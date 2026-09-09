<?php
require __DIR__.'/../includes/bootstrap.php';
if(!$user)redirect('../login.php');
try{
 if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Open the print slip from the requisition details page.');check_csrf();$r=get_request((int)($_POST['id']??0));if(!in_array($r['status'],['Approved','Dispatched','Returned','Completed']))throw new RuntimeException('Only approved requisitions can be printed.');
 $approvals=all("SELECT a.*,u.full_name FROM approvals a JOIN users u ON u.id=a.user_id WHERE requisition_id=? AND decision='Approved' ORDER BY a.id",[$r['id']]);$sign=[];foreach($approvals as $a)$sign[$a['stage']]=$a;
 $m=one('SELECT m.*,u.full_name checked_name FROM vehicle_movements m LEFT JOIN users u ON u.id=m.checked_by WHERE requisition_id=?',[$r['id']]);run('INSERT INTO print_logs(requisition_id,user_id,created_at) VALUES(?,?,?)',[$r['id'],$user['id'],date('Y-m-d H:i:s')]);audit('Requisition print opened',$r['reference']);
}catch(Throwable $e){http_response_code(403);exit(e($e instanceof PDOException?'Unable to load print record.':$e->getMessage()));}
require __DIR__.'/../includes/print-slip.php';
