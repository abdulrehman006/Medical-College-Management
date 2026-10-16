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

class MCM_Notice {

	const CACHE_KEY = 'mcm_notice_feed';
	const FEED_MAX  = 50; // notices held in the cached feed.

	public function __construct() {
		add_shortcode( 'mcm_notices', array( $this, 'sc_list' ) );
		add_shortcode( 'mcm_notice_ticker', array( $this, 'sc_ticker' ) );
		add_shortcode( 'mcm_notice_board', array( $this, 'sc_board' ) );

		// Hide expired notices from the generic archive/list queries.
		add_filter( 'mcm_query_args', array( $this, 'archive_expiry' ), 10, 2 );
		// The /notices/ archive reads best as a list.
		add_filter( 'mcm_archive_view', array( $this, 'archive_view' ), 10, 2 );

		// Invalidate the cached feed whenever notices change.
		add_action( 'save_post_mcm_notice', array( __CLASS__, 'clear_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'clear_cache' ) );
		add_action( 'trashed_post', array( __CLASS__, 'clear_cache' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'clear_cache' ) );
	}

	public static function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}

	public function archive_view( $view, $cpt ) {
		return 'mcm_notice' === $cpt ? 'list' : $view;
	}

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
	----------------------------------------------------------------- *
	 *  Feed (cached, sorted: pinned -> priority -> date)
	 * ----------------------------------------------------------------- */

	public static function get_feed() {
		$ids = get_transient( self::CACHE_KEY );
		if ( false !== $ids ) {
			return is_array( $ids ) ? $ids : array();
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'mcm_notice',
				'post_status'    => 'publish',
				'posts_per_page' => self::FEED_MAX,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => self::expiry_meta_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		$ids = $query->posts;
		usort(
			$ids,
			function ( $x, $y ) {
				$kx = self::sort_key( $x );
				$ky = self::sort_key( $y );
				// Pinned desc, priority desc, timestamp desc.
				return ( $ky[0] <=> $kx[0] ) ?: ( $ky[1] <=> $kx[1] ) ?: ( $ky[2] <=> $kx[2] );
			}
		);

		set_transient( self::CACHE_KEY, $ids, 10 * MINUTE_IN_SECONDS );
		return $ids;
	}

	private static function sort_key( $id ) {
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
	 */
	private static function feed_for( $a ) {
		$ids    = self::get_feed();
		$cat    = trim( (string) $a['category'] );
		$cats   = '' !== $cat ? array_filter( array_map( 'trim', explode( ',', $cat ) ) ) : array();
		$dept   = trim( (string) $a['department'] );
		$prio   = strtolower( trim( (string) $a['priority'] ) );
		$days   = (int) $a['days'];
		$cutoff = $days > 0 ? ( time() - $days * DAY_IN_SECONDS ) : 0;
		$count  = max( 1, (int) $a['count'] );

		$out = array();
		foreach ( $ids as $id ) {
			if ( $cats && ! has_term( $cats, 'mcm_notice_cat', $id ) ) {
				continue;
			}
			if ( '' !== $dept && ! has_term( $dept, 'mcm_department', $id ) ) {
				continue;
			}
			if ( '' !== $prio && strtolower( (string) MCM_Safe::meta( $id, 'mcm_notice_priority' ) ) !== $prio ) {
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
	----------------------------------------------------------------- *
	 *  Per-notice helpers
	 * ----------------------------------------------------------------- */

	private static function notice_ts( $id ) {
		$d = MCM_Safe::meta( $id, 'mcm_notice_date' );
		if ( $d ) {
			$t = strtotime( $d );
			if ( $t ) {
				return $t;
			}
		}
		return (int) get_post_time( 'U', true, $id );
	}

	private static function notice_date( $id ) {
		return date_i18n( get_option( 'date_format' ), self::notice_ts( $id ) );
	}

	private static function notice_link( $id ) {
		$u = MCM_Safe::meta( $id, 'mcm_notice_url' );
		return $u ? $u : get_permalink( $id );
	}

	private static function link_attrs( $id ) {
		return MCM_Safe::meta( $id, 'mcm_notice_url' ) ? ' target="_blank" rel="noopener noreferrer"' : '';
	}

	private static function is_new( $id ) {
		$days = (int) apply_filters( 'mcm_notice_new_days', 7 );
		return $days > 0 && self::notice_ts( $id ) >= ( time() - $days * DAY_IN_SECONDS );
	}

	private static function priority_class( $id ) {
		$p = strtolower( (string) MCM_Safe::meta( $id, 'mcm_notice_priority' ) );
		return $p ? ' mcm-notice--' . sanitize_html_class( $p ) : '';
	}

	private static function badge( $id ) {
		$terms = get_the_terms( $id, 'mcm_notice_cat' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		$t = $terms[0];
		return '<span class="mcm-notice-badge mcm-notice-badge--' . esc_attr( $t->slug ) . '">' . esc_html( $t->name ) . '</span>';
	}

	private static function new_tag( $id ) {
		return self::is_new( $id ) ? '<span class="mcm-notice-new">' . esc_html__( 'New', 'medical-college-manager' ) . '</span>' : '';
	}

	/*
	----------------------------------------------------------------- *
	 *  Attributes
	 * ----------------------------------------------------------------- */

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
			),
			is_array( $atts ) ? $atts : array(),
			'mcm_notice'
		);
	}

	/** Seconds per full scroll cycle, scaled by item count and speed. */
	private static function duration( $a, $items ) {
		$speed = min( 10, max( 1, (int) $a['speed'] ) );
		return max( 12, (int) round( $items * ( 55 / $speed ) / 10 ) * 3 );
	}

	/*
	----------------------------------------------------------------- *
	 *  Shortcodes + renderers
	 * ----------------------------------------------------------------- */

	public function sc_list( $atts ) {
		return self::render_list( self::atts( $atts ) );
	}
	public function sc_ticker( $atts ) {
		return self::render_ticker( self::atts( $atts ) );
	}
	public function sc_board( $atts ) {
		return self::render_board( self::atts( $atts ) );
	}

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

	private static function row_html( $id, $a ) {
		$badge = 'yes' === $a['show_badge'] ? self::badge( $id ) : '';
		$date  = 'yes' === $a['show_date'] ? '<span class="mcm-notice-date">' . esc_html( self::notice_date( $id ) ) . '</span>' : '';
		return '<li class="mcm-notice-row' . self::priority_class( $id ) . '">'
			. $badge
			. '<a class="mcm-notice-link" href="' . esc_url( self::notice_link( $id ) ) . '"' . self::link_attrs( $id ) . '>' . esc_html( get_the_title( $id ) ) . '</a>'
			. self::new_tag( $id )
			. $date
			. '</li>';
	}

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
				. '</a>';
		}

		ob_start();
		echo '<div class="mcm-ticker" role="region" aria-label="' . esc_attr( $label ) . '">';
		echo '<span class="mcm-ticker-label">&#128226; ' . esc_html( $label ) . '</span>';
		echo '<div class="mcm-ticker-viewport"><div class="mcm-ticker-track" style="animation-duration:' . (int) $dur . 's">';
		echo '<span class="mcm-ticker-group">' . $items . '</span>'; // phpcs:ignore
		echo '<span class="mcm-ticker-group mcm-dup" aria-hidden="true">' . $items . '</span>'; // phpcs:ignore
		echo '</div></div>';
		echo '<button type="button" class="mcm-scroll-pause" aria-label="' . esc_attr__( 'Pause', 'medical-college-manager' ) . '" aria-pressed="false">&#10073;&#10073;</button>';
		echo '</div>';
		return ob_get_clean();
	}

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
		echo '<ul class="mcm-board-group mcm-dup" aria-hidden="true">' . $items . '</ul>'; // phpcs:ignore
		echo '</div></div>';
		if ( 'yes' === $a['more'] && $archive ) {
			echo '<a class="mcm-board-more" href="' . esc_url( $archive ) . '">' . esc_html__( 'View all notices', 'medical-college-manager' ) . ' &rarr;</a>';
		}
		echo '</div>';
		return ob_get_clean();
	}
}
