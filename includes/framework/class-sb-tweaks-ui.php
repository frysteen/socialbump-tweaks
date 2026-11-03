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
 * grid of cards), save_button() (Save changes in the bar) and dropdown() (a
 * button with a menu under it, such as Actions). Cards sit straight on the
 * page in .sb-tweaks-grid, not inside a panel.
 *
 * SBTweaksSync (assets/js/sync.js, registered as sb-tweaks-sync) keeps open
 * tabs in step: one tab says what it changed and the others refresh the parts
 * it touches. See register_assets().
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

		// Every SocialBUMP menu item looks the same, on every admin page.
		add_action( 'admin_head', [ __CLASS__, 'menu_styles' ] );

		// Shared scripts, registered early so any page can list them.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'register_assets' ], 1 );
	}

	/**
	 * Scripts any page can list as a dependency, registered early:
	 * sb-tweaks-sync (assets/js/sync.js, SBTweaksSync) keeps open tabs in
	 * step, one tab saying what it changed and the others refreshing the
	 * parts it touches (Sam, 3 November 2026).
	 */
	public static function register_assets() {
		$file = 'assets/js/sync.js';

		if ( file_exists( SB_TWEAKS_PATH . $file ) ) {
			wp_register_script( 'sb-tweaks-sync', SB_TWEAKS_URL . $file, [], SB_TWEAKS_VERSION . '.' . filemtime( SB_TWEAKS_PATH . $file ), true );
		}
	}

	/** A thin upright line between groups of buttons in a bar (ui.css; Sam, 3 November 2026). */
	public static function divider() {
		return '<span class="sb-ui-bar__divider" aria-hidden="true"></span>';
	}

	/**
	 * A dropdown button and its menu (Sam, 3 November 2026), in the look of
	 * the Installs page's Actions: the label, then the arrow in a strip of its
	 * own, and a menu under it that ui.js opens, closes and steps through with
	 * the keys. The items do their own work (a link, a click handler, a form,
	 * confirm.js popups); picking one closes the menu.
	 *
	 * $a: label, items (html: dropdown_item(), dropdown_head(), dropdown_line(),
	 * or a form of class sb-ui-dropdown__form holding an item), primary (green,
	 * for a page's main action; white by default), small (button-small),
	 * align ('right', the menu's right edge under the button's, or 'left'),
	 * class (on the wrapper) and aria (the button's label for screen readers,
	 * when the label alone isn't enough).
	 */
	public static function dropdown( array $a ) {
		$label = isset( $a['label'] ) ? (string) $a['label'] : __( 'Actions', 'sb-tweaks' );
		$class = 'sb-ui-dropdown' . ( ! empty( $a['primary'] ) ? ' sb-ui-dropdown--primary' : '' ) . ( ( $a['align'] ?? 'right' ) === 'left' ? ' sb-ui-dropdown--left' : '' ) . ( ! empty( $a['class'] ) ? ' ' . $a['class'] : '' );
		$btn   = 'button sb-ui-dropdown__button' . ( ! empty( $a['primary'] ) ? ' button-primary' : '' ) . ( ! empty( $a['small'] ) ? ' button-small' : '' );
		$aria  = ! empty( $a['aria'] ) ? ' aria-label="' . esc_attr( $a['aria'] ) . '"' : '';
		$arrow = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';

		return '<span class="' . esc_attr( $class ) . '"><button type="button" class="' . esc_attr( $btn ) . '" aria-haspopup="menu" aria-expanded="false"' . $aria . '><span class="sb-ui-dropdown__label">' . esc_html( $label ) . '</span><span class="sb-ui-dropdown__arrow">' . $arrow . '</span></button>'
			. '<span class="sb-ui-dropdown__menu" role="menu" hidden>' . ( $a['items'] ?? '' ) . '</span></span>';
	}

	/**
	 * One item in a dropdown's menu: an icon (svg html, or '') and its words.
	 * $attrs are its attributes, escaped here: href makes it a link, type
	 * submit makes it its form's button (put it in a form of class
	 * sb-ui-dropdown__form), danger => true makes it red (Delete, after a
	 * dropdown_line()), class adds classes. Any other key is printed as it is
	 * named (data-*, aria-label and the like).
	 */
	public static function dropdown_item( $label, $icon = '', array $attrs = [] ) {
		$href   = $attrs['href'] ?? '';
		$class  = 'sb-ui-dropdown__item' . ( ! empty( $attrs['danger'] ) ? ' is-danger' : '' ) . ( ! empty( $attrs['class'] ) ? ' ' . $attrs['class'] : '' );
		$tag    = $href !== '' ? 'a' : 'button';
		$out    = '<' . $tag . ' role="menuitem" class="' . esc_attr( $class ) . '"' . ( $tag === 'a' ? ' href="' . esc_url( $href ) . '"' : ' type="' . esc_attr( $attrs['type'] ?? 'button' ) . '"' );

		foreach ( $attrs as $name => $value ) {
			if ( in_array( $name, [ 'href', 'type', 'class', 'danger' ], true ) || $value === null || $value === false ) {
				continue;
			}

			$out .= ' ' . preg_replace( '/[^a-z0-9_:-]/i', '', (string) $name ) . ( $value === true ? '' : '="' . esc_attr( (string) $value ) . '"' );
		}

		return $out . '>' . $icon . '<span>' . esc_html( $label ) . '</span></' . $tag . '>';
	}

	/** A small heading in a dropdown's menu, over the items it names. */
	public static function dropdown_head( $text ) {
		return '<span class="sb-ui-dropdown__head">' . esc_html( $text ) . '</span>';
	}

	/** A line between groups in a dropdown's menu (before Delete, say). */
	public static function dropdown_line() {
		return '<span class="sb-ui-dropdown__line" role="separator"></span>';
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
	 * The dark banner every SocialBUMP page opens with (Sam, 3 November 2026):
	 * one component for SocialBUMP Tweaks' own pages (SB_Tweaks_Settings)
	 * and every module's (SB_Tweaks_Screen). Its styles are ui.css, "Banner".
	 *
	 * Along the top, a breadcrumb: the logo goes to SocialBUMP Tweaks'
	 * Modules page, the name ($a['name'], a link to $a['name_url'], the
	 * module's first page) and, after a ›, the page you're on ($a['page'],
	 * left out when it's the same as the name). Then the version badge
	 * ($a['version']: [ text, url ], SocialBUMP Tweaks' own pages) and the
	 * Auto, Light and Dark switch; $a['intro'] under it; and $a['nav'] along
	 * the bottom, a list of [ url, title, current, badge ] (badge: a number),
	 * shown with two or more. The hr after it tells WordPress to put admin
	 * notices below the banner, not inside it.
	 */
	public static function header( array $a ) {
		$a = wp_parse_args(
			$a,
			[
				'name'     => '',
				'name_url' => '',
				'page'     => '',
				'intro'    => '',
				'version'  => null,
				'nav'      => [],
			]
		);

		$modules = admin_url( 'admin.php?page=' . ( class_exists( 'SB_Tweaks_Settings' ) ? SB_Tweaks_Settings::PAGE_SLUG : 'sb-tweaks' ) );

		$out  = '<div class="sb-tweaks-header"><div class="sb-tweaks-header__brand">';
		$out .= '<a class="sb-tweaks-header__home" href="' . esc_url( $modules ) . '" aria-label="' . esc_attr__( 'SocialBUMP Tweaks modules', 'sb-tweaks' ) . '">';
		$out .= '<img class="sb-tweaks-header__logo" src="' . esc_url( SB_TWEAKS_URL . 'assets/img/socialbump-logo-light.svg' ) . '" alt="SocialBUMP" width="203" height="28"></a>';

		$out .= '<h1 class="sb-tweaks-header__title">';
		$out .= $a['name_url'] !== ''
			? '<a class="sb-tweaks-header__module" href="' . esc_url( $a['name_url'] ) . '">' . esc_html( $a['name'] ) . '</a>'
			: esc_html( $a['name'] );

		if ( $a['page'] !== '' && $a['page'] !== $a['name'] ) {
			$out .= '<span class="sb-tweaks-header__sep" aria-hidden="true">›</span><span class="sb-tweaks-header__page">' . esc_html( $a['page'] ) . '</span>';
		}

		$out .= '</h1>';

		if ( is_array( $a['version'] ) && ! empty( $a['version'][0] ) ) {
			$out .= '<a class="sb-tweaks-header__version" href="' . esc_url( $a['version'][1] ?? '' ) . '">' . esc_html( $a['version'][0] ) . '</a>';
		}

		$out .= self::mode_switch() . '</div>';

		if ( $a['intro'] !== '' ) {
			$out .= '<p class="sb-tweaks-header__intro">' . esc_html( $a['intro'] ) . '</p>';
		}

		if ( count( $a['nav'] ) > 1 ) {
			$out .= '<nav class="sb-tweaks-header__nav" aria-label="' . esc_attr( $a['name'] ) . '">';

			foreach ( $a['nav'] as $item ) {
				$item  = array_pad( array_values( (array) $item ), 4, '' );
				$badge = (int) $item[3] > 0 ? '<span class="sb-tweaks-header__badge">' . esc_html( number_format_i18n( (int) $item[3] ) ) . '</span>' : '';

				$out .= '<a class="sb-tweaks-header__link' . ( $item[2] ? ' is-current' : '' ) . '" href="' . esc_url( $item[0] ) . '"' . ( $item[2] ? ' aria-current="page"' : '' ) . '>' . esc_html( $item[1] ) . $badge . '</a>';
			}

			$out .= '</nav>';
		}

		return $out . '</div><hr class="wp-header-end">';
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

	/**
	 * The SocialBUMP exclamation's outline, shared by every SocialBUMP plugin
	 * that puts the mark in the admin menu. Finding it in a menu icon is how
	 * menu_styles() knows the item is ours.
	 */
	const MARK = 'M10.94,30.2c1.24,1.24,1.86,2.75,1.86,4.54s-.62,3.3-1.86,4.54-2.75,1.86-4.54,1.86-3.3-.62-4.54-1.86-1.86-2.75-1.86-4.54.62-3.3,1.86-4.54,2.75-1.86,4.54-1.86,3.3.62,4.54,1.86ZM1.22,24.27L.13,1.4C.09.64.7,0,1.46,0h9.88c.76,0,1.37.64,1.34,1.4l-1.09,22.87c-.03.71-.62,1.27-1.34,1.27H2.56c-.71,0-1.3-.56-1.34-1.27Z';

	/**
	 * The menu tile (Sam, 3 November 2026): the mark in SocialBUMP green on
	 * the banner's dark navy (--sbui-navy-900), with the banner's faint edge.
	 */
	public static function menu_tile() {
		$q = chr( 34 );

		$svg  = '<svg xmlns=' . $q . 'http://www.w3.org/2000/svg' . $q . ' viewBox=' . $q . '0 0 24 24' . $q . '>';
		$svg .= '<rect x=' . $q . '0.5' . $q . ' y=' . $q . '0.5' . $q . ' width=' . $q . '23' . $q . ' height=' . $q . '23' . $q . ' rx=' . $q . '3.3' . $q . ' fill=' . $q . '#16171d' . $q . ' stroke=' . $q . '#ffffff' . $q . ' stroke-opacity=' . $q . '0.12' . $q . '/>';
		$svg .= '<g transform=' . $q . 'translate(9.67 4.5) scale(0.3646)' . $q . ' fill=' . $q . '#85df07' . $q . '><path d=' . $q . self::MARK . $q . '/></g></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Top level menu items carrying the mark: SocialBUMP Tweaks, each module
	 * with a menu of its own, and other SocialBUMP plugins such as SEO for AI,
	 * found by the mark in their icon. Others can be added by menu slug
	 * through the sb_tweaks_menu_marks filter.
	 */
	private static function marked_menu_ids() {
		global $menu;

		$extra = array_map( 'strval', (array) apply_filters( 'sb_tweaks_menu_marks', [] ) );
		$ids   = [];

		foreach ( (array) $menu as $item ) {
			if ( empty( $item[2] ) || empty( $item[5] ) ) {
				continue;
			}

			$icon = isset( $item[6] ) ? (string) $item[6] : '';
			$ours = in_array( (string) $item[2], $extra, true );

			if ( ! $ours && strpos( $icon, 'data:image/svg+xml;base64,' ) === 0 ) {
				$svg  = (string) base64_decode( substr( $icon, 26 ), true );
				$ours = strpos( $svg, substr( self::MARK, 0, 40 ) ) !== false;
			}

			if ( $ours ) {
				$ids[] = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $item[5] );
			}
		}

		return array_filter( $ids );
	}

	/**
	 * The tile in place of each marked item's icon, at full strength in every
	 * state, whatever admin colour scheme is picked. WordPress repaints SVG
	 * menu icons in the scheme's colours, every fill the same, which would
	 * flatten the tile to a single colour block. So the icon's own background
	 * is switched off (with nothing to repaint, the painter leaves it alone)
	 * and the tile is drawn in ::before.
	 */
	public static function menu_styles() {
		$ids = self::marked_menu_ids();

		if ( ! $ids ) {
			return;
		}

		$box = implode( ',', array_map( function ( $id ) { return '#adminmenu #' . $id . ' div.wp-menu-image'; }, $ids ) );
		$pre = implode( ',', array_map( function ( $id ) { return '#adminmenu #' . $id . ' div.wp-menu-image::before'; }, $ids ) );

		echo '<style id="sb-tweaks-menu-marks">';
		echo $box . '{background-image:none!important;position:relative;opacity:1!important;}'; // phpcs:ignore WordPress.Security.EscapeOutput -- ids are stripped to [A-Za-z0-9_-].
		echo $pre . '{content:"";position:absolute;top:50%;left:50%;width:22px;height:22px;padding:0!important;margin:-11px 0 0 -11px;background:url("' . esc_attr( self::menu_tile() ) . '") center/contain no-repeat;opacity:1!important;}'; // phpcs:ignore WordPress.Security.EscapeOutput -- ids are stripped; the tile is a base64 data URI.
		echo '</style>';
	}
}
