<?php
/**
 * Site-chrome service (spec §12a): the option-driven helpers behind the
 * header/footer parts and the runtime CSS seam the theme-vars emitter
 * composes.
 *
 * Runtime seams consumed elsewhere: salt_header_runtime_css() is composed
 * by salt_output_theme_vars_css() (inc/setup.php, wp_head 20) into the one
 * nonce-stamped <style id="salt-theme-vars"> — numeric tokens only, never
 * inline style="" attributes (SEC06/CSP).
 *
 * Every function degrades safely without ACF (DEP01) and fails loud via
 * salt_log() on invalid data — never a silent blank (ARCH05/FAIL01).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Logo height + the header runtime CSS seam.
// ---------------------------------------------------------------------------

/**
 * Validate a raw logo_height option value (PURE — unit-tested).
 *
 * Bounds mirror the field definition (inc/fields/options-logo-fonts.php):
 * 16–200px, default 48. Out-of-range values clamp LOUDLY; non-numeric junk
 * degrades to the default LOUDLY; an unset option is silently the default.
 *
 * @param mixed $raw Raw option value (int, numeric string, null, junk).
 * @return int Validated height in px.
 */
function salt_logo_height_value( $raw ): int {
	if ( null === $raw || '' === $raw || false === $raw ) {
		return 48;
	}

	if ( ! is_numeric( $raw ) ) {
		salt_log( sprintf( 'chrome: non-numeric logo_height "%s" — using the 48px default.', is_scalar( $raw ) ? (string) $raw : get_debug_type( $raw ) ) );
		return 48;
	}

	$height = (int) $raw;

	if ( $height < 16 ) {
		salt_log( sprintf( 'chrome: logo_height %d is below the 16px minimum — clamped.', $height ) );
		return 16;
	}

	if ( $height > 200 ) {
		salt_log( sprintf( 'chrome: logo_height %d is above the 200px maximum — clamped.', $height ) );
		return 200;
	}

	return $height;
}

/**
 * The validated logo height option (px).
 *
 * @return int
 */
function salt_logo_height(): int {
	return salt_logo_height_value( salt_acf_active() ? get_field( 'logo_height', 'option' ) : null );
}

/**
 * Header runtime CSS — the seam salt_output_theme_vars_css() composes.
 *
 * Always emits the validated --logo-height (the x-logo CSS in app.css
 * consumes it); adds the sticky-header anchor offset --salt-scroll-padding
 * ONLY when the header is sticky (scroll-padding-top in app.css defaults
 * to 0 otherwise). Numeric tokens only — safe to print unescaped inside
 * the nonce-stamped <style>.
 *
 * @return string CSS, '' when ACF is absent (compiled defaults apply).
 */
function salt_header_runtime_css(): string {
	if ( ! salt_acf_active() ) {
		return '';
	}

	$css = sprintf( ':root{--logo-height:%dpx}', salt_logo_height() );

	if ( (bool) get_field( 'header_sticky', 'option' ) ) {
		$css .= ':root{--salt-scroll-padding:calc(var(--logo-height,3rem) + 2.5rem)}';
	}

	return $css;
}

// ---------------------------------------------------------------------------
// Header helpers: logo args + CTA.
// ---------------------------------------------------------------------------

/**
 * Build the x-logo component args from the Logo & Fonts options.
 *
 * @return array{light_id:int,dark_id:int,text:string,url:string}
 */
function salt_logo_args(): array {
	$light = 0;
	$dark  = 0;
	$text  = '';

	if ( salt_acf_active() ) {
		$light = absint( get_field( 'logo_light', 'option' ) );
		$dark  = absint( get_field( 'logo_dark', 'option' ) );
		$text  = (string) ( get_field( 'logo_text_fallback', 'option' ) ?? '' );
	}

	return array(
		'light_id' => $light,
		'dark_id'  => $dark,
		'text'     => $text,
		'url'      => home_url( '/' ),
	);
}

/**
 * Normalise an ACF link value into x-button args (PURE — unit-tested).
 *
 * Target follows the x-button contract ('' or '_blank' only — anything
 * else is dropped and logged; the component re-validates as
 * defence-in-depth). A link without a URL is "no CTA" (null), a link
 * without a title falls back to the URL as its label — never a blank
 * button (FAIL01).
 *
 * @param mixed $link Raw ACF link array (title, url, target) or anything else.
 * @return array{label:string,url:string,target:string}|null Null when no CTA is configured.
 */
function salt_header_cta( $link ): ?array {
	if ( ! is_array( $link ) ) {
		return null;
	}

	$url = trim( (string) ( $link['url'] ?? '' ) );
	if ( '' === $url ) {
		return null;
	}

	$title  = trim( (string) ( $link['title'] ?? '' ) );
	$target = (string) ( $link['target'] ?? '' );

	if ( ! in_array( $target, array( '', '_blank' ), true ) ) {
		salt_log( sprintf( 'chrome: invalid header_cta target "%s" — dropped.', $target ) );
		$target = '';
	}

	return array(
		'label'  => '' !== $title ? $title : $url,
		'url'    => $url,
		'target' => $target,
	);
}

/**
 * The header CTA from the Header & Footer options.
 *
 * @return array{label:string,url:string,target:string}|null
 */
function salt_header_cta_args(): ?array {
	return salt_acf_active() ? salt_header_cta( get_field( 'header_cta', 'option' ) ) : null;
}

