<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ClamAV clamd INSTREAM adapter.
 *
 * Uses a Unix socket or explicitly configured internal TCP endpoint. It never
 * executes a shell command and only returns clean on an exact clamd OK result.
 */
final class ClamAV_Attachment_Scanner implements Attachment_Scanner {
    private const MAX_CHUNK_BYTES = 65536;
    private const MAX_RESPONSE_BYTES = 4096;

    public static function configured(): bool {
        if ( defined( 'MVM_HUB_CLAMAV_SOCKET' ) && '' !== trim( (string) MVM_HUB_CLAMAV_SOCKET ) ) {
            return true;
        }
        return defined( 'MVM_HUB_CLAMAV_HOST' )
            && '' !== trim( (string) MVM_HUB_CLAMAV_HOST )
            && defined( 'MVM_HUB_CLAMAV_PORT' )
            && (int) MVM_HUB_CLAMAV_PORT > 0;
    }

    /** @return array<string,mixed>|\WP_Error */
    public function scan( array $normalized_upload ): array|\WP_Error {
        $path = (string) ( $normalized_upload['privateHandle'] ?? '' );
        if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
            return new \WP_Error( 'mvm_mail_attachment_scan_source', 'De bijlage kan niet veilig worden gescand.' );
        }
        if ( ! self::configured() ) {
            return new \WP_Error( 'mvm_mail_clamav_unconfigured', 'De malware-scanner is niet geconfigureerd.' );
        }

        $endpoint = $this->endpoint();
        if ( is_wp_error( $endpoint ) ) {
            return $endpoint;
        }

        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client( $endpoint, $errno, $errstr, 3.0, STREAM_CLIENT_CONNECT );
        if ( false === $stream ) {
            unset( $errstr );
            return new \WP_Error( 'mvm_mail_clamav_connect', 'De malware-scanner is tijdelijk niet bereikbaar.', array( 'scannerCode' => $errno ) );
        }

        stream_set_timeout( $stream, 10 );
        $file = @fopen( $path, 'rb' );
        if ( false === $file ) {
            fclose( $stream );
            return new \WP_Error( 'mvm_mail_attachment_scan_source', 'De bijlage kan niet veilig worden gescand.' );
        }

        try {
            if ( ! self::write_all( $stream, "zINSTREAM\0" ) ) {
                return new \WP_Error( 'mvm_mail_clamav_write', 'De malwarecontrole kon niet worden gestart.' );
            }

            while ( ! feof( $file ) ) {
                $chunk = fread( $file, self::MAX_CHUNK_BYTES );
                if ( false === $chunk ) {
                    return new \WP_Error( 'mvm_mail_clamav_read', 'De bijlage kon niet volledig worden gescand.' );
                }
                if ( '' === $chunk ) {
                    continue;
                }
                if ( ! self::write_all( $stream, pack( 'N', strlen( $chunk ) ) . $chunk ) ) {
                    return new \WP_Error( 'mvm_mail_clamav_write', 'De malwarecontrole werd onderbroken.' );
                }
            }
            if ( ! self::write_all( $stream, pack( 'N', 0 ) ) ) {
                return new \WP_Error( 'mvm_mail_clamav_write', 'De malwarecontrole kon niet worden afgerond.' );
            }

            $response = '';
            while ( ! feof( $stream ) && strlen( $response ) < self::MAX_RESPONSE_BYTES ) {
                $part = fread( $stream, 512 );
                if ( false === $part || '' === $part ) {
                    break;
                }
                $response .= $part;
                if ( str_contains( $response, "\0" ) || str_contains( $response, "\n" ) ) {
                    break;
                }
            }
            $response = trim( str_replace( "\0", '', $response ) );
            if ( preg_match( '/^stream:\s+OK$/i', $response ) ) {
                return array( 'status' => 'clean', 'scanner' => 'clamav' );
            }
            if ( preg_match( '/^stream:\s+(.+)\s+FOUND$/i', $response ) ) {
                return new \WP_Error( 'mvm_mail_attachment_infected', 'De bijlage is door de beveiligingsscan geblokkeerd.' );
            }
            return new \WP_Error( 'mvm_mail_clamav_inconclusive', 'De malware-scanner heeft geen geldige vrijgave gegeven.' );
        } finally {
            fclose( $file );
            fclose( $stream );
        }
    }

    /** @return string|\WP_Error */
    private function endpoint(): string|\WP_Error {
        if ( defined( 'MVM_HUB_CLAMAV_SOCKET' ) && '' !== trim( (string) MVM_HUB_CLAMAV_SOCKET ) ) {
            $socket = trim( (string) MVM_HUB_CLAMAV_SOCKET );
            if ( ! str_starts_with( $socket, '/' ) || str_contains( $socket, "\0" ) ) {
                return new \WP_Error( 'mvm_mail_clamav_socket', 'De ClamAV-socketconfiguratie is ongeldig.' );
            }
            return 'unix://' . $socket;
        }

        $host = preg_replace( '/[^A-Za-z0-9.:-]/', '', (string) MVM_HUB_CLAMAV_HOST );
        $port = max( 1, min( 65535, (int) MVM_HUB_CLAMAV_PORT ) );
        if ( '' === $host ) {
            return new \WP_Error( 'mvm_mail_clamav_host', 'De ClamAV-hostconfiguratie is ongeldig.' );
        }
        return 'tcp://' . $host . ':' . $port;
    }

    /** @param resource $stream */
    private static function write_all( mixed $stream, string $data ): bool {
        $length = strlen( $data );
        $written = 0;
        while ( $written < $length ) {
            $count = fwrite( $stream, substr( $data, $written ) );
            if ( false === $count || 0 === $count ) {
                return false;
            }
            $written += $count;
        }
        return true;
    }
}
