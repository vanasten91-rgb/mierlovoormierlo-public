<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Persistence boundary for Hub V5 operational work items.
 *
 * Implementations must keep canonical domain content outside this repository;
 * the repository stores only workflow coordination data. Mutating methods must
 * be transaction-safe and enforce optimistic concurrency through `version`.
 */
interface Work_Item_Repository {
    /** @return array<string,mixed>|null */
    public function get( int $id ): ?array;

    /** @return array<string,mixed>|\WP_Error */
    public function create( array $record ): array|\WP_Error;

    /** @return array<string,mixed>|\WP_Error */
    public function save( int $id, array $record, int $expected_version ): array|\WP_Error;

    /** @return list<int> */
    public function dependencies( int $id ): array;

    /** @return list<array<string,mixed>> */
    public function checklist( int $id ): array;

    /** @return bool|\WP_Error */
    public function replace_dependencies( int $id, array $dependency_ids ): bool|\WP_Error;

    /** @return bool|\WP_Error */
    public function replace_checklist( int $id, array $items ): bool|\WP_Error;

    /** @return bool|\WP_Error */
    public function append_transition( array $event ): bool|\WP_Error;

    /**
     * Execute one atomic unit of work. Implementations must roll back when the
     * callback returns WP_Error or throws.
     *
     * @return mixed
     */
    public function transaction( callable $callback ): mixed;
}
