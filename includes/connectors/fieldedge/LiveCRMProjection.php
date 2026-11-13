<?php
/**
 * FieldEdge verified-read CRM projection. This does not perform attribution,
 * change lifecycle/tags, or authorize advertising exports.
 *
 * @package SiteEngineFieldEdge
 */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

final class LiveCRMProjection {
	public const IDENTITY = 'se_fe_identity';
	public const CUSTOMER = 'se_fe_customer_json';
	public const JOBS = 'se_fe_jobs_json';

	private static function validId( $value ): bool {
		return is_string( $value ) && (bool) preg_match( '/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/Di', $value );
	}

	private static function validHash( $value ): bool {
		return is_string( $value ) && (bool) preg_match( '/\A[a-f0-9]{64}\z/D', $value );
	}

	private static function acceptedKeys( array $object, array $permitted ): bool {
		return ! array_diff( array_keys( $object ), $permitted );
	}

	private static function textDate( $value ): ?string {
		if ( null === $value ) {
			return null;
		}
		if ( ! is_string( $value ) || strlen( $value ) > 40 ||
			! preg_match( '/\A\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d{1,7})?(?:Z|[+-]\d\d:\d\d)\z/D', $value ) ) {
			throw new \InvalidArgumentException( 'invalid_verified_date' );
		}
		try {
			new \DateTimeImmutable( $value );
		} catch ( \Throwable $error ) {
			throw new \InvalidArgumentException( 'invalid_verified_date' );
		}
		return $value;
	}

