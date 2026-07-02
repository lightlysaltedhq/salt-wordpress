<?php
/**
 * CAPTCHA verification + no-JS challenge (SEC01/SEC02, PRIV02, SEC03).
 *
 * NOTE on ordering: secret keys are wp-config CONSTANTS, which persist for
 * the whole PHPUnit process once defined. The missing-secret tests are
 * declared (and therefore run) BEFORE any test that defines
 * SALT_TURNSTILE_SECRET / SALT_RECAPTCHA_SECRET — the same declaration-
 * order pattern SecurityTest uses for SALT_UPDATER_TOKEN.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/http.php';
require_once __DIR__ . '/../../inc/services/captcha.php';
require_once __DIR__ . '/../../inc/fields/options-captcha.php';

final class CaptchaTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_http_reset();
		salt_captcha_reset();
		$GLOBALS['salt_test_acf_groups']  = array();
		$GLOBALS['salt_test_is_singular'] = true;
	}

	/**
	 * Seed an option-context ACF field fixture.
	 */
	private function set_option_field( string $name, $value ): void {
		$GLOBALS['salt_test_fields']['option'][ $name ] = $value;
	}

	/**
	 * Fully configure Turnstile (secret constant + provider + site key).
	 */
	private function configure_turnstile( string $site_key = 'ts-site-key' ): void {
		if ( ! defined( 'SALT_TURNSTILE_SECRET' ) ) {
			define( 'SALT_TURNSTILE_SECRET', 'ts-secret-value' );
		}
		$this->set_option_field( 'captcha_provider', 'turnstile' );
		$this->set_option_field( 'captcha_turnstile_site_key', $site_key );
	}

	/**
	 * Fully configure reCAPTCHA (secret constant + provider + site key).
	 */
	private function configure_recaptcha(): void {
		if ( ! defined( 'SALT_RECAPTCHA_SECRET' ) ) {
			define( 'SALT_RECAPTCHA_SECRET', 're-secret-value' );
		}
		$this->set_option_field( 'captcha_provider', 'recaptcha' );
		$this->set_option_field( 'captcha_recaptcha_site_key', 're-site-key' );
	}

	/**
	 * Queue a scripted siteverify response.
	 *
	 * @param array<string,mixed>|WP_Error $response Scripted response.
	 */
	private function script_response( $response ): void {
		$GLOBALS['salt_test_http']['responses'][] = $response;
	}

	// -----------------------------------------------------------------------
	// Unconfigured / half-configured states (declared FIRST — see header).
	// -----------------------------------------------------------------------

	public function test_provider_is_empty_when_nothing_configured(): void {
		$this->assertSame( '', salt_captcha_provider() );
		$this->assertSame( array(), $this->logs(), 'No provider selected is a valid state, not an error.' );
	}

	public function test_provider_empty_and_logged_once_when_secret_constant_missing(): void {
		if ( defined( 'SALT_TURNSTILE_SECRET' ) ) {
			$this->markTestSkipped( 'SALT_TURNSTILE_SECRET already defined by an earlier test — ordering assumption broken.' );
		}

		$this->set_option_field( 'captcha_provider', 'turnstile' );
		$this->set_option_field( 'captcha_turnstile_site_key', 'ts-site-key' );

		$this->assertSame( '', salt_captcha_provider() );
		$this->assertSame( '', salt_captcha_provider(), 'Still unconfigured on repeat calls.' );
		$this->assertCount( 1, $this->logs(), 'Half-configuration is logged exactly once per request.' );
		$this->assertStringContainsString( 'SALT_TURNSTILE_SECRET', $this->logs()[0] );
	}

	public function test_unknown_provider_value_fails_closed(): void {
		$this->set_option_field( 'captcha_provider', 'hcaptcha' );

		$this->assertSame( '', salt_captcha_provider() );
		$this->assertCount( 1, $this->logs() );
	}

	public function test_verify_fails_closed_when_unconfigured(): void {
		$result = salt_captcha_verify( 'some-token', '203.0.113.9' );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'unconfigured', $result['reason'] );
		$this->assertSame( array(), $GLOBALS['salt_test_http']['requests'], 'No provider = no outbound request.' );
	}

	public function test_widget_html_is_empty_when_no_provider(): void {
		$this->assertSame( '', salt_captcha_widget_html() );
	}

	// -----------------------------------------------------------------------
	// Configured provider (constants defined from here on).
	// -----------------------------------------------------------------------

	public function test_provider_empty_and_logged_when_site_key_missing(): void {
		if ( ! defined( 'SALT_TURNSTILE_SECRET' ) ) {
			define( 'SALT_TURNSTILE_SECRET', 'ts-secret-value' );
		}
		$this->set_option_field( 'captcha_provider', 'turnstile' );

		$this->assertSame( '', salt_captcha_provider() );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'site key', $this->logs()[0] );
	}

	public function test_provider_active_when_fully_configured(): void {
		$this->configure_turnstile();

		$this->assertSame( 'turnstile', salt_captcha_provider() );
		$this->assertSame( array(), $this->logs() );
	}

	// -----------------------------------------------------------------------
	// SEC02 — siteverify round trip.
	// -----------------------------------------------------------------------

	public function test_verify_success_posts_secret_response_and_remoteip(): void {
		$this->configure_turnstile();
		$this->script_response(
			array(
				'code' => 200,
				'body' => '{"success":true,"hostname":"example.test"}',
			)
		);

		$result = salt_captcha_verify( 'the-token', '203.0.113.9' );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'verified', $result['reason'] );

		$this->assertCount( 1, $GLOBALS['salt_test_http']['requests'] );
		$request = $GLOBALS['salt_test_http']['requests'][0];
		$this->assertSame( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', $request['url'] );
		$this->assertSame( 5, $request['args']['timeout'] );
		$this->assertSame( SALT_TURNSTILE_SECRET, $request['args']['body']['secret'] );
		$this->assertSame( 'the-token', $request['args']['body']['response'] );
		$this->assertSame( '203.0.113.9', $request['args']['body']['remoteip'] );
	}

	public function test_verify_fails_on_empty_token_without_network_call(): void {
		$this->configure_turnstile();

		$result = salt_captcha_verify( '   ', '203.0.113.9' );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'empty-token', $result['reason'] );
		$this->assertSame( array(), $GLOBALS['salt_test_http']['requests'] );
	}

	public function test_verify_fails_closed_on_network_error(): void {
		$this->configure_turnstile();
		$this->script_response( new WP_Error( 'http_request_failed', 'timed out' ) );

		$result = salt_captcha_verify( 'the-token', '203.0.113.9' );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'network-error', $result['reason'] );
		$this->assertNotEmpty( $this->logs(), 'Network failures fail loud (SEC02).' );
	}

	public function test_verify_fails_closed_on_non_200(): void {
		$this->configure_turnstile();
		$this->script_response(
			array(
				'code' => 503,
				'body' => '{"success":true}',
			)
		);

		$result = salt_captcha_verify( 'the-token', '203.0.113.9' );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'bad-status', $result['reason'] );
	}

	public function test_verify_fails_closed_on_unparseable_json(): void {
		$this->configure_turnstile();
		$this->script_response(
			array(
				'code' => 200,
				'body' => '<html>gateway error</html>',
			)
		);

		$result = salt_captcha_verify( 'the-token', '203.0.113.9' );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'bad-json', $result['reason'] );
	}

	public function test_verify_fails_when_provider_rejects(): void {
		$this->configure_turnstile();
		$this->script_response(
			array(
				'code' => 200,
				'body' => '{"success":false,"error-codes":["invalid-input-response"]}',
			)
		);

		$result = salt_captcha_verify( 'the-token', '203.0.113.9' );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'not-verified', $result['reason'] );
	}

	public function test_verify_rejects_success_as_truthy_string(): void {
		$this->configure_turnstile();
		$this->script_response(
			array(
				'code' => 200,
				'body' => '{"success":"true"}',
			)
		);

		$this->assertFalse( salt_captcha_verify( 'the-token', '203.0.113.9' )['ok'], 'success must be strictly boolean true.' );
	}

	public function test_verify_fails_on_hostname_mismatch(): void {
		$this->configure_turnstile();
		$this->script_response(
			array(
				'code' => 200,
				'body' => '{"success":true,"hostname":"evil.example"}',
			)
		);

		$result = salt_captcha_verify( 'the-token', '203.0.113.9' );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'hostname-mismatch', $result['reason'] );
		$this->assertNotEmpty( $this->logs() );
	}

	public function test_verify_ok_when_response_carries_no_hostname(): void {
		$this->configure_turnstile();
		$this->script_response(
			array(
				'code' => 200,
				'body' => '{"success":true}',
			)
		);

		$this->assertTrue( salt_captcha_verify( 'the-token', '203.0.113.9' )['ok'] );
	}

	// -----------------------------------------------------------------------
	// Widget markup.
	// -----------------------------------------------------------------------

	public function test_widget_html_renders_turnstile_container(): void {
		$this->configure_turnstile( 'ts-site-key' );

		$html = salt_captcha_widget_html();

		$this->assertSame( '<div class="cf-turnstile" data-sitekey="ts-site-key"></div>', $html );
	}

	public function test_widget_html_escapes_the_site_key(): void {
		$this->configure_turnstile( '"><script>alert(1)</script>' );

		$html = salt_captcha_widget_html();

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( 'data-sitekey="&quot;&gt;&lt;script&gt;', $html );
	}

	// -----------------------------------------------------------------------
	// Conditional enqueue (PRIV02 — only when active AND the page has the
	// contact section).
	// -----------------------------------------------------------------------

	public function test_enqueue_loads_provider_script_on_contact_pages(): void {
		$this->configure_turnstile();
		$GLOBALS['salt_test_fields']['current']['sections'] = array(
			array( 'acf_fc_layout' => 'contact' ),
		);

		salt_captcha_enqueue();

		$script = $GLOBALS['salt_test_enqueued']['scripts']['salt-captcha'] ?? null;
		$this->assertNotNull( $script );
		$this->assertSame( 'https://challenges.cloudflare.com/turnstile/v0/api.js', $script['src'] );
		$this->assertNull( $script['ver'] );
		$this->assertSame( 'defer', $script['args']['strategy'] );
	}

	public function test_enqueue_skips_pages_without_the_contact_section(): void {
		$this->configure_turnstile();
		$GLOBALS['salt_test_fields']['current']['sections'] = array(
			array( 'acf_fc_layout' => 'hero' ),
		);

		salt_captcha_enqueue();

		$this->assertArrayNotHasKey( 'salt-captcha', $GLOBALS['salt_test_enqueued']['scripts'] );
	}

	public function test_enqueue_skips_when_no_provider_is_active(): void {
		$GLOBALS['salt_test_fields']['current']['sections'] = array(
			array( 'acf_fc_layout' => 'contact' ),
		);

		salt_captcha_enqueue();

		$this->assertArrayNotHasKey( 'salt-captcha', $GLOBALS['salt_test_enqueued']['scripts'] );
	}

	// -----------------------------------------------------------------------
	// CSP extension — only the active provider's origins, only when active.
	// -----------------------------------------------------------------------

	public function test_csp_is_unchanged_when_no_provider_is_active(): void {
		$csp = "default-src 'self'; script-src 'self'";

		$this->assertSame( $csp, salt_captcha_csp_policy( $csp ) );
	}

	public function test_csp_gains_turnstile_origins_when_active(): void {
		$this->configure_turnstile();
		add_filter( 'salt_csp_policy', 'salt_captcha_csp_policy' );

		$csp = salt_security_headers()['Content-Security-Policy'];

		$this->assertMatchesRegularExpression( '#script-src [^;]*https://challenges\.cloudflare\.com#', $csp );
		$this->assertMatchesRegularExpression( '#frame-src [^;]*https://challenges\.cloudflare\.com#', $csp );
		$this->assertStringNotContainsString( 'google', $csp, 'Only the ACTIVE provider\'s origins are appended.' );
		$this->assertStringContainsString( "default-src 'self'", $csp, 'The rest of the policy is untouched.' );
	}

	// -----------------------------------------------------------------------
	// reCAPTCHA variant.
	// -----------------------------------------------------------------------

	public function test_recaptcha_verify_uses_the_google_endpoint(): void {
		$this->configure_recaptcha();
		$this->script_response(
			array(
				'code' => 200,
				'body' => '{"success":true,"hostname":"example.test"}',
			)
		);

		$result = salt_captcha_verify( 'g-token', '203.0.113.9' );

		$this->assertTrue( $result['ok'] );
		$request = $GLOBALS['salt_test_http']['requests'][0];
		$this->assertSame( 'https://www.google.com/recaptcha/api/siteverify', $request['url'] );
		$this->assertSame( SALT_RECAPTCHA_SECRET, $request['args']['body']['secret'] );
	}

	public function test_recaptcha_widget_enqueue_and_csp(): void {
		$this->configure_recaptcha();
		$GLOBALS['salt_test_fields']['current']['sections'] = array(
			array( 'acf_fc_layout' => 'contact' ),
		);

		$this->assertSame( '<div class="g-recaptcha" data-sitekey="re-site-key"></div>', salt_captcha_widget_html() );

		salt_captcha_enqueue();
		$this->assertSame(
			'https://www.google.com/recaptcha/api.js',
			$GLOBALS['salt_test_enqueued']['scripts']['salt-captcha']['src']
		);

		$csp = salt_captcha_csp_policy( "script-src 'self'; frame-ancestors 'self'" );
		$this->assertMatchesRegularExpression( '#script-src [^;]*https://www\.google\.com https://www\.gstatic\.com#', $csp );
		$this->assertMatchesRegularExpression( '#frame-src [^;]*https://www\.google\.com https://www\.gstatic\.com#', $csp );
		$this->assertStringNotContainsString( 'cloudflare', $csp );
	}

	// -----------------------------------------------------------------------
	// SEC01 — no-JS arithmetic challenge.
	// -----------------------------------------------------------------------

	public function test_challenge_round_trip(): void {
		$challenge = salt_challenge_create();

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $challenge['token'] );
		$this->assertMatchesRegularExpression( '/What is [1-9] plus [1-9]\?/', $challenge['question'] );

		$stored = $GLOBALS['salt_test_transients'][ 'salt_challenge_' . $challenge['token'] ];
		$this->assertGreaterThan( time(), $stored['expires'], 'Challenge answer is stored with a TTL.' );
		$this->assertLessThanOrEqual( time() + 900, $stored['expires'] );

		$this->assertTrue( salt_challenge_verify( $challenge['token'], (string) $stored['value'] ) );
	}

	public function test_challenge_is_single_use(): void {
		$challenge = salt_challenge_create();
		$answer    = (string) $GLOBALS['salt_test_transients'][ 'salt_challenge_' . $challenge['token'] ]['value'];

		$this->assertTrue( salt_challenge_verify( $challenge['token'], $answer ) );
		$this->assertFalse( salt_challenge_verify( $challenge['token'], $answer ), 'The token is burned on first read.' );
	}

	public function test_challenge_wrong_answer_fails_and_burns_the_token(): void {
		$challenge = salt_challenge_create();
		$answer    = (string) $GLOBALS['salt_test_transients'][ 'salt_challenge_' . $challenge['token'] ]['value'];

		$this->assertFalse( salt_challenge_verify( $challenge['token'], '999' ) );
		$this->assertFalse( salt_challenge_verify( $challenge['token'], $answer ), 'A wrong attempt also consumes the token.' );
	}

	public function test_challenge_rejects_non_numeric_answers(): void {
		$challenge = salt_challenge_create();

		$this->assertFalse( salt_challenge_verify( $challenge['token'], 'seven' ) );
	}

	public function test_challenge_fails_on_expired_token(): void {
		$challenge = salt_challenge_create();
		$key       = 'salt_challenge_' . $challenge['token'];
		$answer    = (string) $GLOBALS['salt_test_transients'][ $key ]['value'];

		$GLOBALS['salt_test_transients'][ $key ]['expires'] = time() - 1;

		$this->assertFalse( salt_challenge_verify( $challenge['token'], $answer ) );
	}

	public function test_challenge_fails_on_unknown_or_malformed_token(): void {
		$this->assertFalse( salt_challenge_verify( str_repeat( 'a', 32 ), '5' ) );
		$this->assertFalse( salt_challenge_verify( 'not-a-token', '5' ) );
		$this->assertFalse( salt_challenge_verify( '', '5' ) );
	}

	// -----------------------------------------------------------------------
	// Options registration (DATA02; PRIV02 — Turnstile first + recommended).
	// -----------------------------------------------------------------------

	public function test_options_group_registers_with_canonical_keys(): void {
		salt_register_captcha_options_fields();

		$this->assertCount( 1, $GLOBALS['salt_test_acf_groups'] );
		$group = $GLOBALS['salt_test_acf_groups'][0];

		$this->assertSame( 'group_salt_options_captcha', $group['key'] );
		$this->assertSame( 'salt-settings', $group['location'][0][0]['value'] );
		$this->assertSame( array(), $this->logs(), 'No DATA02 key-convention drift.' );

		$provider = null;
		foreach ( $group['fields'] as $field ) {
			if ( 'captcha_provider' === ( $field['name'] ?? '' ) ) {
				$provider = $field;
			}
		}

		$this->assertNotNull( $provider );
		$this->assertSame( 'field_salt_options_captcha_provider', $provider['key'] );
		$this->assertSame( '', $provider['default_value'], 'No provider by default.' );
		$this->assertSame( array( '', 'turnstile', 'recaptcha' ), array_keys( $provider['choices'] ), 'Turnstile is listed FIRST (PRIV02).' );
		$this->assertStringContainsString( 'recommended', strtolower( $provider['choices']['turnstile'] ) );
	}
}
