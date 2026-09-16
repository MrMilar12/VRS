<?php
$places=destination_inputs($r??[]);$destinationSuggestions=$destinationSuggestions??false;
$renderPlace=function(string $place,string $index)use($destinationSuggestions,$config): void {
 $key='destination-'.$index;
?><div class="destination-row" data-destination-row><div class="destination-input" <?=$destinationSuggestions?'data-address-picker':''?> data-search-url="<?=e($config['address_search_url']??'https://photon.komoot.io/api/')?>">
<label for="<?=e($key)?>" data-destination-label>Place <?=ctype_digit($index)?(int)$index+1:''?></label>
<?php if($destinationSuggestions):?><div class="place-combobox"><div class="place-control"><?php endif?>
<input id="<?=e($key)?>" name="destinations[]" type="text" required maxlength="255" autocomplete="off" placeholder="Enter a place or address" value="<?=e($place)?>" <?=$destinationSuggestions?'role="combobox" aria-autocomplete="list" aria-expanded="false"':''?> aria-controls="<?=e($key)?>-options" aria-describedby="<?=e($key)?>-status">
<?php if($destinationSuggestions):?><button type="button" class="icon-button" data-place-toggle aria-label="Show places" aria-controls="<?=e($key)?>-options" aria-expanded="false">⌄</button></div><div id="<?=e($key)?>-options" role="listbox" aria-label="Places" hidden></div></div><?php endif?>
<p id="<?=e($key)?>-status" class="field-hint" role="status" aria-live="polite"><?=$destinationSuggestions?'Type at least 3 characters for suggestions, or enter the address yourself.':''?></p>
<?php if($destinationSuggestions):?><details class="destination-preview"><summary>Map preview</summary><div class="destination-map"><div data-map-placeholder><?=icon('office',25)?><span>Select a suggested place to show its location.</span></div><iframe data-address-map hidden title="Destination map" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div><div class="address-credit"><span>Search by Photon · © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a></span><a data-map-link hidden target="_blank" rel="noopener noreferrer">Open map ↗</a></div></details><?php endif?>
</div><button type="button" class="btn small" data-destination-remove hidden>Remove</button></div><?php
};
?>
<div class="span-2 destinations-field" data-destinations><p class="destinations-title">Places / destinations</p><p class="field-hint">Enter each place in visit order. Use Add place for another stop.</p><div class="stack" data-destination-rows><?php foreach($places as $index=>$place)$renderPlace($place,(string)$index);?></div><button type="button" class="btn" data-destination-add hidden>Add place</button><template data-destination-template><?php $renderPlace('','__INDEX__');?></template><noscript><p class="field-hint">Enable JavaScript to add or remove places.</p></noscript></div>
<?php if($destinationSuggestions):?><script src="assets/js/address-picker.js?v=<?=filemtime(__DIR__.'/../assets/js/address-picker.js')?>" defer></script><?php endif?>
<script src="assets/js/destinations.js?v=<?=filemtime(__DIR__.'/../assets/js/destinations.js')?>" defer></script>
