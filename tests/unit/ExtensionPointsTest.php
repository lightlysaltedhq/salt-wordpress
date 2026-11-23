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
		$this->assertStringContainsString( 'href="https://example.test/contact/"', $html );
		$this->assertStringNotContainsString( 'x-cta', $html, 'The parent view did not run.' );
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

		$this->assertSame( 1, substr_count( $html, 'data-track="rich-text-1"' ), 'The entry\'s band (the contract\'s since M4) …' );
		$this->assertSame( 1, substr_count( $html, 'data-section="rich-text"' ), '… nests the legacy template\'s own band, as before the declaration existed.' );
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

	public function test_a_childs_own_templates_keep_their_planned_anchors(): void {
		$fixture = self::fixture();
		$child   = $this->temp_child(
			array(
				'style.css'                         => (string) file_get_contents( $fixture . '/style.css' ),
				'salt-overrides.json'               => (string) file_get_contents( $fixture . '/salt-overrides.json' ),
				'template-parts/sections/stats.php' => (string) file_get_contents( $fixture . '/template-parts/sections/stats.php' ),
				'template-parts/sections/showroom-finder.php' => '<?php salt_section( salt_section_wrapper_args( "showroom-finder", $args ), static function ( int $level ): void { echo "<p>Find a showroom</p>"; } );',
			)
		);
		salt_test_use_child_theme( $child );
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'stats',
					'fields' => array(
						'heading'  => 'Our numbers',
						'settings' => array( 'anchorId' => 'numbers' ),
					),
				),
				array(
					'layout' => 'showroom_finder',
					'fields' => array( 'settings' => array( 'anchorId' => 'showrooms' ) ),
				),
			)
		);

		ob_start();
		get_template_part( 'template-parts/flexible-content' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'grain-stats', $html, 'The child\'s stats template drew.' );
		$this->assertStringContainsString( 'id="numbers"', $html, 'A tier-logic template keeps the editor\'s anchor.' );
		$this->assertStringContainsString( 'id="showrooms"', $html, 'A child-only layout keeps the editor\'s anchor.' );
		$this->assertStringNotContainsString( 'numbers-2', $html );
		$this->assertStringNotContainsString( 'showrooms-2', $html );
	}
}
