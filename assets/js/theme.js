'use strict';
(() => {
 const key='vrs:theme',root=document.documentElement,system=window.matchMedia('(prefers-color-scheme: dark)');
 let preference=null;
 try{const saved=localStorage.getItem(key);if(saved==='light'||saved==='dark')preference=saved;}catch(error){}
 function apply(){
  const mode=preference||(system.matches?'dark':'light');root.dataset.theme=mode;
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content',mode==='dark'?'#15221d':'#174c40');
  document.querySelectorAll('[data-theme-mode]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.themeMode===mode)));
 }
 apply();
 document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('[data-theme-mode]').forEach(button=>button.addEventListener('click',()=>{preference=button.dataset.themeMode;try{localStorage.setItem(key,preference);}catch(error){}apply();}));
  document.querySelector('[data-theme-switch]')?.removeAttribute('hidden');apply();
 });
 system.addEventListener('change',()=>{if(!preference)apply();});
 window.addEventListener('storage',event=>{if(event.key===key||event.key===null){preference=event.newValue==='light'||event.newValue==='dark'?event.newValue:null;apply();}});
})();
