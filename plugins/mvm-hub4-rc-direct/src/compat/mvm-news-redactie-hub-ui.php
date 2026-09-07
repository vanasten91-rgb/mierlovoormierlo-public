<?php
/**
 * MvM Nieuws/Redactie Hub — clean, role-separated hub layer.
 *
 * Canonical Hub UI, owned by the MvM Nieuws/Redactie Hub plugin.
 * Compatibility function names remain stable during the Hub 3 retirement window.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'MVM_HUBS_V3_VERSION' ) ) {
    define( 'MVM_HUBS_V3_VERSION', MVM_HUB4_VERSION );
}
if ( ! defined( 'MVM_HUBS_V3_CAPS_SCHEMA' ) ) {
    define( 'MVM_HUBS_V3_CAPS_SCHEMA', '2026.09.01-1' );
}

function mvm_hubs_v3_is_draft_preview(): bool {
    $stylesheet = (string) get_stylesheet();
    $draft      = (string) get_option( 'wpvibe_draft_theme', '' );

    if ( '' !== $draft && $draft === $stylesheet ) {
        return true;
    }
    if ( false !== strpos( $stylesheet, 'wpvibe-draft' ) ) {
        return true;
    }
    if ( isset( $_GET['wpvibe_preview'] ) && '' !== sanitize_text_field( wp_unslash( (string) $_GET['wpvibe_preview'] ) ) ) {
        return true;
    }

    $preview_origins = array(
        isset( $_REQUEST['_wp_http_referer'] ) ? (string) wp_unslash( $_REQUEST['_wp_http_referer'] ) : '',
        isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : '',
    );
    foreach ( $preview_origins as $origin ) {
        if ( '' !== $origin && false !== strpos( $origin, 'wpvibe_preview=' ) ) {
            return true;
        }
    }
    return false;
}

function mvm_hubs_v3_role_capability_map(): array {
    return array(
        'mvm_vereniging' => array( 'mvm_hub3_business_access' ),
        'mvm_club' => array( 'mvm_hub3_business_access' ),
        'mvm_organisator' => array( 'mvm_hub3_business_access' ),
        'mvm_ondernemer' => array( 'mvm_hub3_business_access' ),
        'mvm_bedrijf' => array( 'mvm_hub3_business_access' ),
        'mvm_winkelier' => array( 'mvm_hub3_business_access' ),
        'organizer' => array( 'mvm_hub3_business_access' ),
        'mvm_redacteur' => array( 'mvm_hub3_editorial_access' ),
        'mvm_fotograaf' => array( 'mvm_hub3_editorial_access' ),
        'mvm_vertaler' => array( 'mvm_hub3_editorial_access' ),
        'mvm_journalist' => array( 'mvm_hub3_editorial_access' ),
        'mvm_editor' => array( 'mvm_hub3_editorial_access', 'mvm_hub3_review_access' ),
        'mvm_moderator' => array( 'mvm_hub3_editorial_access', 'mvm_hub3_moderation_access' ),
        'mvm_teamleider' => array( 'mvm_hub3_editorial_access', 'mvm_hub3_review_access', 'mvm_hub3_team_planning_access' ),
        'mvm_sysop' => array( 'mvm_hub3_business_access', 'mvm_hub3_editorial_access', 'mvm_hub3_review_access', 'mvm_hub3_moderation_access', 'mvm_hub3_team_planning_access', 'mvm_hub3_admin_access' ),
        'administrator' => array( 'mvm_hub3_business_access', 'mvm_hub3_editorial_access', 'mvm_hub3_review_access', 'mvm_hub3_moderation_access', 'mvm_hub3_team_planning_access', 'mvm_hub3_admin_access' ),
    );
}

function mvm_hubs_v3_managed_capabilities(): array {
    return array(
        'mvm_hub3_business_access',
        'mvm_hub3_editorial_access',
        'mvm_hub3_review_access',
        'mvm_hub3_moderation_access',
        'mvm_hub3_team_planning_access',
        'mvm_hub3_admin_access',
    );
}

function mvm_hubs_v3_sync_capabilities(): void {
    if ( class_exists( 'MvM_Hub4_Hub_Security' ) || mvm_hubs_v3_is_draft_preview() || get_option( 'mvm_hubs_v3_caps_schema' ) === MVM_HUBS_V3_CAPS_SCHEMA ) {
        return;
    }

    $map      = mvm_hubs_v3_role_capability_map();
    $hub_caps = mvm_hubs_v3_managed_capabilities();
    $wp_roles = wp_roles();

    foreach ( array_keys( $wp_roles->roles ) as $role_key ) {
        $role = get_role( $role_key );
        if ( ! $role ) {
            continue;
        }
        foreach ( $hub_caps as $cap ) {
            $role->remove_cap( $cap );
        }
    }
    foreach ( $map as $role_key => $caps ) {
        $role = get_role( $role_key );
        if ( ! $role ) {
            continue;
        }
        foreach ( $caps as $cap ) {
            $role->add_cap( $cap );
        }
    }
    update_option( 'mvm_hubs_v3_caps_schema', MVM_HUBS_V3_CAPS_SCHEMA, false );
    update_option( 'mvm_hubs_v3_caps_version', MVM_HUBS_V3_VERSION, false );
}

function mvm_hubs_v3_capability_health(): array {
    if ( class_exists( 'MvM_Hub4_Hub_Security' ) ) {
        return MvM_Hub4_Hub_Security::capability_health();
    }

    $map           = mvm_hubs_v3_role_capability_map();
    $managed_caps  = mvm_hubs_v3_managed_capabilities();
    $roles_checked = 0;
    $drift_count   = 0;

    foreach ( array_keys( wp_roles()->roles ) as $role_key ) {
        $role = get_role( $role_key );
        if ( ! $role ) {
            continue;
        }
        ++$roles_checked;
        $allowed = $map[ $role_key ] ?? array();
        foreach ( $managed_caps as $cap ) {
            if ( $role->has_cap( $cap ) !== in_array( $cap, $allowed, true ) ) {
                ++$drift_count;
            }
        }
    }

    return array(
        'ok'            => 0 === $drift_count,
        'roles_checked' => $roles_checked,
        'drift_count'   => $drift_count,
    );
}
add_action( 'init', 'mvm_hubs_v3_sync_capabilities', 2 );

function mvm_hubs_v3_routes(): array {
    return array(
        'user' => array(
            'path'  => '/mijn-hub/',
            'title' => 'Mijn Mierlo',
            'label' => 'Gebruikershub',
            'icon'  => 'user',
        ),
        'business' => array(
            'path'  => '/ondernemers-hub/',
            'title' => 'Organisatiehub',
            'label' => 'Voor organisaties & ondernemers',
            'icon'  => 'briefcase',
        ),
        'editorial' => array(
            'path'  => '/redactie-hub/',
            'title' => 'Redactiehub',
            'label' => 'Voor het redactieteam',
            'icon'  => 'newspaper',
        ),
        'admin' => array(
            'path'  => '/beheer-hub/',
            'title' => 'Technische hub',
            'label' => 'Beheer & veiligheid',
            'icon'  => 'shield',
        ),
    );
}

function mvm_hubs_v3_request_path(): string {
    $path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/', PHP_URL_PATH );
    return '/' . trim( is_string( $path ) ? $path : '/', '/' ) . '/';
}

function mvm_hubs_v3_allowed_sections( string $hub ): array {
    if ( class_exists( 'MvM_Hub4_Hub_Security' ) ) {
        return MvM_Hub4_Hub_Security::allowed_sections( $hub );
    }
    $allowed = array(
        'user'      => array( 'organisatieaccount', 'opgeslagen' ),
        'business'  => array( 'profiel', 'vacature', 'evenement' ),
        'editorial' => array( 'mijnwerk', 'review', 'nieuwsradar', 'bronnen', 'mail', 'vacatures', 'planning', 'team', 'communicatie', 'moderatie' ),
        'admin'     => array( 'layouts', 'homeblokken', 'veiligheid', 'integraties', 'onderhoud', 'herstel', 'organisaties' ),
    );
    return $allowed[ $hub ] ?? array();
}

function mvm_hubs_v3_login_target( string $hub, string $deel = '' ): string {
    $routes = mvm_hubs_v3_routes();
    if ( ! isset( $routes[ $hub ] ) ) {
        return home_url( '/mijn-hub/' );
    }

    if ( '' === $deel ) {
        $deel = isset( $_GET['deel'] ) ? sanitize_key( wp_unslash( $_GET['deel'] ) ) : '';
    } else {
        $deel = sanitize_key( $deel );
    }

    $target = home_url( $routes[ $hub ]['path'] );
    if ( $deel && in_array( $deel, mvm_hubs_v3_allowed_sections( $hub ), true ) ) {
        $target = add_query_arg( 'deel', $deel, $target );
    }
    return $target;
}

function mvm_hubs_v3_remember_login_target( string $hub ): void {
    if ( headers_sent() ) {
        return;
    }
    $deel = isset( $_GET['deel'] ) ? sanitize_key( wp_unslash( $_GET['deel'] ) ) : '';
    if ( ! $deel || ! in_array( $deel, mvm_hubs_v3_allowed_sections( $hub ), true ) ) {
        return;
    }
    setcookie(
        'mvm_hub_return_v1',
        $hub . ':' . $deel,
        array(
            'expires'  => time() + 600,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        )
    );
}

function mvm_hubs_v3_forget_login_target(): void {
    if ( ! headers_sent() ) {
        setcookie(
            'mvm_hub_return_v1',
            '',
            array(
                'expires'  => time() - HOUR_IN_SECONDS,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );
    }
    unset( $_COOKIE['mvm_hub_return_v1'] );
}

function mvm_hubs_v3_consume_login_target( int $user_id ): string {
    $raw = isset( $_COOKIE['mvm_hub_return_v1'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['mvm_hub_return_v1'] ) ) : '';
    if ( '' === $raw ) {
        return '';
    }
    if ( false === strpos( $raw, ':' ) ) {
        mvm_hubs_v3_forget_login_target();
        return '';
    }
    list( $hub, $deel ) = array_map( 'sanitize_key', explode( ':', $raw, 2 ) );
    if ( ! isset( mvm_hubs_v3_routes()[ $hub ] ) || ! in_array( $deel, mvm_hubs_v3_allowed_sections( $hub ), true ) ) {
        mvm_hubs_v3_forget_login_target();
        return '';
    }
    if ( ! mvm_hubs_v3_can_access( $hub, $user_id ) ) {
        mvm_hubs_v3_forget_login_target();
        return '';
    }
    $target = mvm_hubs_v3_login_target( $hub, $deel );
    mvm_hubs_v3_forget_login_target();
    return $target;
}

function mvm_hubs_v3_current_hub(): string {
    $request = mvm_hubs_v3_request_path();
    foreach ( mvm_hubs_v3_routes() as $key => $route ) {
        if ( $request === $route['path'] ) {
            return $key;
        }
    }
    return '';
}

function mvm_hubs_v3_user_roles( int $user_id = 0 ): array {
    $user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
    return ( $user && $user->exists() ) ? array_values( (array) $user->roles ) : array();
}

function mvm_hubs_v3_has_any_role( array $allowed, int $user_id = 0 ): bool {
    return (bool) array_intersect( mvm_hubs_v3_user_roles( $user_id ), $allowed );
}

function mvm_hubs_v3_can_access( string $hub, int $user_id = 0 ): bool {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id ) {
        return false;
    }
    if ( class_exists( 'MvM_Hub4_Hub_Security' ) ) {
        return MvM_Hub4_Hub_Security::can_access( $hub, $user_id );
    }

    $roles = mvm_hubs_v3_user_roles( $user_id );
    if ( 'user' === $hub ) {
        return true;
    }

    $caps = array(
        'business'  => 'mvm_hub3_business_access',
        'editorial' => 'mvm_hub3_editorial_access',
        'admin'     => 'mvm_hub3_admin_access',
    );
    if ( isset( $caps[ $hub ] ) && user_can( $user_id, $caps[ $hub ] ) ) {
        return true;
    }

    $capabilities_live = ! mvm_hubs_v3_is_draft_preview() && get_option( 'mvm_hubs_v3_caps_schema' ) === MVM_HUBS_V3_CAPS_SCHEMA;
    if ( $capabilities_live ) {
        return false;
    }

    if ( in_array( 'administrator', $roles, true ) || in_array( 'mvm_sysop', $roles, true ) ) {
        return true;
    }
    if ( 'business' === $hub ) {
        return (bool) array_intersect(
            $roles,
            array( 'mvm_vereniging', 'mvm_club', 'mvm_organisator', 'mvm_ondernemer', 'mvm_bedrijf', 'mvm_winkelier', 'organizer' )
        );
    }
    if ( 'editorial' === $hub ) {
        return (bool) array_intersect( $roles, array( 'mvm_redacteur', 'mvm_fotograaf', 'mvm_moderator', 'mvm_editor', 'mvm_vertaler', 'mvm_journalist', 'mvm_teamleider' ) );
    }
    return false;
}

function mvm_hubs_v3_can_moderate( int $user_id = 0 ): bool {
    $user_id = $user_id ?: get_current_user_id();
    if ( class_exists( 'MvM_Hub4_Hub_Security' ) ) {
        return MvM_Hub4_Hub_Security::can_moderate( $user_id );
    }
    if ( $user_id && ! mvm_hubs_v3_is_draft_preview() && user_can( $user_id, 'mvm_hub3_moderation_access' ) ) {
        return true;
    }
    $capabilities_live = ! mvm_hubs_v3_is_draft_preview() && get_option( 'mvm_hubs_v3_caps_schema' ) === MVM_HUBS_V3_CAPS_SCHEMA;
    if ( $capabilities_live ) {
        return false;
    }
    return mvm_hubs_v3_has_any_role( array( 'administrator', 'mvm_sysop', 'mvm_moderator' ), $user_id );
}

function mvm_hubs_v3_can_review( int $user_id = 0 ): bool {
    $user_id = $user_id ?: get_current_user_id();
    if ( class_exists( 'MvM_Hub4_Hub_Security' ) ) {
        return MvM_Hub4_Hub_Security::can_review( $user_id );
    }
    if ( $user_id && user_can( $user_id, 'mvm_hub3_review_access' ) ) {
        return true;
    }
    $capabilities_live = ! mvm_hubs_v3_is_draft_preview() && get_option( 'mvm_hubs_v3_caps_schema' ) === MVM_HUBS_V3_CAPS_SCHEMA;
    if ( $capabilities_live ) {
        return false;
    }
    return mvm_hubs_v3_has_any_role( array( 'administrator', 'mvm_sysop', 'mvm_editor', 'mvm_teamleider' ), $user_id );
}

function mvm_hubs_v3_can_submit_editorial( int $user_id = 0 ): bool {
    $user_id = $user_id ?: get_current_user_id();
    if ( class_exists( 'MvM_Hub4_Hub_Security' ) ) {
        return MvM_Hub4_Hub_Security::can_submit_editorial( $user_id );
    }
    if ( ! $user_id || ! mvm_hubs_v3_can_access( 'editorial', $user_id ) ) {
        return false;
    }
    return user_can( $user_id, 'edit_posts' ) || user_can( $user_id, 'mvm_submit_articles' );
}

function mvm_hubs_v3_primary_hub( int $user_id = 0 ): string {
    $user_id = $user_id ?: get_current_user_id();
    if ( class_exists( 'MvM_Hub4_Hub_Security' ) ) {
        return MvM_Hub4_Hub_Security::primary_hub( $user_id );
    }
    foreach ( array( 'admin', 'editorial', 'business' ) as $hub ) {
        if ( mvm_hubs_v3_can_access( $hub, $user_id ) ) {
            return $hub;
        }
    }
    return $user_id ? 'user' : '';
}

function mvm_hubs_v3_icon( string $name ): string {
    $icons = array(
        'user'       => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0"/></svg>',
        'briefcase'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7V5h6v2m-10 0h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Zm-2 5h18"/></svg>',
        'newspaper'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h13v14H4zM17 8h3v11h-3M7 8h7M7 11h7M7 14h4"/></svg>',
        'shield'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 7 3v5c0 4.7-2.8 8.1-7 10-4.2-1.9-7-5.3-7-10V6l7-3Zm-3 9 2 2 4-4"/></svg>',
        'bookmark'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h12v17l-6-4-6 4V4Z"/></svg>',
        'envelope'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4V6Zm0 1 8 6 8-6"/></svg>',
        'activity'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h4l2-6 4 12 2-6h6"/></svg>',
        'group'      => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8 0a3 3 0 1 0 0-6M2 20a6 6 0 0 1 12 0m1-6a5 5 0 0 1 7 5"/></svg>',
        'forum'      => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H8l-4 4V5Zm4 4h8m-8 3h5"/></svg>',
        'plus'       => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
        'megaphone'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11v3h4l8 4V7l-8 4H4Zm4 3 1 5h3l-1-4"/></svg>',
        'calendar'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v15H5zM8 3v4m8-4v4M5 9h14"/></svg>',
        'check'      => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
        'settings'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm0-6v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1"/></svg>',
    );
    return $icons[ $name ] ?? $icons['check'];
}

function mvm_hubs_v3_card( string $icon, string $title, string $text, string $url = '', string $action = 'Openen', string $meta = '' ): string {
    $link = $url ? '<a class="mvmh3-card__action" href="' . esc_url( $url ) . '">' . esc_html( $action ) . '<span aria-hidden="true">→</span></a>' : '';
    $meta_html = $meta ? '<p class="mvmh3-card__meta">' . esc_html( $meta ) . '</p>' : '';
    return '<article class="mvmh3-card"><div class="mvmh3-card__icon">' . mvm_hubs_v3_icon( $icon ) . '</div><div class="mvmh3-card__body"><h3>' . esc_html( $title ) . '</h3><p>' . esc_html( $text ) . '</p>' . $meta_html . $link . '</div></article>';
}

function mvm_hubs_v3_saved_items(): array {
    if ( ! is_user_logged_in() || ! function_exists( 'mvm_bookmark_get_user_items_v1' ) ) {
        return array();
    }
    return (array) mvm_bookmark_get_user_items_v1( get_current_user_id(), 8 );
}

function mvm_hubs_v3_user_content(): string {
    $saved = mvm_hubs_v3_saved_items();
    $saved_count = count( $saved );
    $profile = function_exists( 'mvm_get_current_profile_url' ) ? mvm_get_current_profile_url() : home_url( '/activity/' );

    $html  = '<section class="mvmh3-intro"><div><span class="mvmh3-eyebrow">Mijn Mierlo</span><h1>Alles van jou, op één rustige plek.</h1><p>Bekijk wat er voor jou speelt, open je berichten of forum en vind terug wat je hebt opgeslagen.</p></div><a class="mvmh3-primary" href="' . esc_url( $profile ) . '">Mijn profiel</a></section>';
    $html .= '<div class="mvmh3-grid">';
    $html .= mvm_hubs_v3_card( 'bookmark', 'Opgeslagen', 'Nieuws, evenementen en andere MvM-items die je voor later hebt bewaard.', home_url( '/mijn-hub/?deel=opgeslagen' ), 'Bekijk opgeslagen', $saved_count . ' opgeslagen' );
    $html .= mvm_hubs_v3_card( 'activity', 'Community', 'Bekijk je recente activiteit en ga verder waar je gebleven was.', home_url( '/activity/' ), 'Naar community' );
    $html .= mvm_hubs_v3_card( 'activity', 'Berichten', 'Open je privéberichten.', home_url( '/messages/' ), 'Naar berichten' );
    $html .= mvm_hubs_v3_card( 'check', 'Meldingen', 'Bekijk reacties, verzoeken en andere meldingen die aandacht nodig hebben.', home_url( '/notifications/' ), 'Naar meldingen' );
    $html .= mvm_hubs_v3_card( 'forum', 'Forum', 'Volg gesprekken, stel een vraag of help een andere Mierlonaar.', home_url( '/forum/' ), 'Naar forum' );
    $html .= '</div>';
    $personal_api = has_action( 'wp_ajax_mvm_personal_home_v1' );
    $html .= '<section class="mvmh3-section mvmh3-personal-overview" data-mvmh3-personal-summary><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Alleen voor jou</span><h2>Mijn persoonlijke overzicht</h2></div><p>' . esc_html( $personal_api ? 'Deze gegevens worden pas na login privé opgehaald en niet in de openbare paginacache geplaatst.' : 'De persoonlijke datalaag is momenteel niet beschikbaar.' ) . '</p></div><div class="mvmh3-personal-counts"><div><span>Opgeslagen</span><strong data-mvmh3-personal-count="saved">—</strong></div><div><span>Forumbladwijzers</span><strong data-mvmh3-personal-count="forumBookmarks">—</strong></div><div><span>Forum gevolgd</span><strong data-mvmh3-personal-count="forumFollows">—</strong></div><div><span>Mijn agenda</span><strong data-mvmh3-personal-count="events">—</strong></div></div><div class="mvmh3-personal-events"><div class="mvmh3-personal-events__head"><strong>Komende activiteiten</strong><span>Alleen evenementen waarvoor jij hebt aangegeven dat je gaat of geïnteresseerd bent.</span></div><div class="mvmh3-list" data-mvmh3-personal-events><div class="mvmh3-empty"><strong>' . esc_html( $personal_api ? 'Persoonlijke agenda laden…' : 'Persoonlijke agenda niet beschikbaar.' ) . '</strong><span>Deze inhoud wordt niet server-side in de pagina opgenomen.</span></div></div></div></section>';
    $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Iets doen</span><h2>Wat wil je doen?</h2></div><p>Kies een actie en je gaat direct naar de juiste plek.</p></div><div class="mvmh3-action-row">';
    $html .= '<a href="' . esc_url( home_url( '/groepen/' ) ) . '"><strong>Groep maken</strong><span>Voor een onderwerp of gemeenschap</span></a>';
    if ( function_exists( 'mvm_e2_can_manage_pages' ) && mvm_e2_can_manage_pages() ) {
        $html .= '<a href="' . esc_url( home_url( '/paginas/' ) ) . '"><strong>MvM-pagina maken</strong><span>Voor jouw vereniging, club, organisatie of bedrijf</span></a>';
    }
    $html .= '<a href="' . esc_url( home_url( '/photos/' ) ) . '"><strong>Foto delen</strong><span>Deel een foto met de community</span></a><a href="' . esc_url( home_url( '/forum/' ) ) . '"><strong>Vraag stellen</strong><span>Start een gesprek op het forum</span></a>';
    $has_mvm_org_role = function_exists( 'mvm_hubs_v3_has_mvm_org_role' ) ? mvm_hubs_v3_has_mvm_org_role() : false;
    if ( ! $has_mvm_org_role ) {
        $html .= '<a href="' . esc_url( home_url( '/mijn-hub/?deel=organisatieaccount' ) ) . '"><strong>Organisatieaccount</strong><span>Voor vereniging, club, organisator, bedrijf of winkel</span></a>';
    }
    $html .= '</div></section>';

    $deel = isset( $_GET['deel'] ) ? sanitize_key( wp_unslash( $_GET['deel'] ) ) : '';
    if ( 'organisatieaccount' === $deel && ! $has_mvm_org_role ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Organisatieaccount</span><h2>Welk account past bij jou?</h2></div><p>Een organisatieaccount geeft alleen extra functies die echt nodig zijn. MvM controleert de aanvraag voordat extra rechten worden toegekend.</p></div><div class="mvmh3-grid mvmh3-grid--compact">';
        $html .= mvm_hubs_v3_card( 'group', 'Vereniging', 'Eigen MvM-pagina, aanbiedingen en promoties, en evenementen.', '', '', 'Pagina · promotie · evenementen' );
        $html .= mvm_hubs_v3_card( 'group', 'Club', 'Voor een club, team of lokale groep met eigen pagina, promoties en evenementen.', '', '', 'Pagina · promotie · evenementen' );
        $html .= mvm_hubs_v3_card( 'calendar', 'Organisator', 'Voor lokale activiteiten: eigen pagina, promoties en evenementen.', '', '', 'Pagina · promotie · evenementen' );
        $html .= mvm_hubs_v3_card( 'briefcase', 'Ondernemer / Bedrijf', 'Voor commerciele organisaties: pagina, promoties, evenementen en vacatures.', '', '', 'Pagina · promotie · vacatures · evenementen' );
        $html .= mvm_hubs_v3_card( 'briefcase', 'Winkelier', 'Voor lokale winkels: pagina, aanbiedingen, evenementen en vacatures.', '', '', 'Pagina · promotie · vacatures · evenementen' );
        $html .= '</div><div class="mvmh3-callout"><strong>Waarom controleert MvM dit?</strong><span>Een organisatieaccount geeft extra publicatie- en beheerfuncties. Daarom worden deze rechten nooit automatisch aan een gewoon account toegevoegd. Zo blijft duidelijk wie namens een organisatie handelt.</span></div>';
        if ( function_exists( 'mvm_hubs_v3_org_request_form_html' ) ) {
            $html .= mvm_hubs_v3_org_request_form_html();
        }
        $html .= '<div class="mvmh3-callout"><strong>Hulp nodig bij de aanvraag?</strong><span>Gebruik Contact of mail contact@mierlovoormierlo.nl. Vermeld je accountnaam en de organisatie waarvoor je toegang aanvraagt.</span></div><div class="mvmh3-action-row"><a href="' . esc_url( home_url( '/contact/' ) ) . '"><strong>Contact</strong><span>Vraag hulp of geef extra informatie</span></a><a href="mailto:contact@mierlovoormierlo.nl?subject=Organisatieaccount%20aanvragen"><strong>E-mail</strong><span>contact@mierlovoormierlo.nl</span></a></div></section>';
    }
    if ( 'opgeslagen' === $deel ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Persoonlijk</span><h2>Opgeslagen items</h2></div><p>Deze lijst is privé en wordt rechtstreeks uit jouw WordPress-usermeta gelezen.</p></div>';
        if ( ! $saved ) {
            $html .= '<div class="mvmh3-empty"><strong>Nog niets opgeslagen.</strong><span>Gebruik op nieuws en evenementen de knop Opslaan om hier een persoonlijke leeslijst op te bouwen.</span></div>';
        } else {
            $html .= '<div class="mvmh3-list">';
            foreach ( $saved as $item ) {
                $post_id = is_array( $item ) && isset( $item['id'] ) ? absint( $item['id'] ) : ( is_object( $item ) && isset( $item->ID ) ? absint( $item->ID ) : 0 );
                $post = $post_id ? get_post( $post_id ) : null;
                if ( ! $post instanceof WP_Post ) {
                    continue;
                }
                if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post_id ) ) {
                    continue;
                }
                $html .= '<a class="mvmh3-list__row" href="' . esc_url( get_permalink( $post_id ) ) . '"><span>' . esc_html( get_the_title( $post_id ) ) . '</span><span aria-hidden="true">→</span></a>';
            }
            $html .= '</div>';
        }
        $html .= '</section>';
    }

    return $html;
}

function mvm_hubs_v3_register_audit_type(): void {
    register_post_type(
        'mvm_hub_audit_v3',
        array(
            'label'               => 'MvM Hub audit v3',
            'public'              => false,
            'show_ui'             => false,
            'show_in_rest'        => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'supports'            => array( 'title', 'editor', 'author' ),
        )
    );
}
if ( ! class_exists( 'MvM_Hub4_Hub_Audit' ) ) {
    add_action( 'init', 'mvm_hubs_v3_register_audit_type' );
}

function mvm_hubs_v3_audit( string $event, array $context = array() ): void {
    if ( class_exists( 'MvM_Hub4_Hub_Audit' ) ) {
        MvM_Hub4_Hub_Audit::record( $event, $context );
        return;
    }
    if ( mvm_hubs_v3_is_draft_preview() || ! is_user_logged_in() ) {
        return;
    }
    $safe = array_intersect_key( $context, array_flip( array( 'hub', 'object_type', 'object_id', 'result' ) ) );
    wp_insert_post(
        array(
            'post_type'    => 'mvm_hub_audit_v3',
            'post_status'  => 'private',
            'post_title'   => sanitize_text_field( $event ),
            'post_content' => wp_json_encode( $safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'post_author'  => get_current_user_id(),
        ),
        true
    );
}

function mvm_hubs_v3_vacancy_manager_html(): string {
    $employment = array( '', 'Fulltime', 'Parttime', 'Bijbaan', 'Stage', 'Vrijwillig', 'Tijdelijk', 'ZZP / opdracht' );
    $preview = mvm_hubs_v3_is_draft_preview();
    $html = '<section class="mvmh3-section" data-mvmh3-vacancies data-mvmh3-preview="' . ( $preview ? '1' : '0' ) . '"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Vacature</span><h2>Vacature indienen</h2></div><p>Nieuwe vacatures en inhoudelijke wijzigingen gaan eerst naar MvM voor controle.</p></div>';
    if ( $preview ) {
        $html .= '<div class="mvmh3-callout"><strong>Previewmodus</strong><span>Vacaturebeheer is hier alleen-lezen. Je kunt de vormgeving en bestaande lijst controleren zonder live vacatures te wijzigen.</span></div>';
    }
    $html .= '<ol class="mvmh3-steps"><li><strong>1. Vul in</strong><span>Voeg de functie, organisatie, locatie, inhoud en contactroute toe.</span></li><li><strong>2. Dien in</strong><span>Je vacature krijgt de status Ter beoordeling en is nog niet openbaar.</span></li><li><strong>3. MvM controleert</strong><span>Na controle wordt de vacature gepubliceerd of krijg je bericht dat iets aangepast moet worden.</span></li></ol>';
    $html .= '<form class="mvmh3-form" data-mvmh3-vacancy-form><fieldset class="mvmh3-preview-safe"' . ( $preview ? ' disabled aria-disabled="true"' : '' ) . '><input type="hidden" name="id" value=""><input type="hidden" name="image_url" value=""><div class="mvmh3-form__grid">';
    $html .= '<label><span>Functietitel</span><input type="text" name="title" maxlength="180" required></label>';
    $html .= '<label><span>Bedrijf / organisatie</span><input type="text" name="company" maxlength="180" autocomplete="organization" required></label>';
    $html .= '<label><span>Locatie</span><input type="text" name="location" maxlength="180" value="Mierlo"></label>';
    $html .= '<label><span>Dienstverband</span><select name="employment">';
    foreach ( $employment as $value ) {
        $label = '' === $value ? 'Kies dienstverband' : $value;
        $html .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
    }
    $html .= '</select></label>';
    $html .= '<label><span>Uren</span><input type="text" name="hours" maxlength="80" placeholder="Bijv. 24-32 uur"></label>';
    $html .= '<label><span>Sluitingsdatum</span><input type="date" name="closing_date"></label>';
    $html .= '<label><span>Salaris vanaf (€)</span><input type="number" name="salary_min" min="0" step="0.01"></label>';
    $html .= '<label><span>Salaris tot (€)</span><input type="number" name="salary_max" min="0" step="0.01"></label>';
    $html .= '<label><span>Salarisperiode</span><select name="salary_unit"><option value="">Geen salarisindicatie</option><option value="HOUR">Per uur</option><option value="MONTH">Per maand</option><option value="YEAR">Per jaar</option></select></label>';
    $html .= '<label><span>Contactpersoon</span><input type="text" name="contact_name" maxlength="160"></label>';
    $html .= '<label class="mvmh3-form__wide"><span>Sollicitatie-URL</span><input type="url" name="application_url" placeholder="https://…"></label>';
    $html .= '<label><span>Contact e-mail</span><input type="email" name="contact_email" autocomplete="email"></label>';
    $html .= '<label><span>Afbeelding</span><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"><small>JPEG, PNG of WebP · maximaal 8 MB.</small></label>';
    $html .= '<label class="mvmh3-form__wide"><span>Korte samenvatting</span><textarea name="excerpt" maxlength="500" rows="3"></textarea></label>';
    $html .= '<label class="mvmh3-form__wide"><span>Vacaturetekst</span><textarea name="content" maxlength="50000" rows="8" required></textarea></label>';
    $html .= '<label data-mvmh3-vacancy-status-field hidden><span>Status</span><select name="post_status"><option value="pending">Ter beoordeling</option><option value="draft">Concept</option></select><small>Een bestaande vacature kun je als concept bewaren of opnieuw ter beoordeling indienen.</small></label>';
    $html .= '</div><div class="mvmh3-form__footer"><p data-mvmh3-vacancy-status role="status" aria-live="polite">Nieuwe vacatures gaan eerst ter beoordeling bij MvM.</p><button class="mvmh3-primary" type="submit">Vacature indienen</button></div></fieldset></form>';
    $html .= '<div class="mvmh3-section__head mvmh3-vacancies__list-head"><div><span class="mvmh3-eyebrow">Mijn vacatures</span><h2>Overzicht</h2></div><p data-mvmh3-vacancy-list-status role="status" aria-live="polite">Vacatures laden…</p></div><div class="mvmh3-list" data-mvmh3-vacancy-list></div></section>';
    return $html;
}

function mvm_hubs_v3_business_tasks( int $user_id = 0 ): array {
    $user_id = $user_id ?: get_current_user_id();
    $roles   = mvm_hubs_v3_user_roles( $user_id );
    $admin   = (bool) array_intersect( $roles, array( 'administrator', 'mvm_sysop' ) );
    $profile = array();

    if ( function_exists( 'mvm_e2_organization_profile' ) ) {
        $user    = $user_id ? get_userdata( $user_id ) : null;
        $profile = mvm_e2_organization_profile( $user instanceof WP_User ? $user : null );
    }

    // Draft-only role simulator for visual QA. It never changes a WordPress user or role.
    if ( $admin && mvm_hubs_v3_is_draft_preview() && isset( $_GET['mvm_org_preview'] ) && function_exists( 'mvm_e2_organization_role_profiles' ) ) {
        $preview_key = sanitize_key( wp_unslash( $_GET['mvm_org_preview'] ) );
        $preview_map = array(
            'vereniging'  => 'mvm_vereniging',
            'club'        => 'mvm_club',
            'organisator' => 'mvm_organisator',
            'ondernemer'  => 'mvm_ondernemer',
            'bedrijf'     => 'mvm_bedrijf',
            'winkelier'   => 'mvm_winkelier',
        );
        $profiles = mvm_e2_organization_role_profiles();
        $role_key = $preview_map[ $preview_key ] ?? '';
        if ( $role_key && isset( $profiles[ $role_key ] ) ) {
            $profile = $profiles[ $role_key ];
        }
    }

    if ( $admin && empty( $profile ) ) {
        $profile = array(
            'label'     => 'Organisatie',
            'hub_title' => 'Organisatiehub',
            'pages'     => true,
            'promotion' => true,
            'events'    => true,
            'vacancies' => true,
        );
    }

    return array(
        'account_label' => (string) ( $profile['label'] ?? 'Organisatie' ),
        'hub_title'     => (string) ( $profile['hub_title'] ?? 'Organisatiehub' ),
        'company'       => ! empty( $profile['pages'] ),
        'pages'         => ! empty( $profile['pages'] ),
        'promotion'     => ! empty( $profile['promotion'] ),
        'offers'        => ! empty( $profile['promotion'] ),
        'vacancies'     => ! empty( $profile['vacancies'] ),
        'ads'           => ! empty( $profile['promotion'] ),
        'events'        => ! empty( $profile['events'] ),
    );
}

function mvm_hubs_v3_business_content(): string {
    $tasks = mvm_hubs_v3_business_tasks();

    if ( $tasks['promotion'] ) {
        $html  = '<section class="mvmh3-intro"><div><span class="mvmh3-eyebrow">' . esc_html( $tasks['hub_title'] ) . '</span><h1>Beheer je organisatie en lokale zichtbaarheid vanuit één plek.</h1><p>Maak of beheer je MvM-pagina, dien een aanbieding of promo in en ga direct naar de onderdelen die bij jouw accounttype horen.</p></div><a class="mvmh3-primary" href="' . esc_url( home_url( '/ondernemers/#advertentie-maken' ) ) . '">Aanbieding / promo indienen</a></section>';
    } else {
        $html  = '<section class="mvmh3-intro"><div><span class="mvmh3-eyebrow">' . esc_html( $tasks['hub_title'] ) . '</span><h1>Alles voor jouw evenementen.</h1><p>Deze event-only accountrol krijgt geen commerciële of paginarechten buiten de bestaande evenemententaken.</p></div><a class="mvmh3-primary" href="' . esc_url( home_url( '/ondernemers-hub/?deel=evenement' ) ) . '">Evenement aanmelden</a></section>';
    }
    if ( function_exists( 'mvm_hubs_v3_business_scope_html' ) ) {
        $html .= mvm_hubs_v3_business_scope_html();
    }
    $html .= '<div class="mvmh3-grid mvmh3-grid--compact">';
    if ( $tasks['company'] ) {
        $html .= mvm_hubs_v3_card( 'briefcase', 'Organisatieprofiel', 'Houd naam, omschrijving, contactgegevens en logo op één plek actueel.', home_url( '/ondernemers-hub/?deel=profiel' ), 'Profiel beheren', 'Basisgegevens' );
    }
    if ( $tasks['pages'] ) {
        $html .= mvm_hubs_v3_card( 'group', 'Mijn MvM-pagina', 'Maak of beheer de publieke communitypagina van je vereniging, club, organisatie, bedrijf of winkel.', home_url( '/paginas/' ), 'Pagina maken of beheren', 'Alleen jouw eigen pagina&#8217;s' );
    }
    if ( $tasks['promotion'] ) {
        $html .= mvm_hubs_v3_card( 'megaphone', 'Aanbieding / promo', 'Dien een lokale actie of promotie in. Nieuwe inhoud en wijzigingen gaan eerst naar MvM voor controle.', home_url( '/ondernemers/#advertentie-maken' ), 'Aanbieding / promo indienen', 'Review vóór publicatie' );
    }
    if ( $tasks['vacancies'] ) {
        $html .= mvm_hubs_v3_card( 'briefcase', 'Vacatures', 'Plaats en beheer vacatures voor je onderneming, bedrijf of winkel.', home_url( '/ondernemers-hub/?deel=vacature' ), 'Vacature indienen', 'Review vóór publicatie' );
    }
    if ( $tasks['events'] ) {
        $html .= mvm_hubs_v3_card( 'calendar', 'Evenementen', 'Meld een activiteit aan of beheer evenementen die bij jouw organisatie horen.', home_url( '/ondernemers-hub/?deel=evenement' ), 'Evenementen beheren' );
    }
    $html .= '</div>';

    if ( function_exists( 'mvm_hubs_v3_business_onboarding_html' ) ) {
        $html .= mvm_hubs_v3_business_onboarding_html();
    }
    if ( function_exists( 'mvm_hubs_v3_business_review_status_html' ) ) {
        $html .= mvm_hubs_v3_business_review_status_html();
    }

    if ( $tasks['promotion'] ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Snel naar</span><h2>Veelgebruikte acties</h2></div><p>Je accounttype bepaalt server-side welke acties beschikbaar zijn.</p></div><div class="mvmh3-action-row">';
        if ( $tasks['company'] ) {
            $html .= '<a href="' . esc_url( home_url( '/ondernemers-hub/?deel=profiel' ) ) . '"><strong>Organisatieprofiel</strong><span>Basisgegevens bijwerken</span></a>';
        }
        if ( $tasks['pages'] ) {
            $html .= '<a href="' . esc_url( home_url( '/paginas/' ) ) . '"><strong>MvM-pagina</strong><span>Maken of beheren</span></a>';
        }
        $html .= '<a href="' . esc_url( home_url( '/ondernemers/#advertentie-maken' ) ) . '"><strong>Aanbieding / promo</strong><span>Indienen of bijwerken</span></a>';
        if ( $tasks['vacancies'] ) {
            $html .= '<a href="' . esc_url( home_url( '/ondernemers-hub/?deel=vacature' ) ) . '"><strong>Vacature</strong><span>Indienen of beheren</span></a>';
        }
        if ( $tasks['events'] ) {
            $html .= '<a href="' . esc_url( home_url( '/ondernemers-hub/?deel=evenement' ) ) . '"><strong>Evenement</strong><span>Aanmelden of beheren</span></a>';
        }
        $html .= '</div><div class="mvmh3-callout"><strong>Veilige publicatieregel</strong><span>Aanbiedingen, promo&#8217;s, vacatures en evenementen worden niet rechtstreeks door een organisatieaccount gepubliceerd. Nieuwe inhoud en inhoudelijke wijzigingen gaan eerst naar controle door MvM.</span></div></section>';
    }

    $deel = isset( $_GET['deel'] ) ? sanitize_key( wp_unslash( $_GET['deel'] ) ) : '';
    if ( 'profiel' === $deel && $tasks['company'] && function_exists( 'mvm_hubs_v3_organization_profile_html' ) ) {
        $html .= mvm_hubs_v3_organization_profile_html();
    } elseif ( 'vacature' === $deel && $tasks['vacancies'] ) {
        $html .= mvm_hubs_v3_vacancy_manager_html();
    } elseif ( 'evenement' === $deel && $tasks['events'] && function_exists( 'mvm_hubs_v3_event_manager_html' ) ) {
        $html .= mvm_hubs_v3_event_manager_html();
    }
    return $html;
}

function mvm_hubs_v3_can_view_team_planning( int $user_id = 0 ): bool {
    $user_id = $user_id ?: get_current_user_id();
    if ( class_exists( 'MvM_Hub4_Hub_Security' ) ) {
        return MvM_Hub4_Hub_Security::can_view_team_planning( $user_id );
    }
    if ( $user_id && ! mvm_hubs_v3_is_draft_preview() && user_can( $user_id, 'mvm_hub3_team_planning_access' ) ) {
        return true;
    }
    $capabilities_live = ! mvm_hubs_v3_is_draft_preview() && get_option( 'mvm_hubs_v3_caps_schema' ) === MVM_HUBS_V3_CAPS_SCHEMA;
    if ( $capabilities_live ) {
        return false;
    }
    return mvm_hubs_v3_has_any_role( array( 'administrator', 'mvm_sysop', 'mvm_teamleider' ), $user_id );
}

function mvm_hubs_v3_editorial_posts_table( bool $team = false, array $statuses = array( 'draft', 'pending', 'future', 'publish' ) ): string {
    $statuses = array_values( array_intersect( $statuses, array( 'draft', 'pending', 'future', 'publish' ) ) );
    if ( ! $statuses ) {
        $statuses = array( 'draft', 'pending', 'future', 'publish' );
    }
    $args = array(
        'post_type'      => 'post',
        'post_status'    => $statuses,
        'posts_per_page' => 12,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    );
    if ( ! $team ) {
        $args['author'] = get_current_user_id();
    }
    $posts = get_posts( $args );
    if ( ! $posts ) {
        return '<div class="mvmh3-empty"><strong>Nog geen werk in deze lijst.</strong><span>Nieuwe concepten en geplande artikelen verschijnen hier automatisch zodra ze bij jouw account horen.</span></div>';
    }

    $labels = array( 'draft' => 'Concept', 'pending' => 'Te beoordelen', 'future' => 'Gepland', 'publish' => 'Gepubliceerd' );
    $html = '<table class="mvmh3-table"><thead><tr><th>Artikel</th><th>Status</th><th>Gewijzigd</th>' . ( $team ? '<th>Auteur</th>' : '' ) . '</tr></thead><tbody>';
    foreach ( $posts as $post ) {
        $post_id = (int) $post->ID;
        if ( ! current_user_can( 'read_post', $post_id ) ) {
            continue;
        }
        $status = get_post_status( $post );
        $author = get_the_author_meta( 'display_name', (int) $post->post_author );
        $url = '';
        if ( current_user_can( 'edit_post', $post_id ) ) {
            $url = (string) get_edit_post_link( $post_id, '' );
        } elseif ( 'publish' === $status ) {
            $url = (string) get_permalink( $post_id );
        }
        $title = esc_html( get_the_title( $post_id ) ?: '(Zonder titel)' );
        $title_html = $url ? '<a href="' . esc_url( $url ) . '">' . $title . '</a>' : '<span>' . $title . '</span>';
        $html .= '<tr><td>' . $title_html . '</td><td>' . esc_html( $labels[ $status ] ?? $status ) . '</td><td>' . esc_html( get_the_modified_date( 'j M · H:i', $post_id ) ) . '</td>' . ( $team ? '<td>' . esc_html( $author ) . '</td>' : '' ) . '</tr>';
    }
    return $html . '</tbody></table>';
}

function mvm_hubs_v3_can_manage_vacancies( int $user_id = 0 ): bool {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id || ! mvm_hubs_v3_can_access( 'editorial', $user_id ) ) {
        return false;
    }
    if ( user_can( $user_id, 'manage_options' ) ) {
        return true;
    }
    return user_can( $user_id, 'mvm_hub3_review_access' ) && user_can( $user_id, 'mvm_vacatures_manage_all' );
}

function mvm_hubs_v3_vacancy_review_snapshot(): array {
    $posts = get_posts(
        array(
            'post_type'      => 'mvm_vacature',
            'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
            'posts_per_page' => 100,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        )
    );
    $metrics = array( 'pending' => 0, 'published' => 0, 'draft' => 0 );
    $labels  = array( 'pending' => 'Ter beoordeling', 'publish' => 'Gepubliceerd', 'draft' => 'Concept', 'future' => 'Ingepland', 'private' => 'Privé' );
    $html = '';
    foreach ( $posts as $post ) {
        $status = (string) get_post_status( $post );
        if ( 'pending' === $status ) {
            ++$metrics['pending'];
        } elseif ( 'publish' === $status ) {
            ++$metrics['published'];
        } elseif ( 'draft' === $status ) {
            ++$metrics['draft'];
        }
        $company  = (string) get_post_meta( $post->ID, '_mvm_vacature_company', true );
        $location = (string) get_post_meta( $post->ID, '_mvm_vacature_location', true );
        $html .= '<article class="mvmh3-vacancy-item"><div><strong>' . esc_html( get_the_title( $post ) ?: 'Vacature' ) . '</strong><span>' . esc_html( implode( ' · ', array_filter( array( $company ?: 'Organisatie', $location ?: 'Mierlo', $labels[ $status ] ?? $status ) ) ) ) . '</span></div>';
        if ( 'publish' === $status ) {
            $html .= '<div class="mvmh3-vacancy-item__actions"><a class="mvmh3-vacancy-link" href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener noreferrer">Bekijken</a></div>';
        }
        $html .= '</article>';
    }
    if ( '' === $html ) {
        $html = '<div class="mvmh3-empty"><strong>Geen vacatures gevonden.</strong><span>Nieuwe organisatievacatures verschijnen hier zodra ze zijn ingediend.</span></div>';
    }
    return array( 'count' => count( $posts ), 'metrics' => $metrics, 'html' => $html );
}

function mvm_hubs_v3_vacancy_review_html(): string {
    if ( ! mvm_hubs_v3_can_manage_vacancies() ) {
        return '';
    }
    $preview = mvm_hubs_v3_is_draft_preview();
    $html = '<section class="mvmh3-section" data-mvmh3-vacancy-review data-mvmh3-preview="' . ( $preview ? '1' : '0' ) . '">';
    $html .= '<div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Organisatie-inzendingen</span><h2>Vacatures beoordelen</h2></div><p>Controleer lokale vacatures voordat ze openbaar worden. Alleen Editor, Teamleider, SysOp en Administrator met de juiste vacaturecapability kunnen deze lijst gebruiken.</p></div>';
    if ( $preview ) {
        $html .= '<div class="mvmh3-callout"><strong>Previewmodus</strong><span>Vacaturebeoordeling is hier alleen-lezen. Publiceren, terugzetten en verwijderen zijn uitgeschakeld.</span></div>';
    }
    $snapshot = $preview ? mvm_hubs_v3_vacancy_review_snapshot() : array( 'count' => 0, 'metrics' => array( 'pending' => '–', 'published' => '–', 'draft' => '–' ), 'html' => '' );
    $metrics  = (array) $snapshot['metrics'];
    $html .= '<div class="mvmh3-grid mvmh3-grid--compact" data-mvmh3-vacancy-review-metrics><article class="mvmh3-card"><strong>Ter beoordeling</strong><span data-mvmh3-vacancy-metric="pending">' . esc_html( (string) $metrics['pending'] ) . '</span></article><article class="mvmh3-card"><strong>Gepubliceerd</strong><span data-mvmh3-vacancy-metric="published">' . esc_html( (string) $metrics['published'] ) . '</span></article><article class="mvmh3-card"><strong>Concept</strong><span data-mvmh3-vacancy-metric="draft">' . esc_html( (string) $metrics['draft'] ) . '</span></article></div>';
    $html .= '<p class="mvmh3-callout" data-mvmh3-vacancy-review-status role="status" aria-live="polite"><span>' . esc_html( $preview ? ( (int) $snapshot['count'] . ' vacatures in read-only preview.' ) : 'Vacatures laden…' ) . '</span></p>';
    $html .= '<div class="mvmh3-list" data-mvmh3-vacancy-review-list>' . ( $preview ? (string) $snapshot['html'] : '' ) . '</div>';
    $html .= '<form class="mvmh3-form" data-mvmh3-vacancy-review-form hidden><fieldset class="mvmh3-preview-safe"' . ( $preview ? ' disabled aria-disabled="true"' : '' ) . '><input type="hidden" name="id" value=""><div class="mvmh3-form__grid">';
    $html .= '<label><span>Functietitel</span><input type="text" name="title" maxlength="180" required></label><label><span>Bedrijf / organisatie</span><input type="text" name="company" maxlength="180" required></label><label><span>Locatie</span><input type="text" name="location" maxlength="180"></label><label><span>Dienstverband</span><input type="text" name="employment" maxlength="80"></label><label><span>Uren</span><input type="text" name="hours" maxlength="80"></label><label><span>Sluitingsdatum</span><input type="date" name="closing_date"></label><label><span>Salaris vanaf (€)</span><input type="number" name="salary_min" min="0" step="0.01"></label><label><span>Salaris tot (€)</span><input type="number" name="salary_max" min="0" step="0.01"></label><label><span>Salarisperiode</span><select name="salary_unit"><option value="">Geen salarisindicatie</option><option value="HOUR">Per uur</option><option value="MONTH">Per maand</option><option value="YEAR">Per jaar</option></select></label><label><span>Contactpersoon</span><input type="text" name="contact_name" maxlength="160"></label><label><span>Contact e-mail</span><input type="email" name="contact_email"></label><label class="mvmh3-form__wide"><span>Sollicitatie-URL</span><input type="url" name="application_url"></label><label class="mvmh3-form__wide"><span>Afbeelding-URL</span><input type="url" name="image_url"></label><label class="mvmh3-form__wide"><span>Korte samenvatting</span><textarea name="excerpt" maxlength="500" rows="3"></textarea></label><label class="mvmh3-form__wide"><span>Vacaturetekst</span><textarea name="content" maxlength="50000" rows="8" required></textarea></label><label><span>Besluit / status</span><select name="post_status"><option value="pending">Ter beoordeling houden</option><option value="draft">Terug naar concept</option><option value="publish">Publiceren</option></select></label>';
    $html .= '</div><div class="mvmh3-form__footer"><p data-mvmh3-vacancy-review-form-status role="status" aria-live="polite">Controleer inhoud, contactgegevens en status voordat je opslaat.</p><button class="mvmh3-primary" type="submit">Besluit opslaan</button><button class="mvmh3-secondary" type="button" data-mvmh3-vacancy-review-cancel>Annuleren</button></div></fieldset></form></section>';
    return $html;
}

function mvm_hubs_v3_can_view_newsradar(): bool {
    if ( ! is_user_logged_in() || ! mvm_hubs_v3_can_access( 'editorial' ) ) {
        return false;
    }
    return current_user_can( 'manage_options' ) || current_user_can( 'mvm_hub4_news_view' );
}

function mvm_hubs_v3_can_review_newsradar(): bool {
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }
    return mvm_hubs_v3_can_review() && current_user_can( 'mvm_hub4_news_review' );
}

function mvm_hubs_v3_newsradar_audit_gate( string $id ): bool {
    if ( ! post_type_exists( 'mvm_hub_audit_v3' ) ) {
        return false;
    }
    $audit_id = wp_insert_post(
        array(
            'post_type'    => 'mvm_hub_audit_v3',
            'post_status'  => 'private',
            'post_title'   => 'newsradar_review_authorized',
            'post_content' => wp_json_encode(
                array(
                    'hub'         => 'editorial',
                    'object_type' => 'legacy_news_radar_item',
                    'object_key'  => sanitize_key( $id ),
                    'result'      => 'review',
                ),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'post_author'  => get_current_user_id(),
        ),
        true
    );
    return ! is_wp_error( $audit_id ) && (int) $audit_id > 0;
}

function mvm_hubs_v3_newsradar_review(): void {
    if ( ! is_user_logged_in() || ! mvm_hubs_v3_can_review_newsradar() ) {
        wp_die( esc_html__( 'Geen toegang tot deze Nieuwsradar-actie.', 'mvm' ), '', array( 'response' => 403 ) );
    }
    if ( mvm_hubs_v3_is_draft_preview() ) {
        wp_die( esc_html__( 'Nieuwsradar is in de preview alleen-lezen.', 'mvm' ), '', array( 'response' => 403 ) );
    }
    $id = isset( $_POST['radar_id'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['radar_id'] ) ) ) : '';
    if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $id ) ) {
        wp_die( esc_html__( 'Ongeldig Nieuwsradar-item.', 'mvm' ), '', array( 'response' => 400 ) );
    }
    check_admin_referer( 'mvm_hubs_v3_newsradar_review_' . $id, 'mvm_hubs_v3_nonce' );
    if ( ! class_exists( 'MvM_Hub4_Legacy_Newsradar_Writes' ) ) {
        wp_die( esc_html__( 'De beveiligde Nieuwsradar-service is niet beschikbaar.', 'mvm' ), '', array( 'response' => 503 ) );
    }
    if ( ! mvm_hubs_v3_newsradar_audit_gate( $id ) ) {
        wp_die( esc_html__( 'De beveiligde auditregistratie is mislukt. Het item is niet gewijzigd.', 'mvm' ), '', array( 'response' => 503 ) );
    }
    $result = MvM_Hub4_Legacy_Newsradar_Writes::mark_reviewed( $id );
    $args   = array( 'deel' => 'nieuwsradar' );
    if ( is_wp_error( $result ) ) {
        $args['radar_error'] = sanitize_key( $result->get_error_code() );
    } else {
        $args['radar'] = 'reviewed';
        mvm_hubs_v3_audit( 'newsradar_review_completed', array( 'hub' => 'editorial', 'object_type' => 'legacy_news_radar_item', 'object_id' => 0, 'result' => 'reviewed' ) );
    }
    wp_safe_redirect( add_query_arg( $args, home_url( '/redactie-hub/' ) ) );
    exit;
}
add_action( 'admin_post_mvm_hubs_v3_newsradar_review', 'mvm_hubs_v3_newsradar_review' );

function mvm_hubs_v3_newsradar_html(): string {
    if ( ! mvm_hubs_v3_can_view_newsradar() ) {
        return '';
    }
    $preview = mvm_hubs_v3_is_draft_preview();
    if ( ! class_exists( 'MvM_Hub4_Legacy_Newsradar' ) ) {
        return '<section class="mvmh3-section"><div class="mvmh3-empty"><strong>Nieuwsradar-service niet beschikbaar.</strong><span>De bestaande servicedatalaag is niet geladen; er wordt geen alternatieve databron aangemaakt.</span></div></section>';
    }

    $snapshot = (array) MvM_Hub4_Legacy_Newsradar::snapshot();
    $groups   = (array) MvM_Hub4_Legacy_Newsradar::grouped_results();
    if ( class_exists( 'MvM_Hub4_AI_Agenda_Radar' ) && is_callable( array( 'MvM_Hub4_AI_Agenda_Radar', 'sort_groups' ) ) ) {
        $groups = (array) MvM_Hub4_AI_Agenda_Radar::sort_groups( $groups );
    }
    $show_all = isset( $_GET['radar_scope'] ) && 'all' === sanitize_key( wp_unslash( $_GET['radar_scope'] ) );
    $hidden_nonlocal = 0;
    foreach ( $groups as $candidate_group ) {
        $candidate = is_array( $candidate_group ) && isset( $candidate_group['primary'] ) && is_array( $candidate_group['primary'] ) ? $candidate_group['primary'] : array();
        $candidate_status = sanitize_key( (string) ( $candidate['status'] ?? '' ) );
        if ( in_array( $candidate_status, array( 'niet_mierlo', 'dubbel' ), true ) ) {
            $hidden_nonlocal++;
        }
    }
    if ( ! $show_all ) {
        $groups = array_values(
            array_filter(
                $groups,
                static function ( $candidate_group ): bool {
                    $candidate = is_array( $candidate_group ) && isset( $candidate_group['primary'] ) && is_array( $candidate_group['primary'] ) ? $candidate_group['primary'] : array();
                    return ! in_array( sanitize_key( (string) ( $candidate['status'] ?? '' ) ), array( 'niet_mierlo', 'dubbel' ), true );
                }
            )
        );
    }
    $groups = array_slice( $groups, 0, 50 );
    $can_review = mvm_hubs_v3_can_review_newsradar();

    $html  = '<section class="mvmh3-section mvmh3-newsradar" data-mvmh3-newsradar data-mvmh3-preview="' . ( $preview ? '1' : '0' ) . '">';
    $html .= '<div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Mierlo-only bronmonitor</span><h2>Nieuwsradar</h2></div><p>De werkbak toont standaard alleen signalen die Mierlo raken of nog lokale verificatie nodig hebben. Duidelijke buiten-Mierlo-ruis en dubbelen blijven alleen voor audit beschikbaar.</p></div>';
    $scope_url = add_query_arg( array( 'deel' => 'nieuwsradar', 'radar_scope' => $show_all ? 'local' : 'all' ), home_url( '/redactie-hub/' ) );
    $html .= '<div class="mvmh3-source-mode"><div><strong>Mierlo-filter ' . ( $show_all ? 'tijdelijk open' : 'actief' ) . '</strong><span>' . absint( $hidden_nonlocal ) . ' duidelijke buiten-Mierlo/dubbele signalen ' . ( $show_all ? 'worden nu voor controle meegetoond.' : 'zijn uit de dagelijkse werkbak verborgen.' ) . '</span></div><a class="mvmh3-secondary" href="' . esc_url( $scope_url ) . '">' . esc_html( $show_all ? 'Terug naar Mierlo-only' : 'Toon weggefilterd' ) . '</a></div>';
    if ( $preview ) {
        $html .= '<div class="mvmh3-callout"><strong>Previewmodus</strong><span>De lijst is volledig leesbaar, maar reviewacties zijn uitgeschakeld en kunnen geen productiedata wijzigen.</span></div>';
    }
    if ( isset( $_GET['radar'] ) && 'reviewed' === sanitize_key( wp_unslash( $_GET['radar'] ) ) ) {
        $html .= '<div class="mvmh3-notice mvmh3-notice--success"><strong>Gemarkeerd als beoordeeld.</strong><span>De bestaande Nieuwsradar is bijgewerkt zonder de crawlerdata te kopiëren.</span></div>';
    }
    if ( isset( $_GET['radar_error'] ) ) {
        $html .= '<div class="mvmh3-notice"><strong>Reviewactie niet uitgevoerd.</strong><span>De Nieuwsradar veranderde, was bezig of kon niet veilig worden bijgewerkt. Vernieuw de lijst en probeer opnieuw.</span></div>';
    }

    $state = ! empty( $snapshot['running'] ) ? 'Crawler actief' : ( ! empty( $snapshot['enabled'] ) ? 'Monitoring actief' : 'Monitoring gepauzeerd' );
    $html .= '<div class="mvmh3-grid mvmh3-grid--compact mvmh3-newsradar__metrics">';
    $html .= '<article class="mvmh3-card"><strong>Bronnen</strong><span>' . absint( $snapshot['sources'] ?? 0 ) . '</span></article>';
    $html .= '<article class="mvmh3-card"><strong>Signalen</strong><span>' . absint( $snapshot['results'] ?? 0 ) . '</span></article>';
    $html .= '<article class="mvmh3-card"><strong>Detailwachtrij</strong><span>' . absint( $snapshot['queue_jobs'] ?? 0 ) . '</span></article>';
    $html .= '<article class="mvmh3-card"><strong>Status</strong><span>' . esc_html( $state ) . '</span></article></div>';

    if ( ! $groups ) {
        $html .= '<div class="mvmh3-empty"><strong>Geen signalen gevonden.</strong><span>De Nieuwsradar heeft momenteel geen items om te tonen.</span></div></section>';
        return $html;
    }

    $html .= '<div class="mvmh3-newsradar__list">';
    foreach ( $groups as $group ) {
        if ( ! is_array( $group ) || empty( $group['primary'] ) || ! is_array( $group['primary'] ) ) {
            continue;
        }
        $item       = $group['primary'];
        $id         = isset( $item['id'] ) ? strtolower( sanitize_text_field( (string) $item['id'] ) ) : '';
        $title      = sanitize_text_field( (string) ( $item['title'] ?? 'Ongetiteld signaal' ) );
        $source     = sanitize_text_field( (string) ( $item['source'] ?? '' ) );
        $url        = esc_url( (string) ( $item['url'] ?? '' ) );
        $summary    = sanitize_textarea_field( (string) ( $item['summary'] ?? ( $item['excerpt'] ?? '' ) ) );
        $date       = sanitize_text_field( (string) ( $item['published'] ?? ( $item['found_at'] ?? '' ) ) );
        $category   = sanitize_text_field( (string) ( $item['category'] ?? '' ) );
        $reviewed   = ! empty( $item['reviewed'] );
        $dismissed  = ! empty( $item['dismissed'] );
        $updates    = isset( $group['notification_count'] ) ? max( 1, absint( $group['notification_count'] ) ) : 1;
        $confidence = isset( $item['confidence'] ) && is_scalar( $item['confidence'] ) ? sanitize_text_field( (string) $item['confidence'] ) : '';
        $local_score = isset( $item['local_score'] ) ? max( 0, min( 100, absint( $item['local_score'] ) ) ) : 0;
        $provider    = sanitize_key( (string) ( $item['ai_provider'] ?? '' ) );
        $item_status = sanitize_key( (string) ( $item['status'] ?? '' ) );
        $detection   = sanitize_text_field( (string) ( $item['detection_method'] ?? '' ) );

        $html .= '<article class="mvmh3-newsradar__item" data-radar-status="' . esc_attr( $item_status ) . '"><div class="mvmh3-newsradar__body"><div class="mvmh3-newsradar__meta">';
        if ( $source ) { $html .= '<span>' . esc_html( $source ) . '</span>'; }
        if ( $category ) { $html .= '<span>' . esc_html( $category ) . '</span>'; }
        if ( $date ) { $html .= '<span>' . esc_html( $date ) . '</span>'; }
        if ( $updates > 1 ) { $html .= '<span>' . absint( $updates ) . ' meldingen</span>'; }
        if ( $local_score > 0 ) { $html .= '<span>Mierlo-score: ' . absint( $local_score ) . '</span>'; }
        if ( $confidence ) { $html .= '<span>AI-confidence: ' . esc_html( $confidence ) . '</span>'; }
        if ( $provider ) { $html .= '<span>AI: ' . esc_html( 'google' === $provider ? 'Gemini' : ( 'openai' === $provider ? 'OpenAI' : ucfirst( $provider ) ) ) . '</span>'; }
        if ( $item_status ) { $html .= '<span>Status: ' . esc_html( str_replace( '_', ' ', $item_status ) ) . '</span>'; }
        if ( $detection ) { $html .= '<span>Detectie: ' . esc_html( $detection ) . '</span>'; }
        $html .= '</div><h3>' . esc_html( $title ) . '</h3>';
        if ( $summary ) {
            $html .= '<p>' . esc_html( function_exists( 'mb_substr' ) ? mb_substr( $summary, 0, 500, 'UTF-8' ) : substr( $summary, 0, 500 ) ) . '</p>';
        }
        $html .= '</div><div class="mvmh3-newsradar__actions">';
        if ( $reviewed ) {
            $html .= '<span class="mvmh3-newsradar__state is-reviewed">Beoordeeld</span>';
        } elseif ( $dismissed ) {
            $html .= '<span class="mvmh3-newsradar__state">Afgehandeld</span>';
        } elseif ( $can_review && ! $preview && 1 === preg_match( '/^[a-f0-9]{64}$/', $id ) ) {
            $html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mvm_hubs_v3_newsradar_review"><input type="hidden" name="radar_id" value="' . esc_attr( $id ) . '">' . wp_nonce_field( 'mvm_hubs_v3_newsradar_review_' . $id, 'mvm_hubs_v3_nonce', true, false ) . '<button class="mvmh3-primary" type="submit">Markeer beoordeeld</button></form>';
        } elseif ( $can_review && $preview ) {
            $html .= '<span class="mvmh3-preview-lock">Preview · review uitgeschakeld</span>';
        }
        if ( $url ) {
            $html .= '<a class="mvmh3-secondary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">Bron openen</a>';
        }
        $html .= '</div></article>';
    }
    $html .= '</div><div class="mvmh3-callout"><strong>Datagrens</strong><span>Deze weergave leest uitsluitend de bestaande allowlisted Nieuwsradar-adapter. Bronnotities, crawlerdiagnostiek en andere interne velden worden niet naar de Redactiehub gekopieerd.</span></div></section>';
    return $html;
}

function mvm_hubs_v3_can_view_sources(): bool {
    if ( ! is_user_logged_in() || ! mvm_hubs_v3_can_access( 'editorial' ) ) {
        return false;
    }
    return current_user_can( 'manage_options' ) || current_user_can( 'mvm_hub4_sources_view' );
}

function mvm_hubs_v3_can_check_sources(): bool {
    return mvm_hubs_v3_can_view_sources() && ( current_user_can( 'manage_options' ) || current_user_can( 'mvm_hub4_sources_check' ) );
}

function mvm_hubs_v3_can_manage_sources(): bool {
    return mvm_hubs_v3_can_view_sources() && ( current_user_can( 'manage_options' ) || current_user_can( 'mvm_hub4_sources_manage' ) );
}

function mvm_hubs_v3_source_sync_legacy( int $source_id ): void {
    if ( ! function_exists( 'mvm_hub_source_admin_legacy_v1' ) || ! class_exists( 'MvM_Hub4_Sources' ) ) {
        return;
    }
    $post  = get_post( $source_id );
    $state = MvM_Hub4_Sources::get_state( $source_id );
    if ( ! $post instanceof WP_Post || 'mvm_bron' !== $post->post_type || ! is_array( $state ) ) {
        return;
    }
    $categories  = MvM_Hub4_Sources::categories();
    $frequencies = MvM_Hub4_Sources::frequencies();
    $category    = MvM_Hub4_Sources::sanitize_category( (string) ( $state['category'] ?? 'overig' ) );
    $frequency   = MvM_Hub4_Sources::sanitize_frequency( (string) ( $state['frequency'] ?? 'weekly' ) );
    $url         = class_exists( 'MvM_Hub4_Source_Repository' ) && method_exists( 'MvM_Hub4_Source_Repository', 'extract_external_url' )
        ? MvM_Hub4_Source_Repository::extract_external_url( (string) $post->post_content )
        : '';
    mvm_hub_source_admin_legacy_v1(
        $source_id,
        array(
            'title'           => html_entity_decode( wp_strip_all_tags( $post->post_title ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
            'url'             => $url,
            'category_label'  => (string) ( $categories[ $category ] ?? $category ),
            'frequency_label' => (string) ( $frequencies[ $frequency ]['label'] ?? $frequency ),
            'status'          => (string) ( $state['status'] ?? 'active' ),
            'monitor'         => ! empty( $state['monitor_enabled'] ),
        ),
        false
    );
}

function mvm_hubs_v3_archive_source( int $source_id ): bool|WP_Error {
    $post = get_post( $source_id );
    if ( ! $post instanceof WP_Post || 'mvm_bron' !== $post->post_type || 'publish' !== $post->post_status ) {
        return new WP_Error( 'mvm_hubs_v3_source_missing', 'Bron niet gevonden of al gearchiveerd.' );
    }
    $state   = class_exists( 'MvM_Hub4_Sources' ) ? MvM_Hub4_Sources::get_state( $source_id ) : null;
    $archive = get_option( 'mvm_hub_source_archive_v1', array() );
    if ( ! is_array( $archive ) ) {
        $archive = array();
    }
    $archive[ $source_id ] = array(
        'archived_at' => gmdate( 'c' ),
        'archived_by' => get_current_user_id(),
        'post'        => array( 'title' => $post->post_title, 'content' => $post->post_content ),
        'state'       => $state,
        'legacy_id'   => absint( get_post_meta( $source_id, '_mvm_newsradar_legacy_source_id', true ) ),
    );
    update_option( 'mvm_hub_source_archive_v1', $archive, false );
    if ( class_exists( 'MvM_Hub4_Sources' ) ) {
        $saved = MvM_Hub4_Sources::save_state( $source_id, array( 'monitor_enabled' => false, 'status' => 'stopped' ) );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }
    }
    mvm_hubs_v3_source_sync_legacy( $source_id );
    update_post_meta( $source_id, '_mvm_newsradar_active', 0 );
    update_post_meta( $source_id, '_mvm_newsradar_excluded', 1 );
    update_post_meta( $source_id, '_mvm_hub_archived_at', gmdate( 'c' ) );
    $updated = wp_update_post( array( 'ID' => $source_id, 'post_status' => 'draft' ), true );
    if ( is_wp_error( $updated ) ) {
        return $updated;
    }
    return true;
}

function mvm_hubs_v3_source_action(): void {
    if ( ! is_user_logged_in() || ! mvm_hubs_v3_can_view_sources() ) {
        wp_die( esc_html__( 'Geen toegang tot deze bronactie.', 'mvm' ), '', array( 'response' => 403 ) );
    }
    if ( mvm_hubs_v3_is_draft_preview() ) {
        wp_die( esc_html__( 'Bronacties zijn in de preview alleen-lezen.', 'mvm' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'mvm_hubs_v3_sources_action', 'mvm_hubs_v3_sources_nonce' );

    $operation = isset( $_POST['source_operation'] ) ? sanitize_key( wp_unslash( $_POST['source_operation'] ) ) : '';
    $ids       = isset( $_POST['source_ids'] ) && is_array( $_POST['source_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['source_ids'] ) ) : array();
    $single    = isset( $_POST['single_action'] ) ? sanitize_text_field( wp_unslash( $_POST['single_action'] ) ) : '';
    if ( $single && preg_match( '/^(check|checked|pause|resume|stop|archive):(\d+)$/', $single, $match ) ) {
        $operation = sanitize_key( $match[1] );
        $ids       = array( absint( $match[2] ) );
    }
    $ids = array_slice( array_values( array_unique( array_filter( $ids ) ) ), 0, 50 );
    if ( ! $ids || ! in_array( $operation, array( 'check', 'checked', 'pause', 'resume', 'stop', 'archive' ), true ) ) {
        wp_safe_redirect( add_query_arg( array( 'deel' => 'bronnen', 'bron_action' => 'invalid' ), home_url( '/redactie-hub/' ) ) );
        exit;
    }

    $needs_check  = in_array( $operation, array( 'check', 'checked' ), true );
    $needs_manage = in_array( $operation, array( 'pause', 'resume', 'stop', 'archive' ), true );
    if ( ( $needs_check && ! mvm_hubs_v3_can_check_sources() ) || ( $needs_manage && ! mvm_hubs_v3_can_manage_sources() ) ) {
        wp_die( esc_html__( 'Je hebt niet de juiste bronrechten voor deze actie.', 'mvm' ), '', array( 'response' => 403 ) );
    }

    $done = 0;
    $failed = 0;
    $backend_pending = false;
    if ( 'check' === $operation ) {
        if ( class_exists( 'MvM_Hub4_Newsradar_Schedule' ) && method_exists( 'MvM_Hub4_Newsradar_Schedule', 'queue_manual_sources' ) ) {
            $queued = MvM_Hub4_Newsradar_Schedule::queue_manual_sources( $ids );
            $done   = count( (array) ( $queued['queued'] ?? array() ) );
            $failed = count( (array) ( $queued['failed'] ?? array() ) );
        } else {
            $backend_pending = true;
            $failed = count( $ids );
        }
    } else {
        foreach ( $ids as $source_id ) {
            $post = get_post( $source_id );
            if ( ! $post instanceof WP_Post || 'mvm_bron' !== $post->post_type ) {
                $failed++;
                continue;
            }
            $result = true;
            if ( 'checked' === $operation && class_exists( 'MvM_Hub4_Sources' ) ) {
                $result = MvM_Hub4_Sources::mark_checked( $source_id, get_current_user_id() );
            } elseif ( 'pause' === $operation && class_exists( 'MvM_Hub4_Sources' ) ) {
                $result = MvM_Hub4_Sources::save_state( $source_id, array( 'monitor_enabled' => false, 'status' => 'paused' ) );
            } elseif ( 'resume' === $operation && class_exists( 'MvM_Hub4_Sources' ) ) {
                $result = MvM_Hub4_Sources::save_state( $source_id, array( 'monitor_enabled' => true, 'status' => 'active' ) );
            } elseif ( 'stop' === $operation && class_exists( 'MvM_Hub4_Sources' ) ) {
                $result = MvM_Hub4_Sources::save_state( $source_id, array( 'monitor_enabled' => false, 'status' => 'stopped' ) );
            } elseif ( 'archive' === $operation ) {
                $result = mvm_hubs_v3_archive_source( $source_id );
            } else {
                $result = new WP_Error( 'mvm_hubs_v3_source_service_missing', 'Bronservice niet beschikbaar.' );
            }
            if ( is_wp_error( $result ) || false === $result ) {
                $failed++;
                continue;
            }
            if ( in_array( $operation, array( 'pause', 'resume', 'stop' ), true ) ) {
                mvm_hubs_v3_source_sync_legacy( $source_id );
            }
            $done++;
        }
    }

    mvm_hubs_v3_audit(
        'source_bulk_action',
        array(
            'hub'         => 'editorial',
            'object_type' => 'source_bulk',
            'object_id'   => 0,
            'result'      => $failed ? 'partial' : 'success',
        )
    );
    $action_state = $backend_pending ? 'backend_pending' : ( $failed ? 'partial' : 'success' );
    wp_safe_redirect(
        add_query_arg(
            array(
                'deel'        => 'bronnen',
                'bron_action' => $action_state,
                'bron_op'     => $operation,
                'bron_done'   => $done,
                'bron_failed' => $failed,
            ),
            home_url( '/redactie-hub/' )
        )
    );
    exit;
}
add_action( 'admin_post_mvm_hubs_v3_source_action', 'mvm_hubs_v3_source_action' );

function mvm_hubs_v3_sources_html(): string {
    if ( ! mvm_hubs_v3_can_view_sources() ) {
        return '';
    }
    if ( ! class_exists( 'MvM_Hub4_Source_Repository' ) || ! class_exists( 'MvM_Hub4_Sources' ) ) {
        return '<section class="mvmh3-section"><div class="mvmh3-empty"><strong>Bronregister niet beschikbaar.</strong><span>De bestaande bronrepository is niet geladen; Hubs v3 maakt geen tweede bronnenregister aan.</span></div></section>';
    }

    $preview        = mvm_hubs_v3_is_draft_preview();
    $can_check      = mvm_hubs_v3_can_check_sources();
    $can_manage     = mvm_hubs_v3_can_manage_sources();
    $crawler_ready  = class_exists( 'MvM_Hub4_Newsradar_Schedule' ) && method_exists( 'MvM_Hub4_Newsradar_Schedule', 'queue_manual_sources' );
    $allowed_due    = array( '', 'overdue', 'today', 'week', 'later', 'unscheduled' );
    $allowed_status = array( 'active', 'paused', 'stopped', 'all' );
    $due            = isset( $_GET['bron_due'] ) ? sanitize_key( wp_unslash( $_GET['bron_due'] ) ) : '';
    $status_filter  = isset( $_GET['bron_status'] ) ? sanitize_key( wp_unslash( $_GET['bron_status'] ) ) : 'active';
    $search         = isset( $_GET['bron_q'] ) ? sanitize_text_field( wp_unslash( $_GET['bron_q'] ) ) : '';
    $category       = isset( $_GET['bron_category'] ) ? sanitize_key( wp_unslash( $_GET['bron_category'] ) ) : '';
    if ( ! in_array( $due, $allowed_due, true ) ) {
        $due = '';
    }
    if ( ! in_array( $status_filter, $allowed_status, true ) ) {
        $status_filter = 'active';
    }
    if ( ! array_key_exists( $category, MvM_Hub4_Sources::categories() ) ) {
        $category = '';
    }

    $query_args = array(
        'per_page' => 50,
        'page'     => 1,
        'due'      => $due,
        'search'   => $search,
        'category' => $category,
        'status'   => 'all' === $status_filter ? '' : $status_filter,
    );
    $result  = MvM_Hub4_Source_Repository::query( $query_args );
    $items   = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
    $total   = absint( $result['total'] ?? count( $items ) );
    $active  = MvM_Hub4_Source_Repository::query( array( 'per_page' => 1, 'status' => 'active' ) );
    $paused  = MvM_Hub4_Source_Repository::query( array( 'per_page' => 1, 'status' => 'paused' ) );
    $stopped = MvM_Hub4_Source_Repository::query( array( 'per_page' => 1, 'status' => 'stopped' ) );
    $overdue = MvM_Hub4_Source_Repository::query( array( 'per_page' => 1, 'due' => 'overdue', 'status' => 'active' ) );
    $today   = MvM_Hub4_Source_Repository::query( array( 'per_page' => 1, 'due' => 'today', 'status' => 'active' ) );
    $unscheduled = MvM_Hub4_Source_Repository::query( array( 'per_page' => 1, 'due' => 'unscheduled', 'status' => 'active' ) );
    $todo_count = absint( $overdue['total'] ?? 0 ) + absint( $today['total'] ?? 0 ) + absint( $unscheduled['total'] ?? 0 );

    $base = home_url( '/redactie-hub/' );
    $metric = static function ( array $args, string $label, int $count, string $small ) use ( $base ): string {
        $url = add_query_arg( array_merge( array( 'deel' => 'bronnen' ), $args ), $base );
        return '<a href="' . esc_url( $url ) . '"><span>' . esc_html( $label ) . '</span><strong>' . absint( $count ) . '</strong><small>' . esc_html( $small ) . '</small></a>';
    };

    $html  = '<section class="mvmh3-section mvmh3-sources" data-mvmh3-sources data-preview="' . ( $preview ? '1' : '0' ) . '"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Mierlo-only bronmonitor</span><h2>Bronnencontrole</h2></div><p>Selecteer één bron of meerdere tegelijk. Handmatig controleren en automatische monitoring gebruiken hetzelfde canonieke bronregister en voeden dezelfde Nieuwsradar.</p></div>';
    $html .= '<div class="mvmh3-source-mode"><div><strong>Automatisch waar het kan</strong><span>De bronplanning bepaalt wanneer een bron weer aan de beurt is. Nieuws buiten Mierlo hoort vóór dure AI-analyse uit de stroom te vallen.</span></div><a class="mvmh3-secondary" href="' . esc_url( add_query_arg( 'deel', 'nieuwsradar', $base ) ) . '">Open Nieuwsradar</a></div>';
    if ( $preview ) {
        $html .= '<div class="mvmh3-callout"><strong>Previewmodus</strong><span>Je kunt selecteren en de nieuwe workflow bekijken, maar geen bronstatus of productiedata wijzigen.</span></div>';
    } elseif ( ! $crawler_ready ) {
        $html .= '<div class="mvmh3-callout"><strong>Crawler-release nog niet gekoppeld</strong><span>Pauzeren, doorgaan, stoppen en handmatig afvinken gebruiken de bestaande bronservice. De knop Nu checken wordt pas actief zodra de nieuwe begrensde crawlerwachtrij uit de Nieuwsradar-release is uitgerold.</span></div>';
    }
    if ( isset( $_GET['bron_action'] ) ) {
        $action = sanitize_key( wp_unslash( $_GET['bron_action'] ) );
        $done   = absint( $_GET['bron_done'] ?? 0 );
        $failed = absint( $_GET['bron_failed'] ?? 0 );
        if ( 'success' === $action ) {
            $html .= '<div class="mvmh3-notice mvmh3-notice--success"><strong>Bronactie uitgevoerd.</strong><span>' . absint( $done ) . ' bron(nen) bijgewerkt.</span></div>';
        } elseif ( 'backend_pending' === $action ) {
            $html .= '<div class="mvmh3-notice"><strong>Controle nog niet gestart.</strong><span>De nieuwe crawlerwachtrij is nog niet actief op productie; er is niets ten onrechte als gecontroleerd gemarkeerd.</span></div>';
        } elseif ( 'partial' === $action ) {
            $html .= '<div class="mvmh3-notice"><strong>Actie gedeeltelijk uitgevoerd.</strong><span>Gelukt: ' . absint( $done ) . ' · overgeslagen/mislukt: ' . absint( $failed ) . '.</span></div>';
        }
    }

    $html .= '<div class="mvmh3-review-summary__grid mvmh3-source-metrics">';
    $html .= $metric( array( 'bron_status' => 'active' ), 'Actieve bronnen', absint( $active['total'] ?? 0 ), 'Automatische pool' );
    $html .= $metric( array( 'bron_status' => 'active', 'bron_due' => 'overdue' ), 'Nog checken', $todo_count, 'Nu / achterstallig' );
    $html .= $metric( array( 'bron_status' => 'paused' ), 'Gepauzeerd', absint( $paused['total'] ?? 0 ), 'Tijdelijk uit' );
    $html .= $metric( array( 'bron_status' => 'stopped' ), 'Gestopt', absint( $stopped['total'] ?? 0 ), 'Niet automatisch' );
    $html .= '</div>';

    $html .= '<form class="mvmh3-source-filters" method="get" action="' . esc_url( $base ) . '"><input type="hidden" name="deel" value="bronnen"><label><span>Zoeken</span><input type="search" name="bron_q" value="' . esc_attr( $search ) . '" placeholder="Bron, organisatie of URL"></label><label><span>Status</span><select name="bron_status"><option value="active" ' . selected( $status_filter, 'active', false ) . '>Actief</option><option value="paused" ' . selected( $status_filter, 'paused', false ) . '>Pauze</option><option value="stopped" ' . selected( $status_filter, 'stopped', false ) . '>Gestopt</option><option value="all" ' . selected( $status_filter, 'all', false ) . '>Alles</option></select></label><label><span>Planning</span><select name="bron_due"><option value="" ' . selected( $due, '', false ) . '>Alle planning</option><option value="overdue" ' . selected( $due, 'overdue', false ) . '>Achterstallig</option><option value="today" ' . selected( $due, 'today', false ) . '>Vandaag</option><option value="week" ' . selected( $due, 'week', false ) . '>Deze week</option><option value="later" ' . selected( $due, 'later', false ) . '>Later</option><option value="unscheduled" ' . selected( $due, 'unscheduled', false ) . '>Niet gepland</option></select></label><label><span>Categorie</span><select name="bron_category"><option value="">Alle categorieën</option>';
    foreach ( MvM_Hub4_Sources::categories() as $key => $label ) {
        $html .= '<option value="' . esc_attr( $key ) . '" ' . selected( $category, $key, false ) . '>' . esc_html( $label ) . '</option>';
    }
    $html .= '</select></label><button class="mvmh3-secondary" type="submit">Filter</button><a class="mvmh3-source-reset" href="' . esc_url( add_query_arg( 'deel', 'bronnen', $base ) ) . '">Reset</a></form>';

    if ( ! $items ) {
        $html .= '<div class="mvmh3-empty"><strong>Geen bronnen in deze selectie.</strong><span>Pas de filters aan of kies een andere bronstatus.</span></div></section>';
        return $html;
    }

    $html .= '<form class="mvmh3-source-bulk" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mvm_hubs_v3_source_action">' . wp_nonce_field( 'mvm_hubs_v3_sources_action', 'mvm_hubs_v3_sources_nonce', true, false );
    $html .= '<div class="mvmh3-source-toolbar"><div class="mvmh3-source-toolbar__select"><label class="mvmh3-source-selectall"><input type="checkbox" data-mvmh3-source-all ' . disabled( $preview, true, false ) . '><span>Selecteer alles in deze lijst</span></label><span class="mvmh3-source-selected" data-mvmh3-source-count>0 geselecteerd</span></div><div class="mvmh3-source-toolbar__actions">';
    if ( $can_check ) {
        $html .= '<button type="submit" name="source_operation" value="check" ' . disabled( $preview || ! $crawler_ready, true, false ) . '>Nu checken</button><button type="submit" name="source_operation" value="checked" ' . disabled( $preview, true, false ) . '>Al gecheckt</button>';
    }
    if ( $can_manage ) {
        $html .= '<button type="submit" name="source_operation" value="pause" ' . disabled( $preview, true, false ) . '>Pauzeren</button><button type="submit" name="source_operation" value="resume" ' . disabled( $preview, true, false ) . '>Doorgaan</button><button type="submit" name="source_operation" value="stop" ' . disabled( $preview, true, false ) . '>Stoppen</button><button class="is-danger" type="submit" name="source_operation" value="archive" ' . disabled( $preview, true, false ) . ' onclick="return confirm(\'Geselecteerde bronnen archiveren? Historie blijft bewaard, maar de bron verdwijnt uit de actieve lijst.\');">Verwijderen</button>';
    }
    $html .= '</div></div><div class="mvmh3-sources__list">';

    foreach ( $items as $item ) {
        if ( ! is_array( $item ) ) {
            continue;
        }
        $source_id  = absint( $item['id'] ?? 0 );
        $title      = sanitize_text_field( (string) ( $item['title'] ?? 'Ongetitelde bron' ) );
        $category_l = sanitize_text_field( (string) ( $item['categoryLabel'] ?? ( $item['category'] ?? '' ) ) );
        $frequency  = sanitize_text_field( (string) ( $item['frequencyLabel'] ?? ( $item['frequency'] ?? '' ) ) );
        $priority   = strtoupper( sanitize_text_field( (string) ( $item['priority'] ?? '' ) ) );
        $policy_reason = sanitize_text_field( (string) ( $item['policyReason'] ?? '' ) );
        $status_key = sanitize_key( (string) ( $item['status'] ?? 'active' ) );
        $status     = sanitize_text_field( (string) ( $item['statusLabel'] ?? $status_key ) );
        $due_state  = sanitize_key( (string) ( $item['dueState'] ?? '' ) );
        $last       = sanitize_text_field( (string) ( $item['lastCheckedUtc'] ?? '' ) );
        $next       = sanitize_text_field( (string) ( $item['nextCheckUtc'] ?? '' ) );
        $enabled    = ! empty( $item['monitorEnabled'] );
        $workflow   = 'stopped' === $status_key ? 'Gestopt' : ( 'paused' === $status_key || ! $enabled ? 'Gepauzeerd' : ( ! $last || in_array( $due_state, array( 'overdue', 'today', 'unscheduled' ), true ) ? 'Nog checken' : 'Al gecheckt' ) );
        $state_class = 'Nog checken' === $workflow ? 'is-overdue' : ( 'Al gecheckt' === $workflow ? 'is-reviewed' : '' );
        $post = get_post( $source_id );
        $url  = $post instanceof WP_Post ? MvM_Hub4_Source_Repository::extract_external_url( (string) $post->post_content ) : '';

        $html .= '<article class="mvmh3-sources__item" data-source-status="' . esc_attr( $status_key ) . '" data-source-priority="' . esc_attr( $priority ) . '"><label class="mvmh3-source-check"><input type="checkbox" name="source_ids[]" value="' . absint( $source_id ) . '" data-mvmh3-source-box ' . disabled( $preview, true, false ) . '><span class="screen-reader-text">Selecteer ' . esc_html( $title ) . '</span></label><div class="mvmh3-source-main"><div class="mvmh3-newsradar__meta">';
        if ( in_array( $priority, array( 'A', 'B', 'C' ), true ) ) {
            $html .= '<span class="mvmh3-source-priority is-' . esc_attr( strtolower( $priority ) ) . '">Prioriteit ' . esc_html( $priority ) . '</span>';
        }
        $html .= '<span>' . esc_html( $category_l ?: 'Overig' ) . '</span><span>' . esc_html( $frequency ?: 'Onbekend' ) . '</span><span>' . esc_html( $status ?: 'Onbekend' ) . '</span></div><h3>' . esc_html( $title ) . '</h3>';
        if ( $policy_reason ) {
            $html .= '<p class="mvmh3-source-policy">' . esc_html( $policy_reason ) . '</p>';
        }
        if ( $url ) {
            $html .= '<a class="mvmh3-source-url" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">Bron openen</a>';
        }
        $html .= '</div><div class="mvmh3-sources__schedule"><span class="mvmh3-newsradar__state ' . esc_attr( $state_class ) . '">' . esc_html( $workflow ) . '</span><small>Laatste: ' . esc_html( $last ?: 'nog niet' ) . '</small><small>Volgende: ' . esc_html( $next ?: 'niet gepland' ) . '</small></div><div class="mvmh3-source-row-actions">';
        if ( $can_check ) {
            $html .= '<button type="submit" name="single_action" value="check:' . absint( $source_id ) . '" ' . disabled( $preview || ! $crawler_ready || 'stopped' === $status_key, true, false ) . '>Nu checken</button><button type="submit" name="single_action" value="checked:' . absint( $source_id ) . '" ' . disabled( $preview, true, false ) . '>Al gecheckt</button>';
        }
        if ( $can_manage ) {
            if ( 'active' === $status_key ) {
                $html .= '<button type="submit" name="single_action" value="pause:' . absint( $source_id ) . '" ' . disabled( $preview, true, false ) . '>Pauzeren</button><button type="submit" name="single_action" value="stop:' . absint( $source_id ) . '" ' . disabled( $preview, true, false ) . '>Stoppen</button>';
            } else {
                $html .= '<button type="submit" name="single_action" value="resume:' . absint( $source_id ) . '" ' . disabled( $preview, true, false ) . '>Doorgaan</button>';
            }
            $html .= '<button class="is-danger" type="submit" name="single_action" value="archive:' . absint( $source_id ) . '" ' . disabled( $preview, true, false ) . ' onclick="return confirm(\'Deze bron archiveren? De historie blijft bewaard.\');">Verwijderen</button>';
        }
        $html .= '</div></article>';
    }
    $html .= '</div></form>';
    if ( $total > count( $items ) ) {
        $html .= '<div class="mvmh3-callout"><strong>' . absint( count( $items ) ) . ' van ' . absint( $total ) . ' getoond</strong><span>Verfijn de filters om gericht kleinere bronsets te selecteren. Bulkacties zijn bewust begrensd tot maximaal 50 bronnen per opdracht.</span></div>';
    }
    $html .= '<div class="mvmh3-source-explainer"><article><strong>Nog checken</strong><span>De bron is nog nooit gecontroleerd, is vandaag aan de beurt of is achterstallig.</span></article><article><strong>Al gecheckt</strong><span>Gebruik dit alleen als een redacteur de bron zelf handmatig heeft bekeken. Dit start geen crawler.</span></article><article><strong>Pauzeren</strong><span>Tijdelijk uit de automatische planning; een eenmalige handmatige crawlercheck blijft mogelijk.</span></article><article><strong>Stoppen</strong><span>Geen automatische of handmatige crawlercheck totdat je Doorgaan kiest.</span></article><article><strong>Verwijderen</strong><span>Archiveert de bron met herstelhistorie; er vindt geen harde delete van bron- of auditgegevens plaats.</span></article></div>';
    return $html . '</section>';
}

function mvm_hubs_v3_mail_readonly_available(): bool {
    return function_exists( 'mvm_hub_mail_get_credentials' )
        && function_exists( 'mvm_hub_mail_imap_mailbox' )
        && function_exists( 'mvm_hub_mail_decode_header' )
        && function_exists( 'mvm_hub_mail_decode_part' )
        && function_exists( 'imap_open' )
        && function_exists( 'imap_search' )
        && function_exists( 'imap_fetch_overview' )
        && function_exists( 'imap_fetchstructure' )
        && function_exists( 'imap_fetchbody' )
        && function_exists( 'imap_body' )
        && function_exists( 'imap_msgno' )
        && defined( 'OP_READONLY' )
        && defined( 'FT_UID' )
        && defined( 'FT_PEEK' )
        && defined( 'SE_UID' );
}

function mvm_hubs_v3_mail_open_readonly() {
    if ( ! mvm_hubs_v3_mail_readonly_available() ) {
        return new WP_Error( 'mvm_mail_adapter_unavailable', 'De alleen-lezen mailadapter is niet beschikbaar.', array( 'status' => 503 ) );
    }

    $credentials = mvm_hub_mail_get_credentials();
    if ( is_wp_error( $credentials ) ) {
        return $credentials;
    }
    if ( ! is_array( $credentials ) || empty( $credentials['email'] ) || empty( $credentials['password'] ) ) {
        return new WP_Error( 'mvm_mail_not_connected', 'Koppel eerst je MvM-mailbox via de bestaande mailservice.', array( 'status' => 409 ) );
    }

    $mailbox      = (string) mvm_hub_mail_imap_mailbox();
    $folder_start = strrpos( $mailbox, '}' );
    $folder       = false === $folder_start ? '' : substr( $mailbox, $folder_start + 1 );
    if ( '' === $mailbox || 'INBOX' !== strtoupper( trim( (string) $folder ) ) ) {
        return new WP_Error( 'mvm_mail_folder_boundary', 'Alleen de vaste INBOX-mailbox is toegestaan.', array( 'status' => 503 ) );
    }

    $imap = @imap_open( $mailbox, (string) $credentials['email'], (string) $credentials['password'], OP_READONLY, 1 );
    if ( false === $imap ) {
        if ( function_exists( 'imap_errors' ) ) {
            @imap_errors();
        }
        return new WP_Error( 'mvm_mail_imap_unavailable', 'De mailbox kon niet veilig alleen-lezen worden geopend.', array( 'status' => 502 ) );
    }

    return array(
        'imap' => $imap,
    );
}

function mvm_hubs_v3_mail_part_is_attachment( $structure ): bool {
    if ( ! is_object( $structure ) ) {
        return false;
    }
    $disposition = isset( $structure->disposition ) ? strtoupper( (string) $structure->disposition ) : '';
    if ( 'ATTACHMENT' === $disposition ) {
        return true;
    }
    foreach ( array( 'parameters', 'dparameters' ) as $property ) {
        if ( empty( $structure->{$property} ) || ! is_array( $structure->{$property} ) ) {
            continue;
        }
        foreach ( $structure->{$property} as $parameter ) {
            $attribute = isset( $parameter->attribute ) ? strtoupper( (string) $parameter->attribute ) : '';
            if ( in_array( $attribute, array( 'NAME', 'FILENAME' ), true ) ) {
                return true;
            }
        }
    }
    return false;
}

function mvm_hubs_v3_mail_peek_body_part( $imap, int $uid, $structure, string $prefix = '' ): array {
    if ( ! is_object( $structure ) || mvm_hubs_v3_mail_part_is_attachment( $structure ) ) {
        return array();
    }

    $type    = isset( $structure->type ) ? (int) $structure->type : -1;
    $subtype = isset( $structure->subtype ) ? strtoupper( (string) $structure->subtype ) : '';
    if ( 0 === $type && in_array( $subtype, array( 'PLAIN', 'HTML' ), true ) ) {
        if ( '' === $prefix ) {
            $raw = @imap_body( $imap, $uid, FT_UID | FT_PEEK );
        } else {
            $raw = @imap_fetchbody( $imap, $uid, $prefix, FT_UID | FT_PEEK );
        }
        if ( false === $raw ) {
            return array();
        }
        return array(
            'content' => (string) mvm_hub_mail_decode_part( (string) $raw, isset( $structure->encoding ) ? (int) $structure->encoding : 0 ),
            'html'    => 'HTML' === $subtype,
        );
    }

    if ( empty( $structure->parts ) || ! is_array( $structure->parts ) ) {
        return array();
    }

    $plain_candidate = array();
    $html_candidate  = array();
    foreach ( $structure->parts as $index => $part ) {
        $section = '' === $prefix ? (string) ( $index + 1 ) : $prefix . '.' . ( $index + 1 );
        $found   = mvm_hubs_v3_mail_peek_body_part( $imap, $uid, $part, $section );
        if ( empty( $found['content'] ) ) {
            continue;
        }
        if ( empty( $found['html'] ) ) {
            $plain_candidate = $found;
            break;
        }
        if ( ! $html_candidate ) {
            $html_candidate = $found;
        }
    }

    return $plain_candidate ?: $html_candidate;
}

function mvm_hubs_v3_mail_plain_text( string $content, bool $is_html ): string {
    if ( $is_html ) {
        $content = preg_replace( '/<(?:br\s*\/?|\/p|\/div|\/li|\/tr|\/h[1-6])\s*>/i', "\n", $content );
        $content = wp_strip_all_tags( (string) $content, true );
        $content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ?: 'UTF-8' );
    } else {
        $content = wp_strip_all_tags( $content, true );
    }
    $content = preg_replace( "/\r\n?|\x{2028}|\x{2029}/u", "\n", (string) $content );
    $content = preg_replace( "/[\t ]+\n/u", "\n", (string) $content );
    $content = preg_replace( "/\n{4,}/u", "\n\n\n", (string) $content );
    $content = trim( (string) $content );
    if ( function_exists( 'mb_substr' ) ) {
        return mb_substr( $content, 0, 200000 );
    }
    return substr( $content, 0, 200000 );
}

function mvm_hubs_v3_mail_rest_permission(): bool {
    if ( ! is_user_logged_in() || ! mvm_hubs_v3_can_access( 'editorial' ) ) {
        return false;
    }
    return current_user_can( 'mvm_hub3_editorial_access' );
}

function mvm_hubs_v3_mail_read_audit_gate( string $action, int $uid = 0 ): bool {
    if ( ! post_type_exists( 'mvm_hub_audit_v3' ) ) {
        return false;
    }
    $action = in_array( $action, array( 'list', 'read' ), true ) ? $action : 'read';
    $audit_id = wp_insert_post(
        array(
            'post_type'    => 'mvm_hub_audit_v3',
            'post_status'  => 'private',
            'post_title'   => 'mail_' . $action . '_authorized',
            'post_content' => wp_json_encode(
                array(
                    'hub'         => 'editorial',
                    'object_type' => 'mvm_mail_inbox',
                    'object_id'   => max( 0, $uid ),
                    'result'      => 'read',
                ),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'post_author'  => get_current_user_id(),
        ),
        true
    );
    return ! is_wp_error( $audit_id ) && (int) $audit_id > 0;
}

function mvm_hubs_v3_mail_rate_guard( string $bucket, int $limit, int $window ): true|WP_Error {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return new WP_Error( 'mvm_mail_not_authenticated', 'Log opnieuw in om de mailbox te bekijken.', array( 'status' => 401 ) );
    }
    $bucket = in_array( $bucket, array( 'list', 'read' ), true ) ? $bucket : 'read';
    $key    = 'mvm_mail_ro_' . $bucket . '_' . $user_id;
    $count  = (int) get_transient( $key );
    if ( $count >= max( 1, $limit ) ) {
        return new WP_Error( 'mvm_mail_rate_limited', 'Er zijn te veel mailboxverzoeken gedaan. Probeer het over enkele minuten opnieuw.', array( 'status' => 429 ) );
    }
    set_transient( $key, $count + 1, max( 60, $window ) );
    return true;
}

function mvm_hubs_v3_mail_rest_response( array $data ): WP_REST_Response {
    $response = new WP_REST_Response( $data, 200 );
    $response->header( 'Cache-Control', 'private, no-store, max-age=0, must-revalidate' );
    $response->header( 'Pragma', 'no-cache' );
    return $response;
}

function mvm_hubs_v3_mail_rest_list( WP_REST_Request $request ) {
    $rate = mvm_hubs_v3_mail_rate_guard( 'list', 20, 5 * MINUTE_IN_SECONDS );
    if ( is_wp_error( $rate ) ) {
        return $rate;
    }
    if ( ! mvm_hubs_v3_mail_read_audit_gate( 'list' ) ) {
        return new WP_Error( 'mvm_mail_audit_failed', 'De beveiligde auditregistratie is mislukt. De mailbox is niet geopend.', array( 'status' => 503 ) );
    }
    $connection = mvm_hubs_v3_mail_open_readonly();
    if ( is_wp_error( $connection ) ) {
        return $connection;
    }

    $imap   = $connection['imap'];
    $limit  = max( 1, min( 50, absint( $request->get_param( 'limit' ) ?: 30 ) ) );
    $offset = max( 0, absint( $request->get_param( 'offset' ) ?: 0 ) );

    try {
        $uids = @imap_search( $imap, 'ALL', SE_UID );
        if ( false === $uids || ! is_array( $uids ) ) {
            return mvm_hubs_v3_mail_rest_response( array( 'messages' => array(), 'total' => 0, 'readonly' => true ) );
        }
        rsort( $uids, SORT_NUMERIC );
        $total = count( $uids );
        $uids  = array_slice( $uids, $offset, $limit );
        if ( ! $uids ) {
            return mvm_hubs_v3_mail_rest_response( array( 'messages' => array(), 'total' => $total, 'readonly' => true ) );
        }

        $overview = @imap_fetch_overview( $imap, implode( ',', array_map( 'absint', $uids ) ), FT_UID );
        if ( false === $overview || ! is_array( $overview ) ) {
            return new WP_Error( 'mvm_mail_list_failed', 'De berichtenlijst kon niet veilig worden opgehaald.', array( 'status' => 502 ) );
        }
        $by_uid = array();
        foreach ( $overview as $item ) {
            $item_uid = isset( $item->uid ) ? absint( $item->uid ) : 0;
            if ( $item_uid ) {
                $by_uid[ $item_uid ] = $item;
            }
        }

        $messages = array();
        foreach ( $uids as $uid ) {
            $uid = absint( $uid );
            if ( ! isset( $by_uid[ $uid ] ) ) {
                continue;
            }
            $item = $by_uid[ $uid ];
            $messages[] = array(
                'uid'     => $uid,
                'subject' => sanitize_text_field( (string) mvm_hub_mail_decode_header( isset( $item->subject ) ? (string) $item->subject : '(geen onderwerp)' ) ),
                'from'    => sanitize_text_field( (string) mvm_hub_mail_decode_header( isset( $item->from ) ? (string) $item->from : '' ) ),
                'date'    => sanitize_text_field( isset( $item->date ) ? (string) $item->date : '' ),
                'seen'    => ! empty( $item->seen ),
                'size'    => isset( $item->size ) ? absint( $item->size ) : 0,
            );
        }
        return mvm_hubs_v3_mail_rest_response( array( 'messages' => $messages, 'total' => $total, 'readonly' => true ) );
    } finally {
        @imap_close( $imap );
        if ( function_exists( 'imap_errors' ) ) {
            @imap_errors();
        }
    }
}

function mvm_hubs_v3_mail_rest_read( WP_REST_Request $request ) {
    $uid = absint( $request->get_param( 'uid' ) );
    if ( ! $uid ) {
        return new WP_Error( 'mvm_mail_invalid_uid', 'Ongeldig bericht-ID.', array( 'status' => 400 ) );
    }
    $rate = mvm_hubs_v3_mail_rate_guard( 'read', 60, 5 * MINUTE_IN_SECONDS );
    if ( is_wp_error( $rate ) ) {
        return $rate;
    }
    if ( ! mvm_hubs_v3_mail_read_audit_gate( 'read', $uid ) ) {
        return new WP_Error( 'mvm_mail_audit_failed', 'De beveiligde auditregistratie is mislukt. Het bericht is niet geopend.', array( 'status' => 503 ) );
    }

    $connection = mvm_hubs_v3_mail_open_readonly();
    if ( is_wp_error( $connection ) ) {
        return $connection;
    }
    $imap = $connection['imap'];

    try {
        if ( 0 >= (int) @imap_msgno( $imap, $uid ) ) {
            return new WP_Error( 'mvm_mail_message_not_found', 'Dit bericht bestaat niet in jouw INBOX.', array( 'status' => 404 ) );
        }
        $overview = @imap_fetch_overview( $imap, (string) $uid, FT_UID );
        if ( false === $overview || empty( $overview[0] ) ) {
            return new WP_Error( 'mvm_mail_message_failed', 'Het bericht kon niet veilig worden gelezen.', array( 'status' => 502 ) );
        }
        $structure = @imap_fetchstructure( $imap, $uid, FT_UID );
        if ( false === $structure ) {
            return new WP_Error( 'mvm_mail_structure_failed', 'De berichtstructuur kon niet veilig worden gelezen.', array( 'status' => 502 ) );
        }
        $body = mvm_hubs_v3_mail_peek_body_part( $imap, $uid, $structure );
        $item = $overview[0];

        return mvm_hubs_v3_mail_rest_response(
            array(
                'message' => array(
                    'uid'     => $uid,
                    'subject' => sanitize_text_field( (string) mvm_hub_mail_decode_header( isset( $item->subject ) ? (string) $item->subject : '(geen onderwerp)' ) ),
                    'from'    => sanitize_text_field( (string) mvm_hub_mail_decode_header( isset( $item->from ) ? (string) $item->from : '' ) ),
                    'to'      => sanitize_text_field( (string) mvm_hub_mail_decode_header( isset( $item->to ) ? (string) $item->to : '' ) ),
                    'date'    => sanitize_text_field( isset( $item->date ) ? (string) $item->date : '' ),
                    'seen'    => ! empty( $item->seen ),
                    'body'    => mvm_hubs_v3_mail_plain_text( isset( $body['content'] ) ? (string) $body['content'] : '', ! empty( $body['html'] ) ),
                ),
                'readonly' => true,
            )
        );
    } finally {
        @imap_close( $imap );
        if ( function_exists( 'imap_errors' ) ) {
            @imap_errors();
        }
    }
}

function mvm_hubs_v3_mail_service_status(): array {
    $next_cleanup = wp_next_scheduled( 'mvm_mail_trash_cleanup_v2' );
    return array(
        'routing'      => false !== get_option( 'mvm_hub_mail_routing', false ),
        'folders'      => (bool) get_option( 'mvm_mailbox_default_folders_v1_done', false ),
        'trash_cron'   => false !== $next_cleanup,
        'next_cleanup'     => $next_cleanup ? wp_date( 'd-m-Y H:i', (int) $next_cleanup ) : '',
        'readonly_adapter' => mvm_hubs_v3_mail_readonly_available(),
    );
}

function mvm_hubs_v3_editorial_count( array $statuses, bool $team = false ): int {
    $statuses = array_values( array_intersect( $statuses, array( 'draft', 'pending', 'future', 'publish' ) ) );
    if ( ! $statuses ) {
        return 0;
    }
    $args = array(
        'post_type'      => 'post',
        'post_status'    => $statuses,
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => false,
    );
    if ( ! $team ) {
        $args['author'] = get_current_user_id();
    }
    $query = new WP_Query( $args );
    return (int) $query->found_posts;
}

function mvm_hubs_v3_editorial_workflow_summary_html(): string {
    $can_review = mvm_hubs_v3_can_review();
    $team_plan  = mvm_hubs_v3_can_view_team_planning();
    $items = array(
        array( 'Mijn concepten', mvm_hubs_v3_editorial_count( array( 'draft' ) ), 'Alleen jouw eigen werk' ),
        array( 'Mijn inzendingen', mvm_hubs_v3_editorial_count( array( 'pending' ) ), 'Wacht op redactionele controle' ),
        array( 'Mijn publicaties', mvm_hubs_v3_editorial_count( array( 'publish' ) ), 'Gepubliceerd onder jouw account' ),
    );
    if ( $can_review ) {
        $items[] = array( 'Team · te beoordelen', mvm_hubs_v3_editorial_count( array( 'pending' ), true ), 'Alleen met reviewrecht' );
    }
    if ( $team_plan ) {
        $items[] = array( 'Team · gepland', mvm_hubs_v3_editorial_count( array( 'future' ), true ), 'Alleen met planningsrecht' );
    }
    $html = '<section class="mvmh3-section mvmh3-review-summary"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Workflow</span><h2>Redactionele status</h2></div><p>Aantallen volgen dezelfde server-side rolgrenzen als de werk- en reviewlijsten.</p></div><div class="mvmh3-review-summary__grid">';
    foreach ( $items as $item ) {
        $html .= '<article><span>' . esc_html( (string) $item[0] ) . '</span><strong>' . esc_html( (string) $item[1] ) . '</strong><small>' . esc_html( (string) $item[2] ) . '</small></article>';
    }
    return $html . '</div></section>';
}

function mvm_hubs_v3_editorial_content(): string {
    $can_submit     = mvm_hubs_v3_can_submit_editorial();
    $can_review     = mvm_hubs_v3_can_review();
    $newsradar      = mvm_hubs_v3_can_view_newsradar();
    $sources        = mvm_hubs_v3_can_view_sources();
    $mail           = mvm_hubs_v3_mail_rest_permission();
    $vacancy_review = mvm_hubs_v3_can_manage_vacancies();
    $moderation     = mvm_hubs_v3_can_moderate();
    $team_plan      = mvm_hubs_v3_can_view_team_planning();

    $html  = '<section class="mvmh3-intro"><div><span class="mvmh3-eyebrow">Redactiehub</span><h1>Alleen wat jij voor je werk nodig hebt.</h1><p>Je ziet hier alleen de taken die bij jouw rol horen. Schrijven, beoordelen, modereren en plannen zijn bewust van elkaar gescheiden.</p></div><span class="mvmh3-security-badge">Privé · veilig</span></section>';
    $html .= mvm_hubs_v3_editorial_workflow_summary_html();
    $html .= '<div class="mvmh3-grid">';
    if ( $can_submit ) {
        $html .= mvm_hubs_v3_card( 'newspaper', 'Mijn werk', 'Bekijk jouw eigen concepten, ingediende stukken en publicaties.', home_url( '/redactie-hub/?deel=mijnwerk' ), 'Mijn werk', 'Alleen jouw werk' );
        $html .= mvm_hubs_v3_card( 'plus', 'Nieuw artikel', 'Schrijf of lever een nieuw nieuwsartikel aan via de vaste redactiestroom.', home_url( '/nieuws-insturen/' ), 'Nieuw artikel', 'Schrijven & aanleveren' );
    }
    if ( $can_review ) {
        $html .= mvm_hubs_v3_card( 'check', 'Te beoordelen', 'Bekijk artikelen die wachten op redactionele controle.', home_url( '/redactie-hub/?deel=review' ), 'Beoordelingen openen', 'Editor / teamleider' );
    }
    if ( $newsradar ) {
        $html .= mvm_hubs_v3_card( 'activity', 'Nieuwsradar', 'Bekijk nieuwe signalen uit lokale bronnen. Beoordelen verschijnt alleen met apart reviewrecht.', home_url( '/redactie-hub/?deel=nieuwsradar' ), 'Nieuwsradar openen', mvm_hubs_v3_can_review_newsradar() ? 'Lezen + beoordelen' : 'Alleen lezen' );
    }
    if ( $sources ) {
        $html .= mvm_hubs_v3_card( 'bookmark', 'Bronnen', 'Bekijk welke nieuwsbronnen actief zijn en welke controle achterstallig, vandaag of deze week gepland staat.', home_url( '/redactie-hub/?deel=bronnen' ), 'Bronnen bekijken', 'Alleen lezen' );
    }
    if ( $mail ) {
        $html .= mvm_hubs_v3_card( 'envelope', 'MvM Mail', 'Open de bestaande beveiligde MvM-mailbox vanuit één vaste plek in de Redactiehub.', home_url( '/redactie-hub/?deel=mail' ), 'Mail openen', 'Bestaande mailservice' );
    }
    if ( $vacancy_review ) {
        $html .= mvm_hubs_v3_card( 'briefcase', 'Vacatures beoordelen', 'Controleer organisatievacatures, publiceer goedgekeurde inzendingen of zet ze terug naar concept.', home_url( '/redactie-hub/?deel=vacatures' ), 'Vacatures openen', 'Editor / teamleider' );
    }
    if ( $team_plan ) {
        $html .= mvm_hubs_v3_card( 'calendar', 'Planning', 'Bekijk de teamplanning met concepten en geplande publicaties.', home_url( '/redactie-hub/?deel=planning' ), 'Planning openen', 'Teamleider' );
    }
    if ( $moderation ) {
        $html .= mvm_hubs_v3_card( 'shield', 'Moderatie', 'Behandel meldingen uit nieuwsreacties, forum en community.', home_url( '/redactie-hub/?deel=moderatie' ), 'Moderatie openen', 'Moderator' );
    }
    $html .= mvm_hubs_v3_card( 'group', 'Team & afspraken', 'Bekijk kort wie welke taak heeft en hoe werk wordt doorgegeven.', home_url( '/redactie-hub/?deel=team' ), 'Rollen bekijken', 'Duidelijke taakverdeling' );
    $html .= mvm_hubs_v3_card( 'activity', 'Teamberichten', 'Veilige interne communicatie voor het redactieteam, los van gewone PeepSo-privéberichten.', home_url( '/redactie-hub/?deel=communicatie' ), 'Status bekijken', 'Veilige communicatie · in voorbereiding' );
    $html .= '</div>';

    $deel = isset( $_GET['deel'] ) ? sanitize_key( wp_unslash( $_GET['deel'] ) ) : '';
    if ( 'mijnwerk' === $deel && $can_submit ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Mijn werk</span><h2>Mijn artikelen</h2></div><p>Alleen jouw eigen redactionele werk staat in deze lijst.</p></div>' . mvm_hubs_v3_editorial_posts_table( false ) . '</section>';
    } elseif ( 'review' === $deel && $can_review ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Beoordelen</span><h2>Wacht op controle</h2></div><p>Deze lijst bevat alleen artikelen met status Te beoordelen. Publiceren blijft afhankelijk van de bestaande WordPress-rechten op het artikel.</p></div>' . mvm_hubs_v3_editorial_posts_table( true, array( 'pending' ) ) . '</section>';
    } elseif ( 'nieuwsradar' === $deel && $newsradar ) {
        $html .= mvm_hubs_v3_newsradar_html();
    } elseif ( 'bronnen' === $deel && $sources ) {
        $html .= mvm_hubs_v3_sources_html();
    } elseif ( 'mail' === $deel && $mail ) {
        $legacy_mail_url = add_query_arg( 'tab', 'mail', home_url( '/hub/' ) );
        $mail_status     = mvm_hubs_v3_mail_service_status();
        $preview         = mvm_hubs_v3_is_draft_preview();
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Teammail</span><h2>MvM Mail</h2></div><p>De Redactiehub is vanaf nu de vaste ingang. De mailbox zelf blijft in deze fase op de bestaande legacy-mailservice, zodat berichten, folders, routing en de dagelijkse prullenbakopschoning niet worden gekopieerd of onderbroken.</p></div>';
        $html .= '<div class="mvmh3-review-summary__grid"><article><span>Routing</span><strong>' . esc_html( $mail_status['routing'] ? 'Ingericht' : 'Niet gevonden' ) . '</strong></article><article><span>Standaardmappen</span><strong>' . esc_html( $mail_status['folders'] ? 'Ingericht' : 'Onbekend' ) . '</strong></article><article><span>Prullenbakopschoning</span><strong>' . esc_html( $mail_status['trash_cron'] ? 'Gepland' : 'Niet gepland' ) . '</strong><small>' . esc_html( $mail_status['next_cleanup'] ? 'Volgende: ' . $mail_status['next_cleanup'] : 'Geen volgende run gevonden' ) . '</small></article><article><span>V3 lezen</span><strong>' . esc_html( $mail_status['readonly_adapter'] ? 'Alleen-lezen gereed' : 'Niet beschikbaar' ) . '</strong><small>INBOX · geen flags of folderwrites</small></article></div>';
        $html .= '<div class="mvmh3-callout"><strong>Veilige overgang</strong><span>Mailinhoud wordt nooit vooraf geladen. V3 biedt in deze fase uitsluitend list/read via GET, OP_READONLY en FT_PEEK. Verzenden, attachments, CC/BCC, connect-, verwijder- en folderacties blijven volledig buiten v3 en lopen alleen via de bestaande legacy-mailservice.</span></div>';
        if ( $preview ) {
            $html .= '<div class="mvmh3-notice"><strong>Previewmodus · geen mailboxdata</strong><span>De draft toont alleen servicestatus. Live INBOX-inhoud en de legacy-mailbox worden in een WPVibe-preview niet geopend.</span></div>';
        } elseif ( $mail_status['readonly_adapter'] ) {
            $html .= '<div class="mvmh3-mail-readonly" data-mvmh3-mail-readonly><div class="mvmh3-mail-readonly__head"><div><strong>INBOX veilig bekijken</strong><span>Alleen GET · OP_READONLY · FT_PEEK · maximaal 30 berichten per keer</span></div><button type="button" class="mvmh3-primary" data-mvmh3-mail-load>Berichten laden</button></div><p class="mvmh3-mail-readonly__status" data-mvmh3-mail-status aria-live="polite">Er is nog geen mailinhoud opgehaald.</p><div class="mvmh3-mail-readonly__layout"><div class="mvmh3-mail-readonly__list" data-mvmh3-mail-list></div><article class="mvmh3-mail-readonly__message" data-mvmh3-mail-message hidden><div class="mvmh3-mail-readonly__message-head"><h3 data-mvmh3-mail-subject></h3><p data-mvmh3-mail-meta></p></div><pre data-mvmh3-mail-body></pre></article></div></div>';
        }
        if ( $preview ) {
            $html .= '<p><span class="mvmh3-preview-lock">Preview · legacy-mailbox uitgeschakeld</span></p></section>';
        } else {
            $html .= '<p><a class="mvmh3-secondary" href="' . esc_url( $legacy_mail_url ) . '">Legacy-mailbox openen</a> <span class="mvmh3-muted">Alleen nog nodig voor koppelen, ontkoppelen en overige niet-gemigreerde folderacties.</span></p></section>';
        }
    } elseif ( 'vacatures' === $deel && $vacancy_review ) {
        $html .= mvm_hubs_v3_vacancy_review_html();
    } elseif ( 'planning' === $deel && $team_plan ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Planning</span><h2>Wat komt wanneer?</h2></div><p>Alleen Teamleider, SysOp en Administrator kunnen deze team-brede planning openen.</p></div>' . mvm_hubs_v3_editorial_posts_table( true ) . '</section>';
    } elseif ( 'team' === $deel ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Rollen</span><h2>Wie doet wat?</h2></div><p>Iedere rol heeft één duidelijke hoofdtaak. Extra toegang wordt alleen gegeven wanneer die taak dat nodig heeft.</p></div><div class="mvmh3-grid mvmh3-grid--compact">' . mvm_hubs_v3_card( 'newspaper', 'Redacteur / journalist', 'Schrijft en levert nieuws aan.' ) . mvm_hubs_v3_card( 'group', 'Fotograaf / vertaler', 'Levert beeld of vertaling binnen de redactionele werkruimte.' ) . mvm_hubs_v3_card( 'check', 'Editor', 'Beoordeelt artikelen en organisatievacatures en bewaakt kwaliteit.' ) . mvm_hubs_v3_card( 'shield', 'Moderator', 'Behandelt meldingen en moderatie; geen automatisch reviewrecht.' ) . mvm_hubs_v3_card( 'calendar', 'Teamleider', 'Beoordeelt waar nodig artikelen en organisatievacatures en beheert de teamplanning; geen automatisch moderatierecht.' ) . '</div></section>';
    } elseif ( 'communicatie' === $deel ) {
        $secure_ready = class_exists( 'MvM_Hubs_V3_Communications' );
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Teamberichten</span><h2>Interne communicatie blijft afgeschermd</h2></div><p>De draft laadt nog geen gesprekstekst. Voor ieder gesprek wordt straks opnieuw gecontroleerd of iemand nu nog redactionele toegang én huidig deelnemerschap heeft.</p></div><div class="mvmh3-callout"><strong>' . esc_html( $secure_ready ? 'Beveiligingslaag beschikbaar' : 'Nog niet actief in productie' ) . '</strong><span>Verlies van een redactionele rol beëindigt ook toegang tot oude interne gesprekken. Noodinzage vereist een geldige reden en een succesvol privaat auditrecord.</span></div></section>';
    } elseif ( $moderation && 'moderatie' === $deel ) {
        $secure_ready = class_exists( 'MvM_Hubs_V3_Moderation' );
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Moderatie</span><h2>Drie bronnen, één werkwijze</h2></div><p>Nieuwsreacties, forum en community blijven technisch gescheiden. Details worden pas na een nieuwe server-side rechtencontrole geladen.</p></div><div class="mvmh3-grid mvmh3-grid--compact">';
        $html .= mvm_hubs_v3_card( 'newspaper', 'Nieuwsreacties', 'Behandel gemelde reacties onder nieuws.', '', 'Bronstatus' );
        $html .= mvm_hubs_v3_card( 'forum', 'Forum', 'Behandel gemelde onderwerpen en berichten in wpForo.', home_url( '/forum/' ), 'Naar forum' );
        $html .= mvm_hubs_v3_card( 'group', 'Community', 'Behandel PeepSo-meldingen en communityacties.', home_url( '/activity/' ), 'Naar community' );
        $html .= '</div><div class="mvmh3-source-status" data-mvmh3-moderation-sources><div data-mvmh3-source="news_comments"><strong>Nieuwsreacties</strong><span>' . esc_html( $secure_ready ? 'Bronstatus wordt beveiligd opgehaald…' : 'Secure Core nog niet actief' ) . '</span></div><div data-mvmh3-source="forum"><strong>Forum</strong><span>' . esc_html( $secure_ready ? 'Bronstatus wordt beveiligd opgehaald…' : 'Secure Core nog niet actief' ) . '</span></div><div data-mvmh3-source="community"><strong>Community</strong><span>' . esc_html( $secure_ready ? 'Bronstatus wordt beveiligd opgehaald…' : 'Secure Core nog niet actief' ) . '</span></div></div><div class="mvmh3-callout"><strong>Veilig uitbouwen</strong><span>Verwijderen, blokkeren of detailinhoud ophalen wordt alleen toegevoegd met een aparte server-side broncontrole en audit.</span></div></section>';
    }
    return $html;
}

function mvm_hubs_v3_save_home_blocks(): void {
    if ( mvm_hubs_v3_is_draft_preview() ) {
        wp_die( esc_html__( 'Previewmodus: Home-blokken kunnen hier niet live worden gewijzigd.', 'mvm' ), '', array( 'response' => 403 ) );
    }
    if ( ! is_user_logged_in() || ! mvm_hubs_v3_can_access( 'admin' ) ) {
        wp_die( esc_html__( 'Geen toegang tot deze instellingen.', 'mvm' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'mvm_hubs_v3_save_home_blocks', 'mvm_hubs_v3_nonce' );

    $raw = isset( $_POST['slots'] ) && is_array( $_POST['slots'] ) ? wp_unslash( $_POST['slots'] ) : array();
    $clean = array();
    $allowed_types   = array( 'news', 'events' );
    $allowed_layouts = function_exists( 'mvm_cb3_allowed_layouts' ) ? mvm_cb3_allowed_layouts() : array( '1x2', '2x2', '3x2', 'lead-4', 'compact-list' );

    foreach ( array_slice( $raw, 0, 4, true ) as $slot ) {
        if ( ! is_array( $slot ) ) {
            continue;
        }
        $type   = isset( $slot['type'] ) ? sanitize_key( $slot['type'] ) : 'news';
        $layout = isset( $slot['layout'] ) ? sanitize_key( $slot['layout'] ) : '3x2';
        $clean[] = array(
            'enabled'  => ! empty( $slot['enabled'] ) ? 1 : 0,
            'type'     => in_array( $type, $allowed_types, true ) ? $type : 'news',
            'layout'   => in_array( $layout, $allowed_layouts, true ) ? $layout : '3x2',
            'title'    => isset( $slot['title'] ) ? sanitize_text_field( $slot['title'] ) : '',
            'category' => isset( $slot['category'] ) ? sanitize_title( $slot['category'] ) : '',
            'limit'    => isset( $slot['limit'] ) ? max( 1, min( 12, absint( $slot['limit'] ) ) ) : 6,
        );
    }

    update_option( 'mvm_home_blocks_v3', $clean, false );
    mvm_hubs_v3_audit( 'homeblokken_bijgewerkt', array( 'hub' => 'admin', 'object_type' => 'option', 'object_id' => 0, 'result' => 'saved' ) );
    wp_safe_redirect( add_query_arg( array( 'deel' => 'homeblokken', 'saved' => '1' ), home_url( '/beheer-hub/' ) ) );
    exit;
}
if ( ! class_exists( 'MvM_Hubs_V3_Home_Settings' ) ) {
    add_action( 'admin_post_mvm_hubs_v3_save_home_blocks', 'mvm_hubs_v3_save_home_blocks' );
}

function mvm_hubs_v3_home_blocks_form(): string {
    $preview = mvm_hubs_v3_is_draft_preview();
    $config = get_option( 'mvm_home_blocks_v3', array() );
    $config = is_array( $config ) ? array_values( $config ) : array();
    while ( count( $config ) < 4 ) {
        $config[] = array( 'enabled' => 0, 'type' => 'news', 'layout' => '3x2', 'title' => '', 'category' => '', 'limit' => 6 );
    }

    $categories = get_categories( array( 'hide_empty' => false ) );
    $html = '<form class="mvmh3-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><fieldset class="mvmh3-preview-safe"' . ( $preview ? ' disabled aria-disabled="true"' : '' ) . '><input type="hidden" name="action" value="mvm_hubs_v3_save_home_blocks">' . wp_nonce_field( 'mvm_hubs_v3_save_home_blocks', 'mvm_hubs_v3_nonce', true, false );
    $html .= '<div class="mvmh3-slot-grid">';
    foreach ( array_slice( $config, 0, 4 ) as $index => $slot ) {
        $html .= '<fieldset class="mvmh3-slot"><legend>Extra blok ' . esc_html( (string) ( $index + 1 ) ) . '</legend><label class="mvmh3-switch"><input type="checkbox" name="slots[' . esc_attr( (string) $index ) . '][enabled]" value="1" ' . checked( ! empty( $slot['enabled'] ), true, false ) . '><span>Toon dit blok op Home</span></label><div class="mvmh3-form__grid"><label><span>Inhoud</span><select name="slots[' . esc_attr( (string) $index ) . '][type]"><option value="news" ' . selected( $slot['type'], 'news', false ) . '>Nieuws</option><option value="events" ' . selected( $slot['type'], 'events', false ) . '>Evenementen</option></select></label><label><span>Layout</span><select name="slots[' . esc_attr( (string) $index ) . '][layout]"><option value="1x2" ' . selected( $slot['layout'], '1x2', false ) . '>1 × 2</option><option value="2x2" ' . selected( $slot['layout'], '2x2', false ) . '>2 × 2</option><option value="3x2" ' . selected( $slot['layout'], '3x2', false ) . '>3 × 2</option><option value="lead-4" ' . selected( $slot['layout'], 'lead-4', false ) . '>Hoofd + 4</option><option value="compact-list" ' . selected( $slot['layout'], 'compact-list', false ) . '>Compacte lijst</option></select></label><label class="mvmh3-form__wide"><span>Titel</span><input type="text" name="slots[' . esc_attr( (string) $index ) . '][title]" value="' . esc_attr( $slot['title'] ?? '' ) . '" maxlength="90"></label><label><span>Aantal</span><input type="number" min="1" max="12" name="slots[' . esc_attr( (string) $index ) . '][limit]" value="' . esc_attr( (string) ( $slot['limit'] ?? 6 ) ) . '"></label><label><span>Nieuws-categorie</span><select name="slots[' . esc_attr( (string) $index ) . '][category]"><option value="">Alle categorieën</option>';
        foreach ( $categories as $category ) {
            $html .= '<option value="' . esc_attr( $category->slug ) . '" ' . selected( $slot['category'] ?? '', $category->slug, false ) . '>' . esc_html( $category->name ) . '</option>';
        }
        $html .= '</select></label></div><p class="mvmh3-slot__hint">Bij Evenementen wordt de categorie genegeerd. 1×2, 2×2, 3×2 en Hoofd + 4 gebruiken respectievelijk 2, 4, 6 en 5 items; het veld Aantal geldt alleen voor de compacte lijst. Een uitgeschakeld blok blijft bewaard maar verschijnt niet op Home.</p></fieldset>';
    }
    $html .= '</div><div class="mvmh3-form__footer"><p>' . esc_html( $preview ? 'Previewmodus: instellingen zijn alleen-lezen en kunnen hier niet live worden opgeslagen.' : 'Wijzigingen gelden pas nadat je ze hier bewust opslaat. De hoofd-homepage blijft verder ongemoeid.' ) . '</p><button class="mvmh3-primary" type="submit">Home-blokken opslaan</button></div></fieldset></form>';
    return $html;
}

function mvm_hubs_v3_security_matrix_html(): string {
    $rows = array(
        array( 'Gebruikershub', 'Ingelogd account', 'Eigen en publieke gegevens' ),
        array( 'Organisatiehub', 'mvm_hub3_business_access', 'Vereniging / club / organisator / ondernemer / bedrijf / winkelier' ),
        array( 'Redactiehub', 'mvm_hub3_editorial_access', 'Redactionele teamrollen' ),
        array( 'Beoordelen', 'mvm_hub3_review_access', 'Editor / teamleider' ),
        array( 'Moderatie', 'mvm_hub3_moderation_access', 'Moderator' ),
        array( 'Teamplanning', 'mvm_hub3_team_planning_access', 'Teamleider' ),
        array( 'Technische hub', 'mvm_hub3_admin_access', 'SysOp / administrator' ),
    );
    $html = '<table class="mvmh3-table"><thead><tr><th>Domein</th><th>Serverrecht</th><th>Basisgroep</th></tr></thead><tbody>';
    foreach ( $rows as $row ) {
        $html .= '<tr><td><strong>' . esc_html( $row[0] ) . '</strong></td><td><code>' . esc_html( $row[1] ) . '</code></td><td>' . esc_html( $row[2] ) . '</td></tr>';
    }
    return $html . '</tbody></table>';
}

function mvm_hubs_v3_integrations_html(): string {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugins = array(
        'peepso-core-8.0.0.0/peepso.php' => 'PeepSo community',
        'wpforo/wpforo.php' => 'wpForo forum',
        'wp-event-manager/wp-event-manager.php' => 'WP Event Manager',
        'mvm-hub4-rc-direct/mvm-hub4.php' => 'Bestaande MvM Hub-datalaag',
        'backuply/backuply.php' => 'Backuply',
        'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
    );
    $html = '<table class="mvmh3-table"><thead><tr><th>Integratie</th><th>Status</th><th>Rol in v3</th></tr></thead><tbody>';
    foreach ( $plugins as $plugin => $label ) {
        $active = is_plugin_active( $plugin );
        $role = 'mvm-hub4-rc-direct/mvm-hub4.php' === $plugin ? 'Alleen bewezen services hergebruiken; geen oude UI' : 'Eigen data/permissies behouden';
        $html .= '<tr><td><strong>' . esc_html( $label ) . '</strong></td><td>' . esc_html( $active ? 'Actief' : 'Niet actief' ) . '</td><td>' . esc_html( $role ) . '</td></tr>';
    }
    $html .= '</tbody></table>';

    $services = array(
        array( 'Accountlogin', 'Platformservice', '/inloggen/', 'WordPress wp_signon + nonce + reCAPTCHA + throttling; geen Hub4-dashboard nodig.' ),
        array( 'Organisatieaccounts', 'v3-native', '/mijn-hub/?deel=organisatieaccount', 'Aanvraag en beoordeling zitten in Hubs v3 met private write-ahead audit.' ),
        array( 'Vacatures', 'v3-native', '/ondernemers-hub/?deel=vacature', 'Selfservice en stafreview gebruiken /mvm/v1; oude mvm-hub4/v1 alias is alleen staf/admin-compatibiliteit.' ),
        array( 'Evenementen', 'v3-native UI', '/ondernemers-hub/?deel=evenement', 'Hubs v3 beheert de workflow; WP Event Manager blijft de databron.' ),
        array( 'Smart Links', 'v3-native', '/beheer-hub/?deel=integraties', 'Volledige editor in Technische hub; legacy REST-alias blijft tijdelijk zonder Hub4-sessieafhankelijkheid.' ),
        array( 'Nieuwsradar / bronnen', 'v3-interface + Hub4 service', '/redactie-hub/?deel=nieuwsradar', 'Radar lezen/reviewen en bronmonitoring hebben een v3-ingang; crawler- en bronbeheerwrites blijven voorlopig op de bestaande servicedatalaag.' ),
        array( 'MvM Mail', 'v3-ingang + legacy mailservice', '/redactie-hub/?deel=mail', 'Eén zichtbare ingang in de Redactiehub; mailbox, folders, routing en trash-cleanup blijven ongewijzigd totdat hun writes afzonderlijk zijn gemigreerd.' ),
        array( 'Overige redactie-services', 'Hub4 service', '/redactie-hub/', 'Blijven servicedatalaag; niet verwijderen voordat iedere consument is gemigreerd.' ),
    );
    $html .= '<div class="mvmh3-section__head mvmh3-service-map__head"><div><span class="mvmh3-eyebrow">Uitfasering</span><h3>Welke onderdelen zijn al zelfstandig?</h3></div><p>Een legacy route blijft alleen bestaan als compatibiliteit. Hij mag nooit ruimere rechten hebben dan de canonieke v3-route.</p></div>';
    $html .= '<table class="mvmh3-table mvmh3-service-map"><thead><tr><th>Service</th><th>Status</th><th>Canonieke ingang</th><th>Grens</th></tr></thead><tbody>';
    foreach ( $services as $service ) {
        $state = 0 === strpos( $service[1], 'v3-native' ) ? 'ok' : ( 'Platformservice' === $service[1] ? 'platform' : 'legacy' );
        $html .= '<tr data-state="' . esc_attr( $state ) . '"><td><strong>' . esc_html( $service[0] ) . '</strong></td><td><span class="mvmh3-service-state">' . esc_html( $service[1] ) . '</span></td><td><code>' . esc_html( $service[2] ) . '</code></td><td>' . esc_html( $service[3] ) . '</td></tr>';
    }
    $html .= '</tbody></table>';

    if ( function_exists( 'mvm_smart_links_hub_permission_v1' ) && mvm_smart_links_hub_permission_v1() ) {
        $preview = mvm_hubs_v3_is_draft_preview();
        $html .= '<div class="mvmh3-callout"><strong>Smart Links · v3 beheer</strong><span>Beheer de automatische koppelingen van nieuws naar de Mierlose encyclopedie hier. De oude Hub4-route blijft alleen als technische compatibiliteitsalias bestaan.</span></div>';
        if ( $preview ) {
            $html .= '<div class="mvmh3-notice"><strong>Previewmodus</strong><span>Je kunt de actuele index, aliassen en testfunctie bekijken. Opslaan is in deze draft uitgeschakeld zodat productiedata niet verandert.</span></div>';
        }
        $html .= '<p class="mvmh3-smartlinks__status" data-mvmh3-smart-links-message role="status" aria-live="polite"></p><div data-mvmh3-smart-links aria-live="polite"></div>';
    }

    return $html;
}

function mvm_hubs_v3_recovery_html(): string {
    $themes = wp_get_themes();
    $snapshots = array();
    foreach ( $themes as $slug => $theme ) {
        if ( false !== strpos( (string) $slug, 'wpvibe-backup' ) && get_stylesheet() !== $slug ) {
            $snapshots[] = $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' );
        }
    }
    $snapshot_text = $snapshots ? implode( ', ', array_unique( $snapshots ) ) : 'Geen aparte theme-snapshot gevonden';
    $backup_ts            = (int) get_option( 'backuply_last_backup', 0 );
    $backup_label         = $backup_ts > 0 ? wp_date( 'd-m-Y H:i', $backup_ts ) : 'Niet gevonden';
    $backup_recent        = $backup_ts > 0 && ( time() - $backup_ts ) <= DAY_IN_SECONDS;
    $caps_schema          = (string) get_option( 'mvm_hubs_v3_caps_schema', '' );
    $caps_applied_release = (string) get_option( 'mvm_hubs_v3_caps_version', '' );
    $caps_schema_current  = '' !== $caps_schema && hash_equals( MVM_HUBS_V3_CAPS_SCHEMA, $caps_schema );
    $capability_health    = mvm_hubs_v3_capability_health();
    $capability_drift     = (int) ( $capability_health['drift_count'] ?? 0 );
    $preview              = mvm_hubs_v3_is_draft_preview();
    $caps_label           = $caps_schema ?: 'Niet opgeslagen';
    $mail_readonly        = function_exists( 'mvm_hubs_v3_mail_readonly_available' ) && mvm_hubs_v3_mail_readonly_available();
    $updates              = get_site_transient( 'update_plugins' );
    $update_count         = is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ? count( $updates->response ) : 0;
    $release_ready        = $backup_recent && ! empty( $snapshots ) && $mail_readonly && $caps_schema_current && 0 === $capability_drift;

    $html  = '<div class="mvmh3-callout"><strong>Herstelvolgorde</strong><span>1. Volledige siteback-up · 2. Theme-snapshot · 3. Draft preview · 4. Pas daarna publiceren.</span></div>';
    $html .= '<div class="mvmh3-review-summary__grid">';
    $html .= '<article><span>Release</span><strong>' . esc_html( MVM_HUBS_V3_VERSION ) . '</strong><small>' . esc_html( $preview ? 'Full Release Candidate' : 'Live runtime' ) . '</small></article>';
    $html .= '<article><span>Capability-schema live</span><strong>' . esc_html( $caps_label ) . '</strong><small>' . esc_html( $caps_schema_current ? 'Schema actueel' . ( $caps_applied_release ? ' · toegepast bij ' . $caps_applied_release : '' ) : 'Controle nodig' ) . '</small></article>';
    $html .= '<article><span>Capability-drift</span><strong>' . esc_html( 0 === $capability_drift ? '0' : (string) $capability_drift ) . '</strong><small>' . esc_html( 0 === $capability_drift ? 'Rollenmatrix intact' : 'Release blokkeren en rollen herstellen' ) . '</small></article>';
    $html .= '<article><span>Laatste Backuply-back-up</span><strong>' . esc_html( $backup_label ) . '</strong><small>' . esc_html( $backup_recent ? 'Minder dan 24 uur oud' : 'Maak vóór publicatie een verse back-up' ) . '</small></article>';
    $html .= '<article><span>Theme-snapshot</span><strong>' . esc_html( $snapshots ? 'Aanwezig' : 'Ontbreekt' ) . '</strong><small>' . esc_html( (string) count( $snapshots ) . ' herstelpunt(en) gevonden' ) . '</small></article>';
    $html .= '<article><span>MvM Mail</span><strong>' . esc_html( $mail_readonly ? 'Read-only gereed' : 'Controle nodig' ) . '</strong><small>V3 list/read zonder mailboxwrites</small></article>';
    $html .= '<article><span>Pluginupdates</span><strong>' . esc_html( (string) $update_count ) . '</strong><small>Niet mengen met een Hub-release zonder aparte compatibiliteitstest</small></article>';
    $html .= '</div>';
    $html .= '<div class="mvmh3-callout"><strong>' . esc_html( $release_ready ? 'Full Release-preflight technisch gereed' : 'Release nog niet vrijgeven' ) . '</strong><span>' . esc_html( $release_ready ? 'Back-up, herstelpunt, capability-schema, rollenmatrix en read-only Mail-basis zijn in orde. Publiceer pas na functionele previewcontrole en expliciete live-goedkeuring.' : 'Minimaal één verplichte herstel- of veiligheidsvoorwaarde ontbreekt.' ) . '</span></div>';
    $html .= '<table class="mvmh3-table"><tbody><tr><th>Actief theme</th><td>' . esc_html( wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ) ) . '</td></tr><tr><th>Theme-snapshot(s)</th><td>' . esc_html( $snapshot_text ) . '</td></tr><tr><th>Secure Core</th><td>' . esc_html( class_exists( 'MvM_Hub4_Hub_Security' ) ? 'Actief' : 'Niet actief — blijft geblokkeerd tot aparte toestemming' ) . '</td></tr><tr><th>Harde regel</th><td>Geen Secure Core-activatie, PR-merge of productiepublicatie zonder de afgesproken expliciete toestemming.</td></tr></tbody></table>';
    return $html;
}

function mvm_hubs_v3_maintenance_html(): string {
    $secure_ready = class_exists( 'MvM_Hubs_V3_Maintenance' );
    $disabled     = $secure_ready ? '' : ' disabled aria-disabled="true"';
    $html  = '<div class="mvmh3-callout"><strong>Bewust beperkt</strong><span>Hier staan alleen onderhoudsacties die geen berichten, gebruikers, plugins, thema&#8217;s of databasegegevens verwijderen.</span></div>';
    $html .= '<div class="mvmh3-maintenance" data-mvmh3-maintenance><article><div><strong>Hub-rechten synchroniseren</strong><span>Past uitsluitend de vaste Hubs v3-capabilitymatrix opnieuw toe.</span></div><button class="mvmh3-primary" type="button" data-mvmh3-maintenance-action="sync_hub_capabilities"' . $disabled . '>Synchroniseren</button></article><article><div><strong>Routes vernieuwen</strong><span>Bouwt alleen de WordPress rewrite-regels opnieuw op. Inhoud blijft onaangetast.</span></div><button class="mvmh3-primary" type="button" data-mvmh3-maintenance-action="flush_rewrites"' . $disabled . '>Routes vernieuwen</button></article><p class="mvmh3-maintenance__status" data-mvmh3-maintenance-status role="status" aria-live="polite">' . esc_html( $secure_ready ? 'Secure Core actief. Acties worden server-side gecontroleerd.' : 'Previewmodus: Secure Core is nog niet geactiveerd; onderhoudsknoppen blijven uit.' ) . '</p></div>';
    return $html;
}

function mvm_hubs_v3_organization_accounts_html(): string {
    $counts = count_users();
    $available = isset( $counts['avail_roles'] ) && is_array( $counts['avail_roles'] ) ? $counts['avail_roles'] : array();
    $rows = array(
        'mvm_vereniging' => array( 'Vereniging', 'Pagina, promoties, evenementen', 'Geen vacatures' ),
        'mvm_club' => array( 'Club', 'Pagina, promoties, evenementen', 'Geen vacatures' ),
        'mvm_organisator' => array( 'MvM Organisator', 'Pagina, promoties, evenementen', 'Geen vacatures' ),
        'mvm_ondernemer' => array( 'Ondernemer', 'Pagina, promoties, evenementen, vacatures', 'Commercieel' ),
        'mvm_bedrijf' => array( 'Bedrijf', 'Pagina, promoties, evenementen, vacatures', 'Commercieel' ),
        'mvm_winkelier' => array( 'Winkelier', 'Pagina, promoties, evenementen, vacatures', 'Commercieel' ),
        'organizer' => array( 'WP Event Manager Organisator', 'Alleen evenementen', 'Compatibiliteitsrol; geen promotie of pagina' ),
    );
    $html = '<div class="mvmh3-callout"><strong>Veilige toekenning</strong><span>Een gewoon Lid krijgt nooit automatisch organisatieprivileges. Controleer wie namens de organisatie handelt en kies daarna precies één passend MvM-accounttype. De generieke WP Event Manager-rol blijft event-only.</span></div>';
    $html .= '<table class="mvmh3-table"><thead><tr><th>Accounttype</th><th>Accounts</th><th>Mogelijkheden</th><th>Grens</th></tr></thead><tbody>';
    foreach ( $rows as $role_key => $row ) {
        $html .= '<tr><td><strong>' . esc_html( $row[0] ) . '</strong><br><small>' . esc_html( $role_key ) . '</small></td><td>' . absint( $available[ $role_key ] ?? 0 ) . '</td><td>' . esc_html( $row[1] ) . '</td><td>' . esc_html( $row[2] ) . '</td></tr>';
    }
    $html .= '</tbody></table><div class="mvmh3-callout"><strong>Aanvraagroute</strong><span>Gebruikers vragen een organisatieaccount aan vanuit Mijn Mierlo. De aanvraag geeft geen rechten. SysOp/Administrator controleert en keurt het accounttype bewust goed of af. Zelfpromotie is nooit toegestaan.</span></div>';
    $html .= '<div class="mvmh3-section__head mvmh3-vacancies__list-head"><div><span class="mvmh3-eyebrow">Open aanvragen</span><h3>Te beoordelen organisatieaccounts</h3></div><p>Goedkeuren wijzigt alleen de allowlisted MvM-organisatierol en wordt vooraf privaat geaudit.</p></div>';
    if ( function_exists( 'mvm_hubs_v3_org_requests_admin_html' ) ) {
        $html .= mvm_hubs_v3_org_requests_admin_html();
    }
    return $html;
}

function mvm_hubs_v3_admin_content(): string {
    global $wp_version;
    $theme = wp_get_theme();
    $html  = '<section class="mvmh3-intro"><div><span class="mvmh3-eyebrow">Technisch beheer</span><h1>Onderhoud met grenzen, controles en terugvalpunten.</h1><p>Deze hub is alleen voor SysOp/Administrator. Instellingen worden per domein gegroepeerd zodat een wijziging nooit ongemerkt de hele site raakt.</p></div><span class="mvmh3-security-badge">Hoog privilege</span></section>';
    $secure_version = defined( 'MVM_HUBS_V3_SECURE_VERSION' ) ? MVM_HUBS_V3_SECURE_VERSION : 'Niet actief · theme-fallback';
    $cap_health     = mvm_hubs_v3_capability_health();
    $cap_label      = ! empty( $cap_health['ok'] )
        ? 'In orde · ' . absint( $cap_health['roles_checked'] ) . ' rollen'
        : absint( $cap_health['drift_count'] ) . ' afwijkingen';
    $cap_state      = ! empty( $cap_health['ok'] ) ? 'ok' : 'warning';
    $org_pending    = function_exists( 'mvm_hubs_v3_org_request_pending_count' ) ? mvm_hubs_v3_org_request_pending_count() : 0;
    $html .= '<div class="mvmh3-status" data-mvmh3-admin-status><div><span>WordPress</span><strong data-mvmh3-status="wordpress">' . esc_html( $wp_version ) . '</strong></div><div><span>Thema</span><strong data-mvmh3-status="theme">' . esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ) . '</strong></div><div><span>Hub-UI</span><strong>' . esc_html( MVM_HUBS_V3_VERSION ) . '</strong></div><div><span>Capability-schema</span><strong>' . esc_html( MVM_HUBS_V3_CAPS_SCHEMA ) . '</strong></div><div><span>Secure Core</span><strong data-mvmh3-status="secure">' . esc_html( $secure_version ) . '</strong></div><div><span>Rechtenmatrix</span><strong data-mvmh3-status="capabilities" data-state="' . esc_attr( $cap_state ) . '">' . esc_html( $cap_label ) . '</strong></div></div>';
    $html .= '<div class="mvmh3-grid">';
    $html .= mvm_hubs_v3_card( 'shield', 'Veiligheid & rechten', 'Controleer rollen, toegang, sessies en gevoelige functies vanuit één beveiligingsdomein.', home_url( '/beheer-hub/?deel=veiligheid' ), 'Open veiligheid' );
    $html .= mvm_hubs_v3_card( 'settings', 'Integraties', 'PeepSo, wpForo, WP Event Manager en externe koppelingen ieder apart beheren en testen.', home_url( '/beheer-hub/?deel=integraties' ), 'Open integraties' );
    $html .= mvm_hubs_v3_card( 'settings', 'Onderhoud', 'Voer alleen allowlisted, niet-destructieve onderhoudstaken uit met auditlogging.', home_url( '/beheer-hub/?deel=onderhoud' ), 'Onderhoud openen' );
    $html .= mvm_hubs_v3_card( 'newspaper', 'Layoutbibliotheek', 'Bekijk alle nieuwe nieuws- en evenementenlayouts met echte actuele content voordat je ze op Home gebruikt.', home_url( '/beheer-hub/?deel=layouts' ), 'Layouts bekijken' );
    $html .= mvm_hubs_v3_card( 'check', 'Home-blokken', 'Zet extra nieuws- en evenementenblokken aan of uit en kies per blok een vaste layout.', home_url( '/beheer-hub/?deel=homeblokken' ), 'Home inrichten' );
    $html .= mvm_hubs_v3_card( 'bookmark', 'Back-up & herstel', 'Toon het laatste herstelpunt vóór grote wijzigingen en leg vast wat er sindsdien is aangepast.', home_url( '/beheer-hub/?deel=herstel' ), 'Herstelpunten' );
    $html .= mvm_hubs_v3_card( 'group', 'Organisatieaccounts', 'Bekijk accounttypen, grenzen en open aanvragen. Alleen gecontroleerde goedkeuring kan extra rechten activeren.', home_url( '/beheer-hub/?deel=organisaties' ), 'Aanvragen bekijken', $org_pending > 0 ? $org_pending . ' open aanvraag' . ( 1 === $org_pending ? '' : 'en' ) : 'Geen open aanvragen' );
    $html .= '</div>';

    $deel = isset( $_GET['deel'] ) ? sanitize_key( wp_unslash( $_GET['deel'] ) ) : '';
    if ( 'layouts' === $deel ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Bibliotheek</span><h2>Nieuws- en evenementenlayouts</h2></div><p>Dit zijn dezelfde v3-componenten die later als extra Home-blok gebruikt kunnen worden. Geen demo-afbeeldingen: de voorbeelden gebruiken actuele sitecontent.</p></div>';
        if ( function_exists( 'mvm_cb3_render' ) ) {
            $html .= '<div class="mvmh3-layout-preview"><h3>Nieuws · 1 × 2</h3>' . mvm_cb3_render( 'news', array( 'layout' => '1x2', 'limit' => 2, 'title' => 'Twee belangrijke verhalen' ) ) . '<h3>Nieuws · 3 × 2</h3>' . mvm_cb3_render( 'news', array( 'layout' => '3x2', 'limit' => 6, 'title' => 'Meer uit Mierlo' ) ) . '<h3>Nieuws · hoofdverhaal + 4</h3>' . mvm_cb3_render( 'news', array( 'layout' => 'lead-4', 'limit' => 5, 'title' => 'Uitgelicht' ) ) . '<h3>Evenementen · 1 × 2</h3>' . mvm_cb3_render( 'events', array( 'layout' => '1x2', 'limit' => 2, 'title' => 'Binnenkort' ) ) . '<h3>Evenementen · 3 × 2</h3>' . mvm_cb3_render( 'events', array( 'layout' => '3x2', 'limit' => 6, 'title' => 'Agenda uit Mierlo' ) ) . '<h3>Evenementen · compacte lijst</h3>' . mvm_cb3_render( 'events', array( 'layout' => 'compact-list', 'limit' => 5, 'title' => 'Snel overzicht' ) ) . '</div>';
        } else {
            $html .= '<div class="mvmh3-empty"><strong>Layoutmodule niet geladen.</strong><span>De technische hub blokkeert de preview zolang de v3-contentmodule niet actief is.</span></div>';
        }
        $html .= '</section>';
    } elseif ( 'homeblokken' === $deel ) {
        $saved = isset( $_GET['saved'] ) && '1' === sanitize_key( wp_unslash( $_GET['saved'] ) );
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Startpagina</span><h2>Extra contentblokken</h2></div><p>Maximaal vier aanvullende blokken. Ze gebruiken alleen de nieuwe v3-layouts en staan los van de bestaande homepage-opbouw.</p></div>';
        if ( $saved ) {
            $html .= '<div class="mvmh3-notice mvmh3-notice--success"><strong>Instellingen opgeslagen.</strong><span>Alleen ingeschakelde blokken worden op Home toegevoegd.</span></div>';
        }
        $html .= mvm_hubs_v3_home_blocks_form();
        $html .= '</section>';
    } elseif ( 'veiligheid' === $deel ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Beveiligingsdomein</span><h2>Toegang wordt server-side bepaald</h2></div><p>Deze hub toont geen secrets. Rollen, sessies en gevoelige mutaties krijgen afzonderlijke controles en auditlogging.</p></div><div class="mvmh3-callout"><strong>Harde regel</strong><span>Een knop verbergen is nooit genoeg. Elke toekomstige actie moet opnieuw autoriseren op de server.</span></div>' . mvm_hubs_v3_security_matrix_html() . '</section>';
    } elseif ( 'integraties' === $deel ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Integraties</span><h2>Bronnen blijven gescheiden</h2></div><p>PeepSo, wpForo en WP Event Manager houden hun eigen data en permissies. De hub orkestreert, maar wordt geen tweede databron.</p></div>' . mvm_hubs_v3_integrations_html() . '</section>';
    } elseif ( 'onderhoud' === $deel ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Onderhoud</span><h2>Kleine, controleerbare technische acties</h2></div><p>Geen pluginupdates, theme-updates, database-reparaties of verwijderacties vanuit dit scherm. Alleen vooraf gedefinieerde veilige taken.</p></div>' . mvm_hubs_v3_maintenance_html() . '</section>';
    } elseif ( 'herstel' === $deel ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Herstelbeleid</span><h2>Eerst terug kunnen, dan pas veranderen</h2></div><p>Voor structurele wijzigingen blijft een volledige siteback-up én een theme-snapshot verplicht. Het MvM-thema wordt nooit overschreven.</p></div>' . mvm_hubs_v3_recovery_html() . '</section>';
    } elseif ( 'organisaties' === $deel ) {
        $html .= '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Organisatieaccounts</span><h2>Eén passend accounttype per organisatie</h2></div><p>Bekijk de organisatieprofielen en hun grenzen. Extra rechten worden alleen na controle door MvM toegekend.</p></div>' . mvm_hubs_v3_organization_accounts_html() . '</section>';
    }
    return $html;
}

function mvm_hubs_v3_shell( string $hub ): void {
    $routes = mvm_hubs_v3_routes();
    $route  = $routes[ $hub ] ?? null;
    if ( ! $route ) {
        return;
    }

    get_header();
    echo '<main id="content" class="mvmh3" data-mvm-hub-v3="' . esc_attr( $hub ) . '"><div class="mvmh3-shell">';
    echo '<aside class="mvmh3-sidebar"><div class="mvmh3-sidebar__brand"><span class="mvmh3-sidebar__icon">' . mvm_hubs_v3_icon( $route['icon'] ) . '</span><div><strong>' . esc_html( $route['title'] ) . '</strong><span>' . esc_html( $route['label'] ) . '</span></div></div><nav aria-label="Jouw werkruimtes">';
    $nav_order = array_values( array_unique( array( $hub, 'user', 'business', 'editorial', 'admin' ) ) );
    foreach ( $nav_order as $key ) {
        if ( ! isset( $routes[ $key ] ) || ! mvm_hubs_v3_can_access( $key ) ) {
            continue;
        }
        $item = $routes[ $key ];
        echo '<a class="' . ( $key === $hub ? 'is-current' : '' ) . '" href="' . esc_url( home_url( $item['path'] ) ) . '"><span>' . mvm_hubs_v3_icon( $item['icon'] ) . '</span>' . esc_html( $item['title'] ) . '</a>';
    }
    echo '</nav><div class="mvmh3-sidebar__help"><strong>Jouw werkruimtes</strong><span>Je ziet alleen de onderdelen die bij jouw account horen.</span><a href="#mvm-hulp">Hulp & FAQ</a></div></aside>';
    echo '<div class="mvmh3-main">';
    if ( 'user' === $hub ) {
        echo mvm_hubs_v3_user_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ( 'business' === $hub ) {
        echo mvm_hubs_v3_business_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ( 'editorial' === $hub ) {
        echo mvm_hubs_v3_editorial_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } elseif ( 'admin' === $hub ) {
        echo mvm_hubs_v3_admin_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    if ( function_exists( 'mvm_hubs_v3_help_html' ) ) {
        echo mvm_hubs_v3_help_html( $hub ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    echo '</div></div></main>';
    get_footer();
}

function mvm_hubs_v3_private_headers(): void {
    if ( class_exists( 'MvM_Hub4_Hub_Security' ) ) {
        return;
    }
    $hub = mvm_hubs_v3_current_hub();
    if ( ! $hub ) {
        return;
    }
    nocache_headers();
    header( 'Cache-Control: private, no-store, max-age=0, must-revalidate' );
    header( 'Pragma: no-cache' );
    header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
    header( 'Referrer-Policy: same-origin', true );
    header( 'X-Frame-Options: SAMEORIGIN', true );
    header( 'X-Content-Type-Options: nosniff', true );
}
add_action( 'send_headers', 'mvm_hubs_v3_private_headers', 20 );

function mvm_hubs_v3_is_legacy_mail_request(): bool {
    $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
    return '/hub/' === mvm_hubs_v3_request_path() && 'mail' === $tab;
}

function mvm_hubs_v3_legacy_mail_guard(): void {
    if ( ! mvm_hubs_v3_is_legacy_mail_request() ) {
        return;
    }

    nocache_headers();
    header( 'Cache-Control: private, no-store, max-age=0, must-revalidate' );
    header( 'Pragma: no-cache' );
    header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
    header( 'Referrer-Policy: same-origin', true );
    header( 'X-Content-Type-Options: nosniff', true );

    if ( ! is_user_logged_in() ) {
        return;
    }

    if ( ! mvm_hubs_v3_mail_rest_permission() ) {
        wp_die( esc_html__( 'Geen toegang tot MvM Mail.', 'mvm' ), '', array( 'response' => 403 ) );
    }
}
add_action( 'template_redirect', 'mvm_hubs_v3_legacy_mail_guard', 0 );

function mvm_hubs_v3_template_router(): void {
    $hub = mvm_hubs_v3_current_hub();
    if ( ! $hub ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        mvm_hubs_v3_remember_login_target( $hub );
        wp_safe_redirect( wp_login_url( mvm_hubs_v3_login_target( $hub ) ) );
        exit;
    }

    if ( ! mvm_hubs_v3_can_access( $hub ) ) {
        status_header( 403 );
        get_header();
        echo '<main id="content" class="mvmh3"><div class="mvmh3-denied"><span class="mvmh3-denied__icon">' . mvm_hubs_v3_icon( 'shield' ) . '</span><h1>Geen toegang tot deze hub</h1><p>Deze werkruimte bevat functies of informatie die niet bij jouw accountrol horen.</p><a class="mvmh3-primary" href="' . esc_url( home_url( '/mijn-hub/' ) ) . '">Naar Mijn Mierlo</a></div></main>';
        get_footer();
        exit;
    }

    status_header( 200 );
    mvm_hubs_v3_shell( $hub );
    exit;
}
add_action( 'template_redirect', 'mvm_hubs_v3_template_router', 0 );

function mvm_hubs_v3_body_class( array $classes ): array {
    $hub = mvm_hubs_v3_current_hub();
    if ( $hub ) {
        $classes[] = 'mvm-hub-v3-page';
        $classes[] = 'mvm-hub-v3-' . sanitize_html_class( $hub );
    }
    return $classes;
}
add_filter( 'body_class', 'mvm_hubs_v3_body_class', 50 );

function mvm_hubs_v3_assets(): void {
    $show_quick = is_user_logged_in();
    $hub        = mvm_hubs_v3_current_hub();
    if ( ! $show_quick && ! $hub ) {
        return;
    }

    $css = MVM_HUB4_DIR . 'assets/mvm-news-redactie-hub.css';
    $js  = MVM_HUB4_DIR . 'assets/mvm-news-redactie-hub.js';
    if ( is_readable( $css ) ) {
        wp_enqueue_style( 'mvm-hubs-v3', MVM_HUB4_URL . 'assets/mvm-news-redactie-hub.css', array( 'mvm-global-style' ), (string) filemtime( $css ) );
    }
    if ( is_readable( $js ) ) {
        wp_enqueue_script( 'mvm-hubs-v3', MVM_HUB4_URL . 'assets/mvm-news-redactie-hub.js', array(), (string) filemtime( $js ), true );
        if ( $hub && class_exists( 'MvM_Hub4_Hub_Security' ) ) {
            wp_localize_script(
                'mvm-hubs-v3',
                'MvMHubsV3Bridge',
                array(
                    'secure'   => true,
                    'hub'      => $hub,
                    'restRoot' => esc_url_raw( rest_url( 'mvm-hubs/v3/' ) ),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                )
            );
        }
        if ( 'business' === $hub && mvm_hubs_v3_can_access( 'business' ) ) {
            wp_localize_script(
                'mvm-hubs-v3',
                'MvMHubsV3Business',
                array(
                    'restRoot' => esc_url_raw( rest_url( 'mvm/v1/entrepreneur/' ) ),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                )
            );
        }
        if ( 'editorial' === $hub && mvm_hubs_v3_can_manage_vacancies() ) {
            wp_localize_script(
                'mvm-hubs-v3',
                'MvMHubsV3VacancyReview',
                array(
                    'restRoot' => esc_url_raw( rest_url( 'mvm/v1/staff/' ) ),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                )
            );
        }
        $editorial_part = isset( $_GET['deel'] ) ? sanitize_key( wp_unslash( $_GET['deel'] ) ) : '';
        if ( 'editorial' === $hub && 'mail' === $editorial_part && ! mvm_hubs_v3_is_draft_preview() && mvm_hubs_v3_mail_rest_permission() && mvm_hubs_v3_mail_readonly_available() ) {
            wp_localize_script(
                'mvm-hubs-v3',
                'MvMHubsV3MailReadOnly',
                array(
                    'restRoot' => esc_url_raw( rest_url( 'mvm/v1/editorial/mail/' ) ),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                )
            );
        }
        $admin_part = isset( $_GET['deel'] ) ? sanitize_key( wp_unslash( $_GET['deel'] ) ) : '';
        if ( 'admin' === $hub && 'integraties' === $admin_part && mvm_hubs_v3_can_access( 'admin' ) ) {
            $smart_js = get_stylesheet_directory() . '/mvm-smart-links-hub.js';
            if ( is_readable( $smart_js ) ) {
                wp_enqueue_script( 'mvm-smart-links-v3', get_stylesheet_directory_uri() . '/mvm-smart-links-hub.js', array( 'mvm-hubs-v3' ), (string) filemtime( $smart_js ), true );
                wp_localize_script(
                    'mvm-smart-links-v3',
                    'MvMHubs3SmartLinksConfig',
                    array(
                        'restRoot'  => esc_url_raw( rest_url( 'mvm/v1/admin/' ) ),
                        'restNonce' => wp_create_nonce( 'wp_rest' ),
                        'readOnly'  => mvm_hubs_v3_is_draft_preview(),
                    )
                );
            }
        }
    }
}
add_action( 'wp_enqueue_scripts', 'mvm_hubs_v3_assets', 2500 );

function mvm_hubs_v3_quick_panel(): void {
    if ( ! is_user_logged_in() || mvm_hubs_v3_current_hub() ) {
        return;
    }
    $routes  = mvm_hubs_v3_routes();
    $primary = mvm_hubs_v3_primary_hub();
    echo '<section class="mvmh3-quick" data-mvmh3-quick><div class="mvm-header-inner"><button class="mvmh3-quick__toggle" type="button" data-mvmh3-toggle aria-expanded="false" aria-controls="mvmh3-quick-panel"><span>' . mvm_hubs_v3_icon( 'user' ) . '</span><strong>Mijn Mierlo</strong><small>Persoonlijk & werk</small><span class="mvmh3-quick__chevron" aria-hidden="true">⌄</span></button><div id="mvmh3-quick-panel" class="mvmh3-quick__panel" hidden><div class="mvmh3-quick__links">';
    echo '<a href="' . esc_url( home_url( '/mijn-hub/' ) ) . '"><span>' . mvm_hubs_v3_icon( 'user' ) . '</span>Mijn overzicht</a>';
    echo '<a href="' . esc_url( home_url( '/activity/' ) ) . '"><span>' . mvm_hubs_v3_icon( 'activity' ) . '</span>Activiteit</a>';
    echo '<a href="' . esc_url( home_url( '/messages/' ) ) . '"><span>' . mvm_hubs_v3_icon( 'newspaper' ) . '</span>Berichten</a>';
    echo '<a href="' . esc_url( home_url( '/notifications/' ) ) . '"><span>' . mvm_hubs_v3_icon( 'check' ) . '</span>Meldingen</a>';
    echo '<a href="' . esc_url( home_url( '/forum/' ) ) . '"><span>' . mvm_hubs_v3_icon( 'forum' ) . '</span>Forum</a>';
    echo '<a href="' . esc_url( home_url( '/mijn-hub/?deel=opgeslagen' ) ) . '"><span>' . mvm_hubs_v3_icon( 'bookmark' ) . '</span>Opgeslagen</a>';

    $work_labels = array( 'business' => 'Organisatiehub', 'editorial' => 'Redactiehub', 'admin' => 'Technische hub' );
    $has_workhub = isset( $work_labels[ $primary ] ) && mvm_hubs_v3_can_access( $primary );
    if ( $has_workhub ) {
        echo '<a class="mvmh3-quick__role" href="' . esc_url( home_url( $routes[ $primary ]['path'] ) ) . '"><span>' . mvm_hubs_v3_icon( $routes[ $primary ]['icon'] ) . '</span>Mijn werkhub · ' . esc_html( $work_labels[ $primary ] ) . '</a>';
    }
    foreach ( $work_labels as $key => $label ) {
        if ( $key !== $primary && mvm_hubs_v3_can_access( $key ) ) {
            echo '<a class="mvmh3-quick__secondary" href="' . esc_url( home_url( $routes[ $key ]['path'] ) ) . '"><span>' . mvm_hubs_v3_icon( $routes[ $key ]['icon'] ) . '</span>' . esc_html( $label ) . '</a>';
        }
    }
    $hint = $has_workhub
        ? 'Je hoofdwerkhub staat blauw. Extra beheer verschijnt alleen wanneer je daar toegang toe hebt.'
        : 'Hier staan alleen jouw persoonlijke snelkoppelingen.';
    echo '</div><p class="mvmh3-quick__hint">' . esc_html( $hint ) . '</p></div></div></section>';
}
add_action( 'wp_body_open', 'mvm_hubs_v3_quick_panel', 8 );

function mvm_hubs_v3_register_rest(): void {
    register_rest_route(
        'mvm-hubs/v3',
        '/context',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => static function (): bool {
                return is_user_logged_in();
            },
            'callback'            => static function (): WP_REST_Response {
                $allowed = array( 'user' );
                foreach ( array( 'business', 'editorial', 'admin' ) as $hub ) {
                    if ( mvm_hubs_v3_can_access( $hub ) ) {
                        $allowed[] = $hub;
                    }
                }
                $sections = array(
                    'user' => array( 'organisatieaccount', 'opgeslagen' ),
                );
                if ( in_array( 'business', $allowed, true ) ) {
                    $business_tasks = mvm_hubs_v3_business_tasks();
                    $sections['business'] = array();
                    if ( $business_tasks['company'] ) {
                        $sections['business'][] = 'profiel';
                    }
                    if ( $business_tasks['vacancies'] ) {
                        $sections['business'][] = 'vacature';
                    }
                    if ( $business_tasks['events'] ) {
                        $sections['business'][] = 'evenement';
                    }
                }
                if ( in_array( 'editorial', $allowed, true ) ) {
                    $sections['editorial'] = array();
                    if ( mvm_hubs_v3_can_submit_editorial() ) {
                        $sections['editorial'][] = 'mijnwerk';
                    }
                    if ( mvm_hubs_v3_can_review() ) {
                        $sections['editorial'][] = 'review';
                    }
                    if ( mvm_hubs_v3_can_view_newsradar() ) {
                        $sections['editorial'][] = 'nieuwsradar';
                    }
                    if ( mvm_hubs_v3_can_view_sources() ) {
                        $sections['editorial'][] = 'bronnen';
                    }
                    if ( mvm_hubs_v3_mail_rest_permission() ) {
                        $sections['editorial'][] = 'mail';
                    }
                    if ( mvm_hubs_v3_can_manage_vacancies() ) {
                        $sections['editorial'][] = 'vacatures';
                    }
                    if ( mvm_hubs_v3_can_view_team_planning() ) {
                        $sections['editorial'][] = 'planning';
                    }
                    $sections['editorial'][] = 'team';
                    $sections['editorial'][] = 'communicatie';
                    if ( mvm_hubs_v3_can_moderate() ) {
                        $sections['editorial'][] = 'moderatie';
                    }
                }
                if ( in_array( 'admin', $allowed, true ) ) {
                    $sections['admin'] = array( 'layouts', 'homeblokken', 'veiligheid', 'integraties', 'onderhoud', 'herstel', 'organisaties' );
                }
                $response = new WP_REST_Response(
                    array(
                        'version'     => MVM_HUBS_V3_VERSION,
                        'primary_hub' => mvm_hubs_v3_primary_hub(),
                        'hubs'        => $allowed,
                        'sections'    => $sections,
                        'permissions' => array(
                            'submit_editorial' => mvm_hubs_v3_can_submit_editorial(),
                            'review'           => mvm_hubs_v3_can_review(),
                            'moderate'         => mvm_hubs_v3_can_moderate(),
                            'team_planning'    => mvm_hubs_v3_can_view_team_planning(),
                        ),
                    ),
                    200
                );
                $response->header( 'Cache-Control', 'private, no-store, max-age=0, must-revalidate' );
                return $response;
            },
        )
    );
}

function mvm_hubs_v3_register_mail_rest(): void {
    register_rest_route(
        'mvm/v1',
        '/editorial/mail/messages',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => 'mvm_hubs_v3_mail_rest_permission',
            'callback'            => 'mvm_hubs_v3_mail_rest_list',
            'args'                => array(
                'limit' => array(
                    'default'           => 30,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static function ( $value ): bool {
                        $value = absint( $value );
                        return $value >= 1 && $value <= 50;
                    },
                ),
                'offset' => array(
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ),
            ),
        )
    );
    register_rest_route(
        'mvm/v1',
        '/editorial/mail/messages/(?P<uid>\d+)',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => 'mvm_hubs_v3_mail_rest_permission',
            'callback'            => 'mvm_hubs_v3_mail_rest_read',
            'args'                => array(
                'uid' => array(
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static function ( $value ): bool {
                        return absint( $value ) > 0;
                    },
                ),
            ),
        )
    );
}

add_action( 'rest_api_init', 'mvm_hubs_v3_register_mail_rest' );
if ( ! class_exists( 'MvM_Hub4_Hub_Security' ) ) {
    add_action( 'rest_api_init', 'mvm_hubs_v3_register_rest' );
}

