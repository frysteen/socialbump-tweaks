<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'fix-acf-cpt-svg-icons',
	'title'       => __( 'Fix ACF CPT SVG Icons', 'sb-tweaks' ),
	'description' => __( 'Makes SVG menu icons on post types created in ACF behave like the other admin menu icons: the same size, and they change colour on hover and when active. Menu icons from other plugins are left alone.', 'sb-tweaks' ),
	'type'        => 'tweak',
	'section'     => 'admin',
	'default'     => false,
	'requires'    => [ 'acf' ],

	'settings'    => [
		'colour' => [
			'type'        => 'color',
			'label'       => __( 'Icon colour', 'sb-tweaks' ),
			'default'     => '',
			'placeholder' => '#fff or var(--primary)',
		],
	],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-acf-svg-icons.php';
		SB_Site_Kit_Acf_Svg_Icons::boot();
	},
];