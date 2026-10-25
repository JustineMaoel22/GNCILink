(()=>{'use strict';
const toggle=document.getElementById('show-passwords');toggle?.addEventListener('change',()=>document.querySelectorAll('input[autocomplete="new-password"],input[autocomplete="current-password"]').forEach(input=>input.type=toggle.checked?'text':'password'));
const password=document.getElementById('new_password'),confirm=document.getElementById('confirm_password'),meter=document.getElementById('password-strength'),label=document.getElementById('password-strength-label');
function update(){if(!password)return;const text=password.value,common=/^(password|qwerty|welcome|admin|letmein)/i.test(text);let score=text.length<12?1:text.length<16?2:text.length<22?3:4;if(common||new Set(text).size<4)score=1;if(!text)score=0;if(meter)meter.value=score;if(label)label.textContent=['Enter a new password','Too weak','Fair — try a longer phrase','Good','Strong estimate'][score];if(confirm)confirm.setCustomValidity(confirm.value&&confirm.value!==text?'The new passwords do not match.':'');}
password?.addEventListener('input',update);confirm?.addEventListener('input',update);
document.querySelectorAll('[data-auth-form]').forEach(form=>form.addEventListener('submit',()=>{const button=form.querySelector('button[type=submit]');if(button){button.disabled=true;button.textContent=button.dataset.loadingText||'Please wait…';}form.setAttribute('aria-busy','true');}));
})();
