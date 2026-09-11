<?php
function secure_transport(): void {
 if(PHP_SAPI==='cli')return;
 $tls=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
 $loopback=in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
 // Forwarded headers are untrusted. TLS must be set by the web-server configuration.
 if(!$tls&&!$loopback){http_response_code(403);header('Cache-Control: no-store');exit('HTTPS is required. Open this workspace using its secure HTTPS address.');}
 if($tls)header('Strict-Transport-Security: max-age=31536000');
 header('Referrer-Policy: no-referrer');
 header('X-Content-Type-Options: nosniff');
 header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
