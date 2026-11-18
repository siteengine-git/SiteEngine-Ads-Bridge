<?php
/**
 * Exact, provider-confirmed FieldEdge lead-status discovery.
 *
 * One originally stored lead GUID -> one GET -> allowlisted status evidence.
 * No provider write, customer job crosswalk, lifecycle write or advertising
 * export. Raw provider records remain in the private executor process.
 *
 * @package SiteEngineFieldEdge
 */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

require_once __DIR__ . '/TargetedRead.php';
require_once __DIR__ . '/LeadStatusProjection.php';

final class LeadStatusDiscovery {
	public static function run( callable $get, SelectedCaseJournal $journal, array $case ): array {
		if ( ( $case['schema'] ?? null ) !== 1 ||
			! is_string( $case['tenant_id'] ?? null ) ||
			! is_string( $case['lead_id'] ?? null ) ||
			! is_string( $case['inquiry_id'] ?? null ) ||
			! preg_match( '/\A[a-zA-Z0-9:._-]{1,80}\z/D', $case['inquiry_id'] ) ||
			count( $case ) !== 4 ||
			array_diff( array_keys( $case ), array( 'schema', 'tenant_id', 'inquiry_id', 'lead_id' ) ) ) {
			throw new \RuntimeException( 'lead_status_evidence_invalid' );
		}
		TargetedRead::id( $case['tenant_id'] );
		TargetedRead::id( $case['lead_id'] );
		if ( $journal->remaining() !== 6 ) {
			throw new \RuntimeException( 'lead_status_journal_not_fresh' );
		}
		$response = $get( 'lead', array( 'id' => strtolower( $case['lead_id'] ) ) );
		if ( ! is_array( $response ) ||
			! is_array( $response['data'] ?? null ) ) {
			throw new \RuntimeException( 'lead_status_response_invalid' );
		}
		$body = json_encode(
			array( 'data' => $response['data'] ),
			JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
		);
		$observation = LeadStatusProjection::examine(
			$case['tenant_id'], $case['inquiry_id'],
			$case['lead_id'], $body
		);
		if ( $observation['lead_id'] !== strtolower( $case['lead_id'] ) ||
			$observation['tenant_id'] !== strtolower( $case['tenant_id'] ) ) {
			throw new \RuntimeException( 'lead_status_identity_mismatch' );
		}
		return array(
			'schema' => 1,
			'provider' => 'fieldedge',
			'tenant_id' => $observation['tenant_id'],
			'inquiry_id' => $observation['inquiry_id'],
			'lead_id' => $observation['lead_id'],
			'status' => $observation['provider_status'],
			'outcome' => $observation['observed_outcome'],
			'needs_review' => $observation['needs_review'],
			'observation_sha256' => $observation['observation_sha256'],
			'work_order_id' => null,
			'approved_revenue' => null,
			'crm_lifecycle_write_allowed' => false,
			'advertising_export_allowed' => false,
		);
	}
}
