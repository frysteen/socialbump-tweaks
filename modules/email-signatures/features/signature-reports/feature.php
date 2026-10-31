<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Reports page: clicks on signature links, by period, person, link and
 * device. The page itself is SB_EmailSig_Report::render(), loaded by the
 * module's boot.
 */
return [
	'id'          => 'signature-reports',
	'title'       => 'Reports',
	'description' => 'Clicks on the links in everyone\'s signatures.',
	'section'     => 'reports',
	'always'      => true,

	'admin_page'  => [
		'title'  => 'Reports',
		'render' => function ( $feature ) {
			if ( class_exists( 'SB_EmailSig_Report' ) ) {
				SB_EmailSig_Report::render();
			}
		},
	],
];
