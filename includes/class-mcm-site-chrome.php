<?php
/**
 * Global header & footer (1.32) — replaces a theme / Theme Builder header and
 * footer with the college design, without editing the theme.
 *
 * How it renders (see the 1.32 plan, §4):
 *  1. template_redirect: decide whether to render; pre-render each enabled part
 *     into memory behind its own error boundary.
 *  2. The theme is told to skip ONLY the parts that rendered (Hello Elementor:
 *     its own hello_elementor_header_footer filter). A part that fails leaves
 *     the theme's own header/footer in place for that request.
 *  3. The header prints on wp_body_open (only when the theme called
 *     get_header(), so Elementor Canvas stays clean); the footer on wp_footer.
 *
 * Off by default: until an admin enables the header or footer (College Manager
 * → Header & Footer), nothing changes on the site.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Header & footer: settings schema, menu locations, render pipeline.
 */
class MCM_Site_Chrome {

	/** Option holding every header & footer setting. */
	const OPTION = 'mcm_site_chrome';

	/** Menu locations this module registers. */
	const LOCATIONS = array( 'mcm-college-nav', 'mcm-college-quick', 'mcm-college-links-a', 'mcm-college-links-b', 'mcm-college-legal' );

	/**
	 * Pre-rendered parts for this request: 'header' / 'footer' => HTML.
	 *
	 * @var array<string,string>
	 */
	private static $html = array();

