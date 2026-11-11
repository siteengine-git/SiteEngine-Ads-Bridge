<?php
/** Pure synthetic lead intent validation; intentionally no transport or storage. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;
require_once __DIR__ . '/Foundation.php';
final class LeadCreateDryRun {
	private static function text( $value, int $max = 160 ): string {
		if ( ! is_string( $value ) || trim( $value ) === '' || strlen( $value ) > $max || preg_match( '/[\x00-\x1f\x7f]/', $value ) ) { throw new \InvalidArgumentException( 'invalid_lead_field' ); }
		return $value;
	}
	private static function uuid( $value ): string {
		if ( ! is_string( $value ) || ! preg_match( '/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/Di', $value ) ) { throw new \InvalidArgumentException( 'invalid_lead_provider_id' ); }
		return strtolower( $value );
	}
	private static function inquiry( array $source ): array {
		$id = Foundation::id( $source['inquiry_id'] ?? null );
		if ( ! str_starts_with( $id, 'synthetic-' ) ) { throw new \InvalidArgumentException( 'synthetic_inquiry_required' ); }
		$b = $source['source'] ?? array();
		if ( ( $b['type'] ?? null ) === 'fluent_form' && is_int( $b['form_id'] ?? null ) && $b['form_id'] > 0 && is_int( $b['entry_id'] ?? null ) && $b['entry_id'] > 0 ) {
			return array( 'inquiry_id' => $id, 'source' => array( 'type' => 'fluent_form', 'form_id' => $b['form_id'], 'entry_id' => $b['entry_id'] ) );
		}
		if ( ( $b['type'] ?? null ) === 'call' ) { return array( 'inquiry_id' => $id, 'source' => array( 'type' => 'call', 'evidence_id' => Foundation::id( $b['evidence_id'] ?? null ) ) ); }
		throw new \InvalidArgumentException( 'original_binding_required' );
	}
	private static function intentId( string $tenant, array $inquiry ): string { return 'fe-lead-' . Foundation::digest( array( 'provider' => 'fieldedge', 'tenant_id' => $tenant, 'inquiry' => $inquiry ) ); }
	public static function prepare( array $input ): array {
		if ( ( $input['synthetic'] ?? null ) !== true || ! is_string( $input['tenant_id'] ?? null ) || ! str_starts_with( $input['tenant_id'], 'synthetic-' ) ) { throw new \InvalidArgumentException( 'synthetic_lead_input_required' ); }
		$tenant = Foundation::id( $input['tenant_id'] ); $inquiry = self::inquiry( $input['inquiry'] ?? array() );
		$mode = $input['customer_mode'] ?? null; $c = $input['customer'] ?? array(); $evidence = null;
		if ( $mode === 'existing' ) {
			$id = self::uuid( $c['existing_customer_id'] ?? null ); $v = $c['verified_relationship'] ?? array();
			if ( ( $v['provider'] ?? null ) !== 'fieldedge' || ( $v['tenant_id'] ?? null ) !== $tenant || ( $v['customer_id'] ?? null ) !== $id ) { throw new \InvalidArgumentException( 'verified_customer_relationship_required' ); }
			$evidence = Foundation::id( $v['evidence_id'] ?? null ); $customer = array( 'existingCustomerId' => $id );
		} elseif ( $mode === 'new' ) {
			$customer = array( 'firstName' => self::text( $c['first_name'] ?? null ), 'lastName' => self::text( $c['last_name'] ?? null ) );
			if ( ! empty( $c['phone'] ) ) {
				if ( ! is_string( $c['phone'] ) || ! preg_match( '/\A20255501[0-9]{2}\z/D', $c['phone'] ) ) { throw new \InvalidArgumentException( 'synthetic_phone_required' ); }
				$customer['phone'] = $c['phone'];
			}
			if ( ! empty( $c['email'] ) ) {
				if ( ! is_string( $c['email'] ) || ! filter_var( $c['email'], FILTER_VALIDATE_EMAIL ) || ! str_ends_with( strtolower( $c['email'] ), '.invalid' ) ) { throw new \InvalidArgumentException( 'synthetic_email_required' ); }
				$customer['email'] = strtolower( $c['email'] );
			}
			if ( ! isset( $customer['phone'] ) && ! isset( $customer['email'] ) ) { throw new \InvalidArgumentException( 'new_customer_phone_or_email_required' ); }
		} else { throw new \InvalidArgumentException( 'explicit_customer_mode_required' ); }
		$method = $input['preferred_contact_method'] ?? null;
		if ( ! in_array( $method, array( 'phone', 'email' ), true ) || ( $mode === 'new' && ! isset( $customer[ $method ] ) ) ) { throw new \InvalidArgumentException( 'unsupported_preferred_contact_method' ); }
		$a = $input['appointment'] ?? array();
		$date = self::text( $a['preferred_date'] ?? null, 10 ); $time = self::text( $a['preferred_time'] ?? null, 5 );
		$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		if ( ! $d || $d->format( 'Y-m-d' ) !== $date || ! preg_match( '/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/D', $time ) ) { throw new \InvalidArgumentException( 'invalid_lead_appointment' ); }
		$zone = self::text( $a['timezone'] ?? null, 80 );
		try { new \DateTimeZone( $zone ); } catch ( \Throwable $error ) { throw new \InvalidArgumentException( 'invalid_lead_timezone' ); }
		$location = array(); foreach ( array( 'addressLine1', 'city', 'state', 'postalCode', 'country' ) as $key ) { $location[ $key ] = self::text( $a['location'][ $key ] ?? null ); }
		$s = $input['service_request'] ?? array();
		$payload = array( 'source' => self::text( $input['source'] ?? null, 80 ), 'preferredContactMethod' => $method, 'isExistingCustomer' => $mode === 'existing', 'customer' => $customer,
			'appointment' => array( 'preferredDate' => $date, 'preferredTime' => $time, 'timezone' => $zone, 'location' => $location ),
			'serviceRequest' => array( 'serviceCategory' => self::text( $s['category'] ?? null, 80 ), 'serviceType' => self::text( $s['type'] ?? null, 80 ), 'issueDescription' => self::text( $s['description'] ?? null, 500 ) ) );
		$body = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
		return array( 'synthetic' => true, 'dry_run' => true, 'production_post_authorized' => false, 'network_requests' => 0, 'provider' => 'fieldedge', 'tenant_id' => $tenant,
			'local_inquiry' => $inquiry, 'customer_evidence_id' => $evidence, 'intent_id' => self::intentId( $tenant, $inquiry ), 'body' => $body, 'body_sha256' => hash( 'sha256', $body ), 'payload' => $payload,
			'holds' => array( 'source_and_service_values_not_tenant_validated', 'provider_requiredness_and_enum_acceptance_not_tested', 'trackingId_not_documented_as_idempotency_key', 'production_create_not_authorized' ) );
	}
	private static function verify( array $i ): void {
		if ( ( $i['synthetic'] ?? null ) !== true || ( $i['dry_run'] ?? null ) !== true || ( $i['production_post_authorized'] ?? null ) !== false ||
			! hash_equals( hash( 'sha256', $i['body'] ?? '' ), $i['body_sha256'] ?? '' ) || ! hash_equals( self::intentId( $i['tenant_id'], self::inquiry( $i['local_inquiry'] ) ), $i['intent_id'] ?? '' ) ||
			json_decode( $i['body'], true, 16, JSON_THROW_ON_ERROR ) !== $i['payload'] ) { throw new \InvalidArgumentException( 'dry_run_intent_integrity_failed' ); }
	}
	/** Prior state is caller-owned demonstration data, never a new durable ledger. */
	public static function decide( array $i, ?array $prior = null ): array {
		self::verify( $i );
		if ( null === $prior ) { return array( 'status' => 'dry_run_ready', 'post_allowed' => false ); }
		if ( ( $prior['intent_id'] ?? null ) !== $i['intent_id'] ) { return array( 'status' => 'held', 'reason' => 'foreign_intent_state' ); }
		if ( ( $prior['body_sha256'] ?? null ) !== $i['body_sha256'] ) { return array( 'status' => 'held', 'reason' => 'duplicate_intent_content_conflict' ); }
		if ( ( $prior['phase'] ?? null ) === 'prepared' ) { return array( 'status' => 'duplicate_intent', 'post_allowed' => false ); }
		if ( ( $prior['phase'] ?? null ) === 'hypothetical_recorded' && ( $prior['hypothetical'] ?? null ) === true &&
			( $prior['mapping']['provider'] ?? null ) === 'fieldedge' && ( $prior['mapping']['tenant_id'] ?? null ) === $i['tenant_id'] &&
			( $prior['mapping']['inquiry'] ?? null ) === $i['local_inquiry'] ) {
			self::uuid( $prior['mapping']['lead_id'] ?? null );
			return array( 'status' => 'duplicate_hypothetical_success', 'post_allowed' => false );
		}
		return array( 'status' => 'held', 'reason' => 'uncertain_result_no_blind_post_retry' );
	}
	public static function state( array $i, string $phase = 'prepared' ): array {
		self::verify( $i ); if ( ! in_array( $phase, array( 'prepared', 'uncertain' ), true ) ) { throw new \InvalidArgumentException( 'invalid_dry_run_phase' ); }
		return array( 'intent_id' => $i['intent_id'], 'body_sha256' => $i['body_sha256'], 'phase' => $phase, 'hypothetical' => true );
	}
	public static function hypotheticalResponse( array $i, int $http, array $response ): array {
		self::verify( $i ); if ( 201 !== $http ) { return self::state( $i, 'uncertain' ); }
		try { $id = self::uuid( $response['data']['leadId'] ?? null ); } catch ( \InvalidArgumentException $error ) { return self::state( $i, 'uncertain' ); }
		return array( 'intent_id' => $i['intent_id'], 'body_sha256' => $i['body_sha256'], 'phase' => 'hypothetical_recorded', 'hypothetical' => true,
			'mapping' => array( 'provider' => 'fieldedge', 'tenant_id' => $i['tenant_id'], 'lead_id' => $id, 'inquiry' => $i['local_inquiry'] ) );
	}
}
