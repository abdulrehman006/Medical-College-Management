<?php
/**
 * Department-scoped editing.
 *
 * An administrator can limit a user (an Editor or Author, typically a
 * department's web coordinator) to one or more departments under Users →
 * Edit user → "Department access". Such a user then:
 *  - sees only entries tagged with their departments in the wp-admin lists;
 *  - can edit, delete or publish only those entries (and their own new ones);
 *  - can tag entries only with their departments — departments they are not
 *    assigned stay on the entry untouched;
 *  - can edit only their own departments' pages (term settings) and cannot
 *    add new departments.
 *
 * Off by default: users without assigned departments, and every
 * administrator, keep their normal WordPress permissions.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restricts users with assigned departments to those departments' content.
 */
class MCM_Dept_Access {

	const META = 'mcm_departments';
	const TAX  = 'mcm_department';

	/**
	 * Department terms an entry had before the current save (post ID => ids).
	 *
	 * @var array<int,int[]>
	 */
	private static $before = array();

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 10, 4 );
		add_action( 'pre_get_posts', array( __CLASS__, 'scope_admin_lists' ) );
		add_action( 'pre_post_update', array( __CLASS__, 'remember_terms' ) );
		add_action( 'save_post', array( __CLASS__, 'enforce_terms' ), 15, 2 );
		add_filter( 'pre_insert_term', array( __CLASS__, 'block_new_department' ), 10, 2 );
		add_filter( 'get_terms_args', array( __CLASS__, 'limit_checklist' ), 10, 2 );
		add_action( 'show_user_profile', array( __CLASS__, 'profile_field' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_field' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile' ) );
		add_action( 'admin_head', array( __CLASS__, 'hide_add_department' ) );
		add_filter( 'manage_users_columns', array( __CLASS__, 'users_column' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'users_column_value' ), 10, 3 );
	}

	/**
	 * Department IDs a user may manage, including child departments.
	 * Empty array = not scoped (normal permissions).
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	public static function allowed( $user_id ) {
		static $cache = array();

		$user_id = (int) $user_id;
		if ( ! $user_id || user_can( $user_id, 'manage_options' ) ) {
			return array();
		}
		if ( isset( $cache[ $user_id ] ) ) {
			return $cache[ $user_id ];
		}
		$ids = array_filter( array_map( 'intval', (array) get_user_meta( $user_id, self::META, true ) ) );
		$all = array();
		foreach ( $ids as $id ) {
			if ( ! term_exists( $id, self::TAX ) ) {
				continue;
			}
			$all[] = $id;
			$kids  = get_term_children( $id, self::TAX );
			if ( is_array( $kids ) ) {
				$all = array_merge( $all, array_map( 'intval', $kids ) );
			}
		}
		$cache[ $user_id ] = array_values( array_unique( $all ) );
		return $cache[ $user_id ];
	}

	/**
	 * Content types the scoping applies to (every plugin type with departments,
	 * except students, which have their own administrator-only capabilities).
	 *
	 * @return string[]
	 */
	public static function types() {
		$tax = get_taxonomy( self::TAX );
		$out = $tax ? array_diff( (array) $tax->object_type, array( 'mcm_student' ) ) : array();
		return array_values( array_intersect( $out, array_keys( MCM_CPT::post_types() ) ) );
	}

	/**
	 * Department IDs on an entry.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private static function post_departments( $post_id ) {
		$ids = wp_get_object_terms( $post_id, self::TAX, array( 'fields' => 'ids' ) );
		return is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
	}

	/**
	 * Deny edit/delete/publish of entries outside the user's departments, and
	 * editing of other departments' terms.
	 *
	 * @param string[] $caps    Primitive caps.
	 * @param string   $cap     Meta cap.
	 * @param int      $user_id User.
	 * @param array    $args    Args (object ID first).
	 * @return string[]
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		$post_caps = array( 'edit_post', 'delete_post', 'publish_post' );
		$term_caps = array( 'edit_term', 'delete_term' );
		if ( ! in_array( $cap, $post_caps, true ) && ! in_array( $cap, $term_caps, true ) ) {
			return $caps;
		}
		if ( empty( $args[0] ) ) {
			return $caps;
		}
		$allowed = self::allowed( $user_id );
		if ( ! $allowed ) {
			return $caps;
		}
		if ( in_array( $cap, $term_caps, true ) ) {
			$term = get_term( (int) $args[0] );
			if ( $term instanceof WP_Term && self::TAX === $term->taxonomy && ! in_array( (int) $term->term_id, $allowed, true ) ) {
				$caps[] = 'do_not_allow';
			}
			return $caps;
		}
		$post = get_post( (int) $args[0] );
		if ( ! $post || ! in_array( $post->post_type, self::types(), true ) || 'auto-draft' === $post->post_status ) {
			return $caps;
		}
		$depts = self::post_departments( $post->ID );
		if ( $depts ) {
			if ( ! array_intersect( $depts, $allowed ) ) {
				$caps[] = 'do_not_allow';
			}
		} elseif ( (int) $post->post_author !== (int) $user_id ) {
			// College-wide entries (no department) belong to the administrators.
			$caps[] = 'do_not_allow';
		}
		return $caps;
	}

	/**
	 * Show a scoped user only their departments' entries in wp-admin lists.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function scope_admin_lists( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || wp_doing_ajax() ) {
			return;
		}
		$type = $query->get( 'post_type' );
		if ( ! is_string( $type ) || ! in_array( $type, self::types(), true ) ) {
			return;
		}
		$allowed = self::allowed( get_current_user_id() );
		if ( ! $allowed ) {
			return;
		}
		$tq   = (array) $query->get( 'tax_query' );
		$tq[] = array(
			'taxonomy'         => self::TAX,
			'field'            => 'term_id',
			'terms'            => $allowed,
			'include_children' => false,
		);
		$query->set( 'tax_query', $tq ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Admin list scoping.
	}

	/**
	 * Remember an entry's departments before it is updated.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function remember_terms( $post_id ) {
		if ( self::allowed( get_current_user_id() ) && in_array( get_post_type( $post_id ), self::types(), true ) ) {
			self::$before[ (int) $post_id ] = self::post_departments( $post_id );
		}
	}

	/**
	 * After a scoped user saves: keep departments they may not manage as they
	 * were, and make sure the entry carries at least one of theirs.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function enforce_terms( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! in_array( $post->post_type, self::types(), true ) ) {
			return;
		}
		$allowed = self::allowed( get_current_user_id() );
		if ( ! $allowed ) {
			return;
		}
		$now    = self::post_departments( $post_id );
		$before = isset( self::$before[ (int) $post_id ] ) ? self::$before[ (int) $post_id ] : array();
		$mine   = array_values( array_intersect( $now, $allowed ) );
		if ( ! $mine ) {
			$mine = array( (int) $allowed[0] );
		}
		$others = array_values( array_diff( $before, $allowed ) );
		$want   = array_values( array_unique( array_merge( $mine, $others ) ) );
		sort( $want );
		sort( $now );
		if ( $want !== $now ) {
			wp_set_object_terms( $post_id, $want, self::TAX );
		}
	}

	/**
	 * Scoped users: hide "+ Add New Department" (adding is refused anyway).
	 */
	public static function hide_add_department() {
		if ( self::allowed( get_current_user_id() ) ) {
			echo '<style id="mcm-dept-access">#mcm_department-adder,#mcm_department-add-toggle,.taxonomy-mcm_department #col-left{display:none!important}.taxonomy-mcm_department #col-right{width:100%}</style>';
		}
	}

	/**
	 * Scoped users cannot add departments.
	 *
	 * @param string|WP_Error $term     Term name.
	 * @param string          $taxonomy Taxonomy.
	 * @return string|WP_Error
	 */
	public static function block_new_department( $term, $taxonomy ) {
		if ( self::TAX === $taxonomy && self::allowed( get_current_user_id() ) ) {
			return new WP_Error( 'mcm_dept_scoped', __( 'Only an administrator can add departments.', 'medical-college-manager' ) );
		}
		return $term;
	}

	/**
	 * On edit screens, a scoped user's Departments box offers only their
	 * departments (others an entry already has are kept by enforce_terms()).
	 *
	 * @param array    $args       get_terms() args.
	 * @param string[] $taxonomies Taxonomies.
	 * @return array
	 */
	public static function limit_checklist( $args, $taxonomies ) {
		if ( ! is_admin() || array( self::TAX ) !== array_values( (array) $taxonomies ) || ! function_exists( 'get_current_screen' ) ) {
			return $args;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'post', 'edit-tags', 'edit' ), true ) ) {
			return $args;
		}
		$allowed = self::allowed( get_current_user_id() );
		if ( $allowed ) {
			$args['include'] = $allowed;
		}
		return $args;
	}

	/**
	 * "Department access" on the user profile (administrators only).
	 *
	 * @param WP_User $user User being edited.
	 */
	public static function profile_field( $user ) {
		if ( ! current_user_can( 'promote_users' ) || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAX,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			return;
		}
		$have = array_map( 'intval', (array) get_user_meta( $user->ID, self::META, true ) );
		wp_nonce_field( 'mcm_dept_access', 'mcm_dept_access_nonce' );
		echo '<h2 id="mcm-dept-access">' . esc_html__( 'Department access (Medical College Manager)', 'medical-college-manager' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tr><th scope="row">' . esc_html__( 'Departments', 'medical-college-manager' ) . '</th><td>';
		if ( user_can( $user->ID, 'manage_options' ) ) {
			echo '<p class="description">' . esc_html__( 'Administrators always manage every department; ticks here have no effect for them.', 'medical-college-manager' ) . '</p>';
		}
		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Departments', 'medical-college-manager' ) . '</legend>';
		echo '<div style="columns:3 14em;max-width:900px;">';
		foreach ( $terms as $t ) {
			printf(
				'<label style="display:block;margin:0 0 6px;"><input type="checkbox" name="mcm_departments[]" value="%1$d" %2$s /> %3$s</label>',
				(int) $t->term_id,
				checked( in_array( (int) $t->term_id, $have, true ), true, false ),
				esc_html( $t->name )
			);
		}
		echo '</div></fieldset>';
		echo '<p class="description">' . esc_html__( 'Leave all unticked for normal access. Tick one or more to limit this user to those departments: they will see, edit and publish only entries tagged with them (plus their own new entries), and edit only those department pages.', 'medical-college-manager' ) . '</p>';
		echo '</td></tr></table>';
	}

	/**
	 * Save "Department access".
	 *
	 * @param int $user_id User ID.
	 */
	public static function save_profile( $user_id ) {
		if ( ! isset( $_POST['mcm_dept_access_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcm_dept_access_nonce'] ) ), 'mcm_dept_access' ) ) {
			return;
		}
		if ( ! current_user_can( 'promote_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		$raw = isset( $_POST['mcm_departments'] ) && is_array( $_POST['mcm_departments'] ) ? array_map( 'absint', wp_unslash( $_POST['mcm_departments'] ) ) : array();
		$ids = array();
		foreach ( $raw as $id ) {
			if ( $id && term_exists( $id, self::TAX ) ) {
				$ids[] = $id;
			}
		}
		if ( $ids ) {
			update_user_meta( $user_id, self::META, array_values( array_unique( $ids ) ) );
		} else {
			delete_user_meta( $user_id, self::META );
		}
	}

	/**
	 * Users list: a Departments column.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function users_column( $cols ) {
		$cols['mcm_departments'] = __( 'Department access', 'medical-college-manager' );
		return $cols;
	}

	/**
	 * Users list: the assigned departments ("All" when not scoped).
	 *
	 * @param string $out     Output.
	 * @param string $column  Column.
	 * @param int    $user_id User.
	 * @return string
	 */
	public static function users_column_value( $out, $column, $user_id ) {
		if ( 'mcm_departments' !== $column ) {
			return $out;
		}
		$ids = array_filter( array_map( 'intval', (array) get_user_meta( $user_id, self::META, true ) ) );
		if ( ! $ids || user_can( $user_id, 'manage_options' ) ) {
			return esc_html__( 'All', 'medical-college-manager' );
		}
		$names = array();
		foreach ( $ids as $id ) {
			$t = get_term( $id, self::TAX );
			if ( $t instanceof WP_Term ) {
				$names[] = $t->name;
			}
		}
		return esc_html( implode( ', ', $names ) );
	}
}
