<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block editor styles on pages Bricks renders.
 *
 * Bricks dequeues wp-block-library and global-styles on every page it renders
 * (Setup::deregister_styles(), wp_print_styles priority 100), which is right
 * for pages built in Bricks and wrong for a post whose content is Gutenberg but
 * which displays through a Bricks single template: galleries lose their
 * columns and wrapping, images their cropping, blocks their spacing. There is
 * no Bricks setting for it.
 *
 * Straight after Bricks, on a singular page whose content has blocks, this
 * enqueues the stylesheet of each core block the content uses (the per-block
 * files WordPress registers as wp-block-<name>), the small shared block
 * stylesheet, and the layout rules that make flex layouts wrap. The full
 * global stylesheet is skipped: on a Bricks site it is mostly colour presets
 * running to 100 KB or more, so the presets are only added when the content
 * actually uses one. Pages with no block content are left exactly as lean as
 * Bricks makes them, and nothing happens on a page where Bricks did not strip
 * the library in the first place.
 *
 * It also switches on the Block spacing control for the gallery block alone,
 * through the theme.json data filter: Bricks ships no theme.json, so the
 * editor otherwise offers the gallery padding only. The gap that control saves
 * is then written by gallery_gap_css(), since WordPress leaves that out too.
 */
class SB_Bricks_Gutenberg_Styles {

	/** Guards the walk into reusable blocks. */
	const MAX_DEPTH = 5;

	public static function boot() {
		add_filter( 'wp_theme_json_data_theme', [ __CLASS__, 'gallery_gap' ] );

		if ( is_admin() ) {
			// The site-wide gallery gap and the Bricks variables it uses, in the editor.
			add_action( 'enqueue_block_assets', [ __CLASS__, 'editor_styles' ] );

			return;
		}

		// Straight after Bricks removes the library, before anything prints.
		add_action( 'wp_print_styles', [ __CLASS__, 'restore' ], 101 );

		// The gap itself, which WordPress leaves out on a theme like Bricks.
		add_filter( 'render_block_core/gallery', [ __CLASS__, 'gallery_gap_css' ], 20, 2 );
	}

	/**
	 * Write the gap a gallery was given in the editor.
	 *
	 * The gallery's own render only sets --wp--style--unstable-gallery-gap,
	 * which sizes the images to leave room for the gap. The gap property is
	 * written by the layout support, and only when the theme declares block
	 * spacing at the top level of its theme.json. Bricks has no theme.json, so
	 * the images shrank for a gap that never appeared. This adds the rule to the
	 * same block supports stylesheet, on the gallery's own class.
	 */
	public static function gallery_gap_css( $content, $block ) {
		$gap = $block['attrs']['style']['spacing']['blockGap'] ?? null;

		if ( $gap === null || $gap === '' || $gap === [] || ! function_exists( 'wp_style_engine_get_stylesheet_from_css_rules' ) ) {
			return $content;
		}

		if ( ! preg_match( '/\bwp-block-gallery-\d+\b/', (string) $content, $class ) ) {
			return $content;
		}

		// A single value, or top for rows and left for columns.
		$row    = self::gap_value( is_array( $gap ) ? ( $gap['top'] ?? '' ) : $gap );
		$column = self::gap_value( is_array( $gap ) ? ( $gap['left'] ?? '' ) : $gap );

		if ( $row === '' && $column === '' ) {
			return $content;
		}

		$row    = $row !== '' ? $row : $column;
		$column = $column !== '' ? $column : $row;

		wp_style_engine_get_stylesheet_from_css_rules(
			[
				[
					'selector'     => '.wp-block-gallery.' . $class[0],
					'declarations' => [ 'gap' => $row === $column ? $row : $row . ' ' . $column ],
				],
			],
			[ 'context' => 'block-supports' ]
		);

		return $content;
	}

