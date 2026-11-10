<?php
/** Synthetic HTTP responses only. No credentials, provider calls or WordPress. */
require_once __DIR__ . '/../includes/connectors/fieldedge/OAuthClient.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient;

$passed = 0;
function oauth_check( bool $ok, string $label ): void {
	global $passed;
	if ( ! $ok ) { fwrite( STDERR, "FAIL: $label\n" ); exit( 1 ); }
	++$passed;
	echo "PASS: $label\n";
}
$credentials = array( 'client_id' => 'SYNTHETIC-CLIENT', 'client_secret' => 'SYNTHETIC-NOT-A-SECRET', 'target_id' => 'SYNTHETIC-TENANT' );
$token = array( 'status' => 200, 'content_type' => 'application/json',
	'body' => json_encode( array( 'access_token' => 'synthetic.jwt.token', 'token_type' => 'Bearer', 'expires_in' => 28800 ) ) );
$record = array( 'workOrderId' => 'SYNTHETIC-RECORD-DO-NOT-OUTPUT', 'customerId' => 'SYNTHETIC-CUSTOMER-DO-NOT-OUTPUT',
	'completed' => true, 'serviceAddress' => array( 'address' => 'SYNTHETIC-PII-DO-NOT-OUTPUT' ), 'total' => 'SYNTHETIC-FINANCE-DO-NOT-OUTPUT' );
$read = array( 'status' => 200, 'content_type' => 'application/json; charset=utf-8',
	'body' => json_encode( array( 'total' => 34567, 'offset' => 0, 'limit' => 1, 'data' => array( $record ) ) ) );
