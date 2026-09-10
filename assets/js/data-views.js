'use strict';
(() => {
 const page=new URLSearchParams(location.search).get('page')||'dashboard';
 const icons={list:'M4 6h16M4 12h16M4 18h16',card:'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'};
 document.querySelectorAll('.app-shell .data-table,.app-shell .vehicle-grid').forEach((surface,index)=>{
  const isTable=surface.matches('table'),host=isTable?surface.closest('.table-scroll'):surface;
  if(!host||host.dataset.viewReady)return;host.dataset.viewReady='true';
  const panel=host.closest('.panel'),heading=panel?.querySelector('.panel-heading h2')?.textContent.trim();
  const name=heading||(isTable?'Records':'Vehicles'),key='vrs:data-view:'+location.pathname+':'+page+':'+index;
  if(!host.id)host.id='record-view-'+index;
  const toolbar=document.createElement('div');toolbar.className='data-view-toolbar';
  const label=document.createElement('span');label.className='data-view-caption';label.textContent=isTable?'Display records':'Display vehicles';toolbar.append(label);
  const group=document.createElement('div');group.className='data-view-switch';group.setAttribute('role','group');group.setAttribute('aria-label',name+' display mode');toolbar.append(group);
  const buttons={};
  for(const mode of ['list','card']){
   const button=document.createElement('button');button.type='button';button.dataset.viewMode=mode;button.setAttribute('aria-controls',host.id);
   const ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg'),path=document.createElementNS(ns,'path');svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('width','16');svg.setAttribute('height','16');svg.setAttribute('fill','none');svg.setAttribute('stroke','currentColor');svg.setAttribute('stroke-width','1.6');svg.setAttribute('aria-hidden','true');path.setAttribute('d',icons[mode]);svg.append(path);
   button.append(svg,document.createTextNode(mode==='list'?'List':'Cards'));buttons[mode]=button;group.append(button);
   button.addEventListener('click',()=>{setView(mode);try{localStorage.setItem(key,mode);}catch(error){/* Private browsing may disable storage. */}});
  }
  if(isTable){
   const headers=[...surface.tHead.rows[0].cells].map(cell=>cell.textContent.trim());
   // Keep one set of rows and controls: switching never clones forms or IDs.
   surface.setAttribute('role','table');surface.tHead.setAttribute('role','rowgroup');
   for(const row of surface.tHead.rows){row.setAttribute('role','row');for(const cell of row.cells){cell.setAttribute('role','columnheader');cell.scope='col';}}
   for(const body of surface.tBodies){body.setAttribute('role','rowgroup');for(const row of body.rows){row.setAttribute('role','row');
    if(row.cells.length===1&&row.cells[0].colSpan>1){row.classList.add('data-view-empty');continue;}
    let column=0;for(const cell of row.cells){cell.setAttribute('role','cell');cell.dataset.columnLabel=headers[column]||'Actions';if(!headers[column]){cell.classList.add('data-view-actions');cell.querySelectorAll('a.icon-button').forEach(link=>{if(!link.textContent.trim()){const text=document.createElement('span');text.className='data-card-action-label';text.textContent='View details';link.append(text);}});}if(cell.textContent.trim().length>90)cell.classList.add('data-card-wide');column+=cell.colSpan;}
   }}
  }
  function setView(mode){
   host.classList.toggle('data-cards',isTable&&mode==='card');host.classList.toggle('vehicle-list-view',!isTable&&mode==='list');
   for(const [value,button] of Object.entries(buttons))button.setAttribute('aria-pressed',String(value===mode));
   host.dataset.viewMode=mode;label.textContent=(mode==='card'?'Card view':'List view')+' · '+(isTable?'Records':'Vehicles');
  }
  host.before(toolbar);let preferred=isTable?'list':'card';try{const saved=localStorage.getItem(key);if(saved==='list'||saved==='card')preferred=saved;}catch(error){}
  setView(preferred);
 });
})();
