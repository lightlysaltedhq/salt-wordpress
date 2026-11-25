<?php
/**
 * Security baseline: CSP nonce, headers (SEC06), tokenless updater
 * (SEC03), ACF guard (DEP01).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class SecurityTest extends SaltTestCase {

	public function test_csp_nonce_is_stable_within_request_and_urlsafe(): void {
		$nonce = salt_csp_nonce();

		$this->assertSame( $nonce, salt_csp_nonce(), 'Nonce must be stable per request.' );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9_-]{20,}$/', $nonce );
	}

	public function test_only_the_consent_api_settings_script_gets_the_csp_nonce(): void {
		$stamped = salt_consent_api_script_nonce( array( 'id' => 'wp-consent-api-js-extra' ) );
		$other   = salt_consent_api_script_nonce( array( 'id' => 'some-plugin-js-extra' ) );

		$this->assertSame( salt_csp_nonce(), $stamped['nonce'] ?? null );
		$this->assertArrayNotHasKey( 'nonce', $other );
	}

	public function test_only_core_speculation_rules_get_the_csp_nonce(): void {
		$stamped = salt_speculation_rules_script_nonce( array( 'type' => 'speculationrules' ) );
		$other   = salt_speculation_rules_script_nonce( array( 'type' => 'text/javascript' ) );
		$untyped = salt_speculation_rules_script_nonce( array( 'id' => 'some-plugin-js-after' ) );

		$this->assertSame( salt_csp_nonce(), $stamped['nonce'] ?? null );
		$this->assertArrayNotHasKey( 'nonce', $other );
		$this->assertArrayNotHasKey( 'nonce', $untyped );
	}

	public function test_security_headers_ship_the_baseline_set(): void {
		$headers = salt_security_headers();

		$this->assertSame( 'nosniff', $headers['X-Content-Type-Options'] );
		$this->assertSame( 'strict-origin-when-cross-origin', $headers['Referrer-Policy'] );
		$this->assertArrayHasKey( 'Permissions-Policy', $headers );

		$csp = $headers['Content-Security-Policy'];
		$this->assertStringContainsString( "default-src 'self'", $csp );
		$this->assertStringContainsString( "'nonce-" . salt_csp_nonce() . "'", $csp );
		$this->assertStringContainsString( "object-src 'none'", $csp );
		$this->assertStringContainsString( "frame-ancestors 'self'", $csp );
	}

	public function test_csp_contains_no_third_party_origin(): void {
		$csp = salt_security_headers()['Content-Security-Policy'];

		$this->assertDoesNotMatchRegularExpression(
			'#https?://#',
			$csp,
			'PRIV01: no external origin may be allow-listed while salt_consent() is a fail-closed stub.'
		);
	}

	public function test_csp_policy_is_filterable_for_sites(): void {
		add_filter( 'salt_csp_policy', static fn( string $csp ): string => $csp . '; report-uri /csp', 20 );

		$this->assertStringContainsString( 'report-uri /csp', salt_security_headers()['Content-Security-Policy'] );
	}

	// -----------------------------------------------------------------------
	// SEC03 — tokenless updater.
	// -----------------------------------------------------------------------

	/**
	 * Updates carry no credential (SC-005). A fresh PHP process loads the
	 * theme exactly as a site does, with a leftover SALT_UPDATER_TOKEN from
	 * wp-config.php defined, and runs GitHub requests for this repo through
	 * the http_request_args filter: none may gain an Authorization header.
	 * A subprocess, because setUp() clears the hooks the bootstrap registered.
	 */
	public function test_no_authorization_header_is_added_to_github_requests(): void {
		$urls = array(
			'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/tags',
			'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/zipball/v6.0.0',
			'https://codeload.github.com/lightlysaltedhq/salt-wordpress/legacy.zip/refs/tags/v6.0.0',
			'https://github.com/lightlysaltedhq/salt-wordpress/archive/refs/tags/v6.0.0.zip',
			'https://raw.githubusercontent.com/lightlysaltedhq/salt-wordpress/main/style.css',
		);
		$code = 'define( "SALT_UPDATER_TOKEN", "leftover-token" ); require $argv[1];'
			. ' $out = array(); foreach ( array_slice( $argv, 2 ) as $url ) {'
			. ' $out[ $url ] = apply_filters( "http_request_args", array( "headers" => array( "Accept" => "application/vnd.github+json" ) ), $url ); }'
			. ' echo json_encode( $out );';

		$command = escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' ' . escapeshellarg( __DIR__ . '/../bootstrap.php' )
			. ' ' . implode( ' ', array_map( 'escapeshellarg', $urls ) );
		$out     = shell_exec( $command ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test-only subprocess.
		$this->assertIsString( $out );

		$requests = json_decode( $out, true );
		$this->assertIsArray( $requests, 'The subprocess must print JSON: ' . $out );
		$this->assertCount( count( $urls ), $requests );
		foreach ( $urls as $url ) {
			$this->assertSame( array( 'Accept' => 'application/vnd.github+json' ), $requests[ $url ]['headers'], $url );
		}
	}

	public function test_updater_header_constants_match_the_theme_header(): void {
		$root  = dirname( __DIR__, 2 );
		$style = (string) file_get_contents( $root . '/style.css' );
		$boot  = (string) file_get_contents( $root . '/functions.php' );

		$this->assertMatchesRegularExpression( '/^GitHub Theme URI:\s*' . preg_quote( SALT_UPDATE_REPO, '/' ) . '\s*$/m', $style );
		$this->assertMatchesRegularExpression( '#^Update URI:\s*https://github\.com/' . preg_quote( SALT_UPDATE_REPO, '#' ) . '\s*$#m', $style );

		$this->assertSame( 1, preg_match( '/^Version:\s*(\S+)\s*$/m', $style, $header ) );
		$this->assertSame( 1, preg_match( "/define\(\s*'SALT_VERSION',\s*'([^']+)'\s*\)/", $boot, $constant ) );
		$this->assertSame( $header[1], $constant[1], 'SALT_VERSION must equal the style.css Version header.' );
	}

	public function test_folder_notice_shows_only_for_a_wrong_folder_and_a_capable_user(): void {
		$this->assertSame( '', salt_updater_folder_notice( 'salt-wordpress', true ), 'Right folder: no notice.' );
		$this->assertSame( '', salt_updater_folder_notice( 'salt', false ), 'No switch_themes: no notice.' );

		$html = salt_updater_folder_notice( 'salt', true );
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'from &quot;salt&quot; to &quot;salt-wordpress&quot;', $html );

		$hostile = salt_updater_folder_notice( '<script>x</script>', true );
		$this->assertStringNotContainsString( '<script>', $hostile, 'The folder name is escaped.' );
	}

	// -----------------------------------------------------------------------
	// DEP01 — ACF guard.
	// -----------------------------------------------------------------------

	public function test_acf_active_mirrors_function_existence(): void {
		$this->assertSame( function_exists( 'acf_add_local_field_group' ), salt_acf_active() );
	}

	/**
	 * Without ACF the logo keeps the field's 48px default, in its args and
	 * in the nonce'd head rule. Separate process: ACF's shim, once another
	 * test loads it, outlives the test.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_without_acf_the_logo_keeps_its_48px_default(): void {
		require_once dirname( __DIR__, 2 ) . '/inc/services/chrome.php';
		$this->assertFalse( salt_acf_active() );

		$this->assertSame( 48, salt_logo_args()['height'] );
		ob_start();
		salt_output_theme_vars_css();
		$this->assertStringContainsString( '.salt-logo{--logo-height:48px}</style>', (string) ob_get_clean() );
	}

	/**
	 * Core's global styles print inside the theme's nonce'd style, ahead of
	 * the theme's own rules. A
	 * stylesheet that would close the element early is left out, as core's
	 * own printer leaves it out. Separate process: the stand-in
	 * wp_get_global_stylesheet() outlives the test.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_core_global_styles_print_in_the_nonced_theme_style(): void {
		require_once dirname( __DIR__, 2 ) . '/inc/services/chrome.php';
		$GLOBALS['salt_test_global_stylesheet'] = ':root{--wp--preset--color--black:#000000}';
		if ( ! function_exists( 'wp_get_global_stylesheet' ) ) {
			/**
			 * Stand-in for core's global stylesheet.
			 *
			 * @return string
			 */
			function wp_get_global_stylesheet(): string {
				return $GLOBALS['salt_test_global_stylesheet'];
			}
		}

		ob_start();
		salt_output_theme_vars_css();
		$this->assertMatchesRegularExpression( '#<style id="salt-theme-vars" nonce="[^"]+">:root\{--wp--preset--color--black:\#000000\}\.salt-logo\{--logo-height:48px\}</style>#', (string) ob_get_clean() );

		$GLOBALS['salt_test_global_stylesheet'] = 'a{}</style><script>x()</script>';
		ob_start();
		salt_output_theme_vars_css();
		$this->assertStringContainsString( 'nonce="', $out = (string) ob_get_clean() );
		$this->assertStringNotContainsString( '<script>', $out );
	}

	public function test_acf_notice_message_covers_absent_outdated_and_ok(): void {
		$this->assertNull( salt_acf_notice_message( true, true, '6.4.1' ), 'Healthy ACF: no notice.' );

		$absent = salt_acf_notice_message( false, false, '' );
		$this->assertIsString( $absent );
		$this->assertStringContainsString( 'not active', $absent );
		$this->assertStringContainsString( 'nothing will fatal', $absent );

		$outdated = salt_acf_notice_message( true, false, '5.9.0' );
		$this->assertIsString( $outdated );
		$this->assertStringContainsString( SALT_MIN_ACF_VERSION, $outdated );
		$this->assertStringContainsString( '5.9.0', $outdated );
	}

	public function test_field_key_convention_violations_are_logged_in_debug(): void {
		salt_assert_field_keys(
			array(
				array(
					'key'  => 'field_wrong_prefix_name',
					'name' => 'x',
				),
			)
		);

		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'DATA02', $this->logs()[0] );
	}
}
