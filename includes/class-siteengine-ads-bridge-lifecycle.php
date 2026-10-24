<?php
/**
 * FluentCRM lifecycle compliance automation for SiteEngine Ads Bridge.
 *
 * Standard client-facing lifecycle:
 *   New -> Qualified | Unqualified | Won
 *
 * The client makes the business judgment. Ads Bridge handles deterministic
 * state hygiene and milestone timestamps when lifecycle automation is enabled.
 *
 * @package SiteEngineAdsBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteEngine_Ads_Bridge_Lifecycle {

	/** @var bool Recursion guard for tag mutations triggered by our own hook. */
	private static $mutating = false;

	/**
	 * Allowed internal Unqualified Reason values. The CRM field may contain
	 * additional historical values, but these are the canonical Golden values.
	 *
	 * @return array
	 */
	public static function unqualified_reason_values() {
		return array(
			'Wrong Area',
			'Wrong Service',
			'Wrong Product',
			'Wrong Price',
			'Spam / Junk',
			'Other',
		);
	}

	/** @return void */
	public static function init() {
		add_action( 'fluent_crm/contact_added_by_fluentform', array( __CLASS__, 'on_contact_added_by_fluentform' ), 20, 4 );
		add_action( 'fluent_crm/contact_added_to_tags', array( __CLASS__, 'on_contact_added_to_tags' ), 20, 2 );
		add_action( 'fluent_crm/contact_custom_data_updated', array( __CLASS__, 'on_contact_custom_data_updated' ), 50, 3 );
	}

	/**
	 * @param object|null $subscriber Contact being reconciled.
	 * @return bool
	 */
	private static function enabled( $subscriber = null ) {
		// During an intentional repeat-inquiry reset the old Won tag may still
		// be attached while its fields are being cleared. Do not restamp those
		// milestones or restore terminal state from the previous inquiry.
		if ( class_exists( 'SiteEngine_Ads_Bridge_Inquiries' ) ) {
			if ( SiteEngine_Ads_Bridge_Inquiries::is_reconciling() || ( is_object( $subscriber ) && isset( $subscriber->id ) && SiteEngine_Ads_Bridge_Inquiries::activation_pending_for_contact( (int) $subscriber->id ) ) ) {
				return false;
			}
		}
		$config = SiteEngine_Ads_Bridge_Config::instance();
		return (bool) $config->get( 'fluentcrm.lifecycle_automation.enabled', false );
	}

	/**
	 * Seed a newly-created FluentCRM contact with the configured New tag when the
	 * contact was created by the configured master Fluent Form and no lifecycle
	 * state is already present. Existing contacts updated by a new form submission
	 * are intentionally not reset; the current contact-level data model does not
	 * represent concurrent opportunities.
	 *
	 * @param object $subscriber Subscriber model.
	 * @param mixed  $entry      Fluent Forms entry.
	 * @param object $form       Fluent Forms form model.
	 * @param mixed  $feed       FluentCRM feed.
	 * @return void
	 */
	public static function on_contact_added_by_fluentform( $subscriber, $entry, $form, $feed ) {
		unset( $entry, $feed );
		if ( ! self::enabled( $subscriber ) || ! is_object( $subscriber ) || ! method_exists( $subscriber, 'attachTags' ) ) {
			return;
		}

		$config     = SiteEngine_Ads_Bridge_Config::instance();
		$configured = (int) $config->get( 'fluent_forms.form_id', 0 );
		$actual     = is_object( $form ) && isset( $form->id ) ? (int) $form->id : 0;
		if ( $configured < 1 || $actual !== $configured ) {
			return;
		}

		$map = self::configured_tag_map();
		if ( empty( $map['new']['id'] ) || ! empty( self::subscriber_lifecycle_roles( $subscriber, $map ) ) ) {
			return;
		}

		$subscriber->attachTags( array( (int) $map['new']['id'] ) );
	}

	/**
	 * Enforce one lifecycle state and stamp durable milestones when a lifecycle
	 * tag is added. Won implies a good lead, so a direct New -> Won transition
	 * stamps qualified_at as well as closed_date when either is still blank.
	 *
	 * @param object $subscriber Subscriber model.
	 * @param array  $tag_ids    Newly attached FluentCRM tag IDs.
	 * @return void
	 */
	public static function on_contact_added_to_tags( $subscriber, $tag_ids ) {
		if ( self::$mutating || ! self::enabled( $subscriber ) || ! is_object( $subscriber ) ) {
			return;
		}
		if ( ! method_exists( $subscriber, 'detachTags' ) || ! method_exists( $subscriber, 'syncCustomFieldValues' ) || ! method_exists( $subscriber, 'custom_fields' ) ) {
			return;
		}

		$map = self::configured_tag_map();
		if ( empty( $map ) ) {
			return;
		}

		$added_ids = array_values( array_filter( array_map( 'absint', (array) $tag_ids ) ) );
		if ( empty( $added_ids ) ) {
			return;
		}

		$added_roles = array();
		foreach ( $map as $role => $row ) {
			if ( ! empty( $row['id'] ) && in_array( (int) $row['id'], $added_ids, true ) ) {
				$added_roles[] = $role;
			}
		}
		if ( empty( $added_roles ) ) {
			return;
		}

		// Won is terminal once attached. A lower-stage tag added while Won is
		// still present is treated as an accidental regression and is removed
		// below. An operator can still intentionally reopen/correct a record by
		// removing Won first, then attaching the desired lifecycle state.
		$current_roles = self::subscriber_lifecycle_roles( $subscriber, $map );
		if ( in_array( 'won', $current_roles, true ) && ! in_array( 'won', $added_roles, true ) ) {
			$added_roles[] = 'won';
		}

		// If an API/bulk action attached more than one lifecycle tag at once,
		// resolve deterministically toward the furthest business outcome.
		$priority = array( 'won', 'unqualified', 'qualified', 'new' );
		$active   = '';
		foreach ( $priority as $role ) {
			if ( in_array( $role, $added_roles, true ) ) {
				$active = $role;
				break;
			}
		}
		if ( '' === $active || empty( $map[ $active ]['id'] ) ) {
			return;
		}

		self::$mutating = true;
		try {
			$detach = array();
			foreach ( $map as $role => $row ) {
				if ( $role !== $active && ! empty( $row['id'] ) ) {
					$detach[] = (int) $row['id'];
				}
			}
			if ( ! empty( $detach ) ) {
				$subscriber->detachTags( array_values( array_unique( $detach ) ) );
			}

			$config     = SiteEngine_Ads_Bridge_Config::instance();
			$qualified  = trim( (string) $config->get( 'exports.qualified_leads.date_field', 'qualified_at' ) );
			$closed     = trim( (string) $config->get( 'exports.won_jobs.date_field', 'closed_date' ) );
			$unqualified = trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_at_field', 'unqualified_at' ) );

			if ( 'qualified' === $active ) {
				self::stamp_if_blank( $subscriber, $qualified, current_time( 'mysql' ) );
			} elseif ( 'unqualified' === $active ) {
				// Under the 0.5.1 lifecycle, Qualified and Unqualified are competing
				// lead-quality outcomes. If the client corrects a lead to Unqualified
				// before export, do not leave a stale qualification milestone that can
				// later generate a false positive Google signal.
				self::clear_if_set( $subscriber, $qualified );
				self::stamp_if_blank( $subscriber, $unqualified, current_time( 'mysql' ) );
			} elseif ( 'won' === $active ) {
				// A sale is necessarily a qualified lead. Preserve an earlier
				// qualified_at when one exists; otherwise stamp it now.
				self::stamp_if_blank( $subscriber, $qualified, current_time( 'mysql' ) );
				self::stamp_if_blank( $subscriber, $closed, current_time( 'Y-m-d' ) );
			}
		} finally {
			self::$mutating = false;
		}
	}


	/**
	 * Reconcile milestone timestamps after FluentCRM finishes saving custom
	 * fields. The contact editor may persist tag changes before blank custom
	 * fields from the same request; without this second pass a valid tag-driven
	 * stamp can be overwritten later in the save cycle.
	 *
	 * @param array  $new_values    Submitted custom-field values.
	 * @param object $subscriber    Subscriber model.
	 * @param array  $update_values Values FluentCRM actually updated.
	 * @return void
	 */
	public static function on_contact_custom_data_updated( $new_values, $subscriber, $update_values ) {
		unset( $new_values, $update_values );
		if ( self::$mutating || ! self::enabled( $subscriber ) || ! is_object( $subscriber ) ) {
			return;
		}
		if ( ! method_exists( $subscriber, 'syncCustomFieldValues' ) || ! method_exists( $subscriber, 'custom_fields' ) ) {
			return;
		}

		$map   = self::configured_tag_map();
		$roles = self::subscriber_lifecycle_roles( $subscriber, $map );
		if ( empty( $roles ) ) {
			return;
		}

		$priority = array( 'won', 'unqualified', 'qualified', 'new' );
		$active   = '';
		foreach ( $priority as $role ) {
			if ( in_array( $role, $roles, true ) ) {
				$active = $role;
				break;
			}
		}
		if ( '' === $active || 'new' === $active ) {
			return;
		}

		self::$mutating = true;
		try {
			$config      = SiteEngine_Ads_Bridge_Config::instance();
			$qualified   = trim( (string) $config->get( 'exports.qualified_leads.date_field', 'qualified_at' ) );
			$closed      = trim( (string) $config->get( 'exports.won_jobs.date_field', 'closed_date' ) );
			$unqualified = trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_at_field', 'unqualified_at' ) );

			if ( 'qualified' === $active ) {
				self::stamp_if_blank( $subscriber, $qualified, current_time( 'mysql' ) );
			} elseif ( 'unqualified' === $active ) {
				self::clear_if_set( $subscriber, $qualified );
				self::stamp_if_blank( $subscriber, $unqualified, current_time( 'mysql' ) );
			} elseif ( 'won' === $active ) {
				self::stamp_if_blank( $subscriber, $qualified, current_time( 'mysql' ) );
				self::stamp_if_blank( $subscriber, $closed, current_time( 'Y-m-d' ) );
			}
		} finally {
			self::$mutating = false;
		}
	}

	/**
	 * Read the configured lifecycle tags and resolve exact FluentCRM tag IDs.
	 *
	 * @return array role => array{id:int,title:string}
	 */
	public static function configured_tag_map() {
		global $wpdb;
		$config = SiteEngine_Ads_Bridge_Config::instance();
		$map    = array();
		foreach ( SiteEngine_Ads_Bridge_Config::lifecycle_roles() as $role ) {
			$title = trim( (string) $config->get( 'fluentcrm.tags.lifecycle.' . $role, '' ) );
			$map[ $role ] = array( 'id' => 0, 'title' => $title );
		}

		if ( ! isset( $wpdb->prefix ) ) {
			return $map;
		}
		$table = $wpdb->prefix . 'fc_tags';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return $map;
		}
		foreach ( $map as $role => $row ) {
			if ( '' === $row['title'] ) {
				continue;
			}
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE title = %s ORDER BY id ASC LIMIT 1", $row['title'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$map[ $role ]['id'] = $id ? (int) $id : 0;
		}
		return $map;
	}

	/**
	 * Return configured lifecycle roles currently attached to a subscriber.
	 *
	 * @param object $subscriber Subscriber model.
	 * @param array  $map        Configured map.
	 * @return array
	 */
	private static function subscriber_lifecycle_roles( $subscriber, $map ) {
		$ids = array();
		if ( isset( $subscriber->tags ) ) {
			foreach ( $subscriber->tags as $tag ) {
				if ( isset( $tag->id ) ) {
					$ids[] = (int) $tag->id;
			}
			}
		}
		$roles = array();
		foreach ( $map as $role => $row ) {
			if ( ! empty( $row['id'] ) && in_array( (int) $row['id'], $ids, true ) ) {
				$roles[] = $role;
			}
		}
		return $roles;
	}

	/**
	 * Upsert one contact custom field only when it is currently blank.
	 *
	 * @param object $subscriber Subscriber model.
	 * @param string $slug       Custom-field slug.
	 * @param string $value      Value to write.
	 * @return void
	 */
	private static function stamp_if_blank( $subscriber, $slug, $value ) {
		$slug = trim( (string) $slug );
		if ( '' === $slug ) {
			return;
		}
		$custom = (array) $subscriber->custom_fields();
		$current = isset( $custom[ $slug ] ) ? trim( (string) $custom[ $slug ] ) : '';
		if ( '' !== $current ) {
			return;
		}
		$subscriber->syncCustomFieldValues( array( $slug => (string) $value ), false );
	}

	/**
	 * Clear one contact custom field when it currently contains a value.
	 *
	 * @param object $subscriber Subscriber model.
	 * @param string $slug       Custom-field slug.
	 * @return void
	 */
	private static function clear_if_set( $subscriber, $slug ) {
		$slug = trim( (string) $slug );
		if ( '' === $slug ) {
			return;
		}
		$custom  = (array) $subscriber->custom_fields();
		$current = isset( $custom[ $slug ] ) ? trim( (string) $custom[ $slug ] ) : '';
		if ( '' === $current ) {
			return;
		}
		$subscriber->syncCustomFieldValues( array( $slug => '' ), true );
	}

	/**
	 * Build a read-only client-compliance report from current FluentCRM state.
	 *
	 * @param int $limit Maximum contacts to inspect, newest first.
	 * @return array{summary:array,issues:array,inspected:int}
	 */
	public static function compliance_report( $limit = 250 ) {
		global $wpdb;
		$out = array(
			'summary'   => array(
				'no_lifecycle' => 0,
				'conflict' => 0,
				'stale_new' => 0,
				'qualified_incomplete' => 0,
				'unqualified_incomplete' => 0,
				'won_incomplete' => 0,
			),
			'issues'    => array(),
			'inspected' => 0,
		);
		if ( ! isset( $wpdb->prefix ) ) {
			return $out;
		}

		$config = SiteEngine_Ads_Bridge_Config::instance();
		$map    = self::configured_tag_map();
		$tag_ids = array();
		$id_to_role = array();
		foreach ( $map as $role => $row ) {
			if ( ! empty( $row['id'] ) ) {
				$tag_ids[] = (int) $row['id'];
				$id_to_role[ (int) $row['id'] ] = $role;
			}
		}

		$subscribers_table = $wpdb->prefix . 'fc_subscribers';
		$pivot_table       = $wpdb->prefix . 'fc_subscriber_pivot';
		$meta_table        = $wpdb->prefix . 'fc_subscriber_meta';
		foreach ( array( $subscribers_table, $pivot_table, $meta_table ) as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				return $out;
			}
		}

		$limit = max( 1, min( 1000, absint( $limit ) ) );
		$contacts = $wpdb->get_results( "SELECT id, first_name, last_name, email, created_at, updated_at FROM `{$subscribers_table}` ORDER BY id DESC LIMIT {$limit}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$contacts = is_array( $contacts ) ? $contacts : array();
		$out['inspected'] = count( $contacts );
		if ( empty( $contacts ) ) {
			return $out;
		}
		$contact_ids = array_map( 'intval', wp_list_pluck( $contacts, 'id' ) );
		$id_sql = implode( ',', array_filter( $contact_ids ) );
		if ( '' === $id_sql ) {
			return $out;
		}

		$roles_by_contact = array();
		if ( ! empty( $tag_ids ) ) {
			$tag_sql = implode( ',', array_map( 'intval', $tag_ids ) );
			$pivot_sql = $wpdb->prepare( "SELECT subscriber_id, object_id FROM `{$pivot_table}` WHERE subscriber_id IN ({$id_sql}) AND object_type = %s AND object_id IN ({$tag_sql})", 'FluentCrm\\App\\Models\\Tag' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$pivots = $wpdb->get_results( $pivot_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			foreach ( (array) $pivots as $pivot ) {
				$cid = (int) $pivot['subscriber_id'];
				$tid = (int) $pivot['object_id'];
				if ( isset( $id_to_role[ $tid ] ) ) {
					$roles_by_contact[ $cid ][] = $id_to_role[ $tid ];
				}
			}
		}

		$reason_field   = trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_reason_field', 'customer_rating' ) );
		$qualified_field = trim( (string) $config->get( 'exports.qualified_leads.date_field', 'qualified_at' ) );
		$unqualified_field = trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_at_field', 'unqualified_at' ) );
		$closed_field   = trim( (string) $config->get( 'exports.won_jobs.date_field', 'closed_date' ) );
		$value_field    = trim( (string) $config->get( 'exports.won_jobs.value_field', 'invoiced_amount' ) );
		$meta_keys      = array_values( array_unique( array_filter( array( $reason_field, $qualified_field, $unqualified_field, $closed_field, $value_field ) ) ) );
		$meta_by_contact = array();
		if ( ! empty( $meta_keys ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
			$sql = $wpdb->prepare(
				"SELECT subscriber_id, `key`, value FROM `{$meta_table}` WHERE subscriber_id IN ({$id_sql}) AND object_type = 'custom_field' AND `key` IN ({$placeholders})",
				$meta_keys
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$metas = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			foreach ( (array) $metas as $meta ) {
				$meta_by_contact[ (int) $meta['subscriber_id'] ][ (string) $meta['key'] ] = (string) $meta['value'];
			}
		}

		$allowed_reasons = self::unqualified_reason_values();
		$stale_days      = max( 1, absint( $config->get( 'fluentcrm.lifecycle_automation.stale_new_days', 3 ) ) );
		$stale_cutoff    = time() - ( DAY_IN_SECONDS * $stale_days );

		foreach ( $contacts as $contact ) {
			$cid   = (int) $contact['id'];
			$roles = isset( $roles_by_contact[ $cid ] ) ? array_values( array_unique( $roles_by_contact[ $cid ] ) ) : array();
			$meta  = isset( $meta_by_contact[ $cid ] ) ? $meta_by_contact[ $cid ] : array();
			$name  = trim( (string) $contact['first_name'] . ' ' . (string) $contact['last_name'] );
			if ( '' === $name ) {
				$name = (string) $contact['email'];
			}
			$add_issue = function ( $type, $detail ) use ( &$out, $cid, $name, $roles ) {
				$out['summary'][ $type ]++;
				$out['issues'][] = array(
					'contact_id' => $cid,
					'contact'    => $name,
					'lifecycle'  => empty( $roles ) ? '(none)' : implode( ' + ', $roles ),
					'issue'      => $type,
					'detail'     => $detail,
				);
			};

			if ( 0 === count( $roles ) ) {
				$add_issue( 'no_lifecycle', 'No configured lifecycle tag is attached.' );
				continue;
			}
			if ( count( $roles ) > 1 ) {
				$add_issue( 'conflict', 'More than one configured lifecycle tag is attached.' );
				continue;
			}

			$role = $roles[0];
			if ( 'new' === $role ) {
				$created = ! empty( $contact['created_at'] ) ? strtotime( (string) $contact['created_at'] ) : false;
				if ( false !== $created && $created < $stale_cutoff ) {
					$add_issue( 'stale_new', sprintf( 'Still New after more than %d day(s).', $stale_days ) );
				}
			} elseif ( 'qualified' === $role ) {
				if ( empty( $meta[ $qualified_field ] ) ) {
					$add_issue( 'qualified_incomplete', $qualified_field . ' is blank.' );
				}
			} elseif ( 'unqualified' === $role ) {
				$reason = isset( $meta[ $reason_field ] ) ? trim( (string) $meta[ $reason_field ] ) : '';
				$parts  = array();
				if ( '' === $reason ) {
					$parts[] = $reason_field . ' is blank';
				} elseif ( ! in_array( $reason, $allowed_reasons, true ) ) {
					$parts[] = $reason_field . ' has a non-standard value: ' . $reason;
				}
				if ( empty( $meta[ $unqualified_field ] ) ) {
					$parts[] = $unqualified_field . ' is blank';
				}
				if ( ! empty( $parts ) ) {
					$add_issue( 'unqualified_incomplete', implode( '; ', $parts ) . '.' );
				}
			} elseif ( 'won' === $role ) {
				$parts = array();
				$value = isset( $meta[ $value_field ] ) ? $meta[ $value_field ] : '';
				if ( ! is_numeric( $value ) || (float) $value <= 0 ) {
					$parts[] = $value_field . ' must be > 0';
				}
				if ( empty( $meta[ $closed_field ] ) ) {
					$parts[] = $closed_field . ' is blank';
				}
				if ( empty( $meta[ $qualified_field ] ) ) {
					$parts[] = $qualified_field . ' is blank';
				}
				if ( ! empty( $parts ) ) {
					$add_issue( 'won_incomplete', implode( '; ', $parts ) . '.' );
				}
			}
		}

		return $out;
	}
}
