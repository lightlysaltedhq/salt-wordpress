<?php
/**
 * Performance: asset manifest resolution, enqueue, image formats.
 *
 * Built out in Phase 1 (§5.8, ARCH03) — manifest-resolved enqueue with
 * $ver = null (no ?ver= query strings), salt_best_image_format().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-request cache cell for salt_best_image_format() (internal).
 *
 * @param string|null $set   New cached value, or null to read.
 * @param bool        $reset True to clear the cache.
 * @return string|null Cached format, or null when unset.
 */
function salt_image_format_cache( ?string $set = null, bool $reset = false ): ?string {
	static $format = null;

	if ( $reset ) {
		$format = null;
	} elseif ( null !== $set ) {
		$format = $set;
	}

	return $format;
}

if ( ! function_exists( 'salt_best_image_format' ) ) {
	/**
	 * Best modern image format the host can generate (PLUGGABLE — ARCH07).
	 *
	 * Probes the active image editor once per request and caches the answer.
	 *
	 * @return string 'image/avif', 'image/webp', or 'original'.
	 */
	function salt_best_image_format(): string {
		$cached = salt_image_format_cache();
		if ( null !== $cached ) {
			return $cached;
		}

		if ( wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ) ) {
			$format = 'image/avif';
		} elseif ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$format = 'image/webp';
		} else {
			$format = 'original';
		}

		return salt_image_format_cache( $format ) ?? $format;
	}
}

/**
 * Reset the per-request salt_best_image_format() cache (tests).
 *
 * @return void
 */
function salt_best_image_format_reset(): void {
	salt_image_format_cache( null, true );
}

/**
 * Map JPEG/PNG uploads to the best supported modern format.
 *
 * Filter callback for WP core 'image_editor_output_format'. When the host
 * supports neither AVIF nor WebP the map is returned unchanged.
 *
 * @param array<string,string> $formats Mime-type conversion map.
 * @return array<string,string>
 */
function salt_image_output_format( array $formats ): array {
	$best = salt_best_image_format();

	if ( 'original' === $best ) {
		return $formats;
	}

	$formats['image/jpeg'] = $best;
	$formats['image/png']  = $best;

	return $formats;
}
add_filter( 'image_editor_output_format', 'salt_image_output_format' );

/**
 * Warn wp-admin users when the host supports neither AVIF nor WebP.
 *
 * Hooked on 'admin_notices', which WordPress only fires inside wp-admin, so
 * no additional is_admin() guard is needed; the notice is informational
 * (no action button), so no capability check is required either.
 *
 * @return void
 */
function salt_image_support_admin_notice(): void {
	if ( 'original' !== salt_best_image_format() ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'This host supports neither AVIF nor WebP image generation — uploads will keep their original format. Ask your host to enable AVIF or WebP support in the PHP image library.', 'salt' )
	);
}
add_action( 'admin_notices', 'salt_image_support_admin_notice' );

/**
 * Resolve a logical asset name to its hashed dist URI via the manifest.
 *
 * The content hash in the filename is THE cache-buster (ARCH03) — no ?ver=
 * query strings anywhere. The manifest is read once per request.
 *
 * @param string $logical Logical file name, e.g. 'app.css'.
 * @return string|null Absolute URI on hit, null (logged) on any miss.
 */
function salt_asset( string $logical ): ?string {
	$manifest = salt_asset_manifest();
	$hashed   = $manifest[ $logical ] ?? null;

	if ( ! is_string( $hashed ) || '' === $hashed ) {
		salt_log( sprintf( 'salt_asset: no manifest entry for "%s" in assets/dist/manifest.json.', $logical ) );
		return null;
	}

	return get_template_directory_uri() . '/assets/dist/' . $hashed;
}

/**
 * Per-request manifest cache (internal accessor).
 *
 * Reads assets/dist/manifest.json once per request; failures log and yield
 * an empty map so every salt_asset() call reports its own missing key.
 *
 * @param bool $reset True to clear the cache (tests).
 * @return array<string,string>
 */
function salt_asset_manifest( bool $reset = false ): array {
	static $manifest = null;

	if ( $reset ) {
		$manifest = null;
		return array();
	}

	if ( null !== $manifest ) {
		return $manifest;
	}

	$manifest = array();
	$path     = get_template_directory() . '/assets/dist/manifest.json';

	if ( is_readable( $path ) ) {
		$raw     = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file at boot; WP_Filesystem is not appropriate here.
		$decoded = false !== $raw ? json_decode( $raw, true ) : null;

		if ( is_array( $decoded ) ) {
			$manifest = $decoded;
		} else {
			salt_log( 'salt_asset: assets/dist/manifest.json is unreadable or invalid JSON.' );
		}
	} else {
		salt_log( 'salt_asset: assets/dist/manifest.json is missing — run the asset build.' );
	}

	return $manifest;
}

