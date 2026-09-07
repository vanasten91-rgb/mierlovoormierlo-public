<?php

if ( ! defined( 'ABSPATH' ) || ! MvM_Hub4_App::is_system_overview_request() || ! MvM_Hub4_App::can_view_system_overview() ) {
    exit;
}

$user        = wp_get_current_user();
$stats       = MvM_Hub4_System_Stats::overview();
$health      = MvM_Hub4_Health_Check::run();
$nonce       = MvM_Hub4_App::csp_nonce();
$config      = MvM_Hub4_App::app_config();
$style_url   = MVM_HUB4_URL . 'assets/system-overview.css?ver=' . rawurlencode( MVM_HUB4_VERSION );
$script_url  = MVM_HUB4_URL . 'assets/system-overview.js?ver=' . rawurlencode( MVM_HUB4_VERSION );

$get_value = static function ( array $data, string $path ): int|string {
    $value = $data;
    foreach ( explode( '.', $path ) as $part ) {
        if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
            return 0;
        }
        $value = $value[ $part ];
    }
    return is_scalar( $value ) ? $value : 0;
};

$detail_sections = array(
    'Nieuws & redactie' => array(
        'news.published'               => 'Gepubliceerd nieuws',
        'news.drafts'                  => 'Concepten',
        'news.pending'                 => 'Wacht op beoordeling',
        'news.scheduled'               => 'Ingepland',
        'news.tips'                    => 'Nieuws-inzendingen',
        'editorial.openAssignments'    => 'Open Hub 4-opdrachten',
        'editorial.hub4Assignments'    => 'Hub 4-opdrachten totaal',
        'editorial.legacyAssignments'  => 'Bestaande/legacy opdrachten',
        'editorial.sources'            => 'Actieve bronnen',
    ),
    'Encyclopedie' => array(
        'encyclopedia.total'        => 'Encyclopedie-items totaal',
        'encyclopedia.articles'     => 'Hoofdartikelen',
        'encyclopedia.people'       => 'Personen',
        'encyclopedia.locations'    => 'Locaties',
        'encyclopedia.buildings'    => 'Gebouwen',
        'encyclopedia.events'       => 'Gebeurtenissen',
        'encyclopedia.associations' => 'Verenigingen',
        'encyclopedia.businesses'   => 'Bedrijven',
        'encyclopedia.images'       => 'Beeldbank',
    ),
    'Community & forum' => array(
        'community.users'          => 'Geregistreerde gebruikers',
        'community.peepsoPosts'    => 'PeepSo-berichten',
        'community.peepsoComments' => 'PeepSo-reacties',
        'community.peepsoLikes'    => 'PeepSo-likes',
        'community.forumTopics'    => 'Forumonderwerpen',
        'community.forumPosts'     => 'Forumberichten',
    ),
    'Media & evenementen' => array(
        'media.libraryImages'   => 'Afbeeldingen mediabibliotheek',
        'media.communityPhotos' => 'Communityfoto’s',
        'media.imageBank'       => 'Encyclopedie beeldbank',
        'events.active'         => 'Actieve evenementen',
        'events.drafts'         => 'Evenementconcepten',
        'events.expired'        => 'Afgelopen evenementen',
    ),
);

$health_labels = array(
    'ok'       => 'In orde',
    'warning'  => 'Waarschuwing',
    'critical' => 'Kritiek',
    'unknown'  => 'Onbekend',
);
$health_status = isset( $health_labels[ $health['status'] ] ) ? (string) $health['status'] : 'unknown';
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive,nosnippet">
    <meta name="referrer" content="same-origin">
    <title><?php echo esc_html( 'Systeemoverzicht · MvM Hub' ); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url( $style_url ); ?>">
</head>
<body class="mvm-system-overview">
<a class="mvm-system-overview__skip" href="#mvm-system-main">Naar statistieken</a>
<header class="mvm-system-overview__header">
    <div>
        <p class="mvm-system-overview__eyebrow">Administrator / SysOp</p>
        <h1>Systeemoverzicht</h1>
        <p>De belangrijkste aantallen van Mierlo voor Mierlo in één veilig, read-only overzicht.</p>
    </div>
    <div class="mvm-system-overview__actions">
        <a class="mvm-system-overview__button mvm-system-overview__button--secondary" href="<?php echo esc_url( home_url( '/hub4/' ) ); ?>">Terug naar Hub</a>
        <button class="mvm-system-overview__button" type="button" data-mvm-system-refresh>Vernieuwen</button>
    </div>
</header>

