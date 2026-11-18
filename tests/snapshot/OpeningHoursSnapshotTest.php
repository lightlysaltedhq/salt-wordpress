<?php
/**
 * x-opening-hours snapshots (FLEET01) — fully deterministic markup (no live
 * status in the component).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class OpeningHoursSnapshotTest extends SaltSnapshotTestCase {

	/**
	 * A representative trading week: split Wednesday, appointment Saturday.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function week(): array {
		return array(
			array(
				'day'         => 'monday',
				'open'        => '09:00',
				'close'       => '17:00',
				'appointment' => false,
			),
			array(
				'day'         => 'wednesday',
				'open'        => '09:00',
				'close'       => '12:00',
				'appointment' => false,
			),
			array(
				'day'         => 'wednesday',
				'open'        => '14:00',
				'close'       => '17:00',
				'appointment' => false,
			),
			array(
				'day'         => 'saturday',
				'open'        => '',
				'close'       => '',
				'appointment' => true,
			),
		);
	}

	public function test_opening_hours_week_split(): void {
		$this->assertMatchesHtmlSnapshot(
			'opening-hours-week-split',
			salt_get_component( 'opening-hours', array( 'hours' => self::week() ) )
		);
	}

	public function test_opening_hours_compact(): void {
		$this->assertMatchesHtmlSnapshot(
			'opening-hours-compact',
			salt_get_component(
				'opening-hours',
				array(
					'hours'   => self::week(),
					'compact' => true,
				)
			)
		);
	}
}
