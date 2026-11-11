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
		add_action('admin_init',static function(){if(($_GET['page']??'')===self::PAGE_SLUG)SiteEngine_Ads_Bridge_Setup::prepare_storage_for_admin();});
		add_action( 'admin_post_seab_save_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_seab_import_config', array( __CLASS__, 'handle_import' ) );
		add_action('admin_post_seab_use_settings',array(__CLASS__,'handle_authority'));
		add_action( 'admin_post_seab_initialize_storage', array( __CLASS__, 'handle_storage' ) );
		add_action('admin_post_seab_verify_setup',array(__CLASS__,'handle_verification'));
		add_action('admin_post_seab_provision_fields',array(__CLASS__,'handle_fields'));
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

		$ads_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=setup' );
		$advanced_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=advanced' );
		echo '<h2>SiteEngine Ads Bridge Setup</h2><p>Use Overview, Setup, Leads and Advanced. Incomplete setup is saveable; CRM import, credentials access and export readiness are not prerequisites to saving.</p>';
		echo '<h3>Ordered setup</h3><ol>';
		echo '<li><strong>Shared Business Data:</strong> Open <a href="' . esc_url( admin_url( 'admin.php?page=business-data' ) ) . '">Business Data</a> for name, timezone, currency and Google Ads account facts. A different WordPress timezone is not itself an error. Record the manual account/conversion-timezone confirmation in Setup.</li>';
		echo '<li><strong>CRM configuration:</strong> Import a reviewed configuration when needed. Resume saved import for unfinished work using its original identity; do not start another import or initialize storage as recovery. The separate CRM Configuration plugin is not required on destination sites. Import does not authorize enrollment or email.</li>';
		echo '<li><strong>Lead form:</strong> Select and verify the master form in <a href="' . esc_url( $ads_url ) . '">Setup</a>. Review detected fields; choose missing or ambiguous fields explicitly. Independently verify the Fluent Forms-to-CRM feed, including <code>{submission.id}</code> to <code>submission_id</code>. Field matching does not verify that feed. Record operator verification with its stated limits.</li>';
		echo '<li><strong>Lifecycle:</strong> Review Customers, Future Customers, DNC and New/Qualified/Unqualified/Won. Use administrator setup to provision genuinely missing required CRM fields. Optional sources may remain Unused. Unqualified timing is recorded in Ads inquiries; no new <code>unqualified_at</code> CRM field is required and Unqualified produces no Google conversion.</li>';
		echo '<li><strong>Connections:</strong> Configure the spreadsheet, conversion actions/value/date fields and external service-account JSON path. Saving a path does not prove file access under web or CLI identities. Google_Ads_Import has 21 columns and must be the first worksheet; All_Leads has 94 columns. Keep credentials outside the web root.</li>';
		echo '<li><strong>Readiness:</strong> Overview shows the next action. <a href="' . esc_url( $advanced_url ) . '">Advanced</a> contains detailed diagnostics, full overrides, scheduler and storage/recovery tools. Healthy core storage needs no action; normal activation/administrator setup prepares absent core tables through protected additive routines. Never clear history to resolve incompatible schema or unfinished ownership.</li>';
		echo '<li><strong>Explicit enablement:</strong> Review each feature before enabling it. All four export families remain disabled on the development installation; a configured Sheets connection does not enable a family. Advanced provides the site-specific scheduler command; this plugin does not install an operating-system cron job. Do not run live exports or Sheet provisioning merely to clear a warning.</li></ol>';
		echo '<h3>Configuration authority</h3><p>Normal installations use WordPress settings plus Base shared facts. Intentional external <code>SEAB_CONFIG_FILE</code> remains authoritative until an administrator chooses Setup &gt; Configuration authority &gt; Use WordPress settings. This preserves saved values and original files, remains durable after file changes/removal, and does not import unavailable content. Import legacy settings is a separate optional action.</p>';
		echo '<h3>Consent and delivery</h3><p>Form inquiries retain immutable <code>ff:{form_id}:{entry_id}</code> attribution. Signed original evidence, negative-choice veto, policy, fresh current eligibility and current DNC gate advertising browser payloads and every advertising dispatch/retry. Personalization is independent. Internal All_Leads reporting does not grant advertising permission. Google Ads API upload remains disabled; append-only Sheets is the supported sink.</p>';
		echo '<h3>Development integration limits</h3><p>Production FieldEdge creation and lead-to-job continuity are not enabled. Trusted outcome/prospective interfaces retain synthetic-only preparation/replay controls; their optional development storage is not a normal setup requirement. Call advertising remains held for authoritative original-call Consent and provider identity/start evidence. Duration is not business qualification. No call may be represented as a fabricated form submission or grant.</p>';
		echo '<h3>Safe inspection</h3><p><code>wp se-ads doctor</code> reports actual failures and warnings. Disabled, never-run, stale and failed exports are different states. Use read-only inspection before any explicitly authorized delivery; never reset journals, withdrawal barriers or uncertain append evidence.</p>';

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
				$messages[] = 'External configuration is active; UI saves do not replace SEAB_CONFIG_FILE. Open Ads Bridge > Setup > Configuration authority.';
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
		$add = static function( $key, $message ) use ( &$issues, $shared, $labels ) {
			$base = isset( $shared[ $key ] );
			$tab = 'setup';
			if ( strpos( $key, 'fluentcrm.' ) === 0 ) { $tab = 'lifecycle'; }
			if ( strpos( $key, 'fluent_forms.fields.' ) === 0 ) { $tab = 'fields'; }
			if ( strpos( $key, 'exports.' ) === 0 ) { $tab = 'exports'; }
			if ( strpos( $key, 'google_sheets.' ) === 0 ) { $tab = 'sheets'; }
			if ( strpos( $key, 'storage.' ) === 0 ) { $tab = 'storage'; }
			$names = array( 'setup' => 'Setup', 'lifecycle' => 'CRM & Lifecycle', 'fields' => 'Field Mapping', 'exports' => 'Exports', 'sheets' => 'Google Sheets', 'storage' => 'Storage' );
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
					if ( $wpdb->last_error || ! is_array( $ids ) || count( $ids ) !== 1 ) { $add( $key, 'The selected title must match exactly one existing FluentCRM tag. Create missing tags or resolve duplicate titles in FluentCRM > Contacts > Tags, then select the tag in CRM & Lifecycle.' ); }
				}
				if ( (int) $config->get( 'fluent_forms.form_id', 0 ) <= 0 ) { $add( 'fluent_forms.form_id', 'Select the existing master form in Ads Bridge > Setup.' ); }
			}
			if ( ! SiteEngine_Ads_Bridge_Inquiries::table_exists() || ! SiteEngine_Ads_Bridge_Inquiries::schema_ready() ) {
				$add( 'storage.inquiries', 'Required inquiry storage is not initialized at schema 2. Normal setup prepares absent storage automatically. Open Advanced > Storage for the exact blocker; existing incompatible schemas are preserved.' );
			} else {
				$guard = SiteEngine_Ads_Bridge_Coordination::migrate( false );
				if ( $guard['status'] !== 'preview' || empty( $guard['exists_before'] ) ) { $add( 'storage.configuration_guard', 'Required configuration guard storage is missing, incomplete or blocked: ' . $guard['reason_code'] . '. Open Storage for initialization or pending-work diagnosis.' ); }
			}
		}
		return $issues;
	}

	/** Escaped, actionable list for the Ads screen; module messages stay plain text. */
	public static function render_setup_issues( $config ) {
		$issues = self::setup_issues( $config );
		if ( ! $issues ) { return; }
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
        $config=SiteEngine_Ads_Bridge_Config::instance();$requested=sanitize_key(wp_unslash($_GET['tab']??'overview'));
        $legacy=['lifecycle'=>['setup','crm'],'crm-import'=>['setup','crm'],'fields'=>['advanced','fields'],'exports'=>['setup','review'],'sheets'=>['setup','connections'],'compliance'=>['leads',''],'diagnostics'=>['advanced','diagnostics'],'migration'=>['advanced','legacy'],'storage'=>['advanced','storage']];
        [$tab,$section]=$legacy[$requested]??[$requested,sanitize_key(wp_unslash($_GET['section']??''))];
        $tabs=['overview'=>'Overview','setup'=>'Setup','leads'=>'Leads','advanced'=>'Advanced'];if(!isset($tabs[$tab]))$tab='overview';
        echo '<div class="wrap seab-admin"><h1>SiteEngine Ads Bridge</h1>';
        echo '<style>.seab-primary{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0}.seab-primary a{padding:10px 18px;border:1px solid #8c8f94;border-radius:4px;text-decoration:none;background:#fff}.seab-primary a[aria-current]{background:#135e96;color:white}.seab-admin details{padding:14px 18px;border:1px solid #c3c4c7;background:white;margin:14px 0;max-width:1100px}.seab-admin summary{cursor:pointer;font-size:16px;font-weight:600}.seab-admin summary:focus-visible,.seab-admin a:focus-visible{outline:3px solid #2271b1;outline-offset:3px}.seab-admin .form-table input.regular-text,.seab-admin select{max-width:100%}.seab-admin pre{white-space:pre-wrap;overflow-wrap:anywhere}.seab-scroll{overflow-x:auto}.seab-admin .notice{margin:12px 0}@media(max-width:600px){.seab-admin details{padding:12px}.seab-admin .form-table th,.seab-admin .form-table td{display:block;width:auto;padding:8px 0}.seab-admin .widefat{font-size:12px}.seab-primary a{flex:1;text-align:center}.seab-admin input.regular-text{width:100%}.seab-admin code{overflow-wrap:anywhere}}</style>';
        echo '<nav class="seab-primary" aria-label="Ads Bridge">';foreach($tabs as$key=>$label)echo '<a '.($key===$tab?'aria-current="page" ':'').'href="'.esc_url(admin_url('admin.php?page='.self::PAGE_SLUG.'&tab='.$key)).'">'.esc_html($label).'</a>';echo '</nav>';
        if(isset($_GET['seab_saved']))echo '<div class="notice notice-success" role="status"><p>Ads Bridge settings saved. Incomplete setup remains editable.</p></div>';
        if(isset($_GET['seab_imported']))echo '<div class="notice notice-success" role="status"><p>Legacy settings imported. Original configuration preserved.</p></div>';
        if($config->external_override_active()&&$tab!=='advanced')echo '<div class="notice notice-warning"><p>An external configuration controls these values. <a href="'.esc_url(admin_url('admin.php?page='.self::PAGE_SLUG.'&tab=setup&section=authority')).'">Choose configuration authority</a> before changing settings.</p></div>';
        echo '<div class="seab-scroll">';
        if($tab==='overview')self::render_overview($config);
        elseif($tab==='setup')self::render_setup_workflow($config,$section);
        elseif($tab==='leads')self::render_compliance($config);
        else self::render_advanced($config,$section);
        echo '</div></div>';
    }
    private static function step($id,$title,$done,$section,$callback) {
        echo '<details id="seab-step-'.esc_attr($id).'" '.(($section===$id||(!$done&&$section===''))?'open':'').'><summary>'.esc_html($title.' — '.($done?'Complete; edit':'Needs review')).'</summary>'; $callback();echo '</details>';
    }
    private static function render_overview($config) {
        echo '<h2>Setup progress</h2>';$issues=self::setup_issues($config);
        if($issues){$next=$issues[0];echo '<p><strong>Next required action: '.esc_html($next['label']).'</strong> — '.esc_html($next['message']).' <a href="'.esc_url($next['url']).'">'.esc_html($next['destination']).'</a></p>';}
        else echo '<p>Required setup checks are complete. Review readiness before explicitly enabling any features.</p>';
        echo '<p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page='.self::PAGE_SLUG.'&tab=setup')).'">Continue setup</a> <a href="'.esc_url(admin_url('admin.php?page='.self::PAGE_SLUG.'&tab=advanced&section=diagnostics')).'">Detailed diagnostics</a></p>';
        $doctor=new SiteEngine_Ads_Bridge_Doctor($config);$checks=$doctor->run();$fail=self::doctor_failures($checks);
        if($fail){echo '<h3>Checks needing attention ('.count($fail).')</h3><ul>';foreach(array_slice($fail,0,4)as$f)echo '<li><strong>'.esc_html($f['check']).':</strong> '.esc_html($f['message']).($f['corrective_url']?' <a href="'.esc_url($f['corrective_url']).'">'.esc_html($f['corrective_label']).'</a>':'').'</li>';echo '</ul>';}
        echo '<h2>Export status</h2><ul>';foreach(['all_leads'=>'All Leads','qualified_leads'=>'Qualified Lead','won_jobs'=>'Converted Lead']as$key=>$label)echo '<li>'.esc_html($label).': <strong>'.($config->get('exports.'.$key.'.enabled',false)?'Enabled':'Disabled').'</strong></li>';echo '</ul>';
        $last=get_option('seab_last_export_runtime',[]);echo '<p>Last successful aggregate run: '.esc_html(isset($last['timestamp'])?gmdate('Y-m-d H:i:s',(int)$last['timestamp']).' UTC — recorded successful runtime':'Never recorded').'.</p>';
        $attempt=get_option('seab_last_export_attempt',[]);if(is_array($attempt)&&!empty($attempt['status']))echo '<p>Latest recorded operation: '.esc_html($attempt['status']).'. See Advanced for scheduler and failure evidence.</p>';
        echo '<p>No export is run from this page. Google Ads API delivery remains disabled.</p>';
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
        $base=true;foreach(['business_name','timezone','currency','google_ads_customer_id']as$k)if(!function_exists('siteengine_has')||!siteengine_has($k))$base=false;
        $job=\SiteEngine\AdsBridge\Import\Store::job();$imported=$job&&($job['status']??'')==='complete';
        $form=SiteEngine_Ads_Bridge_Setup::form_connection((int)$config->get('fluent_forms.form_id',0),(array)$config->get('fluent_forms.fields',[]));
        if($section==='')$section=!$base?'business':(!$imported?'crm':(!$form['ready']?'form':(($config->get('google_sheets.spreadsheet_id','')===''||$config->get('google_sheets.credentials_path','')==='')?'connections':'review')));
        self::render_authority($config);
        echo '<h2>Complete setup in order</h2><p>Save each step as you go. Completed steps stay collapsed and editable. Saving incomplete setup does not enable delivery.</p>';
        self::step('business','1. Read shared Business Data',$base,$section,static fn()=>self::render_shared_business($config));
        self::step('crm','2. Import CRM configuration',$imported,$section,static function()use($config,$imported){if($imported)echo '<p>Imported configuration and CRM mappings are retained; no need to enter them again.</p>';else echo '<p>CRM configuration has not been imported. This does not block saving Ads settings and does not mean FluentCRM is missing. Existing mappings may be configured below; missing dependencies and invalid mappings are reported separately.</p>';\SiteEngine\AdsBridge\Import\Admin::render();echo '<details><summary>Edit CRM mappings and optional sources</summary>';self::render_lifecycle($config);echo '</details><details><summary>Standard CRM field definitions</summary>';self::render_field_provisioning();echo '</details>';});
        self::step('form','3. Select and verify the lead form',$form['ready']&&$form['mapping']===(array)$config->get('fluent_forms.fields',[])&&SiteEngine_Ads_Bridge_Setup::verification('submission',$config)['valid'],$section,static fn()=>self::render_setup($config));
        $connection=$config->get('google_sheets.spreadsheet_id','')!==''&&$config->get('google_sheets.credentials_path','')!=='';
        self::step('connections','4. Google Sheets and conversion settings',$connection,$section,static function()use($config){self::render_sheets($config);self::render_exports($config);self::verification_form('timezone',$config);});
        self::step('review','5. Review readiness and enable desired features',false,$section,static function()use($config){self::render_setup_issues($config);$d=new SiteEngine_Ads_Bridge_Doctor($config);$d->run();$sum=$d->summary();echo '<p>'.esc_html($sum['PASS'].' checks passed; '.$sum['WARN'].' warnings; '.$sum['FAIL'].' failed checks.').'</p><p>Review the exact failures and warnings in <a href="'.esc_url(admin_url('admin.php?page='.self::PAGE_SLUG.'&tab=advanced&section=diagnostics')).'">Advanced diagnostics</a>. Enable only separately authorized features using the explicit checkboxes in connection/export settings or CRM mapping settings. Nothing is enabled automatically.</p>';});
    }
    private static function render_advanced($config,$section) {
        echo '<h2>Advanced</h2><p>Field overrides, diagnostics and recovery tools. Ordinary setup is on the Setup page.</p><details '.($section==='fields'?'open':'').'><summary>Field overrides and feed verification</summary>';self::render_fields($config);echo '</details><details '.($section==='diagnostics'?'open':'').'><summary>Detailed diagnostics</summary>';self::render_diagnostics($config);echo '</details><details><summary>Scheduler instructions</summary>';self::render_scheduler();echo '</details><details '.($section==='storage'?'open':'').'><summary>Storage inspection and recovery</summary>';self::render_storage();echo '</details><details><summary>Configuration details</summary>';self::render_source_banner($config);echo '</details>';
        if(in_array($config->configuration_source(),['local_file','external'],true)){echo '<details '.($section==='legacy'?'open':'').'><summary>Import legacy settings</summary>';self::render_migration($config);echo '</details>';}
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
		self::render_authority($config);
	}

	/** @return void */
    private static function render_setup($config) {
        $id=(int)$config->get('fluent_forms.form_id',0);$view=SiteEngine_Ads_Bridge_Setup::form_connection($id,(array)$config->get('fluent_forms.fields',[]));
        self::form_open('setup');echo '<table class="form-table"><tbody>';
        self::text_row('Client Slug','client_slug',$config->get('client.slug',''),'Stable business identifier.');self::text_row('Short Name','client_short_name',$config->get('client.short_name',''));
        echo '<tr><th><label for="seab-form-id">Lead form</label></th><td><select id="seab-form-id" name="form_id"><option value="0">Select a form</option>';foreach(self::get_fluent_forms()as$f)echo '<option value="'.esc_attr($f['id']).'" '.selected($id,$f['id'],false).'>'.esc_html($f['title']).'</option>';echo '</select><p>Save a new selection to inspect its fields.</p></td></tr>';
        self::text_row('Required Consent Policy','consent_policy',$config->get('consent.required_policy_version',''),'Must match SiteEngine Consent; never inferred from email subscription.');echo '</tbody></table>';
        echo '<h3>Form connection</h3><p>'.esc_html($view['ready']?'Required advertising fields have unique selectors. Save to retain any newly detected mappings.':$view['error']).'</p>';
        foreach($view['needs']as$key=>$candidates){echo '<p><label for="seab-choice-'.esc_attr($key).'">'.esc_html(ucwords(str_replace('_',' ',$key))).'</label> <select id="seab-choice-'.esc_attr($key).'" name="form_field_'.esc_attr($key).'"><option value="">Choose field; leave incomplete</option>';foreach($view['fields']as$name=>$f)if($f['count']===1&&(!$candidates||in_array($name,$candidates,true)))echo '<option value="'.esc_attr($name).'">'.esc_html($f['label'].' ('.$name.')').'</option>';echo '</select></p>';}
        echo '<p>Standard unique mappings are used by default. Existing custom mappings are preserved. Full overrides are in Advanced.</p>';self::form_close();
        echo '<p><strong>CRM feed is separate.</strong> Field-name checks only verify Ads reading. They do not verify Fluent Forms → FluentCRM delivery or the submission ID feed.</p>';self::verification_form('submission',$config);
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
        self::render_field_provisioning();
        self::verification_form('submission',$config);
		self::form_open( 'fields' );
		echo '<h2>Fluent Form Field Mapping</h2><p>Map Ads Bridge canonical keys to the field names used by the configured master Fluent Form. Defaults match the standard SiteEngine lead form.</p>';
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Canonical Key</th><th>Fluent Forms Field Name</th></tr></thead><tbody>';
		foreach ( SiteEngine_Ads_Bridge_Settings::default_field_map() as $key => $default ) {
			$value = $config->get( 'fluent_forms.fields.' . $key, $default );
			echo '<tr><td><code>' . esc_html( $key ) . '</code></td><td><input class="regular-text" aria-label="Form field: ' . esc_attr( ucwords(str_replace('_',' ',$key)) ) . '" name="field_' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '"></td></tr>';
		}
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
		if ( SiteEngine_Ads_Bridge_Config::instance()->external_override_active() ) { wp_die( 'External SEAB_CONFIG_FILE controls these values. Open Ads Bridge > Setup > Configuration authority to keep intentional file authority or choose WordPress settings without importing the file. File status: ' . SiteEngine_Ads_Bridge_Setup::config_file_status()['state'] ); }
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

		if ( 'setup' === $tab ) {
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
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="seab_initialize_storage">';
		wp_nonce_field( 'seab_initialize_storage' );
		echo '<p><label><input required type="checkbox" name="confirm_storage" value="1"> Initialize required Ads storage on this site. Preserve all existing history and pending work.</label></p>';
		submit_button( 'Initialize Required Ads Storage' );
		echo '</form><p>Existing incomplete or unsupported schemas fail closed and require the supported inquiry-admin migration review. Pending inquiries/configuration journals must be recovered by their owner; initialization never resets them.</p>';
	}

    private static function verification_form($kind,$config) {
        echo '<h2>'.esc_html($kind==='timezone'?'Google Ads Account Timezone — Manual Verification':'Submission ID Feed — Operator Verification').'</h2>';
        $v=SiteEngine_Ads_Bridge_Setup::verification($kind,$config);echo '<p>'.esc_html($v['reason']).'</p>';
        if($v['valid'])echo '<p>'.esc_html('Operator #'.$v['record']['user_id'].'; '.gmdate('Y-m-d H:i:s',$v['record']['timestamp']).' UTC; method: manual_operator').'</p>';
        try{$pin=SiteEngine_Ads_Bridge_Setup::verification_context($kind,$config);}catch(Throwable $e){return;}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="seab_verify_setup"><input type="hidden" name="kind" value="'.esc_attr($kind).'"><input type="hidden" name="context" value="'.esc_attr($pin).'">';wp_nonce_field('seab_verify_setup');
        if($kind==='timezone')echo '<p>Inspect Google Ads &gt; Admin &gt; Account settings for account '.esc_html($config->get('client.google_ads_customer_id','')).'. Effective conversion timezone: '.esc_html($config->get('client.timezone','')).'. This plugin does not query Google Ads.</p><label>Account IANA timezone <input required name="account_timezone"></label>';
        else echo '<p>Inspect Fluent Forms &gt; master form Integrations &gt; FluentCRM: map {submission.id} to submission_id. Confirmation is bound to this form, field mapping and saved form/feed metadata. Optional exact IDs add stored-linkage readback only; no submission or export runs.</p><label>Contact ID (optional) <input type="number" min="1" name="contact_id"></label><label>Submission ID (optional) <input type="number" min="1" name="submission_id"></label>';
        echo '<p><label><input required type="checkbox" name="confirm" value="1"> I inspected these exact inputs. Record my manual confirmation.</label></p>';submit_button('Record Operator Verification');echo '</form>';
    }
    public static function handle_verification() {
        if(!current_user_can('manage_options'))wp_die('Permission denied.');check_admin_referer('seab_verify_setup');
        if(self::post_text('confirm')!=='1')wp_die('Explicit operator confirmation required.');$kind=self::post_text('kind');
        try{SiteEngine_Ads_Bridge_Setup::record_verification($kind,self::post_text('context'),['account_timezone'=>self::post_text('account_timezone'),'contact_id'=>self::post_int('contact_id'),'submission_id'=>self::post_int('submission_id')]);}catch(Throwable $e){wp_die(esc_html($e->getMessage()));}
        wp_safe_redirect(admin_url('admin.php?page='.self::PAGE_SLUG.'&tab='.($kind==='timezone'?'setup':'fields')));exit;
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