<main id="mvm-system-main" class="mvm-system-overview__main">
    <div class="mvm-system-overview__meta">
        <span>Ingelogd als <strong><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></strong></span>
        <span data-mvm-system-generated>Bijgewerkt: <?php echo esc_html( gmdate( 'd-m-Y H:i', strtotime( (string) $stats['generatedAtUtc'] ) ) ); ?> UTC</span>
        <span data-mvm-system-status role="status" aria-live="polite"></span>
    </div>

    <section class="mvm-system-health mvm-system-health--<?php echo esc_attr( $health_status ); ?>" data-mvm-health-card aria-labelledby="mvm-system-health-title">
        <div class="mvm-system-health__heading">
            <div>
                <p class="mvm-system-overview__eyebrow">Health Check</p>
                <h2 id="mvm-system-health-title">Systeemstatus</h2>
                <p>Read-only controle van platform, kritieke onderdelen en beschermde MvM-baselines.</p>
            </div>
            <div class="mvm-system-health__overall" data-mvm-health-overall data-status="<?php echo esc_attr( $health_status ); ?>">
                <span class="mvm-system-health__indicator" aria-hidden="true"></span>
                <strong><?php echo esc_html( $health_labels[ $health_status ] ); ?></strong>
            </div>
        </div>

        <div class="mvm-system-health__counts" aria-label="Verdeling van controleresultaten">
            <?php foreach ( $health_labels as $status_key => $status_label ) : ?>
                <div class="mvm-system-health__count mvm-system-health__count--<?php echo esc_attr( $status_key ); ?>">
                    <strong data-health-count="<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( number_format_i18n( (int) ( $health['summary'][ $status_key ] ?? 0 ) ) ); ?></strong>
                    <span><?php echo esc_html( $status_label ); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="mvm-system-health__checked" data-mvm-health-checked>
            Laatste controle: <?php echo esc_html( gmdate( 'd-m-Y H:i', strtotime( (string) $health['checked_at'] ) ) ); ?> UTC
        </p>

        <details class="mvm-system-health__details" data-mvm-health-details>
            <summary>Bekijk alle <span data-health-total><?php echo esc_html( number_format_i18n( (int) ( $health['summary']['total'] ?? 0 ) ) ); ?></span> controles</summary>
            <ul class="mvm-system-health__list" data-mvm-health-list>
                <?php foreach ( (array) $health['checks'] as $check ) : ?>
                    <?php $check_status = isset( $health_labels[ $check['status'] ] ) ? (string) $check['status'] : 'unknown'; ?>
                    <li class="mvm-system-health__item mvm-system-health__item--<?php echo esc_attr( $check_status ); ?>">
                        <span class="mvm-system-health__item-status"><?php echo esc_html( $health_labels[ $check_status ] ); ?></span>
                        <div>
                            <strong><?php echo esc_html( (string) $check['label'] ); ?></strong>
                            <p><?php echo esc_html( (string) $check['summary'] ); ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>
    </section>

    <section aria-labelledby="mvm-system-kerncijfers">
        <div class="mvm-system-overview__section-heading">
            <div>
                <p class="mvm-system-overview__eyebrow">Kerncijfers</p>
                <h2 id="mvm-system-kerncijfers">In één oogopslag</h2>
            </div>
        </div>
        <div class="mvm-system-overview__cards">
            <?php foreach ( (array) $stats['cards'] as $card ) : ?>
                <article class="mvm-system-overview__card">
                    <span><?php echo esc_html( (string) $card['label'] ); ?></span>
                    <strong data-stat-card="<?php echo esc_attr( (string) $card['id'] ); ?>"><?php echo esc_html( number_format_i18n( (int) $card['value'] ) ); ?></strong>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="mvm-system-overview__details">
        <?php foreach ( $detail_sections as $heading => $items ) : ?>
            <section class="mvm-system-overview__panel">
                <h2><?php echo esc_html( $heading ); ?></h2>
                <dl>
                    <?php foreach ( $items as $path => $label ) : ?>
                        <div>
                            <dt><?php echo esc_html( $label ); ?></dt>
                            <dd data-stat-path="<?php echo esc_attr( $path ); ?>"><?php echo esc_html( number_format_i18n( (int) $get_value( $stats, $path ) ) ); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="mvm-system-overview__note" aria-labelledby="mvm-system-privacy">
        <h2 id="mvm-system-privacy">Privacy & veiligheid</h2>
        <p>Dit scherm toont uitsluitend geaggregeerde aantallen. Namen, e-mailadressen, privéberichten, artikelinhoud en andere gevoelige redactiegegevens worden niet in deze statistiekresponse opgenomen.</p>
    </section>
</main>

<script nonce="<?php echo esc_attr( $nonce ); ?>">window.MvMHub4SystemStats=<?php echo wp_json_encode( array( 'restRoot' => $config['restRoot'], 'restNonce' => $config['restNonce'] ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>;</script>
<script src="<?php echo esc_url( $script_url ); ?>" defer></script>
</body>
</html>