	public static function plan( array $input, array $prior ): array {
		if ( ! self::acceptedKeys( $input, array( 'schema', 'provider', 'tenant_id', 'customer_id', 'contact_id', 'form_id', 'entry_id', 'email_sha256', 'jobs' ) ) ||
			1 !== ( $input['schema'] ?? null ) || 'fieldedge' !== ( $input['provider'] ?? null ) ||
			! self::validId( $input['tenant_id'] ?? null ) || ! self::validId( $input['customer_id'] ?? null ) ||
			! is_int( $input['contact_id'] ?? null ) || $input['contact_id'] < 1 ||
			! is_int( $input['form_id'] ?? null ) || $input['form_id'] < 1 ||
			! is_int( $input['entry_id'] ?? null ) || $input['entry_id'] < 1 ||
			! self::validHash( $input['email_sha256'] ?? null ) ||
			! is_array( $input['jobs'] ?? null ) || ! array_is_list( $input['jobs'] ) ||
			count( $input['jobs'] ) < 1 || count( $input['jobs'] ) > 20 ) {
			return array( 'status' => 'held', 'reason_code' => 'verified_projection_payload_invalid' );
		}
		$identity = json_encode( array(
			'provider' => 'fieldedge',
			'tenant_id' => strtolower( $input['tenant_id'] ),
			'customer_id' => strtolower( $input['customer_id'] ),
		), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
		if ( ! empty( $prior[ self::IDENTITY ] ) && $prior[ self::IDENTITY ] !== $identity ) {
			return array( 'status' => 'held', 'reason_code' => 'provider_identity_conflict' );
		}
		if ( empty( $prior[ self::IDENTITY ] ) &&
			( ! empty( $prior[ self::CUSTOMER ] ) || ! empty( $prior[ self::JOBS ] ) ) ) {
			return array( 'status' => 'held', 'reason_code' => 'existing_summary_without_provider_identity' );
		}
		$old_jobs = array();
		if ( ! empty( $prior[ self::JOBS ] ) ) {
			try {
				$old_jobs = json_decode( (string) $prior[ self::JOBS ], true, 16, JSON_THROW_ON_ERROR );
				if ( ! is_array( $old_jobs ) || array_is_list( $old_jobs ) || count( $old_jobs ) > 20 ) {
					throw new \RuntimeException();
				}
			} catch ( \Throwable $error ) {
				return array( 'status' => 'held', 'reason_code' => 'existing_jobs_invalid' );
			}
		}
		if ( ! empty( $prior[ self::CUSTOMER ] ) ) {
			try {
				$old_customer = json_decode( (string) $prior[ self::CUSTOMER ], true, 8, JSON_THROW_ON_ERROR );
				if ( ! is_array( $old_customer ) || ( $old_customer['customer_id'] ?? null ) !== strtolower( $input['customer_id'] ) ) {
					throw new \RuntimeException();
				}
			} catch ( \Throwable $error ) {
				return array( 'status' => 'held', 'reason_code' => 'existing_customer_invalid' );
			}
		}
		$jobs = $old_jobs;
		$seen = array();
		foreach ( $input['jobs'] as $record ) {
			if ( ! is_array( $record ) ||
				! self::acceptedKeys( $record, array( 'work_order_id', 'customer_id', 'completed', 'completed_at', 'invoice_id', 'source_sha256' ) ) ||
				! self::validId( $record['work_order_id'] ?? null ) ||
				! self::validId( $record['customer_id'] ?? null ) ||
				strtolower( $record['customer_id'] ) !== strtolower( $input['customer_id'] ) ||
				! is_bool( $record['completed'] ?? null ) ||
				! self::validHash( $record['source_sha256'] ?? null ) ||
				( null !== ( $record['invoice_id'] ?? null ) && ! self::validId( $record['invoice_id'] ) ) ) {
				return array( 'status' => 'held', 'reason_code' => 'provider_work_order_invalid' );
			}
			$id = strtolower( $record['work_order_id'] );
			if ( isset( $seen[ $id ] ) ) {
				return array( 'status' => 'held', 'reason_code' => 'duplicate_work_order' );
			}
			$seen[ $id ] = true;
			try {
				$completed_at = self::textDate( $record['completed_at'] ?? null );
			} catch ( \Throwable $error ) {
				return array( 'status' => 'held', 'reason_code' => 'invalid_verified_date' );
			}
			$new = array(
				'job_id' => $id,
				'customer_id' => strtolower( $input['customer_id'] ),
				'status' => $record['completed'] ? 'completed' : 'open',
				'completed_at' => $completed_at,
				'invoice_id' => isset( $record['invoice_id'] ) ? strtolower( $record['invoice_id'] ) : null,
				'source_sha256' => $record['source_sha256'],
			);
			$old = $jobs[ $id ] ?? null;
			if ( is_array( $old ) ) {
				if ( ( $old['customer_id'] ?? null ) !== $new['customer_id'] ||
					( $old['status'] ?? null ) === 'completed' && $new['status'] !== 'completed' ||
					! empty( $old['invoice_id'] ) && ( $old['invoice_id'] !== $new['invoice_id'] ) ||
					! empty( $old['completed_at'] ) && $old['completed_at'] !== $new['completed_at'] ) {
					return array( 'status' => 'held', 'reason_code' => 'work_order_revision_requires_review' );
				}
				// Harmless source re-serialization does not change an accepted
				// economic fact or create an advertising conversion.
			} elseif ( null !== $old ) {
				return array( 'status' => 'held', 'reason_code' => 'existing_jobs_invalid' );
			}
			$jobs[ $id ] = $new;
		}
		if ( count( $jobs ) > 20 ) {
			return array( 'status' => 'held', 'reason_code' => 'bounded_job_capacity' );
		}
		ksort( $jobs, SORT_STRING );
		$customer = array(
			'customer_id' => strtolower( $input['customer_id'] ),
			'source' => 'fieldedge_authenticated_read',
		);
		$fields = array(
			self::IDENTITY => $identity,
			self::CUSTOMER => json_encode( $customer, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ),
			self::JOBS => json_encode( $jobs, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ),
		);
		$same = true;
		foreach ( $fields as $key => $value ) {
			$same = $same && ( $prior[ $key ] ?? null ) === $value;
		}
		return array(
			'status' => $same ? 'replayed' : 'ready',
			'reason_code' => $same ? 'projection_unchanged' : 'projection_write_required',
			'fields' => $fields,
			'job_count' => count( $jobs ),
			'projection_sha256' => hash( 'sha256', $identity . '\n' . $fields[ self::JOBS ] ),
		);
	}
}
