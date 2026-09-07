<?php

define( 'ABSPATH', __DIR__ . '/' );

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public function __construct( public string $code, public string $message = '', public array $data = array() ) {}
        public function get_error_code(): string { return $this->code; }
    }
}
function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function sanitize_key( string $value ): string { $value = strtolower( trim( $value ) ); return preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? ''; }
function sanitize_text_field( string $value ): string { return trim( strip_tags( preg_replace( '/[\r\n\t]+/', ' ', $value ) ?? $value ) ); }
function sanitize_textarea_field( string $value ): string { return trim( strip_tags( $value ) ); }

require_once __DIR__ . '/../plugins/mvm-hub/core/class-data-classification.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-workflow-registry.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-work-item-schema.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/interface-work-item-repository.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-work-item-storage-contract.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-work-item-migration-plan.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-work-item-service.php';

use MVM\Hub\Core\Work_Item_Repository;
use MVM\Hub\Core\Work_Item_Service;
use MVM\Hub\Core\Workflow_Registry;
use MVM\Hub\Core\V5_Work_Item_Migration_Plan;

function mvm_v5_work_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

Workflow_Registry::register( 'test_editorial', array(
    'version' => 1,
    'initial' => 'triage',
    'states' => array( 'triage', 'research', 'review', 'done' ),
    'transitions' => array(
        'triage' => array(
            'research' => array(
                'capability' => 'mvm_newsroom_assign',
                'requires_object_check' => true,
                'audit_event' => 'work_triage_research',
            ),
        ),
        'research' => array(
            'review' => array(
                'capability' => 'mvm_newsroom_submit_review',
                'requires_object_check' => true,
                'audit_event' => 'work_research_review',
            ),
        ),
        'review' => array(
            'done' => array(
                'capability' => 'mvm_newsroom_approve',
                'requires_object_check' => true,
                'requires_step_up' => true,
                'audit_event' => 'work_review_done',
            ),
        ),
    ),
) );

$repository = new class implements Work_Item_Repository {
    public array $items = array();
    public array $deps = array();
    public array $checks = array();
    public array $events = array();
    private int $next = 1;

    public function get( int $id ): ?array { return $this->items[ $id ] ?? null; }
    public function create( array $record ): array|WP_Error {
        $id = $this->next++;
        $record['id'] = $id;
        $record['version'] = 1;
        $this->items[ $id ] = $record;
        return $record;
    }
    public function save( int $id, array $record, int $expected_version ): array|WP_Error {
        if ( ! isset( $this->items[ $id ] ) ) return new WP_Error( 'missing' );
        if ( (int) $this->items[ $id ]['version'] !== $expected_version ) return new WP_Error( 'version_conflict' );
        $record['id'] = $id;
        $record['version'] = $expected_version + 1;
        $this->items[ $id ] = $record;
        return $record;
    }
    public function dependencies( int $id ): array { return $this->deps[ $id ] ?? array(); }
    public function checklist( int $id ): array { return $this->checks[ $id ] ?? array(); }
    public function replace_dependencies( int $id, array $dependency_ids ): bool|WP_Error { $this->deps[ $id ] = array_values( $dependency_ids ); return true; }
    public function replace_checklist( int $id, array $items ): bool|WP_Error { $this->checks[ $id ] = array_values( $items ); return true; }
    public function append_transition( array $event ): bool|WP_Error {
        foreach ( $this->events as $existing ) if ( ( $existing['request_id'] ?? '' ) === ( $event['request_id'] ?? '' ) ) return new WP_Error( 'duplicate_request' );
        $this->events[] = $event;
        return true;
    }
    public function transaction( callable $callback ): mixed {
        $snapshot = array( $this->items, $this->deps, $this->checks, $this->events, $this->next );
        try {
            $result = $callback();
            if ( is_wp_error( $result ) ) {
                [ $this->items, $this->deps, $this->checks, $this->events, $this->next ] = $snapshot;
            }
            return $result;
        } catch ( Throwable $error ) {
            [ $this->items, $this->deps, $this->checks, $this->events, $this->next ] = $snapshot;
            throw $error;
        }
    }
};

