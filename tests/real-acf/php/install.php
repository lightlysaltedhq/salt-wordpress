<?php
/**
 * Installs the throwaway WordPress, activates ACF Pro and switches to the
 * salt theme. HARNESS ONLY.
 *
 * Env: SALT_HARNESS_WP, SALT_HARNESS_URL, SALT_HARNESS_ADMIN_PASS.
 *
 * @package Salt\Tests\RealAcf
 */

define( 'WP_INSTALLING', true );
require __DIR__ . '/wp.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$salt_harness_install = wp_install( 'Salt harness', 'admin', 'admin@example.test', false, '', (string) getenv( 'SALT_HARNESS_ADMIN_PASS' ) );

update_option( 'active_plugins', array( 'advanced-custom-fields-pro/acf.php' ) );
switch_theme( 'salt' );
update_option( 'permalink_structure', '' );

salt_harness_result(
	! empty( $salt_harness_install['user_id'] ) && 'salt' === get_option( 'stylesheet' ),
	'WordPress installed',
	sprintf( 'WordPress %s, theme %s, plugins %s', get_bloginfo( 'version' ), get_option( 'stylesheet' ), implode( ', ', (array) get_option( 'active_plugins' ) ) )
);
salt_harness_exit();
