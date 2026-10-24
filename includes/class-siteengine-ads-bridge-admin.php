<?php
/**
 * SiteEngine Base-integrated administration for Ads Bridge.
 *
 * @package SiteEngineAdsBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteEngine_Ads_Bridge_Admin {

	const PAGE_SLUG = 'siteengine-ads-bridge';

	/** @var bool */
	private static $initialized = false;

	/**
	 * Register hooks once.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		add_filter( 'siteengine_modules', array( __CLASS__, 'register_module' ) );
		add_filter( 'siteengine_readme_tabs', array( __CLASS__, 'register_help_tab' ) );
		add_action( 'admin_post_seab_save_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_seab_import_config', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_base_missing_notice' ) );
	}

	/**
	 * Register Ads Bridge with SiteEngine Base 2.2+.
	 *
	 * @param array $modules Modules.
	 * @return array
	 */
	public static function register_module( $modules ) {
		$modules['ads-bridge'] = array(
			'title'           => 'Ads Bridge',
			'menu_title'      => 'Ads Bridge',
			'page_slug'       => self::PAGE_SLUG,
			'order'           => 30,
			'render'          => array( __CLASS__, 'render' ),
			'status_callback' => array( __CLASS__, 'module_status' ),
			'description'     => 'Fluent Forms / FluentCRM reconciliation and Google Ads Data Manager Sheet exports.',
			'requirements'    => array(
				array( 'key' => 'business_name', 'label' => 'Business Name' ),
				array( 'key' => 'timezone', 'label' => 'Business Timezone' ),
				array( 'key' => 'currency', 'label' => 'Currency' ),
				array( 'key' => 'google_ads_customer_id', 'label' => 'Google Ads Customer ID' ),
			),
		);
		return $modules;
	}

	/**
	 * Register Ads Bridge operator documentation inside Base Help / Reference.
	 *
	 * Base owns the documentation shell; Ads Bridge owns and versions its own
	 * setup guidance so instructions cannot drift out of sync with the plugin.
	 *
	 * @param array $tabs Registered Help / Reference tabs.
	 * @return array
	 */
	public static function register_help_tab( $tabs ) {
		if ( ! is_array( $tabs ) ) {
			$tabs = array();
		}

		$tabs['ads-bridge'] = array(
			'title'    => 'Ads Bridge',
			'callback' => array( __CLASS__, 'render_help' ),
			'order'    => 40,
		);

		return $tabs;
	}

	/**
	 * Render Ads Bridge setup/reference documentation in SiteEngine Base.
	 *
	 * @return void
	 */
	public static function render_help() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$ads_url        = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$diagnostics_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=diagnostics' );

		echo '<h2>SiteEngine Ads Bridge Setup</h2>';
		echo '<p>Ads Bridge reconciles the configured Fluent Form and FluentCRM lifecycle into append-only Google Sheets feeds for Google Ads Data Manager. The Golden Account model standardizes SE | Qualified Lead and SE | Converted Lead on one Google_Ads_Import worksheet. Google Ads API upload remains disabled.</p>';

		echo '<h3>Setup checklist</h3><ol>';
		echo '<li><strong>Business Data:</strong> In <a href="' . esc_url( admin_url( 'admin.php?page=business-data' ) ) . '">SiteEngine &rarr; Business Data</a>, configure Business Name, Business Timezone, Currency, and Google Ads Customer ID.</li>';
		echo '<li><strong>Setup:</strong> In <a href="' . esc_url( $ads_url ) . '">SiteEngine &rarr; Ads Bridge</a>, set the stable client slug, short name, master Fluent Form, and consent policy version.</li>';
		echo '<li><strong>CRM &amp; Lifecycle:</strong> Map Customers, Future Customers, DNC, and the four authoritative lifecycle tags: New, Qualified, Unqualified, and Won. Optional lifecycle automation keeps those states mutually exclusive and stamps milestone dates.</li>';
		echo '<li><strong>Field Mapping:</strong> Confirm Fluent Form field names. The Fluent Forms &rarr; FluentCRM feed must map <code>{submission.id}</code> to the FluentCRM <code>submission_id</code> custom field. For Golden Account diagnostics, add hidden <code>se_cid</code>, <code>se_agid</code>, <code>se_adid</code>, <code>se_kw</code>, <code>se_mt</code>, <code>se_net</code>, <code>se_dev</code>, and <code>se_loc</code> fields populated from matching GET parameters.</li>';
		echo '<li><strong>Exports:</strong> Configure All Leads, Qualified Lead, and Converted Lead (Won). Qualified and Converted events share <code>Google_Ads_Import</code>. Unqualified leads stay internal; their reason is read from the configured <code>customer_rating</code>-style field and recorded in the All_Leads lifecycle ledger.</li>';
		echo '<li><strong>Google Sheets:</strong> Configure the spreadsheet and the external service-account JSON path. <code>Google_Ads_Import</code> must be the first/leftmost worksheet for Data Manager. Configure separate Data Manager filters for <code>Conversion action = SE | Qualified Lead</code> and <code>Conversion action = SE | Converted Lead</code>. Keep credentials outside the web root, normally under <code>/home/CPANEL_USER/.secrets/</code>.</li>';
		echo '<li><strong>Provision:</strong> Run <code>wp se-ads provision-fields --apply</code> only when required, migrate the ledger if absent, then run <code>wp se-ads provision-sheets --apply</code>. The standard workbook is two tabs: <code>Google_Ads_Import</code> first, then <code>All_Leads</code>.</li>';
		echo '<li><strong>Diagnostics:</strong> Run <a href="' . esc_url( $diagnostics_url ) . '">Diagnostics</a> or <code>wp se-ads doctor</code>. Resolve all FAIL results before live exports; documented operator/optional WARN results may remain.</li>';
		echo '<li><strong>Acceptance:</strong> Use targeted dry-runs and one-record <code>--apply</code> canaries. Repeat the same stage/transaction and confirm the append-only ledger skips it before scheduling <code>wp se-ads export-enabled --apply</code>.</li><li><strong>Cron:</strong> Schedule <code>export-enabled --apply</code> every 10 minutes. The Setup tab generates the site-specific cPanel command and Diagnostics warns when the last runtime is stale or uses a different PHP version.</li>';
		echo '</ol>';

		echo '<h3>Configuration authority</h3>';
		echo '<p>Normal installations use WordPress Ads Bridge settings plus shared SiteEngine Base data. Legacy external <code>SEAB_CONFIG_FILE</code> remains the highest-precedence compatibility override; plugin-local <code>config/client.php</code> is supported only as a migration fallback.</p>';

		echo '<h3>Export safety rules</h3><ul>';
		echo '<li>Writes require explicit <code>--apply</code>; preview modes write nothing.</li>';
		echo '<li>Google Sheets is the only export sink. Direct Google Ads API upload is disabled.</li>';
		echo '<li>Qualified/Converted advertising exports require authoritative receipt-backed <code>ad_user_data=granted</code> consent and must not be on DNC. <code>ad_personalization</code> remains independent and is emitted separately.</li>';
		echo '<li>Transaction IDs are deterministic: <code>ff:{form_id}:{entry_id}</code>.</li>';
		echo '<li>Export, doctor, inspection, and Sheet provisioning commands do not mutate FluentCRM. When explicitly enabled, lifecycle compliance automation may attach/detach lifecycle tags and stamp qualified/unqualified/closed dates; it never fabricates invoice revenue.</li>';
		echo '</ul>';

		echo '<h3>Google Golden Account handoff</h3>';
		echo '<ul><li>Create <code>SE | Lead</code> as the website conversion and configure GTM / Google tag to fire it from the <code>siteengine_ads_bridge_lead</code> dataLayer event. Ads Bridge exposes <code>se_transaction_id</code>, <code>se_hashed_email</code>, <code>se_hashed_phone</code>, and available Google click IDs; it does not fire Google tags directly.</li><li>Create offline <code>SE | Qualified Lead</code> and <code>SE | Converted Lead</code> actions with Count = Every for braid compatibility, then use Data Manager filters on the shared import worksheet.</li><li>Keep the canonical order ID <code>ff:{form_id}:{entry_id}</code> unchanged across Lead, Qualified, and Converted stages.</li></ul>';

		echo '<h3>Useful CLI commands</h3>';
		echo '<p><code>wp se-ads inspect-config</code><br><code>wp se-ads doctor</code><br><code>wp se-ads inspect-contact --id=&lt;contact_id&gt;</code><br><code>wp se-ads export-enabled --dry-run --limit=25</code></p>';
	}

	/**
	 * Module status reported to Base.
	 *
	 * @return array
	 */
	public static function module_status() {
		$config = SiteEngine_Ads_Bridge_Config::instance();
		$source = $config->configuration_source();

		if ( 'setup' === $source || ! SiteEngine_Ads_Bridge_Settings::exists() && 'local_file' !== $source && 'external' !== $source ) {
			return array(
				'status'  => 'setup_required',
				'label'   => 'Setup Required',
				'message' => 'Complete Ads Bridge setup before enabling exports.',
			);
		}

		$errors = $config->validate();
		if ( ! empty( $errors ) ) {
			return array(
				'status'  => 'setup_required',
				'label'   => 'Setup Required',
				'message' => (string) reset( $errors ),
			);
		}

		if ( 'local_file' === $source ) {
			return array(
				'status'  => 'warning',
				'label'   => 'Migration Available',
				'message' => 'Existing plugin-local PHP config is active. Import it into Base-managed settings when ready.',
			);
		}

		if ( 'external' === $source ) {
			return array(
				'status'  => 'warning',
				'label'   => 'External Config Active',
				'message' => 'SEAB_CONFIG_FILE currently overrides settings managed through this screen.',
			);
		}

		$doctor  = new SiteEngine_Ads_Bridge_Doctor( $config );
		$doctor->run();
		$summary = $doctor->summary();
		if ( ! empty( $summary['FAIL'] ) ) {
			return array(
				'status'  => 'error',
				'label'   => 'Needs Attention',
				'message' => sprintf( 'Doctor reports %d failing check(s).', (int) $summary['FAIL'] ),
			);
		}

		$message = empty( $summary['WARN'] ) ? 'Configuration and runtime checks are healthy.' : sprintf( 'Ready with %d doctor warning(s).', (int) $summary['WARN'] );
		return array( 'status' => 'ready', 'label' => 'Ready', 'message' => $message );
	}

	/**
	 * Warn only when Base is actually unavailable despite dependency metadata.
	 *
	 * @return void
	 */
	public static function maybe_show_base_missing_notice() {
		if ( ! current_user_can( 'manage_options' ) || class_exists( 'SiteEngine_Base_Module_Registry' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>SiteEngine Ads Bridge:</strong> SiteEngine Base 2.2 or newer is required for configuration and administration.</p></div>';
	}

	/**
	 * Render Base-owned module page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$config = SiteEngine_Ads_Bridge_Config::instance();
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'setup'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs   = array(
			'setup'       => 'Setup',
			'lifecycle'   => 'CRM & Lifecycle',
			'fields'      => 'Field Mapping',
			'exports'     => 'Exports',
			'sheets'      => 'Google Sheets',
			'compliance'  => 'Lead Compliance',
			'diagnostics' => 'Diagnostics',
			'migration'   => 'Migration',
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'setup';
		}

		echo '<div class="wrap seab-admin"><h1>SiteEngine Ads Bridge</h1>';
		self::render_source_banner( $config );

		if ( isset( $_GET['seab_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>Ads Bridge settings saved.</p></div>';
		}
		if ( isset( $_GET['seab_imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>Existing PHP configuration imported into WordPress settings.</p></div>';
		}

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $slug );
			echo '<a class="nav-tab ' . esc_attr( $slug === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		switch ( $tab ) {
			case 'lifecycle':
				self::render_lifecycle( $config );
				break;
			case 'fields':
				self::render_fields( $config );
				break;
			case 'exports':
				self::render_exports( $config );
				break;
			case 'sheets':
				self::render_sheets( $config );
				break;
			case 'compliance':
				self::render_compliance( $config );
				break;
			case 'diagnostics':
				self::render_diagnostics( $config );
				break;
			case 'migration':
				self::render_migration( $config );
				break;
			default:
				self::render_setup( $config );
		}
		echo '</div>';
	}

	/** @return void */
	private static function render_source_banner( $config ) {
		$source = $config->configuration_source();
		$labels = array(
			'settings'   => 'Base-managed WordPress settings',
			'local_file' => 'Legacy plugin-local PHP config',
			'external'   => 'External SEAB_CONFIG_FILE override',
			'setup'      => 'Setup defaults',
		);
		$label = isset( $labels[ $source ] ) ? $labels[ $source ] : $source;
		echo '<p><strong>Configuration authority:</strong> ' . esc_html( $label ) . '.</p>';
		if ( 'external' === $source ) {
			echo '<div class="notice notice-warning inline"><p><strong>External override active.</strong> You may import its values here, but UI changes will not become authoritative until the external <code>SEAB_CONFIG_FILE</code> definition is retired.</p></div>';
		} elseif ( 'local_file' === $source ) {
			echo '<div class="notice notice-info inline"><p>An existing plugin-local PHP config is active. Use the Migration tab to import it into Base-managed settings.</p></div>';
		}
	}

	/** @return void */
	private static function render_setup( $config ) {
		$forms = self::get_fluent_forms();
		$help_url = admin_url( 'admin.php?page=read-me-file&tab=ads-bridge' );
		echo '<p><a href="' . esc_url( $help_url ) . '">View Ads Bridge setup instructions in SiteEngine Help / Reference</a></p>';
		self::form_open( 'setup' );
		echo '<h2>Shared Client Data</h2><p>These values are owned by SiteEngine Base and are read-only here.</p>';
		echo '<table class="form-table"><tbody>';
		self::readonly_row( 'Business Name', function_exists( 'siteengine_get' ) ? siteengine_get( 'business_name', '' ) : $config->get( 'client.name', '' ) );
		self::readonly_row( 'Timezone', function_exists( 'siteengine_get' ) ? siteengine_get( 'timezone', '' ) : $config->get( 'client.timezone', '' ) );
		self::readonly_row( 'Currency', function_exists( 'siteengine_get' ) ? siteengine_get( 'currency', '' ) : $config->get( 'client.currency', '' ) );
		self::readonly_row( 'Google Ads Customer ID', function_exists( 'siteengine_get' ) ? siteengine_get( 'google_ads_customer_id', '' ) : $config->get( 'client.google_ads_customer_id', '' ) );
		echo '</tbody></table>';

		echo '<h2>Ads Bridge Identity</h2><table class="form-table"><tbody>';
		self::text_row( 'Client Slug', 'client_slug', $config->get( 'client.slug', '' ), 'Stable lowercase identifier, for example carriage.' );
		self::text_row( 'Short Name', 'client_short_name', $config->get( 'client.short_name', '' ), 'Used in conversion naming and operator output.' );
		echo '<tr><th><label for="seab-form-id">Master Fluent Form</label></th><td>';
		if ( ! empty( $forms ) ) {
			echo '<select id="seab-form-id" name="form_id"><option value="0">Select a form</option>';
			$current = (int) $config->get( 'fluent_forms.form_id', 0 );
			foreach ( $forms as $form ) {
				echo '<option value="' . esc_attr( $form['id'] ) . '" ' . selected( $current, $form['id'], false ) . '>' . esc_html( '#' . $form['id'] . ' — ' . $form['title'] ) . '</option>';
			}
			echo '</select>';
		} else {
			echo '<input class="small-text" type="number" min="1" id="seab-form-id" name="form_id" value="' . esc_attr( (int) $config->get( 'fluent_forms.form_id', 0 ) ) . '">';
		}
		echo '<p class="description">The single canonical Fluent Form used to establish deterministic ff:{form_id}:{submission_id} identities.</p></td></tr>';
		self::text_row( 'Consent Policy Version', 'consent_policy', $config->get( 'consent.required_policy_version', '' ), 'Must match the SiteEngine Consent policy used for authoritative receipts.' );
		echo '</tbody></table>';
		self::form_close();

		$cron = self::cron_guidance();
		echo '<hr><h2>Scheduled Export / Cron</h2>';
		echo '<p>Recommended schedule: <strong>every 10 minutes</strong>. Use <code>export-enabled</code> so intake, lifecycle-ledger snapshots, Qualified Leads, and Converted Leads run from one scheduler.</p>';
		echo '<table class="widefat striped" style="max-width:1100px"><tbody>';
		echo '<tr><th style="width:220px">Minute</th><td><code>*/10</code></td></tr><tr><th>Hour / Day / Month / Weekday</th><td><code>* &nbsp; * &nbsp; * &nbsp; *</code></td></tr>';
		echo '<tr><th>Detected site PHP</th><td><code>' . esc_html( PHP_VERSION ) . '</code> &mdash; <code>' . esc_html( $cron['php'] ) . '</code></td></tr>';
		echo '<tr><th>cPanel command</th><td><code style="word-break:break-all">' . esc_html( $cron['command'] ) . '</code></td></tr>';
		echo '<tr><th>Complete crontab line</th><td><code style="word-break:break-all">*/10 * * * * ' . esc_html( $cron['command'] ) . '</code></td></tr>';
		echo '</tbody></table>';
		echo '<p class="description">The PHP path is intentionally version-specific on cPanel EA-PHP. Ads Bridge records the runtime used by successful <code>export-enabled --apply</code> runs so Diagnostics can flag a stale scheduler or a cron still running an older PHP version after a site upgrade.</p>';
	}

	/**
	 * Generate the recommended site-specific cron command.
	 *
	 * @return array{php:string,command:string}
	 */
	private static function cron_guidance() {
		$version = explode( '.', PHP_VERSION );
		$ea_php  = '/opt/cpanel/ea-php' . ( isset( $version[0] ) ? (int) $version[0] : 8 ) . ( isset( $version[1] ) ? (int) $version[1] : 2 ) . '/root/usr/bin/php';
		$php     = is_executable( $ea_php ) ? $ea_php : PHP_BINARY;
		$wp      = is_executable( '/usr/local/bin/wp' ) ? '/usr/local/bin/wp' : '/usr/local/bin/wp';
		$path    = untrailingslashit( ABSPATH );
		$home    = dirname( $path );
		if ( preg_match( '#^(/home/[^/]+)(?:/|$)#', $path, $m ) ) {
			$home = $m[1];
		}
		$command = $php . ' -d memory_limit=512M ' . $wp . ' se-ads export-enabled --apply --limit=25 --path=' . $path . ' >> ' . $home . '/se-ads-export-enabled.log 2>&1';
		return array( 'php' => $php, 'command' => $command );
	}

	/** @return void */
	private static function render_lifecycle( $config ) {
		$lists = self::get_fluentcrm_titles( 'fc_lists' );
		$tags  = self::get_fluentcrm_titles( 'fc_tags' );
		self::render_datalist( 'seab-list-options', $lists );
		self::render_datalist( 'seab-tag-options', $tags );
		self::form_open( 'lifecycle' );
		echo '<h2>FluentCRM Lists</h2><table class="form-table"><tbody>';
		self::text_row( 'Customers', 'list_customers', $config->get( 'fluentcrm.lists.customers', '' ), '', 'seab-list-options' );
		self::text_row( 'Future Customers', 'list_future_customers', $config->get( 'fluentcrm.lists.future_customers', '' ), '', 'seab-list-options' );
		self::text_row( 'Do Not Contact', 'list_dnc', $config->get( 'fluentcrm.lists.do_not_contact', '' ), 'Membership blocks advertising exports.', 'seab-list-options' );
		echo '</tbody></table><h2>Lifecycle Tags</h2><p>The client chooses one current state: New, Qualified, Unqualified, or Won. Qualified and Won generate positive Google signals; Unqualified remains an internal outcome.</p><table class="form-table"><tbody>';
		$labels = array(
			'new'         => 'New',
			'qualified'   => 'Qualified',
			'unqualified' => 'Unqualified',
			'won'         => 'Won',
		);
		foreach ( $labels as $role => $label ) {
			self::text_row( $label, 'tag_' . $role, $config->get( 'fluentcrm.tags.lifecycle.' . $role, '' ), '', 'seab-tag-options' );
		}
		echo '</tbody></table><h2>Lifecycle Compliance Automation</h2><table class="form-table"><tbody>';
		self::checkbox_row( 'Enable Lifecycle Compliance Automation', 'lifecycle_automation_enabled', (bool) $config->get( 'fluentcrm.lifecycle_automation.enabled', false ), 'When enabled: new master-form contacts receive New; lifecycle tags are kept mutually exclusive; Qualified stamps qualified_at; Unqualified stamps unqualified_at; Won stamps qualified_at and closed_date when blank. Invoice amount is never fabricated.' );
		self::text_row( 'Unqualified Reason Field', 'unqualified_reason_field', $config->get( 'fluentcrm.lifecycle_automation.unqualified_reason_field', 'customer_rating' ), 'Existing FluentCRM custom-field slug. Canonical choices: Wrong Area, Wrong Service, Wrong Product, Wrong Price, Spam / Junk, Other.' );
		self::text_row( 'Unqualified Timestamp Field', 'unqualified_at_field', $config->get( 'fluentcrm.lifecycle_automation.unqualified_at_field', 'unqualified_at' ), 'Hidden operational date/time field stamped automatically when Unqualified is selected.' );
		self::text_row( 'Stale New Warning (days)', 'stale_new_days', $config->get( 'fluentcrm.lifecycle_automation.stale_new_days', 3 ), 'Lead Compliance flags New contacts older than this threshold.' );
		echo '</tbody></table>';
		self::form_close();
	}

	/** @return void */
	private static function render_fields( $config ) {
		self::form_open( 'fields' );
		echo '<h2>Fluent Form Field Mapping</h2><p>Map Ads Bridge canonical keys to the field names used by the configured master Fluent Form. Defaults match the standard SiteEngine lead form.</p>';
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Canonical Key</th><th>Fluent Forms Field Name</th></tr></thead><tbody>';
		foreach ( SiteEngine_Ads_Bridge_Settings::default_field_map() as $key => $default ) {
			$value = $config->get( 'fluent_forms.fields.' . $key, $default );
			echo '<tr><td><code>' . esc_html( $key ) . '</code></td><td><input class="regular-text" name="field_' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '"></td></tr>';
		}
		echo '</tbody></table>';
		self::form_close();
	}

	/** @return void */
	private static function render_exports( $config ) {
		self::form_open( 'exports' );
		echo '<h2>Export Families</h2><p>Exports remain append-only. Saving configuration does not write to Google Sheets or CRM.</p><table class="form-table"><tbody>';
		self::checkbox_row( 'All Leads', 'all_leads_enabled', (bool) $config->get( 'exports.all_leads.enabled', false ), 'Append every canonical master-form submission to the operational All_Leads sheet.' );
		self::text_row( 'All Leads Sheet Tab', 'all_leads_tab', $config->get( 'exports.all_leads.sheet_tab', 'All_Leads' ) );
		self::text_row( 'Google Import Sheet Tab', 'won_tab', $config->get( 'exports.won_jobs.sheet_tab', 'Google_Ads_Import' ), 'Shared by Qualified and Converted Lead rows. Must be the first/leftmost worksheet for Data Manager.' );

		echo '</tbody></table><h3>SE | Qualified Lead</h3><table class="form-table"><tbody>';
		self::checkbox_row( 'Enable Qualified Lead Export', 'qualified_enabled', (bool) $config->get( 'exports.qualified_leads.enabled', false ), 'Exports contacts with the configured qualification timestamp to the shared Google import tab.' );
		self::text_row( 'Qualified Conversion Action', 'qualified_action', $config->get( 'exports.qualified_leads.conversion_action', 'SE | Qualified Lead' ) );
		self::text_row( 'Qualification Timestamp Field', 'qualified_date_field', $config->get( 'exports.qualified_leads.date_field', 'qualified_at' ), 'Recommended: qualified_at (date_time), populated by the CRM automation at the moment the lead becomes qualified.' );
		self::text_row( 'Qualified Lead Proxy Value', 'qualified_value', $config->get( 'exports.qualified_leads.conversion_value', '' ), 'Optional. Leave blank for no proxy value; if used, enter a non-negative numeric value.' );

		echo '</tbody></table><h3>SE | Converted Lead</h3><table class="form-table"><tbody>';
		self::checkbox_row( 'Enable Converted Lead Export', 'won_enabled', (bool) $config->get( 'exports.won_jobs.enabled', false ), 'Won contacts only; actual positive revenue is mandatory.' );
		self::text_row( 'Converted Conversion Action', 'won_action', $config->get( 'exports.won_jobs.conversion_action', 'SE | Converted Lead' ) );
		self::text_row( 'Converted Value Field', 'won_value_field', $config->get( 'exports.won_jobs.value_field', 'invoiced_amount' ), 'Must contain actual positive revenue. Ads Bridge never fabricates fallback revenue.' );
		self::text_row( 'Outcome Date Field', 'won_date_field', $config->get( 'exports.won_jobs.date_field', 'closed_date' ), 'Recommended canonical field: closed_date (label it Outcome Date in CRM).' );

		echo '</tbody></table><p><strong>Unqualified leads are intentionally not exported as Google conversions.</strong> Their current state and reason are retained in the All_Leads lifecycle ledger.</p>';
		self::form_close();
	}

	/** @return void */
	private static function render_sheets( $config ) {
		self::form_open( 'sheets' );
		echo '<h2>Google Sheets Sink</h2><table class="form-table"><tbody>';
		self::checkbox_row( 'Enable Google Sheets', 'sheets_enabled', (bool) $config->get( 'google_sheets.enabled', false ), 'Required before any export family can be enabled.' );
		self::text_row( 'Spreadsheet ID', 'spreadsheet_id', $config->get( 'google_sheets.spreadsheet_id', '' ), 'Paste the spreadsheet ID, not a service-account secret.' );
		self::text_row( 'Service Account JSON Path', 'credentials_path', $config->get( 'google_sheets.credentials_path', '' ), 'Recommended: /home/CPANEL_USER/.secrets/se-ads-sa.json' );
		echo '</tbody></table><p><strong>Credentials stay outside WordPress.</strong> Ads Bridge stores only the filesystem path to the JSON credential file.</p>';
		self::form_close();
	}


	/** @return void */
	private static function render_compliance( $config ) {
		$report  = SiteEngine_Ads_Bridge_Lifecycle::compliance_report( 500 );
		$summary = isset( $report['summary'] ) ? (array) $report['summary'] : array();
		$total   = array_sum( array_map( 'intval', $summary ) );
		echo '<h2>Lead Compliance</h2>';
		echo '<p>This is a read-only work queue. Lifecycle automation may prevent common state errors, but the client still owns the business judgment and invoice amount.</p>';
		echo '<p><strong>' . esc_html( sprintf( '%d issue(s) across %d inspected contact(s).', $total, (int) $report['inspected'] ) ) . '</strong></p>';
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Issue</th><th>Count</th></tr></thead><tbody>';
		$labels = array(
			'no_lifecycle' => 'No lifecycle state',
			'conflict' => 'Lifecycle conflict',
			'stale_new' => 'Stale New',
			'qualified_incomplete' => 'Qualified incomplete',
			'unqualified_incomplete' => 'Unqualified incomplete',
			'won_incomplete' => 'Won incomplete',
		);
		foreach ( $labels as $key => $label ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( isset( $summary[ $key ] ) ? (int) $summary[ $key ] : 0 ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( ! empty( $report['issues'] ) ) {
			echo '<h3>Contacts Needing Attention</h3><table class="widefat striped" style="max-width:1200px"><thead><tr><th>Contact ID</th><th>Contact</th><th>Lifecycle</th><th>Issue</th><th>Detail</th></tr></thead><tbody>';
			foreach ( array_slice( $report['issues'], 0, 100 ) as $row ) {
				echo '<tr><td>' . esc_html( $row['contact_id'] ) . '</td><td>' . esc_html( $row['contact'] ) . '</td><td>' . esc_html( $row['lifecycle'] ) . '</td><td>' . esc_html( $row['issue'] ) . '</td><td>' . esc_html( $row['detail'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<p style="margin-top:16px">CLI equivalent: <code>wp se-ads compliance</code></p>';
	}

	/** @return void */
	private static function render_diagnostics( $config ) {
		$doctor  = new SiteEngine_Ads_Bridge_Doctor( $config );
		$results = $doctor->run();
		$summary = $doctor->summary();
		echo '<h2>Diagnostics</h2><p><strong>' . esc_html( sprintf( '%d PASS · %d WARN · %d FAIL · %d PENDING', $summary['PASS'], $summary['WARN'], $summary['FAIL'], $summary['PENDING'] ) ) . '</strong></p>';
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Status</th><th>Check</th><th>Message</th></tr></thead><tbody>';
		foreach ( $results as $row ) {
			echo '<tr><td><strong>' . esc_html( $row['status'] ) . '</strong></td><td>' . esc_html( $row['check'] ) . '</td><td>' . esc_html( $row['message'] ) . '</td></tr>';
		}
		echo '</tbody></table><p style="margin-top:16px">CLI equivalent: <code>wp se-ads doctor</code></p>';
	}

	/** @return void */
	private static function render_migration( $config ) {
		$source = $config->configuration_source();
		echo '<h2>Configuration Migration</h2>';
		echo '<p>Current authority: <strong>' . esc_html( $source ) . '</strong></p>';
		if ( 'local_file' === $source || 'external' === $source ) {
			echo '<p>Ads Bridge can copy the currently resolved schema-v2 configuration into WordPress settings. Shared client values will then be supplied by SiteEngine Base.</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="seab_import_config">';
			wp_nonce_field( 'seab_import_config' );
			submit_button( 'Import Current Configuration', 'primary', 'submit', false );
			echo '</form>';
			if ( 'external' === $source ) {
				echo '<div class="notice notice-warning inline"><p>The import does not edit <code>wp-config.php</code>. The external <code>SEAB_CONFIG_FILE</code> definition will continue to override imported settings until you deliberately retire that legacy override.</p></div>';
			} else {
				echo '<p>For plugin-local config installations, imported settings become authoritative on the next request. The old file may remain as an inert fallback until cleanup.</p>';
			}
		} elseif ( 'settings' === $source ) {
			echo '<p>Ads Bridge is already using Base-managed WordPress settings. No config migration is required.</p>';
		} else {
			echo '<p>No legacy PHP configuration is active. Complete setup using the other tabs.</p>';
		}
	}

	/**
	 * Save one UI tab while preserving unexposed fields and mappings.
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}
		check_admin_referer( 'seab_save_settings' );
		$tab = isset( $_POST['seab_tab'] ) ? sanitize_key( wp_unslash( $_POST['seab_tab'] ) ) : 'setup';

		if ( SiteEngine_Ads_Bridge_Settings::exists() ) {
			$data = SiteEngine_Ads_Bridge_Settings::get();
		} else {
			$data = SiteEngine_Ads_Bridge_Config::instance()->all();
			if ( empty( $data ) ) {
				$data = SiteEngine_Ads_Bridge_Settings::defaults();
			}
		}
		$data = SiteEngine_Ads_Bridge_Settings::merge( SiteEngine_Ads_Bridge_Settings::defaults(), $data );

		if ( 'setup' === $tab ) {
			$data['client']['slug'] = self::post_text( 'client_slug' );
			$data['client']['short_name'] = self::post_text( 'client_short_name' );
			$data['fluent_forms']['form_id'] = self::post_int( 'form_id' );
			$data['consent']['required_policy_version'] = self::post_text( 'consent_policy' );
		} elseif ( 'lifecycle' === $tab ) {
			$data['fluentcrm']['lists']['customers'] = self::post_text( 'list_customers' );
			$data['fluentcrm']['lists']['future_customers'] = self::post_text( 'list_future_customers' );
			$data['fluentcrm']['lists']['do_not_contact'] = self::post_text( 'list_dnc' );
			foreach ( SiteEngine_Ads_Bridge_Config::lifecycle_roles() as $role ) {
				$data['fluentcrm']['tags']['lifecycle'][ $role ] = self::post_text( 'tag_' . $role );
			}
			$data['fluentcrm']['lifecycle_automation']['enabled'] = self::post_bool( 'lifecycle_automation_enabled' );
			$data['fluentcrm']['lifecycle_automation']['unqualified_reason_field'] = self::post_text( 'unqualified_reason_field' );
			$data['fluentcrm']['lifecycle_automation']['unqualified_at_field'] = self::post_text( 'unqualified_at_field' );
			$data['fluentcrm']['lifecycle_automation']['stale_new_days'] = max( 1, self::post_int( 'stale_new_days' ) );
		} elseif ( 'fields' === $tab ) {
			foreach ( SiteEngine_Ads_Bridge_Settings::default_field_map() as $key => $default ) {
				$value = self::post_text( 'field_' . $key );
				$data['fluent_forms']['fields'][ $key ] = '' !== $value ? $value : $default;
			}
		} elseif ( 'exports' === $tab ) {
			$data['exports']['all_leads']['enabled'] = self::post_bool( 'all_leads_enabled' );
			$data['exports']['all_leads']['sheet_tab'] = self::post_text( 'all_leads_tab' );
			$data['exports']['qualified_leads']['enabled'] = self::post_bool( 'qualified_enabled' );
			$data['exports']['qualified_leads']['conversion_action'] = self::post_text( 'qualified_action' );
			$data['exports']['qualified_leads']['date_field'] = self::post_text( 'qualified_date_field' );
			$data['exports']['qualified_leads']['conversion_value'] = self::post_text( 'qualified_value' );
			$data['exports']['won_jobs']['enabled'] = self::post_bool( 'won_enabled' );
			$data['exports']['won_jobs']['sheet_tab'] = self::post_text( 'won_tab' );
			$data['exports']['won_jobs']['conversion_action'] = self::post_text( 'won_action' );
			$data['exports']['won_jobs']['value_field'] = self::post_text( 'won_value_field' );
			$data['exports']['won_jobs']['date_field'] = self::post_text( 'won_date_field' );
			$data['exports']['lost_leads']['enabled'] = false; // Retired in 0.5.1.
		} elseif ( 'sheets' === $tab ) {
			$data['google_sheets']['enabled'] = self::post_bool( 'sheets_enabled' );
			$data['google_sheets']['spreadsheet_id'] = self::post_text( 'spreadsheet_id' );
			$data['google_sheets']['credentials_path'] = self::post_text( 'credentials_path' );
		}

		SiteEngine_Ads_Bridge_Settings::save( $data );
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $tab . '&seab_saved=1' );
		wp_safe_redirect( $url );
		exit;
	}

	/** @return void */
	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}
		check_admin_referer( 'seab_import_config' );
		$config = SiteEngine_Ads_Bridge_Config::instance();
		if ( ! in_array( $config->configuration_source(), array( 'local_file', 'external' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=migration' ) );
			exit;
		}
		SiteEngine_Ads_Bridge_Settings::save( $config->all() );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=migration&seab_imported=1' ) );
		exit;
	}

	/** @return void */
	private static function form_open( $tab ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="seab_save_settings"><input type="hidden" name="seab_tab" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'seab_save_settings' );
	}

	/** @return void */
	private static function form_close() {
		submit_button( 'Save Ads Bridge Settings' );
		echo '</form>';
	}

	/** @return void */
	private static function readonly_row( $label, $value ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><code>' . esc_html( '' === (string) $value ? '(not configured)' : (string) $value ) . '</code> <span style="color:#646970">SiteEngine Base</span></td></tr>';
	}

	/** @return void */
	private static function text_row( $label, $name, $value, $description = '', $list = '' ) {
		echo '<tr><th><label for="seab-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" type="text" id="seab-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . ( '' !== $list ? ' list="' . esc_attr( $list ) . '"' : '' ) . '>';
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	/** @return void */
	private static function checkbox_row( $label, $name, $checked, $description = '' ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( $checked, true, false ) . '> Enabled</label>';
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	/** @return void */
	private static function render_datalist( $id, $values ) {
		if ( empty( $values ) ) {
			return;
		}
		echo '<datalist id="' . esc_attr( $id ) . '">';
		foreach ( $values as $value ) {
			echo '<option value="' . esc_attr( $value ) . '"></option>';
		}
		echo '</datalist>';
	}

	/**
	 * Enumerate Fluent Forms without depending on a particular Fluent Forms API.
	 *
	 * @return array
	 */
	private static function get_fluent_forms() {
		global $wpdb;
		$table = $wpdb->prefix . 'fluentform_forms';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return array();
		}
		$rows = $wpdb->get_results( "SELECT id, title FROM {$table} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Enumerate current FluentCRM list/tag titles for operator convenience.
	 *
	 * @param string $suffix Table suffix.
	 * @return array
	 */
	private static function get_fluentcrm_titles( $suffix ) {
		global $wpdb;
		$table = $wpdb->prefix . sanitize_key( $suffix );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return array();
		}
		$values = $wpdb->get_col( "SELECT title FROM {$table} WHERE title <> '' ORDER BY title ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return is_array( $values ) ? array_values( array_unique( array_map( 'strval', $values ) ) ) : array();
	}

	/** @return string */
	private static function post_text( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/** @return int */
	private static function post_int( $key ) {
		return isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : 0;
	}

	/** @return bool */
	private static function post_bool( $key ) {
		return ! empty( $_POST[ $key ] );
	}
}
