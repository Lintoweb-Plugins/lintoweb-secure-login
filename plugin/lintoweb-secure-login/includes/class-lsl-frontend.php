<?php
/**
 * Frontend login presentation manager.
 *
 * @package Lintoweb_Secure_Login
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend login presentation class.
 */
class LSL_Frontend {

	/**
	 * Initialize frontend functionality.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! LSL_Login_URL::is_enabled() || ! $this->is_enabled() ) {
			return;
		}

		add_filter( 'login_body_class', array( $this, 'login_body_class' ), 10, 2 );
		add_filter( 'login_headerurl', array( $this, 'login_header_url' ) );
		add_filter( 'login_headertext', array( $this, 'login_header_text' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'login_footer', array( $this, 'render_orb_background' ), 5 );
	}

	/**
	 * Determine whether custom UI is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		$options = LSL_Settings::get_options();
		return ! empty( $options['login_ui'] );
	}

	/**
	 * Add plugin-specific body classes.
	 *
	 * @param string[] $classes Existing classes.
	 * @param string   $action Login action.
	 * @return string[]
	 */
	public function login_body_class( $classes, $action ) {
		$classes[] = 'lsl-login';
		$classes[] = 'lsl-login-action-' . sanitize_html_class( $action );
		return $classes;
	}

	/**
	 * Set the login logo destination.
	 *
	 * @param string $url Existing URL.
	 * @return string
	 */
	public function login_header_url( $url ) {
		return home_url( '/' );
	}

	/**
	 * Set accessible login logo text.
	 *
	 * @param string $text Existing text.
	 * @return string
	 */
	public function login_header_text( $text ) {
		return get_bloginfo( 'name', 'display' );
	}

	/**
	 * Enqueue login-only CSS.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$css = LSL_DIR . 'frontend/css/login.css';

		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'lsl-login',
				LSL_URL . 'frontend/css/login.css',
				array(),
				LSL_VERSION
			);
		}

		$js = LSL_DIR . 'frontend/js/login-motion.js';
		if ( file_exists( $js ) ) {
			wp_enqueue_script(
				'lsl-login-motion',
				LSL_URL . 'frontend/js/login-motion.js',
				array(),
				LSL_VERSION,
				true
			);
		}

		$logo = $this->get_logo_url();
		if ( $logo ) {
			$inline = ':root{--lsl-login-logo:url("' . esc_url_raw( $logo ) . '");}';
			wp_add_inline_style( 'lsl-login', $inline );
		}
	}

	/**
	 * Render the lightweight ambient orb layer behind the login form.
	 *
	 * @return void
	 */
	public function render_orb_background() {
		?>
		<div class="lsl-login-orbs" aria-hidden="true">
			<span class="lsl-orb lsl-orb-1"></span>
			<span class="lsl-orb lsl-orb-2"></span>
			<span class="lsl-orb lsl-orb-3"></span>
			<span class="lsl-orb lsl-orb-4"></span>
			<span class="lsl-orb lsl-orb-5"></span>
		</div>
		<?php
	}

	/**
	 * Resolve site logo or icon.
	 *
	 * @return string
	 */
	private function get_logo_url() {
		$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo = wp_get_attachment_image_url( $custom_logo_id, 'full' );
			if ( $logo ) {
				return $logo;
			}
		}

		$icon = get_site_icon_url( 512 );
		return $icon ? $icon : '';
	}
}
