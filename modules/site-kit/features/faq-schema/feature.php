<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'          => 'faq-schema',
	'title'       => __( 'FAQ Settings', 'sb-tweaks' ),
	'description' => __( 'Adds the fields that hold your questions and answers, and prints FAQPage schema on the pages that have them. Questions can live on the page itself, or in a post type used as a library.', 'sb-tweaks' ),
	'section'     => 'faq',
	'default'     => true,
	'requires'    => [ 'acf' ],

	/**
	 * The Modules card lists the two halves with their own state, so the card
	 * says what is actually running rather than just naming the module.
	 */
	'features'    => function () {
		require_once SB_SITE_KIT_PATH . 'features/faq-schema/class-sb-site-kit-faq.php';

		$settings = SB_Site_Kit_FAQ::settings();

		return [
			[ 'label' => __( 'FAQ fields on a post', 'sb-tweaks' ), 'on' => ! empty( $settings['repeater_on'] ) ],
			[ 'label' => __( 'FAQ post types', 'sb-tweaks' ), 'on' => ! empty( $settings['sources_on'] ) ],
		];
	},

	'admin_page'  => [
		'title'  => __( 'FAQ Settings', 'sb-tweaks' ),
		'render' => function ( $module ) {
			require_once $module['path'] . 'class-sb-site-kit-faq.php';
			require_once $module['path'] . 'class-sb-site-kit-faq-admin.php';

			SB_Site_Kit_FAQ_Admin::render_page();
		},
	],

	'boot' => function ( $module ) {
		require_once $module['path'] . 'class-sb-site-kit-faq.php';
		require_once $module['path'] . 'class-sb-site-kit-faq-admin.php';

		SB_Site_Kit_FAQ::boot();
		SB_Site_Kit_FAQ_Admin::boot();
	},
];
