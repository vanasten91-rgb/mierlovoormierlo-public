<?php

declare(strict_types=1);

// Standalone executable contract for the dormant Intake owner catalog.
define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( mixed $value ): string {
        $value = strtolower( (string) $value );
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }
}

require_once __DIR__ . '/../plugins/mvm-hub/modules/intake/class-v5-intake-owner-catalog.php';

use MVM\Hub\Modules\Intake\V5_Intake_Owner_Catalog;

function mvm_v5_intake_owner_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$contact = V5_Intake_Owner_Catalog::get( 'general_contact' );
mvm_v5_intake_owner_assert( is_array( $contact ), 'general contact source must be catalogued' );
mvm_v5_intake_owner_assert( '/contact/' === $contact['public_route'], 'general contact route must stay explicit' );
mvm_v5_intake_owner_assert( 487 === $contact['form_id'], 'Contact Form 7 form 487 must remain the observed current owner contract' );
mvm_v5_intake_owner_assert( 'contact-form-7' === $contact['current_owner'], 'general contact owner must be Contact Form 7' );
mvm_v5_intake_owner_assert( 'resolved' === $contact['owner_resolution'], 'general contact owner resolution must be explicit' );
mvm_v5_intake_owner_assert( false === $contact['v5_write_provider_allowed'], 'resolved ownership alone must not authorize V5 write takeover' );
mvm_v5_intake_owner_assert( false === V5_Intake_Owner_Catalog::can_promote_to_v5_write_provider( 'general_contact' ), 'general contact promotion must remain denied until a separate release enables it' );

$tip = V5_Intake_Owner_Catalog::get( 'news_tip' );
mvm_v5_intake_owner_assert( is_array( $tip ), 'news tip source must be catalogued' );
mvm_v5_intake_owner_assert( in_array( '/nieuws-insturen/', $tip['public_routes'], true ), 'news submission route must stay explicit' );
mvm_v5_intake_owner_assert( in_array( '/tip-de-redactie/', $tip['public_routes'], true ), 'tip route must stay explicit' );
mvm_v5_intake_owner_assert( 'mvm_news_submit_form' === $tip['shortcode'], 'current news submit shortcode contract must be pinned' );
mvm_v5_intake_owner_assert( 'mvm_bh_news_submit' === $tip['admin_post_action'], 'current admin-post action must be pinned' );
mvm_v5_intake_owner_assert( 'callback_owner_unresolved' === $tip['owner_resolution'], 'unproven callback owner must remain unresolved' );
mvm_v5_intake_owner_assert( false === V5_Intake_Owner_Catalog::can_promote_to_v5_write_provider( 'news_tip' ), 'unresolved news tip owner must never be promoted to a V5 write provider' );

$unresolved = V5_Intake_Owner_Catalog::unresolved();
mvm_v5_intake_owner_assert( array( 'news_tip' ) === $unresolved, 'only news_tip should currently be unresolved' );
mvm_v5_intake_owner_assert( null === V5_Intake_Owner_Catalog::get( 'newsletter' ), 'newsletter must stay outside editorial Intake ownership' );
mvm_v5_intake_owner_assert( false === V5_Intake_Owner_Catalog::can_promote_to_v5_write_provider( 'unknown' ), 'unknown intake sources must fail closed' );

echo "PASS: MvM Hub V5 Intake owner catalog and write-promotion gate\n";
