<?php
/** FE exact request boundary; AB alone owns durable prepare/claim/observation. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;
require_once __DIR__ . '/LeadCreateDryRun.php';
require_once __DIR__ . '/LeadResponse.php';
final class LeadSubmission {
	private \Closure $prepare;
	private \Closure $claim;
	private \Closure $observe;
	private \Closure $read;
	private ?\Closure $transport;
	/** Test doubles use AB's exact supplied signatures, never WordPress API stubs. */
	public function __construct( ?callable $prepare = null, ?callable $claim = null, ?callable $observe = null, ?callable $read = null, ?callable $transport = null ) {
		$this->prepare = \Closure::fromCallable( $prepare ?? static fn( string $json ): array => \seab_prospective_prepare( $json ) );
		$this->claim = \Closure::fromCallable( $claim ?? static fn( string $id, string $sha ): array => \seab_prospective_claim( $id, $sha ) );
		$this->observe = \Closure::fromCallable( $observe ?? static fn( string $id, string $permit, string $json ): array => \seab_prospective_observe( $id, $permit, $json ) );
		$this->read = \Closure::fromCallable( $read ?? static fn( string $id ): array => \seab_prospective_read( $id ) );
		$this->transport = null === $transport ? null : \Closure::fromCallable( $transport );
	}
	/** Caller supplies canonical AB context; AB verifies against actual stored inquiry. */
	public static function envelope( array $dry, array $context, string $operation ): string {
		LeadCreateDryRun::decide( $dry );
		$id = Foundation::id( $context['inquiry_id'] ?? null );
		if ( strlen( $id ) > 40 ) { throw new \InvalidArgumentException( 'unsupported_ab_inquiry_id' ); }
		$s = $context['source'] ?? array(); $original = $dry['local_inquiry']['source'];
		if ( ( $s['type'] ?? null ) === 'fluent_form' ) {
			if ( ! is_int( $s['form_id'] ?? null ) || $s['form_id'] < 1 || ! is_int( $s['entry_id'] ?? null ) || $s['entry_id'] < 1 ||
				( $s['transaction_id'] ?? null ) !== 'ff:' . $s['form_id'] . ':' . $s['entry_id'] ||
				$original !== array( 'type' => 'fluent_form', 'form_id' => $s['form_id'], 'entry_id' => $s['entry_id'] ) ) { throw new \InvalidArgumentException( 'original_form_binding_mismatch' ); }
			$source = array( 'type' => 'fluent_form', 'form_id' => $s['form_id'], 'entry_id' => $s['entry_id'], 'transaction_id' => $s['transaction_id'] );
		} elseif ( ( $s['type'] ?? null ) === 'call' ) {
			if ( ! is_string( $s['identity'] ?? null ) || ! preg_match( '/\Atwilio:CA[a-f0-9]{32}\z/D', $s['identity'] ) ||
				$original !== array( 'type' => 'call', 'evidence_id' => $s['evidence_id'] ?? null ) ) { throw new \InvalidArgumentException( 'original_call_binding_mismatch' ); }
			$source = array( 'type' => 'call', 'identity' => $s['identity'], 'evidence_id' => Foundation::id( $s['evidence_id'] ) );
		} else { throw new \InvalidArgumentException( 'explicit_ab_source_required' ); }
		if ( strlen( $dry['body'] ) > 16384 ) { throw new \InvalidArgumentException( 'lead_body_too_large' ); }
		return json_encode( array( 'contract_version' => '1', 'provider' => 'fieldedge', 'tenant_id' => $dry['tenant_id'], 'operation_id' => Foundation::id( $operation ),
			'inquiry_id' => $id, 'source' => $source, 'request' => array( 'method' => 'POST', 'endpoint' => LeadResponse::URL, 'body' => $dry['body'], 'body_sha256' => $dry['body_sha256'] ), 'synthetic' => true ), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
	}
	private static function base( array $row, string $json, string $id, string $sha ): bool {
		return ( $row['contract_version'] ?? null ) === '1' && ( $row['intent_id'] ?? null ) === $id &&
			( $row['body_sha256'] ?? null ) === $sha && ( $row['intent_json'] ?? null ) === $json;
	}
	private static function result( array $row, array $intent ): array {
		$o = $row['observation'] ?? null; $status = $row['status'] ?? null;
		if ( ! is_array( $o ) || ( $o['contract_version'] ?? null ) !== '1' || ( $o['request_body_sha256'] ?? null ) !== $intent['request']['body_sha256'] ||
			! is_string( $o['response_sha256'] ?? null ) || ! preg_match( '/\A[a-f0-9]{64}\z/D', $o['response_sha256'] ) ||
			( $row['claim']['dispatch_mode'] ?? null ) !== 'synthetic_transport_only' || ( $row['claim']['body_sha256'] ?? null ) !== $row['body_sha256'] ||
			! is_string( $row['claim']['permit'] ?? null ) || ! preg_match( '/\A[a-f0-9]{64}\z/D', $row['claim']['permit'] ) ) { throw new \RuntimeException(); }
		if ( $status === 'succeeded' ) {
			$id = $o['lead_id'] ?? null;
			if ( ( $o['classification'] ?? null ) !== 'success' || ( $o['http_status'] ?? null ) !== 201 ||
				! is_string( $id ) || ! preg_match( '/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/Di', $id ) ||
				( $row['mapping'] ?? null ) !== array( 'provider' => 'fieldedge', 'tenant_id' => $intent['tenant_id'], 'lead_id' => $id, 'inquiry_id' => $intent['inquiry_id'], 'source' => $intent['source'] ) ) { throw new \RuntimeException(); }
		} elseif ( $status === 'validation_failed' ) {
			if ( ( $o['classification'] ?? null ) !== 'validation_failed' || ! in_array( $o['http_status'] ?? null, array( 400, 422 ), true ) || ( $row['mapping'] ?? null ) !== null ) { throw new \RuntimeException(); }
		} elseif ( $status === 'uncertain' ) {
			if ( ( $o['classification'] ?? null ) !== 'uncertain' || ( $row['mapping'] ?? null ) !== null ) { throw new \RuntimeException(); }
		} else { throw new \RuntimeException(); }
		return array( 'status' => $status, 'intent_id' => $row['intent_id'], 'body_sha256' => $row['body_sha256'], 'receipt_verified' => true,
			'lead_id' => $o['lead_id'] ?? null, 'mapping' => $row['mapping'] ?? null, 'export_eligible' => false );
	}
	/** No retry/reclaim. All external execution occurs after AB claim/read calls return. */
	public function send( string $json ): array {
		$held = static fn( string $reason ) => array( 'status' => 'held', 'reason_code' => $reason, 'receipt_verified' => false, 'export_eligible' => false );
		try {
			$i = json_decode( $json, true, 16, JSON_THROW_ON_ERROR ); $r = $i['request'] ?? array();
			if ( strlen( $json ) > 24576 || ( $i['contract_version'] ?? null ) !== '1' || ( $i['provider'] ?? null ) !== 'fieldedge' || ( $i['synthetic'] ?? null ) !== true ||
				! LeadResponse::allowed( $r['method'] ?? '', $r['endpoint'] ?? '' ) || ! is_string( $r['body'] ?? null ) || strlen( $r['body'] ) > 16384 ||
				( $r['body_sha256'] ?? null ) !== hash( 'sha256', $r['body'] ) ) { return $held( 'intent_envelope_refused' ); }
			$sha = $r['body_sha256'];
			$p = ( $this->prepare )( $json );
			if ( ( $p['status'] ?? null ) === 'conflict' ) { return $held( 'ab_intent_conflict' ); }
			$id = $p['intent_id'] ?? null;
			if ( ( $p['contract_version'] ?? null ) !== '1' || ! in_array( $p['status'] ?? null, array( 'prepared', 'replayed' ), true ) ||
				( $p['body_sha256'] ?? null ) !== $sha || ! is_string( $id ) || ! preg_match( '/\A[a-f0-9]{64}\z/D', $id ) ) { return $held( 'ab_prepare_unconfirmed' ); }
			$row = ( $this->read )( $id );
			if ( ! self::base( $row, $json, $id, $sha ) ) { return $held( 'ab_intent_readback_mismatch' ); }
			if ( in_array( $row['status'] ?? null, array( 'succeeded', 'validation_failed', 'uncertain' ), true ) ) { return self::result( $row, $i ); }
			if ( ( $row['status'] ?? null ) !== 'prepared' || ( $row['claim'] ?? null ) !== null || ( $row['observation'] ?? null ) !== null ) { return $held( 'already_claimed_reconciliation_required' ); }
			if ( null === $this->transport ) { return $held( 'live_lead_transport_disabled' ); }
			$c = ( $this->claim )( $id, $sha ); $permit = $c['permit'] ?? null;
			if ( ( $c['contract_version'] ?? null ) !== '1' || ( $c['status'] ?? null ) !== 'claimed' || ( $c['intent_id'] ?? null ) !== $id ||
				( $c['body_sha256'] ?? null ) !== $sha || ( $c['dispatch_mode'] ?? null ) !== 'synthetic_transport_only' ||
				! is_string( $permit ) || ! preg_match( '/\A[a-f0-9]{64}\z/D', $permit ) ) { return $held( 'ab_claim_unconfirmed' ); }
			$row = ( $this->read )( $id );
			if ( ! self::base( $row, $json, $id, $sha ) || ( $row['status'] ?? null ) !== 'claimed' ||
				( $row['claim'] ?? null ) !== array( 'permit' => $permit, 'body_sha256' => $sha, 'dispatch_mode' => 'synthetic_transport_only' ) ||
				( $row['observation'] ?? null ) !== null ) { return $held( 'ab_claim_readback_mismatch' ); }
			try { $response = ( $this->transport )( $r['method'], $r['endpoint'], $r['body'] ); } catch ( \Throwable $error ) { $response = null; }
			$e = LeadResponse::classify( is_array( $response ) ? $response : null ); unset( $response );
			$o = array( 'contract_version' => '1', 'request_body_sha256' => $sha, 'classification' => $e['classification'], 'http_status' => $e['http_status'],
				'response_sha256' => $e['response_sha256'] ?? hash( 'sha256', '' ), 'lead_id' => $e['lead_id'], 'lead_status' => $e['provider_status'], 'received_at' => $e['received_at'] );
			$observation = json_encode( $o, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
			try { ( $this->observe )( $id, $permit, $observation ); } catch ( \Throwable $error ) { /* Fresh durable read is authority after a lost reply. */ }
			$row = ( $this->read )( $id );
			if ( ! self::base( $row, $json, $id, $sha ) || ( $row['observation'] ?? null ) !== $o ||
				( $row['claim'] ?? null ) !== array( 'permit' => $permit, 'body_sha256' => $sha, 'dispatch_mode' => 'synthetic_transport_only' ) ) { return $held( 'ab_observation_unconfirmed_reconcile' ); }
			return self::result( $row, $i );
		} catch ( \Throwable $error ) { return $held( 'prospective_execution_unconfirmed_reconcile' ); }
	}
}
