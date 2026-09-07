<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Hub_Security_Policy;
use MVM\Hub\Integrations\Mail\Communications_Action_Security;
use MVM\Hub\Integrations\Mail\Communications_Audit;
use MVM\Hub\Integrations\Mail\Mail_Provider;
use MVM\Hub\Integrations\Mail\Mailbox_Access;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Future folder/message-state mutation service.
 *
 * No REST route is registered during parallel migration. The current legacy
 * provider stays read-only, so these calls fail closed until a secured provider
 * implementation is released.
 */
final class Mail_Folder_Service {
    public function __construct(
        private readonly Mail_Provider $provider,
        private readonly Mailbox_Access $access,
        private readonly Communications_Action_Security $security,
        private readonly Communications_Audit $audit
    ) {}

    /** @return array<string,mixed>|\WP_Error */
    public function create_folder( string $mailbox_id, string $name, ?string $parent_id = null ): array|\WP_Error {
        $context = $this->authorize_folder_management( $mailbox_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }

        $name = self::folder_name( $name );
        if ( '' === $name ) {
            return new \WP_Error( 'mvm_mail_folder_name', 'Voer een geldige mapnaam in.' );
        }

        $parent_id = null === $parent_id ? null : self::opaque_id( $parent_id );
        if ( null !== $parent_id && '' === $parent_id ) {
            return new \WP_Error( 'mvm_mail_folder_parent', 'Ongeldige bovenliggende map.' );
        }

        $result = $this->provider->create_folder( $context['mailboxId'], $name, $parent_id );
        $this->audit_result( 'mail.folder.create', $result, $context['userId'], $context['mailboxId'] );
        return $result;
    }

    public function rename_folder( string $mailbox_id, string $folder_id, string $name ): true|\WP_Error {
        $context = $this->authorize_folder_management( $mailbox_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }

        $folder_id = self::opaque_id( $folder_id );
        $name      = self::folder_name( $name );
        if ( '' === $folder_id || '' === $name ) {
            return new \WP_Error( 'mvm_mail_folder_invalid', 'Ongeldige map of mapnaam.' );
        }

        $folder = $this->folder( $context['mailboxId'], $folder_id );
        if ( is_wp_error( $folder ) ) {
            return $folder;
        }
        if ( self::protected_folder( $folder ) ) {
            return new \WP_Error( 'mvm_mail_folder_protected', 'Deze systeemmap kan niet worden hernoemd.' );
        }

        $result = $this->provider->rename_folder( $context['mailboxId'], $folder_id, $name );
        $this->audit_result( 'mail.folder.rename', $result, $context['userId'], $folder_id );
        return is_wp_error( $result ) ? $result : true;
    }

    public function delete_folder( string $mailbox_id, string $folder_id ): true|\WP_Error {
        $context = $this->authorize_folder_management( $mailbox_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }

        if ( ! Hub_Security_Policy::requires_step_up( 'mail_folder_delete' ) ) {
            return new \WP_Error( 'mvm_mail_step_up_policy', 'De beveiligingspolicy voor mapverwijdering is ongeldig.' );
        }
        $step_up = $this->security->authorize_step_up( $context['userId'], 'mail_folder_delete' );
        if ( is_wp_error( $step_up ) ) {
            return $step_up;
        }

        $folder_id = self::opaque_id( $folder_id );
        if ( '' === $folder_id ) {
            return new \WP_Error( 'mvm_mail_folder_invalid', 'Ongeldige map.' );
        }

        $folder = $this->folder( $context['mailboxId'], $folder_id );
        if ( is_wp_error( $folder ) ) {
            return $folder;
        }
        if ( self::protected_folder( $folder ) ) {
            return new \WP_Error( 'mvm_mail_folder_protected', 'Deze systeemmap kan niet worden verwijderd.' );
        }

        $result = $this->provider->delete_folder( $context['mailboxId'], $folder_id );
        $this->audit_result( 'mail.folder.delete', $result, $context['userId'], $folder_id );
        return is_wp_error( $result ) ? $result : true;
    }

