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
}
