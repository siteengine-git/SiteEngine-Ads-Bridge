<?php
/** Climatrol-first normalization. No storage, contact matching or export effects. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

require_once __DIR__ . '/Foundation.php';

final class ClimatrolWorkflow {
	/**
	 * Records, associations and approval are trusted local evidence inputs, not a
	 * public provider payload. The AB binding must revalidate stored evidence.
	 */
	public static function normalize( array $records, array $inquiry, array $associations, array $approval, array $notification ): array {
		$id = Foundation::id( $inquiry['inquiry_id'] ?? null );
		$source = $inquiry['source'] ?? array();
		if ( 'fluent_form' !== ( $source['type'] ?? null ) ||
			! is_int( $source['form_id'] ?? null ) || $source['form_id'] < 1 ||
			! is_int( $source['entry_id'] ?? null ) || $source['entry_id'] < 1 ) {
			throw new \InvalidArgumentException( 'unsupported_inquiry_source' );
		}
		$lead = Foundation::lead( $records['lead'] );
		$outcome = Foundation::outcome( $records['tenant'], $records['work'], $records['invoice'], $associations, $approval );
		$value = null;
		if ( true === ( $approval['approved'] ?? null ) && $outcome['tenant_id'] === ( $approval['tenant_id'] ?? null ) &&
			$outcome['invoice_id'] === ( $approval['invoice_id'] ?? null ) &&
			Foundation::digest( $records['invoice'] ) === ( $approval['invoice_sha256'] ?? null ) ) {
			if ( ! is_string( $approval['currency'] ?? null ) || ! preg_match( '/\A[A-Z]{3}\z/D', $approval['currency'] ) ) {
				throw new \InvalidArgumentException( 'invalid_approved_currency' );
			}
			$value = array(
				'amount' => Foundation::amount( $approval['amount'] ?? null ), 'currency' => $approval['currency'],
				'approval_id' => Foundation::id( $approval['approval_id'] ?? null ),
				'policy_id' => Foundation::id( $approval['policy_id'] ?? null ),
			);
		}
		if ( 'fieldedge' !== ( $notification['provider'] ?? null ) ||
			$outcome['tenant_id'] !== ( $notification['tenant_id'] ?? null ) ||
			'invoice' !== ( $notification['entity_type'] ?? null ) ||
			$outcome['invoice_id'] !== ( $notification['entity_id'] ?? null ) ) {
			throw new \InvalidArgumentException( 'notification_record_mismatch' );
		}
		$attribution = 'Attributed';
		if ( 'association_missing' === $outcome['reason_code'] ) {
			$attribution = 'Unattributed';
		} elseif ( in_array( $outcome['reason_code'], array( 'association_ambiguous', 'association_unproven' ), true ) ) {
			$attribution = 'Needs Review';
		} elseif ( ( $outcome['inquiry_id'] ?? null ) !== $id ||
			( $outcome['lead_id'] ?? null ) !== $lead['lead_id'] ||
			$outcome['customer_id'] !== $lead['customer_id'] ) {
			$attribution = 'Needs Review';
			$outcome['status'] = 'held';
			$outcome['reason_code'] = 'inquiry_lead_evidence_mismatch';
		}
		// Keep source evidence even when the outcome has no accepted inquiry link.
		$packet = array(
			'profile' => 'climatrol-first-synthetic',
			'attribution_status' => $attribution,
			'inquiry' => array( 'inquiry_id' => $id, 'source' => array(
				'type' => 'fluent_form', 'form_id' => $source['form_id'], 'entry_id' => $source['entry_id'],
			) ),
			'outcome' => $outcome,
			'source_evidence' => array(
				'notification' => $notification,
				'lead_id' => $lead['lead_id'],
				'approved_invoice_value' => $value,
				'lead_sha256' => $lead['snapshot_sha256'],
				'work_order_sha256' => Foundation::digest( $records['work'] ),
				'invoice_sha256' => Foundation::digest( $records['invoice'] ),
				'completed_at' => Foundation::timestamp( $records['work']['workOrderInformation']['completedDate'] ?? null ),
				'invoice_at' => Foundation::timestamp( $records['invoice']['invoiceDate'] ?? null ),
			),
			'export_eligible' => false,
		);
		$packet['packet_sha256'] = Foundation::digest( $packet );
		return $packet;
	}
}
