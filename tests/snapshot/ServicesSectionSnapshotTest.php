<?php
/**
 * Services section snapshots (FLEET01) — one per meaningful variant:
 * grid (N items), featured (lead + remainder), single-item static.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/service.php';
require_once __DIR__ . '/../shims/media.php';

final class ServicesSectionSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_permalinks']    = array(
			5 => 'https://example.test/services/web-design/',
			6 => 'https://example.test/services/branding/',
			7 => 'https://example.test/services/seo/',
		);
		$GLOBALS['salt_test_archive_links'] = array( 'service' => 'https://example.test/services/' );

		$this->seed_posts(
			array(
				array(
					'ID'           => 5,
					'post_type'    => 'service',
					'post_title'   => 'Web Design',
					'post_excerpt' => 'Fast, accessible sites.',
					'menu_order'   => 1,
				),
				array(
					'ID'           => 6,
					'post_type'    => 'service',
					'post_title'   => 'Branding',
					'post_excerpt' => 'Identities that stick.',
					'menu_order'   => 2,
				),
				array(
					'ID'           => 7,
					'post_type'    => 'service',
					'post_title'   => 'SEO',
					'post_excerpt' => 'Be found.',
					'menu_order'   => 3,
				),
			)
		);
		// The summary is the excerpt (contract collections/services.json).
		$GLOBALS['salt_test_fields']['5'] = array( 'icon' => 'star' );
		$GLOBALS['salt_test_fields']['6'] = array( 'icon' => 'check' );
	}

	/**
	 * Render the services section for one seeded FC row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_services( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'services',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'services',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_services_grid(): void {
		$this->assertMatchesHtmlSnapshot(
			'services-grid',
			$this->render_services(
				array(
					'layout'   => 'grid',
					'columns'  => '3',
					'eyebrow'  => 'What we do',
					'heading'  => 'Our services',
					'view_all' => 1,
				)
			)
		);
	}

	public function test_services_featured(): void {
		$this->assertMatchesHtmlSnapshot(
			'services-featured',
			$this->render_services(
				array(
					'layout'  => 'featured',
					'columns' => '2',
					'heading' => 'Our services',
				)
			)
		);
	}

	public function test_services_single_item_static(): void {
		$this->seed_posts(
			array(
				array(
					'ID'           => 5,
					'post_type'    => 'service',
					'post_title'   => 'Web Design',
					'post_excerpt' => 'Fast, accessible sites.',
					'menu_order'   => 1,
				),
			)
		);

		$this->assertMatchesHtmlSnapshot(
			'services-single',
			$this->render_services(
				array(
					'layout'  => 'grid',
					'columns' => '3',
					'heading' => 'Our service',
				)
			)
		);
	}

	public function test_services_image(): void {
		$this->seed_posts(
			array(
				array(
					'ID'                => 5,
					'post_type'         => 'service',
					'post_title'        => 'Web Design',
					'post_excerpt'      => 'Fast, accessible sites.',
					'menu_order'        => 1,
					'salt_thumbnail_id' => 71,
				),
			)
		);
		$GLOBALS['salt_test_fields']['5']     = array( 'icon' => 'star' );
		$GLOBALS['salt_test_attachments'][71] = array(
			'src'    => 'https://example.test/uploads/web-design.jpg',
			'width'  => 800,
			'height' => 600,
			'srcset' => 'https://example.test/uploads/web-design-400.jpg 400w, https://example.test/uploads/web-design.jpg 800w',
			'alt'    => 'A wireframe on a desk',
		);

		// salt-contract 1.0.0-rc.7 (collections/services.json, display): the
		// card draws the featured image, and the icon only when there is none.
		$this->assertMatchesHtmlSnapshot(
			'services-image',
			$this->render_services(
				array(
					'layout'  => 'grid',
					'columns' => '3',
					'heading' => 'Our service',
				)
			)
		);
	}
}
