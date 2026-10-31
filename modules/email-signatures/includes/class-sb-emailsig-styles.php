<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template styles: each template's styles.php says what can be set and the
 * defaults; the site's choices are kept on each template's record
 * (SB_EmailSig_Templates::stored_styles(), and save()). Also the Edit styles
 * page, its live preview and its save.
 *
 * A colour is a hex value or a theme colour, var(--name). Emails cannot use
 * CSS variables, so a theme colour is turned into its hex whenever the
 * signature is drawn (see palette()), and follows the theme from then on.
 */
class SB_EmailSig_Styles {

	/** palette() and colours(), worked out once a request. */
	private static $palette = null;
	private static $colours = [];

	/** Before template records (29 October 2026): [ folder => styles ]. Kept for going back. */
	const OPTION = 'sb_tweaks_email_signatures_styles';

	/** User meta: the person this user last picked in the Edit styles preview. */
	const PERSON_META = 'sb_emailsig_preview_person';

	/**
	 * Styles kept on the Layout page for a design with a layout (Layout
	 * Settings, Sam, 29 October 2026): Edit styles no longer shows them, so
	 * its saves keep them as saved (with_layout()).
	 */
	const LAYOUT_PAGE = [ 'layout', 'primary', 'text', 'lines', 'font' ];

	/** Fonts every mail client has. Key => CSS font stack (single quotes only, it sits in a style attribute). */
	public static function fonts() {
		return [
			'arial'     => [ 'Arial', 'Arial, Helvetica, sans-serif' ],
			'helvetica' => [ 'Helvetica', 'Helvetica, Arial, sans-serif' ],
			'verdana'   => [ 'Verdana', 'Verdana, Geneva, sans-serif' ],
			'tahoma'    => [ 'Tahoma', 'Tahoma, Geneva, sans-serif' ],
			'trebuchet' => [ 'Trebuchet MS', "'Trebuchet MS', Helvetica, sans-serif" ],
			'georgia'   => [ 'Georgia', 'Georgia, serif' ],
			'times'     => [ 'Times New Roman', "'Times New Roman', Times, serif" ],
		];
	}

	public static function weights() {
		return [
			'400' => 'Normal',
			'600' => 'Semi bold',
			'700' => 'Bold',
		];
	}

	/** What starts each Info List line (the Info List Styles card's Label style). */
	public static function icon_shows() {
		return [
			'icon'   => 'Icon',
			'letter' => 'First letter',
			'label'  => 'Full label',
			'none'   => 'None',
		];
	}

	/**
	 * Text case. Applied to the text itself when the signature is drawn, not
	 * with CSS, so it holds in every mail client and in the plain text copy.
	 */
	public static function cases() {
		return [
			'none'       => 'As typed',
			'uppercase'  => 'UPPERCASE',
			'lowercase'  => 'lowercase',
			'capitalize' => 'Capitalise Words',
		];
	}

