<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Personalized_Feed_UI {
    public static function init(): void {
        add_shortcode( 'mvm_mijn_mierlo_feed', array( __CLASS__, 'shortcode' ) );
        add_filter( 'do_shortcode_tag', array( __CLASS__, 'append_to_my_mierlo' ), 10, 4 );
    }

    public static function append_to_my_mierlo( string $output, string $tag, array|string $attr, array $match ): string {
        unset( $attr, $match );
        if ( 'mvm_mijn_mierlo' !== $tag || ! is_user_logged_in() ) {
            return $output;
        }
        return $output . self::render( 12 );
    }

    public static function shortcode( array|string $atts = array() ): string {
        if ( ! is_user_logged_in() ) {
            return '<section class="mvm-public-v1 mvm-public-v1__box"><h2>Mijn Mierlo</h2><p>Log in om je persoonlijke nieuwsselectie te bekijken.</p></section>';
        }
        $atts = shortcode_atts( array( 'limit' => 12 ), is_array( $atts ) ? $atts : array(), 'mvm_mijn_mierlo_feed' );
        return self::render( min( 30, max( 1, absint( $atts['limit'] ) ) ) );
    }

    private static function render( int $limit ): string {
        wp_enqueue_style( 'mvm-hub4-public-v1', MVM_HUB4_URL . 'assets/public-v1.css', array(), MVM_HUB4_VERSION );
        $feed = MvM_Hub4_Personalization::feed( $limit );
        $items = (array) ( $feed['items'] ?? array() );
        $personalized = ! empty( $feed['personalized'] );

        ob_start();
        ?>
        <section class="mvm-public-v1 mvm-public-v1__box" aria-labelledby="mvm-my-feed-heading">
            <h2 id="mvm-my-feed-heading"><?php echo esc_html( $personalized ? 'Jouw nieuws uit Mierlo' : 'Het laatste nieuws uit Mierlo' ); ?></h2>
            <p class="mvm-public-v1__intro">
                <?php echo esc_html( $personalized ? 'Gebaseerd op de onderwerpen die je bij Mijn Mierlo volgt.' : 'Kies hierboven onderwerpen om deze selectie persoonlijk te maken.' ); ?>
            </p>
            <?php if ( ! $items ) : ?>
                <p>Er zijn op dit moment geen passende nieuwsberichten.</p>
            <?php else : ?>
                <div class="mvm-public-v1__grid">
                    <?php foreach ( $items as $item ) : ?>
                        <article class="mvm-public-v1__card mvm-public-v1__news-card">
                            <?php if ( ! empty( $item['imageUrl'] ) ) : ?>
                                <a class="mvm-public-v1__thumb" href="<?php echo esc_url( (string) ( $item['url'] ?? '' ) ); ?>" tabindex="-1" aria-hidden="true">
                                    <img src="<?php echo esc_url( (string) $item['imageUrl'] ); ?>" alt="<?php echo esc_attr( (string) ( $item['imageAlt'] ?? '' ) ); ?>" loading="lazy" decoding="async">
                                </a>
                            <?php endif; ?>
                            <div class="mvm-public-v1__news-copy">
                                <h3><a href="<?php echo esc_url( (string) ( $item['url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></a></h3>
                                <?php if ( ! empty( $item['dateUtc'] ) ) : ?><p class="mvm-public-v1__meta"><?php echo esc_html( self::format_date( (string) $item['dateUtc'] ) ); ?></p><?php endif; ?>
                                <?php if ( ! empty( $item['excerpt'] ) ) : ?><p><?php echo esc_html( (string) $item['excerpt'] ); ?></p><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function format_date( string $utc ): string {
        $timestamp = strtotime( $utc . ' UTC' );
        if ( false === $timestamp ) {
            return '';
        }
        return wp_date( get_option( 'date_format' ), $timestamp, wp_timezone() );
    }
}
