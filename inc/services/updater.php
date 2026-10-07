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
 * Must equal the style.css GitHub Theme URI header (a unit test holds them
 * together). Git Updater also requires the theme's folder to be the repo
 * name, so the part after the slash is the folder this theme must live in.
 */
define( 'SALT_UPDATE_REPO', 'lightlysaltedhq/salt-wordpress' );

/**
 * Whether a URL addresses THIS theme's GitHub repo, and nothing wider.
 *
 * The owner/repo pair must be whole path segments in the position each host
 * uses (api.github.com/repos/{owner}/{repo}, otherwise /{owner}/{repo}),
 * followed by "/" or the end. A substring match would also hand the token to
 * a sibling such as {repo}-legacy or to a path that merely embeds the pair.
 * Dot segments are refused outright because the HTTP client may resolve
 * them after this check, sending the token to a different repo.
 *
 * @param string $url Request URL.
 * @return bool
 */
function salt_updater_is_repo_url( string $url ): bool {
	if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
		return false;
	}

	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );

	if ( preg_match( '#(^|/)\.{1,2}(/|$)|%2e#i', $path ) ) {
		return false;
	}

	if ( 'api.github.com' === $host ) {
		$prefix = '/repos/';
	} elseif ( in_array( $host, array( 'github.com', 'raw.githubusercontent.com', 'codeload.github.com' ), true ) ) {
		$prefix = '/';
	} else {
		return false;
	}

	// GitHub owner and repo names are case-insensitive.
	$base = strtolower( $prefix . SALT_UPDATE_REPO );
	$path = strtolower( $path );

	return $path === $base || str_starts_with( $path, $base . '/' );
}

/**
 * Inject the deploy token into HTTP requests for the parent-theme repo.
 *
 * Pure decision function (unit-tested): only HTTPS GitHub requests for
 * exactly SALT_UPDATE_REPO (salt_updater_is_repo_url()) get the header, and only
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

	if ( ! salt_updater_is_repo_url( $url ) ) {
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

/**
 * The admin notice shown when the theme sits in the wrong folder.
 *
 * Git Updater matches a theme to its repo by folder name, so a parent left
 * at themes/salt (the pre-5.0.0 name) silently gets no updates.
 *
 * @param string $template   Active parent theme folder (get_template()).
 * @param bool   $can_switch Whether the current user can switch themes.
 * @return string Notice HTML, or '' when no notice is due.
 */
function salt_updater_folder_notice( string $template, bool $can_switch ): string {
	$folder = substr( SALT_UPDATE_REPO, (int) strpos( SALT_UPDATE_REPO, '/' ) + 1 );

	if ( ! $can_switch || $folder === $template ) {
		return '';
	}

	return sprintf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
		esc_html__( 'salt theme:', 'salt' ),
		esc_html(
			sprintf(
				/* translators: 1: current theme folder. 2: required folder. */
				__( 'Git Updater will not offer updates for this theme until its folder is renamed from "%1$s" to "%2$s" (and each child theme says "Template: %2$s"). See docs/RUNBOOK-ROLLBACK.md.', 'salt' ),
				$template,
				$folder
			)
		)
	);
}

/**
 * Print the wrong-folder notice for users who can switch themes.
 *
 * @return void
 */
function salt_updater_folder_admin_notice(): void {
	echo salt_updater_folder_notice( get_template(), current_user_can( 'switch_themes' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in salt_updater_folder_notice().
}
add_action( 'admin_notices', 'salt_updater_folder_admin_notice' );
