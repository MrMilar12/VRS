<?php
require __DIR__ . '/../includes/functions.php';
function check_destination(bool $ok, string $label): void
{
    if (!$ok) {
        throw new RuntimeException($label);
    }
    echo 'PASS: ' . $label . "\n";
}
if (($argv[1] ?? '') === '--render') {

    $r = ['destination' => "Baler, Aurora\nSan Luis, Aurora"];
    $config = [];
    $destinationSuggestions = true;
    ob_start();
    ?><!doctype html>
<html>
    <meta charset="utf-8" /><title>Destination controls</title
    ><link rel="stylesheet" href="../assets/css/style.css" />
    <form><?php require __DIR__ .
        '/../includes/destination-fields.php'; ?></form>
    <pre id="result">RUNNING</pre>
    <script>
        window.fetch=async()=>({ok:true,json:async()=>({features:[]})});
        window.addEventListener('load',()=>{
         try{
          const group=document.querySelector('[data-destinations]'),rows=group.querySelector('[data-destination-rows]'),add=group.querySelector('[data-destination-add]');
          const assert=(ok,message)=>{if(!ok)throw new Error(message);};
          assert(rows.children.length===2,'Existing destinations restored');
          add.click();assert(rows.children.length===3,'Add creates a third row');
          const input=rows.lastElementChild.querySelector('input');
          assert(rows.lastElementChild.querySelector('[data-address-picker]').dataset.addressReady==='true','New address picker initialized');
          input.value='Dingalan, Aurora';input.dispatchEvent(new Event('input'));
          const ids=Array.from(group.querySelectorAll('[id]'),node=>node.id);
          assert(new Set(ids).size===ids.length,'Unique IDs after adding');
          assert(JSON.stringify(new FormData(document.querySelector('form')).getAll('destinations[]'))===JSON.stringify(['Baler, Aurora','San Luis, Aurora','Dingalan, Aurora']),'All places submitted in order');
          rows.children[1].querySelector('[data-destination-remove]').click();
          assert(rows.children.length===2&&rows.lastElementChild.querySelector('label').textContent==='Place 2','Remove renumbers remaining stops');
          rows.children[1].querySelector('[data-destination-remove]').click();
          assert(rows.firstElementChild.querySelector('[data-destination-remove]').hidden,'Last destination cannot be removed');
          document.querySelector('#result').textContent='ALL PASSED';document.title='PASS';
         }catch(error){document.querySelector('#result').textContent='FAIL: '+error.message;document.title='FAIL';}
        });
    </script>
</html>
<?php
 $html = ob_get_clean();
 echo str_replace(
     ['../assets/', 'src="assets/'],
     [
         'file://' . realpath(__DIR__ . '/../assets') . '/',
         'src="file://' . realpath(__DIR__ . '/../assets') . '/',
     ],
     $html,
 );
 exit();

}
$_POST = ['destination' => 'Original place'];
check_destination(destination_value() === 'Original place', 'Legacy destination accepted');
$_POST = ['destinations' => [' First place, Aurora ', 'Second place', 'First place, Aurora']];
check_destination(
    destination_value() === "First place, Aurora\nSecond place\nFirst place, Aurora",
    'Visit order and return stops preserved',
);
check_destination(
    destination_inputs(['destination' => destination_value()]) === [
        'First place, Aurora',
        'Second place',
        'First place, Aurora',
    ],
    'Saved stops restored individually',
);
foreach (
    [
        [],
        [''],
        ['Valid', ''],
        [['nested']],
        array_fill(0, 21, 'Place'),
        [str_repeat('x', 256)],
        ['First' . "\n" . 'Second'],
    ]
    as $invalid
) {
    $_POST = ['destinations' => $invalid];
    $denied = false;
    try {
        destination_value();
    } catch (RuntimeException $e) {
        $denied = true;
    }
    check_destination($denied, 'Malformed destination list rejected');
}
$_POST = ['destinations' => [str_repeat('a', 200), str_repeat('b', 200)]];
check_destination(mb_strlen(destination_value()) === 401, 'Multiple full addresses supported');
echo "All destination checks passed.\n";
