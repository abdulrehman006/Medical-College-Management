<?php
/**
 * Error boundaries.
 *
 * Nothing in this plugin may take a site down. Module construction, every
 * public render path and every value read for display go through this class:
 * a failure is reported to MCM_Health and replaced by an empty string for
 * visitors (or a small notice for administrators), so the rest of the page —
 * and every other module — keeps working.
 *
 * Deliberately dependency-free and PHP 7.2 compatible: it is loaded first and
 * must never be the thing that fails.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCM_Safe {

	/**
	 * Require a plugin file. A file that fails to load (including a ParseError
	 * from an incompatible PHP version) is reported and skipped.
	 *
	 * @param string $file    Absolute path.
	 * @param string $context Name used in the error log.
	 * @return bool True when the file loaded.
	 */
	public static function load( $file, $context ) {
		try {
			require_once $file;
			return true;
		} catch ( \Throwable $e ) {
			MCM_Health::module_failed( $context, $e );
			return false;
		}
	}

	/**
	 * Construct one module. A module whose constructor throws is skipped and
	 * reported; every other module still boots.
	 *
	 * @param string $class_name Class name.
	 * @return object|null The module, or null when it failed.
	 */
	public static function boot( $class_name ) {
		try {
			if ( ! class_exists( $class_name ) ) {
				throw new \RuntimeException( sprintf( 'Class %s is not available.', $class_name ) );
			}
			$module = new $class_name();
			MCM_Health::module_ok( $class_name );
			return $module;
		} catch ( \Throwable $e ) {
			MCM_Health::module_failed( $class_name, $e );
			return null;
		}
	}

	/**
	 * Run a callback that produces no output (a hook, a migration). Failures
	 * are reported and swallowed.
	 *
	 * @param callable $callback Callback.
	 * @param string   $context  Name used in the error log.
	 * @param array    $args     Arguments.
	 * @param mixed    $fallback Returned on failure.
	 * @return mixed
	 */
	public static function run( $callback, $context, $args = array(), $fallback = null ) {
		try {
			return call_user_func_array( $callback, (array) $args );
		} catch ( \Throwable $e ) {
			MCM_Health::report( $context, $e );
			return $fallback;
		}
	}

	/**
	 * Render boundary for a public output path.
	 *
	 * On failure: output buffers the renderer left open are discarded (so no
	 * half-built HTML leaks), the error is reported, visitors get $fallback and
	 * administrators additionally get a short inline notice — never a trace.
	 *
	 * @param callable $callback Renderer returning a string.
	 * @param string   $context  Human-readable name, shown to administrators.
	 * @param array    $args     Arguments.
	 * @param string   $fallback What to return on failure ('' by default).
	 * @return string
	 */
	public static function render( $callback, $context, $args = array(), $fallback = '' ) {
		$level = ob_get_level();
		try {
			return self::str( call_user_func_array( $callback, (array) $args ) );
		} catch ( \Throwable $e ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			MCM_Health::report( $context, $e );
			return self::str( $fallback ) . self::admin_box( $context );
		}
	}

	/**
	 * Inline notice shown only to administrators in place of a failed block.
	 *
	 * @param string $context What failed.
	 * @return string
	 */
	public static function admin_box( $context ) {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		return '<div class="mcm-error-box" role="alert">' . esc_html(
			sprintf(
				/* translators: %s: name of the block that failed, e.g. "shortcode mcm_events". */
				__( 'Medical College Manager: this block could not be displayed (%s). See Tools → Site Health.', 'medical-college-manager' ),
				$context
			)
		) . '</div>';
	}

	/**
	 * Any value as a display-safe string. Arrays and objects (possible when
	 * another plugin or a hand-made import stored structured meta) become ''
	 * instead of a PHP 8 TypeError further down the line.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function str( $value ) {
		if ( is_string( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		return '';
	}

	/**
	 * A single post meta value as a display-safe string.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	public static function meta( $post_id, $key ) {
		return self::str( get_post_meta( (int) $post_id, $key, true ) );
	}

	/**
	 * A single term meta value as a display-safe string.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	public static function term_meta( $term_id, $key ) {
		return self::str( get_term_meta( (int) $term_id, $key, true ) );
	}

	/**
	 * A scalar from an untrusted array (decoded JSON, $_POST, $_GET). Arrays
	 * and objects are discarded BEFORE any sanitize_*() call sees them — on
	 * older WordPress several sanitizers fatal on a non-string under PHP 8.
	 *
	 * @param array  $source  Source array.
	 * @param string $key     Key.
	 * @param string $fallback Returned when missing or not scalar.
	 * @return string Unsanitized string — sanitize it for its purpose.
	 */
	public static function scalar( $source, $key, $fallback = '' ) {
		if ( ! is_array( $source ) || ! array_key_exists( $key, $source ) || ! is_scalar( $source[ $key ] ) ) {
			return $fallback;
		}
		return (string) $source[ $key ];
	}

	/**
	 * A scalar from $_POST, unslashed. Non-scalars are discarded.
	 *
	 * @param string $key     Key.
	 * @param string $fallback Returned when missing or not scalar.
	 * @return string Unsanitized string — sanitize it for its purpose.
	 */
	public static function post( $key, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reader only; every caller verifies its own nonce.
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return $fallback;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Callers sanitize for their own purpose.
		return (string) wp_unslash( $_POST[ $key ] );
	}
}
