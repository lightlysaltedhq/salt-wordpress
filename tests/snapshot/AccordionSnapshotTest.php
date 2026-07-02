<?php
/**
 * x-accordion snapshots (FLEET01).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class AccordionSnapshotTest extends SaltSnapshotTestCase {

	public function test_accordion_two_items_one_open(): void {
		$this->assertMatchesHtmlSnapshot(
			'accordion-two-items-one-open',
			salt_get_component(
				'accordion',
				array(
					'items' => array(
						array(
							'title'   => 'What is the first question?',
							'content' => '<p>The first <strong>answer</strong>.</p>',
							'open'    => true,
						),
						array(
							'title'   => 'And the second?',
							'content' => '<p>The second answer.</p>',
						),
					),
					'name'  => 'faq',
				)
			)
		);
	}
}
