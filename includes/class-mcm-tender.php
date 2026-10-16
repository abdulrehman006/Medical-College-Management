<?php
/**
 * Tenders — modelled on Admissions, plus auto-expiry: once a tender's
 * submission deadline has passed it drops off the front-end listings
 * automatically (still visible/manageable in wp-admin).
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCM_Tender {

	public function __construct() {
		add_filter( 'mcm_query_args', array( $this, 'hide_expired' ), 10, 2 );
	}

	/**
	 * On the front-end, restrict tender queries to those still open (deadline
	 * today or later, or no deadline set). Filterable so a "past tenders" view
	 * can be built if ever needed.
	 */
	public function hide_expired( $args, $a ) {
		if ( 'mcm_tender' !== $a['type'] ) {
			return $args;
		}
		if ( ! apply_filters( 'mcm_tender_hide_expired', true, $a ) ) {
			return $args;
		}
		if ( empty( $args['meta_query'] ) ) {
			$args['meta_query'] = self::open_meta_query(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		return $args;
	}

	/** Meta query matching tenders whose submission deadline has not passed. */
	public static function open_meta_query() {
		return array(
			'relation' => 'OR',
			array( 'key' => 'mcm_tender_close', 'compare' => 'NOT EXISTS' ),
			array( 'key' => 'mcm_tender_close', 'value' => '', 'compare' => '=' ),
			array( 'key' => 'mcm_tender_close', 'value' => current_time( 'Y-m-d' ), 'compare' => '>=', 'type' => 'DATE' ),
		);
	}
}
