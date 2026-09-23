<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF Gallery as a Bricks query loop.
 *
 * Bricks only turns repeaters, relationships and post objects into loop types,
 * so galleries can normally only be looped with a PHP query. This registers a
 * query type per gallery field (sb_bricks_gallery_<field name>) and returns the
 * attachment posts, so every element inside the loop can use the media dynamic
 * data tags: image, alt text, caption, title and so on.
 *
 * The field's return format does not matter. IDs, URLs and arrays all work.
 * Options page galleries are picked up too when the loop runs on a page with no
 * post of its own.
 */
class SB_Bricks_Gallery_Loop {

	const PREFIX     = 'sb_bricks_gallery_';

	/** What the query type was called before the rename. Loops saved under it still have to run. */
	const PREFIX_OLD = 'sbbt_gallery_';

	const ORDER_MODES = [
		''             => 'Gallery order',
		'reverse'      => 'Reverse gallery order',
		'title_asc'    => 'Title, A to Z',
		'title_desc'   => 'Title, Z to A',
		'filename_asc' => 'File name, A to Z',
		'date_asc'     => 'Date uploaded, oldest first',
		'date_desc'    => 'Date uploaded, newest first',
		'random'       => 'Random',
	];

	private static $fields = null;

	public static function boot() {
		add_filter( 'bricks/setup/control_options', [ __CLASS__, 'query_types' ], 20 );
		add_filter( 'bricks/query/run', [ __CLASS__, 'run' ], 10, 2 );
		add_filter( 'bricks/query/loop_object_id', [ __CLASS__, 'loop_object_id' ], 10, 3 );
		add_filter( 'bricks/query/loop_object_type', [ __CLASS__, 'loop_object_type' ], 10, 3 );
		add_filter( 'post_thumbnail_id', [ __CLASS__, 'thumbnail_id' ], 10, 2 );

		foreach ( [ 'container', 'block', 'div' ] as $element ) {
			add_filter( "bricks/elements/{$element}/controls", [ __CLASS__, 'controls' ] );
		}
	}

	/**
	 * Every gallery field on the site, keyed by its query type name.
	 */
	public static function fields() {
		if ( self::$fields !== null ) {
			return self::$fields;
		}

		self::$fields = [];

		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return self::$fields;
		}

		foreach ( (array) acf_get_field_groups() as $group ) {
			self::collect( (array) acf_get_fields( $group ), $group, self::$fields );
		}

