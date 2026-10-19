<?php
/**
 * College Manager → Header & Footer: the settings screen (tabs), contrast
 * warnings, menu-location status, export / import / reset.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings screen for MCM_Site_Chrome.
 */
class MCM_Site_Chrome_Settings {

	/** Screen slug. */
	const SLUG = 'mcm-site-chrome';

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 21 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_post_mcm_hf_export', array( $this, 'export' ) );
		add_action( 'admin_post_mcm_hf_import', array( $this, 'import' ) );
		add_action( 'admin_post_mcm_hf_reset', array( $this, 'reset' ) );
	}

	/** College Manager → Header & Footer. */
	public function menu() {
		add_submenu_page(
			'mcm-settings',
			__( 'Header & Footer', 'medical-college-manager' ),
			__( 'Header & Footer', 'medical-college-manager' ),
			'manage_options',
			self::SLUG,
			array( $this, 'page' )
		);
	}

	/** Register the option. */
	public function register() {
		register_setting(
			'mcm_site_chrome',
			MCM_Site_Chrome::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'MCM_Site_Chrome', 'sanitize' ),
				'default'           => MCM_Site_Chrome::defaults(),
			)
		);
	}

	/**
	 * Tab labels.
	 *
	 * @return array<string,string>
	 */
	private static function tab_labels() {
		return array(
			'general'    => __( 'General', 'medical-college-manager' ),
			'topbar'     => __( 'Top bar', 'medical-college-manager' ),
			'header'     => __( 'Header & menu', 'medical-college-manager' ),
			'behaviour'  => __( 'Behaviour', 'medical-college-manager' ),
			'footer'     => __( 'Footer', 'medical-college-manager' ),
			'typography' => __( 'Typography', 'medical-college-manager' ),
			'tools'      => __( 'Import / Export', 'medical-college-manager' ),
		);
	}

	/**
	 * WCAG contrast ratio of two hex colours.
	 *
	 * @param string $a Colour.
	 * @param string $b Colour.
	 * @return float
	 */
	public static function contrast( $a, $b ) {
		$lum = function ( $hex ) {
			$h = ltrim( (string) $hex, '#' );
			if ( 3 === strlen( $h ) ) {
				$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
			}
			if ( 6 !== strlen( $h ) || ! ctype_xdigit( $h ) ) {
				return 0.0;
			}
			$l = array();
			foreach ( str_split( $h, 2 ) as $part ) {
				$v   = hexdec( $part ) / 255;
				$l[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
			}
			return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
		};
		$x   = $lum( $a );
		$y   = $lum( $b );
		return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
	}

	/**
	 * Text/background pairs below WCAG AA (4.5:1), for one tab.
	 *
	 * @param string $tab Tab.
	 * @param array  $s   Settings.
	 * @return string[]
	 */
	private static function contrast_warnings( $tab, $s ) {
		$pairs = array(
			'topbar' => array(
				array( 'top_text', 'top_bg', __( 'Top bar text on the top bar', 'medical-college-manager' ) ),
				array( 'top_hover', 'top_bg', __( 'Top bar hover colour on the top bar', 'medical-college-manager' ) ),
			),
			'header' => array(
				array( 'menu_color', 'main_bg', __( 'Menu text on the header', 'medical-college-manager' ) ),
				array( 'hover', 'main_bg', __( 'Menu hover colour on the header', 'medical-college-manager' ) ),
				array( 'active', 'main_bg', __( 'Current-page colour on the header', 'medical-college-manager' ) ),
				array( 'dd_text', 'dd_bg', __( 'Dropdown text on the dropdown', 'medical-college-manager' ) ),
				array( 'dd_hover', 'dd_hover_bg', __( 'Dropdown hover text on its highlight', 'medical-college-manager' ) ),
			),
			'footer' => array(
				array( 'foot_text', 'foot_bg', __( 'Footer text on the footer', 'medical-college-manager' ) ),
				array( 'foot_text', 'foot_bottom_bg', __( 'Footer text on the bottom bar', 'medical-college-manager' ) ),
			),
		);
		$out   = array();
		foreach ( isset( $pairs[ $tab ] ) ? $pairs[ $tab ] : array() as $p ) {
			$r = self::contrast( $s[ $p[0] ], $s[ $p[1] ] );
			if ( $r < 4.5 ) {
				/* translators: 1: colour pair, 2: contrast ratio. */
				$out[] = sprintf( __( '%1$s: contrast %2$s:1 (at least 4.5:1 is needed for readable text).', 'medical-college-manager' ), $p[2], number_format_i18n( $r, 2 ) );
			}
		}
		return $out;
	}

	/** The settings screen. */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$labels = self::tab_labels();
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab switch only.
		$tab    = isset( $labels[ $tab ] ) ? $tab : 'general';
		$s      = MCM_Site_Chrome::settings();
		$base   = admin_url( 'admin.php?page=' . self::SLUG );
		echo '<div class="wrap mcm-hf-admin"><h1>' . esc_html__( 'Header & Footer', 'medical-college-manager' ) . '</h1>';
		echo '<p class="description" style="max-width:860px;">' . esc_html__( 'The college header and footer on every page, replacing the theme\'s — without editing the theme. Both are off until you switch them on below; the menus come from Appearance → Menus.', 'medical-college-manager' ) . '</p>';
		settings_errors();
		$this->import_notice();
		foreach ( MCM_Site_Chrome::problems() as $p ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( $p ) . '</p></div>';
		}
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Header & Footer sections', 'medical-college-manager' ) . '">';
		foreach ( $labels as $key => $label ) {
			echo '<a class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', $key, $base ) ) . '"' . ( $key === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		if ( 'tools' === $tab ) {
			$this->tools();
			echo '</div>';
			return;
		}

		foreach ( self::contrast_warnings( $tab, $s ) as $w ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( $w ) . '</p></div>';
		}
		echo '<form method="post" action="options.php">';
		settings_fields( 'mcm_site_chrome' );
		echo '<input type="hidden" name="' . esc_attr( MCM_Site_Chrome::OPTION . '[_tab]' ) . '" value="' . esc_attr( $tab ) . '" />';
		echo '<table class="form-table" role="presentation">';
		call_user_func( array( $this, 'tab_' . $tab ), $s );
		echo '</table>';
		submit_button();
		echo '</form>';
		if ( 'general' === $tab ) {
			$this->menu_status();
		}
		echo '</div>';
	}

	/*
	 * ----------------------------------------------------------------
	 * Field helpers (every value escaped here)
	 * ----------------------------------------------------------------
	 */

	/**
	 * Field name for a key.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function name( $key ) {
		return MCM_Site_Chrome::OPTION . '[' . $key . ']';
	}

	/**
	 * One table row.
	 *
	 * @param string $key   Key (for the label's for=).
	 * @param string $label Label.
	 * @param string $field Field HTML (escaped by the caller).
	 * @param string $desc  Description.
	 */
	private static function row( $key, $label, $field, $desc = '' ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( 'mcm-hf-' . $key ) . '">' . esc_html( $label ) . '</label></th><td>' . $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by the escaped helpers below.
		if ( '' !== $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Checkbox.
	 *
	 * @param array  $s     Settings.
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $text  Text next to the box.
	 * @param string $desc  Description.
	 */
	private static function check( $s, $key, $label, $text, $desc = '' ) {
		self::row( $key, $label, '<label><input type="checkbox" id="' . esc_attr( 'mcm-hf-' . $key ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="1" ' . checked( '1', $s[ $key ], false ) . ' /> ' . esc_html( $text ) . '</label>', $desc );
	}

	/**
	 * Text-like input.
	 *
	 * @param array  $s     Settings.
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $type  Input type.
	 * @param string $desc  Description.
	 * @param string $extra Extra attributes (trusted, static).
	 */
	private static function input( $s, $key, $label, $type = 'text', $desc = '', $extra = '' ) {
		$cls = 'number' === $type ? 'small-text' : 'regular-text';
		self::row( $key, $label, '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( 'mcm-hf-' . $key ) . '" class="' . $cls . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( $s[ $key ] ) . '" ' . $extra . ' />', $desc );
	}

	/**
	 * Colour picker.
	 *
	 * @param array  $s     Settings.
	 * @param string $key   Key.
	 * @param string $label Label.
	 */
	private static function color( $s, $key, $label ) {
		self::row( $key, $label, '<input type="text" id="' . esc_attr( 'mcm-hf-' . $key ) . '" class="mcm-color-field" data-default-color="' . esc_attr( MCM_Site_Chrome::defaults()[ $key ] ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( $s[ $key ] ) . '" />' );
	}

	/**
	 * Select.
	 *
	 * @param array  $s       Settings.
	 * @param string $key     Key.
	 * @param string $label   Label.
	 * @param array  $options value => label.
	 * @param string $desc    Description.
	 */
	private static function select( $s, $key, $label, $options, $desc = '' ) {
		$html = '<select id="' . esc_attr( 'mcm-hf-' . $key ) . '" name="' . esc_attr( self::name( $key ) ) . '">';
		foreach ( $options as $v => $l ) {
			$html .= '<option value="' . esc_attr( $v ) . '" ' . selected( (string) $v, (string) $s[ $key ], false ) . '>' . esc_html( $l ) . '</option>';
		}
		self::row( $key, $label, $html . '</select>', $desc );
	}

	/**
	 * Image picker (stores the URL; the Media Library ID is resolved on output).
	 *
	 * @param array  $s     Settings.
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $desc  Description.
	 */
	private static function image( $s, $key, $label, $desc = '' ) {
		$prev = '' !== $s[ $key ] ? '<img src="' . esc_url( $s[ $key ] ) . '" alt="" style="max-height:60px;margin-top:8px;display:block;" />' : '';
		self::row(
			$key,
			$label,
			'<input type="url" id="' . esc_attr( 'mcm-hf-' . $key ) . '" class="regular-text mcm-media-field" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( $s[ $key ] ) . '" placeholder="https://…" /> <button type="button" class="button mcm-media-pick" data-type="image" data-size="full">' . esc_html__( 'Select Image', 'medical-college-manager' ) . '</button> <button type="button" class="button-link mcm-media-clear">' . esc_html__( 'Clear', 'medical-college-manager' ) . '</button><span class="mcm-media-preview">' . $prev . '</span>',
			$desc
		);
	}

	/*
	 * ----------------------------------------------------------------
	 * Tabs
	 * ----------------------------------------------------------------
	 */

	/**
	 * General tab.
	 *
	 * @param array $s Settings.
	 */
	private function tab_general( $s ) {
		self::check( $s, 'enabled_header', __( 'Header', 'medical-college-manager' ), __( 'Show the college header on every page', 'medical-college-manager' ), __( 'Replaces the theme\'s header. If it ever fails to load, the theme\'s header is shown instead.', 'medical-college-manager' ) );
		self::check( $s, 'enabled_footer', __( 'Footer', 'medical-college-manager' ), __( 'Show the college footer on every page', 'medical-college-manager' ) );
		self::select(
			$s,
			'source',
			__( 'Design', 'medical-college-manager' ),
			array(
				'builtin'   => __( 'Built-in college design (set up on these tabs)', 'medical-college-manager' ),
				'elementor' => __( 'My own Elementor templates (Templates → Saved Templates)', 'medical-college-manager' ),
			)
		);
		$templates = array( '0' => __( '— Choose a template —', 'medical-college-manager' ) );
		foreach ( get_posts(
			array(
				'post_type'   => 'elementor_library',
				'post_status' => 'publish',
				'numberposts' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- Template picker; sites have a handful.
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		) as $t ) {
			$templates[ (string) $t->ID ] = $t->post_title . ' (#' . $t->ID . ')';
		}
		self::select( $s, 'template_header', __( 'Header template', 'medical-college-manager' ), $templates, __( 'Used when "My own Elementor templates" is chosen. If the template is deleted, the built-in design is shown.', 'medical-college-manager' ) );
		self::select( $s, 'template_footer', __( 'Footer template', 'medical-college-manager' ), $templates );

		$hidden = array_map( 'intval', array_filter( explode( ',', $s['hide_on'] ) ) );
		$opts   = '';
		foreach ( get_pages(
			array(
				'number'      => 500,
				'sort_column' => 'post_title',
			)
		) as $pg ) {
			$opts .= '<option value="' . (int) $pg->ID . '" ' . selected( in_array( (int) $pg->ID, $hidden, true ), true, false ) . '>' . esc_html( $pg->post_title ? $pg->post_title : '#' . $pg->ID ) . '</option>';
		}
		// Posts an array of page IDs; MCM_Site_Chrome::sanitize() joins it.
		self::row( 'hide_on', __( 'Hide on these pages', 'medical-college-manager' ), '<select id="mcm-hf-hide_on" multiple size="6" style="min-width:320px;" name="' . esc_attr( self::name( 'hide_on' ) ) . '[]">' . $opts . '</select>', __( 'Ctrl/Cmd-click to choose several (e.g. a landing page with its own header).', 'medical-college-manager' ) );
		self::check( $s, 'preview', __( 'Elementor preview', 'medical-college-manager' ), __( 'Show the header and footer while editing pages in Elementor', 'medical-college-manager' ) );
	}

	/**
	 * Top bar tab.
	 *
	 * @param array $s Settings.
	 */
	private function tab_topbar( $s ) {
		self::check( $s, 'top_show', __( 'Top bar', 'medical-college-manager' ), __( 'Show the top bar (contact details + top bar menu)', 'medical-college-manager' ) );
		self::input( $s, 'top_email', __( 'Email', 'medical-college-manager' ), 'email' );
		self::input( $s, 'top_phone', __( 'Phone', 'medical-college-manager' ), 'text', __( 'As it should read, e.g. +92-966-747373.', 'medical-college-manager' ) );
		self::select(
			$s,
			'top_mobile',
			__( 'On phones & tablets', 'medical-college-manager' ),
			array(
				'icons'  => __( 'Email and phone as icon buttons', 'medical-college-manager' ),
				'full'   => __( 'Everything, wrapped onto several lines', 'medical-college-manager' ),
				'hidden' => __( 'Hide the top bar', 'medical-college-manager' ),
			)
		);
		self::color( $s, 'top_bg', __( 'Background', 'medical-college-manager' ) );
		self::color( $s, 'top_text', __( 'Text', 'medical-college-manager' ) );
		self::color( $s, 'top_hover', __( 'Text on hover', 'medical-college-manager' ) );
		self::color( $s, 'top_divider', __( 'Menu dividers', 'medical-college-manager' ) );
		self::input( $s, 'top_divider_w', __( 'Divider width (px)', 'medical-college-manager' ), 'number', '', 'min="0" max="6"' );
		self::input( $s, 'top_divider_h', __( 'Divider height (px)', 'medical-college-manager' ), 'number', __( '63 = the full height of the top bar.', 'medical-college-manager' ), 'min="0" max="63"' );
	}

	/**
	 * Header tab.
	 *
	 * @param array $s Settings.
	 */
	private function tab_header( $s ) {
		self::image( $s, 'logo', __( 'Logo', 'medical-college-manager' ), __( 'Empty: the theme\'s logo (Customizer), then the letterhead logo.', 'medical-college-manager' ) );
		self::input( $s, 'logo_h', __( 'Logo height on desktops (px)', 'medical-college-manager' ), 'number', '', 'min="24" max="120"' );
		self::input( $s, 'logo_h_t', __( 'Logo height on tablets (px)', 'medical-college-manager' ), 'number', __( 'Also used whenever the header shows the menu button.', 'medical-college-manager' ), 'min="24" max="120"' );
		self::input( $s, 'logo_h_m', __( 'Logo height on phones (px)', 'medical-college-manager' ), 'number', '', 'min="24" max="100"' );
		self::check( $s, 'search_show', __( 'Search', 'medical-college-manager' ), __( 'Show a search button after the menu', 'medical-college-manager' ) );
		self::check( $s, 'cta_show', __( 'Button', 'medical-college-manager' ), __( 'Show a button after the menu (e.g. Apply Now)', 'medical-college-manager' ) );
		self::input( $s, 'cta_label', __( 'Button text', 'medical-college-manager' ) );
		self::input( $s, 'cta_url', __( 'Button link', 'medical-college-manager' ), 'url', __( 'The button shows only when it has both a text and a link. With a long main menu, the search and the button may not fit on the bar; the header then switches to the menu button, and the button appears in the menu panel.', 'medical-college-manager' ) );
		self::color( $s, 'main_bg', __( 'Background', 'medical-college-manager' ) );
		self::color( $s, 'border', __( 'Bottom border', 'medical-college-manager' ) );
		self::color( $s, 'menu_color', __( 'Menu text', 'medical-college-manager' ) );
		self::color( $s, 'hover', __( 'Menu text on hover', 'medical-college-manager' ) );
		self::color( $s, 'active', __( 'Current page text', 'medical-college-manager' ) );
		self::color( $s, 'bar', __( 'Active bar', 'medical-college-manager' ) );
		self::input( $s, 'bar_h', __( 'Active bar height (px)', 'medical-college-manager' ), 'number', '', 'min="0" max="8"' );
		self::select(
			$s,
			'bar_position',
			__( 'Active bar position', 'medical-college-manager' ),
			array(
				'item'  => __( 'Under the item (as on the current site)', 'medical-college-manager' ),
				'flush' => __( 'Flush with the bottom border', 'medical-college-manager' ),
			)
		);
		self::color( $s, 'dd_bg', __( 'Dropdown background', 'medical-college-manager' ) );
		self::color( $s, 'dd_text', __( 'Dropdown text', 'medical-college-manager' ) );
		self::color( $s, 'dd_hover', __( 'Dropdown text on hover', 'medical-college-manager' ) );
		self::color( $s, 'dd_hover_bg', __( 'Dropdown highlight on hover', 'medical-college-manager' ) );
		$items     = array( '0' => __( 'Automatic (the first top-level item whose label contains "Department")', 'medical-college-manager' ) );
		$locations = get_nav_menu_locations();
		if ( ! empty( $locations['mcm-college-nav'] ) ) {
			foreach ( (array) wp_get_nav_menu_items( $locations['mcm-college-nav'] ) as $it ) {
				if ( is_object( $it ) && ! (int) $it->menu_item_parent ) {
					$items[ (string) $it->ID ] = wp_strip_all_tags( $it->title );
				}
			}
		}
		self::select( $s, 'departments_item', __( 'Departments menu item', 'medical-college-manager' ), $items, __( 'Highlighted as the current section on every department page.', 'medical-college-manager' ) );
	}

	/**
	 * Behaviour tab.
	 *
	 * @param array $s Settings.
	 */
	private function tab_behaviour( $s ) {
		$modes = array(
			'static' => __( 'Static (scrolls away)', 'medical-college-manager' ),
			'sticky' => __( 'Sticky (always visible)', 'medical-college-manager' ),
			'smart'  => __( 'Smart sticky (hides when scrolling down, returns when scrolling up)', 'medical-college-manager' ),
			'shrink' => __( 'Sticky and shrinking', 'medical-college-manager' ),
		);
		self::select( $s, 'mode', __( 'Header on scroll', 'medical-college-manager' ), $modes );
		self::select(
			$s,
			'mode_mobile',
			__( 'On phones & tablets', 'medical-college-manager' ),
			array( 'same' => __( 'Same as above', 'medical-college-manager' ) ) + array_diff_key( $modes, array( 'shrink' => '' ) )
		);
		self::check( $s, 'hide_topbar_sticky', __( 'Top bar when sticky', 'medical-college-manager' ), __( 'Slide the top bar away once the page scrolls', 'medical-college-manager' ) );
		self::input( $s, 'breakpoint', __( 'Hamburger menu below (px)', 'medical-college-manager' ), 'number', __( 'The header also switches to the hamburger at any width when the menu does not fit.', 'medical-college-manager' ), 'min="600" max="1600"' );
		self::select(
			$s,
			'panel_side',
			__( 'Mobile menu opens from', 'medical-college-manager' ),
			array(
				'right' => __( 'Right', 'medical-college-manager' ),
				'left'  => __( 'Left', 'medical-college-manager' ),
			)
		);
		self::check( $s, 'back_to_top', __( 'Back to top', 'medical-college-manager' ), __( 'Show a back-to-top button after scrolling', 'medical-college-manager' ) );
	}

	/**
	 * Footer tab.
	 *
	 * @param array $s Settings.
	 */
	private function tab_footer( $s ) {
		self::select(
			$s,
			'foot_cols',
			__( 'Columns', 'medical-college-manager' ),
			array(
				'4' => __( '4: brand, two link columns, contact', 'medical-college-manager' ),
				'3' => __( '3: brand, one link column, contact', 'medical-college-manager' ),
				'2' => __( '2: brand, contact', 'medical-college-manager' ),
			)
		);
		self::select(
			$s,
			'foot_mode',
			__( 'Footer style', 'medical-college-manager' ),
			array(
				'normal' => __( 'Normal', 'medical-college-manager' ),
				'reveal' => __( 'Reveal (the page scrolls up to uncover a fixed footer)', 'medical-college-manager' ),
			),
			__( 'Reveal switches itself off on short pages, on small screens where the footer is taller than the screen, and for visitors who prefer reduced motion.', 'medical-college-manager' )
		);
		self::image( $s, 'foot_logo', __( 'Footer logo', 'medical-college-manager' ), __( 'Empty: the header logo.', 'medical-college-manager' ) );
		self::input( $s, 'foot_title', __( 'Title', 'medical-college-manager' ), 'text', __( 'Large title next to the logo, e.g. GMC.', 'medical-college-manager' ) );
		self::input( $s, 'foot_subtitle', __( 'Subtitle', 'medical-college-manager' ), 'text', __( 'Empty: the college name from the letterhead settings.', 'medical-college-manager' ) );
		self::row( 'foot_desc', __( 'Description', 'medical-college-manager' ), '<textarea id="mcm-hf-foot_desc" class="large-text" rows="3" name="' . esc_attr( self::name( 'foot_desc' ) ) . '">' . esc_textarea( $s['foot_desc'] ) . '</textarea>' );
		self::check( $s, 'foot_social', __( 'Social icons', 'medical-college-manager' ), __( 'Show the links from College Manager → Social Bar', 'medical-college-manager' ) );
		self::select(
			$s,
			'social_style',
			__( 'Icon style', 'medical-college-manager' ),
			array(
				'color' => __( 'Brand colours', 'medical-college-manager' ),
				'mono'  => __( 'Single colour', 'medical-college-manager' ),
			)
		);
		self::input( $s, 'col2_heading', __( 'Column 2 heading', 'medical-college-manager' ), 'text', __( 'Links: menu location "College footer: column 2 links".', 'medical-college-manager' ) );
		self::input( $s, 'col3_heading', __( 'Column 3 heading', 'medical-college-manager' ), 'text', __( 'Links: menu location "College footer: column 3 links".', 'medical-college-manager' ) );
		self::input( $s, 'contact_heading', __( 'Contact heading', 'medical-college-manager' ) );
		self::row( 'address', __( 'Address', 'medical-college-manager' ), '<textarea id="mcm-hf-address" class="large-text" rows="2" name="' . esc_attr( self::name( 'address' ) ) . '">' . esc_textarea( $s['address'] ) . '</textarea>' );
		self::input( $s, 'phone', __( 'Phone', 'medical-college-manager' ), 'text', __( 'Empty: the top bar phone.', 'medical-college-manager' ) );
		self::input( $s, 'email', __( 'Email', 'medical-college-manager' ), 'email', __( 'Empty: the top bar email.', 'medical-college-manager' ) );
		self::input( $s, 'copyright', __( 'Copyright line', 'medical-college-manager' ), 'text', __( '{year} becomes the current year, {site} the college name.', 'medical-college-manager' ) );
		self::color( $s, 'foot_bg', __( 'Background', 'medical-college-manager' ) );
		self::color( $s, 'foot_text', __( 'Text', 'medical-college-manager' ) );
		self::color( $s, 'foot_icon', __( 'Contact icons', 'medical-college-manager' ) );
		self::color( $s, 'foot_bottom_bg', __( 'Bottom bar background', 'medical-college-manager' ) );
	}

	/**
	 * Typography tab.
	 *
	 * @param array $s Settings.
	 */
	private function tab_typography( $s ) {
		self::select(
			$s,
			'font',
			__( 'Font', 'medical-college-manager' ),
			array(
				'montserrat' => __( 'Montserrat (bundled with the plugin — no Google request)', 'medical-college-manager' ),
				'inherit'    => __( 'Same as the site', 'medical-college-manager' ),
				'system'     => __( 'System font', 'medical-college-manager' ),
			)
		);
		self::input( $s, 'menu_size', __( 'Menu text size (px)', 'medical-college-manager' ), 'number', '', 'min="11" max="22"' );
		self::select(
			$s,
			'menu_weight',
			__( 'Menu text weight', 'medical-college-manager' ),
			array(
				'400' => __( 'Regular', 'medical-college-manager' ),
				'500' => __( 'Medium', 'medical-college-manager' ),
				'600' => __( 'Semi-bold', 'medical-college-manager' ),
				'700' => __( 'Bold', 'medical-college-manager' ),
			)
		);
		self::input( $s, 'foot_h_size', __( 'Footer heading size (px)', 'medical-college-manager' ), 'number', '', 'min="12" max="28"' );
	}

	/** Menu locations: what is assigned, with links to fix it. */
	private function menu_status() {
		$names     = get_registered_nav_menus();
		$locations = get_nav_menu_locations();
		echo '<h2>' . esc_html__( 'Menus', 'medical-college-manager' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:860px;"><thead><tr><th scope="col">' . esc_html__( 'Location', 'medical-college-manager' ) . '</th><th scope="col">' . esc_html__( 'Menu', 'medical-college-manager' ) . '</th></tr></thead><tbody>';
		foreach ( MCM_Site_Chrome::LOCATIONS as $loc ) {
			$menu = ! empty( $locations[ $loc ] ) ? wp_get_nav_menu_object( $locations[ $loc ] ) : false;
			echo '<tr><td>' . esc_html( isset( $names[ $loc ] ) ? $names[ $loc ] : $loc ) . '</td><td>';
			if ( $menu ) {
				echo esc_html( $menu->name ) . ' — <a href="' . esc_url( admin_url( 'nav-menus.php?action=edit&menu=' . (int) $menu->term_id ) ) . '">' . esc_html__( 'Edit menu', 'medical-college-manager' ) . '</a>';
			} else {
				echo esc_html__( 'Not assigned', 'medical-college-manager' ) . ' — <a href="' . esc_url( admin_url( 'nav-menus.php?action=locations' ) ) . '">' . esc_html__( 'Assign', 'medical-college-manager' ) . '</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/*
	 * ----------------------------------------------------------------
	 * Export / import / reset
	 * ----------------------------------------------------------------
	 */

	/** The Import / Export tab. */
	private function tools() {
		echo '<h2>' . esc_html__( 'Export', 'medical-college-manager' ) . '</h2><p>' . esc_html__( 'Download every Header & Footer and Department Page setting as one JSON file, e.g. to copy the design from a staging site to the live site (menus, images and department content stay on this site).', 'medical-college-manager' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcm_hf_export" />';
		wp_nonce_field( 'mcm_hf_export' );
		submit_button( __( 'Download settings', 'medical-college-manager' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Import', 'medical-college-manager' ) . '</h2><p>' . esc_html__( 'Choose a file exported here. Every value is checked; anything unknown is ignored.', 'medical-college-manager' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcm_hf_import" />';
		wp_nonce_field( 'mcm_hf_import' );
		echo '<p><label for="mcm-hf-import-file">' . esc_html__( 'Settings file (.json)', 'medical-college-manager' ) . '</label><br /><input type="file" id="mcm-hf-import-file" name="mcm_hf_file" accept=".json,application/json" /></p>';
		submit_button( __( 'Import settings', 'medical-college-manager' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Reset', 'medical-college-manager' ) . '</h2><p>' . esc_html__( 'Back to the original design. The header and footer are switched off.', 'medical-college-manager' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return window.confirm(' . esc_attr( wp_json_encode( __( 'Reset every Header & Footer setting?', 'medical-college-manager' ) ) ) . ');"><input type="hidden" name="action" value="mcm_hf_reset" />';
		wp_nonce_field( 'mcm_hf_reset' );
		submit_button( __( 'Reset to defaults', 'medical-college-manager' ), 'delete', 'submit', false );
		echo '</form>';
	}

	/** Download the settings as JSON. */
	public function export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'medical-college-manager' ), 403 );
		}
		check_admin_referer( 'mcm_hf_export' );
		$data = array(
			'plugin'     => 'medical-college-manager',
			'type'       => 'site-chrome',
			'version'    => MCM_VERSION,
			'settings'   => MCM_Site_Chrome::settings(),
			// The department page design travels with it (since 1.32.0).
			'department' => class_exists( 'MCM_Department_Page' ) ? MCM_Department_Page::settings() : array(),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename=mcm-header-footer-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/** Import a settings file. */
	public function import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'medical-college-manager' ), 403 );
		}
		check_admin_referer( 'mcm_hf_import' );
		$result = 'bad';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only the temp path is used, validated with is_uploaded_file().
		$tmp = isset( $_FILES['mcm_hf_file']['tmp_name'] ) && is_string( $_FILES['mcm_hf_file']['tmp_name'] ) ? $_FILES['mcm_hf_file']['tmp_name'] : '';
		$len = isset( $_FILES['mcm_hf_file']['size'] ) && is_scalar( $_FILES['mcm_hf_file']['size'] ) ? (int) $_FILES['mcm_hf_file']['size'] : 0;
		if ( '' !== $tmp && is_uploaded_file( $tmp ) && $len > 0 && $len < 200000 ) {
			$json = json_decode( (string) file_get_contents( $tmp ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded temp file.
			if ( is_array( $json ) && isset( $json['type'], $json['settings'] ) && 'site-chrome' === $json['type'] && is_array( $json['settings'] ) ) {
				$known = array_intersect_key( $json['settings'], MCM_Site_Chrome::defaults() );
				update_option( MCM_Site_Chrome::OPTION, MCM_Site_Chrome::clean( wp_parse_args( $known, MCM_Site_Chrome::defaults() ) ) );
				if ( isset( $json['department'] ) && is_array( $json['department'] ) && class_exists( 'MCM_Department_Page' ) ) {
					$dept = array_intersect_key( $json['department'], MCM_Department_Page::defaults() );
					update_option( MCM_Department_Page::OPTION, MCM_Department_Page::sanitize( wp_parse_args( $dept, MCM_Department_Page::defaults() ) ) );
				}
				$result = 'ok';
			}
		}
		wp_safe_redirect( add_query_arg( 'mcm_hf_import', $result, admin_url( 'admin.php?page=' . self::SLUG . '&tab=tools' ) ) );
		exit;
	}

	/** Reset to defaults. */
	public function reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'medical-college-manager' ), 403 );
		}
		check_admin_referer( 'mcm_hf_reset' );
		delete_option( MCM_Site_Chrome::OPTION );
		wp_safe_redirect( add_query_arg( 'mcm_hf_import', 'reset', admin_url( 'admin.php?page=' . self::SLUG . '&tab=tools' ) ) );
		exit;
	}

	/** Result notice after import / reset. */
	private function import_notice() {
		$r = isset( $_GET['mcm_hf_import'] ) ? sanitize_key( wp_unslash( $_GET['mcm_hf_import'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$m = array(
			'ok'    => array( 'success', __( 'Settings imported.', 'medical-college-manager' ) ),
			'reset' => array( 'success', __( 'Settings reset to the defaults. The header and footer are off.', 'medical-college-manager' ) ),
			'bad'   => array( 'error', __( 'That file is not a Header & Footer settings export. Nothing was changed.', 'medical-college-manager' ) ),
		);
		if ( isset( $m[ $r ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $m[ $r ][0] ) . ' is-dismissible"><p>' . esc_html( $m[ $r ][1] ) . '</p></div>';
		}
	}
}
