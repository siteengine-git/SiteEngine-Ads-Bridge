<?php
/**
 * Inquiry-aware attribution persistence and CRM Ops reconciliation handshake.
 *
 * Ads Bridge 0.6.0 keeps historical advertising attribution per inquiry while
 * FluentCRM contact fields remain the editable projection of the current inquiry.
 *
 * @package SiteEngineAdsBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteEngine_Ads_Bridge_Inquiries {

	const CONTRACT_VERSION = '1';
	const SCHEMA_VERSION   = '2';

	/** @var bool Prevent current-contact projection hooks from corrupting archived state during activation. */
	private static $reconciling = false;

	/** @var array Contact IDs locked by this request; transitions are not reentrant. */
	private static $held_locks = array();

	/** @var bool Do not reuse a connection after an unconfirmed rollback/release. */
	private static $connection_unusable = false;

	/**
	 * Serialize Ads Bridge transitions by database, table prefix and contact.
	 * CRM Ops must separately enforce its own atomic head compare-and-swap.
	 *
	 * @param string   $phase adoption|prepare|activation.
	 * @param mixed    $payload Contract payload.
	 * @param callable $callback Validated transition.
	 * @return array
	 */
	private static function serialized_transition( $phase, $payload, $callback ) {
		$payload = is_array( $payload ) ? $payload : array();
		if ( self::$connection_unusable ) {
			return self::transition_failure( $phase, $payload, 'retry', 'inquiry_connection_unusable' );
		}
		$validator = array( 'adoption' => 'validate_adoption_payload', 'prepare' => 'validate_prepare_payload', 'activation' => 'validate_activation_payload' );
		$valid = call_user_func( array( __CLASS__, $validator[ $phase ] ), $payload );
		if ( ! $valid['ok'] ) {
			return self::transition_failure( $phase, $payload, 'rejected', $valid['reason'] );
		}
		if ( ! self::table_exists() ) {
			return self::transition_failure( $phase, $payload, 'retry', 'inquiry_table_missing' );
		}
		if ( ! self::schema_ready() ) {
			return self::transition_failure( $phase, $payload, 'retry', 'inquiry_schema_migration_required' );
		}
		$contact_id = (int) $payload['contact_id'];
		$lock = self::acquire_contact_lock( $contact_id );
		if ( ! $lock['ok'] ) {
			return self::transition_failure( $phase, $payload, 'retry', $lock['reason'] );
		}
		try {
			self::assert_contact_lock( $contact_id );
			$result = call_user_func( $callback, $payload );
			if ( ! self::$connection_unusable ) {
				self::assert_contact_lock( $contact_id );
			}
		} catch ( \Throwable $e ) {
			$result = self::transition_failure( $phase, $payload, 'retry', self::safe_reason( $e->getMessage() ) );
		} finally {
			$released = self::release_contact_lock( $contact_id );
		}
		if ( ! $released ) {
			self::discard_connection();
			if ( ! in_array( $result['status'], array( 'retry', 'rejected' ), true ) ) {
				return self::transition_failure( $phase, $payload, 'retry', 'contact_lock_release_failed' );
			}
			$result['lock_reason_code'] = 'contact_lock_release_failed';
		}
		return $result;
	}

	/**
	 * @param string $phase Phase.
	 * @param array  $payload Payload.
	 * @param string $status Status.
	 * @param string $reason Reason.
	 * @return array
	 */
	private static function transition_failure( $phase, $payload, $status, $reason ) {
		if ( 'adoption' === $phase ) {
			return self::adoption_response( $payload, $status, $reason, '' );
		}
		if ( 'prepare' === $phase ) {
			return self::prepare_response( $payload, $status, $reason, '' );
		}
		return self::activation_response( $payload, $status, $reason );
	}

	/**
	 * @param int $contact_id Contact ID.
	 * @return array
	 */
	private static function acquire_contact_lock( $contact_id ) {
		if ( self::$connection_unusable ) {
			return array( 'ok' => false, 'reason' => 'inquiry_connection_unusable' );
		}
		if ( isset( self::$held_locks[ $contact_id ] ) ) {
			return array( 'ok' => false, 'reason' => 'contact_transition_reentrant' );
		}
		global $wpdb;
		$database = defined( 'DB_NAME' ) ? DB_NAME : '';
		$name = 'seab_i_' . substr( hash( 'sha256', $database . '|' . $wpdb->prefix . '|' . (int) $contact_id ), 0, 48 );
		try {
			$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) );
		} catch ( \Throwable $e ) {
			return array( 'ok' => false, 'reason' => 'contact_lock_unavailable' );
		}
		if ( '1' !== (string) $got ) {
			return array( 'ok' => false, 'reason' => '0' === (string) $got ? 'contact_transition_busy' : 'contact_lock_unavailable' );
		}
		self::$held_locks[ $contact_id ] = $name;
		return array( 'ok' => true, 'reason' => '' );
	}

	/**
	 * @param int $contact_id Contact ID.
	 * @return void
	 */
	private static function assert_contact_lock( $contact_id ) {
		global $wpdb;
		if ( self::$connection_unusable || ! isset( self::$held_locks[ $contact_id ] ) || '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK(%s) = CONNECTION_ID()', self::$held_locks[ $contact_id ] ) ) ) {
			throw new \RuntimeException( 'contact_lock_lost' );
		}
	}

	/**
	 * @param int $contact_id Contact ID.
	 * @return bool
	 */
	private static function release_contact_lock( $contact_id ) {
		global $wpdb;
		if ( ! isset( self::$held_locks[ $contact_id ] ) ) {
			return false;
		}
		$name = self::$held_locks[ $contact_id ];
		unset( self::$held_locks[ $contact_id ] );
		if ( self::$connection_unusable ) {
			return false;
		}
		try {
			return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Close the failed session so uncommitted changes/locks cannot leak.
	 *
	 * @return void
	 */
	private static function discard_connection() {
		self::$connection_unusable = true;
		global $wpdb;
		if ( method_exists( $wpdb, 'close' ) ) {
			try {
				$wpdb->close();
			} catch ( \Throwable $e ) {
				// The request remains poisoned even when the driver cannot close.
			}
		}
	}

	/**
	 * @param mixed $value Version to validate.
	 * @param int   $minimum Minimum.
	 * @return bool
	 */
	private static function valid_head_version( $value, $minimum = 0 ) {
		return ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) && (string) (int) $value === $value ) ) && (int) $value >= $minimum && (int) $value < PHP_INT_MAX;
	}

	/**
	 * @param int $contact_id Contact ID.
	 * @return array
	 */
	private static function pending_for_contact( $contact_id ) {
		global $wpdb;
		$table = self::table_name();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE contact_id = %d AND state IN ('prepared','activating') ORDER BY id ASC", (int) $contact_id ), ARRAY_A );
		if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
			throw new \RuntimeException( 'pending_inquiry_read_failed' );
		}
		return $rows;
	}

	/**
	 * Let lifecycle compliance distinguish an intentional inquiry reset from an
	 * ordinary edit to a terminal Won contact. This guard covers this request only.
	 *
	 * @return bool
	 */
	public static function is_reconciling() {
		return self::$reconciling;
	}

	/**
	 * A failed projection may leave the contact partly reset. Until activation
	 * completes, do not mirror that projection into the prior active history.
	 *
	 * @param int $contact_id Contact ID.
	 * @return bool
	 */
	public static function activation_pending_for_contact( $contact_id ) {
		if ( (int) $contact_id < 1 || ! self::table_exists() ) {
			return false;
		}
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE contact_id = %d AND state = 'activating'", (int) $contact_id ) ) > 0;
	}

	/** @return void */
	public static function init() {
		add_action( 'fluent_crm/contact_added_to_tags', array( __CLASS__, 'sync_from_tag_change' ), 90, 2 );
		add_action( 'fluent_crm/contact_custom_data_updated', array( __CLASS__, 'sync_from_custom_change' ), 90, 3 );
	}

	/** @return string */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'se_ads_inquiries';
	}

	/** @return bool */
	public static function table_exists() {
		global $wpdb;
		$table = self::table_name();
		return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Check physical recovery columns as well as the recorded migration version.
	 *
	 * @param bool $require_version Whether to check the persisted version option.
	 * @return bool
	 */
	public static function schema_ready( $require_version = true ) {
		if ( $require_version && self::SCHEMA_VERSION !== (string) get_option( 'seab_inquiry_schema_version', '' ) ) {
			return false;
		}
		global $wpdb;
		$table = self::table_name();
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $columns ) || ! empty( $wpdb->last_error ) ) {
			return false;
		}
		$required = array( 'projection_plan' => 'longtext', 'projection_plan_hash' => 'char(64)', 'projection_verified_at_utc' => 'datetime' );
		foreach ( $columns as $column ) {
			$name = $column['Field'];
			if ( isset( $required[ $name ] ) && $required[ $name ] === strtolower( (string) $column['Type'] ) && 'YES' === $column['Null'] ) {
				unset( $required[ $name ] );
			}
		}
		return empty( $required );
	}

	/**
	 * Canonical inquiry table DDL.
	 *
	 * active_contact_id is NULL except on the active row. Its UNIQUE index gives
	 * us a portable one-active-inquiry-per-contact invariant without a partial
	 * index (which MySQL/MariaDB do not provide in the same way as PostgreSQL).
	 *
	 * @return string
	 */
	public static function schema_sql() {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			inquiry_id varchar(40) NOT NULL,
			contact_id bigint(20) unsigned NOT NULL,
			active_contact_id bigint(20) unsigned NULL,
			previous_inquiry_id varchar(40) NULL,
			state varchar(24) NOT NULL,
			lifecycle varchar(24) NOT NULL DEFAULT '',
			form_id bigint(20) unsigned NULL,
			submission_id bigint(20) unsigned NULL,
			transaction_id varchar(191) NULL,
			source_type varchar(40) NOT NULL DEFAULT '',
			source_reference varchar(191) NULL,
			reconciliation_id varchar(64) NULL,
			request_event_id varchar(80) NULL,
			request_payload_hash char(64) NULL,
			activation_event_id varchar(80) NULL,
			activation_payload_hash char(64) NULL,
			projection_plan longtext NULL,
			projection_plan_hash char(64) NULL,
			projection_verified_at_utc datetime NULL,
			expected_head_version bigint(20) unsigned NOT NULL DEFAULT 0,
			committed_head_version bigint(20) unsigned NULL,
			contract_version varchar(24) NOT NULL DEFAULT '1',
			qualified_at datetime NULL,
			unqualified_at datetime NULL,
			closed_date date NULL,
			invoiced_amount decimal(20,6) NULL,
			customer_rating varchar(191) NULL,
			ads_conversion_value_frozen decimal(20,6) NULL,
			prepared_at_utc datetime NULL,
			activated_at_utc datetime NULL,
			archived_at_utc datetime NULL,
			created_at_utc datetime NOT NULL,
			updated_at_utc datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY inquiry_id (inquiry_id),
			UNIQUE KEY active_contact_id (active_contact_id),
			UNIQUE KEY transaction_id (transaction_id),
			UNIQUE KEY reconciliation_id (reconciliation_id),
			UNIQUE KEY request_event_id (request_event_id),
			UNIQUE KEY activation_event_id (activation_event_id),
			KEY contact_id (contact_id),
			KEY state (state),
			KEY qualified_at (qualified_at),
			KEY closed_date (closed_date)
		) {$charset};";
	}

	/**
	 * Explicit migration. Never runs implicitly on front-end traffic.
	 *
	 * @param bool $apply Whether to write.
	 * @return array
	 */
	public static function migrate( $apply = false ) {
		$out = array(
			'table'          => self::table_name(),
			'exists_before'  => self::table_exists(),
			'exists_after'   => self::table_exists(),
			'applied'        => false,
			'schema_version' => self::SCHEMA_VERSION,
		);
		if ( ! $apply ) {
			return $out;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::schema_sql() );
		$out['exists_after'] = self::table_exists();
		$out['applied']      = $out['exists_after'] && self::schema_ready( false );
		if ( $out['applied'] ) {
			update_option( 'seab_inquiry_schema_version', self::SCHEMA_VERSION, false );
			$out['applied'] = self::schema_ready();
		}
		return $out;
	}

	/**
	 * Migration/adoption API: freeze an already-current CRM inquiry into Ads Bridge
	 * before repeat-inquiry activation is enabled. This closes the one-time upgrade
	 * race where a later Fluent Forms feed could otherwise overwrite the contact
	 * submission_id before the previous inquiry had an immutable Ads binding.
	 *
	 * Required v1 payload:
	 * - contract_version = "1"
	 * - event_id
	 * - site_instance_id
	 * - contact_id
	 * - inquiry_id
	 * - head_version
	 * - source (optional; fluent_form requires form_id + entry_id)
	 *
	 * When source is omitted, Ads Bridge resolves the contact's current canonical
	 * submission_id during this explicit adoption window. CRM Ops must complete
	 * adoption before enabling Start New Inquiry for pre-0.6 contacts.
	 *
	 * @param array $payload Adoption payload.
	 * @return array
	 */
	public static function adopt_current_inquiry( $payload ) {
		return self::serialized_transition( 'adoption', $payload, static function ( $normalized ) {
			return self::adopt_current_inquiry_locked( $normalized );
		} );
	}

	/**
	 * @param array $payload Validated payload.
	 * @return array
	 */
	private static function adopt_current_inquiry_locked( $payload ) {
		try {
			$payload = is_array( $payload ) ? $payload : array();
			$valid   = self::validate_adoption_payload( $payload );
			if ( ! $valid['ok'] ) {
				return self::adoption_response( $payload, 'rejected', $valid['reason'], '' );
			}
			if ( ! self::table_exists() ) {
				return self::adoption_response( $payload, 'retry', 'inquiry_table_missing', '' );
			}

			global $wpdb;
			$table        = self::table_name();
			$event_id     = trim( (string) $payload['event_id'] );
			$inquiry_id   = trim( (string) $payload['inquiry_id'] );
			$contact_id   = (int) $payload['contact_id'];
			$head_version = (int) $payload['head_version'];
			$payload_hash = self::payload_hash( $payload );

			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE inquiry_id = %s OR request_event_id = %s LIMIT 1", $inquiry_id, $event_id ), ARRAY_A );
			if ( is_array( $existing ) ) {
				if ( (string) $existing['inquiry_id'] !== $inquiry_id || (int) $existing['contact_id'] !== $contact_id ) {
					return self::adoption_response( $payload, 'rejected', 'adoption_identity_conflict', isset( $existing['transaction_id'] ) ? (string) $existing['transaction_id'] : '' );
				}
				if ( null !== self::nullable_string( $existing['request_event_id'] ) && ( (string) $existing['request_event_id'] !== $event_id || (string) $existing['request_payload_hash'] !== $payload_hash ) ) {
					return self::adoption_response( $payload, 'rejected', 'adoption_idempotency_conflict', isset( $existing['transaction_id'] ) ? (string) $existing['transaction_id'] : '' );
				}
				if ( 'active' === (string) $existing['state'] && (int) $existing['active_contact_id'] === $contact_id ) {
					return self::adoption_response( $payload, 'adopted', 'already_adopted', isset( $existing['transaction_id'] ) ? (string) $existing['transaction_id'] : '' );
				}
				return self::adoption_response( $payload, 'rejected', 'existing_inquiry_not_active_current', isset( $existing['transaction_id'] ) ? (string) $existing['transaction_id'] : '' );
			}

			$active = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE active_contact_id = %d LIMIT 1", $contact_id ), ARRAY_A );
			if ( is_array( $active ) ) {
				return self::adoption_response( $payload, 'rejected', 'contact_already_has_active_inquiry', isset( $active['transaction_id'] ) ? (string) $active['transaction_id'] : '' );
			}

			if ( ! empty( self::pending_for_contact( $contact_id ) ) ) {
				return self::adoption_response( $payload, 'retry', 'pending_reconciliation_exists', '' );
			}

			$subscriber = self::find_subscriber( $contact_id );
			if ( ! $subscriber || ! method_exists( $subscriber, 'custom_fields' ) ) {
				return self::adoption_response( $payload, 'rejected', 'contact_not_found', '' );
			}
			$custom = (array) $subscriber->custom_fields();
			$source = isset( $payload['source'] ) && is_array( $payload['source'] ) ? (array) $payload['source'] : array();
			if ( ! empty( $source ) ) {
				$binding = self::validate_source_binding( $source );
				if ( ! $binding['ok'] ) {
					return self::adoption_response( $payload, 'rejected', $binding['reason'], '' );
				}
			} else {
				$binding = self::binding_from_contact_custom( $custom );
				$raw_submission_id = isset( $custom['submission_id'] ) ? trim( (string) $custom['submission_id'] ) : '';
				if ( '' !== $raw_submission_id && empty( $binding['transaction_id'] ) ) {
					return self::adoption_response( $payload, 'rejected', 'current_submission_binding_unresolved', '' );
				}
			}

			if ( ! empty( $binding['transaction_id'] ) ) {
				$bound = $wpdb->get_row( $wpdb->prepare( "SELECT inquiry_id,contact_id FROM `{$table}` WHERE transaction_id = %s LIMIT 1", (string) $binding['transaction_id'] ), ARRAY_A );
				if ( is_array( $bound ) ) {
					return self::adoption_response( $payload, 'rejected', 'transaction_already_bound', (string) $binding['transaction_id'] );
				}
			}

			$config = SiteEngine_Ads_Bridge_Config::instance();
			$qf     = trim( (string) $config->get( 'exports.qualified_leads.date_field', 'qualified_at' ) );
			$uf     = trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_at_field', 'unqualified_at' ) );
			$cf     = trim( (string) $config->get( 'exports.won_jobs.date_field', 'closed_date' ) );
			$vf     = trim( (string) $config->get( 'exports.won_jobs.value_field', 'invoiced_amount' ) );
			$rf     = trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_reason_field', 'customer_rating' ) );
			$now    = self::utc_now();
			$data   = array(
				'inquiry_id'                   => $inquiry_id,
				'contact_id'                   => $contact_id,
				'active_contact_id'            => $contact_id,
				'previous_inquiry_id'          => null,
				'state'                        => 'active',
				'lifecycle'                    => self::subscriber_lifecycle_role( $subscriber ),
				'form_id'                      => isset( $binding['form_id'] ) ? $binding['form_id'] : null,
				'submission_id'                => isset( $binding['entry_id'] ) ? $binding['entry_id'] : null,
				'transaction_id'               => isset( $binding['transaction_id'] ) ? $binding['transaction_id'] : null,
				'source_type'                  => isset( $source['type'] ) ? trim( (string) $source['type'] ) : 'adopted_current',
				'source_reference'             => isset( $source['reference'] ) ? self::nullable_string( $source['reference'] ) : null,
				'reconciliation_id'            => null,
				'request_event_id'             => $event_id,
				'request_payload_hash'         => $payload_hash,
				'activation_event_id'          => null,
				'activation_payload_hash'      => null,
				'expected_head_version'        => $head_version,
				'committed_head_version'       => $head_version,
				'projection_plan'             => null,
				'projection_plan_hash'        => null,
				'projection_verified_at_utc'  => null,
				'contract_version'             => self::CONTRACT_VERSION,
				'qualified_at'                 => self::mysql_datetime_or_null( isset( $custom[ $qf ] ) ? $custom[ $qf ] : null ),
				'unqualified_at'               => self::mysql_datetime_or_null( isset( $custom[ $uf ] ) ? $custom[ $uf ] : null ),
				'closed_date'                  => self::mysql_date_or_null( isset( $custom[ $cf ] ) ? $custom[ $cf ] : null ),
				'invoiced_amount'              => self::positive_decimal_or_null( isset( $custom[ $vf ] ) ? $custom[ $vf ] : null ),
				'customer_rating'              => self::nullable_string( isset( $custom[ $rf ] ) ? $custom[ $rf ] : null ),
				'ads_conversion_value_frozen'  => self::positive_decimal_or_null( isset( $custom['ads_conversion_value_frozen'] ) ? $custom['ads_conversion_value_frozen'] : null ),
				'prepared_at_utc'              => $now,
				'activated_at_utc'             => $now,
				'archived_at_utc'              => null,
				'created_at_utc'               => $now,
				'updated_at_utc'               => $now,
			);
			self::assert_contact_lock( $contact_id );
			$inserted = $wpdb->insert( $table, $data );
			if ( 1 !== $inserted ) {
				return self::adoption_response( $payload, 'retry', 'adoption_persistence_failed', isset( $binding['transaction_id'] ) ? (string) $binding['transaction_id'] : '' );
			}
			$saved = self::get( $inquiry_id );
			if ( ! is_array( $saved ) || 'active' !== (string) $saved['state'] || (int) $saved['active_contact_id'] !== $contact_id || (string) $saved['request_payload_hash'] !== $payload_hash ) {
				return self::adoption_response( $payload, 'retry', 'adoption_readback_failed', '' );
			}
			return self::adoption_response( $payload, 'adopted', 'adopted', isset( $binding['transaction_id'] ) ? (string) $binding['transaction_id'] : '' );
		} catch ( \Throwable $e ) {
			return self::adoption_response( is_array( $payload ) ? $payload : array(), 'retry', 'adoption_exception:' . self::safe_reason( $e->getMessage() ), '' );
		}
	}

	/**
	 * Stable plugin API: prepare the Ads Bridge side of a CRM Ops inquiry switch.
	 * This is phase 1 of the persisted saga. It never mutates the FluentCRM
	 * current-contact projection.
	 *
	 * Required v1 payload:
	 * - contract_version = "1"
	 * - event_id
	 * - site_instance_id
	 * - contact_id
	 * - previous_inquiry_id (nullable)
	 * - proposed_inquiry_id
	 * - expected_head_version
	 * - request_id
	 * - source { type, reference?, form_id?, entry_id? }
	 *
	 * For source.type=fluent_form, form_id + entry_id are required and validated
	 * against the configured master form. Ads Bridge computes the canonical
	 * transaction ID itself and never trusts a caller-provided transaction ID.
	 *
	 * @param array $payload Request payload.
	 * @return array
	 */
	public static function prepare_reconciliation( $payload ) {
		return self::serialized_transition( 'prepare', $payload, static function ( $normalized ) {
			return self::prepare_reconciliation_locked( $normalized );
		} );
	}

	/**
	 * @param array $payload Validated payload.
	 * @return array
	 */
	private static function prepare_reconciliation_locked( $payload ) {
		try {
			$payload = is_array( $payload ) ? $payload : array();
			$valid   = self::validate_prepare_payload( $payload );
			if ( ! $valid['ok'] ) {
				return self::prepare_response( $payload, 'rejected', $valid['reason'], '' );
			}
			if ( ! self::table_exists() ) {
				return self::prepare_response( $payload, 'retry', 'inquiry_table_missing', '' );
			}

			global $wpdb;
			$table        = self::table_name();
			$event_id     = trim( (string) $payload['event_id'] );
			$proposed     = trim( (string) $payload['proposed_inquiry_id'] );
			$previous     = self::nullable_string( isset( $payload['previous_inquiry_id'] ) ? $payload['previous_inquiry_id'] : null );
			$contact_id   = (int) $payload['contact_id'];
			$expected     = (int) $payload['expected_head_version'];
			$payload_hash = self::payload_hash( $payload );
			$recon_id     = self::reconciliation_id_for( $payload );

			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE request_event_id = %s OR inquiry_id = %s LIMIT 1", $event_id, $proposed ), ARRAY_A );
			if ( is_array( $existing ) ) {
				if ( (string) $existing['request_event_id'] !== $event_id || (string) $existing['inquiry_id'] !== $proposed || (string) $existing['request_payload_hash'] !== $payload_hash ) {
					return self::prepare_response( $payload, 'rejected', 'prepare_idempotency_conflict', isset( $existing['reconciliation_id'] ) ? (string) $existing['reconciliation_id'] : $recon_id );
				}
				if ( in_array( (string) $existing['state'], array( 'active', 'archived' ), true ) && ! empty( $existing['activation_event_id'] ) ) {
					$response = self::prepare_response( $payload, 'committed', 'already_activated', (string) $existing['reconciliation_id'] );
					$response['inquiry_state'] = (string) $existing['state'];
					$response['committed_head_version'] = (int) $existing['committed_head_version'];
					return $response;
				}
				if ( 'activating' === (string) $existing['state'] ) {
					return self::prepare_response( $payload, 'retry', 'activation_in_progress', (string) $existing['reconciliation_id'] );
				}
				if ( 'prepared' !== (string) $existing['state'] ) {
					return self::prepare_response( $payload, 'rejected', 'prepared_state_invalid', (string) $existing['reconciliation_id'] );
				}
			}

			$subscriber = self::find_subscriber( $contact_id );
			if ( ! $subscriber ) {
				return self::prepare_response( $payload, 'rejected', 'contact_not_found', $recon_id );
			}

			$active = self::active_for_contact( $contact_id );
			if ( null === $previous ) {
				if ( is_array( $active ) ) {
					return self::prepare_response( $payload, 'rejected', 'unexpected_active_inquiry', $recon_id );
				}
				if ( 0 !== $expected ) {
					return self::prepare_response( $payload, 'rejected', 'head_version_mismatch', $recon_id );
				}
				if ( self::contact_has_history( $contact_id ) ) {
					return self::prepare_response( $payload, 'retry', 'current_inquiry_head_missing', $recon_id );
				}
			} else {
				if ( ! is_array( $active ) ) {
					$prior = self::get( $previous );
					return self::prepare_response( $payload, is_array( $prior ) ? 'rejected' : 'retry', is_array( $prior ) ? 'previous_inquiry_state_mismatch' : 'previous_inquiry_not_adopted', $recon_id );
				}
				if ( (string) $active['inquiry_id'] !== $previous || (int) $active['contact_id'] !== $contact_id || 'active' !== (string) $active['state'] ) {
					return self::prepare_response( $payload, 'rejected', 'active_inquiry_mismatch', $recon_id );
				}
				if ( null === $active['committed_head_version'] || $expected !== (int) $active['committed_head_version'] ) {
					return self::prepare_response( $payload, 'rejected', 'head_version_mismatch', $recon_id );
				}
			}
			foreach ( self::pending_for_contact( $contact_id ) as $pending ) {
				if ( ! is_array( $existing ) || (int) $pending['id'] !== (int) $existing['id'] ) {
					return self::prepare_response( $payload, 'retry', 'pending_reconciliation_exists', $recon_id );
				}
			}
			if ( is_array( $existing ) ) {
				return self::prepare_response( $payload, 'ready', 'already_prepared', (string) $existing['reconciliation_id'] );
			}

			$source  = (array) $payload['source'];
			$binding = self::validate_source_binding( $source );
			if ( ! $binding['ok'] ) {
				return self::prepare_response( $payload, 'rejected', $binding['reason'], $recon_id );
			}

			$now = self::utc_now();
			$data = array(
				'inquiry_id'                 => $proposed,
				'contact_id'                 => $contact_id,
				'active_contact_id'          => null,
				'previous_inquiry_id'        => $previous,
				'state'                      => 'prepared',
				'lifecycle'                  => '',
				'form_id'                    => $binding['form_id'],
				'submission_id'              => $binding['entry_id'],
				'transaction_id'             => $binding['transaction_id'],
				'source_type'                => trim( (string) $source['type'] ),
				'source_reference'           => self::nullable_string( isset( $source['reference'] ) ? $source['reference'] : null ),
				'reconciliation_id'          => $recon_id,
				'request_event_id'           => $event_id,
				'request_payload_hash'       => $payload_hash,
				'activation_event_id'        => null,
				'activation_payload_hash'    => null,
				'expected_head_version'      => $expected,
				'committed_head_version'     => null,
				'projection_plan'             => null,
				'projection_plan_hash'        => null,
				'projection_verified_at_utc'  => null,
				'contract_version'           => self::CONTRACT_VERSION,
				'qualified_at'               => null,
				'unqualified_at'             => null,
				'closed_date'                => null,
				'invoiced_amount'            => null,
				'customer_rating'            => null,
				'ads_conversion_value_frozen'=> null,
				'prepared_at_utc'            => $now,
				'activated_at_utc'           => null,
				'archived_at_utc'            => null,
				'created_at_utc'             => $now,
				'updated_at_utc'             => $now,
			);
			self::assert_contact_lock( $contact_id );
			$inserted = $wpdb->insert( $table, $data );
			if ( 1 !== $inserted ) {
				return self::prepare_response( $payload, 'retry', 'prepare_persistence_failed', $recon_id );
			}
			$saved = self::get( $proposed );
			if ( ! is_array( $saved ) || 'prepared' !== (string) $saved['state'] || (int) $saved['contact_id'] !== $contact_id || (string) $saved['request_payload_hash'] !== $payload_hash || (string) $saved['reconciliation_id'] !== $recon_id ) {
				return self::prepare_response( $payload, 'retry', 'prepare_readback_failed', $recon_id );
			}
			return self::prepare_response( $payload, 'ready', 'prepared', $recon_id );
		} catch ( \Throwable $e ) {
			return self::prepare_response( is_array( $payload ) ? $payload : array(), 'retry', 'prepare_exception:' . self::safe_reason( $e->getMessage() ), '' );
		}
	}

	/**
	 * Stable plugin API: commit phase after CRM Ops has atomically activated its
	 * inquiry head. The trusted CRM Ops adapter must call this method explicitly.
	 *
	 * Ads Bridge applies the current-contact reset idempotently, archives the old
	 * Ads inquiry snapshot, activates the new one, then acknowledges. CRM Ops must
	 * keep customer-facing automation held until status=committed is returned.
	 *
	 * @param array $payload Activation event payload.
	 * @return array
	 */
	public static function commit_activation( $payload ) {
		return self::serialized_transition( 'activation', $payload, static function ( $normalized ) {
			return self::commit_activation_locked( $normalized );
		} );
	}

	/**
	 * @param array $payload Validated payload.
	 * @return array
	 */
	private static function commit_activation_locked( $payload ) {
		try {
			$payload = is_array( $payload ) ? $payload : array();
			$valid   = self::validate_activation_payload( $payload );
			if ( ! $valid['ok'] ) {
				return self::activation_response( $payload, 'rejected', $valid['reason'] );
			}
			if ( ! self::table_exists() ) {
				return self::activation_response( $payload, 'retry', 'inquiry_table_missing' );
			}

			global $wpdb;
			$table       = self::table_name();
			$recon_id    = trim( (string) $payload['reconciliation_id'] );
			$event_id    = trim( (string) $payload['event_id'] );
			$payload_hash= self::payload_hash( $payload );
			$row         = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE reconciliation_id = %s LIMIT 1", $recon_id ), ARRAY_A );
			if ( ! is_array( $row ) ) {
				return self::activation_response( $payload, 'retry', 'prepared_reconciliation_not_found' );
			}
			if ( (string) $row['inquiry_id'] !== (string) $payload['proposed_inquiry_id'] || (int) $row['contact_id'] !== (int) $payload['contact_id'] ) {
				return self::activation_response( $payload, 'rejected', 'activation_identity_mismatch' );
			}
			$site_binding = self::reconciliation_id_for( array( 'site_instance_id' => $payload['site_instance_id'], 'event_id' => $row['request_event_id'], 'proposed_inquiry_id' => $row['inquiry_id'] ) );
			if ( $site_binding !== $recon_id ) {
				return self::activation_response( $payload, 'rejected', 'activation_site_mismatch' );
			}
			$previous = self::nullable_string( isset( $payload['previous_inquiry_id'] ) ? $payload['previous_inquiry_id'] : null );
			if ( self::nullable_string( $row['previous_inquiry_id'] ) !== $previous ) {
				return self::activation_response( $payload, 'rejected', 'activation_previous_inquiry_mismatch' );
			}
			if ( in_array( (string) $row['state'], array( 'active', 'archived' ), true ) ) {
				if ( (string) $row['activation_event_id'] === $event_id && (string) $row['activation_payload_hash'] === $payload_hash ) {
					return self::activation_response( $payload, 'committed', 'already_committed' );
				}
				return self::activation_response( $payload, 'rejected', 'activation_idempotency_conflict' );
			}
			if ( ! in_array( (string) $row['state'], array( 'prepared', 'activating' ), true ) ) {
				return self::activation_response( $payload, 'rejected', 'prepared_state_invalid' );
			}
			if ( 'activating' === (string) $row['state'] && ( (string) $row['activation_event_id'] !== $event_id || (string) $row['activation_payload_hash'] !== $payload_hash ) ) {
				return self::activation_response( $payload, 'rejected', 'activation_idempotency_conflict' );
			}


			$expected = (int) $row['expected_head_version'];
			if ( (int) $payload['head_version'] !== $expected + 1 ) {
				return self::activation_response( $payload, 'rejected', 'activation_head_version_mismatch' );
			}
			$active = self::active_for_contact( (int) $row['contact_id'] );
			if ( null === $previous ) {
				if ( is_array( $active ) || 0 !== $expected ) {
					return self::activation_response( $payload, 'rejected', 'activation_current_head_mismatch' );
				}
			} elseif ( ! is_array( $active ) || (string) $active['inquiry_id'] !== $previous || 'active' !== (string) $active['state'] || null === $active['committed_head_version'] || (int) $active['committed_head_version'] !== $expected ) {
				return self::activation_response( $payload, 'rejected', 'activation_current_head_mismatch' );
			}
			foreach ( self::pending_for_contact( (int) $row['contact_id'] ) as $pending ) {
				if ( (int) $pending['id'] !== (int) $row['id'] ) {
					return self::activation_response( $payload, 'retry', 'pending_reconciliation_exists' );
				}
			}
			$storage = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
			if ( ! is_array( $storage ) || empty( $storage['Engine'] ) || 'innodb' !== strtolower( (string) $storage['Engine'] ) ) {
				return self::activation_response( $payload, 'retry', 'inquiry_table_requires_innodb' );
			}
			self::assert_contact_lock( (int) $row['contact_id'] );

			$subscriber = self::find_subscriber( (int) $row['contact_id'] );
			if ( ! $subscriber ) {
				return self::activation_response( $payload, 'retry', 'contact_not_found' );
			}
			$planned = self::activation_projection_plan( $subscriber, $row, $payload_hash );
			if ( ! $planned['ok'] ) {
				return self::activation_response( $payload, 'retry', $planned['reason'] );
			}
			if ( 'prepared' === (string) $row['state'] ) {
				// Persist both the intent and original values before any CRM write.
				$marked = $wpdb->update(
					$table,
					array(
						'state'                   => 'activating',
						'activation_event_id'     => $event_id,
						'activation_payload_hash' => $payload_hash,
						'committed_head_version'  => (int) $payload['head_version'],
						'projection_plan'         => $planned['encoded'],
						'projection_plan_hash'    => $planned['hash'],
						'updated_at_utc'          => self::utc_now(),
					),
					array( 'id' => (int) $row['id'], 'state' => 'prepared' )
				);
				$marked_row = self::get( (string) $row['inquiry_id'] );
				if ( 1 !== $marked || ! is_array( $marked_row ) || 'activating' !== (string) $marked_row['state'] || $event_id !== (string) $marked_row['activation_event_id'] || $payload_hash !== (string) $marked_row['activation_payload_hash'] || $planned['encoded'] !== (string) $marked_row['projection_plan'] || $planned['hash'] !== (string) $marked_row['projection_plan_hash'] ) {
					return self::activation_response( $payload, 'retry', 'activation_state_not_persisted' );
				}
				$row = $marked_row;
			}

			self::assert_contact_lock( (int) $row['contact_id'] );
			self::$reconciling = true;
			try {
				$projected = self::project_new_inquiry_to_contact( $subscriber, $planned['plan'], ! empty( $row['projection_verified_at_utc'] ) );
			} finally {
				self::$reconciling = false;
			}
			if ( ! $projected['ok'] ) {
				return self::activation_response( $payload, 'retry', $projected['reason'] );
			}
			if ( empty( $row['projection_verified_at_utc'] ) ) {
				self::assert_contact_lock( (int) $row['contact_id'] );
				$verified_at = self::utc_now();
				$checkpoint = $wpdb->update(
					$table,
					array( 'projection_verified_at_utc' => $verified_at, 'updated_at_utc' => $verified_at ),
					array( 'id' => (int) $row['id'], 'state' => 'activating', 'activation_event_id' => $event_id, 'activation_payload_hash' => $payload_hash, 'projection_plan_hash' => $planned['hash'], 'projection_verified_at_utc' => null )
				);
				$saved = self::get( (string) $row['inquiry_id'] );
				if ( 1 !== $checkpoint || ! is_array( $saved ) || $verified_at !== (string) $saved['projection_verified_at_utc'] || $planned['hash'] !== (string) $saved['projection_plan_hash'] ) {
					return self::activation_response( $payload, 'retry', 'projection_checkpoint_not_persisted' );
				}
				$row = $saved;
			}
			// A persisted checkpoint is evidence of the reset, not permission to
			// erase edits made afterwards. Recheck before committing the pointers.
			$verified = self::projection_matches_plan( (int) $row['contact_id'], $planned['plan'] );
			if ( ! $verified['ok'] ) {
				return self::activation_response( $payload, 'retry', 'projection_checkpoint_drift' );
			}

			$now = self::utc_now();
			self::assert_contact_lock( (int) $row['contact_id'] );
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return self::activation_response( $payload, 'retry', 'inquiry_transaction_start_failed' );
			}
			try {
				if ( null !== $previous ) {
					$updated_prev = $wpdb->update(
						$table,
						array( 'state' => 'archived', 'active_contact_id' => null, 'archived_at_utc' => $now, 'updated_at_utc' => $now ),
						array( 'inquiry_id' => $previous, 'contact_id' => (int) $row['contact_id'], 'state' => 'active', 'active_contact_id' => (int) $row['contact_id'], 'committed_head_version' => $expected ),
						array( '%s','%d','%s','%s' ),
						array( '%s','%d','%s','%d','%d' )
					);
					if ( 1 !== $updated_prev ) {
						throw new \RuntimeException( 'previous_inquiry_archive_failed' );
					}
				}
				$updated_new = $wpdb->update(
					$table,
					array(
						'state'              => 'active',
						'active_contact_id'  => (int) $row['contact_id'],
						'lifecycle'          => 'new',
						'activated_at_utc'   => $now,
						'updated_at_utc'     => $now,
					),
					array( 'id' => (int) $row['id'], 'state' => 'activating', 'activation_event_id' => $event_id, 'activation_payload_hash' => $payload_hash, 'expected_head_version' => $expected, 'projection_plan_hash' => $planned['hash'], 'projection_verified_at_utc' => $row['projection_verified_at_utc'] ),
					array( '%s','%d','%s','%s','%s' ),
					array( '%d','%s','%s','%s','%d','%s','%s' )
				);
				if ( 1 !== $updated_new ) {
					throw new \RuntimeException( 'new_inquiry_activation_failed' );
				}
				self::assert_contact_lock( (int) $row['contact_id'] );
				if ( false === $wpdb->query( 'COMMIT' ) ) {
					throw new \RuntimeException( 'inquiry_transaction_commit_failed' );
				}
			} catch ( \Throwable $e ) {
				if ( false === $wpdb->query( 'ROLLBACK' ) ) {
					self::discard_connection();
					return self::activation_response( $payload, 'retry', 'inquiry_transaction_rollback_failed' );
				}
				return self::activation_response( $payload, 'retry', self::safe_reason( $e->getMessage() ) );
			}

			$committed = self::get( (string) $row['inquiry_id'] );
			$archived = null === $previous ? null : self::get( $previous );
			if ( ! is_array( $committed ) || 'active' !== (string) $committed['state'] || (int) $committed['active_contact_id'] !== (int) $row['contact_id'] || (int) $committed['committed_head_version'] !== (int) $payload['head_version'] || (string) $committed['activation_event_id'] !== $event_id || (string) $committed['activation_payload_hash'] !== $payload_hash || ( null !== $previous && ( ! is_array( $archived ) || 'archived' !== (string) $archived['state'] || null !== $archived['active_contact_id'] ) ) ) {
				return self::activation_response( $payload, 'retry', 'inquiry_commit_readback_failed' );
			}

			return self::activation_response( $payload, 'committed', 'activated' );
		} catch ( \Throwable $e ) {
			return self::activation_response( is_array( $payload ) ? $payload : array(), 'retry', 'activation_exception:' . self::safe_reason( $e->getMessage() ) );
		}
	}

	/** @param object $subscriber @param array $tag_ids @return void */
	public static function sync_from_tag_change( $subscriber, $tag_ids ) {
		unset( $tag_ids );
		self::sync_active_from_subscriber( $subscriber );
	}

	/** @param array $new_values @param object $subscriber @param array $update_values @return void */
	public static function sync_from_custom_change( $new_values, $subscriber, $update_values ) {
		unset( $new_values, $update_values );
		self::sync_active_from_subscriber( $subscriber );
	}

	/**
	 * Update mutable milestone state for the currently active inquiry. Immutable
	 * form/submission/transaction binding is deliberately never read back from the
	 * contact projection, preventing a later form feed from corrupting old inquiry
	 * attribution before CRM Ops reconciliation.
	 *
	 * @param object $subscriber FluentCRM subscriber.
	 * @return bool
	 */
	public static function sync_active_from_subscriber( $subscriber ) {
		if ( self::$connection_unusable || self::$reconciling || ! is_object( $subscriber ) || ! isset( $subscriber->id ) || ! self::table_exists() ) {
			return false;
		}
		$contact_id = (int) $subscriber->id;
		$lock = self::acquire_contact_lock( $contact_id );
		if ( ! $lock['ok'] ) {
			return false;
		}
		try {
			self::assert_contact_lock( $contact_id );
			// Do not mirror a stale model captured before another inquiry switch.
			$fresh = self::find_subscriber( $contact_id );
			$synced = self::sync_active_locked( $fresh );
			self::assert_contact_lock( $contact_id );
		} catch ( \Throwable $e ) {
			$synced = false;
		} finally {
			$released = self::release_contact_lock( $contact_id );
			if ( ! $released ) {
				self::discard_connection();
			}
		}
		return $released && $synced;
	}

	/**
	 * @param object $subscriber Fresh contact resolved under its lock.
	 * @return bool
	 */
	private static function sync_active_locked( $subscriber ) {
		if ( self::$reconciling || ! self::table_exists() || ! is_object( $subscriber ) || ! isset( $subscriber->id ) || ! method_exists( $subscriber, 'custom_fields' ) ) {
			return false;
		}
		if ( self::activation_pending_for_contact( (int) $subscriber->id ) ) {
			return false;
		}
		global $wpdb;
		$table = self::table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE active_contact_id = %d LIMIT 1", (int) $subscriber->id ), ARRAY_A );
		if ( ! is_array( $row ) || 'active' !== (string) $row['state'] ) {
			return false;
		}
		$custom = (array) $subscriber->custom_fields();
		$config = SiteEngine_Ads_Bridge_Config::instance();
		$qf     = trim( (string) $config->get( 'exports.qualified_leads.date_field', 'qualified_at' ) );
		$uf     = trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_at_field', 'unqualified_at' ) );
		$cf     = trim( (string) $config->get( 'exports.won_jobs.date_field', 'closed_date' ) );
		$vf     = trim( (string) $config->get( 'exports.won_jobs.value_field', 'invoiced_amount' ) );
		$rf     = trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_reason_field', 'customer_rating' ) );
		$lifecycle = self::subscriber_lifecycle_role( $subscriber );
		$data = array(
			'lifecycle'                   => $lifecycle,
			'qualified_at'                => self::mysql_datetime_or_null( isset( $custom[ $qf ] ) ? $custom[ $qf ] : null ),
			'unqualified_at'              => self::mysql_datetime_or_null( isset( $custom[ $uf ] ) ? $custom[ $uf ] : null ),
			'closed_date'                 => self::mysql_date_or_null( isset( $custom[ $cf ] ) ? $custom[ $cf ] : null ),
			'invoiced_amount'             => self::positive_decimal_or_null( isset( $custom[ $vf ] ) ? $custom[ $vf ] : null ),
			'customer_rating'             => self::nullable_string( isset( $custom[ $rf ] ) ? $custom[ $rf ] : null ),
			'ads_conversion_value_frozen' => self::positive_decimal_or_null( isset( $custom['ads_conversion_value_frozen'] ) ? $custom['ads_conversion_value_frozen'] : null ),
			'updated_at_utc'              => self::utc_now(),
		);
		self::assert_contact_lock( (int) $subscriber->id );
		$updated = $wpdb->update( $table, $data, array( 'id' => (int) $row['id'], 'state' => 'active', 'active_contact_id' => (int) $subscriber->id ), array( '%s','%s','%s','%s','%f','%s','%f','%s' ), array( '%d','%s','%d' ) );
		return false !== $updated;
	}

	/** @param int $contact_id @return array|null */
	public static function active_for_contact( $contact_id ) {
		if ( ! self::table_exists() || (int) $contact_id < 1 ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . self::table_name() . '` WHERE active_contact_id = %d LIMIT 1', (int) $contact_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** @param string $inquiry_id @return array|null */
	public static function get( $inquiry_id ) {
		if ( ! self::table_exists() || '' === trim( (string) $inquiry_id ) ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . self::table_name() . '` WHERE inquiry_id = %s LIMIT 1', trim( (string) $inquiry_id ) ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** @param string $transaction_id @return array|null */
	public static function get_by_transaction_id( $transaction_id ) {
		if ( ! self::table_exists() || '' === trim( (string) $transaction_id ) ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . self::table_name() . '` WHERE transaction_id = %s LIMIT 1', trim( (string) $transaction_id ) ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** @param int $contact_id @return bool */
	public static function contact_has_history( $contact_id ) {
		if ( ! self::table_exists() || (int) $contact_id < 1 ) {
			return false;
		}
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . self::table_name() . '` WHERE contact_id = %d', (int) $contact_id ) ) > 0;
	}

	/**
	 * Candidate inquiry rows for stage export. Prepared/activating rows are never
	 * exportable. Archived rows remain candidates so a stage reached just before a
	 * repeat-inquiry switch cannot be lost between cron runs.
	 *
	 * @param string $stage qualified|unqualified|won.
	 * @param int    $limit Max rows.
	 * @param array  $contact_ids Optional contact filter.
	 * @return array
	 */
	public static function candidates( $stage, $limit = 25, $contact_ids = array(), $offset = 0 ) {
		if ( ! self::table_exists() ) {
			return array();
		}
		$stage = trim( (string) $stage );
		$field = '';
		if ( 'qualified' === $stage ) {
			$field = 'qualified_at';
		} elseif ( 'unqualified' === $stage ) {
			$field = 'unqualified_at';
		} elseif ( 'won' === $stage ) {
			$field = 'closed_date';
		} else {
			return array();
		}
		$limit  = max( 1, (int) $limit );
		$offset = max( 0, (int) $offset );
		global $wpdb;
		$table = self::table_name();
		$where = "state IN ('active','archived') AND `{$field}` IS NOT NULL AND transaction_id IS NOT NULL AND transaction_id <> ''";
		if ( ! empty( $contact_ids ) ) {
			$ids = array_values( array_filter( array_map( 'absint', $contact_ids ) ) );
			if ( empty( $ids ) ) {
				return array();
			}
			$where .= ' AND contact_id IN (' . implode( ',', $ids ) . ')';
		}
		$sql = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY COALESCE(activated_at_utc, prepared_at_utc, created_at_utc) ASC, id ASC LIMIT " . (int) $offset . ',' . (int) $limit;
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Build a deterministic submission resolver from immutable inquiry binding.
	 *
	 * @param array $inquiry Inquiry row.
	 * @return array
	 */
	public static function resolver_for_inquiry( $inquiry ) {
		$out = array(
			'order_id'          => '',
			'source'            => 'inquiry_binding',
			'deterministic'     => false,
			'exportable'        => false,
			'reason'            => '',
			'form_id'           => '',
			'entry_id'          => '',
			'submissions_table' => '',
			'transaction_id'    => '',
		);
		if ( ! is_array( $inquiry ) ) {
			$out['reason'] = 'inquiry row missing';
			return $out;
		}
		$form_id = isset( $inquiry['form_id'] ) ? (int) $inquiry['form_id'] : 0;
		$entry_id= isset( $inquiry['submission_id'] ) ? (int) $inquiry['submission_id'] : 0;
		$txn     = isset( $inquiry['transaction_id'] ) ? trim( (string) $inquiry['transaction_id'] ) : '';
		if ( $form_id < 1 || $entry_id < 1 || ! preg_match( '/^ff:\d+:\d+$/', $txn ) ) {
			$out['reason'] = 'inquiry has no canonical Fluent Forms binding';
			return $out;
		}
		$table = self::canonical_submissions_table();
		if ( '' === $table ) {
			$out['reason'] = 'canonical Fluent Forms submissions table not found';
			return $out;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, form_id FROM `{$table}` WHERE id = %d LIMIT 1", $entry_id ), ARRAY_A );
		if ( ! is_array( $row ) || (int) $row['form_id'] !== $form_id ) {
			$out['reason'] = 'bound Fluent Forms submission no longer resolves';
			return $out;
		}
		$out['order_id']          = $txn;
		$out['deterministic']     = true;
		$out['exportable']        = true;
		$out['form_id']           = (string) $form_id;
		$out['entry_id']          = (string) $entry_id;
		$out['submissions_table'] = $table;
		return $out;
	}

	/** @return array */
	public static function status_summary() {
		$out = array( 'table_exists' => self::table_exists(), 'schema_version' => get_option( 'seab_inquiry_schema_version', '' ), 'schema_ready' => false, 'rows' => 0, 'active' => 0, 'prepared' => 0, 'archived' => 0 );
		if ( ! $out['table_exists'] ) {
			return $out;
		}
		$out['schema_ready'] = self::schema_ready();
		global $wpdb;
		$table = self::table_name();
		$out['rows']     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$out['active']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE state='active'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$out['prepared'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE state IN ('prepared','activating')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$out['archived'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE state='archived'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $out;
	}

	/** @param array $payload @return array */
	private static function validate_adoption_payload( $payload ) {
		if ( ! isset( $payload['contract_version'] ) || ( self::CONTRACT_VERSION !== $payload['contract_version'] && 1 !== $payload['contract_version'] ) ) {
			return array( 'ok' => false, 'reason' => 'unsupported_contract_version' );
		}
		foreach ( array( 'event_id', 'site_instance_id', 'inquiry_id' ) as $key ) {
			if ( ! isset( $payload[ $key ] ) || ! is_string( $payload[ $key ] ) || '' === $payload[ $key ] || trim( $payload[ $key ] ) !== $payload[ $key ] ) {
				return array( 'ok' => false, 'reason' => 'missing_' . $key );
			}
		}
		if ( strlen( $payload['event_id'] ) > 80 ) {
			return array( 'ok' => false, 'reason' => 'invalid_event_id' );
		}
		if ( ! isset( $payload['contact_id'] ) || ! self::valid_head_version( $payload['contact_id'], 1 ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_contact_id' );
		}
		if ( ! isset( $payload['head_version'] ) || ! self::valid_head_version( $payload['head_version'] ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_head_version' );
		}
		if ( ! self::valid_inquiry_id( $payload['inquiry_id'] ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_inquiry_id' );
		}
		if ( isset( $payload['source'] ) && ( ! is_array( $payload['source'] ) || empty( $payload['source']['type'] ) ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_source' );
		}
		return array( 'ok' => true, 'reason' => '' );
	}

	/** @param array $payload @return array */
	private static function validate_prepare_payload( $payload ) {
		if ( ! isset( $payload['contract_version'] ) || ( self::CONTRACT_VERSION !== $payload['contract_version'] && 1 !== $payload['contract_version'] ) ) {
			return array( 'ok' => false, 'reason' => 'unsupported_contract_version' );
		}
		foreach ( array( 'event_id', 'site_instance_id', 'proposed_inquiry_id', 'request_id' ) as $key ) {
			if ( ! isset( $payload[ $key ] ) || ! is_string( $payload[ $key ] ) || '' === $payload[ $key ] || trim( $payload[ $key ] ) !== $payload[ $key ] ) {
				return array( 'ok' => false, 'reason' => 'missing_' . $key );
			}
		}
		if ( strlen( $payload['event_id'] ) > 80 ) {
			return array( 'ok' => false, 'reason' => 'invalid_event_id' );
		}
		if ( ! isset( $payload['contact_id'] ) || ! self::valid_head_version( $payload['contact_id'], 1 ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_contact_id' );
		}
		if ( ! isset( $payload['expected_head_version'] ) || ! self::valid_head_version( $payload['expected_head_version'] ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_expected_head_version' );
		}
		if ( ! self::valid_inquiry_id( $payload['proposed_inquiry_id'] ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_proposed_inquiry_id' );
		}
		if ( isset( $payload['previous_inquiry_id'] ) && ( ! is_string( $payload['previous_inquiry_id'] ) || ( '' !== $payload['previous_inquiry_id'] && ( trim( $payload['previous_inquiry_id'] ) !== $payload['previous_inquiry_id'] || ! self::valid_inquiry_id( $payload['previous_inquiry_id'] ) ) ) ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_previous_inquiry_id' );
		}
		if ( ! isset( $payload['source'] ) || ! is_array( $payload['source'] ) || empty( $payload['source']['type'] ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_source' );
		}
		return array( 'ok' => true, 'reason' => '' );
	}

	/** @param array $payload @return array */
	private static function validate_activation_payload( $payload ) {
		if ( ! isset( $payload['contract_version'] ) || ( self::CONTRACT_VERSION !== $payload['contract_version'] && 1 !== $payload['contract_version'] ) ) {
			return array( 'ok' => false, 'reason' => 'unsupported_contract_version' );
		}
		foreach ( array( 'event_id', 'site_instance_id', 'reconciliation_id', 'proposed_inquiry_id' ) as $key ) {
			if ( ! isset( $payload[ $key ] ) || ! is_string( $payload[ $key ] ) || '' === $payload[ $key ] || trim( $payload[ $key ] ) !== $payload[ $key ] ) {
				return array( 'ok' => false, 'reason' => 'missing_' . $key );
			}
		}
		if ( strlen( $payload['event_id'] ) > 80 ) {
			return array( 'ok' => false, 'reason' => 'invalid_event_id' );
		}
		if ( ! isset( $payload['contact_id'] ) || ! self::valid_head_version( $payload['contact_id'], 1 ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_contact_id' );
		}
		if ( ! isset( $payload['head_version'] ) || ! self::valid_head_version( $payload['head_version'], 1 ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_head_version' );
		}
		if ( strlen( $payload['reconciliation_id'] ) > 64 ) {
			return array( 'ok' => false, 'reason' => 'invalid_reconciliation_id' );
		}
		if ( isset( $payload['previous_inquiry_id'] ) && ( ! is_string( $payload['previous_inquiry_id'] ) || ( '' !== $payload['previous_inquiry_id'] && ! self::valid_inquiry_id( $payload['previous_inquiry_id'] ) ) ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_previous_inquiry_id' );
		}
		if ( ! self::valid_inquiry_id( $payload['proposed_inquiry_id'] ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_proposed_inquiry_id' );
		}
		return array( 'ok' => true, 'reason' => '' );
	}

	/** @param array $source @return array */
	private static function validate_source_binding( $source ) {
		if ( ! isset( $source['type'] ) || ! is_string( $source['type'] ) || '' === trim( $source['type'] ) ) {
			return array( 'ok' => false, 'reason' => 'invalid_source' );
		}
		$type = isset( $source['type'] ) ? trim( (string) $source['type'] ) : '';
		if ( 'fluent_form' !== $type ) {
			return array( 'ok' => true, 'reason' => '', 'form_id' => null, 'entry_id' => null, 'transaction_id' => null );
		}
		if ( ! isset( $source['form_id'], $source['entry_id'] ) || ! self::valid_head_version( $source['form_id'], 1 ) || ! self::valid_head_version( $source['entry_id'], 1 ) ) {
			return array( 'ok' => false, 'reason' => 'fluent_form_source_requires_form_id_and_entry_id' );
		}
		$form_id  = isset( $source['form_id'] ) ? (int) $source['form_id'] : 0;
		$entry_id = isset( $source['entry_id'] ) ? (int) $source['entry_id'] : 0;
		if ( $form_id < 1 || $entry_id < 1 ) {
			return array( 'ok' => false, 'reason' => 'fluent_form_source_requires_form_id_and_entry_id' );
		}
		$configured = (int) SiteEngine_Ads_Bridge_Config::instance()->get( 'fluent_forms.form_id', 0 );
		if ( $configured < 1 || $form_id !== $configured ) {
			return array( 'ok' => false, 'reason' => 'source_form_id_not_configured_master_form' );
		}
		$table = self::canonical_submissions_table();
		if ( '' === $table ) {
			return array( 'ok' => false, 'reason' => 'canonical_submissions_table_missing' );
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, form_id FROM `{$table}` WHERE id = %d LIMIT 1", $entry_id ), ARRAY_A );
		if ( ! is_array( $row ) || (int) $row['form_id'] !== $form_id ) {
			return array( 'ok' => false, 'reason' => 'source_submission_not_found_or_form_mismatch' );
		}
		return array( 'ok' => true, 'reason' => '', 'form_id' => $form_id, 'entry_id' => $entry_id, 'transaction_id' => 'ff:' . $form_id . ':' . $entry_id );
	}

	/** @param object $subscriber @param string $inquiry_id @return array */
	private static function snapshot_legacy_inquiry( $subscriber, $inquiry_id ) {
		if ( ! is_object( $subscriber ) || ! isset( $subscriber->id ) || ! method_exists( $subscriber, 'custom_fields' ) ) {
			return array( 'ok' => false, 'reason' => 'legacy_snapshot_contact_unavailable' );
		}
		global $wpdb;
		$table   = self::table_name();
		$custom  = (array) $subscriber->custom_fields();
		$config  = SiteEngine_Ads_Bridge_Config::instance();
		$binding = self::binding_from_contact_custom( $custom );
		$qf      = trim( (string) $config->get( 'exports.qualified_leads.date_field', 'qualified_at' ) );
		$uf      = trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_at_field', 'unqualified_at' ) );
		$cf      = trim( (string) $config->get( 'exports.won_jobs.date_field', 'closed_date' ) );
		$vf      = trim( (string) $config->get( 'exports.won_jobs.value_field', 'invoiced_amount' ) );
		$rf      = trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_reason_field', 'customer_rating' ) );
		$now     = self::utc_now();
		$data = array(
			'inquiry_id'                  => $inquiry_id,
			'contact_id'                  => (int) $subscriber->id,
			'active_contact_id'           => (int) $subscriber->id,
			'previous_inquiry_id'         => null,
			'state'                       => 'active',
			'lifecycle'                   => self::subscriber_lifecycle_role( $subscriber ),
			'form_id'                     => $binding['form_id'],
			'submission_id'               => $binding['entry_id'],
			'transaction_id'              => $binding['transaction_id'],
			'source_type'                 => 'legacy_contact_snapshot',
			'source_reference'            => null,
			'reconciliation_id'           => null,
			'request_event_id'            => null,
			'request_payload_hash'        => null,
			'activation_event_id'         => null,
			'activation_payload_hash'     => null,
			'expected_head_version'       => 0,
			'committed_head_version'      => null,
			'projection_plan'             => null,
			'projection_plan_hash'        => null,
			'projection_verified_at_utc'  => null,
			'contract_version'            => self::CONTRACT_VERSION,
			'qualified_at'                => self::mysql_datetime_or_null( isset( $custom[ $qf ] ) ? $custom[ $qf ] : null ),
			'unqualified_at'              => self::mysql_datetime_or_null( isset( $custom[ $uf ] ) ? $custom[ $uf ] : null ),
			'closed_date'                 => self::mysql_date_or_null( isset( $custom[ $cf ] ) ? $custom[ $cf ] : null ),
			'invoiced_amount'             => self::positive_decimal_or_null( isset( $custom[ $vf ] ) ? $custom[ $vf ] : null ),
			'customer_rating'             => self::nullable_string( isset( $custom[ $rf ] ) ? $custom[ $rf ] : null ),
			'ads_conversion_value_frozen' => self::positive_decimal_or_null( isset( $custom['ads_conversion_value_frozen'] ) ? $custom['ads_conversion_value_frozen'] : null ),
			'prepared_at_utc'             => $now,
			'activated_at_utc'            => $now,
			'archived_at_utc'             => null,
			'created_at_utc'              => $now,
			'updated_at_utc'              => $now,
		);
		$ok = $wpdb->insert( $table, $data );
		return false === $ok ? array( 'ok' => false, 'reason' => 'legacy_snapshot_persistence_failed' ) : array( 'ok' => true, 'reason' => '' );
	}

	/** @param array $custom @return array */
	private static function binding_from_contact_custom( $custom ) {
		$entry_id = isset( $custom['submission_id'] ) && ctype_digit( trim( (string) $custom['submission_id'] ) ) ? (int) $custom['submission_id'] : 0;
		if ( $entry_id < 1 ) {
			return array( 'form_id' => null, 'entry_id' => null, 'transaction_id' => null );
		}
		$table = self::canonical_submissions_table();
		if ( '' === $table ) {
			return array( 'form_id' => null, 'entry_id' => null, 'transaction_id' => null );
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, form_id FROM `{$table}` WHERE id = %d LIMIT 1", $entry_id ), ARRAY_A );
		$configured = (int) SiteEngine_Ads_Bridge_Config::instance()->get( 'fluent_forms.form_id', 0 );
		if ( ! is_array( $row ) || (int) $row['form_id'] !== $configured ) {
			return array( 'form_id' => null, 'entry_id' => null, 'transaction_id' => null );
		}
		return array( 'form_id' => (int) $row['form_id'], 'entry_id' => (int) $row['id'], 'transaction_id' => 'ff:' . (int) $row['form_id'] . ':' . (int) $row['id'] );
	}

	/** @param array $inquiry @return array */
	private static function projection_definition( $inquiry ) {
		$map = SiteEngine_Ads_Bridge_Lifecycle::configured_tag_map();
		$lifecycle_ids = array();
		foreach ( array( 'new', 'qualified', 'unqualified', 'won' ) as $role ) {
			if ( empty( $map[ $role ]['id'] ) ) {
				return array( 'ok' => false, 'reason' => 'projection_lifecycle_tags_unresolved' );
			}
			$lifecycle_ids[] = (int) $map[ $role ]['id'];
		}
		if ( 4 !== count( array_unique( $lifecycle_ids ) ) ) {
			return array( 'ok' => false, 'reason' => 'projection_lifecycle_tags_not_unique' );
		}
		$entry_id = isset( $inquiry['submission_id'] ) && (int) $inquiry['submission_id'] > 0 ? (string) (int) $inquiry['submission_id'] : '';
		$reset = array(
			'submission_id'               => $entry_id,
			'qualified_at'                => '',
			'unqualified_at'              => '',
			'closed_date'                 => '',
			'invoiced_amount'             => '',
			'customer_rating'             => '',
			'lost_date'                   => '',
			'ads_export_status'           => '',
			'ads_exported_at'             => '',
			'ads_conversion_value_frozen' => '',
			'ads_export_error'            => '',
		);
		$config = SiteEngine_Ads_Bridge_Config::instance();
		$fields = array(
			'exports.qualified_leads.date_field' => 'qualified_at',
			'fluentcrm.lifecycle_automation.unqualified_at_field' => 'unqualified_at',
			'exports.won_jobs.date_field' => 'closed_date',
			'exports.won_jobs.value_field' => 'invoiced_amount',
			'fluentcrm.lifecycle_automation.unqualified_reason_field' => 'customer_rating',
		);
		foreach ( $fields as $key => $default ) {
			$slug = trim( (string) $config->get( $key, $default ) );
			if ( '' === $slug || 'submission_id' === $slug ) {
				return array( 'ok' => false, 'reason' => 'projection_field_mapping_invalid' );
			}
			$reset[ $slug ] = '';
		}
		return array( 'ok' => true, 'reason' => '', 'fields' => $reset, 'lifecycle_ids' => $lifecycle_ids, 'new_tag_id' => (int) $map['new']['id'] );
	}

	/**
	 * Freeze the reset before first use; retries must use that exact plan.
	 * Legacy activating rows have no trustworthy pre-reset snapshot.
	 *
	 * @param object $subscriber Contact.
	 * @param array  $row Inquiry row.
	 * @param string $activation_hash Matching activation body hash.
	 * @return array
	 */
	private static function activation_projection_plan( $subscriber, $row, $activation_hash ) {
		$definition = self::projection_definition( $row );
		if ( ! $definition['ok'] ) {
			return $definition;
		}
		$context = array( 'version' => 1, 'inquiry_id' => (string) $row['inquiry_id'], 'reconciliation_id' => (string) $row['reconciliation_id'], 'activation_payload_hash' => $activation_hash, 'definition' => $definition );
		if ( 'prepared' === (string) $row['state'] ) {
			if ( ! method_exists( $subscriber, 'syncCustomFieldValues' ) || ! method_exists( $subscriber, 'attachTags' ) || ! method_exists( $subscriber, 'detachTags' ) ) {
				return array( 'ok' => false, 'reason' => 'subscriber_projection_methods_unavailable' );
			}
			$before = self::projection_snapshot( (int) $row['contact_id'], $definition );
			if ( ! $before['ok'] ) {
				return $before;
			}
			$plan = array( 'context' => $context, 'before' => $before );
			$encoded = wp_json_encode( $plan );
			if ( ! is_string( $encoded ) || '' === $encoded ) {
				return array( 'ok' => false, 'reason' => 'projection_plan_encoding_failed' );
			}
		} else {
			$encoded = isset( $row['projection_plan'] ) ? $row['projection_plan'] : null;
			if ( ! is_string( $encoded ) || '' === $encoded ) {
				return array( 'ok' => false, 'reason' => 'legacy_activation_projection_unknown' );
			}
			if ( ! isset( $row['projection_plan_hash'] ) || ! hash_equals( (string) $row['projection_plan_hash'], hash( 'sha256', $encoded ) ) ) {
				return array( 'ok' => false, 'reason' => 'projection_plan_corrupt' );
			}
			$plan = json_decode( $encoded, true );
			if ( ! is_array( $plan ) || ! isset( $plan['context'], $plan['before']['fields'], $plan['before']['tags'] ) || ! is_array( $plan['before']['fields'] ) || ! is_array( $plan['before']['tags'] ) ) {
				return array( 'ok' => false, 'reason' => 'projection_plan_corrupt' );
			}
			if ( $context !== $plan['context'] ) {
				return array( 'ok' => false, 'reason' => 'projection_plan_configuration_changed' );
			}
			if ( array_keys( $definition['fields'] ) !== array_keys( $plan['before']['fields'] ) ) {
				return array( 'ok' => false, 'reason' => 'projection_plan_corrupt' );
			}
		}
		return array( 'ok' => true, 'reason' => '', 'plan' => $plan, 'encoded' => $encoded, 'hash' => hash( 'sha256', $encoded ) );
	}

	/** @param int $contact_id @param array $definition @return array */
	private static function projection_snapshot( $contact_id, $definition ) {
		$contact = self::find_subscriber( $contact_id );
		if ( ! $contact || ! method_exists( $contact, 'custom_fields' ) ) {
			return array( 'ok' => false, 'reason' => 'projection_readback_unavailable' );
		}
		$custom = (array) $contact->custom_fields();
		$fields = array();
		foreach ( $definition['fields'] as $slug => $desired ) {
			$value = isset( $custom[ $slug ] ) ? $custom[ $slug ] : '';
			if ( ! is_scalar( $value ) ) {
				return array( 'ok' => false, 'reason' => 'projection_field_value_invalid' );
			}
			$fields[ $slug ] = (string) $value;
		}
		$tags = array();
		foreach ( isset( $contact->tags ) ? $contact->tags : array() as $tag ) {
			if ( isset( $tag->id ) && in_array( (int) $tag->id, $definition['lifecycle_ids'], true ) ) {
				$tags[] = (int) $tag->id;
			}
		}
		$tags = array_values( array_unique( $tags ) );
		sort( $tags, SORT_NUMERIC );
		return array( 'ok' => true, 'reason' => '', 'fields' => $fields, 'tags' => $tags );
	}

	/** @param int $contact_id @param array $plan @return array */
	private static function projection_matches_plan( $contact_id, $plan ) {
		$definition = $plan['context']['definition'];
		$current = self::projection_snapshot( $contact_id, $definition );
		if ( ! $current['ok'] ) {
			return $current;
		}
		if ( $definition['fields'] !== $current['fields'] ) {
			return array( 'ok' => false, 'reason' => 'projection_fields_not_persisted' );
		}
		if ( array( $definition['new_tag_id'] ) !== $current['tags'] ) {
			return array( 'ok' => false, 'reason' => 'projection_lifecycle_not_persisted' );
		}
		return array( 'ok' => true, 'reason' => '' );
	}

	/**
	 * Resume only original -> desired changes. Never resubmit completed fields
	 * or clear a value not present in the frozen pre-reset snapshot.
	 *
	 * @param object $subscriber Contact.
	 * @param array  $plan Persisted projection plan.
	 * @param bool   $verified Whether a completion checkpoint already exists.
	 * @return array
	 */
	private static function project_new_inquiry_to_contact( $subscriber, $plan, $verified ) {
		$definition = $plan['context']['definition'];
		$contact_id = (int) $subscriber->id;
		if ( $verified ) {
			$result = self::projection_matches_plan( $contact_id, $plan );
			return $result['ok'] ? $result : array( 'ok' => false, 'reason' => 'projection_checkpoint_drift' );
		}
		$current = self::projection_snapshot( $contact_id, $definition );
		if ( ! $current['ok'] ) {
			return $current;
		}
		$remaining = array();
		foreach ( $definition['fields'] as $slug => $desired ) {
			$actual = $current['fields'][ $slug ];
			if ( $actual === $desired ) {
				continue;
			}
			if ( $actual !== $plan['before']['fields'][ $slug ] ) {
				return array( 'ok' => false, 'reason' => 'projection_recovery_conflict' );
			}
			$remaining[ $slug ] = $desired;
		}
		foreach ( $current['tags'] as $tag_id ) {
			if ( $definition['new_tag_id'] !== $tag_id && ! in_array( $tag_id, $plan['before']['tags'], true ) ) {
				return array( 'ok' => false, 'reason' => 'projection_recovery_conflict' );
			}
		}
		if ( ! empty( $remaining ) ) {
			self::assert_contact_lock( $contact_id );
			// true deletes only the supplied blanks in installed FluentCRM.
			$subscriber->syncCustomFieldValues( $remaining, true );
		}
		self::assert_contact_lock( $contact_id );
		$current = self::projection_snapshot( $contact_id, $definition );
		if ( ! $current['ok'] ) {
			return $current;
		}
		if ( $definition['fields'] !== $current['fields'] ) {
			return array( 'ok' => false, 'reason' => 'projection_fields_not_persisted' );
		}
		$detach = array();
		foreach ( $current['tags'] as $tag_id ) {
			if ( $definition['new_tag_id'] !== $tag_id ) {
				if ( ! in_array( $tag_id, $plan['before']['tags'], true ) ) {
					return array( 'ok' => false, 'reason' => 'projection_recovery_conflict' );
				}
				$detach[] = $tag_id;
			}
		}
		if ( ! empty( $detach ) ) {
			self::assert_contact_lock( $contact_id );
			$subscriber->detachTags( $detach );
		}
		if ( ! in_array( $definition['new_tag_id'], $current['tags'], true ) ) {
			self::assert_contact_lock( $contact_id );
			$subscriber->attachTags( array( $definition['new_tag_id'] ) );
		}
		self::assert_contact_lock( $contact_id );
		return self::projection_matches_plan( $contact_id, $plan );
	}

	/** @param int $contact_id @return object|null */
	private static function find_subscriber( $contact_id ) {
		if ( ! class_exists( '\\FluentCrm\\App\\Models\\Subscriber' ) ) {
			return null;
		}
		return \FluentCrm\App\Models\Subscriber::find( (int) $contact_id );
	}

	/** @param object $subscriber @return string */
	private static function subscriber_lifecycle_role( $subscriber ) {
		$map = SiteEngine_Ads_Bridge_Lifecycle::configured_tag_map();
		$ids = array();
		if ( is_object( $subscriber ) && isset( $subscriber->tags ) ) {
			foreach ( $subscriber->tags as $tag ) {
				if ( isset( $tag->id ) ) {
					$ids[] = (int) $tag->id;
				}
			}
		}
		$found = array();
		foreach ( $map as $role => $row ) {
			if ( ! empty( $row['id'] ) && in_array( (int) $row['id'], $ids, true ) ) {
				$found[] = $role;
			}
		}
		return 1 === count( $found ) ? (string) $found[0] : '';
	}

	/** @return string */
	private static function canonical_submissions_table() {
		global $wpdb;
		$candidates = array( $wpdb->prefix . 'fluentform_submissions' );
		foreach ( $candidates as $table ) {
			if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				return $table;
			}
		}
		return '';
	}

	/** @param array $payload @return string */
	private static function reconciliation_id_for( $payload ) {
		$site  = isset( $payload['site_instance_id'] ) ? (string) $payload['site_instance_id'] : '';
		$event = isset( $payload['event_id'] ) ? (string) $payload['event_id'] : '';
		$inq   = isset( $payload['proposed_inquiry_id'] ) ? (string) $payload['proposed_inquiry_id'] : '';
		return 'abr_' . substr( hash( 'sha256', $site . '|' . $event . '|' . $inq ), 0, 32 );
	}

	/** @param array $payload @param string $status @param string $reason @param string $transaction_id @return array */
	private static function adoption_response( $payload, $status, $reason, $transaction_id ) {
		return array(
			'contract_version'   => self::CONTRACT_VERSION,
			'ads_bridge_version' => defined( 'SEAB_VERSION' ) ? SEAB_VERSION : '',
			'event_id'           => isset( $payload['event_id'] ) ? (string) $payload['event_id'] : '',
			'inquiry_id'         => isset( $payload['inquiry_id'] ) ? (string) $payload['inquiry_id'] : '',
			'contact_id'         => isset( $payload['contact_id'] ) ? (int) $payload['contact_id'] : 0,
			'head_version'       => isset( $payload['head_version'] ) ? (int) $payload['head_version'] : 0,
			'transaction_id'     => $transaction_id,
			'status'             => $status,
			'reason_code'        => $reason,
		);
	}

	/** @param array $payload @param string $status @param string $reason @param string $recon_id @return array */
	private static function prepare_response( $payload, $status, $reason, $recon_id ) {
		return array(
			'contract_version'      => self::CONTRACT_VERSION,
			'ads_bridge_version'    => defined( 'SEAB_VERSION' ) ? SEAB_VERSION : '',
			'reconciliation_id'     => $recon_id,
			'request_event_id'      => isset( $payload['event_id'] ) ? (string) $payload['event_id'] : '',
			'proposed_inquiry_id'   => isset( $payload['proposed_inquiry_id'] ) ? (string) $payload['proposed_inquiry_id'] : '',
			'previous_inquiry_id'   => isset( $payload['previous_inquiry_id'] ) ? $payload['previous_inquiry_id'] : null,
			'expected_head_version' => isset( $payload['expected_head_version'] ) ? (int) $payload['expected_head_version'] : 0,
			'status'                => $status,
			'reason_code'           => $reason,
		);
	}

	/** @param array $payload @param string $status @param string $reason @return array */
	private static function activation_response( $payload, $status, $reason ) {
		return array(
			'contract_version'    => self::CONTRACT_VERSION,
			'ads_bridge_version'  => defined( 'SEAB_VERSION' ) ? SEAB_VERSION : '',
			'reconciliation_id'   => isset( $payload['reconciliation_id'] ) ? (string) $payload['reconciliation_id'] : '',
			'activation_event_id' => isset( $payload['event_id'] ) ? (string) $payload['event_id'] : '',
			'proposed_inquiry_id' => isset( $payload['proposed_inquiry_id'] ) ? (string) $payload['proposed_inquiry_id'] : '',
			'previous_inquiry_id' => isset( $payload['previous_inquiry_id'] ) ? $payload['previous_inquiry_id'] : null,
			'contact_id'          => isset( $payload['contact_id'] ) ? (int) $payload['contact_id'] : 0,
			'head_version'        => isset( $payload['head_version'] ) ? (int) $payload['head_version'] : 0,
			'status'              => $status,
			'reason_code'         => $reason,
		);
	}

	/** @param mixed $value @return bool */
	private static function valid_inquiry_id( $value ) {
		return 1 === preg_match( '/^inq_[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', trim( (string) $value ) );
	}

	/** @param mixed $value @return string|null */
	private static function nullable_string( $value ) {
		if ( null === $value ) {
			return null;
		}
		$value = trim( (string) $value );
		return '' === $value ? null : $value;
	}

	/** @param mixed $value @return string|null */
	private static function mysql_datetime_or_null( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})/', $value, $m ) ) {
			return $m[1] . ' ' . $m[2];
		}
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})$/', $value, $m ) ) {
			return $m[1] . ' 12:00:00';
		}
		return null;
	}

	/** @param mixed $value @return string|null */
	private static function mysql_date_or_null( $value ) {
		$value = trim( (string) $value );
		return preg_match( '/^(\d{4}-\d{2}-\d{2})/', $value, $m ) ? $m[1] : null;
	}

	/** @param mixed $value @return float|null */
	private static function positive_decimal_or_null( $value ) {
		return is_numeric( $value ) && (float) $value > 0 ? (float) $value : null;
	}

	/** @return string */
	private static function utc_now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/** @param array $payload @return string */
	private static function payload_hash( $payload ) {
		$copy = self::canonicalize( $payload );
		return hash( 'sha256', wp_json_encode( $copy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/** @param mixed $value @return mixed */
	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$assoc = array_keys( $value ) !== range( 0, count( $value ) - 1 );
		if ( $assoc ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}

	/** @param string $reason @return string */
	private static function safe_reason( $reason ) {
		$reason = preg_replace( '/[^A-Za-z0-9_.:-]+/', '_', (string) $reason );
		return substr( trim( $reason, '_' ), 0, 120 );
	}
}
