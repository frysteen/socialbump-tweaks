<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'woo-default-category-title',
	'title'       => 'Fix Default Category Title Bug',
	'description' => 'WooCommerce blanks out the name, edit link and row actions of the default category on Products, Categories. This puts them back and moves the tooltip after the name.',
	'section'     => 'admin',
	'default'     => true,
	'requires'    => [ 'woocommerce' ],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-woo-default-category-title.php';
		SB_Woo_Default_Category_Title::boot();
	},
];
