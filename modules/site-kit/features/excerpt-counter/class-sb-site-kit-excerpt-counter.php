<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Character counter for the excerpt field.
 *
 * Works in the block editor and the classic excerpt box. The block editor
 * builds its panels on demand, so the script watches for the field appearing
 * rather than polling for it.
 */
class SB_Site_Kit_Excerpt_Counter {

	private static $module = [];

	public static function boot( $module ) {
		self::$module = $module;

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
	}

	public static function assets( $hook ) {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && $screen->post_type && ! post_type_supports( $screen->post_type, 'excerpt' ) ) {
			return;
		}

		$js  = self::$module['path'] . 'assets/counter.js';
		$css = self::$module['path'] . 'assets/counter.css';
		$max = (int) SB_Site_Kit::setting( 'excerpt-counter', 'max' );

		wp_enqueue_style( 'sb-site-kit-excerpt-counter', self::$module['url'] . 'assets/counter.css', [], file_exists( $css ) ? SB_TWEAKS_VERSION . '.' . filemtime( $css ) : SB_TWEAKS_VERSION );
		wp_enqueue_script( 'sb-site-kit-excerpt-counter', self::$module['url'] . 'assets/counter.js', [], file_exists( $js ) ? SB_TWEAKS_VERSION . '.' . filemtime( $js ) : SB_TWEAKS_VERSION, true );

		wp_localize_script(
			'sb-site-kit-excerpt-counter',
			'sbSiteKitExcerptCounter',
			[
				'max'    => $max > 0 ? $max : 160,
				/* translators: 1: characters used, 2: the ideal length */
				'format' => __( '%1$s of %2$s characters', 'sb-tweaks' ),
			]
		);
	}
}