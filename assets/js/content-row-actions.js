(function(){
    'use strict';
    function refresh(root){
        (root||document).querySelectorAll('.ann-actions-cell .btn,.evt-actions-cell .btn,.fb-row-actions .btn').forEach(button=>{
            const label=button.textContent.replace(/\s+/g,' ').trim()||button.getAttribute('aria-label')||button.title;
            if(!label)return;
            const description=button.title||button.getAttribute('data-bs-original-title')||'';
            button.setAttribute('aria-label',label);button.classList.add('content-icon-action');
            button.querySelectorAll('i').forEach(icon=>icon.setAttribute('aria-hidden','true'));
            Array.from(button.childNodes).forEach(node=>{
                if(node.nodeType===3&&node.textContent.trim()){
                    const text=document.createElement('span');text.className='visually-hidden';text.textContent=node.textContent;node.replaceWith(text);
                }else if(node.nodeType===1&&node.tagName==='SPAN')node.classList.add('visually-hidden');
            });
            let target=button;
            const wrapper=button.parentElement.classList.contains('content-action-wrap')?button.parentElement:null;
            if(window.bootstrap&&bootstrap.Tooltip){
                const old=bootstrap.Tooltip.getInstance(wrapper||button);if(old)old.dispose();
            }
            if(wrapper){wrapper.replaceWith(button);}
            if(button.disabled){
                target=document.createElement('span');target.className='content-action-wrap';target.tabIndex=0;
                target.setAttribute('aria-label',description?label+': '+description:label);
                button.before(target);target.appendChild(button);
            }
            if(window.bootstrap&&bootstrap.Tooltip){
                const tooltip=new bootstrap.Tooltip(target,{title:description&&description!==label?label+': '+description:label,trigger:'hover focus',container:document.body,boundary:'viewport'});
                if(!button.dataset.actionTooltipBound){
                    button.addEventListener('click',()=>{const current=bootstrap.Tooltip.getInstance(button);if(current)current.hide();});
                    button.dataset.actionTooltipBound='1';
                }
            }else target.title=description?label+': '+description:label;
        });
    }
    window.GNCContentRowActions={refresh:refresh};
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>refresh(document));else refresh(document);
})();
