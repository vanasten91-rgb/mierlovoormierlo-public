<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Platform_PWA {
	private const MANIFEST_PATH = '/mvm.webmanifest';
	private const SW_PATH       = '/mvm-sw.js';
	private const OFFLINE_PATH  = '/mvm-offline/';

	public static function boot(): void {
		add_action( 'template_redirect', array( __CLASS__, 'serve_special_routes' ), -9999 );
		add_action( 'wp_head', array( __CLASS__, 'head_tags' ), 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_registration' ), 30 );
	}

	private static function request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return (string) wp_parse_url( $uri, PHP_URL_PATH );
	}

	private static function excluded_request(): bool {
		$path = untrailingslashit( self::request_path() );
		return str_starts_with( $path, '/hub' )
			|| str_starts_with( $path, '/wp-admin' )
			|| '/wp-login.php' === $path;
	}

	public static function head_tags(): void {
		if ( self::excluded_request() ) {
			return;
		}
		$icon = self::icon_url();
		echo '<link rel="manifest" href="' . esc_url( home_url( self::MANIFEST_PATH ) ) . '">' . "\n";
		echo '<meta name="theme-color" content="#1966AE">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="Mierlo voor Mierlo">' . "\n";
		if ( $icon ) {
			echo '<link rel="apple-touch-icon" href="' . esc_url( $icon ) . '">' . "\n";
		}
	}

	public static function enqueue_registration(): void {
		if ( self::excluded_request() ) {
			return;
		}
		$file = MVM_PLATFORM_DIR . 'assets/pwa-register.js';
		wp_enqueue_script(
			'mvm-platform-pwa',
			MVM_PLATFORM_URL . 'assets/pwa-register.js',
			array(),
			is_readable( $file ) ? (string) filemtime( $file ) : MVM_PLATFORM_VERSION,
			true
		);
		wp_localize_script(
			'mvm-platform-pwa',
			'MvMPWA',
			array(
				'serviceWorker' => add_query_arg( 'v', rawurlencode( MVM_PLATFORM_VERSION . '-root-home-v2' ), home_url( self::SW_PATH ) ),
				'scope'         => home_url( '/' ),
			)
		);
	}

	public static function serve_special_routes(): void {
		$path = untrailingslashit( self::request_path() );
		if ( untrailingslashit( self::MANIFEST_PATH ) === $path ) {
			self::serve_manifest();
		}
		if ( untrailingslashit( self::SW_PATH ) === $path ) {
			self::serve_service_worker();
		}
		if ( untrailingslashit( self::OFFLINE_PATH ) === $path ) {
			self::serve_offline();
		}
	}

	private static function icon_url(): string {
		$custom = (string) apply_filters( 'mvm_pwa_icon_url', '' );
		if ( $custom ) {
			return esc_url_raw( $custom );
		}
		return esc_url_raw( content_url( 'uploads/2026/08/logomvm.png' ) );
	}

	private static function serve_manifest(): void {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		$icon = self::icon_url();
		$manifest = array(
			'id'               => home_url( '/' ),
			'name'             => 'Mierlo voor Mierlo',
			'short_name'       => 'MvM',
			'description'      => 'Lokaal nieuws, evenementen, encyclopedie en Marktplaats voor Mierlo.',
			'lang'             => 'nl-NL',
			'dir'              => 'ltr',
			'start_url'        => home_url( '/' ),
			'scope'            => home_url( '/' ),
			'display'          => 'standalone',
			'background_color' => '#f5f8fb',
			'theme_color'      => '#1966AE',
			'categories'       => array( 'news', 'social' ),
			'icons'            => $icon ? array(
				array(
					'src'     => $icon,
					'sizes'   => '800x800',
					'type'    => 'image/png',
					'purpose' => 'any',
				),
			) : array(),
			'shortcuts' => array(
				array(
					'name'      => 'Laatste nieuws',
					'short_name'=> 'Nieuws',
					'url'       => home_url( '/?source=pwa-shortcut' ),
				),
				array(
					'name'      => 'Marktplaats',
					'short_name'=> 'Marktplaats',
					'url'       => home_url( '/marktplaats/?source=pwa-shortcut' ),
				),
			),
		);
		echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	private static function serve_service_worker(): void {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=UTF-8' );
		header( 'Service-Worker-Allowed: /' );
		header( 'X-Content-Type-Options: nosniff' );
		$cache = 'mvm-pwa-static-' . preg_replace( '/[^A-Za-z0-9._-]/', '-', MVM_PLATFORM_VERSION );
		$offline = home_url( self::OFFLINE_PATH );
		$assets_prefix = wp_parse_url( MVM_PLATFORM_URL . 'assets/', PHP_URL_PATH );
		?>
'use strict';
const MVM_CACHE = <?php echo wp_json_encode( $cache ); ?>;
const MVM_OFFLINE = <?php echo wp_json_encode( $offline ); ?>;
const MVM_ASSET_PREFIX = <?php echo wp_json_encode( (string) $assets_prefix ); ?>;
const MVM_PRIVATE_PREFIXES = ['/hub', '/wp-admin', '/wp-login.php', '/wp-json', '/community', '/mijn-', '/account'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(MVM_CACHE).then((cache) => cache.add(new Request(MVM_OFFLINE, { credentials: 'omit' }))));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((key) => key.startsWith('mvm-pwa-static-') && key !== MVM_CACHE).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

function mvmPrivatePath(pathname) {
  return MVM_PRIVATE_PREFIXES.some((prefix) => pathname === prefix || pathname.startsWith(prefix + '/'));
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname === '/') return;
  if (mvmPrivatePath(url.pathname)) return;

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match(MVM_OFFLINE)));
    return;
  }

  if (MVM_ASSET_PREFIX && url.pathname.startsWith(MVM_ASSET_PREFIX)) {
    event.respondWith(
      caches.match(request).then((cached) => cached || fetch(request).then((response) => {
        if (!response || !response.ok || response.type !== 'basic') return response;
        const copy = response.clone();
        caches.open(MVM_CACHE).then((cache) => cache.put(request, copy));
        return response;
      }))
    );
  }
});
		<?php
		exit;
	}

	private static function serve_offline(): void {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Referrer-Policy: no-referrer' );
		header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'" );
		?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#1966AE">
<title>Mierlo voor Mierlo – offline</title>
<style>:root{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color-scheme:light dark}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#eef4f8;color:#17222d}.card{width:min(100%,540px);padding:28px;border-radius:16px;background:#fff;border:1px solid #cfdae3;box-shadow:0 18px 50px rgba(15,35,55,.14)}.mark{display:grid;place-items:center;width:52px;height:52px;border-radius:50%;background:#1966AE;color:#fff;font-weight:900}h1{margin:18px 0 8px;font-size:1.8rem}p{line-height:1.6}@media(prefers-color-scheme:dark){body{background:#0e1720;color:#f7fafc}.card{background:#17232d;border-color:#405463}}</style>
</head>
<body><main class="card"><div class="mark" aria-hidden="true">MvM</div><h1>Je bent offline</h1><p>De verbinding met Mierlo voor Mierlo is tijdelijk niet beschikbaar. Zodra je weer internet hebt, kun je de pagina opnieuw laden.</p></main></body>
</html>
		<?php
		exit;
	}
}
