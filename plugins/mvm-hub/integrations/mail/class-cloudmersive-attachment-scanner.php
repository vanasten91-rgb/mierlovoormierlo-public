<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fail-closed Cloudmersive Virus Scan API adapter for private Mail attachments.
 *
 * Configuration is server-side only. The scanner posts the private file via
 * verified HTTPS using cURL, with a synthetic filename and without mailbox,
 * draft or user metadata. Only an explicit CleanResult=true with no reported
 * viruses can release the attachment.
 */
final class Cloudmersive_Attachment_Scanner implements Attachment_Scanner {
    private const MAX_FILE_BYTES      = 10 * 1024 * 1024;
    private const MAX_RESPONSE_BYTES  = 16384;
    private const CONNECT_TIMEOUT_SEC = 3;
    private const TIMEOUT_SEC         = 12;
    private const SAFE_FILENAME       = 'attachment.bin';

    /** @var array<string,string>|null */
    private ?array $config_override;

    /** @var callable|null */
    private $transport;

    /**
     * @param array<string,string>|null $config_override Test-only configuration override.
     * @param callable|null             $transport       Test-only HTTP transport override.
     */
    public function __construct( ?array $config_override = null, ?callable $transport = null ) {
        $this->config_override = $config_override;
        $this->transport       = $transport;
    }

    public static function configured(): bool {
        return null !== self::validate_config( self::constant_config() ) && function_exists( 'curl_init' );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function scan( array $normalized_upload ): array|\WP_Error {
        $config = self::validate_config( $this->config_override ?? self::constant_config() );
        if ( null === $config ) {
            return new \WP_Error( 'mvm_mail_cloudmersive_unconfigured', 'De externe malware-scanner is niet veilig geconfigureerd.' );
        }

        $path = (string) ( $normalized_upload['privateHandle'] ?? '' );
        if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
            return new \WP_Error( 'mvm_mail_attachment_scan_source', 'De bijlage kan niet veilig worden gescand.' );
        }

        $actual_size = filesize( $path );
        $stated_size = (int) ( $normalized_upload['sizeBytes'] ?? -1 );
        if ( false === $actual_size || $stated_size < 0 || (int) $actual_size !== $stated_size ) {
            return new \WP_Error( 'mvm_mail_cloudmersive_size', 'De bijlagegrootte kon niet betrouwbaar worden gevalideerd.' );
        }
        if ( 0 === $stated_size || $stated_size > self::MAX_FILE_BYTES ) {
            return new \WP_Error( 'mvm_mail_cloudmersive_size_limit', 'De bijlage valt buiten de veilige scanlimiet.' );
        }

        $mime_type = sanitize_mime_type( (string) ( $normalized_upload['mimeType'] ?? '' ) );
        if ( '' === $mime_type ) {
            return new \WP_Error( 'mvm_mail_cloudmersive_mime', 'Het server-side MIME-type ontbreekt of is ongeldig.' );
        }

        $options = array(
            'connectTimeout' => self::CONNECT_TIMEOUT_SEC,
            'timeout'        => self::TIMEOUT_SEC,
            'maxResponse'    => self::MAX_RESPONSE_BYTES,
            'safeFilename'   => self::SAFE_FILENAME,
        );

        if ( is_callable( $this->transport ) ) {
            $response = call_user_func( $this->transport, $config['url'], $config['apiKey'], $path, $mime_type, $options );
        } else {
            $response = $this->curl_request( $config['url'], $config['apiKey'], $path, $mime_type );
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }
        if ( ! is_array( $response ) ) {
            return new \WP_Error( 'mvm_mail_cloudmersive_response', 'De externe malware-scanner gaf een ongeldige respons.' );
        }

        $status_code = (int) ( $response['statusCode'] ?? 0 );
        $body        = $response['body'] ?? null;
        if ( 200 !== $status_code || ! is_string( $body ) || '' === $body || strlen( $body ) > self::MAX_RESPONSE_BYTES ) {
            return new \WP_Error( 'mvm_mail_cloudmersive_http', 'De externe malware-scanner heeft geen geldige vrijgave gegeven.', array( 'statusCode' => $status_code ) );
        }

        try {
            $payload = json_decode( $body, true, 16, JSON_THROW_ON_ERROR );
        } catch ( \JsonException $exception ) {
            unset( $exception );
            return new \WP_Error( 'mvm_mail_cloudmersive_response', 'De externe malware-scanner gaf een ongeldige respons.' );
        }
        if ( ! is_array( $payload ) || ! array_key_exists( 'CleanResult', $payload ) || ! is_bool( $payload['CleanResult'] ) ) {
            return new \WP_Error( 'mvm_mail_cloudmersive_inconclusive', 'De externe malware-scanner heeft de bijlage niet expliciet vrijgegeven.' );
        }

        $found = $payload['FoundViruses'] ?? array();
        if ( ! is_array( $found ) ) {
            return new \WP_Error( 'mvm_mail_cloudmersive_inconclusive', 'De externe malware-scanner gaf een inconsistente respons.' );
        }

        if ( true !== $payload['CleanResult'] || array() !== $found ) {
            return new \WP_Error( 'mvm_mail_attachment_infected', 'De bijlage is door de beveiligingsscan geblokkeerd.' );
        }

        $sha256 = hash_file( 'sha256', $path );
        if ( ! is_string( $sha256 ) || 64 !== strlen( $sha256 ) ) {
            return new \WP_Error( 'mvm_mail_cloudmersive_digest', 'De gescande bijlage kon niet betrouwbaar worden gebonden.' );
        }

        return array(
            'status' => 'clean',
            'engine' => 'cloudmersive',
            'sha256' => $sha256,
        );
    }

