"""HTTP integration tests against an isolated copy and disposable SQLite database.
Run: python3 tests/smoke.py /path/to/php
"""
import html, http.cookiejar, urllib.request, urllib.parse, urllib.error, re, sys, tempfile, shutil, subprocess, time, socket, json, sqlite3
from pathlib import Path
from datetime import datetime,timedelta
from zoneinfo import ZoneInfo
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
        with self.http.open(self.base+'/'+path,urllib.parse.urlencode(data,doseq=True).encode(),timeout=10) as r:
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
    app=Path(folder)/'app';shutil.copytree(ROOT,app,ignore=shutil.ignore_patterns('.git','*.sqlite','*.sqlite-journal','local.php','ollama.local.php','auth.key','__pycache__'))
    # Some hosted schemas require a reference on INSERT; exercise that stricter contract.
    schema=app/'database/schema.sqlite.sql'
    schema.write_text(schema.read_text().replace('reference VARCHAR(40) UNIQUE','reference VARCHAR(40) NOT NULL UNIQUE'))
    sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
    # Keep integration checks offline, regardless of the configured default AI provider.
    with (app/'config/system.php').open('r+') as settings:
        config_text=settings.read().replace("getenv('BOOKING_AI_PROVIDER') ?: 'ollama'", "'openai'").replace("getenv('OPENAI_API_KEY') ?: ''", "''")
        settings.seek(0);settings.write(config_text);settings.truncate()
    with open(Path(folder)/'server.log','w+') as log:
        proc=subprocess.Popen([PHP,'-d','opcache.enable=0','-d','opcache.enable_cli=0','-S',f'127.0.0.1:{port}','router.php'],cwd=app,stdout=log,stderr=log)
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
            for page in ['dashboard','developer','assistant','calendar','requisitions','create','approvals','dispatch','vehicles','drivers','personnel','personnel-bookings','personnel-create','offices','users','maintenance','reports','audit','settings','notifications']:
                code,text,_=admin.get('index.php?page='+page);check(code==200 and '</html>' in text,'Render '+page)
            for page in ['vehicles','drivers','offices','users']:
                code,text,_=admin.get(f'index.php?page={page}&edit=1');check(code==200 and 'Save ' in text,'Edit form '+page)
            for path in ['storage/demo.sqlite','config/system.php','.git/config','includes/database.php','database/vehicle_requisition.sql']:
                check(admin.get(path)[0]==404,'Private path blocked: '+path)
            requester=Client(base);requester.login('requester@vrs.local')
            check(requester.get('index.php?page=personnel')[0]==403,'Requester cannot manage personnel')
            admin.get('index.php?page=personnel&add=1')
            text,_=admin.post('actions.php',{'action':'save_record','entity':'personnel','id':'0','full_name':'Test Personnel','employee_number':'HTTP-P001','classification':'Utility','office_id':'1','position':'Technician','contact':'','status':'Available'})
            check('Record saved.' in text and 'Test Personnel' in text,'Create personnel from management form')
            code,text,url=admin.get('index.php?page=drivers')
            check('page=personnel' in url and 'Classification' in text and 'Juan Dela Cruz' in text,'Legacy driver page opens unified personnel list')
            driver_form={'action':'save_record','entity':'personnel','id':'0','full_name':'HTTP Driver','employee_number':'HTTP-D001','classification':'Driver','office_id':'1','position':'Driver','contact':'','status':'Available','return_to':'index.php?page=personnel&add=1'}
            text,_=admin.post('actions.php',driver_form)
            check('Drivers require a license' in text,'Driver classification requires license details')
            admin.get('index.php?page=personnel&add=1')
            text,_=admin.post('actions.php',{**driver_form,'license_number':'LICENSE-001','license_expiry':'2030-12-31'})
            check('Record saved.' in text and 'HTTP Driver' in text,'Create driver from unified personnel form')
            code,text,_=admin.get('index.php?page=personnel&classification=Utility')
            check('Test Personnel' in text and 'HTTP Driver' not in text,'Classification filter separates utility staff')
            admin.get('index.php?page=personnel')
            text,_=admin.post('actions.php',{'action':'save_record','entity':'drivers','id':'0','return_to':'index.php?page=personnel'})
            check('Invalid record type' in text,'Separate driver writes are disabled')
            requester.get('index.php?page=personnel-create')
            day=(datetime.now()+timedelta(days=20)).strftime('%Y-%m-%d')
            end_day=(datetime.now()+timedelta(days=21)).strftime('%Y-%m-%d')
            booking={'action':'personnel_save','id':'0','requested_role':'Technician','destination':'HTTP personnel site','purpose':'Inspect equipment','start_datetime':day+'T09:00','end_datetime':end_day+'T12:00','submit_mode':'submit'}
            text,_=requester.post('actions.php',booking)
            check('Personnel requisition saved.' in text and 'Pending Administrative Approval' in text,'Personnel request submits and redirects to protected detail')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                booking_id=db.execute('SELECT max(id) FROM personnel_bookings').fetchone()[0]
                personnel_id=db.execute('SELECT id FROM personnel WHERE employee_number=?',('HTTP-P001',)).fetchone()[0]
                additional_personnel_id=db.execute('SELECT id FROM personnel WHERE employee_number=?',('HTTP-D001',)).fetchone()[0]
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                personnel_reference=db.execute('SELECT reference FROM personnel_bookings WHERE id=?',(booking_id,)).fetchone()[0]
                vehicle_reference=db.execute("SELECT reference FROM requisitions WHERE status='Pending Administrative Approval' LIMIT 1").fetchone()[0]
                expected_pending=db.execute("SELECT count(*) FROM requisitions WHERE status IN ('Pending Supervisor','Pending Administrative Approval')").fetchone()[0]+1
            requester.get('index.php?page=personnel-create')
            requester.post('actions.php',{**booking,'submit_mode':'draft','destination':'Draft personnel only'})
            code,inbox,_=admin.get('index.php?page=approvals')
            check(code==200 and personnel_reference in inbox and vehicle_reference in inbox,'Approval inbox contains pending personnel and vehicle requisitions')
            personnel_row=re.search(r'<tr>(?:(?!</tr>).)*'+re.escape(personnel_reference)+r'.*?</tr>',inbox,re.S)[0]
            end_label=datetime.strptime(end_day,'%Y-%m-%d').strftime('%b ')+str(int(end_day[-2:]))+', '+end_day[:4]+' · 12:00 PM'
            check('<small>Start</small>' in personnel_row and '<small>End</small>' in personnel_row and end_label in personnel_row,'Approval shows separate complete start and end dates for overnight request')
            check('Draft personnel only' not in inbox,'Draft personnel requisitions stay out of approval inbox')
            check(re.search(r'Approvals</span><b class="nav-count">'+str(expected_pending)+r'</b>',inbox),'Approval badge counts both requisition types')
            review=re.search(r'href="([^"]*page=personnel-request[^"]*)">'+re.escape(personnel_reference)+r'</a>',inbox)
            check(review is not None,'Personnel approval row links to personnel review')
            code,detail,_=admin.get(html.unescape(review[1]))
            check(code==200 and 'Assign &amp; approve personnel' in detail,'Personnel inbox link opens assignment and approval form')
            check('name="personnel_ids[]" data-personnel-assignment' in detail and 'data-personnel-add' in detail and 'Hold Ctrl' not in detail,'Approval uses simple dropdown with Add personnel')
            code,filtered,_=admin.get('index.php?page=approvals&q='+urllib.parse.quote(personnel_reference))
            check(personnel_reference in filtered and vehicle_reference not in filtered,'Approval search finds personnel requisitions')
            code,filtered,_=admin.get('index.php?page=approvals&office=2')
            check(personnel_reference not in filtered,'Approval office filter applies to personnel requisitions')
            code,dashboard,_=admin.get('index.php?page=dashboard')
            check(re.search(r'Pending approvals</span>.*?class="stat-number">'+str(expected_pending)+r'<',dashboard,re.S),'Dashboard pending total includes personnel requisitions')
            path=f'index.php?page=personnel-request&id={booking_id}'
            admin.get(path)
            code,text,_=admin.get(f'api.php?action=personnel_availability&id={booking_id}&personnel_id={personnel_id}')
            check(code==200 and json.loads(text)['available'],'Personnel availability API returns available staff')
            code,text,_=admin.get(f'api.php?action=personnel_availability&id={booking_id}&personnel_ids[]={personnel_id}&personnel_ids[]={additional_personnel_id}')
            check(code==200 and json.loads(text)['available'],'Availability checks multiple personnel together')
            text,_=admin.post('actions.php',{'action':'personnel_approve','id':booking_id,'personnel_ids[]':[personnel_id,additional_personnel_id],'password':'Demo@12345'})
            check('Personnel requisition updated.' in text and 'Print requisition' in text and 'Test Personnel, HTTP Driver' in text,'Administrator approves and displays multiple personnel')
            print_url=re.search(r'href="(print/personnel.php[^"]+)"',text)[1]
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                schedule=db.execute('SELECT start_datetime,end_datetime FROM personnel_bookings WHERE id=?',(booking_id,)).fetchone()
                check(schedule==(day+' 09:00:00',end_day+' 12:00:00'),'Approval preserves the requested start and end timestamps')
            code,inbox,_=admin.get('index.php?page=approvals')
            check(personnel_reference not in inbox and vehicle_reference in inbox,'Approved personnel requisition leaves inbox while pending vehicle remains')
            code,slip,_=requester.get(f'print/personnel.php?id={booking_id}')
            check(code==200 and 'Test Personnel' in slip and 'HTTP Driver' in slip and 'PERSONNEL REQUISITION' in slip,'Requester prints approved personnel requisition')
            check(admin.get(print_url)[0]==200,'Encrypted personnel print link works')
            check('data-slip-qr="'+personnel_reference+'"' in slip and 'vendor/qrcodegen.js' in slip and 'data-print-slip disabled' in slip,'Personnel print prepares reference QR before enabling print')
            code,tracked,tracked_url=requester.get('index.php?page=requisitions&q='+personnel_reference)
            check('page=requisitions' in tracked_url and personnel_reference in tracked and 'tracking-card' in tracked and 'personnel-progress' in tracked,'Header QR search tracks personnel requisition progress')
            check('Test Personnel, HTTP Driver' in tracked and 'Approved and awaiting the start of the assignment.' in tracked,'Personnel tracking shows all assigned staff and correct status')
            tracking_link=re.search(r'class="tracking-reference" href="([^"]+)"',tracked)[1]
            check('page=personnel-request' in tracking_link and requester.get(html.unescape(tracking_link))[0]==200,'Personnel tracking opens protected personnel detail')
            code,tracked,_=requester.get('index.php?page=requisitions&q=HTTP+Driver')
            check(personnel_reference in tracked,'Header search finds secondary assigned personnel')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                db.execute("INSERT INTO personnel_bookings(reference,requester_id,office_id,personnel_id,requested_role,destination,purpose,start_datetime,end_datetime,status,created_at) SELECT 'PRIVATE-PERSONNEL-TRACK',1,2,personnel_id,requested_role,destination,purpose,start_datetime,end_datetime,'Approved',created_at FROM personnel_bookings WHERE id=?",(booking_id,))
            code,tracked,_=requester.get('index.php?page=requisitions&q=Test+Personnel')
            check(personnel_reference in tracked and 'PRIVATE-PERSONNEL-TRACK' not in tracked,'Personnel name search hides another requester records')
            code,tracked,_=admin.get('index.php?page=requisitions&q=Test+Personnel&office=2')
            check('PRIVATE-PERSONNEL-TRACK' in tracked and personnel_reference not in tracked,'Tracking office filter includes authorized personnel records')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                db.execute("UPDATE personnel_bookings SET status='Cancelled' WHERE reference='PRIVATE-PERSONNEL-TRACK'")
            requester.get('index.php?page=personnel-create');requester.post('actions.php',booking)
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                second_id=db.execute('SELECT max(id) FROM personnel_bookings').fetchone()[0]
            code,text,_=admin.get(f'index.php?page=personnel-request&id={second_id}')
            check(re.search(r'<option value="'+str(personnel_id)+r'" disabled',text),'Conflicting personnel is disabled in assignment dropdown')
            code,text,_=admin.get(f'api.php?action=personnel_availability&id={second_id}&personnel_id={personnel_id}')
            check(not json.loads(text)['available'],'Personnel availability API detects booking conflict')
            code,text,_=admin.get(f'api.php?action=personnel_availability&id={second_id}&personnel_ids[]={additional_personnel_id}')
            check(not json.loads(text)['available'],'Secondary assignee is unavailable for another requisition')
            text,_=admin.post('actions.php',{'action':'personnel_approve','id':second_id,'personnel_id':personnel_id,'password':'Demo@12345','return_to':f'index.php?page=personnel-request&id={second_id}'})
            check('Already assigned' in text,'Forged conflicting personnel approval is rejected')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                check(db.execute('SELECT status FROM personnel_bookings WHERE id=?',(second_id,)).fetchone()[0]=='Pending Administrative Approval','Failed personnel approval leaves booking pending')
            requester.get(path);requester.post('actions.php',{'action':'personnel_cancel','id':booking_id})
            code,text,_=admin.get(f'api.php?action=personnel_availability&id={second_id}&personnel_id={personnel_id}')
            check(json.loads(text)['available'],'Cancelling personnel requisition releases availability through API')
            progress_path=f'index.php?page=personnel-request&id={second_id}'
            admin.get(progress_path)
            admin.post('actions.php',{'action':'personnel_approve','id':second_id,'personnel_id':personnel_id,'password':'Demo@12345'})
            code,text,_=admin.get(progress_path)
            check('You can start early.' in text and re.search(r'<button(?![^>]*disabled)[^>]*>Start assignment</button>',text),'Future approved assignment permits early start')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                db.execute("UPDATE personnel SET status='On Leave' WHERE id=?",(personnel_id,))
            text,_=admin.post('actions.php',{'action':'personnel_start','id':second_id,'remarks':'Keep this progress note','return_to':progress_path})
            check('Personnel is on leave.' in text and '>Keep this progress note</textarea>' in text,'Failed start retains progress remarks')
            text,_=admin.post('actions.php',{'action':'personnel_complete','id':second_id,'remarks':'Not yet started','return_to':progress_path})
            check('Only assignments in progress can be completed.' in text,'Approved request cannot skip the start step')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                db.execute("UPDATE personnel SET status='Available' WHERE id=?",(personnel_id,))
            admin.get(progress_path)
            text,_=admin.post('actions.php',{'action':'personnel_start','id':second_id,'remarks':'Work started','return_to':progress_path})
            check('Complete assignment' in text and 'Work started' in text,'Start succeeds and records remarks in history')
            code,progress_tracking,_=admin.get('index.php?page=requisitions&q=Test+Personnel&status=In+Progress')
            check('Personnel assignment is in progress.' in progress_tracking and 'Actual start' in progress_tracking,'Tracking status filter shows personnel assignment in progress')
            progress_form=re.search(r'<form[^>]*data-confirm="Mark this assignment complete.*?</form>',text,re.S)[0]
            progress_data=dict(re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',progress_form))
            progress_data['remarks']='All assigned work completed'
            text,_=admin.post('actions.php',progress_data)
            check('This assignment is complete.' in text and 'All assigned work completed' in text,'Rendered Complete form saves completion and remarks')
            code,tracked,_=requester.get('index.php?page=requisitions&q=Test+Personnel&status=Completed')
            check('This personnel assignment is complete.' in tracked and 'Actual completion' in tracked,'Completed personnel tracking shows actual completion time')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                status,started,ended=db.execute('SELECT status,actual_start,actual_end FROM personnel_bookings WHERE id=?',(second_id,)).fetchone()
                check(status=='Completed' and started and ended,'Complete action persists status and actual timestamps')
                check(db.execute("SELECT count(*) FROM personnel_booking_history WHERE booking_id=? AND decision='Completed'",(second_id,)).fetchone()[0]==1,'Completion recorded once in history')

            admin.get('index.php?page=notifications')
            code,text,_=requester.get('index.php?page=notifications')
            check('index.php?page=personnel-bookings' in text,'Personnel notifications link to booking list')
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
            places=['First office, Baler','Second office, San Luis','Final stop '+('x'*230)]
            requester.get('index.php?page=create')
            multi={**data,'destinations[]':places,'purpose':'Multiple places regression','submit_mode':'draft'}
            text,_=requester.post('actions.php',multi)
            check(all(place in text for place in places),'Vehicle requisition displays all places')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                multi_id,saved=db.execute("SELECT id,destination FROM requisitions WHERE purpose='Multiple places regression'").fetchone()
                check(saved=='\n'.join(places) and len(saved)>255,'Multiple places persist in order beyond the old length limit')
            code,edit,_=requester.get(f'index.php?page=create&id={multi_id}')
            check(all('value="'+place+'"' in edit for place in places) and 'data-destination-add' in edit,'Editing restores separate destination fields')
            text,_=requester.post('actions.php',{**multi,'id':multi_id,'destinations[]':['First office, Baler',''],'return_to':f'index.php?page=create&id={multi_id}'})
            check('Enter a valid place' in text and 'value="First office, Baler"' in text,'Blank additional place rejected while preserving form input')
            requester.get(f'index.php?page=create&id={multi_id}')
            requester.post('actions.php',{**multi,'id':multi_id,'destinations[]':places[:2]})
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                check(db.execute('SELECT destination FROM requisitions WHERE id=?',(multi_id,)).fetchone()[0]=='\n'.join(places[:2]),'Removing a place updates saved destinations')
            requester.get('index.php?page=personnel-create')
            text,_=requester.post('actions.php',{**booking,'destinations[]':places,'purpose':'Personnel multiple places','submit_mode':'draft'})
            check(all(place in text for place in places),'Personnel requisition supports multiple places')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                personnel_multi_id=db.execute("SELECT id FROM personnel_bookings WHERE purpose='Personnel multiple places'").fetchone()[0]
            code,edit,_=requester.get(f'index.php?page=personnel-create&id={personnel_multi_id}')
            check(all('value="'+place+'"' in edit for place in places),'Personnel editing restores multiple places')
            requester.get('index.php?page=create')
            for attempt in range(2):
                text,url=requester.post('actions.php',{**data,'id':'','return_to':'index.php?page=create','end_datetime':day+'T07:00'})
                check('Estimated return must be after departure.' in text and 'New requisition' in text and 'HTTP Integration Test' in text,'Failed new request keeps input for correction')
                check('value="index.php?page=create"' in text and 'page=create&amp;id=0' not in text,'Retry form does not gain a nonexistent record ID')
            def save_json(payload):
                req=urllib.request.Request(base+'/actions.php',urllib.parse.urlencode({'csrf':requester.token,**payload}).encode(),headers={'Accept':'application/json'})
                try:
                    response=requester.http.open(req)
                except urllib.error.HTTPError as error:
                    response=error
                with response:
                    check(response.geturl().endswith('actions.php'),'JSON save does not redirect away from entered form')
                    return response.status,json.loads(response.read())
            code,result=save_json({**data,'destination':''})
            check(code==422 and 'destination' in result['error'],'JSON validation returns a field error')
            code,result=save_json({**data,'csrf':'invalid'})
            check(code==422 and 'session token expired' in result['error'],'JSON saves retain CSRF protection')
            code,result=save_json({**data,'destination':'JSON manual destination','vehicle_type':'Motorcycle','purpose':'JSON save regression','submit_mode':'draft'})
            check(code==200 and 'id=v1_' in result['redirect'],'JSON save returns protected record link')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                check(db.execute("SELECT status FROM requisitions WHERE purpose='JSON save regression'").fetchone()[0]=='Draft','JSON save preserves Save as draft choice')
                check(db.execute("SELECT vehicle_type FROM requisitions WHERE purpose='JSON save regression'").fetchone()[0]=='Motorcycle','Motorcycle requisition saves successfully')
            _,fresh_form,_=requester.get('index.php?page=create')
            check('JSON manual destination' not in fresh_form,'Successful save clears stale recovery input')
            text,url=requester.post('actions.php',data);check('id=v1_' in url and 'HTTP Integration Test' in text,'Create requisition with encrypted redirect')
            code,tracked,_=requester.get('index.php?page=requisitions&q=HTTP+Integration+Test')
            check(code==200 and 'tracking-card' in tracked and 'Awaiting administrator review.' in tracked and 'HTTP Integration Test' in tracked,'Tracking search renders matching request cards and progress')
            code,tracked,_=requester.get('index.php?page=requisitions&q=does-not-exist-12345')
            check(code==200 and 'No matching slips' in tracked,'Tracking search has a useful empty state')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                rid=str(db.execute("SELECT id FROM requisitions WHERE destination='HTTP Integration Test' ORDER BY id DESC").fetchone()[0])
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
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                assistant_id=str(db.execute("SELECT id FROM requisitions WHERE destination='Assistant review booking' ORDER BY id DESC").fetchone()[0])
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                stored=db.execute('SELECT status,vehicle_id,requester_id FROM requisitions WHERE id=?',(assistant_id,)).fetchone()
                owner=db.execute("SELECT id FROM users WHERE email='requester@vrs.local'").fetchone()[0]
                check(stored==('Pending Administrative Approval',None,owner),'Assistant review submits only for the signed-in requester and cannot self-approve or assign')
            # A second request with an overlapping vehicle must fail final approval.
            _,url=requester.post('actions.php',{**data,'destination':'HTTP conflict test'})
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                conflict_id=str(db.execute("SELECT id FROM requisitions WHERE destination='HTTP conflict test' ORDER BY id DESC").fetchone()[0])
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
            # Delete controls and endpoint permissions use a disposable fixture for each record type.
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                office_delete=db.execute("INSERT INTO offices(code,name,status) VALUES('DELETE-HTTP','Delete HTTP office','Active')").lastrowid
                user_delete=db.execute("INSERT INTO users(full_name,email,password_hash,role,office_id,status) SELECT 'Delete HTTP user','delete-http@example.test',password_hash,'Requester',1,'Pending' FROM users WHERE id=1").lastrowid
                note_delete=db.execute("INSERT INTO notifications(user_id,message,created_at) VALUES(1,'Delete HTTP note',datetime('now'))").lastrowid
            code,office_page,_=admin.get('index.php?page=offices')
            delete_url=html.unescape(re.search(r'href="([^"]*page=delete&amp;entity=offices[^"]+)"',office_page)[1])
            check('id=v1_' in delete_url,'Delete links use protected record IDs')
            code,confirmation,_=admin.get(delete_url)
            check(code==200 and 'I confirm deletion' in confirmation and 'Delete HTTP office' in confirmation,'Delete opens a named confirmation page')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                check(db.execute('SELECT count(*) FROM offices WHERE id=?',(office_delete,)).fetchone()[0]==1,'GET confirmation does not delete records')
            text,_=admin.post('actions.php',{'action':'delete_record','entity':'offices','id':office_delete,'return_to':'index.php?page=offices'})
            check('confirm delete' in text,'Deletion requires explicit confirmation')
            text,_=admin.post('actions.php',{'action':'delete_record','entity':'offices','id':office_delete,'confirm_delete':'1','csrf':'bad','return_to':'index.php?page=offices'})
            check('session token expired' in text,'Deletion enforces CSRF')
            check(requester.get(f'index.php?page=delete&entity=offices&id={office_delete}')[0]==403,'Unauthorized delete confirmation denied')
            text,_=requester.post('actions.php',{'action':'delete_record','entity':'offices','id':office_delete,'confirm_delete':'1'})
            check('permission' in text,'Forged record deletion denied')
            admin.get('index.php?page=offices')
            text,url=admin.post('actions.php',{'action':'delete_record','entity':'offices','id':office_delete,'confirm_delete':'1'})
            check('Record deleted.' in text and 'page=offices' in url,'Confirmed delete returns to the record list')
            with sqlite3.connect(app/'storage/demo.sqlite') as db:
                check(db.execute('SELECT count(*) FROM offices WHERE id=?',(office_delete,)).fetchone()[0]==0,'Confirmed office deletion persisted')
            code,confirmation,_=admin.get('index.php?page=delete&entity=vehicles&id=1')
            check('linked to other records' in confirmation and 'name="confirm_delete"' not in confirmation,'Linked vehicle has explanation and no destructive submit')
            admin.get(f'index.php?page=delete&entity=users&id={user_delete}')
            text,_=admin.post('actions.php',{'action':'delete_record','entity':'users','id':user_delete,'confirm_delete':'1','confirmation_password':'wrong','return_to':f'index.php?page=delete&entity=users&id={user_delete}'})
            check('Confirm your administrator password' in text,'User deletion requires administrator password')
            text,_=admin.post('actions.php',{'action':'delete_record','entity':'users','id':user_delete,'confirm_delete':'1','confirmation_password':'Demo@12345'})
            check('Record deleted.' in text,'Unused user deleted after password confirmation')
            check(requester.get(f'index.php?page=delete&entity=notifications&id={note_delete}')[0]==403,'Notification delete cannot access another user message')
            admin.get('index.php?page=notifications')
            text,_=admin.post('actions.php',{'action':'delete_record','entity':'notifications','id':note_delete,'confirm_delete':'1'})
            check('Record deleted.' in text and 'Delete HTTP note' not in text,'Notification delete removes only the selected message')
            for entity,record_id in [('requisitions',multi_id),('personnel_bookings',personnel_multi_id)]:
                code,confirmation,_=requester.get(f'index.php?page=delete&entity={entity}&id={record_id}')
                check(code==200 and 'name="confirm_delete"' in confirmation,'Requester can confirm own draft deletion: '+entity)
                text,_=requester.post('actions.php',{'action':'delete_record','entity':entity,'id':record_id,'confirm_delete':'1'})
                check('Record deleted.' in text,'Requester deletes own draft: '+entity)
            anonymous=Client(base)
            check(anonymous.get('api.php?action=calendar')[0]==401,'Calendar API requires sign-in')
            original_config=(app/'config/system.php').read_text()
            try:
                (app/'config/system.php').write_text("<?php throw new RuntimeException('PRIVATE_CONFIGURATION_DETAIL');")
                code,body,_=admin.get('api.php?action=updates')
                result=json.loads(body)
                check(code==500 and 'Reference:' in result['error'] and 'PRIVATE_CONFIGURATION_DETAIL' not in body,'API initialization failure returns safe JSON with a log reference')
            finally:
                (app/'config/system.php').write_text(original_config)
            print('All HTTP integration checks passed; disposable database removed.')
        finally:
            proc.terminate();proc.wait(timeout=10)
            log.seek(0);output=log.read()
            if 'Fatal error' in output or 'Warning:' in output:print(output);raise AssertionError('PHP server warnings found')
