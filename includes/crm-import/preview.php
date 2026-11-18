<?php
namespace SiteEngine\AdsBridge\Import;
/** Compatible native editor representation only. Never starts a funnel. */
final class Preview {
    public static function register():void {
        if(!class_exists('FluentCrm\\App\\Services\\Funnel\\BaseTrigger')||has_filter('fluentcrm_funnel_editor_details_siteengine_crm_inquiry_preview'))return;
        new class extends \FluentCrm\App\Services\Funnel\BaseTrigger {
            public function __construct(){$this->triggerName='siteengine_crm_inquiry_preview';parent::__construct();}
            public function getTrigger(){return ['category'=>'SiteEngine Ads Bridge','label'=>'Recorded inquiry stage — inactive design','description'=>'Imported editor blueprint only. No enrollment or delivery runtime is installed by import.','icon'=>'fc-icon-user'];}
            public function getFunnelSettingsDefaults(){return ['entry_event'=>'accepted-current','__force_run_actions'=>'no'];}
            public function getSettingsFields($funnel){return ['title'=>'Inquiry-owned journey','sub_title'=>'Inactive imported blueprint. Entry events and stop rules are preserved; current-inquiry enrollment requires its separately verified owner.','fields'=>['entry_event'=>['type'=>'input-text','label'=>'Recorded entry event']]];}
            public function handle($funnel,$originalArgs){return null;}
        };
    }
}
