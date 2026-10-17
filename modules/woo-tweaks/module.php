<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Woo Tweaks: what was Site Kit's WooCommerce group.
 *
 * Fixes for the shop front (prices, the cart, product image zoom, logging in to
 * check out) and for the store admin (the default category's name, CSV
 * downloads on Analytics). It needs WooCommerce: on a site without it the
 * module's card says so and nothing loads.
 *
 * It has its own options (sb_tweaks_woo_*), its own code names (SB_Woo_,
 * sb_woo, sb-woo-) and its own page addresses (sb-woo-tweaks-<group>). The
 * feature ids are still woo-*, as Site Kit saved them. The switches and the
 * From Price settings came across from Site Kit once, in
 * SB_Woo_Tweaks::migrate(). A feature that only touches WooCommerce for
 * another module's sake stays there: Bricks keeps its archive display
 * condition, and Site Kit's Block Admin Access keeps its My Account choice.
 */
if ( ! defined( 'SB_WOO_TWEAKS_PATH' ) ) {
	define( 'SB_WOO_TWEAKS_PATH', plugin_dir_path( __FILE__ ) );
	define( 'SB_WOO_TWEAKS_URL', SB_TWEAKS_URL . 'modules/woo-tweaks/' );
}

require_once SB_WOO_TWEAKS_PATH . 'includes/class-sb-woo-tweaks.php';

return [
	'id'          => 'woo',
	'title'       => 'Woo Tweaks',
	'description' => __( 'Fixes and extras for WooCommerce stores: prices, the cart, product image zoom, logging in to check out, the default category and CSV downloads from Analytics.', 'sb-tweaks' ),
	'heading'     => 'Woo Tweaks',
	'requires'    => [ 'woocommerce' ],
	'migrate'     => [ 'SB_Woo_Tweaks', 'migrate' ],

	'groups'      => [
		'shop'  => [
			'title'       => __( 'Shop and Checkout', 'sb-tweaks' ),
			'description' => __( 'Prices, the cart, product images and the checkout.', 'sb-tweaks' ),
		],
		'admin' => [
			'title'       => __( 'Store Admin', 'sb-tweaks' ),
			'description' => __( 'Fixes and extras in the WooCommerce admin.', 'sb-tweaks' ),
		],
	],

	'menu'        => [
		'slug'       => SB_Woo_Tweaks::SLUG,
		'label'      => 'SB Woo Tweaks',
		'page_title' => 'SocialBUMP Woo Tweaks',
		'after'      => 'sb-site-kit',
	],

	'bar'         => [
		'id'    => 'woo-tweaks',
		'label' => 'Woo Tweaks',
	],

	'prompt'      => 'You are picking up work on the Woo Tweaks module of SocialBUMP Tweaks, a WordPress plugin. Everything is developed on the hub, plugins.socialbump.com.au, which you reach through its Novamira MCP connector. The hub has no WooCommerce, so the module shows there as needing it. Before changing anything, read wp-content/plugins/socialbump-tweaks/modules/woo-tweaks/docs/context.md for this module, and wp-content/plugins/socialbump-tweaks/docs/context.md for the framework it runs on. Keep the module notes current: when you change how something works or learn something the hard way, write it there in the same session. Tell me what you have read before you start, and before you finish, bring the notes up to date and tell me exactly what you added or corrected.',

	'boot'        => function ( $context ) {
		SB_Woo_Tweaks::boot();
	},
];
