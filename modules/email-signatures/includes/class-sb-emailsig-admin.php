<?php
/**
 * Admin: the Signatures page, the signature preview box on the edit screen,
 * list columns.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SB_EmailSig_Admin {

	public static function init() {
		add_action( 'add_meta_boxes_' . SB_EMAILSIG_CPT, array( __CLASS__, 'add_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'manage_' . SB_EMAILSIG_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . SB_EMAILSIG_CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'wp_ajax_sb_emailsig_active', array( __CLASS__, 'set_active' ) );
		// The Preview popup's template tabs: on the team page too, for anyone with the link.
		add_action( 'wp_ajax_sb_emailsig_card_sig', array( __CLASS__, 'card_sig' ) );
		add_action( 'wp_ajax_nopriv_sb_emailsig_card_sig', array( __CLASS__, 'card_sig' ) );
		// A template's Preview as one person (Sam, 31 October 2026): managers only.
		add_action( 'wp_ajax_sb_emailsig_tpl_sig', array( __CLASS__, 'tpl_sig' ) );
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		$ours   = $screen && SB_EMAILSIG_CPT === $screen->post_type;

		// The module's own pages (Templates) as well as the people screens.
		if ( ! $ours && strpos( (string) $hook, SB_EmailSig_Module::SLUG ) === false ) {
			return;
		}
		wp_enqueue_style( 'sb-emailsig-admin', SB_EMAILSIG_URL . 'assets/admin.css', array(), SB_EmailSig_Module::asset_version( 'assets/admin.css' ) );
		wp_enqueue_script( 'sb-emailsig-copy', SB_EMAILSIG_URL . 'assets/copy.js', array(), SB_EmailSig_Module::asset_version( 'assets/copy.js' ), true );
		// Image fields: the pencil replaces the image (assets/replace.js).
		wp_enqueue_script( 'sb-emailsig-replace', SB_EMAILSIG_URL . 'assets/replace.js', array(), SB_EmailSig_Module::asset_version( 'assets/replace.js' ), true );

		// Email link and Resend: the Signatures page and a person's edit screen.
		$signatures = strpos( (string) $hook, SB_EmailSig_Module::SLUG . '-signatures' ) !== false;
		if ( $signatures || ( $ours && $screen->base === 'post' ) ) {
			SB_EmailSig_Notify::register_confirm();
			wp_enqueue_script( 'sb-emailsig-notify', SB_EMAILSIG_URL . 'assets/notify.js', array( 'sb-tweaks-confirm' ), SB_EmailSig_Module::asset_version( 'assets/notify.js' ), true );
			wp_localize_script( 'sb-emailsig-notify', 'sbEmailsigNotify', SB_EmailSig_Notify::config() );
		}

		// The Templates page: Preview and Import (list.js), Add template and Export (templates.js), Delete asks first.
		$templates = strpos( (string) $hook, SB_EmailSig_Module::SLUG . '-templates' ) !== false && ! isset( $_GET['edit'] ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( $templates ) {
			SB_EmailSig_Notify::register_confirm();
			wp_enqueue_script( 'sb-emailsig-templates', SB_EMAILSIG_URL . 'assets/templates.js', array( 'sb-tweaks-confirm' ), SB_EmailSig_Module::asset_version( 'assets/templates.js' ), true );
		}

		// The Companies page: Delete asks first.
		if ( strpos( (string) $hook, SB_EmailSig_Module::SLUG . '-companies' ) !== false ) {
			SB_EmailSig_Notify::register_confirm();
			wp_enqueue_script( 'sb-tweaks-confirm' );
		}

		// Brand colours (Companies::swatches()): the popup to set a company's
		// four, on the Companies list and the Signatures page, for administrators.
		$companies_list = strpos( (string) $hook, SB_EmailSig_Module::SLUG . '-companies' ) !== false && ! isset( $_GET['company'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ( $companies_list || $signatures ) && current_user_can( 'manage_options' ) ) {
			SB_EmailSig_Notify::register_confirm();
			wp_enqueue_script( 'sb-emailsig-icon-builder', SB_EMAILSIG_URL . 'assets/icon-builder.js', array(), SB_EmailSig_Module::asset_version( 'assets/icon-builder.js' ), true );
			wp_enqueue_script( 'sb-emailsig-brand', SB_EMAILSIG_URL . 'assets/brand.js', array( 'jquery', 'sb-tweaks-confirm', 'sb-emailsig-icon-builder' ), SB_EmailSig_Module::asset_version( 'assets/brand.js' ), true );
			wp_localize_script(
				'sb-emailsig-brand',
				'sbEmailsigBrand',
				array(
					'ajax'    => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'sb_emailsig_brand' ),
					// The theme's colours, for the menu and to show a theme colour's hex.
					'palette' => SB_EmailSig_Styles::palette(),
					'colours' => (object) SB_EmailSig_Styles::colours(),
				)
			);
		}

		// Live updating on the Signatures page's list (not a person's Add or Edit page, or an import being checked).
		if ( $signatures && ! SB_EmailSig_Person::is_screen() && ! SB_EmailSig_Transfer::previewing() ) {
			wp_enqueue_script( 'sb-emailsig-live', SB_EMAILSIG_URL . 'assets/live.js', array( 'sb-emailsig-list' ), SB_EmailSig_Module::asset_version( 'assets/live.js' ), true );
			wp_localize_script( 'sb-emailsig-live', 'sbEmailsigLive', SB_EmailSig_Live::config( array( 'where' => 'admin' ) ) );
		}

		// The Signatures page's Active switches, the Preview popup there and on
		// Templates, and the companies' on or off switches on the Companies list.
		$companies_page = strpos( (string) $hook, SB_EmailSig_Module::SLUG . '-companies' ) !== false;
		if ( $signatures || $templates || $companies_page ) {
			wp_enqueue_script( 'sb-emailsig-list', SB_EMAILSIG_URL . 'assets/list.js', $templates || $companies_page ? array( 'sb-tweaks-confirm' ) : array(), SB_EmailSig_Module::asset_version( 'assets/list.js' ), true );
			wp_localize_script(
				'sb-emailsig-list',
				'sbEmailsigList',
				array(
					'ajax'    => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'sb_emailsig_active' ),
					'company' => SB_EmailSig_Switch::config(),
				)
			);
		}
	}

	public static function add_box() {
		add_meta_box( 'sb-emailsig-preview', 'Signature', array( __CLASS__, 'render_box' ), SB_EMAILSIG_CPT, 'normal', 'default' );
	}

	public static function render_box( $post ) {
		if ( 'auto-draft' === $post->post_status || ! get_field( 'sb_emailsig_name', $post->ID ) ) {
			echo '<p>Save the person to see their signature.</p>';
			return;
		}
		$html  = SB_EmailSig_Render::html( $post->ID );
		$url   = SB_EmailSig_CPT::page_url( $post->ID );
		$state = SB_EmailSig_Notify::state( $post->ID, SB_EmailSig_Notify::fingerprint( $html ) );

		// Tokens the staff page has no value for: they show as nothing.
		foreach ( SB_EmailSig_Staff::missing( $post->ID ) as $key => $tokens ) {
			$field = function_exists( 'acf_get_field' ) ? acf_get_field( $key ) : null;
			echo '<p class="sb-emailsig-person__check">' . esc_html( implode( ', ', $tokens ) . ' in ' . ( $field ? $field['label'] : 'a field' ) . ' is empty on their staff page.' ) . '</p>';
		}
		?>
		<div class="sb-emailsig-preview">
			<div class="sb-emailsig-preview__sig" id="sb-emailsig-admin-preview"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<script type="application/json" id="sb-emailsig-admin-preview-html"><?php echo wp_json_encode( $html, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		</div>
		<p class="sb-emailsig-preview__actions">
			<button type="button" class="button button-small button-primary" data-sb-emailsig-copy="sb-emailsig-admin-preview" data-sb-emailsig-status="sb-emailsig-admin-status">Copy signature</button>
			<button type="button" class="button button-small" data-sb-emailsig-copy-text="<?php echo esc_attr( $url ); ?>">Copy link</button>
			<a class="button button-small" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Open link</a>
			<?php echo SB_EmailSig_Notify::button( $post->ID, $state, 'button button-small' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in button(). ?>
			<?php echo SB_EmailSig_Notify::badge( $state ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>
			<span class="sb-emailsig-status" id="sb-emailsig-admin-status" role="status" aria-live="polite"></span>
		</p>
		<?php
	}

	/** People with a name, by name: ids. $statuses: publish (active), draft (not). */
	public static function people( array $statuses = array( 'publish' ) ) {
		$ids = get_posts(
			array(
				'post_type'      => SB_EMAILSIG_CPT,
				'post_status'    => $statuses,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		return array_values(
			array_filter(
				$ids,
				function ( $id ) {
					return trim( (string) get_field( 'sb_emailsig_name', $id ) ) !== '';
				}
			)
		);
	}

	/**
	 * The Signatures page: a bar with the team page's link, the people file
	 * (Export, Import) and Add person (SB_EmailSig_Person's page), then,
	 * straight on the page, a card per person with
	 * their photo (or a grey stand-in), name, job title and Active switch
	 * (published or draft), where their signature stands (Up to date, Changed
	 * since copied, Not copied yet), and Preview, Edit, Copy link, Open link
	 * and Email link (Resend when changed). Each card carries its signature
	 * off screen, for the Preview popup (list.js). No Copy signature here: the
	 * person copies it from their own page (Sam, 29 October 2026).
	 */
	public static function render_list() {
		// A person's Add or Edit page (SB_EmailSig_Person).
		if ( SB_EmailSig_Person::is_screen() ) {
			SB_EmailSig_Person::render();

			return;
		}

		// A people import being checked (SB_EmailSig_Transfer).
		if ( SB_EmailSig_Transfer::previewing() ) {
			SB_EmailSig_Transfer::render_preview();

			return;
		}

		$active   = self::people( array( 'publish' ) );
		$inactive = self::people( array( 'draft' ) );
		$deleted  = self::people( array( 'trash' ) );
		$team     = SB_EmailSig_Page::list_url();
		$adding   = self::can_manage() && current_user_can( 'publish_posts' );

		SB_EmailSig_Module::trashed_notice();

		// The bar: find someone and pick a company, the team page's link, the
		// people file and Add person.
		echo '<div class="sb-ui-bar sb-emailsig-bar">';

		$companies = SB_EmailSig_Company::all();
		self::filters( count( $active ) + count( $inactive ) + count( $deleted ) > 1, count( $companies ) > 1 ? $companies : array() );

		echo '<div class="sb-ui-bar__group"><span class="sb-ui-bar__label">Team page</span>';
		echo '<button type="button" class="button button-small sb-emailsig-icon" data-sb-emailsig-copy-text="' . esc_attr( $team ) . '" data-sb-tip="Copy link (every company, for those who manage)">' . self::icon( 'link' ) . '</button>';
		echo '<a class="button button-small sb-emailsig-icon" href="' . esc_url( $team ) . '" target="_blank" rel="noopener" data-sb-tip="Open team page (every company)">' . self::icon( 'open' ) . '</a>';
		echo '</div>';

		// People file: Export and Import (administrators).
		SB_EmailSig_Transfer::render_bar();

		if ( $adding ) {
			// How many there are, "People 1 of 1", and Add person greyed out at the limit (SB_EmailSig_Limits).
			echo '<div class="sb-ui-bar__end">' . SB_EmailSig_Limits::count( 'people' ) . SB_EmailSig_Limits::button( 'people', '<a class="button button-primary" href="' . esc_url( SB_EmailSig_Person::url() ) . '">' . self::icon( 'plus' ) . 'Add person</a>' ) . '</div>';
		}

		echo '</div>';

		if ( ! $active && ! $inactive && ! $deleted ) {
			echo '<div class="sb-ui-empty"><p>No people yet.</p></div>';

			return;
		}

		// Three sections (Sam, 30 October 2026): Active, Inactive, then Deleted
		// (the Trash) folded away at the bottom. The Active switch moves a card
		// between the first two (list.js); a section with no one in it hides,
		// except Active. With more than one company, the three under each
		// company's own bar, as on everyone's team page (Sam, 30 October 2026).
		if ( count( $companies ) > 1 ) {
			foreach ( $companies as $co ) {
				$a = self::of_company( $active, $co );
				$i = self::of_company( $inactive, $co );
				$d = self::of_company( $deleted, $co );

				echo '<section class="sb-emailsig-company-group' . esc_attr( SB_EmailSig_Switch::off_class( $co ) ) . '" data-sb-emailsig-group="' . (int) $co . '">';
				self::company_head( $co, count( $a ), count( $i ), count( $d ) );
				echo '<div class="sb-emailsig-company-group__body">';
				self::section( 'active', 'Active', $a );
				self::section( 'inactive', 'Inactive', $i );
				self::deleted( $d );
				echo '</div></section>';
			}
		} else {
			self::section( 'active', 'Active', $active );
			self::section( 'inactive', 'Inactive', $inactive );
			self::deleted( $deleted );
		}

		echo '<p class="sb-emailsig-find-none" hidden>No one matches that.</p>';
	}

	/** People in company $co. */
	public static function of_company( array $ids, $co ) {
		return array_values(
			array_filter(
				$ids,
				function ( $id ) use ( $co ) {
					return SB_EmailSig_Company::of_person( $id ) === (int) $co;
				}
			)
		);
	}

	/**
	 * A company's bar over its people, on the Signatures page and everyone's
	 * team page (Sam, 30 October 2026): its name (a click folds its people
	 * away, list.js), its brand colours, how many are Active, Inactive and
	 * Deleted (Active only for those who don't manage), then its team page's
	 * link and Edit company.
	 */
	public static function company_head( $co, $active, $inactive = 0, $deleted = 0 ) {
		$manage = self::can_manage();
		$name   = SB_EmailSig_Company::name( $co );
		$link   = SB_EmailSig_Page::list_url( $co );

		echo '<div class="sb-ui-bar sb-emailsig-company-head' . esc_attr( SB_EmailSig_Switch::off_class( $co ) ) . '">';
		echo '<button type="button" class="sb-emailsig-company-head__fold" aria-expanded="true" data-sb-emailsig-fold data-sb-tip="Show or hide ' . esc_attr( $name ) . '\'s people">' . self::icon( 'chevron' ) . '<h2>' . esc_html( $name ) . '</h2></button>';

		echo SB_EmailSig_Companies::swatches( $co ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in swatches().

		echo '<span class="sb-emailsig-company-head__stats">';
		if ( $manage ) {
			foreach ( array( 'active' => array( 'Active', $active ), 'inactive' => array( 'Inactive', $inactive ), 'deleted' => array( 'Deleted', $deleted ) ) as $key => $s ) {
				echo '<span class="sb-emailsig-company-head__stat sb-emailsig-company-head__stat--' . esc_attr( $key ) . '"><b data-sb-emailsig-stat="' . esc_attr( $key ) . '">' . (int) $s[1] . '</b> ' . esc_html( $s[0] ) . '</span>';
			}
		} else {
			echo '<span class="sb-emailsig-company-head__stat sb-emailsig-company-head__stat--active"><b data-sb-emailsig-stat="active">' . (int) $active . '</b> ' . esc_html( _n( 'person', 'people', (int) $active ) ) . '</span>';
		}
		echo '</span>';

		echo '<div class="sb-ui-bar__end">';
		echo '<button type="button" class="button button-small sb-emailsig-icon" data-sb-emailsig-copy-text="' . esc_attr( $link ) . '" data-sb-tip="Copy ' . esc_attr( $name ) . '\'s team page link">' . self::icon( 'link' ) . '</button>';
		echo '<a class="button button-small sb-emailsig-icon" href="' . esc_url( $link ) . '" target="_blank" rel="noopener" data-sb-tip="Open ' . esc_attr( $name ) . '\'s team page">' . self::icon( 'open' ) . '</a>';
		if ( $manage && current_user_can( 'manage_options' ) ) {
			echo '<a class="button button-small" href="' . esc_url( SB_EmailSig_Companies::url( $co ) ) . '">' . self::icon( 'edit' ) . 'Edit company</a>';
		}
		// On or off, far right (SB_EmailSig_Switch; administrators).
		echo SB_EmailSig_Switch::control( $co ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in control().
		echo '</div>';
		echo '</div>';
	}

	/**
	 * The search, the Company filter and Clear filters, for a bar (the
	 * Signatures page and the team pages; list.js does the filtering).
	 * $find: show the search. $companies: company ids for the filter, none
	 * to leave it out.
	 */
	public static function filters( $find, array $companies ) {
		if ( ! $find && ! $companies ) {
			return;
		}

		if ( $find ) {
			echo '<div class="sb-ui-bar__group sb-emailsig-find"><label class="screen-reader-text" for="sb-emailsig-find">Find someone</label>';
			echo '<input type="search" id="sb-emailsig-find" placeholder="Find someone" autocomplete="off" data-sb-emailsig-find></div>';
		}

		if ( $companies ) {
			echo '<div class="sb-ui-bar__group"><label class="sb-ui-bar__label" for="sb-emailsig-pick">Company</label>';
			echo '<select id="sb-emailsig-pick" data-sb-emailsig-pick><option value="">All companies</option>';

			foreach ( $companies as $co ) {
				echo '<option value="' . (int) $co . '">' . esc_html( SB_EmailSig_Company::name( $co ) ) . '</option>';
			}

			echo '</select></div>';
		}

		echo '<div class="sb-ui-bar__group sb-emailsig-clear" hidden><button type="button" class="button-link" data-sb-emailsig-clear>Clear filters</button></div>';
	}

	/**
	 * A section of people: its heading and count, then their cards. $front:
	 * the team page (SB_EmailSig_Page), where only administrators and editors
	 * get the switch, the states and Edit, Email and Delete.
	 */
	public static function section( $key, $title, array $people, $front = false ) {
		echo '<section class="sb-emailsig-people-section" data-sb-emailsig-section="' . esc_attr( $key ) . '"' . ( ! $people && $key !== 'active' ? ' hidden' : '' ) . '>';
		echo '<div class="sb-emailsig-people-head"><h2>' . esc_html( $title ) . '</h2><span class="sb-ui-count sb-emailsig-count sb-emailsig-count--' . esc_attr( $key ) . '" data-sb-emailsig-count>' . count( $people ) . '</span></div>';

		if ( $key === 'active' ) {
			echo '<p class="sb-emailsig-people-none"' . ( $people ? ' hidden' : '' ) . '>' . ( self::can_manage() ? 'No one is active. Switch someone on to add them here.' : 'No signatures yet.' ) . '</p>';
		}

		echo '<div class="sb-emailsig-people">';

		foreach ( $people as $id ) {
			self::card( $id, $front );
		}

		echo '</div></section>';
	}

	/**
	 * One person's card, as Active and Inactive show it. Everyone sees the
	 * photo, name, job, email and mobile, Preview, Copy link and Open their
	 * page; administrators and editors also get the Active switch, where the
	 * signature stands and the chips, Edit, Email link and Delete (Sam, 30
	 * October 2026). $front: on the team page, where Preview's action opens
	 * their page for everyone else and Delete comes back to the team page.
	 */
	public static function card( $id, $front = false ) {
		// In their company: its templates for the chips, its details for the signature.
		SB_EmailSig_Company::with(
			SB_EmailSig_Company::of_person( $id ),
			function () use ( $id, $front ) {
				self::draw_card( $id, $front );
			}
		);
	}

	/** What a search on the team page matches a card on: name, job, email, mobile. */
	private static function find_text( $id ) {
		$words = array();

		foreach ( array( 'sb_emailsig_name', 'sb_emailsig_job_title', 'sb_emailsig_email', 'sb_emailsig_mobile' ) as $field ) {
			$words[] = trim( (string) get_field( $field, $id ) );
		}

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( implode( ' ', $words ) ) : strtolower( implode( ' ', $words ) );
	}

	private static function draw_card( $id, $front ) {
		$manage = self::can_manage();
		$name   = trim( (string) get_field( 'sb_emailsig_name', $id ) );
		$active = get_post_status( $id ) === 'publish';
		$off    = SB_EmailSig_Switch::person_off( $id ); // Their company switched off: locked.
		$url    = SB_EmailSig_CPT::page_url( $id );
		$sig    = 'sb-emailsig-sig-' . $id;
		$html   = SB_EmailSig_Render::html( $id );
		$state  = $manage ? SB_EmailSig_Notify::state( $id, SB_EmailSig_Notify::fingerprint( $html ) ) : '';

		$job  = trim( (string) get_field( 'sb_emailsig_job_title', $id ) );
		$edit = $manage ? (string) get_edit_post_link( $id ) : '';

		// Just restored: highlighted, and list.js scrolls to it.
		$back = isset( $_GET['restored'] ) && absint( $_GET['restored'] ) === (int) $id; // phpcs:ignore WordPress.Security.NonceVerification

		echo '<article class="sb-tweaks-card sb-emailsig-person' . ( $active ? ' is-on' : '' ) . ( $back ? ' is-restored' : '' ) . '" data-sb-emailsig-card data-sb-emailsig-id="' . (int) $id . '" data-sb-emailsig-sort="' . esc_attr( strtolower( get_the_title( $id ) ) ) . '" data-sb-emailsig-text="' . esc_attr( self::find_text( $id ) ) . '" data-sb-emailsig-company="' . (int) SB_EmailSig_Company::of_person( $id ) . '">';

		echo '<div class="sb-emailsig-person__top">';
		self::photo( $id );
		echo '<div class="sb-emailsig-person__who">';

		if ( $manage ) {
			self::company_label( $id, $front );
		}

		echo '<h3 class="sb-emailsig-person__name">' . ( $edit !== '' ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . '</h3>';

		if ( $job !== '' ) {
			echo '<p class="sb-emailsig-person__job">' . esc_html( $job ) . '</p>';
		}

		self::contact( $id );

		echo '</div>';

		if ( $manage ) {
			echo '<label class="sb-tweaks-switch sb-emailsig-person__switch" data-sb-tip="' . esc_attr( $off ? 'Switch ' . SB_EmailSig_Company::name( SB_EmailSig_Company::of_person( $id ) ) . ' on first' : 'Active' ) . '"><input type="checkbox" data-sb-emailsig-active="' . (int) $id . '"' . checked( $active, true, false ) . disabled( $off, true, false ) . '><span class="sb-tweaks-switch__track"><span class="sb-tweaks-switch__dot"></span></span><span class="screen-reader-text">Active</span></label>';
		}

		echo '</div>';

		if ( $manage ) {
			// The chips (and the crop check) in a row, then whether their
			// signature has been copied on a line of its own (Sam, 30 October 2026).
			echo '<div class="sb-emailsig-person__state">';
			ob_start();
			self::chips( $id, $edit );

			// A crop set for another shape, fitted around its middle: worth a look.
			if ( SB_EmailSig_Photo::needs_check( $id ) ) {
				echo '<a class="sb-emailsig-person__check" href="' . esc_url( $edit ) . '#sb-emailsig-crop">Check the photo crop</a>';
			}

			$row = ob_get_clean();
			if ( $row !== '' ) {
				echo '<div class="sb-emailsig-person__chips">' . $row . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
			}
			echo '<div class="sb-emailsig-person__copied">' . ( $state === 'current' ? '<span class="sb-emailsig-state sb-emailsig-state--current">Up to date</span>' : SB_EmailSig_Notify::badge( $state ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup.
			echo '</div>';
		}

		// Preview first, then Edit, the links and the email, as icons.
		echo '<div class="sb-emailsig-person__actions">';
		// Preview's action: Edit for administrators and editors, else their page.
		echo '<button type="button" class="button button-small button-primary" data-sb-emailsig-preview="' . esc_attr( $sig ) . '" data-sb-emailsig-name="' . esc_attr( $name ) . '" data-sb-emailsig-edit="' . esc_url( $edit !== '' ? $edit : $url ) . '"' . ( $edit !== '' ? '' : ' data-sb-emailsig-action="Open their page"' ) . self::template_tabs( $id ) . '>' . self::icon( 'eye' ) . 'Preview</button>';

		if ( $edit !== '' ) {
			echo '<a class="button button-small sb-emailsig-icon" href="' . esc_url( $edit ) . '" data-sb-tip="Edit" aria-label="Edit ' . esc_attr( $name ) . '">' . self::icon( 'edit' ) . '</a>';
		}

		echo '<button type="button" class="button button-small sb-emailsig-icon" data-sb-emailsig-copy-text="' . esc_attr( $url ) . '" data-sb-tip="Copy link" aria-label="Copy ' . esc_attr( $name ) . '\'s link">' . self::icon( 'link' ) . '</button>';
		echo '<a class="button button-small sb-emailsig-icon" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" data-sb-tip="Open their page" aria-label="Open ' . esc_attr( $name ) . '\'s page">' . self::icon( 'open' ) . '</a>';
		echo $manage && ! $off ? self::send_button( $id, $state ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in send_button().

		// Delete, bottom right, asks first (Sam, 30 October 2026). To the Trash, like the person page's.
		if ( $manage && current_user_can( 'delete_post', $id ) ) {
			echo '<form class="sb-emailsig-person__delete" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="sb_emailsig_person_delete"><input type="hidden" name="sb_emailsig_person" value="' . (int) $id . '">';
			echo $front ? '<input type="hidden" name="sb_back" value="team">' : '';
			echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( SB_EmailSig_Person::NONCE . '_delete_' . $id ) ) . '">';
			echo '<button type="submit" class="button button-small sb-emailsig-icon sb-emailsig-icon--danger" data-sb-tip="Delete" aria-label="Delete ' . esc_attr( $name ) . '" data-sb-tweaks-confirm="' . esc_attr( 'Move ' . ( $name !== '' ? $name : 'this person' ) . ' to the Trash? Their signature stops working.' ) . '" data-sb-tweaks-confirm-title="Delete person" data-sb-tweaks-confirm-action="Delete">' . self::icon( 'trash' ) . '</button>';
			echo '</form>';
		}

		echo '</div>';
		echo '<span class="sb-emailsig-status" id="' . esc_attr( $sig ) . '-status" role="status" aria-live="polite"></span>';

		self::hidden_signature( $sig, $html );

		echo '</article>';
	}

	/**
	 * Deleted: people in the Trash, folded away at the bottom (a details
	 * element, closed). Their cards only offer Restore (back as Inactive, as
	 * WordPress restores) and Delete for good (asks first), with when they
	 * were deleted and when WordPress empties them from the Trash.
	 */
	public static function deleted( array $people, $front = false ) {
		// Open straight after deleting, restoring or deleting for good, to show where they went.
		$open = isset( $_GET['trashed'] ) || isset( $_GET['purged'] ); // phpcs:ignore WordPress.Security.NonceVerification

		echo '<details class="sb-emailsig-people-section sb-emailsig-people-section--deleted" data-sb-emailsig-section="deleted"' . ( $people ? '' : ' hidden' ) . ( $open ? ' open' : '' ) . '>';
		echo '<summary class="sb-emailsig-people-head"><h2>Deleted</h2><span class="sb-ui-count sb-emailsig-count sb-emailsig-count--deleted" data-sb-emailsig-count>' . count( $people ) . '</span>' . self::icon( 'chevron' ) . '</summary>';
		echo '<div class="sb-emailsig-people">';

		foreach ( $people as $id ) {
			self::deleted_card( $id, $front );
		}

		echo '</div></details>';
	}

	/** One person's card in Deleted: Restore and Delete for good. Live updating (SB_EmailSig_Live) draws one on its own too. */
	public static function deleted_card( $id, $front = false ) {
		$days = defined( 'EMPTY_TRASH_DAYS' ) ? (int) EMPTY_TRASH_DAYS : 30;
		$name = trim( (string) get_field( 'sb_emailsig_name', $id ) );
		$job  = trim( (string) get_field( 'sb_emailsig_job_title', $id ) );
		$when = (int) get_post_meta( $id, '_wp_trash_meta_time', true );
		$note = '<span>' . esc_html( $when ? 'Deleted ' . human_time_diff( $when ) . ' ago' : 'Deleted' ) . '</span>';

		if ( $when && $days > 0 ) {
			$note .= '<span>' . esc_html( 'Gone for good ' . wp_date( 'j M Y', $when + $days * DAY_IN_SECONDS ) ) . '</span>';
		}

		echo '<article class="sb-tweaks-card sb-emailsig-person sb-emailsig-person--deleted" data-sb-emailsig-id="' . (int) $id . '" data-sb-emailsig-sort="' . esc_attr( strtolower( get_the_title( $id ) ) ) . '" data-sb-emailsig-text="' . esc_attr( self::find_text( $id ) ) . '" data-sb-emailsig-company="' . (int) SB_EmailSig_Company::of_person( $id ) . '">';
		echo '<div class="sb-emailsig-person__top">';
		self::photo( $id );
		echo '<div class="sb-emailsig-person__who"><h3 class="sb-emailsig-person__name">' . esc_html( $name ) . '</h3>';
		echo $job !== '' ? '<p class="sb-emailsig-person__job">' . esc_html( $job ) . '</p>' : '';
		echo '<p class="sb-emailsig-person__contact">' . $note . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
		echo '</div></div>';

		echo '<div class="sb-emailsig-person__actions">';

		if ( current_user_can( 'delete_post', $id ) ) {
			self::person_form( 'restore', $id, $front, '<button type="submit" class="button button-small">' . self::icon( 'restore' ) . 'Restore</button>' );
			self::person_form( 'purge', $id, $front, '<button type="submit" class="button button-small sb-tweaks-button--danger sb-emailsig-person__purge" data-sb-tweaks-confirm="' . esc_attr( 'Delete ' . ( $name !== '' ? $name : 'this person' ) . ' for good? Their signature, photo and details go, and this can\'t be undone.' ) . '" data-sb-tweaks-confirm-title="Delete for good" data-sb-tweaks-confirm-action="Delete for good">' . self::icon( 'trash' ) . 'Delete for good</button>' );
		}

		echo '</div></article>';
	}

	/** A small form posting one person to admin-post.php (sb_emailsig_person_{$what}), around $button. */
	private static function person_form( $what, $id, $front, $button ) {
		echo '<form class="sb-emailsig-person__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sb_emailsig_person_' . esc_attr( $what ) . '"><input type="hidden" name="sb_emailsig_person" value="' . (int) $id . '">';
		echo $front ? '<input type="hidden" name="sb_back" value="team">' : '';
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( SB_EmailSig_Person::NONCE . '_' . $what . '_' . $id ) ) . '">';
		echo $button; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped by the caller.
		echo '</form>';
	}

	/**
	 * A little more about a person on their card (Sam, 30 October 2026):
	 * their email and mobile, then chips that only show when there's
	 * something to say: their own main template (not the company's), extra
	 * templates just for them, their own Info List lines, the company's lines
	 * hidden, and the staff page they're linked to.
	 */
	public static function contact( $id ) {
		$email  = trim( (string) get_field( 'sb_emailsig_email', $id ) );
		$mobile = trim( (string) get_field( 'sb_emailsig_mobile', $id ) );

		if ( $email !== '' || $mobile !== '' ) {
			echo '<p class="sb-emailsig-person__contact">';
			echo $email !== '' ? '<span>' . esc_html( $email ) . '</span>' : '';
			echo $mobile !== '' ? '<span>' . esc_html( $mobile ) . '</span>' : '';
			echo '</p>';
		}
	}

	/**
	 * Their company, a small label above their name, once there is more than
	 * one (Sam, 30 October 2026): to the company's page in WordPress, or on a
	 * team page to the company's own team page. Not shown under a company's
	 * heading on everyone's team page, where it would say it twice (CSS).
	 */
	private static function company_label( $id, $front ) {
		static $many = null;

		if ( null === $many ) {
			$many = count( SB_EmailSig_Company::all() ) > 1;
		}

		if ( ! $many ) {
			return;
		}

		$co   = SB_EmailSig_Company::of_person( $id );
		$name = SB_EmailSig_Company::name( $co );
		$href = $front ? SB_EmailSig_Page::list_url( $co ) : SB_EmailSig_Companies::url( $co );

		echo '<a class="sb-emailsig-person__company" href="' . esc_url( $href ) . '" data-sb-tip="' . esc_attr( $front ? 'Open ' . $name . '\'s team page' : 'Edit ' . $name ) . '">' . esc_html( $name ) . '</a>';
	}

	/**
	 * The chips above where their signature stands: only what applies (see
	 * contact()). $edit: their Edit page, for Image mismatch.
	 */
	public static function chips( $id, $edit = '' ) {
		$chips   = array();
		$company = SB_EmailSig_Templates::current();
		$main    = SB_EmailSig_Templates::for_person( $id );
		$set     = SB_EmailSig_Templates::company_set();

		if ( $main !== $company ) {
			$tpl     = SB_EmailSig_Templates::get( $main );
			$chips[] = array( 'Their main template', $tpl ? $tpl['name'] : '' );
		}

		$extra = array_diff( SB_EmailSig_Templates::available( $id ), array( $main ), $set );

		if ( $extra ) {
			$names   = array_map(
				function ( $t ) {
					$tpl = SB_EmailSig_Templates::get( $t );

					return $tpl ? $tpl['name'] : '';
				},
				$extra
			);
			$chips[] = array( 'Just for them: ' . implode( ', ', array_filter( $names ) ), '+' . count( $extra ) . ' ' . _n( 'template', 'templates', count( $extra ) ) );
		}

		$lines = (array) get_field( 'sb_emailsig_person_info', $id );
		$lines = array_filter( $lines );

		if ( $lines ) {
			$chips[] = array( 'Their own Info List lines', count( $lines ) . ' extra ' . _n( 'line', 'lines', count( $lines ) ) );
		}

		if ( get_field( 'sb_emailsig_person_info_hide', $id ) ) {
			$chips[] = array( 'The company\'s Additional Info List Items are left out', 'Company lines hidden' );
		}

		// Their staff page: Linked to its name, to edit it (Sam, 30 October
		// 2026: not to the page itself, as some are only listed, not public).
		// Image mismatch when their photo isn't the page's featured image.
		$staff = SB_EmailSig_Staff::page_id( $id );
		$extra = '';

		// Asked to be added on the team page (SB_EmailSig_Request), until made Active.
		$asked = SB_EmailSig_Request::requested( $id );
		if ( $asked ) {
			$extra .= '<span class="sb-emailsig-chip sb-emailsig-chip--warn" data-sb-tip="' . esc_attr( 'Asked to be added on the team page, ' . wp_date( get_option( 'date_format' ), $asked ) . '. Make them Active once they\'re set up' ) . '">Requested</span>';
		}

		if ( $staff && get_post_status( $staff ) ) {
			$title = get_the_title( $staff );
			$link  = (string) get_edit_post_link( $staff, 'raw' );
			$inner = '<span class="sb-emailsig-chip__label">Linked to:</span> ' . esc_html( $title );
			$extra = $link !== ''
				? '<a class="sb-emailsig-chip sb-emailsig-chip--linked" href="' . esc_url( $link ) . '" data-sb-tip="' . esc_attr( 'Edit ' . $title . '\'s staff page' ) . '">' . $inner . '</a>'
				: '<span class="sb-emailsig-chip sb-emailsig-chip--linked">' . $inner . '</span>';

			$thumb = (int) get_post_thumbnail_id( $staff );
			if ( $thumb && $thumb !== (int) get_field( 'sb_emailsig_photo', $id, false ) ) {
				$tip    = 'Their signature photo isn\'t the staff page\'s featured image';
				$extra .= $edit !== ''
					? '<a class="sb-emailsig-chip sb-emailsig-chip--warn" href="' . esc_url( $edit ) . '" data-sb-tip="' . esc_attr( $tip . '. Edit their signature' ) . '">Image mismatch</a>'
					: '<span class="sb-emailsig-chip sb-emailsig-chip--warn" data-sb-tip="' . esc_attr( $tip ) . '">Image mismatch</span>';
			}
		}

		$chips = array_filter(
			$chips,
			function ( $c ) {
				return $c[1] !== '';
			}
		);

		// A field filled from the staff page whose merge tag is now empty (or
		// no longer allowed): it comes out blank in the signature. Kept, not
		// cleared, as the staff page may only be empty for now (Sam, 30
		// October 2026). Email is required, so it's red.
		$names = array(
			'field_sb_emailsig_first_name'     => 'First name',
			'field_sb_emailsig_last_name'      => 'Last name',
			'field_sb_emailsig_name'           => 'Name',
			'field_sb_emailsig_qualifications' => 'Qualifications',
			'field_sb_emailsig_job_title'      => 'Job title',
			'field_sb_emailsig_email'          => 'Email',
			'field_sb_emailsig_mobile'         => 'Mobile',
		);
		foreach ( SB_EmailSig_Staff::missing( $id ) as $key => $tokens ) {
			$label  = ( $names[ $key ] ?? 'A field' ) . ' missing';
			$tip    = implode( ', ', $tokens ) . ' ' . _n( 'is', 'are', count( $tokens ) ) . ' empty on their staff page';
			$class  = 'sb-emailsig-chip ' . ( $key === 'field_sb_emailsig_email' ? 'sb-emailsig-chip--danger' : 'sb-emailsig-chip--warn' );
			$extra .= $edit !== ''
				? '<a class="' . $class . '" href="' . esc_url( $edit ) . '" data-sb-tip="' . esc_attr( $tip . '. Edit their signature' ) . '">' . esc_html( $label ) . '</a>'
				: '<span class="' . $class . '" data-sb-tip="' . esc_attr( $tip ) . '">' . esc_html( $label ) . '</span>';
		}

		echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.

		foreach ( $chips as $chip ) {
			if ( ! empty( $chip[2] ) ) {
				echo '<a class="sb-emailsig-chip" href="' . esc_url( $chip[2] ) . '" target="_blank" rel="noopener" data-sb-tip="' . esc_attr( $chip[0] ) . '">' . esc_html( $chip[1] ) . '</a>';
			} else {
				echo '<span class="sb-emailsig-chip" data-sb-tip="' . esc_attr( $chip[0] ) . '">' . esc_html( $chip[1] ) . '</span>';
			}
		}
	}

	/**
	 * Email link as an icon. Its popup starts on Updated Signature when the
	 * signature has changed since it was copied (Sam, 31 October 2026).
	 * Not shown without an email address.
	 */
	public static function send_button( $id, $state ) {
		$email = trim( (string) get_field( 'sb_emailsig_email', $id ) );

		if ( $email === '' ) {
			return '';
		}

		$title = 'Email ' . $email . ' their signature link';

		return '<button type="button" class="button button-small sb-emailsig-icon" data-sb-emailsig-send="' . (int) $id . '" data-sb-emailsig-icon data-sb-tip="' . esc_attr( $title ) . '" aria-label="' . esc_attr( $title ) . '">' . self::icon( 'mail' ) . '</button>';
	}

	/**
	 * A toolbar's name as a menu to open another one's page (Sam, 31 October
	 * 2026: as the company menu on a company's page): $items, each [ url,
	 * label, group ]; groups (company names) as optgroups when there is more
	 * than one. $current: this page's url. Just the name with fewer than two.
	 */
	public static function goto_menu( $current, $name, array $items, $label ) {
		static $script = false;

		if ( count( $items ) < 2 ) {
			return '<span class="sb-ui-bar__title">' . esc_html( $name ) . '</span>';
		}

		$groups = array_unique( array_column( $items, 2 ) );
		$out    = '<select class="sb-emailsig-bar__switch" data-sb-emailsig-goto aria-label="' . esc_attr( $label ) . '">';
		$open   = null;

		foreach ( $items as $item ) {
			if ( count( $groups ) > 1 && $item[2] !== $open ) {
				$out .= ( null !== $open ? '</optgroup>' : '' ) . '<optgroup label="' . esc_attr( $item[2] ) . '">';
				$open = $item[2];
			}

			$out .= '<option value="' . esc_url( $item[0] ) . '"' . selected( $item[0], $current, false ) . '>' . esc_html( $item[1] ) . '</option>';
		}

		$out .= ( null !== $open ? '</optgroup>' : '' ) . '</select>';

		// Picking one opens it; staying (unsaved changes) puts this one back.
		if ( ! $script ) {
			$script = true;
			$out   .= '<script>document.addEventListener("change",function(e){var s=e.target;if(!s.matches||!s.matches("[data-sb-emailsig-goto]")||!s.value)return;var b=s.querySelector("option[selected]");window.location.href=s.value;setTimeout(function(){if(b)s.value=b.value;},500);});</script>';
		}

		return $out;
	}

	/** A small line icon for the buttons. */
	public static function icon( $name ) {
		$paths = array(
			'eye'  => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
			'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
			'link' => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
			'open' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14L21 3"/>',
			'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
			'chevron' => '<path d="M6 9l6 6 6-6"/>',
			'restore' => '<path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"/><path d="M3 3v5h5"/>',
			'plus' => '<path d="M12 5v14M5 12h14"/>',
			'down' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 21h16"/>',
			'up'   => '<path d="M12 15V3M7 8l5-5 5 5"/><path d="M4 21h16"/>',
			'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
			'check' => '<path d="M20 6L9 17l-5-5"/>',
			'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
			'trash' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/>',
			'columns' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M15 4v16"/>',
			'palette' => '<path d="M12 3a9 9 0 0 0 0 18c1 0 1.5-.7 1.5-1.5 0-.4-.2-.8-.4-1.1-.3-.3-.4-.6-.4-1 0-.8.7-1.5 1.5-1.5H16a5 5 0 0 0 5-5c0-4.4-4-7.9-9-7.9z"/><circle cx="7.5" cy="11.5" r="1"/><circle cx="10.5" cy="7.5" r="1"/><circle cx="15.5" cy="8.5" r="1"/>',
			'close' => '<path d="M18 6L6 18M6 6l12 12"/>',
			'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
		);

		return '<svg class="sb-emailsig-svg" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( isset( $paths[ $name ] ) ? $paths[ $name ] : '' ) . '</svg>';
	}

	/** A person's photo, or a grey stand-in. */
	public static function photo( $id ) {
		$photo = SB_EmailSig_Photo::png_url( $id );

		if ( $photo ) {
			echo '<img class="sb-emailsig-person__photo" src="' . esc_url( $photo ) . '" width="96" alt="">';
		} else {
			echo '<span class="sb-emailsig-person__photo sb-emailsig-person__photo--none" aria-hidden="true"><svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg></span>';
		}
	}

	/** The signature off screen, with its exact HTML: what Copy signature copies. */
	public static function hidden_signature( $sig, $html ) {
		echo '<div class="sb-emailsig-person__sig" id="' . esc_attr( $sig ) . '" aria-hidden="true">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped in the template.
		echo '<script type="application/json" id="' . esc_attr( $sig ) . '-html">' . wp_json_encode( $html, JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';
	}

	/** Administrators and editors: who may switch people on and off, here and on the team page. */
	public static function can_manage() {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Preview's template tabs (Sam, 30 October 2026): with more than one
	 * template to pick from, the person's templates, main first, and what
	 * card_sig() needs to draw each (their page's key, as their page does).
	 */
	private static function template_tabs( $id ) {
		$list = array();

		foreach ( SB_EmailSig_Templates::available( $id ) as $tpl_id ) {
			$tpl = SB_EmailSig_Templates::get( $tpl_id );
			if ( $tpl ) {
				$list[] = array( (string) $tpl_id, $tpl['name'] );
			}
		}

		if ( count( $list ) < 2 ) {
			return '';
		}

		$data = array(
			'ajax' => admin_url( 'admin-ajax.php' ),
			'id'   => (int) $id,
			'key'  => SB_EmailSig_CPT::get_key( $id ),
			'list' => $list,
		);

		return ' data-sb-emailsig-tpls="' . esc_attr( wp_json_encode( $data ) ) . '"';
	}

	/** One person's signature in one of their templates, for Preview's tabs. */
	public static function card_sig() {
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- their page's key is the check, as on their page.
		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$tpl = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$ok = $id && get_post_type( $id ) === SB_EMAILSIG_CPT && $key !== '' && hash_equals( SB_EmailSig_CPT::get_key( $id ), $key )
			&& ( ( get_post_status( $id ) === 'publish' && ! SB_EmailSig_Switch::person_off( $id ) ) || current_user_can( 'edit_post', $id ) )
			&& in_array( $tpl, SB_EmailSig_Templates::available( $id ), true );

		if ( ! $ok ) {
			wp_send_json_error( null, 403 );
		}

		wp_send_json_success( SB_EmailSig_Render::html( $id, $tpl ) );
	}

	/**
	 * A template's Preview: everyone, to see it as (Sam, 31 October 2026),
	 * for data-sb-emailsig-people: [ id, name, company ], by company then
	 * name as the person page's menu (company '' with only one); Inactive
	 * people say so. Anyone, whatever their own templates, as nobody may use
	 * a new one yet. '' for those who can't manage.
	 */
	public static function people_pick( $template ) {
		static $list = null;

		if ( ! self::can_manage() ) {
			return '';
		}

		if ( $list === null ) {
			$list = array();
			$many = count( SB_EmailSig_Company::all() ) > 1;

			foreach ( self::people( array( 'publish', 'draft' ) ) as $pid ) {
				$list[] = array(
					(int) $pid,
					trim( (string) get_field( 'sb_emailsig_name', $pid ) ) . ( get_post_status( $pid ) === 'draft' ? ' (Inactive)' : '' ),
					$many ? SB_EmailSig_Company::name( SB_EmailSig_Company::of_person( $pid ) ) : '',
				);
			}

			usort(
				$list,
				function ( $a, $b ) {
					return array( $a[2], strtolower( $a[1] ) ) <=> array( $b[2], strtolower( $b[1] ) );
				}
			);
		}

		if ( ! $list ) {
			return '';
		}

		$data = array(
			'ajax'     => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'sb_emailsig_tpl_sig' ),
			'template' => (string) $template,
			'list'     => $list,
		);

		return ' data-sb-emailsig-people="' . esc_attr( wp_json_encode( $data ) ) . '"';
	}

	/** One person's signature in any template, for a template's Preview. */
	public static function tpl_sig() {
		check_ajax_referer( 'sb_emailsig_tpl_sig', 'nonce' );

		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$tpl = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';

		if ( ! $id || get_post_type( $id ) !== SB_EMAILSIG_CPT || ! self::can_manage() || ! current_user_can( 'edit_post', $id ) || ! SB_EmailSig_Templates::get( $tpl ) ) {
			wp_send_json_error( null, 403 );
		}

		wp_send_json_success( SB_EmailSig_Render::html( $id, $tpl ) );
	}

	/** The Active switch: publish or draft, straight away. */
	public static function set_active() {
		check_ajax_referer( 'sb_emailsig_active', 'nonce' );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id || get_post_type( $id ) !== SB_EMAILSIG_CPT || ! self::can_manage() || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( null, 403 );
		}

		$status = ! empty( $_POST['active'] ) ? 'publish' : 'draft';

		// Not on while their company is switched off (SB_EmailSig_Switch).
		if ( 'publish' === $status && SB_EmailSig_Switch::person_off( $id ) ) {
			wp_send_json_error( 'Switch ' . SB_EmailSig_Company::name( SB_EmailSig_Company::of_person( $id ) ) . ' on first.' );
		}

		// Not past this site's limit, set from the hub (SB_EmailSig_Limits).
		if ( 'publish' === $status && get_post_status( $id ) !== 'publish' && ! SB_EmailSig_Limits::may_be_active( $id ) ) {
			wp_send_json_error( SB_EmailSig_Limits::message( 'people' ) );
		}
		$result = wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => $status,
			),
			true
		);

		is_wp_error( $result ) ? wp_send_json_error() : wp_send_json_success( get_post_status( $id ) );
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $key => $label ) {
			if ( 'title' === $key ) {
				$new['sb_emailsig_photo'] = '';
				$new[ $key ]              = 'Name';
				$new['sb_emailsig_job']   = 'Job Title';
				$new['sb_emailsig_email'] = 'Email';
				continue;
			}
			$new[ $key ] = $label;
		}
		return $new;
	}

	public static function column( $col, $post_id ) {
		switch ( $col ) {
			case 'sb_emailsig_photo':
				$src = SB_EmailSig_Photo::png_url( $post_id );
				if ( $src ) {
					// The made image as it is: its own ratio and shape are baked in.
					echo '<img class="sb-emailsig-thumb" src="' . esc_url( $src ) . '" width="80" alt="" />';
				}
				break;
			case 'sb_emailsig_job':
				echo esc_html( (string) get_field( 'sb_emailsig_job_title', $post_id ) );
				break;
			case 'sb_emailsig_email':
				echo esc_html( (string) get_field( 'sb_emailsig_email', $post_id ) );
				break;
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( SB_EMAILSIG_CPT === $post->post_type && 'trash' !== $post->post_status ) {
			$actions['sb_emailsig_page'] = '<a href="' . esc_url( SB_EmailSig_CPT::page_url( $post->ID ) ) . '" target="_blank" rel="noopener">Signature page</a>';
		}
		return $actions;
	}
}
