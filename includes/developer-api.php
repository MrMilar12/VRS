<?php
require_role('Administrator');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo json_encode(['error'=>'POST required.']);return;}
check_csrf();require_once __DIR__.'/updater.php';$updater=new VrsUpdater(dirname(__DIR__),$config);$mode=field('mode',20);
if($mode==='check'){echo json_encode($updater->check(),JSON_THROW_ON_ERROR);return;}
if($mode==='prepare'){$target=field('target',40);if(!preg_match('/^[a-f0-9]{40}$/D',$target))throw new RuntimeException('Check for updates before downloading.');echo json_encode($updater->check($target),JSON_THROW_ON_ERROR);return;}
if(!in_array($mode,['apply','rollback']))throw new RuntimeException('Choose a valid update action.');
auth_limit('developer-update',(string)$user['id'],5,900);
if(!password_verify(auth_input('password',200),$user['password_hash']))throw new RuntimeException('Confirm your administrator password to install code updates.');
$result=$updater->apply(field('current',40),field('target',40),$mode==='rollback');
audit($mode==='rollback'?'Code update rolled back':'Code update installed',$result['previous'].' → '.$result['current']);
echo json_encode($result,JSON_THROW_ON_ERROR);