/**
 * Reset the per-request manifest cache (tests).
 *
 * @return void
 */
function salt_asset_manifest_reset(): void {
	salt_asset_manifest( true );
}

/**
 * Admin notice for broken asset packaging (missing manifest entries).
 *
 * Registered by salt_enqueue_assets() only when resolution failed — broken
 * packaging must be loud (ARCH05 spirit). Hooked on 'admin_notices' so it
 * only ever runs inside wp-admin.
 *
 * @return void
 */
function salt_asset_missing_admin_notice(): void {
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'salt could not resolve its front-end assets from assets/dist/manifest.json — styles or scripts are not loading. Re-run the asset build and redeploy.', 'salt' )
	);
}

/**
 * Enqueue the theme's hashed assets ('wp_enqueue_scripts').
 *
 * Every handle uses $ver = null so WordPress emits no ?ver= query string —
 * the content hash in the filename is the cache-buster (ARCH03). Missing
 * assets are skipped (salt_asset() already logged) and surfaced loudly via
 * an admin notice.
 *
 * @return void
 */
function salt_enqueue_assets(): void {
	$style  = salt_asset( 'app.css' );
	$salt   = salt_asset( 'salt.css' );
	$script = salt_asset( 'app.js' );

	if ( null !== $style ) {
		wp_enqueue_style( 'salt-app', $style, array(), null );
	}

	// The contract's stylesheet, served verbatim as a stylesheet of its own
	// and AFTER Tailwind's (salt-contract README, Stylesheets): Tailwind v4
	// declares its layer order first, so salt.css's `@layer base` joins
	// Tailwind's base layer instead of preceding it. Depending on salt-app is
	// what makes WordPress print it second.
	if ( null !== $salt ) {
		wp_enqueue_style( 'salt-contract', $salt, null !== $style ? array( 'salt-app' ) : array(), null );
	}

	if ( null !== $script ) {
		wp_enqueue_script(
			'salt-app',
			$script,
			array(),
			null,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}

	if ( null === $style || null === $salt || null === $script ) {
		add_action( 'admin_notices', 'salt_asset_missing_admin_notice' );
	}
}
add_action( 'wp_enqueue_scripts', 'salt_enqueue_assets' );

/**
 * Quiet manifest probe — like salt_asset() but a miss is EXPECTED and not
 * logged. Used for per-section assets, where most sections ship no JS by
 * design (§7).
 *
 * @param string $logical Logical file name, e.g. 'sections/gallery.js'.
 * @return string|null URI on hit, null (silently) on miss.
 */
function salt_asset_optional( string $logical ): ?string {
	$hashed = salt_asset_manifest()[ $logical ] ?? null;

	if ( ! is_string( $hashed ) || '' === $hashed ) {
		return null;
	}

	return get_template_directory_uri() . '/assets/dist/' . $hashed;
}

/**
 * Server-side pre-scan of the current page's Flexible Content rows —
 * which section layouts does this page actually contain?
 *
 * Drives per-section asset enqueue (CSS/JS load only for sections on the
 * page — closing the spec's §7 conditional-enqueue silence) and is
 * available to templates that must make decisions before the loop runs.
 *
 * @return string[] Hyphenated section slugs, unique, in page order.
 */
function salt_page_section_slugs(): array {
	if ( ! salt_acf_active() || ! is_singular() ) {
		return array();
	}

	$rows = get_field( 'sections' );
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$slugs = array();
	foreach ( $rows as $row ) {
		if ( is_array( $row ) && isset( $row['acf_fc_layout'] ) ) {
			$slugs[] = str_replace( '_', '-', (string) $row['acf_fc_layout'] );
		}
	}

	return array_values( array_unique( $slugs ) );
}

/**
 * Enqueue per-section JS modules for the sections present on this page
 * ('wp_enqueue_scripts'). Modules are progressive enhancements (FAIL02) —
 * a section with no module entry simply has none.
 *
 * @return void
 */
function salt_enqueue_section_assets(): void {
	$slugs = salt_page_section_slugs();

	// The share row renders on single posts outside the FC loop — its
	// copy-link enhancement enqueues by route, not by section presence.
	if ( is_singular( 'post' ) ) {
		$slugs[] = 'share';
	}

	// The gallery's carousel layout draws the contract's carousel track
	// (markup/carousel-track.json), whose controls are the carousel's script.
	if ( in_array( 'gallery', $slugs, true ) ) {
		$slugs[] = 'carousel';
	}

	foreach ( array_unique( $slugs ) as $slug ) {
		$src = salt_asset_optional( 'sections/' . $slug . '.js' );
		if ( null !== $src ) {
			wp_enqueue_script(
				'salt-section-' . $slug,
				$src,
				array(),
				null,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'salt_enqueue_section_assets', 20 );
