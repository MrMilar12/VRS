'use strict';
document.querySelector('[data-toggle-sidebar]')?.addEventListener('click',()=>document.querySelector('.sidebar').classList.toggle('open'));
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!confirm(form.dataset.confirm))event.preventDefault();}));
document.querySelectorAll('[data-close-dialog]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog').close()));
document.querySelector('[data-check-availability]')?.addEventListener('click',async event=>{
 const button=event.currentTarget,form=button.closest('form'),result=document.getElementById('availability-result');button.disabled=true;result.textContent='Checking vehicle and driver schedules…';
 try{const params=new URLSearchParams({action:'availability',id:button.dataset.checkAvailability,vehicle_id:form.vehicle_id.value,driver_id:form.driver_id.value});const response=await fetch('api.php?'+params);const data=await response.json();result.textContent=data.error||data.message;result.className='alert '+(data.available?'success':'error');}catch{result.textContent='Unable to check availability. Please try again.';}finally{button.disabled=false;}
});
document.querySelectorAll('input[name="start_datetime"]').forEach(input=>input.addEventListener('change',()=>{const end=input.form.querySelector('input[name="end_datetime"]');if(end)end.min=input.value;}));
