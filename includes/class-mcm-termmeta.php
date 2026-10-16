<?php
/**
 * Term-level content for the shared connector taxonomies.
 *
 * A department is more than a filter label: the review asked each department
 * page to carry the Head of Department's welcome message, the HOD's photograph
 * and a short banner video of the department's activities. Terms had no fields
 * at all, so there was nowhere to put any of it.
 *
 * The fields are added to the standard term add/edit screens, so they are
 * managed exactly where a department is managed — no separate settings page.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCM_TermMeta {

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

		// Shortcodes for composing a custom department template.
		add_shortcode( 'mcm_department_hero', array( $this, 'sc_hero' ) );
		add_shortcode( 'mcm_department_message', array( $this, 'sc_message' ) );
		add_shortcode( 'mcm_department_intro', array( $this, 'sc_intro' ) );
		add_shortcode( 'mcm_department_sections', array( $this, 'sc_sections' ) );
	}

	/**
	 * Which taxonomies get term content, and which fields.
	 *
	 * @return array
	 */
	public static function taxonomies() {
		return apply_filters(
			'mcm_term_fields',
			array(
				'mcm_department' => array(
					'mcm_term_video'     => array( 'label' => __( 'Hero Video URL', 'medical-college-manager' ), 'type' => 'url', 'desc' => __( 'YouTube/Vimeo link or an uploaded MP4 for the banner at the top of the page. Takes priority over the hero image. Note: some videos have embedding turned off by their owner and will show "Video unavailable" on any website — if that happens, use a different (embeddable) video or upload an MP4.', 'medical-college-manager' ) ),
					'mcm_term_banner'    => array( 'label' => __( 'Hero Image', 'medical-college-manager' ), 'type' => 'media', 'desc' => __( 'Static banner shown at the top when no hero video is set. A wide (16:9) image works best.', 'medical-college-manager' ) ),
					'mcm_term_hod'       => array( 'label' => __( 'Head of Department', 'medical-college-manager' ), 'type' => 'text', 'desc' => __( 'Name shown with the message.', 'medical-college-manager' ) ),
					'mcm_term_hod_photo' => array( 'label' => __( 'HOD Photograph', 'medical-college-manager' ), 'type' => 'media', 'desc' => __( 'Shown beside the message.', 'medical-college-manager' ) ),
					'mcm_term_message'   => array( 'label' => __( "Head of Department's Message", 'medical-college-manager' ), 'type' => 'textarea', 'desc' => __( 'The welcome / introduction message for this department page.', 'medical-college-manager' ) ),
					'mcm_term_email'     => array( 'label' => __( 'Departmental Email', 'medical-college-manager' ), 'type' => 'email', 'desc' => __( 'e.g. anatomy@gmcdik.edu.pk', 'medical-college-manager' ) ),
					'mcm_term_phone'     => array( 'label' => __( 'Departmental Phone', 'medical-college-manager' ), 'type' => 'text', 'desc' => '' ),
					'mcm_term_hidden'    => array( 'label' => __( 'Hide Sections', 'medical-college-manager' ), 'type' => 'sections', 'desc' => __( 'Tick any sections you do NOT want on this department page (e.g. hide the timetable). Unticked sections appear automatically whenever they have content.', 'medical-college-manager' ) ),
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

	/** Fields for one taxonomy, or an empty array. */
	public static function fields_for( $tax ) {
		$all = self::taxonomies();
		return isset( $all[ $tax ] ) ? $all[ $tax ] : array();
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
							if ( 'sections' === $type ) {
								return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
							}
							return MCM_Meta::sanitize_value( $type, $value );
						},
						'auth_callback'     => function () {
							return current_user_can( 'manage_categories' );
						},
					)
				);
			}
		}
	}

	/** The "add new term" screen has no table wrapper. */
	public function add_fields( $taxonomy ) {
		$fields = self::fields_for( $taxonomy );
		if ( ! $fields ) {
			return;
		}
		wp_nonce_field( 'mcm_term_save', 'mcm_term_nonce' );
		foreach ( $fields as $key => $field ) {
			echo '<div class="form-field">';
			printf( '<label for="%s">%s</label>', esc_attr( $key ), esc_html( $field['label'] ) );
			self::control( $key, '', $field );
			if ( ! empty( $field['desc'] ) ) {
				echo '<p>' . esc_html( $field['desc'] ) . '</p>';
			}
			echo '</div>';
		}
	}

	/** The "edit term" screen is a table. */
	public function edit_fields( $term, $taxonomy ) {
		$fields = self::fields_for( $taxonomy );
		if ( ! $fields ) {
			return;
		}
		wp_nonce_field( 'mcm_term_save', 'mcm_term_nonce' );
		foreach ( $fields as $key => $field ) {
			$value = MCM_Safe::term_meta( $term->term_id, $key );
			echo '<tr class="form-field">';
			printf( '<th scope="row"><label for="%s">%s</label></th><td>', esc_attr( $key ), esc_html( $field['label'] ) );
			self::control( $key, $value, $field );
			if ( ! empty( $field['desc'] ) ) {
				echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
			}
			echo '</td></tr>';
		}
	}

	/** Render one input. */
	private static function control( $key, $value, $field ) {
		switch ( $field['type'] ) {
			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%1$s" rows="5" style="width:95%%;">%2$s</textarea>',
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
				echo '<button type="button" class="button mcm-media-pick" data-type="' . esc_attr( ! empty( $field['accept'] ) ? $field['accept'] : 'image' ) . '">' . esc_html__( 'Select Image', 'medical-college-manager' ) . '</button> ';
				echo '<button type="button" class="button-link mcm-media-clear">' . esc_html__( 'Clear', 'medical-college-manager' ) . '</button>';
				echo '<span class="mcm-media-preview"></span>';
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
				continue;
			}
			$raw   = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$clean = MCM_Meta::sanitize_value( $field['type'], $raw );
			if ( '' === $clean ) {
				delete_term_meta( $term_id, $key );
			} else {
				update_term_meta( $term_id, $key, $clean );
			}
		}
	}

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
				<span class="mcm-hod-eyebrow"><?php esc_html_e( 'Head of Department', 'medical-college-manager' ); ?></span>
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

	/* ----------------------------------------------------------------- *
	 *  Shortcodes for building a custom department template (Elementor).
	 * ----------------------------------------------------------------- */

	/** [mcm_department_hero department="anatomy"] — video or image banner. */
	public function sc_hero( $atts ) {
		$term = self::resolve_term( is_array( $atts ) ? $atts : array() );
		return $term ? MCM_Safe::render( array( __CLASS__, 'hero_html' ), 'shortcode mcm_department_hero', array( $term ) ) : '';
	}

	/** [mcm_department_message department="anatomy"] — the HOD message card. */
	public function sc_message( $atts ) {
		$term = self::resolve_term( is_array( $atts ) ? $atts : array() );
		return $term ? MCM_Safe::render( array( __CLASS__, 'message_html' ), 'shortcode mcm_department_message', array( $term ) ) : '';
	}

	/** [mcm_department_intro department="anatomy"] — hero + message together. */
	public function sc_intro( $atts ) {
		$term = self::resolve_term( is_array( $atts ) ? $atts : array() );
		return $term ? MCM_Safe::render( array( __CLASS__, 'term_intro' ), 'shortcode mcm_department_intro', array( $term ) ) : '';
	}

	/**
	 * [mcm_department_sections department="anatomy"] — every content type this
	 * department actually has (courses, timetable, faculty, publications…), each
	 * as its own titled section. Populated types only. Perfect as the body of a
	 * custom Elementor department template.
	 */
	public function sc_sections( $atts ) {
		$term = self::resolve_term( is_array( $atts ) ? $atts : array() );
		return $term ? MCM_Safe::render( array( 'MCM_Templates', 'term_sections_html' ), 'shortcode mcm_department_sections', array( $term ) ) : '';
	}
}
