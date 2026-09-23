<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Open the WordPress editor by default when a post has no Bricks content.
 *
 * On the edit screen Bricks opens on its own tab whenever the WordPress content
 * is empty and a Bricks template renders that post, even if the post itself was
 * never built in Bricks. That makes every new or empty post start in Bricks.
 *
 * Bricks passes that decision to its admin script as bricksData.renderWithBricks
 * and clicks its own tab when it is true. That value is worked out inline in
 * Admin::admin_enqueue_scripts(), not through the bricks/render_with_bricks
 * filter, so this switches it off in the script data instead, for posts with no
 * Bricks data of their own.
 *
 * All three tabs stay, and clicking Bricks still works. Nothing is written to
 * the database, no Bricks data is touched, and the front end is untouched:
 * templates still render these pages exactly as before.
 */
class SB_Bricks_Default_WP_Editor {

	public static function boot() {
		// After Bricks has localised its admin script (priority 10).
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'adjust' ], 20 );
	}

	public static function adjust( $hook ) {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || ! wp_script_is( 'bricks-admin', 'registered' ) ) {
			return;
		}

		$post_id = (int) get_the_ID();

		if ( ! $post_id || ! self::should_default_to_wp( $post_id ) ) {
			return;
		}

		// Runs after Bricks' own inline data, so this wins.
		wp_add_inline_script(
			'bricks-admin',
			'if ( window.bricksData ) { window.bricksData.renderWithBricks = false; }',
			'before'
		);
	}

	private static function should_default_to_wp( $post_id ) {
		// Bricks templates are always built in Bricks.
		if ( defined( 'BRICKS_DB_TEMPLATE_SLUG' ) && get_post_type( $post_id ) === BRICKS_DB_TEMPLATE_SLUG ) {
			return false;
		}

		// The post has its own Bricks content, header or footer.
		foreach ( [ 'BRICKS_DB_PAGE_CONTENT', 'BRICKS_DB_PAGE_HEADER', 'BRICKS_DB_PAGE_FOOTER' ] as $constant ) {
			$key = defined( $constant ) ? constant( $constant ) : '';

			if ( $key && ! empty( get_post_meta( $post_id, $key, true ) ) ) {
				return false;
			}
		}

		// The post was last saved in Bricks mode, so respect that choice.
		if ( class_exists( '\Bricks\Helpers' ) && \Bricks\Helpers::get_editor_mode( $post_id ) === 'bricks' ) {
			return false;
		}

		return true;
	}
}