<?php
/**
 * One record per person: consultants and team members (teaching, support and
 * other staff) are identified by their email address.
 *
 * - Email is required and unique within each type. A doctor may still be
 *   both a Consultant and a Team member (two types, one email each).
 * - The CSV import updates the person whose email matches and adds the new
 *   department, so importing Department 2 after Department 1 keeps one
 *   record that belongs to both (see MCM_Import).
 * - Phone is optional. A phone already used by someone else is reported, not
 *   refused: an OPD or office line can be shared.
 * - The edit screen will not save until a valid, unused email is entered;
 *   the server refuses a duplicate even if the browser check is bypassed.
 * - Existing records without an email, or sharing one, are labelled in the
 *   list and listed under "Needs attention". Nothing is merged automatically.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email identity for people records.
 */
class MCM_People {

	/** Meta key holding the email. */
	const EMAIL = 'mcm_email';

	/** Meta key holding the phone. */
	const PHONE = 'mcm_phone';

	/** Transient prefix for the "not saved" notice. */
	const NOTICE = 'mcm_people_notice_';

	/**
	 * Per-request caches: type => normalised phone => post IDs, and
	 * type => duplicate-email post IDs.
	 *
	 * @var array
	 */
	private static $phones = array();

	/**
	 * Per-request cache of posts sharing an email, per type.
	 *
	 * @var array
	 */
	private static $dupes = array();

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_mcm_person_check', array( __CLASS__, 'ajax_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
		add_filter( 'mcm_integrity_flagged_ids', array( __CLASS__, 'flagged_ids' ), 10, 2 );
		foreach ( self::types() as $type ) {
			add_action( "save_post_{$type}", array( __CLASS__, 'forget' ) );
		}
	}

	/**
	 * Post types whose records are people identified by email.
	 *
	 * @return string[]
	 */
	public static function types() {
		return array( 'mcm_consultant', 'mcm_team' );
	}

	/**
	 * Whether a post type is a people type.
	 *
	 * @param string $type Post type.
	 * @return bool
	 */
	public static function is_person_type( $type ) {
		return in_array( (string) $type, self::types(), true );
	}

	/**
	 * Lower-case, sanitised email, or '' when it is not a valid address.
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	public static function normalize_email( $raw ) {
		$email = strtolower( sanitize_email( trim( (string) $raw ) ) );
		return ( '' !== $email && is_email( $email ) ) ? $email : '';
	}

	/**
	 * Comparable form of a phone number: its last 10 digits, so
	 * "+92-300-1234567", "0300 1234567" and "923001234567" are the same.
	 * Numbers with fewer than 7 digits are not compared ('').
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	public static function normalize_phone( $raw ) {
		$digits = preg_replace( '/\D+/', '', (string) $raw );
		if ( strlen( $digits ) < 7 ) {
			return '';
		}
		return strlen( $digits ) > 10 ? substr( $digits, -10 ) : $digits;
	}

	/**
	 * Statuses that count as an existing record (trash and auto-drafts do not).
	 *
	 * @return string[]
	 */
	private static function live_statuses() {
		return array( 'publish', 'draft', 'pending', 'private', 'future' );
	}

	/**
	 * The record of this type that already uses an email.
	 *
	 * @param string $type    Post type.
	 * @param string $email   Email (any case).
	 * @param int    $exclude Post ID to ignore (the one being edited).
	 * @return int Post ID, or 0.
	 */
	public static function find_by_email( $type, $email, $exclude = 0 ) {
		$email = self::normalize_email( $email );
		if ( '' === $email || ! self::is_person_type( $type ) ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => self::live_statuses(),
				'numberposts'      => 2,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin save/import only.
					array(
						'key'   => self::EMAIL,
						'value' => $email,
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			// The meta comparison is case-insensitive in MySQL; confirm in PHP.
			if ( (int) $id !== (int) $exclude && self::normalize_email( get_post_meta( $id, self::EMAIL, true ) ) === $email ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/**
	 * The record of this type that already uses a phone number.
	 *
	 * @param string $type    Post type.
	 * @param string $phone   Phone, any format.
	 * @param int    $exclude Post ID to ignore.
	 * @return int Post ID, or 0.
	 */
	public static function find_by_phone( $type, $phone, $exclude = 0 ) {
		$key = self::normalize_phone( $phone );
		if ( '' === $key || ! self::is_person_type( $type ) ) {
			return 0;
		}
		$map = self::phone_map( $type );
		foreach ( isset( $map[ $key ] ) ? $map[ $key ] : array() as $id ) {
			if ( (int) $id !== (int) $exclude ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/**
	 * Normalised phone => post IDs for one type, built once per request.
	 *
	 * @param string $type Post type.
	 * @return array<string,int[]>
	 */
	private static function phone_map( $type ) {
		if ( isset( self::$phones[ $type ] ) ) {
			return self::$phones[ $type ];
		}
		$map = array();
		foreach ( self::ids_with_meta( $type, self::PHONE ) as $id ) {
			$key = self::normalize_phone( get_post_meta( $id, self::PHONE, true ) );
			if ( '' !== $key ) {
				$map[ $key ][] = (int) $id;
			}
		}
		self::$phones[ $type ] = $map;
		return $map;
	}

	/**
	 * Record a phone written during this request (the import), so later rows
	 * of the same file see it.
	 *
	 * @param string $type    Post type.
	 * @param int    $post_id Post ID.
	 * @param string $phone   Phone.
	 */
	public static function remember_phone( $type, $post_id, $phone ) {
		$key = self::normalize_phone( $phone );
		if ( '' === $key ) {
			return;
		}
		self::phone_map( $type );
		if ( ! isset( self::$phones[ $type ][ $key ] ) || ! in_array( (int) $post_id, self::$phones[ $type ][ $key ], true ) ) {
			self::$phones[ $type ][ $key ][] = (int) $post_id;
		}
	}

	/**
	 * Drop the per-request caches after a person is saved.
	 */
	public static function forget() {
		self::$phones = array();
		self::$dupes  = array();
	}

	/**
	 * IDs of live records of a type that have a non-empty meta value.
	 *
	 * @param string $type Post type.
	 * @param string $key  Meta key.
	 * @return int[]
	 */
	private static function ids_with_meta( $type, $key ) {
		$ids = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => self::live_statuses(),
				'numberposts'      => 5000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- Admin-only; a college has hundreds of people.
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only.
					array(
						'key'     => $key,
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);
		$ids = array_map( 'intval', $ids );
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
		}
		return $ids;
	}

	/**
	 * Why an email cannot be saved on a record, or '' when it can.
	 *
	 * @param string $type    Post type.
	 * @param int    $post_id Record being saved (0 for a new one).
	 * @param string $raw     Email as entered.
	 * @return string Message for the editor.
	 */
	public static function email_problem( $type, $post_id, $raw ) {
		if ( '' === trim( (string) $raw ) ) {
			return __( 'Email is required for consultants and team members.', 'medical-college-manager' );
		}
		$email = self::normalize_email( $raw );
		if ( '' === $email ) {
			/* translators: %s: the value entered. */
			return sprintf( __( '"%s" is not a valid email address.', 'medical-college-manager' ), (string) $raw );
		}
		$other = self::find_by_email( $type, $email, $post_id );
		if ( $other ) {
			return sprintf(
				/* translators: 1: email, 2: name of the other record. */
				__( '%1$s is already used by "%2$s". Each person has one record: add this department to that record instead.', 'medical-college-manager' ),
				$email,
				get_the_title( $other )
			);
		}
		return '';
	}

	/**
	 * Remember a "not saved" message for the next screen.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $message Message.
	 */
	public static function remember_notice( $post_id, $message ) {
		set_transient(
			self::NOTICE . get_current_user_id(),
			array(
				'post'    => (int) $post_id,
				'message' => (string) $message,
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Show the "email not saved" notice on the edit screen.
	 */
	public static function notice() {
		$key  = self::NOTICE . get_current_user_id();
		$data = get_transient( $key );
		if ( ! is_array( $data ) || empty( $data['message'] ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-error is-dismissible"><p>';
		/* translators: %s: the reason. */
		echo esc_html( sprintf( __( 'Email not saved: %s', 'medical-college-manager' ), (string) $data['message'] ) );
		echo '</p></div>';
	}

	/**
	 * Editor script: blocks saving until a valid, unused email is entered and
	 * warns about a phone used by someone else.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! self::is_person_type( $screen->post_type ) ) {
			return;
		}
		$rel = 'assets/js/mcm-people-admin.js';
		$ver = class_exists( 'MCM_Assets' ) ? MCM_Assets::file_ver( $rel ) : MCM_VERSION;
		wp_enqueue_script( 'mcm-people-admin', MCM_URL . $rel, array(), $ver, true );
		wp_localize_script(
			'mcm-people-admin',
			'MCMPeople',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'mcm_person_check' ),
				'type'  => $screen->post_type,
				'i18n'  => array(
					'required' => __( 'Email is required. Add it in the Details box before saving.', 'medical-college-manager' ),
					'invalid'  => __( 'Enter a valid email address, e.g. name@gmc.edu.pk.', 'medical-college-manager' ),
					/* translators: %s: name of the other record. */
					'taken'    => __( 'This email is already used by "%s". Each person has one record: open it and add this department there.', 'medical-college-manager' ),
					/* translators: %s: name of the other record. */
					'phone'    => __( 'Note: this phone number is also on "%s". Saving is allowed (for a shared office line).', 'medical-college-manager' ),
					'open'     => __( 'Open that record', 'medical-college-manager' ),
					'askAdmin' => __( 'You cannot edit that record: ask an administrator to add your department to it.', 'medical-college-manager' ),
				),
			)
		);
	}

	/**
	 * Who else uses an email or phone (edit screen, AJAX).
	 */
	public static function ajax_check() {
		check_ajax_referer( 'mcm_person_check', 'nonce' );
		$type    = sanitize_key( MCM_Safe::post( 'type' ) );
		$post_id = absint( MCM_Safe::post( 'post_id' ) );
		$ptype   = get_post_type_object( $type );
		if ( ! self::is_person_type( $type ) || ! $ptype || ! current_user_can( $ptype->cap->edit_posts ) || ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) ) {
			wp_send_json_error( null, 403 );
		}
		wp_send_json_success(
			array(
				'email' => self::describe( self::find_by_email( $type, MCM_Safe::post( 'email' ), $post_id ) ),
				'phone' => self::describe( self::find_by_phone( $type, MCM_Safe::post( 'phone' ), $post_id ) ),
			)
		);
	}

	/**
	 * Name and (when the user may edit it) edit link of a record, or null.
	 *
	 * @param int $id Post ID.
	 * @return array|null
	 */
	private static function describe( $id ) {
		if ( ! $id ) {
			return null;
		}
		return array(
			'name' => wp_strip_all_tags( get_the_title( $id ) ),
			'url'  => current_user_can( 'edit_post', $id ) ? (string) get_edit_post_link( $id, 'raw' ) : '',
		);
	}

	/**
	 * Posts of a type that share an email with another record.
	 *
	 * @param string $type Post type.
	 * @return array<int,int> Post ID => ID of the record it duplicates.
	 */
	public static function duplicates( $type ) {
		if ( isset( self::$dupes[ $type ] ) ) {
			return self::$dupes[ $type ];
		}
		$by_email = array();
		foreach ( self::ids_with_meta( $type, self::EMAIL ) as $id ) {
			$email = strtolower( trim( (string) get_post_meta( $id, self::EMAIL, true ) ) );
			if ( '' !== $email ) {
				$by_email[ $email ][] = $id;
			}
		}
		$out = array();
		foreach ( $by_email as $ids ) {
			if ( count( $ids ) > 1 ) {
				sort( $ids );
				foreach ( $ids as $id ) {
					$out[ $id ] = $id === $ids[0] ? $ids[1] : $ids[0];
				}
			}
		}
		self::$dupes[ $type ] = $out;
		return $out;
	}

	/**
	 * "No email" / "Same email as …" next to the title in the list.
	 *
	 * @param string[] $states Post states.
	 * @param WP_Post  $post   Post.
	 * @return string[]
	 */
	public static function post_states( $states, $post ) {
		if ( ! is_admin() || ! self::is_person_type( $post->post_type ) || 'auto-draft' === $post->post_status ) {
			return $states;
		}
		if ( '' === trim( (string) get_post_meta( $post->ID, self::EMAIL, true ) ) ) {
			$states['mcm_no_email'] = __( 'No email', 'medical-college-manager' );
			return $states;
		}
		$dupes = self::duplicates( $post->post_type );
		if ( isset( $dupes[ $post->ID ] ) ) {
			/* translators: %s: name of the other record. */
			$states['mcm_same_email'] = sprintf( __( 'Same email as: %s', 'medical-college-manager' ), wp_strip_all_tags( get_the_title( $dupes[ $post->ID ] ) ) );
		}
		return $states;
	}

	/**
	 * Add published people with no email, or a shared one, to "Needs attention".
	 *
	 * @param int[]  $ids  Flagged IDs so far.
	 * @param string $type Post type.
	 * @return int[]
	 */
	public static function flagged_ids( $ids, $type ) {
		if ( ! self::is_person_type( $type ) ) {
			return $ids;
		}
		$has   = self::ids_with_meta( $type, self::EMAIL );
		$all   = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'numberposts'      => 5000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- Admin-only count.
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		$all   = array_map( 'intval', $all );
		$extra = array_merge( array_diff( $all, $has ), array_intersect( $all, array_keys( self::duplicates( $type ) ) ) );
		return array_values( array_unique( array_merge( array_map( 'intval', (array) $ids ), $extra ) ) );
	}
}
