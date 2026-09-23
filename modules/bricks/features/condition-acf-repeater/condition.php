<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF Repeater: has rows / has no rows.
 *
 * Stores the repeater's field key, so two repeaters with the same name in
 * different field groups never get mixed up. Counts the rows directly
 * rather than calling have_rows(), so no ACF loop is left open.
 */
return [
	'key'     => 'sb_bricks_acf_repeater',
	'was'     => [ 'socialbump_acf_repeater' ],
	'label'   => 'ACF Repeater',
	'compare' => [
		'has_rows'    => 'Has rows',
		'has_no_rows' => 'Has no rows',
	],
	'value'   => [
		'placeholder' => 'Select repeater',
		'options'     => function () {
			if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
				return [];
			}

			$options = [];

			foreach ( (array) acf_get_field_groups() as $group ) {
				foreach ( (array) acf_get_fields( $group ) as $field ) {
					if ( empty( $field['key'] ) || empty( $field['name'] ) || ! isset( $field['type'] ) || $field['type'] !== 'repeater' ) {
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

		$rows  = get_field( $value, $post_id, false );
		$count = is_array( $rows ) ? count( $rows ) : (int) $rows;

		return $compare === 'has_no_rows' ? $count === 0 : $count > 0;
	},
];