<?php
/**
 * Plugin Name:       Lintoweb Secure Login
 * Plugin URI:        https://lintoweb.com/
 * Description:       Customizes the WordPress login URL without modifying WordPress core files.
 * Version:           1.0.0
 * Author:            Lintoweb
 * Author URI:        https://lintoweb.com/
 * Text Domain:       lintoweb-secure-login
 * Domain Path:       /languages
 *
 * @package Lintoweb_Secure_Login
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'LSL_VERSION', '1.0.0' );
define( 'LSL_FILE', __FILE__ );
define( 'LSL_DIR', plugin_dir_path( __FILE__ ) );
define( 'LSL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load core classes.
 */
require_once LSL_DIR . 'includes/class-lsl-plugin.php';
require_once LSL_DIR . 'includes/class-lsl-login-url.php';
require_once LSL_DIR . 'includes/class-lsl-settings.php';
require_once LSL_DIR . 'includes/class-lsl-compatibility.php';
require_once LSL_DIR . 'admin/class-lsl-admin.php';

/**
 * Initialize the plugin.
 */
function lsl_init() {
	$plugin = new LSL_Plugin();
	$plugin->init();
}

add_action( 'plugins_loaded', 'lsl_init' );