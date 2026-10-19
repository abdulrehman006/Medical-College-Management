<?php
/**
 * Admin settings: design controls + raw Custom CSS.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Design, printing, privacy and page-banner settings.
 */
class MCM_Settings {

	const OPTION = 'mcm_settings';

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Default design values. Used on activation and as fallbacks.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'primary_color'         => '#0a6b5d',
			'accent_color'          => '#13a594',
			'text_color'            => '#1f2933',
			'muted_color'           => '#6b7280',
			'card_bg'               => '#ffffff',
			'page_bg'               => '#f5f7fa',
			'border_color'          => '#e5e7eb',
			'border_radius'         => '12',
			'card_shadow'           => '1', // 0 = off, 1 = on.
			'card_padding'          => '20',
			'gap'                   => '24',
			'columns'               => '3',
			'font_family'           => 'inherit',
			'title_size'            => '18',
			'image_height'          => '220',
			'hover_lift'            => '1',
			'archive_filters'       => '1', // show the filter bar on CPT/taxonomy archives.
			// Public, safe-fields-only student list ([mcm_students] + AJAX).
			// On by default so existing sites behave exactly as before.
			'public_students'       => '1',
			// Extra sources on the notice ticker/board/list (notices are always in).
			'notice_sources'        => array( 'job', 'tender' ),
			// A job counts as "Closing soon" this many days before its deadline.
			'job_closing_days'      => '3',
			// Content types offering a Print button. Any type may be enabled —
			// the print machinery is generic; these are just the sensible
			// defaults (a timetable and a student list are what get handed out
			// on paper). Developers can still use the mcm_print_types filter.
			'print_types'           => array( 'mcm_timetable', 'mcm_student', 'mcm_research', 'mcm_policy', 'mcm_job' ),
			// Branded stand-in shown when a person has no photograph.
			'default_avatar'        => '',
			// Gender-specific default photos (fall back to default_avatar).
			'default_avatar_male'   => '',
			'default_avatar_female' => '',
			// Letterhead used on printed / PDF output. Blank org name falls back
			// to the site title, blank logo to the theme logo or site icon.
			'print_logo'            => '',
			'print_org'             => '',
			'print_address'         => '',
			'print_footnote'        => '',
			'print_signature'       => '0',
			// auto | always | never - see MCM_Templates::should_show_image().
			'single_image'          => 'auto',
			// Per-archive title banner: [ post_type => [ 'video' => url, 'image' => url ] ].
			'archive_banners'       => array(),
			'custom_css'            => '',
		);
	}

	/**
	 * Banner (hero) HTML for a content type's archive page, from settings.
	 * Video wins over image; empty string when neither is set (so it hides).
	 *
	 * @param string $type Post type.
	 * @return string
	 */
	public static function archive_banner_html( $type ) {
		$s     = self::get();
		$all   = isset( $s['archive_banners'] ) && is_array( $s['archive_banners'] ) ? $s['archive_banners'] : array();
		$b     = isset( $all[ $type ] ) ? $all[ $type ] : array();
		$video = isset( $b['video'] ) ? $b['video'] : '';
		$image = isset( $b['image'] ) ? $b['image'] : '';
		if ( '' === trim( (string) $video ) && '' === trim( (string) $image ) ) {
			return '';
		}
		$obj = get_post_type_object( $type );
		$alt = $obj ? $obj->labels->name : '';
		return MCM_Query::hero_banner( $video, $image, $alt );
	}

	/** Post types that currently offer a Print button. */
	public static function print_types() {
		$s   = self::get();
		$out = isset( $s['print_types'] ) && is_array( $s['print_types'] ) ? $s['print_types'] : array();
		return array_values( array_filter( $out, 'post_type_exists' ) );
	}

	/**
	 * Merged settings (saved over defaults).
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
	}

	/**
	 * One saved setting (or its default).
	 *
	 * @param string $key Key.
	 */
	public static function value( $key ) {
		$all = self::get();
		return isset( $all[ $key ] ) ? $all[ $key ] : '';
	}

	/**
	 * Add the admin menu page.
	 */
	public function menu() {
		add_menu_page(
			__( 'Medical College Manager', 'medical-college-manager' ),
			__( 'College Manager', 'medical-college-manager' ),
			'manage_options',
			'mcm-settings',
			array( $this, 'render_page' ),
			'dashicons-buddicons-buddypress-logo',
			58
		);
	}

	/**
	 * Register the setting with its sanitize callback.
	 */
	public function register() {
		register_setting( 'mcm_settings_group', self::OPTION, array( $this, 'sanitize' ) );
	}

	/**
	 * Sanitize all incoming settings.
	 *
	 * @param array $input Submitted values.
	 */
	public function sanitize( $input ) {
		$defaults = self::default_settings();
		$out      = array();

		$colors = array( 'primary_color', 'accent_color', 'text_color', 'muted_color', 'card_bg', 'page_bg', 'border_color' );
		foreach ( $colors as $c ) {
			$out[ $c ] = isset( $input[ $c ] ) ? sanitize_hex_color( $input[ $c ] ) : $defaults[ $c ];
			if ( empty( $out[ $c ] ) ) {
				$out[ $c ] = $defaults[ $c ];
			}
		}

		$numbers = array( 'border_radius', 'card_padding', 'gap', 'columns', 'title_size', 'image_height' );
		foreach ( $numbers as $n ) {
			$out[ $n ] = isset( $input[ $n ] ) ? absint( $input[ $n ] ) : $defaults[ $n ];
		}

		$toggles = array( 'card_shadow', 'hover_lift', 'archive_filters', 'public_students' );
		foreach ( $toggles as $t ) {
			$out[ $t ] = ! empty( $input[ $t ] ) ? '1' : '0';
		}

		// Printable types — keep only real, registered post types.
		// Notice board sources: only the known extra sources are kept.
		$out['notice_sources'] = array();
		if ( ! empty( $input['notice_sources'] ) && is_array( $input['notice_sources'] ) ) {
			foreach ( $input['notice_sources'] as $src ) {
				$src = sanitize_key( MCM_Safe::str( $src ) );
				if ( in_array( $src, array( 'job', 'tender' ), true ) && ! in_array( $src, $out['notice_sources'], true ) ) {
					$out['notice_sources'][] = $src;
				}
			}
		}
		$out['job_closing_days'] = (string) min( 60, absint( isset( $input['job_closing_days'] ) ? MCM_Safe::str( $input['job_closing_days'] ) : '3' ) );

		$out['print_types'] = array();
		if ( ! empty( $input['print_types'] ) && is_array( $input['print_types'] ) ) {
			foreach ( $input['print_types'] as $pt ) {
				$pt = sanitize_key( $pt );
				if ( post_type_exists( $pt ) ) {
					$out['print_types'][] = $pt;
				}
			}
		}

		foreach ( array( 'default_avatar', 'default_avatar_male', 'default_avatar_female' ) as $mcm_key ) {
			$out[ $mcm_key ] = isset( $input[ $mcm_key ] ) ? esc_url_raw( MCM_Safe::str( $input[ $mcm_key ] ) ) : '';
		}
		$out['print_logo']      = isset( $input['print_logo'] ) ? esc_url_raw( $input['print_logo'] ) : '';
		$out['print_org']       = isset( $input['print_org'] ) ? sanitize_text_field( $input['print_org'] ) : '';
		$out['print_address']   = isset( $input['print_address'] ) ? sanitize_text_field( $input['print_address'] ) : '';
		$out['print_footnote']  = isset( $input['print_footnote'] ) ? sanitize_text_field( $input['print_footnote'] ) : '';
		$out['print_signature'] = ! empty( $input['print_signature'] ) ? '1' : '0';

		$mode                = isset( $input['single_image'] ) ? sanitize_key( $input['single_image'] ) : 'auto';
		$out['single_image'] = in_array( $mode, array( 'auto', 'always', 'never' ), true ) ? $mode : 'auto';

		// Per-archive banners — keep only real content types, sanitize the URLs.
		$out['archive_banners'] = array();
		if ( ! empty( $input['archive_banners'] ) && is_array( $input['archive_banners'] ) ) {
			foreach ( $input['archive_banners'] as $pt => $vals ) {
				$pt = sanitize_key( $pt );
				if ( ! post_type_exists( $pt ) || ! is_array( $vals ) ) {
					continue;
				}
				$video = isset( $vals['video'] ) ? esc_url_raw( $vals['video'] ) : '';
				$image = isset( $vals['image'] ) ? esc_url_raw( $vals['image'] ) : '';
				if ( '' !== $video || '' !== $image ) {
					$out['archive_banners'][ $pt ] = array(
						'video' => $video,
						'image' => $image,
					);
				}
			}
		}

		// font_family is interpolated raw into an inline <style> (--mcm-font),
		// so restrict it to characters that can appear in a font stack. Prevents
		// a "x;} body{display:none}" style-rule injection.
		$font               = isset( $input['font_family'] ) ? (string) $input['font_family'] : 'inherit';
		$font               = preg_replace( '/[^A-Za-z0-9 ,\'"\-]/', '', $font );
		$out['font_family'] = '' !== trim( $font ) ? $font : 'inherit';

		// Custom CSS — strip tags only, keep CSS intact.
		$out['custom_css'] = isset( $input['custom_css'] ) ? wp_strip_all_tags( $input['custom_css'] ) : '';

		return $out;
	}

	/**
	 * Build the dynamic CSS variables + rules from settings.
	 * Consumed by MCM_Assets to print inline.
	 *
	 * @return string
	 */
	public static function dynamic_css() {
		$s      = self::get();
		$shadow = '1' === $s['card_shadow'] ? '0 6px 20px rgba(0,0,0,.08)' : 'none';
		$lift   = '1' === $s['hover_lift'] ? 'translateY(-4px)' : 'none';

		$css  = ':root{';
		$css .= "--mcm-primary:{$s['primary_color']};";
		$css .= "--mcm-accent:{$s['accent_color']};";
		$css .= "--mcm-text:{$s['text_color']};";
		$css .= "--mcm-muted:{$s['muted_color']};";
		$css .= "--mcm-card-bg:{$s['card_bg']};";
		$css .= "--mcm-page-bg:{$s['page_bg']};";
		$css .= "--mcm-border:{$s['border_color']};";
		$css .= "--mcm-radius:{$s['border_radius']}px;";
		$css .= "--mcm-pad:{$s['card_padding']}px;";
		$css .= "--mcm-gap:{$s['gap']}px;";
		$css .= "--mcm-title-size:{$s['title_size']}px;";
		$css .= "--mcm-img-h:{$s['image_height']}px;";
		$css .= "--mcm-shadow:{$shadow};";
		$css .= "--mcm-lift:{$lift};";
		$css .= "--mcm-font:{$s['font_family']};";
		$css .= "--mcm-cols:{$s['columns']};";
		$css .= '}';

		// Append raw custom CSS verbatim.
		if ( ! empty( $s['custom_css'] ) ) {
			$css .= "\n" . $s['custom_css'];
		}
		return $css;
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		$s = self::get();
		?>
		<div class="wrap mcm-settings-wrap">
			<h1><?php esc_html_e( 'Medical College Manager — Design & Settings', 'medical-college-manager' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Everything below is live and dynamic. Adjust colors, spacing and card style, or drop in your own CSS. All shortcodes update automatically.', 'medical-college-manager' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'mcm_settings_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Colors', 'medical-college-manager' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->color_row( 'primary_color', __( 'Primary Color', 'medical-college-manager' ), $s );
					$this->color_row( 'accent_color', __( 'Accent Color', 'medical-college-manager' ), $s );
					$this->color_row( 'text_color', __( 'Text Color', 'medical-college-manager' ), $s );
					$this->color_row( 'muted_color', __( 'Muted / Meta Text', 'medical-college-manager' ), $s );
					$this->color_row( 'card_bg', __( 'Card Background', 'medical-college-manager' ), $s );
					$this->color_row( 'page_bg', __( 'Section Background', 'medical-college-manager' ), $s );
					$this->color_row( 'border_color', __( 'Border Color', 'medical-college-manager' ), $s );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Layout & Cards', 'medical-college-manager' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->number_row( 'columns', __( 'Default Grid Columns', 'medical-college-manager' ), $s, 1, 6 );
					$this->number_row( 'gap', __( 'Gap Between Cards (px)', 'medical-college-manager' ), $s, 0, 80 );
					$this->number_row( 'card_padding', __( 'Card Padding (px)', 'medical-college-manager' ), $s, 0, 60 );
					$this->number_row( 'border_radius', __( 'Border Radius (px)', 'medical-college-manager' ), $s, 0, 50 );
					$this->number_row( 'title_size', __( 'Title Font Size (px)', 'medical-college-manager' ), $s, 12, 40 );
					$this->number_row( 'image_height', __( 'Image Height (px)', 'medical-college-manager' ), $s, 80, 500 );
					$this->toggle_row( 'card_shadow', __( 'Card Shadow', 'medical-college-manager' ), $s );
					$this->toggle_row( 'hover_lift', __( 'Hover Lift Effect', 'medical-college-manager' ), $s );
					$this->toggle_row( 'archive_filters', __( 'Filterable Archive Pages', 'medical-college-manager' ), $s );
					$this->single_image_row( $s );
					?>
					<tr>
						<th scope="row"><label for="font_family"><?php esc_html_e( 'Font Family', 'medical-college-manager' ); ?></label></th>
						<td>
							<input type="text" id="font_family" name="<?php echo esc_attr( self::OPTION ); ?>[font_family]" value="<?php echo esc_attr( $s['font_family'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'e.g. "Poppins", sans-serif — or leave as "inherit".', 'medical-college-manager' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title" id="mcm-default-photos"><?php esc_html_e( 'Default Photos', 'medical-college-manager' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Shown for consultants and team members who have no photo of their own: the photo for their Gender first (set Gender on each profile), then the general default photo, then a plain silhouette.', 'medical-college-manager' ); ?></p>
				<table class="form-table" role="presentation">
					<?php $this->avatar_row( $s ); ?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Printing & Letterhead', 'medical-college-manager' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Printed sheets and "Save as PDF" both use this letterhead, so a timetable or student list handed out on paper carries the college identity. Filters, result count and date are added automatically from whatever the visitor had on screen.', 'medical-college-manager' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					$this->print_types_row( $s );
					$this->letterhead_rows( $s );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Page Banners', 'medical-college-manager' ); ?></h2>
				<p class="description" style="max-width:820px;"><?php esc_html_e( 'Add a title banner to the top of any listing page — a video (YouTube/Vimeo link or an uploaded MP4) or a static image. Leave both blank to show no banner. A video takes priority over an image.', 'medical-college-manager' ); ?></p>
				<table class="form-table" role="presentation">
					<?php $this->banner_rows( $s ); ?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Notice board & jobs', 'medical-college-manager' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Notice board sources', 'medical-college-manager' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Notice board sources', 'medical-college-manager' ); ?></legend>
								<?php
								$mcm_sources = isset( $s['notice_sources'] ) && is_array( $s['notice_sources'] ) ? $s['notice_sources'] : array();
								foreach ( array(
									'job'    => __( 'Open jobs', 'medical-college-manager' ),
									'tender' => __( 'Open tenders', 'medical-college-manager' ),
								) as $mcm_src => $mcm_label ) {
									printf(
										'<label style="display:inline-block;margin:0 16px 6px 0;"><input type="checkbox" name="%1$s[notice_sources][]" value="%2$s" %3$s /> %4$s</label>',
										esc_attr( self::OPTION ),
										esc_attr( $mcm_src ),
										checked( in_array( $mcm_src, $mcm_sources, true ), true, false ),
										esc_html( $mcm_label )
									);
								}
								?>
								<p class="description" style="max-width:820px;"><?php esc_html_e( 'Notices always appear on the notice ticker, board and list. Tick a source to add its open items too (each item can still opt out with its own "Show on notice board" field). A shortcode can choose its own sources with include="notice,job,tender".', 'medical-college-manager' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="job_closing_days"><?php esc_html_e( 'Jobs: "Closing soon" days', 'medical-college-manager' ); ?></label></th>
						<td>
							<input type="number" min="0" max="60" id="job_closing_days" class="small-text" name="<?php echo esc_attr( self::OPTION ); ?>[job_closing_days]" value="<?php echo esc_attr( $s['job_closing_days'] ); ?>" />
							<p class="description"><?php esc_html_e( 'A job shows the "Closing soon" badge this many days before its last date.', 'medical-college-manager' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Privacy', 'medical-college-manager' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Public student directory', 'medical-college-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="public_students" name="<?php echo esc_attr( self::OPTION ); ?>[public_students]" value="1" <?php checked( $s['public_students'], '1' ); ?> />
								<?php esc_html_e( 'Anyone can view and search the student list', 'medical-college-manager' ); ?>
							</label>
							<p class="description" style="max-width:820px;">
								<?php esc_html_e( 'When on, any visitor can see and search the student list produced by [mcm_students]: Name, Student ID, PMDC No., Level, Program, Department, Batch and Status. Email, phone, date of birth, address, blood group, gender and guardian details are never shown on the website either way.', 'medical-college-manager' ); ?>
							</p>
							<p class="description" style="max-width:820px;">
								<?php esc_html_e( 'When off, the list (and its live search) is shown only to logged-in staff who can manage student records — administrators by default. Everyone else sees nothing where the list would be.', 'medical-college-manager' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Custom CSS', 'medical-college-manager' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Advanced: write any CSS here. It is printed after the generated styles, so it always wins. Use the .mcm- classes (see the readme / shortcode reference).', 'medical-college-manager' ); ?></p>
				<textarea name="<?php echo esc_attr( self::OPTION ); ?>[custom_css]" rows="14" style="width:100%;font-family:monospace;" placeholder=".mcm-card{ /* your styles */ }"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>

				<?php submit_button( __( 'Save Settings', 'medical-college-manager' ) ); ?>
			</form>

			<hr/>
			<?php $this->render_shortcode_reference(); ?>
		</div>
		<?php
	}

	/**
	 * All shortcodes grouped, with a plain-language description each. Single
	 * source of truth for the settings-page reference.
	 *
	 * @return array
	 */
	public static function shortcode_reference() {
		return array(
			__( 'Content lists — drop on any page or Elementor Shortcode block', 'medical-college-manager' ) => array(
				array( '[mcm_consultants]', __( 'Doctors & consultants directory — photo cards with a left-side search & filter sidebar.', 'medical-college-manager' ) ),
				array( '[mcm_team]', __( 'Staff / team member directory, in the same layout as consultants. staff_type="support-staff" (or "teaching-technical-staff") lists one staff type.', 'medical-college-manager' ) ),
				array( '[mcm_programs]', __( 'Degree programmes (MBBS, BDS, Nursing…) as cards.', 'medical-college-manager' ) ),
				array( '[mcm_courses]', __( 'Course listing — code, credit hours, semester, lead.', 'medical-college-manager' ) ),
				array( '[mcm_timetable]', __( 'Class timetable as a clean, printable table.', 'medical-college-manager' ) ),
				array( '[mcm_admissions]', __( 'Open admissions with dates, eligibility and an Apply button.', 'medical-college-manager' ) ),
				array( '[mcm_events]', __( 'Upcoming events, soonest first.', 'medical-college-manager' ) ),
				array( '[mcm_publications]', __( 'Research papers as a numbered Vancouver citation list, with DOI/PubMed links.', 'medical-college-manager' ) ),
				array( '[mcm_research]', __( 'Research projects — supervisor, status, and a downloadable paper.', 'medical-college-manager' ) ),
				array( '[mcm_scholarships]', __( 'Scholarships with amount, eligibility and an Apply button.', 'medical-college-manager' ) ),
				array( '[mcm_tenders]', __( 'Active tenders with download & submit links (auto-hide after the deadline).', 'medical-college-manager' ) ),
				array( '[mcm_policies]', __( 'Institutional policies, each with a downloadable document.', 'medical-college-manager' ) ),
				array( '[mcm_jobs]', __( 'Jobs / vacancies (alias [mcm_vacancies]): open jobs by default; status="closed" or status="all" for past ones. Filter with category="…" and department="…".', 'medical-college-manager' ) ),
				array( '[mcm_students]', __( 'Student list — safe fields only (name, PMDC No., department, batch). Put this on the Student Affairs page.', 'medical-college-manager' ) ),
			),
			__( 'Department page — auto-detect the current department, or add department="anatomy"', 'medical-college-manager' ) => array(
				array( '[mcm_department_hero]', __( 'The department’s video or image banner (hero) at the top of the page.', 'medical-college-manager' ) ),
				array( '[mcm_department_message]', __( 'The Head of Department’s message card (photo, name, message, contact).', 'medical-college-manager' ) ),
				array( '[mcm_department_intro]', __( 'Hero banner + HOD message together.', 'medical-college-manager' ) ),
				array( '[mcm_department_sections]', __( 'Every section the department actually has — courses, faculty, timetable, publications… built automatically.', 'medical-college-manager' ) ),
				array( '[mcm_department_page]', __( 'The whole department page template: the eight sections in their fixed order plus the related tabs (College Manager → Department Page).', 'medical-college-manager' ) ),
				array( '[mcm_department_introduction]', __( 'Introduction section (Departments → Edit → Introduction).', 'medical-college-manager' ) ),
				array( '[mcm_department_vision_mission]', __( 'Vision & Mission cards (shown once both are filled in).', 'medical-college-manager' ) ),
				array( '[mcm_department_services]', __( 'Services / Facilities cards, with dates where set.', 'medical-college-manager' ) ),
				array( '[mcm_department_faculty]', __( 'Faculty: team and consultants of the department, grouped by Staff Type.', 'medical-college-manager' ) ),
				array( '[mcm_department_publications]', __( 'The department’s publications, newest first, with “View all”.', 'medical-college-manager' ) ),
				array( '[mcm_department_contact]', __( 'Contact details: phone, email, address, office hours and map.', 'medical-college-manager' ) ),
			),
			__( 'Notice board & homepage pieces', 'medical-college-manager' ) => array(
				array( '[mcm_notice_ticker]', __( 'Scrolling headline strip — great just under the header.', 'medical-college-manager' ) ),
				array( '[mcm_notice_board]', __( 'Vertical auto-scrolling notices panel for a sidebar or homepage column.', 'medical-college-manager' ) ),
				array( '[mcm_notices]', __( 'Static, filterable notices list.', 'medical-college-manager' ) ),
				array( '[mcm_notice_board include="notice,job,tender"]', __( 'Choose what a ticker/board/list shows: notices, open jobs and/or open tenders (default: notices plus the sources ticked in Settings).', 'medical-college-manager' ) ),
				array( '[mcm_social_bar]', __( 'Floating social-media icon bar (also settable site-wide from Settings).', 'medical-college-manager' ) ),
				array( '[mcm_blog]', __( 'Latest blog posts in a date-badge card layout.', 'medical-college-manager' ) ),
			),
			__( 'Generic & advanced', 'medical-college-manager' ) => array(
				array( '[mcm_grid type="mcm_event"]', __( 'Any content type as a grid of cards.', 'medical-college-manager' ) ),
				array( '[mcm_list type="mcm_course"]', __( 'Any content type as a stacked list.', 'medical-college-manager' ) ),
				array( '[mcm_table type="mcm_timetable"]', __( 'Any content type as a table.', 'medical-college-manager' ) ),
				array( '[mcm_single id="123"]', __( 'A single entry by its ID.', 'medical-college-manager' ) ),
			),
		);
	}

	/** Print the copy-friendly shortcode reference table. */
	private function render_shortcode_reference() {
		?>
		<h2><?php esc_html_e( 'Shortcodes — copy & paste anywhere', 'medical-college-manager' ); ?></h2>
		<p class="description" style="max-width:820px;">
			<?php esc_html_e( 'Click a shortcode to copy it, then paste it into any page, post, or an Elementor “Shortcode” widget. Every list accepts optional attributes:', 'medical-college-manager' ); ?>
			<code>count</code>, <code>columns</code>, <code>view="grid|list|table"</code>, <code>filters="no"</code>, <code>print="yes"</code>,
			<?php esc_html_e( 'and filter by a connector using its term slug —', 'medical-college-manager' ); ?>
			<code>department="anatomy"</code>, <code>program="mbbs"</code>, <code>professor="dr-sara-khan"</code>, <code>session="fall-2025"</code>, <code>specialty="cardiology"</code>, <code>research_area="oncology"</code>.
		</p>

		<?php foreach ( self::shortcode_reference() as $group => $items ) : ?>
			<h3 style="margin:22px 0 8px;"><?php echo esc_html( $group ); ?></h3>
			<table class="widefat striped mcm-sc-table" style="max-width:960px;">
				<tbody>
				<?php foreach ( $items as $row ) : ?>
					<tr>
						<td style="width:290px;white-space:nowrap;">
							<button type="button" class="button button-small mcm-copy-sc" data-copy="<?php echo esc_attr( $row[0] ); ?>" title="<?php esc_attr_e( 'Copy to clipboard', 'medical-college-manager' ); ?>">
								<span class="dashicons dashicons-clipboard" style="vertical-align:text-bottom;"></span>
								<code style="background:none;"><?php echo esc_html( $row[0] ); ?></code>
							</button>
						</td>
						<td style="vertical-align:middle;color:#50575e;"><?php echo esc_html( $row[1] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>

		<p class="description" style="margin-top:16px;max-width:820px;">
			<?php esc_html_e( 'Tip: taxonomy attribute values use the term “slug” (lowercase, hyphenated) — e.g. fall-2025, dr-sara-khan. On the front end, visitors filter with the dropdowns and search box automatically; slugs are only needed when you pre-filter inside a shortcode.', 'medical-college-manager' ); ?>
		</p>
		<?php
	}

	/**
	 * A colour-picker settings row.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param array  $s     Settings.
	 */
	private function color_row( $key, $label, $s ) {
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="text" class="mcm-color-field" id="%1$s" name="%4$s[%1$s]" value="%3$s" data-default-color="%3$s" /></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $s[ $key ] ),
			esc_attr( self::OPTION )
		);
	}

	/**
	 * A number settings row.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param array  $s     Settings.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 */
	private function number_row( $key, $label, $s, $min, $max ) {
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="number" min="%5$d" max="%6$d" id="%1$s" name="%4$s[%1$s]" value="%3$s" class="small-text" /></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $s[ $key ] ),
			esc_attr( self::OPTION ),
			(int) $min,
			(int) $max
		);
	}

	/**
	 * Checkbox list of content types that show a Print button. Generic on
	 * purpose: printing is not a timetable feature that other types borrow, it
	 * is a capability any type can be given from here — no code, no override.
	 *
	 * @param array $s Settings.
	 */
	private function print_types_row( $s ) {
		$current = isset( $s['print_types'] ) && is_array( $s['print_types'] ) ? $s['print_types'] : array();
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Printable Content Types', 'medical-college-manager' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Printable Content Types', 'medical-college-manager' ); ?></legend>
					<?php
					foreach ( MCM_CPT::post_types() as $cpt => $cfg ) {
						$obj   = get_post_type_object( $cpt );
						$label = $obj ? $obj->labels->name : $cpt;
						printf(
							'<label style="display:inline-block;min-width:170px;margin:0 8px 6px 0;"><input type="checkbox" name="%1$s[print_types][]" value="%2$s" %3$s /> %4$s</label>',
							esc_attr( self::OPTION ),
							esc_attr( $cpt ),
							checked( in_array( $cpt, $current, true ), true, false ),
							esc_html( $label )
						);
					}
					?>
					<p class="description">
						<?php esc_html_e( 'Adds a Print button to those listings. The printout keeps whatever filters and search the visitor applied, and prints only the list — not the surrounding page.', 'medical-college-manager' ); ?>
					</p>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * Branded placeholder shown for people with no photograph.
	 *
	 * @param array $s Settings.
	 */
	private function avatar_row( $s ) {
		$rows = array(
			'default_avatar_male'   => array(
				__( 'Default Photo — Male', 'medical-college-manager' ),
				__( 'Shown for consultants and team members whose Gender is Male and who have no photo.', 'medical-college-manager' ),
			),
			'default_avatar_female' => array(
				__( 'Default Photo — Female', 'medical-college-manager' ),
				__( 'Shown for consultants and team members whose Gender is Female and who have no photo.', 'medical-college-manager' ),
			),
			'default_avatar'        => array(
				__( 'Default Profile Image', 'medical-college-manager' ),
				__( 'Shown for anyone without a photo whose Gender is not set (or whose male/female photo above is empty). Upload a branded placeholder carrying the college logo; leave blank for a plain silhouette. Square images of at least 400 × 400 px work best.', 'medical-college-manager' ),
			),
		);
		foreach ( $rows as $key => $text ) {
			$val = isset( $s[ $key ] ) ? (string) $s[ $key ] : '';
			?>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $text[0] ); ?></label></th>
				<td>
					<input type="url" id="<?php echo esc_attr( $key ); ?>" class="regular-text mcm-media-field"
						name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>"
						value="<?php echo esc_attr( $val ); ?>" placeholder="https://…" />
					<button type="button" class="button mcm-media-pick" data-type="image" data-title="<?php echo esc_attr( $text[0] ); ?>"><?php esc_html_e( 'Select Image', 'medical-college-manager' ); ?></button>
					<button type="button" class="button-link mcm-media-clear"><?php esc_html_e( 'Clear', 'medical-college-manager' ); ?></button>
					<p class="description"><?php echo esc_html( $text[1] ); ?></p>
					<div class="mcm-media-preview">
						<?php if ( '' !== $val ) : ?>
							<img src="<?php echo esc_url( $val ); ?>" alt="" style="max-height:80px;margin-top:8px;" />
						<?php endif; ?>
					</div>
				</td>
			</tr>
			<?php
		}
	}

	/** Letterhead used on printed / PDF output. */
	/**
	 * One banner row (video URL + image picker) per public content type.
	 *
	 * @param array $s Settings.
	 */
	private function banner_rows( $s ) {
		$saved = isset( $s['archive_banners'] ) && is_array( $s['archive_banners'] ) ? $s['archive_banners'] : array();
		foreach ( MCM_CPT::post_types() as $type => $cfg ) {
			$obj = get_post_type_object( $type );
			if ( ! $obj || ! $obj->public ) {
				continue; // no public archive to banner (skips Students).
			}
			$video = isset( $saved[ $type ]['video'] ) ? $saved[ $type ]['video'] : '';
			$image = isset( $saved[ $type ]['image'] ) ? $saved[ $type ]['image'] : '';
			$base  = self::OPTION . '[archive_banners][' . $type . ']';
			?>
			<tr>
				<th scope="row"><?php echo esc_html( $obj->labels->name ); ?></th>
				<td>
					<input type="url" class="regular-text" name="<?php echo esc_attr( $base . '[video]' ); ?>"
						value="<?php echo esc_attr( $video ); ?>" placeholder="<?php esc_attr_e( 'Video URL (optional)', 'medical-college-manager' ); ?>" />
					<div style="margin-top:6px;">
						<input type="url" class="regular-text mcm-media-field" name="<?php echo esc_attr( $base . '[image]' ); ?>"
							value="<?php echo esc_attr( $image ); ?>" placeholder="<?php esc_attr_e( 'Or an image URL', 'medical-college-manager' ); ?>" />
						<button type="button" class="button mcm-media-pick"><?php esc_html_e( 'Select Image', 'medical-college-manager' ); ?></button>
						<button type="button" class="button-link mcm-media-clear"><?php esc_html_e( 'Clear', 'medical-college-manager' ); ?></button>
						<span class="mcm-media-preview">
						<?php
						if ( $image ) :
							?>
							<img src="<?php echo esc_url( $image ); ?>" alt="" style="max-height:44px;margin-top:6px;" /><?php endif; ?></span>
					</div>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Letterhead settings rows: logo, institution name, address, footer note and signature block.
	 *
	 * @param array $s Settings.
	 */
	private function letterhead_rows( $s ) {
		?>
		<tr>
			<th scope="row"><label for="print_logo"><?php esc_html_e( 'Letterhead Logo', 'medical-college-manager' ); ?></label></th>
			<td>
				<input type="url" id="print_logo" class="regular-text mcm-media-field"
					name="<?php echo esc_attr( self::OPTION ); ?>[print_logo]"
					value="<?php echo esc_attr( $s['print_logo'] ); ?>" placeholder="https://…" />
				<button type="button" class="button mcm-media-pick"><?php esc_html_e( 'Select Image', 'medical-college-manager' ); ?></button>
				<button type="button" class="button-link mcm-media-clear"><?php esc_html_e( 'Clear', 'medical-college-manager' ); ?></button>
				<p class="description"><?php esc_html_e( 'Shown top-left of every printed sheet. Leave blank to fall back to your theme logo, then the site icon. A wide transparent PNG works best.', 'medical-college-manager' ); ?></p>
				<div class="mcm-media-preview">
					<?php if ( ! empty( $s['print_logo'] ) ) : ?>
						<img src="<?php echo esc_url( $s['print_logo'] ); ?>" alt="" style="max-height:60px;margin-top:8px;" />
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="print_org"><?php esc_html_e( 'Institution Name', 'medical-college-manager' ); ?></label></th>
			<td>
				<input type="text" id="print_org" class="regular-text"
					name="<?php echo esc_attr( self::OPTION ); ?>[print_org]"
					value="<?php echo esc_attr( $s['print_org'] ); ?>"
					placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave blank to use the site title.', 'medical-college-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="print_address"><?php esc_html_e( 'Address / Contact Line', 'medical-college-manager' ); ?></label></th>
			<td>
				<input type="text" id="print_address" class="large-text"
					name="<?php echo esc_attr( self::OPTION ); ?>[print_address]"
					value="<?php echo esc_attr( $s['print_address'] ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. Dera Ismail Khan, KP, Pakistan · +92 966 000000 · info@example.edu.pk', 'medical-college-manager' ); ?>" />
				<p class="description"><?php esc_html_e( 'Printed under the institution name.', 'medical-college-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="print_footnote"><?php esc_html_e( 'Footer Note', 'medical-college-manager' ); ?></label></th>
			<td>
				<input type="text" id="print_footnote" class="large-text"
					name="<?php echo esc_attr( self::OPTION ); ?>[print_footnote]"
					value="<?php echo esc_attr( $s['print_footnote'] ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. This is a computer-generated document.', 'medical-college-manager' ); ?>" />
				<p class="description"><?php esc_html_e( 'Printed bottom-left. The generation date is added bottom-right automatically.', 'medical-college-manager' ); ?></p>
			</td>
		</tr>
		<?php
		$this->toggle_row( 'print_signature', __( 'Signature Block', 'medical-college-manager' ), $s );
	}

	/**
	 * How the featured image is handled on single pages.
	 *
	 * @param array $s Settings.
	 */
	private function single_image_row( $s ) {
		$mode    = isset( $s['single_image'] ) ? $s['single_image'] : 'auto';
		$options = array(
			'auto'   => __( 'Auto — show it only if the theme does not (recommended)', 'medical-college-manager' ),
			'always' => __( 'Always show it', 'medical-college-manager' ),
			'never'  => __( 'Never — leave it entirely to the theme', 'medical-college-manager' ),
		);
		?>
		<tr>
			<th scope="row"><label for="single_image"><?php esc_html_e( 'Featured Image on Single Pages', 'medical-college-manager' ); ?></label></th>
			<td>
				<select id="single_image" name="<?php echo esc_attr( self::OPTION ); ?>[single_image]">
					<?php foreach ( $options as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $mode, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'Many themes only show a featured image for blog posts, so an image set on a Consultant or Event can end up invisible. Auto fills that gap without ever showing the picture twice. Choose "Always" if your theme shows it below the content instead of above.', 'medical-college-manager' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * A checkbox settings row.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param array  $s     Settings.
	 */
	private function toggle_row( $key, $label, $s ) {
		printf(
			'<tr><th scope="row">%2$s</th><td><label><input type="checkbox" id="%1$s" name="%4$s[%1$s]" value="1" %3$s /> %5$s</label></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			checked( $s[ $key ], '1', false ),
			esc_attr( self::OPTION ),
			esc_html__( 'Enable', 'medical-college-manager' )
		);
	}
}
