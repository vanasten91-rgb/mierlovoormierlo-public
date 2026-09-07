<?php

namespace MVM\Hub\Modules\Intake;

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\V5_Read_Provider_Contract;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant read-provider declarations for Intake.
 *
 * No provider is allowed to expose submission bodies here. General contact is
 * metadata-only; the unresolved news-tip owner intentionally fails closed.
 */
final class V5_Intake_Read_Providers {
    /** @return array<string,array<string,mixed>> */
    public static function all(): array {
        return array(
            'general_contact' => array(
                'provider_key'           => 'contact_form_7_contract_metadata',
                'domain'                 => 'intake',
                'source_key'             => 'general_contact',
                'declared_owner'         => 'contact-form-7',
                'read_mode'              => V5_Read_Provider_Contract::MODE_METADATA_ONLY,
                'authorization_boundary' => 'pre_authorized_input',
                'write_capable'          => false,
                'classification'         => Data_Classification::CONFIDENTIAL,
                'required_capability'    => 'mvm_intake_view',
                'input_contract'         => 'contact_form_contract_metadata',
                'payload_access'         => false,
            ),
            'news_tip' => array(
                'provider_key'           => 'legacy_news_tip_projection_candidate',
                'domain'                 => 'intake',
                'source_key'             => 'news_tip',
                'declared_owner'         => 'legacy-news-submit-runtime',
                'read_mode'              => V5_Read_Provider_Contract::MODE_METADATA_ONLY,
                'authorization_boundary' => 'pre_authorized_input',
                'write_capable'          => false,
                'classification'         => Data_Classification::CONFIDENTIAL,
                'required_capability'    => 'mvm_intake_view',
                'input_contract'         => 'unresolved_news_tip_metadata',
                'payload_access'         => false,
            ),
        );
    }

    /** @return array<string,mixed>|null */
    public static function resolve( string $source_key ): ?array {
        $source_key = sanitize_key( $source_key );
        $providers  = self::all();
        $provider   = $providers[ $source_key ] ?? null;
        $owner      = V5_Intake_Owner_Catalog::get( $source_key );

        if ( null === $provider || null === $owner ) {
            return null;
        }

        $decision = V5_Read_Provider_Contract::decision( $provider, $owner );
        if ( true !== $decision['allowed'] ) {
            return null;
        }

        $provider['owner_decision'] = $decision;
        $provider['read_only_projection'] = true;
        return $provider;
    }

    /** @return array<string,mixed> */
    public static function decision( string $source_key ): array {
        $source_key = sanitize_key( $source_key );
        $provider   = self::all()[ $source_key ] ?? array();
        $owner      = V5_Intake_Owner_Catalog::get( $source_key ) ?? array();
        return V5_Read_Provider_Contract::decision( $provider, $owner );
    }

    private function __construct() {}
}
