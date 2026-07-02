<?php
/**
 * Admin live contrast checker — enqueue + pinned targets (spec §13/§14).
 *
 * Enqueues the Brand & Design screen's contrast-checker script and hands it
 * the exact surface/step/threshold targets the front-end theme really uses
 * (pinned to match assets/src/css/app.css — the unit test is the sync
 * tripwire). The checker itself is a soft-warning panel: it NEVER blocks
 * saving; the derived-ramp math guarantees legible pairings regardless
 * (inc/colors.php), this screen just makes the outcome visible while picking.
 *
 * The JS (assets/src/js/admin/contrast-checker.js) is a faithful port of the
 * inc/colors.php OKLCH math; bin/check-color-parity.mjs +
 * tests/unit/ColorVectorsTest.php hold both sides to the committed fixture
 * tests/fixtures/color-vectors.json.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is the given admin page slug the Brand & Design options sub-page?
 *
 * Pure predicate — the caller sanitises the slug (sanitize_key).
 *
 * @param string $page Admin page slug (already sanitised).
 * @return bool
 */
function salt_is_brand_options_screen( string $page ): bool {
	return 'salt-settings-brand' === $page;
}

/**
 * The contrast targets the checker validates against.
 *
 * These pin the REAL values the front-end consumes, sourced from
 * assets/src/css/app.css (tests/unit/ContrastCheckerTest.php asserts both
 * sides stay in sync — change app.css and the pin test trips):
 *
 * - Light surfaces: --color-surface (#ffffff) and --color-surface-alt
 *   (= --color-neutral-100, #e7ebf1). Light theme uses brand step 600
 *   (--color-brand: var(--color-primary-600)); text-on-brand is derived.
 * - Dark surfaces: --color-surface (= neutral-950, #141619) and
 *   --color-surface-alt (= neutral-900, #26292e). Dark theme uses brand
 *   steps 400/300 (--color-brand / --color-brand-strong).
 * - Border: --color-border (= neutral-200, #d2d8e0) checked at the 3:1
 *   non-text threshold.
 * - Text anchors: near-black #0f172a on light, near-white #f8fafc on dark
 *   (the salt_text_on() candidate pair).
 *
 * @return array<string, mixed> Targets map (also the JS config payload).
 */
function salt_contrast_targets(): array {
	return array(
		'thresholds' => array(
			'text' => 4.5,
			'ui'   => 3.0,
		),
		'light'      => array(
			'surfaces' => array( '#ffffff', '#e7ebf1' ),
			'steps'    => array( 600 ),
			'text'     => '#0f172a',
		),
		'dark'       => array(
			'surfaces' => array( '#141619', '#26292e' ),
			'steps'    => array( 400, 300 ),
			'text'     => '#f8fafc',
		),
		'border'     => '#d2d8e0',
		'fallback'   => '#4b5563',
		'fields'     => array(
			'primary'   => 'field_salt_options_brand_primary',
			'secondary' => 'field_salt_options_brand_secondary',
			'accent'    => 'field_salt_options_brand_accent',
		),
	);
}

/**
 * The full localisation payload for the checker script (targets + strings).
 *
 * @return array<string, mixed>
 */
function salt_contrast_checker_data(): array {
	$data         = salt_contrast_targets();
	$data['i18n'] = array(
		'heading' => __( 'Contrast check', 'salt' ),
		'intro'   => __( 'Live WCAG pairs derived from the Primary colour. Advisory only — saving is never blocked.', 'salt' ),
		'light'   => __( 'Light theme', 'salt' ),
		'dark'    => __( 'Dark theme', 'salt' ),
		'pass'    => __( 'Pass', 'salt' ),
		'fail'    => __( 'Review', 'salt' ),
		// translators: %s: minimum WCAG contrast ratio, e.g. "4.5".
		'needs'   => __( 'needs %s:1', 'salt' ),
		'onWord'  => __( 'on', 'salt' ),
		'brand'   => __( 'brand', 'salt' ),
		'textOn'  => __( 'text', 'salt' ),
		'border'  => __( 'border', 'salt' ),
	);

	return $data;
}

/**
 * Register the checker script for a page (testable inner seam).
 *
 * Gates on the Brand & Design screen, fails loud when the built asset is
 * missing from the manifest (the checker is core UX on that screen), and
 * localises the pinned targets under 'saltContrast'.
 *
 * @param string      $page Sanitised admin page slug.
 * @param string|null $src  Resolved script URI (salt_asset_optional()), or null.
 * @return bool True when the script was enqueued.
 */
function salt_contrast_checker_register( string $page, ?string $src ): bool {
	if ( ! salt_is_brand_options_screen( $page ) ) {
		return false;
	}

	if ( null === $src ) {
		salt_log( 'salt_contrast_checker: no manifest entry for "admin/contrast-checker.js" — the Brand screen contrast checker is not loading. Re-run the asset build.' );
		return false;
	}

	wp_enqueue_script(
		'salt-contrast',
		$src,
		array( 'acf-input' ),
		null,
		array( 'in_footer' => true )
	);
	wp_localize_script( 'salt-contrast', 'saltContrast', salt_contrast_checker_data() );

	return true;
}

/**
 * Enqueue the contrast checker on ACF admin screens
 * ('acf/input/admin_enqueue_scripts').
 *
 * @return void
 */
function salt_contrast_checker_enqueue(): void {
	// Read-only display gate for an admin script enqueue: no state is read
	// into content or written anywhere, so nonce verification is n/a.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$raw  = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$page = $raw;

	salt_contrast_checker_register( $page, salt_asset_optional( 'admin/contrast-checker.js' ) );
}
add_action( 'acf/input/admin_enqueue_scripts', 'salt_contrast_checker_enqueue' );
