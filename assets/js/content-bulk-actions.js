(function(){
    'use strict';
    function start(){
        const bar=document.querySelector('[data-bulk-actions]');if(!bar)return;
        const table=document.getElementById(bar.dataset.table),all=bar.querySelector('[data-bulk-all]'),count=bar.querySelector('[data-bulk-count]'),button=bar.querySelector('[data-bulk-delete]');
        const dialog=document.querySelector('[data-bulk-dialog]'),form=dialog.querySelector('form'),password=form.querySelector('[name=current_password]'),error=form.querySelector('[data-bulk-error]'),submit=form.querySelector('[type=submit]');
        const visible=()=>[...table.querySelectorAll('[data-bulk-item]')].filter(input=>{const row=input.closest('tr');return !row.hidden&&row.style.display!=='none'&&row.getClientRects().length;});
        function update(){
            const inputs=visible();table.querySelectorAll('[data-bulk-item]').forEach(input=>{if(!inputs.includes(input))input.checked=false;});
            const n=inputs.filter(input=>input.checked).length;
            all.disabled=!inputs.length;all.checked=!!inputs.length&&n===inputs.length;all.indeterminate=n>0&&n<inputs.length;
            count.textContent=n+' selected'+(n>500?' · select at most 500 at a time':'');button.disabled=!n||n>500;
        }
        all.addEventListener('change',()=>{visible().forEach(input=>{input.checked=all.checked;});update();});
        table.addEventListener('change',event=>{if(event.target.matches('[data-bulk-item]'))update();});
        let pending=false;new MutationObserver(()=>{if(!pending){pending=true;requestAnimationFrame(()=>{pending=false;update();});}}).observe(table,{subtree:true,attributes:true,attributeFilter:['hidden','style','class'],childList:true});
        button.addEventListener('click',()=>{
            const selected=visible().filter(input=>input.checked);if(!selected.length||selected.length>500)return;
            const ids=form.querySelector('[data-bulk-ids]');ids.replaceChildren();selected.forEach(input=>{const field=document.createElement('input');field.type='hidden';field.name='ids[]';field.value=input.value;ids.appendChild(field);});
            dialog.querySelector('[data-bulk-description]').textContent=bar.dataset.queued==='1'?'Submit deletion of '+selected.length+' selected items for approval? Published content stays unchanged until approved.':'Delete '+selected.length+' selected items? This cannot be undone. Items on other pages are not selected.';
            password.value='';error.textContent='';submit.disabled=false;dialog.showModal();password.focus();
        });
        form.querySelector('[data-bulk-cancel]').addEventListener('click',()=>dialog.close());
        dialog.addEventListener('close',()=>{password.value='';form.querySelector('[data-bulk-ids]').replaceChildren();button.focus({preventScroll:true});});
        form.addEventListener('submit',async event=>{
            if(bar.dataset.kind!=='event'){submit.disabled=true;return;}
            event.preventDefault();submit.disabled=true;error.textContent='';
            const body=new FormData(form);password.value='';
            try{
                const response=await fetch(form.getAttribute('action'),{method:'POST',body,credentials:'same-origin'}),data=await response.json();
                if(!response.ok||!data.success)throw Error(data.message||data.error||'The selected events could not be deleted.');
                if(data.submitted)location.href='/admin/submissions.php?id='+encodeURIComponent(data.submission_id);else location.reload();
            }catch(failure){error.textContent=failure.message||'Could not reach the server. Try again.';submit.disabled=false;password.focus();}
        });
        window.addEventListener('pageshow',()=>{password.value='';update();});update();
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();
