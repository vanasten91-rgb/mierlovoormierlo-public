<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Deterministic first-pass scope filter for Nieuwsradar.
 *
 * This class deliberately does not decide whether something is publishable.
 * It only answers whether an item is demonstrably about the village Mierlo,
 * is ambiguous enough to require editorial/AI review, or is clearly outside
 * the MvM geographic scope. That keeps non-Mierlo noise out of expensive AI
 * calls without allowing AI to widen the editorial area by itself.
 */
final class MvM_Hub4_Newsradar_Local_Scope {
    private const ACCEPT_SCORE = 70;
    private const REVIEW_SCORE = 40;

    /**
     * @param array<string,mixed> $item
     * @return array{score:int,decision:string,reasons:array<int,string>,matched_entities:array<int,string>}
     */
    public static function assess( array $item ): array {
        $title   = self::normalize( (string) ( $item['title'] ?? '' ) );
        $body    = self::normalize( (string) ( $item['article_text'] ?? ( $item['excerpt'] ?? ( $item['summary'] ?? '' ) ) ) );
        $source  = self::normalize( (string) ( $item['source'] ?? '' ) );
        $url     = self::normalize( (string) ( $item['url'] ?? ( $item['source_url'] ?? '' ) ) );
        $haystack = trim( implode( ' ', array_filter( array( $title, $body, $source, $url ) ) ) );

        $reasons = array();
        $matched = array();
        $score   = 0;

        if ( '' === $haystack ) {
            return self::result( 0, array( 'Geen tekst of broninformatie om Mierlo-relevantie vast te stellen.' ), array() );
        }

        $has_mierlo_hout = self::contains_any( $haystack, array( 'mierlo-hout', 'mierlo hout' ) );
        $without_mierlo_hout = str_replace( array( 'mierlo-hout', 'mierlo hout' ), ' ', $haystack );
        $has_mierlo = 1 === preg_match( '/(^|[^a-z0-9])mierlo([^a-z0-9]|$)/u', $without_mierlo_hout );

        if ( $has_mierlo ) {
            $score = 100;
            $reasons[] = 'Expliciete verwijzing naar het dorp Mierlo.';
            $matched[] = 'Mierlo';
        }

        foreach ( self::entities() as $label => $aliases ) {
            if ( self::contains_any( $haystack, $aliases ) ) {
                $matched[] = $label;
                if ( $score < 100 ) {
                    $score += 55;
                }
            }
        }

        $matched = array_values( array_unique( $matched ) );
        if ( $matched && ! $has_mierlo ) {
            $reasons[] = 'Bekende Mierlose locatie, organisatie of voorziening herkend: ' . implode( ', ', array_slice( $matched, 0, 4 ) ) . '.';
        }

        $municipal_scope = self::contains_any(
            $haystack,
            array(
                'gemeente geldrop-mierlo',
                'gemeente geldrop mierlo',
                'geldrop-mierlo',
                'geldrop mierlo',
            )
        );
        if ( $municipal_scope && ! $has_mierlo && ! $matched ) {
            $score = max( $score, 45 );
            $reasons[] = 'Gemeentebreed signaal: controleren of de maatregel daadwerkelijk gevolgen voor Mierlo heeft.';
        }

        $outside = self::outside_places();
        $outside_hits = array();
        foreach ( $outside as $place ) {
            if ( false !== strpos( $haystack, $place ) ) {
                $outside_hits[] = $place;
            }
        }
        $outside_hits = array_values( array_unique( $outside_hits ) );

        if ( $has_mierlo_hout && ! $has_mierlo && ! $matched ) {
            $score = 0;
            $reasons[] = 'Mierlo-Hout is een wijk van Helmond en is zonder directe Mierlo-impact buiten scope.';
        } elseif ( $outside_hits && ! $has_mierlo && ! $matched && ! $municipal_scope ) {
            $score = min( $score, 20 );
            $reasons[] = 'Alleen plaatsnamen buiten Mierlo herkend: ' . implode( ', ', array_slice( $outside_hits, 0, 4 ) ) . '.';
        }

        if ( $score >= self::ACCEPT_SCORE ) {
            $score = min( 100, $score );
        }

        return self::result( $score, $reasons, $matched );
    }

    /** @return array<string,array<int,string>> */
    public static function entities(): array {
        $entities = array(
            'Luchen' => array( 'luchen' ),
            'Puur Sang' => array( 'puur sang', 'kindcentrum puur sang' ),
            'Mifano' => array( 'mifano' ),
            'HC Mierlo' => array( 'hc mierlo', 'hockeyclub mierlo' ),
            'Mierlose Tennisvereniging' => array( 'mierlose tennisvereniging' ),
            'Uno Animo' => array( 'uno animo' ),
            'De Kersenplukkers' => array( 'de kersenplukkers', 'kersenplukkers' ),
            'Molenplein' => array( 'molenplein' ),
            'Lucia-kerk' => array( 'lucia-kerk', 'luciakerk', 'heilige lucia kerk' ),
        );

        /**
         * Filters the local entity dictionary. Plugins/themes may add verified
         * Mierlo-only streets, clubs, schools, venues or organisations.
         *
         * @param array<string,array<int,string>> $entities
         */
        $filtered = apply_filters( 'mvm_newsradar_mierlo_entities', $entities );
        return is_array( $filtered ) ? $filtered : $entities;
    }

    /** @return array<int,string> */
    private static function outside_places(): array {
        return array( 'geldrop', 'helmond', 'eindhoven', 'heeze', 'leende', 'nuenen', 'someren', 'asten' );
    }

    private static function normalize( string $text ): string {
        $text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
        $text = remove_accents( $text );
        return strtolower( preg_replace( '/\s+/u', ' ', $text ) ?: $text );
    }

    /** @param array<int,string> $needles */
    private static function contains_any( string $haystack, array $needles ): bool {
        foreach ( $needles as $needle ) {
            $needle = self::normalize( (string) $needle );
            if ( '' !== $needle && false !== strpos( $haystack, $needle ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int,string> $reasons
     * @param array<int,string> $matched
     * @return array{score:int,decision:string,reasons:array<int,string>,matched_entities:array<int,string>}
     */
    private static function result( int $score, array $reasons, array $matched ): array {
        $score = max( 0, min( 100, $score ) );
        $decision = $score >= self::ACCEPT_SCORE ? 'accept' : ( $score >= self::REVIEW_SCORE ? 'review' : 'reject' );
        return array(
            'score'            => $score,
            'decision'         => $decision,
            'reasons'          => array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $reasons ) ) ) ),
            'matched_entities' => array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $matched ) ) ) ),
        );
    }
}
