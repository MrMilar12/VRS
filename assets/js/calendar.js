'use strict';
(()=>{
const $=id=>document.getElementById(id),params=new URLSearchParams(location.search);
const localDate=s=>new Date(s.replace(' ','T')),dateKey=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
let selected=/^\d{4}-\d{2}-\d{2}$/.test(params.get('date')||'')?new Date(params.get('date')+'T12:00:00'):new Date();if(isNaN(selected))selected=new Date();
let view=['month','week','day','timeline'].includes(params.get('view'))?params.get('view'):'month',events=[],vehicles=[];
const escape=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const color=s=>({Approved:'blue',Dispatched:'purple',Returned:'green',Completed:'green',Maintenance:'red','Pending Supervisor':'amber','Pending Administrative Approval':'amber'}[s]||'gray');
const time=s=>localDate(s).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});
const dayEvents=day=>{const start=new Date(day);start.setHours(0,0,0,0);const end=new Date(start);end.setDate(end.getDate()+1);return filtered().filter(e=>localDate(e.start)<end&&localDate(e.end)>start).sort((a,b)=>a.start.localeCompare(b.start));};
function filtered(){return events.filter(e=>['vehicle','type','office','driver','status'].every(key=>!$('filter-'+key).value||String(e[['vehicle','office','driver'].includes(key)?key+'_id':key])===$('filter-'+key).value)&&e.title.toLowerCase().includes($('filter-destination').value.toLowerCase()));}
function chip(e){return `<button class="calendar-event ${color(e.status)}" data-event="${escape(e.id)}"><b>${e.status==='Maintenance'?'Service':time(e.start)}</b> ${escape(e.vehicle)}<span>${escape(e.office_code)} · ${escape(e.title)}</span></button>`;}
function render(){
$('calendar-date').value=dateKey(selected);$('print-schedule').href='print/daily-schedule.php?date='+dateKey(selected);
document.querySelectorAll('[data-view]').forEach(b=>{b.classList.toggle('active',b.dataset.view===view);b.setAttribute('aria-pressed',b.dataset.view===view?'true':'false');});
const titleOptions=view==='month'?{month:'long',year:'numeric'}:{weekday:'long',month:'long',day:'numeric',year:'numeric'};$('calendar-title').textContent=selected.toLocaleDateString('en-US',titleOptions);
const target=$('calendar-content');
if(view==='month'||view==='week'){
 let start=new Date(selected);start.setHours(0,0,0,0);if(view==='month')start.setDate(1);start.setDate(start.getDate()-start.getDay());
 let html='<div class="calendar-grid '+(view==='week'?'week-grid':'')+'">'+['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d=>`<div class="weekday">${d}</div>`).join('');
 const count=view==='month'?42:7;for(let i=0;i<count;i++){const day=new Date(start);day.setDate(start.getDate()+i);const list=dayEvents(day);html+=`<div class="calendar-cell ${day.getMonth()!==selected.getMonth()&&view==='month'?'outside':''} ${dateKey(day)===dateKey(new Date())?'is-today':''}"><button class="day-number" data-day="${dateKey(day)}" aria-label="View schedule for ${dateKey(day)}">${day.getDate()}</button><div>${list.slice(0,view==='week'?20:3).map(chip).join('')}${list.length>3&&view==='month'?`<button class="more-events" data-day="${dateKey(day)}">+${list.length-3} more</button>`:''}</div></div>`;}
 target.innerHTML=html+'</div>';
}else if(view==='day'){
 const list=dayEvents(selected);target.innerHTML=list.length?'<div class="day-agenda">'+list.map(e=>`<button class="agenda-item" data-event="${escape(e.id)}"><div class="agenda-time">${time(e.start)}<span>${time(e.end)}</span></div><div class="agenda-main"><h3>${escape(e.title)}</h3><p>${escape(e.vehicle)} · ${escape(e.plate)}</p><small>${escape(e.office)} · ${escape(e.driver)}</small></div><span class="badge ${color(e.status)}">${escape(e.status)}</span></button>`).join('')+'</div>':'<div class="empty-state"><h3>No journeys scheduled</h3><p>There are no matching events for this date.</p></div>';
}else{
const list=dayEvents(selected),dayStart=new Date(selected);dayStart.setHours(0,0,0,0);
let html='<div class="timeline-scroll"><div class="timeline"><div class="timeline-header"><strong>Vehicle / plate</strong><div class="timeline-hours">'+Array.from({length:12},(_,i)=>`<span>${String(i*2).padStart(2,'0')}:00</span>`).join('')+'</div></div>';
for(const v of vehicles.filter(v=>(!$('filter-vehicle').value||String(v.id)===$('filter-vehicle').value)&&(!$('filter-type').value||v.type===$('filter-type').value))){const assigned=list.filter(e=>String(e.vehicle_id)===String(v.id));html+=`<div class="timeline-row"><div class="timeline-vehicle"><strong>${escape(v.model)}</strong><small>${escape(v.plate)}</small></div><div class="timeline-track" style="height:${Math.max(58,assigned.length*38+14)}px">${assigned.map((e,i)=>{const left=Math.max(0,(localDate(e.start)-dayStart)/864000),right=Math.min(100,(localDate(e.end)-dayStart)/864000);return `<button class="timeline-event ${color(e.status)}" style="left:${left}%;width:${Math.max(.5,right-left)}%;top:${8+i*38}px" data-event="${escape(e.id)}">${escape(e.office_code)} · ${escape(e.title)}</button>`;}).join('')}</div></div>`;}target.innerHTML=html+'</div></div>';
}
target.querySelectorAll('[data-day]').forEach(b=>b.addEventListener('click',()=>{selected=new Date(b.dataset.day+'T12:00:00');view='day';render();}));
target.querySelectorAll('[data-event]').forEach(b=>b.addEventListener('click',()=>openEvent(b.dataset.event)));
}
function openEvent(id){const e=events.find(e=>String(e.id)===id);if(!e)return;$('event-detail').innerHTML=`<span class="badge ${color(e.status)}">${escape(e.status)}</span><h2>${escape(e.title)}</h2><p class="muted">${escape(e.purpose||'Scheduled maintenance')}</p><dl class="detail-grid">${Object.entries({'Vehicle':e.vehicle+' · '+e.plate,'Office':e.office,'Departure':localDate(e.start).toLocaleString(),'Estimated return':localDate(e.end).toLocaleString(),'Requester':e.requester||'—','Driver':e.driver}).map(([k,v])=>`<div><dt>${k}</dt><dd>${escape(v)}</dd></div>`).join('')}</dl>${e.status!=='Maintenance'?`<a class="btn primary" href="${escape(e.url)}">View full requisition →</a>`:''}`;$('event-dialog').showModal();}
$('calendar-prev').addEventListener('click',()=>move(-1));$('calendar-next').addEventListener('click',()=>move(1));function move(n){if(view==='month'){selected.setDate(1);selected.setMonth(selected.getMonth()+n);}else selected.setDate(selected.getDate()+n*(view==='week'?7:1));render();}
$('calendar-today').addEventListener('click',()=>{selected=new Date();render();});$('calendar-date').addEventListener('change',event=>{if(event.target.value){selected=new Date(event.target.value+'T12:00:00');render();}});
document.querySelectorAll('[data-view]').forEach(b=>b.addEventListener('click',()=>{view=b.dataset.view;render();}));document.querySelectorAll('.calendar-filters select,.calendar-filters input[type="search"]').forEach(el=>el.addEventListener('input',render));
fetch('api.php?action=calendar').then(r=>{if(!r.ok)throw Error();return r.json();}).then(data=>{events=data.events;vehicles=data.vehicles;render();}).catch(()=>{$('calendar-content').innerHTML='<div class="empty-state"><h3>Unable to load the calendar</h3><p>Refresh the page or sign in again.</p></div>';});
})();
