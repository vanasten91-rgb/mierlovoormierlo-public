<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Narrow, source-owned frontend repairs for MvM pages.
 *
 * This class deliberately does not alter PeepSo, WP Event Manager, Elementor,
 * PostX or the embedded Hub3 legacy tree. It only renders MvM wrappers/CSS,
 * reads published event data through normal WordPress APIs and repairs the
 * two known Encyclopedie asset URLs emitted by the relocated Hub3 runtime.
 */
final class MvM_Hub4_Frontend_Repairs {
    private const EVENTS_PAGE_ID = 9385;
    private const LEGACY_PLUGIN_URL_MARKER = '/wp-content/plugins/';
    private const LEGACY_ENCYCLOPEDIA_ASSET_MARKER = '/wp-content/mvm-hub4-legacy/hub3/modules/encyclopedie/assets/';

    public static function init(): void {
        add_shortcode( 'mvm_events_overview', array( __CLASS__, 'events_shortcode' ) );
        add_action( 'wp_head', array( __CLASS__, 'render_repairs_css' ), PHP_INT_MAX );
        add_filter( 'style_loader_src', array( __CLASS__, 'normalize_legacy_asset_url' ), PHP_INT_MAX, 2 );
        add_filter( 'script_loader_src', array( __CLASS__, 'normalize_legacy_asset_url' ), PHP_INT_MAX, 2 );
    }

    /**
     * Repair only the two Encyclopedie assets whose legacy plugins_url() call
     * receives an absolute path after Hub3 was adopted into WP_CONTENT_DIR.
     *
     * The persistent legacy payload is integrity-checked and remains untouched;
     * this compatibility shim only normalizes the emitted public URL.
     */
    public static function normalize_legacy_asset_url( string $src, string $handle = '' ): string {
        if ( '' === $src ) {
            return $src;
        }

        $plugin_pos = strpos( $src, self::LEGACY_PLUGIN_URL_MARKER );
        $legacy_pos = strpos( $src, self::LEGACY_ENCYCLOPEDIA_ASSET_MARKER );
        if ( false === $plugin_pos || false === $legacy_pos || $plugin_pos >= $legacy_pos ) {
            return $src;
        }

        $asset_tail = substr( $src, $legacy_pos + strlen( self::LEGACY_ENCYCLOPEDIA_ASSET_MARKER ) );
        if ( 1 !== preg_match( '~^(?:css/frontend\.css|js/frontend\.js)(?:[?#].*)?$~', $asset_tail ) ) {
            return $src;
        }

        $content_marker = '/wp-content/';
        $relative = substr( $src, $legacy_pos + strlen( $content_marker ) );
        if ( false === $relative || '' === $relative ) {
            return $src;
        }

        return content_url( '/' . $relative );
    }

