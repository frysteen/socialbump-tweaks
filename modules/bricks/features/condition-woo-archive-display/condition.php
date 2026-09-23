<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Archive Display: is / is not, for what the shop is set to show.
 *
 * WooCommerce decides whether an archive lists products, its subcategories, or
 * both, from the settings under Appearance, Customise, WooCommerce, Product
 * Catalog. This reads that decision for the archive being viewed, so one shop
 * template can hold a product loop and a category loop and show the right one.
 *
 * Pick more than one to match any of them. Off a shop archive nothing matches.
 */
return [
	'key'        => 'sb_bricks_woo_archive_display',
	'was'     => [ 'socialbump_woo_archive_display' ],
	'label'      => 'WooCommerce Archive Display',
	'needs_post' => false,
	'compare'    => [
		'==' => 'Is',
		'!=' => 'Is not',
	],
	'value'      => [
		'placeholder' => 'Select what is shown',
		'multiple'    => true,
		'options'     => [
			'products'      => 'Products',
			'subcategories' => 'Categories',
			'both'          => 'Products and categories',
		],
	],
	'check'      => function ( $compare, $value, $post_id ) {
		if ( ! function_exists( 'woocommerce_get_loop_display_mode' ) ) {
			return false;
		}

		// Only the shop page and the product taxonomy archives have a display mode.
		if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
			return false;
		}

		$selected = array_values( array_filter( array_map( 'sanitize_key', (array) $value ) ) );

		if ( ! $selected ) {
			return false;
		}

		$match = in_array( (string) woocommerce_get_loop_display_mode(), $selected, true );

		return $compare === '!=' ? ! $match : $match;
	},
];
