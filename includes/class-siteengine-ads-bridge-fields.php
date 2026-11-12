<?php
/** Existing explicit vendor provisioning, shared by CLI and authenticated setup. */
if (!defined('ABSPATH')) { exit; }
final class SiteEngine_Ads_Bridge_Fields {
    public static function required() {
        $base = array(
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
        $c=class_exists('SiteEngine_Ads_Bridge_Config')?SiteEngine_Ads_Bridge_Config::instance():null;
        $slug=$c?$c->get('fluentcrm.lifecycle_automation.unqualified_reason_field','customer_rating'):'customer_rating';
        if(!is_string($slug)||!preg_match('/^[a-z][a-z0-9_]*$/D',$slug))throw new RuntimeException('Unqualified Reason field slug is invalid; review advanced lifecycle mappings.');
        foreach($base as$f)if($f['slug']===$slug)throw new RuntimeException('Unqualified Reason conflicts with standard field: '.$slug);
        $base[]=['slug'=>$slug,'label'=>'Unqualified Reason','type'=>'select-one','options'=>SiteEngine_Ads_Bridge_Lifecycle::unqualified_reason_values()];
        return $base;
    }
    /** Create only absent definitions using FluentCRM's API-key/type mapping. */
    public static function provision( $apply = false, ?array $required = null ) {
        if ( ! class_exists( '\FluentCrm\App\Models\CustomContactField' ) ) {
            throw new RuntimeException( 'FluentCRM custom-field manager unavailable.' );
        }
        $manager = new \FluentCrm\App\Models\CustomContactField();
        $supported = array();
        foreach ( (array) $manager->getFieldTypes() as $field_key => $definition ) {
            if ( is_array( $definition ) && is_string( $definition['type'] ?? null ) ) {
                $supported[ $definition['type'] ] = $field_key;
            }
        }
        $global = $manager->getGlobalFields();
        $existing = $global['fields'] ?? array();
        if ( ! is_array( $existing ) ) { throw new RuntimeException( 'Malformed existing custom-field registry.' ); }
        $by = array();
        foreach ( $existing as $field ) {
            $slug = $field['slug'] ?? '';
            if ( ! is_string( $slug ) || '' === $slug ) { throw new RuntimeException( 'Unidentified existing custom field; review in FluentCRM.' ); }
            if ( isset( $by[ $slug ] ) ) { throw new RuntimeException( 'Duplicate custom-field slug: ' . $slug ); }
            $by[ $slug ] = $field;
        }
        $missing = array();
        $conflicts = array();
        foreach ( $required ?? self::required() as $field ) {
            if ( ! isset( $supported[ $field['type'] ] ) ) { throw new RuntimeException( 'Unsupported vendor field type: ' . $field['type'] ); }
            if ( isset( $by[ $field['slug'] ] ) ) {
                if ( ( $by[ $field['slug'] ]['type'] ?? null ) !== $field['type'] ||
                    ( isset( $field['options'] ) && array_values( (array) ( $by[ $field['slug'] ]['options'] ?? array() ) ) !== $field['options'] ) ) {
                    $conflicts[] = $field['slug'];
                }
            } else {
                $field['field_key'] = $supported[ $field['type'] ];
                $missing[] = $field;
            }
        }
        if ( $apply && $conflicts ) { throw new RuntimeException( 'Existing field types require CRM-owner review; unchanged: ' . implode( ', ', $conflicts ) ); }
        if ( $apply && $missing ) {
            $expected = array_merge( $existing, $missing );
            $manager->saveGlobalFields( $expected );
            $after = $manager->getGlobalFields();
            if ( ( $after['fields'] ?? null ) !== $expected ) { throw new RuntimeException( 'Custom-field write/readback mismatch; inspect before retrying.' ); }
        }
        return array( 'missing' => $missing, 'conflicts' => $conflicts, 'created' => $apply ? count( $missing ) : 0, 'existing_preserved' => true );
    }
}
