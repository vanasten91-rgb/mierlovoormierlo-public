<?php
/**
 * MvM Evenementen 2.0, Organisatorenhub en Ondernemershub.
 * De bestaande WP Event Manager-data blijft brondata; presentatie en portals zijn nieuw.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mvm_e2_preview_runtime() {
    return false !== strpos( wp_normalize_path( get_stylesheet_directory() ), 'wpvibe-draft' );
}

function mvm_e2_roles( $user = null ) {
    $user = $user instanceof WP_User ? $user : wp_get_current_user();
    return array_values( (array) $user->roles );
}

function mvm_e2_is_staff( $user = null ) {
    return (bool) array_intersect(
        array( 'administrator', 'mvm_sysop', 'mvm_teamleider', 'mvm_editor', 'mvm_redacteur' ),
        mvm_e2_roles( $user )
    );
}

function mvm_e2_is_organizer( $user = null ) {
    return mvm_e2_is_staff( $user ) || in_array( 'mvm_organisator', mvm_e2_roles( $user ), true );
}

function mvm_e2_is_entrepreneur( $user = null ) {
    return mvm_e2_is_staff( $user ) || in_array( 'mvm_ondernemer', mvm_e2_roles( $user ), true );
}

/**
 * Rollen pas in live runtime aanmaken. Een WPVibe-preview mag geen databasewijziging veroorzaken.
 */
function mvm_e2_register_roles() {
    if ( mvm_e2_preview_runtime() ) {
        return;
    }
    if ( ! get_role( 'mvm_organisator' ) ) {
        add_role( 'mvm_organisator', 'MvM Organisator', array( 'read' => true, 'upload_files' => true ) );
    }
    if ( ! get_role( 'mvm_ondernemer' ) ) {
        add_role( 'mvm_ondernemer', 'MvM Ondernemer', array( 'read' => true, 'upload_files' => true ) );
    }
}
add_action( 'init', 'mvm_e2_register_roles', 30 );

function mvm_e2_register_ad_type() {
    register_post_type(
        'mvm_advertentie',
        array(
            'labels' => array( 'name' => 'Advertenties', 'singular_name' => 'Advertentie' ),
            'public' => false,
            'show_ui' => mvm_e2_is_staff(),
            'show_in_menu' => mvm_e2_is_staff(),
            'show_in_rest' => false,
            'exclude_from_search' => true,
            'supports' => array( 'title', 'editor', 'thumbnail', 'author' ),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        )
    );
}
add_action( 'init', 'mvm_e2_register_ad_type', 12 );

/**
 * Alleen ondernemers en staf mogen de technische PeepSo-pagina-aanmaak zien.
 * In de UI heet dit altijd "MvM-pagina".
 */
function mvm_e2_peepso_pages_permission( $config ) {
    if ( ! is_array( $config ) ) {
        return $config;
    }
    $config['pages_creation_enabled'] = ( is_user_logged_in() && mvm_e2_is_entrepreneur() ) ? 1 : 0;
    return $config;
}
add_filter( 'option_peepso_config', 'mvm_e2_peepso_pages_permission', 25 );

function mvm_e2_login_redirect( $redirect_to, $requested, $user ) {
    if ( ! $user instanceof WP_User ) {
        return home_url( '/' );
    }
    if ( mvm_e2_is_staff( $user ) ) {
        return home_url( '/' );
    }
    $roles = mvm_e2_roles( $user );
    if ( in_array( 'mvm_organisator', $roles, true ) ) {
        return home_url( '/organisatoren/' );
    }
    if ( in_array( 'mvm_ondernemer', $roles, true ) ) {
        return home_url( '/ondernemers/' );
    }
    return home_url( '/' );
}
add_filter( 'login_redirect', 'mvm_e2_login_redirect', 100, 3 );

