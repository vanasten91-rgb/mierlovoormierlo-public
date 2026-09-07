<?php
// Consolidated from production Code Snippet #498.
defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', static function () {
    if ( ! is_front_page() && ! is_home() ) { return; }
    ?>
    <style id="mvm-home-action-colors-v3">
    body.home .mvm-theme1-action-icon {
        background:#1966AE !important;
        color:#fff !important;
        -webkit-text-fill-color:#fff !important;
    }
    body.home .mvm-theme1-action-icon *,
    body.home .mvm-theme1-action-icon svg {
        color:#fff !important;
        fill:currentColor !important;
        stroke:currentColor !important;
        -webkit-text-fill-color:#fff !important;
    }
    body.home .mvm-theme1-action-label--blue {
        color:#1966AE !important;
        -webkit-text-fill-color:#1966AE !important;
    }
    body.home .mvm-theme1-action-card--blue-links a,
    body.home .mvm-theme1-action-card--blue-links a:link,
    body.home .mvm-theme1-action-card--blue-links a:visited,
    body.home .mvm-theme1-action-card--blue-links a:hover,
    body.home .mvm-theme1-action-card--blue-links a:focus,
    body.home .mvm-theme1-action-card--blue-links a:focus-visible,
    body.home .mvm-theme1-action-card--blue-links a * {
        color:#1966AE !important;
        -webkit-text-fill-color:#1966AE !important;
        fill:currentColor !important;
        stroke:currentColor !important;
    }
    </style>
    <?php
}, 9999 );

add_action( 'wp_footer', static function () {
    if ( ! is_front_page() && ! is_home() ) { return; }
    ?>
    <script id="mvm-home-action-colors-v3-js">
    (function(){
        var wanted = ['praat mee','vind elkaar','omkijken naar elkaar','kom in beweging'];
        function norm(s){ return (s || '').replace(/\s+/g,' ').trim().toLocaleLowerCase('nl-NL'); }
        function apply(){
            document.querySelectorAll('.mvm-theme1-action-icon').forEach(function(icon){
                var root = icon.parentElement;
                for (var depth=0; root && depth<6; depth++, root=root.parentElement) {
                    var found = Array.prototype.find.call(root.querySelectorAll('*'), function(el){
                        return el.children.length === 0 && wanted.indexOf(norm(el.textContent)) !== -1;
                    });
                    if (!found) continue;
                    found.classList.add('mvm-theme1-action-label--blue');
                    var card = icon.parentElement;
                    for (var level=0; card && level<6; level++, card=card.parentElement) {
                        if (card.contains(found) && card.querySelector('a') && card.querySelectorAll('.mvm-theme1-action-icon').length === 1) {
                            card.classList.add('mvm-theme1-action-card--blue-links');
                            break;
                        }
                    }
                    return;
                }
            });
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply); else apply();
        window.setTimeout(apply, 500);
    })();
    </script>
    <?php
}, 9999 );