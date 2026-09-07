<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Tools {
    public static function registry(): array {
        $tools = array(
            'news' => array(
                'title'       => 'Nieuwsbeheer',
                'description' => 'Beheer nieuwsberichten en concepten in WordPress.',
                'capability'  => 'edit_posts',
                'available'   => true,
                'url'         => admin_url( 'edit.php' ),
                'target'      => 'same',
                'category'    => 'Redactie',
            ),
            'news_new' => array(
                'title'       => 'Nieuw artikel',
                'description' => 'Start een nieuw nieuwsconcept in de bestaande WordPress-editor.',
                'capability'  => 'edit_posts',
                'available'   => true,
                'url'         => admin_url( 'post-new.php' ),
                'target'      => 'same',
                'category'    => 'Redactie',
            ),
            'media' => array(
                'title'       => 'Mediabibliotheek',
                'description' => 'Beheer foto’s, documenten en andere media.',
                'capability'  => 'upload_files',
                'available'   => true,
                'url'         => admin_url( 'upload.php' ),
                'target'      => 'same',
                'category'    => 'Content',
            ),
            'events' => array(
                'title'       => 'Evenementen',
                'description' => 'Beheer evenementen in WP Event Manager.',
                'capability'  => 'edit_posts',
                'available'   => post_type_exists( 'event_listing' ),
                'url'         => admin_url( 'edit.php?post_type=event_listing' ),
                'target'      => 'same',
                'category'    => 'Redactie',
            ),
            'encyclopedia' => array(
                'title'       => 'Encyclopedie',
                'description' => 'Beheer de Digitale Encyclopedie van Mierlo.',
                'capability'  => 'edit_posts',
                'available'   => post_type_exists( 'mvm_encyclopedie' ),
                'url'         => admin_url( 'edit.php?post_type=mvm_encyclopedie' ),
                'target'      => 'same',
                'category'    => 'Kennis',
            ),
            'elementor_templates' => array(
                'title'       => 'Elementor templates',
                'description' => 'Open de Elementor-templatebibliotheek zonder de editor zelf te overschrijven.',
                'capability'  => 'edit_posts',
                'available'   => post_type_exists( 'elementor_library' ),
                'url'         => admin_url( 'edit.php?post_type=elementor_library' ),
                'target'      => 'same',
                'category'    => 'Sitebouw',
            ),
            'postx_builder' => array(
                'title'       => 'PostX Builder',
                'description' => 'Beheer PostX-builderonderdelen en templates.',
                'capability'  => 'edit_posts',
                'available'   => post_type_exists( 'ultp_builder' ),
                'url'         => admin_url( 'edit.php?post_type=ultp_builder' ),
                'target'      => 'same',
                'category'    => 'Sitebouw',
            ),
            'postx_templates' => array(
                'title'       => 'PostX templates',
                'description' => 'Beheer opgeslagen PostX-templates.',
                'capability'  => 'edit_posts',
                'available'   => post_type_exists( 'ultp_templates' ),
                'url'         => admin_url( 'edit.php?post_type=ultp_templates' ),
                'target'      => 'same',
                'category'    => 'Sitebouw',
            ),
            'users' => array(
                'title'       => 'Gebruikers & staf',
                'description' => 'Beheer gebruikers alleen wanneer je rol dit daadwerkelijk toestaat.',
                'capability'  => 'list_users',
                'available'   => true,
                'url'         => admin_url( 'users.php' ),
                'target'      => 'same',
                'category'    => 'Beheer',
            ),
            'plugins' => array(
                'title'       => 'Plugins',
                'description' => 'Pluginbeheer voor SysOp/beheerders.',
                'capability'  => 'activate_plugins',
                'available'   => true,
                'url'         => admin_url( 'plugins.php' ),
                'target'      => 'same',
                'category'    => 'Systeem',
            ),
            'site_health' => array(
                'title'       => 'Sitediagnose',
                'description' => 'Controleer WordPress Site Health en technische waarschuwingen.',
                'capability'  => 'manage_options',
                'available'   => true,
                'url'         => admin_url( 'site-health.php' ),
                'target'      => 'same',
                'category'    => 'Systeem',
            ),
            'forum' => array(
                'title'       => 'Forum',
                'description' => 'Open het Mierlo voor Mierlo-forum.',
                'capability'  => MvM_Hub4_Security::HUB_CAPABILITY,
                'available'   => post_type_exists( 'wpforo_topic' ) || class_exists( 'wpForo' ) || function_exists( 'WPF' ),
                'url'         => home_url( '/forum/' ),
                'target'      => 'new',
                'category'    => 'Community',
            ),
        );

        /**
         * Extensions may add integrations, but each entry still passes Hub 4's
         * server-side capability and URL validation before it is exposed.
         */
        return apply_filters( 'mvm_hub4_tool_registry', $tools );
    }

    public static function available_for_current_user(): array {
        $available = array();

        foreach ( self::registry() as $id => $tool ) {
            if ( empty( $tool['available'] ) ) {
                continue;
            }
            $capability = sanitize_key( (string) ( $tool['capability'] ?? '' ) );
            if ( '' === $capability || ( ! current_user_can( $capability ) && ! current_user_can( 'manage_options' ) ) ) {
                continue;
            }
            $url = self::safe_url( (string) ( $tool['url'] ?? '' ) );
            if ( '' === $url ) {
                continue;
            }

            $available[ sanitize_key( (string) $id ) ] = array(
                'id'          => sanitize_key( (string) $id ),
                'title'       => sanitize_text_field( (string) ( $tool['title'] ?? $id ) ),
                'description' => sanitize_text_field( (string) ( $tool['description'] ?? '' ) ),
                'category'    => sanitize_text_field( (string) ( $tool['category'] ?? 'Overig' ) ),
                'target'      => 'new' === ( $tool['target'] ?? '' ) ? 'new' : 'same',
            );
        }

        return array_values( $available );
    }

    public static function resolve_for_current_user( string $id ): array|WP_Error {
        $id       = sanitize_key( $id );
        $registry = self::registry();
        if ( ! isset( $registry[ $id ] ) || empty( $registry[ $id ]['available'] ) ) {
            return new WP_Error( 'mvm_hub4_tool_unavailable', 'Deze werkplaatstool is niet beschikbaar.', array( 'status' => 404 ) );
        }

        $tool       = $registry[ $id ];
        $capability = sanitize_key( (string) ( $tool['capability'] ?? '' ) );
        if ( '' === $capability || ( ! current_user_can( $capability ) && ! current_user_can( 'manage_options' ) ) ) {
            return new WP_Error( 'mvm_hub4_tool_forbidden', 'Je hebt geen toegang tot deze werkplaatstool.', array( 'status' => 403 ) );
        }

        $url = self::safe_url( (string) ( $tool['url'] ?? '' ) );
        if ( '' === $url ) {
            return new WP_Error( 'mvm_hub4_tool_invalid_url', 'De veilige doel-URL kon niet worden vastgesteld.', array( 'status' => 500 ) );
        }

        return array(
            'id'     => $id,
            'url'    => $url,
            'target' => 'new' === ( $tool['target'] ?? '' ) ? 'new' : 'same',
        );
    }

    private static function safe_url( string $url ): string {
        $url = esc_url_raw( $url, array( 'http', 'https' ) );
        if ( '' === $url ) {
            return '';
        }

        $site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
        $url_host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( '' === $site_host || '' === $url_host || $site_host !== $url_host ) {
            return '';
        }

        return $url;
    }
}
