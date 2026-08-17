<?php
/**
 * Settings manager.
 *
 * @package Lintoweb_Secure_Login
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings manager class.
 */
class LSL_Settings {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'lsl_options';

	/**
	 * Initialize settings functionality.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Get default options.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'enabled'      => false,
			'login_slug'   => 'lw-login',
			'login_ui'     => true,
		);
	}

	/**
	 * Get all options merged with defaults.
	 *
	 * @return array
	 */
	public static function get_options() {
		$options = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return wp_parse_args( $options, self::get_defaults() );
	}

	/**
	 * Register Settings API structures.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'lsl_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => self::get_defaults(),
			)
		);

		add_settings_section(
			'lsl_login_section',
			__( 'Login settings', 'lintoweb-secure-login' ),
			array( $this, 'render_section' ),
			'lsl-settings'
		);

		add_settings_field(
			'lsl_enabled',
			__( 'Enable Custom Login URL', 'lintoweb-secure-login' ),
			array( $this, 'render_enabled_field' ),
			'lsl-settings',
			'lsl_login_section'
		);

		add_settings_field(
			'lsl_login_slug',
			__( 'Login Slug', 'lintoweb-secure-login' ),
			array( $this, 'render_slug_field' ),
			'lsl-settings',
			'lsl_login_section'
		);

		add_settings_field(
			'lsl_login_ui',
			__( 'Enable Custom Login UI', 'lintoweb-secure-login' ),
			array( $this, 'render_ui_field' ),
			'lsl-settings',
			'lsl_login_section'
		);
	}

	/**
	 * Section description.
	 *
	 * @return void
	 */
	public function render_section() {
		echo '<p>' . esc_html__( 'Configure the custom WordPress login URL and the lightweight login presentation.', 'lintoweb-secure-login' ) . '</p>';
	}

	/**
	 * Render enabled checkbox.
	 *
	 * @return void
	 */
	public function render_enabled_field() {
		$options = self::get_options();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( ! empty( $options['enabled'] ) ); ?> />
			<?php esc_html_e( 'Use the custom login URL instead of the standard login URL for generated login links.', 'lintoweb-secure-login' ); ?>
		</label>
		<?php
	}

	/**
	 * Render login slug field.
	 *
	 * @return void
	 */
	public function render_slug_field() {
		$options  = self::get_options();
		$slug     = (string) $options['login_slug'];
		$login_url = trailingslashit( LSL_Login_URL::get_login_url( $slug ) );
		?>
		<input type="text" class="regular-text code" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[login_slug]" value="<?php echo esc_attr( $slug ); ?>" autocomplete="off" />
		<p class="description">
			<?php esc_html_e( 'Use letters, numbers, hyphens or underscores. Example: secure-entry', 'lintoweb-secure-login' ); ?>
		</p>
		<p><strong><?php esc_html_e( 'Current URL:', 'lintoweb-secure-login' ); ?></strong> <code><?php echo esc_html( $login_url ); ?></code></p>
		<?php
	}

	/**
	 * Render UI checkbox.
	 *
	 * @return void
	 */
	public function render_ui_field() {
		$options = self::get_options();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[login_ui]" value="1" <?php checked( ! empty( $options['login_ui'] ) ); ?> />
			<?php esc_html_e( 'Apply the lightweight Lintoweb login design to the WordPress login screen.', 'lintoweb-secure-login' ); ?>
		</label>
		<?php
	}

	/**
	 * Sanitize all settings.
	 *
	 * @param mixed $input Raw option value.
	 * @return array
	 */
	public function sanitize_options( $input ) {
		$input = is_array( $input ) ? $input : array();
		$slug  = isset( $input['login_slug'] ) ? $this->sanitize_slug( $input['login_slug'] ) : '';

		if ( '' === $slug ) {
			$slug = self::get_defaults()['login_slug'];
		}

		$old_options = self::get_options();
		if ( $slug !== $old_options['login_slug'] && $this->slug_conflicts( $slug ) ) {
			$slug = $old_options['login_slug'];
			add_settings_error(
				'lsl_options',
				'lsl_login_slug_conflict',
				__( 'The selected Login Slug conflicts with an existing WordPress path. The previous value was kept.', 'lintoweb-secure-login' ),
				'error'
			);
		}

		return array(
			'enabled'    => ! empty( $input['enabled'] ),
			'login_slug' => $slug,
			'login_ui'   => ! empty( $input['login_ui'] ),
		);
	}

	/**
	 * Sanitize a login slug.
	 *
	 * @param mixed $value Raw slug.
	 * @return string
	 */
	public function sanitize_slug( $value ) {
		$value = is_string( $value ) ? wp_unslash( $value ) : '';
		$value = strtolower( trim( $value ) );
		$value = trim( $value, "/\\" );
		$value = preg_replace( '/[^a-z0-9_-]+/i', '-', $value );
		$value = preg_replace( '/[-_]{2,}/', '-', $value );
		$value = trim( $value, '-_' );

		if ( '' === $value || strlen( $value ) > 64 ) {
			return '';
		}

		return $value;
	}

	/**
	 * Determine whether a slug conflicts with a known sensitive route.
	 *
	 * @param string $slug Slug to check.
	 * @return bool
	 */
	private function slug_conflicts( $slug ) {
		$reserved = array(
			'wp-admin',
			'wp-login',
			'wp-login.php',
			'wp-content',
			'wp-includes',
			'wp-json',
			'wp-cron',
			'wp-signup',
			'wp-activate',
			'xmlrpc',
			'xmlrpc.php',
			'index.php',
			'feed',
			'comments',
			'author',
			'robots.txt',
			'sitemap',
		);

		if ( in_array( $slug, $reserved, true ) ) {
			return true;
		}

		$page = get_page_by_path( $slug );
		return $page instanceof WP_Post;
	}
}
