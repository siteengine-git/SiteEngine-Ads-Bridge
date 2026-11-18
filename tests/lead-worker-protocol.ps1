param([string]$WireFixtureDirectory)
$ErrorActionPreference='Stop';Set-StrictMode -Version Latest
. (Join-Path $PSScriptRoot '../tools/fieldedge/LeadWorkerProtocol.ps1')
$n=0;function Check([bool]$Ok,[string]$Name){if(!$Ok){throw ('FAIL '+$Name)};$script:n++}
$inputObject=@{environment='fixture';post=@{reservation=@{source=@{form_id=40;entry_id=896};request=@{body='{"retained":"☃"}'}}};get=@{reservation=@{source=@{form_id=40;entry_id=896}}}}
$bytes=ConvertTo-ABLeadWorkerInput $inputObject;$decoded=[Text.Encoding]::UTF8.GetString($bytes)|ConvertFrom-Json -Depth 40
Check ($decoded.post.reservation.request.body -ceq $inputObject.post.reservation.request.body) 'nested Unicode exact input retained'
Check ($decoded.get.reservation.source.entry_id -eq 896) 'nested canonical source retained'
foreach($case in @('malformed','empty','extra_secret','timeout','oversize','nested_secret')){
 $raw=switch($case){'malformed'{'{SENSITIVE'}'empty'{''}'extra_secret'{'{"status":"held","reason_code":"json_invalid","automatic_retries":0,"secret":"SENSITIVE"}'}'oversize'{('SENSITIVE' * 40000)}'nested_secret'{'{"status":"held","reason_code":"json_invalid","automatic_retries":0,"creation":null,"diagnostics":{"phase":"input","secret":"SENSITIVE"}}'}default{'{}'}}
 $receipt=ConvertFrom-ABLeadWorkerOutput $raw 'SENSITIVE_STDERR' 2 ($case -eq 'timeout')
 Check ($receipt.worker_result.status -eq 'held') 'protocol failure holds'
 Check (!(($receipt|ConvertTo-Json -Depth 40 -Compress).Contains('SENSITIVE'))) 'raw stdout/stderr never copied into receipt'
 Check ($receipt.metadata.stderr_bytes -eq 16) 'stderr byte count retained'
}
$good='{"status":"held","reason_code":"json_invalid","creation":null,"automatic_retries":0}'
$receipt=ConvertFrom-ABLeadWorkerOutput $good '' 2
Check ($receipt.worker_result.reason_code -eq 'json_invalid') 'fixed valid worker failure preserved'
Check ((Get-ABLeadWorkerCeiling $true) -ge ((3*15+15)*1000)) 'dispatch ceiling covers three sequential bounded calls plus margin'
Check ((Get-ABLeadWorkerCeiling $false) -ge ((2*15+15)*1000)) 'recovery ceiling covers two calls plus margin'
$creation=@{http_status=201;response_sha256=('c'*64);lead_id='00000000-0000-4000-8000-000000000002';request_body_sha256=('d'*64)}
$frame=(@{protocol='lead-creation-checkpoint-1';creation=$creation}|ConvertTo-Json -Compress)+"`n"
foreach($case in @('crash','timeout','invalid_final','held_final')){
 $stdout=$frame+$(if($case -eq 'invalid_final'){'{SENSITIVE_INVALID'}elseif($case -eq 'held_final'){$good}else{''})
 $r=ConvertFrom-ABLeadWorkerOutput $stdout 'SENSITIVE_STDERR' 7 ($case -eq 'timeout')
 Check ($r.worker_result.creation.http_status -eq 201) 'flushed creation survives failed child/final response'
 Check ($r.metadata.creation_checkpoint_captured) 'checkpoint capture explicit'
 Check (!(($r|ConvertTo-Json -Depth 40).Contains('SENSITIVE'))) 'failed final response not leaked'
}
if($WireFixtureDirectory){
 foreach($case in @('success','oauth_401','get_bad_utf8')){
  $raw=Get-Content -LiteralPath (Join-Path $WireFixtureDirectory ($case+'.json')) -Raw
  $r=ConvertFrom-ABLeadWorkerOutput $raw '' $(if($case -eq 'oauth_401'){2}else{0})
  Check ($r.worker_result.reason_code -ne 'worker_output_invalid') 'actual PHP diagnostic output passes protocol'
  if($case -eq 'oauth_401'){Check ($r.worker_result.diagnostics.phase -eq 'oauth') 'OAuth failure phase crosses PHP/PowerShell boundary'}else{Check ($r.worker_result.creation.http_status -eq 201) 'confirmed creation checkpoint crosses PHP/PowerShell boundary'}
  if($case -eq 'get_bad_utf8'){Check ([Convert]::FromBase64String($r.worker_result.get_body_base64)[0] -eq 255) 'invalid UTF8 preserved across PHP/PowerShell protocol'}
 }
}
$dir=Join-Path ([IO.Path]::GetTempPath()) ('ab-offline-protocol-'+[guid]::NewGuid().ToString('N'));[IO.Directory]::CreateDirectory($dir)|Out-Null
try{
 $confirmed=ConvertFrom-ABLeadWorkerOutput ($frame+$good) '' 2
 $durable=Save-ABLeadWorkerReceipt $dir ('c'*64) $confirmed
 try{throw 'native_status_write_failed'}catch{
  $readback=Get-Content -LiteralPath $durable -Raw|ConvertFrom-Json -Depth 40
  Check ($readback.worker_result.creation.http_status -eq 201) 'later native status-write exception cannot discard private creation receipt'
 };[IO.File]::Delete($durable)
 $path=Save-ABLeadWorkerReceipt $dir ('a'*64) $receipt;Check ([IO.File]::Exists($path)) 'even failed protocol gets exclusive durable receipt';$v=Get-Content -LiteralPath $path -Raw|ConvertFrom-Json -Depth 40;Check ($v.metadata.exit_code -eq 2) 'child exit status survives receipt';[IO.File]::Delete($path)
 $php=(Resolve-Path (Join-Path $PSScriptRoot '../../local-test-runtime/php-8.3.36/php.exe')).Path
 foreach($case in @('crash','invalid','valid_failure','timeout','checkpoint_crash','checkpoint_timeout')){
  $code=switch($case){'crash'{'fwrite(STDERR,"SENSITIVE_STDERR");exit(7);'}'invalid'{'fwrite(STDOUT,"SENSITIVE_INVALID");exit(9);'}'valid_failure'{'echo "{\"status\":\"held\",\"reason_code\":\"json_invalid\",\"creation\":null,\"automatic_retries\":0}";exit(2);'}default{'usleep(1000000);'}}
  $psi=[Diagnostics.ProcessStartInfo]::new($php);$psi.UseShellExecute=$false;$psi.CreateNoWindow=$true;$psi.RedirectStandardOutput=$true;$psi.RedirectStandardError=$true
  if($case -like 'checkpoint_*'){$arguments=@('-n','-d','allow_url_fopen=0',(Join-Path $PSScriptRoot 'lead-worker-checkpoint.php'),$(if($case -eq 'checkpoint_crash'){'child_crash'}else{'child_timeout'}))}else{$arguments=@('-n','-d','allow_url_fopen=0','-r',$code)}
  foreach($arg in $arguments){$psi.ArgumentList.Add($arg)}
  $proc=[Diagnostics.Process]::new();$proc.StartInfo=$psi
  try{[void]$proc.Start();$out=$proc.StandardOutput.ReadToEndAsync();$err=$proc.StandardError.ReadToEndAsync();$timedOut=-not $proc.WaitForExit($(if($case -eq 'timeout'){50}elseif($case -eq 'checkpoint_timeout'){1000}else{5000}));if($timedOut){$proc.Kill();$proc.WaitForExit()};$r=ConvertFrom-ABLeadWorkerOutput $out.Result $err.Result $proc.ExitCode $timedOut;$file=Save-ABLeadWorkerReceipt $dir ('b'*64) $r;$saved=Get-Content -LiteralPath $file -Raw;Check ($r.worker_result.status -eq 'held') 'actual process failure holds';Check (!$saved.Contains('SENSITIVE')) 'actual process stderr/stdout redacted';Check ([IO.File]::Exists($file)) 'actual child failure is durably receipted';if($case -eq 'crash'){Check ($r.metadata.exit_code -eq 7) 'real crash exit code retained'};if($case -eq 'timeout'){Check ($r.metadata.timed_out -and $r.worker_result.reason_code -eq 'worker_timeout') 'real timeout retained'};if($case -like 'checkpoint_*'){Check ($r.worker_result.creation.http_status -eq 201) 'actual stopped child preserves confirmed creation';Check ($r.metadata.creation_checkpoint_captured) 'actual child emitted checkpoint before GET';if($case -eq 'checkpoint_timeout'){Check ($r.metadata.timed_out) 'checkpoint timeout really killed child'}};[IO.File]::Delete($file)}finally{$proc.Dispose()}
 }
}finally{if([IO.Directory]::GetFileSystemEntries($dir).Length -eq 0){[IO.Directory]::Delete($dir)}}
[ordered]@{status='PASS';assertions=$n;network_requests=0;AB_operations=0}|ConvertTo-Json -Compress
