<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure readiness profile for a Mail V2 provider.
 *
 * Capability presence is necessary but never sufficient for production
 * promotion; V5_Mail_Cutover_Gate adds the operational/security gates.
 */
final class V5_Mail_Provider_Profile {
    /**
     * @param array<string,bool|int|string> $mail_capabilities
     * @param array<string,bool|int|string> $sync_capabilities
     * @return array<string,mixed>
     */
    public static function evaluate( array $mail_capabilities, array $sync_capabilities = array() ): array {
        $receive_required = array(
            'read',
            'folders',
            'attachments',
            'flags',
            'move',
            'folderScopedRead',
            'folderScopedMutations',
            'attachmentFetch',
        );
        $send_required = array( 'send' );
        $sync_required = array( 'initial', 'delta', 'deletions', 'cursorReset' );

        $receive_missing = self::missing_true( $mail_capabilities, $receive_required );
        $send_missing    = self::missing_true( $mail_capabilities, $send_required );
        $sync_missing    = self::missing_true( $sync_capabilities, $sync_required );

        $receive_ready = array() === $receive_missing && array() === $sync_missing;
        $send_ready    = array() === $send_missing;

        return array(
            'readyForReceive'       => $receive_ready,
            'readyForSend'          => $send_ready,
            'readyForBidirectional' => $receive_ready && $send_ready,
            'missingReceive'        => $receive_missing,
            'missingSend'           => $send_missing,
            'missingSync'           => $sync_missing,
            'providerCapabilities'  => self::project( $mail_capabilities, array_merge( $receive_required, $send_required ) ),
            'syncCapabilities'      => self::project( $sync_capabilities, $sync_required ),
        );
    }

    /**
     * @param array<string,bool|int|string> $capabilities
     * @param list<string> $required
     * @return list<string>
     */
    private static function missing_true( array $capabilities, array $required ): array {
        $missing = array();
        foreach ( $required as $name ) {
            if ( true !== ( $capabilities[ $name ] ?? false ) ) {
                $missing[] = $name;
            }
        }
        return $missing;
    }

    /**
     * @param array<string,bool|int|string> $capabilities
     * @param list<string> $allowed
     * @return array<string,bool>
     */
    private static function project( array $capabilities, array $allowed ): array {
        $projected = array();
        foreach ( array_values( array_unique( $allowed ) ) as $name ) {
            $projected[ $name ] = true === ( $capabilities[ $name ] ?? false );
        }
        return $projected;
    }

    private function __construct() {}
}
