<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

final class WP_Error {
    public function __construct(
        private string $code,
        private string $message = '',
        private mixed $data = null
    ) {}
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
    public function get_error_data(): mixed { return $this->data; }
}

function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function sanitize_mime_type( string $value ): string {
    $value = strtolower( trim( $value ) );
    return preg_match( '#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $value ) ? $value : '';
}

require_once __DIR__ . '/../plugins/mvm-hub/integrations/mail/interface-attachment-scanner.php';
require_once __DIR__ . '/../plugins/mvm-hub/integrations/mail/class-cloudmersive-attachment-scanner.php';

use MVM\Hub\Integrations\Mail\Cloudmersive_Attachment_Scanner;

function must( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$path = tempnam( sys_get_temp_dir(), 'mvm-cloudmersive-' );
must( is_string( $path ) && '' !== $path, 'temp file must be created' );
file_put_contents( $path, 'safe attachment payload' );
$size = filesize( $path );
must( is_int( $size ) && $size > 0, 'temp file size must be known' );

$config = array(
    'url'    => 'https://eu-scanner.example.test/virus/scan/file',
    'apiKey' => str_repeat( 'a', 32 ),
);

$captured = array();
$clean_transport = static function ( string $url, string $api_key, string $file_path, string $mime, array $options ) use ( &$captured ): array {
    $captured = compact( 'url', 'api_key', 'file_path', 'mime', 'options' );
    return array(
        'statusCode' => 200,
        'body'       => json_encode( array( 'CleanResult' => true, 'FoundViruses' => array() ), JSON_THROW_ON_ERROR ),
    );
};

$scanner = new Cloudmersive_Attachment_Scanner( $config, $clean_transport );
$result  = $scanner->scan( array(
    'privateHandle' => $path,
    'sizeBytes'     => $size,
    'mimeType'      => 'text/plain',
    'filename'      => 'private-user-name.txt',
) );

must( is_array( $result ), 'clean response must return a result array' );
must( 'clean' === ( $result['status'] ?? '' ), 'clean response must release the attachment' );
must( 'cloudmersive' === ( $result['engine'] ?? '' ), 'engine must be identified as Cloudmersive' );
must( hash_file( 'sha256', $path ) === ( $result['sha256'] ?? '' ), 'clean result must bind to the local file digest' );
must( 'https://eu-scanner.example.test/virus/scan/file' === ( $captured['url'] ?? '' ), 'configured endpoint must be used exactly' );
must( str_repeat( 'a', 32 ) === ( $captured['api_key'] ?? '' ), 'API key must be sent only to the transport' );
must( $path === ( $captured['file_path'] ?? '' ), 'private file path must be the transport source' );
must( 'text/plain' === ( $captured['mime'] ?? '' ), 'server-derived MIME must be sent' );
must( 'attachment.bin' === ( $captured['options']['safeFilename'] ?? '' ), 'user filename must be replaced by a synthetic name' );
must( 16384 === ( $captured['options']['maxResponse'] ?? 0 ), 'response body must remain bounded' );
must( 12 === ( $captured['options']['timeout'] ?? 0 ), 'request timeout must remain bounded' );
must( 3 === ( $captured['options']['connectTimeout'] ?? 0 ), 'connect timeout must remain bounded' );

$infected = new Cloudmersive_Attachment_Scanner( $config, static fn(): array => array(
    'statusCode' => 200,
    'body'       => json_encode( array(
        'CleanResult' => false,
        'FoundViruses' => array( array( 'FileName' => 'attachment.bin', 'VirusName' => 'EICAR-Test-File' ) ),
    ), JSON_THROW_ON_ERROR ),
) );
$infected_result = $infected->scan( array( 'privateHandle' => $path, 'sizeBytes' => $size, 'mimeType' => 'text/plain' ) );
must( is_wp_error( $infected_result ) && 'mvm_mail_attachment_infected' === $infected_result->get_error_code(), 'infected result must fail closed' );

$inconsistent = new Cloudmersive_Attachment_Scanner( $config, static fn(): array => array(
    'statusCode' => 200,
    'body'       => json_encode( array(
        'CleanResult' => true,
        'FoundViruses' => array( array( 'FileName' => 'attachment.bin', 'VirusName' => 'Unexpected' ) ),
    ), JSON_THROW_ON_ERROR ),
) );
$inconsistent_result = $inconsistent->scan( array( 'privateHandle' => $path, 'sizeBytes' => $size, 'mimeType' => 'text/plain' ) );
must( is_wp_error( $inconsistent_result ) && 'mvm_mail_attachment_infected' === $inconsistent_result->get_error_code(), 'clean=true with viruses must be blocked as inconsistent' );

$invalid_json = new Cloudmersive_Attachment_Scanner( $config, static fn(): array => array( 'statusCode' => 200, 'body' => '{invalid' ) );
$invalid_json_result = $invalid_json->scan( array( 'privateHandle' => $path, 'sizeBytes' => $size, 'mimeType' => 'text/plain' ) );
must( is_wp_error( $invalid_json_result ) && 'mvm_mail_cloudmersive_response' === $invalid_json_result->get_error_code(), 'invalid JSON must fail closed' );

$missing_clean = new Cloudmersive_Attachment_Scanner( $config, static fn(): array => array( 'statusCode' => 200, 'body' => '{"FoundViruses":[]}' ) );
$missing_clean_result = $missing_clean->scan( array( 'privateHandle' => $path, 'sizeBytes' => $size, 'mimeType' => 'text/plain' ) );
must( is_wp_error( $missing_clean_result ) && 'mvm_mail_cloudmersive_inconclusive' === $missing_clean_result->get_error_code(), 'missing clean verdict must fail closed' );

$http_error = new Cloudmersive_Attachment_Scanner( $config, static fn(): array => array( 'statusCode' => 503, 'body' => '{}' ) );
$http_error_result = $http_error->scan( array( 'privateHandle' => $path, 'sizeBytes' => $size, 'mimeType' => 'text/plain' ) );
must( is_wp_error( $http_error_result ) && 'mvm_mail_cloudmersive_http' === $http_error_result->get_error_code(), 'non-200 response must fail closed' );

$network_error = new Cloudmersive_Attachment_Scanner( $config, static fn(): WP_Error => new WP_Error( 'test_network' ) );
$network_error_result = $network_error->scan( array( 'privateHandle' => $path, 'sizeBytes' => $size, 'mimeType' => 'text/plain' ) );
must( is_wp_error( $network_error_result ), 'transport errors must fail closed' );

$oversized_response = new Cloudmersive_Attachment_Scanner( $config, static fn(): array => array(
    'statusCode' => 200,
    'body'       => str_repeat( 'x', 16385 ),
) );
$oversized_response_result = $oversized_response->scan( array( 'privateHandle' => $path, 'sizeBytes' => $size, 'mimeType' => 'text/plain' ) );
must( is_wp_error( $oversized_response_result ), 'oversized response must fail closed' );

$bad_configs = array(
    array( 'url' => 'http://scanner.example.test/virus/scan/file', 'apiKey' => str_repeat( 'a', 32 ) ),
    array( 'url' => 'https://scanner.example.test/virus/scan/file?secret=1', 'apiKey' => str_repeat( 'a', 32 ) ),
    array( 'url' => 'https://scanner.example.test/not-a-scan-endpoint', 'apiKey' => str_repeat( 'a', 32 ) ),
    array( 'url' => 'https://scanner.example.test/virus/scan/file', 'apiKey' => 'short' ),
);
foreach ( $bad_configs as $bad_config ) {
    $bad = new Cloudmersive_Attachment_Scanner( $bad_config, $clean_transport );
    $bad_result = $bad->scan( array( 'privateHandle' => $path, 'sizeBytes' => $size, 'mimeType' => 'text/plain' ) );
    must( is_wp_error( $bad_result ) && 'mvm_mail_cloudmersive_unconfigured' === $bad_result->get_error_code(), 'unsafe configuration must be rejected' );
}

$size_mismatch = $scanner->scan( array( 'privateHandle' => $path, 'sizeBytes' => $size + 1, 'mimeType' => 'text/plain' ) );
must( is_wp_error( $size_mismatch ) && 'mvm_mail_cloudmersive_size' === $size_mismatch->get_error_code(), 'declared size mismatch must fail closed' );

$bad_mime = $scanner->scan( array( 'privateHandle' => $path, 'sizeBytes' => $size, 'mimeType' => 'not a mime' ) );
must( is_wp_error( $bad_mime ) && 'mvm_mail_cloudmersive_mime' === $bad_mime->get_error_code(), 'invalid MIME must fail closed' );

@unlink( $path );
echo "MvM Cloudmersive attachment scanner smoke OK\n";