    public function move_message( string $mailbox_id, string $message_id, string $folder_id ): true|\WP_Error {
        $context = $this->authorize_message_action( $mailbox_id, $message_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }

        $folder_id = self::opaque_id( $folder_id );
        if ( '' === $folder_id || is_wp_error( $this->folder( $context['mailboxId'], $folder_id ) ) ) {
            return new \WP_Error( 'mvm_mail_folder_invalid', 'Doelmap bestaat niet of is niet beschikbaar.' );
        }

        $result = $this->provider->move_message( $context['mailboxId'], $context['messageId'], $folder_id );
        $this->audit_result( 'mail.message.move', $result, $context['userId'], $context['messageId'] );
        return is_wp_error( $result ) ? $result : true;
    }

    public function move_message_to_special( string $mailbox_id, string $message_id, string $special_use ): true|\WP_Error {
        $context = $this->authorize_message_action( $mailbox_id, $message_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }

        $special_use = sanitize_key( $special_use );
        if ( ! in_array( $special_use, array( 'archive', 'trash', 'junk' ), true ) ) {
            return new \WP_Error( 'mvm_mail_special_action', 'Deze mailactie is niet toegestaan.' );
        }
        $target = $this->special_folder( $context['mailboxId'], $special_use );
        if ( is_wp_error( $target ) ) {
            return $target;
        }
        if ( str_starts_with( $context['messageId'], $target . '.' ) ) {
            return true;
        }

        $result = $this->provider->move_message( $context['mailboxId'], $context['messageId'], $target );
        $event = array( 'archive' => 'archive', 'trash' => 'trash', 'junk' => 'spam' )[ $special_use ];
        $this->audit_result( 'mail.message.' . $event, $result, $context['userId'], $context['messageId'] );
        return is_wp_error( $result ) ? $result : true;
    }

    public function set_read_state( string $mailbox_id, string $message_id, bool $is_read ): true|\WP_Error {
        $context = $this->authorize_message_action( $mailbox_id, $message_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }

        $result = $this->provider->set_read_state( $context['mailboxId'], $context['messageId'], $is_read );
        $this->audit_result( 'mail.message.read_state', $result, $context['userId'], $context['messageId'], array( 'is_read' => $is_read ) );
        return is_wp_error( $result ) ? $result : true;
    }

    public function set_flagged_state( string $mailbox_id, string $message_id, bool $is_flagged ): true|\WP_Error {
        $context = $this->authorize_message_action( $mailbox_id, $message_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }

        $result = $this->provider->set_flagged_state( $context['mailboxId'], $context['messageId'], $is_flagged );
        $this->audit_result( 'mail.message.flag_state', $result, $context['userId'], $context['messageId'], array( 'is_flagged' => $is_flagged ) );
        return is_wp_error( $result ) ? $result : true;
    }

    public function set_pinned_state( string $mailbox_id, string $message_id, bool $is_pinned ): true|\WP_Error {
        $context = $this->authorize_message_action( $mailbox_id, $message_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }
        $result = Mail_Message_Preferences::set_pinned( $context['userId'], $context['mailboxId'], $context['messageId'], $is_pinned );
        $this->audit_result( 'mail.message.pin_state', $result, $context['userId'], $context['messageId'], array( 'is_pinned' => $is_pinned ) );
        return $result;
    }

    /** @return array{reported:bool,reason:string}|\WP_Error */
    public function report_message( string $mailbox_id, string $message_id, string $reason ): array|\WP_Error {
        $context = $this->authorize_message_action( $mailbox_id, $message_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }
        $reason = sanitize_key( $reason );
        if ( ! in_array( $reason, array( 'phishing', 'abuse', 'suspicious', 'other' ), true ) ) {
            return new \WP_Error( 'mvm_mail_report_reason', 'Kies een geldige reden voor de melding.' );
        }
        $result = array( 'reported' => true, 'reason' => $reason );
        $this->audit_result( 'mail.message.report', $result, $context['userId'], $context['messageId'], array( 'reason' => $reason ) );
        return $result;
    }

