<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only bridge between the 18 canonical editorial theme pages and the
 * existing fine-grained mvm_thema taxonomy.
 *
 * The page slugs are editorial groupings, not taxonomy term slugs. Production
 * deliberately contains hundreds of smaller terms instead. This map keeps the
 * existing pages/URLs intact while querying those already stored terms with OR
 * semantics. No taxonomy or post data is created or rewritten.
 */
final class MVM_Encyclopedie_Next_Theme_Groups {
    public static function init(): void {
        // Frontend registers the legacy bridge first; this more precise callback
        // intentionally becomes the final owner of mvm_thema_page.
        add_shortcode( 'mvm_thema_page', array( __CLASS__, 'render' ) );
    }

    /** @return array<string,array<int,string>> */
    public static function groups(): array {
        return array(
            'oorsprong-archeologie' => array(
                'archeologie',
                'archeologie-en-oudste-bewoning',
                'oorsprong-en-vroegste-geschiedenis-van-mierlo',
                'prehistorie',
                'nederzettingsgeschiedenis',
                'vindplaatsen',
            ),
            'heren-heerlijkheid-kasteel' => array(
                'heerlijkheid',
                'de-heerlijkheid-mierlo',
                'de-heren-en-het-kasteel',
                'kasteel',
                'kasteel-van-mierlo',
                'heren',
            ),
            'bestuur-recht-gemeente' => array(
                'bestuur',
                'gemeente',
                'rechtspraak',
                'gemeentebestuur',
                'gemeentegeschiedenis',
                'politiek',
            ),
            'kerk-geloof-religie' => array(
                'religie',
                'kerk',
                'geloof',
                'geloof-en-religieuze-instellingen',
                'kerkgeschiedenis',
                'kerken',
                'parochie',
                'klooster',
            ),
            'oorlog-bevrijding-herdenken' => array(
                'oorlog',
                'tweede-wereldoorlog',
                'tweede-wereldoorlog-en-bevrijding',
                'bevrijding',
                'herdenken',
                'dodenherdenking',
                'verzet',
                'oorlogsgraven',
            ),
            'landbouw-kersen-platteland' => array(
                'landbouw',
                'kersen',
                'kersencultuur',
                'boerenleven',
                'agrarisch-mierlo',
                'boerderijen',
                'tuinbouw',
                'veestapel',
            ),
            'ambachten-textiel-economie' => array(
                'ambachten',
                'textiel',
                'economie',
                'industrie',
                'boerenleven-en-oude-ambachten',
                'industrie-en-ondernemers',
                'arbeid',
                'wevers',
            ),
            'onderwijs-jeugd' => array(
                'onderwijs',
                'jeugd',
                'jeugd-en-opgroeien',
                'scholen',
                'school',
                'meisjesonderwijs',
                'openbaar-onderwijs',
            ),
            'zorg-sociaal-leven' => array(
                'zorg',
                'gezondheid',
                'gezondheid-en-sociaal-leven',
                'sociaal-leven',
                'volksgezondheid',
                'armenzorg',
                'welzijn',
                'wijkverpleging',
            ),
            'cultuur-tradities-identiteit' => array(
                'cultuur',
                'tradities',
                'tradities-en-identiteit',
                'dorpscultuur',
                'dorpsidentiteit',
                'muziek',
                'volkscultuur',
                'dialect',
            ),
            'verenigingen-sport-vrije-tijd' => array(
                'verenigingen',
                'verenigingsleven',
                'verenigingsgeschiedenis',
                'sport',
                'sport-en-sportgeschiedenis',
                'recreatie',
                'evenementen',
                'vrijwilligers',
            ),
            'natuur-landschap-water' => array(
                'natuur',
                'landschap',
                'water',
                'natuur-en-buitengebied',
                'heide',
                'bossen',
                'waterlandschap',
                'biodiversiteit',
            ),
            'verkeer-infrastructuur-nutsvoorzieningen' => array(
                'verkeer',
                'infrastructuur',
                'nutsvoorzieningen',
                'wegen',
                'wegen-en-verbindingen',
                'vervoer',
                'mobiliteit',
                'openbaar-vervoer',
                'spoor',
            ),
            'gebouwen-monumenten-erfgoed' => array(
                'gebouwen',
                'gebouwen-en-monumenten',
                'monumenten',
                'erfgoed',
                'architectuur',
                'rijksmonument',
                'gemeentelijk-monument',
                'monumentenzorg',
            ),
            'buurten-straten-geografie' => array(
                'gehuchten',
                'straten',
                'geografie',
                'historische-geografie',
                'hoeven-en-historische-geografie',
                'toponymie',
                'straatnamen',
                'wijken',
            ),
            'mierlo-hout-brandevoort' => array(
                'mierlo-hout',
                'brandevoort',
                'grenzen',
                'grenswijzigingen',
                'grenzen-en-modern-mierlo',
                'annexatie',
                'gebiedsontwikkeling',
                'ruimtelijke-ontwikkeling',
            ),
            'personen-dorpsverhalen' => array(
                'personen',
                'dorpsverhalen',
                'bijzondere-mierlonaren-en-verhalen',
                'genealogie',
                'familiegeschiedenis',
                'mondelinge-geschiedenis',
                'herinneringen',
            ),
            'onderzoek-bronnen-publicaties' => array(
                'onderzoek',
                'bronnen',
                'publicaties',
                'archieven',
                'heemkunde',
                'historiografie',
                'bibliografie',
                'open-data',
                'kaarten',
            ),
        );
    }

