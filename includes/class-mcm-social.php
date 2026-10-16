<?php
/**
 * Social Bar — a floating social-media bar with its own settings page, a
 * shortcode, and an optional site-wide mode. Fully responsive.
 *
 * @package MedicalCollegeManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCM_Social {

	const OPTION = 'mcm_social';

	/** Whether the bar has already been output this request (avoid duplicates). */
	private $shown = false;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 12 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_shortcode( 'mcm_social_bar', array( $this, 'shortcode' ) );
		add_action( 'wp_footer', array( $this, 'maybe_render_sitewide' ), 20 );
	}

	/**
	 * Supported networks: key => [ label, inline SVG path ].
	 * SVG paths are static, trusted markup (24x24 viewBox).
	 *
	 * @return array
	 */
	public static function networks() {
		return array(
			'facebook'  => array(
				'label' => __( 'Facebook', 'medical-college-manager' ),
				'svg'   => 'M24 12.07C24 5.41 18.63 0 12 0S0 5.41 0 12.07c0 6.02 4.39 11.01 10.12 11.93v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8v8.44C19.61 23.08 24 18.09 24 12.07z',
			),
			'instagram' => array(
				'label' => __( 'Instagram', 'medical-college-manager' ),
				'svg'   => 'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41-.56-.22-.96-.48-1.38-.9-.42-.42-.68-.82-.9-1.38-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16M12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63c-.79.31-1.46.72-2.12 1.38C1.36 2.67.95 3.34.63 4.14.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.31.79.72 1.46 1.38 2.12.66.66 1.33 1.07 2.12 1.38.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56.79-.31 1.46-.72 2.12-1.38.66-.66 1.07-1.33 1.38-2.12.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91-.31-.79-.72-1.46-1.38-2.12-.66-.66-1.33-1.07-2.12-1.38-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0zm0 5.84A6.16 6.16 0 1018.16 12 6.16 6.16 0 0012 5.84zM12 16a4 4 0 114-4 4 4 0 01-4 4zm6.41-10.85a1.44 1.44 0 101.44 1.44 1.44 1.44 0 00-1.44-1.44z',
			),
			'twitter'   => array(
				'label' => __( 'X (Twitter)', 'medical-college-manager' ),
				'svg'   => 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.45-6.231zm-1.161 17.52h1.833L7.084 4.126H5.117l11.966 15.644z',
			),
			'tiktok'    => array(
				'label' => __( 'TikTok', 'medical-college-manager' ),
				'svg'   => 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
			),
			'youtube'   => array(
				'label' => __( 'YouTube', 'medical-college-manager' ),
				'svg'   => 'M23.5 6.2a3.02 3.02 0 00-2.12-2.14C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.51A3.02 3.02 0 00.5 6.2 31.5 31.5 0 000 12a31.5 31.5 0 00.5 5.8 3.02 3.02 0 002.12 2.14c1.88.51 9.38.51 9.38.51s7.5 0 9.38-.51a3.02 3.02 0 002.12-2.14A31.5 31.5 0 0024 12a31.5 31.5 0 00-.5-5.8zM9.55 15.57V8.43L15.82 12z',
			),
			'linkedin'  => array(
				'label' => __( 'LinkedIn', 'medical-college-manager' ),
				'svg'   => 'M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 110-4.13 2.06 2.06 0 010 4.13zM7.12 20.45H3.56V9h3.56zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.22.79 24 1.77 24h20.45c.98 0 1.78-.78 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z',
			),
			'whatsapp'  => array(
				'label' => __( 'WhatsApp', 'medical-college-manager' ),
				'svg'   => 'M17.5 14.38c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51l-.57-.01c-.2 0-.52.07-.8.37-.27.3-1.05 1.02-1.05 2.5 0 1.47 1.08 2.9 1.23 3.1.15.2 2.12 3.24 5.14 4.54.72.31 1.28.5 1.71.64.72.23 1.38.2 1.9.12.58-.09 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.2-.57-.35zM12 0C5.37 0 0 5.37 0 12c0 2.12.55 4.1 1.52 5.83L0 24l6.33-1.66A11.95 11.95 0 0012 24c6.63 0 12-5.37 12-12S18.63 0 12 0zm0 21.82c-1.96 0-3.78-.58-5.31-1.57l-.38-.23-3.76.99 1-3.67-.25-.38A9.78 9.78 0 012.18 12C2.18 6.6 6.6 2.18 12 2.18S21.82 6.6 21.82 12 17.4 21.82 12 21.82z',
			),
		);
	}

	public static function defaults() {
		$d = array(
			'social_enable_sitewide' => '0',
			'social_position'        => 'right',
			'social_mobile'          => 'bottom', // bottom | hide.
		);
		foreach ( array_keys( self::networks() ) as $key ) {
			$d[ 'social_' . $key ] = '';
		}
		return $d;
	}

	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function value( $key ) {
		$all = self::get();
		return isset( $all[ $key ] ) ? $all[ $key ] : '';
	}

	public function menu() {
		add_submenu_page(
			'mcm-settings',
			__( 'Social Bar', 'medical-college-manager' ),
			__( 'Social Bar', 'medical-college-manager' ),
			'manage_options',
			'mcm-social',
			array( $this, 'render_page' )
		);
	}

	public function register() {
		register_setting( 'mcm_social_group', self::OPTION, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ) {
		$defaults = self::defaults();
		$out      = array();

		foreach ( array_keys( self::networks() ) as $key ) {
			$field = 'social_' . $key;
			$raw   = isset( $input[ $field ] ) ? trim( (string) $input[ $field ] ) : '';
			if ( 'whatsapp' === $key ) {
				// Keep digits (and a leading +) only — used to build a wa.me link.
				$out[ $field ] = preg_replace( '/[^0-9]/', '', $raw );
			} else {
				$out[ $field ] = '' === $raw ? '' : esc_url_raw( $raw );
			}
		}

		$out['social_enable_sitewide'] = ! empty( $input['social_enable_sitewide'] ) ? '1' : '0';
		$out['social_position']        = ( isset( $input['social_position'] ) && 'left' === $input['social_position'] ) ? 'left' : 'right';
		$out['social_mobile']          = ( isset( $input['social_mobile'] ) && 'hide' === $input['social_mobile'] ) ? 'hide' : 'bottom';

		return wp_parse_args( $out, $defaults );
	}

	/**
	 * Build the social bar markup. Returns '' when no links are configured.
	 *
	 * @return string
	 */
	public function render_bar() {
		return MCM_Safe::render( array( $this, 'build_bar' ), 'social bar' );
	}

	/**
	 * Unguarded builder; call render_bar() instead.
	 *
	 * @internal
	 * @return string
	 */
	public function build_bar() {
		MCM_Assets::need();
		$s     = self::get();
		$items = '';

		foreach ( self::networks() as $key => $net ) {
			$val = isset( $s[ 'social_' . $key ] ) ? $s[ 'social_' . $key ] : '';
			if ( '' === $val ) {
				continue;
			}

			if ( 'whatsapp' === $key ) {
				$href = 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $val );
			} else {
				$href = $val;
			}

			$items .= sprintf(
				'<a class="mcm-s-%1$s" href="%2$s" target="_blank" rel="noopener noreferrer" aria-label="%3$s"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="%4$s"/></svg><span class="mcm-s-label">%3$s</span></a>',
				esc_attr( $key ),
				esc_url( $href ),
				esc_attr( $net['label'] ),
				$net['svg'] // Static, trusted path data.
			);
		}

		if ( '' === $items ) {
			return '';
		}

		$pos    = 'left' === $s['social_position'] ? 'left' : 'right';
		$mobile = 'hide' === $s['social_mobile'] ? 'hide' : 'bottom';

		return sprintf(
			'<nav class="mcm-social-bar mcm-social-bar--%1$s mcm-social-bar--mobile-%2$s" aria-label="%3$s">%4$s</nav>',
			esc_attr( $pos ),
			esc_attr( $mobile ),
			esc_attr__( 'Social media links', 'medical-college-manager' ),
			$items
		);
	}

	public function shortcode( $atts ) {
		$this->shown = true;
		return $this->render_bar();
	}

	/** Auto-print site-wide in the footer when enabled (and not already shown). */
	public function maybe_render_sitewide() {
		if ( $this->shown || '1' !== self::value( 'social_enable_sitewide' ) ) {
			return;
		}
		$this->shown = true;
		echo $this->render_bar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function render_page() {
		$s = self::get();
		?>
		<div class="wrap mcm-social-wrap">
			<h1><?php esc_html_e( 'Medical College Manager — Social Bar', 'medical-college-manager' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Add your social links below. Leave a field empty to hide that icon. Then either turn on "Show on whole website", or place the shortcode on any page.', 'medical-college-manager' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'mcm_social_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Links', 'medical-college-manager' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( self::networks() as $key => $net ) : ?>
						<tr>
							<th scope="row"><label for="social_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $net['label'] ); ?></label></th>
							<td>
								<input
									type="<?php echo 'whatsapp' === $key ? 'text' : 'url'; ?>"
									id="social_<?php echo esc_attr( $key ); ?>"
									name="<?php echo esc_attr( self::OPTION ); ?>[social_<?php echo esc_attr( $key ); ?>]"
									value="<?php echo esc_attr( $s[ 'social_' . $key ] ); ?>"
									class="regular-text"
									placeholder="<?php echo 'whatsapp' === $key ? esc_attr__( 'e.g. 923001234567 (country code, digits only)', 'medical-college-manager' ) : esc_attr__( 'https://…', 'medical-college-manager' ); ?>"
								/>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Display', 'medical-college-manager' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show on whole website', 'medical-college-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[social_enable_sitewide]" value="1" <?php checked( $s['social_enable_sitewide'], '1' ); ?> />
								<?php esc_html_e( 'Float the bar on every page (no shortcode needed)', 'medical-college-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="social_position"><?php esc_html_e( 'Side', 'medical-college-manager' ); ?></label></th>
						<td>
							<select id="social_position" name="<?php echo esc_attr( self::OPTION ); ?>[social_position]">
								<option value="right" <?php selected( $s['social_position'], 'right' ); ?>><?php esc_html_e( 'Right', 'medical-college-manager' ); ?></option>
								<option value="left" <?php selected( $s['social_position'], 'left' ); ?>><?php esc_html_e( 'Left', 'medical-college-manager' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="social_mobile"><?php esc_html_e( 'On mobile', 'medical-college-manager' ); ?></label></th>
						<td>
							<select id="social_mobile" name="<?php echo esc_attr( self::OPTION ); ?>[social_mobile]">
								<option value="bottom" <?php selected( $s['social_mobile'], 'bottom' ); ?>><?php esc_html_e( 'Show as a bottom bar', 'medical-college-manager' ); ?></option>
								<option value="hide" <?php selected( $s['social_mobile'], 'hide' ); ?>><?php esc_html_e( 'Hide on small screens', 'medical-college-manager' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Social Bar', 'medical-college-manager' ) ); ?>
			</form>

			<hr/>
			<h2><?php esc_html_e( 'Shortcode', 'medical-college-manager' ); ?></h2>
			<p><?php esc_html_e( 'Prefer to place it manually (e.g. in a specific page or an Elementor Shortcode widget)? Use:', 'medical-college-manager' ); ?></p>
			<pre style="background:#fff;border:1px solid #e5e7eb;padding:12px;border-radius:8px;display:inline-block;">[mcm_social_bar]</pre>
			<p class="description"><?php esc_html_e( 'The bar is fixed/floating, so it appears over the page wherever the shortcode is placed. If "Show on whole website" is on, the shortcode is not needed (and duplicates are prevented automatically).', 'medical-college-manager' ); ?></p>
		</div>
		<?php
	}
}
