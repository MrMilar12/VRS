<?php
// Development server router: php -S 127.0.0.1:8086 router.php
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
if(str_contains($path,'..')||preg_match('#(?:^|/)\.|^/(?:storage|config|database|includes|tests)(?:/|$)#',$path)) {http_response_code(404);exit('Not found');}
if($path==='/'||$path==='/index.php'){require __DIR__.'/index.php';return true;}
if(preg_match('#^/(?:login|actions|api|export|setup|install)\.php$|^/print/(?:requisition|daily-schedule)\.php$#',$path)){require __DIR__.$path;return true;}
if(preg_match('#^/assets/[a-zA-Z0-9/_-]+\.(?:css|js|png|jpg|jpeg|webp|ico)$#',$path)&&is_file(__DIR__.$path))return false;
http_response_code(404);echo 'Not found';return true;
