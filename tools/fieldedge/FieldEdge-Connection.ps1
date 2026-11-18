[CmdletBinding()]
param(
    [ValidateSet('Provision','Status','Test','Discover','PilotDouble','PrepareLead','ApproveLead','LeadDispatch','LeadRecover')][string]$Action = 'Status',
    [string]$EvidenceFile,
    [string]$EvidenceSha256,
    [string]$ApprovalReference,
    [string]$RouteFile,[string]$RouteSha256,[string]$CardFile,[string]$CardSha256,
    [string]$BridgeAdapterPath,[string]$BridgeAdapterSha256,
    [ValidateSet('review','simulate','disabled-execute')][string]$PilotMode='simulate'
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# One local Windows DPAPI bundle for this milestone. No secrets in arguments,
# environment variables, repository files, provider diagnostics or command history.
$privateDirectory = Join-Path $env:LOCALAPPDATA 'SiteEngine\FieldEdge-Climatrol-OAuth'
$credentialFile = Join-Path $privateDirectory 'oauth.clixml'
$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$leadMutex=$null;$leadLocked=$false
$card=$null;$leadPhase='initialization';$leadWorkerStarted=$false;$leadStdinComplete=$false

function Assert-NoLink([string]$Path) {
    if ((Test-Path -LiteralPath $Path) -and ((Get-Item -LiteralPath $Path -Force).Attributes -band [IO.FileAttributes]::ReparsePoint)) {
        throw 'unsafe_private_path'
    }
}
function Assert-PrivateDirectory {
    Assert-NoLink $privateDirectory
    $acl = Get-Acl -LiteralPath $privateDirectory
    if (-not $acl.AreAccessRulesProtected) { throw 'private_directory_acl_unconfirmed' }
    foreach ($rule in $acl.GetAccessRules($true,$true,[Security.Principal.SecurityIdentifier])) {
        if ($rule.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow -and
            $rule.IdentityReference.Value -notin @($identity.User.Value,'S-1-5-18')) {
            throw 'private_directory_acl_unconfirmed'
        }
    }
}
function Read-Bundle {
    Assert-NoLink (Join-Path $env:LOCALAPPDATA 'SiteEngine')
    Assert-PrivateDirectory
    Assert-NoLink $credentialFile
    if (-not (Test-Path -LiteralPath $credentialFile -PathType Leaf) -or (Get-Item -LiteralPath $credentialFile).Length -gt 65536) { throw 'credential_file_unavailable' }
    try { $bundle = Import-Clixml -LiteralPath $credentialFile }
    catch { throw 'credential_bundle_decryption_unconfirmed' }
    if ($bundle.schema -ne 1 -or $bundle.environment -ne 'Production_ClimatrolAirLLC' -or $bundle.owner_sid -ne $identity.User.Value) { throw 'credential_context_mismatch' }
    foreach ($field in @('client_id','client_secret','target_id')) {
        if ($bundle.$field -isnot [Security.SecureString] -or $bundle.$field.Length -lt 1) { throw 'credential_bundle_incomplete' }
    }
    return $bundle
}
function Convert-ForProcess([Security.SecureString]$Value) {
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Value)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
}

