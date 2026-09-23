<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'condition-woo-archive-display',
	'title'       => __( 'WooCommerce Archive Display', 'sb-tweaks' ),
	'description' => __( 'Show or hide an element depending on whether the shop or category archive is set to list products, categories, or both.', 'sb-tweaks' ),
	'type'        => 'condition',
	'section'     => 'conditions',
	'default'     => false,
	'requires'    => [ 'woocommerce' ],

	'boot' => function ( $module ) {
		require_once SB_BRICKS_PATH . 'includes/class-sb-bricks-conditions.php';
		SB_Bricks_Conditions::register( include $module['path'] . 'condition.php' );
	},
];
