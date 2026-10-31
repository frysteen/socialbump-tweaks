<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic's style settings: what its Edit styles page offers, and the
 * defaults, taken from The Cosmetic Studio's old signature.
 *
 * Colours are a hex value, a theme colour as var(--name) or a brand colour
 * as var(--sb-brand-primary) and so on (SB_EmailSig_Styles::brand(), from
 * Settings, Branding), each turned into its hex when the signature is drawn.
 * primary and text left blank are the brand's primary and text.
 *
 * items:  each text part the template draws, with its size (px), weight,
 *         style, case and colour ('' uses the text colour, or the brand
 *         colour named in fallback, such as text_light). An item with
 *         card (social) is set on that card on Edit styles instead of Text.
 * lines:  the divider and bottom line ('' uses the primary colour).
 * icons:  what starts each Info List line (the Info List Styles card,
 *         named Info icons before 29 October 2026): show is icon, letter (the label's first
 *         letter), label (the full label) or none. Icons are drawn from
 *         icons/<name>.svg in colour ('' the primary) at size, with a
 *         background (bg, '' the primary; radius) when background is 1.
 *         Letters and labels are in label_colour ('' the primary),
 *         label_weight, label_italic and label_case, at the contact text's
 *         size. valign (top, middle or bottom) lines the icon or label
 *         up with its text.
 * social: the social icons ('' background uses the primary colour).
 * signature: the widest the signature grows (px). It can be narrower;
 *         banners and long footer text fill up to it.
 * logo:   the most the company logo is shown at, wide and high (px). It fits
 *         inside both and is never shown bigger than the image itself.
 *         max_height 0 (blank on the page) means the width alone decides.
 *         small_max_width and small_max_height: the same for the Small logo
 *         (Settings, Branding).
 * photo:  everyone's photo: shape (circle, rounded, square),
 *         radius (px, for rounded), ratio ('1:1', '4:3', '3:4' or 'custom'
 *         with ratio_w and ratio_h) and width (px; the height follows). A
 *         person can have their own ratio, set in their Photo Crop box.
 * buttons: the Additional Buttons (each one solid or outline, ticked in
 *         Settings): colour ('' primary), solid text colour ('' white),
 *         outline text colour ('' the button colour), corner radius, text
 *         size (px; the padding and line height scale with it), weight and
 *         case. Both kinds have a 2px border in the button colour, so they
 *         are the same size.
 * layout: where everything sits (the Layout card; SB_EmailSig_Layout has
 *         the shape). Classic's is the signature as it was drawn before the
 *         layout editor: photo, then name, job title and company, then a line
 *         and the logo, Info List, Save my contact details and social icons.
 *         Whether the photo shows is the layout's now (photo show is kept in
 *         step for the photo code).
 */
