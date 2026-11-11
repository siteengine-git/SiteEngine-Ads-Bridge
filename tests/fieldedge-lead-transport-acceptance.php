<?php
/** Doubles only: never network, credentials or WordPress persistence. */
require_once __DIR__ . '/../includes/connectors/fieldedge/OAuthClient.php';
require_once __DIR__ . '/../includes/connectors/fieldedge/LeadResponse.php';
require_once __DIR__ . '/../includes/connectors/fieldedge/LeadCreateDryRun.php';
require_once __DIR__ . '/../includes/connectors/fieldedge/LeadSubmission.php';
require_once __DIR__ . '/support/fieldedge/LeadFixture.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient as OAuth;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadResponse as Response;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadCreateDryRun as Lead;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadSubmission;
use SiteEngine\AdsBridge\Tests\FieldEdge\LeadFixture;
$pass = 0;
function transportCheck( bool $ok, string $label ): void { global $pass; if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $label ); } ++$pass; echo "PASS: $label\n"; }
$intent = Lead::prepare( LeadFixture::input() );
$context = array( 'inquiry_id' => 'inq-fixture-context-1', 'source' => array( 'type' => 'fluent_form', 'form_id' => 7, 'entry_id' => 101, 'transaction_id' => 'ff:7:101' ) );
$envelope = json_decode( LeadSubmission::envelope( $intent, $context, 'synthetic-submit-1' ), true, 16, JSON_THROW_ON_ERROR );
transportCheck( $envelope['inquiry_id'] === $context['inquiry_id'] && $envelope['source'] === $context['source'] && $envelope['request']['body'] === $intent['body'], 'explicit AB context retains exact original source and request' );
$bad_context = $context; $bad_context['source']['entry_id'] = 102;
try { LeadSubmission::envelope( $intent, $bad_context, 'synthetic-submit-1' ); transportCheck( false, 'mismatched form refused' ); } catch ( InvalidArgumentException $e ) { transportCheck( $e->getMessage() === 'original_form_binding_mismatch', 'mismatched original form refused before AB call' ); }
$call = Lead::prepare( LeadFixture::input( 'existing' ) );
$call_context = array( 'inquiry_id' => 'inq-fixture-call-1', 'source' => array( 'type' => 'call', 'identity' => 'twilio:CA' . str_repeat( 'a', 32 ), 'evidence_id' => 'synthetic-call-evidence-1' ) );
$call_envelope = json_decode( LeadSubmission::envelope( $call, $call_context, 'synthetic-call-submit-1' ), true );
transportCheck( $call_envelope['source']['type'] === 'call' && ! isset( $call_envelope['source']['form_id'] ), 'call identity never converted to fictional form entry' );
$creds = array( 'client_id' => 'SYNTHETIC', 'client_secret' => 'SYNTHETIC-SECRET', 'target_id' => $intent['tenant_id'] );
$token = array( 'status' => 200, 'content_type' => 'application/json', 'body' => '{"access_token":"synthetic.token","token_type":"Bearer","expires_in":3600}' );
$success = array( 'status' => 201, 'content_type' => 'application/json; charset=utf-8', 'body' => '{"data":{"leadId":"00000000-0000-4000-8000-000000000201","status":"Initiated","receivedAt":"2026-11-11T18:00:00Z","unexpected":"PRIVATE-DO-NOT-RETAIN"}}' );
transportCheck( Response::allowed( 'POST', Response::URL ), 'one exact lead POST route' );
foreach ( array( array( 'GET', Response::URL ), array( 'POST', Response::URL . '/' ), array( 'POST', Response::URL . '?x=1' ), array( 'POST', str_replace( 'https:', 'http:', Response::URL ) ), array( 'POST', 'https://api.fieldedge.com/open-api/v1/customers' ) ) as $r ) { transportCheck( ! Response::allowed( ...$r ), 'alternate method or URL refused' ); }
try { ( new OAuth() )->syntheticLead( $creds, $intent ); transportCheck( false, 'native transport disabled' ); } catch ( RuntimeException $e ) { transportCheck( $e->getMessage() === 'live_lead_transport_disabled', 'default native transport refuses lead dispatch before token request' ); }
$calls = array();
$client = new OAuth( static function ( $m, $u, $h, $b ) use ( &$calls, $token, $success ) { $calls[] = array( $m, $u, $h, $b ); return $u === OAuth::TOKEN_URL ? $token : $success; }, static fn() => 100 );
$response = $client->syntheticLead( $creds, $intent );
transportCheck( count( $calls ) === 2 && $calls[0][1] === OAuth::TOKEN_URL && $calls[1][1] === Response::URL, 'reuses original OAuth path then one exact lead request double' );
transportCheck( $calls[1][3] === $intent['body'] && in_array( 'Content-Type: application/json', $calls[1][2], true ), 'exact retained JSON bytes sent to double' );
transportCheck( in_array( 'Authorization: Bearer synthetic.token', $calls[1][2], true ), 'access token scoped to private double headers' );
$e = Response::classify( $response );
transportCheck( $e['classification'] === 'success' && $e['lead_id'] === '00000000-0000-4000-8000-000000000201' && $e['provider_status'] === 'Initiated' && $e['received_at'] === '2026-11-11T18:00:00Z', 'documented create identity status timestamp retained' );
transportCheck( ! str_contains( json_encode( $e ), 'PRIVATE-DO-NOT-RETAIN' ) && ! str_contains( json_encode( $e ), 'synthetic.token' ), 'only approved identity fields and digest escape classifier' );
foreach ( array( 400, 422 ) as $status ) { transportCheck( Response::classify( array( 'status' => $status, 'body' => 'PRIVATE' ) )['classification'] === 'validation_failed', 'terminal validation held without returning provider error payload' ); }
foreach ( array( 0, 200, 202, 302, 401, 403, 409, 429, 500 ) as $status ) { transportCheck( Response::classify( array( 'status' => $status, 'body' => '{}' ) )['classification'] === 'uncertain', 'unexpected/duplicate/transient HTTP result held uncertain' ); }
foreach ( array( array( 'body' => '{}' ), array( 'body' => '{' ), array( 'content_type' => 'text/html' ), array( 'body' => str_repeat( 'x', 65537 ) ), array( 'body' => '{"data":{"leadId":"bad","status":"Initiated","receivedAt":"2026-11-11T18:00:00Z"}}' ), array( 'body' => '{"data":{"leadId":"00000000-0000-4000-8000-000000000201","status":"Initiated","receivedAt":"no-zone"}}' ) ) as $change ) { transportCheck( Response::classify( array_replace( $success, $change ) )['classification'] === 'uncertain', 'malformed success cannot become authoritative lead identity' ); }
$n = 0; $timeout = new OAuth( static function ( $m, $u ) use ( &$n, $token ) { ++$n; if ( $u === OAuth::TOKEN_URL ) { return $token; } throw new RuntimeException( 'SECRET-TRANSPORT-MESSAGE' ); }, static fn() => 100 );
try { $timeout->syntheticLead( $creds, $intent ); } catch ( RuntimeException $e ) { transportCheck( $n === 2, 'timeout double executes no second lead POST' ); }
$prior_count = count( $calls ); $bad = $creds; $bad['target_id'] = 'synthetic-other';
try { $client->syntheticLead( $bad, $intent ); transportCheck( false, 'wrong tenant refused' ); } catch ( RuntimeException $e ) { transportCheck( $e->getMessage() === 'lead_tenant_or_body_refused' && count( $calls ) === $prior_count, 'wrong token tenant refused before authentication' ); }
echo "RESULT: $pass PASS, 0 FAIL; synthetic OAuth/lead doubles only; no network or persistence.\n";
