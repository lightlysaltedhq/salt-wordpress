<?php
/**
 * Git Updater support: tokenless updates from the public repo (SEC03, SC-005).
 *
 * UPDATE CUSTODY CONTRACT (SEC03):
 * - A site holds NO credential for updates. The repository is public, so Git
 *   Updater reads it unauthenticated and a site needs nothing in wp-config.php,
 *   wp_options or anywhere else. There is no per-site token to issue, rotate or
 *   revoke, and a site keeps working on its installed version if the repo stops
 *   being maintained (SC-005 handoff rule).
 * - Git Updater's own authenticated requests are tied to its licence (after the
 *   trial it flushes stored access tokens monthly unless licensed), so
 *   unauthenticated reads are the only licence-free path. GitHub allows 60
 *   unauthenticated API requests per hour per server IP; see
 *   docs/RUNBOOK-ROLLBACK.md §Updates without a token.
 * - Distribution is the repository's tag zipball with assets/dist committed and
 *   the runtime libraries vendored (FLEET08), so the zipball installs as it
 *   stands: no build step on the site, no release asset to build, upload and
 *   authenticate.
 *
 * Mechanism: this theme adds no Authorization header to any request, and
 * reads no credential from wp-config.php. This file keeps only the repo
 * constant and the wrong-folder notice.
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
