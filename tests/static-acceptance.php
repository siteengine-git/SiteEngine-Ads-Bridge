<?php
/**
 * Dependency-free release acceptance for SiteEngine Ads Bridge.
 * Run: php tests/static-acceptance.php
 */

namespace WP_CLI\Utils {
	function get_flag_value( $assoc_args, $key, $default = false ) {
		return array_key_exists( $key, $assoc_args ) ? $assoc_args[ $key ] : $default;
	}

	function format_items( $format, $items, $fields ) {
		// No-op in unit tests.
	}
}

namespace FluentCrm\App\Models {
	class SubscriberQuery {
		private $first;
		public function __construct( $first = null ) { $this->first = $first; }
		public function where( $key, $value ) { return $this; }
		public function first() { return $this->first; }
		public function filterByTags( $ids ) { return $this; }
		public function select( $value ) { return $this; }
		public function distinct() { return $this; }
		public function whereNotIn( $key, $ids ) { return $this; }
		public function orderBy( $key, $direction ) { return $this; }
		public function limit( $limit ) { return $this; }
		public function get() { return array( $this->first ); }
	}

	class Subscriber {
		public $id = 13;
		public $email = 'Test.User@gmail.com';
		public $phone = '+1 (850) 555-0123';
		public $first_name = ' Test ';
		public $last_name = ' Person ';
		public $ip = '192.0.2.10';
		public $city = 'Tallahassee';
		public $postal_code = '32301';
		public $state = 'Florida';
		public $country = 'United States';
		public $status = 'subscribed';
		public $contact_type = 'lead';
		public $created_at = '2026-07-20 10:00:00';
		public $last_activity = '2026-07-26 11:00:00';
		public $address_line_1 = '123 Test Street';
		public $address_line_2 = '';
		public $tags = array();
		public $lists = array();
		private $custom = array();

		public function __construct( $dnc = false, $reason = 'Lost - Wrong Price' ) {
			$this->tags = array( (object) array( 'title' => $reason ), (object) array( 'title' => 'Form Lead' ) );
			$this->lists = $dnc ? array( (object) array( 'title' => 'DNC' ) ) : array( (object) array( 'title' => 'Future Customers' ) );
			$this->custom = array(
				'submission_id' => '24',
				'lost_date' => '2026-07-26',
				'closed_date' => '2026-07-26',
				'qualified_at' => '2026-07-22 15:30:00',
				'invoiced_amount' => '',
				'gclid' => 'crm-gclid',
				'gbraid' => '',
				'wbraid' => '',
				'se_cid' => 'crm-campaign',
				'se_kw' => 'crm-keyword',
				'service_type' => 'Repair',
			);
		}

		public function custom_fields() { return $this->custom; }
		public function getTable() { return 'wp_fc_subscribers'; }
		public static function find( $id ) { return 13 === (int) $id ? new self() : null; }
		public static function query() { return new SubscriberQuery( new self() ); }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'WP_CLI', true );
	define( 'ARRAY_A', 'ARRAY_A' );
	define( 'SEAB_CONFIG_FILE', __DIR__ . '/fixtures/client.php' );

	class WP_CLI_Test_Error extends \RuntimeException {}

	class WP_CLI {
		public static $lines = array();
		public static function line( $message = '' ) { self::$lines[] = (string) $message; }
		public static function warning( $message ) { self::$lines[] = 'WARN: ' . $message; }
		public static function success( $message ) { self::$lines[] = 'SUCCESS: ' . $message; }
		public static function error( $message ) { throw new WP_CLI_Test_Error( (string) $message ); }
		public static function add_command( $name, $class ) {}
	}

	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
	function wp_check_invalid_utf8( $value, $strip = false ) { return (string) $value; }
	function get_option( $key, $default = false ) { return $default; }
	function is_wp_error( $value ) { return false; }
	function wp_remote_retrieve_response_code( $response ) { return isset( $response['code'] ) ? $response['code'] : 200; }
	function wp_remote_retrieve_body( $response ) { return isset( $response['body'] ) ? $response['body'] : ''; }
	$GLOBALS['seab_http_requests'] = array();
	function wp_remote_request( $url, $args ) {
		$GLOBALS['seab_http_requests'][] = array( 'url' => $url, 'args' => $args );
		return array( 'code' => 200, 'body' => '{}' );
	}
	function wp_remote_post( $url, $args ) {
		$GLOBALS['seab_http_requests'][] = array( 'url' => $url, 'args' => $args );
		return array( 'code' => 200, 'body' => '{}' );
	}

