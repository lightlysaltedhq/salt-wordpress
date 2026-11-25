<?php
/**
 * Plugin Name: Salt harness: input-limit negative control
 *
 * Only the disposable site can switch off the guard. No production bypass.
 *
 * @package Salt\Tests\RealAcf
 */

add_action(
	'admin_init',
	static function () {
		if ( getenv( 'SALT_HARNESS_DISABLE_INPUT_GUARD' ) || get_option( 'salt_harness_input_guard_off' ) ) {
			remove_action( 'admin_init', 'salt_acf_refuse_incomplete_form', 0 );
		}
	},
	-1
);
