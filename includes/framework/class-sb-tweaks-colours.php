<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site's colours, for every colour field in SocialBUMP Tweaks (Sam,
 * 3 November 2026): the Theme colours menu beside each field, and the hex
 * of any colour variable the site defines.
 *
 * Moved here from Email Signatures, which built the first menu and now asks
 * this class for its list. The field itself is one component everywhere:
 * SB_Tweaks_Fields::colour() draws it, assets/js/colour.js (SBTweaksColour,
 * registered here as sb-tweaks-colour with its stylesheet) runs it, and
 * data_tag() leaves the site's colours on the page for its menu.
 *
 * Admin pages don't load the front end's colour variables, so CSS that runs
 * in the admin uses css() for a saved colour: a variable keeps its name and
 * gets its hex as the fallback.
 */
class SB_Tweaks_Colours {

	/** The menu, once worked out this request. */
	private static $palette = null;

	/** Every colour variable known, shades too: [ 'var(--name)' => '#rrggbb' ]. */
	private static $colours = [];

	/** The data goes on a page once, however many fields it has. */
	private static $printed = false;

	/**
	 * The site's theme colours: [ [ name, value, hex, group ], ... ], value
	 * being what is saved (var(--name), or the hex for a colour with no
	 * variable) and group the menu heading it sits under.
	 *
	 * Read from, in order: Bricks' colour palettes (Advanced Themer's and
	 * Core Framework's land there on a Bricks site), Core Framework (its
	 * preset for the names, its generated CSS for every variable), Elementor's
	 * kit (system and custom colours, var(--e-global-color-<id>)), then the
	 * theme.json palette. A colour already listed isn't listed again.
	 * Filterable through sb_tweaks/palette. Shades (var(--primary-d-4) and
	 * so on) stay out of the menu, to keep it short, but can still be typed
	 * in: see colours().
	 */
	public static function palette() {
		if ( self::$palette !== null ) {
			return self::$palette;
		}

		$out   = [];
		$all   = [];
		$group = '';
		$add   = function ( $name, $value, $raw, $menu = true ) use ( &$out, &$all, &$group ) {
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
					'group' => $group,
				];
			}
		};

		foreach ( (array) get_option( 'bricks_color_palette', [] ) as $pal ) {
			$group = 'Bricks' . ( ! empty( $pal['name'] ) ? ': ' . $pal['name'] : '' );

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

		$cf = self::core_framework();

		foreach ( $cf['groups'] as $name => $vars ) {
			$group = 'Core Framework' . ( $name !== '' ? ': ' . $name : '' );

			foreach ( $vars as $v ) {
				$add( substr( $v, 2 ), 'var(' . $v . ')', self::cf_value( $cf['vars'][ $v ] ?? '', $cf['vars'] ) );
			}
		}

		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$kit = (int) get_option( 'elementor_active_kit' );
			$set = $kit ? get_post_meta( $kit, '_elementor_page_settings', true ) : [];

			foreach ( [ 'system_colors' => 'Elementor: System Colours', 'custom_colors' => 'Elementor: Custom Colours' ] as $key => $label ) {
				$group = $label;

				foreach ( (array) ( is_array( $set ) ? ( $set[ $key ] ?? [] ) : [] ) as $c ) {
					if ( ! is_array( $c ) || empty( $c['color'] ) || ! preg_match( '/^[a-z0-9_-]+$/i', (string) ( $c['_id'] ?? '' ) ) ) {
						continue;
					}

					$add( ! empty( $c['title'] ) ? $c['title'] : $c['_id'], 'var(--e-global-color-' . strtolower( $c['_id'] ) . ')', (string) $c['color'] );
				}
			}
		}

		if ( function_exists( 'wp_get_global_settings' ) ) {
			$group = 'Theme';
			$theme = (array) wp_get_global_settings( [ 'color', 'palette', 'theme' ] );

			foreach ( $theme as $c ) {
				if ( empty( $c['slug'] ) || empty( $c['color'] ) ) {
					continue;
				}

				$add( $c['name'] ?? $c['slug'], 'var(--wp--preset--color--' . sanitize_key( $c['slug'] ) . ')', (string) $c['color'] );
			}
		}

		// Core Framework's shades, tints and other colour variables: typed in, not listed.
		foreach ( $cf['vars'] as $v => $raw ) {
			$add( '', 'var(' . $v . ')', self::cf_value( $raw, $cf['vars'] ), false );
		}

		$out = array_slice( (array) apply_filters( 'sb_tweaks/palette', $out ), 0, 120 );

		foreach ( $out as $c ) {
			if ( isset( $c['value'], $c['hex'] ) && ! isset( $all[ $c['value'] ] ) ) {
				$all[ $c['value'] ] = $c['hex'];
			}
		}

		self::$colours = $all;
		self::$palette = $out;

		return $out;
	}

	/**
	 * Core Framework's colours, when it's active: [ 'vars' => [ '--name' =>
	 * value ], 'groups' => [ group name => [ '--name', ... ] ] ]. Every
	 * variable comes from its generated CSS (uploads/core-framework/css),
	 * the first :root value of each; the groups and the colours in them
	 * from the preset in use (its own table), leaving out colours that just
	 * point at another (var(--primary)). The preset fills in a variable the
	 * CSS file doesn't have.
	 */
	private static function core_framework() {
		$out    = [ 'vars' => [], 'groups' => [] ];
		$active = (array) get_option( 'active_plugins', [] );

		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}

		$on = false;
		foreach ( $active as $p ) {
			if ( substr( (string) $p, -19 ) === '/core-framework.php' ) {
				$on = true;
				break;
			}
		}

		if ( ! $on ) {
			return $out;
		}

		$up   = wp_upload_dir( null, false );
		$file = trailingslashit( $up['basedir'] ) . 'core-framework/css/core_framework.css';
		$css  = is_readable( $file ) ? (string) file_get_contents( $file ) : '';

		if ( preg_match_all( '/:root\s*\{([^}]*)\}/i', $css, $blocks ) ) {
			foreach ( $blocks[1] as $b ) {
				if ( preg_match_all( '/(--[a-z0-9_-]+)\s*:\s*([^;]+)/i', $b, $m, PREG_SET_ORDER ) ) {
					foreach ( $m as $d ) {
						$k = strtolower( $d[1] );

						if ( ! isset( $out['vars'][ $k ] ) ) {
							$out['vars'][ $k ] = trim( $d[2] );
						}
					}
				}
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . 'core_framework_presets';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return $out;
		}

		$main = get_option( 'core_framework_main' );
		$id   = is_array( $main ) ? (string) ( $main['selected_id'] ?? '' ) : '';
		$data = $id !== '' ? $wpdb->get_var( $wpdb->prepare( "SELECT data FROM {$table} WHERE id = %s", $id ) ) : null;

		if ( ! $data ) {
			$data = $wpdb->get_var( "SELECT data FROM {$table} LIMIT 1" );
		}

		$json = json_decode( (string) $data, true );
		$name = function ( $n ) {
			$n = strtolower( trim( (string) $n ) );

			return preg_match( '/^[a-z0-9_-]+$/', $n ) ? '--' . $n : '';
		};

		foreach ( (array) ( $json['modulesData']['COLOR_SYSTEM']['groups'] ?? [] ) as $g ) {
			foreach ( (array) ( $g['colors'] ?? [] ) as $c ) {
				$k = $name( $c['name'] ?? '' );
				$v = trim( (string) ( $c['value'] ?? '' ) );

				if ( $k === '' || $v === '' ) {
					continue;
				}

				if ( ! isset( $out['vars'][ $k ] ) ) {
					$out['vars'][ $k ] = $v;
				}

				foreach ( [ 'shades', 'tints' ] as $more ) {
					foreach ( (array) ( $c[ $more ] ?? [] ) as $sh ) {
						$sk = $name( $sh['name'] ?? '' );

						if ( $sk !== '' && ! empty( $sh['value'] ) && ! isset( $out['vars'][ $sk ] ) ) {
							$out['vars'][ $sk ] = trim( (string) $sh['value'] );
						}
					}
				}

				if ( stripos( $v, 'var(' ) !== 0 && ! in_array( $k, $out['groups'][ (string) ( $g['name'] ?? '' ) ] ?? [], true ) ) {
					$out['groups'][ (string) ( $g['name'] ?? '' ) ][] = $k;
				}
			}
		}

		return $out;
	}

	/** A value that points at another variable (var(--primary), with or without a fallback) followed to the end. */
	private static function cf_value( $v, array $vars, $depth = 0 ) {
		$v = trim( (string) $v );

		if ( $depth < 6 && preg_match( '/^var\(\s*(--[a-z0-9_-]+)\s*(?:,\s*(.+))?\)$/i', $v, $m ) ) {
			$k = strtolower( $m[1] );

			if ( isset( $vars[ $k ] ) ) {
				return self::cf_value( $vars[ $k ], $vars, $depth + 1 );
			}

			return isset( $m[2] ) ? self::cf_value( $m[2], $vars, $depth + 1 ) : '';
		}

		return $v;
	}

	/** Every colour variable known, shades too, as [ 'var(--name)' => '#rrggbb' ]. */
	public static function colours() {
		self::palette();

		return self::$colours;
	}

	/**
	 * Any saved colour as #rrggbb: a hex, rgb() or hsl(), or a variable this
	 * site defines (with or without a fallback; --name on its own counts).
	 * '' when it can't be worked out.
	 */
	public static function hex( $value ) {
		$v = strtolower( trim( (string) $value ) );

		if ( preg_match( '/^--[a-z0-9_-]+$/', $v ) ) {
			$v = 'var(' . $v . ')';
		}

		if ( preg_match( '/^var\(\s*(--[a-z0-9_-]+)\s*(?:,\s*(.+))?\)$/', $v, $m ) ) {
			$all = self::colours();

			if ( isset( $all[ 'var(' . $m[1] . ')' ] ) ) {
				return $all[ 'var(' . $m[1] . ')' ];
			}

			return isset( $m[2] ) ? self::hex( $m[2] ) : '';
		}

		return self::to_hex( $v );
	}

	/**
	 * A saved colour for CSS that may run where the site's variables aren't
	 * loaded (the admin): var(--name) becomes var(--name, #hex) when the hex
	 * is known. Anything else comes back as it is.
	 */
	public static function css( $value ) {
		$value = (string) $value;

		if ( preg_match( '/^var\(\s*(--[A-Za-z0-9_-]+)\s*\)$/', $value, $m ) ) {
			$hex = self::hex( $value );

			if ( $hex !== '' ) {
				return 'var(' . $m[1] . ', ' . $hex . ')';
			}
		}

		return $value;
	}

	public static function boot() {
		// Registered early, so any page or module can ask for them by handle.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'register_assets' ], 1 );
	}

	/**
	 * The colour field's script and stylesheet, as sb-tweaks-colour. A page
	 * with colour fields lists them as dependencies: the framework's
	 * stylesheet and framework.js do, and so do Email Signatures' own.
	 */
	public static function register_assets() {
		foreach ( [ 'js' => 'assets/js/colour.js', 'css' => 'assets/css/colour.css' ] as $type => $file ) {
			$ver = SB_TWEAKS_VERSION . ( file_exists( SB_TWEAKS_PATH . $file ) ? '.' . filemtime( SB_TWEAKS_PATH . $file ) : '' );

			if ( $type === 'js' ) {
				wp_register_script( 'sb-tweaks-colour', SB_TWEAKS_URL . $file, [], $ver, true );
			} else {
				wp_register_style( 'sb-tweaks-colour', SB_TWEAKS_URL . $file, [], $ver );
			}
		}
	}

	/**
	 * The menu and the colours for colour.js, as JSON in a script tag it reads
	 * (#sb-tweaks-palette). Once per page: '' after the first time, and when
	 * the site has no colours to offer.
	 */
	public static function data_tag() {
		if ( self::$printed ) {
			return '';
		}

		self::$printed = true;

		$palette = self::palette();

		if ( ! $palette ) {
			return '';
		}

		return '<script type="application/json" id="sb-tweaks-palette">' . wp_json_encode( [ 'palette' => $palette, 'colours' => self::colours() ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
	}

	/** data_tag(), printed. */
	public static function print_data() {
		echo self::data_tag(); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON with tags, quotes and ampersands escaped.
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
}
