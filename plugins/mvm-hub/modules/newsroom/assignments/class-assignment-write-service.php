<?php

namespace MVM\Hub\Modules\Newsroom\Assignments;

use MVM\Hub\Core\Audit;
use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Object_Access;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Writes to the existing Hub4 assignment table without changing its schema. */
final class Assignment_Write_Service {
    private const TYPES = array( 'news', 'photo', 'event', 'source', 'general' );

    /** @return array<string,mixed>|\WP_Error */
    public function create( array $input ): array|\WP_Error {
        global $wpdb;

        if ( ! current_user_can( 'manage_options' )
            && ! current_user_can( Capabilities::ASSIGNMENTS_MANAGE )
            && ! current_user_can( Capabilities::NEWS_CREATE ) ) {
            return new \WP_Error( 'mvm_assignment_create_forbidden', 'Je mag geen opdracht aanmaken.', array( 'status' => 403 ) );
        }

        $title = mb_substr( trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) ), 0, 190 );
        if ( '' === $title ) {
            return new \WP_Error( 'mvm_assignment_title', 'Vul een duidelijke opdrachttitel in.', array( 'status' => 400 ) );
        }

        $manager = current_user_can( 'manage_options' ) || current_user_can( Capabilities::ASSIGNMENTS_MANAGE );
        $state   = sanitize_key( (string) ( $input['state'] ?? 'new' ) );
        if ( ! in_array( $state, array( 'new', 'assigned' ), true ) || ( 'assigned' === $state && ! $manager ) ) {
            $state = 'new';
        }

        $assignee = $manager ? absint( $input['assigneeUserId'] ?? get_current_user_id() ) : get_current_user_id();
        $assignee_check = $this->validate_assignee( $assignee );
        if ( is_wp_error( $assignee_check ) ) {
            return $assignee_check;
        }

        $source_id = $this->related_post_id( $input['sourcePostId'] ?? 0, 'mvm_bron', 'bron' );
        if ( is_wp_error( $source_id ) ) {
            return $source_id;
        }
        $event_id = $this->related_post_id( $input['eventPostId'] ?? 0, 'event_listing', 'evenement' );
        if ( is_wp_error( $event_id ) ) {
            return $event_id;
        }
        $news_id = $this->related_post_id( $input['newsPostId'] ?? 0, 'post', 'nieuwsartikel' );
        if ( is_wp_error( $news_id ) ) {
            return $news_id;
        }

        $type = sanitize_key( (string) ( $input['type'] ?? 'news' ) );
        if ( ! in_array( $type, self::TYPES, true ) ) {
            $type = 'general';
        }
        $now = current_time( 'mysql', true );
        $data = array(
            'type'               => $type,
            'status'             => self::legacy_status( $state ),
            'priority'           => $manager ? min( 4, max( 1, absint( $input['priority'] ?? 2 ) ) ) : 2,
            'title'              => $title,
            'brief'              => mb_substr( sanitize_textarea_field( (string) ( $input['brief'] ?? '' ) ), 0, 8000 ),
            'source_post_id'     => $source_id,
            'event_post_id'      => $event_id,
            'news_post_id'       => $news_id,
            'assignee_user_id'   => $assignee,
            'created_by_user_id' => get_current_user_id(),
            'due_at_utc'         => $manager ? self::datetime( $input['dueAtUtc'] ?? null ) : null,
            'created_at_utc'     => $now,
            'updated_at_utc'     => $now,
            'completed_at_utc'   => null,
        );

        $ok = $wpdb->insert(
            self::table(),
            $data,
            array( '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
        );
        if ( false === $ok ) {
            Audit::record( 'assignment.create', 'error', 'assignment', 0, array( 'type' => $type ) );
            return new \WP_Error( 'mvm_assignment_create_failed', 'De opdracht kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }

        $id = (int) $wpdb->insert_id;
        Audit::record( 'assignment.create', 'success', 'assignment', $id, array( 'type' => $type, 'state' => $state, 'assignee_user_id' => $assignee ) );
        return $this->get( $id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function update( int $id, array $input ): array|\WP_Error {
        global $wpdb;

        $row = self::raw( $id );
        if ( ! is_array( $row ) ) {
            return new \WP_Error( 'mvm_assignment_missing', 'Deze opdracht bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! Object_Access::can_update_assignment( $row ) ) {
            Audit::record( 'assignment.update', 'denied', 'assignment', $id );
            return new \WP_Error( 'mvm_assignment_forbidden', 'Je mag deze opdracht niet wijzigen.', array( 'status' => 403 ) );
        }

        $manager = current_user_can( 'manage_options' ) || current_user_can( Capabilities::ASSIGNMENTS_MANAGE );
        $changes = array( 'updated_at_utc' => current_time( 'mysql', true ) );
        $formats = array( '%s' );

        if ( $manager ) {
            if ( array_key_exists( 'title', $input ) ) {
                $title = mb_substr( trim( sanitize_text_field( (string) $input['title'] ) ), 0, 190 );
                if ( '' === $title ) {
                    return new \WP_Error( 'mvm_assignment_title', 'De opdrachttitel mag niet leeg zijn.', array( 'status' => 400 ) );
                }
                $changes['title'] = $title;
                $formats[] = '%s';
            }
            if ( array_key_exists( 'brief', $input ) ) {
                $changes['brief'] = mb_substr( sanitize_textarea_field( (string) $input['brief'] ), 0, 8000 );
                $formats[] = '%s';
            }
            if ( array_key_exists( 'assigneeUserId', $input ) ) {
                $assignee = absint( $input['assigneeUserId'] );
                $check = $this->validate_assignee( $assignee );
                if ( is_wp_error( $check ) ) {
                    return $check;
                }
                $changes['assignee_user_id'] = $assignee;
                $formats[] = '%d';
            }
            if ( array_key_exists( 'priority', $input ) ) {
                $changes['priority'] = min( 4, max( 1, absint( $input['priority'] ) ) );
                $formats[] = '%d';
            }
            if ( array_key_exists( 'dueAtUtc', $input ) ) {
                $changes['due_at_utc'] = self::datetime( $input['dueAtUtc'] );
                $formats[] = '%s';
            }
        }

        $from_state = Assignment_Workflow::from_legacy_status( (string) $row['status'] );
        $to_state   = $from_state;
        if ( array_key_exists( 'state', $input ) ) {
            $candidate = sanitize_key( (string) $input['state'] );
            if ( null === $from_state || ! Assignment_Workflow::can_transition( $from_state, $candidate ) ) {
                return new \WP_Error( 'mvm_assignment_transition', 'Deze statusstap is niet toegestaan.', array( 'status' => 403 ) );
            }
            $to_state = $candidate;
            $changes['status'] = self::legacy_status( $candidate );
            $formats[] = '%s';
            if ( in_array( $candidate, array( 'completed', 'cancelled' ), true ) ) {
                $changes['completed_at_utc'] = current_time( 'mysql', true );
                $formats[] = '%s';
            } elseif ( array_key_exists( 'completed_at_utc', $row ) && ! empty( $row['completed_at_utc'] ) ) {
                $changes['completed_at_utc'] = null;
                $formats[] = '%s';
            }
        }

        if ( 1 === count( $changes ) && ! array_key_exists( 'state', $input ) ) {
            return $this->get( $id );
        }

        $ok = $wpdb->update( self::table(), $changes, array( 'id' => $id ), $formats, array( '%d' ) );
        if ( false === $ok ) {
            Audit::record( 'assignment.update', 'error', 'assignment', $id, array(), (string) $from_state, (string) $to_state );
            return new \WP_Error( 'mvm_assignment_update_failed', 'De opdracht kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        }

        Audit::record( 'assignment.update', 'success', 'assignment', $id, array( 'changed_fields' => count( $changes ) - 1 ), (string) $from_state, (string) $to_state );
        return $this->get( $id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function get( int $id ): array|\WP_Error {
        $row = self::raw( $id );
        if ( ! is_array( $row ) ) {
            return new \WP_Error( 'mvm_assignment_missing', 'Deze opdracht bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! Object_Access::can_read_assignment( $row ) ) {
            return new \WP_Error( 'mvm_assignment_forbidden', 'Je mag deze opdracht niet bekijken.', array( 'status' => 403 ) );
        }
        return array(
            'id'              => (int) $row['id'],
            'type'            => sanitize_key( (string) $row['type'] ),
            'state'           => Assignment_Workflow::from_legacy_status( (string) $row['status'] ),
            'legacyStatus'    => sanitize_key( (string) $row['status'] ),
            'priority'        => (int) $row['priority'],
            'title'           => sanitize_text_field( (string) $row['title'] ),
            'brief'           => sanitize_textarea_field( (string) $row['brief'] ),
            'sourcePostId'    => (int) $row['source_post_id'],
            'eventPostId'     => (int) $row['event_post_id'],
            'newsPostId'      => (int) $row['news_post_id'],
            'assigneeUserId'  => (int) $row['assignee_user_id'],
            'createdByUserId' => (int) $row['created_by_user_id'],
            'dueAtUtc'        => $row['due_at_utc'] ?: null,
            'updatedAtUtc'    => $row['updated_at_utc'] ?: null,
        );
    }

    private function validate_assignee( int $user_id ): true|\WP_Error {
        $user = get_userdata( $user_id );
        if ( ! $user instanceof \WP_User ) {
            return new \WP_Error( 'mvm_assignment_assignee', 'De gekozen medewerker bestaat niet.', array( 'status' => 400 ) );
        }
        if ( ! user_can( $user, 'manage_options' ) && ! user_can( $user, Capabilities::NEWSROOM_ACCESS ) ) {
            return new \WP_Error( 'mvm_assignment_assignee_capability', 'De gekozen medewerker heeft geen Nieuwsroomtoegang.', array( 'status' => 400 ) );
        }
        return true;
    }

    /** @return int|\WP_Error */
    private function related_post_id( mixed $value, string $expected_type, string $label ): int|\WP_Error {
        $id = absint( $value );
        if ( 0 === $id ) {
            return 0;
        }
        $post = get_post( $id );
        if ( ! $post instanceof \WP_Post || $expected_type !== $post->post_type ) {
            return new \WP_Error( 'mvm_assignment_relation', 'De gekoppelde ' . $label . ' is ongeldig.', array( 'status' => 400 ) );
        }
        return $id;
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'mvm_hub4_assignments';
    }

    /** @return array<string,mixed>|null */
    private static function raw( int $id ): ?array {
        global $wpdb;
        $table = self::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal fixed table.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    private static function legacy_status( string $state ): string {
        return match ( sanitize_key( $state ) ) {
            'new'         => 'signal',
            'assigned'    => 'assigned',
            'in_progress' => 'in_progress',
            'review'      => 'review',
            'completed'   => 'ready',
            'cancelled'   => 'cancelled',
            default       => 'signal',
        };
    }

    private static function datetime( mixed $value ): ?string {
        if ( null === $value || '' === trim( (string) $value ) ) {
            return null;
        }
        try {
            $date = new \DateTimeImmutable( (string) $value, new \DateTimeZone( 'UTC' ) );
            return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
        } catch ( \Throwable ) {
            return null;
        }
    }
}
