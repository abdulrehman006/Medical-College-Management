<?php
/**
 * Front-end & admin asset loading + dynamic inline CSS.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCM_Assets {

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

	public function admin( $hook ) {
		// Color picker only on our settings page.
		if ( 'toplevel_page_mcm-settings' === $hook ) {
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

		// Light admin styling for meta boxes everywhere in admin.
		wp_enqueue_style( 'mcm-admin', MCM_URL . 'assets/css/mcm-admin.css', array(), MCM_VERSION );
	}
}
