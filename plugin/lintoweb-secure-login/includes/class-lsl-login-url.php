<?php
/**
 * Login URL manager.
 *
 * @package Lintoweb_Secure_Login
 */

defined( 'ABSPATH' ) || exit;

/**
 * Login URL manager class.
 */
class LSL_Login_URL {

	/**
	 * Initialize login URL functionality.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 3 );
		add_filter( 'lostpassword_url', array( $this, 'filter_lostpassword_url' ), 10, 2 );
		add_filter( 'site_url', array( $this, 'filter_site_url' ), 10, 4 );
		add_filter( 'network_site_url', array( $this, 'filter_network_site_url' ), 10, 3 );
		add_filter( 'wp_redirect', array( $this, 'filter_redirect' ), 10, 2 );
		add_action( 'init', array( $this, 'handle_requests' ), 0 );
		add_action( 'init', array( $this, 'block_direct_wp_admin' ), 1 );
	}

	/**
	 * Get the configured login URL.
	 *
	 * @param string|null $slug Optional slug override.
	 * @param string       $scheme URL scheme.
	 * @return string
	 */
	public static function get_login_url( $slug = null, $scheme = 'login' ) {
		if ( null === $slug ) {
			$options = LSL_Settings::get_options();
			$slug    = $options['login_slug'];
		}

		return site_url( user_trailingslashit( $slug ), $scheme );
	}

	/**
	 * Determine whether custom login URL is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$options = LSL_Settings::get_options();
		return ! empty( $options['enabled'] ) && ! empty( $options['login_slug'] );
	}

	/**
	 * Filter WordPress login URLs.
	 *
	 * @param string $login_url Login URL.
	 * @param string $redirect Redirect target.
	 * @param bool   $force_reauth Whether to force reauthentication.
	 * @return string
	 */
	public function filter_login_url( $login_url, $redirect, $force_reauth ) {
		if ( ! self::is_enabled() ) {
			return $login_url;
		}

		$login_url = self::get_login_url();

		if ( ! empty( $redirect ) ) {
			$login_url = add_query_arg( 'redirect_to', $redirect, $login_url );
		}

		if ( $force_reauth ) {
			$login_url = add_query_arg( 'reauth', '1', $login_url );
		}

		return $login_url;
	}

	/**
	 * Filter lost password URL.
	 *
	 * @param string $lostpassword_url Lost password URL.
	 * @param string $redirect Redirect target.
	 * @return string
	 */
	public function filter_lostpassword_url( $lostpassword_url, $redirect ) {
		if ( ! self::is_enabled() ) {
			return $lostpassword_url;
		}

		$url = self::get_login_url();
		$url = add_query_arg( 'action', 'lostpassword', $url );

		if ( ! empty( $redirect ) ) {
			$url = add_query_arg( 'redirect_to', $redirect, $url );
		}

		return $url;
	}

	/**
	 * Filter site URLs used by login forms.
	 *
	 * This is intentionally scoped to WordPress login schemes and wp-login.php paths
	 * so normal frontend URLs remain untouched.
	 *
	 * @param string      $url URL.
	 * @param string      $path Path.
	 * @param string|null $scheme Scheme.
	 * @param int|null    $blog_id Blog ID.
	 * @return string
	 */
	public function filter_site_url( $url, $path, $scheme = null, $blog_id = null ) {
		if ( ! self::is_enabled() || ! is_string( $path ) || ! $this->is_login_scheme( $scheme ) ) {
			return $url;
		}

		if ( false === strpos( $path, 'wp-login.php' ) ) {
			return $url;
		}

		return $this->replace_login_path( $url );
	}

	/**
	 * Filter network URLs used by login forms.
	 *
	 * @param string      $url URL.
	 * @param string      $path Path.
	 * @param string|null $scheme Scheme.
	 * @return string
	 */
	public function filter_network_site_url( $url, $path, $scheme = null ) {
		if ( ! self::is_enabled() || ! is_string( $path ) || ! $this->is_login_scheme( $scheme ) ) {
			return $url;
		}

		if ( false === strpos( $path, 'wp-login.php' ) ) {
			return $url;
		}

		return $this->replace_login_path( $url );
	}

	/**
	 * Keep Core redirects that target wp-login.php on the custom endpoint.
	 *
	 * @param string $location Redirect location.
	 * @param int    $status Redirect status.
	 * @return string
	 */
	public function filter_redirect( $location, $status ) {
		if ( ! self::is_enabled() || ! is_string( $location ) ) {
			return $location;
		}

		$path = wp_parse_url( $location, PHP_URL_PATH );
		if ( ! is_string( $path ) || ! preg_match( '#(?:^|/)wp-login\\.php$#i', $path ) ) {
			return $location;
		}

		return $this->replace_login_path( $location );
	}

	/**
	 * Handle custom route and legacy URL.
	 *
	 * @return void
	 */
	public function handle_requests() {
		if ( ! self::is_enabled() ) {
			return;
		}

		if ( $this->is_custom_login_request() ) {
			$this->serve_wp_login();
		}

		if ( $this->is_legacy_login_request() && $this->should_redirect_legacy_request() ) {
			$this->redirect_legacy_request();
		}
	}

