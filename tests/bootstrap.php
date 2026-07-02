<?php
/**
 * PHPUnit bootstrap.
 *
 * Order matters: the capturing salt_log() is defined BEFORE the inc modules
 * load, so the pluggable production definition (which writes to error_log)
 * never registers in the test context — the same override mechanism a child
 * theme uses (ARCH07).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

// Dev-only Composer autoloader (PHPUnit itself). Production code never uses it.
require dirname( __DIR__ ) . '/vendor/autoload.php';

require __DIR__ . '/wp-shims.php';

/**
 * Capturing override of the pluggable salt_log().
 *
 * @param string $message Log line.
 * @return void
 */
function salt_log( string $message ): void {
	$GLOBALS['salt_test_logs'][] = $message;
}

// Foundation modules under test (each self-guards on ABSPATH, defined above).
$salt_modules = array(
	'/inc/helpers.php',
	'/inc/services/vendor.php',
	'/inc/colors.php',
	'/inc/modules.php',
	'/inc/sanitization.php',
	'/inc/query/resolver.php',
	'/inc/query/controls.php',
	'/inc/services/consent.php',
	'/inc/services/location-status.php',
	'/inc/performance.php',
	'/inc/render.php',
);
foreach ( $salt_modules as $salt_module ) {
	require dirname( __DIR__ ) . $salt_module;
}
unset( $salt_modules, $salt_module );

require __DIR__ . '/SaltTestCase.php';
require __DIR__ . '/SaltSnapshotTestCase.php';
