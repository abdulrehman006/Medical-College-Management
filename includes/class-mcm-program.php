<?php
/**
 * Program spine.
 *
 * The mcm_program CPT holds the rich program page (degree level, duration,
 * fees). The mcm_program_link taxonomy is the connector that Courses, Timetable,
 * Admissions and Students are tagged with, so a program can list its own
 * curriculum, schedule, intake and roll.
 *
 * To avoid double data entry, a matching taxonomy term is created automatically
 * for every Program CPT entry (the same pattern the importer uses for Faculty),
 * so the "Program" dropdown on a Course/Student/etc. is pre-populated with the
 * programs the college already defined.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Program spine: Program terms kept in sync with Program posts.
 */
class MCM_Program {

	const TAX = 'mcm_program_link';

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		// Keep a Program term in sync with each Program CPT entry.
		add_action( 'save_post_mcm_program', array( $this, 'sync_term' ), 10, 3 );
		// Add the curriculum / schedule / intake blocks to a Program page.
		add_filter( 'mcm_program_related', array( $this, 'noop' ) ); // reserved hook.
	}

	/**
	 * Placeholder so the reserved filter always exists.
	 *
	 * @param mixed $v Value.
	 */
	public function noop( $v ) {
		return $v;
	}

	/**
	 * Ensure a Program term exists for a saved Program CPT entry.
	 *
	 * @param int     $post_id Program post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 */
	public function sync_term( $post_id, $post = null, $update = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- save_post_{post_type} callback signature.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$post = $post ? $post : get_post( $post_id );
		if ( ! $post || 'mcm_program' !== $post->post_type ) {
			return;
		}
		$title = trim( $post->post_title );
		if ( '' === $title ) {
			return;
		}
		self::ensure_term( $title );
	}

	/**
	 * Find or create a Program term by name. Matches slug then name before
	 * creating, so it never duplicates an existing term.
	 *
	 * @param string $name Program name.
	 * @return int Term ID (0 on failure).
	 */
	public static function ensure_term( $name ) {
		$name = trim( $name );
		if ( '' === $name ) {
			return 0;
		}
		$term = get_term_by( 'slug', sanitize_title( $name ), self::TAX );
		if ( ! $term ) {
			$term = get_term_by( 'name', $name, self::TAX );
		}
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		$new = wp_insert_term( $name, self::TAX );
		return ( ! is_wp_error( $new ) && isset( $new['term_id'] ) ) ? (int) $new['term_id'] : 0;
	}

	/**
	 * One-time, additive, idempotent migration.
	 *
	 * 1. Seeds a Program term for every existing Program CPT entry.
	 * 2. For each Student ('mcm_program' text) and Admission ('mcm_program_name'
	 *    text), creates/finds the matching term and assigns it — WITHOUT touching
	 *    the original text (fully reversible; re-running changes nothing).
	 *
	 * Runs from the activation / version-upgrade path, guarded by an option.
	 */
	public static function migrate() {
		if ( get_option( 'mcm_program_migrated' ) ) {
			return;
		}

		// 1. Seed terms from existing Program CPT entries.
		$programs = get_posts(
			array(
				'post_type'        => 'mcm_program',
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $programs as $pid ) {
			self::ensure_term( get_the_title( $pid ) );
		}

		// 2. Assign terms from the free-text fields (text kept intact).
		$sources = array(
			'mcm_student'   => 'mcm_program',
			'mcm_admission' => 'mcm_program_name',
		);
		foreach ( $sources as $type => $meta_key ) {
			$posts = get_posts(
				array(
					'post_type'        => $type,
					'post_status'      => 'any',
					'numberposts'      => -1,
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			);
			foreach ( $posts as $pid ) {
				$value = trim( (string) MCM_Safe::meta( $pid, $meta_key ) );
				if ( '' === $value ) {
					continue;
				}
				$term_id = self::ensure_term( $value );
				if ( $term_id ) {
					// append = true: never clobber a term already set by hand.
					wp_set_object_terms( $pid, array( $term_id ), self::TAX, true );
				}
			}
		}

		update_option( 'mcm_program_migrated', 1, false );
	}

	/**
	 * Curriculum / schedule / intake blocks for a Program CPT single page,
	 * matched to this program's own term. Returns '' when there is no matching
	 * term or nothing tagged to it, so an unconnected program looks unchanged.
	 *
	 * Students are intentionally excluded from the public program page (the roll
	 * is personal data — view it via the protected [mcm_students] page instead).
	 *
	 * @param int $program_id Program post ID.
	 * @return string
	 */
	public static function related_sections( $program_id ) {
		$title = get_the_title( $program_id );
		$term  = get_term_by( 'slug', sanitize_title( $title ), self::TAX );
		if ( ! $term ) {
			$term = get_term_by( 'name', $title, self::TAX );
		}
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		$sections = array(
			'mcm_course'    => __( 'Courses', 'medical-college-manager' ),
			'mcm_timetable' => __( 'Timetable', 'medical-college-manager' ),
			'mcm_admission' => __( 'Admissions', 'medical-college-manager' ),
		);

		$out = '';
		foreach ( $sections as $type => $label ) {
			// Only render a section that actually has content for this program.
			$count = (int) ( new WP_Query(
				array(
					'post_type'      => $type,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => false,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'tax_query'      => array(
						array(
							'taxonomy' => self::TAX,
							'field'    => 'term_id',
							'terms'    => $term->term_id,
						),
					),
				)
			) )->found_posts;
			if ( ! $count ) {
				continue;
			}

			$view = ( 'mcm_timetable' === $type ) ? 'table' : 'grid';
			$out .= '<section class="mcm-program-section">';
			$out .= '<h2 class="mcm-program-section-title">' . esc_html( $label ) . '</h2>';
			$out .= MCM_Shortcodes::render_block(
				array(
					'type'      => $type,
					'view'      => $view,
					'filters'   => 'no',
					'lock_tax'  => self::TAX,
					'lock_term' => $term->slug,
					'count'     => 50,
				)
			);
			$out .= '</section>';
		}

		return $out;
	}
}
