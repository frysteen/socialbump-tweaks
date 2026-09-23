<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One module's admin screen: menu, banner, pages and admin bar rows.
 *
 * The merged SBSK_Settings / SBBT_Settings, minus everything that belongs to
 * the plugin as a whole. Updates, Publishing and Transfer are plugin level
 * and live with SB_Tweaks_Settings; a module's screen holds its Features
 * page, its group pages, any feature pages, and on the hub a Documentation
 * page for its own docs/context.md.
 *
 * One instance exists per enabled module, built by SB_Tweaks_Modules
 * alongside that module's SB_Tweaks_Features, which it reads everything
 * from. It never touches an option directly: saving is SB_Tweaks_Save's
 * job, and state comes from the Features loader.
 */
class SB_Tweaks_Screen {

	/** The module's declaration, as module.php returned it. */
	private $def;

	/** The module's features loader. */
	private $features;

	/** The accent inline style only needs adding once per request. */
	private static $accent_done = false;

	/** One admin-post handler serves every module's Documentation page. */
	private static $docs_hooked = false;

	public function __construct( array $def, SB_Tweaks_Features $features ) {
		$this->def      = $def;
		$this->features = $features;
	}

	/** The module's menu slug: the front door of all its pages. */
	public function slug() {
		return $this->def['menu']['slug'];
	}

	public function group_page_slug( $group ) {
		return $this->slug() . '-' . sanitize_key( $group );
	}

	public function feature_page_slug( $id ) {
		return $this->slug() . '-' . sanitize_key( $id );
	}

