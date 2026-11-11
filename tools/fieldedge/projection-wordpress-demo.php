<?php
/** AB executes inside its retained isolated fixture; never creates definitions. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ||
	! defined( 'SITEENGINE_FE_PROJECTION_ISOLATED' ) || SITEENGINE_FE_PROJECTION_ISOLATED !== true ||
	! isset( $args ) || count( $args ) !== 3 || ! hash_equals( home_url(), $args[1] ) ||
	! hash_equals( realpath( ABSPATH ), realpath( $args[2] ) ?: '' ) ) { throw new RuntimeException( 'explicit_isolated_identity_required' ); }
require_once __DIR__ . '/../../includes/connectors/fieldedge/FluentCRMProjectionContacts.php';
require_once __DIR__ . '/../../tests/support/fieldedge/ProjectionFixture.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\CRMProjection;
use SiteEngine\AdsBridge\Connectors\FieldEdge\FluentCRMProjectionContacts;
use SiteEngine\AdsBridge\Tests\FieldEdge\ProjectionFixture;

if ( $args[0] === 'definitions' ) { echo CRMProjection::json( CRMProjection::definitions() ) . "\n"; return; }
if ( ! in_array( $args[0], array( 'initial', 'updated', 'replay', 'conflict', 'read' ), true ) ) { throw new InvalidArgumentException( 'unknown_fixture_action' ); }
// Request-local backstops only; no existing sending/automation setting is changed.
add_filter( 'pre_http_request', static fn() => new WP_Error( 'fe_synthetic_no_network' ), PHP_INT_MAX );
add_filter( 'pre_wp_mail', static fn() => false, PHP_INT_MAX );
$input = ProjectionFixture::input();
if ( $args[0] !== 'initial' ) {
	$existing = FluentCrmApi( 'contacts' )->getContact( $input['customer']['email'] );
	$expected = CRMProjection::json( array( 'provider' => $input['provider'], 'tenant_id' => $input['tenant_id'], 'customer_id' => $input['customer']['id'] ) );
	if ( ! $existing || ( $existing->custom_fields()[ CRMProjection::IDENTITY ] ?? null ) !== $expected ) { throw new RuntimeException( 'existing_owned_fixture_required' ); }
}
if ( in_array( $args[0], array( 'updated', 'replay', 'conflict' ), true ) ) {
	$input['job']['status'] = 'completed';
	$input['job']['completed_at'] = '2026-11-12T15:00:00Z';
	$input['job']['source_updated_at'] = '2026-11-12T15:05:00Z';
}
if ( $args[0] === 'conflict' ) {
	$input['customer']['id'] = $input['job']['customer_id'] = 'synthetic-customer-conflict';
	$input['customer']['email'] = 'fe-projection-conflict@example.invalid';
}
if ( $args[0] === 'read' ) {
	$contact = FluentCrmApi( 'contacts' )->getContact( $input['customer']['email'] );
	if ( ! $contact ) { throw new RuntimeException( 'fixture_not_present' ); }
	$fields = $contact->custom_fields();
	echo CRMProjection::json( array( 'verification' => 'fresh installed FluentCRM read', 'contact_id' => (int) $contact->id,
		'fixture_unsubscribed' => $contact->status === 'unsubscribed',
		'owned_fields' => array_intersect_key( $fields, array_fill_keys( array( CRMProjection::IDENTITY, CRMProjection::CUSTOMER, CRMProjection::JOBS ), true ) ) ) ) . "\n";
	return;
}
$result = CRMProjection::run( $input, new FluentCRMProjectionContacts() );
echo CRMProjection::json( array( 'verification' => 'actual FluentCRM API in AB-selected isolated fixture', 'result' => $result ) ) . "\n";
if ( $result['status'] === 'held' && $args[0] !== 'conflict' ) { throw new RuntimeException( 'projection_held_inspect_before_retry' ); }
