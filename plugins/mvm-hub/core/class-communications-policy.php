<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared security policy for internal messaging and e-mail.
 *
 * External delivery remains fail-closed unless the independent runtime delivery
 * gate is explicitly enabled for the current environment.
 */
final class Communications_Policy {
    public const DEFAULT_MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    private const MAX_ZIP_ENTRIES = 2048;
    private const MAX_ZIP_UNCOMPRESSED_BYTES = 100 * 1024 * 1024;
    private const MAX_ZIP_COMPRESSION_RATIO = 200;
    private const MAX_ZIP_INSPECTION_BYTES = 25 * 1024 * 1024;

    private const BLOCKED_EXTENSIONS = array(
        'apk', 'app', 'asp', 'aspx', 'bat', 'bin', 'cgi', 'chm', 'class', 'cmd',
        'com', 'cpl', 'deb', 'desktop', 'dll', 'dmg', 'docm', 'dotm', 'exe', 'hta',
        'htm', 'html', 'iso', 'jar', 'js', 'jse', 'ksh', 'lnk', 'msi', 'msp',
        'msix', 'phtml', 'phar', 'php', 'php3', 'php4', 'php5', 'php7', 'php8',
        'pif', 'pl', 'potm', 'ppam', 'pptm', 'ps1', 'py', 'rb', 'reg', 'rpm',
        'scf', 'scr', 'sct', 'sh', 'sldm', 'so', 'svg', 'swf', 'url', 'vb', 'vba',
        'vbe', 'vbs', 'wsf', 'wsh', 'xlam', 'xll', 'xlsb', 'xlsm', 'xltm', 'zip',
    );

