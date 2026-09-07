<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Concrete session boundary for communications mutations.
 *
 * Step-up is fail-closed by default. A dedicated authentication component may
 * authorize a specific action through the filter after verifying fresh user
 * authentication. Navigation/capabilities alone can never satisfy step-up.
 */
final class Default_Communications_Action_Security implements Communications_Action_Security {
    public function authorize_session( int $user_id ): true|\WP_Error {
        if ( $user_id <= 0 || ! is_user_logged_in() || $user_id !== get_current_user_id() ) {
            return new \WP_Error( 'mvm_communications_session', 'Je sessie is niet geldig voor deze actie.' );
        }

        if ( function_exists( 'wp_get_session_token' ) && '' === (string) wp_get_session_token() ) {
            return new \WP_Error( 'mvm_communications_session', 'Je sessie is niet geldig voor deze actie.' );
        }

        return true;
    }

    public function authorize_step_up( int $user_id, string $action ): true|\WP_Error {
        $session = $this->authorize_session( $user_id );
        if ( is_wp_error( $session ) ) {
            return $session;
        }

        $action = sanitize_key( $action );
        $allowed = apply_filters( 'mvm_hub_communications_step_up_authorized_v1', false, $user_id, $action );
        return true === $allowed
            ? true
            : new \WP_Error( 'mvm_communications_step_up_required', 'Bevestig je identiteit opnieuw voor deze gevoelige actie.' );
    }
}