$service = new Work_Item_Service( $repository );
$created = $service->create( array(
    'type' => 'news_regular',
    'domain' => 'newsroom',
    'object_type' => 'post',
    'object_id' => 44,
    'title' => 'Onderzoek dossier',
    'owner_user_id' => 7,
    'priority' => 'high',
    'status' => 'open',
    'workflow_key' => 'test_editorial',
    'workflow_version' => 1,
    'workflow_state' => 'triage',
    'required_capability' => 'mvm_newsroom_assign',
    'dependency_ids' => array( 9, 9, 10 ),
    'checklist' => array( array( 'id' => 'source', 'label' => 'Bron controleren', 'done' => false ) ),
    'classification' => 'internal',
), 7 );

mvm_v5_work_assert( is_array( $created ), 'work item should be created' );
mvm_v5_work_assert( 1 === $created['version'], 'new work item should start at version 1' );
mvm_v5_work_assert( array( 9, 10 ) === $repository->dependencies( 1 ), 'dependencies should be normalized and persisted' );
mvm_v5_work_assert( 1 === count( $repository->checklist( 1 ) ), 'checklist should persist atomically' );

$denied = $service->transition(
    1, 'research', 7, 1, 'req_denied',
    static fn(): bool => false,
    static fn(): bool => true
);
mvm_v5_work_assert( is_wp_error( $denied ) && 'mvm_work_transition_denied' === $denied->get_error_code(), 'authorization denial must fail closed' );
mvm_v5_work_assert( 'triage' === $repository->get( 1 )['workflow_state'], 'denied transition must roll back' );
mvm_v5_work_assert( 0 === count( $repository->events ), 'denied transition must not append an event' );

$object_denied = $service->transition(
    1, 'research', 7, 1, 'req_object_denied',
    static fn(): bool => true,
    static fn(): bool => false
);
mvm_v5_work_assert( is_wp_error( $object_denied ) && 'mvm_work_object_denied' === $object_denied->get_error_code(), 'object authorization must be enforced' );

$ok = $service->transition(
    1, 'research', 7, 1, 'req_research',
    static fn( array $transition ): array => array( 'allow' => 'mvm_newsroom_assign' === $transition['capability'] ),
    static fn( array $item ): bool => 44 === (int) $item['object_id'],
    'assigned'
);
mvm_v5_work_assert( is_array( $ok ) && 'research' === $ok['workflow_state'], 'authorized transition should persist' );
mvm_v5_work_assert( 2 === $ok['version'], 'successful transition should advance optimistic version' );
mvm_v5_work_assert( 'mvm_newsroom_submit_review' === $ok['required_capability'], 'single next capability should be projected' );
mvm_v5_work_assert( 1 === count( $repository->events ), 'successful transition should append an immutable transition event' );
mvm_v5_work_assert( 'req_research' === $repository->events[0]['request_id'], 'transition event should preserve idempotency request id' );

$stale = $service->transition(
    1, 'review', 7, 1, 'req_stale',
    static fn(): bool => true,
    static fn(): bool => true
);
mvm_v5_work_assert( is_wp_error( $stale ) && 'mvm_work_version_conflict' === $stale->get_error_code(), 'stale writers must fail with version conflict' );

$plan = V5_Work_Item_Migration_Plan::plan( 'wpmvm' );
mvm_v5_work_assert( true === $plan['valid'], 'valid prefix should produce a migration plan' );
mvm_v5_work_assert( false === $plan['productionWrites'], 'migration plan itself must never perform production writes' );
mvm_v5_work_assert( 4 === count( $plan['operations'] ), 'migration plan should contain all four storage tables' );
mvm_v5_work_assert( str_contains( $plan['operations'][0]['sql'], 'UNIQUE KEY request_id' ) === false, 'request-id uniqueness belongs to workflow events, not work_items' );
mvm_v5_work_assert( str_contains( $plan['operations'][3]['sql'], 'UNIQUE KEY request_id' ), 'workflow events must reject duplicate transition requests' );
mvm_v5_work_assert( false === V5_Work_Item_Migration_Plan::plan( 'bad-prefix!' )['valid'], 'unsafe table prefixes must fail closed' );

echo "PASS: MvM Hub V5 durable work-item service and migration plan smoke\n";
