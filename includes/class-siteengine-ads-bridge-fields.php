<?php
/** Existing explicit vendor provisioning, shared by CLI and authenticated setup. */
if (!defined('ABSPATH')) { exit; }
final class SiteEngine_Ads_Bridge_Fields {
    public static function required() {
        return array(
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
    }
    public static function provision($apply=false) {
        if(!class_exists('\FluentCrm\App\Models\CustomContactField'))throw new RuntimeException('FluentCRM custom-field manager unavailable.');
        $manager=new \FluentCrm\App\Models\CustomContactField();
        $types=array_keys((array)$manager->getFieldTypes());$global=$manager->getGlobalFields();$existing=$global['fields']??[];
        if(!is_array($existing))throw new RuntimeException('Malformed existing custom-field registry.');
        $by=[];foreach($existing as$f){$slug=$f['slug']??'';if($slug==='')throw new RuntimeException('Unidentified existing custom field; review in FluentCRM.');if(isset($by[$slug]))throw new RuntimeException('Duplicate custom-field slug: '.$slug);$by[$slug]=$f;}
        $missing=[];$conflicts=[];
        foreach(self::required()as$f){if(!in_array($f['type'],$types,true))throw new RuntimeException('Unsupported vendor field type: '.$f['type']);if(isset($by[$f['slug']])){if(($by[$f['slug']]['type']??null)!==$f['type'])$conflicts[]=$f['slug'];}else{$missing[]=$f;}}
        if($apply&&$conflicts)throw new RuntimeException('Existing field types require CRM-owner review; unchanged: '.implode(', ',$conflicts));
        if($apply&&$missing){$manager->saveGlobalFields(array_merge($existing,$missing));$after=$manager->getGlobalFields();if(($after['fields']??null)!==array_merge($existing,$missing))throw new RuntimeException('Custom-field write/readback mismatch; inspect before retrying.');}
        return ['missing'=>$missing,'conflicts'=>$conflicts,'created'=>$apply?count($missing):0,'existing_preserved'=>true];
    }
}
