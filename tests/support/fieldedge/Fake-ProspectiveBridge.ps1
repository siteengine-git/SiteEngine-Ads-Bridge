# Process-protocol DOUBLE ONLY. No SSH, durable state, secrets or provider network.
param([string]$RequestFile,[string]$RequestSha256)
$ErrorActionPreference='Stop'
$q=Get-Content -LiteralPath $RequestFile -Raw|ConvertFrom-Json
$r=$q.route_json|ConvertFrom-Json;$c=$q.card_json|ConvertFrom-Json;$i=$c.intent_json|ConvertFrom-Json
$n=[int]([IO.Path]::GetFileNameWithoutExtension($RequestFile).Split('-')[-1])
$id='1'*64;$permit='2'*64
$claim=[ordered]@{permit=$permit;body_sha256=$i.request.body_sha256;dispatch_mode='synthetic_transport_only'}
$body='{"data":{"leadId":"00000000-0000-4000-8000-000000000601","status":0}}'
$hash=([BitConverter]::ToString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($body)))).Replace('-','').ToLowerInvariant()
$obs=[ordered]@{contract_version='1';request_body_sha256=$i.request.body_sha256;classification='success';http_status=201;response_sha256=$hash;lead_id='00000000-0000-4000-8000-000000000601';lead_status='code:0';received_at=$null}
$state='prepared';if($n -ge 6){$state='claimed'};if($n -ge 8){$state='succeeded'}
$prospective=[ordered]@{contract_version='1';status=$state;intent_id=$id;body_sha256=$i.request.body_sha256;intent_json=$c.intent_json;claim=$(if($n -ge 6){$claim}else{$null});observation=$(if($n -ge 8){$obs}else{$null});mapping=$(if($n -ge 8){[ordered]@{provider='fieldedge';tenant_id=$i.tenant_id;lead_id=$obs.lead_id;inquiry_id=$i.inquiry_id;source=$i.source}}else{$null})}
switch($q.operation){
 'review'{$result=[ordered]@{contract_version='1';status='prepared';intent_id=$id;body_sha256=$i.request.body_sha256;card_sha256=$q.card_sha256;production_post_enabled=$false}}
 'read'{$result=[ordered]@{contract_version='1';status='reviewed';card_sha256=$q.card_sha256;card_json=$q.card_json;production_post_enabled=$false;prospective=$prospective}}
 'claim'{$result=[ordered]@{contract_version='1';status='claimed';intent_id=$id;body_sha256=$i.request.body_sha256;card_sha256=$q.card_sha256;production_post_enabled=$false;permit=$(if($i.operation_id -eq 'bad-permit'){'3'*64}else{$permit});dispatch_mode='synthetic_transport_only'}}
 'observe'{$result=$prospective}
 default{exit 2}
}
$reply=[ordered]@{contract_version='1';request_sha256=$RequestSha256;route_sha256=$q.route_sha256;card_sha256=$q.card_sha256;operation=$q.operation;ok=$true;result=$result;execution=[ordered]@{connection_id=1000+$n;transaction_absent=$true;coordination_lock_free=$true;owner_uid=1001;site_verified=$true;production_post_enabled=$false}}
switch($i.operation_id){
 'fail-after-double-read'{if($n -eq 7){exit 2}}
 'fail-after-double-observe'{if($n -eq 8){exit 2}}
 'bad-reply'{$reply.request_sha256='f'*64}
 'locked'{$reply.execution.coordination_lock_free=$false}
 'nonzero'{exit 2}
 'oversized'{[Console]::Out.Write(('x'*131073));exit 0}
}
$reply|ConvertTo-Json -Depth 35 -Compress
