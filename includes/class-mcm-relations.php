<?php
/**
 * "Related Content" meta box — displays how the post links to other content
 * through the shared connector taxonomies (Department, Faculty, Session,
 * Specialty). Read-only: relationships are created by assigning shared terms.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Related Content box in wp-admin.
 */
class MCM_Relations {

	/** Max related items shown per content type, per term. */
	const PER_TYPE = 8;

	/** Max terms processed per connector taxonomy (keeps the box light). */
	const MAX_TERMS = 6;

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
	}

	/**
	 * Cross-CPT connector taxonomies, in display order. A connector links posts
	 * across every CPT it is attached to.
	 *
	 * @return array
	 */
	public static function connectors() {
		return apply_filters(
			'mcm_relation_connectors',
			array( 'mcm_department', 'mcm_teacher', 'mcm_session', 'mcm_specialty', 'mcm_research_area', 'mcm_program_link' )
		);
	}

	/**
	 * Add the Related Content box to every content type.
	 */
	public function add_box() {
		foreach ( array_keys( MCM_CPT::post_types() ) as $cpt ) {
			add_meta_box(
				'mcm_related',
				__( 'Related Content', 'medical-college-manager' ),
				array( $this, 'render' ),
				$cpt,
				'normal',
				'low'
			);
		}
	}

	/**
	 * Shared renderer for the shortcode callbacks.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render( $post ) {
		$type   = $post->post_type;
		$taxes  = MCM_CPT::taxonomies();
		$blocks = '';

		foreach ( self::connectors() as $tax ) {
			if ( ! is_object_in_taxonomy( $type, $tax ) ) {
				continue;
			}
			$terms = get_the_terms( $post->ID, $tax );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$tobj     = get_taxonomy( $tax );
			$tlabel   = $tobj ? $tobj->labels->singular_name : $tax;
			$cpts_for = isset( $taxes[ $tax ]['post_types'] ) ? $taxes[ $tax ]['post_types'] : array();

			foreach ( array_slice( $terms, 0, self::MAX_TERMS ) as $term ) {
				$grouped = $this->related_by_term( $post->ID, $tax, $term, $cpts_for );
				if ( empty( $grouped ) ) {
					continue;
				}

				$blocks .= '<div class="mcm-rel-term"><h4>' . esc_html( $tlabel . ': ' . $term->name ) . '</h4>';
				foreach ( $grouped as $cpt => $info ) {
					$pt     = get_post_type_object( $cpt );
					$plabel = $pt ? $pt->labels->name : $cpt;

					$links = array();
					foreach ( $info['posts'] as $rid ) {
						$edit    = get_edit_post_link( $rid );
						$title   = get_the_title( $rid );
						$title   = '' === $title ? __( '(no title)', 'medical-college-manager' ) : $title;
						$links[] = $edit
							? '<a href="' . esc_url( $edit ) . '">' . esc_html( $title ) . '</a>'
							: esc_html( $title );
					}

					$blocks .= '<div class="mcm-rel-group"><strong>' . esc_html( $plabel ) . ':</strong> ' . implode( ', ', $links );
					if ( $info['more'] > 0 ) {
						/* translators: %d: number of additional related items. */
						$blocks .= ' <span class="mcm-rel-more">' . esc_html( sprintf( __( '+%d more', 'medical-college-manager' ), $info['more'] ) ) . '</span>';
					}
					$blocks .= '</div>';
				}
				$blocks .= '</div>';
			}
		}

		if ( '' === $blocks ) {
			echo '<p class="mcm-rel-empty">' . esc_html__( 'No related content yet. Assign shared Departments, Faculty, Sessions or Specialties to link this with other content.', 'medical-college-manager' ) . '</p>';
			return;
		}

		echo '<div class="mcm-relations">' . $blocks . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Posts (across every CPT using $tax) that share $term, excluding the
	 * current post, grouped by post type and capped per type.
	 *
	 * @param int     $post_id Post ID.
	 * @param string  $tax Taxonomy name.
	 * @param WP_Term $term Term object.
	 * @param mixed   $cpts_for Cpts for.
	 * @return array  cpt => array( posts => int[], more => int )
	 */
	private function related_by_term( $post_id, $tax, $term, $cpts_for ) {
		if ( empty( $cpts_for ) ) {
			return array();
		}

		// Don't surface a type the current user can't edit — otherwise an editor
		// with no student capability would see student names (via a shared
		// Department term) in this box. Public types are always fine to list.
		$cpts_for = array_values(
			array_filter(
				$cpts_for,
				function ( $cpt ) {
					if ( is_post_type_viewable( $cpt ) ) {
						return true;
					}
					$obj = get_post_type_object( $cpt );
					$cap = ( $obj && isset( $obj->cap->edit_posts ) ) ? $obj->cap->edit_posts : 'edit_posts';
					return current_user_can( $cap );
				}
			)
		);
		if ( empty( $cpts_for ) ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'      => $cpts_for,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin meta box, IDs only; needed to count the "+N more".
				'post__not_in'   => array( $post_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Excludes only the post being edited, in an admin meta box.
				'no_found_rows'  => true,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => $tax,
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
			)
		);

		$grouped = array();
		foreach ( $query->posts as $rid ) {
			$cpt = get_post_type( $rid );
			if ( ! isset( $grouped[ $cpt ] ) ) {
				$grouped[ $cpt ] = array(
					'posts' => array(),
					'more'  => 0,
				);
			}
			if ( count( $grouped[ $cpt ]['posts'] ) < self::PER_TYPE ) {
				$grouped[ $cpt ]['posts'][] = $rid;
			} else {
				++$grouped[ $cpt ]['more'];
			}
		}

		return $grouped;
	}
}
