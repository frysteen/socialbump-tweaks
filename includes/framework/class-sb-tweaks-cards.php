<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cards that collapse to a title, with a Reorder dialogue to arrange them.
 *
 * Ported from the SocialBUMP_Cards class the standalone plugins share. Inside
 * one plugin nothing is shared, so the class_exists guard is gone and one AJAX
 * endpoint serves every page of cards.
 *
 * The arrangement is the user's, not the site's: it is kept in user meta under
 * one key per page, and saved over AJAX as they go, so it never touches the
 * settings form or the save button. The key is the page's option name, which
 * is how each user's arrangement from the standalone plugins carries straight
 * over.
 *
 * A page uses it in four lines: register() once at boot with the page's key
 * and the top level menu slug it belongs to, sort() to put the cards in the
 * saved order, container_attributes() on the grid and card_attribute() on
 * each card, and toolbar() twice, links above the grid and reorder beside the
 * heading. module-cards.js does the rest, and expects a card's first child to
 * be its head (the title and the switch), which is what stays visible when it
 * is collapsed.
 */
class SB_Tweaks_Cards {

	const META = 'socialbump_cards';

	const ACTION = 'sb_tweaks_cards';

	/** key => menu slug, so a saved order can drop that menu's ASE entry. */
	private static $registered = [];

	private static $hooked = false;

	/**
	 * Register one page of cards. Safe to call more than once.
	 *
	 * $menu_slug is the top level menu the page lives under. When an order is
	 * saved, any submenu order Admin and Site Enhancements holds for that menu
	 * is dropped, so the order just saved is the one that shows.
	 */
	public static function register( $key, $menu_slug = '' ) {
		$key = sanitize_key( $key );

		if ( $key === '' || isset( self::$registered[ $key ] ) ) {
			return;
		}

		self::$registered[ $key ] = (string) $menu_slug;

		if ( ! self::$hooked ) {
			self::$hooked = true;

			add_action( 'wp_ajax_' . self::ACTION, [ __CLASS__, 'ajax' ] );
		}
	}

	/** The current user's saved order and collapsed cards for one page. */
	public static function state( $key ) {
		$all   = (array) get_user_meta( get_current_user_id(), self::META, true );
		$state = isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : [];

		return [
			'order'     => array_values( array_map( 'strval', (array) ( $state['order'] ?? [] ) ) ),
			'collapsed' => array_values( array_map( 'strval', (array) ( $state['collapsed'] ?? [] ) ) ),
		];
	}

	/**
	 * Card ids in the order to show them: the saved order first, then anything
	 * new by name. Takes id => title, returns the ids.
	 */
	public static function sort( array $cards, $key ) {
		$order = array_flip( self::state( $key )['order'] );

		uksort(
			$cards,
			function ( $a, $b ) use ( $cards, $order ) {
				$pa = isset( $order[ $a ] ) ? $order[ $a ] : PHP_INT_MAX;
				$pb = isset( $order[ $b ] ) ? $order[ $b ] : PHP_INT_MAX;

				if ( $pa !== $pb ) {
					return $pa <=> $pb;
				}

				return strcasecmp( (string) $cards[ $a ], (string) $cards[ $b ] );
			}
		);

		return array_keys( $cards );
	}

	/** Attributes for the element that holds the cards. */
	public static function container_attributes( $key ) {
		$q     = chr( 34 );
		$state = self::state( $key );

		return ' data-sb-cards=' . $q . esc_attr( $key ) . $q
			. ' data-sb-cards-action=' . $q . esc_attr( self::ACTION ) . $q
			. ' data-sb-cards-nonce=' . $q . esc_attr( wp_create_nonce( 'sb_cards_' . $key ) ) . $q
			. ' data-sb-cards-collapsed=' . $q . esc_attr( implode( ',', $state['collapsed'] ) ) . $q;
	}

	/** The attribute that marks one card. */
	public static function card_attribute( $id ) {
		$q = chr( 34 );

		return ' data-sb-card=' . $q . esc_attr( $id ) . $q;
	}

