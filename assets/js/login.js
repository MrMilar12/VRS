'use strict';
const passwordToggle=document.querySelector('.password-toggle');
const passwordInput=document.getElementById('login-password');
if(passwordToggle&&passwordInput){
 passwordToggle.hidden=false;
 passwordToggle.addEventListener('click',()=>{
  const show=passwordInput.type==='password';
  passwordInput.type=show?'text':'password';
  passwordToggle.textContent=show?'Hide':'Show';
  passwordToggle.setAttribute('aria-label',show?'Hide password':'Show password');
  passwordToggle.setAttribute('aria-pressed',String(show));
 });
}
