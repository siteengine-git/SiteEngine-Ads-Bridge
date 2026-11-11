<?php
/** Synthetic FE customer intelligence only. No attribution, provider I/O or hooks. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

require_once __DIR__ . '/Foundation.php';

interface ProjectionContacts {
	public function locked( callable $run ): array;
	public function candidates( string $identity, string $email, string $job_id ): array;
	public function create( string $email, array $fields ): int;
	public function fields( int $id ): array;
	public function updateFields( int $id, array $fields ): void;
}

final class CRMProjection {
	public const IDENTITY = 'se_fe_identity';
	public const CUSTOMER = 'se_fe_customer_json';
	public const JOBS = 'se_fe_jobs_json';
	public static function definitions(): array {
		return array(
			array( 'slug' => self::IDENTITY, 'label' => 'FieldEdge provider identity', 'type' => 'text' ),
			array( 'slug' => self::CUSTOMER, 'label' => 'FieldEdge customer summary', 'type' => 'textarea' ),
			array( 'slug' => self::JOBS, 'label' => 'FieldEdge job summaries', 'type' => 'textarea' ),
		);
	}
	public static function json( array $value ): string {
		return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
	}
	private static function text( $value, int $max = 160 ): string {
		if ( ! is_string( $value ) || strlen( $value ) > $max || preg_match( '/[\x00-\x1f\x7f]/', $value ) ) { throw new \InvalidArgumentException( 'invalid_summary' ); }
		return $value;
	}
	/** This milestone deliberately refuses production input; no provider records reused. */
	private static function normalize( array $input ): array {
		if ( ( $input['synthetic'] ?? null ) !== true || ( $input['provider'] ?? null ) !== 'fieldedge' ||
			! str_starts_with( $input['tenant_id'] ?? '', 'synthetic-' ) ) { throw new \InvalidArgumentException( 'synthetic_projection_input_required' ); }
		$c = $input['customer']; $j = $input['job'];
		$email = $c['email'] ?? '';
		if ( ! is_string( $email ) || ( '' !== $email && ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) || ! str_ends_with( strtolower( $email ), '.invalid' ) ) ) ) { throw new \InvalidArgumentException( 'synthetic_projection_input_required' ); }
		$identity = array( 'provider' => 'fieldedge', 'tenant_id' => Foundation::id( $input['tenant_id'] ), 'customer_id' => Foundation::id( $c['id'] ) );
		$customer = array( 'customer_id' => $identity['customer_id'], 'name' => self::text( $c['name'] ), 'phone' => self::text( $c['phone'], 40 ),
			'source_updated_at' => Foundation::timestamp( $c['source_updated_at'] ) );
		if ( ( $j['customer_id'] ?? null ) !== $identity['customer_id'] ) { throw new \InvalidArgumentException( 'job_customer_mismatch' ); }
		$job = array( 'job_id' => Foundation::id( $j['id'] ), 'customer_id' => $identity['customer_id'], 'status' => self::text( $j['status'], 40 ),
			'service_category' => self::text( $j['service_category'], 80 ), 'scheduled_at' => null, 'completed_at' => null,
			'source_updated_at' => Foundation::timestamp( $j['source_updated_at'] ) );
		foreach ( array( 'scheduled_at', 'completed_at' ) as $key ) { if ( isset( $j[ $key ] ) ) { $job[ $key ] = Foundation::timestamp( $j[ $key ] ); } }
		$approved = $input['approved_contact_id'] ?? null;
		if ( null !== $approved && ( ! is_int( $approved ) || $approved < 1 ) ) { throw new \InvalidArgumentException( 'invalid_approved_contact' ); }
		return array( self::json( $identity ), strtolower( $email ), $customer, $job, $approved );
	}
	public static function run( array $input, ProjectionContacts $contacts ): array {
		list( $identity, $email, $customer, $job, $approved ) = self::normalize( $input );
		$snapshot = Foundation::digest( array( 'identity' => $identity, 'email' => $email, 'customer' => $customer, 'job' => $job ) );
		$operation = 'fe-projection-' . Foundation::digest( array( 'identity' => $identity, 'job_id' => $job['job_id'], 'customer_revision' => $customer['source_updated_at'], 'job_revision' => $job['source_updated_at'] ) );
		$result = $contacts->locked( static function () use ( $contacts, $identity, $email, $customer, $job, $approved ) {
			$matches = $contacts->candidates( $identity, $email, $job['job_id'] );
			$ids = array_values( array_unique( array_merge( $matches['identity'], $matches['email'], $matches['job'] ) ) );
			if ( count( $matches['identity'] ) > 1 || count( $matches['email'] ) > 1 || count( $ids ) > 1 ) { return array( 'status' => 'held', 'reason' => 'ambiguous_identity' ); }
			$id = $ids[0] ?? null;
			if ( null === $id && '' === $email ) { return array( 'status' => 'held', 'reason' => 'missing_email_contact_creation_unsupported' ); }
			$old = null === $id ? array() : $contacts->fields( $id );
			if ( isset( $old[ self::IDENTITY ] ) && $old[ self::IDENTITY ] !== '' && $old[ self::IDENTITY ] !== $identity ) { return array( 'status' => 'held', 'reason' => 'provider_identity_conflict' ); }
			if ( null !== $id && empty( $old[ self::IDENTITY ] ) && $approved !== $id ) { return array( 'status' => 'held', 'reason' => 'email_match_requires_explicit_contact_binding' ); }
			if ( null !== $approved && $approved !== $id ) { return array( 'status' => 'held', 'reason' => 'approved_contact_mismatch' ); }
			try {
				$jobs = empty( $old[ self::JOBS ] ) ? array() : json_decode( $old[ self::JOBS ], true, 12, JSON_THROW_ON_ERROR );
				$prior_customer = empty( $old[ self::CUSTOMER ] ) ? null : json_decode( $old[ self::CUSTOMER ], true, 8, JSON_THROW_ON_ERROR );
				if ( ! is_array( $jobs ) || count( $jobs ) > 20 || ( $prior_customer !== null && ! is_array( $prior_customer ) ) ) { throw new \RuntimeException(); }
				if ( empty( $old[ self::IDENTITY ] ) && ( $jobs || $prior_customer ) ) { throw new \RuntimeException(); }
				foreach ( $jobs as $key => $value ) {
					if ( ! is_array( $value ) || ( $value['job_id'] ?? null ) !== $key || ( $value['customer_id'] ?? null ) !== $customer['customer_id'] ) { throw new \RuntimeException(); }
				}
				if ( $prior_customer && ( $prior_customer['customer_id'] ?? null ) !== $customer['customer_id'] ) { throw new \RuntimeException(); }
				foreach ( array( array( $prior_customer, $customer ), array( $jobs[ $job['job_id'] ] ?? null, $job ) ) as $pair ) {
					if ( null === $pair[0] ) { continue; }
					$before = new \DateTimeImmutable( Foundation::timestamp( $pair[0]['source_updated_at'] ?? null ) );
					$after = new \DateTimeImmutable( $pair[1]['source_updated_at'] );
					if ( $after < $before || ( $after == $before && $pair[0] !== $pair[1] ) ) { return array( 'status' => 'held', 'reason' => 'stale_or_conflicting_source_snapshot' ); }
				}
			} catch ( \Throwable $error ) { return array( 'status' => 'held', 'reason' => 'existing_summary_invalid' ); }
			$jobs[ $job['job_id'] ] = $job; ksort( $jobs, SORT_STRING );
			if ( count( $jobs ) > 20 ) { return array( 'status' => 'held', 'reason' => 'bounded_job_summary_capacity' ); }
			$fields = array( self::IDENTITY => $identity, self::CUSTOMER => self::json( $customer ), self::JOBS => self::json( $jobs ) );
			$same = null !== $id;
			foreach ( $fields as $key => $value ) { $same = $same && ( $old[ $key ] ?? null ) === $value; }
			if ( $same ) { return array( 'status' => 'replayed', 'contact_id' => $id, 'job_count' => count( $jobs ) ); }
			$created = null === $id;
			try {
				if ( $created ) { $id = $contacts->create( $email, $fields ); } else { $contacts->updateFields( $id, $fields ); }
				$readback = $contacts->fields( $id );
				foreach ( $fields as $key => $value ) { if ( ( $readback[ $key ] ?? null ) !== $value ) { throw new \RuntimeException(); } }
			} catch ( \Throwable $error ) { return array( 'status' => 'held', 'reason' => 'write_result_uncertain_inspect_before_retry' ); }
			return array( 'status' => $created ? 'created' : 'updated', 'contact_id' => $id, 'job_count' => count( $jobs ) );
		} );
		return $result + array( 'operation_id' => $operation, 'snapshot_sha256' => $snapshot );
	}
}
