<?php
// Populated version of Requisition_Slip_for_Vehicle_Use.pdf (US Letter).
$passengerNames=array_values(array_filter(array_map('trim',preg_split('/[,\r\n]+/',$r['passengers']))));
$passengerRows=max(6,count($passengerNames));
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=e($r['reference'])?> · Requisition Slip for Vehicle Use</title>
<link rel="stylesheet" href="../assets/css/vehicle-slip.css?v=<?=filemtime(__DIR__.'/../assets/css/vehicle-slip.css')?>">
<script src="../assets/js/vendor/qrcodegen.js" defer></script>
<script src="../assets/js/slip-qr.js?v=<?=filemtime(__DIR__.'/../assets/js/slip-qr.js')?>" defer></script>
</head><body>
<div class="print-toolbar"><button type="button" data-print-slip disabled>Preparing QR code…</button><a href="<?=e(secure_record_url('../index.php?page=request&id='.$r['id']))?>">Back to requisition</a><span data-qr-status role="status"></span><noscript>Enable JavaScript to generate the slip QR code before printing.</noscript></div>
<main class="vehicle-slip">
<header class="slip-header"><h1>Requisition Slip for Vehicle Use</h1><p>Date of Request: <span class="date-value"><?=shortdate($r['created_at'],'F j, Y')?></span></p>
<figure class="slip-code"><div data-slip-qr="<?=e($r['reference'])?>"></div><figcaption><?=e($r['reference'])?></figcaption></figure></header>
<section class="personnel-section"><h2>Requesting Personnel</h2><table class="narrow-table"><colgroup><col class="label-column"><col></colgroup><tbody>
<?php foreach(['Name:'=>$r['requester_name'],'Designation/Position:'=>$r['position'],'Office/Division:'=>$r['office_name'],'Driver:'=>$r['driver_name']??''] as $label=>$value):?><tr><th scope="row"><?=e($label)?></th><td><?=e($value)?></td></tr><?php endforeach?>
<?php for($i=0;$i<$passengerRows;$i++):?><tr><th scope="row"><?=$i===0?'Passengers:':'<span aria-hidden="true">&nbsp;</span>'?></th><td><?=e($passengerNames[$i]??'')?></td></tr><?php endfor?>
</tbody></table></section>
<section><h2>Vehicle Requested</h2><table class="narrow-table"><colgroup><col class="label-column"><col></colgroup><tbody>
<?php foreach(['Type/Model:'=>trim($r['vehicle_type'].' / '.($r['model']??''),' /'),'Plate Number:'=>$r['plate']??'','Date & Time of Use:'=>shortdate($r['start_datetime'],'F j, Y · g:i A'),'Estimated Time of Return:'=>shortdate($r['end_datetime'],'F j, Y · g:i A')] as $label=>$value):?><tr><th scope="row"><?=e($label)?></th><td><?=e($value)?></td></tr><?php endforeach?>
<tr class="journey-row"><th scope="row">Destination/Purpose:</th><td><div><?=nl2br(e($r['destination']))?></div><div class="purpose-text"><?=nl2br(e($r['purpose']))?></div></td></tr>
</tbody></table></section>
<section class="fuel-section"><h2>Fuel Requirement</h2><p><span class="check-box <?=!empty($r['fuel_allocation'])?'checked':''?>" aria-label="<?=!empty($r['fuel_allocation'])?'Selected':'Not selected'?>"></span> With fuel allocation<?php if(!empty($r['fuel_allocation'])&&(float)$r['fuel_quantity']>0):?> <span class="fuel-detail">(<?=e((string)(float)$r['fuel_quantity'])?> liters requested)</span><?php endif?></p><p><span class="check-box <?=empty($r['fuel_allocation'])?'checked':''?>" aria-label="<?=empty($r['fuel_allocation'])?'Selected':'Not selected'?>"></span> Without fuel allocation</p><?php if(!empty($r['fuel_remarks'])):?><p class="fuel-detail">Remarks: <?=e($r['fuel_remarks'])?></p><?php endif?></section>
<section class="accountability"><h2>Accountability Acknowledgement</h2><p>I, the undersigned, acknowledge receipt and temporary use of the above-mentioned vehicle. I shall be responsible for its proper use, safekeeping, and timely return in good condition.</p><p class="requester-signature">Requesting Personnel: <strong><?=e($r['requester_name'])?></strong></p><p>Signature: <span class="write-line"></span><br>Date: <span class="write-line"></span></p></section>
<section class="approval-section"><h2>Approval</h2><table><tbody>
<?php foreach(['Supervisor'=>'Immediate Supervisor:','Administrative'=>'Administrative Officer In-Charge:'] as $stage=>$label):?><tr><th scope="row" class="approver-name"><?=e($label)?><?php if(!empty($sign[$stage]['full_name'])):?><span class="record-value"><?=e($sign[$stage]['full_name'])?></span><?php endif?></th><td class="approval-signature">Signature: <span class="signature-line"></span></td><td class="approval-date">Date: <?=isset($sign[$stage])?shortdate($sign[$stage]['created_at'],'m/d/Y'):''?></td></tr><?php endforeach?>
</tbody></table></section>
<section class="office-section"><h2>For Office Record</h2><table><colgroup><col class="office-label"><col></colgroup><tbody>
<?php foreach(['Date & Time Returned:'=>!empty($m['return_time'])?shortdate($m['return_time'],'F j, Y · g:i A'):'','Odometer Reading (Out/In):'=>$m?number_format($m['odometer_out']).' km / '.(isset($m['odometer_in'])?number_format($m['odometer_in']).' km':'__________'):'','Checked by:'=>$m['checked_name']??''] as $label=>$value):?><tr><th scope="row"><?=e($label)?></th><td><?=e($value)?></td></tr><?php endforeach?>
</tbody></table></section>
</main></body></html>
