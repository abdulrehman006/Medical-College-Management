<?php
/**
 * One-time data upgrades.
 *
 * 1.30.2 started validating dates and times on save (MCM_Meta::parse_date(),
 * MCM_Meta::parse_time()). Values saved by older versions can still be in any
 * form a user typed, e.g. "15/10/2026", which the database cannot compare as a
 * date. This upgrade normalises every stored date/time value it can read and
 * lists the ones it cannot, so an administrator can fix them by hand.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalises stored date/time values once, and reports unreadable ones.
 */
class MCM_Upgrade {

	/** Option recording that the date upgrade ran, and its result. */
	const DATES_OPTION = 'mcm_dates_migration';

	/** Transient that shows the one-time "dates converted" admin notice. */
	const DATES_NOTICE = 'mcm_dates_migration_notice';

	/** Rows read per database query. */
	const BATCH = 500;

	/**
	 * Register the admin notice that reports the upgrade result.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'dates_notice' ) );
		// Picked up on the first admin page load, or by WP-Cron.
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate_dates' ) );
		add_action( 'mcm_migrate_dates', array( __CLASS__, 'maybe_migrate_dates' ) );
	}

	/**
	 * Run the date upgrade once. Guarded by an option, so calling it again
	 * (every upgrade, every activation) does nothing after the first run.
	 *
	 * Never runs inside a visitor's page request: there it only schedules a
	 * one-off WP-Cron event. It runs on an admin request, from WP-Cron or
	 * WP-CLI, so no visitor ever waits for it.
	 *
	 * @return array|null The result, or null when it did not run now.
	 */
	public static function maybe_migrate_dates() {
		if ( false !== get_option( self::DATES_OPTION, false ) ) {
			return null;
		}
		$background = is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI );
		if ( ! $background ) {
			if ( ! wp_next_scheduled( 'mcm_migrate_dates' ) ) {
				wp_schedule_single_event( time(), 'mcm_migrate_dates' );
			}
			return null;
		}
		$result = self::scan_dates( true );
		$record = array(
			'version'    => defined( 'MCM_VERSION' ) ? MCM_VERSION : '',
			'time'       => time(),
			'scanned'    => $result['scanned'],
			'converted'  => $result['converted'],
			'unreadable' => count( $result['unreadable'] ),
		);
		add_option( self::DATES_OPTION, $record, '', 'no' );
		set_transient( self::DATES_NOTICE, $record, WEEK_IN_SECONDS );
		// Expiry decisions may have changed: rebuild the cached notice feed.
		if ( $record['converted'] && class_exists( 'MCM_Notice' ) ) {
			MCM_Notice::clear_cache();
		}
		return $record;
	}

	/**
	 * Date/time fields per post type, from the field definitions.
	 *
	 * @return array<string,array<string,string>> Post type => meta key => 'date'|'time'.
	 */
	public static function date_fields() {
		$out = array();
		foreach ( MCM_Meta::fields() as $type => $cfg ) {
			foreach ( $cfg['fields'] as $key => $field ) {
				if ( in_array( $field['type'], array( 'date', 'time' ), true ) ) {
					$out[ $type ][ $key ] = $field['type'];
				}
			}
		}
		return $out;
	}

	/**
	 * Scan every stored date/time value of every plugin type.
	 *
	 * With $fix, values that can be read but are not in the stored form
	 * (Y-m-d, H:i) are rewritten in that form. Values that cannot be read are
	 * never changed; they are returned so an administrator can fix them.
	 *
	 * @param bool $fix Rewrite readable values in normalised form.
	 * @return array{scanned:int,converted:int,convertible:int,unreadable:array}
	 */
	public static function scan_dates( $fix = false ) {
		global $wpdb;

		$fields = self::date_fields();
		$result = array(
			'scanned'     => 0,
			'converted'   => 0,
			'convertible' => 0,
			'unreadable'  => array(),
		);
		if ( ! $fields ) {
			return $result;
		}

		$types = array_keys( $fields );
		$keys  = array();
		foreach ( $fields as $type_keys ) {
			$keys = array_merge( $keys, array_keys( $type_keys ) );
		}
		$keys = array_values( array_unique( $keys ) );

		$type_ph = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$key_ph  = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$last_id = 0;

		do {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $type_ph/$key_ph are runs of '%s' built from count(); every value is a prepare() replacement.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade and an admin-only health check; reads raw rows by meta_id in batches.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value, p.post_type
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_id > %d
					AND p.post_type IN ( {$type_ph} )
					AND pm.meta_key IN ( {$key_ph} )
					AND pm.meta_value <> ''
					ORDER BY pm.meta_id ASC
					LIMIT %d",
					array_merge( array( $last_id ), $types, $keys, array( self::BATCH ) )
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

			$updates = array(); // meta_id => normalised value.
			$touched = array(); // post IDs whose meta cache must be cleared.
			foreach ( (array) $rows as $row ) {
				$last_id = (int) $row->meta_id;
				if ( ! isset( $fields[ $row->post_type ][ $row->meta_key ] ) ) {
					continue; // The key is a date on another type, not this one.
				}
				++$result['scanned'];
				$kind   = $fields[ $row->post_type ][ $row->meta_key ];
				$value  = (string) $row->meta_value;
				$normal = 'date' === $kind ? MCM_Meta::parse_date( $value ) : MCM_Meta::parse_time( $value );

				if ( false === $normal ) {
					$result['unreadable'][] = array(
						'post_id' => (int) $row->post_id,
						'type'    => (string) $row->post_type,
						'field'   => (string) $row->meta_key,
						'value'   => $value,
					);
					continue;
				}
				if ( $normal === $value ) {
					continue;
				}
				++$result['convertible'];
				if ( $fix ) {
					$updates[ (int) $row->meta_id ] = $normal;
					$touched[ (int) $row->post_id ] = true;
				}
			}

			// One UPDATE per batch (a row-by-row update took ~20 ms per value).
			if ( $updates ) {
				$args = array();
				foreach ( $updates as $meta_id => $normal ) {
					$args[] = $meta_id;
					$args[] = $normal;
				}
				$args = array_merge( $args, array_keys( $updates ) );
				// Placeholder runs only — fixed text, no data: one "WHEN %d THEN %s"
				// per row and one "%d" per id. Every value is a prepare() argument.
				$case_ph = str_repeat( 'WHEN %d THEN %s ', count( $updates ) );
				$id_ph   = implode( ',', array_fill( 0, count( $updates ), '%d' ) );
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $case_ph/$id_ph are placeholder runs built from count(); every value is a prepare() replacement.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade; the post meta cache is cleared below.
				$changed = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} SET meta_value = CASE meta_id {$case_ph}END WHERE meta_id IN ( {$id_ph} )",
						$args
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$result['converted'] += (int) $changed;
				foreach ( array_keys( $touched ) as $post_id ) {
					wp_cache_delete( $post_id, 'post_meta' );
				}
			}
			$fetched = is_array( $rows ) ? count( $rows ) : 0;
		} while ( self::BATCH === $fetched );

		return $result;
	}

	/**
	 * One-time notice after the upgrade ran, shown to administrators.
	 */
	public function dates_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$record = get_transient( self::DATES_NOTICE );
		if ( ! is_array( $record ) ) {
			return;
		}
		delete_transient( self::DATES_NOTICE );

		$unreadable = isset( $record['unreadable'] ) ? (int) $record['unreadable'] : 0;
		echo '<div class="notice ' . esc_attr( $unreadable ? 'notice-warning' : 'notice-success' ) . ' is-dismissible"><p><strong>' . esc_html__( 'Medical College Manager', 'medical-college-manager' ) . ':</strong> ';
		echo esc_html(
			sprintf(
				/* translators: 1: number of dates/times converted, 2: number checked. */
				__( 'converted %1$d of %2$d stored dates and times to the standard format.', 'medical-college-manager' ),
				isset( $record['converted'] ) ? (int) $record['converted'] : 0,
				isset( $record['scanned'] ) ? (int) $record['scanned'] : 0
			)
		);
		if ( $unreadable ) {
			echo ' ' . esc_html(
				sprintf(
					/* translators: %d: number of values that could not be read. */
					_n( '%d value could not be read and was left unchanged.', '%d values could not be read and were left unchanged.', $unreadable, 'medical-college-manager' ),
					$unreadable
				)
			) . ' <a href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'Fix them from Site Health', 'medical-college-manager' ) . '</a>';
		}
		echo '</p></div>';
	}
}
