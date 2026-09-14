'use strict';
// Run with Node.js. Exercise the production handler with a shadowed form.action.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../assets/js/app.js'),'utf8');
async function checkSave(success){
 let handler,request,navigation,notice;
 class Form {
  constructor(){
   this.action={value:'save_request',toString:()=> '[object HTMLInputElement]'};
   this.elements={namedItem:name=>name==='action'?this.action:null};
   this.values={action:'save_request',csrf:'fixture-token',destination:'Baler',passengers:'Test Passenger'};
   this.dataset={};this.button={name:'submit_mode',value:'draft',disabled:false};
  }
  matches(){return true;}
  getAttribute(name){return name==='action'?'actions.php':null;}
  querySelector(selector){if(selector==='[data-save-status]')return notice||null;return {before:node=>notice=node};}
  querySelectorAll(){return [this.button];}
  setAttribute(){}
  removeAttribute(){}
 }
 class Data extends Map {constructor(form){super(Object.entries(form.values));}}
 const form=new Form();
 const document={baseURI:'http://localhost/vrs/index.php?page=create',querySelector:()=>null,querySelectorAll:()=>[],addEventListener:(type,fn)=>{if(type==='submit')handler=fn;},createElement:()=>({dataset:{},setAttribute(){},focus(){}})};
 vm.runInNewContext(source,{document,HTMLFormElement:Form,FormData:Data,URL,location:{href:document.baseURI,origin:'http://localhost',assign:url=>navigation=url},fetch:async(url,options)=>{
  request={url,options};
  assert.equal(form.button.disabled,true,'Button is disabled during save');
  return {ok:success,redirected:false,headers:{get:()=> 'application/json'},json:async()=>success?{redirect:'index.php?page=request&id=v1_fixture'}:{error:'Please correct the return time.'}};
 }});
 const event={target:form,submitter:form.button,defaultPrevented:false,preventDefault(){this.defaultPrevented=true;}};
 await handler(event);
 assert.equal(event.defaultPrevented,true);
 assert.equal(request.url,'http://localhost/vrs/actions.php','Named action input must not become the URL');
 assert.equal(request.options.method,'POST');
 assert.equal(request.options.body.get('action'),'save_request');
 assert.equal(request.options.body.get('submit_mode'),'draft');
 assert.equal(request.options.body.get('destination'),'Baler');
 assert.equal(form.values.destination,'Baler','Entered fields remain intact');
 assert.equal(form.button.disabled,false);
 if(success)assert.equal(navigation,'http://localhost/vrs/index.php?page=request&id=v1_fixture');
 else {assert.equal(navigation,undefined);assert.equal(notice.textContent,'Please correct the return time.');}
 console.log('PASS: Shadowed form.action posts to actions.php; '+(success?'successful save opens record':'failed save keeps entered data'));
}
(async()=>{await checkSave(false);await checkSave(true);})().catch(error=>{console.error(error);process.exitCode=1;});
