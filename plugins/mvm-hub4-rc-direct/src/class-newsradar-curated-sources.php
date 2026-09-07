<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Newsradar_Curated_Sources {
    private const OPTION_SCHEMA = 'mvm_newsradar_curated_sources_schema_v1';
    private const OPTION_CREATED = 'mvm_newsradar_curated_sources_created_v1';
    private const SCHEMA_VERSION = 1;
    private const KEY_META = '_mvm_newsradar_curated_key';

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'maybe_seed' ), 60 );
    }

    public static function maybe_seed(): void {
        if ( self::SCHEMA_VERSION === (int) get_option( self::OPTION_SCHEMA, 0 ) ) {
            return;
        }
        if ( ! class_exists( 'MvM_Hub4_Sources' ) || ! class_exists( 'MvM_Hub4_Newsradar_Source_Policy' ) ) {
            return;
        }

        $created = array();
        foreach ( MvM_Hub4_Newsradar_Source_Policy::curated_sources() as $config ) {
            $result = self::ensure_source( $config );
            if ( is_wp_error( $result ) ) {
                if ( class_exists( 'MvM_Hub4_Audit' ) ) {
                    MvM_Hub4_Audit::log(
                        'newsradar.curated_source_seed',
                        'error',
                        array( 'object_type' => 'source', 'context' => array( 'key' => sanitize_key( (string) ( $config['key'] ?? '' ) ), 'message' => $result->get_error_message() ) )
                    );
                }
                return;
            }
            if ( ! empty( $result['created'] ) ) {
                $created[ sanitize_key( (string) $config['key'] ) ] = absint( $result['id'] );
            }
        }

        if ( $created ) {
            $previous = get_option( self::OPTION_CREATED, array() );
            $previous = is_array( $previous ) ? $previous : array();
            update_option( self::OPTION_CREATED, array_merge( $previous, $created ), false );
        }
        update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, false );

        if ( class_exists( 'MvM_Hub4_Audit' ) ) {
            MvM_Hub4_Audit::log(
                'newsradar.curated_source_seed',
                'success',
                array( 'object_type' => 'source_policy', 'context' => array( 'schema' => self::SCHEMA_VERSION, 'created' => count( $created ) ) )
            );
        }
    }

    /** @param array<string,string> $config
     *  @return array{created:bool,id:int}|WP_Error
     */
    private static function ensure_source( array $config ): array|WP_Error {
        $key       = sanitize_key( (string) ( $config['key'] ?? '' ) );
        $title     = sanitize_text_field( (string) ( $config['title'] ?? '' ) );
        $url       = esc_url_raw( (string) ( $config['url'] ?? '' ) );
        $category  = MvM_Hub4_Sources::sanitize_category( (string) ( $config['category'] ?? 'overig' ) );
        $frequency = MvM_Hub4_Sources::sanitize_frequency( (string) ( $config['frequency'] ?? 'daily' ) );
        $priority  = strtoupper( sanitize_text_field( (string) ( $config['priority'] ?? 'A' ) ) );
        if ( '' === $key || '' === $title || '' === $url || ! wp_http_validate_url( $url ) ) {
            return new WP_Error( 'mvm_newsradar_curated_invalid', 'Gecureerde bronconfiguratie is ongeldig.' );
        }
        if ( ! in_array( $priority, array( 'A', 'B', 'C' ), true ) ) {
            $priority = 'A';
        }

        $existing = get_posts(
            array(
                'post_type'      => 'mvm_bron',
                'post_status'    => array( 'publish', 'draft' ),
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => self::KEY_META,
                'meta_value'     => $key,
            )
        );
        if ( $existing ) {
            return array( 'created' => false, 'id' => absint( $existing[0] ) );
        }

        // URL-level idempotency also catches a manually added source.
        $url_candidate = self::find_by_url( $url );
        if ( $url_candidate > 0 ) {
            update_post_meta( $url_candidate, self::KEY_META, $key );
            return array( 'created' => false, 'id' => $url_candidate );
        }

        $source_id = wp_insert_post(
            array(
                'post_type'    => 'mvm_bron',
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => '<p><strong>Bron.</strong> Gecureerde Mierlo Nieuwsradar-bron.</p><p><strong>URL:</strong> ' . esc_html( $url ) . '</p>',
            ),
            true
        );
        if ( is_wp_error( $source_id ) ) {
            return $source_id;
        }
        $source_id = absint( $source_id );

        update_post_meta( $source_id, self::KEY_META, $key );
        update_post_meta( $source_id, '_yoast_wpseo_meta-robots-noindex', 1 );
        update_post_meta( $source_id, '_mvm_newsradar_source', 1 );
        update_post_meta( $source_id, '_mvm_newsradar_active', 1 );
        update_post_meta( $source_id, '_mvm_newsradar_excluded', 0 );
        update_post_meta( $source_id, '_mvm_newsradar_priority', $priority );

        $legacy_id = self::next_legacy_id();
        update_post_meta( $source_id, MvM_Hub4_Newsradar_Source_Adoption::LEGACY_ID_META, $legacy_id );

        $saved = MvM_Hub4_Sources::save_state(
            $source_id,
            array(
                'monitor_enabled' => true,
                'category'        => $category,
                'frequency'       => $frequency,
                'status'          => 'active',
                'private_note'    => 'Gecureerde A-bron voor Mierlo-only Nieuwsradar.',
            )
        );
        if ( is_wp_error( $saved ) ) {
            wp_update_post( array( 'ID' => $source_id, 'post_status' => 'draft' ) );
            return $saved;
        }

        $legacy = get_option( 'mvm_bh_sources', array() );
        $legacy = is_array( $legacy ) ? $legacy : array();
        $labels = MvM_Hub4_Sources::categories();
        $frequencies = MvM_Hub4_Sources::frequencies();
        $legacy[] = array(
            'id'           => $legacy_id,
            'priority'     => $priority,
            'name'         => $title,
            'usage'        => 'Gecureerde Mierlo Nieuwsradar-bron',
            'category'     => (string) ( $labels[ $category ] ?? $category ),
            'frequency'    => (string) ( $frequencies[ $frequency ]['label'] ?? $frequency ),
            'url'          => $url,
            'social'       => '',
            'active'       => true,
            'excluded'     => false,
            'last_checked' => '',
            'next_check'   => '',
            'web_checked'  => wp_date( 'Y-m-d' ),
            'note'         => 'Automatisch toegevoegd vanuit de Mierlo-only gecureerde bronpolicy.',
        );
        update_option( 'mvm_bh_sources', array_values( $legacy ), false );

        return array( 'created' => true, 'id' => $source_id );
    }

    private static function next_legacy_id(): int {
        $legacy = get_option( 'mvm_bh_sources', array() );
        $max = 0;
        foreach ( is_array( $legacy ) ? $legacy : array() as $source ) {
            if ( is_array( $source ) ) {
                $max = max( $max, absint( $source['id'] ?? 0 ) );
            }
        }
        return $max + 1;
    }

    private static function find_by_url( string $url ): int {
        $posts = get_posts(
            array(
                'post_type'      => 'mvm_bron',
                'post_status'    => 'publish',
                'posts_per_page' => 300,
                'fields'         => 'ids',
            )
        );
        foreach ( $posts as $post_id ) {
            $post = get_post( $post_id );
            if ( $post instanceof WP_Post && class_exists( 'MvM_Hub4_Source_Repository' ) ) {
                if ( untrailingslashit( MvM_Hub4_Source_Repository::extract_external_url( (string) $post->post_content ) ) === untrailingslashit( $url ) ) {
                    return absint( $post_id );
                }
            }
        }
        return 0;
    }
}
