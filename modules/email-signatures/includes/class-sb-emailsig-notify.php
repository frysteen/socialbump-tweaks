<?php
/**
 * Telling people about their signature.
 *
 * Changed since copied: each person's finished signature has a fingerprint
 * (md5 of its HTML). Pressing Copy signature anywhere (their page, the team
 * page, the admin) records the fingerprint copied (_sb_emailsig_copied:
 * fingerprint and time). If the signature now differs, what they have in Mail
 * is out of date: the card says Changed since copied and its email popup
 * starts on the Updated Signature email. Copies on the admin pages don't count, nor
 * an administrator's or editor's on the front end unless it's their own
 * signature (same email), as checking one isn't the person updating theirs.
 * Links (short links), the campaign banner, the photo and
 * the logo keep their addresses, so only real changes count: text, layout,
 * styles. A new photo or crop shows in pasted signatures by itself
 * (SB_EmailSig_Photo::fixed_url). Never copied: Not copied yet. Nothing is
 * sent by itself. A copy of one of the person's other templates (picked on
 * their page) is kept by template id in the same meta ('t'), and counts as
 * changed too while that template is still theirs.
 *
 * Email link: emails the person their page link, from Settings, Emails tab
 * (From name, and From address on the site's own domain, the same whoever
 * sends it, never the sender's own address; and the text of the new and updated
 * signature emails with merge tags: tags(), listed on the tab with click to
 * copy, render_tags() and assets/settings.js). The button opens a popup (the framework's, SBTweaksConfirm in
 * assets/notify.js) with the email filled in (draft()), to change before
 * Send. Sent with wp_mail as plain text, so an SMTP plugin sends it.
 * The last send is kept in _sb_emailsig_sent (time, kind, to).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Notify {

	const COPIED = '_sb_emailsig_copied';
	const SENT   = '_sb_emailsig_sent';

	/** The emails' text when never saved. */
	const TEXT = array(
		'new_subject'     => 'Your email signature',
		'new_message'     => "Hi {first_name},\n\nYour email signature is ready. Open this link on the computer or phone you send email from, tap Copy signature and follow the steps on the page:\n\n{link}\n\nThanks,\n{sender}",
		'updated_subject' => 'Your email signature has been updated',
		'updated_message' => "Hi {first_name},\n\nYour email signature has changed. Open this link, tap Copy signature and paste it over your old one:\n\n{link}\n\nThanks,\n{sender}",
	);

	public static function init() {
		add_action( 'wp_ajax_sb_emailsig_copied', array( __CLASS__, 'copied' ) );
		add_action( 'wp_ajax_nopriv_sb_emailsig_copied', array( __CLASS__, 'copied' ) );
		add_action( 'wp_ajax_sb_emailsig_send', array( __CLASS__, 'send' ) );
		add_action( 'wp_ajax_sb_emailsig_draft', array( __CLASS__, 'draft' ) );
		add_action( 'acf/render_field/key=field_sb_emailsig_mail_tags', array( __CLASS__, 'render_tags' ) );
		add_filter( 'acf/load_field/key=field_sb_emailsig_mail_from_name', array( __CLASS__, 'from_name_field' ) );
		add_filter( 'acf/load_field/key=field_sb_emailsig_mail_from_email', array( __CLASS__, 'from_field' ) );
		add_filter( 'acf/update_value/key=field_sb_emailsig_mail_from_email', array( __CLASS__, 'from_local' ) );
		add_filter( 'acf/validate_value/key=field_sb_emailsig_mail_from_email', array( __CLASS__, 'from_valid' ), 10, 2 );
	}

	/** The site's domain, for the From address (no www). */
	public static function domain() {
		return preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/** The site's name (Settings, General, Site Title), as text. */
	public static function site_name() {
		return trim( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
	}

	/** Settings: the site's name shown in the empty From Name box, as that's what's used. */
	public static function from_name_field( $field ) {
		$field['placeholder'] = self::site_name();

		return $field;
	}

	/** Settings: @ and the site's domain after the From Email box. */
	public static function from_field( $field ) {
		$field['append'] = '@' . self::domain();

		return $field;
	}

	/** Only the part before the @ is kept, even if a whole address is typed. */
	public static function from_local( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$at    = strpos( $value, '@' );

		return $at === false ? $value : substr( $value, 0, $at );
	}

	public static function from_valid( $valid, $value ) {
		if ( $valid !== true ) {
			return $valid;
		}

		$local = self::from_local( $value );

		return ( $local === '' || preg_match( '/^[a-z0-9][a-z0-9._+-]*$/', $local ) ) ? true : 'Use letters, numbers, dots, dashes or underscores, such as noreply or info.';
	}

	/**
	 * The emails' merge tags: [ tag => [ where it comes from, site-wide value
	 * or null for the person's own ] ]. A site-wide one that's blank fills in
	 * as nothing, so the Settings list greys it out.
	 */
	public static function tags() {
		// Company and Website: the person's company (compose() enters it).
		$o = SB_EmailSig_Company::source();

		return array(
			'{first_name}' => array( 'The person\'s First Name', null ),
			'{name}'       => array( 'The person\'s Name', null ),
			'{job_title}'  => array( 'The person\'s Job Title', null ),
			'{email}'      => array( 'The person\'s Email (where it\'s sent)', null ),
			'{link}'       => array( 'Their signature page, to copy it from', null ),
			'{sender}'     => array( 'Whoever sends it (their WordPress name)', null ),
			'{company}'    => array( 'Their company\'s Company Name', trim( (string) get_field( 'sb_emailsig_company_name', $o ) ) ),
			'{website}'    => array( 'Their company\'s Website', trim( (string) get_field( 'sb_emailsig_website', $o ) ) ),
			'{site_name}'  => array( 'Site Title in WordPress Settings, General', self::site_name() ),
		);
	}

	/** Settings, Emails tab: every merge tag, click to copy (assets/settings.js). */
	public static function render_tags() {
		echo '<div class="sb-emailsig-tags">';

		// With more than one company, Company and Website are each person's
		// company's, so no one company's value is shown here (Sam, 31 October 2026).
		$multi = count( SB_EmailSig_Company::all() ) > 1;

		foreach ( self::tags() as $tag => $t ) {
			if ( $multi && in_array( $tag, array( '{company}', '{website}' ), true ) ) {
				$t[1] = null;
			}

			$empty = $t[1] !== null && $t[1] === '';
			$note  = $t[0] . ( $t[1] !== null ? ( $empty ? ': not set yet' : ': ' . $t[1] ) : '' );

			echo '<div class="sb-emailsig-tags__row' . ( $empty ? ' is-empty' : '' ) . '">';
			echo '<button type="button" class="sb-emailsig-token" data-sb-emailsig-tag="' . esc_attr( $tag ) . '"' . ( $empty ? ' disabled title="Set it first, then save"' : ' title="Copy ' . esc_attr( $tag ) . '"' ) . '><code>' . esc_html( $tag ) . '</code></button>';
			echo '<span class="sb-emailsig-tags__note">' . esc_html( $note ) . '</span>';
			echo '</div>';
		}

		echo '</div>';
	}

	public static function fingerprint( $html ) {
		return md5( (string) $html );
	}

	/** new (never copied), changed, or current. $fingerprint: the main template's signature now. */
	public static function state( $post_id, $fingerprint ) {
		$copied = get_post_meta( $post_id, self::COPIED, true );
		$copied = is_array( $copied ) ? $copied : array();
		$others = isset( $copied['t'] ) && is_array( $copied['t'] ) ? $copied['t'] : array();

		if ( empty( $copied['fp'] ) && ! $others ) {
			return 'new';
		}

		if ( ! empty( $copied['fp'] ) && ! hash_equals( (string) $copied['fp'], (string) $fingerprint ) ) {
			return 'changed';
		}

		// Other templates copied, while still theirs.
		$available = SB_EmailSig_Templates::available( $post_id );

		foreach ( $others as $tpl_id => $fp ) {
			if ( in_array( (string) $tpl_id, $available, true ) && ! hash_equals( (string) $fp, self::fingerprint( SB_EmailSig_Render::html( $post_id, (string) $tpl_id ) ) ) ) {
				return 'changed';
			}
		}

		return 'current';
	}

	/**
	 * The Copy signature button's data: Copy records the version copied
	 * (copy.js posts it). The person's page key proves they may.
	 */
	public static function mark_attr( $post_id, $fingerprint, $tpl_id = '' ) {
		return ' data-sb-emailsig-mark="' . esc_attr( wp_json_encode( self::mark( $post_id, $fingerprint, $tpl_id ) ) ) . '"';
	}

	/** The data mark_attr() carries; $tpl_id '' for the person's main template. */
	public static function mark( $post_id, $fingerprint, $tpl_id = '' ) {
		$out = array(
			'ajax' => admin_url( 'admin-ajax.php' ),
			'id'   => (int) $post_id,
			'key'  => SB_EmailSig_CPT::get_key( $post_id ),
			'fp'   => $fingerprint,
		);

		if ( (string) $tpl_id !== '' && (string) $tpl_id !== SB_EmailSig_Templates::for_person( $post_id ) ) {
			$out['t'] = (string) $tpl_id;
		}

		return $out;
	}

	/** Copy signature was pressed: keep the version copied. */
	public static function copied() {
		// phpcs:disable WordPress.Security.NonceVerification -- the person's page key stands in.
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$fp  = isset( $_POST['fp'] ) ? sanitize_key( wp_unslash( $_POST['fp'] ) ) : '';
		$t   = isset( $_POST['t'] ) ? sanitize_key( wp_unslash( $_POST['t'] ) ) : '';
		// phpcs:enable

		if ( ! $id || get_post_type( $id ) !== SB_EMAILSIG_CPT || $key === '' || ! hash_equals( SB_EmailSig_CPT::get_key( $id ), $key ) || ! preg_match( '/^[a-f0-9]{32}$/', $fp ) ) {
			wp_send_json_error( null, 403 );
		}

		// Someone who manages signatures checking one, not the person.
		if ( is_user_logged_in() && SB_EmailSig_Admin::can_manage() ) {
			$own = strtolower( (string) wp_get_current_user()->user_email ) === strtolower( trim( (string) get_field( 'sb_emailsig_email', $id ) ) );

			if ( ! $own ) {
				wp_send_json_success( 'skipped' );
			}
		}

		$copied = get_post_meta( $id, self::COPIED, true );
		$copied = is_array( $copied ) ? $copied : array();

		// Another of their templates: kept beside the main one's.
		if ( $t !== '' && $t !== SB_EmailSig_Templates::for_person( $id ) && in_array( $t, SB_EmailSig_Templates::available( $id ), true ) ) {
			$copied['t']       = isset( $copied['t'] ) && is_array( $copied['t'] ) ? $copied['t'] : array();
			$copied['t'][ $t ] = $fp;
		} else {
			$copied['fp'] = $fp;
		}

		$copied['time'] = time();
		update_post_meta( $id, self::COPIED, $copied );

		// The activity log: by them (it's their page), or by whoever is logged in and copying their own.
		$other = $t !== '' && $t !== SB_EmailSig_Templates::for_person( $id ) && SB_EmailSig_Templates::get( $t ) ? SB_EmailSig_Templates::get( $t )['name'] : '';
		$who   = is_user_logged_in() ? '' : trim( (string) get_field( 'sb_emailsig_name', $id ) );
		SB_EmailSig_Log::add( 'copied', 'person', $id, '', $other, $who );

		wp_send_json_success( 'recorded' );
	}

	/** The card's note: Changed since copied, or Not copied yet. '' when up to date. */
	public static function badge( $state ) {
		if ( $state === 'changed' ) {
			return '<span class="sb-emailsig-state sb-emailsig-state--changed">Changed since copied</span>';
		}

		if ( $state === 'new' ) {
			return '<span class="sb-emailsig-state sb-emailsig-state--new">Not copied yet</span>';
		}

		return '';
	}

	/**
	 * The Email link button. The popup picks New or Updated Signature,
	 * Updated first when the signature has changed since it was copied
	 * (Sam, 31 October 2026), so the button stays the same. $classes for
	 * the page's button style. Not shown without an email address.
	 */
	public static function button( $post_id, $state, $classes ) {
		$email = trim( (string) get_field( 'sb_emailsig_email', $post_id ) );

		if ( $email === '' ) {
			return '';
		}

		return '<button type="button" class="' . esc_attr( $classes ) . '" data-sb-emailsig-send="' . (int) $post_id . '" title="' . esc_attr( 'Email ' . $email . ' their signature link' ) . '">Email link</button>';
	}

	/** A SocialBUMP Tweaks framework file's address, versioned by its time, or ''. */
	public static function framework_file( $rel ) {
		if ( ! defined( 'SB_TWEAKS_URL' ) || ! defined( 'SB_TWEAKS_PATH' ) || ! file_exists( SB_TWEAKS_PATH . $rel ) ) {
			return '';
		}

		return SB_TWEAKS_URL . $rel . '?ver=' . SB_EmailSig_Module::version() . '.' . filemtime( SB_TWEAKS_PATH . $rel );
	}

	/** The framework's popup script (its handle), for admin screens it doesn't load it on. */
	public static function register_confirm() {
		if ( wp_script_is( 'sb-tweaks-confirm', 'registered' ) || ! defined( 'SB_TWEAKS_URL' ) || ! defined( 'SB_TWEAKS_PATH' ) ) {
			return;
		}

		$file = SB_TWEAKS_PATH . 'assets/js/confirm.js';
		wp_register_script( 'sb-tweaks-confirm', SB_TWEAKS_URL . 'assets/js/confirm.js', array( 'jquery' ), SB_EmailSig_Module::version() . '.' . ( file_exists( $file ) ? filemtime( $file ) : '0' ), true );
	}

	/** For the pages with Email link buttons: [ ajax, nonce ]. */
	public static function config() {
		return array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'sb_emailsig_send' ),
		);
	}

	/** Settings, with defaults where never saved. */
	public static function settings() {
		$o       = 'option';
		$company = trim( (string) get_field( 'sb_emailsig_company_name', SB_EmailSig_Company::source() ) );
		$local   = self::from_local( (string) get_field( 'sb_emailsig_mail_from_email', $o ) );
		$from    = sanitize_email( ( $local !== '' ? $local : 'noreply' ) . '@' . self::domain() );
		$out     = array(
			'from_name'  => trim( (string) get_field( 'sb_emailsig_mail_from_name', $o ) ) ?: ( self::site_name() ?: $company ),
			'from_email' => is_email( $from ) ? $from : 'noreply@' . self::domain(),
		);

		// New and Updated Signature are groups (cards on Settings), read by key.
		$groups = array();
		foreach ( array( 'new', 'updated' ) as $g ) {
			$groups[ $g ] = (array) get_field( 'field_sb_emailsig_mail_' . $g, $o );
		}

		foreach ( self::TEXT as $key => $default ) {
			list( $g, $part ) = explode( '_', $key, 2 );
			$value            = $groups[ $g ][ $part ] ?? null;
			$out[ $key ]      = ( null === $value || trim( (string) $value ) === '' ) ? $default : (string) $value;
		}

		return $out;
	}

	/** The emails Settings has, kind => name, for the popup's choice. */
	const KINDS = array(
		'new'     => 'New Signature',
		'updated' => 'Updated Signature',
	);

	/**
	 * The email for a person as Settings words it, filled in: [ to, kind,
	 * subject, message, drafts ]. The kind is the one posted, else Updated
	 * when the signature has changed since it was copied, else New; drafts
	 * has each kind's subject and message. Stops with a JSON error if it
	 * can't be sent.
	 */
	private static function compose() {
		check_ajax_referer( 'sb_emailsig_send', 'nonce' );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id || get_post_type( $id ) !== SB_EMAILSIG_CPT || ! SB_EmailSig_Admin::can_manage() || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( 'Not allowed.', 403 );
		}

		if ( get_post_status( $id ) !== 'publish' ) {
			wp_send_json_error( 'Switch them on first: their page only opens while they\'re active.' );
		}

		if ( SB_EmailSig_Switch::person_off( $id ) ) {
			wp_send_json_error( 'Switch ' . SB_EmailSig_Company::name( SB_EmailSig_Company::of_person( $id ) ) . ' on first: its pages are closed while it\'s off.' );
		}

		// Their company's name and website in the email (one email a request).
		SB_EmailSig_Company::enter( SB_EmailSig_Company::of_person( $id ) );

		$to = sanitize_email( (string) get_field( 'sb_emailsig_email', $id ) );

		if ( ! is_email( $to ) ) {
			wp_send_json_error( 'They have no email address.' );
		}

		$set     = self::settings();
		$asked   = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$kind    = isset( self::KINDS[ $asked ] ) ? $asked : ( self::state( $id, self::fingerprint( SB_EmailSig_Render::html( $id ) ) ) === 'changed' ? 'updated' : 'new' );
		$name    = trim( (string) get_field( 'sb_emailsig_name', $id ) );
		$first   = trim( (string) get_field( 'sb_emailsig_first_name', $id ) );
		$sender  = wp_get_current_user();
		$company = trim( (string) get_field( 'sb_emailsig_company_name', SB_EmailSig_Company::source() ) );
		$swap    = array(
			'{first_name}' => $first !== '' ? $first : ( strtok( $name, ' ' ) ?: $name ),
			'{name}'       => $name,
			'{job_title}'  => trim( (string) get_field( 'sb_emailsig_job_title', $id ) ),
			'{email}'      => $to,
			'{link}'       => SB_EmailSig_CPT::page_url( $id ),
			'{sender}'     => $sender && $sender->exists() ? $sender->display_name : ( $company ?: get_bloginfo( 'name' ) ),
		);
		foreach ( self::tags() as $tag => $t ) {
			if ( $t[1] !== null ) {
				$swap[ $tag ] = $t[1];
			}
		}

		$drafts = array();
		foreach ( self::KINDS as $k => $label ) {
			$drafts[ $k ] = array(
				'label'   => $label,
				'subject' => strtr( $set[ $k . '_subject' ], $swap ),
				'message' => strtr( $set[ $k . '_message' ], $swap ),
			);
		}

		return array(
			'id'      => $id,
			'to'      => $to,
			'kind'    => $kind,
			'name'    => $name,
			'subject' => $drafts[ $kind ]['subject'],
			'message' => $drafts[ $kind ]['message'],
			'drafts'  => $drafts,
			// From Name takes the merge tags too, e.g. {company}.
			'from'    => trim( strtr( $set['from_name'], $swap ) ) ?: self::site_name(),
		);
	}

	/** The popup's starting text. */
	public static function draft() {
		$mail = self::compose();

		wp_send_json_success(
			array(
				'to'      => $mail['to'],
				'name'    => $mail['name'],
				'kind'    => $mail['kind'],
				'subject' => $mail['subject'],
				'message' => $mail['message'],
				'drafts'  => $mail['drafts'],
			)
		);
	}

	/** Email link: sends the person their page link, in the words and kind from the popup. */
	public static function send() {
		$mail = self::compose();
		$id   = $mail['id'];
		$to   = $mail['to'];
		$kind = $mail['kind'];

		// phpcs:disable WordPress.Security.NonceVerification -- checked in compose().
		$subject = isset( $_POST['subject'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['subject'] ) ) ) : '';
		$message = isset( $_POST['message'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) ) : '';
		// phpcs:enable

		// From the site's own address only: no Reply-To with the sender's
		// address (personal ones, and mail servers block other domains).
		$set     = self::settings();
		$headers = array(
			'From: ' . self::header_name( $mail['from'] ) . ' <' . $set['from_email'] . '>',
			'Content-Type: text/plain; charset=UTF-8',
		);

		$sent = wp_mail( $to, $subject !== '' ? $subject : $mail['subject'], $message !== '' ? $message : $mail['message'], $headers );

		if ( ! $sent ) {
			wp_send_json_error( 'Not sent. Check the site can send email.' );
		}

		update_post_meta(
			$id,
			self::SENT,
			array(
				'time' => time(),
				'kind' => $kind,
				'to'   => $to,
			)
		);
		SB_EmailSig_Log::add( 'emailed', 'person', $id, '', $kind );

		wp_send_json_success( 'Sent to ' . $to . '.' );
	}

	/** A name safe in a From header. */
	private static function header_name( $name ) {
		$name = trim( preg_replace( '/[\r\n"<>]+/', ' ', (string) $name ) );

		return $name === '' ? '' : '"' . $name . '"';
	}
}
