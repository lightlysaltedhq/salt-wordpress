<?php
/**
 * x-section wrapper snapshots (FLEET01).
 *
 * The wrapper is the single highest-blast-radius markup in the theme —
 * every section on every fleet site renders through it. Any structural
 * change here fails CI and forces the semver decision.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class SectionSnapshotTest extends SaltSnapshotTestCase {

	/**
	 * Render a section with fixture args for snapshotting.
	 *
	 * @param array<string,mixed> $args Wrapper args.
	 * @return string
	 */
	private function render_section( array $args ): string {
		ob_start();
		salt_section(
			$args,
			static function ( int $level ): void {
				printf( '<h%1$d>Fixture heading</h%1$d><p>Fixture body copy.</p>', (int) $level );
			}
		);
		return (string) ob_get_clean();
	}

	public function test_section_defaults(): void {
		$this->assertMatchesHtmlSnapshot(
			'section-defaults',
			$this->render_section( array( 'name' => 'rich-text' ) )
		);
	}

	public function test_section_primary_hero_full_options(): void {
		$this->assertMatchesHtmlSnapshot(
			'section-hero-primary',
			$this->render_section(
				array(
					'name'       => 'hero',
					'is_primary' => true,
					'spacing'    => 'lg',
					'background' => 'inverse',
					'container'  => 'wide',
					'anchor'     => 'Welcome Section',
					'divider'    => true,
				)
			)
		);
	}

	public function test_section_narrow_alt(): void {
		$this->assertMatchesHtmlSnapshot(
			'section-narrow-alt',
			$this->render_section(
				array(
					'name'       => 'intro',
					'spacing'    => 'sm',
					'background' => 'surface-alt',
					'container'  => 'narrow',
				)
			)
		);
	}

	// -----------------------------------------------------------------------
	// Same-background adjacency collapse (4.1.0).
	// -----------------------------------------------------------------------

	public function test_adjacent_same_background_collapses_top_padding(): void {
		$first  = $this->render_section( array( 'name' => 'intro' ) );
		$second = $this->render_section( array( 'name' => 'rich-text' ) );

		$this->assertStringNotContainsString( 'pt-0', $first, 'First section keeps its full spacing.' );
		$this->assertMatchesHtmlSnapshot( 'section-adjacent-collapse', $second );
	}

	public function test_adjacent_background_change_keeps_the_full_gap(): void {
		$this->render_section( array( 'name' => 'intro' ) );
		$second = $this->render_section(
			array(
				'name'       => 'rich-text',
				'background' => 'surface-alt',
			)
		);

		$this->assertStringNotContainsString( 'pt-0', $second );
	}

	public function test_divider_is_a_boundary_no_collapse(): void {
		$this->render_section(
			array(
				'name'    => 'intro',
				'divider' => true,
			)
		);
		$second = $this->render_section( array( 'name' => 'rich-text' ) );

		$this->assertStringNotContainsString( 'pt-0', $second, 'A divider keeps the full gap between same-bg sections.' );
	}

	public function test_spacing_none_never_collapses(): void {
		$this->render_section( array( 'name' => 'intro' ) );
		$second = $this->render_section(
			array(
				'name'    => 'rich-text',
				'spacing' => 'none',
			)
		);

		$this->assertStringNotContainsString( 'pt-0', $second );
	}

	public function test_flush_top_forces_the_collapse(): void {
		$this->assertMatchesHtmlSnapshot(
			'section-flush-top',
			$this->render_section(
				array(
					'name'      => 'hero',
					'container' => 'full',
					'flush_top' => true,
				)
			)
		);
	}
}
