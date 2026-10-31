<?php
/**
 * SocialBUMP UI: the look every SocialBUMP plugin page shares.
 *
 * Its own colours, the Outfit font and light and dark modes, so the pages look
 * the same whatever admin colour scheme someone has picked. ui.css carries the
 * look and ui.js the behaviour: the Auto, Light and Dark switch in the banner,
 * toasts in place of notices across the top, and the on, off and edited states
 * of a card.
 *
 * Page parts for building a page in the look: bar() (the links and actions
 * across the top, above the cards), heading() (a heading with a count over a
 * grid of cards) and save_button() (Save changes in the bar). Cards sit
 * straight on the page in .sb-tweaks-grid, not inside a panel.
 *
 * Each user's mode is kept in their user meta, so it follows them from
 * computer to computer. Auto follows the computer's own setting.
 *
 * @package SocialBUMP_Tweaks
 */

defined( 'ABSPATH' ) || exit;

class SB_Tweaks_UI {

	const META   = 'sb_tweaks_ui_theme';
	const MODES  = [ 'auto', 'light', 'dark' ];
	const ACTION = 'sb_tweaks_ui_theme';

	/** True once a SocialBUMP page has asked for the look on this request. */
	private static $on = false;

	/** True when the page is drawn only from parts that have a dark look. */
	private static $dark = false;

	public static function boot() {
		add_filter( 'admin_body_class', [ __CLASS__, 'body_class' ] );
		add_action( 'wp_ajax_' . self::ACTION, [ __CLASS__, 'save_mode' ] );
	}

	/** This user's mode: auto, light or dark. */
	public static function mode() {
		$mode = get_user_meta( get_current_user_id(), self::META, true );

		return in_array( $mode, self::MODES, true ) ? $mode : 'auto';
	}

	/**
	 * Called by a page's own enqueue, after admin.css.
	 *
	 * A page a module still draws in its own older styles passes false and
	 * stays light whatever the mode, until it is moved across: dark text on a
	 * box that is still white would be unreadable.
	 */
	public static function enqueue( $dark_ready = false ) {
		$css = SB_TWEAKS_PATH . 'assets/css/ui.css';
		$js  = SB_TWEAKS_PATH . 'assets/js/ui.js';

		if ( ! file_exists( $css ) ) {
			return;
		}

		wp_enqueue_style( 'sb-tweaks-ui', SB_TWEAKS_URL . 'assets/css/ui.css', wp_style_is( 'sb-tweaks-admin', 'registered' ) ? [ 'sb-tweaks-admin' ] : [], SB_TWEAKS_VERSION . '.' . filemtime( $css ) );

		if ( file_exists( $js ) && ! wp_script_is( 'sb-tweaks-ui', 'enqueued' ) ) {
			wp_enqueue_script( 'sb-tweaks-ui', SB_TWEAKS_URL . 'assets/js/ui.js', [], SB_TWEAKS_VERSION . '.' . filemtime( $js ), true );
			wp_localize_script(
				'sb-tweaks-ui',
				'sbTweaksUI',
				[
					'ajax'   => admin_url( 'admin-ajax.php' ),
					'action' => self::ACTION,
					'nonce'  => wp_create_nonce( self::ACTION ),
					'mode'   => self::mode(),
				]
			);
		}

		self::$on   = true;
		self::$dark = self::$dark || (bool) $dark_ready;
	}

	public static function body_class( $classes ) {
		if ( ! self::$on ) {
			return $classes;
		}

		$mode = self::$dark ? self::mode() : 'light';

		return $classes . ' sb-ui sb-ui-mode-' . $mode . ( self::$dark ? '' : ' sb-ui-light-only' );
	}

	/** The switch in the banner. ui.js does the rest. */
	public static function mode_switch() {
		$mode   = self::mode();
		$labels = [
			'auto'  => [ __( 'Auto', 'sb-tweaks' ), __( 'Matches your computer', 'sb-tweaks' ), '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>' ],
			'light' => [ __( 'Light', 'sb-tweaks' ), __( 'Always light', 'sb-tweaks' ), '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>' ],
			'dark'  => [ __( 'Dark', 'sb-tweaks' ), __( 'Always dark', 'sb-tweaks' ), '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>' ],
		];

		$out = '<div class="sb-ui-mode" role="group" aria-label="' . esc_attr__( 'Colour mode', 'sb-tweaks' ) . '">';

		foreach ( $labels as $key => $label ) {
			$out .= '<button type="button" data-sb-ui-mode="' . esc_attr( $key ) . '" aria-pressed="' . ( $key === $mode ? 'true' : 'false' ) . '" data-sb-tip="' . esc_attr( $label[1] ) . '">';
			$out .= '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $label[2] . '</svg>';
			$out .= '<span>' . esc_html( $label[0] ) . '</span></button>';
		}

		return $out . '</div>';
	}

	/**
	 * The bar across the top of a page, above its cards (.sb-ui-bar): groups
	 * of links and actions, each with an optional small label, then $end on
	 * the right (the page's main button). $groups is a list of
	 * [ label, html ]; html is printed as it is, so escape it first.
	 */
	public static function bar( array $groups, $end = '', $class = '' ) {
		$out = '<div class="sb-ui-bar' . ( $class !== '' ? ' ' . esc_attr( $class ) : '' ) . '">';

		foreach ( $groups as $group ) {
			$label = isset( $group[0] ) ? (string) $group[0] : '';
			$out  .= '<div class="sb-ui-bar__group">' . ( $label !== '' ? '<span class="sb-ui-bar__label">' . esc_html( $label ) . '</span>' : '' ) . ( isset( $group[1] ) ? $group[1] : '' ) . '</div>';
		}

		if ( $end !== '' ) {
			$out .= '<div class="sb-ui-bar__end">' . $end . '</div>';
		}

		return $out . '</div>';
	}

	/** A heading over a grid of cards, with a count and a note beside it (.sb-ui-heading). */
	public static function heading( $title, $count = null, $meta = '' ) {
		$out = '<div class="sb-ui-heading"><h2>' . esc_html( $title ) . '</h2>';

		if ( $count !== null ) {
			$out .= '<span class="sb-ui-count">' . (int) $count . '</span>';
		}

		if ( $meta !== '' ) {
			$out .= '<span class="sb-ui-heading__meta">' . esc_html( $meta ) . '</span>';
		}

		return $out . '</div>';
	}

	/**
	 * Save changes for a form, to sit in the bar: the unsaved changes script
	 * finds it by its form attribute and keeps it in step with the form's own
	 * Save (Nothing to save until something changes). Give the form an id.
	 */
	public static function save_button( $form, $label = '' ) {
		$label = $label !== '' ? $label : __( 'Save changes', 'sb-tweaks' );

		return '<button type="submit" form="' . esc_attr( $form ) . '" class="button button-primary sb-save--clean"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg><span data-sb-label>' . esc_html( $label ) . '</span></button>';
	}

	public static function save_mode() {
		check_ajax_referer( self::ACTION, 'nonce' );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';

		if ( ! is_user_logged_in() || ! in_array( $mode, self::MODES, true ) ) {
			wp_send_json_error( null, 400 );
		}

		update_user_meta( get_current_user_id(), self::META, $mode );
		wp_send_json_success( [ 'mode' => $mode ] );
	}
}