try {
    if ($env:OS -ne 'Windows_NT') { throw 'windows_dpapi_required' }
    if ($Action -eq 'Provision') {
        Assert-NoLink (Join-Path $env:LOCALAPPDATA 'SiteEngine')
        if (Test-Path -LiteralPath $credentialFile) { throw 'existing_bundle_preserved' }
        if (-not (Test-Path -LiteralPath $privateDirectory)) {
            [void][IO.Directory]::CreateDirectory($privateDirectory)
            $acl = New-Object Security.AccessControl.DirectorySecurity
            $acl.SetOwner($identity.User)
            $acl.SetAccessRuleProtection($true,$false)
            foreach ($sid in @($identity.User,[Security.Principal.SecurityIdentifier]::new('S-1-5-18'))) {
                $rule = [Security.AccessControl.FileSystemAccessRule]::new($sid,'FullControl','ContainerInherit,ObjectInherit','None','Allow')
                $acl.AddAccessRule($rule)
            }
            Set-Acl -LiteralPath $privateDirectory -AclObject $acl
        }
        Assert-PrivateDirectory
        Write-Host 'Enter values from Production_ClimatrolAirLLC. Each input is masked; do not paste values into chat.'
        $bundle = [pscustomobject]@{
            schema = 1; environment = 'Production_ClimatrolAirLLC'; owner_sid = $identity.User.Value
            client_id = Read-Host 'OAuth Client ID' -AsSecureString
            client_secret = Read-Host 'OAuth Client Secret' -AsSecureString
            target_id = Read-Host 'FieldEdge tenant ID (target_id supplied by the Integrations Team)' -AsSecureString
        }
        foreach ($field in @('client_id','client_secret','target_id')) {
            if ($bundle.$field.Length -lt 1 -or $bundle.$field.Length -gt 8192) { throw 'credential_input_incomplete' }
        }
        $bundle | Export-Clixml -LiteralPath $credentialFile -NoClobber
        $verified = Read-Bundle
        Remove-Variable bundle,verified
        Write-Host "Provisioned DPAPI-encrypted bundle: $credentialFile"
        Write-Host 'Provisioning made no provider/network request. Next run this script with -Action Test.'
    } elseif ($Action -eq 'PilotDouble') {
        # This branch precedes Read-Bundle: no DPAPI content is loaded for doubles.
        Assert-PrivateDirectory
        $pilotOutput = & (Join-Path $PSScriptRoot 'Invoke-FieldEdgePilotDouble.ps1') -RouteFile $RouteFile -RouteSha256 $RouteSha256 -CardFile $CardFile -CardSha256 $CardSha256 -BridgeAdapterPath $BridgeAdapterPath -BridgeAdapterSha256 $BridgeAdapterSha256 -PilotMode $PilotMode
        $pilotSucceeded = $?
        $pilotOutput
        if (-not $pilotSucceeded) { exit 2 }
    } elseif ($Action -eq 'ApproveLead') {
        Assert-PrivateDirectory
        . (Join-Path $PSScriptRoot 'LeadGate.ps1')
        $card=Read-ABLeadCard $CardFile $CardSha256
        if($ApprovalReference -cnotmatch '^[A-Za-z0-9:._-]{1,128}$'){throw 'specific_approval_reference_required'}
        $decision=Invoke-ABLeadOperation @{operation='approve';intent_id=$card.intent_id;card_sha256=$CardSha256;approval_reference=$ApprovalReference}
        $decision|ConvertTo-Json -Compress
        if($decision.status -cnotin @('approved','replayed')){exit 2}
    } elseif ($Action -eq 'Status') {
        if (-not (Test-Path -LiteralPath $credentialFile)) {
            '{"provisioned":false,"reason_code":"credential_bundle_not_provisioned"}'
        } else {
            $bundle = Read-Bundle
            Remove-Variable bundle
            '{"provisioned":true,"storage":"Windows DPAPI, current user and machine","network_requests":0}'
        }
    } else {
        if ($Action -eq 'Discover') {
            if ($EvidenceSha256 -notmatch '^[a-f0-9]{64}$' -or -not (Test-Path -LiteralPath $EvidenceFile -PathType Leaf)) { throw 'selected_evidence_unavailable' }
            Assert-NoLink $EvidenceFile
            if ((Get-FileHash -LiteralPath $EvidenceFile -Algorithm SHA256).Hash.ToLowerInvariant() -ne $EvidenceSha256) { throw 'selected_evidence_hash_mismatch' }
        }
        $post=$null;$get=$null;$card=$null;$slot=$null
        if($Action -in @('LeadDispatch','LeadRecover')) {
            . (Join-Path $PSScriptRoot 'LeadGate.ps1')
            . (Join-Path $PSScriptRoot 'LeadWorkerProtocol.ps1')
            $leadPhase='card_validation'
            $card=Read-ABLeadCard $CardFile $CardSha256
            $leadMutex=New-Object Threading.Mutex($false,'Local\SiteEngine-FieldEdge-Climatrol-Dev');$leadLocked=$leadMutex.WaitOne(0)
            if(!$leadLocked){throw 'lead_existing_sync_active'}
            if($Action -eq 'LeadDispatch'){
                $leadPhase='post_claim'
                $post=Invoke-ABLeadOperation @{operation='claim';intent_id=$card.intent_id;card_sha256=$CardSha256;request_body_sha256=$card.request_body_sha256}
                if($post.status -cne 'claimed'){[ordered]@{status='held';reason_code=$post.reason_code;credentials_loaded=$false}|ConvertTo-Json -Compress;exit 2}
                $slot='initial'
            }else{$slot='recovery'}
            $leadPhase='get_claim'
            $get=Invoke-ABLeadOperation @{operation='get_claim';intent_id=$card.intent_id;card_sha256=$CardSha256;slot=$slot}
            if($get.status -cne 'claimed'){[ordered]@{status='held';reason_code=$get.reason_code;credentials_loaded=$false}|ConvertTo-Json -Compress;exit 2}
        }
        $leadPhase='credential_load';$bundle = Read-Bundle
        if($Action -eq 'PrepareLead'){
            . (Join-Path $PSScriptRoot 'LeadGate.ps1')
            $prepared=New-ABLeadCard $EvidenceFile $EvidenceSha256 (Convert-ForProcess $bundle.target_id)
            Remove-Variable bundle
            $prepared|ConvertTo-Json -Depth 8 -Compress;exit 0
        }
        $projectDirectory = Split-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) -Parent
        $phpDirectory = Join-Path $projectDirectory 'local-test-runtime\php-8.3.36'
        $php = Join-Path $phpDirectory 'php.exe'
        if (-not (Test-Path -LiteralPath $php) -or -not (Test-Path -LiteralPath (Join-Path $phpDirectory 'ext\php_curl.dll'))) { throw 'approved_php_runtime_unavailable' }
        $probe = Join-Path $PSScriptRoot 'oauth-probe.php'
        if ($Action -eq 'Discover') { $probe = Join-Path $PSScriptRoot 'selected-case.php' }
        if ($Action -in @('LeadDispatch','LeadRecover')) { $probe=Join-Path $PSScriptRoot 'lead-approved-worker.php' }
        $psi = New-Object Diagnostics.ProcessStartInfo
        $psi.FileName = $php
        # Runtime arguments contain paths and flags only; credential JSON goes to
        # redirected stdin. Explicitly exclude exception arguments/error logging.
        $psi.Arguments = '-n -d "extension_dir=' + (Join-Path $phpDirectory 'ext') + '" -d extension=curl -d display_errors=0 -d log_errors=0 -d zend.exception_ignore_args=1 "' + $probe + '"'
        $psi.UseShellExecute = $false; $psi.CreateNoWindow = $true
        $psi.RedirectStandardInput = $true; $psi.RedirectStandardOutput = $true; $psi.RedirectStandardError = $true
        $process = New-Object Diagnostics.Process
        $process.StartInfo = $psi
        try {
            $leadPhase='worker_start'
            [void]$process.Start()
            $leadWorkerStarted=$true
            $outTask = $process.StandardOutput.ReadToEndAsync()
            $errTask = $process.StandardError.ReadToEndAsync()
            $inputObject = @{
                environment = $bundle.environment
                client_id = Convert-ForProcess $bundle.client_id
                client_secret = Convert-ForProcess $bundle.client_secret
                target_id = Convert-ForProcess $bundle.target_id
            }
            if($Action -in @('LeadDispatch','LeadRecover')){
                if($inputObject.target_id -cne $card.tenant_id){throw 'lead_dpapi_tenant_mismatch'}
                $inputObject.post=$post;$inputObject.get=$get
            }
            if ($Action -eq 'Discover') {
                $inputObject.evidence_file = (Resolve-Path -LiteralPath $EvidenceFile).Path
                $inputObject.evidence_sha256 = $EvidenceSha256
                $inputObject.journal_file = Join-Path $privateDirectory 'selected-case-pass.journal.json'
                $inputObject.findings_file = Join-Path $privateDirectory 'selected-case-pass.findings.json'
                Assert-NoLink $inputObject.journal_file
                Assert-NoLink $inputObject.findings_file
            }
            $leadPhase='stdin_serialization'
            if($Action -in @('LeadDispatch','LeadRecover')){$inputBytes=ConvertTo-ABLeadWorkerInput $inputObject}else{$inputBytes=[Text.Encoding]::UTF8.GetBytes((ConvertTo-Json -InputObject $inputObject -Depth 30 -Compress))}
            # Write UTF-8 bytes directly: Windows PowerShell's default writer
            # uses the console code page, which can alter Unicode input.
            $leadPhase='stdin_write'
            $process.StandardInput.BaseStream.Write($inputBytes,0,$inputBytes.Length)
            $process.StandardInput.BaseStream.Flush()
            $process.StandardInput.Close()
            $leadStdinComplete=$true
            [Array]::Clear($inputBytes,0,$inputBytes.Length)
            Remove-Variable inputBytes,inputObject,bundle
            $ceilingMilliseconds = if ($Action -eq 'Discover') { 130000 } else { 40000 }
            $leadPhase='worker_wait';$timedOut=-not $process.WaitForExit($ceilingMilliseconds)
            if($timedOut){$process.Kill();$process.WaitForExit()}
            if($Action -in @('LeadDispatch','LeadRecover')){
                $leadPhase='receipt_capture'
                $protocol=ConvertFrom-ABLeadWorkerOutput $outTask.Result $errTask.Result $process.ExitCode $timedOut
                $receipt=Save-ABLeadWorkerReceipt $privateDirectory $card.intent_id $protocol
                $result=$protocol.worker_result;$leadPhase='ab_record'
                $creation=$null;$observed=$null
                if($result.status -cne 'observations_ready'){
                    if($post){$uncertain=$result.creation;if(!$uncertain){$uncertain=[ordered]@{http_status=$null;response_sha256=('0'*64);lead_id=$null;request_body_sha256=$card.request_body_sha256}};$creation=Invoke-ABLeadOperation @{operation='result';intent_id=$card.intent_id;permit=$post.permit;observation_json=($uncertain|ConvertTo-Json -Compress)}}
                    [ordered]@{status='held';reason_code=$result.reason_code;phase=$result.diagnostics.phase;private_receipt=$receipt;automatic_retries=0}|ConvertTo-Json -Compress;exit 2
                }
                if($post){$creation=Invoke-ABLeadOperation @{operation='result';intent_id=$card.intent_id;permit=$post.permit;observation_json=($result.creation|ConvertTo-Json -Compress)}}
                if($null -ne $result.get_http){$observed=Invoke-ABLeadOperation @{operation='status';intent_id=$card.intent_id;card_sha256=$CardSha256;slot=$slot;permit=$get.permit;http_status=$result.get_http;body_base64=$result.get_body_base64;observed_at=$result.observed_at}}
                [ordered]@{status=if($observed -and $observed.status -cin @('observed','replayed')){'observed'}else{'held'};creation_status=if($creation){$creation.status}else{$null};provider_status=if($observed){$observed.observation.provider_status}else{$null};guid=$card.guid;request_body_sha256=$card.request_body_sha256;private_receipt=$receipt;counts=$result.counts;automatic_retries=0}|ConvertTo-Json -Depth 8 -Compress
                if(!$observed -or $observed.status -cnotin @('observed','replayed')){exit 2};exit 0
            }
            if($timedOut){throw 'bounded_probe_timeout'}
            # Legacy results still never expose raw stderr.
            $result=$outTask.Result|ConvertFrom-Json
            if ($result.reason_code -notmatch '^[a-z_]{1,80}$') { throw 'probe_output_unconfirmed' }
            $result | ConvertTo-Json -Depth 8
            if ($process.ExitCode -ne 0) { exit 2 }
        } finally {
            if ($process -and -not $process.HasExited) { $process.Kill() }
            $process.Dispose()
        }
    }
} catch {
    # Fixed error: no exception, submitted input, secret, token or HTTP body.
    $codes = @('unsafe_private_path','private_directory_acl_unconfirmed','credential_file_unavailable','credential_context_mismatch',
        'credential_bundle_incomplete','credential_bundle_decryption_unconfirmed','windows_dpapi_required','existing_bundle_preserved',
        'credential_input_incomplete','approved_php_runtime_unavailable','bounded_probe_timeout','probe_output_unconfirmed',
        'selected_evidence_unavailable','selected_evidence_hash_mismatch')
    $reason = if ($_.Exception.Message -in $codes) { $_.Exception.Message } else { 'secure_local_operation_unconfirmed' }
    if($Action -in @('LeadDispatch','LeadRecover')){
        $failure=[ordered]@{status='held';reason_code=$reason;phase=$leadPhase;worker_started=$leadWorkerStarted;stdin_complete=$leadStdinComplete;automatic_retries=0}
        if($card -and (Get-Command Save-ABLeadWorkerReceipt -ErrorAction SilentlyContinue)){
            try{Assert-PrivateDirectory;$failure.private_receipt=Save-ABLeadWorkerReceipt $privateDirectory $card.intent_id @{launcher_failure=$failure.Clone()}}catch{}
        }
        $failure|ConvertTo-Json -Depth 8 -Compress;exit 2
    }
    [pscustomobject]@{provisioned_or_test_confirmed=$false;reason_code=$reason} | ConvertTo-Json -Compress
    if ($Action -eq 'Provision') { Write-Host 'No provider call was made. Existing credential files are preserved. Use -Action Status before retrying.' }
    exit 2
} finally {if($leadLocked){$leadMutex.ReleaseMutex()};if($leadMutex){$leadMutex.Dispose()}}
