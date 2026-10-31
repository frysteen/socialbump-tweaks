<?php
/**
 * Submit New Team Member (Sam, 31 October 2026; first named Request to be
 * added): a form on a company's team
 * page for visitors who can't manage it, in place of the quick Add person
 * popup (28 October 2026), which was only ever for administrators and
 * editors. Name, Qualifications, Job Title, Email, Mobile and a photo,
 * uploaded into the Media Library and cropped at the company's main
 * template's ratio and shape (assets/request.js). The person lands as a
 * draft (Inactive) in that company, marked requested (META, the Requested
 * chip) until they are made Active, and the notification goes out when it
 * is switched on in Settings, Admin Notifications (SB_EmailSig_AdminMail).
 *
 * Spam, with no captcha keys yet: a hidden field people leave empty, a
 * token signed for the company and fetched when the form opens (a cached
 * page can't hand out a stale one) that must be MIN_AGE seconds old, and a
 * limit per visitor and per site each hour.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Request {

	/** Image types a photo can be (the Photo field's). */
	const MIMES = array( 'image/jpeg', 'image/png', 'image/webp' );

	/** On a person who asked to be added: when (unix time). Gone once they're Active. */
	const META = 'sb_emailsig_requested';

	/** A token is good from this many seconds old (a person takes longer to fill it in)... */
	const MIN_AGE = 3;

	/** ...for this long. */
	const MAX_AGE = 7200;

	/** Requests an hour from one visitor, and from everyone. */
	const PER_VISITOR = 5;
	const PER_SITE    = 30;

	/** The biggest photo, in bytes (10MB). */
	const MAX_BYTES = 10485760;

	public static function init() {
		foreach ( array( 'sb_emailsig_request_token' => 'token', 'sb_emailsig_request' => 'save' ) as $action => $fn ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $fn ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $fn ) );
		}

		add_action( 'transition_post_status', array( __CLASS__, 'made_active' ), 10, 3 );
	}

	/** The form is on company $co's team page for those who can't manage it. */
	public static function offered( $co ) {
		return $co && SB_EmailSig_Company::valid( $co ) && ! SB_EmailSig_Admin::can_manage() && ! SB_EmailSig_Switch::is_off( $co );
	}

	/** The popup, its scripts and settings, for company $co's team page. */
	public static function dialog( $co ) {
		$co   = (int) $co;
		$look = SB_EmailSig_Company::with(
			$co,
			function () {
				return SB_EmailSig_Photo::look();
			}
		);
		?>
		<dialog class="add add--admin" id="sb-emailsig-request" aria-labelledby="sb-emailsig-request-title">
			<form method="dialog" class="add__form" novalidate enctype="multipart/form-data">
				<div class="add__head">
					<h2 id="sb-emailsig-request-title">Submit New Team Member for Review</h2>
					<button type="button" class="add__close" data-request-close aria-label="Close"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
				</div>

				<label class="add__field"><span>First Name <span aria-hidden="true">*</span></span><input type="text" name="first" required autocomplete="given-name"></label>
				<label class="add__field"><span>Last Name</span><input type="text" name="last" autocomplete="family-name"></label>
				<label class="add__field"><span>Qualifications</span><input type="text" name="quals" autocomplete="off"></label>
				<label class="add__field"><span>Job Title</span><input type="text" name="job" autocomplete="organization-title"></label>
				<label class="add__field"><span>Email <span aria-hidden="true">*</span></span><input type="email" name="email" required autocomplete="email" inputmode="email"></label>
				<label class="add__field"><span>Mobile</span><input type="text" name="mobile" autocomplete="tel" inputmode="tel"></label>

				<!-- Left empty by people; a bot that fills it in is turned away. -->
				<label class="add__trap" aria-hidden="true">Leave this empty<input type="text" name="sb_hp" tabindex="-1" autocomplete="off"></label>

				<div class="add__field add__field--full">
					<span class="add__label">Photo</span>
					<div class="add__photo-buttons">
						<button type="button" class="button" data-request-upload>Choose a photo</button>
						<button type="button" class="button" data-request-remove hidden>Remove</button>
						<input type="file" accept="image/jpeg,image/png,image/webp" data-request-file hidden>
					</div>

					<div class="add__crop<?php echo $look['shape'] === 'circle' ? ' is-circle' : ''; ?>" hidden>
						<div class="add__stage-wrap">
							<div class="add__stage"><img alt=""></div>
							<div class="add__preview"></div>
						</div>
					</div>
				</div>

				<p class="add__status" role="status" aria-live="polite"></p>

				<div class="add__actions">
					<button type="button" class="button" data-request-close>Cancel</button>
					<button type="submit" class="button button-primary" data-request-save>Submit for review</button>
				</div>
			</form>

			<div class="add__done" hidden>
				<div class="add__head">
					<h2>Submitted</h2>
					<button type="button" class="add__close" data-request-close aria-label="Close"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
				</div>
				<p>Thanks. Once it's reviewed, the signature will be set up and sent to them.</p>
				<div class="add__actions"><button type="button" class="button button-primary" data-request-close>Close</button></div>
			</div>
		</dialog>
		<link rel="stylesheet" href="<?php echo esc_url( SB_EMAILSIG_URL . 'assets/vendor/cropper/cropper.min.css?ver=1.6.2' ); ?>">
		<script src="<?php echo esc_url( SB_EMAILSIG_URL . 'assets/vendor/cropper/cropper.min.js?ver=1.6.2' ); ?>"></script>
		<script src="<?php echo esc_url( SB_EMAILSIG_URL . 'assets/crop-resize.js?ver=' . SB_EmailSig_Module::asset_version( 'assets/crop-resize.js' ) ); ?>"></script>
		<script>var sbEmailsigRequest = <?php echo wp_json_encode( array(
			'ajax'     => admin_url( 'admin-ajax.php' ),
			'co'       => $co,
			'maxBytes' => self::MAX_BYTES,
			'ratio'    => (float) $look['ratio'],
			'shape'    => (string) $look['shape'],
			'radius'   => $look['width'] ? (float) $look['radius'] / (float) $look['width'] : 0,
		) ); ?>;</script>
		<script src="<?php echo esc_url( SB_EMAILSIG_URL . 'assets/request.js?ver=' . SB_EmailSig_Module::asset_version( 'assets/request.js' ) ); ?>"></script>
		<?php
	}

	/** The signature on a token: company and time. */
	private static function sign( $co, $time ) {
		return hash_hmac( 'sha256', 'sb_emailsig_request|' . (int) $co . '|' . (int) $time, wp_salt( 'auth' ) );
	}

	/** A token for company $co's form, fetched when it opens. */
	public static function token() {
		$co = isset( $_POST['co'] ) ? absint( $_POST['co'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- hands out the form's own signed token.

		if ( ! self::offered( $co ) ) {
			wp_send_json_error( 'This form isn\'t available.', 403 );
		}

		$now = time();

		wp_send_json_success( $co . '.' . $now . '.' . self::sign( $co, $now ) );
	}

	/** True when $token is company $co's, MIN_AGE to MAX_AGE seconds old. */
	private static function token_ok( $token, $co ) {
		$parts = explode( '.', (string) $token );

		if ( count( $parts ) !== 3 || (int) $parts[0] !== (int) $co ) {
			return false;
		}

		$age = time() - (int) $parts[1];

		return $age >= self::MIN_AGE && $age <= self::MAX_AGE && hash_equals( self::sign( $co, (int) $parts[1] ), $parts[2] );
	}

	/** One more request this hour from this visitor and from everyone: false once either is at its limit. */
	private static function count_one() {
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$keys = array(
			'sb_emailsig_req_' . md5( $ip . wp_salt( 'nonce' ) ) => self::PER_VISITOR,
			'sb_emailsig_req_site'                                => self::PER_SITE,
		);

		foreach ( $keys as $key => $limit ) {
			if ( (int) get_transient( $key ) >= $limit ) {
				return false;
			}
		}

		foreach ( array_keys( $keys ) as $key ) {
			set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
		}

		return true;
	}

	/** The photo sent with a request, into the Media Library: its id, 0 for none, or an error. */
	private static function upload( $name ) {
		// phpcs:disable WordPress.Security.NonceVerification -- checked by save().
		if ( empty( $_FILES['photo'] ) || ! isset( $_FILES['photo']['error'] ) || (int) $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE ) {
			return 0;
		}

		$file = $_FILES['photo']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- checked below, then by media_handle_upload().
		// phpcs:enable

		if ( (int) $file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] <= 0 ) {
			return new WP_Error( 'upload', 'The photo didn\'t upload. Try again.' );
		}

		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return new WP_Error( 'upload', 'That photo is too big. Use one under 10MB.' );
		}

		$type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$size = @getimagesize( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a file that isn't an image just fails.

		if ( ! in_array( $type['type'], self::MIMES, true ) || ! $size || ! in_array( $size['mime'], self::MIMES, true ) ) {
			return new WP_Error( 'upload', 'Use a JPG, PNG or WebP photo.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$id = media_handle_upload( 'photo', 0, array( 'post_title' => 'Signature photo: ' . $name ) );

		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'upload', 'The photo didn\'t upload. Try again.' );
		}

		if ( ! in_array( get_post_mime_type( $id ), self::MIMES, true ) ) {
			wp_delete_attachment( $id, true );

			return new WP_Error( 'upload', 'Use a JPG, PNG or WebP photo.' );
		}

		return (int) $id;
	}

	/** A request: the person as a draft in the company, marked requested. */
	public static function save() {
		// phpcs:disable WordPress.Security.NonceVerification -- a public form: the signed token is the check.
		$get = function ( $key ) {
			return isset( $_POST[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) : '';
		};
		// phpcs:enable

		$co = absint( $get( 'co' ) );

		if ( ! self::offered( $co ) ) {
			wp_send_json_error( 'This form isn\'t available.', 403 );
		}

		// A bot filled in the hidden field: it's told it worked, and nothing is kept.
		if ( $get( 'sb_hp' ) !== '' ) {
			wp_send_json_success();
		}

		if ( ! self::token_ok( $get( 'token' ), $co ) ) {
			wp_send_json_error( 'The form timed out. Close it and try again.' );
		}

		// First and last name (Sam, 31 October 2026); a form from before sends one name.
		$first = $get( 'first' );
		$last  = $get( 'last' );
		if ( $first === '' && $last === '' ) {
			list( $first, $last ) = SB_EmailSig_Names::split( $get( 'name' ) );
		}
		$name   = SB_EmailSig_Names::join( $first, $last );
		$quals  = $get( 'quals' );
		$job    = $get( 'job' );
		$email  = sanitize_email( $get( 'email' ) );
		$mobile = $get( 'mobile' );

		if ( $first === '' ) {
			wp_send_json_error( 'Add their first name.' );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( 'Add a valid email address.' );
		}

		if ( ! self::count_one() ) {
			wp_send_json_error( 'Too many requests for now. Try again in an hour.' );
		}

		$photo = self::upload( $name );

		if ( is_wp_error( $photo ) ) {
			wp_send_json_error( $photo->get_error_message() );
		}

		// Logged as submitted, not added (SB_EmailSig_Log::transition()).
		SB_EmailSig_Log::$via = 'request';
		// No slot left (SB_EmailSig_Limits): said plainly, nothing made.
		if ( SB_EmailSig_Limits::full( 'people' ) ) {
			SB_EmailSig_Log::$via = '';

			if ( $photo ) {
				wp_delete_attachment( $photo, true );
			}

			wp_send_json_error( SB_EmailSig_Limits::message( 'people' ) );
		}

		$id                   = wp_insert_post(
			array(
				'post_type'   => SB_EMAILSIG_CPT,
				'post_status' => 'draft',
				'post_title'  => $name,
			),
			true
		);
		SB_EmailSig_Log::$via = '';

		if ( is_wp_error( $id ) ) {
			if ( $photo ) {
				wp_delete_attachment( $photo, true );
			}

			wp_send_json_error( 'Not sent. Try again.' );
		}

		update_post_meta( $id, SB_EmailSig_Company::PERSON_META, $co );
		update_post_meta( $id, self::META, time() );
		update_field( SB_EmailSig_Names::FIRST, $first, $id );
		update_field( SB_EmailSig_Names::LAST, $last, $id );
		update_field( 'field_sb_emailsig_name', $name, $id );
		update_field( 'field_sb_emailsig_qualifications', $quals, $id );
		update_field( 'field_sb_emailsig_job_title', $job, $id );
		update_field( 'field_sb_emailsig_email', $email, $id );
		update_field( 'field_sb_emailsig_mobile', $mobile, $id );

		if ( $photo ) {
			wp_update_post(
				array(
					'ID'          => $photo,
					'post_parent' => $id,
				)
			);
			update_field( 'field_sb_emailsig_photo', $photo, $id );

			// The crop, as fractions of the photo (as the edit screen keeps it).
			$c = array();
			foreach ( array( 'x', 'y', 'w', 'h' ) as $k ) {
				$c[ $k ] = $get( 'crop_' . $k );
			}
			if ( count( array_filter( $c, 'is_numeric' ) ) === 4 ) {
				SB_EmailSig_Photo::set_crop( $id, $photo, (float) $c['x'], (float) $c['y'], (float) $c['w'], (float) $c['h'] );
			}
		}

		SB_EmailSig_CPT::ensure_key( $id );

		if ( $photo ) {
			SB_EmailSig_Company::with(
				$co,
				function () use ( $id ) {
					SB_EmailSig_Photo::generate( $id );
				}
			);
		}

		self::notify( $id, $co );

		wp_send_json_success();
	}

	/** The email about it (Settings, Admin Notifications: SB_EmailSig_AdminMail). */
	private static function notify( $id, $co ) {
		$field = function ( $name ) use ( $id ) {
			return trim( (string) get_field( 'sb_emailsig_' . $name, $id ) );
		};

		SB_EmailSig_AdminMail::send(
			'request',
			array(
				'name'           => $field( 'name' ),
				'company'        => SB_EmailSig_Company::name( $co ),
				'qualifications' => $field( 'qualifications' ),
				'job_title'      => $field( 'job_title' ),
				'email'          => $field( 'email' ),
				'mobile'         => $field( 'mobile' ),
				'photo'          => get_field( 'sb_emailsig_photo', $id, false ) ? 'Yes' : 'No',
				'edit_link'      => SB_EmailSig_Person::url( $id ),
			)
		);
	}

	/** Made Active: no longer a request. */
	public static function made_active( $new, $old, $post ) {
		if ( $new === 'publish' && $post instanceof WP_Post && $post->post_type === SB_EMAILSIG_CPT ) {
			delete_post_meta( $post->ID, self::META );
		}
	}

	/** When a person asked to be added and is still a draft: unix time, else 0. */
	public static function requested( $id ) {
		return get_post_status( $id ) === 'draft' ? (int) get_post_meta( $id, self::META, true ) : 0;
	}
}
