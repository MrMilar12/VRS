<?php
declare(strict_types=1);
require_once __DIR__.'/transport-security.php';
secure_transport();
// Fail briefly before loading application files while a deployment replaces code.
$updateMarker=__DIR__.'/../storage/update-maintenance.json';
if((@filemtime($updateMarker)?:0)>time()-300){http_response_code(503);header('Retry-After: 30');exit('A software update is being installed. Please try again shortly.');}
$config = require __DIR__.'/../config/system.php';
if (is_file(__DIR__.'/../config/local.php')) $config = array_replace($config, require __DIR__.'/../config/local.php');
date_default_timezone_set($config['timezone']);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies','1');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
require_once __DIR__.'/database.php';
require_once __DIR__.'/functions.php';
require_once __DIR__.'/records.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/url-security.php';
try {
    $pdo = new PDO($config['demo'] ? 'sqlite:'.__DIR__.'/../storage/demo.sqlite' : $config['dsn'], $config['username'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    auth_schema($pdo);
    if ($config['demo']) { $pdo->exec('PRAGMA foreign_keys=ON'); $pdo->exec('PRAGMA busy_timeout=5000'); initialize_database($pdo); }
} catch (Throwable $e) {
    http_response_code(503); exit('Database unavailable. <a href="setup.php">Open workspace setup</a> or follow README.md to configure MySQL.');
}
// Bind authentication to its database; demo IDs cannot carry into a new installation.
$databaseIdentity=hash('sha256',($config['demo']?'demo-sqlite':$config['dsn']).'|'.$config['username']);
if (($_SESSION['database_identity']??null)!==$databaseIdentity) {
    $_SESSION=[];
    session_regenerate_id(true);
    $_SESSION['database_identity']=$databaseIdentity;
    $_SESSION['csrf']=bin2hex(random_bytes(32));
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
$user=null;
if (!empty($_SESSION['user_id'])) {
 $account=one('SELECT * FROM users WHERE id=? AND status=?',[$_SESSION['user_id'],'Active']);
 $valid=auth_session_valid($account,$_SESSION,null,$account&&auth_is_disabled((int)$account['id']));
 if($valid){$user=$account;$_SESSION['auth_seen']=time();}else{unset($_SESSION['user_id'],$_SESSION['auth_level'],$_SESSION['auth_credential']);session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));}
}
