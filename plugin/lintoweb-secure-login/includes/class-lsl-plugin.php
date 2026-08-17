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
			new LSL_Admin(),
		);

		foreach ( $modules as $module ) {
			if ( method_exists( $module, 'init' ) ) {
				$module->init();
			}
		}
	}
}