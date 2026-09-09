'use strict';
document.querySelectorAll('[data-address-picker]').forEach(picker => {
 const input = picker.querySelector('[role="combobox"]');
 const value = picker.querySelector('[name="destination"]');
 const list = picker.querySelector('[role="listbox"]');
 const toggle = picker.querySelector('[data-place-toggle]');
 const status = picker.querySelector('[role="status"]');
 const map = picker.querySelector('[data-address-map]');
 const placeholder = picker.querySelector('[data-map-placeholder]');
 const link = picker.querySelector('[data-map-link]');
 const cache = new Map();
 let results = value.value ? [{label:value.value}] : [], active = -1, timer, controller, generation = 0, lastSearch = 0;
 function open(show) {
  list.hidden = !show; input.setAttribute('aria-expanded',String(show)); toggle.setAttribute('aria-expanded',String(show));
  if(!show) { active=-1; input.removeAttribute('aria-activedescendant'); }
 }
 function highlight(index) {
  active=index;
  [...list.children].forEach((option,i) => option.setAttribute('aria-selected',String(i===active)));
  if(list.children[active]) { input.setAttribute('aria-activedescendant',list.children[active].id); list.children[active].scrollIntoView({block:'nearest'}); }
 }
 function render() {
  list.replaceChildren(); active=-1; input.removeAttribute('aria-activedescendant');
  results.forEach((place,i) => {
   const option=document.createElement('div'); option.id=`place-option-${i}`; option.setAttribute('role','option'); option.setAttribute('aria-selected','false'); option.textContent=place.label;
   option.addEventListener('mousedown',event=>event.preventDefault());
   option.addEventListener('click',()=>choose(place)); list.append(option);
  });
  if(!results.length) { const message=document.createElement('div'); message.className='place-empty'; message.textContent=status.textContent; list.append(message); }
 }
 function choose(place) {
  generation++; clearTimeout(timer); controller?.abort();
  input.value=place.label; value.value=place.label; input.setCustomValidity(''); open(false); input.focus(); open(false);
  status.textContent=`Selected: ${place.label}`;
  if(!Number.isFinite(place.lat)) return;
  const {lat,lon}=place;
  const params=new URLSearchParams({bbox:[Math.max(-180,lon-.015),Math.max(-90,lat-.01),Math.min(180,lon+.015),Math.min(90,lat+.01)].join(','),layer:'mapnik',marker:`${lat},${lon}`});
  map.src=`https://www.openstreetmap.org/export/embed.html?${params}`; map.hidden=false; placeholder.hidden=true;
  link.href=`https://www.openstreetmap.org/?mlat=${lat}&mlon=${lon}#map=16/${lat}/${lon}`; link.hidden=false;
 }
 async function search(term,version) {
  const request=new AbortController(); controller=request;
  const timeout=setTimeout(()=>request.abort(),12000);
  status.textContent='Searching places…'; render();
  try {
   let places=cache.get(term.toLowerCase());
   if(!places) {
    lastSearch=Date.now();
    const url=new URL(picker.dataset.searchUrl); url.search=new URLSearchParams({q:term,limit:'8',lang:'en'});
    const response=await fetch(url,{signal:request.signal,credentials:'omit'});
    if(!response.ok) throw new Error('Search unavailable');
    const data=await response.json(); if(!Array.isArray(data.features)) throw new Error('Invalid results');
    const seen=new Set(); places=[];
    for(const feature of data.features) {
     const p=feature.properties||{}, coords=feature.geometry?.coordinates;
     if(!Array.isArray(coords)) continue;
     const [lon,lat]=coords;
     if(!Number.isFinite(lat)||!Number.isFinite(lon)||Math.abs(lat)>90||Math.abs(lon)>180) continue;
     const label=[...new Set([p.name,[p.housenumber,p.street].filter(Boolean).join(' '),p.district,p.city,p.state,p.postcode,p.country].filter(Boolean))].join(', ');
     if(!label||Array.from(label).length>255||seen.has(label)) continue;
     places.push({label,lat,lon}); seen.add(label);
    }
    cache.set(term.toLowerCase(),places);
   }
   if(version!==generation) return;
   results=places; status.textContent=places.length ? `${places.length} places found. Select a place.` : 'No places found. Try adding a city or country.';
   render();
   if(places.length && !list.hidden) highlight(0);
  } catch(error) {
   if(version!==generation) return;
   results=[]; status.textContent='Search unavailable. Check your connection and type again to retry.'; render();
  } finally { clearTimeout(timeout); }
 }
 input.addEventListener('input',()=>{
  const version=++generation; clearTimeout(timer); controller?.abort(); value.value=''; results=[];
  input.setCustomValidity('Select a place from the dropdown.');
  map.hidden=true; map.removeAttribute('src'); link.hidden=true; placeholder.hidden=false;
  const term=input.value.trim(); status.textContent=term.length<3?'Type at least 3 characters to find a place.':'Searching places…'; render(); open(true);
  if(term.length>=3) timer=setTimeout(()=>search(term,version),Math.max(450,1000-(Date.now()-lastSearch)));
 });
 input.addEventListener('focus',()=>{render(); open(true);});
 input.addEventListener('click',()=>open(true));
 toggle.addEventListener('mousedown',event=>event.preventDefault());
 toggle.addEventListener('click',()=>{const show=list.hidden; input.focus(); open(show);});
 input.addEventListener('keydown',event=>{
  if(event.key==='Escape') { open(false); return; }
  if(event.key==='Tab') { open(false); return; }
  if(event.key==='ArrowDown'||event.key==='ArrowUp') {
   event.preventDefault(); open(true);
   if(results.length) highlight(active<0 ? (event.key==='ArrowDown'?0:results.length-1) : (active+(event.key==='ArrowDown'?1:-1)+results.length)%results.length);
  }
  if(event.key==='Enter'&&!list.hidden) { event.preventDefault(); if(results[active]) choose(results[active]); }
 });
 document.addEventListener('click',event=>{if(!input.contains(event.target)&&!toggle.contains(event.target)&&!list.contains(event.target)) open(false);});
 picker.addEventListener('focusout',event=>{if(!picker.contains(event.relatedTarget)) open(false);});
 input.form.addEventListener('submit',event=>{if(!value.value||input.value!==value.value) {event.preventDefault(); input.setCustomValidity('Select a place from the dropdown.'); input.reportValidity();}});
 render();
});
