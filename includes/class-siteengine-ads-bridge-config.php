<?php
/**
 * Config loader / resolver for SiteEngine Ads Bridge.
 *
 * Schema v2 with provenance tracking and an explicit, reported legacy (v1)
 * compatibility adapter. The v2 schema is authoritative. Legacy schema-less
 * configs are ingested through a fixed mapping table (never silently merged),
 * and every consumer-facing read resolves against one canonical v2 tree.
 *
 * Configuration authority precedence:
 *   1. Explicit external SEAB_CONFIG_FILE (legacy/operator override)
 *   2. WordPress Ads Bridge settings, with shared client facts from SiteEngine Base
 *   3. Existing plugin-local config/client.php (migration fallback)
 *   4. Safe setup defaults with exports disabled
 *
 * Runtime call sites that still read legacy dot-keys (e.g. `timezone` and
 * `sheets.google_ads_import.tab`) resolve
 * through LEGACY_READ_ALIASES to the canonical v2 key, so Phase 2 changes
 * configuration resolution only — never export behavior.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteEngine_Ads_Bridge_Config {

	/**
	 * The only v2 schema version this build supports.
	 */
	const SUPPORTED_SCHEMA_VERSION = 2;

	/**
	 * Provenance source labels.
	 */
	const SRC_CLIENT   = 'PHP config';
	const SRC_SETTINGS = 'Ads Bridge settings';
	const SRC_BASE     = 'SiteEngine Base';
	const SRC_LEGACY   = 'legacy compatibility mapping';
	const SRC_DEFAULT  = 'plugin default';

	/**
	 * Placeholder tokens rejected in required values (case-insensitive substring).
	 *
	 * @var array
	 */
	private static $placeholder_tokens = array(
		'CHANGE_ME',
		'CHANGEME',
		'REPLACE_ME',
		'REPLACEME',
		'PLACEHOLDER',
		'YOUR_CLIENT',
		'YOUR_SPREADSHEET',
		'YOUR_GOOGLE',
		'EXAMPLE_ONLY',
	);

	/**
	 * Legacy (v1) runtime dot-key -> canonical v2 dot-key.
	 *
	 * Complete enumeration of every legacy key read anywhere in the plugin
	 * (CLI + doctor), from the Phase 1 audit. Reads of these keys resolve to
	 * the canonical v2 value regardless of config mode. Do not extend this
	 * table for new code — new code must read v2 keys directly.
	 *
	 * @var array
	 */
	private static $legacy_read_aliases = array(
		'client_slug'                                  => 'client.slug',
		'client_name'                                  => 'client.name',
		'client_short_name'                            => 'client.short_name',
		'google_ads_cid'                               => 'client.google_ads_customer_id',
		'timezone'                                     => 'client.timezone',
		'currency'                                     => 'client.currency',
		'form.id'                                      => 'fluent_forms.form_id',
		'fields'                                       => 'fluent_forms.fields',
		'crm_map.lists'                                => 'fluentcrm.lists',
		'crm_map.lists.customers'                      => 'fluentcrm.lists.customers',
		'crm_map.lists.future_customers'               => 'fluentcrm.lists.future_customers',
		'crm_map.lists.do_not_contact'                 => 'fluentcrm.lists.do_not_contact',
		'crm_map.lists.dnc'                            => 'fluentcrm.lists.do_not_contact',
		'crm_map.tags'                                 => 'fluentcrm.tags',
		'crm_map.tags.sources'                         => 'fluentcrm.tags.sources',
		'crm_map.fields.lead_status.slug'              => 'fluentcrm.fields.lead_status.slug',
		'crm_map.fields.lead_status.won_value'         => 'fluentcrm.fields.lead_status.won_value',
		'conversions.won_job.action_name'              => 'exports.won_jobs.conversion_action',
		'sheets.enabled'                               => 'google_sheets.enabled',
		'sheets.spreadsheet_id'                        => 'google_sheets.spreadsheet_id',
		'sheets.credentials_path'                      => 'google_sheets.credentials_path',
		'sheets.google_ads_import.tab'                 => 'exports.won_jobs.sheet_tab',
		'sheets.google_ads_import.value_input_option'  => 'google_sheets.value_input_option',
		'sheets.google_ads_import.plugin_side_sha256'  => 'exports.won_jobs.plugin_side_sha256',
		'sheets.google_ads_import.headers'             => 'exports.won_jobs.header_overrides',
		'doctor.run_test_profile_guards'               => 'diagnostics.test_profile_guards',
	);

	/**
	 * The four authoritative lifecycle tag role keys, in canonical order.
	 *
	 * @var array
	 */
	private static $lifecycle_roles = array(
		'new',
		'qualified',
		'unqualified',
		'won',
	);

	/**
	 * The five lost-outcome reason keys, in canonical order. Each reason binds
	 * to lifecycle tag role "lost_{reason}" — the lifecycle tag map is the
	 * single tag-name authority; lost reasons never redeclare tag names.
	 *
	 * @var array
	 */
	private static $lost_reasons = array(
		'wrong_price',
		'wrong_product',
		'wrong_service',
		'wrong_area',
		'wrong_attitude',
	);

	/**
	 * Singleton instance.
	 *
	 * @var SiteEngine_Ads_Bridge_Config|null
	 */
	private static $instance = null;

	/**
	 * Raw array as returned by config/client.php.
	 *
	 * @var array
	 */
	private $raw = array();

	/**
	 * Canonical resolved v2 config tree.
	 *
	 * @var array
	 */
	private $config = array();

	/**
	 * Provenance per canonical dot-key (leaf and section level).
	 *
	 * @var array
	 */
	private $provenance = array();

	/**
	 * Schema mode: 'v2', 'legacy', or 'invalid'.
	 *
	 * @var string
	 */
	private $mode = 'invalid';

	/**
	 * Active configuration authority: external, settings, local_file, or setup.
	 *
	 * @var string
	 */
	private $source = 'setup';

	/**
	 * Declared schema_version from the config file (null when absent).
	 *
	 * @var int|null
	 */
	private $declared_schema_version = null;

	/**
	 * Config load errors (fatal for validate()).
	 *
	 * @var array
	 */
	private $load_errors = array();

	/**
	 * Deprecation / compatibility notices produced by the legacy adapter.
	 *
	 * @var array
	 */
	private $deprecations = array();

	/**
	 * Get singleton instance.
	 *
	 * @return SiteEngine_Ads_Bridge_Config
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load();
	}

	/**
	 * The four lifecycle role keys.
	 *
	 * @return array
	 */
	public static function lifecycle_roles() {
		return self::$lifecycle_roles;
	}

	/**
	 * The five lost reason keys.
	 *
	 * @return array
	 */
	public static function lost_reasons() {
		return self::$lost_reasons;
	}

	/**
	 * Load config/client.php and resolve the canonical tree.
	 *
	 * @return void
	 */
	private function load() {
		// An explicitly defined SEAB_CONFIG_FILE remains the highest-precedence
		// authority for backward compatibility. UI settings are intentionally
		// inert until the operator removes that explicit override.
		if ( defined( 'SEAB_CONFIG_IS_EXTERNAL' ) && SEAB_CONFIG_IS_EXTERNAL ) {
			$this->source = 'external';
			$this->load_php_config( SEAB_CONFIG_FILE );
			return;
		}

		// WordPress/Base-managed settings are the normal 0.4+ authority.
		if ( class_exists( 'SiteEngine_Ads_Bridge_Settings' ) && SiteEngine_Ads_Bridge_Settings::exists() ) {
			$this->source = 'settings';
			$raw = SiteEngine_Ads_Bridge_Settings::get();
			$this->ingest_settings_payload( $raw );
			return;
		}

		// Existing plugin-local configs continue running unchanged until the
		// operator imports them through SiteEngine -> Ads Bridge -> Migration.
		if ( defined( 'SEAB_CONFIG_FILE' ) && file_exists( SEAB_CONFIG_FILE ) ) {
			$this->source = 'local_file';
			$this->load_php_config( SEAB_CONFIG_FILE );
			return;
		}

		// Fresh install: build an intentionally incomplete settings tree. Safe
		// defaults keep exports disabled; Base supplies shared client facts.
		$this->source                  = 'setup';
		$this->mode                    = 'v2';
		$this->declared_schema_version = self::SUPPORTED_SCHEMA_VERSION;
		$raw = class_exists( 'SiteEngine_Ads_Bridge_Settings' ) ? SiteEngine_Ads_Bridge_Settings::defaults() : array( 'schema_version' => self::SUPPORTED_SCHEMA_VERSION );
		$this->raw = $raw;
		$this->ingest_v2( $raw );
		$this->overlay_base_shared_values();
		$this->apply_defaults();
	}

	/**
	 * Load a PHP configuration file using the accepted 0.3 resolver.
	 *
	 * @param string $path File path.
	 * @return void
	 */
	private function load_php_config( $path ) {
		if ( ! is_string( $path ) || '' === $path || ! file_exists( $path ) ) {
			$this->load_errors[] = 'Config file is missing.';
			return;
		}

		$raw = require $path;
		if ( ! is_array( $raw ) ) {
			$this->load_errors[] = 'Config file did not return an array.';
			return;
		}

		$this->raw = $raw;
		if ( ! array_key_exists( 'schema_version', $raw ) ) {
			$this->mode = 'legacy';
			$this->ingest_legacy( $raw );
			$this->apply_defaults();
			return;
		}

		$sv = $raw['schema_version'];
		$this->declared_schema_version = is_numeric( $sv ) ? (int) $sv : null;
		if ( self::SUPPORTED_SCHEMA_VERSION !== $this->declared_schema_version ) {
			$this->mode          = 'invalid';
			$this->load_errors[] = sprintf(
				'Unsupported config schema_version "%s". This build supports schema_version %d, or a schema-less legacy config via the compatibility adapter.',
				is_scalar( $sv ) ? (string) $sv : gettype( $sv ),
				self::SUPPORTED_SCHEMA_VERSION
			);
			return;
		}

		$this->mode = 'v2';
		$this->ingest_v2( $raw );
		$this->apply_defaults();
	}

	/**
	 * Load WordPress settings and let SiteEngine Base own shared business facts.
	 *
	 * @param array $raw Settings payload.
	 * @return void
	 */
	private function ingest_settings_payload( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$raw['schema_version'] = self::SUPPORTED_SCHEMA_VERSION;
		$this->raw                     = $raw;
		$this->mode                    = 'v2';
		$this->declared_schema_version = self::SUPPORTED_SCHEMA_VERSION;
		$this->ingest_v2( $raw );

		// ingest_v2() is also used by legacy PHP-file configuration and therefore
		// records PHP provenance by default. In settings mode, re-label the saved
		// settings tree before Base overlays its shared business facts. This changes
		// diagnostics only; resolved values and export behavior are untouched.
		$this->provenance['schema_version'] = self::SRC_SETTINGS;
		foreach ( array( 'client', 'fluent_forms', 'fluentcrm', 'exports', 'google_sheets', 'consent', 'diagnostics' ) as $section ) {
			if ( isset( $raw[ $section ] ) && is_array( $raw[ $section ] ) ) {
				$this->mark_provenance_recursive( $section, $raw[ $section ], self::SRC_SETTINGS );
			}
		}

		$this->overlay_base_shared_values();
		$this->apply_defaults();
	}

	/**
	 * Overlay business/site facts owned by SiteEngine Base. Only settings/setup
	 * mode uses this provider; explicit PHP configuration remains authoritative
	 * until an operator deliberately migrates away from it.
	 *
	 * @return void
	 */
	private function overlay_base_shared_values() {
		if ( ! function_exists( 'siteengine_get' ) ) {
			return;
		}

		$map = array(
			'client.name'                   => 'business_name',
			'client.timezone'               => 'timezone',
			'client.currency'               => 'currency',
			'client.google_ads_customer_id' => 'google_ads_customer_id',
		);

		foreach ( $map as $config_key => $base_key ) {
			$value = siteengine_get( $base_key, '' );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$this->set_canonical( $config_key, (string) $value, self::SRC_BASE );
			}
		}
	}

	/**
	 * Ingest a v2 config: copy known sections verbatim, record provenance,
	 * and reject legacy top-level keys mixed into a v2 file (never merge).
	 *
	 * @param array $raw Raw config array.
	 * @return void
	 */
	private function ingest_v2( $raw ) {
		$sections = array( 'client', 'fluent_forms', 'fluentcrm', 'exports', 'google_sheets', 'consent', 'diagnostics' );

		$this->config['schema_version'] = self::SUPPORTED_SCHEMA_VERSION;
		$this->provenance['schema_version'] = self::SRC_CLIENT;

		foreach ( $sections as $section ) {
			if ( array_key_exists( $section, $raw ) && is_array( $raw[ $section ] ) ) {
				$this->config[ $section ] = $raw[ $section ];
				$this->mark_provenance_recursive( $section, $raw[ $section ], self::SRC_CLIENT );
			}
		}

		// A v2 file must not also carry legacy top-level keys. Conflicting
		// schemas are an error, not a merge.
		$legacy_top = array( 'client_slug', 'client_name', 'client_short_name', 'google_ads_cid', 'timezone', 'currency', 'form', 'crm_map', 'conversions', 'sheets', 'services', 'audiences', 'doctor' );
		$found      = array();
		foreach ( $legacy_top as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$found[] = $key;
			}
		}
		if ( ! empty( $found ) ) {
			$this->load_errors[] = 'Config declares schema_version 2 but also contains legacy top-level key(s): ' . implode( ', ', $found ) . '. Remove the legacy keys; v2 and legacy keys are never merged.';
		}
	}

	/**
	 * Ingest a legacy (v1) config through the fixed compatibility mapping.
	 * Every mapped value is recorded with legacy provenance and a deprecation
	 * notice. Unmapped legacy sections are dropped with an explicit notice.
	 *
	 * @param array $raw Raw legacy config array.
	 * @return void
	 */
	private function ingest_legacy( $raw ) {
		$this->deprecations[] = 'Legacy (schema-less) config detected: values resolved through the compatibility adapter. Migrate config/client.php to schema_version 2.';

		$scalar_map = array(
			'client_slug'       => 'client.slug',
			'client_name'       => 'client.name',
			'client_short_name' => 'client.short_name',
			'google_ads_cid'    => 'client.google_ads_customer_id',
			'timezone'          => 'client.timezone',
			'currency'          => 'client.currency',
		);
		foreach ( $scalar_map as $old => $new ) {
			if ( array_key_exists( $old, $raw ) ) {
				$this->set_canonical( $new, $raw[ $old ], self::SRC_LEGACY );
			}
		}

		// form.id -> fluent_forms.form_id. Other form.* subkeys were never read
		// at runtime and are dropped (reported).
		if ( isset( $raw['form'] ) && is_array( $raw['form'] ) ) {
			if ( array_key_exists( 'id', $raw['form'] ) ) {
				$this->set_canonical( 'fluent_forms.form_id', $raw['form']['id'], self::SRC_LEGACY );
			}
			$extra = array_diff( array_keys( $raw['form'] ), array( 'id' ) );
			if ( ! empty( $extra ) ) {
				$this->deprecations[] = 'Legacy form.* subkey(s) dropped (never read at runtime): ' . implode( ', ', $extra ) . '.';
			}
		}

		if ( isset( $raw['fields'] ) && is_array( $raw['fields'] ) ) {
			$this->set_canonical( 'fluent_forms.fields', $raw['fields'], self::SRC_LEGACY );
		}

		// Lists: accept both legacy spellings; do_not_contact wins over dnc to
		// match the historical runtime read. Divergent duplicates are an error.
		if ( isset( $raw['crm_map']['lists'] ) && is_array( $raw['crm_map']['lists'] ) ) {
			$lists = $raw['crm_map']['lists'];
			foreach ( array( 'customers', 'future_customers' ) as $role ) {
				if ( array_key_exists( $role, $lists ) ) {
					$this->set_canonical( 'fluentcrm.lists.' . $role, $lists[ $role ], self::SRC_LEGACY );
				}
			}
			$has_dnc = array_key_exists( 'dnc', $lists );
			$has_do  = array_key_exists( 'do_not_contact', $lists );
			if ( $has_dnc && $has_do && (string) $lists['dnc'] !== (string) $lists['do_not_contact'] ) {
				$this->load_errors[] = sprintf(
					'Legacy config defines both crm_map.lists.dnc ("%s") and crm_map.lists.do_not_contact ("%s") with different values. One DNC authority is required.',
					(string) $lists['dnc'],
					(string) $lists['do_not_contact']
				);
			}
			if ( $has_do ) {
				$this->set_canonical( 'fluentcrm.lists.do_not_contact', $lists['do_not_contact'], self::SRC_LEGACY );
			} elseif ( $has_dnc ) {
				$this->set_canonical( 'fluentcrm.lists.do_not_contact', $lists['dnc'], self::SRC_LEGACY );
				$this->deprecations[] = 'Legacy key crm_map.lists.dnc mapped to fluentcrm.lists.do_not_contact. Historical runtime read crm_map.lists.do_not_contact and fell back to the hardcoded default "DNC"; the configured value is now authoritative.';
			}
		}

		if ( isset( $raw['crm_map']['tags']['sources'] ) && is_array( $raw['crm_map']['tags']['sources'] ) ) {
			$this->set_canonical( 'fluentcrm.tags.sources', $raw['crm_map']['tags']['sources'], self::SRC_LEGACY );
		}

		// lead_status custom-field map: retained only for legacy-config
		// compatibility. Lifecycle tags are the sole export authority.
		if ( isset( $raw['crm_map']['fields']['lead_status'] ) && is_array( $raw['crm_map']['fields']['lead_status'] ) ) {
			$this->set_canonical( 'fluentcrm.fields.lead_status', $raw['crm_map']['fields']['lead_status'], self::SRC_LEGACY );
			$this->deprecations[] = 'crm_map.fields.lead_status retained only as a legacy compatibility value. Lifecycle tags are the sole export authority.';
		}

		// Won-job conversion: action/value map to exports.won_jobs. The legacy
		// conversions.won_job.sheet_tab (e.g. CA_Won_Job) was dead — the real
		// tab authority was sheets.google_ads_import.tab.
		if ( isset( $raw['conversions']['won_job'] ) && is_array( $raw['conversions']['won_job'] ) ) {
			$won = $raw['conversions']['won_job'];
			if ( array_key_exists( 'action_name', $won ) ) {
				$this->set_canonical( 'exports.won_jobs.conversion_action', $won['action_name'], self::SRC_LEGACY );
			}
			if ( array_key_exists( 'value_source', $won ) ) {
				$this->set_canonical( 'exports.won_jobs.value_field', $won['value_source'], self::SRC_LEGACY );
			}
			if ( array_key_exists( 'enabled', $won ) ) {
				$this->set_canonical( 'exports.won_jobs.enabled', (bool) $won['enabled'], self::SRC_LEGACY );
			}
			if ( array_key_exists( 'sheet_tab', $won ) ) {
				$this->deprecations[] = sprintf( 'Legacy conversions.won_job.sheet_tab ("%s") dropped: it was never the export tab authority. The won export tab is exports.won_jobs.sheet_tab (from legacy sheets.google_ads_import.tab).', (string) $won['sheet_tab'] );
			}
		}

		// lead_submitted was an operational export misrepresented as a Google
		// conversion. It maps to the All_Leads operational family, disabled by
		// default, and its conversion action name is dropped.
		if ( isset( $raw['conversions']['lead_submitted'] ) && is_array( $raw['conversions']['lead_submitted'] ) ) {
			$this->set_canonical( 'exports.all_leads.enabled', false, self::SRC_LEGACY );
			if ( array_key_exists( 'sheet_tab', $raw['conversions']['lead_submitted'] ) ) {
				$this->set_canonical( 'exports.all_leads.sheet_tab', $raw['conversions']['lead_submitted']['sheet_tab'], self::SRC_LEGACY );
			}
			$this->deprecations[] = 'Legacy conversions.lead_submitted mapped to exports.all_leads (disabled by default). Its Google conversion action name was dropped: All_Leads is an operational export, not a Google conversion.';
		}

		if ( isset( $raw['sheets'] ) && is_array( $raw['sheets'] ) ) {
			$sheets = $raw['sheets'];
			if ( array_key_exists( 'enabled', $sheets ) ) {
				$this->set_canonical( 'google_sheets.enabled', (bool) $sheets['enabled'], self::SRC_LEGACY );
			}
			if ( array_key_exists( 'spreadsheet_id', $sheets ) ) {
				$this->set_canonical( 'google_sheets.spreadsheet_id', $sheets['spreadsheet_id'], self::SRC_LEGACY );
			}
			if ( array_key_exists( 'credentials_path', $sheets ) ) {
				$this->set_canonical( 'google_sheets.credentials_path', $sheets['credentials_path'], self::SRC_LEGACY );
			}
			if ( isset( $sheets['google_ads_import'] ) && is_array( $sheets['google_ads_import'] ) ) {
				$gai = $sheets['google_ads_import'];
				if ( array_key_exists( 'tab', $gai ) ) {
					$this->set_canonical( 'exports.won_jobs.sheet_tab', $gai['tab'], self::SRC_LEGACY );
				}
				if ( array_key_exists( 'value_input_option', $gai ) ) {
					$this->set_canonical( 'google_sheets.value_input_option', $gai['value_input_option'], self::SRC_LEGACY );
				}
				if ( array_key_exists( 'plugin_side_sha256', $gai ) ) {
					$this->set_canonical( 'exports.won_jobs.plugin_side_sha256', (bool) $gai['plugin_side_sha256'], self::SRC_LEGACY );
				}
				if ( array_key_exists( 'headers', $gai ) ) {
					$this->set_canonical( 'exports.won_jobs.header_overrides', $gai['headers'], self::SRC_LEGACY );
				}
			}
			if ( array_key_exists( 'tabs', $sheets ) ) {
				$this->deprecations[] = 'Legacy sheets.tabs inventory list dropped: it had no runtime function. Tab authorities are per export family under exports.*.';
			}
		}

		if ( isset( $raw['doctor']['run_test_profile_guards'] ) ) {
			$this->set_canonical( 'diagnostics.test_profile_guards', (bool) $raw['doctor']['run_test_profile_guards'], self::SRC_LEGACY );
		}

		if ( array_key_exists( 'services', $raw ) ) {
			$this->deprecations[] = 'Legacy services section dropped: demo scaffold with no implementation.';
		}
		if ( array_key_exists( 'audiences', $raw ) ) {
			$this->deprecations[] = 'Legacy audiences section dropped: the audience engine is a future feature and will get its own config block.';
		}

		// Legacy configs predate the consent section. No default is invented;
		// doctor reports the missing required policy version.
		$this->deprecations[] = 'Legacy config has no consent.required_policy_version. The consent policy authority must be configured during v2 migration.';
	}

	/**
	 * Fill safe plugin defaults for keys not set by client.php or the adapter,
	 * recording default provenance. No default is ever invented for identity,
	 * credentials, spreadsheet, tabs, actions, or consent policy version.
	 *
	 * @return void
	 */
	private function apply_defaults() {
		$defaults = array(
			'google_sheets.enabled'                => false,
			'google_sheets.value_input_option'     => 'RAW',
			'exports.all_leads.enabled'            => false,
			'exports.all_leads.header_overrides'   => array(),
			'fluentcrm.lifecycle_automation.enabled' => false,
			'fluentcrm.lifecycle_automation.unqualified_reason_field' => 'customer_rating',
			'fluentcrm.lifecycle_automation.unqualified_at_field' => 'unqualified_at',
			'fluentcrm.lifecycle_automation.stale_new_days' => 3,
			'exports.qualified_leads.enabled'       => false,
			'exports.qualified_leads.conversion_action' => 'SE | Qualified Lead',
			'exports.qualified_leads.date_field'    => 'qualified_at',
			'exports.qualified_leads.conversion_value' => '',
			'exports.qualified_leads.plugin_side_sha256' => true,
			'exports.qualified_leads.header_overrides' => array(),
			'exports.won_jobs.enabled'             => false,
			'exports.won_jobs.plugin_side_sha256'  => true,
			'exports.won_jobs.header_overrides'    => array(),
			'exports.won_jobs.value_field'         => 'invoiced_amount',
			'exports.won_jobs.date_field'          => 'closed_date',
			'exports.lost_leads.enabled'           => false,
			'exports.lost_leads.date_field'        => 'closed_date',
			'exports.lost_leads.conversion_value'  => '0.00',
			'exports.lost_leads.plugin_side_sha256'=> true,
			'exports.lost_leads.header_overrides'  => array(),
			'consent.require_ad_user_data'         => true,
			'diagnostics.test_profile_guards'      => false,
		);

		foreach ( $defaults as $key => $value ) {
			if ( ! $this->canonical_has( $key ) ) {
				$this->set_canonical( $key, $value, self::SRC_DEFAULT );
			}
		}
	}

	/**
	 * Whether a canonical dot-key exists.
	 *
	 * @param string $key Canonical dot key.
	 * @return bool
	 */
	private function canonical_has( $key ) {
		$sentinel = new stdClass();
		return $sentinel !== $this->canonical_get( $key, $sentinel );
	}

	/**
	 * Read a canonical dot-key.
	 *
	 * @param string $key     Canonical dot key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	private function canonical_get( $key, $default = null ) {
		if ( '' === $key ) {
			return $this->config;
		}
		$segments = explode( '.', $key );
		$value    = $this->config;
		foreach ( $segments as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}
			$value = $value[ $segment ];
		}
		return $value;
	}

	/**
	 * Set a canonical dot-key and record provenance.
	 *
	 * @param string $key    Canonical dot key.
	 * @param mixed  $value  Value.
	 * @param string $source Provenance label.
	 * @return void
	 */
	private function set_canonical( $key, $value, $source ) {
		$segments = explode( '.', $key );
		$node     = &$this->config;
		foreach ( $segments as $i => $segment ) {
			if ( $i === count( $segments ) - 1 ) {
				$node[ $segment ] = $value;
				break;
			}
			if ( ! isset( $node[ $segment ] ) || ! is_array( $node[ $segment ] ) ) {
				$node[ $segment ] = array();
			}
			$node = &$node[ $segment ];
		}
		unset( $node );

		if ( is_array( $value ) ) {
			$this->mark_provenance_recursive( $key, $value, $source );
		} else {
			$this->provenance[ $key ] = $source;
		}
	}

	/**
	 * Record provenance for a key and, when the value is an array, all leaves.
	 *
	 * @param string $key    Canonical dot key.
	 * @param mixed  $value  Value at the key.
	 * @param string $source Provenance label.
	 * @return void
	 */
	private function mark_provenance_recursive( $key, $value, $source ) {
		$this->provenance[ $key ] = $source;
		if ( is_array( $value ) ) {
			foreach ( $value as $child_key => $child_value ) {
				$this->mark_provenance_recursive( $key . '.' . $child_key, $child_value, $source );
			}
		}
	}

	/**
	 * Return the entire canonical (v2-resolved) config array.
	 *
	 * @return array
	 */
	public function all() {
		return $this->config;
	}

	/**
	 * Return the raw array as returned by client.php (pre-resolution).
	 *
	 * @return array
	 */
	public function raw() {
		return $this->raw;
	}

	/**
	 * Read a config value using dot notation.
	 *
	 * Accepts canonical v2 keys, and legacy runtime keys via
	 * LEGACY_READ_ALIASES so existing call sites keep working unchanged.
	 *
	 * @param string $key     Dot notation key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$canonical = isset( self::$legacy_read_aliases[ $key ] ) ? self::$legacy_read_aliases[ $key ] : $key;
		return $this->canonical_get( $canonical, $default );
	}

	/**
	 * Check whether a dot notation key exists (canonical or legacy alias).
	 *
	 * @param string $key Dot notation key.
	 * @return bool
	 */
	public function has( $key ) {
		$sentinel = new stdClass();
		return $sentinel !== $this->get( $key, $sentinel );
	}

	/**
	 * Provenance for a resolved key ('' when the key is not set).
	 *
	 * @param string $key Canonical or legacy dot key.
	 * @return string
	 */
	public function provenance( $key ) {
		$canonical = isset( self::$legacy_read_aliases[ $key ] ) ? self::$legacy_read_aliases[ $key ] : $key;
		if ( isset( $this->provenance[ $canonical ] ) ) {
			return $this->provenance[ $canonical ];
		}
		return $this->canonical_has( $canonical ) ? self::SRC_DEFAULT : '';
	}

	/**
	 * Schema mode: 'v2', 'legacy', or 'invalid'.
	 *
	 * @return string
	 */
	public function schema_mode() {
		return $this->mode;
	}

	/**
	 * Declared schema_version from the config file (null when absent).
	 *
	 * @return int|null
	 */
	public function declared_schema_version() {
		return $this->declared_schema_version;
	}

	/**
	 * Absolute path of the loaded config file ('' when missing).
	 *
	 * @return string
	 */
	public function config_file_path() {
		if ( 'settings' === $this->source ) {
			return 'WordPress settings + SiteEngine Base';
		}
		if ( 'setup' === $this->source ) {
			return 'SiteEngine Base / setup defaults';
		}
		return defined( 'SEAB_CONFIG_FILE' ) ? (string) SEAB_CONFIG_FILE : '';
	}

	/**
	 * Active configuration authority.
	 *
	 * @return string
	 */
	public function configuration_source() {
		return $this->source;
	}

	/**
	 * Whether wp-config.php (or another bootstrap file) explicitly defines the
	 * PHP config path and therefore overrides Base/UI settings.
	 *
	 * @return bool
	 */
	public function external_override_active() {
		return 'external' === $this->source;
	}

	/**
	 * Deprecation / compatibility notices from the legacy adapter.
	 *
	 * @return array
	 */
	public function deprecations() {
		return $this->deprecations;
	}

	/**
	 * Return config load errors.
	 *
	 * @return array
	 */
	public function get_load_errors() {
		return $this->load_errors;
	}

	/**
	 * Whether a required string value looks like an unconfigured placeholder.
	 *
	 * @param mixed $value Value to test.
	 * @return bool
	 */
	public static function looks_like_placeholder( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}
		$upper = strtoupper( $value );
		foreach ( self::$placeholder_tokens as $token ) {
			if ( false !== strpos( $upper, $token ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validate the loaded config.
	 *
	 * Used by the activation guard. Legacy configs are validated with the
	 * original v1 rules so activation behavior for an existing install is
	 * unchanged; v2 configs are validated against the v2 structural rules.
	 * Deeper, non-fatal diagnostics belong to doctor.
	 *
	 * @return array Validation errors (empty when valid).
	 */
	public function validate() {
		if ( ! empty( $this->load_errors ) ) {
			return $this->load_errors;
		}

		if ( 'legacy' === $this->mode ) {
			return $this->validate_legacy();
		}

		return $this->validate_v2();
	}

	/**
	 * Original v1 validation rules, applied to the raw legacy array.
	 *
	 * @return array
	 */
	private function validate_legacy() {
		$errors = array();

		$required_scalar_keys = array(
			'client_slug',
			'client_name',
			'client_short_name',
			'google_ads_cid',
			'timezone',
			'currency',
		);

		foreach ( $required_scalar_keys as $key ) {
			$value = isset( $this->raw[ $key ] ) ? $this->raw[ $key ] : null;
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				$errors[] = sprintf( 'Missing or empty required config key: %s', $key );
			}
		}

		$required_array_keys = array(
			'form',
			'fields',
			'conversions',
			'services',
			'audiences',
			'sheets',
			'doctor',
		);

		foreach ( $required_array_keys as $key ) {
			if ( ! isset( $this->raw[ $key ] ) || ! is_array( $this->raw[ $key ] ) ) {
				$errors[] = sprintf( 'Missing or invalid required config array: %s', $key );
			}
		}

		$required_conversion_keys = array(
			array( 'lead_submitted', 'action_name' ),
			array( 'lead_submitted', 'sheet_tab' ),
			array( 'won_job', 'action_name' ),
			array( 'won_job', 'sheet_tab' ),
		);

		foreach ( $required_conversion_keys as $pair ) {
			$value = isset( $this->raw['conversions'][ $pair[0] ][ $pair[1] ] ) ? $this->raw['conversions'][ $pair[0] ][ $pair[1] ] : null;
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				$errors[] = sprintf( 'Missing or empty required conversion config key: conversions.%s.%s', $pair[0], $pair[1] );
			}
		}

		return $errors;
	}

	/**
	 * v2 structural validation. Activation-level: structural presence,
	 * uniqueness, and placeholder rejection. Runtime/environment checks
	 * (timezone validity, CID format, live CRM objects, file readability,
	 * form_id 0) are doctor's job.
	 *
	 * @return array
	 */
	private function validate_v2() {
		$errors = array();

		$required_scalars = array(
			'client.slug',
			'client.name',
			'client.short_name',
			'client.timezone',
			'client.currency',
			'client.google_ads_customer_id',
			'consent.required_policy_version',
		);
		foreach ( $required_scalars as $key ) {
			$value = $this->canonical_get( $key );
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				$errors[] = sprintf( 'Missing or empty required config key: %s', $key );
			} elseif ( self::looks_like_placeholder( $value ) ) {
				$errors[] = sprintf( 'Config key %s still contains a placeholder value ("%s"). Configure the real value.', $key, $value );
			}
		}

		if ( true === (bool) $this->canonical_get( 'google_sheets.enabled', false ) ) {
			foreach ( array( 'google_sheets.spreadsheet_id', 'google_sheets.credentials_path' ) as $key ) {
				$value = $this->canonical_get( $key );
				if ( ! is_string( $value ) || '' === trim( $value ) ) {
					$errors[] = sprintf( 'google_sheets.enabled is true but %s is missing or empty.', $key );
				} elseif ( self::looks_like_placeholder( $value ) ) {
					$errors[] = sprintf( 'Config key %s still contains a placeholder value ("%s").', $key, $value );
				}
			}
		}

		foreach ( array( 'customers', 'future_customers', 'do_not_contact' ) as $role ) {
			$value = $this->canonical_get( 'fluentcrm.lists.' . $role );
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				$errors[] = sprintf( 'Missing or empty required FluentCRM list name: fluentcrm.lists.%s', $role );
			} elseif ( self::looks_like_placeholder( $value ) ) {
				$errors[] = sprintf( 'FluentCRM list fluentcrm.lists.%s still contains a placeholder value ("%s").', $role, $value );
			}
		}

		$seen_tags = array();
		foreach ( self::$lifecycle_roles as $role ) {
			$value = $this->canonical_get( 'fluentcrm.tags.lifecycle.' . $role );
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				$errors[] = sprintf( 'Missing or empty required lifecycle tag: fluentcrm.tags.lifecycle.%s', $role );
				continue;
			}
			if ( self::looks_like_placeholder( $value ) ) {
				$errors[] = sprintf( 'Lifecycle tag fluentcrm.tags.lifecycle.%s still contains a placeholder value ("%s").', $role, $value );
				continue;
			}
			$normalized = trim( $value );
			if ( isset( $seen_tags[ $normalized ] ) ) {
				$errors[] = sprintf( 'Duplicate lifecycle tag name "%s" (roles: %s, %s). Each lifecycle role needs a distinct tag.', $normalized, $seen_tags[ $normalized ], $role );
			} else {
				$seen_tags[ $normalized ] = $role;
			}
		}

		if ( true === (bool) $this->canonical_get( 'fluentcrm.lifecycle_automation.enabled', false ) ) {
			$reason_field = trim( (string) $this->canonical_get( 'fluentcrm.lifecycle_automation.unqualified_reason_field', '' ) );
			$unqualified_at = trim( (string) $this->canonical_get( 'fluentcrm.lifecycle_automation.unqualified_at_field', '' ) );
			$stale_days = (int) $this->canonical_get( 'fluentcrm.lifecycle_automation.stale_new_days', 0 );
			if ( '' === $reason_field ) {
				$errors[] = 'Lifecycle automation is enabled but fluentcrm.lifecycle_automation.unqualified_reason_field is empty.';
			}
			if ( '' === $unqualified_at ) {
				$errors[] = 'Lifecycle automation is enabled but fluentcrm.lifecycle_automation.unqualified_at_field is empty.';
			}
			if ( $stale_days < 1 ) {
				$errors[] = 'Lifecycle automation is enabled but fluentcrm.lifecycle_automation.stale_new_days is not a positive integer.';
			}
		}

		$enabled_tabs = array();
		$validate_tab = function ( $key, $family ) use ( &$errors, &$enabled_tabs ) {
			$value = $this->canonical_get( $key );
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				$errors[] = sprintf( '%s is enabled but %s is missing or empty.', $family, $key );
				return '';
			}
			$value = trim( $value );
			if ( self::looks_like_placeholder( $value ) ) {
				$errors[] = sprintf( 'Config key %s still contains a placeholder value ("%s").', $key, $value );
			}
						if ( strlen( $value ) > 100 || false !== strpbrk( $value, ':\\/?*[]' ) ) {
				$errors[] = sprintf( 'Sheet tab %s has an invalid Google Sheets title (max 100 characters; cannot contain : \\ / ? * [ ]).', $key );
			}
			if ( isset( $enabled_tabs[ $value ] ) ) {
				$errors[] = sprintf( 'Duplicate enabled Sheet tab "%s" is assigned to both %s and %s.', $value, $enabled_tabs[ $value ], $family );
			} else {
				$enabled_tabs[ $value ] = $family;
			}
			return $value;
		};

		$all_enabled       = true === (bool) $this->canonical_get( 'exports.all_leads.enabled', false );
		$qualified_enabled = true === (bool) $this->canonical_get( 'exports.qualified_leads.enabled', false );
		$won_enabled       = true === (bool) $this->canonical_get( 'exports.won_jobs.enabled', false );
		$lost_enabled      = false; // 0.5.1 retires Lost_* worksheet exports; retained config is inert for compatibility.

		if ( ( $all_enabled || $qualified_enabled || $won_enabled ) && ! (bool) $this->canonical_get( 'google_sheets.enabled', false ) ) {
			$errors[] = 'At least one export family is enabled but google_sheets.enabled is false.';
		}

		if ( $all_enabled ) {
			$validate_tab( 'exports.all_leads.sheet_tab', 'exports.all_leads' );
		}

		if ( $qualified_enabled || $won_enabled ) {
			$validate_tab( 'exports.won_jobs.sheet_tab', 'exports.google_ads_import' );
		}

		if ( $qualified_enabled ) {
			$action = $this->canonical_get( 'exports.qualified_leads.conversion_action' );
			if ( ! is_string( $action ) || '' === trim( $action ) ) {
				$errors[] = 'exports.qualified_leads.enabled is true but exports.qualified_leads.conversion_action is missing or empty.';
			} elseif ( self::looks_like_placeholder( $action ) ) {
				$errors[] = sprintf( 'exports.qualified_leads.conversion_action still contains a placeholder value ("%s").', $action );
			}
			$date_field = $this->canonical_get( 'exports.qualified_leads.date_field', '' );
			if ( ! is_string( $date_field ) || '' === trim( $date_field ) ) {
				$errors[] = 'exports.qualified_leads.date_field is missing or empty.';
			}
			$qualified_value = $this->canonical_get( 'exports.qualified_leads.conversion_value', '' );
			if ( '' !== trim( (string) $qualified_value ) && ( ! is_numeric( $qualified_value ) || (float) $qualified_value < 0 ) ) {
				$errors[] = 'exports.qualified_leads.conversion_value must be blank or a non-negative number.';
			}
		}

		if ( $won_enabled ) {
			$action = $this->canonical_get( 'exports.won_jobs.conversion_action' );
			if ( ! is_string( $action ) || '' === trim( $action ) ) {
				$errors[] = 'exports.won_jobs.enabled is true but exports.won_jobs.conversion_action is missing or empty.';
			} elseif ( self::looks_like_placeholder( $action ) ) {
				$errors[] = sprintf( 'exports.won_jobs.conversion_action still contains a placeholder value ("%s").', $action );
			}
			foreach ( array( 'value_field', 'date_field' ) as $field_key ) {
				$value = $this->canonical_get( 'exports.won_jobs.' . $field_key, '' );
				if ( ! is_string( $value ) || '' === trim( $value ) ) {
					$errors[] = sprintf( 'exports.won_jobs.%s is missing or empty.', $field_key );
				}
			}
		}

		// Legacy lost-lead worksheet settings are intentionally ignored in 0.5.1.

		$form_id = $this->canonical_get( 'fluent_forms.form_id', null );
		if ( null === $form_id || ! is_numeric( $form_id ) || (int) $form_id < 0 ) {
			$errors[] = 'fluent_forms.form_id must be a non-negative integer (0 is allowed only until the live master form ID is verified; doctor reports it).';
		}

		return $errors;
	}
}
