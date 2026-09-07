<?php
// Consolidated from production Code Snippet #492.
defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', static function () {
    if ( ! is_singular( 'event_listing' ) ) { return; }
    $id = get_queried_object_id();
    if ( ! $id || 'publish' !== get_post_status( $id ) ) { return; }

    $start_raw = trim( (string) get_post_meta( $id, '_event_start_date', true ) );
    $end_raw = trim( (string) get_post_meta( $id, '_event_end_date', true ) );
    if ( ! $start_raw ) { return; }
    if ( ! $end_raw ) { $end_raw = $start_raw; }
    $tz = wp_timezone();
    $start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $start_raw, $tz );
    $end = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $end_raw, $tz );
    if ( ! $start || ! $end ) { return; }
    if ( $end < $start ) { $end = $start; }
    $all_day = '00:00:00' === substr( $start_raw, 11, 8 ) && substr( $start_raw, 0, 10 ) === substr( $end_raw, 0, 10 ) && in_array( substr( $end_raw, 11, 5 ), [ '23:59', '00:00' ], true );

    $url = get_permalink( $id );
    $title = trim( (string) get_post_field( 'post_title', $id ) );
    $description = trim( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $id ) ) ) );
    $description = wp_trim_words( $description, 55, '…' );
    $image = get_the_post_thumbnail_url( $id, 'full' );
    if ( ! $image ) { $image = trim( (string) get_post_meta( $id, '_event_banner', true ) ); }
    $location_text = trim( (string) get_post_meta( $id, '_event_location', true ) );
    $venue_ids = get_post_meta( $id, '_event_venue_ids', true );
    $venue_id = is_array( $venue_ids ) && ! empty( $venue_ids ) ? absint( reset( $venue_ids ) ) : 0;
    $venue_name = $venue_id && 'publish' === get_post_status( $venue_id ) ? trim( (string) get_post_field( 'post_title', $venue_id ) ) : $location_text;
    $organizer_ids = get_post_meta( $id, '_event_organizer_ids', true );
    $organizer_id = is_array( $organizer_ids ) && ! empty( $organizer_ids ) ? absint( reset( $organizer_ids ) ) : 0;

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        '@id' => $url . '#event',
        'name' => $title,
        'url' => $url,
        'eventStatus' => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'startDate' => $all_day ? $start->format( 'Y-m-d' ) : $start->format( DATE_ATOM ),
        'endDate' => $all_day ? $end->format( 'Y-m-d' ) : $end->format( DATE_ATOM ),
    ];
    if ( $description ) { $schema['description'] = $description; }
    if ( $image ) { $schema['image'] = [ esc_url_raw( $image ) ]; }
    if ( $location_text || $venue_name ) {
        $schema['location'] = [ '@type' => 'Place', 'name' => $venue_name ?: $location_text ];
        if ( $location_text ) { $schema['location']['address'] = $location_text; }
        if ( $venue_id ) { $schema['location']['url'] = get_permalink( $venue_id ); }
    }
    if ( $organizer_id && 'publish' === get_post_status( $organizer_id ) ) {
        $schema['organizer'] = [ '@type' => 'Organization', 'name' => trim( (string) get_post_field( 'post_title', $organizer_id ) ), 'url' => get_permalink( $organizer_id ) ];
    }
    $ticket_type = trim( (string) get_post_meta( $id, '_event_ticket_options', true ) );
    $ticket_price = trim( (string) get_post_meta( $id, '_event_ticket_price', true ) );
    $registration = trim( (string) get_post_meta( $id, '_registration', true ) );
    if ( 'paid' === $ticket_type && $ticket_price && filter_var( $registration, FILTER_VALIDATE_URL ) ) {
        $numeric_price = preg_replace( '/[^0-9,.]/', '', $ticket_price );
        $numeric_price = str_replace( ',', '.', $numeric_price );
        if ( is_numeric( $numeric_price ) ) {
            $schema['offers'] = [ '@type' => 'Offer', 'url' => esc_url_raw( $registration ), 'price' => number_format( (float) $numeric_price, 2, '.', '' ), 'priceCurrency' => 'EUR' ];
        }
    }

    echo '<script id="mvm-event-schema-v1" type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 70 );