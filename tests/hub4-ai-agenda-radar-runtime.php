<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'DATE_ATOM', 'Y-m-d\TH:i:sP' );

$GLOBALS['mvm_test_options'] = array();

final class WP_Error {
    public function __construct( public string $code = '', public string $message = '', public mixed $data = null ) {}
    public function get_error_code(): string { return $this->code; }
}

function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function absint( mixed $value ): int { return abs( (int) $value ); }
function sanitize_key( string $value ): string { return trim( strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ) ); }
function sanitize_text_field( string $value ): string { return trim( strip_tags( preg_replace( '/[\r\n\t]+/', ' ', $value ) ) ); }
function sanitize_textarea_field( string $value ): string { return trim( strip_tags( $value ) ); }
function esc_url_raw( string $value ): string { return $value; }
function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); }
function remove_accents( string $value ): string { return $value; }
function wp_parse_url( string $value, int $component = -1 ): mixed { return parse_url( $value, $component ); }
function wp_timezone(): DateTimeZone { return new DateTimeZone( 'Europe/Amsterdam' ); }
function wp_date( string $format, ?int $timestamp = null ): string { return ( new DateTimeImmutable( '@' . ( $timestamp ?? time() ) ) )->setTimezone( wp_timezone() )->format( $format ); }
function get_option( string $name, mixed $default = false ): mixed { return $GLOBALS['mvm_test_options'][ $name ] ?? $default; }
function update_option( string $name, mixed $value, bool $autoload = false ): bool { unset( $autoload ); $GLOBALS['mvm_test_options'][ $name ] = $value; return true; }
function wp_json_encode( mixed $value, int $flags = 0 ): string|false { return json_encode( $value, $flags ); }
if ( ! function_exists( 'mb_substr' ) ) {
    function mb_substr( string $value, int $start, ?int $length = null ): string { return substr( $value, $start, $length ); }
}

require_once __DIR__ . '/../plugins/mvm-hub4-rc-direct/src/class-ai-agenda-radar.php';

function mvm_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$settings = MvM_Hub4_AI_Agenda_Radar::sanitize_settings(
    array( 'enabled' => true, 'batch' => 99, 'radar_sort' => 'not-valid', 'last_message' => '<b>Veilig</b>' )
);
mvm_assert( 2 === $settings['batch'], 'batch is hard begrensd tot twee bronnen' );
mvm_assert( 'recent' === $settings['radar_sort'], 'ongeldige sortering valt terug naar recent' );
mvm_assert( 'Veilig' === $settings['last_message'], 'statusbericht wordt gesaneerd' );

$canonical = MvM_Hub4_AI_Agenda_Radar::canonical_url( 'HTTPS://Example.nl/Agenda/?utm_source=test&b=2&a=1#blok' );
mvm_assert( 'https://example.nl/Agenda?a=1&b=2' === $canonical, 'tracking en fragment verdwijnen uit canonieke URL' );

$start = strtotime( '2099-09-12 19:30:00 Europe/Amsterdam' );
$id_a  = MvM_Hub4_AI_Agenda_Radar::candidate_id( 7, 'https://example.nl/agenda?utm_medium=x', $start, 'Dorpsfeest' );
$id_b  = MvM_Hub4_AI_Agenda_Radar::candidate_id( 7, 'https://example.nl/agenda', $start, 'Dorpsfeest' );
$id_c  = MvM_Hub4_AI_Agenda_Radar::candidate_id( 7, 'https://example.nl/agenda', $start + DAY_IN_SECONDS, 'Dorpsfeest' );
mvm_assert( 64 === strlen( $id_a ) && $id_a === $id_b, 'kandidaat-ID is deterministisch na URL-canonicalisatie' );
mvm_assert( $id_a !== $id_c, 'andere evenementdatum geeft ander kandidaat-ID' );

$json_ld = '<script type="application/ld+json">' . json_encode(
    array(
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => 'Dorpsfeest Mierlo',
        'startDate' => '2099-09-12T19:30:00+02:00',
        'url' => '/agenda/dorpsfeest',
        'location' => array( 'name' => 'Mierlo' ),
    )
) . '</script>';
$events = MvM_Hub4_AI_Agenda_Radar::parse_json_ld_events( $json_ld, 'https://example.nl/agenda' );
mvm_assert( 1 === count( $events ), 'expliciet JSON-LD Event wordt herkend' );
mvm_assert( 'https://example.nl/agenda/dorpsfeest' === $events[0]['url'], 'relatieve event-URL wordt veilig absoluut' );

$no_year = '<script type="application/ld+json">{"@type":"Event","name":"Geen jaar","startDate":"12 september"}</script>';
mvm_assert( array() === MvM_Hub4_AI_Agenda_Radar::parse_json_ld_events( $no_year, 'https://example.nl/' ), 'datum zonder expliciet jaar wordt geweigerd' );

$analysis = MvM_Hub4_AI_Agenda_Radar::validate_analysis_payload(
    array(
        'items' => array(
            array(
                'id' => $id_a,
                'status' => 'publiceerbaar',
                'summary' => 'Lokaal evenement.',
                'confidence' => 1.5,
                'reason' => 'Bron noemt Mierlo.',
                'relationship_type' => 'vervolg',
            ),
        ),
    ),
    array( $id_a )
);
mvm_assert( ! is_wp_error( $analysis ), 'geldige allow-listed AI-respons wordt geaccepteerd' );
mvm_assert( 2 === $analysis[0]['confidence'], 'confidence wordt begrensd en afgerond' );
mvm_assert( 'vervolg' === $analysis[0]['relationship_type'], 'relatietype blijft binnen allowlist' );
mvm_assert( is_wp_error( MvM_Hub4_AI_Agenda_Radar::validate_analysis_payload( '{"wrong":[]}', array( $id_a ) ) ), 'ongeldige AI-schemarespons faalt gesloten' );

$GLOBALS['mvm_test_options'][ MvM_Hub4_AI_Agenda_Radar::OPTION_SETTINGS ] = array( 'radar_sort' => 'recent' );
$groups = MvM_Hub4_AI_Agenda_Radar::sort_groups(
    array(
        array( 'primary' => array( 'title' => 'gevonden', 'published_ts' => 0, 'found_ts' => 200 ) ),
        array( 'primary' => array( 'title' => 'gepubliceerd', 'published_ts' => 300, 'found_ts' => 100 ) ),
    )
);
mvm_assert( 'gepubliceerd' === $groups[0]['primary']['title'], 'Radar sorteert eerst op bronpublicatiedatum en dan vondsttijd' );

fwrite( STDOUT, "AI-agendacrawl runtime contracts: passed.\n" );