    /** @param array<string,mixed> $atts */
    public static function render( $atts ): string {
        $atts = shortcode_atts( array( 'slug' => '' ), is_array( $atts ) ? $atts : array(), 'mvm_thema_page' );
        $slug = sanitize_title( (string) $atts['slug'] );
        $groups = self::groups();

        if ( '' === $slug || ! isset( $groups[ $slug ] ) ) {
            return '<div class="mvm-encyclopedie-next"><div class="mvm-e3-empty">Dit hoofdthema is niet gekoppeld aan de bestaande kennislaag.</div></div>';
        }

        $page = isset( $_GET['mvm_page'] ) ? max( 1, absint( $_GET['mvm_page'] ) ) : 1;
        $title = get_the_title();
        $description = trim( wp_strip_all_tags( (string) get_post_meta( get_the_ID(), '_yoast_wpseo_metadesc', true ) ) );
        $query = MVM_Encyclopedie_Next_Query::search(
            array(
                'page' => $page,
                'per_page' => 24,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'mvm_thema',
                        'field' => 'slug',
                        'terms' => $groups[ $slug ],
                        'operator' => 'IN',
                        'include_children' => false,
                    ),
                ),
            )
        );

        ob_start();
        ?>
        <div class="mvm-encyclopedie-next" data-mvm-theme-group="<?php echo esc_attr( $slug ); ?>">
            <section class="mvm-e3-hero mvm-e3-hero-compact">
                <div class="mvm-e3-hero-copy">
                    <p class="mvm-e3-kicker">Hoofdthema</p>
                    <h1><?php echo esc_html( $title ); ?></h1>
                    <?php if ( '' !== $description ) : ?><p><?php echo esc_html( $description ); ?></p><?php endif; ?>
                </div>
            </section>
            <section class="mvm-e3-section" aria-labelledby="mvm-e3-theme-results">
                <div class="mvm-e3-section-head">
                    <div>
                        <p class="mvm-e3-kicker">Samengestelde kennislaag</p>
                        <h2 id="mvm-e3-theme-results">Dossiers binnen dit thema</h2>
                    </div>
                    <span class="mvm-e3-count"><?php echo esc_html( number_format_i18n( (int) $query->found_posts ) ); ?> dossiers</span>
                </div>
                <?php echo MVM_Encyclopedie_Next_Frontend::render_query_grid( $query ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo MVM_Encyclopedie_Next_Frontend::render_pagination( (int) $query->max_num_pages, $page, 'mvm_page' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
