<?php
/** Explicit administrator setup; no traffic migration or delivery. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SiteEngine_Ads_Bridge_Setup {
    const AUTHORITY_OPTION = 'seab_settings_authority_v1';

    /** A deliberate migration applies only to the reviewed external file bytes. */
    public static function settings_authority() {
        $choice = get_option( self::AUTHORITY_OPTION, array() );
        return SiteEngine_Ads_Bridge_Settings::exists()
            && is_array( $choice ) && isset( $choice['path'], $choice['sha256'] )
            && defined( 'SEAB_CONFIG_FILE' ) && $choice['path'] === SEAB_CONFIG_FILE
            && is_file( SEAB_CONFIG_FILE )
            && hash_equals( (string) $choice['sha256'], hash_file( 'sha256', SEAB_CONFIG_FILE ) );
    }

    /** Consult the existing shared executor and durable job; never take over a job. */
    public static function with_executor( callable $callback ) {
        $lock = null;
        try {
            $directory = null;
            if ( function_exists( 'se_crm_deployment_directory' ) ) {
                $directory = se_crm_deployment_directory();
            } elseif ( class_exists( '\SiteEngine\CRM\AdminConfiguration' ) ) {
                $directory = \SiteEngine\CRM\AdminConfiguration::directory();
            }
            if ( null !== $directory ) {
                if ( ! is_dir( $directory ) || is_link( $directory ) ) { throw new RuntimeException( 'Shared executor directory is unavailable.' ); }
                $lock = fopen( $directory . '/executor.lock', 'c' );
                if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) { throw new RuntimeException( 'Another configuration executor is running. Finish that job before changing Ads setup.' ); }
                $path = $directory . '/deployment-job.json';
                if ( is_link( $path ) ) { throw new RuntimeException( 'Shared job record requires review.' ); }
                if ( is_file( $path ) ) {
                    $job = json_decode( file_get_contents( $path ), true );
                    if ( ! is_array( $job ) || ( $job['format'] ?? '' ) !== 'siteengine.crm.deployment-job/1' || ( $job['state'] ?? '' ) !== 'complete' ) {
                        throw new RuntimeException( 'An unfinished shared configuration job owns these settings. Complete or explicitly hand off that job first.' );
                    }
                }
            }
            return $callback();
        } finally {
            if ( is_resource( $lock ) ) { flock( $lock, LOCK_UN ); fclose( $lock ); }
        }
    }

    /** Guard configuration saves against unfinished Ads history and journals. */
    public static function with_configuration( callable $callback ) {
        return self::with_executor( static function() use ( $callback ) {
            // Fresh setup can save first, while still checking any existing journal.
            $held = SiteEngine_Ads_Bridge_Coordination::enter_setup( true );
            if ( ! $held['ok'] ) { throw new RuntimeException( $held['reason'] ); }
            try { return $callback(); }
            finally { if ( ! SiteEngine_Ads_Bridge_Coordination::release() ) { throw new RuntimeException( 'Ads configuration lock release could not be confirmed. Inspect state before retrying.' ); } }
        } );
    }

    /** Reuse existing explicit migrations; existing partial schemas fail closed. */
    public static function initialize_storage() {
        return self::with_executor( static function() {
            $held = SiteEngine_Ads_Bridge_Coordination::enter_setup( true );
            if ( ! $held['ok'] ) { throw new RuntimeException( 'Storage initialization blocked: ' . $held['reason'] . '. Existing incomplete schemas require the supported inquiry-admin migration review; no rows or journals were reset.' ); }
            try {
                if ( ! SiteEngine_Ads_Bridge_Inquiries::table_exists() || ! SiteEngine_Ads_Bridge_Inquiries::schema_ready() ) {
                    $inquiry = SiteEngine_Ads_Bridge_Inquiries::migrate( true );
                    if ( empty( $inquiry['applied'] ) ) { throw new RuntimeException( 'Inquiry storage initialization was not confirmed. Review Storage before retrying.' ); }
                }
            } finally {
                if ( ! SiteEngine_Ads_Bridge_Coordination::release() ) { throw new RuntimeException( 'Storage lock release unconfirmed. Inspect storage before retrying.' ); }
            }
            $guard = SiteEngine_Ads_Bridge_Coordination::migrate( true );
            if ( $guard['status'] !== 'complete' ) { throw new RuntimeException( 'Configuration guard initialization blocked: ' . $guard['reason_code'] ); }
            return true;
        } );
    }

    /** Exact existing tag titles only; ambiguous titles cannot establish identity. */
    public static function validate_lifecycle( $mapping ) {
        global $wpdb;
        $seen = array();
        foreach ( SiteEngine_Ads_Bridge_Config::lifecycle_roles() as $role ) {
            $title = $mapping[ $role ] ?? '';
            if ( ! is_string( $title ) || '' === trim( $title ) || isset( $seen[ $title ] ) ) { throw new RuntimeException( 'Select four distinct existing lifecycle tags in CRM & Lifecycle; check ' . $role . '.' ); }
            $table = $wpdb->prefix . 'fc_tags';
            $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE BINARY title = %s", $title ) );
            if ( $wpdb->last_error || ! is_array( $ids ) || count( $ids ) !== 1 ) { throw new RuntimeException( ucfirst( $role ) . ' lifecycle tag must identify exactly one existing FluentCRM tag. Create missing tags in FluentCRM > Contacts > Tags, then select them in Ads Bridge > CRM & Lifecycle.' ); }
            $seen[ $title ] = true;
        }
    }
}
