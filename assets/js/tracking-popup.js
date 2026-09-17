'use strict';
(()=>{
 const popup=document.querySelector('#header-help-popup'),header=document.querySelector('.header-tracking-search');if(!popup||!header)return;
 const form=popup.querySelector('[data-header-help-form]'),log=popup.querySelector('[data-header-help-log]'),status=popup.querySelector('[data-header-help-status]'),send=form.querySelector('[type=submit]');let busy=false,opener=null;const island=document.querySelector('[data-assistant-island]');
 function position(){
  if(!island||popup.hidden)return;
  const rect=island.getBoundingClientRect(),width=Math.min(Math.max(rect.width,360),560,window.innerWidth-24),left=Math.max(12,Math.min(rect.left+(rect.width-width)/2,window.innerWidth-width-12));
  popup.style.left=left+'px';popup.style.top=Math.max(12,rect.top)+'px';popup.style.width=width+'px';popup.style.maxHeight=Math.max(160,window.innerHeight-Math.max(12,rect.top)-12)+'px';
 }
 if(island)popup.classList.add('island-expanded-panel');
 window.addEventListener('resize',position);window.addEventListener('scroll',position,{passive:true});
 function isQuestion(text){
  if(/^(?:[A-Z]+-)?\d[\w-]*$/i.test(text))return false;
  // Keep slip references on the normal tracking route, while natural-language
  // booking requests open the assistant directly from the island.
  return /[?？]/u.test(text)||/^(how|what|why|when|where|who|which|can|could|would|should|is|are|do|does|did|has|have|will|please|help|explain|tell me|show me|book|booking|reserve|reservation|vehicle|car|van|driver|personnel|trip|ride|travel|availability|available|schedule|request|paano|ano|bakit|kailan|saan|sino|pwede|puwede|maaari|mayroon|meron)\b/i.test(text)||/^(hi|hello|hey|kumusta|kamusta|good morning|good afternoon|good evening)[!. ]*$/i.test(text);
 }
 const reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)');
 let phase='closed',motion=[],finishMotion=null;
 function settle(){
  const finish=finishMotion;finishMotion=null;
  motion.forEach(animation=>animation.cancel());motion=[];
  finish?.();
 }
 function frame(rect,radius){return {left:rect.left+'px',top:rect.top+'px',width:rect.width+'px',height:rect.height+'px',borderRadius:radius+'px'};}
 function morph(opening,finish){
  if(!island||reducedMotion.matches||typeof popup.animate!=='function'){finish();return;}
  const pill=header.getBoundingClientRect(),panel=popup.getBoundingClientRect();
  const compact=frame(pill,pill.height/2),expanded=frame(panel,24);
  const overshoot=frame({left:panel.left-3,top:panel.top,width:panel.width+6,height:panel.height+6},26);
  finishMotion=finish;
  const shell=popup.animate(opening?[compact,{...overshoot,offset:.72},expanded]:[expanded,compact],{duration:opening?620:380,easing:opening?'cubic-bezier(.22,.8,.25,1)':'cubic-bezier(.4,0,.2,1)',fill:'both'});
  motion.push(shell);
  // Animate the surface bounds; fade content separately so text never stretches.
  for(const child of popup.children)motion.push(child.animate(opening?[{opacity:0,transform:'translateY(8px)'},{opacity:1,transform:'translateY(0)'}]:[{opacity:1},{opacity:0}],{duration:opening?230:100,delay:opening?240:0,fill:'both',easing:'ease-out'}));
  shell.finished.then(()=>{if(finishMotion===finish)settle();},()=>{});
 }
 function open(){
  if(phase==='open'||phase==='opening')return;
  settle();opener=document.activeElement;phase='opening';
  popup.hidden=false;position();island?.classList.add('is-expanded');header.setAttribute('aria-expanded','true');
  morph(true,()=>{phase='open';form.elements.message.focus({preventScroll:true});log.scrollTop=log.scrollHeight;});
 }
 function close(){
  if(phase==='closed'||phase==='closing')return;
  settle();phase='closing';
  morph(false,()=>{popup.hidden=true;phase='closed';island?.classList.remove('is-expanded');header.setAttribute('aria-expanded','false');opener?.focus({preventScroll:true});});
 }
 window.addEventListener('resize',()=>{settle();position();});
 window.addEventListener('scroll',()=>{settle();position();},{passive:true});
 reducedMotion.addEventListener('change',settle);
 popup.querySelector('[data-header-help-close]').addEventListener('click',close);popup.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();close();}});
 const bubble=(speaker,text)=>{const node=document.createElement('article'),title=document.createElement('strong'),body=document.createElement('p');node.className='header-help-message'+(speaker==='You'?' from-user':'');title.textContent=speaker;body.textContent=text;node.append(title,body);log.append(node);return node;};
 async function ask(question){
  if(busy){form.elements.message.value=question;status.textContent='Please wait for the current reply, then send your next question.';return;}
  busy=true;send.disabled=true;status.textContent='';bubble('You',question);const thinking=bubble('VPRS assistant','Thinking…');thinking.classList.add('header-help-thinking');thinking.setAttribute('role','status');form.elements.message.value='';log.scrollTop=log.scrollHeight;
  try{
   const response=await fetch('api.php?action=assistant',{method:'POST',body:new URLSearchParams({mode:'popup',message:question,csrf:form.elements.csrf.value}),headers:{Accept:'application/json'}});
   const result=await response.json();if(!response.ok)throw Error(result.error||'The assistant could not reply. Please try again.');
   thinking.remove();const answer=bubble('VPRS assistant',result.reply);
   for(const item of result.items||[]){const url=new URL(item.url,location.href);if(url.origin!==location.origin)continue;const link=document.createElement('a');link.href=url.href;link.className='btn small';link.textContent='Open '+item.reference;answer.append(link);}
   for(const item of result.availability?.items||[]){const line=document.createElement('p');line.textContent=item.name+' — '+(item.available?'Available':'Unavailable')+'\n'+item.detail+' · '+item.reason;line.className='header-help-resource';answer.append(line);}
   status.textContent='';
  }catch(error){status.textContent=error instanceof SyntaxError?'The assistant could not connect. Please try again.':error.message;if(!form.elements.message.value)form.elements.message.value=question;}
  finally{thinking.remove();busy=false;send.disabled=false;log.scrollTop=log.scrollHeight;}
 }
 header.setAttribute('aria-controls','header-help-popup');header.setAttribute('aria-expanded','false');
 header.addEventListener('submit',event=>{const text=header.elements.q.value.trim();if(!isQuestion(text))return;event.preventDefault();open();ask(text);});
 form.addEventListener('submit',event=>{event.preventDefault();const text=form.elements.message.value.trim();if(text&&form.reportValidity())ask(text);});
})();
