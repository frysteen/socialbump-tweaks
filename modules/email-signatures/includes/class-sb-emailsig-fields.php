<?php
/**
 * ACF field groups and the settings (options) page, registered in PHP.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Fields {

	/**
	 * Each social, button and banner row has a fixed id, so a short link in a
	 * signature already in use still finds its row after rows are added,
	 * removed or moved (SB_EmailSig_Tracking). Hidden in the Settings page,
	 * made on save. repeater name => [ sub field name, sub field key ].
	 */
	const ROW_IDS = array(
		'sb_emailsig_social_media' => array( 'social_media_id', 'field_sb_emailsig_social_media_id' ),
		'sb_emailsig_buttons'      => array( 'button_id', 'field_sb_emailsig_button_id' ),
		'sb_emailsig_banners'      => array( 'banner_id', 'field_sb_emailsig_banner_id' ),
		'sb_emailsig_info'         => array( 'info_id', 'field_sb_emailsig_info_id' ),
		'sb_emailsig_person_info'  => array( 'info_id', 'field_sb_emailsig_person_info_id' ),
	);

	/** Set once every row saved before rows had ids has one. */
	const ROW_IDS_OPTION = 'sb_emailsig_row_ids';

	/**
	 * Icons a company's Social Media row can have: every Info List icon
	 * (Sam, 2 November 2026: a phone or email icon among them), then Custom
	 * SVG. The brand ones are drawn from assets/social, the rest from the
	 * template's icons (SB_EmailSig_Icons::builtin_svg()).
	 */
	public static function social_icons() {
		$all = self::info_icons();
		unset( $all[''] );

		return $all + array( 'custom' => 'Custom SVG' );
	}

	/**
	 * The Info List lines with a fixed icon, which a company can change on
	 * its Branding tab (Sam, 2 November 2026): key => [ label, default ].
	 * Email covers the company's Email line too, so email looks the same
	 * everywhere. Layout::rows() and vcard() read the company's choice from
	 * Data's line_icons.
	 */
	const LINE_ICONS = array(
		'phone'   => array( 'Phone', 'phone' ),
		'mobile'  => array( 'Mobile', 'smartphone' ),
		'email'   => array( 'Email', 'mail' ),
		'website' => array( 'Website', 'globe' ),
		'address' => array( 'Address', 'map-pin' ),
		'vcard'   => array( 'Save Contact', 'user-plus' ),
	);

	/** The Branding tab's Info List Icons: each line's Icon menu (its default to start) with Custom SVG beside it. */
	private static function line_icon_fields() {
		$out = array( self::sub_heading( 'field_sb_emailsig_line_icons_head', 'Info List Icons' ) );

		foreach ( self::LINE_ICONS as $key => $line ) {
			$out[] = array(
				'key'           => 'field_sb_emailsig_line_' . $key . '_icon',
				'label'         => $line[0],
				'name'          => 'sb_emailsig_line_' . $key . '_icon',
				'type'          => 'select',
				'choices'       => self::icon_choices( false ),
				'default_value' => $line[1],
				'wrapper'       => array( 'width' => '25', 'class' => 'sb-emailsig-line-icon' ),
			);
			$out[] = array(
				// Its space always there, empty until the icon is Custom SVG (admin.css).
				'key'           => 'field_sb_emailsig_line_' . $key . '_icon_file',
				'label'         => 'Custom SVG',
				'name'          => 'sb_emailsig_line_' . $key . '_icon_file',
				'type'          => 'file',
				'return_format' => 'id',
				'library'       => 'all',
				'mime_types'    => 'svg',
				'wrapper'       => array( 'width' => '25', 'class' => 'sb-emailsig-line-file' ),
			);
		}

		return $out;
	}

	/** Each Info List Icons menu's default, by field name, for the picker's reset (icon-picker.js). */
	public static function line_icon_defaults() {
		$out = array();

		foreach ( self::LINE_ICONS as $key => $line ) {
			$out[ 'sb_emailsig_line_' . $key . '_icon' ] = $line[1];
		}

		return $out;
	}

	/**
	 * Every Icon menu (Social Media, Additional Buttons, the company's and a
	 * person's Additional Info List Items): one list, the same everywhere
	 * (Sam, 2 November 2026). None, the groups (info_icon_groups()), then
	 * Custom SVG, which each has a file field beside it for. Social Media
	 * leaves out None ($none false): a row there is only its icon.
	 */
	public static function icon_choices( $none = true ) {
		$out = self::info_icon_groups();

		if ( ! $none ) {
			unset( $out[''] );
		}

		$out['custom'] = 'Custom SVG';

		return $out;
	}

	/**
	 * Icons an Additional Info List Item can have: name => label. Each is
	 * one of the template's contact icons (styles.php icons names), drawn in
	 * its icon style. '' for none.
	 */
	public static function info_icons() {
		return array(
			''               => 'None',
			'phone'          => 'Phone',
			'smartphone'     => 'Mobile',
			'mail'           => 'Email',
			'globe'          => 'Website',
			'map-pin'        => 'Location',
			'clock'          => 'Clock',
			'calendar'       => 'Calendar',
			'info'           => 'Info',
			'link'           => 'Link',
			'award'          => 'Award',
			'star'           => 'Star',
			'heart'          => 'Heart',
			'gift'           => 'Gift',
			'briefcase'      => 'Briefcase',
			'building-2'     => 'Building',
			'graduation-cap' => 'Graduation cap',
			'message-circle' => 'Message',
			'file-text'      => 'Quote',
			'clipboard-list' => 'Clipboard',
			'calculator'     => 'Calculator',
			'dollar-sign'    => 'Dollar',
			'receipt'        => 'Receipt',
			'printer'        => 'Printer',
			'car'            => 'Car',
			'user-plus'      => 'Contact',
			'thumbs-up'      => 'Thumbs up',
			'facebook'       => 'Facebook',
			'instagram'      => 'Instagram',
			'linkedin'       => 'LinkedIn',
			'youtube'        => 'YouTube',
			'tiktok'         => 'TikTok',
			'x'              => 'X',
			'threads'        => 'Threads',
			'pinterest'      => 'Pinterest',
			'google'         => 'Google',
			'whatsapp'       => 'WhatsApp',
			'messenger'      => 'Messenger',
			'houzz'          => 'Houzz',
		);
	}

	/**
	 * The same icons in groups, for the Icon menu (Sam, 1 November 2026):
	 * group label => [ name => label ], None on its own first.
	 */
	public static function info_icon_groups() {
		$all    = self::info_icons();
		$groups = array(
			'Contact'               => array( 'phone', 'smartphone', 'mail', 'globe', 'map-pin', 'user-plus', 'printer' ),
			// Quotes and enquiries (Sam, 2 November 2026).
			'Enquiries'             => array( 'message-circle', 'file-text', 'clipboard-list', 'calculator', 'dollar-sign', 'receipt' ),
			'Social Media'          => array( 'facebook', 'instagram', 'linkedin', 'youtube', 'tiktok', 'x', 'threads', 'pinterest', 'google', 'whatsapp', 'messenger', 'houzz' ),
			'Bookings and Hours'    => array( 'calendar', 'clock' ),
			'Reviews and Awards'    => array( 'star', 'heart', 'thumbs-up', 'award' ),
			'Other'                 => array( 'info', 'link', 'gift', 'briefcase', 'building-2', 'graduation-cap', 'car' ),
		);
		$out    = array( '' => $all[''] );

		foreach ( $groups as $label => $names ) {
			$out[ $label ] = array_intersect_key( $all, array_flip( $names ) );
			// In the group's order, not the list's.
			$out[ $label ] = array_merge( array_flip( $names ), $out[ $label ] );
		}

		return $out;
	}

	/**
	 * An Additional Info List Items repeater's rows: icon, label, text, link,
	 * a Custom SVG (when the icon is Custom SVG, Sam, 2 November 2026) and
	 * the hidden row id. $p is the key part (info or person_info), $repeater
	 * its name.
	 *
	 * Custom SVG comes straight after Icon and its space is always there,
	 * empty until the Icon is Custom SVG (Sam, 2 November 2026). That's
	 * admin.css, not ACF's conditional logic: ACF takes a hidden field's
	 * space away and, in a table, its column. Only a row whose icon is
	 * custom uses its file (Data::info_rows(), Icons::custom_names()).
	 *
	 * The company's is a table, Text taking what's left. A person's is in
	 * blocks, as Sam's mock-up: Icon and Custom SVG on the first line, then
	 * Label, Text and Link.
	 */
	private static function info_sub_fields( $p, $repeater ) {
		$person = 'person_info' === $p;
		$w      = $person
			? array( 'icon' => '50', 'label' => '33.33', 'text' => '33.33', 'link' => '33.34', 'file' => '50' )
			: array( 'icon' => '15', 'label' => '20', 'text' => '', 'link' => '20', 'file' => '15' );

		$fields = array(
			array(
				'key'           => 'field_sb_emailsig_' . $p . '_icon',
				'label'         => 'Icon',
				'name'          => 'info_icon',
				'type'          => 'select',
				'choices'       => self::icon_choices(),
				'default_value' => '',
				'wrapper'       => array( 'width' => $w['icon'] ),
			),
			array(
				'key'      => 'field_sb_emailsig_' . $p . '_label',
				'label'    => 'Label',
				'name'     => 'info_label',
				'type'     => 'text',
				'required' => 1,
				'wrapper'  => array( 'width' => $w['label'] ),
			),
			array(
				'key'      => 'field_sb_emailsig_' . $p . '_text',
				'label'    => 'Text',
				'name'     => 'info_text',
				'type'     => 'text',
				'required' => 1,
				'wrapper'  => array( 'width' => $w['text'] ),
			),
			array(
				'key'         => 'field_sb_emailsig_' . $p . '_link',
				'label'       => 'Link',
				'name'        => 'info_link',
				'type'        => 'text',
				'placeholder' => 'Optional',
				'wrapper'     => array( 'width' => $w['link'] ),
			),
			array(
				// As a Social Media row's (SB_EmailSig_Icons::custom_names()).
				'key'               => 'field_sb_emailsig_' . $p . '_icon_file',
				'label'             => 'Custom SVG',
				'name'              => 'info_icon_file',
				'type'              => 'file',
				'return_format'     => 'id',
				'library'           => 'all',
				'mime_types'        => 'svg',
				'wrapper'           => array( 'width' => $w['file'] ),
			),
			self::row_id_field( $repeater ),
		);

		$file = array_splice( $fields, 4, 1 );
		array_splice( $fields, 1, 0, $file );

		return $fields;
	}

	/** An Additional Info List Item link: a web address, mailto: or tel:. */
	public static function info_link_valid( $valid, $value ) {
		$value = trim( (string) $value );

		if ( $valid !== true || $value === '' ) {
			return $valid;
		}

		return preg_match( '#^(https?://[^\s]+\.[^\s]+|mailto:[^\s@]+@[^\s@]+|tel:\+?[0-9 ()-]+)$#i', $value ) ? true : 'Start with https://, mailto: or tel:.';
	}

	/** Conditional logic: shown while Campaign Banner is on. */
	private static function when_campaign() {
		return array(
			array(
				array(
					'field'    => 'field_sb_emailsig_campaign_show',
					'operator' => '==',
					'value'    => '1',
				),
			),
		);
	}

	/** A small heading across the row, splitting a tab into parts: an ACF message field with only a label. */
	private static function sub_heading( $key, $label ) {
		return array(
			'key'       => $key,
			'label'     => $label,
			'name'      => '',
			'type'      => 'message',
			'message'   => '',
			'new_lines' => '',
			'wrapper'   => array( 'class' => 'sb-emailsig-sub-heading' ),
		);
	}

	/** Conditional logic: shown while Add Tracking Codes is on. */
	private static function when_tracking() {
		return array(
			array(
				array(
					'field'    => 'field_sb_emailsig_utm_on',
					'operator' => '==',
					'value'    => '1',
				),
			),
		);
	}

	public static function init() {
		add_action( 'acf/init', array( __CLASS__, 'person_fields' ) );
		add_action( 'acf/init', array( __CLASS__, 'settings_fields' ) );
		add_action( 'init', array( __CLASS__, 'add_row_ids' ), 20 );

		foreach ( self::ROW_IDS as $sub ) {
			add_filter( 'acf/update_value/key=' . $sub[1], array( __CLASS__, 'row_id' ), 10, 3 );
		}

		add_filter( 'acf/load_field/key=field_sb_emailsig_banners', array( __CLASS__, 'banner_help' ) );
		// Footer Text: a toolbar with Bold and Link only, and only those kept.
		add_filter( 'acf/fields/wysiwyg/toolbars', array( __CLASS__, 'toolbars' ) );
		add_filter( 'acf/update_value/key=field_sb_emailsig_fine_print', array( __CLASS__, 'footer_value' ) );
		add_filter( 'acf/validate_value/key=field_sb_emailsig_info_link', array( __CLASS__, 'info_link_valid' ), 10, 2 );
		add_filter( 'acf/validate_value/key=field_sb_emailsig_person_info_link', array( __CLASS__, 'info_link_valid' ), 10, 2 );

		foreach ( array_keys( SB_EmailSig_Styles::BRAND ) as $key ) {
			add_filter( 'acf/validate_value/key=field_sb_emailsig_brand_' . $key, array( __CLASS__, 'brand_valid' ), 10, 2 );
			add_filter( 'acf/update_value/key=field_sb_emailsig_brand_' . $key, array( __CLASS__, 'brand_value' ), 10, 3 );
		}
		add_filter( 'acf/load_field/key=field_sb_emailsig_campaign_width', array( __CLASS__, 'campaign_help' ) );
		// A button's own colour: a hex, a brand colour or a theme colour, saved cleaned.
		add_filter( 'acf/validate_value/key=field_sb_emailsig_button_colour', array( __CLASS__, 'button_colour_valid' ), 10, 2 );
		add_filter( 'acf/update_value/key=field_sb_emailsig_button_colour', array( 'SB_EmailSig_Styles', 'clean_colour' ), 10, 1 );

		// Templates: the choices (people have none of their own since 1 November 2026).
		add_filter( 'acf/load_field/key=field_sb_emailsig_main_template', array( __CLASS__, 'template_choices' ) );
		add_filter( 'acf/load_field/key=field_sb_emailsig_templates', array( __CLASS__, 'template_choices' ) );
		// The Default Template is a company's own field (SB_EmailSig_Company).
		add_filter( 'acf/load_field/key=field_sb_emailsig_person_company', array( __CLASS__, 'company_choices' ) );
		add_filter( 'acf/load_value/key=field_sb_emailsig_person_company', array( __CLASS__, 'company_value' ), 10, 2 );
		add_filter( 'acf/prepare_field/key=field_sb_emailsig_person_company', array( __CLASS__, 'when_companies' ) );
	}

	/** The SB Footer toolbar: Bold, Italic, Link and Unlink. ACF keys it sb_footer (the label through sanitize_title(), dashes made underscores). */
	public static function toolbars( $toolbars ) {
		$toolbars['SB Footer'] = array( 1 => array( 'bold', 'italic', 'link', 'unlink' ) );

		return $toolbars;
	}

	/** Footer Text as saved: bold, links and line breaks only (SB_EmailSig_Data::footer_clean()). */
	public static function footer_value( $value ) {
		return SB_EmailSig_Data::footer_clean( $value );
	}

	/** A brand colour: a hex or a theme colour, not another brand colour. */
	public static function brand_valid( $valid, $value ) {
		if ( $valid !== true || trim( (string) $value ) === '' ) {
			return $valid;
		}

		$c = SB_EmailSig_Styles::clean_colour( $value );

		if ( $c === '' || strpos( $c, 'var(--sb-brand-' ) === 0 ) {
			return 'Use a colour such as #098b97, or pick a theme colour.';
		}

		return SB_EmailSig_Styles::theme_hex( $c ) !== '' ? true : 'That colour variable isn\'t in this site\'s Bricks, Core Framework, Elementor or theme colours. Pick one from the list.';
	}

	/** A button's Colour: blank (the template's), a hex, a brand colour or a theme colour. */
	public static function button_colour_valid( $valid, $value ) {
		if ( $valid !== true || trim( (string) $value ) === '' ) {
			return $valid;
		}

		$c = SB_EmailSig_Styles::clean_colour( $value );

		if ( $c === '' ) {
			return 'Use a colour such as #d63638, or pick one from the list.';
		}

		return SB_EmailSig_Styles::hex( $c ) !== '' ? true : 'That colour isn\'t one of the brand or theme colours. Pick one from the list.';
	}

	/** Saved cleaned, and never blank: blank saves the default (Accent: blank, the primary). */
	public static function brand_value( $value, $post_id, $field ) {
		$key = substr( (string) $field['name'], strlen( 'sb_emailsig_brand_' ) );
		$c   = SB_EmailSig_Styles::clean_colour( $value );

		return $c !== '' ? $c : ( SB_EmailSig_Styles::BRAND[ $key ][1] ?? '' );
	}

	/**
	 * The best size for a banner shown $w wide: twice as wide, so it's sharp
	 * on Retina screens, at 4:1 (or 6:1 for a slimmer strip). "1200 x 300 px
	 * (or 1200 x 200 for a slimmer strip)" at 600.
	 */
	private static function best_size( $w ) {
		$w = max( 100, (int) $w );

		return ( 2 * $w ) . ' x ' . (int) round( $w / 2 ) . ' px (or ' . ( 2 * $w ) . ' x ' . (int) round( $w / 3 ) . ' for a slimmer strip)';
	}

	/** Banners: where they show, and the best size for the site's template width. */
	public static function banner_help( $field ) {
		$w   = 600;
		$tpl = class_exists( 'SB_EmailSig_Templates' ) ? SB_EmailSig_Templates::get( SB_EmailSig_Templates::current() ) : null;

		if ( $tpl ) {
			$s = SB_EmailSig_Styles::resolve( $tpl );
			$w = (int) ( $s['width'] ?? 600 );
		}

		$field['instructions'] = 'Shown one under another below the signature, above the footer text. Best size: ' . self::best_size( $w ) . ', a PNG or JPG. It shows ' . $w . ' px wide, the signature\'s width, so twice that keeps it sharp on Retina screens.';

		return $field;
	}

	/** Every template's name, by id. */
	private static function template_names() {
		$out = array();

		foreach ( SB_EmailSig_Templates::all() as $id => $tpl ) {
			$out[ (string) $id ] = $tpl['name'];
		}

		return $out;
	}

	public static function template_choices( $field ) {
		$field['choices'] = self::template_names();

		return $field;
	}

	/** The companies, by id, for a person's Company. */
	public static function company_choices( $field ) {
		$field['choices'] = array();

		foreach ( SB_EmailSig_Company::all() as $co ) {
			$field['choices'][ (string) $co ] = SB_EmailSig_Company::name( $co );
		}

		return $field;
	}

	/** A person's company: theirs, else the default (a new person). */
	public static function company_value( $value, $post_id ) {
		return (string) ( is_numeric( $post_id ) ? SB_EmailSig_Company::of_person( (int) $post_id ) : SB_EmailSig_Company::default_id() );
	}

	/** Company shows only when there is a choice. */
	public static function when_companies( $field ) {
		return ( $field && count( SB_EmailSig_Company::all() ) > 1 ) ? $field : false;
	}

	/** Campaign Banner width, with the best image size for it. */
	public static function campaign_help( $field ) {
		$w = class_exists( 'SB_EmailSig_Campaign' ) ? SB_EmailSig_Campaign::width() : 600;

		$field['instructions'] = 'Make every banner this wide, any height. Best image size: ' . self::best_size( $w ) . ', twice the width so it\'s sharp on Retina screens.';

		return $field;
	}

	/** A new row id: r and 7 letters or numbers. */
	public static function new_row_id() {
		return 'r' . wp_generate_password( 7, false, false );
	}

	/** On save: keep a row's id, or give it one (a new row, or a copy of another). */
	public static function row_id( $value, $post_id, $field ) {
		static $seen = array();

		$value = preg_replace( '/[^A-Za-z0-9]/', '', (string) $value );
		$key   = $field['key'];

		if ( $value === '' || isset( $seen[ $post_id ][ $key ][ $value ] ) ) {
			$value = self::new_row_id();
		}

		$seen[ $post_id ][ $key ][ $value ] = true;

		return $value;
	}

	/** Once: ids for the rows saved before rows had them, straight into ACF's options. */
	public static function add_row_ids() {
		if ( get_option( self::ROW_IDS_OPTION ) ) {
			return;
		}

		foreach ( self::ROW_IDS as $repeater => $sub ) {
			$rows = (int) get_option( 'options_' . $repeater, 0 );

			for ( $i = 0; $i < $rows; $i++ ) {
				$name = 'options_' . $repeater . '_' . $i . '_' . $sub[0];

				if ( (string) get_option( $name, '' ) === '' ) {
					update_option( $name, self::new_row_id(), false );
					update_option( '_' . $name, $sub[1], false );
				}
			}
		}

		update_option( self::ROW_IDS_OPTION, 1, true );
	}

	/** A row's hidden id field. */
	private static function row_id_field( $repeater ) {
		$sub = self::ROW_IDS[ $repeater ];

		return array(
			'key'      => $sub[1],
			'label'    => 'ID',
			'name'     => $sub[0],
			'type'     => 'text',
			'readonly' => 1,
			// acf-hidden too: ACF's table sizing counts a column as hidden only
			// by that class, and gave this unseen one a share of the width,
			// which went to the row number instead (Sam, 2 November 2026).
			'wrapper'  => array( 'class' => 'sb-emailsig-row-id acf-hidden' ),
		);
	}

	public static function person_fields() {
		acf_add_local_field_group(
			array(
				'key'                   => 'group_sb_emailsig_person',
				'title'                 => 'Person',
				'fields'                => array(
					// Their company (SB_EmailSig_Company): only when there is more than one.
					array(
						'key'           => 'field_sb_emailsig_person_company',
						'label'         => 'Company',
						'name'          => SB_EmailSig_Company::PERSON_META,
						'type'          => 'select',
						'choices'       => array(),
						'allow_null'    => 0,
						'return_format' => 'value',
					),
					// Only when a company names its staff pages' post type (SB_EmailSig_Staff);
					// staff.js hides it for a company without one.
					array(
						'key'           => 'field_sb_emailsig_staff_page',
						'label'         => 'Staff Page',
						'name'          => 'sb_emailsig_staff_page',
						'type'          => 'post_object',
						'post_type'     => array( 'post' ),
						'return_format' => 'id',
						'allow_null'    => 1,
						'ui'            => 1,
						'instructions'  => 'Their photo and name link here. Its photo and details can fill in the fields below.',
					),
					// First and last name (Sam, 31 October 2026); the full name
					// below is built from them on save (SB_EmailSig_Names) and
					// kept off the form.
					array(
						'key'      => 'field_sb_emailsig_first_name',
						'label'    => 'First Name',
						'name'     => 'sb_emailsig_first_name',
						'type'     => 'text',
						'required' => 1,
						'wrapper'  => array( 'width' => '50' ),
					),
					array(
						'key'     => 'field_sb_emailsig_last_name',
						'label'   => 'Last Name',
						'name'    => 'sb_emailsig_last_name',
						'type'    => 'text',
						'wrapper' => array( 'width' => '50' ),
					),
					array(
						'key'      => 'field_sb_emailsig_name',
						'label'    => 'Name',
						'name'     => 'sb_emailsig_name',
						'type'     => 'text',
						'required' => 0,
						'wrapper'  => array( 'width' => '33' ),
					),
					// Qualifications and Job Title on a row of their own, three
					// lines each: each line break is a <br> in the signature, and
					// the Separator joins the lines where a break can't go (Sam,
					// 1 November 2026; SB_EmailSig_Data::one_line()).
					array(
						'key'       => 'field_sb_emailsig_qualifications',
						'label'     => 'Qualifications',
						'name'      => 'sb_emailsig_qualifications',
						'type'      => 'textarea',
						'rows'      => 3,
						'new_lines' => '',
						'wrapper'   => array( 'width' => '33.33' ),
					),
					array(
						'key'       => 'field_sb_emailsig_job_title',
						'label'     => 'Job Title',
						'name'      => 'sb_emailsig_job_title',
						'type'      => 'textarea',
						'rows'      => 3,
						'new_lines' => '',
						'wrapper'   => array( 'width' => '33.33' ),
					),
					array(
						'key'           => 'field_sb_emailsig_join',
						'label'         => 'Separator',
						'name'          => 'sb_emailsig_join',
						'type'          => 'select',
						'choices'       => SB_EmailSig_Data::join_choices(),
						'default_value' => 'comma',
						'allow_null'    => 0,
						'return_format' => 'value',
						'instructions'  => 'Joins the lines where a line break can\'t go.',
						'wrapper'       => array( 'width' => '33.33' ),
					),
					array(
						// Text, not email, so it can hold a token such as {email}
						// (SB_EmailSig_Staff::validate_email() checks it). Not
						// required (Sam, 1 November 2026): without one the
						// signature shows the company's Email.
						'key'      => 'field_sb_emailsig_email',
						'label'    => 'Email',
						'name'     => 'sb_emailsig_email',
						'type'     => 'text',
						'wrapper'  => array( 'width' => '50' ),
					),
					array(
						'key'          => 'field_sb_emailsig_mobile',
						'label'        => 'Mobile',
						'name'         => 'sb_emailsig_mobile',
						'type'         => 'text',
						'wrapper'      => array( 'width' => '50' ),
					),
					array(
						'key'          => 'field_sb_emailsig_person_info',
						'label'        => 'Additional Info List Items',
						'name'         => 'sb_emailsig_person_info',
						'type'         => 'repeater',
						'layout'       => 'block',
						'button_label' => 'Add Item',
						'instructions' => 'Extra Info List lines for this person. The template\'s layout says where they go.',
						'wrapper'      => array( 'class' => 'sb-emailsig-help-top' ),
						'sub_fields'   => self::info_sub_fields( 'person_info', 'sb_emailsig_person_info' ),
					),
					array(
						'key'           => 'field_sb_emailsig_person_info_hide',
						'label'         => 'Company Lines',
						'name'          => 'sb_emailsig_person_info_hide',
						'type'          => 'true_false',
						'message'       => 'Hide the company\'s Additional Info List Items for this person',
						'default_value' => 0,
					),
					array(
						'key'           => 'field_sb_emailsig_photo',
						'label'         => 'Photo',
						'name'          => 'sb_emailsig_photo',
						'type'          => 'image',
						'return_format' => 'id',
						'preview_size'  => 'thumbnail',
						'library'       => 'all',
						'mime_types'    => 'jpg,jpeg,png,webp',
						'instructions'  => 'Any size or shape. Set the crop in the Photo Crop box below.',
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => SB_EMAILSIG_CPT,
						),
					),
				),
				'position'              => 'acf_after_title',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'field',
				'menu_order'            => 0,
			)
		);
	}

	public static function settings_fields() {
		self::split_groups(
			array(
				'key'      => 'group_sb_emailsig_settings',
				'title'    => 'Signature Settings',
				'fields'   => array(

					// Company.
					array(
						'key'   => 'field_sb_emailsig_tab_company',
						'label' => 'Company',
						'type'  => 'tab',
					),
					// Company Info List Items (Sam, 31 October 2026: what the layout's Info List holds): name, phone and address, then website and maps
					// link; then Template Settings (Sam, 30 October 2026).
					self::sub_heading( 'field_sb_emailsig_head_company', 'Company Info List Items' ),
					array(
						'key'     => 'field_sb_emailsig_company_name',
						'label'   => 'Company Name',
						'name'    => 'sb_emailsig_company_name',
						'type'    => 'text',
						'wrapper' => array( 'width' => '25' ),
					),
					array(
						'key'          => 'field_sb_emailsig_phone',
						'label'        => 'Phone',
						'name'         => 'sb_emailsig_phone',
						'type'         => 'text',
						'wrapper'      => array( 'width' => '25' ),
					),
					// The company's Email (Sam, 1 November 2026): an Info List item
					// of its own, and the signature's Email for people without one.
					array(
						'key'          => 'field_sb_emailsig_company_email',
						'label'        => 'Email',
						'name'         => 'sb_emailsig_company_email',
						'type'         => 'text', // Text, so it can hold a shortcode (SB_EmailSig_Data::validate_company_email()).
						'wrapper'      => array( 'width' => '25' ),
					),
					array(
						'key'          => 'field_sb_emailsig_address',
						'label'        => 'Address',
						'name'         => 'sb_emailsig_address',
						'type'         => 'textarea',
						'rows'         => 3,
						'new_lines'    => '',
						'wrapper'      => array( 'width' => '25' ),
					),
					array(
						'key'          => 'field_sb_emailsig_website',
						'label'        => 'Website',
						'name'         => 'sb_emailsig_website',
						'type'         => 'url',
						'instructions' => 'Shown without https:// and www.',
						'wrapper'      => array( 'width' => '50' ),
					),
					array(
						'key'          => 'field_sb_emailsig_maps_link',
						'label'        => 'Google Maps Link',
						'name'         => 'sb_emailsig_maps_link',
						'type'         => 'url',
						'instructions' => 'Optional. Leave blank to link to a Google Maps search of the address.',
						'wrapper'      => array( 'width' => '50' ),
					),
					self::sub_heading( 'field_sb_emailsig_head_templates', 'Template Settings' ),
					// The company's Default Template and its set (company meta, SB_EmailSig_Templates).
					array(
						'key'           => 'field_sb_emailsig_main_template',
						'label'         => 'Default Template', // Was Main Template (Sam, 1 November 2026).
						'name'          => 'sb_emailsig_main_template',
						'type'          => 'select',
						'choices'       => array(),
						'allow_null'    => 0,
						'instructions'  => 'Used for everyone in this company.',
						'wrapper'       => array( 'width' => '50' ),
					),
					array(
						'key'           => 'field_sb_emailsig_templates',
						'label'         => 'Templates',
						'name'          => SB_EmailSig_Templates::COMPANY_SET,
						'type'          => 'checkbox',
						'choices'       => array(),
						'layout'        => 'vertical',
						'return_format' => 'value',
						'instructions'  => 'The ones people can pick on their signature page. The Default Template always is.',
						'wrapper'       => array( 'width' => '50' ),
					),
					// Team Pages: each company its own (SB_EmailSig_Staff; Sam, 30
					// October 2026: was one for the site, in Settings).
					self::sub_heading( 'field_sb_emailsig_head_team_pages', 'Team Pages' ),
					array(
						'key'           => 'field_sb_emailsig_staff_type',
						'label'         => 'Team Member Post Type',
						'name'          => 'sb_emailsig_staff_type',
						'type'          => 'select',
						'choices'       => array( '' => 'None' ),
						'default_value' => '',
						'allow_null'    => 0,
						'instructions'  => 'If the website has a page for each team member, link this company\'s people to theirs.',
						'wrapper'       => array( 'width' => '50' ),
					),
					// The staff page's ACF fields its people can use as merge tags
					// (SB_EmailSig_Staff::allowed(); choices from field_choices(),
					// settings.js shows the chosen post type's). {title} always works.
					array(
						'key'               => 'field_sb_emailsig_staff_fields',
						'label'             => 'Merge Tag Fields',
						'name'              => 'sb_emailsig_staff_fields',
						'type'              => 'checkbox',
						'choices'           => array(),
						'layout'            => 'vertical',
						'return_format'     => 'value',
						'instructions'      => 'The staff page fields people can use, such as {mobile}. {title} always works.',
						'wrapper'           => array( 'width' => '50' ),
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_sb_emailsig_staff_type',
									'operator' => '!=empty',
								),
							),
						),
					),

					// Branding (SB_EmailSig_Styles::brand()): the logo and the brand
					// colours every template uses unless it sets its own. Each
					// company has its own.
					array(
						'key'   => 'field_sb_emailsig_tab_branding',
						'label' => 'Branding',
						'type'  => 'tab',
					),
					array(
						'key'           => 'field_sb_emailsig_logo',
						'label'         => 'Company Logo',
						'name'          => 'sb_emailsig_logo',
						'type'          => 'image',
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'all',
						'mime_types'    => 'png,jpg,jpeg,gif',
						'instructions'  => 'PNG or JPG: email apps don\'t show SVG. Links to the website. Its size is set on the template\'s Edit styles page.',
						'wrapper'       => array( 'width' => '50' ),
					),
					array(
						// Its own block in the layout editor; blank shows nothing.
						'key'           => 'field_sb_emailsig_logo_small',
						'label'         => 'Small Logo',
						'name'          => 'sb_emailsig_logo_small',
						'type'          => 'image',
						'return_format' => 'id',
						'preview_size'  => 'thumbnail',
						'library'       => 'all',
						'mime_types'    => 'png,jpg,jpeg,gif',
						'instructions'  => 'Cropped to a square. At least 512 by 512 pixels is best.',
						'wrapper'       => array( 'width' => '50' ),
					),
					array(
						'key'          => 'field_sb_emailsig_brand_primary',
						'label'        => 'Brand Primary',
						'name'         => 'sb_emailsig_brand_primary',
						'type'         => 'text',
						'placeholder'  => '#000000',
						'instructions' => 'Buttons, icons, lines and the like.',
						'wrapper'      => array( 'width' => '25', 'class' => 'sb-emailsig-brand-colour' ),
					),
					array(
						'key'          => 'field_sb_emailsig_brand_accent',
						'label'        => 'Brand Accent',
						'name'         => 'sb_emailsig_brand_accent',
						'type'         => 'text',
						'placeholder'  => '',
						'instructions' => 'A second colour. Blank: the primary.',
						'wrapper'      => array( 'width' => '25', 'class' => 'sb-emailsig-brand-colour' ),
					),
					array(
						'key'          => 'field_sb_emailsig_brand_text',
						'label'        => 'Brand Text',
						'name'         => 'sb_emailsig_brand_text',
						'type'         => 'text',
						'placeholder'  => '#333333',
						'instructions' => 'The words.',
						'wrapper'      => array( 'width' => '25', 'class' => 'sb-emailsig-brand-colour' ),
					),
					array(
						'key'          => 'field_sb_emailsig_brand_text_light',
						'label'        => 'Brand Text Light',
						'name'         => 'sb_emailsig_brand_text_light',
						'type'         => 'text',
						'placeholder'  => '#777777',
						'instructions' => 'Quieter words, such as the footer text.',
						'wrapper'      => array( 'width' => '25', 'class' => 'sb-emailsig-brand-colour' ),
					),

					// Additional Content (Sam, 30 October 2026): the sign-off, the
					// Save to Contacts link and the company's Info List lines, moved
					// here from Footer and Company. Same fields, so nothing saved moves.
					array(
						'key'   => 'field_sb_emailsig_tab_content',
						'label' => 'Additional Content',
						'type'  => 'tab',
					),
					array(
						'key'           => 'field_sb_emailsig_signoff',
						'label'         => 'Sign-off',
						'name'          => 'sb_emailsig_signoff',
						'type'          => 'text',
						'default_value' => '',
						'placeholder'   => 'e.g. Kind Regards,',
						'instructions'  => 'Shown above the signature. Leave blank to hide.',
					),
					array(
						'key'           => 'field_sb_emailsig_vcard_show',
						'label'         => 'Save to Contacts Link',
						'name'          => 'sb_emailsig_vcard_show',
						'type'          => 'true_false',
						'ui'            => 1,
						'default_value' => 1,
						'wrapper'       => array( 'width' => '30' ),
					),
					array(
						'key'               => 'field_sb_emailsig_vcard_label',
						'label'             => 'Link Text',
						'name'              => 'sb_emailsig_vcard_label',
						'type'              => 'text',
						'default_value'     => 'Save my contact details',
						'wrapper'           => array( 'width' => '70' ),
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_sb_emailsig_vcard_show',
									'operator' => '==',
									'value'    => '1',
								),
							),
						),
					),
					array(
						'key'          => 'field_sb_emailsig_info',
						'label'        => 'Additional Info List Items',
						'name'         => 'sb_emailsig_info',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Add Item',
						'instructions' => 'Extra Info List lines for everyone, such as opening hours. The label names the line in Reports. A person can add their own or hide these.',
						'wrapper'      => array( 'class' => 'sb-emailsig-help-top' ),
						'sub_fields'   => self::info_sub_fields( 'info', 'sb_emailsig_info' ),
					),

					// Social media.
					array(
						'key'   => 'field_sb_emailsig_tab_social',
						'label' => 'Social Media',
						'type'  => 'tab',
					),
					array(
						'key'          => 'field_sb_emailsig_social_preview',
						'label'        => 'Preview',
						'name'         => '',
						'type'         => 'message',
						'new_lines'    => '',
						'message'      => 'Displayed in the template\'s icon style, set on its Edit styles page.',
					),
					array(
						'key'          => 'field_sb_emailsig_social_heading',
						'label'        => 'Heading',
						'name'         => 'sb_emailsig_social_heading',
						'type'         => 'text',
						'placeholder'  => 'e.g. Follow us:',
						'instructions' => 'Optional. Shown above the icons, styled on the template\'s Edit styles page.',
					),
					array(
						'key'          => 'field_sb_emailsig_social_media',
						'label'        => 'Social Media',
						'name'         => 'sb_emailsig_social_media',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Add Social Link',
						'sub_fields'   => array(
							// Icon first: picking one fills an empty Name (assets/autolabel.js).
							array(
								'key'           => 'field_sb_emailsig_social_media_icon',
								'label'         => 'Icon',
								'name'          => 'social_media_icon',
								'type'          => 'select',
								'choices'       => self::icon_choices( false ),
								'default_value' => 'facebook',
								'wrapper'       => array( 'width' => '20' ),
							),
							array(
								// An SVG from the media library (28 October 2026: was pasted code).
								// Beside Icon, its space always there and empty until the icon is
								// Custom SVG (admin.css; see info_sub_fields(), Sam, 2 November 2026).
								'key'           => 'field_sb_emailsig_social_media_icon_file',
								'label'         => 'Custom SVG',
								'name'          => 'social_media_icon_file',
								'type'          => 'file',
								'return_format' => 'id',
								'library'       => 'all',
								'mime_types'    => 'svg',
								'wrapper'       => array( 'width' => '15' ),
							),
							array(
								'key'      => 'field_sb_emailsig_social_media_name',
								'label'    => 'Name',
								'name'     => 'social_media_name',
								'type'     => 'text',
								'required' => 1,
								'wrapper'  => array( 'width' => '20' ),
							),
							array(
								'key'      => 'field_sb_emailsig_social_media_link',
								'label'    => 'Link',
								'name'     => 'social_media_link',
								'type'     => 'url',
								'required' => 1,
								// Takes what's left.
								'wrapper'  => array( 'width' => '' ),
							),
							self::row_id_field( 'sb_emailsig_social_media' ),
						),
					),

					// Footer (the key keeps its old name, so nothing saved moves).
					array(
						'key'   => 'field_sb_emailsig_tab_signature',
						'label' => 'Footer',
						'type'  => 'tab',
					),
					array(
						'key'          => 'field_sb_emailsig_buttons',
						'label'        => 'Additional Buttons',
						'name'         => 'sb_emailsig_buttons',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Add Button',
						'instructions' => 'Shown in one row, such as Book Online. Styled on the template\'s Edit styles page.',
						'wrapper'      => array( 'class' => 'sb-emailsig-help-top' ),
						'sub_fields'   => array(
							// An icon before the label (Sam, 2 November 2026): the Info List's icons, None by default.
							array(
								'key'           => 'field_sb_emailsig_button_icon',
								'label'         => 'Icon',
								'name'          => 'button_icon',
								'type'          => 'select',
								'choices'       => self::icon_choices(),
								'default_value' => '',
								'wrapper'       => array( 'width' => '15' ),
							),
							array(
								// As on Social Media: beside Icon, its space always there and empty
								// until the icon is Custom SVG (admin.css, Sam, 2 November 2026).
								'key'           => 'field_sb_emailsig_button_icon_file',
								'label'         => 'Custom SVG',
								'name'          => 'button_icon_file',
								'type'          => 'file',
								'return_format' => 'id',
								'library'       => 'all',
								'mime_types'    => 'svg',
								'wrapper'       => array( 'width' => '12' ),
							),
							array(
								'key'      => 'field_sb_emailsig_button_label',
								'label'    => 'Label',
								'name'     => 'button_label',
								'type'     => 'text',
								'required' => 1,
								'wrapper'  => array( 'width' => '18' ),
							),
							array(
								'key'      => 'field_sb_emailsig_button_link',
								'label'    => 'Link',
								'name'     => 'button_link',
								'type'     => 'url',
								'required' => 1,
								// Takes what's left.
								'wrapper'  => array( 'width' => '' ),
							),
							array(
								// This button's own colour (Sam, 2 November 2026): blank is the
								// template's. The swatch and colour menu are settings.js's, as
								// the brand colours'. Styles::button_look() uses it.
								'key'         => 'field_sb_emailsig_button_colour',
								'label'       => 'Colour',
								'name'        => 'button_colour',
								'type'        => 'text',
								'placeholder' => 'Template',
								'wrapper'     => array( 'width' => '15', 'class' => 'sb-emailsig-button-colour' ),
							),
							array(
								'key'     => 'field_sb_emailsig_button_outline',
								'label'   => 'Outline',
								'name'    => 'button_outline',
								'type'    => 'true_false',
								'ui'      => 1,
								'wrapper' => array( 'width' => '10' ),
							),
							self::row_id_field( 'sb_emailsig_buttons' ),
						),
					),
					array(
						// Bold, italic and links only (Sam, 2 November 2026): the SB Footer
						// toolbar, anything else taken out on save (footer_value()).
						'key'          => 'field_sb_emailsig_fine_print',
						'label'        => 'Footer Text',
						'name'         => 'sb_emailsig_fine_print',
						'type'         => 'wysiwyg',
						'toolbar'      => 'sb_footer',
						'tabs'         => 'visual',
						'media_upload' => 0,
						'delay'        => 0,
						'instructions' => 'Optional. Small text under the bottom line, e.g. a confidentiality notice. Bold, italic and links only.',
						// New companies start with this (Sam, 2 November 2026); saved text is kept.
						'default_value' => 'Confidentiality Notice: This email and any attachments are confidential and intended only for the named recipient. If you have received it in error, please let the sender know and delete it, including any copies. Any unauthorised use, copying or distribution is prohibited. We handle personal information in line with our privacy policy and applicable privacy laws. While we take care to keep our emails free of viruses, we can\'t accept responsibility for any loss or damage arising from this email or its attachments.',
					),

					// Banners: the campaign banner (SB_EmailSig_Campaign), then the
					// Additional Banners (moved here from Footer, Sam, 2 November 2026;
					// the tab key keeps its old name, so nothing saved moves).
					array(
						'key'   => 'field_sb_emailsig_tab_campaign',
						'label' => 'Banners',
						'type'  => 'tab',
					),
					array(
						'key'           => 'field_sb_emailsig_campaign_show',
						'label'         => 'Campaign Banner',
						'name'          => 'sb_emailsig_campaign_show',
						'type'          => 'true_false',
						'ui'            => 1,
						'default_value' => 0,
						'instructions'  => 'A banner above any others that you can change here any time, even in signatures already in use.',
						'wrapper'       => array( 'width' => '50' ),
					),
					array(
						'key'               => 'field_sb_emailsig_campaign_width',
						'label'             => 'Width',
						'name'              => 'sb_emailsig_campaign_width',
						'type'              => 'number',
						'default_value'     => SB_EmailSig_Campaign::WIDTH,
						'min'               => 100,
						'max'               => 1200,
						'step'              => 1,
						'append'            => 'px',
						'instructions'      => '', // With the best image size: SB_EmailSig_Fields::campaign_help().
						'wrapper'           => array( 'width' => '50' ),
						'conditional_logic' => self::when_campaign(),
					),
					array(
						'key'               => 'field_sb_emailsig_campaign_now',
						'label'             => '',
						'name'              => '',
						'type'              => 'message',
						'new_lines'         => '',
						'message'           => '',
						'conditional_logic' => self::when_campaign(),
					),
					array(
						'key'               => 'field_sb_emailsig_campaign_default_image',
						'label'             => 'Default Banner',
						'name'              => 'sb_emailsig_campaign_default_image',
						'type'              => 'image',
						'return_format'     => 'id',
						'preview_size'      => 'medium',
						'library'           => 'all',
						'mime_types'        => 'png,jpg,jpeg,gif',
						'instructions'      => 'Shown when no campaign is running. Leave empty to show nothing.',
						'wrapper'           => array( 'width' => '50' ),
						'conditional_logic' => self::when_campaign(),
					),
					array(
						'key'               => 'field_sb_emailsig_campaign_default_link',
						'label'             => 'Default Link',
						'name'              => 'sb_emailsig_campaign_default_link',
						'type'              => 'url',
						'wrapper'           => array( 'width' => '50' ),
						'conditional_logic' => self::when_campaign(),
					),
					array(
						'key'               => 'field_sb_emailsig_campaigns',
						'label'             => 'Campaigns',
						'name'              => 'sb_emailsig_campaigns',
						'type'              => 'repeater',
						'layout'            => 'table',
						'button_label'      => 'Add Campaign',
						'instructions'      => 'The first one running today shows. Leave a date blank for no start or no end.',
						'conditional_logic' => self::when_campaign(),
						'sub_fields'        => array(
							array(
								'key'     => 'field_sb_emailsig_campaign_name',
								'label'   => 'Name',
								'name'    => 'campaign_name',
								'type'    => 'text',
								'wrapper' => array( 'width' => '18' ),
							),
							array(
								'key'           => 'field_sb_emailsig_campaign_image',
								'label'         => 'Banner',
								'name'          => 'campaign_image',
								'type'          => 'image',
								'required'      => 1,
								'return_format' => 'id',
								'preview_size'  => 'medium',
								'library'       => 'all',
								'mime_types'    => 'png,jpg,jpeg,gif',
								'wrapper'       => array( 'width' => '26' ),
							),
							array(
								'key'     => 'field_sb_emailsig_campaign_link',
								'label'   => 'Link',
								'name'    => 'campaign_link',
								'type'    => 'url',
								'wrapper' => array( 'width' => '26' ),
							),
							array(
								'key'            => 'field_sb_emailsig_campaign_start',
								'label'          => 'Starts',
								'name'           => 'campaign_start',
								'type'           => 'date_picker',
								'display_format' => 'j M Y',
								'return_format'  => 'Ymd',
								'first_day'      => 1,
								'wrapper'        => array( 'width' => '15' ),
							),
							array(
								'key'            => 'field_sb_emailsig_campaign_end',
								'label'          => 'Ends',
								'name'           => 'campaign_end',
								'type'           => 'date_picker',
								'display_format' => 'j M Y',
								'return_format'  => 'Ymd',
								'first_day'      => 1,
								'wrapper'        => array( 'width' => '15' ),
							),
						),
					),

					array(
						'key'          => 'field_sb_emailsig_banners',
						'label'        => 'Additional Banners',
						'name'         => 'sb_emailsig_banners',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Add Banner',
						'instructions' => '', // Its size advice: SB_EmailSig_Fields::banner_help().
						'wrapper'      => array( 'class' => 'sb-emailsig-help-top' ),
						'sub_fields'   => array(
							array(
								'key'           => 'field_sb_emailsig_banner_image',
								'label'         => 'Image',
								'name'          => 'banner_image',
								'type'          => 'image',
								'required'      => 1,
								'return_format' => 'id',
								'preview_size'  => 'medium',
								'library'       => 'all',
								'mime_types'    => 'png,jpg,jpeg,gif',
								'wrapper'       => array( 'width' => '40' ),
							),
							array(
								'key'     => 'field_sb_emailsig_banner_link',
								'label'   => 'Link',
								'name'    => 'banner_link',
								'type'    => 'url',
								'wrapper' => array( 'width' => '60' ),
							),
							self::row_id_field( 'sb_emailsig_banners' ),
						),
					),

					// Tracking (SB_EmailSig_Tracking): Reports, then Google Analytics
					// (Sam, 30 October 2026: was Tracking Codes, with Track Clicks).
					array(
						'key'   => 'field_sb_emailsig_tab_tracking',
						'label' => 'Tracking',
						'type'  => 'tab',
					),
					self::sub_heading( 'field_sb_emailsig_head_reports', 'Reports' ),
					array(
						'key'           => 'field_sb_emailsig_track_clicks',
						'label'         => 'Enable Reporting',
						'name'          => 'sb_emailsig_track_clicks',
						'type'          => 'true_false',
						'ui'            => 1,
						'default_value' => 1,
						'instructions'  => 'Links go through this website, which counts the click and sends people on. Changing a link here changes it in signatures already in use.',
						'wrapper'       => array( 'width' => '50' ),
					),
					array(
						'key'               => 'field_sb_emailsig_keep_clicks',
						// How long Reports keeps clicks: older ones are deleted (Sam, 30 October 2026: was Keep Clicks For).
						'label'             => 'Keep Report Logs For',
						'name'              => 'sb_emailsig_keep_clicks',
						'type'              => 'select',
						'choices'           => array(
							'6'  => '6 months',
							'12' => '12 months',
							'24' => '24 months',
							'36' => '36 months',
						),
						'default_value'     => (string) SB_EmailSig_Tracking::KEEP,
						'wrapper'           => array( 'width' => '50' ),
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_sb_emailsig_track_clicks',
									'operator' => '==',
									'value'    => '1',
								),
							),
						),
					),
					self::sub_heading( 'field_sb_emailsig_head_utm', 'Google Analytics (UTM)' ),
					array(
						'key'           => 'field_sb_emailsig_utm_on',
						'label'         => 'Add Tracking Codes',
						'name'          => 'sb_emailsig_utm_on',
						'type'          => 'true_false',
						'ui'            => 1,
						'default_value' => 1,
						'instructions'  => 'UTM codes on links to your website, so analytics shows visits from signatures.',
						'wrapper'       => array( 'width' => '50' ),
					),
					array(
						'key'               => 'field_sb_emailsig_utm_all',
						'label'             => 'Other Websites Too',
						'name'              => 'sb_emailsig_utm_all',
						'type'              => 'true_false',
						'ui'                => 1,
						'default_value'     => 0,
						'instructions'      => 'Also tag links to social media, Google Maps and other websites.',
						'wrapper'           => array( 'width' => '50' ),
						'conditional_logic' => self::when_tracking(),
					),
					array(
						'key'               => 'field_sb_emailsig_utm_source',
						'label'             => 'Source (utm_source)',
						'name'              => 'sb_emailsig_utm_source',
						'type'              => 'text',
						'default_value'     => SB_EmailSig_Tracking::DEFAULTS['source'],
						'wrapper'           => array( 'width' => '50' ),
						'conditional_logic' => self::when_tracking(),
					),
					array(
						'key'               => 'field_sb_emailsig_utm_medium',
						'label'             => 'Medium (utm_medium)',
						'name'              => 'sb_emailsig_utm_medium',
						'type'              => 'text',
						'default_value'     => SB_EmailSig_Tracking::DEFAULTS['medium'],
						'wrapper'           => array( 'width' => '50' ),
						'conditional_logic' => self::when_tracking(),
					),
					array(
						'key'               => 'field_sb_emailsig_utm_campaign',
						'label'             => 'Campaign (utm_campaign)',
						'name'              => 'sb_emailsig_utm_campaign',
						'type'              => 'text',
						'default_value'     => SB_EmailSig_Tracking::DEFAULTS['campaign'],
						'wrapper'           => array( 'width' => '50' ),
						'conditional_logic' => self::when_tracking(),
					),
					array(
						'key'               => 'field_sb_emailsig_utm_content',
						'label'             => 'Content (utm_content)',
						'name'              => 'sb_emailsig_utm_content',
						'type'              => 'text',
						'default_value'     => SB_EmailSig_Tracking::DEFAULTS['content'],
						'wrapper'           => array( 'width' => '50' ),
						'conditional_logic' => self::when_tracking(),
					),
					// Every code the boxes above can hold, click to copy (Tracking::render_codes()).
					array(
						'key'               => 'field_sb_emailsig_utm_codes',
						'label'             => 'Codes',
						'name'              => '',
						'type'              => 'message',
						'new_lines'         => '',
						'message'           => '',
						'instructions'      => 'Click one to copy it, then paste it into a box above.',
						'conditional_logic' => self::when_tracking(),
					),
					// Back to the defaults, shown only when they differ (Tracking::render_reset()).
					array(
						'key'       => 'field_sb_emailsig_utm_reset',
						'label'     => '',
						'name'      => '',
						'type'      => 'message',
						'new_lines' => '',
						'message'   => '',
						'wrapper'   => array( 'class' => 'sb-emailsig-utm-reset-field' ),
					),

					// Emails (SB_EmailSig_Notify).
					array(
						'key'   => 'field_sb_emailsig_tab_mail',
						'label' => 'Signature Emails',
						'type'  => 'tab',
					),
					array(
						'key'          => 'field_sb_emailsig_mail_from_name',
						'label'        => 'From Name',
						'name'         => 'sb_emailsig_mail_from_name',
						'type'         => 'text',
						'instructions' => 'Leave blank for the site name.',
						'wrapper'      => array( 'width' => '33' ),
					),
					array(
						// The part before the @ only: the site's domain is added
						// (SB_EmailSig_Notify::from_field() shows it after the box).
						'key'          => 'field_sb_emailsig_mail_from_email',
						'label'        => 'From Email',
						'name'         => 'sb_emailsig_mail_from_email',
						'type'         => 'text',
						'placeholder'  => 'noreply',
						'instructions' => 'Leave blank for noreply.',
						'wrapper'      => array( 'width' => '33' ),
					),
					// {sender} (Sam, 1 November 2026): this name when set, else whoever sends it.
					array(
						'key'          => 'field_sb_emailsig_mail_sender',
						'label'        => 'Default Sender Name',
						'name'         => 'sb_emailsig_mail_sender',
						'type'         => 'text',
						'instructions' => 'Leave blank for whoever sends it.',
						'wrapper'      => array( 'width' => '33' ),
					),
					array(
						'key'          => 'field_sb_emailsig_mail_tags',
						'label'        => 'Merge Tags',
						'name'         => '',
						'type'         => 'message',
						'new_lines'    => '',
						'message'      => '',
						'instructions' => 'Click one to copy it, then paste it into the From Name, a subject or a message.',
					),
					// New Signature as a card (Sam, 31 October 2026): a group named so its
					// fields keep their option names (sb_emailsig_mail_new_subject and
					// _message) and keys; SB_EmailSig_Notify::settings() reads it by key.
					array(
						'key'        => 'field_sb_emailsig_mail_new',
						'label'      => 'New Signature',
						'name'       => 'sb_emailsig_mail_new',
						'type'       => 'group',
						'layout'     => 'block',
						'wrapper'    => array(
							'width' => '50',
							'class' => 'sb-emailsig-card-field',
						),
						'sub_fields' => array(
							array(
								'key'           => 'field_sb_emailsig_mail_new_subject',
								'label'         => 'Subject',
								'name'          => 'subject',
								'type'          => 'text',
								'default_value' => SB_EmailSig_Notify::TEXT['new_subject'],
							),
							array(
								'key'           => 'field_sb_emailsig_mail_new_message',
								'label'         => 'Message',
								'name'          => 'message',
								'type'          => 'textarea',
								'rows'          => 9,
								'new_lines'     => '',
								'default_value' => SB_EmailSig_Notify::TEXT['new_message'],
								'instructions'  => 'Sent by Email link.',
								'wrapper'       => array( 'class' => 'sb-emailsig-card-field__last' ),
							),
						),
					),
					// Updated Signature as a card (Sam, 31 October 2026): a group named so its
					// fields keep their option names (sb_emailsig_mail_updated_subject and
					// _message) and keys; SB_EmailSig_Notify::settings() reads it by key.
					array(
						'key'        => 'field_sb_emailsig_mail_updated',
						'label'      => 'Updated Signature',
						'name'       => 'sb_emailsig_mail_updated',
						'type'       => 'group',
						'layout'     => 'block',
						'wrapper'    => array(
							'width' => '50',
							'class' => 'sb-emailsig-card-field',
						),
						'sub_fields' => array(
							array(
								'key'           => 'field_sb_emailsig_mail_updated_subject',
								'label'         => 'Subject',
								'name'          => 'subject',
								'type'          => 'text',
								'default_value' => SB_EmailSig_Notify::TEXT['updated_subject'],
							),
							array(
								'key'           => 'field_sb_emailsig_mail_updated_message',
								'label'         => 'Message',
								'name'          => 'message',
								'type'          => 'textarea',
								'rows'          => 9,
								'new_lines'     => '',
								'default_value' => SB_EmailSig_Notify::TEXT['updated_message'],
								'instructions'  => 'Sent by Email link, for someone sent their link before.',
								'wrapper'       => array( 'class' => 'sb-emailsig-card-field__last' ),
							),
						),
					),

					// Admin Branding (Sam, 31 October 2026): the site's own logos, as a
					// company has. The small one heads the every-company team page;
					// it is square like a company's (SB_EmailSig_Logo).
					array(
						'key'   => 'field_sb_emailsig_tab_admin_branding',
						'label' => 'Admin Branding',
						'type'  => 'tab',
					),
					array(
						'key'           => 'field_sb_emailsig_admin_logo',
						'label'         => 'Logo',
						'name'          => 'sb_emailsig_admin_logo',
						'type'          => 'image',
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'all',
						'mime_types'    => 'png,jpg,jpeg,gif',
						'instructions'  => 'PNG or JPG.',
						'wrapper'       => array( 'width' => '50' ),
					),
					array(
						'key'           => 'field_sb_emailsig_admin_logo_small',
						'label'         => 'Small Logo',
						'name'          => 'sb_emailsig_admin_logo_small',
						'type'          => 'image',
						'return_format' => 'id',
						'preview_size'  => 'thumbnail',
						'library'       => 'all',
						'mime_types'    => 'png,jpg,jpeg,gif',
						'instructions'  => 'Cropped to a square. At least 512 by 512 pixels is best. Shown on the every-company team page.',
						'wrapper'       => array( 'width' => '50' ),
					),
				),
				'location' => array(
					array(
						array(
							// Shown through acf_form() on the Settings page, which names
							// this group, so it needs no location of its own.
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => 'sb-emailsig-acf-form-only',
						),
					),
				),
				'style'                 => 'seamless',
				'instruction_placement' => 'field',
			)
		);
	}

	/** Settings' own tabs, in this order (Sam, 31 October 2026). */
	const SITE_TABS = array( 'Admin Branding', 'Admin Notifications', 'Signature Emails', 'Tracking' );

	/** Tabs that belong to a company (SB_EmailSig_Company); the rest stay in Settings. */
	const COMPANY_TABS = array( 'Company', 'Branding', 'Additional Content', 'Social Media', 'Footer', 'Banners' );

	/**
	 * The settings fields as two groups (Sam, 30 October 2026): a company's
	 * (SB_EmailSig_Company::GROUP: the COMPANY_TABS), edited on its page, and
	 * the site's own in Settings (Admin Branding, Admin Notifications,
	 * Signature Emails, Tracking). Team pages and Team Member Post Type are a
	 * company's (Company tab). The fields keep their keys and names, so
	 * values read the same from a company's post as from the options.
	 */
	private static function split_groups( array $group ) {
		$company = array();
		$site    = array();
		$into    = 'company';

		// The Branding tab's Info List Icons go after the brand colours.
		$fields = array();

		foreach ( $group['fields'] as $field ) {
			$fields[] = $field;

			if ( ( $field['key'] ?? '' ) === 'field_sb_emailsig_brand_text_light' ) {
				$fields = array_merge( $fields, self::line_icon_fields() );
			}
		}

		foreach ( $fields as $field ) {
			if ( ( $field['type'] ?? '' ) === 'tab' ) {
				$into = in_array( $field['label'], self::COMPANY_TABS, true ) ? 'company' : 'site';
			}

			if ( $into === 'company' ) {
				$company[] = $field;
			} else {
				$site[] = $field;
			}
		}

		acf_add_local_field_group(
			array_merge(
				$group,
				array(
					'key'    => SB_EmailSig_Company::GROUP,
					'title'  => 'Company',
					'fields' => $company,
				)
			)
		);

		// Admin Notifications: a section per notice (SB_EmailSig_AdminMail).
		$site = array_merge( $site, SB_EmailSig_AdminMail::fields() );

		// Settings' tabs in SITE_TABS order; any other keeps its place after them.
		$tabs  = array();
		$label = '';
		foreach ( $site as $field ) {
			if ( ( $field['type'] ?? '' ) === 'tab' ) {
				$label = $field['label'];
			}
			$tabs[ $label ][] = $field;
		}
		uksort(
			$tabs,
			function ( $a, $b ) {
				$x = array_search( $a, self::SITE_TABS, true );
				$y = array_search( $b, self::SITE_TABS, true );

				return ( $x === false ? 99 : $x ) <=> ( $y === false ? 99 : $y );
			}
		);
		$site = $tabs ? array_merge( ...array_values( $tabs ) ) : array();

		acf_add_local_field_group( array_merge( $group, array( 'fields' => $site ) ) );
	}
}
