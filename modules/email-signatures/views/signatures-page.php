<?php
/**
 * Front-end team page, in the SocialBUMP UI look (Sam, 30 October 2026):
 * the same cards as the Signatures page (SB_EmailSig_Admin::section() and
 * card()). Available: $groups (one per company: id, name, link, and ids in
 * active, inactive and deleted; the last two only for administrators and
 * editors), $everyone (every company's page, for those who manage: each
 * company under its own heading, with a company filter), $title, $company,
 * $link (this page's own address), $manage (an administrator or editor:
 * Inactive and Deleted, the Active switch, where each signature stands,
 * Edit, Email link, Delete, and a bar with the page's link and Add person).
 * Everyone gets the search, Preview, Copy link and Open their page.
 * $head: the circle beside the heading (SB_EmailSig_Page::head_mark()), or ''.
 * $request: Submit New Team Member, for visitors who can't manage this
 * company's page (SB_EmailSig_Request).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Back from Delete, Restore or Delete for good (SB_EmailSig_Person): say what happened.
// phpcs:disable WordPress.Security.NonceVerification
$sb_trashed  = $manage && isset( $_GET['trashed'] ) ? absint( $_GET['trashed'] ) : 0;
$sb_restored = $manage && isset( $_GET['restored'] ) ? absint( $_GET['restored'] ) : 0;
$sb_purged   = $manage && ! empty( $_GET['purged'] );
// phpcs:enable

// Everyone in view, for the search (worth it with more than one).
$sb_people = 0;
foreach ( $groups as $sb_group ) {
	$sb_people += count( $sb_group['active'] ) + count( $sb_group['inactive'] ) + count( $sb_group['deleted'] );
}
$sb_pick = $everyone && count( $groups ) > 1;
?><!doctype html>
<html lang="en-AU">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title ); ?></title>
<?php SB_EmailSig_Page::head_assets(); ?>
</head>
<body class="<?php echo esc_attr( SB_EmailSig_Page::body_class() ); ?>">
<div class="sb-tweaks-wrap sb-emailsig-front">

	<header class="sb-emailsig-front__head<?php echo $head !== '' ? ' has-logo' : ''; ?>">
		<?php echo $head; // phpcs:ignore WordPress.Security.EscapeOutput -- built escaped by head_mark(). ?>
		<div class="sb-emailsig-front__headtext">
			<h1><?php echo esc_html( $everyone ? 'Email signatures' : $title ); ?></h1>
			<p class="sb-emailsig-front__lead"><?php echo esc_html( $everyone ? 'Every company\'s people, under their company.' : 'Find your name, preview your signature, then open your page to copy it into your mail app.' ); ?></p>
		</div>
	</header>

	<?php if ( $sb_trashed && get_post_type( $sb_trashed ) === SB_EMAILSIG_CPT ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( get_the_title( $sb_trashed ) . ' moved to Deleted. Restore them from there.' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $sb_restored && get_post_type( $sb_restored ) === SB_EMAILSIG_CPT ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( get_the_title( $sb_restored ) . ' restored to ' . ( get_post_status( $sb_restored ) === 'publish' ? 'Active.' : 'Inactive.' ) ); ?></p></div>
	<?php endif; ?>
	<?php if ( $sb_purged ) : ?>
		<div class="notice notice-success is-dismissible"><p>Deleted for good.</p></div>
	<?php endif; ?>

	<?php if ( $sb_people > 1 || $sb_pick || $manage || $request ) : ?>
	<div class="sb-ui-bar sb-emailsig-bar">
		<?php SB_EmailSig_Admin::filters( $sb_people > 1, $sb_pick ? wp_list_pluck( $groups, 'id' ) : array() ); ?>
		<?php if ( $manage ) : ?>
			<div class="sb-ui-bar__group"><span class="sb-ui-bar__label">This page</span>
				<button type="button" class="button button-small sb-emailsig-icon" data-sb-emailsig-copy-text="<?php echo esc_attr( $link ); ?>" data-sb-tip="Copy link"><?php echo SB_EmailSig_Admin::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></button>
				<?php if ( ! $everyone ) : ?>
					<a class="button button-small" href="<?php echo esc_url( SB_EmailSig_Page::list_url() ); ?>"><?php echo SB_EmailSig_Admin::icon( 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>All companies</a>
				<?php endif; ?>
			</div>
			<div class="sb-ui-bar__group">
				<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SB_EmailSig_Module::SLUG . '-signatures' ) ); ?>"><?php echo SB_EmailSig_Admin::icon( 'gear' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>Signatures in WordPress</a>
			</div>
			<?php if ( current_user_can( 'publish_posts' ) || ( ! $everyone && SB_EmailSig_Switch::can() ) ) : ?>
				<div class="sb-ui-bar__end">
					<?php if ( current_user_can( 'publish_posts' ) ) : ?>
						<?php echo SB_EmailSig_Limits::button( 'people', '<a class="button button-primary" href="' . esc_url( SB_EmailSig_Person::url() ) . '">' . SB_EmailSig_Admin::icon( 'plus' ) . 'Add person</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built escaped; greyed out at the limit (SB_EmailSig_Limits). ?>
					<?php endif; ?>
					<?php echo $everyone ? '' : SB_EmailSig_Switch::control( $groups[0]['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in control(). ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( $request ) : ?>
			<div class="sb-ui-bar__end"><?php echo SB_EmailSig_Limits::button( 'people', '<button type="button" class="button button-primary" data-sb-emailsig-request-open>' . SB_EmailSig_Admin::icon( 'plus' ) . 'Submit New Team Member</button>' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built escaped; greyed out at the limit (SB_EmailSig_Limits). ?></div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( $off ) : ?>
		<div class="notice notice-warning sb-emailsig-off-note"><p><?php echo esc_html( $company . ' is switched off. Its team page, people\'s pages and signatures don\'t work until it\'s back on.' ); ?></p></div>
	<?php endif; ?>

	<?php
	// Its people dimmed while it's off, as under its bar on everyone's page.
	if ( $off ) {
		echo '<div class="sb-emailsig-off-body">';
	}

	foreach ( $groups as $sb_group ) {
		if ( $everyone ) {
			// Its bar (SB_EmailSig_Admin::company_head(), as on the Signatures page).
			echo '<section class="sb-emailsig-company-group' . esc_attr( SB_EmailSig_Switch::off_class( $sb_group['id'] ) ) . '" data-sb-emailsig-group="' . (int) $sb_group['id'] . '">';
			SB_EmailSig_Admin::company_head( $sb_group['id'], count( $sb_group['active'] ), count( $sb_group['inactive'] ), count( $sb_group['deleted'] ) );
			echo '<div class="sb-emailsig-company-group__body">';
		}

		SB_EmailSig_Admin::section( 'active', $manage ? 'Active' : 'Team', $sb_group['active'], true );

		if ( $manage ) {
			SB_EmailSig_Admin::section( 'inactive', 'Inactive', $sb_group['inactive'], true );
			SB_EmailSig_Admin::deleted( $sb_group['deleted'], true );
		}

		if ( $everyone ) {
			echo '</div></section>';
		}
	}

	if ( $off ) {
		echo '</div>';
	}
	?>
	<p class="sb-emailsig-find-none" hidden>No one matches that.</p>

	<?php
	if ( $request ) {
		SB_EmailSig_Request::dialog( $groups[0]['id'] );
	}
	?>
</div>
<?php SB_EmailSig_Page::foot_assets(); ?>
<script src="<?php echo esc_url( SB_EMAILSIG_URL . 'assets/copy.js?ver=' . SB_EmailSig_Module::asset_version( 'assets/copy.js' ) ); ?>"></script>
<script>var sbEmailsigList = <?php echo wp_json_encode( $manage ? array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'sb_emailsig_active' ), 'company' => SB_EmailSig_Switch::config() ) : array() ); ?>;</script>
<script src="<?php echo esc_url( SB_EMAILSIG_URL . 'assets/list.js?ver=' . SB_EmailSig_Module::asset_version( 'assets/list.js' ) ); ?>"></script>
<?php SB_EmailSig_Live::script( array( 'where' => 'team', 'key' => isset( $_GET['sb_emailsigs'] ) ? sanitize_text_field( wp_unslash( $_GET['sb_emailsigs'] ) ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- the page's own key. ?>
<?php if ( $manage ) : ?>
<script>var sbEmailsigNotify = <?php echo wp_json_encode( SB_EmailSig_Notify::config() ); ?>;</script>
<script src="<?php echo esc_url( SB_EMAILSIG_URL . 'assets/notify.js?ver=' . SB_EmailSig_Module::asset_version( 'assets/notify.js' ) ); ?>"></script>
<?php endif; ?>
</body>
</html>
