'use strict';
const setup=document.querySelector('[data-auth-qr]');
if(setup){
 try{
  const qr=qrcodegen.QrCode.encodeText(setup.dataset.authQr,qrcodegen.QrCode.Ecc.MEDIUM),ns='http://www.w3.org/2000/svg',size=qr.size+8;
  const svg=document.createElementNS(ns,'svg');svg.setAttribute('viewBox',`0 0 ${size} ${size}`);svg.setAttribute('role','img');svg.setAttribute('aria-label','Scan with your authenticator app');svg.setAttribute('shape-rendering','crispEdges');
  const background=document.createElementNS(ns,'rect');background.setAttribute('width',size);background.setAttribute('height',size);background.setAttribute('fill','white');svg.append(background);
  const cells=[];for(let y=0;y<qr.size;y++)for(let x=0;x<qr.size;x++)if(qr.getModule(x,y))cells.push(`M${x+4},${y+4}h1v1h-1z`);
  const path=document.createElementNS(ns,'path');path.setAttribute('fill','black');path.setAttribute('d',cells.join(''));svg.append(path);setup.append(svg);
 }catch(error){setup.textContent='Use the manual setup key below to add your authenticator.';}
}
const download=document.querySelector('[data-download-recovery]');
if(download){download.hidden=false;download.addEventListener('click',()=>{const codes=[...document.querySelectorAll('[data-recovery-code]')].map(code=>code.textContent);const url=URL.createObjectURL(new Blob(['VRS one-time recovery codes\nKeep these private. Each code can be used once.\n\n'+codes.join('\n')],{type:'text/plain'}));const a=document.createElement('a');a.href=url;a.download='vrs-recovery-codes.txt';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);});}
