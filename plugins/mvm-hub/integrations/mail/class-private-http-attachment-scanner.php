<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fail-closed private HTTPS malware scanner for Mail V2 attachments.
 *
 * The endpoint and HMAC key are server-side configuration only. Raw file bytes
 * are posted over verified TLS; no filename, mailbox identifier or user secret
 * is sent. Only an HTTP 200 response with status=clean and the exact request
 * ID + SHA-256 digest can release an attachment.
 */
final class Private_HTTP_Attachment_Scanner implements Attachment_Scanner {
    private const MAX_FILE_BYTES     = 10 * 1024 * 1024;
    private const MAX_RESPONSE_BYTES = 16384;
    private const TIMEOUT_SECONDS    = 5;

    /** @var array<string,string>|null */
    private ?array $config_override;

    /** @var callable|null */
    private $http_client;

    /**
     * Test-only dependency injection remains optional; production uses constants
     * and the WordPress HTTP client.
     *
     * @param array<string,string>|null $config_override
     * @param callable|null             $http_client
     */
    public function __construct( ?array $config_override = null, ?callable $http_client = null ) {
        $this->config_override = $config_override;
        $this->http_client     = $http_client;
    }

    public static function configured(): bool {
        return null !== self::validate_config( self::constant_config() );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function scan( array $normalized_upload ): array|\WP_Error {
        $config = self::validate_config( $this->config_override ?? self::constant_config() );
        if ( null === $config ) {
            return new \WP_Error( 'mvm_mail_private_scanner_unconfigured', 'De private malware-scanner is niet veilig geconfigureerd.' );
        }

        $path = (string) ( $normalized_upload['privateHandle'] ?? '' );
        if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
            return new \WP_Error( 'mvm_mail_attachment_scan_source', 'De bijlage kan niet veilig worden gescand.' );
        }

        $actual_size = filesize( $path );
        $stated_size = (int) ( $normalized_upload['sizeBytes'] ?? -1 );
        if ( false === $actual_size || $stated_size < 0 || (int) $actual_size !== $stated_size ) {
            return new \WP_Error( 'mvm_mail_private_scanner_size', 'De bijlagegrootte kon niet betrouwbaar worden gevalideerd.' );
        }
        if ( 0 === $stated_size || $stated_size > self::MAX_FILE_BYTES ) {
            return new \WP_Error( 'mvm_mail_private_scanner_size_limit', 'De bijlage valt buiten de veilige scanlimiet.' );
        }

        $mime_type = sanitize_mime_type( (string) ( $normalized_upload['mimeType'] ?? '' ) );
        if ( '' === $mime_type ) {
            return new \WP_Error( 'mvm_mail_private_scanner_mime', 'Het server-side MIME-type ontbreekt of is ongeldig.' );
        }

        $bytes = @file_get_contents( $path );
        if ( ! is_string( $bytes ) || strlen( $bytes ) !== $stated_size ) {
            return new \WP_Error( 'mvm_mail_private_scanner_read', 'De bijlage kon niet volledig voor de beveiligingsscan worden gelezen.' );
        }

        $sha256     = hash( 'sha256', $bytes );
        $request_id = $this->request_id();
        if ( is_wp_error( $request_id ) ) {
            return $request_id;
        }
        $timestamp = time();
        $canonical = implode(
            "\n",
            array( 'v1', $request_id, (string) $timestamp, $mime_type, (string) $stated_size, $sha256 )
        ) . "\n";
        $signature = hash_hmac( 'sha256', $canonical, $config['hmacKey'] );

        $headers = array(
            'Accept'                    => 'application/json',
            'Content-Type'              => 'application/octet-stream',
            'X-MVM-Scanner-Version'     => '1',
            'X-MVM-Scanner-Request-Id'  => $request_id,
            'X-MVM-Scanner-Timestamp'   => (string) $timestamp,
            'X-MVM-Scanner-Mime'        => $mime_type,
            'X-MVM-Scanner-Size'        => (string) $stated_size,
            'X-MVM-Scanner-Sha256'      => $sha256,
            'X-MVM-Scanner-Signature'   => 'v1=' . $signature,
        );
        if ( '' !== $config['keyId'] ) {
            $headers['X-MVM-Scanner-Key-Id'] = $config['keyId'];
        }

        $args = array(
            'body'                => $bytes,
            'headers'             => $headers,
            'timeout'             => self::TIMEOUT_SECONDS,
            'redirection'         => 0,
            'sslverify'           => true,
            'limit_response_size' => self::MAX_RESPONSE_BYTES,
            'data_format'         => 'body',
        );

        if ( is_callable( $this->http_client ) ) {
            $response = call_user_func( $this->http_client, $config['url'], $args );
        } else {
            if ( ! function_exists( 'wp_remote_post' ) ) {
                return new \WP_Error( 'mvm_mail_private_scanner_http_unavailable', 'De WordPress HTTP-client voor de malware-scanner is niet beschikbaar.' );
            }
            $response = wp_remote_post( $config['url'], $args );
        }

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'mvm_mail_private_scanner_network', 'De private malware-scanner is tijdelijk niet bereikbaar.' );
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            return new \WP_Error( 'mvm_mail_private_scanner_http', 'De private malware-scanner heeft geen geldige vrijgave gegeven.', array( 'statusCode' => $status_code ) );
        }

        $response_body = wp_remote_retrieve_body( $response );
        if ( ! is_string( $response_body ) || '' === $response_body || strlen( $response_body ) > self::MAX_RESPONSE_BYTES ) {
            return new \WP_Error( 'mvm_mail_private_scanner_response', 'De private malware-scanner gaf een ongeldige respons.' );
        }

        try {
            $payload = json_decode( $response_body, true, 16, JSON_THROW_ON_ERROR );
        } catch ( \JsonException $exception ) {
            unset( $exception );
            return new \WP_Error( 'mvm_mail_private_scanner_response', 'De private malware-scanner gaf een ongeldige respons.' );
        }
        if ( ! is_array( $payload ) ) {
            return new \WP_Error( 'mvm_mail_private_scanner_response', 'De private malware-scanner gaf een ongeldige respons.' );
        }

        $scanner_status = sanitize_key( (string) ( $payload['status'] ?? '' ) );
        if ( in_array( $scanner_status, array( 'infected', 'malicious', 'quarantined' ), true ) ) {
            return new \WP_Error( 'mvm_mail_attachment_infected', 'De bijlage is door de beveiligingsscan geblokkeerd.' );
        }
        if ( 'clean' !== $scanner_status ) {
            return new \WP_Error( 'mvm_mail_private_scanner_inconclusive', 'De private malware-scanner heeft de bijlage niet expliciet vrijgegeven.' );
        }

        $response_request_id = (string) ( $payload['requestId'] ?? '' );
        $response_sha256     = (string) ( $payload['sha256'] ?? '' );
        if ( '' === $response_request_id || ! hash_equals( $request_id, $response_request_id ) ) {
            return new \WP_Error( 'mvm_mail_private_scanner_request_mismatch', 'De scanrespons hoort niet aantoonbaar bij deze aanvraag.' );
        }
        if ( '' === $response_sha256 || ! hash_equals( $sha256, $response_sha256 ) ) {
            return new \WP_Error( 'mvm_mail_private_scanner_digest_mismatch', 'De scanrespons hoort niet aantoonbaar bij deze bijlage.' );
        }

        return array(
            'status'  => 'clean',
            'engine'  => 'private_https',
            'sha256'  => $sha256,
        );
    }

    /** @return array<string,string> */
    private static function constant_config(): array {
        return array(
            'url'     => defined( 'MVM_HUB_PRIVATE_SCANNER_URL' ) ? (string) MVM_HUB_PRIVATE_SCANNER_URL : '',
            'hmacKey' => defined( 'MVM_HUB_PRIVATE_SCANNER_HMAC_KEY' ) ? (string) MVM_HUB_PRIVATE_SCANNER_HMAC_KEY : '',
            'keyId'   => defined( 'MVM_HUB_PRIVATE_SCANNER_KEY_ID' ) ? (string) MVM_HUB_PRIVATE_SCANNER_KEY_ID : '',
        );
    }

    /**
     * @param array<string,string> $config
     * @return array{url:string,hmacKey:string,keyId:string}|null
     */
    private static function validate_config( array $config ): ?array {
        $url      = trim( (string) ( $config['url'] ?? '' ) );
        $hmac_key = (string) ( $config['hmacKey'] ?? '' );
        $key_id   = trim( (string) ( $config['keyId'] ?? '' ) );

        if ( '' === $url || strlen( $url ) > 2048 || strlen( $hmac_key ) < 32 ) {
            return null;
        }

        $parts = parse_url( $url );
        if ( ! is_array( $parts )
            || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) )
            || '' === (string) ( $parts['host'] ?? '' )
            || isset( $parts['user'] )
            || isset( $parts['pass'] )
            || isset( $parts['query'] )
            || isset( $parts['fragment'] )
        ) {
            return null;
        }

        if ( '' !== $key_id ) {
            $sanitized_key_id = sanitize_key( $key_id );
            if ( '' === $sanitized_key_id || $sanitized_key_id !== $key_id || strlen( $key_id ) > 64 ) {
                return null;
            }
        }

        return array(
            'url'     => $url,
            'hmacKey' => $hmac_key,
            'keyId'   => $key_id,
        );
    }

    /** @return string|\WP_Error */
    private function request_id(): string|\WP_Error {
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            $request_id = (string) wp_generate_uuid4();
            if ( '' !== $request_id ) {
                return $request_id;
            }
        }

        try {
            return bin2hex( random_bytes( 16 ) );
        } catch ( \Throwable $exception ) {
            unset( $exception );
            return new \WP_Error( 'mvm_mail_private_scanner_request_id', 'Er kon geen veilige scanidentificatie worden aangemaakt.' );
        }
    }
}
