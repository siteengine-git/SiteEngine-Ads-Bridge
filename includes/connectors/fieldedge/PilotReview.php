<?php
/** Exact review-only card wrapper. No production permit, transport or storage. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;
require_once __DIR__ . '/LeadSubmission.php';
require_once __DIR__ . '/PilotPayload.php';
final class PilotReview {
	private string $json;
	private string $sha;
	private array $card;
	private \Closure $review;
	private \Closure $read;
	private \Closure $claim;
	private \Closure $observe;
	public function __construct( string $card_json, ?callable $review = null, ?callable $read = null, ?callable $claim = null, ?callable $observe = null ) {
		if ( strlen( $card_json ) > 32768 ) { throw new \InvalidArgumentException( 'pilot_card_too_large' ); }
		$this->card = json_decode( $card_json, true, 20, JSON_THROW_ON_ERROR ); $this->json = $card_json; $this->sha = hash( 'sha256', $card_json );
		$this->review = \Closure::fromCallable( $review ?? static fn( string $json ): array => \seab_prospective_pilot_review( $json ) );
		$this->read = \Closure::fromCallable( $read ?? static fn( string $sha ): array => \seab_prospective_pilot_read( $sha ) );
		$this->claim = \Closure::fromCallable( $claim ?? static fn( string $sha, string $id, string $digest ): array => \seab_prospective_pilot_claim( $sha, $id, $digest ) );
		$this->observe = \Closure::fromCallable( $observe ?? static fn( string $id, string $permit, string $json ): array => \seab_prospective_observe( $id, $permit, $json ) );
	}
	private function valid(): bool {
		$c = $this->card;
		if ( array_diff( array_keys( $c ), array( 'contract_version', 'mode', 'card_id', 'target', 'intent_json', 'request_budget', 'unresolved', 'subject_kind' ) ) ||
			( $c['contract_version'] ?? null ) !== '1' || ( $c['mode'] ?? null ) !== 'review_only' || ( $c['subject_kind'] ?? null ) !== 'synthetic' ||
			( $c['unresolved'] ?? null ) !== array() || ! is_array( $c['request_budget'] ?? null ) ||
			array_diff( array_keys( $c['request_budget'] ), array( 'oauth_token_posts', 'lead_posts', 'automatic_retries' ) ) ||
			( $c['request_budget']['oauth_token_posts'] ?? null ) !== 1 || ( $c['request_budget']['lead_posts'] ?? null ) !== 1 ||
			( $c['request_budget']['automatic_retries'] ?? null ) !== 0 || ! is_string( $c['intent_json'] ?? null ) ) { return false; }
		Foundation::id( $c['card_id'] ?? null );
		$i = json_decode( $c['intent_json'], true, 16, JSON_THROW_ON_ERROR ); $t = $c['target'] ?? array();
		if ( ! is_string( $i['request']['body'] ?? null ) || ( $i['request']['body_sha256'] ?? null ) !== hash( 'sha256', $i['request']['body'] ) ||
			! PilotPayload::inspect( $i['request']['body'] )['valid'] ||
			array_diff( array_keys( $t ), array( 'provider', 'environment', 'tenant_id', 'credential_reference_sha256' ) ) ) { return false; }
		return ( $t['provider'] ?? null ) === 'fieldedge' && is_string( $t['environment'] ?? null ) && $t['environment'] !== '' &&
			is_string( $t['credential_reference_sha256'] ?? null ) && preg_match( '/\A[a-f0-9]{64}\z/D', $t['credential_reference_sha256'] ) &&
			( $t['tenant_id'] ?? null ) === ( $i['tenant_id'] ?? null ) && ( $i['synthetic'] ?? null ) === true &&
			LeadResponse::allowed( $i['request']['method'] ?? '', $i['request']['endpoint'] ?? '' );
	}
	private function readBound( string $id ): array {
		$r = ( $this->read )( $this->sha );
		if ( ( $r['contract_version'] ?? null ) !== '1' || ( $r['status'] ?? null ) !== 'reviewed' || ( $r['card_sha256'] ?? null ) !== $this->sha ||
			( $r['card_json'] ?? null ) !== $this->json || ( $r['production_post_enabled'] ?? null ) !== false ||
			( $r['prospective']['intent_id'] ?? null ) !== $id || ( $r['prospective']['intent_json'] ?? null ) !== $this->card['intent_json'] ) { throw new \RuntimeException( 'pilot_review_readback_mismatch' ); }
		return $r['prospective'];
	}
	public function review(): array {
		try {
			if ( ! $this->valid() ) { return array( 'status' => 'held', 'reason_code' => 'pilot_card_incomplete_or_unsupported' ); }
			$r = ( $this->review )( $this->json );
			if ( in_array( $r['status'] ?? null, array( 'held', 'conflict' ), true ) ) { return $r; }
			if ( ( $r['contract_version'] ?? null ) !== '1' || ( $r['card_sha256'] ?? null ) !== $this->sha || ( $r['production_post_enabled'] ?? null ) !== false || ! in_array( $r['status'] ?? null, array( 'prepared', 'replayed' ), true ) ) { throw new \RuntimeException(); }
			$this->readBound( $r['intent_id'] ); return $r;
		} catch ( \Throwable $error ) { return array( 'status' => 'held', 'reason_code' => 'pilot_review_unconfirmed' ); }
	}
	public function send( ?callable $synthetic_transport = null ): array {
		try {
			if ( ! $this->valid() ) { return array( 'status' => 'held', 'reason_code' => 'pilot_card_incomplete_or_unsupported' ); }
			$sender = new LeadSubmission(
				function( string $json ): array { if ( $json !== $this->card['intent_json'] ) { throw new \RuntimeException(); } return $this->review(); },
				function( string $id, string $sha ): array {
					$this->readBound( $id ); $r = ( $this->claim )( $this->sha, $id, $sha );
					if ( ( $r['card_sha256'] ?? null ) !== $this->sha || ( $r['production_post_enabled'] ?? null ) !== false ) { throw new \RuntimeException(); } return $r;
				},
				function( string $id, string $permit, string $json ): array { $this->readBound( $id ); return ( $this->observe )( $id, $permit, $json ); },
				fn( string $id ): array => $this->readBound( $id ), $synthetic_transport
			);
			return $sender->send( $this->card['intent_json'] );
		} catch ( \Throwable $error ) { return array( 'status' => 'held', 'reason_code' => 'pilot_card_execution_unconfirmed' ); }
	}
}
