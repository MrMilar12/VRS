<?php
// Isolated rendering checks; no application database or authentication changes.
require __DIR__.'/../includes/functions.php';
$r=['id'=>1,'reference'=>'VR-2026-0001','created_at'=>'2026-09-09 08:00:00','requester_name'=>'Daniel Milar','position'=>'Administrative Assistant','office_name'=>'General Services Office','driver_name'=>'Juan Dela Cruz','passengers'=>'Maria Santos, Pedro Reyes','vehicle_type'=>'Van','model'=>'Toyota Hiace','plate'=>'SAA 1234','start_datetime'=>'2026-09-10 08:00:00','end_datetime'=>'2026-09-10 17:00:00','destination'=>'Quezon City Hall, Metro Manila, Philippines','purpose'=>'Attend the inter-agency coordination meeting.','fuel_allocation'=>1,'fuel_quantity'=>20,'fuel_remarks'=>'Official travel'];
$sign=['Supervisor'=>['full_name'=>'Maria Supervisor','created_at'=>'2026-09-09 09:00:00'],'Administrative'=>['full_name'=>'Jose Administrator','created_at'=>'2026-09-09 10:00:00']];
$m=null;
function render_slip(): string {global $r,$sign,$m;ob_start();require __DIR__.'/../includes/print-slip.php';return ob_get_clean();}
$html=render_slip();
if(($argv[1]??'')==='--render'){echo str_replace('../assets/','file://'.realpath(__DIR__.'/../assets').'/', $html);exit;}
function check_slip(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
check_slip(str_contains($html,'Requisition Slip for Vehicle Use')&&str_contains($html,'Accountability Acknowledgement'),'Source form title and acknowledgement retained');
check_slip(str_contains($html,'data-slip-qr="VR-2026-0001"')&&str_contains($html,'<figcaption>VR-2026-0001</figcaption>'),'QR payload and visible slip number match');
check_slip(str_contains($html,'Maria Supervisor')&&str_contains($html,'Jose Administrator'),'Recorded approvers populated');
check_slip(str_contains($html,'Quezon City Hall')&&str_contains($html,'Pedro Reyes'),'Journey and passengers populated');
$r['destination']='<script>alert(1)</script>'; $r['fuel_allocation']=0;
$m=['return_time'=>'2026-09-10 16:30:00','odometer_out'=>12000,'odometer_in'=>12120,'checked_name'=>'Dispatch Officer'];
$html=render_slip();
check_slip(str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>alert'),'Entered values escaped');
check_slip(str_contains($html,'12,000 km / 12,120 km')&&str_contains($html,'Dispatch Officer'),'Return record populated');
check_slip(str_contains($html,'class="check-box checked" aria-label="Selected"></span> Without fuel allocation'),'Fuel checkbox reflects saved allocation');