	class SEAB_Test_WPDB {
		public $prefix = 'wp_';
		public $last_error = '';
		public $submission_forms = array( 24 => 3, 25 => 3, 26 => 3 );
		public $ledger_enabled = false;
		public $ledger_appended = array();
		public $ledger_failed = array();
		public $consent_ad_user_data = 'granted';
		public $consent_ad_personalization = 'denied';

		public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
		public function get_charset_collate() { return ''; }
		public function prepare( $query, ...$args ) {
			if ( count( $args ) === 1 && is_array( $args[0] ) ) { $args = $args[0]; }
			foreach ( $args as $arg ) {
				$replacement = is_int( $arg ) || is_float( $arg ) || ctype_digit( (string) $arg ) ? (string) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'";
				$query = preg_replace( '/%[dsf]/', $replacement, $query, 1 );
			}
			return $query;
		}
		public function get_col( $sql ) {
			if ( false !== strpos( $sql, 'SHOW TABLES LIKE' ) ) {
				return array( 'wp_fluentform_submissions', 'wp_fluentform_entry_details', 'wp_se_consent_receipts' );
			}
			if ( preg_match( '/SHOW COLUMNS FROM `([^`]+)`/', $sql, $m ) ) {
				$columns = array(
					'wp_fluentform_submissions' => array( 'id', 'form_id', 'response', 'serial_number', 'status', 'created_at', 'updated_at', 'source_url', 'user_id', 'browser', 'device', 'ip' ),
					'wp_fluentform_entry_details' => array( 'id', 'submission_id', 'field_name', 'field_value' ),
					'wp_se_consent_receipts' => array( 'id', 'receipt_id', 'policy_version', 'banner_version', 'google_consent_json' ),
					'wp_fc_subscriber_meta' => array( 'id', 'subscriber_id', 'object_type', 'key', 'value' ),
				);
				return isset( $columns[ $m[1] ] ) ? $columns[ $m[1] ] : array();
			}
			if ( false !== strpos( $sql, 'SELECT subscriber_id' ) && false !== strpos( $sql, 'wp_fc_subscriber_meta' ) ) {
				return false !== strpos( $sql, "value = '24'" ) ? array( 13 ) : array();
			}
			return array();
		}
		public function get_var( $sql ) {
			if ( false !== strpos( $sql, 'SHOW TABLES LIKE' ) && false !== strpos( $sql, 'wp_se_ads_export_ledger' ) ) {
				return $this->ledger_enabled ? 'wp_se_ads_export_ledger' : null;
			}
			if ( false !== strpos( $sql, 'SELECT COUNT(*) FROM `wp_se_ads_export_ledger`' ) ) {
				$sink = '';
				$txn  = '';
				if ( preg_match( "/sink_slug = '([^']+)'/", $sql, $m ) ) { $sink = $m[1]; }
				if ( preg_match( "/transaction_id = '([^']+)'/", $sql, $m ) ) { $txn = $m[1]; }
				if ( false !== strpos( $sql, "status = 'appended'" ) ) {
					return isset( $this->ledger_appended[ $sink ][ $txn ] ) ? (int) $this->ledger_appended[ $sink ][ $txn ] : 0;
				}
				if ( false !== strpos( $sql, "status = 'failed'" ) ) {
					return isset( $this->ledger_failed[ $sink ][ $txn ] ) ? (int) $this->ledger_failed[ $sink ][ $txn ] : 0;
				}
				return 0;
			}
			if ( false !== strpos( $sql, 'SELECT response FROM `wp_fluentform_submissions`' ) ) {
				return json_encode( array( '_se_consent_server' => array( 'receipt_id' => 'receipt-24' ) ) );
			}
			if ( false !== strpos( $sql, 'fluentform_entry_details' ) ) { return 'receipt-24'; }
			return null;
		}

