<?php
/**
 * Logo / Trust Strip section snapshots (FLEET01) — one per meaningful
 * variant: single plain logo (static 1-state) and the full "as featured
 * in" N-state (heading, greyscale, linked + unlinked logos, name-fed alt).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';

final class LogosSectionSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_attachments_reset();
	}

	/**
	 * Seed one resolvable attachment fixture.
	 *
	 * @param int    $id  Attachment ID.
	 * @param string $alt Stored attachment alt.
	 * @return void
	 */
	private function seed_attachment( int $id, string $alt = '' ): void {
		$GLOBALS['salt_test_attachments'][ $id ] = array(
			'src'    => 'https://example.test/logo-' . $id . '.png',
			'width'  => 240,
			'height' => 80,
			'srcset' => '',
			'alt'    => $alt,
		);
	}

	/**
	 * Render ONE logos section row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_logos_section( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'logos',
					'fields' => $fields,
				),
			)
		);
		have_rows( 'sections' );
		the_row();

		ob_start();
		salt_render_section(
			'logos',
			array(
				'index'      => 0,
				'is_primary' => false,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_logos_single_plain(): void {
		$this->seed_attachment( 1, 'Acme Co' );

		$this->assertMatchesHtmlSnapshot(
			'logos-single-plain',
			$this->render_logos_section(
				array(
					'items'     => array( array( 'image' => 1 ) ),
					'greyscale' => 0,
				)
			)
		);
	}

	public function test_logos_featured_in_full(): void {
		$this->seed_attachment( 1, '' );
		$this->seed_attachment( 2, 'The Gazette' );
		$this->seed_attachment( 3, '' );

		$this->assertMatchesHtmlSnapshot(
			'logos-featured-in-full',
			$this->render_logos_section(
				array(
					'heading'   => 'As featured in',
					'greyscale' => 1,
					'items'     => array(
						array(
							'image' => 1,
							'url'   => 'https://press.example.org/feature',
							'name'  => 'The Press',
						),
						array(
							'image' => 2,
							'url'   => 'https://gazette.example.org/story',
							'name'  => 'The Gazette',
						),
						array(
							'image' => 3,
							'name'  => 'Acme Co',
						),
					),
					'tone'      => 'surface-alt',
					'spacing'   => 'sm',
				)
			)
		);
	}
}
