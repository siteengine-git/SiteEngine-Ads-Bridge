[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$RunConfig)
$ErrorActionPreference='Stop'
function Sha([byte[]]$b){[Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($b)).ToLowerInvariant()}
$cfg=Get-Content -LiteralPath $RunConfig -Raw|ConvertFrom-Json
$baseRoute=Get-Content -LiteralPath $cfg.route_path -Raw
$baseCard=Get-Content -LiteralPath $cfg.card_path -Raw
$dir=Join-Path $cfg.private_directory ('adapter-preflight-'+[Guid]::NewGuid().ToString());[void](New-Item -ItemType Directory -Path $dir)
$cases=@('wrong_hash','foreign_host','foreign_sid','profile_hash','runner_hash','foreign_root','bridge_path','unknown_operation','card_hash')
$results=@()
foreach($case in $cases){
 $r=$baseRoute|ConvertFrom-Json -AsHashtable -Depth 30;$c=$baseCard|ConvertFrom-Json -AsHashtable -Depth 30
 switch($case){
  'foreign_host'{$r.execution_host.hostname='foreign-fixture-host'}
  'foreign_sid'{$r.execution_host.user_sid='S-1-0-0'}
  'profile_hash'{$r.profile_sha256='0'*64}
  'runner_hash'{$r.shared_runner_sha256='0'*64}
  'foreign_root'{$r.site.root='/home/foreign-fixture/public_html'}
  'bridge_path'{$r.bridge.path='/tmp/foreign-fixture.php'}
 }
 $rb=$r|ConvertTo-Json -Depth 30 -Compress;$rh=Sha ([Text.Encoding]::UTF8.GetBytes($rb));$c.card_id='route-'+$rh;$cb=$c|ConvertTo-Json -Depth 30 -Compress;$ch=Sha ([Text.Encoding]::UTF8.GetBytes($cb))
 $q=[ordered]@{contract_version='1';operation='read';route_json=$rb;route_sha256=$rh;card_json=$cb;card_sha256=$ch;intent_id=$null;request_sha256=$null;permit=$null;observation_json=$null}
 if($case -eq 'unknown_operation'){$q.operation='arbitrary_eval'}
 if($case -eq 'card_hash'){$q.card_sha256='0'*64}
 $raw=[Text.Encoding]::UTF8.GetBytes(($q|ConvertTo-Json -Depth 40 -Compress));$path=Join-Path $dir ($case+'.json');[IO.File]::WriteAllBytes($path,$raw);$h=Sha $raw;if($case -eq 'wrong_hash'){$h='0'*64}
 $psi=[Diagnostics.ProcessStartInfo]::new();$psi.FileName=(Get-Process -Id $PID).Path;$psi.UseShellExecute=$false;$psi.CreateNoWindow=$true;$psi.RedirectStandardOutput=$true;$psi.RedirectStandardError=$true
 foreach($a in @('-NoProfile','-File',$cfg.bridge_adapter_path,'-RequestFile',$path,'-RequestSha256',$h)){$psi.ArgumentList.Add($a)}
 $p=[Diagnostics.Process]::new();$p.StartInfo=$psi;[void]$p.Start();$o=$p.StandardOutput.ReadToEndAsync();$e=$p.StandardError.ReadToEndAsync()
 if(-not $p.WaitForExit(10000)){$p.Kill($true);throw 'local preflight test timed out'}
 $v=$o.Result|ConvertFrom-Json;$exit=$p.ExitCode;$p.Dispose()
 if($exit -eq 0 -or $v.ok -ne $false -or $v.reason_code -eq 'bridge_execution_unconfirmed' -or $v.reason_code -eq 'bridge_timeout_remote_outcome_unknown'){throw ('explicit local refusal not proved: '+$case)}
 $results+=@{case=$case;status='PASS';reason=$v.reason_code;exit=$exit}
}
if(@(Get-ChildItem -LiteralPath $dir -Filter 'invoke-*.ps1').Count -ne 0){throw 'unexpected remote invocation file during local refusals'}
$result=@{status='PASS';cases=$results;remote_invocations=0;receipt_directory=$dir};$result|ConvertTo-Json -Depth 10
