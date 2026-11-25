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
	// SEC03 — tokenless updater; scoping of a legacy token if one is defined.
	// -----------------------------------------------------------------------

	/**
	 * Tokenless updates (SC-005): with no SALT_UPDATER_TOKEN the updater leaves
	 * every request to the theme's own repo exactly as WordPress built it, so
	 * Git Updater reads the public repo unauthenticated. Separate process:
	 * another test in this file defines the constant, and a constant cannot be
	 * undefined.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_updater_is_a_no_op_when_no_token_is_defined(): void {
		$this->assertFalse( defined( 'SALT_UPDATER_TOKEN' ) );

		$request = array(
			'timeout' => 5,
			'headers' => array( 'Accept' => 'application/vnd.github+json' ),
		);
		$urls    = array(
			'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/tags',
			'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/zipball/v6.0.0',
			'https://codeload.github.com/lightlysaltedhq/salt-wordpress/legacy.zip/refs/tags/v6.0.0',
			'https://github.com/lightlysaltedhq/salt-wordpress/archive/refs/tags/v6.0.0.zip',
		);
		foreach ( $urls as $url ) {
			$this->assertSame( $request, salt_updater_authorize_request( $request, $url ), $url );
			$this->assertSame( array(), salt_updater_authorize_request( array(), $url ), 'No headers key is invented: ' . $url );
		}
	}

	/**
	 * An empty constant counts as no token: no `token ` header with nothing
	 * after it. Separate process, because the constant it defines is permanent.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_updater_is_a_no_op_when_the_token_is_empty(): void {
		define( 'SALT_UPDATER_TOKEN', '' );

		$this->assertSame(
			array(),
			salt_updater_authorize_request( array(), 'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/tags' )
		);
	}

	public function test_updater_scopes_token_to_our_repo_only(): void {
		if ( ! defined( 'SALT_UPDATER_TOKEN' ) ) {
			define( 'SALT_UPDATER_TOKEN', 'test-token-value' );
		}

		$ours = salt_updater_authorize_request( array(), 'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/releases' );
		$this->assertSame( 'token test-token-value', $ours['headers']['Authorization'] );

		$other_repo = salt_updater_authorize_request( array(), 'https://api.github.com/repos/someone/else' );
		$this->assertArrayNotHasKey( 'headers', $other_repo, 'Token must never leak to other repos.' );

		$other_host = salt_updater_authorize_request( array(), 'https://evil.example/repos/lightlysaltedhq/salt-wordpress' );
		$this->assertArrayNotHasKey( 'headers', $other_host, 'Token must never leak to non-GitHub hosts.' );

		$existing = salt_updater_authorize_request(
			array( 'headers' => array( 'Authorization' => 'Bearer keep-me' ) ),
			'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/releases'
		);
		$this->assertSame( 'Bearer keep-me', $existing['headers']['Authorization'], 'Existing auth is never overwritten.' );
	}

	public function test_updater_matches_only_the_exact_repo(): void {
		$ours = array(
			'https://api.github.com/repos/lightlysaltedhq/salt-wordpress',
			'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/zipball/v5.0.0',
			'https://api.github.com/repos/LightlySaltedHQ/Salt-WordPress/releases/latest',
			'https://github.com/lightlysaltedhq/salt-wordpress/archive/refs/tags/v5.0.0.zip',
			'https://codeload.github.com/lightlysaltedhq/salt-wordpress/zip/refs/heads/main',
			'https://raw.githubusercontent.com/lightlysaltedhq/salt-wordpress/main/style.css',
		);
		foreach ( $ours as $url ) {
			$this->assertTrue( salt_updater_is_repo_url( $url ), $url );
		}

		$not_ours = array(
			// A sibling repo whose name starts with ours.
			'https://api.github.com/repos/lightlysaltedhq/salt-wordpress-legacy/releases',
			'https://github.com/lightlysaltedhq/salt-wordpress-legacy',
			// The pair embedded further down someone else's path.
			'https://api.github.com/repos/evil/x/contents/lightlysaltedhq/salt-wordpress',
			'https://github.com/evil/lightlysaltedhq/salt-wordpress/archive/main.zip',
			// The API form needs /repos/, the others must not have it.
			'https://api.github.com/lightlysaltedhq/salt-wordpress',
			'https://github.com/repos/lightlysaltedhq/salt-wordpress',
			// Dot segments the HTTP client could resolve to another repo.
			'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/../../evil/x',
			'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/%2e%2e/%2e%2e/evil/x',
			// Plain HTTP would send the token in the clear.
			'http://api.github.com/repos/lightlysaltedhq/salt-wordpress/releases',
		);
		foreach ( $not_ours as $url ) {
			$this->assertFalse( salt_updater_is_repo_url( $url ), $url );
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