		public function get_row( $sql, $format = null ) {
			if ( false !== strpos( $sql, 'SELECT id, form_id FROM `wp_fluentform_submissions`' ) ) {
				$id = 24;
				if ( preg_match( '/WHERE id = (\d+)/', $sql, $m ) ) { $id = (int) $m[1]; }
				if ( ! isset( $this->submission_forms[ $id ] ) ) { return null; }
				return array( 'id' => $id, 'form_id' => $this->submission_forms[ $id ] );
			}
			if ( false !== strpos( $sql, 'SELECT * FROM `wp_fluentform_submissions`' ) ) {
				return $this->submission_row();
			}
			if ( false !== strpos( $sql, 'FROM `wp_se_consent_receipts`' ) ) {
				return array(
					'id' => 7,
					'policy_version' => '2026-05-08-v1',
					'banner_version' => '0.5.0-test',
					'google_consent_json' => json_encode( array( 'ad_user_data' => $this->consent_ad_user_data, 'ad_personalization' => $this->consent_ad_personalization ) ),
				);
			}
			return null;
		}
		public function get_results( $sql, $format = null ) { return array(); }
		public function query( $sql ) { return true; }
		public function insert( $table, $data, $formats = null ) { return 1; }
		public function submission_row() {
			return array(
				'id' => 24,
				'form_id' => 3,
				'serial_number' => 24,
				'status' => 'pending',
				'created_at' => '2026-07-20 10:00:00',
				'updated_at' => '2026-07-20 10:00:00',
				'source_url' => 'https://example.test/contact',
				'user_id' => 0,
				'browser' => 'Test Browser',
				'device' => 'desktop',
				'ip' => '192.0.2.10',
				'response' => json_encode( array(
					'first_name' => 'Test', 'last_name' => 'Person', 'email' => 'Test.User@gmail.com',
					'phone' => '8505550123', 'service_type' => 'Repair', 'gclid' => 'form-gclid',
					'gbraid' => 'form-gbraid', 'wbraid' => 'form-wbraid',
					'utm_source' => 'google', 'utm_medium' => 'cpc', 'submission_id' => '24',
					'se_cid' => '123456', 'se_agid' => '654321', 'se_adid' => '777', 'se_kw' => 'ac repair',
					'se_mt' => 'e', 'se_net' => 'g', 'se_dev' => 'm', 'se_loc' => '1015116',
					'_se_consent_server' => array( 'receipt_id' => 'receipt-24' ),
				) ),
			);
		}
	}

	$GLOBALS['wpdb'] = new SEAB_Test_WPDB();

	require dirname( __DIR__ ) . '/includes/class-siteengine-ads-bridge-config.php';
	require dirname( __DIR__ ) . '/includes/class-siteengine-ads-bridge-cli.php';

	$passes = 0;
	$fails  = 0;
	function check( $condition, $message ) {
		global $passes, $fails;
		if ( $condition ) {
			$passes++;
			echo "PASS: {$message}\n";
		} else {
			$fails++;
			echo "FAIL: {$message}\n";
		}
	}
	function invoke_private( $object, $method, array $args = array() ) {
		$ref = new \ReflectionMethod( $object, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $object, $args );
	}
	function set_subscriber_custom( $subscriber, array $custom ) {
		$ref = new \ReflectionProperty( $subscriber, 'custom' );
		$ref->setAccessible( true );
		$ref->setValue( $subscriber, $custom );
	}

	$config = SiteEngine_Ads_Bridge_Config::instance();
	check( array() === $config->validate(), 'isolated schema-v2 acceptance config validates' );
	check( ! file_exists( dirname( __DIR__ ) . '/config/client.php' ), 'release package does not contain an active client config' );
	$example_config = require dirname( __DIR__ ) . '/config/client.example.php';
	check( false === $example_config['exports']['all_leads']['enabled'] && false === $example_config['exports']['qualified_leads']['enabled'] && false === $example_config['exports']['won_jobs']['enabled'] && ! isset( $example_config['exports']['lost_leads'] ) && false === $example_config['google_sheets']['enabled'], 'client example ships with active write families and Google Sheets disabled; legacy Lost feeds are omitted' );
	check( 'closed_date' === $config->get( 'exports.won_jobs.date_field' ), 'won terminal field remains closed_date' );
	check( 'closed_date' === $config->get( 'exports.lost_leads.date_field' ), 'lost terminal field uses shared closed_date / Outcome Date' );

