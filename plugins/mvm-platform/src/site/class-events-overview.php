<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Platform_Events_Overview {
	public static function boot(): void {
		remove_shortcode( 'mvm_events_overview' );
		add_shortcode( 'mvm_events_overview', array( __CLASS__, 'shortcode' ) );
	}

	public static function shortcode( array|string $atts = array() ): string {
		$atts  = shortcode_atts( array( 'limit' => 30 ), is_array( $atts ) ? $atts : array(), 'mvm_events_overview' );
		$limit = min( 60, max( 1, absint( $atts['limit'] ) ) );
		self::enqueue_style();

		$query = new WP_Query(
			array(
				'post_type'           => 'event_listing',
				'post_status'         => 'publish',
				'posts_per_page'      => $limit,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'meta_key'            => '_event_start_date',
				'orderby'             => 'meta_value',
				'order'               => 'ASC',
				'meta_type'           => 'DATETIME',
				'meta_query'          => array(
					'relation' => 'OR',
					array(
						'key'     => '_event_end_date',
						'value'   => current_time( 'Y-m-d H:i:s' ),
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
					array(
						'key'     => '_event_end_date',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		ob_start();
		?>
		<section class="mvm-events-v2" aria-labelledby="mvm-events-v2-title">
			<header class="mvm-events-v2__hero">
				<p class="mvm-events-v2__eyebrow">Agenda · Mierlo</p>
				<h1 id="mvm-events-v2-title">Evenementen</h1>
				<p>Bekijk komende activiteiten en evenementen in Mierlo. Open een evenement voor alle informatie en reacties.</p>
			</header>
			<?php if ( ! $query->posts ) : ?>
				<p class="mvm-events-v2__empty">Er staan momenteel geen komende evenementen ingepland.</p>
			<?php else : ?>
				<div class="mvm-events-v2__grid">
					<?php foreach ( $query->posts as $post ) : ?>
						<?php
						if ( ! $post instanceof WP_Post ) {
							continue;
						}
						$url      = get_permalink( $post );
						$title    = get_the_title( $post );
						$start    = (string) get_post_meta( $post->ID, '_event_start_date', true );
						$end      = (string) get_post_meta( $post->ID, '_event_end_date', true );
						$location = sanitize_text_field( (string) get_post_meta( $post->ID, '_event_location', true ) );
						$image    = get_the_post_thumbnail_url( $post, 'medium_large' );
						?>
						<article class="mvm-events-v2__card">
							<?php if ( $image && $url ) : ?>
								<a class="mvm-events-v2__image" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
									<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async">
								</a>
							<?php endif; ?>
							<div class="mvm-events-v2__body">
								<p class="mvm-events-v2__date"><?php echo esc_html( self::format_local_date( $start, $end ) ); ?></p>
								<h2><a href="<?php echo esc_url( $url ?: '' ); ?>"><?php echo esc_html( $title ); ?></a></h2>
								<?php if ( '' !== $location ) : ?><p class="mvm-events-v2__location"><?php echo esc_html( $location ); ?></p><?php endif; ?>
								<a class="mvm-events-v2__cta" href="<?php echo esc_url( $url ?: '' ); ?>">Bekijk evenement →</a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
		wp_reset_postdata();
		return (string) ob_get_clean();
	}

	private static function parse_local( string $value ): ?DateTimeImmutable {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		$timezone = wp_timezone();
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, $timezone );
		if ( false !== $date ) {
			return $date;
		}
		try {
			return new DateTimeImmutable( $value, $timezone );
		} catch ( Throwable $error ) {
			return null;
		}
	}

	private static function format_local_date( string $start, string $end ): string {
		$start_date = self::parse_local( $start );
		if ( ! $start_date ) {
			return 'Datum volgt';
		}
		$end_date = self::parse_local( $end );
		$timezone = wp_timezone();
		$date = wp_date( 'j F Y', $start_date->getTimestamp(), $timezone );
		$start_time = wp_date( 'H:i', $start_date->getTimestamp(), $timezone );
		if ( ! $end_date ) {
			return $date . ' · ' . $start_time;
		}
		$same_day = wp_date( 'Y-m-d', $start_date->getTimestamp(), $timezone ) === wp_date( 'Y-m-d', $end_date->getTimestamp(), $timezone );
		if ( $same_day ) {
			return $date . ' · ' . $start_time . '–' . wp_date( 'H:i', $end_date->getTimestamp(), $timezone );
		}
		return $date . ' · ' . $start_time . ' – ' . wp_date( 'j F Y · H:i', $end_date->getTimestamp(), $timezone );
	}

	private static function enqueue_style(): void {
		$path = MVM_PLATFORM_DIR . 'assets/events-overview.css';
		wp_enqueue_style(
			'mvm-events-overview',
			MVM_PLATFORM_URL . 'assets/events-overview.css',
			array(),
			is_readable( $path ) ? (string) filemtime( $path ) : MVM_PLATFORM_VERSION
		);
	}
}
