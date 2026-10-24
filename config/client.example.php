<?php
/**
 * SiteEngine Ads Bridge client config template — schema v2.
 *
 * Copy this file to a protected external location and define SEAB_CONFIG_FILE
 * in wp-config.php, or copy it to config/client.php. Replace every CHANGE_ME.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'schema_version' => 2,

	'client'         => array(
		'slug'                   => 'CHANGE_ME',
		'name'                   => 'CHANGE_ME',
		'short_name'             => 'CHANGE_ME',
		'timezone'               => 'CHANGE_ME',
		'currency'               => 'USD',
		'google_ads_customer_id' => 'CHANGE_ME',
	),

	'fluent_forms'   => array(
		'form_id' => 0,
		'fields'  => array(
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
			'se_cid'          => 'se_cid',
			'se_agid'         => 'se_agid',
			'se_adid'         => 'se_adid',
			'se_kw'           => 'se_kw',
			'se_mt'           => 'se_mt',
			'se_net'          => 'se_net',
			'se_dev'          => 'se_dev',
			'se_loc'          => 'se_loc',
			'referrer_url'    => 'referrer_url',
			'page_title'      => 'page_title',
			'ip_address'      => 'ip_address',
			'user_agent'      => 'user_agent',
			'cookie_value'    => 'cookie_value',
			'transaction_id'  => 'transaction_id',
			'submission_id'   => 'submission_id',
		),
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
		// Legacy lost_leads settings are intentionally omitted in 0.5.1.
		// Unqualified is retained in All_Leads and is not a Google conversion.
	),

	'google_sheets'  => array(
		'enabled'            => false,
		'spreadsheet_id'     => 'CHANGE_ME',
		'credentials_path'   => '/home/CHANGE_ME/.secrets/se-ads-sa.json',
		'value_input_option' => 'RAW',
	),

	'consent'        => array(
		'required_policy_version' => 'CHANGE_ME',
		'require_ad_user_data'    => true,
	),

	'diagnostics'    => array(
		'test_profile_guards'  => false,
		'cron_expected_minutes' => 10,
	),
);
