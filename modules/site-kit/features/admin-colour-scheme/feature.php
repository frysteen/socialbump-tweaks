<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'admin-colour-scheme',
	'title'       => 'SocialBUMP Admin Colour Scheme',
	'description' => 'Adds a SocialBUMP admin colour scheme, picked under Users then Profile. Based on Midnight with the SocialBUMP palette in place of the red.',
	'section'     => 'admin',
	'default'     => true,

	'settings'    => [
		'force' => [
			'type'        => 'checkbox',
			'label'       => 'Use it for everyone',
			'default'     => 0,
		],
	],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-admin-colours.php';
		SB_Site_Kit_Admin_Colours::boot( $module );
	},
];