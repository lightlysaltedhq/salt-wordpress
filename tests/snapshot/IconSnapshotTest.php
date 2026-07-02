<?php
/**
 * x-icon snapshots (FLEET01).
 *
 * The icon wrapper markup (span + ARIA posture + size class) is consumed by
 * every section that renders an icon; a structural change here fails CI and
 * forces the semver decision.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once dirname( __DIR__, 2 ) . '/inc/services/svg.php';

final class IconSnapshotTest extends SaltSnapshotTestCase {

	public function test_icon_by_name_decorative(): void {
		$this->assertMatchesHtmlSnapshot(
			'icon-check-decorative',
			salt_get_component( 'icon', array( 'name' => 'check' ) )
		);
	}

	public function test_icon_with_label_role_img(): void {
		$this->assertMatchesHtmlSnapshot(
			'icon-phone-labelled',
			salt_get_component(
				'icon',
				array(
					'name'       => 'phone',
					'size'       => 'lg',
					'decorative' => false,
					'label'      => 'Call us',
				)
			)
		);
	}
}
