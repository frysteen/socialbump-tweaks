<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ordering for ACF Relationship query loops.
 *
 * A relationship loop hands back the posts in the order they were arranged in
 * the field. This reorders them just before they are shown, without touching
 * what is saved in ACF.
 *
 * Relationship and post object loops both return posts, so the choices here are
 * post properties rather than the sub fields used for a repeater.
 */
class SB_Bricks_Relationship_Ordering {

	const ELEMENTS = [ 'container', 'block', 'div' ];

	public static function boot() {
		add_action( 'init', [ __CLASS__, 'init' ], 0 );
	}

	public static function init() {
		foreach ( (array) apply_filters( 'sb_bricks/relationship_ordering/elements', self::ELEMENTS ) as $name ) {
			add_filter( "bricks/elements/{$name}/controls", [ __CLASS__, 'controls' ] );
		}

		add_filter( 'bricks/query/result', [ __CLASS__, 'result' ], 20, 2 );
	}

	public static function modes() {
		return [
			'original'    => 'Field order',
			'reverse'     => 'Reverse field order',
			'title_asc'   => 'Title, A to Z',
			'title_desc'  => 'Title, Z to A',
			'date_desc'   => 'Date, newest first',
			'date_asc'    => 'Date, oldest first',
			'menu_order'  => 'Menu order',
			'random'      => 'Random',
		];
	}

	/** The loop type names Bricks uses for relationship and post object fields. */
	public static function loop_types() {
		static $types = null;

		if ( $types !== null ) {
			return $types;
		}

		$types = [];

		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
			foreach ( (array) acf_get_field_groups() as $group ) {
				self::collect( (array) acf_get_fields( $group ), null, $types );
			}
		}

		$types = array_values( array_unique( $types ) );

		return $types;
	}

	private static function collect( array $fields, $parent, array &$types ) {
		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) || empty( $field['type'] ) ) {
				continue;
			}

			if ( in_array( $field['type'], [ 'relationship', 'post_object' ], true ) ) {
				$types[] = $parent ? 'acf_' . $parent['name'] . '_' . $field['name'] : 'acf_' . $field['name'];
			}

			if ( ! empty( $field['sub_fields'] ) && in_array( $field['type'], [ 'group', 'repeater' ], true ) ) {
				self::collect( (array) $field['sub_fields'], $field, $types );
			}
		}
	}

	public static function controls( $controls ) {
		if ( ! is_array( $controls ) || ! isset( $controls['query'] ) ) {
			return $controls;
		}

		$show = [ [ 'hasLoop', '!=', '' ] ];

		// The show rule only matters in the builder, so the ACF lookup is skipped elsewhere.
		if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
			$types = self::loop_types();

			if ( $types ) {
				$show[] = [ 'query.objectType', '=', $types ];
			}
		}

		$new = [
			'sbBricksRelationshipOrderSeparator' => [
				'tab'      => 'content',
				'label'    => 'ACF relationship order',
				'type'     => 'separator',
				'required' => $show,
			],
			'sbBricksRelationshipOrder'          => [
				'tab'         => 'content',
				'label'       => 'Order',
				'type'        => 'select',
				'options'     => self::modes(),
				'placeholder' => 'Field order',
				'description' => 'Changes the order the posts are shown in. The order saved in ACF stays the same.',
				'required'    => $show,
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

	public static function result( $result, $query ) {
		if ( ! is_array( $result ) || count( $result ) < 2 || ! is_object( $query ) ) {
			return $result;
		}

		$type = isset( $query->object_type ) ? (string) $query->object_type : '';

		if ( ! in_array( $type, self::loop_types(), true ) ) {
			return $result;
		}

		$settings = ( isset( $query->settings ) && is_array( $query->settings ) ) ? $query->settings : [];
		// The key this setting had before the rename, for loops saved under it.
		$mode     = isset( $settings['sbBricksRelationshipOrder'] ) ? (string) $settings['sbBricksRelationshipOrder'] : ( isset( $settings['sbbtRelationshipOrder'] ) ? (string) $settings['sbbtRelationshipOrder'] : '' );

		if ( $mode === '' || $mode === 'original' || ! array_key_exists( $mode, self::modes() ) ) {
			return $result;
		}

		return self::sort( array_values( $result ), $mode );
	}

	/** Sort the loop items. They may be posts or plain IDs, depending on the field. */
	public static function sort( array $items, $mode ) {
		if ( $mode === 'reverse' ) {
			return array_reverse( $items );
		}

		if ( $mode === 'random' ) {
			shuffle( $items );

			return $items;
		}

		$keyed = [];

		foreach ( $items as $index => $item ) {
			$post = ( $item instanceof WP_Post ) ? $item : get_post( is_array( $item ) ? ( $item['ID'] ?? 0 ) : $item );

			if ( ! $post ) {
				$keyed[] = [ 'index' => $index, 'item' => $item, 'key' => '' ];
				continue;
			}

			if ( strpos( $mode, 'title' ) === 0 ) {
				$key = $post->post_title;
			} elseif ( strpos( $mode, 'date' ) === 0 ) {
				$key = $post->post_date;
			} else {
				$key = str_pad( (string) (int) $post->menu_order, 10, '0', STR_PAD_LEFT ) . $post->post_title;
			}

			$keyed[] = [
				'index' => $index,
				'item'  => $item,
				'key'   => $key,
			];
		}

		$descending = substr( $mode, -5 ) === '_desc';

		usort(
			$keyed,
			function ( $a, $b ) use ( $mode, $descending ) {
				$cmp = strpos( $mode, 'title' ) === 0 ? strnatcasecmp( $a['key'], $b['key'] ) : strcmp( $a['key'], $b['key'] );

				if ( $descending ) {
					$cmp = -$cmp;
				}

				return $cmp !== 0 ? $cmp : $a['index'] <=> $b['index'];
			}
		);

		return array_map(
			function ( $entry ) {
				return $entry['item'];
			},
			$keyed
		);
	}
}