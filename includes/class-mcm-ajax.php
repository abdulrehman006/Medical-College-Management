<?php
/**
 * AJAX front-end filtering.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCM_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_mcm_filter', array( $this, 'filter' ) );
		add_action( 'wp_ajax_nopriv_mcm_filter', array( $this, 'filter' ) );
	}

	public function filter() {
		check_ajax_referer( 'mcm_filter', 'nonce' );

		$config = isset( $_POST['config'] ) ? json_decode( wp_unslash( $_POST['config'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! is_array( $config ) ) {
			$config = array();
		}

		// Build & sanitize taxonomy => slug filters generically.
		$raw_taxes   = isset( $_POST['taxes'] ) ? json_decode( wp_unslash( $_POST['taxes'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$tax_filters = array();
		if ( is_array( $raw_taxes ) ) {
			foreach ( $raw_taxes as $tax => $slug ) {
				$tax  = sanitize_key( $tax );
				$slug = sanitize_text_field( $slug );
				if ( '' !== $slug && taxonomy_exists( $tax ) ) {
					$tax_filters[ $tax ] = $slug;
				}
			}
		}

		$type = isset( $config['type'] ) ? sanitize_key( $config['type'] ) : 'mcm_consultant';

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
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'medical-college-manager' ) ), 403 );
		}

		// The sort key is attacker-controlled too. Ordering by an arbitrary meta
		// key would let a visitor rank students by a private field (date of
		// birth, blood group…) even though that field is never printed, so only
		// keys the type is allowed to expose may drive the sort.
		$meta_key = isset( $config['meta_key'] ) ? sanitize_key( $config['meta_key'] ) : '';
		if ( '' !== $meta_key && ! in_array( $meta_key, MCM_Query::sortable_meta_keys( $type ), true ) ) {
			$meta_key = '';
		}

		$a = MCM_Query::parse_atts(
			array(
				'type'        => $type,
				'view'        => isset( $config['view'] ) ? sanitize_key( $config['view'] ) : 'grid',
				'columns'     => isset( $config['columns'] ) ? absint( $config['columns'] ) : 3,
				// Clamp so a crafted request can't ask for an unbounded result set.
				'count'       => min( isset( $config['count'] ) ? absint( $config['count'] ) : 12, (int) apply_filters( 'mcm_ajax_max_count', 1000 ) ),
				'orderby'     => isset( $config['orderby'] ) ? sanitize_key( $config['orderby'] ) : 'date',
				'order'       => isset( $config['order'] ) ? sanitize_text_field( wp_unslash( $config['order'] ) ) : 'DESC',
				'category'    => isset( $config['category'] ) ? sanitize_text_field( wp_unslash( $config['category'] ) ) : '',
				'meta_key'    => $meta_key,
				'meta_type'   => isset( $config['meta_type'] ) ? sanitize_text_field( wp_unslash( $config['meta_type'] ) ) : '',
				'lock_tax'    => isset( $config['lock_tax'] ) ? sanitize_key( $config['lock_tax'] ) : '',
				'lock_term'   => isset( $config['lock_term'] ) ? sanitize_text_field( wp_unslash( $config['lock_term'] ) ) : '',
				'search'      => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
				'tax_filters' => $tax_filters,
			)
		);

		$html = MCM_Query::render_results( $a );
		wp_send_json_success( array( 'html' => $html ) );
	}
}
