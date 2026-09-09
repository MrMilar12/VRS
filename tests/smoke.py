"""HTTP integration tests against an isolated copy and disposable SQLite database.
Run: python3 tests/smoke.py /path/to/php
"""
import http.cookiejar, urllib.request, urllib.parse, urllib.error, re, sys, tempfile, shutil, subprocess, time, socket, json
from pathlib import Path
from datetime import datetime,timedelta
PHP=sys.argv[1] if len(sys.argv)>1 else 'php'
ROOT=Path(__file__).resolve().parents[1]
class Client:
    def __init__(self,base):
        self.base=base
        self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token=''
    def get(self,path):
        try:
            with self.http.open(self.base+'/'+path,timeout=10) as r: code,text,url=r.status,r.read().decode(),r.url
        except urllib.error.HTTPError as r:code,text,url=r.code,r.read().decode(),r.url
        if '<b>Warning</b>' in text or '<b>Fatal error</b>' in text:raise AssertionError(text)
        token=re.search(r'name="csrf" value="([^"]+)"',text)
        if token:self.token=token[1]
        return code,text,url
    def post(self,path,data):
        data={'csrf':self.token,**data}
        with self.http.open(self.base+'/'+path,urllib.parse.urlencode(data).encode(),timeout=10) as r:
            text,url=r.read().decode(),r.url
            if '<b>Warning</b>' in text or '<b>Fatal error</b>' in text:raise AssertionError(text)
            token=re.search(r'name="csrf" value="([^"]+)"',text)
            if token:self.token=token[1]
            return text,url
    def login(self,email):
        self.get('login.php');text,url=self.post('login.php',{'email':email,'password':'Demo@12345'})
        assert url.endswith('index.php'),(email,url,text)
def check(value,label):
    assert value,label
    print('PASS:',label)