/**
 * PeepSo verwerkt de header-login via zijn eigen AJAX-route en onthoudt daarbij
 * standaard de HTTP-referrer als "laatst bezochte pagina". Daardoor kon een
 * login vanaf een nieuwsbericht alsnog terugveren naar dat bericht, ook wanneer
 * het formulier zelf redirect_to=/ bevatte. Voor de MvM-loginflow is de
 * startpagina de canonieke fallback.
 */
function mvm_e2_peepso_ajax_login_target() {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    if ( false === strpos( $request_uri, '/peepsoajax/' ) ) {
        return;
    }

    $option = isset( $_POST['option'] ) ? sanitize_key( wp_unslash( $_POST['option'] ) ) : '';
    $task   = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
    if ( 'ps_users' !== $option || false === strpos( $task, 'user-login' ) ) {
        return;
    }

    $target = home_url( '/' );
    $_POST['redirect_to']    = $target;
    $_REQUEST['redirect_to'] = $target;
    $_SERVER['HTTP_REFERER'] = $target;
}
add_action( 'wp_loaded', 'mvm_e2_peepso_ajax_login_target', 1 );

/**
 * Zodra WordPress de gebruiker kent, verfijn de PeepSo-doelroute alleen voor
 * de twee afgescheiden portaalrollen. Alle overige accounts blijven naar Home.
 */
function mvm_e2_peepso_role_login_target( $user_login, $user ) {
    if ( ! $user instanceof WP_User ) {
        return;
    }

    $roles  = mvm_e2_roles( $user );
    $target = home_url( '/' );
    if ( ! mvm_e2_is_staff( $user ) && in_array( 'mvm_organisator', $roles, true ) ) {
        $target = home_url( '/organisatoren/' );
    } elseif ( ! mvm_e2_is_staff( $user ) && in_array( 'mvm_ondernemer', $roles, true ) ) {
        $target = home_url( '/ondernemers/' );
    }

    $_POST['redirect_to']    = $target;
    $_REQUEST['redirect_to'] = $target;
    $_SERVER['HTTP_REFERER'] = $target;
}
add_action( 'wp_login', 'mvm_e2_peepso_role_login_target', 999, 2 );

function mvm_e2_hide_admin_bar( $show ) {
    if ( mvm_e2_is_staff() ) {
        return $show;
    }
    $roles = mvm_e2_roles();
    return ( in_array( 'mvm_organisator', $roles, true ) || in_array( 'mvm_ondernemer', $roles, true ) ) ? false : $show;
}
add_filter( 'show_admin_bar', 'mvm_e2_hide_admin_bar', 30 );

function mvm_e2_portal_admin_guard() {
    if ( ! is_user_logged_in() || wp_doing_ajax() || mvm_e2_is_staff() ) {
        return;
    }
    $script = isset( $_SERVER['PHP_SELF'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) ) : '';
    if ( 'admin-post.php' === $script ) {
        return;
    }
    $roles = mvm_e2_roles();
    if ( in_array( 'mvm_organisator', $roles, true ) ) {
        wp_safe_redirect( home_url( '/organisatoren/' ) );
        exit;
    }
    if ( in_array( 'mvm_ondernemer', $roles, true ) ) {
        wp_safe_redirect( home_url( '/ondernemers/' ) );
        exit;
    }
}
add_action( 'admin_init', 'mvm_e2_portal_admin_guard', 1 );

function mvm_e2_enqueue_assets() {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    if ( ! is_page( 'evenementen' ) && ! is_singular( 'event_listing' ) && 0 !== strpos( $uri, '/organisatoren/' ) && 0 !== strpos( $uri, '/ondernemers/' ) ) {
        return;
    }
    $file = get_stylesheet_directory() . '/mvm-events-2.css';
    wp_enqueue_style( 'mvm-events-2', get_stylesheet_directory_uri() . '/mvm-events-2.css', array(), file_exists( $file ) ? filemtime( $file ) : null );
}
add_action( 'wp_enqueue_scripts', 'mvm_e2_enqueue_assets', 80 );

