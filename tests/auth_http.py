"""Registration and MFA checks in a disposable app/database/session directory."""
import http.cookiejar, urllib.request, urllib.parse, urllib.error, re, sys, tempfile, shutil, subprocess, time, socket, sqlite3, json
from pathlib import Path
from auth_support import totp
PHP=sys.argv[1] if len(sys.argv)>1 else 'php'
ROOT=Path(__file__).resolve().parents[1]
def check(ok,label):
    assert ok,label
    print('PASS:',label,flush=True)
class Client:
    def __init__(self,base):
        self.base=base;self.cookies=http.cookiejar.CookieJar();self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies));self.csrf=''
    def request(self,path,data=None):
        payload=None if data is None else urllib.parse.urlencode({'csrf':self.csrf,**data}).encode()
        try:r=self.http.open(self.base+path,payload,timeout=15)
        except urllib.error.HTTPError as e:r=e
        with r:
            text=r.read().decode();url=r.url;code=r.code
        assert '<b>Warning</b>' not in text and '<b>Fatal error</b>' not in text,text
        match=re.search(r'name="csrf" value="([^"]+)"',text)
        if match:self.csrf=match[1]
        return code,text,url
    def password(self,email,password):
        self.request('login.php');return self.request('login.php',{'email':email,'password':password})
    def enroll(self,text):
        secret=re.search(r'<code data-setup-secret>([^<]+)</code>',text)[1]
        code=totp(secret);_,text,_=self.request('two-factor.php',{'action':'enroll','code':code})
        codes=re.findall(r'<code data-recovery-code>([^<]+)</code>',text)
        check(len(codes)==10,'Enrollment issues ten recovery codes')
        return secret,code,codes