	/** A saved gap as CSS: a preset becomes its variable, anything odd is dropped. */
	private static function gap_value( $value ) {
		$value = trim( (string) $value );

		if ( $value === '' ) {
			return '';
		}

		if ( strpos( $value, 'var:preset|' ) === 0 ) {
			$parts = array_filter( array_map( 'sanitize_key', explode( '|', substr( $value, 11 ) ) ) );

			return $parts ? 'var(--wp--preset--' . implode( '--', $parts ) . ')' : '';
		}

		if ( strpbrk( $value, '{};<>"\'' ) !== false ) {
			return '';
		}

		return preg_match( '/^(?:0|[0-9.]+(?:px|em|rem|%|vw|vh|vmin|vmax|ch)|(?:calc|clamp|min|max|var)\(.+\))$/', $value ) ? $value : '';
	}

	/** The Block spacing control on the gallery block. */
	public static function gallery_gap( $theme_json ) {
		if ( ! is_object( $theme_json ) || ! method_exists( $theme_json, 'update_with' ) ) {
			return $theme_json;
		}

		return $theme_json->update_with(
			[
				'version'  => class_exists( 'WP_Theme_JSON' ) && defined( 'WP_Theme_JSON::LATEST_SCHEMA' ) ? WP_Theme_JSON::LATEST_SCHEMA : 3,
				'settings' => [
					'blocks' => [
						'core/gallery' => [
							'spacing' => [
								'blockGap'            => true,
								// The plain control, with link sides, rather than a slider
								// through WordPress's generic sizes.
								'defaultSpacingSizes' => false,
								'units'               => [ 'px', '%', 'em', 'rem', 'vw', 'vh' ],
							],
						],
					],
				],
			]
		);
	}

	public static function restore() {
		if ( ! is_singular() || function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
			return;
		}

		// Bricks left the library alone on this page, so it is all there already.
		if ( wp_style_is( 'wp-block-library', 'enqueued' ) ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || ! has_blocks( $post->post_content ) ) {
			return;
		}

		$names = [];
		self::collect( parse_blocks( $post->post_content ), $names, 0 );

		if ( ! $names ) {
			return;
		}

		self::enqueue( array_keys( $names ), $post->post_content );
	}

	/** The core blocks used, walking inner blocks and reusable blocks. */
	private static function collect( array $blocks, array &$names, $depth ) {
		foreach ( $blocks as $block ) {
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';

			if ( $name !== '' ) {
				$names[ $name ] = true;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::collect( $block['innerBlocks'], $names, $depth );
			}

			// A reusable block keeps its content in another post.
			if ( $name === 'core/block' && ! empty( $block['attrs']['ref'] ) && $depth < self::MAX_DEPTH ) {
				$ref = get_post( (int) $block['attrs']['ref'] );

				if ( $ref && $ref->post_status === 'publish' ) {
					self::collect( parse_blocks( $ref->post_content ), $names, $depth + 1 );
				}
			}
		}
	}

	/**
	 * The shared block styles, each used block's own, and the layout rules.
	 *
	 * Kept separate from the checks above so it can be exercised directly.
	 */
	public static function enqueue( array $names, $content = '' ) {
		$version = get_bloginfo( 'version' );

		// Alignments, font size and colour classes, screen reader text.
		wp_register_style( 'sb-bricks-block-common', includes_url( 'css/dist/block-library/common.min.css' ), [], $version );
		wp_enqueue_style( 'sb-bricks-block-common' );

		foreach ( $names as $name ) {
			if ( strpos( $name, 'core/' ) !== 0 ) {
				continue;
			}

			$handle = 'wp-block-' . substr( $name, 5 );

			if ( wp_style_is( $handle, 'registered' ) ) {
				wp_enqueue_style( $handle );
			}
		}

		$css = self::default_gap_css() . self::layout_css();

		/**
		 * The colour and size presets only when the content uses one, since on
		 * a site with a big palette they dwarf everything else here.
		 */
		if ( function_exists( 'wp_get_global_stylesheet' ) && preg_match( '/\bhas-[a-z0-9-]+-(?:color|background-color|border-color|font-size|gradient-background|font-family)\b|var:preset\|/', (string) $content ) ) {
			$css = (string) wp_get_global_stylesheet( [ 'variables', 'presets' ] ) . $css;
		}

		if ( $css !== '' ) {
			wp_register_style( 'sb-bricks-block-layout', false, [], $version );
			wp_add_inline_style( 'sb-bricks-block-layout', $css );
			wp_enqueue_style( 'sb-bricks-block-layout' );
		}
	}

