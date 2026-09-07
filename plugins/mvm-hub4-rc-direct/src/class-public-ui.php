<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Public_UI {
    private const RATE_WINDOW = 900;
    private const RATE_LIMIT  = 5;

    public static function init(): void {
        add_shortcode( 'mvm_tip_de_redactie', array( __CLASS__, 'tip_shortcode' ) );
        add_shortcode( 'mvm_mijn_mierlo', array( __CLASS__, 'my_mierlo_shortcode' ) );
        add_shortcode( 'mvm_dossiers', array( __CLASS__, 'dossiers_shortcode' ) );

        add_action( 'admin_post_mvm_hub4_public_tip', array( __CLASS__, 'handle_tip' ) );
        add_action( 'admin_post_nopriv_mvm_hub4_public_tip', array( __CLASS__, 'handle_tip' ) );
        add_action( 'admin_post_mvm_hub4_public_correction', array( __CLASS__, 'handle_correction' ) );
        add_action( 'admin_post_nopriv_mvm_hub4_public_correction', array( __CLASS__, 'handle_correction' ) );
        add_action( 'admin_post_mvm_hub4_my_mierlo', array( __CLASS__, 'handle_my_mierlo' ) );

        add_filter( 'the_content', array( __CLASS__, 'append_correction_block' ), 35 );
    }

    public static function tip_shortcode(): string {
        self::enqueue_style();
        $state = isset( $_GET['mvm_tip'] ) ? sanitize_key( (string) wp_unslash( $_GET['mvm_tip'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only result flag.
        $redirect = self::current_url();

        ob_start();
        ?>
        <section class="mvm-public-v1 mvm-public-v1__box" aria-labelledby="mvm-tip-heading">
            <h2 id="mvm-tip-heading">Tip de redactie</h2>
            <p class="mvm-public-v1__intro">Heb je nieuws, een foto, evenement, bron of veiligheidssignaal uit Mierlo? Stuur het rechtstreeks naar de redactie.</p>
            <p class="mvm-public-v1__notice mvm-public-v1__notice--urgent"><strong>Bij direct gevaar of een noodsituatie: bel 112.</strong> Dit formulier is geen meldkamer.</p>
            <?php if ( 'accepted' === $state ) : ?>
                <p class="mvm-public-v1__notice">Bedankt. Je tip is ontvangen door de redactie.</p>
            <?php elseif ( 'error' === $state ) : ?>
                <p class="mvm-public-v1__notice">De tip kon niet worden verwerkt. Controleer de velden of probeer het later opnieuw.</p>
            <?php endif; ?>
            <form class="mvm-public-v1__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="mvm_hub4_public_tip">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>">
                <?php wp_nonce_field( 'mvm_hub4_public_tip', 'mvm_hub4_public_nonce' ); ?>
                <label>Type
                    <select name="kind">
                        <option value="news">Nieuws</option>
                        <option value="photo">Foto</option>
                        <option value="event">Evenement</option>
                        <option value="source">Bron</option>
                        <option value="safety">112 / veiligheid</option>
                        <option value="general">Algemeen</option>
                    </select>
                </label>
                <label>Titel
                    <input type="text" name="title" minlength="3" maxlength="190" required>
                </label>
                <label class="is-wide">Wat is er aan de hand?
                    <textarea name="summary" maxlength="8000" required></textarea>
                </label>
                <label>Locatie
                    <input type="text" name="location" maxlength="190">
                </label>
                <label>Bron-URL
                    <input type="url" name="source_url" maxlength="2048">
                </label>
                <label>Naam <small>(optioneel)</small>
                    <input type="text" name="contact_name" maxlength="190" autocomplete="name">
                </label>
                <label>E-mail <small>(optioneel)</small>
                    <input type="email" name="contact_email" maxlength="190" autocomplete="email">
                </label>
                <label>Telefoon <small>(optioneel)</small>
                    <input type="tel" name="contact_phone" maxlength="80" autocomplete="tel">
                </label>
                <label class="mvm-public-v1__honeypot" aria-hidden="true">Website
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </label>
                <div class="mvm-public-v1__actions"><button class="mvm-public-v1__button" type="submit">Stuur tip</button></div>
            </form>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public static function my_mierlo_shortcode(): string {
        self::enqueue_style();
        if ( ! is_user_logged_in() ) {
            return '<section class="mvm-public-v1 mvm-public-v1__box"><h2>Mijn Mierlo</h2><p>Log in om onderwerpen te volgen.</p><a class="mvm-public-v1__button" href="' . esc_url( wp_login_url( self::current_url() ) ) . '">Inloggen</a></section>';
        }

        $preferences = MvM_Hub4_Personalization::get_current();
        $selected    = array_map( 'absint', (array) ( $preferences['topics'] ?? array() ) );
        $available   = (array) ( $preferences['available'] ?? array() );
        $state       = isset( $_GET['mvm_topics'] ) ? sanitize_key( (string) wp_unslash( $_GET['mvm_topics'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only result flag.

        ob_start();
        ?>
        <section class="mvm-public-v1 mvm-public-v1__box" aria-labelledby="mvm-my-heading">
            <h2 id="mvm-my-heading">Mijn Mierlo</h2>
            <p class="mvm-public-v1__intro">Kies de onderwerpen die je het liefst volgt. Je keuze wordt alleen aan je eigen account gekoppeld.</p>
            <?php if ( 'saved' === $state ) : ?><p class="mvm-public-v1__notice">Je voorkeuren zijn opgeslagen.</p><?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="mvm_hub4_my_mierlo">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url( self::current_url() ); ?>">
                <?php wp_nonce_field( 'mvm_hub4_my_mierlo', 'mvm_hub4_my_nonce' ); ?>
                <div class="mvm-public-v1__topics">
                    <?php foreach ( $available as $topic ) : ?>
                        <?php $term_id = absint( $topic['id'] ?? 0 ); ?>
                        <label class="mvm-public-v1__topic">
                            <input type="checkbox" name="topics[]" value="<?php echo esc_attr( (string) $term_id ); ?>" <?php checked( in_array( $term_id, $selected, true ) ); ?>>
                            <?php echo esc_html( (string) ( $topic['name'] ?? '' ) ); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p><button class="mvm-public-v1__button" type="submit">Voorkeuren opslaan</button></p>
            </form>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public static function dossiers_shortcode(): string {
        self::enqueue_style();
        $slug = isset( $_GET['mvm_dossier'] ) ? sanitize_title( (string) wp_unslash( $_GET['mvm_dossier'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public navigation.

        if ( '' !== $slug ) {
            $dossier = MvM_Hub4_Dossiers::public_by_slug( $slug );
            if ( is_wp_error( $dossier ) ) {
                return '<section class="mvm-public-v1 mvm-public-v1__box"><h2>Mierlo-dossier</h2><p>Dit dossier is niet beschikbaar.</p></section>';
            }
            ob_start();
            ?>
            <article class="mvm-public-v1 mvm-public-v1__box">
                <p><a href="<?php echo esc_url( remove_query_arg( 'mvm_dossier', self::current_url() ) ); ?>">← Alle dossiers</a></p>
                <h2><?php echo esc_html( (string) $dossier['title'] ); ?></h2>
                <p class="mvm-public-v1__intro"><?php echo esc_html( (string) $dossier['summary'] ); ?></p>
                <?php if ( ! empty( $dossier['publishedAtUtc'] ) ) : ?><p class="mvm-public-v1__meta">Gepubliceerd: <?php echo esc_html( self::format_date( (string) $dossier['publishedAtUtc'] ) ); ?></p><?php endif; ?>
                <?php if ( ! empty( $dossier['links'] ) ) : ?>
                    <h3>Bij dit dossier</h3>
                    <ul>
                        <?php foreach ( (array) $dossier['links'] as $link ) : ?>
                            <li><a href="<?php echo esc_url( (string) ( $link['url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $link['label'] ?: ucfirst( (string) $link['objectType'] ) ) ); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
            <?php
            return (string) ob_get_clean();
        }

        $items = MvM_Hub4_Dossiers::public_list( 50 );
        ob_start();
        ?>
        <section class="mvm-public-v1 mvm-public-v1__box" aria-labelledby="mvm-dossiers-heading">
            <h2 id="mvm-dossiers-heading">Mierlo-dossiers</h2>
            <p class="mvm-public-v1__intro">Nieuws en achtergrond gebundeld rond onderwerpen die in Mierlo spelen.</p>
            <?php if ( ! $items ) : ?>
                <p>Er zijn nog geen openbare dossiers.</p>
            <?php else : ?>
                <div class="mvm-public-v1__grid">
                    <?php foreach ( $items as $item ) : ?>
                        <article class="mvm-public-v1__card">
                            <h3><a href="<?php echo esc_url( add_query_arg( 'mvm_dossier', (string) $item['slug'], self::current_url() ) ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a></h3>
                            <p><?php echo esc_html( (string) $item['summary'] ); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public static function append_correction_block( string $content ): string {
        if ( is_admin() || is_feed() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $post_id = get_the_ID();
        if ( $post_id < 1 ) {
            return $content;
        }
        self::enqueue_style();
        $corrections = MvM_Hub4_Corrections::public_for_post( $post_id );
        $updated     = (string) get_post_meta( $post_id, '_mvm_hub4_last_material_update_utc', true );
        $state       = isset( $_GET['mvm_correction'] ) ? sanitize_key( (string) wp_unslash( $_GET['mvm_correction'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only result flag.

        ob_start();
        ?>
        <section class="mvm-public-v1 mvm-public-v1__box" aria-labelledby="mvm-correction-heading">
            <h3 id="mvm-correction-heading">Correcties & transparantie</h3>
            <?php if ( '' !== $updated ) : ?><p class="mvm-public-v1__meta">Dit artikel is inhoudelijk bijgewerkt op <?php echo esc_html( self::format_date( $updated ) ); ?>.</p><?php endif; ?>
            <?php if ( $corrections ) : ?>
                <div class="mvm-public-v1__correction-list">
                    <?php foreach ( $corrections as $correction ) : ?>
                        <div class="mvm-public-v1__correction"><strong>Correctie</strong><br><?php echo esc_html( (string) $correction['publicNote'] ); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ( 'accepted' === $state ) : ?><p class="mvm-public-v1__notice">Bedankt. Je melding is ontvangen door de redactie.</p><?php endif; ?>
            <details>
                <summary><strong>Fout gezien?</strong></summary>
                <form class="mvm-public-v1__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="mvm_hub4_public_correction">
                    <input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post_id ); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink( $post_id ) ?: home_url( '/' ) ); ?>">
                    <?php wp_nonce_field( 'mvm_hub4_public_correction', 'mvm_hub4_correction_nonce' ); ?>
                    <label class="is-wide">Wat klopt er volgens jou niet?
                        <textarea name="message" minlength="10" maxlength="8000" required></textarea>
                    </label>
                    <label>Naam <small>(optioneel)</small><input type="text" name="contact_name" maxlength="190" autocomplete="name"></label>
                    <label>E-mail <small>(optioneel)</small><input type="email" name="contact_email" maxlength="190" autocomplete="email"></label>
                    <label class="mvm-public-v1__honeypot" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    <div class="mvm-public-v1__actions"><button class="mvm-public-v1__button" type="submit">Meld mogelijke fout</button></div>
                </form>
            </details>
        </section>
        <?php
        return $content . (string) ob_get_clean();
    }

    public static function handle_tip(): never {
        check_admin_referer( 'mvm_hub4_public_tip', 'mvm_hub4_public_nonce' );
        $redirect = self::safe_redirect_from_request();
        if ( ! self::allow_public_submission( 'tip' ) ) {
            self::redirect_with( $redirect, 'mvm_tip', 'error' );
        }

        $input = array(
            'kind'         => sanitize_key( (string) ( $_POST['kind'] ?? 'general' ) ),
            'title'        => sanitize_text_field( (string) ( $_POST['title'] ?? '' ) ),
            'summary'      => sanitize_textarea_field( (string) ( $_POST['summary'] ?? '' ) ),
            'location'     => sanitize_text_field( (string) ( $_POST['location'] ?? '' ) ),
            'sourceUrl'    => esc_url_raw( (string) ( $_POST['source_url'] ?? '' ) ),
            'contactName'  => sanitize_text_field( (string) ( $_POST['contact_name'] ?? '' ) ),
            'contactEmail' => sanitize_email( (string) ( $_POST['contact_email'] ?? '' ) ),
            'contactPhone' => sanitize_text_field( (string) ( $_POST['contact_phone'] ?? '' ) ),
        );
        $result = MvM_Hub4_Signals::create_public( $input );
        self::redirect_with( $redirect, 'mvm_tip', is_wp_error( $result ) ? 'error' : 'accepted' );
    }

    public static function handle_correction(): never {
        check_admin_referer( 'mvm_hub4_public_correction', 'mvm_hub4_correction_nonce' );
        $redirect = self::safe_redirect_from_request();
        if ( ! self::allow_public_submission( 'correction' ) ) {
            self::redirect_with( $redirect, 'mvm_correction', 'error' );
        }
        $result = MvM_Hub4_Corrections::create_public(
            array(
                'postId'       => absint( $_POST['post_id'] ?? 0 ),
                'message'      => sanitize_textarea_field( (string) ( $_POST['message'] ?? '' ) ),
                'contactName'  => sanitize_text_field( (string) ( $_POST['contact_name'] ?? '' ) ),
                'contactEmail' => sanitize_email( (string) ( $_POST['contact_email'] ?? '' ) ),
            )
        );
        self::redirect_with( $redirect, 'mvm_correction', is_wp_error( $result ) ? 'error' : 'accepted' );
    }

    public static function handle_my_mierlo(): never {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            auth_redirect();
        }
        check_admin_referer( 'mvm_hub4_my_mierlo', 'mvm_hub4_my_nonce' );
        $topics = isset( $_POST['topics'] ) && is_array( $_POST['topics'] ) ? array_map( 'absint', wp_unslash( $_POST['topics'] ) ) : array();
        MvM_Hub4_Personalization::update_current( $topics );
        self::redirect_with( self::safe_redirect_from_request(), 'mvm_topics', 'saved' );
    }

    private static function allow_public_submission( string $kind ): bool {
        $honeypot = isset( $_POST['website'] ) ? trim( sanitize_text_field( (string) wp_unslash( $_POST['website'] ) ) ) : '';
        if ( '' !== $honeypot ) {
            return false;
        }
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        $fingerprint = hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
        $key = 'mvm_hub4_ui_' . sanitize_key( $kind ) . '_' . substr( $fingerprint, 0, 32 );
        $count = (int) get_transient( $key );
        if ( $count >= self::RATE_LIMIT ) {
            return false;
        }
        set_transient( $key, $count + 1, self::RATE_WINDOW );
        return true;
    }

    private static function enqueue_style(): void {
        wp_enqueue_style( 'mvm-hub4-public-v1', MVM_HUB4_URL . 'assets/public-v1.css', array(), MVM_HUB4_VERSION );
    }

    private static function current_url(): string {
        $url = is_singular() ? get_permalink() : home_url( '/' );
        return $url ? esc_url_raw( $url ) : home_url( '/' );
    }

    private static function safe_redirect_from_request(): string {
        $requested = isset( $_POST['redirect_to'] ) ? esc_url_raw( (string) wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );
        return wp_validate_redirect( $requested, home_url( '/' ) );
    }

    private static function redirect_with( string $url, string $key, string $value ): never {
        wp_safe_redirect( add_query_arg( $key, $value, $url ) );
        exit;
    }

    private static function format_date( string $value ): string {
        $timestamp = strtotime( $value . ( str_contains( $value, 'Z' ) ? '' : ' UTC' ) );
        return false === $timestamp ? $value : wp_date( 'j F Y \o\m H:i', $timestamp );
    }
}
