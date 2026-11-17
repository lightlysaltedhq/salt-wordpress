<?php
/**
 * FAQ section snapshots (FLEET01) — one per meaningful variant:
 * single item (static 1-state) and full N-state with eyebrow + heading.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/faq.php';

final class FaqSectionSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Seed published faq posts + their answer fields.
	 *
	 * @param array<int,array{id:int,title:string,answer:string}> $faqs FAQ fixtures.
	 * @return void
	 */
	private function seed_faqs( array $faqs ): void {
		$posts = array();
		foreach ( $faqs as $i => $faq ) {
			$posts[] = array(
				'ID'         => $faq['id'],
				'post_type'  => 'faq',
				'post_title' => $faq['title'],
				'menu_order' => $i,
			);

			$GLOBALS['salt_test_fields'][ (string) $faq['id'] ]['answer'] = $faq['answer'];
		}
		$this->seed_posts( $posts );
	}

	/**
	 * Render ONE faq section row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_faq_section( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'faq',
					'fields' => $fields,
				),
			)
		);
		have_rows( 'sections' );
		the_row();

		ob_start();
		salt_render_section(
			'faq',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_faq_single_item_static(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'What does the starter include?',
					'answer' => '<p>A parent theme, a child scaffold and a section library.</p>',
				),
			)
		);

		$this->assertMatchesHtmlSnapshot(
			'faq-single-item',
			$this->render_faq_section( array( 'heading' => 'Frequently asked questions' ) )
		);
	}

	public function test_faq_multiple_items_full(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'How long does a build take?',
					'answer' => '<p>Most sites launch in <strong>four to six weeks</strong>.</p>',
				),
				array(
					'id'     => 2,
					'title'  => 'Do you provide hosting?',
					'answer' => '<p>Yes — managed hosting is included in every care plan.</p>',
				),
				array(
					'id'     => 3,
					'title'  => 'Can I edit the site myself?',
					'answer' => '<p>Everything is editable through the block-free page builder.</p>',
				),
			)
		);

		$this->assertMatchesHtmlSnapshot(
			'faq-multiple-items',
			$this->render_faq_section(
				array(
					'eyebrow' => 'Support',
					'heading' => 'Frequently asked questions',
					'tone'    => 'surface-alt',
					'width'   => 'narrow',
				)
			)
		);
	}
}
