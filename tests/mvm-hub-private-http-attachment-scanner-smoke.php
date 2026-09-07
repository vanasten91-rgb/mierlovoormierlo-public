<?php

define( 'ABSPATH', __DIR__ . '/' );

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private string $code;
        private string $message;
        private mixed $data;

        public function __construct( string $code = '', string $message = '', mixed $data = null ) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code(): string { return $this->code; }
        public function get_error_message(): string { return $this->message; }
        public function get_error_data(): mixed { return $this->data; }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
}
if ( ! function_exists( 'sanitize_mime_type' ) ) {
    function sanitize_mime_type( string $mime ): string {
        return preg_replace( '/[^A-Za-z0-9.+\/-]/', '', $mime ) ?? '';
    }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( string $key ): string {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
    }
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
    function wp_generate_uuid4(): string {
        static $counter = 0;
        ++$counter;
        return sprintf( '00000000-0000-4000-8000-%012d', $counter );
    }
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( mixed $response ): int {
        return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;
    }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( mixed $response ): string {
        return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
    }
}

require_once __DIR__ . '/../plugins/mvm-hub/integrations/mail/interface-attachment-scanner.php';
require_once __DIR__ . '/../plugins/mvm-hub/integrations/mail/class-private-http-attachment-scanner.php';

use MVM\Hub\Integrations\Mail\Private_HTTP_Attachment_Scanner;

function mvm_private_scanner_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function mvm_private_scanner_error_code( mixed $value ): string {
    return $value instanceof WP_Error ? $value->get_error_code() : '';
}

$fixture_path = tempnam( sys_get_temp_dir(), 'mvm-private-scanner-' );
mvm_private_scanner_assert( is_string( $fixture_path ) && '' !== $fixture_path, 'temporary scan fixture should be available' );
$fixture_bytes = "safe-private-scanner-fixture\n";
mvm_private_scanner_assert( strlen( $fixture_bytes ) === file_put_contents( $fixture_path, $fixture_bytes ), 'scan fixture should be written' );

$key    = str_repeat( 'k', 32 );
$config = array(
    'url'     => 'https://scanner.invalid/v1/scan',
    'hmacKey' => $key,
    'keyId'   => 'primary',
);
$upload = array(
    'privateHandle' => $fixture_path,
    'mimeType'      => 'application/pdf',
    'sizeBytes'     => strlen( $fixture_bytes ),
    'filename'      => 'sensitive-user-filename.pdf',
);

$mode     = 'clean';
$captures = array();
$client   = static function ( string $url, array $args ) use ( &$mode, &$captures ): array|WP_Error {
    $captures[] = array( 'url' => $url, 'args' => $args );

    if ( 'network' === $mode ) {
        return new WP_Error( 'http_request_failed', 'simulated timeout' );
    }

    $code       = 'http_error' === $mode ? 503 : 200;
    $request_id = (string) ( $args['headers']['X-MVM-Scanner-Request-Id'] ?? '' );
    $sha256     = (string) ( $args['headers']['X-MVM-Scanner-Sha256'] ?? '' );
    $payload    = array(
        'status'    => 'clean',
        'requestId' => $request_id,
        'sha256'    => $sha256,
    );

    if ( 'infected' === $mode ) {
        $payload['status'] = 'infected';
    } elseif ( 'pending' === $mode ) {
        $payload['status'] = 'pending';
    } elseif ( 'unknown' === $mode ) {
        $payload['status'] = 'unknown';
    } elseif ( 'wrong_sha' === $mode ) {
        $payload['sha256'] = str_repeat( '0', 64 );
    } elseif ( 'wrong_request' === $mode ) {
        $payload['requestId'] = 'different-request-id';
    } elseif ( 'invalid_json' === $mode ) {
        return array( 'response' => array( 'code' => $code ), 'body' => '{not-json' );
    }

    return array(
        'response' => array( 'code' => $code ),
        'body'     => json_encode( $payload, JSON_UNESCAPED_SLASHES ),
    );
};

