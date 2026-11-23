<?php
/**
 * Locations section — states (COMPL02), pill mapping, directions link,
 * schema nodes (SEO01) and the pill/openingHoursSpecification cross-check
 * (TEST03).
 *
 * Time seam: the partial calls salt_location_status() with no $now (live
 * time), so rendered-pill tests use fixtures whose status is the same at
 * ANY instant (all-week overnight spans => always open; a day 3 days away
 * => always closed; all-week appointment => always appointment; no
 * hours => unknown). Frozen-clock assertions call salt_location_status()
 * directly with an injected $now.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/location.php';

final class LocationsSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Seed location posts + their ACF fields.
	 *
	 * @param array<int,array<string,mixed>> $offices Office fixtures: title, address, phone, email, hours.
	 * @return void
	 */
	private function seed_offices( array $offices ): void {
		$posts = array();
		foreach ( array_values( $offices ) as $i => $office ) {
			$id      = (int) ( $office['ID'] ?? ( 100 + $i ) );
			$posts[] = array(
				'ID'         => $id,
				'post_type'  => 'location',
				'post_title' => (string) ( $office['title'] ?? 'Office ' . $id ),
				'menu_order' => $i,
			);
			foreach ( array( 'address', 'phone', 'email', 'hours' ) as $key ) {
				if ( array_key_exists( $key, $office ) ) {
					$GLOBALS['salt_test_fields'][ (string) $id ][ $key ] = $office[ $key ];
				}
			}
		}
		$this->seed_posts( $posts );
	}

	/**
	 * Render the section for one seeded FC row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_locations( array $fields = array() ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'locations',
					'fields' => $fields,
				),
			)
		);
		$this->assertTrue( have_rows( 'sections' ) );
		the_row();

		ob_start();
		salt_render_section(
			'locations',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * An opening-hours row.
	 *
	 * @param string $day         Day key.
	 * @param string $open        Open time.
	 * @param string $close       Close time.
	 * @param bool   $appointment Appointment-only flag.
	 * @return array<string,mixed>
	 */
	private static function hours_row( string $day, string $open = '', string $close = '', bool $appointment = false ): array {
		return array(
			'day'         => $day,
			'open'        => $open,
			'close'       => $close,
			'appointment' => $appointment,
		);
	}

	private const WEEK = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );

	/**
	 * Hours that resolve 'open' at any instant (00:00–00:00 overnight spans all week).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function always_open_hours(): array {
		return array_map( static fn( string $day ): array => self::hours_row( $day, '00:00', '00:00' ), self::WEEK );
	}

	/**
	 * Hours that resolve 'closed' at any instant (single row 3 days from now:
	 * never today, never yesterday's overnight).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function always_closed_hours(): array {
		$day = strtolower( ( new DateTimeImmutable( 'now +3 days', new DateTimeZone( 'UTC' ) ) )->format( 'l' ) );
		return array( self::hours_row( $day, '09:00', '17:00' ) );
	}

	/**
	 * Hours that resolve 'appointment' at any instant (all week appointment-only).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function always_appointment_hours(): array {
		return array_map( static fn( string $day ): array => self::hours_row( $day, '', '', true ), self::WEEK );
	}

	// -----------------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------------

	public function test_zero_offices_renders_nothing_and_queues_no_schema(): void {
		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertSame( '', $html );
		$this->assertSame( array(), salt_schema_queue() );
	}

	public function test_disabled_module_renders_nothing(): void {
		$this->seed_offices( array( array( 'title' => 'Bath office' ) ) );
		$GLOBALS['salt_test_fields']['option']['module_location'] = false;

		$this->assertSame( '', $this->render_locations( array( 'heading' => 'Find us' ) ) );
	}

	public function test_single_office_is_a_static_details_with_no_script(): void {
		$this->seed_offices(
			array(
				array(
					'title'   => 'Bath office',
					'address' => array(
						'street'     => '1 High Street',
						'locality'   => 'Bath',
						'postalCode' => 'BA1 1AA',
					),
					'phone'   => '+44 1225 000000',
					'email'   => 'bath@example.test',
					'hours'   => self::always_open_hours(),
				),
			)
		);

		$html = $this->render_locations(
			array(
				'heading' => 'Find us',
				'eyebrow' => 'Offices',
			)
		);

		$this->assertSame( 1, preg_match_all( '/<details[\s>]/', $html ) );
		$this->assertStringContainsString( 'Bath office', $html );
		$this->assertStringNotContainsString( '<script', $html );

		// Primary section heading claims the H1; office names are styled
		// spans inside <summary> (not headings — AT exposure is unreliable).
		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertStringContainsString( '<span class="salt-locations__name">Bath office</span>', $html );

		// Contact surface.
		$this->assertStringContainsString( 'href="tel:+441225000000"', $html );
		$this->assertStringContainsString( 'href="mailto:bath@example.test"', $html );
		$this->assertStringContainsString( '<address', $html );
		$this->assertStringContainsString( '<dl', $html );
	}

	public function test_multiple_offices_render_one_details_each_in_a_native_exclusive_group(): void {
		$this->seed_offices(
			array(
				array( 'title' => 'Bath office' ),
				array( 'title' => 'Bristol office' ),
				array( 'title' => 'Wells office' ),
			)
		);

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertSame( 3, preg_match_all( '/<details[\s>]/', $html ) );
		$this->assertSame( 3, substr_count( $html, 'name="locations-0"' ), 'Exclusive-open group is scoped per section INSTANCE (index-suffixed).' );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$this->seed_offices( array( array( 'title' => 'Bath office' ) ) );

		$html = $this->render_locations( array() );

		$this->assertSame( 0, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertFalse( salt_h1_claimed() );
		// Office names sit one level below the (unrendered, level-2) main heading.
		$this->assertStringContainsString( '<span class="salt-locations__name">Bath office</span>', $html, 'Office names are styled spans, not headings — heading semantics in <summary> are unreliably exposed to AT.' );
		$this->assertDoesNotMatchRegularExpression( '/<h[1-6][^>]*>Bath office/', $html );
	}

	public function test_broken_office_without_title_is_skipped_and_logged(): void {
		$this->seed_offices(
			array(
				array( 'title' => 'Bath office' ),
				array( 'title' => '   ' ),
			)
		);

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertSame( 1, preg_match_all( '/<details[\s>]/', $html ) );
		$this->assertStringContainsString( 'no title', implode( ' ', $this->logs() ) );
	}

	public function test_all_offices_broken_collapses_to_the_zero_state(): void {
		$this->seed_offices( array( array( 'title' => '' ) ) );

		$this->assertSame( '', $this->render_locations( array( 'heading' => 'Find us' ) ) );
	}

	// -----------------------------------------------------------------------
	// Status pill (COMPL02 unknown => no pill; label text = non-colour signal).
	// -----------------------------------------------------------------------

	public function test_open_office_renders_a_success_pill(): void {
		$this->seed_offices(
			array(
				array(
					'title' => 'Bath office',
					'hours' => self::always_open_hours(),
				),
			)
		); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertMatchesRegularExpression( '/<span class="salt-locations__status" data-status="open" [^>]*>Open now<\/span>/', $html );
	}

	public function test_closed_office_renders_a_muted_pill(): void {
		$this->seed_offices(
			array(
				array(
					'title' => 'Bath office',
					'hours' => self::always_closed_hours(),
				),
			)
		); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertMatchesRegularExpression( '/<span class="salt-locations__status" data-status="closed" [^>]*>Closed<\/span>/', $html );
	}

	public function test_appointment_office_renders_an_info_pill(): void {
		$this->seed_offices(
			array(
				array(
					'title' => 'Bath office',
					'hours' => self::always_appointment_hours(),
				),
			)
		); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertMatchesRegularExpression( '/<span class="salt-locations__status" data-status="appointment" [^>]*>By appointment<\/span>/', $html );
	}

	public function test_unknown_status_renders_no_pill_at_all(): void {
		$this->seed_offices( array( array( 'title' => 'Bath office' ) ) );

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertStringNotContainsString( 'salt-locations__status', $html );
		$this->assertStringContainsString( 'Bath office', $html );
	}

	// -----------------------------------------------------------------------
	// Directions link (PRIV01-compatible plain href; loads nothing unclicked).
	// -----------------------------------------------------------------------

	public function test_directions_link_urlencodes_the_address(): void {
		$this->seed_offices(
			array(
				array(
					'title'   => 'Bath office',
					'address' => array(
						'street'     => '1 High Street',
						'locality'   => 'Bath',
						'postalCode' => 'BA1 1AA',
					),
				),
			)
		);

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertStringContainsString(
			'href="https://www.openstreetmap.org/directions?to=' . rawurlencode( '1 High Street, Bath, BA1 1AA' ) . '"',
			$html
		);
		$this->assertStringContainsString( 'Get directions', $html );
	}

	public function test_office_without_address_gets_no_directions_link(): void {
		$this->seed_offices(
			array(
				array(
					'title' => 'Bath office',
					'phone' => '01225 000000',
				),
			)
		); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertStringNotContainsString( 'openstreetmap.org', $html );
	}

	// -----------------------------------------------------------------------
	// Schema (SEO01) + the TEST03 pill/openingHoursSpecification cross-check.
	// -----------------------------------------------------------------------

	/**
	 * Fixture hours shared by the schema tests: Monday trading, Tuesday
	 * appointment-only, one malformed row.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function schema_fixture_hours(): array {
		return array(
			self::hours_row( 'monday', '09:00', '17:00' ),
			self::hours_row( 'tuesday', '', '', true ),
			self::hours_row( 'someday', '09:00', '17:00' ),
		);
	}

	/**
	 * Render one fully-populated office and return the queued schema nodes.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function render_office_and_get_queue(): array {
		$this->seed_offices(
			array(
				array(
					'title'   => 'Bath office',
					'address' => array(
						'street'     => "1 High Street\nWidcombe",
						'locality'   => 'Bath',
						'region'     => 'Somerset',
						'postalCode' => 'BA1 1AA',
						'country'    => 'GB',
					),
					'phone'   => '+44 1225 000000',
					'email'   => 'bath@example.test',
					'hours'   => self::schema_fixture_hours(),
				),
			)
		);
		$this->render_locations( array( 'heading' => 'Find us' ) );
		return salt_schema_queue();
	}

	public function test_each_office_queues_a_flagged_localbusiness_node(): void {
		$queue = $this->render_office_and_get_queue();

		$this->assertCount( 1, $queue );
		$node = $queue[0];

		$this->assertSame( 'LocalBusiness', $node['@type'] );
		$this->assertTrue( $node['salt_office'] );
		$this->assertSame( 'Bath office', $node['name'] );
		$this->assertSame( '+44 1225 000000', $node['telephone'] );
		$this->assertSame( 'bath@example.test', $node['email'] );

		$this->assertSame(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => '1 High Street, Widcombe',
				'addressLocality' => 'Bath',
				'addressRegion'   => 'Somerset',
				'postalCode'      => 'BA1 1AA',
				'addressCountry'  => 'GB',
			),
			$node['address']
		);
	}

	public function test_opening_hours_specification_omits_appointment_and_malformed_days(): void {
		$queue = $this->render_office_and_get_queue();

		$this->assertSame(
			array(
				array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => 'https://schema.org/Monday',
					'opens'     => '09:00',
					'closes'    => '17:00',
				),
			),
			$queue[0]['openingHoursSpecification']
		);
	}

	public function test_specification_agrees_with_the_pill_on_the_same_rows(): void {
		// TEST03 cross-check: the SAME stored rows drive both surfaces.
		$hours = self::schema_fixture_hours();
		$tz    = new DateTimeZone( 'Europe/London' );

		// Fixture week: 2026-06-01 is a Monday, 2026-06-02 a Tuesday.
		$monday_noon  = new DateTimeImmutable( '2026-06-01 12:00:00', $tz );
		$monday_night = new DateTimeImmutable( '2026-06-01 18:00:00', $tz );
		$tuesday_noon = new DateTimeImmutable( '2026-06-02 12:00:00', $tz );

		// The one emitted spec (Monday 09:00–17:00) matches the live status…
		$this->assertSame( 'open', salt_location_status( $hours, $monday_noon, $tz )['status'] );
		$this->assertSame( 'closed', salt_location_status( $hours, $monday_night, $tz )['status'] );

		// …and the day omitted from the spec is exactly the appointment pill day.
		$this->assertSame( 'appointment', salt_location_status( $hours, $tuesday_noon, $tz )['status'] );

		$queue = $this->render_office_and_get_queue();
		$days  = array_column( $queue[0]['openingHoursSpecification'], 'dayOfWeek' );
		$this->assertNotContains( 'https://schema.org/Tuesday', $days );
		$this->assertContains( 'https://schema.org/Monday', $days );
	}

	public function test_office_without_hours_queues_a_node_without_specification(): void {
		$this->seed_offices( array( array( 'title' => 'Bath office' ) ) );
		$this->render_locations( array( 'heading' => 'Find us' ) );

		$queue = salt_schema_queue();
		$this->assertCount( 1, $queue );
		$this->assertArrayNotHasKey( 'openingHoursSpecification', $queue[0] );
	}

	public function test_graph_builder_links_offices_to_the_parent_organization(): void {
		$this->render_office_and_get_queue();

		$graph = salt_schema_graph(
			array(
				'home_url' => 'https://example.test',
				'page_url' => 'https://example.test/p',
				'org'      => array( 'name' => 'Lightly Salted' ),
			)
		)['@graph'];

		$offices = array_values( array_filter( $graph, static fn( array $node ): bool => 'LocalBusiness' === $node['@type'] ) );

		$this->assertCount( 1, $offices );
		$this->assertSame( array( '@id' => 'https://example.test#organization' ), $offices[0]['parentOrganization'] );
		$this->assertArrayNotHasKey( 'salt_office', $offices[0], 'The internal flag is stripped from output (SEO01).' );
	}

	// -----------------------------------------------------------------------
	// Logic/view split (ID-3587): resolver and view.
	// -----------------------------------------------------------------------

	/**
	 * Seed one locations FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @param int                 $index  Loop index.
	 * @return array<string,mixed>|null
	 */
	private function resolve_locations( array $fields, int $index = 0 ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'locations',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'locations' );
		$this->assertSame( 'salt_resolve_section_locations', $resolver );

		return $resolver(
			array(
				'index'      => $index,
				'is_primary' => false,
			)
		);
	}

	public function test_resolver_returns_null_for_the_zero_state(): void {
		$this->assertNull( $this->resolve_locations( array( 'heading' => 'Find us' ) ), 'No offices.' );

		$this->seed_offices( array( array( 'title' => '   ' ) ) );
		$this->assertNull( $this->resolve_locations( array( 'heading' => 'Find us' ) ), 'Every office broken.' );
		$this->assertCount( 1, $this->logs() );

		$this->seed_offices( array( array( 'title' => 'Bath office' ) ) );
		$GLOBALS['salt_test_fields']['option']['module_location'] = false;
		$this->assertNull( $this->resolve_locations( array( 'heading' => 'Find us' ) ), 'Module off.' );
		$this->assertSame( array(), salt_schema_queue() );
	}

	public function test_resolver_props_use_the_contract_names(): void {
		$this->seed_offices(
			array(
				array(
					'ID'      => 301,
					'title'   => ' Bath office ',
					'address' => array(
						'street'     => ' 1 High Street ',
						'region'     => '  ',
						'locality'   => 'Bath',
						'postalCode' => 'BA1 1AA',
					),
					'phone'   => ' +44 (0)1225 000000 ',
					'email'   => '',
					'hours'   => self::always_appointment_hours(),
				),
				array(
					'ID'    => 302,
					'title' => 'Wells office',
					'phone' => 'Ask at the desk',
				),
			)
		);

		$resolved = $this->resolve_locations(
			array(
				'heading' => ' Find us ',
				'eyebrow' => '',
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'offices', 'timezone', 'labels' ), array_keys( $resolved['props'] ) );
		$this->assertNull( $resolved['props']['eyebrow'] );
		$this->assertSame( ' Find us ', $resolved['props']['heading'], 'Optional text is never trimmed.' );
		$this->assertTrue( $resolved['section']['has_heading'] );
		$this->assertSame( 'locations', $resolved['section']['name'] );

		$bath = $resolved['props']['offices'][0];
		$this->assertSame(
			array( 'id', 'title', 'address', 'phone', 'phoneHref', 'email', 'hours', 'opening_hours', 'status', 'coordinates', 'directionsHref' ),
			array_keys( $bath )
		);
		$this->assertSame( '301', $bath['id'] );
		$this->assertSame( 'Bath office', $bath['title'] );
		$this->assertSame( array( '1 High Street', 'Bath', 'BA1 1AA' ), $bath['address'] );
		$this->assertSame( '+44 (0)1225 000000', $bath['phone'] );
		$this->assertSame( 'tel:+4401225000000', $bath['phoneHref'] );
		$this->assertNull( $bath['email'] );
		$this->assertSame( self::always_appointment_hours(), $bath['opening_hours'], 'Stored rows, as the opening-hours component takes them.' );
		$this->assertSame(
			array(
				'day'         => 'monday',
				'appointment' => true,
			),
			$bath['hours'][0],
			'data-hours: an appointment row has no times.'
		);
		$this->assertSame( 'appointment', $bath['status'] );
		$this->assertNull( $bath['coordinates'], 'No lat or lng: no map.' );
		$this->assertSame( 'https://www.openstreetmap.org/directions?to=' . rawurlencode( '1 High Street, Bath, BA1 1AA' ), $bath['directionsHref'] );

		$wells = $resolved['props']['offices'][1];
		$this->assertSame( 'Ask at the desk', $wells['phone'] );
		$this->assertNull( $wells['phoneHref'], 'Rule 5: no dialable digits — drawn as text.' );
		$this->assertSame( array(), $wells['address'] );
		$this->assertNull( $wells['directionsHref'] );
		$this->assertSame( 'unknown', $wells['status'] );

		$this->assertCount( 2, salt_schema_queue(), 'One LocalBusiness node per office, queued by the resolver.' );
	}

	public function test_details_group_is_locations_and_the_plan_index(): void {
		// markup/locations.json: every office's details carries
		// locations-<plan index>, so two location bands on a page never
		// share exclusive-open state.
		$this->seed_offices(
			array(
				array( 'title' => 'Bath office' ),
				array( 'title' => 'Wells office' ),
			)
		);

		salt_test_seed_fc(
			array(
				array(
					'layout' => 'locations',
					'fields' => array( 'heading' => 'Find us' ),
				),
			)
		);
		the_row();
		ob_start();
		salt_render_section(
			'locations',
			array(
				'index'      => 7,
				'is_primary' => false,
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame( 2, substr_count( $html, 'name="locations-7"' ) );
		$this->assertStringNotContainsString( 'locations-0', $html );
	}

	public function test_map_draws_from_the_stored_coordinates_and_zoom(): void {
		// ID-3967: the map starts at the office's stored lat, lng and zoom;
		// an office with no zoom starts at the contract's 14; one without
		// both coordinates has none, and maps off draws none at all.
		$GLOBALS['salt_test_fields']['option']['maps_enabled'] = true;
		$this->seed_offices(
			array(
				array( 'title' => 'Bath office' ),
				array( 'title' => 'Wells office' ),
				array( 'title' => 'Frome office' ),
			)
		);
		$GLOBALS['salt_test_fields']['100'] = array(
			'lat'  => 51.3822,
			'lng'  => -2.359,
			'zoom' => 16,
		);
		$GLOBALS['salt_test_fields']['101'] = array(
			'lat' => '51.2094',
			'lng' => '-2.6450',
		);
		$GLOBALS['salt_test_fields']['102'] = array( 'lat' => 51.23 );

		$offices = $this->resolve_locations( array( 'heading' => 'Find us' ) )['props']['offices'];
		$this->assertSame(
			array(
				'lat'  => 51.3822,
				'lng'  => -2.359,
				'zoom' => 16,
			),
			$offices[0]['coordinates']
		);
		$this->assertSame(
			array(
				'lat'  => 51.2094,
				'lng'  => -2.645,
				'zoom' => 14,
			),
			$offices[1]['coordinates'],
			'No zoom stored: the contract\'s 14.'
		);
		$this->assertNull( $offices[2]['coordinates'], 'Only a latitude: no map.' );

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );
		$this->assertStringContainsString( 'data-lat="51.3822" data-lng="-2.359" data-zoom="16"', $html );
		$this->assertStringContainsString( 'data-lat="51.2094" data-lng="-2.645" data-zoom="14"', $html );
		$this->assertSame( 2, substr_count( $html, 'class="salt-map"' ) );

		$GLOBALS['salt_test_fields']['option']['maps_enabled'] = false;
		$this->assertStringNotContainsString( 'salt-map', $this->render_locations( array( 'heading' => 'Find us' ) ), 'Maps off: no facade.' );
	}

	public function test_view_draws_from_props_alone(): void {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/locations.php',
			false,
			array(
				'props' => array(
					'eyebrow'  => null,
					'heading'  => 'From props',
					'offices'  => array(
						array(
							'id'             => '9',
							'title'          => 'Bath <office>',
							'address'        => array( '1 High Street' ),
							'phone'          => 'Ask',
							'phoneHref'      => null,
							'email'          => 'bath@example.test',
							'hours'          => array(
								array(
									'day'   => 'monday',
									'open'  => '09:00',
									'close' => '17:00',
								),
							),
							'opening_hours'  => array(),
							'status'         => 'closed',
							'coordinates'    => null,
							'directionsHref' => 'https://www.openstreetmap.org/directions?to=1%20High%20Street',
						),
					),
					'timezone' => 'Europe/Paris',
					'labels'   => array(
						'open'          => 'Ouvert',
						'appointment'   => 'Sur rendez-vous',
						'closed'        => 'Fermé',
						'getDirections' => 'Itinéraire',
						'directions'    => 'Itinéraire vers',
					),
				),
				'plan'  => array(
					'index'         => 3,
					'anchor'        => 'bureaux',
					'headingLevel'  => 2,
					'headingId'     => 'bureaux__heading',
					'bodyBaseLevel' => 2,
					'priorityMedia' => false,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match_all( '/<h2 id="bureaux__heading">/', $html ) );
		$this->assertSame( 1, substr_count( $html, '<details class="salt-accordion__item" name="locations-3">' ) );
		$this->assertStringContainsString( 'Bath &lt;office&gt;', $html );
		$this->assertStringContainsString( 'data-status="closed" data-hours="[{&quot;day&quot;:&quot;monday&quot;,&quot;open&quot;:&quot;09:00&quot;,&quot;close&quot;:&quot;17:00&quot;}]" data-timezone="Europe/Paris"', $html );
		$this->assertStringContainsString( '>Fermé</span>', $html, 'The status word is the labels\'.' );
		$this->assertStringContainsString( '<li>Ask</li>', $html, 'No phoneHref: the number is text.' );
		$this->assertStringContainsString( 'href="mailto:bath@example.test"', $html );
		$this->assertStringContainsString( 'aria-label="Itinéraire vers Bath &lt;office&gt;">Itinéraire</a>', $html );
		$this->assertStringNotContainsString( '<dl', $html, 'No stored hours: no opening hours.' );
		$this->assertStringNotContainsString( 'salt-map', $html, 'No coordinates: no map.' );
		$this->assertStringNotContainsString( 'salt-section', $html, 'The band is the entry\'s, not the view\'s.' );
		$this->assertSame( array(), salt_schema_queue(), 'The view never queues schema.' );
	}
}
