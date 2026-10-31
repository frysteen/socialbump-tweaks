<?php
/**
 * A person's own Add and Edit page (Sam, 30 October 2026), in place of
 * WordPress's post screen: the Signatures page with ?person=<id>, or
 * ?person=new to add someone.
 *
 * The person's ACF fields are drawn in the module's cards with
 * acf_render_fields(), so everything they did on the post screen still works
 * (the staff page and its tokens, the repeater, the photo picker, the crop
 * box from SB_EmailSig_Photo). The form posts to admin-post.php: save()
 * checks the fields as ACF would (acf_validate_save_post()), makes the post
 * when adding (published, as Add person on the team page does), then
 * acf_save_post(), which fires acf/save_post, where the title, crop and
 * photos are done (SB_EmailSig_Photo::on_save()).
 *
 * The preview beside the cards is the saved signature, redrawn as the
 * simple fields change (preview(): the posted values stand in for the saved
 * ones through acf/pre_load_value). The photo, its crop and the Info List
 * show as saved.
 *
 * WordPress's own edit and Add New screens for a person send people here,
 * and edit links (get_edit_post_link()) point here. ?sb_classic=1 on the
 * post screen still opens WordPress's, in case it is ever needed.
 *
 * The page is built to open on its own too (no admin menus), for a popup on
 * the team page later.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Person {

	const NONCE = 'sb_emailsig_person';

	/** The cards and the fields in each, in order. */
	const CARDS = array(
		'details'   => array( 'Details', array( 'person_company', 'staff_page', 'first_name', 'last_name', 'qualifications', 'job_title', 'email', 'mobile' ) ),
		'crop'      => array( 'Photo', array( 'photo' ) ),
		'info'      => array( 'Info List', array( 'person_info', 'person_info_hide' ) ),
		'templates' => array( 'Templates', array( 'person_template' ) ),
	);

	/** Fields the preview takes from the form as they change: simple values only. */
	const LIVE = array( 'first_name', 'last_name', 'qualifications', 'job_title', 'email', 'mobile', 'staff_page', 'person_info_hide', 'person_company' );

	/** The person's own Info List rows (a repeater): its sub fields, by key. */
	const INFO     = 'field_sb_emailsig_person_info';
	const INFO_SUB = array( 'field_sb_emailsig_person_info_icon', 'field_sb_emailsig_person_info_label', 'field_sb_emailsig_person_info_text', 'field_sb_emailsig_person_info_link', 'field_sb_emailsig_person_info_id' );

	public static function init() {
		add_action( 'admin_post_sb_emailsig_person_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_sb_emailsig_person_delete', array( __CLASS__, 'delete' ) );
		add_action( 'admin_post_sb_emailsig_person_restore', array( __CLASS__, 'restore' ) );
		add_action( 'admin_post_sb_emailsig_person_purge', array( __CLASS__, 'purge' ) );
		add_action( 'wp_ajax_sb_emailsig_person_preview', array( __CLASS__, 'preview' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'load-post.php', array( __CLASS__, 'from_post_screen' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'from_post_screen' ) );
		add_filter( 'get_edit_post_link', array( __CLASS__, 'edit_link' ), 10, 3 );
	}

	/**
	 * The bar's name as a menu of everyone, to open another's page (Sam, 31
	 * October 2026), under their companies with more than one; Inactive
	 * people say so.
	 */
	private static function menu( $id, $name ) {
		$many   = count( SB_EmailSig_Company::all() ) > 1;
		$people = array();

		foreach ( SB_EmailSig_Admin::people( array( 'publish', 'draft' ) ) as $pid ) {
			$co       = SB_EmailSig_Company::of_person( $pid );
			$people[] = array(
				self::url( $pid ),
				trim( (string) get_field( 'sb_emailsig_name', $pid ) ) . ( get_post_status( $pid ) === 'draft' ? ' (Inactive)' : '' ),
				$many ? SB_EmailSig_Company::name( $co ) : '',
			);
		}

		// By company, then by name.
		usort(
			$people,
			function ( $a, $b ) {
				return array( $a[2], strtolower( $a[1] ) ) <=> array( $b[2], strtolower( $b[1] ) );
			}
		);

		if ( ! in_array( self::url( $id ), array_column( $people, 0 ), true ) ) {
			return '<span class="sb-ui-bar__title">' . esc_html( $name ) . '</span>';
		}

		return SB_EmailSig_Admin::goto_menu( self::url( $id ), $name, $people, 'Edit another person' );
	}

	/** This page's address: a person, or 0 to add someone. */
	public static function url( $id = 0 ) {
		return admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-signatures&person=' . ( $id ? (int) $id : 'new' ) );
	}

	/** True on this page. */
	public static function is_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		return is_admin() && $page === SB_EmailSig_Module::SLUG . '-signatures' && isset( $_GET['person'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** The person this page is for: their id, or 0 when adding (or not a person). */
	public static function current() {
		$id = isset( $_GET['person'] ) ? absint( $_GET['person'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		return ( $id && get_post_type( $id ) === SB_EMAILSIG_CPT ) ? $id : 0;
	}

	/** Who may add, or edit this person: administrators and editors (SB_EmailSig_Admin::can_manage()). */
	public static function allowed( $id ) {
		if ( ! SB_EmailSig_Admin::can_manage() ) {
			return false;
		}

		return $id ? current_user_can( 'edit_post', $id ) : current_user_can( 'publish_posts' );
	}

	/** WordPress's edit and Add New screens for a person: here instead. */
	public static function from_post_screen() {
		global $typenow;

		// phpcs:disable WordPress.Security.NonceVerification
		if ( $typenow !== SB_EMAILSIG_CPT || ! empty( $_GET['sb_classic'] ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$post   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		// phpcs:enable

		if ( $post && $action !== 'edit' ) {
			return;
		}

		wp_safe_redirect( self::url( $post ) );
		exit;
	}

	/** Edit links for a person (cards, reports, the admin bar) open this page. */
	public static function edit_link( $link, $post_id, $context ) {
		if ( get_post_type( $post_id ) !== SB_EMAILSIG_CPT ) {
			return $link;
		}

		$url = self::url( $post_id );

		return $context === 'display' ? esc_url( $url ) : $url;
	}

	public static function assets() {
		if ( ! self::is_screen() || ! function_exists( 'acf_enqueue_scripts' ) ) {
			return;
		}

		acf_enqueue_scripts();
		wp_enqueue_media();

		$id = self::current();

		wp_enqueue_script( 'sb-emailsig-person', SB_EMAILSIG_URL . 'assets/person.js', wp_script_is( 'acf-input', 'registered' ) ? array( 'acf-input' ) : array(), SB_EmailSig_Module::asset_version( 'assets/person.js' ), true );
		wp_localize_script(
			'sb-emailsig-person',
			'sbEmailsigPerson',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE . '_preview' ),
				'id'    => $id,
				'live'  => array_map(
					function ( $k ) {
						return 'field_sb_emailsig_' . $k;
					},
					array_merge( self::LIVE, array( 'person_template' ) )
				),
			)
		);
	}

	/** The fields of one card, ready to draw ([] when none apply here). */
	private static function fields( array $names ) {
		$out = array();

		foreach ( $names as $name ) {
			$field = acf_get_field( 'field_sb_emailsig_' . $name );

			if ( $field ) {
				$out[] = $field;
			}
		}

		return $out;
	}

	/** One card: its fields drawn by ACF, or nothing when none of them show (Templates with one template). */
	private static function card( $key, $title, array $fields, $post_id, $after = '' ) {
		ob_start();
		acf_render_fields( $fields, $post_id, 'div', 'field' );
		$html = trim( (string) ob_get_clean() );

		if ( $html === '' && $after === '' ) {
			return;
		}

		echo '<div class="sb-tweaks-card sb-emailsig-editor__card sb-emailsig-person-card" id="sb-emailsig-' . esc_attr( $key ) . '"><div class="sb-tweaks-card__head"><h3>' . esc_html( $title ) . '</h3></div>';
		echo '<div class="acf-fields -top">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- ACF's own markup.
		echo $after; // phpcs:ignore WordPress.Security.EscapeOutput -- built by SB_EmailSig_Photo::render_box().
		echo '</div>';
	}

	/** The page, drawn in the person's company (its templates, its details for the preview). */
	public static function render() {
		$id = self::current();

		SB_EmailSig_Company::with(
			SB_EmailSig_Company::of_person( $id ),
			function () {
				self::draw();
			}
		);
	}

	private static function draw() {
		$id = self::current();

		if ( ! function_exists( 'acf_render_fields' ) ) {
			echo '<div class="sb-ui-empty"><p>This page needs ACF Pro.</p></div>';

			return;
		}

		if ( ! self::allowed( $id ) || ( ! $id && isset( $_GET['person'] ) && $_GET['person'] !== 'new' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="sb-ui-empty"><p>' . ( self::allowed( $id ) ? 'That person isn\'t here any more.' : 'You can\'t edit people.' ) . '</p></div>';

			return;
		}

		// A new person only while there's a slot (SB_EmailSig_Limits).
		if ( ! $id && SB_EmailSig_Limits::full( 'people' ) ) {
			echo '<div class="sb-ui-empty"><p>' . esc_html( SB_EmailSig_Limits::message( 'people' ) ) . '</p></div>';

			return;
		}

		$form    = 'sb-emailsig-person-form';
		$post_id = $id ? $id : 'new_post';
		$name    = $id ? trim( (string) get_field( 'sb_emailsig_name', $id ) ) : '';
		$name    = $name !== '' ? $name : ( $id ? get_the_title( $id ) : 'New person' );
		$back    = admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-signatures' );

		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . ( $_GET['saved'] === 'added' ? 'Person added.' : 'Saved.' ) . '</p></div>';
		}
		// phpcs:enable

		// The bar: back to everyone, who this is (and their Active switch),
		// their links, then Delete and Save changes.
		$who = $id ? self::menu( $id, $name ) : '<span class="sb-ui-bar__title">' . esc_html( $name ) . '</span>';

		if ( $id ) {
			$active = get_post_status( $id ) === 'publish';
			$off    = SB_EmailSig_Switch::person_off( $id ); // Their company switched off: locked.
			$who   .= '<label class="sb-tweaks-switch" data-sb-tip="' . esc_attr( $off ? 'Switch ' . SB_EmailSig_Company::name( SB_EmailSig_Company::of_person( $id ) ) . ' on first' : 'Active' ) . '"><input type="checkbox" data-sb-emailsig-active="' . (int) $id . '"' . checked( $active, true, false ) . disabled( $off, true, false ) . '><span class="sb-tweaks-switch__track"><span class="sb-tweaks-switch__dot"></span></span><span class="screen-reader-text">Active</span></label>';
		}

		$groups = array(
			array( '', '<a class="button button-small" href="' . esc_url( $back ) . '">' . SB_EmailSig_Admin::icon( 'grid' ) . 'All people</a>' ),
			array( $id ? 'Person' : 'Add person', $who ),
		);

		if ( $id ) {
			$url      = SB_EmailSig_CPT::page_url( $id );
			$groups[] = array(
				'Their page',
				'<button type="button" class="button button-small sb-emailsig-icon" data-sb-emailsig-copy-text="' . esc_attr( $url ) . '" data-sb-tip="Copy link">' . SB_EmailSig_Admin::icon( 'link' ) . '</button>'
				. '<a class="button button-small sb-emailsig-icon" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" data-sb-tip="Open their page">' . SB_EmailSig_Admin::icon( 'open' ) . '</a>',
			);
		}

		$end = '';

		if ( $id && current_user_can( 'delete_post', $id ) ) {
			$end .= '<button type="submit" form="sb-emailsig-person-delete" class="button sb-emailsig-icon sb-emailsig-icon--danger" data-sb-tip="Delete" data-sb-always-on data-sb-tweaks-confirm="' . esc_attr( 'Move ' . $name . ' to the Trash? Their signature stops working.' ) . '" data-sb-tweaks-confirm-title="Delete person" data-sb-tweaks-confirm-action="Delete">' . SB_EmailSig_Admin::icon( 'trash' ) . '</button>';
		}

		$end .= SB_Tweaks_UI::save_button( $form, $id ? '' : 'Add person' );

		echo SB_Tweaks_UI::bar( $groups, $end, 'sb-emailsig-editbar' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.

		echo '<form id="' . esc_attr( $form ) . '" class="sb-emailsig-editor sb-emailsig-person-editor acf-form" method="post" autocomplete="off" data-sb-dirty action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sb_emailsig_person_save">';
		echo '<input type="hidden" name="sb_emailsig_person" value="' . (int) $id . '">';
		wp_nonce_field( self::NONCE );
		acf_form_data(
			array(
				'screen'  => 'sb_emailsig_person',
				'post_id' => $post_id,
			)
		);

		echo '<div class="sb-emailsig-editor__main">';

		foreach ( self::CARDS as $key => $card ) {
			$after = '';

			// The crop box under the photo, as on the post screen.
			if ( $key === 'crop' ) {
				ob_start();
				SB_EmailSig_Photo::render_box( $id ? get_post( $id ) : (object) array( 'ID' => 0 ) );
				$after = (string) ob_get_clean();
			}

			self::card( $key, $card[0], self::fields( $card[1] ), $post_id, $after );
		}

		echo '<p class="sb-emailsig-editor__actions"><button type="submit" class="button button-primary sb-save--clean">' . ( $id ? 'Save changes' : 'Add person' ) . '</button></p>';
		echo '</div>';

		// The signature, beside the cards.
		echo '<div class="sb-emailsig-editor__side"><div class="sb-emailsig-editor__sticky">';
		echo '<div class="sb-emailsig-editor__side-head"><h3>Preview</h3><span class="sb-emailsig-editor__status" aria-live="polite"></span>';

		if ( $id ) {
			$state = SB_EmailSig_Notify::state( $id, SB_EmailSig_Notify::fingerprint( SB_EmailSig_Render::html( $id ) ) );
			echo $state === 'current' ? '<span class="sb-emailsig-state sb-emailsig-state--current">Up to date</span>' : SB_EmailSig_Notify::badge( $state ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup.
		}

		echo '</div>';

		if ( $id ) {
			$list = SB_EmailSig_Templates::available( $id );
			$view = self::view( $id, $list, $list ? $list[0] : '' );

			echo '<div class="sb-emailsig-person-editor__tpls" id="sb-emailsig-person-tpls">' . $view['switcher'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped in switcher().
			echo '<div class="sb-emailsig-preview sb-emailsig-editor__preview"><div class="sb-emailsig-person-editor__sig" id="sb-emailsig-person-preview">' . $view['html'] . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped in the template.
			echo '<div class="sb-emailsig-person-editor__off" id="sb-emailsig-person-off">' . $view['off'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped in off().
		} else {
			echo '<div class="sb-ui-empty"><p>Their signature shows here once they\'re added.</p></div>';
		}

		echo '</div></div>';
		echo '</form>';

		// Delete posts on its own (the Delete in the bar, after asking).
		if ( $id ) {
			echo '<form id="sb-emailsig-person-delete" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" hidden>';
			echo '<input type="hidden" name="action" value="sb_emailsig_person_delete"><input type="hidden" name="sb_emailsig_person" value="' . (int) $id . '">';
			wp_nonce_field( self::NONCE . '_delete_' . $id );
			echo '</form>';
		}
	}

	/** Save, or add: checked as ACF would, then saved through ACF. */
	public static function save() {
		check_admin_referer( self::NONCE );

		$id = isset( $_POST['sb_emailsig_person'] ) ? absint( $_POST['sb_emailsig_person'] ) : 0;

		if ( $id && get_post_type( $id ) !== SB_EMAILSIG_CPT ) {
			$id = 0;
		}

		if ( ! self::allowed( $id ) || ! function_exists( 'acf_save_post' ) ) {
			wp_die( 'You can\'t edit people.' );
		}

		// Required fields and the email check: a page saying what's wrong, as ACF does.
		acf_validate_save_post( true );

		$added = false;

		if ( ! $id ) {
			$posted = function ( $key ) {
				return isset( $_POST['acf'][ $key ] ) ? sanitize_text_field( wp_unslash( $_POST['acf'][ $key ] ) ) : '';
			};
			$name   = SB_EmailSig_Names::join( $posted( SB_EmailSig_Names::FIRST ), $posted( SB_EmailSig_Names::LAST ) );
			$id   = wp_insert_post(
				array(
					'post_type'   => SB_EMAILSIG_CPT,
					'post_status' => 'publish',
					'post_title'  => $name !== '' ? $name : 'New person',
				),
				true
			);

			if ( is_wp_error( $id ) || ! $id ) {
				wp_die( esc_html( SB_EmailSig_Limits::full( 'people' ) ? SB_EmailSig_Limits::message( 'people' ) : 'They weren\'t added. Go back and try again.' ) );
			}

			$added = true;
		}

		acf_save_post( $id );
		SB_EmailSig_CPT::ensure_key( $id );

		wp_safe_redirect( add_query_arg( 'saved', $added ? 'added' : '1', self::url( $id ) ) );
		exit;
	}

	/** Delete: to the Trash, then back to everyone. */
	public static function delete() {
		$id = isset( $_POST['sb_emailsig_person'] ) ? absint( $_POST['sb_emailsig_person'] ) : 0;

		check_admin_referer( self::NONCE . '_delete_' . $id );

		if ( ! $id || get_post_type( $id ) !== SB_EMAILSIG_CPT || ! self::allowed( $id ) || ! current_user_can( 'delete_post', $id ) ) {
			wp_die( 'You can\'t delete people.' );
		}

		wp_trash_post( $id );

		// From the team page: back to the one they were on, which says where they went.
		self::back_to_team( array( 'trashed' => $id ) );

		wp_safe_redirect( admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-signatures&trashed=1' ) );
		exit;
	}

	/**
	 * A person in the Trash, from Deleted on the Signatures page: checked,
	 * or a page saying why not. $what is restore or purge.
	 */
	private static function trashed_person( $what ) {
		$id = isset( $_POST['sb_emailsig_person'] ) ? absint( $_POST['sb_emailsig_person'] ) : 0;

		check_admin_referer( self::NONCE . '_' . $what . '_' . $id );

		if ( ! $id || get_post_type( $id ) !== SB_EMAILSIG_CPT || get_post_status( $id ) !== 'trash' || ! SB_EmailSig_Admin::can_manage() || ! current_user_can( 'delete_post', $id ) ) {
			wp_die( 'That person can\'t be changed here. Go back and reload the page.' );
		}

		return $id;
	}

	/**
	 * Restore: out of the Trash and back where they were when deleted
	 * (Sam, 30 October 2026): Active if they were on, else Inactive.
	 * WordPress keeps that as _wp_trash_meta_status until untrashed.
	 */
	public static function restore() {
		$id     = self::trashed_person( 'restore' );
		$status = get_post_meta( $id, '_wp_trash_meta_status', true ) === 'publish' ? 'publish' : 'draft';

		wp_untrash_post( $id );

		if ( get_post_status( $id ) !== $status ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
		}

		self::back_to_team( array( 'restored' => $id ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-signatures&restored=' . $id ) );
		exit;
	}

	/**
	 * From a team page (sb_back=team): back to the one it was (everyone's or
	 * a company's), with $args saying what happened. Else carries on.
	 */
	private static function back_to_team( array $args ) {
		if ( ! isset( $_POST['sb_back'] ) || $_POST['sb_back'] !== 'team' ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked by the caller.
			return;
		}

		$from = (string) wp_get_referer();
		$from = strpos( $from, 'sb_emailsigs=' ) !== false ? remove_query_arg( array( 'trashed', 'restored', 'purged' ), $from ) : SB_EmailSig_Page::list_url();

		wp_safe_redirect( add_query_arg( $args, $from ) );
		exit;
	}

	/** Delete for good: the post, its photos (SB_EmailSig_Photo::on_delete) and the rest. */
	public static function purge() {
		$id = self::trashed_person( 'purge' );

		wp_delete_post( $id, true );

		self::back_to_team( array( 'purged' => 1 ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-signatures&purged=1' ) );
		exit;
	}

	/**
	 * The preview (Sam, 30 October 2026): the signature in $tpl_id (one of
	 * $list, the templates they can use, else the first) as the template
	 * editor shows it, with a stand-in for each part that has nothing in it
	 * yet, the switcher between their templates, and what that template
	 * leaves out.
	 */
	private static function view( $id, array $list, $tpl_id ) {
		$tpl_id = in_array( (string) $tpl_id, $list, true ) ? (string) $tpl_id : ( $list ? $list[0] : SB_EmailSig_Templates::current() );
		$tpl    = SB_EmailSig_Templates::get( $tpl_id );
		$out    = array(
			'html'     => '',
			'switcher' => self::switcher( $list, $tpl_id ),
			'off'      => '',
			'template' => $tpl_id,
		);

		if ( ! $tpl ) {
			return $out;
		}

		$d            = SB_EmailSig_Data::get( $id, $tpl['id'] );
		$d['preview'] = true;
		$out['html']  = SB_EmailSig_Render::html_from_data( $d, (string) $tpl['id'] );
		$s            = SB_EmailSig_Styles::resolve( $tpl );
		$out['off']   = self::off( isset( $s['layout'] ) && is_array( $s['layout'] ) ? SB_EmailSig_Layout::disabled( $s['layout'] ) : array() );

		return $out;
	}

	/** Their templates as tabs above the preview, main first: nothing when there's only one. */
	private static function switcher( array $list, $current ) {
		if ( count( $list ) < 2 ) {
			return '';
		}

		$out = '<div class="sb-ui-seg" role="group" aria-label="Preview in">';

		foreach ( $list as $i => $tpl_id ) {
			$tpl = SB_EmailSig_Templates::get( $tpl_id );

			if ( $tpl ) {
				$out .= '<button type="button" data-sb-emailsig-tpl="' . esc_attr( $tpl['id'] ) . '"' . ( (string) $tpl['id'] === (string) $current ? ' aria-current="page"' : '' ) . ( $i === 0 ? ' data-sb-tip="Their main template"' : '' ) . '>' . esc_html( $tpl['name'] ) . '</button>';
			}
		}

		return $out . '</div>';
	}

	/** What the template leaves out, as chips under the preview. */
	private static function off( array $labels ) {
		if ( ! $labels ) {
			return '';
		}

		$out = '<h4>Disabled on this template</h4><div class="sb-emailsig-person-editor__off-list">';

		foreach ( $labels as $label ) {
			$out .= '<span class="sb-emailsig-chip">' . esc_html( $label ) . '</span>';
		}

		return $out . '</div>';
	}

	/**
	 * The signature as the form stands: the posted simple fields, photo, crop
	 * and template picks in place of the saved ones, in the template picked
	 * above the preview. Nothing is saved.
	 */
	public static function preview() {
		check_ajax_referer( self::NONCE . '_preview', 'nonce' );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id || get_post_type( $id ) !== SB_EMAILSIG_CPT || ! self::allowed( $id ) ) {
			wp_send_json_error( null, 403 );
		}

		$posted = isset( $_POST['acf'] ) && is_array( $_POST['acf'] ) ? wp_unslash( $_POST['acf'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each value cleaned below.
		$over   = array();

		foreach ( self::LIVE as $key ) {
			$k = 'field_sb_emailsig_' . $key;

			if ( isset( $posted[ $k ] ) && ! is_array( $posted[ $k ] ) ) {
				$over[ $k ] = sanitize_text_field( (string) $posted[ $k ] );
			}
		}

		// The full name as the first and last names typed make it (SB_EmailSig_Names).
		if ( isset( $over[ SB_EmailSig_Names::FIRST ] ) || isset( $over[ SB_EmailSig_Names::LAST ] ) ) {
			$over[ SB_EmailSig_Names::FULL ] = SB_EmailSig_Names::join( $over[ SB_EmailSig_Names::FIRST ] ?? '', $over[ SB_EmailSig_Names::LAST ] ?? '' );
		}

		// Their own Info List rows as they stand (added, removed, moved or
		// typed in), as ACF keeps them before formatting: rows of sub field
		// key => value. None left posts an empty value.
		if ( isset( $posted[ self::INFO ] ) ) {
			$rows = array();

			foreach ( is_array( $posted[ self::INFO ] ) ? $posted[ self::INFO ] : array() as $key => $row ) {
				if ( $key === 'acfcloneindex' || ! is_array( $row ) ) {
					continue;
				}

				$clean = array();

				foreach ( self::INFO_SUB as $sub ) {
					$clean[ $sub ] = isset( $row[ $sub ] ) && ! is_array( $row[ $sub ] ) ? sanitize_text_field( (string) $row[ $sub ] ) : '';
				}

				$rows[] = $clean;
			}

			$over[ self::INFO ] = $rows;
		}

		// The photo picked (an attachment id, or none).
		if ( isset( $posted['field_sb_emailsig_photo'] ) && ! is_array( $posted['field_sb_emailsig_photo'] ) ) {
			$over['field_sb_emailsig_photo'] = absint( $posted['field_sb_emailsig_photo'] ) ?: '';
		}

		// Their templates as picked: the main ('' for the company's) and the extras.
		$main   = isset( $posted['field_sb_emailsig_person_template'] ) && ! is_array( $posted['field_sb_emailsig_person_template'] ) ? sanitize_text_field( (string) $posted['field_sb_emailsig_person_template'] ) : null;
		$extras = null;

		if ( isset( $_POST['sb_extras'] ) ) {
			$extras = array_map( 'sanitize_text_field', array_filter( (array) wp_unslash( $_POST['sb_extras'] ), 'is_string' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned here.
		}

		// Their company as picked, if it isn't saved yet: its templates and its details.
		$co = isset( $posted['field_sb_emailsig_person_company'] ) && ! is_array( $posted['field_sb_emailsig_person_company'] ) ? absint( $posted['field_sb_emailsig_person_company'] ) : 0;
		$co = SB_EmailSig_Company::valid( $co ) ? $co : null;

		$list = SB_EmailSig_Templates::available( $id, $main, $extras, $co );
		$show = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';

		$swap = function ( $value, $post_id, $field ) use ( $id, $over ) {
			return ( (int) $post_id === $id && isset( $field['key'] ) && array_key_exists( $field['key'], $over ) ) ? $over[ $field['key'] ] : $value;
		};

		add_filter( 'acf/pre_load_value', $swap, 10, 3 );
		SB_EmailSig_Photo::live( $id, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each value cleaned in live().
		SB_EmailSig_Company::force( $id, $co );
		$view = SB_EmailSig_Company::with(
			SB_EmailSig_Company::of_person( $id ),
			function () use ( $id, $list, $show ) {
				return self::view( $id, $list, $show );
			}
		);
		SB_EmailSig_Company::force( $id, null );
		SB_EmailSig_Photo::unlive();
		remove_filter( 'acf/pre_load_value', $swap, 10 );

		wp_send_json_success( $view );
	}
}
