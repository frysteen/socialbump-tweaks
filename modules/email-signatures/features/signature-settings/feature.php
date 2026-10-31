<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Settings page: what is the site's own (team pages, tracking codes,
 * emails), as ACF fields in a framework page. Each company's details are on
 * the Companies page. On whenever the module is. The
 * page itself is SB_EmailSig_Settings, loaded by the module's boot.
 */
return [
	'id'          => 'signature-settings',
	'title'       => 'Settings',
	'description' => 'Team pages, tracking codes and emails, for every company.',
	'section'     => 'settings',
	'always'      => true,

	'admin_page'  => [
		'title'  => 'Settings',
		'render' => function ( $feature ) {
			if ( class_exists( 'SB_EmailSig_Settings' ) ) {
				SB_EmailSig_Settings::render_page();
			}
		},
	],
];
