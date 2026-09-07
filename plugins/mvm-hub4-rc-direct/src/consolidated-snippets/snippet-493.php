<?php
// Consolidated from production Code Snippet #493.
defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', static function () {
    ?>
    <style id="mvm-button-contrast-lock-v4">
    /* Vaste MVM-knoppen: blauw betekent altijd witte tekst. */
    .mvm-public-v1__button,
    .mvm-public-v1__button:link,
    .mvm-public-v1__button:visited,
    .mvm-public-v1__button:hover,
    .mvm-public-v1__button:focus,
    .mvm-public-v1__button:focus-visible,
    .mvm-staff-submit,
    .mvm-staff-submit:hover,
    .mvm-staff-submit:focus,
    .mvm-newsletter__button:not(.mvm-newsletter__button--danger),
    .mvm-newsletter__button:not(.mvm-newsletter__button--danger):hover,
    .mvm-newsletter__button:not(.mvm-newsletter__button--danger):focus,
    .mvm-organisations-v1__button,
    .mvm-organisations-v1__button:hover,
    .mvm-organisations-v1__button:focus,
    .mvm-org-guide-v1__primary,
    .mvm-org-guide-v1__primary:hover,
    .mvm-org-guide-v1__primary:focus,
    body.mierlo-voor-mierlo-sub input[type="submit"],
    body.mierlo-voor-mierlo-sub input[type="submit"]:hover,
    body.mierlo-voor-mierlo-sub input[type="submit"]:focus,
    body.mierlo-voor-mierlo-sub .wpcf7-submit,
    body.mierlo-voor-mierlo-sub .wpcf7-submit:hover,
    body.mierlo-voor-mierlo-sub .wpcf7-submit:focus,
    body.mierlo-voor-mierlo-sub .wpem-theme-button,
    body.mierlo-voor-mierlo-sub .wpem-theme-button:hover,
    body.mierlo-voor-mierlo-sub .wpem-theme-button:focus,
    body.mierlo-voor-mierlo-sub .um-button,
    body.mierlo-voor-mierlo-sub .um-button:hover,
    body.mierlo-voor-mierlo-sub .um-button:focus,
    body.mierlo-voor-mierlo-sub .ps-btn--action,
    body.mierlo-voor-mierlo-sub .ps-btn--action:hover,
    body.mierlo-voor-mierlo-sub .ps-btn--action:focus,
    .mvm-blue-button-contrast-lock,
    .mvm-blue-button-contrast-lock:link,
    .mvm-blue-button-contrast-lock:visited,
    .mvm-blue-button-contrast-lock:hover,
    .mvm-blue-button-contrast-lock:focus,
    .mvm-blue-button-contrast-lock:focus-visible {
        color:#fff !important;
        -webkit-text-fill-color:#fff !important;
    }

    .mvm-public-v1__button *,
    .mvm-staff-submit *,
    .mvm-newsletter__button:not(.mvm-newsletter__button--danger) *,
    .mvm-organisations-v1__button *,
    .mvm-org-guide-v1__primary *,
    body.mierlo-voor-mierlo-sub .wpem-theme-button *,
    body.mierlo-voor-mierlo-sub .um-button *,
    body.mierlo-voor-mierlo-sub .ps-btn--action *,
    .mvm-blue-button-contrast-lock * {
        color:#fff !important;
        -webkit-text-fill-color:#fff !important;
        fill:#fff !important;
        stroke:currentColor !important;
    }
    </style>
    <?php
}, 999999 );

add_action( 'wp_footer', static function () {
    ?>
    <script id="mvm-button-contrast-lock-v4-js">
    (function(){
        function rgb(value){
            var m=(value||'').match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
            return m ? [parseInt(m[1],10),parseInt(m[2],10),parseInt(m[3],10)] : null;
        }
        function isBlue(c){
            if(!c) return false;
            var r=c[0],g=c[1],b=c[2];
            return b>=110 && b>r*1.18 && b>g*1.05 && (b-r)>=35 && g>=45;
        }
        function lock(root){
            var scope=root&&root.querySelectorAll?root:document;
            var els=scope.querySelectorAll('a,button,input[type=submit],input[type=button],input[type=reset],[role=button]');
            Array.prototype.forEach.call(els,function(el){
                var cs=window.getComputedStyle(el);
                if(isBlue(rgb(cs.backgroundColor))){
                    el.classList.add('mvm-blue-button-contrast-lock');
                }
            });
            if(root&&root.matches&&root.matches('a,button,input[type=submit],input[type=button],input[type=reset],[role=button]')){
                var cs=window.getComputedStyle(root);
                if(isBlue(rgb(cs.backgroundColor))) root.classList.add('mvm-blue-button-contrast-lock');
            }
        }
        function run(){ lock(document); }
        if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',run); else run();
        window.addEventListener('load',run,{once:true});
        if(window.MutationObserver){
            var mo=new MutationObserver(function(ms){ ms.forEach(function(m){ Array.prototype.forEach.call(m.addedNodes,function(n){ if(n.nodeType===1) lock(n); }); }); });
            mo.observe(document.documentElement,{childList:true,subtree:true});
        }
    })();
    </script>
    <?php
}, 999999 );