	/**
	 * Parts already printed (each prints once).
	 *
	 * @var array<string,bool>
	 */
	private static $printed = array();

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'after_setup_theme', array( $this, 'menus' ) );
		add_action( 'template_redirect', array( $this, 'prepare' ), 99 );
		add_filter( 'hello_elementor_header_footer', array( $this, 'hello_filter' ), 20 );
		add_filter( 'hello_elementor_enable_skip_link', array( $this, 'hello_skip_link' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 20 );
		add_action( 'wp_head', array( $this, 'preload_font' ), 2 );
		add_action( 'wp_body_open', array( $this, 'print_header' ), 1 );
		add_action( 'wp_footer', array( $this, 'print_footer' ), 5 );
		add_action( 'wp_footer', array( $this, 'note_missing_body_open' ), 999 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'nav_menu_css_class', array( $this, 'departments_ancestor' ), 10, 4 );
		add_filter( 'site_status_tests', array( $this, 'site_health' ) );
	}

	/*
	 * ----------------------------------------------------------------
	 * Settings
	 * ----------------------------------------------------------------
	 */

	/**
	 * Defaults: the measured design of the college's current header & footer.
	 * Both parts are OFF, so updating the plugin changes nothing.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// General.
			'enabled_header'     => '0',
			'enabled_footer'     => '0',
			'source'             => 'builtin',
			'template_header'    => '0',
			'template_footer'    => '0',
			'hide_on'            => '',
			'font'               => 'montserrat',
			'preview'            => '1',
			// Top bar.
			'top_show'           => '1',
			'top_email'          => '',
			'top_phone'          => '',
			'top_bg'             => '#0A4D8C',
			'top_text'           => '#FFFFFF',
			'top_hover'          => '#BFE6E6',
			'top_divider'        => '#434549',
			'top_divider_w'      => '2',
			'top_divider_h'      => '45',
			'top_mobile'         => 'icons',
			// Header.
			'logo'               => '',
			'logo_h'             => '72',
			'logo_h_t'           => '62',
			'logo_h_m'           => '54',
			'cta_show'           => '0',
			'cta_label'          => __( 'Apply Now', 'medical-college-manager' ),
			'cta_url'            => '',
			'search_show'        => '0',
			'main_bg'            => '#FFFFFF',
			'border'             => '#083D70',
			'menu_color'         => '#0A4D8C',
			'menu_size'          => '14',
			'menu_weight'        => '600',
			'hover'              => '#008080',
			'active'             => '#008080',
			'bar'                => '#00A6A6',
			'bar_h'              => '4',
			'bar_position'       => 'item',
			'dd_bg'              => '#FFFFFF',
			'dd_text'            => '#0A4D8C',
			'dd_hover'           => '#007373',
			'dd_hover_bg'        => '#EDF7F7',
			'departments_item'   => '0',
			// Behaviour.
			'mode'               => 'static',
			'mode_mobile'        => 'same',
			'hide_topbar_sticky' => '1',
			'breakpoint'         => '1024',
			'panel_side'         => 'right',
			'back_to_top'        => '1',
			// Footer.
			'foot_logo'          => '',
			'foot_title'         => 'GMC',
			'foot_subtitle'      => '',
			'foot_desc'          => '',
			'foot_social'        => '1',
			'social_style'       => 'color',
			'col2_heading'       => __( 'Quick Links', 'medical-college-manager' ),
			'col3_heading'       => __( 'For Students', 'medical-college-manager' ),
			'contact_heading'    => __( 'Contact', 'medical-college-manager' ),
			'address'            => '',
			'phone'              => '',
			'email'              => '',
			/* translators: Keep {year} and {site}: they become the current year and the college name. */
			'copyright'          => __( '© {year} {site} — All Rights Reserved.', 'medical-college-manager' ),
			'foot_bg'            => '#0A4D8C',
			'foot_text'          => '#FFFFFF',
			'foot_icon'          => '#00A6A6',
			'foot_bottom_bg'     => '#02216D',
			'foot_cols'          => '4',
			'foot_mode'          => 'normal',
			'foot_h_size'        => '16',
		);
	}

	/**
	 * Setting keys by settings-screen tab (a tab saves only its own keys).
	 *
	 * @return array<string,string[]>
	 */
	public static function tabs() {
		return array(
			'general'    => array( 'enabled_header', 'enabled_footer', 'source', 'template_header', 'template_footer', 'hide_on', 'preview' ),
			'topbar'     => array( 'top_show', 'top_email', 'top_phone', 'top_bg', 'top_text', 'top_hover', 'top_divider', 'top_divider_w', 'top_divider_h', 'top_mobile' ),
			'header'     => array( 'logo', 'logo_h', 'logo_h_t', 'logo_h_m', 'cta_show', 'cta_label', 'cta_url', 'search_show', 'main_bg', 'border', 'menu_color', 'hover', 'active', 'bar', 'bar_h', 'bar_position', 'dd_bg', 'dd_text', 'dd_hover', 'dd_hover_bg', 'departments_item' ),
			'behaviour'  => array( 'mode', 'mode_mobile', 'hide_topbar_sticky', 'breakpoint', 'panel_side', 'back_to_top' ),
			'footer'     => array( 'foot_cols', 'foot_mode', 'foot_logo', 'foot_title', 'foot_subtitle', 'foot_desc', 'foot_social', 'social_style', 'col2_heading', 'col3_heading', 'contact_heading', 'address', 'phone', 'email', 'copyright', 'foot_bg', 'foot_text', 'foot_icon', 'foot_bottom_bg' ),
			'typography' => array( 'font', 'menu_size', 'menu_weight', 'foot_h_size' ),
		);
	}

	/**
	 * Saved settings merged over the defaults, sanitized again on read (a
	 * value written around the settings screen never reaches the page
	 * unchecked). Cached until the option changes.
	 *
	 * @return array
	 */
	public static function settings() {
		static $raw = null, $clean = null;
		$saved      = get_option( self::OPTION, array() );
		if ( null === $clean || $saved !== $raw ) {
			$raw   = $saved;
			$clean = self::clean( wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() ) );
		}
		return $clean;
	}

	/**
	 * Sanitize a COMPLETE settings array (every key present).
	 *
	 * @param array $in Values.
	 * @return array
	 */
	public static function clean( $in ) {
		$d    = self::defaults();
		$out  = array();
		$str  = function ( $k ) use ( $in ) {
			return isset( $in[ $k ] ) ? MCM_Safe::str( $in[ $k ] ) : '';
		};
		$int  = function ( $k, $min, $max ) use ( $in, $d ) {
			$v = isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) && '' !== trim( (string) $in[ $k ] ) ? intval( $in[ $k ] ) : intval( $d[ $k ] );
			return (string) min( $max, max( $min, $v ) );
		};
		$pick = function ( $k, $allowed ) use ( $str, $d ) {
			$v = $str( $k );
			return in_array( $v, $allowed, true ) ? $v : $d[ $k ];
		};
		foreach ( array( 'enabled_header', 'enabled_footer', 'preview', 'top_show', 'hide_topbar_sticky', 'back_to_top', 'foot_social', 'cta_show', 'search_show' ) as $k ) {
			$out[ $k ] = ( isset( $in[ $k ] ) && ( '1' === MCM_Safe::str( $in[ $k ] ) || true === $in[ $k ] ) ) ? '1' : '0';
		}
		foreach ( array( 'top_bg', 'top_text', 'top_hover', 'top_divider', 'main_bg', 'border', 'menu_color', 'hover', 'active', 'bar', 'dd_bg', 'dd_text', 'dd_hover', 'dd_hover_bg', 'foot_bg', 'foot_text', 'foot_icon', 'foot_bottom_bg' ) as $k ) {
			$c         = sanitize_hex_color( $str( $k ) );
			$out[ $k ] = $c ? strtoupper( $c ) : $d[ $k ];
		}
		$out['source']           = $pick( 'source', array( 'builtin', 'elementor' ) );
		$out['template_header']  = (string) absint( $str( 'template_header' ) );
		$out['template_footer']  = (string) absint( $str( 'template_footer' ) );
		$out['hide_on']          = implode( ',', array_unique( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $str( 'hide_on' ) ) ) ) ) );
		$out['font']             = $pick( 'font', array( 'montserrat', 'inherit', 'system' ) );
		$out['top_email']        = sanitize_email( $str( 'top_email' ) );
		$out['top_phone']        = sanitize_text_field( $str( 'top_phone' ) );
		$out['top_divider_w']    = $int( 'top_divider_w', 0, 6 );
		$out['top_divider_h']    = $int( 'top_divider_h', 0, 63 );
		$out['top_mobile']       = $pick( 'top_mobile', array( 'icons', 'full', 'hidden' ) );
		$out['logo']             = esc_url_raw( $str( 'logo' ) );
		$out['logo_h']           = $int( 'logo_h', 24, 120 );
		$out['logo_h_t']         = $int( 'logo_h_t', 24, 120 );
		$out['logo_h_m']         = $int( 'logo_h_m', 24, 100 );
		$out['cta_label']        = sanitize_text_field( $str( 'cta_label' ) );
		$out['cta_url']          = esc_url_raw( $str( 'cta_url' ) );
		$out['foot_cols']        = $pick( 'foot_cols', array( '4', '3', '2' ) );
		$out['foot_mode']        = $pick( 'foot_mode', array( 'normal', 'reveal' ) );
		$out['foot_h_size']      = $int( 'foot_h_size', 12, 28 );
		$out['menu_size']        = $int( 'menu_size', 11, 22 );
		$out['menu_weight']      = $pick( 'menu_weight', array( '400', '500', '600', '700' ) );
		$out['bar_h']            = $int( 'bar_h', 0, 8 );
		$out['bar_position']     = $pick( 'bar_position', array( 'item', 'flush' ) );
		$out['departments_item'] = (string) absint( $str( 'departments_item' ) );
		$out['mode']             = $pick( 'mode', array( 'static', 'sticky', 'smart', 'shrink' ) );
		$out['mode_mobile']      = $pick( 'mode_mobile', array( 'same', 'static', 'sticky', 'smart' ) );
		$out['breakpoint']       = $int( 'breakpoint', 600, 1600 );
		$out['panel_side']       = $pick( 'panel_side', array( 'right', 'left' ) );
		$out['foot_logo']        = esc_url_raw( $str( 'foot_logo' ) );
		$out['social_style']     = $pick( 'social_style', array( 'color', 'mono' ) );
		foreach ( array( 'foot_title', 'foot_subtitle', 'col2_heading', 'col3_heading', 'contact_heading', 'phone', 'copyright' ) as $k ) {
			$out[ $k ] = sanitize_text_field( $str( $k ) );
		}
		$out['foot_desc'] = sanitize_textarea_field( $str( 'foot_desc' ) );
		$out['address']   = sanitize_textarea_field( $str( 'address' ) );
		$out['email']     = sanitize_email( $str( 'email' ) );
		return $out;
	}

	/**
	 * Settings API sanitize callback. A settings-screen tab posts only its own
	 * fields (plus `_tab`), so they are merged over what is saved; an import
	 * posts a whole set.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$in = is_array( $input ) ? $input : array();
		if ( isset( $in['hide_on'] ) && is_array( $in['hide_on'] ) ) {
			$in['hide_on'] = implode( ',', array_map( 'absint', $in['hide_on'] ) );
		}
		$current = self::settings();
		$tab     = isset( $in['_tab'] ) ? sanitize_key( MCM_Safe::str( $in['_tab'] ) ) : '';
		$tabs    = self::tabs();
		if ( isset( $tabs[ $tab ] ) ) {
			foreach ( $tabs[ $tab ] as $k ) {
				// Unticked checkboxes are absent from the post.
				$current[ $k ] = isset( $in[ $k ] ) ? $in[ $k ] : '0';
			}
			return self::clean( $current );
		}
		return self::clean( wp_parse_args( $in, $current ) );
	}

	/** The five menu locations. */
	public function menus() {
		register_nav_menus(
			array(
				'mcm-college-nav'     => __( 'College header: main menu', 'medical-college-manager' ),
				'mcm-college-quick'   => __( 'College header: top bar menu', 'medical-college-manager' ),
				'mcm-college-links-a' => __( 'College footer: column 2 links', 'medical-college-manager' ),
				'mcm-college-links-b' => __( 'College footer: column 3 links', 'medical-college-manager' ),
				'mcm-college-legal'   => __( 'College footer: bottom bar links', 'medical-college-manager' ),
			)
		);
	}

	/*
	 * ----------------------------------------------------------------
	 * Decide + pre-render
	 * ----------------------------------------------------------------
	 */

	/**
	 * Is a block theme active (header/footer are declined there)?
	 *
	 * @return bool
	 */
	public static function block_theme() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	/**
	 * Should this request get the plugin header/footer at all?
	 *
	 * @return bool
	 */
	public static function should_render() {
		$s = self::settings();
		if ( '1' !== $s['enabled_header'] && '1' !== $s['enabled_footer'] ) {
			return false;
		}
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() || is_embed() || is_robots() || is_trackback() ) {
			return false;
		}
		if ( function_exists( 'is_favicon' ) && is_favicon() ) {
			return false;
		}
		if ( self::block_theme() ) {
			return false;
		}
		// Elementor's preview frame: shown unless the setting hides it there.
		if ( '1' !== $s['preview'] && isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check.
			return false;
		}
		if ( '' !== $s['hide_on'] && is_singular() && in_array( get_queried_object_id(), array_map( 'intval', explode( ',', $s['hide_on'] ) ), true ) ) {
			return false;
		}
		/**
		 * Filters whether the plugin header/footer render on this request.
		 *
		 * @param bool $render Render them.
		 */
		return (bool) apply_filters( 'mcm_site_chrome_should_render', true );
	}

	/**
	 * Is one part switched on (and not handed to Elementor Pro's Theme Builder)?
	 *
	 * @param string $part 'header' or 'footer'.
	 * @return bool
	 */
	public static function part_enabled( $part ) {
		$s = self::settings();
		if ( '1' !== $s[ 'enabled_' . $part ] ) {
			return false;
		}
		// Elementor Pro Theme Builder has a header/footer for this page: step aside.
		return ! MCM_Templates::theme_builder_has( $part );
	}

	/**
	 * Runs on template_redirect: pre-render the enabled parts into memory. Nothing has
	 * been output yet, so a failing part simply leaves the theme's own one.
	 */
	public function prepare() {
		self::$html    = array();
		self::$printed = array();
		if ( ! self::should_render() ) {
			return;
		}
		// Elementor Canvas never calls get_header()/get_footer(): nothing would print.
		if ( is_singular() && 'elementor_canvas' === get_page_template_slug( get_queried_object_id() ) ) {
			return;
		}
		foreach ( array( 'header', 'footer' ) as $part ) {
			if ( ! self::part_enabled( $part ) ) {
				continue;
			}
			$level = ob_get_level();
			try {
				$html = 'header' === $part ? self::header_html() : self::footer_html();
				if ( '' !== $html ) {
					self::$html[ $part ] = $html;
				}
			} catch ( \Throwable $e ) {
				while ( ob_get_level() > $level ) {
					ob_end_clean();
				}
				// A broken part fails on every page view: report it once an hour,
				// not with a database write per request.
				if ( ! get_transient( 'mcm_hf_err_' . $part ) ) {
					set_transient( 'mcm_hf_err_' . $part, 1, HOUR_IN_SECONDS );
					MCM_Health::report( 'site ' . $part, $e );
				}
			}
		}
	}

	/**
	 * Did this part render for the current request?
	 *
	 * @param string $part 'header' or 'footer'.
	 * @return bool
	 */
	public static function has( $part ) {
		return isset( self::$html[ $part ] );
	}

	/*
	 * ----------------------------------------------------------------
	 * Theme integration
	 * ----------------------------------------------------------------
	 */

	/**
	 * Hello Elementor asks this filter three different questions; the order
	 * of the checks matters (see the plan, §4 step 3):
	 *  - while enqueueing: is either of Hello's parts still needed (its CSS)?
	 *  - in footer.php (after get_footer): show Hello's footer?
	 *  - otherwise (header.php): show Hello's header?
	 *
	 * @param bool $show Hello's current answer.
	 * @return bool
	 */
	public function hello_filter( $show ) {
		if ( ! $show ) {
			return $show; // Already switched off in Hello's own settings.
		}
		if ( doing_action( 'wp_enqueue_scripts' ) ) {
			return ! ( self::has( 'header' ) && self::has( 'footer' ) );
		}
		if ( did_action( 'get_footer' ) ) {
			return ! self::has( 'footer' );
		}
		return ! self::has( 'header' );
	}

	/**
	 * Hello prints its skip link after wp_body_open — under our header. Ours
	 * prints first, so Hello's is switched off while our header shows.
	 *
	 * @param bool $enable Hello's choice.
	 * @return bool
	 */
	public function hello_skip_link( $enable ) {
		return self::has( 'header' ) ? false : $enable;
	}

	/**
	 * For classic themes without a switch: a site can opt in to hiding the
	 * theme's own header/footer with CSS.
	 *
	 * @param string $part 'header' or 'footer'.
	 * @return bool
	 */
	private static function hide_theme_part( $part ) {
		if ( 'hello-elementor' === get_template() ) {
			return false; // Hello is switched through its filter instead.
		}
		/**
		 * Filters whether the active theme's own header/footer is hidden (CSS)
		 * while the plugin's part shows. Off by default for themes other than
		 * Hello Elementor, which is switched off through its own filter.
		 *
		 * @param bool   $suppress Hide the theme's part.
		 * @param string $part     'header' or 'footer'.
		 */
		return (bool) apply_filters( 'mcm_site_chrome_suppress_theme', false, $part );
	}

	/** Print the header (only when the theme's header ran — never on Canvas). */
	public function print_header() {
		if ( ! self::has( 'header' ) || ! empty( self::$printed['header'] ) || ! did_action( 'get_header' ) ) {
			return;
		}
		self::$printed['header'] = true;
		echo self::$html['header']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped in header_html().
	}

	/** Print the footer (only when the theme's footer ran). */
	public function print_footer() {
		if ( ! self::has( 'footer' ) || ! empty( self::$printed['footer'] ) || ! did_action( 'get_footer' ) ) {
			return;
		}
		self::$printed['footer'] = true;
		echo self::$html['footer']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped in footer_html().
	}

	/**
	 * A classic theme that never calls wp_body_open() cannot show the header.
	 * Remembered (once) for Site Health.
	 */
	public function note_missing_body_open() {
		if ( self::has( 'header' ) && did_action( 'get_header' ) && ! did_action( 'wp_body_open' ) && ! self::no_body_open() ) {
			set_transient( 'mcm_hf_no_body_open', get_stylesheet(), WEEK_IN_SECONDS );
		}
	}

	/**
	 * Body classes for theme CSS.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		if ( self::has( 'header' ) || self::has( 'footer' ) ) {
			$classes[] = 'mcm-hf-on';
			if ( self::has( 'header' ) ) {
				$classes[] = 'mcm-hf-mode-' . sanitize_html_class( self::settings()['mode'] );
			}
		}
		return $classes;
	}

	/**
	 * On a department page, mark the main menu's "Departments" parent as the
	 * current ancestor (WordPress cannot know a custom-link parent holds it).
	 *
	 * @param string[] $classes Item classes.
	 * @param WP_Post  $item    Menu item.
	 * @param stdClass $args    wp_nav_menu() arguments.
	 * @param int      $depth   Depth.
	 * @return string[]
	 */
	public function departments_ancestor( $classes, $item, $args = null, $depth = 0 ) {
		if ( 0 !== (int) $depth || ! is_object( $args ) || empty( $args->theme_location ) || 'mcm-college-nav' !== $args->theme_location || ! is_tax( 'mcm_department' ) ) {
			return $classes;
		}
		$chosen = (int) self::settings()['departments_item'];
		$match  = $chosen ? (int) $item->ID === $chosen : (bool) preg_match( '/department/i', (string) $item->title );
		if ( $match && ! in_array( 'current-menu-ancestor', (array) $classes, true ) ) {
			$classes[] = 'current-menu-ancestor';
		}
		return $classes;
	}

	/*
	 * ----------------------------------------------------------------
	 * Assets
	 * ----------------------------------------------------------------
	 */

	/** Styles + script (+ the Elementor template CSS) — only when a part shows. */
	public function assets() {
		if ( ! self::has( 'header' ) && ! self::has( 'footer' ) ) {
			return;
		}
		$s = self::settings();
		wp_enqueue_style( 'mcm-site-chrome', MCM_URL . 'assets/css/mcm-site-chrome.css', array(), MCM_VERSION );
		wp_add_inline_style( 'mcm-site-chrome', self::css_vars() . self::theme_hide_css() );
		// Deferred on WordPress 6.3+, in the footer before that. The footer
		// needs it too (back-to-top).
		wp_enqueue_script(
			'mcm-site-chrome',
			MCM_URL . 'assets/js/mcm-site-chrome.js',
			array(),
			MCM_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		if ( 'elementor' === $s['source'] && class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			foreach ( array( 'header', 'footer' ) as $part ) {
				$id = (int) $s[ 'template_' . $part ];
				if ( self::has( $part ) && self::valid_template( $id ) ) {
					\Elementor\Core\Files\CSS\Post::create( $id )->enqueue();
				}
			}
		}
	}

	/** Preload the bundled font (no layout shift, no Google request). */
	public function preload_font() {
		if ( ( self::has( 'header' ) || self::has( 'footer' ) ) && 'montserrat' === self::settings()['font'] ) {
			echo '<link rel="preload" href="' . esc_url( MCM_URL . 'assets/fonts/Montserrat-var.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		}
	}

	/**
	 * The settings as CSS custom properties (hex colours and numbers only).
	 *
	 * @return string
	 */
	private static function css_vars() {
		$s     = self::settings();
		$fonts = array(
			'montserrat' => '"Montserrat", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif',
			'system'     => 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif',
			'inherit'    => 'inherit',
		);
		$map   = array(
			'top-bg'         => $s['top_bg'],
			'top-text'       => $s['top_text'],
			'top-hover'      => $s['top_hover'],
			'top-divider'    => $s['top_divider'],
			'top-divider-w'  => $s['top_divider_w'] . 'px',
			'top-divider-h'  => $s['top_divider_h'] . 'px',
			'main-bg'        => $s['main_bg'],
			'border'         => $s['border'],
			'logo-h'         => $s['logo_h'] . 'px',
			'logo-h-t'       => $s['logo_h_t'] . 'px',
			'logo-h-m'       => $s['logo_h_m'] . 'px',
			'foot-h-size'    => $s['foot_h_size'] . 'px',
			'menu'           => $s['menu_color'],
			'menu-size'      => $s['menu_size'] . 'px',
			'menu-weight'    => $s['menu_weight'],
			'hover'          => $s['hover'],
			'active'         => $s['active'],
			'bar'            => $s['bar'],
			'bar-h'          => $s['bar_h'] . 'px',
			'bar-offset'     => 'flush' === $s['bar_position'] ? '0px' : '15px',
			'dd-bg'          => $s['dd_bg'],
			'dd-text'        => $s['dd_text'],
			'dd-hover'       => $s['dd_hover'],
			'dd-hover-bg'    => $s['dd_hover_bg'],
			'foot-bg'        => $s['foot_bg'],
			'foot-text'      => $s['foot_text'],
			'foot-icon'      => $s['foot_icon'],
			'foot-bottom-bg' => $s['foot_bottom_bg'],
		);
		$css   = ':root{--mcm-hf-font:' . $fonts[ $s['font'] ] . ';';
		foreach ( $map as $k => $v ) {
			$css .= '--mcm-hf-' . $k . ':' . $v . ';';
		}
		return $css . '}';
	}

	/**
	 * CSS hiding a classic theme's own parts when a site opted in.
	 *
	 * @return string
	 */
	private static function theme_hide_css() {
		/**
		 * Filters the selectors of the theme's own header/footer, hidden when
		 * mcm_site_chrome_suppress_theme returns true.
		 *
		 * @param array $selectors 'header' => CSS selector, 'footer' => CSS selector.
		 */
		$sel = (array) apply_filters(
			'mcm_site_chrome_theme_selectors',
			array(
				'header' => '#masthead, .site-header',
				'footer' => '#colophon, .site-footer',
			)
		);
		$css = '';
		foreach ( array( 'header', 'footer' ) as $part ) {
			if ( self::has( $part ) && ! empty( $sel[ $part ] ) && self::hide_theme_part( $part ) && ! ( 'header' === $part && self::no_body_open() ) ) {
				$css .= wp_strip_all_tags( (string) $sel[ $part ] ) . '{display:none !important;}';
			}
		}
		return $css;
	}

	/*
	 * ----------------------------------------------------------------
	 * Elementor template source
	 * ----------------------------------------------------------------
	 */

	/**
	 * Is this a published Elementor template that can be rendered?
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public static function valid_template( $id ) {
		$id = (int) $id;
		if ( ! $id || ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$post = get_post( $id );
		if ( ! $post || 'elementor_library' !== $post->post_type || 'publish' !== $post->post_status ) {
			return false;
		}
		$docs = isset( \Elementor\Plugin::$instance->documents ) ? \Elementor\Plugin::$instance->documents : null;
		$doc  = $docs && method_exists( $docs, 'get' ) ? $docs->get( $id ) : null;
		return $doc && method_exists( $doc, 'is_built_with_elementor' ) && $doc->is_built_with_elementor();
	}

	/**
	 * A part from an Elementor template, or '' (then the built-in design).
	 *
	 * @param string $part 'header' or 'footer'.
	 * @return string
	 */
	private static function elementor_part( $part ) {
		$s  = self::settings();
		$id = (int) $s[ 'template_' . $part ];
		if ( 'elementor' !== $s['source'] || ! self::valid_template( $id ) ) {
			return '';
		}
		$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $id, false );
		if ( '' === trim( $html ) ) {
			return '';
		}
		$tag = 'header' === $part ? 'header' : 'footer';
		return '<' . $tag . ' class="mcm-hf-elementor mcm-hf-elementor--' . $part . '">' . $html . '</' . $tag . '>';
	}

	/*
	 * ----------------------------------------------------------------
	 * Markup
	 * ----------------------------------------------------------------
	 */

	/**
	 * An inline SVG icon (static, trusted markup).
	 *
	 * @param string $name Icon key.
	 * @return string
	 */
	public static function icon( $name ) {
		$line = ' class="is-line" fill="none" stroke="currentColor" stroke-width="1.9"';
		$i    = array(
			'mail'  => '<svg' . $line . ' viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/></svg>',
			'phone' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24 11.36 11.36 0 003.57.57 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/></svg>',
			'pin'   => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>',
			'env'   => '<svg' . $line . ' viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 10l9-6 9 6v9a2 2 0 01-2 2H5a2 2 0 01-2-2v-9z"/><path d="M3.5 10.5l8.5 5.5 8.5-5.5"/></svg>',
			'menu'  => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5.5" width="18" height="2.2" rx="1"/><rect x="3" y="10.9" width="18" height="2.2" rx="1"/><rect x="3" y="16.3" width="18" height="2.2" rx="1"/></svg>',
			'close' => '<svg class="is-line" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>',
			'up'    => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 7l-7 7 1.4 1.4L12 9.8l5.6 5.6L19 14z"/></svg>',
		);
		return isset( $i[ $name ] ) ? $i[ $name ] : '';
	}

	/**
	 * A menu location's HTML ('' when nothing is assigned or it is empty —
	 * never WordPress's "list every page" fallback).
	 *
	 * @param string $location Location.
	 * @param string $ul_class UL class.
	 * @param int    $depth    Max depth.
	 * @param bool   $ids      Keep the menu-item IDs (false for a second copy).
	 * @return string
	 */
	public static function menu( $location, $ul_class, $depth = 1, $ids = true ) {
		if ( ! has_nav_menu( $location ) ) {
			return '';
		}
		$strip = function () {
			return '';
		};
		if ( ! $ids ) {
			add_filter( 'nav_menu_item_id', $strip );
		}
		// The active theme's own menu add-ons (e.g. Twenty Twenty-One appends
		// its "+/−" submenu buttons to every menu) would duplicate ours.
		$detached = self::detach_theme_callbacks( 'walker_nav_menu_start_el' );
		try {
			$html = wp_nav_menu(
				array(
					'theme_location' => $location,
					'container'      => false,
					'menu_class'     => $ul_class,
					'menu_id'        => '',
					'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
					'depth'          => $depth,
					'fallback_cb'    => false,
					'echo'           => false,
					'walker'         => new MCM_Site_Chrome_Walker(),
				)
			);
		} finally {
			foreach ( $detached as $cb ) {
				add_filter( 'walker_nav_menu_start_el', $cb[0], $cb[1], $cb[2] );
			}
			if ( ! $ids ) {
				remove_filter( 'nav_menu_item_id', $strip );
			}
		}
		return false !== strpos( (string) $html, '<li' ) ? (string) $html : '';
	}

	/**
	 * Temporarily remove the callbacks of one hook that live in the active
	 * theme's folder (other plugins' callbacks stay). Returns what was removed
	 * so the caller can put it back.
	 *
	 * @param string $hook Hook name.
	 * @return array[] [ callback, priority, accepted args ].
	 */
	private static function detach_theme_callbacks( $hook ) {
		global $wp_filter;
		/**
		 * Filters whether the theme's own menu add-ons are kept out of the
		 * college header and footer menus.
		 *
		 * @param bool $isolate Keep them out (default true).
		 */
		if ( empty( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) || ! apply_filters( 'mcm_site_chrome_isolate_menus', true ) ) {
			return array();
		}
		$dirs    = array_unique( array( wp_normalize_path( get_template_directory() ) . '/', wp_normalize_path( get_stylesheet_directory() ) . '/' ) );
		$removed = array();
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$fn = $cb['function'];
				try {
					if ( is_array( $fn ) && 2 === count( $fn ) ) {
						$ref = new ReflectionMethod( $fn[0], $fn[1] );
					} elseif ( is_string( $fn ) && false !== strpos( $fn, '::' ) ) {
						$ref = new ReflectionMethod( $fn );
					} elseif ( is_string( $fn ) || $fn instanceof Closure ) {
						$ref = new ReflectionFunction( $fn );
					} else {
						continue;
					}
				} catch ( ReflectionException $e ) {
					continue;
				}
				$file = $ref->getFileName() ? wp_normalize_path( $ref->getFileName() ) : '';
				foreach ( $dirs as $dir ) {
					if ( '' !== $file && 0 === strpos( $file, $dir ) ) {
						remove_filter( $hook, $fn, $priority );
						$removed[] = array( $fn, $priority, (int) $cb['accepted_args'] );
						break;
					}
				}
			}
		}
		return $removed;
	}

	/**
	 * Logo <img> (Media Library: srcset + width/height) or ''.
	 *
	 * @param string $url    Image URL.
	 * @param int    $height Display height in px.
	 * @param string $alt    Alt text.
	 * @param bool   $lazy   Lazy-load (not for the header logo).
	 * @return string
	 */
	private static function logo_img( $url, $height, $alt, $lazy ) {
		$url = (string) $url;
		if ( '' === $url ) {
			$custom = (int) get_theme_mod( 'custom_logo' );
			if ( $custom ) {
				$url = (string) wp_get_attachment_image_url( $custom, 'full' );
			}
		}
		if ( '' === $url ) {
			$lh  = MCM_Query::letterhead();
			$url = (string) $lh['logo'];
		}
		if ( '' === $url ) {
			return '';
		}
		$id = self::attachment_id( $url );
		if ( $id ) {
			$meta  = wp_get_attachment_metadata( $id );
			$ratio = ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) ? $meta['width'] / $meta['height'] : 1;
			$img   = wp_get_attachment_image(
				$id,
				'medium',
				false,
				array(
					'alt'     => $alt,
					'sizes'   => (int) ceil( $height * $ratio ) . 'px',
					'loading' => $lazy ? 'lazy' : false,
				)
			);
			if ( $img ) {
				return $img;
			}
		}
		return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" height="' . (int) $height . '"' . ( $lazy ? ' loading="lazy"' : '' ) . ' />';
	}

	/**
	 * The header (built-in design, or the chosen Elementor template).
	 *
	 * @return string
	 */
	public static function header_html() {
		$skip = '<a class="mcm-hf mcm-hf-skip" href="#content">' . esc_html__( 'Skip to content', 'medical-college-manager' ) . '</a>';
		$el   = self::elementor_part( 'header' );
		if ( '' !== $el ) {
			return $skip . $el;
		}
		$s    = self::settings();
		$lh   = MCM_Query::letterhead();
		$org  = wp_specialchars_decode( (string) $lh['org'], ENT_QUOTES );
		$home = home_url( '/' );

		// Top bar: contact details + top menu.
		$email   = $s['top_email'];
		$phone   = $s['top_phone'];
		$tel     = preg_replace( '/[^0-9+]/', '', $phone );
		$contact = '';
		$icons   = '';
		if ( '' !== $email ) {
			$contact .= '<li><a href="' . esc_url( 'mailto:' . antispambot( $email ) ) . '">' . self::icon( 'mail' ) . '<span>' . esc_html( antispambot( $email ) ) . '</span></a></li>';
			/* translators: %s: email address. */
			$icons .= '<a href="' . esc_url( 'mailto:' . antispambot( $email ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Email %s', 'medical-college-manager' ), $email ) ) . '">' . self::icon( 'mail' ) . '</a>';
		}
		if ( '' !== $phone ) {
			$contact .= '<li><a href="' . esc_url( 'tel:' . $tel ) . '">' . self::icon( 'phone' ) . '<span>' . esc_html( $phone ) . '</span></a></li>';
			/* translators: %s: phone number. */
			$icons .= '<a href="' . esc_url( 'tel:' . $tel ) . '" aria-label="' . esc_attr( sprintf( __( 'Call %s', 'medical-college-manager' ), $phone ) ) . '">' . self::icon( 'phone' ) . '</a>';
		}
		$topmenu = self::menu( 'mcm-college-quick', 'mcm-hf-topmenu', 1, false );
		$topbar  = '';
		if ( '1' === $s['top_show'] && ( '' !== $contact || '' !== $topmenu ) ) {
			$tmods  = array(
				'hidden' => ' mcm-hf-top-hide-mobile',
				'full'   => ' mcm-hf-top-full',
			);
			$topbar = '<div class="mcm-hf-topbar' . ( isset( $tmods[ $s['top_mobile'] ] ) ? $tmods[ $s['top_mobile'] ] : '' ) . '"><div class="mcm-hf-container">'
				. ( '' !== $contact ? '<ul class="mcm-hf-contact">' . $contact . '</ul><div class="mcm-hf-iconbtns">' . $icons . '</div>' : '<span></span>' )
				. ( '' !== $topmenu ? '<nav class="mcm-hf-topnav" aria-label="' . esc_attr__( 'Top bar', 'medical-college-manager' ) . '">' . $topmenu . '</nav>' : '' )
				. '</div></div>';
		}

		// Main bar: logo + menu + burger.
		/* translators: %s: college name. */
		$logo_alt = sprintf( __( '%s — home', 'medical-college-manager' ), $org );
		$logo     = self::logo_img( $s['logo'], (int) $s['logo_h'], $logo_alt, false );
		$logo     = '' !== $logo ? $logo : '<span class="mcm-hf-site-title">' . esc_html( $org ) . '</span>';
		$main     = self::menu( 'mcm-college-nav', 'mcm-hf-menu', 3 );
		$burger   = '' !== $main || '' !== $topmenu;
		$mode     = $s['mode'];
		$mobile   = 'same' === $s['mode_mobile'] ? ( 'shrink' === $mode ? 'sticky' : $mode ) : $s['mode_mobile'];
		$classes  = 'mcm-hf mcm-hf-header' . ( '1' === $s['hide_topbar_sticky'] ? ' mcm-hf-hide-topbar-sticky' : '' ) . ( 'shrink' === $mode ? ' mcm-hf-shrink' : '' );

		$html  = $skip;
		$html .= '<header class="' . esc_attr( $classes ) . '" data-mode="' . esc_attr( $mode ) . '" data-mode-mobile="' . esc_attr( $mobile ) . '" data-breakpoint="' . (int) $s['breakpoint'] . '">';
		$html .= $topbar;
		$html .= '<div class="mcm-hf-mainbar"><div class="mcm-hf-container">';
		$html .= '<a class="mcm-hf-logo" href="' . esc_url( $home ) . '" rel="home">' . $logo . '</a>';
		$html .= '' !== $main ? '<nav class="mcm-hf-nav" aria-label="' . esc_attr__( 'Main', 'medical-college-manager' ) . '">' . $main . '</nav>' : '';
		$html .= self::actions_html( $s );
		$html .= $burger ? '<button class="mcm-hf-burger" type="button" aria-controls="mcm-hf-panel" aria-expanded="false" aria-label="' . esc_attr__( 'Open menu', 'medical-college-manager' ) . '">' . self::icon( 'menu' ) . '</button>' : '';
		$html .= '</div></div></header>';
		$html .= self::fit_script();
		$html .= '<div class="mcm-hf-spacer" aria-hidden="true"></div>';
		if ( $burger ) {
			$html .= self::panel_html( $s, $org, $contact );
		}
		return $html;
	}

	/**
	 * Is the optional call-to-action button switched on and complete?
	 *
	 * @param array $s Settings.
	 * @return bool
	 */
	private static function cta_on( $s ) {
		return '1' === $s['cta_show'] && '' !== $s['cta_url'] && '' !== $s['cta_label'];
	}

	/**
	 * Optional search (a disclosure that works without script) and CTA
	 * button after the main menu. Both are off by default.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private static function actions_html( $s ) {
		$out = '';
		if ( '1' === $s['search_show'] ) {
			$out .= '<details class="mcm-hf-search"><summary aria-label="' . esc_attr__( 'Search', 'medical-college-manager' ) . '"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 10-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/></svg></summary>'
				. '<form class="mcm-hf-search-form" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '"><label class="mcm-hf-sr" for="mcm-hf-s">' . esc_html__( 'Search for:', 'medical-college-manager' ) . '</label>'
				. '<input type="search" id="mcm-hf-s" name="s" placeholder="' . esc_attr__( 'Search…', 'medical-college-manager' ) . '" />'
				. '<button type="submit">' . esc_html__( 'Search', 'medical-college-manager' ) . '</button></form></details>';
		}
		if ( self::cta_on( $s ) ) {
			$out .= '<a class="mcm-hf-cta" href="' . esc_url( $s['cta_url'] ) . '">' . esc_html( $s['cta_label'] ) . '</a>';
		}
		return '' !== $out ? '<div class="mcm-hf-actions">' . $out . '</div>' : '';
	}

	/**
	 * Tiny inline script printed right after the header, before first paint:
	 * marks JS as available and collapses the header to the hamburger when the
	 * menu does not fit (below the breakpoint, or long menus at any width).
	 *
	 * @return string
	 */
	private static function fit_script() {
		$js = "(function(){var d=document.documentElement,h=document.querySelector('.mcm-hf-header');d.classList.add('mcm-hf-js');if(!h){return;}"
			. "window.mcmHfFit=function(){h.classList.remove('mcm-hf-collapsed','mcm-hf-top-collapsed');if(window.innerWidth<(+h.getAttribute('data-breakpoint')||1024)){h.classList.add('mcm-hf-collapsed');return;}"
			. 'var edge=function(c){var r=c.getBoundingClientRect();return r.right-parseFloat(getComputedStyle(c).paddingRight)+0.5;};'
			. "var mc=h.querySelector('.mcm-hf-mainbar .mcm-hf-container'),m=h.querySelector('.mcm-hf-menu'),ac=h.querySelector('.mcm-hf-actions'),rr=Math.max(m?m.getBoundingClientRect().right:0,ac?ac.getBoundingClientRect().right:0);if(mc&&m&&rr>edge(mc)){h.classList.add('mcm-hf-collapsed');return;}"
			. "var tc=h.querySelector('.mcm-hf-topbar .mcm-hf-container'),tm=h.querySelector('.mcm-hf-topmenu'),ct=h.querySelector('.mcm-hf-contact');if(tc&&tm&&ct&&(tm.getBoundingClientRect().right>edge(tc)||ct.getBoundingClientRect().right>tm.getBoundingClientRect().left)){h.classList.add('mcm-hf-top-collapsed');}};"
			. 'window.mcmHfFit();})();';
		return wp_get_inline_script_tag( $js );
	}

	/**
	 * The mobile menu panel (dialog).
	 *
	 * @param array  $s       Settings.
	 * @param string $org     College name.
	 * @param string $contact Contact list items (already escaped).
	 * @return string
	 */
	private static function panel_html( $s, $org, $contact ) {
		$main  = self::menu( 'mcm-college-nav', 'mcm-hf-acc', 3, false );
		$quick = self::menu( 'mcm-college-quick', 'mcm-hf-panel-quick', 1, false );
		$logo  = self::logo_img( $s['logo'], 48, '', true );
		$html  = '<div id="mcm-hf-panel" class="mcm-hf mcm-hf-panel" data-side="' . esc_attr( $s['panel_side'] ) . '" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Menu', 'medical-college-manager' ) . '" hidden>';
		$html .= '<div class="mcm-hf-panel-backdrop"></div><div class="mcm-hf-panel-box">';
		$html .= '<div class="mcm-hf-panel-head">' . ( '' !== $logo ? $logo : '<span class="mcm-hf-site-title">' . esc_html( $org ) . '</span>' ) . '<button class="mcm-hf-panel-close" type="button" aria-label="' . esc_attr__( 'Close menu', 'medical-college-manager' ) . '">' . self::icon( 'close' ) . '</button></div>';
		$html .= '' !== $main ? '<nav aria-label="' . esc_attr__( 'Main (mobile)', 'medical-college-manager' ) . '">' . $main . '</nav>' : '';
		if ( '' !== $quick ) {
			$html .= '<p class="mcm-hf-panel-h">' . esc_html__( 'Quick Links', 'medical-college-manager' ) . '</p><nav aria-label="' . esc_attr__( 'Top bar (mobile)', 'medical-college-manager' ) . '">' . $quick . '</nav>';
		}
		if ( self::cta_on( $s ) ) {
			$html .= '<p class="mcm-hf-panel-cta"><a class="mcm-hf-cta" href="' . esc_url( $s['cta_url'] ) . '">' . esc_html( $s['cta_label'] ) . '</a></p>';
		}
		if ( '' !== $contact ) {
			$html .= '<ul class="mcm-hf-panel-contact">' . $contact . '</ul>';
		}
		return $html . '</div></div>';
	}

	/**
	 * The footer (built-in design, or the chosen Elementor template).
	 *
	 * @return string
	 */
	public static function footer_html() {
		$el = self::elementor_part( 'footer' );
		$s  = self::settings();
		if ( '' !== $el ) {
			return $el . self::to_top( $s );
		}
		$lh  = MCM_Query::letterhead();
		$org = wp_specialchars_decode( (string) $lh['org'], ENT_QUOTES );

		// Column 1: brand.
		$logo  = self::logo_img( '' !== $s['foot_logo'] ? $s['foot_logo'] : $s['logo'], 89, '', true );
		$brand = '<div class="mcm-hf-foot-brand"><div class="mcm-hf-brand">' . $logo . '<div>'
			. ( '' !== $s['foot_title'] ? '<p class="mcm-hf-brand-title">' . esc_html( $s['foot_title'] ) . '</p>' : '' )
			. '<p class="mcm-hf-brand-sub">' . esc_html( '' !== $s['foot_subtitle'] ? $s['foot_subtitle'] : $org ) . '</p></div></div>'
			. ( '' !== $s['foot_desc'] ? '<p class="mcm-hf-foot-desc">' . esc_html( $s['foot_desc'] ) . '</p>' : '' )
			. ( '1' === $s['foot_social'] ? self::social( $s ) : '' )
			. '</div>';

		// Columns: 4 = brand, two link menus, contact; 3 = brand, first menu,
		// contact; 2 = brand, contact. An empty menu location drops its column.
		$cols  = array( $brand );
		$menus = array(
			'mcm-college-links-a' => $s['col2_heading'],
			'mcm-college-links-b' => $s['col3_heading'],
		);
		$menus = array_slice( $menus, 0, max( 0, (int) $s['foot_cols'] - 2 ), true );
		foreach ( $menus as $loc => $heading ) {
			$links = self::menu( $loc, 'mcm-hf-foot-links', 1, false );
			if ( '' === $links ) {
				continue;
			}
			$hid    = 'mcm-hf-' . $loc;
			$cols[] = '' !== $heading
				? '<nav aria-labelledby="' . esc_attr( $hid ) . '"><h2 class="mcm-hf-foot-h" id="' . esc_attr( $hid ) . '">' . esc_html( $heading ) . '</h2>' . $links . '</nav>'
				: '<nav aria-label="' . esc_attr__( 'Footer links', 'medical-college-manager' ) . '">' . $links . '</nav>';
		}

		// Column 4: contact (falls back to the top bar email/phone).
		$email = '' !== $s['email'] ? $s['email'] : $s['top_email'];
		$phone = '' !== $s['phone'] ? $s['phone'] : $s['top_phone'];
		$items = '';
		if ( '' !== $s['address'] ) {
			$items .= '<li>' . self::icon( 'pin' ) . '<span>' . nl2br( esc_html( $s['address'] ) ) . '</span></li>';
		}
		if ( '' !== $phone ) {
			$items .= '<li>' . self::icon( 'phone' ) . '<a href="' . esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a></li>';
		}
		if ( '' !== $email ) {
			$items .= '<li>' . self::icon( 'env' ) . '<a href="' . esc_url( 'mailto:' . antispambot( $email ) ) . '">' . esc_html( antispambot( $email ) ) . '</a></li>';
		}
		if ( '' !== $items ) {
			$cols[] = '<div>' . ( '' !== $s['contact_heading'] ? '<h2 class="mcm-hf-foot-h mcm-hf-foot-h--contact">' . esc_html( $s['contact_heading'] ) . '</h2>' : '' ) . '<ul class="mcm-hf-foot-contact">' . $items . '</ul></div>';
		}

		$copy  = strtr(
			$s['copyright'],
			array(
				'{year}' => wp_date( 'Y' ),
				'{site}' => $org,
			)
		);
		$legal = self::menu( 'mcm-college-legal', 'mcm-hf-legal', 1, false );

		$html  = 'reveal' === $s['foot_mode'] ? '<div class="mcm-hf-reveal-wrap" data-mcm-reveal="1">' : '';
		$html .= '<footer class="mcm-hf mcm-hf-footer">';
		$html .= '<div class="mcm-hf-foot-main"><div class="mcm-hf-container mcm-hf-foot-grid is-' . count( $cols ) . '">' . implode( '', $cols ) . '</div></div>';
		$html .= '<div class="mcm-hf-foot-bottom"><div class="mcm-hf-container">'
			. ( '' !== $copy ? '<p class="mcm-hf-copy">' . esc_html( $copy ) . '</p>' : '<span></span>' )
			. ( '' !== $legal ? '<nav aria-label="' . esc_attr__( 'Legal', 'medical-college-manager' ) . '">' . $legal . '</nav>' : '' )
			. '</div></div></footer>';
		$html .= 'reveal' === $s['foot_mode'] ? '</div>' : '';
		return $html . self::to_top( $s );
	}

	/**
	 * Back-to-top button.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private static function to_top( $s ) {
		return '1' === $s['back_to_top'] ? '<button class="mcm-hf mcm-hf-totop" type="button" aria-label="' . esc_attr__( 'Back to top', 'medical-college-manager' ) . '">' . self::icon( 'up' ) . '</button>' : '';
	}

	/**
	 * Social icons from College Manager → Social Bar (entered once).
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private static function social( $s ) {
		if ( ! class_exists( 'MCM_Social' ) ) {
			return '';
		}
		$colors = array(
			'facebook'  => '#3B5998',
			'instagram' => '#C13584',
			'twitter'   => '#000000',
			'tiktok'    => '#000000',
			'youtube'   => '#CD201F',
			'linkedin'  => '#0A66C2',
			'whatsapp'  => '#128C7E',
		);
		$out    = '';
		foreach ( MCM_Social::networks() as $key => $n ) {
			$url = trim( (string) MCM_Social::value( 'social_' . $key ) );
			if ( '' === $url ) {
				continue;
			}
			$bg   = 'color' === $s['social_style'] && isset( $colors[ $key ] ) ? ' style="background:' . esc_attr( $colors[ $key ] ) . '"' : '';
			$out .= '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $n['label'] ) . '"' . $bg . '><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . esc_attr( $n['svg'] ) . '"/></svg></a></li>';
		}
		return '' !== $out ? '<ul class="mcm-hf-social' . ( 'mono' === $s['social_style'] ? ' is-mono' : '' ) . '">' . $out . '</ul>' : '';
	}

	/**
	 * Does the active theme lack wp_body_open() (seen on a real page view)?
	 *
	 * @return bool
	 */
	public static function no_body_open() {
		return get_stylesheet() === get_transient( 'mcm_hf_no_body_open' );
	}

	/**
	 * Media Library ID for an image URL, cached for a day (the logo is looked
	 * up on every page view; attachment_url_to_postid() is an uncached query).
	 *
	 * @param string $url Image URL.
	 * @return int
	 */
	private static function attachment_id( $url ) {
		$key = 'mcm_hf_att_' . md5( $url );
		$id  = get_transient( $key );
		if ( false === $id ) {
			$id = MCM_Query::attachment_id_for( $url );
			set_transient( $key, (string) $id, DAY_IN_SECONDS );
		}
		return (int) $id;
	}

	/*
	 * ----------------------------------------------------------------
	 * Site Health
	 * ----------------------------------------------------------------
	 */

	/**
	 * Register the Site Health test.
	 *
	 * @param array $tests Tests.
	 * @return array
	 */
	public function site_health( $tests ) {
		$tests['direct']['mcm_site_chrome'] = array(
			'label' => __( 'Medical College Manager: header & footer', 'medical-college-manager' ),
			'test'  => array( __CLASS__, 'health_test' ),
		);
		return $tests;
	}

	/**
	 * Problems that keep the header/footer from showing as configured.
	 *
	 * @return string[]
	 */
	public static function problems() {
		$s = self::settings();
		if ( '1' !== $s['enabled_header'] && '1' !== $s['enabled_footer'] ) {
			return array();
		}
		$p = array();
		if ( self::block_theme() ) {
			$p[] = __( 'The active theme is a block theme; it builds its own header and footer in the Site Editor, so the college header and footer are not shown. Use a classic theme such as Hello Elementor.', 'medical-college-manager' );
		} elseif ( 'hello-elementor' !== get_template() ) {
			$p[] = __( 'The active theme is not Hello Elementor, so its own header and footer may still show next to the college ones. Switch them off in the theme, or let the plugin hide them with the mcm_site_chrome_suppress_theme filter.', 'medical-college-manager' );
		}
		if ( self::no_body_open() ) {
			$p[] = __( 'The active theme does not call wp_body_open(), so the header cannot be printed. Update the theme or use Hello Elementor.', 'medical-college-manager' );
		}
		if ( 'elementor' === $s['source'] ) {
			foreach ( array(
				'header' => __( 'header', 'medical-college-manager' ),
				'footer' => __( 'footer', 'medical-college-manager' ),
			) as $part => $label ) {
				if ( '1' === $s[ 'enabled_' . $part ] && ! self::valid_template( (int) $s[ 'template_' . $part ] ) ) {
					/* translators: %s: "header" or "footer". */
					$p[] = sprintf( __( 'The Elementor template chosen for the %s is missing, unpublished or not built with Elementor; the built-in design is shown instead.', 'medical-college-manager' ), $label );
				}
			}
		}
		if ( '1' === $s['enabled_header'] && ! has_nav_menu( 'mcm-college-nav' ) ) {
			$p[] = __( 'No menu is assigned to "College header: main menu" (Appearance → Menus → Manage Locations).', 'medical-college-manager' );
		}
		return $p;
	}

	/**
	 * Site Health result.
	 *
	 * @return array
	 */
	public static function health_test() {
		$p      = self::problems();
		$status = $p ? 'recommended' : 'good';
		$desc   = $p ? '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $p ) ) . '</li></ul>' : '<p>' . esc_html__( 'The college header and footer are off, or set up correctly.', 'medical-college-manager' ) . '</p>';
		return array(
			'label'       => $p ? __( 'The college header & footer need attention', 'medical-college-manager' ) : __( 'The college header & footer are fine', 'medical-college-manager' ),
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Medical College Manager', 'medical-college-manager' ),
				'color' => $p ? 'orange' : 'blue',
			),
			'description' => $desc,
			'actions'     => '<p><a href="' . esc_url( admin_url( 'admin.php?page=mcm-site-chrome' ) ) . '">' . esc_html__( 'Header & Footer settings', 'medical-college-manager' ) . '</a></p>',
			'test'        => 'mcm_site_chrome',
		);
	}
}
