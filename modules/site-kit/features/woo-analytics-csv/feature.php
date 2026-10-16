<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'woo-analytics-csv',
	'title'       => __( 'Download Analytics as CSV', 'sb-tweaks' ),
	'description' => __( 'Puts a Download CSV button on the Analytics reports, covering the date range on screen. WooCommerce normally builds the file in the background and emails a link instead.', 'sb-tweaks' ),
	'section'     => 'woocommerce',
	'default'     => false,
	'requires'    => [ 'woocommerce' ],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-woo-analytics-csv.php';
		SB_Site_Kit_Woo_Analytics_CSV::boot();
	},
];
