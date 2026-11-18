<?php
/** Synthetic only: no WordPress, database, provider network or export sink. */
require_once __DIR__ . '/../includes/connectors/fieldedge/Foundation.php';
require_once __DIR__ . '/../includes/connectors/fieldedge/Retry.php';

use SiteEngine\AdsBridge\Connectors\FieldEdge\Foundation as F;
use SiteEngine\AdsBridge\Connectors\FieldEdge\Retry;

$passed = 0;
function check( bool $ok, string $label ): void {
	global $passed;
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL: $label\n" );
		exit( 1 );
	}
	++$passed;
	echo "PASS: $label\n";
}
function rejects( callable $call, string $label ): void {
	try {
		$call();
	} catch ( InvalidArgumentException $error ) {
		check( true, $label );
		return;
	}
	check( false, $label );
}

/** Proposed Ads-owned boundary TEST DOUBLE. No durability/concurrency guarantee. */
final class CoreDouble {
	public array $events = array();
	public array $outcomes = array();
	public array $conversions = array();
	public array $history = array();
	public array $consent = array();
	public array $dnc = array();
	public array $binding = array( 'inq-old' => 'ff:7:101', 'inq-new' => 'ff:7:102' );

	public function submit( array $event, array $outcome ): string {
		$key = $event['tenant_id'] . ':' . $event['event_id'];
		$receipt = F::digest( array( $event, $outcome ) );
		if ( isset( $this->events[ $key ] ) ) {
			return hash_equals( $this->events[ $key ], $receipt ) ? 'event_replay' : 'event_conflict';
		}
		$this->events[ $key ] = $receipt;
		$this->history[] = $outcome;
		if ( 'candidate' !== $outcome['status'] ) {
			return $outcome['reason_code'];
		}
		$inquiry = $outcome['inquiry_id'];
		if ( ! isset( $this->binding[ $inquiry ] ) ) {
			return 'inquiry_unbound';
		}
		// In real Ads this MUST read canonical receipt/withdrawal/current DNC itself.
		if ( true !== ( $this->consent[ $inquiry ] ?? null ) || false !== ( $this->dnc[ $inquiry ] ?? null ) ) {
			return 'consent_or_dnc_hold';
		}
		$key = $outcome['economic_key'];
		if ( isset( $this->outcomes[ $key ] ) ) {
			return F::revision( $this->outcomes[ $key ], $outcome );
		}
		// Model current one-conversion-per-inquiry/action limitation explicitly.
		$conversion = $this->binding[ $inquiry ] . ':converted';
		if ( isset( $this->conversions[ $conversion ] ) ) {
			return 'inquiry_multiple_jobs_requires_policy';
		}
		$this->outcomes[ $key ] = $outcome;
		$this->conversions[ $conversion ] = $outcome;
		return 'accepted_by_double';
	}
}