with tempfile.TemporaryDirectory(prefix='vrs-auth-') as folder:
    folder=Path(folder);app=folder/'app';sessions=folder/'sessions';sessions.mkdir()
    shutil.copytree(ROOT,app,ignore=shutil.ignore_patterns('.git','*.sqlite','*.sqlite-journal','local.php','ollama.local.php','auth.key','__pycache__'))
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    with open(folder/'server.log','w+') as log:
        proc=subprocess.Popen([PHP,'-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','router.php'],cwd=app,stdout=log,stderr=log)
        try:
            base=f'http://127.0.0.1:{port}/';visitor=Client(base)
            for _ in range(50):
                try:visitor.request('register.php');break
                except OSError:time.sleep(.1)
            db=sqlite3.connect(app/'storage/demo.sqlite')
            optional=Client(base);_,_,url=optional.password('daniel@vrs.local','Demo@12345')
            check(url.endswith('index.php'),'New account can sign in without mandatory authenticator enrollment')
            _,profile,_=optional.request('index.php?page=profile')
            check('Two-factor authentication is optional' in profile and 'Turn on authenticator' in profile,'Profile offers optional authenticator controls')
            optional.request('actions.php',{'action':'logout'})
            # Preserve coverage of explicitly required legacy enrollment policies.
            db.execute('INSERT INTO auth_preferences(user_id,authenticator_disabled) VALUES(1,0)');db.commit()

            account={'full_name':'Public Requester','email':'new@example.test','office_id':'1','position':'Field Officer','password':'A unique public passphrase!','confirm_password':'A unique public passphrase!','role':'Administrator','status':'Active'}
            _,text,_=visitor.request('register.php',{**account,'csrf':'wrong'})
            check('session token expired' in text,'Registration enforces CSRF')
            _,text,_=visitor.request('register.php',{**account,'password':'short','confirm_password':'short'})
            check('at least 12' in text,'Registration rejects short passwords')
            _,text,_=visitor.request('register.php',account)
            check('Request received' in text,'Public registration succeeds')
            uid,role,status,hashed=db.execute('SELECT id,role,status,password_hash FROM users WHERE email=?',(account['email'],)).fetchone()
            db.execute('INSERT INTO auth_preferences(user_id,authenticator_disabled) VALUES(?,0)',(uid,));db.commit()
            check(role=='Requester' and status=='Pending' and hashed!=account['password'],'Forged role/status ignored and password hashed')
            check(visitor.request('api.php')[0]==401,'Registration does not authenticate')
            _,duplicate,_=visitor.request('register.php',account)
            check('Request received' in duplicate and db.execute('SELECT COUNT(*) FROM users WHERE email=?',(account['email'],)).fetchone()[0]==1,'Duplicate registration has generic response without altering account')
            _,text,url=visitor.password(account['email'],account['password'])
            check(url.endswith('login.php') and 'not active' in text,'Pending accounts cannot sign in')
            admin=Client(base);_,text,url=admin.password('daniel@vrs.local','Demo@12345')
            check('two-factor.php' in url and admin.request('api.php')[0]==401,'Correct password alone cannot access API')
            check(admin.request('index.php')[2].endswith('login.php'),'Password-only sessions cannot open dashboard')
            _,text,_=admin.request('two-factor.php');secret=re.search(r'<code data-setup-secret>([^<]+)',text)[1]
            _,bad,_=admin.request('two-factor.php',{'action':'enroll','code':'invalid'})
            check('invalid or expired' in bad and not db.execute('SELECT 1 FROM auth_factors WHERE user_id=1').fetchone(),'Enrollment requires a valid code')
            db.execute("CREATE TRIGGER fail_enrollment BEFORE INSERT ON audit_logs WHEN NEW.action='Authenticator enrolled' BEGIN SELECT RAISE(ABORT,'Test enrollment failure'); END");db.commit()
            _,text,_=admin.request('two-factor.php',{'action':'enroll','code':totp(secret)})
            check('database error' in text and not db.execute('SELECT 1 FROM auth_factors WHERE user_id=1').fetchone(),'Failed enrollment rolls back the factor')
            check(secret in text and 'data-recovery-code' not in text,'Failed enrollment preserves setup key without publishing recovery codes')
            db.execute('DROP TRIGGER fail_enrollment');db.commit()
            _,text,_=admin.request('two-factor.php');secret,used_code,codes=admin.enroll(text)
            check(admin.request('api.php')[0]==401,'Enrollment requires recovery-code acknowledgement before access')
            _,text,url=admin.request('two-factor.php',{'action':'finish'})
            check(url.endswith('index.php'),'MFA enrollment completes sign-in')
            cipher,hashes=db.execute('SELECT secret_cipher,recovery_hashes FROM auth_factors WHERE user_id=1').fetchone()
            check(secret not in cipher and all(code not in hashes for code in codes),'Authenticator secret encrypted and recovery codes hashed')
            check((app/'storage/auth.key').stat().st_mode&0o777==0o600 and visitor.request('storage/auth.key')[0]==404,'Encryption key is private and not web-accessible')
            _,queue,_=admin.request('index.php?page=account-requests');check('Public Requester' in queue and 'Approve Requester account' in queue,'Administrator sees account review queue')
            check('Public Requester' not in admin.request('index.php?page=users')[1],'Pending registration excluded from main accounts')
            check(visitor.request('api.php')[0]==401 and visitor.request('index.php')[2].endswith('login.php'),'Pending registration cannot enter workspace or API')
            _,text,_=admin.request('actions.php',{'action':'review_registration','id':uid,'decision':'approve','confirmation_password':'Demo@12345','csrf':'invalid','return_to':'index.php?page=account-requests'})
            check('session token expired' in text and db.execute('SELECT status FROM users WHERE id=?',(uid,)).fetchone()[0]=='Pending','Account review requires CSRF')
            _,text,_=admin.request('actions.php',{'action':'save_record','entity':'users','id':uid,'full_name':account['full_name'],'email':account['email'],'office_id':1,'position':account['position'],'role':'Administrator','status':'Active','confirmation_password':'Demo@12345','return_to':'index.php?page=account-requests'})
            check('Review registrations on the Account requests page' in text and db.execute('SELECT status,role FROM users WHERE id=?',(uid,)).fetchone()==('Pending','Requester'),'Generic account editing cannot bypass registration review')
            _,text,_=admin.request('actions.php',{'action':'review_registration','id':uid,'decision':'approve','confirmation_password':'wrong'})
            check('Confirm your administrator password' in text and db.execute('SELECT status FROM users WHERE id=?',(uid,)).fetchone()[0]=='Pending','Account approval requires administrator password')
            admin.request('actions.php',{'action':'review_registration','id':uid,'decision':'approve','confirmation_password':'Demo@12345'})
            check(db.execute('SELECT status,role FROM users WHERE id=?',(uid,)).fetchone()==('Active','Requester'),'Administrator approval grants only Requester access')
            check('Public Requester' in admin.request('index.php?page=users')[1] and 'Public Requester' not in admin.request('index.php?page=account-requests')[1],'Approval moves request into main accounts')
            db.execute("INSERT INTO users(full_name,email,password_hash,role,office_id,position,status) VALUES(?,?,?,'Requester',1,'Test','Pending')",('Rejected Applicant','rejected@example.test',hashed));db.commit()
            rejected_id=db.execute("SELECT id FROM users WHERE email='rejected@example.test'").fetchone()[0]
            _,_,url=admin.request('actions.php',{'action':'review_registration','id':rejected_id,'decision':'reject','confirmation_password':'Demo@12345'})
            check('page=account-requests' in url and db.execute('SELECT status FROM users WHERE id=?',(rejected_id,)).fetchone()[0]=='Rejected','Rejection returns to separate request queue')
            check('Rejected Applicant' not in admin.request('index.php?page=users')[1] and 'Rejected Applicant' in admin.request('index.php?page=account-requests&status=Rejected')[1],'Rejected requests remain separate from main accounts')
            rejected=Client(base);_,text,url=rejected.password('rejected@example.test',account['password'])
            check(url.endswith('login.php') and rejected.request('api.php')[0]==401,'Rejected account cannot sign in or access API')
            _,text,url=visitor.password(account['email'],account['password']);check('two-factor.php' in url,'Approved registrant must enroll MFA')
            _,_,user_codes=visitor.enroll(text);visitor.request('two-factor.php',{'action':'finish'})
            check(visitor.request('index.php?page=users')[0]==403,'Registered requester cannot manage users')
            check(visitor.request('index.php?page=account-requests')[0]==403,'Requester cannot open account request administration')
            visitor.request('actions.php',{'action':'review_registration','id':rejected_id,'decision':'approve','confirmation_password':account['password']})
            check(db.execute('SELECT status FROM users WHERE id=?',(rejected_id,)).fetchone()[0]=='Rejected','Requester cannot approve an account through a forged POST')
            replay=Client(base);replay.password('daniel@vrs.local','Demo@12345')
            _,text,url=replay.request('two-factor.php',{'action':'finish'})
            check(not url.endswith('index.php') and replay.request('api.php')[0]==401,'Forged finish cannot bypass an existing factor')
            _,text,url=replay.request('two-factor.php',{'action':'verify','code':used_code})
            check('already used' in text and not url.endswith('index.php'),'Authenticator code replay rejected')
            before_hashes=db.execute('SELECT recovery_hashes FROM auth_factors WHERE user_id=1').fetchone()[0]
            db.execute("CREATE TRIGGER fail_mfa_login BEFORE INSERT ON audit_logs WHEN NEW.action='Signed in' BEGIN SELECT RAISE(ABORT,'Test MFA completion failure'); END");db.commit()
            _,text,_=replay.request('two-factor.php',{'action':'verify','code':codes[0]})
            check('database error' in text and replay.request('api.php')[0]==401,'Failed MFA completion does not sign in')
            check(db.execute('SELECT recovery_hashes FROM auth_factors WHERE user_id=1').fetchone()[0]==before_hashes,'Failed MFA completion does not consume recovery code')
            db.execute('DROP TRIGGER fail_mfa_login');db.commit()
            _,_,url=replay.request('two-factor.php',{'action':'verify','code':codes[0]});check(url.endswith('index.php'),'Unused recovery code completes sign-in')
            again=Client(base);again.password('daniel@vrs.local','Demo@12345')
            _,text,url=again.request('two-factor.php',{'action':'verify','code':codes[0]});check('already used' in text and not url.endswith('index.php'),'Used recovery code cannot be replayed in another session')
            again.request('two-factor.php',{'action':'verify','code':codes[1]})
            sid=next(cookie.value for cookie in again.cookies if cookie.name=='PHPSESSID');session=sessions/('sess_'+sid)
            session.write_text(re.sub(r'auth_seen\|i:\d+;',f'auth_seen|i:{int(time.time())-1801};',session.read_text()))
            check(again.request('api.php')[0]==401,'Idle expiry enforced on protected API requests')
            expired=Client(base);expired.password(account['email'],account['password']);sid=next(cookie.value for cookie in expired.cookies if cookie.name=='PHPSESSID');session=sessions/('sess_'+sid)
            session.write_text(re.sub(r's:7:"expires";i:\d+;',f's:7:"expires";i:{int(time.time())-1};',session.read_text()))
            check(expired.request('two-factor.php',{'action':'verify','code':user_codes[0]})[2].endswith('login.php'),'Expired MFA challenge cannot be completed')
            locked=Client(base);locked.password(account['email'],account['password'])
            for _ in range(6):_,text,_=locked.request('two-factor.php',{'action':'verify','code':'invalid'})
            check('Too many attempts' in text,'MFA guessing is rate-limited')
            locked.password(account['email'],account['password']);_,text,_=locked.request('two-factor.php',{'action':'verify','code':user_codes[1]})
            check('Too many attempts' in text and locked.request('api.php')[0]==401,'Restarting sign-in does not reset MFA limits')
            visitor.request('register.php',{'csrf':'invalid'}) # Authenticated visitors are redirected safely.
            spam=Client(base);spam.request('register.php')
            for i in range(5):_,text,_=spam.request('register.php',{**account,'email':f'new-{i}@example.test'})
            check('Too many attempts' in text,'Public registration is rate-limited')
            check(db.execute("SELECT COUNT(*) FROM audit_logs WHERE action='Registration approved'").fetchone()[0]==1,'Registration approval is audited')
            _,profile,_=admin.request('index.php?page=profile')
            check('Turn off authenticator' in profile and '<dd>On</dd>' in profile,'Profile shows enrolled authenticator status')
            admin.request('actions.php',{'action':'factor_disable','password':'Demo@12345','csrf':'invalid'})
            check(db.execute('SELECT 1 FROM auth_factors WHERE user_id=1').fetchone(),'Disable requires CSRF token')
            _,text,_=admin.request('actions.php',{'action':'factor_disable','password':'wrong'})
            check('password is incorrect' in text and db.execute('SELECT 1 FROM auth_factors WHERE user_id=1').fetchone(),'Disable requires correct password')
            _,text,_=admin.request('actions.php',{'action':'factor_disable','password':'Demo@12345','user_id':uid})
            check('Turn on authenticator' in text and '<dd>Off</dd>' in text,'Profile can turn authenticator off')
            check(not db.execute('SELECT 1 FROM auth_factors WHERE user_id=1').fetchone() and db.execute('SELECT 1 FROM auth_factors WHERE user_id=?',(uid,)).fetchone(),'Disable only affects signed-in account and removes factor')
            db.execute("CREATE TRIGGER fail_sign_in_audit BEFORE INSERT ON audit_logs WHEN NEW.action='Signed in' BEGIN SELECT RAISE(ABORT,'Test audit failure'); END")
            db.commit()
            failed_login=Client(base)
            _,text,url=failed_login.password('daniel@vrs.local','Demo@12345')
            check('database error' in text and 'SQLSTATE' in text,'Failed sign-in provides a database diagnostic')
            check('login.php' in failed_login.request('index.php')[2] and failed_login.request('api.php')[0]==401,'Failed sign-in never leaves an authenticated session')
            db.execute('DROP TRIGGER fail_sign_in_audit');db.commit()
            _,_,url=failed_login.password('daniel@vrs.local','Demo@12345')
            check(url.endswith('index.php'),'Sign-in recovers once the database failure is resolved')
            password_only=Client(base);_,_,url=password_only.password('daniel@vrs.local','Demo@12345')
            check(url.endswith('index.php') and password_only.request('api.php')[0]==200,'Disabled authenticator permits password sign-in and protected session')
            _,text,_=admin.request('actions.php',{'action':'factor_start','password':'Demo@12345'})
            check('data-auth-qr' in text and 'data-setup-secret' in text,'Profile setup offers QR and manual key')
            admin.request('actions.php',{'action':'factor_cancel'})
            check('data-setup-secret' not in admin.request('index.php?page=profile')[1],'Setup can be cancelled')
            _,text,_=admin.request('actions.php',{'action':'factor_start','password':'Demo@12345'})
            new_secret=re.search(r'<code data-setup-secret>([^<]+)</code>',text)[1]
            _,text,_=admin.request('actions.php',{'action':'factor_enable','code':'invalid'})
            check('invalid or expired' in text and not db.execute('SELECT 1 FROM auth_factors WHERE user_id=1').fetchone(),'Profile enable requires valid authenticator code')
            _,text,_=admin.request('actions.php',{'action':'factor_enable','code':totp(new_secret)})
            new_codes=re.findall(r'<code data-recovery-code>([^<]+)</code>',text)
            check(len(new_codes)==10 and db.execute('SELECT 1 FROM auth_factors WHERE user_id=1').fetchone(),'Profile enables authenticator and issues fresh recovery codes')
            check(password_only.request('api.php')[0]==401,'Enabling authenticator invalidates other password-only sessions')
            admin.request('actions.php',{'action':'factor_done'})
            check('data-recovery-code' not in admin.request('index.php?page=profile')[1],'Acknowledged recovery codes are removed from profile')
            enabled=Client(base);_,_,url=enabled.password('daniel@vrs.local','Demo@12345')
            check('two-factor.php' in url and enabled.request('api.php')[0]==401,'Re-enabled authenticator requires verification at sign-in')
            _,text,url=enabled.request('two-factor.php',{'action':'verify','code':codes[2]})
            check('already used' in text and 'two-factor.php' in url,'Old recovery codes are invalid after re-enrollment')
            _,_,url=enabled.request('two-factor.php',{'action':'verify','code':new_codes[0]})
            check(url.endswith('index.php'),'New recovery code verifies re-enabled authenticator')
            db.close();print('All registration and MFA HTTP checks passed.',flush=True)
        finally:
            proc.terminate();proc.wait(timeout=10);log.seek(0);errors=log.read()
            assert 'Fatal error' not in errors and 'Warning:' not in errors,errors
