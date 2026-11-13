<?php
/**
 * Read-only bounded operational snapshots for one explicitly pinned FieldEdge
 * customer. Raw provider bodies remain inside this process; output is an
 * allowlisted customer/job summary only. No Ads outcome is inferred.
 */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

require_once __DIR__ . '/OAuthClient.php';

final class LiveSnapshotDiscovery {
	private static function timestamp( $value ): ?string {
		if ( ! is_string( $value ) || strlen( $value ) > 40 ||
			! preg_match( '/\A\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d{1,7})?(?:Z|[+-]\d\d:\d\d)\z/D', $value ) ) {
			return null;
		}
		try {
			new \DateTimeImmutable( $value );
		} catch ( \Throwable $error ) {
			return null;
		}
		return $value;
	}

	public static function run( callable $get, SelectedCaseJournal $journal, array $case ): array {
		if ( ( $case['schema'] ?? null ) !== 1 ||
			! is_int( $case['contact_id'] ?? null ) || $case['contact_id'] < 1 ||
			! is_int( $case['form_id'] ?? null ) || $case['form_id'] < 1 ||
			! is_int( $case['entry_id'] ?? null ) || $case['entry_id'] < 1 ||
			! is_string( $case['email_sha256'] ?? null ) ||
			! preg_match( '/\A[a-f0-9]{64}\z/D', $case['email_sha256'] ) ||
			! is_string( $case['tenant_id'] ?? null ) ) {
			throw new \RuntimeException( 'live_case_invalid' );
		}
		TargetedRead::id( $case['customer_id'] ?? null );
		TargetedRead::id( $case['tenant_id'] );
		$list = $get( 'customer_work_orders', array( 'customerId' => $case['customer_id'] ) );
		$rows = $list['data'] ?? null;
		if ( ! is_array( $rows ) || ! array_is_list( $rows ) ||
			count( $rows ) < 1 || count( $rows ) > 3 ||
			! is_int( $list['total'] ?? null ) || $list['total'] !== count( $rows ) ) {
			throw new \RuntimeException( 'live_work_orders_not_complete_within_budget' );
		}
		$ids = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) { throw new \RuntimeException( 'live_work_order_id_invalid' ); }
			TargetedRead::id( $row['workOrderId'] ?? null );
			$ids[] = strtolower( $row['workOrderId'] );
		}
		if ( count( array_unique( $ids ) ) !== count( $ids ) ) {
			throw new \RuntimeException( 'live_work_order_id_duplicate' );
		}
		sort( $ids, SORT_STRING );
		$jobs = array();
		foreach ( $ids as $id ) {
			if ( $journal->remaining() < 1 ) {
				throw new \RuntimeException( 'live_work_order_budget_exhausted' );
			}
			$detail = $get( 'work_order', array( 'id' => $id ) );
			if ( ! is_array( $detail ) || strtolower( (string) ( $detail['workOrderId'] ?? '' ) ) !== $id ||
				strtolower( (string) ( $detail['customerId'] ?? '' ) ) !== strtolower( $case['customer_id'] ) ||
				! is_bool( $detail['completed'] ?? null ) ) {
				throw new \RuntimeException( 'live_work_order_link_invalid' );
			}
			$invoice_id = $detail['invoiceId'] ?? null;
			if ( null !== $invoice_id && '' !== $invoice_id ) {
				TargetedRead::id( $invoice_id );
				$invoice_id = strtolower( $invoice_id );
			} else {
				$invoice_id = null;
			}
			$jobs[] = array(
				'work_order_id' => $id,
				'customer_id' => strtolower( $case['customer_id'] ),
				'completed' => $detail['completed'],
				'completed_at' => self::timestamp( $detail['workOrderInformation']['completedDate'] ?? null ),
				'invoice_id' => $invoice_id,
				'source_sha256' => hash( 'sha256', json_encode( $detail, JSON_THROW_ON_ERROR ) ),
			);
		}
		return array(
			'schema' => 1, 'provider' => 'fieldedge',
			'tenant_id' => strtolower( $case['tenant_id'] ),
			'customer_id' => strtolower( $case['customer_id'] ),
			'contact_id' => $case['contact_id'],
			'form_id' => $case['form_id'],
			'entry_id' => $case['entry_id'],
			'email_sha256' => $case['email_sha256'],
			'jobs' => $jobs,
		);
	}
}
