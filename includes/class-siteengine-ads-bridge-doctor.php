<?php
/**
 * Doctor checks for SiteEngine Ads Bridge (schema v2 aware).
 *
 * Read-only. Never mutates FluentCRM, Fluent Forms, Sheets, or config.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteEngine_Ads_Bridge_Doctor {

	const STATUS_PASS    = 'PASS';
	const STATUS_WARN    = 'WARN';
	const STATUS_FAIL    = 'FAIL';
	const STATUS_PENDING = 'PENDING';

	/**
	 * Config instance.
	 *
	 * @var SiteEngine_Ads_Bridge_Config
	 */
	private $config;

	/**
	 * Results.
	 *
	 * @var array
	 */
	private $results = array();

	/**
	 * Constructor.
	 *
	 * @param SiteEngine_Ads_Bridge_Config|null $config Config instance.
	 */
	public function __construct( $config = null ) {
		$this->config = $config instanceof SiteEngine_Ads_Bridge_Config ? $config : SiteEngine_Ads_Bridge_Config::instance();
	}

	/**
	 * Run all checks.
	 *
	 * @return array
	 */
	public function run() {
		$this->results = array();

		$this->check_schema_and_load();
		$this->check_core_config_values();
		$this->check_wordpress_timezone();
		$this->check_dependencies();
		$this->check_lifecycle_configuration();
		$this->check_fluentcrm_lists_tags_fields();
		$this->check_inquiry_layer();
		$this->check_export_families();
		$this->check_google_sheets_config();
		$this->check_cron_health();
		$this->check_consent_policy();
		$this->check_fluent_forms_field_contract();
		$this->check_submission_id_pipeline();
		$this->check_runtime_invariants();

		if ( true === (bool) $this->config->get( 'diagnostics.test_profile_guards', false ) ) {
			$this->run_test_profile_guards();
		}

		return $this->results;
	}

	/**
	 * Get PASS/FAIL summary.
	 *
	 * @return array
	 */
	public function summary() {
		$summary = array(
			self::STATUS_PASS    => 0,
			self::STATUS_WARN    => 0,
			self::STATUS_FAIL    => 0,
			self::STATUS_PENDING => 0,
		);

		foreach ( $this->results as $result ) {
			if ( isset( $summary[ $result['status'] ] ) ) {
				$summary[ $result['status'] ]++;
			}
		}

		return $summary;
	}

	/**
	 * Add a result row.
	 *
	 * @param string $status  Status.
	 * @param string $check   Check label.
	 * @param string $message Message.
	 *
	 * @return void
	 */
	private function add_result( $status, $check, $message ) {
		$this->results[] = array(
			'status'  => $status,
			'check'   => $check,
			'message' => $message,
		);
	}

	/**
	 * Whether the loaded config is in legacy compatibility mode.
	 *
	 * @return bool
	 */
	private function is_legacy() {
		return 'legacy' === $this->config->schema_mode();
	}

	/**
	 * Schema mode, declared version, load errors, compatibility mode,
	 * validation errors, and adapter deprecation notices.
	 *
	 * @return void
	 */
	private function check_schema_and_load() {
		$mode = $this->config->schema_mode();

		if ( 'invalid' === $mode ) {
			foreach ( $this->config->get_load_errors() as $error ) {
				$this->add_result( self::STATUS_FAIL, 'Config schema', $error );
			}
			return;
		}

		if ( 'v2' === $mode ) {
			$this->add_result(
				self::STATUS_PASS,
				'Config schema',
				sprintf( 'Config declares supported schema_version %d (source: %s).', SiteEngine_Ads_Bridge_Config::SUPPORTED_SCHEMA_VERSION, $this->config->config_file_path() )
			);
		} else {
			$this->add_result(
				self::STATUS_WARN,
				'Config schema',
				'LEGACY COMPATIBILITY MODE: config/client.php has no schema_version and is resolved through the fixed v1 mapping. Migrate to schema_version 2 (see config/client.example.php).'
			);
		}

		foreach ( $this->config->deprecations() as $notice ) {
			$this->add_result( self::STATUS_WARN, 'Config compatibility', $notice );
		}

		$errors = $this->config->validate();
		if ( empty( $errors ) ) {
			$this->add_result( self::STATUS_PASS, 'Config shape', 'Config passed structural validation for its schema mode.' );
		} else {
			foreach ( $errors as $error ) {
				$this->add_result( self::STATUS_FAIL, 'Config shape', $error );
			}
		}
	}


	/**
	 * Inquiry-aware attribution persistence. Missing table is a warning rather
	 * than a failure so 0.6.0 remains backward-compatible in single-inquiry mode.
	 * CRM Ops inquiry integration requires persistence plus its own acceptance.
	 *
	 * @return void
	 */
	private function check_inquiry_layer() {
		if ( ! class_exists( 'SiteEngine_Ads_Bridge_Inquiries' ) ) {
			$this->add_result( self::STATUS_FAIL, 'Inquiry attribution layer', 'Ads Bridge inquiry service class is unavailable.' );
			return;
		}
		$status = SiteEngine_Ads_Bridge_Inquiries::status_summary();
		if ( empty( $status['table_exists'] ) ) {
			$this->add_result(
				self::STATUS_WARN,
				'Inquiry attribution layer',
				'Ads inquiry table is not installed. Legacy exports remain supported; CRM Ops repeat-inquiry integration requires migration and acceptance. Preview with: wp se-ads inquiry-admin migrate'
			);
			return;
		}
		if ( empty( $status['schema_ready'] ) ) {
			$this->add_result( self::STATUS_WARN, 'Inquiry attribution layer', 'Inquiry recovery schema migration is required. Legacy exports remain supported. Preview with: wp se-ads inquiry-admin migrate' );
			return;
		}
		$this->add_result(
			self::STATUS_PASS,
			'Inquiry attribution layer',
			sprintf(
				'Inquiry table ready (schema %s): %d total, %d active, %d prepared/activating, %d archived.',
				(string) $status['schema_version'],
				(int) $status['rows'],
				(int) $status['active'],
				(int) $status['prepared'],
				(int) $status['archived']
			)
		);
	}

	/**
	 * Core scalar value checks: timezone validity, CID format, currency
	 * format, and placeholder detection for legacy configs (v2 placeholder
	 * rejection already lives in validate()).
	 *
	 * @return void
	 */
	private function check_core_config_values() {
		$timezone = $this->config->get( 'client.timezone', '' );

		if ( is_string( $timezone ) && '' !== trim( $timezone ) && in_array( $timezone, timezone_identifiers_list(), true ) ) {
			$this->add_result(
				self::STATUS_PASS,
				'Config timezone',
				sprintf( 'Configured timezone is a valid IANA timezone: %s', $timezone )
			);
		} else {
			$this->add_result(
				self::STATUS_FAIL,
				'Config timezone',
				'Configured timezone is missing or is not a valid IANA timezone.'
			);
		}

		$cid = $this->config->get( 'client.google_ads_customer_id', '' );

		if ( is_string( $cid ) && preg_match( '/^\d{3}-\d{3}-\d{4}$/', $cid ) ) {
			$this->add_result(
				self::STATUS_PASS,
				'Google Ads CID',
				sprintf( 'Configured Google Ads CID is well-formed: %s', $cid )
			);
		} else {
			$this->add_result(
				self::STATUS_FAIL,
				'Google Ads CID',
				'Configured Google Ads CID is missing or malformed. Expected format: 000-000-0000.'
			);
		}

		$currency = $this->config->get( 'client.currency', '' );

		if ( is_string( $currency ) && preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$this->add_result(
				self::STATUS_PASS,
				'Currency',
				sprintf( 'Configured currency is present: %s', $currency )
			);
		} else {
			$this->add_result(
				self::STATUS_FAIL,
				'Currency',
				'Configured currency is missing or malformed. Expected ISO-style three-letter uppercase code.'
			);
		}

		if ( $this->is_legacy() ) {
			$scalars = array(
				'client.slug',
				'client.name',
				'client.short_name',
				'client.google_ads_customer_id',
				'google_sheets.spreadsheet_id',
				'google_sheets.credentials_path',
			);
			foreach ( $scalars as $key ) {
				$value = $this->config->get( $key, '' );
				if ( SiteEngine_Ads_Bridge_Config::looks_like_placeholder( $value ) ) {
					$this->add_result(
						self::STATUS_FAIL,
						'Placeholder value',
						sprintf( '%s still contains a placeholder value ("%s"). Configure the real value.', $key, (string) $value )
					);
				}
			}
		}
	}

	/**
	 * WordPress-vs-config timezone comparison and Google Ads timezone
	 * operator reminder.
	 *
	 * @return void
	 */
	private function check_wordpress_timezone() {
		$config_timezone = $this->config->get( 'client.timezone', '' );
		$wp_timezone     = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '';

		if ( '' === $wp_timezone ) {
			$this->add_result(
				self::STATUS_WARN,
				'WordPress timezone',
				'Could not resolve WordPress timezone via wp_timezone_string().'
			);
			return;
		}

		if ( $config_timezone === $wp_timezone ) {
			$this->add_result(
				self::STATUS_PASS,
				'WordPress timezone',
				sprintf( 'Config timezone matches WordPress timezone: %s', $wp_timezone )
			);
		} else {
			$this->add_result(
				self::STATUS_WARN,
				'WordPress timezone',
				sprintf(
					'Config timezone (%s) does not match WordPress timezone (%s). Confirm which timezone should drive conversion timestamps.',
					(string) $config_timezone,
					(string) $wp_timezone
				)
			);
		}

		$this->add_result(
			self::STATUS_WARN,
			'Google Ads timezone',
			'Operator must confirm the Google Ads account timezone matches the configured conversion timezone before live Data Manager imports.'
		);
	}

	/**
	 * Dependency presence checks.
	 *
	 * @return void
	 */
	private function check_dependencies() {
		$this->check_fluent_forms();
		$this->check_fluentcrm();
		$this->check_siteengine_consent();
		$this->check_siteengine_business_data();
		$this->check_siteengine_base();
	}

	/**
	 * Check Fluent Forms presence.
	 *
	 * @return void
	 */
	private function check_fluent_forms() {
		if (
			defined( 'FLUENTFORM_VERSION' )
			|| function_exists( 'wpFluentForm' )
			|| class_exists( '\FluentForm\App\App' )
			|| $this->is_plugin_active_safe( 'fluentform/fluentform.php' )
		) {
			$this->add_result(
				self::STATUS_PASS,
				'Fluent Forms',
				'Fluent Forms appears to be active.'
			);
			return;
		}

		$this->add_result(
			self::STATUS_FAIL,
			'Fluent Forms',
			'Fluent Forms was not detected.'
		);
	}

	/**
	 * Check FluentCRM presence.
	 *
	 * @return void
	 */
	private function check_fluentcrm() {
		if (
			defined( 'FLUENTCRM' )
			|| defined( 'FLUENTCRM_VERSION' )
			|| function_exists( 'FluentCrmApi' )
			|| class_exists( '\FluentCrm\App\App' )
			|| $this->is_plugin_active_safe( 'fluent-crm/fluent-crm.php' )
		) {
			$this->add_result(
				self::STATUS_PASS,
				'FluentCRM',
				'FluentCRM appears to be active.'
			);
			return;
		}

		$this->add_result(
			self::STATUS_FAIL,
			'FluentCRM',
			'FluentCRM was not detected.'
		);
	}

	/**
	 * Check SiteEngine Consent presence.
	 *
	 * @return void
	 */
	private function check_siteengine_consent() {
		if (
			defined( 'SE_CONSENT_VERSION' )
			|| class_exists( 'SiteEngine_Consent' )
			|| $this->table_exists( 'se_consent_receipts' )
		) {
			$this->add_result(
				self::STATUS_PASS,
				'SiteEngine Consent',
				'SiteEngine Consent appears to be present.'
			);
			return;
		}

		$this->add_result(
			self::STATUS_FAIL,
			'SiteEngine Consent',
			'SiteEngine Consent was not detected.'
		);
	}

	/**
	 * Check SiteEngine Business Data presence.
	 *
	 * @return void
	 */
	private function check_siteengine_business_data() {
		if ( function_exists( 'siteengine_get' ) ) {
			$this->add_result(
				self::STATUS_PASS,
				'SiteEngine Business Data',
				'Shared business data is available through SiteEngine Base.'
			);
			return;
		}

		$this->add_result(
			self::STATUS_FAIL,
			'SiteEngine Business Data',
			'SiteEngine Base shared configuration API was not detected.'
		);
	}

	/**
	 * SiteEngine Base is optional. The provider interface is inert in this
	 * build; Base absence is never fatal and no detection is guessed.
	 *
	 * @return void
	 */
	private function check_siteengine_base() {
		if ( function_exists( 'siteengine_get' ) && class_exists( 'SiteEngine_Base_Module_Registry' ) ) {
			$this->add_result(
				self::STATUS_PASS,
				'SiteEngine Base',
				'SiteEngine Base shared configuration and module registry are available.'
			);
			return;
		}

		$this->add_result(
			self::STATUS_FAIL,
			'SiteEngine Base',
			'SiteEngine Base 2.2 or newer is required for Ads Bridge 0.6.0 administration.'
		);
	}

	/**
	 * Lifecycle configuration completeness: all four authoritative lifecycle
	 * tags must be present, non-empty, non-placeholder, and unique.
	 * FAIL in v2 mode; WARN in legacy compatibility mode (the legacy schema
	 * predates lifecycle tags and must gain them during v2 migration).
	 *
	 * @return void
	 */
	private function check_lifecycle_configuration() {
		$severity = $this->is_legacy() ? self::STATUS_WARN : self::STATUS_FAIL;
		$roles    = SiteEngine_Ads_Bridge_Config::lifecycle_roles();

		$configured = array();
		$missing    = array();
		$seen       = array();
		$dupes      = array();

		foreach ( $roles as $role ) {
			$value = $this->config->get( 'fluentcrm.tags.lifecycle.' . $role, '' );
			if ( ! is_string( $value ) || '' === trim( $value ) || SiteEngine_Ads_Bridge_Config::looks_like_placeholder( $value ) ) {
				$missing[] = $role;
				continue;
			}
			$value = trim( $value );
			$configured[ $role ] = $value;
			if ( isset( $seen[ $value ] ) ) {
				$dupes[] = sprintf( '"%s" (%s, %s)', $value, $seen[ $value ], $role );
			} else {
				$seen[ $value ] = $role;
			}
		}

		if ( empty( $missing ) && empty( $dupes ) ) {
			$this->add_result(
				self::STATUS_PASS,
				'Lifecycle tags',
				sprintf( 'All %d authoritative lifecycle tags are configured and unique: %s', count( $roles ), implode( ', ', array_values( $configured ) ) )
			);
		} else {
			foreach ( $missing as $role ) {
				$this->add_result(
					$severity,
					'Lifecycle tags',
					sprintf( 'Missing authoritative lifecycle tag: fluentcrm.tags.lifecycle.%s.%s', $role, $this->is_legacy() ? ' Legacy configs predate lifecycle tags; add them during v2 migration.' : '' )
				);
			}
			foreach ( $dupes as $dupe ) {
				$this->add_result(
					self::STATUS_FAIL,
					'Lifecycle tags',
					sprintf( 'Duplicate lifecycle tag name across roles: %s. Each role needs a distinct tag.', $dupe )
				);
			}
		}

		// Won selector authority: candidate discovery and eligibility use the
		// configured lifecycle tag. Legacy lead_status values are never authoritative.
		$this->add_result(
			self::STATUS_PASS,
			'Won selector',
			sprintf(
				'Won selector authority is the configured lifecycle tag "%s" (active). lead_status is not read as a selector or eligibility authority.',
				(string) $this->config->get( 'fluentcrm.tags.lifecycle.won', '(unconfigured)' )
			)
		);

		$automation = (bool) $this->config->get( 'fluentcrm.lifecycle_automation.enabled', false );
		$this->add_result(
			$automation ? self::STATUS_PASS : self::STATUS_WARN,
			'Lifecycle compliance automation',
			$automation
				? 'Enabled: lifecycle states are kept mutually exclusive and milestone timestamps are stamped automatically. Invoice revenue remains manual.'
				: 'Disabled: lifecycle tags and milestone timestamps depend entirely on CRM/operator workflows.'
		);
	}

	/**
	 * Validate FluentCRM mapped lists, lifecycle/source tag existence, and
	 * custom-field readiness. Read-only; creates and mutates nothing.
	 *
	 * @return void
	 */
	private function check_fluentcrm_lists_tags_fields() {
		$this->check_fluentcrm_mapped_roles(
			'FluentCRM list map',
			'lists',
			$this->get_fluentcrm_named_collection_from_api( 'lists' ),
			(array) $this->config->get( 'fluentcrm.lists', array() )
		);

		$available_tags = $this->get_fluentcrm_named_collection_from_api( 'tags' );

		$this->check_fluentcrm_source_tags( $available_tags );

		$this->check_fluentcrm_lifecycle_tags_exist( $available_tags );

		$this->check_fluentcrm_custom_fields();
	}

	/**
	 * Source tags are OPTIONAL and informational only. They are never a
	 * lifecycle authority, never an export gate, and sites may keep source
	 * classification in a custom field instead of tags. Nothing here can
	 * emit FAIL: absent block = informational, configured-but-unverifiable
	 * or configured-but-missing = WARN.
	 *
	 * @param array|null $available_tags Normalized tag collection or null.
	 *
	 * @return void
	 */
	private function check_fluentcrm_source_tags( $available_tags ) {
		$sources = $this->config->get( 'fluentcrm.tags.sources', array() );

		if ( ! is_array( $sources ) || empty( $sources ) ) {
			$this->add_result(
				self::STATUS_PASS,
				'FluentCRM source tags (optional)',
				'No source tags configured. Source tags are optional and informational only; this site may store source classification in a custom field. Not a failure; continuing.'
			);
			return;
		}

		if ( null === $available_tags ) {
			$this->add_result(
				self::STATUS_WARN,
				'FluentCRM source tags (optional)',
				'Source tags are configured but FluentCRM tags could not be inspected through FluentCrmApi("tags")->all(). Informational feature; verification skipped.'
			);
			return;
		}

		$available_names = $this->normalize_item_names( $available_tags );

		foreach ( $sources as $role => $mapped_name ) {
			if ( ! is_string( $mapped_name ) || '' === trim( $mapped_name ) ) {
				$this->add_result(
					self::STATUS_WARN,
					'FluentCRM source tags (optional)',
					sprintf( 'Source role unmapped: %s. Optional; set or remove the mapping.', $role )
				);
				continue;
			}

			$mapped_name = trim( $mapped_name );

			if ( in_array( $mapped_name, $available_names, true ) ) {
				$this->add_result(
					self::STATUS_PASS,
					'FluentCRM source tags (optional)',
					sprintf( 'Source role %s resolves to existing tag: %s (informational only; never a lifecycle authority or export gate).', $role, $mapped_name )
				);
				continue;
			}

			$this->add_result(
				self::STATUS_WARN,
				'FluentCRM source tags (optional)',
				sprintf( 'Configured source tag missing in FluentCRM: %s (role %s). Optional informational tag; do not duplicate custom-field source data into tags to satisfy this check.', $mapped_name, $role )
			);
		}
	}

	/**
	 * Verify each configured lifecycle tag resolves to an existing FluentCRM
	 * tag. Missing CRM tags are WARN (they are created during CRM setup, and
	 * stable ID resolution is a Phase 3 concern); unreadable API is FAIL.
	 *
	 * @param array|null $available_tags Normalized tag collection or null.
	 *
	 * @return void
	 */
	private function check_fluentcrm_lifecycle_tags_exist( $available_tags ) {
		$roles = SiteEngine_Ads_Bridge_Config::lifecycle_roles();

		$configured = array();
		foreach ( $roles as $role ) {
			$value = $this->config->get( 'fluentcrm.tags.lifecycle.' . $role, '' );
			if ( is_string( $value ) && '' !== trim( $value ) && ! SiteEngine_Ads_Bridge_Config::looks_like_placeholder( $value ) ) {
				$configured[ $role ] = trim( $value );
			}
		}

		if ( empty( $configured ) ) {
			return; // Completeness already reported by check_lifecycle_configuration().
		}

		if ( null === $available_tags ) {
			$this->add_result(
				self::STATUS_FAIL,
				'FluentCRM lifecycle tags',
				'Could not inspect FluentCRM tags through FluentCrmApi("tags")->all(); cannot verify lifecycle tags exist.'
			);
			return;
		}

		$available_names = $this->normalize_item_names( $available_tags );
		$missing         = array();

		foreach ( $configured as $role => $name ) {
			if ( ! in_array( $name, $available_names, true ) ) {
				$missing[ $role ] = $name;
			}
		}

		if ( empty( $missing ) ) {
			$this->add_result(
				self::STATUS_PASS,
				'FluentCRM lifecycle tags',
				sprintf( 'All %d configured lifecycle tags exist in FluentCRM.', count( $configured ) )
			);
			return;
		}

		foreach ( $missing as $role => $name ) {
			$this->add_result(
				self::STATUS_WARN,
				'FluentCRM lifecycle tags',
				sprintf( 'Configured lifecycle tag for role %s does not exist in FluentCRM yet: "%s". Create it in FluentCRM (Ads Bridge never creates tags).', $role, $name )
			);
		}
	}

	/**
	 * Check mapped FluentCRM roles against actual list/tag collections.
	 *
	 * Empty or missing mappings are WARN, not FAIL. Missing mapped objects are FAIL.
	 *
	 * @param string     $check_label    Check label.
	 * @param string     $collection_key Collection key.
	 * @param array|null $available_data Available collection.
	 * @param array      $role_map       Configured role map.
	 *
	 * @return void
	 */
	private function check_fluentcrm_mapped_roles( $check_label, $collection_key, $available_data, $role_map ) {
		if ( null === $available_data ) {
			$this->add_result(
				self::STATUS_FAIL,
				$check_label,
				sprintf( 'Could not inspect FluentCRM %s through FluentCrmApi("%s")->all().', $collection_key, $collection_key )
			);
			return;
		}

		if ( ! is_array( $role_map ) || empty( $role_map ) ) {
			$this->add_result(
				self::STATUS_FAIL,
				$check_label,
				sprintf( 'Config map for FluentCRM %s is missing or empty.', $collection_key )
			);
			return;
		}

		$available_names = $this->normalize_item_names( $available_data );

		foreach ( $role_map as $role => $mapped_name ) {
			if ( ! is_string( $mapped_name ) || '' === trim( $mapped_name ) ) {
				$this->add_result(
					self::STATUS_WARN,
					$check_label,
					sprintf( 'Role unmapped: %s. Set the mapping when the operator decides the correct CRM object.', $role )
				);
				continue;
			}

			$mapped_name = trim( $mapped_name );

			if ( in_array( $mapped_name, $available_names, true ) ) {
				$this->add_result(
					self::STATUS_PASS,
					$check_label,
					sprintf( 'Role %s resolves to existing %s: %s', $role, $collection_key, $mapped_name )
				);
				continue;
			}

			$this->add_result(
				self::STATUS_FAIL,
				$check_label,
				sprintf( 'Mapped role %s points to missing %s: %s', $role, $collection_key, $mapped_name )
			);
		}
	}

	/**
	 * Get FluentCRM lists/tags through confirmed API path.
	 *
	 * Confirmed path on the GeneratePress test site:
	 * FluentCrmApi('lists')->all()
	 * FluentCrmApi('tags')->all()
	 *
	 * @param string $resource Resource key.
	 *
	 * @return array|null
	 */
	private function get_fluentcrm_named_collection_from_api( $resource ) {
		if ( ! function_exists( 'FluentCrmApi' ) ) {
			return null;
		}

		try {
			$api = FluentCrmApi( $resource );

			if ( ! is_object( $api ) ) {
				return null;
			}

			$result = $api->all();
		} catch ( Throwable $e ) {
			return null;
		}

		return $this->normalize_collection_result( $result );
	}

	/**
	 * Custom-field check.
	 *
	 * Confirmed read path on the GeneratePress test site:
	 * fluentcrm_get_custom_contact_fields() returns an array of field-definition
	 * arrays, each containing a 'slug'. This is read-only and creates nothing.
	 *
	 * @return void
	 */
	private function check_fluentcrm_custom_fields() {
		$required_fields = array(
			'invoiced_amount',
			'closed_date',
			'submission_id',
			'ads_export_status',
			'ads_exported_at',
			'ads_conversion_value_frozen',
			'ads_export_error',
		);

		if ( (bool) $this->config->get( 'exports.qualified_leads.enabled', false ) ) {
			$required_fields[] = (string) $this->config->get( 'exports.qualified_leads.date_field', 'qualified_at' );
		}
		if ( (bool) $this->config->get( 'exports.won_jobs.enabled', false ) ) {
			$required_fields[] = (string) $this->config->get( 'exports.won_jobs.value_field', 'invoiced_amount' );
			$required_fields[] = (string) $this->config->get( 'exports.won_jobs.date_field', 'closed_date' );
		}
		if ( (bool) $this->config->get( 'fluentcrm.lifecycle_automation.enabled', false ) ) {
			$required_fields[] = (string) $this->config->get( 'fluentcrm.lifecycle_automation.unqualified_at_field', 'unqualified_at' );
			$required_fields[] = (string) $this->config->get( 'fluentcrm.lifecycle_automation.unqualified_reason_field', 'customer_rating' );
		}
		$required_fields = array_values( array_unique( array_filter( $required_fields ) ) );

		if ( ! function_exists( 'fluentcrm_get_custom_contact_fields' ) ) {
			$this->add_result(
				self::STATUS_FAIL,
				'FluentCRM custom fields',
				'FluentCRM custom-field API not available (fluentcrm_get_custom_contact_fields() not found).'
			);
			return;
		}

		$field_definitions = fluentcrm_get_custom_contact_fields();
		$field_slugs       = array();

		if ( is_array( $field_definitions ) ) {
			foreach ( $field_definitions as $field_definition ) {
				if ( is_array( $field_definition ) && isset( $field_definition['slug'] ) && is_string( $field_definition['slug'] ) ) {
					$slug = trim( $field_definition['slug'] );

					if ( '' !== $slug ) {
						$field_slugs[] = $slug;
					}
				}
			}
		}

		$field_slugs = array_values( array_unique( $field_slugs ) );

		$missing = array();

		foreach ( $required_fields as $required_field ) {
			if ( ! in_array( $required_field, $field_slugs, true ) ) {
				$missing[] = $required_field;
			}
		}

		if ( empty( $missing ) ) {
			$this->add_result(
				self::STATUS_PASS,
				'FluentCRM custom fields',
				'All required FluentCRM contact custom fields are present.'
			);
			return;
		}

		foreach ( $missing as $missing_field ) {
			$this->add_result(
				self::STATUS_FAIL,
				'FluentCRM custom fields',
				sprintf( 'Missing FluentCRM contact custom field: %s', $missing_field )
			);
		}
	}

	/**
	 * Export-family configuration: Qualified/Converted completeness, the
	 * operational All_Leads ledger, and physical tab uniqueness. Lost_*
	 * worksheets are retired in 0.5.1; Unqualified is an internal lifecycle
	 * outcome and is intentionally absent from Google conversion imports.
	 *
	 * @return void
	 */
	private function check_export_families() {
		$tabs = array();

		$google_tab = trim( (string) $this->config->get( 'exports.won_jobs.sheet_tab', 'Google_Ads_Import' ) );

		// Qualified lead stage.
		$q_enabled = (bool) $this->config->get( 'exports.qualified_leads.enabled', false );
		$q_action  = trim( (string) $this->config->get( 'exports.qualified_leads.conversion_action', 'SE | Qualified Lead' ) );
		$q_date    = trim( (string) $this->config->get( 'exports.qualified_leads.date_field', 'qualified_at' ) );
		$q_value   = trim( (string) $this->config->get( 'exports.qualified_leads.conversion_value', '' ) );
		$q_bad     = '' === $google_tab || '' === $q_action || '' === $q_date || SiteEngine_Ads_Bridge_Config::looks_like_placeholder( $q_action ) || ( '' !== $q_value && ( ! is_numeric( $q_value ) || (float) $q_value < 0 ) );
		$this->add_result(
			$q_bad && $q_enabled ? self::STATUS_FAIL : ( $q_bad ? self::STATUS_WARN : self::STATUS_PASS ),
			'Export family: qualified_leads',
			$q_bad
				? sprintf( 'qualified_leads is incomplete (shared tab: "%s", action: "%s", timestamp field: "%s", proxy value: "%s").', $google_tab, $q_action, $q_date, $q_value )
				: sprintf( 'qualified_leads %s: shared tab "%s", conversion action "%s", timestamp field "%s", proxy value %s.', $q_enabled ? 'ENABLED' : 'declared (disabled)', $google_tab, $q_action, $q_date, '' === $q_value ? '(none)' : '"' . $q_value . '"' )
		);

		// Converted lead / Won stage.
		$won_enabled = (bool) $this->config->get( 'exports.won_jobs.enabled', false );
		$won_action  = trim( (string) $this->config->get( 'exports.won_jobs.conversion_action', '' ) );
		$won_bad     = '' === $google_tab || '' === $won_action || SiteEngine_Ads_Bridge_Config::looks_like_placeholder( $won_action );
		$this->add_result(
			$won_bad && $won_enabled ? self::STATUS_FAIL : ( $won_bad ? self::STATUS_WARN : self::STATUS_PASS ),
			'Export family: converted_leads',
			$won_bad
				? sprintf( 'Converted Lead/Won export is incomplete (shared tab: "%s", conversion_action: "%s").', $google_tab, $won_action )
				: sprintf( 'converted_leads %s: shared tab "%s", conversion action "%s", value field "%s", date field "%s".', $won_enabled ? 'ENABLED' : 'declared (disabled)', $google_tab, $won_action, (string) $this->config->get( 'exports.won_jobs.value_field', 'invoiced_amount' ), (string) $this->config->get( 'exports.won_jobs.date_field', 'closed_date' ) )
		);
		if ( ( $q_enabled || $won_enabled ) && '' !== $google_tab ) {
			$tabs['google_ads_import'] = $google_tab;
			$this->add_result( self::STATUS_PASS, 'Golden Account import tab', sprintf( 'Qualified + Converted Lead share "%s" and are separated by the Conversion action column. Run `wp se-ads provision-sheets` to preview/verify this worksheet and `--apply` only after review.', $google_tab ) );
		}

		// All leads operational export.
		$al_enabled = (bool) $this->config->get( 'exports.all_leads.enabled', false );
		$al_tab     = trim( (string) $this->config->get( 'exports.all_leads.sheet_tab', '' ) );
		if ( '' === $al_tab ) {
			$this->add_result( $al_enabled ? self::STATUS_FAIL : self::STATUS_WARN, 'Export family: all_leads', 'all_leads has no sheet_tab configured.' );
		} else {
			$this->add_result( self::STATUS_PASS, 'Export family: all_leads', sprintf( 'all_leads %s: operational tab "%s"; raw identifiers, consent state, DNC state, and raw submission JSON are included.', $al_enabled ? 'ENABLED' : 'declared (disabled)', $al_tab ) );
			$tabs['all_leads'] = $al_tab;
		}

		$this->add_result(
			self::STATUS_PASS,
			'Unqualified outcome',
			'Unqualified leads are retained in the All_Leads lifecycle ledger and intentionally produce no Google conversion row. Legacy Lost_* worksheet settings are retired and ignored.'
		);

		$by_tab = array();
		foreach ( $tabs as $family => $tab ) {
			$by_tab[ $tab ][] = $family;
		}
		$dupes = array();
		foreach ( $by_tab as $tab => $families ) {
			if ( count( $families ) > 1 ) {
				$dupes[] = sprintf( '"%s" (%s)', $tab, implode( ', ', $families ) );
			}
		}
		if ( empty( $dupes ) ) {
			$this->add_result( self::STATUS_PASS, 'Export tab uniqueness', sprintf( 'All %d physical export tabs are distinct; Qualified/Converted intentionally share one logical Google import contract.', count( $tabs ) ) );
		} else {
			$this->add_result( self::STATUS_FAIL, 'Export tab uniqueness', 'Conflicting physical export tab definitions: ' . implode( '; ', $dupes ) );
		}
	}

	/**
	 * Google Sheets sink configuration: enabled flag, spreadsheet ID,
	 * credentials path presence + readability, value input option.
	 * Never reads or prints credential contents.
	 *
	 * @return void
	 */
	private function check_google_sheets_config() {
		$enabled        = (bool) $this->config->get( 'google_sheets.enabled', false );
		$spreadsheet_id = trim( (string) $this->config->get( 'google_sheets.spreadsheet_id', '' ) );
		$creds_path     = trim( (string) $this->config->get( 'google_sheets.credentials_path', '' ) );
		$vio            = trim( (string) $this->config->get( 'google_sheets.value_input_option', 'RAW' ) );

		if ( ! $enabled ) {
			$this->add_result(
				self::STATUS_WARN,
				'Google Sheets sink',
				'google_sheets.enabled is false. All Sheet exports (including won_jobs) fail closed until enabled.'
			);
		}

		if ( '' === $spreadsheet_id ) {
			$this->add_result(
				$enabled ? self::STATUS_FAIL : self::STATUS_WARN,
				'Google Sheets sink',
				'google_sheets.spreadsheet_id is missing.'
			);
		} else {
			$this->add_result(
				self::STATUS_PASS,
				'Google Sheets sink',
				sprintf( 'Spreadsheet ID configured: %s', $this->redact_middle( $spreadsheet_id ) )
			);
		}

		if ( '' === $creds_path ) {
			$this->add_result(
				$enabled ? self::STATUS_FAIL : self::STATUS_WARN,
				'Google Sheets credentials',
				'google_sheets.credentials_path is missing.'
			);
		} elseif ( ! is_readable( $creds_path ) ) {
			$this->add_result(
				$enabled ? self::STATUS_FAIL : self::STATUS_WARN,
				'Google Sheets credentials',
				sprintf( 'Service-account JSON is not readable at "%s". Verify existence, ownership, and permissions for the PHP user.', $creds_path )
			);
		} else {
			$this->add_result(
				self::STATUS_PASS,
				'Google Sheets credentials',
				sprintf( 'Service-account JSON is readable at "%s" (contents never inspected or printed by doctor).', $creds_path )
			);
		}

		if ( in_array( $vio, array( 'RAW', 'USER_ENTERED' ), true ) ) {
			$this->add_result(
				self::STATUS_PASS,
				'Google Sheets value input',
				sprintf( 'value_input_option is "%s".', $vio )
			);
		} else {
			$this->add_result(
				self::STATUS_FAIL,
				'Google Sheets value input',
				sprintf( 'value_input_option "%s" is invalid. Use RAW or USER_ENTERED.', $vio )
			);
		}
	}

	/**
	 * Aggregate scheduler health based on successful export-enabled --apply runs.
	 * The admin-side doctor runs under the site's web PHP, so comparing major.minor
	 * versions catches cPanel cron jobs left on an older EA-PHP binary after upgrades.
	 *
	 * @return void
	 */
	private function check_cron_health() {
		$expected = max( 1, (int) $this->config->get( 'diagnostics.cron_expected_minutes', 10 ) );
		$runtime  = get_option( 'seab_last_export_runtime', array() );
		if ( ! is_array( $runtime ) || empty( $runtime['timestamp'] ) ) {
			$this->add_result( self::STATUS_WARN, 'Scheduled export runtime', sprintf( 'No successful `export-enabled --apply` runtime has been recorded yet. Recommended schedule: every %d minutes.', $expected ) );
			return;
		}

		$age_seconds = max( 0, time() - (int) $runtime['timestamp'] );
		$age_minutes = (int) floor( $age_seconds / 60 );
		$stale_after = max( 30, $expected * 3 );
		if ( $age_minutes > $stale_after ) {
			$this->add_result( self::STATUS_WARN, 'Scheduled export freshness', sprintf( 'Last successful aggregate export was %d minutes ago; expected every %d minutes. Cron may be missing, stale, or failing.', $age_minutes, $expected ) );
		} else {
			$this->add_result( self::STATUS_PASS, 'Scheduled export freshness', sprintf( 'Last successful aggregate export was %d minutes ago; expected every %d minutes.', $age_minutes, $expected ) );
		}

		$cron_php = isset( $runtime['php_version'] ) ? (string) $runtime['php_version'] : '';
		$web_mm   = implode( '.', array_slice( explode( '.', PHP_VERSION ), 0, 2 ) );
		$cron_mm  = implode( '.', array_slice( explode( '.', $cron_php ), 0, 2 ) );
		$binary   = isset( $runtime['php_binary'] ) ? (string) $runtime['php_binary'] : '(unknown)';
		if ( '' !== $cron_php && $cron_mm !== $web_mm ) {
			$this->add_result( self::STATUS_WARN, 'Cron PHP runtime', sprintf( 'Site web PHP is %s but the last successful export ran under PHP %s (%s). Update the cPanel cron after the PHP upgrade.', PHP_VERSION, $cron_php, $binary ) );
		} else {
			$this->add_result( self::STATUS_PASS, 'Cron PHP runtime', sprintf( 'Last successful export PHP %s matches the site major.minor runtime %s. Binary: %s', '' !== $cron_php ? $cron_php : '(unknown)', $web_mm, $binary ) );
		}
	}

	/**
	 * Consent policy authority: the configured required_policy_version is
	 * authoritative. Runtime sources (SE_CONSENT_POLICY_VERSION constant,
	 * se_consent_policy_version option) are observed and compared — never
	 * silently substituted. The export-gate wiring to the config value lands
	 * in Phase 3 with fixture proof; until then a mismatch is a reported
	 * configuration/runtime mismatch.
	 *
	 * @return void
	 */
	private function check_consent_policy() {
		$required = trim( (string) $this->config->get( 'consent.required_policy_version', '' ) );

		if ( '' === $required || SiteEngine_Ads_Bridge_Config::looks_like_placeholder( $required ) ) {
			$this->add_result(
				$this->is_legacy() ? self::STATUS_WARN : self::STATUS_FAIL,
				'Consent policy version',
				'consent.required_policy_version is not configured. The locked expected value for current clients is "2026-05-08-v1"; the config is the authority.' . ( $this->is_legacy() ? ' Legacy configs predate this key; add it during v2 migration.' : '' )
			);
			return;
		}

		$this->add_result(
			self::STATUS_PASS,
			'Consent policy version',
			sprintf( 'Configured required consent policy version: %s (config-authoritative).', $required )
		);

		$runtime        = '';
		$runtime_source = '';
		if ( defined( 'SE_CONSENT_POLICY_VERSION' ) && '' !== trim( (string) constant( 'SE_CONSENT_POLICY_VERSION' ) ) ) {
			$runtime        = trim( (string) constant( 'SE_CONSENT_POLICY_VERSION' ) );
			$runtime_source = 'SE_CONSENT_POLICY_VERSION constant';
		} else {
			$opt = get_option( 'se_consent_policy_version', '' );
			if ( is_string( $opt ) && '' !== trim( $opt ) ) {
				$runtime        = trim( $opt );
				$runtime_source = 'se_consent_policy_version option';
			}
		}

		if ( '' === $runtime ) {
			$this->add_result(
				self::STATUS_WARN,
				'Consent policy runtime',
				'No runtime consent policy source is available (MU constant/option not present in this context). The receipt-staleness gate currently skips when no runtime source exists; config-authoritative gating lands in Phase 3.'
			);
			return;
		}

		if ( $runtime === $required ) {
			$this->add_result(
				self::STATUS_PASS,
				'Consent policy runtime',
				sprintf( 'Runtime policy version (%s) matches the configured requirement.', $runtime_source )
			);
		} else {
			$this->add_result(
				self::STATUS_WARN,
				'Consent policy runtime',
				sprintf( 'Configuration/runtime mismatch: config requires "%s" but %s reports "%s". Config is authoritative; reconcile before Phase 3 wires the config value into the export consent gate.', $required, $runtime_source, $runtime )
			);
		}
	}

	/**
	 * Static runtime invariants of this build, asserted for the record.
	 *
	 * @return void
	 */
	private function check_runtime_invariants() {
		$this->add_result( self::STATUS_PASS, 'Invariant', 'Google Ads API upload is DISABLED. Google Sheets are the only export sink (append-only).' );
		$this->add_result( self::STATUS_PASS, 'Invariant', 'Exporter, doctor, and inspection commands perform no FluentCRM mutations. provision-fields is an isolated, explicit --apply administrative operation and is never invoked automatically.' );
		$this->add_result( self::STATUS_PASS, 'Invariant', 'Writes require an explicit --apply. Preview modes write nothing.' );
	}

	/**
	 * Validate Fluent Forms field contract.
	 *
	 * This is read-only. It does not register hooks or mutate forms.
	 *
	 * @return void
	 */
	private function check_fluent_forms_field_contract() {
		$form_id = absint( $this->config->get( 'fluent_forms.form_id', 0 ) );

		if ( 0 === $form_id ) {
			$this->add_result(
				$this->is_legacy() ? self::STATUS_PENDING : self::STATUS_FAIL,
				'Fluent Forms field contract',
				'fluent_forms.form_id is 0 (unconfigured). Verify the live master Fluent Form ID and set it before field-contract validation can run.'
			);
			return;
		}

		$form_fields = $this->get_fluentform_field_definitions( $form_id );

		if ( null === $form_fields ) {
			$this->add_result(
				self::STATUS_FAIL,
				'Fluent Forms field contract',
				sprintf( 'Could not load Fluent Form ID %d. Confirm the form exists and inspect Fluent Forms storage/API before adding fallback logic.', $form_id )
			);
			return;
		}

		$field_names = $this->extract_fluentform_field_names( $form_fields );

		$required_fields = array(
			'first_name',
			'last_name',
			'address_1',
			'address_2',
			'city',
			'state',
			'zip_code',
			'country-list',
			'phone',
			'email',
			'service_type',
			'estimated_value',
			'gclid',
			'gbraid',
			'wbraid',
			'fbclid',
			'msclkid',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_content',
			'utm_term',
			'referrer_url',
			'page_title',
			'ip_address',
			'user_agent',
			'cookie_value',
			'transaction_id',
			// submission_id is intentionally NOT required here: the Fluent Forms
			// entry ID reaches FluentCRM via the feed smartcode ({submission.id}
			// -> custom field submission_id), not via a front-end form field.
			// See check_submission_id_pipeline().
		);

		$missing_fields = array();

		foreach ( $required_fields as $required_field ) {
			if ( ! in_array( $required_field, $field_names, true ) ) {
				$missing_fields[] = $required_field;
			}
		}

		if ( empty( $missing_fields ) ) {
			$this->add_result(
				self::STATUS_PASS,
				'Fluent Forms field contract',
				sprintf( 'All required master-form fields are present on form ID %d.', $form_id )
			);
		} else {
			foreach ( $missing_fields as $missing_field ) {
				$this->add_result(
					self::STATUS_FAIL,
					'Fluent Forms field contract',
					sprintf( 'Missing required form field: %s', $missing_field )
				);
			}
		}
	}

	/**
	 * Load Fluent Forms field definitions.
	 *
	 * Fluent Forms does not expose one universally stable helper across all
	 * installs, so this tries app access first and then a read-only direct load
	 * from the standard forms table.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array|null
	 */
	private function get_fluentform_field_definitions( $form_id ) {
		$form_id = absint( $form_id );

		$fields = $this->get_fluentform_fields_from_app( $form_id );

		if ( null !== $fields ) {
			return $fields;
		}

		return $this->get_fluentform_fields_from_database( $form_id );
	}

	/**
	 * Try loading fields through Fluent Forms app access.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array|null
	 */
	private function get_fluentform_fields_from_app( $form_id ) {
		if ( ! function_exists( 'wpFluentForm' ) ) {
			return null;
		}

		try {
			$app = wpFluentForm();
		} catch ( Throwable $e ) {
			return null;
		}

		if ( ! is_object( $app ) ) {
			return null;
		}

		$candidates = array(
			array( 'forms', 'find' ),
			array( 'forms', 'getForm' ),
			array( 'form', 'find' ),
			array( 'form', 'getForm' ),
		);

		foreach ( $candidates as $candidate ) {
			$property = $candidate[0];
			$method   = $candidate[1];

			if ( ! isset( $app->{$property} ) || ! is_object( $app->{$property} ) || ! method_exists( $app->{$property}, $method ) ) {
				continue;
			}

			try {
				$form = $app->{$property}->{$method}( $form_id );
			} catch ( Throwable $e ) {
				continue;
			}

			$fields = $this->extract_fields_from_fluentform_record( $form );

			if ( null !== $fields ) {
				return $fields;
			}
		}

		return null;
	}

	/**
	 * Read Fluent Forms field JSON from the standard forms table.
	 *
	 * This is read-only. It does not mutate Fluent Forms data.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array|null
	 */
	private function get_fluentform_fields_from_database( $form_id ) {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$table_name = $wpdb->prefix . 'fluentform_forms';

		if ( ! $this->table_exists_by_name( $table_name ) ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT form_fields FROM `{$table_name}` WHERE id = %d LIMIT 1",
				$form_id
			)
		);

		if ( ! $row || ! isset( $row->form_fields ) ) {
			return null;
		}

		$decoded = json_decode( $row->form_fields, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Extract fields from a Fluent Forms record-like object/array.
	 *
	 * @param mixed $form Form record.
	 *
	 * @return array|null
	 */
	private function extract_fields_from_fluentform_record( $form ) {
		if ( is_array( $form ) ) {
			if ( isset( $form['form_fields'] ) ) {
				$decoded = is_string( $form['form_fields'] ) ? json_decode( $form['form_fields'], true ) : $form['form_fields'];

				return is_array( $decoded ) ? $decoded : null;
			}

			if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
				return $form['fields'];
			}

			return $form;
		}

		if ( is_object( $form ) ) {
			if ( isset( $form->form_fields ) ) {
				$decoded = is_string( $form->form_fields ) ? json_decode( $form->form_fields, true ) : $form->form_fields;

				return is_array( $decoded ) ? $decoded : null;
			}

			if ( isset( $form->fields ) && is_array( $form->fields ) ) {
				return $form->fields;
			}
		}

		return null;
	}

	/**
	 * Extract all Fluent Forms field names recursively.
	 *
	 * @param array $fields Field definitions.
	 *
	 * @return array
	 */
	private function extract_fluentform_field_names( $fields ) {
		$names = array();

		$this->walk_array_recursive(
			$fields,
			function ( $node ) use ( &$names ) {
				if ( ! is_array( $node ) ) {
					return;
				}

				if ( isset( $node['attributes']['name'] ) && is_string( $node['attributes']['name'] ) ) {
					$names[] = trim( $node['attributes']['name'] );
				}

				if ( isset( $node['name'] ) && is_string( $node['name'] ) ) {
					$names[] = trim( $node['name'] );
				}

				if ( isset( $node['fieldName'] ) && is_string( $node['fieldName'] ) ) {
					$names[] = trim( $node['fieldName'] );
				}
			}
		);

		$names = array_filter(
			$names,
			function ( $name ) {
				return '' !== $name;
			}
		);

		return array_values( array_unique( $names ) );
	}

	/**
	 * Normalize common API result wrappers into a flat array.
	 *
	 * @param mixed $result Raw result.
	 *
	 * @return array|null
	 */
	private function normalize_collection_result( $result ) {
		if ( is_array( $result ) ) {
			if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
				return $result['data'];
			}

			if ( isset( $result['items'] ) && is_array( $result['items'] ) ) {
				return $result['items'];
			}

			return $result;
		}

		if ( is_object( $result ) ) {
			if ( method_exists( $result, 'toArray' ) ) {
				$array = $result->toArray();

				return is_array( $array ) ? $array : null;
			}

			if ( isset( $result->data ) && is_array( $result->data ) ) {
				return $result->data;
			}

			if ( isset( $result->items ) && is_array( $result->items ) ) {
				return $result->items;
			}

			return array( $result );
		}

		return null;
	}

	/**
	 * Normalize item names from arrays/objects.
	 *
	 * This intentionally includes both title and slug so config values may match
	 * either exact title or exact slug.
	 *
	 * @param array $items Items.
	 *
	 * @return array
	 */
	private function normalize_item_names( $items ) {
		$names = array();

		foreach ( $items as $item ) {
			$candidates = array();

			if ( is_array( $item ) ) {
				$candidates = array(
					isset( $item['title'] ) ? $item['title'] : null,
					isset( $item['name'] ) ? $item['name'] : null,
					isset( $item['slug'] ) ? $item['slug'] : null,
					isset( $item['key'] ) ? $item['key'] : null,
					isset( $item['label'] ) ? $item['label'] : null,
					isset( $item['field_key'] ) ? $item['field_key'] : null,
					isset( $item['field_label'] ) ? $item['field_label'] : null,
				);
			} elseif ( is_object( $item ) ) {
				$candidates = array(
					isset( $item->title ) ? $item->title : null,
					isset( $item->name ) ? $item->name : null,
					isset( $item->slug ) ? $item->slug : null,
					isset( $item->key ) ? $item->key : null,
					isset( $item->label ) ? $item->label : null,
					isset( $item->field_key ) ? $item->field_key : null,
					isset( $item->field_label ) ? $item->field_label : null,
				);
			} elseif ( is_string( $item ) ) {
				$candidates = array( $item );
			}

			foreach ( $candidates as $candidate ) {
				if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
					$names[] = trim( $candidate );
				}
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Legacy test-profile guard hook.
	 *
	 * Client-neutral releases no longer embed any practice-site identifiers.
	 * The legacy diagnostics flag remains readable for backward compatibility,
	 * but enabling it produces an informational warning only.
	 *
	 * @return void
	 */
	private function run_test_profile_guards() {
		$this->add_result(
			self::STATUS_WARN,
			'Legacy test profile guards',
			'diagnostics.test_profile_guards is deprecated in client-neutral releases and performs no client-specific assertions. Disable the flag.'
		);
	}

	/**
	 * Redact the middle of an identifier for display (keeps 4 + 4 chars).
	 *
	 * @param string $value Identifier.
	 *
	 * @return string
	 */
	private function redact_middle( $value ) {
		$value = (string) $value;
		$len   = strlen( $value );
		if ( $len <= 10 ) {
			return $value;
		}
		return substr( $value, 0, 4 ) . str_repeat( '*', 6 ) . substr( $value, -4 );
	}

	/**
	 * Recursively walk an array and call a callback for every node.
	 *
	 * @param mixed    $node     Node.
	 * @param callable $callback Callback.
	 *
	 * @return void
	 */
	private function walk_array_recursive( $node, $callback ) {
		if ( is_array( $node ) ) {
			$callback( $node );

			foreach ( $node as $child ) {
				$this->walk_array_recursive( $child, $callback );
			}
		}
	}

	/**
	 * Safe plugin-active check.
	 *
	 * @param string $plugin_file Plugin basename.
	 *
	 * @return bool
	 */
	private function is_plugin_active_safe( $plugin_file ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file );
	}

	/**
	 * Check whether a table exists by suffix after current WP prefix.
	 *
	 * @param string $table_suffix Table suffix.
	 *
	 * @return bool
	 */
	private function table_exists( $table_suffix ) {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return false;
		}

		$table_name = $wpdb->prefix . $table_suffix;

		return $this->table_exists_by_name( $table_name );
	}

	/**
	 * submission_id pipeline: Fluent Forms entry ID -> feed smartcode
	 * ({submission.id}) -> FluentCRM custom field submission_id.
	 *
	 * The form itself carries NO submission_id front-end field. The custom
	 * field itself is required by check_fluentcrm_custom_fields(). No
	 * supported Fluent Forms or FluentCRM API exposes feed field mappings,
	 * and FluentCRM internal tables are never queried, so the mapping is
	 * always an operator-verification WARN here. Stored runtime fixtures
	 * can be validated separately with the supported inspect-contact /
	 * inspect-contact-submissions commands.
	 *
	 * @return void
	 */
	private function check_submission_id_pipeline() {
		$this->add_result(
			self::STATUS_WARN,
			'submission_id pipeline',
			'Operator verification required: confirm the Fluent Forms feed maps {submission.id} to the FluentCRM custom field submission_id. No supported API exposes feed mappings, so doctor cannot verify this automatically. The stored value must be the real Fluent Forms entry ID - never a random string or hard-coded value. Validate stored runtime fixtures with: wp se-ads inspect-contact / inspect-contact-submissions.'
		);
	}

	/**
	 * Check whether a full table name exists.
	 *
	 * @param string $table_name Full table name.
	 *
	 * @return bool
	 */
	private function table_exists_by_name( $table_name ) {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb || ! is_string( $table_name ) || '' === $table_name ) {
			return false;
		}

		$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );

		return $wpdb->get_var( $query ) === $table_name;
	}
}
