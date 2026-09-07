<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant fail-closed media submission policy for Hub V5.
 *
 * A photographer upload is private by default. Moving media to public
 * visibility is a separate editorial decision and requires complete rights
 * metadata plus an explicit publication approval/capability signal from the
 * caller. Source-protected/confidential/secret originals are never directly
 * eligible for public visibility through this policy.
 *
 * This primitive registers no WordPress hooks and performs no persistence.
 */
final class V5_Media_Submission_Policy {
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_PUBLIC  = 'public';

    /** @var list<string> */
    private const RIGHTS_STATUSES = array(
        'owned',
        'licensed',
        'permission',
        'public_domain',
    );

    /**
     * @param array<string,mixed> $submission
     * @param array<string,mixed> $context
     * @return array{allowed:bool,reason:string,classification:string,requested_visibility:string,effective_visibility:string,rights_complete:bool,publication_eligible:bool}
     */
    public static function decision( array $submission, array $context = array() ): array {
        $classification = Data_Classification::normalize( (string) ( $submission['classification'] ?? '' ) );
        $requested       = sanitize_key( (string) ( $submission['visibility'] ?? self::VISIBILITY_PRIVATE ) );
        $requested       = self::VISIBILITY_PUBLIC === $requested ? self::VISIBILITY_PUBLIC : self::VISIBILITY_PRIVATE;
        $rights_status   = sanitize_key( (string) ( $submission['rights_status'] ?? '' ) );
        $credit          = trim( (string) ( $submission['credit'] ?? '' ) );
        $rights_ref      = trim( (string) ( $submission['rights_reference'] ?? '' ) );
        $rights_complete = self::rights_complete( $rights_status, $credit, $rights_ref );

        $decision = array(
            'allowed'                => false,
            'reason'                 => 'private_upload',
            'classification'         => $classification,
            'requested_visibility'   => $requested,
            'effective_visibility'   => self::VISIBILITY_PRIVATE,
            'rights_complete'        => $rights_complete,
            'publication_eligible'   => false,
        );

        // Uploading is allowed as a private staging action by default. The
        // caller must explicitly request public visibility to enter the
        // publication branch below.
        if ( self::VISIBILITY_PUBLIC !== $requested ) {
            $decision['allowed'] = true;
            return $decision;
        }

        if ( in_array(
            $classification,
            array(
                Data_Classification::CONFIDENTIAL,
                Data_Classification::SOURCE_PROTECTED,
                Data_Classification::SECRET,
            ),
            true
        ) ) {
            $decision['reason'] = 'classification_not_publishable';
            return $decision;
        }

        if ( ! $rights_complete ) {
            $decision['reason'] = 'rights_metadata_required';
            return $decision;
        }

        if ( true !== ( $context['editor_approved'] ?? false ) ) {
            $decision['reason'] = 'editor_approval_required';
            return $decision;
        }

        if ( true !== ( $context['actor_can_publish'] ?? false ) ) {
            $decision['reason'] = 'publisher_capability_required';
            return $decision;
        }

        $decision['allowed']              = true;
        $decision['reason']               = 'ok_publication';
        $decision['effective_visibility'] = self::VISIBILITY_PUBLIC;
        $decision['publication_eligible'] = true;

        return $decision;
    }

    /** @param array<string,mixed> $submission @param array<string,mixed> $context */
    public static function allows( array $submission, array $context = array() ): bool {
        return true === self::decision( $submission, $context )['allowed'];
    }

    private static function rights_complete( string $status, string $credit, string $reference ): bool {
        if ( ! in_array( $status, self::RIGHTS_STATUSES, true ) || '' === $credit ) {
            return false;
        }

        // Licensed, permission-based and public-domain media must carry a
        // traceable reference. Owned media can be evidenced internally by the
        // recorded owner/status plus credit.
        if ( in_array( $status, array( 'licensed', 'permission', 'public_domain' ), true ) && '' === $reference ) {
            return false;
        }

        return true;
    }

    private function __construct() {}
}
