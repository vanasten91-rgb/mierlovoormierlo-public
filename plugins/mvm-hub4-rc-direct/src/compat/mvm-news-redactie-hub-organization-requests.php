<?php
/**
 * MvM Nieuws/Redactie Hub — safe organization-account request and review flow.
 *
 * A request never grants capabilities. Only SysOp/Administrator can approve an
 * allowlisted MvM organization role after a write-ahead private audit record.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mvm_hubs_v3_org_request_roles(): array {
    return array(
        'mvm_vereniging' => 'Vereniging',
        'mvm_club'       => 'Club',
        'mvm_organisator'=> 'Organisator',
        'mvm_ondernemer' => 'Ondernemer',
        'mvm_bedrijf'    => 'Bedrijf',
        'mvm_winkelier'  => 'Winkelier',
    );
}

function mvm_hubs_v3_has_mvm_org_role( int $user_id = 0 ): bool {
    $user_id = $user_id ?: get_current_user_id();
    $user = $user_id ? get_userdata( $user_id ) : null;
    if ( ! $user instanceof WP_User ) {
        return false;
    }
    return (bool) array_intersect( (array) $user->roles, array_keys( mvm_hubs_v3_org_request_roles() ) );
}

function mvm_hubs_v3_register_org_request_type(): void {
    register_post_type(
        'mvm_org_request',
        array(
            'labels' => array(
                'name'          => 'Organisatieaccount aanvragen',
                'singular_name' => 'Organisatieaccount aanvraag',
            ),
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => false,
            'show_in_rest'        => false,
            'exclude_from_search' => true,
            'supports'            => array( 'title', 'author' ),
        )
    );
}
add_action( 'init', 'mvm_hubs_v3_register_org_request_type', 1 );

function mvm_hubs_v3_org_request_pending_count(): int {
    if ( ! post_type_exists( 'mvm_org_request' ) ) {
        return 0;
    }
    $query = new WP_Query(
        array(
            'post_type'      => 'mvm_org_request',
            'post_status'    => 'private',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_mvm_org_request_status',
            'meta_value'     => 'pending',
            'no_found_rows'  => false,
        )
    );
    return (int) $query->found_posts;
}

function mvm_hubs_v3_org_request_current( int $user_id = 0 ): ?WP_Post {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id ) {
        return null;
    }
    $posts = get_posts(
        array(
            'post_type'      => 'mvm_org_request',
            'post_status'    => 'private',
            'posts_per_page' => 1,
            'author'         => $user_id,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'meta_key'       => '_mvm_org_request_status',
            'meta_value'     => 'pending',
            'no_found_rows'  => true,
        )
    );
    return $posts && $posts[0] instanceof WP_Post ? $posts[0] : null;
}

function mvm_hubs_v3_org_request_status_label( string $status ): string {
    $labels = array(
        'pending'  => 'In behandeling',
        'approved' => 'Goedgekeurd',
        'rejected' => 'Afgewezen',
    );
    return $labels[ $status ] ?? 'Onbekend';
}

function mvm_hubs_v3_org_rejection_reasons(): array {
    return array(
        'needs_information'   => 'Meer informatie nodig',
        'identity_unverified' => 'Nog niet duidelijk wie namens de organisatie handelt',
        'wrong_account_type'  => 'Kies een ander accounttype',
        'duplicate'           => 'Er bestaat al een passend organisatieaccount',
        'not_eligible'        => 'Deze aanvraag past niet bij een organisatieaccount',
    );
}

function mvm_hubs_v3_org_request_redirect( array $args = array() ): void {
    wp_safe_redirect( add_query_arg( $args, home_url( '/mijn-hub/?deel=organisatieaccount' ) ) );
    exit;
}

function mvm_hubs_v3_org_request_notify_admin( int $request_id, string $organization, string $role_label ): void {
    $to = (string) apply_filters( 'mvm_hubs_v3_org_request_notification_email', 'contact@mierlovoormierlo.nl' );
    if ( ! is_email( $to ) ) {
        return;
    }
    $subject = '[MvM] Nieuwe organisatieaccount-aanvraag';
    $message = "Er staat een nieuwe organisatieaccount-aanvraag klaar.\n\nOrganisatie: " . $organization . "\nAccounttype: " . $role_label . "\nAanvraagnummer: " . $request_id . "\n\nBeoordeel de aanvraag via Technische hub > Organisatieaccounts.\n" . home_url( '/beheer-hub/?deel=organisaties' );
    wp_mail( $to, $subject, $message );
}

function mvm_hubs_v3_org_request_notify_user( int $user_id, string $decision, string $role_label, string $reason_label = '' ): void {
    $user = get_userdata( $user_id );
    if ( ! $user instanceof WP_User || ! is_email( $user->user_email ) ) {
        return;
    }
    if ( 'approved' === $decision ) {
        $subject = '[MvM] Je organisatieaccount is goedgekeurd';
        $message = "Je aanvraag voor het accounttype " . $role_label . " is goedgekeurd.\n\nNa inloggen vind je je extra functies in de Organisatiehub:\n" . home_url( '/ondernemers-hub/' ) . "\n\nHeb je vragen of klopt iets niet? Mail contact@mierlovoormierlo.nl.";
    } else {
        $subject = '[MvM] Update over je organisatieaccount-aanvraag';
        $message = "Je organisatieaccount-aanvraag is niet goedgekeurd.\n";
        if ( '' !== $reason_label ) {
            $message .= "Reden: " . $reason_label . "\n";
        }
        $message .= "\nJe kunt in Mijn Mierlo een nieuwe of aangepaste aanvraag doen, of hulp vragen via contact@mierlovoormierlo.nl.\n" . home_url( '/mijn-hub/?deel=organisatieaccount' );
    }
    wp_mail( $user->user_email, $subject, $message );
}

function mvm_hubs_v3_save_org_request(): void {
    if ( function_exists( 'mvm_hubs_v3_is_draft_preview' ) && mvm_hubs_v3_is_draft_preview() ) {
        wp_die( 'Previewmodus: organisatieaanvragen kunnen hier niet live worden gewijzigd.', 403 );
    }
    if ( ! is_user_logged_in() ) {
        auth_redirect();
    }
    check_admin_referer( 'mvm_hubs_v3_org_request', 'mvm_org_request_nonce' );

    if ( mvm_hubs_v3_has_mvm_org_role() ) {
        mvm_hubs_v3_org_request_redirect( array( 'org_request' => 'already_active' ) );
    }

    $roles = mvm_hubs_v3_org_request_roles();
    $requested_role = isset( $_POST['requested_role'] ) ? sanitize_key( wp_unslash( $_POST['requested_role'] ) ) : '';
    $organization   = isset( $_POST['organization_name'] ) ? sanitize_text_field( wp_unslash( $_POST['organization_name'] ) ) : '';
    $website        = isset( $_POST['organization_website'] ) ? esc_url_raw( wp_unslash( $_POST['organization_website'] ) ) : '';
    $note           = isset( $_POST['organization_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['organization_note'] ) ) : '';

    if ( ! isset( $roles[ $requested_role ] ) || '' === $organization || mb_strlen( $organization ) > 180 || mb_strlen( $note ) > 1000 ) {
        mvm_hubs_v3_org_request_redirect( array( 'org_request' => 'invalid' ) );
    }

    $user_id = get_current_user_id();
    $existing = mvm_hubs_v3_org_request_current( $user_id );
    if ( ! $existing ) {
        $last = (int) get_user_meta( $user_id, '_mvm_org_request_last_submit', true );
        if ( $last > 0 && time() - $last < 60 ) {
            mvm_hubs_v3_org_request_redirect( array( 'org_request' => 'rate_limited' ) );
        }
    }

    $payload = array(
        'post_type'   => 'mvm_org_request',
        'post_status' => 'private',
        'post_title'  => $organization . ' — ' . $roles[ $requested_role ],
        'post_author' => $user_id,
    );
    if ( $existing ) {
        $payload['ID'] = $existing->ID;
    }

    $request_id = wp_insert_post( wp_slash( $payload ), true );
    if ( is_wp_error( $request_id ) || ! $request_id ) {
        mvm_hubs_v3_org_request_redirect( array( 'org_request' => 'failed' ) );
    }

    update_post_meta( $request_id, '_mvm_org_request_status', 'pending' );
    update_post_meta( $request_id, '_mvm_org_request_role', $requested_role );
    update_post_meta( $request_id, '_mvm_org_request_organization', $organization );
    update_post_meta( $request_id, '_mvm_org_request_website', $website );
    update_post_meta( $request_id, '_mvm_org_request_note', $note );
    update_post_meta( $request_id, '_mvm_org_request_submitted_at', current_time( 'mysql', true ) );
    update_user_meta( $user_id, '_mvm_employer_registration_pending', 1 );
    update_user_meta( $user_id, '_mvm_org_request_last_status', 'pending' );
    update_user_meta( $user_id, '_mvm_org_request_last_reason', '' );
    update_user_meta( $user_id, '_mvm_org_request_last_submit', time() );

    if ( function_exists( 'mvm_hubs_v3_audit' ) ) {
        mvm_hubs_v3_audit(
            'organization_account_requested',
            array(
                'hub'         => 'user',
                'object_type' => 'mvm_org_request',
                'object_id'   => (int) $request_id,
                'result'      => 'pending',
            )
        );
    }
    mvm_hubs_v3_org_request_notify_admin( (int) $request_id, $organization, $roles[ $requested_role ] );

    mvm_hubs_v3_org_request_redirect( array( 'org_request' => 'sent' ) );
}
add_action( 'admin_post_mvm_hubs_v3_org_request', 'mvm_hubs_v3_save_org_request' );

function mvm_hubs_v3_org_review_allowed(): bool {
    return is_user_logged_in() && ( current_user_can( 'mvm_hub3_admin_access' ) || current_user_can( 'manage_options' ) );
}

function mvm_hubs_v3_org_review_audit_gate( int $request_id, int $target_user_id, string $decision, string $requested_role ): bool {
    $audit_id = wp_insert_post(
        array(
            'post_type'    => 'mvm_hub_audit_v3',
            'post_status'  => 'private',
            'post_title'   => 'organization_account_review_authorized',
            'post_content' => wp_json_encode(
                array(
                    'hub'         => 'admin',
                    'object_type' => 'mvm_org_request',
                    'object_id'   => $request_id,
                    'object_key'  => $requested_role,
                    'result'      => $decision,
                ),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'post_author'  => get_current_user_id(),
        ),
        true
    );
    return ! is_wp_error( $audit_id ) && (int) $audit_id > 0;
}

function mvm_hubs_v3_review_org_request(): void {
    if ( function_exists( 'mvm_hubs_v3_is_draft_preview' ) && mvm_hubs_v3_is_draft_preview() ) {
        wp_die( 'Previewmodus: organisatieaccounts kunnen hier niet live worden goedgekeurd of afgewezen.', 403 );
    }
    if ( ! mvm_hubs_v3_org_review_allowed() ) {
        wp_die( 'Geen toegang.', 403 );
    }

    $request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
    check_admin_referer( 'mvm_hubs_v3_org_review_' . $request_id, 'mvm_org_review_nonce' );
    $decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
    if ( ! in_array( $decision, array( 'approve', 'reject' ), true ) ) {
        wp_die( 'Ongeldige beslissing.', 400 );
    }
    $reason_code = isset( $_POST['reason_code'] ) ? sanitize_key( wp_unslash( $_POST['reason_code'] ) ) : '';
    if ( 'reject' === $decision && ! isset( mvm_hubs_v3_org_rejection_reasons()[ $reason_code ] ) ) {
        $reason_code = 'needs_information';
    }

    $request = $request_id ? get_post( $request_id ) : null;
    if ( ! $request instanceof WP_Post || 'mvm_org_request' !== $request->post_type || 'private' !== $request->post_status ) {
        wp_die( 'Aanvraag niet gevonden.', 404 );
    }
    if ( 'pending' !== (string) get_post_meta( $request_id, '_mvm_org_request_status', true ) ) {
        wp_die( 'Deze aanvraag is al behandeld.', 409 );
    }

    $target_user_id = (int) $request->post_author;
    $target_user = get_userdata( $target_user_id );
    $requested_role = (string) get_post_meta( $request_id, '_mvm_org_request_role', true );
    $allowed_roles = mvm_hubs_v3_org_request_roles();
    if ( ! $target_user instanceof WP_User || ! isset( $allowed_roles[ $requested_role ] ) || ! get_role( $requested_role ) ) {
        wp_die( 'Accounttype kan niet veilig worden toegekend.', 409 );
    }

    if ( ! mvm_hubs_v3_org_review_audit_gate( $request_id, $target_user_id, $decision, $requested_role ) ) {
        wp_die( 'De beveiligde auditregistratie is mislukt. Er zijn geen rechten gewijzigd.', 503 );
    }

    if ( 'approve' === $decision ) {
        foreach ( array_keys( mvm_hubs_v3_org_request_roles() ) as $role_key ) {
            if ( in_array( $role_key, (array) $target_user->roles, true ) ) {
                $target_user->remove_role( $role_key );
            }
        }
        $target_user->add_role( $requested_role );
        update_post_meta( $request_id, '_mvm_org_request_status', 'approved' );
        $result = 'approved';
    } else {
        update_post_meta( $request_id, '_mvm_org_request_status', 'rejected' );
        update_post_meta( $request_id, '_mvm_org_request_reason', $reason_code );
        $result = 'rejected';
    }

    update_user_meta( $target_user_id, '_mvm_org_request_last_status', $result );
    update_user_meta( $target_user_id, '_mvm_org_request_last_reason', 'rejected' === $result ? $reason_code : '' );
    update_post_meta( $request_id, '_mvm_org_request_reviewed_by', get_current_user_id() );
    update_post_meta( $request_id, '_mvm_org_request_reviewed_at', current_time( 'mysql', true ) );
    delete_user_meta( $target_user_id, '_mvm_employer_registration_pending' );

    if ( function_exists( 'mvm_hubs_v3_audit' ) ) {
        mvm_hubs_v3_audit(
            'organization_account_review_completed',
            array(
                'hub'         => 'admin',
                'object_type' => 'mvm_org_request',
                'object_id'   => $request_id,
                'result'      => $result,
            )
        );
    }
    $reason_label = 'rejected' === $result ? ( mvm_hubs_v3_org_rejection_reasons()[ $reason_code ] ?? '' ) : '';
    mvm_hubs_v3_org_request_notify_user( $target_user_id, $result, $allowed_roles[ $requested_role ], $reason_label );

    wp_safe_redirect( add_query_arg( 'org_review', $result, home_url( '/beheer-hub/?deel=organisaties' ) ) );
    exit;
}
add_action( 'admin_post_mvm_hubs_v3_org_review', 'mvm_hubs_v3_review_org_request' );

function mvm_hubs_v3_org_request_form_html(): string {
    if ( ! is_user_logged_in() ) {
        return '';
    }
    if ( mvm_hubs_v3_has_mvm_org_role() ) {
        return '<div class="mvmh3-notice mvmh3-notice--success"><div><strong>Organisatieaccount actief.</strong><span>Je extra functies staan in de Organisatiehub.</span></div></div>';
    }

    $current = mvm_hubs_v3_org_request_current();
    $status = isset( $_GET['org_request'] ) ? sanitize_key( wp_unslash( $_GET['org_request'] ) ) : '';
    $preview = function_exists( 'mvm_hubs_v3_is_draft_preview' ) && mvm_hubs_v3_is_draft_preview();
    $html = '';
    if ( $preview ) {
        $html .= '<div class="mvmh3-callout"><strong>Previewmodus</strong><span>Dit formulier is in de WPVibe-preview alleen-lezen. Zo kan testen van een draft nooit een live organisatieaanvraag wijzigen.</span></div>';
    }
    $last_status = (string) get_user_meta( get_current_user_id(), '_mvm_org_request_last_status', true );
    $last_reason = (string) get_user_meta( get_current_user_id(), '_mvm_org_request_last_reason', true );
    $pending_intent = (bool) get_user_meta( get_current_user_id(), '_mvm_employer_registration_pending', true );
    if ( ! $current && $pending_intent && 'rejected' !== $last_status ) {
        $html .= '<div class="mvmh3-callout"><strong>Rond je aanvraag nog af</strong><span>Je hebt bij registratie aangegeven dat je namens een werkgever wilt deelnemen. Kies hieronder het passende accounttype en dien de aanvraag in. Tot dat moment zijn er geen extra rechten actief.</span></div>';
    }
    if ( ! $current && 'rejected' === $last_status ) {
        $reason_label = mvm_hubs_v3_org_rejection_reasons()[ $last_reason ] ?? 'De aanvraag kon nog niet worden goedgekeurd';
        $html .= '<div class="mvmh3-notice"><div><strong>Aanvraag nog niet goedgekeurd.</strong><span>' . esc_html( $reason_label ) . '. Je kunt de gegevens hieronder aanpassen en opnieuw indienen, of Contact gebruiken als je uitleg nodig hebt.</span></div></div>';
    }
    if ( 'sent' === $status ) {
        $html .= '<div class="mvmh3-notice mvmh3-notice--success"><div><strong>Aanvraag ontvangen.</strong><span>Er zijn nog geen extra rechten toegekend. Je ziet hier de status totdat MvM de aanvraag heeft behandeld.</span></div></div>';
    } elseif ( 'invalid' === $status ) {
        $html .= '<div class="mvmh3-notice"><div><strong>Controleer je invoer.</strong><span>Kies een accounttype en vul de naam van je organisatie in.</span></div></div>';
    } elseif ( 'rate_limited' === $status ) {
        $html .= '<div class="mvmh3-notice"><div><strong>Even wachten.</strong><span>Je hebt zojuist al een aanvraag gedaan. Probeer het later opnieuw als dat nodig is.</span></div></div>';
    } elseif ( 'failed' === $status ) {
        $html .= '<div class="mvmh3-notice"><div><strong>Aanvraag niet opgeslagen.</strong><span>Er zijn geen rechten gewijzigd. Gebruik Contact als dit opnieuw gebeurt.</span></div></div>';
    }

    $selected = $current ? (string) get_post_meta( $current->ID, '_mvm_org_request_role', true ) : '';
    $organization = $current ? (string) get_post_meta( $current->ID, '_mvm_org_request_organization', true ) : '';
    $website = $current ? (string) get_post_meta( $current->ID, '_mvm_org_request_website', true ) : '';
    $note = $current ? (string) get_post_meta( $current->ID, '_mvm_org_request_note', true ) : '';

    if ( $current ) {
        $html .= '<div class="mvmh3-callout"><strong>Status: In behandeling</strong><span>Je kunt de aanvraag hieronder nog bijwerken. Extra rechten worden pas na goedkeuring door MvM actief.</span></div>';
    }

    $html .= '<form class="mvmh3-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><fieldset class="mvmh3-preview-safe"' . ( $preview ? ' disabled aria-disabled="true"' : '' ) . '><input type="hidden" name="action" value="mvm_hubs_v3_org_request">';
    $html .= wp_nonce_field( 'mvm_hubs_v3_org_request', 'mvm_org_request_nonce', true, false );
    $html .= '<div class="mvmh3-form__grid"><label><span>Accounttype</span><select name="requested_role" required><option value="">Kies het passende type</option>';
    foreach ( mvm_hubs_v3_org_request_roles() as $role_key => $label ) {
        $html .= '<option value="' . esc_attr( $role_key ) . '"' . selected( $selected, $role_key, false ) . '>' . esc_html( $label ) . '</option>';
    }
    $html .= '</select></label><label><span>Naam organisatie</span><input type="text" name="organization_name" maxlength="180" value="' . esc_attr( $organization ) . '" required></label><label class="mvmh3-form__wide"><span>Website of openbare pagina</span><input type="url" name="organization_website" value="' . esc_attr( $website ) . '" placeholder="https://"></label><label class="mvmh3-form__wide"><span>Korte toelichting</span><textarea name="organization_note" maxlength="1000" rows="4" placeholder="Wat doet de organisatie en waarom wil je dit account beheren?">' . esc_textarea( $note ) . '</textarea></label></div>';
    $html .= '<div class="mvmh3-form__footer"><p>MvM controleert de aanvraag. Het formulier kan nooit zelf een organisatie- of werkgeversrol activeren.</p><button class="mvmh3-primary" type="submit">' . esc_html( $current ? 'Aanvraag bijwerken' : 'Aanvraag indienen' ) . '</button></div></fieldset></form>';
    return $html;
}

function mvm_hubs_v3_org_requests_admin_html(): string {
    if ( ! mvm_hubs_v3_org_review_allowed() ) {
        return '';
    }
    $preview = function_exists( 'mvm_hubs_v3_is_draft_preview' ) && mvm_hubs_v3_is_draft_preview();
    $requests = get_posts(
        array(
            'post_type'      => 'mvm_org_request',
            'post_status'    => 'private',
            'posts_per_page' => 50,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'meta_key'       => '_mvm_org_request_status',
            'meta_value'     => 'pending',
            'no_found_rows'  => true,
        )
    );
    if ( ! $requests ) {
        return '<div class="mvmh3-empty"><strong>Geen open aanvragen.</strong><span>Nieuwe aanvragen verschijnen hier automatisch.</span></div>';
    }

    $roles = mvm_hubs_v3_org_request_roles();
    $reason_options = '<select name="reason_code" aria-label="Reden voor afwijzing">';
    foreach ( mvm_hubs_v3_org_rejection_reasons() as $reason_key => $reason_label ) {
        $reason_options .= '<option value="' . esc_attr( $reason_key ) . '">' . esc_html( $reason_label ) . '</option>';
    }
    $reason_options .= '</select>';
    $html = '<div class="mvmh3-list">';
    foreach ( $requests as $request ) {
        $user = get_userdata( (int) $request->post_author );
        $role_key = (string) get_post_meta( $request->ID, '_mvm_org_request_role', true );
        $organization = (string) get_post_meta( $request->ID, '_mvm_org_request_organization', true );
        $website = (string) get_post_meta( $request->ID, '_mvm_org_request_website', true );
        $note = (string) get_post_meta( $request->ID, '_mvm_org_request_note', true );
        $label = $roles[ $role_key ] ?? 'Onbekend';
        $html .= '<article class="mvmh3-org-request"><div class="mvmh3-org-request__body"><span class="mvmh3-eyebrow">' . esc_html( $label ) . '</span><h3>' . esc_html( $organization ) . '</h3><p>Account: <strong>' . esc_html( $user instanceof WP_User ? $user->display_name : 'Onbekend account' ) . '</strong></p>';
        if ( $website ) {
            $html .= '<p><a href="' . esc_url( $website ) . '" target="_blank" rel="noopener noreferrer">Openbare website bekijken</a></p>';
        }
        if ( $note ) {
            $html .= '<p>' . esc_html( $note ) . '</p>';
        }
        if ( $preview ) {
            $html .= '</div><div class="mvmh3-org-request__actions"><span class="mvmh3-preview-lock">Preview · beoordelen uitgeschakeld</span></div></article>';
        } else {
            $html .= '</div><div class="mvmh3-org-request__actions"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mvm_hubs_v3_org_review"><input type="hidden" name="request_id" value="' . (int) $request->ID . '"><input type="hidden" name="decision" value="approve">' . wp_nonce_field( 'mvm_hubs_v3_org_review_' . $request->ID, 'mvm_org_review_nonce', true, false ) . '<button class="mvmh3-primary" type="submit">Goedkeuren</button></form><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mvm_hubs_v3_org_review"><input type="hidden" name="request_id" value="' . (int) $request->ID . '"><input type="hidden" name="decision" value="reject">' . wp_nonce_field( 'mvm_hubs_v3_org_review_' . $request->ID, 'mvm_org_review_nonce', true, false ) . $reason_options . '<button class="mvmh3-vacancy-remove" type="submit">Afwijzen</button></form></div></article>';
        }
    }
    return $html . '</div>';
}

