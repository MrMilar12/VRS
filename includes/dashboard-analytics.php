<?php
function dashboard_analytics(array $vehicles,array $personnel,string $kind,string $period,string $now): array {
 $today=new DateTimeImmutable(substr($now,0,10));$start=$period==='all'?null:$today->modify('first day of this month')->modify('-'.((int)$period-1).' months');
 $result=['total'=>0,'vehicle'=>0,'personnel'=>0,'completed'=>0,'open'=>0,'statuses'=>[],'offices'=>[],'months'=>[]];
 foreach(['vehicle'=>$vehicles,'personnel'=>$personnel] as $type=>$records){
  if($kind!=='all'&&$kind!==$type)continue;
  foreach($records as $r){
   // Trends reflect when a request was created, not its scheduled service date.
   $created=substr($r['created_at'],0,10);if($created>$today->format('Y-m-d')||($start&&$created<$start->format('Y-m-d')))continue;
   $result['total']++;$result[$type]++;$status=$r['status'];
   if($status==='Completed')$result['completed']++;
   if(!in_array($status,['Completed','Cancelled','Rejected'],true))$result['open']++;
   $result['statuses'][$status]=($result['statuses'][$status]??0)+1;
   $office=$r['office_name'];$result['offices'][$office]=($result['offices'][$office]??0)+1;
   $month=substr($created,0,7);$result['months'][$month]??=['vehicle'=>0,'personnel'=>0];$result['months'][$month][$type]++;
  }
 }
 $first=$start?->format('Y-m');if(!$first&&$result['months'])$first=min(array_keys($result['months']));$first??=$today->format('Y-m');
 for($month=new DateTimeImmutable($first.'-01');$month<=$today;$month=$month->modify('+1 month'))$result['months'][$month->format('Y-m')]??=['vehicle'=>0,'personnel'=>0];
 ksort($result['months']);arsort($result['statuses']);arsort($result['offices']);return $result;
}
function analytics_bars(array $values,string $tone=''): void {
 $max=max(1,...array_values($values));
 if(!$values){echo '<p class="analytics-empty">No data in this selection.</p>';return;}
 foreach($values as $label=>$value):?>
 <div class="analytics-bar-row"><div><span><?=e($label)?></span><strong><?=$value?></strong></div><div class="analytics-track" aria-hidden="true"><span class="<?=e($tone)?>" style="width:<?=$value/$max*100?>%"></span></div></div>
 <?php endforeach;
}
