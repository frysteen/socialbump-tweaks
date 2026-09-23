<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF Relationship: has items / has none.
 *
 * Covers Relationship and Post Object fields, which both hold references to
 * other posts. Stores the field key, so two fields with the same name in
 * different groups never get mixed up, and counts the raw value so the field's
 * return format does not matter.
 */
return [
	'key'     => 'sb_bricks_acf_relationship',
	'was'     => [ 'socialbump_acf_relationship' ],
	'label'   => 'ACF Relationship',
	'compare' => [
		'has_items' => 'Has items',
		'has_none'  => 'Has no items',
	],
	'value'   => [
		'placeholder' => 'Select field',
		'options'     => function () {
			if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
				return [];
			}

			$options = [];

			foreach ( (array) acf_get_field_groups() as $group ) {
				foreach ( (array) acf_get_fields( $group ) as $field ) {
					if ( empty( $field['key'] ) || empty( $field['name'] ) || empty( $field['type'] ) ) {
						continue;
					}

					if ( ! in_array( $field['type'], [ 'relationship', 'post_object' ], true ) ) {
						continue;
					}

					$options[ $field['key'] ] = sprintf(
						'%s: %s (%s)',
						! empty( $group['title'] ) ? $group['title'] : 'ACF',
						! empty( $field['label'] ) ? $field['label'] : $field['name'],
						$field['name']
					);
				}
			}

			asort( $options, SORT_NATURAL | SORT_FLAG_CASE );

			return $options;
		},
	],
	'check'   => function ( $compare, $value, $post_id ) {
		$value = is_array( $value ) ? (string) reset( $value ) : (string) $value;

		if ( $value === '' || ! function_exists( 'get_field' ) ) {
			return false;
		}

		// Raw, so this works whatever the return format is set to.
		$items = get_field( $value, $post_id, false );

		if ( $items === null || $items === '' || $items === false ) {
			$count = 0;
		} elseif ( is_array( $items ) ) {
			$count = count( array_filter( $items ) );
		} else {
			$count = 1;
		}

		return $compare === 'has_none' ? $count === 0 : $count > 0;
	},
];