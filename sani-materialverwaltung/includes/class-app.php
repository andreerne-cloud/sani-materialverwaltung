<?php
/**
 * Die App selbst: eine eigene Seite (?sani-mv-app=1) und der Shortcode
 * [sani_material], der diese Seite in einen Beitrag oder eine Seite einbettet.
 * Die App läuft in einem iframe, damit sich Theme und App nicht gegenseitig stören.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sani_MV_App {

	public static function init() {
		add_shortcode( 'sani_material', array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_app' ) );
	}

	public static function app_url() {
		return add_query_arg( 'sani-mv-app', '1', home_url( '/' ) );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'hoehe' => '85vh' ), $atts, 'sani_material' );
		if ( ! is_user_logged_in() ) {
			$login = wp_login_url( get_permalink() );
			return '<div class="sani-mv-login" style="padding:1.5em;border:1px solid #d9dce8;border-radius:8px;">'
				. '<strong>Materialverwaltung</strong><br>Bitte <a href="' . esc_url( $login ) . '">melde dich an</a>, um das Material zu sehen.</div>';
		}
		if ( ! Sani_MV_Access::can_read() ) {
			return '<p>Du hast keine Berechtigung für die Materialverwaltung.</p>';
		}
		$height = preg_match( '/^\d+(\.\d+)?(px|vh|em|rem)$/', $atts['hoehe'] ) ? $atts['hoehe'] : '85vh';
		$url    = self::app_url();
		return '<div class="sani-mv-embed">'
			. '<iframe src="' . esc_url( $url ) . '" title="Materialverwaltung" style="width:100%;height:' . esc_attr( $height ) . ';min-height:600px;border:1px solid #d9dce8;border-radius:8px;display:block;" loading="lazy"></iframe>'
			. '<p style="margin:.5em 0 0;font-size:.9em;"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Materialverwaltung im eigenen Fenster öffnen</a></p>'
			. '</div>';
	}

	public static function maybe_render_app() {
		if ( empty( $_GET['sani-mv-app'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( ! Sani_MV_Access::can_read() ) {
			wp_die( 'Du hast keine Berechtigung für die Materialverwaltung.', 'Keine Berechtigung', array( 'response' => 403 ) );
		}
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		echo self::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/** Baut die App-Seite aus app/app.html und hängt die WordPress-Anbindung davor. */
	public static function render() {
		$html = file_get_contents( SANI_MV_DIR . 'app/app.html' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$ver  = rawurlencode( SANI_MV_VERSION );

		// Bibliotheken aus dem Plugin statt vom CDN laden; keine externen Schriften (Datenschutz).
		$html = preg_replace(
			'#https://cdnjs\.cloudflare\.com/ajax/libs/jspdf/[^"]+/jspdf\.umd\.min\.js#',
			esc_url( SANI_MV_URL . 'vendor/jspdf.umd.min.js?ver=' . $ver ),
			$html
		);
		$html = preg_replace(
			'#https://cdnjs\.cloudflare\.com/ajax/libs/jspdf-autotable/[^"]+/jspdf\.plugin\.autotable\.min\.js#',
			esc_url( SANI_MV_URL . 'vendor/jspdf.plugin.autotable.min.js?ver=' . $ver ),
			$html
		);
		$html = preg_replace( '#<link[^>]+fonts\.(googleapis|gstatic)\.com[^>]*>\s*#', '', $html );

		$user   = wp_get_current_user();
		$config = array(
			'rest'     => esc_url_raw( rest_url( Sani_MV_Rest::NS . '/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'canWrite' => Sani_MV_Access::can_write(),
			'user'     => $user ? $user->display_name : '',
			'version'  => SANI_MV_VERSION,
			'poll'     => 15000,
		);

		$head  = '<!doctype html><html lang="de"><head><meta charset="utf-8">';
		$head .= '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">';
		$head .= '<style>:root{color-scheme:light;padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)}body{margin:0;font:14px/1.4 system-ui,-apple-system,"Segoe UI",sans-serif}img{max-width:100%}[hidden]{display:none!important}</style>';
		$head .= '<script>window.SANI_MV=' . wp_json_encode( $config ) . ';</script>';
		$head .= '<script src="' . esc_url( SANI_MV_URL . 'assets/wp-adapter.js?ver=' . $ver ) . '"></script>';
		$head .= '</head><body>';

		return $head . $html . '</body></html>';
	}
}
