<?php
/**
 * Compatibility manager.
 *
 * @package Lintoweb_Secure_Login
 */

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility manager class.
 */
class LSL_Compatibility {

	/**
	 * Initialize compatibility functionality.
	 *
	 * No custom WooCommerce authentication hooks are registered intentionally.
	 * WooCommerce continues to use WordPress authentication, sessions and logout.
	 *
	 * @return void
	 */
	public function init() {
		// Native WordPress authentication is the compatibility layer.
	}
}
