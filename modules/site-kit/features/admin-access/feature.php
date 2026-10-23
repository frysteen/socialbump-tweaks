<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sb_site_kit_admin_access_roles' ) ) :
	/**
	 * Roles this setting can affect. Anyone who can manage the site is left out,
	 * so an administrator can never be locked out from here.
	 */
	function sb_site_kit_admin_access_roles() {
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

$sb_site_kit_admin_access_settings = [
	'roles' => [
		'type'    => 'multicheck',
		'label'   => 'Block the admin for these roles',
		'options' => 'sb_site_kit_admin_access_roles',
		'default' => [],
	],
];

// Only worth offering where there is a My Account page to point at.
if ( class_exists( 'WooCommerce' ) ) {
	$sb_site_kit_admin_access_settings['woo_account'] = [
		'type'    => 'checkbox',
		'label'   => 'Send them to the WooCommerce My Account page',
		'default' => 0,
	];
}
return [
	'id'          => 'admin-access',
	'title'       => 'Block Admin Access',
	'description' => 'Sends the roles you tick back to the front end if they try to open the WordPress admin. Administrators are never affected.',
	'section'     => 'admin',
	'default'     => false,

	/**
	 * Ticked roles are blocked, rather than the other way round: losing the admin
	 * stops people working, so a role added later by a plugin should keep its
	 * access until you decide otherwise.
	 */
	'settings'    => array_merge(
		$sb_site_kit_admin_access_settings,
		[
			'redirect' => [
				'type'        => 'text',
				'label'       => 'Send them to',
				'default'     => '',
				'placeholder' => '/my-account/',
				'description' => 'A path or a full URL. Empty sends them to the home page.',
				'hide_when'   => 'woo_account',
			],
		]
	),
	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-admin-access.php';
		SB_Site_Kit_Admin_Access::boot();
	},
];