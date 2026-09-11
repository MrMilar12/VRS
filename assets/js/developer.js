'use strict';
(() => {
 const root=document.querySelector('[data-developer-center]');if(!root)return;
 const find=name=>root.querySelector('[data-update-'+name+']'),form=find('form'),status=find('status'),check=find('check'),apply=find('apply'),rollback=find('rollback');let state=null,busy=false;
 async function request(data){const response=await fetch('api.php?action=updates',{method:'POST',headers:{Accept:'application/json'},body:new URLSearchParams({csrf:form.elements.csrf.value,...data})});let result;try{result=await response.json();}catch(error){throw new Error('The server could not return update status. Refresh the page and check server logs.');}if(!response.ok)throw new Error(result.error||'Update request failed.');return result;}
 function pending(value){busy=value;check.disabled=value;apply.disabled=value||!state?.can_apply;rollback.disabled=value;form.elements.password.disabled=value;root.setAttribute('aria-busy',String(value));}
 function render(data){state=data;find('current').textContent=data.current.slice(0,12);find('latest').textContent=data.latest.slice(0,12);find('summary').textContent=data.summary;find('checked').textContent='Last checked: '+data.checked_at;
  status.textContent=data.available?'New update available':data.current===data.latest?'You’re up to date':'Local and GitHub versions differ';status.classList.toggle('has-update',data.available);document.title=(data.available?'Update available · ':'')+'Developer center · VRS';
  const blockers=find('blockers');blockers.replaceChildren();for(const message of data.blocked){const item=document.createElement('li');item.textContent=message;blockers.append(item);}
  find('changes').hidden=!data.change_count;find('count').textContent='('+data.change_count+')';const files=find('files');files.replaceChildren();for(const path of data.changes){const li=document.createElement('li');li.textContent=path;files.append(li);}if(data.change_count>data.changes.length){const li=document.createElement('li');li.textContent='Preview limited to the first '+data.changes.length+' files.';files.append(li);}
  rollback.hidden=!data.rollback;pending(false);
 }
 async function refresh(){if(busy)return;pending(true);status.textContent='Checking GitHub and downloading the patch…';try{render(await request({mode:'check'}));}catch(error){state=null;status.textContent=error.message+' Update status is unavailable.';}finally{pending(false);}}
 async function install(mode){if(busy||!state||!form.reportValidity())return;const target=mode==='rollback'?state.rollback:state.latest;if(!target)return;
  if(!confirm((mode==='rollback'?'Restore previous code version ':'Install version ')+target.slice(0,12)+'? The workspace will briefly pause during replacement.'))return;
  const password=form.elements.password.value;pending(true);status.textContent='Validating and '+(mode==='rollback'?'restoring':'installing')+' code…';
  try{const result=await request({mode,current:state.current,target,password});form.elements.password.value='';status.textContent=result.message+' Reloading…';location.reload();}catch(error){status.textContent=error.message;form.elements.password.value='';state=null;}finally{pending(false);}
 }
 check.addEventListener('click',refresh);form.addEventListener('submit',event=>{event.preventDefault();install('apply');});rollback.addEventListener('click',()=>install('rollback'));
 setInterval(()=>{if(!document.hidden)refresh();},60000);refresh();
})();
