<?php
/** Git deployments are restricted to one configured GitHub repository and branch. */
final class VrsUpdater {
 private string $root;private array $config;private string $directory;
 public function __construct(string $root,array $config){$this->root=rtrim($root,'/');$this->config=$config;$this->directory=$this->root.'/storage';}
 private function command(array $args,int $timeout=30): array {
  if(!function_exists('proc_open'))throw new RuntimeException('PHP proc_open is disabled. Enable it for developer updates.');
  $pipes=[];$process=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$this->root,array_replace(getenv(),['GIT_TERMINAL_PROMPT'=>'0','GIT_OPTIONAL_LOCKS'=>'0','GCM_INTERACTIVE'=>'never']));
  if(!is_resource($process))throw new RuntimeException('Unable to start the update command. Check server permissions.');
  fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$out='';$err='';$start=microtime(true);$exit=-1;
  try{do{$out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);$status=proc_get_status($process);if(!$status['running']){$exit=$status['exitcode'];break;}if(strlen($out)+strlen($err)>8000000||microtime(true)-$start>$timeout){proc_terminate($process,9);throw new RuntimeException('Update command exceeded its limit. Check connectivity and server resources.');}usleep(20000);}while(true);
   $out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);
  }finally{fclose($pipes[1]);fclose($pipes[2]);proc_close($process);}
  return ['code'=>$exit,'out'=>$out,'err'=>$err];
 }
 private function git(array $args,bool $required=true): string {
  $r=$this->command([$this->config['update_git_binary']??'git','-c','core.hooksPath=/dev/null','-c','protocol.ext.allow=never','-c','protocol.file.allow=never',...$args],60);
  if($required&&$r['code']!==0)throw new RuntimeException('Git could not complete '.($args[0]??'the command').'. Check repository access, Git ownership, and filesystem permissions.');
  return $required?rtrim($r['out'],"\r\n"):($r['code']===0?'yes':'no');
 }
 private function save(string $name,array $data): void {
  $temporary=tempnam($this->directory,'update-');if($temporary===false)throw new RuntimeException('Update storage is not writable.');
  try{if(file_put_contents($temporary,json_encode($data,JSON_THROW_ON_ERROR),LOCK_EX)===false||!rename($temporary,$this->directory.'/'.$name))throw new RuntimeException('Unable to save update state.');}finally{if(is_file($temporary))unlink($temporary);}
 }
 private function read(string $name): array {$data=@file_get_contents($this->directory.'/'.$name);return $data===false?[]:(json_decode($data,true)?:[]);}
 private function lock(){if(!is_dir($this->directory)||!is_writable($this->directory))throw new RuntimeException('The storage directory must be writable.');$lock=fopen($this->directory.'/update.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if(is_resource($lock))fclose($lock);throw new RuntimeException('Another update operation is running. Try again shortly.');}return $lock;}
 private function identity(): array {
  $repo=$this->config['update_repository']??'MrMilar12/VRS';$branch=$this->config['update_branch']??'main';
  if(!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D',$repo)||!preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]*$~D',$branch)||str_contains($branch,'..'))throw new RuntimeException('Invalid update repository or branch.');
  if(realpath($this->git(['rev-parse','--show-toplevel']))!==realpath($this->root))throw new RuntimeException('VRS must be installed at the root of its Git checkout.');
  $remote=$this->git(['remote','get-url','origin']);
  if(!in_array($remote,['https://github.com/'.$repo.'.git','https://github.com/'.$repo,'git@github.com:'.$repo.'.git']))throw new RuntimeException('The origin remote does not match the configured GitHub repository.');
  return [$repo,$branch];
 }
 private function protectedPath(string $path): bool {
  if(in_array($path,['storage/.htaccess','assets/uploads/.htaccess']))return false;
  return in_array($path,['storage','assets/uploads'])||$path==='config/local.php'||$path==='.env'||str_starts_with($path,'.env.')||str_starts_with($path,'storage/')||str_starts_with($path,'assets/uploads/');
 }
 private function targetSafe(string $target,bool $lint): void {
  $tree=$this->git(['ls-tree','-rz',$target]);$names=[];
  foreach(explode("\0",$tree) as $entry){if($entry==='')continue;[$meta,$name]=explode("\t",$entry,2);$names[]=$name;if($this->protectedPath($name))throw new RuntimeException('The update tracks a protected runtime file: '.$name.'. Remove it from Git before updating.');if(str_starts_with($meta,'120000')||str_starts_with($meta,'160000'))throw new RuntimeException('Updates containing symlinks or submodules require manual deployment.');}
  foreach(['index.php','api.php','actions.php','includes/bootstrap.php','includes/updater.php','includes/developer-view.php','assets/js/developer.js'] as $required)if(!in_array($required,$names))throw new RuntimeException('This version is missing an updater or application file. Publish the developer page before using web updates.');
  if(!$lint)return;
  foreach($names as $name)if(str_ends_with($name,'.php')){
   $source=$this->git(['show',$target.':'.$name]);$file=tempnam($this->directory,'lint-');if($file===false)throw new RuntimeException('Unable to stage PHP validation.');
   try{file_put_contents($file,$source);$check=$this->command([$this->config['update_php_binary']??PHP_BINDIR.'/php','-l',$file]);if($check['code']!==0)throw new RuntimeException('PHP validation failed for '.$name.'. Update was not installed.');}finally{unlink($file);}
  }
 }
 private function status(string $repo,string $branch): array {
  $head=$this->git(['rev-parse','HEAD']);$target=$this->git(['rev-parse','refs/remotes/origin/'.$branch]);
  $dirty=$this->git(['status','--porcelain','--untracked-files=all'])!=='';$current=$this->git(['branch','--show-current']);
  $ahead=$this->git(['merge-base','--is-ancestor',$head,$target],false)==='yes';$blocked=[];
  if($dirty)$blocked[]='Uncommitted or untracked files exist. Commit and push your code before applying updates.';
  if($current!==$branch)$blocked[]='Switch this checkout to '.$branch.' before updating.';
  if($head!==$target&&!$ahead)$blocked[]='Local and remote histories differ, or local commits have not been pushed. Resolve them manually.';
  if($head!==$target&&$ahead)try{$this->targetSafe($target,false);}catch(RuntimeException $e){$blocked[]=$e->getMessage();}
  $changes=$head===$target?[]:array_values(array_filter(explode("\n",$this->git(['diff','--name-status','--no-renames',$head,$target,'--']))));
  $deployment=$this->read('update-deployment.json');
  return ['repository'=>$repo,'branch'=>$branch,'current'=>$head,'latest'=>$target,'summary'=>$this->git(['log','-1','--format=%s',$target]),'checked_at'=>date('Y-m-d H:i:s'),'checked_epoch'=>time(),'available'=>$head!==$target&&$ahead,'blocked'=>$blocked,'can_apply'=>$head!==$target&&$ahead&&!$blocked,'changes'=>array_slice($changes,0,300),'change_count'=>count($changes),'rollback'=>($deployment['installed']??'')===$head&&!$dirty?($deployment['previous']??null):null];
 }
 public function check(): array {
  $lock=$this->lock();try{[$repo,$branch]=$this->identity();$cache=$this->read('update-check.json');
   if(($cache['checked_epoch']??0)<time()-60||($cache['repository']??'')!==$repo||($cache['branch']??'')!==$branch){$this->git(['fetch','--no-tags','--no-recurse-submodules','origin','+refs/heads/'.$branch.':refs/remotes/origin/'.$branch]);$cache=$this->status($repo,$branch);$this->save('update-check.json',$cache);return $cache;}
   $fresh=$this->status($repo,$branch);$fresh['checked_at']=$cache['checked_at'];$fresh['checked_epoch']=$cache['checked_epoch'];return $fresh;
  }finally{flock($lock,LOCK_UN);fclose($lock);}
 }
 public function apply(string $expectedHead,string $target,bool $rollback=false): array {
  if(!preg_match('/^[a-f0-9]{40}$/D',$expectedHead)||!preg_match('/^[a-f0-9]{40}$/D',$target))throw new RuntimeException('Check for updates again before applying.');
  $lock=$this->lock();$maintenance=$this->directory.'/update-maintenance.json';$backup='';
  try{[$repo,$branch]=$this->identity();$state=$this->status($repo,$branch);
   if($state['current']!==$expectedHead)throw new RuntimeException('The installed version changed. Refresh the preview.');
   if($this->git(['status','--porcelain','--untracked-files=all'])!=='')throw new RuntimeException('Local changes must be committed before applying or rolling back.');
   if($this->git(['branch','--show-current'])!==$branch)throw new RuntimeException('The checkout is not on the configured branch.');
   if($rollback){if(($state['rollback']??null)!==$target)throw new RuntimeException('The selected rollback is no longer available.');}
   else{if(!$state['can_apply']||$state['latest']!==$target)throw new RuntimeException('This update cannot be applied. Refresh the preview and resolve the listed blockers.');$cache=$this->read('update-check.json');if(($cache['latest']??'')!==$target||($cache['checked_epoch']??0)<time()-600)throw new RuntimeException('The preview expired. Check for updates again.');}
   $this->targetSafe($target,true);
   // Recheck after linting, before touching the working tree.
   if($this->git(['rev-parse','HEAD'])!==$expectedHead||$this->git(['status','--porcelain','--untracked-files=all'])!=='')throw new RuntimeException('Files changed during validation. No update was applied.');
   $backup='refs/vrs/backups/'.date('Ymd-His').'-'.substr($expectedHead,0,12).'-'.bin2hex(random_bytes(3));$this->git(['update-ref',$backup,$expectedHead]);
   $this->save('update-maintenance.json',['started'=>time(),'previous'=>$expectedHead,'target'=>$target,'backup'=>$backup]);
   try{$this->git($rollback?['reset','--keep',$target]:['merge','--ff-only','--no-edit',$target]);}
   catch(Throwable $e){throw new RuntimeException('Git could not finish deployment. Previous code is retained at '.$backup.'. Inspect the checkout before retrying.');}
   if($this->git(['rev-parse','HEAD'])!==$target)throw new RuntimeException('Version verification failed. Restore the backup reference manually.');
   $this->save('update-deployment.json',['previous'=>$expectedHead,'installed'=>$target,'backup'=>$backup,'updated_at'=>date('Y-m-d H:i:s')]);
   if(is_file($this->directory.'/update-check.json'))unlink($this->directory.'/update-check.json');
   if(function_exists('opcache_reset'))opcache_reset();
   return ['success'=>true,'current'=>$target,'previous'=>$expectedHead,'message'=>$rollback?'Previous code version restored.':'Update installed successfully.'];
  }finally{if(is_file($maintenance))unlink($maintenance);flock($lock,LOCK_UN);fclose($lock);}
 }
}
