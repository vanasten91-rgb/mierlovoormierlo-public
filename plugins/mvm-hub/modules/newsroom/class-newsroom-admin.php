<?php

namespace MVM\Hub\Modules\Newsroom;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Hub_Security_Policy;
use MVM\Hub\Core\Runtime_Gates;
use MVM\Hub\Modules\Newsroom\Dashboard\Today_Read_Model;
use MVM\Hub\Modules\Newsroom\News\News_Read_Model;
use MVM\Hub\Modules\Newsroom\Read\Newsroom_Read_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Backend-only Newsroom 2.0 for MvM editorial staff. */
final class Newsroom_Admin {
    private const CAPABILITY = Capabilities::NEWSROOM_ACCESS;
    private const ROOT_SLUG  = 'mvm-newsroom';

    /** @var array<string,string> */
    private const PAGES = array(
        'mvm-newsroom'            => 'Start',
        'mvm-newsroom-stories'    => 'Verhalen',
        'mvm-newsroom-signals'    => 'Wat speelt er?',
        'mvm-newsroom-planning'   => 'Planning',
        'mvm-newsroom-publishing' => 'Publiceren',
        'mvm-newsroom-board'      => 'Prikbord',
        'mvm-newsroom-chat'       => 'Teamchat',
        'mvm-newsroom-team'       => 'Team',
        'mvm-newsroom-help'       => 'Hulp & werkwijze',
    );

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;

