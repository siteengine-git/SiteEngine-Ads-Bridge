[CmdletBinding()]
param(
    [ValidateSet('Provision','Status','Test','Discover')][string]$Action = 'Status',
    [string]$EvidenceFile,
    [string]$EvidenceSha256
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# One local Windows DPAPI bundle for this milestone. No secrets in arguments,
# environment variables, repository files, provider diagnostics or command history.
$privateDirectory = Join-Path $env:LOCALAPPDATA 'SiteEngine\FieldEdge-Climatrol-OAuth'
$credentialFile = Join-Path $privateDirectory 'oauth.clixml'
$identity = [Security.Principal.WindowsIdentity]::GetCurrent()

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
        $bundle = Read-Bundle
        $projectDirectory = Split-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) -Parent
        $phpDirectory = Join-Path $projectDirectory 'local-test-runtime\php-8.3.36'
        $php = Join-Path $phpDirectory 'php.exe'
        if (-not (Test-Path -LiteralPath $php) -or -not (Test-Path -LiteralPath (Join-Path $phpDirectory 'ext\php_curl.dll'))) { throw 'approved_php_runtime_unavailable' }
        $probe = Join-Path $PSScriptRoot 'oauth-probe.php'
        if ($Action -eq 'Discover') { $probe = Join-Path $PSScriptRoot 'selected-case.php' }
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
            [void]$process.Start()
            $outTask = $process.StandardOutput.ReadToEndAsync()
            $errTask = $process.StandardError.ReadToEndAsync()
            $inputObject = @{
                environment = $bundle.environment
                client_id = Convert-ForProcess $bundle.client_id
                client_secret = Convert-ForProcess $bundle.client_secret
                target_id = Convert-ForProcess $bundle.target_id
            }
            if ($Action -eq 'Discover') {
                $inputObject.evidence_file = (Resolve-Path -LiteralPath $EvidenceFile).Path
                $inputObject.evidence_sha256 = $EvidenceSha256
                $inputObject.journal_file = Join-Path $privateDirectory 'selected-case-pass.journal.json'
                $inputObject.findings_file = Join-Path $privateDirectory 'selected-case-pass.findings.json'
                Assert-NoLink $inputObject.journal_file
                Assert-NoLink $inputObject.findings_file
            }
            $inputJson = ConvertTo-Json -InputObject $inputObject -Compress
            $process.StandardInput.Write($inputJson)
            $process.StandardInput.Close()
            Remove-Variable inputJson,inputObject,bundle
            $ceilingMilliseconds = if ($Action -eq 'Discover') { 130000 } else { 40000 }
            if (-not $process.WaitForExit($ceilingMilliseconds)) { $process.Kill(); $process.WaitForExit(); throw 'bounded_probe_timeout' }
            # Never echo raw stderr; provider/cURL diagnostics are not a log source.
            [void]$errTask.Result
            $result = $outTask.Result | ConvertFrom-Json
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
    [pscustomobject]@{provisioned_or_test_confirmed=$false;reason_code=$reason} | ConvertTo-Json -Compress
    if ($Action -eq 'Provision') { Write-Host 'No provider call was made. Existing credential files are preserved. Use -Action Status before retrying.' }
    exit 2
}
