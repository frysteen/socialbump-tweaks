<?php
/**
 * Front-end signature page, in the SocialBUMP UI look (Sam, 30 October
 * 2026). Available: $post_id, $sigs (each template they can pick, main first:
 * name, html, mark), $html (the main one's), $name, $title. Copy signature
 * records the version copied (SB_EmailSig_Notify). With more than one
 * template, tabs swap the signature, its copy buttons and the HTML code box
 * (assets/copy.js). The signature sits on white, as in an email. $head:
 * their company's circle beside the heading (SB_EmailSig_Page::head_mark()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$icons = SB_EMAILSIG_URL . 'assets/guides/';
$mark  = SB_EmailSig_Notify::mark_attr( $post_id, SB_EmailSig_Notify::fingerprint( $html ) );
// Copy link for anyone, to pass the page on; Email link for those who manage (Sam, 31 October 2026).
$manage = SB_EmailSig_Admin::can_manage();
$link   = SB_EmailSig_CPT::page_url( $post_id );
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
<div class="sb-tweaks-wrap sb-emailsig-front sb-emailsig-front--narrow">

	<header class="sb-emailsig-front__head<?php echo $head !== '' ? ' has-logo' : ''; ?>">
		<?php echo $head; // phpcs:ignore WordPress.Security.EscapeOutput -- built escaped by head_mark(). ?>
		<div class="sb-emailsig-front__headtext">
			<h1><?php echo esc_html( $name !== '' ? $name : 'Your email signature' ); ?></h1>
			<p class="sb-emailsig-front__lead">Copy your signature here, then add it to your mail app with the steps below.</p>
		</div>
	</header>

	<section class="sb-tweaks-card sb-emailsig-front__card">
		<div class="sb-tweaks-card__head">
			<h3>Your signature</h3>
			<?php if ( count( $sigs ) > 1 ) : // Each template they can use, main first: tabs (assets/copy.js). ?>
				<div class="sb-ui-seg" role="group" aria-label="Signature style" data-sb-emailsig-style-tabs="sb-emailsig-preview">
					<?php $sb_first = true; foreach ( $sigs as $sig_id => $sig ) : ?>
						<button type="button" data-sb-emailsig-style-tab="<?php echo esc_attr( $sig_id ); ?>"<?php echo $sb_first ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $sig['name'] ); ?></button>
					<?php $sb_first = false; endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( count( $sigs ) > 1 ) : ?>
			<script type="application/json" id="sb-emailsig-preview-styles"><?php echo wp_json_encode( $sigs, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		<?php endif; ?>
		<div class="sb-emailsig-preview-pop sb-emailsig-front__sig"><div class="sb-emailsig-preview-pop__sig" id="sb-emailsig-preview"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped in the template. ?></div></div>
		<script type="application/json" id="sb-emailsig-preview-html"><?php echo wp_json_encode( $html, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		<div class="sb-emailsig-front__actions">
			<button type="button" class="button button-primary button-hero" data-sb-emailsig-copy="sb-emailsig-preview" data-sb-emailsig-status="sb-emailsig-status"<?php echo $mark; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in mark_attr(). ?>><?php echo SB_EmailSig_Admin::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>Copy signature</button>
			<span class="status" id="sb-emailsig-status" role="status" aria-live="polite"></span>
			<span class="sb-emailsig-front__tools">
				<button type="button" class="button sb-emailsig-icon" data-sb-emailsig-copy-text="<?php echo esc_attr( $link ); ?>" data-sb-tip="Copy link to this page" aria-label="Copy link to this page"><?php echo SB_EmailSig_Admin::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></button>
				<?php echo $manage ? SB_EmailSig_Admin::send_button( $post_id, SB_EmailSig_Notify::state( $post_id, SB_EmailSig_Notify::fingerprint( $html ) ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in send_button(). ?>
			</span>
		</div>
	</section>

	<section class="sb-tweaks-card sb-emailsig-front__card guides">
		<div class="sb-tweaks-card__head"><h3 class="guides__title" id="guides-title">Pick your mail app</h3></div>
		<div class="picker" data-sb-emailsig-picker>
			<button type="button" class="picker__button" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="guides-title picker-current"><img src="<?php echo esc_url( $icons . 'apple-mail.png' ); ?>" alt="" width="24" height="24"><span id="picker-current">Apple Mail</span><svg class="picker__chevron" width="12" height="8" viewBox="0 0 12 8" aria-hidden="true"><path d="M1 1l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2"/></svg></button>
			<ul class="picker__list" role="listbox" tabindex="-1" aria-labelledby="guides-title" aria-activedescendant="pick-apple" hidden>
				<li role="option" id="pick-apple" data-panel="guide-apple" aria-selected="true"><img src="<?php echo esc_url( $icons . 'apple-mail.png' ); ?>" alt="" width="24" height="24"><span>Apple Mail</span></li>
				<li role="option" id="pick-ios" data-panel="guide-ios" aria-selected="false"><img src="<?php echo esc_url( $icons . 'iphone-ipad.png' ); ?>" alt="" width="24" height="24"><span>iPhone or iPad</span></li>
				<li role="option" id="pick-gmail" data-panel="guide-gmail" aria-selected="false"><img src="<?php echo esc_url( $icons . 'gmail.png' ); ?>" alt="" width="24" height="24"><span>Gmail</span></li>
				<li role="option" id="pick-gmail-app" data-panel="guide-gmail-app" aria-selected="false"><img src="<?php echo esc_url( $icons . 'gmail.png' ); ?>" alt="" width="24" height="24"><span>Gmail phone app</span></li>
				<li role="option" id="pick-outlook-web" data-panel="guide-outlook-web" aria-selected="false"><img src="<?php echo esc_url( $icons . 'office-365.png' ); ?>" alt="" width="24" height="24"><span>Outlook.com or Office 365</span></li>
				<li role="option" id="pick-outlook-app" data-panel="guide-outlook-app" aria-selected="false"><img src="<?php echo esc_url( $icons . 'outlook-app.png' ); ?>" alt="" width="24" height="24"><span>Outlook desktop app</span></li>
				<li role="option" id="pick-outlook-phone" data-panel="guide-outlook-phone" aria-selected="false"><img src="<?php echo esc_url( $icons . 'outlook-app.png' ); ?>" alt="" width="24" height="24"><span>Outlook phone app</span></li>
				<li role="option" id="pick-thunderbird" data-panel="guide-thunderbird" aria-selected="false"><img src="<?php echo esc_url( $icons . 'thunderbird.png' ); ?>" alt="" width="24" height="24"><span>Thunderbird</span></li>
				<li role="option" id="pick-yahoo" data-panel="guide-yahoo" aria-selected="false"><img src="<?php echo esc_url( $icons . 'yahoo.png' ); ?>" alt="" width="24" height="24"><span>Yahoo Mail</span></li>
				<li role="option" id="pick-code" data-panel="guide-code" aria-selected="false"><img src="<?php echo esc_url( $icons . 'html-code.png' ); ?>" alt="" width="24" height="24"><span>HTML code</span></li>
			</ul>
		</div>

		<div class="panel" id="guide-apple">
			<h3>Apple Mail on a Mac</h3>
			<ol>
				<li>Open this page in Safari or Chrome on your Mac and click <strong>Copy signature</strong>.</li>
				<li>In Mail, go to <strong>Mail &gt; Settings &gt; Signatures</strong>.</li>
				<li>Pick your email account in the left column.</li>
				<li>Click <strong>+</strong> to add a signature and give it a name.</li>
				<li>Untick <strong>Always match my default message font</strong>.</li>
				<li>Click in the signature box, delete what's there and paste with <strong>Command + V</strong>.</li>
				<li>Set it in the <strong>Choose Signature</strong> menu, close Settings and start a new email to check it.</li>
			</ol>
			<p class="note">The preview in Settings often shows blank images or odd text. That's normal. A new email shows it properly.</p>
		</div>

		<div class="panel" id="guide-ios" hidden>
			<h3>Mail on an iPhone or iPad</h3>
			<ol>
				<li>Open this page on your iPhone and tap <strong>Copy signature</strong>.</li>
				<li>Go to <strong>Settings &gt; Apps &gt; Mail &gt; Signature</strong> (on older iOS it's <strong>Settings &gt; Mail &gt; Signature</strong>).</li>
				<li>Choose <strong>All Accounts</strong> or your account, clear the box, then tap and hold and choose <strong>Paste</strong>.</li>
				<li>If the pasted signature doesn't look like the one you copied (often everything looks huge), straight away <strong>shake your phone</strong> and tap <strong>Undo</strong>. That takes off the styling Mail adds and puts yours back. If it already looks right, skip this: shaking would undo the paste.</li>
				<li>Leave Settings and send yourself a test email.</li>
			</ol>
			<p class="note">If the shake doesn't bring up Undo, check Settings &gt; Accessibility &gt; Touch &gt; Shake to Undo is on.</p>
		</div>

		<div class="panel" id="guide-gmail" hidden>
			<h3>Gmail in a web browser</h3>
			<ol>
				<li>Click <strong>Copy signature</strong> on this page.</li>
				<li>In Gmail, click the <strong>Settings</strong> cog at the top right, then <strong>See all settings</strong>.</li>
				<li>On the <strong>General</strong> tab, scroll down to <strong>Signature</strong> and click <strong>Create new</strong>. Give it a name.</li>
				<li>Click in the signature box and paste with <strong>Ctrl + V</strong> (<strong>Command + V</strong> on a Mac).</li>
				<li>Under <strong>Signature defaults</strong>, choose it for new emails, and for replies and forwards if you want it there too.</li>
				<li>Scroll to the bottom and click <strong>Save changes</strong>.</li>
			</ol>
		</div>

		<div class="panel" id="guide-gmail-app" hidden>
			<h3>Gmail app on a phone or tablet</h3>
			<p>The Gmail app uses the signature you set up in Gmail on a computer, as long as the app's own signature is left empty.</p>
			<ol>
				<li>Set up your signature in Gmail on a computer first. Choose <strong>Gmail</strong> in the list above for the steps.</li>
				<li>Open the Gmail app, tap the menu (three lines) at the top left, then <strong>Settings</strong>.</li>
				<li><strong>iPhone or iPad:</strong> under <strong>Compose and Reply</strong>, tap <strong>Signature settings</strong> and make sure <strong>Mobile signature</strong> is turned off.</li>
				<li><strong>Android:</strong> tap your account, then <strong>Mobile signature</strong>. Clear the box and tap <strong>OK</strong>.</li>
				<li>Start a new email in the app to check it.</li>
			</ol>
			<p class="note">If you use more than one account in the app, do this for each one.</p>
		</div>

		<div class="panel" id="guide-outlook-web" hidden>
			<h3>Outlook.com or Office 365 in a web browser</h3>
			<ol>
				<li>Click <strong>Copy signature</strong> on this page.</li>
				<li>In Outlook, click the <strong>Settings</strong> cog at the top right, then go to <strong>Account &gt; Signatures</strong>.</li>
				<li>Add a new signature and give it a name.</li>
				<li>Click in the signature box and paste with <strong>Ctrl + V</strong> (<strong>Command + V</strong> on a Mac).</li>
				<li>Choose it as the default for new messages, and for replies and forwards if you want it there too.</li>
				<li>Click <strong>Save</strong>.</li>
			</ol>
		</div>

		<div class="panel" id="guide-outlook-app" hidden>
			<h3>Outlook app on Windows (new Outlook)</h3>
			<ol>
				<li>Click <strong>Copy signature</strong> on this page.</li>
				<li>In Outlook, click the <strong>Settings</strong> cog, then <strong>Accounts &gt; Signatures</strong>.</li>
				<li>Click <strong>Add signature</strong>, give it a name and paste into the box with <strong>Ctrl + V</strong>.</li>
				<li>Tick the boxes underneath to add it to new messages, and to replies and forwards if you want.</li>
				<li>Click <strong>Save</strong>.</li>
			</ol>
			<h3>Outlook app on Windows (classic Outlook)</h3>
			<ol>
				<li>Click <strong>Copy signature</strong> on this page.</li>
				<li>Start a new email. On the <strong>Message</strong> tab, click <strong>Signature &gt; Signatures</strong>.</li>
				<li>Click <strong>New</strong> and give it a name.</li>
				<li>Click in the <strong>Edit signature</strong> box and paste with <strong>Ctrl + V</strong>.</li>
				<li>Under <strong>Choose default signature</strong>, pick it for <strong>New messages</strong> and <strong>Replies/forwards</strong>, then click <strong>OK</strong>.</li>
			</ol>
			<p class="note">The email you have open won't pick up a new default signature. Start a fresh one to check it.</p>
			<h3>Outlook app on a Mac</h3>
			<ol>
				<li>Click <strong>Copy signature</strong> on this page.</li>
				<li>In Outlook, go to <strong>Outlook &gt; Settings</strong>, then <strong>Signatures</strong> (under Mail).</li>
				<li>Click <strong>+</strong> to add a signature, give it a name and paste into the editor with <strong>Command + V</strong>.</li>
				<li>Under <strong>Set default signature</strong>, choose it for new messages, and for replies or forwards if you want.</li>
				<li>Close the Signatures window.</li>
			</ol>
		</div>

		<div class="panel" id="guide-outlook-phone" hidden>
			<h3>Outlook app on a phone or tablet</h3>
			<ol>
				<li>Open this page on your phone and tap <strong>Copy signature</strong>.</li>
				<li>In the Outlook app, open <strong>Settings</strong>, then under <strong>Mail</strong> tap <strong>Signature</strong>.</li>
				<li>If you have more than one account, turn on <strong>Per Account Signature</strong> to set one just for this account.</li>
				<li>Clear the box, then tap and hold and choose <strong>Paste</strong>.</li>
				<li>Leave Settings and start a new email to check it.</li>
			</ol>
			<p class="note">The Outlook phone app can drop some of the formatting. Your full signature still goes out from Outlook on your computer.</p>
		</div>

		<div class="panel" id="guide-thunderbird" hidden>
			<h3>Thunderbird</h3>
			<p>Thunderbird takes the signature as HTML code, so use this button instead of Copy signature.</p>
			<p><button type="button" class="button" data-sb-emailsig-copy-code="sb-emailsig-preview" data-sb-emailsig-status="sb-emailsig-code-status"<?php echo $mark; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in mark_attr(). ?>>Copy HTML code</button> <span class="status" id="sb-emailsig-code-status" role="status" aria-live="polite"></span></p>
			<ol>
				<li>In Thunderbird, click the menu button (three lines) and choose <strong>Account Settings</strong>.</li>
				<li>Select your email account in the left column.</li>
				<li>Tick <strong>Use HTML</strong>.</li>
				<li>Click in the <strong>Signature text</strong> box, delete what's there and paste the code.</li>
				<li>Close the Account Settings tab and start a new email to check it.</li>
			</ol>
		</div>

		<div class="panel" id="guide-yahoo" hidden>
			<h3>Yahoo Mail in a web browser</h3>
			<ol>
				<li>Click <strong>Copy signature</strong> on this page.</li>
				<li>In Yahoo Mail, open <strong>Settings</strong>, then <strong>More Settings</strong>, then <strong>Writing email</strong>.</li>
				<li>Turn on the signature switch next to your email address.</li>
				<li>Click in the signature box and paste with <strong>Ctrl + V</strong> (<strong>Command + V</strong> on a Mac).</li>
				<li>Start a new email to check it.</li>
			</ol>
		</div>

		<div class="panel" id="guide-code" hidden>
			<h3>HTML code</h3>
			<p>Some email apps and signature tools want the signature as HTML code instead of a paste.</p>
			<p><button type="button" class="button" data-sb-emailsig-copy-code="sb-emailsig-preview" data-sb-emailsig-status="sb-emailsig-raw-status"<?php echo $mark; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in mark_attr(). ?>>Copy HTML code</button> <span class="status" id="sb-emailsig-raw-status" role="status" aria-live="polite"></span></p>
			<textarea class="code sb-emailsig-front__code" readonly rows="6" aria-label="Signature HTML code"><?php echo esc_textarea( $html ); ?></textarea>
		</div>
	</section>

</div>
<?php SB_EmailSig_Page::foot_assets(); ?>
<script src="<?php echo esc_url( SB_EMAILSIG_URL . 'assets/copy.js?ver=' . SB_EmailSig_Module::asset_version( 'assets/copy.js' ) ); ?>"></script>
<?php SB_EmailSig_Live::script( array( 'where' => 'person', 'person' => (int) $post_id, 'key' => SB_EmailSig_CPT::get_key( $post_id ) ) ); ?>
<?php if ( $manage ) : ?>
<script>var sbEmailsigNotify = <?php echo wp_json_encode( SB_EmailSig_Notify::config() ); ?>;</script>
<script src="<?php echo esc_url( SB_EMAILSIG_URL . 'assets/notify.js?ver=' . SB_EmailSig_Module::asset_version( 'assets/notify.js' ) ); ?>"></script>
<?php endif; ?>
</body>
</html>
