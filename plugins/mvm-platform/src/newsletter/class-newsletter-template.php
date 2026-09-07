<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Newsletter_Template {
	private const BRAND_BLUE = '#1966AE';

	public static function render( string $body, string $preheader = '', string $unsubscribe_link = '', bool $is_test = false ): string {
		$body        = wp_kses_post( $body );
		$preheader   = sanitize_text_field( $preheader );
		if ( ( '' !== $unsubscribe_link || $is_test ) && class_exists( 'MvM_Newsletter_Content' ) ) {
			$body = MvM_Newsletter_Content::append_agenda( $body, $preheader );
		}
		$logo_url    = self::logo_url();
		$home_url    = esc_url( home_url( '/' ) );
		$footer_link = $unsubscribe_link ? esc_url( $unsubscribe_link ) : '';
		$test_banner = $is_test
			? '<tr><td style="padding:10px 24px;background:#fff4ce;color:#5a4300;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;text-align:center">TESTVERSIE – deze mail is niet naar abonnees verzonden.</td></tr>'
			: '';
		$logo = $logo_url
			? '<img src="' . esc_url( $logo_url ) . '" width="132" alt="Mierlo voor Mierlo" style="display:block;width:132px;max-width:132px;height:auto;border:0;outline:none;text-decoration:none">'
			: '<span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;line-height:1.2;font-weight:700;color:#ffffff">Mierlo voor Mierlo</span>';
		$unsubscribe = $footer_link
			? '<p style="margin:12px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#5e6f7c">Je ontvangt deze nieuwsbrief omdat je je hiervoor hebt aangemeld. Je kunt je altijd direct afmelden via je beveiligde persoonlijke link: <a href="' . $footer_link . '" style="color:' . self::BRAND_BLUE . ';text-decoration:underline">meld je af voor de nieuwsbrief</a>.</p>'
			: '';

		return '<!doctype html>'
			. '<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">'
			. '<style>@media only screen and (max-width:620px){.mvm-wrap{width:100%!important}.mvm-pad{padding-left:18px!important;padding-right:18px!important}.mvm-title{font-size:24px!important;line-height:1.25!important}}@media (prefers-color-scheme:dark){.mvm-page{background:#101820!important}.mvm-card{background:#ffffff!important}.mvm-copy{color:#17222d!important}.mvm-footer{background:#eef4f8!important}}</style>'
			. '</head><body class="mvm-page" style="margin:0;padding:0;background:#eef4f8;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%">'
			. '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all">' . esc_html( $preheader ) . '</div>'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;background:#eef4f8"><tr><td align="center" style="padding:24px 12px">'
			. '<table role="presentation" class="mvm-wrap" width="680" cellspacing="0" cellpadding="0" border="0" style="width:680px;max-width:680px;border-collapse:separate;border-spacing:0">'
			. $test_banner
			. '<tr><td class="mvm-pad" style="padding:22px 28px;background:' . self::BRAND_BLUE . ';border-radius:14px 14px 0 0">'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="left">' . $logo . '</td><td align="right" style="font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.4;color:#ffffff"><a href="' . $home_url . '" style="color:#ffffff;text-decoration:none">mierlovoormierlo.nl</a></td></tr></table>'
			. '</td></tr>'
			. '<tr><td class="mvm-card mvm-pad" style="padding:30px 32px 34px;background:#ffffff;border-left:1px solid #cfdae3;border-right:1px solid #cfdae3">'
			. '<div class="mvm-copy" style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.7;color:#17222d">' . $body . '</div>'
			. '</td></tr>'
			. '<tr><td class="mvm-footer mvm-pad" style="padding:22px 32px;background:#f5f8fa;border:1px solid #cfdae3;border-top:0;border-radius:0 0 14px 14px">'
			. '<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:#40515f"><strong style="color:#17222d">Mierlo voor Mierlo</strong><br>Lokaal nieuws, activiteiten, verenigingen en verhalen uit Mierlo.</p>'
			. $unsubscribe
			. '</td></tr></table>'
			. '<p style="margin:16px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.5;color:#6c7c88;text-align:center">© ' . esc_html( gmdate( 'Y' ) ) . ' Mierlo voor Mierlo</p>'
			. '</td></tr></table></body></html>';
	}

	private static function logo_url(): string {
		$custom_logo_id = absint( get_theme_mod( 'custom_logo', 0 ) );
		if ( $custom_logo_id > 0 ) {
			$url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
			if ( is_string( $url ) && '' !== $url ) {
				return esc_url_raw( $url );
			}
		}

		$site_icon = get_site_icon_url( 192 );
		return is_string( $site_icon ) ? esc_url_raw( $site_icon ) : '';
	}
}