return [
	'primary' => '',
	'text'    => '',
	'font'    => 'arial',
	'lines'   => '',

	'items'   => [
		'signoff'    => [ 'label' => 'Sign-off', 'size' => 13, 'weight' => '400', 'style' => 'normal', 'case' => 'none', 'colour' => '' ],
		'name'       => [ 'label' => 'Name', 'size' => 18, 'weight' => '700', 'style' => 'normal', 'case' => 'none', 'colour' => '' ],
		'qualifications' => [ 'label' => 'Qualifications', 'size' => 12, 'weight' => '400', 'style' => 'normal', 'case' => 'none', 'colour' => '', 'fallback' => 'text_light' ],
		'job_title'  => [ 'label' => 'Job title', 'size' => 14, 'weight' => '400', 'style' => 'normal', 'case' => 'none', 'colour' => '' ],
		'company'    => [ 'label' => 'Company', 'size' => 14, 'weight' => '400', 'style' => 'normal', 'case' => 'none', 'colour' => '' ],
		'contact'    => [ 'label' => 'Contact details', 'size' => 14, 'weight' => '400', 'style' => 'normal', 'case' => 'none', 'colour' => '' ],
		// Styled on the Social icons card, not the Text card ('card').
		'social_heading' => [ 'label' => 'Heading', 'size' => 14, 'weight' => '400', 'style' => 'normal', 'case' => 'none', 'colour' => '', 'card' => 'social' ],
		'fine_print' => [ 'label' => 'Footer text', 'size' => 11, 'weight' => '400', 'style' => 'normal', 'case' => 'none', 'colour' => '', 'fallback' => 'text_light' ],
	],

	'icons'   => [
		'show'         => 'icon',
		'valign'       => 'top',
		'colour'       => '',
		'background'   => 0,
		'bg'           => '',
		'radius'       => 4,
		'size'         => 18,
		'label_colour' => '',
		'label_weight' => '700',
		'label_italic' => 'normal',
		'label_case'   => 'none',
		// The Info List's own, then the ones Additional Info List Items can
		// pick (SB_EmailSig_Fields::info_icons()).
		'names'  => [ 'phone', 'smartphone', 'mail', 'globe', 'map-pin', 'user-plus', 'clock', 'calendar', 'info', 'link', 'award', 'star', 'heart', 'gift', 'briefcase', 'building-2', 'graduation-cap', 'message-circle', 'printer', 'car' ],
	],

	'social'  => [
		'background' => 1,
		'bg'         => '',
		'colour'     => '#ffffff',
		'radius'     => 5,
		'size'       => 26,
	],

	'signature' => [
		'width' => 600,
	],

	'logo'    => [
		'max_width'        => 180,
		'max_height'       => 0,
		'small_max_width'  => 60,
		'small_max_height' => 0,
	],

	'photo'   => [
		'show'    => 1,
		'shape'   => 'circle',
		'radius'  => 12,
		'ratio'   => '1:1',
		'ratio_w' => 4,
		'ratio_h' => 3,
		'width'   => 130,
		// A ring drawn into the photo (Sam, 30 October 2026): px, 0 for
		// none; its colour ('' the primary); a see-through gap inside it.
		'border'        => 0,
		'border_colour' => '',
		'gap'           => 0,
	],

	'layout'  => [
		'columns'    => [
			[
				'width'  => 0,
				'valign' => '',
				'padding' => [ 'top' => 'double', 'right' => 0, 'bottom' => 'double', 'left' => 0 ], // Double top and bottom unless set (Sam, 31 October 2026).
				'blocks' => [
					[ 'type' => 'photo', 'align' => 'left', 'space' => 'default' ],
				],
			],
			[
				'width'  => 0,
				'valign' => '',
				'padding' => [ 'top' => 'double', 'right' => 0, 'bottom' => 'double', 'left' => 0 ], // Double top and bottom unless set (Sam, 31 October 2026).
				'blocks' => [
					[ 'type' => 'name', 'align' => 'left', 'space' => 'default', 'style' => 'stack', 'sep' => 'bar', 'spacing' => 'quarter' ],
					[ 'type' => 'company', 'align' => 'left', 'space' => 'half' ],
				],
			],
			[
				'width'  => 0,
				'valign' => '', // Default (the row's), as the others (Sam, 31 October 2026).
				'padding' => [ 'top' => 'double', 'right' => 0, 'bottom' => 'double', 'left' => 0 ], // Double top and bottom unless set (Sam, 31 October 2026).
				'blocks' => [
					[ 'type' => 'logo', 'align' => 'left', 'space' => 'default' ],
					[ 'type' => 'info', 'id' => 1, 'align' => 'left', 'space' => 'default', 'style' => 'stack', 'sep' => 'bar', 'spacing' => 'default' ], // Default spacing both (Sam, 31 October 2026).
					[ 'type' => 'vcard', 'align' => 'left', 'space' => 'default' ],
					[ 'type' => 'social', 'align' => 'left', 'space' => 'default', 'heading' => 1, 'direction' => 'row', 'indent' => 1 ],
				],
			],
		],
		'gaps'       => [
			// Double either side, with the Default Line Width, unless set (Sam, 31 October 2026).
			[ 'space' => 'double', 'line' => 'default' ],
			[ 'space' => 'double', 'line' => 'default' ],
		],
		'valign'     => 'middle',
		'block_gap'  => 12, // The Default Spacing.
		'line_width' => 1, // The Default Line Width.
		'line_style' => 'solid', // The Default Line Style.
		'padding'    => 0,
		'border'     => [ 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0, 'colour' => '' ],
		'line_below' => 'default',
		'line_space' => 'double',
		'hold'       => [
			[ 'type' => 'logo_small', 'align' => 'left', 'space' => 'default' ],
		],
	],

	'buttons' => [
		'bg'             => '',
		'colour'         => '',
		'outline_colour' => '',
		'radius'         => 4,
		'text'           => 13,
		'weight'         => '400',
		'case'           => 'none',
	],
];
