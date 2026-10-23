<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'elementor-image-ratio',
	'title'       => 'Image Aspect Ratio',
	'description' => 'An aspect ratio, fit (cover by default) and position for the images in widgets that have no ratio setting of their own, set per device.',
	'section'     => 'images',
	'default'     => true,
	'requires'    => [ 'elementor' ],

	'boot'        => function ( $feature ) {
		require_once $feature['path'] . 'class-sb-elementor-image-ratio.php';
		SB_Elementor_Image_Ratio::boot();
	},
];
