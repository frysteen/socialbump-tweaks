<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'woo-cart-both-prices',
	'title'       => 'Show Both Prices in the Cart',
	'description' => 'On a product that is on sale, the cart shows the regular price struck through next to the sale price, so the saving is visible before checkout.',
	'section'     => 'shop',
	'default'     => false,
	'requires'    => [ 'woocommerce' ],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-woo-cart-both-prices.php';
		SB_Woo_Cart_Both_Prices::boot();
	},
];
