<?php

namespace MVM\Hub\Modules\Newsroom\News;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Explicit Newsroom state machine contract.
 *
 * This class does not write in phase 1. Later mutation services must call this
 * transition policy before any post/status/meta change and must separately pass
 * object-access, nonce/session, validation and audit checks.
 */
final class News_Workflow {
    public const IDEA              = 'idea';
    public const ASSIGNED          = 'assigned';
    public const DRAFT             = 'draft';
    public const REVIEW            = 'review';
    public const CHANGES_REQUESTED = 'changes_requested';
    public const READY             = 'ready';
    public const SCHEDULED         = 'scheduled';
    public const PUBLISHED         = 'published';
    public const CORRECTION        = 'correction';

    /**
     * @return array<string,array<string,string>> from => [to => required capability]
     */
    public static function transitions(): array {
        return array(
            self::IDEA => array(
                self::ASSIGNED => Capabilities::ASSIGNMENTS_MANAGE,
                self::DRAFT    => Capabilities::NEWS_CREATE,
            ),
            self::ASSIGNED => array(
                self::DRAFT => Capabilities::NEWS_EDIT_OWN,
            ),
            self::DRAFT => array(
                self::REVIEW => Capabilities::NEWS_EDIT_OWN,
            ),
            self::REVIEW => array(
                self::CHANGES_REQUESTED => Capabilities::NEWS_REVIEW,
                self::READY             => Capabilities::NEWS_REVIEW,
            ),
            self::CHANGES_REQUESTED => array(
                self::DRAFT => Capabilities::NEWS_EDIT_OWN,
            ),
            self::READY => array(
                self::SCHEDULED => Capabilities::NEWS_PUBLISH,
                self::PUBLISHED => Capabilities::NEWS_PUBLISH,
            ),
            self::SCHEDULED => array(
                self::PUBLISHED => Capabilities::NEWS_PUBLISH,
            ),
            self::PUBLISHED => array(
                self::CORRECTION => Capabilities::CORRECTIONS_MANAGE,
            ),
            self::CORRECTION => array(
                self::PUBLISHED => Capabilities::NEWS_PUBLISH,
            ),
        );
    }

    public static function is_known_state( string $state ): bool {
        $state = sanitize_key( $state );
        return in_array(
            $state,
            array(
                self::IDEA,
                self::ASSIGNED,
                self::DRAFT,
                self::REVIEW,
                self::CHANGES_REQUESTED,
                self::READY,
                self::SCHEDULED,
                self::PUBLISHED,
                self::CORRECTION,
            ),
            true
        );
    }

    public static function required_capability( string $from, string $to ): ?string {
        $from = sanitize_key( $from );
        $to   = sanitize_key( $to );
        return self::transitions()[ $from ][ $to ] ?? null;
    }

    public static function can_transition( string $from, string $to ): bool {
        $capability = self::required_capability( $from, $to );
        if ( null === $capability ) {
            return false;
        }

        // manage_options remains an administrative emergency capability. Normal
        // editorial business logic is capability-based and never role-name based.
        if ( current_user_can( 'manage_options' ) || current_user_can( $capability ) ) {
            return true;
        }

        // During phase 1 the new capability migration is not active. No legacy
        // fallback is accepted for writes: future mutation migration must make
        // the new capability grants explicit before transition endpoints go live.
        return false;
    }

    private function __construct() {}
}
