[CmdletBinding()]
param(
    [ValidateSet('DryRun','Run')][string]$Mode = 'DryRun'
)
# Windows DPAPI is intentionally the only OAuth secret host.
# Provider transport: one token + four allowlisted GETs, no retries or writes.
# WordPress: three FE custom fields only, exact identity and source checks.
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$private = Join-Path $env:LOCALAPPDATA 'SiteEngine\FieldEdge-Climatrol-OAuth'
$launcher = Join-Path $PSScriptRoot 'FieldEdge-Connection.ps1'
$siteRunner = 'D:\SiteEngine\Agency\AI-Workspace\Invoke-Site-Wp.ps1'
$siteProfile = Join-Path $private 'climatrol-wordpress-read-profile-20261113.json'
$seed = Join-Path $private 'live-snapshot-pass-20261113.evidence.json'
$seedHash = '27fbc7e24c6b3618a9728b80dd772fbe1cd78b32991cc3b6444ae5fbc12b6bd9'
$mutex = New-Object Threading.Mutex($false, 'Local\SiteEngine-FieldEdge-Climatrol-Dev')
$locked = $false
$remoteTemp = ''
$runId = ''
try {
    $locked = $mutex.WaitOne(0)
    if (-not $locked) { throw 'sync_already_running' }
    if (-not (Test-Path -LiteralPath $siteProfile -PathType Leaf) -or
        -not (Test-Path -LiteralPath $seed -PathType Leaf) -or
        (Get-FileHash -LiteralPath $seed -Algorithm SHA256).Hash.ToLowerInvariant() -ne $seedHash) {
        throw 'verified_case_or_profile_unavailable'
    }
    $profile = Get-Content -LiteralPath $siteProfile -Raw | ConvertFrom-Json
    if ($profile.root -ne '/home/climatrol.siteengine.space/public_html' -or
        $profile.owner -ne 'clima6647' -or $profile.target -ne 'Alma') {
        throw 'site_profile_mismatch'
    }
    if ($Mode -eq 'DryRun') {
        $status = & $launcher -Action Status | ConvertFrom-Json
        if ($status.provisioned -ne $true) { throw 'secure_provider_credentials_unavailable' }
        [pscustomobject]@{mode='DryRun';status='ready';provider_requests=0;wordpress_writes=0;site='climatrol.siteengine.space'}
        exit 0
    }
    $runId = [datetime]::UtcNow.ToString('yyyyMMddTHHmmssZ') + '-' + [guid]::NewGuid().ToString('N').Substring(0,8)
    if ($runId -notmatch '^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8}$') { throw 'run_id_invalid' }
    $prefix = 'live-snapshot-' + $runId
    $evidence = Join-Path $private ($prefix + '.evidence.json')
    $findings = Join-Path $private ($prefix + '.findings.json')
    $receiptPath = Join-Path $private ($prefix + '.sync-receipt.json')
    $payloadPath = Join-Path $private ($prefix + '.payload.json')
    $stageScript = Join-Path $private ($prefix + '.stage.php')
    foreach ($file in @($evidence,$findings,$receiptPath,$payloadPath,$stageScript)) {
        if (Test-Path -LiteralPath $file) { throw 'private_run_collision' }
    }
    Copy-Item -LiteralPath $seed -Destination $evidence -ErrorAction Stop
    $evidenceHash = (Get-FileHash -LiteralPath $evidence -Algorithm SHA256).Hash.ToLowerInvariant()
    $statusOutput = & powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File $launcher -Action LiveSnapshot -RunId $runId -EvidenceFile $evidence -EvidenceSha256 $evidenceHash
    if ($LASTEXITCODE -ne 0) { throw 'provider_read_not_completed' }
    $status = ($statusOutput | Out-String | ConvertFrom-Json)
    if ($status.reason_code -ne 'selected_case_completed' -or $status.business_get_attempts -ne 4 -or
        $status.work_order_snapshots -ne 3) { throw 'provider_read_not_verified' }
    $privateReport = Get-Content -LiteralPath $findings -Raw | ConvertFrom-Json
    if ($privateReport.reason_code -ne 'selected_case_completed' -or $privateReport.journal.closed -ne $true -or
        $privateReport.findings.contact_id -ne 334 -or $privateReport.findings.jobs.Count -ne 3) {
        throw 'provider_receipt_not_verified'
    }
    $payloadJson = ConvertTo-Json -InputObject $privateReport.findings -Depth 12 -Compress
    [IO.File]::WriteAllText($payloadPath,$payloadJson,[Text.UTF8Encoding]::new($false))
    $payloadHash = (Get-FileHash -LiteralPath $payloadPath -Algorithm SHA256).Hash.ToLowerInvariant()
    $remoteTemp = '/tmp/se-fe-sync-' + $runId + '.json'
    $scp = 'C:\Windows\System32\OpenSSH\scp.exe'
    $sshArgs = @('-F','C:\Users\admin\.ssh\config','-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile=C:/Users/admin/.ssh/known_hosts')
    & $scp @sshArgs $payloadPath ('alma-agent:' + $remoteTemp)
    if ($LASTEXITCODE -ne 0) { throw 'private_snapshot_transfer_failed' }
    $template = @'
$src="__REMOTE_TEMP__";
$dst=dirname(rtrim(ABSPATH,"/"))."/.siteengine-fieldedge";
$name="snapshot-__RUN_ID_LOWER__.json";
$hash="__PAYLOAD_HASH__";
if(home_url("/")!=="https://climatrol.siteengine.space/" || !is_dir($dst) ||
   is_link($dst) || !is_file($src) || filesize($src)>32768 ||
   hash_file("sha256",$src)!==$hash || file_exists($dst."/".$name)) {
    throw new RuntimeException("scoped_snapshot_stage_refused");
}
$bytes=file_get_contents($src);
$fh=fopen($dst."/".$name,"xb");
if(!$fh || fwrite($fh,$bytes)!==strlen($bytes) || !fflush($fh) || !fsync($fh)){
    throw new RuntimeException("scoped_snapshot_stage_failed");
}
fclose($fh);
chmod($dst."/".$name,0600);
if(hash_file("sha256",$dst."/".$name)!==$hash){throw new RuntimeException("scoped_snapshot_digest_failed");}
echo json_encode(["stage"=>"verified","hash"=>$hash,"private"=>true]);
'@
    $template = $template.Replace('__REMOTE_TEMP__',$remoteTemp).Replace('__RUN_ID_LOWER__',$runId.ToLowerInvariant()).Replace('__PAYLOAD_HASH__',$payloadHash)
    [IO.File]::WriteAllText($stageScript,$template,[Text.UTF8Encoding]::new($false))
    $stageOutput = & $siteRunner -SiteProfile $siteProfile -PhpFile $stageScript
    $stage = ($stageOutput | Out-String | ConvertFrom-Json)
    if ($stage.stage -ne 'verified' -or $stage.hash -ne $payloadHash) { throw 'site_snapshot_stage_unverified' }
    $siteOut = & $siteRunner -SiteProfile $siteProfile -WpArguments @('--user=1','siteengine-fieldedge','apply',('--name=snapshot-'+$runId.ToLowerInvariant()+'.json'),('--sha256='+$payloadHash))
    $result = ($siteOut | Out-String | ConvertFrom-Json)
    if ($result.contact_id -ne 334 -or $result.job_count -ne 3 -or
        $result.status -notin @('updated','replayed')) { throw 'crm_projection_not_accepted' }
    $receipt = [ordered]@{
        schema=1;run_id=$runId;provider_result='four_gets_verified';crm_result=$result.status;
        contact_id=334;work_order_count=3;payload_sha256=$payloadHash;
        provider_writes=0;advertising_exports=0;notifications=0
    }
    [IO.File]::WriteAllText($receiptPath,(ConvertTo-Json -InputObject $receipt -Compress),[Text.UTF8Encoding]::new($false))
    $receipt
} catch {
    # Do not print exception details; protect private credential and contact values.
    $reason = if ($_.Exception.Message -match '^[a-z_]{4,64}$') { $_.Exception.Message } else { 'sync_execution_unconfirmed' }
    if ($runId -match '^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8}$') {
        $failure = Join-Path $private ('live-snapshot-' + $runId + '.failure.json')
        if (-not (Test-Path -LiteralPath $failure)) {
            $safeFailure = [ordered]@{schema=1;run_id=$runId;status='held';reason_code=$reason;provider_writes=0;advertising_exports=0}
            [IO.File]::WriteAllText($failure,(ConvertTo-Json -InputObject $safeFailure -Compress),[Text.UTF8Encoding]::new($false))
        }
    }
    [Console]::Out.WriteLine((ConvertTo-Json -InputObject ([ordered]@{mode=$Mode;status='held';reason_code=$reason}) -Compress))
    exit 2
} finally {
    if ($remoteTemp) {
        # Only our fixed-format temporary file may be deleted.
        if ($remoteTemp -match '^/tmp/se-fe-sync-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8}\.json$') {
            $ssh = 'C:\Windows\System32\OpenSSH\ssh.exe'
            & $ssh -F 'C:\Users\admin\.ssh\config' -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=C:/Users/admin/.ssh/known_hosts alma-agent ('rm -f -- ' + $remoteTemp) | Out-Null
        }
    }
    if ($locked) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}
