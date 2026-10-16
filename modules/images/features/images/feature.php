<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SB Image Settings: the standard image sizes, tidy titles and alt text on
 * upload, the media library enhancements and Gutenberg image zoom. No switch
 * of its own: the module's switch on the Modules page turns it on with the
 * cleaner, so the page and everything on it load whenever the module does.
 */
return [
	'id'          => 'images',
	'title'       => __( 'SB Image Settings', 'sb-tweaks' ),
	'description' => __( 'The standard image sizes, plus tidy titles and alt text when images are uploaded.', 'sb-tweaks' ),
	'section'     => 'settings',
	'always'      => true,

	'admin_page'  => [
		'title'  => __( 'SB Image Settings', 'sb-tweaks' ),
		'render' => function ( $feature ) {
			SB_Images::render_page();
		},
	],

	'boot' => function ( $feature ) {
		require_once $feature['path'] . 'class-sb-images.php';
		SB_Images::boot();
	},
];
