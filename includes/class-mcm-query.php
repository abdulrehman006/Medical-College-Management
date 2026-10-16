<?php
/**
 * Query builder + HTML renderers shared by shortcodes and AJAX.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query builder and HTML renderers shared by shortcodes, widgets, archives and AJAX.
 */
class MCM_Query {

	/**
	 * Map each CPT to its filterable taxonomies (the first is the "primary"
	 * one used by the generic `category` attribute). The filter bar renders
	 * a dropdown for every taxonomy listed here.
	 *
	 * @return array
	 */
	public static function tax_map() {
		return array(
			'mcm_timetable'   => array( 'mcm_program_link', 'mcm_department', 'mcm_session', 'mcm_teacher' ),
			'mcm_program'     => array( 'mcm_program_cat', 'mcm_department' ),
			'mcm_course'      => array( 'mcm_program_link', 'mcm_department', 'mcm_teacher', 'mcm_session', 'mcm_course_cat' ),
			'mcm_event'       => array( 'mcm_event_cat', 'mcm_department' ),
			'mcm_consultant'  => array( 'mcm_department', 'mcm_specialty', 'mcm_teacher', 'mcm_research_area' ),
			'mcm_team'        => array( 'mcm_department', 'mcm_specialty', 'mcm_teacher', 'mcm_research_area' ),
			'mcm_publication' => array( 'mcm_research_area', 'mcm_department', 'mcm_teacher' ),
			'mcm_research'    => array( 'mcm_research_area', 'mcm_department', 'mcm_teacher' ),
			'mcm_policy'      => array( 'mcm_policy_cat', 'mcm_department' ),
			'mcm_admission'   => array( 'mcm_program_link', 'mcm_department', 'mcm_session' ),
			'mcm_notice'      => array( 'mcm_notice_cat', 'mcm_department' ),
			'mcm_tender'      => array( 'mcm_tender_cat', 'mcm_department' ),
			'mcm_scholarship' => array( 'mcm_scholarship_cat', 'mcm_department' ),
			'mcm_student'     => array( 'mcm_program_link', 'mcm_level', 'mcm_department', 'mcm_session' ),
		);
	}

