<?php
/**
 * Pricing section snapshots (FLEET01) — the canonical 3-plan grid with a
 * featured middle plan.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class PricingSectionSnapshotTest extends SaltSnapshotTestCase {

	/**
	 * Render the pricing section for one seeded FC row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_pricing( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'pricing',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'pricing',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_pricing_three_plans_featured(): void {
		$this->assertMatchesHtmlSnapshot(
			'pricing-three-plans-featured',
			$this->render_pricing(
				array(
					'eyebrow' => 'Pricing',
					'heading' => 'Simple plans, no surprises',
					'plans'   => array(
						array(
							'name'     => 'Starter',
							'price'    => '£29',
							'period'   => '/month',
							'features' => array(
								array( 'text' => 'One brochure site' ),
								array( 'text' => 'Email support' ),
							),
							'cta'      => array(
								'url'   => 'https://example.test/signup/starter/',
								'title' => 'Choose Starter',
							),
							'featured' => false,
						),
						array(
							'name'     => 'Growth',
							'price'    => '£59',
							'period'   => '/month',
							'features' => array(
								array( 'text' => 'Everything in Starter' ),
								array( 'text' => 'Quarterly content updates' ),
								array( 'text' => 'Priority support' ),
							),
							'cta'      => array(
								'url'   => 'https://example.test/signup/growth/',
								'title' => 'Choose Growth',
							),
							'featured' => true,
						),
						array(
							'name'     => 'Scale',
							'price'    => 'POA',
							'period'   => '',
							'features' => array(
								array( 'text' => 'Everything in Growth' ),
								array( 'text' => 'Dedicated account manager' ),
							),
							'cta'      => array(
								'url'   => 'https://example.test/contact/',
								'title' => 'Talk to us',
							),
							'featured' => false,
						),
					),
				)
			)
		);
	}
}
