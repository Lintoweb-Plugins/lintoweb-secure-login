<?php
/**
 * Admin manager.
 *
 * @package Lintoweb_Secure_Login
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin manager class.
 */
class LSL_Admin {

	/**
	 * Initialize admin functionality.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the settings page.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Lintoweb Secure Login', 'lintoweb-secure-login' ),
			__( 'Secure Login', 'lintoweb-secure-login' ),
			'manage_options',
			'lsl-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'lintoweb-secure-login' ) );
		}

		require LSL_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Enqueue settings assets only on this page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_lsl-settings' !== $hook_suffix ) {
			return;
		}

		$css = LSL_DIR . 'admin/css/settings.css';
		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'lsl-admin',
				LSL_URL . 'admin/css/settings.css',
				array(),
				LSL_VERSION
			);
		}
	}
}