	/**
	 * The links for above the grid (Collapse all, Expand all, Collapse
	 * disabled) or the Reorder Cards button for beside the heading. Both carry
	 * the key so the script finds them.
	 */
	public static function toolbar( $key, $part = 'links' ) {
		$q = chr( 34 );

		if ( $part === 'reorder' ) {
			return '<div class=' . $q . 'sb-cards__tools sb-cards__tools--reorder' . $q . ' data-sb-cards-tools=' . $q . esc_attr( $key ) . $q . '>'
				. '<button type=' . $q . 'button' . $q . ' class=' . $q . 'button' . $q . ' data-sb-cards-reorder>' . esc_html__( 'Reorder Cards', 'sb-tweaks' ) . '</button>'
				. '</div>';
		}

		return '<div class=' . $q . 'sb-cards__tools sb-cards__tools--links' . $q . ' data-sb-cards-tools=' . $q . esc_attr( $key ) . $q . '>'
			. '<button type=' . $q . 'button' . $q . ' class=' . $q . 'button-link sb-toggle' . $q . ' data-sb-cards-collapse>' . esc_html__( 'Collapse all', 'sb-tweaks' ) . '</button>'
			. '<span aria-hidden=' . $q . 'true' . $q . '>|</span>'
			. '<button type=' . $q . 'button' . $q . ' class=' . $q . 'button-link sb-toggle' . $q . ' data-sb-cards-expand>' . esc_html__( 'Expand all', 'sb-tweaks' ) . '</button>'
			. '<span aria-hidden=' . $q . 'true' . $q . '>|</span>'
			. '<button type=' . $q . 'button' . $q . ' class=' . $q . 'button-link sb-toggle' . $q . ' data-sb-cards-collapse-off>' . esc_html__( 'Collapse disabled', 'sb-tweaks' ) . '</button>'
			. '</div>';
	}

	/**
	 * Admin and Site Enhancements can hold its own order for a menu's submenu,
	 * which would sit on top of the one just saved. Dropping the entry for
	 * this menu lets ASE fall back to the order the plugin registers.
	 */
	public static function forget_ase_submenu( $menu_slug ) {
		$menu_slug = (string) $menu_slug;

		if ( $menu_slug === '' ) {
			return;
		}

		$extra = get_option( 'admin_site_enhancements_extra' );

		if ( ! is_array( $extra ) || empty( $extra['admin_menu']['custom_submenus_order'] ) ) {
			return;
		}

		$orders = json_decode( (string) $extra['admin_menu']['custom_submenus_order'], true );

		if ( ! is_array( $orders ) || ! array_key_exists( $menu_slug, $orders ) ) {
			return;
		}

		unset( $orders[ $menu_slug ] );

		$extra['admin_menu']['custom_submenus_order'] = wp_json_encode( $orders );

		update_option( 'admin_site_enhancements_extra', $extra );
	}

	/** Save the arrangement the page sends. */
	public static function ajax() {
		$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';

		if ( $key === '' || ! is_user_logged_in() || ! check_ajax_referer( 'sb_cards_' . $key, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Not allowed.' ], 403 );
		}

		$order     = isset( $_POST['order'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['order'] ) ) : [];
		$collapsed = isset( $_POST['collapsed'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['collapsed'] ) ) : [];

		$all         = (array) get_user_meta( get_current_user_id(), self::META, true );
		$all[ $key ] = [
			'order'     => array_values( array_unique( array_filter( $order, 'strlen' ) ) ),
			'collapsed' => array_values( array_unique( array_filter( $collapsed, 'strlen' ) ) ),
		];

		update_user_meta( get_current_user_id(), self::META, $all );

		if ( ! empty( self::$registered[ $key ] ) ) {
			self::forget_ase_submenu( self::$registered[ $key ] );
		}

		wp_send_json_success( $all[ $key ] );
	}
}
