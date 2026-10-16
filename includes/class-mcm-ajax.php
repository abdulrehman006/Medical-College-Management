<?php
/**
 * AJAX front-end filtering.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Live filtering endpoint (wp_ajax_mcm_filter).
 */
class MCM_Ajax {

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'wp_ajax_mcm_filter', array( $this, 'filter' ) );
		add_action( 'wp_ajax_nopriv_mcm_filter', array( $this, 'filter' ) );
	}

	/**
	 * AJAX entry point. Render boundary: any failure becomes a JSON error the
	 * front end can show ("please refresh"), never a PHP error page.
	 */
	public function filter() {
		try {
			$this->handle();
		} catch ( \Throwable $e ) {
			MCM_Health::report( 'AJAX filter', $e );
			wp_send_json_error(
				array(
					'code'    => 'error',
					'message' => __( 'Could not load results. Please refresh the page and try again.', 'medical-college-manager' ),
				),
				500
			);
		}
	}

	/** Unguarded handler; see filter(). */
	private function handle() {
		/*
		 * This endpoint only READS public listings, so the nonce is a CSRF
		 * nicety, not access control (type_allowed() is). Full-page caches can
		 * serve a page long after its nonce expired, which used to break every
		 * filter silently; such sites can turn the check off with
		 * add_filter( 'mcm_ajax_require_nonce', '__return_false' ).
		 */
		if ( apply_filters( 'mcm_ajax_require_nonce', true ) && ! check_ajax_referer( 'mcm_filter', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'code'    => 'nonce',
					'message' => __( 'This page has expired. Please refresh the page and try again.', 'medical-college-manager' ),
				),
				403
			);
		}

		// Every value below is untrusted. MCM_Safe::scalar() discards arrays and
		// objects BEFORE any sanitize_*() call sees them: on WordPress < 6.1,
		// sanitize_key() on an array is a fatal TypeError under PHP 8.
		$config = json_decode( MCM_Safe::post( 'config', '{}' ), true );
		$config = is_array( $config ) ? $config : array();

		// Visitor-chosen taxonomy filters: taxonomy => comma list of slugs.
		$raw_taxes   = json_decode( MCM_Safe::post( 'taxes', '{}' ), true );
		$tax_filters = array();
		if ( is_array( $raw_taxes ) ) {
			foreach ( $raw_taxes as $tax => $slugs ) {
				$tax   = sanitize_key( (string) $tax );
				$slugs = MCM_Query::normalize_slugs( is_scalar( $slugs ) ? (string) $slugs : '' );
				if ( $slugs && taxonomy_exists( $tax ) ) {
					$tax_filters[ $tax ] = implode( ',', $slugs );
				}
			}
		}

		// The author's pre-filters, echoed back from data-config: taxonomy =>
		// list of slugs. Normalised again by MCM_Query::scope_terms().
		$scope = array();
		if ( isset( $config['scope'] ) && is_array( $config['scope'] ) ) {
			foreach ( $config['scope'] as $tax => $slugs ) {
				$tax = sanitize_key( (string) $tax );
				if ( taxonomy_exists( $tax ) ) {
					$scope[ $tax ] = MCM_Query::normalize_slugs( $slugs );
				}
			}
		}

		$type = sanitize_key( MCM_Safe::scalar( $config, 'type', 'mcm_consultant' ) );

		/*
		 * The requested post type is attacker-controlled and this endpoint is
		 * also registered for nopriv, so the nonce is not an access control —
		 * it is localized on every front-end page for anonymous visitors.
		 * Without this check a crafted request for a non-public type (students)
		 * would return the whole directory, PMDC numbers and contacts included,
		 * because WP_Query honours an explicit post_type regardless of
		 * publicly_queryable. Non-public types stay available over AJAX only to
		 * users who could already read them in wp-admin.
		 */
		if ( ! MCM_Query::type_allowed( $type ) ) {
			wp_send_json_error(
				array(
					'code'    => 'forbidden',
					'message' => __( 'These results are not available.', 'medical-college-manager' ),
				),
				403
			);
		}

		// The sort key is attacker-controlled too. Ordering by an arbitrary meta
		// key would let a visitor rank students by a private field (date of
		// birth, blood group…) even though that field is never printed, so only
		// keys the type is allowed to expose may drive the sort.
		$meta_key = sanitize_key( MCM_Safe::scalar( $config, 'meta_key' ) );
		if ( '' !== $meta_key && ! in_array( $meta_key, MCM_Query::sortable_meta_keys( $type ), true ) ) {
			$meta_key = '';
		}

		$a = MCM_Query::parse_atts(
			array(
				'type'        => $type,
				'view'        => sanitize_key( MCM_Safe::scalar( $config, 'view', 'grid' ) ),
				'columns'     => absint( MCM_Safe::scalar( $config, 'columns', '3' ) ),
				// Clamp so a crafted request can't ask for an unbounded result set.
				'count'       => min( absint( MCM_Safe::scalar( $config, 'count', '12' ) ), (int) apply_filters( 'mcm_ajax_max_count', 1000 ) ),
				'orderby'     => sanitize_key( MCM_Safe::scalar( $config, 'orderby', 'date' ) ),
				'order'       => sanitize_text_field( MCM_Safe::scalar( $config, 'order', 'DESC' ) ),
				'category'    => sanitize_text_field( MCM_Safe::scalar( $config, 'category' ) ),
				'meta_key'    => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Shortcode attribute (whitelisted sort key), not a query argument.
				'meta_type'   => sanitize_text_field( MCM_Safe::scalar( $config, 'meta_type' ) ),
				'lock_tax'    => sanitize_key( MCM_Safe::scalar( $config, 'lock_tax' ) ),
				'lock_term'   => sanitize_title( MCM_Safe::scalar( $config, 'lock_term' ) ),
				// Pagination survives filtering: a paginated block stays
				// paginated, and the pager loads pages in place.
				'paginate'    => MCM_Query::truthy( MCM_Safe::scalar( $config, 'paginate', 'no' ) ) ? 'yes' : 'no',
				'paged'       => max( 1, absint( MCM_Safe::scalar( $config, 'paged', '1' ) ) ),
				'search'      => sanitize_text_field( MCM_Safe::post( 'search' ) ),
				'scope'       => $scope,
				'tax_filters' => $tax_filters,
			)
		);

		$html = MCM_Query::render_results( $a );
		wp_send_json_success( array( 'html' => $html ) );
	}
}
