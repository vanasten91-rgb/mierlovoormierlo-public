<?php

namespace MVM\Hub\Modules\Intake;

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\My_Work_Read_Model;
use MVM\Hub\Core\Work_Item_Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant read-only Intake projection for Hub V5.
 *
 * This model deliberately accepts only bounded case metadata. It never accepts
 * or returns message bodies, contact details, attachment paths or other source
 * material. Protected/secret cases additionally require an explicit ACL marker
 * before they are even offered to the shared authorization layer.
 */
final class V5_Intake_Preview_Model {
    /**
     * @param list<array<string,mixed>> $cases
     * @param callable(array<string,mixed>):(bool|array<string,mixed>) $authorize
     * @return array<string,mixed>
     */
    public static function build(
        array $cases,
        callable $authorize,
        ?\DateTimeImmutable $now = null,
        int $limit = 100
    ): array {
        $candidates = array();

        foreach ( $cases as $case ) {
            if ( ! is_array( $case ) ) {
                continue;
            }

            $candidate = self::case_to_work_item( $case );
            if ( null !== $candidate ) {
                $candidates[] = $candidate;
            }
        }

        $items = My_Work_Read_Model::build( $candidates, $authorize, $now, $limit );
        $counts = array(
            'total'     => count( $items ),
            'protected' => 0,
            'urgent'    => 0,
            'blocked'   => 0,
        );

        foreach ( $items as $item ) {
            if ( in_array(
                (string) ( $item['classification'] ?? '' ),
                array( Data_Classification::SOURCE_PROTECTED, Data_Classification::SECRET ),
                true
            ) ) {
                ++$counts['protected'];
            }
            if ( Work_Item_Schema::PRIORITY_URGENT === (string) ( $item['priority'] ?? '' ) ) {
                ++$counts['urgent'];
            }
            if ( Work_Item_Schema::STATUS_BLOCKED === (string) ( $item['status'] ?? '' ) ) {
                ++$counts['blocked'];
            }
        }

        return array(
            'workspace' => 'intake',
            'counts'    => $counts,
            'items'     => $items,
            'readOnly'  => true,
            'sensitivePayloadIncluded' => false,
            'notificationPayloadPolicy' => 'case-id-and-hub-link-only',
            'providerOwnership' => 'caller-supplied-read-model',
            'routeOwnership'      => false,
            'productionActivated' => false,
        );
    }

    /** @param array<string,mixed> $case @return array<string,mixed>|null */
    private static function case_to_work_item( array $case ): ?array {
        $id = max( 0, (int) ( $case['id'] ?? 0 ) );
        if ( 0 === $id ) {
            return null;
        }

        $classification = Data_Classification::normalize(
            (string) ( $case['classification'] ?? Data_Classification::CONFIDENTIAL )
        );
        $protected = in_array(
            $classification,
            array( Data_Classification::SOURCE_PROTECTED, Data_Classification::SECRET ),
            true
        );

        if ( $protected && true !== ( $case['explicitAclGranted'] ?? false ) ) {
            return null;
        }

        $state = sanitize_key( (string) ( $case['status'] ?? 'new' ) );
        $owner = max( 0, (int) ( $case['ownerUserId'] ?? 0 ) );
        $title = self::safe_title( $case, $id, $classification );
        if ( '' === $title ) {
            return null;
        }

        $candidate = array(
            'id'                  => $id,
            'type'                => 'intake_case',
            'domain'              => 'intake',
            'object_type'         => 'intake_case',
            'object_id'           => $id,
            'title'               => $title,
            'owner_user_id'       => $owner,
            'owner_team'          => $owner > 0 ? '' : 'intake',
            'priority'            => self::priority( sanitize_key( (string) ( $case['priority'] ?? 'normal' ) ) ),
            'status'              => self::work_status( $state ),
            'deadline_utc'        => (string) ( $case['dueAtUtc'] ?? '' ),
            'workflow_key'        => $protected ? 'protected_intake' : 'intake_triage',
            'workflow_version'    => 1,
            'workflow_state'      => '' !== $state ? $state : 'new',
            'required_capability' => 'mvm_intake_view',
            'dependency_ids'      => array(),
            'blocker_reason'      => 'blocked' === $state
                ? sanitize_textarea_field( (string) ( $case['blockerReason'] ?? 'Intakecase is geblokkeerd.' ) )
                : '',
            'checklist'           => array(),
            'classification'      => $classification,
        );

        $normalized = Work_Item_Schema::normalize( $candidate );
        if ( null === $normalized ) {
            return null;
        }

        $normalized['id'] = $id;
        return $normalized;
    }

    /** @param array<string,mixed> $case */
    private static function safe_title( array $case, int $id, string $classification ): string {
        if ( in_array( $classification, array( Data_Classification::SOURCE_PROTECTED, Data_Classification::SECRET ), true ) ) {
            return 'Beschermde tip #' . $id;
        }
        if ( Data_Classification::CONFIDENTIAL === $classification ) {
            $safe = sanitize_text_field( (string) ( $case['safeTitle'] ?? '' ) );
            return '' !== $safe ? $safe : 'Vertrouwelijke intake #' . $id;
        }

        return sanitize_text_field( (string) ( $case['safeTitle'] ?? $case['title'] ?? '' ) );
    }

    private static function priority( string $priority ): string {
        return match ( sanitize_key( $priority ) ) {
            'urgent', 'critical' => Work_Item_Schema::PRIORITY_URGENT,
            'high'               => Work_Item_Schema::PRIORITY_HIGH,
            'low'                => Work_Item_Schema::PRIORITY_LOW,
            default              => Work_Item_Schema::PRIORITY_NORMAL,
        };
    }

    private static function work_status( string $state ): string {
        return match ( sanitize_key( $state ) ) {
            'closed', 'resolved', 'done' => Work_Item_Schema::STATUS_DONE,
            'cancelled', 'canceled'      => Work_Item_Schema::STATUS_CANCELLED,
            'blocked'                    => Work_Item_Schema::STATUS_BLOCKED,
            'assigned', 'in_progress', 'review' => Work_Item_Schema::STATUS_IN_PROGRESS,
            default                      => Work_Item_Schema::STATUS_OPEN,
        };
    }

    private function __construct() {}
}
