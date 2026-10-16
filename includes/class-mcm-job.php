<?php
/**
 * Jobs / vacancies.
 *
 * A job is open until its effective deadline: the extended last date when one
 * is set, otherwise the last date, at the closing time (23:59 when empty), in
 * the site's timezone. Closed jobs leave the listings (unless a listing asks
 * for status="closed" or "all") but keep their single page. A job with a future
 * publication date stays hidden from visitors until that day.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job status, deadline bookkeeping, listing filters and admin integration.
 */
class MCM_Job {

	/** Post type. */
	const TYPE = 'mcm_job';

	/** Computed meta key: effective deadline as "Y-m-d H:i:s", site time. */
	const EFFECTIVE = 'mcm_job_effective_deadline';

	/** Option guarding the one-time install step. */
	const INSTALLED = 'mcm_job_installed';

	/** Meta keys whose change requires the effective deadline to be recomputed. */
	const DEADLINE_KEYS = array( 'mcm_job_deadline', 'mcm_job_deadline_time', 'mcm_job_extended_deadline' );

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 3 );
		add_action( 'save_post_' . self::TYPE, array( __CLASS__, 'after_save' ), 20, 2 );

		add_filter( 'mcm_query_args', array( __CLASS__, 'query_args' ), 10, 2 );
		add_filter( 'mcm_print_titles', array( __CLASS__, 'print_title' ) );
		add_action( 'template_redirect', array( __CLASS__, 'hide_upcoming_single' ) );

		add_filter( 'manage_' . self::TYPE . '_posts_columns', array( __CLASS__, 'admin_columns' ) );
		add_action( 'manage_' . self::TYPE . '_posts_custom_column', array( __CLASS__, 'admin_column_value' ), 10, 2 );
		add_filter( 'manage_edit-' . self::TYPE . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'admin_sort' ) );
		add_action( 'admin_notices', array( __CLASS__, 'missing_deadline_notice' ) );
	}

	/*
	 * ----------------------------------------------------------------
	 * Install and meta
	 * ----------------------------------------------------------------
	 */

	/**
	 * One-time install (version check and activation; guarded by an option):
	 * seed the default job categories and make jobs printable on sites whose
	 * print settings were saved before jobs existed.
	 */
	public static function install() {
		if ( get_option( self::INSTALLED ) ) {
			return;
		}
		if ( taxonomy_exists( 'mcm_job_cat' ) ) {
			foreach ( array(
				__( 'Teaching Faculty', 'medical-college-manager' ),
				__( 'Clinical', 'medical-college-manager' ),
				__( 'Administrative', 'medical-college-manager' ),
				__( 'Support Staff', 'medical-college-manager' ),
			) as $name ) {
				if ( ! term_exists( $name, 'mcm_job_cat' ) ) {
					wp_insert_term( $name, 'mcm_job_cat' );
				}
			}
		}
		$settings = get_option( 'mcm_settings' );
		if ( is_array( $settings ) && isset( $settings['print_types'] ) && is_array( $settings['print_types'] ) && ! in_array( self::TYPE, $settings['print_types'], true ) ) {
			$settings['print_types'][] = self::TYPE;
			update_option( 'mcm_settings', $settings );
		}
		update_option( self::INSTALLED, defined( 'MCM_VERSION' ) ? MCM_VERSION : '1', false );
	}

	/**
	 * Register the computed effective-deadline key: readable over REST, never
	 * writable (it is derived from the deadline fields).
	 */
	public static function register_meta() {
		register_post_meta(
			self::TYPE,
			self::EFFECTIVE,
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => '__return_false',
			)
		);
	}

	/**
	 * Recompute the effective deadline whenever one of its inputs changes,
	 * whatever the write path (meta box, CSV import, REST).
	 *
	 * @param int|int[] $meta_ids  Meta ID(s).
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 */
	public static function on_meta_change( $meta_ids, $object_id, $meta_key ) {
		if ( ! in_array( $meta_key, self::DEADLINE_KEYS, true ) || self::TYPE !== get_post_type( $object_id ) ) {
			return;
		}
		self::sync_effective( (int) $object_id );
	}

	/**
	 * After a job is saved: make sure the effective deadline is current and
	 * warn the editor when a published job has no last date.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function after_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		self::sync_effective( (int) $post_id );
		if ( $post instanceof WP_Post && 'publish' === $post->post_status && false === MCM_Meta::parse_date( MCM_Safe::meta( $post_id, 'mcm_job_deadline' ) ) ) {
			set_transient( 'mcm_job_no_deadline_' . get_current_user_id(), (int) $post_id, 5 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Store the effective deadline ("Y-m-d H:i:s", site time) or remove it
	 * when there is no valid date.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function sync_effective( $post_id ) {
		$effective = self::effective_deadline( $post_id );
		if ( null === $effective ) {
			delete_post_meta( $post_id, self::EFFECTIVE );
			return;
		}
		$value = $effective->format( 'Y-m-d H:i:s' );
		if ( MCM_Safe::meta( $post_id, self::EFFECTIVE ) !== $value ) {
			update_post_meta( $post_id, self::EFFECTIVE, $value );
		}
	}

	/*
	 * ----------------------------------------------------------------
	 * Dates and status
	 * ----------------------------------------------------------------
	 */

	/**
	 * The effective deadline: extended last date if valid, else the last date,
	 * at the closing time (23:59 when empty), in the site timezone.
	 *
	 * @param int $post_id Post ID.
	 * @return DateTimeImmutable|null Null when no valid date is set.
	 */
	public static function effective_deadline( $post_id ) {
		$date = MCM_Meta::parse_date( MCM_Safe::meta( $post_id, 'mcm_job_extended_deadline' ) );
		if ( false === $date ) {
			$date = MCM_Meta::parse_date( MCM_Safe::meta( $post_id, 'mcm_job_deadline' ) );
		}
		if ( false === $date ) {
			return null;
		}
		$time = MCM_Meta::parse_time( MCM_Safe::meta( $post_id, 'mcm_job_deadline_time' ) );
		$time = false === $time ? '23:59' : $time;
		$dt   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date . ' ' . $time . ':59', wp_timezone() );
		return $dt ? $dt : null;
	}

	/**
	 * Whether a valid extended last date replaces the original one.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_extended( $post_id ) {
		$ext  = MCM_Meta::parse_date( MCM_Safe::meta( $post_id, 'mcm_job_extended_deadline' ) );
		$orig = MCM_Meta::parse_date( MCM_Safe::meta( $post_id, 'mcm_job_deadline' ) );
		return false !== $ext && $ext !== $orig;
	}

	/**
	 * Publication date (Y-m-d): the field, else the post date.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function publish_date( $post_id ) {
		$date = MCM_Meta::parse_date( MCM_Safe::meta( $post_id, 'mcm_job_publish_date' ) );
		return false !== $date ? $date : (string) get_post_time( 'Y-m-d', false, $post_id );
	}

	/**
	 * Days before the deadline during which a job counts as "closing soon".
	 *
	 * @return int
	 */
	public static function closing_soon_days() {
		$days = class_exists( 'MCM_Settings' ) ? (int) MCM_Settings::value( 'job_closing_days' ) : 3;
		return max( 0, (int) apply_filters( 'mcm_job_closing_soon_days', $days ) );
	}

	/**
	 * Status of a job: upcoming | open | closing | extended | closed.
	 *
	 * Uses the site clock and timezone only (current_time / wp_timezone), so a
	 * last date of today with no closing time is open until 23:59 local time.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function status( $post_id ) {
		$now = new DateTimeImmutable( 'now', wp_timezone() );

		if ( self::publish_date( $post_id ) > $now->format( 'Y-m-d' ) ) {
			$status = 'upcoming';
		} else {
			$deadline = self::effective_deadline( $post_id );
			if ( null === $deadline ) {
				$status = 'open'; // No last date set: never closes by itself.
			} elseif ( $now > $deadline ) {
				$status = 'closed';
			} elseif ( self::is_extended( $post_id ) ) {
				$status = 'extended';
			} elseif ( $deadline->getTimestamp() - $now->getTimestamp() <= self::closing_soon_days() * DAY_IN_SECONDS ) {
				$status = 'closing';
			} else {
				$status = 'open';
			}
		}
		return (string) apply_filters( 'mcm_job_status', $status, $post_id );
	}

	/**
	 * Whether applications are accepted (open, closing soon or extended).
	 *
	 * @param string $status Status from status().
	 * @return bool
	 */
	public static function is_accepting( $status ) {
		return in_array( $status, array( 'open', 'closing', 'extended' ), true );
	}

	/**
	 * Human label for a status.
	 *
	 * @param string $status Status from status().
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'upcoming' => __( 'Upcoming', 'medical-college-manager' ),
			'open'     => __( 'Open', 'medical-college-manager' ),
			'closing'  => __( 'Closing soon', 'medical-college-manager' ),
			'extended' => __( 'Extended', 'medical-college-manager' ),
			'closed'   => __( 'Closed', 'medical-college-manager' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : '';
	}

	/**
	 * Status badge markup.
	 *
	 * @param string $status Status from status().
	 * @return string
	 */
	public static function badge( $status ) {
		$label = self::status_label( $status );
		return '' === $label ? '' : '<span class="mcm-badge mcm-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Last date for display: the effective date and, when extended, the
	 * original date struck through. Escaped HTML.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function last_date_html( $post_id ) {
		$deadline = self::effective_deadline( $post_id );
		if ( null === $deadline ) {
			return '';
		}
		$fmt  = get_option( 'date_format' );
		$html = '';
		if ( self::is_extended( $post_id ) ) {
			$orig = MCM_Meta::parse_date( MCM_Safe::meta( $post_id, 'mcm_job_deadline' ) );
			if ( false !== $orig ) {
				$html .= '<del class="mcm-job-old-date">' . esc_html( MCM_Query::fmt_date( $orig ) ) . '</del> ';
			}
		}
		$html .= '<span class="mcm-job-date">' . esc_html( wp_date( $fmt, $deadline->getTimestamp() ) ) . '</span>';
		$time  = MCM_Meta::parse_time( MCM_Safe::meta( $post_id, 'mcm_job_deadline_time' ) );
		if ( false !== $time ) {
			$html .= ' <span class="mcm-job-time">' . esc_html( wp_date( get_option( 'time_format' ), $deadline->getTimestamp() ) ) . '</span>';
		}
		return $html;
	}

	/*
	 * ----------------------------------------------------------------
	 * Listings
	 * ----------------------------------------------------------------
	 */

	/**
	 * Normalise the status attribute: open (default) | closed | all.
	 *
	 * @param mixed $value Raw attribute.
	 * @return string
	 */
	public static function status_attr( $value ) {
		$value = sanitize_key( MCM_Safe::str( $value ) );
		return in_array( $value, array( 'open', 'closed', 'all' ), true ) ? $value : 'open';
	}

	/**
	 * Meta query hiding jobs whose publication date is still in the future.
	 *
	 * @return array
	 */
	public static function published_meta_query() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => 'mcm_job_publish_date',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'mcm_job_publish_date',
				'value'   => '',
				'compare' => '=',
			),
			array(
				'key'     => 'mcm_job_publish_date',
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '<=',
				'type'    => 'DATE',
			),
		);
	}

	/**
	 * Filter job listings by status and order them by deadline.
	 *
	 * @param array $args WP_Query arguments.
	 * @param array $a    Parsed listing attributes.
	 * @return array
	 */
	public static function query_args( $args, $a ) {
		if ( ! isset( $a['type'] ) || self::TYPE !== $a['type'] ) {
			return $args;
		}
		$status = self::status_attr( isset( $a['status'] ) ? $a['status'] : '' );

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Jobs listing: status is defined by the deadline meta.
		$meta = array(
			'relation' => 'AND',
			self::published_meta_query(), // Upcoming jobs are always hidden.
		);
		if ( 'open' === $status && apply_filters( 'mcm_job_hide_closed', true, $a ) ) {
			$meta[] = MCM_Query::expiry_meta_query( self::EFFECTIVE, 'DATETIME' );
		} elseif ( 'closed' === $status ) {
			$meta[] = array(
				'key'     => self::EFFECTIVE,
				'value'   => current_time( 'Y-m-d H:i:s' ),
				'compare' => '<',
				'type'    => 'DATETIME',
			);
		}
		if ( ! empty( $args['meta_query'] ) ) {
			$meta[] = $args['meta_query'];
		}
		$args['meta_query'] = $meta;
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		// Soonest deadline first (most recently closed first for closed lists),
		// unless the author asked for a specific order.
		$no_preference = empty( $a['meta_key'] ) && ( empty( $a['orderby'] ) || 'date' === $a['orderby'] );
		if ( $no_preference ) {
			$args = MCM_Query::order_by_optional_meta( $args, self::EFFECTIVE, 'CHAR', 'closed' === $status ? 'DESC' : 'ASC' );
		}
		return $args;
	}

	/**
	 * Printed heading for job lists.
	 *
	 * @param array $titles Type => heading.
	 * @return array
	 */
	public static function print_title( $titles ) {
		$titles[ self::TYPE ] = __( 'Vacancies', 'medical-college-manager' );
		return $titles;
	}

	/**
	 * An upcoming job (publication date in the future) is a 404 for visitors;
	 * editors can still preview it.
	 */
	public static function hide_upcoming_single() {
		if ( ! is_singular( self::TYPE ) ) {
			return;
		}
		$id = (int) get_queried_object_id();
		if ( $id && 'upcoming' === self::status( $id ) && ! current_user_can( 'edit_post', $id ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/*
	 * ----------------------------------------------------------------
	 * Admin
	 * ----------------------------------------------------------------
	 */

	/**
	 * Add Last date and Status columns before the Date column.
	 *
	 * @param array $columns List-table columns.
	 * @return array
	 */
	public static function admin_columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : null;
		unset( $columns['date'] );
		$columns['mcm_job_last_date'] = __( 'Last date', 'medical-college-manager' );
		$columns['mcm_job_status']    = __( 'Status', 'medical-college-manager' );
		if ( null !== $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	/**
	 * Print a job column value.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function admin_column_value( $column, $post_id ) {
		if ( 'mcm_job_last_date' === $column ) {
			$html = self::last_date_html( $post_id );
			echo '' !== $html ? wp_kses_post( $html ) : '&mdash;';
		} elseif ( 'mcm_job_status' === $column ) {
			echo wp_kses_post( self::badge( self::status( $post_id ) ) );
		}
	}

	/**
	 * Make Last date sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable_columns( $columns ) {
		$columns['mcm_job_last_date'] = 'mcm_job_last_date';
		return $columns;
	}

	/**
	 * Sort the admin job list by effective deadline, keeping jobs without one.
	 *
	 * @param WP_Query $query The query.
	 */
	public static function admin_sort( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'mcm_job_last_date' !== $query->get( 'orderby' ) ) {
			return;
		}
		$args = MCM_Query::order_by_optional_meta( array(), self::EFFECTIVE, 'CHAR', 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ? 'DESC' : 'ASC' );
		$query->set( 'mcm_order_meta', $args['mcm_order_meta'] );
		$query->set( 'orderby', 'none' );
	}

	/**
	 * Tell the editor a published job has no last date.
	 */
	public static function missing_deadline_notice() {
		$key = 'mcm_job_no_deadline_' . get_current_user_id();
		$id  = get_transient( $key );
		if ( ! $id ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'This job has no "Last date to apply". It will stay open on the website until you add one.', 'medical-college-manager' ) . '</p></div>';
	}
}
