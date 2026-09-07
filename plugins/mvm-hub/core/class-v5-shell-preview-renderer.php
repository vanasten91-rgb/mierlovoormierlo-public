<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Inert HTML renderer for the side-by-side Hub V5 shell preview.
 *
 * It accepts only pre-authorized shell/My Work models, creates no links to live
 * V5 routes, registers no hooks/assets and performs no data access or writes.
 */
final class V5_Shell_Preview_Renderer {
    /**
     * @param array<string,mixed> $shell
     * @param array<string,mixed> $my_work
     */
    public static function render( array $shell, array $my_work ): string {
        $active = sanitize_key( (string) ( $shell['activeWorkspace'] ?? '' ) );
        $navigation = is_array( $shell['navigation'] ?? null ) ? $shell['navigation'] : array();
        $lanes = is_array( $my_work['lanes'] ?? null ) ? $my_work['lanes'] : array();
        $summary = is_array( $my_work['summary'] ?? null ) ? $my_work['summary'] : array();

        ob_start();
        ?>
        <section class="mvm-v5-preview" data-route-ownership="0" data-production-activated="0" aria-labelledby="mvm-v5-preview-title">
            <header class="mvm-v5-preview__header">
                <p class="mvm-v5-preview__eyebrow"><?php echo esc_html( 'MvM Hub V5 · side-by-side preview' ); ?></p>
                <h1 id="mvm-v5-preview-title"><?php echo esc_html( (string) ( $my_work['title'] ?? 'Mijn Werk' ) ); ?></h1>
                <p><?php echo esc_html( (string) ( $my_work['subtitle'] ?? '' ) ); ?></p>
            </header>

            <nav class="mvm-v5-preview__nav" aria-label="<?php echo esc_attr( 'Hub V5 preview' ); ?>">
                <ul>
                    <?php foreach ( $navigation as $item ) :
                        if ( ! is_array( $item ) ) {
                            continue;
                        }
                        $key = sanitize_key( (string) ( $item['key'] ?? '' ) );
                        $label = sanitize_text_field( (string) ( $item['label'] ?? '' ) );
                        if ( '' === $key || '' === $label ) {
                            continue;
                        }
                        ?>
                        <li>
                            <span data-workspace="<?php echo esc_attr( $key ); ?>"<?php echo $key === $active ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <dl class="mvm-v5-preview__summary">
                <?php foreach ( array( 'total' => 'Actief', 'urgent' => 'Urgent', 'blocked' => 'Geblokkeerd', 'protected' => 'Bronbeschermd' ) as $key => $label ) : ?>
                    <div>
                        <dt><?php echo esc_html( $label ); ?></dt>
                        <dd><?php echo esc_html( (string) max( 0, (int) ( $summary[ $key ] ?? 0 ) ) ); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <div class="mvm-v5-preview__lanes">
                <?php foreach ( self::lane_labels() as $lane_key => $lane_label ) :
                    $cards = is_array( $lanes[ $lane_key ] ?? null ) ? $lanes[ $lane_key ] : array();
                    ?>
                    <section class="mvm-v5-preview__lane" data-lane="<?php echo esc_attr( $lane_key ); ?>" aria-labelledby="mvm-v5-lane-<?php echo esc_attr( $lane_key ); ?>">
                        <h2 id="mvm-v5-lane-<?php echo esc_attr( $lane_key ); ?>"><?php echo esc_html( $lane_label ); ?></h2>
                        <?php if ( array() === $cards ) : ?>
                            <p><?php echo esc_html( 'Geen taken in deze categorie.' ); ?></p>
                        <?php else : ?>
                            <ol>
                                <?php foreach ( array_slice( $cards, 0, 25 ) as $card ) :
                                    if ( ! is_array( $card ) ) {
                                        continue;
                                    }
                                    echo self::render_card( $card ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes all card fields.
                                endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /** @return array<string,string> */
    private static function lane_labels(): array {
        return array(
            'blocked'  => 'Geblokkeerd',
            'overdue'  => 'Over tijd',
            'today'    => 'Vandaag',
            'upcoming' => 'Daarna',
        );
    }

    /** @param array<string,mixed> $card */
    private static function render_card( array $card ): string {
        $title = sanitize_text_field( (string) ( $card['title'] ?? '' ) );
        if ( '' === $title ) {
            return '';
        }

        $meta = array_values( array_filter( array(
            sanitize_key( (string) ( $card['domain'] ?? '' ) ),
            sanitize_key( (string) ( $card['workflowState'] ?? '' ) ),
            sanitize_key( (string) ( $card['priority'] ?? '' ) ),
        ) ) );
        $reasons = is_array( $card['reasons'] ?? null ) ? $card['reasons'] : array();
        $deadline = sanitize_text_field( (string) ( $card['deadlineUtc'] ?? '' ) );
        $blocked = sanitize_textarea_field( (string) ( $card['blockedReason'] ?? '' ) );

        ob_start();
        ?>
        <li class="mvm-v5-preview__card">
            <article>
                <h3><?php echo esc_html( $title ); ?></h3>
                <?php if ( array() !== $meta ) : ?>
                    <p><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
                <?php endif; ?>
                <?php if ( '' !== $deadline ) : ?>
                    <p><strong><?php echo esc_html( 'Deadline:' ); ?></strong> <?php echo esc_html( $deadline ); ?></p>
                <?php endif; ?>
                <?php if ( '' !== $blocked ) : ?>
                    <p><strong><?php echo esc_html( 'Blokkade:' ); ?></strong> <?php echo esc_html( $blocked ); ?></p>
                <?php endif; ?>
                <?php if ( array() !== $reasons ) : ?>
                    <details>
                        <summary><?php echo esc_html( 'Waarom staat dit bovenaan?' ); ?></summary>
                        <ul>
                            <?php foreach ( array_slice( $reasons, 0, 6 ) as $reason ) : ?>
                                <li><?php echo esc_html( sanitize_text_field( (string) $reason ) ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </article>
        </li>
        <?php
        return (string) ob_get_clean();
    }

    private function __construct() {}
}
