<?php
/** Extract documented provider identities only; never constructs a lead/job crosswalk. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;
require_once __DIR__ . '/Foundation.php';
require_once __DIR__ . '/TargetedRead.php';
final class LeadReference {
	public static function status( $value ): ?string {
		if ( null === $value ) { return null; }
		if ( is_int( $value ) && $value >= 0 && $value <= 19 ) { return 'code:' . $value; }
		// Published examples contradict the integer schema; preserve, never map labels.
		if ( is_string( $value ) && preg_match( '/\A[a-zA-Z][a-zA-Z0-9 _-]{0,79}\z/D', $value ) ) { return $value; }
		throw new \InvalidArgumentException( 'provider_lead_status_unrecognized' );
	}
	private static function uuid( $id ): string { TargetedRead::id( $id ); return strtolower( $id ); }
	public static function detail( string $tenant, string $expected_lead_id, string $body ): array {
		Foundation::id( $tenant ); $expected_lead_id = self::uuid( $expected_lead_id );
		if ( strlen( $body ) > 65536 ) { throw new \InvalidArgumentException( 'lead_detail_too_large' ); }
		$o = json_decode( $body, false, 16, JSON_THROW_ON_ERROR );
		if ( ! $o instanceof \stdClass || ! ( $o->data ?? null ) instanceof \stdClass || self::uuid( $o->data->leadId ?? null ) !== $expected_lead_id ) { throw new \InvalidArgumentException( 'lead_detail_identity_mismatch' ); }
		$d = $o->data; $tracking = $d->trackingId ?? null;
		if ( null !== $tracking && ( ! is_string( $tracking ) || strlen( $tracking ) > 160 || preg_match( '/[\x00-\x1f\x7f]/', $tracking ) ) ) { throw new \InvalidArgumentException( 'tracking_reference_invalid' ); }
		return array( 'provider' => 'fieldedge', 'tenant_id' => $tenant, 'lead_id' => $expected_lead_id,
			'customer_id' => isset( $d->customer->customerId ) ? self::uuid( $d->customer->customerId ) : null,
			'account_id' => isset( $d->accountId ) ? self::uuid( $d->accountId ) : null, 'tracking_reference' => $tracking,
			'provider_status' => self::status( $d->status ?? null ), 'response_sha256' => hash( 'sha256', $body ),
			'work_order_id' => null, 'relationship_reason' => 'lead_work_order_reference_not_documented', 'attribution_established' => false );
	}
}
