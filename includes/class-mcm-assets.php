<?php
/**
 * Front-end & admin asset loading + dynamic inline CSS.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end and admin asset loading, plus the settings-driven inline CSS.
 */
class MCM_Assets {

	/**
	 * Register the hooks this module needs.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin' ) );
	}

	/**
	 * Register the front-end assets and enqueue them where they are needed.
	 *
	 * They used to load on every page of the site, including ones with no MCM
	 * content at all. Now they are registered here and enqueued either by the
	 * detection below, or — as a backstop — by MCM_Assets::need() at the moment
	 * a block actually renders. The backstop matters because content built in
	 * Elementor is not in post_content, so no amount of scanning would find it;
	 * the widget calls need() when it renders and the assets load regardless.
	 */
	public function frontend() {
		wp_register_style( 'mcm-frontend', MCM_URL . 'assets/css/mcm-frontend.css', array(), MCM_VERSION );
		wp_register_script( 'mcm-frontend', MCM_URL . 'assets/js/mcm-frontend.js', array( 'jquery' ), MCM_VERSION, true );
		wp_localize_script(
			'mcm-frontend',
			'MCM',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mcm_filter' ),
				'i18n'    => array(
					'loadError' => __( 'Could not load results. Please refresh the page and try again.', 'medical-college-manager' ),
					'generated' => __( 'Generated', 'medical-college-manager' ),
					'search'    => __( 'Search:', 'medical-college-manager' ),
					'entry'     => __( 'entry', 'medical-college-manager' ),
					'entries'   => __( 'entries', 'medical-college-manager' ),
					/* translators: %d: number of filter options. */
					'showAll'   => __( 'Show all (%d)', 'medical-college-manager' ),
					'showFewer' => __( 'Show fewer', 'medical-college-manager' ),
					'filters'   => __( 'Filters', 'medical-college-manager' ),
				),
			)
		);

		if ( self::is_needed() ) {
			self::need();
		}
	}

	/**
	 * Enqueue the front-end assets. Safe to call repeatedly and safe to call
	 * late — WordPress prints a late style in the footer rather than dropping
	 * it, so a block rendered deep in a page builder still gets styled.
	 */
	public static function need() {
		if ( wp_style_is( 'mcm-frontend', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_style( 'mcm-frontend' );
		// Dynamic, settings-driven CSS (variables + custom CSS).
		wp_add_inline_style( 'mcm-frontend', MCM_Settings::dynamic_css() );
		wp_enqueue_script( 'mcm-frontend' );
	}

	/**
	 * Can we tell up-front that this request will show MCM content?
	 *
	 * Deliberately generous: a false positive costs one stylesheet, a false
	 * negative is caught by the render-time backstop. Filterable so a site
	 * doing something exotic can simply force it on.
	 *
	 * @return bool
	 */
	public static function is_needed() {
		if ( apply_filters( 'mcm_always_load_assets', false ) ) {
			return true;
		}

		// Any MCM archive, single or taxonomy term page.
		$types = array_keys( MCM_CPT::post_types() );
		$taxes = array_keys( MCM_CPT::taxonomies() );
		if ( is_singular( $types ) || is_post_type_archive( $types ) || is_tax( $taxes ) ) {
			return true;
		}

		// A shortcode in the queried object's content.
		$post = get_post();
		if ( $post instanceof WP_Post && ! empty( $post->post_content ) ) {
			if ( false !== strpos( $post->post_content, '[mcm_' ) ) {
				return true;
			}
			// Reusable blocks / patterns pull content in by reference.
			if ( false !== strpos( $post->post_content, 'wp:block' ) ) {
				return true;
			}
		}

		// Widgets and site-wide furniture (social bar, notice ticker) can put a
		// block on every page; ask them rather than guessing.
		return (bool) apply_filters( 'mcm_needs_assets', false );
	}

	/**
	 * Enqueue the admin scripts and styles on the plugin's screens.
	 *
	 * @param string $hook Admin page hook suffix.
	 */
	public function admin( $hook ) {
		// Color picker + media on our settings screens (main settings, Header &
		// Footer). Submenu hooks carry a translated parent prefix, so match the
		// suffix.
		if ( preg_match( '/_page_mcm-(settings|site-chrome)$/', (string) $hook ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			// Needed by the letterhead logo picker.
			wp_enqueue_media();
			wp_enqueue_script(
				'mcm-admin',
				MCM_URL . 'assets/js/mcm-admin.js',
				array( 'jquery', 'wp-color-picker' ),
				MCM_VERSION,
				true
			);
		}
		// Media picker for document/image fields — on our post types, and on the
		// term screens that carry department content (HOD photo, banner video).
		$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$on_term = $screen && isset( $screen->taxonomy ) && array_key_exists( $screen->taxonomy, MCM_TermMeta::taxonomies() );
		if ( $on_term || ( $screen && in_array( $screen->post_type, array_keys( MCM_CPT::post_types() ), true ) ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'mcm-admin', MCM_URL . 'assets/js/mcm-admin.js', array( 'jquery' ), MCM_VERSION, true );
		}

		if ( wp_script_is( 'mcm-admin', 'enqueued' ) ) {
			wp_localize_script(
				'mcm-admin',
				'MCMAdmin',
				array(
					'i18n' => array(
						'selectImage' => __( 'Select image', 'medical-college-manager' ),
						'selectFile'  => __( 'Select file', 'medical-college-manager' ),
						'useImage'    => __( 'Use this image', 'medical-college-manager' ),
						'useFile'     => __( 'Use this file', 'medical-college-manager' ),
						'copied'      => __( 'Copied!', 'medical-college-manager' ),
						/* translators: 1: image width in px, 2: recommended minimum width in px. */
						'tooSmall'    => __( 'This image is %1$d px wide; at least %2$d px is recommended, or it may look blurry.', 'medical-college-manager' ),
					),
				)
			);
		}

		// Light admin styling for meta boxes everywhere in admin.
		wp_enqueue_style( 'mcm-admin', MCM_URL . 'assets/css/mcm-admin.css', array(), MCM_VERSION );
	}
}
