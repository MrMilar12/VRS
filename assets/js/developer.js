'use strict';
(() => {
 const root=document.querySelector('[data-developer-center]');if(!root)return;
 const find=name=>root.querySelector('[data-update-'+name+']'),form=find('form'),status=find('status'),check=find('check'),download=find('download'),apply=find('apply'),rollback=find('rollback');let state=null,busy=false,completed=false;
 function progress(value,label){find('progress').setAttribute('aria-valuenow',String(value));find('fill').style.width=value+'%';find('percent').textContent=value+'%';find('phase').textContent=label;}
 async function request(data){
  let response;try{response=await fetch('api.php?action=updates',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:new URLSearchParams({csrf:form.elements.csrf.value,...data})});}catch(error){throw new Error('Could not reach your hosting server. Check your connection and reopen this page.');}
  const body=await response.text();let result;
  try{result=JSON.parse(body);}catch(error){
   if(response.redirected&&/\/(login|two-factor)\.php(?:[?#]|$)/.test(response.url))throw new Error('Your session expired. Sign in again, then check for updates.');
   const reference=response.headers.get('X-VRS-Request-ID');
   if(reference)throw new Error('The VRS server returned an invalid response (HTTP '+response.status+'). Check the PHP error log using reference '+reference+'.');
   if(response.status===403||response.status===429||/aes\.js|__test|checking your browser|javascript is required/i.test(body))throw new Error('Your hosting returned a browser-verification or access-block page. Open your HTTPS website directly, sign in again, then retry.');
   throw new Error('Your hosting returned '+(body.trim().startsWith('<')?'an HTML page':'an empty or invalid response')+' instead of update status (HTTP '+response.status+'). Check the hosting PHP error log and confirm api.php was uploaded completely.');
  }
  if(!result||typeof result!=='object'||Array.isArray(result))throw new Error('The server returned invalid update data. Upload the complete updater patch.');
  if(!response.ok||result.error)throw new Error(result.error||'Update request failed (HTTP '+response.status+').');
  if(data.mode==='check'||data.mode==='prepare'){if(typeof result.current!=='string'||typeof result.latest!=='string'||!Array.isArray(result.blocked)||!Array.isArray(result.changes))throw new Error('The hosted updater files do not match. Upload the complete updater patch.');}
  return result;
 }

 function pending(value){busy=value||completed;check.disabled=busy;download.disabled=busy||!state?.available||state?.prepared;apply.disabled=busy||!state?.can_apply;rollback.disabled=busy;form.elements.password.disabled=busy;root.setAttribute('aria-busy',String(busy));}
 function render(data){state=data;find('current').textContent=data.current.slice(0,12);find('latest').textContent=data.latest.slice(0,12);find('summary').textContent=data.summary;find('checked').textContent='Last checked: '+data.checked_at;
  status.textContent=data.available?(data.prepared?'Patch downloaded · Ready for review':'New update available · Not downloaded'):data.current===data.latest?'You’re up to date':'Local and GitHub versions differ';status.classList.toggle('has-update',data.available);document.title=(data.available?'Update available · ':'')+'Developer center · VRS';
  const blockers=find('blockers');blockers.replaceChildren();for(const message of data.blocked){const item=document.createElement('li');item.textContent=message;blockers.append(item);}
  find('changes').hidden=!data.change_count;find('count').textContent='('+data.change_count+')';const files=find('files');files.replaceChildren();for(const path of data.changes){const li=document.createElement('li');li.textContent=path;files.append(li);}if(data.change_count>data.changes.length){const li=document.createElement('li');li.textContent='Preview limited to the first '+data.changes.length+' files.';files.append(li);}
  rollback.hidden=!data.rollback;find('confirm').hidden=!data.prepared;download.textContent=data.prepared?'Patch ready':data.available?'Update now':'Up to date';progress(data.prepared?50:0,data.prepared?'Download validated · Review your patch':data.available?'New version ready to download':'Ready when you are');pending(false);
 }
 async function refresh(){if(busy)return;pending(true);status.textContent='Checking GitHub for a new version…';try{render(await request({mode:'check'}));}catch(error){state=null;status.textContent=error.message+' Update status is unavailable.';}finally{pending(false);}}
 async function install(mode){if(busy||!state||!form.reportValidity())return;const target=mode==='rollback'?state.rollback:state.latest;if(!target)return;
  if(!confirm((mode==='rollback'?'Restore previous code version ':'Install version ')+target.slice(0,12)+'? The workspace will briefly pause during replacement.'))return;
  const password=form.elements.password.value;pending(true);progress(mode==='rollback'?0:50,mode==='rollback'?'Restoring previous version…':'Installing your reviewed patch…');find('progress').parentElement.classList.add('is-working');status.textContent='Validating and '+(mode==='rollback'?'restoring':'installing')+' code…';
  try{const result=await request({mode,current:state.current,target,password});completed=true;form.elements.password.value='';progress(100,'Update complete');status.textContent=result.message;setTimeout(()=>location.reload(),1500);}catch(error){status.textContent=error.message;form.elements.password.value='';state=null;}finally{find('progress').parentElement.classList.remove('is-working');pending(false);}
 }
 async function prepare(){if(busy||!state?.available)return;const target=state.latest;pending(true);progress(0,'Downloading and validating…');find('progress').parentElement.classList.add('is-working');status.textContent='Downloading and validating your selected patch…';try{render(await request({mode:'prepare',target}));}catch(error){state=null;status.textContent=error.message;}finally{find('progress').parentElement.classList.remove('is-working');pending(false);}}
 download.addEventListener('click',prepare);check.addEventListener('click',refresh);form.addEventListener('submit',event=>{event.preventDefault();install('apply');});rollback.addEventListener('click',()=>{find('confirm').hidden=false;install('rollback');});
})();