		return self::$fields;
	}

	private static function collect( array $fields, $group, array &$found ) {
		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) || empty( $field['type'] ) ) {
				continue;
			}

			if ( $field['type'] === 'gallery' ) {
				$found[ self::PREFIX . $field['name'] ] = [
					'name'  => $field['name'],
					'key'   => isset( $field['key'] ) ? $field['key'] : '',
					'label' => ! empty( $field['label'] ) ? $field['label'] : $field['name'],
					'group' => ! empty( $group['title'] ) ? $group['title'] : '',
				];
				continue;
			}

			// Galleries inside a group or repeater are reachable by name too.
			if ( ! empty( $field['sub_fields'] ) ) {
				self::collect( (array) $field['sub_fields'], $group, $found );
			}
		}
	}

	public static function query_types( $control_options ) {
		foreach ( self::fields() as $type => $field ) {
			$control_options['queryTypes'][ $type ] = 'ACF Gallery: ' . $field['label'];
		}

		return $control_options;
	}

	/**
	 * Ordering controls, shown only for a gallery loop.
	 */
	public static function controls( $controls ) {
		if ( ! is_array( $controls ) || ! isset( $controls['query'] ) ) {
			return $controls;
		}

		$types = array_keys( self::fields() );

		if ( ! $types ) {
			return $controls;
		}

		$show = [
			[ 'hasLoop', '!=', '' ],
			[ 'query.objectType', '=', $types ],
		];

		$new = [
			'sbBricksGalleryOrderSeparator' => [
				'tab'      => 'content',
				'label'    => 'Gallery order',
				'type'     => 'separator',
				'required' => $show,
			],
			'sbBricksGalleryOrder'          => [
				'tab'         => 'content',
				'label'       => 'Order',
				'type'        => 'select',
				'options'     => self::ORDER_MODES,
				'placeholder' => 'Gallery order',
				'required'    => $show,
			],
			'sbBricksGalleryLimit'          => [
				'tab'            => 'content',
				'label'          => 'Limit',
				'type'           => 'number',
				'min'            => 1,
				'placeholder'    => 'All',
				'hasDynamicData' => false,
				'required'       => $show,
			],
			'sbBricksGalleryOffset'         => [
				'tab'            => 'content',
				'label'          => 'Offset',
				'type'           => 'number',
				'min'            => 0,
				'placeholder'    => '0',
				'hasDynamicData' => false,
				'required'       => $show,
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
	 * The loop items: attachment posts, in the chosen order.
	 */
	public static function run( $results, $query ) {
		$fields = self::fields();

		if ( ! isset( $query->object_type ) || ! isset( $fields[ $query->object_type ] ) ) {
			return $results;
		}

		$field    = $fields[ $query->object_type ];
		$settings = ( isset( $query->settings ) && is_array( $query->settings ) ) ? $query->settings : [];
		$ids      = self::ids( $field );

		if ( ! $ids ) {
			return [];
		}

		$ids = self::sort( $ids, isset( $settings['sbBricksGalleryOrder'] ) ? (string) $settings['sbBricksGalleryOrder'] : ( isset( $settings['sbbtGalleryOrder'] ) ? (string) $settings['sbbtGalleryOrder'] : '' ) );

		$offset = isset( $settings['sbBricksGalleryOffset'] ) ? max( 0, (int) $settings['sbBricksGalleryOffset'] ) : ( isset( $settings['sbbtGalleryOffset'] ) ? max( 0, (int) $settings['sbbtGalleryOffset'] ) : 0 );
		$limit  = isset( $settings['sbBricksGalleryLimit'] ) ? (int) $settings['sbBricksGalleryLimit'] : ( isset( $settings['sbbtGalleryLimit'] ) ? (int) $settings['sbbtGalleryLimit'] : 0 );

		if ( $offset || $limit > 0 ) {
			$ids = array_slice( $ids, $offset, $limit > 0 ? $limit : null );
		}

		if ( ! $ids ) {
			return [];
		}

		$posts = get_posts(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			]
		);

		return $posts;
	}

	/**
	 * Attachment IDs from the field, whatever its return format.
	 */
	private static function ids( $field ) {
		if ( ! function_exists( 'get_field' ) ) {
			return [];
		}

		$name = $field['key'] ? $field['key'] : $field['name'];

		// Inside another loop, a gallery sub field belongs to the current row.
		$looping = class_exists( '\Bricks\Query' ) ? \Bricks\Query::is_any_looping() : false;

		if ( $looping ) {
			$loop_object = \Bricks\Query::get_loop_object( $looping );

			if ( is_array( $loop_object ) && array_key_exists( $field['name'], $loop_object ) ) {
				return self::to_ids( $loop_object[ $field['name'] ] );
			}
		}

		$post_id = get_the_ID();
		$value   = $post_id ? get_field( $name, $post_id ) : null;

		// Nothing on this post: try an options page gallery of the same name.
		if ( empty( $value ) ) {
			$value = get_field( $name, 'option' );
		}

		return self::to_ids( $value );
	}

	/**
	 * Normalise IDs, URLs or image arrays into attachment IDs.
	 */
	private static function to_ids( $value ) {
		if ( empty( $value ) ) {
			return [];
		}

		$ids = [];

		foreach ( (array) $value as $item ) {
			if ( is_array( $item ) && isset( $item['ID'] ) ) {
				$ids[] = (int) $item['ID'];
			} elseif ( is_array( $item ) && isset( $item['id'] ) ) {
				$ids[] = (int) $item['id'];
			} elseif ( $item instanceof WP_Post ) {
				$ids[] = (int) $item->ID;
			} elseif ( is_numeric( $item ) ) {
				$ids[] = (int) $item;
			} elseif ( is_string( $item ) ) {
				$id = attachment_url_to_postid( $item );

				if ( $id ) {
					$ids[] = $id;
				}
			}
		}

		return array_values( array_filter( array_unique( $ids ) ) );
	}

	private static function sort( array $ids, $mode ) {
		if ( $mode === '' || ! array_key_exists( $mode, self::ORDER_MODES ) ) {
			return $ids;
		}

		if ( $mode === 'reverse' ) {
			return array_reverse( $ids );
		}

		if ( $mode === 'random' ) {
			shuffle( $ids );

			return $ids;
		}

		$keys = [];

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( ! $post ) {
				$keys[ $id ] = '';
				continue;
			}

			if ( strpos( $mode, 'title' ) === 0 ) {
				$keys[ $id ] = $post->post_title;
			} elseif ( strpos( $mode, 'filename' ) === 0 ) {
				$keys[ $id ] = basename( (string) get_post_meta( $id, '_wp_attached_file', true ) );
			} else {
				$keys[ $id ] = $post->post_date;
			}
		}

		uasort(
			$keys,
			function ( $a, $b ) use ( $mode ) {
				$cmp = strpos( $mode, 'date' ) === 0 ? strcmp( $a, $b ) : strnatcasecmp( $a, $b );

				return substr( $mode, -5 ) === '_desc' ? -$cmp : $cmp;
			}
		);

		return array_keys( $keys );
	}

	/**
	 * Tell Bricks each loop item is an attachment post, so media dynamic data works.
	 */
	public static function loop_object_id( $object_id, $loop_object, $query_id ) {
		return ( $loop_object instanceof WP_Post ) && self::is_ours( $query_id ) ? $loop_object->ID : $object_id;
	}

	public static function loop_object_type( $object_type, $loop_object, $query_id ) {
		return self::is_ours( $query_id ) ? 'post' : $object_type;
	}

	/**
	 * Inside a gallery loop each item is the image itself, but an attachment has
	 * no featured image of its own, so {featured_image} would come back empty.
	 * Point it at the attachment, so the Image element and every featured image
	 * tag show the gallery image.
	 *
	 * Only applies to image attachments while one of these loops is running.
	 */
	public static function thumbnail_id( $thumbnail_id, $post ) {
		if ( $thumbnail_id ) {
			return $thumbnail_id;
		}

		$post = get_post( $post );

		if ( ! $post || $post->post_type !== 'attachment' || strpos( (string) $post->post_mime_type, 'image/' ) !== 0 ) {
			return $thumbnail_id;
		}

		if ( ! class_exists( '\Bricks\Query' ) ) {
			return $thumbnail_id;
		}

		$looping = \Bricks\Query::is_any_looping();

		if ( ! $looping || ! self::is_ours( $looping ) ) {
			return $thumbnail_id;
		}

		$loop_object = \Bricks\Query::get_loop_object( $looping );

		// Only the image currently being looped over.
		if ( ! ( $loop_object instanceof WP_Post ) || (int) $loop_object->ID !== (int) $post->ID ) {
			return $thumbnail_id;
		}

		return (int) $post->ID;
	}

	private static function is_ours( $query_id ) {
		if ( ! class_exists( '\Bricks\Query' ) ) {
			return false;
		}

		$type = \Bricks\Query::get_query_object_type( $query_id );

		return is_string( $type ) && ( strpos( $type, self::PREFIX ) === 0 || strpos( $type, self::PREFIX_OLD ) === 0 );
	}
}