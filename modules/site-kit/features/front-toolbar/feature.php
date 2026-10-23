<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Roles this setting can actually affect.
 */
if ( ! function_exists( 'sb_site_kit_front_toolbar_roles' ) ) :
function sb_site_kit_front_toolbar_roles() {
	if ( ! function_exists( 'wp_roles' ) ) {
		return [];
	}

	$roles = wp_roles();
	$out   = [];

	foreach ( $roles->get_names() as $slug => $label ) {
		$role = $roles->get_role( $slug );

		if ( $role && ! empty( $role->capabilities['manage_options'] ) ) {
			continue;
		}

		$out[ $slug ] = $label;
	}

	return $out;
}
endif;
return [
	'id'          => 'front-toolbar',
	'title'       => 'Hide Toolbar On The Front End',
	'description' => 'Tick the roles that have the admin toolbar hidden on the front end. Administrators keep whatever their own profile says.',
	'section'     => 'admin',
	'default'     => true,

	'settings'    => [
		/**
		 * Roles that can manage the site are left out: their own profile setting
		 * always decides, so a tick here would do nothing.
		 */
		'roles' => [
			'type'    => 'multicheck',
			'label'   => 'Hide the admin toolbar for these roles',
			'options' => 'sb_site_kit_front_toolbar_roles',
			'default' => array_keys( sb_site_kit_front_toolbar_roles() ),
		],
	],
	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-front-toolbar.php';
		SB_Site_Kit_Front_Toolbar::boot();
	},
];