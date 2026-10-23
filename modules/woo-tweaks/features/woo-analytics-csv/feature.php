<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'woo-analytics-csv',
	'title'       => 'Download Analytics as CSV',
	'description' => 'Puts a Download CSV button on the Analytics reports, covering the date range on screen. WooCommerce normally builds the file in the background and emails a link instead.',
	'section'     => 'admin',
	'default'     => false,
	'requires'    => [ 'woocommerce' ],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-woo-analytics-csv.php';
		SB_Woo_Analytics_CSV::boot();
	},
];
