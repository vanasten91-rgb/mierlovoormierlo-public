<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Assets {
    /** @return array<string,array<string,string|bool>> */
    public static function manifest(): array {
        $base=plugin_dir_url(MVM_HUB_FILE).'assets/';
        return array(
            'style'=>array('handle'=>'mvm-hub','src'=>$base.'hub.css','version'=>MVM_HUB_VERSION),
            'sectionsStyle'=>array('handle'=>'mvm-hub-sections','src'=>$base.'hub-sections.css','version'=>MVM_HUB_VERSION),
            'newsroomPreviewStyle'=>array('handle'=>'mvm-hub-newsroom-preview','src'=>$base.'newsroom-preview.css','version'=>MVM_HUB_VERSION),
            'newsroomRuntimeStyle'=>array('handle'=>'mvm-hub-newsroom-runtime','src'=>$base.'newsroom-runtime.css','version'=>MVM_HUB_VERSION),
            'communicationsStyle'=>array('handle'=>'mvm-hub-communications','src'=>$base.'communications.css','version'=>MVM_HUB_VERSION),
            'communicationsUiStyle'=>array('handle'=>'mvm-hub-communications-ui','src'=>$base.'communications-ui.css','version'=>MVM_HUB_VERSION),
            'script'=>array('handle'=>'mvm-hub-shell','src'=>$base.'hub-shell.js','version'=>MVM_HUB_VERSION,'in_footer'=>true),
            'newsroomScript'=>array('handle'=>'mvm-hub-newsroom','src'=>$base.'newsroom.js','version'=>MVM_HUB_VERSION,'in_footer'=>true),
            'communicationsScript'=>array('handle'=>'mvm-hub-communications','src'=>$base.'communications.js','version'=>MVM_HUB_VERSION,'in_footer'=>true),
            'communicationsUiScript'=>array('handle'=>'mvm-hub-communications-ui','src'=>$base.'communications-ui.js','version'=>MVM_HUB_VERSION,'in_footer'=>true),
        );
    }
    private function __construct(){}
}
