<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Companies page: a card for each company, and each one's details,
 * branding, social links, footer, campaign banners and templates. On
 * whenever the module is. The page itself is SB_EmailSig_Companies.
 */
return [
	'id'          => 'signature-companies',
	'title'       => 'Companies',
	'description' => 'Each company\'s details, branding and templates. Everyone belongs to one.',
	'section'     => 'companies',
	'always'      => true,

	'admin_page'  => [
		'title'  => 'Companies',
		'render' => function ( $feature ) {
			if ( class_exists( 'SB_EmailSig_Companies' ) ) {
				SB_EmailSig_Companies::render_page();
			}
		},
	],
];
