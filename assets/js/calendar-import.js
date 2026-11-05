/* Keep every edit in the model; render only the current page. */
document.addEventListener('DOMContentLoaded', () => {
    const $ = id => document.getElementById(id);
    if (!$('calendarImportUpload')) return;
    const modal=$('calendarImportModal'), body=$('calendarImportRows'), confirm=$('calendarImportConfirm');
    let token='', rows=[], categories=[], existing=[], departments=[], programs=[], departmentsReady=false, groupsReady=false, step=1, page=1, editing=null;
    let busy=false, dirty=false, finished=false, allowClose=false, asking=false, management='';
    const selected=new Set();
    const el=(tag,text,className)=>{const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(className)node.className=className;return node;};
    const show=(id,visible)=>$(id)?.classList.toggle('d-none',!visible);
    const showError=message=>{$('calendarImportError').textContent=message;show('calendarImportError',true);$('calendarImportError').scrollIntoView({block:'nearest'});};
    const feedback=message=>{$('ciFeedback').textContent=message;};
    const categoryName=row=>categories.find(cat=>String(cat.category_id)===String(row.category_id))?.category_name||'Uncategorized';
    const dateValid=value=>/^\d{4}-\d{2}-\d{2}$/.test(value||'')&&Number(value.slice(0,4))>=1900&&Number(value.slice(0,4))<=2050&&!Number.isNaN(Date.parse(value))&&new Date(value).toISOString().slice(0,10)===value;
    const timeValid=value=>!value||/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value);
    const shortMonths=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const shortDays=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    function formatDateHuman(value, includeDay=true) {
        if(!dateValid(value))return value||'';
        const [y,m,d]=value.split('-').map(Number);
        const dt=new Date(Date.UTC(y,m-1,d));
        const base=`${shortMonths[m-1]} ${d}, ${y}`;
        return includeDay?`${base} (${shortDays[dt.getUTCDay()]})`:base;
    }
    function formatTime(value) {
        if(!value||!timeValid(value))return value||'';
        const [hStr,mStr]=value.split(':');const h=Number(hStr);
        return `${h%12||12}:${mStr} ${h>=12?'PM':'AM'}`;
    }
    function formatTimeRange(row) {
        if(!row.start_time&&!row.end_time)return '';
        if(row.start_time&&row.end_time)return `${formatTime(row.start_time)} – ${formatTime(row.end_time)}`;
        return formatTime(row.start_time||row.end_time);
    }
    function reindexRows() {
        rows.forEach((row,idx)=>{row.index=idx;});
    }
    function critical(row) {
        if(row.action==='archive')return [];
        const issues=[];
        if(!(row.title||'').trim())issues.push('Enter an event title.');
        if((row.title||'').length>500||(row.location||'').length>500||(row.description||'').length>10000)issues.push('An event field is too long.');
        if(!dateValid(row.start_date)||(row.end_date&&!dateValid(row.end_date)))issues.push('Enter valid dates (1900–2050).');
        if(!categories.some(cat=>String(cat.category_id)===String(row.category_id)))issues.push('Choose a category.');
        const selectedDepartments=departmentIds(row),selectedPrograms=programIds(row);
        if(row.dept_id?.startsWith?.('group:')&&!Array.isArray(row.dept_ids)&&!groupsReady)issues.push('Choose valid departments.');
        if(selectedDepartments.some(id=>!departmentsReady||!departments.some(dept=>String(dept.dept_id)===id)))issues.push('Choose available departments.');
        if(selectedPrograms.some(code=>!programs.some(program=>program.code===code&&(!selectedDepartments.length||selectedDepartments.includes(String(program.dept_id))))))issues.push('Choose College or Graduate School programs in the selected departments.');
        for(const key of ['start_time','end_time'])if(row[key]&&!timeValid(row[key]))issues.push('Correct the event time.');
        const start=`${row.start_date} ${row.start_time||'00:00'}`, end=row.end_date||(row.end_time?row.start_date:'');
        if(end&&`${end} ${row.end_time||'00:00'}`<start)issues.push('End must be after the start.');
        return issues;
    }
    function warnings(row) {
        const issues=critical(row);if(row.reviewed)return issues;
        return [...new Set([...issues,...(row.warnings||[]).filter(message=>{
            if(/category needs review/i.test(message))return !row.category_id;
            if(/title is missing/i.test(message))return !(row.title||'').trim();
            if(/date is missing|invalid date range/i.test(message))return !dateValid(row.start_date)||!!(row.end_date&&!dateValid(row.end_date));
            if(/time is unclear|time is tba|time range|several time/i.test(message))return !row.timeCorrected;
            if(/location is marked as tba/i.test(message))return /^(?:tba|tbd|to be announced|to be determined|\?+)$/i.test((row.location||'').trim());
            if(/several departments/i.test(message))return !departmentIds(row).length&&!programIds(row).length;
            return true;
        })])];
    }
    function flaggedFields(row) {
        if(row.action==='archive'||row.reviewed)return new Set();
        const flags=new Set();
        const w=warnings(row);
        if(!(row.title||'').trim())flags.add('title');
        if(!dateValid(row.start_date))flags.add('start_date');
        if(row.end_date&&(!dateValid(row.end_date)||row.end_date<row.start_date))flags.add('end_date');
        if(!timeValid(row.start_time)||w.some(m=>/time/i.test(m)))flags.add('start_time');
        if(!timeValid(row.end_time))flags.add('end_time');
        if(w.some(m=>/location/i.test(m)))flags.add('location');
        if(!row.category_id)flags.add('category_id');
        if(w.some(m=>/several departments/i.test(m)))flags.add('dept_id');
        return flags;
    }
    const state=row=>row.excluded?'excluded':row.include?'approved':warnings(row).length?'needs':'ready';
    const label=row=>({excluded:'Excluded',approved:'Approved',needs:'Needs Review',ready:row.reviewed?'Reviewed · ready':'Ready'})[state(row)];
    function matches() {
        const q=$('ciSearch').value.trim().toLowerCase();
        const deptFilter=$('ciDepartment')?$('ciDepartment').value:'';
        const result=rows.filter(row=>{
            const haystack=`${row.title||''} ${row.location||''} ${row.description||''} ${audienceName(row)||''}`.toLowerCase();
            return (!q||haystack.includes(q))
                &&(!$('ciMonth').value||row.start_date?.slice(5,7)===$('ciMonth').value)
                &&(!$('ciYear').value||row.start_date?.slice(0,4)===$('ciYear').value)
                &&(!deptFilter||(deptFilter==='general'?!departmentIds(row).length:departmentIds(row).includes(deptFilter)))
                &&(!$('ciCategory').value||($('ciCategory').value==='uncategorized'?!row.category_id:String(row.category_id)===$('ciCategory').value))
                &&(!$('ciStatus').value||state(row)===$('ciStatus').value)
                &&(!$('ciWarningsOnly').checked||warnings(row).length);
        });
        const sort=$('ciSort').value;
        return result.sort((a,b)=>(sort==='title'?(a.title||'').localeCompare(b.title||''):sort==='category'?categoryName(a).localeCompare(categoryName(b)):(a.start_date||'9999').localeCompare(b.start_date||'9999'))||a.index-b.index);
    }
    const pageSize=()=>Number($('ciPageSize').value);
    function pageRows(){const filtered=matches();page=Math.max(1,Math.min(page,Math.ceil(filtered.length/pageSize())||1));return filtered.slice((page-1)*pageSize(),page*pageSize());}
    function duplicateIssues(approved) {
        const keys=new Set(),targets=new Set();
        for(const row of approved){
            const key=`${(row.title||'').trim().toLowerCase().replace(/\s+/g,' ')}|${row.start_date}|${row.start_time||'00:00'}`;
            if((row.event_id&&targets.has(Number(row.event_id)))||(row.action!=='archive'&&keys.has(key)))return true;
            if(row.event_id)targets.add(Number(row.event_id));if(row.action!=='archive')keys.add(key);
        }
        return false;
    }
    function updateSummary() {
        const approved=rows.filter(row=>row.include), excluded=rows.filter(row=>row.excluded).length, needsReview=rows.filter(row=>!row.include&&!row.excluded&&warnings(row).length).length, ready=rows.filter(row=>!row.include&&!row.excluded&&!warnings(row).length).length, pending=rows.length-approved.length-excluded;
        const summaryCards=[
            {name:'All Activities',count:rows.length,filter:'',cls:'total'},
            {name:'Approved',count:approved.length,filter:'approved',cls:'approved'},
            {name:'Needs Review',count:needsReview,filter:'needs',cls:'needs'},
            {name:'Ready',count:ready,filter:'ready',cls:'ready'},
            {name:'Excluded',count:excluded,filter:'excluded',cls:'excluded'}
        ];
        const currentStatus=$('ciStatus').value;
        $('calendarImportSummary').replaceChildren(...summaryCards.map(card=>{
            const box=el('button',undefined,`ci-summary-card ${card.cls}${currentStatus===card.filter?' active':''}`);
            box.type='button';
            box.title=`Filter by ${card.name}`;
            box.append(el('strong',card.count),el('span',card.name));
            box.addEventListener('click',()=>{
                $('ciStatus').value=(currentStatus===card.filter&&card.filter!=='')?'':card.filter;
                page=1;
                render();
            });
            return box;
        }));
        $('calendarImportCount').textContent=`${approved.length} approved · ${needsReview} need review · ${ready} ready`;
        $('ciFooterSummary').textContent=management?'Review all affected events before confirming.':token?`${approved.length} of ${rows.length} approved for import${needsReview?` · ${needsReview} need review`:''}${selected.size?` · ${selected.size} selected`:''}`:'';
        $('ciSelectedCount').textContent=`${selected.size} selected`;
        for(const [id,text] of [['ciApproveSelected','Approve selected'],['ciExcludeSelected','Exclude selected'],['ciRemoveSelected','Remove selected'],['ciApplyCategory','Assign category'],['ciApplyAudience','Assign department/program']]){
            if($(id)){$(id).textContent=`${text} (${selected.size})`;$(id).disabled=busy||!selected.size;}
        }
        if($('ciMergeSelected')){
            const mergeable=rows.filter(row=>selected.has(row.index)&&row.action!=='archive').length;
            $('ciMergeSelected').textContent=`Merge selected (${mergeable})`;
            $('ciMergeSelected').disabled=busy||mergeable<2;
        }
        $('ciSelectMatching').textContent=`Select all ${matches().length} matching`;
        const activeFilters=['ciMonth','ciYear','ciDepartment','ciCategory','ciStatus'].filter(id=>$(id)?.value).length+Number($('ciWarningsOnly').checked)+Number(!!$('ciSearch').value.trim());
        $('ciToggleFilters').textContent=activeFilters?`Filters (${activeFilters})`:'Filters';
        if($('ciResetFilters'))$('ciResetFilters').classList.toggle('d-none',!activeFilters);
        const valid=rows.filter(row=>!row.excluded&&!row.include&&!warnings(row).length&&row.action!=='archive').length;
        $('calendarImportSelectValid').textContent=`Approve all without warnings (${valid})`;
        $('calendarImportSelectValid').disabled=busy||!valid;
        confirm.disabled=busy||(management?!$('calendarManageReviewed').checked:!approved.length||approved.some(row=>critical(row).length)||duplicateIssues(approved));
        const additions=approved.filter(row=>row.action!=='archive'&&!row.event_id).length, updates=approved.filter(row=>row.action!=='archive'&&row.event_id).length, removals=approved.filter(row=>row.action==='archive').length;
        $('ciFinalSummary').textContent=`${approved.length} approved activities: ${additions} additions, ${updates} updates and ${removals} archives. ${excluded} excluded and ${pending} unapproved activities will be left out.`;
        for(const id of ['ciContinue','ciBack','calendarImportExtract'])$(id).disabled=busy;
        const visible=pageRows();$('ciSelectVisible').checked=visible.length>0&&visible.every(row=>selected.has(row.index));$('ciSelectVisible').indeterminate=visible.some(row=>selected.has(row.index))&&!$('ciSelectVisible').checked;
    }
    function change(row,key,value){
        if(key==='dept_id')row.audience_group='all';
        row[key]=value;row.include=false;row.reviewed=false;if(['start_time','end_time'].includes(key))row.timeCorrected=true;dirty=true;updateSummary();
        const badge=body.querySelector(`tr[data-row-index="${row.index}"] .ci-badge`);if(badge){badge.textContent=label(row);badge.className=`ci-badge ${state(row)}`;}
        const approveButton=body.querySelector(`tr[data-row-index="${row.index}"] .ci-approve-action`);if(approveButton){approveButton.textContent='Approve';approveButton.className='btn btn-sm btn-outline-success ci-approve-action';}
        const list=body.querySelector('.ci-editor [data-editor-issues]');if(list)list.replaceChildren(...warnings(row).map(message=>el('li',message)));
    }
    function categorySelect(row) {
        const input=el('select',undefined,'form-select form-select-sm');input.setAttribute('aria-label',`Category for ${row.title||'event'}`);input.append(new Option('Needs Review',''));
        categories.forEach(cat=>input.append(new Option(cat.category_name,cat.category_id)));input.value=row.category_id||'';
        if(!row.category_id&&!row.reviewed)input.classList.add('ci-field-flagged');
        input.addEventListener('change',()=>{change(row,'category_id',input.value);render();});return input;
    }
    function fillDepartments(input,value='') {
        input.replaceChildren(new Option('All departments',''));
        for(const [val,lbl] of [['basic-education','All Basic Education'],['college','All College']]){const option=new Option(lbl,'group:'+val);option.disabled=!groupsReady;input.append(option);}
        departments.forEach(dept=>input.append(new Option(dept.dept_name,dept.dept_id)));
        input.value=value||'';input.disabled=!departmentsReady;
        if(!groupsReady)input.title='Department groups need database/migrate-event-audience-groups.php on this server.';
    }
    function fillPrograms(input,department,value='ALL') {
        input.replaceChildren(new Option(String(department).startsWith('group:')?'All programs in group':department?'All programs in department':'Everyone (all programs)','ALL'));
        programs.filter(program=>!department||String(program.dept_id)===String(department)).forEach(program=>input.append(new Option(`${program.code} — ${program.name}`,program.code)));
        input.value=value||'ALL';if(input.selectedIndex<0)input.value='ALL';
    }
    function departmentIds(row){
        if(Array.isArray(row.dept_ids))return row.dept_ids.map(String);
        const group=String(row.dept_id||'');
        if(group.startsWith('group:'))return departments.filter(dept=>group==='group:basic-education'?dept.department_type==='basic-education':dept.department_type!=='basic-education'&&dept.slug!=='graduate-school'&&dept.dept_name!=='Graduate School').map(dept=>String(dept.dept_id));
        return row.dept_id?[String(row.dept_id)]:[];
    }
    const programIds=row=>Array.isArray(row.programs)?row.programs:row.program&&row.program!=='ALL'?[row.program]:[];
    function setAudience(row,deptIds,codes){
        row.dept_ids=[...new Set(deptIds.map(String))];row.programs=[...new Set(codes)];row.audience_group='all';
        row.dept_id=row.dept_ids.length===1?row.dept_ids[0]:'';
        change(row,'program',row.programs.length===1?row.programs[0]:'ALL');
        const main=body.querySelector(`tr[data-row-index="${row.index}"]`);let pill=main?.querySelector('.ci-dept-pill');
        if(main&&!pill){let meta=main.querySelector('.ci-meta-details');if(!meta){meta=el('div',undefined,'ci-meta-details');main.querySelector('.ci-title').parentElement.append(meta);}pill=el('span',undefined,'ci-detail-pill ci-dept-pill');meta.append(pill);}
        if(pill)pill.textContent=audienceName(row)||'All departments / All programs';
    }
    const audienceName=row=>{
        if(!Array.isArray(row.dept_ids)&&String(row.dept_id).startsWith('group:'))return row.dept_id==='group:basic-education'?'All Basic Education':'All College';
        return [...departmentIds(row).map(id=>departments.find(dept=>String(dept.dept_id)===id)?.dept_name||id),...programIds(row)].join(' / ');
    };
    function approve(targets,cleanOnly=false,individual=false) {
        let accepted=0,skipped=0;
        for(const row of targets){
            if(critical(row).length||(!individual&&warnings(row).length)||(cleanOnly&&row.action==='archive')||duplicateIssues([...rows.filter(other=>other.include&&other!==row),row])){skipped++;continue;}
            row.reviewed=true;row.include=true;row.excluded=false;accepted++;
        }
        dirty=true;feedback(`${accepted} activities approved.${skipped?` ${skipped} skipped: correct errors, duplicates or warnings first.`:''}`);render();
    }
    function mergeRowList(targetRows) {
        const validTargets=targetRows.filter(r=>r&&r.action!=='archive');
        if(validTargets.length<2){feedback('Select at least 2 activities to merge into a single event.');return;}
        const primary=validTargets[0];
        const removeSet=new Set();
        const venueRegex=/\b(gymnasium|gym|auditorium|covered court|court|quadrangle|hall|avr|audio[\s-]*visual|library|lab|laboratory|chapel|church|campus|grounds|room|rm\.?|bldg|building|office|lobby|canteen|zoom|google meet|ms teams|online|virtual)\b/i;
        const activityRegex=/\b(day|celebration|orientation|exam|examination|meeting|assembly|seminar|workshop|training|contest|competition|festival|program|ceremony|graduation|recognition|enrollment|registration|intramurals|holiday|break|suspension|mass|retreat|recollection|blessing|parade|pageant|fair)\b/i;
        const timeRegex=/^(?:time\s*:\s*)?(\d{1,2})(?::(\d{2}))?\s*(am|pm)?(?:\s*(?:-|–|—|\bto\b)\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)?)?$/i;

        for(let i=1;i<validTargets.length;i++){
            const other=validTargets[i];
            removeSet.add(other.index);
            const otherTitle=(other.title||'').trim();
            if(otherTitle){
                // Check if the other row's title was actually a standalone time, location, or description
                const tm=otherTitle.match(timeRegex);
                if(tm&&(tm[2]||tm[3])&&!primary.start_time){
                    const to24=(h,m,ap)=>{let hr=Number(h);const mn=Number(m||0);const a=(ap||'').toLowerCase();if(a)hr=(hr%12)+(a==='pm'?12:0);return `${String(hr).padStart(2,'0')}:${String(mn).padStart(2,'0')}`;};
                    primary.start_time=to24(tm[1],tm[2],tm[3]);
                    if(tm[4])primary.end_time=to24(tm[4],tm[5],tm[6]);
                    primary.timeCorrected=true;
                } else if(!primary.location&&!other.location&&venueRegex.test(otherTitle)&&!activityRegex.test(otherTitle)){
                    primary.location=otherTitle.replace(/^(?:location|venue|place|at|@)\s*[:\-–—]?\s*/i,'').trim();
                } else if(!(primary.title||'').trim()){
                    primary.title=otherTitle;
                } else if(otherTitle.toLowerCase()!==primary.title.trim().toLowerCase()){
                    primary.description=[primary.description,otherTitle].filter(Boolean).join('\n');
                }
            }
            if(!primary.start_date&&other.start_date)primary.start_date=other.start_date;
            if(!primary.end_date&&other.end_date)primary.end_date=other.end_date;
            else if(other.start_date&&primary.start_date&&other.start_date>primary.start_date&&!primary.end_date)primary.end_date=other.start_date;
            if(!primary.start_time&&other.start_time){primary.start_time=other.start_time;primary.timeCorrected=true;}
            if(!primary.end_time&&other.end_time){primary.end_time=other.end_time;primary.timeCorrected=true;}
            if(!primary.location&&other.location)primary.location=other.location;
            else if(other.location&&!primary.location.toLowerCase().includes(other.location.toLowerCase()))primary.location+=`, ${other.location}`;
            if(other.description&&!(primary.description||'').includes(other.description)){
                primary.description=[primary.description,other.description].filter(Boolean).join('\n');
            }
            if(!primary.category_id&&other.category_id)primary.category_id=other.category_id;
            if(Array.isArray(primary.dept_ids)||Array.isArray(other.dept_ids)||Array.isArray(primary.programs)||Array.isArray(other.programs)){
                const ids=[...new Set([...departmentIds(primary),...departmentIds(other)])],codes=[...new Set([...programIds(primary),...programIds(other)])];
                primary.dept_ids=ids;primary.programs=codes;primary.dept_id=ids.length===1?ids[0]:'';primary.program=codes.length===1?codes[0]:'ALL';primary.audience_group='all';
            } else {
                if(!primary.dept_id&&other.dept_id){primary.dept_id=other.dept_id;primary.program=other.program||'ALL';}
                else if((!primary.program||primary.program==='ALL')&&other.program&&other.program!=='ALL'&&(!other.dept_id||String(other.dept_id)===String(primary.dept_id)))primary.program=other.program;
            }
            if(!primary.event_id&&other.event_id){primary.event_id=other.event_id;primary.action=other.action;}
            primary.source=[primary.source,other.source].filter(Boolean).join('\n');
        }
        primary.include=false;primary.reviewed=false;primary.excluded=false;
        rows=rows.filter(r=>!removeSet.has(r.index));
        reindexRows();
        selected.clear();
        editing=primary.index;
        dirty=true;
        feedback(`Merged ${validTargets.length} entries into "${primary.title||'Untitled event'}". Review the combined details below.`);
        render();
    }
    function separateRow(row) {
        if(row.action==='archive')return;
        const pos=rows.findIndex(r=>r.index===row.index);
        if(pos<0)return;
        // Check if title or description has multiple lines or semicolon-separated items
        const titleParts=(row.title||'').split(/\n+|\s*;\s*/).map(s=>s.trim()).filter(Boolean);
        const descLines=(row.description||'').split(/\n+/).map(s=>s.trim()).filter(Boolean);
        const newEntries=[];
        if(titleParts.length>1){
            row.title=titleParts[0];
            for(let i=1;i<titleParts.length;i++){
                newEntries.push({...row,title:titleParts[i],event_id:0,action:'create',include:false,reviewed:false,excluded:false});
            }
        } else if(descLines.length>=1){
            const firstDesc=descLines[0];
            row.description=descLines.slice(1).join('\n');
            newEntries.push({...row,title:firstDesc,description:'',event_id:0,action:'create',include:false,reviewed:false,excluded:false});
        } else {
            newEntries.push({...row,title:`${row.title||''} (Part 2)`.trim(),event_id:0,action:'create',include:false,reviewed:false,excluded:false});
        }
        row.include=false;row.reviewed=false;
        rows.splice(pos+1,0,...newEntries);
        reindexRows();
        editing=rows[pos+1].index;
        dirty=true;
        feedback(`Separated into ${1+newEntries.length} entries. Edit the separated entry below.`);
        render();
    }
    function removeRows(targetRows) {
        if(!targetRows.length)return;
        const removeSet=new Set(targetRows.map(r=>r.index));
        rows=rows.filter(r=>!removeSet.has(r.index));
        reindexRows();
        selected.clear();
        if(editing!==null&&removeSet.has(editing))editing=null;
        dirty=true;
        feedback(`Removed ${targetRows.length} ${targetRows.length===1?'entry':'entries'} from the import preview.`);
        render();
    }
    function button(text,callback,className='btn btn-sm btn-outline-secondary'){const node=el('button',text,className);node.type='button';node.addEventListener('click',callback);return node;}
    function editor(row) {
        const tr=el('tr',undefined,'ci-editor'),td=el('td');td.colSpan=6;const grid=el('div',undefined,'ci-editor-grid');
        const flags=flaggedFields(row);
        for(const [key,text,type,wide] of [['title','Event title','text',true],['location','Location','text',true],['start_date','Start date','date'],['end_date','End date','date'],['start_time','Start time','time'],['end_time','End time','time'],['description','Description','textarea',true]]){
            const wrapper=el('label',undefined,wide?'ci-wide':'');
            const labelSpan=el('span',text);wrapper.append(labelSpan);
            if(flags.has(key))wrapper.append(el('span','Needs review','ci-flag-inline'));
            const input=el(type==='textarea'?'textarea':'input',undefined,'form-control form-control-sm');if(type!=='textarea')input.type=type;
            if(flags.has(key))input.classList.add('ci-field-flagged');
            input.value=row[key]||'';input.dataset.field=key;if(['title','location','description'].includes(key))input.maxLength=key==='description'?10000:500;
            input.addEventListener('input',()=>{input.classList.remove('ci-field-flagged');change(row,key,input.value);});wrapper.append(input);grid.append(wrapper);
        }
        if(row.action!=='archive'){
            const departmentLabel=el('div',undefined,'ci-wide'),department=el('div');department.id='ci-departments-'+row.index;
            const deptHeading=el('label','Departments');deptHeading.id='ci-departments-label-'+row.index;departmentLabel.append(deptHeading,department);
            const programLabel=el('div',undefined,'ci-wide'),program=el('div');program.id='ci-programs-'+row.index;
            const programHeading=el('label','Academic programs (College and Graduate School only)');programHeading.id='ci-programs-label-'+row.index;programLabel.append(programHeading,program);
            const selectedDepartments=departmentIds(row);
            const availablePrograms=ids=>programs.filter(item=>!ids.length||ids.includes(String(item.dept_id))).map(item=>({value:item.code,label:item.name,badge:item.code}));
            const programPicker=new GncMultiSelect(program,{name:'ci-programs[]',allLabel:'All programs',items:availablePrograms(selectedDepartments),initialValues:programIds(row),onChange:codes=>setAudience(row,departmentPicker.getSelected(),codes)});
            const departmentPicker=new GncMultiSelect(department,{name:'ci-departments[]',allLabel:'All departments',items:departments.map(item=>({value:String(item.dept_id),label:item.dept_name})),initialValues:selectedDepartments,onChange:ids=>{
                const items=availablePrograms(ids),allowed=new Set(items.map(item=>item.value)),codes=programPicker.getSelected().filter(code=>allowed.has(code));
                programPicker.config.items=items;programPicker.renderOptions();
                programPicker.allCheckbox.addEventListener('change',()=>{if(programPicker.allCheckbox.checked)programPicker.deselectAll();else programPicker.syncCheckboxesState();});
                programPicker.setSelected(codes,false);programPicker.filterOptions(programPicker.searchInput.value);
                setAudience(row,ids,codes);
            }});
            departmentLabel.append(el('small','Select several departments, or leave empty for all departments.','text-muted'));
            programLabel.append(el('small','Select several programs. Basic Education is targeted through its departments.','text-muted'));
            grid.append(departmentLabel,programLabel);
            const wrapper=el('label','Save action','ci-wide'),target=el('select',undefined,'form-select form-select-sm');target.append(new Option('Add new event',0));
            existing.forEach(event=>target.append(new Option(`Update #${event.event_id}: ${event.title} (${event.start_date.slice(0,10)})`,event.event_id)));target.value=row.event_id||0;
            target.addEventListener('change',()=>{change(row,'event_id',Number(target.value));const old=existing.find(event=>Number(event.event_id)===row.event_id);if(old){row.category_id=old.category_id;row.dept_id=old.audience_group&&old.audience_group!=='all'?'group:'+old.audience_group:old.dept_id||'';row.audience_group='all';row.program=old.program||'ALL';delete row.dept_ids;delete row.programs;if(Array.isArray(old.dept_ids))row.dept_ids=[...old.dept_ids];if(Array.isArray(old.programs))row.programs=[...old.programs];}render();});wrapper.append(target);grid.append(wrapper);
        }
        td.append(grid);const list=el('ul',undefined,'small mt-3 mb-2');list.dataset.editorIssues='';list.setAttribute('aria-live','polite');warnings(row).forEach(message=>list.append(el('li',message)));td.append(list);
        const source=el('details',undefined,'small my-2');source.append(el('summary','Document source and previous values'),el('p',row.source||''));if(row.previous)source.append(el('p',`Previously: ${row.previous.title}; ${row.previous.start_date}; ${row.previous.location||''}; ${row.previous.description||''}`));td.append(source);
        const actions=el('div',undefined,'d-flex gap-2 flex-wrap');
        actions.append(
            button(row.action==='archive'?'Save & Approve Archive':'Save & Approve',()=>{
                if(critical(row).length){feedback('Please fix the highlighted required fields before approving.');return;}
                approve([row],false,true);
                if(row.include)editing=null;
                render();
            },'btn btn-sm btn-success'),
            button('Mark reviewed',()=>{if(critical(row).length){feedback('Correct the errors before marking this activity reviewed.');return;}row.reviewed=true;dirty=true;render();})
        );
        if(row.action!=='archive'){
            actions.append(button('Separate into 2 events',()=>separateRow(row),'btn btn-sm btn-outline-primary'));
            const filtered=matches();
            const curPos=filtered.findIndex(r=>r.index===row.index);
            if(curPos>=0&&curPos+1<filtered.length&&filtered[curPos+1].action!=='archive'){
                actions.append(button('Merge with next row',()=>mergeRowList([row,filtered[curPos+1]]),'btn btn-sm btn-outline-primary'));
            }
        }
        actions.append(
            button(row.excluded?'Restore':'Exclude',()=>{row.include=false;row.excluded=!row.excluded;dirty=true;render();}),
            button('Remove entry',()=>removeRows([row]),'btn btn-sm btn-outline-danger'),
            button('Close',()=>{editing=null;render();})
        );
        td.append(actions);tr.append(td);return tr;
    }
    function render() {
        const years=new Set(Array.from($('ciYear').options,option=>option.value));
        rows.forEach(row=>{const year=row.start_date?.slice(0,4);if(year&&!years.has(year)){years.add(year);$('ciYear').append(new Option(year,year));}});
        body.querySelectorAll('.gnc-ms-wrap').forEach(wrap=>wrap._gncMsInstance?.destroy());
        body.replaceChildren();const filtered=matches(),visible=pageRows();
        visible.forEach(row=>{
            const tr=el('tr');tr.dataset.rowIndex=row.index;
            if(row.include)tr.classList.add('ci-row-approved');
            else if(warnings(row).length)tr.classList.add('ci-row-flagged');
            const flags=flaggedFields(row);
            const checkCell=el('td'),check=el('input',undefined,'form-check-input');check.type='checkbox';check.checked=selected.has(row.index);check.setAttribute('aria-label',`Select ${row.title||'Untitled activity'}`);
            check.addEventListener('change',()=>{if(check.checked)selected.add(row.index);else selected.delete(row.index);updateSummary();});checkCell.append(check);

            const title=el('td'),titleText=el('div',undefined,'ci-title'),issues=warnings(row);
            if(issues.length){const warning=el('i',undefined,'bi bi-exclamation-triangle-fill ci-warning');warning.title=issues.join('\n');warning.setAttribute('aria-label','Needs attention');titleText.append(warning);}
            if((row.title||'').trim()){
                titleText.append(document.createTextNode(row.title));
            } else {
                titleText.append(el('span','Missing event title — Needs review','ci-flag-chip'));
            }
            title.append(titleText);

            if(row.action==='archive'){
                if(audienceName(row))title.append(el('div',audienceName(row),'ci-description'));
                title.append(el('div','Possible removal · archive if approved','ci-description'));
            } else {
                const metaRow=el('div',undefined,'ci-meta-details');
                if(audienceName(row)){
                    metaRow.append(el('span',audienceName(row),'ci-detail-pill ci-dept-pill'));
                } else if(flags.has('dept_id')){
                    metaRow.append(el('span','Department: Needs review','ci-flag-chip'));
                }
                if(row.location){
                    const locSpan=el('span',`Venue: ${row.location}`,flags.has('location')?'ci-detail-pill ci-flag-chip':'ci-detail-pill');
                    metaRow.append(locSpan);
                } else if(flags.has('location')){
                    metaRow.append(el('span','Location: Needs review','ci-flag-chip'));
                }
                if(metaRow.childNodes.length)title.append(metaRow);
                if(row.description){
                    title.append(el('div',row.description,'ci-description'));
                }
            }

            // Show inline warning explanations directly so admins don't need to guess why a row is flagged
            if(issues.length){
                const warnList=el('div',undefined,'ci-warning-list');
                issues.forEach(msg=>warnList.append(el('span',`⚠ ${msg}`,'ci-flag-chip')));
                title.append(warnList);
            }

            const dates=el('td');dates.style.whiteSpace='nowrap';
            if(row.start_date&&dateValid(row.start_date)){
                dates.append(el('div',formatDateHuman(row.start_date),'fw-semibold'));
                if(row.end_date&&row.end_date!==row.start_date)dates.append(el('div',`to ${formatDateHuman(row.end_date,false)}`,'small text-muted'));
            } else {
                dates.append(el('span',row.start_date||'Date missing','ci-flag-chip'));
            }
            const timeText=formatTimeRange(row);
            if(timeText){
                dates.append(el('div',timeText,flags.has('start_time')?'small ci-flag-chip mt-1':'small text-muted'));
            } else if(flags.has('start_time')){
                dates.append(el('div','Time TBA / unclear','small ci-flag-chip mt-1'));
            } else if(row.action!=='archive'){
                dates.append(el('div','All day','small text-muted'));
            }

            const category=el('td');category.append(categorySelect(row));
            const status=el('td');status.append(el('span',label(row),`ci-badge ${state(row)}`));
            const actionCell=el('td',undefined,'ci-actions-cell'),action=el('div',undefined,'ci-row-actions');
            actionCell.append(action);
            if(!row.excluded){
                const quickApprove=button(
                    row.include?'Approved ✓':'Approve',
                    ()=>{
                        if(row.include){
                            row.include=false;dirty=true;render();
                        } else if(critical(row).length){
                            editing=row.index;feedback('Please complete the required fields below before approving.');render();
                        } else {
                            approve([row],false,true);
                        }
                    },
                    row.include?'btn btn-sm btn-success':'btn btn-sm btn-outline-success'
                );
                quickApprove.title=row.include?'Click to unapprove':'Approve this activity for import';
                quickApprove.classList.add('ci-approve-action');
                action.append(quickApprove);
            }
            const edit=button(editing===row.index?'Close':'Edit',()=>{editing=editing===row.index?null:row.index;render();});
            edit.setAttribute('aria-expanded',String(editing===row.index));
            action.append(edit);
            if(row.action!=='archive'){
                const splitBtn=button('Separate',()=>separateRow(row),'btn btn-sm btn-outline-secondary');
                splitBtn.title='Separate this entry into multiple events';
                action.append(splitBtn);
            }
            const removeBtn=button('Remove',()=>removeRows([row]),'btn btn-sm btn-outline-danger');
            removeBtn.title='Remove this entry from the import preview';
            action.append(removeBtn);

            tr.append(checkCell,title,dates,category,status,actionCell);body.append(tr);if(editing===row.index)body.append(editor(row));
        });
        if(editing!==null&&document.getElementById('ci-departments-'+editing))GncAdminFormAudience.enhance([['ci-departments-'+editing,'ci-departments-label-'+editing,'Search departments'],['ci-programs-'+editing,'ci-programs-label-'+editing,'Search programs']]);
        if(!visible.length){const tr=el('tr'),td=el('td','No activities match these filters.','text-center text-muted py-4');td.colSpan=6;tr.append(td);body.append(tr);}
        $('ciPageInfo').textContent=`Showing ${filtered.length?(page-1)*pageSize()+1:0}–${Math.min(page*pageSize(),filtered.length)} of ${filtered.length} matching · ${rows.length} total · Page ${page} of ${Math.max(1,Math.ceil(filtered.length/pageSize()))}`;
        $('ciPrevious').disabled=page===1;$('ciNext').disabled=page*pageSize()>=filtered.length;updateSummary();
    }
    function setStep(next) {
        if(busy||next<1||next>3||(next>1&&!token)||management)return;if(step!==next)show('calendarImportError',false);step=next;
        show('calendarImportUpload',step===1);show('calendarImportManagement',step===1);show('calendarImportPreview',step===2);show('ciFinal',step===3);show('calendarImportSummary',!!token);show('ciBack',step>1);show('ciContinue',step<3&&!!token);show('calendarImportConfirm',step===3);
        document.querySelectorAll('[data-ci-step]').forEach(node=>{const number=Number(node.dataset.ciStep);node.classList.toggle('active',number===step);node.disabled=number>1&&!token;node.removeAttribute('aria-current');if(number===step)node.setAttribute('aria-current','step');});
        $('calendarImportReviewHeading').textContent='Review and approve activities';
        $('calendarImportReviewHelp').textContent='Clean activities are automatically approved. Use Needs Review to inspect flagged items, correct details with Edit, and approve the activities you want to import. Pending and excluded activities are left out. Continue to the final summary when ready.';
        $('ciContinue').textContent=step===2?'Review final summary':'Continue review';render();
    }
    async function request(action,data=new FormData(),useToken=token) {
        data.set('ajax_action',action);data.set('csrf_token',$('csrfToken').value);if(useToken)data.set('import_token',useToken);
        const response=await fetch(window.location.pathname,{method:'POST',body:data});let result;
        try{result=await response.json();}catch{throw new Error('The server could not read this request. Check the file size or refresh and try again.');}
        if(!result.success)throw new Error(result.message||result.error||'The request could not be completed.');return result;
    }
    async function discardPrompt(){return window.AdminDialogs?window.AdminDialogs.confirm('Discard your calendar review? Edits, selections and approvals in this workspace will be lost.',{title:'Discard calendar review?',confirmText:'Discard review',cancelText:'Keep reviewing',danger:true}):window.confirm('Discard your calendar review and unsaved changes?');}
    function updateSelectedFileMeta(file) {
        const meta=$('calendarImportFileMeta');
        const zone=$('calendarImportDropzone');
        if(!file){
            if(meta)meta.textContent='Supports tables, bulleted lists, and paragraphs up to 5 MB';
            zone?.classList.remove('has-file');
            return;
        }
        const sizeKb=Math.max(1,Math.round(file.size/1024));
        if(meta)meta.textContent=`Selected: ${file.name} (${sizeKb} KB)`;
        zone?.classList.add('has-file');
        const nameInput=$('calendarImportName');
        if(nameInput&&!nameInput.disabled&&!nameInput.value.trim()){
            const base=file.name.replace(/\.docx$/i,'').replace(/[_-]+/g,' ').replace(/\s+/g,' ').trim();
            if(base)nameInput.value=base;
        }
        const yearMatch=file.name.match(/\b(20\d{2})\b/);
        if(yearMatch&&$('calendarImportYear'))$('calendarImportYear').value=yearMatch[1];
    }
    $('calendarImportFile')?.addEventListener('change',()=>updateSelectedFileMeta($('calendarImportFile').files[0]));
    if($('calendarImportDropzone')){
        const zone=$('calendarImportDropzone');
        ['dragenter','dragover'].forEach(evt=>zone.addEventListener(evt,e=>{e.preventDefault();zone.classList.add('dragover');}));
        ['dragleave','drop'].forEach(evt=>zone.addEventListener(evt,e=>{e.preventDefault();zone.classList.remove('dragover');}));
        zone.addEventListener('drop',e=>{
            const files=e.dataTransfer?.files;
            if(files?.length&&$('calendarImportFile')){
                $('calendarImportFile').files=files;
                updateSelectedFileMeta(files[0]);
            }
        });
    }
    $('calendarImportUpload').addEventListener('submit',async event=>{
        event.preventDefault();if(busy)return;const file=$('calendarImportFile').files[0];if(!file||file.size>5*1024*1024||!/\.docx$/i.test(file.name)){showError('Choose a .docx file up to 5 MB.');return;}
        if(token&&dirty&&!(await discardPrompt()))return;busy=true;updateSummary();show('calendarImportError',false);$('calendarImportExtract').textContent='Extracting…';$('calendarImportFileStatus').textContent=`${file.name} · Uploading and extracting activities…`;
        try{
            const result=await request('import_preview',new FormData($('calendarImportUpload')));if(token)await request('import_cancel',new FormData(),token).catch(()=>{});
            token=result.token;categories=result.categories;existing=result.existing;departments=result.departments||[];programs=result.programs||[];departmentsReady=!!result.departments_ready;groupsReady=!!result.audience_groups_ready;
            rows=result.rows.map((row,index)=>({program:'ALL',...row,dept_id:row.audience_group&&row.audience_group!=='all'?'group:'+row.audience_group:row.dept_id||'',audience_group:'all',index,source_index:index,include:false,reviewed:false,excluded:false}));
            selected.clear();editing=null;page=1;dirty=true;finished=false;
            fillDepartments($('ciBulkDepartment'));fillPrograms($('ciBulkProgram'),'');
            if($('ciDepartment')){
                fillDepartments($('ciDepartment'));
                $('ciDepartment').disabled=false;
                $('ciDepartment').insertBefore(new Option('General / Institutional (No specific dept)','general'),$('ciDepartment').options[1]||null);
            }
            for(const id of ['ciCategory','ciBulkCategory']){const select=$(id);while(select.options.length>(id==='ciCategory'?2:1))select.remove(select.options.length-1);categories.forEach(cat=>select.append(new Option(cat.category_name,cat.category_id)));}
            $('ciYear').replaceChildren(new Option('All years',''));[...new Set(rows.map(row=>row.start_date?.slice(0,4)).filter(Boolean))].sort().forEach(year=>$('ciYear').append(new Option(year,year)));
            // Auto-approve all clean rows without warnings so the admin only needs to review flagged items
            let autoApproved=0, flaggedCount=0;
            for(const row of rows){
                if(!critical(row).length&&!warnings(row).length&&row.action!=='archive'&&!duplicateIssues([...rows.filter(other=>other.include&&other!==row),row])){
                    row.reviewed=true;row.include=true;row.excluded=false;autoApproved++;
                } else {
                    flaggedCount++;
                }
            }
            const fb=$('ciFeedback');
            fb.replaceChildren();
            const banner=el('div',undefined,'ci-feedback-banner');
            banner.append(el('span',`✓ ${autoApproved} clean ${autoApproved===1?'activity was':'activities were'} automatically approved.${flaggedCount?` ${flaggedCount} ${flaggedCount===1?'activity has':'activities have'} notes or TBA details to review.`:' All extracted activities are ready to import!'}`));
            if(flaggedCount>0){
                const reviewBtn=button(`Review ${flaggedCount} flagged ${flaggedCount===1?'item':'items'}`,()=>{
                    $('ciStatus').value='needs';page=1;render();
                },'btn btn-sm btn-warning ms-2');
                banner.append(reviewBtn);
            }
            fb.append(banner);
            $('calendarImportFileStatus').textContent=`${file.name} · Extraction complete · ${rows.length} ${rows.length===1?'activity':'activities'} detected`;busy=false;setStep(2);
        }catch(e){showError(e.message);$('calendarImportFileStatus').textContent=`${file.name} · Extraction failed. Your previous review is still available.`;}finally{busy=false;$('calendarImportExtract').textContent='Extract Activities';updateSummary();}
    });
    for(const id of ['ciSearch','ciMonth','ciYear','ciDepartment','ciCategory','ciStatus','ciSort','ciWarningsOnly','ciPageSize'])$(id)?.addEventListener(id==='ciSearch'?'input':'change',()=>{page=1;render();});
    $('ciResetFilters')?.addEventListener('click',()=>{
        for(const id of ['ciSearch','ciMonth','ciYear','ciDepartment','ciCategory','ciStatus'])if($(id))$(id).value='';
        if($('ciWarningsOnly'))$('ciWarningsOnly').checked=false;
        page=1;render();
    });
    for(const [id,selector] of [['ciToggleFilters','.ci-toolbar'],['ciToggleBulk','.ci-bulk']])$(id).addEventListener('click',()=>{const expanded=$(id).closest(selector).classList.toggle('ci-expanded');$(id).setAttribute('aria-expanded',String(expanded));});
    $('ciPrevious').addEventListener('click',()=>{page--;render();});$('ciNext').addEventListener('click',()=>{page++;render();});
    $('ciSelectVisible').addEventListener('change',event=>{pageRows().forEach(row=>event.target.checked?selected.add(row.index):selected.delete(row.index));render();});
    $('ciSelectMatching').addEventListener('click',()=>{matches().forEach(row=>selected.add(row.index));render();});$('ciClearSelection').addEventListener('click',()=>{selected.clear();render();});
    $('ciApproveSelected').addEventListener('click',()=>approve(rows.filter(row=>selected.has(row.index))));
    $('ciMergeSelected')?.addEventListener('click',()=>mergeRowList(rows.filter(row=>selected.has(row.index))));
    $('ciExcludeSelected').addEventListener('click',()=>{const targets=rows.filter(row=>selected.has(row.index));targets.forEach(row=>{row.include=false;row.excluded=true;});dirty=true;feedback(`${targets.length} activities excluded.`);render();});
    $('ciRemoveSelected')?.addEventListener('click',()=>removeRows(rows.filter(row=>selected.has(row.index))));
    $('ciApplyCategory').addEventListener('click',()=>{const category=$('ciBulkCategory').value;if(!category){feedback('Choose a category before assigning it.');return;}const targets=rows.filter(row=>selected.has(row.index));targets.forEach(row=>change(row,'category_id',category));feedback(`Category changed for ${targets.length} activities. Review them before approving.`);render();});
    $('ciBulkDepartment').addEventListener('change',()=>fillPrograms($('ciBulkProgram'),$('ciBulkDepartment').value,$('ciBulkProgram').value));
    $('ciApplyAudience').addEventListener('click',()=>{
        const targets=rows.filter(row=>selected.has(row.index)&&row.action!=='archive');
        targets.forEach(row=>{delete row.dept_ids;delete row.programs;change(row,'dept_id',$('ciBulkDepartment').value);change(row,'program',$('ciBulkProgram').value);if(!String(row.dept_id).startsWith('group:')){row.dept_ids=row.dept_id?[String(row.dept_id)]:[];row.programs=row.program!=='ALL'?[row.program]:[];}});
        feedback(`Department/program changed for ${targets.length} activities. Review them before approving.`);render();
    });
    $('calendarImportSelectValid').addEventListener('click',()=>approve(rows.filter(row=>!row.excluded&&!row.include),true));
    $('ciBack').addEventListener('click',()=>setStep(step-1));$('ciContinue').addEventListener('click',()=>setStep(step+1));document.querySelectorAll('[data-ci-step]').forEach(node=>node.addEventListener('click',()=>setStep(Number(node.dataset.ciStep))));
    $('calendarImportSeries').addEventListener('change',event=>{$('calendarImportName').required=!event.target.value;$('calendarImportName').disabled=!!event.target.value;});
    async function manageCalendar(operation) {
        if(busy)return;const series=$('calendarManageSeries').value;if(!series){showError('Choose an imported calendar first.');return;}if(token&&dirty&&!(await discardPrompt()))return;busy=true;show('calendarImportError',false);
        try{
            const data=new FormData();data.set('series_id',series);data.set('calendar_action',operation);const result=await request('import_manage_preview',data);if(token)await request('import_cancel',new FormData(),token).catch(()=>{});
            token=result.token;management=result.calendar_action;rows=[];selected.clear();dirty=true;const deleting=management==='delete';$('calendarManageHeading').textContent=`${deleting?'Delete':'Archive'}: ${result.calendar_name}`;
            $('calendarManageExplanation').textContent=`${result.rows.length} imported events will be ${deleting?'permanently deleted. This cannot be undone.':'archived and hidden from the public calendar. Records will be kept.'} Manually created events are preserved.`;
            $('calendarManageReviewLabel').textContent=deleting?'I reviewed the affected events and confirm permanent deletion of this imported calendar.':'I reviewed the affected events and confirm archiving this imported calendar.';
            $('calendarManageRows').replaceChildren(...result.rows.map(row=>{const tr=el('tr');tr.append(...['title','start_date','end_date','location'].map(key=>el('td',row[key]||'')));return tr;}));
            $('calendarManageReviewed').checked=false;for(const id of ['calendarImportUpload','calendarImportManagement','calendarImportPreview','calendarImportSummary','ciFinal','ciContinue','ciBack'])show(id,false);show('calendarManagePreview',true);show('calendarImportConfirm',true);
            confirm.textContent=deleting?'Confirm Delete Calendar':'Confirm Archive Calendar';confirm.classList.toggle('btn-danger',deleting);confirm.classList.toggle('btn-gnc-gold',!deleting);
        }catch(e){showError(e.message);}finally{busy=false;updateSummary();}
    }
    $('calendarManageArchive').addEventListener('click',()=>manageCalendar('archive'));$('calendarManageDelete')?.addEventListener('click',()=>manageCalendar('delete'));$('calendarManageReviewed').addEventListener('change',updateSummary);
    confirm.addEventListener('click',async()=>{
        if(busy||confirm.disabled||(!management&&step!==3))return;busy=true;updateSummary();show('calendarImportError',false);confirm.textContent='Saving…';
        try{
            const data=new FormData();if(management)data.set('reviewed_all',$('calendarManageReviewed').checked?'1':'0');
            const fields=['source_index','action','event_id','title','description','location','start_date','end_date','start_time','end_time','category_id','dept_id','program','dept_ids','programs','include','reviewed'];
            data.set('import_rows',JSON.stringify(rows.map(row=>Object.fromEntries(fields.map(key=>[key,row[key]])))));data.set('import_status',$('ciImportStatus')?.value||'draft');
            const result=await request('import_confirm',data);finished=true;dirty=false;sessionStorage.setItem('calendarImportMessage',result.message);window.location.reload();
        }catch(e){showError(e.message);}finally{busy=false;updateSummary();confirm.textContent=management?(management==='delete'?'Confirm Delete Calendar':'Confirm Archive Calendar'):'Confirm Import';}
    });
    modal.addEventListener('hide.bs.modal',async event=>{
        if(busy){event.preventDefault();return;}if(allowClose||finished||!dirty)return;event.preventDefault();if(asking)return;asking=true;
        try{if(await discardPrompt()){allowClose=true;bootstrap.Modal.getInstance(modal).hide();}}finally{asking=false;}
    });
    modal.addEventListener('hidden.bs.modal',()=>{
        const oldToken=token;token='';rows=[];selected.clear();categories=[];existing=[];management='';editing=null;dirty=false;finished=false;allowClose=false;page=1;if(oldToken)request('import_cancel',new FormData(),oldToken).catch(()=>{});
        show('calendarManagePreview',false);show('calendarImportError',false);confirm.classList.remove('btn-danger');confirm.classList.add('btn-gnc-gold');confirm.textContent='Confirm Import';$('ciFeedback').textContent='';$('calendarImportFileStatus').textContent='Step 1 of 3 · Choose your calendar document to get started.';updateSelectedFileMeta(null);setStep(1);
    });
    window.addEventListener('beforeunload',event=>{if(dirty&&!finished){event.preventDefault();event.returnValue='';}});setStep(1);
});
document.addEventListener('DOMContentLoaded',()=>{
    const message=sessionStorage.getItem('calendarImportMessage');if(!message)return;sessionStorage.removeItem('calendarImportMessage');const notice=document.createElement('div');notice.className='alert alert-success';notice.setAttribute('role','status');notice.textContent=message;document.querySelector('.page-header')?.after(notice);
});