	$cli = new SiteEngine_Ads_Bridge_CLI();
	$all_spec = invoke_private( $cli, 'all_leads_header_spec' );
	check( 94 === count( $all_spec ), 'All_Leads contract has 94 columns including Golden tracking + lifecycle ledger + inquiry context' );
	check( 94 === count( array_unique( array_column( $all_spec, 'k' ) ) ), 'All_Leads contract keys are unique' );

	$contracts = invoke_private( $cli, 'enabled_sheet_contracts', array( $config ) );
	check( 2 === count( $contracts ), 'only the two canonical Sheet contracts are declared' );
	check( 2 === count( array_unique( array_column( $contracts, 'tab' ) ) ), 'enabled Sheet tabs are unique' );
	check( 'Google_Ads_Import' === $contracts[0]['tab'], 'Google_Ads_Import is the first physical Sheet contract' );
	check( 21 === count( invoke_private( $cli, 'data_manager_headers', array( $config, 'won_jobs', '' ) ) ), 'shared Google import feed has 21 Data Manager columns including Conversion action' );
	check( 29 === count( invoke_private( $cli, 'data_manager_headers', array( $config, 'lost_leads', 'wrong_price' ) ) ), 'lost diagnostic feed has 29 columns including Golden Account tracking context' );
	$all_csv = str_getcsv( trim( file_get_contents( dirname( __DIR__ ) . '/docs/headers/all-leads.csv' ) ), ',', '"', '' );
	$gdm_csv = str_getcsv( trim( file_get_contents( dirname( __DIR__ ) . '/docs/headers/google-data-manager.csv' ) ), ',', '"', '' );
	check( array_column( $all_spec, 'h' ) === $all_csv, 'All_Leads reference CSV byte-matches the runtime header contract' );
	check( invoke_private( $cli, 'data_manager_headers', array( $config, 'won_jobs', '' ) ) === $gdm_csv, 'Data Manager reference CSV byte-matches the runtime header contract' );
	check( 'A' === invoke_private( $cli, 'sheet_column_name', array( 1 ) ), 'Sheet column 1 is A' );
	check( 'Z' === invoke_private( $cli, 'sheet_column_name', array( 26 ) ), 'Sheet column 26 is Z' );
	check( 'AA' === invoke_private( $cli, 'sheet_column_name', array( 27 ) ), 'Sheet column 27 is AA' );
	check( 'CB' === invoke_private( $cli, 'sheet_column_name', array( 80 ) ), 'Sheet column 80 is CB' );
	check( 'CJ' === invoke_private( $cli, 'sheet_column_name', array( 88 ) ), 'Sheet column 88 is CJ' );
	check( 'CO' === invoke_private( $cli, 'sheet_column_name', array( 93 ) ), 'Sheet column 93 is CO' );
	check( 'CP' === invoke_private( $cli, 'sheet_column_name', array( 94 ) ), 'Sheet column 94 is CP' );

	$GLOBALS['seab_http_requests'] = array();
	$created = invoke_private( $cli, 'google_sheets_create_tabs', array( 'sheet-id', array( 'All_Leads' => 94 ), 'token' ) );
	$create_request = end( $GLOBALS['seab_http_requests'] );
	$create_body = json_decode( $create_request['args']['body'], true );
	check( true === $created['ok'], 'Sheet create helper accepts a required-width contract' );
	check( 94 === $create_body['requests'][0]['addSheet']['properties']['gridProperties']['columnCount'], 'All_Leads tab is created with 94 columns rather than the default grid width' );

	$GLOBALS['seab_http_requests'] = array();
	$resized = invoke_private( $cli, 'google_sheets_resize_tabs', array( 'sheet-id', array( 'All_Leads' => array( 'sheet_id' => 7, 'column_count' => 94 ) ), 'token' ) );
	$resize_request = end( $GLOBALS['seab_http_requests'] );
	$resize_body = json_decode( $resize_request['args']['body'], true );
	check( true === $resized['ok'], 'Sheet resize helper accepts existing-tab metadata' );
	check( 94 === $resize_body['requests'][0]['updateSheetProperties']['properties']['gridProperties']['columnCount'], 'existing All_Leads grid expands to 94 columns without rewriting cells' );
	check( 'gridProperties.columnCount' === $resize_body['requests'][0]['updateSheetProperties']['fields'], 'Sheet resize request changes column count only' );

