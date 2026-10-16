<?php
/**
 * Central error log and module status.
 *
 * Every caught failure ends up here: it is kept in a small, non-autoloaded
 * option for administrators to read, written to the PHP error log only when
 * WP_DEBUG_LOG is on, and never printed on the front end.
 *
 * Must not depend on any other plugin class — it is what the others report to.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Error log, admin notices and Site Health integration.
 */
class MCM_Health {

	/** Option holding the last errors (not autoloaded). */
	const LOG_OPTION = 'mcm_error_log';

	/** Transient that raises the admin notice. */
	const ALERT = 'mcm_health_alert';

	/** Number of errors kept. */
	const MAX_ERRORS = 20;

	/**
	 * Modules that booted.
	 *
	 * @var string[]
	 */
	private static $active = array();

	/**
	 * Modules that failed, with the reason.
	 *
	 * @var array<string,string>
	 */
	private static $disabled = array();

	/**
	 * Re-entrancy guard: reporting must never recurse.
	 *
	 * @var bool
	 */
	private static $reporting = false;

	/** Register the admin-facing hooks. Called once from the bootstrap. */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_action( 'admin_post_mcm_health_dismiss', array( __CLASS__, 'handle_dismiss' ) );
		add_action( 'admin_post_mcm_health_clear', array( __CLASS__, 'handle_clear' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'register_tests' ) );
		add_filter( 'debug_information', array( __CLASS__, 'debug_information' ) );
	}

	/*
	 * ----------------------------------------------------------------
	 * Site Health
	 * ----------------------------------------------------------------
	 */

	/**
	 * Register the plugin's Site Health tests.
	 *
	 * @param array $tests Registered tests.
	 * @return array
	 */
	public static function register_tests( $tests ) {
		$own = array(
			'mcm_php'        => __( 'Medical College Manager: PHP version', 'medical-college-manager' ),
			'mcm_wp'         => __( 'Medical College Manager: WordPress version', 'medical-college-manager' ),
			'mcm_elementor'  => __( 'Medical College Manager: Elementor', 'medical-college-manager' ),
			'mcm_errors'     => __( 'Medical College Manager: recent errors', 'medical-college-manager' ),
			'mcm_timezone'   => __( 'Medical College Manager: site timezone', 'medical-college-manager' ),
			'mcm_permalinks' => __( 'Medical College Manager: permalinks', 'medical-college-manager' ),
			'mcm_dates'      => __( 'Medical College Manager: unreadable dates', 'medical-college-manager' ),
		);
		foreach ( $own as $key => $label ) {
			$tests['direct'][ $key ] = array(
				'label' => $label,
				'test'  => array( __CLASS__, 'test_' . substr( $key, 4 ) ),
			);
		}
		return $tests;
	}

	/**
	 * One Site Health result in the shape WordPress expects.
	 *
	 * @param string $test        Test id.
	 * @param string $status      good | recommended | critical.
	 * @param string $label       Headline.
	 * @param string $description Plain-text explanation.
	 * @param string $extra_html  Optional, already-escaped HTML appended to the description.
	 * @return array
	 */
	private static function result( $test, $status, $label, $description, $extra_html = '' ) {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Medical College Manager', 'medical-college-manager' ),
				'color' => 'good' === $status ? 'blue' : ( 'critical' === $status ? 'red' : 'orange' ),
			),
			'description' => '<p>' . esc_html( $description ) . '</p>' . $extra_html,
			'actions'     => '',
			'test'        => $test,
		);
	}

	/**
	 * Site Health test: PHP version.
	 *
	 * @return array
	 */
	public static function test_php() {
		if ( version_compare( PHP_VERSION, '7.4', '>=' ) ) {
			/* translators: %s: PHP version. */
			return self::result( 'mcm_php', 'good', __( 'PHP version is supported', 'medical-college-manager' ), sprintf( __( 'This server runs PHP %s.', 'medical-college-manager' ), PHP_VERSION ) );
		}
		return self::result(
			'mcm_php',
			'recommended',
			__( 'PHP is old', 'medical-college-manager' ),
			/* translators: %s: PHP version. */
			sprintf( __( 'This server runs PHP %s, which no longer receives security fixes. The plugin works, but ask your host to move to PHP 8.1 or newer.', 'medical-college-manager' ), PHP_VERSION )
		);
	}

	/**
	 * Site Health test: WordPress version.
	 *
	 * @return array
	 */
	public static function test_wp() {
		$wp = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';
		/* translators: %s: WordPress version. */
		return self::result( 'mcm_wp', 'good', __( 'WordPress version is supported', 'medical-college-manager' ), sprintf( __( 'This site runs WordPress %s.', 'medical-college-manager' ), $wp ) );
	}

	/**
	 * Site Health test: Elementor presence and version.
	 *
	 * @return array
	 */
	public static function test_elementor() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return self::result( 'mcm_elementor', 'good', __( 'Elementor is not active', 'medical-college-manager' ), __( 'The Medical College widgets need Elementor; every shortcode works without it.', 'medical-college-manager' ) );
		}
		$min = defined( 'MCM_MIN_ELEMENTOR' ) ? MCM_MIN_ELEMENTOR : '3.5.0';
		if ( version_compare( ELEMENTOR_VERSION, $min, '<' ) ) {
			return self::result(
				'mcm_elementor',
				'recommended',
				__( 'Elementor is too old for the Medical College widgets', 'medical-college-manager' ),
				/* translators: 1: installed Elementor version, 2: required version. */
				sprintf( __( 'Elementor %1$s is installed; the widgets need %2$s or newer and have been switched off. Shortcodes keep working. Update Elementor to use the widgets.', 'medical-college-manager' ), ELEMENTOR_VERSION, $min )
			);
		}
		/* translators: %s: Elementor version. */
		return self::result( 'mcm_elementor', 'good', __( 'Elementor is compatible', 'medical-college-manager' ), sprintf( __( 'Elementor %s is active; the Medical College widgets are available.', 'medical-college-manager' ), ELEMENTOR_VERSION ) );
	}

	/**
	 * Site Health test: errors caught in the last 7 days.
	 *
	 * @return array
	 */
	public static function test_errors() {
		$recent = self::recent_errors( 7 );
		if ( ! $recent && ! self::$disabled ) {
			return self::result( 'mcm_errors', 'good', __( 'No plugin errors in the last 7 days', 'medical-college-manager' ), __( 'Medical College Manager has not caught any problems recently.', 'medical-college-manager' ) );
		}

		$items = '';
		foreach ( array_slice( $recent, 0, 10 ) as $err ) {
			$items .= '<li><code>' . esc_html( isset( $err['context'] ) ? (string) $err['context'] : '' ) . '</code> &mdash; '
				. esc_html( isset( $err['message'] ) ? (string) $err['message'] : '' )
				. ' <span style="color:#646970;">(' . esc_html( isset( $err['file'] ) ? (string) $err['file'] : '' ) . ', '
				. esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), isset( $err['time'] ) ? (int) $err['time'] : 0 ) )
				. ( ! empty( $err['count'] ) && (int) $err['count'] > 1 ? ' &times;' . (int) $err['count'] : '' )
				. ')</span></li>';
		}
		foreach ( self::$disabled as $module => $why ) {
			$items .= '<li><strong>' . esc_html__( 'Switched off:', 'medical-college-manager' ) . '</strong> <code>' . esc_html( $module ) . '</code> &mdash; ' . esc_html( $why ) . '</li>';
		}
		$clear = wp_nonce_url( admin_url( 'admin-post.php?action=mcm_health_clear' ), 'mcm_health_clear' );

		return self::result(
			'mcm_errors',
			'recommended',
			__( 'Medical College Manager caught errors recently', 'medical-college-manager' ),
			__( 'These problems were caught and contained: visitors saw an empty block instead of a broken page. Share this list with your developer.', 'medical-college-manager' ),
			'<ul style="list-style:disc;padding-left:20px;">' . $items . '</ul><p><a class="button" href="' . esc_url( $clear ) . '">' . esc_html__( 'Clear the error log', 'medical-college-manager' ) . '</a></p>'
		);
	}

	/**
	 * Site Health test: the site timezone is set.
	 *
	 * @return array
	 */
	public static function test_timezone() {
		$tz     = (string) get_option( 'timezone_string' );
		$offset = (float) get_option( 'gmt_offset' );
		if ( '' !== $tz || 0.0 !== $offset ) {
			$name = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : $tz;
			/* translators: %s: timezone name or offset. */
			return self::result( 'mcm_timezone', 'good', __( 'Site timezone is set', 'medical-college-manager' ), sprintf( __( 'Deadlines and expiry dates are evaluated in %s.', 'medical-college-manager' ), $name ) );
		}
		return self::result(
			'mcm_timezone',
			'recommended',
			__( 'Site timezone is UTC', 'medical-college-manager' ),
			__( 'Tender, notice and other deadlines are checked in the site timezone, which is still UTC. Set your city (e.g. Karachi) in Settings → General so items close at local midnight.', 'medical-college-manager' )
		);
	}

	/**
	 * Site Health test: stored dates and times the plugin cannot read, with edit links.
	 *
	 * @return array
	 */
	public static function test_dates() {
		if ( ! class_exists( 'MCM_Upgrade' ) || ! class_exists( 'MCM_Meta' ) ) {
			return self::result( 'mcm_dates', 'good', __( 'Date check not available', 'medical-college-manager' ), __( 'The date check module is not running.', 'medical-college-manager' ) );
		}

		// Read-only scan: always reflects the data as it is now, so the list
		// shrinks as soon as an entry is fixed.
		$scan   = MCM_Upgrade::scan_dates( false );
		$record = get_option( MCM_Upgrade::DATES_OPTION );
		$ran    = '';
		if ( is_array( $record ) && isset( $record['converted'], $record['time'] ) ) {
			$ran = sprintf(
				/* translators: 1: number of values converted, 2: date of the upgrade. */
				__( 'The 1.30.2 upgrade converted %1$d stored dates/times to the standard format on %2$s.', 'medical-college-manager' ),
				(int) $record['converted'],
				wp_date( get_option( 'date_format' ), (int) $record['time'] )
			);
		}

		if ( ! $scan['unreadable'] && ! $scan['convertible'] ) {
			return self::result(
				'mcm_dates',
				'good',
				__( 'All stored dates and times are readable', 'medical-college-manager' ),
				trim(
					sprintf(
						/* translators: %d: number of values checked. */
						__( '%d stored dates and times checked.', 'medical-college-manager' ),
						$scan['scanned']
					) . ' ' . $ran
				)
			);
		}

		$rows = '';
		foreach ( array_slice( $scan['unreadable'], 0, 100 ) as $item ) {
			$type  = get_post_type_object( $item['type'] );
			$field = MCM_Meta::fields();
			$label = isset( $field[ $item['type'] ]['fields'][ $item['field'] ]['label'] ) ? $field[ $item['type'] ]['fields'][ $item['field'] ]['label'] : $item['field'];
			$rows .= '<tr><td><a href="' . esc_url( admin_url( 'post.php?post=' . (int) $item['post_id'] . '&action=edit' ) ) . '">' . esc_html( get_the_title( $item['post_id'] ) ? get_the_title( $item['post_id'] ) : '#' . $item['post_id'] ) . '</a></td>'
				. '<td>' . esc_html( $type ? $type->labels->singular_name : $item['type'] ) . '</td>'
				. '<td>' . esc_html( $label ) . '</td>'
				. '<td><code>' . esc_html( $item['value'] ) . '</code></td></tr>';
		}
		$table = '';
		if ( $rows ) {
			$table = '<table class="widefat striped" style="margin-top:8px;"><thead><tr><th>' . esc_html__( 'Entry', 'medical-college-manager' ) . '</th><th>' . esc_html__( 'Type', 'medical-college-manager' ) . '</th><th>' . esc_html__( 'Field', 'medical-college-manager' ) . '</th><th>' . esc_html__( 'Stored value', 'medical-college-manager' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>';
			if ( count( $scan['unreadable'] ) > 100 ) {
				/* translators: %d: number of further items not listed. */
				$table .= '<p>' . esc_html( sprintf( __( '…and %d more.', 'medical-college-manager' ), count( $scan['unreadable'] ) - 100 ) ) . '</p>';
			}
		}
		if ( $scan['convertible'] ) {
			$table .= '<p>' . esc_html(
				sprintf(
					/* translators: %d: number of values. */
					_n( '%d further value is readable but not yet in the standard format; it is corrected the next time the entry is saved.', '%d further values are readable but not yet in the standard format; they are corrected the next time each entry is saved.', $scan['convertible'], 'medical-college-manager' ),
					$scan['convertible']
				)
			) . '</p>';
		}

		return self::result(
			'mcm_dates',
			'recommended',
			sprintf(
				/* translators: %d: number of unreadable values. */
				_n( '%d stored date or time cannot be read', '%d stored dates or times cannot be read', max( 1, count( $scan['unreadable'] ) ), 'medical-college-manager' ),
				count( $scan['unreadable'] )
			),
			trim( __( 'These values were left unchanged. Until they are fixed, the entries may be hidden (for example an expired-looking tender) or sorted in the wrong place. Open each entry and enter the date again, e.g. 15/10/2026, or the time, e.g. 14:30.', 'medical-college-manager' ) . ' ' . $ran ),
			$table
		);
	}

	/**
	 * Site Health test: permalinks are not Plain.
	 *
	 * @return array
	 */
	public static function test_permalinks() {
		if ( '' !== (string) get_option( 'permalink_structure' ) ) {
			return self::result( 'mcm_permalinks', 'good', __( 'Pretty permalinks are on', 'medical-college-manager' ), __( 'Archive pages such as /events/ and /department/anatomy/ work.', 'medical-college-manager' ) );
		}
		return self::result(
			'mcm_permalinks',
			'recommended',
			__( 'Permalinks are set to "Plain"', 'medical-college-manager' ),
			__( 'Archive and department page addresses (e.g. /events/) need pretty permalinks. Choose "Post name" in Settings → Permalinks.', 'medical-college-manager' )
		);
	}

	/**
	 * Debug info section (Tools → Site Health → Info).
	 *
	 * @param array $info Sections.
	 * @return array
	 */
	public static function debug_information( $info ) {
		$disabled = array();
		foreach ( self::$disabled as $module => $why ) {
			$disabled[] = $module . ' (' . $why . ')';
		}
		$info['medical-college-manager'] = array(
			'label'  => __( 'Medical College Manager', 'medical-college-manager' ),
			'fields' => array(
				'version'   => array(
					'label' => __( 'Version', 'medical-college-manager' ),
					'value' => defined( 'MCM_VERSION' ) ? MCM_VERSION : '',
				),
				'active'    => array(
					'label' => __( 'Active modules', 'medical-college-manager' ),
					'value' => self::$active ? implode( ', ', self::$active ) : __( 'None', 'medical-college-manager' ),
				),
				'disabled'  => array(
					'label' => __( 'Disabled modules', 'medical-college-manager' ),
					'value' => $disabled ? implode( '; ', $disabled ) : __( 'None', 'medical-college-manager' ),
				),
				'errors'    => array(
					'label' => __( 'Errors logged (last 7 days)', 'medical-college-manager' ),
					'value' => (string) count( self::recent_errors( 7 ) ),
				),
				'elementor' => array(
					'label' => __( 'Elementor version', 'medical-college-manager' ),
					'value' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : __( 'Not active', 'medical-college-manager' ),
				),
			),
		);
		return $info;
	}

	/** Clear the error log (Site Health button). */
	public static function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'medical-college-manager' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mcm_health_clear' );
		delete_option( self::LOG_OPTION );
		delete_transient( self::ALERT );
		wp_safe_redirect( admin_url( 'site-health.php' ) );
		exit;
	}

	/**
	 * Record a module that booted.
	 *
	 * @param string $name Module class name.
	 */
	public static function module_ok( $name ) {
		self::$active[] = (string) $name;
	}

	/**
	 * A module failed to load or construct.
	 *
	 * @param string     $name Module.
	 * @param \Throwable $e    Cause.
	 */
	public static function module_failed( $name, $e ) {
		self::$disabled[ (string) $name ] = $e instanceof \Throwable ? $e->getMessage() : '';
		self::report( 'module ' . $name, $e );
	}

	/**
	 * Modules that booted.
	 *
	 * @return string[]
	 */
	public static function active_modules() {
		return self::$active;
	}

	/**
	 * Modules that failed, with the reason.
	 *
	 * @return array<string,string>
	 */
	public static function disabled_modules() {
		return self::$disabled;
	}

	/**
	 * Record a caught failure. Never throws, never prints.
	 *
	 * @param string     $context Where it happened (e.g. "shortcode mcm_events").
	 * @param \Throwable $e       The failure.
	 */
	public static function report( $context, $e ) {
		if ( self::$reporting ) {
			return;
		}
		self::$reporting = true;
		try {
			$entry = array(
				'time'    => time(),
				'context' => substr( (string) $context, 0, 120 ),
				'message' => $e instanceof \Throwable ? substr( $e->getMessage(), 0, 300 ) : '',
				'file'    => $e instanceof \Throwable ? self::short_path( $e->getFile() ) . ':' . $e->getLine() : '',
				'count'   => 1,
			);

			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only when the site owner enabled WP_DEBUG_LOG.
				error_log( sprintf( '[Medical College Manager] %s: %s (%s)', $entry['context'], $entry['message'], $entry['file'] ) );
			}

			$log = get_option( self::LOG_OPTION, array() );
			$log = is_array( $log ) ? $log : array();

			// The same failure on every page view must not flush the history:
			// repeats of the newest entry are counted instead of appended.
			$last = $log ? $log[0] : null;
			if ( is_array( $last ) && isset( $last['context'], $last['message'], $last['file'] )
				&& $last['context'] === $entry['context'] && $last['message'] === $entry['message'] && $last['file'] === $entry['file'] ) {
				$log[0]['time']  = $entry['time'];
				$log[0]['count'] = ( isset( $last['count'] ) ? (int) $last['count'] : 1 ) + 1;
			} else {
				array_unshift( $log, $entry );
				$log = array_slice( $log, 0, self::MAX_ERRORS );
			}

			if ( false === get_option( self::LOG_OPTION, false ) ) {
				add_option( self::LOG_OPTION, $log, '', 'no' );
			} else {
				update_option( self::LOG_OPTION, $log, false );
			}
			set_transient( self::ALERT, 1, WEEK_IN_SECONDS );
		} catch ( \Throwable $ignored ) {
			// Reporting is best-effort; it must never become the failure.
			unset( $ignored );
		}
		self::$reporting = false;
	}

	/**
	 * Logged errors.
	 *
	 * @return array[] Newest first.
	 */
	public static function errors() {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Errors logged in the last N days.
	 *
	 * @param int $days Days.
	 * @return array[]
	 */
	public static function recent_errors( $days = 7 ) {
		$since = time() - ( (int) $days * DAY_IN_SECONDS );
		$out   = array();
		foreach ( self::errors() as $err ) {
			if ( is_array( $err ) && isset( $err['time'] ) && (int) $err['time'] >= $since ) {
				$out[] = $err;
			}
		}
		return $out;
	}

	/**
	 * Path relative to the plugin, so the log never shows server layout.
	 *
	 * @param mixed $file File.
	 */
	private static function short_path( $file ) {
		$file = wp_normalize_path( (string) $file );
		$base = defined( 'MCM_PATH' ) ? wp_normalize_path( MCM_PATH ) : '';
		if ( '' !== $base && 0 === strpos( $file, $base ) ) {
			return substr( $file, strlen( $base ) );
		}
		return basename( $file );
	}

	/** Admin notices: disabled modules (always) and new errors (until dismissed). */
	public static function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::$disabled ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Medical College Manager', 'medical-college-manager' ) . ':</strong> ';
			echo esc_html(
				sprintf(
					/* translators: %s: comma-separated list of module names. */
					__( 'these parts could not start and have been switched off so the rest of the site keeps working: %s.', 'medical-college-manager' ),
					implode( ', ', array_keys( self::$disabled ) )
				)
			);
			echo '</p></div>';
		}

		if ( get_transient( self::ALERT ) ) {
			$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=mcm_health_dismiss' ), 'mcm_health_dismiss' );
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Medical College Manager', 'medical-college-manager' ) . ':</strong> ';
			echo esc_html__( 'a problem was caught and logged. Visitors were not shown an error.', 'medical-college-manager' ) . ' ';
			echo '<a href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'View details in Site Health', 'medical-college-manager' ) . '</a> &middot; ';
			echo '<a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'medical-college-manager' ) . '</a>';
			echo '</p></div>';
		}
	}

	/** Dismiss the error notice (the log itself is kept). */
	public static function handle_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'medical-college-manager' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mcm_health_dismiss' );
		delete_transient( self::ALERT );
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}
}
