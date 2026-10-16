<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saving works whether the module is on or off: the page shows either way, so
 * rules can be set up before switching it on. The module's boot only runs
 * while it is on, so the save handler is registered here, once.
 */
if ( ! function_exists( 'sb_site_kit_robots_save' ) ) {
	function sb_site_kit_robots_save() {
		require_once __DIR__ . '/class-sb-site-kit-robots.php';
		SB_Site_Kit_Robots::save();
	}

	add_action( 'admin_post_sb_site_kit_save_robots', 'sb_site_kit_robots_save' );
}

return [
	'id'          => 'robots-txt',
	'title'       => __( 'Robots.txt', 'sb-tweaks' ),
	'description' => __( 'Serves the SocialBUMP robots.txt: your allow and disallow rules, any extra lines, and the sitemap address filled in for this site. Takes over from the SEO plugin\'s robots.txt editor.', 'sb-tweaks' ),
	'section'     => 'seo',
	'default'     => false,

	'admin_page'  => [
		'title'  => __( 'Robots.txt', 'sb-tweaks' ),
		'render' => function ( $module ) {
			// The page shows even while the module is off, so load the class here too.
			require_once $module['path'] . 'class-sb-site-kit-robots.php';

			SB_Site_Kit_Robots::render_page();
		},
	],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-robots.php';
		SB_Site_Kit_Robots::boot();
	},
];
