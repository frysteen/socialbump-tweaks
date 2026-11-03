<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Import/Export page (Sam, 3 November 2026): the people file, moved here
 * from the Signatures page's bar as it was (SB_EmailSig_Transfer). Company
 * export and the rest to come.
 */
return [
	'id'          => 'signature-transfer',
	'title'       => 'Import/Export',
	'description' => 'Move people in and out of this site with a spreadsheet file.',
	'section'     => 'transfer',
	'always'      => true,

	'admin_page'  => [
		'title'  => 'Import/Export',
		'render' => function ( $feature ) {
			if ( class_exists( 'SB_EmailSig_Transfer' ) ) {
				SB_EmailSig_Transfer::render_page();
			}
		},
	],
];
