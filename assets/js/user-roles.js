(function(){
    'use strict';
    const dialog=document.getElementById('role-user-dialog');
    if(!dialog)return;
    const form=document.getElementById('role-user-form'),password=document.getElementById('role-user-password');
    const forms=[...document.querySelectorAll('[data-saved-role]')];
    const changed=row=>row.querySelector('[name="role"]').value!==row.dataset.savedRole;
    const update=row=>{row.querySelector('button').disabled=!changed(row);};
    let trigger;
    forms.forEach(row=>{
        row.querySelector('[name="role"]').addEventListener('change',()=>update(row));
        row.addEventListener('reset',()=>setTimeout(()=>update(row),0));
        row.addEventListener('submit',event=>{
            event.preventDefault();if(!changed(row))return;
            trigger=row.querySelector('button');form.reset();form.querySelector('button[type="submit"]').disabled=false;
            document.getElementById('role-user-id').value=row.querySelector('[name="user_id"]').value;
            document.getElementById('role-user-selected').value=row.querySelector('[name="role"]').value;
            document.getElementById('role-user-name').textContent=row.closest('tr').querySelector('.user-list-person > span:last-child').textContent;
            document.getElementById('role-user-value').textContent=row.querySelector('[name="role"]').value;
            dialog.showModal();password.focus();
        });
        update(row);
    });
    document.getElementById('cancel-role-user').addEventListener('click',()=>dialog.close());
    dialog.addEventListener('close',()=>{
        password.value='';document.getElementById('role-user-id').value='';document.getElementById('role-user-selected').value='';
        if(trigger&&trigger.isConnected)trigger.focus({preventScroll:true});
    });
    form.addEventListener('submit',()=>{form.querySelector('button[type="submit"]').disabled=true;});
    window.addEventListener('pageshow',()=>{password.value='';forms.forEach(update);form.querySelector('button[type="submit"]').disabled=false;});
})();
