<?php
/** AB contract fixture double: in-memory receipts, no DB durability or exports. */
namespace SiteEngine\AdsBridge\Tests\FieldEdge;

final class SyntheticOutcomeApi {
	public array $receipts = array();
	public int $insertions = 0;
	public int $calls = 0;
	public bool $uncertain_reply = false;
	public string $read_mode = 'normal';

	public function capability(): array {
		return array( 'contract_version' => '1', 'destination' => str_repeat( 'a', 64 ) );
	}

	public function submit( string $body ): array {
		++$this->calls;
		$request = json_decode( $body, true, 16, JSON_THROW_ON_ERROR );
		$id = hash( 'sha256', 'fixture-receipt:' . $request['operation_id'] );
		$digest = hash( 'sha256', $body );
		$response = array(
			'contract_version' => '1', 'status' => 'recorded', 'reason_code' => 'outcome_persisted',
			'receipt_id' => $id, 'body_sha256' => $digest, 'outcome_revision' => 1, 'export_eligible' => false,
			'association_status' => 'attributed', 'canonical_receipt_id' => $id, 'inquiry_id' => $request['inquiry_id'],
		);
		if ( isset( $this->receipts[ $id ] ) ) {
			if ( $body === $this->receipts[ $id ]['body'] ) {
				return json_decode( $this->receipts[ $id ]['response'], true, 16, JSON_THROW_ON_ERROR );
			}
			return array_replace( $response, array( 'status' => 'conflict', 'reason_code' => 'operation_identity_reused' ) );
		}
		foreach ( $this->receipts as $row ) {
			$prior = json_decode( $row['body'], true, 16, JSON_THROW_ON_ERROR );
			if ( $prior['provider'] === $request['provider'] && $prior['tenant_id'] === $request['tenant_id'] && $prior['event_id'] === $request['event_id'] ) {
				return array_replace( $response, array( 'status' => 'conflict', 'reason_code' => 'source_event_identity_reused' ) );
			}
		}
		// Explicit fixture responses, not an implementation of core review/storage.
		if ( 'synthetic-unattributed-1' === $request['evidence_id'] ) {
			$response['status'] = 'held';
			$response['reason_code'] = 'association_missing';
			$response['association_status'] = 'unmatched';
		} elseif ( 'synthetic-review-1' === $request['evidence_id'] ) {
			$response['status'] = 'held';
			$response['reason_code'] = 'association_ambiguous';
			$response['association_status'] = 'unmatched';
		}
		$this->receipts[ $id ] = array( 'body' => $body, 'body_digest' => $digest, 'response' => json_encode( $response, JSON_THROW_ON_ERROR ) );
		++$this->insertions;
		return $this->uncertain_reply ? array_replace( $response, array( 'status' => 'retry', 'reason_code' => 'outcome_receipt_readback_unconfirmed' ) ) : $response;
	}

	public function read( string $receipt ): ?array {
		if ( 'throw' === $this->read_mode ) {
			throw new \RuntimeException( 'synthetic read failure' );
		}
		if ( 'missing' === $this->read_mode ) {
			return null;
		}
		$row = $this->receipts[ $receipt ] ?? null;
		if ( null !== $row && 'wrong_body' === $this->read_mode ) {
			$row['body'] .= ' ';
		} elseif ( null !== $row && 'bad_response' === $this->read_mode ) {
			$row['response'] = '{}';
		}
		return $row;
	}
}
