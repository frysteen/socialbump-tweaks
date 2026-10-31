<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Templates page: every template with a live preview, and which one the
 * site uses. On whenever the module is. The registry itself is
 * SB_EmailSig_Templates, loaded by the module's boot.
 */
return [
	'id'          => 'signature-templates',
	'title'       => 'Templates',
	'description' => 'Every signature layout this site can use, previewed with a real person, and the one it uses.',
	'section'     => 'templates',
	'always'      => true,

	'admin_page'  => [
		'title'  => 'Templates',
		'render' => function ( $feature ) {
			if ( class_exists( 'SB_EmailSig_Templates' ) ) {
				SB_EmailSig_Templates::render_page();
			}
		},
	],
];
