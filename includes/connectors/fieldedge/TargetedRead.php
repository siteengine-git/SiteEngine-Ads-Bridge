<?php
/** Narrow documented identity lookups; offset/page sweeping and invoice reads absent. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

final class TargetedRead {
	public static function url( string $operation, array $parameters ): string {
		$base = 'https://api.fieldedge.com/open-api/v1/';
		$keys = array_keys( $parameters );
		sort( $keys );
		if ( 'customer_search' === $operation && ( $keys === array( 'email' ) || $keys === array( 'phone' ) ) ) {
			$key = $keys[0];
			$value = $parameters[ $key ];
			self::text( $value, 254 );
			if ( 'email' === $key && ! filter_var( $value, FILTER_VALIDATE_EMAIL ) ||
				'phone' === $key && ! preg_match( '/\A[+0-9() .-]{7,64}\z/D', $value ) ) {
				throw new \RuntimeException( 'targeted_parameter_refused' );
			}
			return $base . 'customers/search?' . http_build_query( array( $key => $value, 'offset' => 0, 'limit' => 3 ), '', '&', PHP_QUERY_RFC3986 );
		}
		if ( 'customer_work_orders' === $operation && $keys === array( 'customerId' ) ) {
			self::id( $parameters['customerId'] );
			return $base . 'work-orders?' . http_build_query( array( 'customerId' => $parameters['customerId'], 'offset' => 0, 'limit' => 3 ), '', '&', PHP_QUERY_RFC3986 );
		}
		if ( 'address_work_orders' === $operation && $keys === array( 'addressLine1', 'city', 'postalCode', 'stateOrRegion' ) ) {
			foreach ( $parameters as $value ) { self::text( $value, 250 ); }
			$ordered = array_intersect_key( array_fill_keys( array( 'addressLine1', 'city', 'stateOrRegion', 'postalCode' ), '' ), $parameters );
			foreach ( $ordered as $key => &$value ) { $value = $parameters[ $key ]; }
			unset( $value );
			return $base . 'work-orders/search/by-address?' . http_build_query( $ordered + array( 'offset' => 0, 'limit' => 3 ), '', '&', PHP_QUERY_RFC3986 );
		}
		$routes = array( 'work_order' => 'work-orders/', 'customer' => 'customers/', 'lead' => 'leads/', 'invoice' => 'invoices/' );
		if ( isset( $routes[ $operation ] ) && $keys === array( 'id' ) ) {
			self::id( $parameters['id'] );
			return $base . $routes[ $operation ] . rawurlencode( $parameters['id'] );
		}
		throw new \RuntimeException( 'targeted_operation_refused' );
	}

	private static function text( $value, int $limit ): void {
		if ( ! is_string( $value ) || '' === trim( $value ) || strlen( $value ) > $limit || preg_match( '/[\x00-\x1f\x7f]/', $value ) ) {
			throw new \RuntimeException( 'targeted_parameter_refused' );
		}
	}
	public static function id( $value ): void {
		if ( ! is_string( $value ) || ! preg_match( '/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/Di', $value ) ) {
			throw new \RuntimeException( 'targeted_reference_refused' );
		}
	}
	public static function allowed( string $method, string $url ): bool {
		if ( 'GET' !== $method ) { return false; }
		$p = parse_url( $url );
		if ( ! is_array( $p ) || ( $p['scheme'] ?? null ) !== 'https' || ( $p['host'] ?? null ) !== 'api.fieldedge.com' ||
			isset( $p['user'], $p['pass'] ) || isset( $p['user'] ) || isset( $p['port'] ) || isset( $p['fragment'] ) ) { return false; }
		$query = array();
		parse_str( $p['query'] ?? '', $query );
		try {
			if ( ( $p['path'] ?? '' ) === '/open-api/v1/customers/search' ) {
				unset( $query['offset'], $query['limit'] );
				return self::url( 'customer_search', $query ) === $url;
			}
			if ( ( $p['path'] ?? '' ) === '/open-api/v1/work-orders' ) {
				unset( $query['offset'], $query['limit'] );
				return self::url( 'customer_work_orders', $query ) === $url;
			}
			if ( ( $p['path'] ?? '' ) === '/open-api/v1/work-orders/search/by-address' ) {
				unset( $query['offset'], $query['limit'] );
				return self::url( 'address_work_orders', $query ) === $url;
			}
			if ( ! $query && preg_match( '#\A/open-api/v1/(work-orders|customers|leads|invoices)/([^/]+)\z#D', $p['path'] ?? '', $matches ) ) {
				$operation = array( 'work-orders' => 'work_order', 'customers' => 'customer', 'leads' => 'lead', 'invoices' => 'invoice' )[ $matches[1] ];
				return self::url( $operation, array( 'id' => $matches[2] ) ) === $url;
			}
		} catch ( \Throwable $error ) { return false; }
		return false;
	}
}

/** One private pass journal. Counts are reserved/read back BEFORE network dispatch. */
final class SelectedCaseJournal {
	private $file;
	private array $state;
	public function __construct( string $path, string $evidence_hash ) {
		if ( ! preg_match( '/\A[a-f0-9]{64}\z/D', $evidence_hash ) || is_link( $path ) || is_link( dirname( $path ) ) ) {
			throw new \RuntimeException( 'private_journal_refused' );
		}
		$new = ! file_exists( $path );
		$this->file = @fopen( $path, $new ? 'x+b' : 'r+b' );
		if ( ! $this->file || ! flock( $this->file, LOCK_EX | LOCK_NB ) ) { throw new \RuntimeException( 'private_journal_busy' ); }
		if ( $new ) {
			$this->state = array( 'schema' => 1, 'evidence_sha256' => $evidence_hash, 'ceiling' => 6, 'closed' => false, 'token_attempts' => 0, 'attempts' => array() );
			$this->save();
		} else {
			$this->state = json_decode( stream_get_contents( $this->file, 65537 ), true, 16, JSON_THROW_ON_ERROR );
			if ( ( $this->state['schema'] ?? null ) !== 1 || ( $this->state['evidence_sha256'] ?? null ) !== $evidence_hash ||
				( $this->state['ceiling'] ?? null ) !== 6 || ! is_array( $this->state['attempts'] ?? null ) ||
				count( $this->state['attempts'] ) > 6 || ! is_int( $this->state['token_attempts'] ?? null ) ) {
				throw new \RuntimeException( 'private_journal_invalid' );
			}
		}
	}
	private function save(): void {
		$body = json_encode( $this->state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT );
		rewind( $this->file );
		if ( ! ftruncate( $this->file, 0 ) || fwrite( $this->file, $body ) !== strlen( $body ) || ! fflush( $this->file ) || ! fsync( $this->file ) ) {
			throw new \RuntimeException( 'private_journal_write_unconfirmed' );
		}
		rewind( $this->file );
		if ( stream_get_contents( $this->file ) !== $body ) { throw new \RuntimeException( 'private_journal_readback_unconfirmed' ); }
	}
	public function startToken(): void {
		if ( $this->state['closed'] || $this->state['token_attempts'] !== 0 || $this->state['attempts'] ) {
			throw new \RuntimeException( 'selected_pass_already_started' );
		}
		++$this->state['token_attempts'];
		$this->save();
	}
	public function reserve( string $operation, array $parameters ): int {
		TargetedRead::url( $operation, $parameters );
		if ( $this->state['closed'] || count( $this->state['attempts'] ) >= 6 ) { throw new \RuntimeException( 'selected_get_ceiling_reached' ); }
		$this->state['attempts'][] = array( 'number' => count( $this->state['attempts'] ) + 1, 'operation' => $operation,
			'selector_sha256' => hash( 'sha256', json_encode( $parameters, JSON_THROW_ON_ERROR ) ), 'status' => 'attempted_unconfirmed', 'http_status' => null );
		$this->save();
		return count( $this->state['attempts'] );
	}
	public function complete( int $number, ?int $http, string $status, array $shape = array() ): void {
		if ( ! isset( $this->state['attempts'][ $number - 1 ] ) ) { throw new \RuntimeException( 'private_journal_invalid' ); }
		$this->state['attempts'][ $number - 1 ]['http_status'] = $http;
		$this->state['attempts'][ $number - 1 ]['status'] = $status;
		if ( $shape ) { $this->state['attempts'][ $number - 1 ]['response_shape'] = $shape; }
		$this->save();
	}
	public function remaining(): int { return 6 - count( $this->state['attempts'] ); }
	public function close( string $reason ): void { $this->state['closed'] = true; $this->state['stop_reason'] = $reason; $this->save(); }
	public function snapshot(): array { return $this->state; }
	public function __destruct() { if ( is_resource( $this->file ) ) { flock( $this->file, LOCK_UN ); fclose( $this->file ); } }
}
