<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Application service for durable V5 work items.
 *
 * The service owns workflow coordination only. Canonical news/event/entity/mail
 * data remains with the corresponding domain owner. Every mutation is executed
 * through the repository transaction boundary and uses optimistic concurrency.
 */
final class Work_Item_Service {
    public function __construct(
        private readonly Work_Item_Repository $repository
    ) {}

    /** @return array<string,mixed>|\WP_Error */
    public function create( array $candidate, int $actor_user_id ): array|\WP_Error {
        if ( $actor_user_id < 1 ) {
            return new \WP_Error( 'mvm_work_actor_invalid', 'Ongeldige actor.', array( 'status' => 400 ) );
        }

        $normalized = Work_Item_Schema::normalize( $candidate );
        if ( null === $normalized ) {
            return new \WP_Error( 'mvm_work_invalid', 'Het werkitem is ongeldig.', array( 'status' => 400 ) );
        }

        $workflow = Workflow_Registry::get( (string) $normalized['workflow_key'] );
        if ( null === $workflow || (int) $workflow['version'] !== (int) $normalized['workflow_version'] ) {
            return new \WP_Error( 'mvm_workflow_unknown', 'De workflowversie is niet beschikbaar.', array( 'status' => 409 ) );
        }
        if ( ! Workflow_Registry::is_known_state( (string) $normalized['workflow_key'], (string) $normalized['workflow_state'] ) ) {
            return new \WP_Error( 'mvm_workflow_state_invalid', 'De workflowstatus is ongeldig.', array( 'status' => 409 ) );
        }

        $dependencies = (array) $normalized['dependency_ids'];
        $checklist    = (array) $normalized['checklist'];
        unset( $normalized['dependency_ids'], $normalized['checklist'] );
        $normalized['created_by_user_id'] = $actor_user_id;

        return $this->repository->transaction( function () use ( $normalized, $dependencies, $checklist ) {
            $created = $this->repository->create( $normalized );
            if ( is_wp_error( $created ) ) {
                return $created;
            }

            $id = (int) ( $created['id'] ?? 0 );
            if ( $id < 1 ) {
                return new \WP_Error( 'mvm_work_create_invalid', 'Opslag leverde geen geldig werkitem op.', array( 'status' => 500 ) );
            }

            $dep_result = $this->repository->replace_dependencies( $id, $dependencies );
            if ( is_wp_error( $dep_result ) ) {
                return $dep_result;
            }

            $check_result = $this->repository->replace_checklist( $id, $checklist );
            if ( is_wp_error( $check_result ) ) {
                return $check_result;
            }

            $created['dependency_ids'] = $dependencies;
            $created['checklist'] = $checklist;
            return $created;
        } );
    }

