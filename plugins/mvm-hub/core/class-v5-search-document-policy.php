<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant search-document security policy for Hub V5.
 *
 * This primitive decides whether an already-normalized document may enter a
 * particular shared search index or be returned from it. It performs no query,
 * index write, WordPress hook registration, persistence or logging.
 */
final class V5_Search_Document_Policy {
    public const SCOPE_PUBLIC = 'public';
    public const SCOPE_STAFF  = 'staff';

    /**
     * @param array<string,mixed> $document
     * @param array<string,mixed> $context
     * @return array{allowed:bool,reason:string,classification:string,scope:string,search_visibility:string,requires_object_policy:bool,shared_index:bool}
     */
    public static function decision( array $document, array $context ): array {
        $classification    = Data_Classification::normalize( (string) ( $document['classification'] ?? '' ) );
        $scope             = sanitize_key( (string) ( $context['scope'] ?? '' ) );
        $search_visibility = sanitize_key( (string) ( $document['search_visibility'] ?? 'inherit' ) );
        $search_visibility = '' === $search_visibility ? 'inherit' : $search_visibility;
        $shared_index      = true !== ( $context['direct_object_lookup'] ?? false );

        $decision = array(
            'allowed'                => false,
            'reason'                 => 'invalid_scope',
            'classification'         => $classification,
            'scope'                  => $scope,
            'search_visibility'      => $search_visibility,
            'requires_object_policy' => false,
            'shared_index'           => $shared_index,
        );

        if ( ! in_array( $scope, array( self::SCOPE_PUBLIC, self::SCOPE_STAFF ), true ) ) {
            return $decision;
        }

        // Some internal content is intentionally never discoverable, even to
        // general staff search. Examples: staff board and team-chat messages.
        if ( 'none' === $search_visibility ) {
            $decision['reason'] = 'search_visibility_denied';
            return $decision;
        }

        if ( self::SCOPE_PUBLIC === $scope ) {
            if ( Data_Classification::PUBLIC !== $classification ) {
                $decision['reason'] = 'classification_not_public';
                return $decision;
            }

            $decision['allowed'] = true;
            $decision['reason']  = 'ok';
            return $decision;
        }

        if ( Data_Classification::SECRET === $classification ) {
            $decision['reason'] = 'secret_never_indexed';
            return $decision;
        }

        if ( Data_Classification::SOURCE_PROTECTED === $classification ) {
            $decision['requires_object_policy'] = true;

            if ( $shared_index ) {
                $decision['reason'] = 'source_protected_shared_index_denied';
                return $decision;
            }

            if ( true !== ( $context['object_access_allowed'] ?? false ) ) {
                $decision['reason'] = 'object_access_denied';
                return $decision;
            }

            if ( true !== ( $context['step_up_satisfied'] ?? false ) ) {
                $decision['reason'] = 'step_up_required';
                return $decision;
            }

            $decision['allowed'] = true;
            $decision['reason']  = 'ok_direct_object_lookup';
            return $decision;
        }

        if ( Data_Classification::CONFIDENTIAL === $classification ) {
            $decision['requires_object_policy'] = true;

            if ( $shared_index ) {
                $decision['reason'] = 'confidential_shared_index_denied';
                return $decision;
            }

            if ( true !== ( $context['object_access_allowed'] ?? false ) ) {
                $decision['reason'] = 'object_access_denied';
                return $decision;
            }

            $decision['allowed'] = true;
            $decision['reason']  = 'ok_direct_object_lookup';
            return $decision;
        }

        if ( Data_Classification::allows_general_staff_search( $classification ) ) {
            $decision['allowed'] = true;
            $decision['reason']  = 'ok';
            return $decision;
        }

        $decision['reason'] = 'classification_denied';
        return $decision;
    }

    /** @param array<string,mixed> $document @param array<string,mixed> $context */
    public static function allows( array $document, array $context ): bool {
        return true === self::decision( $document, $context )['allowed'];
    }

    /**
     * Protect result rendering independently from index construction.
     * A stale/misconfigured index must not become an authorization bypass.
     *
     * @param list<array<string,mixed>> $documents
     * @param array<string,mixed> $context
     * @return list<array<string,mixed>>
     */
    public static function filter_results( array $documents, array $context ): array {
        $allowed = array();

        foreach ( $documents as $document ) {
            if ( ! is_array( $document ) || ! self::allows( $document, $context ) ) {
                continue;
            }

            $allowed[] = $document;
        }

        return array_values( $allowed );
    }

    private function __construct() {}
}
