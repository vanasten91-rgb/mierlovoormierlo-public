<?php

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'sanitize_file_name' ) ) {
    function sanitize_file_name( string $name ): string {
        $name = basename( str_replace( '\\', '/', $name ) );
        return preg_replace( '/[^A-Za-z0-9._-]/', '-', $name ) ?? '';
    }
}
if ( ! function_exists( 'sanitize_mime_type' ) ) {
    function sanitize_mime_type( string $mime ): string {
        return preg_replace( '/[^A-Za-z0-9.+\/-]/', '', $mime ) ?? '';
    }
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( string $hook, mixed $value ): mixed {
        unset( $hook );
        return $value;
    }
}

require_once __DIR__ . '/../plugins/mvm-hub/core/class-communications-policy.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-attachment-policy.php';

use MVM\Hub\Core\Communications_Policy;
use MVM\Hub\Modules\Communications\V5_Mail_Attachment_Policy;

function mvm_attachment_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function mvm_attachment_utf16le( string $value ): string {
    $converted = function_exists( 'iconv' ) ? iconv( 'UTF-8', 'UTF-16LE', $value ) : false;
    if ( is_string( $converted ) ) {
        return $converted;
    }
    return implode( '', array_map( static fn( string $char ): string => $char . "\0", str_split( $value ) ) );
}

/** @param array<string,string|array{0:string,1:int}> $entries */
function mvm_attachment_zip( array $entries ): string {
    $local = '';
    $central = '';
    $offset = 0;
    foreach ( $entries as $name => $specification ) {
        $content = is_array( $specification ) ? $specification[0] : $specification;
        $method = is_array( $specification ) ? $specification[1] : 0;
        $payload = 8 === $method ? gzdeflate( $content ) : $content;
        mvm_attachment_assert( is_string( $payload ), "ZIP fixture {$name} should compress" );
        $crc = crc32( $content );
        $name_length = strlen( $name );
        $content_length = strlen( $content );
        $payload_length = strlen( $payload );
        $local_record = "PK\x03\x04"
            . pack( 'v', 20 )
            . pack( 'v', 0 )
            . pack( 'v', $method )
            . pack( 'v', 0 )
            . pack( 'v', 0 )
            . pack( 'V', $crc )
            . pack( 'V', $payload_length )
            . pack( 'V', $content_length )
            . pack( 'v', $name_length )
            . pack( 'v', 0 )
            . $name
            . $payload;
        $central .= "PK\x01\x02"
            . pack( 'v', 20 )
            . pack( 'v', 20 )
            . pack( 'v', 0 )
            . pack( 'v', $method )
            . pack( 'v', 0 )
            . pack( 'v', 0 )
            . pack( 'V', $crc )
            . pack( 'V', $payload_length )
            . pack( 'V', $content_length )
            . pack( 'v', $name_length )
            . pack( 'v', 0 )
            . pack( 'v', 0 )
            . pack( 'v', 0 )
            . pack( 'v', 0 )
            . pack( 'V', 0 )
            . pack( 'V', $offset )
            . $name;
        $local .= $local_record;
        $offset += strlen( $local_record );
    }
    $count = count( $entries );
    return $local
        . $central
        . "PK\x05\x06"
        . pack( 'v', 0 )
        . pack( 'v', 0 )
        . pack( 'v', $count )
        . pack( 'v', $count )
        . pack( 'V', strlen( $central ) )
        . pack( 'V', strlen( $local ) )
        . pack( 'v', 0 );
}

function mvm_attachment_bmff( string $brand ): string {
    return pack( 'N', 20 ) . 'ftyp' . $brand . pack( 'N', 0 ) . $brand;
}

$base = tempnam( sys_get_temp_dir(), 'mvm-mail-policy-' );
mvm_attachment_assert( is_string( $base ) && '' !== $base, 'temporary fixture path should be available' );
@unlink( $base );
mvm_attachment_assert( @mkdir( $base, 0700 ), 'temporary fixture directory should be created' );
$paths = array();

