<?php
// Consolidated from production Code Snippet #509.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mvm_ai_agenda_discover_v2' ) ) {
    function mvm_ai_agenda_discover_v2( $html, $base ) {
        if ( ! class_exists( 'DOMDocument' ) ) { return ''; }
        $d = new DOMDocument();
        libxml_use_internal_errors( true );
        @$d->loadHTML( '<?xml encoding="utf-8" ?>' . substr( (string) $html, 0, 650000 ) );
        $xp = new DOMXPath( $d );
        $links = $xp->query( '//a[@href]' );
        if ( ! $links ) { return ''; }

        $candidates = [];
        foreach ( $links as $a ) {
            $href = trim( (string) $a->getAttribute( 'href' ) );
            if ( '' === $href || 0 === strpos( $href, '#' ) || 0 === stripos( $href, 'mailto:' ) || 0 === stripos( $href, 'tel:' ) ) { continue; }
            $text = trim( preg_replace( '/\s+/u', ' ', (string) $a->textContent ) );
            $u = mvm_ai_agenda_abs_url_v1( $href, $base );
            if ( ! $u || ! wp_http_validate_url( $u ) ) { continue; }

            $hp = strtolower( remove_accents( (string) wp_parse_url( $u, PHP_URL_PATH ) ) );
            $tx = strtolower( remove_accents( $text ) );
            $score = 0;

            if ( preg_match( '#/(agenda|kalender)(?:/|$)#u', $hp ) ) { $score += 120; }
            elseif ( preg_match( '#/(evenementen?|events?)(?:/|$)#u', $hp ) ) { $score += 110; }
            elseif ( preg_match( '#/activiteiten/?$#u', $hp ) ) { $score += 90; }

            if ( preg_match( '/^(agenda|kalender|evenementen?|events?)$/u', $tx ) ) { $score += 90; }
            elseif ( preg_match( '/^(bekijk |naar |onze )?(agenda|kalender|evenementen?|events?)\b/u', $tx ) && mb_strlen( $tx ) <= 50 ) { $score += 70; }
            elseif ( preg_match( '/^(activiteiten|activiteitenkalender)$/u', $tx ) ) { $score += 55; }

            if ( preg_match( '#/(nieuws|artikel|articles?|product|shop|hulpvragen?)(?:/|$)#u', $hp ) ) { $score -= 150; }
            if ( preg_match( '#/activiteiten/[^/]+/?$#u', $hp ) ) { $score -= 60; }

            if ( $score > 0 ) { $candidates[] = [ 'url'=>$u, 'score'=>$score, 'len'=>strlen($hp) ]; }
        }

        if ( ! $candidates ) { return ''; }
        usort( $candidates, static function( $a, $b ) {
            if ( $a['score'] !== $b['score'] ) { return $b['score'] <=> $a['score']; }
            return $a['len'] <=> $b['len'];
        } );
        return esc_url_raw( $candidates[0]['url'] );
    }
}

if ( ! function_exists( 'mvm_ai_agenda_scan_v2' ) ) {
    function mvm_ai_agenda_scan_v2() {
        $s = mvm_ai_agenda_settings_v1();
        if ( empty( $s['enabled'] ) ) { return [ 'added'=>0, 'message'=>'AI-agendacrawl staat uit.' ]; }

        $sources = get_option( 'mvm_bh_sources', [] );
        $sources = array_values( array_filter( is_array($sources) ? $sources : [], static fn($x) => is_array($x) && !empty($x['active']) && empty($x['excluded']) && !empty($x['url']) ) );
        if ( ! $sources ) {
            $s['last_run'] = time();
            $s['last_message'] = 'Geen actieve bronnen met URL.';
            mvm_ai_agenda_save_settings_v1( $s );
            return [ 'added'=>0, 'message'=>$s['last_message'] ];
        }

        $batch = max( 1, min( 5, absint( $s['batch'] ) ) );
        $cursor = absint( $s['cursor'] ) % count( $sources );
        $added = 0; $checked = 0; $agenda_pages = 0; $structured_events = 0;

        for ( $n = 0; $n < $batch; $n++ ) {
            $source = $sources[ ( $cursor + $n ) % count( $sources ) ];
            $html = mvm_ai_agenda_fetch_v1( $source['url'] );
            $checked++;
            if ( is_wp_error( $html ) ) { continue; }

            $events = mvm_ai_agenda_events_v1( $html, $source['url'] );
            if ( $events ) { $structured_events += count( $events ); }

            if ( ! $events ) {
                $agenda = mvm_ai_agenda_discover_v2( $html, $source['url'] );
                if ( $agenda && $agenda !== $source['url'] ) {
                    $agenda_pages++;
                    $ahtml = mvm_ai_agenda_fetch_v1( $agenda );
                    if ( ! is_wp_error( $ahtml ) ) {
                        $events = mvm_ai_agenda_events_v1( $ahtml, $agenda );
                        if ( $events ) { $structured_events += count( $events ); }
                    }
                }
            }

            foreach ( array_slice( $events, 0, 3 ) as $event ) {
                if ( mvm_ai_agenda_merge_result_v1( $source, $event ) ) { $added++; }
            }
        }

        $s['cursor'] = ( $cursor + $batch ) % count( $sources );
        $s['last_run'] = time();
        $s['last_message'] = $checked . ' bron(nen) gecontroleerd; ' . $agenda_pages . ' agenda-/kalenderpagina(s) gevonden; ' . $structured_events . ' gestructureerde event(s); ' . $added . ' nieuw(e) item(s) naar de Nieuwsradar AI-wachtrij.';
        mvm_ai_agenda_save_settings_v1( $s );

        return [
            'added'=>$added,
            'checked'=>$checked,
            'agendaPages'=>$agenda_pages,
            'structuredEvents'=>$structured_events,
            'message'=>$s['last_message'],
        ];
    }
}

add_action( 'init', static function() {
    remove_action( 'mvm_hub_ai_agenda_scan_v1', 'mvm_ai_agenda_scan_v1' );
    add_action( 'mvm_hub_ai_agenda_scan_v1', 'mvm_ai_agenda_scan_v2' );
}, 1 );

add_action( 'rest_api_init', static function() {
    if ( ! class_exists( 'MvM_Hub4_Security' ) || ! class_exists( 'MvM_Hub4_Capabilities' ) ) { return; }
    $manage = MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_MANAGE );
    register_rest_route( 'mvm-hub4/v1', '/agenda-crawl/run', [
        'methods'=>WP_REST_Server::CREATABLE,
        'permission_callback'=>$manage,
        'callback'=>static function() {
            $s = mvm_ai_agenda_settings_v1();
            if ( empty( $s['enabled'] ) ) {
                return new WP_Error( 'mvm_agenda_disabled', 'Zet AI-agendacrawl eerst aan.', [ 'status'=>400 ] );
            }
            return rest_ensure_response( mvm_ai_agenda_scan_v2() );
        },
    ], true );
}, 99 );