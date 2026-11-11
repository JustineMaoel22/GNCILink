(function () {
    'use strict';
    window.GNCScholarshipManager = {open};
    let active = null;
    function node(tag, attrs = {}, children = []) {
        const el = document.createElement(tag);
        Object.entries(attrs).forEach(([key, value]) => {
            if (value === false || value == null) return;
            if (key === 'text') el.textContent = value;
            else if (key === 'class') el.className = value;
            else el.setAttribute(key, value === true ? '' : value);
        });
        el.append(...children); return el;
    }
    function glyph(name) {return node('i', {class:'bi bi-'+name, 'aria-hidden':'true'});}
    function button(label, action, className = 'sm-button', attrs = {}) {
        const el = node('button', {type:'button', class:className, ...attrs}, [label]);
        el.addEventListener('click', action);return el;
    }
    function open(api) {
        if(active){active.dialog.focus();return active;}
        const previousFocus=document.activeElement;
        const dialog=node('dialog',{class:'sm-dialog','aria-labelledby':'sm-title',tabindex:'-1'});
        const title=node('h2',{id:'sm-title',text:'Manage scholarships'});
        const subtitle=node('p',{text:'Organize categories, scholarship titles and PDF documents.'});
        const body=node('div',{class:'sm-body'});
        const live=node('p',{class:'sm-feedback',role:'status','aria-live':'polite'});
        const header=node('header',{class:'sm-header'},[node('div',{},[title,subtitle]),button(glyph('x-lg'),()=>dialog.close(),'sm-icon',{'aria-label':'Close scholarship manager'})]);
        const footer=node('footer',{class:'sm-footer'},[node('p',{text:'Changes stay in the editor until you publish or submit for approval.'}),button('Done',()=>dialog.close(),'sm-button sm-primary')]);
        dialog.append(header,body,live,footer);document.body.append(dialog);
        active={dialog,edit:scholarshipForm};
        let search='',view='list',drag=null,menus=[],focusAfter=null;
        const expanded=new Set([api.selection().category]);
        const data=()=>api.read();
        function announce(message){live.textContent=message;}
        function changed(message){api.changed();announce(message);render();}
        function back(){view='list';title.textContent='Manage scholarships';subtitle.textContent='Organize categories, scholarship titles and PDF documents.';render();body.querySelector('input')?.focus();}
        function reset(heading,caption){menus.forEach(menu=>menu.remove());menus=[];view='form';body.replaceChildren();footer.querySelector('button').hidden=true;title.textContent=heading;subtitle.textContent=caption;announce('');}
        function menu(label,items){
            const panel=node('div',{class:'sm-menu',popover:'auto','aria-label':label});
            const trigger=button(glyph('three-dots-vertical'),()=>{
                panel.showPopover();const rect=trigger.getBoundingClientRect();
                panel.style.left=Math.max(8,Math.min(rect.right-220,innerWidth-228))+'px';
                panel.style.top=Math.max(8,Math.min(rect.bottom+4,innerHeight-panel.offsetHeight-8))+'px';
                panel.querySelector('button:not(:disabled)')?.focus();
            },'sm-icon',{'aria-label':label,'aria-haspopup':'true'});
            items.forEach(item=>panel.append(button(item.label,()=>{panel.hidePopover();item.action();},'sm-menu-item'+(item.danger?' is-danger':''),{disabled:item.disabled,'aria-label':item.aria||item.label})));
            panel.addEventListener('keydown',event=>{
                const options=Array.from(panel.querySelectorAll('button:not(:disabled)'));
                let index=options.indexOf(document.activeElement);
                if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();index=(index+(event.key==='ArrowDown'?1:-1)+options.length)%options.length;options[index]?.focus();}
                if(event.key==='Escape'){event.preventDefault();event.stopPropagation();panel.hidePopover();trigger.focus();}
            });
            dialog.append(panel);menus.push(panel);return trigger;
        }
        function move(kind,id,step){
            const state=data(),list=kind==='category'?state.categories:state.entries.filter(e=>e.category===state.entries.find(e=>e.id===id).category);
            const index=list.findIndex(item=>item.id===id),target=index+step;
            if(target<0||target>=list.length)return;
            const first=list[index],other=list[target];
            if(kind==='category'){state.categories[index]=other;state.categories[target]=first;}
            else {const from=state.entries.indexOf(first),to=state.entries.indexOf(other);state.entries[from]=other;state.entries[to]=first;}
            focusAfter={kind,id};changed((kind==='category'?'Category':'Scholarship')+' moved '+(step<0?'up.':'down.'));
        }
        function confirmDelete(kind,item){
            const members=kind==='category'?data().entries.filter(e=>e.category===item.id):[];
            reset('Delete '+(kind==='category'?'category':'scholarship')+'?',item.label||item.title);
            const blocked=members.length>0||kind==='category'&&data().categories.length===1;
            body.append(node('p',{class:'sm-notice',text:members.length?'This category contains '+members.length+' scholarship(s). Move them to another category or delete them before deleting this category.':blocked?'Keep at least one scholarship category.':'This item will be removed when you publish. Previously stored PDFs are retained.'}));
            body.append(node('div',{class:'sm-form-actions'},[button(blocked?'Back to scholarships':'Keep '+kind,back),...blocked?[]:[button('Delete '+kind,()=>{
                const state=data(),list=kind==='category'?state.categories:state.entries;
                list.splice(list.indexOf(item),1);if(kind==='scholarship')api.forgetFile(item.id);
                view='list';title.textContent='Manage scholarships';subtitle.textContent='Organize categories, scholarship titles and PDF documents.';changed((kind==='category'?'Category':'Scholarship')+' removed from the editor.');
            },'sm-button sm-danger')]]));
        }
        function categoryForm(current){
            reset(current?'Rename category':'Add category','Use a clear name administrators and visitors will recognize.');
            const input=node('input',{id:'sm-category-name',class:'sm-input',type:'text',maxlength:'100',required:true,value:current?.label||''});
            const error=node('p',{class:'sm-error',role:'alert'});
            const form=node('form',{class:'sm-form'},[node('label',{for:input.id,text:'Category name'}),input,error,node('div',{class:'sm-form-actions'},[button('Cancel',back),node('button',{type:'submit',class:'sm-button sm-primary',text:current?'Save category':'Add category'})])]);
            form.addEventListener('submit',event=>{
                event.preventDefault();const label=input.value.trim(),state=data();
                if(!label||label.length>100||/[<>]/.test(label)||state.categories.some(c=>c!==current&&c.label.toLocaleLowerCase()===label.toLocaleLowerCase())){error.textContent='Enter a unique category name of up to 100 characters.';input.focus();return;}
                if(!current&&state.categories.length>=50){error.textContent='Use up to 50 categories.';return;}
                const item=current||{id:'category-'+crypto.randomUUID().replaceAll('-','').slice(0,16)};
                item.label=label;if(!current)state.categories.push(item);expanded.add(item.id);search='';back();focusAfter={kind:'toggle',id:item.id};changed(current?'Category renamed.':'Category added.');
            });body.append(form);input.focus();
        }
        function scholarshipForm(category,current){
            reset(current?'Edit scholarship':'Add scholarship','Choose a category, enter a title and attach the scholarship PDF.');
            const state=data();
            const name=node('input',{id:'sm-scholarship-title',class:'sm-input',type:'text',maxlength:'255',required:true,value:current?.title||'','aria-label':'Scholarship title'});
            const select=node('select',{id:'sm-scholarship-category',class:'sm-input','aria-label':'Scholarship category'},state.categories.map(c=>node('option',{value:c.id,text:c.label})));select.value=category;
            const file=node('input',{id:'sm-scholarship-pdf',type:'file',accept:'application/pdf,.pdf',class:'sm-input','aria-label':'Scholarship PDF'});
            const error=node('p',{class:'sm-error',role:'alert'});
            const form=node('form',{class:'sm-form'},[node('label',{for:select.id,text:'Category'}),select,node('label',{for:name.id,text:'Scholarship title'}),name,node('label',{for:file.id,text:current?'Upload or replace PDF':'PDF document (optional)'}),file,node('p',{class:'sm-hint',text:current&&api.hasPdf(current)?'A PDF is attached. Leave this empty to keep it. PDF files up to 20 MB.':'PDF files up to 20 MB. You can attach the document later.'}),error,node('div',{class:'sm-form-actions'},[button('Cancel',back),node('button',{type:'submit',class:'sm-button sm-primary',text:current?'Save changes':'Add scholarship'})])]);
            form.addEventListener('submit',event=>{
                event.preventDefault();const title=name.value.trim(),pdf=file.files[0];
                if(!title||title.length>255){error.textContent='Enter a scholarship title of up to 255 characters.';name.focus();return;}
                if(pdf&&(!/\.pdf$/i.test(pdf.name)||!pdf.size||pdf.size>20*1024*1024)){error.textContent='Choose a PDF of up to 20 MB.';return;}
                if(!current&&state.entries.length>=100){error.textContent='Use up to 100 scholarships.';return;}
                const item=current||{id:crypto.randomUUID().replaceAll('-','').slice(0,16),description:'',url:''};
                Object.assign(item,{title,category:select.value});if(!current)state.entries.push(item);if(pdf)api.attachFile(item,pdf);
                expanded.add(item.category);api.preview(item.id);search='';back();focusAfter={kind:'entry',id:item.id};changed(current?'Scholarship updated.':'Scholarship added.');
            });body.append(form);name.focus();
        }
        function upload(item){
            const input=node('input',{type:'file',accept:'application/pdf,.pdf',hidden:true});body.append(input);
            input.addEventListener('change',()=>{
                const file=input.files[0];input.remove();if(!file)return;
                if(!/\.pdf$/i.test(file.name)||!file.size||file.size>20*1024*1024){announce('Choose a PDF of up to 20 MB.');return;}
                api.attachFile(item,file);api.preview(item.id);changed('PDF selected. Publish or submit for approval when ready.');
            });input.addEventListener('cancel',()=>input.remove(),{once:true});input.click();
        }
        function handle(kind,item,category){return button(glyph('grip-vertical'),()=>announce('Drag this handle to reorder, or use Move up / Move down in the action menu.'),'sm-drag',{'aria-label':'Drag '+(item.label||item.title)+' to reorder','data-drag-kind':kind,'data-drag-id':item.id,'data-drag-category':category||'',disabled:!!search,title:search?'Clear search to drag items':'Drag to reorder'});}
        function render(){
            if(view!=='list')return;
            footer.querySelector('button').hidden=false;
            menus.forEach(menu=>menu.remove());menus=[];
            const scrollTop=body.scrollTop,state=data(),term=search.toLocaleLowerCase();body.replaceChildren();
            const field=node('input',{type:'search',class:'sm-input',placeholder:'Search categories or scholarships…','aria-label':'Search categories or scholarships',value:search});
            field.addEventListener('input',()=>{search=field.value;const caret=field.selectionStart;render();const next=body.querySelector('input');next.focus();try{next.setSelectionRange(caret,caret);}catch(_){};});
            body.append(node('div',{class:'sm-controls'},[field,button(node('span',{},[glyph('plus-lg'),' Add category']),()=>categoryForm(null),'sm-button sm-primary',{disabled:state.categories.length>=50})]));
            body.append(node('p',{class:'sm-hint',text:state.categories.length+' categories · '+state.entries.length+' scholarships. Drag handles to reorder; action menus also include Move up / Move down.'}));
            let count=0;
            state.categories.forEach((category,index)=>{
                const group=state.entries.filter(e=>e.category===category.id),categoryMatch=category.label.toLocaleLowerCase().includes(term),visible=group.filter(e=>categoryMatch||e.title.toLocaleLowerCase().includes(term));
                if(term&&!categoryMatch&&!visible.length)return;count++;
                const isOpen=expanded.has(category.id)||!!term;
                const card=node('section',{class:'sm-category'+(isOpen?' is-open':''),'data-order-kind':'category','data-order-id':category.id});
                const list=node('div',{class:'sm-items',id:'sm-items-'+category.id,hidden:!isOpen});
                const toggle=button(node('span',{},[node('strong',{text:category.label}),node('span',{class:'sm-count',text:String(group.length),'aria-label':group.length+' scholarships'})]),()=>{if(expanded.has(category.id))expanded.delete(category.id);else expanded.add(category.id);focusAfter={kind:'toggle',id:category.id};render();},'sm-category-toggle',{'aria-expanded':String(isOpen),'aria-controls':list.id});
                toggle.append(glyph(isOpen?'chevron-up':'chevron-down'));
                const actions=menu('Actions for category '+category.label,[{label:'Rename category',action:()=>categoryForm(category)},{label:'Move up',aria:'Move category '+category.label+' up',disabled:index===0,action:()=>move('category',category.id,-1)},{label:'Move down',aria:'Move category '+category.label+' down',disabled:index===state.categories.length-1,action:()=>move('category',category.id,1)},{label:'Delete category',danger:true,action:()=>confirmDelete('category',category)}]);
                card.append(node('div',{class:'sm-category-header'},[handle('category',category),toggle,actions]));
                if(!group.length)list.append(node('p',{class:'sm-empty',text:'No scholarships in this category. Add a title and PDF to get started.'}));
                visible.forEach(item=>{
                    const at=group.indexOf(item),attached=api.hasPdf(item);
                    const edit=button(node('span',{},[node('strong',{text:item.title}),node('small',{text:attached?'PDF attached':'No PDF yet'})]),()=>scholarshipForm(category.id,item),'sm-scholarship-edit',{'aria-label':'Edit scholarship '+item.title,title:'Edit scholarship'});
                    list.append(node('div',{class:'sm-item','data-order-kind':'scholarship','data-order-id':item.id,'data-order-category':category.id},[handle('scholarship',item,category.id),edit,menu('Actions for scholarship '+item.title,[{label:'Edit title or category',action:()=>scholarshipForm(category.id,item)},{label:attached?'Replace PDF':'Upload PDF',action:()=>upload(item)},{label:'Move up',aria:'Move scholarship '+item.title+' up',disabled:at===0,action:()=>move('scholarship',item.id,-1)},{label:'Move down',aria:'Move scholarship '+item.title+' down',disabled:at===group.length-1,action:()=>move('scholarship',item.id,1)},{label:'Delete scholarship',danger:true,action:()=>confirmDelete('scholarship',item)}])]));
                });
                list.append(button(node('span',{},[glyph('plus-lg'),' Add scholarship']),()=>scholarshipForm(category.id,null),'sm-button sm-add',{'aria-label':'Add scholarship to '+category.label,disabled:state.entries.length>=100}));
                card.append(list);body.append(card);
            });
            if(!count)body.append(node('p',{class:'sm-empty',text:'No matching categories or scholarships. Try another search.'}));
            body.scrollTop=scrollTop;
            if(focusAfter){const kind=focusAfter.kind,id=focusAfter.id,selector=kind==='toggle'?'[data-order-id="'+id+'"] .sm-category-toggle':kind==='entry'?'[data-order-id="'+id+'"] .sm-scholarship-edit':'[data-drag-kind="'+kind+'"][data-drag-id="'+id+'"]';body.querySelector(selector)?.focus();focusAfter=null;}
        }
        function clearDrag(){body.querySelectorAll('.is-dragging,.drop-before,.drop-after').forEach(el=>el.classList.remove('is-dragging','drop-before','drop-after'));drag=null;}
        body.addEventListener('pointerdown',event=>{
            const grip=event.target.closest('[data-drag-kind]');if(!grip||grip.disabled||event.button!==0)return;
            drag={kind:grip.dataset.dragKind,id:grip.dataset.dragId,category:grip.dataset.dragCategory,x:event.clientX,y:event.clientY,grip,started:false,target:null};
            grip.setPointerCapture(event.pointerId);
        });
        body.addEventListener('pointermove',event=>{
            if(!drag)return;if(!drag.started&&Math.hypot(event.clientX-drag.x,event.clientY-drag.y)<6)return;
            drag.started=true;event.preventDefault();
            body.querySelectorAll('.drop-before,.drop-after').forEach(el=>el.classList.remove('drop-before','drop-after'));
            drag.grip.closest('[data-order-kind]').classList.add('is-dragging');
            const target=document.elementFromPoint(event.clientX,event.clientY)?.closest('[data-order-kind="'+drag.kind+'"]');
            drag.target=null;
            if(target&&body.contains(target)&&target.dataset.orderId!==drag.id&&(drag.kind==='category'||target.dataset.orderCategory===drag.category)){
                const rect=target.getBoundingClientRect(),after=event.clientY>rect.top+rect.height/2;
                target.classList.add(after?'drop-after':'drop-before');drag.target={id:target.dataset.orderId,after};
            }
            const bounds=body.getBoundingClientRect();if(event.clientY<bounds.top+48)body.scrollTop-=16;else if(event.clientY>bounds.bottom-48)body.scrollTop+=16;
        });
        body.addEventListener('pointerup',()=>{
            if(!drag)return;const action=drag;
            if(!action.started||!action.target){clearDrag();return;}
            const state=data(),list=action.kind==='category'?state.categories:state.entries.filter(e=>e.category===action.category),from=list.findIndex(e=>e.id===action.id),item=list.splice(from,1)[0];
            const to=list.findIndex(e=>e.id===action.target.id)+(action.target.after?1:0);list.splice(to,0,item);
            if(action.kind==='scholarship'){let i=0;state.entries.forEach((e,index)=>{if(e.category===action.category)state.entries[index]=list[i++];});}
            clearDrag();focusAfter={kind:action.kind,id:action.id};changed('Order updated in the editor.');
        });
        body.addEventListener('pointercancel',clearDrag);
        dialog.addEventListener('cancel',event=>{if(drag){event.preventDefault();clearDrag();announce('Reordering cancelled.');}else if(view!=='list'){event.preventDefault();back();}});
        dialog.addEventListener('close',()=>{menus.forEach(menu=>menu.remove());dialog.remove();active=null;if(previousFocus?.isConnected)previousFocus.focus();},{once:true});
        dialog.showModal();render();body.querySelector('input')?.focus();return active;
    }
})();
