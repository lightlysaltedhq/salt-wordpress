<?php
/**
 * Maps (MapLibre facade) + booking facade — components, option validation,
 * CSP additions and enqueue gating (spec §9; PRIV01/§7).
 *
 * The modules under test are not in the foundation bootstrap yet, so this
 * file requires them directly (each is ABSPATH-guarded and idempotent).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/acf.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/maps.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/booking.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/options.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/options-integrations.php';

final class MapsBookingTest extends SaltTestCase {

	private const BASE_CSP = "default-src 'self'; script-src 'self' 'nonce-abc'; style-src 'self'; img-src 'self' data:; connect-src 'self'";

	protected function setUp(): void {
		parent::setUp();
		salt_asset_manifest_reset();
		$GLOBALS['salt_test_acf_groups']  = array();
		$GLOBALS['salt_test_is_singular'] = true;
	}

	protected function tearDown(): void {
		salt_asset_manifest_reset();
		parent::tearDown();
	}

	/**
	 * Seed the maps options.
	 *
	 * @param bool        $enabled Whether maps are on.
	 * @param string|null $tile    Tile URL option (null = unset).
	 * @return void
	 */
	private function configure_maps( bool $enabled = true, ?string $tile = null ): void {
		$GLOBALS['salt_test_fields']['option']['maps_enabled'] = $enabled;
		if ( null !== $tile ) {
			$GLOBALS['salt_test_fields']['option']['maps_tile_url'] = $tile;
		}
	}

	/**
	 * Seed the booking options.
	 *
	 * @param string $provider Provider slug.
	 * @param string $url      Booking URL.
	 * @return void
	 */
	private function configure_booking( string $provider, string $url ): void {
		$GLOBALS['salt_test_fields']['option']['booking_provider'] = $provider;
		$GLOBALS['salt_test_fields']['option']['booking_url']      = $url;
	}

	/**
	 * Render the map facade with valid default coordinates.
	 *
	 * @param array<string,mixed> $overrides Arg overrides.
	 * @return string
	 */
	private function render_map( array $overrides = array() ): string {
		return salt_get_component(
			'map',
			array_merge(
				array(
					'lat' => 51.5074,
					'lng' => -0.1278,
				),
				$overrides
			)
		);
	}

	/**
	 * Assert markup contains no third-party URL in a LOADING attribute
	 * (src/href/srcset/action) — data-* carriers are inert and allowed.
	 *
	 * @param string $html Facade markup.
	 * @return void
	 */
	private function assert_no_third_party_load( string $html ): void {
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<iframe', $html );
		$this->assertStringNotContainsString( '<link', $html );
		preg_match_all( '/\b(?:src|href|srcset|action)="([^"]*)"/i', $html, $matches );
		foreach ( $matches[1] as $url ) {
			$this->assertStringNotContainsString( 'openstreetmap.org', $url );
			$this->assertStringNotContainsString( 'calendly.com', $url );
			$this->assertStringNotContainsString( 'cal.com', $url );
		}
	}

	// -----------------------------------------------------------------
	// x-map facade.
	// -----------------------------------------------------------------

	public function test_map_facade_renders_placeholder_with_inert_data_attrs(): void {
		$this->configure_maps();

		$html = $this->render_map( array( 'label' => 'Salt HQ' ) );

		$this->assertStringStartsWith( '<div class="salt-map" ', $html );
		$this->assertStringContainsString( 'data-lat="51.5074"', $html );
		$this->assertStringContainsString( 'data-lng="-0.1278"', $html );
		$this->assertStringContainsString( 'data-zoom="14"', $html );
		$this->assertStringContainsString( 'data-tile="https://tile.openstreetmap.org/{z}/{x}/{y}.png"', $html );
		$this->assertStringContainsString( '<button class="salt-button salt-map__load" type="button" data-style="primary" aria-label="Load the map">Load map</button>', $html );
		$this->assertStringContainsString( '<p class="salt-map__label">Salt HQ</p>', $html );
		// The bundle's address is the script's (window.saltMaps), never the markup's (SC-006).
		$this->assertStringNotContainsString( 'maplibre', $html );
	}

	public function test_map_facade_loads_nothing_third_party_at_render(): void {
		$this->configure_maps();

		$this->assert_no_third_party_load( $this->render_map() );
	}

	public function test_map_data_attrs_are_escaped(): void {
		$this->configure_maps( true, 'https://tiles.example.com/{z}/{x}/{y}.png?key=a"b<c>' );

		$html = $this->render_map( array( 'label' => 'A "quoted" <label>' ) );

		$this->assertStringNotContainsString( '"b<c>', $html );
		$this->assertStringContainsString( 'key=a&quot;b&lt;c&gt;', $html );
		$this->assertStringContainsString( 'A &quot;quoted&quot; &lt;label&gt;', $html );
	}

	public function test_map_renders_nothing_and_logs_when_disabled(): void {
		$this->configure_maps( false );

		$this->assertSame( '', $this->render_map() );
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'disabled', implode( ' ', $this->logs() ) );
	}

	public function test_map_missing_required_lat_throws_under_debug(): void {
		$this->configure_maps();

		$this->assert_component_throws( array( 'lng' => -0.1278 ), 'required arg &quot;lat&quot; missing' );
	}

	public function test_map_wrong_arg_type_throws_under_debug(): void {
		$this->configure_maps();

		$this->assert_component_throws(
			array(
				'lat' => '51.5',
				'lng' => -0.1278,
			),
			'arg &quot;lat&quot; must be float'
		);
	}

	/**
	 * Assert the x-map contract throws (cleaning the render buffer the
	 * aborted salt_get_component() leaves open).
	 *
	 * @param array<string,mixed> $args     Component args.
	 * @param string              $expected Expected message fragment.
	 * @return void
	 */
	private function assert_component_throws( array $args, string $expected ): void {
		$level = ob_get_level();
		try {
			salt_get_component( 'map', $args );
			$this->fail( 'Expected InvalidArgumentException was not thrown.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( $expected, $e->getMessage() );
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
		}
	}

	public function test_map_out_of_range_coordinates_render_nothing_and_log(): void {
		$this->configure_maps();

		$this->assertSame( '', $this->render_map( array( 'lat' => 123.0 ) ) );
		$this->assertStringContainsString( 'out of range', implode( ' ', $this->logs() ) );
	}

	public function test_map_zoom_is_clamped_and_logged(): void {
		$this->configure_maps();

		$html = $this->render_map( array( 'zoom' => 40 ) );

		$this->assertStringContainsString( 'data-zoom="20"', $html, 'The contract\'s zoom is 1 to 20.' );
		$this->assertStringContainsString( 'data-zoom="1"', $this->render_map( array( 'zoom' => 0 ) ) );
		$this->assertStringContainsString( 'clamped', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Tile URL validation (fail loud, degrade to the OSM default).
	// -----------------------------------------------------------------

	/**
	 * Invalid tile templates.
	 *
	 * @return array<string,array{string}>
	 */
	public static function invalid_tile_urls(): array {
		return array(
			'http (not https)'     => array( 'http://tiles.example.com/{z}/{x}/{y}.png' ),
			'javascript scheme'    => array( 'javascript:alert(1)' ),
			'missing placeholders' => array( 'https://tiles.example.com/static.png' ),
			'no host'              => array( 'https:///{z}/{x}/{y}.png' ),
		);
	}

	/**
	 * Every invalid template degrades to the default + a log line.
	 *
	 * @param string $url Invalid tile URL option.
	 */
	#[DataProvider( 'invalid_tile_urls' )]
	public function test_invalid_tile_url_falls_back_to_default_and_logs( string $url ): void {
		$this->configure_maps( true, $url );

		$this->assertSame( salt_maps_default_tile_url(), salt_maps_tile_url() );
		$this->assertStringContainsString( 'tile URL', implode( ' ', $this->logs() ) );
	}

	public function test_valid_custom_tile_url_is_used(): void {
		$this->configure_maps( true, 'https://tiles.example.com:8443/{z}/{x}/{y}.png' );

		$this->assertSame( 'https://tiles.example.com:8443/{z}/{x}/{y}.png', salt_maps_tile_url() );
		$this->assertSame( array(), $this->logs() );
	}

	// -----------------------------------------------------------------
	// Maps CSP.
	// -----------------------------------------------------------------

	public function test_maps_csp_untouched_when_disabled(): void {
		$this->configure_maps( false );

		$this->assertSame( self::BASE_CSP, salt_maps_csp_policy( self::BASE_CSP ) );
	}

	public function test_maps_csp_adds_tile_origin_to_img_and_connect_only(): void {
		$this->configure_maps();

		$csp = salt_maps_csp_policy( self::BASE_CSP );

		$this->assertStringContainsString( "img-src 'self' data: https://tile.openstreetmap.org", $csp );
		$this->assertStringContainsString( "connect-src 'self' https://tile.openstreetmap.org", $csp );
		$this->assertStringContainsString( "script-src 'self' 'nonce-abc';", $csp );
		$this->assertSame( 1, substr_count( $csp, 'default-src' ) );
	}

	public function test_maps_csp_uses_the_configured_tile_origin(): void {
		$this->configure_maps( true, 'https://tiles.example.com/{z}/{x}/{y}.png' );

		$csp = salt_maps_csp_policy( self::BASE_CSP );

		$this->assertStringContainsString( 'img-src \'self\' data: https://tiles.example.com', $csp );
		$this->assertStringNotContainsString( 'openstreetmap', $csp );
	}

	// -----------------------------------------------------------------
	// Maps enqueue gating.
	// -----------------------------------------------------------------

	/**
	 * Seed the page's Flexible Content pre-scan rows.
	 *
	 * @param string[] $layouts Layout slugs (underscored, as stored).
	 * @return void
	 */
	private function seed_sections( array $layouts ): void {
		$GLOBALS['salt_test_fields']['current']['sections'] = array_map(
			static fn( string $layout ): array => array( 'acf_fc_layout' => $layout ),
			$layouts
		);
	}

	public function test_maps_script_not_enqueued_when_disabled(): void {
		$this->configure_maps( false );
		$this->seed_sections( array( 'contact' ) );

		salt_maps_enqueue();

		$this->assertArrayNotHasKey( 'salt-maps', $GLOBALS['salt_test_enqueued']['scripts'] );
	}

	public function test_maps_script_not_enqueued_without_a_map_capable_section(): void {
		$this->configure_maps();
		$this->seed_sections( array( 'hero', 'faq' ) );

		salt_maps_enqueue();

		$this->assertArrayNotHasKey( 'salt-maps', $GLOBALS['salt_test_enqueued']['scripts'] );
	}

	public function test_maps_script_enqueued_on_contact_and_locations_pages(): void {
		$this->configure_maps();

		foreach ( array( 'contact', 'locations' ) as $slug ) {
			$GLOBALS['salt_test_enqueued']['scripts'] = array();
			$this->seed_sections( array( 'hero', $slug ) );

			salt_maps_enqueue();

			$this->assertArrayHasKey( 'salt-maps', $GLOBALS['salt_test_enqueued']['scripts'], $slug );
			$this->assertStringContainsString( '/assets/dist/maps.', $GLOBALS['salt_test_enqueued']['scripts']['salt-maps']['src'] );
		}
	}

	// -----------------------------------------------------------------
	// x-booking facade.
	// -----------------------------------------------------------------

	public function test_booking_unconfigured_renders_nothing_and_logs(): void {
		$this->assertSame( '', salt_get_component( 'booking' ) );
		$this->assertStringContainsString( 'x-booking', implode( ' ', $this->logs() ) );
	}

	public function test_booking_facade_is_a_plain_provider_link(): void {
		$this->configure_booking( 'calendly', 'https://calendly.com/acme/intro' );

		$html = salt_get_component( 'booking' );

		$this->assertStringContainsString( 'href="https://calendly.com/acme/intro"', $html );
		$this->assertStringContainsString( 'target="_blank"', $html );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $html );
		$this->assertStringContainsString( 'data-salt-booking', $html );
		$this->assertStringContainsString( 'data-provider="calendly"', $html );
		$this->assertStringContainsString( '>Book a call</a>', $html );
		// The href is the ONE allowed third-party URL; nothing loads.
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<iframe', $html );
		$this->assertStringNotContainsString( 'assets.calendly.com', $html );
	}

	public function test_booking_custom_label_and_classes(): void {
		$this->configure_booking( 'calcom', 'https://cal.com/acme/intro' );

		$html = salt_get_component(
			'booking',
			array(
				'label'   => 'Grab a slot <now>',
				'classes' => 'w-full',
			)
		);

		$this->assertStringContainsString( '>Grab a slot &lt;now&gt;</a>', $html );
		$this->assertStringContainsString( 'w-full', $html );
		$this->assertStringContainsString( 'data-provider="calcom"', $html );
	}

	/**
	 * Misconfigurations that must fail closed.
	 *
	 * @return array<string,array{string,string}>
	 */
	public static function invalid_booking_configs(): array {
		return array(
			'unknown provider'       => array( 'hubspot', 'https://meetings.hubspot.com/acme' ),
			'provider without url'   => array( 'calendly', '' ),
			'http url'               => array( 'calendly', 'http://calendly.com/acme' ),
			'host/provider mismatch' => array( 'calendly', 'https://evil.example.com/acme' ),
			'lookalike host'         => array( 'calcom', 'https://cal.com.evil.example.com/acme' ),
		);
	}

	/**
	 * Every misconfiguration must yield null + '' render + a log line.
	 *
	 * @param string $provider Provider option.
	 * @param string $url      URL option.
	 */
	#[DataProvider( 'invalid_booking_configs' )]
	public function test_misconfigured_booking_fails_closed( string $provider, string $url ): void {
		$this->configure_booking( $provider, $url );

		$this->assertNull( salt_booking_config() );
		$this->assertSame( '', salt_get_component( 'booking' ) );
		$this->assertNotEmpty( $this->logs() );
	}

	public function test_booking_subdomain_of_provider_host_is_allowed(): void {
		$this->configure_booking( 'calcom', 'https://app.cal.com/acme/intro' );

		$config = salt_booking_config();

		$this->assertNotNull( $config );
		$this->assertSame( 'calcom', $config['provider'] );
	}

	// -----------------------------------------------------------------
	// Booking CSP.
	// -----------------------------------------------------------------

	public function test_booking_csp_untouched_when_unconfigured(): void {
		$this->assertSame( self::BASE_CSP, salt_booking_csp_policy( self::BASE_CSP ) );
	}

	public function test_booking_csp_adds_calendly_origins(): void {
		$this->configure_booking( 'calendly', 'https://calendly.com/acme/intro' );

		$csp = salt_booking_csp_policy( self::BASE_CSP );

		$this->assertStringContainsString( "script-src 'self' 'nonce-abc' https://assets.calendly.com https://calendly.com", $csp );
		$this->assertStringContainsString( 'frame-src https://assets.calendly.com https://calendly.com', $csp );
		$this->assertStringContainsString( "connect-src 'self' https://assets.calendly.com https://calendly.com", $csp );
		$this->assertStringNotContainsString( 'cal.com', str_replace( 'calendly.com', '', $csp ) );
	}

	public function test_booking_csp_adds_calcom_origins_only(): void {
		$this->configure_booking( 'calcom', 'https://cal.com/acme/intro' );

		$csp = salt_booking_csp_policy( self::BASE_CSP );

		$this->assertStringContainsString( "script-src 'self' 'nonce-abc' https://app.cal.com https://cal.com", $csp );
		$this->assertStringContainsString( 'frame-src https://app.cal.com https://cal.com', $csp );
		$this->assertStringNotContainsString( 'calendly', $csp );
	}

	// -----------------------------------------------------------------
	// Booking enqueue gating.
	// -----------------------------------------------------------------

	public function test_booking_script_not_enqueued_when_unconfigured(): void {
		salt_booking_enqueue();

		$this->assertArrayNotHasKey( 'salt-booking', $GLOBALS['salt_test_enqueued']['scripts'] );
	}

	public function test_booking_script_enqueued_when_configured(): void {
		$this->configure_booking( 'calendly', 'https://calendly.com/acme/intro' );

		salt_booking_enqueue();

		$this->assertArrayHasKey( 'salt-booking', $GLOBALS['salt_test_enqueued']['scripts'] );
		$this->assertStringContainsString( '/assets/dist/booking.', $GLOBALS['salt_test_enqueued']['scripts']['salt-booking']['src'] );
	}

	// -----------------------------------------------------------------
	// Option field groups (DATA02 key conventions).
	// -----------------------------------------------------------------

	public function test_option_field_groups_register_with_convention_keys(): void {
		salt_register_maps_options_fields();
		salt_register_booking_options_fields();

		$keys = array_column( $GLOBALS['salt_test_acf_groups'], 'key' );
		$this->assertContains( 'group_salt_options_maps', $keys );
		$this->assertContains( 'group_salt_options_booking', $keys );
		// salt_register_field_group() logs any DATA02 key drift under debug.
		$this->assertSame( array(), $this->logs() );

		foreach ( $GLOBALS['salt_test_acf_groups'] as $group ) {
			if ( ! in_array( $group['key'], array( 'group_salt_options_maps', 'group_salt_options_booking' ), true ) ) {
				continue;
			}
			foreach ( $group['fields'] as $field ) {
				$this->assertStringStartsWith( 'field_salt_options_', $field['key'] );
			}
		}
	}
}
