[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$RequestFile,[Parameter(Mandatory=$true)][string]$RequestSha256)
$ErrorActionPreference='Stop'
Set-StrictMode -Version Latest
$proc=$null;$reply=$null
function Require([bool]$Value,[string]$Reason){if(-not $Value){throw $Reason}}
function Hash-Bytes([byte[]]$Value){[Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($Value)).ToLowerInvariant()}
function Quote-PS([string]$Value){"'"+$Value.Replace("'","''")+"'"}
try {
 Require ($env:OS -eq 'Windows_NT') 'bridge_windows_required'
 Require ($RequestSha256 -cmatch '^[a-f0-9]{64}$') 'bridge_request_hash_invalid'
 $item=Get-Item -LiteralPath $RequestFile -Force
 Require (-not $item.PSIsContainer -and -not ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -and $item.Length -le 131072) 'bridge_private_request_invalid'
 $identity=[Security.Principal.WindowsIdentity]::GetCurrent()
 foreach($path in @($item.FullName,$item.DirectoryName)){
  $obj=Get-Item -LiteralPath $path -Force;Require (-not ($obj.Attributes -band [IO.FileAttributes]::ReparsePoint)) 'bridge_private_request_invalid'
  $acl=Get-Acl -LiteralPath $path
  foreach($rule in $acl.GetAccessRules($true,$true,[Security.Principal.SecurityIdentifier])){if($rule.AccessControlType -eq 'Allow'){Require ($rule.IdentityReference.Value -in @($identity.User.Value,'S-1-5-18')) 'bridge_private_acl_unconfirmed'}}
 }
 $raw=[IO.File]::ReadAllBytes($item.FullName);Require ((Hash-Bytes $raw) -ceq $RequestSha256) 'bridge_request_hash_mismatch'
 $utf8=[Text.UTF8Encoding]::new($false,$true);$q=$utf8.GetString($raw)|ConvertFrom-Json -Depth 40
 Require ($q.contract_version -ceq '1' -and $q.operation -cin @('review','read','claim','observe')) 'bridge_operation_refused'
 Require ((Hash-Bytes $utf8.GetBytes($q.route_json)) -ceq $q.route_sha256 -and (Hash-Bytes $utf8.GetBytes($q.card_json)) -ceq $q.card_sha256) 'bridge_exact_bytes_required'
 $r=$q.route_json|ConvertFrom-Json -Depth 30
 Require ($r.mode -ceq 'double_only' -and $r.execution_host.hostname -ieq [Environment]::MachineName -and $r.execution_host.user_sid -ceq $identity.User.Value) 'bridge_host_binding_mismatch'
 $project=Split-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) -Parent
 $profile=(Get-Item -LiteralPath $r.profile_path).FullName
 Require ($profile.StartsWith($project+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase) -and [IO.Path]::GetFileName($profile) -ceq 'site-access.json') 'bridge_profile_scope_refused'
 Require ((Get-FileHash -LiteralPath $profile -Algorithm SHA256).Hash.ToLowerInvariant() -ceq $r.profile_sha256) 'bridge_profile_hash_mismatch'
 $p=Get-Content -LiteralPath $profile -Raw|ConvertFrom-Json
 Require ($p.target -ceq 'Alma' -and $p.accessMode -ceq 'owner-shell' -and $p.sshAlias -ceq 'alma-agent' -and $p.owner -ceq $r.site.owner -and $p.root.TrimEnd('/') -ceq $r.site.root.TrimEnd('/') -and $r.site.ssh_alias -ceq $p.sshAlias) 'bridge_profile_binding_mismatch'
 Require ($p.root.Contains('/.crm-acceptance/') -and $r.site.home -match '^http://127\.0\.0\.1:[1-9][0-9]{3,4}$') 'bridge_isolation_required'
 $runner='D:\SiteEngine\Agency\AI-Workspace\Invoke-Site-Wp.ps1'
 Require ((Get-FileHash -LiteralPath $runner -Algorithm SHA256).Hash.ToLowerInvariant() -ceq $r.shared_runner_sha256) 'bridge_shared_runner_mismatch'
 Require ($r.bridge.path -match '^/[A-Za-z0-9_./-]+$' -and -not $r.bridge.path.Contains('/../') -and $r.bridge.path.StartsWith((Split-Path $p.root -Parent).Replace('\','/')+'/') -and -not $r.bridge.path.StartsWith($p.root.TrimEnd('/')+'/') -and $r.bridge.sha256 -cmatch '^[a-f0-9]{64}$') 'bridge_script_scope_refused'
 $encoded=[Convert]::ToBase64String($raw)
 $code="if(hash_file('sha256','"+$r.bridge.path+"')!=='"+$r.bridge.sha256+"')throw new RuntimeException('bridge_source_pin_mismatch');`$seab_prospective_bridge_json=base64_decode('"+$encoded+"');require '"+$r.bridge.path+"';"
 $invokeFile=Join-Path $item.DirectoryName ('invoke-'+[Guid]::NewGuid().ToString()+'.ps1')
 $script='$ErrorActionPreference=''Stop'''+"`n& "+(Quote-PS $runner)+' -SiteProfile '+(Quote-PS $profile)+' -WpArguments @(''--user=1'',''eval'','+(Quote-PS $code)+') -TimeoutSeconds 30'+"`nif(`$LASTEXITCODE -ne 0){exit 2}`n"
 $stream=[IO.File]::Open($invokeFile,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None)
 try{$b=[Text.UTF8Encoding]::new($true).GetPreamble()+[Text.Encoding]::UTF8.GetBytes($script);$stream.Write($b,0,$b.Length);$stream.Flush($true)}finally{$stream.Dispose()}
 $psi=[Diagnostics.ProcessStartInfo]::new();$psi.FileName=(Get-Process -Id $PID).Path;$psi.UseShellExecute=$false;$psi.CreateNoWindow=$true
 foreach($arg in @('-NoProfile','-File',$invokeFile)){$psi.ArgumentList.Add($arg)}
 $psi.RedirectStandardOutput=$true;$psi.RedirectStandardError=$true;$psi.RedirectStandardInput=$true
 $proc=[Diagnostics.Process]::new();$proc.StartInfo=$psi;[void]$proc.Start();$proc.StandardInput.Close();$out=$proc.StandardOutput.ReadToEndAsync();$err=$proc.StandardError.ReadToEndAsync()
 if(-not $proc.WaitForExit(40000)){try{$proc.Kill($true);[void]$proc.WaitForExit(1000)}catch{};throw 'bridge_timeout_remote_outcome_unknown'}
 Require ($out.Wait(1000) -and $err.Wait(1000)) 'bridge_pipe_completion_unknown'
 Require ($proc.ExitCode -eq 0 -and $out.Result.Length -le 262144 -and $err.Result.Length -le 262144) 'bridge_process_or_output_unconfirmed'
 $matches=[regex]::Matches($out.Result,'(?m)^SEAB_BRIDGE_V1:([A-Za-z0-9+/=]+)\r?$');Require ($matches.Count -eq 1) 'bridge_sentinel_unconfirmed'
 $decoded=[Convert]::FromBase64String($matches[0].Groups[1].Value);Require ($decoded.Length -le 131072) 'bridge_response_bound'
 $reply=$utf8.GetString($decoded)|ConvertFrom-Json -Depth 40
 Require ($reply.contract_version -ceq '1' -and $reply.request_sha256 -ceq $RequestSha256 -and $reply.route_sha256 -ceq $q.route_sha256 -and $reply.card_sha256 -ceq $q.card_sha256 -and $reply.operation -ceq $q.operation) 'bridge_reply_pin_mismatch'
 $reply|ConvertTo-Json -Depth 40 -Compress
 if(-not $reply.ok){exit 2}
}catch{
 $reason=if($_.Exception.Message -cmatch '^bridge_[a-z_]{1,80}$'){$_.Exception.Message}else{'bridge_execution_unconfirmed'}
 [ordered]@{contract_version='1';ok=$false;reason_code=$reason;request_sha256=$RequestSha256;execution=$null}|ConvertTo-Json -Compress
 exit 2
}finally{if($null -ne $proc){$proc.Dispose()}}
