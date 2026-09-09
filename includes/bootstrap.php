<?php
declare(strict_types=1);
$config = require __DIR__.'/../config/system.php';
if (is_file(__DIR__.'/../config/local.php')) $config = array_replace($config, require __DIR__.'/../config/local.php');
date_default_timezone_set($config['timezone']);
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
require_once __DIR__.'/database.php';
require_once __DIR__.'/functions.php';
require_once __DIR__.'/records.php';
try {
    $pdo = new PDO($config['demo'] ? 'sqlite:'.__DIR__.'/../storage/demo.sqlite' : $config['dsn'], $config['username'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    if ($config['demo']) { $pdo->exec('PRAGMA foreign_keys=ON'); $pdo->exec('PRAGMA busy_timeout=5000'); initialize_database($pdo); }
} catch (Throwable $e) {
    http_response_code(503); exit('Database unavailable. <a href="setup.php">Open workspace setup</a> or follow README.md to configure MySQL.');
}
// Bind authentication to its database; demo IDs cannot carry into a new installation.
$databaseIdentity=hash('sha256',($config['demo']?'demo-sqlite':$config['dsn']).'|'.$config['username']);
if (($_SESSION['database_identity']??null)!==$databaseIdentity) {
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
    $_SESSION['database_identity']=$databaseIdentity;
    $_SESSION['csrf']=bin2hex(random_bytes(32));
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
$user=null;
if (!empty($_SESSION['user_id'])) $user=one('SELECT * FROM users WHERE id=? AND status=?', [$_SESSION['user_id'],'Active']);
