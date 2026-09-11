<?php
/** Download pinned GitHub archives. Git metadata and runtime data are never patched. */
final class VrsUpdater {
 private string $root; private string $directory; private array $config;
 public function __construct(string $root,array $config){$this->root=rtrim($root,'/');$this->directory=$this->root.'/storage';$this->config=$config;}
 private function read(string $name): array {return json_decode(@file_get_contents($this->directory.'/'.$name)?:'[]',true)?:[];}
 private function save(string $name,array $value): void {$this->write($this->directory.'/'.$name,json_encode($value,JSON_THROW_ON_ERROR));}
 private function write(string $path,string $data): void {
  $dir=dirname($path);if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Cannot create update directory.');
  $temp=tempnam($dir,'.patch-');if($temp===false)throw new RuntimeException('Update directory is not writable.');
  try{if(file_put_contents($temp,$data)!==strlen($data))throw new RuntimeException('Cannot write complete patch file.');chmod($temp,is_file($path)?(fileperms($path)&0777):0644);if(!rename($temp,$path))throw new RuntimeException('Cannot replace '.basename($path).'. Check application file permissions.');}finally{if(is_file($temp))unlink($temp);}
 }
 private function lock(){ $lock=@fopen($this->directory.'/update.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if(is_resource($lock))fclose($lock);throw new RuntimeException('Another update operation is running or storage is not writable.');}return $lock; }
 private function identity(): array {
  $repo=$this->config['update_repository']??'MrMilar12/VRS';$branch=$this->config['update_branch']??'main';
  if(!preg_match('~^[A-Za-z0-9_-][A-Za-z0-9_.-]*/[A-Za-z0-9_-][A-Za-z0-9_.-]*$~D',$repo)||!preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]*$~D',$branch)||str_contains($branch,'..'))throw new RuntimeException('Invalid update repository or branch.');return [$repo,$branch];
 }
 private function download(string $url,int $limit): string {
  if(!function_exists('curl_init'))throw new RuntimeException('Enable PHP cURL for GitHub downloads.');
  $body='';$curl=curl_init($url);$headers=['Accept: application/vnd.github+json','User-Agent: VRS-Updater'];
  $token=getenv('VRS_GITHUB_TOKEN');if($token)$headers[]='Authorization: Bearer '.$token;
  curl_setopt_array($curl,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>90,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body,$limit){if(strlen($body)+strlen($chunk)>$limit)return 0;$body.=$chunk;return strlen($chunk);}]);
  $ok=curl_exec($curl);$code=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
  if($ok===false||$code!==200)throw new RuntimeException('GitHub download failed (HTTP '.$code.'). Check connectivity, repository visibility, and VRS_GITHUB_TOKEN for private repositories.');return $body;
 }
 private function current(): string {
  $installed=$this->read('update-installed.json')['commit']??'';if(preg_match('/^[a-f0-9]{40}$/D',$installed))return $installed;
  // Bootstrap an existing checkout using read-only metadata; Git is not required.
  $head=trim(@file_get_contents($this->root.'/.git/HEAD')?:'');
  if(str_starts_with($head,'ref: refs/')){$ref=substr($head,5);if(!str_contains($ref,'..')){$head=trim(@file_get_contents($this->root.'/.git/'.$ref)?:'');if(!$head)foreach(explode("\n",@file_get_contents($this->root.'/.git/packed-refs')?:'') as $line)if(str_ends_with($line,' '.$ref))$head=substr($line,0,40);}}
  return preg_match('/^[a-f0-9]{40}$/D',$head)?$head:str_repeat('0',40);
 }
 private function allowed(string $path): bool {
  if($path===''||str_contains($path,'\\')||str_contains($path,"\0")||str_starts_with($path,'/')||preg_match('~(^|/)(\.|\.\.)(/|$)|[\x00-\x1f:]~',$path))throw new RuntimeException('Unsafe archive path.');
  foreach(explode('/',$path) as $part)if(str_starts_with($part,'.')&&$part!=='.htaccess')return false;
  return !preg_match('~^(storage|assets/uploads)(/|$)~',$path)&&$path!=='config/local.php';
 }
 private function local(string $path): string {
  if(!$this->allowed($path))throw new RuntimeException('Protected patch path.');$full=$this->root;
  foreach(explode('/',$path) as $part){$full.='/'.$part;if(is_link($full))throw new RuntimeException('A patch path is a symbolic link: '.$path);}
  if(is_dir($full))throw new RuntimeException('A file conflicts with a local directory: '.$path);return $full;
 }
 private function hash(string $path): ?string {$file=$this->local($path);return is_file($file)?hash_file('sha256',$file):null;}
 private function lint(string $file,string $name): void {
  if(!function_exists('proc_open'))throw new RuntimeException('Enable PHP proc_open to validate downloaded PHP files.');
  $p=proc_open([$this->config['update_php_binary']??PHP_BINDIR.'/php','-n','-l',$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  if(!is_resource($p))throw new RuntimeException('Cannot start PHP validation.');fclose($pipes[0]);stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('PHP validation failed for '.$name.'. No code was replaced.');
 }
 private function discardStage(string $base): void {
  if(!preg_match('/^update-stage-[a-f0-9]{16}$/D',$base)||!is_dir($this->directory.'/'.$base))return;
  $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory.'/'.$base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
  foreach($items as $item){if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($this->directory.'/'.$base);
 }
 private function stage(string $target,string $archive): array {
  if(!class_exists('ZipArchive'))throw new RuntimeException('Enable PHP ZipArchive for code updates.');
  $base='update-stage-'.bin2hex(random_bytes(8));mkdir($this->directory.'/'.$base,0700);try{$zipPath=$this->directory.'/'.$base.'/source.zip';file_put_contents($zipPath,$archive);$zip=new ZipArchive();
  if($zip->open($zipPath)!==true)throw new RuntimeException('GitHub did not return a valid ZIP archive.');$files=[];$seen=[];$total=0;$prefix=null;
  try{if($zip->numFiles>10000)throw new RuntimeException('Archive contains too many files.');
   for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$name=$stat['name'];$parts=explode('/',$name,2);if(count($parts)!==2||$parts[0]===''||$parts[0]==='..')throw new RuntimeException('Invalid archive root.');$prefix??=$parts[0];if($prefix!==$parts[0])throw new RuntimeException('Archive has multiple roots.');$path=rtrim($parts[1],'/');if($path==='')continue;
    $safe=$this->allowed($path);$zip->getExternalAttributesIndex($i,$opsys,$attr);$type=($attr>>16)&0170000;if($type!==0&&$type!==0100000&&$type!==0040000)throw new RuntimeException('Archive contains a symlink or special file.');
    $total+=$stat['size'];if($total>150000000||$stat['size']>20000000)throw new RuntimeException('Archive exceeds extraction limits.');if(!$safe||str_ends_with($name,'/'))continue;
    $key=strtolower($path);if(isset($seen[$key]))throw new RuntimeException('Archive contains duplicate file paths.');$seen[$key]=true;
    $data=$zip->getFromIndex($i);if($data===false||strlen($data)!==$stat['size'])throw new RuntimeException('Incomplete archive file.');$destination=$this->directory.'/'.$base.'/files/'.$path;$this->write($destination,$data);if(str_ends_with(strtolower($path),'.php'))$this->lint($destination,$path);$files[$path]=hash('sha256',$data);
   }
  }finally{$zip->close();unlink($zipPath);}
  foreach(['index.php','api.php','actions.php','includes/bootstrap.php','includes/updater.php','includes/developer-view.php','assets/js/developer.js'] as $required)if(!isset($files[$required]))throw new RuntimeException('The GitHub archive is missing '.$required.'. Push the complete application first.');
  $before=[];$changes=[];$previous=$this->read('update-installed.json');
  foreach($files as $path=>$hash){$old=$this->hash($path);if($old!==$hash){$before[$path]=$old;$changes[$path]=$hash;}}
  // Remove only files recorded by a previous archive deployment; preserve unmanaged files.
  foreach(($previous['files']??[]) as $path=>$hash)if(!isset($files[$path])&&$this->allowed($path)&&$this->hash($path)!==null){$before[$path]=$this->hash($path);$changes[$path]=null;}
  $state=['target'=>$target,'current'=>$this->current(),'base'=>$base,'files'=>$files,'before'=>$before,'changes'=>$changes,'created'=>time()];$previousStage=$this->read('update-stage.json')['base']??'';$this->save('update-stage.json',$state);$this->discardStage($previousStage);return $state;}catch(Throwable $e){$this->discardStage($base);throw $e;}
 }
 private function stale(array $stage): bool {foreach(($stage['before']??[]) as $path=>$hash)if($this->hash($path)!==$hash)return true;return false;}
 private function permissions(array $stage): array {
  foreach($stage['changes'] as $path=>$hash){$full=$this->local($path);$parent=dirname($full);while(!is_dir($parent))$parent=dirname($parent);if(!is_writable($parent)||(file_exists($full)&&!is_writable($full)))return ['XAMPP needs write access to application code before patching ('.$path.'). Git metadata does not need write access.'];}return [];
 }
 public function check(string $downloadTarget=''): array {
  $lock=$this->lock();try{[$repo,$branch]=$this->identity();$cache=$this->read('update-check.json');
   if(($cache['checked_epoch']??0)<time()-60||($cache['repository']??'')!==$repo||($cache['branch']??'')!==$branch){$commit=json_decode($this->download('https://api.github.com/repos/'.$repo.'/commits/'.rawurlencode($branch),2000000),true);$target=$commit['sha']??'';if(!preg_match('/^[a-f0-9]{40}$/D',$target))throw new RuntimeException('Invalid GitHub version.');$cache=['repository'=>$repo,'branch'=>$branch,'latest'=>$target,'summary'=>explode("\n",$commit['commit']['message']??'GitHub update')[0],'checked_epoch'=>time(),'checked_at'=>date('Y-m-d H:i:s')];$this->save('update-check.json',$cache);}
   $current=$this->current();$stage=$this->read('update-stage.json');$available=$current!==$cache['latest'];$blocked=[];$changes=[];
   $prepared=$available&&($stage['target']??'')===$cache['latest']&&($stage['current']??'')===$current&&!$this->stale($stage);
   if($downloadTarget!==''){
    if(!$available||$downloadTarget!==$cache['latest'])throw new RuntimeException('The available version changed. Check for updates again before downloading.');
    if(!$prepared)$stage=$this->stage($cache['latest'],$this->download('https://codeload.github.com/'.$repo.'/zip/'.$cache['latest'],50000000));$prepared=true;
   }
   if($prepared){$blocked=$this->permissions($stage);foreach($stage['changes'] as $path=>$hash)$changes[]=($hash===null?'D':($stage['before'][$path]===null?'A':'M'))."\t".$path;}
   $deployment=$this->read('update-deployment.json');return array_merge($cache,['current'=>$current,'available'=>$available,'prepared'=>$prepared,'blocked'=>$blocked,'can_apply'=>$prepared&&!$blocked,'changes'=>array_slice($changes,0,300),'change_count'=>count($changes),'rollback'=>($deployment['installed']??'')===$current?($deployment['previous']??null):null]);
  }finally{flock($lock,LOCK_UN);fclose($lock);}
 }
 public function apply(string $expectedHead,string $target,bool $rollback=false): array {
  if(!preg_match('/^[a-f0-9]{40}$/D',$target))throw new RuntimeException('Invalid update version.');$lock=$this->lock();$maintenance=false;
  try{if($this->current()!==$expectedHead)throw new RuntimeException('The installed version changed. Refresh the preview.');
   if($rollback){$deployment=$this->read('update-deployment.json');if(($deployment['installed']??'')!==$expectedHead||($deployment['previous']??'')!==$target)throw new RuntimeException('Rollback is no longer available.');$stage=$this->read($deployment['backup'].'/manifest.json');$source=$deployment['backup'];}
   else{$stage=$this->read('update-stage.json');if(($stage['target']??'')!==$target||($stage['current']??'')!==$expectedHead)throw new RuntimeException('Check for updates to download and preview this version first.');$source=$stage['base'];}
   if($errors=$this->permissions($stage))throw new RuntimeException($errors[0]);
   foreach($stage['changes'] as $path=>$hash){if($this->hash($path)!==$stage['before'][$path])throw new RuntimeException('Local files changed since the preview. Check for updates again to refresh the preview.');if($hash!==null&&hash_file('sha256',$this->directory.'/'.$source.'/files/'.$path)!==$hash)throw new RuntimeException('Downloaded file verification failed.');}
   $backup='update-backup-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));$inverse=['changes'=>$stage['before'],'before'=>$stage['changes']];
   foreach($stage['before'] as $path=>$hash)if($hash!==null)$this->write($this->directory.'/'.$backup.'/files/'.$path,file_get_contents($this->local($path)));
   $this->save($backup.'/manifest.json',$inverse);$oldInstalled=$this->read('update-installed.json');$this->save($backup.'/installed.json',$oldInstalled);
   $this->save('update-maintenance.json',['started'=>time(),'backup'=>$backup]);$maintenance=true;$done=[];
   try{foreach($stage['changes'] as $path=>$hash){$full=$this->local($path);if($hash===null){if(!unlink($full))throw new RuntimeException('Cannot remove '.$path);}else $this->write($full,file_get_contents($this->directory.'/'.$source.'/files/'.$path));$done[]=$path;}
    $installed=$rollback?$this->read($source.'/installed.json'):['commit'=>$target,'files'=>$stage['files']];$installed['commit']=$target;$this->save('update-installed.json',$installed);
    $this->save('update-deployment.json',['installed'=>$target,'previous'=>$expectedHead,'backup'=>$backup]);
   }catch(Throwable $e){try{foreach(array_reverse($done) as $path){if($stage['before'][$path]===null){if(is_file($this->local($path)))unlink($this->local($path));}else $this->write($this->local($path),file_get_contents($this->directory.'/'.$backup.'/files/'.$path));}$this->save('update-installed.json',$oldInstalled);}catch(Throwable $restore){$maintenance=false;throw new RuntimeException('Patch recovery needs attention. Backup: storage/'.$backup);}throw new RuntimeException('Patch failed; previous files restored. '.$e->getMessage());}
   @unlink($this->directory.'/update-stage.json');if(!$rollback)$this->discardStage($source);if(function_exists('opcache_reset'))opcache_reset();return ['success'=>true,'current'=>$target,'previous'=>$expectedHead,'message'=>$rollback?'Previous files restored.':'GitHub archive downloaded, backed up, and installed successfully.'];
  }finally{if($maintenance)@unlink($this->directory.'/update-maintenance.json');flock($lock,LOCK_UN);fclose($lock);}
 }
}
