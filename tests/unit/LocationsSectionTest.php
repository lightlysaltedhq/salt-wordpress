<?php
/**
 * Locations section — states (COMPL02), pill mapping, directions link,
 * schema nodes (SEO01) and the pill/openingHoursSpecification cross-check
 * (TEST03).
 *
 * Time seam: the partial calls salt_location_status() with no $now (live
 * time), so rendered-pill tests use fixtures whose status is the same at
 * ANY instant (all-week overnight spans => always open; a day 3 days away
 * => always closed; all-week appointment_only => always appointment; no
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
	 * @param array<int,array<string,mixed>> $offices Office fixtures: title, address, phone, email, opening_hours.
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
			foreach ( array( 'address', 'phone', 'email', 'opening_hours' ) as $key ) {
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
			'day'              => $day,
			'open'             => $open,
			'close'            => $close,
			'appointment_only' => $appointment,
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
					'title'         => 'Bath office',
					'address'       => array(
						'line1'    => '1 High Street',
						'city'     => 'Bath',
						'postcode' => 'BA1 1AA',
					),
					'phone'         => '+44 1225 000000',
					'email'         => 'bath@example.test',
					'opening_hours' => self::always_open_hours(),
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

		// Primary section heading claims the H1; office names step to h2.
		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertMatchesRegularExpression( '/<h2[^>]*>Bath office<\/h2>/', $html );

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
		$this->assertSame( 3, substr_count( $html, 'name="locations"' ) );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$this->seed_offices( array( array( 'title' => 'Bath office' ) ) );

		$html = $this->render_locations( array() );

		$this->assertSame( 0, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertFalse( salt_h1_claimed() );
		// Office names sit one level below the (unrendered, level-2) main heading.
		$this->assertMatchesRegularExpression( '/<h3[^>]*>Bath office<\/h3>/', $html );
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
					'title'         => 'Bath office',
					'opening_hours' => self::always_open_hours(),
				),
			)
		); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertMatchesRegularExpression( '/<span class="x-status-pill[^"]*text-success" data-status="open">Open now<\/span>/', $html );
	}

	public function test_closed_office_renders_a_muted_pill(): void {
		$this->seed_offices(
			array(
				array(
					'title'         => 'Bath office',
					'opening_hours' => self::always_closed_hours(),
				),
			)
		); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertMatchesRegularExpression( '/<span class="x-status-pill[^"]*text-ink-muted" data-status="closed">Closed<\/span>/', $html );
	}

	public function test_appointment_office_renders_an_info_pill(): void {
		$this->seed_offices(
			array(
				array(
					'title'         => 'Bath office',
					'opening_hours' => self::always_appointment_hours(),
				),
			)
		); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertMatchesRegularExpression( '/<span class="x-status-pill[^"]*text-info" data-status="appointment">By appointment<\/span>/', $html );
	}

	public function test_unknown_status_renders_no_pill_at_all(): void {
		$this->seed_offices( array( array( 'title' => 'Bath office' ) ) );

		$html = $this->render_locations( array( 'heading' => 'Find us' ) );

		$this->assertStringNotContainsString( 'x-status-pill', $html );
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
						'line1'    => '1 High Street',
						'city'     => 'Bath',
						'postcode' => 'BA1 1AA',
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
					'title'         => 'Bath office',
					'address'       => array(
						'line1'    => '1 High Street',
						'line2'    => 'Widcombe',
						'city'     => 'Bath',
						'region'   => 'Somerset',
						'postcode' => 'BA1 1AA',
						'country'  => 'GB',
					),
					'phone'         => '+44 1225 000000',
					'email'         => 'bath@example.test',
					'opening_hours' => self::schema_fixture_hours(),
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
}
