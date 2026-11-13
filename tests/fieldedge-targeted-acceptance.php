<?php
/** Synthetic transports + real temporary journal files; no provider calls. */
require_once __DIR__ . '/../includes/connectors/fieldedge/SelectedCaseDiscovery.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\TargetedRead as T;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SelectedCaseJournal as J;
use SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient as C;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SelectedCaseDiscovery as D;
$passed = 0; $files = array();
function tc( bool $ok, string $label ): void { global $passed; if ( ! $ok ) { throw new RuntimeException( "FAIL: $label" ); } ++$passed; echo "PASS: $label\n"; }
function refused( callable $f, string $label ): void { try { $f(); } catch ( Throwable $e ) { tc( true, $label ); return; } tc( false, $label ); }
function journal(): array { global $files; $p = sys_get_temp_dir() . '/se-fe-selected-synthetic-' . bin2hex( random_bytes( 8 ) ) . '.json'; $files[] = $p; return array( new J( $p, str_repeat( 'a', 64 ) ), $p ); }
$uuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$job = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$creds = array( 'client_id' => 'SYNTHETIC-CLIENT', 'client_secret' => 'SYNTHETIC-SECRET', 'target_id' => 'SYNTHETIC-TENANT' );
$token = array( 'status' => 200, 'content_type' => 'application/json', 'body' => '{"access_token":"synthetic.token","token_type":"Bearer","expires_in":28800}' );
$reply = static fn( $v ) => array( 'status' => 200, 'content_type' => 'application/json', 'body' => json_encode( $v, JSON_THROW_ON_ERROR ) );
try {
	foreach ( array(
		array( 'customer_search', array( 'email' => 'synthetic.selector@example.invalid' ) ),
		array( 'customer_search', array( 'phone' => '+1 (555) 000-0101' ) ),
		array( 'customer_work_orders', array( 'customerId' => $uuid ) ),
		array( 'address_work_orders', array( 'postalCode' => '00000', 'city' => 'Synthetic', 'stateOrRegion' => 'ZZ', 'addressLine1' => 'Synthetic only' ) ),
		array( 'work_order', array( 'id' => $uuid ) ), array( 'customer', array( 'id' => $uuid ) ), array( 'lead', array( 'id' => $uuid ) ), array( 'invoice', array( 'id' => $uuid ) ),
	) as $case ) { tc( T::allowed( 'GET', T::url( ...$case ) ), 'documented targeted GET allowed with canonical parameters' ); }
	foreach ( array(
		array( 'customer_search', array() ), array( 'customer_search', array( 'email' => 'bad', 'offset' => 3 ) ),
		array( 'customer_search', array( 'email' => "unsafe\r\nheader" ) ), array( 'customer_work_orders', array( 'customerId' => $uuid, 'updatedAfter' => '2026-01-01' ) ),
		array( 'work_order', array( 'id' => '../escape' ) ), array( 'invoice', array( 'id' => '../escape' ) ), array( 'leads_write', array() ),
	) as $case ) { refused( fn() => T::url( ...$case ), 'missing/extra/unsupported selector or operation refused' ); }
	$url = T::url( 'customer_search', array( 'email' => 'synthetic.selector@example.invalid' ) );
	foreach ( array( 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD' ) as $method ) { tc( ! T::allowed( $method, $url ), 'non-GET business method refused' ); }
	foreach ( array( str_replace( 'https:', 'http:', $url ), str_replace( 'api.fieldedge.com', 'evil.invalid', $url ), $url . '&offset=3', $url . '&limit=100', $url . '#fragment', str_replace( 'api.fieldedge.com', 'user@api.fieldedge.com', $url ), C::READ_URL ) as $bad ) {
		tc( ! T::allowed( 'GET', $bad ), 'off-origin, paging, fragment or arbitrary probe refused in targeted route' );
	}
	list( $j, $p ) = journal(); $network = 0;
	$c = new C( function ( $method ) use ( &$network, $token, $reply, $j, $p ) {
		if ( $method === 'POST' ) { return $token; }
		++$network;
		$disk = $j->snapshot(); // reserve() already fsyncs and checks exact readback on its locked handle.
		tc( count( $disk['attempts'] ) === $network && $disk['attempts'][ $network - 1 ]['status'] === 'attempted_unconfirmed', 'attempt durably counted before dispatch' );
		return $reply( array( 'customers' => array() ) );
	}, static fn() => 100 );
	$out = $c->selectedCase( $creds, $j, static function ( $get ) {
		for ( $i = 0; $i < 7; ++$i ) { $get( 'customer_search', array( 'email' => 'synthetic.selector@example.invalid' ) ); }
		return array();
	} );
	tc( $network === 6 && count( $out['journal']['attempts'] ) === 6 && $out['reason_code'] === 'selected_get_ceiling_reached', 'seventh GET blocked across cumulative pass' );
	tc( ! str_contains( json_encode( $out['journal'] ), 'synthetic.selector' ) && ! str_contains( json_encode( $out['journal'] ), 'SYNTHETIC-SECRET' ), 'journal contains selector hashes, never contact/credential values' );
	$again = $c->selectedCase( $creds, $j, static fn() => array() );
	tc( $again['reason_code'] === 'selected_pass_already_started' && $network === 6 && $again['journal']['token_attempts'] === 1, 'rerun cannot reset counter or refresh token' );
	unset( $j, $c );
	$reopen = new J( $p, str_repeat( 'a', 64 ) );
	tc( count( $reopen->snapshot()['attempts'] ) === 6 && $reopen->snapshot()['closed'], 'fresh journal read preserves six attempts and stop state' ); unset( $reopen );
	list( $j2 ) = journal(); $gets = 0;
	$uncertain = new C( function ( $method ) use ( $token, &$gets ) { if ( $method === 'POST' ) { return $token; } ++$gets; throw new RuntimeException( 'https_transport_failed' ); }, static fn() => 100 );
	$unknown = $uncertain->selectedCase( $creds, $j2, static fn( $get ) => $get( 'work_order', array( 'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' ) ) );
	tc( $gets === 1 && count( $unknown['journal']['attempts'] ) === 1 && $unknown['journal']['attempts'][0]['status'] === 'transport_unconfirmed', 'uncertain GET consumes one attempt without retry' ); unset( $j2, $uncertain );
	$entries = array();
	foreach ( array( 101, 102 ) as $id ) { $entries[] = array( 'form_id' => 7, 'entry_id' => $id, 'created_at' => '2026-11-01 12:00:00', 'AB_inquiry_rows' => array(), 'retained_response' => array(
		'email' => 'synthetic' . $id . '@example.invalid', 'street_address' => 'Synthetic only', 'zip_code' => '00000', 'city_1' => 'Synthetic', 'state_1' => 'ZZ', 'transaction_id' => 'ff:7:' . $id, 'service_type' => 'maintenance' ) ); }
	foreach ( array( false, true ) as $explicit ) {
		list( $jx ) = journal(); $calls = 0;
		$driver = new C( function ( $method, $uri ) use ( $token, $reply, $uuid, $job, $explicit, &$calls ) {
			if ( $method === 'POST' ) { return $token; } ++$calls;
			if ( str_contains( $uri, '/customers/search?' ) ) {
				$email = str_contains( $uri, 'synthetic102' ) ? 'synthetic102@example.invalid' : 'synthetic101@example.invalid';
				return $reply( array( 'total' => 1, 'customers' => array( array( 'id' => $uuid, 'primaryEmail' => $email ) ) ) );
			}
			$work = array( 'workOrderId' => $job, 'customerId' => $uuid, 'serviceAddress' => array( 'addressLine1' => 'Synthetic only', 'postalCode' => '00000' ), 'workDescription' => 'maintenance' );
			if ( $explicit ) { $work['trackingId'] = 'ff:7:101'; }
			return str_contains( $uri, 'customerId=' ) ? $reply( array( 'total' => 1, 'data' => array( $work ) ) ) : $reply( $work );
		}, static fn() => 100 );
		$found = $driver->selectedCase( $creds, $jx, static fn( $get, $j ) => D::run( $get, $j, $entries ) );
		if ( $explicit ) { tc( $calls === 3 && count( $found['findings'] ) === 1 && $found['findings'][0]['disposition'] === 'Attributed', 'explicit source reference stops after one defensible pair' ); }
		else { tc( $calls === 6 && count( $found['findings'] ) === 2 && array_column( $found['findings'], 'disposition' ) === array( 'Needs Review', 'Needs Review' ), 'contact/address/service match alone cannot establish inquiry attribution' ); }
		tc( ! str_contains( json_encode( $found ), 'synthetic101@example.invalid' ) && ! str_contains( json_encode( $found ), 'maintenance' ), 'findings omit contact/service payload values' );
		unset( $jx, $driver );
	}
	list( $jf ) = journal(); $failed_gets = 0;
	$failure = new C( function ( $method ) use ( $token, &$failed_gets ) { if ( $method === 'POST' ) { return $token; } ++$failed_gets; return array( 'status' => 403, 'content_type' => 'application/json', 'body' => '{}' ); }, static fn() => 100 );
	$partial = $failure->selectedCase( $creds, $jf, static fn( $get, $j ) => D::run( $get, $j, $entries ) );
	tc( $partial['reason_code'] === 'selected_request_failure_stop' && $partial['journal']['closed'], 'partial request failure propagates failed stop rather than completed' );
	tc( $failed_gets === 1 && count( $partial['findings'] ) === 1 && $partial['findings'][0]['request_failure_stop'], 'partial findings and one attempted GET retained; second entry not queried' );
	tc( $partial['journal']['attempts'][0]['http_status'] === 403 && $partial['findings'][0]['disposition'] === 'Needs Review', 'failure status preserved without negative association inference' );
	unset( $jf, $failure );
	echo "RESULT: $passed PASS, 0 FAIL; synthetic targeted transport and native private-journal files only.\n";
} finally { foreach ( $files as $p ) { if ( file_exists( $p ) ) { unlink( $p ); } } }