	public static function init() {
		add_action( 'admin_post_sb_emailsig_save_styles', [ __CLASS__, 'save' ] );
		add_action( 'admin_post_sb_emailsig_save_layout', [ __CLASS__, 'save_layout' ] );
		add_action( 'wp_ajax_sb_emailsig_preview', [ __CLASS__, 'preview' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
	}

	/* ------------------------------------------------------------------ *
	 * Colours
	 * ------------------------------------------------------------------ */

	/** True for '#abc', '#aabbcc' or 'var(--name)'. */
	public static function is_colour( $v ) {
		return (bool) preg_match( '/^(#[0-9a-f]{3}|#[0-9a-f]{6}|var\(--[a-z0-9_-]+\))$/i', (string) $v );
	}

	/** A posted colour cleaned: '', '#aabbcc' or 'var(--name)'. Anything else is ''. */
	public static function clean_colour( $v ) {
		$v = strtolower( preg_replace( '/\s+/', '', (string) $v ) );

		if ( preg_match( '/^--[a-z0-9_-]+$/', $v ) ) {
			$v = 'var(' . $v . ')';
		}

		return self::is_colour( $v ) ? $v : '';
	}

	/**
	 * The site's theme colours: [ [ name, value, hex ], ... ], value being
	 * what is saved (var(--name), or the hex for a colour with no variable).
	 *
	 * Read from Bricks' colour palettes (Core Framework fills these too), then
	 * the theme.json palette for colours it gives a real value. Filterable
	 * through sb_emailsig/palette. The generated shades (var(--primary-d-4)
	 * and so on) are left out of this list, to keep the menu short, but can
	 * still be typed in: see colours().
	 */
	public static function palette() {
		if ( self::$palette !== null ) {
			return self::$palette;
		}

		$out  = [];
		$all  = [];
		$add  = function ( $name, $value, $raw, $menu = true ) use ( &$out, &$all ) {
			$hex = self::to_hex( $raw );

			if ( $hex === '' || isset( $all[ $value ] ) ) {
				return;
			}

			$all[ $value ] = $hex;

			if ( $menu ) {
				$out[] = [
					'name'  => (string) $name,
					'value' => $value,
					'hex'   => $hex,
				];
			}
		};

		foreach ( (array) get_option( 'bricks_color_palette', [] ) as $pal ) {
			foreach ( (array) ( $pal['colors'] ?? [] ) as $c ) {
				if ( ! is_array( $c ) ) {
					continue;
				}

				$raw   = (string) ( $c['rawValue']['light'] ?? ( $c['light'] ?? ( $c['hex'] ?? ( $c['rgb'] ?? ( $c['hsl'] ?? '' ) ) ) ) );
				$var   = preg_match( '/^var\(\s*(--[a-z0-9_-]+)\s*\)$/i', (string) ( $c['raw'] ?? '' ), $m ) ? 'var(' . strtolower( $m[1] ) . ')' : '';
				$value = $var !== '' ? $var : self::to_hex( $raw );
				$name  = ! empty( $c['name'] ) ? $c['name'] : ( $var !== '' ? substr( $var, 6, -1 ) : $value );

				$add( $name, $value, $raw, empty( $c['isShade'] ) );
			}
		}

		if ( function_exists( 'wp_get_global_settings' ) ) {
			$theme = (array) wp_get_global_settings( [ 'color', 'palette', 'theme' ] );

			foreach ( $theme as $c ) {
				if ( empty( $c['slug'] ) || empty( $c['color'] ) ) {
					continue;
				}

				$add( $c['name'] ?? $c['slug'], 'var(--wp--preset--color--' . sanitize_key( $c['slug'] ) . ')', (string) $c['color'] );
			}
		}

		$out = array_slice( (array) apply_filters( 'sb_emailsig/palette', $out ), 0, 80 );

		foreach ( $out as $c ) {
			if ( isset( $c['value'], $c['hex'] ) && ! isset( $all[ $c['value'] ] ) ) {
				$all[ $c['value'] ] = $c['hex'];
			}
		}

		self::$colours = $all;
		self::$palette = $out;

		return $out;
	}

	/** Every theme colour, shades too, as [ 'var(--name)' => '#rrggbb' ]. */
	public static function colours() {
		self::palette();

		return self::$colours;
	}

	/** A saved colour as hex: brand and theme colours looked up, '' when it can't be. */
	public static function hex( $v ) {
		$v = (string) $v;

		if ( strpos( $v, 'var(' ) === 0 ) {
			if ( strpos( $v, 'var(--sb-brand-' ) === 0 ) {
				foreach ( self::brand() as $b ) {
					if ( $b['value'] === $v ) {
						return $b['hex'];
					}
				}

				return '';
			}

			return self::colours()[ $v ] ?? '';
		}

		return self::to_hex( $v );
	}

	/* ------------------------------------------------------------------ *
	 * Brand colours (Settings, Branding tab)
	 * ------------------------------------------------------------------ */

	/**
	 * The brand colours: key => [ label, fallback ]. Accent's fallback ''
	 * means the brand primary. Saved as options sb_emailsig_brand_<key>
	 * (a hex or a theme colour, never blank once saved: see
	 * SB_EmailSig_Fields).
	 */
	const BRAND = [
		'primary'    => [ 'Brand Primary', '#000000' ],
		'accent'     => [ 'Brand Accent', '' ],
		'text'       => [ 'Brand Text', '#333333' ],
		'text_light' => [ 'Brand Text Light', '#777777' ],
	];

	/** A brand colour's token as templates save it: var(--sb-brand-text-light). */
	public static function brand_token( $key ) {
		return 'var(--sb-brand-' . str_replace( '_', '-', $key ) . ')';
	}

	/**
	 * The brand colours now: key => [ name, value (its token), hex ]. Read
	 * each time (ACF caches the options), as the Settings page changes them
	 * mid request. The company being drawn's (SB_EmailSig_Company).
	 */
	public static function brand() {
		$out = [];

		foreach ( self::BRAND as $key => $b ) {
			$raw = function_exists( 'get_field' ) ? self::clean_colour( (string) get_field( 'sb_emailsig_brand_' . $key, class_exists( 'SB_EmailSig_Company' ) ? SB_EmailSig_Company::source() : 'option' ) ) : '';
			$hex = ( $raw !== '' && strpos( $raw, 'var(--sb-brand-' ) !== 0 ) ? self::theme_hex( $raw ) : '';

			if ( $hex === '' ) {
				$hex = $b[1] !== '' ? $b[1] : ( $out['primary']['hex'] ?? '#000000' );
			}

			$out[ $key ] = [
				'name'  => $b[0],
				'value' => self::brand_token( $key ),
				'hex'   => $hex,
			];
		}

		return $out;
	}

	/** A colour as hex without the brand colours (for the brand colours themselves). */
	public static function theme_hex( $v ) {
		$v = (string) $v;

		return strpos( $v, 'var(' ) === 0 ? ( self::colours()[ $v ] ?? '' ) : self::to_hex( $v );
	}

	/** The Edit styles colour menu: the brand colours first, then the theme's. */
	public static function editor_palette() {
		$out = [];

		foreach ( self::brand() as $b ) {
			$out[] = $b + [ 'group' => 'brand' ];
		}

		return array_merge( $out, self::palette() );
	}

	/**
	 * Any CSS colour Bricks or theme.json might hold (#rgb, #rrggbb, #rrggbbaa,
	 * rgb(), rgba(), hsl(), hsla()) as #rrggbb, see-through ones laid over
	 * white. '' for anything else.
	 */
	public static function to_hex( $v ) {
		$v = strtolower( trim( (string) $v ) );

		if ( preg_match( '/^#([0-9a-f]{3})$/', $v, $m ) ) {
			return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}

		if ( preg_match( '/^#([0-9a-f]{6})([0-9a-f]{2})?$/', $v, $m ) ) {
			$alpha = isset( $m[2] ) && $m[2] !== '' ? hexdec( $m[2] ) / 255 : 1;

			return self::rgb_hex( hexdec( substr( $m[1], 0, 2 ) ), hexdec( substr( $m[1], 2, 2 ) ), hexdec( substr( $m[1], 4, 2 ) ), $alpha );
		}

		if ( ! preg_match( '/^(rgba?|hsla?)\((.*)\)$/', $v, $m ) ) {
			return '';
		}

		$parts = preg_split( '/[\s,\/]+/', trim( $m[2] ) );

		if ( count( $parts ) < 3 ) {
			return '';
		}

		$num   = function ( $p, $scale ) {
			return substr( $p, -1 ) === '%' ? (float) $p / 100 * $scale : (float) $p;
		};
		$alpha = isset( $parts[3] ) ? $num( $parts[3], 1 ) : 1;

		if ( $m[1][0] === 'r' ) {
			return self::rgb_hex( $num( $parts[0], 255 ), $num( $parts[1], 255 ), $num( $parts[2], 255 ), $alpha );
		}

		$h = fmod( fmod( (float) $parts[0], 360 ) + 360, 360 ) / 360;
		$s = max( 0, min( 1, (float) $parts[1] / 100 ) );
		$l = max( 0, min( 1, (float) $parts[2] / 100 ) );

		if ( $s <= 0 ) {
			return self::rgb_hex( $l * 255, $l * 255, $l * 255, $alpha );
		}

		$q   = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
		$p   = 2 * $l - $q;
		$hue = function ( $t ) use ( $p, $q ) {
			$t = $t < 0 ? $t + 1 : ( $t > 1 ? $t - 1 : $t );

			if ( $t < 1 / 6 ) {
				return $p + ( $q - $p ) * 6 * $t;
			}
			if ( $t < 1 / 2 ) {
				return $q;
			}
			if ( $t < 2 / 3 ) {
				return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
			}

			return $p;
		};

		return self::rgb_hex( $hue( $h + 1 / 3 ) * 255, $hue( $h ) * 255, $hue( $h - 1 / 3 ) * 255, $alpha );
	}

	private static function rgb_hex( $r, $g, $b, $alpha = 1 ) {
		$alpha = max( 0, min( 1, (float) $alpha ) );
		$out   = '#';

		foreach ( [ $r, $g, $b ] as $c ) {
			$c    = $c * $alpha + 255 * ( 1 - $alpha );
			$out .= str_pad( dechex( (int) max( 0, min( 255, round( $c ) ) ) ), 2, '0', STR_PAD_LEFT );
		}

		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Values
	 * ------------------------------------------------------------------ */

	/** A template's defaults, from its styles.php. */
	public static function defaults( array $tpl ) {
		$file = $tpl['path'] . 'styles.php';
		$def  = file_exists( $file ) ? include $file : [];
		$def  = is_array( $def ) ? $def : [];

		// Line height (Sam, 31 October 2026): a default for every text part,
		// which each can change. Designs from before it start at 1.4.
		if ( isset( $def['items'] ) && is_array( $def['items'] ) ) {
			$def['line_height'] = self::lh( $def['line_height'] ?? '', '1.4' );

			foreach ( $def['items'] as $item => $props ) {
				$def['items'][ $item ]['line'] = self::lh( $props['line'] ?? '', '' );
			}
		}

		// The design's layout in its exact shape, so it compares as saved.
		if ( isset( $def['layout'] ) ) {
			$def['layout'] = SB_EmailSig_Layout::sanitize( $def['layout'] );
		}

		return $def;
	}

	/**
	 * The layout for saved or posted values: theirs, cleaned, or the design's
	 * when they have none. Values from before the layout editor with the
	 * photo switched off (Photo card, Show) start with the photo in the
	 * holding area.
	 */
	private static function layout_for( array $defaults, array $values ) {
		if ( isset( $values['layout'] ) ) {
			return SB_EmailSig_Layout::sanitize( $values['layout'], $defaults['layout'] );
		}

		if ( isset( $values['photo'] ) && is_array( $values['photo'] ) && array_key_exists( 'show', $values['photo'] ) && empty( $values['photo']['show'] ) ) {
			return SB_EmailSig_Layout::hold( $defaults['layout'], 'photo' );
		}

		return $defaults['layout'];
	}

	/** The saved values for a template merged over its defaults. */
	public static function values( array $tpl ) {
		return self::merge( self::defaults( $tpl ), SB_EmailSig_Templates::stored_styles( $tpl ) );
	}

	/** Saved values over defaults, keeping only keys the template knows. */
	private static function merge( array $defaults, array $saved ) {
		$out = $defaults;

		foreach ( [ 'primary', 'text', 'font', 'lines', 'line_height' ] as $k ) {
			if ( array_key_exists( $k, $defaults ) && array_key_exists( $k, $saved ) ) {
				$out[ $k ] = $saved[ $k ];
			}
		}

		foreach ( (array) ( $defaults['items'] ?? [] ) as $item => $props ) {
			foreach ( [ 'size', 'weight', 'style', 'case', 'colour', 'line' ] as $k ) {
				if ( isset( $saved['items'][ $item ] ) && array_key_exists( $k, $saved['items'][ $item ] ) ) {
					$out['items'][ $item ][ $k ] = $saved['items'][ $item ][ $k ];
				}
			}
		}

		foreach ( [ 'icons', 'social', 'logo', 'buttons', 'signature', 'photo' ] as $group ) {
			foreach ( (array) ( $defaults[ $group ] ?? [] ) as $k => $v ) {
				if ( $k !== 'names' && isset( $saved[ $group ] ) && array_key_exists( $k, $saved[ $group ] ) ) {
					$out[ $group ][ $k ] = $saved[ $group ][ $k ];
				}
			}
		}

		if ( isset( $defaults['layout'] ) ) {
			$out['layout'] = self::layout_for( $defaults, $saved );

			if ( isset( $out['photo'] ) ) {
				$out['photo']['show'] = SB_EmailSig_Layout::shows( $out['layout'], 'photo' ) ? 1 : 0;
			}
		}

		// Buttons were sized by height (size, 22 to 50) before 28 October
		// 2026; now by their text: the same text size the old height gave.
		if ( isset( $defaults['buttons']['text'], $saved['buttons']['size'] ) && ! isset( $saved['buttons']['text'] ) ) {
			$out['buttons']['text'] = max( 10, min( 24, (int) round( (int) $saved['buttons']['size'] * 0.42 ) ) );
		}

		return $out;
	}

	/**
	 * Values with every colour fixed as the hex it shows now, for an exact
	 * copy on another site (export with Lock colours): theme and brand
	 * colours looked up, and the blanks that follow a brand colour filled
	 * in (primary, text, and items with a fallback such as text_light).
	 * Other blanks follow the primary or text colour, so they stay blank.
	 */
	public static function locked( array $tpl, array $values ) {
		$brand = self::brand();
		$walk  = function ( $v ) use ( &$walk ) {
			if ( is_array( $v ) ) {
				foreach ( $v as $k => $x ) {
					$v[ $k ] = $walk( $x );
				}

				return $v;
			}

			return ( is_string( $v ) && strpos( $v, 'var(' ) === 0 ) ? (string) self::hex( $v ) : $v;
		};
		$out   = $walk( $values );

		foreach ( [ 'primary', 'text' ] as $k ) {
			if ( array_key_exists( $k, $out ) && $out[ $k ] === '' ) {
				$out[ $k ] = $brand[ $k ]['hex'];
			}
		}

		$defaults = self::defaults( $tpl );

		foreach ( (array) ( $out['items'] ?? [] ) as $item => $p ) {
			$fb = (string) ( $defaults['items'][ $item ]['fallback'] ?? '' );

			if ( ( $p['colour'] ?? '' ) === '' && isset( $brand[ $fb ] ) ) {
				$out['items'][ $item ]['colour'] = $brand[ $fb ]['hex'];
			}
		}

		return $out;
	}

	/**
	 * A line height: a multiple of the font size, 1 to 3 to one place (the
	 * box steps by 0.1, and a finer value would stop the form saving), as
	 * text ('1.4'); $fallback when blank or not a number. Written into
	 * emails without a unit, so it grows with the text when the Gmail phone
	 * app enlarges small text (a px one didn't, and the footer text's lines
	 * crowded together: Sam, 31 October 2026).
	 */
	public static function lh( $v, $fallback = '' ) {
		$v = trim( str_replace( ',', '.', (string) $v ) );

		if ( $v === '' || ! is_numeric( $v ) || (float) $v <= 0 ) {
			return (string) $fallback;
		}

		$f = max( 1, min( 3, round( (float) $v, 1 ) ) );

		return rtrim( rtrim( number_format( $f, 1, '.', '' ), '0' ), '.' );
	}

	/** A size that can be left blank: 0 for blank, else kept between $min and $max. */
	private static function optional_size( $v, $min, $max ) {
		$v = trim( (string) $v );

		return ( $v === '' || (int) $v <= 0 ) ? 0 : max( $min, min( $max, (int) $v ) );
	}

	/** Posted values cleaned against the template's defaults. */
	public static function sanitize( array $tpl, $posted ) {
		$posted   = is_array( $posted ) ? $posted : [];
		$defaults = self::defaults( $tpl );
		$out      = [];

		// Blank: the brand colour (Settings, Branding).
		foreach ( [ 'primary', 'text' ] as $k ) {
			if ( array_key_exists( $k, $defaults ) ) {
				$out[ $k ] = self::clean_colour( $posted[ $k ] ?? '' );
			}
		}

		if ( array_key_exists( 'lines', $defaults ) ) {
			$out['lines'] = self::clean_colour( $posted['lines'] ?? '' );
		}

		if ( array_key_exists( 'font', $defaults ) ) {
			$font        = sanitize_key( $posted['font'] ?? '' );
			$out['font'] = isset( self::fonts()[ $font ] ) ? $font : $defaults['font'];
		}

		foreach ( (array) ( $defaults['items'] ?? [] ) as $item => $props ) {
			$p      = isset( $posted['items'][ $item ] ) && is_array( $posted['items'][ $item ] ) ? $posted['items'][ $item ] : [];
			$weight = (string) ( $p['weight'] ?? '' );
			$case   = (string) ( $p['case'] ?? '' );

			$out['items'][ $item ] = [
				'size'   => max( 8, min( 40, (int) ( $p['size'] ?? $props['size'] ) ) ),
				'weight' => isset( self::weights()[ $weight ] ) ? $weight : $props['weight'],
				'style'  => ( ( $p['style'] ?? '' ) === 'italic' ) ? 'italic' : 'normal',
				'case'   => isset( self::cases()[ $case ] ) ? $case : 'none',
				'colour' => self::clean_colour( $p['colour'] ?? '' ),
				'line'   => self::lh( $p['line'] ?? '', '' ),
			];
		}

		if ( array_key_exists( 'line_height', $defaults ) ) {
			$out['line_height'] = self::lh( $posted['line_height'] ?? '', $defaults['line_height'] );
		}

		if ( isset( $defaults['icons'] ) ) {
			$i            = isset( $posted['icons'] ) && is_array( $posted['icons'] ) ? $posted['icons'] : [];
			$di           = $defaults['icons'];
			$show         = (string) ( $i['show'] ?? '' );
			$weight       = (string) ( $i['label_weight'] ?? '' );
			$case         = (string) ( $i['label_case'] ?? '' );
			$out['icons'] = [
				'show'         => isset( self::icon_shows()[ $show ] ) ? $show : ( $di['show'] ?? 'icon' ),
				'valign'       => in_array( $i['valign'] ?? '', [ 'top', 'middle', 'bottom' ], true ) ? $i['valign'] : ( $di['valign'] ?? 'top' ),
				'colour'       => self::clean_colour( $i['colour'] ?? '' ),
				'background'   => empty( $i['background'] ) ? 0 : 1,
				'bg'           => self::clean_colour( $i['bg'] ?? '' ),
				'radius'       => max( 0, min( 30, (int) ( $i['radius'] ?? ( $di['radius'] ?? 4 ) ) ) ),
				'size'         => max( 10, min( 40, (int) ( $i['size'] ?? ( $di['size'] ?? 18 ) ) ) ),
				'label_colour' => self::clean_colour( $i['label_colour'] ?? '' ),
				'label_weight' => isset( self::weights()[ $weight ] ) ? $weight : ( $di['label_weight'] ?? '700' ),
				'label_italic' => ( ( $i['label_italic'] ?? '' ) === 'italic' ) ? 'italic' : 'normal',
				'label_case'   => isset( self::cases()[ $case ] ) ? $case : 'none',
			];
		}

		if ( isset( $defaults['social'] ) ) {
			$s             = isset( $posted['social'] ) && is_array( $posted['social'] ) ? $posted['social'] : [];
			$out['social'] = [
				'background' => empty( $s['background'] ) ? 0 : 1,
				'bg'         => self::clean_colour( $s['bg'] ?? '' ),
				'colour'     => self::clean_colour( $s['colour'] ?? '' ) ?: $defaults['social']['colour'],
				'radius'     => max( 0, min( 50, (int) ( $s['radius'] ?? $defaults['social']['radius'] ) ) ),
				'size'       => max( 16, min( 60, (int) ( $s['size'] ?? $defaults['social']['size'] ) ) ),
			];
		}

		if ( isset( $defaults['logo'] ) ) {
			$out['logo'] = [
				'max_width'  => max( 40, min( 400, (int) ( $posted['logo']['max_width'] ?? $defaults['logo']['max_width'] ) ) ),
				// Blank (0) means no height limit: the width decides.
				'max_height' => self::optional_size( $posted['logo']['max_height'] ?? $defaults['logo']['max_height'], 20, 200 ),
			];

			if ( isset( $defaults['logo']['small_max_width'] ) ) {
				$out['logo']['small_max_width']  = max( 16, min( 300, (int) ( $posted['logo']['small_max_width'] ?? $defaults['logo']['small_max_width'] ) ) );
				$out['logo']['small_max_height'] = self::optional_size( $posted['logo']['small_max_height'] ?? $defaults['logo']['small_max_height'], 10, 200 );
			}
		}

		// The layout (the Layout card posts it as JSON).
		if ( isset( $defaults['layout'] ) ) {
			$out['layout'] = self::layout_for( $defaults, $posted );
		}

		if ( isset( $defaults['buttons'] ) ) {
			$b              = isset( $posted['buttons'] ) && is_array( $posted['buttons'] ) ? $posted['buttons'] : [];
			$db             = $defaults['buttons'];
			$weight         = (string) ( $b['weight'] ?? '' );
			$case           = (string) ( $b['case'] ?? '' );
			$out['buttons'] = [
				'bg'             => self::clean_colour( $b['bg'] ?? '' ),
				'colour'         => self::clean_colour( $b['colour'] ?? '' ),
				'outline_colour' => self::clean_colour( $b['outline_colour'] ?? '' ),
				'radius'         => max( 0, min( 30, (int) ( $b['radius'] ?? $db['radius'] ) ) ),
				'text'           => max( 10, min( 24, (int) ( $b['text'] ?? $db['text'] ) ) ),
				'weight'         => isset( self::weights()[ $weight ] ) ? $weight : $db['weight'],
				'case'           => isset( self::cases()[ $case ] ) ? $case : 'none',
			];
		}

		if ( isset( $defaults['photo'] ) ) {
			$p            = isset( $posted['photo'] ) && is_array( $posted['photo'] ) ? $posted['photo'] : [];
			$dp           = $defaults['photo'];
			$shape        = (string) ( $p['shape'] ?? '' );
			$ratio        = (string) ( $p['ratio'] ?? '' );
			$out['photo'] = [
				// Shown when the layout has the photo in a column; the Show
				// tick went with the layout editor.
				'show'    => isset( $out['layout'] ) ? ( SB_EmailSig_Layout::shows( $out['layout'], 'photo' ) ? 1 : 0 ) : ( empty( $p['show'] ) ? 0 : 1 ),
				'shape'   => isset( SB_EmailSig_Photo::shapes()[ $shape ] ) ? $shape : $dp['shape'],
				'radius'  => max( 0, min( 60, (int) ( $p['radius'] ?? $dp['radius'] ) ) ),
				'ratio'   => isset( SB_EmailSig_Photo::ratios()[ $ratio ] ) ? $ratio : $dp['ratio'],
				'ratio_w' => max( 1, min( 99, (int) ( $p['ratio_w'] ?? $dp['ratio_w'] ) ) ),
				'ratio_h' => max( 1, min( 99, (int) ( $p['ratio_h'] ?? $dp['ratio_h'] ) ) ),
				'width'   => max( 40, min( 300, (int) ( $p['width'] ?? $dp['width'] ) ) ),
				'border'        => max( 0, min( 10, (int) ( $p['border'] ?? ( $dp['border'] ?? 0 ) ) ) ),
				'border_colour' => self::clean_colour( $p['border_colour'] ?? '' ),
				'gap'           => max( 0, min( 10, (int) ( $p['gap'] ?? ( $dp['gap'] ?? 0 ) ) ) ),
			];
		}

		if ( isset( $defaults['signature'] ) ) {
			$out['signature'] = [ 'width' => max( 300, min( 900, (int) ( $posted['signature']['width'] ?? $defaults['signature']['width'] ) ) ) ];
		}

		return $out;
	}

	/**
	 * What a template draws with: every colour as hex (blank ones take the
	 * primary or text colour, theme colours their current value), the font
	 * stack, a ready style string per item and each item's case. $values
	 * defaults to the saved ones.
	 */
	public static function resolve( array $tpl, $values = null ) {
		$d     = self::defaults( $tpl );
		$v     = is_array( $values ) ? self::merge( $d, $values ) : self::values( $tpl );
		$fonts = self::fonts();

		// Blank, or a theme colour that has gone from the theme: the brand's.
		$brand   = self::brand();
		$primary = self::hex( $v['primary'] ?? '' ) ?: $brand['primary']['hex'];
		$text    = self::hex( $v['text'] ?? '' ) ?: $brand['text']['hex'];
		$font    = $fonts[ $v['font'] ?? 'arial' ][1] ?? $fonts['arial'][1];

		$out = [
			'primary' => $primary,
			'text'    => $text,
			'font'    => $font,
			'lines'   => self::hex( $v['lines'] ?? '' ) ?: $primary,
			'css'     => [],
			'colour'  => [],
			'case'    => [],
			'icons'   => [],
			'social'  => [],
		];

		// Line height: each part's own, else the default (unitless; see lh()).
		$lh_base = self::lh( $v['line_height'] ?? '', '1.4' );
		$lh_of   = function ( $item ) use ( $v, $lh_base ) {
			return self::lh( $v['items'][ $item ]['line'] ?? '', $lh_base );
		};

		foreach ( (array) ( $v['items'] ?? [] ) as $item => $p ) {
			$size  = (int) $p['size'];
			// Blank: the text colour, or the brand colour the template names
			// (fallback, e.g. text_light for the footer text).
			$fb    = (string) ( $p['fallback'] ?? '' );
			$color = self::hex( $p['colour'] ?? '' ) ?: ( isset( $brand[ $fb ] ) ? $brand[ $fb ]['hex'] : $text );

			$out['css'][ $item ]    = 'font-family:' . $font . ';font-size:' . $size . 'px;line-height:' . $lh_of( $item ) . ';font-weight:' . $p['weight'] . ';font-style:' . $p['style'] . ';color:' . $color . ';';
			$out['colour'][ $item ] = $color;
			$out['case'][ $item ]   = $p['case'] ?? 'none';
		}

		if ( isset( $v['icons'] ) ) {
			$i     = $v['icons'];
			$show  = (string) ( $i['show'] ?? 'icon' );
			$tsize = (int) ( $v['items']['contact']['size'] ?? 14 );

			$ibg          = ! empty( $i['background'] ) ? ( self::hex( $i['bg'] ?? '' ) ?: $primary ) : '';
			$out['icons'] = [
				// icon, letter, label or none (the Info List Styles card).
				'show'   => isset( self::icon_shows()[ $show ] ) ? $show : 'icon',
				// How the icon or label lines up with its text.
				'valign' => in_array( $i['valign'] ?? '', [ 'top', 'middle', 'bottom' ], true ) ? $i['valign'] : 'top',
				// Blank: the primary, or white on a background.
				'colour' => self::hex( $i['colour'] ?? '' ) ?: ( $ibg !== '' ? '#ffffff' : $primary ),
				'bg'     => $ibg,
				'radius' => (int) ( $i['radius'] ?? 4 ),
				'size'   => (int) ( $i['size'] ?? 18 ),
				'names'  => (array) ( $i['names'] ?? [] ),
				// Letters and labels: at the contact text's size.
				'label'  => 'font-family:' . $font . ';font-size:' . $tsize . 'px;line-height:' . $lh_of( 'contact' ) . ';font-weight:' . ( $i['label_weight'] ?? '700' ) . ';font-style:' . ( ( $i['label_italic'] ?? '' ) === 'italic' ? 'italic' : 'normal' ) . ';color:' . ( self::hex( $i['label_colour'] ?? '' ) ?: $primary ) . ';',
			];
			$out['case']['icon_label'] = $i['label_case'] ?? 'none';
		}

		if ( isset( $v['social'] ) ) {
			$size          = (int) $v['social']['size'];
			$out['social'] = [
				'bg'     => ! empty( $v['social']['background'] ) ? ( self::hex( $v['social']['bg'] ?? '' ) ?: $primary ) : '',
				'colour' => self::hex( $v['social']['colour'] ?? '' ) ?: '#ffffff',
				'radius' => (int) $v['social']['radius'],
				'size'   => $size,
				'gap'    => (int) round( $size * 0.23 ),
			];
		}

		// Logo: fits inside both, never bigger than the image itself.
		$out['logo'] = [
			'max_width'        => (int) ( $v['logo']['max_width'] ?? 180 ),
			'max_height'       => (int) ( $v['logo']['max_height'] ?? 0 ),
			'small_max_width'  => (int) ( $v['logo']['small_max_width'] ?? 60 ),
			'small_max_height' => (int) ( $v['logo']['small_max_height'] ?? 0 ),
		];

		// Where everything sits (SB_EmailSig_Layout). A design without one
		// draws itself in its template.php.
		if ( isset( $v['layout'] ) ) {
			$out['layout'] = $v['layout'];
		}

		// The person's photo: shown or not, its shape, aspect ratio (width over
		// height) and width. A template without a photo group shows none.
		$out['photo'] = SB_EmailSig_Photo::default_look();
		if ( isset( $v['photo'] ) ) {
			$p            = $v['photo'];
			$out['photo'] = [
				'show'   => isset( $v['layout'] ) ? SB_EmailSig_Layout::shows( $v['layout'], 'photo' ) : ! empty( $p['show'] ),
				'shape'  => (string) ( $p['shape'] ?? 'circle' ),
				'radius' => (int) ( $p['radius'] ?? 0 ),
				'width'  => (int) ( $p['width'] ?? 130 ),
				'ratio'  => SB_EmailSig_Photo::ratio_value( $p['ratio'] ?? '1:1', $p['ratio_w'] ?? 0, $p['ratio_h'] ?? 0 ),
				// A ring drawn into the photo: its width, colour (blank: the
				// primary) and the see-through gap inside it.
				'border'        => max( 0, (int) ( $p['border'] ?? 0 ) ),
				'border_colour' => self::hex( $p['border_colour'] ?? '' ) ?: $primary,
				'gap'           => max( 0, (int) ( $p['gap'] ?? 0 ) ),
			];
		} else {
			$out['photo']['show'] = false;
		}

		// The signature never grows wider than this; banners fit inside it.
		$out['width'] = (int) ( $v['signature']['width'] ?? 600 );

		if ( isset( $v['buttons'] ) ) {
			$b       = $v['buttons'];
			// Sized by the text: padding and line height follow it, worked
			// out in px (as em would be) as Outlook ignores em padding.
			$fsize   = max( 10, min( 24, (int) ( $b['text'] ?? 13 ) ) );
			$lh      = (int) round( $fsize * 1.2 );
			$colour  = self::hex( $b['bg'] ?? '' ) ?: $primary;

			// Each button is solid or outline (ticked in Settings); both have
			// the same 2px border so they come out the same size.
			$out['buttons'] = [
				'solid'   => [
					'bg'     => $colour,
					'border' => $colour,
					'text'   => self::hex( $b['colour'] ?? '' ) ?: '#ffffff',
				],
				'outline' => [
					'bg'     => '',
					'border' => $colour,
					'text'   => self::hex( $b['outline_colour'] ?? '' ) ?: $colour,
				],
				'radius'  => (int) $b['radius'],
				'font'    => $fsize,
				'line'    => $lh,
				'pad_y'   => max( 2, (int) round( $fsize * 0.4 ) ),
				'pad_x'   => (int) round( $fsize * 1.15 ),
				'weight'  => $b['weight'] ?? '400',
				'gap'     => 8,
			];
			$out['case']['buttons'] = $b['case'] ?? 'none';
		}

		return $out;
	}

	/** Text in an item's case. For templates: SB_EmailSig_Styles::cased( $s, 'name', $d['name'] ). */
	public static function cased( array $s, $item, $text ) {
		$text = (string) $text;

		switch ( $s['case'][ $item ] ?? 'none' ) {
			case 'uppercase':
				return mb_strtoupper( $text, 'UTF-8' );
			case 'lowercase':
				return mb_strtolower( $text, 'UTF-8' );
			case 'capitalize':
				// Like CSS: the first letter of each word up, the rest as typed.
				return preg_replace_callback(
					'/(^|[\s\-\/(])(\p{Ll})/u',
					function ( $m ) {
						return $m[1] . mb_strtoupper( $m[2], 'UTF-8' );
					},
					$text
				);
		}

		return $text;
	}

	/**
	 * Every template's icon styles, for building icons on the Settings page:
	 * the social icons (size, colour, bg, radius, gap) and contact (size,
	 * colour, svgs), plus src: which brand colour each colour comes from
	 * ('' for none), so a brand colour changed on that page draws them in
	 * the new colour before it is saved.
	 */
	public static function social_styles() {
		$out = [];

		foreach ( SB_EmailSig_Templates::all() as $id => $tpl ) {
			$r       = self::resolve( $tpl );
			$v       = self::values( $tpl );
			$primary = (string) ( $v['primary'] ?? '' ) !== '' ? $v['primary'] : self::brand_token( 'primary' );
			$key     = function ( $raw ) {
				return preg_match( '/^var\(--sb-brand-([a-z-]+)\)$/', (string) $raw, $m ) ? str_replace( '-', '_', $m[1] ) : '';
			};
			$entry   = $r['social'] ? $r['social'] : [];

			if ( $r['social'] ) {
				$entry['src'] = [
					'bg'     => $r['social']['bg'] !== '' ? $key( ( $v['social']['bg'] ?? '' ) !== '' ? $v['social']['bg'] : $primary ) : '',
					'colour' => $key( $v['social']['colour'] ?? '' ),
				];
			}

			if ( $r['icons'] ) {
				$entry['contact'] = [
					'size'   => $r['icons']['size'],
					'colour' => $r['icons']['colour'],
					'src'    => ( $v['icons']['colour'] ?? '' ) !== '' ? $key( $v['icons']['colour'] ) : ( $r['icons']['bg'] !== '' ? '' : $key( $primary ) ),
					'bg'     => $r['icons']['bg'],
					'bgsrc'  => $r['icons']['bg'] !== '' ? $key( ( $v['icons']['bg'] ?? '' ) !== '' ? $v['icons']['bg'] : $primary ) : '',
					'radius' => $r['icons']['radius'],
					'svgs'   => self::contact_svgs( $tpl ),
				];
			}

			if ( $entry ) {
				$out[ $id ] = $entry;
			}
		}

		return $out;
	}

	/** A template's contact icon SVGs, by name, from its icons/ folder. */
	public static function contact_svgs( array $tpl ) {
		$out = [];

		foreach ( (array) ( self::defaults( $tpl )['icons']['names'] ?? [] ) as $name ) {
			$file = $tpl['path'] . 'icons/' . $name . '.svg';
			if ( file_exists( $file ) ) {
				$out[ $name ] = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}

		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Edit styles page
	 * ------------------------------------------------------------------ */

	public static function editing() {
		$id = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		return $id !== '' ? SB_EmailSig_Templates::get( $id ) : null;
	}

	/** True on a template's Layout page (...-templates&edit=<id>&view=layout), for a design with a layout. */
	public static function on_layout_page( array $tpl ) {
		return isset( $_GET['view'] ) && $_GET['view'] === 'layout' && isset( self::defaults( $tpl )['layout'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function layout_url( $id ) {
		return add_query_arg( 'view', 'layout', SB_EmailSig_Templates::edit_url( $id ) );
	}

	/**
	 * What the Layout page needs to redraw the contact and social icons in a
	 * new Primary colour, the way styles.js draws them on Edit styles: the
	 * SVGs, the icon styles as saved (colours as hex, '' where they follow
	 * the Primary) and the Primary they were drawn in.
	 */
	private static function icon_setup( array $tpl, array $v, array $d ) {
		return [
			// Each company's colours and social icons (Sam, 30 October 2026):
			// the preview draws in the previewed person's, and a save draws
			// every company's.
			'companies' => self::company_setups( $v ),
			'company'   => self::preview_company(),
			'primary'  => self::hex( $v['primary'] ) ?: self::brand()['primary']['hex'],
			// The template's own Primary, '' when it follows each company's.
			'own'      => (string) self::hex( $v['primary'] ),
			'contact'  => self::contact_svgs( $tpl ),
			'social'   => SB_EmailSig_Icons::social_svgs(),
			'styles'   => self::icon_styles( $v, $d ),
			// As the design has them, for Reset Template with Edit styles too.
			'defaults' => self::icon_styles( $d, $d ),
		];
	}

	/**
	 * Every company's brand colours (hex), colours menu, social icons and
	 * the Primary its icons are drawn in (the template's own, else the
	 * company's), for the icons drawn in the browser on Edit styles and the
	 * Layout page (Sam, 30 October 2026).
	 */
	private static function company_setups( array $v ) {
		$out = [];

		foreach ( SB_EmailSig_Company::all() as $co ) {
			$out[] = SB_EmailSig_Company::with(
				$co,
				function () use ( $co, $v ) {
					$brand = self::brand();

					return [
						'id'      => (int) $co,
						'brand'   => (object) wp_list_pluck( $brand, 'hex' ),
						'palette' => self::editor_palette(),
						'social'  => SB_EmailSig_Icons::social_svgs(),
						'primary' => self::hex( $v['primary'] ?? '' ) ?: $brand['primary']['hex'],
					];
				}
			);
		}

		return $out;
	}

	/** The company of the person the preview opens on. */
	private static function preview_company() {
		$person = self::person_or_first( (int) get_user_meta( get_current_user_id(), self::PERSON_META, true ) );

		return (int) SB_EmailSig_Company::of_person( $person );
	}

	/**
	 * Icons drawn in the browser, saved: one set (the default company's
	 * slot), or { companies: { id: set } } for each company's own.
	 */
	private static function save_icons( $tpl_id, $icons ) {
		if ( ! is_array( $icons ) ) {
			return;
		}

		if ( isset( $icons['companies'] ) && is_array( $icons['companies'] ) ) {
			foreach ( $icons['companies'] as $co => $set ) {
				if ( is_array( $set ) && SB_EmailSig_Company::valid( (int) $co ) ) {
					SB_EmailSig_Icons::save( $tpl_id, $set, [ 'contact', 'social' ], (int) $co );

					// Icons in rows' and blocks' own colours (the Layout page).
					if ( isset( $set['variants'] ) && is_array( $set['variants'] ) ) {
						SB_EmailSig_Icons::save_variants( $tpl_id, $set['variants'], (int) $co );
					}
				}
			}

			return;
		}

		SB_EmailSig_Icons::save( $tpl_id, $icons, [ 'contact', 'social' ] );

		if ( isset( $icons['variants'] ) && is_array( $icons['variants'] ) ) {
			SB_EmailSig_Icons::save_variants( $tpl_id, $icons['variants'] );
		}
	}

	/** The icon styles in $v (saved or the design's): colours as hex, '' where they follow the Primary. */
	private static function icon_styles( array $v, array $d ) {
		$i      = $v['icons'] ?? [];
		$so     = $v['social'] ?? null;
		$social = isset( $d['social'] ) && is_array( $so ) ? [
			'size'       => (int) ( $so['size'] ?: $d['social']['size'] ),
			'colour'     => self::hex( $so['colour'] ) ?: ( self::hex( $d['social']['colour'] ) ?: '#ffffff' ),
			'radius'     => (int) $so['radius'],
			'background' => ! empty( $so['background'] ),
			'bg'         => (string) self::hex( $so['bg'] ),
		] : null;

		return [
			'contact' => [
				'size'       => (int) ( ( $i['size'] ?? 0 ) ?: ( $d['icons']['size'] ?? 18 ) ),
				'colour'     => (string) self::hex( $i['colour'] ?? '' ),
				'background' => ! empty( $i['background'] ),
				'bg'         => (string) self::hex( $i['bg'] ?? '' ),
				'radius'     => (int) ( $i['radius'] ?? 0 ),
			],
			'social'  => $social,
		];
	}

	public static function assets( $hook ) {
		$tpl = self::editing();

		if ( ! $tpl || strpos( (string) $hook, SB_EmailSig_Module::SLUG ) === false ) {
			return;
		}

		// The Layout page: the layout editor (assets/layout.js), with
		// SortableJS for dragging and the framework's popup for each block's
		// settings. It draws its own preview.
		if ( self::on_layout_page( $tpl ) ) {
			SB_EmailSig_Notify::register_confirm();
			wp_enqueue_script( 'sb-emailsig-sortable', SB_EMAILSIG_URL . 'assets/vendor/sortable/Sortable.min.js', [], '1.15.6', true );
			wp_enqueue_script( 'sb-emailsig-icon-builder', SB_EMAILSIG_URL . 'assets/icon-builder.js', [], SB_EmailSig_Module::asset_version( 'assets/icon-builder.js' ), true );
			wp_enqueue_script( 'sb-emailsig-layout', SB_EMAILSIG_URL . 'assets/layout.js', array_merge( [ 'jquery', 'sb-emailsig-sortable', 'sb-emailsig-icon-builder' ], wp_script_is( 'sb-tweaks-confirm', 'registered' ) ? [ 'sb-tweaks-confirm' ] : [] ), SB_EmailSig_Module::asset_version( 'assets/layout.js' ), true );

			$v = self::values( $tpl );
			$d = self::defaults( $tpl );

			$seps = [];
			foreach ( SB_EmailSig_Layout::seps() as $k => $sep ) {
				$seps[ $k ] = $sep[0];
			}

			// Each block type with its options at their defaults (their order is the saved order).
			$defs = [];
			foreach ( array_keys( SB_EmailSig_Layout::blocks() ) as $type ) {
				$defs[ $type ] = SB_EmailSig_Layout::block( $type, 1 );
			}

			wp_localize_script(
				'sb-emailsig-layout',
				'sbEmailsigLayout',
				[
					'blocks' => SB_EmailSig_Layout::blocks(),
					'items'  => SB_EmailSig_Layout::items(),
					'texts'  => array_combine(
						array_map(
							function ( $ref ) {
								return 'c:' . $ref;
							},
							array_keys( SB_EmailSig_Layout::company_items() )
						),
						array_column( SB_EmailSig_Layout::company_items(), 'text' )
					) ?: (object) [],
					'seps'   => $seps,
					'multi'  => SB_EmailSig_Layout::MULTI,
					'max'    => SB_EmailSig_Layout::MAX_MULTI,
					'maxCols' => SB_EmailSig_Layout::MAX_COLUMNS,
					'maxRows' => SB_EmailSig_Layout::MAX_ROWS,
					'nameFormats' => SB_EmailSig_Names::formats(),
					'block'  => $defs,
					'ajax'   => admin_url( 'admin-ajax.php' ),
					'nonce'  => wp_create_nonce( 'sb_emailsig_preview' ),
					'tpl'    => $tpl['id'],
					// The colour fields: the theme colours menu, and what a blank one shows.
					'palette' => self::editor_palette(),
					'colours' => self::colours(),
					'brand'   => wp_list_pluck( self::brand(), 'hex' ),
					'lines'   => self::resolve( $tpl )['lines'],
					'styles'  => SB_EmailSig_Layout::STYLES,
					'maxLine' => SB_EmailSig_Layout::MAX_LINE,
					'doubleMin' => SB_EmailSig_Layout::DOUBLE_MIN,
					// The icons, redrawn when the Primary colour changes (Layout Settings).
					'icons'   => self::icon_setup( $tpl, $v, $d ),
				]
			);

			return;
		}

		wp_enqueue_script( 'sb-emailsig-icon-builder', SB_EMAILSIG_URL . 'assets/icon-builder.js', [], SB_EmailSig_Module::asset_version( 'assets/icon-builder.js' ), true );
		wp_enqueue_script( 'sb-emailsig-styles', SB_EMAILSIG_URL . 'assets/styles.js', [ 'jquery', 'sb-emailsig-icon-builder' ], SB_EmailSig_Module::asset_version( 'assets/styles.js' ), true );

		$contact = self::contact_svgs( $tpl );

		wp_localize_script(
			'sb-emailsig-styles',
			'sbEmailsigStyles',
			[
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'sb_emailsig_preview' ),
				'template' => $tpl['id'],
				'contact'  => $contact,
				'social'   => SB_EmailSig_Icons::social_svgs(),
				'defaults' => self::defaults( $tpl ),
				'palette'  => self::editor_palette(),
				'colours'  => self::colours(),
				'brand'    => wp_list_pluck( self::brand(), 'hex' ),
				// Each company's colours and social icons, and the previewed person's company.
				'companies' => self::company_setups( self::values( $tpl ) ),
				'company'   => self::preview_company(),
				// Primary and Text as saved, for a design whose Colours are on the Layout page.
				'saved'    => [
					'primary' => (string) self::values( $tpl )['primary'],
					'text'    => (string) self::values( $tpl )['text'],
				],
			]
		);
	}

	public static function render_editor( array $tpl ) {
		$v      = self::values( $tpl );
		$d      = self::defaults( $tpl );
		$person = self::person_or_first( (int) get_user_meta( get_current_user_id(), self::PERSON_META, true ) );
		$html   = SB_EmailSig_Render::html_from_data( SB_EmailSig_Templates::preview_data( $person, $tpl['id'] ), $tpl['id'] );
		$name   = $tpl['name'] !== $tpl['default_name'] ? $tpl['name'] : '';

		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>Template saved.</p></div>';
		} elseif ( isset( $_GET['copied'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>Template copied. Change what you like and save.</p></div>';
		} elseif ( isset( $_GET['added'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>Template added with the default layout and styles. Change what you like and save.</p></div>';
		} elseif ( isset( $_GET['imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>Template imported. Save it once so its icons are drawn in its colours.</p></div>';
		}

		self::editor_bar( $tpl, 'styles', 'sb-emailsig-styles-form' );

		echo '<form id="sb-emailsig-styles-form" class="sb-emailsig-editor" method="post" autocomplete="off" data-sb-dirty action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sb_emailsig_save_styles">';
		echo '<input type="hidden" name="sb_emailsig_template" value="' . esc_attr( $tpl['id'] ) . '">';
		// No name until the form is sent, so the framework's unsaved changes check ignores it.
		echo '<input type="hidden" data-sb-emailsig-icons value="">';
		// Reset to defaults with the Layout page too: named only then (styles.js).
		if ( isset( $d['layout'] ) ) {
			echo '<input type="hidden" data-sb-emailsig-reset-other value="1">';
		}
		wp_nonce_field( 'sb_emailsig_save_styles' );

		echo '<div class="sb-emailsig-editor__main">';

		// Template: its name and description, which nothing resets (Sam, 29 October 2026).
		self::card_open( 'Template', false );
		echo '<div class="sb-emailsig-fields">';
		echo '<div class="sb-emailsig-field sb-emailsig-field--wide"><label for="sb-emailsig-template-name">Name</label>';
		echo '<input type="text" id="sb-emailsig-template-name" name="template_name" value="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $tpl['default_name'] ) . '"></div>';
		// Its description, for its card on the Templates page (records only).
		if ( SB_EmailSig_Templates::has_records() ) {
			$max = SB_EmailSig_Templates::NOTE_MAX;
			echo '<div class="sb-emailsig-field sb-emailsig-field--note"><label for="sb-emailsig-template-note">Description</label>';
			echo '<textarea id="sb-emailsig-template-note" name="template_note" rows="3" maxlength="' . (int) $max . '" placeholder="What it\'s for, shown on its card">' . esc_textarea( $tpl['note'] ) . '</textarea>';
			echo '<span class="sb-ui-chars" data-sb-count-for="sb-emailsig-template-note"></span></div>';
		}
		echo '</div>';
		self::card_close();

		// Sizes: all maximums, so nothing grows past them or past its own image.
		if ( isset( $d['signature'] ) || isset( $d['logo'] ) ) {
			self::card_open( 'Sizes' );
			// A line each: the signature, the logo, the small logo (Sam, 31 October 2026).
			echo '<div class="sb-emailsig-sizes">';
			if ( isset( $d['signature'] ) ) {
				echo '<div class="sb-emailsig-fields">';
				self::number_field( 'Signature max width', 's[signature][width]', $v['signature']['width'], $d['signature']['width'], 300, 900, 'px' );
				echo '</div>';
			}
			if ( isset( $d['logo'] ) ) {
				echo '<div class="sb-emailsig-fields">';
				self::number_field( 'Logo max width', 's[logo][max_width]', $v['logo']['max_width'], $d['logo']['max_width'], 40, 400, 'px' );
				self::number_field( 'Logo max height', 's[logo][max_height]', $v['logo']['max_height'], $d['logo']['max_height'], 20, 200, 'px', true );
				echo '</div>';
				if ( isset( $d['logo']['small_max_width'] ) ) {
					echo '<div class="sb-emailsig-fields">';
					self::number_field( 'Small logo max width', 's[logo][small_max_width]', $v['logo']['small_max_width'], $d['logo']['small_max_width'], 16, 300, 'px' );
					self::number_field( 'Small logo max height', 's[logo][small_max_height]', $v['logo']['small_max_height'], $d['logo']['small_max_height'], 10, 200, 'px', true );
					echo '</div>';
				}
			}
			echo '</div>';
			self::card_close();
		}

		// Photo: the template's look for everyone's photo.
		if ( isset( $d['photo'] ) ) {
			$p  = $v['photo'];
			$dp = $d['photo'];
			self::card_open( 'Photo' );
			echo '<div class="sb-emailsig-fields">';
			// Whether it shows is the layout's (the Show tick went with the
			// layout editor), except for a design without a layout.
			if ( ! isset( $d['layout'] ) ) {
				echo '<div class="sb-emailsig-field"><span class="sb-emailsig-field__label">Photos</span><label class="sb-emailsig-check"><input type="checkbox" name="s[photo][show]" value="1" data-default="' . (int) $dp['show'] . '"' . checked( ! empty( $p['show'] ), true, false ) . '> Show</label></div>';
			}
			self::select_field( 'Shape', 's[photo][shape]', $p['shape'], SB_EmailSig_Photo::shapes(), $dp['shape'] );
			self::when( 's[photo][shape]', 'rounded' );
			self::number_field( 'Corner radius', 's[photo][radius]', $p['radius'], $dp['radius'], 0, 60, 'px' );
			echo '</div>';
			self::select_field( 'Aspect ratio', 's[photo][ratio]', $p['ratio'], SB_EmailSig_Photo::ratios(), $dp['ratio'] );
			self::when( 's[photo][ratio]', 'custom' );
			self::number_field( 'Ratio width', 's[photo][ratio_w]', $p['ratio_w'], $dp['ratio_w'], 1, 99, '' );
			self::number_field( 'Ratio height', 's[photo][ratio_h]', $p['ratio_h'], $dp['ratio_h'], 1, 99, '' );
			echo '</div>';
			self::number_field( 'Width', 's[photo][width]', $p['width'], $dp['width'], 40, 300, 'px' );
			echo '</div>';
			// A ring drawn into the photo itself, so every mail app shows it
			// (Sam, 30 October 2026), on a row of its own. People can have
			// their own on their page.
			echo '<div class="sb-emailsig-fields sb-emailsig-fields--row">';
			self::number_field( 'Border', 's[photo][border]', $p['border'] ?? 0, $dp['border'] ?? 0, 0, 10, 'px', true );
			self::number_field( 'Gap inside', 's[photo][gap]', $p['gap'] ?? 0, $dp['gap'] ?? 0, 0, 10, 'px', true );
			self::colour_field( 'Border colour', 's[photo][border_colour]', $p['border_colour'] ?? '', $dp['border_colour'] ?? '', 'primary' );
			echo '</div>';
			self::card_close();
		}

		// Colours and font: on the Layout page (Layout Settings) for a
		// design with a layout.
		if ( ! isset( $d['layout'] ) ) {
			self::card_open( 'Colours and font' );
			self::colours_and_font( $v, $d );
			self::card_close();
		}

		// Text: the default line height, then one row per part (parts with a
		// card of their own sit there).
		self::card_open( 'Text' );
		if ( array_key_exists( 'line_height', $d ) ) {
			echo '<div class="sb-emailsig-fields">';
			self::lh_field( 'Default line height', 's[line_height]', $v['line_height'], $d['line_height'], '' );
			echo '</div>';
		}
		foreach ( (array) $d['items'] as $item => $props ) {
			if ( empty( $props['card'] ) ) {
				self::item_fields( $item, $props, $v['items'][ $item ] );
			}
		}
		self::card_close();

		// Info List Styles: what starts each Info List line (Info icons
		// until 29 October 2026, renamed with the layout editor).
		if ( isset( $d['icons'] ) ) {
			$i  = $v['icons'];
			$di = $d['icons'];
			self::card_open( 'Info List Styles' );
			echo '<div class="sb-emailsig-fields">';
			self::select_field( 'Label style', 's[icons][show]', $i['show'] ?? 'icon', self::icon_shows(), $di['show'] ?? 'icon' );
			self::when( 's[icons][show]', 'icon|letter|label' );
			self::select_field( 'Vertical align', 's[icons][valign]', $i['valign'] ?? 'top', [ 'top' => 'Top', 'middle' => 'Middle', 'bottom' => 'Bottom' ], $di['valign'] ?? 'top' );
			echo '</div>';
			self::when( 's[icons][show]', 'icon' );
			self::colour_field( 'Icon colour', 's[icons][colour]', $i['colour'] ?? '', $di['colour'] ?? '', 'icon' );
			echo '<div class="sb-emailsig-field"><span class="sb-emailsig-field__label">Background</span><label class="sb-emailsig-check"><input type="checkbox" name="s[icons][background]" value="1" data-default="' . (int) ( $di['background'] ?? 0 ) . '"' . checked( ! empty( $i['background'] ), true, false ) . '> Show</label></div>';
			self::when( 's[icons][background]', 'true' );
			self::colour_field( 'Background colour', 's[icons][bg]', $i['bg'] ?? '', $di['bg'] ?? '', 'primary' );
			self::number_field( 'Corner radius', 's[icons][radius]', $i['radius'] ?? 4, $di['radius'] ?? 4, 0, 30, 'px' );
			echo '</div>';
			self::number_field( 'Size', 's[icons][size]', $i['size'] ?? 18, $di['size'] ?? 18, 10, 40, 'px' );
			echo '</div>';
			self::when( 's[icons][show]', 'letter|label' );
			self::colour_field( 'Label colour', 's[icons][label_colour]', $i['label_colour'] ?? '', $di['label_colour'] ?? '', 'primary' );
			self::select_field( 'Weight', 's[icons][label_weight]', $i['label_weight'] ?? '700', self::weights(), $di['label_weight'] ?? '700' );
			self::select_field( 'Style', 's[icons][label_italic]', $i['label_italic'] ?? 'normal', [ 'normal' => 'Normal', 'italic' => 'Italic' ], $di['label_italic'] ?? 'normal' );
			self::select_field( 'Case', 's[icons][label_case]', $i['label_case'] ?? 'none', self::cases(), $di['label_case'] ?? 'none' );
			echo '</div>';
			echo '</div>';
			self::card_close();
		}

		// Social icons.
		if ( isset( $d['social'] ) ) {
			$s = $v['social'];
			self::card_open( 'Social icons' );
			echo '<div class="sb-emailsig-fields">';
			echo '<div class="sb-emailsig-field"><span class="sb-emailsig-field__label">Background</span><label class="sb-emailsig-check"><input type="checkbox" name="s[social][background]" value="1" data-default="' . (int) $d['social']['background'] . '"' . checked( ! empty( $s['background'] ), true, false ) . '> Show</label></div>';
			self::colour_field( 'Background colour', 's[social][bg]', $s['bg'], $d['social']['bg'], 'primary' );
			self::colour_field( 'Icon colour', 's[social][colour]', $s['colour'], $d['social']['colour'] );
			self::number_field( 'Corner radius', 's[social][radius]', $s['radius'], $d['social']['radius'], 0, 50, 'px' );
			self::number_field( 'Size', 's[social][size]', $s['size'], $d['social']['size'], 16, 60, 'px' );
			echo '</div>';
			// The heading above them (Settings, Social Media).
			foreach ( (array) $d['items'] as $item => $props ) {
				if ( ( $props['card'] ?? '' ) === 'social' ) {
					self::item_fields( $item, $props, $v['items'][ $item ] );
				}
			}
			self::card_close();
		}

		// Additional Buttons.
		if ( isset( $d['buttons'] ) ) {
			$b  = $v['buttons'];
			$db = $d['buttons'];
			self::card_open( 'Buttons' );
			echo '<div class="sb-emailsig-fields">';
			self::colour_field( 'Colour', 's[buttons][bg]', $b['bg'], $db['bg'], 'primary' );
			self::colour_field( 'Solid text', 's[buttons][colour]', $b['colour'], $db['colour'], 'white' );
			self::colour_field( 'Outline text', 's[buttons][outline_colour]', $b['outline_colour'], $db['outline_colour'], 'button' );
			self::number_field( 'Corner radius', 's[buttons][radius]', $b['radius'], $db['radius'], 0, 30, 'px' );
			self::number_field( 'Text size', 's[buttons][text]', $b['text'], $db['text'], 10, 24, 'px' );
			self::select_field( 'Weight', 's[buttons][weight]', $b['weight'], self::weights(), $db['weight'] );
			self::select_field( 'Case', 's[buttons][case]', $b['case'], self::cases(), $db['case'] );
			echo '</div>';
			self::card_close();
		}

		echo '<p class="sb-emailsig-editor__actions">';
		echo '<button type="submit" class="button button-primary sb-save--clean">Save changes</button>';
		echo '<button type="button" class="button sb-tweaks-button--danger sb-emailsig-editor__reset">Reset to defaults</button>';
		echo '</p>';

		echo '</div>';

		echo '<div class="sb-emailsig-editor__side"><div class="sb-emailsig-editor__sticky">';
		echo '<div class="sb-emailsig-editor__side-head"><h3>Preview</h3><span class="sb-emailsig-editor__status" aria-live="polite"></span>';
		echo self::people_select( $person ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
		echo '</div>';
		echo '<div class="sb-emailsig-preview sb-emailsig-editor__preview"><div class="sb-emailsig-editor__fit" id="sb-emailsig-style-preview">' . $html . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped in the template.
		echo '<p class="sb-emailsig-editor__who"><span class="sb-emailsig-editor__scale"></span></p>';
		echo '</div></div>';

		echo '</form>';
	}

	/**
	 * The bar across the top of Edit styles and Layout (Sam, 29 October
	 * 2026): All templates, the template's name, tabs between its two pages
	 * and Save changes for this page's form (the framework's unsaved changes
	 * script keeps it in step with the Save at the foot).
	 */
	private static function editor_bar( array $tpl, $view, $form ) {
		// Main for any company (Sam, 31 October 2026: was the default company's only).
		$of     = SB_EmailSig_Templates::companies_of( $tpl['id'] );
		$multi  = count( SB_EmailSig_Company::all() ) > 1;
		$main   = $multi ? (bool) $of['main'] : $tpl['id'] === SB_EmailSig_Templates::current();
		$layout = isset( self::defaults( $tpl )['layout'] );

		echo '<div class="sb-ui-bar sb-emailsig-bar sb-emailsig-editbar">';
		echo '<div class="sb-ui-bar__group"><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-templates' ) ) . '">' . SB_EmailSig_Admin::icon( 'grid' ) . 'All templates</a></div>';
		// Its name as a menu of every template, to the same page of another (Sam, 31 October 2026).
		$here  = $view === 'layout' ? self::layout_url( $tpl['id'] ) : SB_EmailSig_Templates::edit_url( $tpl['id'] );
		$items = array();
		foreach ( SB_EmailSig_Templates::all() as $tid => $t ) {
			$items[] = array(
				$view === 'layout' && isset( self::defaults( $t )['layout'] ) ? self::layout_url( (string) $tid ) : SB_EmailSig_Templates::edit_url( (string) $tid ),
				$t['name'],
				'',
			);
		}
		echo '<div class="sb-ui-bar__group"><span class="sb-ui-bar__label">Template</span>' . SB_EmailSig_Admin::goto_menu( $here, $tpl['name'], $items, 'Edit another template' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in goto_menu().

		if ( $main ) {
			echo '<span class="sb-emailsig-template__badge" data-sb-tip="' . esc_attr( $multi ? 'Main for ' . implode( ', ', $of['main'] ) : 'Everyone\'s, unless their own says otherwise' ) . '">Main</span>';
		}

		echo '</div>';

		if ( $layout ) {
			echo '<div class="sb-ui-bar__group"><nav class="sb-ui-seg" aria-label="Template pages">';
			echo '<a href="' . esc_url( SB_EmailSig_Templates::edit_url( $tpl['id'] ) ) . '"' . ( $view === 'styles' ? ' aria-current="page"' : '' ) . '>' . SB_EmailSig_Admin::icon( 'palette' ) . 'Edit styles</a>';
			echo '<a href="' . esc_url( self::layout_url( $tpl['id'] ) ) . '"' . ( $view === 'layout' ? ' aria-current="page"' : '' ) . '>' . SB_EmailSig_Admin::icon( 'columns' ) . 'Layout</a>';
			echo '</nav></div>';
		}

		echo '<div class="sb-ui-bar__end"><button type="submit" form="' . esc_attr( $form ) . '" class="button button-primary sb-save--clean">' . SB_EmailSig_Admin::icon( 'check' ) . '<span data-sb-label>Save changes</span></button></div>';
		echo '</div>';
	}

	/** Primary, Text, Lines and Font: Colours and font on Edit styles, or the Layout page's Layout Settings. */
	private static function colours_and_font( array $v, array $d ) {
		echo '<div class="sb-emailsig-fields">';
		self::colour_field( 'Primary', 's[primary]', $v['primary'], $d['primary'], 'brand_primary' );
		self::colour_field( 'Text', 's[text]', $v['text'], $d['text'], 'brand_text' );
		if ( array_key_exists( 'lines', $d ) ) {
			self::colour_field( 'Lines', 's[lines]', $v['lines'], '', 'primary' );
		}
		self::select_field( 'Font', 's[font]', $v['font'], wp_list_pluck( self::fonts(), 0 ), $d['font'] );
		echo '</div>';
	}

	/**
	 * A template's Layout page: the layout editor and a preview of it, saved
	 * on its own (save_layout()), so it and Edit styles never overwrite each
	 * other. assets/layout.js draws the editor from the JSON in s[layout],
	 * writes it back and keeps the preview up to date.
	 */
	public static function render_layout( array $tpl ) {
		$v      = self::values( $tpl );
		$d      = self::defaults( $tpl );
		$person = self::person_or_first( (int) get_user_meta( get_current_user_id(), self::PERSON_META, true ) );
		$html   = SB_EmailSig_Render::html_from_data( SB_EmailSig_Templates::preview_data( $person, $tpl['id'] ), $tpl['id'] );
		$flags  = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>Layout saved.</p></div>';
		}

		self::editor_bar( $tpl, 'layout', 'sb-emailsig-layout-form' );

		echo '<form id="sb-emailsig-layout-form" class="sb-emailsig-editor sb-emailsig-editor--layout" method="post" autocomplete="off" data-sb-dirty action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sb_emailsig_save_layout">';
		echo '<input type="hidden" name="sb_emailsig_template" value="' . esc_attr( $tpl['id'] ) . '">';
		// Icons redrawn when the Primary colour changes; named only as the form is sent (layout.js).
		echo '<input type="hidden" data-sb-emailsig-icons value="">';
		// Reset Template with Edit styles too: named only then (layout.js).
		echo '<input type="hidden" data-sb-emailsig-reset-other value="1">';
		wp_nonce_field( 'sb_emailsig_save_layout' );

		echo '<div class="sb-emailsig-editor__main">';

		// The layout card first, then Layout Settings (Sam, 29 October 2026).
		// Undo and Redo cover every change on the page (layout.js).
		echo '<div class="sb-tweaks-card sb-emailsig-editor__card"><div class="sb-tweaks-card__head"><h3>' . esc_html( $tpl['name'] ) . ' layout</h3>';
		// Reset Template: everything on this page back to the design, after asking (Sam, 29 October 2026).
		echo '<button type="button" class="button button-small sb-tweaks-button--danger sb-emailsig-layout__reset-all">Reset Template</button>';
		echo '<span class="sb-emailsig-layout__history"><button type="button" class="button button-small" data-history="undo" title="Undo" aria-label="Undo" disabled><span class="dashicons dashicons-undo" aria-hidden="true"></span></button>';
		echo '<button type="button" class="button button-small" data-history="redo" title="Redo" aria-label="Redo" disabled><span class="dashicons dashicons-redo" aria-hidden="true"></span></button></span>';
		echo '<button type="button" class="sb-emailsig-card-reset" disabled>Reset</button></div>';
		echo '<input type="hidden" name="s[layout]" value="' . esc_attr( wp_json_encode( $v['layout'], $flags ) ) . '" data-default="' . esc_attr( wp_json_encode( $d['layout'], $flags ) ) . '" data-sb-emailsig-layout-field>';
		echo '<div class="sb-emailsig-layout" data-sb-emailsig-layout></div>';
		echo '</div>';

		// Layout Settings: the colours and font (Colours and font on Edit
		// styles until 29 October 2026) and the Default Spacing, Line Width
		// and Line Style, which layout.js adds (Sam).
		echo '<div class="sb-tweaks-card sb-emailsig-editor__card" data-sb-emailsig-layout-settings-card><div class="sb-tweaks-card__head"><h3>Layout Settings</h3>';
		echo '<button type="button" class="sb-emailsig-card-reset" disabled>Reset</button></div>';
		self::colours_and_font( $v, $d );
		echo '<div class="sb-emailsig-layout" data-sb-emailsig-layout-settings></div>';
		echo '</div>';

		// Two more cards layout.js fills in, each with its own Reset (Sam, 29 October 2026).
		echo '<div class="sb-tweaks-card sb-emailsig-editor__card" data-sb-emailsig-layout-part="box"><div class="sb-tweaks-card__head"><h3>Container Settings</h3><button type="button" class="sb-emailsig-card-reset" disabled>Reset</button></div><div class="sb-emailsig-layout" data-sb-emailsig-layout-box></div></div>';
		echo '<div class="sb-tweaks-card sb-emailsig-editor__card" data-sb-emailsig-layout-part="footer"><div class="sb-tweaks-card__head"><h3>Footer Settings</h3><button type="button" class="sb-emailsig-card-reset" disabled>Reset</button></div><div class="sb-emailsig-layout" data-sb-emailsig-layout-footer></div></div>';
		echo '<p class="sb-emailsig-editor__actions"><button type="submit" class="button button-primary sb-save--clean">Save changes</button></p>';
		echo '</div>';

		echo '<div class="sb-emailsig-editor__side"><div class="sb-emailsig-editor__sticky">';
		echo '<div class="sb-emailsig-editor__side-head"><h3>Preview</h3><span class="sb-emailsig-editor__status" aria-live="polite"></span>';
		echo self::people_select( $person ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
		echo '</div>';
		echo '<div class="sb-emailsig-preview sb-emailsig-editor__preview"><div class="sb-emailsig-editor__fit" id="sb-emailsig-style-preview">' . $html . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped in the template.
		echo '<p class="sb-emailsig-editor__who"><span class="sb-emailsig-editor__scale"></span></p>';
		echo '</div></div>';

		echo '</form>';
	}

	/**
	 * Who the preview shows. No name, so it never counts as an unsaved change.
	 * With nobody added yet, sample details.
	 */
	private static function people_select( $current ) {
		$people = get_posts(
			[
				'post_type'      => SB_EmailSig_Module::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			]
		);

		if ( ! $people ) {
			return '<span class="sb-emailsig-editor__who">Sample details</span>';
		}

		$out = '<select class="sb-emailsig-editor__person" aria-label="Preview as">';
		foreach ( $people as $id ) {
			$out .= '<option value="' . (int) $id . '" data-company="' . (int) SB_EmailSig_Company::of_person( $id ) . '"' . selected( (int) $current, (int) $id, false ) . '>' . esc_html( get_the_title( $id ) ) . '</option>';
		}

		return $out . '</select>';
	}

	/** The person a preview request asks for, if they are one; else the first. */
	private static function requested_person() {
		return self::person_or_first( isset( $_POST['person'] ) ? absint( $_POST['person'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in preview().
	}

	/** $id if it's a published person, else the first one (0 with nobody added). */
	private static function person_or_first( $id ) {
		$post = $id ? get_post( $id ) : null;

		return ( $post && $post->post_type === SB_EmailSig_Module::CPT && $post->post_status === 'publish' ) ? $id : SB_EmailSig_Templates::preview_person();
	}

	/** One text part's fields: size, weight, style, case and colour. */
	private static function item_fields( $item, array $props, array $p ) {
		$base = 's[items][' . $item . ']';

		echo '<div class="sb-emailsig-item"><div class="sb-emailsig-item__name">' . esc_html( $props['label'] ) . '</div><div class="sb-emailsig-fields">';
		self::number_field( 'Size', $base . '[size]', $p['size'], $props['size'], 8, 40, 'px' );
		self::select_field( 'Weight', $base . '[weight]', $p['weight'], self::weights(), $props['weight'] );
		self::select_field( 'Style', $base . '[style]', $p['style'], [ 'normal' => 'Normal', 'italic' => 'Italic' ], $props['style'] );
		self::select_field( 'Case', $base . '[case]', $p['case'] ?? 'none', self::cases(), $props['case'] ?? 'none' );
		self::colour_field( 'Colour', $base . '[colour]', $p['colour'], $props['colour'], ! empty( $props['fallback'] ) ? 'brand_' . $props['fallback'] : 'text' );
		if ( array_key_exists( 'line', $props ) ) {
			self::lh_field( 'Line height', $base . '[line]', $p['line'] ?? '', $props['line'], 'Default' );
		}
		echo '</div></div>';
	}

	/** A line height box: a multiple of the font size (lh()); $blank, the placeholder when it can be left empty. */
	private static function lh_field( $label, $name, $value, $default, $blank ) {
		$id = self::field_id( $name );

		echo '<div class="sb-emailsig-field sb-emailsig-field--number"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<span class="sb-emailsig-field__number"><input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" min="1" max="3" step="0.1" inputmode="decimal" data-default="' . esc_attr( (string) $default ) . '"' . ( $blank !== '' ? ' placeholder="' . esc_attr( $blank ) . '"' : '' ) . '></span></div>'; // No × after it: it read as a clear button (Sam, 31 October 2026).
	}

	/** A card; its Reset button shows (styles.js) once anything in it differs from the template's default. */
	private static function card_open( $title, $reset = true ) {
		echo '<div class="sb-tweaks-card sb-emailsig-editor__card"><div class="sb-tweaks-card__head"><h3>' . esc_html( $title ) . '</h3>';
		echo ( $reset ? '<button type="button" class="sb-emailsig-card-reset" disabled>Reset</button>' : '' ) . '</div>';
	}

	private static function card_close() {
		echo '</div>';
	}

	private static function field_id( $name ) {
		return 'sb-emailsig-' . trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ), '-' );
	}

	/**
	 * A colour: a swatch to pick with, the value (hex or var(--name)), and the
	 * theme colours. $fallback (primary or text) is what a blank one uses.
	 */
	private static function colour_field( $label, $name, $value, $default, $fallback = '' ) {
		$id          = self::field_id( $name );
		$brand       = strpos( $fallback, 'brand_' ) === 0 ? ( self::BRAND[ substr( $fallback, 6 ) ][0] ?? '' ) : '';
		$placeholder = $brand !== '' ? $brand : ( $fallback !== '' ? 'Default' : '' );

		echo '<div class="sb-emailsig-field sb-emailsig-field--colour"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		// The framework's colour field (swatch and text box), plus the theme colours.
		echo '<span class="sb-tweaks-colour sb-emailsig-colour" data-fallback="' . esc_attr( $fallback ) . '">';
		echo '<input type="color" class="sb-tweaks-colour__swatch" value="#ffffff" aria-label="' . esc_attr( $label ) . '">';
		echo '<input type="text" class="sb-tweaks-colour__value code" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-default="' . esc_attr( $default ) . '" placeholder="' . esc_attr( $placeholder ) . '" spellcheck="false" autocomplete="off">';
		echo '<button type="button" class="sb-emailsig-colour__theme" aria-label="Theme colours" title="Theme colours" hidden><span class="dashicons dashicons-art" aria-hidden="true"></span></button>';
		echo '</span></div>';
	}

	/** $blank: the field can be left empty (saved as 0, shown empty). */
	private static function number_field( $label, $name, $value, $default, $min, $max, $unit, $blank = false ) {
		$id      = self::field_id( $name );
		$value   = ( $blank && ! (int) $value ) ? '' : (int) $value;
		$default = ( $blank && ! (int) $default ) ? '' : (int) $default;

		echo '<div class="sb-emailsig-field sb-emailsig-field--number"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<span class="sb-emailsig-field__number"><input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" min="' . (int) $min . '" max="' . (int) $max . '" data-default="' . esc_attr( $default ) . '"' . ( $blank ? ' placeholder="None"' : '' ) . '><span>' . esc_html( $unit ) . '</span></span></div>';
	}

	/**
	 * Fields that only count for one value of another field, such as Corner
	 * radius for Rounded corners. Close with echo '</div>'; styles.js shows
	 * them while the other field has that value.
	 */
	private static function when( $name, $value ) {
		echo '<div class="sb-emailsig-when" data-when-name="' . esc_attr( $name ) . '" data-when-value="' . esc_attr( $value ) . '">';
	}

	private static function select_field( $label, $name, $value, array $choices, $default ) {
		$id = self::field_id( $name );

		echo '<div class="sb-emailsig-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" data-default="' . esc_attr( $default ) . '">';
		foreach ( $choices as $k => $text ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select></div>';
	}

	/* ------------------------------------------------------------------ *
	 * Save and preview
	 * ------------------------------------------------------------------ */

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do that.' );
		}

		check_admin_referer( 'sb_emailsig_save_styles' );

		$id  = isset( $_POST['sb_emailsig_template'] ) ? sanitize_key( wp_unslash( $_POST['sb_emailsig_template'] ) ) : '';
		$tpl = SB_EmailSig_Templates::get( $id );

		if ( $tpl ) {
			$id = $tpl['id'];

			// The template's name: blank goes back to its design's.
			SB_EmailSig_Templates::save(
				$tpl,
				self::sanitize( $tpl, self::with_layout( $tpl, isset( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : [], ! empty( $_POST['sb_emailsig_reset_other'] ) ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : ''
			);

			if ( isset( $_POST['template_note'] ) ) {
				SB_EmailSig_Templates::set_note( $tpl, wp_unslash( $_POST['template_note'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned in clean_note().
			}

			// Icons drawn in the browser with these styles.
			self::save_icons( $id, isset( $_POST['sb_emailsig_icons'] ) ? json_decode( wp_unslash( $_POST['sb_emailsig_icons'] ), true ) : null ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

			SB_EmailSig_Log::add( 'styles', 'template', (int) $id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-templates&edit=' . rawurlencode( $id ) . '&saved=1' ) );
		exit;
	}

	/**
	 * Posted styles from Edit styles, which has no layout since the layout
	 * got its own page, and no colours or font since they joined it (Layout
	 * Settings): the template's own added, so a save there keeps them.
	 * $reset (Reset to defaults with the Layout page ticked): the design's
	 * instead.
	 */
	private static function with_layout( array $tpl, $posted, $reset = false ) {
		$posted = is_array( $posted ) ? $posted : [];

		if ( ! isset( self::defaults( $tpl )['layout'] ) ) {
			return $posted;
		}

		$values = $reset ? self::defaults( $tpl ) : self::values( $tpl );

		foreach ( self::LAYOUT_PAGE as $k ) {
			if ( ! isset( $posted[ $k ] ) && array_key_exists( $k, $values ) ) {
				$posted[ $k ] = $values[ $k ];
			}
		}

		return $posted;
	}

	/**
	 * The template's saved styles with the Layout page's posted ones over
	 * them. $reset (Reset Template with Edit styles ticked): the design's
	 * styles under them instead.
	 */
	private static function layout_page_values( array $tpl, $posted, $reset = false ) {
		$posted = is_array( $posted ) ? $posted : [];
		$values = $reset ? self::defaults( $tpl ) : self::values( $tpl );

		foreach ( self::LAYOUT_PAGE as $k ) {
			if ( isset( $posted[ $k ] ) && array_key_exists( $k, $values ) ) {
				$values[ $k ] = $posted[ $k ];
			}
		}

		return $values;
	}

	/** The Layout page's save: the layout over the template's other styles, as saved. */
	public static function save_layout() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do that.' );
		}

		check_admin_referer( 'sb_emailsig_save_layout' );

		$id  = isset( $_POST['sb_emailsig_template'] ) ? sanitize_key( wp_unslash( $_POST['sb_emailsig_template'] ) ) : '';
		$tpl = SB_EmailSig_Templates::get( $id );

		if ( ! $tpl ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-templates' ) );
			exit;
		}

		$values = self::layout_page_values( $tpl, isset( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : [], ! empty( $_POST['sb_emailsig_reset_other'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned by sanitize().

		SB_EmailSig_Templates::save( $tpl, self::sanitize( $tpl, $values ), $tpl['name'] !== $tpl['default_name'] ? $tpl['name'] : '' );

		// Icons redrawn in a new Primary colour (layout.js).
		self::save_icons( $tpl['id'], isset( $_POST['sb_emailsig_icons'] ) ? json_decode( wp_unslash( $_POST['sb_emailsig_icons'] ), true ) : null ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		SB_EmailSig_Log::add( 'layout', 'template', (int) $tpl['id'] );
		wp_safe_redirect( add_query_arg( 'saved', '1', self::layout_url( $tpl['id'] ) ) );
		exit;
	}

	/** Live preview: the signature with the posted (unsaved) styles. */
	public static function preview() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'sb_emailsig_preview', 'nonce', false ) ) {
			wp_send_json_error( 'Not allowed.' );
		}

		$id  = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';
		$tpl = SB_EmailSig_Templates::get( $id );

		if ( ! $tpl ) {
			wp_send_json_error( 'Unknown template.' );
		}

		$posted = isset( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		// The Layout page sends only its own styles: the others as saved.
		if ( isset( $_POST['scope'] ) && $_POST['scope'] === 'layout' ) {
			$posted = self::layout_page_values( $tpl, $posted );
		}

		$values = self::sanitize( $tpl, self::with_layout( $tpl, $posted ) );
		$person = self::requested_person();
		$data   = SB_EmailSig_Templates::preview_data( $person, $tpl['id'] );

		// The person picked is remembered for this user, saved or not, so the
		// page opens on them next time.
		if ( $person && isset( $_POST['person'] ) && absint( $_POST['person'] ) === $person && (int) get_user_meta( get_current_user_id(), self::PERSON_META, true ) !== $person ) {
			update_user_meta( get_current_user_id(), self::PERSON_META, $person );
		}

		wp_send_json_success( SB_EmailSig_Render::html_from_data( $data, $tpl['id'], $values ) );
	}
}
