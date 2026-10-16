<?php
/**
 * Registers all custom post types and taxonomies.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the content types and taxonomies.
 */
class MCM_CPT {

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		// Force the simple classic editor for every plugin CPT (keeps the REST
		// API on for meta/Elementor, but no block editor — easier data entry).
		add_filter( 'use_block_editor_for_post_type', array( $this, 'force_classic_editor' ), 10, 2 );
	}

	/**
	 * Use the classic editor for our content types so non-technical staff get a
	 * plain title + content + labelled meta-box screen instead of blocks.
	 *
	 * @param mixed $use_block Use block.
	 * @param mixed $post_type Post type.
	 */
	public function force_classic_editor( $use_block, $post_type ) {
		return array_key_exists( $post_type, self::post_types() ) ? false : $use_block;
	}

	/**
	 * Central config for every CPT. Slugs use the `mcm_` prefix to avoid clashes.
	 *
	 * @return array
	 */
	public static function post_types() {
		return array(
			'mcm_timetable'   => array(
				'singular'    => __( 'Timetable Entry', 'medical-college-manager' ),
				'plural'      => __( 'Timetable', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-calendar-alt',
				'slug'        => 'timetable',
				'supports'    => array( 'title' ),
				'has_archive' => true,
			),
			'mcm_program'     => array(
				'singular'    => __( 'Program', 'medical-college-manager' ),
				'plural'      => __( 'Programs', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-welcome-learn-more',
				'slug'        => 'programs',
				'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive' => true,
			),
			'mcm_course'      => array(
				'singular'    => __( 'Course', 'medical-college-manager' ),
				'plural'      => __( 'Courses', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-book',
				'slug'        => 'courses',
				'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive' => true,
			),
			'mcm_event'       => array(
				'singular'    => __( 'Event', 'medical-college-manager' ),
				'plural'      => __( 'Events', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-megaphone',
				'slug'        => 'events',
				'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive' => true,
			),
			'mcm_consultant'  => array(
				'singular'    => __( 'Consultant', 'medical-college-manager' ),
				'plural'      => __( 'Consultants', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-businessperson',
				'slug'        => 'consultants',
				'supports'    => array( 'title', 'editor', 'thumbnail' ),
				'has_archive' => true,
			),
			'mcm_team'        => array(
				'singular'    => __( 'Team Member', 'medical-college-manager' ),
				'plural'      => __( 'Team', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-groups',
				'slug'        => 'team',
				'supports'    => array( 'title', 'editor', 'thumbnail' ),
				'has_archive' => true,
			),
			'mcm_admission'   => array(
				'singular'    => __( 'Admission', 'medical-college-manager' ),
				'plural'      => __( 'Admissions', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-id-alt',
				'slug'        => 'admissions',
				'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive' => true,
			),
			'mcm_notice'      => array(
				'singular'    => __( 'Notice', 'medical-college-manager' ),
				'plural'      => __( 'Notices', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-bell',
				'slug'        => 'notices',
				'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive' => true,
			),
			'mcm_tender'      => array(
				'singular'    => __( 'Tender', 'medical-college-manager' ),
				'plural'      => __( 'Tenders', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-media-document',
				'slug'        => 'tenders',
				'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive' => true,
			),
			'mcm_scholarship' => array(
				'singular'    => __( 'Scholarship', 'medical-college-manager' ),
				'plural'      => __( 'Scholarships', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-awards',
				'slug'        => 'scholarships',
				'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive' => true,
			),
			'mcm_publication' => array(
				'singular'    => __( 'Publication', 'medical-college-manager' ),
				'plural'      => __( 'Publications', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-book-alt',
				'slug'        => 'publications',
				'supports'    => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'has_archive' => true,
			),
			'mcm_research'    => array(
				'singular'    => __( 'Research Project', 'medical-college-manager' ),
				'plural'      => __( 'Research Projects', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-microscope',
				'slug'        => 'research',
				'supports'    => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'has_archive' => true,
			),
			'mcm_policy'      => array(
				'singular'    => __( 'Policy', 'medical-college-manager' ),
				'plural'      => __( 'Policies', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-media-document',
				'slug'        => 'policies',
				'supports'    => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'has_archive' => true,
			),
			'mcm_student'     => array(
				'singular'    => __( 'Student', 'medical-college-manager' ),
				'plural'      => __( 'Students', 'medical-college-manager' ),
				'menu_icon'   => 'dashicons-admin-users',
				'slug'        => 'students',
				'supports'    => array( 'title', 'editor', 'thumbnail' ),
				'has_archive' => false, // directory is internal; expose via shortcodes.
				// Student records hold PII — keep the editor UI but remove all
				// front-end AND REST exposure (no public single URLs, no site
				// search, no /wp-json route leaking emails/phones/addresses).
				// Students are managed in the classic editor via the meta boxes.
				'args'        => array(
					'public'              => false,
					'publicly_queryable'  => false,
					'exclude_from_search' => true,
					'show_in_rest'        => false,
					// Student records are the only content here that is personal
					// data (PMDC no., email, phone, DOB, address, guardian). With
					// the default 'post' capabilities every Author and Contributor
					// could open the whole roll. Own capability set instead, and
					// see MCM_Caps for who is granted it (administrators only, by
					// default). Every other type keeps the familiar post caps, so
					// staff who manage timetables and events are unaffected.
					'capability_type'     => array( 'mcm_student', 'mcm_students' ),
					'map_meta_cap'        => true,
				),
			),
		);
	}

	/**
	 * Central config for every taxonomy. `post_types` can list multiple CPTs so
	 * a single taxonomy (e.g. Department) is shared and consistently filterable.
	 *
	 * @return array
	 */
	public static function taxonomies() {
		return array(
			// Shared across most CPTs — the backbone filter.
			'mcm_department'      => array(
				'post_types'   => array( 'mcm_timetable', 'mcm_program', 'mcm_course', 'mcm_event', 'mcm_consultant', 'mcm_team', 'mcm_admission', 'mcm_student', 'mcm_notice', 'mcm_tender', 'mcm_scholarship', 'mcm_publication', 'mcm_research', 'mcm_policy' ),
				'singular'     => __( 'Department', 'medical-college-manager' ),
				'plural'       => __( 'Departments', 'medical-college-manager' ),
				'slug'         => 'department',
				'hierarchical' => true,
			),
			// Academic session / intake.
			'mcm_session'         => array(
				'post_types'   => array( 'mcm_timetable', 'mcm_course', 'mcm_admission', 'mcm_student' ),
				'singular'     => __( 'Session', 'medical-college-manager' ),
				'plural'       => __( 'Sessions', 'medical-college-manager' ),
				'slug'         => 'session',
				'hierarchical' => true,
			),
			// Student level — Graduate / Undergraduate.
			'mcm_level'           => array(
				'post_types'   => array( 'mcm_student' ),
				'singular'     => __( 'Level', 'medical-college-manager' ),
				'plural'       => __( 'Levels', 'medical-college-manager' ),
				'slug'         => 'student-level',
				'hierarchical' => true,
			),
			// Faculty / Teacher — the person who teaches. Shared so a faculty
			// member links their Timetable & Courses with their Consultant/Team
			// profile (one term = one person).
			'mcm_teacher'         => array(
				'post_types'   => array( 'mcm_timetable', 'mcm_course', 'mcm_consultant', 'mcm_team', 'mcm_publication', 'mcm_research' ),
				'singular'     => __( 'Faculty', 'medical-college-manager' ),
				'plural'       => __( 'Faculty', 'medical-college-manager' ),
				'slug'         => 'faculty',
				'hierarchical' => false,
			),
			// Specialty — consultants & team.
			'mcm_specialty'       => array(
				'post_types'   => array( 'mcm_consultant', 'mcm_team' ),
				'singular'     => __( 'Specialty', 'medical-college-manager' ),
				'plural'       => __( 'Specialties', 'medical-college-manager' ),
				'slug'         => 'specialty',
				'hierarchical' => false,
			),
			// Per-type categories.
			'mcm_program_cat'     => array(
				'post_types'   => array( 'mcm_program' ),
				'singular'     => __( 'Program Category', 'medical-college-manager' ),
				'plural'       => __( 'Program Categories', 'medical-college-manager' ),
				'slug'         => 'program-category',
				'hierarchical' => true,
			),
			'mcm_course_cat'      => array(
				'post_types'   => array( 'mcm_course' ),
				'singular'     => __( 'Course Category', 'medical-college-manager' ),
				'plural'       => __( 'Course Categories', 'medical-college-manager' ),
				'slug'         => 'course-category',
				'hierarchical' => true,
			),
			'mcm_event_cat'       => array(
				'post_types'   => array( 'mcm_event' ),
				'singular'     => __( 'Event Category', 'medical-college-manager' ),
				'plural'       => __( 'Event Categories', 'medical-college-manager' ),
				'slug'         => 'event-category',
				'hierarchical' => true,
			),
			'mcm_notice_cat'      => array(
				'post_types'   => array( 'mcm_notice' ),
				'singular'     => __( 'Notice Category', 'medical-college-manager' ),
				'plural'       => __( 'Notice Categories', 'medical-college-manager' ),
				'slug'         => 'notice-category',
				'hierarchical' => true,
			),
			'mcm_tender_cat'      => array(
				'post_types'   => array( 'mcm_tender' ),
				'singular'     => __( 'Tender Category', 'medical-college-manager' ),
				'plural'       => __( 'Tender Categories', 'medical-college-manager' ),
				'slug'         => 'tender-category',
				'hierarchical' => true,
			),
			'mcm_scholarship_cat' => array(
				'post_types'   => array( 'mcm_scholarship' ),
				'singular'     => __( 'Scholarship Type', 'medical-college-manager' ),
				'plural'       => __( 'Scholarship Types', 'medical-college-manager' ),
				'slug'         => 'scholarship-type',
				'hierarchical' => true,
			),
			// Research Area — shared by publications and the people who research
			// them, so a professor's interests and output stay connected.
			'mcm_research_area'   => array(
				'post_types'   => array( 'mcm_publication', 'mcm_consultant', 'mcm_team', 'mcm_research' ),
				'singular'     => __( 'Research Area', 'medical-college-manager' ),
				'plural'       => __( 'Research Areas', 'medical-college-manager' ),
				'slug'         => 'research-area',
				'hierarchical' => true,
			),
			'mcm_policy_cat'      => array(
				'post_types'   => array( 'mcm_policy' ),
				'singular'     => __( 'Policy Category', 'medical-college-manager' ),
				'plural'       => __( 'Policy Categories', 'medical-college-manager' ),
				'slug'         => 'policy-category',
				'hierarchical' => true,
			),
			// Program connector — the academic spine (MBBS/BDS/BSN…). Shared so a
			// program page lists its courses, roll, admissions and timetable.
			// Distinct key from the mcm_program CPT (which holds the rich program
			// page); a matching term is auto-created for each Program entry
			// (see MCM_Program) so there is no double data entry.
			'mcm_program_link'    => array(
				'post_types'   => array( 'mcm_course', 'mcm_timetable', 'mcm_admission', 'mcm_student' ),
				'singular'     => __( 'Program', 'medical-college-manager' ),
				'plural'       => __( 'Programs', 'medical-college-manager' ),
				'slug'         => 'program',
				'hierarchical' => true,
			),
		);
	}

	/**
	 * Register every content type defined in post_types().
	 */
	public function register_post_types() {
		foreach ( self::post_types() as $key => $cfg ) {
			/* translators (all %s below): the content type's singular or plural name. */
			$s      = $cfg['singular'];
			$p      = $cfg['plural'];
			$labels = array(
				'name'               => $p,
				'singular_name'      => $s,
				'menu_name'          => $p,
				'add_new'            => __( 'Add New', 'medical-college-manager' ),
				/* translators: %s: content type or taxonomy singular name. */
				'add_new_item'       => sprintf( __( 'Add New %s', 'medical-college-manager' ), $s ),
				/* translators: %s: content type or taxonomy singular name. */
				'edit_item'          => sprintf( __( 'Edit %s', 'medical-college-manager' ), $s ),
				/* translators: %s: content type or taxonomy singular name. */
				'new_item'           => sprintf( __( 'New %s', 'medical-college-manager' ), $s ),
				/* translators: %s: content type or taxonomy singular name. */
				'view_item'          => sprintf( __( 'View %s', 'medical-college-manager' ), $s ),
				/* translators: %s: content type or taxonomy plural name. */
				'search_items'       => sprintf( __( 'Search %s', 'medical-college-manager' ), $p ),
				'not_found'          => __( 'Nothing found', 'medical-college-manager' ),
				'not_found_in_trash' => __( 'Nothing found in Trash', 'medical-college-manager' ),
				/* translators: %s: content type or taxonomy plural name. */
				'all_items'          => sprintf( __( 'All %s', 'medical-college-manager' ), $p ),
			);

			$args = array(
				'labels'       => $labels,
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => true,
				'show_in_rest' => true,
				'menu_icon'    => $cfg['menu_icon'],
				'supports'     => $cfg['supports'],
				'has_archive'  => $cfg['has_archive'],
				'rewrite'      => array(
					'slug'       => $cfg['slug'],
					'with_front' => false,
				),
				'hierarchical' => false,
			);

			// Per-type overrides (e.g. students opt out of public exposure).
			if ( ! empty( $cfg['args'] ) && is_array( $cfg['args'] ) ) {
				$args = array_merge( $args, $cfg['args'] );
			}

			register_post_type( $key, apply_filters( "mcm_cpt_args_{$key}", $args ) );
		}
	}

	/**
	 * Register every taxonomy defined in taxonomies().
	 */
	public function register_taxonomies() {
		foreach ( self::taxonomies() as $key => $cfg ) {
			$s      = $cfg['singular'];
			$p      = $cfg['plural'];
			$labels = array(
				'name'          => $p,
				'singular_name' => $s,
				/* translators: %s: content type or taxonomy plural name. */
				'search_items'  => sprintf( __( 'Search %s', 'medical-college-manager' ), $p ),
				/* translators: %s: content type or taxonomy plural name. */
				'all_items'     => sprintf( __( 'All %s', 'medical-college-manager' ), $p ),
				/* translators: %s: content type or taxonomy singular name. */
				'edit_item'     => sprintf( __( 'Edit %s', 'medical-college-manager' ), $s ),
				/* translators: %s: content type or taxonomy singular name. */
				'update_item'   => sprintf( __( 'Update %s', 'medical-college-manager' ), $s ),
				/* translators: %s: content type or taxonomy singular name. */
				'add_new_item'  => sprintf( __( 'Add New %s', 'medical-college-manager' ), $s ),
				/* translators: %s: content type or taxonomy singular name. */
				'new_item_name' => sprintf( __( 'New %s Name', 'medical-college-manager' ), $s ),
				'menu_name'     => $p,
			);

			// A taxonomy that lives ONLY on private content (e.g. Level, which is
			// students-only) must not expose a public term archive or REST route —
			// its pages could only ever be empty, and it keeps student-only
			// vocabulary off the open web. It stays fully usable in wp-admin.
			$types     = self::post_types();
			$is_public = false;
			foreach ( (array) $cfg['post_types'] as $pt ) {
				$pub = ! isset( $types[ $pt ]['args']['public'] ) || false !== $types[ $pt ]['args']['public'];
				if ( $pub ) {
					$is_public = true;
					break;
				}
			}

			$args = array(
				'labels'             => $labels,
				'public'             => $is_public,
				'publicly_queryable' => $is_public,
				'hierarchical'       => $cfg['hierarchical'],
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => $is_public,
				'query_var'          => $is_public,
				'rewrite'            => $is_public ? array( 'slug' => $cfg['slug'] ) : false,
			);

			register_taxonomy( $key, $cfg['post_types'], apply_filters( "mcm_tax_args_{$key}", $args ) );
		}
	}
}
