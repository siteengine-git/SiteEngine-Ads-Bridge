<?php
/** Nonmutating exact-body validator from official leads.md OpenAPI, not a sender. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;
require_once __DIR__ . '/TargetedRead.php';
final class PilotPayload {
	public static function inspect( string $body ): array {
		$issues = array();
		if ( strlen( $body ) > 16384 ) { return array( 'valid' => false, 'issues' => array( 'request_too_large' ) ); }
		try { $o = json_decode( $body, false, 16, JSON_THROW_ON_ERROR ); } catch ( \Throwable $e ) { return array( 'valid' => false, 'issues' => array( 'request_not_json' ) ); }
		if ( ! $o instanceof \stdClass ) { return array( 'valid' => false, 'issues' => array( 'request_object_required' ) ); }
		if ( array_diff( array_keys( get_object_vars( $o ) ), array( 'source', 'customer', 'isExistingCustomer', 'appointment', 'serviceRequest', 'preferredContactMethod' ) ) ) { $issues[] = 'optional_extra_fields_not_in_minimal_pilot_policy'; }
		foreach ( array( 'source', 'customer', 'isExistingCustomer', 'appointment', 'serviceRequest' ) as $key ) { if ( ! property_exists( $o, $key ) ) { $issues[] = 'required_' . $key; } }
		if ( ! is_string( $o->source ?? null ) || trim( $o->source ) === '' ) { $issues[] = 'nonempty_source_required'; }
		foreach ( array( 'customer', 'appointment', 'serviceRequest' ) as $key ) { if ( ! ( $o->$key ?? null ) instanceof \stdClass ) { $issues[] = $key . '_object_required'; } }
		if ( ! is_bool( $o->isExistingCustomer ?? null ) ) { $issues[] = 'isExistingCustomer_boolean_required'; }
		$c = $o->customer ?? new \stdClass();
		if ( ! $c instanceof \stdClass ) { return array( 'valid' => false, 'issues' => $issues ); }
		if ( array_diff( array_keys( get_object_vars( $c ) ), array( 'existingCustomerId', 'firstName', 'lastName', 'phone', 'email', 'address', 'customerRefId', 'marketingEmailOptedIn' ) ) ) { $issues[] = 'unknown_customer_field'; }
		foreach ( array( 'firstName', 'lastName', 'phone', 'email', 'customerRefId' ) as $key ) {
			if ( isset( $c->$key ) && ( ! is_string( $c->$key ) || strlen( $c->$key ) > 254 || preg_match( '/[\x00-\x1f\x7f]/', $c->$key ) ) ) { $issues[] = 'customer_string_invalid'; }
		}
		if ( isset( $c->email ) && ! filter_var( $c->email, FILTER_VALIDATE_EMAIL ) ) { $issues[] = 'customer_email_invalid'; }
		foreach ( array( array( $o->serviceRequest ?? null, array( 'serviceCategory', 'serviceType', 'issueDescription' ) ),
			array( $o->appointment ?? null, array( 'preferredDate', 'preferredTime', 'timezone', 'availabilityNotes' ) ) ) as $part ) {
			if ( ! $part[0] instanceof \stdClass ) { continue; }
			$allowed = $part[1][0] === 'preferredDate' ? array_merge( $part[1], array( 'location' ) ) : $part[1];
			if ( array_diff( array_keys( get_object_vars( $part[0] ) ), $allowed ) ) { $issues[] = 'unsupported_service_or_appointment_field'; }
			foreach ( $part[1] as $key ) { if ( isset( $part[0]->$key ) && ( ! is_string( $part[0]->$key ) || strlen( $part[0]->$key ) > 500 || preg_match( '/[\x00-\x1f\x7f]/', $part[0]->$key ) ) ) { $issues[] = 'service_or_appointment_string_invalid'; } }
		}
		foreach ( array( $c->address ?? null, $o->appointment->location ?? null ) as $address ) {
			if ( null === $address ) { continue; }
			if ( ! $address instanceof \stdClass || array_diff( array_keys( get_object_vars( $address ) ), array( 'addressRefId', 'addressLine1', 'addressLine2', 'city', 'state', 'postalCode', 'country' ) ) ) { $issues[] = 'address_shape_invalid'; continue; }
			foreach ( get_object_vars( $address ) as $v ) { if ( ! is_string( $v ) || strlen( $v ) > 254 || preg_match( '/[\x00-\x1f\x7f]/', $v ) ) { $issues[] = 'address_string_invalid'; } }
		}
		if ( isset( $c->existingCustomerId ) ) { try { TargetedRead::id( $c->existingCustomerId ); } catch ( \Throwable $e ) { $issues[] = 'existing_customer_uuid_required'; } }
		elseif ( ( $o->isExistingCustomer ?? null ) === true ) { $issues[] = 'explicit_existing_customer_id_required_by_pilot_policy'; }
		elseif ( empty( $c->phone ) && empty( $c->email ) ) { $issues[] = 'new_customer_phone_or_email_required'; }
		if ( isset( $o->preferredContactMethod ) && ! in_array( $o->preferredContactMethod, array( 'Email', 'Phone', 'SMS' ), true ) ) { $issues[] = 'preferred_contact_method_casing_unconfirmed'; }
		if ( isset( $c->marketingEmailOptedIn ) ) { $issues[] = 'marketing_optin_requires_separate_owner_authority'; }
		return array( 'valid' => ! $issues, 'issues' => array_values( array_unique( $issues ) ), 'body_sha256' => hash( 'sha256', $body ),
			'tenant_acceptance_verified' => false, 'provider_side_effects_verified' => false );
	}
}
