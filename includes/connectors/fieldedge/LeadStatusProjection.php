<?php
/**
 * Interpret a verified FieldEdge lead status without guessing work-order
 * identity, invoice value, CRM lifecycle or Google Ads export eligibility.
 *
 * FieldEdge Integration Team confirmed status vocabulary and absence of a
 * supported lead-to-work-order GET/webhook on 2026-11-18.
 *
 * @package SiteEngineFieldEdge
 */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

require_once __DIR__ . '/LeadReference.php';

final class LeadStatusProjection {
	private const STATUS = array(
		'Accepted'      => 'lead_accepted',
		'Rejected'      => 'lead_rejected',
		'QuoteAccepted' => 'quote_accepted',
		'QuoteRejected' => 'quote_rejected',
		'QuoteExpired'  => 'quote_expired',
		'JobCanceled'   => 'job_canceled',
		'JobCompleted'  => 'job_completed',
		'JobFinalized'  => 'job_finalized',
	);
	/**
	 * @param string $tenant Tenant identity in the caller's own contract.
	 * @param string $inquiry Local original inquiry identity from Ads Bridge.
	 * @param string $saved_guid A GUID generated and durably stored before POST.
	 * @param string $body Complete exact GET /v1/leads/{leadId} response body.
	 */
	public static function examine( string $tenant, string $inquiry, string $saved_guid, string $body ): array {
		if ( ! preg_match( '/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/Di', $saved_guid ) ||
			! preg_match( '/\A[a-zA-Z0-9:._-]{1,80}\z/D', $inquiry ) ) {
			throw new \InvalidArgumentException( 'stored_lead_binding_invalid' );
		}
		$lead = LeadReference::detail( $tenant, $saved_guid, $body );
		// For partner-created leads, FieldEdge says trackingId is retained as
		// leadId. A returned different trackingId must never bind this inquiry.
		$tracking = $lead['tracking_reference'];
		if ( null !== $tracking && strtolower( $tracking ) !== strtolower( $saved_guid ) ) {
			throw new \InvalidArgumentException( 'tracking_id_mismatch' );
		}
		$status = $lead['provider_status'];
		$known = is_string( $status ) && array_key_exists( $status, self::STATUS );
		return array(
			'provider' => 'fieldedge',
			'tenant_id' => $lead['tenant_id'],
			'inquiry_id' => $inquiry,
			'lead_id' => $lead['lead_id'],
			'provider_status' => $status,
			'observed_outcome' => $known ? self::STATUS[ $status ] : 'status_unmapped',
			'needs_review' => ! $known,
			'response_sha256' => $lead['response_sha256'],
			'observation_sha256' => hash( 'sha256', json_encode( array(
				$lead['tenant_id'], $inquiry, $lead['lead_id'], $status,
				$lead['response_sha256'],
			), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) ),
			'work_order_id' => null,
			'invoice_id' => null,
			'approved_revenue' => null,
			'crm_lifecycle_write_allowed' => false,
			'advertising_export_allowed' => false,
		);
	}
}
