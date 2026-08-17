<?php
/**
 * Plugin Name:       Lintoweb Secure Login
 * Plugin URI:        https://lintoweb.com/
 * Description:       Changes the WordPress login URL and provides an isolated login UI without replacing WordPress authentication.
 * Version:           1.0.7
 * Author:            Lintoweb
 * Author URI:        https://lintoweb.com/
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Text Domain:       lintoweb-secure-login
 * Domain Path:       /languages
 *
 * @package Lintoweb_Secure_Login
 */

defined( 'ABSPATH' ) || exit;

define( 'LSL_VERSION', '1.0.7' );
define( 'LSL_FILE', __FILE__ );
define( 'LSL_DIR', plugin_dir_path( __FILE__ ) );
define( 'LSL_URL', plugin_dir_url( __FILE__ ) );

require_once LSL_DIR . 'includes/class-lsl-plugin.php';
require_once LSL_DIR . 'includes/class-lsl-login-url.php';
require_once LSL_DIR . 'includes/class-lsl-settings.php';
require_once LSL_DIR . 'includes/class-lsl-compatibility.php';
require_once LSL_DIR . 'includes/class-lsl-frontend.php';
require_once LSL_DIR . 'admin/class-lsl-admin.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function lsl_init() {
	$plugin = new LSL_Plugin();
	$plugin->init();
}

add_action( 'plugins_loaded', 'lsl_init' );
register_activation_hook( LSL_FILE, array( 'LSL_Plugin', 'activate' ) );
