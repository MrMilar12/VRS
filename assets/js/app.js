'use strict';
document.querySelector('[data-toggle-sidebar]')?.addEventListener('click',()=>document.querySelector('.sidebar').classList.toggle('open'));
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!confirm(form.dataset.confirm))event.preventDefault();}));
document.querySelectorAll('[data-close-dialog]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog').close()));
document.querySelectorAll('input[name="start_datetime"]').forEach(input=>input.addEventListener('change',()=>{const end=input.form.querySelector('input[name="end_datetime"]');if(end)end.min=input.value;}));
