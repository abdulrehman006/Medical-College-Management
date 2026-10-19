<?php
/**
 * Department page template (1.32).
 *
 * Every department archive (/department/{slug}/) renders the same eight
 * sections in a fixed order — Hero, Message from the HOD, Introduction,
 * Vision & Mission, Services / Facilities, Faculty, Publications, Contact —
 * followed by "More from this department" (tabs for the other content types).
 * Content comes from the Departments → Edit fields (MCM_TermMeta) and from
 * the content tagged with the department.
 *
 * Off by default: until an admin ticks "Enable the department page template"
 * (College Manager → Department Page), department pages look exactly as
 * before. If the template fails to render, the classic page is shown instead.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The fixed department page: settings, renderers, shortcodes, JSON-LD.
 */
class MCM_Department_Page {

	/** Option holding the department page settings. */
	const OPTION = 'mcm_department_page';

	/** The eight sections, in their fixed order. */
	const SECTIONS = array( 'hero', 'hod', 'introduction', 'vision_mission', 'services', 'faculty', 'publications', 'contact' );

	/**
	 * ID prefix of the instance being rendered. The first instance on a page
	 * gets the plain prefix (so #mcm-dept-publications links work); later ones
	 * (a second shortcode or widget) get a numbered one, so IDs never repeat.
	 *
	 * @var string
	 */
	private static $uid = 'mcm-dept-';

	/**
	 * Instances rendered in this request.
	 *
	 * @var int
	 */
	private static $instances = 0;

