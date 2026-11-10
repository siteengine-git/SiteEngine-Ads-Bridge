<?php
/** One token + one read-only FieldEdge request; no persistence, sync or AB writes. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

require_once __DIR__ . '/TargetedRead.php';

final class OAuthClient {
	public const TOKEN_URL = 'https://fieldedge.withgobo.com/oauth/token';
	public const READ_URL = 'https://api.fieldedge.com/open-api/v1/work-orders?offset=0&limit=1';
	private const MAX_BYTES = 65536;
	private \Closure $transport;
	private \Closure $clock;

	/** Test transport receives method, fixed URL, headers and exact request body. */
	public function __construct( ?callable $transport = null, ?callable $clock = null ) {
		$this->transport = \Closure::fromCallable( $transport ?? array( self::class, 'https' ) );
		$this->clock = \Closure::fromCallable( $clock ?? 'time' );
	}

	/** Only sanitized structure/status escapes this method, including on failures. */
	public function probe( array $credentials ): array {
		$report = array(
			'authentication_succeeded' => false, 'read_succeeded' => false,
			'token_endpoint' => self::TOKEN_URL, 'read_endpoint' => self::READ_URL,
			'token_http_status' => null, 'read_http_status' => null,
			'account_identity' => 'not_independently_verified_company_read_not_granted',
			'tenant_binding' => 'operator_supplied_target_id_in_token_request',
			'permissions_verified' => array(), 'automatic_retries' => 0,
		);
		try {
			$session = $this->authenticate( $credentials, $report );
			unset( $credentials );
			$token = $session['token'];
			$response = ( $this->transport )( 'GET', self::READ_URL,
				array( 'Accept: application/json', 'Authorization: Bearer ' . $token ), '' );
			unset( $token );
			$report['read_http_status'] = $this->status( $response );
			if ( 200 !== $report['read_http_status'] ) {
				throw new \RuntimeException( 'read_http_rejected' );
			}
			$data = $this->object( $response );
			if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) || ! array_is_list( $data['data'] ) || count( $data['data'] ) > 1 ||
				! is_int( $data['limit'] ?? null ) || 1 !== $data['limit'] ||
				! is_int( $data['offset'] ?? null ) || 0 !== $data['offset'] || ! is_int( $data['total'] ?? null ) ) {
				throw new \RuntimeException( 'bounded_read_structure_unverified' );
			}
			$report['response_structure'] = array( 'total' => 'integer_value_redacted', 'limit' => 'integer', 'offset' => 'integer', 'data' => 'array_of_zero_or_one_records' );
			$report['returned_record_count'] = count( $data['data'] );
			$report['record_field_types'] = array();
			if ( $data['data'] ) {
				if ( ! is_array( $data['data'][0] ) || array_is_list( $data['data'][0] ) ) {
					throw new \RuntimeException( 'bounded_record_structure_unverified' );
				}
				// Known schema names only. Never output arbitrary keys or any values.
				foreach ( array( 'workOrderId', 'completed', 'customerId', 'invoiceId', 'billingCustomerId', 'serviceAddress', 'resolution', 'scheduledStart', 'scheduledEnd' ) as $field ) {
					if ( array_key_exists( $field, $data['data'][0] ) ) {
						$report['record_field_types'][ $field ] = get_debug_type( $data['data'][0][ $field ] );
					}
				}
			}
			unset( $data, $response );
			$report['read_succeeded'] = true;
			$report['permissions_verified'] = array( 'workorders:read' );
			$report['reason_code'] = 'bounded_read_verified';
		} catch ( \Throwable $error ) {
			$allowed = array( 'credential_input_invalid', 'oauth_http_rejected', 'oauth_response_invalid', 'token_expired_or_too_short_for_read',
				'read_http_rejected', 'bounded_read_structure_unverified', 'bounded_record_structure_unverified',
				'https_transport_unavailable', 'https_transport_failed', 'response_too_large', 'response_not_json', 'response_structure_invalid' );
			$report['reason_code'] = in_array( $error->getMessage(), $allowed, true ) ? $error->getMessage() : 'connection_test_unconfirmed';
		}
		return $report;
	}

	/** Shared original OAuth logic; credentials/token never persisted or returned to UI. */
	private function authenticate( array $credentials, array &$report ): array {
		foreach ( array( 'client_id' => 512, 'client_secret' => 8192, 'target_id' => 128 ) as $field => $limit ) {
			$value = $credentials[ $field ] ?? null;
			if ( ! is_string( $value ) || '' === trim( $value ) || strlen( $value ) > $limit || preg_match( '/[\x00-\x1f\x7f]/', $value ) ) {
				throw new \RuntimeException( 'credential_input_invalid' );
			}
		}
		$started = ( $this->clock )();
		$response = ( $this->transport )( 'POST', self::TOKEN_URL,
			array( 'Content-Type: application/x-www-form-urlencoded', 'Accept: application/json' ),
			http_build_query( array( 'client_id' => $credentials['client_id'], 'client_secret' => $credentials['client_secret'],
				'target_id' => $credentials['target_id'], 'grant_type' => 'client_credentials' ), '', '&', PHP_QUERY_RFC3986 ) );
		$report['token_http_status'] = $this->status( $response );
		if ( 200 !== $report['token_http_status'] ) { throw new \RuntimeException( 'oauth_http_rejected' ); }
		$data = $this->object( $response );
		$token = $data['access_token'] ?? null;
		$ttl = $data['expires_in'] ?? null;
		if ( is_string( $ttl ) && preg_match( '/\A[1-9][0-9]{0,9}\z/D', $ttl ) ) { $ttl = (int) $ttl; }
		if ( ! is_string( $token ) || strlen( $token ) > 32768 || ! preg_match( '/\A[A-Za-z0-9._~+\/-]+=*\z/D', $token ) ||
			! is_string( $data['token_type'] ?? null ) || 0 !== strcasecmp( 'Bearer', $data['token_type'] ) ||
			! is_int( $ttl ) || $ttl < 1 || $ttl > 2147483647 ) { throw new \RuntimeException( 'oauth_response_invalid' ); }
		$report['authentication_succeeded'] = true;
		$report['token_type'] = 'Bearer';
		$report['expires_in_seconds'] = $ttl;
		$report['token_format'] = 2 === substr_count( $token, '.' ) ? 'jwt_shaped_not_locally_verified' : 'opaque_bearer';
		if ( ( $this->clock )() + 5 >= $started + $ttl ) { throw new \RuntimeException( 'token_expired_or_too_short_for_read' ); }
		return array( 'token' => $token, 'expires_at' => $started + $ttl );
	}

	/** One token, finite selected-case workflow. Raw data stays in private trusted PHP. */
	public function selectedCase( array $credentials, SelectedCaseJournal $journal, callable $workflow ): array {
		$report = array( 'authentication_succeeded' => false, 'token_http_status' => null, 'automatic_retries' => 0 );
		try {
			$journal->startToken();
			$session = $this->authenticate( $credentials, $report );
			unset( $credentials );
			$get = function ( string $operation, array $parameters ) use ( $session, $journal ): array {
				$url = TargetedRead::url( $operation, $parameters );
				if ( ! TargetedRead::allowed( 'GET', $url ) ) { throw new \RuntimeException( 'targeted_operation_refused' ); }
				if ( ( $this->clock )() + 5 >= $session['expires_at'] ) { throw new \RuntimeException( 'token_expired_or_too_short_for_read' ); }
				$number = $journal->reserve( $operation, $parameters );
				$status = null;
				try {
					$response = ( $this->transport )( 'GET', $url, array( 'Accept: application/json', 'Authorization: Bearer ' . $session['token'] ), '' );
					$status = $this->status( $response );
					$journal->complete( $number, $status, 'http_received' );
					if ( 200 !== $status ) { throw new \RuntimeException( 'targeted_http_rejected' ); }
					$object = $this->object( $response );
					$shape = array();
					foreach ( array( 'total', 'offset', 'limit', 'customers', 'data', 'workOrderId', 'customerId', 'leadId' ) as $key ) {
						if ( array_key_exists( $key, $object ) ) { $shape[ $key ] = get_debug_type( $object[ $key ] ); }
					}
					$journal->complete( $number, $status, 'json_received', $shape );
					return $object;
				} catch ( \Throwable $error ) {
					if ( null === $status ) { $journal->complete( $number, null, 'transport_unconfirmed' ); }
					throw $error;
				}
			};
			$report['findings'] = $workflow( $get, $journal );
			$failed = array_filter( $report['findings'], static fn( $f ) => ! empty( $f['request_failure_stop'] ) );
			$report['reason_code'] = $failed ? 'selected_request_failure_stop' : 'selected_case_completed';
			$journal->close( $report['reason_code'] );
		} catch ( \Throwable $error ) {
			$codes = array( 'selected_pass_already_started', 'selected_get_ceiling_reached', 'targeted_parameter_refused', 'targeted_operation_refused',
				'targeted_reference_refused', 'targeted_http_rejected', 'oauth_http_rejected', 'oauth_response_invalid', 'https_transport_failed',
				'response_too_large', 'response_not_json', 'response_structure_invalid', 'bounded_targeted_structure_unverified', 'token_expired_or_too_short_for_read' );
			$report['reason_code'] = in_array( $error->getMessage(), $codes, true ) ? $error->getMessage() : 'selected_case_unconfirmed';
			if ( $report['reason_code'] !== 'selected_pass_already_started' ) { $journal->close( $report['reason_code'] ); }
		}
		$report['journal'] = $journal->snapshot();
		return $report;
	}

	private function status( array $response ): int {
		$status = $response['status'] ?? null;
		if ( ! is_int( $status ) || $status < 100 || $status > 599 ) {
			throw new \RuntimeException( 'https_transport_failed' );
		}
		return $status;
	}

	private function object( array $response ): array {
		$body = $response['body'] ?? null;
		if ( ! is_string( $body ) || strlen( $body ) > self::MAX_BYTES ) {
			throw new \RuntimeException( 'response_too_large' );
		}
		if ( ! is_string( $response['content_type'] ?? null ) || ! preg_match( '/\Aapplication\/json(?:\s*;|\z)/i', $response['content_type'] ) ) {
			throw new \RuntimeException( 'response_not_json' );
		}
		try {
			$object = json_decode( $body, false, 24, JSON_THROW_ON_ERROR );
			if ( ! $object instanceof \stdClass ) {
				throw new \RuntimeException();
			}
			return json_decode( $body, true, 24, JSON_THROW_ON_ERROR );
		} catch ( \Throwable $error ) {
			throw new \RuntimeException( 'response_structure_invalid' );
		}
	}

	/** TLS-verifying bounded cURL transport. Only the two exact requests exist. */
	private static function https( string $method, string $url, array $headers, string $body ): array {
		if ( ! function_exists( 'curl_init' ) ) {
			throw new \RuntimeException( 'https_transport_unavailable' );
		}
		if ( ! ( 'POST' === $method && self::TOKEN_URL === $url || 'GET' === $method && self::READ_URL === $url || TargetedRead::allowed( $method, $url ) ) ) {
			throw new \RuntimeException( 'https_transport_failed' );
		}
		$handle = curl_init( $url );
		$received = '';
		$header_bytes = 0;
		$oversized = false;
		try {
			curl_setopt_array( $handle, array(
				CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
				CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
				CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_VERBOSE => false,
				CURLOPT_HEADERFUNCTION => static function ( $unused, string $line ) use ( &$header_bytes, &$oversized ): int {
					$header_bytes += strlen( $line );
					if ( $header_bytes > 8192 ) {
						$oversized = true;
						return 0;
					}
					return strlen( $line );
				},
				CURLOPT_WRITEFUNCTION => static function ( $unused, string $chunk ) use ( &$received, &$oversized ): int {
					if ( strlen( $received ) + strlen( $chunk ) > self::MAX_BYTES ) {
						$oversized = true;
						return 0;
					}
					$received .= $chunk;
					return strlen( $chunk );
				},
			) );
			if ( PHP_OS_FAMILY === 'Windows' && defined( 'CURLSSLOPT_NATIVE_CA' ) ) {
				// Use the existing Windows trust store; never disable verification.
				curl_setopt( $handle, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA );
			}
			if ( 'POST' === $method ) {
				curl_setopt( $handle, CURLOPT_POST, true );
				curl_setopt( $handle, CURLOPT_POSTFIELDS, $body );
			}
			$ok = curl_exec( $handle );
			if ( $oversized ) {
				throw new \RuntimeException( 'response_too_large' );
			}
			if ( false === $ok ) {
				throw new \RuntimeException( 'https_transport_failed' );
			}
			return array( 'status' => curl_getinfo( $handle, CURLINFO_RESPONSE_CODE ),
				'content_type' => curl_getinfo( $handle, CURLINFO_CONTENT_TYPE ), 'body' => $received );
		} finally {
			curl_close( $handle );
		}
	}
}
