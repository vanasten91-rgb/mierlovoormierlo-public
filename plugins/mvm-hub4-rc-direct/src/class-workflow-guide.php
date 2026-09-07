<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Workflow_Guide {
    public static function current(): array {
        $user     = wp_get_current_user();
        $role     = self::primary_role( $user );
        $profiles = self::profiles();
        $profile  = $profiles[ $role ] ?? $profiles['staff'];

        return array(
            'role'        => $role,
            'roleLabel'   => sanitize_text_field( (string) $profile['roleLabel'] ),
            'title'       => sanitize_text_field( (string) $profile['title'] ),
            'description' => sanitize_text_field( (string) $profile['description'] ),
            'navOrder'    => array_values( array_map( 'sanitize_key', (array) $profile['navOrder'] ) ),
            'steps'       => array_values( array_map( 'sanitize_text_field', (array) $profile['steps'] ) ),
            'tips'        => array_values( array_map( 'sanitize_text_field', (array) $profile['tips'] ) ),
        );
    }

    public static function navigation_order(): array {
        return (array) self::current()['navOrder'];
    }

    private static function primary_role( WP_User $user ): string {
        if ( ! $user->exists() ) {
            return 'staff';
        }

        $priority = array(
            'mvm_sysop',
            'mvm_teamleider',
            'mvm_editor',
            'mvm_redacteur',
            'mvm_journalist',
            'mvm_fotograaf',
            'mvm_moderator',
            'mvm_vertaler',
            'administrator',
        );

        foreach ( $priority as $role ) {
            if ( in_array( $role, (array) $user->roles, true ) ) {
                return $role;
            }
        }

        return 'staff';
    }

    private static function profiles(): array {
        $default_order = array( 'today', 'news', 'sources', 'agenda', 'team', 'more' );

        return array(
            'mvm_sysop' => array(
                'roleLabel'   => 'SysOp',
                'title'       => 'Veiligheid en continuïteit eerst',
                'description' => 'Gebruik de Hub als controlekamer: begin bij afwijkingen en open specialistisch beheer alleen wanneer dat nodig is.',
                'navOrder'    => array( 'today', 'more', 'team', 'sources', 'news', 'agenda' ),
                'steps'       => array(
                    'Open Vandaag en controleer achterstallige bronnen, sessiestatus en opvallende gebeurtenissen.',
                    'Gebruik Meer en Werkplaats voor technisch beheer zodat capabilitycontrole en logging behouden blijven.',
                    'Controleer na een beheerwijziging altijd de relevante publieke route en de audittrail.',
                    'Leg bijzonderheden voor de redactie vast via de normale teamworkflow in plaats van technische noodoplossingen.',
                ),
                'tips'        => array(
                    'Verander één technisch onderdeel tegelijk; zo blijft een regressie traceerbaar.',
                    'Gebruik het auditlog voor metadata en controle, nooit als opslagplaats voor gevoelige inhoud.',
                    'Laat inhoudelijke publicatiebeslissingen bij editor of teamleider tenzij veiligheid ingrijpen noodzakelijk maakt.',
                ),
            ),
            'administrator' => array(
                'roleLabel'   => 'Beheerder',
                'title'       => 'Beheer zonder de redactie te verstoren',
                'description' => 'Werk vanuit uitzonderingen en gebruik de beveiligde Werkplaats voor beheeracties.',
                'navOrder'    => array( 'today', 'more', 'team', 'sources', 'news', 'agenda' ),
                'steps'       => array(
                    'Controleer Vandaag op technische en redactionele aandachtspunten.',
                    'Open beheertools via Werkplaats zodat rechten en auditlogging actief blijven.',
                    'Voer wijzigingen gecontroleerd uit en controleer direct het resultaat.',
                    'Gebruik Team, Nieuws of Bronnen alleen wanneer de beheeractie daar functionele gevolgen heeft.',
                ),
                'tips'        => array(
                    'Vermijd grote bulkacties als dezelfde taak veilig per onderdeel kan worden uitgevoerd.',
                    'Controleer na plugin- of rolwijzigingen opnieuw de Hub-toegang van betrokken stafrollen.',
                    'Gebruik beheerdersrechten alleen voor taken die niet met een lagere rol kunnen worden uitgevoerd.',
                ),
            ),
            'mvm_teamleider' => array(
                'roleLabel'   => 'Teamleider',
                'title'       => 'Prioriteren, verdelen en bewaken',
                'description' => 'Begin bij wat vandaag aandacht vraagt, verdeel werk en bewaak daarna review, planning en publicatie.',
                'navOrder'    => array( 'today', 'news', 'team', 'agenda', 'sources', 'more' ),
                'steps'       => array(
                    'Bekijk Vandaag en Nieuws voor openstaand werk, review en deadlines.',
                    'Controleer Team om werk logisch te verdelen en knelpunten vroeg te zien.',
                    'Gebruik Agenda om dekking en publicatiemomenten af te stemmen.',
                    'Controleer Bronnen wanneer een verhaal verificatie nodig heeft en rond daarna de publicatiecyclus af.',
                ),
                'tips'        => array(
                    'Prioriteer eerst; ga pas zelf redigeren als verdeling en deadlines duidelijk zijn.',
                    'Laat één persoon eigenaar zijn van een item zodat verantwoordelijkheid zichtbaar blijft.',
                    'Gebruik auditfilters bij incidenten of onduidelijkheid, niet als dagelijkse micromanagementtool.',
                ),
            ),
            'mvm_editor' => array(
                'roleLabel'   => 'Editor',
                'title'       => 'Van review naar sterke publicatie',
                'description' => 'Werk in een vaste volgorde: beoordelen, feiten en bron controleren, redigeren, plannen en publiceren.',
                'navOrder'    => array( 'today', 'news', 'sources', 'agenda', 'team', 'more' ),
                'steps'       => array(
                    'Begin bij Nieuws en pak eerst items die op beoordeling wachten.',
                    'Controleer titel, intro, bron, categorie, afbeelding en feitelijke details.',
                    'Gebruik Bronnen wanneer extra verificatie nodig is en Agenda voor timing of evenementcontext.',
                    'Zet het item daarna klaar, plan of publiceer en controleer de publieke weergave.',
                ),
                'tips'        => array(
                    'Verander de stem van de auteur alleen wanneer duidelijkheid of correctheid dat vereist.',
                    'Controleer vóór publicatie altijd of titel en afbeelding ook goed werken wanneer het bericht op Facebook wordt gedeeld.',
                    'Stuur een item terug voor aanpassing in plaats van onduidelijk werk stilletjes zelf over te nemen.',
                ),
            ),
            'mvm_redacteur' => array(
                'roleLabel'   => 'Redacteur',
                'title'       => 'Van signaal naar publiceerbaar nieuws',
                'description' => 'Nieuws en bronnen staan vooraan; planning en teaminformatie blijven direct beschikbaar voor een snelle redactieflow.',
                'navOrder'    => array( 'today', 'news', 'sources', 'agenda', 'team', 'more' ),
                'steps'       => array(
                    'Kies op Vandaag of Nieuws het item dat nu de hoogste prioriteit heeft.',
                    'Controleer de lokale bron, feiten, namen, datum en actualiteit.',
                    'Werk het bericht helder uit en voeg de juiste categorie, afbeelding en context toe.',
                    'Lever het item aan voor review of zet het volgens je rechten klaar voor planning.',
                ),
                'tips'        => array(
                    'Begin met de kern: wat is er gebeurd, voor wie is het belangrijk en wanneer?',
                    'Gebruik korte alinea’s en een duidelijke eerste alinea; dat helpt ook bij mobiel lezen en Facebook-verkeer.',
                    'Zet twijfelpunten expliciet door naar editor/teamleider in plaats van aannames te publiceren.',
                ),
            ),
            'mvm_journalist' => array(
                'roleLabel'   => 'Journalist',
                'title'       => 'Bron naar verhaal naar review',
                'description' => 'Start bij betrouwbare lokale bronnen en bouw van daaruit een controleerbaar nieuwsverhaal.',
                'navOrder'    => array( 'today', 'sources', 'news', 'agenda', 'team', 'more' ),
                'steps'       => array(
                    'Open Bronnen en controleer de relevante bron of gebruik een concrete redactieopdracht.',
                    'Bepaal de nieuwswaarde en noteer de belangrijkste feiten voordat je gaat schrijven.',
                    'Werk het bericht als concept uit en gebruik Agenda voor aanvullende lokale context.',
                    'Lever het concept aan voor review en meld ontbrekende informatie of gewenst beeld duidelijk.',
                ),
                'tips'        => array(
                    'Controleer namen, data en cijfers altijd een tweede keer.',
                    'Schrijf eerst feitelijk; voeg achtergrond pas toe als die het lokale verhaal echt verduidelijkt.',
                    'Een sterke lokale invalshoek maakt delen door inwoners waarschijnlijker dan een algemene formulering.',
                ),
            ),
            'mvm_fotograaf' => array(
                'roleLabel'   => 'Fotograaf',
                'title'       => 'Fotomoment naar bruikbaar redactiebeeld',
                'description' => 'Agenda en media staan vooraan zodat beeld direct aan het juiste nieuwsitem en moment wordt gekoppeld.',
                'navOrder'    => array( 'today', 'agenda', 'news', 'team', 'sources', 'more' ),
                'steps'       => array(
                    'Bekijk Vandaag en Agenda om te zien waar en wanneer beeld nodig is.',
                    'Maak een gerichte selectie en upload alleen bruikbare beelden met correcte bestands- en altinformatie.',
                    'Controleer Nieuws om het beeld aan het juiste verhaal of onderwerp te koppelen.',
                    'Meld aan de verantwoordelijke redacteur dat het beeld gereed is en of er bijzonderheden zijn.',
                ),
                'tips'        => array(
                    'Maak naast overzicht ook één duidelijk beeld dat als Facebook- of artikelbeeld kan werken.',
                    'Vermijd onnodige duplicaten in de mediabibliotheek; selectie vóór upload houdt de Hub snel.',
                    'Let bij herkenbare personen op context en redactionele noodzaak voordat beeld wordt gepubliceerd.',
                ),
            ),
            'mvm_moderator' => array(
                'roleLabel'   => 'Moderator',
                'title'       => 'Context beoordelen en rustig modereren',
                'description' => 'Community en forum staan centraal; nieuws- en teamcontext helpen om consequent en proportioneel te handelen.',
                'navOrder'    => array( 'today', 'more', 'team', 'news', 'sources', 'agenda' ),
                'steps'       => array(
                    'Controleer eerst de context, ernst en eventuele voorgeschiedenis van een melding.',
                    'Gebruik de bestaande forum- of communitymoderatie en kies de minst ingrijpende passende maatregel.',
                    'Controleer Nieuws wanneer een discussie direct samenhangt met een gepubliceerd MvM-bericht.',
                    'Escaleren naar teamleider of SysOp wanneer veiligheid, privacy of herhaald misbruik speelt.',
                ),
                'tips'        => array(
                    'Modereer gedrag en inhoud volgens dezelfde norm, ongeacht de persoon.',
                    'Verwijder of blokkeer niet meer dan nodig; behoud context wanneer dat veilig kan.',
                    'Zet gevoelige persoonsgegevens nooit in het auditlog of openbare moderatie-notities.',
                ),
            ),
            'mvm_vertaler' => array(
                'roleLabel'   => 'Vertaler',
                'title'       => 'Betekenis behouden, tekst toegankelijk maken',
                'description' => 'Werk vanuit het definitieve of bijna definitieve bronartikel en behoud namen, feiten, links en lokale context.',
                'navOrder'    => array( 'today', 'news', 'sources', 'team', 'agenda', 'more' ),
                'steps'       => array(
                    'Controleer in Nieuws welk item vertaald of aangepast moet worden en of de brontekst stabiel genoeg is.',
                    'Gebruik Bronnen om namen, organisaties en lokale termen te verifiëren.',
                    'Vertaal betekenis en toon; verander geen feiten of redactionele conclusie.',
                    'Lever de tekst terug voor controle wanneer termen, namen of context onzeker zijn.',
                ),
                'tips'        => array(
                    'Laat eigennamen, officiële organisatienamen en bronlinks intact tenzij een officiële vertaling bestaat.',
                    'Schrijf natuurlijk in de doeltaal en vermijd woord-voor-woordvertaling.',
                    'Markeer twijfelgevallen voor de editor in plaats van zelf een inhoudelijke keuze te verzinnen.',
                ),
            ),
            'staff' => array(
                'roleLabel'   => 'Staf',
                'title'       => 'Begin bij Vandaag en werk stap voor stap',
                'description' => 'De Hub toont alleen onderdelen waarvoor je rechten hebt. Gebruik Vandaag als startpunt en ga daarna naar het onderwerp dat je taak ondersteunt.',
                'navOrder'    => $default_order,
                'steps'       => array(
                    'Open Vandaag en bepaal wat voor jouw taak aandacht vraagt.',
                    'Gebruik de eerstvolgende beschikbare module voor je inhoud, bron, planning of teamafstemming.',
                    'Rond je taak af en controleer of de volgende verantwoordelijke weet dat het werk klaarstaat.',
                ),
                'tips'        => array(
                    'Werk vanuit de Hub in plaats van losse beheerlinks; zo blijven rechten en logging consistent.',
                    'Bewaar geen gevoelige newsroominformatie in browsernotities of URL’s.',
                    'Vraag teamleider of SysOp wanneer een taak buiten je normale rechten of verantwoordelijkheid valt.',
                ),
            ),
        );
    }
}