$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/fieldedge/records.json' ), true, 16, JSON_THROW_ON_ERROR );
$tenant = $fixture['tenant'];
$work = $fixture['work'];
$invoice = $fixture['invoice'];
$lead = F::lead( $fixture['lead'] );
$link = array(
	'tenant_id' => $tenant, 'work_order_id' => $work['workOrderId'],
	'invoice_id' => $invoice['invoiceId'], 'customer_id' => $lead['customer_id'],
	'lead_id' => $lead['lead_id'], 'inquiry_id' => 'inq-old',
	'basis' => 'reviewed_explicit', 'evidence_id' => 'association-synthetic-1',
);
$approval = array(
	'tenant_id' => $tenant, 'invoice_id' => $invoice['invoiceId'],
	'invoice_sha256' => F::digest( $invoice ), 'approved' => true,
	'amount' => '1250.00', 'currency' => 'USD',
	'approval_id' => 'synthetic-approval-1', 'policy_id' => 'synthetic-invoiced-value-v1',
);
$candidate = F::outcome( $tenant, $work, $invoice, array( $link ), $approval );
check( 'candidate' === $candidate['status'] && $candidate['lead_id'] === $lead['lead_id'], 'known lead explicitly linked to inquiry' );
check( ! isset( $lead['email'] ) && ! isset( $lead['marketingEmailOptedIn'] ), 'PII and provider marketing flag excluded' );
check( '1250.00' === $candidate['amount'] && 'USD' === $candidate['currency'], 'approved decimal value and currency preserved' );
check( $candidate['completed_at'] === $work['workOrderInformation']['completedDate'], 'original seven-digit timestamp preserved' );
check( false === $candidate['export_eligible'] && ! isset( $candidate['transaction_id'] ), 'foundation grants no export authority or Google identity' );
$new_link = array_replace( $link, array( 'inquiry_id' => 'inq-new' ) );
check( 'inq-new' === F::outcome( $tenant, $work, $invoice, array( $new_link ), $approval )['inquiry_id'], 'new inquiry can explicitly reference known provider lead' );
$mismatched_work = array_replace( $work, array( 'invoiceId' => $work['billingCustomerId'] ) );
check( 'association_unproven' === F::outcome( $tenant, $mismatched_work, $invoice, array( $link ), $approval )['reason_code'], 'provider invoice link mismatch held' );
check( 'value_approval_missing_or_stale' === F::outcome( $tenant, $work, $invoice, array( $link ), array() )['reason_code'], 'missing value approval held' );
check( 'value_approval_missing_or_stale' === F::outcome( $tenant, $work, $invoice, array( $link ), array_replace( $approval, array( 'tenant_id' => $work['workOrderId'] ) ) )['reason_code'], 'cross-tenant value approval held' );
check( 'value_or_currency_requires_review' === F::outcome( $tenant, $work, $invoice, array( $link ), array_replace( $approval, array( 'amount' => '0.00' ) ) )['reason_code'], 'zero-value outcome held' );
check( 'value_or_currency_requires_review' === F::outcome( $tenant, $work, $invoice, array( $link ), array_replace( $approval, array( 'currency' => '' ) ) )['reason_code'], 'missing approved currency held' );
rejects( fn() => F::outcome( $tenant, array_replace( $work, array( 'workOrderInformation' => array() ) ), $invoice, array( $link ), $approval ), 'missing completion timestamp rejected' );
rejects( fn() => F::lead( array( 'data' => array( 'leadId' => '../untrusted' ) ) ), 'invalid provider identity rejected' );

$body = json_encode( $fixture['notification'], JSON_THROW_ON_ERROR );
$secret = 'synthetic-test-only-not-a-provider-secret';
$sign = static fn( $raw ) => 'sha256=' . hash_hmac( 'sha256', $raw, $secret );
$event = F::notification( $body, $sign( $body ), $secret, $tenant );
check( true === $event['requires_fetch'] && $event['body_sha256'] === hash( 'sha256', $body ), 'signed notification requires authenticated record fetch' );
rejects( fn() => F::notification( $body . ' ', $sign( $body ), $secret, $tenant ), 'body tampering rejected' );
rejects( fn() => F::notification( $body, 'sha256=' . str_repeat( 'a', 64 ), $secret, $tenant ), 'wrong signature rejected' );
rejects( fn() => F::notification( $body, $sign( $body ), '', $tenant ), 'empty secret rejected' );
rejects( fn() => F::notification( $body, $sign( $body ), $secret, $work['workOrderId'] ), 'cross-tenant event rejected' );
rejects( fn() => F::notification( '{', $sign( '{' ), $secret, $tenant ), 'signed malformed JSON rejected' );
$oversized = str_repeat( ' ', 262145 );
rejects( fn() => F::notification( $oversized, $sign( $oversized ), $secret, $tenant ), 'oversized signed body rejected' );
$bad = $fixture['notification'];
$bad['type'] = 'invoice.deleted';
$bad_body = json_encode( $bad );
rejects( fn() => F::notification( $bad_body, $sign( $bad_body ), $secret, $tenant ), 'undocumented event held' );

