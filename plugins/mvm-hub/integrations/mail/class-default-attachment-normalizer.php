<?php

namespace MVM\Hub\Integrations\Mail;

use MVM\Hub\Core\Communications_Policy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Derives MIME/size from the actual temporary file and re-encodes supported
 * images to strip embedded EXIF/GPS metadata before scanning/storage.
 */
final class Default_Attachment_Normalizer implements Attachment_Normalizer {
    /** @return array{name:string,mimeType:string,sizeBytes:int,privateHandle:mixed}|\WP_Error */
    public function normalize( array $upload ): array|\WP_Error {
        $name = sanitize_file_name( (string) ( $upload['name'] ?? '' ) );
        $tmp  = (string) ( $upload['tmp_name'] ?? $upload['privateHandle'] ?? '' );
        $error = (int) ( $upload['error'] ?? UPLOAD_ERR_OK );
        if ( UPLOAD_ERR_OK !== $error || '' === $name || '' === $tmp || ! is_file( $tmp ) || ! is_readable( $tmp ) ) {
            return new \WP_Error( 'mvm_mail_upload_invalid', 'De upload is ongeldig of niet leesbaar.' );
        }

        $size = filesize( $tmp );
        $size = false === $size ? 0 : (int) $size;
        if ( $size <= 0 ) {
            return new \WP_Error( 'mvm_mail_upload_empty', 'Het bestand is leeg.' );
        }

        $mime = $this->mime( $tmp );
        if ( '' === $mime || 'application/octet-stream' === $mime ) {
            $mime = Communications_Policy::detect_attachment_mime( $name, $tmp );
        }
        if ( '' === $mime ) {
            return new \WP_Error( 'mvm_mail_upload_mime', 'Het bestandstype kon niet veilig worden vastgesteld.' );
        }

        $handle = $tmp;
        if ( str_starts_with( $mime, 'image/' ) ) {
            $normalized = $this->normalize_image( $tmp, $mime );
            if ( is_wp_error( $normalized ) ) {
                return $normalized;
            }
            $handle = $normalized;
            $new_size = filesize( $handle );
            $size = false === $new_size ? 0 : (int) $new_size;
            $mime = $this->mime( $handle );
            if ( '' === $mime || 'application/octet-stream' === $mime ) {
                $mime = Communications_Policy::detect_attachment_mime( $name, $handle );
            }
            if ( '' === $mime ) {
                @unlink( $handle );
                return new \WP_Error( 'mvm_mail_upload_mime', 'Het genormaliseerde bestandstype kon niet veilig worden vastgesteld.' );
            }
        }

        return array(
            'name'          => $name,
            'mimeType'      => sanitize_mime_type( $mime ),
            'sizeBytes'     => $size,
            'privateHandle' => $handle,
        );
    }

    private function mime( string $path ): string {
        if ( class_exists( '\finfo' ) ) {
            $finfo = new \finfo( FILEINFO_MIME_TYPE );
            $mime = $finfo->file( $path );
            if ( is_string( $mime ) && '' !== $mime ) {
                return strtolower( $mime );
            }
        }
        $type = function_exists( 'mime_content_type' ) ? mime_content_type( $path ) : false;
        return is_string( $type ) ? strtolower( $type ) : '';
    }

    /** @return string|\WP_Error */
    private function normalize_image( string $path, string $mime ): string|\WP_Error {
        if ( ! function_exists( 'wp_get_image_editor' ) || ! function_exists( 'wp_tempnam' ) ) {
            return new \WP_Error( 'mvm_mail_image_normalizer', 'Veilige beeldnormalisatie is niet beschikbaar.' );
        }

        $editor = wp_get_image_editor( $path );
        if ( is_wp_error( $editor ) ) {
            return new \WP_Error( 'mvm_mail_image_normalizer', 'De afbeelding kon niet veilig worden herbouwd.' );
        }

        $target = match ( $mime ) {
            'image/jpeg', 'image/pjpeg' => array( '.jpg', 'image/jpeg' ),
            'image/png', 'image/x-png' => array( '.png', 'image/png' ),
            'image/webp' => array( '.webp', 'image/webp' ),
            'image/gif'  => array( '.gif', 'image/gif' ),
            'image/avif' => array( '.avif', 'image/avif' ),
            'image/heic', 'image/heic-sequence' => array( '.heic', 'image/heic' ),
            'image/heif', 'image/heif-sequence' => array( '.heif', 'image/heif' ),
            'image/tiff', 'image/x-tiff' => array( '.tiff', 'image/tiff' ),
            'image/bmp', 'image/x-bmp', 'image/x-ms-bmp' => array( '.bmp', 'image/bmp' ),
            default => array( '', '' ),
        };
        [ $extension, $target_mime ] = $target;
        if ( '' === $extension ) {
            return new \WP_Error( 'mvm_mail_image_type', 'Dit afbeeldingstype wordt niet ondersteund.' );
        }

        $base = wp_tempnam( 'mvm-mail-image' );
        if ( ! is_string( $base ) || '' === $base ) {
            return new \WP_Error( 'mvm_mail_image_temp', 'Er kon geen veilige tijdelijke afbeelding worden gemaakt.' );
        }
        @unlink( $base );
        $target = $base . $extension;
        $saved = $editor->save( $target, $target_mime );
        if ( is_wp_error( $saved ) || ! is_file( $target ) ) {
            @unlink( $target );
            return new \WP_Error( 'mvm_mail_image_normalizer', 'De afbeelding kon niet veilig worden herbouwd.' );
        }
        @chmod( $target, 0600 );
        return $target;
    }
}
