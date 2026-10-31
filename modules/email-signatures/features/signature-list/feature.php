<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Signatures page: everyone's signature as a card (photo and name) with
 * Copy signature, Copy link and Open link, the same three as on a person's
 * edit screen. The module's main page. On whenever the module is. The page
 * itself is SB_EmailSig_Admin::render_list(), loaded by the module's boot.
 */
return [
	'id'          => 'signature-list',
	'title'       => 'Signatures',
	'description' => 'Everyone\'s signature, with links to copy, send or open it.',
	'section'     => 'signatures',
	'always'      => true,

	'admin_page'  => [
		'title'  => 'Signatures',
		'render' => function ( $feature ) {
			if ( class_exists( 'SB_EmailSig_Admin' ) ) {
				SB_EmailSig_Admin::render_list();
			}
		},
	],
];
