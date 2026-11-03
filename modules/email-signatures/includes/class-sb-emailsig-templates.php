<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Signature templates, and the Templates page.
 *
 * A design is a folder in templates/ holding template.php (tables and inline
 * styles only, for email clients), styles.php (its defaults) and anything it
 * needs, such as its own icons. Its name and description come from a header
 * in template.php:
 *
 *   Template Name: Classic
 *   Description:   One or two sentences for the Templates page.
 *
 * A template is a record (a hidden post of type sb_emailsig_tpl, since 29
 * October 2026): its post ID is the template's id, its title the name, and it
 * keeps the design it is drawn with (_sb_emailsig_design) and its saved
 * styles (_sb_emailsig_styles). Duplicate makes another from it, with the
 * same design and styles, to change from there.
 *
 * Which templates people use:
 * - The company's Default Template (company meta sb_emailsig_main_template,
 *   set on the company's Company tab) is everyone's in that company.
 * - The company's set (company meta sb_emailsig_templates) is the templates
 *   its people can pick on their signature page; the default one always is.
 * - People use their company's templates only (Sam, 1 November 2026): no
 *   main or extra templates of their own any more. What was kept for them
 *   (sb_emailsig_template, sb_emailsig_templates_extra) is deleted once
 *   (forget_person_picks()). They still pick one of the company's on their
 *   signature page, the company's default first.
 *
 * Templates page (Sam, 29 October 2026): the framework's bar (Settings,
 * Import, Add template) and a small card per template, like the people
 * cards: name, design, its description (the record's excerpt, at most 160
 * characters, set on Edit styles), its layout in numbers and how many people use it,
 * then Preview (a popup, list.js), Edit styles, Layout, Duplicate, Export
 * and Delete. Add template asks for a name (templates.js) and makes one on
 * the default design with its default layout and styles.
 *
 * Export (Templates page) downloads a template as a .json file: its name,
 * design and styles (format 2: the layout is part of the styles). Lock colours
 * writes every colour as the hex it shows now, else brand and theme colours
 * stay links and follow the other site's. Import makes a new template from
 * one, never over another, its styles checked like a save on Edit styles.
 *
 * Before records, a template was its folder, keyed by folder name, with its
 * styles in sb_tweaks_email_signatures_styles and its name in
 * sb_tweaks_email_signatures_template_names. migrate() makes a record from
 * each folder once, keeping those options for going back; the old ids still
 * find their record (sb_emailsig_template_ids). With no records (the move
 * not made yet), the folders are the templates as before.
 */
class SB_EmailSig_Templates {

	/** The design a site starts from. */
	const DEFAULT_TEMPLATE = 'classic';

	/** Template records. */
	const CPT = 'sb_emailsig_tpl';

	const DESIGN_META = '_sb_emailsig_design';
	const STYLES_META = '_sb_emailsig_styles';

	/** Before records: names given on the Edit styles pages, [ folder => name ]. */
	const NAMES_OPTION = 'sb_tweaks_email_signatures_template_names';

	/** [ folder => template id ], from the move to records. */
	const LEGACY_OPTION = 'sb_emailsig_template_ids';

	/** 1 once the folders have records. */
	const VERSION_OPTION = 'sb_emailsig_templates_version';

	/** Held while migrate() runs, so two requests don't both make records. */
	const LOCK_OPTION = 'sb_emailsig_templates_moving';

	/** ACF option: the company set. */
	const COMPANY_SET = 'sb_emailsig_templates';

	/** Export files: what they say they are, and the newest format this reads. */
	const FILE_TYPE   = 'sb-email-signature-template';
	const FILE_FORMAT = 2; // 2 (29 October 2026): the layout is in the styles. A format 1 file has none, so it starts with its design's.

	/** What a person's own template picks were kept as, before 1 November 2026: only forget_person_picks() uses them. */
	const PERSON_MAIN  = 'sb_emailsig_template';
	const PERSON_EXTRA = 'sb_emailsig_templates_extra';

	/** Templates, keyed by id. */
	private static $all = null;

