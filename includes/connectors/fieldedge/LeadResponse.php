<?php
/** Bounded, secret-safe create response evidence; no contact/attribution inference. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;
require_once __DIR__ . '/Foundation.php';
final class LeadResponse {
	public const URL = 'https://api.fieldedge.com/open-api/v1/leads';
	public static function allowed( string $method, string $url ): bool { return $method === 'POST' && $url === self::URL; }
	public static function classify( ?array $response ): array {
		$http = $response['status'] ?? null;
		if ( ! is_int( $http ) || $http < 100 || $http > 599 ) { $http = null; }
		$body = $response['body'] ?? null;
		$e = array( 'classification' => 'uncertain', 'reason_code' => 'provider_result_unconfirmed', 'http_status' => $http,
			'response_sha256' => is_string( $body ) ? hash( 'sha256', $body ) : null,
			'lead_id' => null, 'provider_status' => null, 'received_at' => null );
		if ( in_array( $http, array( 400, 422 ), true ) ) {
			$e['classification'] = 'validation_failed'; $e['reason_code'] = 'provider_validation_rejected'; return $e;
		}
		if ( 409 === $http ) { $e['reason_code'] = 'provider_duplicate_unresolved'; return $e; }
		if ( 201 !== $http ) { return $e; }
		try {
			if ( ! is_string( $body ) || strlen( $body ) > 65536 || ! is_string( $response['content_type'] ?? null ) ||
				! preg_match( '/\Aapplication\/json(?:\s*;|\z)/i', $response['content_type'] ) ) { throw new \RuntimeException(); }
			$object = json_decode( $body, false, 16, JSON_THROW_ON_ERROR );
			if ( ! $object instanceof \stdClass || ! ( $object->data ?? null ) instanceof \stdClass ) { throw new \RuntimeException(); }
			$data = $object->data;
			if ( ! is_string( $data->leadId ?? null ) || ! preg_match( '/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/Di', $data->leadId ) ||
				! is_string( $data->status ?? null ) || ! preg_match( '/\A[a-zA-Z][a-zA-Z0-9 _-]{0,79}\z/D', $data->status ) ) { throw new \RuntimeException(); }
			$at = Foundation::timestamp( $data->receivedAt ?? null );
			$e['classification'] = 'success'; $e['reason_code'] = 'provider_lead_created';
			$e['lead_id'] = strtolower( $data->leadId ); $e['provider_status'] = $data->status; $e['received_at'] = $at;
		} catch ( \Throwable $error ) { $e['reason_code'] = 'provider_success_shape_unconfirmed'; }
		return $e;
	}
}
