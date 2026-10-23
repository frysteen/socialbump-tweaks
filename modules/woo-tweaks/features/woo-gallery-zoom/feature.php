<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'woo-gallery-zoom',
	'title'       => 'Turn Off Product Image Zoom',
	'description' => 'Stops the magnifier appearing over product images. WooCommerce has no setting for this, so it has to be switched off in code.',
	'section'     => 'shop',
	'default'     => false,
	'requires'    => [ 'woocommerce' ],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-woo-gallery-zoom.php';
		SB_Woo_Gallery_Zoom::boot();
	},
];
