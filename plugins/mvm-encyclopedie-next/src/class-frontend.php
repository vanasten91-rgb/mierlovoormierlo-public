<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MVM_Encyclopedie_Next_Frontend {
    private const HOME_PAGE_ID = 1492;
    private const THEMES_PAGE_ID = 6905;

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'pre_get_posts', array( __CLASS__, 'shape_archive_query' ) );
        add_filter( 'template_include', array( __CLASS__, 'template_include' ), 50 );

        add_shortcode( 'mvm_public_home', array( __CLASS__, 'shortcode_home' ) );
        add_shortcode( 'mvm_themas', array( __CLASS__, 'shortcode_themes' ) );
        add_shortcode( 'mvm_thema_page', array( __CLASS__, 'shortcode_theme_page' ) );
    }

    public static function is_surface(): bool {
        $types = MVM_Encyclopedie_Next_Content_Model::post_type_slugs();
        $taxonomies = array_keys( MVM_Encyclopedie_Next_Content_Model::taxonomies() );

        if ( is_singular( $types ) || is_post_type_archive( $types ) || is_tax( $taxonomies ) ) {
            return true;
        }

        if ( is_page( self::HOME_PAGE_ID ) || is_page( self::THEMES_PAGE_ID ) ) {
            return true;
        }

        if ( is_page() ) {
            $post_id = get_queried_object_id();
            $ancestors = get_post_ancestors( $post_id );
            return in_array( self::HOME_PAGE_ID, array_map( 'absint', $ancestors ), true );
        }

        return false;
    }

    public static function enqueue_assets(): void {
        if ( ! self::is_surface() ) {
            return;
        }

        wp_enqueue_style(
            'mvm-encyclopedie-next',
            MVM_ENCYCLOPEDIE_NEXT_URL . 'assets/frontend.css',
            array(),
            MVM_ENCYCLOPEDIE_NEXT_VERSION
        );

        wp_enqueue_script(
            'mvm-encyclopedie-next',
            MVM_ENCYCLOPEDIE_NEXT_URL . 'assets/frontend.js',
            array(),
            MVM_ENCYCLOPEDIE_NEXT_VERSION,
            true
        );

        wp_localize_script(
            'mvm-encyclopedie-next',
            'MVMEncyclopedieNext',
            array(
                'restUrl' => esc_url_raw( rest_url( 'mvm-encyclopedie/v1/' ) ),
                'homeUrl' => esc_url_raw( home_url( '/encyclopedie/' ) ),
                'labels' => array(
                    'loading' => 'Zoeken…',
                    'empty' => 'Geen dossiers gevonden.',
                    'error' => 'Zoeken lukt nu niet. Gebruik Enter voor de gewone zoekopdracht.',
                    'moreRelations' => 'Meer verbanden laden',
                ),
            )
        );
    }

    public static function shape_archive_query( WP_Query $query ): void {
        if ( is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $types = MVM_Encyclopedie_Next_Content_Model::post_type_slugs();
        $taxonomies = array_keys( MVM_Encyclopedie_Next_Content_Model::taxonomies() );

        if ( $query->is_post_type_archive( $types ) ) {
            $query->set( 'posts_per_page', 24 );
            $query->set( 'post_status', 'publish' );
            $query->set( 'orderby', 'title' );
            $query->set( 'order', 'ASC' );
        }

        if ( $query->is_tax( $taxonomies ) ) {
            $query->set( 'post_type', $types );
            $query->set( 'posts_per_page', 24 );
            $query->set( 'post_status', 'publish' );
            $query->set( 'orderby', 'title' );
            $query->set( 'order', 'ASC' );
        }
    }

    public static function template_include( string $template ): string {
        $types = MVM_Encyclopedie_Next_Content_Model::post_type_slugs();
        $taxonomies = array_keys( MVM_Encyclopedie_Next_Content_Model::taxonomies() );

        if ( is_singular( $types ) ) {
            $candidate = MVM_ENCYCLOPEDIE_NEXT_DIR . 'templates/single.php';
            return is_readable( $candidate ) ? $candidate : $template;
        }

        if ( is_post_type_archive( $types ) || is_tax( $taxonomies ) ) {
            $candidate = MVM_ENCYCLOPEDIE_NEXT_DIR . 'templates/archive.php';
            return is_readable( $candidate ) ? $candidate : $template;
        }

        return $template;
    }

    public static function shortcode_home(): string {
        $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
        $type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
        $letter = isset( $_GET['letter'] ) ? strtoupper( substr( sanitize_text_field( wp_unslash( $_GET['letter'] ) ), 0, 1 ) ) : '';
        $page = isset( $_GET['mvm_page'] ) ? max( 1, absint( $_GET['mvm_page'] ) ) : 1;
        $has_filter = '' !== $search || '' !== $type || preg_match( '/^[A-Z]$/', $letter );

        ob_start();
        ?>
        <div class="mvm-encyclopedie-next" data-mvm-encyclopedie-root>
            <?php echo self::render_home_hero( $search, $type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo self::render_collection_strip(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo self::render_alphabet( $letter ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <?php if ( $has_filter ) : ?>
                <?php
                $query = MVM_Encyclopedie_Next_Query::search(
                    array(
                        'search' => $search,
                        'post_type' => $type,
                        'letter' => $letter,
                        'page' => $page,
                        'per_page' => 24,
                    )
                );
                ?>
                <section class="mvm-e3-section" aria-labelledby="mvm-e3-results-title">
                    <div class="mvm-e3-section-head">
                        <div>
                            <p class="mvm-e3-kicker">Resultaten</p>
                            <h2 id="mvm-e3-results-title"><?php echo esc_html( self::result_heading( $search, $type, $letter ) ); ?></h2>
                        </div>
                        <span class="mvm-e3-count"><?php echo esc_html( number_format_i18n( (int) $query->found_posts ) ); ?> dossiers</span>
                    </div>
                    <?php echo self::render_query_grid( $query ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo self::render_pagination( (int) $query->max_num_pages, $page, 'mvm_page' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php else : ?>
                <?php echo self::render_discover(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo self::render_timeline(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_home_hero( string $search, string $type ): string {
        $definitions = MVM_Encyclopedie_Next_Content_Model::post_types();
        ob_start();
        ?>
        <section class="mvm-e3-hero">
            <div class="mvm-e3-hero-copy">
                <p class="mvm-e3-kicker">Digitale Encyclopedie van Mierlo</p>
                <h1>Verhalen, plekken en mensen van Mierlo — met elkaar verbonden.</h1>
                <p>Zoek in de bestaande historische kennisbank zonder honderden dossiers tegelijk te laden. Elk resultaat opent op zijn vaste, canonieke MvM-adres.</p>
            </div>
            <form class="mvm-e3-search" action="<?php echo esc_url( home_url( '/encyclopedie/' ) ); ?>" method="get" data-mvm-live-search>
                <label class="screen-reader-text" for="mvm-e3-q">Zoek in de encyclopedie</label>
                <div class="mvm-e3-search-row">
                    <input id="mvm-e3-q" name="q" type="search" value="<?php echo esc_attr( $search ); ?>" placeholder="Zoek op straat, persoon, gebouw, vereniging…" autocomplete="off" data-mvm-search-input>
                    <select name="type" aria-label="Filter op collectie" data-mvm-search-type>
                        <option value="">Alle collecties</option>
                        <?php foreach ( $definitions as $slug => $definition ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $type, $slug ); ?>><?php echo esc_html( (string) $definition['plural'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Zoeken</button>
                </div>
                <div class="mvm-e3-live-results" data-mvm-live-results hidden aria-live="polite"></div>
            </form>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_collection_strip(): string {
        $counts = MVM_Encyclopedie_Next_Query::counts();
        $definitions = MVM_Encyclopedie_Next_Content_Model::post_types();
        ob_start();
        ?>
        <section class="mvm-e3-section mvm-e3-collections" aria-labelledby="mvm-e3-collections-title">
            <div class="mvm-e3-section-head">
                <div>
                    <p class="mvm-e3-kicker">Verkennen</p>
                    <h2 id="mvm-e3-collections-title">Collecties</h2>
                </div>
                <a class="mvm-e3-text-link" href="<?php echo esc_url( home_url( '/encyclopedie/themas/' ) ); ?>">Bekijk de 18 hoofdthema’s</a>
            </div>
            <div class="mvm-e3-collection-grid">
                <?php foreach ( $definitions as $slug => $definition ) : ?>
                    <a class="mvm-e3-collection-card" href="<?php echo esc_url( MVM_Encyclopedie_Next_Content_Model::archive_url_for( $slug ) ); ?>">
                        <span><?php echo esc_html( (string) $definition['plural'] ); ?></span>
                        <strong><?php echo esc_html( number_format_i18n( isset( $counts[ $slug ] ) ? $counts[ $slug ] : 0 ) ); ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_alphabet( string $active ): string {
        ob_start();
        ?>
        <nav class="mvm-e3-alphabet" aria-label="Zoek alfabetisch">
            <span>A–Z</span>
            <?php foreach ( range( 'A', 'Z' ) as $letter ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'letter', $letter, home_url( '/encyclopedie/' ) ) ); ?>" <?php echo $active === $letter ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $letter ); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_discover(): string {
        $query = new WP_Query(
            array(
                'post_type' => array( 'mvm_encyclopedie', 'mvm_persoon', 'mvm_locatie', 'mvm_gebouw', 'mvm_gebeurtenis', 'mvm_vereniging', 'mvm_bedrijf' ),
                'post_status' => 'publish',
                'posts_per_page' => 8,
                'orderby' => 'modified',
                'order' => 'DESC',
                'ignore_sticky_posts' => true,
                'no_found_rows' => true,
                'meta_query' => array(
                    'relation' => 'OR',
                    array( 'key' => '_mvm_public_hidden', 'compare' => 'NOT EXISTS' ),
                    array( 'key' => '_mvm_public_hidden', 'value' => '1', 'compare' => '!=' ),
                ),
            )
        );

        ob_start();
        ?>
        <section class="mvm-e3-section" aria-labelledby="mvm-e3-discover-title">
            <div class="mvm-e3-section-head">
                <div>
                    <p class="mvm-e3-kicker">Ontdek Mierlo</p>
                    <h2 id="mvm-e3-discover-title">Recent bijgewerkte dossiers</h2>
                </div>
            </div>
            <?php echo self::render_query_grid( $query ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
        <?php
        wp_reset_postdata();
        return (string) ob_get_clean();
    }

    private static function render_timeline(): string {
        $events = MVM_Encyclopedie_Next_Query::timeline( 8 );
        if ( ! $events ) {
            return '';
        }

        ob_start();
        ?>
        <section class="mvm-e3-section" aria-labelledby="mvm-e3-timeline-title">
            <div class="mvm-e3-section-head">
                <div>
                    <p class="mvm-e3-kicker">Tijdlijn</p>
                    <h2 id="mvm-e3-timeline-title">Momenten uit de geschiedenis</h2>
                </div>
                <a class="mvm-e3-text-link" href="<?php echo esc_url( MVM_Encyclopedie_Next_Content_Model::archive_url_for( 'mvm_gebeurtenis' ) ); ?>">Alle gebeurtenissen</a>
            </div>
            <ol class="mvm-e3-timeline">
                <?php foreach ( $events as $event ) : ?>
                    <?php $date = (string) get_post_meta( $event->ID, '_mvm_start_date', true ); ?>
                    <li>
                        <time><?php echo esc_html( self::human_date( $date ) ); ?></time>
                        <a href="<?php echo esc_url( get_permalink( $event ) ); ?>"><?php echo esc_html( get_the_title( $event ) ); ?></a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public static function shortcode_themes(): string {
        $pages = get_posts(
            array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_parent' => self::THEMES_PAGE_ID,
                'posts_per_page' => 24,
                'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
                'order' => 'ASC',
                'no_found_rows' => true,
            )
        );

        ob_start();
        ?>
        <div class="mvm-encyclopedie-next">
            <section class="mvm-e3-hero mvm-e3-hero-compact">
                <div class="mvm-e3-hero-copy">
                    <p class="mvm-e3-kicker">Thematisch ontdekken</p>
                    <h1>18 ingangen naar de geschiedenis van Mierlo</h1>
                    <p>De bestaande themapagina’s blijven intact. Alleen de presentatie en het ophalen van dossiers zijn opnieuw opgebouwd.</p>
                </div>
            </section>
            <div class="mvm-e3-theme-grid">
                <?php foreach ( $pages as $page ) : ?>
                    <a class="mvm-e3-theme-card" href="<?php echo esc_url( get_permalink( $page ) ); ?>">
                        <span><?php echo esc_html( get_the_title( $page ) ); ?></span>
                        <small>Bekijk dossiers</small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $atts */
    public static function shortcode_theme_page( $atts ): string {
        $atts = shortcode_atts( array( 'slug' => '' ), is_array( $atts ) ? $atts : array(), 'mvm_thema_page' );
        $slug = sanitize_title( (string) $atts['slug'] );
        if ( '' === $slug ) {
            return '';
        }

        $term = get_term_by( 'slug', $slug, 'mvm_thema' );
        $page = isset( $_GET['mvm_page'] ) ? max( 1, absint( $_GET['mvm_page'] ) ) : 1;

        ob_start();
        ?>
        <div class="mvm-encyclopedie-next">
            <section class="mvm-e3-hero mvm-e3-hero-compact">
                <div class="mvm-e3-hero-copy">
                    <p class="mvm-e3-kicker">Thema</p>
                    <h1><?php echo esc_html( $term && ! is_wp_error( $term ) ? $term->name : get_the_title() ); ?></h1>
                    <?php if ( $term && ! is_wp_error( $term ) && $term->description ) : ?>
                        <p><?php echo esc_html( wp_strip_all_tags( $term->description ) ); ?></p>
                    <?php endif; ?>
                </div>
            </section>
            <?php if ( $term && ! is_wp_error( $term ) ) : ?>
                <?php
                $query = MVM_Encyclopedie_Next_Query::search(
                    array(
                        'page' => $page,
                        'per_page' => 24,
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'mvm_thema',
                                'field' => 'term_id',
                                'terms' => array( (int) $term->term_id ),
                            ),
                        ),
                    )
                );
                ?>
                <section class="mvm-e3-section">
                    <div class="mvm-e3-section-head"><h2>Dossiers binnen dit thema</h2><span class="mvm-e3-count"><?php echo esc_html( number_format_i18n( (int) $query->found_posts ) ); ?></span></div>
                    <?php echo self::render_query_grid( $query ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo self::render_pagination( (int) $query->max_num_pages, $page, 'mvm_page' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php else : ?>
                <div class="mvm-e3-empty">Dit thema is nog niet als taxonomieterm gekoppeld. De bestaande themapagina blijft bereikbaar.</div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function render_query_grid( WP_Query $query ): string {
        if ( ! $query->posts ) {
            return '<div class="mvm-e3-empty">Geen dossiers gevonden.</div>';
        }

        ob_start();
        echo '<div class="mvm-e3-card-grid">';
        foreach ( $query->posts as $post ) {
            if ( $post instanceof WP_Post ) {
                echo self::render_card( MVM_Encyclopedie_Next_Query::summary( $post ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $item */
    public static function render_card( array $item ): string {
        $thumbnail = isset( $item['thumbnail'] ) ? (string) $item['thumbnail'] : '';
        ob_start();
        ?>
        <article class="mvm-e3-card">
            <a class="mvm-e3-card-media" href="<?php echo esc_url( (string) $item['url'] ); ?>" tabindex="-1" aria-hidden="true">
                <?php if ( '' !== $thumbnail ) : ?>
                    <img src="<?php echo esc_url( $thumbnail ); ?>" alt="" loading="lazy" decoding="async">
                <?php else : ?>
                    <span class="mvm-e3-card-placeholder" aria-hidden="true"></span>
                <?php endif; ?>
            </a>
            <div class="mvm-e3-card-body">
                <span class="mvm-e3-type"><?php echo esc_html( (string) $item['type_label'] ); ?></span>
                <h3><a href="<?php echo esc_url( (string) $item['url'] ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a></h3>
                <?php if ( ! empty( $item['excerpt'] ) ) : ?><p><?php echo esc_html( (string) $item['excerpt'] ); ?></p><?php endif; ?>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    public static function render_pagination( int $max_pages, int $current, string $query_arg = 'mvm_page' ): string {
        if ( $max_pages <= 1 ) {
            return '';
        }

        $base_url = remove_query_arg( $query_arg );
        $links = paginate_links(
            array(
                'base' => esc_url_raw( add_query_arg( $query_arg, '%#%', $base_url ) ),
                'format' => '',
                'current' => max( 1, $current ),
                'total' => $max_pages,
                'type' => 'list',
                'prev_text' => 'Vorige',
                'next_text' => 'Volgende',
            )
        );

        return $links ? '<nav class="mvm-e3-pagination" aria-label="Paginering">' . $links . '</nav>' : '';
    }

    public static function render_breadcrumbs( ?WP_Post $post = null ): string {
        $parts = array( '<a href="' . esc_url( home_url( '/' ) ) . '">Start</a>', '<a href="' . esc_url( home_url( '/encyclopedie/' ) ) . '">Encyclopedie</a>' );
        if ( $post ) {
            $archive = MVM_Encyclopedie_Next_Content_Model::archive_url_for( (string) $post->post_type );
            $parts[] = '<a href="' . esc_url( $archive ) . '">' . esc_html( MVM_Encyclopedie_Next_Content_Model::label_for( (string) $post->post_type, true ) ) . '</a>';
            $parts[] = '<span aria-current="page">' . esc_html( get_the_title( $post ) ) . '</span>';
        }
        return '<nav class="mvm-e3-breadcrumbs" aria-label="Kruimelpad">' . implode( '<span aria-hidden="true">/</span>', $parts ) . '</nav>';
    }

    /** @return array<string,string> */
    public static function facts( WP_Post $post ): array {
        $maps = array(
            'mvm_persoon' => array( '_mvm_birth_date' => 'Geboren', '_mvm_birth_place' => 'Geboorteplaats', '_mvm_death_date' => 'Overleden', '_mvm_death_place' => 'Plaats van overlijden', '_mvm_role' => 'Rol' ),
            'mvm_locatie' => array( '_mvm_address' => 'Adres' ),
            'mvm_gebouw' => array( '_mvm_build_year' => 'Bouwjaar', '_mvm_demolished_year' => 'Sloopjaar', '_mvm_building_status' => 'Status', '_mvm_address' => 'Adres' ),
            'mvm_gebeurtenis' => array( '_mvm_exact_date' => 'Datum', '_mvm_start_date' => 'Start', '_mvm_end_date' => 'Einde', '_mvm_event_kind' => 'Soort gebeurtenis' ),
            'mvm_vereniging' => array( '_mvm_founded_year' => 'Opgericht', '_mvm_closed_year' => 'Beëindigd', '_mvm_org_status' => 'Status' ),
            'mvm_bedrijf' => array( '_mvm_founded_year' => 'Opgericht', '_mvm_closed_year' => 'Beëindigd', '_mvm_address' => 'Adres' ),
            'mvm_beeld' => array( '_mvm_media_date' => 'Datering', '_mvm_media_creator' => 'Maker', '_mvm_media_credit' => 'Credit', '_mvm_media_rights' => 'Rechten' ),
            'mvm_bron' => array( '_mvm_source_reference' => 'Bronverwijzing', '_mvm_source_status' => 'Bronstatus' ),
        );

        $map = isset( $maps[ $post->post_type ] ) ? $maps[ $post->post_type ] : array();
        $facts = array();
        foreach ( $map as $key => $label ) {
            $value = trim( (string) get_post_meta( $post->ID, $key, true ) );
            if ( '' !== $value ) {
                $facts[ $label ] = $value;
            }
        }
        return $facts;
    }

    /** @return array<string,array<int,WP_Term>> */
    public static function taxonomy_groups( WP_Post $post ): array {
        $groups = array();
        foreach ( array_keys( MVM_Encyclopedie_Next_Content_Model::taxonomies() ) as $taxonomy ) {
            $terms = get_the_terms( $post, $taxonomy );
            if ( ! $terms || is_wp_error( $terms ) ) {
                continue;
            }
            $groups[ $taxonomy ] = array_slice( $terms, 0, 12 );
        }
        return $groups;
    }

    private static function result_heading( string $search, string $type, string $letter ): string {
        if ( '' !== $search ) {
            return 'Zoeken naar “' . $search . '”';
        }
        if ( preg_match( '/^[A-Z]$/', $letter ) ) {
            return 'Dossiers met de letter ' . $letter;
        }
        if ( MVM_Encyclopedie_Next_Query::is_allowed_post_type( $type ) ) {
            return MVM_Encyclopedie_Next_Content_Model::label_for( $type, true );
        }
        return 'Alle dossiers';
    }

    public static function human_date( string $date ): string {
        $date = trim( $date );
        if ( '' === $date ) {
            return 'Onbekend';
        }
        $timestamp = strtotime( $date );
        return $timestamp ? wp_date( 'j F Y', $timestamp ) : $date;
    }
}
