<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'woo-default-category-title',
	'title'       => __( 'Fix Default Category Title Bug', 'sb-tweaks' ),
	'description' => __( 'WooCommerce blanks out the name, edit link and row actions of the default category on Products, Categories. This puts them back and moves the tooltip after the name.', 'sb-tweaks' ),
	'section'     => 'woocommerce',
	'default'     => true,
	'requires'    => [ 'woocommerce' ],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-woo-default-category-title.php';
		SB_Site_Kit_Woo_Default_Category_Title::boot();
	},
];
