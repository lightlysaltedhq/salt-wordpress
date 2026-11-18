<?php
/**
 * Hero section snapshots (FLEET01) — one per meaningful variant:
 * full-bleed with the complete field set (the eager LCP image is asserted
 * in the snapshot), split two-column, and the text-only minimal variant.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';

final class HeroSectionSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_attachments_reset();
		$GLOBALS['salt_test_attachments'][41] = array(
			'src'    => 'https://example.test/uploads/hero.jpg',
			'width'  => 2000,
			'height' => 1200,
			'srcset' => 'https://example.test/uploads/hero-1000.jpg 1000w, https://example.test/uploads/hero.jpg 2000w',
			'alt'    => 'Studio workspace',
		);
	}

	/**
	 * Seed one hero FC row and render the section.
	 *
	 * @param array<string,mixed> $fields     Row fields.
	 * @param bool                $is_primary Loop context flag.
	 * @return string
	 */
	private function render_hero( array $fields, bool $is_primary = true ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'hero',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'hero',
			array(
				'index'      => 0,
				'is_primary' => $is_primary,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_hero_full_bleed_full_field_set(): void {
		$this->assertMatchesHtmlSnapshot(
			'hero-full-bleed',
			$this->render_hero(
				array(
					'variant'    => 'full-bleed',
					'eyebrow'    => 'Design studio',
					'heading'    => 'We build brands that last',
					'subheading' => 'Strategy, identity and digital for ambitious SMEs.',
					'buttons'    => array(
						salt_test_button( 'Start a project', 'https://example.test/contact/' ),
						salt_test_button( 'See our work', 'https://example.test/work/', 'secondary' ),
					),
					'settings'   => array( 'backgroundImage' => array( 'image' => 41 ) ),
				)
			)
		);
	}

	public function test_hero_split(): void {
		$this->assertMatchesHtmlSnapshot(
			'hero-split',
			$this->render_hero(
				array(
					'variant'    => 'split',
					'heading'    => 'A partner, not a supplier',
					'subheading' => 'We work alongside your team.',
					'image'      => 41,
					'buttons'    => array( salt_test_button( 'Get in touch', 'https://example.test/contact/' ) ),
				)
			)
		);
	}

	public function test_hero_minimal_text_only(): void {
		$this->assertMatchesHtmlSnapshot(
			'hero-minimal',
			$this->render_hero(
				array(
					'variant'    => 'minimal',
					'eyebrow'    => 'Careers',
					'heading'    => 'Join the team',
					'subheading' => 'We hire two roles a year. Make one yours.',
				)
			)
		);
	}
}
