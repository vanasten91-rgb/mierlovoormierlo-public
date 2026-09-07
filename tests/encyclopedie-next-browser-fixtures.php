<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

$relation_ids = array( 2054, 2055 );
for ( $id = 7100; $id <= 7113; ++$id ) {
    $relation_ids[] = $id;
}

update_post_meta( 1977, '_mvm_relations', $relation_ids );
clean_post_cache( 1977 );

echo "Encyclopedie Next browser fixtures: OK\n";
