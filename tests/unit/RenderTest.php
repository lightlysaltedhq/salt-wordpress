<?php
/**
 * Render layer: args contract, H1 invariant, anchor registry, section
 * machinery, fallback behaviour (§5.3; A11Y01, ARCH05, EDIT02, FAIL01).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class RenderTest extends SaltTestCase {

	// -----------------------------------------------------------------------
	// salt_require_args (FAIL01).
	// -----------------------------------------------------------------------

	public function test_missing_required_arg_throws_in_debug_and_logs(): void {
		$this->expectException( InvalidArgumentException::class );

		try {
			salt_require_args(
				array(),
				array(
					'label' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'button'
			);
		} finally {
			$this->assertNotEmpty( $this->logs() );
			$this->assertStringContainsString( 'label', $this->logs()[0] );
		}
	}

	public function test_wrong_type_throws_in_debug(): void {
		$this->expectException( InvalidArgumentException::class );
		salt_require_args( array( 'count' => 'six' ), array( 'count' => array( 'type' => 'int' ) ), 'grid' );
	}

	public function test_production_path_logs_and_degrades_to_default(): void {
		add_filter( 'salt_is_debug', static fn(): bool => false );

		$args = salt_require_args(
			array( 'count' => 'six' ),
			array(
				'count' => array(
					'type'    => 'int',
					'default' => 3,
				),
				'label' => array(
					'type'     => 'string',
					'required' => true,
				),
			),
			'grid'
		);

		$this->assertSame( 3, $args['count'], 'Wrong type degrades to declared default.' );
		$this->assertSame( '', $args['label'], 'Missing required degrades to typed empty value.' );
		$this->assertCount( 2, $this->logs(), 'Both violations reached the log (never silent).' );
	}

	public function test_defaults_applied_for_absent_optional_args(): void {
		$args = salt_require_args(
			array(),
			array(
				'spacing' => array(
					'type'    => 'string',
					'default' => 'md',
				),
			),
			'section'
		);
		$this->assertSame( 'md', $args['spacing'] );
	}

	// -----------------------------------------------------------------------
	// H1 invariant (A11Y01).
	// -----------------------------------------------------------------------

	public function test_h1_claim_is_single_use(): void {
		$this->assertTrue( salt_claim_h1( 'section:hero' ) );
		$this->assertFalse( salt_claim_h1( 'section:cta' ), 'Second claim must be refused (at-most-one-H1).' );
		$this->assertTrue( salt_h1_claimed() );
		$this->assertNotEmpty( $this->logs(), 'Downgrade is logged.' );
	}

	public function test_document_h1_helper_emits_h1_then_defensive_h2(): void {
		ob_start();
		salt_the_document_h1( 'Archive: Services' );
		$first = ob_get_clean();

		ob_start();
		salt_the_document_h1( 'Should downgrade' );
		$second = ob_get_clean();

		$this->assertStringContainsString( '<h1>Archive: Services</h1>', $first );
		$this->assertStringContainsString( '<h2>', $second );
		$this->assertStringNotContainsString( '<h1>', $second );
	}

	public function test_render_state_reset_releases_the_claim(): void {
		salt_claim_h1( 'a' );
		salt_render_state_reset();
		$this->assertFalse( salt_h1_claimed() );
		$this->assertTrue( salt_claim_h1( 'b' ) );
	}

	// -----------------------------------------------------------------------
	// Anchor IDs (EDIT02).
	// -----------------------------------------------------------------------

	public function test_anchor_is_slugified(): void {
		$this->assertSame( 'our-team-2026', salt_anchor_id( ' Our #Team — 2026! ' ) );
	}

	public function test_anchor_collision_gets_suffix(): void {
		$this->assertSame( 'pricing', salt_anchor_id( 'Pricing' ) );
		$this->assertSame( 'pricing-2', salt_anchor_id( 'Pricing' ) );
		$this->assertSame( 'pricing-3', salt_anchor_id( 'pricing' ) );
	}

	public function test_a_landmark_anchor_becomes_its_section_form(): void {
		$this->assertSame( 'main-section', salt_anchor_id( 'Main' ), 'section#anchors: a landmark id becomes <anchor>-section.' );
		$this->assertSame( 'main-section-2', salt_anchor_id( 'main' ) );
		$this->assertSame( 'site-navigation-section', salt_anchor_id( 'Site navigation' ) );
	}

	public function test_empty_anchor_stays_empty(): void {
		$this->assertSame( '', salt_anchor_id( '  ' ) );
	}

	// -----------------------------------------------------------------------
	// salt_section + wrapper.
	// -----------------------------------------------------------------------

	public function test_section_body_receives_level_2_by_default(): void {
		$seen = null;

		ob_start();
		salt_section(
			array( 'name' => 'rich-text' ),
			function ( int $level ) use ( &$seen ): void {
				$seen = $level;
				echo '<p>body</p>';
			}
		);
		$html = ob_get_clean();

		$this->assertSame( 2, $seen );
		$this->assertStringContainsString( 'data-section="rich-text"', $html );
		$this->assertStringContainsString( '<p>body</p>', $html );
		$this->assertStringContainsString( 'bg-surface text-ink', $html );
	}

	public function test_primary_section_gets_h1_level_once(): void {
		$levels = array();
		$body   = function ( int $level ) use ( &$levels ): void {
			$levels[] = $level;
		};

		ob_start();
		salt_section(
			array(
				'name'       => 'hero',
				'is_primary' => true,
			),
			$body
		);
		salt_section(
			array(
				'name'       => 'cta',
				'is_primary' => true,
			),
			$body
		);
		ob_end_clean();

		$this->assertSame( array( 1, 2 ), $levels, 'First primary claims H1; second downgrades (A11Y01).' );
	}

	public function test_section_anchor_is_sanitised_and_rendered(): void {
		ob_start();
		salt_section(
			array(
				'name'   => 'faq',
				'anchor' => 'Common #Questions',
			),
			static function ( int $level ): void {
				echo '<p>x</p>';
			}
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="common-questions"', $html );
	}

	public function test_unknown_wrapper_values_fall_back_and_log(): void {
		ob_start();
		salt_section(
			array(
				'name'       => 'hero',
				'background' => 'hotpink',
			),
			static function ( int $level ): void {
				echo 'x';
			}
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'bg-surface text-ink', $html, 'Unknown background falls back to surface.' );
		$this->assertNotEmpty( $this->logs() );
	}

	// -----------------------------------------------------------------------
	// salt_render_section + fallback (ARCH05).
	// -----------------------------------------------------------------------

	public function test_missing_layout_renders_nothing_public_but_logs(): void {
		ob_start();
		salt_render_section( 'does-not-exist' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'salt: section unavailable', $html, 'Comment marker present for diagnostics.' );
		$this->assertStringNotContainsString( 'Section unavailable:', $html, 'No visible marker for the public.' );
		$this->assertStringContainsString( 'does-not-exist', implode( "\n", $this->logs() ), 'Missing slug reaches the error log.' );
	}

	public function test_missing_layout_shows_visible_marker_to_editors(): void {
		$GLOBALS['salt_test_user']['logged_in'] = true;
		$GLOBALS['salt_test_user']['caps']      = array( 'edit_posts' );

		ob_start();
		salt_render_section( 'does-not-exist' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Section unavailable', $html );
		$this->assertStringContainsString( 'does-not-exist', $html );
	}

	public function test_component_missing_file_logs_and_throws_in_debug(): void {
		$this->expectException( RuntimeException::class );
		salt_get_component( 'no-such-component' );
	}
}
