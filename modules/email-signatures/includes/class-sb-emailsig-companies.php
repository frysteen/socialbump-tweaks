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
		// The Default Template and the ones people can pick, from a company's card (brand.js).
		add_action( 'wp_ajax_sb_emailsig_templates_save', array( __CLASS__, 'templates_save' ) );
		add_filter( 'acf/pre_save_post', array( __CLASS__, 'limit_new' ), 5 );
	}

	/** A new company only while the site is under its limit, even from a page opened before it was reached. */
	public static function limit_new( $post_id ) {
		if ( $post_id === 'new_post' && self::is_edit() && SB_EmailSig_Limits::full( 'companies' ) ) {
			wp_die( esc_html( SB_EmailSig_Limits::message( 'companies' ) ), '', array( 'back_link' => true ) );
		}

		return $post_id;
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
	/**
	 * A company's templates for the popup (brand.js): its Default Template,
	 * the ones its people can pick, and every template as [ id, name ].
	 */
	private static function templates_data( $co ) {
		return array(
			'main' => SB_EmailSig_Templates::main_for( $co ),
			'set'  => array_values( SB_EmailSig_Templates::set_for( $co ) ),
			'all'  => array_map(
				function ( $id, $tpl ) {
					return array( (string) $id, $tpl['name'] );
				},
				array_keys( SB_EmailSig_Templates::all() ),
				SB_EmailSig_Templates::all()
			),
		);
	}

	/** The Default Template's name on a company's card, as the button that opens its templates popup. */
	private static function templates_link( $co, $name ) {
		return '<button type="button" class="sb-emailsig-tpl-link" data-sb-emailsig-tpls="' . (int) $co . '" data-sb-emailsig-tpls-name="' . esc_attr( SB_EmailSig_Company::name( $co ) ) . '" data-sb-emailsig-tpls-data="' . esc_attr( wp_json_encode( self::templates_data( $co ) ) ) . '" data-sb-tip="Change the Default Template and the ones people can pick">' . esc_html( $name ) . '</button>';
	}

	/**
	 * Save a company's templates from the popup (Sam, 3 November 2026), as
	 * its Company tab would: the Default Template, and the ones its people
	 * can pick, which always include the default. Logged as an edit.
	 */
	public static function templates_save() {
		$co   = self::brand_check();
		$main = isset( $_POST['main'] ) ? sanitize_text_field( wp_unslash( $_POST['main'] ) ) : '';
		$list = isset( $_POST['templates'] ) ? json_decode( wp_unslash( $_POST['templates'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- checked below.
		$tpl  = SB_EmailSig_Templates::get( $main );

		if ( ! $tpl ) {
			wp_send_json_error( 'Pick a Default Template.' );
		}

		$set = array( (string) $tpl['id'] );

		foreach ( is_array( $list ) ? $list : array() as $id ) {
			$t = SB_EmailSig_Templates::get( sanitize_text_field( (string) $id ) );

			if ( $t && ! in_array( (string) $t['id'], $set, true ) ) {
				$set[] = (string) $t['id'];
			}
		}

		update_field( 'field_sb_emailsig_main_template', (string) $tpl['id'], $co );
		update_field( 'field_sb_emailsig_templates', $set, $co );
		SB_EmailSig_Log::add( 'edited', 'company', $co );

		$count = count( SB_EmailSig_Templates::set_for( $co ) );

		wp_send_json_success(
			array(
				'name'  => $tpl['name'],
				'count' => $count,
				'noun'  => _n( 'template', 'templates', $count ),
				'data'  => self::templates_data( $co ),
			)
		);
	}

	public static function brand_save() {
		$co      = self::brand_check();
		$colours = isset( $_POST['colours'] ) ? json_decode( wp_unslash( $_POST['colours'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned below.

		if ( ! is_array( $colours ) ) {
			wp_send_json_error( 'Nothing to save.' );
		}

		$clean = array();

		foreach ( SB_EmailSig_Styles::BRAND as $key => $b ) {
			$raw   = trim( (string) ( $colours[ $key ] ?? '' ) );
			$valid = SB_EmailSig_Fields::brand_valid( true, $raw );

			if ( $valid !== true ) {
				wp_send_json_error( $b[0] . ': ' . lcfirst( $valid ) );
			}

			$clean[ $key ] = SB_EmailSig_Styles::clean_colour( $raw );
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
					'brand'     => (object) wp_list_pluck( SB_EmailSig_Styles::brand_all(), 'hex' ),
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

		$moved   = isset( $_GET['moved'] ) ? absint( $_GET['moved'] ) : 0;
		$purged  = isset( $_GET['purged'] ) ? absint( $_GET['purged'] ) : 0;
		$notices = array(
			'deleted' => array( 'success', 'Company deleted.' . ( $purged ? ' ' . sprintf( _n( 'Its %d person was deleted too.', 'Its %d people were deleted too.', $purged ), $purged ) : ( SB_EmailSig_Company::valid( $moved ) ? ' Its people moved to ' . SB_EmailSig_Company::name( $moved ) . '.' : '' ) ) ),
			'failed'  => array( 'error', 'That didn\'t work. Try again.' ),
		);
		$notice  = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
		// phpcs:enable

		if ( isset( $notices[ $notice ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $notices[ $notice ][0] ) . ' is-dismissible"><p>' . esc_html( $notices[ $notice ][1] ) . '</p></div>';
		}

		self::render_list();
	}

	/** Every company as a card, by name. Any can be deleted while there's another (Sam, 3 November 2026). */
	private static function render_list() {
		$all = SB_EmailSig_Company::all();
		// Everyone's team page (administrators and editors): every company, grouped.
		$team = SB_EmailSig_Page::list_url();

		// How many, "Companies 1 of 1", and Add company greyed out at the limit (SB_EmailSig_Limits).
		echo SB_Tweaks_UI::bar( // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
			array(
				array( 'Team page', '<button type="button" class="button button-small sb-emailsig-icon" data-sb-emailsig-copy-text="' . esc_attr( $team ) . '" data-sb-tip="Copy link (every company, for those who manage)" aria-label="Copy link (every company, for those who manage)">' . SB_EmailSig_Admin::icon( 'link' ) . '</button><a class="button button-small" href="' . esc_url( $team ) . '" target="_blank" rel="noopener">' . SB_EmailSig_Admin::icon( 'open' ) . 'All companies</a>' ),
				array( 'Templates', '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-templates' ) ) . '">' . SB_EmailSig_Admin::icon( 'grid' ) . 'All templates</a>' ),
			),
			SB_EmailSig_Limits::count( 'companies' ) . SB_EmailSig_Limits::button( 'companies', '<a class="button button-primary" href="' . esc_url( self::url( 'new' ) ) . '">' . SB_EmailSig_Admin::icon( 'plus' ) . 'Add company</a>' ),
			'sb-emailsig-bar'
		);

		if ( ! $all ) {
			echo '<div class="sb-ui-empty"><p>No companies yet.</p></div>';

			return;
		}

		echo '<div class="sb-emailsig-people sb-emailsig-companies">';

		foreach ( $all as $id ) {
			self::card( $id, $all );
		}

		echo '</div>';
	}

	/** A company's letter: the first of its name, leaving out a leading "The" (The Cosmetic Studio is C). */
	public static function initial( $name ) {
		$name = trim( preg_replace( '/^the\s+/i', '', trim( (string) $name ) ) );
		$char = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );

		return $char !== '' ? ( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $char ) : strtoupper( $char ) ) : '?';
	}

	/** A company's page on its Company tab, at one of its fields (settings.js opens them). */
	public static function field_url( $id, $field ) {
		return add_query_arg(
			array(
				'sb_tab'   => 'company',
				'sb_field' => sanitize_key( $field ),
			),
			self::url( $id )
		);
	}

	/**
	 * One company's card (Sam, 1 November 2026): its logo, name with its brand
	 * colours under it, and switch; its Website, Phone, Email, Address and
	 * Default Template; its people and templates (links to them), then Edit, Add person
	 * to it (administrators), its team page's links and Delete. Its parts line up with the other cards' in a row (subgrid,
	 * admin.css).
	 */
	private static function card( $id, array $all ) {
		$name    = SB_EmailSig_Company::name( $id );
		$edit    = self::url( $id );
		$site    = (string) get_post_meta( $id, 'sb_emailsig_website', true );
		$host    = $site !== '' ? SB_EmailSig_Data::website_label( $site ) : '';
		// The small logo, else the logo (Sam, 30 October 2026).
		$logo    = (int) get_post_meta( $id, 'sb_emailsig_logo_small', true ) ?: (int) get_post_meta( $id, 'sb_emailsig_logo', true );
		$set     = SB_EmailSig_Templates::set_for( $id );
		$main    = SB_EmailSig_Templates::get( SB_EmailSig_Templates::main_for( $id ) );
		$people  = self::people( $id );

		echo '<article class="sb-tweaks-card sb-emailsig-template sb-emailsig-company' . esc_attr( SB_EmailSig_Switch::off_class( $id ) ) . '">';

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

		// The name, then its brand colours, a click to change one (swatches()). The
		// website is with the details below (Sam, 3 November 2026): a long one up
		// here pushed the switch out of the card.
		echo '<div class="sb-emailsig-person__who">';
		echo '<h3 class="sb-emailsig-person__name"><a href="' . esc_url( $edit ) . '">' . esc_html( $name ) . '</a></h3>';
		echo self::swatches( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in swatches().
		echo '</div></div>';

		// Its on or off switch (SB_EmailSig_Switch), top right. No Default badge
		// (Sam, 3 November 2026): the default can't be changed, so it said nothing.
		echo '<div class="sb-emailsig-company__end">';
		echo SB_EmailSig_Switch::control( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in control().
		echo '</div>';

		echo '</div>';

		// Its Website, Phone, Email, Address and Default Template (Sam, 1 and 3
		// November 2026), each always there: one not set says so, a link to its field.
		$details = SB_EmailSig_Company::with(
			$id,
			function () use ( $id, $host ) {
				return array(
					'website'       => array( 'globe', $host ),
					'phone'         => array( 'phone', trim( (string) get_field( 'sb_emailsig_phone', $id ) ) ),
					'company_email' => array( 'mail', trim( (string) get_field( 'sb_emailsig_company_email', $id ) ) ),
					'address'       => array( 'pin', implode( ', ', SB_EmailSig_Data::lines( (string) get_field( 'sb_emailsig_address', $id ) ) ) ),
				);
			}
		);
		$details['main_template'] = array( 'grid', $main ? 'Default Template: ' . $main['name'] : '' );

		echo '<ul class="sb-emailsig-company__details">';
		foreach ( $details as $field => $row ) {
			echo '<li>' . SB_EmailSig_Admin::icon( $row[0] ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup.
			if ( $field === 'main_template' && $main && current_user_can( 'manage_options' ) ) {
				// The template's name opens its popup (Sam, 3 November 2026).
				echo '<span>Default Template: ' . self::templates_link( $id, $main['name'] ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in templates_link().
				continue;
			}
			echo $row[1] !== ''
				? '<span data-sb-tip="' . esc_attr( $row[1] ) . '">' . esc_html( $row[1] ) . '</span>'
				: '<a class="is-unset" href="' . esc_url( self::field_url( $id, $field ) ) . '">Not set</a>';
			echo '</li>';
		}
		echo '</ul>';

		// People and templates, to the Signatures and Templates pages for this company.
		echo '<div class="sb-emailsig-template__stats">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-signatures&company=' . (int) $id ) ) . '" data-sb-tip="See its people"><strong>' . (int) $people . '</strong> ' . esc_html( _n( 'person', 'people', $people ) ) . '</a>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-templates&used=' . (int) $id ) ) . '" data-sb-tip="See its templates"><strong data-sb-emailsig-tpl-count="' . (int) $id . '">' . count( $set ) . '</strong> ' . esc_html( _n( 'template', 'templates', count( $set ) ) ) . '</a>';
		echo '</div>';

		echo '<div class="sb-emailsig-person__actions">';
		echo '<a class="button button-small button-primary" href="' . esc_url( $edit ) . '">' . SB_EmailSig_Admin::icon( 'edit' ) . 'Edit</a>';

		// Its team page: only its people (SB_EmailSig_Page::list_url()).
		$team = SB_EmailSig_Page::list_url( $id );
		// Add person to this company (administrators), before its team page's links (Sam, 3 November 2026).
		echo SB_EmailSig_Admin::add_person_button( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in add_person_button().
		echo SB_EmailSig_Admin::link_buttons( $team, 'Copy team page link', 'Open team page', 'Copy ' . $name . '\'s team page link', 'Open ' . $name . '\'s team page' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in link_buttons().

		// Delete, on any company while there's another (Sam, 3 November 2026:
		// no company is kept back). With people, the popup asks what happens to
		// them (data-sb-tweaks-confirm-choose, confirm.js): move them to another
		// company (named, or picked when there's more than one), say for a
		// rebrand or a merger, or delete them too.
		$others = array_values( array_diff( $all, array( (int) $id ) ) );

		if ( $others ) {
			$one   = count( $others ) === 1;
			$count = count( self::all_people( $id ) );
			$move  = array(
				'value' => 'move',
				'label' => $one ? 'Move them to ' . SB_EmailSig_Company::name( $others[0] ) : 'Move them to',
				'note'  => 'For a rebrand or a merger: they keep their own details and take on that company\'s branding.',
			);

			if ( ! $one ) {
				$move['menu'] = array(
					'name'    => 'move_to',
					'options' => array_map(
						function ( $co ) {
							return array( (string) $co, SB_EmailSig_Company::name( $co ) );
						},
						$others
					),
				);
			}

			$choose = $count ? array(
				'name'    => 'people',
				'label'   => 'Its ' . $count . ' ' . _n( 'person', 'people', $count ),
				'type'    => 'radio',
				'options' => array(
					$move,
					array(
						'value'  => 'delete',
						'label'  => 'Delete them too',
						'note'   => 'Deleted for good with their photos, deleted ones included.',
						'tone'   => 'danger',
						'action' => 'Delete company and people',
					),
				),
			) : null;

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sb-emailsig-person__delete">';
			echo '<input type="hidden" name="action" value="sb_emailsig_company_delete">';
			echo '<input type="hidden" name="company" value="' . (int) $id . '">';
			echo $one ? '<input type="hidden" name="move_to" value="' . (int) $others[0] . '">' : '';
			wp_nonce_field( 'sb_emailsig_company_delete_' . $id );
			echo '<button type="submit" class="button button-small sb-emailsig-icon sb-emailsig-icon--danger" data-sb-tip="Delete" aria-label="' . esc_attr( 'Delete ' . $name ) . '" data-sb-tweaks-confirm="' . esc_attr( 'Delete ' . $name . '? Its details, branding and campaign banners go. This can\'t be undone.' ) . '"' . ( $choose ? ' data-sb-tweaks-confirm-choose="' . esc_attr( wp_json_encode( $choose ) ) . '"' : '' ) . ' data-sb-tweaks-confirm-title="Delete company" data-sb-tweaks-confirm-action="Delete">' . SB_EmailSig_Admin::icon( 'trash' ) . '</button>';
			echo '</form>';
		}

		echo '</div>';
		echo '</article>';
	}

	/** Every one of company $id's people, deleted ones (Trash) included: ids. */
	private static function all_people( $id ) {
		return get_posts(
			array(
				'post_type'      => SB_EMAILSIG_CPT,
				'post_status'    => SB_EmailSig_Company::STATUSES,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => SB_EmailSig_Company::PERSON_META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => (int) $id, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
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
		$items = array();

		foreach ( $id ? SB_EmailSig_Company::all() : array() as $co ) {
			$items[] = array( self::url( $co ), SB_EmailSig_Company::name( $co ), '' );
		}

		return SB_EmailSig_Admin::goto_menu( self::url( $id ), $name, $items, 'Edit another company' );
	}

	/** A company's page: back to the list, its name, then its fields. $id 0 for a new one. */
	private static function render_edit( $id ) {
		$name = $id ? SB_EmailSig_Company::name( $id ) : 'New company';

		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . ( $_GET['saved'] === 'added' ? 'Company added.' : 'Saved.' ) . '</p></div>';
		}
		// phpcs:enable

		// Its on or off switch beside it, as a person's Active switch on theirs (SB_EmailSig_Switch).
		echo SB_Tweaks_UI::bar( // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
			array(
				array( '', '<a class="button button-small" href="' . esc_url( self::url() ) . '">' . SB_EmailSig_Admin::icon( 'grid' ) . 'All companies</a>' ),
				array( $id ? 'Company' : 'Add company', self::switcher( $id, $name ) . ( $id ? SB_EmailSig_Switch::control( $id ) : '' ) ),
			),
			'',
			'sb-emailsig-bar'
		);

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

	/** Delete a company for good, any while there's another: its people move to the one picked (move_to, else the first other one) or, with people=delete, are deleted too. */
	public static function delete() {
		$id = isset( $_POST['company'] ) ? absint( $_POST['company'] ) : 0;

		check_admin_referer( 'sb_emailsig_company_delete_' . $id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You can\'t delete companies.', 403 );
		}

		$all = SB_EmailSig_Company::all();
		$to  = isset( $_POST['move_to'] ) ? absint( $_POST['move_to'] ) : 0;

		if ( ! SB_EmailSig_Company::valid( $id ) || count( $all ) < 2 ) {
			wp_safe_redirect( add_query_arg( 'done', 'failed', self::url() ) );
			exit;
		}

		// Its people, deleted ones too: moved to the company picked, or
		// deleted for good with their photos (Sam, 3 November 2026). Then it goes.
		$people = self::all_people( $id );
		$purge  = isset( $_POST['people'] ) && 'delete' === $_POST['people'];

		if ( $purge ) {
			foreach ( $people as $person ) {
				wp_delete_post( $person, true );
			}
		} else {
			if ( ! SB_EmailSig_Company::valid( $to ) || $to === $id ) {
				$to = SB_EmailSig_Company::first_id( $id );
			}

			foreach ( $people as $person ) {
				// Their Linked to page's fields kept as text when it can't follow them.
				SB_EmailSig_Staff::carry( $person, $person, $to );
				update_post_meta( $person, SB_EmailSig_Company::PERSON_META, $to );
			}
		}

		$ok = wp_delete_post( $id, true );

		wp_safe_redirect( add_query_arg( $ok ? array( 'done' => 'deleted', $purge ? 'purged' : 'moved' => $purge ? count( $people ) : $to ) : array( 'done' => 'failed' ), self::url() ) );
		exit;
	}
}
