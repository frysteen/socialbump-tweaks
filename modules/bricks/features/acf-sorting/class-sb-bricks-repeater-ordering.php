<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF Repeater Ordering for Bricks query loops.
 *
 * Reorders the rows Bricks fetched for an ACF repeater loop, just before they
 * are displayed. The rows saved in ACF are never touched.
 *
 * Setting keys match the old SocialBUMP Bricks Toolkit snippet, so loops that
 * were already set up keep working.
 */
class SB_Bricks_Repeater_Ordering {

	const ELEMENTS = [ 'container', 'block', 'div' ];

	/** Modes that sort by a sub field. */
	const FIELD_MODES = [ 'alpha_asc', 'alpha_desc', 'numeric_asc', 'numeric_desc', 'date_asc', 'date_desc' ];

	public static function boot() {
		add_action( 'init', [ __CLASS__, 'init' ], 0 );
	}

	public static function init() {
		// The old SocialBUMP Bricks Toolkit snippet does the same job. Step aside while it is on.
		if ( function_exists( 'socialbump_sort_repeater_rows' ) ) {
			add_action( 'admin_notices', [ __CLASS__, 'snippet_notice' ] );
			return;
		}

		foreach ( (array) apply_filters( 'sb_bricks/repeater_ordering/elements', self::ELEMENTS ) as $name ) {
			add_filter( "bricks/elements/{$name}/controls", [ __CLASS__, 'controls' ] );
		}

		add_filter( 'bricks/query/result', [ __CLASS__, 'result' ], 20, 2 );
	}

	public static function modes() {
		return [
			'original'     => 'Original order',
			'reverse'      => 'Reverse original order',
			'alpha_asc'    => 'A to Z',
			'alpha_desc'   => 'Z to A',
			'numeric_asc'  => 'Numeric, low to high',
			'numeric_desc' => 'Numeric, high to low',
			'date_asc'     => 'Date, oldest to newest',
			'date_desc'    => 'Date, newest to oldest',
			'random'       => 'Random',
		];
	}

	/**
	 * Add the settings straight under Bricks' own Query control.
	 * In the builder they only show when the loop type is an ACF repeater.
	 */
	public static function controls( $controls ) {
		if ( ! is_array( $controls ) || ! isset( $controls['query'] ) ) {
			return $controls;
		}

		$show = [ [ 'hasLoop', '!=', '' ] ];

		// The show rule only matters in the builder, so the ACF lookup is skipped everywhere else.
		if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
			$types = self::repeater_loop_types();

			if ( $types ) {
				$show[] = [ 'query.objectType', '=', $types ];
			}
		}

		$new = [
			'sbBricksRepeaterOrderSeparator' => [
				'tab'      => 'content',
				'label'    => 'ACF repeater order',
				'type'     => 'separator',
				'required' => $show,
			],
			'sbBricksRepeaterOrder'          => [
				'tab'         => 'content',
				'label'       => 'Repeater order',
				'type'        => 'select',
				'options'     => self::modes(),
				'placeholder' => 'Original order',
				'description' => 'Changes the order rows are shown in. The order saved in ACF stays the same.',
				'required'    => $show,
			],
			'sbBricksRepeaterOrderField'     => [
				'tab'            => 'content',
				'label'          => 'Sort by sub field',
				'type'           => 'text',
				'placeholder'    => 'e.g. start_date',
				'description'    => 'The ACF sub field name, exactly as it appears in ACF.',
				'hasDynamicData' => false,
				'required'       => array_merge( $show, [ [ 'sbBricksRepeaterOrder', '=', self::FIELD_MODES ] ] ),
			],
		];

		$out = [];

		foreach ( $controls as $key => $control ) {
			$out[ $key ] = $control;

			if ( $key === 'query' ) {
				$out = array_merge( $out, $new );
			}
		}