// ---------------------------------------------------------------------------
// Nav menu fallback (both locations).
// ---------------------------------------------------------------------------

/**
 * wp_nav_menu fallback_cb: render NOTHING, log once per location.
 *
 * An unassigned menu location is a configuration gap, not an error page —
 * the header/footer keep rendering (logo, CTA, copyright) while the gap is
 * visible in the log (ARCH05). Static once-per-location flag; $reset is
 * the test seam (see salt_nav_menu_fallback_reset()).
 *
 * @param array<string,mixed> $args  wp_nav_menu args (theme_location read).
 * @param bool                $reset Internal: clear the once-flags.
 * @return string Always ''.
 */
function salt_nav_menu_fallback( array $args = array(), bool $reset = false ): string {
	static $logged = array();

	if ( $reset ) {
		$logged = array();
		return '';
	}

	$location = (string) ( $args['theme_location'] ?? '' );

	if ( ! isset( $logged[ $location ] ) ) {
		$logged[ $location ] = true;
		salt_log( sprintf( 'chrome: no menu assigned to the "%s" location — nav rendered empty.', $location ) );
	}

	return '';
}

/**
 * Reset the fallback's once-per-location log flags (per request / per test).
 *
 * @return void
 */
function salt_nav_menu_fallback_reset(): void {
	salt_nav_menu_fallback( array(), true );
}

// ---------------------------------------------------------------------------
// Footer helpers: copyright + social row.
// ---------------------------------------------------------------------------

/**
 * The auto © line: current year (gmdate) + org_name, falling back to the
 * site title.
 *
 * @return string
 */
function salt_footer_copyright(): string {
	$name = salt_acf_active() ? trim( (string) ( get_field( 'org_name', 'option' ) ?? '' ) ) : '';

	if ( '' === $name ) {
		$name = (string) get_bloginfo( 'name' );
	}

	return trim( sprintf( '© %s %s', gmdate( 'Y' ), $name ) );
}

/**
 * The known social hosts map: host => label + candidate icon name.
 *
 * Labels are proper nouns (not translated). The icon is only USED when the
 * built-in set ships it (salt_chrome_icon_exists()) — otherwise the label
 * renders as text (the current built-in set has no social glyphs, so text
 * is today's baseline; adding assets/src/icons/{name}.svg upgrades the row
 * without a code change).
 *
 * @return array<string,array{label:string,icon:string}>
 */
function salt_chrome_social_hosts(): array {
	return array(
		'facebook.com'  => array(
			'label' => 'Facebook',
			'icon'  => 'facebook',
		),
		'instagram.com' => array(
			'label' => 'Instagram',
			'icon'  => 'instagram',
		),
		'linkedin.com'  => array(
			'label' => 'LinkedIn',
			'icon'  => 'linkedin',
		),
		'youtube.com'   => array(
			'label' => 'YouTube',
			'icon'  => 'youtube',
		),
		'tiktok.com'    => array(
			'label' => 'TikTok',
			'icon'  => 'tiktok',
		),
		'twitter.com'   => array(
			'label' => 'X',
			'icon'  => 'x-social',
		),
		'x.com'         => array(
			'label' => 'X',
			'icon'  => 'x-social',
		),
	);
}

/**
 * Whether a built-in icon exists for the given name.
 *
 * @param string $name Icon name (assets/src/icons/{name}.svg).
 * @return bool
 */
function salt_chrome_icon_exists( string $name ): bool {
	return '' !== $name
		&& 1 === preg_match( '/^[a-z0-9-]+$/', $name )
		&& is_readable( get_template_directory() . '/assets/src/icons/' . $name . '.svg' );
}

/**
 * Resolve one sameAs URL into a footer social link (PURE — unit-tested).
 *
 * Known hosts (including their subdomains) get the platform label and —
 * when the built-in set ships one — an x-icon name; unknown hosts label as
 * their bare domain (www. stripped). URLs without a usable host are
 * dropped LOUDLY.
 *
 * @param string $url Profile URL from the org_same_as repeater.
 * @return array{url:string,icon:string,label:string}|null Null when the URL is unusable.
 */
function salt_social_link( string $url ): ?array {
	$url  = trim( $url );
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

	if ( '' === $host ) {
		salt_log( sprintf( 'chrome: org_same_as URL "%s" has no host — skipped in the footer social row.', $url ) );
		return null;
	}

	$host = preg_replace( '/^www\./', '', $host ) ?? $host;

	foreach ( salt_chrome_social_hosts() as $known => $platform ) {
		if ( $host === $known || str_ends_with( $host, '.' . $known ) ) {
			return array(
				'url'   => $url,
				'icon'  => salt_chrome_icon_exists( $platform['icon'] ) ? $platform['icon'] : '',
				'label' => $platform['label'],
			);
		}
	}

	return array(
		'url'   => $url,
		'icon'  => '',
		'label' => $host,
	);
}

/**
 * The footer social row from the org_same_as repeater (Company & Schema).
 *
 * @return array<int,array{url:string,icon:string,label:string}>
 */
function salt_footer_social_links(): array {
	if ( ! salt_acf_active() ) {
		return array();
	}

	$rows = get_field( 'org_same_as', 'option' );
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$links = array();
	foreach ( $rows as $row ) {
		$link = salt_social_link( is_array( $row ) ? (string) ( $row['url'] ?? '' ) : '' );
		if ( null !== $link ) {
			$links[] = $link;
		}
	}

	return $links;
}