	/**
	 * Meta keys that the free-text search should also match (per CPT).
	 * Lets students be found by ID, roll no, email, phone, program, batch.
	 *
	 * @param string $type Post type.
	 * @return array
	 */
	public static function searchable_meta( $type ) {
		$map = array(
			// Students are searchable by public identifiers only — never by email
			// or phone, so the list cannot be used to confirm a student from
			// their contact details. Name is always matched via the post title.
			'mcm_student'    => array( 'mcm_student_id', 'mcm_roll_no', 'mcm_program', 'mcm_batch' ),
			'mcm_consultant' => array( 'mcm_email', 'mcm_phone', 'mcm_designation', 'mcm_qualifications' ),
			'mcm_team'       => array( 'mcm_email', 'mcm_phone', 'mcm_designation' ),
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : array();
	}

	/**
	 * Meta keys a public request may sort a type by.
	 *
	 * Students may only be ordered by the safe columns their public table
	 * already shows; every other type by any of its own registered fields.
	 *
	 * @param string $type Post type.
	 * @return string[]
	 */
	public static function sortable_meta_keys( $type ) {
		if ( 'mcm_student' === $type ) {
			$keys = array();
			foreach ( self::table_columns( $type ) as $col ) {
				if ( in_array( $col[1], array( 'meta', 'date' ), true ) && '' !== $col[2] ) {
					$keys[] = $col[2];
				}
			}
			return $keys;
		}
		$fields = MCM_Meta::fields();
		return isset( $fields[ $type ]['fields'] ) ? array_keys( $fields[ $type ]['fields'] ) : array();
	}

	/** Types whose free-text search also scans meta (drives the SQL whitelist). */
	private static function meta_search_types() {
		return array( 'mcm_student', 'mcm_consultant', 'mcm_team' );
	}

	/**
	 * Shared truthy check for yes/1/true/on attribute values.
	 *
	 * @param mixed $val Value.
	 */
	public static function truthy( $val ) {
		return in_array( strtolower( (string) $val ), array( 'yes', '1', 'true', 'on' ), true );
	}

	/**
	 * Normalize/parse shortcode-style attributes.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public static function parse_atts( $atts ) {
		$defaults = array(
			'type'          => 'mcm_consultant',
			'view'          => 'grid',        // grid | list | table.
			'columns'       => MCM_Settings::value( 'columns' ),
			'count'         => 12,
			'category'      => '',            // primary taxonomy term slug(s).
			'filters'       => 'no',          // show filter bar.
			'print'         => '',            // '' = follow the settings screen; yes/no forces it.
			'paginate'      => 'no',          // render numbered pagination.
			'print_title'   => '',            // heading shown on the printed page.
			'search'        => '',            // initial search string.
			'orderby'       => 'date',
			'order'         => 'DESC',
			'meta_key'      => '',            // enables orderby="meta_value".
			'meta_type'     => '',            // e.g. DATE, NUMERIC, CHAR.
			'paged'         => 1,
			'class_css'     => '',
			// Generic per-taxonomy attribute (slug = taxonomy without mcm_ prefix).
			'department'    => '',
			'session'       => '',
			'teacher'       => '',
			'specialty'     => '',
			'level'         => '',
			'research_area' => '',
			'program'       => '',
			// Alias: editors think "professor", the taxonomy is mcm_teacher.
			'professor'     => '',
			// Locked taxonomy term (e.g. on a taxonomy archive) — always applied
			// and ANDed in, surviving AJAX so the archive stays scoped.
			'lock_tax'      => '',
			'lock_term'     => '',
			// Internal: taxonomy => term map chosen by the VISITOR (AJAX).
			'tax_filters'   => array(),
			// Internal: taxonomy => slug(s) pre-filter set by the AUTHOR (Elementor
			// widget controls). Locked like the friendly attributes above.
			'prefilter'     => array(),
			// Internal: the author scope round-tripped through data-config (AJAX).
			'scope'         => array(),
		);
		$a = wp_parse_args( $atts, $defaults );

		foreach ( array( 'tax_filters', 'prefilter', 'scope' ) as $key ) {
			if ( ! is_array( $a[ $key ] ) ) {
				$a[ $key ] = array();
			}
		}
		return $a;
	}

	/**
	 * Meta query keeping items whose date in $meta_key is today or later in
	 * the SITE timezone, or that have no date at all. Shared by every type
	 * that auto-expires (tenders, notices, and later jobs).
	 *
	 * @param string $meta_key Meta key holding a Y-m-d date.
	 * @return array
	 */
	public static function expiry_meta_query( $meta_key ) {
		return array(
			'relation' => 'OR',
			array(
				'key'     => $meta_key,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => $meta_key,
				'value'   => '',
				'compare' => '=',
			),
			array(
				'key'     => $meta_key,
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '>=',
				'type'    => 'DATE',
			),
		);
	}

	/**
	 * Friendly shortcode attribute => taxonomy.
	 *
	 * @return array<string,string>
	 */
	public static function attr_taxonomies() {
		return array(
			'department'    => 'mcm_department',
			'session'       => 'mcm_session',
			'teacher'       => 'mcm_teacher',
			'professor'     => 'mcm_teacher',        // friendly alias for teacher.
			'specialty'     => 'mcm_specialty',
			'level'         => 'mcm_level',
			'research_area' => 'mcm_research_area',
			'program'       => 'mcm_program_link',   // the Program spine connector.
		);
	}

	/**
	 * Turn "a, b" or array( 'a', 'b' ) into a clean, unique list of slugs.
	 * Non-scalar members are dropped, and the list is capped so a crafted
	 * request cannot build an enormous query.
	 *
	 * @param mixed $value Comma list or array.
	 * @return string[]
	 */
	public static function normalize_slugs( $value ) {
		$parts = is_array( $value ) ? $value : explode( ',', MCM_Safe::str( $value ) );
		$out   = array();
		foreach ( $parts as $part ) {
			if ( ! is_scalar( $part ) ) {
				continue;
			}
			$slug = sanitize_title( trim( (string) $part ) );
			if ( '' !== $slug ) {
				$out[ $slug ] = $slug;
			}
		}
		return array_slice( array_values( $out ), 0, 50 );
	}

	/**
	 * The author's scope: every term restriction set by whoever placed the
	 * block. That is the `category` attribute, the friendly attributes
	 * (department, program and so on), Elementor pre-filters, and the scope
	 * echoed back by AJAX.
	 *
	 * A scope is ALWAYS applied. Visitors can only narrow within it: with one
	 * term its dropdown is hidden; with several the dropdown lists only those.
	 *
	 * @param array $a Parsed attributes.
	 * @return array<string,string[]> Taxonomy => slugs.
	 */
	public static function scope_terms( $a ) {
		$sources = array();

		$map     = self::tax_map();
		$type    = MCM_Safe::str( $a['type'] );
		$primary = isset( $map[ $type ][0] ) ? $map[ $type ][0] : '';
		if ( $primary && '' !== MCM_Safe::str( $a['category'] ) ) {
			$sources[] = array( $primary, $a['category'] );
		}
		foreach ( self::attr_taxonomies() as $attr => $tax ) {
			if ( isset( $a[ $attr ] ) && '' !== MCM_Safe::str( $a[ $attr ] ) ) {
				$sources[] = array( $tax, $a[ $attr ] );
			}
		}
		foreach ( array( 'prefilter', 'scope' ) as $key ) {
			if ( ! empty( $a[ $key ] ) && is_array( $a[ $key ] ) ) {
				foreach ( $a[ $key ] as $tax => $value ) {
					$sources[] = array( $tax, $value );
				}
			}
		}

		$scope = array();
		foreach ( $sources as $source ) {
			$tax = sanitize_key( MCM_Safe::str( $source[0] ) );
			if ( '' === $tax || ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$slugs = self::normalize_slugs( $source[1] );
			if ( ! $slugs ) {
				continue;
			}
			$scope[ $tax ] = isset( $scope[ $tax ] ) ? array_values( array_unique( array_merge( $scope[ $tax ], $slugs ) ) ) : $slugs;
		}
		return $scope;
	}

	/**
	 * Terms a visitor may pick in one taxonomy's dropdown or facet, honouring
	 * the author's scope. An empty array means: do not show that control.
	 *
	 * @param string $tax   Taxonomy.
	 * @param array  $scope Output of scope_terms().
	 * @return WP_Term[]
	 */
	private static function visible_facet_terms( $tax, $scope ) {
		$terms = self::facet_terms( $tax );
		if ( ! isset( $scope[ $tax ] ) ) {
			return $terms;
		}
		if ( count( $scope[ $tax ] ) < 2 ) {
			return array(); // A single scoped term: nothing to choose.
		}
		$keep = array_flip( $scope[ $tax ] );
		$out  = array();
		foreach ( $terms as $term ) {
			if ( isset( $keep[ $term->slug ] ) ) {
				$out[] = $term;
			}
		}
		return $out;
	}

	/**
	 * Build the combined taxonomy => terms map from all sources.
	 *
	 * Precedence: the author's scope is always applied. A visitor's choice for
	 * a scoped taxonomy is intersected with it, so a choice outside the scope
	 * is ignored and never widens it. The locked archive term wins for its own
	 * taxonomy.
	 *
	 * @param array $a Parsed attributes.
	 * @return array
	 */
	private static function collect_terms( $a ) {
		$terms = self::scope_terms( $a );

		// Visitor choices (AJAX / explicit tax_filters: taxonomy => slug list).
		foreach ( (array) $a['tax_filters'] as $tax => $value ) {
			$tax = sanitize_key( MCM_Safe::str( $tax ) );
			if ( '' === $tax || ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$slugs = self::normalize_slugs( $value );
			if ( ! $slugs ) {
				continue;
			}
			if ( isset( $terms[ $tax ] ) ) {
				$slugs = array_values( array_intersect( $slugs, $terms[ $tax ] ) );
				if ( ! $slugs ) {
					continue; // Outside the scope: keep the scope.
				}
			}
			$terms[ $tax ] = $slugs;
		}

		// Locked taxonomy term wins for its taxonomy (archive scoping).
		if ( '' !== $a['lock_tax'] && '' !== $a['lock_term'] && taxonomy_exists( $a['lock_tax'] ) ) {
			$terms[ $a['lock_tax'] ] = array( $a['lock_term'] );
		}

		return $terms;
	}

	/**
	 * Build and run WP_Query from parsed attributes.
	 *
	 * @param array $a Parsed attributes.
	 * @return WP_Query
	 */
	public static function get_query( $a ) {
		$terms     = self::collect_terms( $a );
		$tax_query = array();

		foreach ( $terms as $tax => $slugs ) {
			if ( ! taxonomy_exists( $tax ) || empty( $slugs ) ) {
				continue;
			}
			$tax_query[] = array(
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => $slugs,
			);
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		$paginate = self::truthy( $a['paginate'] );
		$args     = array(
			'post_type'      => $a['type'],
			'post_status'    => 'publish',
			'posts_per_page' => (int) $a['count'],
			'paged'          => max( 1, (int) $a['paged'] ),
			'orderby'        => sanitize_key( $a['orderby'] ),
			'order'          => ( 'ASC' === strtoupper( $a['order'] ) ) ? 'ASC' : 'DESC',
			// Found rows are only needed when we render pagination.
			'no_found_rows'  => ! $paginate,
		);

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		if ( '' !== $a['search'] ) {
			$search = sanitize_text_field( $a['search'] );
			if ( self::searchable_meta( $a['type'] ) ) {
				// Custom query var picked up by our posts_join / posts_where filters.
				$args['mcm_meta_search'] = $search;
			} else {
				$args['s'] = $search;
			}
		}
		// Sorting by a meta field (e.g. event date) requires a meta_key.
		if ( '' !== $a['meta_key'] ) {
			$args['meta_key'] = sanitize_key( $a['meta_key'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			if ( '' !== $a['meta_type'] ) {
				// WP_Query expects an upper-case SQL type from a fixed set.
				$allowed = array( 'CHAR', 'NUMERIC', 'DECIMAL', 'SIGNED', 'UNSIGNED', 'BINARY', 'DATE', 'DATETIME', 'TIME' );
				$mt      = strtoupper( sanitize_text_field( $a['meta_type'] ) );
				if ( in_array( $mt, $allowed, true ) ) {
					$args['meta_type'] = $mt;
				}
			}
		}

		return new WP_Query( apply_filters( 'mcm_query_args', $args, $a ) );
	}

	/*
	 * ----------------------------------------------------------------
	 * Meta-aware search: students by ID / PMDC No., staff by email, phone or
	 * designation. Registered once from the main plugin bootstrap.
	 * ----------------------------------------------------------------
	 */

	/**
	 * Register the SQL filters (meta search, optional-meta ordering) and facet cache invalidation.
	 */
	public static function register_hooks() {
		add_filter( 'posts_join', array( __CLASS__, 'filter_join' ), 10, 2 );
		add_filter( 'posts_where', array( __CLASS__, 'filter_where' ), 10, 2 );
		add_filter( 'posts_distinct', array( __CLASS__, 'filter_distinct' ), 10, 2 );
		add_filter( 'posts_clauses', array( __CLASS__, 'filter_order_clauses' ), 10, 2 );

		// Keep the cached filter facets honest.
		add_action( 'created_term', array( __CLASS__, 'flush_facet_cache' ), 10, 3 );
		add_action( 'edited_term', array( __CLASS__, 'flush_facet_cache' ), 10, 3 );
		add_action( 'delete_term', array( __CLASS__, 'flush_facet_cache' ), 10, 3 );
	}

	/**
	 * Ordering by an OPTIONAL meta value, without dropping posts that lack it.
	 *
	 * The obvious approaches are both wrong. A bare meta_key + orderby=meta_value
	 * INNER JOINs and silently hides every post missing the key. The
	 * OR (EXISTS / NOT EXISTS) meta_query trick keeps those posts, but WP_Meta_Query
	 * builds the EXISTS side's JOIN with no meta_key condition (the key is only
	 * applied in the WHERE), so for a post that matched via NOT EXISTS every one
	 * of its meta rows survives the join and GROUP BY collapses them to an
	 * arbitrary one. A publication with no year but a PMID of 39123456 then sorts
	 * as if its year were 39,123,456 — straight to the top.
	 *
	 * So we join the one key explicitly and COALESCE the missing side to a
	 * sentinel, which is both deterministic and ONLY_FULL_GROUP_BY-safe.
	 *
	 * @param array  $args      Query args to modify.
	 * @param string $meta_key  Meta key to order by.
	 * @param string $cast      SIGNED, DATE or CHAR.
	 * @param string $dir       ASC or DESC.
	 * @return array Modified args.
	 */
	public static function order_by_optional_meta( $args, $meta_key, $cast = 'SIGNED', $dir = 'DESC' ) {
		$args['mcm_order_meta'] = array(
			'key'  => $meta_key,
			'cast' => in_array( $cast, array( 'SIGNED', 'DATE', 'CHAR' ), true ) ? $cast : 'CHAR',
			'dir'  => ( 'ASC' === strtoupper( $dir ) ) ? 'ASC' : 'DESC',
		);
		return $args;
	}

	/**
	 * Apply the optional-meta ordering requested by order_by_optional_meta().
	 *
	 * @param array    $clauses SQL clauses.
	 * @param WP_Query $query The query.
	 */
	public static function filter_order_clauses( $clauses, $query ) {
		$cfg = $query->get( 'mcm_order_meta' );
		if ( empty( $cfg ) || empty( $cfg['key'] ) ) {
			return $clauses;
		}
		global $wpdb;

		$key  = $cfg['key'];
		$cast = $cfg['cast'];
		$dir  = $cfg['dir'];

		$clauses['join'] .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} AS mcm_ometa ON ( {$wpdb->posts}.ID = mcm_ometa.post_id AND mcm_ometa.meta_key = %s ) ",
			$key
		);

		// Sentinel sorts the missing rows last in either direction.
		if ( 'SIGNED' === $cast ) {
			$missing = ( 'DESC' === $dir ) ? '-999999999' : '999999999';
			$expr    = "CAST( COALESCE( NULLIF( mcm_ometa.meta_value, '' ), '{$missing}' ) AS SIGNED )";
		} elseif ( 'DATE' === $cast ) {
			$missing = ( 'DESC' === $dir ) ? '0000-01-01' : '9999-12-31';
			$expr    = "CAST( COALESCE( NULLIF( mcm_ometa.meta_value, '' ), '{$missing}' ) AS DATE )";
		} else {
			$missing = ( 'DESC' === $dir ) ? '' : 'zzzzzzzz';
			$expr    = "COALESCE( NULLIF( mcm_ometa.meta_value, '' ), '{$missing}' )";
		}

		$clauses['orderby'] = "{$expr} {$dir}, {$wpdb->posts}.post_date DESC";
		return $clauses;
	}

	/** Allowed meta keys for the meta-search JOIN (static whitelist, never user input). */
	private static function search_meta_whitelist() {
		// Single source of truth: the union of every CPT's searchable meta.
		$all = array();
		foreach ( self::meta_search_types() as $type ) {
			$all = array_merge( $all, self::searchable_meta( $type ) );
		}
		return array_values( array_unique( $all ) );
	}

	/**
	 * Join post meta for meta-aware search.
	 *
	 * @param string   $join  JOIN clause.
	 * @param WP_Query $query The query.
	 */
	public static function filter_join( $join, $query ) {
		if ( $query->get( 'mcm_meta_search' ) ) {
			global $wpdb;
			$join .= " LEFT JOIN {$wpdb->postmeta} AS mcm_smeta ON ( {$wpdb->posts}.ID = mcm_smeta.post_id ) ";
		}
		return $join;
	}

	/**
	 * Match the search term against the title and the type's searchable meta.
	 *
	 * @param string   $where WHERE clause.
	 * @param WP_Query $query The query.
	 */
	public static function filter_where( $where, $query ) {
		$term = $query->get( 'mcm_meta_search' );
		if ( ! $term ) {
			return $where;
		}
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $term ) . '%';

		// Scope the searchable keys to THIS query's post type, not the union of
		// all types. Without this, a student search could still match on a key
		// that is private for students but public for another type (e.g. email),
		// because the union carries it. Per-type keeps student search to the
		// safe identifiers only. Falls back to the union if the type is unknown.
		$pt   = $query->get( 'post_type' );
		$pt   = is_array( $pt ) ? ( $pt ? reset( $pt ) : '' ) : $pt;
		$keys = ( $pt && self::searchable_meta( $pt ) ) ? self::searchable_meta( $pt ) : self::search_meta_whitelist();
		// Build with real placeholders — standards-compliant and injection-proof.
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- {$placeholders} is a run of '%s' built from count( $keys ); every value is a prepare() replacement, passed in one array.
		$where .= $wpdb->prepare(
			" AND ( {$wpdb->posts}.post_title LIKE %s OR ( mcm_smeta.meta_key IN ( {$placeholders} ) AND mcm_smeta.meta_value LIKE %s ) ) ",
			array_merge( array( $like ), $keys, array( $like ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		return $where;
	}

	/**
	 * Avoid duplicate rows from the meta-search join.
	 *
	 * @param string   $distinct DISTINCT clause.
	 * @param WP_Query $query    The query.
	 */
	public static function filter_distinct( $distinct, $query ) {
		return $query->get( 'mcm_meta_search' ) ? 'DISTINCT' : $distinct;
	}

	/**
	 * Render the filter bar (search + a dropdown per filterable taxonomy).
	 *
	 * @param array $a Parsed attributes.
	 */
	public static function render_filters( $a ) {
		$map   = self::tax_map();
		$taxes = isset( $map[ $a['type'] ] ) ? $map[ $a['type'] ] : array();

		$placeholder = ( 'mcm_student' === $a['type'] )
			? __( 'Search by name, ID or PMDC No.…', 'medical-college-manager' )
			: __( 'Search by name, keyword…', 'medical-college-manager' );

		$scope = self::scope_terms( $a );

		ob_start();
		?>
		<div class="mcm-filters" data-type="<?php echo esc_attr( $a['type'] ); ?>">
			<input type="search" class="mcm-search" aria-label="<?php echo esc_attr( $placeholder ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" value="<?php echo esc_attr( $a['search'] ); ?>" />
			<?php
			foreach ( $taxes as $tax ) {
				// The locked taxonomy is fixed by the archive context — no dropdown.
				if ( $tax === $a['lock_tax'] ) {
					continue;
				}
				// Honour the author's pre-filter: hidden for one term, limited
				// to the chosen terms for several.
				$terms = self::visible_facet_terms( $tax, $scope );
				if ( empty( $terms ) ) {
					continue;
				}
				$tobj = get_taxonomy( $tax );
				if ( ! $tobj ) {
					continue;
				}
				?>
				<select class="mcm-filter-tax" data-tax="<?php echo esc_attr( $tax ); ?>" aria-label="<?php echo esc_attr( $tobj->labels->singular_name ); ?>">
					<option value=""><?php echo esc_html( $tobj->labels->all_items ); ?></option>
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
			}
			?>
			<button type="button" class="mcm-reset"><?php esc_html_e( 'Reset', 'medical-college-manager' ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Heading printed above a listing when the author gave no print_title.
	 *
	 * Falls back to the post type's own plural label, so enabling print on a
	 * new type needs no code here. The two named cases only exist because
	 * "Timetable" and "Students" read poorly as document headings.
	 *
	 * @param string $type Post type.
	 * @return string
	 */
	private static function default_print_title( $type ) {
		$named = apply_filters(
			'mcm_print_titles',
			array(
				'mcm_timetable' => __( 'Class Timetable', 'medical-college-manager' ),
				'mcm_student'   => __( 'Student List', 'medical-college-manager' ),
				'mcm_research'  => __( 'Research Projects', 'medical-college-manager' ),
				'mcm_policy'    => __( 'Institutional Policies', 'medical-college-manager' ),
			)
		);
		if ( isset( $named[ $type ] ) ) {
			return $named[ $type ];
		}
		$obj = get_post_type_object( $type );
		return $obj ? $obj->labels->name : __( 'Listing', 'medical-college-manager' );
	}

	/**
	 * Print-only header (branding + title). The filter summary, count and date
	 * line is filled in by JS at print time so it reflects the applied filters.
	 *
	 * @param array $a Parsed attributes.
	 */
	public static function print_header( $a ) {
		$title = '' !== $a['print_title'] ? $a['print_title'] : self::default_print_title( $a['type'] );
		$lh    = self::letterhead();
		ob_start();
		?>
		<div class="mcm-print-header" aria-hidden="true">
			<div class="mcm-print-letterhead">
				<?php if ( '' !== $lh['logo'] ) : ?>
					<img class="mcm-print-logo" src="<?php echo esc_url( $lh['logo'] ); ?>" alt="" />
				<?php endif; ?>
				<div class="mcm-print-org">
					<div class="mcm-print-brand"><?php echo esc_html( $lh['org'] ); ?></div>
					<?php if ( '' !== $lh['address'] ) : ?>
						<div class="mcm-print-address"><?php echo esc_html( $lh['address'] ); ?></div>
					<?php endif; ?>
				</div>
			</div>
			<div class="mcm-print-title"><?php echo esc_html( $title ); ?></div>
			<div class="mcm-print-meta"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Letterhead parts, with sensible fallbacks so a site that configures
	 * nothing still prints something official-looking: the site title stands in
	 * for the institution name, and the theme's custom logo (or the site icon)
	 * stands in for an explicit print logo.
	 *
	 * @return array{logo:string,org:string,address:string,footnote:string,signature:bool}
	 */
	public static function letterhead() {
		$s = MCM_Settings::get();

		$logo = isset( $s['print_logo'] ) ? trim( (string) $s['print_logo'] ) : '';
		if ( '' === $logo ) {
			$custom = get_theme_mod( 'custom_logo' );
			if ( $custom ) {
				$src = wp_get_attachment_image_src( $custom, 'full' );
				if ( $src ) {
					$logo = $src[0];
				}
			}
		}
		if ( '' === $logo && function_exists( 'get_site_icon_url' ) ) {
			$logo = (string) get_site_icon_url( 180 );
		}

		$org = isset( $s['print_org'] ) ? trim( (string) $s['print_org'] ) : '';
		if ( '' === $org ) {
			$org = (string) get_bloginfo( 'name' );
		}

		return array(
			'logo'      => $logo,
			'org'       => $org,
			'address'   => isset( $s['print_address'] ) ? trim( (string) $s['print_address'] ) : '',
			'footnote'  => isset( $s['print_footnote'] ) ? trim( (string) $s['print_footnote'] ) : '',
			'signature' => ! empty( $s['print_signature'] ),
		);
	}

	/**
	 * Print-only footer: an optional note, a signature block, and a slot the JS
	 * stamps with the generation time. Cloned into the print container after
	 * the results so it lands at the end of the document.
	 */
	public static function print_footer() {
		$lh = self::letterhead();
		ob_start();
		?>
		<div class="mcm-print-foot" aria-hidden="true">
			<?php if ( $lh['signature'] ) : ?>
				<div class="mcm-print-sign">
					<div class="mcm-print-sign-box">
						<span class="mcm-print-sign-line"></span>
						<span class="mcm-print-sign-label"><?php esc_html_e( 'Prepared by', 'medical-college-manager' ); ?></span>
					</div>
					<div class="mcm-print-sign-box">
						<span class="mcm-print-sign-line"></span>
						<span class="mcm-print-sign-label"><?php esc_html_e( 'Checked by', 'medical-college-manager' ); ?></span>
					</div>
					<div class="mcm-print-sign-box">
						<span class="mcm-print-sign-line"></span>
						<span class="mcm-print-sign-label"><?php esc_html_e( 'Principal / Head of Department', 'medical-college-manager' ); ?></span>
					</div>
				</div>
			<?php endif; ?>
			<div class="mcm-print-footline">
				<?php if ( '' !== $lh['footnote'] ) : ?>
					<span class="mcm-print-note"><?php echo esc_html( $lh['footnote'] ); ?></span>
				<?php endif; ?>
				<span class="mcm-print-gen"></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Terms for one filter facet, cached.
	 *
	 * Every filter bar and every sidebar runs one of these per taxonomy, on
	 * page load and again on each AJAX filter. Since the sidebar started
	 * rendering a facet for every taxonomy of a type, that is several
	 * uncached term queries per request for a list that changes maybe monthly.
	 * Cached in the object cache (persistent where the host provides one) and
	 * invalidated whenever a term is created, edited or deleted.
	 *
	 * @param string $tax Taxonomy name.
	 * @return WP_Term[]
	 */
	public static function facet_terms( $tax ) {
		$key   = 'mcm_facet_' . $tax;
		$terms = wp_cache_get( $key, 'medical-college-manager' );

		if ( false === $terms ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $tax,
					'hide_empty' => false,
				)
			);
			if ( is_wp_error( $terms ) ) {
				$terms = array();
			}
			wp_cache_set( $key, $terms, 'medical-college-manager', HOUR_IN_SECONDS );
		}

		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Drop a cached facet when its taxonomy changes.
	 *
	 * @param int    $term_id Term ID.
	 * @param int    $tt_id Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 */
	public static function flush_facet_cache( $term_id, $tt_id = 0, $taxonomy = '' ) {
		if ( $taxonomy ) {
			wp_cache_delete( 'mcm_facet_' . $taxonomy, 'medical-college-manager' );
		}
	}

	/**
	 * Is this URL a known oEmbed video provider we will let the server contact?
	 * Restricts oEmbed's server-side discovery (an SSRF vector) to YouTube/Vimeo.
	 *
	 * @param string $url URL.
	 */
	public static function is_embed_provider( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}
		$allow = apply_filters(
			'mcm_embed_provider_hosts',
			array( 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com' )
		);
		return in_array( strtolower( $host ), $allow, true );
	}

	/**
	 * Safe video embed markup for a URL: an oEmbed player for a known provider,
	 * a plain <video> for a self-hosted file, or '' when it is neither (the
	 * caller can then fall back to a plain link/button). Never fetches an
	 * arbitrary URL server-side.
	 *
	 * @param string $url        Video URL.
	 * @param string $wrap_class Wrapper CSS class.
	 * @return string
	 */
	public static function video_embed( $url, $wrap_class = 'mcm-video' ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		// Known provider (YouTube/Vimeo): build the player iframe directly from
		// the parsed video ID. We deliberately do NOT call wp_oembed_get() — that
		// makes a blocking outbound HTTP request on every render, adds ~1s of
		// latency, and silently returns nothing when the server cannot reach the
		// provider (Vimeo's endpoint fails outright; YouTube fails behind many
		// firewalls/proxies) — which is exactly why a set video would not show.
		// Emitting a fixed provider origin + sanitised ID keeps this SSRF-safe.
		$src = self::provider_embed_src( $url );
		if ( '' !== $src ) {
			return '<div class="' . esc_attr( $wrap_class ) . '"><iframe src="' . esc_url( $src )
				. '" title="' . esc_attr__( 'Video player', 'medical-college-manager' ) . '"'
				. ' width="560" height="315" loading="lazy" frameborder="0"'
				. ' allow="accelerated-sensors; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"'
				. ' referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>';
		}

		// Self-hosted / direct video file.
		if ( preg_match( '/\.(mp4|webm|ogg|ogv|mov|m4v)(\?.*)?$/i', $url ) ) {
			return '<div class="' . esc_attr( $wrap_class ) . '"><video controls playsinline preload="metadata" src="' . esc_url( $url ) . '"></video></div>';
		}
		return '';
	}

	/**
	 * Map a YouTube/Vimeo watch/share/embed URL to its canonical, privacy-
	 * friendly embed src — or '' when the URL is not a recognised provider link.
	 *
	 * Never contacts the URL: it only ever returns a hard-coded provider origin
	 * with a strictly sanitised video ID, so it is safe against SSRF and works
	 * with no outbound network. Handles every common YouTube form (watch?v=,
	 * youtu.be/, /embed/, /shorts/, /live/, /v/) and Vimeo form (vimeo.com/ID,
	 * player.vimeo.com/video/ID, channel/group paths).
	 *
	 * @param string $url Candidate URL.
	 * @return string Embed src, or '' if not a supported provider link.
	 */
	public static function provider_embed_src( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || ! self::is_embed_provider( $url ) ) {
			return '';
		}
		$host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );

		// ---- YouTube ----
		if ( false !== strpos( $host, 'youtu' ) ) {
			$id = '';
			if ( 'youtu.be' === $host ) {
				$id = trim( $path, '/' );
			} else {
				$q = array();
				parse_str( $query, $q );
				if ( ! empty( $q['v'] ) ) {
					$id = $q['v'];
				} elseif ( preg_match( '~/(?:embed|shorts|live|v)/([^/?#]+)~', $path, $m ) ) {
					$id = $m[1];
				}
			}
			$id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $id );
			// Standard youtube.com/embed is the most compatible player domain —
			// youtube-nocookie shows "Video unavailable" for some clips and is
			// blocked by some privacy tools. rel=0 keeps suggested videos on-topic.
			return '' !== $id ? 'https://www.youtube.com/embed/' . $id . '?rel=0' : '';
		}

		// ---- Vimeo ---- (supports unlisted videos, whose privacy hash rides in
		// the path as /ID/HASH or as a ?h=HASH query param).
		if ( preg_match( '~(?:/video/|/channels/[^/]+/|/groups/[^/]+/videos/|^/)(\d+)(?:/([A-Za-z0-9]+))?~', $path, $m ) ) {
			$src  = 'https://player.vimeo.com/video/' . $m[1];
			$hash = '';
			if ( ! empty( $m[2] ) ) {
				$hash = $m[2];
			} else {
				$vq = array();
				parse_str( $query, $vq );
				if ( ! empty( $vq['h'] ) ) {
					$hash = preg_replace( '/[^A-Za-z0-9]/', '', (string) $vq['h'] );
				}
			}
			return '' !== $hash ? $src . '?h=' . $hash : $src;
		}
		return '';
	}

	/**
	 * Full-width hero for an archive/landing page: the video if set (safe
	 * providers/files only), otherwise a static image, otherwise '' — matching
	 * the department hero so every banner across the site looks the same.
	 *
	 * @param string $video Video URL.
	 * @param string $image Image URL.
	 * @param string $alt   Image alt text.
	 * @return string
	 */
	public static function hero_banner( $video, $image, $alt = '' ) {
		$vid = self::video_embed( $video, 'mcm-hero mcm-hero--video mcm-wrap' );
		if ( '' !== $vid ) {
			return $vid;
		}
		$image = trim( (string) $image );
		if ( '' !== $image ) {
			return '<div class="mcm-hero mcm-hero--image mcm-wrap"><img src="' . esc_url( $image ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" /></div>';
		}
		return '';
	}

	/**
	 * May the current request list this post type?
	 *
	 * Publicly viewable types are always fine. A non-public type (students,
	 * which hold PMDC numbers and contact details) is only listable by someone
	 * who can already edit that type in wp-admin — so server-rendered
	 * shortcodes on a protected page keep working for staff, while an
	 * anonymous request for the same type is refused.
	 *
	 * @param string $type Post type name.
	 * @return bool
	 */
	public static function type_allowed( $type ) {
		if ( ! post_type_exists( $type ) ) {
			return false;
		}
		// This endpoint serves ONLY this plugin's own content types — never core
		// post/page or another plugin's CPT, even though those are viewable.
		// Keeps the public AJAX surface to exactly what the plugin renders.
		if ( ! array_key_exists( $type, MCM_CPT::post_types() ) ) {
			return false;
		}
		if ( is_post_type_viewable( $type ) ) {
			return true;
		}
		// Students are non-public, but every front-end view of them renders ONLY
		// safe fields — name, PMDC No., ID, department, program, batch, status —
		// searches only those, and is forced to the table renderer (see
		// render_results). So a public, safe student directory is permitted while
		// the sensitive fields (email, phone, DOB, address, guardian, blood
		// group) stay in wp-admin. Whether that safe list is PUBLIC is a
		// setting (College Manager → Settings → Public student directory).
		// Any OTHER non-public type still needs caps.
		if ( 'mcm_student' === $type ) {
			return self::students_visible();
		}
		$obj = get_post_type_object( $type );
		$cap = ( $obj && isset( $obj->cap->edit_posts ) ) ? $obj->cap->edit_posts : 'edit_posts';
		return current_user_can( $cap );
	}

	/**
	 * May the current visitor see the (safe-fields-only) student list?
	 *
	 * Yes when the "Public student directory" setting is on (the default,
	 * which keeps pre-1.30.2 behaviour); otherwise only for users who can
	 * manage student records in wp-admin.
	 *
	 * @return bool
	 */
	public static function students_visible() {
		if ( '1' === (string) MCM_Settings::value( 'public_students' ) ) {
			return true;
		}
		$obj = get_post_type_object( 'mcm_student' );
		$cap = ( $obj && isset( $obj->cap->edit_posts ) ) ? $obj->cap->edit_posts : 'edit_mcm_students';
		return current_user_can( $cap );
	}

	/**
	 * Sidebar filter for the directory layout: a search box (with button) plus
	 * one multi-select checkbox facet per taxonomy registered for the type —
	 * e.g. publications get Research Area, Department and Professor.
	 *
	 * A taxonomy locked by the archive context (a term page) is skipped, since
	 * every result already shares that term. Facets with no terms are skipped
	 * too, so a site that does not use a taxonomy never sees an empty box.
	 * The JS groups checkboxes by their data-tax, so facets combine as
	 * AND-between-taxonomies, OR-within-a-taxonomy.
	 *
	 * @param array $a Parsed attributes.
	 */
	public static function render_sidebar( $a ) {
		$map   = self::tax_map();
		$taxes = isset( $map[ $a['type'] ] ) ? $map[ $a['type'] ] : array();

		$placeholder = ( 'mcm_consultant' === $a['type'] )
			? __( 'Find a Consultant …', 'medical-college-manager' )
			: __( 'Search by name, keyword…', 'medical-college-manager' );

		$scope = self::scope_terms( $a );

		ob_start();
		?>
		<div class="mcm-dir-filters" data-type="<?php echo esc_attr( $a['type'] ); ?>">
			<div class="mcm-dir-search">
				<input type="search" class="mcm-search" aria-label="<?php echo esc_attr( $placeholder ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" value="<?php echo esc_attr( $a['search'] ); ?>" />
				<button type="button" class="mcm-search-btn"><?php esc_html_e( 'Search', 'medical-college-manager' ); ?></button>
			</div>
			<?php
			foreach ( $taxes as $tax ) {
				if ( ! $tax || $tax === $a['lock_tax'] ) {
					continue;
				}
				$terms = self::visible_facet_terms( $tax, $scope );
				if ( empty( $terms ) ) {
					continue;
				}
				$tobj = get_taxonomy( $tax );
				if ( ! $tobj ) {
					continue;
				}
				?>
				<div class="mcm-dir-facet">
					<h4 class="mcm-dir-facet-title"><?php echo esc_html( $tobj->labels->name ); ?></h4>
					<ul class="mcm-dir-checks">
						<?php foreach ( $terms as $term ) : ?>
							<li>
								<label>
									<input type="checkbox" class="mcm-filter-check" data-tax="<?php echo esc_attr( $tax ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" />
									<span><?php echo esc_html( $term->name ); ?></span>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php
			}
			?>
			<button type="button" class="mcm-reset mcm-dir-reset"><?php esc_html_e( 'Reset filters', 'medical-college-manager' ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	/*
	 * ----------------------------------------------------------------
	 * Publications
	 * ----------------------------------------------------------------
	 */

	/**
	 * Vancouver-style citation string (the medical standard):
	 * Authors. Title. Journal. Year;Volume(Issue):Pages.
	 *
	 * @param int $id Post ID.
	 * @return string Plain text (escape at output).
	 */
	public static function citation( $id ) {
		return self::build_citation( $id, true );
	}

	/**
	 * Assemble a citation from its parts.
	 *
	 * Built segment by segment (never by subtracting the title from the full
	 * string) so an empty title, or a title that also occurs in the journal
	 * name, cannot corrupt the output.
	 *
	 * @param int  $id         Publication ID.
	 * @param bool $with_title Include the title segment.
	 * @return string Plain text (escape at output).
	 */
	private static function build_citation( $id, $with_title ) {
		$journal = trim( (string) MCM_Safe::meta( $id, 'mcm_pub_journal' ) );
		$year    = trim( (string) MCM_Safe::meta( $id, 'mcm_pub_year' ) );
		$vol     = trim( (string) MCM_Safe::meta( $id, 'mcm_pub_volume' ) );
		$issue   = trim( (string) MCM_Safe::meta( $id, 'mcm_pub_issue' ) );
		$pages   = trim( (string) MCM_Safe::meta( $id, 'mcm_pub_pages' ) );

		$segments = array( trim( (string) MCM_Safe::meta( $id, 'mcm_pub_authors' ) ) );
		if ( $with_title ) {
			$segments[] = trim( (string) get_the_title( $id ) );
		}
		$segments[] = $journal;

		$out = '';
		foreach ( $segments as $segment ) {
			if ( '' !== $segment ) {
				$out .= rtrim( $segment, '. ' ) . '. ';
			}
		}

		// Tail segment, in the form year;volume(issue):pages.
		$tail = $year;
		if ( '' !== $vol ) {
			$tail .= ( '' !== $tail ? ';' : '' ) . $vol;
			if ( '' !== $issue ) {
				$tail .= '(' . $issue . ')';
			}
		}
		if ( '' !== $pages ) {
			$tail .= ( '' !== $tail ? ':' : '' ) . $pages;
		}
		if ( '' !== $tail ) {
			$out .= $tail . '.';
		}
		return trim( $out );
	}

	/**
	 * External links for a publication: DOI, PubMed, full text, PDF.
	 *
	 * @param int $id Post ID.
	 */
	private static function publication_links( $id ) {
		$links = array();
		$doi   = trim( (string) MCM_Safe::meta( $id, 'mcm_pub_doi' ) );
		$pmid  = trim( (string) MCM_Safe::meta( $id, 'mcm_pub_pmid' ) );
		$url   = trim( (string) MCM_Safe::meta( $id, 'mcm_pub_url' ) );
		$pdf   = trim( (string) MCM_Safe::meta( $id, 'mcm_pub_pdf' ) );

		if ( '' !== $doi ) {
			$doi_url = preg_match( '#^https?://#i', $doi ) ? $doi : 'https://doi.org/' . ltrim( $doi, '/' );
			$links[] = array( __( 'DOI', 'medical-college-manager' ), $doi_url );
		}
		if ( '' !== $pmid ) {
			if ( preg_match( '#^https?://#i', $pmid ) ) {
				$links[] = array( __( 'PubMed', 'medical-college-manager' ), $pmid );
			} else {
				// Accept "12345678", "PMID: 12345678" etc. Skip if no digits at
				// all, so we never emit a link to an empty PubMed record.
				$digits = preg_replace( '/[^0-9]/', '', $pmid );
				if ( '' !== $digits ) {
					$links[] = array( __( 'PubMed', 'medical-college-manager' ), 'https://pubmed.ncbi.nlm.nih.gov/' . $digits . '/' );
				}
			}
		}
		if ( '' !== $url ) {
			$links[] = array( __( 'Full text', 'medical-college-manager' ), self::external_url( $url ) );
		}
		if ( '' !== $pdf ) {
			$links[] = array( __( 'PDF', 'medical-college-manager' ), self::external_url( $pdf ) );
		}
		return $links;
	}

	/**
	 * Does this URL point somewhere other than this site?
	 *
	 * @param string $url URL.
	 */
	private static function is_external_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return false; // relative / same-site.
		}
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return strtolower( $host ) !== strtolower( (string) $home );
	}

	/**
	 * Make a pasted address safe to link. Editors routinely paste "example.com"
	 * or "www.example.com"; esc_url() would strip a schemeless value and leave a
	 * dead button, so assume https when no scheme is present.
	 *
	 * @param string $url URL.
	 */
	private static function external_url( $url ) {
		$url = trim( $url );
		if ( '' === $url || preg_match( '#^(https?:|//|/|\#)#i', $url ) ) {
			return $url;
		}
		return 'https://' . $url;
	}

	/**
	 * One citation row in the publications list.
	 *
	 * @param int $id Post ID.
	 */
	public static function publication_row( $id ) {
		$type = trim( (string) MCM_Safe::meta( $id, 'mcm_pub_type' ) );
		$year = trim( (string) MCM_Safe::meta( $id, 'mcm_pub_year' ) );
		// Case-insensitive: CSV imports supply "yes"/"YES"/"true" as freely as "Yes".
		$oa   = in_array( strtolower( trim( (string) MCM_Safe::meta( $id, 'mcm_pub_open_access' ) ) ), array( 'yes', 'y', '1', 'true' ), true );
		$link = get_permalink( $id );

		ob_start();
		?>
		<li class="mcm-pub-item">
			<div class="mcm-pub-top">
				<?php if ( '' !== $type ) : ?>
					<span class="mcm-pub-type"><?php echo esc_html( $type ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $year ) : ?>
					<span class="mcm-pub-year"><?php echo esc_html( $year ); ?></span>
				<?php endif; ?>
				<?php if ( $oa ) : ?>
					<span class="mcm-pub-oa"><?php esc_html_e( 'Open Access', 'medical-college-manager' ); ?></span>
				<?php endif; ?>
			</div>
			<p class="mcm-pub-cite">
				<a class="mcm-pub-title" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a>
				<span class="mcm-pub-meta"><?php echo esc_html( self::citation_without_title( $id ) ); ?></span>
			</p>
			<?php $links = self::publication_links( $id ); ?>
			<?php if ( $links ) : ?>
				<div class="mcm-pub-links">
					<?php foreach ( $links as $l ) : ?>
						<a class="mcm-pub-link" href="<?php echo esc_url( $l[1] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $l[0] ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</li>
		<?php
		return ob_get_clean();
	}

	/**
	 * Citation minus the title (the title is rendered as a link separately).
	 *
	 * @param int $id Post ID.
	 */
	private static function citation_without_title( $id ) {
		return self::build_citation( $id, false );
	}

	/**
	 * The publications list (ordered citation entries).
	 *
	 * @param WP_Query $query The query.
	 */
	public static function render_citation_list( $query ) {
		ob_start();
		if ( $query->have_posts() ) {
			echo '<ol class="mcm-pub-list">';
			while ( $query->have_posts() ) {
				$query->the_post();
				echo self::publication_row( get_the_ID() ); // phpcs:ignore
			}
			echo '</ol>';
		} else {
			echo '<p class="mcm-empty">' . esc_html__( 'No publications found.', 'medical-college-manager' ) . '</p>';
		}
		return ob_get_clean();
	}

	/**
	 * Directory card: photo, name, specialty, designation, department and button.
	 *
	 * @param int $id Post ID.
	 */
	public static function directory_card( $id ) {
		$type  = get_post_type( $id );
		$title = get_the_title( $id );
		$link  = is_post_type_viewable( $type ) ? get_permalink( $id ) : '';
		$thumb = get_the_post_thumbnail(
			$id,
			'medium',
			array(
				'class'   => 'mcm-dir-img',
				'loading' => 'lazy',
				'alt'     => esc_attr( $title ),
			)
		);
		$desig = MCM_Safe::meta( $id, 'mcm_designation' );
		$dept  = self::cached_term_names( $id, 'mcm_department' );
		// Specialty is what a visitor scans a consultant directory for, so it
		// belongs on the card under the photo, next to the name.
		$spec = self::cached_term_names( $id, 'mcm_specialty' );

		if ( 'mcm_consultant' === $type ) {
			$btn = __( 'Meet the Consultant', 'medical-college-manager' );
		} elseif ( 'mcm_team' === $type ) {
			$btn = __( 'View Profile', 'medical-college-manager' );
		} else {
			$btn = __( 'View details', 'medical-college-manager' );
		}

		ob_start();
		?>
		<article class="mcm-dir-card mcm-dir-card--<?php echo esc_attr( str_replace( 'mcm_', '', $type ) ); ?>">
			<div class="mcm-dir-card-media">
				<?php
				if ( $thumb ) {
					echo $thumb; // phpcs:ignore
				} else {
					echo self::default_avatar( 'mcm-dir-img', 'mcm-dir-card-ph' ); // phpcs:ignore
				}
				?>
			</div>
			<h3 class="mcm-dir-card-name">
				<?php if ( $link ) : ?>
					<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $title ); ?>
				<?php endif; ?>
			</h3>
			<?php if ( ! empty( $spec ) ) : ?>
				<div class="mcm-dir-card-spec"><?php echo esc_html( implode( ', ', $spec ) ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== $desig ) : ?>
				<div class="mcm-dir-card-desig"><?php echo esc_html( $desig ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $dept ) ) : ?>
				<div class="mcm-dir-card-dept"><?php echo esc_html( implode( ', ', $dept ) ); ?></div>
			<?php endif; ?>
			<?php if ( $link ) : ?>
				<a class="mcm-dir-card-btn" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $btn ); ?></a>
			<?php endif; ?>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render results in the requested view.
	 *
	 * @param array $a Parsed attributes.
	 */
	public static function render_results( $a ) {
		// Students are the only content with private fields. Whatever view is
		// requested — including via the public AJAX endpoint, whose params are
		// attacker-controlled — force the safe table renderer. This is what makes
		// grid/card photos and any future view unable to surface a student photo
		// or private field; the table shows only the safe columns.
		if ( isset( $a['type'] ) && 'mcm_student' === $a['type'] ) {
			$a['view'] = 'table';
		}

		$query = self::get_query( $a );

		ob_start();

		if ( 'table' === $a['view'] ) {
			echo self::render_table( $query, $a ); // phpcs:ignore
		} elseif ( 'citation' === $a['view'] ) {
			echo self::render_citation_list( $query ); // phpcs:ignore
		} elseif ( 'directory' === $a['view'] ) {
			// Directory = left sidebar + a main area whose presentation suits the
			// type: a table for timetable, citations for publications, profile
			// cards for people, info cards for everything else.
			if ( 'mcm_timetable' === $a['type'] ) {
				echo self::render_table( $query, $a ); // phpcs:ignore
			} elseif ( 'mcm_publication' === $a['type'] ) {
				echo self::render_citation_list( $query ); // phpcs:ignore
			} else {
				$people = in_array( $a['type'], array( 'mcm_consultant', 'mcm_team' ), true );
				$cls    = $people ? 'mcm-dir-grid' : 'mcm-grid';
				echo '<div class="' . esc_attr( $cls ) . '" style="--mcm-cols:' . max( 1, (int) $a['columns'] ) . ';">'; // phpcs:ignore
				if ( $query->have_posts() ) {
					while ( $query->have_posts() ) {
						$query->the_post();
						echo $people ? self::directory_card( get_the_ID() ) : self::card( get_the_ID() ); // phpcs:ignore
					}
				} else {
					echo '<p class="mcm-empty">' . esc_html__( 'No results found.', 'medical-college-manager' ) . '</p>';
				}
				echo '</div>';
			}
		} else {
			$is_list    = ( 'list' === $a['view'] );
			$wrap_class = $is_list ? 'mcm-list' : 'mcm-grid';
			$style      = $is_list ? '' : sprintf( ' style="--mcm-cols:%d;"', max( 1, (int) $a['columns'] ) );

			echo '<div class="' . esc_attr( $wrap_class ) . '"' . $style . '>'; // phpcs:ignore

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					echo $is_list ? self::list_item( get_the_ID() ) : self::card( get_the_ID() ); // phpcs:ignore
				}
			} else {
				echo '<p class="mcm-empty">' . esc_html__( 'No results found.', 'medical-college-manager' ) . '</p>';
			}
			echo '</div>';
		}

		if ( self::truthy( $a['paginate'] ) ) {
			echo self::render_pagination( $query, max( 1, (int) $a['paged'] ) ); // phpcs:ignore
		}

		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Numbered pagination for a paginated results query. Returns '' when there
	 * is only a single page.
	 *
	 * @param WP_Query $query The query.
	 * @param int      $current Current page.
	 */
	public static function render_pagination( $query, $current ) {
		$total = (int) $query->max_num_pages;
		if ( $total < 2 ) {
			return '';
		}
		$links = paginate_links(
			array(
				'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
				'format'    => '?paged=%#%',
				'current'   => max( 1, (int) $current ),
				'total'     => $total,
				'type'      => 'list',
				'mid_size'  => 1,
				'prev_text' => __( '&larr; Prev', 'medical-college-manager' ),
				'next_text' => __( 'Next &rarr;', 'medical-college-manager' ),
			)
		);
		return $links ? '<nav class="mcm-pagination" aria-label="' . esc_attr__( 'Pagination', 'medical-college-manager' ) . '">' . $links . '</nav>' : '';
	}

	/*
	 * ----------------------------------------------------------------
	 * Card / list / table renderers
	 * ----------------------------------------------------------------
	 */

	/**
	 * People types that get a default avatar when they have no featured image.
	 *
	 * @param string $type Post type.
	 */
	public static function is_person( $type ) {
		return in_array( $type, array( 'mcm_consultant', 'mcm_team', 'mcm_student' ), true );
	}

	/**
	 * Default avatar markup for a person with no featured image. Uses a custom
	 * image if the site provides one via the mcm_default_avatar_url filter,
	 * otherwise a built-in silhouette. $img_class styles the <img>; $ph_class
	 * styles the SVG placeholder box.
	 *
	 * @param string $img_class CSS class for the image.
	 * @param string $ph_class CSS class for the placeholder.
	 */
	public static function default_avatar( $img_class, $ph_class ) {
		// A branded placeholder (the GMC roundel) set in Settings wins; the
		// filter still overrides it for anyone doing something custom, and the
		// generic silhouette remains the last resort.
		$url = apply_filters( 'mcm_default_avatar_url', MCM_Settings::value( 'default_avatar' ) );
		if ( $url ) {
			return '<img class="' . esc_attr( $img_class ) . '" src="' . esc_url( $url ) . '" alt="" loading="lazy" />';
		}
		return '<span class="' . esc_attr( $ph_class ) . ' mcm-avatar" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 12.5a4.6 4.6 0 1 0-4.6-4.6 4.6 4.6 0 0 0 4.6 4.6zm0 2.3c-3.9 0-7.1 2-7.1 4.45V21h14.2v-1.75c0-2.45-3.2-4.45-7.1-4.45z"/></svg></span>';
	}

	/**
	 * Featured image, or (for people) a default avatar, or '' otherwise.
	 *
	 * @param int    $id Post ID.
	 * @param string $size Image size.
	 * @param string $img_class CSS class for the image.
	 * @param string $ph_class CSS class for the placeholder.
	 */
	private static function media_html( $id, $size, $img_class, $ph_class ) {
		$thumb = get_the_post_thumbnail(
			$id,
			$size,
			array(
				'class'   => $img_class,
				'loading' => 'lazy',
				'alt'     => esc_attr( get_the_title( $id ) ),
			)
		);
		if ( $thumb ) {
			return $thumb;
		}
		return self::is_person( get_post_type( $id ) ) ? self::default_avatar( $img_class, $ph_class ) : '';
	}

	/**
	 * Grid card for one entry.
	 *
	 * @param int $id Post ID.
	 */
	public static function card( $id ) {
		$type  = get_post_type( $id );
		$title = get_the_title( $id );
		// Only link to a front-end single when the type is publicly viewable
		// (students are private — render plain text instead of a 404 link).
		$link  = is_post_type_viewable( $type ) ? get_permalink( $id ) : '';
		$media = self::media_html( $id, 'large', 'mcm-card-img', 'mcm-card-ph' );

		ob_start();
		?>
		<article class="mcm-card mcm-card--<?php echo esc_attr( str_replace( 'mcm_', '', $type ) ); ?>">
			<?php if ( $media ) : ?>
				<?php if ( $link ) : ?>
					<a class="mcm-card-media" href="<?php echo esc_url( $link ); ?>"><?php echo $media; // phpcs:ignore ?></a>
				<?php else : ?>
					<span class="mcm-card-media"><?php echo $media; // phpcs:ignore ?></span>
				<?php endif; ?>
			<?php endif; ?>
			<div class="mcm-card-body">
				<h3 class="mcm-card-title">
					<?php if ( $link ) : ?>
						<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $title ); ?>
					<?php endif; ?>
				</h3>
				<?php echo self::meta_lines( $id, $type ); // phpcs:ignore ?>
				<?php
				$excerpt = has_excerpt( $id ) ? get_the_excerpt( $id ) : '';
				if ( $excerpt ) :
					?>
					<p class="mcm-card-excerpt"><?php echo esc_html( wp_trim_words( $excerpt, 22 ) ); ?></p>
				<?php endif; ?>
				<?php if ( $link ) : ?>
					<a class="mcm-card-link" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'View details', 'medical-college-manager' ); ?> &rarr;</a>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * List row for one entry.
	 *
	 * @param int $id Post ID.
	 */
	public static function list_item( $id ) {
		$type  = get_post_type( $id );
		$title = get_the_title( $id );
		$link  = is_post_type_viewable( $type ) ? get_permalink( $id ) : '';
		$media = self::media_html( $id, 'thumbnail', 'mcm-list-img', 'mcm-list-ph' );

		ob_start();
		?>
		<div class="mcm-list-item mcm-list-item--<?php echo esc_attr( str_replace( 'mcm_', '', $type ) ); ?>">
			<?php if ( $media ) : ?>
				<?php if ( $link ) : ?>
					<a class="mcm-list-media" href="<?php echo esc_url( $link ); ?>"><?php echo $media; // phpcs:ignore ?></a>
				<?php else : ?>
					<span class="mcm-list-media"><?php echo $media; // phpcs:ignore ?></span>
				<?php endif; ?>
			<?php endif; ?>
			<div class="mcm-list-body">
				<h3 class="mcm-list-title">
					<?php if ( $link ) : ?>
						<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $title ); ?>
					<?php endif; ?>
				</h3>
				<?php echo self::meta_lines( $id, $type ); // phpcs:ignore ?>
			</div>
			<?php if ( $link ) : ?>
			<a class="mcm-list-link" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'View', 'medical-college-manager' ); ?> &rarr;</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Column definitions for the table view, per CPT.
	 * Each column: array( label, kind, key ) where kind is
	 * title | meta | tax | time | date.
	 *
	 * @param string $type Post type.
	 */
	public static function table_columns( $type ) {
		$cols = array(
			'mcm_timetable'  => array(
				array( __( 'Day', 'medical-college-manager' ), 'meta', 'mcm_day' ),
				array( __( 'Time', 'medical-college-manager' ), 'time', '' ),
				array( __( 'Subject', 'medical-college-manager' ), 'meta', 'mcm_subject' ),
				array( __( 'Professor', 'medical-college-manager' ), 'tax', 'mcm_teacher' ),
				array( __( 'Department', 'medical-college-manager' ), 'tax', 'mcm_department' ),
				array( __( 'Room', 'medical-college-manager' ), 'meta', 'mcm_room' ),
				array( __( 'Session', 'medical-college-manager' ), 'tax', 'mcm_session' ),
			),
			'mcm_course'     => array(
				array( __( 'Code', 'medical-college-manager' ), 'meta', 'mcm_course_code' ),
				array( __( 'Course', 'medical-college-manager' ), 'title', '' ),
				array( __( 'Department', 'medical-college-manager' ), 'tax', 'mcm_department' ),
				array( __( 'Professor', 'medical-college-manager' ), 'tax', 'mcm_teacher' ),
				array( __( 'Credits', 'medical-college-manager' ), 'meta', 'mcm_credit_hours', 'center' ),
				array( __( 'Semester', 'medical-college-manager' ), 'meta', 'mcm_semester' ),
			),
			'mcm_admission'  => array(
				array( __( 'Program', 'medical-college-manager' ), 'title', '' ),
				array( __( 'Opens', 'medical-college-manager' ), 'date', 'mcm_open_date' ),
				array( __( 'Closes', 'medical-college-manager' ), 'date', 'mcm_close_date' ),
				array( __( 'Department', 'medical-college-manager' ), 'tax', 'mcm_department' ),
				array( __( 'Fee', 'medical-college-manager' ), 'meta', 'mcm_fee' ),
			),
			'mcm_consultant' => array(
				array( __( 'Name', 'medical-college-manager' ), 'title', '' ),
				array( __( 'Designation', 'medical-college-manager' ), 'meta', 'mcm_designation' ),
				array( __( 'Department', 'medical-college-manager' ), 'tax', 'mcm_department' ),
				array( __( 'Specialty', 'medical-college-manager' ), 'tax', 'mcm_specialty' ),
				array( __( 'OPD', 'medical-college-manager' ), 'meta', 'mcm_opd_days' ),
			),
			'mcm_team'       => array(
				array( __( 'Name', 'medical-college-manager' ), 'title', '' ),
				array( __( 'Designation', 'medical-college-manager' ), 'meta', 'mcm_designation' ),
				array( __( 'Department', 'medical-college-manager' ), 'tax', 'mcm_department' ),
				array( __( 'Specialty', 'medical-college-manager' ), 'tax', 'mcm_specialty' ),
			),
			'mcm_program'    => array(
				array( __( 'Program', 'medical-college-manager' ), 'title', '' ),
				array( __( 'Level', 'medical-college-manager' ), 'meta', 'mcm_degree_level' ),
				array( __( 'Duration', 'medical-college-manager' ), 'meta', 'mcm_duration' ),
				array( __( 'Department', 'medical-college-manager' ), 'tax', 'mcm_department' ),
				array( __( 'Fees', 'medical-college-manager' ), 'meta', 'mcm_fees' ),
				array( __( 'Seats', 'medical-college-manager' ), 'meta', 'mcm_intake' ),
			),
			'mcm_event'      => array(
				array( __( 'Event', 'medical-college-manager' ), 'title', '' ),
				array( __( 'Date', 'medical-college-manager' ), 'date', 'mcm_event_date' ),
				array( __( 'Time', 'medical-college-manager' ), 'time', '' ),
				array( __( 'Venue', 'medical-college-manager' ), 'meta', 'mcm_venue' ),
				array( __( 'Department', 'medical-college-manager' ), 'tax', 'mcm_department' ),
			),
			// PUBLIC-SAFE columns only. Contact details (email, phone), DOB,
			// address, blood group, guardian and gender are deliberately absent —
			// they are personal data and live only in wp-admin. Do not add them
			// here: this table is reachable on a public page.
			'mcm_student'    => array(
				array( __( 'Name', 'medical-college-manager' ), 'title', '' ),
				array( __( 'Student ID', 'medical-college-manager' ), 'meta', 'mcm_student_id' ),
				array( __( 'PMDC No.', 'medical-college-manager' ), 'meta', 'mcm_roll_no' ),
				array( __( 'Level', 'medical-college-manager' ), 'tax', 'mcm_level' ),
				array( __( 'Program', 'medical-college-manager' ), 'meta', 'mcm_program' ),
				array( __( 'Department', 'medical-college-manager' ), 'tax', 'mcm_department' ),
				array( __( 'Batch', 'medical-college-manager' ), 'meta', 'mcm_batch' ),
				array( __( 'Status', 'medical-college-manager' ), 'meta', 'mcm_status' ),
			),
		);

		if ( isset( $cols[ $type ] ) ) {
			return $cols[ $type ];
		}
		// Generic fallback.
		return array(
			array( __( 'Title', 'medical-college-manager' ), 'title', '' ),
		);
	}

	/**
	 * Table view for a query.
	 *
	 * @param WP_Query $query The query.
	 * @param array    $a     Parsed attributes.
	 */
	public static function render_table( $query, $a ) {
		$cols = self::table_columns( $a['type'] );

		ob_start();
		echo '<div class="mcm-table-wrap"><table class="mcm-table">';
		echo '<thead><tr>';
		foreach ( $cols as $col ) {
			$cls = isset( $col[3] ) ? ' class="mcm-col-' . esc_attr( $col[3] ) . '"' : '';
			echo '<th scope="col"' . $cls . '>' . esc_html( $col[0] ) . '</th>'; // phpcs:ignore
		}
		echo '</tr></thead><tbody>';

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$id = get_the_ID();
				echo '<tr>';
				foreach ( $cols as $col ) {
					$cls = isset( $col[3] ) ? ' class="mcm-col-' . esc_attr( $col[3] ) . '"' : '';
					echo '<td data-label="' . esc_attr( $col[0] ) . '"' . $cls . '>' . esc_html( self::cell_value( $id, $col ) ) . '</td>'; // phpcs:ignore
				}
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="' . count( $cols ) . '" class="mcm-empty">' . esc_html__( 'No results found.', 'medical-college-manager' ) . '</td></tr>';
		}

		echo '</tbody></table></div>';
		return ob_get_clean();
	}

	/**
	 * Value of one table cell.
	 *
	 * @param int   $id  Post ID.
	 * @param array $col Column definition.
	 */
	private static function cell_value( $id, $col ) {
		list( $label, $kind, $key ) = $col;
		switch ( $kind ) {
			case 'title':
				return get_the_title( $id );
			case 'meta':
				return (string) MCM_Safe::meta( $id, $key );
			case 'date':
				return self::fmt_date( MCM_Safe::meta( $id, $key ) );
			case 'time':
				$start = MCM_Safe::meta( $id, 'mcm_start_time' );
				$end   = MCM_Safe::meta( $id, 'mcm_end_time' );
				return trim( $start . ( $end ? ' – ' . $end : '' ) );
			case 'tax':
				return implode( ', ', self::cached_term_names( $id, $key ) );
		}
		return '';
	}

	/*
	 * ----------------------------------------------------------------
	 * Meta line output
	 * ----------------------------------------------------------------
	 */

	/**
	 * The key facts shown on a card or list row, per content type.
	 *
	 * @param int    $id   Post ID.
	 * @param string $type Post type.
	 */
	public static function meta_lines( $id, $type ) {
		$rows = array();

		switch ( $type ) {
			case 'mcm_timetable':
				$rows[] = self::meta_pair( __( 'Day', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_day' ) );
				$rows[] = self::time_range( $id );
				$rows[] = self::meta_pair( __( 'Subject', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_subject' ) );
				$rows[] = self::tax_pair( __( 'Professor', 'medical-college-manager' ), $id, 'mcm_teacher' );
				$rows[] = self::meta_pair( __( 'Room', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_room' ) );
				break;

			case 'mcm_program':
				$rows[] = self::meta_pair( __( 'Level', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_degree_level' ) );
				$rows[] = self::meta_pair( __( 'Duration', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_duration' ) );
				$rows[] = self::meta_pair( __( 'Seats', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_intake' ) );
				break;

			case 'mcm_course':
				$rows[] = self::meta_pair( __( 'Code', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_course_code' ) );
				$rows[] = self::tax_pair( __( 'Dept', 'medical-college-manager' ), $id, 'mcm_department' );
				$rows[] = self::tax_pair( __( 'Professor', 'medical-college-manager' ), $id, 'mcm_teacher' );
				$rows[] = self::meta_pair( __( 'Credits', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_credit_hours' ) );
				break;

			case 'mcm_event':
				$rows[] = self::meta_pair( __( 'Date', 'medical-college-manager' ), self::fmt_date( MCM_Safe::meta( $id, 'mcm_event_date' ) ) );
				$rows[] = self::time_range( $id );
				$rows[] = self::meta_pair( __( 'Venue', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_venue' ) );
				break;

			case 'mcm_consultant':
				$rows[] = self::meta_pair( '', MCM_Safe::meta( $id, 'mcm_designation' ) );
				$rows[] = self::tax_pair( __( 'Dept', 'medical-college-manager' ), $id, 'mcm_department' );
				$rows[] = self::tax_pair( __( 'Specialty', 'medical-college-manager' ), $id, 'mcm_specialty' );
				$rows[] = self::meta_pair( __( 'OPD', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_opd_days' ) );
				break;

			case 'mcm_team':
				$rows[] = self::meta_pair( '', MCM_Safe::meta( $id, 'mcm_designation' ) );
				$rows[] = self::tax_pair( __( 'Dept', 'medical-college-manager' ), $id, 'mcm_department' );
				$rows[] = self::tax_pair( __( 'Specialty', 'medical-college-manager' ), $id, 'mcm_specialty' );
				break;

			case 'mcm_admission':
				$rows[] = self::meta_pair( __( 'Program', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_program_name' ) );
				$rows[] = self::meta_pair( __( 'Opens', 'medical-college-manager' ), self::fmt_date( MCM_Safe::meta( $id, 'mcm_open_date' ) ) );
				$rows[] = self::meta_pair( __( 'Closes', 'medical-college-manager' ), self::fmt_date( MCM_Safe::meta( $id, 'mcm_close_date' ) ) );
				break;

			case 'mcm_notice':
				$rows[] = self::tax_pair( __( 'Category', 'medical-college-manager' ), $id, 'mcm_notice_cat' );
				$rows[] = self::meta_pair( __( 'Priority', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_notice_priority' ) );
				$rows[] = self::meta_pair( __( 'Date', 'medical-college-manager' ), self::fmt_date( MCM_Safe::meta( $id, 'mcm_notice_date' ) ) );
				break;

			case 'mcm_tender':
				$rows[] = self::meta_pair( __( 'Ref', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_tender_ref' ) );
				$rows[] = self::tax_pair( __( 'Category', 'medical-college-manager' ), $id, 'mcm_tender_cat' );
				$rows[] = self::meta_pair( __( 'Deadline', 'medical-college-manager' ), self::fmt_date( MCM_Safe::meta( $id, 'mcm_tender_close' ) ) );
				break;

			case 'mcm_research':
				$rows[] = self::meta_pair( __( 'Supervisor / Principal Investigator', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_res_pi' ) );
				$rows[] = self::meta_pair( __( 'Status', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_res_status' ) );
				$rows[] = self::tax_pair( __( 'Research Area', 'medical-college-manager' ), $id, 'mcm_research_area' );
				break;

			case 'mcm_policy':
				$rows[] = self::tax_pair( __( 'Category', 'medical-college-manager' ), $id, 'mcm_policy_cat' );
				$rows[] = self::meta_pair( __( 'Issued By', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_policy_issuer' ) );
				$rows[] = self::meta_pair( __( 'Effective', 'medical-college-manager' ), self::fmt_date( MCM_Safe::meta( $id, 'mcm_policy_date' ) ) );
				break;

			case 'mcm_publication':
				$rows[] = self::meta_pair( __( 'Journal', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_pub_journal' ) );
				$rows[] = self::meta_pair( __( 'Year', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_pub_year' ) );
				$rows[] = self::tax_pair( __( 'Research Area', 'medical-college-manager' ), $id, 'mcm_research_area' );
				break;

			case 'mcm_scholarship':
				$rows[] = self::tax_pair( __( 'Type', 'medical-college-manager' ), $id, 'mcm_scholarship_cat' );
				$rows[] = self::meta_pair( __( 'Amount', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_scholarship_amount' ) );
				$rows[] = self::meta_pair( __( 'Deadline', 'medical-college-manager' ), self::fmt_date( MCM_Safe::meta( $id, 'mcm_scholarship_deadline' ) ) );
				break;

			case 'mcm_student':
				$rows[] = self::meta_pair( __( 'ID', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_student_id' ) );
				$rows[] = self::meta_pair( __( 'PMDC No.', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_roll_no' ) );
				$rows[] = self::tax_pair( __( 'Level', 'medical-college-manager' ), $id, 'mcm_level' );
				$rows[] = self::meta_pair( __( 'Program', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_program' ) );
				$rows[] = self::tax_pair( __( 'Dept', 'medical-college-manager' ), $id, 'mcm_department' );
				$rows[] = self::meta_pair( __( 'Batch', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_batch' ) );
				$rows[] = self::meta_pair( __( 'Status', 'medical-college-manager' ), MCM_Safe::meta( $id, 'mcm_status' ) );
				break;
		}

		$rows = array_filter( $rows );
		if ( empty( $rows ) ) {
			return '';
		}
		return '<div class="mcm-meta">' . implode( '', $rows ) . '</div>';
	}

	/**
	 * One "Label: value" line (empty when the value is empty).
	 *
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 */
	private static function meta_pair( $label, $value ) {
		if ( '' === $value || null === $value ) {
			return '';
		}
		$label_html = $label ? '<span class="mcm-meta-label">' . esc_html( $label ) . ':</span> ' : '';
		return '<span class="mcm-meta-line">' . $label_html . '<span class="mcm-meta-value">' . esc_html( $value ) . '</span></span>';
	}

	/**
	 * One "Label: term, term" line from a taxonomy.
	 *
	 * @param string $label Label.
	 * @param int    $id    Post ID.
	 * @param string $tax   Taxonomy name.
	 */
	private static function tax_pair( $label, $id, $tax ) {
		$names = self::cached_term_names( $id, $tax );
		if ( empty( $names ) ) {
			return '';
		}
		return self::meta_pair( $label, implode( ', ', $names ) );
	}

	/**
	 * Start and end time line.
	 *
	 * @param int $id Post ID.
	 */
	private static function time_range( $id ) {
		$start = MCM_Safe::meta( $id, 'mcm_start_time' );
		$end   = MCM_Safe::meta( $id, 'mcm_end_time' );
		if ( ! $start && ! $end ) {
			return '';
		}
		$val = trim( $start . ( $end ? ' – ' . $end : '' ) );
		return self::meta_pair( __( 'Time', 'medical-college-manager' ), $val );
	}

	/**
	 * A stored date for display, in the site's date format.
	 *
	 * Reads every accepted input form (Y-m-d, d/m/Y…), so older non-ISO values
	 * still display correctly. A value that is not a date at all shows as
	 * "Date not set" (the edit screen flags it) instead of raw text.
	 *
	 * @param mixed $raw Stored value.
	 * @return string
	 */
	public static function fmt_date( $raw ) {
		$raw = trim( MCM_Safe::str( $raw ) );
		if ( '' === $raw ) {
			return '';
		}
		$iso = MCM_Meta::parse_date( $raw );
		if ( false === $iso ) {
			return __( 'Date not set', 'medical-college-manager' );
		}
		$ts = strtotime( $iso . ' 00:00:00' );
		return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : $iso;
	}

	/**
	 * Term names for a post, read from the object-term cache that WP_Query
	 * already primed (get_the_terms), NOT a fresh per-row query
	 * (wp_get_post_terms). In a listing loop this is the difference between one
	 * DB query per row per taxonomy and zero extra queries.
	 *
	 * @param int    $id Post ID.
	 * @param string $tax Taxonomy name.
	 * @return string[] Term names (empty array if none / error).
	 */
	public static function cached_term_names( $id, $tax ) {
		$terms = get_the_terms( $id, $tax );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}
		return wp_list_pluck( $terms, 'name' );
	}

	/**
	 * Comma-joined term names for a taxonomy (empty string if none).
	 *
	 * @param int    $id Post ID.
	 * @param string $tax Taxonomy name.
	 */
	private static function tax_names( $id, $tax ) {
		return implode( ', ', self::cached_term_names( $id, $tax ) );
	}

	/*
	 * ----------------------------------------------------------------
	 * Single / detail
	 * ----------------------------------------------------------------
	 */

	/**
	 * Elegant, type-specific details panel for a single CPT: a heading, an
	 * info grid of the relevant fields, and action buttons (Apply / Register /
	 * Call / Email / LinkedIn). Returns '' when there's nothing to show.
	 *
	 * @param int    $id Post ID.
	 * @param string $type Post type.
	 */
	public static function single_details( $id, $type ) {
		$rows    = array(); // label => value.
		$actions = array(); // [ label, url, primary(bool) ].
		$heading = '';

		switch ( $type ) {
			case 'mcm_consultant':
			case 'mcm_team':
				$heading = __( 'Profile', 'medical-college-manager' );
				$rows[ __( 'Designation', 'medical-college-manager' ) ]    = MCM_Safe::meta( $id, 'mcm_designation' );
				$rows[ __( 'Department', 'medical-college-manager' ) ]     = self::tax_names( $id, 'mcm_department' );
				$rows[ __( 'Specialty', 'medical-college-manager' ) ]      = self::tax_names( $id, 'mcm_specialty' );
				$rows[ __( 'Qualifications', 'medical-college-manager' ) ] = MCM_Safe::meta( $id, 'mcm_qualifications' );
				$rows[ __( 'Experience', 'medical-college-manager' ) ]     = MCM_Safe::meta( $id, 'mcm_experience' );
				if ( 'mcm_consultant' === $type ) {
					$rows[ __( 'OPD Days', 'medical-college-manager' ) ]   = MCM_Safe::meta( $id, 'mcm_opd_days' );
					$rows[ __( 'OPD Timing', 'medical-college-manager' ) ] = MCM_Safe::meta( $id, 'mcm_opd_time' );
				}
				$rows[ __( 'Research Interests', 'medical-college-manager' ) ] = self::tax_names( $id, 'mcm_research_area' );
				$phone    = MCM_Safe::meta( $id, 'mcm_phone' );
				$email    = MCM_Safe::meta( $id, 'mcm_email' );
				$linkedin = MCM_Safe::meta( $id, 'mcm_linkedin' );
				$orcid    = MCM_Safe::meta( $id, 'mcm_orcid' );
				$scholar  = MCM_Safe::meta( $id, 'mcm_scholar' );
				$pubmed   = MCM_Safe::meta( $id, 'mcm_pubmed' );
				if ( $phone ) {
					$actions[] = array( __( 'Call', 'medical-college-manager' ), 'tel:' . $phone, false );
				}
				if ( $email ) {
					$actions[] = array( __( 'Email', 'medical-college-manager' ), 'mailto:' . $email, false );
				}
				if ( $linkedin ) {
					$actions[] = array( __( 'LinkedIn', 'medical-college-manager' ), $linkedin, false );
				}
				if ( $orcid ) {
					$actions[] = array( __( 'ORCID', 'medical-college-manager' ), $orcid, false );
				}
				if ( $scholar ) {
					$actions[] = array( __( 'Google Scholar', 'medical-college-manager' ), $scholar, false );
				}
				if ( $pubmed ) {
					$actions[] = array( __( 'PubMed', 'medical-college-manager' ), $pubmed, false );
				}
				break;

			case 'mcm_course':
				$heading = __( 'Course Details', 'medical-college-manager' );
				$rows[ __( 'Course Code', 'medical-college-manager' ) ]  = MCM_Safe::meta( $id, 'mcm_course_code' );
				$rows[ __( 'Department', 'medical-college-manager' ) ]   = self::tax_names( $id, 'mcm_department' );
				$rows[ __( 'Professor', 'medical-college-manager' ) ]    = self::tax_names( $id, 'mcm_teacher' );
				$rows[ __( 'Session', 'medical-college-manager' ) ]      = self::tax_names( $id, 'mcm_session' );
				$rows[ __( 'Credit Hours', 'medical-college-manager' ) ] = MCM_Safe::meta( $id, 'mcm_credit_hours' );
				$rows[ __( 'Semester', 'medical-college-manager' ) ]     = MCM_Safe::meta( $id, 'mcm_semester' );
				$rows[ __( 'Course Lead', 'medical-college-manager' ) ]  = MCM_Safe::meta( $id, 'mcm_course_lead' );
				break;

			case 'mcm_program':
				$heading = __( 'Program Details', 'medical-college-manager' );
				$rows[ __( 'Degree Level', 'medical-college-manager' ) ]   = MCM_Safe::meta( $id, 'mcm_degree_level' );
				$rows[ __( 'Duration', 'medical-college-manager' ) ]       = MCM_Safe::meta( $id, 'mcm_duration' );
				$rows[ __( 'Department', 'medical-college-manager' ) ]     = self::tax_names( $id, 'mcm_department' );
				$rows[ __( 'Fees', 'medical-college-manager' ) ]           = MCM_Safe::meta( $id, 'mcm_fees' );
				$rows[ __( 'Intake / Seats', 'medical-college-manager' ) ] = MCM_Safe::meta( $id, 'mcm_intake' );
				$apply = MCM_Safe::meta( $id, 'mcm_apply_url' );
				if ( $apply ) {
					$actions[] = array( __( 'Apply / More Info', 'medical-college-manager' ), $apply, true );
				}
				break;

			case 'mcm_event':
				$heading = __( 'Event Details', 'medical-college-manager' );
				$rows[ __( 'Date', 'medical-college-manager' ) ] = self::fmt_date( MCM_Safe::meta( $id, 'mcm_event_date' ) );
				$start = MCM_Safe::meta( $id, 'mcm_start_time' );
				$end   = MCM_Safe::meta( $id, 'mcm_end_time' );
				$rows[ __( 'Time', 'medical-college-manager' ) ]       = trim( $start . ( $end ? ' – ' . $end : '' ) );
				$rows[ __( 'Venue', 'medical-college-manager' ) ]      = MCM_Safe::meta( $id, 'mcm_venue' );
				$rows[ __( 'Organizer', 'medical-college-manager' ) ]  = MCM_Safe::meta( $id, 'mcm_organizer' );
				$rows[ __( 'Department', 'medical-college-manager' ) ] = self::tax_names( $id, 'mcm_department' );
				$reg = MCM_Safe::meta( $id, 'mcm_event_url' );
				if ( $reg ) {
					$actions[] = array( __( 'Register', 'medical-college-manager' ), $reg, true );
				}
				// Live stream: a "Watch Live" button whenever a stream URL is set
				// (the embedded player itself is added above the content by
				// MCM_Templates::single_meta). Shown only when a URL exists.
				$stream = MCM_Safe::meta( $id, 'mcm_event_stream' );
				if ( $stream ) {
					$actions[] = array( __( 'Watch Live', 'medical-college-manager' ), $stream, ! $reg, array( 'newtab' => true ) );
				}
				break;

			case 'mcm_admission':
				$heading = __( 'Admission Details', 'medical-college-manager' );
				$rows[ __( 'Program', 'medical-college-manager' ) ]         = MCM_Safe::meta( $id, 'mcm_program_name' );
				$rows[ __( 'Department', 'medical-college-manager' ) ]      = self::tax_names( $id, 'mcm_department' );
				$rows[ __( 'Opens', 'medical-college-manager' ) ]           = self::fmt_date( MCM_Safe::meta( $id, 'mcm_open_date' ) );
				$rows[ __( 'Closes', 'medical-college-manager' ) ]          = self::fmt_date( MCM_Safe::meta( $id, 'mcm_close_date' ) );
				$rows[ __( 'Eligibility', 'medical-college-manager' ) ]     = MCM_Safe::meta( $id, 'mcm_eligibility' );
				$rows[ __( 'Application Fee', 'medical-college-manager' ) ] = MCM_Safe::meta( $id, 'mcm_fee' );
				$apply = MCM_Safe::meta( $id, 'mcm_apply_url' );
				if ( $apply ) {
					$actions[] = array( __( 'Apply Now', 'medical-college-manager' ), $apply, true );
				}
				break;

			case 'mcm_tender':
				$heading = __( 'Tender Details', 'medical-college-manager' );
				$rows[ __( 'Reference No.', 'medical-college-manager' ) ]       = MCM_Safe::meta( $id, 'mcm_tender_ref' );
				$rows[ __( 'Category', 'medical-college-manager' ) ]            = self::tax_names( $id, 'mcm_tender_cat' );
				$rows[ __( 'Department', 'medical-college-manager' ) ]          = self::tax_names( $id, 'mcm_department' );
				$rows[ __( 'Published', 'medical-college-manager' ) ]           = self::fmt_date( MCM_Safe::meta( $id, 'mcm_tender_open' ) );
				$rows[ __( 'Submission Deadline', 'medical-college-manager' ) ] = self::fmt_date( MCM_Safe::meta( $id, 'mcm_tender_close' ) );
				$rows[ __( 'Bid Opening', 'medical-college-manager' ) ]         = self::fmt_date( MCM_Safe::meta( $id, 'mcm_tender_bid' ) );
				$rows[ __( 'Document Fee', 'medical-college-manager' ) ]        = MCM_Safe::meta( $id, 'mcm_tender_fee' );
				$rows[ __( 'Earnest Money', 'medical-college-manager' ) ]       = MCM_Safe::meta( $id, 'mcm_tender_earnest' );
				$rows[ __( 'Eligibility', 'medical-college-manager' ) ]         = MCM_Safe::meta( $id, 'mcm_tender_eligibility' );
				$doc    = MCM_Safe::meta( $id, 'mcm_tender_doc' );
				$portal = MCM_Safe::meta( $id, 'mcm_tender_apply' );
				if ( $doc ) {
					$actions[] = array( __( 'Download Tender Document', 'medical-college-manager' ), $doc, true );
				}
				if ( $portal ) {
					$actions[] = array( __( 'Submit / Portal', 'medical-college-manager' ), $portal, ! $doc );
				}
				break;

			case 'mcm_publication':
				$heading = __( 'Publication Details', 'medical-college-manager' );
				$rows[ __( 'Citation', 'medical-college-manager' ) ]                            = self::citation( $id );
				$rows[ __( 'Authors', 'medical-college-manager' ) ]                             = MCM_Safe::meta( $id, 'mcm_pub_authors' );
				$rows[ __( 'Journal', 'medical-college-manager' ) ]                             = MCM_Safe::meta( $id, 'mcm_pub_journal' );
				$rows[ __( 'Year', 'medical-college-manager' ) ]                                = MCM_Safe::meta( $id, 'mcm_pub_year' );
				$rows[ __( 'Type', 'medical-college-manager' ) ]                                = MCM_Safe::meta( $id, 'mcm_pub_type' );
				$rows[ __( 'Research Area', 'medical-college-manager' ) ]                       = self::tax_names( $id, 'mcm_research_area' );
				$rows[ __( 'Department', 'medical-college-manager' ) ]                          = self::tax_names( $id, 'mcm_department' );
				$rows[ __( 'Professor', 'medical-college-manager' ) ]                           = self::tax_names( $id, 'mcm_teacher' );
				$rows[ __( 'Supervisor / Principal Investigator', 'medical-college-manager' ) ] = MCM_Safe::meta( $id, 'mcm_pub_supervisor' );
				$rows[ __( 'Assisting Students', 'medical-college-manager' ) ]                  = MCM_Safe::meta( $id, 'mcm_pub_fellows' );
				$rows[ __( 'Impact Factor', 'medical-college-manager' ) ]                       = MCM_Safe::meta( $id, 'mcm_pub_impact' );
				$rows[ __( 'Indexing', 'medical-college-manager' ) ]                            = MCM_Safe::meta( $id, 'mcm_pub_indexing' );
				foreach ( self::publication_links( $id ) as $i => $l ) {
					$actions[] = array( $l[0], $l[1], 0 === $i );
				}
				// Paper management: the PDF is viewable via the link above; add
				// an explicit Download alongside it.
				$pdf = MCM_Safe::meta( $id, 'mcm_pub_pdf' );
				if ( $pdf ) {
					$actions[] = array( __( 'Download', 'medical-college-manager' ), $pdf, false, array( 'download' => true ) );
				}
				break;

			case 'mcm_research':
				$heading = __( 'Research Details', 'medical-college-manager' );
				$rows[ __( 'Supervisor / Principal Investigator', 'medical-college-manager' ) ] = MCM_Safe::meta( $id, 'mcm_res_pi' );
				$rows[ __( 'Status', 'medical-college-manager' ) ]                              = MCM_Safe::meta( $id, 'mcm_res_status' );
				$rows[ __( 'Started', 'medical-college-manager' ) ]                             = self::fmt_date( MCM_Safe::meta( $id, 'mcm_res_start' ) );
				$rows[ __( 'Completed', 'medical-college-manager' ) ]                           = self::fmt_date( MCM_Safe::meta( $id, 'mcm_res_completed' ) );
				$rows[ __( 'Co-Investigators', 'medical-college-manager' ) ]                    = MCM_Safe::meta( $id, 'mcm_res_coinv' );
				$rows[ __( 'Assisting Students', 'medical-college-manager' ) ]                  = MCM_Safe::meta( $id, 'mcm_res_students' );
				$rows[ __( 'Funding', 'medical-college-manager' ) ]                             = MCM_Safe::meta( $id, 'mcm_res_funding' );
				$rows[ __( 'Research Area', 'medical-college-manager' ) ]                       = self::tax_names( $id, 'mcm_research_area' );
				$rows[ __( 'Department', 'medical-college-manager' ) ]                          = self::tax_names( $id, 'mcm_department' );
				$doc = MCM_Safe::meta( $id, 'mcm_res_doc' );
				if ( $doc ) {
					$actions[] = array( __( 'View Paper', 'medical-college-manager' ), $doc, true, array( 'newtab' => true ) );
					$actions[] = array( __( 'Download', 'medical-college-manager' ), $doc, false, array( 'download' => true ) );
				}
				break;

			case 'mcm_policy':
				$heading = __( 'Policy Details', 'medical-college-manager' );
				$rows[ __( 'Category', 'medical-college-manager' ) ]       = self::tax_names( $id, 'mcm_policy_cat' );
				$rows[ __( 'Issued By', 'medical-college-manager' ) ]      = MCM_Safe::meta( $id, 'mcm_policy_issuer' );
				$rows[ __( 'Reference No.', 'medical-college-manager' ) ]  = MCM_Safe::meta( $id, 'mcm_policy_ref' );
				$rows[ __( 'Effective Date', 'medical-college-manager' ) ] = self::fmt_date( MCM_Safe::meta( $id, 'mcm_policy_date' ) );
				$rows[ __( 'Department', 'medical-college-manager' ) ]     = self::tax_names( $id, 'mcm_department' );
				$pdoc = MCM_Safe::meta( $id, 'mcm_policy_doc' );
				if ( $pdoc ) {
					$actions[] = array( __( 'Download Policy', 'medical-college-manager' ), $pdoc, true );
				}
				break;

			case 'mcm_scholarship':
				$heading = __( 'Scholarship Details', 'medical-college-manager' );
				$rows[ __( 'Type', 'medical-college-manager' ) ]                 = self::tax_names( $id, 'mcm_scholarship_cat' );
				$rows[ __( 'Department', 'medical-college-manager' ) ]           = self::tax_names( $id, 'mcm_department' );
				$rows[ __( 'Amount / Coverage', 'medical-college-manager' ) ]    = MCM_Safe::meta( $id, 'mcm_scholarship_amount' );
				$rows[ __( 'Eligibility', 'medical-college-manager' ) ]          = MCM_Safe::meta( $id, 'mcm_scholarship_eligibility' );
				$rows[ __( 'Application Deadline', 'medical-college-manager' ) ] = self::fmt_date( MCM_Safe::meta( $id, 'mcm_scholarship_deadline' ) );
				$apply = MCM_Safe::meta( $id, 'mcm_scholarship_apply' );
				if ( $apply ) {
					$actions[] = array( __( 'Apply / More Info', 'medical-college-manager' ), $apply, true );
				}
				break;

			case 'mcm_notice':
				$heading = __( 'Notice', 'medical-college-manager' );
				$rows[ __( 'Category', 'medical-college-manager' ) ]   = self::tax_names( $id, 'mcm_notice_cat' );
				$rows[ __( 'Department', 'medical-college-manager' ) ] = self::tax_names( $id, 'mcm_department' );
				$rows[ __( 'Priority', 'medical-college-manager' ) ]   = MCM_Safe::meta( $id, 'mcm_notice_priority' );
				$ndate = MCM_Safe::meta( $id, 'mcm_notice_date' );
				$rows[ __( 'Date', 'medical-college-manager' ) ] = $ndate ? self::fmt_date( $ndate ) : get_the_date( '', $id );
				$link = MCM_Safe::meta( $id, 'mcm_notice_url' );
				$file = MCM_Safe::meta( $id, 'mcm_notice_attachment' );
				if ( $file ) {
					$actions[] = array( __( 'Download', 'medical-college-manager' ), $file, true );
				}
				if ( $link ) {
					$actions[] = array( __( 'Open Link', 'medical-college-manager' ), $link, ! $file );
				}
				break;

			case 'mcm_timetable':
				$heading                                        = __( 'Timetable Entry', 'medical-college-manager' );
				$rows[ __( 'Day', 'medical-college-manager' ) ] = MCM_Safe::meta( $id, 'mcm_day' );
				$start = MCM_Safe::meta( $id, 'mcm_start_time' );
				$end   = MCM_Safe::meta( $id, 'mcm_end_time' );
				$rows[ __( 'Time', 'medical-college-manager' ) ]       = trim( $start . ( $end ? ' – ' . $end : '' ) );
				$rows[ __( 'Subject', 'medical-college-manager' ) ]    = MCM_Safe::meta( $id, 'mcm_subject' );
				$rows[ __( 'Professor', 'medical-college-manager' ) ]  = self::tax_names( $id, 'mcm_teacher' );
				$rows[ __( 'Department', 'medical-college-manager' ) ] = self::tax_names( $id, 'mcm_department' );
				$rows[ __( 'Room', 'medical-college-manager' ) ]       = MCM_Safe::meta( $id, 'mcm_room' );
				$rows[ __( 'Year', 'medical-college-manager' ) ]       = MCM_Safe::meta( $id, 'mcm_year' );
				break;

			default:
				return '';
		}

		// Drop empty values.
		$rows = array_filter(
			$rows,
			function ( $v ) {
				return '' !== $v && null !== $v;
			}
		);

		if ( empty( $rows ) && empty( $actions ) ) {
			return '';
		}

		ob_start();
		echo '<div class="mcm-single-details mcm-single-details--' . esc_attr( str_replace( 'mcm_', '', $type ) ) . '">';
		if ( $heading ) {
			echo '<h3 class="mcm-sd-heading">' . esc_html( $heading ) . '</h3>';
		}
		if ( $rows ) {
			echo '<div class="mcm-sd-grid">';
			foreach ( $rows as $label => $value ) {
				// Escape first, then keep line breaks: Fellowship Members and
				// Co-Investigators are entered one per line and must not collapse
				// into a run-on. nl2br after esc_html is safe (no raw HTML).
				echo '<div class="mcm-sd-item"><span class="mcm-sd-label">' . esc_html( $label ) . '</span><span class="mcm-sd-value">' . nl2br( esc_html( $value ) ) . '</span></div>';
			}
			echo '</div>';
		}
		if ( $actions ) {
			echo '<div class="mcm-sd-actions">';
			foreach ( $actions as $act ) {
				// Optional 4th element carries link options: {newtab, download}.
				// Older 3-element actions keep working unchanged.
				$act                                  = array_pad( (array) $act, 4, null );
				list( $label, $url, $primary, $opts ) = $act;
				$opts                                 = is_array( $opts ) ? $opts : array();
				$cls                                  = 'mcm-sd-btn' . ( $primary ? ' mcm-sd-btn--primary' : '' );
				// Any off-site destination opens in a new tab, not just the
				// primary one — ORCID, Scholar, PubMed and LinkedIn are all
				// secondary buttons and used to navigate the visitor away. A
				// "View" paper button also opens in a new tab even on-site.
				$attr = '';
				if ( self::is_external_url( $url ) || ! empty( $opts['newtab'] ) ) {
					$attr .= ' target="_blank" rel="noopener noreferrer"';
				}
				if ( ! empty( $opts['download'] ) ) {
					$attr .= ' download';
				}
				printf(
					'<a class="%1$s" href="%2$s"%4$s>%3$s</a>',
					esc_attr( $cls ),
					esc_url( $url ),
					esc_html( $label ),
					$attr // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Only the fixed strings ' target="_blank" rel="noopener noreferrer"' and ' download'.
				);
			}
			echo '</div>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * A single published entry, for [mcm_single].
	 *
	 * @param int $id Post ID.
	 */
	public static function single( $id ) {
		$id   = absint( $id );
		$type = $id ? get_post_type( $id ) : '';

		// Only ever render published MCM content. Never expose drafts, private,
		// pending, trashed, or non-plugin posts via [mcm_single id="…"].
		// Non-viewable types (students) are refused too: their photo and body
		// text are private, and this shortcode is open to any shortcode author.
		if ( ! $id || 'publish' !== get_post_status( $id ) || 0 !== strpos( (string) $type, 'mcm_' ) || ! is_post_type_viewable( $type ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="mcm-single mcm-single--<?php echo esc_attr( str_replace( 'mcm_', '', (string) $type ) ); ?>">
			<?php if ( has_post_thumbnail( $id ) ) : ?>
				<div class="mcm-single-media"><?php echo get_the_post_thumbnail( $id, 'large', array( 'alt' => esc_attr( get_the_title( $id ) ) ) ); // phpcs:ignore ?></div>
			<?php elseif ( self::is_person( $type ) ) : ?>
				<div class="mcm-single-media"><?php echo self::default_avatar( 'mcm-single-img', 'mcm-single-ph' ); // phpcs:ignore ?></div>
			<?php endif; ?>
			<div class="mcm-single-body">
				<h2 class="mcm-single-title"><?php echo esc_html( get_the_title( $id ) ); ?></h2>
				<?php
				$excerpt = has_excerpt( $id ) ? get_the_excerpt( $id ) : '';
				if ( $excerpt ) {
					echo '<p class="mcm-single-lead">' . esc_html( $excerpt ) . '</p>';
				}
				$content = get_post_field( 'post_content', $id );
				if ( '' !== trim( (string) $content ) ) {
					echo '<div class="mcm-single-content">' . wp_kses_post( apply_filters( 'the_content', $content ) ) . '</div>'; // phpcs:ignore
				}
				echo self::single_details( $id, $type ); // phpcs:ignore
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
