<?php
$now=date('Y-m-d H:i:s');
$starting=$r['status']==='Approved';
$canStart=$now>=$r['start_datetime']&&$now<$r['end_datetime'];
$old=$_SESSION['old_input']??[];$progressRemarks='';
if(in_array($old['action']??'',['personnel_start','personnel_complete'],true)&&(int)($old['id']??0)===(int)$r['id']){
 $progressRemarks=is_string($old['remarks']??null)?$old['remarks']:'';unset($_SESSION['old_input']);
}
?>
<section class="panel"><div class="panel-heading"><h2>Assignment progress</h2><?=badge($r['status'])?></div>
<div class="form-body stack">
<dl class="progress-schedule"><div><dt>Scheduled start</dt><dd><?=shortdate($r['start_datetime'],'M j, Y · g:i A')?></dd></div><div><dt>Scheduled end</dt><dd><?=shortdate($r['end_datetime'],'M j, Y · g:i A')?></dd></div><?php if($r['actual_start']):?><div><dt>Started</dt><dd><?=shortdate($r['actual_start'],'M j, Y · g:i A')?></dd></div><?php endif?><?php if($r['actual_end']):?><div><dt>Completed</dt><dd><?=shortdate($r['actual_end'],'M j, Y · g:i A')?></dd></div><?php endif?></dl>
<?php if($r['status']==='Completed'):?><p class="field-hint">This assignment is complete. Remarks are saved in Requisition history.</p>
<?php else:?>
<?php if($starting&&!$canStart):?><p class="alert" id="progress-guidance"><?=$now<$r['start_datetime']?'This assignment has not started yet. You can start it at the scheduled time. Refresh this page then.':'The scheduled end has passed. This assignment can no longer be started; submit a new requisition for a revised schedule.'?></p><?php else:?><p class="field-hint" id="progress-guidance"><?=$starting?'Start records the current time for all assigned personnel. Availability is checked again.':'Complete records the current time and releases all assigned personnel.'?></p><?php endif?>
<p class="field-hint">Current time: <?=shortdate($now,'M j, Y · g:i A')?> (<?=e($config['timezone'])?>)</p>
<form class="stack" method="post" action="actions.php" data-confirm="<?=$starting?'Start this assignment for all assigned personnel?':'Mark this assignment complete for all assigned personnel?'?>">
<?=csrf()?><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="action" value="<?=$starting?'personnel_start':'personnel_complete'?>"><input type="hidden" name="return_to" value="index.php?page=personnel-request&id=<?=$r['id']?>">
<label>Remarks (optional)<textarea name="remarks" maxlength="2000" rows="3" placeholder="<?=$starting?'Add a note about the start of the assignment':'Add completion notes or the work accomplished'?>"><?=e($progressRemarks)?></textarea></label>
<button type="submit" class="btn primary" aria-describedby="progress-guidance" <?=$starting&&!$canStart?'disabled':''?>><?=$starting?'Start assignment':'Complete assignment'?></button>
</form>
<?php endif?></div></section>
