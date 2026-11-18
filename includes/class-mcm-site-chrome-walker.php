<?php
/**
 * Accessible menu walker for the college header (disclosure pattern): every
 * parent item stays a real link and gets its own toggle button.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core's walker (all its filters, classes and aria-current) plus a submenu
 * toggle button after each parent link.
 */
class MCM_Site_Chrome_Walker extends Walker_Nav_Menu {

	/**
	 * Start an item; parents get a toggle button after their link.
	 *
	 * @param string   $output Output, by reference.
	 * @param WP_Post  $data_object Menu item.
	 * @param int      $depth  Depth.
	 * @param stdClass $args   wp_nav_menu() arguments.
	 * @param int      $current_object_id Current item ID.
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
		parent::start_el( $output, $data_object, $depth, $args, $current_object_id );
		$max = is_object( $args ) && isset( $args->depth ) ? (int) $args->depth : 0;
		if ( $this->has_children && ( 0 === $max || $depth + 1 < $max ) ) {
			// Core's own title filter, as Walker_Nav_Menu applies it to the link.
			$label   = wp_strip_all_tags( (string) apply_filters( 'the_title', $data_object->title, $data_object->ID ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
			$output .= '<button class="mcm-hf-sub-toggle" type="button" aria-expanded="false"><span class="mcm-hf-sr">'
				/* translators: %s: menu item label. */
				. esc_html( sprintf( __( 'Show submenu for %s', 'medical-college-manager' ), $label ) )
				. '</span><span class="mcm-hf-caret" aria-hidden="true"></span></button>';
		}
	}
}
