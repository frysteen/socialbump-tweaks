<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'admin-variables',
	'title'       => 'Bricks Variables in Admin',
	'description' => 'Loads the site\'s Bricks colour palette and global variables in wp-admin, so admin styles can use them.',
	'type'        => 'tweak',
	'section'     => 'extras',
	'default'     => false,

	'settings'    => [
		'palettes'  => [
			'type'    => 'switch',
			'label'   => 'Colour palette',
			'default' => 1,
		],
		'variables' => [
			'type'    => 'switch',
			'label'   => 'Global variables',
			'default' => 1,
		],
	],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-bricks-admin-variables.php';
		SB_Bricks_Admin_Variables::boot();
	},
];
