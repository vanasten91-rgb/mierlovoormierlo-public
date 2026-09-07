<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Canonical V5 data-classification contract.
 *
 * Unknown values deliberately fail closed to CONFIDENTIAL. This class is a
 * policy primitive only; object access and capabilities remain separate checks.
 */
final class Data_Classification {
    public const PUBLIC           = 'public';
    public const INTERNAL         = 'internal';
    public const CONFIDENTIAL     = 'confidential';
    public const SOURCE_PROTECTED = 'source_protected';
    public const SECRET           = 'secret';

    /** @return list<string> */
    public static function all(): array {
        return array(
            self::PUBLIC,
            self::INTERNAL,
            self::CONFIDENTIAL,
            self::SOURCE_PROTECTED,
            self::SECRET,
        );
    }

    public static function is_known( string $classification ): bool {
        return in_array( sanitize_key( $classification ), self::all(), true );
    }

    public static function normalize( string $classification ): string {
        $classification = sanitize_key( $classification );

        return self::is_known( $classification )
            ? $classification
            : self::CONFIDENTIAL;
    }

    /**
     * Public/internal data may be represented in the ordinary staff index.
     * Confidential data needs an explicitly scoped index/query path instead.
     */
    public static function allows_general_staff_search( string $classification ): bool {
        return in_array(
            self::normalize( $classification ),
            array( self::PUBLIC, self::INTERNAL ),
            true
        );
    }

    /**
     * V5 defaults external AI to public data only. Feature-specific approved
     * policies may become stricter but must not bypass this primitive for
     * source-protected or secret content.
     */
    public static function allows_external_ai_by_default( string $classification ): bool {
        return self::PUBLIC === self::normalize( $classification );
    }

    public static function requires_explicit_acl( string $classification ): bool {
        return in_array(
            self::normalize( $classification ),
            array( self::SOURCE_PROTECTED, self::SECRET ),
            true
        );
    }

    public static function may_include_body_in_general_audit( string $classification ): bool {
        // Audit is metadata-first for every classification. Body/content logging
        // requires a separate narrowly reviewed audit use case.
        return false;
    }

    private function __construct() {}
}
