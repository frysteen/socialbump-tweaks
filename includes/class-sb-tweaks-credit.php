<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The SocialBUMP credit in the WordPress admin footer.
 *
 * Part of the plugin itself rather than a module, so it follows the plugin
 * being active rather than anything being switched on. If SocialBUMP Tweaks is
 * running, the footer says so.
 *
 * The link carries UTM parameters, so the referrals arrive in analytics as one
 * campaign with the client site named as the source. It used to point at
 * /client_referral/?url=, which needed a page to exist and meant nothing to any
 * analytics tool.
 *
 * The front end credit is a separate thing and stays a WP CodeBox snippet,
 * [social_bump_credit], because it is placed by hand per site. Keep the two
 * links the same shape so the reporting reads as one campaign.
 */
class SB_Tweaks_Credit {

	const URL      = 'https://socialbump.com.au/';
	const CAMPAIGN = 'client-referral';

	public static function boot() {
		add_filter( 'admin_footer_text', [ __CLASS__, 'admin_footer' ] );
	}

	/**
	 * The link, with this site named as the source.
	 *
	 * The host on its own, no scheme and no trailing slash: one site should be
	 * one source in the reports rather than two.
	 */
	public static function url() {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return add_query_arg(
			[
				'utm_source'   => $host !== '' ? $host : 'unknown',
				'utm_medium'   => 'referral',
				'utm_campaign' => self::CAMPAIGN,
			],
			self::URL
		);
	}

	public static function admin_footer( $text ) {
		$q = chr( 34 );

		return sprintf(
			/* translators: 1: opening anchor tag, 2: closing anchor tag */
			esc_html__( 'Website by %1$sSocialBUMP!%2$s', 'sb-tweaks' ),
			'<a href=' . $q . esc_url( self::url() ) . $q . ' target=' . $q . '_blank' . $q . ' rel=' . $q . 'noopener' . $q . '>',
			'</a>'
		);
	}
}