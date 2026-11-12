<?php
/**
 * FE connector diagnostics regression: safe fields, readiness and admin isolation.
 */
require_once __DIR__ . '/../includes/connectors/fieldedge/ConnectorDiagnostics.php';

use SiteEngine\AdsBridge\Connectors\FieldEdge\ConnectorDiagnostics as Diagnostics;
use SiteEngine\AdsBridge\Connectors\FieldEdge\CRMProjection;

$passed = 0;
function expect_status( bool $condition, string $label ): void {
	global $passed;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	++$passed;
	echo 'PASS: ' . $label . "\n";
}

$missing = Diagnostics::summarize( array(), true, true );
expect_status( $missing['crm_field_schema'] === 'missing_fields', 'missing CRM field definitions detected' );
expect_status( $missing['crm_fields_missing'] === array( CRMProjection::IDENTITY, CRMProjection::CUSTOMER, CRMProjection::JOBS ), 'only owned field names reported' );
expect_status( $missing['crm_projection'] === 'not_ready', 'projection unavailable until fields provisioned' );

$registry = array_merge(
	array( array( 'slug' => 'unrelated-secret', 'type' => 'text', 'value' => 'private-customer-value' ) ),
	CRMProjection::definitions()
);
$ready = Diagnostics::summarize( $registry, true, true );
expect_status( $ready['crm_field_schema'] === 'ready' && $ready['crm_projection'] === 'synthetic_isolated_cli_only', 'supported projection marked synthetic-only' );
expect_status( $ready['ab_prospective_contract'] === 'callables_present', 'AB callable readiness reported without invoking interface' );
expect_status( $ready['lead_creation'] === 'production_post_disabled' && $ready['automatic_sync'] === 'disabled', 'no production dispatch or automatic sync represented' );
expect_status( $ready['provider_connection'] === 'not_tested_by_diagnostics' && $ready['provider_http_requests'] === 0, 'provider reachability is not invented' );
expect_status( $ready['database_writes'] === 0 && $ready['customer_records_in_output'] === false, 'read-only, non-customer-output contract reported' );
expect_status( ! str_contains( json_encode( $ready, JSON_THROW_ON_ERROR ), 'private-customer-value' ) &&
	! str_contains( json_encode( $ready, JSON_THROW_ON_ERROR ), 'unrelated-secret' ), 'unrelated registry names and values excluded' );

$wrong = $registry;
$wrong[2]['type'] = 'number';
$conflict = Diagnostics::summarize( $wrong, true, true );
expect_status( $conflict['crm_field_schema'] === 'type_conflict' && $conflict['crm_fields_type_conflict'] === array( CRMProjection::CUSTOMER ), 'owned field type conflict held' );

$duplicate = $registry;
$duplicate[] = CRMProjection::definitions()[0];
expect_status( Diagnostics::summarize( $duplicate, true, true )['crm_field_schema'] === 'invalid_registry', 'duplicate registry field held' );
$malformed = array( array( 'slug' => null, 'type' => 'text' ) );
expect_status( Diagnostics::summarize( $malformed, true, true )['crm_field_schema'] === 'invalid_registry', 'malformed registry held' );

$unavailable = Diagnostics::summarize( null, false, false, 'unavailable' );
expect_status( $unavailable['crm_field_schema'] === 'not_verified' &&
	$unavailable['crm_field_registry_read'] === 'unavailable' &&
	$unavailable['ab_prospective_contract'] === 'callables_missing', 'dependency unavailability explicit' );
$unknown = Diagnostics::summarize( null, true, false, 'unconfirmed' );
expect_status( $unknown['crm_field_registry_read'] === 'unconfirmed', 'failed read does not claim absent fields' );
expect_status( $ready['consent_and_dnc'] === 'not_changed' && $ready['advertising_attribution'] === 'not_assessed', 'marketing decisions remain AB-owned' );

try {
	Diagnostics::wordpress();
	expect_status( false, 'WordPress admin gate' );
} catch ( RuntimeException $error ) {
	expect_status( $error->getMessage() === 'administrator_required', 'WordPress admin gate rejects non-WordPress calls' );
}

echo "RESULT: $passed PASS, 0 FAIL; pure synthetic registry, no WordPress, provider or database access.\n";