	$GLOBALS['seab_http_requests'] = array();
	$moved = invoke_private( $cli, 'google_sheets_move_tab_to_index', array( 'sheet-id', 77, 0, 'token' ) );
	$move_request = end( $GLOBALS['seab_http_requests'] );
	$move_body = json_decode( $move_request['args']['body'], true );
	check( true === $moved['ok'], 'worksheet-order helper accepts a safe index move' );
	check( 77 === $move_body['requests'][0]['updateSheetProperties']['properties']['sheetId'] && 0 === $move_body['requests'][0]['updateSheetProperties']['properties']['index'], 'Google import tab move targets worksheet index 0 only' );
	check( 'index' === $move_body['requests'][0]['updateSheetProperties']['fields'], 'worksheet-order migration changes index only and never cell data' );


	$GLOBALS['seab_http_requests'] = array();
	$written = invoke_private( $cli, 'google_sheets_write_header', array( 'sheet-id', 'All_Leads', array_fill( 0, 94, 'Header' ), 'token' ) );
	$write_request = end( $GLOBALS['seab_http_requests'] );
	check( true === $written['ok'], '94-column header write helper succeeds' );
	check( false !== strpos( $write_request['url'], '%21A1%3ACP1' ), 'All_Leads header write targets A1:CP1' );

	$all = invoke_private( $cli, 'build_all_leads_row', array( $GLOBALS['wpdb']->submission_row(), 'wp_fluentform_submissions', $config ) );
	check( true === $all['exportable'], 'All_Leads row is exportable for a valid configured-form submission' );
	check( 'ff:3:24' === $all['transaction_id'], 'All_Leads transaction ID is deterministic' );
	check( 94 === count( $all['values'] ), 'All_Leads row contains 94 values' );
	check( 'New' === $all['values'][91], 'All_Leads intake row records the New lifecycle event' );
	$all_by_key = array_combine( array_column( $all['spec'], 'k' ), array_column( $all['spec'], 'v' ) );
	check( '123456' === $all_by_key['se_cid'] && 'ac repair' === $all_by_key['se_kw'], 'All_Leads preserves Golden Account ValueTrack context from the canonical form submission' );
	check( array_column( $all_spec, 'h' ) === $all['headers'], 'All_Leads built headers exactly match provisioning contract' );
	check( 13 === $all['contact_id'], 'All_Leads row links the matching CRM contact without requiring it' );

	$unmatched_submission = $GLOBALS['wpdb']->submission_row();
	$unmatched_submission['id'] = 25;
	$unmatched_submission['response'] = json_encode( array( 'first_name' => 'No', 'last_name' => 'CRM Match' ) );
	$unmatched = invoke_private( $cli, 'build_all_leads_row', array( $unmatched_submission, 'wp_fluentform_submissions', $config ) );
	check( true === $unmatched['exportable'], 'All_Leads exports a canonical submission even when no CRM contact matches' );
	check( 0 === $unmatched['contact_id'], 'All_Leads records contact_id 0 when no CRM match exists' );

	$invalid_submission = $GLOBALS['wpdb']->submission_row();
	$invalid_submission['id'] = 26;
	$invalid_submission['response'] = '{invalid-json';
	$invalid = invoke_private( $cli, 'build_all_leads_row', array( $invalid_submission, 'wp_fluentform_submissions', $config ) );
	check( true === $invalid['exportable'], 'All_Leads still records a canonical submission with malformed response JSON' );
	check( false !== strpos( $invalid['values'][18], 'invalid JSON' ), 'All_Leads labels malformed response JSON explicitly' );

