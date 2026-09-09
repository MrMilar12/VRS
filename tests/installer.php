<?php
require __DIR__.'/../includes/installer.php';
function expect(bool $value,string $message):void {if(!$value)throw new RuntimeException($message);echo "PASS: $message\n";}
$input=['db_host'=>'127.0.0.1','db_port'=>'3306','db_name'=>'vrs_test','db_user'=>'root','db_password'=>"secret'\\value",'organization'=>'Test Organization','admin_name'=>'Test Admin','admin_email'=>'admin@example.test','admin_password'=>'TestPassword!123','confirm_password'=>'TestPassword!123'];
$config=installation_input($input);
expect($config['dsn']==='mysql:host=127.0.0.1;port=3306;dbname=vrs_test;charset=utf8mb4','MySQL DSN constructed');
expect($config['password']===$input['db_password'],'Database password preserved literally');
foreach(['db_host'=>'localhost;dbname=other','db_name'=>'vrs;DROP DATABASE x','db_port'=>'0','db_user'=>''] as $key=>$bad){
    $rejected=false;try{installation_input(array_replace($input,[$key=>$bad]));}catch(RuntimeException $e){$rejected=true;}
    expect($rejected,'Reject invalid '.$key);
}
$account=installation_account($input);
expect(password_verify($input['admin_password'],$account['password_hash']),'Administrator password hashed');
$rejected=false;try{installation_account(array_replace($input,['confirm_password'=>'mismatch']));}catch(RuntimeException $e){$rejected=true;}
expect($rejected,'Reject mismatched passwords');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec(file_get_contents(__DIR__.'/../database/schema.sqlite.sql'));
installation_seed($db,$account);
$row=$db->query('SELECT * FROM users')->fetch();
expect($row['role']==='Administrator'&&$row['status']==='Active'&&$row['email']===$input['admin_email'],'Seed active administrator');
expect((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===1,'No demo accounts created');
expect($db->query("SELECT setting_value FROM system_settings WHERE setting_key='organization'")->fetchColumn()===$input['organization'],'Organization settings saved');
expect((int)$db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn()===1,'Installation audited');
echo "Installer checks passed.\n";
