<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site Kit: what was the standalone SocialBUMP Site Kit plugin, less its image
 * code, which is the Image Management module now.
 *
 * Content, FAQ, WooCommerce, SEO and admin tweaks for any site, whatever it is
 * built with. Its settings are the sb_tweaks_site_kit_features, _settings and
 * _groups options every site already holds from the standalone plugin, so
 * nothing is converted, and the page addresses stay sb-site-kit and
 * sb-site-kit-<group>. What posts have saved keeps its name: the FAQ fields'
 * ACF keys (field_sbsk_faq_ and group_sbsk_faq_). The code's own names moved
 * from SBSK_ and sbsk to SB_Site_Kit_ and sb_site_kit.
 */
if ( ! defined( 'SB_SITE_KIT_PATH' ) ) {
	define( 'SB_SITE_KIT_PATH', plugin_dir_path( __FILE__ ) );
	define( 'SB_SITE_KIT_URL', SB_TWEAKS_URL . 'modules/site-kit/' );
}

require_once SB_SITE_KIT_PATH . 'includes/class-sb-site-kit.php';

return [
	'id'          => 'site_kit',
	'title'       => 'Site Kit',
	'description' => __( 'Content, FAQ, WooCommerce, SEO and admin tweaks for any site, whatever it is built with. Replaces the standalone SocialBUMP Site Kit plugin, apart from its image pages, which are the Image Management module.', 'sb-tweaks' ),
	'heading'     => 'Site Kit',
	'replaces'    => 'socialbump-site-kit/socialbump-site-kit.php',

	'groups'      => [
		'content'     => [
			'title'       => __( 'Content', 'sb-tweaks' ),
			'description' => __( 'Shortcodes, fields and editor tools.', 'sb-tweaks' ),
		],
		'faq'         => [
			'title'       => __( 'FAQ Settings', 'sb-tweaks' ),
			'description' => __( 'Fields for questions and answers, and the FAQPage schema that goes with them.', 'sb-tweaks' ),
			'default'     => false,
		],
		'woocommerce' => [
			'title'       => __( 'WooCommerce', 'sb-tweaks' ),
			'description' => __( 'Corrections and tweaks for the WooCommerce admin.', 'sb-tweaks' ),
		],
		'seo'         => [
			'title'       => __( 'SEO', 'sb-tweaks' ),
			'description' => __( 'The robots.txt search engines and other crawlers read.', 'sb-tweaks' ),
		],
		'admin'       => [
			'title'       => __( 'Admin Settings', 'sb-tweaks' ),
			'description' => __( 'How the WordPress admin looks and who gets to use it.', 'sb-tweaks' ),
		],
	],

	'menu'        => [
		'slug'       => 'sb-site-kit',
		'label'      => 'SB Site Kit',
		'page_title' => 'SocialBUMP Site Kit',
		'after'      => 'sb-bricks-tweaks',
	],

	'bar'         => [
		'id'    => 'site-kit',
		'label' => 'Site Kit',
	],

	'prompt'      => 'You are picking up work on the Site Kit module of SocialBUMP Tweaks, a WordPress plugin. Everything is developed on the hub, plugins.socialbump.com.au, which you reach through its Novamira MCP connector. Before changing anything, read wp-content/plugins/socialbump-tweaks/modules/site-kit/docs/context.md for this module, and wp-content/plugins/socialbump-tweaks/docs/context.md for the framework it runs on. Keep the module notes current: when you change how something works or learn something the hard way, write it there in the same session. Tell me what you have read before you start, and before you finish, bring the notes up to date and tell me exactly what you added or corrected.',

	'boot'        => function ( $context ) {
		// The FAQ Settings and Robots.txt pages' own styles, on this module's pages only.
		add_action(
			'admin_enqueue_scripts',
			function ( $hook ) {
				if ( strpos( (string) $hook, SB_Site_Kit::SLUG ) === false ) {
					return;
				}

				$css = SB_SITE_KIT_PATH . 'assets/css/site-kit.css';

				wp_enqueue_style( 'sb-site-kit', SB_SITE_KIT_URL . 'assets/css/site-kit.css', [ 'sb-tweaks-admin' ], SB_TWEAKS_VERSION . '.' . ( file_exists( $css ) ? filemtime( $css ) : 0 ) );
			}
		);
	},
];
