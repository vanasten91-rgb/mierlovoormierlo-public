<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Declarative mapping from the canonical V5 UAT personas to WordPress roles.
 *
 * This class performs no role mutation and no capability lookup. It exists so
 * staging evidence and CI use one explicit least-privilege mapping rather than
 * silently treating the broad mvm_editor role as every domain specialist.
 */
final class V5_Role_UAT_Role_Map {
    /** @return array<string,array{wpRoles:list<string>,requiredCapabilities:list<string>,forbiddenCapabilities:list<string>}> */
    public static function all(): array {
        return array(
            'journalist' => self::profile(
                array( 'mvm_journalist' ),
                array( Capabilities::NEWSROOM_ACCESS, Capabilities::NEWS_CREATE, Capabilities::NEWS_EDIT_OWN, Capabilities::ASSIGNMENTS_VIEW ),
                array( Capabilities::NEWS_PUBLISH, Capabilities::TECHNICAL_ACCESS )
            ),
            'redacteur' => self::profile(
                array( 'mvm_redacteur' ),
                array( Capabilities::NEWSROOM_ACCESS, Capabilities::ASSIGNMENTS_MANAGE, Capabilities::RADAR_TRIAGE, 'mvm_intake_view', 'mvm_intake_triage', 'mvm_intake_assign' ),
                array( Capabilities::NEWS_PUBLISH, Capabilities::TECHNICAL_ACCESS )
            ),
            'editor' => self::profile(
                array( 'mvm_editor' ),
                array( Capabilities::NEWS_REVIEW, Capabilities::CORRECTIONS_MANAGE, Capabilities::NEWS_PUBLISH ),
                array( Capabilities::TECHNICAL_ACCESS )
            ),
            'fotograaf' => self::profile(
                array( 'mvm_fotograaf' ),
                array( Capabilities::MEDIA_VIEW, Capabilities::MEDIA_MANAGE, Capabilities::ASSIGNMENTS_VIEW ),
                array( Capabilities::NEWS_PUBLISH, Capabilities::TECHNICAL_ACCESS )
            ),
            'moderator' => self::profile(
                array( 'mvm_moderator' ),
                array( Capabilities::NEWS_REVIEW, 'mvm_community_moderate' ),
                array( Capabilities::NEWS_PUBLISH, Capabilities::TECHNICAL_ACCESS )
            ),
            'vertaler' => self::profile(
                array( 'mvm_vertaler' ),
                array( Capabilities::NEWS_EDIT_OWN, Capabilities::ASSIGNMENTS_VIEW ),
                array( Capabilities::NEWS_PUBLISH, Capabilities::TECHNICAL_ACCESS )
            ),
            'teamleider' => self::profile(
                array( 'mvm_teamleider' ),
                array( Capabilities::ORGANIZATION_ACCESS, 'mvm_community_escalate', 'mvm_intake_manage' ),
                array( Capabilities::TECHNICAL_ACCESS )
            ),
            'agenda_editor' => self::profile(
                array( 'mvm_agenda_editor' ),
                array( Capabilities::AGENDA_VIEW, Capabilities::AGENDA_MANAGE ),
                array( Capabilities::NEWS_PUBLISH, Capabilities::TECHNICAL_ACCESS )
            ),
            'encyclopedie_editor' => self::profile(
                array( 'mvm_encyclopedie_editor' ),
                array( 'mvm_encyclopedie_view_editorial', 'mvm_encyclopedie_edit', 'mvm_encyclopedie_review', 'mvm_contextlinks_review' ),
                array( Capabilities::NEWS_PUBLISH, Capabilities::TECHNICAL_ACCESS )
            ),
            'mierlokaal_editor' => self::profile(
                array( 'mvm_mierlokaal_editor' ),
                array( 'mvm_marketplace_moderate', 'mvm_local_ads_manage', 'mvm_vacatures_manage_all' ),
                array( Capabilities::NEWSROOM_ACCESS, Capabilities::NEWS_PUBLISH, Capabilities::TECHNICAL_ACCESS )
            ),
            'communications' => self::profile(
                array( 'mvm_communications' ),
                array( Capabilities::MAIL_ACCESS, Capabilities::MAIL_READ, Capabilities::MAIL_COMPOSE, Capabilities::MAIL_SEND ),
                array( Capabilities::NEWS_PUBLISH, Capabilities::TECHNICAL_ACCESS )
            ),
            'sysop' => self::profile(
                array( 'mvm_sysop' ),
                array( Capabilities::TECHNICAL_ACCESS ),
                array()
            ),
            'partner' => self::profile(
                array( 'mvm_bedrijf', 'mvm_club', 'mvm_ondernemer', 'mvm_organisator', 'mvm_vereniging', 'mvm_winkelier' ),
                array( 'mvm_hub3_business_access' ),
                array( Capabilities::NEWSROOM_ACCESS, Capabilities::TEAM_CHAT_ACCESS, Capabilities::TECHNICAL_ACCESS )
            ),
        );
    }

    /** @return array{wpRoles:list<string>,requiredCapabilities:list<string>,forbiddenCapabilities:list<string>} */
    private static function profile( array $roles, array $required, array $forbidden ): array {
        return array(
            'wpRoles' => array_values( array_unique( array_map( 'sanitize_key', $roles ) ) ),
            'requiredCapabilities' => array_values( array_unique( array_map( 'sanitize_key', $required ) ) ),
            'forbiddenCapabilities' => array_values( array_unique( array_map( 'sanitize_key', $forbidden ) ) ),
        );
    }

    private function __construct() {}
}
