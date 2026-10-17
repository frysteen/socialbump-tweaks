<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'woo-trim-zeros',
	'title'       => __( 'Hide Empty Decimals on Prices', 'sb-tweaks' ),
	'description' => __( 'A price of 10.00 shows as 10. Prices with real decimals, such as 9.99, are left alone.', 'sb-tweaks' ),
	'section'     => 'shop',
	'default'     => false,
	'requires'    => [ 'woocommerce' ],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-woo-trim-zeros.php';
		SB_Woo_Trim_Zeros::boot();
	},
];
