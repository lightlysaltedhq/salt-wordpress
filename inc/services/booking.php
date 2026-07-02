<?php
/**
 * Booking facade — Cal.com / Calendly behind a progressive click-to-load
 * link (spec §9; PRIV01/§7).
 *
 * The x-booking component renders a PLAIN LINK to the site's booking page
 * (the one third-party URL allowed in facade markup — it loads nothing
 * until the visitor navigates). assets/src/js/booking.js intercepts the
 * click ONLY when the 'marketing' consent category is granted client-side
 * and swaps the link for the provider's inline embed; denied / no-CMP means
 * the plain link simply navigates (progressive fallback, zero third-party
 * loads on our page).
 *
 * Provider + URL are site-configurable non-secrets
 * (inc/fields/options-integrations.php). The CSP is widened for exactly the
 * configured provider's origins via the dedicated 'salt_csp_policy'
 * callback below, only while the integration is configured.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The supported booking providers: allowed URL hosts + CSP origins.
 *
 * Hosts gate the configured booking URL (a Calendly setup must link to
 * calendly.com, etc. — fail closed on mismatch); csp lists the exact
 * origins appended to script-src/frame-src/connect-src when active.
 *
 * @return array<string,array{hosts:array<string>,csp:array<string>}>
 */
function salt_booking_providers(): array {
	return array(
		'calcom'   => array(
			'hosts' => array( 'cal.com' ),
			'csp'   => array( 'https://app.cal.com', 'https://cal.com' ),
		),
		'calendly' => array(
			'hosts' => array( 'calendly.com' ),
			'csp'   => array( 'https://assets.calendly.com', 'https://calendly.com' ),
		),
	);
}

/**
 * Whether a host is the given registrable domain or a subdomain of it.
 *
 * @param string $host   Lowercased host.
 * @param string $domain Allowed domain, e.g. 'calendly.com'.
 * @return bool
 */
function salt_booking_host_matches( string $host, string $domain ): bool {
	return $host === $domain || str_ends_with( $host, '.' . $domain );
}

/**
 * The validated booking configuration, or null when not configured.
 *
 * Null (silently) when the provider option is simply unset; null + log on
 * every MISconfiguration: unknown provider, missing URL, non-https URL, or
 * a URL whose host does not belong to the chosen provider (fail closed —
 * the URL travels into markup and the embed JS).
 *
 * @return array{provider:string,url:string}|null
 */
function salt_booking_config(): ?array {
	if ( ! salt_acf_active() ) {
		return null;
	}

	$provider = (string) ( get_field( 'booking_provider', 'option' ) ?? '' );
	if ( '' === $provider ) {
		return null;
	}

	$providers = salt_booking_providers();
	if ( ! isset( $providers[ $provider ] ) ) {
		salt_log( sprintf( 'booking: unknown provider "%s" — integration disabled.', $provider ) );
		return null;
	}

	$url = trim( (string) ( get_field( 'booking_url', 'option' ) ?? '' ) );
	if ( '' === $url ) {
		salt_log( sprintf( 'booking: provider "%s" is set but no booking URL is configured — integration disabled.', $provider ) );
		return null;
	}

	$parts  = wp_parse_url( $url );
	$scheme = strtolower( (string) ( is_array( $parts ) ? ( $parts['scheme'] ?? '' ) : '' ) );
	$host   = strtolower( (string) ( is_array( $parts ) ? ( $parts['host'] ?? '' ) : '' ) );

	if ( 'https' !== $scheme || '' === $host ) {
		salt_log( sprintf( 'booking: URL "%s" is not a valid https URL — integration disabled (fail closed).', $url ) );
		return null;
	}

	$host_ok = false;
	foreach ( $providers[ $provider ]['hosts'] as $domain ) {
		if ( salt_booking_host_matches( $host, $domain ) ) {
			$host_ok = true;
			break;
		}
	}

	if ( ! $host_ok ) {
		salt_log( sprintf( 'booking: URL host "%s" does not belong to provider "%s" — integration disabled (fail closed).', $host, $provider ) );
		return null;
	}

	return array(
		'provider' => $provider,
		'url'      => $url,
	);
}

/**
 * Append source origins to named CSP directives (booking-internal helper).
 *
 * Existing directives are widened in place; directives absent from the
 * base policy (frame-src here) are appended. Never removes anything.
 *
 * @param string                      $csp       Serialised CSP.
 * @param array<string,array<string>> $additions Directive => origins.
 * @return string
 */
function salt_booking_csp_append( string $csp, array $additions ): string {
	$directives = explode( '; ', $csp );

	foreach ( $directives as $i => $directive ) {
		foreach ( $additions as $name => $origins ) {
			if ( str_starts_with( $directive, $name . ' ' ) ) {
				$directives[ $i ] = $directive . ' ' . implode( ' ', $origins );
				unset( $additions[ $name ] );
			}
		}
	}

	foreach ( $additions as $name => $origins ) {
		$directives[] = $name . ' ' . implode( ' ', $origins );
	}

	return implode( '; ', $directives );
}

/**
 * 'salt_csp_policy' callback: allow the configured provider's origins.
 *
 * Appends ONLY the active provider's documented origins, to script-src
 * (embed loader), frame-src (the inline booking iframe) and connect-src
 * (embed XHR), and only while the integration is configured. Registered
 * at priority 10 (parent band).
 *
 * @param string $csp Serialised CSP.
 * @return string
 */
function salt_booking_csp_policy( string $csp ): string {
	$config = salt_booking_config();
	if ( null === $config ) {
		return $csp;
	}

	$origins = salt_booking_providers()[ $config['provider'] ]['csp'];

	return salt_booking_csp_append(
		$csp,
		array(
			'script-src'  => $origins,
			'frame-src'   => $origins,
			'connect-src' => $origins,
		)
	);
}
add_filter( 'salt_csp_policy', 'salt_booking_csp_policy', 10 );

/**
 * Enqueue the booking click-intercept when configured
 * ('wp_enqueue_scripts', 20).
 *
 * The script is a tiny facade loader that no-ops when no
 * [data-salt-booking] link is on the page; the provider embed itself loads
 * only post-click, post-consent, from the provider origin.
 *
 * @return void
 */
function salt_booking_enqueue(): void {
	if ( null === salt_booking_config() ) {
		return;
	}

	$src = salt_asset( 'booking.js' );
	if ( null !== $src ) {
		wp_enqueue_script(
			'salt-booking',
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
add_action( 'wp_enqueue_scripts', 'salt_booking_enqueue', 20 );
