<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Editorial policy for the Mierlo-only Nieuwsradar.
 * Deterministic on purpose: AI judges items, never which sources deserve budget.
 */
final class MvM_Hub4_Newsradar_Source_Policy {
    /** @return array<int,array<string,string>> */
    public static function curated_sources(): array {
        return array(
            array(
                'key'       => 'luchen_news',
                'title'     => 'Luchen – nieuws',
                'url'       => 'https://luchen.geldrop-mierlo.nl/nieuws',
                'category'  => 'officieel',
                'priority'  => 'A',
                'frequency' => 'daily',
            ),
            array(
                'key'       => 'college_decisions',
                'title'     => 'Gemeente – B&W-besluitenlijsten',
                'url'       => 'https://www.geldrop-mierlo.nl/besluitenlijst',
                'category'  => 'politiek',
                'priority'  => 'A',
                'frequency' => 'daily',
            ),
        );
    }

    /** @return array{priority:string,frequency:string,reason:string} */
    public static function recommend( string $title, string $category, string $current_priority = 'B' ): array {
        $title_norm = self::normalize( $title );
        $category   = MvM_Hub4_Sources::sanitize_category( $category );
        $priority   = strtoupper( sanitize_text_field( $current_priority ) );
        if ( ! in_array( $priority, array( 'A', 'B', 'C' ), true ) ) {
            $priority = 'B';
        }

        $high_speed = array(
            'gemeente geldrop mierlo',
            'gemeente bekendmakingen',
            'gemeente wegwerkzaamheden',
            'gemeente buurtpreventie',
            'gemeenteraad geldrop mierlo ibabs',
            'politie burgernet',
        );
        foreach ( $high_speed as $needle ) {
            if ( false !== strpos( $title_norm, $needle ) ) {
                return array( 'priority' => 'A', 'frequency' => 'four_daily', 'reason' => 'Snel officieel/veiligheidssignaal met mogelijke directe impact op Mierlo.' );
            }
        }

        $daily_local = array(
            'patronaat mierlo',
            'kindcentrum puur sang',
            'mifano',
            'hockeyclub mierlo',
            'ovm mierlo',
            'jvw mierlo',
            'visit geldrop mierlo agenda',
            'luchen nieuws',
            'gemeente b w besluitenlijsten',
        );
        foreach ( $daily_local as $needle ) {
            if ( false !== strpos( $title_norm, $needle ) ) {
                return array( 'priority' => 'A', 'frequency' => 'daily', 'reason' => 'Kernbron met hoge Mierlo-specificiteit en regelmatige actualiteit.' );
            }
        }

        if ( false !== strpos( $title_norm, 'gemeente ondernemersnieuwsbrief' ) ) {
            return array( 'priority' => 'A', 'frequency' => 'three_weekly', 'reason' => 'Officiële ondernemersinformatie; relevant voor Mierlo maar niet tijdkritisch per uur.' );
        }

        if ( 'nieuwssites' === $category ) {
            return array( 'priority' => $priority, 'frequency' => 'daily', 'reason' => 'Brede regionale nieuwsbron: maximaal dagelijks crawlen en ieder item streng op Mierlo filteren.' );
        }

        if ( in_array( $category, array( 'officieel', 'politiek' ), true ) ) {
            return array( 'priority' => $priority === 'C' ? 'B' : $priority, 'frequency' => $priority === 'A' ? 'twice_daily' : 'daily', 'reason' => 'Officiële bron met potentiële directe Mierlo-impact.' );
        }

        if ( 'veiligheid' === $category ) {
            if ( 'A' === $priority ) {
                return array( 'priority' => 'A', 'frequency' => 'twice_daily', 'reason' => 'Primaire veiligheidsbron; meerdere controles per dag zijn gerechtvaardigd.' );
            }
            return array( 'priority' => $priority, 'frequency' => 'daily', 'reason' => 'Secundaire veiligheidsbron; dagelijks is voldoende naast primaire bronnen.' );
        }

        if ( 'onderwijs' === $category ) {
            return array( 'priority' => $priority === 'A' ? 'A' : 'B', 'frequency' => 'three_weekly', 'reason' => 'Lokale onderwijsbron; relevant, maar doorgaans geen dagelijkse nieuwsfrequentie.' );
        }

        if ( in_array( $category, array( 'sport', 'cultuur', 'verenigingen' ), true ) ) {
            return array( 'priority' => $priority === 'A' ? 'A' : 'B', 'frequency' => $priority === 'A' ? 'three_weekly' : 'weekly', 'reason' => 'Lokale club/cultuurbron; periodieke controle voorkomt ruis en mist aankondigingen niet.' );
        }

        if ( 'ondernemers' === $category ) {
            if ( false !== strpos( $title_norm, 'ovm ' ) || false !== strpos( $title_norm, 'ondernemersvereniging' ) ) {
                return array( 'priority' => $priority === 'A' ? 'A' : 'B', 'frequency' => 'three_weekly', 'reason' => 'Collectieve ondernemersbron met bredere lokale nieuwswaarde.' );
            }
            return array( 'priority' => 'C', 'frequency' => 'biweekly', 'reason' => 'Individuele commerciële bron; alleen incidenteel nieuwswaardig voor Mierlo.' );
        }

        if ( 'lokaal_sociaal' === $category ) {
            $low_signal = array( 'tandarts', 'podotherapie', 'osteopathie', 'logopedie', 'dieet', 'fysio', 'psychologie', 'revalidatie', 'verloskund', 'zorgkaart' );
            foreach ( $low_signal as $needle ) {
                if ( false !== strpos( $title_norm, $needle ) ) {
                    return array( 'priority' => 'C', 'frequency' => 'monthly', 'reason' => 'Lokale dienstverlener met lage structurele nieuwsfrequentie.' );
                }
            }
            return array( 'priority' => $priority === 'A' ? 'A' : 'B', 'frequency' => $priority === 'A' ? 'three_weekly' : 'weekly', 'reason' => 'Lokale sociale/maatschappelijke bron; regelmatig maar niet continu controleren.' );
        }

        return array( 'priority' => 'C', 'frequency' => 'biweekly', 'reason' => 'Overige bron; lage standaardfrequentie totdat lokale nieuwswaarde is bewezen.' );
    }

    private static function normalize( string $value ): string {
        if ( function_exists( 'remove_accents' ) ) {
            $value = remove_accents( $value );
        }
        $value = strtolower( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' ) );
        $value = preg_replace( '/[^a-z0-9]+/', ' ', $value );
        return trim( (string) $value );
    }
}
