'use strict';
(() => {
 const container=document.querySelector('[data-slip-qr]');
 const button=document.querySelector('[data-print-slip]');
 const status=document.querySelector('[data-qr-status]');
 if(!container||!button) return;
 try {
  const reference=container.dataset.slipQr;
  if(!reference) throw new Error('Missing slip number');
  const qr=qrcodegen.QrCode.encodeText(reference,qrcodegen.QrCode.Ecc.MEDIUM);
  const border=4, size=qr.size+border*2, ns='http://www.w3.org/2000/svg';
  const svg=document.createElementNS(ns,'svg');
  svg.setAttribute('viewBox',`0 0 ${size} ${size}`);svg.setAttribute('role','img');svg.setAttribute('aria-label',`QR code for slip ${reference}`);svg.setAttribute('shape-rendering','crispEdges');
  const background=document.createElementNS(ns,'rect');background.setAttribute('width',String(size));background.setAttribute('height',String(size));background.setAttribute('fill','#fff');svg.append(background);
  const modules=[];
  for(let y=0;y<qr.size;y++) for(let x=0;x<qr.size;x++) if(qr.getModule(x,y)) modules.push(`M${x+border},${y+border}h1v1h-1z`);
  const path=document.createElementNS(ns,'path');path.setAttribute('d',modules.join(''));path.setAttribute('fill','#000');svg.append(path);container.replaceChildren(svg);
  button.disabled=false;button.textContent='Print / Save as PDF';button.addEventListener('click',()=>window.print());
 } catch(error) {
  button.textContent='QR code unavailable';status.textContent='Refresh the page to generate the QR code before printing.';
 }
})();
