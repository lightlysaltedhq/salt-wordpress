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

		$this->assertStringContainsString( 'data-section="call-to-action"', $html, 'Inside the parent wrapper.' );
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

	/**
	 * One testimonial the resolver can draw.
	 *
	 * @return void
	 */
	private function seed_testimonial(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 301,
					'post_type'  => 'testimonial',
					'post_title' => 'Dana Example',
				),
			)
		);
		$GLOBALS['salt_test_fields']['301'] = array( 'quote' => 'Grid or slider, still me.' );
	}

	public function test_carousel_logic_takes_over_slider_testimonials_only(): void {
		salt_test_use_child_theme(
			$this->temp_child(
				array(
					'salt-overrides.json' => self::json(
						self::declaration(
							array(
								'sections' => array(
									self::entry(
										array(
											'section' => 'carousel',
											'tier'    => 'logic',
											'files'   => array( 'template-parts/sections/scroll-carousel.php' ),
										)
									),
								),
							)
						)
					),
					'template-parts/sections/scroll-carousel.php' => '<?php echo "<p>child carousel</p>";',
				)
			)
		);
		$this->seed_testimonial();

		$this->seed_row( 'testimonials', array( 'layout' => 'grid' ) );
		$this->assertStringContainsString( 'Grid or slider, still me.', $this->render( 'testimonials', self::parent_view( 'testimonials' ) ), 'A grid row is collection-showcase: the parent draws it.' );

		$this->seed_row( 'testimonials', array( 'layout' => 'slider' ) );
		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( 'has no template-parts/sections/testimonials.php' );
		$this->render( 'testimonials', self::parent_view( 'testimonials' ) );
	}

	public function test_collection_showcase_logic_with_carousel_view_splits_testimonials_by_row(): void {
		$dir = $this->temp_child(
			array(
				'salt-overrides.json'                      => self::json(
					self::declaration(
						array(
							'sections' => array(
								self::entry(
									array(
										'section' => 'collection-showcase',
										'tier'    => 'logic',
										'files'   => array( 'template-parts/sections/testimonials.php' ),
									)
								),
								self::entry(
									array(
										'section' => 'carousel',
										'files'   => array( 'template-parts/sections/testimonials.php' ),
									)
								),
							),
						)
					)
				),
				'template-parts/sections/testimonials.php' => '<?php if ( isset( $args["props"] ) ) { echo "<p>as view</p>"; } else { salt_section( array( "name" => "testimonials" ), static function ( int $level ): void { echo "<p>as whole</p>"; } ); }',
			)
		);
		salt_test_use_child_theme( $dir );
		$this->seed_testimonial();
		$view = $dir . '/template-parts/sections/testimonials.php';

		$this->seed_row( 'testimonials', array( 'layout' => 'grid' ) );
		$grid = $this->render( 'testimonials', $view );
		$this->assertStringContainsString( '<p>as whole</p>', $grid, 'Grid row: collection-showcase, taken over.' );
		$this->assertSame( 1, substr_count( $grid, 'data-section="testimonials"' ) );

		$this->seed_row( 'testimonials', array( 'layout' => 'slider' ) );
		$this->assertStringContainsString( '<p>as view</p>', $this->render( 'testimonials', $view ), 'Slider row: carousel, a view swap only.' );
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
}
