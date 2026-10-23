<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor Tweaks: extras for sites built with Elementor.
 *
 * Its features add settings to Elementor's widgets rather than pages of their
 * own. Both image features share one SB Image section, added to every widget
 * that shows an image (SB_Elementor_Tweaks::section_end() decides which).
 * It needs Elementor: on a site without it the module's card says so and
 * nothing loads.
 *
 * It replaces the Custom Alt Text for Elementor Images plugin. The settings
 * that plugin saved in pages are renamed to this module's once, in
 * SB_Elementor_Tweaks::migrate(), and the plugin is switched off and kept off
 * through 'replaces'.
 */
if ( ! defined( 'SB_ELEMENTOR_TWEAKS_PATH' ) ) {
	define( 'SB_ELEMENTOR_TWEAKS_PATH', plugin_dir_path( __FILE__ ) );
	define( 'SB_ELEMENTOR_TWEAKS_URL', SB_TWEAKS_URL . 'modules/elementor-tweaks/' );
}

require_once SB_ELEMENTOR_TWEAKS_PATH . 'includes/class-sb-elementor-tweaks.php';

return [
	'id'          => 'elementor',
	'title'       => 'Elementor Tweaks',
	'description' => 'Extras for Elementor widgets: alt text you set per widget, and an aspect ratio, fit and position for widget images.',
	'heading'     => 'Elementor Tweaks',
	'requires'    => [ 'elementor' ],
	'replaces'    => SB_Elementor_Tweaks::OLD_ALT_PLUGIN,
	'migrate'     => [ 'SB_Elementor_Tweaks', 'migrate' ],

	'groups'      => [
		'images' => [
			'title'       => 'Images',
			'description' => 'Settings added to every Elementor widget that shows an image, in its SB Image section.',
		],
	],

	'menu'        => [
		'slug'       => SB_Elementor_Tweaks::SLUG,
		'label'      => 'SB Elementor Tweaks',
		'page_title' => 'SocialBUMP Elementor Tweaks',
		'after'      => 'sb-site-kit',
	],

	'bar'         => [
		'id'    => 'elementor-tweaks',
		'label' => 'Elementor Tweaks',
	],

	'prompt'      => 'You are picking up work on the Elementor Tweaks module of SocialBUMP Tweaks, a WordPress plugin. Everything is developed on the hub, plugins.socialbump.com.au, which you reach through its Novamira MCP connector. The hub has no Elementor, so the module shows there as needing it, and testing happens on an Elementor client site. Before changing anything, read wp-content/plugins/socialbump-tweaks/modules/elementor-tweaks/docs/context.md for this module, and wp-content/plugins/socialbump-tweaks/docs/context.md for the framework it runs on. Keep the module notes current: when you change how something works or learn something the hard way, write it there in the same session. Tell me what you have read before you start, and before you finish, bring the notes up to date and tell me exactly what you added or corrected.',

	'boot'        => function ( $context ) {
		SB_Elementor_Tweaks::boot();
	},
];
