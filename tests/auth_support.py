"""Authenticator helpers used only by isolated integration tests."""
import base64, hashlib, hmac, re, struct, time
RECOVERY = {}
def totp(secret, at=None):
    counter=int(time.time() if at is None else at)//30
    key=base64.b32decode(secret+'='*((8-len(secret)%8)%8))
    digest=hmac.new(key,struct.pack('>Q',counter),hashlib.sha1).digest()
    offset=digest[-1]&15
    return str((struct.unpack('>I',digest[offset:offset+4])[0]&0x7fffffff)%1000000).zfill(6)
def finish_mfa(post,email,text,url):
    assert 'two-factor.php' in url, 'Password must lead to second-factor verification'
    secret=re.search(r'<code data-setup-secret>([^<]+)</code>',text)
    if secret:
        text,url=post('two-factor.php',{'action':'enroll','code':totp(secret[1])})
        codes=re.findall(r'<code data-recovery-code>([^<]+)</code>',text)
        assert len(codes)==10, 'Enrollment must issue recovery codes'
        RECOVERY[email]=codes
        text,url=post('two-factor.php',{'action':'finish'})
    else:
        assert RECOVERY.get(email), 'Test needs an unused recovery code'
        text,url=post('two-factor.php',{'action':'verify','code':RECOVERY[email].pop()})
    assert url.endswith('index.php'), 'Second factor must complete sign-in'
    return text,url
