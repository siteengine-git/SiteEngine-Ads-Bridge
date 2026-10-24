<?php
/**
 * WP-CLI commands for SiteEngine Ads Bridge.
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
        return;
}

class SiteEngine_Ads_Bridge_CLI {

        /**
         * Inspect active config.
         *
         * Registered as:
         * wp se-ads inspect-config   (alias: wp se-ads inspect_config)
         *
         * @subcommand inspect-config
         * @alias inspect_config
         *
         * @return void
         */
        public function inspect_config() {
                $config = SiteEngine_Ads_Bridge_Config::instance();

                $errors = $config->validate();

                WP_CLI::line( 'SiteEngine Ads Bridge config' );
                WP_CLI::line( '----------------------------' );

                $mode      = $config->schema_mode();
                $mode_line = 'invalid';

                if ( 'v2' === $mode ) {
                        $mode_line = sprintf( 'v2 (schema_version %d)', SiteEngine_Ads_Bridge_Config::SUPPORTED_SCHEMA_VERSION );
                } elseif ( 'legacy' === $mode ) {
                        $mode_line = 'LEGACY COMPATIBILITY MODE (schema-less v1 config resolved through the fixed compatibility mapping)';
                }

                WP_CLI::line( 'Schema mode : ' . $mode_line );
                WP_CLI::line( 'Config source : ' . $config->config_file_path() );
                WP_CLI::line( '' );

                foreach ( $config->deprecations() as $notice ) {
                        WP_CLI::warning( 'Compatibility: ' . $notice );
                }

                if ( ! empty( $errors ) ) {
                        foreach ( $errors as $error ) {
                                WP_CLI::warning( $error );
                        }
                }

                $spreadsheet_id = (string) $config->get( 'google_sheets.spreadsheet_id', '' );
                if ( strlen( $spreadsheet_id ) > 10 ) {
                        $spreadsheet_id = substr( $spreadsheet_id, 0, 4 ) . str_repeat( '*', 6 ) . substr( $spreadsheet_id, -4 );
                }

                $rows = array();

                $scalar_keys = array(
                        'client.slug',
                        'client.name',
                        'client.short_name',
                        'client.google_ads_customer_id',
                        'client.timezone',
                        'client.currency',
                        'fluent_forms.form_id',
                        'fluentcrm.lists.customers',
                        'fluentcrm.lists.future_customers',
                        'fluentcrm.lists.do_not_contact',
                        'google_sheets.enabled',
                        'google_sheets.credentials_path',
                        'google_sheets.value_input_option',
                        'consent.required_policy_version',
                        'exports.qualified_leads.enabled',
                        'exports.qualified_leads.conversion_action',
                        'exports.qualified_leads.date_field',
                        'exports.qualified_leads.conversion_value',
                        'exports.won_jobs.value_field',
                        'exports.won_jobs.date_field',
                        'fluentcrm.lifecycle_automation.enabled',
                        'fluentcrm.lifecycle_automation.unqualified_reason_field',
                        'fluentcrm.lifecycle_automation.unqualified_at_field',
                        'fluentcrm.lifecycle_automation.stale_new_days',
                        'diagnostics.test_profile_guards',
                        'diagnostics.cron_expected_minutes',
                );

                foreach ( $scalar_keys as $key ) {
                        $value = $config->get( $key, '' );
                        if ( is_bool( $value ) ) {
                                $value = $value ? 'true' : 'false';
                        }
                        $rows[] = array(
                                'key'    => $key,
                                'value'  => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
                                'source' => $config->provenance( $key ),
                        );
                }

                $rows[] = array(
                        'key'    => 'google_sheets.spreadsheet_id',
                        'value'  => $spreadsheet_id,
                        'source' => $config->provenance( 'google_sheets.spreadsheet_id' ),
                );
                $rows[] = array(
                        'key'    => 'wordpress_timezone',
                        'value'  => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '',
                        'source' => 'WordPress runtime (informational)',
                );

                WP_CLI\Utils\format_items( 'table', $rows, array( 'key', 'value', 'source' ) );

                WP_CLI::line( '' );
                WP_CLI::line( 'Lifecycle tags (authoritative export lifecycle; exactly one configured lifecycle tag required)' );
                WP_CLI::line( '--------------------------------------------------------------------------------------' );

                $tag_rows = array();
                foreach ( SiteEngine_Ads_Bridge_Config::lifecycle_roles() as $role ) {
                        $tag_key    = 'fluentcrm.tags.lifecycle.' . $role;
                        $tag_value  = $config->get( $tag_key, '' );
                        $tag_rows[] = array(
                                'role'   => $role,
                                'tag'    => is_string( $tag_value ) && '' !== $tag_value ? $tag_value : '(not configured)',
                                'source' => '' !== (string) $config->provenance( $tag_key ) ? $config->provenance( $tag_key ) : '(not configured)',
                        );
                }
                WP_CLI\Utils\format_items( 'table', $tag_rows, array( 'role', 'tag', 'source' ) );

                WP_CLI::line( '' );
                WP_CLI::line( 'Export families (Qualified + Converted share Google_Ads_Import; Unqualified is internal only)' );
                WP_CLI::line( '--------------------------------------------------------------------------------------' );

                $family_rows = array(
                        array(
                                'family'            => 'all_leads',
                                'enabled'           => (bool) $config->get( 'exports.all_leads.enabled', false ) ? 'true' : 'false',
                                'sheet_tab'         => (string) $config->get( 'exports.all_leads.sheet_tab', '' ),
                                'conversion_action' => '(none: operational export, not a Google conversion)',
                        ),
                        array(
                                'family'            => 'qualified_leads',
                                'enabled'           => (bool) $config->get( 'exports.qualified_leads.enabled', false ) ? 'true' : 'false',
                                'sheet_tab'         => (string) $config->get( 'exports.won_jobs.sheet_tab', '' ),
                                'conversion_action' => (string) $config->get( 'exports.qualified_leads.conversion_action', 'SE | Qualified Lead' ),
                        ),
                        array(
                                'family'            => 'won_jobs / converted_leads',
                                'enabled'           => (bool) $config->get( 'exports.won_jobs.enabled', false ) ? 'true' : 'false',
                                'sheet_tab'         => (string) $config->get( 'exports.won_jobs.sheet_tab', '' ),
                                'conversion_action' => (string) $config->get( 'exports.won_jobs.conversion_action', 'SE | Converted Lead' ),
                        ),
                );


                WP_CLI\Utils\format_items( 'table', $family_rows, array( 'family', 'enabled', 'sheet_tab', 'conversion_action' ) );

                WP_CLI::line( '' );
                WP_CLI::line(
                        sprintf(
                                'Won authority : configured lifecycle tag "%s" (ACTIVE). Candidate discovery and eligibility use the configured Won lifecycle tag; lead_status is not an authority.',
                                (string) $config->get( 'fluentcrm.tags.lifecycle.won', '(unconfigured)' )
                        )
                );
                WP_CLI::line( 'Google Ads API upload : DISABLED (Sheets are the only sink; append-only).' );
                WP_CLI::line( 'CRM mutation          : export/doctor/inspection/Sheet-provisioning commands are read-only. Optional lifecycle compliance automation may maintain lifecycle tags and milestone timestamps.' );

                if ( empty( $errors ) ) {
                        WP_CLI::success( 'Config loaded and passed validation for its schema mode.' );
                        return;
                }

                WP_CLI::error( 'Config validation failed.' );
        }

        /**
         * Run doctor checks.
         *
         * Registered explicitly as:
         * wp se-ads doctor
         *
         * @return void
         */
        public function doctor() {
                $doctor  = new SiteEngine_Ads_Bridge_Doctor();
                $results = $doctor->run();

                WP_CLI::line( 'SiteEngine Ads Bridge doctor' );
                WP_CLI::line( '----------------------------' );

                WP_CLI\Utils\format_items(
                        'table',
                        $results,
                        array(
                                'status',
                                'check',
                                'message',
                        )
                );

                $summary = $doctor->summary();

                WP_CLI::line( '' );
                WP_CLI::line(
                        sprintf(
                                'Summary: %d PASS, %d WARN, %d FAIL, %d PENDING',
                                $summary[ SiteEngine_Ads_Bridge_Doctor::STATUS_PASS ],
                                $summary[ SiteEngine_Ads_Bridge_Doctor::STATUS_WARN ],
                                $summary[ SiteEngine_Ads_Bridge_Doctor::STATUS_FAIL ],
                                $summary[ SiteEngine_Ads_Bridge_Doctor::STATUS_PENDING ]
                        )
                );

                if ( $summary[ SiteEngine_Ads_Bridge_Doctor::STATUS_FAIL ] > 0 ) {
                        WP_CLI::error( 'Doctor failed.' );
                }

                if ( $summary[ SiteEngine_Ads_Bridge_Doctor::STATUS_WARN ] > 0 || $summary[ SiteEngine_Ads_Bridge_Doctor::STATUS_PENDING ] > 0 ) {
                        WP_CLI::warning( 'Doctor completed with warnings or pending checks.' );
                        return;
                }

                WP_CLI::success( 'Doctor passed.' );
        }

        /**
         * Show the read-only lead compliance work queue.
         *
         * ## OPTIONS
         *
         * [--limit=<n>]
         * : Maximum contacts to inspect, newest first. Default: 250.
         *
         * @subcommand compliance
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function compliance( $args, $assoc_args ) {
                unset( $args );
                $limit  = max( 1, min( 1000, (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 250 ) ) );
                $report = SiteEngine_Ads_Bridge_Lifecycle::compliance_report( $limit );

                WP_CLI::line( 'SiteEngine Ads Bridge: lead compliance (READ-ONLY)' );
                WP_CLI::line( '-------------------------------------------------' );
                WP_CLI::line( sprintf( 'Inspected %d contact(s).', (int) $report['inspected'] ) );
                WP_CLI::line( '' );

                $summary_rows = array();
                foreach ( (array) $report['summary'] as $issue => $count ) {
                        $summary_rows[] = array( 'issue' => $issue, 'count' => (int) $count );
                }
                WP_CLI\Utils\format_items( 'table', $summary_rows, array( 'issue', 'count' ) );

                if ( ! empty( $report['issues'] ) ) {
                        WP_CLI::line( '' );
                        WP_CLI\Utils\format_items( 'table', $report['issues'], array( 'contact_id', 'contact', 'lifecycle', 'issue', 'detail' ) );
                        WP_CLI::warning( sprintf( '%d compliance issue(s) found. No CRM or Sheet changes were made.', count( $report['issues'] ) ) );
                        return;
                }

                WP_CLI::success( 'No compliance issues found in the inspected contacts.' );
        }

        /**
         * Provision the FluentCRM contact custom fields the bridge requires.
         *
         * Dry-run by default; pass --apply to write. Creates missing fields ONLY.
         * Never renames, retypes, or deletes existing fields. Idempotent. This is a
         * standalone, opt-in command and is never invoked by doctor or on a schedule.
         *
         * Registered as:
         * wp se-ads provision-fields [--apply]   (alias: wp se-ads provision_fields)
         *
         * ## OPTIONS
         *
         * [--apply]
         * : Write missing fields to FluentCRM. Without this flag the command only
         * previews what would change and makes no modifications.
         *
         * @subcommand provision-fields
         * @alias provision_fields
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function provision_fields( $args, $assoc_args ) {
                $apply = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );

                // Required field definitions. Slugs are LOCKED — the doctor asserts these
                // exact slugs, and they are passed explicitly so saveGlobalFields() does
                // not derive (and truncate) them. Labels and types are the schema.
                $required = array(
                        array(
                                'slug'  => 'invoiced_amount',
                                'label' => 'Invoiced Amount',
                                'type'  => 'number',
                        ),
                        array(
                                'slug'  => 'closed_date',
                                'label' => 'Closed Date',
                                'type'  => 'date',
                        ),
                        array(
                                'slug'  => 'qualified_at',
                                'label' => 'Qualified At',
                                'type'  => 'date_time',
                        ),
                        array(
                                'slug'  => 'unqualified_at',
                                'label' => 'Unqualified At',
                                'type'  => 'date_time',
                        ),
                        array(
                                'slug'  => 'submission_id',
                                'label' => 'Fluent Forms Submission ID',
                                'type'  => 'text',
                        ),
                        array(
                                'slug'  => 'ads_export_status',
                                'label' => 'Ads Export Status',
                                'type'  => 'text',
                        ),
                        array(
                                'slug'  => 'ads_exported_at',
                                'label' => 'Ads Exported At',
                                'type'  => 'date_time',
                        ),
                        array(
                                'slug'  => 'ads_conversion_value_frozen',
                                'label' => 'Ads Conversion Value Frozen',
                                'type'  => 'number',
                        ),
                        array(
                                'slug'  => 'ads_export_error',
                                'label' => 'Ads Export Error',
                                'type'  => 'textarea',
                        ),
                );

                if ( ! class_exists( '\FluentCrm\App\Models\CustomContactField' ) ) {
                        WP_CLI::error( 'FluentCRM is not active (CustomContactField model not found). Cannot provision fields.' );
                }

                $manager = new \FluentCrm\App\Models\CustomContactField();

                // Validate every proposed type against this install's live field-type
                // registry before touching anything.
                $valid_types = array_keys( (array) $manager->getFieldTypes() );

                $invalid = array();
                foreach ( $required as $field ) {
                        if ( ! in_array( $field['type'], $valid_types, true ) ) {
                                $invalid[] = $field['slug'] . ' (' . $field['type'] . ')';
                        }
                }

                if ( ! empty( $invalid ) ) {
                        WP_CLI::error(
                                'Unsupported field type(s) on this FluentCRM install: ' . implode( ', ', $invalid )
                                . '. Valid types: ' . implode( ', ', $valid_types )
                        );
                }

                // Read the existing full set.
                $global   = $manager->getGlobalFields();
                $existing = ( isset( $global['fields'] ) && is_array( $global['fields'] ) ) ? $global['fields'] : array();

                $existing_by_slug = array();
                foreach ( $existing as $field ) {
                        if ( isset( $field['slug'] ) ) {
                                $existing_by_slug[ $field['slug'] ] = $field;
                        }
                }

                // Build the report and the append set (missing only).
                $rows      = array();
                $to_append = array();

                foreach ( $required as $field ) {
                        $slug = $field['slug'];

                        if ( isset( $existing_by_slug[ $slug ] ) ) {
                                $current_type = isset( $existing_by_slug[ $slug ]['type'] ) ? $existing_by_slug[ $slug ]['type'] : '';
                                $note         = '';

                                if ( $current_type !== $field['type'] ) {
                                        $note = sprintf( 'existing type "%s" != expected "%s" (left unchanged)', $current_type, $field['type'] );
                                }

                                $rows[] = array(
                                        'slug'   => $slug,
                                        'type'   => $field['type'],
                                        'state'  => 'exists',
                                        'action' => 'skip',
                                        'note'   => $note,
                                );

                                continue;
                        }

                        $rows[] = array(
                                'slug'   => $slug,
                                'type'   => $field['type'],
                                'state'  => 'missing',
                                'action' => $apply ? 'create' : 'would create',
                                'note'   => '',
                        );

                        $to_append[] = array(
                                'slug'  => $slug,
                                'label' => $field['label'],
                                'type'  => $field['type'],
                        );
                }

                WP_CLI::line( 'SiteEngine Ads Bridge: provision FluentCRM custom fields' );
                WP_CLI::line( '-------------------------------------------------------' );
                WP_CLI::line( $apply ? 'Mode: APPLY (writing missing fields)' : 'Mode: DRY-RUN (no changes; pass --apply to write)' );
                WP_CLI::line( '' );

                WP_CLI\Utils\format_items( 'table', $rows, array( 'slug', 'type', 'state', 'action', 'note' ) );

                // Surface (never act on) any existing field whose type differs.
                foreach ( $rows as $row ) {
                        if ( 'exists' === $row['state'] && '' !== $row['note'] ) {
                                WP_CLI::warning( sprintf( '%s: %s', $row['slug'], $row['note'] ) );
                        }
                }

                $missing_count = count( $to_append );

                if ( 0 === $missing_count ) {
                        WP_CLI::success( 'All required custom fields already exist. Nothing to do.' );
                        return;
                }

                if ( ! $apply ) {
                        WP_CLI::line( '' );
                        WP_CLI::line( sprintf( '%d field(s) would be created. Re-run with --apply to write them.', $missing_count ) );
                        WP_CLI::warning( 'Dry-run only. No changes were made.' );
                        return;
                }

                // APPLY: save the full merged set (existing preserved verbatim, missing appended).
                $merged = array_merge( $existing, $to_append );
                $manager->saveGlobalFields( $merged );

                // Re-read and assert every required slug now resolves.
                $after        = $manager->getGlobalFields();
                $after_fields = ( isset( $after['fields'] ) && is_array( $after['fields'] ) ) ? $after['fields'] : array();

                $after_slugs = array();
                foreach ( $after_fields as $field ) {
                        if ( isset( $field['slug'] ) ) {
                                $after_slugs[] = $field['slug'];
                        }
                }

                $still_missing = array();
                foreach ( $required as $field ) {
                        if ( ! in_array( $field['slug'], $after_slugs, true ) ) {
                                $still_missing[] = $field['slug'];
                        }
                }

                if ( ! empty( $still_missing ) ) {
                        WP_CLI::error(
                                'Write completed but these required slugs did not materialize: '
                                . implode( ', ', $still_missing ) . '. Inspect FluentCRM field storage before retrying.'
                        );
                }

                WP_CLI::success(
                        sprintf(
                                '%d field(s) created. All %d required slugs verified present.',
                                $missing_count,
                                count( $required )
                        )
                );
        }

        /**
         * Inspect a single FluentCRM contact (read-only).
         *
         * Reports the contact's identity, its list/tag state relative to the
         * configured crm_map, the configured Ads Bridge custom fields, and a read-only
         * verdict on whether the contact would currently be eligible for Won-row
         * construction. This command never mutates CRM state and never builds a
         * conversion row. Lookup is by FluentCRM contact id only in this first cut.
         *
         * Registered as:
         * wp se-ads inspect-contact --id=<contact_id>   (alias: wp se-ads inspect_contact)
         *
         * ## OPTIONS
         *
         * --id=<contact_id>
         * : The FluentCRM contact (subscriber) id to inspect.
         *
         * @subcommand inspect-contact
         * @alias inspect_contact
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function inspect_contact( $args, $assoc_args ) {
                $id_raw = WP_CLI\Utils\get_flag_value( $assoc_args, 'id', '' );

                if ( '' === $id_raw || ! ctype_digit( (string) $id_raw ) ) {
                        WP_CLI::error( 'Provide a numeric FluentCRM contact id, e.g. --id=42.' );
                }

                $id = (int) $id_raw;

                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                        WP_CLI::error( 'FluentCRM is not active (Subscriber model not found).' );
                }

                $subscriber = \FluentCrm\App\Models\Subscriber::find( $id );

                if ( ! $subscriber ) {
                        WP_CLI::error( sprintf( 'No FluentCRM contact found with id %d.', $id ) );
                }

                $email = (string) $subscriber->email;
                $phone = (string) $subscriber->phone;
                $full  = trim( (string) $subscriber->full_name );

                WP_CLI::line( 'SiteEngine Ads Bridge: inspect contact (read-only)' );
                WP_CLI::line( '--------------------------------------------------' );

                $identity = array(
                        array(
                                'field' => 'id',
                                'value' => (string) $subscriber->id,
                        ),
                        array(
                                'field' => 'email',
                                'value' => '' !== $email ? $email : '(none)',
                        ),
                        array(
                                'field' => 'full_name',
                                'value' => '' !== $full ? $full : '(none)',
                        ),
                        array(
                                'field' => 'phone',
                                'value' => '' !== $phone ? $phone : '(none)',
                        ),
                        array(
                                'field' => 'status',
                                'value' => (string) $subscriber->status,
                        ),
                        array(
                                'field' => 'contact_type',
                                'value' => (string) $subscriber->contact_type,
                        ),
                        array(
                                'field' => 'created_at',
                                'value' => (string) $subscriber->created_at,
                        ),
                        array(
                                'field' => 'last_activity',
                                'value' => (string) $subscriber->last_activity,
                        ),
                );

                WP_CLI\Utils\format_items( 'table', $identity, array( 'field', 'value' ) );

                // Build name sets (title + slug) for the contact's actual memberships,
                // plus title-only lists for raw display.
                $tag_names   = array();
                $tag_titles  = array();
                foreach ( $subscriber->tags as $tag ) {
                        if ( isset( $tag->title ) ) {
                                $tag_names[]  = $tag->title;
                                $tag_titles[] = $tag->title;
                        }
                        if ( isset( $tag->slug ) ) {
                                $tag_names[] = $tag->slug;
                        }
                }

                $list_names  = array();
                $list_titles = array();
                foreach ( $subscriber->lists as $list ) {
                        if ( isset( $list->title ) ) {
                                $list_names[]  = $list->title;
                                $list_titles[] = $list->title;
                        }
                        if ( isset( $list->slug ) ) {
                                $list_names[] = $list->slug;
                        }
                }

                WP_CLI::line( '' );
                WP_CLI::line( 'Lifecycle membership (raw):' );
                WP_CLI::line( '  Tags:  ' . ( ! empty( $tag_titles ) ? implode( ', ', $tag_titles ) : '(none)' ) );
                WP_CLI::line( '  Lists: ' . ( ! empty( $list_titles ) ? implode( ', ', $list_titles ) : '(none)' ) );

                // Resolve crm_map roles against actual membership.
                $config   = SiteEngine_Ads_Bridge_Config::instance();
                $list_map = (array) $config->get( 'crm_map.lists', array() );
                $tag_map  = (array) $config->get( 'crm_map.tags', array() );

                $map_rows = array();

                foreach ( $list_map as $role => $name ) {
                        if ( '' === (string) $name ) {
                                $map_rows[] = array(
                                        'kind'      => 'list',
                                        'role'      => $role,
                                        'mapped_to' => '(unmapped)',
                                        'member'    => 'n/a',
                                );
                                continue;
                        }

                        $map_rows[] = array(
                                'kind'      => 'list',
                                'role'      => $role,
                                'mapped_to' => $name,
                                'member'    => in_array( $name, $list_names, true ) ? 'yes' : 'no',
                        );
                }

                // Source tags are nested under crm_map.tags.sources and are inert for
                // export eligibility; shown for diagnostics only. Flattened so the
                // (string) cast and in_array() never receive the nested sub-array.
                $source_tags = ( isset( $tag_map['sources'] ) && is_array( $tag_map['sources'] ) ) ? $tag_map['sources'] : array();
                foreach ( $source_tags as $role => $name ) {
                        if ( '' === (string) $name ) {
                                $map_rows[] = array(
                                        'kind'      => 'tag',
                                        'role'      => 'source:' . $role,
                                        'mapped_to' => '(unmapped)',
                                        'member'    => 'n/a',
                                );
                                continue;
                        }

                        $map_rows[] = array(
                                'kind'      => 'tag',
                                'role'      => 'source:' . $role,
                                'mapped_to' => $name,
                                'member'    => in_array( $name, $tag_names, true ) ? 'yes' : 'no',
                        );
                }

                WP_CLI::line( '' );
                WP_CLI::line( 'crm_map role membership:' );
                WP_CLI\Utils\format_items( 'table', $map_rows, array( 'kind', 'role', 'mapped_to', 'member' ) );

                // Read the Ads Bridge custom field values.
                $required_slugs = array(
                        'submission_id',
                        'invoiced_amount',
                        'closed_date',
                        'qualified_at',
                        'unqualified_at',
                        'customer_rating',
                        'lost_date',
                        'ads_export_status',
                        'ads_exported_at',
                        'ads_conversion_value_frozen',
                        'ads_export_error',
                );

                $custom = array();
                if ( method_exists( $subscriber, 'custom_fields' ) ) {
                        $custom = (array) $subscriber->custom_fields();
                } else {
                        WP_CLI::warning( 'This FluentCRM version does not expose Subscriber::custom_fields(); custom field values unavailable.' );
                }

                $cf_rows = array();
                foreach ( $required_slugs as $slug ) {
                        $present = array_key_exists( $slug, $custom ) && null !== $custom[ $slug ] && '' !== $custom[ $slug ];

                        if ( $present ) {
                                $raw   = $custom[ $slug ];
                                $value = is_array( $raw ) ? implode( '|', $raw ) : (string) $raw;
                        } else {
                                $value = '(not set)';
                        }

                        $cf_rows[] = array(
                                'slug'  => $slug,
                                'value' => $value,
                        );
                }

                WP_CLI::line( '' );
                WP_CLI::line( 'Ads Bridge custom fields:' );
                WP_CLI\Utils\format_items( 'table', $cf_rows, array( 'slug', 'value' ) );

                // Consent provenance (read-only diagnostics). Attaches to the same
                // deterministic canonical Fluent Forms submission used for the Order ID,
                // resolves the stamped receipt, and shows the receipt-authority consent
                // values used by the conversion builder for the two Google consent
                // columns and the consent export gate. Loose CRM/form fields are shown
                // separately and explicitly marked diagnostic-only.
                $resolved_oid               = $this->resolve_won_order_id( $custom, $email );
                $consent_dx                 = $this->resolve_consent_provenance( $resolved_oid );
                $measurement_consent_dx     = $this->read_consent_value( $custom, array( 'se_google_ad_user_data', 'se_consent_advertising' ) );
                $personalization_consent_dx = $this->read_consent_value( $custom, array( 'se_google_ad_personalization', 'se_google_ad_personalizat', 'se_consent_personalization', 'se_consent_personalizatio' ) );

                $consent_rows = array(
                        array( 'field' => 'canonical_submission_id',               'value' => '' !== $consent_dx['attached_entry_id'] ? $consent_dx['attached_entry_id'] : '(none/non-deterministic)' ),
                        array( 'field' => 'resolved_receipt_id',                   'value' => '' !== $consent_dx['receipt_id'] ? $consent_dx['receipt_id'] : '(none)' ),
                        array( 'field' => 'receipt_id_source',                     'value' => $consent_dx['receipt_id_source'] ),
                        array( 'field' => 'receipt_row_id',                        'value' => $consent_dx['receipt_found'] ? $consent_dx['receipt_row_id'] : '(not found)' ),
                        array( 'field' => 'receipt_policy_version',                'value' => '' !== $consent_dx['policy_version'] ? $consent_dx['policy_version'] : '(none)' ),
                        array( 'field' => 'receipt_banner_version',                'value' => '' !== $consent_dx['banner_version'] ? $consent_dx['banner_version'] : '(none)' ),
                        array( 'field' => 'policy_check',                          'value' => $consent_dx['policy_check'] . ( '' !== $consent_dx['current_policy'] ? ' (current=' . $consent_dx['current_policy'] . ')' : '' ) ),
                        array( 'field' => 'receipt_ad_user_data',                  'value' => '' !== $consent_dx['ad_user_data'] ? $consent_dx['ad_user_data'] : '(unset)' ),
                        array( 'field' => 'receipt_ad_personalization',            'value' => '' !== $consent_dx['ad_personalization'] ? $consent_dx['ad_personalization'] : '(unset)' ),
                        array( 'field' => 'consent_export_decision',               'value' => 'exportable' === $consent_dx['decision'] ? 'OK' : ( 'BLOCK: ' . $consent_dx['block_reason'] ) ),
                        array( 'field' => 'copied_ad_user_data (diagnostic)',      'value' => '' !== $measurement_consent_dx['value'] ? $measurement_consent_dx['value'] . ' [' . $measurement_consent_dx['source'] . ']' : '(unset)' ),
                        array( 'field' => 'copied_ad_personalization (diagnostic)', 'value' => '' !== $personalization_consent_dx['value'] ? $personalization_consent_dx['value'] . ' [' . $personalization_consent_dx['source'] . ']' : '(unset)' ),
                );

                WP_CLI::line( '' );
                WP_CLI::line( 'Consent provenance (read-only; receipt authority + diagnostics):' );
                WP_CLI\Utils\format_items( 'table', $consent_rows, array( 'field', 'value' ) );

                // Read-only Won-row eligibility. No row is built. CRM-knowable gates come
                // from the shared evaluator; gates that depend on modules not yet present
                // (Order ID resolution, consent, hashing) are shown as deferred here.
                $checks = $this->evaluate_won_eligibility( $subscriber, $list_names, $custom, $email, $phone, $config );

                $checks[] = array(
                        'gate'   => 'stable Order ID',
                        'result' => 'deferred',
                        'detail' => 'resolved by conversion builder (form entry/submission/transaction id)',
                );
                $checks[] = array(
                        'gate'   => 'consent eligibility',
                        'result' => 'deferred',
                        'detail' => 'evaluated by the consent reader module',
                );
                $checks[] = array(
                        'gate'   => 'email/phone hashing',
                        'result' => 'deferred',
                        'detail' => 'performed by the conversion builder',
                );

                WP_CLI::line( '' );
                WP_CLI::line( 'Won-row eligibility (read-only; no row built):' );
                WP_CLI\Utils\format_items( 'table', $checks, array( 'gate', 'result', 'detail' ) );

                $blocking = array();
                foreach ( $checks as $check ) {
                        if ( 'fail' === $check['result'] ) {
                                $blocking[] = $check['gate'];
                        }
                }

                WP_CLI::line( '' );

                if ( empty( $blocking ) ) {
                        WP_CLI::success( 'Eligible so far: all CRM-knowable Won-row gates pass. Deferred gates (Order ID, consent, hashing) are evaluated later by the conversion builder.' );
                        return;
                }

                WP_CLI::warning( 'Ineligible for Won-row construction. Failing gate(s): ' . implode( '; ', $blocking ) . '.' );
        }

        /**
         * Build a CA_Won_Job conversion row from CRM state and print it (dry-run).
         *
         * Read-only. Never writes to Google Sheets, never calls Google, never
         * mutates CRM. Reads one contact, re-checks the CRM-knowable Won-row gates,
         * and if eligible assembles the conversion row: normalizes and SHA-256
         * hashes email/phone per Google's Enhanced Conversions for Leads rules,
         * derives the conversion time from closed_date in the configured timezone,
         * and prints every field. Order ID is resolved from form-submission data,
         * which does not exist yet, so it is reported UNRESOLVED rather than guessed.
         *
         * Registered as:
         * wp se-ads conversion-dry-run --id=<contact_id> [--time-of-day=<HH:MM:SS>]
         * (alias: wp se-ads conversion_dry_run)
         *
         * ## OPTIONS
         *
         * --id=<contact_id>
         * : The FluentCRM contact (subscriber) id to build a Won-row for.
         *
         * [--time-of-day=<time>]
         * : Time-of-day (format HH:MM:SS) applied to the date-only closed_date when
         * deriving the conversion timestamp, in the configured timezone. Default:
         * 12:00:00.
         *
         * @subcommand conversion-dry-run
         * @alias conversion_dry_run
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function conversion_dry_run( $args, $assoc_args ) {
                $id_raw = WP_CLI\Utils\get_flag_value( $assoc_args, 'id', '' );

                if ( '' === $id_raw || ! ctype_digit( (string) $id_raw ) ) {
                        WP_CLI::error( 'Provide a numeric FluentCRM contact id, e.g. --id=5.' );
                }

                $id          = (int) $id_raw;
                $time_of_day = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'time-of-day', '12:00:00' );

                if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time_of_day ) ) {
                        WP_CLI::error( 'Invalid --time-of-day; expected HH:MM:SS, e.g. 12:00:00.' );
                }

                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                        WP_CLI::error( 'FluentCRM is not active (Subscriber model not found).' );
                }

                $subscriber = \FluentCrm\App\Models\Subscriber::find( $id );

                if ( ! $subscriber ) {
                        WP_CLI::error( sprintf( 'No FluentCRM contact found with id %d.', $id ) );
                }

                $config = SiteEngine_Ads_Bridge_Config::instance();
                $email  = (string) $subscriber->email;
                $phone  = (string) $subscriber->phone;

                // Tag name set (title + slug) for gate evaluation.
                $tag_names = array();
                foreach ( $subscriber->tags as $tag ) {
                        if ( isset( $tag->title ) ) {
                                $tag_names[] = $tag->title;
                        }
                        if ( isset( $tag->slug ) ) {
                                $tag_names[] = $tag->slug;
                        }
                }

                // List name set (title + slug) for the DNC suppressor gate.
                $list_names = array();
                foreach ( $subscriber->lists as $list ) {
                        if ( isset( $list->title ) ) {
                                $list_names[] = $list->title;
                        }
                        if ( isset( $list->slug ) ) {
                                $list_names[] = $list->slug;
                        }
                }

                $custom = array();
                if ( method_exists( $subscriber, 'custom_fields' ) ) {
                        $custom = (array) $subscriber->custom_fields();
                }

                WP_CLI::line( 'SiteEngine Ads Bridge: Won-row conversion dry-run' );
                WP_CLI::line( '-------------------------------------------------' );
                WP_CLI::line( 'DRY-RUN ONLY. No Sheet write, no Google call, no CRM mutation.' );
                WP_CLI::line( '' );

                // Gate on the same CRM-knowable checks inspect-contact reports.
                $checks   = $this->evaluate_won_eligibility( $subscriber, $list_names, $custom, $email, $phone, $config );
                $blocking = array();
                foreach ( $checks as $check ) {
                        if ( 'fail' === $check['result'] ) {
                                $blocking[] = $check['gate'];
                        }
                }

                WP_CLI\Utils\format_items( 'table', $checks, array( 'gate', 'result', 'detail' ) );
                WP_CLI::line( '' );

                if ( ! empty( $blocking ) ) {
                        WP_CLI::warning( 'Ineligible: ' . implode( '; ', $blocking ) . '. No row built.' );
                        return;
                }

                // --- Assemble the row from verified rules and config ---

                // Conversion time: closed_date (date only) + time-of-day in the configured
                // timezone, formatted with a numeric UTC offset (DST-aware).
                $tz_string = (string) $config->get( 'timezone', 'UTC' );

                try {
                        $tz = new \DateTimeZone( $tz_string );
                } catch ( \Exception $e ) {
                        WP_CLI::error( sprintf( 'Configured timezone "%s" is invalid: %s', $tz_string, $e->getMessage() ) );
                }

                $closed_raw = isset( $custom['closed_date'] ) ? (string) $custom['closed_date'] : '';
                if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})/', $closed_raw, $m ) ) {
                        WP_CLI::error( sprintf( 'closed_date "%s" is not a parseable Y-m-d date; cannot derive conversion time.', $closed_raw ) );
                }
                $closed_date = $m[1];

                $dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', $closed_date . ' ' . $time_of_day, $tz );
                if ( false === $dt ) {
                        WP_CLI::error( sprintf( 'Could not build a datetime from closed_date %s and time %s.', $closed_date, $time_of_day ) );
                }
                $conversion_time = $dt->format( 'Y-m-d H:i:sP' );

                // Conversion value: invoiced_amount, formatted to 2 decimals.
                $invoiced       = (float) $custom['invoiced_amount'];
                $conversion_val = number_format( $invoiced, 2, '.', '' );

                // Hashed identifiers (verified normalization rules; lowercase hex SHA-256).
                $norm_email   = $this->normalize_email( $email );
                $hashed_email = '' !== $norm_email ? hash( 'sha256', $norm_email ) : '';

                $norm_phone   = $this->normalize_phone( $phone );
                $hashed_phone = '' !== $norm_phone ? hash( 'sha256', $norm_phone ) : '';

                // Acquisition / linkage identifiers (legacy integration fields). Read as
                // diagnostics where present — NOT part of the six canonical Ads Bridge
                // fields. gbraid may not exist on this install; absent/empty -> (none).
                $gclid         = isset( $custom['gclid'] ) ? trim( (string) $custom['gclid'] ) : '';
                $gbraid        = isset( $custom['gbraid'] ) ? trim( (string) $custom['gbraid'] ) : '';
                $submission_id = isset( $custom['submission_id'] ) ? trim( (string) $custom['submission_id'] ) : '';

                // Resolve a stable Order ID (read-only). Primary path: the contact's stored
                // submission_id looked up by PK in the canonical (non-draft) Fluent Forms
                // submissions table -> ff:{form_id}:{entry_id}. Fallback (only when stored
                // submission_id is missing): a unique email match. Draft and transaction
                // tables are never used to derive the Order ID; transaction_id is carried
                // separately for correlation only. The resolver mutates nothing.
                $resolved = is_array( $inquiry ) ? SiteEngine_Ads_Bridge_Inquiries::resolver_for_inquiry( $inquiry ) : $this->resolve_won_order_id( $custom, $email );
                $order_id = (string) $resolved['order_id'];

                $conversion_name = (string) $config->get( 'conversions.won_job.action_name', '' );
                $currency        = (string) $config->get( 'currency', '' );

                // Row in the contract's field order.
                $row = array(
                        array( 'field' => 'Order ID',            'value' => '' !== $order_id ? $order_id : '(UNRESOLVED)' ),
                        array( 'field' => 'Conversion Name',     'value' => '' !== $conversion_name ? $conversion_name : '(not configured)' ),
                        array( 'field' => 'Conversion Time',     'value' => $conversion_time ),
                        array( 'field' => 'Conversion Value',    'value' => $conversion_val ),
                        array( 'field' => 'Conversion Currency', 'value' => '' !== $currency ? $currency : '(not configured)' ),
                        array( 'field' => 'Hashed Email',        'value' => '' !== $hashed_email ? $hashed_email : '(none)' ),
                        array( 'field' => 'Hashed Phone',        'value' => '' !== $hashed_phone ? $hashed_phone : '(none)' ),
                        array( 'field' => 'GCLID',               'value' => '' !== $gclid ? $gclid : '(none)' ),
                        array( 'field' => 'GBRAID',              'value' => '' !== $gbraid ? $gbraid : '(none)' ),
                );

                WP_CLI::line( 'CA_Won_Job row (dry-run):' );
                WP_CLI\Utils\format_items( 'table', $row, array( 'field', 'value' ) );

                // Transparency: show how the hashable inputs were normalized.
                WP_CLI::line( '' );
                WP_CLI::line( 'Normalization detail:' );
                WP_CLI::line( '  email: ' . ( '' !== $email ? $email . ' -> ' . $norm_email : '(none)' ) );
                if ( '' !== $phone ) {
                        WP_CLI::line( '  phone: ' . $phone . ' -> ' . ( '' !== $norm_phone ? $norm_phone : '(not E.164; skipped)' ) );
                } else {
                        WP_CLI::line( '  phone: (none)' );
                }
                WP_CLI::line( '  conversion time derived from closed_date ' . $closed_date . ' @ ' . $time_of_day . ' ' . $tz_string );

                // Acquisition / linkage diagnostics. GCLID/GBRAID are included in the row
                // above when present; user-data-only rows remain valid, so emitting (none)
                // is expected for CRM-only contacts and does not suppress hashed identifiers.
                WP_CLI::line( '' );
                WP_CLI::line( 'Acquisition / linkage diagnostics:' );
                WP_CLI::line( '  gclid:         ' . ( '' !== $gclid ? $gclid : '(none)' ) );
                WP_CLI::line( '  gbraid:        ' . ( '' !== $gbraid ? $gbraid : '(none)' ) );
                if ( '' !== $submission_id ) {
                        WP_CLI::line( '  submission_id: ' . $submission_id . ' (stored on contact)' );
                } else {
                        WP_CLI::line( '  submission_id: (none)' );
                }
                WP_CLI::line( '  order_id source: ' . $resolved['source'] . ( $resolved['deterministic'] ? ' (deterministic)' : ' (non-deterministic)' ) );
                if ( '' !== (string) $resolved['form_id'] || '' !== (string) $resolved['entry_id'] ) {
                        WP_CLI::line( '  resolved entry: table=' . ( '' !== (string) $resolved['submissions_table'] ? $resolved['submissions_table'] : '(n/a)' ) . ', form_id=' . ( '' !== (string) $resolved['form_id'] ? $resolved['form_id'] : '(n/a)' ) . ', entry_id=' . ( '' !== (string) $resolved['entry_id'] ? $resolved['entry_id'] : '(n/a)' ) );
                }
                WP_CLI::line( '  transaction_id: ' . ( '' !== (string) $resolved['transaction_id'] ? $resolved['transaction_id'] . ' (correlation only; NOT part of Order ID)' : '(none)' ) );

                // Flags the operator must resolve before any live import.
                WP_CLI::line( '' );
                if ( ! $resolved['exportable'] ) {
                        WP_CLI::warning( 'Order ID UNRESOLVED: ' . $resolved['reason'] . ' Without a stable Order ID, re-uploads risk duplicate leads.' );
                } elseif ( ! $resolved['deterministic'] ) {
                        WP_CLI::warning( 'Order ID is fallback-derived (' . $resolved['reason'] . ') — a single email match today may not stay unique. Prefer storing submission_id on the contact at submission time.' );
                }
                if ( '' !== $phone && '' === $norm_phone ) {
                        WP_CLI::warning( 'Phone present but not in E.164 (no leading "+"/country code); skipped from hashing rather than guessing a country code.' );
                }
                WP_CLI::warning( sprintf( 'Conversion time uses config timezone %s. Confirm this equals your Google Ads account timezone before live import (WordPress site timezone is independent of this).', $tz_string ) );

                // Exportability verdict, driven by the Order ID resolver. A row is only
                // exportable once a stable Order ID is resolved. This remains a dry-run
                // regardless; nothing is written either way.
                WP_CLI::line( '' );
                if ( $resolved['exportable'] ) {
                        WP_CLI::line( 'Exportable: YES  (Order ID ' . $order_id . ' via ' . $resolved['source'] . ( $resolved['deterministic'] ? ', deterministic' : ', fallback-derived/non-deterministic' ) . ') — dry-run only; nothing written.' );
                } else {
                        WP_CLI::line( 'Exportable: NO  (' . $resolved['reason'] . ')' );
                }

                WP_CLI::success( 'Dry-run complete. Row assembled in memory only; nothing was written.' );
        }

        /**
         * Discover any observable linkage between a FluentCRM contact and Fluent
         * Forms submission/payment storage (read-only).
         *
         * Pure discovery. It does NOT build a conversion row, does NOT resolve an
         * Order ID, and writes nothing. It inspects raw storage with SELECT / SHOW
         * COLUMNS only and intentionally uses $wpdb rather than Fluent Forms models,
         * so it makes no assumptions about model namespaces or methods that vary by
         * version. It discovers which Fluent Forms tables exist on this install,
         * looks for entries that plausibly belong to the contact (linked WordPress
         * user id, exact email, normalized phone), and reports whether any column
         * could serve as a stable Order ID. Use the output to decide what the master
         * form must capture going forward.
         *
         * Registered as:
         * wp se-ads inspect-contact-submissions --id=<contact_id>
         * (alias: wp se-ads inspect_contact_submissions)
         *
         * ## OPTIONS
         *
         * --id=<contact_id>
         * : The FluentCRM contact (subscriber) id to inspect linkage for.
         *
         * @subcommand inspect-contact-submissions
         * @alias inspect_contact_submissions
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function inspect_contact_submissions( $args, $assoc_args ) {
                global $wpdb;

                $id_raw = WP_CLI\Utils\get_flag_value( $assoc_args, 'id', '' );

                if ( '' === $id_raw || ! ctype_digit( (string) $id_raw ) ) {
                        WP_CLI::error( 'Provide a numeric FluentCRM contact id, e.g. --id=5.' );
                }

                $id = (int) $id_raw;

                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                        WP_CLI::error( 'FluentCRM is not active (Subscriber model not found).' );
                }

                $subscriber = \FluentCrm\App\Models\Subscriber::find( $id );

                if ( ! $subscriber ) {
                        WP_CLI::error( sprintf( 'No FluentCRM contact found with id %d.', $id ) );
                }

                $email   = (string) $subscriber->email;
                $phone   = (string) $subscriber->phone;
                $full    = trim( (string) $subscriber->full_name );
                $user_id = (int) $subscriber->user_id; // Null/absent attribute casts to 0.

                WP_CLI::line( 'SiteEngine Ads Bridge: contact <-> Fluent Forms linkage discovery (read-only)' );
                WP_CLI::line( '---------------------------------------------------------------------------' );
                WP_CLI::line( 'DISCOVERY ONLY. No row built, no Order ID resolved, nothing written.' );
                WP_CLI::line( '' );

                // Contact identity + export/acquisition diagnostics.
                $custom = array();
                if ( method_exists( $subscriber, 'custom_fields' ) ) {
                        $custom = (array) $subscriber->custom_fields();
                }

                $norm_phone = $this->normalize_phone( $phone );

                $identity = array(
                        array( 'field' => 'contact_id', 'value' => (string) $subscriber->id ),
                        array( 'field' => 'email',      'value' => '' !== $email ? $email : '(none)' ),
                        array( 'field' => 'phone',      'value' => '' !== $phone ? $phone : '(none)' ),
                        array( 'field' => 'phone_e164', 'value' => '' !== $norm_phone ? $norm_phone : '(not E.164/none)' ),
                        array( 'field' => 'full_name',  'value' => '' !== $full ? $full : '(none)' ),
                        array( 'field' => 'wp_user_id', 'value' => $user_id > 0 ? (string) $user_id : '(none)' ),
                        array( 'field' => 'status',     'value' => (string) $subscriber->status ),
                );
                WP_CLI\Utils\format_items( 'table', $identity, array( 'field', 'value' ) );

                $diag_slugs = array( 'invoiced_amount', 'closed_date', 'ads_export_status', 'ads_exported_at', 'ads_conversion_value_frozen', 'ads_export_error', 'gclid', 'gbraid', 'submission_id' );
                $diag_rows  = array();
                foreach ( $diag_slugs as $slug ) {
                        $present     = array_key_exists( $slug, $custom ) && null !== $custom[ $slug ] && '' !== $custom[ $slug ];
                        $val         = $present ? ( is_array( $custom[ $slug ] ) ? implode( '|', $custom[ $slug ] ) : (string) $custom[ $slug ] ) : '(not set)';
                        $diag_rows[] = array( 'field' => $slug, 'value' => $val );
                }
                WP_CLI::line( '' );
                WP_CLI::line( 'Conversion / export + acquisition fields:' );
                WP_CLI\Utils\format_items( 'table', $diag_rows, array( 'field', 'value' ) );

                // Stored Order ID resolution preview (read-only; the same resolver the Won
                // dry-run uses). Independent of Won-eligibility gates, so linkage can be
                // proven on any contact.
                $resolved = $this->resolve_won_order_id( $custom, $email );
                WP_CLI::line( '' );
                WP_CLI::line( 'Stored Order ID resolution (resolver preview):' );
                WP_CLI::line( '  order_id:       ' . ( '' !== (string) $resolved['order_id'] ? $resolved['order_id'] : '(unresolved)' ) );
                WP_CLI::line( '  source:         ' . $resolved['source'] . ( $resolved['deterministic'] ? ' (deterministic)' : ' (non-deterministic)' ) );
                WP_CLI::line( '  exportable:     ' . ( $resolved['exportable'] ? 'YES' : 'NO' ) );
                if ( '' !== (string) $resolved['reason'] ) {
                        WP_CLI::line( '  reason:         ' . $resolved['reason'] );
                }
                WP_CLI::line( '  resolved via:   table=' . ( '' !== (string) $resolved['submissions_table'] ? $resolved['submissions_table'] : '(n/a)' ) . ', form_id=' . ( '' !== (string) $resolved['form_id'] ? $resolved['form_id'] : '(n/a)' ) . ', entry_id=' . ( '' !== (string) $resolved['entry_id'] ? $resolved['entry_id'] : '(n/a)' ) );
                WP_CLI::line( '  transaction_id: ' . ( '' !== (string) $resolved['transaction_id'] ? $resolved['transaction_id'] . ' (correlation only)' : '(none)' ) );

                // Fluent Forms table discovery (core uses the fluentform_ prefix; some
                // addons use ff_). Discover both, never assume specific table names.
                $prefix   = $wpdb->prefix;
                $patterns = array( $wpdb->esc_like( $prefix . 'fluentform' ) . '%', $wpdb->esc_like( $prefix . 'ff_' ) . '%' );

                $tables = array();
                foreach ( $patterns as $pat ) {
                        $found = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pat ) );
                        if ( is_array( $found ) ) {
                                $tables = array_merge( $tables, $found );
                        }
                }
                $tables = array_values( array_unique( $tables ) );
                sort( $tables );

                WP_CLI::line( '' );
                WP_CLI::line( 'Fluent Forms storage discovered (prefix ' . $prefix . '):' );

                if ( empty( $tables ) ) {
                        WP_CLI::warning( 'No Fluent Forms tables found. Fluent Forms may be inactive or uses an unexpected prefix. Linkage cannot be assessed.' );
                        WP_CLI::line( '' );
                        WP_CLI::line( 'Resolver recommendation: none possible — no Fluent Forms storage present.' );
                        return;
                }

                $tbl_rows = array();
                foreach ( $tables as $t ) {
                        $tbl_rows[] = array( 'table' => $t, 'role' => $this->classify_ff_table( $t ) );
                }
                WP_CLI\Utils\format_items( 'table', $tbl_rows, array( 'table', 'role' ) );

                // Pick the primary submissions table. Prefer the canonical non-draft
                // submissions table; fall back to a draft submissions table only if no
                // canonical one exists. (Draft entries are not a resolver source.)
                $submissions_table = '';
                foreach ( $tbl_rows as $r ) {
                        if ( 'submissions' === $r['role'] && false === strpos( strtolower( $r['table'] ), 'draft' ) ) {
                                $submissions_table = $r['table'];
                                break;
                        }
                }
                if ( '' === $submissions_table ) {
                        foreach ( $tbl_rows as $r ) {
                                if ( 'submissions' === $r['role'] ) {
                                        $submissions_table = $r['table'];
                                        break;
                                }
                        }
                }

                if ( '' === $submissions_table ) {
                        WP_CLI::line( '' );
                        WP_CLI::warning( 'No submissions table identified among discovered tables.' );
                        WP_CLI::line( 'Resolver recommendation: none found; master form must capture a canonical submission identifier going forward.' );
                        return;
                }

                $cols = $this->table_columns( $submissions_table );

                if ( ! in_array( 'id', $cols, true ) ) {
                        WP_CLI::line( '' );
                        WP_CLI::warning( sprintf( 'Submissions table %s has no `id` primary key column; cannot key a stable Order ID off it.', $submissions_table ) );
                        WP_CLI::line( 'Resolver recommendation: master form must capture a canonical submission identifier going forward.' );
                        return;
                }

                $has = function ( $c ) use ( $cols ) {
                        return in_array( $c, $cols, true );
                };

                // Defensive SELECT: only columns that exist, all from a fixed allowlist.
                $wanted      = array( 'id', 'form_id', 'serial_number', 'status', 'created_at', 'updated_at', 'source_url', 'user_id', 'payment_status', 'payment_total', 'payment_method', 'currency', 'response' );
                $select_cols = array();
                foreach ( $wanted as $c ) {
                        if ( $has( $c ) ) {
                                $select_cols[] = $c;
                        }
                }
                $select_sql = '`' . implode( '`,`', $select_cols ) . '`';

                // Direct candidate matches against the contact.
                $matches  = array();
                $match_by = array();

                // (a) Linked WordPress user id — deterministic when both sides have it.
                if ( $user_id > 0 && $has( 'user_id' ) ) {
                        $rows = $wpdb->get_results(
                                $wpdb->prepare( "SELECT {$select_sql} FROM `{$submissions_table}` WHERE user_id = %d ORDER BY id DESC LIMIT 25", $user_id ),
                                ARRAY_A
                        );
                        foreach ( (array) $rows as $row ) {
                                $matches[ $row['id'] ]  = $row;
                                $match_by[ $row['id'] ] = isset( $match_by[ $row['id'] ] ) ? $match_by[ $row['id'] ] . ',user_id' : 'user_id';
                        }
                }

                // (b) Email present in the response JSON (heuristic; response is JSON text).
                if ( '' !== $email && $has( 'response' ) ) {
                        $like = '%' . $wpdb->esc_like( $email ) . '%';
                        $rows = $wpdb->get_results(
                                $wpdb->prepare( "SELECT {$select_sql} FROM `{$submissions_table}` WHERE response LIKE %s ORDER BY id DESC LIMIT 25", $like ),
                                ARRAY_A
                        );
                        foreach ( (array) $rows as $row ) {
                                $matches[ $row['id'] ]  = $row;
                                $match_by[ $row['id'] ] = isset( $match_by[ $row['id'] ] ) ? $match_by[ $row['id'] ] . ',email' : 'email';
                        }
                }

                // (c) Normalized phone present in the response JSON (heuristic).
                if ( '' !== $norm_phone && $has( 'response' ) ) {
                        $like = '%' . $wpdb->esc_like( $norm_phone ) . '%';
                        $rows = $wpdb->get_results(
                                $wpdb->prepare( "SELECT {$select_sql} FROM `{$submissions_table}` WHERE response LIKE %s ORDER BY id DESC LIMIT 25", $like ),
                                ARRAY_A
                        );
                        foreach ( (array) $rows as $row ) {
                                $matches[ $row['id'] ]  = $row;
                                $match_by[ $row['id'] ] = isset( $match_by[ $row['id'] ] ) ? $match_by[ $row['id'] ] . ',phone' : 'phone';
                        }
                }

                WP_CLI::line( '' );
                WP_CLI::line( 'Candidate submissions for this contact (table: ' . $submissions_table . '):' );

                if ( empty( $matches ) ) {
                        WP_CLI::warning( 'No submissions matched this contact by user_id, email, or normalized phone.' );
                        WP_CLI::line( '' );
                        WP_CLI::line( 'Resolver recommendation: none found for this contact. Either it never came through a tracked form, or no contact-resolvable identifier is persisted. The master form must capture a canonical submission identifier (and store it onto the contact) going forward.' );
                        return;
                }

                $cand_rows = array();
                foreach ( $matches as $eid => $row ) {
                        $cand_rows[] = array(
                                'entry_id'   => (string) ( isset( $row['id'] ) ? $row['id'] : '(?)' ),
                                'form_id'    => isset( $row['form_id'] ) ? (string) $row['form_id'] : '(n/a)',
                                'created_at' => isset( $row['created_at'] ) ? (string) $row['created_at'] : '(n/a)',
                                'status'     => isset( $row['status'] ) ? (string) $row['status'] : '(n/a)',
                                'matched_by' => $match_by[ $eid ],
                                'pay_status' => isset( $row['payment_status'] ) ? (string) $row['payment_status'] : '(n/a)',
                        );
                }
                WP_CLI\Utils\format_items( 'table', $cand_rows, array( 'entry_id', 'form_id', 'created_at', 'status', 'matched_by', 'pay_status' ) );

                // Payment/transaction linkage discovery, if such a table exists.

                $txn_table = '';
                foreach ( $tbl_rows as $r ) {
                        if ( 'transactions' === $r['role'] ) {
                                $txn_table = $r['table'];
                                break;
                        }
                }

                $txn_note = 'no transactions table discovered';
                if ( '' !== $txn_table ) {
                        $txn_cols = $this->table_columns( $txn_table );
                        $fk       = '';
                        foreach ( array( 'submission_id', 'response_id', 'entry_id' ) as $cand ) {
                                if ( in_array( $cand, $txn_cols, true ) ) {
                                        $fk = $cand;
                                        break;
                                }
                        }
                        $has_txn_id = in_array( 'transaction_id', $txn_cols, true );

                        if ( '' !== $fk ) {
                                $ids = array_map( 'intval', array_keys( $matches ) );
                                $in  = implode( ',', $ids ); // intval-only -> safe to interpolate.
                                $sel = "`{$fk}` AS fk" . ( $has_txn_id ? ", `transaction_id`" : '' );
                                $txn_rows = $wpdb->get_results( "SELECT {$sel} FROM `{$txn_table}` WHERE `{$fk}` IN ({$in}) LIMIT 50", ARRAY_A );
                                if ( ! empty( $txn_rows ) ) {
                                        $txn_note = sprintf( '%d row(s) in %s linked via %s%s', count( $txn_rows ), $txn_table, $fk, $has_txn_id ? ' (transaction_id present)' : '' );
                                } else {
                                        $txn_note = sprintf( 'table %s present (FK %s) but no rows for these entries', $txn_table, $fk );
                                }
                        } else {
                                $txn_note = sprintf( 'table %s present but no recognizable submission FK column', $txn_table );
                        }
                }

                WP_CLI::line( '' );
                WP_CLI::line( 'Payment/transaction linkage: ' . $txn_note );

                // Resolver recommendation.
                $deterministic = false;
                foreach ( $match_by as $by ) {
                        if ( false !== strpos( $by, 'user_id' ) ) {
                                $deterministic = true;
                                break;
                        }
                }

                $email_matches = 0;
                foreach ( $match_by as $by ) {
                        if ( false !== strpos( $by, 'email' ) ) {
                                $email_matches++;
                        }
                }
                $unique_email = ( '' !== $email && 1 === $email_matches );

                WP_CLI::line( '' );
                WP_CLI::line( 'Resolver recommendation:' );
                if ( $deterministic ) {
                        WP_CLI::line( '  Prefer fluentforms_entry_id (' . $submissions_table . '.id), keyed by linked wp_user_id (deterministic).' );
                } elseif ( $unique_email ) {
                        WP_CLI::line( '  Candidate fluentforms_entry_id (' . $submissions_table . '.id) via a unique email match. Verify email uniqueness per form before trusting; ideally store the entry id onto the contact at submission time to make it deterministic.' );
                } else {
                        WP_CLI::line( '  Matches exist but none are deterministic (multiple email matches and/or no user_id link). Recommend the master form explicitly write its Fluent Forms entry id onto the contact (e.g. into submission_id) at submission time, giving the resolver a 1:1 key.' );
                }
                if ( '' !== $txn_table && false !== strpos( $txn_note, 'transaction_id present' ) ) {
                        WP_CLI::line( '  transaction_id is available as a secondary/dedup key for paid entries.' );
                }

                WP_CLI::success( 'Linkage discovery complete (read-only). Use this to set master-form requirements before building the resolver.' );
        }

        /**
         * Best-effort role label for a discovered Fluent Forms table, by name only.
         * Advisory labelling for the discovery report; never used to gate logic.
         *
         * @param string $name Table name.
         * @return string
         */
        private function classify_ff_table( $name ) {
                $n = strtolower( $name );
                if ( false !== strpos( $n, 'submission_meta' ) || false !== strpos( $n, 'entry_meta' ) ) {
                        return 'submission_meta';
                }
                if ( false !== strpos( $n, 'submission' ) || preg_match( '/_entries$/', $n ) ) {
                        return 'submissions';
                }
                if ( false !== strpos( $n, 'form_meta' ) ) {
                        return 'form_meta';
                }
                if ( preg_match( '/_forms$/', $n ) ) {
                        return 'forms';
                }
                if ( false !== strpos( $n, 'transaction' ) || false !== strpos( $n, 'order' ) || false !== strpos( $n, 'payment' ) ) {
                        return 'transactions';
                }
                if ( false !== strpos( $n, 'subscription' ) ) {
                        return 'subscriptions';
                }
                if ( false !== strpos( $n, 'log' ) ) {
                        return 'logs';
                }
                return 'other';
        }

        /**
         * Return a table's column names (read-only). Validates the table name against
         * a strict allowlist pattern before use, even though it originates from a
         * SHOW TABLES result.
         *
         * @param string $table Table name.
         * @return array
         */
        private function table_columns( $table ) {
                global $wpdb;
                if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
                        return array();
                }
                $cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
                return is_array( $cols ) ? $cols : array();
        }

        /**
         * Locate the canonical (non-draft) Fluent Forms submissions table. Prefers a
         * submissions table whose name has no 'draft'; falls back to a draft table
         * only if no canonical one exists. Read-only; returns '' if none found.
         *
         * @return string Table name, or '' if none.
         */
        private function find_canonical_submissions_table() {
                global $wpdb;

                $prefix   = $wpdb->prefix;
                $patterns = array( $wpdb->esc_like( $prefix . 'fluentform' ) . '%', $wpdb->esc_like( $prefix . 'ff_' ) . '%' );

                $tables = array();
                foreach ( $patterns as $pat ) {
                        $found = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pat ) );
                        if ( is_array( $found ) ) {
                                $tables = array_merge( $tables, $found );
                        }
                }
                $tables = array_values( array_unique( $tables ) );
                sort( $tables );

                $fallback = '';
                foreach ( $tables as $t ) {
                        if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $t ) ) {
                                continue;
                        }
                        if ( 'submissions' !== $this->classify_ff_table( $t ) ) {
                                continue;
                        }
                        if ( false === strpos( strtolower( $t ), 'draft' ) ) {
                                return $t;
                        }
                        if ( '' === $fallback ) {
                                $fallback = $t;
                        }
                }

                return $fallback;
        }

        /**
         * Resolve a stable Won-row Order ID for a contact (read-only; no mutation).
         *
         * Primary: the contact's stored submission_id, looked up by primary key in the
         * canonical (non-draft) Fluent Forms submissions table. On a hit the Order ID
         * is ff:{form_id}:{entry_id} and is marked deterministic. A present-but-invalid
         * stored submission_id (non-numeric or not found) is treated as a data error
         * and does NOT fall through to email matching — a stored id signals intended
         * linkage. Fallback (only when no submission_id is stored): a unique email
         * match in the same canonical table yields a fallback-derived, non-deterministic
         * Order ID; zero or multiple matches resolve to nothing. Draft and transaction
         * tables are never used to derive the Order ID. transaction_id, when found, is
         * returned separately for GTM/browser correlation only.
         *
         * @param array  $custom Contact custom field slug => value map.
         * @param string $email  Contact email (used only for the fallback path).
         * @return array{order_id:string,source:string,deterministic:bool,exportable:bool,reason:string,form_id:string,entry_id:string,submissions_table:string,transaction_id:string}
         */
        private function resolve_won_order_id( $custom, $email ) {
                global $wpdb;

                $result = array(
                        'order_id'          => '',
                        'source'            => 'none',
                        'deterministic'     => false,
                        'exportable'        => false,
                        'reason'            => '',
                        'form_id'           => '',
                        'entry_id'          => '',
                        'submissions_table' => '',
                        'transaction_id'    => '',
                );

                $configured_form_id = (int) SiteEngine_Ads_Bridge_Config::instance()->get( 'fluent_forms.form_id', 0 );
                if ( $configured_form_id < 1 ) {
                        $result['reason'] = 'configured fluent_forms.form_id is not a positive form ID.';
                        return $result;
                }

                $table = $this->find_canonical_submissions_table();
                if ( '' === $table || ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
                        $result['reason'] = 'no canonical Fluent Forms submissions table found.';
                        return $result;
                }
                $result['submissions_table'] = $table;

                $cols = $this->table_columns( $table );
                if ( ! in_array( 'id', $cols, true ) || ! in_array( 'form_id', $cols, true ) ) {
                        $result['reason'] = sprintf( 'submissions table %s lacks id/form_id columns; cannot key an Order ID.', $table );
                        return $result;
                }

                $stored = isset( $custom['submission_id'] ) ? trim( (string) $custom['submission_id'] ) : '';

                // PRIMARY: stored submission_id (the Fluent Forms entry id / PK).
                if ( '' !== $stored ) {
                        if ( ! ctype_digit( $stored ) ) {
                                $result['source'] = 'stored_submission_id';
                                $result['reason'] = sprintf( 'stored submission_id "%s" is not numeric; not promoting, and not falling back (a stored id signals intended linkage).', $stored );
                                return $result;
                        }

                        $row = $wpdb->get_row(
                                $wpdb->prepare( "SELECT id, form_id FROM `{$table}` WHERE id = %d", (int) $stored ),
                                ARRAY_A
                        );

                        if ( ! $row ) {
                                $result['source'] = 'stored_submission_id';
                                $result['reason'] = sprintf( 'stored submission_id %d not found in canonical submissions table %s; not falling back (a stored id signals intended linkage).', (int) $stored, $table );
                                return $result;
                        }

                        $result['form_id']  = (string) $row['form_id'];
                        $result['entry_id'] = (string) $row['id'];
                        $result['source']   = 'stored_submission_id';
                        if ( (int) $row['form_id'] !== $configured_form_id ) {
                                $result['reason'] = sprintf(
                                        'stored submission_id %d belongs to form %d, not configured master form %d.',
                                        (int) $row['id'],
                                        (int) $row['form_id'],
                                        $configured_form_id
                                );
                                return $result;
                        }
                        $result['order_id']       = 'ff:' . $row['form_id'] . ':' . $row['id'];
                        $result['deterministic']  = true;
                        $result['exportable']     = true;
                        $result['transaction_id'] = $this->find_transaction_id_for_entry( (int) $row['id'] );
                        return $result;
                }

                // FALLBACK (only when no submission_id is stored): unique email match.
                if ( '' === $email ) {
                        $result['reason'] = 'no stored submission_id and no email available for fallback resolution.';
                        return $result;
                }
                if ( ! in_array( 'response', $cols, true ) ) {
                        $result['reason'] = sprintf( 'no stored submission_id and submissions table %s has no response column to email-match against.', $table );
                        return $result;
                }

                $like = '%' . $wpdb->esc_like( $email ) . '%';
                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT id, form_id FROM `{$table}` WHERE form_id = %d AND response LIKE %s ORDER BY id DESC LIMIT 25",
                                $configured_form_id,
                                $like
                        ),
                        ARRAY_A
                );
                $rows = is_array( $rows ) ? $rows : array();

                if ( 1 !== count( $rows ) ) {
                        $result['source'] = 'fallback_email';
                        $result['reason'] = sprintf( 'fallback email match returned %d canonical submission(s); need exactly 1.', count( $rows ) );
                        return $result;
                }

                $row                      = $rows[0];
                $result['form_id']        = (string) $row['form_id'];
                $result['entry_id']       = (string) $row['id'];
                $result['order_id']       = 'ff:' . $row['form_id'] . ':' . $row['id'];
                $result['source']         = 'fallback_email';
                $result['deterministic']  = false;
                $result['exportable']     = true;
                $result['reason']         = 'fallback-derived from a single email match (not deterministic).';
                $result['transaction_id'] = $this->find_transaction_id_for_entry( (int) $row['id'] );
                return $result;
        }

        /**
         * Find a transaction_id linked to a Fluent Forms entry, for GTM/browser
         * correlation ONLY (read-only). Never used to derive or gate the Order ID.
         * Returns '' if no transactions table, FK, or matching row is found.
         *
         * @param int $entry_id Fluent Forms submissions.id.
         * @return string transaction_id, or ''.
         */
        private function find_transaction_id_for_entry( $entry_id ) {
                global $wpdb;

                $prefix   = $wpdb->prefix;
                $patterns = array( $wpdb->esc_like( $prefix . 'fluentform' ) . '%', $wpdb->esc_like( $prefix . 'ff_' ) . '%' );

                $tables = array();
                foreach ( $patterns as $pat ) {
                        $found = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pat ) );
                        if ( is_array( $found ) ) {
                                $tables = array_merge( $tables, $found );
                        }
                }

                foreach ( array_unique( $tables ) as $t ) {
                        if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $t ) ) {
                                continue;
                        }
                        if ( 'transactions' !== $this->classify_ff_table( $t ) ) {
                                continue;
                        }
                        $tcols = $this->table_columns( $t );
                        if ( ! in_array( 'transaction_id', $tcols, true ) ) {
                                continue;
                        }
                        $fk = '';
                        foreach ( array( 'submission_id', 'response_id', 'entry_id' ) as $cand ) {
                                if ( in_array( $cand, $tcols, true ) ) {
                                        $fk = $cand;
                                        break;
                                }
                        }
                        if ( '' === $fk ) {
                                continue;
                        }
                        $val = $wpdb->get_var( $wpdb->prepare( "SELECT transaction_id FROM `{$t}` WHERE `{$fk}` = %d LIMIT 1", $entry_id ) );
                        if ( null !== $val && '' !== (string) $val ) {
                                return (string) $val;
                        }
                }

                return '';
        }

        /**
         * Build the exact Google_Ads_Import (Data Manager) row for a contact and print
         * it (append-only dry-run). Writes nothing: no sheet write, no Google upload,
         * no CRM mutation.
         *
         * Emits the destination's confirmed 20-field schema in order. The four hashed
         * fields (Email address, Phone number, First name, Last name) are SHA-256 hex
         * hashed plugin-side by default (so Data Manager must NOT re-hash); set
         * sheets.google_ads_import.plugin_side_sha256 = false in config to emit
         * normalized-but-unhashed values and let Data Manager hash instead. Header
         * labels default to Google's exact names and may be overridden per field via
         * sheets.google_ads_import.headers. "Transaction ID" carries the deterministic
         * resolver Order ID (ff:{form_id}:{entry_id}) — never the form hidden field
         * transaction_id, which stays an internal/GTM correlation value.
         *
         * Export is gated: Exportable: YES requires all CRM Won gates to pass AND a
         * deterministic resolved Order ID. ads_export_status is never read; re-sends
         * are not suppressed (append-only; Google de-duplicates via Transaction ID).
         *
         * Registered as:
         * wp se-ads export-dry-run --id=<contact_id> [--time-of-day=<time>]
         * (alias: wp se-ads export_dry_run)
         *
         * ## OPTIONS
         *
         * --id=<contact_id>
         * : The FluentCRM contact (subscriber) id to build a Google_Ads_Import row for.
         *
         * [--time-of-day=<time>]
         * : Time-of-day (format HH:MM:SS) applied to the date-only closed_date when
         * deriving the conversion timestamp, in the configured timezone. Default:
         * 12:00:00.
         *
         * @subcommand export-dry-run
         * @alias export_dry_run
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function export_dry_run( $args, $assoc_args ) {
                $id_raw = WP_CLI\Utils\get_flag_value( $assoc_args, 'id', '' );

                if ( '' === $id_raw || ! ctype_digit( (string) $id_raw ) ) {
                        WP_CLI::error( 'Provide a numeric FluentCRM contact id, e.g. --id=5.' );
                }

                $id          = (int) $id_raw;
                $time_of_day = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'time-of-day', '12:00:00' );

                if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time_of_day ) ) {
                        WP_CLI::error( sprintf( '--time-of-day "%s" is not a valid HH:MM:SS (00:00:00-23:59:59).', $time_of_day ) );
                }

                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                        WP_CLI::error( 'FluentCRM is not active (Subscriber model not found).' );
                }

                $subscriber = \FluentCrm\App\Models\Subscriber::find( $id );

                if ( ! $subscriber ) {
                        WP_CLI::error( sprintf( 'No FluentCRM contact found with id %d.', $id ) );
                }

                $config = SiteEngine_Ads_Bridge_Config::instance();

                // Assemble the exact Google_Ads_Import row + exportability verdict via the
                // SHARED builder. append-row uses this same method, so the dry-run preview
                // and a real append can never diverge.
                $b = $this->build_google_ads_import( $subscriber, $config, $time_of_day );

                $email                   = $b['email'];
                $resolved                = $b['resolved'];
                $order_id                = $b['order_id'];
                $gate_fail               = $b['gate_fail'];
                $gclid                   = $b['gclid'];
                $out_email               = $b['out_email'];
                $out_phone               = $b['out_phone'];
                $norm_email              = $b['norm_email'];
                $norm_phone              = $b['norm_phone'];
                $norm_first              = $b['norm_first'];
                $norm_last               = $b['norm_last'];
                $plugin_hash             = $b['plugin_hash'];
                $conv_val                = $b['conv_val'];
                $val_src                 = $b['val_src'];
                $conversion_time         = $b['conversion_time'];
                $time_err                = $b['time_err'];
                $tz_string               = $b['tz_string'];
                $state                   = $b['state'];
                $state_norm              = $b['state_norm'];
                $country                 = $b['country'];
                $country_norm            = $b['country_norm'];
                $city                    = $b['city'];
                $postal                  = $b['postal'];
                $measurement_consent     = $b['measurement_consent'];
                $personalization_consent = $b['personalization_consent'];
                $consent                 = $b['consent'];
                $hidden_txn              = $b['hidden_txn'];
                $spec                    = $b['spec'];

                WP_CLI::line( 'SiteEngine Ads Bridge: Google_Ads_Import export dry-run (read-only)' );
                WP_CLI::line( '-----------------------------------------------------------------' );
                WP_CLI::line( 'Mode: DRY-RUN. Append-only. Google upload: DISABLED. Nothing is written.' );
                WP_CLI::line( sprintf( 'Contact #%d  %s', $id, '' !== $email ? $email : '(no email)' ) );
                WP_CLI::line( '' );

                $overrides = (array) $config->get( 'sheets.google_ads_import.headers', array() );

                $rows = array();
                foreach ( $spec as $f ) {
                        $header = ( isset( $overrides[ $f['k'] ] ) && '' !== (string) $overrides[ $f['k'] ] ) ? (string) $overrides[ $f['k'] ] : $f['h'];
                        $rows[] = array(
                                'column' => $header,
                                'value'  => '' === (string) $f['v'] ? '(empty)' : (string) $f['v'],
                        );
                }

                WP_CLI::line( 'Google_Ads_Import row that would be appended:' );
                WP_CLI\Utils\format_items( 'table', $rows, array( 'column', 'value' ) );

                WP_CLI::line( '' );
                WP_CLI::line( 'Field detail:' );
                WP_CLI::line( '  hashing:        plugin-side SHA-256 hex = ' . ( $plugin_hash ? 'ON (Data Manager must NOT re-hash these)' : 'OFF (values normalized but unhashed; Data Manager hashes)' ) );
                WP_CLI::line( '  email:          ' . ( '' !== $norm_email ? $norm_email : '(none/not normalizable)' ) );
                WP_CLI::line( '  phone:          ' . ( '' !== $norm_phone ? $norm_phone : '(none/not E.164)' ) );
                WP_CLI::line( '  first/last:     ' . ( '' !== $norm_first ? $norm_first : '(none)' ) . ' / ' . ( '' !== $norm_last ? $norm_last : '(none)' ) );
                WP_CLI::line( '  conversion val: ' . ( '' !== $conv_val ? $conv_val . ' (from ' . $val_src . ')' : '(none)' ) );
                WP_CLI::line( '  conversion time:' . ( '' !== $conversion_time ? ' ' . $conversion_time . ' [' . $tz_string . ']' : ' (unresolved: ' . $time_err . ')' ) );
                WP_CLI::line( '  Transaction ID: ' . ( '' !== $order_id ? $order_id . ' via ' . $resolved['source'] . ( $resolved['deterministic'] ? ' (deterministic)' : ' (non-deterministic)' ) : '(unresolved: ' . $resolved['reason'] . ')' ) );
                WP_CLI::line( '  state:          raw "' . $state . '" -> exported "' . $state_norm['value'] . '" (' . $state_norm['status'] . ')' );
                WP_CLI::line( '  country:        raw "' . $country . '" -> exported "' . $country_norm['value'] . '" (' . $country_norm['status'] . ')' );

                WP_CLI::line( '' );
                WP_CLI::line( 'Consent provenance (receipt authority — feeds the two consent columns and the export gate):' );
                WP_CLI::line( '  canonical submission: ' . ( '' !== $consent['attached_entry_id'] ? 'entry #' . $consent['attached_entry_id'] . ' (form ' . $consent['attached_form_id'] . ')' : '(none — ' . ( '' !== $consent['block_reason'] ? $consent['block_reason'] : 'not deterministic' ) . ')' ) );
                WP_CLI::line( '  receipt id:           ' . ( '' !== $consent['receipt_id'] ? $consent['receipt_id'] . ' [via ' . $consent['receipt_id_source'] . ']' : '(none)' ) );
                WP_CLI::line( '  receipt row:          ' . ( $consent['receipt_found'] ? '#' . $consent['receipt_row_id'] . ' (policy_version="' . $consent['policy_version'] . '", banner_version="' . $consent['banner_version'] . '")' : '(not found)' ) );
                WP_CLI::line( '  policy check:         ' . $consent['policy_check'] . ( '' !== $consent['current_policy'] ? ' (current="' . $consent['current_policy'] . '")' : ' (no current policy source available)' ) );
                WP_CLI::line( '  ad_user_data:         ' . ( '' !== $consent['ad_user_data'] ? $consent['ad_user_data'] : '(unset)' ) . ' -> Ad measurement consent column = "' . ( '' !== $consent['measurement_feed'] ? $consent['measurement_feed'] : '(blank)' ) . '"' );
                WP_CLI::line( '  ad_personalization:   ' . ( '' !== $consent['ad_personalization'] ? $consent['ad_personalization'] : '(unset)' ) . ' -> Ad personalization consent column = "' . ( '' !== $consent['personalization_feed'] ? $consent['personalization_feed'] : '(blank)' ) . '"' );
                WP_CLI::line( '  consent decision:     ' . ( 'exportable' === $consent['decision'] ? 'OK (ad_user_data granted, receipt valid)' : 'BLOCK — ' . $consent['block_reason'] ) );
                WP_CLI::line( '' );
                WP_CLI::line( 'Consent diagnostics only (loose CRM/form copied fields — NOT used for export):' );
                WP_CLI::line( '    Ad measurement consent     <- ' . ( 'found' === $measurement_consent['status'] ? $measurement_consent['source'] . '="' . $measurement_consent['value'] . '"' : '(empty)' ) . '  [checked: ' . $measurement_consent['detail'] . ']' );
                WP_CLI::line( '    Ad personalization consent <- ' . ( 'found' === $personalization_consent['status'] ? $personalization_consent['source'] . '="' . $personalization_consent['value'] . '"' : '(empty)' ) . '  [checked: ' . $personalization_consent['detail'] . ']' );
                WP_CLI::line( '' );
                WP_CLI::line( 'Pending / not-yet-wired columns:' );
                WP_CLI::line( '  User agent: left empty — no wired source on the contact.' );
                WP_CLI::line( '  Session attributes: left empty — no serialized payload defined.' );
                if ( 'unknown' === $state_norm['status'] ) {
                        WP_CLI::warning( sprintf( 'State/region "%s" not recognized as a US state name or 2-letter code; left unchanged. Verify Data Manager accepts it.', $state ) );
                }
                if ( 'unknown' === $country_norm['status'] ) {
                        WP_CLI::warning( sprintf( 'Country "%s" not recognized; left unchanged. Verify Data Manager accepts it or store a 2-letter code.', $country ) );
                }
                if ( '' !== $city && '' !== $postal && '' !== $state ) {
                        WP_CLI::line( '  Geo note: city/postal/state are not cross-validated (no ZIP-to-state inference). Verify they refer to the same locality; a city/ZIP in one state with a different stored state is a data-entry problem.' );
                }
                if ( '' !== $gclid && ( '' !== $out_email || '' !== $out_phone ) ) {
                        WP_CLI::warning( 'GCLID is present alongside hashed user data; Google may prioritize GCLID and this can cause matching conflicts. If matching issues arise, test an upload omitting GCLID.' );
                }

                $hidden_txn = isset( $custom['transaction_id'] ) ? trim( (string) $custom['transaction_id'] ) : '';
                if ( '' !== $hidden_txn ) {
                        WP_CLI::line( '' );
                        WP_CLI::line( 'Note: contact has a hidden-field transaction_id (' . $hidden_txn . '). It is correlation-only and is NOT written to the Data Manager "Transaction ID" column.' );
                }

                // Exportability reasons come from the SOLE authority (build_google_ads_import),
                // so the per-contact dry-run can never diverge from append-row / export-batch.
                // This now includes the consent provenance gate.
                $reasons = (array) $b['reasons'];

                WP_CLI::line( '' );
                if ( empty( $reasons ) ) {
                        WP_CLI::line( 'Exportable: YES  (deterministic Transaction ID ' . $order_id . ') — would append 1 row to Google_Ads_Import. Append-only: no local dedup, no ads_export_status check; Google de-duplicates via Transaction ID. Google upload DISABLED; nothing written.' );
                } else {
                        WP_CLI::line( 'Exportable: NO  — ' . implode( '; ', $reasons ) . '.' );
                }

                WP_CLI::success( 'Export dry-run complete. No sheet write, no Google upload, no CRM mutation.' );
        }

        /**
         * Append exactly one exportable Google_Ads_Import row for a contact through
         * the ledger-protected export-batch path.
         *
         * This compatibility command validates its arguments, requires explicit
         * --apply, and delegates to export-batch with --ids=<contact_id> and
         * --limit=1. export-batch remains the sole owner of exportability checks,
         * Sheet preflight, append execution, ledger bookkeeping, idempotency, and
         * reporting. This command never uploads to Google Ads or mutates FluentCRM.
         *
         * Registered as:
         * wp se-ads append-row --id=<contact_id> --apply [--time-of-day=<time>]
         * (alias: wp se-ads append_row)
         *
         * ## OPTIONS
         *
         * --id=<contact_id>
         * : The FluentCRM contact (subscriber) id to pass to export-batch.
         *
         * [--apply]
         * : Required. Run the single-contact export through the ledger-protected
         * export-batch apply path.
         *
         * [--time-of-day=<time>]
         * : Time-of-day (HH:MM:SS) applied to the date-only closed_date when deriving
         * the conversion timestamp, in the configured timezone. Default: 12:00:00.
         *
         * @subcommand append-row
         * @alias append_row
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function append_row( $args, $assoc_args ) {
                $id_raw = WP_CLI\Utils\get_flag_value( $assoc_args, 'id', '' );

                if ( '' === $id_raw || ! ctype_digit( (string) $id_raw ) ) {
                        WP_CLI::error( 'Provide a numeric FluentCRM contact id, e.g. --id=11.' );
                }

                $id          = (int) $id_raw;
                $time_of_day = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'time-of-day', '12:00:00' );

                if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time_of_day ) ) {
                        WP_CLI::error( sprintf( '--time-of-day "%s" is not a valid HH:MM:SS (00:00:00-23:59:59).', $time_of_day ) );
                }

                $apply = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );
                if ( ! $apply ) {
                        WP_CLI::error( 'Refusing to append without --apply. This command delegates to the ledger-protected export-batch path.' );
                }

                $this->export_batch(
                        array(),
                        array(
                                'apply'       => true,
                                'ids'         => (string) $id,
                                'limit'       => 1,
                                'time-of-day' => $time_of_day,
                        )
                );
        }
/**
         * Manual batch export of exportable Google_Ads_Import rows to the configured
         * Google Sheet sink, with a plugin-owned exporter ledger for append bookkeeping.
         *
         * Reuses the shared build_google_ads_import() row builder unchanged as the SOLE
         * exportability authority. Candidate selection is a narrow shortlist only; every
         * candidate is passed through the builder, and a row is appended only if the
         * builder returns exportable, exactly 20 values, and a deterministic
         * ff:{form_id}:{entry_id} Transaction ID. NEVER uploads to Google Ads, NEVER
         * mutates FluentCRM, NEVER reads/writes ads_export_status. Append-only; Google
         * de-duplicates via Transaction ID. The ledger is exporter bookkeeping only.
         *
         * Exactly one of --dry-run or --apply is required. --dry-run writes nothing:
         * no Sheet write, no ledger write/creation, no mutation. --apply creates/verifies
         * the ledger table BEFORE any append and fails closed if that or the Sheet config
         * preflight fails. Transaction IDs already recorded as appended to this
         * sink are always skipped; there is no re-append path.
         *
         * Registered as:
         * wp se-ads export-batch (--dry-run|--apply) [--limit=<n>] [--ids=<csv>] [--time-of-day=<time>]
         * (alias: wp se-ads export_batch)
         *
         * ## OPTIONS
         *
         * [--dry-run]
         * : Build and report candidates; write nothing. Mutually exclusive with --apply.
         *
         * [--apply]
         * : Append exportable, non-skipped rows to the Sheet. Mutually exclusive with --dry-run.
         *
         * [--limit=<n>]
         * : Maximum candidates to consider. Default: 10.
         *
         * [--ids=<csv>]
         * : Optional explicit contact-id shortlist (e.g. 10,11). Overrides won-tag discovery.
         *
         * [--time-of-day=<time>]
         * : HH:MM:SS applied to closed_date when deriving the conversion timestamp. Default: 12:00:00.
         *
         * @subcommand export-batch
         * @alias export_batch
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function export_batch( $args, $assoc_args ) {
                $sink = 'google_ads_import_converted_lead';

                $dry   = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
                $apply = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );

                if ( $dry && $apply ) {
                        WP_CLI::error( 'Pass exactly one of --dry-run or --apply, not both.' );
                }
                if ( ! $dry && ! $apply ) {
                        WP_CLI::error( 'Refusing to run with no mode. Pass --dry-run (writes nothing) or --apply (appends exportable rows).' );
                }

                $limit_raw = WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 10 );
                if ( ! ctype_digit( (string) $limit_raw ) || (int) $limit_raw < 1 ) {
                        WP_CLI::error( sprintf( '--limit "%s" must be a positive integer.', (string) $limit_raw ) );
                }
                $limit = (int) $limit_raw;

                $time_of_day = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'time-of-day', '12:00:00' );
                if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time_of_day ) ) {
                        WP_CLI::error( sprintf( '--time-of-day "%s" is not a valid HH:MM:SS (00:00:00-23:59:59).', $time_of_day ) );
                }

                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                        WP_CLI::error( 'FluentCRM is not active (Subscriber model not found).' );
                }

                $config = SiteEngine_Ads_Bridge_Config::instance();

                // --- Candidate shortlist (NOT the exportability authority) ---
                // Inquiry-aware rows are selected first. Legacy contact-scoped rows
                // remain supported for contacts that have never entered inquiry mode.
                $ledger_exists = $this->ledger_table_exists();
                $ids_raw       = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'ids', '' );
                $ids           = array();
                if ( '' !== $ids_raw ) {
                        $ids = array_filter( array_map( 'trim', explode( ',', $ids_raw ) ), 'ctype_digit' );
                        $ids = array_slice( array_values( array_unique( $ids ) ), 0, $limit );
                        $ids = array_map( 'intval', $ids );
                }

                $inquiry_selection = $this->select_unledgered_inquiries( 'won', $sink, $limit, $ledger_exists, $ids, array( 'google_ads_import_sheet' ) );
                $inquiries         = $inquiry_selection['inquiries'];
                $remaining         = max( 0, $limit - count( $inquiries ) );
                $subscribers       = array();
                $legacy_skipped    = 0;

                if ( $remaining > 0 ) {
                        if ( ! empty( $ids ) ) {
                                foreach ( $ids as $cid ) {
                                        if ( class_exists( 'SiteEngine_Ads_Bridge_Inquiries' ) && SiteEngine_Ads_Bridge_Inquiries::contact_has_history( $cid ) ) {
                                                continue;
                                        }
                                        $s = \FluentCrm\App\Models\Subscriber::find( (int) $cid );
                                        if ( $s ) {
                                                $subscribers[] = $s;
                                                if ( count( $subscribers ) >= $remaining ) {
                                                        break;
                                                }
                                        } else {
                                                WP_CLI::warning( sprintf( 'Contact #%d not found; dropped from candidates.', (int) $cid ) );
                                        }
                                }
                        } else {
                                $won_title = (string) $config->get( 'fluentcrm.tags.lifecycle.won', '' );
                                if ( '' === $won_title ) {
                                        WP_CLI::error( 'fluentcrm.tags.lifecycle.won is not configured; cannot select legacy Won candidates.' );
                                }
                                $won_tag_id = $this->resolve_lifecycle_tag_id( $won_title );
                                try {
                                        $model = new \FluentCrm\App\Models\Subscriber();
                                        $table = (string) $model->getTable();
                                        $found = \FluentCrm\App\Models\Subscriber::query()
                                                ->filterByTags( array( $won_tag_id ) )
                                                ->select( $table . '.*' )
                                                ->distinct()
                                                ->orderBy( $table . '.id', 'ASC' )
                                                ->get();
                                } catch ( \Throwable $e ) {
                                        WP_CLI::error( 'Won-tag candidate discovery failed (' . $e->getMessage() . '). Refusing to continue with a duplicate-prone or partial shortlist.' );
                                }
                                $selection     = $this->select_current_unledgered_subscribers( $found, $sink, $remaining, $ledger_exists, array( 'google_ads_import_sheet' ) );
                                $subscribers   = $selection['subscribers'];
                                $legacy_skipped= $selection['ledger_skipped'];
                        }
                }

                $items = array();
                foreach ( $inquiries as $inquiry ) {
                        $items[] = array( 'type' => 'inquiry', 'inquiry' => $inquiry );
                }
                foreach ( $subscribers as $subscriber ) {
                        $items[] = array( 'type' => 'legacy', 'subscriber' => $subscriber );
                }
                $selector = ! empty( $ids )
                        ? sprintf( 'explicit --ids; inquiry-aware=%d, legacy=%d', count( $inquiries ), count( $subscribers ) )
                        : sprintf( 'inquiry-aware Won + legacy Won; ledger prefiltered inquiry=%d legacy=%d', $inquiry_selection['ledger_skipped'], $legacy_skipped );

                WP_CLI::line( 'SiteEngine Ads Bridge: export-batch (' . ( $dry ? 'DRY-RUN' : 'APPLY' ) . ')' );
                WP_CLI::line( '------------------------------------------------------------' );
                WP_CLI::line( 'Sink: ' . $sink . ' (Google_Ads_Import Sheet). Google Ads upload DISABLED. No CRM mutation.' );
                WP_CLI::line( sprintf( 'Candidate selector: %s. Limit: %d.', $selector, $limit ) );
                if ( ! $ledger_exists ) {
                        WP_CLI::line( 'Ledger: table not present yet' . ( $dry ? ' (dry-run will NOT create it).' : ' (will be created before any append).' ) );
                }
                WP_CLI::line( '' );

                $count = array(
                        'candidates'       => count( $items ),
                        'exportable'       => 0,
                        'already_appended' => 0,
                        'appended'         => 0,
                        'failed'           => 0,
                        'not_exportable'   => 0,
                );
                $report    = array();
                $orphans   = array();
                $to_append = array();

                foreach ( $items as $item ) {
                        $inquiry = ( isset( $item['type'] ) && 'inquiry' === $item['type'] && isset( $item['inquiry'] ) && is_array( $item['inquiry'] ) ) ? $item['inquiry'] : null;
                        if ( is_array( $inquiry ) ) {
                                $cid = isset( $inquiry['contact_id'] ) ? (int) $inquiry['contact_id'] : 0;
                                $sub = $cid > 0 ? \FluentCrm\App\Models\Subscriber::find( $cid ) : null;
                                if ( ! $sub ) {
                                        $count['not_exportable']++;
                                        $report[] = array(
                                                'contact_id'     => $cid,
                                                'transaction_id' => isset( $inquiry['transaction_id'] ) ? (string) $inquiry['transaction_id'] : '(none)',
                                                'exportable'     => 'no',
                                                'action'         => 'not_exportable',
                                                'detail'         => 'inquiry-bound FluentCRM contact not found',
                                        );
                                        continue;
                                }
                        } else {
                                $sub = isset( $item['subscriber'] ) ? $item['subscriber'] : null;
                                if ( ! is_object( $sub ) || ! isset( $sub->id ) ) {
                                        continue;
                                }
                                $cid = (int) $sub->id;
                        }

                        try {
                                $b = $this->build_google_ads_import( $sub, $config, $time_of_day, $inquiry );
                        } catch ( \Throwable $e ) {
                                $count['not_exportable']++;
                                $report[] = array(
                                        'contact_id'     => $cid,
                                        'transaction_id' => '(none)',
                                        'exportable'     => 'no',
                                        'action'         => 'not_exportable',
                                        'detail'         => 'builder error: ' . $e->getMessage(),
                                );
                                continue;
                        }

                        $txn        = (string) $b['order_id'];
                        $exportable = (bool) $b['exportable'];
                        $values     = isset( $b['values'] ) ? (array) $b['values'] : array();

                        if ( ! $exportable ) {
                                $count['not_exportable']++;
                                $report[] = array(
                                        'contact_id'     => $cid,
                                        'transaction_id' => '' !== $txn ? $txn : '(none)',
                                        'exportable'     => 'no',
                                        'action'         => 'not_exportable',
                                        'detail'         => implode( '; ', (array) $b['reasons'] ),
                                );
                                continue;
                        }

                        // Mirror append-row's hard append-safety asserts.
                        if ( count( $values ) !== 21 || ! preg_match( '/^ff:\d+:\d+$/', $txn ) || empty( $b['resolved']['deterministic'] ) ) {
                                $count['not_exportable']++;
                                $report[] = array(
                                        'contact_id'     => $cid,
                                        'transaction_id' => '' !== $txn ? $txn : '(none)',
                                        'exportable'     => 'no',
                                        'action'         => 'not_exportable',
                                        'detail'         => 'failed append-safety assert (21 cols / deterministic ff id)',
                                );
                                continue;
                        }

                        $count['exportable']++;
                        $row_hash = hash( 'sha256', implode( "\x1f", array_map( 'strval', $values ) ) );

                        $already = $ledger_exists ? $this->ledger_appended_in_any_sink( array( $sink, 'google_ads_import_sheet' ), $txn ) : false;

                        if ( $already ) {
                                $count['already_appended']++;
                                $report[] = array(
                                        'contact_id'     => $cid,
                                        'transaction_id' => $txn,
                                        'exportable'     => 'yes',
                                        'action'         => 'skipped',
                                        'detail'         => 'already appended to Converted Lead or legacy Won sink (ledger)',
                                );
                                continue;
                        }

                        $to_append[] = array(
                                'cid'      => $cid,
                                'txn'      => $txn,
                                'values'   => $values,
                                'row_hash' => $row_hash,
                        );
                }

                // --- DRY-RUN: report only; create/write nothing ---
                if ( $dry ) {
                        foreach ( $to_append as $item ) {
                                $report[] = array(
                                        'contact_id'     => $item['cid'],
                                        'transaction_id' => $item['txn'],
                                        'exportable'     => 'yes',
                                        'action'         => 'would_append',
                                        'detail'         => 'dry-run: nothing written',
                                );
                        }
                        $this->print_export_batch_summary( $report, $count, count( $to_append ), true );
                        WP_CLI::success( 'Dry-run complete. No Sheet write, no ledger write, no CRM mutation.' );
                        return;
                }

                // --- APPLY ---
                if ( empty( $to_append ) ) {
                        $this->print_export_batch_summary( $report, $count, 0, false );
                        WP_CLI::line( '' );
                        WP_CLI::line( 'Nothing to append (no new exportable candidates). No Sheet write performed.' );
                        WP_CLI::line( 'Google Ads upload DISABLED. No CRM mutation.' );
                        WP_CLI::success( 'export-batch --apply complete; 0 rows appended.' );
                        return;
                }

                // Ledger table first — fail closed before any Sheet write.
                if ( ! $this->ensure_ledger_table() ) {
                        WP_CLI::error( sprintf( 'Could not create/verify ledger table %s; failing closed before any append. Nothing was written.', $this->ledger_table() ) );
                }

                // Sheet-append config preflight — same keys append-row enforces.
                $missing        = array();
                $sheets_enabled = (bool) $config->get( 'sheets.enabled', false );
                $spreadsheet_id = trim( (string) $config->get( 'sheets.spreadsheet_id', '' ) );
                $tab            = trim( (string) $config->get( 'sheets.google_ads_import.tab', '' ) );
                $creds_path     = trim( (string) $config->get( 'sheets.credentials_path', '' ) );

                if ( true !== $sheets_enabled ) {
                        $missing[] = 'sheets.enabled (must be true)';
                }
                if ( '' === $spreadsheet_id ) {
                        $missing[] = 'sheets.spreadsheet_id (target Google Sheet id)';
                }
                if ( '' === $tab ) {
                        $missing[] = 'sheets.google_ads_import.tab (worksheet/tab name)';
                }
                if ( '' === $creds_path ) {
                        $missing[] = 'sheets.credentials_path (absolute path to a Google service-account JSON)';
                } elseif ( ! is_readable( $creds_path ) ) {
                        $missing[] = sprintf( 'sheets.credentials_path -> file not readable at "%s"', $creds_path );
                }
                if ( ! empty( $missing ) ) {
                        foreach ( $missing as $miss ) {
                                WP_CLI::line( 'Missing: ' . $miss . '.' );
                        }
                        WP_CLI::error( 'Google Sheet append is not configured; failing closed. Nothing was written.' );
                }

                $creds = json_decode( (string) file_get_contents( $creds_path ), true );
                if ( ! is_array( $creds ) || empty( $creds['client_email'] ) || empty( $creds['private_key'] ) ) {
                        WP_CLI::error( sprintf( 'Service-account JSON at "%s" is missing client_email/private_key. Nothing written.', $creds_path ) );
                }

                $token = $this->google_sa_access_token( $creds );
                if ( ! $token['ok'] ) {
                        WP_CLI::error( 'Could not obtain a Google access token: ' . $token['error'] . '. Nothing written.' );
                }

                $vio = (string) $config->get( 'sheets.google_ads_import.value_input_option', 'RAW' );

                foreach ( $to_append as $item ) {
                        // Re-check both the 0.5 Converted sink and the historical 0.4.x
                        // Won sink immediately before the physical append. This closes the
                        // upgrade/concurrency window where an old ledger row exists (or a
                        // parallel process appends after candidate selection) but the new
                        // stage-specific sink is still empty. Qualified Lead deliberately
                        // does NOT consult the legacy Won sink because it is a distinct
                        // Google conversion stage for the same canonical transaction ID.
                        if ( $this->ledger_appended_in_any_sink( array( $sink, 'google_ads_import_sheet' ), $item['txn'] ) ) {
                                $count['already_appended']++;
                                $report[] = array(
                                        'contact_id'     => $item['cid'],
                                        'transaction_id' => $item['txn'],
                                        'exportable'     => 'yes',
                                        'action'         => 'skipped',
                                        'detail'         => 'last-moment dedupe: Converted Lead or legacy Won ledger already appended',
                                );
                                continue;
                        }

                        $append     = $this->google_sheets_append_row( $spreadsheet_id, $tab, $item['values'], $token['token'], $vio );
                        $attempt_no = $this->ledger_attempt_count( $sink, $item['txn'] ) + 1;

                        if ( $append['ok'] ) {
                                // Sheet append succeeded: the row now physically exists in the
                                // Sheet. It may ONLY be reported as a clean append once its ledger
                                // record is confirmed written. If the ledger insert fails, dedupe
                                // is blind to this row and a later batch could double-append it.
                                $led = $this->ledger_insert(
                                        array(
                                                'sink_slug'      => $sink,
                                                'contact_id'     => $item['cid'],
                                                'transaction_id' => $item['txn'],
                                                'row_hash'       => $item['row_hash'],
                                                'spreadsheet_id' => $spreadsheet_id,
                                                'tab'            => $tab,
                                                'updated_range'  => (string) $append['updated_range'],
                                                'status'         => 'appended',
                                                'error'          => '',
                                                'attempts'       => $attempt_no,
                                        )
                                );

                                if ( $led['ok'] ) {
                                        $count['appended']++;
                                        $report[] = array(
                                                'contact_id'     => $item['cid'],
                                                'transaction_id' => $item['txn'],
                                                'exportable'     => 'yes',
                                                'action'         => 'appended',
                                                'detail'         => '' !== $append['updated_range'] ? $append['updated_range'] : '(range not reported)',
                                        );
                                } else {
                                        // ORPHAN: Sheet row written, ledger insert NOT confirmed. Do
                                        // not count as appended. Collect for the consolidated
                                        // end-of-run report and force a non-zero exit after the loop.
                                        $orphan_range = '' !== $append['updated_range'] ? (string) $append['updated_range'] : '(range not reported)';
                                        $orphans[]    = array(
                                                'contact_id'     => $item['cid'],
                                                'transaction_id' => $item['txn'],
                                                'updated_range'  => $orphan_range,
                                                'tab'            => $tab,
                                                'db_error'       => $led['error'],
                                        );
                                        $report[] = array(
                                                'contact_id'     => $item['cid'],
                                                'transaction_id' => $item['txn'],
                                                'exportable'     => 'yes',
                                                'action'         => 'ORPHAN (appended, NOT ledgered)',
                                                'detail'         => $orphan_range . ' | ledger error: ' . $led['error'],
                                        );
                                }
                        } else {
                                // Sheet append failed: nothing is in the Sheet, so there is no
                                // orphan risk here. Record the failed attempt for history. If THAT
                                // ledger insert also fails, it is bookkeeping loss only (no Sheet
                                // row, no dedupe poisoning) — warn, but do not gate the exit code.
                                $count['failed']++;
                                $led = $this->ledger_insert(
                                        array(
                                                'sink_slug'      => $sink,
                                                'contact_id'     => $item['cid'],
                                                'transaction_id' => $item['txn'],
                                                'row_hash'       => $item['row_hash'],
                                                'spreadsheet_id' => $spreadsheet_id,
                                                'tab'            => $tab,
                                                'updated_range'  => '',
                                                'status'         => 'failed',
                                                'error'          => (string) $append['error'],
                                                'attempts'       => $attempt_no,
                                        )
                                );
                                if ( ! $led['ok'] ) {
                                        WP_CLI::warning( sprintf(
                                                'Failed-attempt ledger write did not persist for contact %d (%s): %s. No Sheet row exists for this item, so there is no orphan/dedupe risk; only attempt history may be incomplete.',
                                                (int) $item['cid'],
                                                (string) $item['txn'],
                                                $led['error']
                                        ) );
                                }
                                $report[] = array(
                                        'contact_id'     => $item['cid'],
                                        'transaction_id' => $item['txn'],
                                        'exportable'     => 'yes',
                                        'action'         => 'failed',
                                        'detail'         => (string) $append['error'],
                                );
                        }
                }

                $this->print_export_batch_summary( $report, $count, $count['appended'], false );
                WP_CLI::line( '' );
                WP_CLI::line( 'Google Ads upload DISABLED. No CRM mutation. Append-only.' );

                // Orphan gate: a confirmed Sheet append whose ledger record could not be
                // written is a data-integrity incident (the row exists but dedupe is blind
                // to it). Per the acceptance rule, no such row may be reported as fully
                // successful. Surface every orphan from this run, then exit non-zero.
                if ( ! empty( $orphans ) ) {
                        WP_CLI::line( '' );
                        WP_CLI::line( '==================== ORPHAN ROW RISK ====================' );
                        WP_CLI::line( sprintf( '%d Sheet row(s) were appended but their ledger record could NOT be confirmed.', count( $orphans ) ) );
                        WP_CLI::line( 'These rows physically exist in the Sheet but are invisible to ledger dedupe.' );
                        WP_CLI::line( 'A later batch run could double-append them. Manual reconciliation required.' );
                        WP_CLI::line( '' );
                        WP_CLI\Utils\format_items( 'table', $orphans, array( 'contact_id', 'transaction_id', 'updated_range', 'tab', 'db_error' ) );
                        WP_CLI::line( '' );
                        WP_CLI::line( sprintf( 'Clean appends: %d. Orphaned (NOT counted as appended): %d. Failed: %d. Skipped: %d.', $count['appended'], count( $orphans ), $count['failed'], $count['already_appended'] ) );
                        WP_CLI::error( sprintf( 'export-batch --apply finished with %d orphan row(s): Sheet write succeeded but ledger insert failed. Reconcile the listed range(s) before the next run.', count( $orphans ) ) );
                }

                WP_CLI::success( sprintf( 'export-batch --apply complete; %d appended, %d failed, %d skipped.', $count['appended'], $count['failed'], $count['already_appended'] ) );
        }

        /**
         * Create missing enabled-family worksheets and install their exact header rows.
         * Existing matching headers are left untouched. Existing non-empty mismatched
         * headers fail closed and are never overwritten.
         *
         * Dry-run by default. This command reads/writes Google Sheets only; it never
         * writes the export ledger and never mutates FluentCRM or Fluent Forms.
         *
         * ## OPTIONS
         *
         * [--apply]
         * : Create missing tabs and write headers into missing/blank header rows.
         *
         * @subcommand provision-sheets
         * @alias provision_sheets
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function provision_sheets( $args, $assoc_args ) {
                $apply  = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );
                $config = SiteEngine_Ads_Bridge_Config::instance();
                $errors = $config->validate();

                if ( ! empty( $errors ) ) {
                        foreach ( $errors as $error ) {
                                WP_CLI::warning( $error );
                        }
                        WP_CLI::error( 'Client config is invalid; refusing Sheet provisioning.' );
                }

                $contracts = $this->enabled_sheet_contracts( $config );
                if ( empty( $contracts ) ) {
                        WP_CLI::error( 'No export family is enabled; there are no Sheet contracts to provision.' );
                }

                $first = reset( $contracts );
                $sheet = $this->prepare_family_sheet_context( $config, $first['tab'] );
                $tabs  = $this->google_sheets_list_tabs( $sheet['spreadsheet_id'], $sheet['token'] );
                if ( ! $tabs['ok'] ) {
                        WP_CLI::error( 'Could not inspect spreadsheet tabs: ' . $tabs['error'] );
                }

                $rows         = array();
                $creates      = array();
                $resizes      = array();
                $writes       = array();
                $conflicts    = array();
                $known_sheets = isset( $tabs['sheets'] ) && is_array( $tabs['sheets'] ) ? $tabs['sheets'] : array();

                foreach ( $contracts as $contract ) {
                        $tab      = $contract['tab'];
                        $expected = array_values( array_map( 'strval', $contract['headers'] ) );

                        if ( ! isset( $known_sheets[ $tab ] ) ) {
                                $creates[ $tab ] = count( $expected );
                                $writes[ $tab ]  = $expected;
                                $rows[] = array(
                                        'family'   => $contract['label'],
                                        'tab'      => $tab,
                                        'columns'  => count( $expected ),
                                        'state'    => 'missing',
                                        'action'   => $apply ? 'create tab at required width + write header' : 'would create tab at required width + write header',
                                        'detail'   => '',
                                );
                                continue;
                        }

                        $sheet_meta    = $known_sheets[ $tab ];
                        $column_count  = isset( $sheet_meta['column_count'] ) ? (int) $sheet_meta['column_count'] : 0;
                        $required_cols = count( $expected );
                        $needs_resize  = $column_count > 0 && $column_count < $required_cols;

                        $read = $this->google_sheets_read_header( $sheet['spreadsheet_id'], $tab, $sheet['token'] );
                        if ( ! $read['ok'] ) {
                                WP_CLI::error( sprintf( 'Could not read header row from tab "%s": %s', $tab, $read['error'] ) );
                        }
                        $actual = $this->trim_trailing_empty_cells( $read['values'] );

                        if ( empty( $actual ) ) {
                                if ( $needs_resize ) {
                                        $resizes[ $tab ] = array(
                                                'sheet_id'     => isset( $sheet_meta['sheet_id'] ) ? (int) $sheet_meta['sheet_id'] : 0,
                                                'column_count' => $required_cols,
                                        );
                                }
                                $writes[ $tab ] = $expected;
                                $rows[] = array(
                                        'family'   => $contract['label'],
                                        'tab'      => $tab,
                                        'columns'  => $required_cols,
                                        'state'    => 'blank header',
                                        'action'   => $apply
                                                ? ( $needs_resize ? 'expand columns + write header' : 'write header' )
                                                : ( $needs_resize ? 'would expand columns + write header' : 'would write header' ),
                                        'detail'   => $needs_resize ? sprintf( 'current grid: %d columns; required: %d', $column_count, $required_cols ) : '',
                                );
                                continue;
                        }

                        // Safe append-only header migrations. Historical columns are
                        // never reinterpreted unless a known manual Golden-template
                        // variant is detected AND its trailing manually-added data cells
                        // are confirmed blank before the canonical header is restored.
                        $legacy_columns = 0;
                        $migration_note = '';
                        if ( 'google_ads_import' === $contract['label'] && 21 === count( $expected ) && 20 === count( $actual ) ) {
                                if ( $actual === array_slice( $expected, 0, 20 ) ) {
                                        $legacy_columns = 20;
                                        $migration_note = 'append Conversion action header';
                                } elseif ( array_slice( $actual, 0, 19 ) === array_slice( $expected, 0, 19 ) && 'Conversion action' === (string) $actual[19] ) {
                                        $row_count = isset( $sheet_meta['row_count'] ) ? max( 2, (int) $sheet_meta['row_count'] ) : 1000;
                                        $blank = $this->google_sheets_range_is_blank( $sheet['spreadsheet_id'], $tab, 'T2:T' . $row_count, $sheet['token'] );
                                        if ( ! $blank['ok'] ) {
                                                WP_CLI::error( sprintf( 'Could not validate compact Google_Ads_Import migration safety on "%s": %s', $tab, $blank['error'] ) );
                                        }
                                        if ( $blank['blank'] ) {
                                                if ( $needs_resize ) {
                                                        $resizes[ $tab ] = array(
                                                                'sheet_id'     => isset( $sheet_meta['sheet_id'] ) ? (int) $sheet_meta['sheet_id'] : 0,
                                                                'column_count' => $required_cols,
                                                        );
                                                }
                                                $writes[ $tab ] = $expected;
                                                $rows[] = array(
                                                        'family'  => $contract['label'],
                                                        'tab'     => $tab,
                                                        'columns' => $required_cols,
                                                        'state'   => 'manual compact 0.5 header',
                                                        'action'  => $apply ? 'restore Session attributes + move Conversion action header to column U' : 'would restore Session attributes + move Conversion action header to column U',
                                                        'detail'  => 'T2:T is blank; existing data rows remain untouched',
                                                );
                                                continue;
                                        }
                                }
                        } elseif ( 'all_leads' === $contract['label'] && 94 === count( $expected ) && 93 === count( $actual ) ) {
                                $legacy_columns = 93;
                                $migration_note = 'append Inquiry ID header';
                        } elseif ( 'all_leads' === $contract['label'] && 94 === count( $expected ) && 88 === count( $actual ) ) {
                                $legacy_columns = 88;
                                $migration_note = 'append 5 lifecycle-ledger headers + Inquiry ID';
                        } elseif ( 'all_leads' === $contract['label'] && 94 === count( $expected ) && 80 === count( $actual ) ) {
                                $legacy_columns = 80;
                                $migration_note = 'append 8 Golden Account tracking + 5 lifecycle-ledger headers + Inquiry ID';
                        } elseif ( 'all_leads' === $contract['label'] && 94 === count( $expected ) && in_array( count( $actual ), array( 82, 83 ), true ) && array_slice( $actual, 0, 80 ) === array_slice( $expected, 0, 80 ) ) {
                                $tail = array_slice( $actual, 80 );
                                $known_manual_tail = in_array(
                                        $tail,
                                        array(
                                                array( 'Customer Rating', 'Qualified at' ),
                                                array( 'Customer Rating', 'Customer Rating', 'Qualified at' ),
                                        ),
                                        true
                                );
                                if ( $known_manual_tail ) {
                                        $end_col   = 82 === count( $actual ) ? 'CD' : 'CE';
                                        $row_count = isset( $sheet_meta['row_count'] ) ? max( 2, (int) $sheet_meta['row_count'] ) : 1000;
                                        $blank = $this->google_sheets_range_is_blank( $sheet['spreadsheet_id'], $tab, 'CC2:' . $end_col . $row_count, $sheet['token'] );
                                        if ( ! $blank['ok'] ) {
                                                WP_CLI::error( sprintf( 'Could not validate manual All_Leads migration safety on "%s": %s', $tab, $blank['error'] ) );
                                        }
                                        if ( $blank['blank'] ) {
                                                if ( $needs_resize ) {
                                                        $resizes[ $tab ] = array(
                                                                'sheet_id'     => isset( $sheet_meta['sheet_id'] ) ? (int) $sheet_meta['sheet_id'] : 0,
                                                                'column_count' => $required_cols,
                                                        );
                                                }
                                                $writes[ $tab ] = $expected;
                                                $rows[] = array(
                                                        'family'  => $contract['label'],
                                                        'tab'     => $tab,
                                                        'columns' => $required_cols,
                                                        'state'   => 'manual Golden header',
                                                        'action'  => $apply ? 'normalize to canonical 0.6.0 All_Leads header' : 'would normalize to canonical 0.6.0 All_Leads header',
                                                        'detail'  => 'manually-added trailing columns are blank; existing data rows remain untouched',
                                                );
                                                continue;
                                        }
                                }
                        }
                        if ( $legacy_columns > 0 && $actual === array_slice( $expected, 0, $legacy_columns ) ) {
                                if ( $needs_resize ) {
                                        $resizes[ $tab ] = array(
                                                'sheet_id'     => isset( $sheet_meta['sheet_id'] ) ? (int) $sheet_meta['sheet_id'] : 0,
                                                'column_count' => $required_cols,
                                        );
                                }
                                $writes[ $tab ] = $expected;
                                $rows[] = array(
                                        'family'   => $contract['label'],
                                        'tab'      => $tab,
                                        'columns'  => $required_cols,
                                        'state'    => 'legacy 0.4.1 header',
                                        'action'   => $apply ? $migration_note : 'would ' . $migration_note,
                                        'detail'   => sprintf( 'first %d columns match exactly; existing data rows remain untouched', $legacy_columns ),
                                );
                                continue;
                        }

                        if ( $actual === $expected ) {
                                if ( $needs_resize ) {
                                        $resizes[ $tab ] = array(
                                                'sheet_id'     => isset( $sheet_meta['sheet_id'] ) ? (int) $sheet_meta['sheet_id'] : 0,
                                                'column_count' => $required_cols,
                                        );
                                }
                                $rows[] = array(
                                        'family'   => $contract['label'],
                                        'tab'      => $tab,
                                        'columns'  => $required_cols,
                                        'state'    => 'ready',
                                        'action'   => $needs_resize ? ( $apply ? 'expand columns' : 'would expand columns' ) : 'none',
                                        'detail'   => $needs_resize
                                                ? sprintf( 'header matches; current grid: %d columns; required: %d', $column_count, $required_cols )
                                                : 'header byte-matches contract',
                                );
                                continue;
                        }

                        $detail      = $this->describe_header_difference( $expected, $actual );
                        $conflicts[] = $tab . ': ' . $detail;
                        $rows[] = array(
                                'family'   => $contract['label'],
                                'tab'      => $tab,
                                'columns'  => count( $expected ),
                                'state'    => 'CONFLICT',
                                'action'   => 'refuse overwrite',
                                'detail'   => $detail,
                        );
                }

                $google_import_tab = '';
                if ( (bool) $config->get( 'exports.qualified_leads.enabled', false ) || (bool) $config->get( 'exports.won_jobs.enabled', false ) ) {
                        $google_import_tab = trim( (string) $config->get( 'exports.won_jobs.sheet_tab', 'Google_Ads_Import' ) );
                        if ( '' !== $google_import_tab ) {
                                $index = isset( $known_sheets[ $google_import_tab ]['index'] ) ? (int) $known_sheets[ $google_import_tab ]['index'] : -1;
                                $rows[] = array(
                                        'family'   => 'data_manager_order',
                                        'tab'      => $google_import_tab,
                                        'columns'  => 21,
                                        'state'    => 0 === $index ? 'first worksheet' : ( -1 === $index ? 'will be created' : 'worksheet index ' . $index ),
                                        'action'   => 0 === $index ? 'none' : ( $apply ? 'move to worksheet index 0' : 'would move to worksheet index 0' ),
                                        'detail'   => 'Google Ads Data Manager reads the first/leftmost worksheet',
                                );
                        }
                }

                WP_CLI::line( 'SiteEngine Ads Bridge: provision-sheets (' . ( $apply ? 'APPLY' : 'DRY-RUN' ) . ')' );
                WP_CLI::line( '------------------------------------------------------------' );
                WP_CLI::line( 'Spreadsheet: ' . $this->mask_identifier( $sheet['spreadsheet_id'] ) );
                WP_CLI::line( 'Only missing tabs and blank header rows are writable. Non-empty mismatches fail closed.' );
                WP_CLI::line( '' );
                WP_CLI\Utils\format_items( 'table', $rows, array( 'family', 'tab', 'columns', 'state', 'action', 'detail' ) );

                if ( ! empty( $conflicts ) ) {
                        WP_CLI::line( '' );
                        foreach ( $conflicts as $conflict ) {
                                WP_CLI::warning( $conflict );
                        }
                        WP_CLI::error( 'One or more existing Sheet headers conflict with the plugin contract. Nothing was written.' );
                }

                if ( ! $apply ) {
                        WP_CLI::success( sprintf( 'Dry-run complete; %d tab(s) would be created, %d existing tab(s) would be expanded, and %d header row(s) would be written. Google_Ads_Import would also be moved to worksheet index 0 when required. No Sheet, ledger, form, or CRM write.', count( $creates ), count( $resizes ), count( $writes ) ) );
                        return;
                }

                if ( ! empty( $creates ) ) {
                        $created = $this->google_sheets_create_tabs( $sheet['spreadsheet_id'], $creates, $sheet['token'] );
                        if ( ! $created['ok'] ) {
                                WP_CLI::error( 'Could not create required Sheet tab(s): ' . $created['error'] );
                        }
                }

                if ( ! empty( $resizes ) ) {
                        $resized = $this->google_sheets_resize_tabs( $sheet['spreadsheet_id'], $resizes, $sheet['token'] );
                        if ( ! $resized['ok'] ) {
                                WP_CLI::error( 'Could not expand required Sheet tab(s): ' . $resized['error'] );
                        }
                }

                foreach ( $writes as $tab => $headers ) {
                        $written = $this->google_sheets_write_header( $sheet['spreadsheet_id'], $tab, $headers, $sheet['token'] );
                        if ( ! $written['ok'] ) {
                                WP_CLI::error( sprintf( 'Could not write header row to tab "%s": %s', $tab, $written['error'] ) );
                        }
                }

                $moved_first = false;
                if ( '' !== $google_import_tab ) {
                        $after_tabs = $this->google_sheets_list_tabs( $sheet['spreadsheet_id'], $sheet['token'] );
                        if ( ! $after_tabs['ok'] || ! isset( $after_tabs['sheets'][ $google_import_tab ] ) ) {
                                WP_CLI::error( 'Could not verify Google import worksheet ordering after provisioning.' );
                        }
                        $meta = $after_tabs['sheets'][ $google_import_tab ];
                        if ( ! isset( $meta['index'] ) || 0 !== (int) $meta['index'] ) {
                                $moved = $this->google_sheets_move_tab_to_index( $sheet['spreadsheet_id'], (int) $meta['sheet_id'], 0, $sheet['token'] );
                                if ( ! $moved['ok'] ) {
                                        WP_CLI::error( 'Could not move Google import worksheet to index 0: ' . $moved['error'] );
                                }
                                $moved_first = true;
                        }
                        $verify_tabs = $this->google_sheets_list_tabs( $sheet['spreadsheet_id'], $sheet['token'] );
                        if ( ! $verify_tabs['ok'] || ! isset( $verify_tabs['sheets'][ $google_import_tab ]['index'] ) || 0 !== (int) $verify_tabs['sheets'][ $google_import_tab ]['index'] ) {
                                WP_CLI::error( 'Post-write verification failed: Google import worksheet is not index 0.' );
                        }
                }

                foreach ( $contracts as $contract ) {
                        $verify = $this->google_sheets_read_header( $sheet['spreadsheet_id'], $contract['tab'], $sheet['token'] );
                        if ( ! $verify['ok'] ) {
                                WP_CLI::error( sprintf( 'Post-write verification could not read tab "%s": %s', $contract['tab'], $verify['error'] ) );
                        }
                        $actual = $this->trim_trailing_empty_cells( $verify['values'] );
                        if ( $actual !== array_values( array_map( 'strval', $contract['headers'] ) ) ) {
                                WP_CLI::error( sprintf( 'Post-write verification failed for tab "%s": %s', $contract['tab'], $this->describe_header_difference( $contract['headers'], $actual ) ) );
                        }
                }

                WP_CLI::success( sprintf( 'Sheet provisioning complete; %d tab(s) created, %d existing tab(s) expanded, %d header row(s) written, %d enabled contract(s) verified%s. No ledger or CRM mutation.', count( $creates ), count( $resizes ), count( $writes ), count( $contracts ), $moved_first ? ', Google import moved to worksheet index 0' : ', Google import worksheet order verified' ) );
        }

        /**
         * Export every unledgered submission from the configured master Fluent Form
         * to the operational All_Leads worksheet.
         *
         * All_Leads is an internal operational record, not a Google conversion feed.
         * It intentionally records DNC and consent-denied submissions together with
         * their current state; those flags remain hard exclusions from advertising
         * conversion exports. Raw identifiers are emitted without hashing.
         *
         * ## OPTIONS
         *
         * [--dry-run]
         * : Build and report rows; write nothing. Mutually exclusive with --apply.
         *
         * [--apply]
         * : Append unledgered rows to the configured All_Leads tab.
         *
         * [--limit=<n>]
         * : Maximum submissions to consider. Default: 25.
         *
         * [--ids=<csv>]
         * : Optional explicit Fluent Forms submission IDs. Intended for canaries.
         *
         * @subcommand export-all-leads
         * @alias export_all_leads
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function export_all_leads( $args, $assoc_args ) {
                $batch  = $this->parse_family_batch_args( $assoc_args, 25, false );
                $dry    = $batch['dry'];
                $limit  = $batch['limit'];
                $config = SiteEngine_Ads_Bridge_Config::instance();
                $sink   = 'all_leads_sheet';

                if ( ! (bool) $config->get( 'exports.all_leads.enabled', false ) ) {
                        WP_CLI::error( 'exports.all_leads.enabled is false. Enable the family in client config before running it.' );
                }

                $tab = trim( (string) $config->get( 'exports.all_leads.sheet_tab', '' ) );
                if ( '' === $tab ) {
                        WP_CLI::error( 'exports.all_leads.sheet_tab is empty; refusing to run.' );
                }

                $form_id = (int) $config->get( 'fluent_forms.form_id', 0 );
                if ( $form_id < 1 ) {
                        WP_CLI::error( 'fluent_forms.form_id must be a positive verified form ID before All_Leads can run.' );
                }

                $table = $this->find_canonical_submissions_table();
                if ( '' === $table || ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
                        WP_CLI::error( 'No canonical Fluent Forms submissions table was found.' );
                }

                $columns = $this->table_columns( $table );
                foreach ( array( 'id', 'form_id', 'response' ) as $required_column ) {
                        if ( ! in_array( $required_column, $columns, true ) ) {
                                WP_CLI::error( sprintf( 'Canonical submissions table %s lacks required column %s.', $table, $required_column ) );
                        }
                }

                $ids_raw = trim( (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'ids', '' ) );
                $ids     = '' !== $ids_raw ? $this->parse_positive_csv_ids( $ids_raw, '--ids' ) : array();
                if ( ! empty( $ids ) ) {
                        $ids = array_slice( $ids, 0, $limit );
                }

                $ledger_exists = $this->ledger_table_exists();
                $submissions   = $this->select_all_leads_submissions( $table, $form_id, $ids, $limit, $sink, $ledger_exists );

                WP_CLI::line( 'SiteEngine Ads Bridge: export-all-leads (' . ( $dry ? 'DRY-RUN' : 'APPLY' ) . ')' );
                WP_CLI::line( '------------------------------------------------------------' );
                WP_CLI::line( sprintf( 'Source: %s, configured form_id=%d.', $table, $form_id ) );
                WP_CLI::line( sprintf( 'Sink: %s -> tab "%s". Raw operational data; no Google Ads upload.', $sink, $tab ) );
                WP_CLI::line( 'DNC and consent state are recorded, not used to suppress this internal operational export.' );
                WP_CLI::line( sprintf( 'Candidate selector: %s. Limit: %d.', empty( $ids ) ? 'oldest unledgered configured-form submissions' : 'explicit submission --ids', $limit ) );
                WP_CLI::line( '' );

                $count = array(
                        'candidates'       => count( $submissions ),
                        'exportable'       => 0,
                        'already_appended' => 0,
                        'appended'         => 0,
                        'failed'           => 0,
                        'not_exportable'   => 0,
                );
                $report    = array();
                $to_append = array();

                foreach ( $submissions as $submission ) {
                        $entry_id = isset( $submission['id'] ) ? (int) $submission['id'] : 0;

                        try {
                                $built = $this->build_all_leads_row(
                                        $submission,
                                        $table,
                                        $config,
                                        'New',
                                        isset( $submission['created_at'] ) ? (string) $submission['created_at'] : ''
                                );
                        } catch ( \Throwable $e ) {
                                $count['not_exportable']++;
                                $report[] = array(
                                        'record'         => 'submission ' . $entry_id,
                                        'transaction_id' => '(none)',
                                        'exportable'     => 'no',
                                        'action'         => 'not_exportable',
                                        'detail'         => 'builder error: ' . $e->getMessage(),
                                );
                                continue;
                        }

                        $txn    = (string) $built['transaction_id'];
                        $values = (array) $built['values'];
                        $cid    = (int) $built['contact_id'];
                        $record = 'submission ' . $entry_id . ( $cid > 0 ? ' / contact ' . $cid : ' / no CRM contact' );

                        if ( empty( $built['exportable'] ) ) {
                                $count['not_exportable']++;
                                $report[] = array(
                                        'record'         => $record,
                                        'transaction_id' => '' !== $txn ? $txn : '(none)',
                                        'exportable'     => 'no',
                                        'action'         => 'not_exportable',
                                        'detail'         => implode( '; ', (array) $built['reasons'] ),
                                );
                                continue;
                        }

                        if ( ! preg_match( '/^ff:\d+:\d+$/', $txn ) || empty( $values ) ) {
                                $count['not_exportable']++;
                                $report[] = array(
                                        'record'         => $record,
                                        'transaction_id' => '' !== $txn ? $txn : '(none)',
                                        'exportable'     => 'no',
                                        'action'         => 'not_exportable',
                                        'detail'         => 'failed append-safety assert (deterministic ff id / non-empty row)',
                                );
                                continue;
                        }

                        $count['exportable']++;
                        if ( $ledger_exists && $this->ledger_appended_count( $sink, $txn ) > 0 ) {
                                $count['already_appended']++;
                                $report[] = array(
                                        'record'         => $record,
                                        'transaction_id' => $txn,
                                        'exportable'     => 'yes',
                                        'action'         => 'skipped',
                                        'detail'         => 'already appended to this sink (ledger)',
                                );
                                continue;
                        }

                        $to_append[] = array(
                                'record'   => $record,
                                'cid'      => $cid,
                                'txn'      => $txn,
                                'values'   => $values,
                                'row_hash' => hash( 'sha256', implode( "\x1f", array_map( 'strval', $values ) ) ),
                        );
                }

                $this->complete_family_sheet_batch( 'export-all-leads', $sink, $tab, $dry, $config, $report, $count, $to_append );
        }

        /**
         * Append lifecycle snapshots to All_Leads so later CRM decisions are not
         * lost after the one-time intake row has already been ledgered.
         *
         * Qualified and Unqualified are discovered from durable milestone custom
         * fields. Won is discovered from the configured Won lifecycle tag. Each
         * stage owns an independent ledger sink, so one deterministic submission
         * may legitimately produce New, Qualified, and Won snapshots over time.
         *
         * This is an internal operational ledger only. DNC/consent do not suppress
         * lifecycle snapshots and no Google conversion is created by this command.
         *
         * ## OPTIONS
         *
         * [--dry-run]
         * : Build/report lifecycle snapshots without writes.
         *
         * [--apply]
         * : Append eligible unledgered lifecycle snapshots.
         *
         * [--limit=<n>]
         * : Maximum candidates per lifecycle stage. Default: 25.
         *
         * [--ids=<csv>]
         * : Optional explicit FluentCRM contact IDs.
         *
         * @subcommand export-lifecycle-ledger
         * @alias export_lifecycle_ledger
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function export_lifecycle_ledger( $args, $assoc_args ) {
                $batch  = $this->parse_family_batch_args( $assoc_args, 25, false );
                $dry    = $batch['dry'];
                $limit  = $batch['limit'];
                $config = SiteEngine_Ads_Bridge_Config::instance();

                if ( ! (bool) $config->get( 'exports.all_leads.enabled', false ) ) {
                        WP_CLI::error( 'exports.all_leads.enabled is false. Lifecycle snapshots require the All_Leads operational ledger.' );
                }
                if ( ! class_exists( '\\FluentCrm\\App\\Models\\Subscriber' ) ) {
                        WP_CLI::error( 'FluentCRM is not active (Subscriber model not found).' );
                }

                $tab = trim( (string) $config->get( 'exports.all_leads.sheet_tab', '' ) );
                if ( '' === $tab ) {
                        WP_CLI::error( 'exports.all_leads.sheet_tab is empty; refusing to run lifecycle ledger export.' );
                }

                $ids_raw = trim( (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'ids', '' ) );
                $ids     = '' !== $ids_raw ? array_slice( $this->parse_positive_csv_ids( $ids_raw, '--ids' ), 0, $limit ) : array();
                $ledger_exists = $this->ledger_table_exists();

                $stages = array(
                        'qualified' => array(
                                'event' => 'Qualified',
                                'field' => trim( (string) $config->get( 'exports.qualified_leads.date_field', 'qualified_at' ) ),
                                'sink'  => 'all_leads_lifecycle_qualified',
                                'mode'  => 'field',
                        ),
                        'unqualified' => array(
                                'event' => 'Unqualified',
                                'field' => trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_at_field', 'unqualified_at' ) ),
                                'sink'  => 'all_leads_lifecycle_unqualified',
                                'mode'  => 'field',
                        ),
                        'won' => array(
                                'event' => 'Won',
                                'field' => trim( (string) $config->get( 'exports.won_jobs.date_field', 'closed_date' ) ),
                                'sink'  => 'all_leads_lifecycle_won',
                                'mode'  => 'tag',
                        ),
                );

                WP_CLI::line( 'SiteEngine Ads Bridge: export-lifecycle-ledger (' . ( $dry ? 'DRY-RUN' : 'APPLY' ) . ')' );
                WP_CLI::line( '-----------------------------------------------------------------' );
                WP_CLI::line( sprintf( 'Target: tab "%s". Internal append-only lifecycle history; no Google Ads conversion upload.', $tab ) );
                WP_CLI::line( 'Stages: Qualified, Unqualified, Won. Each stage has an independent ledger sink.' );
                WP_CLI::line( sprintf( 'Limit: %d candidate(s) per stage.', $limit ) );
                WP_CLI::line( '' );

                foreach ( $stages as $role => $stage ) {
                        if ( '' === $stage['field'] ) {
                                WP_CLI::warning( sprintf( '%s stage skipped: configured milestone field is empty.', $stage['event'] ) );
                                continue;
                        }

                        $inquiry_selection = $this->select_unledgered_inquiries( $role, $stage['sink'], $limit, $ledger_exists, $ids );
                        $inquiries         = $inquiry_selection['inquiries'];
                        $remaining         = max( 0, $limit - count( $inquiries ) );
                        $subscribers       = array();
                        $legacy_skipped    = 0;

                        if ( $remaining > 0 ) {
                                if ( ! empty( $ids ) ) {
                                        foreach ( $ids as $cid ) {
                                                if ( class_exists( 'SiteEngine_Ads_Bridge_Inquiries' ) && SiteEngine_Ads_Bridge_Inquiries::contact_has_history( $cid ) ) {
                                                        continue;
                                                }
                                                $subscriber = \FluentCrm\App\Models\Subscriber::find( $cid );
                                                if ( $subscriber ) {
                                                        $subscribers[] = $subscriber;
                                                        if ( count( $subscribers ) >= $remaining ) {
                                                                break;
                                                        }
                                                }
                                        }
                                } elseif ( 'tag' === $stage['mode'] ) {
                                        $found       = $this->discover_lifecycle_role_subscribers( $role );
                                        $selection   = $this->select_current_unledgered_subscribers( $found, $stage['sink'], $remaining, $ledger_exists );
                                        $subscribers = $selection['subscribers'];
                                        $legacy_skipped = $selection['ledger_skipped'];
                                } else {
                                        $found       = $this->discover_custom_field_subscribers( $stage['field'] );
                                        $selection   = $this->select_current_unledgered_subscribers( $found, $stage['sink'], $remaining, $ledger_exists );
                                        $subscribers = $selection['subscribers'];
                                        $legacy_skipped = $selection['ledger_skipped'];
                                }
                        }

                        $items = array();
                        foreach ( $inquiries as $inquiry ) {
                                $items[] = array( 'type' => 'inquiry', 'inquiry' => $inquiry );
                        }
                        foreach ( $subscribers as $subscriber ) {
                                $items[] = array( 'type' => 'legacy', 'subscriber' => $subscriber );
                        }
                        $selector = ! empty( $ids )
                                ? sprintf( 'explicit --ids; inquiry-aware=%d, legacy=%d', count( $inquiries ), count( $subscribers ) )
                                : sprintf( 'inquiry-aware %s + legacy; ledger prefiltered inquiry=%d legacy=%d', $stage['event'], $inquiry_selection['ledger_skipped'], $legacy_skipped );

                        WP_CLI::line( sprintf( '[%s] selector: %s', $stage['event'], $selector ) );

                        $count = array(
                                'candidates'       => count( $items ),
                                'exportable'       => 0,
                                'already_appended' => 0,
                                'appended'         => 0,
                                'failed'           => 0,
                                'not_exportable'   => 0,
                        );
                        $report    = array();
                        $to_append = array();

                        foreach ( $items as $item ) {
                                $inquiry = ( isset( $item['type'] ) && 'inquiry' === $item['type'] && isset( $item['inquiry'] ) && is_array( $item['inquiry'] ) ) ? $item['inquiry'] : null;
                                if ( is_array( $inquiry ) ) {
                                        $cid        = isset( $inquiry['contact_id'] ) ? (int) $inquiry['contact_id'] : 0;
                                        $subscriber = $cid > 0 ? \FluentCrm\App\Models\Subscriber::find( $cid ) : null;
                                        $record     = 'inquiry ' . (string) $inquiry['inquiry_id'] . ' / contact ' . $cid;
                                        if ( ! $subscriber ) {
                                                $count['not_exportable']++;
                                                $report[] = array(
                                                        'record'         => $record,
                                                        'transaction_id' => isset( $inquiry['transaction_id'] ) ? (string) $inquiry['transaction_id'] : '(none)',
                                                        'exportable'     => 'no',
                                                        'action'         => 'not_exportable',
                                                        'detail'         => 'inquiry-bound FluentCRM contact not found',
                                                );
                                                continue;
                                        }
                                        $custom   = method_exists( $subscriber, 'custom_fields' ) ? (array) $subscriber->custom_fields() : array();
                                        $email    = isset( $subscriber->email ) ? (string) $subscriber->email : '';
                                        $event_at = isset( $inquiry[ $stage['field'] ] ) ? (string) $inquiry[ $stage['field'] ] : '';
                                } else {
                                        $subscriber = isset( $item['subscriber'] ) ? $item['subscriber'] : null;
                                        if ( ! is_object( $subscriber ) || ! isset( $subscriber->id ) ) {
                                                continue;
                                        }
                                        $cid      = (int) $subscriber->id;
                                        $record   = 'contact ' . $cid;
                                        $custom   = method_exists( $subscriber, 'custom_fields' ) ? (array) $subscriber->custom_fields() : array();
                                        $email    = isset( $subscriber->email ) ? (string) $subscriber->email : '';
                                        $event_at = $this->get_contact_custom_value( $subscriber, $custom, $stage['field'] );
                                }

                                if ( '' === trim( (string) $event_at ) ) {
                                        $count['not_exportable']++;
                                        $report[] = array(
                                                'record'         => $record,
                                                'transaction_id' => '(none)',
                                                'exportable'     => 'no',
                                                'action'         => 'not_exportable',
                                                'detail'         => $stage['field'] . ' is blank',
                                        );
                                        continue;
                                }

                                $resolved = is_array( $inquiry ) ? SiteEngine_Ads_Bridge_Inquiries::resolver_for_inquiry( $inquiry ) : $this->resolve_won_order_id( $custom, $email );
                                $txn      = isset( $resolved['order_id'] ) ? (string) $resolved['order_id'] : '';
                                if ( empty( $resolved['deterministic'] ) || ! preg_match( '/^ff:\\d+:\\d+$/', $txn ) ) {
                                        $count['not_exportable']++;
                                        $report[] = array(
                                                'record'         => $record,
                                                'transaction_id' => '' !== $txn ? $txn : '(none)',
                                                'exportable'     => 'no',
                                                'action'         => 'not_exportable',
                                                'detail'         => 'deterministic canonical Fluent Forms submission unresolved: ' . ( isset( $resolved['reason'] ) ? $resolved['reason'] : '' ),
                                        );
                                        continue;
                                }

                                $context = $this->resolved_submission_context( $resolved );
                                $submission = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
                                if ( empty( $submission ) ) {
                                        $count['not_exportable']++;
                                        $report[] = array(
                                                'record'         => $record,
                                                'transaction_id' => $txn,
                                                'exportable'     => 'no',
                                                'action'         => 'not_exportable',
                                                'detail'         => 'canonical submission row could not be loaded',
                                        );
                                        continue;
                                }

                                try {
                                        $built = $this->build_all_leads_row( $submission, (string) $resolved['submissions_table'], $config, $stage['event'], $event_at, $inquiry );
                                } catch ( \Throwable $e ) {
                                        $count['not_exportable']++;
                                        $report[] = array(
                                                'record'         => $record,
                                                'transaction_id' => $txn,
                                                'exportable'     => 'no',
                                                'action'         => 'not_exportable',
                                                'detail'         => 'builder error: ' . $e->getMessage(),
                                        );
                                        continue;
                                }

                                $values = (array) $built['values'];
                                if ( empty( $built['exportable'] ) || 94 !== count( $values ) ) {
                                        $count['not_exportable']++;
                                        $report[] = array(
                                                'record'         => $record,
                                                'transaction_id' => $txn,
                                                'exportable'     => 'no',
                                                'action'         => 'not_exportable',
                                                'detail'         => ! empty( $built['reasons'] ) ? implode( '; ', (array) $built['reasons'] ) : 'failed append-safety assert (94-column All_Leads row)',
                                        );
                                        continue;
                                }

                                $count['exportable']++;
                                if ( $ledger_exists && $this->ledger_appended_count( $stage['sink'], $txn ) > 0 ) {
                                        $count['already_appended']++;
                                        $report[] = array(
                                                'record'         => $record,
                                                'transaction_id' => $txn,
                                                'exportable'     => 'yes',
                                                'action'         => 'skipped',
                                                'detail'         => 'already appended to this lifecycle sink (ledger)',
                                        );
                                        continue;
                                }

                                $to_append[] = array(
                                        'record'   => $record,
                                        'cid'      => $cid,
                                        'txn'      => $txn,
                                        'values'   => $values,
                                        'row_hash' => hash( 'sha256', implode( "\x1f", array_map( 'strval', $values ) ) ),
                                );
                        }

                        $this->complete_family_sheet_batch( 'export-lifecycle-ledger/' . $role, $stage['sink'], $tab, $dry, $config, $report, $count, $to_append );
                        WP_CLI::line( '' );
                }
        }

        /**
         * Export qualified lead stage events to the shared Google_Ads_Import tab.
         *
         * Qualification is explicit, not inferred from the current lifecycle tag:
         * the configured qualification timestamp field (default qualified_at) is the
         * durable event marker. This means a lead that moves from Qualified to Won
         * between cron runs can still export both funnel stages independently.
         *
         * ## OPTIONS
         *
         * [--dry-run]
         * : Build and report rows; write nothing.
         *
         * [--apply]
         * : Append eligible, unledgered Qualified Lead rows.
         *
         * [--limit=<n>]
         * : Maximum candidates. Default: 25.
         *
         * [--ids=<csv>]
         * : Optional explicit FluentCRM contact IDs.
         *
         * [--time-of-day=<time>]
         * : HH:MM:SS fallback for date-only qualification fields. Default: 12:00:00.
         *
         * @subcommand export-qualified-leads
         * @alias export_qualified_leads
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function export_qualified_leads( $args, $assoc_args ) {
                $batch       = $this->parse_family_batch_args( $assoc_args, 25, true );
                $dry         = $batch['dry'];
                $limit       = $batch['limit'];
                $time_of_day = $batch['time_of_day'];
                $config      = SiteEngine_Ads_Bridge_Config::instance();
                $sink        = 'google_ads_import_qualified_lead';
                $tab         = trim( (string) $config->get( 'exports.won_jobs.sheet_tab', 'Google_Ads_Import' ) );
                $date_field  = trim( (string) $config->get( 'exports.qualified_leads.date_field', 'qualified_at' ) );

                if ( ! (bool) $config->get( 'exports.qualified_leads.enabled', false ) ) {
                        WP_CLI::error( 'exports.qualified_leads.enabled is false. Enable the family before running it.' );
                }
                if ( ! class_exists( '\\FluentCrm\\App\\Models\\Subscriber' ) ) {
                        WP_CLI::error( 'FluentCRM is not active (Subscriber model not found).' );
                }
                if ( '' === $tab || '' === $date_field ) {
                        WP_CLI::error( 'Qualified Lead export requires the shared Google import tab and a qualification timestamp field.' );
                }

                $ids_raw = trim( (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'ids', '' ) );
                $ids     = '' !== $ids_raw ? array_slice( $this->parse_positive_csv_ids( $ids_raw, '--ids' ), 0, $limit ) : array();
                $ledger_exists = $this->ledger_table_exists();

                $inquiry_selection = $this->select_unledgered_inquiries( 'qualified', $sink, $limit, $ledger_exists, $ids );
                $inquiries         = $inquiry_selection['inquiries'];
                $remaining         = max( 0, $limit - count( $inquiries ) );
                $subscribers       = array();
                $legacy_skipped    = 0;

                if ( $remaining > 0 ) {
                        if ( ! empty( $ids ) ) {
                                foreach ( $ids as $cid ) {
                                        if ( class_exists( 'SiteEngine_Ads_Bridge_Inquiries' ) && SiteEngine_Ads_Bridge_Inquiries::contact_has_history( $cid ) ) {
                                                continue;
                                        }
                                        $subscriber = \FluentCrm\App\Models\Subscriber::find( $cid );
                                        if ( $subscriber ) {
                                                $subscribers[] = $subscriber;
                                                if ( count( $subscribers ) >= $remaining ) {
                                                        break;
                                                }
                                        } else {
                                                WP_CLI::warning( sprintf( 'Contact #%d not found; dropped from candidates.', $cid ) );
                                        }
                                }
                        } else {
                                $found       = $this->discover_custom_field_subscribers( $date_field );
                                $selection   = $this->select_current_unledgered_subscribers( $found, $sink, $remaining, $ledger_exists );
                                $subscribers = $selection['subscribers'];
                                $legacy_skipped = $selection['ledger_skipped'];
                        }
                }

                $items = array();
                foreach ( $inquiries as $inquiry ) {
                        $items[] = array( 'type' => 'inquiry', 'inquiry' => $inquiry );
                }
                foreach ( $subscribers as $subscriber ) {
                        $items[] = array( 'type' => 'legacy', 'subscriber' => $subscriber );
                }
                $selector = ! empty( $ids )
                        ? sprintf( 'explicit --ids; inquiry-aware=%d, legacy=%d', count( $inquiries ), count( $subscribers ) )
                        : sprintf( 'inquiry-aware %s + legacy contacts; ledger prefiltered inquiry=%d legacy=%d', $date_field, $inquiry_selection['ledger_skipped'], $legacy_skipped );

                WP_CLI::line( 'SiteEngine Ads Bridge: export-qualified-leads (' . ( $dry ? 'DRY-RUN' : 'APPLY' ) . ')' );
                WP_CLI::line( '------------------------------------------------------------' );
                WP_CLI::line( sprintf( 'Sink: %s -> tab "%s"; conversion action "%s".', $sink, $tab, (string) $config->get( 'exports.qualified_leads.conversion_action', 'SE | Qualified Lead' ) ) );
                WP_CLI::line( sprintf( 'Candidate selector: %s. Limit: %d.', $selector, $limit ) );
                WP_CLI::line( '' );

                $count = array(
                        'candidates'       => count( $items ),
                        'exportable'       => 0,
                        'already_appended' => 0,
                        'appended'         => 0,
                        'failed'           => 0,
                        'not_exportable'   => 0,
                );
                $report    = array();
                $to_append = array();

                foreach ( $items as $item ) {
                        $inquiry = ( isset( $item['type'] ) && 'inquiry' === $item['type'] && isset( $item['inquiry'] ) && is_array( $item['inquiry'] ) ) ? $item['inquiry'] : null;
                        if ( is_array( $inquiry ) ) {
                                $cid        = isset( $inquiry['contact_id'] ) ? (int) $inquiry['contact_id'] : 0;
                                $subscriber = $cid > 0 ? \FluentCrm\App\Models\Subscriber::find( $cid ) : null;
                                $record     = 'inquiry ' . (string) $inquiry['inquiry_id'] . ' / contact ' . $cid;
                                if ( ! $subscriber ) {
                                        $count['not_exportable']++;
                                        $report[] = array( 'record' => $record, 'transaction_id' => isset( $inquiry['transaction_id'] ) ? (string) $inquiry['transaction_id'] : '(none)', 'exportable' => 'no', 'action' => 'not_exportable', 'detail' => 'inquiry-bound FluentCRM contact not found' );
                                        continue;
                                }
                        } else {
                                $subscriber = isset( $item['subscriber'] ) ? $item['subscriber'] : null;
                                if ( ! is_object( $subscriber ) || ! isset( $subscriber->id ) ) {
                                        continue;
                                }
                                $cid    = (int) $subscriber->id;
                                $record = 'contact ' . $cid;
                        }
                        try {
                                $built = $this->build_qualified_lead_import( $subscriber, $config, $time_of_day, $inquiry );
                        } catch ( \Throwable $e ) {
                                $count['not_exportable']++;
                                $report[] = array( 'record' => $record, 'transaction_id' => '(none)', 'exportable' => 'no', 'action' => 'not_exportable', 'detail' => 'builder error: ' . $e->getMessage() );
                                continue;
                        }

                        $txn    = (string) $built['order_id'];
                        $values = (array) $built['values'];
                        if ( ! $built['exportable'] || count( $values ) !== 21 || ! preg_match( '/^ff:\\d+:\\d+$/', $txn ) ) {
                                $count['not_exportable']++;
                                $detail = ! empty( $built['reasons'] ) ? implode( '; ', $built['reasons'] ) : 'failed append-safety assert (21 cols / deterministic ff id)';
                                $report[] = array( 'record' => $record, 'transaction_id' => '' !== $txn ? $txn : '(none)', 'exportable' => 'no', 'action' => 'not_exportable', 'detail' => $detail );
                                continue;
                        }

                        $count['exportable']++;
                        if ( $ledger_exists && $this->ledger_appended_count( $sink, $txn ) > 0 ) {
                                $count['already_appended']++;
                                $report[] = array( 'record' => $record, 'transaction_id' => $txn, 'exportable' => 'yes', 'action' => 'skipped', 'detail' => 'already appended to this stage sink (ledger)' );
                                continue;
                        }

                        $to_append[] = array(
                                'record'   => $record,
                                'cid'      => $cid,
                                'txn'      => $txn,
                                'values'   => $values,
                                'row_hash' => hash( 'sha256', implode( "\x1f", array_map( 'strval', $values ) ) ),
                        );
                }

                $this->complete_family_sheet_batch( 'export-qualified-leads', $sink, $tab, $dry, $config, $report, $count, $to_append );
        }

        /**
         * Compatibility stub for the retired Lost_* worksheet exporter.
         *
         * 0.5.1 models bad leads as the Unqualified lifecycle state and stores the
         * optional reason in customer_rating / All_Leads. Unqualified is not a
         * Google conversion, so no reason-specific worksheet export remains.
         *
         * @subcommand export-lost-leads
         * @alias export_lost_leads
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments (unused).
         * @return void
         */
        public function export_lost_leads( $args, $assoc_args ) {
                unset( $args, $assoc_args );
                WP_CLI::error( 'export-lost-leads was retired in Ads Bridge 0.5.1. Mark the CRM contact Unqualified and store the reason in customer_rating; the outcome is retained in All_Leads and is intentionally not sent to Google as a conversion.' );
        }

        /**
         * Run every enabled export family in operational order: intake All_Leads,
         * lifecycle snapshots, Qualified Lead, then Converted Lead. This is the
         * intended cron entry point.
         *
         * ## OPTIONS
         *
         * [--dry-run]
         * : Run every enabled family without writes.
         *
         * [--apply]
         * : Run every enabled family with explicit write authorization.
         *
         * [--limit=<n>]
         * : Maximum candidates per family/reason. Default: 25.
         *
         * [--time-of-day=<time>]
         * : HH:MM:SS used for date-only conversion dates. Default: 12:00:00.
         *
         * @subcommand export-enabled
         * @alias export_enabled
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function export_enabled( $args, $assoc_args ) {
                $batch       = $this->parse_family_batch_args( $assoc_args, 25, true );
                $mode_args   = $batch['dry'] ? array( 'dry-run' => true ) : array( 'apply' => true );
                $mode_args['limit']       = $batch['limit'];
                $mode_args['time-of-day'] = $batch['time_of_day'];
                $config      = SiteEngine_Ads_Bridge_Config::instance();
                $ran         = 0;

                WP_CLI::line( 'SiteEngine Ads Bridge: export-enabled (' . ( $batch['dry'] ? 'DRY-RUN' : 'APPLY' ) . ')' );
                WP_CLI::line( '============================================================' );
                WP_CLI::line( 'Execution order: All_Leads intake -> lifecycle ledger -> qualified_leads -> converted_leads.' );
                WP_CLI::line( 'Qualified and Converted Lead share Google_Ads_Import with distinct ledger scopes. Unqualified remains internal in All_Leads.' );
                WP_CLI::line( '' );

                if ( (bool) $config->get( 'exports.all_leads.enabled', false ) ) {
                        $ran++;
                        $this->export_all_leads( array(), $mode_args );
                        WP_CLI::line( '' );

                        $ran++;
                        $this->export_lifecycle_ledger( array(), $mode_args );
                        WP_CLI::line( '' );
                }

                if ( (bool) $config->get( 'exports.qualified_leads.enabled', false ) ) {
                        $ran++;
                        $this->export_qualified_leads( array(), $mode_args );
                        WP_CLI::line( '' );
                }

                if ( (bool) $config->get( 'exports.won_jobs.enabled', false ) ) {
                        $ran++;
                        $this->export_batch( array(), $mode_args );
                        WP_CLI::line( '' );
                }

                if ( 0 === $ran ) {
                        WP_CLI::error( 'No export family is enabled in client config.' );
                }

                if ( ! $batch['dry'] ) {
                        $this->record_export_runtime();
                }

                WP_CLI::success( sprintf( 'export-enabled complete; %d enabled family group(s) executed.', $ran ) );
        }

        /**
         * Record the successful aggregate export runtime for admin/doctor cron health.
         * This option is operational telemetry only; it contains no lead or PII data.
         *
         * @return void
         */
        private function record_export_runtime() {
                update_option(
                        'seab_last_export_runtime',
                        array(
                                'timestamp'   => time(),
                                'utc'         => gmdate( 'Y-m-d H:i:s' ),
                                'php_version' => PHP_VERSION,
                                'php_binary'  => PHP_BINARY,
                                'wp_root'     => defined( 'ABSPATH' ) ? untrailingslashit( ABSPATH ) : '',
                                'plugin'      => defined( 'SEAB_VERSION' ) ? SEAB_VERSION : '',
                        ),
                        false
                );
        }

        /**
         * Print the export-batch per-row table and summary counts.
         *
         * @param array $report       Per-row report rows.
         * @param array $count         Summary counters.
         * @param int   $append_total  appended (apply) or would-append (dry-run) total.
         * @param bool  $dry           Dry-run mode.
         * @return void
         */
        private function print_export_batch_summary( $report, $count, $append_total, $dry ) {
                WP_CLI::line( 'Per-row:' );
                if ( empty( $report ) ) {
                        WP_CLI::line( '  (no candidates matched the selector)' );
                } else {
                        WP_CLI\Utils\format_items( 'table', $report, array( 'contact_id', 'transaction_id', 'exportable', 'action', 'detail' ) );
                }

                WP_CLI::line( '' );
                WP_CLI::line( 'Summary:' );
                WP_CLI::line( '  candidates found:         ' . (int) $count['candidates'] );
                WP_CLI::line( '  exportable:               ' . (int) $count['exportable'] );
                WP_CLI::line( '  already-appended skipped: ' . (int) $count['already_appended'] );
                WP_CLI::line( '  ' . ( $dry ? 'would append:             ' : 'appended:                 ' ) . (int) $append_total );
                if ( ! $dry ) {
                        WP_CLI::line( '  failed:                   ' . (int) $count['failed'] );
                }
                WP_CLI::line( '  not exportable:           ' . (int) $count['not_exportable'] );
        }

        /**
         * Fully-qualified ledger table name (plugin-owned exporter bookkeeping).
         *
         * @return string
         */
        private function ledger_table() {
                global $wpdb;
                return $wpdb->prefix . 'se_ads_export_ledger';
        }

        /**
         * Whether the ledger table currently exists. Read-only (used by dry-run).
         *
         * @return bool
         */
        private function ledger_table_exists() {
                global $wpdb;
                $t     = $this->ledger_table();
                $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
                return ( (string) $found === $t );
        }

        /**
         * Create the ledger table if absent, then verify it exists. Idempotent.
         * Never called during dry-run.
         *
         * @return bool True if the table exists after the call.
         */
        private function ensure_ledger_table() {
                global $wpdb;
                if ( $this->ledger_table_exists() ) {
                        return true;
                }
                $t               = $this->ledger_table();
                $charset_collate = $wpdb->get_charset_collate();
                $sql             = "CREATE TABLE IF NOT EXISTS `{$t}` (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        sink_slug VARCHAR(64) NOT NULL DEFAULT '',
                        contact_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        transaction_id VARCHAR(191) NOT NULL DEFAULT '',
                        row_hash CHAR(64) NOT NULL DEFAULT '',
                        spreadsheet_id VARCHAR(191) NOT NULL DEFAULT '',
                        tab VARCHAR(191) NOT NULL DEFAULT '',
                        updated_range VARCHAR(191) NOT NULL DEFAULT '',
                        status VARCHAR(16) NOT NULL DEFAULT '',
                        error TEXT NULL,
                        attempts INT UNSIGNED NOT NULL DEFAULT 0,
                        created_at DATETIME NOT NULL,
                        updated_at DATETIME NOT NULL,
                        PRIMARY KEY (id),
                        KEY sink_txn (sink_slug, transaction_id),
                        KEY sink_status (sink_slug, status)
                ) {$charset_collate};";
                $wpdb->query( $sql );
                return $this->ledger_table_exists();
        }

        /**
         * Count successful appends recorded for a sink + Transaction ID (dedupe key).
         *
         * @param string $sink Sink slug.
         * @param string $txn  Transaction ID.
         * @return int
         */
        private function ledger_appended_count( $sink, $txn ) {
                global $wpdb;
                $t = $this->ledger_table();
                return (int) $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT COUNT(*) FROM `{$t}` WHERE sink_slug = %s AND transaction_id = %s AND status = %s",
                                $sink,
                                $txn,
                                'appended'
                        )
                );
        }

        /**
         * Whether an appended ledger row exists in any supplied sink for one
         * canonical Transaction ID. Used by Converted Lead migration safety so
         * historical 0.4.x Won rows suppress only the 0.5 Converted stage, not
         * the new Qualified stage.
         *
         * @param array  $sinks Ledger sink slugs.
         * @param string $txn   Transaction ID.
         * @return bool
         */
        private function ledger_appended_in_any_sink( $sinks, $txn ) {
                foreach ( array_values( array_unique( array_filter( array_map( 'strval', (array) $sinks ) ) ) ) as $sink ) {
                        if ( $this->ledger_appended_count( $sink, $txn ) > 0 ) {
                                return true;
                        }
                }
                return false;
        }

        /**
         * Count all recorded attempts (any status) for a sink + Transaction ID.
         *
         * @param string $sink Sink slug.
         * @param string $txn  Transaction ID.
         * @return int
         */
        private function ledger_attempt_count( $sink, $txn ) {
                global $wpdb;
                $t = $this->ledger_table();
                return (int) $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT COUNT(*) FROM `{$t}` WHERE sink_slug = %s AND transaction_id = %s",
                                $sink,
                                $txn
                        )
                );
        }

        /**
         * Insert one ledger event row. Append-log; never updates in place.
         *
         * @param array $data Row data.
         * @return array{ok:bool,insert_id:int,error:string} Insert result. ok is true
         *               only when \$wpdb->insert() reported a write AND no DB error was
         *               raised. Success is never keyed off insert_id, which is not reset
         *               on a failed insert and is therefore an unreliable success signal.
         */
        private function ledger_insert( $data ) {
                global $wpdb;
                $now = current_time( 'mysql' );
                $result = $wpdb->insert(
                        $this->ledger_table(),
                        array(
                                'sink_slug'      => (string) $data['sink_slug'],
                                'contact_id'     => (int) $data['contact_id'],
                                'transaction_id' => (string) $data['transaction_id'],
                                'row_hash'       => (string) $data['row_hash'],
                                'spreadsheet_id' => (string) $data['spreadsheet_id'],
                                'tab'            => (string) $data['tab'],
                                'updated_range'  => (string) $data['updated_range'],
                                'status'         => (string) $data['status'],
                                'error'          => (string) $data['error'],
                                'attempts'       => (int) $data['attempts'],
                                'created_at'     => $now,
                                'updated_at'     => $now,
                        ),
                        array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
                );

                // Truth comes from the insert() boolean and last_error, NOT from
                // insert_id: insert_id is not reset on a failed insert, so it can read a
                // stale prior id and falsely look successful.
                $db_error = (string) $wpdb->last_error;
                $ok       = ( false !== $result ) && ( '' === $db_error );

                return array(
                        'ok'        => $ok,
                        'insert_id' => $ok ? (int) $wpdb->insert_id : 0,
                        'error'     => $ok ? '' : ( '' !== $db_error ? $db_error : 'wpdb->insert() returned false with no last_error' ),
                );
        }

        /**
         * Ledger administration: inspect, migrate, reconcile, and duplicate audit for
         * the plugin-owned export ledger.
         *
         * The ledger is append-only exporter bookkeeping. This command NEVER deletes,
         * merges, rewrites, or retroactively edits ledger history; NEVER writes to the
         * Google Sheet; NEVER uploads to Google Ads; and NEVER mutates FluentCRM.
         *
         * OPERATIONS
         *
         * inspect     Read-only. Reports table existence, full column metadata, the
         *             primary key, full indexes, a canonical/noncanonical schema
         *             verdict, total row count, counts by status, counts by sink, and
         *             the oldest and newest created_at values. Rejects --apply.
         *
         * migrate     Default dry-run; writes require --apply. Only two states can be
         *             resolved: an ABSENT table (created through the plugin's existing
         *             canonical creation path, then re-inspected and verified), or an
         *             already-CANONICAL table (no-op, zero writes). ANY noncanonical
         *             schema prints the complete column and index differences and
         *             fails closed, non-zero, performing no ALTER, DROP, UPDATE,
         *             DELETE, or row rewrite. No historical migration is attempted:
         *             no legacy ledger schema has been captured and approved.
         *
         * reconcile   Records one operator-supplied orphan append (a Sheet row that
         *             physically exists but whose ledger insert was never confirmed)
         *             as a single status=appended ledger event. Evidence is supplied
         *             entirely by the operator; the Sheet is never scanned. Default
         *             dry-run; writes require --apply. Idempotent: if an appended row
         *             already exists for the same sink + Transaction ID, it reports
         *             already-reconciled, writes nothing, and exits 0.
         *
         * duplicates  Read-only. Reports every sink + Transaction ID pair holding more
         *             than one status=appended row, with the full matching ledger rows
         *             displayed. Rejects --apply. Nothing is ever deleted or merged.
         *
         * Registered as:
         * wp se-ads ledger-admin <operation> [--apply] [reconcile evidence flags]
         * (alias: wp se-ads ledger_admin)
         *
         * ## OPTIONS
         *
         * <operation>
         * : One of inspect, migrate, reconcile, duplicates.
         *
         * [--apply]
         * : Perform writes. Accepted by migrate and reconcile only; inspect and
         * duplicates reject it.
         *
         * [--sink=<slug>]
         * : reconcile only. Use one configured ledger sink, including stage-specific
         *   google_ads_import_qualified_lead / google_ads_import_converted_lead.
         *
         * [--contact-id=<id>]
         * : reconcile only. Positive integer FluentCRM contact id.
         *
         * [--transaction-id=<txn>]
         * : reconcile only. Deterministic Transaction ID, ff:<form_id>:<entry_id>.
         *
         * [--spreadsheet-id=<id>]
         * : reconcile only. Must byte-match the configured sheets.spreadsheet_id.
         *
         * [--tab=<name>]
         * : reconcile only. Must byte-match the configured
         * sheets.google_ads_import.tab.
         *
         * [--updated-range=<range>]
         * : reconcile only, optional. The Sheets updatedRange reported for the row.
         *
         * [--row-hash=<sha256>]
         * : reconcile only, optional. 64-character hexadecimal SHA-256 of the row;
         * normalized to lowercase.
         *
         * @subcommand ledger-admin
         * @alias ledger_admin
         *
         * @param array $args       Positional arguments; $args[0] is the operation.
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function ledger_admin( $args, $assoc_args ) {
                $op    = isset( $args[0] ) ? (string) $args[0] : '';
                $apply = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );

                $valid = array( 'inspect', 'migrate', 'reconcile', 'duplicates' );

                if ( ! in_array( $op, $valid, true ) ) {
                        WP_CLI::error( sprintf( 'Unknown ledger-admin operation "%s". Valid operations: %s.', $op, implode( ', ', $valid ) ) );
                }

                // inspect and duplicates are read-only by contract. --apply is rejected
                // rather than ignored so an operator can never believe a write happened.
                if ( $apply && ( 'inspect' === $op || 'duplicates' === $op ) ) {
                        WP_CLI::error( sprintf( 'ledger-admin %s is read-only; --apply is not accepted.', $op ) );
                }

                if ( 'inspect' === $op ) {
                        $this->ledger_admin_inspect();
                        return;
                }
                if ( 'migrate' === $op ) {
                        $this->ledger_admin_migrate( $apply );
                        return;
                }
                if ( 'reconcile' === $op ) {
                        $this->ledger_admin_reconcile( $assoc_args, $apply );
                        return;
                }

                $this->ledger_admin_duplicates();
        }

        /**
         * Canonical ledger column contract, in canonical ordinal order.
         *
         * This mirrors ensure_ledger_table() exactly. Defaults are expressed the way
         * SHOW FULL COLUMNS reports them: null means "no default", and a string means
         * that literal default value. Character set and collation are deliberately
         * excluded — they follow wpdb::get_charset_collate() and are site-dependent.
         *
         * @return array
         */
        private function ledger_canonical_columns() {
                return array(
                        array( 'field' => 'id',             'type' => 'bigint unsigned',  'null' => 'NO',  'default' => null, 'extra' => 'auto_increment' ),
                        array( 'field' => 'sink_slug',      'type' => 'varchar(64)',      'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'contact_id',     'type' => 'bigint unsigned',  'null' => 'NO',  'default' => '0',  'extra' => '' ),
                        array( 'field' => 'transaction_id', 'type' => 'varchar(191)',     'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'row_hash',       'type' => 'char(64)',         'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'spreadsheet_id', 'type' => 'varchar(191)',     'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'tab',            'type' => 'varchar(191)',     'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'updated_range',  'type' => 'varchar(191)',     'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'status',         'type' => 'varchar(16)',      'null' => 'NO',  'default' => '',   'extra' => '' ),
                        array( 'field' => 'error',          'type' => 'text',             'null' => 'YES', 'default' => null, 'extra' => '' ),
                        array( 'field' => 'attempts',       'type' => 'int unsigned',     'null' => 'NO',  'default' => '0',  'extra' => '' ),
                        array( 'field' => 'created_at',     'type' => 'datetime',         'null' => 'NO',  'default' => null, 'extra' => '' ),
                        array( 'field' => 'updated_at',     'type' => 'datetime',         'null' => 'NO',  'default' => null, 'extra' => '' ),
                );
        }

        /**
         * Canonical ledger index contract: PRIMARY on id, sink_txn on
         * (sink_slug, transaction_id), sink_status on (sink_slug, status).
         *
         * @return array
         */
        private function ledger_canonical_indexes() {
                return array(
                        'PRIMARY'     => array( 'unique' => true,  'columns' => array( 'id' ) ),
                        'sink_txn'    => array( 'unique' => false, 'columns' => array( 'sink_slug', 'transaction_id' ) ),
                        'sink_status' => array( 'unique' => false, 'columns' => array( 'sink_slug', 'status' ) ),
                );
        }

        /**
         * Ordered ledger column names used for full-row display.
         *
         * @return array
         */
        private function ledger_admin_row_columns() {
                $keys = array();
                foreach ( $this->ledger_canonical_columns() as $col ) {
                        $keys[] = $col['field'];
                }
                return $keys;
        }

        /**
         * Normalize a column type for comparison.
         *
         * Integer display widths are cosmetic and server-version dependent: MySQL
         * 8.0.17+ omits them, while MySQL 5.7 and MariaDB emit them. A byte-identical
         * CREATE TABLE must not be reported as schema drift because of that, so widths
         * are stripped from integer types only. VARCHAR/CHAR lengths are semantic and
         * are preserved.
         *
         * @param string $type Raw Type value from SHOW FULL COLUMNS.
         * @return string
         */
        private function normalize_column_type( $type ) {
                $t = strtolower( trim( (string) $type ) );
                $t = preg_replace( '/\s+/', ' ', $t );
                $t = preg_replace( '/^(tinyint|smallint|mediumint|int|integer|bigint)\(\d+\)/', '$1', $t );
                $t = preg_replace( '/^integer\b/', 'int', $t );
                return (string) $t;
        }

        /**
         * Render a default value the way the reports display it.
         *
         * @param mixed $value Default value, or null for "no default".
         * @return string
         */
        private function ledger_render_default( $value ) {
                return ( null === $value ) ? '(none)' : sprintf( "'%s'", (string) $value );
        }

        /**
         * Whether an observed default matches the canonical default.
         *
         * @param mixed $expected Canonical default (null = no default).
         * @param mixed $actual   Observed default (null = no default).
         * @return bool
         */
        private function ledger_default_matches( $expected, $actual ) {
                if ( null === $expected ) {
                        return ( null === $actual );
                }
                if ( null === $actual ) {
                        return false;
                }
                return ( (string) $expected === (string) $actual );
        }

        /**
         * SHOW FULL COLUMNS for the ledger table. Read-only.
         *
         * @return array
         */
        private function ledger_show_full_columns() {
                global $wpdb;
                $t = $this->ledger_table();
                if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $t ) ) {
                        return array();
                }
                $rows = $wpdb->get_results( "SHOW FULL COLUMNS FROM `{$t}`", ARRAY_A );
                return is_array( $rows ) ? $rows : array();
        }

        /**
         * SHOW INDEX for the ledger table. Read-only.
         *
         * @return array
         */
        private function ledger_show_index() {
                global $wpdb;
                $t = $this->ledger_table();
                if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $t ) ) {
                        return array();
                }
                $rows = $wpdb->get_results( "SHOW INDEX FROM `{$t}`", ARRAY_A );
                return is_array( $rows ) ? $rows : array();
        }

        /**
         * Collapse SHOW INDEX rows into name => {unique, ordered columns}.
         *
         * @return array
         */
        private function ledger_index_map() {
                $map = array();
                $seq = array();

                foreach ( $this->ledger_show_index() as $r ) {
                        $name = isset( $r['Key_name'] ) ? (string) $r['Key_name'] : '';
                        if ( '' === $name ) {
                                continue;
                        }
                        if ( ! isset( $map[ $name ] ) ) {
                                $map[ $name ] = array(
                                        'unique'  => ( isset( $r['Non_unique'] ) && 0 === (int) $r['Non_unique'] ),
                                        'columns' => array(),
                                );
                                $seq[ $name ] = array();
                        }
                        $pos                  = isset( $r['Seq_in_index'] ) ? (int) $r['Seq_in_index'] : ( count( $seq[ $name ] ) + 1 );
                        $seq[ $name ][ $pos ] = isset( $r['Column_name'] ) ? (string) $r['Column_name'] : '';
                }

                foreach ( $seq as $name => $cols ) {
                        ksort( $cols, SORT_NUMERIC );
                        $map[ $name ]['columns'] = array_values( $cols );
                }

                return $map;
        }

        /**
         * Complete list of differences between the live ledger table and the canonical
         * schema. An empty array means canonical.
         *
         * Comparison is deliberately deeper than column names: it covers types,
         * nullability, defaults, extra/auto_increment, canonical column order, the
         * PRIMARY key, and both secondary indexes, in both directions (missing and
         * unexpected). Character set and collation are excluded by design.
         *
         * @return array List of human-readable difference strings.
         */
        private function ledger_schema_differences() {
                $diffs = array();
                $spec  = $this->ledger_canonical_columns();

                $actual_by_name = array();
                $actual_order   = array();
                foreach ( $this->ledger_show_full_columns() as $row ) {
                        $name                    = isset( $row['Field'] ) ? (string) $row['Field'] : '';
                        $actual_by_name[ $name ] = $row;
                        $actual_order[]          = $name;
                }

                $spec_order = array();
                foreach ( $spec as $col ) {
                        $spec_order[] = $col['field'];
                }

                foreach ( $spec as $col ) {
                        $name = $col['field'];

                        if ( ! isset( $actual_by_name[ $name ] ) ) {
                                $diffs[] = sprintf(
                                        'column MISSING: %s (expected type %s, Null=%s, default %s, extra "%s")',
                                        $name,
                                        $col['type'],
                                        $col['null'],
                                        $this->ledger_render_default( $col['default'] ),
                                        $col['extra']
                                );
                                continue;
                        }

                        $a = $actual_by_name[ $name ];

                        $found_type    = $this->normalize_column_type( isset( $a['Type'] ) ? $a['Type'] : '' );
                        $expected_type = $this->normalize_column_type( $col['type'] );
                        if ( $found_type !== $expected_type ) {
                                $diffs[] = sprintf( 'column %s TYPE: expected "%s", found "%s"', $name, $expected_type, $found_type );
                        }

                        $found_null = strtoupper( trim( (string) ( isset( $a['Null'] ) ? $a['Null'] : '' ) ) );
                        if ( $found_null !== $col['null'] ) {
                                $diffs[] = sprintf( 'column %s NULLABILITY: expected Null=%s, found Null=%s', $name, $col['null'], '' === $found_null ? '(unknown)' : $found_null );
                        }

                        $found_default = array_key_exists( 'Default', $a ) ? $a['Default'] : null;
                        if ( ! $this->ledger_default_matches( $col['default'], $found_default ) ) {
                                $diffs[] = sprintf(
                                        'column %s DEFAULT: expected %s, found %s',
                                        $name,
                                        $this->ledger_render_default( $col['default'] ),
                                        $this->ledger_render_default( $found_default )
                                );
                        }

                        $found_extra = strtolower( trim( (string) ( isset( $a['Extra'] ) ? $a['Extra'] : '' ) ) );
                        if ( $found_extra !== strtolower( $col['extra'] ) ) {
                                $diffs[] = sprintf( 'column %s EXTRA: expected "%s", found "%s"', $name, $col['extra'], $found_extra );
                        }
                }

                foreach ( $actual_order as $name ) {
                        if ( ! in_array( $name, $spec_order, true ) ) {
                                $diffs[] = sprintf( 'column UNEXPECTED: %s (not part of the canonical ledger schema)', $name );
                        }
                }

                // Ordinal position is only meaningful once both sides hold the same set;
                // otherwise the missing/unexpected entries above already explain it.
                $sorted_actual = $actual_order;
                $sorted_spec   = $spec_order;
                sort( $sorted_actual );
                sort( $sorted_spec );
                if ( $sorted_actual === $sorted_spec && $actual_order !== $spec_order ) {
                        $diffs[] = sprintf( 'column ORDER: expected [%s], found [%s]', implode( ', ', $spec_order ), implode( ', ', $actual_order ) );
                }

                $actual_idx = $this->ledger_index_map();
                $spec_idx   = $this->ledger_canonical_indexes();

                foreach ( $spec_idx as $key => $def ) {
                        if ( ! isset( $actual_idx[ $key ] ) ) {
                                $diffs[] = sprintf( 'index MISSING: %s (%s on [%s])', $key, $def['unique'] ? 'unique' : 'non-unique', implode( ', ', $def['columns'] ) );
                                continue;
                        }
                        if ( $actual_idx[ $key ]['columns'] !== $def['columns'] ) {
                                $diffs[] = sprintf(
                                        'index %s COLUMNS: expected [%s], found [%s]',
                                        $key,
                                        implode( ', ', $def['columns'] ),
                                        implode( ', ', $actual_idx[ $key ]['columns'] )
                                );
                        }
                        if ( $actual_idx[ $key ]['unique'] !== $def['unique'] ) {
                                $diffs[] = sprintf(
                                        'index %s UNIQUENESS: expected %s, found %s',
                                        $key,
                                        $def['unique'] ? 'unique' : 'non-unique',
                                        $actual_idx[ $key ]['unique'] ? 'unique' : 'non-unique'
                                );
                        }
                }

                foreach ( $actual_idx as $key => $def ) {
                        if ( ! isset( $spec_idx[ $key ] ) ) {
                                $diffs[] = sprintf( 'index UNEXPECTED: %s on [%s]', $key, implode( ', ', $def['columns'] ) );
                        }
                }

                return $diffs;
        }

        /**
         * Print the canonical column and index contract (used by migrate dry-run).
         *
         * @return void
         */
        private function ledger_print_canonical_spec() {
                $rows = array();
                foreach ( $this->ledger_canonical_columns() as $col ) {
                        $rows[] = array(
                                'field'   => $col['field'],
                                'type'    => $col['type'],
                                'null'    => $col['null'],
                                'default' => $this->ledger_render_default( $col['default'] ),
                                'extra'   => '' === $col['extra'] ? '(none)' : $col['extra'],
                        );
                }
                WP_CLI::line( '' );
                WP_CLI::line( 'Canonical columns that would be created:' );
                WP_CLI\Utils\format_items( 'table', $rows, array( 'field', 'type', 'null', 'default', 'extra' ) );

                $irows = array();
                foreach ( $this->ledger_canonical_indexes() as $name => $def ) {
                        $irows[] = array(
                                'key_name' => $name,
                                'unique'   => $def['unique'] ? 'yes' : 'no',
                                'columns'  => implode( ', ', $def['columns'] ),
                        );
                }
                WP_CLI::line( '' );
                WP_CLI::line( 'Canonical indexes that would be created:' );
                WP_CLI\Utils\format_items( 'table', $irows, array( 'key_name', 'unique', 'columns' ) );
        }

        /**
         * Print the schema verdict and, when noncanonical, every difference.
         *
         * @param array $diffs Difference strings from ledger_schema_differences().
         * @return void
         */
        private function ledger_print_schema_verdict( $diffs ) {
                if ( empty( $diffs ) ) {
                        WP_CLI::line( 'Schema verdict: CANONICAL' );
                        WP_CLI::line( '  Columns, types, nullability, defaults, extra/auto_increment, column order, PRIMARY(id), sink_txn(sink_slug, transaction_id) and sink_status(sink_slug, status) all match.' );
                } else {
                        WP_CLI::line( 'Schema verdict: NONCANONICAL' );
                        foreach ( $diffs as $d ) {
                                WP_CLI::line( '  - ' . $d );
                        }
                }
                WP_CLI::line( 'Note: character set and collation follow wpdb::get_charset_collate() and are NOT part of this comparison.' );
        }

        /**
         * Fetch full ledger rows for a sink + Transaction ID at a given status.
         *
         * @param string $sink   Sink slug.
         * @param string $txn    Transaction ID.
         * @param string $status Ledger status.
         * @return array
         */
        private function ledger_admin_rows_for( $sink, $txn, $status ) {
                global $wpdb;
                $t    = $this->ledger_table();
                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT * FROM `{$t}` WHERE sink_slug = %s AND transaction_id = %s AND status = %s ORDER BY id ASC",
                                $sink,
                                $txn,
                                $status
                        ),
                        ARRAY_A
                );
                return is_array( $rows ) ? $rows : array();
        }

        /**
         * Print full ledger rows in canonical column order.
         *
         * @param array $rows Associative ledger rows.
         * @return void
         */
        private function ledger_admin_print_rows( $rows ) {
                $keys = $this->ledger_admin_row_columns();
                $disp = array();

                foreach ( $rows as $r ) {
                        $one = array();
                        foreach ( $keys as $k ) {
                                $v           = array_key_exists( $k, $r ) ? $r[ $k ] : '';
                                $one[ $k ] = ( null === $v || '' === (string) $v ) ? '(empty)' : (string) $v;
                        }
                        $disp[] = $one;
                }

                WP_CLI\Utils\format_items( 'table', $disp, $keys );
        }

        /**
         * ledger-admin inspect. Always read-only; writes nothing under any condition.
         *
         * @return void
         */
        private function ledger_admin_inspect() {
                global $wpdb;
                $t = $this->ledger_table();

                WP_CLI::line( 'SiteEngine Ads Bridge: ledger-admin inspect (READ-ONLY)' );
                WP_CLI::line( '------------------------------------------------------------' );
                WP_CLI::line( 'Ledger table: ' . $t );

                if ( ! $this->ledger_table_exists() ) {
                        WP_CLI::line( 'Table exists: NO' );
                        WP_CLI::line( '' );
                        WP_CLI::line( 'No columns, indexes, or rows to report. To create the canonical table:' );
                        WP_CLI::line( '  wp se-ads ledger-admin migrate --apply' );
                        WP_CLI::success( 'Inspect complete (table absent). Nothing was written.' );
                        return;
                }

                WP_CLI::line( 'Table exists: YES' );
                WP_CLI::line( '' );

                $cols = $this->ledger_show_full_columns();
                WP_CLI::line( 'Columns (SHOW FULL COLUMNS):' );
                if ( empty( $cols ) ) {
                        WP_CLI::line( '  (no column metadata returned)' );
                } else {
                        $crows = array();
                        foreach ( $cols as $c ) {
                                $collation = ( isset( $c['Collation'] ) && null !== $c['Collation'] && '' !== (string) $c['Collation'] ) ? (string) $c['Collation'] : '(none)';
                                $crows[]   = array(
                                        'field'     => isset( $c['Field'] ) ? (string) $c['Field'] : '',
                                        'type'      => isset( $c['Type'] ) ? (string) $c['Type'] : '',
                                        'collation' => $collation,
                                        'null'      => isset( $c['Null'] ) ? (string) $c['Null'] : '',
                                        'key'       => ( isset( $c['Key'] ) && '' !== (string) $c['Key'] ) ? (string) $c['Key'] : '(none)',
                                        'default'   => $this->ledger_render_default( array_key_exists( 'Default', $c ) ? $c['Default'] : null ),
                                        'extra'     => ( isset( $c['Extra'] ) && '' !== (string) $c['Extra'] ) ? (string) $c['Extra'] : '(none)',
                                );
                        }
                        WP_CLI\Utils\format_items( 'table', $crows, array( 'field', 'type', 'collation', 'null', 'key', 'default', 'extra' ) );
                }

                $idx_map = $this->ledger_index_map();
                WP_CLI::line( '' );
                WP_CLI::line( 'Primary key: ' . ( isset( $idx_map['PRIMARY'] ) ? implode( ', ', $idx_map['PRIMARY']['columns'] ) : '(none)' ) );

                WP_CLI::line( '' );
                WP_CLI::line( 'Indexes (SHOW INDEX):' );
                $raw_idx = $this->ledger_show_index();
                if ( empty( $raw_idx ) ) {
                        WP_CLI::line( '  (no indexes reported)' );
                } else {
                        $irows = array();
                        foreach ( $raw_idx as $r ) {
                                $irows[] = array(
                                        'key_name'     => isset( $r['Key_name'] ) ? (string) $r['Key_name'] : '',
                                        'seq_in_index' => isset( $r['Seq_in_index'] ) ? (string) $r['Seq_in_index'] : '',
                                        'column_name'  => isset( $r['Column_name'] ) ? (string) $r['Column_name'] : '',
                                        'non_unique'   => isset( $r['Non_unique'] ) ? (string) $r['Non_unique'] : '',
                                        'index_type'   => isset( $r['Index_type'] ) ? (string) $r['Index_type'] : '',
                                        'nullable'     => ( isset( $r['Null'] ) && '' !== (string) $r['Null'] ) ? (string) $r['Null'] : '(no)',
                                );
                        }
                        WP_CLI\Utils\format_items( 'table', $irows, array( 'key_name', 'seq_in_index', 'column_name', 'non_unique', 'index_type', 'nullable' ) );
                }

                WP_CLI::line( '' );
                $this->ledger_print_schema_verdict( $this->ledger_schema_differences() );

                $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" );
                WP_CLI::line( '' );
                WP_CLI::line( 'Total rows: ' . $total );

                WP_CLI::line( '' );
                WP_CLI::line( 'Rows by status:' );
                $by_status = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM `{$t}` GROUP BY status ORDER BY status ASC", ARRAY_A );
                if ( ! is_array( $by_status ) || empty( $by_status ) ) {
                        WP_CLI::line( '  (no rows)' );
                } else {
                        $srows = array();
                        foreach ( $by_status as $r ) {
                                $srows[] = array(
                                        'status' => ( '' === (string) $r['status'] ) ? '(empty)' : (string) $r['status'],
                                        'rows'   => (string) (int) $r['c'],
                                );
                        }
                        WP_CLI\Utils\format_items( 'table', $srows, array( 'status', 'rows' ) );
                }

                WP_CLI::line( '' );
                WP_CLI::line( 'Rows by sink:' );
                $by_sink = $wpdb->get_results( "SELECT sink_slug, COUNT(*) AS c FROM `{$t}` GROUP BY sink_slug ORDER BY sink_slug ASC", ARRAY_A );
                if ( ! is_array( $by_sink ) || empty( $by_sink ) ) {
                        WP_CLI::line( '  (no rows)' );
                } else {
                        $krows = array();
                        foreach ( $by_sink as $r ) {
                                $krows[] = array(
                                        'sink_slug' => ( '' === (string) $r['sink_slug'] ) ? '(empty)' : (string) $r['sink_slug'],
                                        'rows'      => (string) (int) $r['c'],
                                );
                        }
                        WP_CLI\Utils\format_items( 'table', $krows, array( 'sink_slug', 'rows' ) );
                }

                $oldest = $wpdb->get_var( "SELECT MIN(created_at) FROM `{$t}`" );
                $newest = $wpdb->get_var( "SELECT MAX(created_at) FROM `{$t}`" );
                WP_CLI::line( '' );
                WP_CLI::line( 'Oldest created_at: ' . ( ( null === $oldest || '' === (string) $oldest ) ? '(none)' : (string) $oldest ) );
                WP_CLI::line( 'Newest created_at: ' . ( ( null === $newest || '' === (string) $newest ) ? '(none)' : (string) $newest ) );

                WP_CLI::line( '' );
                WP_CLI::success( 'Inspect complete. Read-only; nothing was written.' );
        }

        /**
         * ledger-admin migrate. Dry-run by default; --apply may only create an absent
         * canonical table. Any noncanonical schema fails closed with zero writes.
         *
         * @param bool $apply Whether writes are authorized.
         * @return void
         */
        private function ledger_admin_migrate( $apply ) {
                $t = $this->ledger_table();

                WP_CLI::line( 'SiteEngine Ads Bridge: ledger-admin migrate (' . ( $apply ? 'APPLY' : 'DRY-RUN' ) . ')' );
                WP_CLI::line( '------------------------------------------------------------' );
                WP_CLI::line( 'Ledger table: ' . $t );
                WP_CLI::line( 'This command never performs ALTER, DROP, UPDATE, DELETE, or row rewrite. Ledger history is append-only.' );
                WP_CLI::line( '' );

                if ( ! $this->ledger_table_exists() ) {
                        WP_CLI::line( 'Case: ledger table ABSENT.' );

                        if ( ! $apply ) {
                                WP_CLI::line( 'Dry-run: the canonical ledger table WOULD be created through the plugin\'s existing canonical creation path (the same path export-batch --apply uses).' );
                                $this->ledger_print_canonical_spec();
                                WP_CLI::line( '' );
                                WP_CLI::success( 'Dry-run complete. No table created, no row written.' );
                                return;
                        }

                        if ( ! $this->ensure_ledger_table() ) {
                                WP_CLI::error( sprintf( 'Could not create ledger table %s. Nothing was written.', $t ) );
                        }

                        WP_CLI::line( 'Created the ledger table through the canonical creation path. Re-inspecting...' );
                        WP_CLI::line( '' );

                        $post_diffs = $this->ledger_schema_differences();
                        $this->ledger_print_schema_verdict( $post_diffs );

                        if ( ! empty( $post_diffs ) ) {
                                WP_CLI::error( 'Ledger table was created but does NOT verify as the complete canonical schema. Failing closed; no further action taken and no rows were written.' );
                        }

                        WP_CLI::line( '' );
                        WP_CLI::success( 'Ledger table created and verified as the complete canonical schema. 0 ledger rows written.' );
                        return;
                }

                WP_CLI::line( 'Case: ledger table PRESENT.' );
                WP_CLI::line( '' );

                $diffs = $this->ledger_schema_differences();
                $this->ledger_print_schema_verdict( $diffs );

                if ( empty( $diffs ) ) {
                        WP_CLI::line( '' );
                        WP_CLI::success( 'No-op: ledger schema is already canonical. 0 writes performed.' );
                        return;
                }

                WP_CLI::line( '' );
                WP_CLI::line( 'No legacy ledger schema has been captured and approved, so no historical migration is attempted.' );
                WP_CLI::line( 'Nothing was altered, dropped, updated, deleted, or rewritten.' );
                WP_CLI::error( 'Ledger schema is noncanonical; failing closed. Capture and approve an explicit migration before exporting against this table.' );
        }

        /**
         * Configured ledger sink registry used by reconciliation validation.
         *
         * @param object $config Config instance.
         * @return array
         */
        private function ledger_sink_registry( $config ) {
                $registry = array();

                $won_tab = trim( (string) $config->get( 'exports.won_jobs.sheet_tab', '' ) );
                if ( '' !== $won_tab ) {
                        $registry['google_ads_import_qualified_lead'] = array(
                                'label'          => 'qualified_leads',
                                'tabs'           => array( $won_tab ),
                                'min_contact_id' => 1,
                        );
                        $registry['google_ads_import_converted_lead'] = array(
                                'label'          => 'converted_leads',
                                'tabs'           => array( $won_tab ),
                                'min_contact_id' => 1,
                        );
                        // 0.4.x compatibility: historical rows used one shared sink.
                        // Keep it recognizable for inspect/reconcile, but 0.5.0 exports
                        // never write new rows under this legacy slug.
                        $registry['google_ads_import_sheet'] = array(
                                'label'          => 'legacy_google_ads_import',
                                'tabs'           => array( $won_tab ),
                                'min_contact_id' => 1,
                        );
                }

                $all_tab = trim( (string) $config->get( 'exports.all_leads.sheet_tab', '' ) );
                if ( '' !== $all_tab ) {
                        $registry['all_leads_sheet'] = array(
                                'label'          => 'all_leads',
                                'tabs'           => array( $all_tab ),
                                'min_contact_id' => 0,
                        );
                        $registry['all_leads_lifecycle_qualified'] = array(
                                'label'          => 'all_leads_qualified_snapshot',
                                'tabs'           => array( $all_tab ),
                                'min_contact_id' => 1,
                        );
                        $registry['all_leads_lifecycle_unqualified'] = array(
                                'label'          => 'all_leads_unqualified_snapshot',
                                'tabs'           => array( $all_tab ),
                                'min_contact_id' => 1,
                        );
                        $registry['all_leads_lifecycle_won'] = array(
                                'label'          => 'all_leads_won_snapshot',
                                'tabs'           => array( $all_tab ),
                                'min_contact_id' => 1,
                        );
                }

                $lost_tabs = array();
                foreach ( SiteEngine_Ads_Bridge_Config::lost_reasons() as $reason ) {
                        $tab = trim( (string) $config->get( 'exports.lost_leads.reasons.' . $reason . '.sheet_tab', '' ) );
                        if ( '' !== $tab ) {
                                $lost_tabs[] = $tab;
                        }
                }
                $lost_tabs = array_values( array_unique( $lost_tabs ) );
                if ( ! empty( $lost_tabs ) ) {
                        $registry['lost_leads_sheet'] = array(
                                'label'          => 'lost_leads',
                                'tabs'           => $lost_tabs,
                                'min_contact_id' => 1,
                        );
                }

                return $registry;
        }

        /**
         * ledger-admin reconcile. Records one operator-supplied orphan append as a
         * single status=appended ledger event.
         *
         * The Sheet is never scanned: every piece of evidence is supplied by the
         * operator and validated against configuration before any write. Reconciliation
         * provenance is stated in console output only — it is deliberately NOT written
         * into the error column, which stays empty so the row is indistinguishable in
         * shape from a normal successful append and dedupe behaves identically.
         *
         * @param array $assoc_args Associative arguments.
         * @param bool  $apply      Whether writes are authorized.
         * @return void
         */
        private function ledger_admin_reconcile( $assoc_args, $apply ) {
                $config   = SiteEngine_Ads_Bridge_Config::instance();
                $registry = $this->ledger_sink_registry( $config );

                WP_CLI::line( 'SiteEngine Ads Bridge: ledger-admin reconcile (' . ( $apply ? 'APPLY' : 'DRY-RUN' ) . ')' );
                WP_CLI::line( '------------------------------------------------------------' );
                WP_CLI::line( 'Operator-supplied orphan evidence only. The Google Sheet is NOT read or scanned.' );
                WP_CLI::line( 'Append-only: no existing ledger row is edited, merged, or removed.' );
                WP_CLI::line( '' );

                $sink     = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'sink', '' );
                $cid_raw  = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'contact-id', '' );
                $txn      = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'transaction-id', '' );
                $sheet_in = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'spreadsheet-id', '' );
                $tab_in   = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'tab', '' );
                $range_in = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'updated-range', '' );
                $hash_in  = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'row-hash', '' );

                $sheet_cfg = trim( (string) $config->get( 'google_sheets.spreadsheet_id', '' ) );
                $sink_cfg  = isset( $registry[ $sink ] ) ? $registry[ $sink ] : null;
                $tabs_cfg  = is_array( $sink_cfg ) ? (array) $sink_cfg['tabs'] : array();

                $problems = array();

                if ( '' === $sink ) {
                        $problems[] = '--sink is required.';
                } elseif ( ! isset( $registry[ $sink ] ) ) {
                        $problems[] = sprintf( '--sink "%s" is not configured. Valid sinks: %s.', $sink, implode( ', ', array_keys( $registry ) ) );
                }

                if ( '' === $cid_raw ) {
                        $problems[] = '--contact-id is required (use 0 only for an All_Leads row with no CRM match).';
                } elseif ( ! ctype_digit( $cid_raw ) ) {
                        $problems[] = sprintf( '--contact-id "%s" must be a non-negative integer.', $cid_raw );
                } elseif ( is_array( $sink_cfg ) && (int) $cid_raw < (int) $sink_cfg['min_contact_id'] ) {
                        $problems[] = sprintf( '--contact-id must be at least %d for sink "%s".', (int) $sink_cfg['min_contact_id'], $sink );
                }

                if ( '' === $txn ) {
                        $problems[] = '--transaction-id is required.';
                } elseif ( ! preg_match( '/^ff:\d+:\d+$/', $txn ) ) {
                        $problems[] = sprintf( '--transaction-id "%s" must match ff:<form_id>:<entry_id>.', $txn );
                }

                if ( '' === $sheet_cfg ) {
                        $problems[] = 'sheets.spreadsheet_id is not configured; --spreadsheet-id cannot be verified.';
                }
                if ( is_array( $sink_cfg ) && empty( $tabs_cfg ) ) {
                        $problems[] = 'The selected sink has no configured tab; --tab cannot be verified.';
                }

                if ( '' === $sheet_in ) {
                        $problems[] = '--spreadsheet-id is required.';
                } elseif ( '' !== $sheet_cfg && $sheet_in !== $sheet_cfg ) {
                        $problems[] = sprintf( '--spreadsheet-id does not byte-match the configured sheets.spreadsheet_id ("%s").', $sheet_cfg );
                }

                if ( '' === $tab_in ) {
                        $problems[] = '--tab is required.';
                } elseif ( ! empty( $tabs_cfg ) && ! in_array( $tab_in, $tabs_cfg, true ) ) {
                        $problems[] = sprintf( '--tab "%s" is not valid for sink "%s". Configured tab(s): %s.', $tab_in, $sink, implode( ', ', $tabs_cfg ) );
                }

                $row_hash = '';
                if ( '' !== $hash_in ) {
                        if ( ! preg_match( '/^[0-9a-fA-F]{64}$/', $hash_in ) ) {
                                $problems[] = sprintf( '--row-hash "%s" must be exactly 64 hexadecimal characters (SHA-256).', $hash_in );
                        } else {
                                $row_hash = strtolower( $hash_in );
                        }
                }

                if ( ! empty( $problems ) ) {
                        foreach ( $problems as $p ) {
                                WP_CLI::line( 'Invalid evidence: ' . $p );
                        }
                        WP_CLI::error( 'Reconciliation evidence is missing or invalid; failing closed before any write. Nothing was written.' );
                }

                if ( ! $this->ledger_table_exists() ) {
                        WP_CLI::error( sprintf( 'Ledger table %s does not exist; there is nothing to reconcile into. Run "wp se-ads ledger-admin migrate --apply" first. Nothing was written.', $this->ledger_table() ) );
                }

                $cid = (int) $cid_raw;

                WP_CLI::line( sprintf( 'Sink: %s', $sink ) );
                WP_CLI::line( sprintf( 'Contact: %d', $cid ) );
                WP_CLI::line( sprintf( 'Transaction ID: %s', $txn ) );
                WP_CLI::line( sprintf( 'Spreadsheet: %s (matches configured value)', $sheet_in ) );
                WP_CLI::line( sprintf( 'Tab: %s (valid for configured sink family %s)', $tab_in, $sink_cfg['label'] ) );
                WP_CLI::line( '' );

                $existing_failed = $this->ledger_admin_rows_for( $sink, $txn, 'failed' );
                if ( ! empty( $existing_failed ) ) {
                        WP_CLI::line( sprintf( 'Context: %d prior failed attempt(s) recorded for this sink + Transaction ID. Shown for history only; failed rows do not block reconciliation.', count( $existing_failed ) ) );
                        $this->ledger_admin_print_rows( $existing_failed );
                        WP_CLI::line( '' );
                }

                $existing_appended = $this->ledger_admin_rows_for( $sink, $txn, 'appended' );
                if ( ! empty( $existing_appended ) ) {
                        WP_CLI::line( sprintf( 'Already reconciled: %d status=appended ledger row(s) already exist for %s + %s.', count( $existing_appended ), $sink, $txn ) );
                        $this->ledger_admin_print_rows( $existing_appended );
                        WP_CLI::line( '' );
                        WP_CLI::line( 'Ledger dedupe already covers this Transaction ID. Nothing written.' );
                        WP_CLI::success( 'Already reconciled; 0 ledger rows written.' );
                        return;
                }

                $attempt_no = $this->ledger_attempt_count( $sink, $txn ) + 1;

                $row = array(
                        'sink_slug'      => $sink,
                        'contact_id'     => $cid,
                        'transaction_id' => $txn,
                        'row_hash'       => $row_hash,
                        'spreadsheet_id' => $sheet_in,
                        'tab'            => $tab_in,
                        'updated_range'  => $range_in,
                        'status'         => 'appended',
                        'error'          => '',
                        'attempts'       => $attempt_no,
                );

                $disp = array();
                foreach ( $row as $k => $v ) {
                        $disp[] = array(
                                'column' => $k,
                                'value'  => ( '' === (string) $v ) ? '(empty)' : (string) $v,
                        );
                }
                $disp[] = array( 'column' => 'created_at', 'value' => '(current_time( \'mysql\' ) at insert)' );
                $disp[] = array( 'column' => 'updated_at', 'value' => '(current_time( \'mysql\' ) at insert)' );

                WP_CLI::line( 'Exact ledger row to be inserted:' );
                WP_CLI\Utils\format_items( 'table', $disp, array( 'column', 'value' ) );
                WP_CLI::line( '' );
                WP_CLI::line( 'Provenance: this is an OPERATOR RECONCILIATION of a Sheet row that was appended without a confirmed ledger insert.' );
                WP_CLI::line( 'That provenance is recorded in this console output only. The ledger error column is left empty and no existing row is modified.' );

                if ( ! $apply ) {
                        WP_CLI::line( '' );
                        WP_CLI::success( 'Dry-run complete. No ledger row written. Re-run with --apply to record it.' );
                        return;
                }

                $led = $this->ledger_insert( $row );
                if ( ! $led['ok'] ) {
                        WP_CLI::error( 'Ledger insert failed: ' . $led['error'] . '. Nothing was reconciled.' );
                }

                $verify = $this->ledger_appended_count( $sink, $txn );
                if ( $verify < 1 ) {
                        WP_CLI::error( sprintf( 'Ledger insert reported success but verification found %d appended row(s) for %s + %s. Failing closed.', (int) $verify, $sink, $txn ) );
                }

                WP_CLI::line( '' );
                WP_CLI::line( sprintf( 'Verification: appended-row count for %s + %s is now %d.', $sink, $txn, (int) $verify ) );
                WP_CLI::line( 'Google Ads upload DISABLED. No Sheet write. No CRM mutation. Ledger history unchanged apart from this one appended event.' );
                WP_CLI::success( sprintf( 'Reconciled: 1 status=appended ledger event recorded for contact %d (%s).', $cid, $txn ) );
        }

        /**
         * ledger-admin duplicates. Always read-only.
         *
         * Reports every sink + Transaction ID pair holding more than one
         * status=appended row — the dedupe key that export-batch relies on — and prints
         * the full matching rows. Nothing is ever deleted, merged, or rewritten;
         * remediation is an operator decision made outside this plugin.
         *
         * @return void
         */
        private function ledger_admin_duplicates() {
                global $wpdb;
                $t = $this->ledger_table();

                WP_CLI::line( 'SiteEngine Ads Bridge: ledger-admin duplicates (READ-ONLY)' );
                WP_CLI::line( '------------------------------------------------------------' );
                WP_CLI::line( 'Ledger table: ' . $t );
                WP_CLI::line( 'Audit only. No ledger row is ever deleted, merged, or rewritten.' );
                WP_CLI::line( '' );

                if ( ! $this->ledger_table_exists() ) {
                        WP_CLI::error( sprintf( 'Ledger table %s does not exist; there is nothing to audit. Nothing was written.', $t ) );
                }

                $pairs = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT sink_slug, transaction_id, COUNT(*) AS c FROM `{$t}` WHERE status = %s GROUP BY sink_slug, transaction_id HAVING COUNT(*) > 1 ORDER BY c DESC, sink_slug ASC, transaction_id ASC",
                                'appended'
                        ),
                        ARRAY_A
                );

                if ( ! is_array( $pairs ) || empty( $pairs ) ) {
                        WP_CLI::line( 'No sink + Transaction ID pair holds more than one status=appended row.' );
                        WP_CLI::line( '' );
                        WP_CLI::success( 'Duplicate audit complete; 0 duplicated dedupe keys found. Read-only; nothing was written.' );
                        return;
                }

                $summary   = array();
                $extra_sum = 0;
                foreach ( $pairs as $p ) {
                        $c           = (int) $p['c'];
                        $extra_sum  += ( $c - 1 );
                        $summary[]   = array(
                                'sink_slug'      => (string) $p['sink_slug'],
                                'transaction_id' => (string) $p['transaction_id'],
                                'appended_rows'  => (string) $c,
                        );
                }

                WP_CLI::line( sprintf( '%d duplicated dedupe key(s) found (%d surplus appended row(s) beyond one per key):', count( $pairs ), $extra_sum ) );
                WP_CLI\Utils\format_items( 'table', $summary, array( 'sink_slug', 'transaction_id', 'appended_rows' ) );

                foreach ( $pairs as $p ) {
                        $sink = (string) $p['sink_slug'];
                        $txn  = (string) $p['transaction_id'];
                        WP_CLI::line( '' );
                        WP_CLI::line( sprintf( 'Full appended rows for %s + %s:', $sink, $txn ) );
                        $this->ledger_admin_print_rows( $this->ledger_admin_rows_for( $sink, $txn, 'appended' ) );
                }

                WP_CLI::line( '' );
                WP_CLI::warning( sprintf( '%d duplicated dedupe key(s) present. A Transaction ID appended more than once may have been double-counted downstream; investigate the Sheet and Google Ads Data Manager before changing anything.', count( $pairs ) ) );
                WP_CLI::success( 'Duplicate audit complete. Read-only; nothing was written.' );
        }

        /**
         * Assemble the exact 21-field Google_Ads_Import (Data Manager) Converted Lead row and the
         * exportability verdict for a contact (read-only; no printing, no writes).
         *
         * This is the single source of truth shared by export-dry-run (preview) and
         * append-row (real append). It performs the same normalization, plugin-side
         * SHA-256 hex hashing policy, deterministic Order ID resolution, consent reads,
         * conversion-time derivation, and exportability gating. It returns both the
         * ordered field spec (internal key, default Google header, raw value) and a
         * flat 21-element values array in the contract's column order, plus the
         * intermediate diagnostics the dry-run prints.
         *
         * @param object $subscriber  FluentCRM subscriber model.
         * @param object $config       Config singleton instance.
         * @param string $time_of_day  Validated HH:MM:SS.
         * @return array
         */
        private function build_google_ads_import( $subscriber, $config, $time_of_day, $inquiry = null ) {
                $email   = (string) $subscriber->email;
                $phone   = (string) $subscriber->phone;
                $first   = (string) $subscriber->first_name;
                $last    = (string) $subscriber->last_name;
                $ip      = (string) $subscriber->ip;
                $city    = (string) $subscriber->city;
                $postal  = (string) $subscriber->postal_code;
                $state   = (string) $subscriber->state;
                $country = (string) $subscriber->country;

                $state_norm   = $this->normalize_us_state( $state );
                $country_norm = $this->normalize_country( $country );

                $user_agent = '';

                $custom = array();
                if ( method_exists( $subscriber, 'custom_fields' ) ) {
                        $custom = (array) $subscriber->custom_fields();
                }
                if ( is_array( $inquiry ) ) {
                        foreach ( array( 'submission_id', 'qualified_at', 'unqualified_at', 'closed_date', 'invoiced_amount', 'customer_rating', 'ads_conversion_value_frozen' ) as $ikey ) {
                                if ( array_key_exists( $ikey, $inquiry ) ) {
                                        $custom[ $ikey ] = null === $inquiry[ $ikey ] ? '' : (string) $inquiry[ $ikey ];
                                }
                        }
                }

                $resolved = is_array( $inquiry ) ? SiteEngine_Ads_Bridge_Inquiries::resolver_for_inquiry( $inquiry ) : $this->resolve_won_order_id( $custom, $email );
                $submission_context = null;
                if ( is_array( $inquiry ) ) {
                        $identity = $this->inquiry_submission_identity( $resolved, $config, array(
                                'email' => $email, 'phone' => $phone, 'first_name' => $first, 'last_name' => $last,
                                'city' => $city, 'postal_code' => $postal, 'state' => $state, 'country' => $country, 'ip' => $ip,
                        ) );
                        $email   = $identity['identity']['email'];
                        $phone   = $identity['identity']['phone'];
                        $first   = $identity['identity']['first_name'];
                        $last    = $identity['identity']['last_name'];
                        $city    = $identity['identity']['city'];
                        $postal  = $identity['identity']['postal_code'];
                        $state   = $identity['identity']['state'];
                        $country = $identity['identity']['country'];
                        $ip      = $identity['identity']['ip'];
                        $submission_context = $identity['submission'];
                        $state_norm   = $this->normalize_us_state( $state );
                        $country_norm = $this->normalize_country( $country );
                }

                $tag_names = array();
                foreach ( $subscriber->tags as $tag ) {
                        if ( isset( $tag->title ) ) {
                                $tag_names[] = $tag->title;
                        }
                        if ( isset( $tag->slug ) ) {
                                $tag_names[] = $tag->slug;
                        }
                }

                $list_names = array();
                foreach ( $subscriber->lists as $list ) {
                        if ( isset( $list->title ) ) {
                                $list_names[] = $list->title;
                        }
                        if ( isset( $list->slug ) ) {
                                $list_names[] = $list->slug;
                        }
                }

                $checks    = $this->evaluate_won_eligibility( $subscriber, $list_names, $custom, $email, $phone, $config, $inquiry );
                $gate_fail = array();
                foreach ( $checks as $check ) {
                        if ( 'fail' === $check['result'] ) {
                                $gate_fail[] = $check['gate'];
                        }
                }

                $order_id = isset( $resolved['order_id'] ) ? (string) $resolved['order_id'] : '';

                $gclid  = $this->resolved_attribution_value( $resolved, $config, $custom, 'gclid', ! is_array( $inquiry ) );
                $gbraid = $this->resolved_attribution_value( $resolved, $config, $custom, 'gbraid', ! is_array( $inquiry ) );
                $wbraid = $this->resolved_attribution_value( $resolved, $config, $custom, 'wbraid', ! is_array( $inquiry ) );
                if ( '' !== $gbraid && '' !== $wbraid ) {
                        $wbraid = '';
                }

                if ( ! is_array( $submission_context ) ) {
                        $submission_context = $this->resolved_submission_context( $resolved );
                }
                $form_user_agent    = $this->configured_form_response_value( $submission_context['response'], $config, 'user_agent' );
                $form_ip            = $this->configured_form_response_value( $submission_context['response'], $config, 'ip_address' );
                if ( '' !== $form_user_agent ) {
                        $user_agent = $form_user_agent;
                } elseif ( isset( $submission_context['row']['browser'] ) ) {
                        $user_agent = (string) $submission_context['row']['browser'];
                }
                if ( '' !== $form_ip ) {
                        $ip = $form_ip;
                } elseif ( '' === $ip && isset( $submission_context['row']['ip'] ) ) {
                        $ip = (string) $submission_context['row']['ip'];
                }

                $tz_string       = (string) $config->get( 'timezone', 'UTC' );
                $conversion_time = '';
                $time_err        = '';
                $tz              = null;
                try {
                        $tz = new \DateTimeZone( $tz_string );
                } catch ( \Exception $e ) {
                        $time_err = 'invalid configured timezone "' . $tz_string . '"';
                }
                $date_field = trim( (string) $config->get( 'exports.won_jobs.date_field', 'closed_date' ) );
                $date_raw   = isset( $custom[ $date_field ] ) ? (string) $custom[ $date_field ] : '';
                if ( null !== $tz ) {
                        if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $date_raw, $m ) ) {
                                $dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', $m[1] . ' ' . $time_of_day, $tz );
                                if ( false !== $dt ) {
                                        $conversion_time = $dt->format( 'Y-m-d H:i:sP' );
                                } else {
                                        $time_err = 'could not build datetime from ' . $date_field . '/time';
                                }
                        } else {
                                $time_err = $date_field . ' "' . $date_raw . '" is not a parseable Y-m-d date';
                        }
                }

                $value_field = trim( (string) $config->get( 'exports.won_jobs.value_field', 'invoiced_amount' ) );
                $frozen      = isset( $custom['ads_conversion_value_frozen'] ) ? $custom['ads_conversion_value_frozen'] : '';
                $value_raw   = isset( $custom[ $value_field ] ) ? $custom[ $value_field ] : '';
                if ( is_numeric( $frozen ) && (float) $frozen > 0 ) {
                        $val_src  = 'ads_conversion_value_frozen';
                        $conv_val = number_format( (float) $frozen, 2, '.', '' );
                } elseif ( is_numeric( $value_raw ) && (float) $value_raw > 0 ) {
                        $val_src  = $value_field;
                        $conv_val = number_format( (float) $value_raw, 2, '.', '' );
                } else {
                        $val_src  = '(none)';
                        $conv_val = '';
                }

                $currency = (string) $config->get( 'currency', 'USD' );
                if ( '' === $currency ) {
                        $currency = 'USD';
                }

                $plugin_hash = (bool) $config->get( 'sheets.google_ads_import.plugin_side_sha256', true );

                $norm_email = $this->normalize_email( $email );
                $norm_phone = $this->normalize_phone( $phone );
                $norm_first = $this->normalize_name( $first );
                $norm_last  = $this->normalize_name( $last );

                $emit = function ( $norm ) use ( $plugin_hash ) {
                        if ( '' === $norm ) {
                                return '';
                        }
                        return $plugin_hash ? hash( 'sha256', $norm ) : $norm;
                };

                $out_email = $emit( $norm_email );
                $out_phone = $emit( $norm_phone );
                $out_first = $emit( $norm_first );
                $out_last  = $emit( $norm_last );

                $measurement_consent     = $this->read_consent_value( $custom, array( 'se_google_ad_user_data', 'se_consent_advertising' ) );
                $personalization_consent = $this->read_consent_value( $custom, array( 'se_google_ad_personalization', 'se_google_ad_personalizat', 'se_consent_personalization', 'se_consent_personalizatio' ) );

                // Consent provenance authority (receipt-backed). This is the SOLE source
                // for the two emitted Google consent columns and for the consent export
                // gate. The read_consent_value() results above are retained as diagnostics
                // only and no longer feed the Google_Ads_Import row.
                $consent = $this->resolve_consent_provenance( $resolved );

                $spec = array(
                        array( 'k' => 'conversion_time',            'h' => 'Conversion date/time',       'v' => $conversion_time ),
                        array( 'k' => 'transaction_id',             'h' => 'Transaction ID',             'v' => $order_id ),
                        array( 'k' => 'gclid',                      'h' => 'GCLID',                      'v' => $gclid ),
                        array( 'k' => 'gbraid',                     'h' => 'GBRAID',                     'v' => $gbraid ),
                        array( 'k' => 'wbraid',                     'h' => 'WBRAID',                     'v' => $wbraid ),
                        array( 'k' => 'email',                      'h' => 'Email address',              'v' => $out_email ),
                        array( 'k' => 'phone',                      'h' => 'Phone number',               'v' => $out_phone ),
                        array( 'k' => 'ip',                         'h' => 'IP address',                 'v' => $ip ),
                        array( 'k' => 'first_name',                 'h' => 'First name',                 'v' => $out_first ),
                        array( 'k' => 'last_name',                  'h' => 'Last name',                  'v' => $out_last ),
                        array( 'k' => 'city',                       'h' => 'City',                       'v' => $city ),
                        array( 'k' => 'postal_code',                'h' => 'Postal code',                'v' => $postal ),
                        array( 'k' => 'region_code',                'h' => 'State or region code',       'v' => $state_norm['value'] ),
                        array( 'k' => 'country_code',               'h' => 'Country code',               'v' => $country_norm['value'] ),
                        array( 'k' => 'conversion_value',           'h' => 'Conversion value',           'v' => $conv_val ),
                        array( 'k' => 'currency_code',              'h' => 'Currency code',              'v' => $currency ),
                        array( 'k' => 'ad_measurement_consent',     'h' => 'Ad measurement consent',     'v' => $consent['measurement_feed'] ),
                        array( 'k' => 'ad_personalization_consent', 'h' => 'Ad personalization consent', 'v' => $consent['personalization_feed'] ),
                        array( 'k' => 'user_agent',                 'h' => 'User agent',                 'v' => $user_agent ),
                        array( 'k' => 'session_attributes',         'h' => 'Session attributes',         'v' => '' ),
                        array( 'k' => 'conversion_action',          'h' => 'Conversion action',           'v' => (string) $config->get( 'exports.won_jobs.conversion_action', 'SE | Converted Lead' ) ),
                );

                $overrides = (array) $config->get( 'sheets.google_ads_import.headers', array() );
                $headers   = array();
                $values    = array();
                foreach ( $spec as $f ) {
                        $headers[] = ( isset( $overrides[ $f['k'] ] ) && '' !== (string) $overrides[ $f['k'] ] ) ? (string) $overrides[ $f['k'] ] : $f['h'];
                        $values[]  = (string) $f['v'];
                }

                $hidden_txn = isset( $custom['transaction_id'] ) ? trim( (string) $custom['transaction_id'] ) : '';

                // Exportability: deterministic resolver + all CRM gates + resolved time/value.
                $reasons = array();
                if ( ! empty( $gate_fail ) ) {
                        $reasons[] = 'failing CRM gate(s): ' . implode( ', ', $gate_fail );
                }
                if ( ! ( $resolved['exportable'] && $resolved['deterministic'] ) ) {
                        $reasons[] = 'Order ID not deterministic (' . $resolved['reason'] . ')';
                }
                if ( '' === $conversion_time ) {
                        $reasons[] = 'conversion time unresolved (' . $time_err . ')';
                }
                if ( '' === $conv_val ) {
                        $reasons[] = 'no positive conversion value';
                }
                if ( '' !== $consent['block_reason'] ) {
                        $reasons[] = $consent['block_reason'];
                }
                $exportable = empty( $reasons );

                return array(
                        'email'                   => $email,
                        'phone'                   => $phone,
                        'first'                   => $first,
                        'last'                    => $last,
                        'ip'                      => $ip,
                        'city'                    => $city,
                        'postal'                  => $postal,
                        'state'                   => $state,
                        'country'                 => $country,
                        'state_norm'              => $state_norm,
                        'country_norm'            => $country_norm,
                        'user_agent'              => $user_agent,
                        'custom'                  => $custom,
                        'tag_names'               => $tag_names,
                        'list_names'              => $list_names,
                        'checks'                  => $checks,
                        'gate_fail'               => $gate_fail,
                        'resolved'                => $resolved,
                        'order_id'                => $order_id,
                        'gclid'                   => $gclid,
                        'gbraid'                  => $gbraid,
                        'wbraid'                  => $wbraid,
                        'conversion_time'         => $conversion_time,
                        'time_err'                => $time_err,
                        'tz_string'               => $tz_string,
                        'date_field'              => $date_field,
                        'conv_val'                => $conv_val,
                        'val_src'                 => $val_src,
                        'value_field'             => $value_field,
                        'currency'                => $currency,
                        'conversion_action'       => (string) $config->get( 'exports.won_jobs.conversion_action', 'SE | Converted Lead' ),
                        'plugin_hash'             => $plugin_hash,
                        'norm_email'              => $norm_email,
                        'norm_phone'              => $norm_phone,
                        'norm_first'              => $norm_first,
                        'norm_last'               => $norm_last,
                        'out_email'               => $out_email,
                        'out_phone'               => $out_phone,
                        'out_first'               => $out_first,
                        'out_last'                => $out_last,
                        'consent'                 => $consent,
                        'measurement_consent'     => $measurement_consent,
                        'personalization_consent' => $personalization_consent,
                        'spec'                    => $spec,
                        'headers'                 => $headers,
                        'values'                  => $values,
                        'hidden_txn'              => $hidden_txn,
                        'reasons'                 => $reasons,
                        'exportable'              => $exportable,
                );
        }

        /**
         * Ordered All_Leads header contract. Keys support per-client header overrides.
         *
         * @return array
         */
        private function all_leads_header_spec() {
                return array(
                        array( 'k' => 'transaction_id', 'h' => 'Transaction ID' ),
                        array( 'k' => 'form_id', 'h' => 'Form ID' ),
                        array( 'k' => 'submission_id', 'h' => 'Submission ID' ),
                        array( 'k' => 'serial_number', 'h' => 'Submission serial number' ),
                        array( 'k' => 'submission_status', 'h' => 'Submission status' ),
                        array( 'k' => 'submitted_at', 'h' => 'Submitted at' ),
                        array( 'k' => 'submission_updated_at', 'h' => 'Submission updated at' ),
                        array( 'k' => 'source_url', 'h' => 'Source URL' ),
                        array( 'k' => 'user_id', 'h' => 'WordPress user ID' ),
                        array( 'k' => 'browser', 'h' => 'Browser' ),
                        array( 'k' => 'device', 'h' => 'Device' ),
                        array( 'k' => 'is_favourite', 'h' => 'Favourite' ),
                        array( 'k' => 'payment_status', 'h' => 'Payment status' ),
                        array( 'k' => 'payment_method', 'h' => 'Payment method' ),
                        array( 'k' => 'payment_type', 'h' => 'Payment type' ),
                        array( 'k' => 'payment_currency', 'h' => 'Payment currency' ),
                        array( 'k' => 'payment_total', 'h' => 'Payment total' ),
                        array( 'k' => 'total_paid', 'h' => 'Total paid' ),
                        array( 'k' => 'response_decode_status', 'h' => 'Response decode status' ),
                        array( 'k' => 'raw_response_truncated', 'h' => 'Raw response truncated' ),
                        array( 'k' => 'crm_contact_id', 'h' => 'CRM contact ID' ),
                        array( 'k' => 'crm_match_source', 'h' => 'CRM match source' ),
                        array( 'k' => 'crm_match_detail', 'h' => 'CRM match detail' ),
                        array( 'k' => 'crm_status', 'h' => 'CRM status' ),
                        array( 'k' => 'crm_contact_type', 'h' => 'CRM contact type' ),
                        array( 'k' => 'crm_created_at', 'h' => 'CRM created at' ),
                        array( 'k' => 'crm_last_activity', 'h' => 'CRM last activity' ),
                        array( 'k' => 'lifecycle_tags', 'h' => 'Lifecycle tags' ),
                        array( 'k' => 'source_tags', 'h' => 'Source tags' ),
                        array( 'k' => 'other_tags', 'h' => 'Other tags' ),
                        array( 'k' => 'lists', 'h' => 'Lists' ),
                        array( 'k' => 'dnc', 'h' => 'DNC' ),
                        array( 'k' => 'first_name', 'h' => 'First name' ),
                        array( 'k' => 'last_name', 'h' => 'Last name' ),
                        array( 'k' => 'email', 'h' => 'Email' ),
                        array( 'k' => 'phone', 'h' => 'Phone' ),
                        array( 'k' => 'address_1', 'h' => 'Address line 1' ),
                        array( 'k' => 'address_2', 'h' => 'Address line 2' ),
                        array( 'k' => 'city', 'h' => 'City' ),
                        array( 'k' => 'state', 'h' => 'State' ),
                        array( 'k' => 'postal_code', 'h' => 'Postal code' ),
                        array( 'k' => 'country', 'h' => 'Country' ),
                        array( 'k' => 'service_type', 'h' => 'Service type' ),
                        array( 'k' => 'estimated_value', 'h' => 'Estimated value' ),
                        array( 'k' => 'invoiced_amount', 'h' => 'Invoiced amount' ),
                        array( 'k' => 'closed_date', 'h' => 'Closed date' ),
                        array( 'k' => 'lost_date', 'h' => 'Lost date' ),
                        array( 'k' => 'gclid', 'h' => 'GCLID' ),
                        array( 'k' => 'gbraid', 'h' => 'GBRAID' ),
                        array( 'k' => 'wbraid', 'h' => 'WBRAID' ),
                        array( 'k' => 'fbclid', 'h' => 'FBCLID' ),
                        array( 'k' => 'msclkid', 'h' => 'MSCLKID' ),
                        array( 'k' => 'utm_source', 'h' => 'UTM source' ),
                        array( 'k' => 'utm_medium', 'h' => 'UTM medium' ),
                        array( 'k' => 'utm_campaign', 'h' => 'UTM campaign' ),
                        array( 'k' => 'utm_content', 'h' => 'UTM content' ),
                        array( 'k' => 'utm_term', 'h' => 'UTM term' ),
                        array( 'k' => 'referrer_url', 'h' => 'Referrer URL' ),
                        array( 'k' => 'page_title', 'h' => 'Page title' ),
                        array( 'k' => 'ip_address', 'h' => 'IP address' ),
                        array( 'k' => 'user_agent', 'h' => 'User agent' ),
                        array( 'k' => 'cookie_value', 'h' => 'Cookie value' ),
                        array( 'k' => 'hidden_transaction_id', 'h' => 'Hidden transaction ID' ),
                        array( 'k' => 'stored_submission_id', 'h' => 'Stored submission ID' ),
                        array( 'k' => 'consent_receipt_id', 'h' => 'Consent receipt ID' ),
                        array( 'k' => 'consent_receipt_source', 'h' => 'Consent receipt ID source' ),
                        array( 'k' => 'consent_receipt_row_id', 'h' => 'Consent receipt row ID' ),
                        array( 'k' => 'consent_policy_version', 'h' => 'Consent policy version' ),
                        array( 'k' => 'consent_banner_version', 'h' => 'Consent banner version' ),
                        array( 'k' => 'required_policy_version', 'h' => 'Required policy version' ),
                        array( 'k' => 'runtime_policy_version', 'h' => 'Runtime consent policy version' ),
                        array( 'k' => 'consent_policy_check', 'h' => 'Consent policy check' ),
                        array( 'k' => 'ad_user_data', 'h' => 'Ad user data' ),
                        array( 'k' => 'ad_personalization', 'h' => 'Ad personalization' ),
                        array( 'k' => 'measurement_feed', 'h' => 'Ad measurement consent' ),
                        array( 'k' => 'personalization_feed', 'h' => 'Ad personalization consent' ),
                        array( 'k' => 'consent_decision', 'h' => 'Consent decision' ),
                        array( 'k' => 'consent_block_reason', 'h' => 'Consent block reason' ),
                        array( 'k' => 'google_consent_json', 'h' => 'Google consent JSON' ),
                        array( 'k' => 'raw_submission_json', 'h' => 'Raw submission JSON' ),
                        array( 'k' => 'se_cid', 'h' => 'Google campaign ID' ),
                        array( 'k' => 'se_agid', 'h' => 'Google ad group ID' ),
                        array( 'k' => 'se_adid', 'h' => 'Google creative ID' ),
                        array( 'k' => 'se_kw', 'h' => 'Google matched keyword' ),
                        array( 'k' => 'se_mt', 'h' => 'Google match type' ),
                        array( 'k' => 'se_net', 'h' => 'Google network' ),
                        array( 'k' => 'se_dev', 'h' => 'Google device' ),
                        array( 'k' => 'se_loc', 'h' => 'Google physical location criterion ID' ),
                        array( 'k' => 'customer_rating', 'h' => 'Customer Rating' ),
                        array( 'k' => 'qualified_at', 'h' => 'Qualified at' ),
                        array( 'k' => 'unqualified_at', 'h' => 'Unqualified at' ),
                        array( 'k' => 'lifecycle_event', 'h' => 'Lifecycle event' ),
                        array( 'k' => 'lifecycle_event_at', 'h' => 'Lifecycle event at' ),
                        array( 'k' => 'inquiry_id', 'h' => 'Inquiry ID' ),
                );
        }

        /**
         * Apply keyed header overrides without changing column order.
         *
         * @param array $spec      Header spec rows with k/h keys.
         * @param array $overrides Keyed replacement labels.
         * @return array
         */
        private function headers_from_spec( $spec, $overrides ) {
                $headers = array();
                foreach ( $spec as $field ) {
                        $key       = isset( $field['k'] ) ? (string) $field['k'] : '';
                        $fallback  = isset( $field['h'] ) ? (string) $field['h'] : '';
                        $headers[] = isset( $overrides[ $key ] ) && '' !== (string) $overrides[ $key ] ? (string) $overrides[ $key ] : $fallback;
                }
                return $headers;
        }

        /**
         * Build the complete worksheet/header contract for every enabled family.
         *
         * @param object $config Config instance.
         * @return array
         */
        private function enabled_sheet_contracts( $config ) {
                $contracts = array();

                $qualified_enabled = (bool) $config->get( 'exports.qualified_leads.enabled', false );
                $won_enabled       = (bool) $config->get( 'exports.won_jobs.enabled', false );

                // Google Ads Data Manager reads the first/leftmost worksheet. Qualified
                // and Converted Lead rows therefore share one canonical import tab and
                // are separated by the Conversion action column / Data Manager filters.
                if ( $qualified_enabled || $won_enabled ) {
                        $contracts[] = array(
                                'label'   => 'google_ads_import',
                                'tab'     => trim( (string) $config->get( 'exports.won_jobs.sheet_tab', 'Google_Ads_Import' ) ),
                                'headers' => $this->data_manager_headers( $config, 'won_jobs', '' ),
                        );
                }

                if ( (bool) $config->get( 'exports.all_leads.enabled', false ) ) {
                        $contracts[] = array(
                                'label'   => 'all_leads',
                                'tab'     => trim( (string) $config->get( 'exports.all_leads.sheet_tab', '' ) ),
                                'headers' => $this->headers_from_spec( $this->all_leads_header_spec(), (array) $config->get( 'exports.all_leads.header_overrides', array() ) ),
                        );
                }

                $seen = array();
                foreach ( $contracts as $contract ) {
                        if ( '' === $contract['tab'] ) {
                                WP_CLI::error( 'An enabled export family has an empty Sheet tab.' );
                        }
                        if ( isset( $seen[ $contract['tab'] ] ) ) {
                                WP_CLI::error( sprintf( 'Enabled Sheet tab "%s" is assigned to both %s and %s.', $contract['tab'], $seen[ $contract['tab'] ], $contract['label'] ) );
                        }
                        $seen[ $contract['tab'] ] = $contract['label'];
                }

                return $contracts;
        }

        /**
         * Return the 21-column Data Manager header contract for Google import rows and legacy-shaped lost diagnostics.
         *
         * @param object $config Config instance.
         * @param string $family won_jobs|lost_leads.
         * @param string $reason Lost reason key when applicable.
         * @return array
         */
        private function data_manager_headers( $config, $family, $reason ) {
                $blank = array(
                        'conversion_time' => '', 'transaction_id' => '', 'gclid' => '', 'gbraid' => '', 'wbraid' => '',
                        'email' => '', 'phone' => '', 'ip' => '', 'first_name' => '', 'last_name' => '', 'city' => '',
                        'postal_code' => '', 'region_code' => '', 'country_code' => '', 'conversion_value' => '',
                        'currency_code' => '', 'measurement_consent' => '', 'personalization_consent' => '',
                        'user_agent' => '', 'session_attributes' => '', 'conversion_action' => '',
                );
                $spec = 'lost_leads' === $family
                        ? $this->lost_diagnostic_row_spec( array_merge( $blank, array(
                                'se_cid' => '', 'se_agid' => '', 'se_adid' => '', 'se_kw' => '',
                                'se_mt' => '', 'se_net' => '', 'se_dev' => '', 'se_loc' => '',
                        ) ) )
                        : $this->data_manager_row_spec( $blank );

                if ( 'won_jobs' === $family ) {
                        $overrides = (array) $config->get( 'exports.won_jobs.header_overrides', array() );
                } else {
                        $global = (array) $config->get( 'exports.lost_leads.header_overrides', array() );
                        $local  = '' !== $reason ? (array) $config->get( 'exports.lost_leads.reasons.' . $reason . '.header_overrides', array() ) : array();
                        $overrides = array_merge( $global, $local );
                }

                return $this->headers_from_spec( $spec, $overrides );
        }

        /**
         * Remove only empty trailing cells and normalize every remaining value to text.
         *
         * @param array $cells Sheet row cells.
         * @return array
         */
        private function trim_trailing_empty_cells( $cells ) {
                $cells = array_values( array_map( 'strval', is_array( $cells ) ? $cells : array() ) );
                while ( ! empty( $cells ) && '' === (string) end( $cells ) ) {
                        array_pop( $cells );
                }
                return $cells;
        }

        /**
         * Describe the first exact header mismatch.
         *
         * @param array $expected Expected cells.
         * @param array $actual   Actual cells.
         * @return string
         */
        private function describe_header_difference( $expected, $actual ) {
                $expected = array_values( array_map( 'strval', $expected ) );
                $actual   = array_values( array_map( 'strval', $actual ) );
                $max      = max( count( $expected ), count( $actual ) );
                for ( $i = 0; $i < $max; $i++ ) {
                        $want = array_key_exists( $i, $expected ) ? $expected[ $i ] : '(no expected column)';
                        $have = array_key_exists( $i, $actual ) ? $actual[ $i ] : '(missing)';
                        if ( $want !== $have ) {
                                return sprintf( 'column %s: expected "%s"; found "%s" (expected %d columns, found %d)', $this->sheet_column_name( $i + 1 ), $want, $have, count( $expected ), count( $actual ) );
                        }
                }
                return sprintf( 'header differs (expected %d columns, found %d)', count( $expected ), count( $actual ) );
        }

        /**
         * Convert a positive 1-based column number to a Sheets column label.
         *
         * @param int $number 1-based column number.
         * @return string
         */
        private function sheet_column_name( $number ) {
                $number = (int) $number;
                $name   = '';
                while ( $number > 0 ) {
                        $number--;
                        $name   = chr( 65 + ( $number % 26 ) ) . $name;
                        $number = intdiv( $number, 26 );
                }
                return $name;
        }

        /**
         * Quote one worksheet title for A1 notation.
         *
         * @param string $tab Worksheet title.
         * @return string
         */
        private function quote_sheet_title( $tab ) {
                return "'" . str_replace( "'", "''", (string) $tab ) . "'";
        }

        /**
         * Mask an identifier for operator output.
         *
         * @param string $value Identifier.
         * @return string
         */
        private function mask_identifier( $value ) {
                $value = (string) $value;
                if ( strlen( $value ) <= 10 ) {
                        return $value;
                }
                return substr( $value, 0, 4 ) . str_repeat( '*', 6 ) . substr( $value, -4 );
        }

        /**
         * Execute one authenticated Sheets API JSON request.
         *
         * @param string     $method HTTP method.
         * @param string     $url    Absolute API URL.
         * @param string     $token  OAuth bearer token.
         * @param array|null $body   Optional JSON body.
         * @return array{ok:bool,code:int,json:array,error:string}
         */
        private function google_sheets_json_request( $method, $url, $token, $body = null ) {
                $args = array(
                        'method'  => strtoupper( (string) $method ),
                        'timeout' => 25,
                        'headers' => array(
                                'Authorization' => 'Bearer ' . $token,
                                'Content-Type'  => 'application/json',
                        ),
                );
                if ( null !== $body ) {
                        $encoded = wp_json_encode( $body );
                        if ( false === $encoded ) {
                                return array( 'ok' => false, 'code' => 0, 'json' => array(), 'error' => 'could not encode JSON request body' );
                        }
                        $args['body'] = $encoded;
                }

                $response = wp_remote_request( $url, $args );
                if ( is_wp_error( $response ) ) {
                        return array( 'ok' => false, 'code' => 0, 'json' => array(), 'error' => $response->get_error_message() );
                }

                $code = (int) wp_remote_retrieve_response_code( $response );
                $raw  = (string) wp_remote_retrieve_body( $response );
                $json = '' !== $raw ? json_decode( $raw, true ) : array();
                $json = is_array( $json ) ? $json : array();

                if ( $code < 200 || $code >= 300 ) {
                        $message = isset( $json['error']['message'] ) ? (string) $json['error']['message'] : ( '' !== $raw ? substr( $raw, 0, 400 ) : 'no response body' );
                        return array( 'ok' => false, 'code' => $code, 'json' => $json, 'error' => sprintf( 'HTTP %d: %s', $code, $message ) );
                }

                return array( 'ok' => true, 'code' => $code, 'json' => $json, 'error' => '' );
        }

        /**
         * List worksheet titles in a spreadsheet.
         *
         * @param string $spreadsheet_id Spreadsheet ID.
         * @param string $token          OAuth token.
         * @return array{ok:bool,titles:array,sheets:array,error:string}
         */
        private function google_sheets_list_tabs( $spreadsheet_id, $token ) {
                $url = sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s?fields=sheets.properties%%28sheetId%%2Ctitle%%2Cindex%%2CgridProperties%%28columnCount%%2CrowCount%%29%%29', rawurlencode( $spreadsheet_id ) );
                $res = $this->google_sheets_json_request( 'GET', $url, $token );
                if ( ! $res['ok'] ) {
                        return array( 'ok' => false, 'titles' => array(), 'sheets' => array(), 'error' => $res['error'] );
                }
                $titles = array();
                $by_title = array();
                foreach ( isset( $res['json']['sheets'] ) && is_array( $res['json']['sheets'] ) ? $res['json']['sheets'] : array() as $sheet ) {
                        if ( ! isset( $sheet['properties']['title'] ) ) {
                                continue;
                        }
                        $properties = (array) $sheet['properties'];
                        $title      = (string) $properties['title'];
                        $titles[]   = $title;
                        $by_title[ $title ] = array(
                                'sheet_id'     => isset( $properties['sheetId'] ) ? (int) $properties['sheetId'] : 0,
                                'index'        => isset( $properties['index'] ) ? (int) $properties['index'] : -1,
                                'column_count' => isset( $properties['gridProperties']['columnCount'] ) ? (int) $properties['gridProperties']['columnCount'] : 0,
                                'row_count'    => isset( $properties['gridProperties']['rowCount'] ) ? (int) $properties['gridProperties']['rowCount'] : 0,
                        );
                }
                return array( 'ok' => true, 'titles' => array_values( array_unique( $titles ) ), 'sheets' => $by_title, 'error' => '' );
        }

        /**
         * Read the first row from one worksheet.
         *
         * @param string $spreadsheet_id Spreadsheet ID.
         * @param string $tab            Worksheet title.
         * @param string $token          OAuth token.
         * @return array{ok:bool,values:array,error:string}
         */
        private function google_sheets_read_header( $spreadsheet_id, $tab, $token ) {
                $range = $this->quote_sheet_title( $tab ) . '!1:1';
                $url   = sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?majorDimension=ROWS', rawurlencode( $spreadsheet_id ), rawurlencode( $range ) );
                $res   = $this->google_sheets_json_request( 'GET', $url, $token );
                if ( ! $res['ok'] ) {
                        return array( 'ok' => false, 'values' => array(), 'error' => $res['error'] );
                }
                $values = isset( $res['json']['values'][0] ) && is_array( $res['json']['values'][0] ) ? $res['json']['values'][0] : array();
                return array( 'ok' => true, 'values' => $values, 'error' => '' );
        }

        /**
         * Confirm that one bounded worksheet range contains no non-empty values.
         * Used only to fail closed before normalizing known manual header variants.
         *
         * @param string $spreadsheet_id Spreadsheet ID.
         * @param string $tab            Worksheet title.
         * @param string $cells          Cell-only A1 range, e.g. T2:T1000.
         * @param string $token          OAuth token.
         * @return array{ok:bool,blank:bool,error:string}
         */
        private function google_sheets_range_is_blank( $spreadsheet_id, $tab, $cells, $token ) {
                $range = $this->quote_sheet_title( $tab ) . '!' . trim( (string) $cells );
                $url   = sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?majorDimension=ROWS', rawurlencode( $spreadsheet_id ), rawurlencode( $range ) );
                $res   = $this->google_sheets_json_request( 'GET', $url, $token );
                if ( ! $res['ok'] ) {
                        return array( 'ok' => false, 'blank' => false, 'error' => $res['error'] );
                }

                $rows = isset( $res['json']['values'] ) && is_array( $res['json']['values'] ) ? $res['json']['values'] : array();
                foreach ( $rows as $row ) {
                        foreach ( is_array( $row ) ? $row : array() as $value ) {
                                if ( '' !== trim( (string) $value ) ) {
                                        return array( 'ok' => true, 'blank' => false, 'error' => '' );
                                }
                        }
                }
                return array( 'ok' => true, 'blank' => true, 'error' => '' );
        }

        /**
         * Create missing worksheet titles in one atomic Sheets batchUpdate request.
         *
         * @param string $spreadsheet_id Spreadsheet ID.
         * @param array  $tabs           Map of worksheet title => required columns.
         * @param string $token          OAuth token.
         * @return array{ok:bool,error:string}
         */
        private function google_sheets_create_tabs( $spreadsheet_id, $tabs, $token ) {
                $requests = array();
                foreach ( $tabs as $tab => $columns ) {
                        if ( is_int( $tab ) ) {
                                $tab     = (string) $columns;
                                $columns = 26;
                        }
                        $requests[] = array(
                                'addSheet' => array(
                                        'properties' => array(
                                                'title'          => (string) $tab,
                                                'gridProperties' => array(
                                                        'rowCount'    => 1000,
                                                        'columnCount' => max( 26, (int) $columns ),
                                                ),
                                        ),
                                ),
                        );
                }
                if ( empty( $requests ) ) {
                        return array( 'ok' => true, 'error' => '' );
                }
                $url = sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s:batchUpdate', rawurlencode( $spreadsheet_id ) );
                $res = $this->google_sheets_json_request( 'POST', $url, $token, array( 'requests' => $requests ) );
                return array( 'ok' => $res['ok'], 'error' => $res['error'] );
        }

        /**
         * Move an existing worksheet to one zero-based index without touching cells.
         *
         * @param string $spreadsheet_id Spreadsheet ID.
         * @param int    $sheet_id       Google worksheet ID.
         * @param int    $index          Target zero-based index.
         * @param string $token          OAuth token.
         * @return array{ok:bool,error:string}
         */
        private function google_sheets_move_tab_to_index( $spreadsheet_id, $sheet_id, $index, $token ) {
                $url = sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s:batchUpdate', rawurlencode( $spreadsheet_id ) );
                $body = array(
                        'requests' => array(
                                array(
                                        'updateSheetProperties' => array(
                                                'properties' => array( 'sheetId' => (int) $sheet_id, 'index' => max( 0, (int) $index ) ),
                                                'fields'     => 'index',
                                        ),
                                ),
                        ),
                );
                $res = $this->google_sheets_json_request( 'POST', $url, $token, $body );
                return array( 'ok' => $res['ok'], 'error' => $res['error'] );
        }

        /**
         * Increase existing worksheet grids to their required contract width.
         * Column counts are only increased; rows and existing cell values are untouched.
         *
         * @param string $spreadsheet_id Spreadsheet ID.
         * @param array  $tabs           Map of title => sheet_id/column_count.
         * @param string $token          OAuth token.
         * @return array{ok:bool,error:string}
         */
        private function google_sheets_resize_tabs( $spreadsheet_id, $tabs, $token ) {
                $requests = array();
                foreach ( $tabs as $tab => $meta ) {
                        $sheet_id = isset( $meta['sheet_id'] ) ? (int) $meta['sheet_id'] : 0;
                        $columns  = isset( $meta['column_count'] ) ? (int) $meta['column_count'] : 0;
                        if ( $sheet_id < 0 || $columns < 1 ) {
                                return array( 'ok' => false, 'error' => sprintf( 'invalid resize metadata for tab "%s"', (string) $tab ) );
                        }
                        $requests[] = array(
                                'updateSheetProperties' => array(
                                        'properties' => array(
                                                'sheetId'        => $sheet_id,
                                                'gridProperties' => array( 'columnCount' => $columns ),
                                        ),
                                        'fields' => 'gridProperties.columnCount',
                                ),
                        );
                }
                if ( empty( $requests ) ) {
                        return array( 'ok' => true, 'error' => '' );
                }
                $url = sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s:batchUpdate', rawurlencode( $spreadsheet_id ) );
                $res = $this->google_sheets_json_request( 'POST', $url, $token, array( 'requests' => $requests ) );
                return array( 'ok' => $res['ok'], 'error' => $res['error'] );
        }

        /**
         * Write one exact header row into A1 through the final contract column.
         *
         * @param string $spreadsheet_id Spreadsheet ID.
         * @param string $tab            Worksheet title.
         * @param array  $headers        Ordered header cells.
         * @param string $token          OAuth token.
         * @return array{ok:bool,error:string}
         */
        private function google_sheets_write_header( $spreadsheet_id, $tab, $headers, $token ) {
                if ( empty( $headers ) ) {
                        return array( 'ok' => false, 'error' => 'header contract is empty' );
                }
                $range = $this->quote_sheet_title( $tab ) . '!A1:' . $this->sheet_column_name( count( $headers ) ) . '1';
                $url   = sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?valueInputOption=RAW', rawurlencode( $spreadsheet_id ), rawurlencode( $range ) );
                $res   = $this->google_sheets_json_request( 'PUT', $url, $token, array( 'majorDimension' => 'ROWS', 'values' => array( array_values( $headers ) ) ) );
                return array( 'ok' => $res['ok'], 'error' => $res['error'] );
        }

        /**
         * Parse the common mode, limit, and optional terminal-time arguments used by
         * the additional export families.
         *
         * @param array $assoc_args   CLI associative arguments.
         * @param int   $default_limit Default batch limit.
         * @param bool  $with_time     Whether to parse --time-of-day.
         * @return array
         */
        private function parse_family_batch_args( $assoc_args, $default_limit, $with_time ) {
                $dry   = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
                $apply = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );

                if ( $dry && $apply ) {
                        WP_CLI::error( 'Pass exactly one of --dry-run or --apply, not both.' );
                }
                if ( ! $dry && ! $apply ) {
                        WP_CLI::error( 'Refusing to run with no mode. Pass --dry-run or --apply.' );
                }

                $limit_raw = WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', $default_limit );
                if ( ! ctype_digit( (string) $limit_raw ) || (int) $limit_raw < 1 ) {
                        WP_CLI::error( sprintf( '--limit "%s" must be a positive integer.', (string) $limit_raw ) );
                }

                $out = array(
                        'dry'   => $dry,
                        'apply' => $apply,
                        'limit' => (int) $limit_raw,
                );

                if ( $with_time ) {
                        $time = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'time-of-day', '12:00:00' );
                        if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time ) ) {
                                WP_CLI::error( sprintf( '--time-of-day "%s" is not a valid HH:MM:SS (00:00:00-23:59:59).', $time ) );
                        }
                        $out['time_of_day'] = $time;
                }

                return $out;
        }

        /**
         * Strictly parse a comma-separated list of positive integer IDs.
         *
         * @param string $raw   Raw CSV.
         * @param string $label Argument label for diagnostics.
         * @return array
         */
        private function parse_positive_csv_ids( $raw, $label ) {
                $out = array();
                foreach ( explode( ',', (string) $raw ) as $part ) {
                        $part = trim( $part );
                        if ( '' === $part || ! ctype_digit( $part ) || (int) $part < 1 ) {
                                WP_CLI::error( sprintf( '%s must contain only positive integer IDs; invalid token "%s".', $label, $part ) );
                        }
                        $out[] = (int) $part;
                }
                return array_values( array_unique( $out ) );
        }

        /**
         * Select All_Leads source submissions. Normal runs select the oldest rows for
         * the configured form that have no appended ledger event. Explicit IDs are
         * selected verbatim so the command can report an already-ledgered canary.
         *
         * @param string $table         Canonical submissions table.
         * @param int    $form_id       Configured form ID.
         * @param array  $ids           Explicit submission IDs, or empty.
         * @param int    $limit         Maximum rows.
         * @param string $sink          Ledger sink.
         * @param bool   $ledger_exists Whether the ledger exists.
         * @return array
         */
        private function select_all_leads_submissions( $table, $form_id, $ids, $limit, $sink, $ledger_exists ) {
                global $wpdb;

                if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
                        return array();
                }

                if ( ! empty( $ids ) ) {
                        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
                        $sql          = "SELECT * FROM `{$table}` WHERE id IN ({$placeholders}) ORDER BY id ASC LIMIT %d";
                        $params       = array_merge( $ids, array( $limit ) );
                        $prepared     = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $params ) );
                        $rows         = $wpdb->get_results( $prepared, ARRAY_A );
                        return is_array( $rows ) ? $rows : array();
                }

                if ( $ledger_exists ) {
                        $ledger = $this->ledger_table();
                        $sql    = "SELECT s.*
                                     FROM `{$table}` AS s
                                    WHERE s.form_id = %d
                                      AND NOT EXISTS (
                                            SELECT 1
                                              FROM `{$ledger}` AS l
                                             WHERE l.sink_slug = %s
                                               AND l.transaction_id = CONCAT('ff:', s.form_id, ':', s.id)
                                               AND l.status = 'appended'
                                      )
                                    ORDER BY s.id ASC
                                    LIMIT %d";
                        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $form_id, $sink, $limit ), ARRAY_A );
                } else {
                        $rows = $wpdb->get_results(
                                $wpdb->prepare( "SELECT * FROM `{$table}` WHERE form_id = %d ORDER BY id ASC LIMIT %d", $form_id, $limit ),
                                ARRAY_A
                        );
                }

                return is_array( $rows ) ? $rows : array();
        }

        /**
         * Convert an arbitrary Fluent Forms response value into one stable Sheet cell.
         *
         * @param mixed $value Input value.
         * @return string
         */
        private function submission_value_to_string( $value ) {
                if ( null === $value ) {
                        return '';
                }
                if ( is_bool( $value ) ) {
                        return $value ? 'true' : 'false';
                }
                if ( is_scalar( $value ) ) {
                        return $this->sanitize_sheet_cell( (string) $value );
                }
                $json = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                return false === $json ? '' : $this->sanitize_sheet_cell( $json );
        }

        /**
         * Read one configured Fluent Forms role from a decoded submission response.
         * Supports direct field names and optional dot paths.
         *
         * @param array  $response Decoded response.
         * @param object $config   Config instance.
         * @param string $role     Internal field role.
         * @return string
         */
        private function configured_form_response_value( $response, $config, $role ) {
                $field = (string) $config->get( 'fluent_forms.fields.' . $role, '' );
                if ( '' === $field || ! is_array( $response ) ) {
                        return '';
                }
                if ( array_key_exists( $field, $response ) ) {
                        return $this->submission_value_to_string( $response[ $field ] );
                }

                $node = $response;
                foreach ( explode( '.', $field ) as $segment ) {
                        if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
                                return '';
                        }
                        $node = $node[ $segment ];
                }
                return $this->submission_value_to_string( $node );
        }

        /**
         * Make one value safe for a Google Sheets cell. RAW input mode prevents
         * formula interpretation. The 49,000-byte cap leaves room beneath the Sheets
         * per-cell limit; callers record whether a raw response was truncated.
         *
         * @param mixed $value Input value.
         * @param int   $limit Maximum bytes.
         * @return string
         */
        private function sanitize_sheet_cell( $value, $limit = 49000 ) {
                $value = (string) $value;
                if ( function_exists( 'wp_check_invalid_utf8' ) ) {
                        $value = wp_check_invalid_utf8( $value, true );
                }
                $value = str_replace( "\0", '', $value );
                if ( strlen( $value ) > $limit ) {
                        if ( function_exists( 'mb_strcut' ) ) {
                                $value = mb_strcut( $value, 0, $limit, 'UTF-8' );
                        } else {
                                $value = substr( $value, 0, $limit );
                        }
                }
                return $value;
        }

        /**
         * Match a FluentCRM contact to one submission. The stored submission_id custom
         * field is primary. Exact email is a fallback only when no stored link exists.
         * Contact linkage is diagnostic for All_Leads and never gates the row.
         *
         * @param int    $entry_id Submission ID.
         * @param string $email    Submission email.
         * @return array{subscriber:mixed,source:string,detail:string}
         */
        private function match_subscriber_for_submission( $entry_id, $email ) {
                $out = array( 'subscriber' => null, 'source' => 'none', 'detail' => 'no FluentCRM contact matched' );

                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                        $out['detail'] = 'FluentCRM Subscriber model unavailable';
                        return $out;
                }

                global $wpdb;
                $meta_table = $wpdb->prefix . 'fc_subscriber_meta';
                $meta_cols  = $this->table_columns( $meta_table );

                if ( in_array( 'subscriber_id', $meta_cols, true )
                        && in_array( 'object_type', $meta_cols, true )
                        && in_array( 'key', $meta_cols, true )
                        && in_array( 'value', $meta_cols, true ) ) {
                        $linked = $wpdb->get_col(
                                $wpdb->prepare(
                                        "SELECT subscriber_id
                                           FROM `{$meta_table}`
                                          WHERE object_type = %s
                                            AND `key` = %s
                                            AND value = %s
                                          ORDER BY id DESC
                                          LIMIT 3",
                                        'custom_field',
                                        'submission_id',
                                        (string) $entry_id
                                )
                        );
                        $linked = array_values( array_unique( array_map( 'intval', is_array( $linked ) ? $linked : array() ) ) );
                        if ( 1 === count( $linked ) ) {
                                $subscriber = \FluentCrm\App\Models\Subscriber::find( $linked[0] );
                                if ( $subscriber ) {
                                        return array( 'subscriber' => $subscriber, 'source' => 'stored submission_id', 'detail' => 'exact custom-field link' );
                                }
                        } elseif ( count( $linked ) > 1 ) {
                                return array( 'subscriber' => null, 'source' => 'stored submission_id conflict', 'detail' => 'multiple contacts claim submission_id ' . $entry_id );
                        }
                }

                $email = trim( (string) $email );
                if ( '' === $email ) {
                        return $out;
                }

                try {
                        $subscriber = \FluentCrm\App\Models\Subscriber::query()->where( 'email', $email )->first();
                } catch ( \Throwable $e ) {
                        $out['detail'] = 'email lookup failed: ' . $e->getMessage();
                        return $out;
                }

                if ( $subscriber ) {
                        return array( 'subscriber' => $subscriber, 'source' => 'exact email fallback', 'detail' => 'no stored submission_id link found' );
                }

                return $out;
        }

        /**
         * Snapshot a contact's current tags/lists and classify lifecycle/source tags.
         * Sorted output makes row hashes stable regardless of relationship order.
         *
         * @param object|null $subscriber FluentCRM subscriber.
         * @param object      $config     Config instance.
         * @return array
         */
        private function subscriber_membership_snapshot( $subscriber, $config ) {
                $tag_titles  = array();
                $list_titles = array();

                if ( is_object( $subscriber ) ) {
                        $tags = isset( $subscriber->tags ) ? $subscriber->tags : array();
                        if ( is_iterable( $tags ) ) {
                                foreach ( $tags as $tag ) {
                                        if ( is_object( $tag ) && isset( $tag->title ) && '' !== (string) $tag->title ) {
                                                $tag_titles[] = (string) $tag->title;
                                        }
                                }
                        }
                        $lists = isset( $subscriber->lists ) ? $subscriber->lists : array();
                        if ( is_iterable( $lists ) ) {
                                foreach ( $lists as $list ) {
                                        if ( is_object( $list ) && isset( $list->title ) && '' !== (string) $list->title ) {
                                                $list_titles[] = (string) $list->title;
                                        }
                                }
                        }
                }

                $tag_titles  = array_values( array_unique( $tag_titles ) );
                $list_titles = array_values( array_unique( $list_titles ) );
                sort( $tag_titles, SORT_STRING );
                sort( $list_titles, SORT_STRING );

                $lifecycle_config = array();
                foreach ( SiteEngine_Ads_Bridge_Config::lifecycle_roles() as $role ) {
                        $title = (string) $config->get( 'fluentcrm.tags.lifecycle.' . $role, '' );
                        if ( '' !== $title ) {
                                $lifecycle_config[ $role ] = $title;
                        }
                }
                $source_config = (array) $config->get( 'fluentcrm.tags.sources', array() );

                $lifecycle = array_values( array_intersect( $tag_titles, array_values( $lifecycle_config ) ) );
                $sources   = array_values( array_intersect( $tag_titles, array_values( $source_config ) ) );
                $other     = array_values( array_diff( $tag_titles, $lifecycle, $sources ) );
                sort( $lifecycle, SORT_STRING );
                sort( $sources, SORT_STRING );
                sort( $other, SORT_STRING );

                $dnc_name = (string) $config->get( 'fluentcrm.lists.do_not_contact', 'DNC' );

                return array(
                        'tags'       => $tag_titles,
                        'lists'      => $list_titles,
                        'lifecycle'  => $lifecycle,
                        'sources'    => $sources,
                        'other_tags' => $other,
                        'dnc'        => ( '' !== $dnc_name && in_array( $dnc_name, $list_titles, true ) ),
                        'dnc_name'   => $dnc_name,
                );
        }

        /**
         * Build the fixed All_Leads operational row.
         *
         * @param array  $submission Submission table row.
         * @param string $table      Canonical submissions table.
         * @param object $config     Config instance.
         * @return array
         */
        private function build_all_leads_row( $submission, $table, $config, $lifecycle_event = 'New', $lifecycle_event_at = '', $inquiry = null ) {
                $entry_id = isset( $submission['id'] ) ? (int) $submission['id'] : 0;
                $form_id  = isset( $submission['form_id'] ) ? (int) $submission['form_id'] : 0;
                $txn      = ( $entry_id > 0 && $form_id > 0 ) ? 'ff:' . $form_id . ':' . $entry_id : '';
                $reasons  = array();

                if ( $entry_id < 1 || $form_id < 1 ) {
                        $reasons[] = 'submission lacks positive id/form_id';
                }
                $configured_form = (int) $config->get( 'fluent_forms.form_id', 0 );
                if ( $configured_form > 0 && $form_id !== $configured_form ) {
                        $reasons[] = sprintf( 'submission form_id %d does not match configured master form %d', $form_id, $configured_form );
                }

                $raw_response  = isset( $submission['response'] ) ? (string) $submission['response'] : '';
                $response      = array();
                $decode_status = 'empty';
                if ( '' !== $raw_response ) {
                        $decoded = json_decode( $raw_response, true );
                        if ( is_array( $decoded ) ) {
                                $response      = $decoded;
                                $decode_status = 'ok';
                        } else {
                                $decode_status = 'invalid JSON: ' . ( function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : (string) json_last_error() );
                        }
                }

                $form_values = array();
                foreach ( array( 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'zip_code', 'country', 'phone', 'email', 'service_type', 'estimated_value', 'invoiced_amount', 'closed_date', 'lost_date', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'se_cid', 'se_agid', 'se_adid', 'se_kw', 'se_mt', 'se_net', 'se_dev', 'se_loc', 'referrer_url', 'page_title', 'ip_address', 'user_agent', 'cookie_value', 'transaction_id', 'submission_id' ) as $role ) {
                        $form_values[ $role ] = $this->configured_form_response_value( $response, $config, $role );
                }

                $match      = $this->match_subscriber_for_submission( $entry_id, $form_values['email'] );
                $subscriber = $match['subscriber'];
                $custom     = array();
                if ( is_object( $subscriber ) && method_exists( $subscriber, 'custom_fields' ) ) {
                        $custom = (array) $subscriber->custom_fields();
                }
                $membership = $this->subscriber_membership_snapshot( $subscriber, $config );

                // Inquiry-aware rows must never inherit mutable current-inquiry
                // milestones from a later FluentCRM contact projection. The bound
                // submission remains the identity/attribution authority, while the
                // Ads inquiry row supplies the historical lifecycle/value snapshot.
                $inquiry_id = '';
                if ( is_array( $inquiry ) && ! empty( $inquiry['inquiry_id'] ) ) {
                        $inquiry_id = (string) $inquiry['inquiry_id'];
                        foreach ( array( 'submission_id', 'qualified_at', 'unqualified_at', 'closed_date', 'invoiced_amount', 'customer_rating' ) as $ikey ) {
                                if ( array_key_exists( $ikey, $inquiry ) ) {
                                        $custom[ $ikey ] = null === $inquiry[ $ikey ] ? '' : (string) $inquiry[ $ikey ];
                                }
                        }
                        if ( ! empty( $inquiry['lifecycle'] ) ) {
                                $membership['lifecycle'] = array( ucfirst( (string) $inquiry['lifecycle'] ) );
                        }
                } elseif ( '' !== $txn && class_exists( 'SiteEngine_Ads_Bridge_Inquiries' ) ) {
                        $mapped = SiteEngine_Ads_Bridge_Inquiries::get_by_transaction_id( $txn );
                        if ( is_array( $mapped ) && ! empty( $mapped['inquiry_id'] ) ) {
                                $inquiry_id = (string) $mapped['inquiry_id'];
                        }
                }

                $pick = function ( $primary, $fallback ) {
                        return '' !== trim( (string) $primary ) ? (string) $primary : (string) $fallback;
                };
                $sub_prop = function ( $name ) use ( $subscriber ) {
                        return is_object( $subscriber ) && isset( $subscriber->{$name} ) ? (string) $subscriber->{$name} : '';
                };
                $custom_value = function ( $slug ) use ( $subscriber, $custom, $inquiry ) {
                        if ( is_array( $inquiry ) && array_key_exists( $slug, $custom ) ) {
                                return is_array( $custom[ $slug ] ) ? implode( '|', $custom[ $slug ] ) : (string) $custom[ $slug ];
                        }
                        return is_object( $subscriber ) ? $this->get_contact_custom_value( $subscriber, $custom, $slug ) : '';
                };

                $reason_field      = trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_reason_field', 'customer_rating' ) );
                $qualified_field   = trim( (string) $config->get( 'exports.qualified_leads.date_field', 'qualified_at' ) );
                $unqualified_field = trim( (string) $config->get( 'fluentcrm.lifecycle_automation.unqualified_at_field', 'unqualified_at' ) );

                $resolved = array(
                        'order_id'          => $txn,
                        'source'            => 'direct_submission',
                        'deterministic'     => ( '' !== $txn ),
                        'exportable'        => ( '' !== $txn ),
                        'reason'            => '',
                        'form_id'           => (string) $form_id,
                        'entry_id'          => (string) $entry_id,
                        'submissions_table' => $table,
                        'transaction_id'    => $this->find_transaction_id_for_entry( $entry_id ),
                );
                $consent = $this->resolve_consent_provenance( $resolved, $config );

                $raw_truncated = strlen( $raw_response ) > 49000;
                $raw_cell      = $this->sanitize_sheet_cell( $raw_response );

                $spec = array(
                        array( 'k' => 'transaction_id',          'h' => 'Transaction ID',                 'v' => $txn ),
                        array( 'k' => 'form_id',                 'h' => 'Form ID',                        'v' => $form_id ),
                        array( 'k' => 'submission_id',           'h' => 'Submission ID',                  'v' => $entry_id ),
                        array( 'k' => 'serial_number',           'h' => 'Submission serial number',       'v' => isset( $submission['serial_number'] ) ? $submission['serial_number'] : '' ),
                        array( 'k' => 'submission_status',       'h' => 'Submission status',              'v' => isset( $submission['status'] ) ? $submission['status'] : '' ),
                        array( 'k' => 'submitted_at',            'h' => 'Submitted at',                   'v' => isset( $submission['created_at'] ) ? $submission['created_at'] : '' ),
                        array( 'k' => 'submission_updated_at',   'h' => 'Submission updated at',          'v' => isset( $submission['updated_at'] ) ? $submission['updated_at'] : '' ),
                        array( 'k' => 'source_url',              'h' => 'Source URL',                     'v' => isset( $submission['source_url'] ) ? $submission['source_url'] : '' ),
                        array( 'k' => 'user_id',                 'h' => 'WordPress user ID',              'v' => isset( $submission['user_id'] ) ? $submission['user_id'] : '' ),
                        array( 'k' => 'browser',                 'h' => 'Browser',                        'v' => isset( $submission['browser'] ) ? $submission['browser'] : '' ),
                        array( 'k' => 'device',                  'h' => 'Device',                         'v' => isset( $submission['device'] ) ? $submission['device'] : '' ),
                        array( 'k' => 'is_favourite',            'h' => 'Favourite',                      'v' => isset( $submission['is_favourite'] ) ? $submission['is_favourite'] : '' ),
                        array( 'k' => 'payment_status',          'h' => 'Payment status',                 'v' => isset( $submission['payment_status'] ) ? $submission['payment_status'] : '' ),
                        array( 'k' => 'payment_method',          'h' => 'Payment method',                 'v' => isset( $submission['payment_method'] ) ? $submission['payment_method'] : '' ),
                        array( 'k' => 'payment_type',            'h' => 'Payment type',                   'v' => isset( $submission['payment_type'] ) ? $submission['payment_type'] : '' ),
                        array( 'k' => 'payment_currency',        'h' => 'Payment currency',               'v' => isset( $submission['currency'] ) ? $submission['currency'] : '' ),
                        array( 'k' => 'payment_total',           'h' => 'Payment total',                  'v' => isset( $submission['payment_total'] ) ? $submission['payment_total'] : '' ),
                        array( 'k' => 'total_paid',              'h' => 'Total paid',                     'v' => isset( $submission['total_paid'] ) ? $submission['total_paid'] : '' ),
                        array( 'k' => 'response_decode_status',  'h' => 'Response decode status',         'v' => $decode_status ),
                        array( 'k' => 'raw_response_truncated',  'h' => 'Raw response truncated',         'v' => $raw_truncated ? 'yes' : 'no' ),
                        array( 'k' => 'crm_contact_id',          'h' => 'CRM contact ID',                 'v' => is_object( $subscriber ) && isset( $subscriber->id ) ? $subscriber->id : '' ),
                        array( 'k' => 'crm_match_source',        'h' => 'CRM match source',               'v' => $match['source'] ),
                        array( 'k' => 'crm_match_detail',        'h' => 'CRM match detail',               'v' => $match['detail'] ),
                        array( 'k' => 'crm_status',              'h' => 'CRM status',                     'v' => $sub_prop( 'status' ) ),
                        array( 'k' => 'crm_contact_type',        'h' => 'CRM contact type',               'v' => $sub_prop( 'contact_type' ) ),
                        array( 'k' => 'crm_created_at',          'h' => 'CRM created at',                 'v' => $sub_prop( 'created_at' ) ),
                        array( 'k' => 'crm_last_activity',       'h' => 'CRM last activity',              'v' => $sub_prop( 'last_activity' ) ),
                        array( 'k' => 'lifecycle_tags',          'h' => 'Lifecycle tags',                 'v' => implode( ' | ', $membership['lifecycle'] ) ),
                        array( 'k' => 'source_tags',             'h' => 'Source tags',                    'v' => implode( ' | ', $membership['sources'] ) ),
                        array( 'k' => 'other_tags',              'h' => 'Other tags',                     'v' => implode( ' | ', $membership['other_tags'] ) ),
                        array( 'k' => 'lists',                   'h' => 'Lists',                          'v' => implode( ' | ', $membership['lists'] ) ),
                        array( 'k' => 'dnc',                     'h' => 'DNC',                            'v' => $membership['dnc'] ? 'yes' : 'no' ),
                        array( 'k' => 'first_name',              'h' => 'First name',                     'v' => $pick( $form_values['first_name'], $sub_prop( 'first_name' ) ) ),
                        array( 'k' => 'last_name',               'h' => 'Last name',                      'v' => $pick( $form_values['last_name'], $sub_prop( 'last_name' ) ) ),
                        array( 'k' => 'email',                   'h' => 'Email',                          'v' => $pick( $form_values['email'], $sub_prop( 'email' ) ) ),
                        array( 'k' => 'phone',                   'h' => 'Phone',                          'v' => $pick( $form_values['phone'], $sub_prop( 'phone' ) ) ),
                        array( 'k' => 'address_1',               'h' => 'Address line 1',                 'v' => $pick( $form_values['address_1'], $sub_prop( 'address_line_1' ) ) ),
                        array( 'k' => 'address_2',               'h' => 'Address line 2',                 'v' => $pick( $form_values['address_2'], $sub_prop( 'address_line_2' ) ) ),
                        array( 'k' => 'city',                    'h' => 'City',                           'v' => $pick( $form_values['city'], $sub_prop( 'city' ) ) ),
                        array( 'k' => 'state',                   'h' => 'State',                          'v' => $pick( $form_values['state'], $sub_prop( 'state' ) ) ),
                        array( 'k' => 'postal_code',             'h' => 'Postal code',                    'v' => $pick( $form_values['zip_code'], $sub_prop( 'postal_code' ) ) ),
                        array( 'k' => 'country',                 'h' => 'Country',                        'v' => $pick( $form_values['country'], $sub_prop( 'country' ) ) ),
                        array( 'k' => 'service_type',            'h' => 'Service type',                   'v' => $pick( $form_values['service_type'], $custom_value( 'service_type' ) ) ),
                        array( 'k' => 'estimated_value',         'h' => 'Estimated value',                'v' => $pick( $form_values['estimated_value'], $custom_value( 'estimated_value' ) ) ),
                        array( 'k' => 'invoiced_amount',         'h' => 'Invoiced amount',                'v' => $pick( $form_values['invoiced_amount'], $custom_value( 'invoiced_amount' ) ) ),
                        array( 'k' => 'closed_date',             'h' => 'Closed date',                    'v' => $pick( $form_values['closed_date'], $custom_value( 'closed_date' ) ) ),
                        array( 'k' => 'lost_date',               'h' => 'Lost date',                      'v' => $pick( $form_values['lost_date'], $custom_value( 'lost_date' ) ) ),
                        array( 'k' => 'gclid',                   'h' => 'GCLID',                          'v' => $pick( $form_values['gclid'], $custom_value( 'gclid' ) ) ),
                        array( 'k' => 'gbraid',                  'h' => 'GBRAID',                         'v' => $pick( $form_values['gbraid'], $custom_value( 'gbraid' ) ) ),
                        array( 'k' => 'wbraid',                  'h' => 'WBRAID',                         'v' => $pick( $form_values['wbraid'], $custom_value( 'wbraid' ) ) ),
                        array( 'k' => 'fbclid',                  'h' => 'FBCLID',                         'v' => $pick( $form_values['fbclid'], $custom_value( 'fbclid' ) ) ),
                        array( 'k' => 'msclkid',                 'h' => 'MSCLKID',                        'v' => $pick( $form_values['msclkid'], $custom_value( 'msclkid' ) ) ),
                        array( 'k' => 'utm_source',              'h' => 'UTM source',                     'v' => $pick( $form_values['utm_source'], $custom_value( 'utm_source' ) ) ),
                        array( 'k' => 'utm_medium',              'h' => 'UTM medium',                     'v' => $pick( $form_values['utm_medium'], $custom_value( 'utm_medium' ) ) ),
                        array( 'k' => 'utm_campaign',            'h' => 'UTM campaign',                   'v' => $pick( $form_values['utm_campaign'], $custom_value( 'utm_campaign' ) ) ),
                        array( 'k' => 'utm_content',             'h' => 'UTM content',                    'v' => $pick( $form_values['utm_content'], $custom_value( 'utm_content' ) ) ),
                        array( 'k' => 'utm_term',                'h' => 'UTM term',                       'v' => $pick( $form_values['utm_term'], $custom_value( 'utm_term' ) ) ),
                        array( 'k' => 'referrer_url',            'h' => 'Referrer URL',                   'v' => $pick( $form_values['referrer_url'], $custom_value( 'referrer_url' ) ) ),
                        array( 'k' => 'page_title',              'h' => 'Page title',                     'v' => $pick( $form_values['page_title'], $custom_value( 'page_title' ) ) ),
                        array( 'k' => 'ip_address',              'h' => 'IP address',                     'v' => $pick( $form_values['ip_address'], isset( $submission['ip'] ) ? $submission['ip'] : $sub_prop( 'ip' ) ) ),
                        array( 'k' => 'user_agent',              'h' => 'User agent',                     'v' => $pick( $form_values['user_agent'], isset( $submission['browser'] ) ? $submission['browser'] : '' ) ),
                        array( 'k' => 'cookie_value',            'h' => 'Cookie value',                   'v' => $pick( $form_values['cookie_value'], $custom_value( 'cookie_value' ) ) ),
                        array( 'k' => 'hidden_transaction_id',   'h' => 'Hidden transaction ID',          'v' => $pick( $form_values['transaction_id'], $custom_value( 'transaction_id' ) ) ),
                        array( 'k' => 'stored_submission_id',    'h' => 'Stored submission ID',           'v' => $pick( $form_values['submission_id'], $custom_value( 'submission_id' ) ) ),
                        array( 'k' => 'consent_receipt_id',      'h' => 'Consent receipt ID',             'v' => $consent['receipt_id'] ),
                        array( 'k' => 'consent_receipt_source',  'h' => 'Consent receipt ID source',      'v' => $consent['receipt_id_source'] ),
                        array( 'k' => 'consent_receipt_row_id',  'h' => 'Consent receipt row ID',         'v' => $consent['receipt_row_id'] ),
                        array( 'k' => 'consent_policy_version',  'h' => 'Consent policy version',         'v' => $consent['policy_version'] ),
                        array( 'k' => 'consent_banner_version',  'h' => 'Consent banner version',         'v' => $consent['banner_version'] ),
                        array( 'k' => 'required_policy_version', 'h' => 'Required policy version',        'v' => isset( $consent['required_policy'] ) ? $consent['required_policy'] : $consent['current_policy'] ),
                        array( 'k' => 'runtime_policy_version',  'h' => 'Runtime consent policy version', 'v' => isset( $consent['runtime_policy'] ) ? $consent['runtime_policy'] : '' ),
                        array( 'k' => 'consent_policy_check',    'h' => 'Consent policy check',           'v' => $consent['policy_check'] ),
                        array( 'k' => 'ad_user_data',            'h' => 'Ad user data',                   'v' => $consent['ad_user_data'] ),
                        array( 'k' => 'ad_personalization',      'h' => 'Ad personalization',             'v' => $consent['ad_personalization'] ),
                        array( 'k' => 'measurement_feed',        'h' => 'Ad measurement consent',         'v' => $consent['measurement_feed'] ),
                        array( 'k' => 'personalization_feed',    'h' => 'Ad personalization consent',     'v' => $consent['personalization_feed'] ),
                        array( 'k' => 'consent_decision',        'h' => 'Consent decision',               'v' => $consent['decision'] ),
                        array( 'k' => 'consent_block_reason',    'h' => 'Consent block reason',           'v' => $consent['block_reason'] ),
                        array( 'k' => 'google_consent_json',     'h' => 'Google consent JSON',            'v' => isset( $consent['google_consent_json'] ) ? $consent['google_consent_json'] : '' ),
                        array( 'k' => 'raw_submission_json',     'h' => 'Raw submission JSON',            'v' => $raw_cell ),
                        array( 'k' => 'se_cid',                  'h' => 'Google campaign ID',             'v' => $pick( $form_values['se_cid'], $custom_value( 'se_cid' ) ) ),
                        array( 'k' => 'se_agid',                 'h' => 'Google ad group ID',             'v' => $pick( $form_values['se_agid'], $custom_value( 'se_agid' ) ) ),
                        array( 'k' => 'se_adid',                 'h' => 'Google creative ID',             'v' => $pick( $form_values['se_adid'], $custom_value( 'se_adid' ) ) ),
                        array( 'k' => 'se_kw',                   'h' => 'Google matched keyword',         'v' => $pick( $form_values['se_kw'], $custom_value( 'se_kw' ) ) ),
                        array( 'k' => 'se_mt',                   'h' => 'Google match type',              'v' => $pick( $form_values['se_mt'], $custom_value( 'se_mt' ) ) ),
                        array( 'k' => 'se_net',                  'h' => 'Google network',                 'v' => $pick( $form_values['se_net'], $custom_value( 'se_net' ) ) ),
                        array( 'k' => 'se_dev',                  'h' => 'Google device',                  'v' => $pick( $form_values['se_dev'], $custom_value( 'se_dev' ) ) ),
                        array( 'k' => 'se_loc',                  'h' => 'Google physical location criterion ID', 'v' => $pick( $form_values['se_loc'], $custom_value( 'se_loc' ) ) ),
                        array( 'k' => 'customer_rating',         'h' => 'Customer Rating',                'v' => $custom_value( $reason_field ) ),
                        array( 'k' => 'qualified_at',            'h' => 'Qualified at',                   'v' => $custom_value( $qualified_field ) ),
                        array( 'k' => 'unqualified_at',          'h' => 'Unqualified at',                 'v' => $custom_value( $unqualified_field ) ),
                        array( 'k' => 'lifecycle_event',         'h' => 'Lifecycle event',                'v' => trim( (string) $lifecycle_event ) ),
                        array( 'k' => 'lifecycle_event_at',      'h' => 'Lifecycle event at',             'v' => trim( (string) $lifecycle_event_at ) ),
                        array( 'k' => 'inquiry_id',              'h' => 'Inquiry ID',                     'v' => $inquiry_id ),
                );

                $overrides = (array) $config->get( 'exports.all_leads.header_overrides', array() );
                $headers   = array();
                $values    = array();
                foreach ( $spec as $field ) {
                        $headers[] = isset( $overrides[ $field['k'] ] ) && '' !== (string) $overrides[ $field['k'] ] ? (string) $overrides[ $field['k'] ] : $field['h'];
                        $values[]  = $this->submission_value_to_string( $field['v'] );
                }

                return array(
                        'transaction_id' => $txn,
                        'contact_id'     => is_object( $subscriber ) && isset( $subscriber->id ) ? (int) $subscriber->id : 0,
                        'headers'        => $headers,
                        'values'         => $values,
                        'spec'           => $spec,
                        'consent'        => $consent,
                        'reasons'        => $reasons,
                        'exportable'     => empty( $reasons ),
                );
        }

        /**
         * Select inquiry-scoped stage candidates whose immutable transaction IDs are
         * not already appended to the requested sink. Archived inquiries remain
         * eligible so a stage reached immediately before a repeat-inquiry switch is
         * not lost between cron runs.
         *
         * @param string $stage         qualified|unqualified|won.
         * @param string $sink          Ledger sink.
         * @param int    $limit         Maximum candidates.
         * @param bool   $ledger_exists Whether ledger exists.
         * @param array  $contact_ids   Optional explicit contact IDs.
         * @param array  $legacy_sinks  Historical equivalent sinks.
         * @return array{inquiries:array,ledger_skipped:int}
         */
        private function select_unledgered_inquiries( $stage, $sink, $limit, $ledger_exists, $contact_ids = array(), $legacy_sinks = array() ) {
                $out     = array();
                $skipped = 0;
                if ( ! class_exists( 'SiteEngine_Ads_Bridge_Inquiries' ) || ! SiteEngine_Ads_Bridge_Inquiries::table_exists() ) {
                        return array( 'inquiries' => $out, 'ledger_skipped' => 0 );
                }

                $page_size = max( 50, min( 250, (int) $limit * 4 ) );
                $offset    = 0;
                $seen      = array();
                while ( count( $out ) < (int) $limit ) {
                        $rows = SiteEngine_Ads_Bridge_Inquiries::candidates( $stage, $page_size, $contact_ids, $offset );
                        if ( empty( $rows ) ) {
                                break;
                        }
                        foreach ( $rows as $row ) {
                                $inq_id = isset( $row['inquiry_id'] ) ? (string) $row['inquiry_id'] : '';
                                if ( '' === $inq_id || isset( $seen[ $inq_id ] ) ) {
                                        continue;
                                }
                                $seen[ $inq_id ] = true;
                                $txn = isset( $row['transaction_id'] ) ? trim( (string) $row['transaction_id'] ) : '';
                                if ( $ledger_exists && '' !== $txn ) {
                                        $dedupe_sinks = array_merge( array( $sink ), (array) $legacy_sinks );
                                        if ( $this->ledger_appended_in_any_sink( $dedupe_sinks, $txn ) ) {
                                                $skipped++;
                                                continue;
                                        }
                                }
                                $out[] = $row;
                                if ( count( $out ) >= (int) $limit ) {
                                        break;
                                }
                        }
                        if ( count( $rows ) < $page_size ) {
                                break;
                        }
                        $offset += $page_size;
                }
                return array( 'inquiries' => $out, 'ledger_skipped' => $skipped );
        }

        /**
         * Select up to the requested number of contacts whose current deterministic
         * Transaction ID has not already been appended to this sink. Contacts with
         * unresolved/non-deterministic submissions remain candidates so the exporter
         * can report the data defect rather than silently hiding it. This deliberately
         * dedupes by sink + current ff:{form_id}:{entry_id}, never lifetime contact ID.
         *
         * @param iterable $subscribers   Tagged contacts in deterministic order.
         * @param string   $sink          Ledger sink slug.
         * @param int      $limit         Maximum unledgered candidates to return.
         * @param bool     $ledger_exists Whether the ledger table exists.
         * @param array    $legacy_sinks  Optional historical sink slugs that should
         *                                also suppress this stage during migration.
         * @return array{subscribers:array,ledger_skipped:int}
         */
        private function select_current_unledgered_subscribers( $subscribers, $sink, $limit, $ledger_exists, $legacy_sinks = array() ) {
                $out     = array();
                $seen    = array();
                $skipped = 0;

                if ( ! is_iterable( $subscribers ) ) {
                        return array( 'subscribers' => $out, 'ledger_skipped' => 0 );
                }

                foreach ( $subscribers as $subscriber ) {
                        if ( ! is_object( $subscriber ) || ! isset( $subscriber->id ) ) {
                                continue;
                        }
                        $cid = (int) $subscriber->id;
                        if ( isset( $seen[ $cid ] ) ) {
                                continue;
                        }
                        $seen[ $cid ] = true;

                        // Once a contact has inquiry-aware attribution history, the
                        // immutable inquiry records own stage exports. Never fall back
                        // to the mutable current-contact submission_id for that contact.
                        if ( class_exists( 'SiteEngine_Ads_Bridge_Inquiries' ) && SiteEngine_Ads_Bridge_Inquiries::contact_has_history( $cid ) ) {
                                continue;
                        }

                        if ( $ledger_exists ) {
                                $custom   = method_exists( $subscriber, 'custom_fields' ) ? (array) $subscriber->custom_fields() : array();
                                $email    = isset( $subscriber->email ) ? (string) $subscriber->email : '';
                                $resolved = $this->resolve_won_order_id( $custom, $email );
                                $txn      = isset( $resolved['order_id'] ) ? (string) $resolved['order_id'] : '';
                                $dedupe_sinks = array_merge( array( $sink ), (array) $legacy_sinks );
                                if ( ! empty( $resolved['deterministic'] ) && preg_match( '/^ff:\d+:\d+$/', $txn ) && $this->ledger_appended_in_any_sink( $dedupe_sinks, $txn ) ) {
                                        $skipped++;
                                        continue;
                                }
                        }

                        $out[] = $subscriber;
                        if ( count( $out ) >= $limit ) {
                                break;
                        }
                }

                return array( 'subscribers' => $out, 'ledger_skipped' => $skipped );
        }

        /**
         * Discover contacts carrying one configured lost lifecycle tag. Ledger
         * filtering is intentionally not performed by contact ID; the caller applies
         * Transaction-ID dedupe after resolving each contact's current submission.
         *
         * @param string $tag_title Configured tag title.
         * @return array
         */
        private function discover_lost_subscribers( $tag_title ) {
                if ( '' === trim( (string) $tag_title ) ) {
                        WP_CLI::error( 'A lost lifecycle tag title is empty in client config.' );
                }

                $tag_id = $this->resolve_lifecycle_tag_id( $tag_title );
                $found  = array();
                try {
                        $model = new \FluentCrm\App\Models\Subscriber();
                        $table = (string) $model->getTable();
                        $found = \FluentCrm\App\Models\Subscriber::query()
                                ->filterByTags( array( $tag_id ) )
                                ->select( $table . '.*' )
                                ->distinct()
                                ->orderBy( $table . '.id', 'ASC' )
                                ->get();
                } catch ( \Throwable $e ) {
                        WP_CLI::error( 'Lost-tag candidate discovery failed (' . $e->getMessage() . ').' );
                }

                $out  = array();
                $seen = array();
                if ( is_iterable( $found ) ) {
                        foreach ( $found as $subscriber ) {
                                if ( is_object( $subscriber ) && isset( $subscriber->id ) && ! isset( $seen[ (int) $subscriber->id ] ) ) {
                                        $seen[ (int) $subscriber->id ] = true;
                                        $out[]                         = $subscriber;
                                }
                        }
                }
                return $out;
        }

        /**
         * Discover contacts with one non-empty FluentCRM custom field.
         *
         * @param string $slug Custom-field slug.
         * @return array
         */
        private function discover_custom_field_subscribers( $slug ) {
                global $wpdb;
                $slug = trim( (string) $slug );
                if ( '' === $slug ) {
                        return array();
                }
                $table = $wpdb->prefix . 'fc_subscriber_meta';
                $ids   = $wpdb->get_col(
                        $wpdb->prepare(
                                "SELECT DISTINCT subscriber_id FROM `{$table}` WHERE object_type = %s AND `key` = %s AND TRIM(value) <> '' ORDER BY subscriber_id ASC",
                                'custom_field',
                                $slug
                        )
                );
                $out = array();
                foreach ( is_array( $ids ) ? $ids : array() as $id ) {
                        $subscriber = \FluentCrm\App\Models\Subscriber::find( (int) $id );
                        if ( $subscriber ) {
                                $out[] = $subscriber;
                        }
                }
                return $out;
        }

        /**
         * Discover contacts currently carrying one configured lifecycle role.
         *
         * @param string $role Canonical lifecycle role.
         * @return array
         */
        private function discover_lifecycle_role_subscribers( $role ) {
                $role = trim( (string) $role );
                if ( ! in_array( $role, SiteEngine_Ads_Bridge_Config::lifecycle_roles(), true ) ) {
                        WP_CLI::error( sprintf( 'Unknown lifecycle role "%s".', $role ) );
                }

                $config = SiteEngine_Ads_Bridge_Config::instance();
                $title  = trim( (string) $config->get( 'fluentcrm.tags.lifecycle.' . $role, '' ) );
                if ( '' === $title ) {
                        WP_CLI::error( sprintf( 'Configured lifecycle tag for role "%s" is empty.', $role ) );
                }

                $tag_id = $this->resolve_lifecycle_tag_id( $title );
                try {
                        $model = new \FluentCrm\App\Models\Subscriber();
                        $table = (string) $model->getTable();
                        $found = \FluentCrm\App\Models\Subscriber::query()
                                ->filterByTags( array( $tag_id ) )
                                ->select( $table . '.*' )
                                ->distinct()
                                ->orderBy( $table . '.id', 'ASC' )
                                ->get();
                } catch ( \Throwable $e ) {
                        WP_CLI::error( sprintf( '%s lifecycle candidate discovery failed (%s).', ucfirst( $role ), $e->getMessage() ) );
                }

                $out  = array();
                $seen = array();
                foreach ( is_iterable( $found ) ? $found : array() as $subscriber ) {
                        if ( ! is_object( $subscriber ) || ! isset( $subscriber->id ) ) {
                                continue;
                        }
                        $cid = (int) $subscriber->id;
                        if ( isset( $seen[ $cid ] ) ) {
                                continue;
                        }
                        $seen[ $cid ] = true;
                        $out[]        = $subscriber;
                }
                return $out;
        }

        /**
         * Build one SE | Qualified Lead row for the shared Google import tab.
         *
         * @param object $subscriber  FluentCRM subscriber.
         * @param object $config      Config instance.
         * @param string $time_of_day Fallback time for date-only values.
         * @return array
         */
        private function build_qualified_lead_import( $subscriber, $config, $time_of_day, $inquiry = null ) {
                $email   = isset( $subscriber->email ) ? (string) $subscriber->email : '';
                $phone   = isset( $subscriber->phone ) ? (string) $subscriber->phone : '';
                $first   = isset( $subscriber->first_name ) ? (string) $subscriber->first_name : '';
                $last    = isset( $subscriber->last_name ) ? (string) $subscriber->last_name : '';
                $ip      = isset( $subscriber->ip ) ? (string) $subscriber->ip : '';
                $city    = isset( $subscriber->city ) ? (string) $subscriber->city : '';
                $postal  = isset( $subscriber->postal_code ) ? (string) $subscriber->postal_code : '';
                $state   = isset( $subscriber->state ) ? (string) $subscriber->state : '';
                $country = isset( $subscriber->country ) ? (string) $subscriber->country : '';
                $custom  = method_exists( $subscriber, 'custom_fields' ) ? (array) $subscriber->custom_fields() : array();
                if ( is_array( $inquiry ) ) {
                        foreach ( array( 'submission_id', 'qualified_at', 'unqualified_at', 'closed_date', 'invoiced_amount', 'customer_rating', 'ads_conversion_value_frozen' ) as $ikey ) {
                                if ( array_key_exists( $ikey, $inquiry ) ) {
                                        $custom[ $ikey ] = null === $inquiry[ $ikey ] ? '' : (string) $inquiry[ $ikey ];
                                }
                        }
                }
                $reasons = array();

                $resolved = is_array( $inquiry ) ? SiteEngine_Ads_Bridge_Inquiries::resolver_for_inquiry( $inquiry ) : $this->resolve_won_order_id( $custom, $email );
                $order_id = isset( $resolved['order_id'] ) ? (string) $resolved['order_id'] : '';
                $submission = null;
                if ( is_array( $inquiry ) ) {
                        $identity = $this->inquiry_submission_identity( $resolved, $config, array(
                                'email' => $email, 'phone' => $phone, 'first_name' => $first, 'last_name' => $last,
                                'city' => $city, 'postal_code' => $postal, 'state' => $state, 'country' => $country, 'ip' => $ip,
                        ) );
                        $email   = $identity['identity']['email'];
                        $phone   = $identity['identity']['phone'];
                        $first   = $identity['identity']['first_name'];
                        $last    = $identity['identity']['last_name'];
                        $city    = $identity['identity']['city'];
                        $postal  = $identity['identity']['postal_code'];
                        $state   = $identity['identity']['state'];
                        $country = $identity['identity']['country'];
                        $ip      = $identity['identity']['ip'];
                        $submission = $identity['submission'];
                }
                if ( empty( $resolved['exportable'] ) || empty( $resolved['deterministic'] ) ) {
                        $reasons[] = 'Order ID not deterministic (' . ( isset( $resolved['reason'] ) ? $resolved['reason'] : 'unresolved' ) . ')';
                }

                $date_field = trim( (string) $config->get( 'exports.qualified_leads.date_field', 'qualified_at' ) );
                $date_raw   = $this->get_contact_custom_value( $subscriber, $custom, $date_field );
                $date       = $this->build_terminal_datetime( $date_raw, $time_of_day, (string) $config->get( 'client.timezone', 'UTC' ) );
                if ( '' === $date_raw ) {
                        $reasons[] = $date_field . ' is not set';
                } elseif ( '' === $date['value'] ) {
                        $reasons[] = 'qualification time unresolved (' . $date['error'] . ')';
                }

                $membership = $this->subscriber_membership_snapshot( $subscriber, $config );
                if ( ! empty( $membership['dnc'] ) ) {
                        $reasons[] = 'DNC poison pill present: ' . $membership['dnc_name'];
                }
                if ( '' === trim( $email ) && '' === trim( $phone ) ) {
                        $reasons[] = 'email or phone present';
                }

                $consent = $this->resolve_consent_provenance( $resolved, $config );
                if ( '' !== $consent['block_reason'] ) {
                        $reasons[] = $consent['block_reason'];
                }

                if ( ! is_array( $submission ) ) {
                        $submission = $this->resolved_submission_context( $resolved );
                }
                $user_agent = $this->configured_form_response_value( $submission['response'], $config, 'user_agent' );
                $form_ip    = $this->configured_form_response_value( $submission['response'], $config, 'ip_address' );
                if ( '' !== $form_ip ) {
                        $ip = $form_ip;
                } elseif ( '' === $ip && isset( $submission['row']['ip'] ) ) {
                        $ip = (string) $submission['row']['ip'];
                }
                if ( '' === $user_agent && isset( $submission['row']['browser'] ) ) {
                        $user_agent = (string) $submission['row']['browser'];
                }

                $gclid  = $this->resolved_attribution_value( $resolved, $config, $custom, 'gclid', ! is_array( $inquiry ) );
                $gbraid = $this->resolved_attribution_value( $resolved, $config, $custom, 'gbraid', ! is_array( $inquiry ) );
                $wbraid = $this->resolved_attribution_value( $resolved, $config, $custom, 'wbraid', ! is_array( $inquiry ) );
                if ( '' !== $gbraid && '' !== $wbraid ) {
                        $wbraid = '';
                }

                $plugin_hash = (bool) $config->get( 'exports.qualified_leads.plugin_side_sha256', true );
                $emit = function ( $normalized ) use ( $plugin_hash ) {
                        return '' === $normalized ? '' : ( $plugin_hash ? hash( 'sha256', $normalized ) : $normalized );
                };

                $value_raw = trim( (string) $config->get( 'exports.qualified_leads.conversion_value', '' ) );
                $value     = '';
                if ( '' !== $value_raw ) {
                        if ( ! is_numeric( $value_raw ) || (float) $value_raw < 0 ) {
                                $reasons[] = 'qualified conversion value must be blank or non-negative';
                        } else {
                                $value = number_format( (float) $value_raw, 2, '.', '' );
                        }
                }

                $data = array(
                        'conversion_time'         => $date['value'],
                        'transaction_id'          => $order_id,
                        'gclid'                   => $gclid,
                        'gbraid'                  => $gbraid,
                        'wbraid'                  => $wbraid,
                        'email'                   => $emit( $this->normalize_email( $email ) ),
                        'phone'                   => $emit( $this->normalize_phone( $phone ) ),
                        'ip'                      => $ip,
                        'first_name'              => $emit( $this->normalize_name( $first ) ),
                        'last_name'               => $emit( $this->normalize_name( $last ) ),
                        'city'                    => $city,
                        'postal_code'             => $postal,
                        'region_code'             => $this->normalize_us_state( $state )['value'],
                        'country_code'            => $this->normalize_country( $country )['value'],
                        'conversion_value'        => $value,
                        'currency_code'           => (string) $config->get( 'client.currency', 'USD' ),
                        'measurement_consent'     => $consent['measurement_feed'],
                        'personalization_consent' => $consent['personalization_feed'],
                        'user_agent'              => $user_agent,
                        'session_attributes'      => '',
                        'conversion_action'       => (string) $config->get( 'exports.qualified_leads.conversion_action', 'SE | Qualified Lead' ),
                );
                $spec    = $this->data_manager_row_spec( $data );
                $headers = $this->headers_from_spec( $spec, (array) $config->get( 'exports.won_jobs.header_overrides', array() ) );
                $values  = array_map( 'strval', array_column( $spec, 'v' ) );

                return array(
                        'order_id'         => $order_id,
                        'conversion_time'  => $date['value'],
                        'conversion_value' => $value,
                        'consent'          => $consent,
                        'headers'          => $headers,
                        'values'           => $values,
                        'spec'             => $spec,
                        'reasons'          => $reasons,
                        'exportable'       => empty( $reasons ),
                );
        }

        /**
         * Prefer identity captured on an inquiry's immutable Fluent Forms binding.
         * Current FluentCRM identity is only a fallback for fields the submission did
         * not capture. This prevents a later repeat inquiry/contact edit from changing
         * identifiers on an older not-yet-exported inquiry.
         *
         * @param array  $resolved Deterministic inquiry resolver.
         * @param object $config   Config instance.
         * @param array  $fallback Current contact identity.
         * @return array{identity:array,submission:array}
         */
        private function inquiry_submission_identity( $resolved, $config, $fallback ) {
                $submission = $this->resolved_submission_context( $resolved );
                $identity   = is_array( $fallback ) ? $fallback : array();
                $map = array(
                        'email'       => 'email',
                        'phone'       => 'phone',
                        'first_name'  => 'first_name',
                        'last_name'   => 'last_name',
                        'city'        => 'city',
                        'postal_code' => 'zip_code',
                        'state'       => 'state',
                        'country'     => 'country',
                        'ip'          => 'ip_address',
                );
                foreach ( $map as $target => $role ) {
                        $value = trim( (string) $this->configured_form_response_value( $submission['response'], $config, $role ) );
                        if ( '' !== $value ) {
                                $identity[ $target ] = $value;
                        }
                }
                if ( empty( $identity['ip'] ) && isset( $submission['row']['ip'] ) && '' !== trim( (string) $submission['row']['ip'] ) ) {
                        $identity['ip'] = (string) $submission['row']['ip'];
                }
                return array( 'identity' => $identity, 'submission' => $submission );
        }

        /**
         * Load the canonical submission row and decoded response represented by a
         * deterministic resolver result.
         *
         * @param array $resolved Resolver result.
         * @return array{row:array,response:array}
         */
        private function resolved_submission_context( $resolved ) {
                global $wpdb;
                $out   = array( 'row' => array(), 'response' => array() );
                $table = isset( $resolved['submissions_table'] ) ? (string) $resolved['submissions_table'] : '';
                $id    = isset( $resolved['entry_id'] ) ? (int) $resolved['entry_id'] : 0;
                if ( $id < 1 || ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
                        return $out;
                }
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), ARRAY_A );
                if ( ! is_array( $row ) ) {
                        return $out;
                }
                $out['row'] = $row;
                if ( isset( $row['response'] ) && is_string( $row['response'] ) ) {
                        $response = json_decode( $row['response'], true );
                        if ( is_array( $response ) ) {
                                $out['response'] = $response;
                        }
                }
                return $out;
        }

        /**
         * Resolve an attribution identifier from the canonical Fluent Forms submission
         * first, falling back to the FluentCRM custom-field copy only when the form
         * response is blank.
         *
         * @param array  $resolved Deterministic submission resolver result.
         * @param object $config   Config instance.
         * @param array  $custom   FluentCRM custom fields.
         * @param string $key      Canonical attribution key.
         * @return string
         */
        private function resolved_attribution_value( $resolved, $config, $custom, $key, $allow_custom_fallback = true ) {
                $submission = $this->resolved_submission_context( $resolved );
                $value      = trim( $this->configured_form_response_value( $submission['response'], $config, $key ) );
                if ( '' !== $value ) {
                        return $value;
                }
                if ( ! $allow_custom_fallback ) {
                        return '';
                }
                return isset( $custom[ $key ] ) ? trim( (string) $custom[ $key ] ) : '';
        }

        /**
         * Build a configured date plus CLI time-of-day in the client timezone.
         *
         * @param string $raw         Raw date/date-time.
         * @param string $time_of_day HH:MM:SS fallback time.
         * @param string $timezone    IANA timezone.
         * @return array{value:string,error:string}
         */
        private function build_terminal_datetime( $raw, $time_of_day, $timezone ) {
                $out = array( 'value' => '', 'error' => '' );
                try {
                        $tz = new \DateTimeZone( $timezone );
                } catch ( \Exception $e ) {
                        $out['error'] = 'invalid configured timezone "' . $timezone . '"';
                        return $out;
                }

                $raw = trim( (string) $raw );
                if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}:\d{2}))?/', $raw, $match ) ) {
                        $out['error'] = 'terminal date "' . $raw . '" is not parseable as Y-m-d';
                        return $out;
                }
                $time = ! empty( $match[2] ) ? $match[2] : $time_of_day;
                $dt   = \DateTime::createFromFormat( 'Y-m-d H:i:s', $match[1] . ' ' . $time, $tz );
                if ( false === $dt ) {
                        $out['error'] = 'could not build terminal datetime';
                        return $out;
                }
                $out['value'] = $dt->format( 'Y-m-d H:i:sP' );
                return $out;
        }

        /**
         * Build one reason-specific lost conversion row.
         *
         * @param object $subscriber  FluentCRM subscriber.
         * @param object $config      Config instance.
         * @param string $reason      Lost reason key.
         * @param string $time_of_day CLI terminal time.
         * @return array
         */
        private function build_lost_lead_import( $subscriber, $config, $reason, $time_of_day ) {
                $email   = isset( $subscriber->email ) ? (string) $subscriber->email : '';
                $phone   = isset( $subscriber->phone ) ? (string) $subscriber->phone : '';
                $first   = isset( $subscriber->first_name ) ? (string) $subscriber->first_name : '';
                $last    = isset( $subscriber->last_name ) ? (string) $subscriber->last_name : '';
                $ip      = isset( $subscriber->ip ) ? (string) $subscriber->ip : '';
                $city    = isset( $subscriber->city ) ? (string) $subscriber->city : '';
                $postal  = isset( $subscriber->postal_code ) ? (string) $subscriber->postal_code : '';
                $state   = isset( $subscriber->state ) ? (string) $subscriber->state : '';
                $country = isset( $subscriber->country ) ? (string) $subscriber->country : '';

                $custom = array();
                if ( method_exists( $subscriber, 'custom_fields' ) ) {
                        $custom = (array) $subscriber->custom_fields();
                }
                $membership = $this->subscriber_membership_snapshot( $subscriber, $config );
                $required   = (string) $config->get( 'fluentcrm.tags.lifecycle.lost_' . $reason, '' );
                $reasons    = array();

                if ( ! in_array( $required, $membership['lifecycle'], true ) ) {
                        $reasons[] = 'required lost lifecycle tag not present: ' . $required;
                }
                if ( 1 !== count( $membership['lifecycle'] ) ) {
                        $reasons[] = 0 === count( $membership['lifecycle'] )
                                ? 'no configured lifecycle tag present'
                                : 'lifecycle conflict: ' . implode( ' + ', $membership['lifecycle'] );
                }
                if ( $membership['dnc'] ) {
                        $reasons[] = 'DNC poison pill present: ' . $membership['dnc_name'];
                }
                if ( '' === trim( $email ) && '' === trim( $phone ) ) {
                        $reasons[] = 'neither email nor phone is present';
                }

                $resolved = $this->resolve_won_order_id( $custom, $email );
                $order_id = (string) $resolved['order_id'];
                if ( empty( $resolved['exportable'] ) || empty( $resolved['deterministic'] ) ) {
                        $reasons[] = 'Order ID not deterministic (' . $resolved['reason'] . ')';
                }

                $date_field = trim( (string) $config->get( 'exports.lost_leads.date_field', 'lost_date' ) );
                $date_raw   = $this->get_contact_custom_value( $subscriber, $custom, $date_field );
                if ( '' === trim( $date_raw ) ) {
                        $reasons[] = $date_field . ' is not set';
                }
                $date = $this->build_terminal_datetime( $date_raw, $time_of_day, (string) $config->get( 'client.timezone', 'UTC' ) );
                if ( '' === $date['value'] ) {
                        $reasons[] = 'conversion time unresolved (' . $date['error'] . ')';
                }

                $value_raw = $config->get(
                        'exports.lost_leads.reasons.' . $reason . '.conversion_value',
                        $config->get( 'exports.lost_leads.conversion_value', '0.00' )
                );
                if ( ! is_numeric( $value_raw ) || (float) $value_raw < 0 ) {
                        $reasons[] = 'configured lost conversion value must be numeric and non-negative';
                        $value = '';
                } else {
                        $value = number_format( (float) $value_raw, 2, '.', '' );
                }

                $consent = $this->resolve_consent_provenance( $resolved, $config );
                if ( '' !== $consent['block_reason'] ) {
                        $reasons[] = $consent['block_reason'];
                }

                $submission = $this->resolved_submission_context( $resolved );
                $user_agent = $this->configured_form_response_value( $submission['response'], $config, 'user_agent' );
                if ( '' === $user_agent && isset( $submission['row']['browser'] ) ) {
                        $user_agent = (string) $submission['row']['browser'];
                }
                $form_ip = $this->configured_form_response_value( $submission['response'], $config, 'ip_address' );
                if ( '' !== $form_ip ) {
                        $ip = $form_ip;
                } elseif ( '' === $ip && isset( $submission['row']['ip'] ) ) {
                        $ip = (string) $submission['row']['ip'];
                }

                $plugin_hash = (bool) $config->get( 'exports.lost_leads.plugin_side_sha256', true );
                $emit = function ( $normalized ) use ( $plugin_hash ) {
                        return '' === $normalized ? '' : ( $plugin_hash ? hash( 'sha256', $normalized ) : $normalized );
                };

                $lost_gclid  = $this->resolved_attribution_value( $resolved, $config, $custom, 'gclid' );
                $lost_gbraid = $this->resolved_attribution_value( $resolved, $config, $custom, 'gbraid' );
                $lost_wbraid = $this->resolved_attribution_value( $resolved, $config, $custom, 'wbraid' );
                if ( '' !== $lost_gbraid && '' !== $lost_wbraid ) {
                        $lost_wbraid = '';
                }
                $lost_tracking = array();
                foreach ( array( 'se_cid', 'se_agid', 'se_adid', 'se_kw', 'se_mt', 'se_net', 'se_dev', 'se_loc' ) as $tracking_key ) {
                        $lost_tracking[ $tracking_key ] = $this->resolved_attribution_value( $resolved, $config, $custom, $tracking_key );
                }

                $data = array(
                        'conversion_time'        => $date['value'],
                        'transaction_id'         => $order_id,
                        'gclid'                  => $lost_gclid,
                        'gbraid'                 => $lost_gbraid,
                        'wbraid'                 => $lost_wbraid,
                        'email'                  => $emit( $this->normalize_email( $email ) ),
                        'phone'                  => $emit( $this->normalize_phone( $phone ) ),
                        'ip'                     => $ip,
                        'first_name'             => $emit( $this->normalize_name( $first ) ),
                        'last_name'              => $emit( $this->normalize_name( $last ) ),
                        'city'                   => $city,
                        'postal_code'            => $postal,
                        'region_code'            => $this->normalize_us_state( $state )['value'],
                        'country_code'           => $this->normalize_country( $country )['value'],
                        'conversion_value'       => $value,
                        'currency_code'          => (string) $config->get( 'client.currency', 'USD' ),
                        'measurement_consent'    => $consent['measurement_feed'],
                        'personalization_consent'=> $consent['personalization_feed'],
                        'user_agent'             => $user_agent,
                        'session_attributes'     => '',
                        'conversion_action'      => '',
                        'se_cid'                 => $lost_tracking['se_cid'],
                        'se_agid'                => $lost_tracking['se_agid'],
                        'se_adid'                => $lost_tracking['se_adid'],
                        'se_kw'                  => $lost_tracking['se_kw'],
                        'se_mt'                  => $lost_tracking['se_mt'],
                        'se_net'                 => $lost_tracking['se_net'],
                        'se_dev'                 => $lost_tracking['se_dev'],
                        'se_loc'                 => $lost_tracking['se_loc'],
                );
                $spec = $this->lost_diagnostic_row_spec( $data );

                $global_overrides = (array) $config->get( 'exports.lost_leads.header_overrides', array() );
                $reason_overrides = (array) $config->get( 'exports.lost_leads.reasons.' . $reason . '.header_overrides', array() );
                $overrides        = array_merge( $global_overrides, $reason_overrides );
                $headers          = array();
                $values           = array();
                foreach ( $spec as $field ) {
                        $headers[] = isset( $overrides[ $field['k'] ] ) && '' !== (string) $overrides[ $field['k'] ] ? (string) $overrides[ $field['k'] ] : $field['h'];
                        $values[]  = (string) $field['v'];
                }

                return array(
                        'order_id'          => $order_id,
                        'resolved'          => $resolved,
                        'reason'            => $reason,
                        'conversion_action' => '',
                        'date_field'        => $date_field,
                        'date_raw'          => $date_raw,
                        'conversion_time'   => $date['value'],
                        'conversion_value'  => $value,
                        'consent'           => $consent,
                        'headers'           => $headers,
                        'values'            => $values,
                        'spec'              => $spec,
                        'reasons'           => $reasons,
                        'exportable'        => empty( $reasons ),
                );
        }

        /**
         * Internal lost-lead diagnostic contract. The first 21 columns mirror the
         * Google import contract for operator familiarity; the trailing SiteEngine
         * ValueTrack columns are diagnostic only and are never uploaded as conversions.
         *
         * @param array $data Keyed values.
         * @return array
         */
        private function lost_diagnostic_row_spec( $data ) {
                $spec = $this->data_manager_row_spec( $data );
                foreach ( array(
                        'se_cid'  => 'Google campaign ID',
                        'se_agid' => 'Google ad group ID',
                        'se_adid' => 'Google creative ID',
                        'se_kw'   => 'Google matched keyword',
                        'se_mt'   => 'Google match type',
                        'se_net'  => 'Google network',
                        'se_dev'  => 'Google device',
                        'se_loc'  => 'Google physical location criterion ID',
                ) as $key => $header ) {
                        $spec[] = array( 'k' => $key, 'h' => $header, 'v' => isset( $data[ $key ] ) ? $data[ $key ] : '' );
                }
                return $spec;
        }

        /**
         * The fixed 21-column Google Ads Data Manager row contract.
         *
         * @param array $data Keyed values.
         * @return array
         */
        private function data_manager_row_spec( $data ) {
                return array(
                        array( 'k' => 'conversion_time',            'h' => 'Conversion date/time',       'v' => $data['conversion_time'] ),
                        array( 'k' => 'transaction_id',             'h' => 'Transaction ID',             'v' => $data['transaction_id'] ),
                        array( 'k' => 'gclid',                      'h' => 'GCLID',                      'v' => $data['gclid'] ),
                        array( 'k' => 'gbraid',                     'h' => 'GBRAID',                     'v' => $data['gbraid'] ),
                        array( 'k' => 'wbraid',                     'h' => 'WBRAID',                     'v' => $data['wbraid'] ),
                        array( 'k' => 'email',                      'h' => 'Email address',              'v' => $data['email'] ),
                        array( 'k' => 'phone',                      'h' => 'Phone number',               'v' => $data['phone'] ),
                        array( 'k' => 'ip',                         'h' => 'IP address',                 'v' => $data['ip'] ),
                        array( 'k' => 'first_name',                 'h' => 'First name',                 'v' => $data['first_name'] ),
                        array( 'k' => 'last_name',                  'h' => 'Last name',                  'v' => $data['last_name'] ),
                        array( 'k' => 'city',                       'h' => 'City',                       'v' => $data['city'] ),
                        array( 'k' => 'postal_code',                'h' => 'Postal code',                'v' => $data['postal_code'] ),
                        array( 'k' => 'region_code',                'h' => 'State or region code',       'v' => $data['region_code'] ),
                        array( 'k' => 'country_code',               'h' => 'Country code',               'v' => $data['country_code'] ),
                        array( 'k' => 'conversion_value',           'h' => 'Conversion value',           'v' => $data['conversion_value'] ),
                        array( 'k' => 'currency_code',              'h' => 'Currency code',              'v' => $data['currency_code'] ),
                        array( 'k' => 'ad_measurement_consent',     'h' => 'Ad measurement consent',     'v' => $data['measurement_consent'] ),
                        array( 'k' => 'ad_personalization_consent', 'h' => 'Ad personalization consent', 'v' => $data['personalization_consent'] ),
                        array( 'k' => 'user_agent',                 'h' => 'User agent',                 'v' => $data['user_agent'] ),
                        array( 'k' => 'session_attributes',         'h' => 'Session attributes',         'v' => $data['session_attributes'] ),
                        array( 'k' => 'conversion_action',          'h' => 'Conversion action',           'v' => isset( $data['conversion_action'] ) ? $data['conversion_action'] : '' ),
                );
        }

        /**
         * Validate Sheet configuration and obtain one service-account token.
         *
         * @param object $config Config instance.
         * @param string $tab    Target tab.
         * @return array
         */
        private function prepare_family_sheet_context( $config, $tab ) {
                $missing        = array();
                $enabled        = (bool) $config->get( 'google_sheets.enabled', false );
                $spreadsheet_id = trim( (string) $config->get( 'google_sheets.spreadsheet_id', '' ) );
                $creds_path     = trim( (string) $config->get( 'google_sheets.credentials_path', '' ) );

                if ( ! $enabled ) {
                        $missing[] = 'google_sheets.enabled (must be true)';
                }
                if ( '' === $spreadsheet_id ) {
                        $missing[] = 'google_sheets.spreadsheet_id';
                }
                if ( '' === trim( $tab ) ) {
                        $missing[] = 'target sheet tab';
                }
                if ( '' === $creds_path ) {
                        $missing[] = 'google_sheets.credentials_path';
                } elseif ( ! is_readable( $creds_path ) ) {
                        $missing[] = 'credentials file is not readable at ' . $creds_path;
                }
                if ( ! empty( $missing ) ) {
                        foreach ( $missing as $item ) {
                                WP_CLI::line( 'Missing: ' . $item . '.' );
                        }
                        WP_CLI::error( 'Google Sheet append is not configured; failing closed. Nothing was written.' );
                }

                $creds = json_decode( (string) file_get_contents( $creds_path ), true );
                if ( ! is_array( $creds ) || empty( $creds['client_email'] ) || empty( $creds['private_key'] ) ) {
                        WP_CLI::error( sprintf( 'Service-account JSON at "%s" is missing client_email/private_key.', $creds_path ) );
                }
                $token = $this->google_sa_access_token( $creds );
                if ( ! $token['ok'] ) {
                        WP_CLI::error( 'Could not obtain a Google access token: ' . $token['error'] . '. Nothing written.' );
                }

                return array(
                        'spreadsheet_id' => $spreadsheet_id,
                        'token'          => $token['token'],
                        'vio'            => (string) $config->get( 'google_sheets.value_input_option', 'RAW' ),
                );
        }

        /**
         * Complete a prepared family batch using the shared append-log ledger.
         *
         * @param string $label     Command/family label.
         * @param string $sink      Ledger sink slug.
         * @param string $tab       Target tab.
         * @param bool   $dry       Dry-run mode.
         * @param object $config    Config instance.
         * @param array  $report    Existing report rows.
         * @param array  $count     Counters.
         * @param array  $to_append Prepared rows.
         * @return void
         */
        private function complete_family_sheet_batch( $label, $sink, $tab, $dry, $config, $report, $count, $to_append ) {
                if ( $dry ) {
                        foreach ( $to_append as $item ) {
                                $report[] = array(
                                        'record'         => $item['record'],
                                        'transaction_id' => $item['txn'],
                                        'exportable'     => 'yes',
                                        'action'         => 'would_append',
                                        'detail'         => 'dry-run: nothing written',
                                );
                        }
                        $this->print_family_batch_summary( $report, $count, count( $to_append ), true );
                        WP_CLI::success( $label . ' dry-run complete. No Sheet write, no ledger write, no CRM mutation.' );
                        return;
                }

                if ( empty( $to_append ) ) {
                        $this->print_family_batch_summary( $report, $count, 0, false );
                        WP_CLI::line( '' );
                        WP_CLI::line( 'Nothing to append. No Sheet write performed.' );
                        WP_CLI::success( $label . ' --apply complete; 0 rows appended.' );
                        return;
                }

                if ( ! $this->ensure_ledger_table() ) {
                        WP_CLI::error( sprintf( 'Could not create/verify ledger table %s; failing closed.', $this->ledger_table() ) );
                }

                $sheet   = $this->prepare_family_sheet_context( $config, $tab );
                $orphans = array();

                foreach ( $to_append as $item ) {
                        // Recheck after token acquisition in case another process appended
                        // the same transaction between candidate preparation and this write.
                        if ( $this->ledger_appended_count( $sink, $item['txn'] ) > 0 ) {
                                $count['already_appended']++;
                                $report[] = array(
                                        'record'         => $item['record'],
                                        'transaction_id' => $item['txn'],
                                        'exportable'     => 'yes',
                                        'action'         => 'skipped',
                                        'detail'         => 'already appended to this sink (ledger recheck)',
                                );
                                continue;
                        }

                        $append     = $this->google_sheets_append_row( $sheet['spreadsheet_id'], $tab, $item['values'], $sheet['token'], $sheet['vio'] );
                        $attempt_no = $this->ledger_attempt_count( $sink, $item['txn'] ) + 1;

                        if ( $append['ok'] ) {
                                $ledger = $this->ledger_insert(
                                        array(
                                                'sink_slug'      => $sink,
                                                'contact_id'     => $item['cid'],
                                                'transaction_id' => $item['txn'],
                                                'row_hash'       => $item['row_hash'],
                                                'spreadsheet_id' => $sheet['spreadsheet_id'],
                                                'tab'            => $tab,
                                                'updated_range'  => (string) $append['updated_range'],
                                                'status'         => 'appended',
                                                'error'          => '',
                                                'attempts'       => $attempt_no,
                                        )
                                );
                                if ( $ledger['ok'] ) {
                                        $count['appended']++;
                                        $report[] = array(
                                                'record'         => $item['record'],
                                                'transaction_id' => $item['txn'],
                                                'exportable'     => 'yes',
                                                'action'         => 'appended',
                                                'detail'         => '' !== (string) $append['updated_range'] ? (string) $append['updated_range'] : '(range not reported)',
                                        );
                                } else {
                                        $orphans[] = array(
                                                'record'         => $item['record'],
                                                'transaction_id' => $item['txn'],
                                                'updated_range'  => (string) $append['updated_range'],
                                                'tab'            => $tab,
                                                'db_error'       => $ledger['error'],
                                        );
                                        $report[] = array(
                                                'record'         => $item['record'],
                                                'transaction_id' => $item['txn'],
                                                'exportable'     => 'yes',
                                                'action'         => 'ORPHAN (appended, NOT ledgered)',
                                                'detail'         => (string) $append['updated_range'] . ' | ledger error: ' . $ledger['error'],
                                        );
                                }
                        } else {
                                $count['failed']++;
                                $ledger = $this->ledger_insert(
                                        array(
                                                'sink_slug'      => $sink,
                                                'contact_id'     => $item['cid'],
                                                'transaction_id' => $item['txn'],
                                                'row_hash'       => $item['row_hash'],
                                                'spreadsheet_id' => $sheet['spreadsheet_id'],
                                                'tab'            => $tab,
                                                'updated_range'  => '',
                                                'status'         => 'failed',
                                                'error'          => (string) $append['error'],
                                                'attempts'       => $attempt_no,
                                        )
                                );
                                if ( ! $ledger['ok'] ) {
                                        WP_CLI::warning( sprintf( 'Failed-attempt ledger write did not persist for %s (%s): %s.', $item['record'], $item['txn'], $ledger['error'] ) );
                                }
                                $report[] = array(
                                        'record'         => $item['record'],
                                        'transaction_id' => $item['txn'],
                                        'exportable'     => 'yes',
                                        'action'         => 'failed',
                                        'detail'         => (string) $append['error'],
                                );
                        }
                }

                $this->print_family_batch_summary( $report, $count, $count['appended'], false );
                WP_CLI::line( '' );
                WP_CLI::line( 'Google Ads API upload DISABLED. No CRM mutation. Sheet export is append-only.' );

                if ( ! empty( $orphans ) ) {
                        WP_CLI::line( '' );
                        WP_CLI::line( '==================== ORPHAN ROW RISK ====================' );
                        WP_CLI\Utils\format_items( 'table', $orphans, array( 'record', 'transaction_id', 'updated_range', 'tab', 'db_error' ) );
                        WP_CLI::error( sprintf( '%s finished with %d Sheet row(s) lacking a confirmed ledger insert. Use ledger-admin reconcile with the listed evidence before rerunning.', $label, count( $orphans ) ) );
                }

                WP_CLI::success( sprintf( '%s --apply complete; %d appended, %d failed, %d skipped.', $label, $count['appended'], $count['failed'], $count['already_appended'] ) );
        }

        /**
         * Print a prepared family batch report.
         *
         * @param array $report       Report rows.
         * @param array $count        Counters.
         * @param int   $append_total Appended/would-append total.
         * @param bool  $dry          Dry-run mode.
         * @return void
         */
        private function print_family_batch_summary( $report, $count, $append_total, $dry ) {
                WP_CLI::line( 'Per-row:' );
                if ( empty( $report ) ) {
                        WP_CLI::line( '  (no candidates matched)' );
                } else {
                        WP_CLI\Utils\format_items( 'table', $report, array( 'record', 'transaction_id', 'exportable', 'action', 'detail' ) );
                }
                WP_CLI::line( '' );
                WP_CLI::line( 'Summary:' );
                WP_CLI::line( '  candidates found:         ' . (int) $count['candidates'] );
                WP_CLI::line( '  exportable:               ' . (int) $count['exportable'] );
                WP_CLI::line( '  already-appended skipped: ' . (int) $count['already_appended'] );
                WP_CLI::line( '  ' . ( $dry ? 'would append:             ' : 'appended:                 ' ) . (int) $append_total );
                if ( ! $dry ) {
                        WP_CLI::line( '  failed:                   ' . (int) $count['failed'] );
                }
                WP_CLI::line( '  not exportable:           ' . (int) $count['not_exportable'] );
        }

        /**
         * Mint a Google OAuth2 access token from a service-account JSON via the JWT
         * bearer flow, scoped to Sheets. Dependency-free: builds and RS256-signs the
         * JWT with openssl and exchanges it over the WordPress HTTP API. No Composer /
         * google-api-php-client required.
         *
         * @param array $creds Decoded service-account JSON (needs client_email, private_key).
         * @return array{ok:bool,token:string,error:string}
         */
        private function google_sa_access_token( $creds ) {
                $out = array( 'ok' => false, 'token' => '', 'error' => '' );

                if ( ! function_exists( 'openssl_sign' ) ) {
                        $out['error'] = 'openssl extension unavailable; cannot sign the service-account JWT.';
                        return $out;
                }

                $token_uri = ! empty( $creds['token_uri'] ) ? (string) $creds['token_uri'] : 'https://oauth2.googleapis.com/token';
                $scope     = 'https://www.googleapis.com/auth/spreadsheets';
                $now       = time();

                $b64url = function ( $data ) {
                        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
                };

                $header_seg = $b64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
                $claim_seg  = $b64url(
                        wp_json_encode(
                                array(
                                        'iss'   => (string) $creds['client_email'],
                                        'scope' => $scope,
                                        'aud'   => $token_uri,
                                        'iat'   => $now,
                                        'exp'   => $now + 3600,
                                )
                        )
                );

                $signing_input = $header_seg . '.' . $claim_seg;
                $signature     = '';
                $signed        = openssl_sign( $signing_input, $signature, (string) $creds['private_key'], OPENSSL_ALGO_SHA256 );
                if ( ! $signed ) {
                        $out['error'] = 'openssl_sign failed (check the private_key in the service-account JSON).';
                        return $out;
                }
                $jwt = $signing_input . '.' . $b64url( $signature );

                $response = wp_remote_post(
                        $token_uri,
                        array(
                                'timeout' => 20,
                                'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
                                'body'    => array(
                                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                                        'assertion'  => $jwt,
                                ),
                        )
                );

                if ( is_wp_error( $response ) ) {
                        $out['error'] = 'token request transport error: ' . $response->get_error_message();
                        return $out;
                }

                $code = (int) wp_remote_retrieve_response_code( $response );
                $body = (string) wp_remote_retrieve_body( $response );
                $json = json_decode( $body, true );

                if ( 200 !== $code || ! is_array( $json ) || empty( $json['access_token'] ) ) {
                        $err = ( is_array( $json ) && isset( $json['error_description'] ) ) ? (string) $json['error_description'] : ( '' !== $body ? substr( $body, 0, 300 ) : 'no body' );
                        $out['error'] = sprintf( 'token endpoint returned HTTP %d: %s', $code, $err );
                        return $out;
                }

                $out['ok']    = true;
                $out['token'] = (string) $json['access_token'];
                return $out;
        }

        /**
         * Append a single row of values to a Google Sheet tab via the Sheets v4
         * values:append endpoint over the WordPress HTTP API. Append-only
         * (INSERT_ROWS). Writes nothing else; touches no Google Ads surface.
         *
         * @param string $spreadsheet_id Target spreadsheet id.
         * @param string $tab            Worksheet/tab name.
         * @param array  $values         Flat ordered list of cell values (one row).
         * @param string $access_token   OAuth2 bearer token.
         * @param string $vio            valueInputOption (RAW or USER_ENTERED).
         * @return array{ok:bool,updated_range:string,updated_rows:int,error:string}
         */
        private function google_sheets_append_row( $spreadsheet_id, $tab, $values, $access_token, $vio = 'RAW' ) {
                $out = array( 'ok' => false, 'updated_range' => '', 'updated_rows' => 0, 'error' => '' );

                $vio = in_array( $vio, array( 'RAW', 'USER_ENTERED' ), true ) ? $vio : 'RAW';

                $range = rawurlencode( $tab . '!A1' );
                $url   = sprintf(
                        'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=%s&insertDataOption=INSERT_ROWS',
                        rawurlencode( $spreadsheet_id ),
                        $range,
                        $vio
                );

                $payload = wp_json_encode( array( 'values' => array( array_values( $values ) ) ) );

                $response = wp_remote_post(
                        $url,
                        array(
                                'timeout' => 20,
                                'headers' => array(
                                        'Authorization' => 'Bearer ' . $access_token,
                                        'Content-Type'  => 'application/json',
                                ),
                                'body'    => $payload,
                        )
                );

                if ( is_wp_error( $response ) ) {
                        $out['error'] = 'append transport error: ' . $response->get_error_message();
                        return $out;
                }

                $code = (int) wp_remote_retrieve_response_code( $response );
                $body = (string) wp_remote_retrieve_body( $response );
                $json = json_decode( $body, true );

                if ( 200 !== $code ) {
                        $err = ( is_array( $json ) && isset( $json['error']['message'] ) ) ? (string) $json['error']['message'] : ( '' !== $body ? substr( $body, 0, 300 ) : 'no body' );
                        $out['error'] = sprintf( 'Sheets API returned HTTP %d: %s', $code, $err );
                        return $out;
                }

                if ( is_array( $json ) && isset( $json['updates'] ) && is_array( $json['updates'] ) ) {
                        $out['updated_range'] = isset( $json['updates']['updatedRange'] ) ? (string) $json['updates']['updatedRange'] : '';
                        $out['updated_rows']  = isset( $json['updates']['updatedRows'] ) ? (int) $json['updates']['updatedRows'] : 0;
                }

                $out['ok'] = true;
                return $out;
        }

        /**
         * Inspect FluentCRM custom field definitions and the values stored on one
         * contact (read-only). Built to diagnose slug mismatches — notably
         * FluentCRM's 25-character slug truncation — by listing the actual defined
         * slugs and the contact's stored values side by side, focused on consent /
         * Google-ad / analytics fields.
         *
         * Read-only: no CRM mutation, no form mutation, no Google/Sheet write. Scope:
         * FluentCRM-defined fields via fluentcrm_get_custom_contact_fields() and the
         * contact's values via Subscriber::custom_fields(). A value written to a meta
         * key with no matching field definition is out of scope for this read.
         *
         * Registered as:
         * wp se-ads inspect-contact-fields --id=<contact_id>
         * (alias: wp se-ads inspect_contact_fields)
         *
         * ## OPTIONS
         *
         * --id=<contact_id>
         * : The FluentCRM contact (subscriber) id to inspect.
         *
         * @subcommand inspect-contact-fields
         * @alias inspect_contact_fields
         *
         * @param array $args       Positional arguments (unused).
         * @param array $assoc_args Associative arguments.
         * @return void
         */
        public function inspect_contact_fields( $args, $assoc_args ) {
                $id_raw = WP_CLI\Utils\get_flag_value( $assoc_args, 'id', '' );
                if ( '' === $id_raw || ! ctype_digit( (string) $id_raw ) ) {
                        WP_CLI::error( 'Provide a numeric FluentCRM contact id, e.g. --id=10.' );
                }
                $id = (int) $id_raw;

                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                        WP_CLI::error( 'FluentCRM is not active (Subscriber model not found).' );
                }
                $subscriber = \FluentCrm\App\Models\Subscriber::find( $id );
                if ( ! $subscriber ) {
                        WP_CLI::error( sprintf( 'No FluentCRM contact found with id %d.', $id ) );
                }

                WP_CLI::line( 'SiteEngine Ads Bridge: inspect contact custom fields (read-only)' );
                WP_CLI::line( '---------------------------------------------------------------' );
                WP_CLI::line( sprintf( 'Contact #%d  %s', $id, (string) $subscriber->email ) );
                WP_CLI::line( '' );

                // Defined custom fields. Helper may return a flat list or { fields: [...] }.
                $defs_raw = function_exists( 'fluentcrm_get_custom_contact_fields' ) ? fluentcrm_get_custom_contact_fields() : array();
                if ( is_array( $defs_raw ) && isset( $defs_raw['fields'] ) && is_array( $defs_raw['fields'] ) ) {
                        $defs = $defs_raw['fields'];
                } else {
                        $defs = is_array( $defs_raw ) ? $defs_raw : array();
                }

                $def_by_slug = array();
                foreach ( $defs as $d ) {
                        if ( is_array( $d ) && isset( $d['slug'] ) ) {
                                $def_by_slug[ (string) $d['slug'] ] = isset( $d['label'] ) ? (string) $d['label'] : '';
                        }
                }

                // Values stored on the contact (slug => value).
                $values = array();
                if ( method_exists( $subscriber, 'custom_fields' ) ) {
                        $values = (array) $subscriber->custom_fields();
                } else {
                        WP_CLI::warning( 'Subscriber::custom_fields() unavailable on this FluentCRM version; values cannot be read.' );
                }

                $all_slugs = array_values( array_unique( array_merge( array_keys( $def_by_slug ), array_keys( $values ) ) ) );
                sort( $all_slugs );

                WP_CLI::line( sprintf( 'Defined custom fields: %d.  Slugs in contact value map: %d.', count( $def_by_slug ), count( $values ) ) );
                WP_CLI::line( 'All known slugs (defined + stored):' );
                WP_CLI::line( '  ' . ( ! empty( $all_slugs ) ? implode( ', ', $all_slugs ) : '(none)' ) );

                // Keyword-filtered detail. len exposes 25-char truncation at a glance.
                $keywords = array( 'consent', 'google_ad', 'google_analytics', 'personalization', 'advertising' );
                $matched  = array();
                foreach ( $all_slugs as $slug ) {
                        $hit = false;
                        foreach ( $keywords as $kw ) {
                                if ( false !== stripos( $slug, $kw ) ) {
                                        $hit = true;
                                        break;
                                }
                        }
                        if ( ! $hit ) {
                                continue;
                        }
                        $has_value = array_key_exists( $slug, $values ) && null !== $values[ $slug ] && '' !== $values[ $slug ];
                        $raw       = $has_value ? ( is_array( $values[ $slug ] ) ? implode( '|', $values[ $slug ] ) : (string) $values[ $slug ] ) : '';
                        $matched[] = array(
                                'slug'    => $slug,
                                'len'     => (string) strlen( $slug ),
                                'defined' => isset( $def_by_slug[ $slug ] ) ? 'yes' : 'no',
                                'label'   => isset( $def_by_slug[ $slug ] ) ? $def_by_slug[ $slug ] : '',
                                'value'   => $has_value ? $raw : '(not set)',
                        );
                }

                WP_CLI::line( '' );
                WP_CLI::line( 'Keyword-matched fields (consent, google_ad, google_analytics, personalization, advertising):' );
                if ( empty( $matched ) ) {
                        WP_CLI::line( '  (no slugs matched these keywords)' );
                } else {
                        WP_CLI\Utils\format_items( 'table', $matched, array( 'slug', 'len', 'defined', 'label', 'value' ) );
                }

                // Explicit probes: full vs truncated slug candidates.
                $probes = array(
                        'se_google_ad_user_data',
                        'se_consent_advertising',
                        'se_google_ad_personalization',
                        'se_google_ad_personalizat',
                        'se_consent_personalization',
                        'se_consent_personalizatio',
                );
                $probe_rows = array();
                foreach ( $probes as $slug ) {
                        $has_value = array_key_exists( $slug, $values ) && null !== $values[ $slug ] && '' !== $values[ $slug ];
                        $raw       = $has_value ? ( is_array( $values[ $slug ] ) ? implode( '|', $values[ $slug ] ) : (string) $values[ $slug ] ) : '';
                        $probe_rows[] = array(
                                'slug'    => $slug,
                                'len'     => (string) strlen( $slug ),
                                'defined' => isset( $def_by_slug[ $slug ] ) ? 'yes' : 'no',
                                'value'   => $has_value ? $raw : '(not set)',
                        );
                }
                WP_CLI::line( '' );
                WP_CLI::line( 'Explicit slug probes (full vs 25-char truncated):' );
                WP_CLI\Utils\format_items( 'table', $probe_rows, array( 'slug', 'len', 'defined', 'value' ) );

                WP_CLI::line( '' );
                WP_CLI::line( 'Note: shows FluentCRM-defined fields + this contact value map. A value written to a meta key with no field definition is out of scope for this read. Read-only: no CRM/form mutation, no Google/Sheet write.' );

                WP_CLI::success( 'Custom-field inspection complete (read-only).' );
        }

        /**
         * Read a contact custom-field value, raw bytes preserved.
         *
         * Reads $custom[$slug] when present and non-empty (trim is used only for the
         * emptiness decision; the returned value is NOT trimmed), otherwise falls back
         * to a targeted {prefix}fc_subscriber_meta lookup (object_type=custom_field).
         * Returning raw bytes keeps the Lead Status gate's ( $won === $value ) check
         * byte-for-byte aligned with the export-batch BINARY shortlist, and makes the
         * optional custom_fields()/lead_status probe non-blocking.
         *
         * @param object $subscriber FluentCRM subscriber (needs ->id for the fallback).
         * @param array  $custom     Contact custom field slug => value map.
         * @param string $slug       Custom field slug to read.
         * @return string Raw stored value, or '' if not found.
         */
        private function get_contact_custom_value( $subscriber, $custom, $slug ) {
                $slug = (string) $slug;

                if ( '' === $slug ) {
                        return '';
                }

                if ( is_array( $custom ) && array_key_exists( $slug, $custom ) ) {
                        $raw = is_array( $custom[ $slug ] ) ? implode( '|', $custom[ $slug ] ) : (string) $custom[ $slug ];

                        if ( '' !== trim( $raw ) ) {
                                return $raw;
                        }
                }

                if ( empty( $subscriber ) || empty( $subscriber->id ) ) {
                        return '';
                }

                global $wpdb;

                $table = $wpdb->prefix . 'fc_subscriber_meta';

                $value = $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT meta.value
                                   FROM `{$table}` AS meta
                                  WHERE meta.subscriber_id = %d
                                    AND meta.object_type = %s
                                    AND meta.`key` = %s
                                  ORDER BY meta.id DESC
                                  LIMIT 1",
                                (int) $subscriber->id,
                                'custom_field',
                                $slug
                        )
                );

                return null === $value ? '' : (string) $value;
        }

        /**
         * Resolve a configured lifecycle tag title to its unique FluentCRM tag ID.
         *
         * Resolution surface order per FluentCRM support: FluentCrmApi('tags')->all()
         * first, then the Tag model query surface if the API wrapper is unavailable.
         * (Only the 'contacts' wrapper fails to proxy model scopes on this runtime;
         * the 'tags' API surface is unaffected.) Title matching is byte-exact PHP
         * ===. Fail-closed: an unreadable collection, zero matches, or duplicate
         * title matches stop the run with a CLI error - never a silent empty set.
         *
         * @param string $title Configured lifecycle tag title.
         * @return int Unique FluentCRM tag ID.
         */
        private function resolve_lifecycle_tag_id( $title ) {
                $tags = null;

                if ( function_exists( 'FluentCrmApi' ) ) {
                        try {
                                $api = FluentCrmApi( 'tags' );
                                if ( is_object( $api ) ) {
                                        $tags = $api->all();
                                }
                        } catch ( \Throwable $e ) {
                                $tags = null;
                        }
                }

                if ( ! is_iterable( $tags ) && class_exists( '\\FluentCrm\\App\\Models\\Tag' ) ) {
                        try {
                                $tags = \FluentCrm\App\Models\Tag::query()->get();
                        } catch ( \Throwable $e ) {
                                $tags = null;
                        }
                }

                if ( ! is_iterable( $tags ) ) {
                        WP_CLI::error( sprintf( 'Could not enumerate FluentCRM tags through a supported surface (FluentCrmApi("tags")->all() / Tag model); cannot resolve lifecycle tag "%s".', $title ) );
                }

                $matches = array();
                foreach ( $tags as $tag ) {
                        if ( is_object( $tag ) && isset( $tag->title ) && isset( $tag->id ) && (string) $tag->title === (string) $title ) {
                                $matches[] = (int) $tag->id;
                        }
                }
                $matches = array_values( array_unique( $matches ) );

                if ( 0 === count( $matches ) ) {
                        WP_CLI::error( sprintf( 'Configured lifecycle tag "%s" does not exist in FluentCRM (byte-exact title match). Fix config/CRM tag drift before selecting candidates.', $title ) );
                }
                if ( count( $matches ) > 1 ) {
                        WP_CLI::error( sprintf( 'Configured lifecycle tag "%s" matches multiple FluentCRM tag IDs (%s). Exactly one tag authority is required.', $title, implode( ', ', $matches ) ) );
                }

                return (int) $matches[0];
        }

        /**
         * Evaluate the CRM-knowable Won-row eligibility gates.
         *
         * Shared by inspect-contact, conversion-dry-run, and the Google_Ads_Import
         * row builder so these surfaces cannot diverge. Returns rows of array(
         * 'gate', 'result', 'detail' ); result is 'pass' or 'fail'. Deferred gates
         * (Order ID, consent, hashing) are added by callers as appropriate.
         *
         * @param object $subscriber FluentCRM subscriber model.
         * @param array  $list_names Contact list titles and slugs.
         * @param array  $custom     Contact custom field slug => value map.
         * @param string $email      Contact email.
         * @param string $phone      Contact phone.
         * @param object $config     Config singleton instance.
         * @return array
         */
        private function evaluate_won_eligibility( $subscriber, $list_names, $custom, $email, $phone, $config, $inquiry = null ) {
                $checks = array();

                // Lifecycle-tag authority (Phase 3). The contact's lifecycle set is
                // the byte-exact intersection of its FluentCRM tag titles with the
                // configured lifecycle map. Unrelated FluentCRM tags are ignored and
                // never counted as lifecycle conflicts. lead_status is not read as
                // an eligibility authority; the config key remains inert.
                $configured_lifecycle = array();
                foreach ( SiteEngine_Ads_Bridge_Config::lifecycle_roles() as $role ) {
                        $lc_title = (string) $config->get( 'fluentcrm.tags.lifecycle.' . $role, '' );
                        if ( '' !== $lc_title ) {
                                $configured_lifecycle[] = $lc_title;
                        }
                }
                $won_title = (string) $config->get( 'fluentcrm.tags.lifecycle.won', '' );

                $contact_lifecycle = array();
                if ( is_array( $inquiry ) && ! empty( $inquiry['lifecycle'] ) ) {
                        $role = trim( (string) $inquiry['lifecycle'] );
                        $title = (string) $config->get( 'fluentcrm.tags.lifecycle.' . $role, '' );
                        if ( '' !== $title ) {
                                $contact_lifecycle[] = $title;
                        }
                } else {
                        $contact_tags = ( is_object( $subscriber ) && isset( $subscriber->tags ) ) ? $subscriber->tags : array();
                        if ( is_iterable( $contact_tags ) ) {
                                foreach ( $contact_tags as $lc_tag ) {
                                        if ( is_object( $lc_tag ) && isset( $lc_tag->title ) && in_array( (string) $lc_tag->title, $configured_lifecycle, true ) ) {
                                                $contact_lifecycle[] = (string) $lc_tag->title;
                                        }
                                }
                        }
                }
                $contact_lifecycle = array_values( array_unique( $contact_lifecycle ) );
                $lifecycle_count   = count( $contact_lifecycle );
                $has_won           = ( '' !== $won_title && in_array( $won_title, $contact_lifecycle, true ) );

                // Lock 2: zero-tag and multi-tag diagnostics carry distinct wording
                // and are never merged into a single failure string. Both rows use
                // result 'fail' when blocking so existing "'fail' === result"
                // consumers collect them unchanged.
                $checks[] = array(
                        'gate'   => 'won lifecycle tag present',
                        'result' => $has_won ? 'pass' : 'fail',
                        'detail' => ( 0 === $lifecycle_count ) ? '(no configured lifecycle tag)' : implode( ' + ', $contact_lifecycle ),
                );

                if ( 0 === $lifecycle_count ) {
                        $exactly_detail = '(no configured lifecycle tag)';
                } elseif ( 1 === $lifecycle_count ) {
                        $exactly_detail = $contact_lifecycle[0];
                } else {
                        $exactly_detail = 'lifecycle conflict: ' . implode( ' + ', $contact_lifecycle ) . ' (skip)';
                }
                $checks[] = array(
                        'gate'   => 'exactly one configured lifecycle tag',
                        'result' => ( 1 === $lifecycle_count ) ? 'pass' : 'fail',
                        'detail' => $exactly_detail,
                );

                // DNC poison pill. 'fail' (not 'block') so existing
                // "'fail' === $check['result']" consumers collect it as blocking.
                $dnc_list = (string) $config->get( 'crm_map.lists.do_not_contact', 'DNC' );
                $in_dnc   = ( '' !== $dnc_list && is_array( $list_names ) && in_array( $dnc_list, $list_names, true ) );
                $checks[] = array(
                        'gate'   => 'dnc list present (poison pill)',
                        'result' => $in_dnc ? 'fail' : 'pass',
                        'detail' => $in_dnc ? $dnc_list : 'not in DNC',
                );

                $value_field = trim( (string) $config->get( 'exports.won_jobs.value_field', 'invoiced_amount' ) );
                $value_raw   = array_key_exists( $value_field, $custom ) ? $custom[ $value_field ] : null;
                $value_ok    = is_numeric( $value_raw ) && (float) $value_raw > 0;
                $checks[]    = array(
                        'gate'   => $value_field . ' > 0',
                        'result' => $value_ok ? 'pass' : 'fail',
                        'detail' => ( null === $value_raw || '' === $value_raw ) ? '(not set)' : (string) $value_raw,
                );

                $date_field = trim( (string) $config->get( 'exports.won_jobs.date_field', 'closed_date' ) );
                $date_raw   = array_key_exists( $date_field, $custom ) ? $custom[ $date_field ] : null;
                $date_ok    = ! ( null === $date_raw || '' === $date_raw );
                $checks[]   = array(
                        'gate'   => $date_field . ' present',
                        'result' => $date_ok ? 'pass' : 'fail',
                        'detail' => $date_ok ? (string) $date_raw : '(not set)',
                );

                $contactable = ( '' !== (string) $email ) || ( '' !== (string) $phone );
                $checks[]    = array(
                        'gate'   => 'email or phone present',
                        'result' => $contactable ? 'pass' : 'fail',
                        'detail' => $contactable ? '' : 'neither set',
                );

                return $checks;
        }

        /**
         * Normalize an email per Google Enhanced Conversions rules.
         *
         * Lowercase, trim, and for gmail.com / googlemail.com remove periods in the
         * local part. Does NOT strip "+" suffixes (matching Google's EC code samples).
         * Returns '' if the input is empty or not a single-"@" address.
         *
         * @param string $email Raw email.
         * @return string Normalized email or empty string.
         */
        private function normalize_email( $email ) {
                $email = strtolower( trim( (string) $email ) );

                if ( '' === $email || false === strpos( $email, '@' ) ) {
                        return '';
                }

                $parts = explode( '@', $email );
                if ( count( $parts ) !== 2 ) {
                        return '';
                }

                list( $local, $domain ) = $parts;

                if ( 'gmail.com' === $domain || 'googlemail.com' === $domain ) {
                        $local = str_replace( '.', '', $local );
                }

                return $local . '@' . $domain;
        }

        /**
         * Normalize a phone number to E.164 (leading "+" then digits only).
         *
         * Accepts numbers already in E.164 (with a leading "+"); strips spaces,
         * dashes, and punctuation. Returns '' for numbers without a leading "+"
         * because the country code cannot be inferred safely, and emitting a wrong
         * hash would silently miss matches.
         *
         * @param string $phone Raw phone.
         * @return string E.164 phone or empty string.
         */
        private function normalize_phone( $phone ) {
                $phone  = trim( (string) $phone );
                $digits = preg_replace( '/\D+/', '', $phone );

                if ( '' === $digits ) {
                        return '';
                }

                // SiteEngine's current deployment base is U.S. local-service businesses.
                // FluentCRM commonly stores these as bare 10-digit numbers; normalize
                // them deterministically to +1 E.164 before hashing.
                if ( 10 === strlen( $digits ) ) {
                        return '+1' . $digits;
                }
                if ( 11 === strlen( $digits ) && '1' === substr( $digits, 0, 1 ) ) {
                        return '+' . $digits;
                }

                // Preserve already international E.164-shaped values only when the raw
                // source carried the explicit country-code marker.
                if ( '+' === substr( $phone, 0, 1 ) && strlen( $digits ) >= 8 && strlen( $digits ) <= 15 ) {
                        return '+' . $digits;
                }

                return '';
        }

        /**
         * Normalize a personal name for hashing: trim and lowercase. Returns '' if
         * empty. (Google's documented minimum for first/last name hashing.)
         *
         * @param string $name Raw name.
         * @return string Normalized name, or ''.
         */
        private function normalize_name( $name ) {
                return strtolower( trim( (string) $name ) );
        }

        /**
         * Normalize a US state/region value to a 2-letter code for the Data Manager
         * "State or region code" field. A 2-letter uppercase value is preserved as-is;
         * a recognized full state/territory name (case-insensitive) is mapped; anything
         * else is returned unchanged with status 'unknown' so the caller can warn.
         *
         * @param string $raw Raw stored state/region value.
         * @return array{value:string,status:string} status: empty|preserved|mapped|unknown
         */
        private function normalize_us_state( $raw ) {
                $v = trim( (string) $raw );
                if ( '' === $v ) {
                        return array( 'value' => '', 'status' => 'empty' );
                }
                if ( preg_match( '/^[A-Z]{2}$/', $v ) ) {
                        return array( 'value' => $v, 'status' => 'preserved' );
                }
                $map = array(
                        'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR',
                        'california' => 'CA', 'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE',
                        'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID',
                        'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS',
                        'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
                        'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN', 'mississippi' => 'MS',
                        'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV',
                        'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY',
                        'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK',
                        'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode island' => 'RI', 'south carolina' => 'SC',
                        'south dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT',
                        'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA', 'west virginia' => 'WV',
                        'wisconsin' => 'WI', 'wyoming' => 'WY', 'district of columbia' => 'DC',
                        'puerto rico' => 'PR', 'guam' => 'GU', 'virgin islands' => 'VI',
                        'american samoa' => 'AS', 'northern mariana islands' => 'MP',
                );
                $key = strtolower( $v );
                if ( isset( $map[ $key ] ) ) {
                        return array( 'value' => $map[ $key ], 'status' => 'mapped' );
                }
                return array( 'value' => $v, 'status' => 'unknown' );
        }

        /**
         * Normalize a country value to a 2-letter code, conservatively. A 2-letter
         * uppercase value is preserved; common United States spellings map to US;
         * anything else is returned unchanged with status 'unknown'. No full country
         * table is applied here.
         *
         * @param string $raw Raw stored country value.
         * @return array{value:string,status:string} status: empty|preserved|mapped|unknown
         */
        private function normalize_country( $raw ) {
                $v = trim( (string) $raw );
                if ( '' === $v ) {
                        return array( 'value' => '', 'status' => 'empty' );
                }
                if ( preg_match( '/^[A-Z]{2}$/', $v ) ) {
                        return array( 'value' => $v, 'status' => 'preserved' );
                }
                $map = array(
                        'united states' => 'US',
                        'united states of america' => 'US',
                        'usa' => 'US',
                        'u.s.a.' => 'US',
                        'u.s.' => 'US',
                        'us' => 'US',
                );
                $key = strtolower( $v );
                if ( isset( $map[ $key ] ) ) {
                        return array( 'value' => $map[ $key ], 'status' => 'mapped' );
                }
                return array( 'value' => $v, 'status' => 'unknown' );
        }

        /**
         * Read a consent value from the first recognized contact custom field.
         *
         * Pass-through only: the raw stored value is returned verbatim (trimmed). No
         * semantic mapping to Data Manager consent tokens (e.g. GRANTED/DENIED) is
         * performed here — that is deliberately deferred so semantics are never guessed
         * silently. Reads CRM custom-field values only; never touches the consent
         * plugin namespace/constants/options. Returns the chosen source slug, the raw
         * value, a status (found|none), and a 'detail' string listing every candidate
         * checked.
         *
         * @param array $custom     Contact custom field slug => value map.
         * @param array $candidates Ordered list of candidate source slugs.
         * @return array{value:string,source:string,status:string,detail:string}
         */
        private function read_consent_value( $custom, $candidates ) {
                $chosen_value  = '';
                $chosen_source = '';
                $status        = 'none';
                $parts         = array();

                foreach ( $candidates as $slug ) {
                        if ( array_key_exists( $slug, $custom ) && null !== $custom[ $slug ] ) {
                                $raw = is_array( $custom[ $slug ] ) ? implode( '|', $custom[ $slug ] ) : (string) $custom[ $slug ];
                                $raw = trim( $raw );
                        } else {
                                $raw = '';
                        }

                        $parts[] = $slug . '=' . ( '' !== $raw ? '"' . $raw . '"' : '(not set)' );

                        if ( 'none' === $status && '' !== $raw ) {
                                $chosen_value  = $raw;
                                $chosen_source = $slug;
                                $status        = 'found';
                        }
                }

                return array(
                        'value'  => $chosen_value,
                        'source' => $chosen_source,
                        'status' => $status,
                        'detail' => implode( '; ', $parts ),
                );
        }

        /**
         * Map a stored consent token to the Google_Ads_Import Sheet / Data Manager
         * feed value.
         *
         * Sheet/Data Manager feed vocabulary (documented in Google Ads Help) is
         * title-case Granted / Denied, with a blank cell representing unset/unknown.
         * This is deliberately NOT the Google Ads API ConsentStatus enum
         * (GRANTED/DENIED/UNSPECIFIED) and NOT browser Consent Mode v2
         * (granted/denied). Recognition is case-insensitive; output is canonical.
         * Anything not recognized as granted/denied (including empty/unset) returns
         * an empty string, which is the feed representation for unset/unknown.
         *
         * This maps at the output/emit point only. The raw stored value remains
         * untouched in the read_consent_value() result, and no FluentCRM value is
         * mutated.
         *
         * @param mixed $stored Raw stored consent value (typically lowercase 'granted').
         * @return string 'Granted' | 'Denied' | '' (blank cell = unset/unknown)
         */
        private function map_consent_for_sheet_feed( $stored ) {
                $token = strtolower( trim( (string) $stored ) );
                if ( 'granted' === $token ) {
                        return 'Granted';
                }
                if ( 'denied' === $token ) {
                        return 'Denied';
                }
                return '';
        }

        /**
         * Resolve consent provenance for a contact from the AUTHORITATIVE consent
         * receipt, attached to the deterministic canonical Fluent Forms submission
         * already resolved for the Order ID. Read-only: never mutates FluentCRM,
         * Fluent Forms, the consent receipts table, the Sheet, or the ledger.
         *
         * Authority chain:
         *   1. Require a deterministic canonical submission (entry id) to attach to.
         *      Arbitrary recent receipts are NEVER guessed.
         *   2. Resolve the stamped receipt id from that submission, in precedence order:
         *        a. response JSON `_se_consent_server.receipt_id` (server-captured)
         *        b. response JSON flat `se_consent_receipt_id`
         *        c. {prefix}fluentform_entry_details.field_name = 'se_consent_receipt_id'
         *   3. Load the matching row from {prefix}se_consent_receipts (the PLURAL,
         *      authoritative table).
         *   4. Decode google_consent_json and read ad_user_data / ad_personalization.
         *   5. Policy-version staleness: compare the receipt policy_version against the
         *      configured consent.required_policy_version authority. Runtime consent
         *      plugin policy values are diagnostic only.
         *
         * Emitted Sheet/Data Manager columns and the export gate derive ONLY from the
         * receipt row's google_consent_json. Loose CRM/form copied fields are not used
         * as authority anywhere in this method.
         *
         * @param array       $resolved Result of resolve_won_order_id() or direct submission context.
         * @param object|null $config   Config instance; loaded when omitted.
         * @return array Structured consent decision and audit detail.
         */
        private function resolve_consent_provenance( $resolved, $config = null ) {
                global $wpdb;
                $config = $config instanceof SiteEngine_Ads_Bridge_Config ? $config : SiteEngine_Ads_Bridge_Config::instance();

                $out = array(
                        'decision'             => 'blocked',
                        'block_reason'         => '',
                        'attached_entry_id'    => '',
                        'attached_form_id'     => '',
                        'order_id'             => isset( $resolved['order_id'] ) ? (string) $resolved['order_id'] : '',
                        'receipt_id'           => '',
                        'receipt_id_source'    => 'none',
                        'receipt_found'        => false,
                        'receipt_row_id'       => '',
                        'policy_version'       => '',
                        'banner_version'       => '',
                        'ad_user_data'         => '',
                        'ad_personalization'   => '',
                        'measurement_feed'     => '',
                        'personalization_feed' => '',
                        'policy_check'         => 'unavailable',
                        'current_policy'       => '',
                        'required_policy'      => '',
                        'runtime_policy'       => '',
                        'google_consent_json'  => '',
                        'detail'               => array(),
                );

                // 1. Require a deterministic canonical submission. Non-deterministic /
                // fallback-email / unresolved Order IDs do not provide a trustworthy
                // submission to attach consent to, so we do not guess a receipt.
                $entry_id      = isset( $resolved['entry_id'] ) ? trim( (string) $resolved['entry_id'] ) : '';
                $deterministic = ! empty( $resolved['deterministic'] );
                if ( ! $deterministic || '' === $entry_id || ! ctype_digit( $entry_id ) ) {
                        $out['block_reason'] = 'consent unresolved: no deterministic canonical Fluent Forms submission to attach a consent receipt to.';
                        $out['detail'][]     = 'deterministic=' . ( $deterministic ? 'yes' : 'no' ) . '; entry_id=' . ( '' !== $entry_id ? $entry_id : '(none)' );
                        return $out;
                }
                $out['attached_entry_id'] = $entry_id;
                $out['attached_form_id']  = isset( $resolved['form_id'] ) ? (string) $resolved['form_id'] : '';

                // 2. Resolve the stamped receipt id from the canonical submission.
                $receipt_id = '';
                $src        = 'none';

                $table = isset( $resolved['submissions_table'] ) ? (string) $resolved['submissions_table'] : '';
                if ( '' !== $table && preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
                        $cols = $this->table_columns( $table );
                        if ( in_array( 'response', $cols, true ) ) {
                                $resp_raw = $wpdb->get_var(
                                        $wpdb->prepare( "SELECT response FROM `{$table}` WHERE id = %d", (int) $entry_id )
                                );
                                if ( is_string( $resp_raw ) && '' !== $resp_raw ) {
                                        $resp = json_decode( $resp_raw, true );
                                        if ( is_array( $resp ) ) {
                                                // 2a: server-captured stamp (primary).
                                                if ( isset( $resp['_se_consent_server'] ) && is_array( $resp['_se_consent_server'] )
                                                        && isset( $resp['_se_consent_server']['receipt_id'] )
                                                        && '' !== trim( (string) $resp['_se_consent_server']['receipt_id'] ) ) {
                                                        $receipt_id = trim( (string) $resp['_se_consent_server']['receipt_id'] );
                                                        $src        = 'response._se_consent_server.receipt_id';
                                                }
                                                // 2b: flat field (fallback).
                                                if ( '' === $receipt_id && isset( $resp['se_consent_receipt_id'] )
                                                        && '' !== trim( (string) $resp['se_consent_receipt_id'] ) ) {
                                                        $receipt_id = trim( (string) $resp['se_consent_receipt_id'] );
                                                        $src        = 'response.se_consent_receipt_id';
                                                }
                                        }
                                }
                        }
                }

                // 2c: normalized entry-details fallback (proven storage location on live).
                if ( '' === $receipt_id ) {
                        $details_table = $wpdb->prefix . 'fluentform_entry_details';
                        $det_cols      = $this->table_columns( $details_table );
                        if ( in_array( 'submission_id', $det_cols, true )
                                && in_array( 'field_name', $det_cols, true )
                                && in_array( 'field_value', $det_cols, true ) ) {
                                $val = $wpdb->get_var(
                                        $wpdb->prepare(
                                                "SELECT field_value FROM `{$details_table}` WHERE submission_id = %d AND field_name = %s ORDER BY id DESC LIMIT 1",
                                                (int) $entry_id,
                                                'se_consent_receipt_id'
                                        )
                                );
                                if ( is_string( $val ) && '' !== trim( $val ) ) {
                                        $receipt_id = trim( $val );
                                        $src        = 'fluentform_entry_details.se_consent_receipt_id';
                                }
                        }
                }

                $out['receipt_id']        = $receipt_id;
                $out['receipt_id_source'] = $src;

                if ( '' === $receipt_id ) {
                        $out['block_reason'] = 'consent unresolved: canonical submission ' . $entry_id . ' has no stamped consent receipt id.';
                        return $out;
                }

                // 3. Load the authoritative receipt row (PLURAL table).
                $rtable = $wpdb->prefix . 'se_consent_receipts';
                $rcols  = $this->table_columns( $rtable );
                if ( ! in_array( 'receipt_id', $rcols, true ) || ! in_array( 'google_consent_json', $rcols, true ) ) {
                        $out['block_reason'] = 'consent unresolved: authoritative receipts table ' . $rtable . ' is missing or lacks required columns.';
                        $out['detail'][]     = 'receipts table columns seen: ' . ( ! empty( $rcols ) ? implode( ',', $rcols ) : '(none)' );
                        return $out;
                }

                $row = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id, policy_version, banner_version, google_consent_json FROM `{$rtable}` WHERE receipt_id = %s ORDER BY id DESC LIMIT 1",
                                $receipt_id
                        ),
                        ARRAY_A
                );
                if ( ! $row ) {
                        $out['block_reason'] = 'consent unresolved: receipt id ' . $receipt_id . ' does not resolve to an authoritative receipt row.';
                        return $out;
                }

                $out['receipt_found']  = true;
                $out['receipt_row_id'] = (string) $row['id'];
                $out['policy_version'] = (string) $row['policy_version'];
                $out['banner_version'] = (string) $row['banner_version'];
                $out['google_consent_json'] = (string) $row['google_consent_json'];

                // 4. Decode google_consent_json.
                $gc = json_decode( (string) $row['google_consent_json'], true );
                if ( ! is_array( $gc ) ) {
                        $out['block_reason'] = 'consent invalid: receipt ' . $receipt_id . ' google_consent_json is missing or malformed.';
                        return $out;
                }

                $aud  = isset( $gc['ad_user_data'] ) ? strtolower( trim( (string) $gc['ad_user_data'] ) ) : '';
                $aper = isset( $gc['ad_personalization'] ) ? strtolower( trim( (string) $gc['ad_personalization'] ) ) : '';

                $out['ad_user_data']         = $aud;
                $out['ad_personalization']   = $aper;
                $out['measurement_feed']     = $this->map_consent_for_sheet_feed( $aud );
                $out['personalization_feed'] = $this->map_consent_for_sheet_feed( $aper );

                // 5. Configured policy version is the export authority. The consent
                // plugin's runtime value is recorded only to expose config/runtime drift.
                $required_policy         = trim( (string) $config->get( 'consent.required_policy_version', '' ) );
                $runtime_policy          = $this->current_consent_policy_version();
                $out['required_policy']  = $required_policy;
                $out['current_policy']   = $required_policy; // Backward-compatible diagnostic key.
                $out['runtime_policy']   = $runtime_policy;

                if ( '' === $required_policy ) {
                        $out['policy_check'] = 'unavailable';
                        $out['detail'][]     = 'configured consent.required_policy_version is empty.';
                } elseif ( (string) $row['policy_version'] === $required_policy ) {
                        $out['policy_check'] = 'matched';
                } else {
                        $out['policy_check'] = 'mismatch';
                }
                if ( '' !== $runtime_policy && '' !== $required_policy && $runtime_policy !== $required_policy ) {
                        $out['detail'][] = 'runtime consent policy "' . $runtime_policy . '" differs from configured authority "' . $required_policy . '".';
                }

                // 6. Hard gate: ad_user_data must be granted.
                if ( 'granted' !== $aud ) {
                        $out['block_reason'] = 'consent denied/missing: receipt ' . $receipt_id . ' ad_user_data=' . ( '' !== $aud ? $aud : '(unset)' ) . ' (require granted).';
                        return $out;
                }

                // 7. Missing configured policy authority or a mismatch is a hard block.
                if ( 'unavailable' === $out['policy_check'] ) {
                        $out['block_reason'] = 'consent policy authority missing: configure consent.required_policy_version.';
                        return $out;
                }
                if ( 'mismatch' === $out['policy_check'] ) {
                        $out['block_reason'] = 'consent stale: receipt policy_version "' . (string) $row['policy_version'] . '" != required "' . $required_policy . '".';
                        return $out;
                }

                $out['decision'] = 'exportable';
                return $out;
        }

        /**
         * Manage the Ads Bridge inquiry-attribution persistence layer.
         *
         * Operations:
         *   migrate [--apply]   Preview/create the inquiry table.
         *   adopt [--apply]     Freeze one existing current inquiry before repeat activation.
         *   inspect             Read-only schema/count summary and optional rows.
         *
         * ## OPTIONS
         *
         * <operation>
         * : migrate|adopt|inspect
         *
         * [--apply]
         * : Required only to create/repair the inquiry table with dbDelta.
         *
         * [--contact-id=<id>]
         * : inspect only. Restrict rows to one FluentCRM contact.
         *
         * [--inquiry-id=<id>]
         * : inspect only. Restrict to one inq_<UUID>.
         *
         * @subcommand inquiry-admin
         * @alias inquiry_admin
         *
         * @param array $args Positional args.
         * @param array $assoc_args Assoc args.
         * @return void
         */
        public function inquiry_admin( $args, $assoc_args ) {
                $op = isset( $args[0] ) ? strtolower( trim( (string) $args[0] ) ) : '';
                if ( ! in_array( $op, array( 'migrate', 'adopt', 'inspect' ), true ) ) {
                        WP_CLI::error( 'Usage: wp se-ads inquiry-admin <migrate|adopt|inspect> [--apply] [--contact-id=<id>] [--inquiry-id=<inq_uuid>]' );
                }
                $apply = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );
                if ( 'inspect' === $op && $apply ) {
                        WP_CLI::error( 'inquiry-admin inspect is read-only; --apply is not accepted.' );
                }

                if ( 'migrate' === $op ) {
                        $result = SiteEngine_Ads_Bridge_Inquiries::migrate( $apply );
                        WP_CLI::line( 'SiteEngine Ads Bridge: inquiry-admin migrate (' . ( $apply ? 'APPLY' : 'DRY-RUN' ) . ')' );
                        WP_CLI::line( '------------------------------------------------------------' );
                        WP_CLI::line( 'Table: ' . $result['table'] );
                        WP_CLI::line( 'Schema version: ' . $result['schema_version'] );
                        WP_CLI::line( 'Exists before: ' . ( $result['exists_before'] ? 'yes' : 'no' ) );
                        if ( ! $apply ) {
                                WP_CLI::line( 'Action: ' . ( $result['exists_before'] ? 'would verify/repair canonical schema' : 'would create canonical inquiry table' ) );
                                WP_CLI::success( 'Dry-run complete. No database write.' );
                                return;
                        }
                        if ( empty( $result['applied'] ) ) {
                                WP_CLI::error( 'Inquiry table migration did not verify the recovery columns and schema version.' );
                        }
                        WP_CLI::success( 'Inquiry table migration complete.' );
                        return;
                }

                if ( 'adopt' === $op ) {
                        $contact_id = WP_CLI\Utils\get_flag_value( $assoc_args, 'contact-id', null );
                        $inquiry_id = trim( (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'inquiry-id', '' ) );
                        $head_version = WP_CLI\Utils\get_flag_value( $assoc_args, 'head-version', 0 );
                        if ( null === $contact_id || ! ctype_digit( (string) $contact_id ) || (int) $contact_id < 1 ) {
                                WP_CLI::error( 'inquiry-admin adopt requires --contact-id=<positive integer>.' );
                        }
                        if ( 1 !== preg_match( '/^inq_[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $inquiry_id ) ) {
                                WP_CLI::error( 'inquiry-admin adopt requires --inquiry-id=inq_<UUID>.' );
                        }
                        if ( ! is_numeric( $head_version ) || (int) $head_version < 0 ) {
                                WP_CLI::error( '--head-version must be a non-negative integer.' );
                        }
                        $form_id = WP_CLI\Utils\get_flag_value( $assoc_args, 'form-id', null );
                        $entry_id = WP_CLI\Utils\get_flag_value( $assoc_args, 'entry-id', null );
                        if ( ( null === $form_id ) xor ( null === $entry_id ) ) {
                                WP_CLI::error( '--form-id and --entry-id must be provided together.' );
                        }
                        if ( null !== $form_id && ( ! ctype_digit( (string) $form_id ) || ! ctype_digit( (string) $entry_id ) || (int) $form_id < 1 || (int) $entry_id < 1 ) ) {
                                WP_CLI::error( '--form-id and --entry-id must be positive integers.' );
                        }
                        $event_id = trim( (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'event-id', '' ) );
                        if ( '' === $event_id ) {
                                $event_id = 'evt_seab_adopt_' . substr( hash( 'sha256', (int) $contact_id . '|' . $inquiry_id ), 0, 32 );
                        }
                        $payload = array(
                                'contract_version' => SiteEngine_Ads_Bridge_Inquiries::CONTRACT_VERSION,
                                'event_id' => $event_id,
                                'site_instance_id' => home_url( '/' ),
                                'contact_id' => (int) $contact_id,
                                'inquiry_id' => $inquiry_id,
                                'head_version' => (int) $head_version,
                        );
                        if ( null !== $form_id ) {
                                $payload['source'] = array(
                                        'type' => 'fluent_form',
                                        'form_id' => (int) $form_id,
                                        'entry_id' => (int) $entry_id,
                                        'reference' => 'form:' . (int) $form_id . ':entry:' . (int) $entry_id,
                                );
                        }
                        WP_CLI::line( 'SiteEngine Ads Bridge: inquiry-admin adopt (' . ( $apply ? 'APPLY' : 'DRY-RUN' ) . ')' );
                        WP_CLI::line( '------------------------------------------------------------' );
                        WP_CLI::line( 'Contact: ' . (int) $contact_id );
                        WP_CLI::line( 'Inquiry: ' . $inquiry_id );
                        WP_CLI::line( 'Event: ' . $event_id );
                        WP_CLI::line( 'Binding: ' . ( null !== $form_id ? 'explicit ff:' . (int) $form_id . ':' . (int) $entry_id : 'resolve current canonical submission_id at apply time' ) );
                        if ( ! $apply ) {
                                $current = SiteEngine_Ads_Bridge_Inquiries::active_for_contact( (int) $contact_id );
                                WP_CLI::line( 'Existing active Ads inquiry: ' . ( is_array( $current ) ? (string) $current['inquiry_id'] : '(none)' ) );
                                WP_CLI::success( 'Dry-run complete. No database or CRM write.' );
                                return;
                        }
                        $result = SiteEngine_Ads_Bridge_Inquiries::adopt_current_inquiry( $payload );
                        WP_CLI\Utils\format_items( 'table', array( $result ), array( 'status','reason_code','contact_id','inquiry_id','transaction_id','head_version' ) );
                        if ( 'adopted' !== (string) $result['status'] ) {
                                WP_CLI::error( 'Current inquiry adoption did not commit.' );
                        }
                        WP_CLI::success( 'Current inquiry adopted. Its Ads attribution binding is now immutable.' );
                        return;
                }

                $summary = SiteEngine_Ads_Bridge_Inquiries::status_summary();
                WP_CLI::line( 'SiteEngine Ads Bridge: inquiry-admin inspect (READ-ONLY)' );
                WP_CLI::line( '------------------------------------------------------------' );
                WP_CLI::line( 'Table exists: ' . ( $summary['table_exists'] ? 'yes' : 'no' ) );
                WP_CLI::line( 'Schema version option: ' . ( '' !== (string) $summary['schema_version'] ? (string) $summary['schema_version'] : '(not set)' ) );
                WP_CLI::line( 'Recovery schema ready: ' . ( $summary['schema_ready'] ? 'yes' : 'no; explicit migration required' ) );
                if ( ! $summary['table_exists'] ) {
                        WP_CLI::warning( 'Inquiry table is not installed. Run inquiry-admin migrate first.' );
                        return;
                }
                WP_CLI::line( sprintf( 'Rows: %d total; %d active; %d prepared/activating; %d archived.', $summary['rows'], $summary['active'], $summary['prepared'], $summary['archived'] ) );

                global $wpdb;
                $table = SiteEngine_Ads_Bridge_Inquiries::table_name();
                $where = array();
                $params = array();
                $contact_id = WP_CLI\Utils\get_flag_value( $assoc_args, 'contact-id', null );
                $inquiry_id = trim( (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'inquiry-id', '' ) );
                if ( null !== $contact_id ) {
                        if ( ! ctype_digit( (string) $contact_id ) || (int) $contact_id < 1 ) {
                                WP_CLI::error( '--contact-id must be a positive integer.' );
                        }
                        $where[] = 'contact_id = %d';
                        $params[] = (int) $contact_id;
                }
                if ( '' !== $inquiry_id ) {
                        $where[] = 'inquiry_id = %s';
                        $params[] = $inquiry_id;
                }
                $sql = "SELECT inquiry_id,contact_id,state,lifecycle,transaction_id,previous_inquiry_id,reconciliation_id,qualified_at,unqualified_at,closed_date,invoiced_amount,customer_rating,prepared_at_utc,activated_at_utc,archived_at_utc FROM `{$table}`";
                if ( ! empty( $where ) ) {
                        $sql .= ' WHERE ' . implode( ' AND ', $where );
                }
                $sql .= ' ORDER BY id ASC LIMIT 100';
                if ( ! empty( $params ) ) {
                        $sql = $wpdb->prepare( $sql, $params );
                }
                $rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
                if ( empty( $rows ) ) {
                        WP_CLI::line( '(no matching inquiry rows)' );
                        return;
                }
                WP_CLI\Utils\format_items( 'table', $rows, array( 'inquiry_id','contact_id','state','lifecycle','transaction_id','previous_inquiry_id','qualified_at','unqualified_at','closed_date','invoiced_amount','customer_rating' ) );
        }

        /**
         * Runtime consent plugin policy version, used for diagnostics only.
         * Side-effect-free: reads the mu-plugin constant (its default_config() source of
         * truth) first, then a persisted option. Never instantiates the consent
         * singleton (which performs a privacy-URL HTTP check), and never guesses a
         * value. The configured consent.required_policy_version remains authoritative.
         *
         * @return string Policy version string, or '' if unavailable.
         */
        private function current_consent_policy_version() {
                if ( defined( 'SE_CONSENT_POLICY_VERSION' ) ) {
                        $v = trim( (string) constant( 'SE_CONSENT_POLICY_VERSION' ) );
                        if ( '' !== $v ) {
                                return $v;
                        }
                }
                $opt = get_option( 'se_consent_policy_version', '' );
                if ( is_string( $opt ) && '' !== trim( $opt ) ) {
                        return trim( $opt );
                }
                return '';
        }

}