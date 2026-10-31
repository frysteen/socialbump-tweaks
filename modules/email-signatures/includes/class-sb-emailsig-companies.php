<?php
/**
 * The Companies page (Sam, 30 October 2026): a card for each company, and
 * a company's own page (?company={id}, or new) with the fields Settings used
 * to hold, through acf_form() on the company's post. A company's icons are
 * drawn by settings.js on its page, as they were on Settings
 * (SB_EmailSig_Icons::settings_saved()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Companies {

	public static function init() {
		add_action( 'admin_post_sb_emailsig_company_delete', array( __CLASS__, 'delete' ) );
		add_action( 'wp_ajax_sb_emailsig_brand_save', array( __CLASS__, 'brand_save' ) );
		add_action( 'wp_ajax_sb_emailsig_brand_icons', array( __CLASS__, 'brand_icons' ) );
	}

	/**
	 * A company's four brand colours as circles, a crossed one where it has
	 * none of its own (Sam, 30 October 2026). In WordPress, for
	 * administrators, the row is a button that opens Brand colours, a popup
	 * with all four to set, then Save (assets/brand.js; brand_save()): not
	 * changed straight from a picker, as that was too easy to do by accident.
	 */
	public static function swatches( $co ) {
		$co   = (int) $co;
		$edit = is_admin() && current_user_can( 'manage_options' );
		$data = self::swatch_data( $co );
		$out  = '';

		foreach ( $data as $key => $b ) {
			$out .= '<span class="sb-emailsig-swatch' . ( $b['raw'] === '' ? ' is-empty' : '' ) . '" style="--sb-swatch:' . esc_attr( $b['hex'] ) . ';" data-sb-swatch-key="' . esc_attr( $key ) . '" data-sb-tip="' . esc_attr( $b['tip'] ) . '"></span>';
		}

		if ( ! $edit ) {
			return '<span class="sb-emailsig-swatches">' . $out . '</span>';
		}

		return '<button type="button" class="sb-emailsig-swatches is-edit" data-sb-emailsig-brand="' . $co . '" data-sb-emailsig-brand-name="' . esc_attr( SB_EmailSig_Company::name( $co ) ) . '" data-sb-emailsig-brand-data="' . esc_attr( wp_json_encode( $data ) ) . '" aria-label="' . esc_attr( 'Edit ' . SB_EmailSig_Company::name( $co ) . '\'s brand colours' ) . '">' . $out . '</button>';
	}

	/** Company $co's brand colours: key => [ name, raw (its own, '' for none), hex (as used), default, tip ]. */
	private static function swatch_data( $co ) {
		$brand = SB_EmailSig_Company::with(
			$co,
			function () {
				return SB_EmailSig_Styles::brand();
			}
		);
		$out   = array();

		foreach ( $brand as $key => $b ) {
			$raw         = (string) get_post_meta( $co, 'sb_emailsig_brand_' . $key, true );
			$out[ $key ] = array(
				'name'    => $b['name'],
				'raw'     => $raw,
				'hex'     => $b['hex'],
				'default' => SB_EmailSig_Styles::BRAND[ $key ][1],
				'tip'     => $b['name'] . ': ' . ( $raw !== '' ? $b['hex'] : ( $key === 'accent' ? 'not set, uses Brand Primary' : 'not set, uses ' . $b['hex'] ) ),
			);
		}

		return $out;
	}

	/** Who may change brand colours here: administrators, with the page's nonce. */
	private static function brand_check() {
		check_ajax_referer( 'sb_emailsig_brand', 'nonce' );

		$co = isset( $_POST['co'] ) ? absint( $_POST['co'] ) : 0;

		if ( ! current_user_can( 'manage_options' ) || ! SB_EmailSig_Company::valid( $co ) ) {
			wp_send_json_error( 'Not allowed.', 403 );
		}

		return $co;
	}

	/**
	 * Brand colours' Save: all four checked as the Branding tab checks them
	 * (a hex or a theme colour; blank for the default), then kept, and what
	 * the page needs to redraw the company's icons in them sent back.
	 */
	public static function brand_save() {
		$co      = self::brand_check();
		$colours = isset( $_POST['colours'] ) ? json_decode( wp_unslash( $_POST['colours'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned below.

		if ( ! is_array( $colours ) ) {
			wp_send_json_error( 'Nothing to save.' );
		}

		$clean = array();

		foreach ( SB_EmailSig_Styles::BRAND as $key => $b ) {
			$raw = trim( (string) ( $colours[ $key ] ?? '' ) );
			$c   = SB_EmailSig_Styles::clean_colour( $raw );

			if ( $raw !== '' && ( $c === '' || strpos( $c, 'var(--sb-brand-' ) === 0 || SB_EmailSig_Styles::theme_hex( $c ) === '' ) ) {
				wp_send_json_error( $b[0] . ': use a colour such as #098b97, or pick a theme colour.' );
			}

			$clean[ $key ] = $c;
		}

		foreach ( $clean as $key => $c ) {
			// Blank keeps the default, as the Branding tab does (Accent: blank, the primary).
			update_field( 'field_sb_emailsig_brand_' . $key, $c !== '' ? $c : SB_EmailSig_Styles::BRAND[ $key ][1], $co );
		}

		$draw = SB_EmailSig_Company::with(
			$co,
			function () {
				return array(
					'templates' => SB_EmailSig_Styles::social_styles(),
					'rows'      => SB_EmailSig_Icons::social_svgs(),
					'brand'     => (object) wp_list_pluck( SB_EmailSig_Styles::brand(), 'hex' ),
				);
			}
		);

		SB_EmailSig_Log::add( 'branding', 'company', $co );

		wp_send_json_success(
			array(
				'swatches' => self::swatches( $co ),
				'draw'     => $draw,
			)
		);
	}

	/** The icons the page drew in the new colours: kept for each template, as a company's Save does. */
	public static function brand_icons() {
		$co      = self::brand_check();
		$payload = isset( $_POST['icons'] ) ? json_decode( wp_unslash( $_POST['icons'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! is_array( $payload ) ) {
			wp_send_json_error( 'No icons.' );
		}

		foreach ( $payload as $tpl_id => $icons ) {
			if ( is_array( $icons ) ) {
				SB_EmailSig_Icons::save( sanitize_key( (string) $tpl_id ), $icons, array( 'social', 'contact' ), $co );
			}
		}

		wp_send_json_success();
	}

	public static function slug() {
		return SB_EmailSig_Module::SLUG . '-companies';
	}

	/** The Companies page, or one company's page ($id), or a new one ('new'). */
	public static function url( $id = 0 ) {
		$args = array( 'page' => self::slug() );

		if ( $id ) {
			$args['company'] = $id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/** True on the Companies page, a company's page included. */
	public static function is_request() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		return is_admin() && $page === self::slug();
	}

	/** On a company's page: its id, 0 for a new one. null on the list. */
	public static function editing() {
		if ( ! self::is_request() || ! isset( $_GET['company'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return null;
		}

		$raw = sanitize_key( wp_unslash( $_GET['company'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		return $raw === 'new' ? 0 : ( SB_EmailSig_Company::valid( (int) $raw ) ? (int) $raw : null );
	}

	/** True on a company's page (one there is, or a new one). */
	public static function is_edit() {
		return self::editing() !== null;
	}

	public static function render_page() {
		if ( ! function_exists( 'acf_form' ) ) {
			echo '<div class="sb-ui-empty"><p>Companies need ACF Pro.</p></div>';

			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['company'] ) ) {
			$id = self::editing();

			if ( $id === null ) {
				echo '<div class="sb-ui-empty"><p>That company isn\'t here any more.</p></div>';

				return;
			}

			SB_EmailSig_Company::with(
				$id,
				function () use ( $id ) {
					self::render_edit( $id );
				}
			);

			return;
		}

		$notices = array(
			'deleted' => array( 'success', 'Company deleted. Its people moved to ' . SB_EmailSig_Company::name( SB_EmailSig_Company::default_id() ) . '.' ),
			'failed'  => array( 'error', 'That didn\'t work. Try again.' ),
		);
		$notice  = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
		// phpcs:enable

		if ( isset( $notices[ $notice ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $notices[ $notice ][0] ) . ' is-dismissible"><p>' . esc_html( $notices[ $notice ][1] ) . '</p></div>';
		}

		self::render_list();
	}

	/** Every company as a card, the default first. */
	private static function render_list() {
		$all = SB_EmailSig_Company::all();
		$def = SB_EmailSig_Company::default_id();

		if ( $def && in_array( $def, $all, true ) ) {
			$all = array_merge( array( $def ), array_values( array_diff( $all, array( $def ) ) ) );
		}

		// Everyone's team page (administrators and editors): every company, grouped.
		$team = SB_EmailSig_Page::list_url();

		echo '<div class="sb-ui-bar sb-emailsig-bar">';
		echo '<div class="sb-ui-bar__group"><span class="sb-ui-bar__label">Team page</span>';
		echo '<button type="button" class="button button-small sb-emailsig-icon" data-sb-emailsig-copy-text="' . esc_attr( $team ) . '" data-sb-tip="Copy link (every company, for those who manage)">' . SB_EmailSig_Admin::icon( 'link' ) . '</button>';
		echo '<a class="button button-small" href="' . esc_url( $team ) . '" target="_blank" rel="noopener">' . SB_EmailSig_Admin::icon( 'open' ) . 'All companies</a>';
		echo '</div>';
		echo '<div class="sb-ui-bar__group"><span class="sb-ui-bar__label">Templates</span>';
		echo '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-templates' ) ) . '">' . SB_EmailSig_Admin::icon( 'grid' ) . 'All templates</a>';
		echo '</div>';
		// How many, "Companies 1 of 1", and Add company greyed out at the limit (SB_EmailSig_Limits).
		echo '<div class="sb-ui-bar__end">' . SB_EmailSig_Limits::count( 'companies' ) . SB_EmailSig_Limits::button( 'companies', '<a class="button button-primary" href="' . esc_url( self::url( 'new' ) ) . '">' . SB_EmailSig_Admin::icon( 'plus' ) . 'Add company</a>' ) . '</div>';
		echo '</div>';

		if ( ! $all ) {
			echo '<div class="sb-ui-empty"><p>No companies yet.</p></div>';

			return;
		}

		echo '<div class="sb-emailsig-people sb-emailsig-companies">';

		foreach ( $all as $id ) {
			self::card( $id, $id === $def );
		}

		echo '</div>';
	}

	/** A company's letter: the first of its name, leaving out a leading "The" (The Cosmetic Studio is C). */
	public static function initial( $name ) {
		$name = trim( preg_replace( '/^the\s+/i', '', trim( (string) $name ) ) );
		$char = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );

		return $char !== '' ? ( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $char ) : strtoupper( $char ) ) : '?';
	}

	/** One company's card: its logo and name, its numbers, Edit and Delete. */
	private static function card( $id, $is_default ) {
		$name    = SB_EmailSig_Company::name( $id );
		$edit    = self::url( $id );
		$site    = (string) get_post_meta( $id, 'sb_emailsig_website', true );
		$host    = $site !== '' ? preg_replace( '#^(https?://)?(www\.)?#i', '', untrailingslashit( $site ) ) : '';
		// The small logo, else the logo (Sam, 30 October 2026).
		$logo    = (int) get_post_meta( $id, 'sb_emailsig_logo_small', true ) ?: (int) get_post_meta( $id, 'sb_emailsig_logo', true );
		$set     = SB_EmailSig_Templates::set_for( $id );
		$main    = SB_EmailSig_Templates::get( SB_EmailSig_Templates::main_for( $id ) );
		$people  = self::people( $id );

		echo '<article class="sb-tweaks-card sb-emailsig-template sb-emailsig-company' . ( $is_default ? ' is-main' : '' ) . esc_attr( SB_EmailSig_Switch::off_class( $id ) ) . '">';

		echo '<div class="sb-emailsig-template__top">';
		echo '<div class="sb-emailsig-company__who">';

		$src = $logo ? wp_get_attachment_image_url( $logo, 'medium' ) : '';

		if ( $src ) {
			echo '<span class="sb-emailsig-company__logo"><img src="' . esc_url( $src ) . '" alt=""></span>';
		} else {
			// No logo: the name's first letter, white on the company's Brand Primary.
			$colour = SB_EmailSig_Company::with(
				$id,
				function () {
					return SB_EmailSig_Styles::brand()['primary']['hex'];
				}
			);
			echo '<span class="sb-emailsig-company__logo sb-emailsig-company__logo--letter" style="background-color:' . esc_attr( $colour ) . ';" aria-hidden="true">' . esc_html( self::initial( $name ) ) . '</span>';
		}

		echo '<div class="sb-emailsig-person__who">';
		echo '<h3 class="sb-emailsig-person__name"><a href="' . esc_url( $edit ) . '">' . esc_html( $name ) . '</a></h3>';

		if ( $host !== '' ) {
			echo '<p class="sb-emailsig-person__job">' . esc_html( $host ) . '</p>';
		}

		echo '</div></div>';

		// Default, then its on or off switch (SB_EmailSig_Switch), top right.
		echo '<div class="sb-emailsig-company__end">';
		if ( $is_default ) {
			echo '<span class="sb-emailsig-template__badge" data-sb-tip="People with no company of their own are in this one">Default</span>';
		}
		echo SB_EmailSig_Switch::control( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in control().
		echo '</div>';

		echo '</div>';

		// Its brand colours, a click to change one (swatches()).
		echo '<div class="sb-emailsig-company__brand">' . self::swatches( $id ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in swatches().

		echo '<div class="sb-emailsig-template__stats">';
		echo '<span data-sb-tip="Active people"><strong>' . (int) $people . '</strong> ' . esc_html( _n( 'person', 'people', $people ) ) . '</span>';
		echo '<span data-sb-tip="Templates its people can use"><strong>' . count( $set ) . '</strong> ' . esc_html( _n( 'template', 'templates', count( $set ) ) ) . '</span>';
		echo '</div>';

		if ( $main ) {
			echo '<p class="sb-emailsig-template__note">Main template: ' . esc_html( $main['name'] ) . '</p>';
		}

		echo '<div class="sb-emailsig-person__actions">';
		echo '<a class="button button-small button-primary" href="' . esc_url( $edit ) . '">' . SB_EmailSig_Admin::icon( 'edit' ) . 'Edit</a>';

		// Its team page: only its people (SB_EmailSig_Page::list_url()).
		$team = SB_EmailSig_Page::list_url( $id );
		echo '<button type="button" class="button button-small sb-emailsig-icon" data-sb-emailsig-copy-text="' . esc_attr( $team ) . '" data-sb-tip="Copy team page link" aria-label="' . esc_attr( 'Copy ' . $name . '\'s team page link' ) . '">' . SB_EmailSig_Admin::icon( 'link' ) . '</button>';
		echo '<a class="button button-small sb-emailsig-icon" href="' . esc_url( $team ) . '" target="_blank" rel="noopener" data-sb-tip="Open team page" aria-label="' . esc_attr( 'Open ' . $name . '\'s team page' ) . '">' . SB_EmailSig_Admin::icon( 'open' ) . '</a>';

		if ( ! $is_default ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sb-emailsig-person__delete">';
			echo '<input type="hidden" name="action" value="sb_emailsig_company_delete">';
			echo '<input type="hidden" name="company" value="' . (int) $id . '">';
			wp_nonce_field( 'sb_emailsig_company_delete_' . $id );
			echo '<button type="submit" class="button button-small sb-emailsig-icon sb-emailsig-icon--danger" data-sb-tip="Delete" aria-label="' . esc_attr( 'Delete ' . $name ) . '" data-sb-tweaks-confirm="' . esc_attr( 'Delete ' . $name . '? Its people move to ' . SB_EmailSig_Company::name( SB_EmailSig_Company::default_id() ) . ', and its details, branding and campaign banners go. This can\'t be undone.' ) . '" data-sb-tweaks-confirm-title="Delete company" data-sb-tweaks-confirm-action="Delete">' . SB_EmailSig_Admin::icon( 'trash' ) . '</button>';
			echo '</form>';
		}

		echo '</div>';
		echo '</article>';
	}

	/** How many active people a company has. */
	private static function people( $id ) {
		$q = new WP_Query(
			array(
				'post_type'      => SB_EMAILSIG_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'meta_key'       => SB_EmailSig_Company::PERSON_META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => (int) $id, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);

		return (int) $q->found_posts;
	}

	/**
	 * The bar's company name: with more than one company, a menu to open
	 * another's page (settings.js; Sam, 30 October 2026). Else just the name.
	 */
	private static function switcher( $id, $name ) {
		$all = $id ? SB_EmailSig_Company::all() : array();

		if ( count( $all ) < 2 ) {
			return '<span class="sb-ui-bar__title">' . esc_html( $name ) . '</span>';
		}

		$out = '<select class="sb-emailsig-bar__switch" data-sb-emailsig-switch aria-label="Edit another company">';
		foreach ( $all as $co ) {
			$out .= '<option value="' . esc_url( self::url( $co ) ) . '"' . selected( (int) $id, (int) $co, false ) . '>' . esc_html( SB_EmailSig_Company::name( $co ) ) . '</option>';
		}

		return $out . '</select>';
	}

	/** A company's page: back to the list, its name, then its fields. $id 0 for a new one. */
	private static function render_edit( $id ) {
		$name = $id ? SB_EmailSig_Company::name( $id ) : 'New company';

		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . ( $_GET['saved'] === 'added' ? 'Company added.' : 'Saved.' ) . '</p></div>';
		}
		// phpcs:enable

		echo '<div class="sb-ui-bar sb-emailsig-bar">';
		echo '<div class="sb-ui-bar__group"><a class="button button-small" href="' . esc_url( self::url() ) . '">' . SB_EmailSig_Admin::icon( 'grid' ) . 'All companies</a></div>';
		// Its on or off switch beside it, as a person's Active switch on theirs (SB_EmailSig_Switch).
		echo '<div class="sb-ui-bar__group"><span class="sb-ui-bar__label">' . ( $id ? 'Company' : 'Add company' ) . '</span>' . self::switcher( $id, $name ) . ( $id ? SB_EmailSig_Switch::control( $id ) : '' ) . '</div>';
		echo '</div>';

		echo '<section class="sb-tweaks-section sb-emailsig-settings"><div class="sb-tweaks-section__body">';

		$args = array(
			'id'                    => 'sb-emailsig-settings-form',
			'post_id'               => $id ? $id : 'new_post',
			'field_groups'          => array( SB_EmailSig_Company::GROUP ),
			'form_attributes'       => array( 'data-sb-dirty' => '' ),
			'return'                => add_query_arg( 'saved', $id ? '1' : 'added', self::url( $id ? $id : '%post_id%' ) ),
			'updated_message'       => false,
			'label_placement'       => 'top',
			'instruction_placement' => 'field',
			'submit_value'          => $id ? 'Save changes' : 'Add company',
			'html_submit_button'    => '<input type="submit" class="button button-primary sb-save--clean" value="%s" />',
			'html_submit_spinner'   => '<span class="acf-spinner"></span>',
		);

		if ( ! $id ) {
			$args['new_post'] = array(
				'post_type'   => SB_EmailSig_Company::CPT,
				'post_status' => 'publish',
				'post_title'  => 'New company',
			);
		}

		// A new one only while this site is under its limit, set from the hub (SB_EmailSig_Limits).
		if ( ! $id && SB_EmailSig_Limits::full( 'companies' ) ) {
			echo '<p>' . esc_html( SB_EmailSig_Limits::message( 'companies' ) ) . '</p>';
		} else {
			acf_form( $args );
		}

		echo '</div></section>';
	}

	/** Delete a company for good: its people move to the default one first. */
	public static function delete() {
		$id = isset( $_POST['company'] ) ? absint( $_POST['company'] ) : 0;

		check_admin_referer( 'sb_emailsig_company_delete_' . $id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You can\'t delete companies.', 403 );
		}

		$def = SB_EmailSig_Company::default_id();

		if ( ! SB_EmailSig_Company::valid( $id ) || $id === $def ) {
			wp_safe_redirect( add_query_arg( 'done', 'failed', self::url() ) );
			exit;
		}

		foreach ( get_posts( array( 'post_type' => SB_EMAILSIG_CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => SB_EmailSig_Company::PERSON_META, 'meta_value' => $id ) ) as $person ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			update_post_meta( $person, SB_EmailSig_Company::PERSON_META, $def );
		}

		$ok = wp_delete_post( $id, true );

		wp_safe_redirect( add_query_arg( 'done', $ok ? 'deleted' : 'failed', self::url() ) );
		exit;
	}
}
