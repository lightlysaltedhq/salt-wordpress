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

// The render path asks whether a child theme is active (salt-overrides.json,
// inc/overrides.php), so get_stylesheet_directory() must exist for every suite.
// Loaded BEFORE wp-shims.php so its child-first locate_template() is the one
// defined (identical to the parent-only shim while no child theme is active).
require __DIR__ . '/shims/child-theme.php';

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
	'/inc/query/cards.php',
	'/inc/services/consent.php',
	'/inc/services/location-status.php',
	'/inc/services/maps.php',
	'/inc/services/updater.php',
	'/inc/services/override-flag.php',
	'/inc/services/rate-limit.php',
	'/inc/services/captcha.php',
	'/inc/services/mailer.php',
	'/inc/services/webhooks.php',
	'/inc/services/submissions.php',
	'/inc/services/contact.php',
	'/inc/services/analytics.php',
	'/inc/performance.php',
	'/inc/setup.php',
	'/inc/render.php',
	'/inc/overrides.php',
	'/inc/acf.php',
	'/inc/services/character-count.php',
	'/inc/cpt/topics.php',
	'/inc/security.php',
);
foreach ( $salt_modules as $salt_module ) {
	require dirname( __DIR__ ) . $salt_module;
}
unset( $salt_modules, $salt_module );

// Modules that land via parallel build agents — loaded once present.
foreach ( array( '/inc/media.php', '/inc/schema/graph.php', '/inc/services/svg.php', '/inc/services/post-meta.php', '/inc/services/views.php' ) as $salt_pending ) {
	if ( file_exists( dirname( __DIR__ ) . $salt_pending ) ) {
		require dirname( __DIR__ ) . $salt_pending;
	}
}
unset( $salt_pending );

require __DIR__ . '/SaltTestCase.php';
require __DIR__ . '/SaltSnapshotTestCase.php';
