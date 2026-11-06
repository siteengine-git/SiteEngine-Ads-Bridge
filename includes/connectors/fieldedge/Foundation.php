<?php
/** Dormant, pure FieldEdge boundary. No WordPress hooks, I/O or core writes. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

final class Foundation {

	/** Canonical digest for locally reviewed snapshots; never a provider revision. */
	public static function digest( array $value ): string {
		$sort = static function ( $item ) use ( &$sort ) {
			if ( ! is_array( $item ) ) {
				return $item;
			}
			if ( ! array_is_list( $item ) ) {
				ksort( $item, SORT_STRING );
			}
			return array_map( $sort, $item );
		};
		return hash( 'sha256', json_encode( $sort( $value ), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION ) );
	}

	public static function id( $value ): string {
		if ( ! is_string( $value ) || ! preg_match( '/\A[a-zA-Z0-9][a-zA-Z0-9_.:-]{0,127}\z/D', $value ) ) {
			throw new \InvalidArgumentException( 'invalid_identity' );
		}
		return $value;
	}

	private static function uuid( $value ): string {
		if ( ! is_string( $value ) || ! preg_match( '/\A[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\z/Di', $value ) ) {
			throw new \InvalidArgumentException( 'invalid_provider_uuid' );
		}
		return strtolower( $value );
	}

	/** Preserve original offset/fraction; reject rollover dates and missing zones. */
	public static function timestamp( $value ): string {
		if ( ! is_string( $value ) || ! preg_match( '/\A\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d{1,7})?(?:Z|[+-](?:0\d|1[0-3]):[0-5]\d)\z/D', $value ) ) {
			throw new \InvalidArgumentException( 'invalid_timestamp' );
		}
		// PHP supports six fractional places; retain the seventh in returned evidence.
		$parse = preg_replace( '/(\.\d{6})\d/', '$1', $value );
		try {
			new \DateTimeImmutable( $parse );
			$errors = \DateTimeImmutable::getLastErrors();
			if ( $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) {
				throw new \Exception();
			}
		} catch ( \Exception $error ) {
			throw new \InvalidArgumentException( 'invalid_timestamp' );
		}
		return $value;
	}

	/** Exact proposed two-decimal value convention, not an inferred API currency. */
	public static function amount( $value ): string {
		if ( ! is_string( $value ) || ! preg_match( '/\A(?:0|[1-9]\d{0,11})\.\d{2}\z/D', $value ) ) {
			throw new \InvalidArgumentException( 'invalid_approved_amount' );
		}
		return $value;
	}

	/** Verify exact body first. Transport must separately enforce HTTPS/body limits. */
	public static function notification( string $body, string $signature, string $secret, string $tenant ): array {
		if ( '' === $secret || strlen( $body ) > 262144 || ! preg_match( '/\Asha256=[0-9a-f]{64}\z/D', $signature ) ||
			! hash_equals( 'sha256=' . hash_hmac( 'sha256', $body, $secret ), $signature ) ) {
			throw new \InvalidArgumentException( 'invalid_notification_signature_or_size' );
		}
		try {
			$event = json_decode( $body, true, 16, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $error ) {
			throw new \InvalidArgumentException( 'invalid_notification_json' );
		}
		if ( ! is_array( $event ) || self::uuid( $event['companyId'] ?? null ) !== self::uuid( $tenant ) ||
			'application/json' !== ( $event['datacontenttype'] ?? null ) ) {
			throw new \InvalidArgumentException( 'notification_tenant_or_content_mismatch' );
		}
		$families = array(
			'work-orders' => array( 'workOrderId', 'work-order', array( 'created', 'scheduled', 'unscheduled', 'traveling', 'complete', 'canceled', 'backorder', 'finalized' ) ),
			'invoices' => array( 'invoiceId', 'invoice', array( 'created', 'updated' ) ),
			'customers' => array( 'customerId', 'customer', array( 'created', 'updated' ) ),
		);
		// Lead/job/quote events need separately verified entity-link behavior first.
		$source = $event['source'] ?? null;
		if ( ! is_string( $source ) || ! isset( $families[ $source ] ) ) {
			throw new \InvalidArgumentException( 'unsupported_notification' );
		}
		list( $id_key, $prefix, $actions ) = $families[ $source ];
		if ( ! in_array( $event['type'] ?? null, array_map( static fn( $action ) => $prefix . '.' . $action, $actions ), true ) ) {
			throw new \InvalidArgumentException( 'unsupported_notification' );
		}
		return array(
			'provider' => 'fieldedge',
			'tenant_id' => self::uuid( $tenant ),
			'event_id' => self::uuid( $event['id'] ?? null ),
			'event_type' => $event['type'],
			'occurred_at' => self::timestamp( $event['time'] ?? null ),
			'entity_id' => self::uuid( $event['data'][ $id_key ] ?? null ),
			'entity_type' => $prefix,
			'body_sha256' => hash( 'sha256', $body ),
			'requires_fetch' => true,
		);
	}

	/** Explicit allowlist: provider contact/financial payloads do not pass through. */
	public static function lead( array $response ): array {
		$data = $response['data'] ?? array();
		return array(
			'lead_id' => self::uuid( $data['leadId'] ?? null ),
			'customer_id' => self::uuid( $data['customer']['customerId'] ?? null ),
			'snapshot_sha256' => self::digest( $response ),
		);
	}

	/** This local decision is a proposal. It grants no eligibility or write authority. */
	public static function outcome( string $tenant, array $work, array $invoice, array $links, array $approval ): array {
		$tenant = self::uuid( $tenant );
		$work_id = self::uuid( $work['workOrderId'] ?? null );
		$invoice_id = self::uuid( $invoice['invoiceId'] ?? null );
		$customer_id = self::uuid( $work['customerId'] ?? null );
		$result = array(
			'contract' => 'fieldedge-foundation-draft-1',
			'provider' => 'fieldedge',
			'tenant_id' => $tenant,
			'work_order_id' => $work_id,
			'invoice_id' => $invoice_id,
			'customer_id' => $customer_id,
			'economic_key' => self::digest( array( 'fieldedge', $tenant, 'converted', $work_id ) ),
			'snapshot_sha256' => self::digest( array( $work, $invoice ) ),
			'status' => 'held',
			'reason_code' => 'association_missing',
			'export_eligible' => false,
		);
		$matches = array();
		foreach ( $links as $link ) {
			if ( ! is_array( $link ) ) {
				throw new \InvalidArgumentException( 'invalid_association' );
			}
			if ( ( $link['tenant_id'] ?? null ) === $tenant && ( $link['work_order_id'] ?? null ) === $work_id ) {
				$matches[] = $link;
			}
		}
		if ( 1 !== count( $matches ) ) {
			$result['reason_code'] = count( $matches ) ? 'association_ambiguous' : 'association_missing';
			return $result;
		}
		$link = $matches[0];
		if ( 'reviewed_explicit' !== ( $link['basis'] ?? null ) ||
			$customer_id !== ( $link['customer_id'] ?? null ) ||
			$invoice_id !== ( $link['invoice_id'] ?? null ) ||
			$invoice_id !== ( $work['invoiceId'] ?? null ) ) {
			$result['reason_code'] = 'association_unproven';
			return $result;
		}
		$result['inquiry_id'] = self::id( $link['inquiry_id'] ?? null );
		$result['lead_id'] = self::uuid( $link['lead_id'] ?? null );
		$result['association_evidence_id'] = self::id( $link['evidence_id'] ?? null );
		if ( true !== ( $work['completed'] ?? null ) || 'WorkPerformed' !== ( $work['resolution'] ?? null ) ) {
			$result['reason_code'] = 'work_not_proven_completed';
			return $result;
		}
		$result['completed_at'] = self::timestamp( $work['workOrderInformation']['completedDate'] ?? null );
		$result['invoice_at'] = self::timestamp( $invoice['invoiceDate'] ?? null );
		if ( true !== ( $approval['approved'] ?? null ) || ( $approval['tenant_id'] ?? null ) !== $tenant ||
			( $approval['invoice_id'] ?? null ) !== $invoice_id ||
			( $approval['invoice_sha256'] ?? null ) !== self::digest( $invoice ) ) {
			$result['reason_code'] = 'value_approval_missing_or_stale';
			return $result;
		}
		$result['amount'] = self::amount( $approval['amount'] ?? null );
		if ( '0.00' === $result['amount'] || ! is_string( $approval['currency'] ?? null ) ||
			! preg_match( '/\A[A-Z]{3}\z/D', $approval['currency'] ) ) {
			$result['reason_code'] = 'value_or_currency_requires_review';
			return $result;
		}
		$result['currency'] = $approval['currency'];
		$result['value_approval_id'] = self::id( $approval['approval_id'] ?? null );
		$result['value_policy_id'] = self::id( $approval['policy_id'] ?? null );
		$result['status'] = 'candidate';
		$result['reason_code'] = 'core_review_required';
		return $result;
	}

	/** Decide a revision without creating another conversion or replacing history. */
	public static function revision( array $previous, array $next ): string {
		if ( ( $previous['economic_key'] ?? null ) !== ( $next['economic_key'] ?? null ) ||
			empty( $next['economic_key'] ) ) {
			return 'different_economic_event';
		}
		return self::digest( $previous ) === self::digest( $next ) ? 'replay' : 'revision_review';
	}
}