	/**
	 * The Bricks theme style's Image Gallery spacing, as the default gallery gap.
	 *
	 * WordPress reads --wp--style--gallery-gap-default first whenever a gallery
	 * has no gap of its own, for both the gap and the image widths. Setting it to
	 * the Bricks value means every Gutenberg gallery matches every Bricks gallery,
	 * and changing it in the theme style changes both. A gap set on one gallery
	 * in the editor still wins. Only the base value is used; per-breakpoint
	 * values in the theme style are not carried across.
	 */
	public static function default_gap() {
		$value = null;

		// On the front end Bricks has already worked out which style applies.
		if ( class_exists( '\Bricks\Theme_Styles' ) && ! empty( \Bricks\Theme_Styles::$settings_by_id ) ) {
			$value = \Bricks\Theme_Styles::get_setting_by_key( 'image-gallery', 'gutter' );
		}

		// In the editor it has not, so take the site-wide style, or the only one.
		if ( $value === null ) {
			$styles = (array) get_option( defined( 'BRICKS_DB_THEME_STYLES' ) ? BRICKS_DB_THEME_STYLES : 'bricks_theme_styles', [] );
			$pick   = null;

			foreach ( $styles as $style ) {
				foreach ( (array) ( $style['settings']['conditions']['conditions'] ?? [] ) as $condition ) {
					if ( ( $condition['main'] ?? '' ) === 'any' ) {
						$pick = $style;
					}
				}
			}

			if ( $pick === null && count( $styles ) === 1 ) {
				$pick = reset( $styles );
			}

			$value = $pick['settings']['image-gallery']['gutter'] ?? null;
		}

		// A bare number is pixels, the way Bricks treats it.
		if ( is_numeric( $value ) ) {
			$value = $value . 'px';
		}

		return (string) apply_filters( 'sb_bricks/gutenberg_styles/default_gallery_gap', self::gap_value( (string) $value ) );
	}

	/** The default gap as a rule, or nothing when there is none. */
	private static function default_gap_css() {
		$gap = self::default_gap();

		return $gap === '' ? '' : ':root{--wp--style--gallery-gap-default:' . $gap . ';}';
	}

	/**
	 * The same default in the editor, with the Bricks variables it relies on,
	 * so a gallery looks in the editor the way it will on the page.
	 */
	public static function editor_styles() {
		$css = self::default_gap_css();

		if ( $css === '' ) {
			return;
		}

		$uploads   = wp_get_upload_dir();
		$variables = trailingslashit( $uploads['basedir'] ) . 'bricks/css/global-variables.min.css';

		if ( file_exists( $variables ) ) {
			wp_enqueue_style( 'sb-bricks-bricks-variables', trailingslashit( $uploads['baseurl'] ) . 'bricks/css/global-variables.min.css', [], (string) filemtime( $variables ) );
		}

		wp_register_style( 'sb-bricks-gallery-gap', false, [], null );
		wp_add_inline_style( 'sb-bricks-gallery-gap', $css );
		wp_enqueue_style( 'sb-bricks-gallery-gap' );
	}

	/**
	 * The layout rules alone: what makes flex and grid layouts display, wrap
	 * and space, and each block's default gap, gallery included.
	 *
	 * On a theme without a theme.json these sit in the global stylesheet's
	 * styles part, which also carries body margin and padding resets, the
	 * default grey button and pullquote sizing. None of that should reach a
	 * Bricks site, so only the rules whose selector names a layout are kept.
	 */
	public static function layout_css() {
		if ( ! function_exists( 'wp_get_global_stylesheet' ) ) {
			return '';
		}

		$styles = (string) wp_get_global_stylesheet( [ 'styles' ] );

		if ( ! preg_match_all( '/[^{}]+\{[^{}]*\}/', $styles, $rules ) ) {
			return '';
		}

		$keep = '';

		foreach ( $rules[0] as $rule ) {
			$selector = substr( $rule, 0, strpos( $rule, '{' ) );

			if ( strpos( $selector, 'is-layout-' ) !== false ) {
				$keep .= trim( $rule );
			}
		}

		return $keep;
	}
}
