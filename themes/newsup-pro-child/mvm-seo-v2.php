<?php
/**
 * Small Yoast SEO completion layer for Mierlo voor Mierlo.
 *
 * Yoast remains the sole metadata/schema provider. This module only supplies a
 * meta-description value when Yoast has none for public event/encyclopedia items.
 *
 * @package MierloVoorMierlo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical fallback for retired public PeepSo route aliases.
 *
 * Hub 1.1 owns the same redirects at platform level. This theme fallback runs
 * afterwards, so a live Hub router wins and the child theme only fills the gap
 * when that Hub release is not yet active.
 *
 * @return void
 */
function mvm_seo_v2_redirect_legacy_public_routes() {
	$request_path = isset( $_SERVER['REQUEST_URI'] )
		? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH )
		: '';
	$request_path = trailingslashit( $request_path );

	$redirects = array(
		'/community/' => '/activity/',
		'/groups/'    => '/groepen/',
		'/pages/'     => '/paginas/',
	);

	if ( ! isset( $redirects[ $request_path ] ) ) {
		return;
	}

	wp_safe_redirect( home_url( $redirects[ $request_path ] ), 301, 'MvM canonical route' );
	exit;
}
add_action( 'template_redirect', 'mvm_seo_v2_redirect_legacy_public_routes', -80 );

/**
 * Post types for which MvM may provide a missing Yoast meta description.
 *
 * @return string[]
 */
function mvm_seo_v2_description_post_types() {
	return array(
		'event_listing',
		'mvm_encyclopedie',
		'mvm_persoon',
		'mvm_locatie',
		'mvm_gebouw',
		'mvm_gebeurtenis',
		'mvm_vereniging',
		'mvm_bedrijf',
		'mvm_beeld',
		'mvm_bron',
	);
}

/**
 * Trim plain text to a search-snippet-friendly length without cutting a word.
 *
 * @param string $text Text.
 * @param int    $limit Character limit.
 * @return string
 */
function mvm_seo_v2_trim_description( $text, $limit = 155 ) {
	$text = preg_replace( '/\s+/u', ' ', trim( (string) $text ) );
	if ( '' === $text ) {
		return '';
	}

	$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	if ( $length <= $limit ) {
		return $text;
	}

	$trimmed = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit + 1, 'UTF-8' ) : substr( $text, 0, $limit + 1 );
	$space   = function_exists( 'mb_strrpos' ) ? mb_strrpos( $trimmed, ' ', 0, 'UTF-8' ) : strrpos( $trimmed, ' ' );
	if ( false !== $space && $space > (int) ( $limit * 0.65 ) ) {
		$trimmed = function_exists( 'mb_substr' ) ? mb_substr( $trimmed, 0, $space, 'UTF-8' ) : substr( $trimmed, 0, $space );
	} else {
		$trimmed = function_exists( 'mb_substr' ) ? mb_substr( $trimmed, 0, $limit, 'UTF-8' ) : substr( $trimmed, 0, $limit );
	}

	return rtrim( $trimmed, " \t\n\r\0\x0B,.;:-" ) . '…';
}

/**
 * Build a concise description from existing public post data.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function mvm_seo_v2_build_description( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$text = '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
	$text = strip_shortcodes( (string) $text );
	$text = wp_strip_all_tags( $text, true );
	$text = preg_replace( '/\s+/u', ' ', trim( $text ) );

	$title = wp_strip_all_tags( get_the_title( $post ) );
	if ( '' !== $title && '' !== $text ) {
		$text = preg_replace( '/^' . preg_quote( $title, '/' ) . '\s*/iu', '', $text, 1 );
	}

	if ( 'event_listing' === $post->post_type ) {
		$location = sanitize_text_field( (string) get_post_meta( $post->ID, '_event_location', true ) );
		if ( '' !== $location && false === stripos( $text, $location ) ) {
			$text = rtrim( $text, '. ' ) . '. Locatie: ' . $location . '.';
		}
	}

	return mvm_seo_v2_trim_description( $text, 155 );
}

/**
 * Supply a description only when Yoast has not already resolved one.
 *
 * @param string $description Existing Yoast description.
 * @return string
 */
function mvm_seo_v2_yoast_metadesc( $description ) {
	if ( '' !== trim( (string) $description ) ) {
		return $description;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id && isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ) {
		$post_id = $GLOBALS['post']->ID;
	}

	$post = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
		return $description;
	}

	if ( ! in_array( $post->post_type, mvm_seo_v2_description_post_types(), true ) ) {
		return $description;
	}

	$fallback = mvm_seo_v2_build_description( $post );
	return '' !== $fallback ? $fallback : $description;
}
add_filter( 'wpseo_metadesc', 'mvm_seo_v2_yoast_metadesc', 20 );

/**
 * Keep social-preview descriptions free from frontend controls such as
 * bookmark/login calls-to-action that can leak into Yoast's generated excerpt.
 * Existing clean social descriptions are preserved.
 *
 * @param string $description Existing social description.
 * @return string
 */
function mvm_seo_v2_social_description( $description ) {
	$post_id = get_queried_object_id();
	if ( ! $post_id && isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ) {
		$post_id = $GLOBALS['post']->ID;
	}

	$post = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
		return $description;
	}
	if ( ! in_array( $post->post_type, mvm_seo_v2_description_post_types(), true ) ) {
		return $description;
	}

	$current = trim( wp_strip_all_tags( (string) $description ) );
	$polluted = '' === $current
		|| false !== stripos( $current, 'Inloggen om op te slaan' )
		|| false !== stripos( $current, 'Inloggen om ' . get_the_title( $post ) . ' op te slaan' );

	if ( ! $polluted ) {
		return $description;
	}

	$fallback = mvm_seo_v2_build_description( $post );
	return '' !== $fallback ? $fallback : $description;
}
add_filter( 'wpseo_opengraph_desc', 'mvm_seo_v2_social_description', 20 );
add_filter( 'wpseo_twitter_description', 'mvm_seo_v2_social_description', 20 );