    public static function events_shortcode( array|string $atts = array() ): string {
        $atts = shortcode_atts( array( 'limit' => 30 ), is_array( $atts ) ? $atts : array(), 'mvm_events_overview' );
        $limit = min( 60, max( 1, absint( $atts['limit'] ) ) );

        wp_enqueue_style( 'mvm-hub4-public-v1', MVM_HUB4_URL . 'assets/public-v1.css', array(), MVM_HUB4_VERSION );

        $now = current_time( 'Y-m-d H:i:s' );
        $query = new WP_Query(
            array(
                'post_type'              => 'event_listing',
                'post_status'            => 'publish',
                'posts_per_page'         => $limit,
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'meta_key'               => '_event_start_date',
                'orderby'                => 'meta_value',
                'order'                  => 'ASC',
                'meta_type'              => 'DATETIME',
                'meta_query'             => array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_event_end_date',
                        'value'   => $now,
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ),
                    array(
                        'key'     => '_event_end_date',
                        'compare' => 'NOT EXISTS',
                    ),
                ),
            )
        );

        ob_start();
        ?>
        <section class="mvm-events-v1" aria-label="Evenementen in Mierlo">
            <p class="mvm-events-v1__intro">Bekijk komende activiteiten en evenementen in Mierlo. Open een evenement voor alle informatie en reacties.</p>
            <?php if ( ! $query->posts ) : ?>
                <p class="mvm-events-v1__empty">Er staan momenteel geen komende evenementen ingepland.</p>
            <?php else : ?>
                <div class="mvm-events-v1__grid">
                    <?php foreach ( $query->posts as $post ) : ?>
                        <?php
                        if ( ! $post instanceof WP_Post ) {
                            continue;
                        }
                        $url      = get_permalink( $post );
                        $title    = get_the_title( $post );
                        $start    = (string) get_post_meta( $post->ID, '_event_start_date', true );
                        $end      = (string) get_post_meta( $post->ID, '_event_end_date', true );
                        $location = sanitize_text_field( (string) get_post_meta( $post->ID, '_event_location', true ) );
                        $image    = get_the_post_thumbnail_url( $post, 'medium_large' );
                        ?>
                        <article class="mvm-events-v1__card">
                            <?php if ( $image && $url ) : ?>
                                <a class="mvm-events-v1__image" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
                                    <img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async">
                                </a>
                            <?php endif; ?>
                            <div class="mvm-events-v1__body">
                                <p class="mvm-events-v1__date"><?php echo esc_html( self::format_event_date( $start, $end ) ); ?></p>
                                <h2 class="mvm-events-v1__title"><a href="<?php echo esc_url( $url ?: '' ); ?>"><?php echo esc_html( $title ); ?></a></h2>
                                <?php if ( '' !== $location ) : ?><p class="mvm-events-v1__location"><?php echo esc_html( $location ); ?></p><?php endif; ?>
                                <a class="mvm-events-v1__cta" href="<?php echo esc_url( $url ?: '' ); ?>">Bekijk evenement →</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        wp_reset_postdata();
        return (string) ob_get_clean();
    }

    public static function render_repairs_css(): void {
        $encyclopedia = self::is_encyclopedia_request();
        $home         = is_home() || is_front_page();
        if ( ! $encyclopedia && ! $home && ! is_page( self::EVENTS_PAGE_ID ) ) {
            return;
        }
        ?>
        <style id="mvm-hub4-frontend-repairs">
        <?php if ( $home ) : ?>
        body.home .mvm-theme1-discover-photo-copy,
        body.home .mvm-elementor-discover__photo>div,
        body.home .mvm-theme1-discover-photo-copy *,
        body.home .mvm-elementor-discover__photo>div *{
            color:#fff!important;
            -webkit-text-fill-color:#fff!important;
        }
        body.home .mvm-theme1-discover-photo-copy,
        body.home .mvm-elementor-discover__photo>div{
            background:linear-gradient(180deg,rgba(7,22,35,0) 0%,rgba(7,22,35,.86) 56%,rgba(7,22,35,.96) 100%)!important;
            text-shadow:0 1px 3px rgba(0,0,0,.65);
        }
        body.home .mvm-theme1-discover-photo-copy h3,
        body.home .mvm-theme1-discover-photo-copy h3 a,
        body.home .mvm-elementor-discover__photo h3,
        body.home .mvm-elementor-discover__photo h3 a{
            color:#fff!important;
            -webkit-text-fill-color:#fff!important;
        }
        <?php endif; ?>
        <?php if ( $encyclopedia ) : ?>
        .mvm-public-home{max-width:1240px!important;margin-inline:auto!important;padding:0 clamp(10px,2vw,20px) 32px!important;color:#17202a!important}
        .mvm-public-home .mvm-hero{border-radius:16px!important;background:linear-gradient(135deg,#1966AE 0%,#124f88 100%)!important;color:#fff!important;box-shadow:0 10px 30px rgba(18,79,136,.18)!important}
        .mvm-public-home .mvm-hero,.mvm-public-home .mvm-hero *{color:#fff!important;-webkit-text-fill-color:#fff!important}
        .mvm-public-home .mvm-public-search input{background:#fff!important;color:#17202a!important;-webkit-text-fill-color:#17202a!important;border-color:#c8d6e2!important}
        .mvm-public-home .mvm-public-search button{background:#0e477b!important;color:#fff!important;-webkit-text-fill-color:#fff!important;border-color:#fff!important}
        .mvm-public-home .mvm-entry-grid a{background:#fff!important;color:#124f88!important;-webkit-text-fill-color:#124f88!important;border:1px solid #d8e2ea!important;border-radius:10px!important}
        .mvm-public-home .mvm-public-section{color:#17202a!important}
        .mvm-public-home .mvm-section-head h2,.mvm-public-home .mvm-section-head span{color:#17202a!important;-webkit-text-fill-color:#17202a!important}
        .mvm-public-home .mvm-public-card{overflow:hidden!important;background:#fff!important;color:#17202a!important;border:1px solid #d8e2ea!important;border-radius:14px!important;box-shadow:0 8px 24px rgba(23,32,42,.07)!important}
        .mvm-public-home .mvm-card-image{display:block!important;width:100%!important;aspect-ratio:16/10!important;height:auto!important;object-fit:cover!important}
        .mvm-public-home .mvm-card-body,.mvm-public-home .mvm-card-body p,.mvm-public-home .mvm-card-body h3,.mvm-public-home .mvm-card-body h3 a{color:#17202a!important;-webkit-text-fill-color:#17202a!important}
        .mvm-public-home .mvm-card-meta{color:#526575!important;-webkit-text-fill-color:#526575!important}
        .mvm-public-home .mvm-read-more,.mvm-public-home .mvm-card-body a{color:#1966AE!important;-webkit-text-fill-color:#1966AE!important}
        .mvm-public-home .mvm-az-scroll{max-height:none!important;overflow:visible!important;background:#fff!important;color:#17202a!important;border-color:#d8e2ea!important;scrollbar-gutter:auto!important}
        .mvm-public-home .mvm-az-nav a{color:#124f88!important;-webkit-text-fill-color:#124f88!important;background:#f3f7fb!important;border-color:#d8e2ea!important}
        .mvm-public-home .mvm-az-letter{border-color:#d8e2ea!important;background:#fff!important}
        .mvm-public-home .mvm-az-letter summary{background:#f3f7fb!important;color:#17202a!important;-webkit-text-fill-color:#17202a!important}
        .mvm-public-home .mvm-az-letter-body,.mvm-public-home .mvm-az-letter-body li,.mvm-public-home .mvm-az-letter-body a{color:#17202a!important;-webkit-text-fill-color:#17202a!important}
        .mvm-public-home .mvm-az-letter-body a{color:#124f88!important;-webkit-text-fill-color:#124f88!important}
        body.mvm-header-dark .mvm-public-home,html[data-theme="dark"] .mvm-public-home{color:#f3f7fa!important}
        body.mvm-header-dark .mvm-public-home .mvm-public-section,html[data-theme="dark"] .mvm-public-home .mvm-public-section{color:#f3f7fa!important}
        body.mvm-header-dark .mvm-public-home .mvm-section-head h2,body.mvm-header-dark .mvm-public-home .mvm-section-head span,html[data-theme="dark"] .mvm-public-home .mvm-section-head h2,html[data-theme="dark"] .mvm-public-home .mvm-section-head span{color:#f3f7fa!important;-webkit-text-fill-color:#f3f7fa!important}
        body.mvm-header-dark .mvm-public-home .mvm-entry-grid a,body.mvm-header-dark .mvm-public-home .mvm-public-card,body.mvm-header-dark .mvm-public-home .mvm-az-scroll,body.mvm-header-dark .mvm-public-home .mvm-az-letter,html[data-theme="dark"] .mvm-public-home .mvm-entry-grid a,html[data-theme="dark"] .mvm-public-home .mvm-public-card,html[data-theme="dark"] .mvm-public-home .mvm-az-scroll,html[data-theme="dark"] .mvm-public-home .mvm-az-letter{background:#17222d!important;border-color:#344757!important;color:#f3f7fa!important}
        body.mvm-header-dark .mvm-public-home .mvm-entry-grid a,body.mvm-header-dark .mvm-public-home .mvm-card-body,body.mvm-header-dark .mvm-public-home .mvm-card-body p,body.mvm-header-dark .mvm-public-home .mvm-card-body h3,body.mvm-header-dark .mvm-public-home .mvm-card-body h3 a,body.mvm-header-dark .mvm-public-home .mvm-az-letter-body,body.mvm-header-dark .mvm-public-home .mvm-az-letter-body li,html[data-theme="dark"] .mvm-public-home .mvm-entry-grid a,html[data-theme="dark"] .mvm-public-home .mvm-card-body,html[data-theme="dark"] .mvm-public-home .mvm-card-body p,html[data-theme="dark"] .mvm-public-home .mvm-card-body h3,html[data-theme="dark"] .mvm-public-home .mvm-card-body h3 a,html[data-theme="dark"] .mvm-public-home .mvm-az-letter-body,html[data-theme="dark"] .mvm-public-home .mvm-az-letter-body li{color:#f3f7fa!important;-webkit-text-fill-color:#f3f7fa!important}
        body.mvm-header-dark .mvm-public-home .mvm-card-meta,html[data-theme="dark"] .mvm-public-home .mvm-card-meta{color:#c2d0dc!important;-webkit-text-fill-color:#c2d0dc!important}
        body.mvm-header-dark .mvm-public-home .mvm-read-more,body.mvm-header-dark .mvm-public-home .mvm-card-body a,body.mvm-header-dark .mvm-public-home .mvm-az-letter-body a,html[data-theme="dark"] .mvm-public-home .mvm-read-more,html[data-theme="dark"] .mvm-public-home .mvm-card-body a,html[data-theme="dark"] .mvm-public-home .mvm-az-letter-body a{color:#8bc7fb!important;-webkit-text-fill-color:#8bc7fb!important}
        body.mvm-header-dark .mvm-public-home .mvm-az-letter summary,body.mvm-header-dark .mvm-public-home .mvm-az-nav a,html[data-theme="dark"] .mvm-public-home .mvm-az-letter summary,html[data-theme="dark"] .mvm-public-home .mvm-az-nav a{background:#1d2a36!important;color:#f3f7fa!important;-webkit-text-fill-color:#f3f7fa!important;border-color:#344757!important}
        @media(max-width:650px){.mvm-public-home{padding-inline:8px!important}.mvm-public-home .mvm-card-image{aspect-ratio:4/3!important}}
        <?php endif; ?>
        </style>
        <?php
    }

    private static function is_encyclopedia_request(): bool {
        $path = untrailingslashit( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
        $root = untrailingslashit( (string) wp_parse_url( home_url( '/encyclopedie/' ), PHP_URL_PATH ) );
        return $root !== '' && ( $path === $root || str_starts_with( $path, $root . '/' ) );
    }

    private static function format_event_date( string $start, string $end ): string {
        $start_ts = strtotime( $start );
        if ( false === $start_ts ) {
            return 'Datum volgt';
        }
        $end_ts = strtotime( $end );
        $same_day = false !== $end_ts && wp_date( 'Y-m-d', $start_ts, wp_timezone() ) === wp_date( 'Y-m-d', $end_ts, wp_timezone() );
        $date = wp_date( 'j F Y', $start_ts, wp_timezone() );
        $start_time = wp_date( 'H:i', $start_ts, wp_timezone() );
        if ( false === $end_ts ) {
            return $date . ' · ' . $start_time;
        }
        if ( $same_day ) {
            return $date . ' · ' . $start_time . '–' . wp_date( 'H:i', $end_ts, wp_timezone() );
        }
        return $date . ' · ' . $start_time . ' – ' . wp_date( 'j F Y · H:i', $end_ts, wp_timezone() );
    }
}
