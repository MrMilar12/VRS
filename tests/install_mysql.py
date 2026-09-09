"""Verify the browser installer using a disposable local MySQL database (root, no password).
Run: python3 tests/install_mysql.py /path/to/php
"""
import os, pwd, http.cookiejar, urllib.request, urllib.parse, re, sys, tempfile, shutil, subprocess, time, socket, secrets, json
from pathlib import Path
PHP=sys.argv[1] if len(sys.argv)>1 else 'php'
ROOT=Path(__file__).resolve().parents[1]
APACHE='--apache' in sys.argv
name='vrs_install_test_'+secrets.token_hex(6)
def check(value,label):
    assert value,label
    print('PASS:',label)
with tempfile.TemporaryDirectory(prefix='vrs-apache-install-' if APACHE else 'vrs-install-',dir=ROOT if APACHE else None) as folder:
    app=Path(folder)/'app'
    shutil.copytree(ROOT,app,ignore=shutil.ignore_patterns('.git','*.sqlite','*.sqlite-journal','local.php','__pycache__','vrs-apache-install-*'))
    sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
    base=f'http://localhost/VRS/{Path(folder).name}/app/' if APACHE else f'http://127.0.0.1:{port}/'
    if APACHE:
        Path(folder).chmod(0o755)
        for relative in ['config','storage','assets/uploads']:
            for account in ['daemon',pwd.getpwuid(os.getuid()).pw_name]:
                subprocess.run(['chmod','+a',f'user:{account} allow read,write,append,execute,delete,readattr,writeattr,readextattr,writeextattr,readsecurity,file_inherit,directory_inherit',str(app/relative)],check=True)
    jar=http.cookiejar.CookieJar()
    client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    token=''
    def request(path,data=None):
        global token
        payload=None if data is None else urllib.parse.urlencode({'csrf':token,**data}).encode()
        with client.open(base+path,payload,timeout=15) as response:
            text=response.read().decode()
            assert '<b>Warning</b>' not in text and '<b>Fatal error</b>' not in text,text
            match=re.search(r'name="csrf" value="([^"]+)"',text)
            if match:token=match[1]
            return text,response.url
    with open(Path(folder)/'server.log','w+') as log:
        proc=None if APACHE else subprocess.Popen([PHP,'-S',f'127.0.0.1:{port}','router.php'],cwd=app,stdout=log,stderr=log)
        try:
            for _ in range(40):
                try:request('login.php');break
                except OSError:time.sleep(.1)
            request('login.php',{'email':'daniel@vrs.local','password':'Demo@12345'})
            # Retain a second demo login to verify database-bound sessions.
            second=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
            html=second.open(base+'login.php').read().decode()
            second_token=re.search(r'name="csrf" value="([^"]+)"',html)[1]
            second.open(base+'login.php',urllib.parse.urlencode({'csrf':second_token,'email':'daniel@vrs.local','password':'Demo@12345'}).encode()).read()
            html,_=request('setup.php')
            check('Needs attention' not in html,'Web server can write installation folders')
            test_request=urllib.request.Request(base+'setup.php',urllib.parse.urlencode({'csrf':token,'action':'test','db_host':'127.0.0.1','db_port':'3306','db_name':name,'db_user':'root','db_password':''}).encode(),headers={'Accept':'application/json'})
            try:
                client.open(test_request)
                raise AssertionError('Missing database was unexpectedly accepted')
            except urllib.error.HTTPError as error:
                status=json.loads(error.read())
                check(error.code==422 and not status['success'] and 'does not exist' in status['message'],'Connection test explains missing database as JSON')
            data={'action':'install','db_host':'127.0.0.1','db_port':'3306','db_name':name,'db_user':'root','db_password':'','create_database':'1','organization':'Installer Test Organization','admin_name':'Installation Admin','admin_email':'install@example.test','admin_password':'TemporaryTest!123','confirm_password':'TemporaryTest!123'}
            text,_=request('setup.php',data)
            check('Installation complete' in text,'MySQL schema and administrator installed through browser')
            check((app/'config/local.php').exists(),'Configuration activated')
            with second.open(base+'index.php') as response:
                check('login.php' in response.url,'Existing demo sessions cannot access installed database')
            text,url=request('index.php')
            check('login.php' in url,'Installer demo session cleared')
            text,url=request('login.php',{'email':'install@example.test','password':'TemporaryTest!123'})
            check(url.endswith('index.php') and 'Installation Admin' in text,'New administrator can sign in')
            for page in ['vehicles','users','reports','settings','audit']:
                text,_=request('index.php?page='+page)
                check('</html>' in text,'MySQL renders '+page)
            text,_=request('setup.php',data)
            check('Installation is locked' in text,'Repeat installation blocked')
            # Verify nonempty database refusal in a separate unconfigured copy.
            config=(app/'config/local.php').read_text()
            (app/'config/local.php').unlink()
            try:
                request('setup.php')
                text,_=request('setup.php',data)
                check('database contains tables' in text and not (app/'config/local.php').exists(),'Existing MySQL tables never overwritten')
            finally:
                (app/'config/local.php').write_text(config)
            print('All MySQL browser installation checks passed.')
        finally:
            if proc:
                proc.terminate();proc.wait(timeout=10)
            # Only remove this run's random, explicitly named disposable database.
            cleanup='<?php $name='+json.dumps(name)+'; if(!preg_match("/^vrs_install_test_[a-f0-9]{12}$/",$name))exit(1); $p=new PDO("mysql:host=127.0.0.1;port=3306","root","",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); $p->exec("DROP DATABASE IF EXISTS ".chr(96).$name.chr(96));'
            subprocess.run([PHP],input=cleanup,text=True,check=True)
            log.seek(0);output=log.read()
            assert 'PHP Warning' not in output and 'PHP Fatal' not in output,output
