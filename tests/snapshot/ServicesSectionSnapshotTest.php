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
					'ID'         => 5,
					'post_type'  => 'service',
					'post_title' => 'Web Design',
					'menu_order' => 1,
				),
				array(
					'ID'         => 6,
					'post_type'  => 'service',
					'post_title' => 'Branding',
					'menu_order' => 2,
				),
				array(
					'ID'         => 7,
					'post_type'  => 'service',
					'post_title' => 'SEO',
					'menu_order' => 3,
				),
			)
		);
		$GLOBALS['salt_test_fields']['5'] = array(
			'summary' => 'Fast, accessible sites.',
			'icon'    => 'star',
		);
		$GLOBALS['salt_test_fields']['6'] = array(
			'summary' => 'Identities that stick.',
			'icon'    => 'check',
		);
		$GLOBALS['salt_test_fields']['7'] = array( 'summary' => 'Be found.' );
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
					'ID'         => 5,
					'post_type'  => 'service',
					'post_title' => 'Web Design',
					'menu_order' => 1,
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
}
