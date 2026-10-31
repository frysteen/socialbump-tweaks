<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Activity page: who did what, and when (SB_EmailSig_Log::render(),
 * loaded by the module's boot).
 */
return [
	'id'          => 'signature-activity',
	'title'       => 'Activity',
	'description' => 'Who did what, and when.',
	'section'     => 'activity',
	'always'      => true,

	'admin_page'  => [
		'title'  => 'Activity',
		'render' => function ( $feature ) {
			if ( class_exists( 'SB_EmailSig_Log' ) ) {
				SB_EmailSig_Log::render();
			}
		},
	],
];
