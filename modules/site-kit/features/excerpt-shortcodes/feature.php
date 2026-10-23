<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sb_site_kit_excerpt_shortcode_settings = [];

// Only worth offering where there is an SEO description to run them in.
if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ) {
	$sb_site_kit_excerpt_shortcode_settings['seo'] = [
		'type'    => 'checkbox',
		'label'   => 'Run them in SEO meta descriptions too',
		'default' => 0,
	];
}

return [
	'id'          => 'excerpt-shortcodes',
	'title'       => 'Shortcodes In Excerpts',
	'description' => 'Runs shortcodes written into an excerpt instead of printing them as text.',
	'section'     => 'content',
	'default'     => false,
	'settings'    => $sb_site_kit_excerpt_shortcode_settings,

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-excerpt-shortcodes.php';
		SB_Site_Kit_Excerpt_Shortcodes::boot();
	},
];