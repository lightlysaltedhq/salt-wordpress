<?php
/**
 * Locations section snapshots (FLEET01).
 *
 * Fixture statuses are chosen to be time-invariant so the snapshots never
 * flap: all-week 00:00–00:00 overnight spans => always "Open now";
 * all-week appointment_only => always "By appointment"; no hours =>
 * unknown (no pill).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class LocationsSnapshotTest extends SaltSnapshotTestCase {

	private const WEEK = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );

	/**
	 * Render the section for one seeded FC row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_locations( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'locations',
					'fields' => $fields,
				),
			)
		);
		the_row();

		ob_start();
		salt_render_section(
			'locations',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * Seed one office post + fields.
	 *
	 * @param int                 $id     Post ID.
	 * @param string              $title  Office name.
	 * @param array<string,mixed> $fields ACF field fixtures.
	 * @param int                 $order  Menu order.
	 * @return array<string,mixed> WP_Post props.
	 */
	private function office( int $id, string $title, array $fields, int $order = 0 ): array {
		foreach ( $fields as $key => $value ) {
			$GLOBALS['salt_test_fields'][ (string) $id ][ $key ] = $value;
		}
		return array(
			'ID'         => $id,
			'post_type'  => 'location',
			'post_title' => $title,
			'menu_order' => $order,
		);
	}

	public function test_locations_single_office_appointment(): void {
		$this->seed_posts(
			array(
				$this->office(
					101,
					'Bath office',
					array(
						'address'       => array(
							'line1'    => '1 High Street',
							'city'     => 'Bath',
							'postcode' => 'BA1 1AA',
						),
						'phone'         => '+44 1225 000000',
						'email'         => 'bath@example.test',
						'opening_hours' => array_map(
							static fn( string $day ): array => array(
								'day'              => $day,
								'open'             => '',
								'close'            => '',
								'appointment_only' => true,
							),
							self::WEEK
						),
					)
				),
			)
		);

		$this->assertMatchesHtmlSnapshot(
			'locations-single-office-appointment',
			$this->render_locations(
				array(
					'eyebrow' => 'Offices',
					'heading' => 'Find us',
				)
			)
		);
	}

	public function test_locations_multi_office_mixed_status(): void {
		$this->seed_posts(
			array(
				$this->office(
					101,
					'Bath office',
					array(
						'address'       => array(
							'line1' => '1 High Street',
							'city'  => 'Bath',
						),
						'phone'         => '+44 1225 000000',
						'opening_hours' => array_map(
							static fn( string $day ): array => array(
								'day'              => $day,
								'open'             => '00:00',
								'close'            => '00:00',
								'appointment_only' => false,
							),
							self::WEEK
						),
					)
				),
				$this->office(
					102,
					'Bristol office',
					array(
						'email' => 'bristol@example.test',
					),
					1
				),
			)
		);

		$this->assertMatchesHtmlSnapshot(
			'locations-multi-office-mixed-status',
			$this->render_locations( array( 'heading' => 'Our offices' ) )
		);
	}
}