$write = static function ( string $name, string $content ) use ( $base, &$paths ): string {
    $path = $base . DIRECTORY_SEPARATOR . $name;
    mvm_attachment_assert( strlen( $content ) === file_put_contents( $path, $content ), "fixture {$name} should be written" );
    $paths[] = $path;
    return $path;
};

$fixtures = array(
    'photo.jpg' => array( 'image/jpeg', "\xFF\xD8\xFF\xE0safe-jpeg" ),
    'photo.jpeg' => array( 'image/jpeg', "\xFF\xD8\xFF\xE0safe-jpeg" ),
    'photo.jpe' => array( 'image/jpeg', "\xFF\xD8\xFF\xE0safe-jpeg" ),
    'photo.jfif' => array( 'image/jpeg', "\xFF\xD8\xFF\xE0safe-jpeg" ),
    'photo.png' => array( 'image/png', "\x89PNG\r\n\x1A\nsafe-png" ),
    'photo.gif' => array( 'image/gif', 'GIF89a-safe-gif' ),
    'photo.webp' => array( 'image/webp', 'RIFF' . pack( 'V', 8 ) . 'WEBPsafe' ),
    'photo.avif' => array( 'image/avif', mvm_attachment_bmff( 'avif' ) ),
    'photo.heic' => array( 'image/heic', mvm_attachment_bmff( 'heic' ) ),
    'photo.heif' => array( 'image/heif', mvm_attachment_bmff( 'mif1' ) ),
    'photo.tif' => array( 'image/tiff', "II*\x00safe-tiff" ),
    'photo.tiff' => array( 'image/tiff', "MM\x00*safe-tiff" ),
    'photo.bmp' => array( 'image/bmp', 'BMsafe-bitmap' ),
    'clip.mp4' => array( 'video/mp4', mvm_attachment_bmff( 'isom' ) ),
    'clip.m4v' => array( 'video/x-m4v', mvm_attachment_bmff( 'M4V ' ) ),
    'clip.mov' => array( 'video/quicktime', mvm_attachment_bmff( 'qt  ' ) ),
    'clip.webm' => array( 'video/webm', "\x1A\x45\xDF\xA3safe-webm" ),
    'clip.ogv' => array( 'video/ogg', 'OggS-safe-theora' ),
    'clip.mkv' => array( 'video/x-matroska', "\x1A\x45\xDF\xA3safe-matroska" ),
    'clip.avi' => array( 'video/x-msvideo', 'RIFF' . pack( 'V', 8 ) . 'AVI safe' ),
    'clip.mpeg' => array( 'video/mpeg', "\x00\x00\x01\xBAsafe-mpeg" ),
    'clip.mpg' => array( 'video/mpeg', "\x00\x00\x01\xBAsafe-mpeg" ),
    'clip.m2v' => array( 'video/mpeg', "\x00\x00\x01\xB3safe-mpeg" ),
    'clip.3gp' => array( 'video/3gpp', mvm_attachment_bmff( '3gp6' ) ),
    'clip.3g2' => array( 'video/3gpp2', mvm_attachment_bmff( '3g2a' ) ),
    'report.pdf' => array( 'application/pdf', "%PDF-1.7\n1 0 obj<</Type/Catalog>>endobj\n%%EOF" ),
    'legacy.doc' => array( 'application/msword', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat( "\0", 504 ) . mvm_attachment_utf16le( 'WordDocument 1Table' ) ),
    'document.docx' => array(
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        mvm_attachment_zip( array(
            '[Content_Types].xml' => array( '<Types><Override ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>', 8 ),
            'word/document.xml' => array( '<w:document xmlns:w="urn:test"><w:body/></w:document>', 8 ),
        ) ),
    ),
    'document.odt' => array(
        'application/vnd.oasis.opendocument.text',
        mvm_attachment_zip( array(
            'mimetype' => 'application/vnd.oasis.opendocument.text',
            'content.xml' => array( '<office:document-content xmlns:office="urn:test"/>', 8 ),
            'META-INF/manifest.xml' => array( '<manifest:manifest xmlns:manifest="urn:test"/>', 8 ),
        ) ),
    ),
    'document.rtf' => array( 'application/rtf', '{\\rtf1\\ansi Safe RTF document}' ),
    'notes.txt' => array( 'text/plain', "Gewone tekst\nTweede regel" ),
    'notes.md' => array( 'text/markdown', "# Titel\n\nVeilige **tekst**." ),
    'table.csv' => array( 'text/csv', "name,value\nAlice,1\nBob,-2" ),
);