with tempfile.TemporaryDirectory(prefix='vrs-http-') as folder:
    app=Path(folder)/'app';shutil.copytree(ROOT,app,ignore=shutil.ignore_patterns('.git','*.sqlite','*.sqlite-journal','local.php','__pycache__'))
    sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
    with open(Path(folder)/'server.log','w+') as log:
        proc=subprocess.Popen([PHP,'-S',f'127.0.0.1:{port}','router.php'],cwd=app,stdout=log,stderr=log)
        try:
            base=f'http://127.0.0.1:{port}';admin=Client(base)
            for _ in range(40):
                try:admin.get('login.php');break
                except OSError:time.sleep(.1)
            code,text,_=admin.get('setup.php')
            check(code==200 and 'System requirements' in text and 'Install workspace' in text,'Installation wizard renders')
            text,_=admin.post('setup.php',{'action':'test','csrf':'invalid'})
            check('session expired' in text,'Installer CSRF rejection')
            text,_=admin.post('setup.php',{'action':'test','db_host':'localhost;dbname=bad','db_port':'3306','db_name':'vrs','db_user':'root'})
            check('valid database hostname' in text,'Installer rejects DSN injection')
            text,_=admin.post('setup.php',{'action':'install','db_host':'127.0.0.1','db_port':'3306','db_name':'vrs','db_user':'root','organization':'Test','admin_name':'Test Admin','admin_email':'admin@example.test','admin_password':'TestPassword123','confirm_password':'wrong'})
            check('passwords do not match' in text and not (app/'config/local.php').exists(),'Invalid installer account makes no configuration changes')
            code,text,_=admin.get('install.php')
            check(code==200 and 'Install workspace' in text,'Browser install.php opens wizard')
            (app/'config/local.php').write_text("<?php return ['demo'=>true];")
            try:
                text,_=admin.post('setup.php',{'action':'install'})
                check('Installation is locked' in text and "demo" in (app/'config/local.php').read_text(),'Existing configuration locks installer')
            finally:
                (app/'config/local.php').unlink()
            admin.login('daniel@vrs.local')
            for page in ['dashboard','calendar','requisitions','create','approvals','dispatch','vehicles','drivers','offices','users','maintenance','reports','audit','settings','notifications']:
                code,text,_=admin.get('index.php?page='+page);check(code==200 and '</html>' in text,'Render '+page)
            for page in ['vehicles','drivers','offices','users']:
                code,text,_=admin.get(f'index.php?page={page}&edit=1');check(code==200 and 'Save ' in text,'Edit form '+page)
            for path in ['storage/demo.sqlite','config/system.php','.git/config','includes/database.php','database/vehicle_requisition.sql']:
                check(admin.get(path)[0]==404,'Private path blocked: '+path)
            requester=Client(base);requester.login('requester@vrs.local')
            check(requester.get('index.php?page=users')[0]==403,'Requester cannot manage users')
            check('access denied' in requester.get('index.php?page=request&id=1')[1].lower(),'Requester cannot read another office request')
            day=(datetime.now()-timedelta(days=5)).strftime('%Y-%m-%d')
            data={'action':'save_request','vehicle_type':'SUV','passengers':'Ana Flores, Test Guest','start_datetime':day+'T08:00','end_datetime':day+'T12:00','destination':'HTTP Integration Test','purpose':'Complete workflow test','fuel_quantity':'5','office_id':'3','submit_mode':'submit'}
            text,url=requester.post('actions.php',data);match=re.search(r'id=(\d+)',url);check(bool(match),'Create requisition');rid=match[1]
            check('Pending Supervisor' in text and 'Administrative Office' in text,'Office binding and initial status')
            text,_=requester.post('actions.php',{'action':'approve','id':rid,'password':'Demo@12345'});check('permission' in text,'Requester cannot approve')
            supervisor=Client(base);supervisor.login('supervisor@vrs.local')
            text,_=supervisor.post('actions.php',{'action':'approve','id':rid,'password':'wrong'});check('password is incorrect' in text,'Approval requires correct password')
            text,_=supervisor.post('actions.php',{'action':'return_correction','id':rid,'password':'Demo@12345','remarks':'Add the meeting venue'});check('Returned for Correction' in text,'Return for correction')
            text,_=requester.post('actions.php',{**data,'id':rid,'destination':'HTTP Integration Test - corrected'});check('Pending Supervisor' in text,'Revise and resubmit')
            text,_=supervisor.post('actions.php',{'action':'approve','id':rid,'password':'Demo@12345','remarks':'Reviewed'});check('Pending Administrative Approval' in text,'Supervisor approval')
            officer=Client(base);officer.login('admin@vrs.local')
            available=json.loads(officer.get(f'api.php?action=availability&id={rid}&vehicle_id=3&driver_id=5')[1]);check(available['available'],'Availability API')
            text,_=officer.post('actions.php',{'action':'approve','id':rid,'password':'Demo@12345','vehicle_id':'3','driver_id':'5','remarks':'Confirmed'});check('Request marked approved' in text,'Administrative assignment and approval')
            text,_=requester.post('print/requisition.php',{'id':rid});check('VEHICLE REQUISITION SLIP' in text and 'HTTP Integration Test - corrected' in text,'Populated requisition printing')
            # A second request with an overlapping vehicle must fail final approval.
            _,url=requester.post('actions.php',{**data,'destination':'HTTP conflict test'});conflict_id=re.search(r'id=(\d+)',url)[1]
            supervisor.post('actions.php',{'action':'approve','id':conflict_id,'password':'Demo@12345'})
            text,_=officer.post('actions.php',{'action':'approve','id':conflict_id,'password':'Demo@12345','vehicle_id':'3','driver_id':'5'});check('overlapping' in text,'Overlapping final approval blocked')
            dispatcher=Client(base);dispatcher.login('dispatch@vrs.local')
            text,_=dispatcher.post('actions.php',{'action':'dispatch','id':rid,'odometer_out':'35870','actual_time':day+'T08:00','condition_text':'Good'});check('Request marked dispatched' in text,'Vehicle dispatch')
            text,_=dispatcher.post('actions.php',{'action':'receive','id':rid,'odometer_in':'35800','actual_time':day+'T11:00','condition_text':'Good'});check('Check the return time and odometer' in text,'Invalid odometer rejected')
            text,_=dispatcher.post('actions.php',{'action':'receive','id':rid,'odometer_in':'35930','actual_time':day+'T11:00','condition_text':'Good','remarks':'Returned safely'});check('Request marked returned' in text and '60 km' in text,'Vehicle return and mileage')
            text,_=dispatcher.post('actions.php',{'action':'complete','id':rid});check('Request marked completed' in text,'Trip completion')
            text,_=admin.post('actions.php',{'action':'settings','csrf':'invalid','organization':'Bad'});check('session token expired' in text,'CSRF rejection')
            text,_=admin.post('actions.php',{'action':'save_record','entity':'offices','code':'TST','name':'Test Office','head':'Test Head','supervisor':'Test Supervisor','contact':'123','status':'Active'});check('Record saved' in text and 'Test Office' in text,'Office management persistence')
            text,_=admin.post('actions.php',{'action':'settings','organization':'Test Organization','supervisor_signatory':'Supervisor','admin_signatory':'Administrative Officer','turnaround_minutes':'45'});check('Settings updated' in text and 'Test Organization' in text,'Settings persistence')
            check('HTTP Integration Test' in admin.get('export.php?from='+day+'&to='+day)[1],'CSV report export')
            check('request' in requester.get('index.php?page=notifications')[1].lower(),'Requester notifications')
            check('Request Completed' in admin.get('index.php?page=audit')[1],'Audit trail records workflow')
            print('All HTTP integration checks passed; disposable database removed.')
        finally:
            proc.terminate();proc.wait(timeout=10)
            log.seek(0);output=log.read()
            if 'Fatal error' in output or 'Warning:' in output:print(output);raise AssertionError('PHP server warnings found')