    /** @return array{blocked:bool,movedToSpam:bool,warning:string}|\WP_Error */
    public function set_sender_blocked( string $mailbox_id, string $message_id, bool $blocked ): array|\WP_Error {
        $context = $this->authorize_message_action( $mailbox_id, $message_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }
        $message = $this->provider->get_message( $context['mailboxId'], $context['messageId'] );
        if ( is_wp_error( $message ) ) {
            return $message;
        }
        $sender = sanitize_email( strtolower( trim( (string) ( $message['fromAddress'] ?? '' ) ) ) );
        if ( false === is_email( $sender ) ) {
            return new \WP_Error( 'mvm_mail_sender_invalid', 'De afzender kan niet veilig worden vastgesteld.' );
        }

        $stored = Mail_Sender_Blocklist::set_blocked( $context['mailboxId'], $sender, $blocked );
        if ( is_wp_error( $stored ) ) {
            $this->audit_result( 'mail.sender.block_state', $stored, $context['userId'], $context['messageId'], array( 'blocked' => $blocked ) );
            return $stored;
        }

        $moved = false;
        $warning = '';
        if ( $blocked ) {
            $target = $this->special_folder( $context['mailboxId'], 'junk' );
            if ( is_wp_error( $target ) ) {
                $warning = 'Afzender is geblokkeerd, maar de spammap ontbreekt.';
            } elseif ( str_starts_with( $context['messageId'], $target . '.' ) ) {
                $moved = true;
            } else {
                $move = $this->provider->move_message( $context['mailboxId'], $context['messageId'], $target );
                $moved = ! is_wp_error( $move );
                if ( ! $moved ) {
                    $warning = 'Afzender is geblokkeerd, maar het bericht kon niet naar spam worden verplaatst.';
                }
            }
        }

        $result = array( 'blocked' => $blocked, 'movedToSpam' => $moved, 'warning' => $warning );
        $this->audit_result( 'mail.sender.block_state', $result, $context['userId'], $context['messageId'], array( 'blocked' => $blocked, 'moved_to_spam' => $moved ) );
        return $result;
    }

    /** @return array{userId:int,mailboxId:string}|\WP_Error */
    private function authorize_folder_management( string $mailbox_id ): array|\WP_Error {
        $user_id    = get_current_user_id();
        $mailbox_id = self::opaque_id( $mailbox_id );

        if ( ! is_user_logged_in() || ! Capabilities::can_access_mail() || '' === $mailbox_id ) {
            return new \WP_Error( 'mvm_mail_folder_forbidden', 'Geen toegang tot deze mailbox.' );
        }

        if (
            ! current_user_can( 'manage_options' )
            && ! current_user_can( Capabilities::COMMUNICATIONS_ADMIN )
            && ! current_user_can( Capabilities::MAIL_MANAGE_FOLDERS )
        ) {
            return new \WP_Error( 'mvm_mail_folder_forbidden', 'Je hebt geen toestemming om mappen te beheren.' );
        }

        $session = $this->security->authorize_session( $user_id );
        if ( is_wp_error( $session ) ) {
            return $session;
        }

        $object_access = $this->access->authorize_manage_folders( $user_id, $mailbox_id );
        if ( is_wp_error( $object_access ) ) {
            return $object_access;
        }

        return array( 'userId' => $user_id, 'mailboxId' => $mailbox_id );
    }