	$won_subscriber = new \FluentCrm\App\Models\Subscriber();
	$won_subscriber->tags = array( (object) array( 'title' => 'Won' ), (object) array( 'title' => 'Form Lead' ) );
	$won_custom_ref = new \ReflectionProperty( $won_subscriber, 'custom' );
	$won_custom_ref->setAccessible( true );
	$won_custom_ref->setValue( $won_subscriber, array(
		'submission_id' => '24',
		'closed_date' => '2026-06-24',
		'invoiced_amount' => '999.99',
		'ads_conversion_value_frozen' => '',
		'gclid' => 'test-gclid',
		'gbraid' => '',
		'wbraid' => '',
	) );
	$won = invoke_private( $cli, 'build_google_ads_import', array( $won_subscriber, $config, '12:00:00' ) );
	check( true === $won['exportable'], 'accepted won-job builder remains exportable for the canonical fixture' );
	check( 'ff:3:24' === $won['order_id'], 'won-job builder preserves deterministic Fluent Forms transaction ID' );
	check( '2026-06-24 12:00:00-05:00' === $won['conversion_time'], 'won-job builder preserves America/Chicago DST-aware datetime' );
	check( '999.99' === $won['conv_val'], 'won-job builder preserves invoiced conversion value' );
	check( 21 === count( $won['values'] ), 'converted-lead builder preserves the 21-column Data Manager contract' );
	check( 'SE | Converted Lead' === $won['values'][20], 'converted-lead row emits the standardized conversion action' );
	check( 'form-gclid' === $won['values'][2] && 'form-gbraid' === $won['values'][3] && '' === $won['values'][4], 'converted-lead attribution prefers canonical Fluent Forms click IDs and enforces braid mutual exclusion' );
	check( hash( 'sha256', '+18505550123' ) === $won['values'][6], '10-digit US phone is normalized to E.164 before SHA-256 hashing' );

	$qualified_subscriber = new \FluentCrm\App\Models\Subscriber();
	set_subscriber_custom( $qualified_subscriber, array(
		'submission_id' => '24',
		'qualified_at' => '2026-07-22 15:30:00',
		'gclid' => 'crm-gclid',
		'gbraid' => '',
		'wbraid' => '',
	) );
	$qualified = invoke_private( $cli, 'build_qualified_lead_import', array( $qualified_subscriber, $config, '12:00:00' ) );
	check( true === $qualified['exportable'], 'qualified-lead builder exports a durable qualified_at milestone regardless of later lifecycle state' );
	check( 'ff:3:24' === $qualified['order_id'], 'qualified and converted stages reuse the canonical Fluent Forms transaction ID' );
	check( '2026-07-22 15:30:00-05:00' === $qualified['conversion_time'], 'qualified timestamp is emitted in the client timezone with offset' );
	check( '250.00' === $qualified['conversion_value'], 'configured Qualified Lead proxy value is emitted when present' );
	check( 'SE | Qualified Lead' === $qualified['values'][20], 'qualified row emits the standardized conversion action' );
	check( 'form-gclid' === $qualified['values'][2], 'qualified attribution prefers canonical Fluent Forms submission over CRM fallback' );


	// Configured master form is authoritative for Won and Lost resolution.
	$GLOBALS['wpdb']->submission_forms[24] = 4;
	$wrong_form = invoke_private( $cli, 'build_google_ads_import', array( $won_subscriber, $config, '12:00:00' ) );
	check( false === $wrong_form['exportable'], 'wrong-form stored submission is rejected for advertising export' );
	check( false !== strpos( implode( '; ', $wrong_form['reasons'] ), 'configured master form 3' ), 'wrong-form rejection names the configured master form' );
	$GLOBALS['wpdb']->submission_forms[24] = 3;

	// Won value/date configuration is honored at runtime, not merely validated by doctor.
	$custom_config = new class( $config ) {
		private $base;
		public function __construct( $base ) { $this->base = $base; }
		public function get( $key, $default = null ) {
			if ( 'exports.won_jobs.value_field' === $key ) { return 'job_total'; }
			if ( 'exports.won_jobs.date_field' === $key ) { return 'job_closed'; }
			return $this->base->get( $key, $default );
		}
	};
	$custom_field_subscriber = clone $won_subscriber;
	set_subscriber_custom( $custom_field_subscriber, array(
		'submission_id' => '24', 'job_closed' => '2026-08-15', 'job_total' => '1234.56',
		'ads_conversion_value_frozen' => '', 'gclid' => 'test-gclid', 'gbraid' => '', 'wbraid' => '',
	) );
	$configured_won = invoke_private( $cli, 'build_google_ads_import', array( $custom_field_subscriber, $custom_config, '09:15:00' ) );
	check( true === $configured_won['exportable'], 'configured Won value/date fields produce an exportable row' );
	check( 'job_total' === $configured_won['val_src'] && '1234.56' === $configured_won['conv_val'], 'configured Won value_field controls conversion value' );
	check( 'job_closed' === $configured_won['date_field'] && '2026-08-15 09:15:00-05:00' === $configured_won['conversion_time'], 'configured Won date_field controls conversion time' );

