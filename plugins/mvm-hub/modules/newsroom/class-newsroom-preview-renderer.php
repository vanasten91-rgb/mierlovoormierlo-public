<?php

namespace MVM\Hub\Modules\Newsroom;

use MVM\Hub\Modules\Newsroom\Assignments\Assignments_Read_Model;
use MVM\Hub\Modules\Newsroom\Dashboard\Today_Read_Model;
use MVM\Hub\Modules\Newsroom\News\News_Read_Model;
use MVM\Hub\Modules\Newsroom\Read\Newsroom_Read_Model;
use MVM\Hub\Modules\Newsroom\Read\Newsroom_Secondary_Read_Model;
use MVM\Hub\Modules\Newsroom\Team\Team_Read_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Server-rendered read-only module previews for the gated shell smoke route.
 *
 * Every value is escaped at the point of output. Underlying read models enforce
 * capability/object scope, while this renderer adds a second explicit UI-column
 * allowlist. No mutation controls are rendered.
 */
final class Newsroom_Preview_Renderer {
    public static function render( string $section ): string {
        $section = sanitize_key( $section );

        return match ( $section ) {
            'today'       => self::today(),
            'news'        => self::news(),
            'assignments' => self::assignments(),
            'radar',
            'sources',
            'agenda',
            'media',
            'dossiers',
            'corrections',
            'distribution',
            'team'        => self::operational_list( $section ),
            default       => '',
        };
    }

