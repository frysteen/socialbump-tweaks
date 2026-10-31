<?php
/**
 * Pulls a person's fields and the shared settings into one clean array
 * for the template and the vCard.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Data {

	/**
	 * $tpl_id: the template the data is for (its social icons), '' for the
	 * person's main. Read in the person's company (SB_EmailSig_Company; the
	 * default one for 0), whose id comes back as company_id.
	 */
	public static function get( $post_id, $tpl_id = '' ) {
		if ( ! class_exists( 'SB_EmailSig_Company' ) ) {
			return self::build( $post_id, $tpl_id );
		}

		$co = SB_EmailSig_Company::of_person( (int) $post_id );

		return SB_EmailSig_Company::with(
			$co,
			function () use ( $post_id, $tpl_id, $co ) {
				return array( 'company_id' => $co ) + self::build( $post_id, $tpl_id );
			}
		);
	}

	/** Where the company fields are read: the company being drawn. */
	private static function co_src() {
		return class_exists( 'SB_EmailSig_Company' ) ? SB_EmailSig_Company::source() : 'option';
	}

	private static function build( $post_id, $tpl_id = '' ) {
		$post_id = (int) $post_id;
		$o       = self::co_src();
		$tpl_id  = $tpl_id !== '' ? $tpl_id : SB_EmailSig_Templates::for_person( $post_id );
		$tpl     = SB_EmailSig_Templates::get( $tpl_id );
		$tpl_id  = $tpl ? $tpl['id'] : $tpl_id;

		$website = trim( (string) get_field( 'sb_emailsig_website', $o ) );

		// The address as typed, one entry per line.
		$lines = self::address_lines();
		$one   = implode( ', ', $lines );

		$phone  = trim( (string) get_field( 'sb_emailsig_phone', $o ) );
		$mobile = trim( (string) get_field( 'sb_emailsig_mobile', $post_id ) );

		// Every link goes through SB_EmailSig_Tracking: a short link that
		// counts the click, or the address with UTM codes.
		$name    = trim( (string) get_field( 'sb_emailsig_name', $post_id ) );
		$targets = self::targets();
		$link    = SB_EmailSig_Tracking::linker( $post_id, $name, $tpl_id );
		$go      = function ( $key ) use ( $targets, $link ) {
			return isset( $targets[ $key ] ) ? $link( $key, $targets[ $key ]['url'], $targets[ $key ]['name'] ) : '';
		};

		$vcard_show = get_field( 'sb_emailsig_vcard_show', $o );
		$vcard_show = ( null === $vcard_show ) ? true : (bool) $vcard_show;
		$signoff    = get_field( 'sb_emailsig_signoff', $o );
		$signoff    = ( null === $signoff ) ? 'Kind Regards,' : trim( (string) $signoff );
		$info       = self::info( $post_id, $targets, $go, $link );

		return array(
			'id'            => $post_id,
			'template'      => $tpl_id,
			'preview'       => false,
			'name'          => $name,
			'first_name'    => trim( (string) get_field( 'sb_emailsig_first_name', $post_id ) ),
			'last_name'     => trim( (string) get_field( 'sb_emailsig_last_name', $post_id ) ),
			'qualifications' => trim( (string) get_field( 'sb_emailsig_qualifications', $post_id ) ),
			'job_title'     => trim( (string) get_field( 'sb_emailsig_job_title', $post_id ) ),
			'email'         => trim( (string) get_field( 'sb_emailsig_email', $post_id ) ),
			'mobile'        => $mobile,
			'mobile_link'   => self::tel( $mobile ),
			'photo'         => null, // Made for the template by SB_EmailSig_Render.

			'company'       => trim( (string) get_field( 'sb_emailsig_company_name', $o ) ),
			'phone'         => $phone,
			'phone_link'    => self::tel( $phone ),
			'website'       => $website, // As typed, for the vCard. Link with website_link.
			'website_link'  => $go( 'w' ),
			'website_label' => preg_replace( '#^(https?://)?(www\.)?#i', '', untrailingslashit( $website ) ),
			'address'       => $one,
			'address_lines' => $lines,
			'maps'          => $go( 'm' ),
			// The Additional Info List Items: both together, and each on its
			// own for the layout's Info Lists.
			'info'          => array_merge( $info['company'], $info['person'] ),
			'info_company'  => $info['company'],
			'info_person'   => $info['person'],
			'socials'       => self::socials( $tpl_id, $targets, $go ),
			'social_heading' => trim( (string) get_field( 'sb_emailsig_social_heading', $o ) ),
			'logo'          => self::image( (int) get_field( 'sb_emailsig_logo', $o, false ) ),
			'logo_small'    => self::image( (int) get_field( 'sb_emailsig_logo_small', $o, false ) ),
			'logo_link'     => $go( 'l' ), // The logo links to the website, or nowhere without one.
			'company_link'  => $go( 'o' ), // So does the company name.
			'buttons'       => self::buttons( $targets, $go ),
			'campaign'      => SB_EmailSig_Campaign::block( $post_id > 0 && SB_EmailSig_Tracking::settings()['clicks'] ? SB_EmailSig_Tracking::go_url( $post_id, 'c', $tpl_id ) : $go( 'c' ) ),
			'banners'       => self::banners( $targets, $go ),
			'signoff'       => $signoff,
			'vcard_show'    => $vcard_show,
			'vcard_label'   => trim( (string) get_field( 'sb_emailsig_vcard_label', $o ) ) ?: 'Save my contact details',
			'vcard_url'     => SB_EmailSig_VCard::url( $post_id, $tpl_id ),
			'fine_print'    => trim( (string) get_field( 'sb_emailsig_fine_print', $o ) ),
			// Their staff page (SB_EmailSig_Staff), for the photo and name.
			'profile_link'  => $link( 'p', SB_EmailSig_Staff::page_url( $post_id ), 'profile' ),
		);
	}

	/** The Address setting, one entry per line as typed. */
	private static function address_lines() {
		$address = (string) get_field( 'sb_emailsig_address', self::co_src(), false );

		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $address ) ) ) );
	}

	/**
	 * Every link the signature can have, by the key its short link carries
	 * (SB_EmailSig_Tracking): w website, l logo, o company name, m map, then s, b and n with
	 * the social, button or banner row's fixed id (i and its place for a row
	 * saved before rows had ids), and c for the campaign banner (whatever
	 * runs today: SB_EmailSig_Campaign). Each: kind, url (as set; '' for
	 * none), name (for utm_content), label (for the report), row (its place).
	 */
	public static function targets() {
		$o       = self::co_src();
		$website = trim( (string) get_field( 'sb_emailsig_website', $o ) );

		$maps = trim( (string) get_field( 'sb_emailsig_maps_link', $o ) );
		$one  = implode( ', ', self::address_lines() );
		if ( ! $maps && $one ) {
			$maps = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $one );
		}

		$t = array(
			'w' => array( 'kind' => 'w', 'url' => $website, 'name' => 'website', 'label' => 'Website', 'row' => 0 ),
			'l' => array( 'kind' => 'l', 'url' => $website, 'name' => 'logo', 'label' => 'Logo', 'row' => 0 ),
			'o' => array( 'kind' => 'o', 'url' => $website, 'name' => 'company', 'label' => 'Company name', 'row' => 0 ),
			'm' => array( 'kind' => 'm', 'url' => $maps, 'name' => 'map', 'label' => 'Map', 'row' => 0 ),
		);

		$now      = SB_EmailSig_Campaign::current();
		$t['c']   = array(
			'kind'  => 'c',
			'url'   => $now ? $now['link'] : '',
			'name'  => $now ? $now['name'] : 'campaign',
			'label' => $now ? $now['label'] : 'Campaign banner',
			'row'   => 0,
		);

		foreach ( array_values( (array) get_field( 'sb_emailsig_social_media', $o ) ) as $i => $row ) {
			$name = trim( (string) ( $row['social_media_name'] ?? '' ) );
			$name = $name !== '' ? $name : 'Social ' . ( $i + 1 );

			$t[ 's' . self::ref( $row['social_media_id'] ?? '', $i ) ] = array(
				'kind'  => 's',
				'url'   => trim( (string) ( $row['social_media_link'] ?? '' ) ),
				'name'  => $name,
				'label' => $name,
				'row'   => $i,
			);
		}

		foreach ( array_values( (array) get_field( 'sb_emailsig_buttons', $o ) ) as $i => $row ) {
			$label = trim( (string) ( $row['button_label'] ?? '' ) );

			$t[ 'b' . self::ref( $row['button_id'] ?? '', $i ) ] = array(
				'kind'  => 'b',
				'url'   => trim( (string) ( $row['button_link'] ?? '' ) ),
				'name'  => 'button-' . $label,
				'label' => $label !== '' ? $label : 'Button ' . ( $i + 1 ),
				'row'   => $i,
			);
		}

		// Unformatted rows, keyed by field key.
		foreach ( array_values( (array) get_field( 'sb_emailsig_banners', $o, false ) ) as $i => $row ) {
			$t[ 'n' . self::ref( $row['field_sb_emailsig_banner_id'] ?? ( $row['banner_id'] ?? '' ), $i ) ] = array(
				'kind'  => 'n',
				'url'   => trim( (string) ( $row['field_sb_emailsig_banner_link'] ?? ( $row['banner_link'] ?? '' ) ) ),
				'name'  => 'banner-' . ( $i + 1 ),
				'label' => 'Banner ' . ( $i + 1 ),
				'row'   => $i,
			);
		}

		// Additional Info List Items with a link.
		foreach ( self::info_rows( 'option' ) as $row ) {
			if ( $row['link'] !== '' ) {
				$t[ 'a' . $row['ref'] ] = array(
					'kind'  => 'a',
					'url'   => $row['link'],
					'name'  => 'info-' . $row['label'],
					'label' => $row['label'],
					'row'   => $row['i'],
				);
			}
		}

		return $t;
	}

	/**
	 * A person's own Additional Info List Item links, by key (q and the row's
	 * id), for SB_EmailSig_Tracking to find when one is clicked.
	 */
	public static function person_targets( $post_id ) {
		$t = array();

		foreach ( self::info_rows( (int) $post_id ) as $row ) {
			if ( $row['link'] !== '' ) {
				$t[ 'q' . $row['ref'] ] = array(
					'kind'  => 'q',
					'url'   => $row['link'],
					'name'  => 'info-' . $row['label'],
					'label' => $row['label'],
					'row'   => $row['i'],
				);
			}
		}

		return $t;
	}

	/**
	 * Additional Info List Items with text, from Settings ('option') or a
	 * person (their id): i (place), ref (fixed id), icon, label, text, link.
	 */
	private static function info_rows( $from ) {
		$name  = $from === 'option' ? 'sb_emailsig_info' : 'sb_emailsig_person_info';
		$rows  = $from === 'option' ? get_field( $name, self::co_src() ) : ( $from > 0 ? get_field( $name, $from ) : array() );
		$icons = SB_EmailSig_Fields::info_icons();
		$out   = array();

		foreach ( array_values( is_array( $rows ) ? $rows : array() ) as $i => $row ) {
			$text = trim( (string) ( $row['info_text'] ?? '' ) );

			if ( $text === '' ) {
				continue;
			}

			$icon  = (string) ( $row['info_icon'] ?? '' );
			$label = trim( (string) ( $row['info_label'] ?? '' ) );
			$link  = trim( (string) ( $row['info_link'] ?? '' ) );
			if ( stripos( $link, 'tel:' ) === 0 ) {
				$link = self::tel( substr( $link, 4 ) );
			}
			$out[] = array(
				'i'     => $i,
				'ref'   => self::ref( $row['info_id'] ?? '', $i ),
				'icon'  => isset( $icons[ $icon ] ) ? $icon : '',
				'label' => $label !== '' ? $label : $text,
				'text'  => $text,
				'link'  => $link,
			);
		}

		return $out;
	}

	/**
	 * The Additional Info List Items: [ 'company' => the company's (none
	 * when the person hides them), 'person' => the person's own ]. Each:
	 * icon ('' for none), label, text, link (tracked like the others; '' for
	 * none).
	 */
	private static function info( $post_id, array $targets, $go, $link ) {
		$out  = array(
			'company' => array(),
			'person'  => array(),
		);
		$hide = $post_id > 0 && get_field( 'sb_emailsig_person_info_hide', $post_id );

		if ( ! $hide ) {
			foreach ( self::info_rows( 'option' ) as $row ) {
				$out['company'][] = array(
					'ref'   => $row['ref'],
					'icon'  => $row['icon'],
					'label' => $row['label'],
					'text'  => $row['text'],
					'link'  => $row['link'] !== '' ? $go( 'a' . $row['ref'] ) : '',
				);
			}
		}

		foreach ( self::info_rows( (int) $post_id ) as $row ) {
			$out['person'][] = array(
				'ref'   => $row['ref'],
				'icon'  => $row['icon'],
				'label' => $row['label'],
				'text'  => $row['text'],
				'link'  => $row['link'] !== '' ? $link( 'q' . $row['ref'], $row['link'], 'info-' . $row['label'] ) : '',
			);
		}

		return $out;
	}

	/**
	 * The company's Additional Info List Items for the layout editor: row id
	 * => [ label, text ].
	 */
	public static function company_items() {
		$out = array();

		foreach ( self::info_rows( 'option' ) as $row ) {
			$out[ $row['ref'] ] = array(
				'label' => $row['label'],
				'text'  => $row['text'],
			);
		}

		return $out;
	}

	/** A row's fixed id, or i and its place if it has none yet. */
	private static function ref( $id, $i ) {
		$id = preg_replace( '/[^A-Za-z0-9]/', '', (string) $id );

		return $id !== '' ? $id : 'i' . (int) $i;
	}

	/** The key in ::targets() of row $i of a kind (s, b, n). */
	private static function key_for( array $targets, $kind, $i ) {
		foreach ( $targets as $key => $t ) {
			if ( $t['kind'] === $kind && $t['row'] === $i ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * Social rows that have a link and an icon built for this template. 'row'
	 * is the repeater row. $go makes a link from its key in ::targets().
	 */
	private static function socials( $tpl_id, array $targets, $go ) {
		$rows = get_field( 'sb_emailsig_social_media', self::co_src() );
		$out  = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( array_values( $rows ) as $i => $row ) {
			$link = isset( $row['social_media_link'] ) ? trim( (string) $row['social_media_link'] ) : '';
			$img  = SB_EmailSig_Icons::social_url( $tpl_id, $i );
			if ( ! $link || ! $img ) {
				continue;
			}
			$out[] = array(
				'row'  => $i,
				'name' => trim( (string) ( $row['social_media_name'] ?? '' ) ),
				'link' => $go( self::key_for( $targets, 's', $i ) ),
				'icon' => $img,
			);
		}
		return $out;
	}

	/** Buttons with a label and a link: [ [ label, link, outline ], ... ]. */
	private static function buttons( array $targets, $go ) {
		$out = array();

		foreach ( array_values( (array) get_field( 'sb_emailsig_buttons', self::co_src() ) ) as $i => $row ) {
			$label = trim( (string) ( $row['button_label'] ?? '' ) );
			$link  = trim( (string) ( $row['button_link'] ?? '' ) );

			if ( $label !== '' && $link !== '' ) {
				$out[] = array(
					'label'   => $label,
					'link'    => $go( self::key_for( $targets, 'b', $i ) ),
					'outline' => ! empty( $row['button_outline'] ),
				);
			}
		}

		return $out;
	}

	/** Banners with an image: [ [ image, link ], ... ]. */
	private static function banners( array $targets, $go ) {
		$out = array();

		foreach ( array_values( (array) get_field( 'sb_emailsig_banners', self::co_src(), false ) ) as $i => $row ) {
			// Unformatted rows are keyed by field key.
			$id  = (int) ( $row['field_sb_emailsig_banner_image'] ?? ( $row['banner_image'] ?? 0 ) );
			$img = self::image( $id );

			if ( $img ) {
				$out[] = array(
					'image' => $img,
					'link'  => $go( self::key_for( $targets, 'n', $i ) ),
				);
			}
		}

		return $out;
	}

	/**
	 * An image for the signature: [ id, url, width, height, alt ] at full
	 * size, plus the registered sizes so a template can pick one sharp
	 * enough for the width it shows it at (see ::src()). null if there is
	 * none.
	 */
	public static function image( $id ) {
		$id   = (int) $id;
		$full = $id ? wp_get_attachment_image_src( $id, 'full' ) : false;

		if ( ! $full ) {
			return null;
		}

		return array(
			'id'     => $id,
			'url'    => $full[0],
			'width'  => (int) $full[1],
			'height' => (int) $full[2],
			'alt'    => trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
		);
	}

	/**
	 * The image's address for showing it $width wide: the smallest of its
	 * sizes at least twice that (sharp on retina screens) in the same shape,
	 * else the full image. Any of its sizes, so a logo's own
	 * (SB_EmailSig_Logo) and a site's image- widths count as well as
	 * WordPress's, which a site may have switched off.
	 */
	public static function src( array $img, $width ) {
		$meta = wp_get_attachment_metadata( (int) $img['id'] );
		$file = get_attached_file( (int) $img['id'] );
		$best = null;

		if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && $file && $img['width'] > 0 && $img['height'] > 0 ) {
			$ratio  = $img['width'] / $img['height'];
			$folder = trailingslashit( dirname( $file ) );

			foreach ( $meta['sizes'] as $name => $size ) {
				$w = (int) ( $size['width'] ?? 0 );
				$h = (int) ( $size['height'] ?? 0 );

				if ( $w < $width * 2 || $h < 1 || abs( $w / $h - $ratio ) > $ratio * 0.02 || ( null !== $best && $w >= $best[1] ) ) {
					continue;
				}

				if ( ! empty( $size['file'] ) && file_exists( $folder . $size['file'] ) ) {
					$best = array( (string) $name, $w );
				}
			}
		}

		if ( $best ) {
			$src = wp_get_attachment_image_src( (int) $img['id'], $best[0] );

			if ( $src && ! empty( $src[3] ) ) {
				return $src[0];
			}
		}

		return $img['url'];
	}

	/**
	 * Width and height to show an image at: inside $max_w by $max_h (0 for no
	 * height limit) and never bigger than the image itself. If the image's
	 * size isn't known (an SVG, say) the height comes back 0: leave it off.
	 */
	public static function fit( array $img, $max_w, $max_h = 0 ) {
		$w = (int) $img['width'];
		$h = (int) $img['height'];

		if ( $w < 1 || $h < 1 ) {
			return array( (int) $max_w, 0 );
		}

		$scale = min( 1, $max_w / $w );
		if ( $max_h > 0 ) {
			$scale = min( $scale, $max_h / $h );
		}

		return array( max( 1, (int) round( $w * $scale ) ), max( 1, (int) round( $h * $scale ) ) );
	}

	/**
	 * tel: link. Australian numbers starting with 0 become +61.
	 */
	public static function tel( $number ) {
		$digits = preg_replace( '/[^0-9+]/', '', (string) $number );
		if ( '' === $digits ) {
			return '';
		}
		if ( '+' !== $digits[0] && '0' === $digits[0] && strlen( $digits ) === 10 ) {
			$digits = '+61' . substr( $digits, 1 );
		}
		return 'tel:' . $digits;
	}

	/**
	 * Stand-ins for every part, for the Templates page: shows where each thing
	 * goes without anyone's details, and works before any People are added.
	 */
	public static function placeholders( $tpl_id = '' ) {
		return array(
			'id'            => 0,
			'template'      => $tpl_id !== '' ? $tpl_id : SB_EmailSig_Templates::current(),
			'preview'       => true,
			'name'          => '{Name}',
			'first_name'    => '{First Name}',
			'last_name'     => '{Last Name}',
			'qualifications' => '{Qualifications}',
			'job_title'     => '{Job Title}',
			'email'         => '{Email}',
			'mobile'        => '{Mobile}',
			'mobile_link'   => '',
			'photo'         => null,
			'company'       => '{Company}',
			'phone'         => '{Phone}',
			'phone_link'    => '',
			'website'       => '{Website}',
			'website_label' => '{Website}',
			'website_link'  => '',
			'address'       => '{Address}',
			'address_lines' => array( '{Address}' ),
			'maps'          => '',
			'info'          => array(),
			'info_company'  => array(),
			'info_person'   => array(),
			'socials'       => array(),
			'social_heading' => '',
			'logo'          => null,
			'logo_small'    => null,
			'logo_link'     => '',
			'company_link'  => '',
			'buttons'       => array(),
			'campaign'      => null,
			'banners'       => array(),
			'signoff'       => '{Sign-off}',
			'vcard_show'    => true,
			'vcard_label'   => '{Save contact link}',
			'vcard_url'     => '',
			'fine_print'    => '',
			'profile_link'  => '',
		);
	}

	/**
	 * Sample person details with the site's real company settings, for
	 * previews before anyone has been added.
	 */
	public static function sample( $tpl_id = '' ) {
		$d = self::get( 0, $tpl_id );

		return array_merge(
			$d,
			array(
				'name'        => 'Alex Sample',
				'first_name'  => 'Alex',
				'last_name'   => 'Sample',
				'qualifications' => 'Qualifications',
				'job_title'   => 'Job Title',
				'email'       => 'alex@example.com',
				'mobile'      => '0400 000 000',
				'mobile_link' => 'tel:+61400000000',
				'photo'       => null,
				'vcard_url'   => '#',
			)
		);
	}
}
