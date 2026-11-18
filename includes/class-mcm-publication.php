<?php
/**
 * Publications — research output. Adds sensible default ordering (newest
 * publication year first) without hiding records that have no year set.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default ordering for publications (newest year first).
 */
class MCM_Publication {

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_filter( 'mcm_query_args', array( $this, 'order_by_year' ), 10, 2 );
	}

	/**
	 * Default publications to publication-year DESC — research is listed by the
	 * year it was published, not the day it was typed into the site.
	 *
	 * Skipped whenever the author asked for a specific order, so
	 * [mcm_publications orderby="title" order="ASC"] still works: 'date' is the
	 * parse_atts() default and therefore means "no preference expressed".
	 *
	 * Entries with no year are kept, never dropped, and sort last. See
	 * MCM_Query::order_by_optional_meta() for why the two obvious meta_query
	 * approaches both get this wrong.
	 *
	 * @param array $args Query args.
	 * @param array $a    Parsed shortcode attributes.
	 * @return array
	 */
	public function order_by_year( $args, $a ) {
		if ( ! isset( $a['type'] ) || 'mcm_publication' !== $a['type'] ) {
			return $args;
		}
		// An explicit ordering request wins over the year default.
		if ( ! empty( $a['meta_key'] ) || ! empty( $args['meta_query'] ) ) {
			return $args;
		}
		if ( ! empty( $a['orderby'] ) && 'date' !== $a['orderby'] ) {
			return $args;
		}

		return MCM_Query::order_by_optional_meta( $args, 'mcm_pub_year', 'SIGNED', 'DESC' );
	}
}
