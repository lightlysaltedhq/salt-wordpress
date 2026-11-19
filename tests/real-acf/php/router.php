<?php
/** Forward virtual sitemap XML to WordPress as a normal web server would. */
$salt_path = filter_var( $_SERVER['REQUEST_URI'] ?? '', FILTER_SANITIZE_URL ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- WordPress is not loaded in the CLI router.
if ( PHP_SAPI === 'cli-server' && preg_match( '~^/wp-sitemap[^/]*\.xml$~D', $salt_path ) ) {
	require getenv( 'SALT_HARNESS_WP' ) . '/index.php';
	return true;
}
return false;
