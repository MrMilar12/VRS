<?php
function e($v): string {return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');}
function all(string $sql,array $args=[]): array {global $pdo;$q=$pdo->prepare($sql);$q->execute($args);return $q->fetchAll();}
function one(string $sql,array $args=[]): ?array {return all($sql,$args)[0]??null;}
function run(string $sql,array $args=[]): void {global $pdo;$q=$pdo->prepare($sql);$q->execute($args);}
function setting(string $key,string $default=''): string {return one('SELECT setting_value FROM system_settings WHERE setting_key=?',[$key])['setting_value']??$default;}
function is_role(string ...$roles): bool {global $user;return $user && in_array($user['role'],$roles,true);}
function manage(): bool {return is_role('Administrator','Administrative Officer');}
function require_role(string ...$roles): void {if(!is_role(...$roles))throw new RuntimeException('You do not have permission to perform this action.');}
function audit(string $action,string $detail=''): void {global $user;run('INSERT INTO audit_logs(user_id,action,details,created_at) VALUES(?,?,?,?)',[$user['id']??null,$action,$detail,date('Y-m-d H:i:s')]);}
function csrf(): string {return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';}
function check_csrf(): void {if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))throw new RuntimeException('Your session token expired. Refresh the page and try again.');}
function redirect(string $url): never {header('Location: '.$url);exit;}
function flash(string $message,string $type='success'): void {$_SESSION['flash']=[$message,$type];}
function field(string $name,int $max=255,bool $required=true): string {$raw=$_POST[$name]??'';if(!is_string($raw))throw new RuntimeException('Please provide a valid '.str_replace('_',' ',$name).'.');$v=trim($raw);if(($required&&$v==='')||mb_strlen($v)>$max)throw new RuntimeException('Please provide a valid '.str_replace('_',' ',$name).'.');return $v;}
function number(string $name,int $min=0): int {$v=filter_var($_POST[$name]??null,FILTER_VALIDATE_INT);if($v===false||$v<$min)throw new RuntimeException('Invalid '.str_replace('_',' ',$name).'.');return $v;}
function datetime_value(string $name): string {$s=field($name,30);$d=DateTime::createFromFormat('Y-m-d\TH:i',$s);if(!$d||$d->format('Y-m-d\TH:i')!==$s)throw new RuntimeException('Enter a valid date and time.');return $d->format('Y-m-d H:i:s');}
function badge(string $status): string {$class=match($status){'Approved','Reserved'=>'blue','Dispatched','In Use'=>'purple','Completed','Returned','Available','Active'=>'green','Rejected','Under Maintenance'=>'red','Pending Supervisor','Pending Administrative Approval','Submitted'=>'amber',default=>'gray'};return '<span class="badge '.$class.'"><i></i>'.e($status).'</span>';}
function shortdate(string $s,string $format='M j, Y'): string {return date($format,strtotime($s));}
function request_query(): string {return 'SELECT r.*,u.full_name requester_name,u.position,o.name office_name,o.code office_code,v.model,v.plate,d.full_name driver_name FROM requisitions r JOIN users u ON u.id=r.requester_id JOIN offices o ON o.id=r.office_id LEFT JOIN vehicles v ON v.id=r.vehicle_id LEFT JOIN drivers d ON d.id=r.driver_id';}
function visible(array $r): bool {global $user;return is_role('Administrator','Administrative Officer','Dispatcher')||($user['role']==='Supervisor'&&(int)$r['office_id']===(int)$user['office_id'])||(int)$r['requester_id']===(int)$user['id'];}
function requests(): array {global $user;$sql=request_query();$args=[];if(is_role('Requester')){$sql.=' WHERE r.requester_id=?';$args[]=$user['id'];}elseif(is_role('Supervisor')){$sql.=' WHERE r.office_id=?';$args[]=$user['office_id'];}return all($sql.' ORDER BY r.start_datetime DESC,r.id DESC',$args);}
// Every calendar excludes unapproved requests, even those owned by the viewer.
function calendar_requests(): array {
 global $user;if(!$user)return [];
 $sql=request_query()." WHERE r.status IN ('Approved','Dispatched','Returned','Completed')";
 $args=[];
 if(!is_role('Administrator')){$sql.=' AND r.requester_id=?';$args[]=$user['id'];}
 return all($sql.' ORDER BY r.start_datetime DESC,r.id DESC',$args);
}
function calendar_resources(array $rows): array {
 $resources=['vehicles'=>[],'drivers'=>[],'offices'=>[]];
 foreach($rows as $r){
  if($r['vehicle_id'])$resources['vehicles'][$r['vehicle_id']]=['id'=>$r['vehicle_id'],'model'=>$r['model'],'plate'=>$r['plate'],'type'=>$r['vehicle_type']];
  if($r['driver_id'])$resources['drivers'][$r['driver_id']]=['id'=>$r['driver_id'],'full_name'=>$r['driver_name']];
  $resources['offices'][$r['office_id']]=['id'=>$r['office_id'],'name'=>$r['office_name']];
 }
 foreach(['vehicles'=>'model','drivers'=>'full_name','offices'=>'name'] as $key=>$label){$resources[$key]=array_values($resources[$key]);usort($resources[$key],fn($a,$b)=>strcasecmp($a[$label]??'',$b[$label]??''));}
 return $resources;
}
function get_request(int $id): array {$r=one(request_query().' WHERE r.id=?',[$id]);if(!$r||!visible($r))throw new RuntimeException('Request not found or access denied.');return $r;}
function lock_transaction(): void {global $pdo;if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')$pdo->exec('BEGIN IMMEDIATE');else $pdo->beginTransaction();}
function finish_transaction(bool $success): void {global $pdo;if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')$pdo->exec($success?'COMMIT':'ROLLBACK');elseif($pdo->inTransaction()){$success?$pdo->commit():$pdo->rollBack();}}
function lock_record(string $table,int $id): ?array {global $pdo;if(!in_array($table,['requisitions','vehicles','drivers']))throw new RuntimeException('Invalid resource.');return one("SELECT * FROM $table WHERE id=?".($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$id]);}
function conflicts(int $vehicle,int $driver,string $start,string $end,int $exclude=0): array {
    global $pdo;
    // Locking reads observe the latest committed assignments after resource locks are acquired.
    $currentRead=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'&&$pdo->inTransaction()?' FOR UPDATE':'';
    $buffer=(int)setting('turnaround_minutes','30');$from=date('Y-m-d H:i:s',strtotime($start)-$buffer*60);$to=date('Y-m-d H:i:s',strtotime($end)+$buffer*60);
    $found=all("SELECT reference,vehicle_id,driver_id,start_datetime,end_datetime FROM requisitions WHERE id<>? AND status IN ('Approved','Dispatched') AND (vehicle_id=? OR driver_id=?) AND ((start_datetime < ? AND end_datetime > ?) OR (status='Dispatched' AND end_datetime < ?))".$currentRead,[$exclude,$vehicle,$driver,$to,$from,date('Y-m-d H:i:s')]);
    $messages=[];foreach($found as $r)$messages[]=$r['reference'].' has an overlapping '.((int)$r['vehicle_id']===$vehicle?'vehicle':'driver').' assignment ('.shortdate($r['start_datetime'],'M j, Y g:i A').' – '.shortdate($r['end_datetime'],'M j, Y g:i A').').';
    if(one('SELECT id FROM vehicle_blocks WHERE vehicle_id=? AND start_datetime < ? AND end_datetime > ?'.$currentRead,[$vehicle,$to,$from]))$messages[]='The vehicle has a maintenance block during this schedule.';
    return $messages;
}
function vehicle_assignment_issues(array $v,array $r): array {
 $issues=[];
 if(!in_array($v['status'],['Available','Reserved']))$issues[]='Vehicle is '.$v['status'].'.';
 if($v['registration_expiry']<substr($r['end_datetime'],0,10))$issues[]='Vehicle registration expires before this trip ends.';
 if(count(array_filter(preg_split('/[,\n]+/',$r['passengers'])))>(int)$v['capacity'])$issues[]='The passenger list exceeds this vehicle’s capacity.';
 return $issues;
}
function driver_assignment_issues(array $d,array $r): array {
 $issues=[];
 if($d['status']!=='Available')$issues[]='Driver is unavailable.';
 if($d['license_expiry']<substr($r['end_datetime'],0,10))$issues[]='Driver license expires before this trip ends.';
 return $issues;
}
function notify_request(array $r,string $message): void {run('INSERT INTO notifications(user_id,message,requisition_id,created_at) VALUES(?,?,?,?)',[$r['requester_id'],$r['reference'].' · '.$message,$r['id'],date('Y-m-d H:i:s')]);}
function icon(string $name,int $size=20): string {
$paths=['grid'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>','calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-13 4h2m4 0h2m-8 3h2"/>','file'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8m-8 4h6"/>','car'=>'<path d="m5 6-2 6v7h3v-3h12v3h3v-7l-2-6zM3 12h18M7 9h10M6 14h2m8 0h2"/>','users'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v3"/>','office'=>'<path d="M4 21V3h12v18M2 21h20M16 9h4v12M8 7h4M8 11h4M8 15h4m-3 6v-3h2v3"/>','check'=>'<path d="m5 12 4 4L19 6"/>','shield'=>'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6z"/><path d="m8 12 3 3 5-6"/>','arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>','chart'=>'<path d="M4 3v18h17M8 17v-6m5 6V7m5 10V4"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>','settings'=>'<path d="m10 3-1 3-3 1-3 3 2 3-1 4 4 1 2 3 3-2 4 1 1-4 3-2-2-3 1-4-4-1-2-3z"/><circle cx="12" cy="12" r="3"/>','bell'=>'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>','search'=>'<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>','plus'=>'<path d="M12 5v14M5 12h14"/>','logout'=>'<path d="M9 4H4v16h5m6-4 4-4-4-4m-6 4h12"/>','print'=>'<path d="M6 9V3h12v6M6 17H3V9h18v8h-3M6 14h12v7H6z"/>','chevron'=>'<path d="m9 5 7 7-7 7"/>','wrench'=>'<path d="m14 6 4 4 3-3a6 6 0 0 1-8 8l-7 7-4-4 7-7a6 6 0 0 1 8-8z"/>'];
return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['grid']).'</svg>';
}
