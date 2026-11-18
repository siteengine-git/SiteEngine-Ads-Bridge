# Existing owner-scoped OpenSSH file custody + canonical website runner. No secrets.
function Invoke-ABLeadOperation([hashtable]$Request) {
    $ErrorActionPreference='Stop'
    $private=Join-Path $env:LOCALAPPDATA 'SiteEngine\FieldEdge-Climatrol-OAuth'
    $profile=Join-Path $private 'climatrol-wordpress-read-profile-20261113.json'
    $p=Get-Content -LiteralPath $profile -Raw|ConvertFrom-Json
    if($p.root -cne '/home/climatrol.siteengine.space/public_html' -or $p.owner -cne 'clima6647' -or $p.sshAlias -cne 'alma-agent' -or $p.target -cne 'Alma'){throw 'lead_profile_refused'}
    $bytes=[Text.UTF8Encoding]::new($false).GetBytes((ConvertTo-Json -InputObject $Request -Depth 30 -Compress))
    if($bytes.Length -gt 131072){throw 'lead_request_bound'}
    $sha=[Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($bytes)).ToLowerInvariant()
    $name='request-'+[guid]::NewGuid().ToString('N')+'.json'
    $remote='/home/climatrol.siteengine.space/.se-ads-pilot-gatea-20261118/gate-b/'+$name
    $sshArgs=@('-F','C:\Users\admin\.ssh\config','-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile=C:/Users/admin/.ssh/known_hosts','-o','ConnectTimeout=8','alma-agent')
    # Bytes (including private contact fields) travel ONLY through stdin; never argv.
    $code="import os,sys,hashlib; p='"+$remote+"'; b=sys.stdin.buffer.read(131073); assert len(b)<=131072 and hashlib.sha256(b).hexdigest()=='"+$sha+"'; f=os.open(p,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600); os.write(f,b); os.fsync(f); os.close(f)"
    $psi=[Diagnostics.ProcessStartInfo]::new('C:\Windows\System32\OpenSSH\ssh.exe');$psi.UseShellExecute=$false;$psi.CreateNoWindow=$true
    foreach($a in $sshArgs){$psi.ArgumentList.Add($a)}
    $quote=[char]39;$dq=[char]34;$quoted=$quote+$code.Replace([string]$quote,([string]$quote+$dq+$quote+$dq+$quote))+$quote
    $psi.ArgumentList.Add('sudo -n -u clima6647 -- python3 -c '+$quoted)
    $psi.RedirectStandardInput=$true;$psi.RedirectStandardOutput=$true;$psi.RedirectStandardError=$true
    $proc=[Diagnostics.Process]::new();$proc.StartInfo=$psi
    try{[void]$proc.Start();$out=$proc.StandardOutput.ReadToEndAsync();$err=$proc.StandardError.ReadToEndAsync();$proc.StandardInput.BaseStream.Write($bytes,0,$bytes.Length);$proc.StandardInput.Close();if(!$proc.WaitForExit(30000)){$proc.Kill();throw 'lead_stage_uncertain'};if($proc.ExitCode -ne 0){throw 'lead_stage_refused'}}finally{$proc.Dispose();[Array]::Clear($bytes,0,$bytes.Length)}
    $runner='D:\SiteEngine\Agency\AI-Workspace\Invoke-Site-Wp.ps1'
    $output=& $runner -SiteProfile $profile -WpArguments @('--require=/home/climatrol.siteengine.space/.se-ads-pilot-gatea-20261118/gate-b/admit.php','--user=1','eval-file','/home/climatrol.siteengine.space/public_html/wp-content/plugins/siteengine-ads-bridge/tools/acceptance/lead-gate.php',$remote,$sha) -TimeoutSeconds 30
    $reply=($output|Out-String)|ConvertFrom-Json -Depth 35
    if($reply.request_sha256 -cne $sha -or $reply.operation -cne $Request.operation -or $reply.version -cne '0.9.6-rc.2' -or !$reply.execution.transaction_absent -or !$reply.execution.coordination_lock_free -or $reply.execution.site -cne 'https://climatrol.siteengine.space'){throw 'lead_native_reply_unconfirmed'}
    return $reply.result
}
function New-ABLeadCard([string]$InputFile,[string]$InputSha,[string]$Tenant) {
    $private=Join-Path $env:LOCALAPPDATA 'SiteEngine\FieldEdge-Climatrol-OAuth'
    $item=Get-Item -LiteralPath $InputFile -Force
    if(!$item.FullName.StartsWith($private+'\',[StringComparison]::OrdinalIgnoreCase) -or ($item.Attributes-band [IO.FileAttributes]::ReparsePoint) -or $item.Length -gt 32768 -or (Get-FileHash -LiteralPath $InputFile -Algorithm SHA256).Hash.ToLowerInvariant() -cne $InputSha){throw 'lead_private_input_refused'}
    $spec=[IO.File]::ReadAllText($InputFile)|ConvertFrom-Json -AsHashtable -Depth 20
    if($spec.Keys.Count -ne 3 -or $spec.entry_id -isnot [long] -and $spec.entry_id -isnot [int] -or $spec.contact_id -isnot [long] -and $spec.contact_id -isnot [int] -or !$spec.request -or $spec.request.Contains('trackingId')){throw 'lead_operator_input_required'}
    $native=Invoke-ABLeadOperation @{operation='inspect'};if($native.status -cne 'ready' -or $native.master_form -ne 40){throw 'lead_master_form_changed'}
    $planPath=Join-Path $private ('lead-plan-'+$InputSha+'.json')
    if(Test-Path -LiteralPath $planPath){$plan=[IO.File]::ReadAllText($planPath)|ConvertFrom-Json -AsHashtable -Depth 25}else{
        $guid=[guid]::NewGuid().ToString();$inq='inq_'+[guid]::NewGuid().ToString();$source=[ordered]@{type='fluent_form';form_id=40;entry_id=$spec.entry_id;transaction_id=('ff:40:'+$spec.entry_id)}
        $payload=[ordered]@{trackingId=$guid};foreach($key in $spec.request.Keys){$payload[$key]=$spec.request[$key]};$body=$payload|ConvertTo-Json -Depth 20 -Compress
        $bodySha=[Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($body))).ToLowerInvariant()
        $envelope=([ordered]@{contract_version='2';provider='fieldedge';tenant_id=$Tenant;inquiry_id=$inq;source=$source;guid=$guid;request=[ordered]@{method='POST';endpoint='https://api.fieldedge.com/open-api/v1/leads';body=$body;body_sha256=$bodySha};synthetic=$false})|ConvertTo-Json -Depth 25 -Compress
        $plan=[ordered]@{input_sha256=$InputSha;envelope=$envelope;expires_at=[datetime]::UtcNow.AddHours(24).ToString('yyyy-MM-ddTHH:mm:ssZ');adoption=[ordered]@{contract_version='1';event_id=('lead-'+$guid);site_instance_id='climatrol-dev-fe-pilot';contact_id=$spec.contact_id;inquiry_id=$inq;head_version=0;source=[ordered]@{type='fluent_form';form_id=40;entry_id=$spec.entry_id;reference=('form:40:entry:'+$spec.entry_id)}}}
        $stream=[IO.File]::Open($planPath,[IO.FileMode]::CreateNew);try{$b=[Text.UTF8Encoding]::new($false).GetBytes(($plan|ConvertTo-Json -Depth 30 -Compress));$stream.Write($b,0,$b.Length);$stream.Flush($true)}finally{$stream.Dispose()}
    }
    $checked=$plan.envelope|ConvertFrom-Json -Depth 25;$validated=Invoke-ABLeadOperation @{operation='validate';body=$checked.request.body;guid=$checked.guid};if($validated.status -cne 'valid'){throw 'lead_payload_invalid'}
    $a=Invoke-ABLeadOperation @{operation='adopt';adoption=$plan.adoption};if($a.status -cne 'adopted'){throw 'lead_native_adoption_held'}
    $reserved=Invoke-ABLeadOperation @{operation='reserve';envelope=$plan.envelope};if($reserved.status -cnotin @('prepared','replayed')){throw 'lead_native_reservation_held'}
    $e=$plan.envelope|ConvertFrom-Json -AsHashtable -Depth 20
    $card=[ordered]@{contract_version='2';intent_id=$reserved.intent_id;inquiry_id=$e.inquiry_id;tenant_id=$Tenant;guid=$e.guid;request_body=$e.request.body;request_body_sha256=$e.request.body_sha256;environment='Production_ClimatrolAirLLC';expires_at=$plan.expires_at;effects=[ordered]@{creates_lead=$true;may_create_customer=$true;scheduling_authority='FieldEdge';confirmed_appointment=$false;preserves_inquiry_preferences=$true;staff_notifications='possible_provider_controlled';no_site_messages=$true;no_crm_lifecycle=$true;no_google_operations=$true};budget=[ordered]@{oauth_token_posts=2;lead_posts=1;lead_gets=2;automatic_retries=0};runtime_sha256=$native.runtime_sha256}
    $cardJson=$card|ConvertTo-Json -Depth 25 -Compress;$path=Join-Path $private ('lead-card-'+$reserved.intent_id+'.json');$bytes=[Text.UTF8Encoding]::new($false).GetBytes($cardJson)
    if(Test-Path -LiteralPath $path){if([IO.File]::ReadAllText($path) -cne $cardJson){throw 'lead_private_card_conflict'}}else{$stream=[IO.File]::Open($path,[IO.FileMode]::CreateNew);try{$stream.Write($bytes,0,$bytes.Length);$stream.Flush($true)}finally{$stream.Dispose()}}
    $review=Invoke-ABLeadOperation @{operation='review';intent_id=$reserved.intent_id;card_json=$cardJson};if($review.status -cnotin @('review_ready','replayed')){throw 'lead_native_card_held'}
    return [ordered]@{status='approval_required';card_file=$path;card_sha256=$review.card_sha256;guid=$e.guid;request_body_sha256=$e.request.body_sha256;network_requests=0;approval_recorded=$false}
}
function Read-ABLeadCard([string]$Path,[string]$Sha) {
    $private=Join-Path $env:LOCALAPPDATA 'SiteEngine\FieldEdge-Climatrol-OAuth';$item=Get-Item -LiteralPath $Path -Force
    if(!$item.FullName.StartsWith($private+'\',[StringComparison]::OrdinalIgnoreCase) -or ($item.Attributes-band [IO.FileAttributes]::ReparsePoint) -or $item.Length -gt 32768 -or (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant() -cne $Sha){throw 'lead_private_card_refused'}
    $sid=[Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    foreach($target in @($item.FullName,$item.DirectoryName)) {foreach($rule in (Get-Acl -LiteralPath $target).GetAccessRules($true,$true,[Security.Principal.SecurityIdentifier])){if($rule.AccessControlType -eq 'Allow' -and $rule.IdentityReference.Value -notin @($sid,'S-1-5-18')){throw 'lead_card_acl_unconfirmed'}}}
    $card=[IO.File]::ReadAllText($Path)|ConvertFrom-Json -Depth 25
    if($card.environment -cne 'Production_ClimatrolAirLLC' -or $card.contract_version -cne '2'){throw 'lead_card_environment_refused'}
    return $card
}
