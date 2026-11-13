<?php
/**
 * Pricing slice unit tests: layout shape (DATA02), x-card-pricing contract,
 * section 0/1/N states (COMPL02), multiple-featured demotion, malformed
 * plans skipped + logged, escaping, and the no-schema invariant (§10 #11:
 * Offer/PriceSpecification deferred — prices are display strings).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class PricingSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Render ONE pricing section row.
	 *
	 * @param array<string,mixed> $fields  Row sub-field fixtures.
	 * @param array<string,mixed> $context Loop context.
	 * @return string
	 */
	private function render_pricing_section( array $fields = array(), array $context = array(
		'index'      => 0,
		'is_primary' => true,
	) ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'pricing',
					'fields' => $fields,
				),
			)
		);
		$this->assertTrue( have_rows( 'sections' ) );
		the_row();

		ob_start();
		salt_render_section( 'pricing', $context );
		return (string) ob_get_clean();
	}

	/**
	 * A well-formed plan fixture.
	 *
	 * @param array<string,mixed> $overrides Field overrides.
	 * @return array<string,mixed>
	 */
	private function plan( array $overrides = array() ): array {
		return array_merge(
			array(
				'name'     => 'Starter',
				'price'    => '£29',
				'period'   => '/month',
				'features' => array(
					array( 'text' => 'One brochure site' ),
					array( 'text' => 'Email support' ),
				),
				'cta'      => array(
					'url'   => 'https://example.test/signup/',
					'title' => 'Start now',
				),
				'featured' => false,
			),
			$overrides
		);
	}

	// -----------------------------------------------------------------
	// Layout shape (DATA02 / FLEET07).
	// -----------------------------------------------------------------

	public function test_layout_file_shape_and_key_conventions(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/pricing.php';

		$this->assertSame( 'pricing', $layout['name'] );
		$this->assertSame( 'layout_salt_pricing', $layout['key'] );

		$names = array_map(
			static fn( array $field ): string => (string) ( $field['name'] ?? '' ),
			$layout['sub_fields']
		);

		// Content fields first…
		$this->assertSame( array( 'eyebrow', 'heading', 'plans' ), array_slice( array_values( array_filter( $names ) ), 0, 3 ) );
		// …then the shared §11 wrapper controls last.
		$this->assertContains( 'section_spacing', $names );
		$this->assertGreaterThan( array_search( 'plans', $names, true ), array_search( 'section_spacing', $names, true ) );

		$plans = null;
		foreach ( $layout['sub_fields'] as $field ) {
			if ( 'plans' === ( $field['name'] ?? '' ) ) {
				$plans = $field;
			}
		}
		$this->assertNotNull( $plans );
		$this->assertSame( 'repeater', $plans['type'] );
		$this->assertSame(
			array( 'name', 'price', 'period', 'features', 'cta', 'featured' ),
			array_map( static fn( array $sub ): string => (string) $sub['name'], $plans['sub_fields'] )
		);
		foreach ( $plans['sub_fields'] as $sub ) {
			$this->assertStringStartsWith( 'field_salt_pricing_plans_', (string) $sub['key'] );
			if ( 'features' === $sub['name'] ) {
				$this->assertSame( 'repeater', $sub['type'] );
				$this->assertSame( 'field_salt_pricing_plans_features_text', $sub['sub_fields'][0]['key'] );
			}
		}
	}

	// -----------------------------------------------------------------
	// x-card-pricing component contract.
	// -----------------------------------------------------------------

	public function test_card_pricing_featured_gets_ring_and_badge(): void {
		$html = salt_get_component(
			'card-pricing',
			array(
				'name'     => 'Growth',
				'price'    => '£59',
				'period'   => '/month',
				'features' => array( 'Everything in Starter' ),
				'cta'      => array(
					'label' => 'Choose Growth',
					'url'   => 'https://example.test/growth/',
				),
				'featured' => true,
			)
		);

		$this->assertStringContainsString( 'x-card-pricing-featured', $html );
		$this->assertStringContainsString( 'border-brand', $html );
		$this->assertStringContainsString( 'Most popular', $html );
		$this->assertStringContainsString( 'x-button-primary', $html, 'Featured plan CTA promotes to primary.' );
	}

	public function test_card_pricing_non_featured_has_no_badge(): void {
		$html = salt_get_component(
			'card-pricing',
			array(
				'name' => 'Starter',
				'cta'  => array(
					'label' => 'Choose',
					'url'   => 'https://example.test/',
				),
			)
		);

		$this->assertStringNotContainsString( 'Most popular', $html );
		$this->assertStringContainsString( 'border-border', $html );
		$this->assertStringContainsString( 'x-button-secondary', $html );
	}

	public function test_card_pricing_price_markup_reads_as_one_phrase(): void {
		$html = salt_get_component(
			'card-pricing',
			array(
				'name'   => 'Starter',
				'price'  => '£29',
				'period' => '/month',
			)
		);

		// Price + period live in ONE paragraph, in reading order, with no
		// aria-hidden splitting — screen readers announce "£29 /month".
		$this->assertSame( 1, preg_match( '/<p class="x-card-pricing-price">.*£29.*\/month.*<\/p>/s', $html ) );
		$this->assertStringNotContainsString( 'aria-hidden', $html );
	}

	public function test_card_pricing_empty_name_renders_nothing(): void {
		$this->assertSame( '', salt_get_component( 'card-pricing', array( 'name' => '   ' ) ) );
		$this->assertStringContainsString( 'x-card-pricing: empty name', implode( ' ', $this->logs() ) );
	}

	public function test_card_pricing_skips_malformed_features_and_partial_cta(): void {
		$html = salt_get_component(
			'card-pricing',
			array(
				'name'     => 'Starter',
				'features' => array( 'Kept', '', 42, array( 'nested' ) ),
				'cta'      => array( 'label' => 'No URL' ),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<li\b/', $html ), 'Only the valid feature renders.' );
		$this->assertStringNotContainsString( 'x-button', $html, 'Partial CTA renders no button.' );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'feature 1 is not a non-empty string', $logs );
		$this->assertStringContainsString( 'CTA is missing a label or URL', $logs );
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_zero_plans_renders_nothing(): void {
		$this->assertSame( '', $this->render_pricing_section( array( 'heading' => 'Pricing' ) ) );
		$this->assertSame( '', $this->render_pricing_section( array( 'plans' => array() ) ) );
		$this->assertSame( '', $this->render_pricing_section( array( 'plans' => 'not-an-array' ) ) );
	}

	public function test_single_plan_is_a_valid_static_layout(): void {
		$html = $this->render_pricing_section(
			array(
				'heading' => 'Simple pricing',
				'plans'   => array( $this->plan() ),
			)
		);

		$this->assertStringContainsString( 'x-pricing-grid', $html );
		$this->assertSame( 1, preg_match_all( '/<article\b/', $html ) );
		$this->assertStringContainsString( '<h1', $html, 'Primary section with heading claims the H1.' );
		$this->assertStringContainsString( '<h2', $html, 'Plan name steps to level + 1.' );
		$this->assertStringContainsString( 'sm:max-w-md', $html, 'Single plan gets the static single-card layout.' );
		$this->assertStringNotContainsString( '<script', $html, 'No JS in this section — ever.' );
	}

	public function test_three_plans_render_three_column_grid(): void {
		$html = $this->render_pricing_section(
			array(
				'eyebrow' => 'Pricing',
				'heading' => 'Plans',
				'plans'   => array(
					$this->plan( array( 'name' => 'Starter' ) ),
					$this->plan(
						array(
							'name'     => 'Growth',
							'featured' => true,
						)
					),
					$this->plan( array( 'name' => 'Scale' ) ),
				),
			)
		);

		$this->assertSame( 3, preg_match_all( '/<article\b/', $html ) );
		$this->assertStringContainsString( 'lg:grid-cols-3', $html );
		$this->assertStringContainsString( 'x-eyebrow', $html );
		$this->assertSame( 1, preg_match_all( '/Most popular/', $html ) );
	}

	public function test_only_first_featured_plan_is_honoured_and_logged(): void {
		$html = $this->render_pricing_section(
			array(
				'heading' => 'Plans',
				'plans'   => array(
					$this->plan(
						array(
							'name'     => 'Starter',
							'featured' => true,
						)
					),
					$this->plan(
						array(
							'name'     => 'Growth',
							'featured' => true,
						)
					),
					$this->plan(
						array(
							'name'     => 'Scale',
							'featured' => true,
						)
					),
				),
			)
		);

		$this->assertSame( 1, preg_match_all( '/Most popular/', $html ), 'Exactly one badge.' );
		$this->assertSame( 1, preg_match_all( '/x-card-pricing-featured/', $html ) );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'plan 1 ("Growth") also flagged featured', $logs );
		$this->assertStringContainsString( 'plan 2 ("Scale") also flagged featured', $logs );
	}

	public function test_malformed_plans_are_skipped_and_logged(): void {
		$html = $this->render_pricing_section(
			array(
				'heading' => 'Plans',
				'plans'   => array(
					$this->plan( array( 'name' => 'Kept' ) ),
					'not-a-row',
					array( 'price' => '£99' ),
					$this->plan( array( 'name' => '   ' ) ),
				),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<article\b/', $html ), 'Only the valid plan renders.' );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'pricing section: plan 1 is not a row array', $logs );
		$this->assertStringContainsString( 'pricing section: plan 2 has no name', $logs );
		$this->assertStringContainsString( 'pricing section: plan 3 has no name', $logs );
	}

	public function test_all_plans_malformed_renders_nothing(): void {
		$html = $this->render_pricing_section(
			array(
				'heading' => 'Plans',
				'plans'   => array( array( 'price' => '£99' ) ),
			)
		);

		$this->assertSame( '', $html, 'Never a blank band (COMPL02).' );
		$this->assertNotSame( array(), $this->logs() );
	}

	// -----------------------------------------------------------------
	// Escaping + heading claim.
	// -----------------------------------------------------------------

	public function test_values_are_escaped_on_output(): void {
		$html = $this->render_pricing_section(
			array(
				'heading' => 'Plans <script>alert(1)</script>',
				'plans'   => array(
					$this->plan(
						array(
							'name'     => 'Starter <em>x</em>',
							'price'    => '£29 <b>only</b>',
							'features' => array( array( 'text' => 'Feature <script>alert(2)</script>' ) ),
							'cta'      => array(
								'url'   => 'javascript:alert(3)',
								'title' => 'Go',
							),
						)
					),
				),
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<em>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$html = $this->render_pricing_section(
			array(
				'plans' => array( $this->plan() ),
			),
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);

		$this->assertStringNotContainsString( '<h1', $html );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less primary section must not consume the H1 claim (A11Y01).' );
	}

	// -----------------------------------------------------------------
	// No schema for this section (§10 #11 — deferred, docblocked).
	// -----------------------------------------------------------------

	public function test_no_schema_nodes_are_queued(): void {
		salt_schema_reset();

		$graph_args = array(
			'home_url' => 'https://example.test',
			'page_url' => 'https://example.test/p',
		);
		$baseline   = count( salt_schema_graph( $graph_args )['@graph'] );

		$this->render_pricing_section(
			array(
				'heading' => 'Plans',
				'plans'   => array( $this->plan() ),
			)
		);

		$this->assertCount( $baseline, salt_schema_graph( $graph_args )['@graph'], 'Pricing section queues no schema nodes (Offer deferred).' );
	}

	// -----------------------------------------------------------------
	// Logic/view split (ID-3587): resolver and view.
	// -----------------------------------------------------------------

	/**
	 * Seed one pricing FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return array<string,mixed>|null
	 */
	private function resolve_pricing( array $fields ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'pricing',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'pricing' );
		$this->assertSame( 'salt_resolve_section_pricing', $resolver );

		return $resolver(
			array(
				'index'      => 0,
				'is_primary' => false,
			)
		);
	}

	public function test_resolver_returns_null_without_a_named_plan(): void {
		$this->assertNull(
			$this->resolve_pricing(
				array(
					'heading' => 'Plans',
					'plans'   => array( 'not a row', $this->plan( array( 'name' => '  ' ) ) ),
				)
			)
		);
	}

	public function test_resolver_props_use_the_contract_names(): void {
		$resolved = $this->resolve_pricing(
			array(
				'eyebrow' => ' Pricing ',
				'heading' => ' Plans ',
				'plans'   => array(
					$this->plan(
						array(
							'name'     => ' Starter ',
							'features' => array( array( 'text' => ' Site ' ), array( 'text' => ' ' ), 'bad' ),
							'featured' => true,
						)
					),
					$this->plan(
						array(
							'name'     => 'Pro',
							'price'    => null,
							'cta'      => array(
								'url'   => 'https://example.test/pro/',
								'title' => '',
							),
							'featured' => true,
						)
					),
				),
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'plans' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'Pricing', $resolved['props']['eyebrow'], 'Trimmed, as before the split.' );
		$this->assertSame( 'Plans', $resolved['props']['heading'] );
		$this->assertTrue( $resolved['section']['has_heading'] );

		$this->assertSame(
			array(
				'name'     => 'Starter',
				'price'    => '£29',
				'period'   => '/month',
				'features' => array( 'Site' ),
				'cta'      => array(
					'label' => 'Start now',
					'url'   => 'https://example.test/signup/',
				),
				'featured' => true,
			),
			$resolved['props']['plans'][0]
		);
		$this->assertSame( '', $resolved['props']['plans'][1]['price'] );
		$this->assertSame( 'Choose plan', $resolved['props']['plans'][1]['cta']['label'] );
		$this->assertFalse( $resolved['props']['plans'][1]['featured'], 'Only the first featured plan is honoured.' );
		$this->assertCount( 1, $this->logs(), 'The demotion is logged once.' );
	}

	public function test_resolver_blank_eyebrow_and_heading_are_null(): void {
		$resolved = $this->resolve_pricing(
			array(
				'eyebrow' => '   ',
				'plans'   => array( $this->plan( array( 'cta' => array( 'url' => ' ' ) ) ) ),
			)
		);

		$this->assertNull( $resolved['props']['eyebrow'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertFalse( $resolved['section']['has_heading'] );
		$this->assertSame( array(), $resolved['props']['plans'][0]['cta'], 'A link without a URL is no CTA.' );
	}

	public function test_view_draws_from_props_alone(): void {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/pricing.php',
			false,
			array(
				'props' => array(
					'eyebrow' => null,
					'heading' => 'From props',
					'plans'   => array(
						array(
							'name'     => 'Solo',
							'price'    => '£9',
							'period'   => '',
							'features' => array( 'One thing' ),
							'cta'      => array(),
							'featured' => false,
						),
					),
				),
				'plan'  => array(
					'index'         => 0,
					'headingLevel'  => 2,
					'priorityMedia' => false,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ) );
		$this->assertSame( 1, preg_match_all( '/<h3[\s>]/', $html ), 'Plan name one level below the heading.' );
		$this->assertStringContainsString( 'x-pricing-grid grid gap-6 pt-3 sm:max-w-md', $html, 'One plan: single-card grid.' );
		$this->assertStringContainsString( 'One thing', $html );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );
	}
}
