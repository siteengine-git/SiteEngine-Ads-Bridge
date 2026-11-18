<?php
namespace SiteEngine\AdsBridge\Tests\FieldEdge;
final class LeadFixture {
	public static function input( string $mode = 'new' ): array {
		$i = array( 'synthetic' => true, 'tenant_id' => 'synthetic-climatrol-lead-1',
			'inquiry' => array( 'inquiry_id' => 'synthetic-inquiry-form-1', 'source' => array( 'type' => 'fluent_form', 'form_id' => 7, 'entry_id' => 101 ) ),
			'customer_mode' => $mode, 'source' => 'siteengine', 'preferred_contact_method' => 'phone',
			'customer' => array( 'first_name' => 'Synthetic', 'last_name' => 'Fixture', 'phone' => '2025550101' ),
			'appointment' => array( 'preferred_date' => '2026-11-16', 'preferred_time' => '10:00', 'timezone' => 'America/New_York',
				'location' => array( 'addressLine1' => '123 Synthetic Street', 'city' => 'Synthetic City', 'state' => 'GA', 'postalCode' => '00000', 'country' => 'USA' ) ),
			'service_request' => array( 'category' => 'HVAC', 'type' => 'Maintenance', 'description' => 'Synthetic dry-run only; do not submit' ) );
		if ( $mode === 'existing' ) {
			$i['inquiry'] = array( 'inquiry_id' => 'synthetic-inquiry-call-1', 'source' => array( 'type' => 'call', 'evidence_id' => 'synthetic-call-evidence-1' ) );
			$i['customer'] = array( 'existing_customer_id' => '00000000-0000-4000-8000-000000000101',
				'verified_relationship' => array( 'provider' => 'fieldedge', 'tenant_id' => $i['tenant_id'], 'customer_id' => '00000000-0000-4000-8000-000000000101', 'evidence_id' => 'synthetic-verified-customer-1' ) );
		}
		return $i;
	}
}
