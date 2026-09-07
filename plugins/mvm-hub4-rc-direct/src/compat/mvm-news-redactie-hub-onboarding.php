<?php
/**
 * MvM Nieuws/Redactie Hub — role-aware onboarding and next-step guidance.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mvm_hubs_v3_owned_content_count( string $post_type, int $user_id = 0 ): int {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id || ! post_type_exists( $post_type ) ) {
        return 0;
    }
    $query = new WP_Query(
        array(
            'post_type'      => $post_type,
            'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
            'author'         => $user_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        )
    );
    return (int) $query->found_posts;
}

function mvm_hubs_v3_owned_pending_count( string $post_type, int $user_id = 0 ): int {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id || ! post_type_exists( $post_type ) ) {
        return 0;
    }
    $query = new WP_Query(
        array(
            'post_type'      => $post_type,
            'post_status'    => 'pending',
            'author'         => $user_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        )
    );
    return (int) $query->found_posts;
}

function mvm_hubs_v3_organization_profile_status( int $user_id = 0 ): array {
    $user_id = $user_id ?: get_current_user_id();
    $profile = function_exists( 'mvm_entrepreneur_profile_v1' ) ? mvm_entrepreneur_profile_v1( $user_id ) : array();
    $name_ok = ! empty( $profile['company_name'] );
    $description_ok = ! empty( $profile['description'] ) || ! empty( $profile['tagline'] );
    $contact_ok = ! empty( $profile['website'] ) || ! empty( $profile['email'] ) || ! empty( $profile['phone'] );
    $complete = $name_ok && $description_ok && $contact_ok;
    return array(
        'complete' => $complete,
        'name' => $name_ok,
        'description' => $description_ok,
        'contact' => $contact_ok,
    );
}

function mvm_hubs_v3_organization_profile_html(): string {
    if ( ! is_user_logged_in() || ! function_exists( 'mvm_entrepreneur_profile_v1' ) ) {
        return '';
    }
    $profile = mvm_entrepreneur_profile_v1( get_current_user_id() );
    $status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
    $preview = function_exists( 'mvm_hubs_v3_is_draft_preview' ) && mvm_hubs_v3_is_draft_preview();
    $html  = '<section class="mvmh3-section"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Organisatieprofiel</span><h2>Basisgegevens van je organisatie</h2></div><p>MvM gebruikt deze gegevens waar dat logisch is als uitgangspunt voor nieuwe organisatie-inhoud.</p></div>';
    if ( $preview ) {
        $html .= '<div class="mvmh3-callout"><strong>Previewmodus</strong><span>Profielvelden zijn alleen-lezen. Zo verandert testen van de draft nooit je live organisatieprofiel.</span></div>';
    }
    if ( 'profiel-opgeslagen' === $status ) {
        $html .= '<div class="mvmh3-notice mvmh3-notice--success"><div><strong>Organisatieprofiel opgeslagen.</strong><span>Nieuwe formulieren kunnen deze basisgegevens als uitgangspunt gebruiken.</span></div></div>';
    } elseif ( 'profiel-ongeldig' === $status ) {
        $html .= '<div class="mvmh3-notice"><div><strong>Naam ontbreekt.</strong><span>Vul minimaal de naam van je organisatie in.</span></div></div>';
    } elseif ( 'profiel-contact-ongeldig' === $status ) {
        $html .= '<div class="mvmh3-notice"><div><strong>Controleer de contactgegevens.</strong><span>Gebruik een geldig e-mailadres en een website die begint met http:// of https://.</span></div></div>';
    }
    $html .= '<form class="mvmh3-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data"><fieldset class="mvmh3-preview-safe"' . ( $preview ? ' disabled aria-disabled="true"' : '' ) . '><input type="hidden" name="action" value="mvm_entrepreneur_save_profile_v1">';
    $html .= wp_nonce_field( 'mvm_entrepreneur_profile_v1', 'mvm_nonce', true, false );
    $html .= '<div class="mvmh3-form__grid"><label><span>Naam organisatie</span><input required type="text" maxlength="180" name="company_name" autocomplete="organization" value="' . esc_attr( (string) ( $profile['company_name'] ?? '' ) ) . '"></label><label><span>Korte omschrijving</span><input type="text" maxlength="180" name="tagline" value="' . esc_attr( (string) ( $profile['tagline'] ?? '' ) ) . '" placeholder="Bijv. lokale vereniging, winkel of bedrijf in Mierlo"></label><label class="mvmh3-form__wide"><span>Over de organisatie</span><textarea name="description" rows="5" maxlength="1500">' . esc_textarea( (string) ( $profile['description'] ?? '' ) ) . '</textarea></label><label><span>Website</span><input type="url" name="website" placeholder="https://" value="' . esc_attr( (string) ( $profile['website'] ?? '' ) ) . '"></label><label><span>Contact e-mail</span><input type="email" name="email" autocomplete="email" value="' . esc_attr( (string) ( $profile['email'] ?? '' ) ) . '"></label><label><span>Telefoon</span><input type="tel" name="phone" autocomplete="tel" value="' . esc_attr( (string) ( $profile['phone'] ?? '' ) ) . '"></label><label><span>Plaats</span><input type="text" name="location" value="' . esc_attr( (string) ( $profile['location'] ?? 'Mierlo' ) ) . '"></label><label class="mvmh3-form__wide"><span>Adres</span><input type="text" name="address" autocomplete="street-address" value="' . esc_attr( (string) ( $profile['address'] ?? '' ) ) . '"></label><label class="mvmh3-form__wide"><span>Logo organisatie</span><input type="file" name="business_logo" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG of WebP, maximaal 3 MB. Laat leeg om het huidige logo te behouden.</small></label></div>';
    if ( ! empty( $profile['logo_url'] ) ) {
        $html .= '<div class="mvmh3-profile-logo"><img src="' . esc_url( (string) $profile['logo_url'] ) . '" alt="Huidig organisatielogo"><span>Huidig logo</span></div>';
    }
    $html .= '<div class="mvmh3-form__footer"><p>Wijzig alleen gegevens die je namens deze organisatie mag beheren. Openbare MvM-pagina-inhoud beheer je apart via MvM-pagina.</p><button class="mvmh3-primary" type="submit">Organisatieprofiel opslaan</button></div></fieldset></form></section>';
    return $html;
}

function mvm_hubs_v3_business_scope_html(): string {
    if ( ! is_user_logged_in() || ! function_exists( 'mvm_hubs_v3_business_tasks' ) ) {
        return '';
    }
    $tasks = mvm_hubs_v3_business_tasks();
    $enabled = array();
    foreach ( array(
        'pages' => 'MvM-pagina',
        'promotion' => 'Promoties',
        'vacancies' => 'Vacatures',
        'events' => 'Evenementen',
    ) as $key => $label ) {
        if ( ! empty( $tasks[ $key ] ) ) {
            $enabled[] = $label;
        }
    }
    $scope = $enabled ? implode( ' · ', $enabled ) : 'Alleen basisfuncties';
    $html = '<section class="mvmh3-section mvmh3-review-summary"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Toegang</span><h2>Jouw organisatiescope</h2></div><p>De server bepaalt per accounttype welke services beschikbaar zijn; navigatie alleen geeft nooit extra rechten.</p></div><div class="mvmh3-review-summary__grid">';
    $html .= '<article><span>Accounttype</span><strong>' . esc_html( (string) $tasks['account_label'] ) . '</strong><small>Toegekend na beoordeling</small></article>';
    $html .= '<article><span>Beschikbare services</span><strong>' . esc_html( (string) count( $enabled ) ) . '</strong><small>' . esc_html( $scope ) . '</small></article>';
    $html .= '<article><span>Objectgrens</span><strong>Eigen inhoud</strong><small>Vacatures en evenementen blijven owner-scoped</small></article>';
    $html .= '</div><div class="mvmh3-callout"><strong>Least privilege</strong><span>Een event-only organizer krijgt geen promotie-, pagina- of vacaturemogelijkheden. Commerciële organisatieaccounts kunnen alleen de services gebruiken die expliciet bij hun profiel horen; publicatie blijft waar nodig onder MvM-review.</span></div></section>';
    return $html;
}

function mvm_hubs_v3_business_review_status_html(): string {
    if ( ! is_user_logged_in() || ! function_exists( 'mvm_hubs_v3_business_tasks' ) ) {
        return '';
    }
    $tasks = mvm_hubs_v3_business_tasks();
    $user_id = get_current_user_id();
    $items = array();
    if ( ! empty( $tasks['promotion'] ) ) {
        $items[] = array( 'Aanbiedingen / promo&#8217;s', mvm_hubs_v3_owned_pending_count( 'mvm_advertentie', $user_id ), home_url( '/ondernemers/' ) );
    }
    if ( ! empty( $tasks['vacancies'] ) ) {
        $items[] = array( 'Vacatures', mvm_hubs_v3_owned_pending_count( 'mvm_vacature', $user_id ), home_url( '/ondernemers-hub/?deel=vacature' ) );
    }
    if ( ! empty( $tasks['events'] ) ) {
        $items[] = array( 'Evenementen', mvm_hubs_v3_owned_pending_count( 'event_listing', $user_id ), home_url( '/evenement-dashboard/' ) );
    }
    if ( ! $items ) {
        return '';
    }
    $total = array_sum( array_map( static fn( $item ) => (int) $item[1], $items ) );
    $html  = '<section class="mvmh3-section mvmh3-review-summary"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Status</span><h2>Wacht op MvM</h2></div><p>' . esc_html( $total > 0 ? $total . ' inzending' . ( 1 === $total ? '' : 'en' ) . ' wacht' . ( 1 === $total ? '' : 'en' ) . ' op controle.' : 'Er wachten momenteel geen inzendingen op controle.' ) . '</p></div><div class="mvmh3-review-summary__grid">';
    foreach ( $items as $item ) {
        $count = (int) $item[1];
        $html .= '<a href="' . esc_url( $item[2] ) . '"><span>' . $item[0] . '</span><strong>' . esc_html( (string) $count ) . '</strong><small>' . esc_html( $count > 0 ? 'ter beoordeling' : 'niets open' ) . '</small></a>';
    }
    $html .= '</div><div class="mvmh3-callout"><strong>Wat gebeurt er nu?</strong><span>MvM controleert de inhoud. Na goedkeuring kan deze zichtbaar worden. Als iets moet worden aangepast, blijft de inhoud uit de openbare publicatiestroom totdat de controle is afgerond.</span></div></section>';
    return $html;
}

function mvm_hubs_v3_event_manager_html(): string {
    if ( ! is_user_logged_in() || ! function_exists( 'mvm_e2_is_organizer' ) || ! mvm_e2_is_organizer() ) {
        return '';
    }

    $preview = function_exists( 'mvm_hubs_v3_is_draft_preview' ) && mvm_hubs_v3_is_draft_preview();
    $edit_id = isset( $_GET['bewerken'] ) ? absint( $_GET['bewerken'] ) : 0;
    $editing = $edit_id && function_exists( 'mvm_e2_owned_post' ) && mvm_e2_owned_post( $edit_id, 'event_listing' ) ? get_post( $edit_id ) : null;
    $status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

    $args = array(
        'post_type'      => 'event_listing',
        'post_status'    => array( 'publish', 'pending', 'draft', 'expired' ),
        'posts_per_page' => 100,
        'meta_key'       => '_event_start_date',
        'orderby'        => array( 'meta_value' => 'ASC', 'title' => 'ASC' ),
        'order'          => 'ASC',
        'no_found_rows'  => true,
    );
    if ( ! function_exists( 'mvm_e2_is_staff' ) || ! mvm_e2_is_staff() ) {
        $args['author'] = get_current_user_id();
    }
    $events = get_posts( $args );

    $html = '<section class="mvmh3-section mvmh3-event-manager"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Evenementen</span><h2>Aanmelden en beheren</h2></div><p>Nieuwe evenementen en inhoudelijke wijzigingen aan gepubliceerde evenementen gaan eerst ter controle door MvM.</p></div>';
    $html .= '<ol class="mvmh3-steps"><li><strong>1. Vul in</strong><span>Voeg titel, datum, tijd, locatie en organisatorinformatie toe.</span></li><li><strong>2. Dien in</strong><span>De activiteit krijgt de status Ter beoordeling en is nog niet openbaar.</span></li><li><strong>3. MvM controleert</strong><span>Na controle wordt het evenement gepubliceerd of krijg je bericht als iets aangepast moet worden.</span></li></ol>';
    if ( $preview ) {
        $html .= '<div class="mvmh3-callout"><strong>Previewmodus</strong><span>Evenementbeheer is hier alleen-lezen. Zo kan het testen van ' . esc_html( defined( 'MVM_HUBS_V3_VERSION' ) ? MVM_HUBS_V3_VERSION : 'deze draft' ) . ' geen live evenement wijzigen.</span></div>';
    }
    if ( 'ingediend' === $status ) {
        $html .= '<div class="mvmh3-notice mvmh3-notice--success"><div><strong>Evenement ingediend.</strong><span>De inzending staat klaar voor controle door MvM.</span></div></div>';
    } elseif ( 'bijgewerkt' === $status ) {
        $html .= '<div class="mvmh3-notice mvmh3-notice--success"><div><strong>Wijziging opgeslagen.</strong><span>Een inhoudelijke wijziging staat waar nodig opnieuw klaar voor controle.</span></div></div>';
    } elseif ( 'ongeldig' === $status ) {
        $html .= '<div class="mvmh3-notice"><div><strong>Controleer titel en datum.</strong><span>Het evenement kon nog niet worden opgeslagen.</span></div></div>';
    }

    $html .= '<div class="mvmh3-event-manager__grid"><div><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Mijn evenementen</span><h3>Overzicht</h3></div><p>' . esc_html( count( $events ) . ' evenement' . ( 1 === count( $events ) ? '' : 'en' ) ) . '</p></div><div class="mvmh3-list">';
    if ( ! $events ) {
        $html .= '<div class="mvmh3-empty"><strong>Nog geen evenementen.</strong><span>Je ingediende activiteiten verschijnen hier.</span></div>';
    } else {
        foreach ( $events as $event ) {
            $event_id = (int) $event->ID;
            $meta = array();
            if ( function_exists( 'mvm_e2_date_label' ) ) {
                $meta[] = mvm_e2_date_label( $event_id );
            }
            if ( function_exists( 'mvm_e2_time_label' ) ) {
                $meta[] = mvm_e2_time_label( $event_id );
            }
            if ( function_exists( 'mvm_e2_location' ) ) {
                $meta[] = mvm_e2_location( $event_id );
            }
            $html .= '<article class="mvmh3-vacancy-item"><div><strong>' . esc_html( get_the_title( $event_id ) ) . '</strong><span>' . esc_html( implode( ' · ', array_filter( $meta ) ) ) . '</span><span>' . esc_html( function_exists( 'mvm_e2_status_label' ) ? mvm_e2_status_label( get_post_status( $event_id ) ) : get_post_status( $event_id ) ) . '</span></div><div class="mvmh3-vacancy-item__actions"><a class="mvmh3-vacancy-link" href="' . esc_url( add_query_arg( array( 'deel' => 'evenement', 'bewerken' => $event_id ), home_url( '/ondernemers-hub/' ) ) ) . '">Bewerken</a>';
            if ( 'publish' === get_post_status( $event_id ) ) {
                $html .= '<a class="mvmh3-vacancy-link" href="' . esc_url( get_permalink( $event_id ) ) . '">Bekijken</a>';
            }
            $html .= '</div></article>';
        }
    }
    $html .= '</div></div>';

    $html .= '<div><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">' . esc_html( $editing ? 'Bewerken' : 'Nieuw' ) . '</span><h3>' . esc_html( $editing ? 'Evenement bewerken' : 'Evenement aanmelden' ) . '</h3></div></div>';
    $html .= '<form class="mvmh3-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data"><fieldset class="mvmh3-preview-safe"' . ( $preview ? ' disabled aria-disabled="true"' : '' ) . '><input type="hidden" name="action" value="mvm_e2_save_event"><input type="hidden" name="event_id" value="' . ( $editing ? (int) $editing->ID : 0 ) . '">' . wp_nonce_field( 'mvm_e2_save_event', 'mvm_nonce', true, false );
    $html .= '<div class="mvmh3-form__grid"><label class="mvmh3-form__wide"><span>Titel</span><input required type="text" name="event_title" maxlength="180" value="' . esc_attr( $editing ? $editing->post_title : '' ) . '"></label><label class="mvmh3-form__wide"><span>Omschrijving</span><textarea name="event_description" rows="7">' . esc_textarea( $editing ? $editing->post_content : '' ) . '</textarea></label><label class="mvmh3-form__wide"><span>Organisator</span><input type="text" name="organizer_name" maxlength="180" value="' . esc_attr( $editing ? get_post_meta( $editing->ID, '_mvm_organizer_name', true ) : '' ) . '"></label>';
    $html .= '<label><span>Startdatum</span><input required type="date" name="start_date" value="' . esc_attr( $editing ? substr( (string) get_post_meta( $editing->ID, '_event_start_date', true ), 0, 10 ) : '' ) . '"></label><label><span>Starttijd</span><input type="time" name="start_time" value="' . esc_attr( $editing ? (string) get_post_meta( $editing->ID, '_event_start_time', true ) : '' ) . '"></label><label><span>Einddatum</span><input type="date" name="end_date" value="' . esc_attr( $editing ? substr( (string) get_post_meta( $editing->ID, '_event_end_date', true ), 0, 10 ) : '' ) . '"></label><label><span>Eindtijd</span><input type="time" name="end_time" value="' . esc_attr( $editing ? (string) get_post_meta( $editing->ID, '_event_end_time', true ) : '' ) . '"></label>';
    $html .= '<label class="mvmh3-form__wide mvmh3-inline-check"><input type="checkbox" name="all_day" value="1" ' . checked( $editing ? get_post_meta( $editing->ID, '_mvm_all_day', true ) : '', '1', false ) . '><span>Hele dag</span></label><label><span>Locatienaam</span><input type="text" name="event_location" maxlength="180" value="' . esc_attr( $editing ? get_post_meta( $editing->ID, '_event_location', true ) : '' ) . '"></label><label><span>Adres</span><input type="text" name="event_address" maxlength="220" value="' . esc_attr( $editing ? get_post_meta( $editing->ID, '_event_address', true ) : '' ) . '"></label><label><span>Postcode</span><input type="text" name="event_postcode" maxlength="20" value="' . esc_attr( $editing ? get_post_meta( $editing->ID, '_event_pincode', true ) : '' ) . '"></label><label><span>Prijs</span><input type="text" name="event_price" maxlength="80" placeholder="Bijv. Gratis of 12,50" value="' . esc_attr( $editing ? get_post_meta( $editing->ID, '_event_ticket_price', true ) : '' ) . '"></label><label class="mvmh3-form__wide"><span>Website / aanmelden</span><input type="url" name="event_website" value="' . esc_attr( $editing ? get_post_meta( $editing->ID, '_registration', true ) : '' ) . '"></label><label class="mvmh3-form__wide"><span>Afbeelding</span><input type="file" name="event_image" accept="image/jpeg,image/png,image/webp"><small>JPEG, PNG of WebP · maximaal 8 MB.</small></label></div>';
    $html .= '<div class="mvmh3-form__footer"><p>Je beheert alleen evenementen waarvoor je account bevoegd is. Nieuwe inhoud en inhoudelijke wijzigingen gaan eerst naar controle.</p><button class="mvmh3-primary" type="submit">' . esc_html( $editing ? 'Wijzigingen indienen' : 'Evenement indienen' ) . '</button></div></fieldset></form></div></div></section>';
    return $html;
}

function mvm_hubs_v3_business_onboarding_html(): string {
    if ( ! is_user_logged_in() || ! function_exists( 'mvm_hubs_v3_business_tasks' ) ) {
        return '';
    }
    $tasks = mvm_hubs_v3_business_tasks();
    $user_id = get_current_user_id();
    $profile = mvm_hubs_v3_organization_profile_status( $user_id );
    $promo_count = ! empty( $tasks['promotion'] ) ? mvm_hubs_v3_owned_content_count( 'mvm_advertentie', $user_id ) : 0;
    $vacancy_count = ! empty( $tasks['vacancies'] ) ? mvm_hubs_v3_owned_content_count( 'mvm_vacature', $user_id ) : 0;
    $event_count = ! empty( $tasks['events'] ) ? mvm_hubs_v3_owned_content_count( 'event_listing', $user_id ) : 0;

    $checks = array();
    if ( ! empty( $tasks['company'] ) ) {
        $checks[] = array(
            'label' => 'Organisatieprofiel',
            'done' => ! empty( $profile['complete'] ),
            'text' => ! empty( $profile['complete'] ) ? 'Basisgegevens zijn ingevuld.' : 'Vul naam, korte omschrijving en minimaal één contactmogelijkheid in.',
            'url' => home_url( '/ondernemers-hub/?deel=profiel' ),
            'action' => ! empty( $profile['complete'] ) ? 'Profiel bekijken' : 'Profiel aanvullen',
        );
    }
    if ( ! empty( $tasks['pages'] ) ) {
        $checks[] = array(
            'label' => 'MvM-pagina',
            'done' => null,
            'text' => 'Maak of beheer de publieke communitypagina van je organisatie. PeepSo bewaart deze pagina zelf; daarom toont MvM hier geen onbetrouwbare automatische status.',
            'url' => home_url( '/paginas/' ),
            'action' => 'Paginabeheer openen',
        );
    }
    if ( ! empty( $tasks['promotion'] ) ) {
        $checks[] = array(
            'label' => 'Aanbieding / promo',
            'done' => $promo_count > 0,
            'text' => $promo_count > 0 ? $promo_count . ' eigen inzending(en) gevonden.' : 'Nog geen aanbieding of promotie ingediend. Dit is optioneel; gebruik het wanneer je iets lokaals wilt uitlichten.',
            'url' => home_url( '/ondernemers/#advertentie-maken' ),
            'action' => 'Promo openen',
        );
    }
    if ( ! empty( $tasks['vacancies'] ) ) {
        $checks[] = array(
            'label' => 'Vacatures',
            'done' => $vacancy_count > 0,
            'text' => $vacancy_count > 0 ? $vacancy_count . ' eigen vacature(s) gevonden.' : 'Nog geen vacature ingediend. Alleen nodig wanneer je personeel of ondersteuning zoekt.',
            'url' => home_url( '/ondernemers-hub/?deel=vacature' ),
            'action' => 'Vacatures openen',
        );
    }
    if ( ! empty( $tasks['events'] ) ) {
        $checks[] = array(
            'label' => 'Evenementen',
            'done' => $event_count > 0,
            'text' => $event_count > 0 ? $event_count . ' eigen evenement(en) gevonden.' : 'Nog geen evenement aangemeld. Voeg alleen activiteiten toe die echt bij jouw organisatie horen.',
            'url' => home_url( '/ondernemers-hub/?deel=evenement' ),
            'action' => $event_count > 0 ? 'Evenementen beheren' : 'Evenement aanmelden',
        );
    }

    $measured = array_filter( $checks, static fn( $item ) => null !== $item['done'] );
    $done = count( array_filter( $measured, static fn( $item ) => true === $item['done'] ) );
    $total = count( $measured );
    $percent = $total ? (int) round( ( $done / $total ) * 100 ) : 0;

    $html = '<section class="mvmh3-section mvmh3-onboarding"><div class="mvmh3-section__head"><div><span class="mvmh3-eyebrow">Aan de slag</span><h2>Jouw organisatie in MvM</h2></div><p>Je hoeft niet alles tegelijk te doen. MvM laat zien wat al is ingericht en welke volgende stap logisch kan zijn.</p></div>';
    $html .= '<div class="mvmh3-onboarding__progress"><div><strong>' . esc_html( $done . ' van ' . $total . ' meetbare stappen' ) . '</strong><span>Optionele onderdelen hoeven niet ingevuld te worden om je account te gebruiken.</span></div><div class="mvmh3-onboarding__bar" aria-label="Voortgang ' . esc_attr( (string) $percent ) . ' procent"><span style="width:' . esc_attr( (string) $percent ) . '%"></span></div></div>';
    $html .= '<div class="mvmh3-onboarding__list">';
    foreach ( $checks as $item ) {
        $state_class = null === $item['done'] ? 'is-info' : ( $item['done'] ? 'is-done' : 'is-next' );
        $state_label = null === $item['done'] ? 'Zelf beheren' : ( $item['done'] ? 'In orde' : 'Kan nog' );
        $html .= '<article class="mvmh3-onboarding__item ' . esc_attr( $state_class ) . '"><div><span class="mvmh3-onboarding__state">' . esc_html( $state_label ) . '</span><strong>' . esc_html( $item['label'] ) . '</strong><p>' . esc_html( $item['text'] ) . '</p></div><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['action'] ) . '</a></article>';
    }
    $html .= '</div><div class="mvmh3-callout"><strong>Geen verplicht scorebord</strong><span>Een organisatieaccount hoeft geen 100% te halen. Deze checklist helpt alleen om de juiste functies te vinden en vergeten basisgegevens op te merken.</span></div></section>';
    return $html;
}

