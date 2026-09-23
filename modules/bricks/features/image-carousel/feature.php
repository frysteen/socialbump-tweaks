<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'            => 'image-carousel',
	'title'         => __( 'Image Carousel', 'sb-tweaks' ),
	'description'   => __( 'An SEO friendly image carousel. Real img tags with alt text, drag ordering, ACF gallery support, breakpoint controls, lightbox and continuous scroll.', 'sb-tweaks' ),
	'type'          => 'element',
	'default'       => true,
	'element_file'  => 'class-element-image-carousel.php',
	'element_name'  => 'sb-bricks-image-carousel',
	'element_class' => 'SB_Bricks_Element_Image_Carousel',

	// The name this element had before the rename, kept registered so pages
	// built with it still render. Hidden from the builder panel. Remove once
	// every site has been converted.
	'element_legacy_file'  => 'class-element-image-carousel-legacy.php',
	'element_legacy_name'  => 'sb-image-carousel',
	'element_legacy_class' => 'SB_Bricks_Element_Image_Carousel_Legacy',

	'assets' => function ( $module ) {
		$ver = function ( $relative ) use ( $module ) {
			$file = $module['path'] . $relative;

			return file_exists( $file ) ? (string) filemtime( $file ) : SB_TWEAKS_VERSION;
		};

		wp_register_style(
			'sb-bricks-carousel',
			$module['url'] . 'assets/css/sb-carousel.css',
			[],
			$ver( 'assets/css/sb-carousel.css' )
		);

		wp_register_script(
			'sb-bricks-splide-auto-scroll',
			$module['url'] . 'assets/js/splide-extension-auto-scroll.min.js',
			[ 'bricks-splide' ],
			'0.5.3',
			true
		);

		wp_register_script(
			'sb-bricks-carousel',
			$module['url'] . 'assets/js/sb-carousel.js',
			[ 'bricks-splide' ],
			$ver( 'assets/js/sb-carousel.js' ),
			true
		);
	},
];