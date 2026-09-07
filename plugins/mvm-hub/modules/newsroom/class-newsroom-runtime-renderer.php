<?php

namespace MVM\Hub\Modules\Newsroom;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Runtime_Gates;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Standalone /hub/ Newsroom runtime. Security remains authoritative in REST/services. */
final class Newsroom_Runtime_Renderer {
    public static function render( string $section ): string {
        $section = sanitize_key( $section );
        $preview = Newsroom_Preview_Renderer::render( $section );
        $write_enabled = Runtime_Gates::newsroom_writes_enabled();
        $can_create = $write_enabled && self::can_create( $section );
        $can_update = $write_enabled && self::can_update( $section );

        ob_start();
        ?>
<div class="mvm-newsroom-runtime"
     data-mvm-newsroom
     data-section="<?php echo esc_attr( $section ); ?>"
     data-rest-base="<?php echo esc_url( rest_url( 'mvm-hub/v1' ) ); ?>"
     data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
     data-write-enabled="<?php echo $write_enabled ? '1' : '0'; ?>"
     data-can-create="<?php echo $can_create ? '1' : '0'; ?>"
     data-can-update="<?php echo $can_update ? '1' : '0'; ?>"
     data-current-user="<?php echo esc_attr( (string) get_current_user_id() ); ?>">
    <div class="mvm-newsroom-runtime__toolbar">
        <div>
            <p class="mvm-hub__eyebrow">Nieuwsroom</p>
            <strong><?php echo esc_html( self::section_label( $section ) ); ?></strong>
        </div>
        <div class="mvm-newsroom-runtime__actions">
            <?php if ( $can_create ) : ?>
                <button type="button" class="mvm-button mvm-button--primary" data-newsroom-new><?php echo esc_html( self::create_label( $section ) ); ?></button>
            <?php endif; ?>
            <button type="button" class="mvm-button mvm-button--secondary" data-newsroom-refresh>Vernieuwen</button>
        </div>
    </div>

    <div class="mvm-alert" data-newsroom-notice hidden role="status" aria-live="polite"></div>
    <?php if ( ! $write_enabled && ! in_array( $section, array( 'today', 'team' ), true ) ) : ?>
        <div class="mvm-alert mvm-alert--warning">Deze deployment toont de Nieuwsroom read-only. Schrijven vereist de afzonderlijke Nieuwsroom-writegate.</div>
    <?php endif; ?>

    <div data-newsroom-content><?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted preview renderer. ?></div>

    <?php if ( $write_enabled && ( $can_create || $can_update ) ) : ?>
    <dialog class="mvm-newsroom-editor" data-newsroom-dialog>
        <form method="dialog" class="mvm-newsroom-editor__frame" data-newsroom-form>
            <header>
                <div><p class="mvm-hub__eyebrow">Nieuwsroom</p><h2 data-newsroom-dialog-title>Bewerken</h2></div>
                <button type="button" class="mvm-icon-button" data-newsroom-close aria-label="Sluiten">×</button>
            </header>
            <div class="mvm-newsroom-editor__body" data-newsroom-fields></div>
            <div class="mvm-newsroom-editor__smartlinks" data-smartlinks-panel hidden>
                <div class="mvm-newsroom-editor__smartlinks-head"><strong>Smart Links · Encyclopedie</strong><span class="mvm-newsroom-preview__muted">Zoeken geeft suggesties; invoegen blijft een redactionele keuze.</span></div>
                <div class="mvm-newsroom-editor__smartlinks-search"><input type="search" maxlength="120" placeholder="Zoek Encyclopedie-doel" data-smartlinks-query><button type="button" class="mvm-button mvm-button--secondary" data-smartlinks-search>Zoek</button></div>
                <div data-smartlinks-results></div>
            </div>
            <footer>
                <span class="mvm-newsroom-preview__muted" data-newsroom-form-status></span>
                <div><button type="button" class="mvm-button mvm-button--secondary" data-newsroom-close>Annuleren</button><button type="submit" class="mvm-button mvm-button--primary" data-newsroom-save>Opslaan</button></div>
            </footer>
        </form>
    </dialog>
    <?php endif; ?>
</div>
        <?php
        return (string) ob_get_clean();
    }

    private static function can_create( string $section ): bool {
        if ( current_user_can( 'manage_options' ) ) return true;
        return match ( $section ) {
            'news'         => current_user_can( Capabilities::NEWS_CREATE ),
            'assignments'  => current_user_can( Capabilities::ASSIGNMENTS_MANAGE ) || current_user_can( Capabilities::NEWS_CREATE ),
            'radar'        => current_user_can( Capabilities::NEWS_CREATE ) || current_user_can( Capabilities::RADAR_TRIAGE ),
            'agenda'       => current_user_can( Capabilities::AGENDA_MANAGE ),
            'media'        => current_user_can( Capabilities::MEDIA_MANAGE ),
            'dossiers'     => current_user_can( Capabilities::DOSSIERS_MANAGE ),
            'corrections'  => current_user_can( Capabilities::CORRECTIONS_MANAGE ),
            'distribution' => current_user_can( Capabilities::DISTRIBUTION_MANAGE ),
            default        => false,
        };
    }

    private static function can_update( string $section ): bool {
        if ( current_user_can( 'manage_options' ) ) return true;
        return match ( $section ) {
            'news' => current_user_can( Capabilities::NEWS_EDIT_OWN ) || current_user_can( Capabilities::NEWS_EDIT_TEAM ) || current_user_can( Capabilities::NEWS_REVIEW ) || current_user_can( Capabilities::NEWS_PUBLISH ) || current_user_can( Capabilities::CORRECTIONS_MANAGE ),
            'assignments' => current_user_can( Capabilities::ASSIGNMENTS_VIEW ) || current_user_can( Capabilities::ASSIGNMENTS_MANAGE ),
            'radar' => current_user_can( Capabilities::RADAR_TRIAGE ),
            'sources' => current_user_can( Capabilities::SOURCES_MANAGE ),
            'agenda' => current_user_can( Capabilities::AGENDA_MANAGE ),
            'media' => current_user_can( Capabilities::MEDIA_MANAGE ),
            'dossiers' => current_user_can( Capabilities::DOSSIERS_MANAGE ),
            'corrections' => current_user_can( Capabilities::CORRECTIONS_MANAGE ),
            'distribution' => current_user_can( Capabilities::DISTRIBUTION_MANAGE ),
            default => false,
        };
    }

    private static function section_label( string $section ): string {
        return array(
            'today'=>'Vandaag','news'=>'Nieuws','assignments'=>'Opdrachten','radar'=>'Radar','sources'=>'Bronnen','agenda'=>'Agenda','media'=>'Media','dossiers'=>'Dossiers','corrections'=>'Correcties','distribution'=>'Distributie','team'=>'Team',
        )[ $section ] ?? 'Nieuwsroom';
    }

    private static function create_label( string $section ): string {
        return array(
            'news'=>'Nieuw artikel','assignments'=>'Nieuwe opdracht','radar'=>'Nieuw signaal','agenda'=>'Nieuw agenda-item','media'=>'Nieuw media-item','dossiers'=>'Nieuw dossier','corrections'=>'Nieuwe correctie','distribution'=>'Nieuwe distributie',
        )[ $section ] ?? 'Nieuw';
    }

    private function __construct() {}
}
