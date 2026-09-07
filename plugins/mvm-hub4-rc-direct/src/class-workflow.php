<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Workflow {
    public static function current(): array {
        $profile = self::profile_for_user( wp_get_current_user() );
        $profile['quickActions'] = self::resolve_quick_actions( $profile['quickActions'] ?? array() );
        return $profile;
    }

    public static function navigation_order(): array {
        $profile = self::profile_for_user( wp_get_current_user() );
        return array_values( array_map( 'sanitize_key', (array) ( $profile['navOrder'] ?? array() ) ) );
    }

    public static function primary_role( ?WP_User $user = null ): string {
        $user = $user ?: wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
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

    public static function profile_for_user( WP_User $user ): array {
        $role     = self::primary_role( $user );
        $profiles = self::profiles();
        $profile  = $profiles[ $role ] ?? $profiles['staff'];

        return array(
            'role'             => $role,
            'roleLabel'        => sanitize_text_field( (string) $profile['roleLabel'] ),
            'focusTitle'       => sanitize_text_field( (string) $profile['focusTitle'] ),
            'focusDescription' => sanitize_text_field( (string) $profile['focusDescription'] ),
            'navOrder'         => array_values( (array) $profile['navOrder'] ),
            'quickActions'     => array_values( (array) $profile['quickActions'] ),
            'steps'            => array_values( array_map( 'sanitize_text_field', (array) $profile['steps'] ) ),
        );
    }

    private static function resolve_quick_actions( array $actions ): array {
        $navigation = array_column( MvM_Hub4_App::navigation_base(), null, 'id' );
        $tools      = class_exists( 'MvM_Hub4_Tools' ) ? MvM_Hub4_Tools::available_for_current_user() : array();
        $tool_map   = array_column( $tools, null, 'id' );
        $resolved   = array();

        foreach ( $actions as $action ) {
            $kind = sanitize_key( (string) ( $action['kind'] ?? '' ) );
            $id   = sanitize_key( (string) ( $action['id'] ?? '' ) );
            if ( '' === $kind || '' === $id ) {
                continue;
            }

            if ( 'view' === $kind ) {
                if ( ! isset( $navigation[ $id ] ) ) {
                    continue;
                }
                $nav = $navigation[ $id ];
                if ( ! current_user_can( $nav['cap'] ) && ! current_user_can( 'manage_options' ) ) {
                    continue;
                }
                $resolved[] = array(
                    'kind'        => 'view',
                    'id'          => $id,
                    'title'       => sanitize_text_field( (string) ( $action['title'] ?? $nav['label'] ) ),
                    'description' => sanitize_text_field( (string) ( $action['description'] ?? '' ) ),
                );
                continue;
            }

            if ( 'tool' === $kind && isset( $tool_map[ $id ] ) ) {
                $tool = $tool_map[ $id ];
                $resolved[] = array(
                    'kind'        => 'tool',
                    'id'          => $id,
                    'title'       => sanitize_text_field( (string) ( $action['title'] ?? $tool['title'] ) ),
                    'description' => sanitize_text_field( (string) ( $action['description'] ?? $tool['description'] ) ),
                    'target'      => 'new' === ( $tool['target'] ?? '' ) ? 'new' : 'same',
                );
            }
        }

        return array_slice( $resolved, 0, 4 );
    }

    private static function profiles(): array {
        $default_order = array( 'today', 'news', 'sources', 'agenda', 'team', 'more' );

        return array(
            'mvm_sysop' => array(
                'roleLabel'        => 'SysOp',
                'focusTitle'       => 'Continuïteit, veiligheid en overzicht',
                'focusDescription' => 'Begin bij uitzonderingen en risico’s; open specialistisch beheer pas wanneer dat nodig is.',
                'navOrder'         => array( 'today', 'more', 'team', 'sources', 'news', 'agenda' ),
                'quickActions'     => array(
                    array( 'kind' => 'view', 'id' => 'more', 'title' => 'Werkplaats & audit', 'description' => 'Systeemtools en controlelog.' ),
                    array( 'kind' => 'view', 'id' => 'team', 'title' => 'Team & rollen', 'description' => 'Controleer bezetting en verantwoordelijkheden.' ),
                    array( 'kind' => 'view', 'id' => 'sources', 'title' => 'Bronnencontrole', 'description' => 'Achterstallige controles en bronstatus.' ),
                ),
                'steps' => array(
                    'Controleer eerst waarschuwingen, achterstallige bronnen en auditafwijkingen.',
                    'Voer beheeracties via Werkplaats uit zodat rechten en auditcontrole behouden blijven.',
                    'Controleer na technische wijzigingen de newsroom, community en publicatieflow.',
                ),
            ),
            'administrator' => array(
                'roleLabel'        => 'Beheerder',
                'focusTitle'       => 'Beheer met zo weinig mogelijk omwegen',
                'focusDescription' => 'Dezelfde veilige beheerroute als SysOp, met specialistische WordPress-tools alleen waar nodig.',
                'navOrder'         => array( 'today', 'more', 'team', 'sources', 'news', 'agenda' ),
                'quickActions'     => array(
                    array( 'kind' => 'view', 'id' => 'more', 'title' => 'Werkplaats & audit', 'description' => 'Beheer en controle.' ),
                    array( 'kind' => 'view', 'id' => 'team', 'title' => 'Team', 'description' => 'Rollen en bezetting.' ),
                    array( 'kind' => 'view', 'id' => 'news', 'title' => 'Nieuwsstatus', 'description' => 'Redactionele voortgang.' ),
                ),
                'steps' => array(
                    'Begin bij uitzonderingen en openstaand beheer.',
                    'Gebruik Werkplaats in plaats van losse beheer-URL’s.',
                    'Controleer na wijzigingen de audittrail en belangrijkste gebruikersroutes.',
                ),
            ),
            'mvm_teamleider' => array(
                'roleLabel'        => 'Teamleider',
                'focusTitle'       => 'Prioriteren, verdelen en beoordelen',
                'focusDescription' => 'Zie eerst wat aandacht vraagt, verdeel werk en bewaak daarna publicatie en planning.',
                'navOrder'         => array( 'today', 'news', 'team', 'agenda', 'sources', 'more' ),
                'quickActions'     => array(
                    array( 'kind' => 'view', 'id' => 'news', 'title' => 'Review nieuws', 'description' => 'Concepten, beoordeling en planning.' ),
                    array( 'kind' => 'view', 'id' => 'team', 'title' => 'Teamoverzicht', 'description' => 'Wie doet wat en waar ligt capaciteit?' ),
                    array( 'kind' => 'view', 'id' => 'agenda', 'title' => 'Planning', 'description' => 'Evenementen en ingepland nieuws.' ),
                    array( 'kind' => 'view', 'id' => 'sources', 'title' => 'Bronnen', 'description' => 'Controleer wat redactioneel aandacht vraagt.' ),
                ),
                'steps' => array(
                    'Bekijk eerst concepten, te beoordelen werk en deadlines.',
                    'Verdeel of herprioriteer werk voordat je zelf inhoud gaat bewerken.',
                    'Rond de cyclus af met planning, publicatiestatus en eventuele broncontrole.',
                ),
            ),
            'mvm_editor' => array(
                'roleLabel'        => 'Editor',
                'focusTitle'       => 'Van review naar publicatie',
                'focusDescription' => 'Werk in een vaste volgorde: beoordelen, redigeren, plannen en publiceren.',
                'navOrder'         => array( 'today', 'news', 'sources', 'agenda', 'team', 'more' ),
                'quickActions'     => array(
                    array( 'kind' => 'view', 'id' => 'news', 'title' => 'Te beoordelen nieuws', 'description' => 'Open concepten en publicatiestatus.' ),
                    array( 'kind' => 'view', 'id' => 'sources', 'title' => 'Broncheck', 'description' => 'Verifieer bronnen vóór publicatie.' ),
                    array( 'kind' => 'view', 'id' => 'agenda', 'title' => 'Publicatieplanning', 'description' => 'Controleer timing en evenementen.' ),
                ),
                'steps' => array(
                    'Controleer titel, inhoud, bron en status van het concept.',
                    'Redigeer alleen wat nodig is en behoud de auteur/workflowstatus.',
                    'Plan of publiceer en controleer daarna de publieke weergave.',
                ),
            ),
            'mvm_redacteur' => array(
                'roleLabel'        => 'Redacteur',
                'focusTitle'       => 'Snel van signaal naar publiceerbaar verhaal',
                'focusDescription' => 'Nieuws en bronnen staan vooraan; planning en teaminformatie blijven direct bereikbaar.',
                'navOrder'         => array( 'today', 'news', 'sources', 'agenda', 'team', 'more' ),
                'quickActions'     => array(
                    array( 'kind' => 'view', 'id' => 'news', 'title' => 'Nieuws bewerken', 'description' => 'Concepten en recente wijzigingen.' ),
                    array( 'kind' => 'view', 'id' => 'sources', 'title' => 'Bronnen', 'description' => 'Controleer relevante lokale bronnen.' ),
                    array( 'kind' => 'view', 'id' => 'agenda', 'title' => 'Agenda', 'description' => 'Koppel nieuws aan actuele momenten.' ),
                ),
                'steps' => array(
                    'Kies het nieuwsitem of signaal dat nu prioriteit heeft.',
                    'Controleer bron, feiten en actualiteit voordat je redigeert.',
                    'Zet het item klaar voor review/publicatie en controleer de planning.',
                ),
            ),
            'mvm_journalist' => array(
                'roleLabel'        => 'Journalist',
                'focusTitle'       => 'Bron → verhaal → review',
                'focusDescription' => 'Bronnen en nieuws vormen één korte route, met agenda als context voor actualiteit.',
                'navOrder'         => array( 'today', 'sources', 'news', 'agenda', 'team', 'more' ),
                'quickActions'     => array(
                    array( 'kind' => 'view', 'id' => 'sources', 'title' => 'Bronnen bekijken', 'description' => 'Vind en controleer de juiste lokale bron.' ),
                    array( 'kind' => 'view', 'id' => 'news', 'title' => 'Mijn nieuwsflow', 'description' => 'Werk verder aan concepten.' ),
                    array( 'kind' => 'view', 'id' => 'agenda', 'title' => 'Wat komt eraan?', 'description' => 'Gebruik evenementen als redactionele context.' ),
                ),
                'steps' => array(
                    'Begin bij een betrouwbare bron of concrete opdracht.',
                    'Werk het verhaal als concept uit zonder publicatiestatus te forceren.',
                    'Lever het stuk aan voor review en voeg waar nodig context of media toe.',
                ),
            ),
            'mvm_fotograaf' => array(
                'roleLabel'        => 'Fotograaf',
                'focusTitle'       => 'Op pad, beeld verwerken, aanleveren',
                'focusDescription' => 'Agenda en media staan vooraan; nieuws blijft zichtbaar om beelden aan het juiste verhaal te koppelen.',
                'navOrder'         => array( 'today', 'agenda', 'news', 'team', 'sources', 'more' ),
                'quickActions'     => array(
                    array( 'kind' => 'view', 'id' => 'agenda', 'title' => 'Fotomomenten', 'description' => 'Bekijk aankomende evenementen en tijden.' ),
                    array( 'kind' => 'tool', 'id' => 'media', 'title' => 'Media uploaden', 'description' => 'Verwerk en beheer foto’s.' ),
                    array( 'kind' => 'view', 'id' => 'news', 'title' => 'Bijbehorend nieuws', 'description' => 'Koppel beeld aan actuele verhalen.' ),
                    array( 'kind' => 'view', 'id' => 'team', 'title' => 'Team', 'description' => 'Zie wie redactioneel betrokken is.' ),
                ),
                'steps' => array(
                    'Controleer eerst waar en wanneer beeld nodig is.',
                    'Upload alleen bruikbaar geselecteerd materiaal met passende metadata.',
                    'Koppel of meld het beeld bij het juiste nieuwsitem of teamlid.',
                ),
            ),
            'mvm_moderator' => array(
                'roleLabel'        => 'Moderator',
                'focusTitle'       => 'Community eerst, redactiecontext erbij',
                'focusDescription' => 'Forum en community staan centraal; nieuws en teamcontext blijven dichtbij voor consistente moderatie.',
                'navOrder'         => array( 'today', 'more', 'team', 'news', 'sources', 'agenda' ),
                'quickActions'     => array(
                    array( 'kind' => 'tool', 'id' => 'forum', 'title' => 'Forum openen', 'description' => 'Ga direct naar de communitydiscussies.' ),
                    array( 'kind' => 'view', 'id' => 'team', 'title' => 'Team', 'description' => 'Escaleren of afstemmen met de juiste rol.' ),
                    array( 'kind' => 'view', 'id' => 'news', 'title' => 'Nieuwscontext', 'description' => 'Controleer de bron van lopende discussies.' ),
                ),
                'steps' => array(
                    'Beoordeel eerst context en ernst voordat je ingrijpt.',
                    'Gebruik bestaande moderatie- en forumfuncties; wijzig geen redactionele inhoud zonder noodzaak.',
                    'Escaleren naar teamleider/SysOp wanneer een kwestie buiten moderatie valt.',
                ),
            ),
            'mvm_vertaler' => array(
                'roleLabel'        => 'Vertaler',
                'focusTitle'       => 'Context behouden, vertaling opleveren',
                'focusDescription' => 'Nieuws en kenniscontent staan vooraan; bronnen en team blijven beschikbaar voor terminologie en afstemming.',
                'navOrder'         => array( 'today', 'news', 'more', 'sources', 'team', 'agenda' ),
                'quickActions'     => array(
                    array( 'kind' => 'view', 'id' => 'news', 'title' => 'Te vertalen nieuws', 'description' => 'Bekijk de actuele redactionele inhoud.' ),
                    array( 'kind' => 'tool', 'id' => 'encyclopedia', 'title' => 'Encyclopedie', 'description' => 'Controleer namen, termen en historische context.' ),
                    array( 'kind' => 'view', 'id' => 'sources', 'title' => 'Bronnen', 'description' => 'Verifieer betekenis en lokale context.' ),
                ),
                'steps' => array(
                    'Lees eerst de volledige context en controleer eigennamen/plaatsnamen.',
                    'Vertaal betekenisgetrouw zonder redactionele feiten te veranderen.',
                    'Laat twijfelpunten expliciet terugkomen bij de redactie in plaats van ze zelf in te vullen.',
                ),
            ),
            'staff' => array(
                'roleLabel'        => 'Staf',
                'focusTitle'       => 'Vandaag overzichtelijk afwerken',
                'focusDescription' => 'Begin bij je actuele werk en gebruik daarna de module die bij de taak hoort.',
                'navOrder'         => $default_order,
                'quickActions'     => array(
                    array( 'kind' => 'view', 'id' => 'news', 'title' => 'Nieuws', 'description' => 'Actuele redactieflow.' ),
                    array( 'kind' => 'view', 'id' => 'agenda', 'title' => 'Agenda', 'description' => 'Planning en evenementen.' ),
                    array( 'kind' => 'view', 'id' => 'team', 'title' => 'Team', 'description' => 'Wie kan helpen?' ),
                ),
                'steps' => array(
                    'Kies eerst de taak die vandaag prioriteit heeft.',
                    'Werk binnen je bestaande rol en rechten.',
                    'Rond af met een duidelijke status of overdracht.',
                ),
            ),
        );
    }
}
