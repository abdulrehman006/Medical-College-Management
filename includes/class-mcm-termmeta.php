<?php
/**
 * Term-level content for the shared connector taxonomies.
 *
 * A department is more than a filter label: the review asked each department
 * page to carry the Head of Department's welcome message, the HOD's photograph
 * and a short banner video of the department's activities. Terms had no fields
 * at all, so there was nowhere to put any of it.
 *
 * 1.32 adds the content of the fixed department page template: HOD
 * designation, introduction, vision, mission, services, address, office hours
 * and a map. The Departments → Edit screen shows the fields as collapsible
 * panels in the same order as the page, and the Departments list gets a
 * completeness column ("6/8 sections · Vision missing").
 *
 * The fields are added to the standard term add/edit screens, so they are
 * managed exactly where a department is managed — no separate settings page.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Department fields (hero, Head of Department, introduction, vision & mission,
 * services, contact, hidden sections) and the department shortcodes.
 */
class MCM_TermMeta {

	/** Most service rows one department can hold. */
	const MAX_SERVICES = 50;

	/** Minimum hero width (px) before a quality warning is shown. */
	const HERO_MIN_WIDTH = 1600;

	/** Minimum portrait width (px) before a quality warning is shown. */
	const PHOTO_MIN_WIDTH = 400;

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		// Everything here is deferred to init. The field definitions are
		// translated, and touching them at plugin-load time would load the text
		// domain before init — which WordPress 6.7+ reports as a doing_it_wrong
		// notice. The hooks below all fire well after init, so nothing is lost.
		add_action( 'init', array( $this, 'boot' ) );
	}

	/** Register the term hooks and meta. Runs on init. */
	public function boot() {
		foreach ( array_keys( self::taxonomies() ) as $tax ) {
			add_action( "{$tax}_add_form_fields", array( $this, 'add_fields' ) );
			add_action( "{$tax}_edit_form_fields", array( $this, 'edit_fields' ), 10, 2 );
			add_action( "created_{$tax}", array( $this, 'save' ) );
			add_action( "edited_{$tax}", array( $this, 'save' ) );
		}
		$this->register_meta();

		// Departments list: completeness column (the client's content checklist).
		add_filter( 'manage_edit-mcm_department_columns', array( $this, 'list_columns' ) );
		add_filter( 'manage_mcm_department_custom_column', array( $this, 'list_column_value' ), 10, 3 );

		// Shortcodes for composing a custom department template.
		add_shortcode( 'mcm_department_hero', array( $this, 'sc_hero' ) );
		add_shortcode( 'mcm_department_message', array( $this, 'sc_message' ) );
		add_shortcode( 'mcm_department_intro', array( $this, 'sc_intro' ) );
		add_shortcode( 'mcm_department_sections', array( $this, 'sc_sections' ) );
	}

	/**
	 * Panels on the Departments → Edit screen, in the order of the page template.
	 *
	 * @return array<string,string> Group key => heading.
	 */
	public static function groups() {
		return array(
			'hero'     => __( '1. Hero', 'medical-college-manager' ),
			'hod'      => __( '2. Message from the HOD', 'medical-college-manager' ),
			'intro'    => __( '3. Introduction', 'medical-college-manager' ),
			'vm'       => __( '4. Vision & Mission', 'medical-college-manager' ),
			'services' => __( '5. Services / Facilities', 'medical-college-manager' ),
			'contact'  => __( '8. Contact Information', 'medical-college-manager' ),
			'sections' => __( 'Page sections', 'medical-college-manager' ),
		);
	}

	/**
	 * Which taxonomies get term content, and which fields.
	 *
	 * Field keys: label, type, desc, group (panel), and for media: size (the
	 * image size stored: full | large | medium) and minw (quality warning
	 * below this width). Existing meta keys are unchanged; 1.32 only adds keys.
	 *
	 * @return array
	 */
	public static function taxonomies() {
		return apply_filters(
			'mcm_term_fields',
			array(
				'mcm_department' => array(
					'mcm_term_video'           => array(
						'label' => __( 'Hero Video URL', 'medical-college-manager' ),
						'type'  => 'url',
						'group' => 'hero',
						'desc'  => __( 'YouTube/Vimeo link or an uploaded MP4 for the banner at the top of the page. Takes priority over the hero image. Note: some videos have embedding turned off by their owner and will show "Video unavailable" on any website — if that happens, use a different (embeddable) video or upload an MP4.', 'medical-college-manager' ),
					),
					'mcm_term_banner'          => array(
						'label' => __( 'Hero Image', 'medical-college-manager' ),
						'type'  => 'media',
						'group' => 'hero',
						'size'  => 'full',
						'minw'  => self::HERO_MIN_WIDTH,
						'desc'  => __( 'Static banner shown at the top when no hero video is set. Use a wide (16:9) image at least 1600 px wide.', 'medical-college-manager' ),
					),
					'mcm_term_hod'             => array(
						'label' => __( 'Head of Department', 'medical-college-manager' ),
						'type'  => 'text',
						'group' => 'hod',
						'desc'  => __( 'Full name shown with the message.', 'medical-college-manager' ),
					),
					'mcm_term_hod_designation' => array(
						'label' => __( 'HOD Designation', 'medical-college-manager' ),
						'type'  => 'text',
						'group' => 'hod',
						'desc'  => __( 'e.g. Professor & Head of Department', 'medical-college-manager' ),
					),
					'mcm_term_hod_photo'       => array(
						'label' => __( 'HOD Photograph', 'medical-college-manager' ),
						'type'  => 'media',
						'group' => 'hod',
						'size'  => 'large',
						'minw'  => self::PHOTO_MIN_WIDTH,
						'desc'  => __( 'Shown beside the message. A portrait at least 400 px wide.', 'medical-college-manager' ),
					),
					'mcm_term_message'         => array(
						'label' => __( "Head of Department's Message", 'medical-college-manager' ),
						'type'  => 'textarea',
						'group' => 'hod',
						'desc'  => __( 'The welcome message for this department page.', 'medical-college-manager' ),
					),
					'mcm_term_intro'           => array(
						'label' => __( 'Introduction', 'medical-college-manager' ),
						'type'  => 'richtext',
						'group' => 'intro',
						'desc'  => __( 'A short introduction to the department: what it teaches and does.', 'medical-college-manager' ),
					),
					'mcm_term_vision'          => array(
						'label'    => __( 'Vision', 'medical-college-manager' ),
						'type'     => 'textarea',
						'group'    => 'vm',
						'required' => true,
						'desc'     => __( 'Required. The Vision & Mission section is shown only when both are filled in.', 'medical-college-manager' ),
					),
					'mcm_term_mission'         => array(
						'label'    => __( 'Mission', 'medical-college-manager' ),
						'type'     => 'textarea',
						'group'    => 'vm',
						'required' => true,
						'desc'     => __( 'Required.', 'medical-college-manager' ),
					),
					'mcm_term_services'        => array(
						'label' => __( 'Services / Facilities', 'medical-college-manager' ),
						'type'  => 'services',
						'group' => 'services',
						'desc'  => __( 'One row per service or facility. Dates are optional (e.g. a workshop held 12/03/2026 – 14/03/2026). Rows without a title are ignored.', 'medical-college-manager' ),
					),
					'mcm_term_email'           => array(
						'label' => __( 'Departmental Email', 'medical-college-manager' ),
						'type'  => 'email',
						'group' => 'contact',
						'desc'  => __( 'e.g. anatomy@gmcdik.edu.pk', 'medical-college-manager' ),
					),
					'mcm_term_phone'           => array(
						'label' => __( 'Departmental Phone', 'medical-college-manager' ),
						'type'  => 'text',
						'group' => 'contact',
						'desc'  => '',
					),
					'mcm_term_address'         => array(
						'label' => __( 'Address', 'medical-college-manager' ),
						'type'  => 'textarea',
						'group' => 'contact',
						'desc'  => __( 'Where the department is (building, floor, campus).', 'medical-college-manager' ),
					),
					'mcm_term_hours'           => array(
						'label' => __( 'Office Hours', 'medical-college-manager' ),
						'type'  => 'text',
						'group' => 'contact',
						'desc'  => __( 'e.g. Monday–Friday, 8:00 AM – 2:00 PM', 'medical-college-manager' ),
					),
					'mcm_term_map'             => array(
						'label' => __( 'Map (Google Maps embed)', 'medical-college-manager' ),
						'type'  => 'mapurl',
						'group' => 'contact',
						'desc'  => __( 'In Google Maps: Share → Embed a map → Copy HTML, then paste it here (the link is taken from it). Only Google Maps embed links are accepted.', 'medical-college-manager' ),
					),
					'mcm_term_hidden'          => array(
						'label' => __( 'Hide Sections', 'medical-college-manager' ),
						'type'  => 'sections',
						'group' => 'sections',
						'desc'  => __( 'Tick any sections you do NOT want on this department page (e.g. hide the timetable). Unticked sections appear automatically whenever they have content.', 'medical-college-manager' ),
					),
				),
			)
		);
	}

	/**
	 * The public content types that can appear as sections on a department page,
	 * as [ post_type => plural label ]. Used to render the "Hide Sections"
	 * checklist and to validate what an admin chose to hide.
	 *
	 * @return array<string,string>
	 */
	public static function section_types() {
		$taxonomies = MCM_CPT::taxonomies();
		$uses       = isset( $taxonomies['mcm_department']['post_types'] ) ? $taxonomies['mcm_department']['post_types'] : array();
		$out        = array();
		foreach ( $uses as $cpt ) {
			if ( ! is_post_type_viewable( $cpt ) ) {
				continue; // private types (students) never appear as sections.
			}
			$obj = get_post_type_object( $cpt );
			if ( $obj ) {
				$out[ $cpt ] = $obj->labels->name;
			}
		}
		return $out;
	}

	/**
	 * The set of section post types an admin has hidden for a department, as a
	 * lookup [ post_type => true ]. Empty when nothing is hidden.
	 *
	 * @param int $term_id Term ID.
	 * @return array<string,bool>
	 */
	public static function hidden_sections( $term_id ) {
		$raw    = (string) MCM_Safe::term_meta( $term_id, 'mcm_term_hidden' );
		$hidden = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) as $cpt ) {
			$hidden[ $cpt ] = true;
		}
		return $hidden;
	}

	/**
	 * Fields for one taxonomy, or an empty array.
	 *
	 * @param string $tax Taxonomy name.
	 */
	public static function fields_for( $tax ) {
		$all = self::taxonomies();
		return isset( $all[ $tax ] ) ? $all[ $tax ] : array();
	}

	/**
	 * Fields grouped into the edit-screen panels, in panel order. Fields
	 * without a known group (e.g. added through the mcm_term_fields filter)
	 * come last under "More".
	 *
	 * @param string $tax Taxonomy name.
	 * @return array<string,array{title:string,fields:array}>
	 */
	private static function grouped_fields( $tax ) {
		$groups = self::groups();
		$out    = array();
		foreach ( $groups as $g => $title ) {
			$out[ $g ] = array(
				'title'  => $title,
				'fields' => array(),
			);
		}
		$out['more'] = array(
			'title'  => __( 'More', 'medical-college-manager' ),
			'fields' => array(),
		);
		foreach ( self::fields_for( $tax ) as $key => $field ) {
			$g                           = ( isset( $field['group'] ) && isset( $groups[ $field['group'] ] ) ) ? $field['group'] : 'more';
			$out[ $g ]['fields'][ $key ] = $field;
		}
		return array_filter(
			$out,
			function ( $g ) {
				return ! empty( $g['fields'] );
			}
		);
	}

	/** Register the term meta so it is typed, sanitized and REST-aware. */
	public function register_meta() {
		foreach ( self::taxonomies() as $tax => $fields ) {
			foreach ( $fields as $key => $field ) {
				$type = $field['type'];
				register_term_meta(
					$tax,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => function ( $value ) use ( $type ) {
							return MCM_TermMeta::sanitize_field( $type, $value );
						},
						'auth_callback'     => function () {
							return current_user_can( 'manage_categories' );
						},
					)
				);
			}
		}
	}

	/**
	 * One sanitizer for every write path (edit screen, REST, other code).
	 *
	 * @param string $type  Field type.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	public static function sanitize_field( $type, $value ) {
		switch ( $type ) {
			case 'sections':
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
			case 'richtext':
				// Script/style blocks go whole (kses would keep their text content).
				return is_scalar( $value ) ? trim( wp_kses_post( (string) preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', (string) $value ) ) ) : '';
			case 'services':
				return self::sanitize_services( $value );
			case 'mapurl':
				return self::sanitize_map( $value );
			default:
				return MCM_Meta::sanitize_value( $type, $value );
		}
	}

	/**
	 * Services repeater → JSON. Accepts the posted rows (array) or a JSON
	 * string (REST / re-saving the stored value). Every row field is sanitized;
	 * rows without a title are dropped; dates go through MCM_Meta::parse_date()
	 * and an unreadable date is cleared; at most MAX_SERVICES rows.
	 *
	 * @param mixed $value Rows.
	 * @return string JSON array, or '' when no row is left.
	 */
	public static function sanitize_services( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $value ) ) {
			return '';
		}
		$rows = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$title = isset( $row['title'] ) ? sanitize_text_field( MCM_Safe::str( $row['title'] ) ) : '';
			if ( '' === $title ) {
				continue;
			}
			$from   = isset( $row['date_from'] ) ? MCM_Meta::parse_date( MCM_Safe::str( $row['date_from'] ) ) : false;
			$to     = isset( $row['date_to'] ) ? MCM_Meta::parse_date( MCM_Safe::str( $row['date_to'] ) ) : false;
			$rows[] = array(
				'title'       => $title,
				'description' => isset( $row['description'] ) ? sanitize_textarea_field( MCM_Safe::str( $row['description'] ) ) : '',
				'date_from'   => false !== $from ? $from : '',
				'date_to'     => false !== $to ? $to : '',
				'image'       => isset( $row['image'] ) ? esc_url_raw( MCM_Safe::str( $row['image'] ) ) : '',
			);
			if ( count( $rows ) >= self::MAX_SERVICES ) {
				break;
			}
		}
		return $rows ? (string) wp_json_encode( $rows ) : '';
	}

	/**
	 * The stored service rows of a department (always a clean list).
	 *
	 * @param int $term_id Term ID.
	 * @return array[]
	 */
	public static function services( $term_id ) {
		$json = self::sanitize_services( (string) MCM_Safe::term_meta( $term_id, 'mcm_term_services' ) );
		$rows = '' === $json ? array() : json_decode( $json, true );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * A Google Maps embed link, or ''. Accepts the bare link or the whole
	 * <iframe> snippet Google's "Embed a map" gives (the src is extracted).
	 * Only https://www.google.com/maps/embed… (or maps.google.com/…output=embed)
	 * is allowed, so no other site can be framed on the department page.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function sanitize_map( $value ) {
		$raw = trim( MCM_Safe::str( $value ) );
		if ( '' === $raw ) {
			return '';
		}
		if ( preg_match( '/src\s*=\s*["\']([^"\']+)["\']/i', $raw, $m ) ) {
			$raw = $m[1];
		}
		$url   = html_entity_decode( $raw, ENT_QUOTES );
		$parts = wp_parse_url( $url );
		if ( ! $parts || empty( $parts['host'] ) || 'https' !== strtolower( isset( $parts['scheme'] ) ? $parts['scheme'] : '' ) ) {
			return '';
		}
		$host = strtolower( $parts['host'] );
		$path = isset( $parts['path'] ) ? $parts['path'] : '';
		$ok   = ( in_array( $host, array( 'www.google.com', 'google.com' ), true ) && 0 === strpos( $path, '/maps/embed' ) )
			|| ( 'maps.google.com' === $host && isset( $parts['query'] ) && false !== strpos( $parts['query'], 'output=embed' ) );
		return $ok ? esc_url_raw( $url ) : '';
	}

	/**
	 * Original width (px) of a Media Library image given any of its URLs
	 * (full or a resized copy), or 0 when it is not in the Media Library.
	 *
	 * @param string $url Image URL.
	 * @return int
	 */
	public static function image_width( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return 0;
		}
		$id = (int) attachment_url_to_postid( $url );
		if ( ! $id ) {
			// A resized copy (photo-300x200.jpg) is not matched; try the original.
			$id = (int) attachment_url_to_postid( preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $url ) );
		}
		if ( ! $id ) {
			return 0;
		}
		$meta = wp_get_attachment_metadata( $id );
		return isset( $meta['width'] ) ? (int) $meta['width'] : 0;
	}

	/**
	 * The "add new term" screen has no table wrapper.
	 *
	 * @param string $taxonomy Taxonomy name.
	 */
	public function add_fields( $taxonomy ) {
		$groups = self::grouped_fields( $taxonomy );
		if ( ! $groups ) {
			return;
		}
		wp_nonce_field( 'mcm_term_save', 'mcm_term_nonce' );
		foreach ( $groups as $group ) {
			echo '<details class="mcm-panel" open><summary class="mcm-panel-title">' . esc_html( $group['title'] ) . '</summary><div class="mcm-panel-body">';
			foreach ( $group['fields'] as $key => $field ) {
				echo '<div class="form-field">';
				printf( '<label for="%s">%s</label>', esc_attr( $key ), esc_html( $field['label'] ) );
				self::control( $key, '', $field, false );
				if ( ! empty( $field['desc'] ) ) {
					echo '<p>' . esc_html( $field['desc'] ) . '</p>';
				}
				echo '</div>';
			}
			echo '</div></details>';
		}
	}

	/**
	 * The "edit term" screen is a table: one full-width row per panel, each a
	 * native <details> (keyboard and screen-reader friendly, no script).
	 *
	 * @param WP_Term $term Term object.
	 * @param string  $taxonomy Taxonomy name.
	 */
	public function edit_fields( $term, $taxonomy ) {
		$groups = self::grouped_fields( $taxonomy );
		if ( ! $groups ) {
			return;
		}
		wp_nonce_field( 'mcm_term_save', 'mcm_term_nonce' );

		$notice = get_transient( 'mcm_term_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'mcm_term_notice_' . get_current_user_id() );
			echo '<tr><td colspan="2"><div class="notice notice-warning inline"><p>' . esc_html( $notice ) . '</p></div></td></tr>';
		}
		if ( 'mcm_department' === $taxonomy ) {
			$missing = self::missing_required( $term->term_id );
			if ( $missing ) {
				echo '<tr><td colspan="2"><div class="notice notice-warning inline mcm-vm-warning"><p>' . esc_html(
					sprintf(
						/* translators: %s: list of missing fields, e.g. "Vision, Mission". */
						__( 'Missing: %s. The department page does not show a Vision & Mission section until both are filled in.', 'medical-college-manager' ),
						implode( ', ', $missing )
					)
				) . '</p></div></td></tr>';
			}
		}

		foreach ( $groups as $g => $group ) {
			echo '<tr class="mcm-panel-row"><td colspan="2">';
			printf( '<details class="mcm-panel" %s><summary class="mcm-panel-title">%s</summary>', 'sections' === $g ? '' : 'open', esc_html( $group['title'] ) );
			echo '<table class="form-table mcm-panel-table" role="presentation">';
			foreach ( $group['fields'] as $key => $field ) {
				$value = MCM_Safe::term_meta( $term->term_id, $key );
				echo '<tr class="form-field">';
				printf( '<th scope="row"><label for="%s">%s</label></th><td>', esc_attr( $key ), esc_html( $field['label'] ) );
				self::control( $key, $value, $field, true );
				if ( ! empty( $field['desc'] ) ) {
					echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
				}
				self::quality_warning( $value, $field );
				echo '</td></tr>';
			}
			echo '</table></details></td></tr>';
		}
	}

	/**
	 * Warn (never block) when a saved image is smaller than the field needs.
	 *
	 * @param mixed $value Stored URL.
	 * @param array $field Field definition.
	 */
	private static function quality_warning( $value, $field ) {
		if ( 'media' !== $field['type'] || empty( $field['minw'] ) || '' === (string) $value ) {
			return;
		}
		$w = self::image_width( (string) $value );
		if ( $w && $w < (int) $field['minw'] ) {
			echo '<p class="mcm-quality-warning">' . esc_html(
				sprintf(
					/* translators: 1: image width in px, 2: recommended minimum width in px. */
					__( 'This image is %1$d px wide; at least %2$d px is recommended, or it may look blurry.', 'medical-college-manager' ),
					$w,
					(int) $field['minw']
				)
			) . '</p>';
		}
	}

	/**
	 * Render one input.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param array  $field Field definition.
	 * @param bool   $edit  True on the edit screen (rich editor available).
	 */
	private static function control( $key, $value, $field, $edit = true ) {
		switch ( $field['type'] ) {
			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%1$s" rows="5" style="width:95%%;"%3$s>%2$s</textarea>',
					esc_attr( $key ),
					esc_textarea( $value ),
					! empty( $field['required'] ) ? ' aria-describedby="' . esc_attr( $key ) . '-req"' : ''
				);
				if ( ! empty( $field['required'] ) ) {
					printf( '<span id="%s-req" class="screen-reader-text">%s</span>', esc_attr( $key ), esc_html__( 'Required for the Vision & Mission section.', 'medical-college-manager' ) );
				}
				break;

			case 'richtext':
				// The add-term form is submitted by AJAX and cannot re-initialise
				// an editor, so it gets a plain textarea (HTML allowed).
				if ( $edit && function_exists( 'wp_editor' ) ) {
					wp_editor(
						(string) $value,
						$key,
						array(
							'textarea_name' => $key,
							'textarea_rows' => 8,
							'media_buttons' => false,
							'teeny'         => true,
						)
					);
				} else {
					printf( '<textarea id="%1$s" name="%1$s" rows="6" style="width:95%%;">%2$s</textarea>', esc_attr( $key ), esc_textarea( $value ) );
				}
				break;

			case 'services':
				self::services_control( $key, (string) $value );
				break;

			case 'mapurl':
				printf(
					'<textarea id="%1$s" name="%1$s" rows="2" style="width:95%%;" placeholder="https://www.google.com/maps/embed?pb=…">%2$s</textarea>',
					esc_attr( $key ),
					esc_textarea( $value )
				);
				break;

			case 'media':
				printf(
					'<input type="url" id="%1$s" name="%1$s" value="%2$s" class="mcm-media-field" style="width:60%%;" placeholder="https://…" /> ',
					esc_attr( $key ),
					esc_attr( $value )
				);
				printf(
					'<button type="button" class="button mcm-media-pick" data-type="%1$s" data-size="%2$s" data-minw="%3$d">%4$s</button> ',
					esc_attr( ! empty( $field['accept'] ) ? $field['accept'] : 'image' ),
					esc_attr( ! empty( $field['size'] ) ? $field['size'] : 'medium' ),
					! empty( $field['minw'] ) ? (int) $field['minw'] : 0,
					esc_html__( 'Select Image', 'medical-college-manager' )
				);
				echo '<button type="button" class="button-link mcm-media-clear">' . esc_html__( 'Clear', 'medical-college-manager' ) . '</button>';
				echo '<span class="mcm-media-preview">';
				if ( '' !== (string) $value ) {
					echo '<img src="' . esc_url( (string) $value ) . '" alt="" style="max-height:60px;margin-top:8px;display:block;" />';
				}
				echo '</span>';
				break;

			case 'sections':
				$hidden = array();
				foreach ( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) as $cpt ) {
					$hidden[ $cpt ] = true;
				}
				$types = self::section_types();
				if ( ! $types ) {
					esc_html_e( 'No sections available.', 'medical-college-manager' );
					break;
				}
				echo '<fieldset>';
				foreach ( $types as $cpt => $label ) {
					printf(
						'<label style="display:inline-block;margin:0 16px 6px 0;"><input type="checkbox" name="%1$s[]" value="%2$s"%3$s /> %4$s</label>',
						esc_attr( $key ),
						esc_attr( $cpt ),
						checked( isset( $hidden[ $cpt ] ), true, false ),
						esc_html( $label )
					);
				}
				echo '</fieldset>';
				break;

			default:
				printf(
					'<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" style="width:95%%;" />',
					esc_attr( 'email' === $field['type'] ? 'email' : ( 'url' === $field['type'] ? 'url' : 'text' ) ),
					esc_attr( $key ),
					esc_attr( $value )
				);
				break;
		}
	}

	/**
	 * Services repeater. Works without JavaScript (the existing rows plus one
	 * empty row are always rendered); with it, rows can be added, removed and
	 * moved up/down (buttons, so it is keyboard accessible). The script renames
	 * the inputs after every change so the saved order is the order shown.
	 *
	 * @param string $key   Field key.
	 * @param string $value Stored JSON.
	 */
	private static function services_control( $key, $value ) {
		$rows = self::sanitize_services( $value );
		$rows = '' === $rows ? array() : json_decode( $rows, true );
		$rows = is_array( $rows ) ? $rows : array();

		echo '<div class="mcm-repeater" data-key="' . esc_attr( $key ) . '">';
		echo '<ol class="mcm-rep-rows">';
		foreach ( $rows as $i => $row ) {
			self::service_row( $key, (string) $i, $row );
		}
		self::service_row( $key, (string) count( $rows ), array() ); // One empty row to add to.
		echo '</ol>';
		echo '<template class="mcm-rep-template">';
		self::service_row( $key, '__i__', array() );
		echo '</template>';
		echo '<button type="button" class="button mcm-rep-add">' . esc_html__( '+ Add a service', 'medical-college-manager' ) . '</button>';
		echo '</div>';
	}

	/**
	 * One repeater row.
	 *
	 * @param string $key Field key.
	 * @param string $i   Row index (or the __i__ template placeholder).
	 * @param array  $row Row values.
	 */
	private static function service_row( $key, $i, $row ) {
		$v    = function ( $k ) use ( $row ) {
			return isset( $row[ $k ] ) ? (string) $row[ $k ] : '';
		};
		$name = function ( $k ) use ( $key, $i ) {
			return $key . '[' . $i . '][' . $k . ']';
		};
		$id   = function ( $k ) use ( $key, $i ) {
			return $key . '-' . $i . '-' . $k;
		};
		echo '<li class="mcm-rep-row"><fieldset>';
		echo '<legend class="mcm-rep-legend">' . esc_html__( 'Service', 'medical-college-manager' ) . '</legend>';
		printf( '<p><label for="%1$s">%2$s</label><input type="text" id="%1$s" name="%3$s" value="%4$s" class="widefat" /></p>', esc_attr( $id( 'title' ) ), esc_html__( 'Title', 'medical-college-manager' ), esc_attr( $name( 'title' ) ), esc_attr( $v( 'title' ) ) );
		printf( '<p><label for="%1$s">%2$s</label><textarea id="%1$s" name="%3$s" rows="2" class="widefat">%4$s</textarea></p>', esc_attr( $id( 'description' ) ), esc_html__( 'Description', 'medical-college-manager' ), esc_attr( $name( 'description' ) ), esc_textarea( $v( 'description' ) ) );
		echo '<p class="mcm-rep-dates">';
		printf( '<label for="%1$s">%2$s</label><input type="text" id="%1$s" name="%3$s" value="%4$s" placeholder="15/10/2026" size="12" /> ', esc_attr( $id( 'date_from' ) ), esc_html__( 'Date (optional)', 'medical-college-manager' ), esc_attr( $name( 'date_from' ) ), esc_attr( self::dmy( $v( 'date_from' ) ) ) );
		printf( '<label for="%1$s">%2$s</label><input type="text" id="%1$s" name="%3$s" value="%4$s" placeholder="17/10/2026" size="12" />', esc_attr( $id( 'date_to' ) ), esc_html__( 'to (optional)', 'medical-college-manager' ), esc_attr( $name( 'date_to' ) ), esc_attr( self::dmy( $v( 'date_to' ) ) ) );
		echo '</p><p class="mcm-rep-image">';
		printf( '<label for="%1$s">%2$s</label><input type="url" id="%1$s" name="%3$s" value="%4$s" class="mcm-media-field" style="width:60%%;" placeholder="https://…" /> ', esc_attr( $id( 'image' ) ), esc_html__( 'Icon or image (optional)', 'medical-college-manager' ), esc_attr( $name( 'image' ) ), esc_attr( $v( 'image' ) ) );
		echo '<button type="button" class="button mcm-media-pick" data-type="image" data-size="medium">' . esc_html__( 'Select Image', 'medical-college-manager' ) . '</button> ';
		echo '<button type="button" class="button-link mcm-media-clear">' . esc_html__( 'Clear', 'medical-college-manager' ) . '</button><span class="mcm-media-preview"></span></p>';
		echo '<p class="mcm-rep-actions">';
		echo '<button type="button" class="button mcm-rep-up">' . esc_html__( 'Move up', 'medical-college-manager' ) . '</button> ';
		echo '<button type="button" class="button mcm-rep-down">' . esc_html__( 'Move down', 'medical-college-manager' ) . '</button> ';
		echo '<button type="button" class="button-link button-link-delete mcm-rep-remove">' . esc_html__( 'Remove', 'medical-college-manager' ) . '</button>';
		echo '</p></fieldset></li>';
	}

	/**
	 * A stored Y-m-d date shown as d/m/Y in an input (the format staff type and
	 * the one MCM_Meta::parse_date() reads back), or ''.
	 *
	 * @param string $ymd Stored date.
	 * @return string
	 */
	private static function dmy( $ymd ) {
		$d = '' !== $ymd ? DateTime::createFromFormat( 'Y-m-d', $ymd ) : false;
		return $d ? $d->format( 'd/m/Y' ) : '';
	}

	/**
	 * Persist the fields.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save( $term_id ) {
		if ( ! isset( $_POST['mcm_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcm_term_nonce'] ) ), 'mcm_term_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$notes = array();
		foreach ( self::fields_for( $term->taxonomy ) as $key => $field ) {
			// A checklist posts an array of ticked values (nothing at all when the
			// admin unticks every box), so it is always processed — never skipped
			// on "unset" — otherwise a cleared list could never be saved.
			if ( 'sections' === $field['type'] ) {
				$valid   = self::section_types();
				$checked = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$keep    = array();
				foreach ( $checked as $cpt ) {
					$cpt = sanitize_key( $cpt );
					if ( isset( $valid[ $cpt ] ) ) {
						$keep[] = $cpt;
					}
				}
				if ( $keep ) {
					update_term_meta( $term_id, $key, implode( ',', $keep ) );
				} else {
					delete_term_meta( $term_id, $key );
				}
				continue;
			}

			if ( ! isset( $_POST[ $key ] ) ) {
				// The repeater posts nothing once every row is removed (only the
				// empty template row remains), so a missing key clears it.
				if ( 'services' === $field['type'] ) {
					delete_term_meta( $term_id, $key );
				}
				continue;
			}
			$raw   = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitized by sanitize_field() per type.
			$clean = self::sanitize_field( $field['type'], $raw );

			if ( 'mapurl' === $field['type'] && '' === $clean && '' !== trim( MCM_Safe::str( $raw ) ) ) {
				$notes[] = __( 'The map was not saved: only Google Maps embed links are accepted (Share → Embed a map).', 'medical-college-manager' );
			}
			if ( 'services' === $field['type'] && is_array( $raw ) ) {
				foreach ( $raw as $row ) {
					foreach ( array( 'date_from', 'date_to' ) as $dk ) {
						$d = is_array( $row ) && isset( $row[ $dk ] ) ? trim( MCM_Safe::str( $row[ $dk ] ) ) : '';
						if ( '' !== $d && false === MCM_Meta::parse_date( $d ) ) {
							/* translators: %s: the date as typed. */
							$notes[] = sprintf( __( 'A service date "%s" could not be read and was left empty (use 15/10/2026 or 2026-10-15).', 'medical-college-manager' ), $d );
						}
					}
				}
			}

			if ( '' === $clean ) {
				delete_term_meta( $term_id, $key );
			} else {
				update_term_meta( $term_id, $key, $clean );
			}
		}
		if ( $notes ) {
			set_transient( 'mcm_term_notice_' . get_current_user_id(), implode( ' ', array_unique( $notes ) ), 5 * MINUTE_IN_SECONDS );
		}
	}

	/*
	 * ----------------------------------------------------------------
	 * Completeness (Departments list)
	 * ----------------------------------------------------------------
	 */

	/**
	 * Mandatory department fields that are empty, as labels.
	 *
	 * @param int $term_id Term ID.
	 * @return string[]
	 */
	public static function missing_required( $term_id ) {
		$out = array();
		foreach ( self::fields_for( 'mcm_department' ) as $key => $field ) {
			if ( ! empty( $field['required'] ) && '' === trim( (string) MCM_Safe::term_meta( $term_id, $key ) ) ) {
				$out[] = $field['label'];
			}
		}
		return $out;
	}

	/**
	 * Which of the 8 template sections a department can show, keyed by
	 * section. Faculty and Publications count when tagged content exists.
	 *
	 * @param WP_Term $term Department.
	 * @return array<string,bool>
	 */
	public static function completeness( $term ) {
		$id  = (int) $term->term_id;
		$has = function ( $key ) use ( $id ) {
			return '' !== trim( (string) MCM_Safe::term_meta( $id, $key ) );
		};
		$any = function ( $types ) use ( $id ) {
			$q = get_posts(
				array(
					'post_type'        => $types,
					'post_status'      => 'publish',
					'numberposts'      => 1,
					'fields'           => 'ids',
					'suppress_filters' => false,
					'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One-row existence check.
						array(
							'taxonomy' => 'mcm_department',
							'terms'    => $id,
						),
					),
				)
			);
			return ! empty( $q );
		};
		return array(
			'hero'         => $has( 'mcm_term_banner' ) || $has( 'mcm_term_video' ),
			'hod'          => $has( 'mcm_term_message' ) || $has( 'mcm_term_hod' ),
			'intro'        => $has( 'mcm_term_intro' ),
			'vm'           => $has( 'mcm_term_vision' ) && $has( 'mcm_term_mission' ),
			'services'     => (bool) self::services( $id ),
			'faculty'      => $any( array( 'mcm_team', 'mcm_consultant' ) ),
			'publications' => $any( array( 'mcm_publication' ) ),
			'contact'      => $has( 'mcm_term_phone' ) || $has( 'mcm_term_email' ) || $has( 'mcm_term_address' ) || $has( 'mcm_term_hours' ) || $has( 'mcm_term_map' ),
		);
	}

	/**
	 * Add the "Page" column to the Departments list.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function list_columns( $columns ) {
		$out = array();
		foreach ( $columns as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'name' === $k ) {
				$out['mcm_page'] = __( 'Page', 'medical-college-manager' );
			}
		}
		if ( ! isset( $out['mcm_page'] ) ) {
			$out['mcm_page'] = __( 'Page', 'medical-college-manager' );
		}
		return $out;
	}

	/**
	 * "6/8 sections" plus the missing mandatory fields, e.g. "Vision missing".
	 *
	 * @param string $content Existing content.
	 * @param string $column  Column key.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public function list_column_value( $content, $column, $term_id ) {
		if ( 'mcm_page' !== $column ) {
			return $content;
		}
		$term = get_term( (int) $term_id, 'mcm_department' );
		if ( ! $term instanceof WP_Term ) {
			return $content;
		}
		$c     = self::completeness( $term );
		$count = count( array_filter( $c ) );
		$html  = '<span class="mcm-completeness">' . esc_html(
			sprintf(
				/* translators: 1: sections that can be shown, 2: total sections. */
				__( '%1$d/%2$d sections', 'medical-college-manager' ),
				$count,
				count( $c )
			)
		) . '</span>';
		foreach ( self::missing_required( (int) $term_id ) as $label ) {
			$html .= '<br /><span class="mcm-missing">' . esc_html(
				sprintf(
					/* translators: %s: field name, e.g. "Vision". */
					__( '%s missing', 'medical-college-manager' ),
					$label
				)
			) . '</span>';
		}
		return $html;
	}

	/*
	 * ----------------------------------------------------------------
	 * Front end (existing department page parts)
	 * ----------------------------------------------------------------
	 */

	/**
	 * Resolve which Department term a shortcode targets: the `department` (or
	 * `dept`) attribute slug, else the current queried term when placed on a
	 * department archive / Elementor department template.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return WP_Term|null
	 */
	public static function resolve_term( $atts = array() ) {
		$slug = '';
		if ( isset( $atts['department'] ) && '' !== $atts['department'] ) {
			$slug = sanitize_title( $atts['department'] );
		} elseif ( isset( $atts['dept'] ) && '' !== $atts['dept'] ) {
			$slug = sanitize_title( $atts['dept'] );
		}
		if ( $slug ) {
			$t = get_term_by( 'slug', $slug, 'mcm_department' );
			return ( $t && ! is_wp_error( $t ) ) ? $t : null;
		}
		$obj = get_queried_object();
		return ( $obj instanceof WP_Term && 'mcm_department' === $obj->taxonomy ) ? $obj : null;
	}

	/**
	 * The hero banner: the video if one is set (safe providers only), otherwise
	 * the static hero image. Empty string if neither is set.
	 *
	 * @param WP_Term $term Department term.
	 * @return string
	 */
	public static function hero_html( $term ) {
		if ( ! $term instanceof WP_Term ) {
			return '';
		}
		// Shared hero renderer — same banner markup as events and archive pages.
		return MCM_Query::hero_banner(
			MCM_Safe::term_meta( $term->term_id, 'mcm_term_video' ),
			MCM_Safe::term_meta( $term->term_id, 'mcm_term_banner' ),
			$term->name
		);
	}

	/**
	 * The Head-of-Department message card: photo, name, role, message, contact.
	 * Empty string when nothing is filled in.
	 *
	 * @param WP_Term $term Department term.
	 * @return string
	 */
	public static function message_html( $term ) {
		if ( ! $term instanceof WP_Term ) {
			return '';
		}
		$hod     = trim( (string) MCM_Safe::term_meta( $term->term_id, 'mcm_term_hod' ) );
		$role    = trim( (string) MCM_Safe::term_meta( $term->term_id, 'mcm_term_hod_designation' ) );
		$photo   = trim( (string) MCM_Safe::term_meta( $term->term_id, 'mcm_term_hod_photo' ) );
		$message = trim( (string) MCM_Safe::term_meta( $term->term_id, 'mcm_term_message' ) );
		$email   = trim( (string) MCM_Safe::term_meta( $term->term_id, 'mcm_term_email' ) );
		$phone   = trim( (string) MCM_Safe::term_meta( $term->term_id, 'mcm_term_phone' ) );

		if ( '' === $hod && '' === $message && '' === $email && '' === $phone ) {
			return '';
		}

		ob_start();
		?>
		<div class="mcm-hod mcm-wrap">
			<?php if ( '' !== $photo ) : ?>
				<div class="mcm-hod-photo"><img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $hod ); ?>" loading="lazy" /></div>
			<?php endif; ?>
			<div class="mcm-hod-body">
				<span class="mcm-hod-eyebrow"><?php echo esc_html( '' !== $role ? $role : __( 'Head of Department', 'medical-college-manager' ) ); ?></span>
				<?php if ( '' !== $hod ) : ?>
					<h2 class="mcm-hod-name"><?php echo esc_html( $hod ); ?></h2>
				<?php endif; ?>
				<?php if ( '' !== $message ) : ?>
					<div class="mcm-hod-message"><?php echo wpautop( esc_html( $message ) ); // phpcs:ignore ?></div>
				<?php endif; ?>
				<?php if ( '' !== $email || '' !== $phone ) : ?>
					<div class="mcm-hod-contact">
						<?php if ( '' !== $email ) : ?>
							<a class="mcm-hod-contact-item" href="mailto:<?php echo esc_attr( $email ); ?>">
								<svg class="mcm-ico" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
								<span><?php echo esc_html( $email ); ?></span>
							</a>
						<?php endif; ?>
						<?php if ( '' !== $phone ) : ?>
							<a class="mcm-hod-contact-item" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>">
								<svg class="mcm-ico" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.7a2 2 0 0 1-.5 2.1L8 9.6a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.1-.5c.9.3 1.8.5 2.7.6a2 2 0 0 1 1.7 2Z"/></svg>
								<span><?php echo esc_html( $phone ); ?></span>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Hero + message together — used at the top of the auto department page.
	 * Kept as the public entry the archive template already calls.
	 *
	 * @param WP_Term $term Term object.
	 * @return string
	 */
	public static function term_intro( $term ) {
		if ( ! $term instanceof WP_Term || ! self::fields_for( $term->taxonomy ) ) {
			return '';
		}
		return self::hero_html( $term ) . self::message_html( $term );
	}

	/*
	 * ----------------------------------------------------------------
	 * Shortcodes for building a custom department template (Elementor).
	 * ----------------------------------------------------------------
	 */

	/**
	 * [mcm_department_hero department="anatomy"] — video or image banner.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_hero( $atts ) {
		$term = self::resolve_term( is_array( $atts ) ? $atts : array() );
		return $term ? MCM_Safe::render( array( __CLASS__, 'hero_html' ), 'shortcode mcm_department_hero', array( $term ) ) : '';
	}

	/**
	 * [mcm_department_message department="anatomy"] — the HOD message card.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_message( $atts ) {
		$term = self::resolve_term( is_array( $atts ) ? $atts : array() );
		return $term ? MCM_Safe::render( array( __CLASS__, 'message_html' ), 'shortcode mcm_department_message', array( $term ) ) : '';
	}

	/**
	 * [mcm_department_intro department="anatomy"] — hero + message together.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_intro( $atts ) {
		$term = self::resolve_term( is_array( $atts ) ? $atts : array() );
		return $term ? MCM_Safe::render( array( __CLASS__, 'term_intro' ), 'shortcode mcm_department_intro', array( $term ) ) : '';
	}

	/**
	 * [mcm_department_sections department="anatomy"] — every content type this
	 * department actually has (courses, timetable, faculty, publications…), each
	 * as its own titled section. Populated types only. Perfect as the body of a
	 * custom Elementor department template.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_sections( $atts ) {
		$term = self::resolve_term( is_array( $atts ) ? $atts : array() );
		return $term ? MCM_Safe::render( array( 'MCM_Templates', 'term_sections_html' ), 'shortcode mcm_department_sections', array( $term ) ) : '';
	}
}
