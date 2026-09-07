<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Hub_Security_Policy;
use MVM\Hub\Core\Privacy;
use MVM\Hub\Integrations\Mail\Mail_Delivery_Security;
use MVM\Hub\Integrations\Mail\Mail_Provider;
use MVM\Hub\Integrations\Mail\Private_Attachment_Store;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Future transport orchestration service.
 *
 * There is intentionally no REST/AJAX route for this service during the
 * parallel migration. External delivery also remains blocked by the central
 * Communications_Policy gate enforced by Mail_Delivery_Guard.
 */
final class Mail_Delivery_Service {
    public function __construct(
        private readonly Mail_Provider $provider,
        private readonly Private_Attachment_Store $attachments,
        private readonly Mail_Delivery_Security $security
    ) {}

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>|\WP_Error
     */
    public function deliver( string $mailbox_id, array $input, string $idempotency_key ): array|\WP_Error {
        $guard = Mail_Delivery_Guard::authorize( $idempotency_key );
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }

        $user_id    = get_current_user_id();
        $mailbox_id = sanitize_text_field( $mailbox_id );
        if ( $user_id <= 0 || '' === $mailbox_id ) {
            return new \WP_Error( 'mvm_mail_delivery_context', 'Ongeldige verzendcontext.' );
        }

        foreach (
            array(
                $this->security->authorize_session( $user_id ),
                $this->security->authorize_step_up( $user_id, 'mail_send' ),
                $this->security->authorize_mailbox_send( $user_id, $mailbox_id ),
            ) as $authorization
        ) {
            if ( is_wp_error( $authorization ) ) {
                return $authorization;
            }
        }

        if ( ! Hub_Security_Policy::requires_step_up( 'mail_send' ) ) {
            return new \WP_Error( 'mvm_mail_step_up_policy', 'De beveiligingspolicy voor verzenden is ongeldig.' );
        }

        $message = Mail_Compose_Validator::validate( $input );
        if ( is_wp_error( $message ) ) {
            return $message;
        }

        $provider_capabilities = $this->provider->capabilities( $mailbox_id );
        if ( true !== ( $provider_capabilities['send'] ?? false ) ) {
            return new \WP_Error( 'mvm_mail_transport_read_only', 'Deze mailboxprovider ondersteunt nog geen beveiligde verzending.' );
        }
        if ( true !== ( $provider_capabilities['sentArchive'] ?? false ) ) {
            return new \WP_Error( 'mvm_mail_sent_archive_unavailable', 'Verzenden is geblokkeerd omdat de map Verzonden niet veilig beschikbaar is.' );
        }

        $claim = $this->security->claim_idempotency( $user_id, $mailbox_id, $idempotency_key );
        if ( is_wp_error( $claim ) ) {
            return $claim;
        }

        $rate = $this->security->consume_rate_limit( $user_id, $mailbox_id, (int) $message['recipientCount'] );
        if ( is_wp_error( $rate ) ) {
            $this->security->finish_idempotency( $user_id, $mailbox_id, $idempotency_key, 'rejected' );
            $this->audit( 'mail.delivery', 'rate_limited', $mailbox_id, $message );
            return $rate;
        }

        $draft_id = (string) $message['draftId'];
        if ( ! empty( $message['attachmentIds'] ) && '' === $draft_id ) {
            $this->security->finish_idempotency( $user_id, $mailbox_id, $idempotency_key, 'rejected' );
            $this->audit( 'mail.delivery', 'attachment_denied', $mailbox_id, $message );
            return new \WP_Error( 'mvm_mail_draft_required', 'Bijlagen moeten aan een beveiligd concept gekoppeld zijn.' );
        }

        $resolved_attachments = array();
        if ( ! empty( $message['attachmentIds'] ) ) {
            $resolved_attachments = $this->attachments->authorize_for_send(
                $user_id,
                $draft_id,
                (array) $message['attachmentIds']
            );
            if ( is_wp_error( $resolved_attachments ) ) {
                $this->security->finish_idempotency( $user_id, $mailbox_id, $idempotency_key, 'rejected' );
                $this->audit( 'mail.delivery', 'attachment_denied', $mailbox_id, $message );
                return $resolved_attachments;
            }
        }

        $transport_message = array(
            'mode'        => $message['mode'],
            'to'          => $message['to'],
            'cc'          => $message['cc'],
            'bcc'         => $message['bcc'],
            'subject'     => $message['subject'],
            'htmlBody'    => $message['htmlBody'],
            'textBody'    => $message['textBody'],
            'inReplyTo'   => $message['inReplyTo'],
            'references'  => $message['references'],
            'attachments' => $resolved_attachments,
        );

        $this->audit( 'mail.delivery', 'attempt', $mailbox_id, $message );
        $result = $this->provider->deliver( $mailbox_id, $transport_message, $idempotency_key );

        if ( is_wp_error( $result ) ) {
            // Conservative duplicate prevention: a failed/unknown transport state
            // remains terminal for this idempotency key. A later provider may
            // expose reconciliation before allowing a new delivery attempt.
            $this->security->finish_idempotency( $user_id, $mailbox_id, $idempotency_key, 'failed' );
            $this->audit( 'mail.delivery', 'failed', $mailbox_id, $message );
            return $result;
        }

        $sent_archived = true === ( $result['sentArchived'] ?? false );
        $this->security->finish_idempotency(
            $user_id,
            $mailbox_id,
            $idempotency_key,
            $sent_archived ? 'delivered' : 'delivered_unarchived'
        );
        $this->audit( 'mail.delivery', 'success', $mailbox_id, $message );
        if ( ! $sent_archived ) {
            // SMTP is an irreversible external side effect. Never report this as
            // a retryable delivery failure: the terminal idempotency state above
            // prevents duplicate mail while the missing Sent copy is visible.
            $this->audit( 'mail.sent_archive', 'failed', $mailbox_id, $message );
        }

        return array(
            'delivered'         => true,
            'provider'          => sanitize_key( (string) ( $result['provider'] ?? 'mail' ) ),
            'messageId'         => sanitize_text_field( (string) ( $result['messageId'] ?? '' ) ),
            'sentArchived'      => $sent_archived,
            'sentArchiveStatus' => sanitize_key( (string) ( $result['sentArchiveStatus'] ?? ( $sent_archived ? 'archived' : 'failed' ) ) ),
        );
    }

    /** @param array<string,mixed> $message */
    private function audit( string $event, string $result, string $mailbox_id, array $message ): void {
        // Data minimization first, redaction second. Subjects, addresses, message
        // bodies, filenames and provider credentials never enter the audit input.
        $context = Privacy::redact_for_audit(
            array(
                'mailbox_ref'      => substr( wp_hash( $mailbox_id, 'mvm_hub_mailbox' ), 0, 24 ),
                'mode'             => sanitize_key( (string) ( $message['mode'] ?? '' ) ),
                'recipient_count'  => (int) ( $message['recipientCount'] ?? 0 ),
                'attachment_count' => count( (array) ( $message['attachmentIds'] ?? array() ) ),
            )
        );

        $this->security->audit( $event, $result, $context );
    }
}
