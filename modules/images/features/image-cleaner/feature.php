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
	'title'       => 'SB Image Cleaner',
	'description' => 'Find missing thumbnails, old sizes left behind, and files in the uploads folder nothing refers to. Also adds a sizes panel to each image in the media library.',
	'section'     => 'cleaner',
	'always'      => true,

	'admin_page'  => [
		'title'  => 'SB Image Cleaner',
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
