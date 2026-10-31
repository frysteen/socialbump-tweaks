<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Signatures: staff email signatures built from one post per person and
 * shared company settings, copied to the clipboard for Apple Mail, iPhone and
 * the rest.
 *
 * Built first for The Cosmetic Studio Noosa (October 2026) to replace
 * MySignature. Its own code names (SB_EmailSig_, sb_emailsig_, sb-emailsig-),
 * its own CPT (sb_emailsig) and its own ACF fields (sb_emailsig_*), so it could
 * become a plugin of its own again without touching the rest. It needs ACF Pro
 * for the fields and the settings page.
 */
if ( ! defined( 'SB_EMAILSIG_DIR' ) ) {
	define( 'SB_EMAILSIG_DIR', plugin_dir_path( __FILE__ ) );
	define( 'SB_EMAILSIG_URL', SB_TWEAKS_URL . 'modules/email-signatures/' );
}

require_once SB_EMAILSIG_DIR . 'includes/class-sb-emailsig-module.php';

return [
	'id'          => 'email_signatures',
	'title'       => 'Email Signatures',
	'description' => 'Staff email signatures: one post per person, shared company details and social icons, templates, and a page each person opens to copy theirs into Apple Mail or their iPhone.',
	'heading'     => 'Email Signatures',
	'requires'    => [ 'acf' ],

	// Its pages use the SocialBUMP UI tokens throughout, so dark mode is on.
	'ui_ready'    => true,

	// Pages rather than switchable features: no Features page, and the nav
	// reads Signatures, Companies, Reports, Templates, Settings.
	'groups'      => [
		'signatures' => [
			'title'       => 'Signatures',
			'description' => 'Everyone\'s signature, ready to copy or send.',
		],
		'companies'  => [
			'title'       => 'Companies',
			'description' => 'Each company\'s details, branding and templates. Everyone belongs to one.',
		],
		'templates' => [
			'title'       => 'Templates',
			'description' => 'The signature layouts this site can use, and their styles.',
		],
		'reports'    => [
			'title'       => 'Reports',
			'description' => 'Clicks on the links in everyone\'s signatures.',
		],
		'activity'   => [
			'title'       => 'Activity',
			'description' => 'Who did what, and when.',
		],
		'settings'  => [
			'title'       => 'Settings',
			'description' => 'Team pages, tracking codes and emails, for every company.',
		],
	],

	// Signatures is listed here too, so it comes first in the nav and the
	// menu opens on it: the framework puts 'nav' screens before the groups,
	// and a group already listed keeps its place. WordPress's People list
	// isn't in the nav: the people are on the Signatures page.
	'nav'         => [
		'sb-email-signatures-signatures' => 'Signatures',
	],

	'menu'        => [
		'slug'       => SB_EmailSig_Module::SLUG,
		'label'      => 'SB Email Signatures',
		'page_title' => 'SocialBUMP Email Signatures',
		'features'   => false,
	],

	'bar'         => [
		'id'    => 'email-signatures',
		'label' => 'Email Signatures',
	],

	'prompt'      => 'You are picking up work on the Email Signatures module of SocialBUMP Tweaks, a WordPress plugin. Everything is developed on the hub, plugins.socialbump.com.au, which you reach through its Novamira MCP connector. Before changing anything, read wp-content/plugins/socialbump-tweaks/modules/email-signatures/docs/context.md for this module, and wp-content/plugins/socialbump-tweaks/docs/context.md for the framework it runs on. Keep the module notes current: when you change how something works or learn something the hard way, write it there in the same session. Tell me what you have read before you start, and before you finish, bring the notes up to date and tell me exactly what you added or corrected.',

	'boot'        => function ( $context ) {
		SB_EmailSig_Module::boot( $context );
	},
];
