<?php
/**
 * Settings page view.
 *
 * @package Lintoweb_Secure_Login
 */

defined( 'ABSPATH' ) || exit;
$options   = LSL_Settings::get_options();
$login_url = trailingslashit( LSL_Login_URL::get_login_url() );
?>
<div class="wrap lsl-settings-wrap">
	<div class="lsl-settings-shell">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="lsl-settings-card">
		<div class="lsl-settings-intro">
			<p><?php esc_html_e( 'A focused login URL and presentation layer that keeps WordPress authentication intact.', 'lintoweb-secure-login' ); ?></p>
			<p class="lsl-settings-current-url">
				<strong><?php esc_html_e( 'Current login URL:', 'lintoweb-secure-login' ); ?></strong>
				<a href="<?php echo esc_url( $login_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $login_url ); ?></a>
			</p>
		</div>

		<form action="options.php" method="post">
			<?php
			settings_fields( 'lsl_settings' );
			do_settings_sections( 'lsl-settings' );
			submit_button( __( 'Save Settings', 'lintoweb-secure-login' ) );
			?>
		</form>
	</div>

	<div class="lsl-settings-note">
		<strong><?php esc_html_e( 'Compatibility note:', 'lintoweb-secure-login' ); ?></strong>
		<?php esc_html_e( 'WordPress and WooCommerce continue to use the native authentication, session and logout mechanisms.', 'lintoweb-secure-login' ); ?>
	</div>
	</div>
</div>
