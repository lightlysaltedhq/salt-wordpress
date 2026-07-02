<?php
/**
 * Intro / Lead section snapshots (FLEET01) — one per meaningful variant:
 * the full left-aligned field set and the centred variant.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class IntroSectionSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Seed one intro FC row and render the section.
	 *
	 * @param array<string,mixed> $fields     Row fields.
	 * @param bool                $is_primary Loop context flag.
	 * @return string
	 */
	private function render_intro( array $fields, bool $is_primary = false ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'intro',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'intro',
			array(
				'index'      => 0,
				'is_primary' => $is_primary,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_intro_left_full_field_set(): void {
		$this->assertMatchesHtmlSnapshot(
			'intro-left-full',
			$this->render_intro(
				array(
					'eyebrow' => 'About us',
					'heading' => 'A studio of six, twenty years deep',
					'content' => '<p>We are an independent design studio. <strong>Small on purpose.</strong></p>',
					'align'   => 'left',
				)
			)
		);
	}

	public function test_intro_centred(): void {
		$this->assertMatchesHtmlSnapshot(
			'intro-center',
			$this->render_intro(
				array(
					'heading' => 'Why clients stay',
					'content' => '<p>Because the work works.</p>',
					'align'   => 'center',
				)
			)
		);
	}
}
