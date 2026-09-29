<?php
// CLI installation; browser requests open the protected setup wizard.
if(PHP_SAPI!=='cli'){header('Location: setup.php');exit;}
$config=require __DIR__.'/config/system.php';if(is_file(__DIR__.'/config/local.php'))$config=array_replace($config,require __DIR__.'/config/local.php');
if($config['demo']){fwrite(STDERR,"Set demo => false in config/local.php before installing MySQL.\n");exit(1);}
require __DIR__.'/includes/installer.php';
try{
 $db=new PDO($config['dsn'],$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 // Refuse to modify an existing installation.
 $tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);if($tables)throw new RuntimeException('Use an empty database. Existing tables were found; nothing was changed.');
 echo "Organization name: ";$organization=trim(fgets(STDIN));echo "Administrator full name: ";$name=trim(fgets(STDIN));echo "Administrator email: ";$email=trim(fgets(STDIN));echo "Administrator password (10-72 characters; input is visible): ";$pass=trim(fgets(STDIN));echo "Confirm administrator password: ";$confirm=trim(fgets(STDIN));
 $account=installation_account(['organization'=>$organization,'admin_name'=>$name,'admin_email'=>$email,'admin_password'=>$pass,'confirm_password'=>$confirm]);
 installation_schema($db);
 installation_seed($db,$account);
 echo "\nInstallation complete. Sign in at login.php with your new account.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,'Installation failed: '.$e->getMessage()."\n");exit(1);}
