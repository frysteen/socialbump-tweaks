<?php
/**
 * Front-end pages, never cached or indexed:
 *
 * - a person's signature page, /?sb_emailsig={id}&key={key}: staff open it
 *   on their Mac or iPhone, tap Copy, paste into Mail. Active (published)
 *   people only.
 * - the team page, /?sb_emailsigs={key}: everyone active as a card, the same
 *   cards as the Signatures page (SB_EmailSig_Admin::card()) with Preview,
 *   Copy link and Open their page. A company's own key
 *   (SB_EmailSig_Company::team_key()) shows only its people, in its name.
 *   The site's key shows every company, grouped, with a company filter and
 *   a search, to administrators and editors only; with one company it is
 *   that company's page, for anyone (Sam, 30 October 2026). Logged in
 *   administrators and editors also get Inactive, the Active switch, Edit,
 *   Email link and Delete, and Add person.
 *
 * Both are drawn in the SocialBUMP UI look (head_assets(), foot_assets()),
 * as the admin is (Sam, 30 October 2026).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Page {

	/** The team page's key. */
	const LIST_KEY_OPTION = 'sb_tweaks_email_signatures_list_key';

	public static function list_key() {
		$key = (string) get_option( self::LIST_KEY_OPTION, '' );

		if ( $key === '' ) {
			$key = wp_generate_password( 20, false, false );
			update_option( self::LIST_KEY_OPTION, $key, false );
		}

		return $key;
	}

	/** The team page's link: everyone's, or one company's ($co). */
	public static function list_url( $co = 0 ) {
		$key = $co && SB_EmailSig_Company::valid( $co ) ? SB_EmailSig_Company::team_key( $co ) : self::list_key();

		return add_query_arg( array( 'sb_emailsigs' => $key ), home_url( '/' ) );
	}

	/**
	 * Which company a team page key is for, as render_list() decides: 0 for
	 * everyone's page (for those who manage; with one company, that
	 * company's), else the company's id; -1 when it can't be seen.
	 * SB_EmailSig_Live checks a page's updates against it.
	 */
	public static function team_company( $key ) {
		$key = (string) $key;

		if ( $key === '' ) {
			return -1;
		}

		if ( hash_equals( self::list_key(), $key ) ) {
			if ( SB_EmailSig_Admin::can_manage() ) {
				return 0;
			}

			$all = SB_EmailSig_Company::all();

			return count( $all ) > 1 ? -1 : (int) ( $all ? $all[0] : 0 );
		}

		$co = (int) SB_EmailSig_Company::by_team_key( $key );

		return $co ? $co : -1;
	}

	/**
	 * Both pages in the SocialBUMP UI look, the same as the Signatures page
	 * (Sam, 30 October 2026): the framework's stylesheets and the module's,
	 * for the head. Light or dark follows the person's device, or their
	 * saved mode when logged in.
	 */
	public static function head_assets() {
		$files = array(
			includes_url( 'css/buttons.min.css' ),
			SB_EmailSig_Notify::framework_file( 'assets/css/admin.css' ),
			SB_EmailSig_Notify::framework_file( 'assets/css/ui.css' ),
			SB_EMAILSIG_URL . 'assets/admin.css?ver=' . SB_EmailSig_Module::asset_version( 'assets/admin.css' ),
		);

		foreach ( array_filter( $files ) as $href ) {
			echo '<link rel="stylesheet" href="' . esc_url( $href ) . '">' . "\n";
		}

		// The site icon in the tab. These pages skip wp_head(), so it was left to
		// the browser's /favicon.ico guess, which some tabs missed (Sam, 31 October 2026).
		wp_site_icon();
	}

	/** The body's classes: the look, and the mode. */
	public static function body_class() {
		$mode = is_user_logged_in() && class_exists( 'SB_Tweaks_UI' ) ? SB_Tweaks_UI::mode() : 'auto';

		return 'sb-ui sb-ui-mode-' . $mode . ' wp-core-ui sb-emailsig-front-page';
	}

	/** The framework's script (toasts, tooltips) and popup, before the page's own. */
	public static function foot_assets() {
		$cfg = array( 'mode' => 'auto' );

		if ( is_user_logged_in() && class_exists( 'SB_Tweaks_UI' ) ) {
			$cfg = array(
				'ajax'   => admin_url( 'admin-ajax.php' ),
				'action' => SB_Tweaks_UI::ACTION,
				'nonce'  => wp_create_nonce( SB_Tweaks_UI::ACTION ),
				'mode'   => SB_Tweaks_UI::mode(),
			);
		}

		echo '<script>var sbTweaksUI = ' . wp_json_encode( $cfg ) . ';</script>' . "\n";

		foreach ( array( SB_EmailSig_Notify::framework_file( 'assets/js/ui.js' ), includes_url( 'js/jquery/jquery.min.js' ), SB_EmailSig_Notify::framework_file( 'assets/js/confirm.js' ) ) as $src ) {
			if ( $src ) {
				echo '<script src="' . esc_url( $src ) . '"></script>' . "\n";
			}
		}
	}

	/** No caching, no indexing: the pages carry private links. */
	private static function private_headers() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'X-LiteSpeed-Cache-Control: no-cache', true );
	}

	/** The team page. */
	private static function render_list() {
		$key = isset( $_GET['sb_emailsigs'] ) ? sanitize_text_field( wp_unslash( $_GET['sb_emailsigs'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		self::private_headers();

		// The site's key: everyone. A company's: its people only.
		$co = hash_equals( self::list_key(), $key ) ? 0 : SB_EmailSig_Company::by_team_key( $key );

		if ( $key === '' || ( ! $co && ! hash_equals( self::list_key(), $key ) ) ) {
			status_header( 404 );
			echo 'Page not found.';
			exit;
		}

		$manage = SB_EmailSig_Admin::can_manage();
		$all    = SB_EmailSig_Company::all();

		// Everyone's page with more than one company: administrators and
		// editors only (Sam, 30 October 2026). With one company it is that
		// company's page, as it was before companies, so links already
		// shared keep working.
		if ( ! $co && ! $manage ) {
			if ( count( $all ) > 1 ) {
				// Logged out: to the login, then back. Logged in without the right: not here.
				if ( ! is_user_logged_in() ) {
					auth_redirect();
				}

				status_header( 404 );
				echo 'Page not found.';
				exit;
			}

			$co = $all ? $all[0] : 0;
		}

		// A company switched off (SB_EmailSig_Switch): its page is gone,
		// except for those who manage, and comes back by itself when it's on.
		if ( $co && ! $manage && SB_EmailSig_Switch::is_off( $co ) ) {
			self::listening_404(
				'Page not found.',
				array(
					'where' => 'team',
					'key'   => $key,
				)
			);
		}

		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );

		// Everyone's (administrators and editors): each company with its own
		// Active, Inactive and Deleted, a company filter and a search. One
		// company's: its people, Inactive and Deleted for those who manage.
		$groups = array();

		foreach ( $co ? array( $co ) : $all as $id ) {
			$groups[] = array(
				'id'       => (int) $id,
				'name'     => SB_EmailSig_Company::name( $id ),
				'link'     => self::list_url( $id ),
				'active'   => self::of_company( SB_EmailSig_Admin::people( array( 'publish' ) ), $id ),
				'inactive' => $manage ? self::of_company( SB_EmailSig_Admin::people( array( 'draft' ) ), $id ) : array(),
				'deleted'  => $manage ? self::of_company( SB_EmailSig_Admin::people( array( 'trash' ) ), $id ) : array(),
			);
		}

		$everyone = ! $co;
		$company  = $everyone ? '' : SB_EmailSig_Company::name( $co );
		$link     = self::list_url( $co );
		$tpl      = SB_EmailSig_Templates::get( SB_EmailSig_Templates::main_for( $co ? $co : SB_EmailSig_Company::default_id() ) );
		$teal     = $tpl ? SB_EmailSig_Styles::resolve( $tpl )['primary'] : '#2271b1';
		$title    = $everyone ? 'Email signatures for every company' : 'Email signatures' . ( $company ? ' for ' . $company : '' );

		$head     = self::head_mark( $everyone ? 0 : $co );
		$request  = ! $everyone && SB_EmailSig_Request::offered( $co );
		$off      = ! $everyone && SB_EmailSig_Switch::is_off( $co );

		include SB_EMAILSIG_DIR . 'views/signatures-page.php';
		exit;
	}

	/**
	 * The circle beside a front page's heading (Sam, 31 October 2026): on a
	 * person's page ($person) their photo first, as their main template makes
	 * it (a circle when it's square, else as it is); then the company's small
	 * logo, else its name's first letter in white on its Brand Primary, as on
	 * the Companies cards. $co 0 (the every-company page): Settings' Admin
	 * Branding small logo, else the site icon (Sam, 31 October 2026), else
	 * nothing.
	 */
	public static function head_mark( $co, $person = 0 ) {
		if ( $person ) {
			$photo = SB_EmailSig_Photo::png( $person );

			if ( $photo && $photo['width'] > 0 && $photo['height'] > 0 ) {
				$square = abs( $photo['width'] - $photo['height'] ) <= 1;
				$wide   = $square ? 72 : (int) round( 72 * $photo['width'] / $photo['height'] );

				return '<img class="sb-emailsig-front__logo' . ( $square ? '' : ' sb-emailsig-front__logo--photo' ) . '" src="' . esc_url( $photo['url'] ) . '" width="' . $wide . '" height="72" alt="' . esc_attr( (string) get_field( 'sb_emailsig_name', $person ) ) . '">';
			}
		}

		$co    = (int) $co;
		$small = $co ? (int) get_post_meta( $co, 'sb_emailsig_logo_small', true ) : 0;
		$logo  = $co ? ( $small ? SB_EmailSig_Data::image( $small ) : null ) : SB_EmailSig_Logo::site_small();

		if ( $logo ) {
			return '<img class="sb-emailsig-front__logo" src="' . esc_url( SB_EmailSig_Data::src( $logo, 72 ) ) . '" width="72" height="72" alt="' . esc_attr( $logo['alt'] !== '' ? $logo['alt'] : ( $co ? SB_EmailSig_Company::name( $co ) : get_bloginfo( 'name' ) ) ) . '">';
		}

		if ( ! $co ) {
			$icon = get_site_icon_url( 144 );

			return $icon ? '<img class="sb-emailsig-front__logo" src="' . esc_url( $icon ) . '" width="72" height="72" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">' : '';
		}

		$colour = SB_EmailSig_Company::with(
			$co,
			function () {
				return SB_EmailSig_Styles::brand()['primary']['hex'];
			}
		);

		return '<span class="sb-emailsig-front__logo sb-emailsig-front__logo--letter" style="background-color:' . esc_attr( $colour ) . ';" aria-hidden="true">' . esc_html( SB_EmailSig_Companies::initial( SB_EmailSig_Company::name( $co ) ) ) . '</span>';
	}

	/** Not found, in words, with live updating ($ctx) so the page comes back by itself. Ends the request. */
	private static function listening_404( $text, array $ctx ) {
		status_header( 404 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html>' . "\n" . '<html><head><meta charset="utf-8"><meta name="robots" content="noindex, nofollow"><title>' . esc_html( rtrim( $text, '.' ) ) . '</title></head><body>' . esc_html( $text ) . "\n";
		SB_EmailSig_Live::script( $ctx );
		echo '</body></html>';
		exit;
	}

	/** People in company $co (all for 0). */
	private static function of_company( array $ids, $co ) {
		if ( ! $co ) {
			return $ids;
		}

		return array_values(
			array_filter(
				$ids,
				function ( $id ) use ( $co ) {
					return SB_EmailSig_Company::of_person( $id ) === (int) $co;
				}
			)
		);
	}

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 0 );
	}

	public static function maybe_render() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['sb_emailsigs'] ) ) {
			self::render_list();
		}
		if ( ! isset( $_GET['sb_emailsig'] ) ) {
			return;
		}
		$post_id = absint( $_GET['sb_emailsig'] );
		$key     = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		// phpcs:enable

		$post = $post_id ? get_post( $post_id ) : null;
		// Active (published) people only: switching someone off on the
		// Signatures page closes their page too.
		// Nor while their company is switched off (SB_EmailSig_Switch).
		$ok   = $post && SB_EMAILSIG_CPT === $post->post_type && 'publish' === $post->post_status
			&& '' !== $key && hash_equals( SB_EmailSig_CPT::get_key( $post_id ), $key )
			&& ! SB_EmailSig_Switch::person_off( $post_id );

		self::private_headers();

		if ( ! $ok ) {
			status_header( 404 );

			// Their own key, but switched off (or their company is): the page
			// keeps listening, so it comes back by itself when they're switched
			// on (Sam, 31 October 2026).
			if ( $post && SB_EMAILSIG_CPT === $post->post_type && '' !== $key && hash_equals( SB_EmailSig_CPT::get_key( $post_id ), $key ) ) {
				self::listening_404(
					'Signature not found.',
					array(
						'where'  => 'person',
						'person' => (int) $post_id,
						'key'    => $key,
					)
				);
			}

			echo 'Signature not found.';
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );

		// Each template they can pick, their main one first.
		$sigs = array();
		foreach ( SB_EmailSig_Templates::available( $post_id ) as $tpl_id ) {
			$tpl_html = SB_EmailSig_Render::html( $post_id, $tpl_id );
			if ( $tpl_html !== '' ) {
				$sigs[ $tpl_id ] = array(
					'name' => SB_EmailSig_Templates::get( $tpl_id )['name'],
					'html' => $tpl_html,
					'mark' => SB_EmailSig_Notify::mark( $post_id, SB_EmailSig_Notify::fingerprint( $tpl_html ), $tpl_id ),
				);
			}
		}

		$html  = $sigs ? reset( $sigs )['html'] : '';
		$name  = (string) get_field( 'sb_emailsig_name', $post_id );
		$tpl   = SB_EmailSig_Templates::get( SB_EmailSig_Templates::for_person( $post_id ) );
		$teal  = $tpl ? SB_EmailSig_Styles::resolve( $tpl )['primary'] : '#2271b1';
		$title = 'Email signature' . ( $name ? ' for ' . $name : '' );
		$head  = self::head_mark( SB_EmailSig_Company::of_person( $post_id ), $post_id );

		include SB_EMAILSIG_DIR . 'views/signature-page.php';
		exit;
	}
}
