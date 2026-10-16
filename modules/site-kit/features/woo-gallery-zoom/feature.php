<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'woo-gallery-zoom',
	'title'       => __( 'Turn Off Product Image Zoom', 'sb-tweaks' ),
	'description' => __( 'Stops the magnifier appearing over product images. WooCommerce has no setting for this, so it has to be switched off in code.', 'sb-tweaks' ),
	'section'     => 'woocommerce',
	'default'     => false,
	'requires'    => [ 'woocommerce' ],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-woo-gallery-zoom.php';
		SB_Site_Kit_Woo_Gallery_Zoom::boot();
	},
];
