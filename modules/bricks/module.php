<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bricks Tweaks: what was the standalone SocialBUMP Bricks Tweaks plugin.
 *
 * Custom Bricks elements, conditions for the Bricks Conditions panel, and
 * tweaks to how Bricks works. Its settings are the sb_tweaks_bricks_features,
 * _settings and _groups options every site already holds from the standalone
 * plugin, so nothing is converted. Everything pages have saved keeps its name:
 * the sb_bricks_* condition keys, the sb-bricks-image-carousel element, the
 * sbBricks* loop settings and the sb_bricks_gallery_ query types. The code's own
 * names moved from SBBT_ and sbbt to SB_Bricks_ and sb_bricks.
 *
 * The page address stays sb-bricks-tweaks, as Admin Site Enhancements' menu
 * setup and bookmarks hold it, and the menu still sits just below Bricks.
 */
if ( ! defined( 'SB_BRICKS_PATH' ) ) {
	define( 'SB_BRICKS_PATH', plugin_dir_path( __FILE__ ) );
	define( 'SB_BRICKS_URL', SB_TWEAKS_URL . 'modules/bricks/' );
}

return [
	'id'          => 'bricks',
	'title'       => 'Bricks Tweaks',
	'description' => __( 'Custom Bricks elements, extra conditions for the Bricks Conditions panel, and tweaks to how Bricks works. Replaces the standalone SocialBUMP Bricks Tweaks plugin.', 'sb-tweaks' ),
	'heading'     => 'Bricks Tweaks',
	'requires'    => [ 'bricks' ],
	'replaces'    => 'socialbump-bricks-tweaks/socialbump-bricks-tweaks.php',

	'groups'      => [
		'elements'   => [
			'title'       => __( 'Bricks Elements', 'sb-tweaks' ),
			'description' => __( 'Custom elements added to the Bricks element panel.', 'sb-tweaks' ),
		],
		'conditions' => [
			'title'       => __( 'Conditional Logic', 'sb-tweaks' ),
			'description' => __( 'Extra options in the Bricks element Conditions panel, listed under the SocialBUMP group.', 'sb-tweaks' ),
		],
		'extras'     => [
			'title'       => __( 'Extras', 'sb-tweaks' ),
			'description' => __( 'Other tweaks to how Bricks works.', 'sb-tweaks' ),
		],
	],

	'menu'        => [
		'slug'       => 'sb-bricks-tweaks',
		'label'      => 'SB Bricks Tweaks',
		'page_title' => 'SocialBUMP Bricks Tweaks',
		'after'      => 'bricks',
	],

	'prompt'      => 'You are picking up work on the Bricks Tweaks module of SocialBUMP Tweaks, a WordPress plugin. Everything is developed on the hub, plugins.socialbump.com.au, which you reach through its Novamira MCP connector. Before changing anything, read wp-content/plugins/socialbump-tweaks/modules/bricks/docs/context.md for this module, and wp-content/plugins/socialbump-tweaks/docs/context.md for the framework it runs on. Keep the module notes current: when you change how something works or learn something the hard way, write it there in the same session. Tell me what you have read before you start, and before you finish, bring the notes up to date and tell me exactly what you added or corrected.',

	'boot'        => function ( $context ) {
		$features = $context['features'];

		// Elements register with Bricks on init, after Bricks has loaded its own.
		add_action(
			'init',
			function () use ( $features ) {
				if ( ! class_exists( '\Bricks\Elements' ) ) {
					return;
				}

				foreach ( $features->enabled() as $feature ) {
					if ( empty( $feature['element_file'] ) ) {
						continue;
					}

					\Bricks\Elements::register_element( $feature['path'] . $feature['element_file'], $feature['element_name'] ?? '', $feature['element_class'] ?? '' );

					// A renamed element keeps its old name registered, hidden from the
					// builder panel, so a page still built with it renders.
					if ( ! empty( $feature['element_legacy_file'] ) ) {
						\Bricks\Elements::register_element( $feature['path'] . $feature['element_legacy_file'], $feature['element_legacy_name'] ?? '', $feature['element_legacy_class'] ?? '' );
					}
				}
			},
			11
		);

		// ACF bundled with Advanced Themer: worth knowing, on this module's pages.
		add_action(
			'admin_notices',
			function () {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

				if ( ! $screen || strpos( (string) $screen->id, 'sb-bricks-tweaks' ) === false ) {
					return;
				}

				require_once SB_BRICKS_PATH . 'includes/class-sb-bricks-acf-source.php';
				SB_Bricks_Acf_Source::notice();
			}
		);
	},
];