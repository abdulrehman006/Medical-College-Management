<?php
/**
 * Notice Board — cached notice feed plus three front-end presentations
 * (scrolling ticker, vertical auto-scroll board, and a static list) with
 * shortcodes. Handles pinning, priority, "new" badges and auto-expiry.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notice ticker, notice board and notices list.
 */
class MCM_Notice {

	const CACHE_KEY = 'mcm_notice_feed';
	const FEED_MAX  = 50; // notices held in the cached feed.

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_shortcode( 'mcm_notices', array( $this, 'sc_list' ) );
		add_shortcode( 'mcm_notice_ticker', array( $this, 'sc_ticker' ) );
		add_shortcode( 'mcm_notice_board', array( $this, 'sc_board' ) );

		// Hide expired notices from the generic archive/list queries.
		add_filter( 'mcm_query_args', array( $this, 'archive_expiry' ), 10, 2 );
		// The /notices/ archive reads best as a list.
		add_filter( 'mcm_archive_view', array( $this, 'archive_view' ), 10, 2 );

		// Invalidate the cached feed whenever notices, jobs or tenders change.
		add_action( 'save_post_mcm_notice', array( __CLASS__, 'clear_cache' ) );
		add_action( 'save_post_mcm_job', array( __CLASS__, 'clear_cache' ) );
		add_action( 'save_post_mcm_tender', array( __CLASS__, 'clear_cache' ) );
		// Fields written after the post (CSV import, REST, meta box).
		add_action( 'added_post_meta', array( __CLASS__, 'maybe_clear_on_meta' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'maybe_clear_on_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'maybe_clear_on_meta' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'clear_cache' ) );
		add_action( 'trashed_post', array( __CLASS__, 'clear_cache' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'clear_cache' ) );
	}

	/**
	 * Clear the cached notice feed.
	 */
	public static function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Clear the feed when a field that decides board membership changes.
	 *
	 * @param int|int[] $meta_ids  Meta ID(s).
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 */
	public static function maybe_clear_on_meta( $meta_ids, $object_id, $meta_key ) {
		if ( preg_match( '/^mcm_(notice|job|tender)_/', (string) $meta_key ) ) {
			self::clear_cache();
		}
	}

	/**
	 * Which sources a feed shows: the include="…" attribute when given,
	 * otherwise notices plus the sources ticked in Settings. Filterable via
	 * mcm_notice_feed_types.
	 *
	 * @param array $a Parsed attributes.
	 * @return string[] Subset of notice, job, tender.
	 */
	public static function feed_types( $a = array() ) {
		$known   = array( 'notice', 'job', 'tender' );
		$include = isset( $a['include'] ) ? trim( MCM_Safe::str( $a['include'] ) ) : '';
		if ( '' !== $include ) {
			$types = array_map( 'sanitize_key', array_map( 'trim', explode( ',', $include ) ) );
		} else {
			$types   = array( 'notice' );
			$sources = class_exists( 'MCM_Settings' ) ? MCM_Settings::value( 'notice_sources' ) : array();
			if ( is_array( $sources ) ) {
				$types = array_merge( $types, $sources );
			}
		}
		$types = (array) apply_filters( 'mcm_notice_feed_types', $types, $a );
		$types = array_values( array_intersect( $known, array_map( 'strval', $types ) ) );
		// No jobs while the Jobs module is down (the type is then not viewable).
		if ( ! class_exists( 'MCM_Job' ) || ! is_post_type_viewable( 'mcm_job' ) ) {
			$types = array_values( array_diff( $types, array( 'job' ) ) );
		}
		return $types;
	}

	/**
	 * Meta query: the item has not opted out of the notice board.
	 *
	 * @param string $key On-board meta key.
	 * @return array
	 */
	private static function on_board_meta_query( $key ) {
		return array(
			'relation' => 'OR',
			array(
				'key'     => $key,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => $key,
				'value'   => array( '', 'Yes' ),
				'compare' => 'IN',
			),
		);
	}

	/**
	 * Show the /notices/ archive as a list.
	 *
	 * @param string $view Layout.
	 * @param string $cpt  Post type.
	 */
	public function archive_view( $view, $cpt ) {
		return 'mcm_notice' === $cpt ? 'list' : $view;
	}

	/**
	 * Hide expired notices from notice listings.
	 *
	 * @param array $args Query arguments.
	 * @param array $a    Parsed attributes.
	 */
	public function archive_expiry( $args, $a ) {
		if ( 'mcm_notice' === $a['type'] && empty( $args['meta_query'] ) ) {
			$args['meta_query'] = self::expiry_meta_query(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		return $args;
	}

	/** Meta query that excludes notices whose expiry date has passed. */
	public static function expiry_meta_query() {
		return MCM_Query::expiry_meta_query( 'mcm_notice_expiry' );
	}

	/*
	 * ----------------------------------------------------------------
	 * Feed (cached, sorted: pinned -> priority -> date)
	 * ----------------------------------------------------------------
	 */

	/**
	 * IDs on the board, sorted pinned, then priority, then date (cached).
	 *
	 * Notices, plus open jobs and open tenders when requested. Never creates
	 * notice posts: jobs and tenders are listed from their own records and
	 * drop off once their deadline has passed (within the 10-minute cache).
	 *
	 * @param string[]|null $types Sources (notice/job/tender); null = defaults.
	 * @return int[]
	 */
	public static function get_feed( $types = null ) {
		$types = null === $types ? self::feed_types() : array_values( array_intersect( array( 'notice', 'job', 'tender' ), (array) $types ) );
		sort( $types );
		$sig   = implode( ',', $types );
		$cache = get_transient( self::CACHE_KEY );
		$cache = is_array( $cache ) ? $cache : array();
		if ( isset( $cache[ $sig ] ) && is_array( $cache[ $sig ] ) ) {
			return $cache[ $sig ];
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Board membership and expiry are defined by meta; the result is cached.
		$queries = array();
		if ( in_array( 'notice', $types, true ) ) {
			$queries['mcm_notice'] = self::expiry_meta_query();
		}
		if ( in_array( 'job', $types, true ) ) {
			$queries['mcm_job'] = array(
				'relation' => 'AND',
				self::on_board_meta_query( 'mcm_job_on_board' ),
				MCM_Job::published_meta_query(),
				MCM_Query::expiry_meta_query( MCM_Job::EFFECTIVE, 'DATETIME' ),
			);
		}
		if ( in_array( 'tender', $types, true ) ) {
			$queries['mcm_tender'] = array(
				'relation' => 'AND',
				self::on_board_meta_query( 'mcm_tender_on_board' ),
				MCM_Query::expiry_meta_query( 'mcm_tender_close' ),
			);
		}

		$ids = array();
		foreach ( $queries as $post_type => $meta_query ) {
			$query = new WP_Query(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'publish',
					'posts_per_page'         => self::FEED_MAX,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'meta_query'             => $meta_query,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			$ids   = array_merge( $ids, array_map( 'intval', $query->posts ) );
		}
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		// Prime meta and term caches once for the whole feed (sort keys, badges).
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
			update_object_term_cache( $ids, array_keys( $queries ) );
		}
		usort(
			$ids,
			function ( $x, $y ) {
				$kx = self::sort_key( $x );
				$ky = self::sort_key( $y );
				// Pinned desc, then priority desc, then timestamp desc.
				$cmp = $ky[0] <=> $kx[0];
				if ( 0 === $cmp ) {
					$cmp = $ky[1] <=> $kx[1];
				}
				if ( 0 === $cmp ) {
					$cmp = $ky[2] <=> $kx[2];
				}
				return $cmp;
			}
		);

		$cache[ $sig ] = $ids;
		set_transient( self::CACHE_KEY, $cache, 10 * MINUTE_IN_SECONDS );
		return $ids;
	}

	/**
	 * Sort key for one notice: pinned, priority, timestamp.
	 *
	 * @param int $id Post ID.
	 */
	private static function sort_key( $id ) {
		if ( 'mcm_notice' !== get_post_type( $id ) ) {
			return array( 0, 1, self::notice_ts( $id ) );
		}
		$pinned = ( 'Yes' === MCM_Safe::meta( $id, 'mcm_notice_pinned' ) ) ? 1 : 0;
		$map    = array(
			'Urgent'    => 3,
			'Important' => 2,
			'Normal'    => 1,
		);
		$p      = MCM_Safe::meta( $id, 'mcm_notice_priority' );
		$prio   = isset( $map[ $p ] ) ? $map[ $p ] : 1;
		return array( $pinned, $prio, self::notice_ts( $id ) );
	}

	/**
	 * Filter the cached feed by category / department / priority / recency and
	 * cap to the requested count.
	 *
	 * @param array $a Parsed attributes.
	 */
	private static function feed_for( $a ) {
		$ids    = self::get_feed( self::feed_types( $a ) );
		$cat    = trim( (string) $a['category'] );
		$cats   = '' !== $cat ? array_filter( array_map( 'trim', explode( ',', $cat ) ) ) : array();
		$dept   = trim( (string) $a['department'] );
		$prio   = strtolower( trim( (string) $a['priority'] ) );
		$days   = (int) $a['days'];
		$cutoff = $days > 0 ? ( time() - $days * DAY_IN_SECONDS ) : 0;
		$count  = max( 1, (int) $a['count'] );

		$out = array();
		foreach ( $ids as $id ) {
			$is_notice = 'mcm_notice' === get_post_type( $id );
			// Category and priority belong to notices; jobs and tenders pass.
			if ( $is_notice && $cats && ! has_term( $cats, 'mcm_notice_cat', $id ) ) {
				continue;
			}
			if ( '' !== $dept && ! has_term( $dept, 'mcm_department', $id ) ) {
				continue;
			}
			if ( $is_notice && '' !== $prio && strtolower( (string) MCM_Safe::meta( $id, 'mcm_notice_priority' ) ) !== $prio ) {
				continue;
			}
			if ( $cutoff && self::notice_ts( $id ) < $cutoff ) {
				continue;
			}
			$out[] = $id;
			if ( count( $out ) >= $count ) {
				break;
			}
		}
		return $out;
	}

	/*
	 * ----------------------------------------------------------------
	 * Per-notice helpers
	 * ----------------------------------------------------------------
	 */

	/**
	 * Timestamp of a notice: its Notice Date, else its publish date.
	 *
	 * @param int $id Post ID.
	 */
	private static function notice_ts( $id ) {
		$keys = array(
			'mcm_notice' => 'mcm_notice_date',
			'mcm_job'    => 'mcm_job_publish_date',
			'mcm_tender' => 'mcm_tender_open',
		);
		$type = (string) get_post_type( $id );
		$d    = isset( $keys[ $type ] ) ? MCM_Safe::meta( $id, $keys[ $type ] ) : '';
		if ( $d ) {
			$t = strtotime( $d );
			if ( $t ) {
				return $t;
			}
		}
		return (int) get_post_time( 'U', true, $id );
	}

	/**
	 * Notice date formatted for display.
	 *
	 * @param int $id Post ID.
	 */
	private static function notice_date( $id ) {
		return date_i18n( get_option( 'date_format' ), self::notice_ts( $id ) );
	}

	/**
	 * Where a notice links: its Link URL, else its own page.
	 *
	 * @param int $id Post ID.
	 */
	private static function notice_link( $id ) {
		$u = 'mcm_notice' === get_post_type( $id ) ? MCM_Safe::meta( $id, 'mcm_notice_url' ) : '';
		return $u ? $u : get_permalink( $id );
	}

	/**
	 * Attributes that open an external notice link in a new tab.
	 *
	 * @param int $id Post ID.
	 */
	private static function link_attrs( $id ) {
		return ( 'mcm_notice' === get_post_type( $id ) && MCM_Safe::meta( $id, 'mcm_notice_url' ) ) ? ' target="_blank" rel="noopener noreferrer"' : '';
	}

	/**
	 * Whether a notice is recent enough for the "New" badge.
	 *
	 * @param int $id Post ID.
	 */
	private static function is_new( $id ) {
		$days = (int) apply_filters( 'mcm_notice_new_days', 7 );
		return $days > 0 && self::notice_ts( $id ) >= ( time() - $days * DAY_IN_SECONDS );
	}

	/**
	 * CSS modifier class for a notice's priority.
	 *
	 * @param int $id Post ID.
	 */
	private static function priority_class( $id ) {
		if ( 'mcm_notice' !== get_post_type( $id ) ) {
			return ' mcm-notice--' . ( 'mcm_job' === get_post_type( $id ) ? 'job' : 'tender' );
		}
		$p = strtolower( (string) MCM_Safe::meta( $id, 'mcm_notice_priority' ) );
		return $p ? ' mcm-notice--' . sanitize_html_class( $p ) : '';
	}

	/**
	 * Badge showing the notice's first category.
	 *
	 * @param int $id Post ID.
	 */
	private static function badge( $id ) {
		$type = get_post_type( $id );
		if ( 'mcm_job' === $type ) {
			return '<span class="mcm-notice-badge mcm-notice-badge--job">' . esc_html__( 'Job', 'medical-college-manager' ) . '</span>';
		}
		if ( 'mcm_tender' === $type ) {
			return '<span class="mcm-notice-badge mcm-notice-badge--tender">' . esc_html__( 'Tender', 'medical-college-manager' ) . '</span>';
		}
		$terms = get_the_terms( $id, 'mcm_notice_cat' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		$t = $terms[0];
		return '<span class="mcm-notice-badge mcm-notice-badge--' . esc_attr( $t->slug ) . '">' . esc_html( $t->name ) . '</span>';
	}

	/**
	 * "Last date 31 Oct" for a job or tender on the board (empty otherwise).
	 *
	 * @param int $id Post ID.
	 */
	private static function deadline_tag( $id ) {
		$type = get_post_type( $id );
		$ts   = 0;
		if ( 'mcm_job' === $type && class_exists( 'MCM_Job' ) ) {
			$dt = MCM_Job::effective_deadline( $id );
			$ts = $dt ? $dt->getTimestamp() : 0;
		} elseif ( 'mcm_tender' === $type ) {
			$date = MCM_Meta::parse_date( MCM_Safe::meta( $id, 'mcm_tender_close' ) );
			$ts   = false !== $date ? strtotime( $date . ' 00:00:00' ) : 0;
		}
		if ( ! $ts ) {
			return '';
		}
		$when = 'mcm_job' === $type ? wp_date( 'j M', $ts ) : date_i18n( 'j M', $ts );
		/* translators: %s: day and month, e.g. "31 Oct". */
		return '<span class="mcm-notice-deadline">' . esc_html( sprintf( __( 'Last date %s', 'medical-college-manager' ), $when ) ) . '</span>';
	}

	/**
	 * The "New" tag, or an empty string.
	 *
	 * @param int $id Post ID.
	 */
	private static function new_tag( $id ) {
		return self::is_new( $id ) ? '<span class="mcm-notice-new">' . esc_html__( 'New', 'medical-college-manager' ) . '</span>' : '';
	}

	/*
	 * ----------------------------------------------------------------
	 * Attributes
	 * ----------------------------------------------------------------
	 */

	/**
	 * Normalised notice shortcode attributes.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public static function atts( $atts ) {
		return shortcode_atts(
			array(
				'count'      => 10,
				'category'   => '',
				'department' => '',
				'priority'   => '',
				'days'       => 0,
				'speed'      => 5,        // 1 (slow) – 10 (fast).
				'label'      => '',       // ticker label.
				'title'      => '',       // board heading.
				'height'     => 340,      // board viewport height (px).
				'more'       => 'yes',
				'show_date'  => 'yes',
				'show_badge' => 'yes',
				'include'    => '',       // notice,job,tender (default: notices + Settings).
			),
			is_array( $atts ) ? $atts : array(),
			'mcm_notice'
		);
	}

	/**
	 * Seconds per full scroll cycle, scaled by item count and speed.
	 *
	 * @param array $a Parsed attributes.
	 * @param int   $items Number of items.
	 */
	private static function duration( $a, $items ) {
		$speed = min( 10, max( 1, (int) $a['speed'] ) );
		return max( 12, (int) round( $items * ( 55 / $speed ) / 10 ) * 3 );
	}

	/*
	 * ----------------------------------------------------------------
	 * Shortcodes + renderers
	 * ----------------------------------------------------------------
	 */

	/**
	 * Shortcode callback for [mcm_notices].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_list( $atts ) {
		return self::render_list( self::atts( $atts ) );
	}
	/**
	 * Shortcode callback for [mcm_notice_ticker].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_ticker( $atts ) {
		return self::render_ticker( self::atts( $atts ) );
	}
	/**
	 * Shortcode callback for [mcm_notice_board].
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function sc_board( $atts ) {
		return self::render_board( self::atts( $atts ) );
	}

	/**
	 * Notices list, behind a render boundary.
	 *
	 * @param array $a Parsed attributes.
	 */
	public static function render_list( $a ) {
		return MCM_Safe::render(
			function () use ( $a ) {
				return MCM_Notice::build_list( $a );
			},
			'notice list'
		);
	}

	/**
	 * Unguarded builder; call render_list() instead.
	 *
	 * @internal
	 * @param array $a Attributes.
	 * @return string
	 */
	public static function build_list( $a ) {
		MCM_Assets::need();
		$ids = self::feed_for( $a );
		if ( empty( $ids ) ) {
			return '<p class="mcm-empty">' . esc_html__( 'No notices at the moment.', 'medical-college-manager' ) . '</p>';
		}
		ob_start();
		echo '<ul class="mcm-notice-list">';
		foreach ( $ids as $id ) {
			echo self::row_html( $id, $a ); // phpcs:ignore
		}
		echo '</ul>';
		return ob_get_clean();
	}

	/**
	 * Markup for one row of the notices list.
	 *
	 * @param int   $id Post ID.
	 * @param array $a  Parsed attributes.
	 */
	private static function row_html( $id, $a ) {
		$badge = 'yes' === $a['show_badge'] ? self::badge( $id ) : '';
		$date  = 'yes' === $a['show_date'] ? '<span class="mcm-notice-date">' . esc_html( self::notice_date( $id ) ) . '</span>' : '';
		return '<li class="mcm-notice-row' . self::priority_class( $id ) . '">'
			. $badge
			. '<a class="mcm-notice-link" href="' . esc_url( self::notice_link( $id ) ) . '"' . self::link_attrs( $id ) . '>' . esc_html( get_the_title( $id ) ) . '</a>'
			. self::new_tag( $id )
			. self::deadline_tag( $id )
			. $date
			. '</li>';
	}

	/**
	 * Scrolling notice ticker, behind a render boundary.
	 *
	 * @param array $a Parsed attributes.
	 */
	public static function render_ticker( $a ) {
		return MCM_Safe::render(
			function () use ( $a ) {
				return MCM_Notice::build_ticker( $a );
			},
			'notice ticker'
		);
	}

	/**
	 * Unguarded builder; call render_ticker() instead.
	 *
	 * @internal
	 * @param array $a Attributes.
	 * @return string
	 */
	public static function build_ticker( $a ) {
		MCM_Assets::need();
		$ids = self::feed_for( $a );
		if ( empty( $ids ) ) {
			return '';
		}
		$label = '' !== $a['label'] ? $a['label'] : __( 'Latest', 'medical-college-manager' );
		$dur   = self::duration( $a, count( $ids ) );

		$items = '';
		foreach ( $ids as $id ) {
			$items .= '<a class="mcm-ticker-item" href="' . esc_url( self::notice_link( $id ) ) . '"' . self::link_attrs( $id ) . '>'
				. ( 'yes' === $a['show_badge'] ? self::badge( $id ) : '' )
				. '<span class="mcm-ticker-text">' . esc_html( get_the_title( $id ) ) . '</span>'
				. self::new_tag( $id )
				. self::deadline_tag( $id )
				. '</a>';
		}

		ob_start();
		echo '<div class="mcm-ticker" role="region" aria-label="' . esc_attr( $label ) . '">';
		echo '<span class="mcm-ticker-label">&#128226; ' . esc_html( $label ) . '</span>';
		echo '<div class="mcm-ticker-viewport"><div class="mcm-ticker-track" style="animation-duration:' . (int) $dur . 's">';
		echo '<span class="mcm-ticker-group">' . $items . '</span>'; // phpcs:ignore
		// The looping copy is hidden from screen readers, so it must not take keyboard focus either.
		echo '<span class="mcm-ticker-group mcm-dup" aria-hidden="true">' . self::unfocusable( $items ) . '</span>'; // phpcs:ignore
		echo '</div></div>';
		echo '<button type="button" class="mcm-scroll-pause" aria-label="' . esc_attr__( 'Pause', 'medical-college-manager' ) . '" aria-pressed="false">&#10073;&#10073;</button>';
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Vertical auto-scrolling notice board, behind a render boundary.
	 *
	 * @param array $a Parsed attributes.
	 */
	public static function render_board( $a ) {
		return MCM_Safe::render(
			function () use ( $a ) {
				return MCM_Notice::build_board( $a );
			},
			'notice board'
		);
	}

	/**
	 * Unguarded builder; call render_board() instead.
	 *
	 * @internal
	 * @param array $a Attributes.
	 * @return string
	 */
	public static function build_board( $a ) {
		MCM_Assets::need();
		$ids = self::feed_for( $a );
		if ( empty( $ids ) ) {
			return '';
		}
		$title = '' !== $a['title'] ? $a['title'] : __( 'Notice Board', 'medical-college-manager' );
		$dur   = self::duration( $a, count( $ids ) );

		$items = '';
		foreach ( $ids as $id ) {
			$items .= '<li class="mcm-board-item' . self::priority_class( $id ) . '">'
				. '<div class="mcm-board-item-top">'
				. ( 'yes' === $a['show_badge'] ? self::badge( $id ) : '' )
				. ( 'yes' === $a['show_date'] ? '<span class="mcm-notice-date">' . esc_html( self::notice_date( $id ) ) . '</span>' : '' )
				. self::new_tag( $id )
				. self::deadline_tag( $id )
				. '</div>'
				. '<a class="mcm-board-item-title" href="' . esc_url( self::notice_link( $id ) ) . '"' . self::link_attrs( $id ) . '>' . esc_html( get_the_title( $id ) ) . '</a>'
				. '</li>';
		}

		$archive = get_post_type_archive_link( 'mcm_notice' );

		ob_start();
		echo '<div class="mcm-board">';
		echo '<div class="mcm-board-head"><span class="mcm-board-title">&#128204; ' . esc_html( $title ) . '</span>';
		echo '<button type="button" class="mcm-scroll-pause" aria-label="' . esc_attr__( 'Pause', 'medical-college-manager' ) . '" aria-pressed="false">&#10073;&#10073;</button></div>';
		echo '<div class="mcm-board-viewport" style="--mcm-board-h:' . absint( $a['height'] ) . 'px;">';
		echo '<div class="mcm-board-track" style="animation-duration:' . (int) $dur . 's">';
		echo '<ul class="mcm-board-group">' . $items . '</ul>'; // phpcs:ignore
		echo '<ul class="mcm-board-group mcm-dup" aria-hidden="true">' . self::unfocusable( $items ) . '</ul>'; // phpcs:ignore
		echo '</div></div>';
		if ( 'yes' === $a['more'] && $archive ) {
			echo '<a class="mcm-board-more" href="' . esc_url( $archive ) . '">' . esc_html__( 'View all notices', 'medical-college-manager' ) . ' &rarr;</a>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Take links and buttons out of the tab order (for the aria-hidden copy
	 * that makes the ticker and board scroll seamlessly).
	 *
	 * @param string $html Escaped item markup.
	 * @return string
	 */
	private static function unfocusable( $html ) {
		return (string) preg_replace( '/<(a|button)\b/i', '<$1 tabindex="-1"', $html );
	}
}
