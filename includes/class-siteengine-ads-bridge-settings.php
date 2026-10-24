<?php
/**
 * WordPress-backed settings storage for SiteEngine Ads Bridge.
 *
 * @package SiteEngineAdsBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteEngine_Ads_Bridge_Settings {

	const OPTION_KEY = 'seab_settings_v2';

	/**
	 * Whether a SiteEngine Ads Bridge settings payload has been saved.
	 *
	 * @return bool
	 */
	public static function exists() {
		$value = get_option( self::OPTION_KEY, null );
		return is_array( $value ) && ! empty( $value );
	}

	/**
	 * Get saved settings.
	 *
	 * @return array
	 */
	public static function get() {
		$value = get_option( self::OPTION_KEY, array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Save a schema-v2 configuration payload.
	 *
	 * @param array $config Configuration.
	 * @return bool
	 */
	public static function save( $config ) {
		$config = self::sanitize_config( $config );
		return update_option( self::OPTION_KEY, $config, false );
	}

	/**
	 * Delete WordPress-backed settings. Primarily useful during migration tests.
	 *
	 * @return bool
	 */
	public static function delete() {
		return delete_option( self::OPTION_KEY );
	}

	/**
	 * Base/default v2 tree for a fresh setup.
	 * Shared business facts are intentionally populated from SiteEngine Base at
	 * runtime by the config resolver rather than duplicated here.
	 *
	 * @return array
	 */
	public static function defaults() {
		$policy = defined( 'SE_CONSENT_POLICY_VERSION' ) ? (string) SE_CONSENT_POLICY_VERSION : '';

		return array(
			'schema_version' => 2,
			'client'         => array(
				'slug'                   => '',
				'name'                   => '',
				'short_name'             => '',
				'timezone'               => '',
				'currency'               => '',
				'google_ads_customer_id' => '',
			),
			'fluent_forms'   => array(
				'form_id' => 0,
				'fields'  => self::default_field_map(),
			),
			'fluentcrm'      => array(
				'lists' => array(
					'customers'        => 'Customers',
					'future_customers' => 'Future Customers',
					'do_not_contact'   => 'DNC',
				),
				'tags'  => array(
					'lifecycle' => array(
						'new'         => 'New',
						'qualified'   => 'Qualified',
						'unqualified' => 'Unqualified',
						'won'         => 'Won',
					),
					'sources'   => array(
						'call'     => 'Call Lead',
						'form'     => 'Form Lead',
						'referral' => 'Referral Lead',
						'walk_in'  => 'Walk In Lead',
					),
				),
				'lifecycle_automation' => array(
					'enabled'                  => false,
					'unqualified_reason_field' => 'customer_rating',
					'unqualified_at_field'     => 'unqualified_at',
					'stale_new_days'           => 3,
				),
			),
			'exports'        => array(
				'all_leads'  => array(
					'enabled'          => false,
					'sheet_tab'        => 'All_Leads',
					'header_overrides' => array(),
				),
				'qualified_leads' => array(
					'enabled'            => false,
					'conversion_action'  => 'SE | Qualified Lead',
					'date_field'         => 'qualified_at',
					'conversion_value'   => '',
					'plugin_side_sha256' => true,
					'header_overrides'   => array(),
				),
				'won_jobs'   => array(
					'enabled'            => false,
					'sheet_tab'          => 'Google_Ads_Import',
					'conversion_action'  => 'SE | Converted Lead',
					'value_field'        => 'invoiced_amount',
					'date_field'         => 'closed_date',
					'plugin_side_sha256' => true,
					'header_overrides'   => array(),
				),
				'lost_leads' => array(
					'enabled'            => false,
					'date_field'         => 'closed_date',
					'conversion_value'   => '0.00',
					'plugin_side_sha256' => true,
					'header_overrides'   => array(),
					'reasons'            => array(
						'wrong_price'    => array( 'sheet_tab' => 'Lost_Wrong_Price', 'conversion_action' => '' ),
						'wrong_product'  => array( 'sheet_tab' => 'Lost_Wrong_Product', 'conversion_action' => '' ),
						'wrong_service'  => array( 'sheet_tab' => 'Lost_Wrong_Service', 'conversion_action' => '' ),
						'wrong_area'     => array( 'sheet_tab' => 'Lost_Wrong_Area', 'conversion_action' => '' ),
						'wrong_attitude' => array( 'sheet_tab' => 'Lost_Wrong_Attitude', 'conversion_action' => '' ),
					),
				),
			),
			'google_sheets'  => array(
				'enabled'            => false,
				'spreadsheet_id'     => '',
				'credentials_path'   => '',
				'value_input_option' => 'RAW',
			),
			'consent'        => array(
				'required_policy_version' => $policy,
				'require_ad_user_data'    => true,
			),
			'diagnostics'    => array(
				'test_profile_guards'  => false,
				'cron_expected_minutes' => 10,
			),
		);
	}

	/**
	 * Default Fluent Forms field map.
	 *
	 * @return array
	 */
	public static function default_field_map() {
		return array(
			'first_name'      => 'first_name',
			'last_name'       => 'last_name',
			'address_1'       => 'address_1',
			'address_2'       => 'address_2',
			'city'            => 'city',
			'state'           => 'state',
			'zip_code'        => 'zip_code',
			'country'         => 'country-list',
			'phone'           => 'phone',
			'email'           => 'email',
			'service_type'    => 'service_type',
			'estimated_value' => 'estimated_value',
			'invoiced_amount' => 'invoiced_amount',
			'closed_date'     => 'closed_date',
			'qualified_at'    => 'qualified_at',
			'lost_date'       => 'lost_date',
			'gclid'           => 'gclid',
			'gbraid'          => 'gbraid',
			'wbraid'          => 'wbraid',
			'fbclid'          => 'fbclid',
			'msclkid'         => 'msclkid',
			'utm_source'      => 'utm_source',
			'utm_medium'      => 'utm_medium',
			'utm_campaign'    => 'utm_campaign',
			'utm_content'     => 'utm_content',
			'utm_term'        => 'utm_term',
			'referrer_url'    => 'referrer_url',
			'page_title'      => 'page_title',
			'ip_address'      => 'ip_address',
			'user_agent'      => 'user_agent',
			'cookie_value'    => 'cookie_value',
			'transaction_id'  => 'transaction_id',
			'submission_id'   => 'submission_id',
			'se_cid'          => 'se_cid',
			'se_agid'         => 'se_agid',
			'se_adid'         => 'se_adid',
			'se_kw'           => 'se_kw',
			'se_mt'           => 'se_mt',
			'se_net'          => 'se_net',
			'se_dev'          => 'se_dev',
			'se_loc'          => 'se_loc',
		);
	}

	/**
	 * Deep merge arrays without allowing numeric-list semantics to corrupt maps.
	 *
	 * @param array $base Base.
	 * @param array $over Override.
	 * @return array
	 */
	public static function merge( $base, $over ) {
		foreach ( $over as $key => $value ) {
			if ( isset( $base[ $key ] ) && is_array( $base[ $key ] ) && is_array( $value ) ) {
				$base[ $key ] = self::merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Sanitize a complete schema-v2 settings tree.
	 *
	 * @param array $input Raw configuration.
	 * @return array
	 */
	public static function sanitize_config( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$config = self::merge( self::defaults(), $input );
		$config['schema_version'] = 2;

		$config['client']['slug']       = sanitize_key( (string) $config['client']['slug'] );
		$config['client']['name']       = sanitize_text_field( (string) $config['client']['name'] );
		$config['client']['short_name'] = sanitize_text_field( (string) $config['client']['short_name'] );

		$config['fluent_forms']['form_id'] = absint( $config['fluent_forms']['form_id'] );
		foreach ( self::default_field_map() as $key => $default ) {
			$value = isset( $config['fluent_forms']['fields'][ $key ] ) ? $config['fluent_forms']['fields'][ $key ] : $default;
			$config['fluent_forms']['fields'][ $key ] = sanitize_key( (string) $value );
		}

		foreach ( array( 'customers', 'future_customers', 'do_not_contact' ) as $role ) {
			$config['fluentcrm']['lists'][ $role ] = sanitize_text_field( (string) $config['fluentcrm']['lists'][ $role ] );
		}
		foreach ( array( 'new', 'qualified', 'unqualified', 'won' ) as $role ) {
			$config['fluentcrm']['tags']['lifecycle'][ $role ] = sanitize_text_field( (string) $config['fluentcrm']['tags']['lifecycle'][ $role ] );
		}
		$config['fluentcrm']['lifecycle_automation']['enabled'] = ! empty( $config['fluentcrm']['lifecycle_automation']['enabled'] );
		$config['fluentcrm']['lifecycle_automation']['unqualified_reason_field'] = sanitize_key( (string) $config['fluentcrm']['lifecycle_automation']['unqualified_reason_field'] );
		$config['fluentcrm']['lifecycle_automation']['unqualified_at_field'] = sanitize_key( (string) $config['fluentcrm']['lifecycle_automation']['unqualified_at_field'] );
		$config['fluentcrm']['lifecycle_automation']['stale_new_days'] = max( 1, absint( $config['fluentcrm']['lifecycle_automation']['stale_new_days'] ) );
		foreach ( array( 'call', 'form', 'referral', 'walk_in' ) as $role ) {
			$config['fluentcrm']['tags']['sources'][ $role ] = sanitize_text_field( (string) $config['fluentcrm']['tags']['sources'][ $role ] );
		}

		$config['exports']['all_leads']['enabled'] = ! empty( $config['exports']['all_leads']['enabled'] );
		$config['exports']['all_leads']['sheet_tab'] = sanitize_text_field( (string) $config['exports']['all_leads']['sheet_tab'] );

		$config['exports']['qualified_leads']['enabled'] = ! empty( $config['exports']['qualified_leads']['enabled'] );
		$config['exports']['qualified_leads']['conversion_action'] = sanitize_text_field( (string) $config['exports']['qualified_leads']['conversion_action'] );
		$config['exports']['qualified_leads']['date_field'] = sanitize_key( (string) $config['exports']['qualified_leads']['date_field'] );
		$config['exports']['qualified_leads']['conversion_value'] = sanitize_text_field( (string) $config['exports']['qualified_leads']['conversion_value'] );
		$config['exports']['qualified_leads']['plugin_side_sha256'] = ! empty( $config['exports']['qualified_leads']['plugin_side_sha256'] );

		$config['exports']['won_jobs']['enabled'] = ! empty( $config['exports']['won_jobs']['enabled'] );
		$config['exports']['won_jobs']['sheet_tab'] = sanitize_text_field( (string) $config['exports']['won_jobs']['sheet_tab'] );
		$config['exports']['won_jobs']['conversion_action'] = sanitize_text_field( (string) $config['exports']['won_jobs']['conversion_action'] );
		$config['exports']['won_jobs']['value_field'] = sanitize_key( (string) $config['exports']['won_jobs']['value_field'] );
		$config['exports']['won_jobs']['date_field'] = sanitize_key( (string) $config['exports']['won_jobs']['date_field'] );
		$config['exports']['won_jobs']['plugin_side_sha256'] = ! empty( $config['exports']['won_jobs']['plugin_side_sha256'] );

		$config['exports']['lost_leads']['enabled'] = false; // 0.5.1 retires Lost_* worksheet exports.
		$config['exports']['lost_leads']['date_field'] = sanitize_key( (string) $config['exports']['lost_leads']['date_field'] );
		$config['exports']['lost_leads']['conversion_value'] = sanitize_text_field( (string) $config['exports']['lost_leads']['conversion_value'] );
		$config['exports']['lost_leads']['plugin_side_sha256'] = ! empty( $config['exports']['lost_leads']['plugin_side_sha256'] );
		foreach ( array( 'wrong_price', 'wrong_product', 'wrong_service', 'wrong_area', 'wrong_attitude' ) as $reason ) {
			$config['exports']['lost_leads']['reasons'][ $reason ]['sheet_tab'] = sanitize_text_field( (string) $config['exports']['lost_leads']['reasons'][ $reason ]['sheet_tab'] );
			$config['exports']['lost_leads']['reasons'][ $reason ]['conversion_action'] = sanitize_text_field( (string) $config['exports']['lost_leads']['reasons'][ $reason ]['conversion_action'] );
		}

		$config['google_sheets']['enabled'] = ! empty( $config['google_sheets']['enabled'] );
		$config['google_sheets']['spreadsheet_id'] = sanitize_text_field( (string) $config['google_sheets']['spreadsheet_id'] );
		$config['google_sheets']['credentials_path'] = sanitize_text_field( (string) $config['google_sheets']['credentials_path'] );
		$config['google_sheets']['value_input_option'] = 'RAW';

		$config['consent']['required_policy_version'] = sanitize_text_field( (string) $config['consent']['required_policy_version'] );
		$config['consent']['require_ad_user_data'] = true;
		$config['diagnostics']['test_profile_guards'] = false;
		$config['diagnostics']['cron_expected_minutes'] = max( 1, absint( isset( $config['diagnostics']['cron_expected_minutes'] ) ? $config['diagnostics']['cron_expected_minutes'] : 10 ) );

		return $config;
	}
}
