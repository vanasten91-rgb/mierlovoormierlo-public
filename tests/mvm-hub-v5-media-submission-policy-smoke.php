<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( mixed $value ): string {
        $value = strtolower( (string) $value );
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }
}

require_once __DIR__ . '/../plugins/mvm-hub/core/class-data-classification.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-media-submission-policy.php';

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\V5_Media_Submission_Policy;

function mvm_v5_media_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$private_default = V5_Media_Submission_Policy::decision(
    array( 'classification' => Data_Classification::PUBLIC )
);
mvm_v5_media_assert( true === $private_default['allowed'], 'default upload must be accepted as private staging media' );
mvm_v5_media_assert( V5_Media_Submission_Policy::VISIBILITY_PRIVATE === $private_default['effective_visibility'], 'default upload must remain private' );
mvm_v5_media_assert( false === $private_default['publication_eligible'], 'default private upload must not be publication eligible' );

$protected_public = V5_Media_Submission_Policy::decision(
    array(
        'classification' => Data_Classification::SOURCE_PROTECTED,
        'visibility' => V5_Media_Submission_Policy::VISIBILITY_PUBLIC,
        'rights_status' => 'permission',
        'credit' => 'Bronhouder',
        'rights_reference' => 'permission-123',
    ),
    array( 'editor_approved' => true, 'actor_can_publish' => true )
);
mvm_v5_media_assert( false === $protected_public['allowed'], 'source-protected original must never become public through media submission policy' );
mvm_v5_media_assert( 'classification_not_publishable' === $protected_public['reason'], 'protected public denial reason must be explicit' );
mvm_v5_media_assert( V5_Media_Submission_Policy::VISIBILITY_PRIVATE === $protected_public['effective_visibility'], 'protected media must remain private after denied publication' );

$missing_rights = V5_Media_Submission_Policy::decision(
    array(
        'classification' => Data_Classification::PUBLIC,
        'visibility' => V5_Media_Submission_Policy::VISIBILITY_PUBLIC,
    ),
    array( 'editor_approved' => true, 'actor_can_publish' => true )
);
mvm_v5_media_assert( false === $missing_rights['allowed'], 'public visibility must fail without rights metadata' );
mvm_v5_media_assert( 'rights_metadata_required' === $missing_rights['reason'], 'missing rights denial must be explicit' );

$license_without_reference = V5_Media_Submission_Policy::decision(
    array(
        'classification' => Data_Classification::PUBLIC,
        'visibility' => V5_Media_Submission_Policy::VISIBILITY_PUBLIC,
        'rights_status' => 'licensed',
        'credit' => 'Fotograaf X',
    ),
    array( 'editor_approved' => true, 'actor_can_publish' => true )
);
mvm_v5_media_assert( false === $license_without_reference['allowed'], 'licensed media must require a traceable rights reference' );
mvm_v5_media_assert( 'rights_metadata_required' === $license_without_reference['reason'], 'missing license reference must be treated as incomplete rights metadata' );

$missing_editor = V5_Media_Submission_Policy::decision(
    array(
        'classification' => Data_Classification::PUBLIC,
        'visibility' => V5_Media_Submission_Policy::VISIBILITY_PUBLIC,
        'rights_status' => 'owned',
        'credit' => 'MvM Fotograaf',
    ),
    array( 'editor_approved' => false, 'actor_can_publish' => true )
);
mvm_v5_media_assert( false === $missing_editor['allowed'], 'rights-complete media must still require editorial approval' );
mvm_v5_media_assert( 'editor_approval_required' === $missing_editor['reason'], 'editor approval denial must be explicit' );

$missing_publisher = V5_Media_Submission_Policy::decision(
    array(
        'classification' => Data_Classification::PUBLIC,
        'visibility' => V5_Media_Submission_Policy::VISIBILITY_PUBLIC,
        'rights_status' => 'owned',
        'credit' => 'MvM Fotograaf',
    ),
    array( 'editor_approved' => true, 'actor_can_publish' => false )
);
mvm_v5_media_assert( false === $missing_publisher['allowed'], 'editor approval must not bypass publisher capability' );
mvm_v5_media_assert( 'publisher_capability_required' === $missing_publisher['reason'], 'publisher capability denial must be explicit' );

$publishable = V5_Media_Submission_Policy::decision(
    array(
        'classification' => Data_Classification::PUBLIC,
        'visibility' => V5_Media_Submission_Policy::VISIBILITY_PUBLIC,
        'rights_status' => 'permission',
        'credit' => 'Fotograaf X',
        'rights_reference' => 'release-form-2026-09-05',
    ),
    array( 'editor_approved' => true, 'actor_can_publish' => true )
);
mvm_v5_media_assert( true === $publishable['allowed'], 'fully authorized rights-complete public media may be published' );
mvm_v5_media_assert( true === $publishable['publication_eligible'], 'authorized public media must be marked publication eligible' );
mvm_v5_media_assert( V5_Media_Submission_Policy::VISIBILITY_PUBLIC === $publishable['effective_visibility'], 'only successful publication decision may expose media publicly' );

$unknown = V5_Media_Submission_Policy::decision(
    array(
        'classification' => 'mystery',
        'visibility' => V5_Media_Submission_Policy::VISIBILITY_PUBLIC,
        'rights_status' => 'owned',
        'credit' => 'Unknown',
    ),
    array( 'editor_approved' => true, 'actor_can_publish' => true )
);
mvm_v5_media_assert( false === $unknown['allowed'], 'unknown classification must fail closed for public media' );
mvm_v5_media_assert( 'classification_not_publishable' === $unknown['reason'], 'unknown classification denial must use protected publication boundary' );

$protected_private = V5_Media_Submission_Policy::decision(
    array(
        'classification' => Data_Classification::SOURCE_PROTECTED,
        'visibility' => V5_Media_Submission_Policy::VISIBILITY_PRIVATE,
    )
);
mvm_v5_media_assert( true === $protected_private['allowed'], 'source-protected media may be accepted into private staging storage' );
mvm_v5_media_assert( V5_Media_Submission_Policy::VISIBILITY_PRIVATE === $protected_private['effective_visibility'], 'source-protected staging upload must stay private' );
mvm_v5_media_assert( false === $protected_private['publication_eligible'], 'source-protected private upload must never be publication eligible by default' );

echo "PASS: MvM Hub V5 media submission policy keeps uploads private by default and requires rights plus editorial publication gates\n";
