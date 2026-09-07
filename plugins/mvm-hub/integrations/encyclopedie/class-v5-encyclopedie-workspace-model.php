<?php

namespace MVM\Hub\Integrations\Encyclopedie;

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\My_Work_Read_Model;
use MVM\Hub\Core\Work_Item_Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant read-only V5 Encyclopedie workspace projection.
 *
 * The Encyclopedie plugin remains canonical owner. This model accepts bounded
 * provider output only; it never queries plugin tables or mutates entities.
 */
final class V5_Encyclopedie_Workspace_Model {
    /**
     * @param array<string,mixed> $provider_payload
     * @param callable(array<string,mixed>):(bool|array<string,mixed>) $authorize
     * @return array<string,mixed>
     */
    public static function build(
        array $provider_payload,
        callable $authorize,
        ?\DateTimeImmutable $now = null,
        int $limit = 100
    ): array {
        $production_version = sanitize_text_field( (string) ( $provider_payload['productionVersion'] ?? '' ) );
        $repository_version = sanitize_text_field( (string) ( $provider_payload['repositoryVersion'] ?? '' ) );
        $parity_matched = '' !== $production_version
            && '' !== $repository_version
            && $production_version === $repository_version;

        $candidates = array();
        $issues = is_array( $provider_payload['maintenance'] ?? null )
            ? $provider_payload['maintenance']
            : array();

        foreach ( $issues as $issue ) {
            if ( ! is_array( $issue ) ) {
                continue;
            }

            $candidate = self::issue_to_work_item( $issue );
            if ( null !== $candidate ) {
                $candidates[] = $candidate;
            }
        }

        $items = My_Work_Read_Model::build( $candidates, $authorize, $now, $limit );
        $counts = array(
            'total'  => count( $items ),
            'urgent' => 0,
            'high'   => 0,
            'normal' => 0,
            'low'    => 0,
            'blocked'=> 0,
        );

        foreach ( $items as $item ) {
            $priority = sanitize_key( (string) ( $item['priority'] ?? '' ) );
            if ( isset( $counts[ $priority ] ) ) {
                ++$counts[ $priority ];
            }
            if ( Work_Item_Schema::STATUS_BLOCKED === sanitize_key( (string) ( $item['status'] ?? '' ) ) ) {
                ++$counts['blocked'];
            }
        }

        return array(
            'workspace' => 'encyclopedie',
            'owner'     => 'mvm-encyclopedie-next',
            'readOnly'  => true,
            'providerAvailable' => true === ( $provider_payload['available'] ?? false ),
            'parity'    => array(
                'productionVersion'     => $production_version,
                'repositoryVersion'     => $repository_version,
                'matched'               => $parity_matched,
                'hardDependencyAllowed' => $parity_matched,
            ),
            'counts'    => $counts,
            'items'     => $items,
            'routeOwnership'      => false,
            'productionActivated' => false,
        );
    }

    /** @param array<string,mixed> $issue @return array<string,mixed>|null */
    private static function issue_to_work_item( array $issue ): ?array {
        $id       = max( 0, (int) ( $issue['id'] ?? 0 ) );
        $title    = sanitize_text_field( (string) ( $issue['title'] ?? '' ) );
        $state    = sanitize_key( (string) ( $issue['status'] ?? 'open' ) );
        $severity = sanitize_key( (string) ( $issue['severity'] ?? 'normal' ) );
        $owner    = max( 0, (int) ( $issue['ownerUserId'] ?? 0 ) );

        if ( 0 === $id || '' === $title ) {
            return null;
        }

        $candidate = array(
            'id'                  => $id,
            'type'                => 'encyclopedie_maintenance',
            'domain'              => 'encyclopedie',
            'object_type'         => sanitize_key( (string) ( $issue['type'] ?? 'integrity_issue' ) ),
            'object_id'           => max( 0, (int) ( $issue['objectId'] ?? $id ) ),
            'title'               => $title,
            'owner_user_id'       => $owner,
            'owner_team'          => $owner > 0 ? '' : 'encyclopedie',
            'priority'            => self::priority( $severity ),
            'status'              => self::work_status( $state ),
            'deadline_utc'        => (string) ( $issue['dueAtUtc'] ?? '' ),
            'workflow_key'        => 'encyclopedie_maintenance',
            'workflow_version'    => 1,
            'workflow_state'      => '' !== $state ? $state : 'open',
            'required_capability' => 'mvm_encyclopedie_view_editorial',
            'dependency_ids'      => array(),
            'blocker_reason'      => 'blocked' === $state
                ? sanitize_textarea_field( (string) ( $issue['blockerReason'] ?? 'Onderhoudsitem is geblokkeerd.' ) )
                : '',
            'checklist'           => array(),
            'classification'      => Data_Classification::normalize(
                (string) ( $issue['classification'] ?? Data_Classification::INTERNAL )
            ),
        );

        $normalized = Work_Item_Schema::normalize( $candidate );
        if ( null === $normalized ) {
            return null;
        }
        $normalized['id'] = $id;
        return $normalized;
    }

    private static function priority( string $severity ): string {
        return match ( sanitize_key( $severity ) ) {
            'critical', 'urgent' => Work_Item_Schema::PRIORITY_URGENT,
            'high', 'error'      => Work_Item_Schema::PRIORITY_HIGH,
            'low', 'info'        => Work_Item_Schema::PRIORITY_LOW,
            default              => Work_Item_Schema::PRIORITY_NORMAL,
        };
    }

    private static function work_status( string $state ): string {
        return match ( sanitize_key( $state ) ) {
            'resolved', 'closed', 'done' => Work_Item_Schema::STATUS_DONE,
            'cancelled', 'canceled'      => Work_Item_Schema::STATUS_CANCELLED,
            'blocked'                    => Work_Item_Schema::STATUS_BLOCKED,
            'in_progress', 'review'      => Work_Item_Schema::STATUS_IN_PROGRESS,
            default                      => Work_Item_Schema::STATUS_OPEN,
        };
    }

    private function __construct() {}
}
