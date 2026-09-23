<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's own notes, on the Publishing page.
 *
 * Every plugin carries a docs/context.md written for whoever works on it next,
 * which in practice is a fresh chat with no memory of how any of it came about.
 * It ships with the plugin, so it reaches every site.
 *
 * Editing happens here on the hub, because this is the copy that gets published.
 * These notes stand alone, like the plugin: there is nothing to compare them
 * against and nothing that has to be copied elsewhere when they change.
 */
class SB_Tweaks_Docs {

	const START = '<!-- shared:start -->';
	const END   = '<!-- shared:end -->';

	public static function boot() {
		add_action( 'admin_post_sb_tweaks_save_docs', [ __CLASS__, 'save' ] );

		// The Publishing page draws whatever hooks this.
		add_action( 'sb_tweaks_settings_after', [ __CLASS__, 'render' ] );
	}

	private static function path() {
		return SB_TWEAKS_PATH . 'docs/context.md';
	}

	/** The shared block, for comparing against the other plugins. */
	public static function shared( $file = '' ) {
		$file = $file !== '' ? $file : self::path();

		if ( ! is_readable( $file ) ) {
			return '';
		}

		$text  = (string) file_get_contents( $file );
		$start = strpos( $text, self::START );
		$end   = strpos( $text, self::END );

		if ( $start === false || $end === false || $end < $start ) {
			return '';
		}

		return substr( $text, $start, $end - $start );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sb-tweaks' ) );
		}

		check_admin_referer( 'sb_tweaks_save_docs' );

		$text = isset( $_POST['sb_tweaks_docs'] ) ? (string) wp_unslash( $_POST['sb_tweaks_docs'] ) : '';
		$file = self::path();

		if ( ! is_dir( dirname( $file ) ) ) {
			wp_mkdir_p( dirname( $file ) );
		}

		if ( trim( $text ) !== '' ) {
			file_put_contents( $file, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		wp_safe_redirect( admin_url( 'admin.php?page=sb-tweaks-publishing&docs=saved' ) );
		exit;
	}

	public static function render() {
		$file = self::path();
		$text = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
		$q    = chr( 34 );

		echo '<section class=' . $q . 'sb-tweaks-section' . $q . '>';
		echo '<div class=' . $q . 'sb-tweaks-section__head' . $q . '><h2>' . esc_html__( 'Notes for next time', 'sb-tweaks' ) . '</h2>';
		echo '<p>' . esc_html__( 'How this plugin works, written for whoever picks it up next. Published with the plugin, so keep it accurate and keep it public friendly.', 'sb-tweaks' ) . '</p></div>';
		echo '<div class=' . $q . 'sb-tweaks-section__body' . $q . '>';

		if ( isset( $_GET['docs'] ) ) {
			echo '<div class=' . $q . 'notice notice-success inline' . $q . '><p>' . esc_html__( 'Notes saved.', 'sb-tweaks' ) . '</p></div>';
		}

		$prompt  = 'You are picking up work on SocialBUMP Tweaks, a WordPress plugin. ';
		$prompt .= 'Everything is developed on the hub, plugins.socialbump.com.au, which you reach through its Novamira MCP connector. ';
		$prompt .= 'Before changing anything, read wp-content/plugins/socialbump-tweaks/docs/context.md on the hub. ';
		$prompt .= 'It explains what the plugin does, how it is built, and the mistakes already made and fixed. ';
		$prompt .= 'This plugin is standalone. It shares no code with the other SocialBUMP plugins, so nothing you change here lands anywhere else, and nothing they change lands here. It may copy from them, never share with them. ';
		$prompt .= 'Keep that file current: when you change how something works or learn something the hard way, write it there in the same session. ';
		$prompt .= 'Tell me what you have read before you start. ';
		$prompt .= 'And before you finish, or any time I say we are done, go back over what we changed and bring that file up to date, then tell me exactly what you added or corrected in it. ';
		$prompt .= 'If nothing in it needed changing, say so plainly rather than saying nothing.';

		echo '<h3 class=' . $q . 'sb-tweaks-docs__heading' . $q . '>' . esc_html__( 'Starting a new chat', 'sb-tweaks' ) . '</h3>';
		echo '<p class=' . $q . 'description' . $q . '>' . esc_html__( 'Copy this in as the first message, so the chat knows where to look.', 'sb-tweaks' ) . '</p>';
		echo '<textarea class=' . $q . 'large-text code sb-tweaks-docs__prompt' . $q . ' rows=' . $q . '5' . $q . ' readonly onclick=' . $q . 'this.select();' . $q . '>' . esc_textarea( $prompt ) . '</textarea>';

		echo '<h3 class=' . $q . 'sb-tweaks-docs__heading' . $q . '>' . esc_html__( 'The notes themselves', 'sb-tweaks' ) . '</h3>';

		echo '<form method=' . $q . 'post' . $q . ' action=' . $q . esc_url( admin_url( 'admin-post.php' ) ) . $q . '>';
		echo '<input type=' . $q . 'hidden' . $q . ' name=' . $q . 'action' . $q . ' value=' . $q . 'sb_tweaks_save_docs' . $q . '>';
		wp_nonce_field( 'sb_tweaks_save_docs' );
		echo '<textarea name=' . $q . 'sb_tweaks_docs' . $q . ' rows=' . $q . '18' . $q . ' class=' . $q . 'large-text code sb-tweaks-docs' . $q . ' spellcheck=' . $q . 'false' . $q . '>' . esc_textarea( $text ) . '</textarea>';
		echo '<p><button type=' . $q . 'submit' . $q . ' class=' . $q . 'button' . $q . '>' . esc_html__( 'Save notes', 'sb-tweaks' ) . '</button></p>';
		echo '</form></div></section>';
	}
}
