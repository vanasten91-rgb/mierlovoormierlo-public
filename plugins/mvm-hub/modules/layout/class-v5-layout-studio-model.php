<?php

namespace MVM\Hub\Modules\Layout;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure, dormant Layout Studio draft/preview model.
 *
 * It stores no layout and renders no public HTML. It only normalizes reviewed
 * template IDs + safe settings and projects preview/release readiness.
 */
final class V5_Layout_Studio_Model {
    /** @return array<string,mixed>|null */
    public static function draft( array $input ): ?array {
        $layout = V5_Layout_Template_Registry::normalize(
            (string) ( $input['slot'] ?? '' ),
            (string) ( $input['template'] ?? '' ),
            is_array( $input['settings'] ?? null ) ? $input['settings'] : array()
        );
        if ( null === $layout ) return null;

        $status = sanitize_key( (string) ( $input['status'] ?? 'draft' ) );
        if ( ! in_array( $status, array( 'draft', 'review', 'approved' ), true ) ) {
            $status = 'draft';
        }

        return array(
            'layout' => $layout,
            'status' => $status,
            'revision' => max( 1, (int) ( $input['revision'] ?? 1 ) ),
            'previewModes' => array(
                'desktop' => true,
                'tablet' => true,
                'mobile' => true,
                'light' => true,
                'dark' => true,
            ),
            'routeOwnership' => false,
            'publicRendererOwnership' => false,
            'productionActivated' => false,
        );
    }

    /** @return array<string,mixed> */
    public static function release_readiness( array $draft, array $signals ): array {
        $required = array(
            'reviewedRendererExists',
            'desktopPreviewApproved',
            'tabletPreviewApproved',
            'mobilePreviewApproved',
            'lightPreviewApproved',
            'darkPreviewApproved',
            'accessibilityChecked',
            'rollbackRevisionExists',
        );
        $missing = array();
        foreach ( $required as $signal ) {
            if ( true !== ( $signals[ $signal ] ?? false ) ) $missing[] = $signal;
        }

        $status = sanitize_key( (string) ( $draft['status'] ?? '' ) );
        if ( 'approved' !== $status ) $missing[] = 'editorialApproval';
        if ( ! is_array( $draft['layout'] ?? null ) ) $missing[] = 'validLayout';

        $missing = array_values( array_unique( $missing ) );
        return array(
            'canRequestPublish' => array() === $missing,
            'canPublishProduction' => false,
            'requiresSeparatePublishAction' => true,
            'requiresRollbackRevision' => true,
            'missing' => $missing,
        );
    }

    private function __construct() {}
}
