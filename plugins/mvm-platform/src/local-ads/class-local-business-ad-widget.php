<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Local_Business_Ad_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'mvm_local_business_ad',
			'MvM · Lokale ondernemer advertentie',
			array(
				'description' => 'Toont een duidelijk gelabelde lokale ondernemersadvertentie in MvM-stijl.',
			)
		);
	}

	public function widget( $args, $instance ): void {
		$placement = sanitize_key( (string) ( $instance['placement'] ?? 'sidebar' ) );
		$variant   = sanitize_key( (string) ( $instance['variant'] ?? 'compact' ) );
		$html      = MvM_Local_Business_Ads::render( $placement, $variant );
		if ( '' === $html ) {
			return;
		}
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted theme wrapper.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes dynamic values.
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted theme wrapper.
	}

	public function form( $instance ): void {
		$placement = sanitize_key( (string) ( $instance['placement'] ?? 'sidebar' ) );
		$variant   = sanitize_key( (string) ( $instance['variant'] ?? 'compact' ) );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'placement' ) ); ?>">Plaatsing</label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'placement' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'placement' ) ); ?>">
				<?php foreach ( MvM_Local_Business_Ads::placements() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $placement, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'variant' ) ); ?>">Weergave</label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'variant' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'variant' ) ); ?>">
				<option value="wide" <?php selected( $variant, 'wide' ); ?>>Breed</option>
				<option value="compact" <?php selected( $variant, 'compact' ); ?>>Compact</option>
				<option value="inline" <?php selected( $variant, 'inline' ); ?>>Inline</option>
			</select>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ): array {
		unset( $old_instance );
		$placement = sanitize_key( (string) ( $new_instance['placement'] ?? 'sidebar' ) );
		$variant   = sanitize_key( (string) ( $new_instance['variant'] ?? 'compact' ) );
		if ( ! isset( MvM_Local_Business_Ads::placements()[ $placement ] ) ) {
			$placement = 'sidebar';
		}
		if ( ! in_array( $variant, array( 'wide', 'compact', 'inline' ), true ) ) {
			$variant = 'compact';
		}
		return array(
			'placement' => $placement,
			'variant'   => $variant,
		);
	}
}
