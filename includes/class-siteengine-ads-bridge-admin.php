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
    private static $combined_form = false;

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
		add_action('admin_init',static function(){if(($_GET['page']??'')===self::PAGE_SLUG)SiteEngine_Ads_Bridge_Setup::prepare_storage_for_admin();});
		add_action( 'admin_post_seab_save_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_seab_import_config', array( __CLASS__, 'handle_import' ) );
		add_action('admin_post_seab_use_settings',array(__CLASS__,'handle_authority'));
		add_action( 'admin_post_seab_initialize_storage', array( __CLASS__, 'handle_storage' ) );
		add_action('admin_post_seab_provision_fields',array(__CLASS__,'handle_fields'));
        add_action('admin_post_seab_crm_requirements',array(__CLASS__,'handle_crm_requirements'));
        add_action('admin_post_seab_provision_fieldedge',array(__CLASS__,'handle_fieldedge_fields'));
        add_action('wp_ajax_seab_test_sheets',array(__CLASS__,'test_sheets'));
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_base_missing_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_doctor_failures' ) );
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

        echo '<h2>Ads Bridge</h2><p>Setup has one Save settings action. Incomplete configuration is saveable. Shared Business Data is read-only from Base; Consent owns the required policy. Set up CRM requirements creates only missing definitions, never marketing content or contacts. Select the lead form and inspect its separate FluentCRM integration. Configuration checks do not prove contact delivery.</p><p>Test connection reads spreadsheet identity with entered draft values, without saving or appending rows. A successful read does not prove write permission. Select the Google Ads account timezone independently of business timezone. Enable only explicitly authorized features; saving never runs exports.</p><p>API Setup contains dormant connector information. Settings contains custom field overrides, detailed diagnostics, preserved-operation recovery and scheduler instructions. Printed commands do not prove cron installation. Existing imported content, smartcodes and journals are preserved. No production FieldEdge creation or call advertising is enabled.</p>';

	}

	/**
	 * Module status reported to Base.
	 *
	 * @return array
	 */
	public static function module_status() {
		$config = SiteEngine_Ads_Bridge_Config::instance();
		$source = $config->configuration_source();
		$issues = self::setup_issues( $config );
		if ( $issues ) {
			$messages = array();
			foreach ( $issues as $issue ) {
				$messages[] = $issue['label'] . ': ' . $issue['message'] . ' Open ' . $issue['destination'] . '.';
			}
			if ( 'external' === $source ) {
				$messages[] = 'External configuration is active; UI saves do not replace SEAB_CONFIG_FILE. Open Ads Bridge > Settings > Detailed diagnostics > Configuration authority.';
			}
			return array(
				'status'  => 'setup_required',
				'label'   => 'Setup Required',
				'message' => implode( ' ', $messages ),
				'setup_errors' => $issues,
			);
		}

		$doctor  = new SiteEngine_Ads_Bridge_Doctor( $config );
		$results = $doctor->run();
		$summary = $doctor->summary();
		$failures=self::doctor_failures($results);
		if ( $failures ) {
			$diagnostics = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=diagnostics' );
			$messages = array();
			foreach ( $failures as $failure ) {
				$message = $failure['check'] . ': ' . $failure['message'];
				if ( $failure['corrective_url'] ) { $message .= ' Review ' . $failure['corrective_label'] . ': ' . $failure['corrective_url']; }
				$messages[] = $message;
			}
			return array(
				'status'  => 'error',
				'label'   => 'Needs Attention',
				'message' => sprintf( '%d Doctor failure(s). ', count( $failures ) ) . implode( ' ', $messages ) . ' Open Ads Bridge Diagnostics: ' . $diagnostics,
				'doctor_failures' => $failures,
				'diagnostics_url' => $diagnostics,
			);
		}

		if ( 'local_file' === $source ) {
			return array(
				'status'  => 'warning',
				'label'   => 'Legacy settings source',
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

		$message = empty( $summary['WARN'] ) ? 'Configuration and runtime checks are healthy.' : sprintf( 'Ready with %d doctor warning(s).', (int) $summary['WARN'] );
		return array( 'status' => 'ready', 'label' => 'Ready', 'message' => $message );
	}

	/** Keep Doctor severity/text intact; only known check destinations are attached. */
	public static function doctor_failures( $results ) {
		$tabs = array(
			'Config schema' => 'setup', 'Config shape' => 'setup',
			'Lifecycle tags' => 'lifecycle', 'FluentCRM lifecycle tags' => 'lifecycle',
			'FluentCRM lists' => 'lifecycle', 'FluentCRM source tags' => 'lifecycle',
			'Inquiry attribution layer' => 'storage', 'Consent policy version' => 'setup',
			'Export family: qualified_leads' => 'exports', 'Export family: converted_leads' => 'exports',
			'Export family: all_leads' => 'exports', 'Export tab uniqueness' => 'exports',
			'FluentCRM custom fields' => 'fields', 'Google Ads timezone' => 'setup', 'submission_id pipeline' => 'fields',
			'Google Sheets sink' => 'sheets', 'Google Sheets credentials' => 'sheets', 'Google Sheets value input' => 'sheets',
		);
		$labels = array( 'setup' => 'Ads Bridge Setup', 'lifecycle' => 'Ads Bridge CRM & Lifecycle', 'storage' => 'Ads Bridge Storage', 'exports' => 'Ads Bridge Exports', 'fields' => 'Ads Bridge Field Mapping', 'sheets' => 'Ads Bridge Google Sheets' );
		$out = array();
		foreach ( (array) $results as $row ) {
			if ( ! is_array( $row ) || ( $row['status'] ?? '' ) !== 'FAIL' ) { continue; }
			$row['corrective_label'] = '';
			$row['corrective_url'] = '';
			$check = (string) ( $row['check'] ?? '' );
            if($check==='Config shape'&&preg_match('/\b(client|consent|fluent_forms)\.[a-z_]+(?:\.[a-z_]+)*/',$row['message'],$match)){
                $key=$match[0];$names=['client.name'=>'Business Name','client.timezone'=>'Business Timezone','client.currency'=>'Currency','client.google_ads_customer_id'=>'Google Ads Customer ID','client.slug'=>'Client Slug','client.short_name'=>'Short Name','consent.required_policy_version'=>'Required Consent Policy','fluent_forms.form_id'=>'Lead form'];
                $row['message']=str_replace($key,$names[$key]??ucwords(str_replace(['.','_'],' ',$key)),$row['message']);
                if(in_array($key,['client.name','client.timezone','client.currency','client.google_ads_customer_id'],true)){$row['corrective_label']='Business Data';$row['corrective_url']=admin_url('admin.php?page=business-data');$out[]=$row;continue;}
            }
			if ( isset( $tabs[ $check ] ) ) {
				$row['corrective_label'] = $labels[ $tabs[ $check ] ];
				$row['corrective_url'] = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $tabs[ $check ] );
			} elseif ( in_array( $check, array( 'Config timezone', 'Google Ads CID', 'Currency' ), true ) ) {
				$row['corrective_label'] = 'Business Data';
				$row['corrective_url'] = admin_url( 'admin.php?page=business-data' );
			} elseif ( in_array( $check, array( 'Fluent Forms', 'FluentCRM', 'SiteEngine Consent', 'SiteEngine Base', 'SiteEngine Business Data' ), true ) ) {
				$row['corrective_label'] = 'Plugins';
				$row['corrective_url'] = admin_url( 'plugins.php' );
			}
			$out[] = $row;
		}
		return $out;
	}

	/** Base renders status messages as plain text; supply clickable Ads-owned links. */
	public static function maybe_show_doctor_failures() {
        if(($_GET['page']??'')===self::PAGE_SLUG)return;
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || ! in_array( $page, array( self::PAGE_SLUG, 'siteengine-dashboard' ), true ) ) { return; }
		$status = self::module_status();
		if ( empty( $status['doctor_failures'] ) ) { return; }
		echo '<div class="notice notice-error inline seab-doctor-failures"><h2>Ads Bridge needs attention</h2><ul>';
		foreach ( $status['doctor_failures'] as $failure ) {
			echo '<li><strong>' . esc_html( $failure['check'] ) . ':</strong> ' . esc_html( $failure['message'] );
			if ( $failure['corrective_url'] ) { echo ' <a href="' . esc_url( $failure['corrective_url'] ) . '">' . esc_html( $failure['corrective_label'] ) . '</a>'; }
			echo '</li>';
		}
		echo '</ul><p><a href="' . esc_url( $status['diagnostics_url'] ) . '">Open Ads Bridge Diagnostics</a></p></div>';
	}

	/** Reporting only: retain every validation failure and stored Base prerequisite. */
	public static function setup_issues( $config = null ) {
		$config = $config ?: SiteEngine_Ads_Bridge_Config::instance();
		$shared = array( 'client.name' => array( 'business_name', 'Business Name' ), 'client.timezone' => array( 'timezone', 'Business Timezone' ), 'client.currency' => array( 'currency', 'Currency' ), 'client.google_ads_customer_id' => array( 'google_ads_customer_id', 'Google Ads Customer ID' ) );
		$labels = array( 'setup' => 'Ads setup', 'client.slug' => 'Client Slug', 'client.short_name' => 'Short Name', 'consent.required_policy_version' => 'Required Consent Policy Version', 'fluent_forms.form_id' => 'Master Fluent Form' );
		foreach ( $shared as $key => $definition ) { $labels[ $key ] = $definition[1]; }
		foreach ( array( 'new', 'qualified', 'unqualified', 'won' ) as $role ) { $labels[ 'fluentcrm.tags.lifecycle.' . $role ] = ucfirst( $role ) . ' lifecycle tag'; }
		$issues = array();
		$labels[ 'google_sheets.account_timezone' ] = 'Google Ads account timezone';
		$add = static function( $key, $message ) use ( &$issues, $shared, $labels ) {
			$base = isset( $shared[ $key ] );
			$tab = 'setup';
			if ( strpos( $key, 'fluentcrm.' ) === 0 ) { $tab = 'lifecycle'; }
			if ( strpos( $key, 'fluent_forms.fields.' ) === 0 ) { $tab = 'fields'; }
			if ( strpos( $key, 'exports.' ) === 0 ) { $tab = 'exports'; }
			if ( strpos( $key, 'google_sheets.' ) === 0 ) { $tab = 'sheets'; }
			if ( strpos( $key, 'storage.' ) === 0 ) { $tab = 'storage'; }
			$names = array( 'setup' => 'Setup', 'lifecycle' => 'Setup > CRM requirements', 'fields' => 'Settings > Advanced field overrides', 'exports' => 'Setup > Export configuration', 'sheets' => 'Setup > Google Sheets', 'storage' => 'Settings > Detailed diagnostics' );
			$issues[] = array( 'key' => $key, 'label' => $labels[ $key ] ?? ( $key ? ucwords( str_replace( array( '.', '_' ), ' ', $key ) ) : 'Ads configuration' ), 'message' => str_replace($key?:'__no_key__',$labels[$key]??ucwords(str_replace(array('.','_'),' ',$key)),(string)$message), 'destination' => $base ? 'Business Data' : 'Ads setup > ' . $names[ $tab ], 'url' => $base ? self::business_data_url($shared[$key][0]) : admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=' . $tab) );
		};
		$source = $config->configuration_source();
		if ( 'setup' === $source || ( ! SiteEngine_Ads_Bridge_Settings::exists() && ! in_array( $source, array( 'local_file', 'external' ), true ) ) ) {
			$add( 'setup', 'Settings have not been saved. Complete and save Ads setup; review every failure below.' );
		}
		if ( function_exists( 'siteengine_has' ) ) {
			foreach ( $shared as $key => $definition ) {
				if ( ! siteengine_has( $definition[0] ) ) { $add( $key, 'No explicit value is saved in Business Data. A runtime fallback does not complete this prerequisite.' ); }
			}
		}
		foreach ( $config->validate() as $error ) {
			preg_match( '/\b(?:client|fluent_forms|fluentcrm|exports|google_sheets|consent|diagnostics)\.[a-z_]+(?:\.[a-z_]+)*/', (string) $error, $match );
			$key = $match[0] ?? '';
			if ( ! $key ) {
				foreach ( array( 'client_name' => 'client.name', 'timezone' => 'client.timezone', 'currency' => 'client.currency', 'google_ads_cid' => 'client.google_ads_customer_id', 'client_slug' => 'client.slug', 'client_short_name' => 'client.short_name' ) as $legacy => $canonical ) {
					if ( preg_match( '/\b' . preg_quote( $legacy, '/' ) . '\b/', (string) $error ) ) { $key = $canonical; break; }
				}
			}
			$add( $key, $error );
		}
		if ( class_exists( 'SiteEngine_Ads_Bridge_Inquiries' ) && class_exists( 'SiteEngine_Ads_Bridge_Coordination' ) ) {
			if ( method_exists( $config, 'get' ) ) {
				global $wpdb;
				foreach ( SiteEngine_Ads_Bridge_Config::lifecycle_roles() as $role ) {
					$key = 'fluentcrm.tags.lifecycle.' . $role;
					$title = $config->get( $key, '' );
					if ( ! is_string( $title ) || '' === trim( $title ) ) { continue; }
					$table = $wpdb->prefix . 'fc_tags';
					$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE BINARY title = %s", $title ) );
					if ( $wpdb->last_error || ! is_array( $ids ) || count( $ids ) !== 1 ) { $add( $key, 'The selected title must match exactly one existing FluentCRM tag. Use Setup > Set up CRM requirements for missing tags. Resolve duplicate titles in FluentCRM before provisioning; custom role overrides are in Settings > Advanced field overrides.' ); }
				}
				if ( (int) $config->get( 'fluent_forms.form_id', 0 ) <= 0 ) { $add( 'fluent_forms.form_id', 'Select the existing master form in Ads Bridge > Setup.' ); }
			}
			if ( ! SiteEngine_Ads_Bridge_Inquiries::table_exists() || ! SiteEngine_Ads_Bridge_Inquiries::schema_ready() ) {
				$add( 'storage.inquiries', 'Required inquiry storage is not initialized at schema 2. Normal setup prepares absent storage automatically. Open Settings > Detailed diagnostics for the exact blocker; existing incompatible schemas are preserved.' );
			} else {
				$guard = SiteEngine_Ads_Bridge_Coordination::migrate( false );
				if ( $guard['status'] !== 'preview' || empty( $guard['exists_before'] ) ) { $add( 'storage.configuration_guard', 'Required configuration guard storage is missing, incomplete or blocked: ' . $guard['reason_code'] . '. Open Settings > Detailed diagnostics for pending-work diagnosis.' ); }
			}
		}
        if ( class_exists( 'SiteEngine_Ads_Bridge_Product_Setup' ) ) {
            $form_id = (int) $config->get( 'fluent_forms.form_id', 0 );
            if ( $form_id > 0 ) {
                $feed = SiteEngine_Ads_Bridge_Product_Setup::feed( $form_id );
                if ( ! $feed['configured'] ) {
                    $issues[] = array( 'key' => 'fluent_forms.feed', 'label' => 'FluentCRM form integration', 'message' => $feed['message'], 'destination' => 'FluentCRM integration', 'url' => $feed['url'] );
                }
            }
            if ( ! $config->get( 'fluentcrm.lifecycle_automation.enabled', false ) ) {
                $add( 'fluentcrm.lifecycle_automation.enabled', 'Enable Ads lifecycle handling in Setup so lead states and qualification times are recorded.' );
            }
            try {
                $requirements = SiteEngine_Ads_Bridge_Product_Setup::crm( false );
                if ( array_filter( $requirements['terms'], static fn( $term ) => ! $term['id'] ) || $requirements['fields']['missing'] ) {
                    $add( 'fluentcrm.requirements', 'Required CRM definitions are missing. Use Set up CRM requirements on this page.' );
                }
            } catch ( Throwable $error ) {
                $add( 'fluentcrm.requirements', $error->getMessage() );
            }
        }
		return $issues;
	}

	/** Escaped, actionable list for the Ads screen; module messages stay plain text. */
	public static function render_setup_issues( $config ) {
		$issues = self::setup_issues( $config );
		if ( ! $issues ) { echo '<p>Setup configuration complete.</p>'; return; }
		echo '<div class="notice notice-error inline"><h2>Complete Ads Bridge setup</h2><ul>';
		foreach ( $issues as $issue ) {
			echo '<li><strong>' . esc_html( $issue['label'] ) . ':</strong> ' . esc_html( $issue['message'] ) . ' <a href="' . esc_url( $issue['url'] ) . '">Open ' . esc_html( $issue['destination'] ) . '</a></li>';
		}
		echo '</ul></div>';
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
        if(!current_user_can('manage_options'))return;
        $config=SiteEngine_Ads_Bridge_Config::instance();$requested=sanitize_key(wp_unslash($_GET['tab']??'setup'));
        $tab=in_array($requested,['api','settings'],true)?$requested:(in_array($requested,['advanced','fields','diagnostics','storage','migration','leads','compliance'],true)?'settings':'setup');
        $section=sanitize_key(wp_unslash($_GET['section']??$requested));
        echo '<div class="wrap seab-admin"><h1>SiteEngine Ads Bridge</h1><style>.seab-primary{display:flex;gap:20px;margin:20px 0}.seab-admin .form-table input,.seab-admin select{max-width:100%}#seab-ordinary-settings .submit{position:sticky;bottom:0;padding:12px;background:#f0f0f1;z-index:2}.seab-admin pre{white-space:pre-wrap;overflow-wrap:anywhere}@media(max-width:600px){.seab-admin .form-table th,.seab-admin .form-table td{display:block;width:auto}}</style><nav class="seab-primary" aria-label="Ads Bridge">';
        foreach(['setup'=>'Setup','api'=>'API Setup','settings'=>'Settings']as$key=>$title)echo '<a '.($key===$tab?'aria-current="page" ':'').'href="'.esc_url(admin_url('admin.php?page='.self::PAGE_SLUG.'&tab='.$key)).'">'.esc_html($title).'</a>';echo '</nav>';
        if(isset($_GET['seab_saved']))echo '<div class="notice notice-success" role="status"><p>Settings saved. These are the persisted values; disabled features remain disabled.</p></div>';
        if(isset($_GET['seab_authority_saved']))echo '<div class="notice notice-success" role="status"><p>WordPress settings selected. Original configuration files are preserved. Review and save ordinary settings below.</p></div>';
        if(isset($_GET['seab_imported']))echo '<div class="notice notice-success" role="status"><p>Legacy settings imported; original file preserved. Review the saved values below.</p></div>';
        if(isset($_GET['seab_crm_ready']))echo '<div class="notice notice-success" role="status"><p>CRM requirements checked. Missing definitions created; existing definitions and contact values preserved. No email or enrollment was enabled.</p></div>';
        if($tab==='setup')self::render_setup_workflow($config,$section);
        elseif($tab==='api')self::render_api();
        else self::render_advanced($config,$section);
        echo '</div>';
    }
    private static function render_api() {
        echo '<h2>API Setup</h2><nav class="nav-tab-wrapper" aria-label="Connectors"><span class="nav-tab nav-tab-active">FieldEdge</span></nav><p>Disabled. No automatic synchronization, lead creation or provider request is enabled by this page.</p>';
        self::render_fieldedge_status();
    }
	private static function render_fieldedge_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		try {
			$status = \SiteEngine\AdsBridge\Connectors\FieldEdge\ConnectorDiagnostics::wordpress();
		} catch ( \Throwable $error ) {
			echo '<p>FieldEdge diagnostics unavailable. No connection or transport state was changed.</p>';
			return;
		}

		$labels = array(
			'crm_contact_api'         => 'FluentCRM contact API',
			'crm_field_registry_read' => 'CRM field registry',
			'crm_field_schema'        => 'FieldEdge CRM field definitions',
			'crm_projection'          => 'Customer/job projection',
			'ab_prospective_contract' => 'Ads Bridge inquiry bridge',
			'provider_connection'     => 'FieldEdge connection',
			'credential_state'        => 'Credentials',
			'lead_creation'           => 'FieldEdge lead creation',
			'provider_lead_job_link'  => 'Lead-to-work-order relationship',
			'automatic_sync'          => 'Automatic synchronization',
			'consent_and_dnc'         => 'Consent and DNC',
			'advertising_attribution' => 'Advertising attribution',
		);

		echo '<p>Read-only diagnostics. Provider authentication is not tested here. Real FieldEdge lead creation, automatic synchronization and conversion delivery remain disabled.</p>';
		echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th scope="col">Capability</th><th scope="col">Current state</th></tr></thead><tbody>';
		foreach ( $labels as $key => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) ( $status[ $key ] ?? 'unavailable' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';

		foreach ( array( 'crm_fields_missing' => 'Missing FieldEdge definitions', 'crm_fields_type_conflict' => 'Conflicting FieldEdge definitions' ) as $key => $label ) {
			if ( ! empty( $status[ $key ] ) && is_array( $status[ $key ] ) ) {
				echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( implode( ', ', $status[ $key ] ) ) . '</p>';
			}
		}

		if ( isset( $_GET['seab_fe_fields'] ) && 'ready' === ( $status['crm_field_schema'] ?? '' ) ) {
			echo '<div class="notice notice-success inline"><p>FieldEdge CRM definitions verified. No contacts or provider records were changed.</p></div>';
		}
		if ( 'missing_fields' === ( $status['crm_field_schema'] ?? '' ) && 'available' === ( $status['crm_field_registry_read'] ?? '' ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="seab_provision_fieldedge">';
			wp_nonce_field( 'seab_provision_fieldedge' );
			echo '<p><label><input type="checkbox" name="confirm" value="1" required> Create only the missing FieldEdge custom fields. Existing CRM definitions and contact values will be preserved.</label></p>';
			submit_button( 'Create Missing FieldEdge Fields', 'secondary', 'submit', false );
			echo '</form>';
		}
		echo '<p>This status does not enable FieldEdge access or alter Consent, subscriptions, job records or exports. Field provisioning requires an explicit administrator confirmation.</p>';
	}

	public static function handle_fieldedge_fields() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}
		check_admin_referer( 'seab_provision_fieldedge' );
		if ( self::post_text( 'confirm' ) !== '1' ) {
			wp_die( 'Explicit FieldEdge field provisioning confirmation required.' );
		}
		try {
			SiteEngine_Ads_Bridge_Setup::with_configuration(
				static function () {
					return SiteEngine_Ads_Bridge_Fields::provision(
						true,
						\SiteEngine\AdsBridge\Connectors\FieldEdge\CRMProjection::definitions()
					);
				}
			);
		} catch ( \Throwable $error ) {
			wp_die( esc_html( $error->getMessage() ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=api&seab_fe_fields=1' ) );
		exit;
	}
    /** Resolve the actual Base/ACF field identity, including legacy definitions. */
    private static function business_data_url($key='') {
        $url=admin_url('admin.php?page=business-data');if($key===''||!function_exists('acf_get_field_groups')||!function_exists('acf_get_fields'))return $url;
        $name=$key==='google_ads_customer_id'?'bd_google_ads_id':'bd_'.$key;$seen=0;$found=null;
        $walk=static function($rows)use(&$walk,&$seen,&$found,$name){foreach((array)$rows as$f){if(++$seen>500)return;if(!is_array($f))continue;if(($f['name']??null)===$name&&is_string($f['key']??null)){$found=$f['key'];return;}if(isset($f['sub_fields']))$walk($f['sub_fields']);}};
        try{$groups=acf_get_field_groups(['options_page'=>'business-data']);if(!is_array($groups)||count($groups)>20)return $url;foreach($groups as$g){$walk(acf_get_fields($g));if($found)return $url.'#acf-'.sanitize_key($found);if($seen>500)break;}}catch(Throwable $e){}
        return $url;
    }
    private static function render_shared_business($config) {
        echo '<p>Shared facts come from SiteEngine Base. <a href="'.esc_url(admin_url('admin.php?page=business-data')).'">Edit Business Data</a></p><table class="form-table"><tbody>';
        foreach(['business_name'=>['Business Name','name'],'timezone'=>['Business Timezone','timezone'],'currency'=>['Currency','currency'],'google_ads_customer_id'=>['Google Ads Customer ID','google_ads_customer_id']]as$key=>$label){$v=function_exists('siteengine_get')?siteengine_get($key,''):$config->get('client.'.$label[1],'');self::readonly_row($label[0],$v);if(function_exists('siteengine_has')&&!siteengine_has($key))echo '<tr><td colspan="2">Missing '.esc_html($label[0]).' — <a href="'.esc_url(self::business_data_url($key)).'">complete this Business Data field</a>.</td></tr>';}
        echo '</tbody></table>';
    }
    private static function render_setup_workflow($config,$section) {
        echo '<h2>Setup</h2><p>Save incomplete configuration at any time. Saving does not run exports, send email or enroll contacts.</p>';
        self::render_shared_business($config);
        echo '<h2>CRM requirements</h2><p>Only three lists, four lifecycle roles and required contact fields are needed. Marketing templates, campaigns, sequences and the CRM builder are not prerequisites.</p>';
        try{$plan=SiteEngine_Ads_Bridge_Product_Setup::crm();$missing=count(array_filter($plan['terms'],static fn($x)=>!$x['id']))+count($plan['fields']['missing']);echo '<p>'.esc_html($missing?$missing.' required definitions are missing.':'Required CRM definitions exist.').'</p><ul>';foreach($plan['terms']as$t)if(!$t['id'])echo '<li>'.esc_html($t['title'].' — '.$t['kind']).'</li>';foreach($plan['fields']['missing']as$f)echo '<li>'.esc_html($f['label'].' — contact field').'</li>';echo '</ul>';}catch(Throwable $e){echo '<p>'.esc_html($e->getMessage()).'</p>';}
        echo '<form id="seab-crm-requirements" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="seab_crm_requirements">';wp_nonce_field('seab_crm_requirements');submit_button('Set up CRM requirements','secondary');echo '<p id="seab-crm-setup-status" role="status"></p></form>';
        if($config->external_override_active())echo '<p>An intentional external configuration controls these values. <a href="'.esc_url(admin_url('admin.php?page='.self::PAGE_SLUG.'&tab=settings&section=diagnostics')).'">Review settings source in Diagnostics</a>.</p>';
        self::form_open('product');self::$combined_form=true;
        self::render_setup($config);
        echo '<h2>Lifecycle handling</h2><table class="form-table"><tbody>';self::checkbox_row('Enable Ads lifecycle handling','lifecycle_automation_enabled',(bool)$config->get('fluentcrm.lifecycle_automation.enabled',false),'Records Ads inquiry events and lifecycle state. This does not authorize marketing email or enrollment.');echo '</tbody></table>';
        self::render_sheets($config);self::render_exports($config);
        echo '<table class="form-table"><tbody>';self::text_row('Google Ads account timezone','account_timezone',$config->get('google_sheets.account_timezone',''),'Use the IANA timezone shown in your Google Ads account. Separate from Base business timezone; Google Ads has not been queried.');echo '</tbody></table>';
        self::$combined_form=false;self::form_close();
        echo '<p id="seab-unsaved" role="status">Save settings retains all values on this page. Saving does not run exports.</p><h2>Remaining requirements</h2>';self::render_setup_issues($config);
        echo '<p>Disabled optional exports are intentional. Readiness is not permission to deliver. Detailed diagnostics are in Settings.</p><script src="'.esc_url(SEAB_PLUGIN_URL.'includes/product-setup.js?ver='.SEAB_VERSION).'"></script>';
    }
    private static function render_advanced($config,$section) {
        $items=['fields'=>'Advanced field overrides','diagnostics'=>'Detailed diagnostics','scheduler'=>'Scheduler','leads'=>'Lead compliance'];if($section==='compliance')$section='leads';if(!isset($items[$section]))$section='diagnostics';
        echo '<h2>Settings</h2><nav class="nav-tab-wrapper" aria-label="Settings sections">';foreach($items as$key=>$label)echo '<a class="nav-tab '.($key===$section?'nav-tab-active':'').'" href="'.esc_url(admin_url('admin.php?page='.self::PAGE_SLUG.'&tab=settings&section='.$key)).'">'.esc_html($label).'</a>';echo '</nav>';
        if($section==='fields'){echo '<p>Defaults are populated; existing custom overrides are retained. Not every field is required: requirements depend on the selected feature and actual form.</p>';self::render_fields($config);self::render_lifecycle($config);}
        elseif($section==='scheduler'){self::render_scheduler();$d=new SiteEngine_Ads_Bridge_Doctor($config);foreach($d->run()as$r)if(in_array($r['check']??'',['Scheduled export runtime','Scheduled export freshness','Cron PHP runtime'],true))echo '<p>'.esc_html(($r['status']??'').': '.($r['message']??'')).'</p>';}
        elseif($section==='leads')self::render_compliance($config);
        else{self::render_source_banner($config);self::render_diagnostics($config);self::render_storage();$job=\SiteEngine\AdsBridge\Import\Store::job();if($job&&in_array($job['status']??'',['running','uncertain'],true)){echo '<h3>Retained unfinished configuration operation</h3>';$recovery=\SiteEngine\AdsBridge\Import\Recovery::inspect();\SiteEngine\AdsBridge\Import\Recovery::render($recovery);if(!empty($recovery['resume']))echo '<button type="button" class="button" id="seab-resume-operation" data-nonce="'.esc_attr(wp_create_nonce('seab_crm_import')).'">Resume original saved operation</button><p id="seab-resume-result" role="status"></p><script src="'.esc_url(SEAB_PLUGIN_URL.'includes/saved-operation-recovery.js?ver='.SEAB_VERSION).'"></script>';echo '<p>The original operation and receipts are retained. Use supported exact-operation recovery; ordinary setup never resets it.</p>';}}
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
        if(in_array($source,['external','local_file'],true))self::render_authority($config);
	}

	/** @return void */
    private static function render_setup($config) {
        $id=(int)$config->get('fluent_forms.form_id',0);$view=SiteEngine_Ads_Bridge_Setup::form_connection($id,(array)$config->get('fluent_forms.fields',[]));
        self::form_open('setup');echo '<table class="form-table"><tbody>';

        echo '<tr><th><label for="seab-form-id">Lead form</label></th><td><select id="seab-form-id" name="form_id"><option value="0">Select a form</option>';foreach(self::get_fluent_forms()as$f)echo '<option value="'.esc_attr($f['id']).'" '.selected($id,$f['id'],false).'>'.esc_html($f['title']).'</option>';echo '</select><p>Save a new selection to inspect its fields.</p></td></tr>';
        echo '</tbody></table>';
        echo '<h3>Form connection</h3><p>'.esc_html($view['ready']?'Required advertising fields have unique selectors. Save to retain any newly detected mappings.':$view['error']).'</p>';
        foreach($view['needs']as$key=>$candidates){echo '<p><label for="seab-choice-'.esc_attr($key).'">'.esc_html(ucwords(str_replace('_',' ',$key))).'</label> <select id="seab-choice-'.esc_attr($key).'" name="form_field_'.esc_attr($key).'"><option value="">Choose field; leave incomplete</option>';foreach($view['fields']as$name=>$f)if($f['count']===1&&(!$candidates||in_array($name,$candidates,true)))echo '<option value="'.esc_attr($name).'">'.esc_html($f['label'].' ('.$name.')').'</option>';echo '</select></p>';}
        echo '<p>Standard unique mappings are used by default. Existing custom mappings are preserved. Full overrides are in Settings > Advanced field overrides.</p>';self::form_close();
        echo '<p><strong>CRM feed is separate.</strong> Field-name checks only verify Ads reading. They do not verify Fluent Forms → FluentCRM delivery or the submission ID feed.</p>';$feed=SiteEngine_Ads_Bridge_Product_Setup::feed((int)$config->get('fluent_forms.form_id',0));echo '<p>'.esc_html($feed['message']).' <a href="'.esc_url($feed['url']).'">Edit FluentCRM integration</a></p>';
    }
    private static function render_scheduler() {
		$cron = self::cron_guidance();
		echo '<hr><h2>Scheduled Export / Cron</h2>';
		echo '<p>When delivery is separately authorized and exports are enabled: <strong>every 10 minutes</strong>. Keep unscheduled while disabled. Use <code>export-enabled</code> so intake, lifecycle-ledger snapshots, Qualified Leads, and Converted Leads run from one scheduler.</p>';
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
		$ls_php='/usr/local/lsws/lsphp'.$version[0].$version[1].'/bin/php';
		$php = is_executable($ea_php)?$ea_php:(is_executable($ls_php)?$ls_php:(PHP_SAPI==='cli'?PHP_BINARY:''));
		$wp = is_executable('/usr/bin/wp')?'/usr/bin/wp':(is_executable('/usr/local/bin/wp')?'/usr/local/bin/wp':'');
		$path    = untrailingslashit( ABSPATH );
		$home    = dirname( $path );
		if ( preg_match( '#^(/home/[^/]+)(?:/|$)#', $path, $m ) ) {
			$home = $m[1];
		}
		$command = $php!==''&&$wp!=='' ? escapeshellarg($php).' -d memory_limit=512M '.escapeshellarg($wp).' --path='.escapeshellarg($path).' se-ads export-enabled --apply --limit=25 >> '.escapeshellarg($home.'/se-ads-export-enabled.log').' 2>&1' : 'CLI PHP / WP-CLI path unresolved; ask the site administrator. Do not schedule this placeholder.';
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
			$value = (string) $config->get( 'fluentcrm.tags.lifecycle.' . $role, '' );
			echo '<tr><th><label for="seab-tag-' . esc_attr( $role ) . '">' . esc_html( $label ) . ' lifecycle tag</label></th><td><select id="seab-tag-' . esc_attr( $role ) . '" name="tag_' . esc_attr( $role ) . '"><option value="">Select an existing tag</option>';
			foreach ( $tags as $title ) { echo '<option value="' . esc_attr( $title ) . '" ' . selected( $value, $title, false ) . '>' . esc_html( $title ) . '</option>'; }
			echo '</select><p class="description">Field: fluentcrm.tags.lifecycle.' . esc_html( $role ) . '. Missing tags: create them in FluentCRM &rarr; Contacts &rarr; Tags, then return here. Duplicate titles must be resolved there first.</p></td></tr>';
		}
        echo '</tbody></table><h2>Optional Source Tags</h2><p>Informational only. Select an existing title (for example Referral) or leave unused.</p><table class="form-table"><tbody>';
        foreach(['call'=>'Call','form'=>'Form','referral'=>'Referral','walk_in'=>'Walk in']as$role=>$label){
            echo '<tr><th><label for="seab-source-'.esc_attr($role).'">'.esc_html($label).'</label></th><td><select id="seab-source-'.esc_attr($role).'" name="source_'.esc_attr($role).'"><option value="">Unused</option>';
            $current=(string)$config->get('fluentcrm.tags.sources.'.$role,'');if($current!==''&&!in_array($current,$tags,true))echo '<option selected value="'.esc_attr($current).'">'.esc_html($current.' (existing mapping; review missing tag)').'</option>';
            foreach($tags as$title){echo '<option value="'.esc_attr($title).'" '.selected((string)$config->get('fluentcrm.tags.sources.'.$role,''),$title,false).'>'.esc_html($title).'</option>';}
            echo '</select></td></tr>';
        }
		echo '</tbody></table><h2>Lifecycle Compliance Automation</h2><table class="form-table"><tbody>';
		self::checkbox_row( 'Enable Lifecycle Compliance Automation', 'lifecycle_automation_enabled', (bool) $config->get( 'fluentcrm.lifecycle_automation.enabled', false ), 'When enabled: new master-form contacts receive New; lifecycle tags are kept mutually exclusive; Qualified stamps qualified_at; Unqualified records its event in Ads inquiry storage; Won stamps qualified_at and closed_date when blank. Invoice amount is never fabricated.' );
		self::text_row( 'Unqualified Reason Field', 'unqualified_reason_field', $config->get( 'fluentcrm.lifecycle_automation.unqualified_reason_field', 'customer_rating' ), 'Existing FluentCRM custom-field slug. Canonical choices: Wrong Area, Wrong Service, Wrong Product, Wrong Price, Spam / Junk, Other.' );
		echo '<p>Unqualified event time is owned by the active Ads inquiry, not a required CRM field. Existing historical CRM values are preserved.</p>';
		self::text_row( 'Stale New Warning (days)', 'stale_new_days', $config->get( 'fluentcrm.lifecycle_automation.stale_new_days', 3 ), 'Lead Compliance flags New contacts older than this threshold.' );
		echo '</tbody></table>';
		self::form_close();
	}

	/** @return void */
	private static function render_fields( $config ) {
        echo '<p>Use Setup > Set up CRM requirements for missing definitions. This screen preserves custom overrides; blank optional sources remain unused.</p>';
        $feed=SiteEngine_Ads_Bridge_Product_Setup::feed((int)$config->get('fluent_forms.form_id',0));echo '<p>'.esc_html($feed['message']).' <a href="'.esc_url($feed['url']).'">Edit FluentCRM integration</a></p>';
		self::form_open( 'fields' );
		echo '<h2>Fluent Form Field Mapping</h2><p>Map Ads Bridge canonical keys to the field names used by the configured master Fluent Form. Defaults match the standard SiteEngine lead form.</p>';
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Canonical Key</th><th>Fluent Forms Field Name</th></tr></thead><tbody>';
		foreach ( SiteEngine_Ads_Bridge_Settings::default_field_map() as $key => $default ) {
			$value = $config->get( 'fluent_forms.fields.' . $key, $default );
			echo '<tr><td><code>' . esc_html( $key ) . '</code></td><td><input class="regular-text" aria-label="Form field: ' . esc_attr( ucwords(str_replace('_',' ',$key)) ) . '" name="field_' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '"></td></tr>';
		}
		echo '</tbody></table><h3>CRM event field overrides</h3><table class="form-table"><tbody>';
        self::text_row('Qualification timestamp field','qualified_date_field',$config->get('exports.qualified_leads.date_field','qualified_at'),'Required when Qualified export is enabled.');
        self::text_row('Converted revenue field','won_value_field',$config->get('exports.won_jobs.value_field','invoiced_amount'),'Actual positive revenue is required for Converted export.');
        self::text_row('Converted outcome date field','won_date_field',$config->get('exports.won_jobs.date_field','closed_date'),'Required when Converted export is enabled.');
        echo '</tbody></table>';
		self::form_close();
	}

    private static function render_field_provisioning() {
        echo '<h2>Required CRM Definitions</h2><p>Preview and deliberately create missing definitions only. Existing fields, types and contact values are never replaced. Conflicting definitions require CRM-owner review.</p>';
        try{$plan=SiteEngine_Ads_Bridge_Fields::provision(false);echo '<ul>';foreach($plan['missing']as$f)echo '<li>'.esc_html($f['slug'].' — '.$f['type']).'</li>';foreach($plan['conflicts']as$f)echo '<li>'.esc_html($f.' — existing type conflict; unchanged').'</li>';echo '</ul>';}catch(Throwable $e){echo '<p>'.esc_html($e->getMessage()).'</p>';}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="seab_provision_fields">';wp_nonce_field('seab_provision_fields');
        echo '<label><input type="checkbox" name="confirm" value="1" required> Create only missing standard Ads fields. Unqualified timing is internal; no unqualified_at CRM field is created.</label>';submit_button('Create Missing CRM Fields');echo '</form>';

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
        if(!self::$combined_form){self::text_row( 'Qualification Timestamp Field', 'qualified_date_field', $config->get( 'exports.qualified_leads.date_field', 'qualified_at' ), 'Recommended: qualified_at (date_time), populated by the CRM automation at the moment the lead becomes qualified.' );}
		self::text_row( 'Qualified Lead Proxy Value', 'qualified_value', $config->get( 'exports.qualified_leads.conversion_value', '' ), 'Optional. Leave blank for no proxy value; if used, enter a non-negative numeric value.' );

		echo '</tbody></table><h3>SE | Converted Lead</h3><table class="form-table"><tbody>';
		self::checkbox_row( 'Enable Converted Lead Export', 'won_enabled', (bool) $config->get( 'exports.won_jobs.enabled', false ), 'Won contacts only; actual positive revenue is mandatory.' );
		self::text_row( 'Converted Conversion Action', 'won_action', $config->get( 'exports.won_jobs.conversion_action', 'SE | Converted Lead' ) );
        if(!self::$combined_form){self::text_row( 'Converted Value Field', 'won_value_field', $config->get( 'exports.won_jobs.value_field', 'invoiced_amount' ), 'Must contain actual positive revenue. Ads Bridge never fabricates fallback revenue.' );}
        if(!self::$combined_form){self::text_row( 'Outcome Date Field', 'won_date_field', $config->get( 'exports.won_jobs.date_field', 'closed_date' ), 'Recommended canonical field: closed_date (label it Outcome Date in CRM).' );}

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
        echo '<tr><td colspan="2"><button type="button" class="button" id="seab-test-sheets" data-nonce="'.esc_attr(wp_create_nonce('seab_test_sheets')).'">Test connection</button><p id="seab-sheets-result" role="status"></p></td></tr>';
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
    private static function render_migration($config) { self::render_authority($config); }

    /** Always reachable from normal Setup, even with missing/unreadable legacy files. */
    private static function render_authority($config) {
        $source=$config->configuration_source();$file=SiteEngine_Ads_Bridge_Setup::config_file_status();
        echo '<section id="seab-configuration-authority"><h3>Configuration authority</h3>';
        if(SiteEngine_Ads_Bridge_Setup::settings_authority()){
            echo '<p><strong>WordPress settings explicitly selected.</strong> The preserved legacy file is inactive. Changing or removing it will not reverse this choice. Shared facts come from Business Data.</p>';
        }elseif(!in_array($source,['external','local_file'],true)){
            echo '<p>WordPress settings are available. Save incomplete setup now; CRM import, credentials access and export readiness are not required to save.</p>';
        }else{
            echo '<p><strong>Legacy file authority is selected.</strong> The external flag alone does not establish that a valid configuration was loaded. Keep intentional file authority by leaving it selected, or explicitly choose WordPress settings below.</p>';
            echo '<p>File status: <strong>'.esc_html($file['state']).'</strong>. Path: <code>'.esc_html($file['path']).'</code>.</p>';
            foreach($config->get_load_errors()as$error)echo '<p>'.esc_html($error).'</p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="seab_use_settings">';
            wp_nonce_field('seab_use_settings');
            echo '<p><label><input required type="checkbox" name="confirm_settings_authority" value="1"> Use WordPress settings. Keep existing saved Ads values, or start with disabled setup defaults if none are saved. Do not import the legacy file.</label></p>';
            submit_button('Use WordPress settings','primary','submit',false);echo '</form><p>The original file and wp-config.php are preserved. This choice remains effective after later file changes or removal. Saving incomplete settings is allowed.</p>';
            if($file['readable']&&!$config->get_load_errors()){
                echo '<details><summary>Import legacy settings instead</summary><p>Optional: copy the currently loaded values after reviewing this exact file. Shared facts then come from Base. Existing WordPress values are replaced only by this explicit import; review delivery flags before confirming.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="seab_import_config">';
                wp_nonce_field('seab_import_config');echo '<input type="hidden" name="external_sha256" value="'.esc_attr(hash_file('sha256',SEAB_CONFIG_FILE)).'"><p><label><input required type="checkbox" name="switch_authority" value="1"> Import these reviewed values and durably use WordPress settings.</label></p>';
                submit_button('Import legacy settings','secondary','submit',false);echo '</form></details>';
            }else echo '<p>No legacy content can be imported from this state. Choosing WordPress settings does not read or import that content.</p>';
        }
        echo '</section>';
    }

    public static function handle_crm_requirements() {
        if(!current_user_can('manage_options'))wp_die('Permission denied.');
        check_admin_referer('seab_crm_requirements');
        try{
            if(SiteEngine_Ads_Bridge_Config::instance()->external_override_active())throw new RuntimeException('External settings are authoritative. Review Settings > Detailed diagnostics before provisioning mapped requirements.');
            SiteEngine_Ads_Bridge_Setup::with_configuration(static function(){
                $before=SiteEngine_Ads_Bridge_Settings::get();
                $plan=SiteEngine_Ads_Bridge_Product_Setup::crm(true);
                $data=SiteEngine_Ads_Bridge_Settings::merge(SiteEngine_Ads_Bridge_Settings::defaults(),$before?:SiteEngine_Ads_Bridge_Config::instance()->all());
                foreach($plan['terms']as$t){
                    if($t['kind']==='list')$data['fluentcrm']['lists'][$t['role']]=$t['title'];
                    else $data['fluentcrm']['tags']['lifecycle'][$t['role']]=$t['title'];
                }
                if(SiteEngine_Ads_Bridge_Settings::get()!==$before)throw new RuntimeException('Settings changed during provisioning. Definitions are retained; reload before mapping them.');
                SiteEngine_Ads_Bridge_Settings::save($data);
                if(SiteEngine_Ads_Bridge_Settings::get()!==SiteEngine_Ads_Bridge_Settings::sanitize_config($data))throw new RuntimeException('CRM mapping readback failed; inspect saved configuration before retrying.');
            });
        }catch(Throwable $e){wp_die(esc_html($e->getMessage()));}
        wp_safe_redirect(admin_url('admin.php?page='.self::PAGE_SLUG.'&tab=setup&seab_crm_ready=1'));exit;
    }
    public static function test_sheets() {
        if(!current_user_can('manage_options'))wp_send_json_error(['message'=>'Administrator permission required.'],403);
        check_ajax_referer('seab_test_sheets');
        try{wp_send_json_success(SiteEngine_Ads_Bridge_Product_Setup::test_connection(self::post_text('credentials_path'),self::post_text('spreadsheet_id')));}
        catch(Throwable $e){wp_send_json_error(['message'=>$e->getMessage()],400);}
    }
    public static function handle_authority() {
        if(!current_user_can('manage_options'))wp_die('Permission denied.');
        check_admin_referer('seab_use_settings');
        if(self::post_text('confirm_settings_authority')!=='1')wp_die('Confirm Use WordPress settings in Ads Bridge > Setup > Configuration authority.');
        $before=SiteEngine_Ads_Bridge_Settings::get();$choice=get_option(SiteEngine_Ads_Bridge_Setup::AUTHORITY_OPTION,null);
        try{SiteEngine_Ads_Bridge_Setup::with_configuration(static function()use($before,$choice){
            if(SiteEngine_Ads_Bridge_Settings::get()!==$before||get_option(SiteEngine_Ads_Bridge_Setup::AUTHORITY_OPTION,null)!==$choice)throw new RuntimeException('Configuration changed during this request;reload before choosing authority.');
            SiteEngine_Ads_Bridge_Setup::choose_wordpress_settings();
        });}catch(Throwable $e){wp_die(esc_html($e->getMessage()));}
        wp_safe_redirect(admin_url('admin.php?page='.self::PAGE_SLUG.'&tab=setup&section=authority&seab_authority_saved=1'));exit;
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
		if ( SiteEngine_Ads_Bridge_Config::instance()->external_override_active() ) { wp_die( 'External SEAB_CONFIG_FILE controls these values. Open Ads Bridge > Settings > Detailed diagnostics > Configuration authority to keep intentional file authority or choose WordPress settings without importing the file. File status: ' . SiteEngine_Ads_Bridge_Setup::config_file_status()['state'] ); }
		$tab = isset( $_POST['seab_tab'] ) ? sanitize_key( wp_unslash( $_POST['seab_tab'] ) ) : 'setup';
		$saved_before = SiteEngine_Ads_Bridge_Settings::get();

		if ( SiteEngine_Ads_Bridge_Settings::exists() ) {
			$data = SiteEngine_Ads_Bridge_Settings::get();
		} else {
			$data = SiteEngine_Ads_Bridge_Config::instance()->all();
			if ( empty( $data ) ) {
				$data = SiteEngine_Ads_Bridge_Settings::defaults();
			}
		}
		$data = SiteEngine_Ads_Bridge_Settings::merge( SiteEngine_Ads_Bridge_Settings::defaults(), $data );

		if ('product' === $tab) {
            try{$data=SiteEngine_Ads_Bridge_Product_Setup::values($data,$_POST);}catch(Throwable $e){wp_die(esc_html($e->getMessage()));}
        } elseif ( 'setup' === $tab ) {
			$data['client']['slug'] = self::post_text( 'client_slug' );
			$data['client']['short_name'] = self::post_text( 'client_short_name' );
			$data['fluent_forms']['form_id'] = self::post_int( 'form_id' );
			$data['consent']['required_policy_version'] = self::post_text( 'consent_policy' );
            $choices=[];foreach(SiteEngine_Ads_Bridge_Setup::required_form_keys()as$key)if(isset($_POST['form_field_'.$key]))$choices[$key]=self::post_text('form_field_'.$key);
            try{$data['fluent_forms']['fields']=SiteEngine_Ads_Bridge_Setup::resolve_form_mapping($data['fluent_forms']['form_id'],$data['fluent_forms']['fields'],$choices);}catch(Throwable $e){wp_die(esc_html($e->getMessage()));}
		} elseif ( 'lifecycle' === $tab ) {
			$data['fluentcrm']['lists']['customers'] = self::post_text( 'list_customers' );
			$data['fluentcrm']['lists']['future_customers'] = self::post_text( 'list_future_customers' );
			$data['fluentcrm']['lists']['do_not_contact'] = self::post_text( 'list_dnc' );
			foreach ( SiteEngine_Ads_Bridge_Config::lifecycle_roles() as $role ) {
				$data['fluentcrm']['tags']['lifecycle'][ $role ] = self::post_text( 'tag_' . $role );
			}
			if ( self::post_bool( 'lifecycle_automation_enabled' ) ) {
				try { SiteEngine_Ads_Bridge_Setup::validate_lifecycle( $data['fluentcrm']['tags']['lifecycle'] ); }
				catch ( Throwable $e ) { wp_die( esc_html( $e->getMessage() ) ); }
			}
            foreach(['call','form','referral','walk_in']as$role){
                $key='source_'.$role;if(!array_key_exists($key,$_POST))continue;$value=self::post_text($key);
                if($value!==''&&$value!==($saved_before['fluentcrm']['tags']['sources'][$role]??null)){global $wpdb;$ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM `{$wpdb->prefix}fc_tags` WHERE BINARY title=%s",$value));if($wpdb->last_error||!is_array($ids)||count($ids)!==1)wp_die('Optional source mapping must identify exactly one existing tag.');}
                $data['fluentcrm']['tags']['sources'][$role]=$value;
            }
			$data['fluentcrm']['lifecycle_automation']['enabled'] = self::post_bool( 'lifecycle_automation_enabled' );
			$data['fluentcrm']['lifecycle_automation']['unqualified_reason_field'] = self::post_text( 'unqualified_reason_field' );
			$data['fluentcrm']['lifecycle_automation']['stale_new_days'] = max( 1, self::post_int( 'stale_new_days' ) );
		} elseif ( 'fields' === $tab ) {
			foreach ( SiteEngine_Ads_Bridge_Settings::default_field_map() as $key => $default ) {
				$value = self::post_text( 'field_' . $key );
				$data['fluent_forms']['fields'][ $key ] = '' !== $value ? $value : $default;
			}
            foreach(['qualified_date_field'=>['qualified_leads','date_field'],'won_value_field'=>['won_jobs','value_field'],'won_date_field'=>['won_jobs','date_field']]as$key=>$path)if(array_key_exists($key,$_POST))$data['exports'][$path[0]][$path[1]]=self::post_text($key);
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

		try { SiteEngine_Ads_Bridge_Setup::with_configuration( static function() use ( $data, $saved_before ) {
			if ( SiteEngine_Ads_Bridge_Settings::get() !== $saved_before ) { throw new RuntimeException( 'Ads settings changed during this request. Reload the tab before saving.' ); }
			SiteEngine_Ads_Bridge_Settings::save( $data );
			if ( SiteEngine_Ads_Bridge_Settings::get() !== SiteEngine_Ads_Bridge_Settings::sanitize_config( $data ) ) { throw new RuntimeException( 'Settings readback failed. Inspect current values before retrying.' ); }
		} ); } catch ( Throwable $e ) { wp_die( esc_html( $e->getMessage() ) ); }
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . ($tab==='product'?'setup':$tab) . '&seab_saved=1' );
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
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=setup&section=authority' ) );
			exit;
		}
		try { SiteEngine_Ads_Bridge_Setup::with_configuration( static function() use ( $config ) {
			$choice = true;
			// Both local and external imports require the exact readable review.
			{
				$hash = self::post_text( 'external_sha256' );
				if ( self::post_text( 'switch_authority' ) !== '1' || !SiteEngine_Ads_Bridge_Setup::config_file_status()['readable'] || $config->get_load_errors() || ! hash_equals( hash_file( 'sha256', SEAB_CONFIG_FILE ), $hash ) ) { throw new RuntimeException( 'Confirm the reviewed import in Ads Bridge > Setup > Configuration authority. The readable file must still match; no missing, unreadable or invalid content is imported.' ); }
				$choice = true;
			}
			$data = $config->all();
			foreach ( SiteEngine_Ads_Bridge_Config::lifecycle_roles() as $role ) { $data['fluentcrm']['tags']['lifecycle'][ $role ] = $config->get( 'fluentcrm.tags.lifecycle.' . $role, '' ); }
			$data = SiteEngine_Ads_Bridge_Settings::sanitize_config( $data );
			SiteEngine_Ads_Bridge_Settings::save( $data );
			if ( SiteEngine_Ads_Bridge_Settings::get() !== $data ) { throw new RuntimeException( 'Imported settings readback failed; external authority has not been changed.' ); }
			if ( null !== $choice ) {
				SiteEngine_Ads_Bridge_Setup::choose_wordpress_settings();
			}
		} ); } catch ( Throwable $e ) { wp_die( esc_html( $e->getMessage() ) ); }
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=setup&section=authority&seab_imported=1' ) );
		exit;
	}

	public static function render_storage() {
		echo '<h2>Required Ads Storage</h2><p>Inquiry history (schema 2) and the configuration guard (schema 1) are required. Initialization reuses the explicit plugin migrations. It creates absent storage, preserves existing records and journals, and never adopts inquiries, enables delivery or exports, or changes Consent.</p>';
		$storage = \SiteEngine\AdsBridge\Import\Recovery::inspect();
        $ledger=SiteEngine_Ads_Bridge_Storage::inspect_ledger();
        if($storage['kind']==='ready'&&in_array($storage['shared']['kind'],['none','idle'],true)&&$ledger['ready']&&!SiteEngine_Ads_Bridge_Setup::$storage_error){echo '<p><strong>Storage ready. No action required.</strong></p>';return;}
        if(SiteEngine_Ads_Bridge_Setup::$storage_error)echo '<p>'.esc_html(SiteEngine_Ads_Bridge_Setup::$storage_error).'</p>';
        if(!$ledger['ready'])echo '<p>'.esc_html($ledger['reason']).'</p>';
		\SiteEngine\AdsBridge\Import\Recovery::render($storage);
		if (!$storage['can_initialize']) { echo '<p>Initialization is unavailable for this state. Use the reported owner/recovery workflow; it never clears pending operations.</p>'; return; }
        echo '<p>Normal activation and supported additive upgrades prepare absent plugin storage. Existing incompatible schemas or retained unfinished operations require the exact recovery described above; no rows or journals are reset.</p>';
	}

    public static function handle_fields() {
        if(!current_user_can('manage_options'))wp_die('Permission denied.');check_admin_referer('seab_provision_fields');if(self::post_text('confirm')!=='1')wp_die('Explicit provisioning confirmation required.');
        try{SiteEngine_Ads_Bridge_Setup::with_configuration(static function(){return SiteEngine_Ads_Bridge_Fields::provision(true);});}catch(Throwable $e){wp_die(esc_html($e->getMessage()));}
        wp_safe_redirect(admin_url('admin.php?page='.self::PAGE_SLUG.'&tab=fields'));exit;
    }

	public static function handle_storage() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Permission denied.' ); }
		check_admin_referer( 'seab_initialize_storage' );
		if ( self::post_text( 'confirm_storage' ) !== '1' ) { wp_die( 'Confirm storage initialization in Ads Bridge > Storage.' ); }
		try { SiteEngine_Ads_Bridge_Setup::initialize_storage(); }
		catch ( Throwable $e ) { wp_die( esc_html( $e->getMessage() ) ); }
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=storage' ) );
		exit;
	}

	/** @return void */
	private static function form_open( $tab ) {
		if(self::$combined_form)return;
		echo '<form '.($tab==='product'?'id="seab-ordinary-settings" ':'').'method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="seab_save_settings"><input type="hidden" name="seab_tab" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'seab_save_settings' );
	}

	/** @return void */
	private static function form_close() {
		if(self::$combined_form)return;
		submit_button( 'Save settings' );
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
