<?php
/**
 * Git Updater support: private-repo token custody (spec §4; review SEC03).
 *
 * TOKEN CUSTODY CONTRACT (SEC03):
 * - The repo access token lives in a wp-config.php constant,
 *   SALT_UPDATER_TOKEN — NEVER in wp_options or anywhere in the database,
 *   so it stays out of DB backups and admin-visible screens.
 * - Each site gets its own FINE-GRAINED, READ-ONLY deploy token scoped to
 *   the single parent-theme repo, so one breached site cannot be used
 *   against the rest of the fleet. Rotation + revocation procedure:
 *   docs/RUNBOOK-ROLLBACK.md §Token custody.
 *
 * Mechanism: rather than depending on a specific updater plugin's own
 * token storage, salt injects the Authorization header into WP HTTP
 * requests that target THIS theme's GitHub repo (works with Git Updater
 * and plain WP HTTP alike). The pure resolver is unit-tested.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The GitHub repo path ("owner/repo") this parent theme updates from.
 *
 * Derived from the style.css Update URI header at release time.
 */
define( 'SALT_UPDATE_REPO', 'otigs/salt' );

/**
 * Inject the deploy token into HTTP requests for the parent-theme repo.
 *
 * Pure decision function (unit-tested): only api.github.com/github.com
 * requests whose path targets SALT_UPDATE_REPO get the header, and only
 * when the wp-config constant is defined and no Authorization header is
 * already present.
 *
 * @param array<string,mixed> $parsed_args HTTP request args.
 * @param string              $url         Request URL.
 * @return array<string,mixed>
 */
function salt_updater_authorize_request( array $parsed_args, string $url ): array {
	if ( ! defined( 'SALT_UPDATER_TOKEN' ) || '' === (string) SALT_UPDATER_TOKEN ) {
		return $parsed_args;
	}

	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );

	$is_github   = in_array( $host, array( 'api.github.com', 'github.com', 'raw.githubusercontent.com', 'codeload.github.com' ), true );
	$is_our_repo = str_contains( $path, SALT_UPDATE_REPO );

	if ( ! $is_github || ! $is_our_repo ) {
		return $parsed_args;
	}

	if ( isset( $parsed_args['headers']['Authorization'] ) || isset( $parsed_args['headers']['authorization'] ) ) {
		return $parsed_args;
	}

	if ( ! isset( $parsed_args['headers'] ) || ! is_array( $parsed_args['headers'] ) ) {
		$parsed_args['headers'] = array();
	}
	$parsed_args['headers']['Authorization'] = 'token ' . SALT_UPDATER_TOKEN;

	return $parsed_args;
}
add_filter( 'http_request_args', 'salt_updater_authorize_request', 10, 2 );