    /**
     * @param callable $authorize fn(array $transition, array $item, int $actor): bool|array
     * @param callable|null $object_check fn(array $item, int $actor): bool|array
     * @return array<string,mixed>|\WP_Error
     */
    public function transition(
        int $id,
        string $to_state,
        int $actor_user_id,
        int $expected_version,
        string $request_id,
        callable $authorize,
        ?callable $object_check = null,
        string $reason_code = ''
    ): array|\WP_Error {
        if ( $id < 1 || $actor_user_id < 1 || $expected_version < 1 ) {
            return new \WP_Error( 'mvm_work_transition_invalid', 'Ongeldige transitie-aanvraag.', array( 'status' => 400 ) );
        }

        $to_state   = sanitize_key( $to_state );
        $request_id = sanitize_key( $request_id );
        $reason_code = sanitize_key( $reason_code );
        if ( '' === $to_state || '' === $request_id || strlen( $request_id ) > 96 ) {
            return new \WP_Error( 'mvm_work_transition_invalid', 'Ongeldige transitie-identiteit.', array( 'status' => 400 ) );
        }

        return $this->repository->transaction( function () use ( $id, $to_state, $actor_user_id, $expected_version, $request_id, $authorize, $object_check, $reason_code ) {
            $item = $this->repository->get( $id );
            if ( null === $item ) {
                return new \WP_Error( 'mvm_work_not_found', 'Werkitem niet gevonden.', array( 'status' => 404 ) );
            }

            if ( (int) ( $item['version'] ?? 0 ) !== $expected_version ) {
                return new \WP_Error( 'mvm_work_version_conflict', 'Het werkitem is intussen gewijzigd.', array( 'status' => 409 ) );
            }

            $workflow_key = sanitize_key( (string) ( $item['workflow_key'] ?? '' ) );
            $from_state   = sanitize_key( (string) ( $item['workflow_state'] ?? '' ) );
            $workflow     = Workflow_Registry::get( $workflow_key );
            if ( null === $workflow || (int) ( $workflow['version'] ?? 0 ) !== (int) ( $item['workflow_version'] ?? 0 ) ) {
                return new \WP_Error( 'mvm_workflow_version_conflict', 'De workflowversie van dit werkitem is niet actief.', array( 'status' => 409 ) );
            }

            $transition = Workflow_Registry::transition( $workflow_key, $from_state, $to_state );
            if ( null === $transition ) {
                return new \WP_Error( 'mvm_work_transition_forbidden', 'Deze workflowtransitie bestaat niet.', array( 'status' => 409 ) );
            }

            if ( ! self::decision_allows( $authorize( $transition, $item, $actor_user_id ) ) ) {
                return new \WP_Error( 'mvm_work_transition_denied', 'Geen toestemming voor deze workflowtransitie.', array( 'status' => 403 ) );
            }

            if ( ! empty( $transition['requires_object_check'] ) ) {
                if ( null === $object_check || ! self::decision_allows( $object_check( $item, $actor_user_id ) ) ) {
                    return new \WP_Error( 'mvm_work_object_denied', 'Geen toegang tot het gekoppelde object.', array( 'status' => 403 ) );
                }
            }

            $next = $item;
            $next['workflow_state'] = $to_state;
            $next['required_capability'] = self::next_capability( $workflow_key, $to_state );

            $saved = $this->repository->save( $id, $next, $expected_version );
            if ( is_wp_error( $saved ) ) {
                return $saved;
            }

            $event = array(
                'work_item_id'     => $id,
                'workflow_key'     => $workflow_key,
                'workflow_version' => (int) $item['workflow_version'],
                'from_state'       => $from_state,
                'to_state'         => $to_state,
                'actor_user_id'    => $actor_user_id,
                'reason_code'      => $reason_code,
                'request_id'       => $request_id,
                'audit_event'      => sanitize_key( (string) ( $transition['audit_event'] ?? '' ) ),
            );

            $event_result = $this->repository->append_transition( $event );
            if ( is_wp_error( $event_result ) ) {
                return $event_result;
            }

            return $saved;
        } );
    }

    /** @param bool|array<string,mixed> $decision */
    private static function decision_allows( bool|array $decision ): bool {
        if ( true === $decision ) {
            return true;
        }
        return is_array( $decision ) && true === ( $decision['allow'] ?? false );
    }

    private static function next_capability( string $workflow_key, string $state ): string {
        $workflow = Workflow_Registry::get( $workflow_key );
        if ( null === $workflow ) {
            return '';
        }
        $targets = $workflow['transitions'][ $state ] ?? array();
        if ( ! is_array( $targets ) || empty( $targets ) ) {
            return '';
        }
        $caps = array();
        foreach ( $targets as $policy ) {
            if ( is_array( $policy ) && isset( $policy['capability'] ) ) {
                $cap = sanitize_key( (string) $policy['capability'] );
                if ( '' !== $cap ) {
                    $caps[] = $cap;
                }
            }
        }
        $caps = array_values( array_unique( $caps ) );
        return 1 === count( $caps ) ? $caps[0] : '';
    }
}