    /** @return array{userId:int,mailboxId:string,messageId:string}|\WP_Error */
    private function authorize_message_action( string $mailbox_id, string $message_id ): array|\WP_Error {
        $user_id    = get_current_user_id();
        $mailbox_id = self::opaque_id( $mailbox_id );
        $message_id = self::opaque_id( $message_id );

        if ( ! is_user_logged_in() || ! Capabilities::can_access_mail() || '' === $mailbox_id || '' === $message_id ) {
            return new \WP_Error( 'mvm_mail_message_forbidden', 'Geen toegang tot dit bericht.' );
        }

        if (
            ! current_user_can( 'manage_options' )
            && ! current_user_can( Capabilities::COMMUNICATIONS_ADMIN )
            && ! current_user_can( Capabilities::MAIL_MANAGE_MESSAGES )
        ) {
            return new \WP_Error( 'mvm_mail_message_forbidden', 'Je hebt geen toestemming om berichtstatus of map te wijzigen.' );
        }

        $session = $this->security->authorize_session( $user_id );
        if ( is_wp_error( $session ) ) {
            return $session;
        }

        $mailbox_access = $this->access->authorize_read( $user_id, $mailbox_id );
        if ( is_wp_error( $mailbox_access ) ) {
            return $mailbox_access;
        }

        $object_access = $this->access->authorize_message( $user_id, $mailbox_id, $message_id );
        if ( is_wp_error( $object_access ) ) {
            return $object_access;
        }

        return array( 'userId' => $user_id, 'mailboxId' => $mailbox_id, 'messageId' => $message_id );
    }

    /** @return array<string,mixed>|\WP_Error */
    private function folder( string $mailbox_id, string $folder_id ): array|\WP_Error {
        foreach ( $this->provider->list_folders( $mailbox_id ) as $folder ) {
            if ( is_array( $folder ) && self::opaque_id( (string) ( $folder['id'] ?? '' ) ) === $folder_id ) {
                return $folder;
            }
        }
        return new \WP_Error( 'mvm_mail_folder_missing', 'Map bestaat niet.' );
    }

    /** @return string|\WP_Error */
    private function special_folder( string $mailbox_id, string $special_use ): string|\WP_Error {
        $matches = array();
        foreach ( $this->provider->list_folders( $mailbox_id ) as $folder ) {
            if ( ! is_array( $folder ) || $special_use !== sanitize_key( (string) ( $folder['specialUse'] ?? '' ) ) ) {
                continue;
            }
            $id = self::opaque_id( (string) ( $folder['id'] ?? '' ) );
            if ( '' !== $id ) {
                $matches[] = $id;
            }
        }
        $matches = array_values( array_unique( $matches ) );
        if ( 1 !== count( $matches ) ) {
            $labels = array( 'archive' => 'Archief', 'trash' => 'Prullenbak', 'junk' => 'Spam' );
            return new \WP_Error(
                'mvm_mail_special_folder_unavailable',
                ( $labels[ $special_use ] ?? 'De doelmap' ) . ' is niet eenduidig beschikbaar.'
            );
        }
        return $matches[0];
    }

    /** @param array<string,mixed> $folder */
    private static function protected_folder( array $folder ): bool {
        if ( true === ( $folder['system'] ?? false ) ) {
            return true;
        }

        $special = sanitize_key( (string) ( $folder['specialUse'] ?? '' ) );
        return in_array( $special, array( 'inbox', 'sent', 'drafts', 'trash', 'junk', 'spam' ), true );
    }

    private static function folder_name( string $name ): string {
        $name = trim( sanitize_text_field( str_replace( array( "\r", "\n", "\0" ), ' ', $name ) ) );
        if ( '' === $name || '.' === $name || '..' === $name ) {
            return '';
        }
        return mb_substr( $name, 0, 100 );
    }

    private static function opaque_id( string $value ): string {
        $value = trim( $value );
        return strlen( $value ) <= 128 && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }

    /** @param bool|\WP_Error|array<string,mixed> $result @param array<string,mixed> $context */
    private function audit_result( string $event, bool|\WP_Error|array $result, int $user_id, string $object_id, array $context = array() ): void {
        $this->audit->record(
            $event,
            is_wp_error( $result ) || false === $result ? 'failed' : 'success',
            $user_id,
            self::safe_ref( $object_id ),
            $context
        );
    }

    private static function safe_ref( string $value ): string {
        return '' === $value ? '' : substr( wp_hash( $value, 'mvm_hub_mail_object' ), 0, 24 );
    }
}