	// Discovery limit is applied after current Transaction-ID dedupe, preventing old
	// ledgered contacts from starving newer work.
	$old = clone $won_subscriber;
	$old->id = 13;
	set_subscriber_custom( $old, array( 'submission_id' => '24' ) );
	$new = clone $won_subscriber;
	$new->id = 14;
	set_subscriber_custom( $new, array( 'submission_id' => '25' ) );
	$GLOBALS['wpdb']->ledger_enabled = true;
	$GLOBALS['wpdb']->ledger_appended = array( 'google_ads_import_converted_lead' => array( 'ff:3:24' => 1 ) );
	$selected = invoke_private( $cli, 'select_current_unledgered_subscribers', array( array( $old, $new ), 'google_ads_import_converted_lead', 1, true ) );
	check( 1 === count( $selected['subscribers'] ) && 14 === (int) $selected['subscribers'][0]->id, 'ledgered Won transaction cannot starve a newer candidate at the limit' );
	check( 1 === $selected['ledger_skipped'], 'preselection records the skipped appended transaction' );

	// 0.5.0 migration safety: historical 0.4.x Won rows used the generic
	// google_ads_import_sheet sink. Converted Lead must honor those rows so an
	// upgrade cannot append the same Won conversion again. Qualified Lead is a
	// new, distinct Google conversion stage and must NOT be suppressed by the
	// historical Won ledger row for the same canonical ff transaction.
	$GLOBALS['wpdb']->ledger_appended = array( 'google_ads_import_sheet' => array( 'ff:3:24' => 1 ) );
	$legacy_converted = invoke_private( $cli, 'select_current_unledgered_subscribers', array( array( $old, $new ), 'google_ads_import_converted_lead', 1, true, array( 'google_ads_import_sheet' ) ) );
	check( 1 === count( $legacy_converted['subscribers'] ) && 14 === (int) $legacy_converted['subscribers'][0]->id, 'legacy 0.4.x Won ledger row suppresses only the matching 0.5 Converted transaction' );
	check( 1 === $legacy_converted['ledger_skipped'], 'legacy Won suppression is counted during Converted candidate prefilter' );
	$qualified_not_suppressed = invoke_private( $cli, 'select_current_unledgered_subscribers', array( array( $old ), 'google_ads_import_qualified_lead', 1, true ) );
	check( 1 === count( $qualified_not_suppressed['subscribers'] ), 'legacy 0.4.x Won ledger row does not suppress the new Qualified Lead stage' );
	check( true === invoke_private( $cli, 'ledger_appended_in_any_sink', array( array( 'google_ads_import_converted_lead', 'google_ads_import_sheet' ), 'ff:3:24' ) ), 'multi-sink dedupe helper recognizes the historical Won sink' );

	// A failed attempt remains retryable because only appended status suppresses work.
	$GLOBALS['wpdb']->ledger_appended = array();
	$GLOBALS['wpdb']->ledger_failed = array( 'google_ads_import_converted_lead' => array( 'ff:3:24' => 1 ) );
	$retry = invoke_private( $cli, 'select_current_unledgered_subscribers', array( array( $old ), 'google_ads_import_converted_lead', 1, true ) );
	check( 1 === count( $retry['subscribers'] ), 'failed prior Sheet attempt remains retryable' );

	// Sequential returning-customer opportunity: same CRM contact ID, new submission.
	$returning = clone $won_subscriber;
	$returning->id = 13;
	set_subscriber_custom( $returning, array( 'submission_id' => '25' ) );
	$GLOBALS['wpdb']->ledger_appended = array( 'lost_leads_sheet' => array( 'ff:3:24' => 1 ) );
	$returning_selected = invoke_private( $cli, 'select_current_unledgered_subscribers', array( array( $returning ), 'lost_leads_sheet', 1, true ) );
	check( 1 === count( $returning_selected['subscribers'] ), 'same contact is eligible for a later sequential transaction despite an older lost ledger row' );
	$GLOBALS['wpdb']->ledger_enabled = false;
	$GLOBALS['wpdb']->ledger_appended = array();
	$GLOBALS['wpdb']->ledger_failed = array();

