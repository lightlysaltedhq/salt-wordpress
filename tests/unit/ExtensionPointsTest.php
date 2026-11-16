<?php
/**
 * Acceptance for ID-3613: tests/fixtures/themes/grain-fixture is a child
 * theme modelled on grain-wordpress that swaps the call-to-action section's
 * view (layout cta) and declares stats replaced (tier "logic"). Both are
 * honoured without touching the parent; undeclared sections render as
 * before, and an UNDECLARED replacement still fails exactly as before.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/SaltOverridesTestCase.php';

final class ExtensionPointsTest extends SaltOverridesTestCase {

	// -----------------------------------------------------------------
	// Acceptance: the fixture child's view swap and logic takeover.
	// -----------------------------------------------------------------

	public function test_acceptance_the_declared_view_swap_draws_the_parent_props(): void {
		salt_test_use_child_theme( self::fixture() );
		$this->assertSame( 'view', salt_override_tier( 'cta' ) );

		$this->seed_row(
			'cta',
			array(
				'heading' => 'Ready?',
				'buttons' => array(
					array(
						'label' => 'Talk to us',
						'url'   => 'https://example.test/contact/',
					),
				),
			)
		);
		$html = $this->render(
			'cta',
			self::fixture() . '/template-parts/sections/cta.php',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);

		$this->assertStringContainsString( 'data-section="cta"', $html, 'Inside the parent wrapper.' );
		$this->assertStringContainsString( '<div class="grain-cta">', $html, 'The child view drew it.' );
		$this->assertStringContainsString( '<h1>Ready?</h1>', $html, 'From the parent resolver\'s props and plan.' );
		$this->assertStringContainsString( 'href="https://example.test/contact/"', $html );
		$this->assertStringNotContainsString( 'x-cta', $html, 'The parent view did not run.' );
	}

	public function test_acceptance_the_declared_logic_section_is_the_childs_whole_template(): void {
		salt_test_use_child_theme( self::fixture() );
		$this->assertSame( 'logic', salt_override_tier( 'stats' ) );

		// An item with no number: the PARENT resolver would log "has no number"
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
		$this->assertSame( 1, substr_count( $html, 'data-section="stats"' ), 'One band: the child\'s, not one nested in the parent\'s.' );
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
		$this->seed_row( 'stats', array( 'items' => array( array( 'number' => '9' ) ) ) );

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
		$this->seed_row( 'rich_text', array( 'heading' => 'Legacy' ) );

		$html = $this->render( 'rich-text', $view );

		$this->assertSame( 2, substr_count( $html, 'data-section="rich-text"' ), 'Nested band, exactly as before the declaration existed.' );
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
}
