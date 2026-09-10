'use strict';
(() => {
 const root=document.querySelector('[data-booking-assistant]');if(!root)return;
 const chat=root.querySelector('[data-booking-chat-form]'),review=root.querySelector('[data-booking-review]'),messages=root.querySelector('[data-booking-messages]'),status=root.querySelector('[data-booking-status]'),reset=root.querySelector('[data-booking-reset]'),input=chat.elements.message,send=chat.querySelector('[type=submit]');
 const reviewPanel=root.querySelector('[data-booking-review-panel]'),availabilityPanel=root.querySelector('[data-availability-panel]'),refresh=root.querySelector('[data-availability-refresh]');
 const reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)'),motion=new Set();
 function reveal(node,delay=0){if(reducedMotion.matches||typeof node.animate!=='function')return;const animation=node.animate([{opacity:0,transform:'translateY(12px) scale(.985)'},{opacity:1,transform:'translateY(0) scale(1)'}],{duration:480,delay,easing:'cubic-bezier(.22,1,.36,1)',fill:'backwards'});motion.add(animation);animation.finished.then(()=>motion.delete(animation),()=>motion.delete(animation));}
 reducedMotion.addEventListener('change',()=>{if(reducedMotion.matches){for(const animation of motion)animation.cancel();motion.clear();}});
 function togglePanel(panel,show){const opening=panel.hidden&&show;panel.hidden=!show;if(opening)reveal(panel);}
 let busy=false,refreshing=false;
 function showAvailability(data){
  const opening=availabilityPanel.hidden&&Boolean(data);togglePanel(availabilityPanel,Boolean(data));if(!data)return;
  root.querySelector('[data-availability-summary]').textContent=data.reply;
  root.querySelector('[data-availability-checked]').textContent=data.checked_at?'Checked '+data.checked_at+' · '+(data.live?'Updates every 30 seconds while this page is visible.':'Requested period; refresh to check changes.'):'Please provide both dates and times.';
  refresh.hidden=Boolean(data.needs_dates);const list=root.querySelector('[data-availability-items]');list.replaceChildren();
  for(const item of data.items||[]){const row=document.createElement('article');row.className='assistant-resource';const heading=document.createElement('strong');heading.textContent=item.name;const badge=document.createElement('span');badge.className='badge '+(item.available?'green':'amber');badge.textContent=item.available?'Available':'Unavailable';const detail=document.createElement('p');detail.textContent=item.detail+' · '+item.reason;row.append(heading,badge,detail);list.append(row);if(opening)reveal(row,Math.min(list.children.length-1,5)*45);}
 }
 async function refreshAvailability(){if(busy||refreshing||availabilityPanel.hidden)return;refreshing=true;refresh.disabled=true;try{const result=await request({mode:'refresh'});showAvailability(result.availability);}catch(error){root.querySelector('[data-availability-checked]').textContent=error.message+' Displayed results may be out of date.';}finally{refreshing=false;refresh.disabled=false;}}
 refresh.addEventListener('click',refreshAvailability);
 setInterval(()=>{if(!document.hidden&&!refresh.hidden)refreshAvailability();},30000);

 function bubble(role,text){root.querySelector('[data-assistant-welcome]').hidden=true;messages.hidden=false;const node=document.createElement('div');node.className='booking-message '+role;const title=document.createElement('strong');title.textContent=role==='user'?'You':'VRS assistant';const p=document.createElement('p');p.textContent=text;node.append(title,p);messages.append(node);reveal(node);messages.scrollTo({top:messages.scrollHeight,behavior:reducedMotion.matches?'auto':'smooth'});}
 async function request(data){const response=await fetch('api.php?action=assistant',{method:'POST',body:new URLSearchParams({csrf:chat.elements.csrf.value,...data}),headers:{Accept:'application/json'}});let result;try{result=await response.json();}catch(error){throw new Error('Unable to reach the assistant. Refresh the page or try again.');}if(!response.ok)throw new Error(result.error||'Unable to prepare the request.');return result;}
 function pending(value){busy=value;togglePanel(root.querySelector('[data-assistant-thinking]'),value);root.classList.toggle('assistant-is-thinking',value);send.textContent=value?'Working on it…':'Send message';root.querySelectorAll('[data-assistant-prompt]').forEach(button=>button.disabled=value||input.disabled);send.disabled=value||input.disabled;reset.disabled=value;review.querySelector('button[type=submit]').disabled=value;root.setAttribute('aria-busy',String(value));}
 chat.addEventListener('submit',async event=>{event.preventDefault();if(busy||!chat.reportValidity())return;const text=input.value.trim();if(!text)return;
  pending(true);status.textContent='Checking your request…';
  try{const draft=Object.fromEntries(new FormData(review));draft.fuel_allocation=review.elements.fuel_allocation.checked;draft.fuel_quantity=Number(draft.fuel_quantity||0);const result=await request({message:text,draft:JSON.stringify(draft)});bubble('user',text);bubble('assistant',result.reply);review.elements.end_datetime.setCustomValidity('');for(const [key,value] of Object.entries(result.draft)){const field=review.elements.namedItem(key);if(!field)continue;if(field.type==='checkbox')field.checked=Boolean(value);else field.value=value??'';}
   const start=review.elements.start_datetime,end=review.elements.end_datetime;end.min=start.value;
   root.classList.toggle('booking-chat-only',!result.show_review);togglePanel(reviewPanel,Boolean(result.show_review));showAvailability(result.availability);
   root.querySelector('[data-booking-review-status]').textContent=result.ready?'Your request is ready to review. Submit it when the details are correct.':'Still needed: '+result.missing.join(', ')+'. You can reply or fill in the form.';
   input.value='';status.textContent=result.show_review?'Your review form is ready. Nothing has been submitted.':'Reply ready.';
  }catch(error){status.textContent=error.message;}finally{pending(false);input.focus();}
 });
 root.querySelectorAll('[data-assistant-prompt]').forEach(button=>button.addEventListener('click',()=>{if(busy||input.disabled)return;input.value=button.dataset.assistantPrompt;chat.requestSubmit();}));
 reset.addEventListener('click',async()=>{if(busy||!confirm('Clear this conversation and the current trip details?'))return;pending(true);try{await request({mode:'reset'});location.reload();}catch(error){status.textContent=error.message;pending(false);}});
 review.addEventListener('submit',event=>{if(busy){event.preventDefault();return;}const start=review.elements.start_datetime,end=review.elements.end_datetime;if(end.value<=start.value){event.preventDefault();end.setCustomValidity('Return time must be after departure.');end.reportValidity();}});
 for(const field of [review.elements.start_datetime,review.elements.end_datetime])field.addEventListener('input',()=>review.elements.end_datetime.setCustomValidity(''));
})();
