<?php
/** Two selected entries only. Candidate contact/location matches never prove origin. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;

require_once __DIR__ . '/OAuthClient.php';

final class SelectedCaseDiscovery {
	public static function run( callable $get, SelectedCaseJournal $journal, array $entries ): array {
		if ( count( $entries ) !== 2 || ! is_int( $entries[0]['form_id'] ?? null ) || $entries[0]['form_id'] < 1 ||
			$entries[0]['form_id'] !== ( $entries[1]['form_id'] ?? null ) ||
			! is_int( $entries[0]['entry_id'] ?? null ) || ! is_int( $entries[1]['entry_id'] ?? null ) ||
			$entries[0]['entry_id'] < 1 || $entries[1]['entry_id'] < 1 || $entries[0]['entry_id'] === $entries[1]['entry_id'] ) {
			throw new \RuntimeException( 'selected_entries_mismatch' );
		}
		$findings = array();
		foreach ( $entries as $entry ) {
			$result = array( 'form_id' => $entry['form_id'], 'entry_id' => $entry['entry_id'], 'disposition' => 'Needs Review',
				'reason_code' => 'bounded_search_no_defensible_pair', 'provider_relationships' => array(), 'customer_candidates' => array(), 'work_order_candidates' => array(),
				'AB_inquiry_present' => ! empty( $entry['AB_inquiry_rows'] ), 'advertising_eligibility' => 'not_granted_by_association' );
			if ( $journal->remaining() === 0 ) { $result['reason_code'] = 'six_get_budget_consumed'; $findings[] = $result; break; }
			try {
				$r = $entry['retained_response'];
				$selector = ! empty( $r['email'] ) ? array( 'email' => trim( $r['email'] ) ) : array( 'phone' => trim( $r['phone'] ?? '' ) );
				$search = $get( 'customer_search', $selector );
				$customers = self::page( $search, 'customers' );
				$matched = array_values( array_filter( $customers, static function ( $c ) use ( $selector ) {
					if ( isset( $selector['email'] ) ) { return is_string( $c['primaryEmail'] ?? null ) && strcasecmp( trim( $c['primaryEmail'] ), $selector['email'] ) === 0; }
					return self::phone( $c['primaryPhone'] ?? '' ) === self::phone( $selector['phone'] );
				} ) );
				foreach ( $matched as $c ) { TargetedRead::id( $c['id'] ?? null ); $result['customer_candidates'][] = $c['id']; }
				$result['customer_search_page_incomplete'] = ( $search['total'] ?? count( $customers ) ) > count( $customers );
				$works = array();
				if ( count( $matched ) === 1 && $journal->remaining() > 0 ) {
					$list = $get( 'customer_work_orders', array( 'customerId' => $matched[0]['id'] ) );
					$works = self::page( $list, 'data' );
					$result['work_order_search_page_incomplete'] = ( $list['total'] ?? count( $works ) ) > count( $works );
				} elseif ( $journal->remaining() > 0 && ! empty( $r['street_address'] ) && ! empty( $r['city_1'] ) && ! empty( $r['state_1'] ) && ! empty( $r['zip_code'] ) ) {
					$list = $get( 'address_work_orders', array( 'addressLine1' => $r['street_address'], 'city' => $r['city_1'], 'stateOrRegion' => $r['state_1'], 'postalCode' => $r['zip_code'] ) );
					$works = self::page( $list, 'data' );
					$result['work_order_search_page_incomplete'] = ( $list['total'] ?? count( $works ) ) > count( $works );
				}
				foreach ( $works as $work ) { TargetedRead::id( $work['workOrderId'] ?? null ); $result['work_order_candidates'][] = $work['workOrderId']; }
				$location_matches = array_values( array_filter( $works, static fn( $w ) => self::sameAddress( $w, $r ) ) );
				$chosen = count( $works ) === 1 ? $works[0] : ( count( $location_matches ) === 1 ? $location_matches[0] : null );
				if ( null === $chosen ) {
					$result['reason_code'] = $works ? 'multiple_jobs_no_unique_supported_selector' : 'bounded_search_no_candidate';
				} elseif ( $journal->remaining() === 0 ) {
					$result['reason_code'] = 'candidate_found_detail_budget_exhausted';
				} else {
					$detail = $get( 'work_order', array( 'id' => $chosen['workOrderId'] ) );
					if ( ( $detail['workOrderId'] ?? null ) !== $chosen['workOrderId'] ) { throw new \RuntimeException( 'bounded_targeted_structure_unverified' ); }
					$relations = array();
					foreach ( array( 'workOrderId', 'customerId', 'billingCustomerId', 'invoiceId', 'leadId' ) as $field ) {
						if ( ! empty( $detail[ $field ] ) ) { TargetedRead::id( $detail[ $field ] ); $relations[ $field ] = $detail[ $field ]; }
					}
					$result['provider_relationships'] = $relations;
					$result['work_order_snapshot_sha256'] = hash( 'sha256', json_encode( $detail, JSON_THROW_ON_ERROR ) );
					$result['work_customer_matches_search_candidate'] = in_array( $detail['customerId'] ?? null, $result['customer_candidates'], true );
					$result['work_address_matches_entry'] = self::sameAddress( $detail, $r );
					$result['provider_completion_time'] = $detail['workOrderInformation']['completedDate'] ?? null;
					$result['provider_scheduled_time'] = $detail['workOrderInformation']['scheduledStart'] ?? $detail['scheduledStart'] ?? null;
					$result['original_submission_time_reference'] = $entry['created_at'];
					$result['requested_appointment_present'] = ! empty( $r['future_date'] );
					$service = strtolower( $r['service_type'] ?? '' );
					$result['service_category_text_overlap_only'] = '' !== $service && str_contains( strtolower( (string) ( $detail['workDescription'] ?? '' ) ), $service );
					$original_reference = $r['transaction_id'] ?? '';
					$other_refs = array_column( array_column( $entries, 'retained_response' ), 'transaction_id' );
					$unique = is_string( $original_reference ) && strlen( $original_reference ) >= 8 && count( array_keys( $other_refs, $original_reference, true ) ) === 1;
					$explicit = $unique && is_string( $detail['trackingId'] ?? null ) && $detail['trackingId'] === $original_reference;
					$result['explicit_original_reference_match'] = $explicit;
					$result['explicit_reference_location'] = $explicit ? 'work_order.trackingId' : null;
					if ( ! $explicit && ! empty( $relations['leadId'] ) && $journal->remaining() > 0 ) {
						$lead = $get( 'lead', array( 'id' => $relations['leadId'] ) );
						$lead = $lead['data'] ?? array();
						$result['lead_reference_verified'] = ( $lead['leadId'] ?? null ) === $relations['leadId'];
						$result['lead_customer_matches_work'] = ( $lead['customer']['customerId'] ?? null ) === ( $detail['customerId'] ?? null );
						$explicit = $unique && $result['lead_reference_verified'] && $result['lead_customer_matches_work'] && ( $lead['trackingId'] ?? null ) === $original_reference;
						$result['explicit_original_reference_match'] = $explicit;
						$result['explicit_reference_location'] = $explicit ? 'explicit_work_lead.data.trackingId' : null;
					}
					if ( $explicit && ( $result['work_customer_matches_search_candidate'] || $result['work_address_matches_entry'] ) ) {
						$result['disposition'] = 'Attributed';
						$result['reason_code'] = 'explicit_original_transaction_reference_in_provider_record';
					} else { $result['reason_code'] = 'candidate_job_without_explicit_original_inquiry_link'; }
				}
			} catch ( \Throwable $error ) {
				$result['reason_code'] = 'selected_request_or_structure_unconfirmed';
				$result['request_failure_stop'] = true;
				$findings[] = $result;
				break;
			}
			$findings[] = $result;
			if ( $result['disposition'] === 'Attributed' ) { break; }
		}
		return $findings;
	}
	private static function page( array $data, string $member ): array {
		if ( ! is_array( $data[ $member ] ?? null ) || ! array_is_list( $data[ $member ] ) || count( $data[ $member ] ) > 3 ) {
			throw new \RuntimeException( 'bounded_targeted_structure_unverified' );
		}
		foreach ( $data[ $member ] as $record ) { if ( ! is_array( $record ) ) { throw new \RuntimeException( 'bounded_targeted_structure_unverified' ); } }
		return $data[ $member ];
	}
	private static function phone( string $value ): string {
		$value = preg_replace( '/[^0-9]/', '', $value );
		return strlen( $value ) === 11 && $value[0] === '1' ? substr( $value, 1 ) : $value;
	}
	private static function sameAddress( array $work, array $r ): bool {
		$address = $work['serviceAddress'] ?? $work['address'] ?? array();
		$street = $address['addressLine1'] ?? $address['address1'] ?? '';
		$postal = $address['postalCode'] ?? $address['zip'] ?? '';
		$normalize = static fn( $v ) => strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) $v ) );
		return ! empty( $r['street_address'] ) && ! empty( $r['zip_code'] ) && $normalize( $street ) === $normalize( $r['street_address'] ) && $normalize( $postal ) === $normalize( $r['zip_code'] );
	}
}
