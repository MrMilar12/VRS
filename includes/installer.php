<?php
declare(strict_types=1);
function installation_input(array $input): array {
    $get = static function(string $key, int $max=190) use($input): string {
        $value=$input[$key]??'';
        if(!is_string($value)||strlen($value)>$max) throw new RuntimeException('Invalid '.str_replace('_',' ',$key).'.');
        return trim($value);
    };
    $host=$get('db_host',253);
    if(!preg_match('/\A[a-zA-Z0-9.:-]+\z/',$host)) throw new RuntimeException('Enter a valid database hostname or IP address.');
    $port=filter_var($input['db_port']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
    if($port===false) throw new RuntimeException('Database port must be between 1 and 65535.');
    $database=$get('db_name',64);
    if(!preg_match('/\A[a-zA-Z0-9_]+\z/',$database)) throw new RuntimeException('Database name may contain letters, numbers, and underscores only.');
    $username=$get('db_user');
    if($username==='') throw new RuntimeException('Enter the database username.');
    $password=$input['db_password']??'';
    if(!is_string($password)||strlen($password)>1024) throw new RuntimeException('Invalid database password.');
    return ['demo'=>false,'dsn'=>"mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",'username'=>$username,'password'=>$password,'host'=>$host,'port'=>$port,'database'=>$database];
}
function installation_account(array $input): array {
    $account=[];
    foreach(['organization'=>255,'admin_name'=>160,'admin_email'=>190] as $key=>$max) {
        $value=$input[$key]??'';
        if(!is_string($value)||trim($value)===''||strlen($value)>$max) throw new RuntimeException('Enter a valid '.str_replace('_',' ',$key).'.');
        $account[$key]=trim($value);
    }
    if(!filter_var($account['admin_email'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid administrator email.');
    $password=$input['admin_password']??'';
    if(!is_string($password)||strlen($password)<10||strlen($password)>72||str_contains($password,"\0")) throw new RuntimeException('Administrator password must contain 10–72 bytes.');
    if($password!==($input['confirm_password']??null)) throw new RuntimeException('Administrator passwords do not match.');
    $account['password_hash']=password_hash($password,PASSWORD_DEFAULT);
    return $account;
}
function installation_connect(array $c,bool $create=false): PDO {
    $options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5];
    if($create) {
        $server=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';charset=utf8mb4',$c['username'],$c['password'],$options);
        $server->exec('CREATE DATABASE IF NOT EXISTS '.chr(96).$c['database'].chr(96).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
    return new PDO($c['dsn'],$c['username'],$c['password'],$options);
}
function installation_empty(PDO $db): void {
    if($db->query('SHOW TABLES')->fetchColumn()!==false) throw new RuntimeException('This database contains tables. Choose a new or empty database; existing data will not be overwritten.');
}
function installation_schema(PDO $db): void {
    $sql=preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/../database/vehicle_requisition.sql'));
    foreach(explode(';',$sql) as $statement) if(trim($statement)!=='') $db->exec($statement);
}
function installation_seed(PDO $db,array $account): void {
    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO offices(code,name,head,supervisor,contact,status) VALUES(?,?,?,?,?,?)')->execute(['ADM','Administrative Office',$account['admin_name'],$account['admin_name'],'','Active']);
        $officeId=$db->lastInsertId();
        $db->prepare('INSERT INTO users(full_name,email,password_hash,role,office_id,position,status) VALUES(?,?,?,?,?,?,?)')->execute([$account['admin_name'],$account['admin_email'],$account['password_hash'],'Administrator',$officeId,'System Administrator','Active']);
        $userId=$db->lastInsertId();
        foreach(['turnaround_minutes'=>'30','organization'=>$account['organization'],'supervisor_signatory'=>'Immediate Supervisor','admin_signatory'=>'Administrative Officer'] as $key=>$value)
            $db->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES(?,?)')->execute([$key,$value]);
        $db->prepare('INSERT INTO audit_logs(user_id,action,details,created_at) VALUES(?,?,?,?)')->execute([$userId,'System installed','MySQL workspace and initial administrator created',date('Y-m-d H:i:s')]);
        $db->commit();
    } catch(Throwable $error) {
        if($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}


function installation_database_error(PDOException $error): string {
    $code=(int)($error->errorInfo[1]??0);
    return match($code) {
        1049 => 'The database does not exist. Select “Create the database if it does not exist” and install, or create it in phpMyAdmin before testing.',
        1045,1698 => 'MySQL rejected the username or password. Check the database account credentials.',
        1044,1142,1227 => 'The MySQL account lacks permission for this operation. Grant access to the selected database, or create the database in phpMyAdmin first.',
        2002,2003,2005 => 'Cannot reach MySQL. Start MySQL in XAMPP and check the host and port. For local TCP connections, use 127.0.0.1.',
        default => 'MySQL could not complete this operation. Verify the connection and database permissions, then try again.',
    };
}