$calls = array();
$client = new OAuthClient( static function ( $method, $url, $headers, $body ) use ( &$calls, $token, $read ) {
	$calls[] = array( $method, $url, $headers, $body );
	return 1 === count( $calls ) ? $token : $read;
}, static fn() => 100 );
$result = $client->probe( $credentials );
oauth_check( $result['authentication_succeeded'] && $result['read_succeeded'], 'synthetic token and bounded read succeed' );
oauth_check( count( $calls ) === 2, 'exactly one token and one read request' );
oauth_check( $calls[0][0] === 'POST' && $calls[0][1] === OAuthClient::TOKEN_URL, 'documented OAuth endpoint and method' );
parse_str( $calls[0][3], $form );
oauth_check( array_keys( $form ) === array( 'client_id', 'client_secret', 'target_id', 'grant_type' ) && $form['grant_type'] === 'client_credentials', 'exact documented form fields; no redirect or invented scope/audience' );
oauth_check( in_array( 'Content-Type: application/x-www-form-urlencoded', $calls[0][2], true ), 'token body form encoding' );
oauth_check( $calls[1][0] === 'GET' && $calls[1][1] === OAuthClient::READ_URL && $calls[1][3] === '', 'fixed one-record work-order GET only' );
oauth_check( in_array( 'Authorization: Bearer synthetic.jwt.token', $calls[1][2], true ), 'Bearer authorization header used' );
oauth_check( $result['expires_in_seconds'] === 28800 && $result['token_format'] === 'jwt_shaped_not_locally_verified', 'actual TTL used without unverified JWT claim authority' );
oauth_check( $result['permissions_verified'] === array( 'workorders:read' ), 'only exercised read permission claimed' );
oauth_check( $result['account_identity'] === 'not_independently_verified_company_read_not_granted', 'absent company scope not bypassed or fabricated' );
$safe = json_encode( $result );
foreach ( array_values( $credentials ) as $sentinel ) {
	oauth_check( ! str_contains( $safe, $sentinel ), 'credential value absent from report' );
}
foreach ( array( 'synthetic.jwt.token', 'SYNTHETIC-RECORD', 'SYNTHETIC-CUSTOMER', 'SYNTHETIC-PII', 'SYNTHETIC-FINANCE', '34567' ) as $sentinel ) {
	oauth_check( ! str_contains( $safe, $sentinel ), 'token/PII/financial/count value absent from report' );
}
$run = static function ( array $token_response, array $read_response ) use ( $credentials ): array {
	$count = 0;
	$test = new OAuthClient( static function () use ( &$count, $token_response, $read_response ) {
		return ++$count === 1 ? $token_response : $read_response;
	}, static fn() => 100 );
	return array( $test->probe( $credentials ), $count );
};
foreach ( array( 302, 400, 401, 403, 429, 500 ) as $status ) {
	list( $report, $count ) = $run( array_replace( $token, array( 'status' => $status, 'body' => 'SYNTHETIC-SECRET-ECHO' ) ), $read );
	oauth_check( ! $report['authentication_succeeded'] && $report['reason_code'] === 'oauth_http_rejected' && $count === 1 && ! str_contains( json_encode( $report ), 'SYNTHETIC-SECRET-ECHO' ), "OAuth $status fails safely without GET/retry" );
}
foreach ( array( 302, 401, 403, 429, 500 ) as $status ) {
	list( $report, $count ) = $run( $token, array_replace( $read, array( 'status' => $status ) ) );
	oauth_check( $report['authentication_succeeded'] && ! $report['read_succeeded'] && $report['read_http_status'] === $status && $count === 2, "read $status fails with no follow-up/retry" );
}
foreach ( array(
	array( 'body' => '{}' ), array( 'body' => 'not json' ), array( 'content_type' => 'text/html' ),
	array( 'body' => str_repeat( 'x', 65537 ) ),
	array( 'body' => json_encode( array( 'access_token' => "unsafe\r\nheader", 'token_type' => 'Bearer', 'expires_in' => 60 ) ) ),
	array( 'body' => json_encode( array( 'access_token' => 'synthetic', 'token_type' => 'Basic', 'expires_in' => 60 ) ) ),
	array( 'body' => json_encode( array( 'access_token' => 'synthetic', 'token_type' => 'Bearer', 'expires_in' => 0 ) ) ),
	array( 'body' => json_encode( array( 'access_token' => 'synthetic', 'token_type' => 'Bearer', 'expires_in' => 1 ) ) ),
) as $change ) {
	list( $report, $count ) = $run( array_replace( $token, $change ), $read );
	oauth_check( ! $report['read_succeeded'] && $count === 1, 'invalid/oversized/expired token response never reaches read' );
}
list( $empty_report ) = $run( $token, array_replace( $read, array( 'body' => '{"total":0,"offset":0,"limit":1,"data":[]}' ) ) );
oauth_check( $empty_report['read_succeeded'] && $empty_report['returned_record_count'] === 0, 'empty tenant result is still a verified read' );
list( $oversized_report ) = $run( $token, array_replace( $read, array( 'body' => json_encode( array( 'total' => 2, 'offset' => 0, 'limit' => 2, 'data' => array( $record, $record ) ) ) ) ) );
oauth_check( ! $oversized_report['read_succeeded'], 'provider ignoring one-record limit fails closed' );
$clock_count = 0;
$expired = new OAuthClient( static fn() => $token, static function () use ( &$clock_count ) { return ++$clock_count === 1 ? 100 : 99999; } );
oauth_check( $expired->probe( $credentials )['reason_code'] === 'token_expired_or_too_short_for_read', 'elapsed token lifetime checked before GET' );
$exception = new OAuthClient( static function () { throw new RuntimeException( 'SYNTHETIC-SECRET-ECHO' ); } );
oauth_check( $exception->probe( $credentials )['reason_code'] === 'connection_test_unconfirmed', 'arbitrary transport exception redacted' );
$missing_calls = 0;
$missing = new OAuthClient( static function () use ( &$missing_calls ) { ++$missing_calls; return array(); } );
oauth_check( $missing->probe( array() )['reason_code'] === 'credential_input_invalid' && $missing_calls === 0, 'unprovisioned credentials cause no network calls' );
echo "RESULT: $passed PASS, 0 FAIL; synthetic OAuth/HTTP only; no authenticated production test.\n";
