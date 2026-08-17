<?php
/**
 * Plugin uninstall handler.
 *
 * @package Lintoweb_Secure_Login
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'lsl_options' );
