'use strict';
(()=>{
 const form=document.querySelector('[data-tracking-chat]');if(!form)return;
 const log=document.querySelector('[data-tracking-log]'),notice=form.querySelector('[data-tracking-error]'),button=form.querySelector('button');let busy=false;
 const bubble=(speaker,text)=>{const node=document.createElement('article'),title=document.createElement('strong'),body=document.createElement('p');node.className='tracking-chat-message';title.textContent=speaker;body.textContent=text;node.append(title,body);log.append(node);return node;};
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(busy||!form.reportValidity())return;
  const question=form.elements.message.value.trim();if(!question)return;
  busy=true;button.disabled=true;notice.textContent='Checking your requests…';
  try{
   const response=await fetch('api.php?action=assistant',{method:'POST',body:new URLSearchParams({mode:'tracking',csrf:form.elements.csrf.value,message:question}),headers:{Accept:'application/json'}});
   const result=await response.json();if(!response.ok)throw Error(result.error||'Tracking is temporarily unavailable.');
   bubble('You',question);const answer=bubble('Tracking assistant',result.reply);
   for(const item of result.items||[]){const url=new URL(item.url,location.href);if(url.origin!==location.origin)continue;const link=document.createElement('a');link.className='btn small';link.href=url.href;link.textContent='Open '+item.reference;answer.append(link);}
   form.elements.message.value='';notice.textContent='Checked '+(result.checked_at||'just now');log.scrollTop=log.scrollHeight;
  }catch(error){notice.textContent=error instanceof SyntaxError?'Could not load tracking. Please try again.':error.message;}
  finally{busy=false;button.disabled=false;form.elements.message.focus();}
 });
})();
