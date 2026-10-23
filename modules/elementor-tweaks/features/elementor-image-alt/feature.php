<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'elementor-image-alt',
	'title'       => 'Image Alt Text',
	'description' => 'Set the alt text per widget: the image\'s own, your own words or a dynamic tag like the post title, or none for decorative images. Works on every widget that shows images, galleries and carousels included.',
	'section'     => 'images',
	'default'     => true,
	'requires'    => [ 'elementor' ],

	'boot'        => function ( $feature ) {
		require_once $feature['path'] . 'class-sb-elementor-image-alt.php';
		SB_Elementor_Image_Alt::boot();
	},
];
