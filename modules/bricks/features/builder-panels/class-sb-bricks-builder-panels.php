<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep WPCodeBox CSS snippets out of the Bricks builder's own window (Sam,
 * 31 October 2026).
 *
 * The builder is two pages: its own window (panels, popups, the media
 * library) and the canvas, an iframe loaded with ?brickspreview. WPCodeBox
 * prints its CSS snippets in wp_head on both, so a site's section, card and
 * layout rules restyled the builder's panels too. Its snippets carry a class
 * of their own (link.wpcb2-external-style, style.wpcb2-inline-style), so in
 * the builder's window only (bricks_is_builder_main()) they are switched off.
 * The canvas and the front end are untouched.
 *
 * Switched off rather than removed: WPCodeBox prints them inside its own
 * wp_head closures, with no handle to dequeue.
 */
class SB_Bricks_Builder_Panels {

	public static function boot() {
		// After WPCodeBox's styles (wp_head, its snippets' own priority).
		add_action( 'wp_head', [ __CLASS__, 'script' ], 999 );
	}

	public static function script() {
		if ( ! function_exists( 'bricks_is_builder_main' ) || ! bricks_is_builder_main() ) {
			return;
		}

		// Now, for what's already printed, and again once the page is read, for any printed later.
		echo "<script>(function(){function off(){document.querySelectorAll('link.wpcb2-external-style, style.wpcb2-inline-style').forEach(function(el){el.disabled=true;});}off();document.addEventListener('DOMContentLoaded',off);})();</script>\n";
	}
}