try {
    $scanner = new Private_HTTP_Attachment_Scanner( $config, $client );
    $clean   = $scanner->scan( $upload );
    mvm_private_scanner_assert( is_array( $clean ) && 'clean' === ( $clean['status'] ?? '' ), 'valid clean response should release the attachment' );
    mvm_private_scanner_assert( 'private_https' === ( $clean['engine'] ?? '' ), 'clean result should identify the private HTTPS engine' );
    mvm_private_scanner_assert( 1 === count( $captures ), 'clean scan should perform one HTTP request' );

    $request = $captures[0];
    $args    = $request['args'];
    mvm_private_scanner_assert( $config['url'] === $request['url'], 'scanner must use the configured HTTPS endpoint' );
    mvm_private_scanner_assert( true === ( $args['sslverify'] ?? null ), 'TLS certificate verification must be enabled' );
    mvm_private_scanner_assert( 0 === ( $args['redirection'] ?? null ), 'redirects must be disabled' );
    mvm_private_scanner_assert( 5 === ( $args['timeout'] ?? null ), 'scanner timeout must stay bounded' );
    mvm_private_scanner_assert( 16384 === ( $args['limit_response_size'] ?? null ), 'scanner response size must stay bounded' );
    mvm_private_scanner_assert( $fixture_bytes === ( $args['body'] ?? null ), 'raw file bytes must be sent as the request body' );
    mvm_private_scanner_assert( 'application/octet-stream' === ( $args['headers']['Content-Type'] ?? '' ), 'request body must be generic binary content' );
    mvm_private_scanner_assert( 'application/pdf' === ( $args['headers']['X-MVM-Scanner-Mime'] ?? '' ), 'server-side MIME must be integrity-bound' );
    mvm_private_scanner_assert( (string) strlen( $fixture_bytes ) === ( $args['headers']['X-MVM-Scanner-Size'] ?? '' ), 'file size must be integrity-bound' );
    mvm_private_scanner_assert( hash( 'sha256', $fixture_bytes ) === ( $args['headers']['X-MVM-Scanner-Sha256'] ?? '' ), 'SHA-256 must be integrity-bound' );
    mvm_private_scanner_assert( 'primary' === ( $args['headers']['X-MVM-Scanner-Key-Id'] ?? '' ), 'optional key id should be sent without the key itself' );

    $request_id = (string) $args['headers']['X-MVM-Scanner-Request-Id'];
    $timestamp  = (string) $args['headers']['X-MVM-Scanner-Timestamp'];
    $sha256     = (string) $args['headers']['X-MVM-Scanner-Sha256'];
    $canonical  = implode( "\n", array( 'v1', $request_id, $timestamp, 'application/pdf', (string) strlen( $fixture_bytes ), $sha256 ) ) . "\n";
    $expected_signature = 'v1=' . hash_hmac( 'sha256', $canonical, $key );
    mvm_private_scanner_assert( hash_equals( $expected_signature, (string) ( $args['headers']['X-MVM-Scanner-Signature'] ?? '' ) ), 'request metadata must be HMAC-bound' );

    $serialized_request = json_encode( $request, JSON_UNESCAPED_SLASHES );
    mvm_private_scanner_assert( is_string( $serialized_request ), 'captured request should serialize for leakage checks' );
    mvm_private_scanner_assert( ! str_contains( $serialized_request, 'sensitive-user-filename.pdf' ), 'filename must never leave the WordPress server' );
    mvm_private_scanner_assert( ! str_contains( $serialized_request, $key ), 'HMAC secret must never be sent to the scanner' );

    foreach ( array( 'infected', 'pending', 'unknown', 'wrong_sha', 'wrong_request', 'http_error', 'network', 'invalid_json' ) as $case ) {
        $mode   = $case;
        $result = $scanner->scan( $upload );
        mvm_private_scanner_assert( $result instanceof WP_Error, "{$case} response must fail closed" );
    }

    $mode = 'infected';
    mvm_private_scanner_assert( 'mvm_mail_attachment_infected' === mvm_private_scanner_error_code( $scanner->scan( $upload ) ), 'infected response should use the infected error boundary' );

    $mode = 'clean';
    $wrong_size = $upload;
    $wrong_size['sizeBytes'] = strlen( $fixture_bytes ) + 1;
    mvm_private_scanner_assert( 'mvm_mail_private_scanner_size' === mvm_private_scanner_error_code( $scanner->scan( $wrong_size ) ), 'declared and actual file size mismatch must fail closed' );

    $never_call = static function (): WP_Error {
        return new WP_Error( 'unexpected_http', 'HTTP client must not be called for invalid configuration' );
    };
    $missing_key = new Private_HTTP_Attachment_Scanner(
        array( 'url' => 'https://scanner.invalid/v1/scan', 'hmacKey' => '', 'keyId' => '' ),
        $never_call
    );
    mvm_private_scanner_assert( 'mvm_mail_private_scanner_unconfigured' === mvm_private_scanner_error_code( $missing_key->scan( $upload ) ), 'missing HMAC key must fail closed' );

    $short_key = new Private_HTTP_Attachment_Scanner(
        array( 'url' => 'https://scanner.invalid/v1/scan', 'hmacKey' => 'too-short', 'keyId' => '' ),
        $never_call
    );
    mvm_private_scanner_assert( 'mvm_mail_private_scanner_unconfigured' === mvm_private_scanner_error_code( $short_key->scan( $upload ) ), 'short HMAC key must fail closed' );

    $http_url = new Private_HTTP_Attachment_Scanner(
        array( 'url' => 'http://scanner.invalid/v1/scan', 'hmacKey' => $key, 'keyId' => '' ),
        $never_call
    );
    mvm_private_scanner_assert( 'mvm_mail_private_scanner_unconfigured' === mvm_private_scanner_error_code( $http_url->scan( $upload ) ), 'plain HTTP scanner endpoint must fail closed' );

    $query_url = new Private_HTTP_Attachment_Scanner(
        array( 'url' => 'https://scanner.invalid/v1/scan?token=not-allowed', 'hmacKey' => $key, 'keyId' => '' ),
        $never_call
    );
    mvm_private_scanner_assert( 'mvm_mail_private_scanner_unconfigured' === mvm_private_scanner_error_code( $query_url->scan( $upload ) ), 'scanner secrets must not be embedded in URL query strings' );

    $large_path = tempnam( sys_get_temp_dir(), 'mvm-private-scanner-large-' );
    mvm_private_scanner_assert( is_string( $large_path ) && '' !== $large_path, 'large-file fixture should be available' );
    $large_stream = fopen( $large_path, 'wb' );
    mvm_private_scanner_assert( is_resource( $large_stream ), 'large-file fixture should open' );
    mvm_private_scanner_assert( ftruncate( $large_stream, ( 10 * 1024 * 1024 ) + 1 ), 'large-file fixture should be sized without loading into memory' );
    fclose( $large_stream );
    $too_large = array(
        'privateHandle' => $large_path,
        'mimeType'      => 'application/pdf',
        'sizeBytes'     => ( 10 * 1024 * 1024 ) + 1,
    );
    mvm_private_scanner_assert( 'mvm_mail_private_scanner_size_limit' === mvm_private_scanner_error_code( $scanner->scan( $too_large ) ), 'files over the scanner limit must fail before HTTP upload' );
    @unlink( $large_path );

    $scanner_source = file_get_contents( __DIR__ . '/../plugins/mvm-hub/integrations/mail/class-private-http-attachment-scanner.php' );
    mvm_private_scanner_assert( is_string( $scanner_source ) && ! str_contains( $scanner_source, 'error_log(' ), 'scanner implementation must not log request content or secrets' );
    mvm_private_scanner_assert( ! str_contains( $scanner_source, "['filename']" ) && ! str_contains( $scanner_source, "['fileName']" ), 'scanner implementation must not consume a filename field' );
} finally {
    @unlink( $fixture_path );
}

echo "MvM private HTTPS attachment scanner smoke OK\n";
