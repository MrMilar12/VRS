'use strict';
(() => {
 const check=document.querySelector('[data-check-availability]');if(!check)return;
 const form=check.form,result=document.getElementById('availability-result');
 const vehicle=form.elements.vehicle_id,driver=form.elements.driver_id;
 const approve=form.querySelector('[data-standard-approve]');
 const toggle=form.querySelector('[data-enable-override]'),panel=form.querySelector('#schedule-override');
 const override=form.querySelector('[data-override-approve]'),reason=form.elements.override_reason;
 let overrideMode=false,version=0,controller;
 function reset(){approve.disabled=true;if(override)override.disabled=true;}
 async function checkAvailability(){
  const current=++version;controller?.abort();reset();
  if(!vehicle.value||!driver.value){check.disabled=false;result.className='muted';result.textContent='Select a vehicle and driver to check this schedule.';return;}
  controller=new AbortController();const request=controller;
  const timeout=setTimeout(()=>request.abort(),12000);
  check.disabled=true;result.className='muted';result.textContent='Checking vehicle and driver schedules…';
  try{
   const params=new URLSearchParams({action:'availability',id:check.dataset.checkAvailability,vehicle_id:vehicle.value,driver_id:driver.value});
   const response=await fetch('api.php?'+params,{signal:request.signal});const data=await response.json();
   if(current!==version)return;
   if(!response.ok||data.error)throw new Error(data.error||'Unable to check availability.');
   result.textContent=data.message;result.className='alert '+(data.available?'success':'error');
   approve.disabled=!data.available;
   if(override)override.disabled=!(overrideMode&&data.overridable);
  }catch(error){if(current===version){result.className='alert error';result.textContent='Unable to check availability. Try Check availability again.';}}
  finally{clearTimeout(timeout);if(current===version)check.disabled=false;}
 }
 toggle?.addEventListener('click',()=>{
  overrideMode=!overrideMode;toggle.setAttribute('aria-expanded',String(overrideMode));
  toggle.textContent=overrideMode?'Cancel override':'Override schedule conflict';panel.hidden=!overrideMode;override.hidden=!overrideMode;reason.disabled=!overrideMode;
  reason.setCustomValidity('');
  form.querySelectorAll('option[data-overridable="true"]').forEach(option=>{option.disabled=!overrideMode;});
  for(const select of [vehicle,driver])if(select.selectedOptions[0]?.disabled)select.value='';
  checkAvailability();
 });
 for(const select of [vehicle,driver])select.addEventListener('change',checkAvailability);
 check.addEventListener('click',checkAvailability);
 reason?.addEventListener('input',()=>{reason.setCustomValidity('');reason.removeAttribute('aria-invalid');});
 form.addEventListener('submit',event=>{
  if(override&&event.submitter===override&&Array.from(reason.value.trim()).length<15){event.preventDefault();reason.setAttribute('aria-invalid','true');result.className='alert error';result.textContent='Enter an override reason of at least 15 characters.';reason.focus();}
 });
 checkAvailability();
})();
