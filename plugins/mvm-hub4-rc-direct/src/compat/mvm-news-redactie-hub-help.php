<?php
/**
 * MvM Nieuws/Redactie Hub — contextual help, role guidance and FAQ.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mvm_hubs_v3_help_editorial_profile(): array {
    $roles = function_exists( 'mvm_hubs_v3_user_roles' ) ? mvm_hubs_v3_user_roles() : array();
    $profiles = array(
        'mvm_teamleider' => array(
            'label' => 'Teamleider',
            'purpose' => 'Houd overzicht, bewaak de planning, beoordeel waar nodig redactioneel werk, Nieuwsradar-signalen en organisatievacatures en help het team om werk op de juiste plek te krijgen.',
            'tips' => array(
                'Gebruik Planning voor het teamoverzicht; maak geen tweede planning buiten MvM.',
                'Geef duidelijk aan wie iets oppakt en wat nog gecontroleerd moet worden.',
                'Een teamleider heeft niet automatisch moderatierecht: laat moderatie bij de Moderator tenzij extra toegang bewust is gegeven.',
            ),
        ),
        'mvm_editor' => array(
            'label' => 'Editor',
            'purpose' => 'Beoordeel aangeleverde artikelen, Nieuwsradar-signalen en organisatievacatures op juistheid, duidelijkheid, relevante rechten en publicatiewaarde.',
            'tips' => array(
                'Controleer feiten, namen, data, bronvermelding, privacy en beeldrechten voordat iets verdergaat.',
                'Vraag een aanpassing als iets niet duidelijk of controleerbaar genoeg is.',
                'Beoordelen is iets anders dan modereren; gebruik alleen de onderdelen die bij jouw rol horen.',
            ),
        ),
        'mvm_moderator' => array(
            'label' => 'Moderator',
            'purpose' => 'Help de community veilig en respectvol te houden door meldingen zorgvuldig en proportioneel te behandelen.',
            'tips' => array(
                'Bekijk de context voordat je een maatregel neemt en behandel vergelijkbare situaties zoveel mogelijk gelijk.',
                'Gebruik het reglement als vaste basis bij twijfel over gedrag of inhoud.',
                'Leg gevoelige of onduidelijke situaties voor aan de Teamleider of beheerder in plaats van te gokken.',
            ),
        ),
        'mvm_journalist' => array(
            'label' => 'Journalist',
            'purpose' => 'Verzamel lokale informatie, controleer feiten en lever zorgvuldige nieuwsverhalen aan.',
            'tips' => array(
                'Gebruik Nieuwsradar om nieuwe lokale signalen te vinden en controleer namen, data, locaties en kernfeiten daarna bij een betrouwbare bron.',
                'Noteer relevante broninformatie en meld twijfel of ontbrekend wederhoor bij de inzending.',
                'Plaats geen onnodige persoonsgegevens en houd rekening met privacy, auteursrecht en portretrecht.',
            ),
        ),
        'mvm_redacteur' => array(
            'label' => 'Redacteur',
            'purpose' => 'Schrijf, verbeter en lever lokaal nieuws aan via de vaste redactionele werkstroom.',
            'tips' => array(
                'Gebruik Nieuwsradar voor nieuwe signalen en werk daarna vanuit Mijn werk zodat concept, beoordeling en publicatie bij elkaar blijven.',
                'Schrijf feitelijk, begrijpelijk en lokaal relevant; maak duidelijk wat feit en wat mening is.',
                'Meld onzekerheden, correcties en ontbrekende informatie liever direct dan ze zelf in te vullen.',
            ),
        ),
        'mvm_fotograaf' => array(
            'label' => 'Fotograaf',
            'purpose' => 'Lever bruikbaar en rechtmatig beeld aan dat de lokale berichtgeving versterkt.',
            'tips' => array(
                'Controleer of je de foto mag aanleveren en meld relevante beperkingen of afspraken.',
                'Vermijd onnodig gevoelige situaties en houd rekening met privacy en portretrecht.',
                'Geef bij beeld kort door wat, waar en wanneer is gefotografeerd zodat de redactie het juist kan plaatsen.',
            ),
        ),
        'mvm_vertaler' => array(
            'label' => 'Vertaler',
            'purpose' => 'Maak aangeleverde informatie begrijpelijk in de gevraagde taal zonder betekenis of feiten te veranderen.',
            'tips' => array(
                'Behoud namen, data, citaten en feitelijke betekenis zo nauwkeurig mogelijk.',
                'Markeer twijfel over vaktermen, namen of context in plaats van te raden.',
                'Behandel niet-gepubliceerde teksten als interne informatie.',
            ),
        ),
    );

    foreach ( $profiles as $role => $profile ) {
        if ( in_array( $role, $roles, true ) ) {
            return $profile;
        }
    }

    return array(
        'label' => 'Redactieteam',
        'purpose' => 'Gebruik alleen de redactionele onderdelen waarvoor jouw huidige account rechten heeft.',
        'tips' => array(
            'Werk vanuit de hub zodat rechten, status en overdracht duidelijk blijven.',
            'Behandel interne informatie en persoonsgegevens zorgvuldig.',
            'Meld onduidelijke rechten of ontbrekende functies bij MvM in plaats van een omweg te gebruiken.',
        ),
    );
}

function mvm_hubs_v3_help_context( string $hub ): array {
    $contact_url = home_url( '/contact/' );
    $rules_url   = home_url( '/algemene-voorwaarden/' );
    $privacy_url = home_url( '/privacy/' );

    $base = array(
        'label' => 'Mierlonaar',
        'purpose' => 'Gebruik Mierlo voor Mierlo om lokaal nieuws te volgen, mee te praten, vragen te stellen en informatie met het dorp te delen.',
        'steps' => array(
            'Kies in Mijn Mierlo wat je wilt doen: lezen, berichten bekijken, meepraten, iets opslaan of het forum openen.',
            'Gebruik de knop bij het onderdeel zelf; je hoeft geen technische route of plugin te kennen.',
            'Controleer wat je deelt voordat je het verstuurt en houd rekening met privacy en rechten van anderen.',
            'Zie je een fout, probleem of verbetermogelijkheid? Meld het via Contact zodat MvM het kan onderzoeken.',
        ),
        'tips' => array(
            'Gebruik Opgeslagen om nieuws en evenementen later terug te vinden.',
            'Gebruik privéberichten voor persoonlijke communicatie en zet geen privécontactgegevens onnodig openbaar.',
            'Een vereniging, club, organisator, ondernemer, bedrijf of winkelier kan vanuit Mijn Mierlo een passend organisatieaccount aanvragen. Extra rechten worden pas na controle door MvM actief.',
        ),
        'expectations' => array(
            'Ga respectvol met andere Mierlonaren om, ook wanneer meningen verschillen.',
            'Plaats alleen materiaal dat je rechtmatig mag delen en probeer feitelijke informatie correct en actueel te houden.',
            'Meld misbruik, onveilige inhoud, feitelijke fouten of technische problemen wanneer je die tegenkomt.',
        ),
        'faq' => array(
            array( 'Hoe krijg ik hulp?', 'Onderaan iedere hub staat deze FAQ. Je kunt daarnaast de Contactpagina gebruiken of mailen naar contact@mierlovoormierlo.nl.' ),
            array( 'Waar staan de regels?', 'De actuele afspraken staan op Algemene voorwaarden en gebruiksregels. Die regels gelden voor nieuws, community, forum, accounts, evenementen en andere interactieve onderdelen.' ),
            array( 'Kan ik een organisatieaccount krijgen?', 'Ja. Open Mijn Mierlo en kies Organisatieaccount. Je kiest het passende type en vult de organisatiegegevens in. De aanvraag geeft nog geen extra rechten; MvM controleert eerst of het accounttype en de vertegenwoordiging kloppen.' ),
        ),
    );

    if ( 'business' === $hub ) {
        $profile = function_exists( 'mvm_e2_organization_profile' ) ? mvm_e2_organization_profile() : array();
        $tasks   = function_exists( 'mvm_hubs_v3_business_tasks' ) ? mvm_hubs_v3_business_tasks() : array();
        $label   = ! empty( $profile['label'] ) ? (string) $profile['label'] : 'Organisatieaccount';
        $actions = array();
        if ( ! empty( $tasks['pages'] ) ) {
            $actions[] = 'je eigen MvM-pagina maken en beheren';
        }
        if ( ! empty( $tasks['promotion'] ) ) {
            $actions[] = 'aanbiedingen en promoties indienen en bijwerken';
        }
        if ( ! empty( $tasks['vacancies'] ) ) {
            $actions[] = 'vacatures indienen en beheren';
        }
        if ( ! empty( $tasks['events'] ) ) {
            $actions[] = 'evenementen aanmelden en beheren';
        }
        $base['label'] = $label;
        $base['purpose'] = 'Beheer vanuit één plek de onderdelen die bij jouw ' . strtolower( $label ) . '-account horen: ' . ( $actions ? implode( ', ', $actions ) : 'de beschikbare organisatietaken' ) . '.';
        $base['steps'] = array(
            'Open de Organisatiehub en kies direct de taak die je wilt uitvoeren. Je ziet alleen functies die voor jouw accounttype zijn toegestaan.',
            'Werk alleen de onderdelen bij die jouw Organisatiehub toont; functies die niet bij jouw accounttype horen worden niet aangeboden.',
            'Gebruik voor iedere taak de eigen knop in de Organisatiehub. De invoer en vervolgstappen passen bij de functies die jouw account werkelijk heeft.',
            'Waar beoordeling nodig is, krijgt de inzending eerst een controlestatus. MvM beoordeelt de inhoud voordat die openbaar wordt of opnieuw zichtbaar wordt na een inhoudelijke wijziging.',
            'Na publicatie blijf je zelf verantwoordelijk voor juistheid, looptijd, beschikbaarheid, prijzen, data en wijzigingen van je eigen organisatie-inhoud.',
        );
        $base['tips'] = array(
            'Houd één duidelijke MvM-pagina aan voor je organisatie en werk die bij in plaats van meerdere losse pagina’s te maken.',
            'Gebruik alleen de taaktypes die jouw Organisatiehub toont en kies per taak de daarvoor bedoelde invoerroute.',
            'Gebruik geen privé-adressen of andere onnodige persoonsgegevens in openbare promo’s, vacatures of pagina-inhoud.',
        );
        if ( empty( $tasks['pages'] ) && ! empty( $base['tips'] ) ) {
            array_shift( $base['tips'] );
            array_unshift( $base['tips'], 'Houd de evenementgegevens die je via dit account beheert actueel en gebruik geen andere organisatiefuncties als die niet in jouw hub staan.' );
        }
        $base['expectations'] = array(
            'Houd alles wat je via dit account beheert feitelijk juist en actueel.',
            'Gebruik alleen beeld, logo’s en teksten waarvoor je voldoende rechten hebt.',
            'Reageer op een verzoek om correctie of aanvullende informatie wanneer MvM iets niet veilig of duidelijk genoeg kan beoordelen.',
            'Meld direct wanneer een workflow niet klopt, een knop niet werkt of jouw account onjuiste rechten lijkt te hebben.',
        );
        $base['faq'] = array(
            array( 'Wat kan ik zelf beheren?', $actions ? 'Met dit account kun je ' . implode( ', ', $actions ) . '. Je ziet geen functies waarvoor jouw accounttype geen recht heeft.' : 'De hub toont automatisch de functies die bij jouw accounttype horen.' ),
            array( 'Wanneer wordt mijn inzending zichtbaar?', 'Een inzending die controle vereist gaat eerst naar MvM. Na controle kan die worden gepubliceerd. Een inhoudelijke wijziging aan eerder zichtbare inhoud kan opnieuw ter beoordeling gaan.' ),
            array( 'Waarom zie ik sommige onderdelen niet?', 'De Organisatiehub toont alleen functies die bij jouw goedgekeurde accounttype horen. Zo krijgt ieder account precies de rechten en taken die voor dat type zijn toegestaan.' ),
            array( 'Kan ik mijn eigen inhoud later aanpassen?', 'Ja, waar de betreffende functie dit ondersteunt. Je beheert alleen je eigen organisatie-inhoud. Een wijziging die opnieuw publieke gevolgen heeft kan eerst teruggaan naar controle.' ),
        );
    } elseif ( 'editorial' === $hub ) {
        $profile = mvm_hubs_v3_help_editorial_profile();
        $base['label'] = $profile['label'];
        $base['purpose'] = $profile['purpose'];
        $base['steps'] = array(
            'Start altijd in de Redactiehub. Daar zie je alleen de taken die bij jouw huidige rol horen.',
            'Werk via Mijn werk, Nieuwsradar, Bronnen, MvM Mail, Te beoordelen, Planning of Moderatie afhankelijk van de rechten die je hebt; gebruik geen omwegen via wp-admin voor normale redactietaken.',
            'Controleer inhoud, bronnen, privacy, auteursrecht en relevante context voordat je iets doorgeeft, beoordeelt of publiceert.',
            'Is iets onduidelijk of gevoelig? Zet het niet op eigen gezag door, maar vraag een Editor, Teamleider of beheerder om mee te kijken.',
            'Na publicatie blijft correctie mogelijk: meld feitelijke fouten direct zodat ze zorgvuldig kunnen worden beoordeeld en hersteld.',
        );
        $base['tips'] = $profile['tips'];
        $base['tips'][] = 'Open MvM Mail altijd vanuit de Redactiehub. Behandel mailboxinhoud als vertrouwelijke stafcommunicatie en deel geen toegang, inloggegevens of onnodige persoonsgegevens buiten de juiste werkstroom.';
        $base['expectations'] = array(
            'Werk zorgvuldig, controleerbaar en onafhankelijk binnen de taak van jouw rol.',
            'Behandel niet-gepubliceerde informatie, interne communicatie en persoonsgegevens vertrouwelijk.',
            'Gebruik alleen rechten die voor jouw werkzaamheden nodig zijn en deel geen stafaccount of accounttoegang met anderen.',
            'Meld fouten, belangenconflicten, onduidelijke bronnen, beveiligingsproblemen en ontbrekende functionaliteit zo snel mogelijk.',
        );
        $base['faq'] = array(
            array( 'Wat wordt van staf verwacht?', 'Zorgvuldigheid, respect voor privacy en rechten, duidelijke broncontrole, correcte overdracht binnen de workflow en tijdig melden van fouten of twijfel. Iedere rol blijft bij zijn eigen hoofdtaak.' ),
            array( 'Wie mag beoordelen, modereren of plannen?', 'Die functies zijn bewust gescheiden. Editor en Teamleider kunnen beoordelen; de Moderator behandelt moderatie; de Teamleider beheert planning. Extra rechten worden niet automatisch verondersteld.' ),
            array( 'Wat doe ik als ik twijfel aan een publicatie?', 'Niet gokken. Laat het item staan in de workflow, noteer wat ontbreekt of onzeker is en vraag de juiste collega om controle.' ),
            array( 'Wat als ik na publicatie een fout ontdek?', 'Meld de fout direct. Feitelijke correcties worden zorgvuldig beoordeeld; transparantie en juistheid gaan voor het verbergen van een vergissing.' ),
            array( 'Waar open ik MvM Mail?', 'Open MvM Mail vanuit de Redactiehub. In deze overgangsfase opent daarna de bestaande beveiligde mailservice. Die kan zijn eigen sessiecontrole uitvoeren; mailboxdata wordt niet naar een tweede systeem gekopieerd.' ),
        );
        if ( function_exists( 'mvm_hubs_v3_can_manage_vacancies' ) && mvm_hubs_v3_can_manage_vacancies() ) {
            $base['tips'][] = 'Gebruik Vacatures beoordelen voor organisatievacatures. Publiceer alleen wanneer inhoud, contactroute en organisatiegegevens voldoende duidelijk zijn; zet de vacature anders terug naar concept.';
            $base['faq'][] = array( 'Hoe beoordeel ik een vacature?', 'Open Vacatures beoordelen. Controleer de functie, organisatie, contactgegevens en vacaturetekst. Kies Publiceren als de inzending akkoord is, Ter beoordeling houden als je nog niet klaar bent of Terug naar concept wanneer aanpassing nodig is.' );
        }
    } elseif ( 'admin' === $hub ) {
        $roles = function_exists( 'mvm_hubs_v3_user_roles' ) ? mvm_hubs_v3_user_roles() : array();
        $base['label'] = in_array( 'mvm_sysop', $roles, true ) ? 'SysOp' : 'Administrator';
        $base['purpose'] = 'Houd MvM veilig, beschikbaar en controleerbaar. Technisch beheer ondersteunt de site en de andere rollen, maar vervangt hun inhoudelijke verantwoordelijkheden niet.';
        $base['steps'] = array(
            'Controleer eerst Status en Rechten voordat je een technische wijziging uitvoert.',
            'Gebruik alleen de specifieke beheerfunctie voor het probleem dat je wilt oplossen; vermijd brede wijzigingen wanneer een kleine ingreep volstaat.',
            'Zorg vóór structurele wijzigingen voor een recent herstelpunt en controleer na de wijziging alleen de betrokken onderdelen.',
            'Leg gevoelige of ingrijpende acties vast via de beschikbare audit- en onderhoudsstromen.',
            'Als een fout niet veilig binnen de hub kan worden opgelost, stop dan de wijziging en onderzoek eerst de oorzaak en terugvalmogelijkheid.',
        );
        $base['tips'] = array(
            'Behandel de Technische hub als hoog-privilege omgeving: dagelijks werk hoort waar mogelijk in de gewone werkhubs.',
            'Bewaar geen wachtwoorden, API-sleutels of andere secrets in notities, contentvelden, tickets of openbare pagina’s.',
            'Controleer na rolwijzigingen de rechtenmatrix; verborgen knoppen zijn nooit een vervanging voor server-side autorisatie.',
            'Gebruik Smart Links onder Integraties om encyclopediekoppelingen te testen en beheren; een instellingenwrite wordt vooraf geaudit en faalt gesloten wanneer die audit niet kan worden vastgelegd.',
        );
        $base['expectations'] = array(
            'Gebruik verhoogde rechten alleen wanneer dat technisch noodzakelijk is en kies de minst ingrijpende oplossing.',
            'Bescherm persoonsgegevens en interne informatie en voorkom dat logs of statusschermen onnodig gevoelige gegevens tonen.',
            'Werk met herstelpunten bij structurele wijzigingen en meld incidenten, fouten of twijfel direct.',
            'Ondersteun andere rollen zonder redactionele, moderatie- of organisatiebesluiten over te nemen tenzij die taak expliciet aan jou is toegewezen.',
        );
        $base['faq'] = array(
            array( 'Wanneer gebruik ik de Technische hub?', 'Alleen voor veiligheid, integraties, gecontroleerd onderhoud, site-inrichting en herstel. Redactioneel of organisatorisch dagelijks werk hoort in de betreffende werkhub.' ),
            array( 'Waarom kan niet alles vanuit één beheerknop?', 'Omdat MvM gevoelige acties bewust scheidt. Kleine, allowlisted acties zijn beter te controleren, auditen en terugdraaien dan brede onbeperkte beheeracties.' ),
            array( 'Wat doe ik voor een grote wijziging?', 'Controleer de impact, zorg voor een volledig herstelpunt, wijzig zo klein mogelijk, test de relevante routes en leg vast wat er is aangepast.' ),
            array( 'Wat kan ik met Smart Links?', 'Onder Integraties bekijk je de actuele linkindex, handmatige aliassen, uitzonderingslijsten en diagnostiek. Je kunt tekst testen zonder te publiceren; opslaan van instellingen vereist server-side adminrecht en een geslaagde private auditregistratie.' ),
        );
    }

    $base['contact_url'] = $contact_url;
    $base['rules_url']   = $rules_url;
    $base['privacy_url'] = $privacy_url;
    return $base;
}

function mvm_hubs_v3_help_faq_item( string $question, string $answer ): string {
    return '<details class="mvmh3-faq__item"><summary>' . esc_html( $question ) . '</summary><div><p>' . esc_html( $answer ) . '</p></div></details>';
}

function mvm_hubs_v3_help_html( string $hub ): string {
    $context = mvm_hubs_v3_help_context( $hub );
    $steps = (array) ( $context['steps'] ?? array() );
    $tips = (array) ( $context['tips'] ?? array() );
    $expectations = (array) ( $context['expectations'] ?? array() );
    $faq = (array) ( $context['faq'] ?? array() );
    $contact_url = (string) ( $context['contact_url'] ?? home_url( '/contact/' ) );
    $rules_url = (string) ( $context['rules_url'] ?? home_url( '/algemene-voorwaarden/' ) );
    $privacy_url = (string) ( $context['privacy_url'] ?? home_url( '/privacy/' ) );

    $html  = '<section id="mvm-hulp" class="mvmh3-section mvmh3-help" aria-labelledby="mvm-hulp-title">';
    $html .= '<div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Hulp & uitleg · ' . esc_html( (string) $context['label'] ) . '</span><h2 id="mvm-hulp-title">Zo werkt jouw MvM</h2></div><p>De hub is zo ingericht dat je meestal zonder handleiding verder kunt. Hieronder staat de uitleg voor als je toch wilt weten wat er gebeurt.</p></div>';
    $html .= '<div class="mvmh3-help__purpose"><strong>Jouw rol</strong><span>' . esc_html( (string) $context['purpose'] ) . '</span></div>';

    if ( $steps ) {
        $html .= '<div class="mvmh3-help__block"><h3>Wat doe ik, en wat gebeurt daarna?</h3><ol class="mvmh3-help__steps">';
        foreach ( $steps as $step ) {
            $html .= '<li><span>' . esc_html( (string) $step ) . '</span></li>';
        }
        $html .= '</ol></div>';
    }

    if ( $tips || $expectations ) {
        $html .= '<div class="mvmh3-help__columns">';
        if ( $tips ) {
            $html .= '<div class="mvmh3-help__block"><h3>Tips</h3><ul>';
            foreach ( $tips as $tip ) {
                $html .= '<li>' . esc_html( (string) $tip ) . '</li>';
            }
            $html .= '</ul></div>';
        }
        if ( $expectations ) {
            $html .= '<div class="mvmh3-help__block"><h3>Wat MvM van jou verwacht</h3><ul>';
            foreach ( $expectations as $expectation ) {
                $html .= '<li>' . esc_html( (string) $expectation ) . '</li>';
            }
            $html .= '</ul></div>';
        }
        $html .= '</div>';
    }

    $html .= '<div class="mvmh3-security-note"><div><strong>Bescherm je account</strong><p>Ga altijd voorzichtig om met je inloggegevens. Deel je wachtwoord of accounttoegang nooit en meld vermoedelijk misbruik of ongeautoriseerde toegang zo snel mogelijk. Uiteindelijk ben jij zelf verantwoordelijk voor zorgvuldig gebruik van je account en voor activiteiten via jouw account, behalve voor zover misbruik buiten jouw schuld plaatsvindt.</p></div><div><strong>Wat Mierlo voor Mierlo doet</strong><p>Mierlo voor Mierlo zal er alles aan doen wat redelijkerwijs mogelijk is om gegevens van gebruikers en staf veilig te houden, onder meer met afgeschermde rollen, server-side rechten, beperkte toegang en passende technische en organisatorische beveiligingsmaatregelen. Geen online systeem is volledig zonder risico; meld iets verdachts daarom direct.</p></div></div>';

    $html .= '<div class="mvmh3-help__links"><a href="' . esc_url( $rules_url ) . '"><strong>Reglement & gebruiksregels</strong><span>Lees de afspraken die voor alle gebruikers en staf gelden</span></a><a href="' . esc_url( $privacy_url ) . '"><strong>Privacy</strong><span>Lees hoe MvM met persoonsgegevens omgaat</span></a><a href="' . esc_url( $contact_url ) . '"><strong>Hulp of iets melden</strong><span>Vraag hulp, meld een fout of stuur een idee</span></a></div>';

    $html .= '<div class="mvmh3-help__block mvmh3-faq"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">FAQ</span><h3>Veelgestelde vragen</h3></div><p>Je vindt deze FAQ onderaan iedere hub via de link Hulp & FAQ in de zijbalk.</p></div>';
    foreach ( $faq as $item ) {
        if ( is_array( $item ) && isset( $item[0], $item[1] ) ) {
            $html .= mvm_hubs_v3_help_faq_item( (string) $item[0], (string) $item[1] );
        }
    }
    $html .= mvm_hubs_v3_help_faq_item( 'Hoe houd ik mijn account veilig?', 'Gebruik je account alleen zelf, deel geen wachtwoord of accounttoegang, log uit op gedeelde apparaten en meld verdachte toegang direct. Bevoegde staf deelt ook geen interne Hub-toegang met anderen.' );
    $html .= mvm_hubs_v3_help_faq_item( 'Wat als iets niet klopt of niet werkt?', 'Meld het. Geef kort aan op welke pagina je was, wat je wilde doen en wat er gebeurde. MvM wil fouten, onduidelijke rechten, ontbrekende uitleg en technische problemen zo vroeg mogelijk weten.' );
    $html .= mvm_hubs_v3_help_faq_item( 'Mag ik meedenken over verbeteringen?', 'Graag. Mierlo voor Mierlo is er voor Mierlo. Ideeën over duidelijkheid, toegankelijkheid, veiligheid, nieuwe functies en betere workflows zijn welkom, ook als het om een klein detail gaat.' );
    $html .= '</div>';

    $html .= '<div class="mvmh3-help__feedback"><strong>Zie je iets dat beter kan?</strong><span>Meld fouten, onduidelijkheden, verkeerde rechten of ideeën via <a href="' . esc_url( $contact_url ) . '">Contact</a> of mail <a href="mailto:contact@mierlovoormierlo.nl">contact@mierlovoormierlo.nl</a>. Ook als je twijfelt of iets belangrijk genoeg is: melden helpt MvM verbeteren.</span></div>';
    $html .= '</section>';
    return $html;
}

