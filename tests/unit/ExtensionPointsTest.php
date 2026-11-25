<?php
/**
 * Acceptance for ID-3613: tests/fixtures/themes/grain-fixture is a child
 * theme modelled on grain-wordpress that swaps the call-to-action section's
 * view (layout call-to-action) and declares stats replaced (tier "logic"). Both are
 * honoured without touching the parent; undeclared sections render as
 * before, and an UNDECLARED replacement still fails exactly as before.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once dirname( __DIR__ ) . '/SaltOverridesTestCase.php';

final class ExtensionPointsTest extends SaltOverridesTestCase {

	// -----------------------------------------------------------------
	// Acceptance: the fixture child's view swap and logic takeover.
	// -----------------------------------------------------------------

	public function test_acceptance_the_declared_view_swap_draws_the_parent_props(): void {
		salt_test_use_child_theme( self::fixture() );
		$this->assertSame( 'view', salt_override_tier( 'call-to-action' ) );

		$this->seed_row(
			'call-to-action',
			array(
				'heading' => 'Ready?',
				'buttons' => array(
					salt_test_button( 'Talk to us', 'https://example.test/contact/' ),
				),
			)
		);
		$html = $this->render(
			'call-to-action',
			self::fixture() . '/template-parts/sections/call-to-action.php',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);

		$this->assertStringContainsString( 'data-track="call-to-action-1"', $html, 'Inside the parent wrapper.' );
		$this->assertStringContainsString( '<div class="grain-cta">', $html, 'The child view drew it.' );
		$this->assertStringContainsString( '<h1>Ready?</h1>', $html, 'From the parent resolver\'s props and plan.' );
		$this->assertStringContainsString( 'href="/contact/"', $html );
		$this->assertStringNotContainsString( 'data-block="call-to-action"', $html, 'The parent view did not run.' );
	}

	public function test_acceptance_the_declared_logic_section_is_the_childs_whole_template(): void {
		salt_test_use_child_theme( self::fixture() );
		$this->assertSame( 'logic', salt_override_tier( 'stats' ) );

		// An item with no value: the PARENT resolver would log "has no value"
		// and return the 0-state. It must not run at all.
		$this->seed_row(
			'stats',
			array(
				'heading' => 'Our numbers',
				'items'   => array( array( 'label' => 'No number' ) ),
			)
		);
		$html = $this->render(
			'stats',
			self::fixture() . '/template-parts/sections/stats.php',
			array(
				'index'      => 2,
				'is_primary' => true,
			)
		);

		$this->assertStringContainsString( '<div class="grain-stats"><h1>Our numbers</h1>', $html, 'The child composed the band and claimed the H1 itself.' );
		$this->assertSame( 1, substr_count( $html, 'data-track="stats-1"' ), 'One band: the child\'s, not one nested in the parent\'s.' );
		$this->assertSame( array(), $this->logs(), 'No parent resolver log, no whole-section-override warning.' );
	}

	public function test_a_declared_logic_section_without_a_child_template_fails_loudly(): void {
		$dir = $this->temp_child(
			array(
				'salt-overrides.json' => self::json(
					self::declaration(
						array(
							'sections' => array(
								self::entry(
									array(
										'section' => 'stats',
										'tier'    => 'logic',
										'files'   => array( 'template-parts/sections/stats.php' ),
									)
								),
							),
						)
					)
				),
			)
		);
		salt_test_use_child_theme( $dir );
		$this->seed_row( 'stats', array( 'items' => array( array( 'value' => '9' ) ) ) );

		try {
			$this->render( 'stats', self::parent_view( 'stats' ) );
			$this->fail( 'Expected a LogicException under debug.' );
		} catch ( LogicException $e ) {
			$this->assertStringContainsString( 'declares it replaced', $e->getMessage() );
		}

		add_filter( 'salt_is_debug', static fn(): bool => false, 20 );
		$this->assertSame( '', $this->render( 'stats', self::parent_view( 'stats' ) ), 'Production: nothing, never the parent view without props.' );
		$this->assertStringContainsString( 'has no template-parts/sections/stats.php', end( $GLOBALS['salt_test_logs'] ) );
	}

	public function test_undeclared_sections_are_unchanged_while_a_declaration_is_active(): void {
		$this->seed_row( 'hero', array( 'heading' => 'Same bytes' ) );
		$without = $this->render( 'hero', self::parent_view( 'hero' ) );

		salt_render_state_reset();
		salt_test_use_child_theme( self::fixture() );
		$this->seed_row( 'hero', array( 'heading' => 'Same bytes' ) );
		$with = $this->render( 'hero', self::parent_view( 'hero' ) );

		$this->assertSame( $without, $with );
	}

	public function test_an_undeclared_whole_section_template_is_still_loaded_as_a_view_and_logged(): void {
		salt_test_use_child_theme( self::fixture() );
		$dir  = $this->temp_child(
			array(
				'template-parts/sections/rich-text.php' => '<?php salt_section( array( "name" => "rich-text" ), static function ( int $level ): void { echo "legacy"; } );',
			)
		);
		$view = $dir . '/template-parts/sections/rich-text.php';
		$this->seed_row( 'rich-text', array( 'heading' => 'Legacy' ) );

		$html = $this->render( 'rich-text', $view );

		$this->assertSame( 1, substr_count( $html, 'data-track="rich-text-1"' ), 'The entry\'s band …' );
		$this->assertSame( 1, substr_count( $html, 'data-track="rich-text-2"' ), '… nests the legacy template\'s own band, as before the declaration existed.' );
		$this->assertStringContainsString( 'whole-section override', implode( ' ', $this->logs() ) );
	}

	public function test_an_undeclared_resolver_replacement_still_fails_loudly(): void {
		salt_test_use_child_theme( self::fixture() );
		$child = $this->temp_child( array( 'resolver.php' => "<?php\nfunction salt_resolve_section_zz_test_ep_conflict( array \$context ): ?array {\n\treturn array( 'section' => array( 'name' => 'child' ), 'props' => array() );\n}\n" ) );
		require $child . '/resolver.php';
		$parent_file  = dirname( __DIR__, 2 ) . '/inc/sections/zz-test-ep-conflict.php';
		$this->temp[] = $parent_file;
		file_put_contents( $parent_file, "<?php\nfunction salt_resolve_section_zz_test_ep_conflict( array \$context ): ?array {\n\treturn null;\n}\n" );

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( 'cannot be replaced' );
		$this->render( 'zz-test-ep-conflict', self::parent_view( 'hero' ) );
	}

	public function test_acceptance_through_salt_render_section_with_a_child_first_lookup(): void {
		salt_test_use_child_theme( self::fixture() );

		$this->seed_row( 'call-to-action', array( 'heading' => 'Found first' ) );
		ob_start();
		salt_render_section(
			'call-to-action',
			array(
				'index'      => 0,
				'is_primary' => false,
			)
		);
		$this->assertStringContainsString( '<div class="grain-cta">', (string) ob_get_clean(), 'locate_template() found the child view.' );

		$this->seed_row( 'stats', array( 'heading' => 'Taken over' ) );
		ob_start();
		salt_render_section(
			'stats',
			array(
				'index'      => 1,
				'is_primary' => false,
			)
		);
		$this->assertStringContainsString( '<div class="grain-stats"><h2>Taken over</h2>', (string) ob_get_clean() );
	}

	/**
	 * A child that DECLARES call-to-action as a view swap and also defines its
	 * resolver still gets the loud failure: tier "view" grants nothing to
	 * the logic. Separate process, because the defined function would
	 * otherwise outlive the test and break every later call-to-action test.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_a_child_resolver_for_a_view_declared_section_still_fails_loudly(): void {
		salt_test_use_child_theme( self::fixture() );
		$this->assertSame( 'view', salt_override_tier( 'call-to-action' ) );
		$child = $this->temp_child( array( 'resolver.php' => "<?php\nfunction salt_resolve_section_call_to_action( array \$context ): ?array {\n\treturn null;\n}\n" ) );
		require $child . '/resolver.php';
		$this->seed_row( 'call-to-action', array( 'heading' => 'Hijacked?' ) );

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( 'cannot be replaced' );
		$this->render( 'call-to-action', self::fixture() . '/template-parts/sections/call-to-action.php' );
	}

	public function test_a_logic_template_that_skips_salt_section_is_warned_about(): void {
		$declaration = self::json(
			self::declaration(
				array(
					'sections' => array(
						self::entry(
							array(
								'section' => 'stats',
								'tier'    => 'logic',
								'files'   => array( 'template-parts/sections/stats.php' ),
							)
						),
					),
				)
			)
		);

		$bare = $this->temp_child(
			array(
				'salt-overrides.json'               => $declaration,
				'template-parts/sections/stats.php' => '<?php echo "<p>bare</p>";',
			)
		);
		salt_test_use_child_theme( $bare );
		$this->assertSame( '<p>bare</p>', $this->render( 'stats', $bare . '/template-parts/sections/stats.php' ), 'Output passes through unchanged.' );
		$this->assertStringContainsString( 'drew markup without calling salt_section()', implode( ' ', $this->logs() ) );

		$GLOBALS['salt_test_logs'] = array();
		$empty                     = $this->temp_child(
			array(
				'salt-overrides.json'               => $declaration,
				'template-parts/sections/stats.php' => '<?php // Nothing to show.',
			)
		);
		salt_test_use_child_theme( $empty );
		$this->assertSame( '', $this->render( 'stats', $empty . '/template-parts/sections/stats.php' ) );
		$this->assertSame( array(), $this->logs(), 'Drawing nothing is a 0-state, not a missing band.' );
	}

	/**
	 * A child theme made of the grain fixture (its declaration and its
	 * tier-logic stats.php), plus a child-only showroom-finder.php and any
	 * other files.
	 *
	 * @param array<string,string> $extra Relative path => contents.
	 * @return string Directory.
	 */
	private function grain_child( array $extra = array() ): string {
		$fixture = self::fixture();
		return $this->temp_child(
			array_merge(
				array(
					'style.css'                         => (string) file_get_contents( $fixture . '/style.css' ),
					'salt-overrides.json'               => (string) file_get_contents( $fixture . '/salt-overrides.json' ),
					'template-parts/sections/stats.php' => (string) file_get_contents( $fixture . '/template-parts/sections/stats.php' ),
					'template-parts/sections/showroom-finder.php' => '<?php salt_section( salt_section_contract_args( "showroom-finder", $args ), static function ( int $level ): void { echo "<p>Find a showroom</p>"; } );',
				),
				$extra
			)
		);
	}

	/**
	 * A row of the given layout with heading and anchor.
	 *
	 * @param string $layout  Stored layout.
	 * @param string $heading Heading.
	 * @param string $anchor  anchorId.
	 * @return array{layout:string,fields:array<string,mixed>}
	 */
	private static function row( string $layout, string $heading, string $anchor ): array {
		return array(
			'layout' => $layout,
			'fields' => array(
				'variant'  => 'minimal',
				'heading'  => $heading,
				'settings' => array( 'anchorId' => $anchor ),
			),
		);
	}

	/**
	 * Draw the page loop over these rows.
	 *
	 * @param array<int,array<string,mixed>> $rows Stored rows.
	 * @return string
	 */
	private function render_page( array $rows ): string {
		salt_test_seed_fc( $rows );
		ob_start();
		get_template_part( 'template-parts/flexible-content' );
		return (string) ob_get_clean();
	}

	public function test_a_childs_own_templates_keep_their_planned_anchors(): void {
		salt_test_use_child_theme( $this->grain_child() );

		$html = $this->render_page(
			array(
				self::row( 'hero', 'Top', 'top' ),
				self::row( 'stats', 'Our numbers', 'numbers' ),
				self::row( 'rich-text', 'More', 'more' ),
				self::row( 'showroom_finder', '', 'showrooms' ),
			)
		);

		$this->assertStringContainsString( 'grain-stats', $html, 'The child\'s stats template drew.' );
		foreach ( array( 'top', 'numbers', 'more', 'showrooms' ) as $id ) {
			$this->assertSame( 1, substr_count( $html, 'id="' . $id . '"' ), $id );
			$this->assertStringNotContainsString( $id . '-2', $html );
		}
	}

	public function test_a_child_template_in_a_post_keeps_its_anchor(): void {
		require_once __DIR__ . '/../shims/blog.php';
		salt_test_use_child_theme( $this->grain_child() );
		$this->seed_posts(
			array(
				array(
					'ID'         => 500,
					'post_type'  => 'post',
					'post_title' => 'A post',
				),
			)
		);
		salt_test_seed_fc(
			array(
				self::row( 'rich-text', 'Intro', 'intro' ),
				self::row( 'stats', 'Our numbers', 'numbers' ),
			)
		);

		ob_start();
		salt_the_post_layout( 500 );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="intro"', $html );
		$this->assertStringContainsString( 'id="numbers"', $html );
		$this->assertStringNotContainsString( 'numbers-2', $html );
	}

	public function test_a_child_templates_own_anchor_and_a_parent_band_share_one_registry(): void {
		salt_test_use_child_theme(
			$this->grain_child(
				array( 'template-parts/sections/showroom-finder.php' => '<?php salt_section( array_merge( salt_section_contract_args( "showroom-finder", $args ), array( "anchor" => "foo" ) ), static function ( int $level ): void { echo "<p>Find a showroom</p>"; } );' )
			)
		);

		$html = $this->render_page(
			array(
				self::row( 'rich-text', 'Parent', 'foo' ),
				self::row( 'showroom_finder', '', '' ),
			)
		);

		$this->assertMatchesRegularExpression( '/id="foo"[^>]*>.*Parent.*id="foo-2"[^>]*>.*Find a showroom/s', $html, 'As on f3897db: the earlier band keeps foo.' );
	}

	public function test_an_unanchored_band_never_takes_a_child_bands_anchor(): void {
		salt_test_use_child_theme(
			$this->grain_child(
				array( 'template-parts/sections/showroom-finder.php' => '<?php salt_section( array_merge( salt_section_contract_args( "showroom-finder", $args ), array( "has_heading" => false ) ), static function ( int $level ): void { echo "<p>Find a showroom</p>"; } );' )
			)
		);

		$html = $this->render_page(
			array(
				self::row( 'rich-text', 'Unanchored', '' ),
				self::row( 'showroom_finder', '', 'rich-text' ),
			)
		);

		$this->assertMatchesRegularExpression( '/id="rich-text-2"[^>]*>.*Unanchored.*id="rich-text"[^>]*>.*Find a showroom/s', $html );
	}

	public function test_a_rows_second_band_mints_its_own_id(): void {
		$band = 'salt_section( array_merge( salt_section_contract_args( "showroom-finder", $args ), array( "has_heading" => false ) ), static function ( int $level ): void { echo "<p>Band</p>"; } );';
		salt_test_use_child_theme( $this->grain_child( array( 'template-parts/sections/showroom-finder.php' => '<?php ' . $band . ' ' . $band ) ) );

		$html = $this->render_page(
			array(
				self::row( 'showroom_finder', '', '' ),
				self::row( 'showroom_finder', '', '' ),
			)
		);

		preg_match_all( '/ id="([^"]+)"/', $html, $ids );
		$this->assertSame( array( 'showroom-finder', 'showroom-finder-3', 'showroom-finder-2', 'showroom-finder-4' ), $ids[1], 'Each row\'s first band keeps its planned id; a second band mints one in one pass, so no id repeats.' );
	}
}
