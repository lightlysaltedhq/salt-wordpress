<?php
/**
 * Logo component snapshots (FLEET01) — light-only, light+dark (.has-dark swap) and
 * the styled text fallback. Fixture attachment URLs keep the markup
 * deterministic.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/nav.php';
require_once __DIR__ . '/../shims/media.php';

final class LogoSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_attachments_reset();
		$GLOBALS['salt_test_bloginfo'] = array( 'name' => 'Salt Fixture Site' );

		$GLOBALS['salt_test_attachments'][11] = array(
			'src'    => 'https://example.test/uploads/logo-light.png',
			'width'  => 320,
			'height' => 96,
		);
		$GLOBALS['salt_test_attachments'][12] = array(
			'src'    => 'https://example.test/uploads/logo-dark.png',
			'width'  => 320,
			'height' => 96,
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['salt_test_bloginfo'] );
		parent::tearDown();
	}

	public function test_logo_light_only(): void {
		$this->assertMatchesHtmlSnapshot(
			'logo-light-only',
			salt_get_component( 'logo', array( 'light_id' => 11 ) )
		);
	}

	public function test_logo_light_and_dark_sets_has_dark(): void {
		$this->assertMatchesHtmlSnapshot(
			'logo-light-dark',
			salt_get_component(
				'logo',
				array(
					'light_id' => 11,
					'dark_id'  => 12,
				)
			)
		);
	}

	public function test_logo_text_fallback(): void {
		$this->assertMatchesHtmlSnapshot(
			'logo-text-fallback',
			salt_get_component( 'logo', array( 'text' => 'Salt & Pepper Studio' ) )
		);
	}
}
