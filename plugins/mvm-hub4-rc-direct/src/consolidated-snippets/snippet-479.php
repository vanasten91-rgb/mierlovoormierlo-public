<?php
// Consolidated from production Code Snippet #479.
defined( 'ABSPATH' ) || exit;

add_shortcode( 'mvm_mijn_mierlo_quicknav_v1', static function () {
    if ( ! is_user_logged_in() ) { return ''; }
    $items = [
        [ '#mvm-my-new-title', 'Jouw update' ],
        [ '#mvm-my-recent-title', 'Recent bekeken' ],
        [ '#mvm-my-notifications-title', 'Meldingen' ],
        [ '#mvm-my-contributions-title', 'Mijn bijdragen' ],
        [ '#mvm-my-saved-events-title', 'Mijn agenda' ],
        [ '#mvm-my-agenda-title', 'Binnenkort' ],
        [ '#mvm-my-saved-title', 'Opgeslagen nieuws' ],
        [ '#mvm-my-ency-title', 'Encyclopedie' ],
        [ '#mvm-my-verenigingen-title', 'Verenigingen' ],
        [ '#mvm-my-heading', 'Nieuwsvoorkeuren' ],
    ];
    ob_start();
    echo '<nav class="mvm-my-quicknav-v1" aria-label="Onderdelen van Mijn Mierlo"><span class="mvm-my-quicknav-v1__label">Ga direct naar:</span><div class="mvm-my-quicknav-v1__links">';
    foreach ( $items as $item ) { echo '<a href="' . esc_attr( $item[0] ) . '">' . esc_html( $item[1] ) . '</a>'; }
    echo '</div></nav>';
    echo '<style id="mvm-my-quicknav-v1-css">.mvm-my-quicknav-v1{margin:0 0 22px;padding:14px;border:1px solid var(--mvm-border,#c5d3df);border-radius:12px;background:var(--mvm-bg,#f4f7fa)}.mvm-my-quicknav-v1__label{display:block;margin-bottom:9px;font-weight:700}.mvm-my-quicknav-v1__links{display:flex;flex-wrap:wrap;gap:8px}.mvm-my-quicknav-v1 a{display:inline-flex;padding:7px 10px;border-radius:999px;border:1px solid #1966AE;text-decoration:none}.mvm-my-quicknav-v1 a:hover,.mvm-my-quicknav-v1 a:focus-visible{background:#1966AE;color:#fff;outline-offset:2px}@media(max-width:560px){.mvm-my-quicknav-v1__links{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.mvm-my-quicknav-v1 a{justify-content:center;text-align:center}}@media(max-width:380px){.mvm-my-quicknav-v1__links{grid-template-columns:1fr}}</style>';
    return ob_get_clean();
} );