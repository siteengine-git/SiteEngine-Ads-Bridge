<?php
/** Concrete documented wire shape only. No credentials, HTTP client or POST path. */
if ( PHP_SAPI !== 'cli' ) { exit( 2 ); }
$payload = array(
	'trackingId' => 'synthetic-siteengine-form7-entry101', 'source' => 'siteengine',
	'preferredContactMethod' => 'email', 'isExistingCustomer' => false,
	'customer' => array( 'customerRefId' => '00000000-0000-4000-8000-000000000101', 'firstName' => 'Synthetic', 'lastName' => 'Fixture',
		'email' => 'fe-lead-dry-run@example.invalid', 'marketingEmailOptedIn' => false ),
	'appointment' => array( 'preferredDate' => '2026-11-16', 'preferredTime' => '10:00', 'timezone' => 'America/New_York',
		'location' => array( 'addressRefId' => '00000000-0000-4000-8000-000000000102', 'addressLine1' => '123 Synthetic Street', 'city' => 'Synthetic City', 'state' => 'GA', 'postalCode' => '00000', 'country' => 'USA' ) ),
	'serviceRequest' => array( 'serviceCategory' => 'HVAC', 'serviceType' => 'Maintenance', 'issueDescription' => 'Synthetic dry-run only; do not submit' ),
);
echo json_encode( array( 'dry_run' => true, 'network_requests' => 0, 'production_post_authorized' => false,
	'documented_method' => 'POST', 'documented_endpoint' => 'https://api.fieldedge.com/open-api/v1/leads',
	'payload' => $payload, 'payload_sha256' => hash( 'sha256', json_encode( $payload, JSON_THROW_ON_ERROR ) ),
	'holds' => array( 'source_and_service_values_not_tenant_validated', 'trackingId_not_documented_as_idempotency_key', 'production_create_not_authorized' ),
	'duplicate_and_uncertain_result_handling' => array(
		'known_success_same_body' => 'retain returned leadId and original exact body; skip another POST',
		'known_success_different_body' => 'hold conflict; never silently replace prior request',
		'timeout_disconnect_5xx_or_unknown' => 'hold uncertain; no automatic POST retry or regenerated trackingId',
		'duplicate_response' => 'hold until provider confirms existing lead identity; do not assume deduplication',
		'reconcile' => 'use documented GET /v1/leads/{leadId} only when a returned or independently verified leadId exists; no documented trackingId lookup/idempotency guarantee',
	) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
