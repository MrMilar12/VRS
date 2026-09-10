"""HTTP integration tests against an isolated copy and disposable SQLite database.
Run: python3 tests/smoke.py /path/to/php
"""
import http.cookiejar, urllib.request, urllib.parse, urllib.error, re, sys, tempfile, shutil, subprocess, time, socket, json, sqlite3
from pathlib import Path
from datetime import datetime,timedelta
from auth_support import finish_mfa
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
        text,url=finish_mfa(self.post,email,text,url)
        assert url.endswith('index.php'),(email,url,text)
def check(value,label):
    assert value,label
    print('PASS:',label)
with tempfile.TemporaryDirectory(prefix='vrs-http-') as folder:
    app=Path(folder)/'app';shutil.copytree(ROOT,app,ignore=shutil.ignore_patterns('.git','*.sqlite','*.sqlite-journal','local.php','auth.key','__pycache__'))
    sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
    # Keep integration checks offline, regardless of the configured default AI provider.
    with (app/'config/system.php').open('r+') as settings:
        config_text=settings.read().replace("getenv('BOOKING_AI_PROVIDER') ?: 'ollama'", "'openai'").replace("getenv('OPENAI_API_KEY') ?: ''", "''")
        settings.seek(0);settings.write(config_text);settings.truncate()
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
            for page in ['dashboard','developer','assistant','calendar','requisitions','create','approvals','dispatch','vehicles','drivers','offices','users','maintenance','reports','audit','settings','notifications']:
                code,text,_=admin.get('index.php?page='+page);check(code==200 and '</html>' in text,'Render '+page)
            for page in ['vehicles','drivers','offices','users']:
                code,text,_=admin.get(f'index.php?page={page}&edit=1');check(code==200 and 'Save ' in text,'Edit form '+page)
            for path in ['storage/demo.sqlite','config/system.php','.git/config','includes/database.php','database/vehicle_requisition.sql']:
                check(admin.get(path)[0]==404,'Private path blocked: '+path)
            requester=Client(base);requester.login('requester@vrs.local')
            check(requester.get('index.php?page=users')[0]==403,'Requester cannot manage users')
            check(requester.get('index.php?page=developer')[0]==403,'Developer page requires Administrator')
            try:
                requester.post('api.php?action=updates',{'mode':'apply','current':'a'*40,'target':'b'*40,'password':'Demo@12345'})
                raise AssertionError('Requester reached code updater')
            except urllib.error.HTTPError as error:
                check('permission' in error.read().decode(),'Update API rejects non-administrator before any Git operation')
            try:
                admin.post('api.php?action=updates',{'mode':'apply','current':'a'*40,'target':'b'*40,'password':'wrong'})
                raise AssertionError('Wrong updater password accepted')
            except urllib.error.HTTPError as error:
                check('administrator password' in error.read().decode(),'Code updates require password confirmation')
            try:
                admin.post('api.php?action=updates',{'mode':'check','csrf':'invalid'})
                raise AssertionError('Updater CSRF accepted')
            except urllib.error.HTTPError as error:
                check('session token expired' in error.read().decode(),'Update checks enforce CSRF')
            check('access denied' in requester.get('index.php?page=request&id=1')[1].lower(),'Requester cannot read another office request')
            code,assistant_page,_=requester.get('index.php?page=assistant')
            check(code==200 and 'AI booking is not connected yet' in assistant_page and 'Submit request for approval' in assistant_page,'Assistant explains missing configuration and links regular form')
            check(re.search(r'data-booking-review-panel\s+hidden',assistant_page) is not None,'Assistant review starts hidden until trip details are ready')
            check(requester.get('api.php?action=assistant')[0]==405,'Assistant requires POST')
            def assistant_post(data):
                try:
                    return requester.post('api.php?action=assistant',data)[0]
                except urllib.error.HTTPError as error:
                    return error.read().decode()
            check('session token expired' in assistant_post({'message':'Book a van','csrf':'bad'}),'Assistant enforces CSRF')
            check('not connected' in assistant_post({'message':'Book a van'}),'Unconfigured assistant fails clearly without making a booking')
            check('reset' in assistant_post({'mode':'reset'}),'Assistant supports clearing conversation')
            day=(datetime.now()-timedelta(days=5)).strftime('%Y-%m-%d')
            data={'action':'save_request','vehicle_type':'SUV','passengers':'Ana Flores, Test Guest','start_datetime':day+'T08:00','end_datetime':day+'T12:00','destination':'HTTP Integration Test','purpose':'Complete workflow test','fuel_quantity':'5','office_id':'3','submit_mode':'submit'}
            text,url=requester.post('actions.php',data);match=re.search(r'id=(\d+)',url);check(bool(match),'Create requisition');rid=match[1]
            check('Pending Administrative Approval' in text and 'Administrative Office' in text,'Office binding and initial status')
            text,_=requester.post('actions.php',{'action':'approve','id':rid,'password':'Demo@12345'});check('permission' in text,'Requester cannot approve')
            supervisor=Client(base);supervisor.login('supervisor@vrs.local')
            text,_=admin.post('actions.php',{'action':'approve','id':rid,'password':'wrong'});check('password is incorrect' in text,'Approval requires correct password')
            text,_=admin.post('actions.php',{'action':'return_correction','id':rid,'password':'Demo@12345','remarks':'Add the meeting venue'});check('Returned for Correction' in text,'Return for correction')
            text,_=requester.post('actions.php',{**data,'id':rid,'destination':'HTTP Integration Test - corrected'});check('Pending Administrative Approval' in text,'Revise and resubmit')
            text,_=supervisor.post('actions.php',{'action':'approve','id':rid,'password':'Demo@12345','remarks':'Reviewed'});check('do not have permission' in text,'Supervisor approval denied')
            officer=Client(base);officer.login('admin@vrs.local')
            for reviewer in [supervisor, officer]:
                check(reviewer.get('index.php?page=approvals')[0]==403,'Non-administrator approval inbox denied')
                check('data-standard-approve' not in reviewer.get('index.php?page=request&id='+rid)[1],'Non-administrator approval controls hidden')
                for decision in ['approve','reject','return_correction']:
                    text,_=reviewer.post('actions.php',{'action':decision,'id':rid,'password':'Demo@12345','remarks':'Unauthorized review','vehicle_id':'3','driver_id':'5'})
                    check('do not have permission' in text,'Non-administrator review denied: '+decision)
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                check(db.execute('SELECT status FROM requisitions WHERE id=?',(rid,)).fetchone()[0]=='Pending Administrative Approval','Denied reviews leave status unchanged')
                db.execute("UPDATE requisitions SET status='Pending Supervisor' WHERE id=?",(rid,))
            check('name="vehicle_id"' in admin.get('index.php?page=request&id='+rid)[1],'Legacy supervisor-pending request supports administrator assignment')
            available=json.loads(officer.get(f'api.php?action=availability&id={rid}&vehicle_id=3&driver_id=5')[1]);check(available['available'],'Availability API')
            text,_=admin.post('actions.php',{'action':'approve','id':rid,'password':'Demo@12345','vehicle_id':'3','driver_id':'5','remarks':'Confirmed'});check('Request marked approved' in text,'Administrative assignment and approval')
            text,_=requester.post('print/requisition.php',{'id':rid});check('Requisition Slip for Vehicle Use' in text and 'HTTP Integration Test - corrected' in text and 'data-slip-qr="VR-' in text,'Populated requisition printing')
            _,assistant_url=requester.post('actions.php',{**data,'destination':'Assistant review booking','return_to':'index.php?page=assistant','status':'Approved','vehicle_id':'3','requester_id':'1'})
            assistant_id=re.search(r'id=(\d+)',assistant_url)[1]
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                stored=db.execute('SELECT status,vehicle_id,requester_id FROM requisitions WHERE id=?',(assistant_id,)).fetchone()
                owner=db.execute("SELECT id FROM users WHERE email='requester@vrs.local'").fetchone()[0]
                check(stored==('Pending Administrative Approval',None,owner),'Assistant review submits only for the signed-in requester and cannot self-approve or assign')
            # A second request with an overlapping vehicle must fail final approval.
            _,url=requester.post('actions.php',{**data,'destination':'HTTP conflict test'});conflict_id=re.search(r'id=(\d+)',url)[1]
            text,_=admin.post('actions.php',{'action':'approve','id':conflict_id,'password':'Demo@12345','vehicle_id':'3','driver_id':'5'});check('overlapping' in text,'Overlapping final approval blocked')
            # Conflicting resources are visibly blocked and override is explicit.
            code,assignment_form,_=admin.get('index.php?page=request&id='+conflict_id)
            vehicle_options=re.search(r'<select name="vehicle_id"[^>]*>(.*?)</select>',assignment_form,re.S)[1]
            check(re.search(r'<option value="3"[^>]*disabled',vehicle_options) is not None,'Booked vehicle disabled in assignment selector')
            check('data-standard-approve' not in officer.get('index.php?page=request&id='+conflict_id)[1],'Administrative officer cannot access approval controls')
            blocked=json.loads(officer.get(f'api.php?action=availability&id={conflict_id}&vehicle_id=3&driver_id=5')[1])
            check(not blocked['available'] and not blocked['overridable'],'Officer availability check blocks conflicting booking')
            blocked_admin=json.loads(admin.get(f'api.php?action=availability&id={conflict_id}&vehicle_id=3&driver_id=5')[1])
            check(not blocked_admin['available'] and blocked_admin['overridable'],'Administrator sees explicit conflict override eligibility')
            code,assignment_form,_=admin.get('index.php?page=request&id='+conflict_id)
            check('data-enable-override' in assignment_form and 'value="override_approve"' in assignment_form,'Administrator has separate Override and approve control')
            override_data={'id':conflict_id,'password':'Demo@12345','vehicle_id':'3','driver_id':'5','override_reason':'Emergency coordination approved by the fleet administrator.'}
            text,_=admin.post('actions.php',{'action':'approve',**override_data})
            check('This schedule is blocked' in text,'Typing a reason does not bypass normal approval')
            text,_=officer.post('actions.php',{'action':'override_approve',**override_data})
            check('do not have permission' in text,'Forged officer override rejected')
            text,_=admin.post('actions.php',{'action':'override_approve',**override_data,'override_reason':'Short'})
            check('at least 15 characters' in text,'Override requires a meaningful reason')
            text,_=admin.post('actions.php',{'action':'override_approve',**override_data,'password':'wrong'})
            check('password is incorrect' in text,'Override requires administrator password confirmation')
            text,_=admin.post('actions.php',{'action':'override_approve',**override_data,'vehicle_id':'6'})
            check('Vehicle is Under Maintenance' in text,'Override cannot bypass an unavailable vehicle')
            text,_=admin.post('actions.php',{'action':'override_approve',**override_data})
            check('Request marked approved' in text,'Explicit administrator override approves conflicting assignment')
            check('Schedule conflict overridden' in admin.get('index.php?page=audit')[1],'Override is recorded in audit history')
            check('[Override:' in admin.get('index.php?page=request&id='+conflict_id)[1],'Override reason preserved in approval history')
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
            # Administrators see approved fleet trips; other roles remain owner-scoped.
            calendar_day=(datetime.now()+timedelta(days=70)).strftime('%Y-%m-%d')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                accounts=db.execute('SELECT id,email,role,office_id FROM users ORDER BY id').fetchall()
                vehicle_ids=[row[0] for row in db.execute('SELECT id FROM vehicles ORDER BY id')]
                driver_ids=[row[0] for row in db.execute('SELECT id FROM drivers ORDER BY id')]
                for i,(uid,email,role,office_id) in enumerate(accounts):
                    db.execute("INSERT INTO requisitions(reference,requester_id,office_id,vehicle_type,vehicle_id,driver_id,passengers,start_datetime,end_datetime,destination,purpose,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)",(f'CAL-USER-{uid}',uid,office_id,'Van',vehicle_ids[i%len(vehicle_ids)],driver_ids[i%len(driver_ids)],'Calendar test passenger',calendar_day+' 08:00:00',calendar_day+' 10:00:00',f'Private calendar destination {uid}','Calendar access test','Approved',calendar_day+' 07:00:00'))
                for state in ['Draft','Returned for Correction','Pending Supervisor','Pending Administrative Approval','Rejected','Cancelled','Approved','Dispatched','Returned','Completed']:
                    db.execute("INSERT INTO requisitions(reference,requester_id,office_id,vehicle_type,vehicle_id,driver_id,passengers,start_datetime,end_datetime,destination,purpose,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)",('CAL-STATE-'+state,3,1,'Van',vehicle_ids[-1],driver_ids[-1],'State test passenger',calendar_day+' 11:00:00',calendar_day+' 12:00:00','Calendar state '+state,'Calendar state access test',state,calendar_day+' 07:00:00'))
                # Reproduce an administrator-owned, unassigned pending request.
                db.execute("INSERT INTO requisitions(reference,requester_id,office_id,vehicle_type,passengers,start_datetime,end_datetime,destination,purpose,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)",('CAL-ADMIN-PENDING',1,1,'Van','Pending passenger',calendar_day+' 08:00:00',calendar_day+' 10:00:00','Bulacan, Philippines','Unapproved own request','Pending Administrative Approval',calendar_day+' 07:00:00'))
                db.commit()
                for uid,email,role,office_id in accounts:
                    client=Client(base);client.login(email)
                    expected=db.execute("SELECT id,vehicle_id,driver_id,office_id FROM requisitions WHERE requester_id=? AND status IN ('Approved','Dispatched','Returned','Completed')",(uid,)).fetchall()
                    if role=='Administrator':
                        expected+=db.execute("SELECT id,vehicle_id,driver_id,office_id FROM requisitions WHERE status IN ('Approved','Dispatched','Returned','Completed') AND requester_id!=?",(uid,)).fetchall()
                    expected_ids={row[0] for row in expected}
                    # Tampered parameters cannot override the authenticated owner.
                    code,payload,_=client.get('api.php?action=calendar&user_id=1&requester_id=1&office_id=1')
                    payload=json.loads(payload)
                    check(all(event['status'] in ['Approved','Dispatched','Returned','Completed'] for event in payload['events']),role+' calendar hides all unapproved requests, including own')
                    check(code==200 and {event['id'] for event in payload['events']}==expected_ids,role+' calendar respects approved-fleet and ownership rules')
                    check({v['id'] for v in payload['vehicles']}=={row[1] for row in expected if row[1]},role+' timeline vehicles scoped to visible trips')
                    code,calendar_html,_=client.get('index.php?page=calendar')
                    check(code==200,role+' retains calendar access')
                    for field,column in [('vehicle',1),('driver',2),('office',3)]:
                        options=re.search(r'<select id="filter-'+field+r'"[^>]*>(.*?)</select>',calendar_html,re.S)[1]
                        actual={int(v) for v in re.findall(r'<option value="(\d+)"',options)}
                        check(actual=={row[column] for row in expected if row[column]},role+' '+field+' filter respects calendar access')
                    code,schedule,_=client.get('print/daily-schedule.php?date='+calendar_day+'&requester_id=1')
                    if role=='Administrator':
                        check(code==200 and all(f'Private calendar destination {other[0]}' in schedule for other in accounts),'Administrator prints all approved fleet schedules')
                        titles={event['title'] for event in payload['events']}
                        check('Bulacan, Philippines' not in titles and 'Bulacan, Philippines' not in schedule,'Administrator own unassigned pending trip is hidden')
                        confirmed=['Approved','Dispatched','Returned','Completed']
                        hidden=['Draft','Returned for Correction','Pending Supervisor','Pending Administrative Approval','Rejected','Cancelled']
                        check(all('Calendar state '+state in titles for state in confirmed) and all('Calendar state '+state not in titles for state in hidden),'Administrator sees approved lifecycle, not pending or cancelled requests')
                        check(all('Calendar state '+state in schedule for state in confirmed) and all('Calendar state '+state not in schedule for state in hidden),'Printed fleet schedule excludes unapproved and cancelled trips')
                    else:
                        check(code==200 and f'Private calendar destination {uid}' in schedule and all(f'Private calendar destination {other[0]}' not in schedule for other in accounts if other[0]!=uid),role+' printed schedule contains only owned trips')
            anonymous=Client(base)
            check(anonymous.get('api.php?action=calendar')[0]==401,'Calendar API requires sign-in')
            print('All HTTP integration checks passed; disposable database removed.')
        finally:
            proc.terminate();proc.wait(timeout=10)
            log.seek(0);output=log.read()
            if 'Fatal error' in output or 'Warning:' in output:print(output);raise AssertionError('PHP server warnings found')
