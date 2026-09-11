<?php
require __DIR__.'/../includes/updater.php';
$root=sys_get_temp_dir().'/vrs-archive-test-'.bin2hex(random_bytes(6));mkdir($root);mkdir($root.'/storage');
function expect(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function reject(callable $call,string $part): void {try{$call();}catch(RuntimeException $e){if(str_contains($e->getMessage(),$part)){echo "PASS: rejects $part\n";return;}throw $e;}throw new RuntimeException('Expected rejection: '.$part);}
function archive_test(array $files): string {global $root;$p=$root.'/fixture.zip';$z=new ZipArchive();$z->open($p,ZipArchive::CREATE|ZipArchive::OVERWRITE);foreach($files as $name=>$data)$z->addFromString('VRS-release/'.$name,$data);$z->close();$data=file_get_contents($p);unlink($p);return $data;}
try{
 $old=str_repeat('a',40);$new=str_repeat('b',40);file_put_contents($root.'/storage/update-installed.json',json_encode(['commit'=>$old,'files'=>['obsolete.txt'=>hash('sha256','old')]]));file_put_contents($root.'/obsolete.txt','old');
 $files=[];foreach(['index.php','api.php','actions.php','includes/bootstrap.php','includes/updater.php','includes/developer-view.php'] as $p){$files[$p]="<?php // new\n";if(!is_dir(dirname($root.'/'.$p)))mkdir(dirname($root.'/'.$p),0755,true);file_put_contents($root.'/'.$p,"<?php // local edit\n");}$files['assets/js/developer.js']='// new';$files['added.txt']='added';
 mkdir($root.'/config');mkdir($root.'/assets/uploads',0755,true);file_put_contents($root.'/config/local.php','SECRET');file_put_contents($root.'/storage/db.sqlite','DATABASE');file_put_contents($root.'/assets/uploads/photo','PHOTO');
 $files['config/local.php']='BAD';$files['storage/db.sqlite']='BAD';$files['assets/uploads/photo']='BAD';$files['.git/config']='BAD';
 $u=new VrsUpdater($root,['update_php_binary'=>PHP_BINARY]);$stage=new ReflectionMethod($u,'stage');$s=$stage->invoke($u,$new,archive_test($files));expect(count($s['changes'])===9,'Preview includes additions, modifications and tracked deletion');
 reject(fn()=>$u->apply(str_repeat('c',40),$new),'installed version changed');
 $lock=fopen($root.'/storage/update.lock','c');flock($lock,LOCK_EX);reject(fn()=>$u->apply($old,$new),'Another update');flock($lock,LOCK_UN);fclose($lock);
 file_put_contents($root.'/index.php','changed after preview');reject(fn()=>$u->apply($old,$new),'Local files changed');file_put_contents($root.'/index.php',"<?php // local edit\n");
 $u->apply($old,$new);expect(file_get_contents($root.'/index.php')===$files['index.php']&&is_file($root.'/added.txt')&&!is_file($root.'/obsolete.txt'),'Downloaded patch overwrites code, adds files and removes previously deployed files');
 expect(file_get_contents($root.'/config/local.php')==='SECRET'&&file_get_contents($root.'/storage/db.sqlite')==='DATABASE'&&file_get_contents($root.'/assets/uploads/photo')==='PHOTO'&&!is_dir($root.'/.git'),'Runtime files and Git metadata protected');
 $u->apply($new,$old,true);expect(file_get_contents($root.'/index.php')==="<?php // local edit\n"&&!is_file($root.'/added.txt')&&file_get_contents($root.'/obsolete.txt')==='old','Rollback restores exact local edits and deleted files');expect(!is_file($root.'/storage/update-maintenance.json'),'Maintenance marker removed');
 $bad=$files;$bad['index.php']='<?php syntax error!';reject(fn()=>$stage->invoke($u,$new,archive_test($bad)),'PHP validation failed');$bad=$files;$bad['../escape']='bad';reject(fn()=>$stage->invoke($u,$new,archive_test($bad)),'Unsafe archive path');
 $bad=$files;$bad['INDEX.php']='<?php';reject(fn()=>$stage->invoke($u,$new,archive_test($bad)),'duplicate');
 symlink($root.'/config/local.php',$root.'/added.txt');reject(fn()=>$stage->invoke($u,$new,archive_test($files)),'symbolic link');unlink($root.'/added.txt');
 echo "All archive updater tests passed.\n";
}finally{$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){if($f->isLink()||!$f->isDir())unlink($f->getPathname());else rmdir($f->getPathname());}rmdir($root);}
