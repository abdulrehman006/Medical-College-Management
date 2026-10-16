<?php
/**
 * Shortcodes — one per CPT plus generic grid/list/table/single.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content list shortcodes and the shared block renderer.
 */
class MCM_Shortcodes {

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_shortcode( 'mcm_consultants', array( $this, 'sc_consultants' ) );
		add_shortcode( 'mcm_team', array( $this, 'sc_team' ) );
		add_shortcode( 'mcm_teams', array( $this, 'sc_team' ) ); // alias.
		add_shortcode( 'mcm_programs', array( $this, 'sc_programs' ) );
		add_shortcode( 'mcm_courses', array( $this, 'sc_courses' ) );
		add_shortcode( 'mcm_events', array( $this, 'sc_events' ) );
		add_shortcode( 'mcm_timetable', array( $this, 'sc_timetable' ) );
		add_shortcode( 'mcm_admissions', array( $this, 'sc_admissions' ) );
		add_shortcode( 'mcm_tenders', array( $this, 'sc_tenders' ) );
		add_shortcode( 'mcm_scholarships', array( $this, 'sc_scholarships' ) );
		add_shortcode( 'mcm_publications', array( $this, 'sc_publications' ) );
		add_shortcode( 'mcm_research', array( $this, 'sc_research' ) );
		add_shortcode( 'mcm_policies', array( $this, 'sc_policies' ) );
		add_shortcode( 'mcm_jobs', array( $this, 'sc_jobs' ) );
		add_shortcode( 'mcm_vacancies', array( $this, 'sc_jobs' ) ); // alias.
		add_shortcode( 'mcm_students', array( $this, 'sc_students' ) );
		add_shortcode( 'mcm_student', array( $this, 'sc_students' ) ); // alias.