function mvm_e2_meta( $post_id, $key, $fallback = '' ) {
    $value = get_post_meta( $post_id, $key, true );
    if ( is_array( $value ) ) {
        $value = reset( $value );
    }
    return '' !== trim( (string) $value ) ? $value : $fallback;
}

function mvm_e2_ts( $post_id, $key ) {
    $raw = mvm_e2_meta( $post_id, $key );
    if ( ! $raw ) {
        return 0;
    }
    try {
        return ( new DateTimeImmutable( $raw, wp_timezone() ) )->getTimestamp();
    } catch ( Exception $e ) {
        return 0;
    }
}

function mvm_e2_date_label( $post_id, $compact = false ) {
    $start = mvm_e2_ts( $post_id, '_event_start_date' );
    $end = mvm_e2_ts( $post_id, '_event_end_date' );
    if ( ! $start ) {
        return 'Datum volgt';
    }
    $format = $compact ? 'D j M' : 'l j F Y';
    $label = ucfirst( wp_date( $format, $start, wp_timezone() ) );
    if ( $end && wp_date( 'Y-m-d', $start, wp_timezone() ) !== wp_date( 'Y-m-d', $end, wp_timezone() ) ) {
        $label .= ' – ' . ucfirst( wp_date( $format, $end, wp_timezone() ) );
    }
    return $label;
}

function mvm_e2_time_label( $post_id ) {
    if ( '1' === (string) get_post_meta( $post_id, '_mvm_all_day', true ) ) {
        return 'Hele dag';
    }
    $start = mvm_e2_meta( $post_id, '_event_start_time' );
    $end = mvm_e2_meta( $post_id, '_event_end_time' );
    if ( ! $start ) {
        $ts = mvm_e2_ts( $post_id, '_event_start_date' );
        $start = $ts ? wp_date( 'H:i', $ts, wp_timezone() ) : '';
    }
    if ( ! $end ) {
        $ts = mvm_e2_ts( $post_id, '_event_end_date' );
        $end = $ts ? wp_date( 'H:i', $ts, wp_timezone() ) : '';
    }
    if ( ! $start ) {
        return 'Tijd volgt';
    }
    if ( preg_match( '/^\d{2}:\d{2}:\d{2}$/', $start ) ) {
        $start = substr( $start, 0, 5 );
    }
    if ( preg_match( '/^\d{2}:\d{2}:\d{2}$/', $end ) ) {
        $end = substr( $end, 0, 5 );
    }
    if ( '00:00' === $start && '23:59' === $end ) {
        return 'Hele dag';
    }
    return $end && $end !== $start ? $start . ' – ' . $end : $start;
}

function mvm_e2_location( $post_id ) {
    $value = trim( (string) mvm_e2_meta( $post_id, '_event_location' ) );
    if ( ! $value ) {
        $value = trim( (string) mvm_e2_meta( $post_id, '_event_address' ) );
    }
    if ( $value ) {
        $value = preg_replace( '/,\s*Netherlands$/i', ', Nederland', $value );
    }
    return $value ?: 'Locatie volgt';
}

function mvm_e2_organizer_name( $post_id ) {
    $name = trim( (string) get_post_meta( $post_id, '_mvm_organizer_name', true ) );
    if ( $name ) {
        return $name;
    }
    $ids = get_post_meta( $post_id, '_event_organizer_ids', true );
    if ( is_array( $ids ) && ! empty( $ids[0] ) ) {
        return get_the_title( (int) $ids[0] );
    }
    return '';
}

