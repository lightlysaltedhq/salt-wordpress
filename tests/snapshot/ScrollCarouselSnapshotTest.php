<?php
/**
 * Scroll Carousel section snapshots (FLEET01) — the generic N-card track +
 * the single-card static variant.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class ScrollCarouselSnapshotTest extends SaltSnapshotTestCase {

	/**
	 * Render the scroll-carousel section for one seeded FC row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_carousel( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'scroll_carousel',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'scroll-carousel',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_scroll_carousel_generic(): void {
		$this->assertMatchesHtmlSnapshot(
			'scroll-carousel-generic',
			$this->render_carousel(
				array(
					'heading'   => 'Highlights',
					'eyebrow'   => 'In focus',
					'card_type' => 'generic',
					'cards'     => array(
						array(
							'title' => 'First card',
							'text'  => 'First text.',
						),
						array(
							'title' => 'Second card',
							'text'  => 'Second text.',
						),
						array(
							'title' => 'Third card',
							'text'  => 'Third text.',
						),
					),
				)
			)
		);
	}

	public function test_scroll_carousel_single_static(): void {
		$this->assertMatchesHtmlSnapshot(
			'scroll-carousel-single',
			$this->render_carousel(
				array(
					'card_type' => 'testimonial',
					'cards'     => array(
						array(
							'title' => 'Jane Doe',
							'text'  => 'Salt transformed our site.',
							'role'  => 'Founder, Acme',
						),
					),
				)
			)
		);
	}
}
