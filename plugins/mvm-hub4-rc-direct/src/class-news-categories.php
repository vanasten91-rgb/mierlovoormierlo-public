<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dynamic Hub news category pages.
 *
 * Categories remain the canonical WordPress taxonomy. Hub 4 does not create
 * duplicate WordPress pages; each term automatically has a deep-linkable Hub
 * view through ?view=news&category=<slug>.
 */
final class MvM_Hub4_News_Categories {
    public const MANAGE_CAPABILITY = 'mvm_hub4_news_category_manage';
    private const COLOR_META = 'mvm_hub4_category_color';
    private const ROLE_SCHEMA_OPTION = 'mvm_hub4_news_category_role_schema';
    private const ROLE_SCHEMA_VERSION = 1;

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'reconcile_roles' ), 6 );
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
        add_action( 'template_redirect', array( __CLASS__, 'start_asset_injection' ), -1000 );
    }

    public static function palette(): array {
        return array(
            'blue'   => array( 'label' => 'MvM blauw',  'hex' => '#1966AE' ),
            'red'    => array( 'label' => 'Veiligheid', 'hex' => '#B42318' ),
            'orange' => array( 'label' => 'Agenda',     'hex' => '#A15C00' ),
            'green'  => array( 'label' => 'Sport',      'hex' => '#1F7A3F' ),
            'purple' => array( 'label' => 'Cultuur',    'hex' => '#6D3AAE' ),
            'teal'   => array( 'label' => 'Vereniging', 'hex' => '#0F766E' ),
            'pink'   => array( 'label' => 'Politiek',   'hex' => '#9D174D' ),
            'slate'  => array( 'label' => 'Archief',    'hex' => '#475569' ),
        );
    }

    public static function register_routes(): void {
        register_rest_route(
            'mvm-hub4/v1',
            '/news/categories',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'categories' ),
                    'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::NEWS_VIEW ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, 'create_category' ),
                    'permission_callback' => array( __CLASS__, 'can_manage_categories' ),
                ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/news/category/(?P<slug>[a-z0-9-]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'category_news' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::NEWS_VIEW ),
                'args'                => array(
                    'slug' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => static fn( mixed $value ): bool => '' !== sanitize_title( (string) $value ),
                    ),
                    'limit' => array(
                        'required'          => false,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
    }

    public static function can_manage_categories(): bool|WP_Error {
        $hub = MvM_Hub4_Security::require_hub_access();
        if ( true !== $hub ) {
            return $hub;
        }

        if ( ! self::user_can_manage_categories() ) {
            MvM_Hub4_Audit::log(
                'news.category_manage_denied',
                'denied',
                array(
                    'object_type' => 'category',
                    'context' => array(
                        'required_capability' => self::MANAGE_CAPABILITY,
                        'requires_news_review' => true,
                    ),
                )
            );
            return new WP_Error( 'mvm_hub4_category_forbidden', 'Onvoldoende rechten om nieuwscategorieën te beheren.', array( 'status' => 403 ) );
        }

        return true;
    }

    public static function categories( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        unset( $request );
        $terms = get_terms(
            array(
                'taxonomy'   => 'category',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );
        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        $items = array_map( array( __CLASS__, 'term_payload' ), $terms );
        MvM_Hub4_Audit::log(
            'news.category_list_view',
            'success',
            array( 'object_type' => 'category_list', 'context' => array( 'count' => count( $items ) ) )
        );

        return rest_ensure_response(
            array(
                'items' => $items,
                'palette' => self::palette(),
                'permissions' => array(
                    'canManage' => self::user_can_manage_categories(),
                ),
            )
        );
    }

    public static function category_news( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $slug = sanitize_title( (string) $request['slug'] );
        $term = get_term_by( 'slug', $slug, 'category' );
        if ( ! $term instanceof WP_Term ) {
            return new WP_Error( 'mvm_hub4_category_not_found', 'Nieuwscategorie niet gevonden.', array( 'status' => 404 ) );
        }

        $limit = min( 50, max( 1, absint( $request->get_param( 'limit' ) ?: 30 ) ) );
        $statuses = array( 'publish' );
        if ( current_user_can( 'manage_options' ) || current_user_can( MvM_Hub4_Capabilities::NEWS_REVIEW ) ) {
            $statuses = array( 'draft', 'pending', 'future', 'publish', 'private' );
        } elseif ( current_user_can( 'edit_posts' ) ) {
            $statuses = array( 'draft', 'pending', 'future', 'publish', 'private' );
        }

        $query = new WP_Query(
            array(
                'post_type'              => 'post',
                'post_status'            => $statuses,
                'posts_per_page'         => $limit,
                'category_name'           => $term->slug,
                'orderby'                 => 'modified',
                'order'                   => 'DESC',
                'no_found_rows'           => false,
                'ignore_sticky_posts'     => true,
                'update_post_meta_cache'  => false,
                'update_post_term_cache'  => false,
            )
        );

        $items = array();
        foreach ( (array) $query->posts as $post ) {
            if ( ! $post instanceof WP_Post ) {
                continue;
            }
            $is_published = 'publish' === $post->post_status;
            if ( ! $is_published && ! current_user_can( 'read_post', $post->ID ) && ! current_user_can( 'edit_post', $post->ID ) ) {
                continue;
            }
            $author = get_userdata( (int) $post->post_author );
            $items[] = array(
                'id'          => (int) $post->ID,
                'title'       => get_the_title( $post ) ?: '(Zonder titel)',
                'status'      => (string) $post->post_status,
                'modifiedUtc' => get_post_modified_time( 'Y-m-d H:i:s', true, $post ),
                'author'      => $author instanceof WP_User ? sanitize_text_field( (string) $author->display_name ) : '',
                'viewUrl'     => $is_published ? esc_url_raw( (string) get_permalink( $post ) ) : '',
                'editUrl'     => current_user_can( 'edit_post', $post->ID ) ? esc_url_raw( (string) get_edit_post_link( $post->ID, 'raw' ) ) : '',
            );
        }

        MvM_Hub4_Audit::log(
            'news.category_view',
            'success',
            array(
                'object_type' => 'category',
                'object_id'   => (int) $term->term_id,
                'context'     => array( 'slug' => $term->slug, 'returned' => count( $items ) ),
            )
        );

        return rest_ensure_response(
            array(
                'category' => self::term_payload( $term ),
                'items'    => $items,
                'total'    => (int) $query->found_posts,
            )
        );
    }

    public static function create_category( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        // Deliberate second authorization gate: permission_callback is not the
        // only line of defence for this taxonomy write.
        $permission = self::can_manage_categories();
        if ( true !== $permission ) {
            return $permission;
        }

        $params = (array) $request->get_json_params();
        $name = sanitize_text_field( (string) ( $params['name'] ?? '' ) );
        $color = sanitize_key( (string) ( $params['color'] ?? 'blue' ) );
        $palette = self::palette();

        if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 80 ) {
            return new WP_Error( 'mvm_hub4_category_name_invalid', 'Gebruik een categorienaam van 2 tot 80 tekens.', array( 'status' => 400 ) );
        }
        if ( ! isset( $palette[ $color ] ) ) {
            return new WP_Error( 'mvm_hub4_category_color_invalid', 'Kies een kleur uit het MvM-palet.', array( 'status' => 400 ) );
        }

        $slug = sanitize_title( (string) ( $params['slug'] ?? $name ) );
        if ( '' === $slug ) {
            return new WP_Error( 'mvm_hub4_category_slug_invalid', 'De categorie heeft geen geldige slug.', array( 'status' => 400 ) );
        }
        if ( term_exists( $slug, 'category' ) || term_exists( $name, 'category' ) ) {
            return new WP_Error( 'mvm_hub4_category_exists', 'Deze nieuwscategorie bestaat al.', array( 'status' => 409 ) );
        }

        $created = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
        if ( is_wp_error( $created ) ) {
            return $created;
        }

        $term_id = absint( $created['term_id'] ?? 0 );
        if ( $term_id < 1 ) {
            return new WP_Error( 'mvm_hub4_category_create_failed', 'De categorie kon niet veilig worden aangemaakt.', array( 'status' => 500 ) );
        }
        update_term_meta( $term_id, self::COLOR_META, $color );
        $term = get_term( $term_id, 'category' );
        if ( ! $term instanceof WP_Term ) {
            return new WP_Error( 'mvm_hub4_category_create_failed', 'De aangemaakte categorie kon niet worden geladen.', array( 'status' => 500 ) );
        }

        MvM_Hub4_Audit::log(
            'news.category_created',
            'success',
            array(
                'object_type' => 'category',
                'object_id'   => $term_id,
                'context'     => array( 'slug' => $term->slug, 'color' => $color ),
            )
        );

        $response = rest_ensure_response( self::term_payload( $term ) );
        $response->set_status( 201 );
        return $response;
    }

    public static function reconcile_roles(): void {
        $roles = array( 'administrator', 'mvm_sysop', 'mvm_teamleider', 'mvm_editor' );
        $all_managed_roles = array(
            'administrator', 'mvm_sysop', 'mvm_teamleider', 'mvm_editor',
            'mvm_journalist', 'mvm_redacteur', 'mvm_moderator', 'mvm_vertaler', 'mvm_fotograaf',
        );
        $schema_current = self::ROLE_SCHEMA_VERSION === (int) get_option( self::ROLE_SCHEMA_OPTION, 0 );
        $matrix_current = true;
        foreach ( $all_managed_roles as $role_name ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                continue;
            }
            $should_have = in_array( $role_name, $roles, true );
            if ( $role->has_cap( self::MANAGE_CAPABILITY ) !== $should_have ) {
                $matrix_current = false;
                break;
            }
        }
        if ( $schema_current && $matrix_current ) {
            return;
        }

        foreach ( $all_managed_roles as $role_name ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                continue;
            }
            $role->remove_cap( self::MANAGE_CAPABILITY );
            if ( in_array( $role_name, $roles, true ) ) {
                $role->add_cap( self::MANAGE_CAPABILITY );
            }
        }
        update_option( self::ROLE_SCHEMA_OPTION, self::ROLE_SCHEMA_VERSION, false );
        MvM_Hub4_Audit::log(
            'system.news_category_capability_reconciled',
            'success',
            array( 'object_type' => 'role_matrix', 'context' => array( 'schema_version' => self::ROLE_SCHEMA_VERSION ) )
        );
    }

    public static function start_asset_injection(): void {
        if ( ! self::is_hub_path() ) {
            return;
        }
        $css = esc_url( MVM_HUB4_URL . 'assets/news-categories-v1.css?ver=' . rawurlencode( MVM_HUB4_VERSION ) );
        $js  = esc_url( MVM_HUB4_URL . 'assets/news-categories-v1.js?ver=' . rawurlencode( MVM_HUB4_VERSION ) );
        ob_start(
            static function ( string $html ) use ( $css, $js ): string {
                // The unauthenticated legacy staff gateway also lives at /hub/.
                // Inject only after the final response proves this is Hub 4.
                if ( ! str_contains( $html, 'class="mvm-hub4"' ) ) {
                    return $html;
                }
                if ( ! str_contains( $html, 'mvm-hub4-news-categories-css' ) ) {
                    $html = str_replace( '</head>', '<link id="mvm-hub4-news-categories-css" rel="stylesheet" href="' . $css . '">' . "\n</head>", $html );
                }
                if ( ! str_contains( $html, 'mvm-hub4-news-categories-js' ) ) {
                    $html = str_replace( '</body>', '<script id="mvm-hub4-news-categories-js" src="' . $js . '" defer></script>' . "\n</body>", $html );
                }
                return $html;
            }
        );
    }

    private static function user_can_manage_categories(): bool {
        return current_user_can( 'manage_options' ) || (
            current_user_can( self::MANAGE_CAPABILITY )
            && current_user_can( MvM_Hub4_Capabilities::NEWS_REVIEW )
        );
    }

    private static function is_hub_path(): bool {
        if ( empty( $_SERVER['REQUEST_URI'] ) ) {
            return false;
        }
        $request_path = wp_parse_url( wp_unslash( (string) $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
        if ( ! is_string( $request_path ) ) {
            return false;
        }
        $hub_path  = wp_parse_url( home_url( '/hub/' ), PHP_URL_PATH );
        $hub4_path = wp_parse_url( home_url( '/hub4/' ), PHP_URL_PATH );
        return (
            is_string( $hub_path ) && untrailingslashit( $request_path ) === untrailingslashit( $hub_path )
        ) || (
            is_string( $hub4_path ) && untrailingslashit( $request_path ) === untrailingslashit( $hub4_path )
        );
    }

    private static function term_payload( WP_Term $term ): array {
        $color = sanitize_key( (string) get_term_meta( $term->term_id, self::COLOR_META, true ) );
        if ( ! isset( self::palette()[ $color ] ) ) {
            $color = self::default_color_for_slug( $term->slug );
        }

        return array(
            'id'       => (int) $term->term_id,
            'name'     => sanitize_text_field( (string) $term->name ),
            'slug'     => sanitize_title( (string) $term->slug ),
            'count'    => (int) $term->count,
            'colorKey' => $color,
            'hubUrl'   => esc_url_raw( add_query_arg( array( 'view' => 'news', 'category' => $term->slug ), home_url( '/hub/' ) ) ),
        );
    }

    private static function default_color_for_slug( string $slug ): string {
        $slug = sanitize_title( $slug );
        $map = array(
            '112-veiligheid'      => 'red',
            '112-en-veiligheid'   => 'red',
            'veiligheid'          => 'red',
            'algemeen'            => 'blue',
            'archief'             => 'slate',
            'cultuur'             => 'purple',
            'evenementen-agenda'  => 'orange',
            'evenementen'         => 'orange',
            'agenda'              => 'orange',
            'politiek'            => 'pink',
            'sport'               => 'green',
            'verenigingen'        => 'teal',
            'ongecategoriseerd'   => 'slate',
        );
        return $map[ $slug ] ?? 'blue';
    }
}
