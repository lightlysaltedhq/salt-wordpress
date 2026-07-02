<?php
/**
 * Tests for inc/services/analytics.php + inc/fields/options-analytics.php —
 * PostHog + GA4 (Consent Mode pattern, everything OFF by default).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/analytics.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/options-analytics.php';

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Analytics module test case.
 */
final class AnalyticsTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_acf_groups'] = array();
		salt_asset_manifest_reset();
	}

	/**
	 * Seed the analytics option fields.
	 *
	 * @param array<string,mixed> $fields Field name => value.
	 * @return void
	 */
	private function set_options( array $fields ): void {
		$GLOBALS['salt_test_fields']['option'] = $fields;
	}

	/**
	 * Real committed manifest, decoded.
	 *
	 * @return array<string,string>
	 */
	private function real_manifest(): array {
		$raw = file_get_contents( dirname( __DIR__, 2 ) . '/assets/dist/manifest.json' );
		$this->assertIsString( $raw );
		$manifest = json_decode( $raw, true );
		$this->assertIsArray( $manifest );
		return $manifest;
	}

	/**
	 * A representative base policy (mirrors salt_security_headers() shape).
	 *
	 * @return string
	 */
	private function base_csp(): string {
		return "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'";
	}

	// -----------------------------------------------------------------------
	// OFF by default: zero output, zero enqueues, zero CSP changes.
	// -----------------------------------------------------------------------

	public function test_everything_off_by_default_no_output_enqueues_or_csp_changes(): void {
		$this->set_options( array() );

		$this->assertSame(
			array(
				'posthog' => false,
				'ga4'     => false,
			),
			salt_analytics_active()
		);

		ob_start();
		salt_output_ga4_snippet();
		salt_output_posthog_config();
		$this->assertSame( '', ob_get_clean(), 'Disabled integrations must emit nothing.' );

		salt_analytics_enqueue();
		$this->assertSame( array(), $GLOBALS['salt_test_enqueued']['scripts'], 'Disabled integrations must enqueue nothing.' );

		$csp = $this->base_csp();
		$this->assertSame( $csp, salt_analytics_ga4_csp( $csp ), 'Inactive GA4 must not touch the CSP.' );
		$this->assertSame( $csp, salt_analytics_posthog_csp( $csp ), 'Inactive PostHog must not touch the CSP.' );

		$this->assertSame( array(), $this->logs(), 'An unconfigured site is not a misconfiguration — no log noise.' );
	}

	// -----------------------------------------------------------------------
	// GA4 snippet (Consent Mode v2).
	// -----------------------------------------------------------------------

	public function test_ga4_snippet_consent_defaults_denied_before_loader_and_nonced(): void {
		$this->set_options(
			array(
				'ga4_enabled' => 1,
				'ga4_id'      => 'G-ABC123',
			)
		);

		ob_start();
		salt_output_ga4_snippet();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'nonce="' . salt_csp_nonce() . '"', $html );

		$default = "gtag('consent','default',{ad_storage:'denied',analytics_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',wait_for_update:500});";
		$this->assertStringContainsString( $default, $html );

		$loader_pos = strpos( $html, 'https://www.googletagmanager.com/gtag/js?id=G-ABC123' ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Assertion string, not markup.
		$this->assertIsInt( $loader_pos, 'The async gtag.js loader must be present.' );
		$this->assertLessThan(
			$loader_pos,
			strpos( $html, $default ),
			'Consent defaults must be DENIED before the tag loads (Consent Mode v2 Advanced).'
		);

		$this->assertStringContainsString( '<script async src=', $html ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Assertion string, not markup.
		$this->assertStringContainsString( "gtag('js',new Date());", $html );
		$this->assertStringContainsString( "gtag('config',\"G-ABC123\");", $html );
		$this->assertSame( array(), $this->logs() );
	}

	/**
	 * Invalid GA4 measurement IDs.
	 *
	 * @return array<string,array{string}>
	 */
	public static function invalid_ga4_ids(): array {
		return array(
			'empty'            => array( '' ),
			'UA property'      => array( 'UA-12345-1' ),
			'lowercase'        => array( 'G-abc123' ),
			'bare prefix'      => array( 'G-' ),
			'embedded space'   => array( 'G-ABC 123' ),
			'script injection' => array( 'G-ABC123"></script><script>alert(1)</script>' ),
		);
	}

	/**
	 * @param string $ga4_id Invalid measurement ID.
	 */
	#[DataProvider( 'invalid_ga4_ids' )]
	public function test_invalid_ga4_id_skips_output_and_logs( string $ga4_id ): void {
		$this->set_options(
			array(
				'ga4_enabled' => 1,
				'ga4_id'      => $ga4_id,
			)
		);

		ob_start();
		salt_output_ga4_snippet();
		$this->assertSame( '', ob_get_clean(), 'Invalid ID must produce no output at all.' );

		$this->assertFalse( salt_analytics_active()['ga4'] );
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'invalid', $this->logs()[0] );
	}

	// -----------------------------------------------------------------------
	// PostHog config JSON data block.
	// -----------------------------------------------------------------------

	public function test_posthog_config_json_is_unnonced_data_block_with_resolved_host(): void {
		$this->set_options(
			array(
				'posthog_enabled' => 1,
				'posthog_key'     => 'phc_testkey123',
				'posthog_host'    => 'eu',
			)
		);

		ob_start();
		salt_output_posthog_config();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<script type="application/json" id="salt-posthog-config">', $html );
		$this->assertStringNotContainsString( 'nonce=', $html, 'Non-executable data blocks are deliberately un-nonced.' );

		$this->assertSame( 1, preg_match( '#<script[^>]*>(.*)</script>#s', $html, $m ) );
		$this->assertSame(
			array(
				'key'       => 'phc_testkey123',
				'host'      => 'https://eu.i.posthog.com',
				'recording' => false,
			),
			json_decode( $m[1], true )
		);
		$this->assertSame( array(), $this->logs() );
	}

	public function test_posthog_session_recording_toggle_reaches_the_config(): void {
		$this->set_options(
			array(
				'posthog_enabled'           => 1,
				'posthog_key'               => 'phc_x',
				'posthog_host'              => 'us',
				'posthog_session_recording' => 1,
			)
		);

		$config = salt_analytics_posthog_config();
		$this->assertIsArray( $config );
		$this->assertTrue( $config['recording'] );
	}

	/**
	 * Host resolution matrix.
	 *
	 * @return array<string,array{array<string,mixed>,?string}>
	 */
	public static function posthog_host_matrix(): array {
		return array(
			'default (unset) is US' => array( array(), 'https://us.i.posthog.com' ),
			'us'                    => array( array( 'posthog_host' => 'us' ), 'https://us.i.posthog.com' ),
			'eu'                    => array( array( 'posthog_host' => 'eu' ), 'https://eu.i.posthog.com' ),
			'custom reverse proxy'  => array(
				array(
					'posthog_host'        => 'custom',
					'posthog_host_custom' => 'https://ph.example.com/',
				),
				'https://ph.example.com',
			),
			'custom missing url'    => array( array( 'posthog_host' => 'custom' ), null ),
			'custom schemeless url' => array(
				array(
					'posthog_host'        => 'custom',
					'posthog_host_custom' => 'ph.example.com',
				),
				null,
			),
			'unknown choice'        => array( array( 'posthog_host' => 'apac' ), null ),
		);
	}

	/**
	 * @param array<string,mixed> $host_fields Host option fields.
	 * @param string|null         $expected    Resolved api_host, or null (disabled).
	 */
	#[DataProvider( 'posthog_host_matrix' )]
	public function test_posthog_host_resolution( array $host_fields, ?string $expected ): void {
		$this->set_options(
			array_merge(
				array(
					'posthog_enabled' => 1,
					'posthog_key'     => 'phc_x',
				),
				$host_fields
			)
		);

		$config = salt_analytics_posthog_config();

		if ( null === $expected ) {
			$this->assertNull( $config, 'Unresolvable host must disable the integration (fail-closed).' );
			$this->assertFalse( salt_analytics_active()['posthog'] );
			$this->assertNotEmpty( $this->logs(), 'Misconfiguration must log (fail loud).' );

			ob_start();
			salt_output_posthog_config();
			salt_analytics_enqueue();
			$this->assertSame( '', ob_get_clean() );
			$this->assertSame( array(), $GLOBALS['salt_test_enqueued']['scripts'] );
			return;
		}

		$this->assertIsArray( $config );
		$this->assertSame( $expected, $config['host'] );
	}

	public function test_posthog_enabled_without_key_is_disabled_and_logged(): void {
		$this->set_options( array( 'posthog_enabled' => 1 ) );

		$this->assertNull( salt_analytics_posthog_config() );
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'key', $this->logs()[0] );
	}

	// -----------------------------------------------------------------------
	// CSP extensions — exact additions per provider.
	// -----------------------------------------------------------------------

	public function test_ga4_csp_appends_exactly_the_google_origins(): void {
		$this->set_options(
			array(
				'ga4_enabled' => 1,
				'ga4_id'      => 'G-ABC123',
			)
		);

		$this->assertSame(
			"default-src 'self'; "
			. "script-src 'self' https://www.googletagmanager.com; "
			. "style-src 'self'; "
			. "img-src 'self' data: https://*.google-analytics.com; "
			. "connect-src 'self' https://*.google-analytics.com https://www.googletagmanager.com; "
			. "object-src 'none'",
			salt_analytics_ga4_csp( $this->base_csp() )
		);
	}

	public function test_posthog_csp_appends_only_the_configured_ingestion_origin(): void {
		$this->set_options(
			array(
				'posthog_enabled'     => 1,
				'posthog_key'         => 'phc_x',
				'posthog_host'        => 'custom',
				'posthog_host_custom' => 'https://ph.example.com:8443/ingest',
			)
		);

		$this->assertSame(
			"default-src 'self'; "
			. "script-src 'self'; "
			. "style-src 'self'; "
			. "img-src 'self' data:; "
			. "connect-src 'self' https://ph.example.com:8443; "
			. "object-src 'none'",
			salt_analytics_posthog_csp( $this->base_csp() ),
			'PostHog is first-party bundled: connect-src gains the ingestion origin ONLY (no script-src change).'
		);
	}

	public function test_csp_callbacks_compose_with_the_real_policy_via_the_filter(): void {
		$this->set_options(
			array(
				'ga4_enabled'     => 1,
				'ga4_id'          => 'G-XYZ9',
				'posthog_enabled' => 1,
				'posthog_key'     => 'phc_x',
				'posthog_host'    => 'eu',
			)
		);

		add_filter( 'salt_csp_policy', 'salt_analytics_ga4_csp' );
		add_filter( 'salt_csp_policy', 'salt_analytics_posthog_csp' );

		$csp = salt_security_headers()['Content-Security-Policy'];

		$this->assertStringContainsString( "script-src 'self' 'nonce-" . salt_csp_nonce() . "' https://www.googletagmanager.com", $csp );
		$this->assertStringContainsString( 'https://eu.i.posthog.com', $csp );
		$this->assertStringContainsString( 'https://*.google-analytics.com', $csp );
	}

	// -----------------------------------------------------------------------
	// Enqueues — hashed manifest entries, $ver null.
	// -----------------------------------------------------------------------

	public function test_enqueues_hashed_manifest_entries_with_null_ver_when_both_active(): void {
		$this->set_options(
			array(
				'posthog_enabled' => 1,
				'posthog_key'     => 'phc_x',
				'posthog_host'    => 'us',
				'ga4_enabled'     => 1,
				'ga4_id'          => 'G-ABC123',
			)
		);

		salt_analytics_enqueue();

		$manifest = $this->real_manifest();
		$scripts  = $GLOBALS['salt_test_enqueued']['scripts'];

		foreach ( array(
			'salt-analytics-posthog' => 'analytics/posthog.js',
			'salt-analytics-consent' => 'analytics/consent.js',
			'salt-analytics-events'  => 'analytics/events.js',
		) as $handle => $logical ) {
			$this->assertArrayHasKey( $handle, $scripts );
			$this->assertArrayHasKey( $logical, $manifest, 'Build the analytics entries (npm run build) before running the suite.' );
			$this->assertStringEndsWith( '/assets/dist/' . $manifest[ $logical ], $scripts[ $handle ]['src'] );
			$this->assertNull( $scripts[ $handle ]['ver'], 'ARCH03: the content hash is the cache-buster — no ?ver=.' );
			$this->assertSame(
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				),
				$scripts[ $handle ]['args']
			);
		}

		$this->assertSame( array( 'salt-analytics-posthog' ), $scripts['salt-analytics-consent']['deps'], 'Consent wiring must run after the PostHog bundle.' );
		$this->assertSame( array(), $this->logs() );
	}

	public function test_ga4_only_skips_the_posthog_bundle_but_ships_consent_and_events(): void {
		$this->set_options(
			array(
				'ga4_enabled' => 1,
				'ga4_id'      => 'G-ABC123',
			)
		);

		salt_analytics_enqueue();
		$scripts = $GLOBALS['salt_test_enqueued']['scripts'];

		$this->assertArrayNotHasKey( 'salt-analytics-posthog', $scripts );
		$this->assertArrayHasKey( 'salt-analytics-consent', $scripts );
		$this->assertArrayHasKey( 'salt-analytics-events', $scripts );
		$this->assertSame( array(), $scripts['salt-analytics-consent']['deps'] );
	}

	// -----------------------------------------------------------------------
	// Options field group (DATA02).
	// -----------------------------------------------------------------------

	public function test_field_group_registers_on_salt_settings_with_convention_keys(): void {
		salt_register_analytics_options_fields();

		$groups = $GLOBALS['salt_test_acf_groups'];
		$this->assertCount( 1, $groups );

		$group = $groups[0];
		$this->assertSame( 'group_salt_options_analytics', $group['key'] );
		$this->assertSame( 'salt-settings', $group['location'][0][0]['value'] );

		$names    = array_column( $group['fields'], 'name' );
		$expected = array( 'posthog_enabled', 'posthog_key', 'posthog_host', 'posthog_host_custom', 'posthog_session_recording', 'ga4_enabled', 'ga4_id' );
		$this->assertSame( $expected, $names );

		foreach ( $group['fields'] as $field ) {
			$this->assertStringStartsWith( 'field_salt_options_analytics_', $field['key'] );
		}

		// Toggles default OFF; the cookieless prerequisite is documented on the field.
		$by_name = array_column( $group['fields'], null, 'name' );
		$this->assertSame( 0, $by_name['posthog_enabled']['default_value'] );
		$this->assertSame( 0, $by_name['posthog_session_recording']['default_value'] );
		$this->assertSame( 0, $by_name['ga4_enabled']['default_value'] );
		$this->assertStringContainsString( 'Cookieless server hash mode', $by_name['posthog_enabled']['instructions'] );

		$this->assertSame( array(), $this->logs(), 'DATA02 key-convention assertions must stay quiet.' );
	}
}
