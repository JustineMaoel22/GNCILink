/* Keep every edit in the model; render only the current page. */
document.addEventListener('DOMContentLoaded', () => {
    const $ = id => document.getElementById(id);
    if (!$('calendarImportUpload')) return;
    const modal=$('calendarImportModal'), body=$('calendarImportRows'), confirm=$('calendarImportConfirm');
    let token='', rows=[], categories=[], existing=[], departments=[], programs=[], departmentsReady=false, groupsReady=false, step=1, page=1, editing=null;
    let busy=false, dirty=false, finished=false, allowClose=false, asking=false, management='';
    const selected=new Set();
    const el=(tag,text,className)=>{const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(className)node.className=className;return node;};
    const show=(id,visible)=>$(id).classList.toggle('d-none',!visible);
    const showError=message=>{$('calendarImportError').textContent=message;show('calendarImportError',true);$('calendarImportError').scrollIntoView({block:'nearest'});};
    const feedback=message=>{$('ciFeedback').textContent=message;};
    const categoryName=row=>categories.find(cat=>String(cat.category_id)===String(row.category_id))?.category_name||'Uncategorized';
    const dateValid=value=>/^\d{4}-\d{2}-\d{2}$/.test(value||'')&&Number(value.slice(0,4))>=1900&&Number(value.slice(0,4))<=2050&&!Number.isNaN(Date.parse(value))&&new Date(value).toISOString().slice(0,10)===value;
    function critical(row) {
        if(row.action==='archive')return [];
        const issues=[];
        if(!(row.title||'').trim())issues.push('Enter an event title.');
        if((row.title||'').length>500||(row.location||'').length>500||(row.description||'').length>10000)issues.push('An event field is too long.');
        if(!dateValid(row.start_date)||(row.end_date&&!dateValid(row.end_date)))issues.push('Enter valid dates (1900–2050).');
        if(!categories.some(cat=>String(cat.category_id)===String(row.category_id)))issues.push('Choose a category.');
        if(row.dept_id?.startsWith?.('group:')){if(!groupsReady||!['group:basic-education','group:college'].includes(row.dept_id)||row.program!=='ALL')issues.push('Choose a valid department group with all programs.');}
        else if(row.dept_id&&(!departmentsReady||!departments.some(dept=>String(dept.dept_id)===String(row.dept_id))))issues.push('Choose an available department.');
        if(row.program&&row.program!=='ALL'&&!programs.some(program=>program.code===row.program&&(!row.dept_id||String(program.dept_id)===String(row.dept_id))))issues.push('Choose a program in the selected department.');
        for(const key of ['start_time','end_time'])if(row[key]&&!/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(row[key]))issues.push('Correct the event time.');
        const start=`${row.start_date} ${row.start_time||'00:00'}`, end=row.end_date||(row.end_time?row.start_date:'');
        if(end&&`${end} ${row.end_time||'00:00'}`<start)issues.push('End must be after the start.');
        return issues;
    }
    function warnings(row) {
        const issues=critical(row);if(row.reviewed)return issues;
        return [...new Set([...issues,...(row.warnings||[]).filter(message=>{
            if(/category needs review/i.test(message))return !row.category_id;
            if(/date is missing|invalid date range/i.test(message))return !dateValid(row.start_date)||!!(row.end_date&&!dateValid(row.end_date));
            if(/time is unclear|time range|several time/i.test(message))return !row.timeCorrected;
            return true;
        })])];
    }
    const state=row=>row.excluded?'excluded':row.include?'approved':warnings(row).length?'needs':'ready';
    const label=row=>({excluded:'Excluded',approved:'Approved',needs:'Needs Review',ready:row.reviewed?'Reviewed · ready':'Ready'})[state(row)];
    function matches() {
        const q=$('ciSearch').value.trim().toLowerCase();
        const result=rows.filter(row=>(!q||row.title.toLowerCase().includes(q))
            &&(!$('ciMonth').value||row.start_date?.slice(5,7)===$('ciMonth').value)
            &&(!$('ciYear').value||row.start_date?.slice(0,4)===$('ciYear').value)
            &&(!$('ciCategory').value||($('ciCategory').value==='uncategorized'?!row.category_id:String(row.category_id)===$('ciCategory').value))
            &&(!$('ciStatus').value||state(row)===$('ciStatus').value)
            &&(!$('ciWarningsOnly').checked||warnings(row).length));
        const sort=$('ciSort').value;
        return result.sort((a,b)=>(sort==='title'?a.title.localeCompare(b.title):sort==='category'?categoryName(a).localeCompare(categoryName(b)):(a.start_date||'9999').localeCompare(b.start_date||'9999'))||a.index-b.index);
    }
    const pageSize=()=>Number($('ciPageSize').value);
    function pageRows(){const filtered=matches();page=Math.max(1,Math.min(page,Math.ceil(filtered.length/pageSize())||1));return filtered.slice((page-1)*pageSize(),page*pageSize());}
    function duplicateIssues(approved) {
        const keys=new Set(),targets=new Set();
        for(const row of approved){
            const key=`${row.title.trim().toLowerCase().replace(/\s+/g,' ')}|${row.start_date}|${row.start_time||'00:00'}`;
            if((row.event_id&&targets.has(Number(row.event_id)))||(row.action!=='archive'&&keys.has(key)))return true;
            if(row.event_id)targets.add(Number(row.event_id));if(row.action!=='archive')keys.add(key);
        }
        return false;
    }
    function updateSummary() {
        const approved=rows.filter(row=>row.include), excluded=rows.filter(row=>row.excluded).length, pending=rows.length-approved.length-excluded;
        const counts={Total:rows.length,Approved:approved.length,'Needs Review':rows.filter(row=>!row.include&&!row.excluded&&warnings(row).length).length,Excluded:excluded,Pending:pending};
        $('calendarImportSummary').replaceChildren(...Object.entries(counts).map(([name,count])=>{const box=el('div');box.append(el('strong',count),el('span',name));return box;}));
        $('calendarImportCount').textContent=`${approved.length} approved · ${pending} pending`;
        $('ciFooterSummary').textContent=management?'Review all affected events before confirming.':token?`${approved.length} approved for import · ${selected.size} selected`:'';
        $('ciSelectedCount').textContent=`${selected.size} selected`;
        for(const [id,text] of [['ciApproveSelected','Approve selected'],['ciExcludeSelected','Exclude selected'],['ciApplyCategory','Assign category'],['ciApplyAudience','Assign department/program']]){$(id).textContent=`${text} (${selected.size})`;$(id).disabled=busy||!selected.size;}
        $('ciSelectMatching').textContent=`Select all ${matches().length} matching`;
        const activeFilters=['ciMonth','ciYear','ciCategory','ciStatus'].filter(id=>$(id).value).length+Number($('ciWarningsOnly').checked);
        $('ciToggleFilters').textContent=activeFilters?`Filters (${activeFilters})`:'Filters';
        const valid=rows.filter(row=>!row.excluded&&!row.include&&!warnings(row).length&&row.action!=='archive').length;
        $('calendarImportSelectValid').textContent=`Approve all without warnings (${valid})`;
        confirm.disabled=busy||(management?!$('calendarManageReviewed').checked:!approved.length||approved.some(row=>critical(row).length)||duplicateIssues(approved));
        const additions=approved.filter(row=>row.action!=='archive'&&!row.event_id).length, updates=approved.filter(row=>row.action!=='archive'&&row.event_id).length, removals=approved.filter(row=>row.action==='archive').length;
        $('ciFinalSummary').textContent=`${approved.length} approved activities: ${additions} additions, ${updates} updates and ${removals} archives. ${excluded} excluded and ${pending} pending activities will be left out.`;
        for(const id of ['ciContinue','ciBack','calendarImportExtract'])$(id).disabled=busy;
        const visible=pageRows();$('ciSelectVisible').checked=visible.length>0&&visible.every(row=>selected.has(row.index));$('ciSelectVisible').indeterminate=visible.some(row=>selected.has(row.index))&&!$('ciSelectVisible').checked;
    }
    function change(row,key,value){
        if(key==='dept_id')row.audience_group='all';
        row[key]=value;row.include=false;row.reviewed=false;if(['start_time','end_time'].includes(key))row.timeCorrected=true;dirty=true;updateSummary();
        const badge=body.querySelector(`tr[data-row-index="${row.index}"] .ci-badge`);if(badge){badge.textContent=label(row);badge.className=`ci-badge ${state(row)}`;}
        const list=body.querySelector('.ci-editor [data-editor-issues]');if(list)list.replaceChildren(...warnings(row).map(message=>el('li',message)));
    }
    function categorySelect(row) {
        const input=el('select',undefined,'form-select form-select-sm');input.setAttribute('aria-label',`Category for ${row.title}`);input.append(new Option('Needs Review',''));
        categories.forEach(cat=>input.append(new Option(cat.category_name,cat.category_id)));input.value=row.category_id||'';
        input.addEventListener('change',()=>{change(row,'category_id',input.value);render();});return input;
    }
    function fillDepartments(input,value='') {
        input.replaceChildren(new Option('All departments',''));
        for(const [value,label] of [['basic-education','All Basic Education'],['college','All College']]){const option=new Option(label,'group:'+value);option.disabled=!groupsReady;input.append(option);}
        departments.forEach(dept=>input.append(new Option(dept.dept_name,dept.dept_id)));
        input.value=value||'';input.disabled=!departmentsReady;
        if(!groupsReady)input.title='Department groups need database/migrate-event-audience-groups.php on this server.';
    }
    function fillPrograms(input,department,value='ALL') {
        input.replaceChildren(new Option(String(department).startsWith('group:')?'All programs in group':department?'All programs in department':'Everyone (all programs)','ALL'));
        programs.filter(program=>!department||String(program.dept_id)===String(department)).forEach(program=>input.append(new Option(`${program.code} — ${program.name}`,program.code)));
        input.value=value||'ALL';if(input.selectedIndex<0)input.value='ALL';
    }
    const audienceName=row=>[({'group:basic-education':'All Basic Education','group:college':'All College'})[row.dept_id]||departments.find(dept=>String(dept.dept_id)===String(row.dept_id))?.dept_name,row.program&&row.program!=='ALL'?row.program:''].filter(Boolean).join(' · ');
    function approve(targets,cleanOnly=false,individual=false) {
        let accepted=0,skipped=0;
        for(const row of targets){
            if(critical(row).length||(!individual&&warnings(row).length)||(cleanOnly&&row.action==='archive')||duplicateIssues([...rows.filter(other=>other.include&&other!==row),row])){skipped++;continue;}
            row.reviewed=true;row.include=true;row.excluded=false;accepted++;
        }
        dirty=true;feedback(`${accepted} activities approved.${skipped?` ${skipped} skipped: correct errors, duplicates or warnings first.`:''}`);render();
    }
    function button(text,callback,className='btn btn-sm btn-outline-secondary'){const node=el('button',text,className);node.type='button';node.addEventListener('click',callback);return node;}
    function editor(row) {
        const tr=el('tr',undefined,'ci-editor'),td=el('td');td.colSpan=6;const grid=el('div',undefined,'ci-editor-grid');
        for(const [key,text,type,wide] of [['title','Event title','text',true],['location','Location','text',true],['start_date','Start date','date'],['end_date','End date','date'],['start_time','Start time','time'],['end_time','End time','time'],['description','Description','textarea',true]]){
            const wrapper=el('label',text,wide?'ci-wide':''),input=el(type==='textarea'?'textarea':'input',undefined,'form-control form-control-sm');if(type!=='textarea')input.type=type;
            input.value=row[key]||'';input.dataset.field=key;if(['title','location','description'].includes(key))input.maxLength=key==='description'?10000:500;
            input.addEventListener('input',()=>change(row,key,input.value));wrapper.append(input);grid.append(wrapper);
        }
        if(row.action!=='archive'){
            const departmentLabel=el('label','Department'),department=el('select',undefined,'form-select form-select-sm');department.dataset.field='dept_id';
            const programLabel=el('label','Program (optional)'),program=el('select',undefined,'form-select form-select-sm');program.dataset.field='program';
            fillDepartments(department,row.dept_id);fillPrograms(program,row.dept_id,row.program);
            department.addEventListener('change',()=>{change(row,'dept_id',department.value);fillPrograms(program,department.value,row.program);if(program.value!==row.program)change(row,'program',program.value);render();});
            program.addEventListener('change',()=>{change(row,'program',program.value);render();});
            departmentLabel.append(department);if(!departmentsReady)departmentLabel.append(el('small','Run database/migrate-event-departments.php to enable departments.','text-muted'));
            programLabel.append(program);grid.append(departmentLabel,programLabel);
            const wrapper=el('label','Save action','ci-wide'),target=el('select',undefined,'form-select form-select-sm');target.append(new Option('Add new event',0));
            existing.forEach(event=>target.append(new Option(`Update #${event.event_id}: ${event.title} (${event.start_date.slice(0,10)})`,event.event_id)));target.value=row.event_id||0;
            target.addEventListener('change',()=>{change(row,'event_id',Number(target.value));const old=existing.find(event=>Number(event.event_id)===row.event_id);if(old){row.category_id=old.category_id;row.dept_id=old.audience_group&&old.audience_group!=='all'?'group:'+old.audience_group:old.dept_id||'';row.audience_group='all';row.program=old.program||'ALL';}render();});wrapper.append(target);grid.append(wrapper);
        }
        td.append(grid);const list=el('ul',undefined,'small mt-3 mb-2');list.dataset.editorIssues='';list.setAttribute('aria-live','polite');warnings(row).forEach(message=>list.append(el('li',message)));td.append(list);
        const source=el('details',undefined,'small my-2');source.append(el('summary','Document source and previous values'),el('p',row.source||''));if(row.previous)source.append(el('p',`Previously: ${row.previous.title}; ${row.previous.start_date}; ${row.previous.location||''}; ${row.previous.description||''}`));td.append(source);
        const actions=el('div',undefined,'d-flex gap-2 flex-wrap');actions.append(button('Mark reviewed',()=>{if(critical(row).length){feedback('Correct the errors before marking this activity reviewed.');return;}row.reviewed=true;dirty=true;render();}),button(row.action==='archive'?'Approve archiving':'Approve activity',()=>approve([row],false,true)),button('Exclude',()=>{row.include=false;row.excluded=true;dirty=true;render();}),button('Done',()=>{editing=null;render();}));td.append(actions);tr.append(td);return tr;
    }
    function render() {
        const years=new Set(Array.from($('ciYear').options,option=>option.value));
        rows.forEach(row=>{const year=row.start_date?.slice(0,4);if(year&&!years.has(year)){years.add(year);$('ciYear').append(new Option(year,year));}});
        body.replaceChildren();const filtered=matches(),visible=pageRows();
        visible.forEach(row=>{
            const tr=el('tr');tr.dataset.rowIndex=row.index;const checkCell=el('td'),check=el('input',undefined,'form-check-input');check.type='checkbox';check.checked=selected.has(row.index);check.setAttribute('aria-label',`Select ${row.title}`);
            check.addEventListener('change',()=>{if(check.checked)selected.add(row.index);else selected.delete(row.index);updateSummary();});checkCell.append(check);
            const title=el('td'),titleText=el('div',undefined,'ci-title'),issues=warnings(row);
            if(issues.length){const warning=el('i',undefined,'bi bi-exclamation-triangle-fill ci-warning');warning.title=issues.join('\n');warning.setAttribute('aria-label','Needs attention');titleText.append(warning);}
            titleText.append(document.createTextNode(row.title||'Untitled activity'));title.append(titleText);
            if(audienceName(row))title.append(el('div',audienceName(row),'ci-description'));
            if(row.action==='archive')title.append(el('div','Possible removal · archive if approved','ci-description'));else if(row.description||row.location)title.append(el('div',[row.location,row.description].filter(Boolean).join(' · '),'ci-description'));
            const dates=el('td',row.start_date||'Date needed');dates.style.whiteSpace='nowrap';if(row.end_date&&row.end_date!==row.start_date)dates.append(el('div',`– ${row.end_date}`,'small text-muted'));
            const category=el('td');category.append(categorySelect(row));const status=el('td');status.append(el('span',label(row),`ci-badge ${state(row)}`));
            const action=el('td'),edit=button(editing===row.index?'Close':'Edit',()=>{editing=editing===row.index?null:row.index;render();});edit.setAttribute('aria-expanded',String(editing===row.index));action.append(edit);
            tr.append(checkCell,title,dates,category,status,action);body.append(tr);if(editing===row.index)body.append(editor(row));
        });
        if(!visible.length){const tr=el('tr'),td=el('td','No activities match these filters.','text-center text-muted py-4');td.colSpan=6;tr.append(td);body.append(tr);}
        $('ciPageInfo').textContent=`Showing ${filtered.length?(page-1)*pageSize()+1:0}–${Math.min(page*pageSize(),filtered.length)} of ${filtered.length} matching · ${rows.length} total · Page ${page} of ${Math.max(1,Math.ceil(filtered.length/pageSize()))}`;
        $('ciPrevious').disabled=page===1;$('ciNext').disabled=page*pageSize()>=filtered.length;updateSummary();
    }
    function setStep(next) {
        if(busy||(next>1&&!token)||management)return;if(step!==next)show('calendarImportError',false);step=next;
        show('calendarImportUpload',step===1);show('calendarImportManagement',step===1);show('calendarImportPreview',step===2||step===3);show('ciFinal',step===4);show('calendarImportSummary',!!token);show('ciBack',step>1);show('ciContinue',step<4&&!!token);show('calendarImportConfirm',step===4);
        document.querySelectorAll('[data-ci-step]').forEach(node=>{const number=Number(node.dataset.ciStep);node.classList.toggle('active',number===step);node.disabled=number>1&&!token;node.removeAttribute('aria-current');if(number===step)node.setAttribute('aria-current','step');});
        $('calendarImportReviewHeading').textContent=step===3?'Verify and approve activities':'Review extracted activities';$('calendarImportReviewHelp').textContent=step===3?'Selection and approval are separate. Review warnings, then approve the activities to import.':'Use Edit for details. Category changes can be made directly in the table.';
        $('ciContinue').textContent=step===2?'Continue to verification':step===3?'Review final summary':'Continue review';render();
    }
    async function request(action,data=new FormData(),useToken=token) {
        data.set('ajax_action',action);data.set('csrf_token',$('csrfToken').value);if(useToken)data.set('import_token',useToken);
        const response=await fetch(window.location.pathname,{method:'POST',body:data});let result;
        try{result=await response.json();}catch{throw new Error('The server could not read this request. Check the file size or refresh and try again.');}
        if(!result.success)throw new Error(result.message||result.error||'The request could not be completed.');return result;
    }
    async function discardPrompt(){return window.AdminDialogs?window.AdminDialogs.confirm('Discard your calendar review? Edits, selections and approvals in this workspace will be lost.',{title:'Discard calendar review?',confirmText:'Discard review',cancelText:'Keep reviewing',danger:true}):window.confirm('Discard your calendar review and unsaved changes?');}
    $('calendarImportUpload').addEventListener('submit',async event=>{
        event.preventDefault();if(busy)return;const file=$('calendarImportFile').files[0];if(!file||file.size>5*1024*1024||!/\.docx$/i.test(file.name)){showError('Choose a .docx file up to 5 MB.');return;}
        if(token&&dirty&&!(await discardPrompt()))return;busy=true;updateSummary();show('calendarImportError',false);$('calendarImportExtract').textContent='Extracting…';$('calendarImportFileStatus').textContent=`${file.name} · Uploading and extracting activities…`;
        try{
            const result=await request('import_preview',new FormData($('calendarImportUpload')));if(token)await request('import_cancel',new FormData(),token).catch(()=>{});
            token=result.token;categories=result.categories;existing=result.existing;departments=result.departments||[];programs=result.programs||[];departmentsReady=!!result.departments_ready;groupsReady=!!result.audience_groups_ready;rows=result.rows.map((row,index)=>({program:'ALL',...row,dept_id:row.audience_group&&row.audience_group!=='all'?'group:'+row.audience_group:row.dept_id||'',audience_group:'all',index,include:false,reviewed:false,excluded:false}));selected.clear();editing=null;page=1;dirty=true;finished=false;
            fillDepartments($('ciBulkDepartment'));fillPrograms($('ciBulkProgram'),'');
            for(const id of ['ciCategory','ciBulkCategory']){const select=$(id);while(select.options.length>(id==='ciCategory'?2:1))select.remove(select.options.length-1);categories.forEach(cat=>select.append(new Option(cat.category_name,cat.category_id)));}
            $('ciYear').replaceChildren(new Option('All years',''));[...new Set(rows.map(row=>row.start_date?.slice(0,4)).filter(Boolean))].sort().forEach(year=>$('ciYear').append(new Option(year,year)));
            $('calendarImportFileStatus').textContent=`${file.name} · Extraction complete · ${rows.length} activities`;busy=false;setStep(2);
        }catch(e){showError(e.message);$('calendarImportFileStatus').textContent=`${file.name} · Extraction failed. Your previous review is still available.`;}finally{busy=false;$('calendarImportExtract').textContent='Extract Activities';updateSummary();}
    });
    for(const id of ['ciSearch','ciMonth','ciYear','ciCategory','ciStatus','ciSort','ciWarningsOnly','ciPageSize'])$(id).addEventListener(id==='ciSearch'?'input':'change',()=>{page=1;render();});
    for(const [id,selector] of [['ciToggleFilters','.ci-toolbar'],['ciToggleBulk','.ci-bulk']])$(id).addEventListener('click',()=>{const expanded=$(id).closest(selector).classList.toggle('ci-expanded');$(id).setAttribute('aria-expanded',String(expanded));});
    $('ciPrevious').addEventListener('click',()=>{page--;render();});$('ciNext').addEventListener('click',()=>{page++;render();});
    $('ciSelectVisible').addEventListener('change',event=>{pageRows().forEach(row=>event.target.checked?selected.add(row.index):selected.delete(row.index));render();});
    $('ciSelectMatching').addEventListener('click',()=>{matches().forEach(row=>selected.add(row.index));render();});$('ciClearSelection').addEventListener('click',()=>{selected.clear();render();});
    $('ciApproveSelected').addEventListener('click',()=>approve(rows.filter(row=>selected.has(row.index))));
    $('ciExcludeSelected').addEventListener('click',()=>{const targets=rows.filter(row=>selected.has(row.index));targets.forEach(row=>{row.include=false;row.excluded=true;});dirty=true;feedback(`${targets.length} activities excluded.`);render();});
    $('ciApplyCategory').addEventListener('click',()=>{const category=$('ciBulkCategory').value;if(!category){feedback('Choose a category before assigning it.');return;}const targets=rows.filter(row=>selected.has(row.index));targets.forEach(row=>change(row,'category_id',category));feedback(`Category changed for ${targets.length} activities. Review them before approving.`);render();});
    $('ciBulkDepartment').addEventListener('change',()=>fillPrograms($('ciBulkProgram'),$('ciBulkDepartment').value,$('ciBulkProgram').value));
    $('ciApplyAudience').addEventListener('click',()=>{
        const targets=rows.filter(row=>selected.has(row.index)&&row.action!=='archive');
        targets.forEach(row=>{change(row,'dept_id',$('ciBulkDepartment').value);change(row,'program',$('ciBulkProgram').value);});
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
        if(busy||confirm.disabled||(!management&&step!==4))return;busy=true;updateSummary();show('calendarImportError',false);confirm.textContent='Saving…';
        try{
            const data=new FormData();if(management)data.set('reviewed_all',$('calendarManageReviewed').checked?'1':'0');const fields=['event_id','title','description','location','start_date','end_date','start_time','end_time','category_id','dept_id','program','include','reviewed'];
            data.set('import_rows',JSON.stringify(rows.map(row=>Object.fromEntries(fields.map(key=>[key,row[key]])))));data.set('import_status','draft');
            const result=await request('import_confirm',data);finished=true;dirty=false;sessionStorage.setItem('calendarImportMessage',result.message);window.location.reload();
        }catch(e){showError(e.message);}finally{busy=false;updateSummary();confirm.textContent=management?(management==='delete'?'Confirm Delete Calendar':'Confirm Archive Calendar'):'Confirm Import';}
    });
    modal.addEventListener('hide.bs.modal',async event=>{
        if(busy){event.preventDefault();return;}if(allowClose||finished||!dirty)return;event.preventDefault();if(asking)return;asking=true;
        try{if(await discardPrompt()){allowClose=true;bootstrap.Modal.getInstance(modal).hide();}}finally{asking=false;}
    });
    modal.addEventListener('hidden.bs.modal',()=>{
        const oldToken=token;token='';rows=[];selected.clear();categories=[];existing=[];management='';editing=null;dirty=false;finished=false;allowClose=false;page=1;if(oldToken)request('import_cancel',new FormData(),oldToken).catch(()=>{});
        show('calendarManagePreview',false);show('calendarImportError',false);confirm.classList.remove('btn-danger');confirm.classList.add('btn-gnc-gold');confirm.textContent='Confirm Import';$('ciFeedback').textContent='';$('calendarImportFileStatus').textContent='Step 1 of 4 · Choose your calendar document to get started.';setStep(1);
    });
    window.addEventListener('beforeunload',event=>{if(dirty&&!finished){event.preventDefault();event.returnValue='';}});setStep(1);
});
document.addEventListener('DOMContentLoaded',()=>{
    const message=sessionStorage.getItem('calendarImportMessage');if(!message)return;sessionStorage.removeItem('calendarImportMessage');const notice=document.createElement('div');notice.className='alert alert-success';notice.setAttribute('role','status');notice.textContent=message;document.querySelector('.page-header')?.after(notice);
});
