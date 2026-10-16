<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Management: what were Site Kit's Images and Image Cleaner pages.
 *
 * The two pages sit in the Media menu as SB Image Settings and SB Image
 * Cleaner, with no menu or Features page of their own: the module's switch on
 * the Modules page turns both on together. In the SocialBUMP admin bar the
 * module is one row, Image Management, with the two pages on its flyout.
 *
 * It has its own options (sb_tweaks_images_*), its own code names (SB_Images_,
 * sb_images, sb-images-) and its own hooks (sb_images/), so it could become a
 * plugin of its own again without touching the rest. The settings came across
 * from Site Kit once, in SB_Images_Module::migrate(). What is saved in posts
 * keeps its name: the gallery and image blocks' sbskZoom and sbskZoomSize
 * attributes. The image-<width> size names never change.
 *
 * It waits for standalone Site Kit rather than replacing it: the Site Kit
 * module does that, and this one sits out any request Site Kit had loaded in,
 * since both would register the same sizes and handle the same uploads.
 */
if ( ! defined( 'SB_IMAGES_PATH' ) ) {
	define( 'SB_IMAGES_PATH', plugin_dir_path( __FILE__ ) );
	define( 'SB_IMAGES_URL', SB_TWEAKS_URL . 'modules/images/' );
}

require_once SB_IMAGES_PATH . 'includes/class-sb-images-module.php';

return [
	'id'          => 'images',
	'title'       => 'Image Management',
	'description' => __( 'Image sizes, tidy titles and alt text on upload, media library extras and the Image Cleaner. Adds SB Image Settings and SB Image Cleaner to the Media menu.', 'sb-tweaks' ),
	'heading'     => 'Image Management',
	'waits_for'   => SB_Images_Module::SITE_KIT,
	'migrate'     => [ 'SB_Images_Module', 'migrate' ],

	// Settings first: the order of the Media menu and the admin bar flyout.
	'groups'      => [
		'settings' => [
			'title'       => __( 'SB Image Settings', 'sb-tweaks' ),
			'description' => __( 'Image sizes, tidy titles and alt text on upload, and the media library extras.', 'sb-tweaks' ),
		],
		'cleaner'  => [
			'title'       => __( 'SB Image Cleaner', 'sb-tweaks' ),
			'description' => __( 'Missing thumbnails, old sizes and orphaned files.', 'sb-tweaks' ),
		],
	],

	'menu'        => [
		'slug'       => SB_Images_Module::SLUG,
		'parent'     => 'upload.php',
		'label'      => 'Image Management',
		'page_title' => 'Image Management',
	],

	'bar'         => [
		'id'    => 'image-management',
		'label' => 'Image Management',
	],

	'prompt'      => 'You are picking up work on the Image Management module of SocialBUMP Tweaks, a WordPress plugin. Everything is developed on the hub, plugins.socialbump.com.au, which you reach through its Novamira MCP connector. Before changing anything, read wp-content/plugins/socialbump-tweaks/modules/images/docs/context.md for this module, and wp-content/plugins/socialbump-tweaks/docs/context.md for the framework it runs on. Keep the module notes current: when you change how something works or learn something the hard way, write it there in the same session. Tell me what you have read before you start, and before you finish, bring the notes up to date and tell me exactly what you added or corrected.',

	'boot'        => function ( $context ) {
		SB_Images_Module::boot();
	},
];