	$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-siteengine-ads-bridge-cli.php' );
	check( false !== strpos( $source, 'export-lost-leads was retired in Ads Bridge 0.5.1' ), 'legacy Lost worksheet exporter is hard-retired in 0.5.2' );
	check( false !== strpos( $source, 'all_leads_lifecycle_qualified' ) && false !== strpos( $source, 'all_leads_lifecycle_unqualified' ) && false !== strpos( $source, 'all_leads_lifecycle_won' ), 'All_Leads lifecycle stages own independent append-only ledger sinks' );

	check( '+18505550123' === invoke_private( $cli, 'normalize_phone', array( '850-555-0123' ) ), 'phone normalizer converts 10-digit US numbers to E.164' );
	check( '+18505550123' === invoke_private( $cli, 'normalize_phone', array( '1 (850) 555-0123' ) ), 'phone normalizer converts 11-digit US numbers to E.164' );
	check( '+442071838750' === invoke_private( $cli, 'normalize_phone', array( '+44 20 7183 8750' ) ), 'phone normalizer preserves explicit international E.164 intent' );
	check( 'testuser+gold@gmail.com' === invoke_private( $cli, 'normalize_email', array( 'Test.User+gold@gmail.com' ) ), 'Gmail normalization strips periods but preserves plus aliases' );

	$dt = invoke_private( $cli, 'build_terminal_datetime', array( '2026-12-15', '08:30:00', 'America/Chicago' ) );
	check( '2026-12-15 08:30:00-06:00' === $dt['value'], 'terminal datetime observes winter CST offset' );

	$thrown = false;
	try { invoke_private( $cli, 'parse_family_batch_args', array( array(), 25, false ) ); } catch ( WP_CLI_Test_Error $e ) { $thrown = true; }
	check( $thrown, 'new export families refuse to run without --dry-run or --apply' );

	check( false === strpos( $source, '--force' ), 'removed --force flag is absent from CLI source' );
	check( false === strpos( $source, 'ledger_appended_contact_ids' ), 'lost discovery no longer suppresses a CRM contact for life' );
	$append_body_start = strpos( $source, 'public function append_row' );
	$append_body_end   = strpos( $source, 'public function export_batch', $append_body_start );
	$append_body       = substr( $source, $append_body_start, $append_body_end - $append_body_start );
	check( false === strpos( $append_body, 'google_sheets_append_row' ), 'append-row has no direct Sheet write path' );
	check( false === strpos( $append_body, 'ledger_insert' ), 'append-row has no independent ledger implementation' );
	check( 2 === substr_count( $source, '$this->google_sheets_append_row(' ), 'only the won exporter and shared family exporter can append Sheet rows' );

	$public_methods = array_filter( ( new \ReflectionClass( $cli ) )->getMethods( \ReflectionMethod::IS_PUBLIC ), function ( $method ) { return '__construct' !== $method->name; } );
	check( 19 === count( $public_methods ), 'CLI exposes 19 public commands including inquiry-admin' );

	$package_source = $source . file_get_contents( dirname( __DIR__ ) . '/includes/class-siteengine-ads-bridge-doctor.php' );
	check( false === strpos( $package_source, '987-654-3210' ) && false === strpos( $package_source, 'LeadEngine - Won Job' ), 'client-neutral runtime code contains no practice Ads account identifiers' );
	$main_source = file_get_contents( dirname( __DIR__ ) . '/siteengine-ads-bridge.php' );
	check( false !== strpos( $main_source, 'Version: 0.6.3' ) && false !== strpos( $main_source, "SEAB_VERSION', '0.6.3'" ), 'release version is 0.6.3 in header and runtime constant' );

	echo "\nSUMMARY: {$passes} PASS, {$fails} FAIL\n";
	exit( 0 === $fails ? 0 : 1 );
}
