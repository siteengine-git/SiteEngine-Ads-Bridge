<?php
/** Validated synthetic form/call intents only; no credentials, storage or HTTP. */
if ( PHP_SAPI !== 'cli' ) { exit( 2 ); }
require_once __DIR__ . '/../../includes/connectors/fieldedge/LeadCreateDryRun.php';
require_once __DIR__ . '/../../tests/support/fieldedge/LeadFixture.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadCreateDryRun as Lead;
use SiteEngine\AdsBridge\Tests\FieldEdge\LeadFixture;
$mode = $argv[1] ?? 'new';
if ( ! in_array( $mode, array( 'new', 'existing' ), true ) ) { fwrite( STDERR, "Use new or existing synthetic scenario.\n" ); exit( 2 ); }
$intent = Lead::prepare( LeadFixture::input( $mode ) );
$prepared = Lead::state( $intent );
$success = Lead::hypotheticalResponse( $intent, 201, array( 'data' => array( 'leadId' => '00000000-0000-4000-8000-000000000201' ) ) );
echo json_encode( array( 'documented_method' => 'POST', 'documented_endpoint' => 'https://api.fieldedge.com/open-api/v1/leads',
    'intent' => $intent, 'first_decision' => Lead::decide( $intent ), 'duplicate_intent' => Lead::decide( $intent, $prepared ),
    'uncertain_result' => Lead::decide( $intent, Lead::state( $intent, 'uncertain' ) ),
    'hypothetical_result' => $success, 'duplicate_success' => Lead::decide( $intent, $success ),
    'reconciliation_limit' => 'No documented trackingId idempotency/lookup guarantee; use known leadId read or provider assistance, never blind POST retry' ),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