	/**
	 * Did the full template render as this request's department page?
	 *
	 * @var bool
	 */
	private static $is_page = false;

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'init', array( $this, 'shortcodes' ) );
		add_action( 'wp_head', array( $this, 'json_ld' ), 21 );
	}

	/**
	 * Start a new instance: pick its ID prefix.
	 */
	private static function begin_instance() {
		++self::$instances;
		self::$uid = 1 === self::$instances ? 'mcm-dept-' : 'mcm-dept-' . self::$instances . '-';
	}

	/**
	 * An element ID within the current instance.
	 *
	 * @param string $key Suffix.
	 * @return string
	 */
	private static function id( $key ) {
		return self::$uid . $key;
	}

	/**
	 * Called by MCM_Templates once the template is this request's page (the
	 * structured data is printed only then).
	 */
	public static function mark_page() {
		self::$is_page = true;
	}

	/*
	 * ----------------------------------------------------------------
	 * Settings
	 * ----------------------------------------------------------------
	 */

	/**
	 * Default settings. The template is off; colours match the site design.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'          => '0',
			'heading_color'    => '#0A4D8C',
			'accent_color'     => '#00A6A6',
			'card_bg'          => '#F4F7FA',
			'overlay'          => '55',
			'fallback_banner'  => '',
			'hod_layout'       => 'left',
			'faculty_cols'     => '4',
			'card_style'       => 'card',
			'show_designation' => '1',
			'show_department'  => '0',
			'pubs_limit'       => '10',
			'empty'            => 'hide',
			'related_tabs'     => '1',
			'titles'           => array(),
		);
	}

	/**
	 * Saved settings merged over the defaults — sanitized again on read, so a
	 * value written outside the settings screen (WP-CLI, an import) can never
	 * reach the page unchecked. Cached until the option changes.
	 *
	 * @return array
	 */
	public static function settings() {
		static $raw = null, $clean = null;
		$saved      = get_option( self::OPTION, array() );
		if ( null === $clean || $saved !== $raw ) {
			$raw   = $saved;
			$clean = self::sanitize( wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() ) );
		}
		return $clean;
	}

	/**
	 * Is the template switched on?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return '1' === (string) self::settings()['enabled'];
	}

	/**
	 * Default (translatable) section titles.
	 *
	 * @return array<string,string>
	 */
	public static function default_titles() {
		return array(
			'hod'            => __( 'Message from the HOD', 'medical-college-manager' ),
			'introduction'   => __( 'Introduction', 'medical-college-manager' ),
			'vision_mission' => __( 'Vision & Mission', 'medical-college-manager' ),
			'services'       => __( 'Services & Facilities', 'medical-college-manager' ),
			'faculty'        => __( 'Faculty', 'medical-college-manager' ),
			'publications'   => __( 'Publications', 'medical-college-manager' ),
			'contact'        => __( 'Contact Information', 'medical-college-manager' ),
			'related'        => __( 'More from this department', 'medical-college-manager' ),
		);
	}

	/**
	 * A section title: the admin's text when set, else the translated default.
	 *
	 * @param string $key Section key.
	 * @return string
	 */
	public static function title( $key ) {
		$s        = self::settings();
		$defaults = self::default_titles();
		$custom   = isset( $s['titles'][ $key ] ) ? trim( (string) $s['titles'][ $key ] ) : '';
		return '' !== $custom ? $custom : ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
	}

	/** College Manager → Department Page. */
	public function menu() {
		add_submenu_page(
			'mcm-settings',
			__( 'Department Page', 'medical-college-manager' ),
			__( 'Department Page', 'medical-college-manager' ),
			'manage_options',
			'mcm-department-page',
			array( $this, 'page' )
		);
	}

	/** Register the option with its sanitizer. */
	public function register_setting() {
		register_setting(
			'mcm_department_page',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize every setting.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$in  = is_array( $input ) ? $input : array();
		$d   = self::defaults();
		$out = array();
		foreach ( array( 'enabled', 'show_designation', 'show_department', 'related_tabs' ) as $k ) {
			$out[ $k ] = ! empty( $in[ $k ] ) ? '1' : '0';
		}
		foreach ( array( 'heading_color', 'accent_color', 'card_bg' ) as $k ) {
			$c         = isset( $in[ $k ] ) ? sanitize_hex_color( MCM_Safe::str( $in[ $k ] ) ) : '';
			$out[ $k ] = $c ? $c : $d[ $k ];
		}
		$out['overlay']         = (string) min( 90, max( 0, intval( isset( $in['overlay'] ) ? MCM_Safe::str( $in['overlay'] ) : $d['overlay'] ) ) );
		$out['fallback_banner'] = isset( $in['fallback_banner'] ) ? esc_url_raw( MCM_Safe::str( $in['fallback_banner'] ) ) : '';
		$out['hod_layout']      = ( isset( $in['hod_layout'] ) && 'right' === $in['hod_layout'] ) ? 'right' : 'left';
		$out['faculty_cols']    = (string) min( 5, max( 2, intval( isset( $in['faculty_cols'] ) ? MCM_Safe::str( $in['faculty_cols'] ) : 4 ) ) );
		$out['card_style']      = ( isset( $in['card_style'] ) && 'flat' === $in['card_style'] ) ? 'flat' : 'card';
		$out['pubs_limit']      = (string) min( 100, max( 1, intval( isset( $in['pubs_limit'] ) ? MCM_Safe::str( $in['pubs_limit'] ) : 10 ) ) );
		$out['empty']           = ( isset( $in['empty'] ) && 'soon' === $in['empty'] ) ? 'soon' : 'hide';
		$out['titles']          = array();
		foreach ( array_keys( self::default_titles() ) as $k ) {
			$t = isset( $in['titles'][ $k ] ) ? sanitize_text_field( MCM_Safe::str( $in['titles'][ $k ] ) ) : '';
			if ( '' !== $t ) {
				$out['titles'][ $k ] = $t;
			}
		}
		return $out;
	}

	/** The settings screen. */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = self::settings();
		$opt   = self::OPTION;
		$row   = function ( $label, $html, $desc = '' ) {
			echo '<tr><th scope="row">' . wp_kses_post( $label ) . '</th><td>' . $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the callers below.
			if ( '' !== $desc ) {
				echo '<p class="description">' . esc_html( $desc ) . '</p>';
			}
			echo '</td></tr>';
		};
		$check = function ( $key, $label ) use ( $s, $opt ) {
			return '<label><input type="checkbox" name="' . esc_attr( $opt . '[' . $key . ']' ) . '" value="1" ' . checked( '1', (string) $s[ $key ], false ) . ' /> ' . esc_html( $label ) . '</label>';
		};
		$color = function ( $key ) use ( $s, $opt ) {
			return '<input type="text" id="' . esc_attr( 'mcm-dp-' . $key ) . '" class="mcm-color-field" name="' . esc_attr( $opt . '[' . $key . ']' ) . '" value="' . esc_attr( $s[ $key ] ) . '" />';
		};
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Department Page', 'medical-college-manager' ); ?></h1>
			<p class="description" style="max-width:820px;"><?php esc_html_e( 'One fixed layout for every department page (/department/…/): Hero, Message from the HOD, Introduction, Vision & Mission, Services / Facilities, Faculty, Publications and Contact, then tabs for the department\'s other content. The content is entered under Departments → Edit.', 'medical-college-manager' ); ?></p>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'mcm_department_page' ); ?>
				<table class="form-table" role="presentation">
					<?php
					$row( esc_html__( 'Template', 'medical-college-manager' ), $check( 'enabled', __( 'Enable the department page template', 'medical-college-manager' ) ), __( 'Off: department pages keep their current layout.', 'medical-college-manager' ) );
					$row( '<label for="mcm-dp-heading_color">' . esc_html__( 'Section heading colour', 'medical-college-manager' ) . '</label>', $color( 'heading_color' ) );
					$row( '<label for="mcm-dp-accent_color">' . esc_html__( 'Accent colour', 'medical-college-manager' ) . '</label>', $color( 'accent_color' ), __( 'Bars, borders and icons. Text in the accent colour uses a darker shade so it stays readable.', 'medical-college-manager' ) );
					$row( '<label for="mcm-dp-card_bg">' . esc_html__( 'Card background', 'medical-college-manager' ) . '</label>', $color( 'card_bg' ), __( 'Also the background of every other section.', 'medical-college-manager' ) );
					$row(
						'<label for="mcm-dp-overlay">' . esc_html__( 'Hero overlay (%)', 'medical-college-manager' ) . '</label>',
						'<input type="number" id="mcm-dp-overlay" min="0" max="90" class="small-text" name="' . esc_attr( $opt . '[overlay]' ) . '" value="' . esc_attr( $s['overlay'] ) . '" />',
						__( 'Darkens the bottom of the hero image so the department name stays readable.', 'medical-college-manager' )
					);
					$row(
						'<label for="mcm-dp-banner">' . esc_html__( 'Fallback hero image', 'medical-college-manager' ) . '</label>',
						'<input type="url" id="mcm-dp-banner" class="regular-text mcm-media-field" name="' . esc_attr( $opt . '[fallback_banner]' ) . '" value="' . esc_attr( $s['fallback_banner'] ) . '" placeholder="https://…" /> <button type="button" class="button mcm-media-pick" data-type="image" data-size="full" data-minw="1600">' . esc_html__( 'Select Image', 'medical-college-manager' ) . '</button> <button type="button" class="button-link mcm-media-clear">' . esc_html__( 'Clear', 'medical-college-manager' ) . '</button><span class="mcm-media-preview"></span>',
						__( 'Used for departments without their own hero image. Leave empty for a plain colour band.', 'medical-college-manager' )
					);
					$row(
						'<label for="mcm-dp-hod">' . esc_html__( 'HOD photo position', 'medical-college-manager' ) . '</label>',
						'<select id="mcm-dp-hod" name="' . esc_attr( $opt . '[hod_layout]' ) . '"><option value="left" ' . selected( 'left', $s['hod_layout'], false ) . '>' . esc_html__( 'Left', 'medical-college-manager' ) . '</option><option value="right" ' . selected( 'right', $s['hod_layout'], false ) . '>' . esc_html__( 'Right', 'medical-college-manager' ) . '</option></select>'
					);
					$row(
						'<label for="mcm-dp-cols">' . esc_html__( 'Faculty columns', 'medical-college-manager' ) . '</label>',
						'<input type="number" id="mcm-dp-cols" min="2" max="5" class="small-text" name="' . esc_attr( $opt . '[faculty_cols]' ) . '" value="' . esc_attr( $s['faculty_cols'] ) . '" />'
					);
					$row(
						'<label for="mcm-dp-style">' . esc_html__( 'Faculty card style', 'medical-college-manager' ) . '</label>',
						'<select id="mcm-dp-style" name="' . esc_attr( $opt . '[card_style]' ) . '"><option value="card" ' . selected( 'card', $s['card_style'], false ) . '>' . esc_html__( 'Card (border)', 'medical-college-manager' ) . '</option><option value="flat" ' . selected( 'flat', $s['card_style'], false ) . '>' . esc_html__( 'Flat', 'medical-college-manager' ) . '</option></select><br />' . $check( 'show_designation', __( 'Show designation', 'medical-college-manager' ) ) . '<br />' . $check( 'show_department', __( 'Show department', 'medical-college-manager' ) )
					);
					$row(
						'<label for="mcm-dp-pubs">' . esc_html__( 'Publications shown', 'medical-college-manager' ) . '</label>',
						'<input type="number" id="mcm-dp-pubs" min="1" max="100" class="small-text" name="' . esc_attr( $opt . '[pubs_limit]' ) . '" value="' . esc_attr( $s['pubs_limit'] ) . '" />',
						__( 'Newest first; a "View all" link appears when there are more.', 'medical-college-manager' )
					);
					$row(
						'<label for="mcm-dp-empty">' . esc_html__( 'Empty sections', 'medical-college-manager' ) . '</label>',
						'<select id="mcm-dp-empty" name="' . esc_attr( $opt . '[empty]' ) . '"><option value="hide" ' . selected( 'hide', $s['empty'], false ) . '>' . esc_html__( 'Hide them', 'medical-college-manager' ) . '</option><option value="soon" ' . selected( 'soon', $s['empty'], false ) . '>' . esc_html__( 'Show "Information will be available soon"', 'medical-college-manager' ) . '</option></select>',
						__( 'The order never changes. Vision & Mission stays hidden until both are filled in.', 'medical-college-manager' )
					);
					$row( esc_html__( 'More from this department', 'medical-college-manager' ), $check( 'related_tabs', __( 'Show tabs for the department\'s other content (courses, timetable, events, notices, tenders, jobs…)', 'medical-college-manager' ) ) );
					?>
				</table>
				<h2 class="title"><?php esc_html_e( 'Section titles', 'medical-college-manager' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Leave a box empty to use the default (translated) title.', 'medical-college-manager' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					foreach ( self::default_titles() as $k => $default ) {
						$val = isset( $s['titles'][ $k ] ) ? $s['titles'][ $k ] : '';
						$row(
							'<label for="mcm-dp-t-' . esc_attr( $k ) . '">' . esc_html( $default ) . '</label>',
							'<input type="text" id="mcm-dp-t-' . esc_attr( $k ) . '" class="regular-text" name="' . esc_attr( $opt . '[titles][' . $k . ']' ) . '" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $default ) . '" />'
						);
					}
					?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/*
	 * ----------------------------------------------------------------
	 * Rendering
	 * ----------------------------------------------------------------
	 */

	/**
	 * The whole page for a department, or null when it could not be built
	 * (the caller then shows the classic page — the site never breaks).
	 *
	 * @param WP_Term $term Department.
	 * @return string|null
	 */
	public static function render_or_null( $term ) {
		$level = ob_get_level();
		try {
			return self::render( $term );
		} catch ( \Throwable $e ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			MCM_Health::report( 'department page template', $e );
			return null;
		}
	}

	/**
	 * The whole page: eight sections in their fixed order, then related tabs.
	 *
	 * @param WP_Term $term Department.
	 * @return string
	 */
	public static function render( $term ) {
		if ( ! $term instanceof WP_Term || 'mcm_department' !== $term->taxonomy ) {
			return '';
		}
		return self::keep_post( array( __CLASS__, 'build' ), array( $term ) );
	}

	/**
	 * Run a renderer and put the global $post back afterwards — the section
	 * queries loop with the_post(), and wp_reset_postdata() alone cannot restore
	 * it where there is no main query (AJAX, REST, WP-CLI).
	 *
	 * @param callable $cb   Renderer.
	 * @param array    $args Arguments.
	 * @return string
	 */
	private static function keep_post( $cb, $args ) {
		$saved = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		try {
			return (string) call_user_func_array( $cb, $args );
		} finally {
			$GLOBALS['post'] = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the caller's post.
			if ( $saved instanceof WP_Post ) {
				setup_postdata( $saved );
			}
		}
	}

	/**
	 * The page body; see render().
	 *
	 * @internal
	 * @param WP_Term $term Department.
	 * @return string
	 */
	public static function build( $term ) {
		MCM_Assets::need();
		self::begin_instance();
		$s     = self::settings();
		$html  = '';
		$shown = 0; // Tint every other section that is actually shown.
		foreach ( self::SECTIONS as $key ) {
			$part = self::section( $key, $term, $shown );
			if ( '' !== $part ) {
				$html .= $part;
				++$shown;
			}
		}
		if ( '1' === (string) $s['related_tabs'] ) {
			$html .= self::related( $term, 1 === $shown % 2 );
		}
		return '<div class="mcm-dept" style="' . esc_attr( self::vars() ) . '">' . $html . '</div>';
	}

	/**
	 * The colour/layout settings as CSS custom properties (values sanitized
	 * by settings(); hex colours and numbers only).
	 *
	 * @return string
	 */
	private static function vars() {
		$s = self::settings();
		return sprintf(
			'--mcm-dept-heading:%1$s;--mcm-dept-accent:%2$s;--mcm-dept-accent-text:%3$s;--mcm-dept-card:%4$s;--mcm-dept-overlay:%5$s;--mcm-dept-cols:%6$d;',
			$s['heading_color'],
			$s['accent_color'],
			self::readable( $s['accent_color'], $s['card_bg'] ),
			$s['card_bg'],
			number_format( (int) $s['overlay'] / 100, 2, '.', '' ),
			(int) $s['faculty_cols']
		);
	}

	/**
	 * The colour, darkened just enough to be readable as small text (WCAG AA,
	 * 4.5:1) on both white and the card background.
	 *
	 * @param string $hex Colour (#rgb or #rrggbb).
	 * @param string $bg  Card background colour.
	 * @return string #rrggbb
	 */
	public static function readable( $hex, $bg ) {
		$rgb = function ( $h ) {
			$h = ltrim( (string) $h, '#' );
			if ( 3 === strlen( $h ) ) {
				$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
			}
			return 6 === strlen( $h ) && ctype_xdigit( $h ) ? array_map( 'hexdec', str_split( $h, 2 ) ) : array( 0, 0, 0 );
		};
		$lum = function ( $c ) {
			$l = array();
			foreach ( $c as $v ) {
				$v   = $v / 255;
				$l[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
			}
			return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
		};
		// Text sits on white or on the card colour: check against the darker.
		$light = min( 1.0, $lum( $rgb( $bg ) ) );
		$c     = $rgb( $hex );
		// Darken in 7% steps until 4.5:1 (40 steps reach black from anything).
		for ( $i = 0; $i < 40; $i++ ) {
			if ( ( $light + 0.05 ) / ( $lum( $c ) + 0.05 ) >= 4.5 ) {
				break;
			}
			$c = array_map(
				function ( $v ) {
					return (int) floor( $v * 0.93 );
				},
				$c
			);
		}
		return sprintf( '#%02x%02x%02x', $c[0], $c[1], $c[2] );
	}

	/**
	 * One section's HTML ('' when it is empty and the setting hides empties,
	 * or when the department hides that content under Page sections).
	 * Building block: use part() or render(), which also restore the global $post.
	 *
	 * @param string  $key  Section key (see SECTIONS).
	 * @param WP_Term $term Department.
	 * @param int     $i    Position among the shown sections (odd = tinted).
	 * @param string  $tag  Hero title tag (h1 on the page; a widget may choose).
	 * @return string
	 */
	public static function section( $key, $term, $i = 0, $tag = 'h1' ) {
		if ( ! $term instanceof WP_Term ) {
			return '';
		}
		if ( 'hero' === $key ) {
			return self::hero( $term, $tag );
		}
		// Departments → Edit → Page sections can hide a content type.
		$hidden = MCM_TermMeta::hidden_sections( $term->term_id );
		if ( ( 'publications' === $key && isset( $hidden['mcm_publication'] ) ) || ( 'faculty' === $key && isset( $hidden['mcm_team'], $hidden['mcm_consultant'] ) ) ) {
			return '';
		}
		$method = 'body_' . $key;
		$body   = method_exists( __CLASS__, $method ) ? (string) self::$method( $term ) : '';
		if ( '' === $body ) {
			// Vision & Mission is mandatory: nothing until both are filled in.
			if ( 'vision_mission' === $key || 'soon' !== self::settings()['empty'] ) {
				return '';
			}
			$body = '<p class="mcm-dept-soon">' . esc_html__( 'Information will be available soon.', 'medical-college-manager' ) . '</p>';
		}
		$id = self::id( $key );
		return '<section class="mcm-dept-section mcm-dept-section--' . esc_attr( $key ) . ( 1 === $i % 2 ? ' is-tinted' : '' ) . '" aria-labelledby="' . esc_attr( $id ) . '"><div class="mcm-dept-wrap"><h2 class="mcm-dept-h" id="' . esc_attr( $id ) . '">' . esc_html( self::title( $key ) ) . '</h2>' . $body . '</div></section>';
	}

	/**
	 * Image markup for a Media Library URL (srcset, width, height) or a plain
	 * <img> for an external URL.
	 *
	 * @param string $url   Image URL.
	 * @param string $size  Size to request.
	 * @param array  $attrs Extra attributes.
	 * @return string
	 */
	private static function image( $url, $size, $attrs ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return '';
		}
		$id = MCM_Query::attachment_id_for( $url );
		if ( ! $id ) {
			$id = MCM_Query::attachment_id_for( (string) preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $url ) );
		}
		if ( $id ) {
			$img = wp_get_attachment_image( $id, $size, false, $attrs );
			if ( $img ) {
				return $img;
			}
		}
		$out = '<img src="' . esc_url( $url ) . '"';
		foreach ( $attrs as $k => $v ) {
			$out .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		return $out . ' />';
	}

	/**
	 * 1. Hero: the department video, else its image, else the fallback banner,
	 * else a colour band — always with the department name as the page <h1>.
	 *
	 * @param WP_Term $term Department.
	 * @param string  $tag  Title tag: h1 (page), h2 or div.
	 * @return string
	 */
	private static function hero( $term, $tag = 'h1' ) {
		$tag   = in_array( $tag, array( 'h1', 'h2', 'div' ), true ) ? $tag : 'h1';
		$video = (string) MCM_Safe::term_meta( $term->term_id, 'mcm_term_video' );
		$embed = '' !== trim( $video ) ? MCM_Query::video_embed( $video, 'mcm-dept-hero-video' ) : '';
		$image = trim( (string) MCM_Safe::term_meta( $term->term_id, 'mcm_term_banner' ) );
		if ( '' === $image && '' === $embed ) {
			$image = trim( (string) self::settings()['fallback_banner'] );
		}
		$media   = '' !== $embed ? $embed : self::image(
			$image,
			'full',
			array(
				'class'         => 'mcm-dept-hero-img',
				'alt'           => '',
				'sizes'         => '100vw',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
			)
		);
		$eyebrow = '';
		if ( $term->parent ) {
			$parent = get_term( $term->parent, 'mcm_department' );
			if ( $parent instanceof WP_Term ) {
				$eyebrow = '<span class="mcm-dept-hero-tag">' . esc_html( $parent->name ) . '</span>';
			}
		}
		$cls = 'mcm-dept-hero' . ( '' !== $embed ? ' has-video' : ( '' !== $media ? ' has-image' : '' ) );
		$tid = self::id( 'title' );
		return '<section class="' . esc_attr( $cls ) . '" aria-labelledby="' . esc_attr( $tid ) . '">' . $media
			. '<div class="mcm-dept-hero-text"><div class="mcm-dept-wrap">' . $eyebrow . '<' . $tag . ' class="mcm-dept-title" id="' . esc_attr( $tid ) . '">' . esc_html( $term->name ) . '</' . $tag . '></div></div></section>';
	}

	/**
	 * 2. Message from the HOD.
	 *
	 * @param WP_Term $term Department.
	 * @return string
	 */
	private static function body_hod( $term ) {
		$id      = $term->term_id;
		$name    = trim( (string) MCM_Safe::term_meta( $id, 'mcm_term_hod' ) );
		$role    = trim( (string) MCM_Safe::term_meta( $id, 'mcm_term_hod_designation' ) );
		$photo   = trim( (string) MCM_Safe::term_meta( $id, 'mcm_term_hod_photo' ) );
		$message = trim( (string) MCM_Safe::term_meta( $id, 'mcm_term_message' ) );
		if ( '' === $message && '' === $name ) {
			return '';
		}
		$right = 'right' === self::settings()['hod_layout'];
		$fig   = '';
		if ( '' !== $photo || '' !== $name || '' !== $role ) {
			// The caption names the person, so the photo itself has an empty alt.
			$fig = '<figure class="mcm-dept-hod-photo">' . ( '' !== $photo ? self::image(
				$photo,
				'large',
				array(
					'class'   => 'mcm-dept-photo',
					'alt'     => '',
					'loading' => 'lazy',
				)
			) : '<span class="mcm-dept-photo mcm-dept-photo--empty" aria-hidden="true"></span>' )
				. ( '' !== $name || '' !== $role ? '<figcaption>' . ( '' !== $name ? '<span class="mcm-dept-hod-name">' . esc_html( $name ) . '</span>' : '' ) . ( '' !== $role ? '<span class="mcm-dept-hod-role">' . esc_html( $role ) . '</span>' : '' ) . '</figcaption>' : '' )
				. '</figure>';
		}
		$quote = '' !== $message ? '<blockquote class="mcm-dept-quote">' . wpautop( esc_html( $message ) ) . '</blockquote>' : '';
		return '<div class="mcm-dept-hod' . ( $right ? ' is-right' : '' ) . ( '' === $fig ? ' no-photo' : '' ) . '">' . $fig . $quote . '</div>';
	}

	/**
	 * 3. Introduction.
	 *
	 * @param WP_Term $term Department.
	 * @return string
	 */
	private static function body_introduction( $term ) {
		$intro = trim( (string) MCM_Safe::term_meta( $term->term_id, 'mcm_term_intro' ) );
		return '' === $intro ? '' : '<div class="mcm-dept-intro">' . wpautop( wp_kses_post( $intro ) ) . '</div>';
	}

	/**
	 * 4. Vision & Mission (both required).
	 *
	 * @param WP_Term $term Department.
	 * @return string
	 */
	private static function body_vision_mission( $term ) {
		$vision  = trim( (string) MCM_Safe::term_meta( $term->term_id, 'mcm_term_vision' ) );
		$mission = trim( (string) MCM_Safe::term_meta( $term->term_id, 'mcm_term_mission' ) );
		if ( '' === $vision || '' === $mission ) {
			return '';
		}
		$card   = function ( $title, $text, $icon ) {
			return '<div class="mcm-dept-card"><h3><span class="mcm-dept-card-icon" aria-hidden="true">' . $icon . '</span>' . esc_html( $title ) . '</h3>' . wpautop( esc_html( $text ) ) . '</div>';
		};
		$eye    = '<svg viewBox="0 0 24 24" width="20" height="20" focusable="false"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17a5 5 0 110-10 5 5 0 010 10zm0-8a3 3 0 100 6 3 3 0 000-6z"/></svg>';
		$target = '<svg viewBox="0 0 24 24" width="20" height="20" focusable="false"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 18a8 8 0 110-16 8 8 0 010 16zm0-14a6 6 0 100 12 6 6 0 000-12zm0 10a4 4 0 110-8 4 4 0 010 8zm0-6a2 2 0 100 4 2 2 0 000-4z"/></svg>';
		return '<div class="mcm-dept-vm">' . $card( __( 'Vision', 'medical-college-manager' ), $vision, $eye ) . $card( __( 'Mission', 'medical-college-manager' ), $mission, $target ) . '</div>';
	}

	/**
	 * 5. Services / Facilities.
	 *
	 * @param WP_Term $term Department.
	 * @return string
	 */
	private static function body_services( $term ) {
		$rows = MCM_TermMeta::services( $term->term_id );
		if ( ! $rows ) {
			return '';
		}
		$out = '<div class="mcm-dept-services">';
		foreach ( $rows as $r ) {
			$date = '';
			if ( '' !== $r['date_from'] ) {
				$date = MCM_Query::fmt_date( $r['date_from'] );
				if ( '' !== $r['date_to'] && $r['date_to'] !== $r['date_from'] ) {
					$date .= ' – ' . MCM_Query::fmt_date( $r['date_to'] );
				}
			}
			$out .= '<article class="mcm-dept-service">'
				. ( '' !== $r['image'] ? '<div class="mcm-dept-service-img">' . self::image(
					$r['image'],
					'medium',
					array(
						'alt'     => '',
						'loading' => 'lazy',
					)
				) . '</div>' : '' )
				. '<h3>' . esc_html( $r['title'] ) . '</h3>'
				. ( '' !== $r['description'] ? wpautop( esc_html( $r['description'] ) ) : '' )
				. ( '' !== $date ? '<span class="mcm-dept-date">' . esc_html( $date ) . '</span>' : '' )
				. '</article>';
		}
		return $out . '</div>';
	}

	/**
	 * 6. Faculty: team members and consultants tagged with the department,
	 * grouped by Staff Type (Teaching & Technical first, then Support, then any
	 * other type). Consultants and members without a type join the first group.
	 *
	 * @param WP_Term $term Department.
	 * @return string
	 */
	private static function body_faculty( $term ) {
		$hidden = MCM_TermMeta::hidden_sections( $term->term_id );
		$types  = array_values( array_diff( array( 'mcm_team', 'mcm_consultant' ), array_keys( $hidden ) ) );
		if ( ! $types ) {
			return '';
		}
		$q      = new WP_Query(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- A department's whole faculty on one page.
				'no_found_rows'  => true,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Department faculty list.
					array(
						'taxonomy'         => 'mcm_department',
						'terms'            => $term->term_id,
						'include_children' => false,
					),
				),
			)
		);
		$people = $q->posts;
		if ( ! $people ) {
			return '';
		}
		// One query for every photo instead of two per person.
		update_post_thumbnail_cache( $q );

		// Group order: the two seeded types first, then any others by name.
		$order  = array(
			'teaching-technical-staff' => 0,
			'support-staff'            => 1,
		);
		$groups = array();
		$names  = array();
		foreach ( $people as $p ) {
			$slug  = 'teaching-technical-staff';
			$types = 'mcm_team' === $p->post_type ? get_the_terms( $p->ID, 'mcm_staff_type' ) : false;
			if ( $types && ! is_wp_error( $types ) ) {
				$slug           = $types[0]->slug;
				$names[ $slug ] = $types[0]->name;
			}
			$groups[ $slug ][] = $p;
		}
		uksort(
			$groups,
			function ( $a, $b ) use ( $order, $names ) {
				$oa = isset( $order[ $a ] ) ? $order[ $a ] : 2;
				$ob = isset( $order[ $b ] ) ? $order[ $b ] : 2;
				return $oa === $ob ? strcmp( isset( $names[ $a ] ) ? $names[ $a ] : $a, isset( $names[ $b ] ) ? $names[ $b ] : $b ) : $oa - $ob;
			}
		);

		$s   = self::settings();
		$out = '';
		foreach ( $groups as $slug => $members ) {
			$t     = get_term_by( 'slug', $slug, 'mcm_staff_type' );
			$label = $t ? $t->name : ( 'teaching-technical-staff' === $slug ? __( 'Teaching & Technical Staff', 'medical-college-manager' ) : $slug );
			if ( count( $groups ) > 1 || 'teaching-technical-staff' !== $slug || $t ) {
				$out .= '<h3 class="mcm-dept-group">' . esc_html( $label ) . '</h3>';
			}
			$out .= '<div class="mcm-dept-faculty mcm-dept-cols-' . (int) $s['faculty_cols'] . ( 'flat' === $s['card_style'] ? ' is-flat' : '' ) . '">';
			foreach ( $members as $p ) {
				$out .= self::person( $p, $s );
			}
			$out .= '</div>';
		}
		return $out;
	}

	/**
	 * One faculty card: 4:5 photo (own, or the gender/default photo), name,
	 * designation and department as configured.
	 *
	 * @param WP_Post $p Person.
	 * @param array   $s Settings.
	 * @return string
	 */
	private static function person( $p, $s ) {
		$link  = get_permalink( $p );
		$photo = get_the_post_thumbnail(
			$p,
			'medium_large',
			array(
				'class'   => 'mcm-dept-photo',
				'alt'     => '', // The name is the link text.
				'loading' => 'lazy',
			)
		);
		if ( '' === $photo ) {
			$photo = MCM_Query::default_avatar( 'mcm-dept-photo', 'mcm-dept-photo mcm-dept-photo--empty', $p->ID, 'medium_large' );
		}
		$role = '1' === (string) $s['show_designation'] ? trim( (string) MCM_Safe::meta( $p->ID, 'mcm_designation' ) ) : '';
		$dept = '1' === (string) $s['show_department'] ? implode( ', ', MCM_Query::cached_term_names( $p->ID, 'mcm_department' ) ) : '';
		return '<article class="mcm-dept-person"><a class="mcm-dept-person-link" href="' . esc_url( $link ) . '">'
			. '<div class="mcm-dept-person-photo">' . $photo . '</div>'
			. '<div class="mcm-dept-person-body"><span class="mcm-dept-person-name">' . esc_html( get_the_title( $p ) ) . '</span>'
			. ( '' !== $role ? '<span class="mcm-dept-person-role">' . esc_html( $role ) . '</span>' : '' )
			. ( '' !== $dept ? '<span class="mcm-dept-person-role">' . esc_html( $dept ) . '</span>' : '' )
			. '</div></a></article>';
	}

	/**
	 * 7. Publications: this department only, newest year first, with "View
	 * all" when there are more than the limit (?all_publications=1).
	 *
	 * @param WP_Term $term Department.
	 * @return string
	 */
	private static function body_publications( $term ) {
		$limit = (int) self::settings()['pubs_limit'];
		$max   = 200;
		// Read-only display switch (?all_publications=1); no nonce needed.
		$all  = isset( $_GET['all_publications'] ) && '1' === sanitize_key( wp_unslash( $_GET['all_publications'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args = array(
			'post_type'      => 'mcm_publication',
			'post_status'    => 'publish',
			'posts_per_page' => $all ? $max : $limit,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Department publications.
				array(
					'taxonomy'         => 'mcm_department',
					'terms'            => $term->term_id,
					'include_children' => false,
				),
			),
		);
		$args = MCM_Query::order_by_optional_meta( $args, 'mcm_pub_year', 'SIGNED', 'DESC' );
		$q    = new WP_Query( $args );
		if ( ! $q->have_posts() ) {
			return '';
		}
		try {
			$html = MCM_Query::render_citation_list( $q );
		} finally {
			wp_reset_postdata();
		}
		$total = (int) $q->found_posts;
		$link  = get_term_link( $term );
		if ( ! $all && $total > $limit && ! is_wp_error( $link ) ) {
			// The full list is a feature of the template; with the template
			// off the department page itself lists every publication.
			$href  = self::enabled() ? add_query_arg( 'all_publications', '1', $link ) . '#mcm-dept-publications' : $link;
			$html .= '<p><a class="mcm-dept-more" href="' . esc_url( $href ) . '">' . esc_html(
				sprintf(
					/* translators: %d: number of publications. */
					_n( 'View all %d publication', 'View all %d publications', $total, 'medical-college-manager' ),
					$total
				)
			) . ' &rarr;</a></p>';
		} elseif ( $all && $total > $max ) {
			$html .= '<p class="mcm-dept-soon">' . esc_html(
				sprintf(
					/* translators: 1: number shown, 2: total number of publications. */
					__( 'Showing the newest %1$d of %2$d publications.', 'medical-college-manager' ),
					$max,
					$total
				)
			) . '</p>';
		}
		return $html;
	}

	/**
	 * 8. Contact information (+ optional Google Maps embed).
	 *
	 * @param WP_Term $term Department.
	 * @return string
	 */
	private static function body_contact( $term ) {
		$id    = $term->term_id;
		$phone = trim( (string) MCM_Safe::term_meta( $id, 'mcm_term_phone' ) );
		$email = trim( (string) MCM_Safe::term_meta( $id, 'mcm_term_email' ) );
		$addr  = trim( (string) MCM_Safe::term_meta( $id, 'mcm_term_address' ) );
		$hours = trim( (string) MCM_Safe::term_meta( $id, 'mcm_term_hours' ) );
		$map   = MCM_TermMeta::sanitize_map( MCM_Safe::term_meta( $id, 'mcm_term_map' ) );
		if ( '' === $phone && '' === $email && '' === $addr && '' === $hours && '' === $map ) {
			return '';
		}
		$row  = function ( $label, $value ) {
			return '<li><strong>' . esc_html( $label ) . '</strong><span>' . $value . '</span></li>';
		};
		$list = '';
		if ( '' !== $phone ) {
			$list .= $row( __( 'Phone', 'medical-college-manager' ), '<a href="' . esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>' );
		}
		if ( '' !== $email && is_email( $email ) ) {
			$list .= $row( __( 'Email', 'medical-college-manager' ), '<a href="' . esc_url( 'mailto:' . antispambot( $email ) ) . '">' . esc_html( antispambot( $email ) ) . '</a>' );
		}
		if ( '' !== $addr ) {
			$list .= $row( __( 'Address', 'medical-college-manager' ), nl2br( esc_html( $addr ) ) );
		}
		if ( '' !== $hours ) {
			$list .= $row( __( 'Office hours', 'medical-college-manager' ), esc_html( $hours ) );
		}
		$frame = '' !== $map ? '<div class="mcm-dept-map"><iframe src="' . esc_url( $map ) . '" title="' . esc_attr(
			sprintf(
				/* translators: %s: department name. */
				__( 'Map: %s', 'medical-college-manager' ),
				$term->name
			)
		) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>' : '';
		return '<div class="mcm-dept-contact' . ( '' === $frame ? ' no-map' : '' ) . '">' . ( '' !== $list ? '<ul>' . $list . '</ul>' : '' ) . $frame . '</div>';
	}

	/**
	 * "More from this department": a tab per other content type with content
	 * (faculty and publications are shown above), honouring Hide Sections.
	 * Without JavaScript every panel is simply shown in turn.
	 *
	 * @param WP_Term $term   Department.
	 * @param bool    $tinted Continue the section tint alternation.
	 * @return string
	 */
	private static function related( $term, $tinted = false ) {
		$skip   = array( 'mcm_team', 'mcm_consultant', 'mcm_publication' );
		$blocks = array();
		foreach ( MCM_Templates::term_blocks( $term ) as $b ) {
			if ( isset( $b['atts']['type'] ) && ! in_array( $b['atts']['type'], $skip, true ) ) {
				$blocks[] = $b;
			}
		}
		if ( ! $blocks ) {
			return '';
		}
		$tabs   = '';
		$panels = '';
		$rid    = self::id( 'related' );
		foreach ( $blocks as $i => $b ) {
			$tid   = self::id( 'tab-' . sanitize_key( $b['atts']['type'] ) );
			$pid   = $tid . '-panel';
			$tabs .= '<button type="button" role="tab" id="' . esc_attr( $tid ) . '" aria-controls="' . esc_attr( $pid ) . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '" tabindex="' . ( 0 === $i ? '0' : '-1' ) . '">' . esc_html( $b['label'] ) . '</button>';
			// Without JavaScript the tab list stays hidden and every panel shows
			// under its own heading; the script turns them into tabs.
			$panels .= '<div class="mcm-dept-tabpanel" id="' . esc_attr( $pid ) . '" data-mcm-tab="' . esc_attr( $tid ) . '"><h3 class="mcm-dept-panel-h">' . esc_html( $b['label'] ) . '</h3>' . MCM_Shortcodes::render_block( $b['atts'] ) . '</div>';
		}
		return '<section class="mcm-dept-section mcm-dept-section--related' . ( $tinted ? ' is-tinted' : '' ) . '" aria-labelledby="' . esc_attr( $rid ) . '"><div class="mcm-dept-wrap"><h2 class="mcm-dept-h" id="' . esc_attr( $rid ) . '">' . esc_html( self::title( 'related' ) ) . '</h2>'
			. '<div class="mcm-dept-tabs" role="tablist" aria-labelledby="' . esc_attr( $rid ) . '" hidden>' . $tabs . '</div>' . $panels . '</div></section>';
	}

	/*
	 * ----------------------------------------------------------------
	 * Shortcodes
	 * ----------------------------------------------------------------
	 */

	/** Register the section shortcodes (the existing ones stay unchanged). */
	public function shortcodes() {
		add_shortcode( 'mcm_department_page', array( $this, 'sc_page' ) );
		foreach ( array( 'introduction', 'vision_mission', 'services', 'faculty', 'publications', 'contact' ) as $key ) {
			add_shortcode(
				'mcm_department_' . $key,
				function ( $atts ) use ( $key ) {
					return MCM_Department_Page::sc_section( $key, $atts );
				}
			);
		}
	}

	/**
	 * [mcm_department_page department="anatomy"] — the whole template.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function sc_page( $atts ) {
		$term = MCM_TermMeta::resolve_term( is_array( $atts ) ? $atts : array() );
		return $term ? MCM_Safe::render( array( __CLASS__, 'render' ), 'shortcode mcm_department_page', array( $term ) ) : '';
	}

	/**
	 * [mcm_department_{section} department="…"] — one section.
	 *
	 * @param string       $key  Section key.
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function sc_section( $key, $atts ) {
		$term = MCM_TermMeta::resolve_term( is_array( $atts ) ? $atts : array() );
		if ( ! $term ) {
			return '';
		}
		return MCM_Safe::render( array( __CLASS__, 'part' ), 'shortcode mcm_department_' . $key, array( $key, $term ) );
	}

	/**
	 * One section on its own (shortcodes, Elementor widgets), with the colour
	 * settings applied. Unguarded; callers wrap it in MCM_Safe::render().
	 *
	 * @param string  $key  Section key (see SECTIONS).
	 * @param WP_Term $term Department.
	 * @param string  $tag  Hero title tag.
	 * @return string
	 */
	public static function part( $key, $term, $tag = 'h1' ) {
		MCM_Assets::need();
		self::begin_instance();
		$html = self::keep_post( array( __CLASS__, 'section' ), array( $key, $term, 0, $tag ) );
		if ( '' === $html ) {
			return '';
		}
		return '<div class="mcm-dept mcm-dept--part" style="' . esc_attr( self::vars() ) . '">' . $html . '</div>';
	}

	/*
	 * ----------------------------------------------------------------
	 * Structured data
	 * ----------------------------------------------------------------
	 */

	/** CollegeOrUniversity JSON-LD on department pages (template enabled). */
	public function json_ld() {
		// Only when the template really is this page (not a theme's own
		// template, a disabled archive or the classic fallback).
		if ( ! self::$is_page || ! is_tax( 'mcm_department' ) ) {
			return;
		}
		echo MCM_Safe::render( array( __CLASS__, 'json_ld_html' ), 'department JSON-LD' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded in json_ld_html().
	}

	/**
	 * The JSON-LD script for the current department.
	 *
	 * @return string
	 */
	public static function json_ld_html() {
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return '';
		}
		$id   = $term->term_id;
		$lh   = MCM_Query::letterhead();
		$link = get_term_link( $term );
		// Names are stored HTML-encoded ("Obstetrics &amp; Gynaecology");
		// JSON-LD wants the plain text.
		$data = array(
			'@context'           => 'https://schema.org',
			'@type'              => 'CollegeOrUniversity',
			'name'               => wp_specialchars_decode( $term->name, ENT_QUOTES ),
			'parentOrganization' => array(
				'@type' => 'CollegeOrUniversity',
				'name'  => wp_specialchars_decode( (string) $lh['org'], ENT_QUOTES ),
				'url'   => home_url( '/' ),
			),
		);
		if ( ! is_wp_error( $link ) ) {
			$data['url'] = $link;
		}
		$image = trim( (string) MCM_Safe::term_meta( $id, 'mcm_term_banner' ) );
		if ( '' !== $image ) {
			$data['image'] = $image;
		}
		$phone = trim( (string) MCM_Safe::term_meta( $id, 'mcm_term_phone' ) );
		if ( '' !== $phone ) {
			$data['telephone'] = $phone;
		}
		$email = trim( (string) MCM_Safe::term_meta( $id, 'mcm_term_email' ) );
		if ( '' !== $email && is_email( $email ) ) {
			$data['email'] = $email;
		}
		$addr = trim( (string) MCM_Safe::term_meta( $id, 'mcm_term_address' ) );
		if ( '' !== $addr ) {
			$data['address'] = array(
				'@type'         => 'PostalAddress',
				'streetAddress' => preg_replace( '/\s+/', ' ', $addr ),
			);
		}
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		return is_string( $json ) ? '<script type="application/ld+json">' . $json . '</script>' . "\n" : '';
	}
}
