<?php
/**
 * SiteEngine Ads Bridge active client config — schema v2.
 *
 * Practice profile: Lead Engine / GeneratePress.
 * Sheets are the only export sink. Google Ads API upload is disabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'schema_version' => 2,

	'client'         => array(
		'slug'                   => 'acceptance-test',
		'name'                   => 'Acceptance Test',
		'short_name'             => 'Test',
		'timezone'               => 'America/Chicago',
		'currency'               => 'USD',
		'google_ads_customer_id' => '123-456-7890',
	),

	'fluent_forms'   => array(
		'form_id' => 3,
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
			'enabled'                  => true,
			'unqualified_reason_field' => 'customer_rating',
			'unqualified_at_field'     => 'unqualified_at',
			'stale_new_days'           => 3,
		),
	),

	'exports'        => array(
		'all_leads'  => array(
			'enabled'          => true,
			'sheet_tab'        => 'All_Leads',
			'header_overrides' => array(),
		),
		'qualified_leads' => array(
			'enabled'            => true,
			'conversion_action'  => 'SE | Qualified Lead',
			'date_field'         => 'qualified_at',
			'conversion_value'   => '250.00',
			'plugin_side_sha256' => true,
			'header_overrides'   => array(),
		),
		'won_jobs'   => array(
			'enabled'            => true,
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
				'wrong_price'    => array(
					'sheet_tab'         => 'Lost_Wrong_Price',
				),
				'wrong_product'  => array(
					'sheet_tab'         => 'Lost_Wrong_Product',
				),
				'wrong_service'  => array(
					'sheet_tab'         => 'Lost_Wrong_Service',
				),
				'wrong_area'     => array(
					'sheet_tab'         => 'Lost_Wrong_Area',
				),
				'wrong_attitude' => array(
					'sheet_tab'         => 'Lost_Wrong_Attitude',
				),
			),
		),
	),

	'google_sheets'  => array(
		'enabled'            => true,
		'spreadsheet_id'     => 'test-spreadsheet-id',
		'credentials_path'   => '/tmp/seab-test-service-account.json',
		'value_input_option' => 'RAW',
	),

	'consent'        => array(
		'required_policy_version' => '2026-05-08-v1',
		'require_ad_user_data'    => true,
	),

	'diagnostics'    => array(
		'test_profile_guards'  => false,
		'cron_expected_minutes' => 10,
	),
);
