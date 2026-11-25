<?php
/**
 * Section band snapshots (FLEET01): salt_section(), the contract's band
 * (salt-contract markup/section.json).
 *
 * The band is the single highest-blast-radius markup in the theme —
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
					'tone'       => 'surface-inverse',
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
					'name'      => 'intro',
					'spacing'   => 'sm',
					'tone'      => 'surface-alt',
					'container' => 'narrow',
				)
			)
		);
	}

	// -----------------------------------------------------------------------
	// Adjacency collapse (section#adjacent-collapse).
	// -----------------------------------------------------------------------

	public function test_adjacent_same_background_collapses_top_padding(): void {
		$first  = $this->render_section( array( 'name' => 'intro' ) );
		$second = $this->render_section( array( 'name' => 'rich-text' ) );

		$this->assertStringNotContainsString( 'data-collapse-top', $first, 'First section keeps its full spacing.' );
		$this->assertMatchesHtmlSnapshot( 'section-adjacent-collapse', $second );
	}

	public function test_adjacent_background_change_keeps_the_full_gap(): void {
		$this->render_section( array( 'name' => 'intro' ) );
		$second = $this->render_section(
			array(
				'name' => 'rich-text',
				'tone' => 'surface-alt',
			)
		);

		$this->assertStringNotContainsString( 'data-collapse-top', $second );
	}

	public function test_divider_is_a_boundary_no_collapse(): void {
		$this->render_section( array( 'name' => 'intro' ) );
		$second = $this->render_section(
			array(
				'name'    => 'rich-text',
				'divider' => true,
			)
		);

		$this->assertStringNotContainsString( 'data-collapse-top', $second, 'A divider keeps the full gap between same-tone sections.' );
	}

	public function test_a_different_dark_tone_keeps_the_full_gap(): void {
		$this->render_section( array( 'name' => 'intro' ) );
		$second = $this->render_section(
			array(
				'name'      => 'rich-text',
				'tone_dark' => 'surface-inverse',
			)
		);

		$this->assertStringNotContainsString( 'data-collapse-top', $second );
	}

	public function test_spacing_none_never_collapses(): void {
		$this->render_section( array( 'name' => 'intro' ) );
		$second = $this->render_section(
			array(
				'name'    => 'rich-text',
				'spacing' => 'none',
			)
		);

		$this->assertStringNotContainsString( 'data-collapse-top', $second );
	}
}
