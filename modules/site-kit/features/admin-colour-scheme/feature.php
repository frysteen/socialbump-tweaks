<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'admin-colour-scheme',
	'title'       => __( 'SocialBUMP Admin Colour Scheme', 'sb-tweaks' ),
	'description' => __( 'Adds a SocialBUMP admin colour scheme, picked under Users then Profile. Based on Midnight with the SocialBUMP palette in place of the red.', 'sb-tweaks' ),
	'section'     => 'admin',
	'default'     => true,

	'settings'    => [
		'force' => [
			'type'        => 'checkbox',
			'label'       => __( 'Use it for everyone', 'sb-tweaks' ),
			'default'     => 0,
		],
	],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-admin-colours.php';
		SB_Site_Kit_Admin_Colours::boot( $module );
	},
];