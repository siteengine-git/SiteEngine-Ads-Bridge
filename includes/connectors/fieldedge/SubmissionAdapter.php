<?php
/** Exact binding to AB's supplied external outcome contract 1. No owned ledger. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

require_once __DIR__ . '/ClimatrolWorkflow.php';

final class SubmissionAdapter {
	private \Closure $capability;
	private \Closure $submit;
	private \Closure $read;

	/** Optional test seams have the exact supplied AB callable signatures. */
	public function __construct( ?callable $capability = null, ?callable $submit = null, ?callable $read = null ) {
		$this->capability = \Closure::fromCallable( $capability ?? static function (): array {
			if ( ! function_exists( 'seab_external_outcome_capability' ) ) {
				throw new \RuntimeException( 'ab_interface_unavailable' );
			}
			return \seab_external_outcome_capability();
		} );
		$this->submit = \Closure::fromCallable( $submit ?? static fn( string $body ): array => \seab_external_outcome_submit( $body ) );
		$this->read = \Closure::fromCallable( $read ?? static fn( string $receipt ): ?array => \seab_external_outcome_read( $receipt ) );
	}

	/** Prepare ONCE; retain and resend these exact bytes after retry/uncertainty. */
	public function prepare( array $packet, string $operation_id, string $evidence_id ): string {
		$expected = $packet['packet_sha256'] ?? null;
		$unsigned = $packet;
		unset( $unsigned['packet_sha256'] );
		if ( ! is_string( $expected ) || ! hash_equals( Foundation::digest( $unsigned ), $expected ) ) {
			throw new \InvalidArgumentException( 'normalized_packet_changed' );
		}
		$capability = ( $this->capability )();
		if ( '1' !== ( $capability['contract_version'] ?? null ) ||
			! is_string( $capability['destination'] ?? null ) ||
			! preg_match( '/\A[a-f0-9]{64}\z/D', $capability['destination'] ) ) {
			throw new \InvalidArgumentException( 'unsupported_ab_capability' );
		}
		$value = $packet['source_evidence']['approved_invoice_value'] ?? null;
		if ( ! is_array( $value ) || 'USD' !== $value['currency'] || '0.00' === $value['amount'] ||
			'synthetic-usd-demo-1' !== $value['policy_id'] ) {
			throw new \InvalidArgumentException( 'synthetic_value_evidence_required' );
		}
		$outcome = $packet['outcome'];
		$event = $packet['source_evidence']['notification'];
		$inquiry = Foundation::id( $packet['inquiry']['inquiry_id'] );
		if ( strlen( $inquiry ) > 40 ) {
			throw new \InvalidArgumentException( 'unsupported_inquiry_identity' );
		}
		$request = array(
			'contract_version' => '1', 'destination' => $capability['destination'],
			'operation_id' => Foundation::id( $operation_id ), 'provider' => $outcome['provider'],
			'tenant_id' => $outcome['tenant_id'], 'event_id' => $event['event_id'],
			'event_sha256' => $event['body_sha256'], 'evidence_id' => Foundation::id( $evidence_id ),
			'inquiry_id' => $inquiry, 'customer_id' => $outcome['customer_id'],
			'job_id' => $outcome['work_order_id'], 'invoice_id' => $outcome['invoice_id'],
			'completed_at' => $packet['source_evidence']['completed_at'],
			'invoice_at' => $packet['source_evidence']['invoice_at'],
			'amount' => $value['amount'], 'currency' => $value['currency'],
			'snapshot_sha256' => $outcome['snapshot_sha256'], 'value_policy_id' => $value['policy_id'],
		);
		// Inquiry is a requested subject, never proof of association. Only the
		// separately stored operator review can establish attribution in AB.
		$body = json_encode( $request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );
		if ( strlen( $body ) > 16384 ) {
			throw new \InvalidArgumentException( 'submission_too_large' );
		}
		return $body;
	}

	/** No automatic retry, review creation, migrations, eligibility or exports. */
	public function send( string $body ): array {
		$digest = hash( 'sha256', $body );
		$retry = static fn( string $reason ) => array(
			'status' => 'retry', 'reason_code' => $reason, 'request_body_sha256' => $digest,
			'receipt_verified' => false, 'attribution_status' => 'Needs Review', 'export_eligible' => false,
		);
		try {
			$response = ( $this->submit )( $body );
			if ( ! in_array( $response['status'] ?? null, array( 'recorded', 'held', 'retry', 'conflict' ), true ) ||
				! is_string( $response['reason_code'] ?? null ) || ! preg_match( '/\A[a-z][a-z0-9_]{0,95}\z/D', $response['reason_code'] ) ||
				'1' !== ( $response['contract_version'] ?? null ) ||
				$digest !== ( $response['body_sha256'] ?? null ) ||
				false !== ( $response['export_eligible'] ?? null ) ) {
				return $retry( 'ab_response_unconfirmed' );
			}
			$verified = false;
			$receipt = $response['receipt_id'] ?? '';
			if ( 'conflict' !== $response['status'] && is_string( $receipt ) && preg_match( '/\A[a-f0-9]{64}\z/D', $receipt ) ) {
				$row = ( $this->read )( $receipt );
				if ( null !== $row ) {
					if ( $body !== ( $row['body'] ?? null ) || $digest !== ( $row['body_digest'] ?? null ) ) {
						return $retry( 'ab_receipt_body_mismatch' );
					}
					$stored = json_decode( $row['response'] ?? '', true, 16, JSON_THROW_ON_ERROR );
					if ( ! is_array( $stored ) || ! in_array( $stored['status'] ?? null, array( 'recorded', 'held' ), true ) ||
						! is_string( $stored['reason_code'] ?? null ) || ! preg_match( '/\A[a-z][a-z0-9_]{0,95}\z/D', $stored['reason_code'] ) ||
						'1' !== ( $stored['contract_version'] ?? null ) || $receipt !== ( $stored['receipt_id'] ?? null ) ||
						$digest !== ( $stored['body_sha256'] ?? null ) || 1 !== ( $stored['outcome_revision'] ?? null ) ||
						false !== ( $stored['export_eligible'] ?? null ) ) {
						return $retry( 'ab_receipt_response_unconfirmed' );
					}
					// Matching stored receipt governs even a lost/unconfirmed submit reply.
					$response = $stored;
					$verified = true;
				}
			}
			if ( in_array( $response['status'], array( 'recorded', 'held' ), true ) && ! $verified ) {
				return $retry( 'ab_receipt_unconfirmed' );
			}
			$attribution = 'Needs Review';
			if ( $verified && 'attributed' === ( $response['association_status'] ?? null ) ) {
				$attribution = 'Attributed';
			} elseif ( $verified && 'association_missing' === ( $response['reason_code'] ?? null ) ) {
				$attribution = 'Unattributed';
			}
			return array(
				'status' => $response['status'], 'reason_code' => $response['reason_code'],
				'receipt_verified' => $verified, 'attribution_status' => $attribution,
				'export_eligible' => false, 'request_body_sha256' => $digest, 'ab_response' => $response,
			);
		} catch ( \Throwable $error ) {
			return $retry( 'ab_call_or_read_unconfirmed' );
		}
	}
}
