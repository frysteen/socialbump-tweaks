<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'builder-panels',
	'title'       => 'Clean Builder Panels',
	'description' => 'Stops WPCodeBox CSS snippets styling the Bricks builder\'s own panels and popups. The canvas still gets them.',
	'type'        => 'tweak',
	'section'     => 'extras',
	'default'     => true,

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-bricks-builder-panels.php';
		SB_Bricks_Builder_Panels::boot();
	},
];
