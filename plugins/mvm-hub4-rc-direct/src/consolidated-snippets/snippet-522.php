<?php
// Consolidated from production Code Snippet #522.
defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', static function () {
    ?>
    <style id="mvm-onetap-readability-v3">
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings,
    .onetap-accessibility-plugin .onetap-accessibility-settings,
    .onetap-accessibility-settings {
        background:#fff !important;
        color:#17222d !important;
        color-scheme:light !important;
    }
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings h1,
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings h2,
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings h3,
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings h4,
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings p,
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings label,
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings small,
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings strong,
    .onetap-accessibility-plugin .onetap-accessibility-settings h1,
    .onetap-accessibility-plugin .onetap-accessibility-settings h2,
    .onetap-accessibility-plugin .onetap-accessibility-settings h3,
    .onetap-accessibility-plugin .onetap-accessibility-settings h4,
    .onetap-accessibility-plugin .onetap-accessibility-settings p,
    .onetap-accessibility-plugin .onetap-accessibility-settings label,
    .onetap-accessibility-plugin .onetap-accessibility-settings small,
    .onetap-accessibility-plugin .onetap-accessibility-settings strong {
        color:#17222d !important;
        -webkit-text-fill-color:#17222d !important;
    }
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings header.onetap-header-top .onetap-site-container,
    .onetap-accessibility-plugin .onetap-accessibility-settings header.onetap-header-top .onetap-site-container {
        background:#1966AE !important;
        color:#fff !important;
    }
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings header.onetap-header-top .onetap-site-container *,
    .onetap-accessibility-plugin .onetap-accessibility-settings header.onetap-header-top .onetap-site-container * {
        color:#fff !important;
        -webkit-text-fill-color:#fff !important;
    }
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings select,
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings option,
    .onetap-accessibility-plugin .onetap-accessibility-settings select,
    .onetap-accessibility-plugin .onetap-accessibility-settings option {
        background:#fff !important;
        color:#17222d !important;
        -webkit-text-fill-color:#17222d !important;
        border-color:#8299ad !important;
    }
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings .mvm-onetap-dark-text,
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings .mvm-onetap-dark-text *,
    .onetap-accessibility-plugin .onetap-accessibility-settings .mvm-onetap-dark-text,
    .onetap-accessibility-plugin .onetap-accessibility-settings .mvm-onetap-dark-text * {
        color:#17222d !important;
        -webkit-text-fill-color:#17222d !important;
    }
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings .mvm-onetap-light-text,
    nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings .mvm-onetap-light-text *,
    .onetap-accessibility-plugin .onetap-accessibility-settings .mvm-onetap-light-text,
    .onetap-accessibility-plugin .onetap-accessibility-settings .mvm-onetap-light-text * {
        color:#fff !important;
        -webkit-text-fill-color:#fff !important;
    }

    /* OneTap Licht contrast zet standaard bijna alle spans op wit. Binnen het eigen paneel
       herstellen we alleen tekstdragers naar transparant, zodat echte knop- en paneelvlakken blijven staan. */
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings span,
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings p,
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings strong,
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings small,
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings label,
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings li,
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings h1,
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings h2,
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings h3,
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings h4,
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings h5,
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings h6,
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings a {
        background-color:transparent !important;
    }
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings {
        background:#fff !important;
        color:#17222d !important;
    }
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings header.onetap-header-top .onetap-site-container {
        background:#1966AE !important;
        color:#fff !important;
    }
    body.onetap-light-contrast nav.onetap-accessibility.onetap-plugin-onetap .onetap-accessibility-settings header.onetap-header-top .onetap-site-container * {
        background-color:transparent !important;
        color:#fff !important;
        -webkit-text-fill-color:#fff !important;
    }

    .onetap-accessibility-settings button:focus-visible,
    .onetap-accessibility-settings a:focus-visible,
    .onetap-accessibility-settings select:focus-visible,
    .onetap-accessibility-settings [role="button"]:focus-visible {
        outline:3px solid #ffbf47 !important;
        outline-offset:2px !important;
    }
    </style>
    <?php
}, 1000001 );

add_action( 'wp_footer', static function () {
    ?>
    <script id="mvm-onetap-readability-v3-js">
    (function(){
        function rgba(v){
            var m=(v||'').match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([0-9.]+))?/i);
            return m ? [Number(m[1]),Number(m[2]),Number(m[3]),m[4]===undefined?1:Number(m[4])] : null;
        }
        function bg(el){
            var n=el;
            while(n&&n!==document.documentElement){
                var c=rgba(getComputedStyle(n).backgroundColor);
                if(c&&c[3]>.15) return c;
                n=n.parentElement;
            }
            return [255,255,255,1];
        }
        function lum(c){
            function f(x){x/=255;return x<=.03928?x/12.92:Math.pow((x+.055)/1.055,2.4);}
            return .2126*f(c[0])+.7152*f(c[1])+.0722*f(c[2]);
        }
        function apply(){
            document.querySelectorAll('.onetap-accessibility-settings').forEach(function(root){
                root.querySelectorAll('button,a,[role=button],.onetap-box-feature').forEach(function(el){
                    var want=lum(bg(el))>.42?'mvm-onetap-dark-text':'mvm-onetap-light-text';
                    var other=want==='mvm-onetap-dark-text'?'mvm-onetap-light-text':'mvm-onetap-dark-text';
                    if(el.classList.contains(want)&&!el.classList.contains(other)) return;
                    el.classList.remove(other);
                    el.classList.add(want);
                });
            });
        }
        var queued=false;
        function schedule(){if(queued)return;queued=true;requestAnimationFrame(function(){queued=false;apply();});}
        if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',schedule);else schedule();
        window.addEventListener('load',schedule,{once:true});
        if(window.MutationObserver){new MutationObserver(schedule).observe(document.documentElement,{childList:true,subtree:true,attributes:true,attributeFilter:['class','style','aria-pressed']});}
    })();
    </script>
    <?php
}, 1000001 );