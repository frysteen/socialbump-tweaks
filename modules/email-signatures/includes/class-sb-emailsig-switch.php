<?php
/**
 * A company switched on or off (Sam, 31 October 2026): for a company that no
 * longer pays to have its signatures managed. Off, everything of theirs stops,
 * without switching each person off or deleting anything:
 *
 * - its team page and its people's pages (SB_EmailSig_Page), for everyone
 *   but administrators and editors, who see it dimmed with its switch;
 * - the photo, vCard, campaign banner and tracked link addresses in their
 *   signatures, which go through the site (Photo, Vcard, Campaign,
 *   Tracking): not found;
 * - its icons: the files are moved aside (park_icons()), so signatures
 *   already pasted lose them, and moved back when it's on again;
 * - its logos: the module's sizes are taken off (SB_EmailSig_Logo::park()),
 *   and made again, with the same names, when it's on again. A logo used at
 *   its full size, and footer banners, are the Media Library's own files
 *   and stay.
 *
 * Its people can't be switched on, or emailed, while it's off. Switched by
 * administrators, on the company's bar (Signatures page, everyone's team
 * page) and a company's own team page. Logged (company disabled, enabled),
 * so open pages reload (SB_EmailSig_Live).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Switch {

	/** On a company switched off: when. */
	const META = 'sb_emailsig_off';

	const NONCE = 'sb_emailsig_company_switch';

	/** Where a company's icons wait while it's off: in the module's upload folder, off-c{id}. */
	const PARK = 'off-c';

	private static $off = array();

	public static function init() {
		add_action( 'wp_ajax_sb_emailsig_company_switch', array( __CLASS__, 'ajax' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'stay_off' ), 20, 2 );
	}

	/** However a person is saved (their page, an import): not switched on while their company is off. */
	public static function stay_off( $data, $postarr ) {
		$id = (int) ( $postarr['ID'] ?? 0 );

		if ( ! $id || SB_EMAILSIG_CPT !== ( $data['post_type'] ?? '' ) || 'publish' !== ( $data['post_status'] ?? '' ) ) {
			return $data;
		}

		$was = get_post_status( $id );

		if ( $was && 'publish' !== $was && self::person_off( $id ) ) {
			$data['post_status'] = 'trash' === $was ? 'draft' : $was;
		}

		return $data;
	}

	/** Company $co switched off. */
	public static function is_off( $co ) {
		$co = (int) $co;

		if ( ! $co ) {
			return false;
		}

		if ( ! isset( self::$off[ $co ] ) ) {
			self::$off[ $co ] = (bool) get_post_meta( $co, self::META, true );
		}

		return self::$off[ $co ];
	}

	/** Person $id's company switched off. */
	public static function person_off( $id ) {
		return $id && self::is_off( SB_EmailSig_Company::of_person( (int) $id ) );
	}

	/** Who may switch a company: administrators. */
	public static function can() {
		return current_user_can( 'manage_options' );
	}

	/** ' is-off' for a company's group or bar while it's off. */
	public static function off_class( $co ) {
		return self::is_off( $co ) ? ' is-off' : '';
	}

	/** The switch, for a company's bar (administrators only; '' otherwise). */
	public static function control( $co ) {
		if ( ! self::can() ) {
			return '';
		}

		$on   = ! self::is_off( $co );
		$name = SB_EmailSig_Company::name( $co );

		return '<label class="sb-tweaks-switch sb-emailsig-company-switch" data-sb-tip="' . esc_attr( $on ? 'Switch ' . $name . ' off' : 'Switch ' . $name . ' on' ) . '"><input type="checkbox" data-sb-emailsig-company-switch="' . (int) $co . '" data-sb-emailsig-company-name="' . esc_attr( $name ) . '"' . checked( $on, true, false ) . '><span class="sb-tweaks-switch__track"><span class="sb-tweaks-switch__dot"></span></span><span class="screen-reader-text">' . esc_html( $name ) . ' on</span></label>';
	}

	/** For the pages with the switch: [ ajax, nonce ]. */
	public static function config() {
		return self::can()
			? array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE ),
			)
			: array();
	}

	public static function ajax() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$co = isset( $_POST['company'] ) ? absint( $_POST['company'] ) : 0;

		if ( ! self::can() || ! $co || ! SB_EmailSig_Company::valid( $co ) ) {
			wp_send_json_error( null, 403 );
		}

		// Not past this site's limit, set from the hub (SB_EmailSig_Limits).
		if ( ! empty( $_POST['on'] ) && self::is_off( $co ) && SB_EmailSig_Limits::full( 'companies', $co ) ) {
			wp_send_json_error( SB_EmailSig_Limits::message( 'companies' ) );
		}

		self::set( $co, ! empty( $_POST['on'] ) );

		wp_send_json_success( array( 'on' => ! self::is_off( $co ) ) );
	}

	/** Switch company $co on or off, and everything that goes with it. */
	public static function set( $co, $on ) {
		$co = (int) $co;

		if ( $on === ! self::is_off( $co ) ) {
			return;
		}

		if ( $on ) {
			delete_post_meta( $co, self::META );
			self::$off[ $co ] = false;
			self::unpark_icons( $co );
			SB_EmailSig_Logo::unpark( $co );
			SB_EmailSig_Log::add( 'enabled', 'company', $co );
		} else {
			update_post_meta( $co, self::META, time() );
			self::$off[ $co ] = true;
			self::park_icons( $co );
			SB_EmailSig_Logo::park( $co );
			SB_EmailSig_Log::add( 'disabled', 'company', $co );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Icons
	 * ------------------------------------------------------------------ */

	private static function icon_dir() {
		$up = wp_upload_dir();

		return trailingslashit( $up['basedir'] ) . SB_EmailSig_Photo::UPLOAD;
	}

	/** The icon files company $co's signatures use: those in its own slots (SB_EmailSig_Icons::slot()). */
	private static function icon_files( $co ) {
		$co    = (int) $co;
		$def   = (int) SB_EmailSig_Company::default_id();
		$files = array();

		foreach ( (array) get_option( SB_EmailSig_Icons::OPTION, array() ) as $slot => $set ) {
			$slot = (string) $slot;
			$mine = $co === $def ? strpos( $slot, '@c' ) === false : substr( $slot, -strlen( '@c' . $co ) ) === '@c' . $co;

			if ( ! $mine || ! is_array( $set ) ) {
				continue;
			}

			foreach ( array( 'contact', 'social' ) as $kind ) {
				foreach ( (array) ( $set[ $kind ] ?? array() ) as $file ) {
					if ( is_string( $file ) && $file !== '' ) {
						$files[] = basename( $file );
					}
				}
			}
		}

		return array_values( array_unique( $files ) );
	}

	/** Move company $co's icons aside, out of reach (the folder answers no one). */
	private static function park_icons( $co ) {
		$from = self::icon_dir() . '/icons';
		$to   = self::icon_dir() . '/' . self::PARK . (int) $co;

		wp_mkdir_p( $to );

		if ( ! file_exists( $to . '/index.php' ) ) {
			file_put_contents( $to . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( ! file_exists( $to . '/.htaccess' ) ) {
			file_put_contents( $to . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		foreach ( self::icon_files( $co ) as $file ) {
			if ( file_exists( $from . '/' . $file ) ) {
				rename( $from . '/' . $file, $to . '/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
	}

	/** Put company $co's icons back. */
	private static function unpark_icons( $co ) {
		$from = self::icon_dir() . '/' . self::PARK . (int) $co;
		$to   = self::icon_dir() . '/icons';

		if ( ! is_dir( $from ) ) {
			return;
		}

		wp_mkdir_p( $to );

		foreach ( (array) glob( $from . '/*.png' ) as $path ) {
			$file = basename( (string) $path );

			if ( file_exists( $to . '/' . $file ) ) {
				wp_delete_file( $path );
			} else {
				rename( $path, $to . '/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
	}
}
