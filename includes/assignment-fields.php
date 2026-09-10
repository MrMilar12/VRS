<?php
$unavailable=[];
foreach(['vehicle'=>'vehicles','driver'=>'drivers'] as $kind=>$table):
$records=all('SELECT * FROM '.$table.' ORDER BY '.($kind==='vehicle'?'model':'full_name'));
?><label>Assign <?=$kind?><select name="<?=$kind?>_id" data-assignment-resource><option value="">Select <?=$kind?></option>
<?php foreach($records as $record):
$hard=$kind==='vehicle'?vehicle_assignment_issues($record,$r):driver_assignment_issues($record,$r);
$bookings=conflicts($kind==='vehicle'?(int)$record['id']:0,$kind==='driver'?(int)$record['id']:0,$r['start_datetime'],$r['end_datetime'],(int)$r['id']);
$issues=[...$hard,...$bookings];$name=$kind==='vehicle'?$record['model'].' · '.$record['plate'].' · '.$record['capacity'].' seats':$record['full_name'];
if($issues)$unavailable[]=['name'=>$name,'reason'=>implode(' ',$issues)];
?><option value="<?=$record['id']?>" <?=$issues?'disabled':''?> data-overridable="<?=!$hard&&$bookings?'true':'false'?>" title="<?=e(implode(' ',$issues))?>"><?=e($name.($hard?' — Unavailable':($bookings?' — Already scheduled':' — Available')))?></option><?php endforeach?>
</select></label><?php endforeach?>
<p class="field-hint">Availability is checked for this trip’s dates and times, including the <?=e(setting('turnaround_minutes','30'))?> minute turnaround buffer. Booked resources cannot be assigned through normal approval.</p>
<?php if($unavailable):?><details class="assignment-conflicts"><summary>Why are some vehicles or drivers unavailable?</summary><ul><?php foreach($unavailable as $item):?><li><strong><?=e($item['name'])?></strong><p><?=e($item['reason'])?></p></li><?php endforeach?></ul></details><?php endif?>
<button type="button" class="btn" data-check-availability="<?=$r['id']?>">Check availability</button><div id="availability-result" class="muted" role="status" aria-live="polite">Select a vehicle and driver to check this schedule.</div>
<?php if(is_role('Administrator')):?><button type="button" class="btn danger" data-enable-override aria-expanded="false" aria-controls="schedule-override">Override schedule conflict</button><div id="schedule-override" class="assignment-override" hidden><p>Override approval permits a conflicting booking. Review the existing schedule and record why this exception is needed.</p><label>Override reason<textarea name="override_reason" rows="3" maxlength="1000" placeholder="Explain why this conflicting assignment is necessary" disabled></textarea><span class="field-hint">At least 15 characters. Your name, the conflict, and your reason will be recorded.</span></label></div><noscript><p class="field-hint">Enable JavaScript to use the administrator override controls.</p></noscript><?php endif?>
<script src="assets/js/assignment.js?v=<?=filemtime(__DIR__.'/../assets/js/assignment.js')?>" defer></script>
