<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared engine for the SocialBUMP element conditions.
 *
 * Each condition is its own module (includes/modules/condition-...), so each
 * gets its own switch on the settings page. A condition module registers a
 * definition array from its condition.php:
 *
 *   'key'     Unique key. Bricks saves it with the element, so never rename it.
 *   'label'   Name shown in the Bricks Conditions panel.
 *   'compare' [ value => label ] choices. The first one is the default.
 *   'value'   Optional. [ 'placeholder' => '', 'options' => callable returning [ value => label ], 'multiple' => true ].
 *             With 'multiple', check() receives an array of the chosen values.
 *   'check'   callable( $compare, $value, $post_id ). Return true to show the element.
 *
 * To add a condition: copy one of the condition module folders, give it a new
 * folder name, id and title in module.php, and write the new condition.php.
 */
class SB_Bricks_Conditions {

	const GROUP = 'socialbump';

	private static $conditions = [];

	/** Old key => current key, for conditions saved before a rename. */
	private static $aliases = [];

	private static $hooked = false;

	public static function register( $condition ) {
		if (
			! is_array( $condition ) ||
			empty( $condition['key'] ) ||
			empty( $condition['compare'] ) ||
			! isset( $condition['check'] ) ||
			! is_callable( $condition['check'] )
		) {
			return;
		}

		self::$conditions[ $condition['key'] ] = $condition;

		/**
		 * A condition that has been renamed answers to its old key as well.
		 *
		 * Bricks saves the key with the element, so every page built before the
		 * rename still says the old one. The alias is evaluated but never offered
		 * in the builder, so old pages keep working and nobody can pick it again.
		 * Drop these once every site has been converted.
		 */
		if ( ! empty( $condition['was'] ) ) {
			foreach ( (array) $condition['was'] as $old_key ) {
				self::$aliases[ $old_key ] = $condition['key'];
			}
		}

		if ( ! self::$hooked ) {
			self::$hooked = true;
			add_action( 'init', [ __CLASS__, 'init' ], 0 );
		}
	}

	public static function init() {
		// The old SocialBUMP Bricks Toolkit snippet registers the same conditions. Step aside while it is on.
		if ( function_exists( 'socialbump_get_acf_repeater_options' ) ) {
			add_action( 'admin_notices', [ __CLASS__, 'snippet_notice' ] );
			return;
		}

		self::$conditions = (array) apply_filters( 'sb_bricks/element_conditions', self::$conditions );

		add_filter( 'bricks/conditions/groups', [ __CLASS__, 'groups' ] );
		add_filter( 'bricks/conditions/options', [ __CLASS__, 'options' ] );
		add_filter( 'bricks/conditions/result', [ __CLASS__, 'result' ], 10, 3 );
	}

	public static function groups( $groups ) {
		$groups[] = [
			'name'  => self::GROUP,
			'label' => 'SocialBUMP',
		];

		return $groups;
	}

	/**
	 * Bricks only asks for this list when the builder is open.
	 */
	public static function options( $options ) {
		foreach ( self::$conditions as $key => $condition ) {
			$option = [
				'key'     => $key,
				'label'   => isset( $condition['label'] ) ? $condition['label'] : $key,
				'group'   => self::GROUP,
				'compare' => [
					'type'        => 'select',
					'options'     => $condition['compare'],
					'placeholder' => reset( $condition['compare'] ),
				],
			];

			if ( ! empty( $condition['value'] ) ) {
				// Choices may be given as a plain list or as a function that builds one.
				$choices = isset( $condition['value']['options'] ) ? $condition['value']['options'] : [];
				$choices = is_callable( $choices ) ? (array) call_user_func( $choices ) : (array) $choices;

				$option['value'] = [
					'type'        => 'select',
					'options'     => $choices,
					'placeholder' => isset( $condition['value']['placeholder'] ) ? $condition['value']['placeholder'] : '',
					'searchable'  => true,
				];

				if ( ! empty( $condition['value']['multiple'] ) ) {
					$option['value']['multiple'] = true;
				}
			}

			$options[] = $option;
		}

		return $options;
	}

	public static function result( $result, $key, $condition ) {
		// A key saved before a rename still points at the condition that replaced it.
		if ( isset( self::$aliases[ $key ] ) ) {
			$key = self::$aliases[ $key ];
		}

		// Leave conditions from Bricks and other plugins alone.
		if ( ! isset( self::$conditions[ $key ] ) ) {
			return $result;
		}

		$definition = self::$conditions[ $key ];
		$compare    = isset( $condition['compare'] ) ? (string) $condition['compare'] : '';

		if ( ! array_key_exists( $compare, $definition['compare'] ) ) {
			$choices = array_keys( $definition['compare'] );
			$compare = (string) $choices[0];
		}

		$value = '';

		if ( isset( $condition['value'] ) && is_array( $condition['value'] ) ) {
			$value = array_values( array_filter( array_map( 'trim', array_map( 'strval', array_filter( $condition['value'], 'is_scalar' ) ) ), 'strlen' ) );
		} elseif ( isset( $condition['value'] ) && is_scalar( $condition['value'] ) ) {
			$value = trim( (string) $condition['value'] );
		}

		$post_id = self::post_id();

		// Most conditions ask about the current post. One that does not, such as an
		// archive condition, sets 'needs_post' => false and is checked regardless.
		$needs_post = ! isset( $definition['needs_post'] ) || $definition['needs_post'];

		if ( $needs_post && ! $post_id ) {
			return false;
		}

		return (bool) call_user_func( $definition['check'], $compare, $value, $post_id );
	}

	/**
	 * The post a condition is about.
	 *
	 * get_the_ID() follows the current Bricks query loop, so inside a loop each
	 * post is checked in turn. Outside a loop it is usually the page being
	 * shown, with one exception: on the posts page WordPress sets the current
	 * post to the first post in the list, not the page. A template on the Blog
	 * page then answered for that post instead, so Bricks Content said "not
	 * built with Bricks" about a page that was, and showed the wrong section.
	 * There, outside a loop, the Blog page itself is used.
	 */
	private static function post_id() {
		$looping = class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'is_any_looping' ) && \Bricks\Query::is_any_looping();

		if ( ! $looping && is_home() ) {
			$blog = (int) get_option( 'page_for_posts' );

			if ( $blog > 0 ) {
				return $blog;
			}
		}

		return (int) get_the_ID();
	}

	public static function snippet_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>SB Tweaks:</strong> ';
		esc_html_e( 'The SocialBUMP conditions are paused on this site because the old SocialBUMP Bricks Toolkit snippet is still switched on. Turn that snippet off in WP CodeBox and the plugin takes over. Conditions already set in Bricks keep working.', 'sb-tweaks' );
		echo '</p></div>';
	}
}