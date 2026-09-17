"""Verify malformed provider replies are retried once using a local fake provider."""
import json, subprocess, sys, threading
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path
root=Path(__file__).resolve().parents[1]
seen=[]
always_bad=False
class Provider(BaseHTTPRequestHandler):
    def log_message(self,*args): pass
    def do_POST(self):
        seen.append(json.loads(self.rfile.read(int(self.headers['Content-Length']))))
        content='not valid JSON' if always_bad or len(seen)==1 else json.dumps({'intent':'booking','topic':'booking','lookup':{},'reply':'When do you leave?','draft':{'destination':'Baler'}})
        body=json.dumps({'done':True,'message':{'content':content}}).encode()
        self.send_response(200);self.send_header('Content-Type','application/json');self.end_headers();self.wfile.write(body)
server=HTTPServer(('127.0.0.1',0),Provider)
threading.Thread(target=server.serve_forever,daemon=True).start()
php=sys.argv[1] if len(sys.argv)>1 else 'php'
code='''require "includes/booking-assistant.php";
$config=['booking_ai_provider'=>'ollama','ollama_url'=>'http://127.0.0.1:PORT','ollama_model'=>'test','openai_model'=>'test','timezone'=>'Asia/Manila'];
try{$r=booking_respond([['role'=>'user','content'=>'A trip to Baler']],['destination'=>'Baler']);echo json_encode(['ok'=>true,'destination'=>$r['draft']['destination']]);}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}'''.replace('PORT',str(server.server_port))
try:
    result=json.loads(subprocess.check_output([php,'-r',code],cwd=root))
    assert result=={'ok':True,'destination':'Baler'} and len(seen)==2,result
    assert 'Formatting correction' in seen[1]['messages'][-1]['content']
    print('PASS: One format retry repairs response and preserves trip details')
    seen.clear();always_bad=True
    result=json.loads(subprocess.check_output([php,'-r',code],cwd=root))
    assert result['ok'] is False and len(seen)==2 and 'after retrying' in result['error'],result
    print('PASS: Repeated malformed responses stop after one retry')
finally:server.shutdown();server.server_close()
