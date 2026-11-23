<?php
/**
 * Media + Text section snapshots (FLEET01; salt-contract
 * markup/media-text.json, rc.8) — one per meaningful variant: single mode
 * with a right-side media row and a button, the alternating zigzag, and a
 * text-only (imageless) row. Each upload has two of the contract's
 * candidate sub-sizes, so the srcset is the contract's.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';

final class MediaTextSectionSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_attachments_reset();

		$GLOBALS['salt_test_attachments'][61] = array(
			'src'    => 'https://example.test/uploads/studio.jpg',
			'width'  => 1200,
			'height' => 800,
			'sizes'  => array(
				'salt-640'  => array(
					'width'  => 640,
					'height' => 427,
					'url'    => 'https://example.test/uploads/studio-640.jpg',
				),
				'salt-1080' => array(
					'width'  => 1080,
					'height' => 720,
					'url'    => 'https://example.test/uploads/studio-1080.jpg',
				),
			),
			'alt'    => 'The studio workspace',
		);
		$GLOBALS['salt_test_attachments'][62] = array(
			'src'    => 'https://example.test/uploads/team.jpg',
			'width'  => 1200,
			'height' => 800,
			'sizes'  => array(
				'salt-640'  => array(
					'width'  => 640,
					'height' => 427,
					'url'    => 'https://example.test/uploads/team-640.jpg',
				),
				'salt-1080' => array(
					'width'  => 1080,
					'height' => 720,
					'url'    => 'https://example.test/uploads/team-1080.jpg',
				),
			),
			'alt'    => 'The team at work',
		);
	}

	/**
	 * Seed the FC row and render the media-text section.
	 *
	 * @param array<string,mixed> $fields     Row sub-field fixtures.
	 * @param bool                $is_primary Loop context flag.
	 * @return string
	 */
	private function render_media_text( array $fields, bool $is_primary = true ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'media-text',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'media-text',
			array(
				'index'      => 0,
				'is_primary' => $is_primary,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_media_text_single_mode_right_side_with_cta(): void {
		$this->assertMatchesHtmlSnapshot(
			'media-text-single-right-cta',
			$this->render_media_text(
				array(
					'mode' => 'single',
					'rows' => array(
						array(
							'image'     => 61,
							'heading'   => 'Inside the studio',
							'content'   => '<p>Where the work happens, <strong>every day</strong>.</p>',
							'mediaSide' => 'right',
							'buttons'   => array( salt_test_button( 'About us', 'https://example.test/about/', 'secondary' ) ),
						),
					),
				)
			)
		);
	}

	public function test_media_text_alternating_two_rows(): void {
		$this->assertMatchesHtmlSnapshot(
			'media-text-alternating-two-rows',
			$this->render_media_text(
				array(
					'mode' => 'alternating',
					'rows' => array(
						array(
							'image'   => 61,
							'heading' => 'Our process',
							'content' => '<p>Discovery first, always.</p>',
						),
						array(
							'image'   => 62,
							'heading' => 'Our people',
							'content' => '<p>A small senior team.</p>',
						),
					),
				)
			)
		);
	}

	public function test_media_text_text_only_row_non_primary(): void {
		$this->assertMatchesHtmlSnapshot(
			'media-text-text-only-row',
			$this->render_media_text(
				array(
					'mode' => 'single',
					'rows' => array(
						array(
							'heading' => 'No media here',
							'content' => '<p>A full-width text row.</p>',
						),
					),
				),
				false
			)
		);
	}
}
