<?php
require_once __DIR__ . '/../includes/connectors/fieldedge/LeadSubmission.php';
require_once __DIR__ . '/support/fieldedge/LeadFixture.php';
require_once __DIR__ . '/support/fieldedge/SyntheticProspectiveApi.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadSubmission as S;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadCreateDryRun as L;
use SiteEngine\AdsBridge\Tests\FieldEdge\LeadFixture as F;
use SiteEngine\AdsBridge\Tests\FieldEdge\SyntheticProspectiveApi as API;
$n = 0;
function submissionCheck( bool $ok, string $label ): void { global $n; if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $label ); } ++$n; echo "PASS: $label\n"; }
function sender( API $a, ?callable $t ): S { return new S( array($a,'prepare'), array($a,'claim'), array($a,'observe'), array($a,'read'), $t ); }
$dry = L::prepare( F::input() ); $ctx = array( 'inquiry_id' => 'inq-context-test-1', 'source' => array( 'type' => 'fluent_form', 'form_id' => 7, 'entry_id' => 101, 'transaction_id' => 'ff:7:101' ) );
$json = S::envelope( $dry, $ctx, 'synthetic-operation-1' );
$success = array( 'status' => 201, 'content_type' => 'application/json', 'body' => '{"data":{"leadId":"00000000-0000-4000-8000-000000000201","status":"Initiated","receivedAt":"2026-11-11T18:00:00Z"}}' );
$a = new API();
submissionCheck( sender($a,null)->send($json)['reason_code'] === 'live_lead_transport_disabled' && current($a->rows)['claim'] === null, 'disabled default never claims or dispatches' );
$posts = 0;
$t = static function( $m, $u, $body ) use ( $a, &$posts, $dry, $success ) {
	++$posts; $row = current($a->rows);
	submissionCheck( $row['status'] === 'claimed' && $row['claim']['permit'] && end($a->calls) === 'read', 'fresh durable claim read completes before transport callback' );
	submissionCheck( $body === $dry['body'] && $m === 'POST' && $u === 'https://api.fieldedge.com/open-api/v1/leads', 'transport receives one exact allowlisted body' );
	return $success;
};
$r = sender($a,$t)->send($json);
submissionCheck( $r['status'] === 'succeeded' && $r['receipt_verified'] && $r['mapping']['inquiry_id'] === $ctx['inquiry_id'] && !$r['export_eligible'], 'success authority is fresh AB observation/mapping' );
submissionCheck( sender($a,$t)->send($json)['status'] === 'succeeded' && $posts === 1, 'exact success replay performs no second transport' );
$altered = json_decode($json,true); $altered['operation_id'] = 'rotated-operation';
submissionCheck( sender($a,$t)->send(json_encode($altered))['reason_code'] === 'ab_intent_conflict' && $posts === 1, 'operation rotation cannot redispatch same originating inquiry' );
foreach ( array(400,422,409,500) as $http ) {
	$b = new API(); $count = 0;
	$fn = static function() use(&$count,$http) { ++$count; return array('status'=>$http,'body'=>'PRIVATE-ERROR'); };
	$x = sender($b,$fn); $first = $x->send($json); $again = $x->send($json);
	submissionCheck( $first['status'] === (in_array($http,array(400,422),true)?'validation_failed':'uncertain') && $again['status'] === $first['status'] && $count === 1, 'HTTP'.$http.' durable hold replays without retry' );
	submissionCheck( !str_contains(json_encode($b->rows),'PRIVATE-ERROR'), 'provider error body absent from durable observation double' );
}
$b = new API(); $count = 0; $fn = static function() use(&$count) { ++$count; throw new RuntimeException('PRIVATE-SECRET'); }; $x = sender($b,$fn);
submissionCheck( $x->send($json)['status'] === 'uncertain' && $x->send($json)['status'] === 'uncertain' && $count === 1, 'ambiguous timeout durably held no automatic POST retry' );
$b = new API(); $b->failObserve = true; $count = 0; $fn = static function() use(&$count,$success) { ++$count; return $success; }; $x = sender($b,$fn);
submissionCheck( $x->send($json)['reason_code'] === 'ab_observation_unconfirmed_reconcile' && $x->send($json)['reason_code'] === 'already_claimed_reconciliation_required' && $count === 1, 'lost observation persistence leaves original exclusive claim blocking redispatch' );
$b = new API(); $b->loseObserveReply = true;
submissionCheck( sender($b,static fn()=>$success)->send($json)['status'] === 'succeeded', 'lost observe reply recovers only through fresh stored observation' );
$b = new API(); $b->badRead = true; $count = 0; $fn = static function() use(&$count,$success) {++$count;return $success;};
submissionCheck( sender($b,$fn)->send($json)['reason_code'] === 'ab_intent_readback_mismatch' && $count === 0, 'changed envelope readback blocks before claim/transport' );
$b = new API(); $b->badPermitRead = true;
submissionCheck( sender($b,$fn)->send($json)['reason_code'] === 'ab_claim_readback_mismatch' && $count === 0, 'changed permit readback blocks dispatch' );
$b = new API(); $bad = json_decode($json,true); $bad['request']['endpoint'] .= '?redirect=1';
submissionCheck( sender($b,$fn)->send(json_encode($bad))['reason_code'] === 'intent_envelope_refused' && !$b->rows && !$count, 'alternate endpoint refused before durable prepare' );
submissionCheck( (new S())->send($json)['status'] === 'held', 'missing actual AB interface holds rather than pretending installed success' );
echo "RESULT: $n PASS, 0 FAIL; in-memory exact-contract doubles only; NOT installed persistence.\n";
