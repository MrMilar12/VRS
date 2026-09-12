<?php
require_once __DIR__.'/booking-assistant.php';
$connected=booking_configured();$state=$_SESSION['booking_chat']??[];
if(($state['expires']??0)<time()){$state=[];unset($_SESSION['booking_chat']);}
$draft=$state['draft']??[];$showReview=!empty($state['show_review']);
$old=$_SESSION['old_input']??[];if(($old['action']??'')==='save_request'&&($old['return_to']??'')==='index.php?page=assistant'){$showReview=true;$draft=array_replace($draft,$old);unset($_SESSION['old_input']);}
page_heading('Booking assistant','Plan official travel, with a little help.','<a class="btn" href="index.php?page=create">Create request manually</a>');
?>
<div class="booking-layout <?=$showReview?'':'booking-chat-only'?>" data-booking-assistant>
 <section class="panel booking-chat" aria-labelledby="booking-chat-title">
  <div class="panel-heading assistant-chat-heading"><div class="assistant-identity"><span class="assistant-symbol" aria-hidden="true"><?=icon('car',22)?></span><div><h2 id="booking-chat-title">Your travel workspace</h2><p>VRS booking assistant</p></div></div><button class="btn small assistant-reset" type="button" data-booking-reset>New chat</button></div>
  <?php if(!$connected):?><div class="booking-notice" role="status"><strong>AI booking is not connected yet</strong><p><?=e(booking_configuration_error())?></p><p>Use the regular request form while your administrator completes the connection.</p></div><?php endif?>
  <div class="assistant-welcome" data-assistant-welcome <?=empty($state['messages'])?'':'hidden'?>>
   <span class="assistant-welcome-mark" aria-hidden="true"><?=icon('car',28)?></span>
   <span class="assistant-eyebrow">LET’S PLAN YOUR NEXT TRIP</span>
   <h2>Where are you<br>headed next?</h2>
   <p>Describe your trip in your own words.<br>I’ll help turn the details into a request.</p>
   <div class="assistant-prompts" aria-label="Suggested questions">
    <?php foreach([['car','Find a vehicle','See what’s available','Which vehicles are available now?'],['users','Find a driver','Check driver availability','Which drivers are available now?'],['file','Plan my trip','Prepare a vehicle request','Help me book a vehicle.']] as [$ico,$title,$caption,$prompt]):?><button type="button" data-assistant-prompt="<?=e($prompt)?>" <?=$connected?'':'disabled'?>><span class="assistant-prompt-icon"><?=icon($ico,20)?></span><strong><?=e($title)?></strong><small><?=e($caption)?></small></button><?php endforeach?>
   </div>
  </div>
  <div class="booking-messages" role="log" aria-label="Booking conversation" aria-live="polite" data-booking-messages <?=empty($state['messages'])?'hidden':''?>>
   <?php foreach($state['messages']??[] as $message):?><div class="booking-message <?=$message['role']==='user'?'user':'assistant'?>"><strong><?=$message['role']==='user'?'You':'VRS assistant'?></strong><p><?=e($message['content'])?></p></div><?php endforeach?>
  </div>
  <div class="assistant-thinking" data-assistant-thinking hidden aria-hidden="true"><span class="assistant-thinking-dots"><i></i><i></i><i></i></span><span>Thinking through the details</span></div>
  <form class="booking-composer" data-booking-chat-form><?=csrf()?><label for="booking-message">Tell me about your trip</label><textarea id="booking-message" name="message" rows="2" maxlength="3000" required <?=$connected?'':'disabled'?> placeholder="e.g. We need a van to Baler tomorrow, 8 AM–5 PM, for a planning meeting."></textarea><div><span><?=icon('clock',14)?> <?=e($config['timezone'])?></span><button type="submit" class="btn primary" <?=$connected?'':'disabled'?>>Send message</button></div><p role="status" data-booking-status></p></form>
 <section class="assistant-availability" data-availability-panel hidden aria-labelledby="availability-heading"><span class="assistant-eyebrow">FLEET AT A GLANCE</span><h3 id="availability-heading">Your availability results</h3><p data-availability-summary></p><button type="button" class="btn small" data-availability-refresh>Refresh availability</button><p class="field-hint" data-availability-checked role="status"></p><div data-availability-items></div></section>
 </section>
 <aside class="assistant-guide" aria-labelledby="assistant-guide-title">
  <span class="assistant-eyebrow">FROM PLAN TO REQUEST</span><h2 id="assistant-guide-title">A smoother start<br>to your journey.</h2><p>A few details here. A request ready for review.</p>
  <ol class="assistant-steps"><li><span>01</span><div><h3>Tell me the plan</h3><p>Share where you’re going, when, and who’s coming.</p></div></li><li><span>02</span><div><h3>Review the details</h3><p>Check your trip and make any changes in the form.</p></div></li><li><span>03</span><div><h3>Send for approval</h3><p>Your administrator reviews the request and assigns a vehicle and driver.</p></div></li></ol>
  <div class="assistant-tip"><span aria-hidden="true"><?=icon('file',20)?></span><div><strong>Start with what you know</strong><p>You can write in English or Filipino. I’ll ask for anything that’s missing.</p></div></div>
  <p class="assistant-guide-note">Nothing is submitted until you review and send your request.</p>
 </aside>
 <section class="panel booking-review" aria-labelledby="booking-review-title" data-booking-review-panel <?=$showReview?'':'hidden'?>><div class="panel-heading"><div><span class="assistant-eyebrow">REVIEW &amp; SUBMIT</span><h2 id="booking-review-title">Your trip details</h2><p>Make sure everything is correct before sending for approval.</p></div></div>
 <form action="actions.php" method="post" class="form-body stack" data-booking-review><?=csrf()?><input type="hidden" name="action" value="save_request"><input type="hidden" name="return_to" value="index.php?page=assistant">
 <label>Destination<input name="destination" required maxlength="255" value="<?=e($draft['destination']??'')?>" placeholder="Full destination or address"></label>
 <div class="form-grid"><label>Departure<input type="datetime-local" name="start_datetime" required value="<?=e($draft['start_datetime']??'')?>"></label><label>Estimated return<input type="datetime-local" name="end_datetime" required value="<?=e($draft['end_datetime']??'')?>"></label></div>
 <label>Vehicle type<select name="vehicle_type" required><option value="">Choose a vehicle type</option><?php foreach(['Van','MPV','SUV','Pickup','Sedan','Bus'] as $type):?><option <?=($draft['vehicle_type']??'')===$type?'selected':''?>><?=$type?></option><?php endforeach?></select></label>
 <label>Passengers<textarea name="passengers" required rows="2" maxlength="5000" placeholder="Full names, separated by commas"><?=e($draft['passengers']??'')?></textarea></label>
 <label>Purpose of travel<textarea name="purpose" required rows="2" maxlength="5000"><?=e($draft['purpose']??'')?></textarea></label>
 <label>Preferred driver <span class="field-hint">Optional</span><input name="preferred_driver" maxlength="160" value="<?=e($draft['preferred_driver']??'')?>"></label>
 <details><summary>Fuel requirements</summary><label class="checkbox-label"><input name="fuel_allocation" type="checkbox" value="1" <?=!empty($draft['fuel_allocation'])?'checked':''?>> Request fuel allocation</label><label>Quantity (liters)<input type="number" name="fuel_quantity" min="0" max="10000" step="0.01" value="<?=e($draft['fuel_quantity']??0)?>"></label><label>Fuel remarks<input name="fuel_remarks" maxlength="1000" value="<?=e($draft['fuel_remarks']??'')?>"></label></details>
 <p class="booking-review-status" data-booking-review-status>Review all details. A vehicle is reserved only after administrator approval.</p><button class="btn primary" type="submit" name="submit_mode" value="submit">Submit request for approval</button>
 </form></section>
</div>
<script src="assets/js/booking-assistant.js?v=<?=filemtime(__DIR__.'/../assets/js/booking-assistant.js')?>" defer></script>