	/** Design folders, keyed by folder name. */
	private static $designs = null;

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'init', [ __CLASS__, 'migrate' ], 20 );
		add_action( 'admin_post_sb_emailsig_duplicate_template', [ __CLASS__, 'duplicate' ] );
		add_action( 'admin_post_sb_emailsig_delete_template', [ __CLASS__, 'delete' ] );
		add_action( 'admin_post_sb_emailsig_export_template', [ __CLASS__, 'export' ] );
		add_action( 'admin_post_sb_emailsig_import_template', [ __CLASS__, 'import' ] );
		add_action( 'admin_post_sb_emailsig_add_template', [ __CLASS__, 'add' ] );
		add_action( 'admin_init', [ __CLASS__, 'forget_person_picks' ] );
	}

	public static function register() {
		register_post_type(
			self::CPT,
			[
				'label'               => 'Signature Templates',
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'supports'            => [ 'title' ],
			]
		);
	}

	/* ------------------------------------------------------------------ *
	 * Designs and templates
	 * ------------------------------------------------------------------ */

	/**
	 * Every design folder: id => name, description, file, path, url.
	 * Filterable through sb_emailsig/designs, so a theme or snippet can add one.
	 */
	public static function designs() {
		if ( self::$designs !== null ) {
			return self::$designs;
		}

		$found = [];

		foreach ( (array) glob( SB_EMAILSIG_DIR . 'templates/*/template.php' ) as $file ) {
			$id   = sanitize_key( basename( dirname( $file ) ) );
			$head = get_file_data(
				$file,
				[
					'name'        => 'Template Name',
					'description' => 'Description',
				]
			);

			$found[ $id ] = [
				'id'          => $id,
				'name'        => $head['name'] !== '' ? $head['name'] : ucfirst( $id ),
				'description' => $head['description'],
				'file'        => $file,
				'path'        => trailingslashit( dirname( $file ) ),
				'url'         => SB_EMAILSIG_URL . 'templates/' . basename( dirname( $file ) ) . '/',
			];
		}

		ksort( $found );

		self::$designs = (array) apply_filters( 'sb_emailsig/designs', $found );

		return self::$designs;
	}

	/** True once templates are records. */
	public static function has_records() {
		return (int) get_option( self::VERSION_OPTION, 0 ) >= 1;
	}

	/**
	 * Every template: id => name, default_name (the design's), description,
	 * design, file, path, url. In the order made, oldest first. PHP turns
	 * a key like '12' into a number, so use each entry's id (a string)
	 * rather than its key.
	 */
	public static function all() {
		if ( self::$all !== null ) {
			return self::$all;
		}

		$found   = [];
		$designs = self::designs();

		if ( self::has_records() ) {
			$ids = get_posts(
				[
					'post_type'        => self::CPT,
					'post_status'      => 'publish',
					'posts_per_page'   => -1,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
				]
			);

			// Over this site's limit (set from the hub), the newest are left out: switched off until it goes up.
			$over = class_exists( 'SB_EmailSig_Limits' ) ? SB_EmailSig_Limits::templates_over() : [];

			foreach ( (array) $ids as $id ) {
				if ( in_array( (int) $id, $over, true ) ) {
					continue;
				}

				$design = (string) get_post_meta( $id, self::DESIGN_META, true );

				if ( ! isset( $designs[ $design ] ) ) {
					continue;
				}

				$d     = $designs[ $design ];
				$title = trim( (string) get_post_field( 'post_title', $id, 'raw' ) );

				$found[ (string) $id ] = [
					'id'           => (string) $id,
					'name'         => $title !== '' ? $title : $d['name'],
					'default_name' => $d['name'],
					'description'  => $d['description'],
					'note'         => trim( (string) get_post_field( 'post_excerpt', $id, 'raw' ) ),
					'design'       => $design,
					'file'         => $d['file'],
					'path'         => $d['path'],
					'url'          => $d['url'],
				];
			}
		} else {
			// Not moved yet: each folder is a template, as before.
			$names = (array) get_option( self::NAMES_OPTION, [] );

			foreach ( $designs as $id => $d ) {
				$found[ $id ] = array_merge(
					$d,
					[
						'name'         => ! empty( $names[ $id ] ) ? (string) $names[ $id ] : $d['name'],
						'default_name' => $d['name'],
						'note'         => '',
						'design'       => $id,
					]
				);
			}
		}

		self::$all = $found;

		return self::$all;
	}

	/** Forget the found templates, after one is made or deleted. */
	public static function flush() {
		self::$all = null;
	}

	/** A template by id, or by the folder name it had before records. */
	public static function get( $id ) {
		$all = self::all();
		$id  = (string) $id;

		if ( isset( $all[ $id ] ) ) {
			return $all[ $id ];
		}

		$map = (array) get_option( self::LEGACY_OPTION, [] );

		return isset( $map[ $id ], $all[ (string) $map[ $id ] ] ) ? $all[ (string) $map[ $id ] ] : null;
	}

	/**
	 * The main template of the company being drawn (SB_EmailSig_Company):
	 * its own if it still exists, else the first template. Before there are
	 * companies, the one Settings kept.
	 */
	public static function current() {
		return self::main_for( class_exists( 'SB_EmailSig_Company' ) ? SB_EmailSig_Company::current() : 0 );
	}

	/** A company's main template ($co 0: the one Settings kept). */
	public static function main_for( $co ) {
		$saved = self::get( (string) ( $co ? get_post_meta( (int) $co, 'sb_emailsig_main_template', true ) : get_option( SB_EmailSig_Module::TEMPLATE_OPTION, '' ) ) );

		if ( $saved ) {
			return $saved['id'];
		}

		$all = self::all();

		return $all ? (string) key( $all ) : '';
	}

	/** Every company's main template (a template that is one can't be deleted). */
	public static function mains() {
		$out = [ self::current() ];

		foreach ( class_exists( 'SB_EmailSig_Company' ) ? SB_EmailSig_Company::all() : [] as $co ) {
			$out[] = self::main_for( $co );
		}

		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * The companies a template is the default for, and the others whose set
	 * it is in: [ 'default' => ids, 'available' => ids ] (Sam, 1 November 2026).
	 */
	public static function uses( $tpl_id ) {
		$out = [ 'default' => [], 'available' => [] ];

		foreach ( class_exists( 'SB_EmailSig_Company' ) ? SB_EmailSig_Company::all() : [] as $co ) {
			if ( self::main_for( $co ) === (string) $tpl_id ) {
				$out['default'][] = (int) $co;
			} elseif ( in_array( (string) $tpl_id, self::set_for( $co ), true ) ) {
				$out['available'][] = (int) $co;
			}
		}

		return $out;
	}

	/** Companies as items for SB_EmailSig_Admin::name_list(): [ name, its page ]. */
	public static function company_items( array $ids ) {
		return array_map(
			function ( $co ) {
				return [ SB_EmailSig_Company::name( $co ), class_exists( 'SB_EmailSig_Companies' ) ? SB_EmailSig_Companies::url( $co ) : '' ];
			},
			$ids
		);
	}

	/** Ids from a stored list that are still templates, without repeats. */
	private static function ids( $list ) {
		$out = [];

		foreach ( (array) $list as $id ) {
			$tpl = self::get( (string) $id );

			if ( $tpl ) {
				$out[ $tpl['id'] ] = true;
			}
		}

		// Keys like '12' come back as numbers: ids are strings throughout.
		return array_map( 'strval', array_keys( $out ) );
	}

	/** A company's set, its main first ($co 0: the one Settings kept). */
	public static function set_for( $co ) {
		$set = $co ? get_post_meta( (int) $co, self::COMPANY_SET, true ) : get_option( 'options_' . self::COMPANY_SET, [] );

		return self::ordered( self::main_for( $co ), self::ids( $set ) );
	}

	/** $main first, then the rest in the templates' order. */
	private static function ordered( $main, array $ids ) {
		$out = $main !== '' ? [ $main ] : [];

		foreach ( array_keys( self::all() ) as $id ) {
			$id = (string) $id;

			if ( $id !== $main && in_array( $id, $ids, true ) ) {
				$out[] = $id;
			}
		}

		return $out;
	}

	/** A person's template: their company's default (Sam, 1 November 2026: none of their own any more). */
	public static function for_person( $post_id ) {
		return self::main_for( class_exists( 'SB_EmailSig_Company' ) ? SB_EmailSig_Company::of_person( $post_id ) : 0 );
	}

	/**
	 * The templates a person can use: their company's set, its default
	 * first. $co (the person page's unsaved company) stands in for their
	 * saved one when given.
	 */
	public static function available( $post_id, $co = null ) {
		$co = $co !== null && class_exists( 'SB_EmailSig_Company' ) && SB_EmailSig_Company::valid( $co ) ? (int) $co : ( class_exists( 'SB_EmailSig_Company' ) ? SB_EmailSig_Company::of_person( $post_id ) : 0 );

		return self::set_for( $co );
	}

	/** Set once people's own template picks have been deleted. */
	const PICKS_GONE = 'sb_emailsig_person_templates_gone';

	/** People's own main and extra templates, deleted once (Sam, 1 November 2026). */
	public static function forget_person_picks() {
		if ( get_option( self::PICKS_GONE ) ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- once.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE m FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = %s AND m.meta_key IN ( %s, %s, %s, %s )",
				SB_EMAILSIG_CPT,
				self::PERSON_MAIN,
				'_' . self::PERSON_MAIN,
				self::PERSON_EXTRA,
				'_' . self::PERSON_EXTRA
			)
		);
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'post_meta' );
		}
		update_option( self::PICKS_GONE, 1, true );
	}

	/** An asset inside a template's design folder, such as icons/phone.png. */
	public static function asset_url( $template, $path ) {
		return $template['url'] . ltrim( $path, '/' );
	}

	/** A template's saved styles (not merged over its defaults). */
	public static function stored_styles( array $tpl ) {
		if ( self::has_records() && ctype_digit( (string) $tpl['id'] ) ) {
			$saved = get_post_meta( (int) $tpl['id'], self::STYLES_META, true );
		} else {
			$all   = (array) get_option( SB_EmailSig_Styles::OPTION, [] );
			$saved = $all[ $tpl['id'] ] ?? [];
		}

		return is_array( $saved ) ? $saved : [];
	}

	/** Save a template's styles (cleaned already) and name ('' for the design's). */
	public static function save( array $tpl, array $styles, $name ) {
		$name = trim( (string) $name );

		if ( self::has_records() && ctype_digit( (string) $tpl['id'] ) ) {
			update_post_meta( (int) $tpl['id'], self::STYLES_META, $styles );
			wp_update_post(
				[
					'ID'         => (int) $tpl['id'],
					'post_title' => $name !== '' ? $name : $tpl['default_name'],
				]
			);
		} else {
			$all              = (array) get_option( SB_EmailSig_Styles::OPTION, [] );
			$all[ $tpl['id'] ] = $styles;
			update_option( SB_EmailSig_Styles::OPTION, $all );

			$names = (array) get_option( self::NAMES_OPTION, [] );

			if ( $name === '' || $name === $tpl['default_name'] ) {
				unset( $names[ $tpl['id'] ] );
			} else {
				$names[ $tpl['id'] ] = $name;
			}

			update_option( self::NAMES_OPTION, $names );
		}

		self::flush();
	}

	/** Longest a template's description can be: it shows on its card (Sam, 29 October 2026). */
	const NOTE_MAX = 160;

	/** A description cleaned: one line of plain text, at most NOTE_MAX characters. */
	public static function clean_note( $text ) {
		$text = trim( preg_replace( '/\s+/u', ' ', sanitize_textarea_field( (string) $text ) ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::NOTE_MAX ) : substr( $text, 0, self::NOTE_MAX );
	}

	/** Save a template's own description (records only: it is the record's excerpt). */
	public static function set_note( array $tpl, $text ) {
		if ( ! self::has_records() || ! ctype_digit( (string) $tpl['id'] ) ) {
			return;
		}

		wp_update_post(
			[
				'ID'           => (int) $tpl['id'],
				'post_excerpt' => self::clean_note( $text ),
			]
		);

		self::flush();
	}

	/** A new template record. Returns its id, or '' if it couldn't be made. */
	private static function make( $design, $name, array $styles, $note = '' ) {
		$id = wp_insert_post(
			[
				'post_type'    => self::CPT,
				'post_status'  => 'publish',
				'post_title'   => (string) $name,
				'post_excerpt' => self::clean_note( $note ),
			],
			true
		);

		if ( ! $id || is_wp_error( $id ) ) {
			return '';
		}

		update_post_meta( $id, self::DESIGN_META, (string) $design );

		if ( $styles ) {
			update_post_meta( $id, self::STYLES_META, $styles );
		}

		self::flush();

		return (string) $id;
	}

	/**
	 * Once: a record for each design folder, with the styles and name saved
	 * for it, its drawn icons, the site's choice as the company's main, and
	 * the main as the company set. The old options stay, for going back.
	 */
	public static function migrate() {
		if ( self::has_records() ) {
			return;
		}

		// One request at a time; a stuck lock clears after a minute.
		if ( ! add_option( self::LOCK_OPTION, time(), '', 'no' ) ) {
			if ( time() - (int) get_option( self::LOCK_OPTION, 0 ) < 60 ) {
				return;
			}
			update_option( self::LOCK_OPTION, time(), false );
		}

		$styles = (array) get_option( SB_EmailSig_Styles::OPTION, [] );
		$names  = (array) get_option( self::NAMES_OPTION, [] );
		$map    = (array) get_option( self::LEGACY_OPTION, [] );

		foreach ( self::designs() as $folder => $d ) {
			// Made by a run that stopped part way.
			if ( isset( $map[ $folder ] ) && get_post_type( (int) $map[ $folder ] ) === self::CPT ) {
				continue;
			}

			$saved = isset( $styles[ $folder ] ) && is_array( $styles[ $folder ] ) ? $styles[ $folder ] : [];
			$id    = self::make( $folder, ! empty( $names[ $folder ] ) ? (string) $names[ $folder ] : $d['name'], $saved );

			if ( $id === '' ) {
				delete_option( self::LOCK_OPTION );

				return;
			}

			$map[ $folder ] = (int) $id;
			update_option( self::LEGACY_OPTION, $map, false );
		}

		$saved = (string) get_option( SB_EmailSig_Module::TEMPLATE_OPTION, '' );
		$main  = $map[ $saved ] ?? ( $map[ self::DEFAULT_TEMPLATE ] ?? ( $map ? reset( $map ) : 0 ) );

		if ( $main ) {
			update_option( SB_EmailSig_Module::TEMPLATE_OPTION, (string) $main );

			// Set straight in ACF's own option, as the fields may not be loaded.
			if ( get_option( 'options_' . self::COMPANY_SET, null ) === null ) {
				update_option( 'options_' . self::COMPANY_SET, [ (string) $main ] );
				update_option( '_options_' . self::COMPANY_SET, 'field_sb_emailsig_templates' );
			}
		}

		// The icons drawn for each folder, now kept under its template's id too.
		$icons = (array) get_option( SB_EmailSig_Icons::OPTION, [] );

		foreach ( $map as $folder => $id ) {
			if ( isset( $icons[ $folder ] ) && ! isset( $icons[ (string) $id ] ) ) {
				$icons[ (string) $id ] = $icons[ $folder ];
			}
		}

		update_option( SB_EmailSig_Icons::OPTION, $icons, false );
		update_option( self::VERSION_OPTION, 1 );
		delete_option( self::LOCK_OPTION );

		self::flush();
	}

	/* ------------------------------------------------------------------ *
	 * Templates page
	 * ------------------------------------------------------------------ */

	/** The person shown in the previews: the first one by name, or 0 for sample details. */
	public static function preview_person() {
		$people = get_posts(
			[
				'post_type'      => SB_EmailSig_Module::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			]
		);

		return $people ? (int) $people[0] : 0;
	}

	/** Data for a preview: that person, or sample details, marked as a preview. */
	public static function preview_data( $person, $tpl_id = '', $bare = false ) {
		$d            = $person ? SB_EmailSig_Data::get( $person, $tpl_id ) : SB_EmailSig_Data::sample( $tpl_id );
		$d['preview'] = true;

		// Bare: no grey stand-ins for empty parts (the builder's switch off).
		if ( $bare ) {
			$d['bare'] = true;
		}

		return $d;
	}

	public static function edit_url( $id ) {
		return admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-templates&edit=' . rawurlencode( $id ) );
	}

	private static function page_url( array $args = [] ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-templates' ) );
	}

	public static function render_page() {
		// One template's Edit styles page.
		$editing = SB_EmailSig_Styles::editing();

		if ( $editing ) {
			if ( SB_EmailSig_Styles::on_layout_page( $editing ) ) {
				SB_EmailSig_Styles::render_layout( $editing );
			} else {
				SB_EmailSig_Styles::render_editor( $editing );
			}

			return;
		}

		$all     = self::all();
		$current = self::current();
		$multi   = class_exists( 'SB_EmailSig_Company' ) && count( SB_EmailSig_Company::all() ) > 1;
		$notice  = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$records = self::has_records();

		$notices = [
			'deleted'  => [ 'success', 'Template deleted.' ],
			'failed'   => [ 'error', 'That didn\'t work. Try again.' ],
			'badfile'  => [ 'error', 'That file isn\'t a signature template export.' ],
			'newer'    => [ 'error', 'That file was exported from a newer version of this plugin. Update it here first.' ],
			'nodesign' => [ 'error', 'That template is built on a design this site doesn\'t have.' ],
			'limit'    => [ 'error', class_exists( 'SB_EmailSig_Limits' ) ? SB_EmailSig_Limits::message( 'templates' ) : '' ],
		];

		if ( isset( $notices[ $notice ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $notices[ $notice ][0] ) . ' is-dismissible"><p>' . esc_html( $notices[ $notice ][1] ) . '</p></div>';
		}

		// Used by (Sam, 1 November 2026): the templates a company uses, or those none do.
		$companies = class_exists( 'SB_EmailSig_Company' ) ? SB_EmailSig_Company::all() : [];
		$used      = isset( $_GET['used'] ) ? sanitize_key( wp_unslash( $_GET['used'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$used      = ( $used === 'none' || in_array( (int) $used, $companies, true ) ) ? $used : '';

		// The bar: Used by, where each company's default is chosen, and Add template. Import is on the Import/Export page.
		echo '<div class="sb-ui-bar sb-emailsig-bar">';
		echo '<div class="sb-ui-bar__group"><label class="sb-ui-bar__label" for="sb-emailsig-used">Used by</label>';
		echo '<select id="sb-emailsig-used" data-sb-emailsig-used="' . esc_url( self::page_url() ) . '"><option value="">All companies</option>';
		foreach ( $companies as $co ) {
			echo '<option value="' . (int) $co . '"' . selected( $used, (string) $co, false ) . '>' . esc_html( SB_EmailSig_Company::name( $co ) ) . '</option>';
		}
		echo '<option value="none"' . selected( $used, 'none', false ) . '>Not in use</option>';
		echo '</select></div>';
		echo '<div class="sb-ui-bar__group"><span class="sb-ui-bar__label">Default Template</span>';
		echo '<a class="button button-small" href="' . esc_url( SB_EmailSig_Companies::url() ) . '" data-sb-tip="Each company\'s Default Template and the ones its people can pick">' . SB_EmailSig_Admin::icon( 'gear' ) . 'Companies</a>';
		echo '</div>';

		if ( $records ) {
			// Add template: a name in a popup (templates.js), then this form.
			// How many, "Templates 2 of 2", and Add template greyed out at the limit (SB_EmailSig_Limits).
			echo '<div class="sb-ui-bar__end">' . SB_EmailSig_Limits::count( 'templates' );
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-sb-emailsig-add-template>';
			echo '<input type="hidden" name="action" value="sb_emailsig_add_template">';
			echo '<input type="hidden" name="sb_emailsig_name" value="">';
			wp_nonce_field( 'sb_emailsig_add_template' );
			echo SB_EmailSig_Limits::button( 'templates', '<button type="button" class="button button-primary" data-sb-emailsig-add-template-open>' . SB_EmailSig_Admin::icon( 'plus' ) . 'Add template</button>' );
			echo '</form></div>';
		}

		echo '</div>';

		if ( ! $all ) {
			echo '<div class="sb-ui-empty"><p>No templates found.</p></div>';

			return;
		}

		// The main one first (every company's, with more than one).
		foreach ( array_reverse( $multi ? self::mains() : [ $current ] ) as $main ) {
			if ( isset( $all[ $main ] ) ) {
				$all = [ $main => $all[ $main ] ] + $all;
			}
		}

		$using = self::use_counts();
		$shown = 0;

		echo '<div class="sb-emailsig-people sb-emailsig-templates">';

		foreach ( $all as $id => $template ) {
			$id      = (string) $id;
			$uses    = self::uses( $id );
			$is_main = (bool) $uses['default'] || $id === $current;

			// Used by: this company's (its default or one of its set), or no one's.
			// Every card is drawn, the ones that don't match hidden, so the menu
			// filters on the page without loading it again (templates.js, Sam,
			// 3 November 2026).
			$by    = array_values( array_unique( array_map( 'intval', array_merge( $uses['default'], $uses['available'] ) ) ) );
			$match = $used === '' || ( $used === 'none' ? ! $by : in_array( (int) $used, $by, true ) );
			$shown += $match ? 1 : 0;

			$stats   = self::stats( $template );
			$sig     = 'sb-emailsig-tpl-' . $id;
			$edit    = self::edit_url( $id );
			$people  = $using[ $id ] ?? 0;

			echo '<article class="sb-tweaks-card sb-emailsig-template' . ( $is_main ? ' is-main' : '' ) . ( $match ? '' : ' is-filtered' ) . '" data-sb-emailsig-used-by="' . esc_attr( implode( ',', $by ) ) . '">';

			echo '<div class="sb-emailsig-template__top">';
			echo '<div class="sb-emailsig-person__who">';
			echo '<h3 class="sb-emailsig-person__name"><a href="' . esc_url( $edit ) . '">' . esc_html( $template['name'] ) . '</a></h3>';
			echo '</div>';
			echo '</div>';

			// Its own description, set on Edit styles. Always there, empty when it
			// has none, so every card has the same rows and Default for and
			// Available for line up across a row (subgrid, admin.css; Sam, 3 November 2026).
			echo '<p class="sb-emailsig-template__note">' . esc_html( $template['note'] ) . '</p>';

			// The companies it's the Default Template for, and those that also
			// offer it (Sam, 1 November 2026), or Not in use.
			echo '<div class="sb-emailsig-template__uses">';
			if ( $uses['default'] || $uses['available'] ) {
				echo $uses['default'] ? SB_EmailSig_Admin::name_list( 'Default for:', self::company_items( $uses['default'] ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in name_list().
				echo $uses['available'] ? SB_EmailSig_Admin::name_list( 'Available for:', self::company_items( $uses['available'] ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in name_list().
			} else {
				echo SB_EmailSig_Admin::name_list( 'Used by:', [], 'Not in use' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in name_list().
			}
			echo '</div>';

			// What's in it, in numbers.
			echo '<div class="sb-emailsig-template__stats">';

			if ( $stats ) {
				echo '<span><strong>' . (int) $stats['columns'] . '</strong> ' . esc_html( _n( 'column', 'columns', $stats['columns'] ) ) . '</span>';
				echo '<span><strong>' . (int) $stats['blocks'] . '</strong> ' . esc_html( _n( 'widget', 'widgets', $stats['blocks'] ) ) . '</span>';

				// Always shown, so the four sit two by two (Sam, 30 October 2026).
				echo '<span data-sb-tip="In Not in Use, so not shown"><strong>' . (int) $stats['hidden'] . '</strong> hidden</span>';
			}

			echo '<span data-sb-tip="People whose Default Template this is"><strong>' . (int) $people . '</strong> ' . esc_html( _n( 'person', 'people', $people ) ) . '</span>';
			echo '</div>';

			// Preview's footer (Sam, 31 October 2026): Edit styles and Edit Layout
			// on the left, with the card's icons, and only Close on the right.
			$foot = array( array( $edit, 'Edit styles', SB_EmailSig_Admin::icon( 'palette' ) ) );
			if ( $stats ) {
				$foot[] = array( SB_EmailSig_Styles::layout_url( $id ), 'Edit Layout', SB_EmailSig_Admin::icon( 'columns' ) );
			}

			echo '<div class="sb-emailsig-person__actions">';
			echo '<button type="button" class="button button-small button-primary" data-sb-emailsig-preview="' . esc_attr( $sig ) . '" data-sb-emailsig-name="' . esc_attr( $template['name'] ) . '" data-sb-emailsig-edit="' . esc_url( $edit ) . '" data-sb-emailsig-action="Edit styles" data-sb-emailsig-foot="' . esc_attr( wp_json_encode( $foot ) ) . '" data-sb-emailsig-close-icon="' . esc_attr( SB_EmailSig_Admin::icon( 'close' ) ) . '"' . SB_EmailSig_Admin::people_pick( $id ) . '>' . SB_EmailSig_Admin::icon( 'eye' ) . 'Preview</button>';
			echo '<a class="button button-small sb-emailsig-icon" href="' . esc_url( $edit ) . '" data-sb-tip="Edit styles">' . SB_EmailSig_Admin::icon( 'palette' ) . '</a>';

			if ( $stats ) {
				echo '<a class="button button-small sb-emailsig-icon" href="' . esc_url( SB_EmailSig_Styles::layout_url( $id ) ) . '" data-sb-tip="Layout">' . SB_EmailSig_Admin::icon( 'columns' ) . '</a>';
			}

			if ( $records ) {
				self::icon_form( 'sb_emailsig_duplicate_template', $id, 'copy', 'Duplicate' );

				// Export asks about Lock colours first (templates.js).
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-sb-emailsig-export="' . esc_attr( $template['name'] ) . '">';
				echo '<input type="hidden" name="action" value="sb_emailsig_export_template">';
				echo '<input type="hidden" name="sb_emailsig_template" value="' . esc_attr( $id ) . '">';
				echo '<input type="hidden" name="lock" value="">';
				wp_nonce_field( 'sb_emailsig_export_template_' . $id );
				echo '<button type="button" class="button button-small sb-emailsig-icon" data-sb-emailsig-export-open data-sb-tip="Export">' . SB_EmailSig_Admin::icon( 'down' ) . '</button>';
				echo '</form>';

				if ( ! $is_main ) {
					self::icon_form( 'sb_emailsig_delete_template', $id, 'trash', 'Delete', 'Delete the ' . $template['name'] . ' template? Anyone using it goes back to their company\'s Default Template.' );
				}
			}

			echo '</div>';

			// The preview's HTML, for the popup (list.js).
			$html = SB_EmailSig_Render::html_from_data( SB_EmailSig_Data::placeholders( $id ), $id );
			echo '<script type="application/json" id="' . esc_attr( $sig ) . '-html">' . wp_json_encode( $html, JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';

			echo '</article>';
		}

		echo '</div>';

		// Shown when nothing matches; templates.js keeps it in step with the menu.
		echo '<div class="sb-ui-empty" data-sb-emailsig-used-none' . ( $shown ? ' hidden' : '' ) . '><p data-sb-emailsig-used-none-text data-none="Every template is in use." data-company="No templates for that company.">' . esc_html( $used === 'none' ? 'Every template is in use.' : 'No templates for that company.' ) . '</p></div>';
	}

	/** A small form posting to admin-post.php, as an icon button with a tooltip. Asks first with $confirm. */
	private static function icon_form( $action, $id, $icon, $label, $confirm = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="sb_emailsig_template" value="' . esc_attr( $id ) . '">';
		wp_nonce_field( $action . '_' . $id );

		// Duplicate makes a template: greyed out at the limit (SB_EmailSig_Limits).
		if ( $action === 'sb_emailsig_duplicate_template' && SB_EmailSig_Limits::full( 'templates' ) ) {
			echo '<span class="sb-emailsig-limit-lock" data-sb-tip="' . esc_attr( SB_EmailSig_Limits::message( 'templates' ) ) . '" tabindex="0"><span class="button button-small sb-emailsig-icon disabled" aria-disabled="true">' . SB_EmailSig_Admin::icon( $icon ) . '</span></span></form>';

			return;
		}

		echo '<button type="submit" class="button button-small sb-emailsig-icon' . ( $icon === 'trash' ? ' sb-emailsig-icon--danger' : '' ) . '" data-sb-tip="' . esc_attr( $label ) . '"';

		if ( $confirm !== '' ) {
			echo ' data-sb-tweaks-confirm="' . esc_attr( $confirm ) . '" data-sb-tweaks-confirm-title="' . esc_attr( $label . ' template' ) . '" data-sb-tweaks-confirm-action="' . esc_attr( $label ) . '"';
		}

		echo '>' . SB_EmailSig_Admin::icon( $icon ) . '</button>';
		echo '</form>';
	}

	/** A template's layout in numbers: columns, blocks in them, blocks held back. [] for a design with no layout. */
	public static function stats( array $tpl ) {
		$values = SB_EmailSig_Styles::values( $tpl );
		$layout = $values['layout'] ?? null;

		$cols = is_array( $layout ) ? SB_EmailSig_Layout::all_columns( $layout ) : [];

		if ( ! $cols ) {
			return [];
		}

		$blocks = 0;

		foreach ( $cols as $col ) {
			$blocks += count( (array) ( $col['blocks'] ?? [] ) );
		}

		return [
			'columns' => count( $cols ),
			'blocks'  => $blocks,
			'hidden'  => count( (array) ( $layout['hold'] ?? [] ) ),
		];
	}

	/** How many people (active or not) have each template as their main: [ id => count ]. */
	public static function use_counts() {
		$out = [];

		foreach ( SB_EmailSig_Admin::people( [ 'publish', 'draft' ] ) as $person ) {
			$id         = self::for_person( $person );
			$out[ $id ] = ( $out[ $id ] ?? 0 ) + 1;
		}

		return $out;
	}

	/** Add template: a new one on the default design, with its default layout and styles, then its Edit styles page. */
	public static function add() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do that.' );
		}

		check_admin_referer( 'sb_emailsig_add_template' );

		// This site's limit, set from the hub (SB_EmailSig_Limits).
		if ( SB_EmailSig_Limits::full( 'templates' ) ) {
			wp_safe_redirect( self::page_url( [ 'template' => 'limit' ] ) );
			exit;
		}

		$designs = self::designs();
		$design  = isset( $designs[ self::DEFAULT_TEMPLATE ] ) ? self::DEFAULT_TEMPLATE : (string) key( $designs );
		$name    = isset( $_POST['sb_emailsig_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['sb_emailsig_name'] ) ) ) : '';
		$name    = $name !== '' ? $name : 'New template';
		$new     = ( self::has_records() && $design !== '' ) ? self::make( $design, $name, [] ) : '';

		if ( $new === '' ) {
			wp_safe_redirect( self::page_url( [ 'template' => 'failed' ] ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'added', '1', self::edit_url( $new ) ) );
		exit;
	}

	/** The template a Templates page button was for, checked. */
	private static function posted( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do that.' );
		}

		$id = isset( $_POST['sb_emailsig_template'] ) ? sanitize_key( wp_unslash( $_POST['sb_emailsig_template'] ) ) : '';

		check_admin_referer( $action . '_' . $id );

		$tpl = self::get( $id );

		return ( $tpl && self::has_records() && $tpl['id'] === $id ) ? $tpl : null;
	}

	/** Duplicate: a copy with the same design and styles, then its Edit styles page. */
	public static function duplicate() {
		$tpl = self::posted( 'sb_emailsig_duplicate_template' );

		if ( $tpl && SB_EmailSig_Limits::full( 'templates' ) ) {
			wp_safe_redirect( self::page_url( [ 'template' => 'limit' ] ) );
			exit;
		}

		$new = $tpl ? self::make( $tpl['design'], $tpl['name'] . ' (copy)', self::stored_styles( $tpl ), $tpl['note'] ) : '';

		if ( $new === '' ) {
			wp_safe_redirect( self::page_url( [ 'template' => 'failed' ] ) );
			exit;
		}

		// Its icons look the same until its styles change.
		$icons = (array) get_option( SB_EmailSig_Icons::OPTION, [] );

		if ( isset( $icons[ $tpl['id'] ] ) ) {
			$icons[ $new ] = $icons[ $tpl['id'] ];
			update_option( SB_EmailSig_Icons::OPTION, $icons, false );
		}

		wp_safe_redirect( add_query_arg( 'copied', '1', self::edit_url( $new ) ) );
		exit;
	}

	/** Export: the template as a .json file to import on another site. */
	public static function export() {
		$tpl = self::posted( 'sb_emailsig_export_template' );

		if ( ! $tpl ) {
			wp_safe_redirect( self::page_url( [ 'template' => 'failed' ] ) );
			exit;
		}

		$lock   = ! empty( $_POST['lock'] ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in posted().
		$styles = SB_EmailSig_Styles::sanitize( $tpl, SB_EmailSig_Styles::values( $tpl ) );
		$data   = [
			'type'     => self::FILE_TYPE,
			'format'   => self::FILE_FORMAT,
			'name'     => $tpl['name'],
			'note'     => $tpl['note'],
			'design'   => $tpl['design'],
			'locked'   => $lock,
			'exported' => gmdate( 'c' ),
			'from'     => wp_parse_url( home_url(), PHP_URL_HOST ),
			'styles'   => $lock ? SB_EmailSig_Styles::locked( $tpl, $styles ) : $styles,
		];
		$file   = sanitize_title( $tpl['name'] ) . '-signature-template.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput -- a JSON download.
		exit;
	}

	/**
	 * Import: a new template from an export file, its styles checked like a
	 * save, then its Edit styles page (saving there draws its icons). Its
	 * name gets (imported) when the site already has one by that name.
	 */
	/**
	 * Template import's card on the Import/Export page (Sam, 3 November 2026,
	 * from the Templates page's bar): pick a file exported from another site
	 * and it's added straight away (list.js picks it, import() reads it), then
	 * opens to check. A file that can't be used comes back here saying why.
	 */
	public static function render_import_card() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$why = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$say = [
			'failed'   => 'That didn\'t work. Try again.',
			'badfile'  => 'That file isn\'t a signature template export.',
			'newer'    => 'That file was exported from a newer version of this plugin. Update it here first.',
			'nodesign' => 'That template is built on a design this site doesn\'t have.',
			'limit'    => class_exists( 'SB_EmailSig_Limits' ) ? SB_EmailSig_Limits::message( 'templates' ) : '',
		];

		if ( isset( $say[ $why ] ) && $say[ $why ] !== '' ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $say[ $why ] ) . '</p></div>';
		}

		echo '<div class="sb-tweaks-card sb-emailsig-transfer">';
		echo '<div class="sb-tweaks-card__head"><h3>Template file</h3></div>';
		echo '<p class="sb-tweaks-card__desc">A template exported from another site (each template card has Export). It\'s added as a new template and opens so you can check it.</p>';
		echo '<div class="sb-emailsig-transfer__body"><div class="sb-emailsig-transfer__actions">';

		if ( ! self::has_records() ) {
			echo '<p class="description">Templates can be imported once this site has its own templates.</p>';
		} else {
			echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-sb-emailsig-import>';
			echo '<input type="hidden" name="action" value="sb_emailsig_import_template">';
			wp_nonce_field( 'sb_emailsig_import_template' );
			echo '<input type="file" name="sb_emailsig_file" accept=".json,application/json" required aria-label="Template file" class="screen-reader-text" tabindex="-1">';
			echo SB_EmailSig_Limits::full( 'templates' )
				? '<span class="sb-emailsig-limit-lock" data-sb-tip="' . esc_attr( SB_EmailSig_Limits::message( 'templates' ) ) . '" tabindex="0"><span class="button button-small disabled" aria-disabled="true">' . SB_EmailSig_Admin::icon( 'up' ) . 'Import</span></span>' // phpcs:ignore WordPress.Security.EscapeOutput -- fixed icon markup.
				: '<button type="button" class="button button-small" data-sb-emailsig-import-pick data-sb-tip="Add a template exported from another site">' . SB_EmailSig_Admin::icon( 'up' ) . 'Import</button>'; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed icon markup.
			echo '</form>';
		}

		echo '</div></div></div>';
	}

	public static function import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do that.' );
		}

		check_admin_referer( 'sb_emailsig_import_template' );

		// Back to the Import/Export page, saying why (render_import_card()).
		$fail = function ( $why ) {
			wp_safe_redirect( add_query_arg( 'template', $why, admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-transfer' ) ) );
			exit;
		};
		if ( SB_EmailSig_Limits::full( 'templates' ) ) {
			$fail( 'limit' );
		}

		$up   = isset( $_FILES['sb_emailsig_file'] ) && is_array( $_FILES['sb_emailsig_file'] ) ? $_FILES['sb_emailsig_file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read as JSON and checked below.

		if ( ! self::has_records() || ! $up || ( $up['error'] ?? 1 ) !== UPLOAD_ERR_OK || ! is_uploaded_file( $up['tmp_name'] ) || (int) $up['size'] > 200000 ) {
			$fail( 'badfile' );
		}

		$data = json_decode( (string) file_get_contents( $up['tmp_name'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( ! is_array( $data ) || ( $data['type'] ?? '' ) !== self::FILE_TYPE || ! is_array( $data['styles'] ?? null ) ) {
			$fail( 'badfile' );
		}

		if ( (int) ( $data['format'] ?? 0 ) > self::FILE_FORMAT ) {
			$fail( 'newer' );
		}

		$designs = self::designs();
		$design  = sanitize_key( (string) ( $data['design'] ?? '' ) );

		if ( ! isset( $designs[ $design ] ) ) {
			$fail( 'nodesign' );
		}

		$name  = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		$name  = $name !== '' ? $name : $designs[ $design ]['name'];
		$names = wp_list_pluck( self::all(), 'name' );

		if ( in_array( $name, $names, true ) ) {
			$name .= ' (imported)';
		}

		// Checked as Edit styles checks a save, against the design's defaults.
		$base   = array_merge( $designs[ $design ], [ 'id' => '', 'design' => $design, 'default_name' => $designs[ $design ]['name'] ] );
		$styles = SB_EmailSig_Styles::sanitize( $base, $data['styles'] );
		$new    = self::make( $design, $name, $styles, (string) ( $data['note'] ?? '' ) );

		if ( $new === '' ) {
			$fail( 'failed' );
		}

		wp_safe_redirect( add_query_arg( 'imported', '1', self::edit_url( $new ) ) );
		exit;
	}

	/** Delete: never the main one. Anyone using it falls back to the main. */
	public static function delete() {
		$tpl = self::posted( 'sb_emailsig_delete_template' );

		if ( ! $tpl || in_array( $tpl['id'], self::mains(), true ) ) {
			wp_safe_redirect( self::page_url( [ 'template' => 'failed' ] ) );
			exit;
		}

		wp_delete_post( (int) $tpl['id'], true );

		$set = (array) get_option( 'options_' . self::COMPANY_SET, [] );
		update_option( 'options_' . self::COMPANY_SET, array_values( array_diff( array_map( 'strval', $set ), [ $tpl['id'] ] ) ) );

		// Out of every company's set too.
		foreach ( SB_EmailSig_Company::all() as $co ) {
			$set = (array) get_post_meta( $co, self::COMPANY_SET, true );
			update_post_meta( $co, self::COMPANY_SET, array_values( array_diff( array_map( 'strval', $set ), [ $tpl['id'] ] ) ) );
		}

		SB_EmailSig_Icons::forget( $tpl['id'] );
		self::flush();

		wp_safe_redirect( self::page_url( [ 'template' => 'deleted' ] ) );
		exit;
	}
}
