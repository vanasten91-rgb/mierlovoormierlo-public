<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Server-rendered, read-only Communications preview for the gated shell probe.
 * List views never render message/mail bodies, credentials or raw provider data.
 */
final class Communications_Preview_Renderer {
    public static function render( string $section ): string {
        return match ( sanitize_key( $section ) ) {
            'messages' => self::messages(),
            'mail'     => self::mail(),
            default    => '',
        };
    }

    private static function messages(): string {
        $payload = Communications_Service_Factory::internal_message_read_service()->list( 1, 20 );
        if ( is_wp_error( $payload ) ) {
            return self::error_state( 'Interne berichten zijn niet beschikbaar binnen deze preview.' );
        }
        $items = is_array( $payload['items'] ?? null ) ? $payload['items'] : array();

        ob_start();
        ?>
        <div class="mvm-communications-preview mvm-communications-preview--messages">
            <header class="mvm-newsroom-preview__section-head">
                <div>
                    <p class="mvm-hub__eyebrow">Communicatie</p>
                    <h2>Interne berichten</h2>
                    <p class="mvm-newsroom-preview__muted">Alleen gesprekken waarvan jij deelnemer bent worden getoond.</p>
                </div>
                <span class="mvm-badge">Privé</span>
            </header>
            <?php if ( array() === $items ) : ?>
                <div class="mvm-empty">Geen interne gesprekken beschikbaar.</div>
            <?php else : ?>
                <div class="mvm-table-wrap"><table class="mvm-table">
                    <thead><tr><th scope="col">Gesprek</th><th scope="col">Deelnemers</th><th scope="col">Ongelezen</th><th scope="col">Bijgewerkt</th></tr></thead>
                    <tbody>
                    <?php foreach ( $items as $item ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( (string) ( $item['title'] ?? 'Gesprek' ) ); ?></strong></td>
                            <td><?php echo esc_html( self::participant_names( $item['participants'] ?? array() ) ); ?></td>
                            <td><?php echo esc_html( (string) max( 0, (int) ( $item['unreadCount'] ?? 0 ) ) ); ?></td>
                            <td><?php echo self::time_cell( (string) ( $item['updatedAtUtc'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php if ( ! empty( $payload['hasMore'] ) ) : ?><p class="mvm-newsroom-preview__muted">Meer gesprekken zijn beschikbaar via de beveiligde read-route.</p><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function mail(): string {
        $service = Communications_Service_Factory::mail_read_service();
        $mailbox = self::mailbox_id();
        $folders = $service->folders( $mailbox );
        if ( is_wp_error( $folders ) ) {
            return self::error_state( 'De redactionele mailbox is niet beschikbaar binnen deze preview.' );
        }
        $inbox = '';
        foreach ( (array) ( $folders['folders'] ?? array() ) as $folder ) {
            if ( is_array( $folder ) && 'inbox' === sanitize_key( (string) ( $folder['specialUse'] ?? '' ) ) ) {
                $inbox = (string) ( $folder['id'] ?? '' );
                break;
            }
        }
        if ( '' === $inbox ) {
            return self::error_state( 'De inbox is niet beschikbaar binnen deze preview.' );
        }
        $payload = $service->messages( $mailbox, $inbox, array( 'page' => 1, 'per_page' => 20, 'sort' => 'date', 'direction' => 'desc', 'state' => 'all' ) );
        if ( is_wp_error( $payload ) ) {
            return self::error_state( 'De redactionele mailbox is niet beschikbaar binnen deze preview.' );
        }
        $items = is_array( $payload['messages'] ?? null ) ? $payload['messages'] : array();

        ob_start();
        ?>
        <div class="mvm-communications-preview mvm-communications-preview--mail">
            <header class="mvm-newsroom-preview__section-head">
                <div>
                    <p class="mvm-hub__eyebrow">Communicatie</p>
                    <h2>Mail</h2>
                    <p class="mvm-newsroom-preview__muted">Mailboxmetadata wordt getoond zonder body, credentials of externe afbeeldingen.</p>
                </div>
                <span class="mvm-badge">Beveiligde mailbox</span>
            </header>
            <?php if ( array() === $items ) : ?>
                <div class="mvm-empty">Geen mailberichten beschikbaar.</div>
            <?php else : ?>
                <div class="mvm-table-wrap"><table class="mvm-table">
                    <thead><tr><th scope="col">Onderwerp</th><th scope="col">Afzender</th><th scope="col">Datum</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    <?php foreach ( $items as $item ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( (string) ( $item['subject'] ?? '(Geen onderwerp)' ) ); ?></strong></td>
                            <td><?php echo esc_html( (string) ( $item['fromLabel'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $item['date'] ?? '' ) ); ?></td>
                            <td><span class="mvm-badge"><?php echo esc_html( ! empty( $item['seen'] ) ? 'Gelezen' : 'Ongelezen' ); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php if ( ! empty( $payload['hasMore'] ) ) : ?><p class="mvm-newsroom-preview__muted">Meer berichten zijn beschikbaar via de beveiligde read-route.</p><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function error_state( string $message ): string { return '<div class="mvm-alert mvm-alert--warning" role="status">' . esc_html( $message ) . '</div>'; }

    private static function participant_names( mixed $participants ): string {
        if ( ! is_array( $participants ) ) { return ''; }
        $names = array();
        foreach ( array_slice( $participants, 0, 25 ) as $participant ) {
            if ( is_array( $participant ) ) {
                $name = sanitize_text_field( (string) ( $participant['displayName'] ?? '' ) );
                if ( '' !== $name ) { $names[] = $name; }
            }
        }
        return implode( ', ', array_values( array_unique( $names ) ) );
    }

    private static function time_cell( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) { return '<span aria-label="Geen datum">—</span>'; }
        $timestamp = strtotime( $value . ( str_contains( $value, 'Z' ) || str_contains( $value, '+' ) ? '' : ' UTC' ) );
        if ( false === $timestamp ) { return '<span>—</span>'; }
        return sprintf( '<time datetime="%1$s">%2$s</time>', esc_attr( gmdate( 'c', $timestamp ) ), esc_html( wp_date( 'd-m-Y H:i', $timestamp ) ) );
    }

    private static function mailbox_id(): string { return defined( 'MVM_HUB_MAILBOX_ID' ) ? sanitize_key( (string) MVM_HUB_MAILBOX_ID ) : 'editorial'; }
    private function __construct() {}
}
