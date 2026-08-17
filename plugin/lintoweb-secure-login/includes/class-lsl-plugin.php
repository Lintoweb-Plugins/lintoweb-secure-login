<?php
/**
 * Main plugin bootstrap class.
 *
 * @package Lintoweb_Secure_Login
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
class LSL_Plugin {

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init() {
		$modules = array(
			new LSL_Settings(),
			new LSL_Login_URL(),
			new LSL_Compatibility(),
			new LSL_Frontend(),
			new LSL_Admin(),
		);

		foreach ( $modules as $module ) {
			if ( method_exists( $module, 'init' ) ) {
				$module->init();
			}
		}
	}

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		$option_name = LSL_Settings::OPTION_NAME;
		$current     = get_option( $option_name, array() );
		$defaults    = LSL_Settings::get_defaults();

		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$options = wp_parse_args( $current, $defaults );

		// Custom login routing is opt-in. Activation must never enable it automatically.
		if ( empty( $current ) ) {
			$options['enabled'] = false;
		}

		if ( get_page_by_path( $options['login_slug'] ) instanceof WP_Post ) {
			$options['enabled'] = false;
		}

		update_option( $option_name, $options, false );
	}
}
