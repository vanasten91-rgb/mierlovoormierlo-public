<?php
// Consolidated from production Code Snippet #489.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mvm_event_calendar_data_v1' ) ) {
    function mvm_event_calendar_data_v1( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id || 'event_listing' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) { return null; }
        $start_raw = trim( (string) get_post_meta( $post_id, '_event_start_date', true ) );
        $end_raw   = trim( (string) get_post_meta( $post_id, '_event_end_date', true ) );
        if ( ! $start_raw ) { return null; }
        if ( ! $end_raw ) { $end_raw = $start_raw; }
        $tz = wp_timezone();
        $start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $start_raw, $tz );
        $end   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $end_raw, $tz );
        if ( ! $start || ! $end ) { return null; }
        if ( $end < $start ) { $end = $start; }
        $all_day = '00:00:00' === substr( $start_raw, 11, 8 ) && substr( $start_raw, 0, 10 ) === substr( $end_raw, 0, 10 ) && in_array( substr( $end_raw, 11, 5 ), [ '23:59', '00:00' ], true );
        return [
            'id' => $post_id,
            'title' => get_the_title( $post_id ),
            'url' => get_permalink( $post_id ),
            'location' => trim( (string) get_post_meta( $post_id, '_event_location', true ) ),
            'start' => $start,
            'end' => $end,
            'all_day' => $all_day,
        ];
    }
}

if ( ! function_exists( 'mvm_event_ics_escape_v1' ) ) {
    function mvm_event_ics_escape_v1( $value ) {
        return str_replace( [ '\\', ';', ',', "\r\n", "\r", "\n" ], [ '\\\\', '\;', '\,', '\n', '\n', '\n' ], (string) $value );
    }
}