    /** @var array<string,array<int,string>> */
    private const ALLOWED_ATTACHMENT_TYPES = array(
        'jpg'  => array( 'image/jpeg', 'image/pjpeg' ),
        'jpeg' => array( 'image/jpeg', 'image/pjpeg' ),
        'jpe'  => array( 'image/jpeg', 'image/pjpeg' ),
        'jfif' => array( 'image/jpeg', 'image/pjpeg' ),
        'png'  => array( 'image/png', 'image/x-png' ),
        'gif'  => array( 'image/gif' ),
        'webp' => array( 'image/webp' ),
        'avif' => array( 'image/avif' ),
        'heic' => array( 'image/heic', 'image/heic-sequence', 'image/heif' ),
        'heif' => array( 'image/heif', 'image/heif-sequence', 'image/heic' ),
        'tif'  => array( 'image/tiff', 'image/x-tiff' ),
        'tiff' => array( 'image/tiff', 'image/x-tiff' ),
        'bmp'  => array( 'image/bmp', 'image/x-bmp', 'image/x-ms-bmp' ),
        'mp4'  => array( 'video/mp4', 'application/mp4' ),
        'm4v'  => array( 'video/x-m4v', 'video/mp4' ),
        'mov'  => array( 'video/quicktime' ),
        'webm' => array( 'video/webm' ),
        'ogv'  => array( 'video/ogg', 'application/ogg' ),
        'mkv'  => array( 'video/x-matroska', 'video/matroska' ),
        'avi'  => array( 'video/x-msvideo', 'video/avi', 'video/msvideo' ),
        'mpeg' => array( 'video/mpeg' ),
        'mpg'  => array( 'video/mpeg' ),
        'm2v'  => array( 'video/mpeg' ),
        '3gp'  => array( 'video/3gpp' ),
        '3g2'  => array( 'video/3gpp2' ),
        'pdf'  => array( 'application/pdf' ),
        'doc'  => array( 'application/msword', 'application/x-ole-storage', 'application/vnd.ms-office' ),
        'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip' ),
        'odt'  => array( 'application/vnd.oasis.opendocument.text', 'application/zip' ),
        'rtf'  => array( 'application/rtf', 'text/rtf' ),
        'txt'  => array( 'text/plain' ),
        'md'   => array( 'text/markdown', 'text/x-markdown', 'text/plain' ),
        'csv'  => array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' ),
        'xls'  => array( 'application/vnd.ms-excel', 'application/x-ole-storage', 'application/vnd.ms-office' ),
        'xlsx' => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip' ),
        'ods'  => array( 'application/vnd.oasis.opendocument.spreadsheet', 'application/zip' ),
        'mp3'  => array( 'audio/mpeg', 'audio/mp3' ),
        'm4a'  => array( 'audio/mp4', 'audio/x-m4a' ),
    );

    public static function remote_images_allowed_by_default(): bool {
        return false;
    }

    public static function notification_subjects_allowed_by_default(): bool {
        return false;
    }

    public static function external_mail_delivery_enabled(): bool {
        return Runtime_Gates::mail_writes_enabled();
    }

    public static function max_attachment_bytes(): int {
        $limit = (int) apply_filters(
            'mvm_hub_communications_max_attachment_bytes',
            self::DEFAULT_MAX_ATTACHMENT_BYTES
        );

        return max( 1024 * 1024, min( 25 * 1024 * 1024, $limit ) );
    }

    public static function attachment_extension_allowed( string $filename ): bool {
        $extension = strtolower( (string) pathinfo( sanitize_file_name( $filename ), PATHINFO_EXTENSION ) );
        if ( '' === $extension || in_array( $extension, self::BLOCKED_EXTENSIONS, true ) ) {
            return false;
        }

        return isset( self::ALLOWED_ATTACHMENT_TYPES[ $extension ] );
    }

    public static function attachment_type_allowed( string $filename, string $mime_type, int $size_bytes ): bool {
        $filename  = sanitize_file_name( $filename );
        $extension = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
        $mime_type = strtolower( sanitize_mime_type( $mime_type ) );

        if ( ! self::attachment_extension_allowed( $filename ) ) {
            return false;
        }

        if ( $size_bytes <= 0 || $size_bytes > self::max_attachment_bytes() ) {
            return false;
        }

        return self::attachment_mime_allowed( $filename, $mime_type );
    }

    public static function attachment_mime_allowed( string $filename, string $mime_type ): bool {
        $filename  = sanitize_file_name( $filename );
        $extension = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
        $mime_type = strtolower( sanitize_mime_type( $mime_type ) );

        if ( ! self::attachment_extension_allowed( $filename ) ) {
            return false;
        }

        $allowed_mimes = self::ALLOWED_ATTACHMENT_TYPES[ $extension ] ?? array();
        return in_array( $mime_type, $allowed_mimes, true );
    }

    /**
     * Verifies the bytes behind an already server-derived MIME value.
     *
     * Container formats are inspected fail-closed for encryption, traversal,
     * executable payloads and macro/active-content markers. A clean malware
     * result remains an independent mandatory gate after this validation.
     */
    public static function attachment_content_allowed( string $filename, string $mime_type, int $size_bytes, string $path ): bool {
        if ( ! self::attachment_type_allowed( $filename, $mime_type, $size_bytes ) ) {
            return false;
        }
        if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
            return false;
        }

        $actual_size = filesize( $path );
        if ( false === $actual_size || (int) $actual_size !== $size_bytes ) {
            return false;
        }

        $extension = strtolower( (string) pathinfo( sanitize_file_name( $filename ), PATHINFO_EXTENSION ) );
        $prefix = self::read_segment( $path, 0, min( 65536, $size_bytes ) );
        if ( false === $prefix || self::has_executable_signature( $prefix ) ) {
            return false;
        }

        return match ( $extension ) {
            'jpg', 'jpeg', 'jpe', 'jfif' => str_starts_with( $prefix, "\xFF\xD8\xFF" ),
            'png'  => str_starts_with( $prefix, "\x89PNG\r\n\x1A\n" ),
            'gif'  => str_starts_with( $prefix, 'GIF87a' ) || str_starts_with( $prefix, 'GIF89a' ),
            'webp' => strlen( $prefix ) >= 12 && 'RIFF' === substr( $prefix, 0, 4 ) && 'WEBP' === substr( $prefix, 8, 4 ),
            'avif' => self::bmff_has_brand( $prefix, array( 'avif', 'avis' ) ),
            'heic' => self::bmff_has_brand( $prefix, array( 'heic', 'heix', 'hevc', 'hevx' ) ),
            'heif' => self::bmff_has_brand( $prefix, array( 'mif1', 'msf1', 'heic', 'heix', 'hevc', 'hevx' ) ),
            'tif', 'tiff' => str_starts_with( $prefix, "II*\x00" ) || str_starts_with( $prefix, "MM\x00*" ),
            'bmp'  => str_starts_with( $prefix, 'BM' ),
            'mp4'  => self::bmff_has_brand( $prefix, array( 'isom', 'iso2', 'iso3', 'iso4', 'iso5', 'iso6', 'mp41', 'mp42', 'avc1', 'dash' ) ),
            'm4v'  => self::bmff_has_brand( $prefix, array( 'M4V ', 'M4VH', 'M4VP', 'isom', 'mp41', 'mp42' ) ),
            'mov'  => self::bmff_has_brand( $prefix, array( 'qt  ' ) ),
            '3gp'  => self::bmff_has_brand_prefix( $prefix, '3gp' ) || self::bmff_has_brand_prefix( $prefix, '3ge' ),
            '3g2'  => self::bmff_has_brand_prefix( $prefix, '3g2' ),
            'webm' => str_starts_with( $prefix, "\x1A\x45\xDF\xA3" ) && false !== stripos( $prefix, 'webm' ),
            'mkv'  => str_starts_with( $prefix, "\x1A\x45\xDF\xA3" ) && false !== stripos( $prefix, 'matroska' ),
            'ogv'  => str_starts_with( $prefix, 'OggS' ) && false !== stripos( $prefix, 'theora' ),
            'avi'  => strlen( $prefix ) >= 12 && 'RIFF' === substr( $prefix, 0, 4 ) && 'AVI ' === substr( $prefix, 8, 4 ),
            'mpeg', 'mpg', 'm2v' => str_starts_with( $prefix, "\x00\x00\x01\xBA" ) || str_starts_with( $prefix, "\x00\x00\x01\xB3" ),
            'pdf'  => self::pdf_is_passive( $path ),
            'doc'  => self::cfb_is_safe_office_document( $path, 'word' ),
            'xls'  => self::cfb_is_safe_office_document( $path, 'excel' ),
            'docx' => self::zip_is_safe_office_document( $path, 'docx' ),
            'xlsx' => self::zip_is_safe_office_document( $path, 'xlsx' ),
            'odt'  => self::zip_is_safe_office_document( $path, 'odt' ),
            'ods'  => self::zip_is_safe_office_document( $path, 'ods' ),
            'rtf'  => self::rtf_is_passive( $path ),
            'txt', 'md', 'csv' => self::text_is_passive( $path, $extension ),
            'mp3'  => str_starts_with( $prefix, 'ID3' ) || ( strlen( $prefix ) >= 2 && "\xFF" === $prefix[0] && 0xE0 === ( ord( $prefix[1] ) & 0xE0 ) ),
            'm4a'  => self::bmff_has_brand( $prefix, array( 'M4A ', 'M4B ', 'isom', 'mp41', 'mp42' ) ),
            default => false,
        };
    }

    /**
     * Signature-based fallback for hosts where fileinfo returns no useful MIME.
     */
    public static function detect_attachment_mime( string $filename, string $path ): string {
        $filename  = sanitize_file_name( $filename );
        $extension = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
        $size = is_file( $path ) ? filesize( $path ) : false;
        $mimes = self::ALLOWED_ATTACHMENT_TYPES[ $extension ] ?? array();
        if ( false === $size || (int) $size <= 0 ) {
            return '';
        }

        foreach ( $mimes as $mime ) {
            if ( self::attachment_content_allowed( $filename, $mime, (int) $size, $path ) ) {
                return $mime;
            }
        }
        return '';
    }

    /** @return array<int,string> */
    public static function allowed_attachment_extensions(): array {
        return array_keys( self::ALLOWED_ATTACHMENT_TYPES );
    }

    /** @return array<string,array<int,string>> */
    public static function allowed_attachment_types(): array {
        return self::ALLOWED_ATTACHMENT_TYPES;
    }

    /** @return string|false */
    private static function read_segment( string $path, int $offset, int $length ): string|false {
        if ( $offset < 0 || $length <= 0 ) {
            return false;
        }
        $handle = @fopen( $path, 'rb' );
        if ( false === $handle ) {
            return false;
        }
        try {
            if ( 0 !== $offset && 0 !== fseek( $handle, $offset ) ) {
                return false;
            }
            $data = fread( $handle, $length );
            return is_string( $data ) ? $data : false;
        } finally {
            fclose( $handle );
        }
    }

    private static function has_executable_signature( string $prefix ): bool {
        $without_bom = str_starts_with( $prefix, "\xEF\xBB\xBF" ) ? substr( $prefix, 3 ) : $prefix;
        foreach ( array(
            "MZ",
            "\x7FELF",
            "\xFE\xED\xFA\xCE",
            "\xFE\xED\xFA\xCF",
            "\xCE\xFA\xED\xFE",
            "\xCF\xFA\xED\xFE",
            "\xCA\xFE\xBA\xBE",
            '%!PS-Adobe',
        ) as $signature ) {
            if ( str_starts_with( $prefix, $signature ) ) {
                return true;
            }
        }
        return str_starts_with( $without_bom, '#!' );
    }

    /** @param array<int,string> $allowed */
    private static function bmff_has_brand( string $prefix, array $allowed ): bool {
        return array() !== array_intersect( self::bmff_brands( $prefix ), $allowed );
    }

    private static function bmff_has_brand_prefix( string $prefix, string $allowed_prefix ): bool {
        foreach ( self::bmff_brands( $prefix ) as $brand ) {
            if ( str_starts_with( strtolower( $brand ), strtolower( $allowed_prefix ) ) ) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int,string> */
    private static function bmff_brands( string $prefix ): array {
        if ( strlen( $prefix ) < 16 || 'ftyp' !== substr( $prefix, 4, 4 ) ) {
            return array();
        }
        $box_size = self::uint32le_or_be( substr( $prefix, 0, 4 ), false );
        if ( $box_size < 16 || $box_size > min( 4096, strlen( $prefix ) ) || 0 !== ( ( $box_size - 16 ) % 4 ) ) {
            return array();
        }

        $brands = array( substr( $prefix, 8, 4 ) );
        for ( $offset = 16; $offset + 4 <= $box_size; $offset += 4 ) {
            $brands[] = substr( $prefix, $offset, 4 );
        }
        return array_values( array_unique( $brands ) );
    }

    private static function pdf_is_passive( string $path ): bool {
        $data = @file_get_contents( $path );
        if ( ! is_string( $data ) || false === strpos( substr( $data, 0, 1024 ), '%PDF-' ) || false === strpos( substr( $data, -2048 ), '%%EOF' ) ) {
            return false;
        }

        $normalized = preg_replace_callback(
            '/#([0-9a-f]{2})/i',
            static fn( array $match ): string => chr( hexdec( $match[1] ) ),
            $data
        );
        $normalized = strtolower( is_string( $normalized ) ? $normalized : $data );
        return 1 !== preg_match(
            '#/(?:javascript|js|launch|openaction|aa|richmedia|embeddedfile|xfa|acroform|submitform|importdata)(?=[\s<>\[\]()/]|$)#i',
            $normalized
        );
    }

    private static function rtf_is_passive( string $path ): bool {
        $data = @file_get_contents( $path );
        if ( ! is_string( $data ) || ! str_starts_with( ltrim( $data, "\xEF\xBB\xBF\r\n\t " ), '{\\rtf' ) ) {
            return false;
        }
        return 1 !== preg_match( '/\\\\(?:object|objdata|objclass|datastore|htmltag|filetbl)\b|\\\\fldinst\s+(?:dde|ddeauto)/i', $data );
    }

    private static function text_is_passive( string $path, string $extension ): bool {
        $data = @file_get_contents( $path );
        if ( ! is_string( $data ) || '' === $data || str_contains( $data, "\0" ) ) {
            return false;
        }
        if ( 1 === preg_match( '/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/', $data ) ) {
            return false;
        }
        if ( 1 === preg_match( '/<\?(?:php|=)|<script\b|javascript\s*:|data\s*:\s*text\/html/i', $data ) ) {
            return false;
        }
        if ( 'md' === $extension && 1 === preg_match( '/<\/?(?:html|iframe|object|embed|svg)\b|\bon[a-z]+\s*=/i', $data ) ) {
            return false;
        }
        if ( 'csv' === $extension && 1 === preg_match( '/(?:^|[,;\t])\s*"?[=+@]|(?:^|[,;\t])\s*"?-\s*[A-Za-z@=+]/m', $data ) ) {
            return false;
        }
        return true;
    }

    private static function cfb_is_safe_office_document( string $path, string $kind ): bool {
        $data = @file_get_contents( $path );
        if ( ! is_string( $data ) || strlen( $data ) < 512 || ! str_starts_with( $data, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" ) ) {
            return false;
        }

        $searchable = strtolower( str_replace( "\0", '', $data ) );
        foreach ( array( 'vba', 'macros', '_vba_project', 'projectwm', 'encryptedpackage', 'encryptioninfo', 'ddeauto' ) as $blocked ) {
            if ( false !== strpos( $searchable, $blocked ) ) {
                return false;
            }
        }

        if ( 'word' === $kind ) {
            return false !== strpos( $searchable, 'worddocument' )
                && ( false !== strpos( $searchable, '0table' ) || false !== strpos( $searchable, '1table' ) );
        }
        return false !== strpos( $searchable, 'workbook' ) || false !== strpos( $searchable, 'book' );
    }

    private static function zip_is_safe_office_document( string $path, string $kind ): bool {
        $archive = self::zip_manifest( $path );
        if ( null === $archive ) {
            return false;
        }
        $entries = $archive['entries'];

        foreach ( $entries as $name => $entry ) {
            if (
                1 === preg_match( '#(?:^|/)(?:scripts?|basic|activex|embeddings?|customui)(?:/|$)#i', $name )
                || 1 === preg_match( '/\.(?:exe|dll|com|cpl|js|jse|vbs|vbe|ps1|bat|cmd|msi|scr|hta|html?|php[0-9]?|phtml|phar|jar|class|sh|pl|py|rb|docm|dotm|xlsm|xltm|pptm|potm|ppam|sldm|xlam|xll|bin)$/i', $name )
            ) {
                return false;
            }
        }

        if ( 'docx' === $kind || 'xlsx' === $kind ) {
            $main = 'docx' === $kind ? 'word/document.xml' : 'xl/workbook.xml';
            if ( ! isset( $entries['[content_types].xml'], $entries[ $main ] ) ) {
                return false;
            }
            $types = self::zip_entry_contents( $path, $entries['[content_types].xml'] );
            if ( ! is_string( $types ) ) {
                return false;
            }
            $types = strtolower( $types );
            $required = 'docx' === $kind
                ? 'wordprocessingml.document.main+xml'
                : 'spreadsheetml.sheet.main+xml';
            if ( false === strpos( $types, $required ) ) {
                return false;
            }
            foreach ( array( 'macroenabled', 'vbaproject', 'activex', 'oleobject' ) as $blocked ) {
                if ( false !== strpos( $types, $blocked ) ) {
                    return false;
                }
            }
            foreach ( $entries as $name => $entry ) {
                if ( ! str_ends_with( $name, '.rels' ) ) {
                    continue;
                }
                $relations = self::zip_entry_contents( $path, $entry );
                if ( ! is_string( $relations ) || 1 === preg_match( '/vbaproject|oleobject|attachedtemplate|activex/i', $relations ) ) {
                    return false;
                }
            }
            return true;
        }

        $expected_mime = 'odt' === $kind
            ? 'application/vnd.oasis.opendocument.text'
            : 'application/vnd.oasis.opendocument.spreadsheet';
        if ( ! isset( $entries['mimetype'], $entries['content.xml'] ) || 0 !== $entries['mimetype']['method'] ) {
            return false;
        }
        $mimetype = self::zip_entry_contents( $path, $entries['mimetype'] );
        if ( $expected_mime !== $mimetype ) {
            return false;
        }
        foreach ( array( 'meta-inf/manifest.xml', 'content.xml' ) as $inspect ) {
            if ( ! isset( $entries[ $inspect ] ) ) {
                continue;
            }
            $content = self::zip_entry_contents( $path, $entries[ $inspect ] );
            if ( ! is_string( $content ) || 1 === preg_match( '/encryption-data|office:scripts|script:|basic-library|executable/i', $content ) ) {
                return false;
            }
        }
        return true;
    }

    /** @return array{entries:array<string,array<string,int|string>>}|null */
    private static function zip_manifest( string $path ): ?array {
        $size = filesize( $path );
        if ( false === $size || $size < 22 ) {
            return null;
        }
        $tail_length = min( 65557, (int) $size );
        $tail = self::read_segment( $path, (int) $size - $tail_length, $tail_length );
        if ( false === $tail ) {
            return null;
        }
        $eocd_position = strrpos( $tail, "PK\x05\x06" );
        if ( false === $eocd_position || $eocd_position + 22 > strlen( $tail ) ) {
            return null;
        }
        $eocd = substr( $tail, $eocd_position, 22 );
        $disk = self::uint16le( substr( $eocd, 4, 2 ) );
        $central_disk = self::uint16le( substr( $eocd, 6, 2 ) );
        $entries_on_disk = self::uint16le( substr( $eocd, 8, 2 ) );
        $entry_count = self::uint16le( substr( $eocd, 10, 2 ) );
        $central_size = self::uint32le_or_be( substr( $eocd, 12, 4 ), true );
        $central_offset = self::uint32le_or_be( substr( $eocd, 16, 4 ), true );
        $comment_length = self::uint16le( substr( $eocd, 20, 2 ) );
        if (
            0 !== $disk || 0 !== $central_disk || $entries_on_disk !== $entry_count
            || $entry_count <= 0 || $entry_count > self::MAX_ZIP_ENTRIES
            || $eocd_position + 22 + $comment_length !== strlen( $tail )
            || $central_offset + $central_size !== (int) $size - $tail_length + $eocd_position
        ) {
            return null;
        }

        $central = self::read_segment( $path, $central_offset, $central_size );
        if ( false === $central || strlen( $central ) !== $central_size ) {
            return null;
        }

        $cursor = 0;
        $total_uncompressed = 0;
        $entries = array();
        for ( $index = 0; $index < $entry_count; $index++ ) {
            if ( $cursor + 46 > strlen( $central ) || 'PK' . "\x01\x02" !== substr( $central, $cursor, 4 ) ) {
                return null;
            }
            $flags = self::uint16le( substr( $central, $cursor + 8, 2 ) );
            $method = self::uint16le( substr( $central, $cursor + 10, 2 ) );
            $crc = self::uint32le_or_be( substr( $central, $cursor + 16, 4 ), true );
            $compressed = self::uint32le_or_be( substr( $central, $cursor + 20, 4 ), true );
            $uncompressed = self::uint32le_or_be( substr( $central, $cursor + 24, 4 ), true );
            $name_length = self::uint16le( substr( $central, $cursor + 28, 2 ) );
            $extra_length = self::uint16le( substr( $central, $cursor + 30, 2 ) );
            $entry_comment_length = self::uint16le( substr( $central, $cursor + 32, 2 ) );
            $disk_start = self::uint16le( substr( $central, $cursor + 34, 2 ) );
            $external = self::uint32le_or_be( substr( $central, $cursor + 38, 4 ), true );
            $local_offset = self::uint32le_or_be( substr( $central, $cursor + 42, 4 ), true );
            $record_length = 46 + $name_length + $extra_length + $entry_comment_length;
            if ( $cursor + $record_length > strlen( $central ) ) {
                return null;
            }

            $raw_name = substr( $central, $cursor + 46, $name_length );
            $name = self::safe_zip_name( $raw_name );
            $mode = ( $external >> 16 ) & 0xFFFF;
            $is_symlink = 0120000 === ( $mode & 0170000 );
            if (
                '' === $name || isset( $entries[ $name ] ) || 0 !== $disk_start || $is_symlink
                || 0 !== ( $flags & 0x0001 ) || 0 !== ( $flags & 0x0040 )
                || ! in_array( $method, array( 0, 8 ), true )
                || 0xFFFFFFFF === $compressed || 0xFFFFFFFF === $uncompressed
                || $local_offset + 30 > (int) $size
                || ( $compressed <= 0 && $uncompressed > 0 )
                || ( $compressed > 0 && $uncompressed > $compressed * self::MAX_ZIP_COMPRESSION_RATIO )
            ) {
                return null;
            }
            $total_uncompressed += $uncompressed;
            if ( $total_uncompressed > self::MAX_ZIP_UNCOMPRESSED_BYTES ) {
                return null;
            }
            $entries[ $name ] = array(
                'name' => $raw_name,
                'flags' => $flags,
                'method' => $method,
                'crc' => $crc,
                'compressed' => $compressed,
                'uncompressed' => $uncompressed,
                'localOffset' => $local_offset,
            );
            $cursor += $record_length;
        }
        return $cursor === strlen( $central ) ? array( 'entries' => $entries ) : null;
    }

    private static function safe_zip_name( string $raw_name ): string {
        if (
            '' === $raw_name
            || str_contains( $raw_name, "\0" )
            || str_contains( $raw_name, '\\' )
            || 1 === preg_match( '/[\x00-\x1F\x7F]/', $raw_name )
        ) {
            return '';
        }
        $name = strtolower( $raw_name );
        if ( str_starts_with( $name, '/' ) || 1 === preg_match( '/^[a-z]:/i', $name ) ) {
            return '';
        }
        foreach ( explode( '/', rtrim( $name, '/' ) ) as $part ) {
            if ( '' === $part || '.' === $part || '..' === $part ) {
                return '';
            }
        }
        return $name;
    }

    /** @param array<string,int|string> $entry */
    private static function zip_entry_contents( string $path, array $entry ): string|false {
        $uncompressed = (int) ( $entry['uncompressed'] ?? -1 );
        $compressed = (int) ( $entry['compressed'] ?? -1 );
        if ( $uncompressed < 0 || $compressed < 0 || $uncompressed > self::MAX_ZIP_INSPECTION_BYTES ) {
            return false;
        }
        $offset = (int) ( $entry['localOffset'] ?? -1 );
        $header = self::read_segment( $path, $offset, 30 );
        if ( false === $header || 'PK' . "\x03\x04" !== substr( $header, 0, 4 ) ) {
            return false;
        }
        $flags = self::uint16le( substr( $header, 6, 2 ) );
        $method = self::uint16le( substr( $header, 8, 2 ) );
        $name_length = self::uint16le( substr( $header, 26, 2 ) );
        $extra_length = self::uint16le( substr( $header, 28, 2 ) );
        if ( 0 !== ( $flags & 0x0001 ) || $method !== (int) ( $entry['method'] ?? -1 ) ) {
            return false;
        }
        $raw_name = self::read_segment( $path, $offset + 30, $name_length );
        if ( false === $raw_name || $raw_name !== (string) ( $entry['name'] ?? '' ) ) {
            return false;
        }
        if ( 0 === $compressed && 0 === $uncompressed ) {
            return '';
        }
        $payload = self::read_segment( $path, $offset + 30 + $name_length + $extra_length, $compressed );
        if ( false === $payload || strlen( $payload ) !== $compressed ) {
            return false;
        }
        $content = 0 === $method ? $payload : @gzinflate( $payload, self::MAX_ZIP_INSPECTION_BYTES );
        if ( ! is_string( $content ) || strlen( $content ) !== $uncompressed ) {
            return false;
        }
        $actual_crc = crc32( $content ) & 0xFFFFFFFF;
        return $actual_crc === ( (int) ( $entry['crc'] ?? -1 ) & 0xFFFFFFFF ) ? $content : false;
    }

    private static function uint16le( string $bytes ): int {
        if ( 2 !== strlen( $bytes ) ) {
            return -1;
        }
        $value = unpack( 'vvalue', $bytes );
        return is_array( $value ) ? (int) $value['value'] : -1;
    }

    private static function uint32le_or_be( string $bytes, bool $little_endian ): int {
        if ( 4 !== strlen( $bytes ) ) {
            return -1;
        }
        $value = unpack( ( $little_endian ? 'V' : 'N' ) . 'value', $bytes );
        return is_array( $value ) ? (int) $value['value'] : -1;
    }

    /** @return array<string,array<string,bool>> */
    public static function composer_allowed_html(): array {
        return array(
            'p'          => array(),
            'br'         => array(),
            'strong'     => array(),
            'b'          => array(),
            'em'         => array(),
            'i'          => array(),
            'u'          => array(),
            's'          => array(),
            'blockquote' => array(),
            'ul'         => array(),
            'ol'         => array(),
            'li'         => array(),
            'a'          => array(
                'href'   => true,
                'title'  => true,
                'target' => true,
                'rel'    => true,
            ),
        );
    }

    /** @return array<string,array<string,bool>> */
    public static function incoming_message_allowed_html(): array {
        return array_merge(
            self::composer_allowed_html(),
            array(
                'div'   => array(),
                'span'  => array(),
                'pre'   => array(),
                'code'  => array(),
                'hr'    => array(),
                'h1'    => array(),
                'h2'    => array(),
                'h3'    => array(),
                'h4'    => array(),
                'table' => array(),
                'thead' => array(),
                'tbody' => array(),
                'tfoot' => array(),
                'tr'    => array(),
                'th'    => array(
                    'colspan' => true,
                    'rowspan' => true,
                ),
                'td'    => array(
                    'colspan' => true,
                    'rowspan' => true,
                ),
            )
        );
    }

    public static function sanitize_composer_html( string $html ): string {
        return wp_targeted_link_rel( wp_kses( $html, self::composer_allowed_html() ) );
    }

    public static function sanitize_incoming_html( string $html ): string {
        return wp_targeted_link_rel( wp_kses( $html, self::incoming_message_allowed_html() ) );
    }

    public static function safe_notification_preview( string $subject ): string {
        if ( ! self::notification_subjects_allowed_by_default() ) {
            return 'Nieuw beveiligd bericht';
        }

        $subject = sanitize_text_field( $subject );
        return '' !== $subject ? $subject : 'Nieuw beveiligd bericht';
    }

    private function __construct() {}
}
