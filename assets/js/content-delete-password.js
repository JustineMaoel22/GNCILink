(function(){
    'use strict';
    document.querySelectorAll('[data-delete-password-modal]').forEach(modal=>{
        const input=modal.querySelector('[name="current_password"]');
        modal.addEventListener('show.bs.modal',()=>{input.value='';});
        modal.addEventListener('shown.bs.modal',()=>input.focus());
        modal.addEventListener('hidden.bs.modal',()=>{input.value='';});
    });
})();
