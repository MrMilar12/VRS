<?php
declare(strict_types=1);
require __DIR__.'/includes/installer.php';
$defaults=require __DIR__.'/config/system.php';
date_default_timezone_set($defaults['timezone']);
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);
session_start();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
function esc($value): string {return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$local=in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
$locked=is_file(__DIR__.'/config/local.php');
$complete=!empty($_SESSION['installation_complete']);
$requirements=[
    'PHP 8.0 or newer'=>PHP_VERSION_ID>=80000,
    'PDO MySQL extension'=>extension_loaded('pdo_mysql'),
    'Multibyte string extension'=>extension_loaded('mbstring'),
    'File information extension'=>extension_loaded('fileinfo'),
    'GD image extension'=>extension_loaded('gd'),
    'Writable configuration folder'=>is_writable(__DIR__.'/config'),
    'Writable storage folder'=>is_writable(__DIR__.'/storage'),
    'Writable vehicle uploads folder'=>is_writable(__DIR__.'/assets/uploads'),
];
if(empty($_SESSION['setup_csrf'])) $_SESSION['setup_csrf']=bin2hex(random_bytes(32));
$message='';$error='';$lock=null;$staged=null;$schemaStarted=false;
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!$local) throw new RuntimeException('Browser installation is available on this server only. Use the command-line installer for a remote deployment.');
        if($locked) throw new RuntimeException('Installation is locked because config/local.php already exists. Your current configuration has not been changed.');
        if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['setup_csrf'],$_POST['csrf'])) throw new RuntimeException('Your installation session expired. Refresh the page and try again.');
        $action=$_POST['action']??'';
        if(!in_array($action,['test','install'],true)) throw new RuntimeException('Invalid installation action.');
        $needed=$action==='test'?['PDO MySQL extension'=>$requirements['PDO MySQL extension']]:$requirements;
        $failed=array_keys(array_filter($needed,static fn($passed)=>!$passed));
        if($failed) throw new RuntimeException('Resolve these requirements: '.implode(', ',$failed).'.');
        $dbConfig=installation_input($_POST);
        if($action==='test') {
            $db=installation_connect($dbConfig);
            installation_empty($db);
            $message='Connection successful. The database is empty and ready to install. No changes were made.';
        } else {
            $account=installation_account($_POST);
            $lock=fopen(__DIR__.'/storage/installation.lock','c');
            if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Another installation is running. Try again when it finishes.');
            clearstatcache(true,__DIR__.'/config/local.php');
            if(is_file(__DIR__.'/config/local.php')) throw new RuntimeException('This workspace has already been configured.');
            $savedConfig=array_intersect_key($dbConfig,array_flip(['demo','dsn','username','password']));
            $staged=tempnam(__DIR__.'/config','.installation-');
            if(!$staged||!chmod($staged,0600)||file_put_contents($staged,"<?php\nreturn ".var_export($savedConfig,true).";\n")===false) throw new RuntimeException('Unable to write configuration. Check the configuration folder permissions.');
            $db=installation_connect($dbConfig,isset($_POST['create_database']));
            installation_empty($db);
            $schemaStarted=true;
            installation_schema($db);
            installation_seed($db,$account);
            if(!rename($staged,__DIR__.'/config/local.php')) throw new RuntimeException('The database is installed, but configuration could not be activated. Configure config/local.php manually with the same database credentials.');
            $staged=null;
            // Demo user IDs must not become authenticated production user IDs.
            $_SESSION=['installation_complete'=>true];
            session_regenerate_id(true);
            flock($lock,LOCK_UN);fclose($lock);$lock=null;
            header('Location: setup.php');exit;
        }
    } catch(Throwable $exception) {
        $error=$exception instanceof PDOException
            ? installation_database_error($exception)
            : $exception->getMessage();
        if($schemaStarted) $error.=' Tables may have been created: MySQL schema changes cannot be rolled back. Inspect the database before retrying; the installer will not overwrite it.';
    } finally {
        if($staged&&is_file($staged)) unlink($staged);
        if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}
    }
}
if(($_POST['action']??'')==='test'&&str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')) {
    header('Content-Type: application/json; charset=utf-8');
    if($error!=='') http_response_code(422);
    echo json_encode(['success'=>$error==='', 'message'=>$error?:$message]);
    exit;
}
$values=['db_host'=>'127.0.0.1','db_port'=>'3306','db_name'=>'vrs','db_user'=>'root','organization'=>'','admin_name'=>'','admin_email'=>''];
foreach($values as $key=>$default) if(is_string($_POST[$key]??null)) $values[$key]=$_POST[$key];
if(!$local&&!$locked) http_response_code(403);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Install VRS · Workspace setup</title><link rel="stylesheet" href="assets/css/style.css"><script src="assets/js/setup.js" defer></script></head><body><main class="installation-shell"><a class="installation-brand" href="index.php">VRS <span>FLEET OPERATIONS</span></a><div class="page-heading"><div><h1><?=$complete?'Your workspace is ready':'Install your VRS workspace'?></h1><p>Connect your database, create an administrator, and get moving.</p></div></div>
<?php if($error):?><div class="alert error" role="alert"><?=esc($error)?></div><?php endif?>
<?php if($message):?><div class="alert success" role="status"><?=esc($message)?></div><?php endif?>
<?php if($locked):?><section class="panel"><div class="panel-heading"><h2><?=$complete?'Installation complete':'Installation is locked'?></h2></div><div class="form-body stack"><p><?=$complete?'Your MySQL database, organization, and administrator account have been created. Sign in using the administrator credentials you entered.':'This workspace already has a local configuration. The installer cannot overwrite it. If you configured MySQL manually, complete initialization with php install.php.'?></p><a class="btn primary" href="login.php">Continue to sign in →</a></div></section>
<?php elseif(!$local):?><section class="panel"><div class="form-body stack"><h2>Open installation on the server</h2><p>Open this page through localhost to use the browser installer. For remote hosting, configure <code>config/local.php</code> and run <code>php install.php</code> through your hosting terminal.</p><a class="btn" href="login.php">Back to sign in</a></div></section>
<?php else:?>
<?php if(!$requirements['Writable configuration folder']||!$requirements['Writable storage folder']||!$requirements['Writable vehicle uploads folder']):?>
<div class="alert error"><div><strong>PHP needs permission to save installation files.</strong><p>Give the web-server account write access to <code>config/</code>, <code>storage/</code>, and <code>assets/uploads/</code>. On XAMPP for macOS this account is normally <code>daemon</code>. See the permission instructions in README.md, then refresh this page.</p></div></div>
<?php endif?>
<section class="panel"><div class="panel-heading"><h2><span class="step-number">1</span> System requirements</h2><span class="badge <?=in_array(false,$requirements,true)?'red':'green'?>"><?=in_array(false,$requirements,true)?'Action required':'Ready to install'?></span></div><div class="form-body installation-checks"><?php foreach($requirements as $label=>$passed):?><div><span><?=esc($label)?></span><strong class="badge <?=$passed?'green':'red'?>"><?=$passed?'Passed':'Needs attention'?></strong></div><?php endforeach?></div></section>
<form action="setup.php" method="post" class="stack" autocomplete="off" id="installation-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['setup_csrf'])?>"><section class="panel"><div class="panel-heading"><div><h2><span class="step-number">2</span> MySQL connection</h2><p>Use an empty database. Existing tables and demo records are never overwritten.</p></div></div><div class="form-body form-grid"><?php foreach(['db_host'=>'Database host','db_port'=>'Port','db_name'=>'Database name','db_user'=>'Database username'] as $key=>$label):?><label><?=$label?><input name="<?=$key?>" value="<?=esc($values[$key])?>" required <?=$key==='db_port'?'type="number" min="1" max="65535"':'maxlength="253"'?>></label><?php endforeach?><label class="span-2">Database password <span class="field-hint">Leave blank only if your MySQL account has no password.</span><input type="password" name="db_password" maxlength="1024" autocomplete="new-password"></label><label class="checkbox-label span-2"><input type="checkbox" name="create_database" value="1" <?=isset($_POST['create_database'])?'checked':''?>> Create the database if it does not exist</label><div class="span-2"><button class="btn" name="action" value="test" formnovalidate id="test-connection">Test existing database connection</button><p class="field-hint" style="margin-top:10px">Connection testing does not create a database. Your entries stay in this form while the connection is checked.</p><div id="connection-result" role="status" aria-live="polite"></div></div></div></section><section class="panel"><div class="panel-heading"><h2><span class="step-number">3</span> Organization & administrator</h2></div><div class="form-body form-grid"><label class="span-2">Organization name<input name="organization" maxlength="255" required value="<?=esc($values['organization'])?>" placeholder="Your government office or organization"></label><label>Administrator full name<input name="admin_name" maxlength="160" required value="<?=esc($values['admin_name'])?>"></label><label>Administrator email<input type="email" name="admin_email" maxlength="190" required value="<?=esc($values['admin_email'])?>"></label><label>Administrator password<input type="password" name="admin_password" minlength="10" maxlength="72" required autocomplete="new-password"><span class="field-hint">Use 10–72 characters for an ASCII password.</span></label><label>Confirm password<input type="password" name="confirm_password" minlength="10" maxlength="72" required autocomplete="new-password"></label></div></section><div class="installation-actions"><a class="btn" href="login.php">Continue with local demo</a><button class="btn primary" name="action" value="install" <?=in_array(false,$requirements,true)?'disabled':''?>>Install workspace →</button></div><p class="muted">Installation saves the MySQL configuration and switches this application from demo mode. Your SQLite demo data stays on disk. New MySQL workspaces use the administrator entered above.</p></form><?php endif?><footer class="page-footer"><span>VRS · Workspace installation</span><span>Native PHP & MySQL</span></footer></main></body></html>
