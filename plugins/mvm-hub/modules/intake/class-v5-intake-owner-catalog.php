<?php

namespace MVM\Hub\Modules\Intake;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant catalog of currently observed public intake entry points.
 *
 * This is migration evidence, not a runtime router. It deliberately separates
 * observed route/action contracts from a proven canonical storage/callback owner.
 */
final class V5_Intake_Owner_Catalog {
    /** @return array<string,array<string,mixed>> */
    public static function all(): array {
        return array(
            'general_contact' => array(
                'label'                    => 'Algemeen contact',
                'public_route'             => '/contact/',
                'page_id'                  => 140,
                'entry_contract'           => 'contact-form-7',
                'form_id'                  => 487,
                'current_owner'            => 'contact-form-7',
                'owner_resolution'         => 'resolved',
                'v5_write_provider_allowed'=> false,
                'classification_default'   => 'confidential',
            ),
            'news_tip' => array(
                'label'                    => 'Nieuws insturen / Tip de redactie',
                'public_routes'            => array( '/nieuws-insturen/', '/tip-de-redactie/' ),
                'page_ids'                 => array( 9483, 10366 ),
                'shortcode'                => 'mvm_news_submit_form',
                'admin_post_action'        => 'mvm_bh_news_submit',
                'current_owner'            => 'legacy-news-submit-runtime',
                'owner_resolution'         => 'callback_owner_unresolved',
                'v5_write_provider_allowed'=> false,
                'classification_default'   => 'confidential',
            ),
        );
    }

    /** @return array<string,mixed>|null */
    public static function get( string $key ): ?array {
        $key = sanitize_key( $key );
        $all = self::all();
        return $all[ $key ] ?? null;
    }

    public static function can_promote_to_v5_write_provider( string $key ): bool {
        $record = self::get( $key );
        if ( null === $record ) {
            return false;
        }

        return 'resolved' === (string) ( $record['owner_resolution'] ?? '' )
            && true === ( $record['v5_write_provider_allowed'] ?? false );
    }

    /** @return list<string> */
    public static function unresolved(): array {
        $keys = array();
        foreach ( self::all() as $key => $record ) {
            if ( 'resolved' !== (string) ( $record['owner_resolution'] ?? '' ) ) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    private function __construct() {}
}
