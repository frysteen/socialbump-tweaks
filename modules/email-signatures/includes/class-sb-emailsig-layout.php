<?php
/**
 * Template layouts (the layout editor, 29 October 2026).
 *
 * A layout is kept with the template's styles, as styles['layout'], so it is
 * saved, previewed, duplicated, exported and imported with them. Its design
 * gives the default (styles.php, 'layout'). Shape, keys always in this order
 * (assets/layout.js writes the same, so an unchanged layout reads unchanged):
 *
 *   rows       (Sam, 31 October 2026; before, one row's columns, gaps and
 *              valign sat at the top level, read as Row 1) 1 to MAX_ROWS,
 *              stacked, each: [
 *     columns  1 to 5 (3 before 30 October 2026): [ width (px, 0 for as wide as its blocks), valign
 *              ('' for the row's), top and bottom (px of space inside
 *              the column, so a line beside it runs past its blocks), blocks ]
 *     gaps     always four (two before 30 October 2026), between each
 *              column and the next: [ space
 *              (px each side of a line, or between the columns), line (its
 *              thickness in px, 0 for none; 0 or 1 before 29 October 2026,
 *              which read the same), line_style, colour ]
 *     valign   top, middle or bottom: its columns' default
 *     padding, border, background: the row's own box, as the Container's ]
 *   row_gaps   one between each row and the next, as a column gap
 *              (space each side of a line, or between the rows)
 *   block_gap  px between blocks in a column (a block's space overrides it)
 *   padding    px inside the border
 *   border     top, right, bottom, left (px), colour ('' for the lines colour)
 *   background behind the columns ('' for none)
 *   line_below the line above the buttons, footer text and banners (the
 *              Footer Divider card): its width (thickness) in px, 0 for none
 *   line_colour its colour ('' for the lines colour)
 *   line_space px above it
 *   hold       the holding area: blocks that don't show
 *
 * Spacing and lines (29 October 2026, Sam): block_gap is the Default
 * Spacing and line_width the Default Line Width. Every space (a block's
 * space above, name and Info List spacing, a column's top and bottom, a
 * gap's space, padding, line_space) is a step of the default ('default',
 * 'double', 'half', 'quarter', 'eighth') or a number of px (0 for none);
 * px() works it out. Every line (a gap's line, a divider, line_below) is
 * 'default' or a number of px (0 for none, but a divider has at least 1);
 * lpx() works it out. Saved numbers stay as they were (custom), and a
 * block's space of -1 (before steps) reads as 'default'.
 *
 * Line styles (Sam, 29 October 2026): line_style is the Default Line Style
 * (solid or double); a gap's line, the border, a divider and the footer
 * line (line_below_style) are 'default' or a style of their own. Dashed
 * and dotted went on 31 October 2026 (Sam): they can only be borders, and
 * the Gmail phone app broke them. Saved ones read as solid.
 * Lines and the border are at most 8px: Outlook for Windows draws no border
 * thicker than that.
 *
 * Footer Controls (Sam, 29 October 2026): hide_buttons, hide_banners,
 * hide_campaign and hide_text (1 to leave that part out of signatures
 * using this template). hide_signoff (Container Settings, Sam, 30 October
 * 2026) does the same for the sign-off above it all.
 *
 * A block: type, id (Info List and Divider only, as there can be several),
 * align (left, center, right), space (px above it, -1 for block_gap), then
 * its own options (block_options()). Every single block (photo, name and the
 * rest) is in the layout exactly once, in a column or the holding area.
 *
 * An Info List's items: phone, mobile, email, website, address, each of the
 * company's Additional Info List Items on its own (c:<row id>, Settings,
 * Company tab) and info_person (the person's own, which differ person to
 * person, so they go as one). A company item added in Settings later turns
 * up ticked in the Info List holding the company's items (the first in a
 * column with one ticked), after them, and unticked in the others; one
 * deleted drops out. Layouts saved on 29 October 2026 before the items
 * went one by one have info_company (all of them), read as each of them.
 *
 * Settings decides what exists, the layout where it shows. The sign-off
 * always sits above the columns; buttons, footer text, the campaign banner
 * and banners always sit below them.
 *
 * Email clients: tables and inline styles only. Columns never stack on
 * phones (Outlook ignores the CSS that would), so keep them few and narrow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Layout {

	/**
	 * The thinnest a double line is (lwidth()): two lines and the gap
	 * between need a pixel each (Sam, 31 October 2026). The editor sets a
	 * thinner one to this; lwidth() makes sure of it.
	 */
	const DOUBLE_MIN = 3;

	/** Blocks that can be added more than once, and how many of each. */
	const MULTI     = [ 'info', 'divider', 'text' ];
	const MAX_MULTI = 6;

	/**
	 * Columns a row can have: added and removed one at a time (Sam, 31
	 * October 2026: no maximum; 12 keeps a layout sane). GAP_PAD: gaps kept
	 * for a row, never fewer than 4, as when a row had up to 5 columns.
	 */
	const MAX_COLUMNS = 12;
	const GAP_PAD     = 4;

	/** Rows a layout can have (Sam, 31 October 2026). */
	const MAX_ROWS = 12;

	/**
	 * Colours a row or block can have of its own (Sam, 31 October 2026),
	 * each '' to follow the one above (block, then row, then template):
	 * a row's text, accent (labels, letters and separators) and icons;
	 * blocks their own parts.
	 */
	const COLOURS = [
		'row'     => [
			'text'   => '',
			'accent' => '',
			'icons'  => '',
		],
		'name'    => [
			'name'           => '',
			'qualifications' => '',
			'job'            => '',
			'accent'         => '',
		],
		'company' => [ 'text' => '' ],
		'info'    => [
			'text'   => '',
			'accent' => '',
			'icons'  => '',
		],
		'vcard'   => [
			'text'  => '',
			'icons' => '',
		],
		'social'  => [
			'heading' => '',
			'icons'   => '',
			'bg'      => '',
		],
		'text'    => [
			'title' => '',
			'text'  => '',
			'links' => '',
		],
	];

	/** What a Text block's title and text can have of their own: blank (0 or '') follows the template. */
	const TEXT_PARTS = [ 'title', 'text' ];

	/** The merge tags a Text block fills in (Sam, 31 October 2026). */
	const TAGS = [ 'first_name', 'last_name', 'name', 'job_title', 'qualifications', 'company', 'phone', 'mobile', 'email', 'website' ];

	/** Spacing steps, as parts of the Default Spacing. */
	const STEPS = [
		'default' => 1,
		'double'  => 2,
		'half'    => 0.5,
		'quarter' => 0.25,
		'eighth'  => 0.125,
	];

	/** Line styles. */
	const STYLES = [
		'solid'  => 'Solid',
		'double' => 'Double',
	];

	/** Styles there were once (dashed, dotted): saved ones read as solid. */
	const OLD_STYLES = [ 'dashed', 'dotted' ];

	/** The thickest line or border: Outlook for Windows draws none thicker. */
	const MAX_LINE = 8;

	/** The layout being drawn (for its default spacing and line width). */
	private static $layout = [];

	/** The row being drawn (its colours), and the variant icon sets its block draws with ('' for the usual). */
	private static $row_now = [];

	/** The colours blocks follow while a column is drawn: its own, else its row's. */
	private static $colours_now = [];
	private static $variant = [
		'contact' => '',
		'social'  => '',
	];

	/** One render's data, styles and template (html() sets them). */
	private static $d   = [];
	private static $s   = [];
	private static $tpl = [];

	/** While sanitize() runs: company items new to the layout, and which Info List takes them. */
	private static $fresh = [];
	private static $info  = 0;
	private static $takes = -1;

	/** Block types: type => label. */
	public static function blocks() {
		return [
			'photo'      => 'Photo',
			'name'       => 'Name and job title',
			'company'    => 'Company name',
			'logo'       => 'Logo',
			'logo_small' => 'Small logo',
			'info'       => 'Info List',
			'vcard'      => 'Save my contact details',
			'social'     => 'Social icons',
			'divider'    => 'Divider',
			'text'       => 'Text',
		];
	}

	/**
	 * What an Info List can hold: key => label. The company's Additional
	 * Info List Items one by one (c:<row id>, labelled as in Settings), then
	 * the person's own as one.
	 */
	public static function items() {
		$out = [
			'phone'   => 'Phone',
			'mobile'  => 'Mobile',
			'email'   => 'Email',
			'website' => 'Website',
			'address' => 'Address',
		];

		foreach ( self::company_items() as $ref => $item ) {
			$out[ 'c:' . $ref ] = $item['label'];
		}

		return $out + [ 'info_person' => 'Person\'s own Additional Info List Items' ];
	}

	/**
	 * What a layout leaves out, for the person page's preview (Sam, 30
	 * October 2026): blocks in the holding area (not dividers), Info List
	 * items no Info List in a column has ticked, and the footer parts Footer
	 * Controls hide. Labels, in that order.
	 */
	public static function disabled( array $layout ) {
		$blocks = self::blocks();
		$shown  = false;
		$on     = [];
		$out    = [];

		foreach ( self::all_columns( $layout ) as $col ) {
			foreach ( (array) ( $col['blocks'] ?? [] ) as $b ) {
				if ( ( $b['type'] ?? '' ) !== 'info' ) {
					continue;
				}

				$shown = true;

				foreach ( self::expand( $b['items'] ?? [] ) as $item ) {
					if ( ! empty( $item['on'] ) ) {
						$on[ (string) ( $item['key'] ?? '' ) ] = true;
					}
				}
			}
		}

		foreach ( (array) ( $layout['hold'] ?? [] ) as $b ) {
			$type = (string) ( $b['type'] ?? '' );

			// A spare Info List held back says nothing while another shows: its items are judged below.
			if ( $type === 'divider' || $type === 'text' || ! isset( $blocks[ $type ] ) || ( $type === 'info' && $shown ) ) {
				continue;
			}

			$out[ 'b:' . $type ] = $blocks[ $type ];
		}

		if ( $shown ) {
			foreach ( self::items() as $key => $label ) {
				if ( empty( $on[ $key ] ) ) {
					$out[ 'i:' . $key ] = $key === 'info_person' ? 'Their own Info List items' : $label;
				}
			}
		}

		foreach ( [ 'signoff' => 'Sign-off', 'buttons' => 'Buttons', 'text' => 'Footer text', 'campaign' => 'Campaign banner', 'banners' => 'Banners' ] as $part => $label ) {
			if ( ! empty( $layout[ 'hide_' . $part ] ) ) {
				$out[ 'f:' . $part ] = $label;
			}
		}

		return array_values( $out );
	}

	/** The company's Additional Info List Items: row id => [ label, text ]. */
	public static function company_items() {
		return class_exists( 'SB_EmailSig_Data' ) && function_exists( 'get_field' ) ? SB_EmailSig_Data::company_items() : [];
	}

	/** An Info List's items with the old info_company (all of them) read as each one. */
	private static function expand( $list ) {
		$out = [];

		foreach ( is_array( $list ) ? $list : [] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			if ( ( $item['key'] ?? '' ) === 'info_company' ) {
				foreach ( array_keys( self::company_items() ) as $ref ) {
					$out[] = [
						'key' => 'c:' . $ref,
						'on'  => empty( $item['on'] ) ? 0 : 1,
					];
				}
				continue;
			}

			$out[] = $item;
		}

		return $out;
	}

	/** Separators for things on one row: key => [ label, character ]. */
	public static function seps() {
		return [
			'bar'   => [ 'Line |', '|' ],
			'dot'   => [ 'Dot •', "\u{2022}" ],
			'mid'   => [ 'Small dot ·', "\u{00B7}" ],
			'slash' => [ 'Slash /', '/' ],
			'none'  => [ 'Space only', '' ],
		];
	}

	/** A block's own options and their defaults, in order. */
	public static function block_options( $type ) {
		switch ( $type ) {
			case 'name':
				return [
					'format'  => 'full', // How the name shows (SB_EmailSig_Names::formats(), Sam, 31 October 2026).
					'style'   => 'stack', // stack, or row: on one line.
					'sep'     => 'bar',
					'spacing' => 'quarter', // Between the lines (stacked) or either side of the separator (row).
					'colours' => self::COLOURS['name'],
				];
			case 'company':
				return [ 'colours' => self::COLOURS['company'] ];
			case 'vcard':
				return [ 'colours' => self::COLOURS['vcard'] ];
			case 'info':
				$items = [];
				foreach ( array_keys( self::items() ) as $key ) {
					$items[] = [
						'key' => $key,
						'on'  => 1,
					];
				}

				return [
					'items'   => $items,
					'style'   => 'stack',
					'sep'     => 'bar',
					'spacing' => 'default', // Between lines (stacked) or either side of the separator (row).
					'lead'    => '', // What starts each line: '' for Info List Styles, or icon, letter, label, none.
					'colours' => self::COLOURS['info'],
				];
			case 'social':
				return [
					'heading'   => 1, // The Social Media heading from Settings.
					'direction' => 'row', // row, or column: a strip down the side.
					'indent'    => 0, // Line up with the Info List's text (after its icons).
					'colours'   => self::COLOURS['social'],
				];
			case 'divider':
				return [
					'thickness'  => 'default', // The Default Line Width, or px.
					'colour'     => '', // Blank for the Lines colour.
					'line_style' => 'default', // The Default Line Style, or a style.
				];
			case 'text':
				// A title and text of its own (Sam, 31 October 2026): merge
				// tags, shortcodes and a little HTML. Each part's styles blank
				// to follow the template (the title Name's, the text the Info
				// List's).
				$out = [
					'title' => '',
					'text'  => '',
					'gap'   => 'quarter', // Between the title and the text.
				];
				foreach ( self::TEXT_PARTS as $part ) {
					$out[ $part . '_size' ]   = 0;
					$out[ $part . '_weight' ] = '';
					$out[ $part . '_style' ]  = '';
					$out[ $part . '_case' ]   = '';
					$out[ $part . '_line' ]   = '';
				}
				$out['colours'] = self::COLOURS['text'];

				return $out;
		}

		return [];
	}

	/** A block of a type with every option at its default. */
	public static function block( $type, $id = 0 ) {
		$b = [ 'type' => $type ];

		if ( in_array( $type, self::MULTI, true ) ) {
			$b['id'] = max( 1, (int) $id );
		}

		return $b + [
			'align' => 'left',
			'space' => 'default',
		] + self::block_options( $type );
	}

	/** The layout a design without one in styles.php starts with: like Classic. */
	public static function fallback() {
		$b = function ( $type, array $set = [], $id = 0 ) {
			return array_merge( self::block( $type, $id ), $set );
		};

		return [
			'columns'    => [ // A one row layout as before rows: legacy() makes it Row 1.
				[
					'width'  => 0,
					'valign' => '',
					'padding' => [ 'top' => 'double', 'right' => 0, 'bottom' => 'double', 'left' => 0 ], // Double top and bottom unless set (Sam, 31 October 2026).
					'blocks' => [ $b( 'photo' ) ],
				],
				[
					'width'  => 0,
					'valign' => '',
					'padding' => [ 'top' => 'double', 'right' => 0, 'bottom' => 'double', 'left' => 0 ], // Double top and bottom unless set (Sam, 31 October 2026).
					'blocks' => [ $b( 'name' ), $b( 'company', [ 'space' => 'half' ] ) ],
				],
				[
					'width'  => 0,
					'valign' => '', // Default (the row's), as the others (Sam, 31 October 2026).
					'padding' => [ 'top' => 'double', 'right' => 0, 'bottom' => 'double', 'left' => 0 ], // Double top and bottom unless set (Sam, 31 October 2026).
					'blocks' => [
						$b( 'logo' ),
						$b( 'info', [], 1 ), // Default spacing both (Sam, 31 October 2026).
						$b( 'vcard' ),
						$b( 'social', [ 'indent' => 1 ] ),
					],
				],
			],
			'gaps'       => [
				[
					'space' => 'double',
					'line'  => 'default',
				],
				[
					'space' => 'double',
					'line'  => 'default',
				],
			],
			'valign'     => 'middle',
			'block_gap'  => 12,
			'padding'    => 0,
			'border'     => [
				'top'    => 0,
				'right'  => 0,
				'bottom' => 0,
				'left'   => 0,
				'colour' => '',
			],
			'line_below' => 'default',
			'line_space' => 'double',
			'hold'       => [ $b( 'logo_small' ) ],
		];
	}

	/**
	 * A layout from before rows (its columns, gaps and valign at the top
	 * level) as one with them: those are Row 1.
	 */
	private static function legacy( $raw ) {
		if ( ! is_array( $raw ) || isset( $raw['rows'] ) || ! isset( $raw['columns'] ) ) {
			return $raw;
		}

		$raw['rows'] = [
			[
				'columns' => $raw['columns'],
				'gaps'    => $raw['gaps'] ?? [],
				'valign'  => $raw['valign'] ?? 'top',
			],
		];
		unset( $raw['columns'], $raw['gaps'], $raw['valign'] );

		return $raw;
	}

	/** Every column in every row, in order (a layout from before rows too). */
	public static function all_columns( array $layout ) {
		$layout = self::legacy( $layout );
		$out    = [];

		foreach ( (array) ( $layout['rows'] ?? [] ) as $row ) {
			foreach ( (array) ( is_array( $row ) ? ( $row['columns'] ?? [] ) : [] ) as $col ) {
				if ( is_array( $col ) ) {
					$out[] = $col;
				}
			}
		}

		return $out;
	}

	/** A box's border cleaned (the Container's, a row's). */
	private static function clean_border( $border ) {
		$border = is_array( $border ) ? $border : [];

		return [
			'top'        => self::num( $border['top'] ?? 0, 0, self::MAX_LINE, 0 ),
			'right'      => self::num( $border['right'] ?? 0, 0, self::MAX_LINE, 0 ),
			'bottom'     => self::num( $border['bottom'] ?? 0, 0, self::MAX_LINE, 0 ),
			'left'       => self::num( $border['left'] ?? 0, 0, self::MAX_LINE, 0 ),
			'colour'     => SB_EmailSig_Styles::clean_colour( $border['colour'] ?? '' ),
			'line_style' => self::line_style( $border['line_style'] ?? '' ),
		];
	}

	/**
	 * Padding on each side (Sam, 31 October 2026): [ top, right, bottom,
	 * left ], each a space step or px. One value (before sides) is all four.
	 */
	private static function clean_pad( $v ) {
		$out = [];

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			$out[ $side ] = self::space( is_array( $v ) ? ( $v[ $side ] ?? null ) : $v, 0 );
		}

		return $out;
	}

	/** Padding as CSS: one value when all four sides match (as before sides). */
	private static function pad_css( $pad ) {
		if ( ! is_array( $pad ) ) {
			return 'padding:' . self::px( $pad ) . 'px;';
		}

		$px = array_map( [ __CLASS__, 'px' ], [ $pad['top'] ?? 0, $pad['right'] ?? 0, $pad['bottom'] ?? 0, $pad['left'] ?? 0 ] );

		return count( array_unique( $px ) ) === 1 ? 'padding:' . $px[0] . 'px;' : 'padding:' . implode( 'px ', $px ) . 'px;';
	}

	/** Whether padding has any space at all. */
	private static function padded( $pad ) {
		foreach ( is_array( $pad ) ? $pad : [ $pad ] as $v ) {
			if ( self::px( $v ) > 0 ) {
				return true;
			}
		}

		return false;
	}

	/** Tags a Text block's title and text may have. */
	const TEXT_HTML = [
		'a'      => [
			'href'  => true,
			'title' => true,
		],
		'strong' => [],
		'b'      => [],
		'em'     => [],
		'i'      => [],
		'u'      => [],
		'br'     => [],
	];

	/** A Text block's title (one line) or text, with only TEXT_HTML's tags. */
	private static function clean_text( $v, $title ) {
		$v = str_replace( [ "\r\n", "\r" ], "\n", is_string( $v ) ? $v : '' );
		$v = $title ? trim( preg_replace( '/\s*\n\s*/', ' ', $v ) ) : trim( $v, "\n" );
		$v = function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $title ? 300 : 3000 ) : substr( $v, 0, $title ? 300 : 3000 );

		return wp_kses( $v, self::TEXT_HTML );
	}

	/** One of a Text block's part styles, blank (0 or '') to follow the template. */
	private static function text_style( $what, $v ) {
		switch ( $what ) {
			case 'size':
				$n = (int) $v;
				return $n <= 0 ? 0 : max( 8, min( 48, $n ) );
			case 'weight':
				return self::pick( $v, [ '', '400', '600', '700' ], '' );
			case 'style':
				return self::pick( $v, [ '', 'normal', 'italic' ], '' );
			case 'case':
				return self::pick( $v, [ '', 'none', 'uppercase', 'lowercase', 'capitalize' ], '' );
			case 'line':
				return SB_EmailSig_Styles::lh( $v, '' );
		}

		return '';
	}

	/** A set of colours (COLOURS) cleaned: its keys, in order, each a colour or ''. */
	private static function clean_colours( $v, array $keys ) {
		$v   = is_array( $v ) ? $v : [];
		$out = [];

		foreach ( array_keys( $keys ) as $k ) {
			$out[ $k ] = SB_EmailSig_Styles::clean_colour( is_string( $v[ $k ] ?? '' ) ? ( $v[ $k ] ?? '' ) : '' );
		}

		return $out;
	}

	/** A gap cleaned (between columns, or rows): $space and $line when it has none. */
	private static function clean_gap( $g, $space, $line ) {
		$g = is_array( $g ) ? $g : [];

		return [
			'space'      => self::space( $g['space'] ?? null, $space ),
			'line'       => self::line( $g['line'] ?? null, $line ),
			'line_style' => self::line_style( $g['line_style'] ?? '' ),
			// Its own colour, '' for the lines colour (Sam, 31 October 2026).
			'colour'     => SB_EmailSig_Styles::clean_colour( is_string( $g['colour'] ?? '' ) ? ( $g['colour'] ?? '' ) : '' ),
		];
	}

	/** A space: a step of the Default Spacing, or px. -1 (before steps) is the default; missing is $default. */
	private static function space( $v, $default ) {
		if ( $v === null || $v === '' ) {
			return $default;
		}

		if ( is_string( $v ) && isset( self::STEPS[ $v ] ) ) {
			return $v;
		}

		if ( is_numeric( $v ) && (int) $v === -1 ) {
			return 'default';
		}

		return is_numeric( $v ) ? max( 0, min( 80, (int) $v ) ) : $default;
	}

	/** A line: 'default' (the Default Line Width) or px, at least $min. Missing is $default. */
	private static function line( $v, $default, $min = 0 ) {
		if ( $v === null || $v === '' ) {
			return $default;
		}

		if ( $v === 'default' ) {
			return 'default';
		}

		return is_numeric( $v ) ? max( $min, min( self::MAX_LINE, (int) $v ) ) : $default;
	}

	/** A line's style: 'default' (the Default Line Style) or a style. */
	private static function line_style( $v ) {
		if ( in_array( $v, self::OLD_STYLES, true ) ) {
			return 'solid';
		}

		return isset( self::STYLES[ $v ] ) ? $v : 'default';
	}

	/** A line's style for CSS: its own, or the layout's Default Line Style. */
	public static function lstyle( $v, $layout = null ) {
		$layout = is_array( $layout ) ? $layout : self::$layout;

		if ( isset( self::STYLES[ $v ] ) ) {
			return $v;
		}

		if ( in_array( $v, self::OLD_STYLES, true ) ) {
			return 'solid';
		}

		return isset( self::STYLES[ $layout['line_style'] ?? '' ] ) ? $layout['line_style'] : 'solid';
	}

	/**
	 * A line's px for its style: a double line is DOUBLE_MIN or more, so
	 * it shows as two lines (Sam, 31 October 2026). 0 stays 0.
	 */
	public static function lwidth( $px, $style ) {
		$px = max( 0, (int) $px );

		return ( $px > 0 && $style === 'double' ) ? max( self::DOUBLE_MIN, $px ) : $px;
	}

	/** A space in px, now: a step of the layout's Default Spacing, or its own px. */
	public static function px( $v, $layout = null ) {
		$layout = is_array( $layout ) ? $layout : self::$layout;

		if ( is_string( $v ) && isset( self::STEPS[ $v ] ) ) {
			return (int) round( (int) ( $layout['block_gap'] ?? 12 ) * self::STEPS[ $v ] );
		}

		return max( 0, (int) $v );
	}

	/** A line's width in px: the Default Line Width, or its own. */
	public static function lpx( $v, $layout = null ) {
		$layout = is_array( $layout ) ? $layout : self::$layout;

		return $v === 'default' ? (int) ( $layout['line_width'] ?? 1 ) : max( 0, (int) $v );
	}

	/** A number kept between $min and $max. */
	private static function num( $v, $min, $max, $default ) {
		return is_numeric( $v ) ? max( $min, min( $max, (int) $v ) ) : $default;
	}

	private static function pick( $v, array $allowed, $default ) {
		return in_array( (string) $v, $allowed, true ) ? (string) $v : $default;
	}

	/**
	 * A layout cleaned into its exact shape: posted JSON or an array.
	 * $default is the design's; null or rubbish gives the default.
	 */
	public static function sanitize( $raw, $default = null ) {
		$default = self::legacy( is_array( $default ) ? $default : self::fallback() );

		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}

		$raw = self::legacy( $raw );

		if ( ! is_array( $raw ) || empty( $raw['rows'] ) || ! is_array( $raw['rows'] ) || ! self::all_columns( $raw ) ) {
			$raw = $default;
		}

		$raw['rows'] = array_slice( array_values( array_filter( (array) $raw['rows'], 'is_array' ) ), 0, self::MAX_ROWS );
		$in_cols     = self::all_columns( $raw );

		// Company items no Info List mentions yet, and the Info List (by its
		// place among them, columns first) that shows them: the first in a
		// column with a company item ticked.
		$infos   = [];
		$known   = [];
		foreach ( array_merge( $in_cols, [ [ 'blocks' => $raw['hold'] ?? [] ] ] ) as $c => $col ) {
			foreach ( (array) ( is_array( $col ) ? ( $col['blocks'] ?? [] ) : [] ) as $b ) {
				if ( is_array( $b ) && ( $b['type'] ?? '' ) === 'info' ) {
					$items   = array_key_exists( 'items', $b ) ? self::expand( $b['items'] ) : null;
					$ticked  = $items === null; // A new Info List shows everything.
					foreach ( (array) $items as $item ) {
						if ( strpos( (string) ( $item['key'] ?? '' ), 'c:' ) === 0 ) {
							$known[ substr( $item['key'], 2 ) ] = true;
							$ticked = $ticked || ! empty( $item['on'] );
						}
					}
					$infos[] = $ticked && $c < count( $in_cols );
				}
			}
		}
		self::$fresh = array_values( array_diff( array_map( 'strval', array_keys( self::company_items() ) ), array_map( 'strval', array_keys( $known ) ) ) );
		self::$takes = (int) array_search( true, $infos, true );
		self::$takes = in_array( true, $infos, true ) ? self::$takes : -1;
		self::$info  = 0;

		$seen    = []; // Single types placed so far.
		$ids     = []; // type => ids used.
		$valigns = [ 'top', 'middle', 'bottom' ];
		$clean   = function ( $list ) use ( &$seen, &$ids ) {
			$out = [];

			foreach ( is_array( $list ) ? $list : [] as $b ) {
				$b = self::clean_block( $b, $seen, $ids );
				if ( $b ) {
					$out[] = $b;
				}
			}

			return $out;
		};

		$rows = [];
		foreach ( $raw['rows'] as $r => $row ) {
			$columns = [];
			foreach ( array_slice( array_values( (array) ( $row['columns'] ?? [] ) ), 0, self::MAX_COLUMNS ) as $col ) {
				$col       = is_array( $col ) ? $col : [];
				$valign    = (string) ( $col['valign'] ?? '' );
				// A box of its own, as a row has (Sam, 31 October 2026): padding
				// on each side (from Space at the top and bottom before), a
				// border, a background and colours.
				$columns[] = [
					'width'      => self::num( $col['width'] ?? 0, 0, 600, 0 ),
					'valign'     => in_array( $valign, $valigns, true ) ? $valign : '',
					'padding'    => isset( $col['padding'] ) ? self::clean_pad( $col['padding'] ) : self::clean_pad(
						[
							'top'    => $col['top'] ?? 0,
							'bottom' => $col['bottom'] ?? 0,
						]
					),
					'border'     => self::clean_border( $col['border'] ?? [] ),
					'background' => SB_EmailSig_Styles::clean_colour( $col['background'] ?? '' ),
					'colours'    => self::clean_colours( $col['colours'] ?? [], self::COLOURS['row'] ),
					'blocks'     => $clean( $col['blocks'] ?? [] ),
				];
			}

			// A row has a column at least.
			if ( ! $columns ) {
				$columns[] = [
					'width'      => 0,
					'valign'     => '',
					'padding'    => self::clean_pad( 0 ),
					'border'     => self::clean_border( [] ),
					'background' => '',
					'colours'    => self::clean_colours( [], self::COLOURS['row'] ),
					'blocks'     => [],
				];
			}

			$gaps = [];
			for ( $i = 0; $i < max( self::GAP_PAD, count( $columns ) - 1 ); $i++ ) {
				$g      = isset( $row['gaps'][ $i ] ) && is_array( $row['gaps'][ $i ] ) ? $row['gaps'][ $i ] : ( $default['rows'][ $r ]['gaps'][ $i ] ?? [] );
				$gaps[] = self::clean_gap( $g, 'double', 'default' ); // Double, with the Default Line Width, unless set (Sam, 31 October 2026).
			}

			$rows[] = [
				'columns'    => $columns,
				'gaps'       => $gaps,
				'valign'     => self::pick( $row['valign'] ?? '', $valigns, 'top' ),
				'padding'    => self::clean_pad( $row['padding'] ?? null ),
				'border'     => self::clean_border( $row['border'] ?? [] ),
				'background' => SB_EmailSig_Styles::clean_colour( $row['background'] ?? '' ),
				'colours'    => self::clean_colours( $row['colours'] ?? [], self::COLOURS['row'] ),
			];
		}

		// Between each row and the next.
		$row_gaps = [];
		for ( $i = 0; $i < count( $rows ) - 1; $i++ ) {
			$row_gaps[] = self::clean_gap( $raw['row_gaps'][ $i ] ?? [], 'double', 'default' ); // Double, with the Default Line Width, unless set (Sam, 31 October 2026).
		}

		$hold   = $clean( $raw['hold'] ?? [] );

		// Every single block is somewhere: one missing goes in the holding area.
		foreach ( array_keys( self::blocks() ) as $type ) {
			if ( ! in_array( $type, self::MULTI, true ) && ! isset( $seen[ $type ] ) ) {
				$hold[] = self::block( $type );
			}
		}

		// The Default Line Style; Double, and the Default Line Width is 3 or more.
		$dstyle = self::pick( $raw['line_style'] ?? '', array_keys( self::STYLES ), 'solid' );

		return [
			'rows'       => $rows,
			'row_gaps'   => $row_gaps,
			'block_gap'  => self::num( $raw['block_gap'] ?? 12, 0, 40, 12 ),
			'line_width' => self::lwidth( self::num( $raw['line_width'] ?? 1, 1, self::MAX_LINE, 1 ), $dstyle ),
			'line_style' => $dstyle,
			'padding'    => self::space( $raw['padding'] ?? null, 0 ),
			'border'     => self::clean_border( $raw['border'] ?? [] ),
			'background' => SB_EmailSig_Styles::clean_colour( $raw['background'] ?? '' ),
			'line_below' => self::line( $raw['line_below'] ?? null, 'default' ),
			'line_below_style' => self::line_style( $raw['line_below_style'] ?? '' ),
			'line_colour' => SB_EmailSig_Styles::clean_colour( $raw['line_colour'] ?? '' ),
			'line_space' => self::space( $raw['line_space'] ?? null, 24 ),
			'hide_buttons'  => empty( $raw['hide_buttons'] ) ? 0 : 1,
			'hide_banners'  => empty( $raw['hide_banners'] ) ? 0 : 1,
			'hide_campaign' => empty( $raw['hide_campaign'] ) ? 0 : 1,
			'hide_text'     => empty( $raw['hide_text'] ) ? 0 : 1,
			'hide_signoff'  => empty( $raw['hide_signoff'] ) ? 0 : 1,
			'hold'       => $hold,
		];
	}

	/** One block cleaned, or null (unknown type, a second single, too many of a kind). */
	private static function clean_block( $b, array &$seen, array &$ids ) {
		if ( ! is_array( $b ) ) {
			return null;
		}

		$type  = (string) ( $b['type'] ?? '' );
		$types = self::blocks();

		if ( ! isset( $types[ $type ] ) ) {
			return null;
		}

		$out  = [ 'type' => $type ];
		$fill = false; // This Info List takes the company items new to the layout.

		if ( $type === 'info' ) {
			$fill = self::$info === self::$takes;
			self::$info++;
		}

		if ( in_array( $type, self::MULTI, true ) ) {
			$used = $ids[ $type ] ?? [];
			if ( count( $used ) >= self::MAX_MULTI ) {
				return null;
			}

			$id = max( 1, min( 99, (int) ( $b['id'] ?? 1 ) ) );
			while ( in_array( $id, $used, true ) ) {
				$id++;
			}

			$ids[ $type ][] = $id;
			$out['id']      = $id;
		} else {
			if ( isset( $seen[ $type ] ) ) {
				return null;
			}

			$seen[ $type ] = true;
		}

		$out['align'] = self::pick( $b['align'] ?? '', [ 'left', 'center', 'right' ], 'left' );
		$out['space'] = self::space( $b['space'] ?? null, 'default' );
		$seps         = array_keys( self::seps() );

		foreach ( self::block_options( $type ) as $k => $def ) {
			$v = $b[ $k ] ?? $def;

			// A Text block's title and text styles.
			if ( preg_match( '/^(title|text)_(size|weight|style|case|line)$/', $k, $m ) ) {
				$out[ $k ] = self::text_style( $m[2], $v );
				continue;
			}

			switch ( $k ) {
				case 'title':
					$out[ $k ] = self::clean_text( $v, true );
					break;
				case 'text':
					$out[ $k ] = self::clean_text( $v, false );
					break;
				case 'gap':
					$out[ $k ] = self::space( $v, $def );
					break;
				case 'style':
					$out[ $k ] = self::pick( $v, [ 'stack', 'row' ], $def );
					break;
				case 'format':
					$out[ $k ] = self::pick( $v, array_keys( SB_EmailSig_Names::formats() ), $def );
					break;
				case 'sep':
					$out[ $k ] = self::pick( $v, $seps, $def );
					break;
				case 'direction':
					$out[ $k ] = self::pick( $v, [ 'row', 'column' ], $def );
					break;
				case 'lead':
					$out[ $k ] = self::pick( $v, [ '', 'icon', 'letter', 'label', 'none' ], $def );
					break;
				case 'spacing':
					$out[ $k ] = self::space( $v, $def );
					break;
				case 'thickness':
					$out[ $k ] = self::line( $v, $def, 1 );
					break;
				case 'items':
					$out[ $k ] = self::clean_items( $v, $fill );
					break;
				case 'colour':
					$out[ $k ] = SB_EmailSig_Styles::clean_colour( is_string( $v ) ? $v : '' );
					break;
				case 'line_style':
					$out[ $k ] = self::line_style( $v );
					break;
				case 'colours':
					$out[ $k ] = self::clean_colours( $v, $def );
					break;
				default: // 0 or 1.
					$out[ $k ] = empty( $v ) ? 0 : 1;
			}
		}

		return $out;
	}

	/**
	 * An Info List's items: each key once, in the order given, any missing
	 * added switched off. $fill: company items new to the layout go in
	 * ticked, after this list's last company item.
	 */
	private static function clean_items( $list, $fill = false ) {
		$all = array_keys( self::items() );
		$out = [];
		$got = [];

		foreach ( self::expand( $list ) as $item ) {
			$key = is_array( $item ) ? (string) ( $item['key'] ?? '' ) : '';

			if ( in_array( $key, $all, true ) && ! isset( $got[ $key ] ) ) {
				$got[ $key ] = true;
				$out[]       = [
					'key' => $key,
					'on'  => empty( $item['on'] ) ? 0 : 1,
				];
			}
		}

		if ( $fill && self::$fresh ) {
			$at = count( $out );
			foreach ( $out as $n => $item ) {
				if ( strpos( $item['key'], 'c:' ) === 0 ) {
					$at = $n + 1;
				}
			}

			$add = [];
			foreach ( self::$fresh as $ref ) {
				if ( ! isset( $got[ 'c:' . $ref ] ) ) {
					$got[ 'c:' . $ref ] = true;
					$add[]              = [
						'key' => 'c:' . $ref,
						'on'  => 1,
					];
				}
			}

			array_splice( $out, $at, 0, $add );
		}

		foreach ( $all as $key ) {
			if ( ! isset( $got[ $key ] ) ) {
				$out[] = [
					'key' => $key,
					'on'  => 0,
				];
			}
		}

		return $out;
	}

	/** True when a block of $type is in a column (not the holding area). */
	public static function shows( array $layout, $type ) {
		foreach ( self::all_columns( $layout ) as $col ) {
			foreach ( (array) ( $col['blocks'] ?? [] ) as $b ) {
				if ( ( $b['type'] ?? '' ) === $type ) {
					return true;
				}
			}
		}

		return false;
	}

	/** $layout with $type moved to the holding area (a template whose photo was switched off). */
	public static function hold( array $layout, $type ) {
		$layout = self::legacy( $layout );

		foreach ( $layout['rows'] as $r => $row ) {
			foreach ( $row['columns'] as $c => $col ) {
				foreach ( $col['blocks'] as $i => $b ) {
					if ( $b['type'] === $type ) {
						unset( $layout['rows'][ $r ]['columns'][ $c ]['blocks'][ $i ] );
						$layout['rows'][ $r ]['columns'][ $c ]['blocks'] = array_values( $layout['rows'][ $r ]['columns'][ $c ]['blocks'] );
						$layout['hold'][] = $b;
					}
				}
			}
		}

		return $layout;
	}

	/* ------------------------------------------------------------------ *
	 * Drawing
	 * ------------------------------------------------------------------ */

	/** Every link opens in a new window, so webmail stays open behind it. */
	const NEW_WINDOW = ' target="_blank" rel="noopener"';

	/** Links take the text's own colour. */
	const LINK = 'color:inherit;text-decoration:none;';

	private static function cased( $item, $text ) {
		return SB_EmailSig_Styles::cased( self::$s, $item, $text );
	}

	private static function preview() {
		return ! empty( self::$d['preview'] );
	}

	/** In previews, icons carry a marker so the page can show them in unsaved colours. */
	private static function mark( $attr, $value ) {
		return self::preview() ? ' ' . $attr . '="' . esc_attr( $value ) . '"' : '';
	}

	/** A grey stand-in for something not set yet, in previews only. */
	private static function ph( $label, $style = '' ) {
		if ( ! self::preview() ) {
			return '';
		}

		return '<div style="display:inline-block;padding:6px 10px;border:1px dashed #c3c4c7;border-radius:4px;color:#a7aaad;font-family:Arial,sans-serif;font-size:12px;line-height:16px;' . esc_attr( $style ) . '">{' . esc_html( $label ) . ' here}</div>';
	}

	/** A table for inside a block, lined up with the block's align. */
	private static function table( $align, $extra = '' ) {
		$attr = $align === 'left' ? '' : ' align="' . esc_attr( $align ) . '"';
		$css  = $align === 'center' ? 'margin:0 auto;' : ( $align === 'right' ? 'margin:0 0 0 auto;' : '' );

		return '<table cellpadding="0" cellspacing="0" border="0" role="presentation"' . $attr . ' style="border-collapse:collapse;' . $css . $extra . '">';
	}

	/** A link around $inner, or $inner alone without one. */
	private static function a( $url, $inner, $style = 'text-decoration:none;' ) {
		if ( (string) $url === '' ) {
			return $inner;
		}

		return '<a href="' . esc_url( $url, [ 'http', 'https', 'mailto', 'tel' ] ) . '"' . self::NEW_WINDOW . ' style="' . esc_attr( $style ) . '">' . $inner . '</a>';
	}

	/**
	 * The signature: the sign-off, the columns, then the line, buttons,
	 * footer text, campaign banner and banners. Called by the design's
	 * template.php with its $d, $s and $tpl.
	 */
	public static function html( array $d, array $s, array $tpl ) {
		self::$d   = $d;
		self::$s   = $s;
		self::$tpl = $tpl;

		$layout       = isset( $s['layout'] ) && is_array( $s['layout'] ) ? $s['layout'] : self::sanitize( null );
		$layout       = isset( $layout['rows'] ) ? $layout : self::sanitize( $layout ); // One from before rows.
		self::$layout = $layout;
		$css    = $s['css'];
		$width  = (int) ( $s['width'] ?? 600 );
		$lines  = $s['lines'];
		// -webkit-text-size-adjust on the whole signature (it passes down to
		// every part): iPhone Mail enlarges text under about 13px, so every size
		// stays as set (Sam, 30 October 2026). Separate borders, not collapsed:
		// collapsed, half the Container border sat outside the table, and Gmail
		// webmail cut off its left side (Sam, 31 October 2026).
		$out    = '<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:separate;border-spacing:0;max-width:' . $width . 'px;-webkit-text-size-adjust:100%;font-family:' . esc_attr( $s['font'] ) . ';color:' . esc_attr( $s['text'] ) . ';">';

		if ( $d['signoff'] && empty( $layout['hide_signoff'] ) ) {
			$out .= '<tr><td style="padding:0 0 20px 0;' . esc_attr( $css['signoff'] ?? '' ) . '">' . esc_html( self::cased( 'signoff', $d['signoff'] ) ) . '</td></tr>';
		}

		$out .= self::columns( $layout );

		if ( self::lpx( $layout['line_below'] ?? 0 ) > 0 ) {
			$out .= '<tr><td style="padding:' . self::px( $layout['line_space'] ?? 24 ) . 'px 0 0 0;">' . self::rule( self::lpx( $layout['line_below'] ), (string) ( $layout['line_colour'] ?? '' ), self::lstyle( $layout['line_below_style'] ?? 'default' ) ) . '</td></tr>';
		}

		$out .= self::footer( $layout );

		return $out . '</table>';
	}

	/** A box's style (the Container's, a row's): padding, background and border. [ css, background hex ]. */
	private static function box( array $box ) {
		$lines  = self::$s['lines'];
		$border = $box['border'];
		$colour = $border['colour'] !== '' ? ( SB_EmailSig_Styles::hex( $border['colour'] ) ?: $lines ) : $lines;
		$wrap   = self::pad_css( $box['padding'] );
		$bg     = ! empty( $box['background'] ) ? SB_EmailSig_Styles::hex( $box['background'] ) : '';

		// A background, bgcolor too for Outlook.
		if ( $bg !== '' ) {
			$wrap .= 'background:' . $bg . ';';
		}

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			if ( (int) $border[ $side ] > 0 ) {
				$bstyle = self::lstyle( $border['line_style'] ?? 'default' );
				$wrap  .= 'border-' . $side . ':' . self::lwidth( $border[ $side ], $bstyle ) . 'px ' . $bstyle . ' ' . $colour . ';';
			}
		}

		return [ $wrap, $bg ];
	}

	/** A row with something of its own: padding, a border or a background. */
	private static function boxed( array $row ) {
		return self::padded( $row['padding'] ) || $row['background'] !== '' || array_sum( array_map( 'intval', array_intersect_key( $row['border'], array_flip( [ 'top', 'right', 'bottom', 'left' ] ) ) ) ) > 0;
	}

	/**
	 * The rows in the Container (Sam, 31 October 2026): each row's columns,
	 * its own box round them, and the gap (and any line) between rows. Rows
	 * with nothing to show are left out. One row with no box of its own is
	 * drawn exactly as before rows, so signatures already copied stay up to
	 * date.
	 */
	private static function columns( array $layout ) {
		$shown = [];

		foreach ( $layout['rows'] as $row ) {
			$html = self::row( $row );
			if ( $html !== '' ) {
				$shown[] = [ $row, $html ];
			}
		}

		if ( ! $shown ) {
			return '';
		}

		list( $wrap, $bg ) = self::box( $layout );

		$out = '<tr><td' . ( $bg !== '' ? ' bgcolor="' . esc_attr( $bg ) . '"' : '' ) . ' style="' . esc_attr( $wrap ) . '">';

		if ( count( $shown ) === 1 && ! self::boxed( $shown[0][0] ) ) {
			return $out . $shown[0][1] . '</td></tr>';
		}

		$out .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;width:100%;">';

		foreach ( $shown as $n => list( $row, $html ) ) {
			if ( $n > 0 ) {
				$out .= self::row_gap( $layout['row_gaps'][ $n - 1 ] ?? [ 'space' => 'default', 'line' => 0 ] );
			}

			list( $rwrap, $rbg ) = self::box( $row );
			$out                .= '<tr><td' . ( $rbg !== '' ? ' bgcolor="' . esc_attr( $rbg ) . '"' : '' ) . ' style="' . esc_attr( $rwrap ) . '">' . $html . '</td></tr>';
		}

		return $out . '</table></td></tr>';
	}

	/** The space between two rows, with a full width line in the middle when it has one. */
	private static function row_gap( array $g ) {
		$space = self::px( $g['space'] );
		$line  = self::lpx( $g['line'] );

		if ( ! $line ) {
			return $space ? '<tr><td style="height:' . $space . 'px;line-height:' . $space . 'px;font-size:0;padding:0;"></td></tr>' : '';
		}

		return '<tr><td style="padding:' . $space . 'px 0 ' . $space . 'px 0;">' . self::rule( $line, (string) ( $g['colour'] ?? '' ), self::lstyle( $g['line_style'] ?? 'default' ) ) . '</td></tr>';
	}

	/** One row's columns, as a table: empty columns (nothing to show) are left out, gaps and all. '' with nothing to show. */
	private static function row( array $layout ) {
		$cols          = [];
		self::$row_now = $layout;

		foreach ( $layout['columns'] as $col ) {
			$parts = [];

			// Its blocks follow its colours, else the row's (Sam, 31 October 2026).
			self::$colours_now = (array) ( $layout['colours'] ?? [] );
			foreach ( (array) ( $col['colours'] ?? [] ) as $k => $v ) {
				if ( (string) $v !== '' ) {
					self::$colours_now[ $k ] = $v;
				}
			}

			foreach ( $col['blocks'] as $b ) {
				$html = self::draw( $b );
				if ( $html !== '' ) {
					$parts[] = [ $b, $html ];
				}
			}

			if ( $parts ) {
				$cols[] = [ $col, $parts ];
			}
		}

		if ( ! $cols ) {
			return '';
		}

		$lines = self::$s['lines'];
		$out   = '<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;"><tr>';
		$count = count( $cols );

		foreach ( $cols as $i => list( $col, $parts ) ) {
			$va    = $col['valign'] !== '' ? $col['valign'] : $layout['valign'];
			$after = $i < $count - 1 ? ( $layout['gaps'][ $i ] ?? [ 'space' => 20, 'line' => 0 ] ) : null;
			$befor = $i > 0 ? ( $layout['gaps'][ $i - 1 ] ?? [ 'space' => 20, 'line' => 0 ] ) : null;
			$bline = $befor ? self::lpx( $befor['line'] ) : 0;
			$pad   = (array) ( $col['padding'] ?? [] );
			$boxed = self::boxed( [ 'padding' => 0, 'border' => $col['border'] ?? [], 'background' => $col['background'] ?? '' ] );
			$cbg   = '';

			if ( $boxed ) {
				// A border or background of its own (Sam, 31 October 2026): on
				// the cell itself, so it fills the row's height, with the gaps
				// either side as cells of their own outside it.
				list( $style, $cbg ) = self::box(
					[
						'padding'    => $pad,
						'border'     => $col['border'],
						'background' => $col['background'],
					]
				);
				$style .= 'vertical-align:' . $va . ';';
			} else {
				$style = 'padding:' . ( self::px( $pad['top'] ?? 0 ) ) . 'px ' . ( ( $after ? self::px( $after['space'] ) : 0 ) + self::px( $pad['right'] ?? 0 ) ) . 'px ' . ( self::px( $pad['bottom'] ?? 0 ) ) . 'px ' . ( ( $bline ? self::px( $befor['space'] ) : 0 ) + self::px( $pad['left'] ?? 0 ) ) . 'px;vertical-align:' . $va . ';';
			}

			// The line between columns: a cell of its own in the lines colour,
			// as the Gmail phone app loses a 1px border when it shrinks a
			// signature to fit (Sam, 30 October 2026). Double: two of them with
			// an empty cell between (Sam, 31 October 2026).
			if ( $bline ) {
				$lstyle = self::lstyle( $befor['line_style'] ?? 'default' );
				$gcol   = ( ! empty( $befor['colour'] ) ? SB_EmailSig_Styles::hex( (string) $befor['colour'] ) : '' ) ?: $lines;

				if ( $lstyle === 'double' ) {
					list( $one, $between ) = self::dsplit( $bline );
					$out .= self::vline( $one, $gcol ) . self::vgap( $between ) . self::vline( $one, $gcol );
				} else {
					$out .= self::vline( $bline, $gcol );
				}
			}

			// Boxed: the space after a line before it, as a cell.
			if ( $boxed && $bline && self::px( $befor['space'] ) > 0 ) {
				$out .= self::vgap( self::px( $befor['space'] ) );
			}

			$w = (int) $col['width'];
			if ( $w ) {
				$style .= 'width:' . $w . 'px;';
			}

			$out .= '<td valign="' . esc_attr( $va ) . '"' . ( $w ? ' width="' . $w . '"' : '' ) . ( $cbg !== '' ? ' bgcolor="' . esc_attr( $cbg ) . '"' : '' ) . ' style="' . esc_attr( $style ) . '">';
			$out .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;width:100%;">';

			foreach ( $parts as $n => list( $b, $html ) ) {
				$space = $n === 0 ? 0 : self::px( $b['space'] );
				$out  .= '<tr><td align="' . esc_attr( $b['align'] ) . '" style="padding:' . $space . 'px 0 0 0;text-align:' . esc_attr( $b['align'] ) . ';">' . $html . '</td></tr>';
			}

			$out .= '</table></td>';

			// Boxed: the space after it, before any line, as a cell.
			if ( $boxed && $after && self::px( $after['space'] ) > 0 ) {
				$out .= self::vgap( self::px( $after['space'] ) );
			}
		}

		return $out . '</tr></table>';
	}

	/**
	 * One block's HTML, '' when it has nothing to show: in its own colours,
	 * else its row's, else the template's (Sam, 31 October 2026).
	 */
	private static function draw( array $b ) {
		$s       = self::$s;
		$variant = self::$variant;

		self::colours_for( $b );
		$html = self::draw_block( $b );

		self::$s       = $s;
		self::$variant = $variant;

		return $html;
	}

	/** A colour as six lower case hex digits with its #, or ''. */
	private static function hex6( $v ) {
		$v = strtolower( (string) $v );

		if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $v, $m ) ) {
			return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}

		return preg_match( '/^#[0-9a-f]{6}$/', $v ) ? $v : '';
	}

	/**
	 * What a variant icon set is drawn as, so it is found again (and missed
	 * when anything about it changes): c (contact) or s (social), its size,
	 * colour, background (x for none) and corner radius. layout.js makes the
	 * same.
	 */
	public static function variant_key( $kind, $size, $colour, $bg, $radius ) {
		$c = ltrim( self::hex6( $colour ), '#' );
		$g = ltrim( self::hex6( $bg ), '#' );

		return $c === '' ? '' : $kind . (int) $size . '-' . $c . '-' . ( $g !== '' ? $g : 'x' ) . '-' . (int) $radius;
	}

	/** An item's text colour, while a block is drawn. */
	private static function text_colour( $item, $hex ) {
		if ( $hex === '' || ! isset( self::$s['css'][ $item ] ) ) {
			return;
		}

		self::$s['css'][ $item ]    = preg_replace( '/(?<![-a-z])color:[^;]*;/', 'color:' . $hex . ';', self::$s['css'][ $item ] );
		self::$s['colour'][ $item ] = $hex;
	}

	/** A block's colours (its own, else its row's) put into the styles it draws with. */
	private static function colours_for( array $b ) {
		$row  = self::$colours_now;
		$own  = (array) ( $b['colours'] ?? [] );
		$pick = function ( $k, $rk = null ) use ( $own, $row ) {
			$v = (string) ( $own[ $k ] ?? '' );
			if ( $v === '' && null !== $rk ) {
				$v = (string) ( $row[ $rk ] ?? '' );
			}

			return $v !== '' ? self::hex6( SB_EmailSig_Styles::hex( $v ) ) : '';
		};

		$accent = '';
		$icons  = '';

		switch ( $b['type'] ) {
			case 'name':
				self::text_colour( 'name', $pick( 'name', 'text' ) );
				self::text_colour( 'qualifications', $pick( 'qualifications', 'text' ) );
				self::text_colour( 'job_title', $pick( 'job', 'text' ) );
				$accent = $pick( 'accent', 'accent' );
				break;
			case 'company':
				self::text_colour( 'company', $pick( 'text', 'text' ) );
				break;
			case 'info':
				self::text_colour( 'contact', $pick( 'text', 'text' ) );
				$accent = $pick( 'accent', 'accent' );
				$icons  = $pick( 'icons', 'icons' );
				break;
			case 'vcard':
				self::text_colour( 'contact', $pick( 'text', 'text' ) );
				$accent = $pick( 'accent', 'accent' );
				$icons  = $pick( 'icons', 'icons' );
				break;
			case 'social':
				self::text_colour( 'social_heading', $pick( 'heading', 'text' ) );
				$colour = $pick( 'icons', 'icons' );
				$bg     = $pick( 'bg' );

				if ( ( $colour !== '' || $bg !== '' ) && ! empty( self::$s['social'] ) ) {
					$so                      = self::$s['social'];
					self::$variant['social'] = self::variant_key( 's', $so['size'], $colour !== '' ? $colour : $so['colour'], $bg !== '' ? $bg : $so['bg'], $so['radius'] );
				}
				break;
		}

		// Separators, and the letters and labels starting Info List lines.
		if ( $accent !== '' ) {
			self::$s['lines'] = $accent;

			if ( isset( self::$s['icons']['label'] ) ) {
				self::$s['icons']['label'] = preg_replace( '/(?<![-a-z])color:[^;]*;/', 'color:' . $accent . ';', self::$s['icons']['label'] );
			}
		}

		if ( $icons !== '' && ! empty( self::$s['icons'] ) ) {
			$i                        = self::$s['icons'];
			self::$variant['contact'] = self::variant_key( 'c', $i['size'] ?? 18, $icons, $i['bg'] ?? '', $i['radius'] ?? 0 );
		}
	}

	private static function draw_block( array $b ) {
		switch ( $b['type'] ) {
			case 'photo':
				return self::photo( $b['align'] );
			case 'name':
				return self::name( $b );
			case 'company':
				return self::company();
			case 'logo':
				return self::logo( false, $b['align'] );
			case 'logo_small':
				return self::logo( true, $b['align'] );
			case 'info':
				return self::info( $b );
			case 'vcard':
				return self::vcard( $b );
			case 'social':
				return self::social( $b );
			case 'divider':
				return self::rule( max( 1, self::lpx( $b['thickness'] ) ), (string) ( $b['colour'] ?? '' ), self::lstyle( $b['line_style'] ?? 'default' ) );
			case 'text':
				return self::text_block( $b );
		}

		return '';
	}

	/**
	 * A line between columns: a cell $px wide, filled with $colour. Empty,
	 * not &nbsp;: Apple Mail's compose window gave the space its own 12pt
	 * font, so the line drew as wide as a character (Sam, 30 October 2026).
	 */
	private static function vline( $px, $colour ) {
		$colour = esc_attr( $colour );

		return '<td width="' . (int) $px . '" bgcolor="' . $colour . '" style="width:' . (int) $px . 'px;min-width:' . (int) $px . 'px;padding:0;background-color:' . $colour . ';font-size:0;line-height:0;"></td>';
	}

	/** The space between a double line's two lines between columns: an empty cell $px wide. */
	private static function vgap( $px ) {
		return '<td width="' . (int) $px . '" style="width:' . (int) $px . 'px;min-width:' . (int) $px . 'px;padding:0;font-size:0;line-height:0;"></td>';
	}

	/**
	 * A double line $px thick as [ each line, the space between ], as a
	 * browser draws a double border: 3 is 1, 1, 1; 5 is 2, 1, 2; 8 is 3, 2, 3.
	 */
	private static function dsplit( $px ) {
		$px  = max( self::DOUBLE_MIN, (int) $px );
		$one = max( 1, (int) round( $px / 3 ) );

		return [ $one, max( 1, $px - 2 * $one ) ];
	}

	/**
	 * A full width line, $px thick, in $colour (a saved colour; '' for the
	 * lines colour): a block that height, filled (the Gmail phone app lost a
	 * 1px border, shrank an empty table cell to a pixel, and a border on a
	 * table cell to a single dot). Double: two of them with an empty block
	 * between (Sam, 31 October 2026).
	 */
	private static function rule( $px, $colour = '', $style = 'solid' ) {
		$style  = isset( self::STYLES[ $style ] ) ? $style : 'solid';
		$px     = self::lwidth( max( 1, (int) $px ), $style );
		$colour = ( $colour !== '' ? SB_EmailSig_Styles::hex( $colour ) : '' ) ?: self::$s['lines'];

		// A block, so it fills the width it sits in: the Gmail phone app
		// shrank a 100% wide table with nothing in it to a single pixel.
		$bar = function ( $h, $fill ) {
			return '<div style="width:100%;height:' . $h . 'px;line-height:' . $h . 'px;font-size:0;' . ( $fill !== '' ? 'background-color:' . esc_attr( $fill ) . ';' : '' ) . '"></div>';
		};

		if ( $style === 'double' ) {
			list( $one, $between ) = self::dsplit( $px );

			return $bar( $one, $colour ) . $bar( $between, '' ) . $bar( $one, $colour );
		}

		return $bar( $px, $colour );
	}

	/**
	 * The person's photo as this template makes it, or a stand-in its size in
	 * previews. Width only, height auto: outside previews the address is fixed
	 * (SB_EmailSig_Photo::fixed_url) and the photo behind it may change ratio.
	 */
	private static function photo( $align ) {
		$d     = self::$d;
		$s     = self::$s;
		$photo = $d['photo'] ?? null;
		$shape = $photo['shape'] ?? ( $s['photo']['shape'] ?? '' );
		$round = $shape === 'circle' ? '50%' : ( $shape === 'rounded' ? (int) ( $photo['radius'] ?? $s['photo']['radius'] ) . 'px' : '0' );

		if ( $photo ) {
			$w   = (int) $photo['width'];
			$img = '<img src="' . esc_url( $photo['url'] ) . '" width="' . $w . '" alt="' . esc_attr( $d['name'] ) . '" style="' . self::img_display( $align ) . 'width:' . $w . 'px;height:auto;border:0;outline:none;' . ( $photo['baked'] ? '' : 'border-radius:' . esc_attr( $round ) . ';' ) . '" />';

			return self::a( $d['profile_link'], $img );
		}

		if ( ! self::preview() ) {
			return '';
		}

		$w = (int) ( $s['photo']['width'] ?? 130 );
		$h = (int) round( $w / max( 0.01, (float) ( $s['photo']['ratio'] ?? 1 ) ) );

		return '<div style="display:inline-block;width:' . ( $w - 4 ) . 'px;height:' . ( $h - 4 ) . 'px;line-height:' . ( $h - 4 ) . 'px;border:2px dashed #c3c4c7;border-radius:' . esc_attr( $round ) . ';background:#f6f7f7;color:#a7aaad;font-family:Arial,sans-serif;font-size:12px;text-align:center;">{photo}</div>';
	}

	/**
	 * An image's display: a block on the left (no gap under it), inline and
	 * top aligned otherwise, so the cell's align can centre it, Outlook too.
	 */
	private static function img_display( $align ) {
		return $align === 'left' ? 'display:block;' : 'display:inline-block;vertical-align:top;';
	}

	/** The separator character for a key of seps(). */
	private static function sep( $key ) {
		return self::seps()[ $key ][1] ?? '|';
	}

	/** Name, qualifications and job title, stacked or on one line. */
	private static function name( array $b ) {
		$d    = self::$d;
		$css  = self::$s['css'];
		$name = esc_html( self::cased( 'name', SB_EmailSig_Names::show( $b['format'] ?? 'full', $d['first_name'] ?? '', $d['last_name'] ?? '', $d['name'] ) ) );
		$name = $d['profile_link'] ? self::a( $d['profile_link'], $name, self::LINK . ( $css['name'] ?? '' ) ) : $name;
		$more = [];

		foreach ( [ 'qualifications', 'job_title' ] as $item ) {
			if ( ! empty( $d[ $item ] ) ) {
				$more[ $item ] = esc_html( self::cased( $item, $d[ $item ] ) );
			}
		}

		$space = self::px( $b['spacing'] ?? 3 );

		if ( $b['style'] === 'row' ) {
			$sep = self::sep( $b['sep'] );
			$out = '<div style="white-space:nowrap;"><span style="' . esc_attr( $css['name'] ?? '' ) . '">' . $name . '</span>';

			foreach ( $more as $item => $text ) {
				$out .= '<span style="' . esc_attr( ( $css[ $item ] ?? '' ) . 'padding:0 ' . $space . 'px;color:' . self::$s['lines'] . ';' ) . '">' . ( $sep !== '' ? esc_html( $sep ) : '&nbsp;' ) . '</span><span style="' . esc_attr( $css[ $item ] ?? '' ) . '">' . $text . '</span>';
			}

			return $out . '</div>';
		}

		$out = '<div style="white-space:nowrap;' . esc_attr( $css['name'] ?? '' ) . '">' . $name . '</div>';

		foreach ( $more as $item => $text ) {
			$out .= '<div style="padding-top:' . $space . 'px;white-space:nowrap;' . esc_attr( $css[ $item ] ?? '' ) . '">' . $text . '</div>';
		}

		return $out;
	}

	/**
	 * A Text block (Sam, 31 October 2026): its title and text, merge tags
	 * filled in, then Dynamic Shortcodes and shortcodes run, addresses made
	 * links. Each part in its own styles, else the template's (the title as
	 * Name, the text as the Info List), colours over its row's.
	 */
	private static function text_block( array $b ) {
		$row    = self::$colours_now;
		$own    = (array) ( $b['colours'] ?? [] );
		$colour = function ( $k, $rk = null ) use ( $own, $row ) {
			$v = (string) ( $own[ $k ] ?? '' );
			if ( $v === '' && null !== $rk ) {
				$v = (string) ( $row[ $rk ] ?? '' );
			}

			return $v !== '' ? self::hex6( SB_EmailSig_Styles::hex( $v ) ) : '';
		};
		$parts  = [
			'title' => 'name',
			'text'  => 'contact',
		];
		$html   = [];
		$css    = [];

		foreach ( $parts as $part => $item ) {
			$raw = (string) ( $b[ $part ] ?? '' );
			if ( trim( $raw ) === '' ) {
				continue;
			}

			$hex          = $colour( $part, 'text' );
			$css[ $part ] = self::restyle( self::$s['css'][ $item ] ?? '', $b, $part, $hex );
			$case         = (string) ( $b[ $part . '_case' ] ?? '' );
			$case         = $case !== '' ? $case : (string) ( self::$s['case'][ $item ] ?? 'none' );
			$text_hex     = $hex !== '' ? $hex : (string) ( self::$s['colour'][ $item ] ?? '' );
			$links        = $colour( 'links' );
			$out          = self::fill( $part === 'text' ? nl2br( $raw, false ) : $raw, $case, $links !== '' ? $links : $text_hex );

			if ( trim( wp_strip_all_tags( $out ) ) !== '' || strpos( $out, '<img' ) !== false ) {
				// A space beside a link or bold stays: Render drops whitespace around tags.
				$html[ $part ] = preg_replace( '/(?<=>) | (?=<)/', '&#32;', $out );
			}
		}

		if ( ! $html ) {
			return self::ph( 'text' );
		}

		$out = '';
		if ( isset( $html['title'] ) ) {
			$out .= '<div style="' . esc_attr( $css['title'] ) . '">' . $html['title'] . '</div>';
		}
		if ( isset( $html['text'] ) ) {
			$out .= '<div style="' . esc_attr( ( isset( $html['title'] ) ? 'padding-top:' . self::px( $b['gap'] ?? 'quarter' ) . 'px;' : '' ) . $css['text'] ) . '">' . $html['text'] . '</div>';
		}

		return $out;
	}

	/** An item's CSS with a Text block part's own size, weight, style, line height and colour over it. */
	private static function restyle( $css, array $b, $part, $hex ) {
		$set = [];
		if ( (int) ( $b[ $part . '_size' ] ?? 0 ) > 0 ) {
			$set['font-size'] = (int) $b[ $part . '_size' ] . 'px';
		}
		if ( (string) ( $b[ $part . '_weight' ] ?? '' ) !== '' ) {
			$set['font-weight'] = $b[ $part . '_weight' ];
		}
		if ( (string) ( $b[ $part . '_style' ] ?? '' ) !== '' ) {
			$set['font-style'] = $b[ $part . '_style' ];
		}
		if ( (string) ( $b[ $part . '_line' ] ?? '' ) !== '' ) {
			$set['line-height'] = $b[ $part . '_line' ];
		}
		if ( $hex !== '' ) {
			$set['color'] = $hex;
		}

		foreach ( $set as $prop => $value ) {
			$rule = $prop . ':' . $value . ';';
			$css  = preg_match( '/(?<![-a-z])' . $prop . ':[^;]*;/', $css ) ? preg_replace( '/(?<![-a-z])' . $prop . ':[^;]*;/', $rule, $css ) : $css . $rule;
		}

		return $css;
	}

	/** A Text block part filled in: merge tags, Dynamic Shortcodes, shortcodes, links, then its case. */
	private static function fill( $html, $case, $link_hex ) {
		$d    = self::$d;
		$name = trim( (string) ( $d['name'] ?? '' ) );
		$bits = SB_EmailSig_Names::split( $name );
		$vals = [
			'first_name'     => ( $d['first_name'] ?? '' ) !== '' ? $d['first_name'] : $bits[0],
			'last_name'      => ( $d['first_name'] ?? '' ) !== '' ? (string) ( $d['last_name'] ?? '' ) : $bits[1],
			'name'           => $name,
			'job_title'      => $d['job_title'] ?? '',
			'qualifications' => $d['qualifications'] ?? '',
			'company'        => $d['company'] ?? '',
			'phone'          => $d['phone'] ?? '',
			'mobile'         => $d['mobile'] ?? '',
			'email'          => $d['email'] ?? '',
			'website'        => ( $d['website_label'] ?? '' ) !== '' ? $d['website_label'] : ( $d['website'] ?? '' ),
		];

		$html = preg_replace_callback(
			'/\{(' . implode( '|', self::TAGS ) . ')\}/',
			function ( $m ) use ( $vals ) {
				return esc_html( (string) $vals[ $m[1] ] );
			},
			$html
		);

		// Dynamic Shortcodes (dynamic.ooo), when the site has it: {wp-shortcode:site_phone @ format="link"}.
		if ( strpos( $html, '{' ) !== false && class_exists( '\DynamicShortcodes\Plugin' ) ) {
			try {
				$manager = \DynamicShortcodes\Plugin::instance()->shortcodes_manager;
				$done    = $manager ? $manager->expand_shortcodes( $html ) : null;
				$html    = is_string( $done ) ? $done : $html;
			} catch ( \Throwable $e ) {
				unset( $e ); // Left as typed.
			}
		}

		if ( strpos( $html, '[' ) !== false ) {
			$html = do_shortcode( $html );
		}

		$html = make_clickable( $html );

		// The case on the words only, not inside tags.
		if ( $case !== '' && $case !== 'none' ) {
			$html = preg_replace_callback(
				'/(^|>)([^<]+)/',
				function ( $m ) use ( $case ) {
					return $m[1] . SB_EmailSig_Styles::cased( [ 'case' => [ 't' => $case ] ], 't', $m[2] );
				},
				$html
			);
		}

		// Every link: its address, a new window, and the links colour.
		return preg_replace_callback(
			'/<a\s([^>]*)>/i',
			function ( $m ) use ( $link_hex ) {
				$href = preg_match( '/href\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $m[1], $h ) ? html_entity_decode( $h[2] !== '' ? $h[2] : ( $h[3] ?? '' ) ) : '';

				return '<a href="' . esc_url( $href, [ 'http', 'https', 'mailto', 'tel', 'sms' ] ) . '"' . self::NEW_WINDOW . ' style="' . esc_attr( ( $link_hex !== '' ? 'color:' . $link_hex . ';' : 'color:inherit;' ) . 'text-decoration:underline;' ) . '">';
			},
			$html
		);
	}

	private static function company() {
		$d = self::$d;

		if ( ! $d['company'] ) {
			return '';
		}

		$css  = self::$s['css']['company'] ?? '';
		$text = esc_html( self::cased( 'company', $d['company'] ) );

		return '<div style="white-space:nowrap;' . esc_attr( $css ) . '">' . self::a( $d['company_link'] ?? '', $text, self::LINK . $css ) . '</div>';
	}

	/** The company logo, or the small logo, at the template's sizes for it. */
	private static function logo( $small, $align ) {
		$d    = self::$d;
		$logo = $small ? ( $d['logo_small'] ?? null ) : ( $d['logo'] ?? null );

		if ( ! $logo ) {
			return self::ph( $small ? 'small logo' : 'logo' );
		}

		$max_w = (int) ( $small ? ( self::$s['logo']['small_max_width'] ?? 60 ) : self::$s['logo']['max_width'] );
		$max_h = (int) ( $small ? ( self::$s['logo']['small_max_height'] ?? 0 ) : self::$s['logo']['max_height'] );

		list( $w, $h ) = SB_EmailSig_Data::fit( $logo, $max_w, $max_h );

		$img = '<img src="' . esc_url( SB_EmailSig_Data::src( $logo, $w ) ) . '" width="' . (int) $w . '"' . ( $h ? ' height="' . (int) $h . '"' : '' ) . ' alt="' . esc_attr( $logo['alt'] ?: $d['company'] ) . '" style="' . self::img_display( $align ) . 'width:' . (int) $w . 'px;height:' . ( $h ? (int) $h . 'px' : 'auto' ) . ';' . ( $max_h ? 'max-height:' . $max_h . 'px;' : '' ) . 'border:0;outline:none;" />';

		return self::a( $d['logo_link'] ?? '', $img );
	}

	/**
	 * An Info List's lines, in its order: each [ icon, text (array for
	 * several lines), link, label ]. An Additional Info List Item gets an
	 * icon only if this template has it.
	 */
	private static function rows( array $items ) {
		$d     = self::$d;
		$names = (array) ( self::$s['icons']['names'] ?? [] );
		$rows  = [];
		$extra = function ( $list ) use ( &$rows, $names ) {
			foreach ( (array) $list as $info ) {
				$rows[] = [ in_array( $info['icon'], $names, true ) ? $info['icon'] : '', $info['text'], $info['link'], $info['label'] ];
			}
		};

		foreach ( $items as $item ) {
			if ( empty( $item['on'] ) ) {
				continue;
			}

			switch ( $item['key'] ) {
				case 'phone':
					if ( $d['phone'] ) {
						$rows[] = [ 'phone', $d['phone'], $d['phone_link'], 'Phone' ];
					}
					break;
				case 'mobile':
					if ( $d['mobile'] ) {
						$rows[] = [ 'smartphone', $d['mobile'], $d['mobile_link'], 'Mobile' ];
					}
					break;
				case 'email':
					if ( $d['email'] ) {
						$rows[] = [ 'mail', $d['email'], 'mailto:' . $d['email'], 'Email' ];
					}
					break;
				case 'website':
					if ( $d['website'] ) {
						$rows[] = [ 'globe', $d['website_label'], $d['website_link'], 'Website' ];
					}
					break;
				case 'address':
					if ( $d['address_lines'] ) {
						$rows[] = [ 'map-pin', $d['address_lines'], $d['maps'], 'Address' ];
					}
					break;
				case 'info_person':
					$extra( $d['info_person'] ?? [] );
					break;
				default:
					// One of the company's (gone when the person hides them).
					foreach ( (array) ( $d['info_company'] ?? [] ) as $info ) {
						if ( 'c:' . ( $info['ref'] ?? '' ) === $item['key'] ) {
							$extra( [ $info ] );
						}
					}
			}
		}

		return $rows;
	}

	/**
	 * What starts a line: an icon cell, a letter or label cell, or nothing.
	 * $show is the Info List's own choice, '' for the Info List Styles card's.
	 */
	private static function lead( array $row, $va, $pad, $show = '' ) {
		$i    = self::$s['icons'] ?? [];
		$show = $show !== '' ? (string) $show : (string) ( $i['show'] ?? 'icon' );
		$size = (int) ( $i['size'] ?? 18 );

		if ( $show === 'letter' || $show === 'label' ) {
			$label = trim( (string) $row[3] );
			$label = self::cased( 'icon_label', $show === 'letter' ? mb_substr( $label, 0, 1, 'UTF-8' ) : $label );

			return '<td valign="' . $va . '" style="padding:' . $pad . ';padding-right:8px;vertical-align:' . $va . ';white-space:nowrap;' . esc_attr( $i['label'] ?? '' ) . '">' . esc_html( $label ) . '</td>';
		}

		if ( $show !== 'icon' ) {
			return '';
		}

		// In the block's or row's own colour when it has one and it's been drawn (the Layout page), else the usual.
		$key = self::$variant['contact'];
		$src = $row[0] !== '' && $key !== '' ? SB_EmailSig_Icons::contact_variant_url( self::$tpl, $row[0], $key ) : '';
		$src = $src !== '' ? $src : ( $row[0] !== '' ? SB_EmailSig_Icons::contact_url( self::$tpl, $row[0] ) : '' );
		$img = $row[0] !== '' ? '<img src="' . esc_url( $src ) . '" width="' . $size . '" height="' . $size . '" alt="" style="display:block;width:' . $size . 'px;height:' . $size . 'px;border:0;outline:none;"' . self::mark( 'data-sbes-icon', $row[0] ) . ( $key !== '' ? self::mark( 'data-sbes-variant', $key ) : '' ) . ' />' : '';

		return '<td valign="' . $va . '" width="' . ( $size + 8 ) . '" style="width:' . ( $size + 8 ) . 'px;min-width:' . ( $size + 8 ) . 'px;padding:' . $pad . ';vertical-align:' . $va . ';">' . $img . '</td>';
	}

	/** A line's text, linked when it has a link. $join puts several lines on one. */
	private static function text( array $row, $va, $pad, $join = '' ) {
		$css   = self::$s['css']['contact'] ?? '';
		$lines = array_map(
			function ( $line ) {
				return esc_html( self::cased( 'contact', $line ) );
			},
			(array) $row[1]
		);
		$text  = $join !== '' ? implode( esc_html( $join ), $lines ) : implode( '<br>', $lines );
		$wrap  = ( is_array( $row[1] ) && $join === '' ) ? '' : 'white-space:nowrap;';

		return '<td valign="' . $va . '" style="padding:' . $pad . ';vertical-align:' . $va . ';text-align:left;' . esc_attr( $css ) . $wrap . '">' . ( $row[2] ? self::a( $row[2], $text, self::LINK . $css ) : $text ) . '</td>';
	}

	/** An Info List: its lines stacked, or on one row with a separator. */
	private static function info( array $b ) {
		$rows = self::rows( $b['items'] );

		if ( ! $rows ) {
			return self::ph( 'Info List' );
		}

		$va  = esc_attr( self::$s['icons']['valign'] ?? 'top' );
		$gap = self::px( $b['spacing'] );
		$out = self::table( $b['align'] );

		if ( $b['style'] === 'row' ) {
			$sep = self::sep( $b['sep'] );
			$out .= '<tr>';

			foreach ( $rows as $n => $row ) {
				if ( $n ) {
					$out .= '<td valign="' . $va . '" style="padding:0 ' . $gap . 'px;vertical-align:' . $va . ';white-space:nowrap;' . esc_attr( ( self::$s['css']['contact'] ?? '' ) . 'color:' . self::$s['lines'] . ';' ) . '">' . ( $sep !== '' ? esc_html( $sep ) : '&nbsp;' ) . '</td>';
				}

				$out .= self::lead( $row, $va, '0', $b['lead'] ?? '' ) . self::text( $row, $va, '0', ', ' );
			}

			return $out . '</tr></table>';
		}

		foreach ( $rows as $n => $row ) {
			$pad  = ( $n ? $gap : 0 ) . 'px 0 0 0';
			$out .= '<tr>' . self::lead( $row, $va, $pad, $b['lead'] ?? '' ) . self::text( $row, $va, $pad ) . '</tr>';
		}

		return $out . '</table>';
	}

	/** Save my contact details: one line, like an Info List's, with the user-plus icon. */
	private static function vcard( array $b ) {
		$d = self::$d;

		if ( empty( $d['vcard_show'] ) || empty( $d['vcard_url'] ) ) {
			return '';
		}

		$va  = esc_attr( self::$s['icons']['valign'] ?? 'top' );
		$row = [ 'user-plus', $d['vcard_label'], $d['vcard_url'], '' ];

		return self::table( $b['align'] ) . '<tr>' . self::lead( $row, $va, '0' ) . self::text( $row, $va, '0' ) . '</tr></table>';
	}

	/** The social icons, with the heading from Settings, in a row or a column. */
	private static function social( array $b ) {
		$d = self::$d;
		$s = self::$s;

		if ( ! $d['socials'] ) {
			return self::ph( 'social icons' );
		}

		$size = (int) $s['social']['size'];
		$gap  = (int) $s['social']['gap'];
		$out  = '';

		if ( $b['heading'] && ! empty( $d['social_heading'] ) ) {
			$out .= '<div style="padding:0 0 6px 0;' . esc_attr( $s['css']['social_heading'] ?? '' ) . '">' . esc_html( self::cased( 'social_heading', $d['social_heading'] ) ) . '</div>';
		}

		$cells = [];
		$last  = count( $d['socials'] ) - 1;

		$key = self::$variant['social'];

		foreach ( $d['socials'] as $n => $soc ) {
			// In the block's or row's own colours when it has them and they've been drawn, else the usual.
			$src     = $key !== '' ? SB_EmailSig_Icons::social_variant_url( self::$tpl['id'], (int) $soc['row'], $key ) : '';
			$img     = '<img src="' . esc_url( $src !== '' ? $src : $soc['icon'] ) . '" width="' . $size . '" height="' . $size . '" alt="' . esc_attr( $soc['name'] ) . '" style="display:block;width:' . $size . 'px;height:' . $size . 'px;border:0;outline:none;"' . self::mark( 'data-sbes-social', $soc['row'] ) . ( $key !== '' ? self::mark( 'data-sbes-variant', $key ) : '' ) . ' />';
			$pad     = $b['direction'] === 'column' ? '0 0 ' . ( $n < $last ? $gap : 0 ) . 'px 0' : '0 ' . ( $n < $last ? $gap : 0 ) . 'px 0 0';
			$cells[] = '<td style="padding:' . $pad . ';">' . self::a( $soc['link'], $img ) . '</td>';
		}

		$out .= self::table( $b['align'] ) . ( $b['direction'] === 'column' ? '<tr>' . implode( '</tr><tr>', $cells ) . '</tr>' : '<tr>' . implode( '', $cells ) . '</tr>' ) . '</table>';

		// Lined up with an Info List's text: past the icon column.
		$lead = (string) ( $s['icons']['show'] ?? 'icon' );
		if ( $b['indent'] && $lead === 'icon' && $b['align'] === 'left' ) {
			$w   = (int) ( $s['icons']['size'] ?? 18 ) + 8;
			$out = '<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;"><tr><td width="' . $w . '" style="width:' . $w . 'px;padding:0;"></td><td style="padding:0;text-align:left;">' . $out . '</td></tr></table>';
		}

		return $out;
	}

	/** Below the columns: buttons, footer text, the campaign banner and banners. */
	private static function footer( array $layout = [] ) {
		$d     = self::$d;
		$hide  = function ( $part ) use ( $layout ) {
			return ! empty( $layout[ 'hide_' . $part ] );
		};
		$s     = self::$s;
		$width = (int) ( $s['width'] ?? 600 );
		$btn   = $s['buttons'] ?? [];
		$out   = '';

		// Footer Controls: a part left out leaves out its placeholder too.
		if ( ! $d['buttons'] && self::preview() && ! $hide( 'buttons' ) ) {
			$out .= '<tr><td style="padding:16px 0 0 0;">' . self::ph( 'buttons' ) . '</td></tr>';
		}

		if ( $d['buttons'] && $btn && ! $hide( 'buttons' ) ) {
			$out .= '<tr><td style="padding:16px 0 0 0;"><table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;"><tr>';

			foreach ( $d['buttons'] as $button ) {
				$look = $button['outline'] ? $btn['outline'] : $btn['solid'];
				$out .= '<td style="padding:0 ' . (int) $btn['gap'] . 'px 0 0;"><table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:separate;"><tr>';
				$out .= '<td' . ( $look['bg'] ? ' bgcolor="' . esc_attr( $look['bg'] ) . '"' : '' ) . ' style="border:2px solid ' . esc_attr( $look['border'] ) . ';border-radius:' . (int) $btn['radius'] . 'px;' . ( $look['bg'] ? 'background:' . esc_attr( $look['bg'] ) . ';' : '' ) . '">';
				$out .= '<a href="' . esc_url( $button['link'], [ 'http', 'https', 'mailto', 'tel' ] ) . '"' . self::NEW_WINDOW . ' style="display:inline-block;padding:' . (int) $btn['pad_y'] . 'px ' . (int) $btn['pad_x'] . 'px;font-family:' . esc_attr( $s['font'] ) . ';font-size:' . (int) $btn['font'] . 'px;line-height:' . (int) $btn['line'] . 'px;font-weight:' . esc_attr( $btn['weight'] ) . ';color:' . esc_attr( $look['text'] ) . ';text-decoration:none;white-space:nowrap;">' . esc_html( self::cased( 'buttons', $button['label'] ) ) . '</a>';
				$out .= '</td></tr></table></td>';
			}

			$out .= '</tr></table></td></tr>';
		}

		if ( ! $d['fine_print'] && self::preview() && ! $hide( 'text' ) ) {
			$out .= '<tr><td style="padding:14px 0 0 0;">' . self::ph( 'footer text' ) . '</td></tr>';
		}

		// -webkit-text-size-adjust: iPhone Mail enlarges text under about 13px,
		// so small footer text stays its own size there (Sam, 30 October 2026).
		if ( $d['fine_print'] && ! $hide( 'text' ) ) {
			$out .= '<tr><td style="padding:14px 0 0 0;"><div style="max-width:' . $width . 'px;-webkit-text-size-adjust:100%;' . esc_attr( $s['css']['fine_print'] ?? '' ) . '">' . wp_kses( self::cased( 'fine_print', $d['fine_print'] ), [ 'br' => [] ] ) . '</div></td></tr>';
		}

		if ( $d['campaign'] && ! $hide( 'campaign' ) ) {
			$cw   = min( (int) $d['campaign']['width'], $width );
			$img  = '<img src="' . esc_url( $d['campaign']['image'] ) . '" width="' . $cw . '" alt="" style="display:block;width:100%;max-width:' . $cw . 'px;height:auto;border:0;outline:none;" />';
			$out .= '<tr><td style="padding:16px 0 0 0;">' . self::a( $d['campaign']['link'], $img ) . '</td></tr>';
		}

		if ( ! $d['banners'] && self::preview() && ! $hide( 'banners' ) ) {
			$out .= '<tr><td style="padding:16px 0 0 0;">' . self::ph( 'banners', 'display:block;width:' . min( 600, $width ) . 'px;max-width:100%;padding:24px 0;text-align:center;box-sizing:border-box;' ) . '</td></tr>';
		}

		foreach ( $hide( 'banners' ) ? [] : $d['banners'] as $i => $banner ) {
			list( $bw, $bh ) = SB_EmailSig_Data::fit( $banner['image'], $width );

			$img  = '<img src="' . esc_url( SB_EmailSig_Data::src( $banner['image'], $bw ) ) . '" width="' . (int) $bw . '"' . ( $bh ? ' height="' . (int) $bh . '"' : '' ) . ' alt="' . esc_attr( $banner['image']['alt'] ) . '" style="display:block;width:100%;max-width:' . (int) $bw . 'px;height:auto;border:0;outline:none;" />';
			$out .= '<tr><td style="padding:' . ( $i ? '10' : '16' ) . 'px 0 0 0;">' . self::a( $banner['link'], $img ) . '</td></tr>';
		}

		return $out;
	}
}
