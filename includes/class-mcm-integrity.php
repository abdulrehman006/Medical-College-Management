<?php
/**
 * Content integrity for editors: flags published entries that are missing
 * the links or dates the website relies on (a team member with no
 * department never appears on a department page; a course with no program
 * is missing from the program page; a tender with no deadline never
 * closes), and shows deadline status in the list tables.
 *
 * Nothing is blocked: entries still publish, as before. The editor is told
 * once after saving, the entry carries a "Missing: …" label in the list,
 * and a "Needs attention" view lists every flagged entry of that type.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Missing-link warnings, the "Needs attention" view and deadline status columns.
 */
class MCM_Integrity {

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'save_post', array( __CLASS__, 'after_save' ), 20, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_list' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_list_hooks' ) );
	}

	/**
	 * What each type needs to show up where visitors expect it.
	 * 'tax' => taxonomies needing at least one term; 'meta' => required dates.
	 *
	 * @return array<string,array{tax:string[],meta:string[]}>
	 */
	public static function expectations() {
		$map = array(
			'mcm_team'        => array(
				'tax'  => array( 'mcm_department' ),
				'meta' => array(),
			),
			'mcm_consultant'  => array(
				'tax'  => array( 'mcm_department' ),
				'meta' => array(),
			),
			'mcm_course'      => array(
				'tax'  => array( 'mcm_department', 'mcm_program_link' ),
				'meta' => array(),
			),
			'mcm_timetable'   => array(
				'tax'  => array( 'mcm_department', 'mcm_program_link' ),
				'meta' => array( 'mcm_day' ),
			),
			'mcm_publication' => array(
				'tax'  => array( 'mcm_department' ),
				'meta' => array(),
			),
			'mcm_research'    => array(
				'tax'  => array( 'mcm_department' ),
				'meta' => array(),
			),
			'mcm_event'       => array(
				'tax'  => array(),
				'meta' => array( 'mcm_event_date' ),
			),
			'mcm_tender'      => array(
				'tax'  => array(),
				'meta' => array( 'mcm_tender_close' ),
			),
			'mcm_scholarship' => array(
				'tax'  => array(),
				'meta' => array( 'mcm_scholarship_deadline' ),
			),
			'mcm_admission'   => array(
				'tax'  => array(),
				'meta' => array( 'mcm_close_date' ),
			),
		);
		/**
		 * Filter what each content type is expected to have.
		 *
		 * @param array $map Type => array( 'tax' => taxonomies, 'meta' => meta keys ).
		 */
		$map = apply_filters( 'mcm_expected_links', $map );
		return is_array( $map ) ? $map : array();
	}

	/**
	 * Human label for a taxonomy or meta key.
	 *
	 * @param string $type Post type.
	 * @param string $key  Taxonomy or meta key.
	 * @return string
	 */
	private static function label( $type, $key ) {
		$tax = get_taxonomy( $key );
		if ( $tax ) {
			return $tax->labels->singular_name;
		}
		$fields = MCM_Meta::fields();
		return isset( $fields[ $type ]['fields'][ $key ]['label'] ) ? $fields[ $type ]['fields'][ $key ]['label'] : $key;
	}

	/**
	 * What a published entry is missing (labels), empty when complete.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function missing( $post_id ) {
		$type = (string) get_post_type( $post_id );
		$exp  = self::expectations();
		if ( empty( $exp[ $type ] ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) $exp[ $type ]['tax'] as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$terms = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				$out[] = self::label( $type, $tax );
			}
		}
		foreach ( (array) $exp[ $type ]['meta'] as $key ) {
			if ( '' === trim( MCM_Safe::meta( $post_id, $key ) ) ) {
				$out[] = self::label( $type, $key );
			}
		}
		return $out;
	}

	/**
	 * After a save (terms and fields are stored by now): remember what a
	 * published entry is missing, to tell the editor on the next screen.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function after_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) {
			return;
		}
		if ( ! is_admin() || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$missing = self::missing( $post_id );
		if ( $missing ) {
			set_transient(
				'mcm_missing_' . get_current_user_id(),
				array(
					'post'    => (int) $post_id,
					'missing' => $missing,
				),
				5 * MINUTE_IN_SECONDS
			);
		}
	}

	/**
	 * The warning shown on the edit screen after saving.
	 */
	public static function notice() {
		$key  = 'mcm_missing_' . get_current_user_id();
		$data = get_transient( $key );
		if ( ! is_array( $data ) || empty( $data['missing'] ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		delete_transient( $key );
		$type = get_post_type_object( (string) get_post_type( (int) $data['post'] ) );
		echo '<div class="notice notice-warning is-dismissible mcm-missing-notice"><p>';
		echo esc_html(
			sprintf(
				/* translators: 1: content type, e.g. "Team Member"; 2: comma-separated list, e.g. "Department, Program". */
				__( 'This %1$s is published, but it has no %2$s. Until you add it, it will not appear on the pages and filters that use it.', 'medical-college-manager' ),
				$type ? $type->labels->singular_name : __( 'entry', 'medical-college-manager' ),
				implode( ', ', array_map( 'strval', $data['missing'] ) )
			)
		);
		echo '</p></div>';
	}

	/**
	 * "Missing: Department" next to the title in the list table.
	 *
	 * @param string[] $states Post states.
	 * @param WP_Post  $post   Post.
	 * @return string[]
	 */
	public static function post_states( $states, $post ) {
		if ( ! is_admin() || 'publish' !== $post->post_status || ! isset( self::expectations()[ $post->post_type ] ) ) {
			return $states;
		}
		$missing = self::missing( $post->ID );
		if ( $missing ) {
			/* translators: %s: comma-separated list of what is missing. */
			$states['mcm_missing'] = sprintf( __( 'Missing: %s', 'medical-college-manager' ), implode( ', ', $missing ) );
		}
		return $states;
	}

	/**
	 * Published IDs of a type that are missing something.
	 *
	 * @param string $type Post type.
	 * @return int[]
	 */
	public static function flagged_ids( $type ) {
		$exp = self::expectations();
		if ( empty( $exp[ $type ] ) ) {
			return array();
		}
		$base = array(
			'post_type'              => $type,
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin-only count of a bounded list.
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'suppress_filters'       => false,
			'mcm_integrity'          => true,
		);
		$ids  = array();
		foreach ( (array) $exp[ $type ]['tax'] as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$q   = new WP_Query(
				$base + array(
					'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Admin list only.
						array(
							'taxonomy' => $tax,
							'operator' => 'NOT EXISTS',
						),
					),
				)
			);
			$ids = array_merge( $ids, $q->posts );
		}
		foreach ( (array) $exp[ $type ]['meta'] as $key ) {
			$q   = new WP_Query(
				$base + array(
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin list only.
						'relation' => 'OR',
						array(
							'key'     => $key,
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => $key,
							'value'   => '',
							'compare' => '=',
						),
					),
				)
			);
			$ids = array_merge( $ids, $q->posts );
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Views link and status columns for the list tables.
	 */
	public static function register_list_hooks() {
		foreach ( array_keys( self::expectations() ) as $type ) {
			add_filter( "views_edit-{$type}", array( __CLASS__, 'views' ) );
		}
		foreach ( array_keys( self::status_types() ) as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'status_column' ), 20 );
			add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'status_value' ), 10, 2 );
		}
	}

	/**
	 * "Needs attention (n)" link above the list.
	 *
	 * @param array $views Views.
	 * @return array
	 */
	public static function views( $views ) {
		$screen = get_current_screen();
		$type   = $screen ? $screen->post_type : '';
		$n      = count( self::flagged_ids( $type ) );
		if ( ! $n ) {
			return $views;
		}
		$url = add_query_arg(
			array(
				'post_type'     => $type,
				'mcm_attention' => 1,
			),
			admin_url( 'edit.php' )
		);

		$current                = isset( $_GET['mcm_attention'] ) ? ' class="current" aria-current="page"' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list view.
		$views['mcm_attention'] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
			esc_url( $url ),
			$current,
			esc_html__( 'Needs attention', 'medical-college-manager' ),
			esc_html( number_format_i18n( $n ) )
		);
		return $views;
	}

	/**
	 * Apply the "Needs attention" view.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function filter_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || empty( $_GET['mcm_attention'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list view.
			return;
		}
		$type = $query->get( 'post_type' );
		if ( ! is_string( $type ) || ! isset( self::expectations()[ $type ] ) ) {
			return;
		}
		$ids = self::flagged_ids( $type );
		$query->set( 'post__in', $ids ? $ids : array( 0 ) );
		$query->set( 'post_status', 'publish' );
	}

	/**
	 * Types with a date-driven status, and the field holding the date.
	 *
	 * @return array<string,string>
	 */
	public static function status_types() {
		return array(
			'mcm_tender'      => 'mcm_tender_close',
			'mcm_scholarship' => 'mcm_scholarship_deadline',
			'mcm_notice'      => 'mcm_notice_expiry',
			'mcm_admission'   => 'mcm_close_date',
			'mcm_event'       => 'mcm_event_date',
		);
	}

	/**
	 * Add the Status column before Date.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function status_column( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : null;
		unset( $columns['date'] );
		$columns['mcm_status_col'] = __( 'Status on website', 'medical-college-manager' );
		if ( null !== $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	/**
	 * Print the Status column: Open / Closed / Expired, with the date.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public static function status_value( $column, $post_id ) {
		if ( 'mcm_status_col' !== $column ) {
			return;
		}
		$type = (string) get_post_type( $post_id );
		$key  = isset( self::status_types()[ $type ] ) ? self::status_types()[ $type ] : '';
		$date = MCM_Meta::parse_date( MCM_Safe::meta( $post_id, $key ) );
		$when = false !== $date ? date_i18n( get_option( 'date_format' ), strtotime( $date ) ) : '';
		if ( 'mcm_notice' === $type ) {
			if ( false === $date ) {
				echo esc_html__( 'Shown (no expiry date)', 'medical-college-manager' );
				return;
			}
			$expired = $date < current_time( 'Y-m-d' );
			printf(
				'<span class="mcm-admin-status mcm-admin-status--%1$s">%2$s</span><br><small>%3$s</small>',
				esc_attr( $expired ? 'closed' : 'open' ),
				esc_html( $expired ? __( 'Expired — hidden', 'medical-college-manager' ) : __( 'Shown', 'medical-college-manager' ) ),
				/* translators: %s: date. */
				esc_html( sprintf( __( 'Expires %s', 'medical-college-manager' ), $when ) )
			);
			return;
		}
		$st = MCM_Query::date_status( $post_id, $type );
		if ( ! $st ) {
			echo esc_html( 'mcm_event' === $type ? __( 'No event date', 'medical-college-manager' ) : __( 'No deadline — stays open', 'medical-college-manager' ) );
			return;
		}
		$label = $st['label'];
		if ( 'mcm_tender' === $type && 'closed' === $st['status'] ) {
			$label = __( 'Closed — hidden', 'medical-college-manager' );
		}
		printf(
			'<span class="mcm-admin-status mcm-admin-status--%1$s">%2$s</span>%3$s',
			esc_attr( $st['badge'] ),
			esc_html( $label ),
			'' !== $when ? '<br><small>' . esc_html( $when ) . '</small>' : ''
		);
	}
}