$core = new CoreDouble();
$core->consent = array( 'inq-old' => true, 'inq-new' => true );
$core->dnc = array( 'inq-old' => false, 'inq-new' => false );
check( 'accepted_by_double' === $core->submit( $event, $candidate ), 'completed work associated with old inquiry, not latest contact inquiry [double]' );
check( isset( $core->conversions['ff:7:101:converted'] ) && ! isset( $core->conversions['ff:7:102:converted'] ), 'core-owned frozen transaction preserved [double]' );
check( 'event_replay' === $core->submit( $event, $candidate ) && 1 === count( $core->history ), 'replayed notification produces no duplicate processing [double]' );
$changed = $candidate;
$changed['amount'] = '1400.00';
check( 'event_conflict' === $core->submit( $event, $changed ), 'same event identity with changed body/outcome conflicts [double]' );
$next_event = $event;
$next_event['event_id'] = '77777777-7777-4777-8777-777777777777';
check( 'replay' === $core->submit( $next_event, $candidate ) && 1 === count( $core->conversions ), 'different notification same economic outcome creates no new conversion [double]' );

$revised_invoice = $invoice;
$revised_invoice['total'] = 1400;
$stale = F::outcome( $tenant, $work, $revised_invoice, array( $link ), $approval );
check( 'value_approval_missing_or_stale' === $stale['reason_code'], 'invoice revision invalidates prior value approval' );
$revised_approval = $approval;
$revised_approval['amount'] = '1400.00';
$revised_approval['invoice_sha256'] = F::digest( $revised_invoice );
$revised_approval['approval_id'] = 'synthetic-approval-2';
$revised = F::outcome( $tenant, $work, $revised_invoice, array( $link ), $revised_approval );
$next_event['event_id'] = '88888888-8888-4888-8888-888888888888';
check( 'revision_review' === $core->submit( $next_event, $revised ), 'revised value held under same economic identity [double]' );
check( 1 === count( $core->conversions ) && '1250.00' === $core->conversions['ff:7:101:converted']['amount'], 'revision preserves conversion and original value [double]' );
check( count( $core->history ) === 3, 'original, semantic replay and revision evidence retained [double]' );

$other_work = $work;
$other_work['workOrderId'] = '99999999-9999-4999-8999-999999999999';
$other_work['invoiceId'] = $other_work['workOrderId'];
$other_invoice = $invoice;
$other_invoice['invoiceId'] = $other_work['workOrderId'];
$other_approval = $approval;
$other_approval['invoice_id'] = $other_invoice['invoiceId'];
$other_approval['invoice_sha256'] = F::digest( $other_invoice );
$unrelated = F::outcome( $tenant, $other_work, $other_invoice, array( $link ), $other_approval );
check( 'association_missing' === $unrelated['reason_code'], 'unrelated repeat-customer job held despite same customer' );
$second_link = $link;
$second_link['work_order_id'] = $other_work['workOrderId'];
$second_link['invoice_id'] = $other_invoice['invoiceId'];
$second_link['inquiry_id'] = 'inq-new';
$second = F::outcome( $tenant, $other_work, $other_invoice, array( $link, $second_link ), $other_approval );
check( 'inq-new' === $second['inquiry_id'] && 'inq-old' === $candidate['inquiry_id'], 'multiple inquiries and jobs retain separate explicit links' );
$next_event['event_id'] = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
check( 'accepted_by_double' === $core->submit( $next_event, $second ) && 2 === count( $core->conversions ), 'second inquiry gets only its own job value [double]' );
$same_inquiry = $second;
$same_inquiry['inquiry_id'] = 'inq-old';
$fresh_core = new CoreDouble();
$fresh_core->consent = $core->consent;
$fresh_core->dnc = $core->dnc;
$fresh_core->submit( $event, $candidate );
check( 'inquiry_multiple_jobs_requires_policy' === $fresh_core->submit( $next_event, $same_inquiry ), 'multiple jobs for one inquiry require aggregation policy [double]' );
$ambiguous = F::outcome( $tenant, $work, $invoice, array( $link, array_replace( $link, array( 'inquiry_id' => 'inq-new' ) ) ), $approval );
check( 'association_ambiguous' === $ambiguous['reason_code'], 'ambiguous association held' );
$phone_link = array_replace( $link, array( 'basis' => 'phone_match' ) );
check( 'association_unproven' === F::outcome( $tenant, $work, $invoice, array( $phone_link ), $approval )['reason_code'], 'phone match never establishes advertising attribution' );
$wrong_customer = array_replace( $link, array( 'customer_id' => $work['billingCustomerId'] ) );
check( 'association_unproven' === F::outcome( $tenant, $work, $invoice, array( $wrong_customer ), $approval )['reason_code'], 'billing customer does not silently replace service customer' );
$canceled = array_replace( $work, array( 'resolution' => 'Canceled' ) );
check( 'work_not_proven_completed' === F::outcome( $tenant, $canceled, $invoice, array( $link ), $approval )['reason_code'], 'canceled/revised work held' );