		// Generic.
		add_shortcode( 'mcm_grid', array( $this, 'sc_grid' ) );
		add_shortcode( 'mcm_list', array( $this, 'sc_list' ) );
		add_shortcode( 'mcm_table', array( $this, 'sc_table' ) );
		add_shortcode( 'mcm_single', array( $this, 'sc_single' ) );
	}

	/**
	 * WordPress passes '' (empty string) when a shortcode has no attributes.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	private function normalize( $atts ) {
		return is_array( $atts ) ? $atts : array();
	}

	/**
	 * Whether a shortcode-style value means yes (yes/1/true/on).
	 *
	 * @param mixed $val Value.
	 */
	private static function truthy( $val ) {
		return in_array( strtolower( (string) $val ), array( 'yes', '1', 'true', 'on' ), true );
	}

	/**
	 * Shared renderer: outputs a wrapper that AJAX can target. Instance method
	 * kept for the shortcode callbacks; delegates to the reusable static below.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	private function render( $atts ) {
		return self::render_block( $atts );
	}

	/**
	 * Build one self-contained, AJAX-filterable block for the given attributes.
	 * Public + static so archive templates can render blocks directly.
	 *
	 * Every listing on the site (shortcodes, Elementor widgets, archive and
	 * department pages, program pages) comes through here, so this is the
	 * render boundary: a failure becomes an empty block, never a broken page.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public static function render_block( $atts ) {
		$type = ( is_array( $atts ) && isset( $atts['type'] ) ) ? MCM_Safe::str( $atts['type'] ) : '';
		return MCM_Safe::render(
			function () use ( $atts ) {
				return MCM_Shortcodes::build_block( $atts );
			},
			'listing ' . $type
		);
	}

	/**
	 * The unguarded block builder. Call render_block() instead.
	 *
	 * @internal
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function build_block( $atts ) {
		// Backstop: whatever rendered this block (shortcode, Elementor widget,
		// archive template) now needs the CSS/JS, even if detection missed it.
		MCM_Assets::need();

		$a   = MCM_Query::parse_atts( is_array( $atts ) ? $atts : array() );
		$uid = 'mcm-' . wp_generate_password( 6, false, false );

		// [mcm_students], [mcm_table type="mcm_student"]… render nothing when the
		// public student directory is switched off and the viewer is not staff.
		if ( 'mcm_student' === $a['type'] && ! MCM_Query::students_visible() ) {
			return '';
		}
		// Jobs are listable only while the Jobs module is up (it is what hides
		// jobs not yet published); otherwise only staff see the list.
		if ( 'mcm_job' === $a['type'] && ! MCM_Query::type_allowed( 'mcm_job' ) ) {
			return '';
		}

		$data = array(
			'type'      => $a['type'],
			'view'      => $a['view'],
			'columns'   => (int) $a['columns'],
			'count'     => (int) $a['count'],
			'orderby'   => $a['orderby'],
			'order'     => $a['order'],
			'category'  => $a['category'],
			'meta_key'  => $a['meta_key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- data-config attribute, not a query argument.
			'meta_type' => $a['meta_type'],
			'lock_tax'  => $a['lock_tax'],
			'lock_term' => $a['lock_term'],
			// The author's pre-filters (department="…", program="…", Elementor
			// pre-filter controls…). Without them the first AJAX search, filter
			// change, Reset or Print-all silently widened the list.
			'scope'     => (object) MCM_Query::scope_terms( $a ),
			// Jobs status filter (open/closed/all) must survive AJAX, Reset and Print.
			'status'    => sanitize_key( MCM_Safe::str( $a['status'] ) ),
			// Without these a filtered AJAX response silently loses pagination.
			'paginate'  => $a['paginate'],
			'paged'     => (int) $a['paged'],
		);

		$show_filters = self::truthy( $a['filters'] );

		/*
		 * Printable types come from the settings screen (Timetable and Students
		 * by default); the filter stays as the developer extension point. The
		 * type gate always applies — a print="yes" on a type that was never
		 * enabled does not conjure a button. Left unset, the shortcode simply
		 * follows the setting, so enabling a type there is the only step
		 * needed; print="no" still suppresses it on one instance.
		 */
		$print_types = apply_filters( 'mcm_print_types', MCM_Settings::print_types() );
		$print_attr  = trim( (string) $a['print'] );
		$show_print  = in_array( $a['type'], $print_types, true )
			&& ( '' === $print_attr ? true : self::truthy( $print_attr ) );

		ob_start();

		// Directory layout: filters live in a left sidebar, results on the right.
		if ( 'directory' === $a['view'] ) {
			echo '<div class="mcm-wrap mcm-directory ' . esc_attr( $a['class_css'] ) . '" id="' . esc_attr( $uid ) . '" data-config="' . esc_attr( wp_json_encode( $data ) ) . '">';
			echo '<aside class="mcm-dir-aside">';
			echo MCM_Query::render_sidebar( $a ); // phpcs:ignore
			echo '</aside>';
			echo '<div class="mcm-dir-main">';
			if ( $show_print ) {
				echo '<div class="mcm-toolbar"><button type="button" class="mcm-print-btn" aria-label="' . esc_attr__( 'Print', 'medical-college-manager' ) . '">&#128424; ' . esc_html__( 'Print', 'medical-college-manager' ) . '</button></div>';
				echo MCM_Query::print_header( $a ); // phpcs:ignore
				echo MCM_Query::print_footer(); // phpcs:ignore
			}
			// aria-live: filtering swaps these results without a page load, so
			// assistive tech is told the list changed.
			echo '<div class="mcm-results" aria-live="polite" aria-busy="false">';
			echo MCM_Query::render_results( $a ); // phpcs:ignore
			echo '</div>';
			echo '</div>';
			echo '</div>';
			return ob_get_clean();
		}

		echo '<div class="mcm-wrap ' . esc_attr( $a['class_css'] ) . '" id="' . esc_attr( $uid ) . '" data-config="' . esc_attr( wp_json_encode( $data ) ) . '">';

		if ( $show_filters || $show_print ) {
			echo '<div class="mcm-toolbar">';
			if ( $show_filters ) {
				echo MCM_Query::render_filters( $a ); // phpcs:ignore
			}
			if ( $show_print ) {
				echo '<button type="button" class="mcm-print-btn" aria-label="' . esc_attr__( 'Print', 'medical-college-manager' ) . '">&#128424; ' . esc_html__( 'Print', 'medical-college-manager' ) . '</button>';
			}
			echo '</div>';
		}

		if ( $show_print ) {
			echo MCM_Query::print_header( $a ); // phpcs:ignore
			echo MCM_Query::print_footer(); // phpcs:ignore
		}

		echo '<div class="mcm-results" aria-live="polite" aria-busy="false">';
		echo MCM_Query::render_results( $a ); // phpcs:ignore
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Shortcode callback for [mcm_consultants].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_consultants( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_consultant';
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_team] and [mcm_teams].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_team( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_team';
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_programs].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_programs( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_program';
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_courses].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_courses( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_course';
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_events].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_events( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_event';
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_timetable].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_timetable( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_timetable';
		if ( empty( $atts['view'] ) ) {
			$atts['view'] = 'table';
		}
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_admissions].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_admissions( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_admission';
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_tenders].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_tenders( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_tender';
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_scholarships].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_scholarships( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_scholarship';
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_publications].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_publications( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_publication';
		if ( empty( $atts['view'] ) ) {
			$atts['view'] = 'citation';
		}
		if ( empty( $atts['count'] ) ) {
			$atts['count'] = 20;
		}
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_research].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_research( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_research';
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_jobs] and [mcm_vacancies].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_jobs( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_job';
		if ( empty( $atts['count'] ) ) {
			$atts['count'] = 12;
		}
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_policies].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_policies( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_policy';
		if ( empty( $atts['view'] ) ) {
			$atts['view'] = 'list';
		}
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_students] and [mcm_student].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_students( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['type'] = 'mcm_student';
		if ( empty( $atts['view'] ) ) {
			$atts['view'] = 'table';
		}
		if ( empty( $atts['count'] ) ) {
			$atts['count'] = 50; // directory default — show more per page.
		}
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_grid].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_grid( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['view'] = 'grid';
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_list].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_list( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['view'] = 'list';
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_table].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_table( $atts ) {
		$atts         = $this->normalize( $atts );
		$atts['view'] = 'table';
		return $this->render( $atts );
	}

	/**
	 * Shortcode callback for [mcm_single].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_single( $atts ) {
		$a  = shortcode_atts(
			array(
				'id'   => 0,
				'type' => '',
			),
			$atts,
			'mcm_single'
		);
		$id = absint( $a['id'] );
		if ( ! $id ) {
			return '';
		}
		return MCM_Safe::render(
			function () use ( $id ) {
				MCM_Assets::need();
				return '<div class="mcm-wrap">' . MCM_Query::single( $id ) . '</div>';
			},
			'shortcode mcm_single'
		);
	}
}
