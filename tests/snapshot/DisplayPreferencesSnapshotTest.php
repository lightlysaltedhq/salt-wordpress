<?php
/**
 * x-display-preferences snapshots (FLEET01) — fully deterministic markup
 * (controls are passed in; the JS owns all state).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/display-preferences.php';

final class DisplayPreferencesSnapshotTest extends SaltSnapshotTestCase {

	public function test_display_preferences_full(): void {
		$this->assertMatchesHtmlSnapshot(
			'display-preferences-full',
			salt_get_component(
				'display-preferences',
				array(
					'controls' => array( 'font_size', 'contrast', 'grayscale', 'dark_mode', 'motion' ),
				)
			)
		);
	}

	public function test_display_preferences_subset(): void {
		$this->assertMatchesHtmlSnapshot(
			'display-preferences-subset',
			salt_get_component(
				'display-preferences',
				array(
					'controls'       => array( 'font_size', 'contrast', 'motion' ),
					'motion_default' => true,
				)
			)
		);
	}
}