foreach ( array( 'missing', 'withdrawn', 'dnc', 'unknown_dnc' ) as $case ) {
	$blocked = new CoreDouble();
	$blocked->consent['inq-old'] = 'missing' === $case ? null : 'withdrawn' !== $case;
	$blocked->dnc['inq-old'] = 'unknown_dnc' === $case ? null : 'dnc' === $case;
	check( 'consent_or_dnc_hold' === $blocked->submit( $event, $candidate ) && ! $blocked->conversions, "$case blocks eligibility [double]" );
}
foreach ( array( '2026-02-30T10:00:00Z', '2026-11-01T14:00:00', '2026-11-01T25:00:00Z', 'tomorrow' ) as $invalid ) {
	rejects( fn() => F::timestamp( $invalid ), 'invalid/zoneless timestamp rejected: ' . $invalid );
}
foreach ( array( 12.50, '1e2', '-1.00', '0.001', 'NaN', '01.00' ) as $invalid ) {
	rejects( fn() => F::amount( $invalid ), 'lossy/noncanonical money rejected' );
}
check( F::digest( array( 'b' => 2, 'a' => 1 ) ) === F::digest( array( 'a' => 1, 'b' => 2 ) ), 'snapshot digest ignores object member order' );
check( 'deferred' === Retry::classify( 429, '', '12', 0, 100 )['state'] && 112 === Retry::classify( 429, '', '12', 0, 100 )['not_before'], '429 respects documented delay' );
check( 3700 === Retry::classify( 429, '', '3600', 0, 100 )['not_before'], 'long Retry-After never shortened' );
check( 'retry_after_requires_review' === Retry::classify( 429, '', 'tomorrow', 0, 100 )['reason_code'], 'malformed Retry-After held' );
check( 3600 === Retry::classify( 403, 'Out of call volume quota. Quota will be replenished in 00:21:37.', null, 0, 100 )['not_before'], 'documented quota pauses partner to UTC hour' );
check( 'held' === Retry::classify( 403, 'Forbidden', null, 0, 100 )['state'], 'authorization 403 not blindly retried' );
check( 'held' === Retry::classify( 401, '', null, 0, 100 )['state'], 'unauthorized authentication held' );
check( 'deferred' === Retry::classify( 503, '', null, 2, 100 )['state'], 'transient GET can be deferred' );
check( 'retry_budget_exhausted' === Retry::classify( 503, '', null, 5, 100 )['reason_code'], 'retry budget bounded' );
check( 'complete' === Retry::classify( 200, '', null, 5, 100 )['state'], 'successful response does not trigger retry-budget failure' );
echo "RESULT: $passed PASS, 0 FAIL; synthetic foundation + CoreDouble only; no installed/E2E acceptance.\n";
