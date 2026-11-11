[CmdletBinding()]
param(
 [Parameter(Mandatory=$true)][string]$RouteFile,
 [Parameter(Mandatory=$true)][string]$RouteSha256,
 [Parameter(Mandatory=$true)][string]$CardFile,
 [Parameter(Mandatory=$true)][string]$CardSha256,
 [Parameter(Mandatory=$true)][string]$BridgeAdapterPath,
 [Parameter(Mandatory=$true)][string]$BridgeAdapterSha256,
 [ValidateSet('review','simulate','disabled-execute')][string]$PilotMode='simulate'
)
$ErrorActionPreference='Stop'
Set-StrictMode -Version Latest
$root=Join-Path $env:LOCALAPPDATA 'SiteEngine\FieldEdge-Climatrol-OAuth'
$runDirectory=$null;$worker=$null
$timer=[Diagnostics.Stopwatch]::StartNew()
$powerShell7=Join-Path $PSHOME 'pwsh.exe'
function Hash-Text([string]$Text){$h=[Security.Cryptography.SHA256]::Create();try{return ([BitConverter]::ToString($h.ComputeHash([Text.Encoding]::UTF8.GetBytes($Text)))).Replace('-','').ToLowerInvariant()}finally{$h.Dispose()}}
function Read-Pinned([string]$Path,[string]$Sha,[int]$Max){
 if($Sha -notmatch '^[a-f0-9]{64}$' -or -not(Test-Path -LiteralPath $Path -PathType Leaf) -or ((Get-Item -LiteralPath $Path).Attributes -band [IO.FileAttributes]::ReparsePoint) -or (Get-Item -LiteralPath $Path).Length -gt $Max){throw 'pinned_input_refused'}
 if((Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant() -ne $Sha){throw 'pinned_input_mismatch'}
 $text=[IO.File]::ReadAllText((Resolve-Path -LiteralPath $Path).Path)
 # Raw protocol digests bind UTF-8 bytes; reject BOM/transcoding, never normalize.
 if((Hash-Text $text) -ne $Sha){throw 'pinned_input_encoding_refused'}
 return $text
}
function Save-Exclusive([string]$Path,[string]$Text){$b=[Text.Encoding]::UTF8.GetBytes($Text);$f=[IO.File]::Open($Path,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None);try{$f.Write($b,0,$b.Length);$f.Flush($true)}finally{$f.Dispose()}}
function Send-Line($Process,[string]$Text){$b=[Text.Encoding]::UTF8.GetBytes($Text+"`n");$Process.StandardInput.BaseStream.Write($b,0,$b.Length);$Process.StandardInput.BaseStream.Flush()}
function Remaining([int]$Limit){$left=240000-$timer.ElapsedMilliseconds;if($left -le 0){throw 'pilot_total_timeout'};return [Math]::Min($Limit,$left)}
function Invoke-Adapter([string]$Path,[string]$Sha){
 $localLimit=Remaining 45000
 $psi=New-Object Diagnostics.ProcessStartInfo
 $psi.FileName=$powerShell7
 # Paths/hash only. Request contents never enter local process arguments.
 foreach($v in @($BridgeAdapterPath,$Path)){if($v.Contains('"') -or $v.Contains("`n") -or $v.Contains("`r")){throw 'unsafe_process_path'}}
 $psi.Arguments='-NoProfile -File "'+$BridgeAdapterPath+'" -RequestFile "'+$Path+'" -RequestSha256 '+$Sha
 $psi.UseShellExecute=$false;$psi.CreateNoWindow=$true;$psi.RedirectStandardInput=$true;$psi.RedirectStandardOutput=$true;$psi.RedirectStandardError=$true
 $p=[Diagnostics.Process]::Start($psi);$p.StandardInput.Close()
 $out=New-Object Text.StringBuilder;$ob=New-Object char[] 4096;$eb=New-Object char[] 4096
 $ot=$p.StandardOutput.ReadAsync($ob,0,$ob.Length);$et=$p.StandardError.ReadAsync($eb,0,$eb.Length);$outDone=$false;$errDone=$false;$stderrCount=0;$local=[Diagnostics.Stopwatch]::StartNew()
 try{
  while(-not($outDone -and $errDone -and $p.HasExited)){
   if($local.ElapsedMilliseconds -gt $localLimit -or $timer.ElapsedMilliseconds -ge 240000){throw 'bridge_adapter_timeout'}
   if(-not$outDone -and $ot.IsCompleted){$count=$ot.Result;if($count -eq 0){$outDone=$true}else{[void]$out.Append($ob,0,$count);if($out.Length -gt 131072){throw 'bridge_response_too_large'};$ot=$p.StandardOutput.ReadAsync($ob,0,$ob.Length)}}
   if(-not$errDone -and $et.IsCompleted){$count=$et.Result;if($count -eq 0){$errDone=$true}else{$stderrCount+=$count;if($stderrCount -gt 16384){throw 'bridge_diagnostics_too_large'};$et=$p.StandardError.ReadAsync($eb,0,$eb.Length)}}
   if(-not($outDone -and $errDone -and $p.HasExited)){[Threading.Thread]::Sleep(10)}
  }
  if($p.ExitCode -ne 0){throw 'bridge_adapter_failed'}
  return $out.ToString()
 }finally{if(-not$p.HasExited){$p.Kill();$p.WaitForExit()};$p.Dispose()}
}
try{
 if($PSVersionTable.PSVersion.Major -lt 7 -or -not(Test-Path -LiteralPath $powerShell7)){throw 'existing_powershell7_required'}
 $sid=[Security.Principal.WindowsIdentity]::GetCurrent().User.Value
 $acl=Get-Acl -LiteralPath $root
 if(-not$acl.AreAccessRulesProtected -or ((Get-Item -LiteralPath $root).Attributes -band [IO.FileAttributes]::ReparsePoint)){throw 'private_receipt_root_unconfirmed'}
 foreach($r in $acl.GetAccessRules($true,$true,[Security.Principal.SecurityIdentifier])){if($r.AccessControlType -eq 'Allow' -and $r.IdentityReference.Value -notin @($sid,'S-1-5-18')){throw 'private_receipt_root_unconfirmed'}}
 $routeJson=Read-Pinned $RouteFile $RouteSha256 32768;$cardJson=Read-Pinned $CardFile $CardSha256 32768
 [void](Read-Pinned $BridgeAdapterPath $BridgeAdapterSha256 262144)
 $route=$routeJson|ConvertFrom-Json;$card=$cardJson|ConvertFrom-Json;$intent=$card.intent_json|ConvertFrom-Json
 if($route.contract_version -ne '1' -or $route.mode -ne 'double_only' -or $route.execution_host.hostname -ne [Environment]::MachineName -or $route.execution_host.user_sid -ne $sid){throw 'execution_host_mismatch'}
 if($card.card_id -ne ('route-'+$RouteSha256) -or $card.mode -ne 'review_only' -or $card.subject_kind -ne 'synthetic' -or @($card.unresolved).Count -ne 0 -or $intent.synthetic -ne $true -or $intent.tenant_id -notlike 'synthetic-*'){throw 'synthetic_route_card_required'}
 if((Hash-Text $card.intent_json) -ne $route.binding.intent_sha256 -or $intent.tenant_id -ne $route.binding.tenant_id -or $intent.inquiry_id -ne $route.binding.inquiry_id -or $intent.operation_id -ne $route.binding.operation_id -or $intent.request.body_sha256 -ne $route.binding.request_body_sha256 -or $card.target.tenant_id -ne $route.binding.tenant_id -or $card.target.credential_reference_sha256 -ne $route.binding.credential_reference_sha256){throw 'route_binding_mismatch'}
 if(($intent.source|ConvertTo-Json -Depth 5 -Compress) -ne ($route.binding.source|ConvertTo-Json -Depth 5 -Compress)){throw 'route_source_mismatch'}
 $profileJson=Read-Pinned $route.profile_path $route.profile_sha256 32768;$profile=$profileJson|ConvertFrom-Json
 $shared='D:\SiteEngine\Agency\AI-Workspace\Invoke-Site-Wp.ps1';[void](Read-Pinned $shared $route.shared_runner_sha256 131072)
 if($profile.sshAlias -cne $route.site.ssh_alias -or $profile.owner -cne $route.site.owner -or $profile.root -cne $route.site.root -or $profile.target -cne 'Alma' -or $profile.accessMode -cne 'owner-shell' -or $profile.sshAlias -cne 'alma-agent' -or -not$profile.root.Contains('/.crm-acceptance/') -or $route.site.home -notmatch '^http://127\.0\.0\.1:[1-9][0-9]{3,4}$'){throw 'approved_profile_route_mismatch'}
 $runDirectory=Join-Path $root ('pilot-route-'+[guid]::NewGuid().ToString('N'));[void][IO.Directory]::CreateDirectory($runDirectory)
 Save-Exclusive (Join-Path $runDirectory 'route.json') $routeJson;Save-Exclusive (Join-Path $runDirectory 'card.json') $cardJson
 $project=Split-Path(Split-Path(Split-Path $PSScriptRoot -Parent)-Parent)-Parent
 $php=Join-Path $project 'local-test-runtime\php-8.3.36\php.exe';$client=Join-Path $PSScriptRoot 'pilot-windows-client.php'
 $psi=New-Object Diagnostics.ProcessStartInfo;$psi.FileName=$php;$psi.Arguments='-n -d display_errors=0 -d log_errors=0 -d zend.exception_ignore_args=1 "'+$client+'"';$psi.UseShellExecute=$false;$psi.CreateNoWindow=$true;$psi.RedirectStandardInput=$true;$psi.RedirectStandardOutput=$true;$psi.RedirectStandardError=$true
 $worker=[Diagnostics.Process]::Start($psi);$workerErr=$worker.StandardError.ReadToEndAsync()
 Send-Line $worker (([ordered]@{mode=$PilotMode;card_json=$cardJson;card_sha256=$CardSha256}|ConvertTo-Json -Depth 5 -Compress))
 $calls=0;$events=@();$finished=$false
 while(-not$finished){
  $task=$worker.StandardOutput.ReadLineAsync();if(-not$task.Wait([int](Remaining 45000))){throw 'pilot_worker_timeout'};$line=$task.Result
  if($null -eq $line -or $line.Length -gt 131072){throw 'pilot_worker_output_unconfirmed'};$message=$line|ConvertFrom-Json
  if($message.kind -eq 'complete'){$final=$message;$finished=$true;break}
  if($message.kind -ne 'bridge' -or ++$calls -gt 12){throw 'bridge_operation_ceiling'}
  $op=$null;$id=$null;$requestSha=$null;$permit=$null;$observation=$null
  switch($message.operation){
   'pilot_review'{if($message.args.Count -ne 1 -or $message.args[0] -cne $cardJson){throw 'bridge_card_mismatch'};$op='review'}
   'pilot_read'{if($message.args.Count -ne 1 -or $message.args[0] -cne $CardSha256){throw 'bridge_read_mismatch'};$op='read'}
   'pilot_claim'{if($message.args.Count -ne 3 -or $message.args[0] -cne $CardSha256){throw 'bridge_claim_mismatch'};$op='claim';$id=$message.args[1];$requestSha=$message.args[2]}
   'observe'{if($message.args.Count -ne 3){throw 'bridge_observation_mismatch'};$op='observe';$id=$message.args[0];$permit=$message.args[1];$observation=$message.args[2]}
   default{throw 'bridge_operation_refused'}
  }
  # Revalidate input executable pins before each potential mutation. No retries.
  [void](Read-Pinned $BridgeAdapterPath $BridgeAdapterSha256 262144);[void](Read-Pinned $route.profile_path $route.profile_sha256 32768);[void](Read-Pinned $shared $route.shared_runner_sha256 131072)
  $request=[ordered]@{contract_version='1';operation=$op;route_json=$routeJson;route_sha256=$RouteSha256;card_json=$cardJson;card_sha256=$CardSha256;intent_id=$id;request_sha256=$requestSha;permit=$permit;observation_json=$observation}
  $json=$request|ConvertTo-Json -Depth 5 -Compress;if([Text.Encoding]::UTF8.GetByteCount($json) -gt 131072){throw 'bridge_request_too_large'}
  $requestPath=Join-Path $runDirectory ('request-'+$calls.ToString('D2')+'.json');Save-Exclusive $requestPath $json;$requestFileSha=Hash-Text $json
  $raw=Invoke-Adapter $requestPath $requestFileSha
  if([Text.Encoding]::UTF8.GetByteCount($raw) -gt 131072){throw 'bridge_response_too_large'}
  Save-Exclusive (Join-Path $runDirectory ('response-'+$calls.ToString('D2')+'.json')) $raw
  $response=$raw|ConvertFrom-Json
  if($response.contract_version -isnot [string] -or $response.contract_version -cne '1' -or $response.request_sha256 -cne $requestFileSha -or $response.route_sha256 -cne $RouteSha256 -or $response.card_sha256 -cne $CardSha256 -or $response.operation -cne $op -or $response.ok -isnot [bool] -or $response.ok -ne $true -or $response.execution.transaction_absent -isnot [bool] -or $response.execution.transaction_absent -ne $true -or $response.execution.coordination_lock_free -isnot [bool] -or $response.execution.coordination_lock_free -ne $true -or $response.execution.site_verified -isnot [bool] -or $response.execution.site_verified -ne $true -or $response.execution.production_post_enabled -isnot [bool] -or $response.execution.production_post_enabled -ne $false -or $response.execution.connection_id -lt 1 -or $response.execution.owner_uid -lt 1){throw 'bridge_execution_readback_mismatch'}
  $events+=@([ordered]@{number=$calls;operation=$op;request_file=$requestPath;request_sha256=$requestFileSha;execution=$response.execution})
  Send-Line $worker (([ordered]@{kind='bridge_result';result=$response.result;execution=$response.execution}|ConvertTo-Json -Depth 30 -Compress))
 }
 $worker.StandardInput.Close();if(-not$worker.WaitForExit([int](Remaining 5000))){throw 'pilot_worker_exit_unconfirmed'};[void]$workerErr.Result
 if($worker.ExitCode -ne 0 -or $final.provider_requests -ne 0 -or $final.credentials_loaded -ne $false -or $final.production_post_enabled -ne $false -or $final.lead_doubles -gt 1){throw 'pilot_completion_unconfirmed'}
 $receipt=[ordered]@{status='completed';verification='Windows orchestration; pinned adapter identifies process DOUBLE versus actual AB runtime';route_sha256=$RouteSha256;card_sha256=$CardSha256;adapter_path=$BridgeAdapterPath;adapter_sha256=$BridgeAdapterSha256;execution_host=[Environment]::MachineName;user_sid=$sid;elapsed_ms=$timer.ElapsedMilliseconds;events=$events;worker=$final;provider_requests=0;credentials_loaded=$false;production_post_enabled=$false}
 Save-Exclusive (Join-Path $runDirectory 'receipt.json') ($receipt|ConvertTo-Json -Depth 35)
 [ordered]@{status='completed';result=$final.result;bridge_calls=$calls;lead_doubles=$final.lead_doubles;receipt_directory=$runDirectory;provider_requests=0;credentials_loaded=$false;production_post_enabled=$false}|ConvertTo-Json -Depth 10 -Compress
}catch{
 if($runDirectory){try{Save-Exclusive (Join-Path $runDirectory 'held.json') (([ordered]@{status='held';reason_code='windows_bridge_unconfirmed_reconcile';elapsed_ms=$timer.ElapsedMilliseconds;provider_requests=0;credentials_loaded=$false;no_automatic_retry=$true}|ConvertTo-Json -Compress))}catch{}}
 [ordered]@{status='held';reason_code='windows_bridge_unconfirmed_reconcile';receipt_directory=$runDirectory;provider_requests=0;credentials_loaded=$false;production_post_enabled=$false}|ConvertTo-Json -Compress
 exit 2
}finally{if($worker){if(-not$worker.HasExited){$worker.Kill();$worker.WaitForExit()};$worker.Dispose()}}