		return $out;
	}

	/**
	 * The loop type names Bricks uses for ACF repeaters, e.g. acf_services.
	 * Bricks names them acf_ plus the field name, with the parent field name in
	 * between for repeaters inside a group or another repeater.
	 */
	private static function repeater_loop_types() {
		static $types = null;

		if ( $types !== null ) {
			return $types;
		}

		$types = [];

		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
			foreach ( (array) acf_get_field_groups() as $group ) {
				self::collect_repeaters( (array) acf_get_fields( $group ), null, $types );
			}
		}

		// Also take Bricks' own list when it is ready, in case it named a loop differently.
		if ( class_exists( '\Bricks\Integrations\Dynamic_Data\Providers' ) ) {
			$provider = \Bricks\Integrations\Dynamic_Data\Providers::get_registered_provider( 'acf' );

			if ( $provider && property_exists( $provider, 'loop_tags' ) ) {
				try {
					$property = new ReflectionProperty( $provider, 'loop_tags' );
					$property->setAccessible( true );

					foreach ( (array) $property->getValue( $provider ) as $name => $tag ) {
						if ( isset( $tag['field']['type'] ) && $tag['field']['type'] === 'repeater' ) {
							$types[] = $name;
						}
					}
				} catch ( \Throwable $e ) {
					// Fall back to the ACF list only.
				}
			}
		}

		$types = array_values( array_unique( $types ) );

		return $types;
	}

	private static function collect_repeaters( array $fields, $parent, array &$types ) {
		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) || empty( $field['type'] ) ) {
				continue;
			}

			if ( $field['type'] === 'repeater' ) {
				$types[] = $parent ? 'acf_' . $parent['name'] . '_' . $field['name'] : 'acf_' . $field['name'];
			}

			if ( ! empty( $field['sub_fields'] ) && in_array( $field['type'], [ 'group', 'repeater' ], true ) ) {
				self::collect_repeaters( (array) $field['sub_fields'], $field, $types );
			}
		}
	}

	public static function result( $result, $query ) {
		if ( ! is_array( $result ) || count( $result ) < 2 || ! is_object( $query ) ) {
			return $result;
		}

		// Only ACF loops. Bricks names them acf_...
		$type = isset( $query->object_type ) ? (string) $query->object_type : '';

		if ( strpos( $type, 'acf_' ) !== 0 ) {
			return $result;
		}

		// Repeater rows are arrays. Relationship and post object loops return posts, so skip those.
		if ( ! is_array( reset( $result ) ) ) {
			return $result;
		}

		$settings = ( isset( $query->settings ) && is_array( $query->settings ) ) ? $query->settings : [];
		// The keys these settings had before the rename, for loops saved under them.
		$mode     = isset( $settings['sbBricksRepeaterOrder'] ) ? (string) $settings['sbBricksRepeaterOrder'] : ( isset( $settings['socialbumpRepeaterOrder'] ) ? (string) $settings['socialbumpRepeaterOrder'] : '' );

		if ( $mode === '' || $mode === 'original' || ! array_key_exists( $mode, self::modes() ) ) {
			return $result;
		}

		$field = isset( $settings['sbBricksRepeaterOrderField'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $settings['sbBricksRepeaterOrderField'] ) : ( isset( $settings['socialbumpRepeaterOrderField'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $settings['socialbumpRepeaterOrderField'] ) : '' );

		return self::sort( $result, $field, $mode );
	}

	/**
	 * Sort rows. Rows with equal values keep their ACF order, and empty values always go last.
	 */
	public static function sort( array $rows, $field, $mode ) {
		$rows = array_values( $rows );

		if ( $mode === 'reverse' ) {
			return array_reverse( $rows );
		}

		if ( $mode === 'random' ) {
			shuffle( $rows );
			return $rows;
		}

		if ( $field === '' || ! in_array( $mode, self::FIELD_MODES, true ) ) {
			return $rows;
		}

		$kind  = strpos( $mode, 'numeric' ) === 0 ? 'numeric' : ( strpos( $mode, 'date' ) === 0 ? 'date' : 'alpha' );
		$desc  = substr( $mode, -5 ) === '_desc';
		$items = [];

		foreach ( $rows as $index => $row ) {
			$raw     = ( is_array( $row ) && array_key_exists( $field, $row ) ) ? $row[ $field ] : null;
			$items[] = [
				'index' => $index,
				'row'   => $row,
				'key'   => self::sort_key( $raw, $kind ),
			];
		}

		usort(
			$items,
			function ( $a, $b ) use ( $kind, $desc ) {
				$a_empty = $a['key'] === null;
				$b_empty = $b['key'] === null;

				if ( $a_empty || $b_empty ) {
					if ( $a_empty && $b_empty ) {
						return $a['index'] <=> $b['index'];
					}

					return $a_empty ? 1 : -1;
				}

				$cmp = $kind === 'alpha' ? strnatcasecmp( $a['key'], $b['key'] ) : ( $a['key'] <=> $b['key'] );

				if ( $desc ) {
					$cmp = -$cmp;
				}

				return $cmp !== 0 ? $cmp : $a['index'] <=> $b['index'];
			}
		);

		return array_map(
			function ( $item ) {
				return $item['row'];
			},
			$items
		);
	}

	/**
	 * The value to sort on, or null when it should count as empty. Zero is a real value.
	 */
	private static function sort_key( $value, $kind ) {
		if ( $kind === 'date' ) {
			return self::timestamp( $value );
		}

		$text = self::text( $value );

		if ( $text === '' ) {
			return null;
		}

		if ( $kind === 'numeric' ) {
			// Strip currency symbols, commas and units: "$1,200.50" becomes 1200.5.
			$clean = preg_replace( '/[^0-9.\-]/', '', $text );

			return is_numeric( $clean ) ? (float) $clean : null;
		}

		return $text;
	}

	/**
	 * Turn an ACF value into text: plain values, select label/value arrays, checkboxes, posts and terms.
	 */
	private static function text( $value ) {
		if ( $value === null ) {
			return '';
		}

		if ( is_scalar( $value ) ) {
			return trim( (string) $value );
		}

		if ( $value instanceof WP_Post ) {
			return trim( $value->post_title );
		}

		if ( $value instanceof WP_Term ) {
			return trim( $value->name );
		}

		if ( is_array( $value ) ) {
			foreach ( [ 'label', 'value', 'title' ] as $key ) {
				if ( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ) {
					return trim( (string) $value[ $key ] );
				}
			}

			$parts = [];

			foreach ( $value as $item ) {
				$part = self::text( $item );

				if ( $part !== '' ) {
					$parts[] = $part;
				}
			}

			return implode( ' ', $parts );
		}

		return '';
	}

	/**
	 * Turn an ACF date into a timestamp.
	 *
	 * Handles ACF's stored Ymd format, Unix timestamps, DateTime objects, and slash
	 * dates like 11/09/2026. Slash dates are read day first unless the site language
	 * is US English. Change that with the sb_bricks/repeater_ordering/day_first filter.
	 */
	private static function timestamp( $value ) {
		if ( $value instanceof DateTimeInterface ) {
			return $value->getTimestamp();
		}

		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$value = trim( (string) $value );

		if ( $value === '' ) {
			return null;
		}

		if ( preg_match( '/^\d{8}$/', $value ) ) {
			$date = DateTime::createFromFormat( '!Ymd', $value );

			if ( $date instanceof DateTime ) {
				return $date->getTimestamp();
			}
		}

		if ( ctype_digit( $value ) && strlen( $value ) >= 9 ) {
			return (int) $value;
		}

		if ( preg_match( '#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4}|\d{2})(?:[\sT]+(.+))?$#', $value, $m ) ) {
			$day_first = (bool) apply_filters( 'sb_bricks/repeater_ordering/day_first', get_locale() !== 'en_US' );
			$day       = (int) ( $day_first ? $m[1] : $m[2] );
			$month     = (int) ( $day_first ? $m[2] : $m[1] );
			$year      = (int) ( strlen( $m[3] ) === 2 ? '20' . $m[3] : $m[3] );

			if ( ! checkdate( $month, $day, $year ) ) {
				return null;
			}

			$time  = isset( $m[4] ) ? trim( $m[4] ) : '';
			$stamp = strtotime( trim( sprintf( '%04d-%02d-%02d %s', $year, $month, $day, $time ) ) );

			return $stamp === false ? null : $stamp;
		}

		$stamp = strtotime( $value );

		return $stamp === false ? null : $stamp;
	}

	public static function snippet_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>SB Tweaks:</strong> ';
		esc_html_e( 'ACF Repeater Ordering is paused on this site because the old SocialBUMP repeater ordering snippet is still switched on. Turn that snippet off in WP CodeBox and the plugin takes over. Loops already set up keep their settings.', 'sb-tweaks' );
		echo '</p></div>';
	}
}