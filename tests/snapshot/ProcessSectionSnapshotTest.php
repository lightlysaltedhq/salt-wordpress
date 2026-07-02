<?php
/**
 * Process / Steps section snapshots (FLEET01) — timeline and cards
 * variants.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class ProcessSectionSnapshotTest extends SaltSnapshotTestCase {

	/**
	 * Render the process section for one seeded FC row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_process( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'process',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'process',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * Shared step fixtures.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function steps(): array {
		return array(
			array(
				'title' => 'Discover',
				'text'  => 'We learn about your goals.',
				'icon'  => 'check',
			),
			array(
				'title' => 'Design',
				'text'  => 'We shape the solution.',
			),
			array(
				'title' => 'Deliver',
				'text'  => 'We ship and iterate.',
			),
		);
	}

	public function test_process_timeline(): void {
		$this->assertMatchesHtmlSnapshot(
			'process-timeline',
			$this->render_process(
				array(
					'heading' => 'How we work',
					'eyebrow' => 'Our process',
					'layout'  => 'timeline',
					'steps'   => $this->steps(),
				)
			)
		);
	}

	public function test_process_cards(): void {
		$this->assertMatchesHtmlSnapshot(
			'process-cards',
			$this->render_process(
				array(
					'heading' => 'How we work',
					'layout'  => 'cards',
					'steps'   => $this->steps(),
				)
			)
		);
	}
}
