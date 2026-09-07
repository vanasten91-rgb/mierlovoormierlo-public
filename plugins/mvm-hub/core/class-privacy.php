<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central privacy policy for Hub payloads.
 *
 * Rule: callers project from an explicit allow-list. Sensitive fields are never
 * included merely because the current user can access the parent object.
 */
final class Privacy {
    public const PUBLIC_DATA       = 'public';
    public const INTERNAL_DATA     = 'internal';
    public const CONFIDENTIAL_DATA = 'confidential';
    public const SECRET_DATA       = 'secret';

    private const TODAY_ASSIGNMENT_FIELDS = array(
        'id',
        'type',
        'status',
        'priority',
        'title',
        'due_at_utc',
        'news_post_id',
        'event_post_id',
        'source_post_id',
        'updated_at_utc',
    );

    /**
     * Known sensitive fields. These require a dedicated need-to-know endpoint
     * and must never leak into generic dashboards, logs or client config.
     */
    private const SENSITIVE_FIELDS = array(
        'contact_name'      => self::CONFIDENTIAL_DATA,
        'contact_email'     => self::CONFIDENTIAL_DATA,
        'contact_phone'     => self::CONFIDENTIAL_DATA,
        'private_note'      => self::CONFIDENTIAL_DATA,
        'brief'             => self::INTERNAL_DATA,
        'internal_brief'    => self::CONFIDENTIAL_DATA,
        'message'           => self::CONFIDENTIAL_DATA,
        'subject'           => self::CONFIDENTIAL_DATA,
        'email'             => self::CONFIDENTIAL_DATA,
        'from'              => self::CONFIDENTIAL_DATA,
        'to'                => self::CONFIDENTIAL_DATA,
        'cc'                => self::CONFIDENTIAL_DATA,
        'bcc'               => self::CONFIDENTIAL_DATA,
        'reply_to'          => self::CONFIDENTIAL_DATA,
        'reply_to_email'    => self::CONFIDENTIAL_DATA,
        'from_email'        => self::CONFIDENTIAL_DATA,
        'message_body'      => self::CONFIDENTIAL_DATA,
        'html_body'         => self::CONFIDENTIAL_DATA,
        'text_body'         => self::CONFIDENTIAL_DATA,
        'thread_body'       => self::CONFIDENTIAL_DATA,
        'attachment_name'   => self::CONFIDENTIAL_DATA,
        'attachment_path'   => self::SECRET_DATA,
        'storage_key'       => self::SECRET_DATA,
        'mailbox_password'  => self::SECRET_DATA,
        'imap_password'     => self::SECRET_DATA,
        'smtp_password'     => self::SECRET_DATA,
        'oauth_token'       => self::SECRET_DATA,
        'refresh_token'     => self::SECRET_DATA,
        'password'          => self::SECRET_DATA,
        'api_key'           => self::SECRET_DATA,
        'token'             => self::SECRET_DATA,
        'secret'            => self::SECRET_DATA,
    );

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function project_today_assignment( array $row ): array {
        return array_intersect_key( $row, array_fill_keys( self::TODAY_ASSIGNMENT_FIELDS, true ) );
    }

    public static function field_classification( string $field ): string {
        $key = sanitize_key( $field );
        if ( isset( self::SENSITIVE_FIELDS[ $key ] ) ) {
            return self::SENSITIVE_FIELDS[ $key ];
        }

        return self::INTERNAL_DATA;
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public static function redact_for_audit( array $context ): array {
        foreach ( $context as $key => $value ) {
            $normalized = sanitize_key( (string) $key );
            $class      = self::field_classification( $normalized );

            if ( self::SECRET_DATA === $class || self::CONFIDENTIAL_DATA === $class ) {
                $context[ $key ] = '[redacted]';
                continue;
            }

            if ( is_array( $value ) ) {
                $context[ $key ] = self::redact_for_audit( $value );
            }
        }

        return $context;
    }

    private function __construct() {}
}
