<?php
/** Offline guard tests only. No WordPress/application/database bootstrapping. */
define( 'SEAB_JOINT_LIBRARY_ONLY', true );
require dirname( __DIR__ ) . '/tools/acceptance/joint-owned-observer.php';
$m = array( 'assignment' => 'ALMA-JOINT-CONSENT-ADS-20261031', 'site' => 'https://alma.siteengine.space/',
    'marker' => 'jct_' . str_repeat( 'a', 32 ), 'prefix' => 'fixture_', 'form_id' => 3, 'phase' => 'J3',
    'cases' => array( array( 'entry_id' => 1, 'contact_id' => 1, 'inquiry_id' => null, 'original_stamp_sha256' => str_repeat( 'b', 64 ),
        'expect' => array( 'current' => false, 'browser' => false, 'qualified' => false, 'converted' => false, 'dispatch_preflight' => false ) ) ) );
$count = 0;
function reject_ledger( $m ) { try { seab_joint_ledger( json_encode( $m ) ); } catch ( Throwable $e ) { return; } throw new RuntimeException( 'unsafe ledger accepted' ); }
if ( seab_joint_ledger( json_encode( $m ) ) !== $m ) { throw new RuntimeException( 'valid fixture ledger rejected' ); } $count++;
foreach ( array( 'assignment' => 'foreign', 'marker' => 'unmarked', 'site' => 'https://other.invalid/', 'prefix' => 'x`; DROP', 'form_id' => '3', 'phase' => 'J9', 'cases' => array() ) as $key => $value ) {
    $bad = $m; $bad[$key] = $value; reject_ledger( $bad ); $count++;
}
$bad = $m; $bad['cases'][] = $m['cases'][0]; reject_ledger( $bad ); $count++;
$bad = $m; $bad['cases'][0]['entry_id'] = 0; reject_ledger( $bad ); $count++;
$bad = $m; $bad['cases'][0]['expect']['current'] = 0; reject_ledger( $bad ); $count++;
$bad = $m; unset( $bad['cases'][0]['expect']['browser'] ); reject_ledger( $bad ); $count++;
$bad = $m; $bad['cases'][0]['original_stamp_sha256'] = 'unknown'; reject_ledger( $bad ); $count++;
try { seab_joint_ledger( str_repeat( 'x', 32769 ) ); throw new LogicException( 'oversize accepted' ); } catch ( RuntimeException $e ) { $count++; }
try { seab_joint_observe( $m ); throw new LogicException( 'unbootstrapped observe accepted' ); } catch ( RuntimeException $e ) { $count++; }
echo "PASS {$count} offline guard assertions; installed observer NOT RUN\n";
