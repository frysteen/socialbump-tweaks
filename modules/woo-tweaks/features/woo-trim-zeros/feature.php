<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'woo-trim-zeros',
	'title'       => 'Hide Empty Decimals on Prices',
	'description' => 'A price of 10.00 shows as 10. Prices with real decimals, such as 9.99, are left alone.',
	'section'     => 'shop',
	'default'     => false,
	'requires'    => [ 'woocommerce' ],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-woo-trim-zeros.php';
		SB_Woo_Trim_Zeros::boot();
	},
];
