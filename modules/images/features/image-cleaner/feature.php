<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SB Image Cleaner: missing thumbnails, old sizes left behind, files in the
 * uploads folder nothing refers to, and the sizes panel on each image in the
 * media library. On whenever the module is, like SB Image Settings.
 */
return [
	'id'          => 'image-cleaner',
	'title'       => __( 'SB Image Cleaner', 'sb-tweaks' ),
	'description' => __( 'Find missing thumbnails, old sizes left behind, and files in the uploads folder nothing refers to. Also adds a sizes panel to each image in the media library.', 'sb-tweaks' ),
	'section'     => 'cleaner',
	'always'      => true,

	'admin_page'  => [
		'title'  => __( 'SB Image Cleaner', 'sb-tweaks' ),
		'render' => function ( $feature ) {
			require_once $feature['path'] . 'class-sb-images-cleaner.php';

			SB_Images_Cleaner::render_page();
		},
	],

	'boot' => function ( $feature ) {
		require_once $feature['path'] . 'class-sb-images-cleaner.php';

		SB_Images_Cleaner::boot();
	},
];
