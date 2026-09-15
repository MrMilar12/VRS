<?php
// CLI installation; browser requests open the protected setup wizard.
if(PHP_SAPI!=='cli'){header('Location: setup.php');exit;}
$config=require __DIR__.'/config/system.php';if(is_file(__DIR__.'/config/local.php'))$config=array_replace($config,require __DIR__.'/config/local.php');
if($config['demo']){fwrite(STDERR,"Set demo => false in config/local.php before installing MySQL.\n");exit(1);}
require __DIR__.'/includes/database.php';
try{
 $db=new PDO($config['dsn'],$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 // Refuse to modify an existing installation.
 $tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);if($tables)throw new RuntimeException('Use an empty database. Existing tables were found; nothing was changed.');
 echo "Administrator full name: ";$name=trim(fgets(STDIN));echo "Administrator email: ";$email=trim(fgets(STDIN));echo "Administrator password (10+ characters; input is visible): ";$pass=trim(fgets(STDIN));
 if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<10)throw new RuntimeException('Invalid account information. No tables were created.');
 $sql=preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/database/vehicle_requisition.sql'));foreach(explode(';',$sql) as $statement)if(trim($statement))$db->exec($statement);
 $db->beginTransaction();
 $db->prepare('INSERT INTO offices(code,name,head,supervisor,contact,status) VALUES(?,?,?,?,?,?)')->execute(['ADM','Administrative Office',$name,$name,'','Active']);
 $db->prepare('INSERT INTO users(full_name,email,password_hash,role,office_id,position,status) VALUES(?,?,?,?,?,?,?)')->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),'Administrator',1,'Administrator','Active']);
 foreach(['turnaround_minutes'=>'30','organization'=>'Government Workspace','supervisor_signatory'=>'Immediate Supervisor','admin_signatory'=>'Administrative Officer'] as $k=>$v)$db->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES(?,?)')->execute([$k,$v]);
 $db->prepare('INSERT INTO audit_logs(user_id,action,details,created_at) VALUES(?,?,?,?)')->execute([1,'System installed','MySQL installation completed',date('Y-m-d H:i:s')]);$db->commit();echo "\nInstallation complete. Sign in at login.php with your new account.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,'Installation failed: '.$e->getMessage()."\n");exit(1);}
