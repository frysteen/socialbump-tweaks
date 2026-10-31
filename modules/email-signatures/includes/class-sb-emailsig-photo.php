<?php
/**
 * A person's photo: the crop box on the edit screen (with the person's own
 * aspect ratio, if they need one), and the images made from it.
 *
 * The crop is kept as fractions of the original photo, which is never
 * changed. Each template says how photos look (Edit styles, Photo: shown or
 * not, shape, aspect ratio, width), and the image for a template is made from
 * the crop on demand, the shape baked in because Outlook ignores
 * border-radius. A crop made for another aspect ratio is fitted around its
 * centre (where the face is) and flagged for checking.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Photo {

	const SCALE     = 2;   // Retina multiplier for the made image.
	const VCARD_PX  = 240; // Square JPG embedded in the vCard.
	const UPLOAD    = 'sb-email-signatures';
	const QUERY     = 'sbphoto'; // The photo's fixed address in signatures: maybe_serve().
	const IMAGE_REV = '2'; // Bump to remake every photo after changing how they are made.

	/** Made images: [ template id or '_preview' => [ file, hash, width, height, baked ] ]. */
	const MAP_META = '_sb_emailsig_photos';

	/** The person's own aspect ratio: '' (the template's), '1:1', '4:3', '3:4' or 'w:h'. */
	const RATIO_META = '_sb_emailsig_ratio';

	/**
	 * The person's own shape and corner radius (px at the template's width),
	 * kept only while they have their own ratio: Default means the
	 * template's look throughout.
	 */
	const SHAPE_META  = '_sb_emailsig_shape';
	const RADIUS_META = '_sb_emailsig_radius';

	/**
	 * The person's own photo border (Sam, 30 October 2026): none (the
	 * template's), [ mode => none ], or [ mode => own, w, c, g ] (width,
	 * colour, gap, px at the template's width).
	 */
	const BORDER_META = '_sb_emailsig_border';

	/** The person whose unsaved photo, crop and look the person page's preview is showing (live()), or 0. */
	private static $live = 0;

	/** Their unsaved meta for that preview: meta key => value. */
	private static $live_meta = array();

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 0 );
		add_action( 'add_meta_boxes_' . SB_EMAILSIG_CPT, array( __CLASS__, 'add_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'acf/save_post', array( __CLASS__, 'on_save' ), 20 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Shapes and aspect ratios
	 * ------------------------------------------------------------------ */

	public static function shapes() {
		return array(
			'circle'  => 'Circle',
			'rounded' => 'Rounded corners',
			'square'  => 'Square corners',
		);
	}

	public static function ratios() {
		return array(
			'1:1'    => 'Square (1:1)',
			'4:3'    => 'Landscape (4:3)',
			'3:4'    => 'Portrait (3:4)',
			'custom' => 'Custom',
		);
	}

	/** Width over height for a ratio key ('custom' takes $w and $h), 1 if it makes no sense. */
	public static function ratio_value( $key, $w = 0, $h = 0 ) {
		$key = (string) $key;

		if ( $key === 'custom' ) {
			$key = (int) $w . ':' . (int) $h;
		}

		if ( preg_match( '/^(\d{1,3}):(\d{1,3})$/', $key, $m ) && (int) $m[1] > 0 && (int) $m[2] > 0 ) {
			return (int) $m[1] / (int) $m[2];
		}

		return 1.0;
	}

	/** The person's own ratio as saved: '', a key, or 'w:h' for custom. */
	public static function person_ratio( $post_id ) {
		$r = (string) get_post_meta( $post_id, self::RATIO_META, true );

		return preg_match( '/^\d{1,3}:\d{1,3}$/', $r ) ? $r : '';
	}

	/**
	 * The look a person's photo is made with: the template's ($p, resolved
	 * styles' photo), with their own ratio, shape and radius over it when
	 * they have their own ratio.
	 */
	public static function person_look( $post_id, array $p ) {
		$p['ratio'] = self::ratio_for( $post_id, $p['ratio'] ?? 1 );

		if ( self::person_ratio( $post_id ) !== '' ) {
			$shape = (string) get_post_meta( $post_id, self::SHAPE_META, true );
			if ( isset( self::shapes()[ $shape ] ) ) {
				$p['shape'] = $shape;
			}
			$radius = get_post_meta( $post_id, self::RADIUS_META, true );
			if ( $radius !== '' ) {
				$p['radius'] = max( 0, min( 200, (int) $radius ) );
			}
		}

		// Their own border, or none, over the template's.
		$b = self::person_border( $post_id );

		if ( $b && $b['mode'] === 'none' ) {
			$p['border'] = 0;
			$p['gap']    = 0;
		} elseif ( $b && $b['mode'] === 'own' ) {
			$p['border'] = $b['w'];
			$p['gap']    = $b['g'];
			$colour      = $b['c'] !== '' ? SB_EmailSig_Styles::hex( $b['c'] ) : '';
			if ( $colour !== '' ) {
				$p['border_colour'] = $colour;
			}
		}

		return $p;
	}

	/** The person's own border as saved (see BORDER_META), or null for the template's. */
	public static function person_border( $post_id ) {
		return self::clean_border( get_post_meta( $post_id, self::BORDER_META, true ) );
	}

	/** A border setting made safe: null (the template's), none, or own with w, c and g. */
	private static function clean_border( $b ) {
		if ( ! is_array( $b ) || ! in_array( $b['mode'] ?? '', array( 'none', 'own' ), true ) ) {
			return null;
		}

		if ( $b['mode'] === 'none' ) {
			return array( 'mode' => 'none' );
		}

		return array(
			'mode' => 'own',
			'w'    => max( 1, min( 10, (int) ( $b['w'] ?? 2 ) ) ),
			// A hex, a brand colour or a theme variable ('' the template's).
			'c'    => SB_EmailSig_Styles::clean_colour( (string) ( $b['c'] ?? '' ) ),
			'g'    => max( 0, min( 10, (int) ( $b['g'] ?? 0 ) ) ),
		);
	}

	/** The crop box's Border fields as a setting (posted names sb_emailsig_border, _w, _c, _g). */
	private static function posted_border( array $posted ) {
		return self::clean_border(
			array(
				'mode' => sanitize_key( (string) ( $posted['sb_emailsig_border'] ?? '' ) ),
				'w'    => absint( $posted['sb_emailsig_border_w'] ?? 2 ),
				'c'    => sanitize_text_field( (string) ( $posted['sb_emailsig_border_c'] ?? '' ) ),
				'g'    => absint( $posted['sb_emailsig_border_g'] ?? 0 ),
			)
		);
	}

	/** The ratio a person's photo is made at in a template: their own, else the template's. */
	public static function ratio_for( $post_id, $template_ratio ) {
		$own = self::person_ratio( $post_id );

		return $own !== '' ? self::ratio_value( $own ) : (float) $template_ratio;
	}

	/** How photos look for a template that doesn't say: Classic's old round 130px photo. */
	public static function default_look() {
		return array(
			'show'   => true,
			'shape'  => 'circle',
			'ratio'  => 1.0,
			'width'  => 130,
			'radius' => 0,
			'border'        => 0,
			'border_colour' => '',
			'gap'           => 0,
		);
	}

	/** A template and its resolved styles ('' for the company's main). */
	private static function styles_for( $tpl_id, $post_id = 0 ) {
		// In the person's company, so its brand colours fill the template's
		// (a Default border colour, say). Outside it they were the default
		// company's: Gary's ring came out teal on his page and in pasted
		// signatures (Sam, 31 October 2026).
		if ( $post_id && class_exists( 'SB_EmailSig_Company' ) ) {
			return SB_EmailSig_Company::with(
				SB_EmailSig_Company::of_person( (int) $post_id ),
				function () use ( $tpl_id ) {
					return self::styles_for( $tpl_id );
				}
			);
		}

		$tpl = SB_EmailSig_Templates::get( $tpl_id !== '' ? $tpl_id : SB_EmailSig_Templates::current() );

		return $tpl ? array( $tpl, SB_EmailSig_Styles::resolve( $tpl ) ) : array( null, null );
	}

	/* ------------------------------------------------------------------ *
	 * Edit screen
	 * ------------------------------------------------------------------ */

	public static function add_box() {
		add_meta_box( 'sb-emailsig-crop', 'Photo Crop', array( __CLASS__, 'render_box' ), SB_EMAILSIG_CPT, 'normal', 'high' );
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		$post   = $screen && SB_EMAILSIG_CPT === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		if ( ! $post && ! SB_EmailSig_Person::is_screen() ) {
			return;
		}
		wp_enqueue_style( 'sb-emailsig-cropper', SB_EMAILSIG_URL . 'assets/vendor/cropper/cropper.min.css', array(), '1.6.2' );
		wp_enqueue_script( 'sb-emailsig-cropper', SB_EMAILSIG_URL . 'assets/vendor/cropper/cropper.min.js', array(), '1.6.2', true );
		wp_enqueue_style( 'sb-emailsig-admin', SB_EMAILSIG_URL . 'assets/admin.css', array(), SB_EmailSig_Module::asset_version( 'assets/admin.css' ) );
		wp_enqueue_script( 'sb-emailsig-crop-resize', SB_EMAILSIG_URL . 'assets/crop-resize.js', array( 'sb-emailsig-cropper' ), SB_EmailSig_Module::asset_version( 'assets/crop-resize.js' ), true );
		wp_enqueue_script( 'sb-emailsig-crop', SB_EMAILSIG_URL . 'assets/crop.js', array( 'jquery', 'sb-emailsig-cropper', 'sb-emailsig-crop-resize' ), SB_EmailSig_Module::asset_version( 'assets/crop.js' ), true );

		// The border's colour field: the brand and theme colours menu, in the person's company's colours.
		wp_enqueue_script( 'sb-emailsig-colour', SB_EMAILSIG_URL . 'assets/colour.js', array(), SB_EmailSig_Module::asset_version( 'assets/colour.js' ), true );
		$person = SB_EmailSig_Person::is_screen() ? (int) SB_EmailSig_Person::current() : (int) get_the_ID();
		wp_localize_script(
			'sb-emailsig-colour',
			'sbEmailsigColours',
			SB_EmailSig_Company::with(
				SB_EmailSig_Company::of_person( $person ),
				function () {
					return array(
						'palette' => SB_EmailSig_Styles::editor_palette(),
						'colours' => (object) SB_EmailSig_Styles::colours(),
						'brand'   => (object) wp_list_pluck( SB_EmailSig_Styles::brand(), 'hex' ),
					);
				}
			)
		);
	}

	public static function render_box( $post ) {
		wp_nonce_field( 'sb_emailsig_crop', 'sb_emailsig_crop_nonce' );

		$att_id = (int) get_post_meta( $post->ID, 'sb_emailsig_photo', true );
		$src    = $att_id ? wp_get_attachment_image_url( $att_id, 'full' ) : '';
		$crop   = get_post_meta( $post->ID, '_sb_emailsig_crop', true );

		list( , $s ) = self::styles_for( SB_EmailSig_Templates::for_person( $post->ID ), $post->ID );
		$photo       = ( $s && ! empty( $s['photo'] ) ) ? $s['photo'] : self::default_look();
		$own         = self::person_ratio( $post->ID );
		$key         = isset( self::ratios()[ $own ] ) ? $own : ( $own !== '' ? 'custom' : '' );
		list( $cw, $ch ) = $key === 'custom' ? array_map( 'intval', explode( ':', $own ) ) : array( 4, 3 );
		$mine        = self::person_look( $post->ID, $photo );
		?>
		<div class="sb-emailsig-crop" data-src="<?php echo esc_url( (string) $src ); ?>" data-attachment="<?php echo (int) $att_id; ?>"
			data-template-ratio="<?php echo esc_attr( (string) $photo['ratio'] ); ?>" data-shape="<?php echo esc_attr( $photo['shape'] ); ?>"
			data-radius="<?php echo esc_attr( (string) ( $photo['width'] ? $photo['radius'] / $photo['width'] : 0 ) ); ?>" data-width="<?php echo (int) $photo['width']; ?>">
			<p class="sb-emailsig-crop__ratio">
				<label for="sb-emailsig-ratio">Aspect ratio</label>
				<select id="sb-emailsig-ratio" name="sb_emailsig_ratio">
					<option value="">Default (the template's)</option>
					<?php foreach ( self::ratios() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $key, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="sb-emailsig-crop__custom"<?php echo $key === 'custom' ? '' : ' hidden'; ?>>
					<input type="number" name="sb_emailsig_ratio_w" value="<?php echo (int) $cw; ?>" min="1" max="99" step="1" inputmode="numeric" aria-label="Width">
					<span>:</span>
					<input type="number" name="sb_emailsig_ratio_h" value="<?php echo (int) $ch; ?>" min="1" max="99" step="1" inputmode="numeric" aria-label="Height">
				</span>
				<span class="sb-emailsig-crop__own"<?php echo $key === '' ? ' hidden' : ''; ?>>
					<label for="sb-emailsig-shape">Shape</label>
					<select id="sb-emailsig-shape" name="sb_emailsig_shape">
						<?php foreach ( self::shapes() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $mine['shape'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="sb-emailsig-crop__radius"<?php echo $mine['shape'] === 'rounded' ? '' : ' hidden'; ?>>
						<label for="sb-emailsig-radius">Corner radius</label>
						<input type="number" id="sb-emailsig-radius" name="sb_emailsig_radius" value="<?php echo (int) $mine['radius']; ?>" min="0" max="200" step="1" inputmode="numeric"> px
					</span>
				</span>
			</p>
			<?php
			// Their own border (Sam, 30 October 2026): the template's, none, or their own.
			$border = self::person_border( $post->ID );
			$bmode  = $border ? $border['mode'] : '';
			$bown   = $border && $bmode === 'own' ? $border : array( 'w' => max( 1, (int) ( $photo['border'] ?? 2 ) ?: 2 ), 'c' => '', 'g' => (int) ( $photo['gap'] ?? 0 ) );
			$bfall  = SB_EmailSig_Styles::hex( (string) ( $photo['border_colour'] ?? '' ) ) ?: '#333333';
			?>
			<p class="sb-emailsig-crop__border">
				<label for="sb-emailsig-border">Border</label>
				<select id="sb-emailsig-border" name="sb_emailsig_border">
					<option value=""<?php selected( $bmode, '' ); ?>><?php echo esc_html( 'Default (the template\'s' . ( ! empty( $photo['border'] ) ? ', ' . (int) $photo['border'] . 'px' : ', none' ) . ')' ); ?></option>
					<option value="none"<?php selected( $bmode, 'none' ); ?>>None</option>
					<option value="own"<?php selected( $bmode, 'own' ); ?>>Their own</option>
				</select>
				<span class="sb-emailsig-crop__border-own"<?php echo $bmode === 'own' ? '' : ' hidden'; ?>>
					<span class="sb-emailsig-crop__pair">
						<label for="sb-emailsig-border-w">Width</label>
						<input type="number" id="sb-emailsig-border-w" name="sb_emailsig_border_w" value="<?php echo (int) $bown['w']; ?>" min="1" max="10" step="1" inputmode="numeric"> px
					</span>
					<span class="sb-emailsig-crop__pair">
						<label for="sb-emailsig-border-c">Colour</label>
						<?php // The colour field with the brand and theme colours menu (assets/colour.js); blank is the template's. ?>
						<span class="sb-tweaks-colour sb-emailsig-colour" data-sb-colour data-fallback-hex="<?php echo esc_attr( $bfall ); ?>">
							<input type="color" class="sb-tweaks-colour__swatch" value="<?php echo esc_attr( $bfall ); ?>" aria-label="Border colour">
							<input type="text" class="sb-tweaks-colour__value code" id="sb-emailsig-border-c" name="sb_emailsig_border_c" value="<?php echo esc_attr( $bown['c'] ); ?>" placeholder="Template's" spellcheck="false" autocomplete="off">
							<button type="button" class="sb-emailsig-colour__theme" aria-label="Theme colours" title="Theme colours" hidden><span class="dashicons dashicons-art" aria-hidden="true"></span></button>
						</span>
					</span>
					<span class="sb-emailsig-crop__pair">
						<label for="sb-emailsig-border-g">Gap</label>
						<input type="number" id="sb-emailsig-border-g" name="sb_emailsig_border_g" value="<?php echo (int) $bown['g']; ?>" min="0" max="10" step="1" inputmode="numeric"> px
					</span>
				</span>
			</p>
			<?php // Reset to Default (Sam, 31 October 2026): back to the template's ratio, shape and border; shown while any is their own (assets/crop.js). ?>
			<p class="sb-emailsig-crop__defaults"<?php echo ( $key === '' && $bmode === '' ) ? ' hidden' : ''; ?>><button type="button" class="button button-small sb-emailsig-crop__defaults-btn">Reset to Default</button></p>
			<p class="sb-emailsig-crop__check notice-warning" hidden>This crop was set for a different shape. It has been fitted around its middle: check it, then save.</p>
			<p class="sb-emailsig-crop__empty"<?php echo $src ? ' hidden' : ''; ?>>Choose a photo above, then drag the box to set the crop.</p>
			<div class="sb-emailsig-crop__wrap"<?php echo $src ? '' : ' hidden'; ?>>
				<div class="sb-emailsig-crop__stage"><img alt="" /></div>
				<div class="sb-emailsig-crop__side">
					<div class="sb-emailsig-crop__preview"></div>
					<p class="description">Drag the box to move it, drag a corner or side to resize. Hold Option (Alt) to resize from the middle. Saved with the rest.</p>
					<button type="button" class="button sb-emailsig-crop__reset">Reset crop</button>
				</div>
			</div>
			<input type="hidden" name="sb_emailsig_crop" data-sb-watch value="<?php echo esc_attr( is_string( $crop ) ? $crop : '' ); ?>" />
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Save
	 * ------------------------------------------------------------------ */

	public static function on_save( $post_id ) {
		if ( ! is_numeric( $post_id ) || SB_EMAILSIG_CPT !== get_post_type( $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Keep the post title in step with the Name field.
		// Formatted, so a {title} from their staff page is filled in.
		$name = trim( (string) get_field( 'sb_emailsig_name', $post_id ) );
		if ( '' !== $name && get_the_title( $post_id ) !== $name ) {
			remove_action( 'acf/save_post', array( __CLASS__, 'on_save' ), 20 );
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $name,
					'post_name'  => sanitize_title( $name ),
				)
			);
			add_action( 'acf/save_post', array( __CLASS__, 'on_save' ), 20 );
		}

		if ( isset( $_POST['sb_emailsig_crop_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sb_emailsig_crop_nonce'] ) ), 'sb_emailsig_crop' ) ) {
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput
			self::save_crop(
				$post_id,
				isset( $_POST['sb_emailsig_crop'] ) ? wp_unslash( $_POST['sb_emailsig_crop'] ) : '',
				isset( $_POST['sb_emailsig_ratio'] ) ? sanitize_text_field( wp_unslash( $_POST['sb_emailsig_ratio'] ) ) : '',
				absint( $_POST['sb_emailsig_ratio_w'] ?? 1 ),
				absint( $_POST['sb_emailsig_ratio_h'] ?? 1 ),
				isset( $_POST['sb_emailsig_shape'] ) ? sanitize_key( wp_unslash( $_POST['sb_emailsig_shape'] ) ) : '',
				isset( $_POST['sb_emailsig_radius'] ) ? absint( $_POST['sb_emailsig_radius'] ) : 0
			);

			if ( isset( $_POST['sb_emailsig_border'] ) ) {
				$border = self::posted_border( wp_unslash( $_POST ) );
				if ( $border ) {
					update_post_meta( $post_id, self::BORDER_META, $border );
				} else {
					delete_post_meta( $post_id, self::BORDER_META );
				}
			}
			// phpcs:enable
		}

		self::generate( $post_id );
	}

	/**
	 * Keeps a crop (JSON: id, x, y, w, h as fractions) and the person's own
	 * ratio ('' for the template's, a key of ::ratios(), or 'custom' with $w
	 * and $h), with their own shape and corner radius while the ratio is
	 * their own. The edit screen and the team page's Add person use it.
	 */
	public static function save_crop( $post_id, $crop_json, $ratio, $w = 1, $h = 1, $shape = '', $radius = 0 ) {
		$crop = self::clean_crop( (string) $crop_json );
		if ( $crop ) {
			update_post_meta( $post_id, '_sb_emailsig_crop', wp_slash( wp_json_encode( $crop ) ) );
		}

		$ratio = (string) $ratio;
		if ( $ratio === 'custom' ) {
			$ratio = max( 1, min( 99, (int) $w ) ) . ':' . max( 1, min( 99, (int) $h ) );
		}
		if ( $ratio !== '' && preg_match( '/^\d{1,2}:\d{1,2}$/', $ratio ) ) {
			update_post_meta( $post_id, self::RATIO_META, $ratio );

			if ( isset( self::shapes()[ $shape ] ) ) {
				update_post_meta( $post_id, self::SHAPE_META, $shape );
				update_post_meta( $post_id, self::RADIUS_META, max( 0, min( 200, (int) $radius ) ) );
			}
		} else {
			delete_post_meta( $post_id, self::RATIO_META );
			delete_post_meta( $post_id, self::SHAPE_META );
			delete_post_meta( $post_id, self::RADIUS_META );
		}
	}

	/**
	 * The person page's preview (Sam, 30 October 2026): reads the crop box's
	 * unsaved crop, ratio, shape and radius ($posted, named as the box names
	 * them) in place of the saved ones until unlive(). Photos made meanwhile
	 * go in the preview slot, so the saved ones stay as they are. Nothing is
	 * saved.
	 */
	public static function live( $post_id, array $posted ) {
		$meta = array();

		if ( isset( $posted['sb_emailsig_crop'] ) ) {
			$crop = self::clean_crop( (string) $posted['sb_emailsig_crop'] );
			if ( $crop ) {
				$meta['_sb_emailsig_crop'] = wp_json_encode( $crop );
			}
		}

		if ( isset( $posted['sb_emailsig_ratio'] ) ) {
			$ratio = sanitize_text_field( (string) $posted['sb_emailsig_ratio'] );
			if ( $ratio === 'custom' ) {
				$ratio = max( 1, min( 99, absint( $posted['sb_emailsig_ratio_w'] ?? 1 ) ) ) . ':' . max( 1, min( 99, absint( $posted['sb_emailsig_ratio_h'] ?? 1 ) ) );
			}
			$own                       = $ratio !== '' && preg_match( '/^\d{1,2}:\d{1,2}$/', $ratio );
			$meta[ self::RATIO_META ]  = $own ? $ratio : '';
			$shape                     = sanitize_key( (string) ( $posted['sb_emailsig_shape'] ?? '' ) );
			$meta[ self::SHAPE_META ]  = $own && isset( self::shapes()[ $shape ] ) ? $shape : '';
			$meta[ self::RADIUS_META ] = $own && isset( $posted['sb_emailsig_radius'] ) ? (string) max( 0, min( 200, absint( $posted['sb_emailsig_radius'] ) ) ) : '';
		}

		if ( isset( $posted['sb_emailsig_border'] ) ) {
			$meta[ self::BORDER_META ] = self::posted_border( $posted ) ?: '';
		}

		self::$live      = (int) $post_id;
		self::$live_meta = $meta;
		add_filter( 'get_post_metadata', array( __CLASS__, 'live_meta' ), 10, 4 );
	}

	public static function unlive() {
		remove_filter( 'get_post_metadata', array( __CLASS__, 'live_meta' ), 10 );
		self::$live      = 0;
		self::$live_meta = array();
	}

	/** get_post_metadata while live(): the unsaved value for that person's photo meta. */
	public static function live_meta( $value, $object_id, $meta_key, $single ) {
		if ( (int) $object_id !== self::$live || ! array_key_exists( (string) $meta_key, self::$live_meta ) ) {
			return $value;
		}

		return $single ? array( self::$live_meta[ $meta_key ] ) : array( self::$live_meta[ $meta_key ] );
	}

	/** The company's main template's photo look (shape, ratio, width, radius), for crop boxes. */
	public static function look() {
		list( , $s ) = self::styles_for( '' );

		return ( $s && ! empty( $s['photo'] ) ) ? $s['photo'] : self::default_look();
	}

	/**
	 * Crop is stored as fractions of the image (0 to 1) plus the attachment
	 * it belongs to, so it survives WordPress serving a scaled copy.
	 */
	private static function clean_crop( $raw ) {
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$out = array( 'id' => isset( $data['id'] ) ? (int) $data['id'] : 0 );
		foreach ( array( 'x', 'y', 'w', 'h' ) as $k ) {
			if ( ! isset( $data[ $k ] ) || ! is_numeric( $data[ $k ] ) ) {
				return null;
			}
			$out[ $k ] = max( 0, min( 1, (float) $data[ $k ] ) );
		}
		return $out;
	}

	/** A crop from a people import: fractions of the photo, for that attachment. */
	public static function set_crop( $post_id, $att_id, $x, $y, $w, $h ) {
		$crop = self::clean_crop( wp_json_encode( array( 'id' => (int) $att_id, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h ) ) );

		if ( $crop ) {
			update_post_meta( $post_id, '_sb_emailsig_crop', wp_slash( wp_json_encode( $crop ) ) );
		}
	}

	private static function get_crop( $post_id, $att_id ) {
		$crop = json_decode( (string) get_post_meta( $post_id, '_sb_emailsig_crop', true ), true );
		if ( is_array( $crop ) && isset( $crop['id'] ) && (int) $crop['id'] === (int) $att_id ) {
			return $crop;
		}
		return null;
	}

	/** The photo's attachment id and file, or [ 0, '' ]. */
	private static function source( $post_id ) {
		$att_id = (int) get_field( 'sb_emailsig_photo', $post_id, false );
		$file   = $att_id ? get_attached_file( $att_id ) : '';

		return ( $file && file_exists( $file ) ) ? array( $att_id, $file ) : array( 0, '' );
	}

	/**
	 * True when the person's crop was set for a different shape than their
	 * main template now makes, so it has been fitted and wants a look.
	 */
	public static function needs_check( $post_id ) {
		list( $att_id ) = self::source( $post_id );
		$crop           = $att_id ? self::get_crop( $post_id, $att_id ) : null;
		list( , $s )    = self::styles_for( SB_EmailSig_Templates::for_person( $post_id ), $post_id );

		if ( ! $crop || ! $s || empty( $s['photo'] ) ) {
			return false;
		}

		$meta = wp_get_attachment_metadata( $att_id );
		$w    = (int) ( $meta['width'] ?? 0 );
		$h    = (int) ( $meta['height'] ?? 0 );

		if ( ! $w || ! $h || $crop['h'] <= 0 ) {
			return false;
		}

		$have = ( $crop['w'] * $w ) / ( $crop['h'] * $h );
		$want = self::ratio_for( $post_id, $s['photo']['ratio'] );

		return abs( $have - $want ) / $want > 0.02;
	}

	/* ------------------------------------------------------------------ *
	 * Images
	 * ------------------------------------------------------------------ */

	private static function dir() {
		$up = wp_upload_dir();
		return array(
			'path' => trailingslashit( $up['basedir'] ) . self::UPLOAD,
			'url'  => trailingslashit( $up['baseurl'] ) . self::UPLOAD,
		);
	}

	/**
	 * The person's photo for a template with resolved styles $s:
	 * [ url, width, height, baked ] at display size, or null (no photo, or
	 * the template doesn't show photos). Made on first use and kept until the
	 * crop, photo or template's photo settings change. $preview: unsaved
	 * styles from the Edit styles page, kept apart so they never replace the
	 * template's own image.
	 */
	public static function image( $post_id, array $tpl, array $s, $preview = false ) {
		$p       = $s['photo'] ?? null;
		$preview = $preview || ( self::$live && self::$live === (int) $post_id );

		if ( ! $p || empty( $p['show'] ) ) {
			return null;
		}

		list( $att_id, $file ) = self::source( $post_id );

		if ( ! $att_id ) {
			return null;
		}

		$p     = self::person_look( $post_id, $p );
		$ratio = (float) $p['ratio'];
		$width = max( 20, (int) $p['width'] );
		$spec  = array(
			'w'      => $width,
			'h'      => max( 1, (int) round( $width / $ratio ) ),
			'shape'  => $p['shape'],
			'radius' => $p['shape'] === 'rounded' ? (int) $p['radius'] : 0,
		);

		// A border drawn in (only named when there is one, so photos
		// without stay as they were made).
		if ( ! empty( $p['border'] ) ) {
			$spec['border'] = (int) $p['border'];
			$spec['colour'] = (string) ( $p['border_colour'] ?? '' );
			$spec['gap']    = (int) ( $p['gap'] ?? 0 );
		}

		$crop  = self::get_crop( $post_id, $att_id );
		$hash  = substr( md5( $att_id . '|' . wp_json_encode( $crop ) . '|' . filemtime( $file ) . '|' . wp_json_encode( $spec ) . '|' . self::IMAGE_REV ), 0, 10 );
		$dir   = self::dir();
		$map   = self::map( $post_id );
		$key   = $preview ? '_preview' : (string) $tpl['id'];

		// Already made, for this template or another with the same look.
		// Keys compared as strings: PHP keeps a template id like '554' as
		// the number 554, which never matched, so the photo was "moved" on to
		// itself and its file deleted (Sam, 30 October 2026: a new person's
		// photo missing until it was cropped again).
		foreach ( $map as $k => $entry ) {
			if ( ( $entry['hash'] ?? '' ) === $hash && file_exists( $dir['path'] . '/' . $entry['file'] ) ) {
				if ( (string) $k !== $key && ! $preview ) {
					self::forget( $post_id, $map, $key, $entry['file'] );
					$map[ $key ] = $entry;
					update_post_meta( $post_id, self::MAP_META, $map );
				}

				return self::entry_out( $entry, $dir );
			}
		}

		wp_mkdir_p( $dir['path'] );
		$name = $post_id . '-' . $hash . '.png';
		$baked = self::build( $file, $crop, $ratio, $spec, $dir['path'] . '/' . $name );

		if ( $baked === null ) {
			return null;
		}

		self::delete_legacy( $post_id );
		self::forget( $post_id, $map, $key, $name );
		$map[ $key ] = array(
			'file'   => $name,
			'hash'   => $hash,
			'width'  => $spec['w'],
			'height' => $spec['h'],
			'baked'  => $baked,
			'shape'  => $spec['shape'],
			'radius' => $spec['radius'],
		);
		update_post_meta( $post_id, self::MAP_META, $map );

		return self::entry_out( $map[ $key ], $dir );
	}

	/* ------------------------------------------------------------------ *
	 * The fixed address
	 * ------------------------------------------------------------------ */

	/**
	 * A person's photo address in signatures, the same for good:
	 * /?sbphoto={id}-{check} in their main template, and
	 * /?sbphoto={id}-{template id}-{check} in any other.
	 */
	public static function fixed_url( $post_id, $tpl_id = '' ) {
		$post_id = (int) $post_id;
		$tpl_id  = (string) $tpl_id;

		if ( $tpl_id === '' || $tpl_id === SB_EmailSig_Templates::for_person( $post_id ) ) {
			return add_query_arg( self::QUERY, $post_id . '-' . SB_EmailSig_Tracking::sign( $post_id . '|photo' ), home_url( '/' ) );
		}

		return add_query_arg( self::QUERY, $post_id . '-' . $tpl_id . '-' . SB_EmailSig_Tracking::sign( $post_id . '|photo|' . $tpl_id ), home_url( '/' ) );
	}

	/**
	 * /?sbphoto=: the person's photo as that template makes it now (their
	 * main template's when the address names none, or the template has
	 * gone), made if need be, so a new photo, crop, ratio or shape shows in
	 * every signature already pasted. With no photo, a see-through PNG one
	 * pixel high. Browsers check back each time (no-cache, with an ETag);
	 * mail apps and Gmail's image proxy keep their own copies for a while.
	 */
	public static function maybe_serve() {
		if ( ! isset( $_GET[ self::QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'X-LiteSpeed-Cache-Control: no-cache', true );
		header( 'Cache-Control: no-cache', true );

		$code = sanitize_text_field( wp_unslash( $_GET[ self::QUERY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$id   = preg_match( '/^(\d+)(?:-(\d+))?-([a-f0-9]{8})$/', $code, $m ) ? (int) $m[1] : 0;
		$t    = $id ? (string) $m[2] : '';

		// Not found too while their company is switched off (SB_EmailSig_Switch).
		if ( ! $id || ! hash_equals( SB_EmailSig_Tracking::sign( $id . '|photo' . ( $t !== '' ? '|' . $t : '' ) ), $m[3] ) || get_post_type( $id ) !== SB_EMAILSIG_CPT || SB_EmailSig_Switch::person_off( $id ) ) {
			status_header( 404 );
			exit;
		}

		$t               = ( $t !== '' && SB_EmailSig_Templates::get( $t ) ) ? $t : SB_EmailSig_Templates::for_person( $id );
		list( $tpl, $s ) = self::styles_for( $t, $id );
		$img             = $tpl ? self::image( $id, $tpl, $s ) : null;
		$dir             = self::dir();
		$path            = $img ? $dir['path'] . '/' . basename( $img['url'] ) : '';

		if ( $path !== '' && is_readable( $path ) ) {
			$etag = '"' . md5( basename( $path ) ) . '"';
			header( 'ETag: ' . $etag );

			if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && trim( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) === $etag ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				status_header( 304 );
				exit;
			}

			header( 'Content-Type: image/png' );
			header( 'Content-Length: ' . filesize( $path ) );
			readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			exit;
		}

		$png = SB_EmailSig_Campaign::spacer( (int) ( $s['photo']['width'] ?? 1 ) );
		header( 'Content-Type: image/png' );
		header( 'Content-Length: ' . strlen( $png ) );
		echo $png; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/** The made images, by template id (an empty meta comes back as ''). */
	private static function map( $post_id ) {
		$map = get_post_meta( $post_id, self::MAP_META, true );

		return is_array( $map ) ? array_filter( $map, 'is_array' ) : array();
	}

	private static function entry_out( array $entry, array $dir ) {
		return array(
			'url'    => $dir['url'] . '/' . $entry['file'],
			'width'  => (int) $entry['width'],
			'height' => (int) $entry['height'],
			// Only without GD is the shape left to CSS (border-radius).
			'baked'  => ! empty( $entry['baked'] ),
			'shape'  => $entry['shape'] ?? 'square',
			'radius' => (int) ( $entry['radius'] ?? 0 ),
		);
	}

	/**
	 * Drop $key from the map, deleting its file unless another key uses it
	 * or it is $keep (the file about to take its place).
	 */
	private static function forget( $post_id, array &$map, $key, $keep = '' ) {
		if ( ! isset( $map[ $key ] ) ) {
			return;
		}

		$file = $map[ $key ]['file'];
		unset( $map[ $key ] );

		if ( $file === $keep ) {
			return;
		}

		foreach ( $map as $entry ) {
			if ( ( $entry['file'] ?? '' ) === $file ) {
				return;
			}
		}

		$path = self::dir()['path'] . '/' . $file;
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * On save: the person's main template's image and the vCard's square JPG, made
	 * now so the first signature after saving is quick. Removes everything
	 * when the photo has gone.
	 */
	public static function generate( $post_id ) {
		list( $att_id, $file ) = self::source( $post_id );

		self::delete_legacy( $post_id );

		if ( ! $att_id ) {
			self::delete_files( $post_id );
			return;
		}

		$crop = self::get_crop( $post_id, $att_id );
		$hash = substr( md5( $att_id . '|' . wp_json_encode( $crop ) . '|' . filemtime( $file ) . '|' . self::IMAGE_REV ), 0, 10 );
		$dir  = self::dir();
		$jpg  = $post_id . '-' . $hash . '.jpg';
		$old  = (string) get_post_meta( $post_id, '_sb_emailsig_photo_jpg', true );

		if ( $old !== $jpg || ! file_exists( $dir['path'] . '/' . $jpg ) ) {
			wp_mkdir_p( $dir['path'] );
			if ( self::build_jpg( $file, $crop, $dir['path'] . '/' . $jpg ) ) {
				if ( $old && $old !== $jpg && file_exists( $dir['path'] . '/' . $old ) ) {
					wp_delete_file( $dir['path'] . '/' . $old );
				}
				update_post_meta( $post_id, '_sb_emailsig_photo_jpg', $jpg );
			}
		}

		list( $tpl, $s ) = self::styles_for( SB_EmailSig_Templates::for_person( $post_id ), $post_id );
		if ( $tpl ) {
			self::image( $post_id, $tpl, $s );
		}
	}

	/** The crop box in source pixels at $ratio (width over height), fitted around the crop's middle. */
	private static function box( $w, $h, $crop, $ratio ) {
		if ( $crop ) {
			$bx = $crop['x'] * $w;
			$by = $crop['y'] * $h;
			$bw = $crop['w'] * $w;
			$bh = $crop['h'] * $h;
		} else {
			$bx = 0;
			$by = 0;
			$bw = $w;
			$bh = $h;
		}

		$cx = $bx + $bw / 2;
		$cy = $by + $bh / 2;

		// Same area as the crop, at the new shape, inside the photo.
		$nw = sqrt( max( 1, $bw * $bh ) * $ratio );
		$nh = $nw / $ratio;
		if ( $nw > $w ) {
			$nh *= $w / $nw;
			$nw  = $w;
		}
		if ( $nh > $h ) {
			$nw *= $h / $nh;
			$nh  = $h;
		}

		$x = max( 0, min( $cx - $nw / 2, $w - $nw ) );
		$y = max( 0, min( $cy - $nh / 2, $h - $nh ) );

		return array( (int) round( $x ), (int) round( $y ), max( 1, (int) round( $nw ) ), max( 1, (int) round( $nh ) ) );
	}

	/**
	 * Make the PNG at twice display size with the shape baked in: true when
	 * baked (GD), false when only cropped (no GD, the template rounds it with
	 * CSS), null on failure.
	 */
	private static function build( $file, $crop, $ratio, array $spec, $path ) {
		$pw = $spec['w'] * self::SCALE;
		$ph = $spec['h'] * self::SCALE;

		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$size = wp_getimagesize( $file );
			$ed   = $size ? wp_get_image_editor( $file ) : null;
			if ( ! $ed || is_wp_error( $ed ) ) {
				return null;
			}
			list( $x, $y, $bw, $bh ) = self::box( $size[0], $size[1], $crop, $ratio );
			$ed->crop( $x, $y, $bw, $bh, $pw, $ph );

			return is_wp_error( $ed->save( $path, 'image/png' ) ) ? null : false;
		}

		$src = @imagecreatefromstring( file_get_contents( $file ) ); // phpcs:ignore
		if ( ! $src ) {
			return null;
		}

		// A border: the photo inside it, then the ring around the edge.
		if ( ! empty( $spec['border'] ) ) {
			$ok = self::build_ringed( $src, $crop, $spec, $path );
			imagedestroy( $src );

			return $ok ? true : null;
		}

		list( $x, $y, $bw, $bh ) = self::box( imagesx( $src ), imagesy( $src ), $crop, $ratio );

		$dst = imagecreatetruecolor( $pw, $ph );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		imagefill( $dst, 0, 0, imagecolorallocatealpha( $dst, 0, 0, 0, 127 ) );
		imagecopyresampled( $dst, $src, 0, 0, $x, $y, $pw, $ph, $bw, $bh );
		imagedestroy( $src );

		if ( $spec['shape'] === 'circle' ) {
			self::mask( $dst, $pw, $ph, 'ellipse', 0 );
		} elseif ( $spec['shape'] === 'rounded' && $spec['radius'] > 0 ) {
			self::mask( $dst, $pw, $ph, 'rounded', min( $spec['radius'] * self::SCALE, min( $pw, $ph ) / 2 ) );
		}

		$ok = imagepng( $dst, $path, 9 );
		imagedestroy( $dst );

		return $ok ? true : null;
	}

	/**
	 * A photo with a border drawn in (Sam, 30 October 2026): the same size
	 * as without, the photo set in by the border and gap, a see-through gap,
	 * and the ring in the border colour on the edge, following the shape.
	 * Soft edges throughout, as mask() makes them.
	 */
	private static function build_ringed( $src, $crop, array $spec, $path ) {
		$pw     = $spec['w'] * self::SCALE;
		$ph     = $spec['h'] * self::SCALE;
		$b      = max( 1, (int) $spec['border'] ) * self::SCALE;
		$inset  = $b + max( 0, (int) ( $spec['gap'] ?? 0 ) ) * self::SCALE;
		$iw     = max( 2, $pw - 2 * $inset );
		$ih     = max( 2, $ph - 2 * $inset );
		$shape  = $spec['shape'];
		$radius = $shape === 'rounded' ? min( $spec['radius'] * self::SCALE, min( $pw, $ph ) / 2 ) : 0;

		// The photo, cropped at the inner box's own shape.
		list( $x, $y, $bw, $bh ) = self::box( imagesx( $src ), imagesy( $src ), $crop, $iw / $ih );
		$in = imagecreatetruecolor( $iw, $ih );
		imagealphablending( $in, false );
		imagesavealpha( $in, true );
		imagefill( $in, 0, 0, imagecolorallocatealpha( $in, 0, 0, 0, 127 ) );
		imagecopyresampled( $in, $src, 0, 0, $x, $y, $iw, $ih, $bw, $bh );

		if ( $shape === 'circle' ) {
			self::mask( $in, $iw, $ih, 'ellipse', 0 );
		} elseif ( $shape === 'rounded' && $radius > $inset ) {
			self::mask( $in, $iw, $ih, 'rounded', $radius - $inset );
		}

		$dst = imagecreatetruecolor( $pw, $ph );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		imagefill( $dst, 0, 0, imagecolorallocatealpha( $dst, 0, 0, 0, 127 ) );
		imagecopy( $dst, $in, $inset, $inset, 0, 0, $iw, $ih );
		imagedestroy( $in );

		// The ring: inside the outer edge, outside the edge $b in.
		$hex = ltrim( (string) ( $spec['colour'] ?? '' ), '#' );
		$hex = preg_match( '/^[0-9a-f]{6}$/i', $hex ) ? $hex : '333333';
		$rr  = hexdec( substr( $hex, 0, 2 ) );
		$rg  = hexdec( substr( $hex, 2, 2 ) );
		$rb  = hexdec( substr( $hex, 4, 2 ) );
		$geo = $shape === 'circle' ? 'ellipse' : 'box';

		for ( $iy = 0; $iy < $ph; $iy++ ) {
			for ( $ix = 0; $ix < $pw; $ix++ ) {
				$px    = $ix + 0.5;
				$py    = $iy + 0.5;
				$outer = self::cover( $px, $py, $pw, $ph, $geo, $radius, 0 );

				if ( $outer <= 0 ) {
					continue;
				}

				$ring = $outer - self::cover( $px, $py, $pw, $ph, $geo, $radius, $b );

				if ( $ring <= 0 ) {
					continue;
				}

				// The ring over what is there (the gap or the photo's soft edge).
				$rgb = imagecolorat( $dst, $ix, $iy );
				$ba  = 1 - ( ( $rgb >> 24 ) & 0x7F ) / 127;
				$oa  = $ring + $ba * ( 1 - $ring );
				$mix = function ( $top, $under ) use ( $ring, $ba, $oa ) {
					return $oa > 0 ? (int) round( ( $top * $ring + $under * $ba * ( 1 - $ring ) ) / $oa ) : 0;
				};

				imagesetpixel(
					$dst,
					$ix,
					$iy,
					imagecolorallocatealpha(
						$dst,
						$mix( $rr, ( $rgb >> 16 ) & 0xFF ),
						$mix( $rg, ( $rgb >> 8 ) & 0xFF ),
						$mix( $rb, $rgb & 0xFF ),
						127 - (int) round( $oa * 127 )
					)
				);
			}
		}

		$ok = imagepng( $dst, $path, 9 );
		imagedestroy( $dst );

		return $ok;
	}

	/**
	 * How much of the pixel at $px, $py is inside the shape set in by $inset
	 * (0 to 1, soft at the edge): an ellipse filling $w by $h, or a box with
	 * corners of radius $r (0 square).
	 */
	private static function cover( $px, $py, $w, $h, $geo, $r, $inset ) {
		$hx = $w / 2 - $inset;
		$hy = $h / 2 - $inset;

		if ( $hx <= 0 || $hy <= 0 ) {
			return 0;
		}

		$dx = $px - $w / 2;
		$dy = $py - $h / 2;

		if ( $geo === 'ellipse' ) {
			$dist = ( sqrt( ( $dx / $hx ) ** 2 + ( $dy / $hy ) ** 2 ) - 1 ) * min( $hx, $hy );
		} else {
			$r    = max( 0, min( $r - $inset, min( $hx, $hy ) ) );
			$qx   = abs( $dx ) - ( $hx - $r );
			$qy   = abs( $dy ) - ( $hy - $r );
			$dist = sqrt( max( $qx, 0 ) ** 2 + max( $qy, 0 ) ** 2 ) + min( max( $qx, $qy ), 0 ) - $r;
		}

		return max( 0, min( 1, 0.5 - $dist ) );
	}

	/** Make the corners transparent, with a soft (anti-aliased) edge. */
	private static function mask( $img, $w, $h, $type, $r ) {
		$rx = $w / 2;
		$ry = $h / 2;

		for ( $iy = 0; $iy < $h; $iy++ ) {
			for ( $ix = 0; $ix < $w; $ix++ ) {
				$px = $ix + 0.5;
				$py = $iy + 0.5;

				if ( $type === 'ellipse' ) {
					$d     = sqrt( ( ( $px - $rx ) / $rx ) ** 2 + ( ( $py - $ry ) / $ry ) ** 2 );
					$cover = ( 1 - $d ) * min( $rx, $ry ) + 0.5;
				} else {
					// Only the corners: distance from the corner's circle.
					$cx = $px < $r ? $r : ( $px > $w - $r ? $w - $r : $px );
					$cy = $py < $r ? $r : ( $py > $h - $r ? $h - $r : $py );
					if ( $cx === $px || $cy === $py ) {
						continue;
					}
					$cover = $r - sqrt( ( $px - $cx ) ** 2 + ( $py - $cy ) ** 2 ) + 0.5;
				}

				if ( $cover >= 1 ) {
					continue;
				}

				$cover = max( 0, $cover );
				$rgb   = imagecolorat( $img, $ix, $iy );
				$a     = 127 - (int) round( $cover * ( 127 - ( ( $rgb >> 24 ) & 0x7F ) ) );
				imagesetpixel( $img, $ix, $iy, imagecolorallocatealpha( $img, ( $rgb >> 16 ) & 0xFF, ( $rgb >> 8 ) & 0xFF, $rgb & 0xFF, $a ) );
			}
		}
	}

	/** Square JPG for the vCard, from the middle of the crop. */
	private static function build_jpg( $file, $crop, $path ) {
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$src = @imagecreatefromstring( file_get_contents( $file ) ); // phpcs:ignore
			if ( ! $src ) {
				return false;
			}
			list( $x, $y, $bw, $bh ) = self::box( imagesx( $src ), imagesy( $src ), $crop, 1 );
			$sq = imagecreatetruecolor( self::VCARD_PX, self::VCARD_PX );
			imagefill( $sq, 0, 0, imagecolorallocate( $sq, 255, 255, 255 ) );
			imagecopyresampled( $sq, $src, 0, 0, $x, $y, self::VCARD_PX, self::VCARD_PX, $bw, $bh );
			$ok = imagejpeg( $sq, $path, 85 );
			imagedestroy( $sq );
			imagedestroy( $src );

			return $ok;
		}

		$size = wp_getimagesize( $file );
		$ed   = $size ? wp_get_image_editor( $file ) : null;
		if ( ! $ed || is_wp_error( $ed ) ) {
			return false;
		}
		list( $x, $y, $bw, $bh ) = self::box( $size[0], $size[1], $crop, 1 );
		$ed->crop( $x, $y, $bw, $bh, self::VCARD_PX, self::VCARD_PX );

		return ! is_wp_error( $ed->save( $path, 'image/jpeg' ) );
	}

	/** Before October 2026's shapes: one circle PNG per person. */
	private static function delete_legacy( $post_id ) {
		$name = (string) get_post_meta( $post_id, '_sb_emailsig_photo_png', true );

		if ( $name !== '' ) {
			$path = self::dir()['path'] . '/' . $name;
			if ( file_exists( $path ) ) {
				wp_delete_file( $path );
			}
		}

		foreach ( array( '_sb_emailsig_photo_png', '_sb_emailsig_photo_hash', '_sb_emailsig_photo_circle' ) as $key ) {
			delete_post_meta( $post_id, $key );
		}
	}

	private static function delete_files( $post_id ) {
		$dir = self::dir();
		$map = self::map( $post_id );

		foreach ( $map as $entry ) {
			if ( ! empty( $entry['file'] ) && file_exists( $dir['path'] . '/' . $entry['file'] ) ) {
				wp_delete_file( $dir['path'] . '/' . $entry['file'] );
			}
		}
		delete_post_meta( $post_id, self::MAP_META );

		$jpg = (string) get_post_meta( $post_id, '_sb_emailsig_photo_jpg', true );
		if ( $jpg !== '' && file_exists( $dir['path'] . '/' . $jpg ) ) {
			wp_delete_file( $dir['path'] . '/' . $jpg );
		}
		delete_post_meta( $post_id, '_sb_emailsig_photo_jpg' );

		self::delete_legacy( $post_id );
	}

	public static function on_delete( $post_id ) {
		if ( SB_EMAILSIG_CPT === get_post_type( $post_id ) ) {
			self::delete_files( $post_id );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Getters
	 * ------------------------------------------------------------------ */

	/**
	 * The photo as the person's main template makes it, for thumbnails on
	 * admin pages and the team page (shown even if the template hides photos).
	 */
	public static function png_url( $post_id ) {
		$img = self::png( $post_id );

		return $img ? $img['url'] : '';
	}

	/** The person's photo as their main template makes it: [ url, width, height, baked ], or null. */
	public static function png( $post_id ) {
		list( $tpl, $s ) = self::styles_for( SB_EmailSig_Templates::for_person( $post_id ), $post_id );

		if ( ! $tpl ) {
			return null;
		}

		$s['photo']         = ! empty( $s['photo'] ) ? $s['photo'] : self::default_look();
		$s['photo']['show'] = true;

		return self::image( $post_id, $tpl, $s ) ?: null;
	}

	public static function jpg_path( $post_id ) {
		$name = get_post_meta( $post_id, '_sb_emailsig_photo_jpg', true );
		$path = $name ? self::dir()['path'] . '/' . $name : '';
		return ( $path && file_exists( $path ) ) ? $path : '';
	}
}
