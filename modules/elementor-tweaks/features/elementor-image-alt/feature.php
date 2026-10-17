<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'elementor-image-alt',
	'title'       => __( 'Image Alt Text', 'sb-tweaks' ),
	'description' => __( 'Set the alt text per widget: the image\'s own, your own words or a dynamic tag like the post title, or none for decorative images. Works on every widget that shows images, galleries and carousels included.', 'sb-tweaks' ),
	'section'     => 'images',
	'default'     => true,
	'requires'    => [ 'elementor' ],

	'boot'        => function ( $feature ) {
		require_once $feature['path'] . 'class-sb-elementor-image-alt.php';
		SB_Elementor_Image_Alt::boot();
	},
];