    private static function today(): string {
        $payload = ( new Today_Read_Model() )->snapshot();
        $metrics = is_array( $payload['metrics'] ?? null ) ? $payload['metrics'] : array();
        $actions = is_array( $payload['guidance'] ?? null ) ? $payload['guidance'] : array();
        $assignments = is_array( $payload['items']['assignments'] ?? null ) ? $payload['items']['assignments'] : array();

        $metric_labels = array(
            'overdueAssignments'      => 'Verlopen opdrachten',
            'dueSoonAssignments'      => 'Binnen 24 uur',
            'reviewAssignments'       => 'Opdrachten in review',
            'newsAwaitingAction'      => 'Nieuws vraagt actie',
            'signalsNeedingTriage'    => 'Signalen te beoordelen',
            'sourcesNeedingCheck'     => 'Bronnen controleren',
            'upcomingCalendar'        => 'Agenda komende 7 dagen',
            'mediaNeedsAction'        => 'Media vraagt actie',
            'correctionsWaiting'      => 'Open correcties',
            'distributionNeedsAction' => 'Distributie vraagt actie',
        );

        ob_start();
        ?>
        <div class="mvm-newsroom-preview mvm-newsroom-preview--today">
            <section class="mvm-newsroom-preview__section" aria-labelledby="mvm-preview-today-overview">
                <header class="mvm-newsroom-preview__section-head">
                    <div>
                        <p class="mvm-hub__eyebrow">Vandaag</p>
                        <h2 id="mvm-preview-today-overview">Redactioneel overzicht</h2>
                    </div>
                    <span class="mvm-badge"><?php echo esc_html( 'team' === ( $payload['scope'] ?? '' ) ? 'Teamweergave' : 'Mijn werk' ); ?></span>
                </header>

                <div class="mvm-newsroom-preview__metrics">
                    <?php foreach ( $metric_labels as $key => $label ) : ?>
                        <?php if ( array_key_exists( $key, $metrics ) ) : ?>
                            <article class="mvm-card mvm-newsroom-preview__metric">
                                <strong class="mvm-newsroom-preview__metric-value"><?php echo esc_html( (string) max( 0, (int) $metrics[ $key ] ) ); ?></strong>
                                <span><?php echo esc_html( $label ); ?></span>
                            </article>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="mvm-newsroom-preview__section" aria-labelledby="mvm-preview-next-actions">
                <h2 id="mvm-preview-next-actions">Volgende acties</h2>
                <?php if ( array() === $actions ) : ?>
                    <div class="mvm-empty">Er zijn op dit moment geen urgente redactionele acties.</div>
                <?php else : ?>
                    <ol class="mvm-newsroom-preview__actions">
                        <?php foreach ( array_slice( $actions, 0, 6 ) as $action ) : ?>
                            <li class="mvm-card">
                                <strong><?php echo esc_html( (string) ( $action['title'] ?? '' ) ); ?></strong>
                                <p><?php echo esc_html( (string) ( $action['instruction'] ?? '' ) ); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section class="mvm-newsroom-preview__section" aria-labelledby="mvm-preview-active-assignments">
                <h2 id="mvm-preview-active-assignments">Actieve opdrachten</h2>
                <?php echo self::assignment_table( $assignments ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes every cell. ?>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function news(): string {
        $payload = ( new News_Read_Model() )->list( 1, 20, '' );
        $items   = is_array( $payload['items'] ?? null ) ? $payload['items'] : array();

        ob_start();
        ?>
        <div class="mvm-newsroom-preview mvm-newsroom-preview--news">
            <header class="mvm-newsroom-preview__section-head">
                <div>
                    <p class="mvm-hub__eyebrow">Nieuws</p>
                    <h2>Recente artikelen</h2>
                    <p class="mvm-newsroom-preview__muted">Alleen artikelen die binnen jouw objectrechten vallen worden getoond.</p>
                </div>
                <span class="mvm-badge"><?php echo esc_html( 'team' === ( $payload['scope'] ?? '' ) ? 'Team' : 'Eigen' ); ?></span>
            </header>

            <?php if ( array() === $items ) : ?>
                <div class="mvm-empty">Geen artikelen beschikbaar binnen deze weergave.</div>
            <?php else : ?>
                <div class="mvm-table-wrap">
                    <table class="mvm-table">
                        <thead><tr><th scope="col">Artikel</th><th scope="col">Workflow</th><th scope="col">Gewijzigd</th><th scope="col">Toegang</th></tr></thead>
                        <tbody>
                        <?php foreach ( array_slice( $items, 0, 20 ) as $item ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( (string) ( $item['title'] ?? '(Zonder titel)' ) ); ?></strong></td>
                                <td><span class="mvm-badge"><?php echo esc_html( self::workflow_label( (string) ( $item['workflowState'] ?? $item['wpStatus'] ?? '' ) ) ); ?></span></td>
                                <td><?php echo self::time_cell( (string) ( $item['modifiedUtc'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                <td><?php echo esc_html( ! empty( $item['canEdit'] ) ? 'Bewerken' : 'Lezen' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ( ! empty( $payload['hasMore'] ) ) : ?><p class="mvm-newsroom-preview__muted">Er zijn meer artikelen; paginering volgt via de read-route.</p><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function assignments(): string {
        $payload = ( new Assignments_Read_Model() )->list( 1, 20, '' );
        $items   = is_array( $payload['items'] ?? null ) ? $payload['items'] : array();

        ob_start();
        ?>
        <div class="mvm-newsroom-preview mvm-newsroom-preview--assignments">
            <header class="mvm-newsroom-preview__section-head">
                <div>
                    <p class="mvm-hub__eyebrow">Opdrachten</p>
                    <h2>Redactionele opdrachten</h2>
                    <p class="mvm-newsroom-preview__muted">Vertrouwelijke opdrachtdetails worden niet in deze lijst geladen.</p>
                </div>
                <span class="mvm-badge"><?php echo esc_html( 'team' === ( $payload['scope'] ?? '' ) ? 'Team' : 'Eigen' ); ?></span>
            </header>
            <?php echo self::assignment_table( $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes every cell. ?>
            <?php if ( ! empty( $payload['hasMore'] ) ) : ?><p class="mvm-newsroom-preview__muted">Er zijn meer opdrachten; paginering volgt via de read-route.</p><?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function operational_list( string $section ): string {
        $primary   = new Newsroom_Read_Model();
        $secondary = new Newsroom_Secondary_Read_Model();

        $definition = match ( $section ) {
            'radar' => array(
                'eyebrow' => 'Radar',
                'title'   => 'Nieuws- en bronsignalen',
                'note'    => 'Contactgegevens en vrije signaalinhoud worden alleen via latere need-to-know detailweergaven beschikbaar.',
                'payload' => $primary->radar( 1, 20, '' ),
                'columns' => array(
                    array( 'key' => 'title', 'label' => 'Signaal', 'format' => 'strong' ),
                    array( 'key' => 'kind', 'label' => 'Type', 'format' => 'badge' ),
                    array( 'key' => 'status', 'label' => 'Status', 'format' => 'badge' ),
                    array( 'key' => 'verificationStatus', 'label' => 'Verificatie', 'format' => 'badge' ),
                    array( 'key' => 'priority', 'label' => 'Prioriteit', 'format' => 'priority' ),
                    array( 'key' => 'updatedAtUtc', 'label' => 'Gewijzigd', 'format' => 'date' ),
                ),
            ),
            'sources' => array(
                'eyebrow' => 'Bronnen',
                'title'   => 'Bronmonitor',
                'note'    => 'Deze lijst toont alleen operationele bronstatus; gevoelige broninformatie blijft buiten de generieke weergave.',
                'payload' => $primary->sources( 1, 20, '' ),
                'columns' => array(
                    array( 'key' => 'title', 'label' => 'Bron', 'format' => 'strong' ),
                    array( 'key' => 'category', 'label' => 'Categorie', 'format' => 'badge' ),
                    array( 'key' => 'status', 'label' => 'Status', 'format' => 'badge' ),
                    array( 'key' => 'monitorEnabled', 'label' => 'Monitor', 'format' => 'bool' ),
                    array( 'key' => 'nextCheckUtc', 'label' => 'Volgende controle', 'format' => 'date' ),
                ),
            ),
            'agenda' => array(
                'eyebrow' => 'Agenda',
                'title'   => 'Redactionele agenda',
                'note'    => 'De standaardweergave is begrensd rond de actuele periode en bevat alleen operationele planningsvelden.',
                'payload' => $primary->agenda( 1, 20, '' ),
                'columns' => array(
                    array( 'key' => 'title', 'label' => 'Item', 'format' => 'strong' ),
                    array( 'key' => 'kind', 'label' => 'Type', 'format' => 'badge' ),
                    array( 'key' => 'status', 'label' => 'Status', 'format' => 'badge' ),
                    array( 'key' => 'startsAtUtc', 'label' => 'Start', 'format' => 'date' ),
                    array( 'key' => 'endsAtUtc', 'label' => 'Einde', 'format' => 'date' ),
                ),
            ),
            'media' => array(
                'eyebrow' => 'Media',
                'title'   => 'Mediawerk',
                'note'    => 'De lijst toont status en toestemming zonder locatie-, credit- of andere vrije detailtekst.',
                'payload' => $secondary->media( 1, 20, '' ),
                'columns' => array(
                    array( 'key' => 'title', 'label' => 'Media-item', 'format' => 'strong' ),
                    array( 'key' => 'kind', 'label' => 'Type', 'format' => 'badge' ),
                    array( 'key' => 'status', 'label' => 'Status', 'format' => 'badge' ),
                    array( 'key' => 'consentStatus', 'label' => 'Toestemming', 'format' => 'badge' ),
                    array( 'key' => 'updatedAtUtc', 'label' => 'Gewijzigd', 'format' => 'date' ),
                ),
            ),
            'dossiers' => array(
                'eyebrow' => 'Dossiers',
                'title'   => 'Redactionele dossiers',
                'note'    => 'Interne dossierinhoud wordt niet in deze overzichtslijst geladen; zichtbaarheid wordt al in het readmodel begrensd.',
                'payload' => $secondary->dossiers( 1, 20, '' ),
                'columns' => array(
                    array( 'key' => 'title', 'label' => 'Dossier', 'format' => 'strong' ),
                    array( 'key' => 'status', 'label' => 'Status', 'format' => 'badge' ),
                    array( 'key' => 'visibility', 'label' => 'Zichtbaarheid', 'format' => 'badge' ),
                    array( 'key' => 'leadUserId', 'label' => 'Eigenaar-ID', 'format' => 'id' ),
                    array( 'key' => 'updatedAtUtc', 'label' => 'Gewijzigd', 'format' => 'date' ),
                ),
            ),
            'corrections' => array(
                'eyebrow' => 'Correcties',
                'title'   => 'Correctiewachtrij',
                'note'    => 'Meldingstekst en contactinformatie worden niet in de generieke correctielijst geladen.',
                'payload' => $secondary->corrections( 1, 20, '' ),
                'columns' => array(
                    array( 'key' => 'postId', 'label' => 'Artikel-ID', 'format' => 'id' ),
                    array( 'key' => 'status', 'label' => 'Status', 'format' => 'badge' ),
                    array( 'key' => 'submittedVia', 'label' => 'Kanaal', 'format' => 'badge' ),
                    array( 'key' => 'updatedAtUtc', 'label' => 'Gewijzigd', 'format' => 'date' ),
                    array( 'key' => 'resolvedAtUtc', 'label' => 'Afgerond', 'format' => 'date' ),
                ),
            ),
            'distribution' => array(
                'eyebrow' => 'Distributie',
                'title'   => 'Distributiewachtrij',
                'note'    => 'Publicatiecopy en externe doeladressen blijven buiten deze lijst; alleen workflowmetadata wordt getoond.',
                'payload' => $secondary->distribution( 1, 20, '' ),
                'columns' => array(
                    array( 'key' => 'postId', 'label' => 'Artikel-ID', 'format' => 'id' ),
                    array( 'key' => 'dossierId', 'label' => 'Dossier-ID', 'format' => 'id' ),
                    array( 'key' => 'channel', 'label' => 'Kanaal', 'format' => 'badge' ),
                    array( 'key' => 'status', 'label' => 'Status', 'format' => 'badge' ),
                    array( 'key' => 'sentAtUtc', 'label' => 'Verzonden', 'format' => 'date' ),
                ),
            ),
            'team' => array(
                'eyebrow' => 'Team',
                'title'   => 'Redactionele werkgebieden',
                'note'    => 'Werkgebieden zijn afgeleid van capabilities. Login-, e-mail- en profielgegevens worden niet geladen.',
                'payload' => ( new Team_Read_Model() )->list( 1, 20 ),
                'columns' => array(
                    array( 'key' => 'displayName', 'label' => 'Naam', 'format' => 'strong' ),
                    array( 'key' => 'areas', 'label' => 'Werkgebieden', 'format' => 'tags' ),
                    array( 'key' => 'isCurrent', 'label' => 'Jij', 'format' => 'bool' ),
                ),
            ),
            default => array(),
        };

        if ( array() === $definition ) {
            return '';
        }

        $payload = is_array( $definition['payload'] ?? null ) ? $definition['payload'] : array();
        $items   = is_array( $payload['items'] ?? null ) ? $payload['items'] : array();

        return self::generic_list(
            (string) $definition['eyebrow'],
            (string) $definition['title'],
            (string) $definition['note'],
            $items,
            (array) $definition['columns'],
            ! empty( $payload['hasMore'] )
        );
    }

    /** @param array<int,array<string,mixed>> $items @param array<int,array<string,string>> $columns */
    private static function generic_list(
        string $eyebrow,
        string $title,
        string $note,
        array $items,
        array $columns,
        bool $has_more
    ): string {
        ob_start();
        ?>
        <div class="mvm-newsroom-preview mvm-newsroom-preview--list">
            <header class="mvm-newsroom-preview__section-head">
                <div>
                    <p class="mvm-hub__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
                    <h2><?php echo esc_html( $title ); ?></h2>
                    <p class="mvm-newsroom-preview__muted"><?php echo esc_html( $note ); ?></p>
                </div>
                <span class="mvm-badge">Alleen-lezen</span>
            </header>

            <?php if ( array() === $items ) : ?>
                <div class="mvm-empty">Nog geen items beschikbaar in deze weergave.</div>
            <?php else : ?>
                <div class="mvm-table-wrap">
                    <table class="mvm-table">
                        <thead><tr>
                            <?php foreach ( $columns as $column ) : ?>
                                <th scope="col"><?php echo esc_html( (string) ( $column['label'] ?? '' ) ); ?></th>
                            <?php endforeach; ?>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( array_slice( $items, 0, 20 ) as $item ) : ?>
                            <tr>
                                <?php foreach ( $columns as $column ) : ?>
                                    <td><?php echo self::format_cell( $item, $column ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formatter escapes output. ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ( $has_more ) : ?><p class="mvm-newsroom-preview__muted">Meer resultaten zijn beschikbaar via de begrensde read-route.</p><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $item @param array<string,string> $column */
    private static function format_cell( array $item, array $column ): string {
        $key    = (string) ( $column['key'] ?? '' );
        $format = (string) ( $column['format'] ?? 'text' );
        $value  = $item[ $key ] ?? null;

        return match ( $format ) {
            'strong'   => '<strong>' . esc_html( (string) $value ) . '</strong>',
            'badge'    => '<span class="mvm-badge">' . esc_html( self::key_label( (string) $value ) ) . '</span>',
            'date'     => self::time_cell( is_string( $value ) ? $value : '' ),
            'bool'     => esc_html( ! empty( $value ) ? 'Ja' : 'Nee' ),
            'priority' => esc_html( self::priority_label( (int) $value ) ),
            'id'       => esc_html( (int) $value > 0 ? '#' . (int) $value : '—' ),
            'tags'     => self::tags_cell( is_array( $value ) ? $value : array() ),
            default    => esc_html( (string) $value ),
        };
    }

    /** @param array<int,mixed> $values */
    private static function tags_cell( array $values ): string {
        $html = array();
        foreach ( array_slice( $values, 0, 12 ) as $value ) {
            $label = self::key_label( (string) $value );
            if ( '' !== $label ) {
                $html[] = '<span class="mvm-badge">' . esc_html( $label ) . '</span>';
            }
        }
        return array() === $html ? '<span>—</span>' : '<span class="mvm-newsroom-preview__tags">' . implode( '', $html ) . '</span>';
    }

    /** @param array<int,array<string,mixed>> $items */
    private static function assignment_table( array $items ): string {
        if ( array() === $items ) {
            return '<div class="mvm-empty">Geen actieve opdrachten beschikbaar.</div>';
        }

        ob_start();
        ?>
        <div class="mvm-table-wrap">
            <table class="mvm-table">
                <thead><tr><th scope="col">Opdracht</th><th scope="col">Status</th><th scope="col">Prioriteit</th><th scope="col">Deadline</th></tr></thead>
                <tbody>
                <?php foreach ( array_slice( $items, 0, 20 ) as $item ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></strong></td>
                        <td><span class="mvm-badge"><?php echo esc_html( self::workflow_label( (string) ( $item['workflowState'] ?? $item['status'] ?? $item['legacyStatus'] ?? '' ) ) ); ?></span></td>
                        <td><?php echo esc_html( self::priority_label( (int) ( $item['priority'] ?? 0 ) ) ); ?></td>
                        <td><?php echo self::time_cell( (string) ( $item['dueAtUtc'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function workflow_label( string $state ): string {
        return match ( sanitize_key( $state ) ) {
            'idea', 'signal'       => 'Idee/signaal',
            'assigned'             => 'Toegewezen',
            'draft', 'in_progress' => 'Concept',
            'review'               => 'Te beoordelen',
            'changes_requested'    => 'Aanpassing gevraagd',
            'ready'                => 'Klaar voor publicatie',
            'scheduled'            => 'Ingepland',
            'published'            => 'Gepubliceerd',
            'correction'           => 'Correctie',
            'cancelled'            => 'Geannuleerd',
            default                => self::key_label( $state ),
        };
    }

    private static function key_label( string $value ): string {
        $key = sanitize_key( $value );
        if ( '' === $key ) {
            return '—';
        }

        return match ( $key ) {
            'new'             => 'Nieuw',
            'triage'          => 'Te beoordelen',
            'active'          => 'Actief',
            'inactive'        => 'Inactief',
            'draft'           => 'Concept',
            'review'          => 'Review',
            'ready'           => 'Gereed',
            'scheduled'       => 'Ingepland',
            'published'       => 'Gepubliceerd',
            'requested'       => 'Aangevraagd',
            'submitted'       => 'Aangeleverd',
            'resolved'        => 'Afgerond',
            'closed'          => 'Gesloten',
            'internal'        => 'Intern',
            'public'          => 'Publiek',
            'verified'        => 'Geverifieerd',
            'unverified'      => 'Niet geverifieerd',
            'unknown'         => 'Onbekend',
            'approved'        => 'Goedgekeurd',
            'rejected'        => 'Afgewezen',
            'news'            => 'Nieuws',
            'photo'           => 'Foto',
            'event'           => 'Evenement',
            'source'          => 'Bron',
            'general'         => 'Algemeen',
            'email'           => 'E-mail',
            'public_form'     => 'Publiek formulier',
            default           => ucwords( str_replace( array( '_', '-' ), ' ', $key ) ),
        };
    }

    private static function priority_label( int $priority ): string {
        return match ( $priority ) {
            1 => 'Hoog',
            2 => 'Normaal',
            3 => 'Laag',
            4 => 'Later',
            default => '—',
        };
    }

    private static function time_cell( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '<span aria-label="Geen datum">—</span>';
        }

        $timestamp = strtotime( $value . ' UTC' );
        if ( false === $timestamp ) {
            return '<span>—</span>';
        }

        return sprintf(
            '<time datetime="%1$s">%2$s</time>',
            esc_attr( gmdate( 'c', $timestamp ) ),
            esc_html( wp_date( 'd-m-Y H:i', $timestamp ) )
        );
    }

    private function __construct() {}
}
