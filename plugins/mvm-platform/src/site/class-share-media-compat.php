<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Platform_Share_Media_Compat {
	private const FALLBACK_IMAGE = 'https://www.mierlovoormierlo.nl/wp-content/uploads/2026/08/mvm-facebook-og-final-v3.jpg';

	public static function boot(): void {
		// During the staged migration the legacy Code Snippet may still be active.
		// Avoid duplicate Open Graph output until that snippet is disabled.
		if ( function_exists( 'mvm_share_jpeg_url_v1' ) ) {
			return;
		}

		add_filter( 'wpseo_opengraph_image', array( __CLASS__, 'filter_share_image' ), 100 );
		add_filter( 'wpseo_twitter_image', array( __CLASS__, 'filter_share_image' ), 100 );
		add_action( 'wp_head', array( __CLASS__, 'print_secure_og_image' ), 2 );
	}

	public static function filter_share_image( $image ) {
		$share = self::share_image_url();
		return $share ?: $image;
	}

	public static function print_secure_og_image(): void {
		if ( is_admin() ) {
			return;
		}

		$image = self::share_image_url();
		if ( ! $image ) {
			return;
		}

		echo '<meta property="og:image:secure_url" content="' . esc_url( $image ) . '" />' . "\n";
	}

	private static function share_image_url(): string {
		if ( ! is_singular() ) {
			return self::FALLBACK_IMAGE;
		}

		$post_id       = get_queried_object_id();
		$attachment_id = $post_id ? get_post_thumbnail_id( $post_id ) : 0;
		$jpeg          = $attachment_id ? self::jpeg_url( $attachment_id ) : '';

		return $jpeg ?: self::FALLBACK_IMAGE;
	}

	private static function jpeg_url( int $attachment_id ): string {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return '';
		}

		$mime = (string) get_post_mime_type( $attachment_id );
		$url  = (string) wp_get_attachment_url( $attachment_id );
		if ( in_array( $mime, array( 'image/jpeg', 'image/jpg' ), true ) && $url ) {
			return $url;
		}
		if ( 0 !== strpos( $mime, 'image/' ) ) {
			return '';
		}

		$source = get_attached_file( $attachment_id );
		if ( ! $source || ! is_readable( $source ) ) {
			return '';
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}

		$directory = trailingslashit( $uploads['basedir'] ) . 'mvm-share';
		if ( ! wp_mkdir_p( $directory ) ) {
			return '';
		}

		$filename     = 'og-' . $attachment_id . '-1200x630.jpg';
		$destination  = trailingslashit( $directory ) . $filename;
		$destination_url = trailingslashit( $uploads['baseurl'] ) . 'mvm-share/' . $filename;

		if ( is_readable( $destination ) && filesize( $destination ) > 1000 ) {
			return $destination_url;
		}

		$editor = wp_get_image_editor( $source );
		if ( is_wp_error( $editor ) ) {
			return '';
		}

		$editor->resize( 1200, 630, true );
		$editor->set_quality( 88 );
		$saved = $editor->save( $destination, 'image/jpeg' );

		return is_wp_error( $saved ) || empty( $saved['path'] ) ? '' : $destination_url;
	}
}