try {
    $required = array(
        'jpg', 'jpeg', 'jpe', 'jfif', 'png', 'gif', 'webp', 'avif', 'heic', 'heif', 'tif', 'tiff', 'bmp',
        'mp4', 'm4v', 'mov', 'webm', 'ogv', 'mkv', 'avi', 'mpeg', 'mpg', 'm2v', '3gp', '3g2',
        'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'md', 'csv',
    );
    $allowed_types = Communications_Policy::allowed_attachment_types();
    foreach ( $required as $extension ) {
        mvm_attachment_assert( isset( $allowed_types[ $extension ] ) && array() !== $allowed_types[ $extension ], "{$extension} must have an explicit MIME allowlist" );
    }

    foreach ( $fixtures as $name => [ $mime, $content ] ) {
        $path = $write( str_replace( '.', '-', $name ) . '.fixture', $content );
        $size = filesize( $path );
        mvm_attachment_assert( is_int( $size ) && Communications_Policy::attachment_type_allowed( $name, $mime, $size ), "{$name} extension/MIME pair should be allowlisted" );
        mvm_attachment_assert( Communications_Policy::attachment_content_allowed( $name, $mime, $size, $path ), "{$name} signature/content should validate" );
        $detected = Communications_Policy::detect_attachment_mime( $name, $path );
        mvm_attachment_assert( '' !== $detected && Communications_Policy::attachment_mime_allowed( $name, $detected ), "{$name} should have a safe signature-derived MIME fallback" );
    }

    $mismatch = $write( 'mismatch.fixture', "\x89PNG\r\n\x1A\nnot-a-jpeg" );
    mvm_attachment_assert( ! Communications_Policy::attachment_content_allowed( 'photo.jpg', 'image/jpeg', filesize( $mismatch ), $mismatch ), 'extension/MIME with a mismatched signature must fail closed' );

    $renamed_executable = $write( 'renamed-executable.fixture', 'MZ' . str_repeat( "\0", 128 ) );
    mvm_attachment_assert( ! Communications_Policy::attachment_content_allowed( 'payload.jpg', 'image/jpeg', filesize( $renamed_executable ), $renamed_executable ), 'renamed executable must fail closed' );

    $active_pdf = $write( 'active-pdf.fixture', "%PDF-1.7\n1 0 obj<</OpenAction 2 0 R /JavaScript(test)>>endobj\n%%EOF" );
    mvm_attachment_assert( ! Communications_Policy::attachment_content_allowed( 'active.pdf', 'application/pdf', filesize( $active_pdf ), $active_pdf ), 'PDF active content must fail closed' );

    $macro_docx = $write( 'macro-docx.fixture', mvm_attachment_zip( array(
        '[Content_Types].xml' => '<Types><Override ContentType="application/vnd.ms-word.document.macroEnabled.main+xml"/></Types>',
        'word/document.xml' => '<w:document/>',
        'word/vbaProject.bin' => 'macro',
    ) ) );
    mvm_attachment_assert( ! Communications_Policy::attachment_content_allowed( 'macro.docx', 'application/zip', filesize( $macro_docx ), $macro_docx ), 'OOXML macro payloads must fail closed' );

    $traversal_docx = $write( 'traversal-docx.fixture', mvm_attachment_zip( array(
        '[Content_Types].xml' => '<Types><Override ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
        'word/document.xml' => '<w:document/>',
        '../outside.txt' => 'not allowed',
    ) ) );
    mvm_attachment_assert( ! Communications_Policy::attachment_content_allowed( 'traversal.docx', 'application/zip', filesize( $traversal_docx ), $traversal_docx ), 'Office archive path traversal must fail closed' );

    $macro_doc = $write( 'macro-doc.fixture', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat( "\0", 504 ) . mvm_attachment_utf16le( 'WordDocument 1Table VBA' ) );
    mvm_attachment_assert( ! Communications_Policy::attachment_content_allowed( 'macro.doc', 'application/msword', filesize( $macro_doc ), $macro_doc ), 'legacy Word macro markers must fail closed' );

    $formula_csv = $write( 'formula-csv.fixture', "name,value\nAlice,=HYPERLINK(\"https://example.test\")" );
    mvm_attachment_assert( ! Communications_Policy::attachment_content_allowed( 'formula.csv', 'text/csv', filesize( $formula_csv ), $formula_csv ), 'CSV formula injection must fail closed' );

    $script_text = $write( 'script-text.fixture', "<?php system('id');" );
    mvm_attachment_assert( ! Communications_Policy::attachment_content_allowed( 'script.txt', 'text/plain', filesize( $script_text ), $script_text ), 'script content renamed as text must fail closed' );

    mvm_attachment_assert( ! Communications_Policy::attachment_extension_allowed( 'macro.docm' ), 'macro-enabled Word extension must not be allowlisted' );
    mvm_attachment_assert( ! Communications_Policy::attachment_extension_allowed( 'archive.zip' ), 'generic ZIP must not be allowlisted' );
    mvm_attachment_assert( ! Communications_Policy::attachment_extension_allowed( 'script.js' ), 'script extension must not be allowlisted' );
    mvm_attachment_assert( ! Communications_Policy::attachment_type_allowed( 'photo.jpg', 'text/plain', 100 ), 'wrong MIME must fail closed' );

    $eligible_input = array(
        'id' => 'draft-att-1',
        'name' => 'photo.jfif',
        'mime' => 'image/jpeg',
        'size' => 1024,
        'privateStorage' => true,
        'ownerAuthorized' => true,
        'draftLinked' => true,
        'contentValidationStatus' => 'verified',
        'scanStatus' => 'clean',
    );
    $eligible = V5_Mail_Attachment_Policy::evaluate( $eligible_input );
    mvm_attachment_assert( true === $eligible['allowedForSend'], 'verified allowlisted attachment with a clean malware scan should be eligible' );

    $unvalidated = V5_Mail_Attachment_Policy::evaluate( array_merge( $eligible_input, array( 'contentValidationStatus' => 'unknown' ) ) );
    mvm_attachment_assert( false === $unvalidated['allowedForSend'] && in_array( 'content_validation_required', $unvalidated['reasons'], true ), 'missing content validation must fail closed' );

    $unscanned = V5_Mail_Attachment_Policy::evaluate( array_merge( $eligible_input, array( 'scanStatus' => 'pending' ) ) );
    mvm_attachment_assert( false === $unscanned['allowedForSend'] && in_array( 'malware_scan_pending', $unscanned['reasons'], true ), 'malware scan must remain required after content validation' );

    $empty_override = V5_Mail_Attachment_Policy::evaluate( $eligible_input, array( 'allowedMimes' => array() ) );
    mvm_attachment_assert( false === $empty_override['allowedForSend'], 'an explicit empty policy override must not mean allow all' );
} finally {
    foreach ( array_reverse( $paths ) as $path ) {
        @unlink( $path );
    }
    @rmdir( $base );
}

echo "PASS: Mail attachment allowlists validate MIME plus content, reject active/macro formats, and still require a clean malware scan\n";
