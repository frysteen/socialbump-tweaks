<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post Type: is / is not, with one or more post types.
 *
 * Works like a template's post type condition: pick several types, and
 * "Is not" excludes all of them. Lets one shared template show different
 * sections for Pages, Posts and custom post types.
 *
 * Conditions saved before multi-select (a single post type) still work.
 */
return [
	'key'     => 'sb_bricks_post_type',
	'was'     => [ 'socialbump_post_type' ],
	'label'   => 'Post Type',
	'compare' => [
		'==' => 'Is',
		'!=' => 'Is not',
	],
	'value'   => [
		'placeholder' => 'Select post types',
		'multiple'    => true,
		'options'     => function () {
			$options = [];

			foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $type ) {
				if ( $type->name === 'attachment' ) {
					continue;
				}

				$label                  = ! empty( $type->labels->singular_name ) ? $type->labels->singular_name : $type->label;
				$options[ $type->name ] = sprintf( '%s (%s)', $label, $type->name );
			}

			asort( $options, SORT_NATURAL | SORT_FLAG_CASE );

			return $options;
		},
	],
	'check'   => function ( $compare, $value, $post_id ) {
		$selected = array_values( array_filter( array_map( 'sanitize_key', (array) $value ) ) );
		$type     = get_post_type( $post_id );

		if ( ! $selected || ! $type ) {
			return false;
		}

		$match = in_array( $type, $selected, true );

		return $compare === '!=' ? ! $match : $match;
	},
];