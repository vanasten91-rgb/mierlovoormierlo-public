<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure normalization + storage contract for the eventual durable V5 outbox.
 */
final class V5_Domain_Event_Outbox_Contract {
    public const SCHEMA_VERSION = '2026.09.04-1';
    public const MAX_PAYLOAD_KEYS = 32;
    public const MAX_PAYLOAD_BYTES = 8192;

    /** @return array<string,mixed>|null */
    public static function normalize_event( array $event ): ?array {
        $name = sanitize_key( (string) ( $event['name'] ?? '' ) );
        $aggregate_type = sanitize_key( (string) ( $event['aggregateType'] ?? '' ) );
        $aggregate_id = self::opaque( (string) ( $event['aggregateId'] ?? '' ), 160 );
        $correlation_id = self::opaque( (string) ( $event['correlationId'] ?? '' ), 96 );
        $classification = Data_Classification::normalize( (string) ( $event['classification'] ?? '' ) );

        if ( '' === $name || '' === $aggregate_type || '' === $aggregate_id || '' === $correlation_id || '' === $classification ) {
            return null;
        }

        $payload = is_array( $event['payload'] ?? null ) ? $event['payload'] : array();
        if ( count( $payload ) > self::MAX_PAYLOAD_KEYS ) {
            return null;
        }

        $safe_payload = array();
        foreach ( $payload as $key => $value ) {
            $key = sanitize_key( (string) $key );
            if ( '' === $key || self::forbidden_payload_key( $key ) ) {
                continue;
            }
            if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
                $safe_payload[ $key ] = $value;
                continue;
            }
            if ( is_string( $value ) ) {
                $value = trim( wp_strip_all_tags( $value, true ) );
                $safe_payload[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 500 ) : substr( $value, 0, 500 );
            }
        }

        $json = wp_json_encode( $safe_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( false === $json || strlen( $json ) > self::MAX_PAYLOAD_BYTES ) {
            return null;
        }

        return array(
            'name' => $name,
            'aggregateType' => $aggregate_type,
            'aggregateId' => $aggregate_id,
            'correlationId' => $correlation_id,
            'classification' => $classification,
            'payload' => $safe_payload,
            'searchVisibility' => 'none',
            'aiVisibility' => 'none',
            'containsBody' => false,
            'containsCredentials' => false,
        );
    }

    /** @return array<string,mixed> */
    public static function storage_contract( string $prefix ): array {
        if ( '' === $prefix || 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $prefix ) ) {
            return array( 'valid' => false, 'schemaVersion' => self::SCHEMA_VERSION, 'sql' => '' );
        }
        $table = $prefix . 'mvm_domain_event_outbox';
        return array(
            'valid' => true,
            'schemaVersion' => self::SCHEMA_VERSION,
            'table' => $table,
            'productionWrites' => false,
            'requiresSeparateApproval' => true,
            'sql' => "CREATE TABLE {$table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, event_name VARCHAR(128) NOT NULL, aggregate_type VARCHAR(64) NOT NULL, aggregate_id VARCHAR(160) NOT NULL, correlation_id VARCHAR(96) NOT NULL, classification VARCHAR(32) NOT NULL, payload_json TEXT NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'pending', attempts INT UNSIGNED NOT NULL DEFAULT 0, available_at_utc DATETIME NOT NULL, created_at_utc DATETIME NOT NULL, delivered_at_utc DATETIME NULL, last_error_code VARCHAR(96) NOT NULL DEFAULT '', PRIMARY KEY (id), UNIQUE KEY correlation_event (correlation_id,event_name), KEY pending_available (status,available_at_utc), KEY aggregate_lookup (aggregate_type,aggregate_id))",
        );
    }

    private static function forbidden_payload_key( string $key ): bool {
        foreach ( array( 'body', 'html', 'content', 'message', 'subject', 'email', 'address', 'filename', 'path', 'url', 'token', 'secret', 'password', 'credential', 'authorization', 'source_identity', 'source_contact' ) as $needle ) {
            if ( str_contains( $key, $needle ) ) return true;
        }
        return false;
    }

    private static function opaque( string $value, int $max ): string {
        $value = trim( $value );
        if ( '' === $value || strlen( $value ) > $max ) return '';
        return 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : hash( 'sha256', $value );
    }

    private function __construct() {}
}
