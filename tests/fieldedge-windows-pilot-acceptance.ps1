param([ValidateSet('standard','uncertainty')][string]$Group='standard')
$ErrorActionPreference='Stop'
if($PSVersionTable.PSVersion.Major -lt 7){throw 'existing PowerShell7 required'}
$passes=0
function Check([bool]$v,[string]$label){if(-not$v){throw ('FAIL: '+$label)};$script:passes++;Write-Output ('PASS: '+$label)}
function Write-Utf8([string]$p,[string]$v){[IO.File]::WriteAllText($p,$v,[Text.UTF8Encoding]::new($false))}
function Sha([string]$p){(Get-FileHash -LiteralPath $p).Hash.ToLowerInvariant()}
$repo=Split-Path $PSScriptRoot -Parent;$launcher=Join-Path $repo 'tools\fieldedge\FieldEdge-Connection.ps1';$fake=Join-Path $PSScriptRoot 'support\fieldedge\Fake-ProspectiveBridge.ps1'
$root=Join-Path $env:LOCALAPPDATA ('SiteEngine\FieldEdge-Climatrol-OAuth\windows-double-test-'+[guid]::NewGuid().ToString('N'));[void][IO.Directory]::CreateDirectory($root)
$pwsh=Join-Path $PSHOME 'pwsh.exe';$runner='D:\SiteEngine\Agency\AI-Workspace\Invoke-Site-Wp.ps1'
$cases=if($Group -eq 'uncertainty'){@('fail-after-double-read','fail-after-double-observe')}else{@('happy','bad-reply','locked','nonzero','oversized','bad-permit','host-mismatch')}
foreach($scenario in $cases){
 $dir=Join-Path $root $scenario;[void][IO.Directory]::CreateDirectory($dir)
 $profile=Join-Path $dir 'site-access.json';Write-Utf8 $profile (([ordered]@{target='Alma';accessMode='owner-shell';sshAlias='alma-agent';owner='synthetic';root='/synthetic/.crm-acceptance/fixture'}|ConvertTo-Json -Compress))
 $body='{"source":"siteengine","customer":{"phone":"2025550101"},"isExistingCustomer":false,"appointment":{},"serviceRequest":{},"preferredContactMethod":"Phone"}'
 $bodyPath=Join-Path $dir 'body.txt';Write-Utf8 $bodyPath $body
 $source=[ordered]@{type='fluent_form';form_id=7;entry_id=999;transaction_id='ff:7:999'}
 $intent=[ordered]@{contract_version='1';provider='fieldedge';tenant_id='synthetic-windows-test';operation_id=$scenario;inquiry_id='inq-process-double';source=$source;request=[ordered]@{method='POST';endpoint='https://api.fieldedge.com/open-api/v1/leads';body=$body;body_sha256=(Sha $bodyPath)};synthetic=$true}
 $intentJson=$intent|ConvertTo-Json -Depth 8 -Compress;$intentPath=Join-Path $dir 'intent.json';Write-Utf8 $intentPath $intentJson
 $route=[ordered]@{contract_version='1';mode='double_only';execution_host=[ordered]@{hostname=$(if($scenario -eq 'host-mismatch'){'foreign-host'}else{[Environment]::MachineName});user_sid=[Security.Principal.WindowsIdentity]::GetCurrent().User.Value};site=[ordered]@{ssh_alias='alma-agent';owner='synthetic';root='/synthetic/.crm-acceptance/fixture';home='http://127.0.0.1:12345';database_sha256=('4'*64);prefix='fixture_';ads_manifest_sha256=('5'*64)};profile_path=$profile;profile_sha256=(Sha $profile);shared_runner_sha256=(Sha $runner);bridge=[ordered]@{path='/synthetic/bridge.php';sha256=('6'*64)};binding=[ordered]@{tenant_id=$intent.tenant_id;inquiry_id=$intent.inquiry_id;source=$source;operation_id=$scenario;intent_sha256=(Sha $intentPath);request_body_sha256=$intent.request.body_sha256;credential_reference_sha256=('7'*64)}}
 $routePath=Join-Path $dir 'route.json';Write-Utf8 $routePath ($route|ConvertTo-Json -Depth 12 -Compress);$routeSha=Sha $routePath
 $card=[ordered]@{contract_version='1';mode='review_only';card_id=('route-'+$routeSha);target=[ordered]@{provider='fieldedge';environment='Synthetic';tenant_id=$intent.tenant_id;credential_reference_sha256=('7'*64)};intent_json=$intentJson;request_budget=[ordered]@{oauth_token_posts=1;lead_posts=1;automatic_retries=0};unresolved=@();subject_kind='synthetic'}
 $cardPath=Join-Path $dir 'card.json';Write-Utf8 $cardPath ($card|ConvertTo-Json -Depth 8 -Compress)
 $psi=[Diagnostics.ProcessStartInfo]::new();$psi.FileName=$pwsh;$psi.UseShellExecute=$false;$psi.CreateNoWindow=$true;$psi.RedirectStandardOutput=$true;$psi.RedirectStandardError=$true
 foreach($arg in @('-NoProfile','-File',$launcher,'-Action','PilotDouble','-RouteFile',$routePath,'-RouteSha256',$routeSha,'-CardFile',$cardPath,'-CardSha256',(Sha $cardPath),'-BridgeAdapterPath',$fake,'-BridgeAdapterSha256',(Sha $fake))){$psi.ArgumentList.Add($arg)}
 $p=[Diagnostics.Process]::Start($psi);$out=$p.StandardOutput.ReadToEndAsync();$err=$p.StandardError.ReadToEndAsync();if(-not$p.WaitForExit(30000)){$p.Kill($true);throw 'local double process exceeded30seconds'}
 $raw=$out.Result;$result=$raw|ConvertFrom-Json;$code=$p.ExitCode;[void]$err.Result;$p.Dispose()
 if($Group -eq 'uncertainty'){
  $expected=if($scenario -eq 'fail-after-double-read'){7}else{8}
  Check ($code -ne 0 -and $result.status -eq 'held' -and $result.credentials_loaded -eq $false) ($scenario+' holds after the Windows double')
  $requests=@(Get-ChildItem -LiteralPath $result.receipt_directory -Filter 'request-*.json'|Sort-Object Name)
  Check ($requests.Count -eq $expected -and (Test-Path -LiteralPath (Join-Path $result.receipt_directory 'held.json')) -and -not(Test-Path -LiteralPath (Join-Path $result.receipt_directory 'receipt.json'))) 'exclusive request/held evidence preserved with no successful completion receipt'
  $packets=@($requests|ForEach-Object{Get-Content -LiteralPath $_.FullName -Raw|ConvertFrom-Json})
  $sequence=($packets.operation -join ',')
  $wanted='review,read,read,read,claim,read,read'+$(if($expected -eq 8){',observe'}else{''})
  Check ($sequence -eq $wanted) 'no callback or write retry after failing operation'
  Check (@($packets|Where-Object operation -eq 'claim').Count -eq 1 -and @($packets|Where-Object operation -eq 'observe').Count -eq $(if($expected -eq 8){1}else{0})) 'one claim and at most the original observation attempt; no repeated mutation'
  $held=Get-Content -LiteralPath (Join-Path $result.receipt_directory 'held.json') -Raw|ConvertFrom-Json
  Check ($held.no_automatic_retry -eq $true -and $result.PSObject.Properties.Name -notcontains 'lead_doubles') 'unconfirmed transport count stays unknown,never relabeled zero or success'
  $prior=Get-Content -LiteralPath (Join-Path $result.receipt_directory 'response-06.json') -Raw|ConvertFrom-Json
  Check ($prior.result.prospective.status -eq 'claimed' -and $prior.result.prospective.claim.permit -eq ('2'*64) -and $prior.result.prospective.claim.body_sha256 -eq $intent.request.body_sha256) 'last fresh claim evidence retains original permit and request digest'
  if($expected -eq 8){
   $last=$packets[-1];$observation=$last.observation_json|ConvertFrom-Json
   Check ($last.permit -eq $prior.result.prospective.claim.permit -and $observation.classification -eq 'success' -and $observation.request_body_sha256 -eq $intent.request.body_sha256 -and $observation.lead_id -eq '00000000-0000-4000-8000-000000000601') 'uncertain observe request preserves exact original permit and classified local result'
  }
 }elseif($scenario -eq 'happy'){
  Check ($code -eq 0 -and $result.status -eq 'completed' -and $result.result.status -eq 'succeeded') 'actual Windows/PHP IPC happy path via process-protocol DOUBLE'
  Check ($result.bridge_calls -eq 9 -and $result.lead_doubles -eq 1 -and $result.provider_requests -eq 0 -and $result.credentials_loaded -eq $false) 'nine bounded callbacks,one local lead double,no provider/credential access'
  $receipt=Get-Content -LiteralPath (Join-Path $result.receipt_directory 'receipt.json') -Raw|ConvertFrom-Json
  Check ($receipt.events.Count -eq 9 -and @($receipt.events.execution.connection_id|Sort-Object -Unique).Count -eq 9) 'per-operation private receipt preserves distinct synthetic connection IDs'
 }elseif($scenario -eq 'bad-permit'){Check ($result.status -eq 'completed' -and $result.result.status -eq 'held' -and $result.lead_doubles -eq 0) 'claim/read permit mismatch prevents local transport'}
 else{Check ($code -ne 0 -and $result.status -eq 'held' -and $result.credentials_loaded -eq $false) ($scenario+' holds without automatic replay')}
}
Write-Output "RESULT: $passes PASS, 0 FAIL; Windows process/bridge DOUBLES only; no SSH/AB/provider calls."
Write-Output ('Private retained test receipts: '+$root)