add_action( 'template_redirect', static function () {
    if ( empty( $_GET['mvm_event_ics'] ) ) { return; }
    $data = mvm_event_calendar_data_v1( absint( $_GET['mvm_event_ics'] ) );
    if ( ! $data ) { status_header( 404 ); exit; }
    $utc = new DateTimeZone( 'UTC' );
    $lines = [ 'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Mierlo voor Mierlo//Evenementen//NL', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT', 'UID:mvm-event-' . $data['id'] . '@mierlovoormierlo.nl', 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) ];
    if ( $data['all_day'] ) {
        $lines[] = 'DTSTART;VALUE=DATE:' . $data['start']->format( 'Ymd' );
        $lines[] = 'DTEND;VALUE=DATE:' . $data['start']->modify( '+1 day' )->format( 'Ymd' );
    } else {
        $lines[] = 'DTSTART:' . $data['start']->setTimezone( $utc )->format( 'Ymd\THis\Z' );
        $lines[] = 'DTEND:' . $data['end']->setTimezone( $utc )->format( 'Ymd\THis\Z' );
    }
    $lines[] = 'SUMMARY:' . mvm_event_ics_escape_v1( $data['title'] );
    if ( $data['location'] ) { $lines[] = 'LOCATION:' . mvm_event_ics_escape_v1( $data['location'] ); }
    $lines[] = 'DESCRIPTION:' . mvm_event_ics_escape_v1( 'Meer informatie: ' . $data['url'] );
    $lines[] = 'URL:' . mvm_event_ics_escape_v1( $data['url'] );
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';
    nocache_headers();
    header( 'Content-Type: text/calendar; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( get_post_field( 'post_name', $data['id'] ) ?: 'mvm-evenement' ) . '.ics"' );
    echo implode( "\r\n", $lines ) . "\r\n";
    exit;
}, 1 );

add_filter( 'the_content', static function ( $content ) {
    if ( ! is_singular( 'event_listing' ) || ! is_main_query() || ! in_the_loop() ) { return $content; }
    $data = mvm_event_calendar_data_v1( get_the_ID() );
    if ( ! $data ) { return $content; }
    $utc = new DateTimeZone( 'UTC' );
    if ( $data['all_day'] ) {
        $google_dates = $data['start']->format( 'Ymd' ) . '/' . $data['start']->modify( '+1 day' )->format( 'Ymd' );
        $date_label = wp_date( 'j F Y', $data['start']->getTimestamp(), wp_timezone() ) . ' · hele dag';
    } else {
        $google_dates = $data['start']->setTimezone( $utc )->format( 'Ymd\THis\Z' ) . '/' . $data['end']->setTimezone( $utc )->format( 'Ymd\THis\Z' );
        if ( $data['start']->format( 'Y-m-d' ) === $data['end']->format( 'Y-m-d' ) ) {
            $date_label = wp_date( 'j F Y · H:i', $data['start']->getTimestamp(), wp_timezone() ) . '–' . wp_date( 'H:i', $data['end']->getTimestamp(), wp_timezone() );
        } else {
            $date_label = wp_date( 'j F Y · H:i', $data['start']->getTimestamp(), wp_timezone() ) . ' – ' . wp_date( 'j F Y · H:i', $data['end']->getTimestamp(), wp_timezone() );
        }
    }
    $google = add_query_arg( [ 'action' => 'TEMPLATE', 'text' => $data['title'], 'dates' => $google_dates, 'details' => 'Meer informatie: ' . $data['url'], 'location' => $data['location'] ], 'https://calendar.google.com/calendar/render' );
    $ics = add_query_arg( 'mvm_event_ics', $data['id'], home_url( '/' ) );
    $share_text = $data['title'] . ' — ' . $date_label;
    if ( $data['location'] ) { $share_text .= ' — ' . $data['location']; }
    $share_text .= ' — ' . $data['url'];
    $whatsapp = 'https://wa.me/?text=' . rawurlencode( $share_text );
    ob_start();
    echo '<aside class="mvm-event-tools-v1" aria-labelledby="mvm-event-tools-title"><p class="mvm-event-tools-v1__eyebrow">Handig</p><h2 id="mvm-event-tools-title">Zet dit evenement in je agenda</h2><p class="mvm-event-tools-v1__meta">' . esc_html( $date_label );
    if ( $data['location'] ) { echo ' · ' . esc_html( $data['location'] ); }
    echo '</p><div class="mvm-event-tools-v1__actions"><a class="mvm-event-tools-v1__primary" href="' . esc_url( $google ) . '" target="_blank" rel="noopener noreferrer">Google Agenda</a><a href="' . esc_url( $ics ) . '">Download .ics</a><a href="' . esc_url( $whatsapp ) . '" target="_blank" rel="noopener noreferrer">WhatsApp delen</a></div><p class="mvm-event-tools-v1__hint">Het .ics-bestand werkt onder andere met Apple Agenda en Outlook.</p></aside>';
    echo '<style id="mvm-event-tools-v1-css">.mvm-event-tools-v1{margin:24px 0 0;padding:18px;border:1px solid var(--mvm-border,#c5d3df);border-radius:14px;background:var(--mvm-surface,#fff)}.mvm-event-tools-v1__eyebrow{margin:0 0 4px;color:#1966AE;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.mvm-event-tools-v1 h2{margin:0 0 8px;text-transform:none}.mvm-event-tools-v1__meta{margin:0 0 14px;color:var(--mvm-muted,#46596b)}.mvm-event-tools-v1__actions{display:flex;flex-wrap:wrap;gap:8px}.mvm-event-tools-v1__actions a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 13px;border:1px solid #1966AE;border-radius:9px;color:#1966AE;font-weight:750;text-decoration:none}.mvm-event-tools-v1__actions a.mvm-event-tools-v1__primary{background:#1966AE;color:#fff}.mvm-event-tools-v1__actions a:focus-visible{outline:3px solid currentColor;outline-offset:2px}.mvm-event-tools-v1__hint{margin:12px 0 0;font-size:.88rem;color:var(--mvm-muted,#46596b)}@media(max-width:560px){.mvm-event-tools-v1__actions{display:grid;grid-template-columns:1fr}.mvm-event-tools-v1__actions a{width:100%}}</style>';
    return $content . ob_get_clean();
}, 48 );