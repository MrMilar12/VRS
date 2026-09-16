<?php
$rows=requests();$title='Requisitions';$description='Every request, from the first step to the final mile.';
if($page==='approvals'){$title='Approval inbox';$description='Review vehicle and personnel requisitions awaiting approval. Schedule times: '.$config['timezone'].'.';$rows=approval_requests();}
if($page==='dispatch'){$title='Dispatch & returns';$description='Release approved vehicles and record every safe return.';$rows=array_values(array_filter($rows,fn($r)=>in_array($r['status'],['Approved','Dispatched','Returned','Completed'])));}
$q=is_string($_GET['q']??null)?trim($_GET['q']):'';
if($page==='requisitions'&&$q!==''){
 $rows=[...array_map(fn($r)=>array_replace($r,['request_kind'=>'Vehicle']),$rows),...array_map(fn($r)=>array_replace($r,['request_kind'=>'Personnel']),personnel_bookings())];
 usort($rows,fn($a,$b)=>strcmp($b['start_datetime'],$a['start_datetime'])?:strcmp($b['reference'],$a['reference']));
}
$statuses=array_values(array_unique(array_column($rows,'status')));$offices=array_unique(array_column($rows,'office_name','office_id'));
$status=$_GET['status']??'';$office=$_GET['office']??'';
$filtered=array_values(array_filter($rows,fn($r)=>(!$status||$r['status']===$status)&&(!$office||$r['office_id']==$office)&&(!$q||stripos(implode(' ',[$r['reference'],$r['destination'],$r['requester_name'],$r['purpose'],$r['plate']??'',$r['requested_role']??'',$r['preferred_personnel']??'',$r['request_kind']??'',$r['personnel_name']??'',$r['driver_name']??'',$r['office_name']??'']),$q)!==false)));
if($page==='requisitions'&&$q!==''){require __DIR__.'/tracking-view.php';return;}
page_heading($title,$description,new_button());
?><section class="panel"><form class="filter-bar" method="get"><input type="hidden" name="page" value="<?=e($page)?>"><div class="search-field"><?=icon('search',18)?><input aria-label="Search requisitions" name="q" value="<?=e($q)?>" placeholder="Search reference, destination, or requester…"></div><select name="status" aria-label="Filter status"><option value="">All statuses</option><?php foreach($statuses as $s):?><option <?=$s===$status?'selected':''?>><?=e($s)?></option><?php endforeach?></select><select name="office" aria-label="Filter office"><option value="">All offices</option><?php foreach($offices as $id=>$o):?><option value="<?=$id?>" <?=$office==$id?'selected':''?>><?=e($o)?></option><?php endforeach?></select><button class="btn">Filter</button><?php if($q||$status||$office):?><a class="text-link" href="index.php?page=<?=e($page)?>">Clear</a><?php endif?></form><?php request_table($filtered,false,$page==='approvals');?><div class="table-footer"><?=count($filtered)?> requisition<?=count($filtered)!==1?'s':''?></div></section>
