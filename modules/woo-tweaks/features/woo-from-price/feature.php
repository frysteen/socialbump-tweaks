<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'woo-from-price',
	'title'       => 'From Price on Variable Products',
	'description' => 'Shows the lowest price with a label in front of it, instead of a price range.',
	'section'     => 'shop',
	'default'     => false,
	'requires'    => [ 'woocommerce' ],

	'settings'    => [
		'on_loops'  => [
			'type'    => 'checkbox',
			'label'   => 'Use in product loops',
			'default' => 1,
		],
		'on_single' => [
			'type'        => 'checkbox',
			'label'       => 'Use on the single product page',
			'default'     => 0,
		],
		'label'     => [
			'type'        => 'text',
			'label'       => 'Label',
			'default'     => 'From:',
			'description' => 'Leave empty to show the price on its own.',
		],
	],

	'features'    => function () {
		require_once __DIR__ . '/class-sb-woo-from-price.php';

		return [
			[ 'label' => __( 'From price in loops', 'sb-tweaks' ), 'on' => (bool) SB_Woo_From_Price::setting( 'on_loops', 1 ) ],
			[ 'label' => __( 'From price on the product page', 'sb-tweaks' ), 'on' => (bool) SB_Woo_From_Price::setting( 'on_single', 0 ) ],
		];
	},

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-woo-from-price.php';
		SB_Woo_From_Price::boot();
	},
];
