<?php
/** Pure request classification. Does not wait, call providers or schedule retries. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

final class Retry {
	public static function classify( int $status, string $message, ?string $retry_after, int $attempt, int $now ): array {
		if ( $status >= 200 && $status < 300 ) {
			return array( 'state' => 'complete' );
		}
		if ( $attempt < 0 || $attempt >= 5 ) {
			return array( 'state' => 'held', 'reason_code' => 'retry_budget_exhausted' );
		}
		if ( 403 === $status && str_starts_with( $message, 'Out of call volume quota.' ) ) {
			return array( 'state' => 'deferred', 'reason_code' => 'partner_hourly_quota', 'not_before' => ( intdiv( $now, 3600 ) + 1 ) * 3600 );
		}
		if ( 429 === $status || 0 === $status || $status >= 500 && $status <= 599 ) {
			$delay = min( 60, 2 ** $attempt );
			if ( 429 === $status && null !== $retry_after ) {
				if ( ! preg_match( '/\A\d{1,6}\z/D', $retry_after ) || (int) $retry_after > 86400 ) {
					return array( 'state' => 'held', 'reason_code' => 'retry_after_requires_review' );
				}
				$delay = max( $delay, (int) $retry_after );
			}
			return array( 'state' => 'deferred', 'reason_code' => 'transient_read_failure', 'not_before' => $now + $delay );
		}
		return array( 'state' => 'held', 'reason_code' => 'authorization_or_record_review' );
	}
}
