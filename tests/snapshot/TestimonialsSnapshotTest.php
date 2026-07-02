<?php
/**
 * Testimonials section snapshots (FLEET01) — one per meaningful variant:
 * single static card, grid, and the no-JS scroll-snap slider base.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/testimonial.php';

final class TestimonialsSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Seed $count deterministic testimonial posts + per-post fields.
	 *
	 * @param int $count Number of testimonials.
	 */
	private function seed_testimonials( int $count ): void {
		$fixtures = array(
			array( 'Alice Example', 'Salt transformed our site.', 'Founder', 'Acme Ltd', 5 ),
			array( 'Bob Sample', 'Fast, friendly, flawless.', 'Ops Lead', 'Beta Co', 0 ),
			array( 'Cara Test', 'Would recommend to anyone.', '', 'Gamma LLP', 4 ),
		);

		$posts = array();
		foreach ( array_slice( $fixtures, 0, $count ) as $i => $fixture ) {
			$id      = 101 + $i;
			$posts[] = array(
				'ID'         => $id,
				'post_type'  => 'testimonial',
				'post_title' => $fixture[0],
				'post_date'  => sprintf( '2026-01-%02d 00:00:00', 9 - $i ),
			);

			$GLOBALS['salt_test_fields'][ (string) $id ] = array(
				'quote'   => $fixture[1],
				'role'    => $fixture[2],
				'company' => $fixture[3],
				'rating'  => $fixture[4],
			);
		}

		$this->seed_posts( $posts );
	}

	/**
	 * Seed the FC row and render the section as the page's primary section.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string Rendered HTML.
	 */
	private function render_testimonials( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'testimonials',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'testimonials',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_testimonials_single(): void {
		$this->seed_testimonials( 1 );

		$this->assertMatchesHtmlSnapshot(
			'testimonials-single',
			$this->render_testimonials(
				array(
					'layout'  => 'slider',
					'heading' => 'What clients say',
					'eyebrow' => 'Testimonials',
				)
			)
		);
	}

	public function test_testimonials_grid(): void {
		$this->seed_testimonials( 3 );

		$this->assertMatchesHtmlSnapshot(
			'testimonials-grid',
			$this->render_testimonials(
				array(
					'layout'  => 'grid',
					'heading' => 'What clients say',
					'eyebrow' => 'Testimonials',
				)
			)
		);
	}

	public function test_testimonials_slider(): void {
		$this->seed_testimonials( 3 );

		$this->assertMatchesHtmlSnapshot(
			'testimonials-slider',
			$this->render_testimonials(
				array(
					'layout'  => 'slider',
					'heading' => 'What clients say',
				)
			)
		);
	}
}