        add_action( 'admin_menu', array( self::class, 'register_menu' ), 20 );
        add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
        add_action( 'admin_init', array( self::class, 'protect_screen' ), 1 );
        add_action( 'send_headers', array( self::class, 'private_headers' ), 1 );
    }

    public static function register_menu(): void {
        if ( ! self::can_enter() ) {
            return;
        }

        add_menu_page(
            __( 'MvM Newsroom', 'mvm-hub' ),
            __( 'MvM Newsroom', 'mvm-hub' ),
            self::CAPABILITY,
            self::ROOT_SLUG,
            array( self::class, 'render_start' ),
            'dashicons-megaphone',
            3
        );

        self::submenu( self::ROOT_SLUG, 'Start', array( self::class, 'render_start' ) );
        self::submenu( 'mvm-newsroom-stories', 'Verhalen', array( self::class, 'render_stories' ) );
        self::submenu( 'mvm-newsroom-signals', 'Wat speelt er?', array( self::class, 'render_signals' ) );
        self::submenu( 'mvm-newsroom-planning', 'Planning', array( self::class, 'render_planning' ) );
        self::submenu( 'mvm-newsroom-publishing', 'Publiceren', array( self::class, 'render_publishing' ) );
        if ( Capabilities::can_access_staff_board() ) {
            self::submenu( 'mvm-newsroom-board', 'Prikbord', array( self::class, 'render_board' ) );
        }
        if ( Capabilities::can_access_team_chat() ) {
            self::submenu( 'mvm-newsroom-chat', 'Teamchat', array( self::class, 'render_chat' ) );
        }
        self::submenu( 'mvm-newsroom-team', 'Team', array( self::class, 'render_team' ) );
        self::submenu( 'mvm-newsroom-help', 'Hulp & werkwijze', array( self::class, 'render_help' ) );
    }

    private static function submenu( string $slug, string $label, callable $callback ): void {
        add_submenu_page(
            self::ROOT_SLUG,
            $label . ' · MvM Newsroom',
            $label,
            self::CAPABILITY,
            $slug,
            $callback
        );
    }

    public static function enqueue_assets( string $hook_suffix ): void {
        if ( ! self::is_newsroom_screen() || ! self::can_enter() ) {
            return;
        }

        wp_enqueue_style(
            'mvm-newsroom2-admin',
            plugin_dir_url( MVM_HUB_FILE ) . 'assets/newsroom2-admin.css',
            array(),
            MVM_HUB_VERSION
        );
        wp_enqueue_script(
            'mvm-newsroom2-admin',
            plugin_dir_url( MVM_HUB_FILE ) . 'assets/newsroom2-admin.js',
            array(),
            MVM_HUB_VERSION,
            true
        );
        wp_add_inline_script(
            'mvm-newsroom2-admin',
            'window.MVMNewsroom2=' . wp_json_encode(
                array(
                    'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                    'nonce'         => Staff_Collaboration::nonce(),
                    'userId'        => get_current_user_id(),
                    'writesEnabled' => Runtime_Gates::newsroom_writes_enabled(),
                )
            ) . ';',
            'before'
        );
    }

    public static function protect_screen(): void {
        if ( ! is_admin() || ! self::is_newsroom_screen() ) {
            return;
        }
        if ( ! is_user_logged_in() || ! self::can_enter() ) {
            wp_die( esc_html__( 'Je hebt geen toegang tot de MvM Newsroom.', 'mvm-hub' ), esc_html__( 'Toegang geweigerd', 'mvm-hub' ), array( 'response' => 403 ) );
        }
    }

    public static function private_headers(): void {
        if ( ! is_admin() || ! self::is_newsroom_screen() ) {
            return;
        }
        foreach ( Hub_Security_Policy::response_headers() as $name => $value ) {
            header( $name . ': ' . $value, true );
        }
    }

    public static function render_start(): void {
        self::guard();
        $snapshot = ( new Today_Read_Model() )->snapshot();
        $metrics  = is_array( $snapshot['metrics'] ?? null ) ? $snapshot['metrics'] : array();
        $actions  = is_array( $snapshot['guidance'] ?? null ) ? $snapshot['guidance'] : array();

        self::open_page( 'Start', 'Je rustige vertrekpunt voor de redactiedag.', 'Begin bovenaan. Werk eerst af wat aandacht vraagt en open daarna alleen het onderdeel dat je nodig hebt.' );
        echo '<div class="mvm-nr2-metrics">';
        self::metric( 'Verhalen', (int) ( $metrics['newsAwaitingAction'] ?? 0 ), 'vragen redactionele actie', 'mvm-newsroom-stories' );
        self::metric( 'Signalen', (int) ( $metrics['signalsNeedingTriage'] ?? 0 ), 'moeten beoordeeld worden', 'mvm-newsroom-signals' );
        self::metric( 'Planning', (int) ( $metrics['upcomingCalendar'] ?? 0 ), 'items komende 7 dagen', 'mvm-newsroom-planning' );
        self::metric( 'Publiceren', (int) ( $metrics['distributionNeedsAction'] ?? 0 ), 'publicatie-acties open', 'mvm-newsroom-publishing' );
        echo '</div>';

        echo '<div class="mvm-nr2-startgrid"><section class="mvm-nr2-card"><div class="mvm-nr2-card__head"><div><span>Wat nu?</span><h2>Volgende acties</h2></div></div>';
        if ( array() === $actions ) {
            echo '<p class="mvm-nr2-empty">Geen urgente redactionele acties. De werkvoorraad is op orde.</p>';
        } else {
            echo '<ol class="mvm-nr2-actionlist">';
            foreach ( array_slice( $actions, 0, 6 ) as $action ) {
                echo '<li><strong>' . esc_html( (string) ( $action['title'] ?? 'Actie' ) ) . '</strong><span>' . esc_html( (string) ( $action['instruction'] ?? '' ) ) . '</span></li>';
            }
            echo '</ol>';
        }
        echo '</section>';
        echo '<section class="mvm-nr2-card"><div class="mvm-nr2-card__head"><div><span>Samenwerken</span><h2>Redactieteam</h2></div></div><div class="mvm-nr2-quicklinks">';
        if ( Capabilities::can_access_staff_board() ) {
            self::quick_link( 'Prikbord', 'Mededelingen, afspraken en belangrijke informatie die moet blijven staan.', 'mvm-newsroom-board' );
        }
        if ( Capabilities::can_access_team_chat() ) {
            self::quick_link( 'Teamchat', 'Snelle interne afstemming met de staf. Niet bedoeld als archief of bronregistratie.', 'mvm-newsroom-chat' );
        }
        echo '</div></section></div>';

        self::expectation( 'Aan het einde van je startcheck', array(
            'weet je welke verhalen vandaag aandacht vragen;',
            'heb je nieuwe signalen beoordeeld of toegewezen;',
            'ken je belangrijke deadlines en gebeurtenissen;',
            'heb je relevante stafmededelingen gelezen;',
            'laat je onzekere informatie als concept of ter beoordeling staan.'
        ) );
        self::close_page();
    }

    public static function render_stories(): void {
        self::guard();
        $payload = ( new News_Read_Model() )->list( 1, 30, '' );
        $items = is_array( $payload['items'] ?? null ) ? $payload['items'] : array();
        self::open_page( 'Verhalen', 'Hier werk je aan nieuws van idee tot publiceerbaar verhaal.', 'Controleer feiten, schrijf helder en laat een tweede paar ogen meekijken zodra het onderwerp gevoelig of onzeker is.' );
        self::principles( array(
            'Feiten eerst' => 'Publiceer geen onbevestigde beweringen als feit.',
            'Bron duidelijk' => 'Leg intern vast waar informatie vandaan komt.',
            'Privacy bewust' => 'Neem persoonsgegevens alleen op als ze redactioneel noodzakelijk zijn.',
            'Vier-ogen bij twijfel' => 'Gebruik beoordeling bij gevoelige onderwerpen, correcties en twijfelgevallen.',
        ) );
        self::simple_table( array( 'Verhaal', 'Fase', 'Gewijzigd', 'Wat kun jij?' ), array_map(
            static fn( array $item ): array => array(
                (string) ( $item['title'] ?? '(Zonder titel)' ),
                self::workflow_label( (string) ( $item['workflowState'] ?? $item['wpStatus'] ?? '' ) ),
                self::date_label( (string) ( $item['modifiedUtc'] ?? '' ) ),
                ! empty( $item['canEdit'] ) ? 'Bewerken' : 'Lezen',
            ),
            $items
        ) );
        self::expectation( 'Een verhaal is klaar voor beoordeling wanneer', array(
            'titel en kern helder zijn;', 'belangrijkste feiten gecontroleerd zijn;', 'bronverwijzingen intern kloppen;', 'privacy en mogelijke schade zijn meegewogen;', 'beeld en bijschrift kloppen als media is toegevoegd.'
        ) );
        self::close_page();
    }

    public static function render_signals(): void {
        self::guard();
        $model = new Newsroom_Read_Model();
        $signals = $model->radar( 1, 30, '' );
        $sources = $model->sources( 1, 12, '' );
        self::open_page( 'Wat speelt er?', 'Een inbox voor gebeurtenissen, tips en signalen die mogelijk nieuws worden.', 'Beoordeel eerst relevantie en betrouwbaarheid. Een signaal is nog geen nieuwsbericht.' );
        self::principles( array(
            '1. Lokaal relevant?' => 'Heeft dit betekenis voor inwoners van Mierlo?',
            '2. Verifieerbaar?' => 'Zoek een onafhankelijke of primaire bevestiging.',
            '3. Haast?' => 'Spoed verandert niet de norm voor verificatie.',
            '4. Risico?' => 'Bij veiligheid, reputatie of persoonsgegevens laat je een bevoegde collega meekijken.',
        ) );
        $signal_items = is_array( $signals['items'] ?? null ) ? $signals['items'] : array();
        self::simple_table( array( 'Signaal', 'Status', 'Verificatie', 'Prioriteit' ), array_map(
            static fn( array $item ): array => array(
                (string) ( $item['title'] ?? '(Zonder titel)' ), self::workflow_label( (string) ( $item['status'] ?? '' ) ), self::workflow_label( (string) ( $item['verificationStatus'] ?? '' ) ), (string) max( 0, (int) ( $item['priority'] ?? 0 ) )
            ),
            $signal_items
        ) );
        $source_items = is_array( $sources['items'] ?? null ) ? $sources['items'] : array();
        echo '<section class="mvm-nr2-card"><div class="mvm-nr2-card__head"><div><span>Broncontrole</span><h2>Bronnen die aandacht vragen</h2></div></div><div class="mvm-nr2-sourcegrid">';
        foreach ( array_slice( $source_items, 0, 8 ) as $source ) {
            echo '<article><strong>' . esc_html( (string) ( $source['title'] ?? 'Bron' ) ) . '</strong><span>' . esc_html( self::workflow_label( (string) ( $source['status'] ?? '' ) ) ) . '</span><small>' . esc_html( ! empty( $source['monitorEnabled'] ) ? 'Monitoring aan' : 'Monitoring uit' ) . '</small></article>';
        }
        if ( array() === $source_items ) echo '<p class="mvm-nr2-empty">Geen bronitems beschikbaar.</p>';
        echo '</div></section>';
        self::close_page();
    }

    public static function render_planning(): void {
        self::guard();
        $payload = ( new Newsroom_Read_Model() )->agenda( 1, 40, '' );
        $items = is_array( $payload['items'] ?? null ) ? $payload['items'] : array();
        self::open_page( 'Planning', 'Zie vooruit wat eraan komt en voorkom dat lokaal nieuws te laat wordt opgepakt.', 'Bepaal per item: volgen, iemand toewijzen, voorbereiden of bewust niets doen.' );
        self::simple_table( array( 'Moment', 'Onderwerp', 'Soort', 'Status' ), array_map(
            static fn( array $item ): array => array( self::date_label( (string) ( $item['startsAtUtc'] ?? '' ) ), (string) ( $item['title'] ?? '(Zonder titel)' ), self::workflow_label( (string) ( $item['kind'] ?? '' ) ), self::workflow_label( (string) ( $item['status'] ?? '' ) ) ),
            $items
        ) );
        self::expectation( 'Goede planning betekent', array( 'belangrijke lokale momenten hebben een eigenaar;', 'deadlines zijn realistisch;', 'fotografie of andere media wordt op tijd geregeld;', 'niemand hoeft op de dag zelf te raden wat er verwacht wordt.' ) );
        self::close_page();
    }

    public static function render_publishing(): void {
        self::guard();
        self::open_page( 'Publiceren', 'De laatste veiligheids- en kwaliteitscontrole vóór iets zichtbaar wordt voor inwoners.', 'Publiceren is bewust gescheiden van schrijven. Alleen bevoegde rollen krijgen publicatie-acties.' );
        self::principles( array(
            'Inhoud klopt' => 'Titel, tekst, namen, datum, locatie en links zijn gecontroleerd.',
            'Juridisch en privacy' => 'Geen onnodige persoonsgegevens, gevoelige gegevens of ongefundeerde beschuldigingen.',
            'Beeld klopt' => 'Rechten, bron, alt-tekst en bijschrift zijn in orde.',
            'Publicatie is bewust' => 'Controleer categorie, planning en zichtbaarheid vóór definitief publiceren.',
            'Correcties traceerbaar' => 'Wijzigingen aan gepubliceerd nieuws horen in de audittrail.',
        ) );
        echo '<section class="mvm-nr2-card mvm-nr2-card--safe"><div class="mvm-nr2-card__head"><div><span>Veilige standaard</span><h2>Geen blind één-klik-publiceren</h2></div></div><p>Een definitieve publicatieactie verschijnt alleen bij de juiste capability. Zonder bevoegdheid blijft dit scherm informatief en read-only.</p></section>';
        self::close_page();
    }

    public static function render_board(): void {
        self::guard_collaboration( 'board' );
        self::open_page( 'Prikbord', 'Vaste interne mededelingen, afspraken en praktische informatie voor de staf.', 'Gebruik het prikbord voor informatie die collega’s later terug moeten kunnen vinden. Gebruik Teamchat voor korte afstemming.' );
        echo '<section class="mvm-nr2-collab" data-nr2-board>';
        if ( Capabilities::can_post_staff_board() ) {
            echo '<form class="mvm-nr2-card mvm-nr2-compose" data-nr2-board-form><div class="mvm-nr2-card__head"><div><span>Nieuwe mededeling</span><h2>Plaats op het prikbord</h2></div></div><label>Titel<input type="text" name="title" maxlength="120" required autocomplete="off"></label><label>Bericht<textarea name="message" maxlength="4000" rows="5" required></textarea></label><div class="mvm-nr2-compose__footer"><label class="mvm-nr2-inlinefield">Belang<select name="priority"><option value="normal">Normaal</option><option value="important">Belangrijk</option></select></label><button type="submit" class="button button-primary">Plaatsen</button></div><p class="mvm-nr2-formstatus" data-nr2-board-status role="status" aria-live="polite"></p></form>';
        }
        echo '<section class="mvm-nr2-card"><div class="mvm-nr2-card__head"><div><span>Voor het team</span><h2>Mededelingen</h2></div><button type="button" class="button" data-nr2-board-refresh>Vernieuwen</button></div><div class="mvm-nr2-boardlist" data-nr2-board-list><p class="mvm-nr2-empty">Prikbord wordt geladen…</p></div></section></section>';
        self::expectation( 'Gebruik het prikbord zorgvuldig', array( 'zet geen wachtwoorden, privésleutels of gevoelige brongegevens op het prikbord;', 'schrijf duidelijk wie iets moet doen en wanneer;', 'gebruik “Belangrijk” alleen als collega’s er echt op moeten letten;', 'editors en teamleiders kunnen berichten vastpinnen of modereren.' ) );
        self::close_page();
    }

    public static function render_chat(): void {
        self::guard_collaboration( 'chat' );
        self::open_page( 'Teamchat', 'Snelle interne afstemming met de redactie, binnen de beveiligde Hub.', 'Hou berichten kort. Besluiten, bronnen, opdrachten en blijvende afspraken horen daarna op de juiste vaste plek.' );
        echo '<section class="mvm-nr2-card mvm-nr2-chat" data-nr2-chat><div class="mvm-nr2-card__head"><div><span>Redactieteam</span><h2>Teamchat</h2></div><span class="mvm-nr2-live">Automatisch bijgewerkt</span></div><div class="mvm-nr2-chatlog" data-nr2-chat-list aria-live="polite"><p class="mvm-nr2-empty">Chat wordt geladen…</p></div>';
        if ( Capabilities::can_send_team_chat() ) {
            echo '<form class="mvm-nr2-chatform" data-nr2-chat-form><label class="screen-reader-text" for="mvm-nr2-chat-message">Bericht</label><textarea id="mvm-nr2-chat-message" name="message" rows="2" maxlength="1500" placeholder="Schrijf een kort bericht aan het team…" required></textarea><button type="submit" class="button button-primary">Versturen</button><p class="mvm-nr2-formstatus" data-nr2-chat-status role="status" aria-live="polite"></p></form>';
        }
        echo '</section>';
        self::expectation( 'Teamchat is voor afstemming, niet voor archivering', array( 'deel geen wachtwoorden of geheime sleutels;', 'zet gevoelige broninformatie alleen in daarvoor bedoelde afgeschermde dossiers;', 'verplaats blijvende afspraken naar het Prikbord of de Planning;', 'eigen chatberichten kunnen kort na verzending worden verwijderd; moderatoren kunnen ingrijpen.' ) );
        self::close_page();
    }

    public static function render_team(): void {
        self::guard();
        self::open_page( 'Team', 'Iedereen ziet alleen de redactionele functies die bij zijn of haar rol horen.', 'Gebruik rollen voor verantwoordelijkheden; geef nooit extra rechten alleen om een scherm zichtbaar te maken.' );
        self::principles( array(
            'Journalist' => 'Maakt en bewerkt eigen verhalen en voert toegewezen werk uit.',
            'Fotograaf' => 'Werkt met media-opdrachten en bijbehorende informatie.',
            'Editor' => 'Kan teamwerk beoordelen, prikbord modereren en redactioneel bijsturen.',
            'Moderator' => 'Behandelt moderatie volgens eigen afgeschermde rechten.',
            'Teamleider' => 'Beheert redactionele werkverdeling, teamprocessen en stafcommunicatie.',
            'Beheerder' => 'Technische beheerrechten blijven gescheiden van dagelijks redactiewerk.',
        ) );
        echo '<div class="mvm-nr2-startgrid">';
        if ( Capabilities::can_access_staff_board() ) self::quick_link_card( 'Prikbord', 'Mededelingen die het hele team moet kunnen terugvinden.', 'mvm-newsroom-board' );
        if ( Capabilities::can_access_team_chat() ) self::quick_link_card( 'Teamchat', 'Korte dagelijkse afstemming binnen de beveiligde redactieomgeving.', 'mvm-newsroom-chat' );
        echo '</div>';
        self::expectation( 'Veilig samenwerken', array( 'deel accounts nooit;', 'gebruik geen beheeraccount voor normaal redactiewerk;', 'sluit je sessie op gedeelde apparaten;', 'meld onverwachte toegang of verdachte wijzigingen direct;', 'beperk gevoelige broninformatie tot collega’s die die informatie echt nodig hebben.' ) );
        self::close_page();
    }

    public static function render_help(): void {
        self::guard();
        self::open_page( 'Hulp & werkwijze', 'Een korte handleiding voor nieuwe en bestaande staf.', 'Gebruik deze pagina als je twijfelt wat de volgende veilige stap is.' );
        self::principles( array(
            'Start' => 'Kijk eerst wat vandaag aandacht vraagt.',
            'Verhalen' => 'Schrijf, controleer en laat beoordelen voordat je publiceert.',
            'Wat speelt er?' => 'Maak van tips pas nieuws na verificatie.',
            'Planning' => 'Zet belangrijke momenten tijdig op de redactieagenda.',
            'Publiceren' => 'Doe de laatste kwaliteits-, privacy- en rechtencheck.',
            'Prikbord' => 'Gebruik voor vaste interne mededelingen en afspraken.',
            'Teamchat' => 'Gebruik voor korte afstemming; verplaats blijvende informatie daarna naar de juiste plek.',
            'Team' => 'Werk binnen je rol en vraag gerichte hulp als je iets niet mag uitvoeren.',
        ) );
        echo '<section class="mvm-nr2-card"><div class="mvm-nr2-card__head"><div><span>Bij twijfel</span><h2>Niet gokken</h2></div></div><p>Laat een item als concept of ter beoordeling staan. Vraag een editor of teamleider om mee te kijken. Extra controle is beter dan een onjuiste publicatie of het onbedoeld delen van gevoelige informatie.</p></section>';
        self::close_page();
    }

    private static function can_enter(): bool {
        return is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( self::CAPABILITY ) );
    }

    private static function guard(): void {
        if ( ! self::can_enter() ) wp_die( esc_html__( 'Je hebt geen toegang tot de MvM Newsroom.', 'mvm-hub' ), '', array( 'response' => 403 ) );
    }

    private static function guard_collaboration( string $kind ): void {
        self::guard();
        $allowed = 'board' === $kind ? Capabilities::can_access_staff_board() : Capabilities::can_access_team_chat();
        if ( ! $allowed ) wp_die( esc_html__( 'Je hebt geen toegang tot dit stafonderdeel.', 'mvm-hub' ), '', array( 'response' => 403 ) );
    }

    private static function is_newsroom_screen(): bool {
        if ( ! is_admin() ) return false;
        $page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
        return isset( self::PAGES[ $page ] );
    }

    private static function open_page( string $title, string $description, string $expectation ): void {
        $user = wp_get_current_user();
        ?>
        <div class="wrap mvm-nr2" data-mvm-newsroom2>
            <header class="mvm-nr2-hero">
                <div><p class="mvm-nr2-eyebrow">MvM Newsroom 2.0 · alleen voor staf</p><h1><?php echo esc_html( $title ); ?></h1><p class="mvm-nr2-lead"><?php echo esc_html( $description ); ?></p></div>
                <div class="mvm-nr2-user"><span>Ingelogd als</span><strong><?php echo esc_html( $user->display_name ); ?></strong><button type="button" class="mvm-nr2-theme-toggle" data-nr2-theme-toggle aria-pressed="false"><span aria-hidden="true">◐</span><span data-nr2-theme-label>Lichte modus</span></button></div>
            </header>
            <aside class="mvm-nr2-guide" aria-label="Wat wordt hier van je verwacht?"><strong>Wat wordt hier van je verwacht?</strong><span><?php echo esc_html( $expectation ); ?></span></aside>
        <?php
    }

    private static function close_page(): void {
        echo '<footer class="mvm-nr2-footer"><span>Privé redactieomgeving · niet openbaar · geen cache</span><a href="' . esc_url( admin_url( 'admin.php?page=mvm-newsroom-help' ) ) . '">Hulp & werkwijze</a></footer></div>';
    }

    private static function metric( string $label, int $value, string $detail, string $slug ): void {
        echo '<a class="mvm-nr2-metric" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"><strong>' . esc_html( (string) max( 0, $value ) ) . '</strong><span>' . esc_html( $label ) . '</span><small>' . esc_html( $detail ) . '</small></a>';
    }

    private static function quick_link( string $title, string $text, string $slug ): void {
        echo '<a class="mvm-nr2-quicklink" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"><strong>' . esc_html( $title ) . '</strong><span>' . esc_html( $text ) . '</span><b aria-hidden="true">→</b></a>';
    }

    private static function quick_link_card( string $title, string $text, string $slug ): void {
        echo '<section class="mvm-nr2-card">';
        self::quick_link( $title, $text, $slug );
        echo '</section>';
    }

    /** @param array<string,string> $items */
    private static function principles( array $items ): void {
        echo '<section class="mvm-nr2-principles">';
        foreach ( $items as $title => $text ) echo '<article class="mvm-nr2-card"><strong>' . esc_html( $title ) . '</strong><p>' . esc_html( $text ) . '</p></article>';
        echo '</section>';
    }

    /** @param array<int,string> $headers @param array<int,array<int,string>> $rows */
    private static function simple_table( array $headers, array $rows ): void {
        echo '<section class="mvm-nr2-card mvm-nr2-tablecard"><div class="mvm-nr2-tablewrap"><table><thead><tr>';
        foreach ( $headers as $header ) echo '<th scope="col">' . esc_html( $header ) . '</th>';
        echo '</tr></thead><tbody>';
        if ( array() === $rows ) {
            echo '<tr><td colspan="' . esc_attr( (string) max( 1, count( $headers ) ) ) . '">Geen items binnen jouw toegangsrechten.</td></tr>';
        } else {
            foreach ( $rows as $row ) { echo '<tr>'; foreach ( $row as $cell ) echo '<td>' . esc_html( $cell ) . '</td>'; echo '</tr>'; }
        }
        echo '</tbody></table></div></section>';
    }

    /** @param array<int,string> $items */
    private static function expectation( string $title, array $items ): void {
        echo '<section class="mvm-nr2-card mvm-nr2-checklist"><div class="mvm-nr2-card__head"><div><span>Checklist</span><h2>' . esc_html( $title ) . '</h2></div></div><ul>';
        foreach ( $items as $item ) echo '<li>' . esc_html( $item ) . '</li>';
        echo '</ul></section>';
    }

    private static function workflow_label( string $value ): string {
        $key = sanitize_key( $value );
        return match ( $key ) {
            'draft' => 'Concept', 'pending', 'review' => 'Ter beoordeling', 'future', 'scheduled' => 'Ingepland', 'publish', 'published' => 'Gepubliceerd', 'new' => 'Nieuw', 'triage' => 'Beoordelen', 'verified' => 'Geverifieerd', 'unverified' => 'Nog niet geverifieerd', 'active' => 'Actief', 'paused' => 'Gepauzeerd', 'ready' => 'Gereed', default => '' !== $key ? ucfirst( str_replace( '_', ' ', $key ) ) : '—',
        };
    }

    private static function date_label( string $utc ): string {
        if ( '' === $utc ) return '—';
        $timestamp = strtotime( $utc . ' UTC' );
        return false === $timestamp ? '—' : wp_date( 'j M Y · H:i', $timestamp );
    }

    private function __construct() {}
}
