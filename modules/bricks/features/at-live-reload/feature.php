<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'at-live-reload',
	'title'       => 'AT Stylesheet Live Reload',
	'description' => 'Saving a WPCodeBox stylesheet in Advanced Themer reloads it in open front-end tabs, as saving in WPCodeBox does. Follows each stylesheet\'s Autoreload Changes setting.',
	'type'        => 'tweak',
	'section'     => 'extras',
	'default'     => true,

	// Both plugins have loaded by the time features boot (plugins_loaded).
	'unavailable' => function () {
		if ( defined( 'WPCODEBOX2_VERSION' ) && class_exists( 'Advanced_Themer_Bricks\AT__Ajax' ) ) {
			return '';
		}

		return function () {
			return __( 'Needs Advanced Themer and WPCodeBox 2.', 'sb-tweaks' );
		};
	},

	'boot'        => function ( $module ) {
		require_once $module['path'] . 'class-sb-bricks-at-live-reload.php';
		SB_Bricks_AT_Live_Reload::boot();
	},
];