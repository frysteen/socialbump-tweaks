<?php
/**
 * Telling people about their signature.
 *
 * Copies: pressing Copy signature (or a Copy HTML code button) on a
 * person's page, or Copy signature in the admin's Signature box, is logged
 * (SB_EmailSig_Log): the person, the template's name, and the name of
 * whoever was logged in (Sam, 1 November 2026). The old copied or not
 * copied status (Changed since copied, Not copied yet, Resend) is gone,
 * and what it kept against each person (_sb_emailsig_copied) is deleted
 * once (forget_copies()).
 *
 * Email link: emails the person their page link, from Settings, Emails tab
 * (From name, and From address on the site's own domain, the same whoever
 * sends it, never the sender's own address; and the text of the new and updated
 * signature emails with merge tags: tags(), listed on the tab with click to
 * copy, render_tags() and assets/settings.js). The button opens a popup (the framework's, SBTweaksConfirm in
 * assets/notify.js) with the email filled in (draft()), to change before
 * Send: To starts as their Email, else their company's, else blank, and
 * can be changed (Sam, 1 November 2026). It starts on Updated Signature
 * for someone sent their link before, else New. {sender} is Settings'
 * Default Sender Name, else whoever sends it. Sent with wp_mail as plain
 * text, so an SMTP plugin sends it. The last send is kept in
 * _sb_emailsig_sent (time, kind, to).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Notify {

	/** What the old copied status kept: deleted once (forget_copies()). */
	const COPIED = '_sb_emailsig_copied';
	const SENT   = '_sb_emailsig_sent';

	/** Set once the old copied status has been deleted. */
	const COPIES_GONE = 'sb_emailsig_copies_gone';

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
		add_action( 'admin_init', array( __CLASS__, 'forget_copies' ) );
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
			'{email}'      => array( 'The person\'s Email, else their company\'s', null ),
			'{link}'       => array( 'Their signature page, to copy it from', null ),
			'{sender}'     => array( 'Default Sender Name, else whoever sends it (their WordPress name)', self::sender_name() !== '' ? self::sender_name() : null ),
			'{company}'    => array( 'Their company\'s Company Name', trim( (string) get_field( 'sb_emailsig_company_name', $o ) ) ),
			'{website}'    => array( 'Their company\'s Website', trim( (string) get_field( 'sb_emailsig_website', $o ) ) ),
			'{site_name}'  => array( 'Site Title in WordPress Settings, General', self::site_name() ),
		);
	}

	/** Settings, Emails tab: every merge tag, click to copy (assets/settings.js). */
	public static function render_tags() {
		// With more than one company, Company and Website are each person's
		// company's, so no one company's value is shown here (Sam, 31 October 2026).
		$multi = count( SB_EmailSig_Company::all() ) > 1;
		$rows  = array();

		foreach ( self::tags() as $tag => $t ) {
			if ( $multi && in_array( $tag, array( '{company}', '{website}' ), true ) ) {
				$t[1] = null;
			}

			$empty  = $t[1] !== null && $t[1] === '';
			$rows[] = array( $tag, $t[0] . ( $t[1] !== null ? ( $empty ? ': not set yet' : ': ' . $t[1] ) : '' ), $empty );
		}

		SB_EmailSig_Admin::tag_list( $rows );
	}

	/** The old copied status, deleted once (Sam, 1 November 2026). */
	public static function forget_copies() {
		if ( get_option( self::COPIES_GONE ) ) {
			return;
		}

		delete_post_meta_by_key( self::COPIED );
		update_option( self::COPIES_GONE, 1, true );
	}

	/**
	 * A Copy button's data: copy.js posts it when the copy works, to log it.
	 * The person's page key proves they may. $tpl_id '' for their main
	 * template; $where 'admin' for the Signature box in the admin.
	 */
	public static function mark_attr( $post_id, $tpl_id = '', $where = '' ) {
		return ' data-sb-emailsig-mark="' . esc_attr( wp_json_encode( self::mark( $post_id, $tpl_id, $where ) ) ) . '"';
	}

	/** The data mark_attr() carries. */
	public static function mark( $post_id, $tpl_id = '', $where = '' ) {
		$out = array(
			'ajax' => admin_url( 'admin-ajax.php' ),
			'id'   => (int) $post_id,
			'key'  => SB_EmailSig_CPT::get_key( $post_id ),
			't'    => (string) $tpl_id !== '' ? (string) $tpl_id : SB_EmailSig_Templates::for_person( $post_id ),
		);

		if ( $where === 'admin' ) {
			$out['w'] = 'admin';
		}

		return $out;
	}

	/**
	 * A copy, logged (Sam, 1 November 2026): every click, with the
	 * template's name, HTML code when it was the code, and admin when from
	 * the admin's Signature box. By whoever is logged in, else the person
	 * (it's their page).
	 */
	public static function copied() {
		// phpcs:disable WordPress.Security.NonceVerification -- the person's page key stands in.
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$key  = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$t    = isset( $_POST['t'] ) ? sanitize_key( wp_unslash( $_POST['t'] ) ) : '';
		$w    = isset( $_POST['w'] ) ? sanitize_key( wp_unslash( $_POST['w'] ) ) : '';
		$code = ! empty( $_POST['c'] );
		// phpcs:enable

		if ( ! $id || get_post_type( $id ) !== SB_EMAILSIG_CPT || $key === '' || ! hash_equals( SB_EmailSig_CPT::get_key( $id ), $key ) ) {
			wp_send_json_error( null, 403 );
		}

		// Logged out: only from a page that shows (active, company on), and
		// no more than 20 in 10 minutes from one visitor for one person.
		if ( ! is_user_logged_in() ) {
			if ( get_post_status( $id ) !== 'publish' || SB_EmailSig_Switch::person_off( $id ) ) {
				wp_send_json_error( null, 403 );
			}

			$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$limit = 'sb_emailsig_copied_' . md5( $ip . '|' . $id . '|' . wp_salt( 'nonce' ) );
			$n     = (int) get_transient( $limit );

			if ( $n >= 20 ) {
				wp_send_json_success( 'enough' );
			}

			set_transient( $limit, $n + 1, 10 * MINUTE_IN_SECONDS );
		}

		$tpl    = $t !== '' ? SB_EmailSig_Templates::get( $t ) : null;
		$detail = array();

		if ( $tpl && ! empty( $tpl['name'] ) ) {
			$detail[] = $tpl['name'];
		}
		if ( $code ) {
			$detail[] = 'HTML code';
		}
		if ( $w === 'admin' && is_user_logged_in() ) {
			$detail[] = 'admin';
		}

		$who = is_user_logged_in() ? '' : trim( (string) get_field( 'sb_emailsig_name', $id ) );
		SB_EmailSig_Log::add( 'copied', 'person', $id, '', implode( ', ', $detail ), $who );

		wp_send_json_success( 'recorded' );
	}

	/**
	 * The Email link button, for everyone (Sam, 1 November 2026): without
	 * an Email the popup's To starts as the company's, or blank to fill in.
	 * $classes for the page's button style.
	 */
	public static function button( $post_id, $classes ) {
		return '<button type="button" class="' . esc_attr( $classes ) . '" data-sb-emailsig-send="' . (int) $post_id . '" data-sb-tip="' . esc_attr( self::send_title( $post_id ) ) . '">Email link</button>';
	}

	/** Where Email link sends to start with: their Email, else their company's, else ''. */
	public static function to( $post_id ) {
		$own = sanitize_email( (string) get_field( 'sb_emailsig_email', $post_id ) );

		if ( is_email( $own ) ) {
			return $own;
		}

		return SB_EmailSig_Company::email( SB_EmailSig_Company::of_person( (int) $post_id ) );
	}

	/** The Email link button's tip. */
	public static function send_title( $post_id ) {
		$to = self::to( $post_id );

		return $to !== '' ? 'Email ' . $to . ' their signature link' : 'Email their signature link';
	}

	/** Settings' Default Sender Name, or ''. */
	public static function sender_name() {
		return trim( (string) get_field( 'sb_emailsig_mail_sender', 'option' ) );
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
	 * when a link was sent to them before, else New; drafts
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

		$to = self::to( $id );

		// Sent before: Updated Signature first, else New (Sam, 1 November 2026).
		$set     = self::settings();
		$asked   = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$kind    = isset( self::KINDS[ $asked ] ) ? $asked : ( get_post_meta( $id, self::SENT, true ) ? 'updated' : 'new' );
		$name    = trim( (string) get_field( 'sb_emailsig_name', $id ) );
		$first   = trim( (string) get_field( 'sb_emailsig_first_name', $id ) );
		$sender  = wp_get_current_user();
		$company = trim( (string) get_field( 'sb_emailsig_company_name', SB_EmailSig_Company::source() ) );
		$swap    = array(
			'{first_name}' => $first !== '' ? $first : ( strtok( $name, ' ' ) ?: $name ),
			'{name}'       => $name,
			'{job_title}'  => SB_EmailSig_Data::field_line( $id, 'job_title' ),
			'{email}'      => $to,
			'{link}'       => SB_EmailSig_CPT::page_url( $id ),
			'{sender}'     => self::sender_name() !== '' ? self::sender_name() : ( $sender && $sender->exists() ? $sender->display_name : ( $company ?: get_bloginfo( 'name' ) ) ),
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
		$kind = $mail['kind'];

		// phpcs:disable WordPress.Security.NonceVerification -- checked in compose().
		$to      = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : $mail['to'];
		$subject = isset( $_POST['subject'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['subject'] ) ) ) : '';
		$message = isset( $_POST['message'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) ) : '';
		// phpcs:enable

		// To, as the popup has it (Sam, 1 November 2026).
		if ( ! is_email( $to ) ) {
			wp_send_json_error( 'Enter an email address to send it to.' );
		}

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
