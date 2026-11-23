<?php
/**
 * Call to Action SECTION snapshots (FLEET01) — the canonical brand-tint
 * band with two buttons and the site's arrow, in the contract's markup
 * (salt-contract markup/call-to-action.json, rc.8). The x-cta component,
 * which this section no longer draws, keeps its own snapshots in
 * CtaSnapshotTest.php.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class CallToActionSectionSnapshotTest extends SaltSnapshotTestCase {

	/**
	 * Render the call-to-action section for one seeded FC row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_cta( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'call-to-action',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'call-to-action',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_cta_section_brand_tint_two_buttons(): void {
		$this->assertMatchesHtmlSnapshot(
			'call-to-action-section-brand-tint',
			$this->render_cta(
				array(
					'heading' => 'Ready to start your project?',
					'body'    => '<p>Tell us where you are and where you want to be — we will map the route.</p>',
					'buttons' => array(
						salt_test_button( 'Get in touch', 'https://example.test/contact/', 'primary' ),
						salt_test_button( 'See our work', 'https://example.test/work/', 'secondary' ),
					),
				)
			)
		);
	}
}