	public function boot() {
		add_action( 'admin_menu', [ $this, 'add_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'styles' ] );
		add_action( 'admin_head', [ $this, 'icon_styles' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 100 );

		// Drag to reorder and collapse on the Features page, kept per user.
		SB_Tweaks_Cards::register( $this->features->groups_option(), $this->slug() );

		if ( ! self::$docs_hooked ) {
			add_action( 'admin_post_sb_tweaks_save_module_docs', [ __CLASS__, 'save_docs' ] );

			self::$docs_hooked = true;
		}
	}

	/**
	 * The groups in the order this user arranged them on the Features page,
	 * or by name until they have. Feeds the menu, the tab bar and the admin
	 * bar, so the order is the same everywhere.
	 */
	public function ordered_groups() {
		$states   = $this->features->group_states();
		$sections = $this->features->groups();
		$titles   = [];

		foreach ( $states as $group => $on ) {
			$titles[ $group ] = isset( $sections[ $group ]['title'] ) ? $sections[ $group ]['title'] : $group;
		}

		$ids     = SB_Tweaks_Cards::sort( $titles, $this->features->groups_option() );
		$ordered = [];

		foreach ( $ids as $group ) {
			$ordered[ $group ] = $states[ $group ];
		}

		return $ordered;
	}

	public function add_menu() {
		$sections = $this->features->groups();

		add_menu_page(
			esc_html( $this->def['menu']['page_title'] ),
			esc_html( $this->def['menu']['label'] ),
			'manage_options',
			$this->slug(),
			[ $this, 'render_groups' ],
			self::brand_icon(),
			$this->menu_position()
		);

		// First sub item, so the menu reads "Features" rather than repeating the module name.
		add_submenu_page(
			$this->slug(),
			esc_html( $this->def['menu']['page_title'] ),
			esc_html__( 'Features', 'sb-tweaks' ),
			'manage_options',
			$this->slug(),
			[ $this, 'render_groups' ]
		);

		/**
		 * Each group that is switched on gets a page of its own, in the order
		 * the cards were arranged.
		 */
		foreach ( $this->ordered_groups() as $group => $on ) {
			if ( ! $on || ! $this->features->in_group( $group ) ) {
				continue;
			}

			$section = isset( $sections[ $group ] ) ? $sections[ $group ] : [];
			$title   = isset( $section['title'] ) ? $section['title'] : $group;

			add_submenu_page(
				$this->slug(),
				esc_html( $title ),
				esc_html( $title ),
				'manage_options',
				$this->group_page_slug( $group ),
				function () use ( $group ) {
					$this->render_group( $group );
				}
			);
		}

		foreach ( $this->features->enabled() as $id => $feature ) {
			/**
			 * A feature that is the only one in its group is already shown on
			 * the group page, so it does not need a second entry of its own.
			 */
			if ( count( $this->features->in_group( $feature['section'] ) ) === 1 ) {
				continue;
			}

			if ( empty( $feature['admin_page']['title'] ) || empty( $feature['admin_page']['render'] ) || ! is_callable( $feature['admin_page']['render'] ) ) {
				continue;
			}

			add_submenu_page(
				$this->slug(),
				esc_html( $feature['admin_page']['title'] ),
				esc_html( $feature['admin_page']['title'] ),
				'manage_options',
				$this->feature_page_slug( $id ),
				function () use ( $feature ) {
					$this->render_feature_page( $feature );
				}
			);
		}

		// The module's own notes, edited and shipped from the hub.
		if ( function_exists( 'sb_tweaks_is_hub' ) && sb_tweaks_is_hub() ) {
			add_submenu_page(
				$this->slug(),
				esc_html__( 'Documentation', 'sb-tweaks' ),
				esc_html__( 'Documentation', 'sb-tweaks' ),
				'manage_options',
				$this->slug() . '-documentation',
				[ $this, 'render_documentation' ]
			);
		}
	}

	/** Just below the menu named in the declaration, when it is there. */
	private function menu_position() {
		if ( $this->def['menu']['after'] === '' ) {
			return null;
		}

		global $menu;

		foreach ( (array) $menu as $position => $item ) {
			if ( isset( $item[2] ) && $item[2] === $this->def['menu']['after'] ) {
				return (float) $position + 0.1;
			}
		}

		return null;
	}

	/** The pages the tab bar and the admin bar shortcut list, in menu order. */
	public function bar_items() {
		$sections = $this->features->groups();
		$items    = [ $this->slug() => __( 'Features', 'sb-tweaks' ) ];

		foreach ( $this->ordered_groups() as $group => $on ) {
			if ( ! $on || ! $this->features->in_group( $group ) ) {
				continue;
			}

			$items[ $this->group_page_slug( $group ) ] = isset( $sections[ $group ]['title'] ) ? $sections[ $group ]['title'] : $group;
		}

		// A feature with a page of its own, unless its group page already is that page.
		foreach ( $this->features->enabled() as $id => $feature ) {
			if ( empty( $feature['admin_page']['title'] ) || count( $this->features->in_group( $feature['section'] ) ) === 1 ) {
				continue;
			}

			$items[ $this->feature_page_slug( $id ) ] = $feature['admin_page']['title'];
		}

		if ( function_exists( 'sb_tweaks_is_hub' ) && sb_tweaks_is_hub() ) {
			$items[ $this->slug() . '-documentation' ] = __( 'Documentation', 'sb-tweaks' );
		}

		return $items;
	}

	/** Hand this module's pages to the shared SocialBUMP menu in the admin bar. */
	public function admin_bar() {
		if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'SB_Tweaks_Bar' ) ) {
			return;
		}

		$items = $this->bar_items();

		// Which of our pages is open, if any. Nothing is current on the front end.
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$current = isset( $items[ $page ] ) ? $page : '';

		$pages = [];

		foreach ( $items as $slug => $title ) {
			$pages[] = [
				'title'   => $title,
				'href'    => admin_url( 'admin.php?page=' . $slug ),
				'current' => $slug === $current,
			];
		}

		SB_Tweaks_Bar::register(
			[
				'id'      => $this->def['bar']['id'],
				'module'  => $this->def['id'],
				'label'   => $this->def['bar']['label'],
				'href'    => admin_url( 'admin.php?page=' . $this->slug() ),
				'items'   => $pages,
				'current' => $current !== '',
			]
		);
	}

	/**
	 * The module's pages, along the bottom of the banner. The menu lists them
	 * already, but on a long admin menu the module can be a scroll away, and
	 * its pages only show while you are on one of them.
	 */
	private function render_nav() {
		$items = $this->bar_items();

		if ( count( $items ) < 2 ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		echo '<nav class="sb-tweaks-header__nav">';

		foreach ( $items as $slug => $title ) {
			echo '<a class="sb-tweaks-header__link' . ( $slug === $page ? ' is-current' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $title ) . '</a>';
		}

		echo '</nav>';
	}

	/**
	 * Dark SocialBUMP banner at the top of every page of this module. The hr
	 * after it tells WordPress to put admin notices below the banner, not
	 * inside it. The version badge is the plugin's: modules do not have
	 * versions of their own, and it links to Updates only once that page
	 * exists.
	 */
	public function render_header( $title, $intro = '' ) {
		$heading = $this->def['heading'];

		echo '<div class="sb-tweaks-header"><div class="sb-tweaks-header__brand">';
		echo '<a class="sb-tweaks-header__home" href="' . esc_url( admin_url( 'admin.php?page=' . $this->slug() ) ) . '">';
		echo '<img class="sb-tweaks-header__logo" src="' . esc_url( SB_TWEAKS_URL . 'assets/img/socialbump-logo-light.svg' ) . '" alt="SocialBUMP" width="203" height="28">';
		echo '</a>';
		echo '<h1 class="sb-tweaks-header__title">' . esc_html( $heading );

		if ( $title !== $heading ) {
			echo ' <span class="sb-tweaks-header__page">' . esc_html( $title ) . '</span>';
		}

		echo '</h1>';

		// No version badge: a module has no version of its own. It ships inside
		// SocialBUMP Tweaks, whose own pages show the plugin's version.
		echo '</div>';

		if ( $intro !== '' ) {
			echo '<p class="sb-tweaks-header__intro">' . esc_html( $intro ) . '</p>';
		}

		$this->render_nav();

		echo '</div><hr class="wp-header-end">';
	}

	/**
	 * The Features page: one switch per group. A group that is on gets its
	 * own page in the menu, holding the features that belong to it.
	 */
	public function render_groups() {
		$sections = $this->features->groups();
		$states   = $this->features->group_states();

		echo '<div class="wrap sb-tweaks-wrap">';
		$this->render_header( __( 'Features', 'sb-tweaks' ), __( 'Switch on the parts this site needs. Each one adds its own page below.', 'sb-tweaks' ) );

		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'sb-tweaks' ) . '</p></div>';
		}

		echo '<form method="post" autocomplete="off" data-sb-dirty action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sb_tweaks_save_groups">';
		echo '<input type="hidden" name="sb_tweaks_module" value="' . esc_attr( $this->def['id'] ) . '">';
		wp_nonce_field( 'sb_tweaks_save_groups' );

		// Reorder sits up here with the heading. Below the grid it was too
		// easy to hit on the way to Save changes.
		echo '<section class="sb-tweaks-section"><div class="sb-tweaks-section__head sb-tweaks-section__head--tools"><div><h2>' . esc_html__( 'Features', 'sb-tweaks' ) . '</h2><p>' . esc_html__( 'Each one switched on adds its own page to the menu. Reorder puts them in the order you want, here and in the menus, and each one collapses to its title.', 'sb-tweaks' ) . '</p></div>';
		echo SB_Tweaks_Cards::toolbar( $this->features->groups_option(), 'reorder' );
		echo '</div>';

		// By name until the user drags them; then in their order, new ones by name at the end.
		$titles = [];

		foreach ( $sections as $group => $section ) {
			$titles[ $group ] = isset( $section['title'] ) ? $section['title'] : $group;
		}

		$ordered = SB_Tweaks_Cards::sort( $titles, $this->features->groups_option() );

		echo SB_Tweaks_Cards::toolbar( $this->features->groups_option(), 'links' );
		echo '<div class="sb-tweaks-grid"' . SB_Tweaks_Cards::container_attributes( $this->features->groups_option() ) . '>';

		$states_of = $this->features->get_states();

		foreach ( $ordered as $group ) {
			$section = $sections[ $group ];

			// Listed in the order the group's own page draws them, so a wide
			// card is last in both places.
			$features = $this->page_order( $this->features->in_group( $group ) );

			if ( ! $features ) {
				continue;
			}

			$on    = ! empty( $states[ $group ] );
			$needs = $this->features->group_needs( $group );

			$card  = '<div class="sb-tweaks-card' . ( $on && ! $needs ? ' is-on' : '' ) . ( $needs ? ' is-unavailable' : '' ) . '"' . SB_Tweaks_Cards::card_attribute( $group ) . '>';
			$card .= '<div class="sb-tweaks-card__head"><h3>' . esc_html( $section['title'] ) . '</h3>';
			$card .= '<label class="sb-tweaks-switch"><input type="checkbox" name="sb_tweaks_groups[' . esc_attr( $group ) . ']" value="1" ' . checked( $on, true, false ) . ' ' . disabled( (bool) $needs, true, false ) . '>';
			$card .= '<span class="sb-tweaks-switch__track"><span class="sb-tweaks-switch__dot"></span></span>';
			$card .= '<span class="screen-reader-text">' . esc_html( $section['title'] ) . '</span></label></div>';
			$card .= '<p class="sb-tweaks-card__desc">' . esc_html( isset( $section['description'] ) ? $section['description'] : '' ) . '</p>';

			if ( $needs ) {
				/* translators: %s: plugin name(s) */
				$card .= '<p class="sb-tweaks-card__needs">' . sprintf( esc_html__( 'Needs %s installed and active.', 'sb-tweaks' ), esc_html( implode( ' and ', $needs ) ) ) . '</p>';
			}

			$card .= '<ul class="sb-tweaks-features">';

			foreach ( $features as $feature_id => $feature ) {
				// A feature with no switch is on whenever its group is: there
				// is no state stored for it, so asking for one left it looking
				// switched off with no way to switch it on.
				$always = ! empty( $feature['always'] );
				$lit    = $on && ( $always || ! empty( $states_of[ $feature_id ] ) ) && ! $this->features->missing( $feature_id ) && ! $this->features->unavailable( $feature_id );

				// A feature can report its own switches, so the list shows what is really on.
				$parts = ( ! empty( $feature['features'] ) && is_callable( $feature['features'] ) ) ? (array) call_user_func( $feature['features'] ) : [];

				if ( ! $parts ) {
					$parts = [ [ 'label' => $feature['title'], 'on' => true ] ];
				}

				foreach ( $parts as $part ) {
					$part_on = $lit && ! empty( $part['on'] );

					$card .= '<li class="' . ( $part_on ? 'is-on' : 'is-off' ) . '"><span class="sb-tweaks-dot"></span>' . esc_html( $part['label'] ) . '</li>';
				}
			}

			$card .= '</ul>';

			if ( $on ) {
				$card .= '<p class="sb-tweaks-card__link"><a href="' . esc_url( admin_url( 'admin.php?page=' . $this->group_page_slug( $group ) ) ) . '">' . esc_html__( 'Settings', 'sb-tweaks' ) . '</a></p>';
			}

			echo $card . '</div>';
		}

		echo '</div></section>';
		submit_button( esc_html__( 'Save changes', 'sb-tweaks' ), 'primary sb-save--clean' );
		echo '</form></div>';
	}