    /** @return array{statusCode:int,body:string}|\WP_Error */
    private function curl_request( string $url, string $api_key, string $path, string $mime_type ): array|\WP_Error {
        if ( ! function_exists( 'curl_init' ) || ! class_exists( '\\CURLFile' ) ) {
            return new \WP_Error( 'mvm_mail_cloudmersive_transport', 'De veilige cURL-transportlaag voor de malware-scanner is niet beschikbaar.' );
        }

        $handle = curl_init();
        if ( false === $handle ) {
            return new \WP_Error( 'mvm_mail_cloudmersive_transport', 'De externe malware-scanner kon niet veilig worden gestart.' );
        }

        $body = '';
        $file = new \CURLFile( $path, $mime_type, self::SAFE_FILENAME );

        $options = array(
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SEC,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SEC,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => array(
                'Accept: application/json',
                'Apikey: ' . $api_key,
            ),
            CURLOPT_POSTFIELDS     => array( 'inputFile' => $file ),
            CURLOPT_WRITEFUNCTION  => static function ( $curl, string $chunk ) use ( &$body ): int {
                unset( $curl );
                if ( strlen( $body ) + strlen( $chunk ) > self::MAX_RESPONSE_BYTES ) {
                    return 0;
                }
                $body .= $chunk;
                return strlen( $chunk );
            },
        );

        if ( defined( 'CURLOPT_PROTOCOLS' ) && defined( 'CURLPROTO_HTTPS' ) ) {
            $options[ CURLOPT_PROTOCOLS ] = CURLPROTO_HTTPS;
        }

        curl_setopt_array( $handle, $options );
        $ok          = curl_exec( $handle );
        $status_code = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
        $curl_errno  = curl_errno( $handle );
        curl_close( $handle );

        if ( false === $ok || 0 !== $curl_errno ) {
            return new \WP_Error( 'mvm_mail_cloudmersive_network', 'De externe malware-scanner is tijdelijk niet bereikbaar.' );
        }

        return array(
            'statusCode' => $status_code,
            'body'       => $body,
        );
    }

    /** @return array<string,string> */
    private static function constant_config(): array {
        return array(
            'url'    => defined( 'MVM_HUB_CLOUDMERSIVE_SCAN_URL' ) ? (string) MVM_HUB_CLOUDMERSIVE_SCAN_URL : '',
            'apiKey' => defined( 'MVM_HUB_CLOUDMERSIVE_API_KEY' ) ? (string) MVM_HUB_CLOUDMERSIVE_API_KEY : '',
        );
    }

    /**
     * @param array<string,string> $config
     * @return array{url:string,apiKey:string}|null
     */
    private static function validate_config( array $config ): ?array {
        $url     = trim( (string) ( $config['url'] ?? '' ) );
        $api_key = trim( (string) ( $config['apiKey'] ?? '' ) );

        if ( '' === $url || strlen( $url ) > 2048 || strlen( $api_key ) < 16 || strlen( $api_key ) > 512 ) {
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

        $path = rtrim( (string) ( $parts['path'] ?? '' ), '/' );
        if ( ! str_ends_with( $path, '/virus/scan/file' ) ) {
            return null;
        }

        return array(
            'url'    => $url,
            'apiKey' => $api_key,
        );
    }
}
