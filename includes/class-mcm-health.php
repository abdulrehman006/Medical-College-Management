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

class MCM_Health {

	/** Option holding the last errors (not autoloaded). */
	const LOG_OPTION = 'mcm_error_log';

	/** Transient that raises the admin notice. */
	const ALERT = 'mcm_health_alert';

	/** Number of errors kept. */
	const MAX_ERRORS = 20;

	/** @var string[] Modules that booted. */
	private static $active = array();

	/** @var array<string,string> Modules that failed => reason. */
	private static $disabled = array();

	/** @var bool Re-entrancy guard: reporting must never recurse. */
	private static $reporting = false;

	/** Register the admin-facing hooks. Called once from the bootstrap. */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_action( 'admin_post_mcm_health_dismiss', array( __CLASS__, 'handle_dismiss' ) );
	}

	/** @param string $name Module that booted. */
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

	/** @return string[] */
	public static function active_modules() {
		return self::$active;
	}

	/** @return array<string,string> */
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

	/** @return array[] Logged errors, newest first. */
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

	/** Path relative to the plugin, so the log never shows server layout. */
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
