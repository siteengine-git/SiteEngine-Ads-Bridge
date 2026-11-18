<?php
require_once __DIR__ . '/../includes/connectors/fieldedge/LeadCreateDryRun.php';
require_once __DIR__ . '/support/fieldedge/LeadFixture.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadCreateDryRun as L;
use SiteEngine\AdsBridge\Tests\FieldEdge\LeadFixture as F;
$pass = 0;
function verifyLead( bool $ok, string $label ): void { global $pass; if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $label ); } ++$pass; echo "PASS: $label\n"; }
function refusedLead( array $i, string $reason ): void { try { L::prepare( $i ); } catch ( InvalidArgumentException $e ) { verifyLead( $e->getMessage() === $reason, $reason ); return; } verifyLead( false, $reason ); }
$new = F::input(); $i = L::prepare( $new );
verifyLead( isset( $i['payload']['customer']['phone'] ) && ! isset( $i['payload']['customer']['email'] ), 'documented new phone-only payload without fabricated email' );
verifyLead( ! isset( $i['payload']['customer']['marketingEmailOptedIn'] ) && ! isset( $i['payload']['trackingId'] ), 'marketing opt-in and tracking transport are not invented' );
verifyLead( ! isset( $i['payload']['inquiry_id'] ) && $i['local_inquiry'] === $new['inquiry'], 'original form inquiry/binding stays local' );
verifyLead( L::prepare( $new ) === $i, 'identical input retains exact intent/body/digest' );
$roundtrip = json_decode( json_encode( L::state( $i ), JSON_THROW_ON_ERROR ), true, 8, JSON_THROW_ON_ERROR );
verifyLead( L::decide( $i, $roundtrip )['status'] === 'duplicate_intent', 'duplicate intent detected from caller-retained state' );
verifyLead( L::decide( $i )['status'] === 'dry_run_ready' && ! L::decide( $i )['post_allowed'] && $i['network_requests'] === 0, 'ready dry run never authorizes POST' );
$changed = $new; $changed['service_request']['description'] = 'Different synthetic request'; $different = L::prepare( $changed );
verifyLead( $different['intent_id'] === $i['intent_id'] && L::decide( $different, $roundtrip )['reason'] === 'duplicate_intent_content_conflict', 'same inquiry changed body holds duplicate conflict' );
verifyLead( L::decide( $i, L::state( $i, 'uncertain' ) )['reason'] === 'uncertain_result_no_blind_post_retry', 'uncertain attempt cannot become blind retry' );
$existing = F::input( 'existing' ); $x = L::prepare( $existing );
verifyLead( $x['payload']['isExistingCustomer'] && array_keys( $x['payload']['customer'] ) === array( 'existingCustomerId' ), 'verified existing customer sends only provider customer ID' );
verifyLead( $x['customer_evidence_id'] === $existing['customer']['verified_relationship']['evidence_id'] && $x['local_inquiry'] === $existing['inquiry'], 'call inquiry and verified customer evidence retained locally' );
verifyLead( L::decide( $x, $roundtrip )['reason'] === 'foreign_intent_state', 'foreign prior state held' );
$response = array( 'data' => array( 'leadId' => '00000000-0000-4000-8000-000000000201' ) );
$mapped = L::hypotheticalResponse( $i, 201, $response );
verifyLead( $mapped['hypothetical'] && $mapped['mapping']['inquiry'] === $new['inquiry'] && $mapped['mapping']['tenant_id'] === $new['tenant_id'], 'hypothetical lead ID maps to exact original inquiry and tenant' );
verifyLead( L::decide( $i, $mapped )['status'] === 'duplicate_hypothetical_success', 'known hypothetical success does not create second intent' );
$foreign_mapping = $mapped; $foreign_mapping['mapping']['tenant_id'] = 'synthetic-foreign';
verifyLead( L::decide( $i, $foreign_mapping )['status'] === 'held', 'foreign mapping cannot stand in for this tenant result' );
foreach ( array( 0, 400, 409, 500 ) as $http ) { verifyLead( L::hypotheticalResponse( $i, $http, $response )['phase'] === 'uncertain', 'HTTP ' . $http . ' never assumes lead creation or automatic retry' ); }
verifyLead( L::hypotheticalResponse( $i, 201, array() )['phase'] === 'uncertain', 'missing returned lead ID holds unknown result' );
$bad = $new; unset( $bad['customer']['phone'] ); refusedLead( $bad, 'new_customer_phone_or_email_required' );
$bad = $existing; $bad['customer']['verified_relationship']['tenant_id'] = 'synthetic-wrong'; refusedLead( $bad, 'verified_customer_relationship_required' );
$bad = $existing; $bad['customer']['existing_customer_id'] = 'bad'; refusedLead( $bad, 'invalid_lead_provider_id' );
$bad = $new; unset( $bad['service_request']['category'] ); refusedLead( $bad, 'invalid_lead_field' );
$bad = $new; unset( $bad['appointment']['location']['city'] ); refusedLead( $bad, 'invalid_lead_field' );
$bad = $new; $bad['appointment']['preferred_date'] = '2026-02-30'; refusedLead( $bad, 'invalid_lead_appointment' );
$bad = $new; $bad['appointment']['timezone'] = 'not-a-zone'; refusedLead( $bad, 'invalid_lead_timezone' );
$bad = $new; $bad['preferred_contact_method'] = 'email'; refusedLead( $bad, 'unsupported_preferred_contact_method' );
$bad = $new; $bad['synthetic'] = false; refusedLead( $bad, 'synthetic_lead_input_required' );
$bad = $new; $bad['customer']['email'] = 'real@example.com'; refusedLead( $bad, 'synthetic_email_required' );
$bad = $new; $bad['customer']['phone'] = '9999999999'; refusedLead( $bad, 'synthetic_phone_required' );
$tampered = $i; $tampered['body'] .= ' ';
try { L::decide( $tampered ); verifyLead( false, 'tampered intent refused' ); } catch ( InvalidArgumentException $e ) { verifyLead( $e->getMessage() === 'dry_run_intent_integrity_failed', 'tampered intent refused' ); }
echo "RESULT: $pass PASS, 0 FAIL; pure synthetic lead dry runs; zero network/storage/provider requests.\n";