function mvm_e2_events_query( $past = false ) {
    $now = current_time( 'mysql' );
    $meta_query = $past
        ? array( array( 'key' => '_event_end_date', 'value' => $now, 'compare' => '<', 'type' => 'DATETIME' ) )
        : array(
            'relation' => 'OR',
            array( 'key' => '_event_end_date', 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME' ),
            array( 'key' => '_event_start_date', 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME' ),
        );
    return new WP_Query(
        array(
            'post_type' => 'event_listing',
            'post_status' => $past ? array( 'publish', 'expired' ) : 'publish',
            'posts_per_page' => 80,
            'meta_key' => '_event_start_date',
            'meta_query' => $meta_query,
            'orderby' => array( 'meta_value' => $past ? 'DESC' : 'ASC', 'title' => 'ASC' ),
            'order' => $past ? 'DESC' : 'ASC',
        )
    );
}

function mvm_e2_status_label( $status ) {
    $labels = array(
        'publish' => 'Gepubliceerd',
        'pending' => 'In controle',
        'draft'   => 'Concept',
        'expired' => 'Voorbij',
    );
    return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( (string) $status );
}

function mvm_e2_force_comments( $open, $post_id ) {
    return 'event_listing' === get_post_type( $post_id ) ? true : $open;
}
add_filter( 'comments_open', 'mvm_e2_force_comments', 40, 2 );

function mvm_e2_disable_pings( $open, $post_id ) {
    return 'event_listing' === get_post_type( $post_id ) ? false : $open;
}
add_filter( 'pings_open', 'mvm_e2_disable_pings', 40, 2 );

function mvm_e2_share_block( $post_id ) {
    $url = get_permalink( $post_id );
    $title = get_the_title( $post_id );
    $summary = $title . ' — ' . mvm_e2_date_label( $post_id ) . ', ' . mvm_e2_time_label( $post_id ) . ' · ' . mvm_e2_location( $post_id );
    ob_start();
    ?>
    <section class="mvm-e2-share" aria-labelledby="mvm-e2-share-title">
        <div><span class="mvm-e2-kicker">Delen</span><h2 id="mvm-e2-share-title">Deel dit evenement</h2><p>Datum, tijd en locatie gaan direct mee.</p></div>
        <div class="mvm-e2-share-actions">
            <a href="https://wa.me/?text=<?php echo rawurlencode( $summary . ' ' . $url ); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( $url ); ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
            <a href="mailto:?subject=<?php echo rawurlencode( $title ); ?>&body=<?php echo rawurlencode( $summary . "\n\n" . $url ); ?>">E-mail</a>
            <button type="button" class="mvm-e2-copy" data-url="<?php echo esc_attr( $url ); ?>">Kopieer link</button>
        </div>
    </section>
    <script>document.addEventListener('click',function(e){var b=e.target.closest('.mvm-e2-copy');if(!b)return;var u=b.getAttribute('data-url');if(navigator.clipboard){navigator.clipboard.writeText(u).then(function(){b.textContent='Link gekopieerd';});}});</script>
    <?php
    return ob_get_clean();
}

function mvm_e2_owned_post( $post_id, $type ) {
    $post = get_post( $post_id );
    return $post && $type === $post->post_type && ( (int) $post->post_author === get_current_user_id() || mvm_e2_is_staff() );
}

function mvm_e2_upload( $field, $post_id ) {
    if ( empty( $_FILES[ $field ]['name'] ) ) {
        return 0;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attachment_id = media_handle_upload( $field, $post_id );
    return is_wp_error( $attachment_id ) ? 0 : (int) $attachment_id;
}

function mvm_e2_save_event() {
    if ( ! is_user_logged_in() || ! mvm_e2_is_organizer() ) {
        wp_die( 'Geen toegang.', 403 );
    }
    check_admin_referer( 'mvm_e2_save_event', 'mvm_nonce' );
    $event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
    if ( $event_id && ! mvm_e2_owned_post( $event_id, 'event_listing' ) ) {
        wp_die( 'Dit evenement is niet van jou.', 403 );
    }

    $title = isset( $_POST['event_title'] ) ? sanitize_text_field( wp_unslash( $_POST['event_title'] ) ) : '';
    $date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
    if ( ! $title || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        wp_safe_redirect( add_query_arg( 'status', 'ongeldig', home_url( '/organisatoren/' ) ) );
        exit;
    }

    $all_day = ! empty( $_POST['all_day'] );
    $stime = isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '';
    $edate = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : $date;
    $etime = isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '';
    $stime = $all_day ? '00:00' : ( preg_match( '/^\d{2}:\d{2}$/', $stime ) ? $stime : '00:00' );
    $etime = $all_day ? '23:59' : ( preg_match( '/^\d{2}:\d{2}$/', $etime ) ? $etime : $stime );
    $edate = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $edate ) ? $edate : $date;
    $start = $date . ' ' . $stime . ':00';
    $end = $edate . ' ' . $etime . ':00';
    if ( strtotime( $end ) < strtotime( $start ) ) {
        $end = $start;
    }

    $status = 'pending';
    if ( $event_id && 'publish' === get_post_status( $event_id ) ) {
        $status = 'publish';
    }
    $saved = wp_insert_post(
        wp_slash(
            array(
                'ID' => $event_id,
                'post_type' => 'event_listing',
                'post_title' => $title,
                'post_content' => isset( $_POST['event_description'] ) ? wp_kses_post( wp_unslash( $_POST['event_description'] ) ) : '',
                'post_status' => $status,
                'post_author' => get_current_user_id(),
                'comment_status' => 'open',
            )
        ),
        true
    );
    if ( is_wp_error( $saved ) ) {
        wp_die( esc_html( $saved->get_error_message() ) );
    }

    $simple = array(
        '_event_location' => 'event_location', '_event_address' => 'event_address', '_event_pincode' => 'event_postcode',
        '_event_ticket_price' => 'event_price', '_mvm_organizer_name' => 'organizer_name',
    );
    foreach ( $simple as $meta => $field ) {
        update_post_meta( $saved, $meta, isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '' );
    }
    update_post_meta( $saved, '_registration', isset( $_POST['event_website'] ) ? esc_url_raw( wp_unslash( $_POST['event_website'] ) ) : '' );
    update_post_meta( $saved, '_event_start_date', $start );
    update_post_meta( $saved, '_event_end_date', $end );
    update_post_meta( $saved, '_event_start_time', $stime );
    update_post_meta( $saved, '_event_end_time', $etime );
    update_post_meta( $saved, '_event_expiry_date', $edate );
    update_post_meta( $saved, '_event_timezone', wp_timezone_string() ?: 'Europe/Amsterdam' );
    update_post_meta( $saved, '_mvm_all_day', $all_day ? '1' : '0' );
    update_post_meta( $saved, '_mvm_owner_user', get_current_user_id() );
    update_post_meta( $saved, 'peepso_wpem_rsvp_notifications', '1' );
    $image = mvm_e2_upload( 'event_image', $saved );
    if ( $image ) {
        set_post_thumbnail( $saved, $image );
        update_post_meta( $saved, '_event_banner', wp_get_attachment_url( $image ) );
    }
    wp_safe_redirect( add_query_arg( 'status', $event_id ? 'bijgewerkt' : 'ingediend', home_url( '/organisatoren/' ) ) );
    exit;
}
add_action( 'admin_post_mvm_e2_save_event', 'mvm_e2_save_event' );

function mvm_e2_save_ad() {
    if ( ! is_user_logged_in() || ! mvm_e2_is_entrepreneur() ) {
        wp_die( 'Geen toegang.', 403 );
    }
    check_admin_referer( 'mvm_e2_save_ad', 'mvm_nonce' );
    $ad_id = isset( $_POST['ad_id'] ) ? absint( $_POST['ad_id'] ) : 0;
    if ( $ad_id && ! mvm_e2_owned_post( $ad_id, 'mvm_advertentie' ) ) {
        wp_die( 'Deze advertentie is niet van jou.', 403 );
    }
    $title = isset( $_POST['ad_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ad_title'] ) ) : '';
    if ( ! $title ) {
        wp_safe_redirect( add_query_arg( 'status', 'ongeldig', home_url( '/ondernemers/' ) ) );
        exit;
    }
    $status = $ad_id && 'publish' === get_post_status( $ad_id ) ? 'publish' : 'pending';
    $saved = wp_insert_post(
        wp_slash(
            array(
                'ID' => $ad_id,
                'post_type' => 'mvm_advertentie',
                'post_title' => $title,
                'post_content' => isset( $_POST['ad_description'] ) ? wp_kses_post( wp_unslash( $_POST['ad_description'] ) ) : '',
                'post_status' => $status,
                'post_author' => get_current_user_id(),
            )
        ),
        true
    );
    if ( is_wp_error( $saved ) ) {
        wp_die( esc_html( $saved->get_error_message() ) );
    }
    $fields = array(
        '_mvm_business_name' => 'business_name', '_mvm_ad_category' => 'ad_category', '_mvm_ad_cta_label' => 'ad_cta_label',
        '_mvm_ad_start' => 'ad_start', '_mvm_ad_end' => 'ad_end',
    );
    foreach ( $fields as $meta => $field ) {
        update_post_meta( $saved, $meta, isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '' );
    }
    update_post_meta( $saved, '_mvm_ad_cta_url', isset( $_POST['ad_cta_url'] ) ? esc_url_raw( wp_unslash( $_POST['ad_cta_url'] ) ) : '' );
    update_post_meta( $saved, '_mvm_owner_user', get_current_user_id() );
    $image = mvm_e2_upload( 'ad_image', $saved );
    if ( $image ) {
        set_post_thumbnail( $saved, $image );
    }
    wp_safe_redirect( add_query_arg( 'status', $ad_id ? 'bijgewerkt' : 'ingediend', home_url( '/ondernemers/' ) ) );
    exit;
}
add_action( 'admin_post_mvm_e2_save_ad', 'mvm_e2_save_ad' );

function mvm_e2_portal_template( $template ) {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
    if ( '/organisatoren/' === trailingslashit( $uri ) ) {
        return get_stylesheet_directory() . '/mvm-organisatoren.php';
    }
    if ( '/ondernemers/' === trailingslashit( $uri ) ) {
        return get_stylesheet_directory() . '/mvm-ondernemers.php';
    }
    return $template;
}
add_filter( 'template_include', 'mvm_e2_portal_template', 99 );

function mvm_e2_legacy_event_redirects() {
    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
    $path = trailingslashit( (string) $uri );

    $portal_routes = array(
        '/een-evenement-posten/',
        '/evenement-dashboard/',
        '/organisatorformulier-verzenden/',
        '/organisator-dashboard/',
        '/locatieformulier-verzenden/',
        '/locatie-dashboard/',
    );
    if ( in_array( $path, $portal_routes, true ) ) {
        wp_safe_redirect( home_url( '/organisatoren/' ), 301 );
        exit;
    }

    $agenda_routes = array(
        '/evenement-organisatoren/',
        '/evenementlocaties/',
    );
    if ( in_array( $path, $agenda_routes, true ) ) {
        wp_safe_redirect( home_url( '/evenementen/' ), 301 );
        exit;
    }
}
add_action( 'template_redirect', 'mvm_e2_legacy_event_redirects', -5 );

function mvm_e2_portal_robots( $robots ) {
    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
    $path = trailingslashit( (string) $uri );
    if ( in_array( $path, array( '/organisatoren/', '/ondernemers/' ), true ) ) {
        $robots['noindex']  = true;
        $robots['nofollow'] = true;
        $robots['noarchive'] = true;
    }
    return $robots;
}
add_filter( 'wp_robots', 'mvm_e2_portal_robots' );

function mvm_e2_virtual_portal_query() {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
    if ( in_array( trailingslashit( $uri ), array( '/organisatoren/', '/ondernemers/' ), true ) ) {
        global $wp_query;
        $wp_query->is_404 = false;
        status_header( 200 );
    }
}
add_action( 'template_redirect', 'mvm_e2_virtual_portal_query', 0 );
