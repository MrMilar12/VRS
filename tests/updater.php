<?php
require __DIR__.'/../includes/updater.php';
$root=sys_get_temp_dir().'/vrs-update-test-'.bin2hex(random_bytes(6));mkdir($root);mkdir($root.'/storage');mkdir($root.'/includes');mkdir($root.'/assets');mkdir($root.'/assets/js');mkdir($root.'/assets/uploads');mkdir($root.'/config');
function command_test(array $args): string {global $root;$p=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException($err);return trim($out);}
function git_test(array $args): string {return command_test(['git',...$args]);}
function check_update(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label."\n";}
function cache_update(string $target): void {global $root;file_put_contents($root.'/storage/update-check.json',json_encode(['latest'=>$target,'checked_epoch'=>time(),'repository'=>'MrMilar12/VRS','branch'=>'main','checked_at'=>date('Y-m-d H:i:s')]));git_test(['update-ref','refs/remotes/origin/main',$target]);}
function rejected_update(callable $call,string $label,string $contains): void {$failed=false;try{$call();}catch(RuntimeException $e){$failed=str_contains($e->getMessage(),$contains);if(!$failed)throw $e;}check_update($failed,$label);}
try{
 git_test(['init','-b','main']);git_test(['config','user.name','Update Test']);git_test(['config','user.email','update@example.test']);git_test(['remote','add','origin','https://github.com/MrMilar12/VRS.git']);
 file_put_contents($root.'/.gitignore',"/storage/*\n/config/local.php\n/assets/uploads/*\n");
 foreach(['index.php','api.php','actions.php','includes/bootstrap.php','includes/updater.php','includes/developer-view.php'] as $file)file_put_contents($root.'/'.$file,"<?php // Original fixture\n");
 file_put_contents($root.'/assets/js/developer.js','// fixture');file_put_contents($root.'/obsolete.txt','old');
 git_test(['add','.']);git_test(['commit','-m','Initial deployment']);$old=git_test(['rev-parse','HEAD']);
 file_put_contents($root.'/index.php',"<?php // Updated fixture\n");file_put_contents($root.'/added.php',"<?php // New file\n");unlink($root.'/obsolete.txt');git_test(['add','-A']);git_test(['commit','-m','New release']);$target=git_test(['rev-parse','HEAD']);git_test(['reset','--hard',$old]);
 file_put_contents($root.'/config/local.php','PRIVATE CONFIG');file_put_contents($root.'/storage/demo.sqlite','DATABASE');file_put_contents($root.'/storage/auth.key','KEY');file_put_contents($root.'/assets/uploads/photo.jpg','PHOTO');cache_update($target);
 $updater=new VrsUpdater($root,['update_repository'=>'MrMilar12/VRS','update_branch'=>'main','update_php_binary'=>PHP_BINARY]);
 $state=$updater->check();check_update($state['can_apply']&&$state['change_count']===3,'Preview finds added, modified and removed files');
 rejected_update(fn()=>$updater->apply(str_repeat('a',40),$target),'Stale installed-version preview rejected','installed version changed');
 $lock=fopen($root.'/storage/update.lock','c');flock($lock,LOCK_EX);rejected_update(fn()=>$updater->apply($old,$target),'Concurrent deployments blocked','Another update');flock($lock,LOCK_UN);fclose($lock);
 file_put_contents($root.'/index.php','LOCAL EDIT');rejected_update(fn()=>$updater->apply($old,$target),'Local changes cannot be overwritten','Local changes');git_test(['restore','index.php']);
 $result=$updater->apply($old,$target);check_update($result['success']&&str_contains(file_get_contents($root.'/index.php'),'Updated')&&is_file($root.'/added.php')&&!is_file($root.'/obsolete.txt'),'Update replaces old code, adds and removes files');
 check_update(file_get_contents($root.'/config/local.php')==='PRIVATE CONFIG'&&file_get_contents($root.'/storage/demo.sqlite')==='DATABASE'&&file_get_contents($root.'/storage/auth.key')==='KEY'&&file_get_contents($root.'/assets/uploads/photo.jpg')==='PHOTO','Configuration, database, authenticator key and uploads preserved');
 check_update(!is_file($root.'/storage/update-maintenance.json'),'Maintenance ends after update');
 $updater->apply($target,$old,true);check_update(git_test(['rev-parse','HEAD'])===$old&&is_file($root.'/obsolete.txt')&&!is_file($root.'/added.php'),'Rollback restores previous code');
 file_put_contents($root.'/index.php','<?php syntax error!');git_test(['add','index.php']);git_test(['commit','-m','Broken PHP']);$broken=git_test(['rev-parse','HEAD']);git_test(['reset','--hard',$old]);cache_update($broken);rejected_update(fn()=>$updater->apply($old,$broken),'Invalid PHP rejected before replacement','PHP validation failed');
 file_put_contents($root.'/config/local.php','REMOTE CONFIG');git_test(['add','-f','config/local.php']);git_test(['commit','-m','Bad runtime file']);$unsafe=git_test(['rev-parse','HEAD']);git_test(['reset','--hard',$old]);cache_update($unsafe);check_update(!$updater->check()['can_apply'],'Remote commits tracking runtime data blocked');
 check_update(git_test(['rev-parse','HEAD'])===$old,'Rejected updates leave installed commit unchanged');
 echo "All updater checks passed in a disposable repository.\n";
}finally{
 $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($files as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
}