	/**
	 * Return a real 404 response for a direct unauthenticated wp-admin request.
	 *
	 * WordPress normally redirects unauthenticated admin requests to wp-login.php.
	 * That redirect would then be rewritten to the custom login URL by this plugin.
	 * The intended behaviour is to keep wp-admin non-discoverable and let users enter
	 * through the explicitly configured custom login endpoint instead.
	 *
	 * @return void
	 */
	public function block_direct_wp_admin() {
		if ( ! self::is_enabled() || is_user_logged_in() || ! $this->is_wp_admin_root_request() ) {
			return;
		}

		$this->render_not_found();
	}

	/**
	 * Determine whether the current request is the wp-admin root entry point.
	 *
	 * admin-ajax.php, admin-post.php and other wp-admin endpoints are intentionally
	 * excluded so normal WordPress integrations are not affected.
	 *
	 * @return bool
	 */
	private function is_wp_admin_root_request() {
		$request_path = untrailingslashit( $this->get_request_path() );
		$admin_path   = wp_parse_url( admin_url(), PHP_URL_PATH );
		$admin_path   = is_string( $admin_path ) ? untrailingslashit( $admin_path ) : '/wp-admin';

		if ( $request_path === $admin_path ) {
			return true;
		}

		return $request_path === $admin_path . '/index.php';
	}

	/**
	 * Serve the WordPress core login controller for the custom route.
	 *
	 * @return void
	 */
	private function serve_wp_login() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		nocache_headers();

		// wp-login.php is normally executed in the global script scope.
		// When it is included from a class method, older/current Core branches
		// can encounter undefined locals such as $user_login and $error.
		// Seed the variables Core expects without replacing Core authentication.
		global $user_login, $error, $interim_login, $action, $errors;
		$user_login    = '';
		$error         = '';
		$interim_login = false;
		$action        = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
		$errors        = new WP_Error();

		require ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Render the active theme's 404 template instead of wp_die().
	 *
	 * @return void
	 */
	private function render_not_found() {
		global $wp_query;

		// Mark the current request as a real 404 before loading the active theme's
		// template, so themes that inspect the main query render their normal 404 UI.
		if ( isset( $wp_query ) && is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();

		$template = get_404_template();
		if ( $template ) {
			include $template;
		} else {
			wp_die(
				esc_html__( 'Page not found.', 'lintoweb-secure-login' ),
				esc_html__( '404 Not Found', 'lintoweb-secure-login' ),
				array( 'response' => 404 )
			);
		}

		exit;
	}

	/**
	 * Redirect the legacy login endpoint to the custom route.
	 *
	 * @return void
	 */
	private function redirect_legacy_request() {
		$url = self::get_login_url();

		if ( ! empty( $_GET ) ) {
			$query = wp_unslash( $_GET );
			if ( is_array( $query ) ) {
				$url = add_query_arg( $query, $url );
			}
		}

		wp_safe_redirect( $url, 302, 'Lintoweb Secure Login' );
		exit;
	}

	/**
	 * Determine whether the current request is the custom login route.
	 *
	 * @return bool
	 */
	private function is_custom_login_request() {
		$request_path = $this->get_request_path();
		$login_path   = wp_parse_url( self::get_login_url(), PHP_URL_PATH );

		if ( ! is_string( $login_path ) ) {
			return false;
		}

		return untrailingslashit( $request_path ) === untrailingslashit( $login_path );
	}

	/**
	 * Determine whether the current request directly targets wp-login.php.
	 *
	 * @return bool
	 */
	private function is_legacy_login_request() {
		$request_path = $this->get_request_path();
		return (bool) preg_match( '#/wp-login\.php$#i', $request_path );
	}

	/**
	 * Determine whether the legacy request should redirect.
	 *
	 * POST requests are left to WordPress for compatibility with integrations
	 * that may still submit directly to wp-login.php.
	 *
	 * @return bool
	 */
	private function should_redirect_legacy_request() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		if ( 'POST' === $method ) {
			return false;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login';
		$allowed_legacy_actions = array( 'logout', 'postpass' );

		return ! in_array( $action, $allowed_legacy_actions, true );
	}

	/**
	 * Get the current request path.
	 *
	 * @return string
	 */
	private function get_request_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		$path = is_string( $path ) ? rawurldecode( $path ) : '';

		return '/' . ltrim( $path, '/' );
	}

	/**
	 * Determine whether a URL scheme is login-related.
	 *
	 * @param string|null $scheme URL scheme.
	 * @return bool
	 */
	private function is_login_scheme( $scheme ) {
		return in_array( $scheme, array( 'login', 'login_post' ), true );
	}

	/**
	 * Replace a wp-login.php path while preserving query parameters.
	 *
	 * @param string $url Original URL.
	 * @return string
	 */
	private function replace_login_path( $url ) {
		$slug = LSL_Settings::get_options()['login_slug'];
		return preg_replace( '#(^|/)wp-login\\.php#i', '$1' . rawurlencode( $slug ), $url, 1 );
	}
}
