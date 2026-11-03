<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Live reload for WPCodeBox stylesheets saved in Advanced Themer (Sam,
 * 3 November 2026).
 *
 * WPCodeBox reloads its stylesheets in any open front-end tab when one is
 * saved in its own editor: the editor writes the localStorage key
 * wpcb2Reload, and a script WPCodeBox prints in wp_head (for admins) hears
 * the storage event in the other tabs and reloads its link.wpcb2-external-style
 * and style.wpcb2-inline-style tags. Advanced Themer's Advanced CSS saves the
 * same stylesheets through WPCodeBox's own API (Api::updateSnippet, so the
 * external file is rewritten), but never writes that key, so open tabs never
 * heard about it.
 *
 * On AT's save (admin-ajax save_advanced_css_ajax_function) this runs first,
 * at priority 1. If a WPCodeBox stylesheet that changed has Autoreload
 * Changes on (devMode), or is a partial (saving one recompiles the sheets
 * that use it), it sets a short-lived cookie on the response. In the
 * builder's window a script checks for that cookie after every request
 * finishes and, when it is there, clears it and writes wpcb2Reload, exactly
 * as WPCodeBox's editor does.
 */
class SB_Bricks_AT_Live_Reload {

	const COOKIE = 'sb_bricks_wpcb_reload';

	public static function boot() {
		add_action( 'wp_ajax_save_advanced_css_ajax_function', [ __CLASS__, 'flag' ], 1 );
		add_action( 'wp_footer', [ __CLASS__, 'script' ] );
	}

	/** Before AT saves: flag the response when a reload is wanted. */
	public static function flag() {
		if ( ! current_user_can( 'manage_options' ) || empty( $_POST['advanced_css'] ) || ! is_array( $_POST['advanced_css'] ) ) {
			return;
		}

		$ids = [];

		foreach ( wp_unslash( $_POST['advanced_css'] ) as $item ) {
			if ( ! is_array( $item ) || strpos( (string) ( $item['category'] ?? '' ), 'wpcodebox' ) !== 0 ) {
				continue;
			}

			$changed = filter_var( $item['hasChanged'] ?? false, FILTER_VALIDATE_BOOLEAN );
			$removed = filter_var( $item['removed'] ?? false, FILTER_VALIDATE_BOOLEAN );

			if ( $changed && ! $removed ) {
				$ids[] = absint( $item['id'] ?? 0 );
			}
		}

		$ids = array_values( array_filter( array_unique( $ids ) ) );

		if ( ! $ids ) {
			return;
		}

		global $wpdb;

		$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wpcb_snippets WHERE id IN (' . $in . ') AND ( devMode = 1 OR codeType = %s )', array_merge( $ids, [ 'scssp' ] ) ) );

		if ( $count < 1 ) {
			return;
		}

		setcookie(
			self::COOKIE,
			(string) time(),
			[
				'expires'  => time() + 60,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			]
		);
	}

	/** In the builder's window: turn the cookie into WPCodeBox's reload signal. */
	public static function script() {
		if ( ! function_exists( 'bricks_is_builder_main' ) || ! bricks_is_builder_main() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$name  = self::COOKIE . '=';
		$clear = self::COOKIE . '=; Max-Age=0; path=' . ( COOKIEPATH ? COOKIEPATH : '/' ) . ( COOKIE_DOMAIN ? '; domain=' . COOKIE_DOMAIN : '' );

		// After any request the builder makes (AT's save is one), look for the cookie.
		echo '<script>(function(){var n=' . wp_json_encode( $name ) . ',c=' . wp_json_encode( $clear ) . ';function check(){if(document.cookie.indexOf(n)===-1)return;document.cookie=c;try{localStorage.setItem(\'wpcb2Reload\',String(Date.now()));}catch(e){}}var o=XMLHttpRequest.prototype.open;XMLHttpRequest.prototype.open=function(){this.addEventListener(\'loadend\',check);return o.apply(this,arguments);};if(window.fetch){var f=window.fetch;window.fetch=function(){return f.apply(this,arguments).then(function(r){setTimeout(check,0);return r;});};}})();</script>' . PHP_EOL;
	}
}