<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Session and step-up authorization for non-delivery communications mutations.
 */
interface Communications_Action_Security {
    public function authorize_session( int $user_id ): true|\WP_Error;

    public function authorize_step_up( int $user_id, string $action ): true|\WP_Error;
}