	/**
	 * One group page: the features that belong to it, each with its own
	 * switch. A group whose only feature brings its own page shows that page
	 * here rather than a list with one card on it.
	 */
	public function render_group( $group ) {
		$sections = $this->features->groups();
		$section  = isset( $sections[ $group ] ) ? $sections[ $group ] : [ 'title' => $group, 'description' => '' ];
		$features = $this->features->in_group( $group );

		echo '<div class="wrap sb-tweaks-wrap">';
		$this->render_header( $section['title'], isset( $section['description'] ) ? $section['description'] : '' );

		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'sb-tweaks' ) . '</p></div>';
		}

		// A single feature with a page of its own: show that page here.
		$only = count( $features ) === 1 ? reset( $features ) : null;

		if ( $only && ! empty( $only['admin_page']['render'] ) && is_callable( $only['admin_page']['render'] ) ) {
			call_user_func( $only['admin_page']['render'], $only );
			echo '</div>';

			return;
		}

		echo '<form method="post" autocomplete="off" data-sb-dirty action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sb_tweaks_save">';
		echo '<input type="hidden" name="sb_tweaks_module" value="' . esc_attr( $this->def['id'] ) . '">';
		echo '<input type="hidden" name="sb_tweaks_group" value="' . esc_attr( $group ) . '">';
		wp_nonce_field( 'sb_tweaks_save' );
		echo '<div class="sb-tweaks-grid">';

		$states = $this->features->get_states();

		// Wide cards last, the same order the Features page lists them in.
		foreach ( $this->page_order( $features ) as $id => $feature ) {
			$this->render_card( $id, $feature, $states );
		}

		echo '</div>';
		submit_button( esc_html__( 'Save changes', 'sb-tweaks' ), 'primary sb-save--clean' );
		echo '</form></div>';
	}

	/**
	 * Features in the order a page draws them: wide cards last. A wide card
	 * spans every column of the grid, so it can only sit at the bottom, and
	 * the Features page lists the same features and has to agree with it.
	 */
	private function page_order( $features ) {
		$normal = [];
		$wide   = [];

		foreach ( $features as $id => $feature ) {
			if ( ! empty( $feature['wide'] ) ) {
				$wide[ $id ] = $feature;
				continue;
			}

			$normal[ $id ] = $feature;
		}

		return $normal + $wide;
	}

	/** One feature card, with its switch, notes and any settings of its own. */
	private function render_card( $id, $feature, $states ) {
		$missing = $this->features->missing( $id );
		$blocked = $this->features->unavailable( $id );
		$always  = ! empty( $feature['always'] );
		$on      = $always ? ! $missing && ! $blocked : ( ! empty( $states[ $id ] ) && ! $missing && ! $blocked );

		// A feature with no switch is a settings card: it is always on, so a
		// toggle stuck in the on position would only invite someone to try
		// turning it off.
		printf(
			'<div class="sb-tweaks-card%1$s%2$s%5$s" id="sb-tweaks-%6$s-feature-%7$s"><div class="sb-tweaks-card__head"><h3>%3$s</h3>%4$s</div>',
			$on ? ' is-on' : '',
			( $missing || $blocked ) ? ' is-unavailable' : '',
			esc_html( $feature['title'] ),
			$always ? '' : sprintf(
				'<label class="sb-tweaks-switch"><input type="checkbox" name="sb_tweaks_features[%1$s]" value="1" %2$s %3$s><span class="sb-tweaks-switch__track"><span class="sb-tweaks-switch__dot"></span></span><span class="screen-reader-text">%4$s</span></label>',
				esc_attr( $id ),
				checked( $on, true, false ),
				disabled( (bool) $missing || (bool) $blocked, true, false ),
				esc_html( $feature['title'] )
			),
			empty( $feature['wide'] ) ? '' : ' sb-tweaks-card--wide',
			esc_attr( sanitize_key( $this->def['id'] ) ),
			esc_attr( sanitize_key( $id ) )
		);

		if ( $blocked ) {
			// A feature can point at the setting that is holding it back, so links are allowed here.
			echo '<p class="sb-tweaks-card__needs">' . wp_kses( $blocked, [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] ) . '</p>';
		}

		if ( $missing ) {
			printf(
				/* translators: %s: plugin name(s) */
				'<p class="sb-tweaks-card__needs">' . esc_html__( 'Needs %s installed and active.', 'sb-tweaks' ) . '</p>',
				esc_html( implode( ' and ', $missing ) )
			);
		}

		if ( $feature['description'] ) {
			echo '<p class="sb-tweaks-card__desc">' . esc_html( $feature['description'] ) . '</p>';
		}

		if ( ! $missing && ! $blocked && ! empty( $feature['settings'] ) ) {
			echo '<div class="sb-tweaks-card__settings">';

			foreach ( $feature['settings'] as $key => $field ) {
				SB_Tweaks_Fields::render( $this->def['id'], $id, $key, $field, $this->features->setting( $id, $key ) );
			}

			echo '</div>';
		}

		if ( $on && ! empty( $feature['admin_page']['title'] ) && count( $this->features->in_group( $feature['section'] ) ) > 1 ) {
			echo '<p class="sb-tweaks-card__link"><a href="' . esc_url( admin_url( 'admin.php?page=' . $this->feature_page_slug( $id ) ) ) . '">' . esc_html__( 'Settings', 'sb-tweaks' ) . '</a></p>';
		}

		echo '</div>';
	}

	/** Standard frame for a feature's own settings page. */
	public function render_feature_page( $feature ) {
		echo '<div class="wrap sb-tweaks-wrap">';
		$this->render_header( $feature['admin_page']['title'], isset( $feature['admin_page']['description'] ) ? $feature['admin_page']['description'] : '' );
		call_user_func( $feature['admin_page']['render'], $feature );
		echo '</div>';
	}

	/**
	 * The module's Documentation page, hub only: the prompt for starting a
	 * chat on this module, and its docs/context.md, editable in place. These
	 * notes ship inside the module folder, so every site carries them.
	 */
	public function render_documentation() {
		if ( ! function_exists( 'sb_tweaks_is_hub' ) || ! sb_tweaks_is_hub() ) {
			return;
		}

		echo '<div class="wrap sb-tweaks-wrap">';
		$this->render_header( __( 'Documentation', 'sb-tweaks' ), __( 'The prompt for starting a chat on this module, and the notes that ship with it.', 'sb-tweaks' ) );

		if ( isset( $_GET['docs'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Documentation saved.', 'sb-tweaks' ) . '</p></div>';
		}

		$prompt = $this->def['prompt'];

		if ( is_callable( $prompt ) ) {
			$prompt = (string) call_user_func( $prompt );
		}

		if ( ! is_string( $prompt ) || $prompt === '' ) {
			$prompt = sprintf(
				'Work on the %1$s module of the SocialBUMP Tweaks WordPress plugin, developed live on the hub plugins.socialbump.com.au through its Novamira MCP connector. Read modules/%2$s/docs/context.md inside the plugin folder first: it holds the notes for this module and how work is done here.',
				$this->def['title'],
				basename( untrailingslashit( $this->def['path'] ) )
			);
		}

		echo '<section class="sb-tweaks-section"><div class="sb-tweaks-section__head"><h2>' . esc_html__( 'Starting a chat', 'sb-tweaks' ) . '</h2><p>' . esc_html__( 'Paste this to begin a session on this module. Click the box to select all of it.', 'sb-tweaks' ) . '</p></div>';
		echo '<div class="sb-tweaks-section__body"><textarea class="large-text code" rows="5" readonly onclick="this.select();">' . esc_textarea( $prompt ) . '</textarea></div></section>';

		$file = $this->def['path'] . 'docs/context.md';
		$docs = file_exists( $file ) ? (string) file_get_contents( $file ) : '';

		echo '<section class="sb-tweaks-section"><div class="sb-tweaks-section__head"><h2>' . esc_html__( 'Notes', 'sb-tweaks' ) . '</h2><p>' . esc_html__( 'These are the notes a session reads first. They live in the module folder and go out with every release, so keep them current and keep secrets out.', 'sb-tweaks' ) . '</p></div>';
		echo '<div class="sb-tweaks-section__body">';
		echo '<form method="post" autocomplete="off" data-sb-dirty action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sb_tweaks_save_module_docs">';
		echo '<input type="hidden" name="sb_tweaks_module" value="' . esc_attr( $this->def['id'] ) . '">';
		wp_nonce_field( 'sb_tweaks_save_module_docs' );
		echo '<textarea class="large-text code" rows="24" name="sb_tweaks_docs">' . esc_textarea( $docs ) . '</textarea>';
		submit_button( esc_html__( 'Save changes', 'sb-tweaks' ), 'primary sb-save--clean' );
		echo '</form></div></section>';

		// What the module's standalone plugin shipped, release by release, before
		// it moved in. Kept as it was, and never edited from here.
		$archive = $this->def['path'] . 'docs/changes.md';

		if ( file_exists( $archive ) ) {
			echo '<section class="sb-tweaks-section"><div class="sb-tweaks-section__head"><h2>' . esc_html__( 'Changes before the merge', 'sb-tweaks' ) . '</h2><p>' . esc_html__( 'The release notes of the standalone plugin this module replaced, kept as they were.', 'sb-tweaks' ) . '</p></div>';
			echo '<div class="sb-tweaks-section__body"><textarea class="large-text code" rows="16" readonly>' . esc_textarea( (string) file_get_contents( $archive ) ) . '</textarea></div></section>';
		}

		echo '</div>';
	}

	/** Write a module's docs/context.md back to its folder, hub only. */
	public static function save_docs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sb-tweaks' ) );
		}

		check_admin_referer( 'sb_tweaks_save_module_docs' );

		if ( ! function_exists( 'sb_tweaks_is_hub' ) || ! sb_tweaks_is_hub() ) {
			wp_die( esc_html__( 'Documentation is edited on the hub only.', 'sb-tweaks' ) );
		}

		$module = isset( $_POST['sb_tweaks_module'] ) ? sanitize_key( wp_unslash( $_POST['sb_tweaks_module'] ) ) : '';
		$all    = SB_Tweaks_Modules::instance()->all();

		if ( ! isset( $all[ $module ] ) ) {
			wp_die( esc_html__( 'Unknown module.', 'sb-tweaks' ) );
		}

		$docs = isset( $_POST['sb_tweaks_docs'] ) ? (string) wp_unslash( $_POST['sb_tweaks_docs'] ) : '';

		// An empty save is far more likely a mistake than a request to erase
		// the notes, so it changes nothing.
		if ( trim( $docs ) !== '' ) {
			$dir = $all[ $module ]['path'] . 'docs/';

			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			file_put_contents( $dir . 'context.md', $docs );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page' => $all[ $module ]['menu']['slug'] . '-documentation',
					'docs' => 'saved',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function styles( $hook ) {
		if ( strpos( (string) $hook, $this->slug() ) === false ) {
			return;
		}

		$css = SB_TWEAKS_PATH . 'assets/css/admin.css';
		$ver = file_exists( $css ) ? SB_TWEAKS_VERSION . '.' . filemtime( $css ) : SB_TWEAKS_VERSION;

		wp_enqueue_style( 'sb-tweaks-admin', SB_TWEAKS_URL . 'assets/css/admin.css', [], $ver );

		// Match the card accent to the admin colour scheme the user has
		// chosen, once, however many module screens share the request.
		if ( ! self::$accent_done ) {
			wp_add_inline_style( 'sb-tweaks-admin', ':root{--sb-tweaks-accent:' . self::accent_colour() . ';}' );

			self::$accent_done = true;
		}

		// Tells you when there is something to save, and when there is not.
		$dirty = SB_TWEAKS_PATH . 'assets/js/save-state.js';

		if ( file_exists( $dirty ) ) {
			wp_enqueue_script( 'sb-tweaks-save-state', SB_TWEAKS_URL . 'assets/js/save-state.js', [], SB_TWEAKS_VERSION . '.' . filemtime( $dirty ), true );
		}

		// Colour swatches, hide-when fields, card switch styling and the
		// select all and none links.
		$js = SB_TWEAKS_PATH . 'assets/js/framework.js';

		if ( file_exists( $js ) ) {
			wp_enqueue_script( 'sb-tweaks-framework', SB_TWEAKS_URL . 'assets/js/framework.js', [ 'jquery' ], SB_TWEAKS_VERSION . '.' . filemtime( $js ), true );

			// The progress popup, for anything that takes more than a moment.
			if ( file_exists( SB_TWEAKS_PATH . 'assets/js/progress.js' ) ) {
				wp_enqueue_script( 'sb-tweaks-progress', SB_TWEAKS_URL . 'assets/js/progress.js', [], SB_TWEAKS_VERSION . '.' . filemtime( SB_TWEAKS_PATH . 'assets/js/progress.js' ), true );
			}
		}

		// Shared cards that drag and collapse.
		$cards = SB_TWEAKS_PATH . 'assets/js/module-cards.js';

		if ( file_exists( $cards ) ) {
			wp_enqueue_script( 'sb-module-cards', SB_TWEAKS_URL . 'assets/js/module-cards.js', [], SB_TWEAKS_VERSION . '.' . filemtime( $cards ), true );
		}
	}

	/** Make the mark dim and brighten like the Dashicons around it. */
	public function icon_styles() {
		$item = '#adminmenu #toplevel_page_' . $this->slug();

		echo '<style>' . $item . ' div.wp-menu-image.svg{opacity:0.6;transition:opacity .1s ease-in-out;}';
		echo $item . ':hover div.wp-menu-image.svg,' . $item . '.wp-has-current-submenu div.wp-menu-image.svg,' . $item . '.current div.wp-menu-image.svg{opacity:1;}</style>';
	}

	/**
	 * The SocialBUMP exclamation as a data URI for the admin menu.
	 *
	 * WordPress does not recolour an SVG menu icon, only Dashicons, so this
	 * one is white and the dimming is done in icon_styles(). Built by
	 * concatenation with chr( 34 ): a quote mangled in the middle of it
	 * produces markup that silently draws nothing.
	 */
	public static function brand_icon() {
		$q = chr( 34 );

		// Tall and narrow, so it is scaled to the height of the box and centred.
		$path = 'M10.94,30.2c1.24,1.24,1.86,2.75,1.86,4.54s-.62,3.3-1.86,4.54-2.75,1.86-4.54,1.86-3.3-.62-4.54-1.86-1.86-2.75-1.86-4.54.62-3.3,1.86-4.54,2.75-1.86,4.54-1.86,3.3.62,4.54,1.86ZM1.22,24.27L.13,1.4C.09.64.7,0,1.46,0h9.88c.76,0,1.37.64,1.34,1.4l-1.09,22.87c-.03.71-.62,1.27-1.34,1.27H2.56c-.71,0-1.3-.56-1.34-1.27Z';

		$svg  = '<svg xmlns=' . $q . 'http://www.w3.org/2000/svg' . $q . ' viewBox=' . $q . '0 0 20 20' . $q . '>';
		$svg .= '<g transform=' . $q . 'translate(7.2 1) scale(0.4376)' . $q . ' fill=' . $q . '#ffffff' . $q . '>';
		$svg .= '<path d=' . $q . $path . $q . '/></g></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/** How strongly coloured a hex value is, from 0 (grey) to 1. */
	public static function saturation( $hex ) {
		$raw = ltrim( (string) $hex, '#' );

		if ( strlen( $raw ) === 3 ) {
			$raw = $raw[0] . $raw[0] . $raw[1] . $raw[1] . $raw[2] . $raw[2];
		}

		if ( strlen( $raw ) !== 6 ) {
			return 0;
		}

		$rgb = [ hexdec( substr( $raw, 0, 2 ) ), hexdec( substr( $raw, 2, 2 ) ), hexdec( substr( $raw, 4, 2 ) ) ];
		$max = max( $rgb );

		return $max > 0 ? ( $max - min( $rgb ) ) / $max : 0;
	}

	/**
	 * The current admin colour scheme's accent.
	 *
	 * WordPress does not expose this directly: each scheme registers four
	 * swatch colours and the accent is not always in the same slot. The last
	 * two are the candidates, so this takes the more saturated of them, which
	 * matches what the scheme actually paints the current menu item with.
	 */
	public static function accent_colour() {
		global $_wp_admin_css_colors;

		$scheme = get_user_option( 'admin_color' );
		$colors = ( $scheme && isset( $_wp_admin_css_colors[ $scheme ]->colors ) ) ? (array) $_wp_admin_css_colors[ $scheme ]->colors : [];
		$colors = array_values(
			array_filter(
				$colors,
				function ( $hex ) {
					return (bool) sanitize_hex_color( $hex );
				}
			)
		);

		// A scheme with a strongly coloured focus colour is naming its accent directly.
		if ( $scheme && ! empty( $_wp_admin_css_colors[ $scheme ]->icon_colors['focus'] ) ) {
			$focus = sanitize_hex_color( $_wp_admin_css_colors[ $scheme ]->icon_colors['focus'] );

			if ( $focus && self::saturation( $focus ) >= 0.6 ) {
				return $focus;
			}
		}

		if ( count( $colors ) < 2 ) {
			return '#2271b1';
		}

		$pair      = array_slice( $colors, -2 );
		$highlight = $pair[0];
		$notice    = $pair[1];

		return self::saturation( $notice ) > self::saturation( $highlight ) + 0.15 ? $notice : $highlight;
	}
}
