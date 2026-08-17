<?php
/**
 * Plugin Name:       Lintoweb Secure Login
 * Plugin URI:        https://lintoweb.com/
 * Description:       Customizes the WordPress login URL without modifying WordPress core files.
 * Version:           1.0.8
 * Author:            Lintoweb
 * Author URI:        https://lintoweb.com/
 * Text Domain:       lintoweb-secure-login
 * Domain Path:       /languages
 * Update URI:        https://github.com/Lintoweb-Plugins/lintoweb-secure-login/
 *
 * @package Lintoweb_Secure_Login
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'LSL_VERSION', '1.0.8' );
define( 'LSL_FILE', __FILE__ );
define( 'LSL_DIR', plugin_dir_path( __FILE__ ) );
define( 'LSL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin Update Checker.
 */
require_once LSL_DIR . 'plugin-update-checker/plugin-update-checker.php';

$lsl_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/Lintoweb-Plugins/lintoweb-secure-login/',
	__FILE__,
	'lintoweb-secure-login'
);

$lsl_update_checker->getVcsApi()->enableReleaseAssets(
	'/^lintoweb-secure-login-[0-9.]+\.zip$/i'
);

/**
 * Load core classes.
 *
 * Keep the dependency order explicit: LSL_Plugin instantiates the module
 * classes during init(), so every module class must be loaded first.
 */
require_once LSL_DIR . 'includes/class-lsl-settings.php';
require_once LSL_DIR . 'includes/class-lsl-login-url.php';
require_once LSL_DIR . 'includes/class-lsl-compatibility.php';
require_once LSL_DIR . 'includes/class-lsl-frontend.php';
require_once LSL_DIR . 'admin/class-lsl-admin.php';
require_once LSL_DIR . 'includes/class-lsl-plugin.php';

/**
 * Register activation handling.
 */
register_activation_hook( LSL_FILE, array( 'LSL_Plugin', 'activate' ) );

/**
 * Initialize the plugin.
 */
function lsl_init() {
	$plugin = new LSL_Plugin();
	$plugin->init();
}

add_action( 'plugins_loaded', 'lsl_init' );