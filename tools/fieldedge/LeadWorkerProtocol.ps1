# Pure private protocol functions. They cannot load credentials, call AB or HTTP.
function Test-ABLeadCreation([object]$Value) {
    if($null -eq $Value){return $true}
    $keys=@('http_status','response_sha256','lead_id','request_body_sha256')
    if(@($Value.PSObject.Properties.Name).Count -ne 4 -or @($Value.PSObject.Properties.Name|Where-Object{$_ -cnotin $keys}).Count){return $false}
    if($Value.response_sha256 -cnotmatch '^[a-f0-9]{64}$' -or $Value.request_body_sha256 -cnotmatch '^[a-f0-9]{64}$'){return $false}
    if($null -ne $Value.http_status -and ($Value.http_status -isnot [long] -and $Value.http_status -isnot [int] -or $Value.http_status -lt 100 -or $Value.http_status -gt 599)){return $false}
    return $null -eq $Value.lead_id -or $Value.lead_id -cmatch '^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$'
}
function Test-ABLeadDiagnostics([object]$Value) {
    if($null -eq $Value){return $true} # Legacy fixed-code receipts remain readable.
    $phases=@('input','binding','credentials','oauth','post','post_classification','get','complete','serialization')
    $keys=@('phase','transport_invocations','events','creation_checkpoint','counts_meaning')
    if(@($Value.PSObject.Properties.Name|Where-Object{$_ -cnotin $keys}).Count -or $Value.phase -cnotin $phases -or $Value.counts_meaning -cne 'Callable invocation, not proof that bytes reached the provider'){return $false}
    if(!(Test-ABLeadCreation $Value.creation_checkpoint)){return $false}
    if(@($Value.transport_invocations.PSObject.Properties.Name).Count -ne 3){return $false}
    foreach($name in @('oauth','post','get')){$v=$Value.transport_invocations.$name;if(($v -isnot [long] -and $v -isnot [int]) -or $v -lt 0 -or $v -gt 1){return $false}}
    if(@($Value.events).Count -gt 32){return $false}
    foreach($event in $Value.events){
        if(@($event.PSObject.Properties.Name|Where-Object{$_ -cnotin @('phase','event','http_status','response_bytes','response_sha256','reason_code','error_class')}).Count -or $event.phase -cnotin $phases -or $event.event -cnotin @('enter','transport_invoked','reply','failure')){return $false}
        if($event.PSObject.Properties['reason_code'] -and $event.reason_code -cnotmatch '^[a-z_]{1,80}$'){return $false}
        if($event.PSObject.Properties['error_class'] -and $event.error_class -cnotin @('JsonException','TypeError','RuntimeException','InvalidArgumentException','Throwable')){return $false}
        if($event.PSObject.Properties['response_sha256'] -and $event.response_sha256 -cnotmatch '^[a-f0-9]{64}$'){return $false}
    }
    return $true
}
function ConvertTo-ABLeadWorkerInput([object]$Value) {
    $json=ConvertTo-Json -InputObject $Value -Depth 30 -Compress
    $bytes=[Text.UTF8Encoding]::new($false).GetBytes($json)
    if($bytes.Length -gt 196608){throw 'worker_input_bound'}
    return ,$bytes
}
function ConvertFrom-ABLeadWorkerOutput([string]$Stdout,[string]$Stderr,[int]$ExitCode,[bool]$TimedOut=$false) {
    $utf8=[Text.UTF8Encoding]::new($false)
    $hash={param([string]$v)[Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($utf8.GetBytes($v))).ToLowerInvariant()}
    $metadata=[ordered]@{protocol='lead-worker-2';exit_code=$ExitCode;timed_out=$TimedOut;stdout_bytes=$utf8.GetByteCount($Stdout);stderr_bytes=$utf8.GetByteCount($Stderr);stdout_sha256=(& $hash $Stdout);stderr_sha256=(& $hash $Stderr)}
    $reason=$null;$result=$null
    if($TimedOut){$reason='worker_timeout'}elseif($Stdout.Length -gt 262144){$reason='worker_output_bound'}else{
        try{
            $result=ConvertFrom-Json -InputObject $Stdout -Depth 40 -ErrorAction Stop
            $allowed=@('status','reason_code','creation','get_http','get_body','get_body_base64','get_body_sha256','get_body_bytes','observed_at','counts','automatic_retries','diagnostics')
            if($null -eq $result -or @($result.PSObject.Properties.Name|Where-Object{$_ -cnotin $allowed}).Count -ne 0 -or $result.status -cnotin @('held','observations_ready') -or $result.reason_code -cnotmatch '^[a-z_]{1,80}$' -or $result.automatic_retries -ne 0){throw 'worker_output_shape_invalid'}
            $creation=if($result.PSObject.Properties['creation']){$result.creation}else{$null};$diagnostics=if($result.PSObject.Properties['diagnostics']){$result.diagnostics}else{$null}
            if(!(Test-ABLeadCreation $creation) -or !(Test-ABLeadDiagnostics $diagnostics)){throw 'worker_output_shape_invalid'}
            if($result.PSObject.Properties['get_body_base64']){
                if($result.get_body_base64 -isnot [string] -or $result.get_body_base64.Length -gt 87384){throw 'worker_output_shape_invalid'}
                $body=[Convert]::FromBase64String($result.get_body_base64);if($body.Length -gt 65536){throw 'worker_output_shape_invalid'}
                if($result.PSObject.Properties['get_body_sha256'] -and [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($body)).ToLowerInvariant() -cne $result.get_body_sha256){throw 'worker_output_shape_invalid'}
            }
            if(!$result.PSObject.Properties['creation']){$result|Add-Member -NotePropertyName creation -NotePropertyValue $null}
            if(!$result.PSObject.Properties['diagnostics']){$result|Add-Member -NotePropertyName diagnostics -NotePropertyValue @{phase='legacy_unknown';transport_invocations=$null}}
        }catch{$reason='worker_output_invalid';$result=$null}
    }
    if($reason){$result=[pscustomobject]@{status='held';reason_code=$reason;creation=$null;automatic_retries=0;diagnostics=@{phase='worker_protocol';transport_invocations=$null;historical_counts='unknown'}}}
    return [pscustomobject]@{metadata=$metadata;worker_result=$result}
}
function Save-ABLeadWorkerReceipt([string]$Directory,[string]$IntentId,[object]$Receipt) {
    if($IntentId -cnotmatch '^[a-f0-9]{64}$'){throw 'worker_receipt_identity_invalid'}
    $path=Join-Path $Directory ('lead-observation-'+$IntentId+'-'+[guid]::NewGuid().ToString('N')+'.json')
    $stream=[IO.File]::Open($path,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None)
    try{$bytes=[Text.UTF8Encoding]::new($false).GetBytes((ConvertTo-Json -InputObject $Receipt -Depth 40 -Compress));$stream.Write($bytes,0,$bytes.Length);$stream.Flush($true)}finally{$stream.Dispose()}
    return $path
}
