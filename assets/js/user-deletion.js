(function () {
    'use strict';
    const dialog = document.getElementById('delete-user-dialog');
    if (!dialog) return;
    const form = document.getElementById('delete-user-form');
    const password = document.getElementById('delete-user-password');
    let trigger;
    document.querySelectorAll('[data-delete-user]').forEach(button => button.addEventListener('click', () => {
        trigger = button;
        form.reset();
        document.getElementById('delete-user-id').value = button.dataset.deleteUser;
        document.getElementById('delete-user-name').textContent = button.dataset.userName;
        dialog.showModal(); password.focus();
    }));
    document.getElementById('cancel-delete-user').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        password.value = '';
        document.getElementById('delete-user-id').value = '';
        if (trigger && trigger.isConnected) trigger.focus({preventScroll:true});
    });
    form.addEventListener('submit', () => {
        form.querySelector('button[type="submit"]').disabled = true;
    });
})();
