<?php
/**
 * Site-header part snapshots (FLEET01) — the §12a chrome markup child
 * themes couple to when they override the part (OVERRIDES.md).
 *
 * Determinism: fixture attachment URLs, a frozen fixture menu <ul> (the
 * wp_nav_menu shim echoes it for the 'primary' location), fixture options.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/nav.php';
require_once __DIR__ . '/../shims/media.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/chrome.php';
require_once dirname( __DIR__, 2 ) . '/inc/class-salt-nav-walker.php';

final class SiteHeaderSnapshotTest extends SaltSnapshotTestCase {

	private const FIXTURE_MENU = '<ul class="x-nav-menu flex items-center gap-6">'
		. '<li class="x-nav-item menu-item"><a class="x-nav-link inline-flex items-center py-2 text-ink hover:text-brand" href="https://example.test/about/">About</a></li>'
		. '<li class="x-nav-item menu-item"><a class="x-nav-link inline-flex items-center py-2 text-ink hover:text-brand" href="https://example.test/contact/" aria-current="page">Contact</a></li>'
		. '</ul>';

	protected function setUp(): void {
		parent::setUp();
		salt_test_attachments_reset();
		salt_test_menus_reset();
		salt_nav_menu_fallback_reset();

		$GLOBALS['salt_test_bloginfo']         = array( 'name' => 'Salt Fixture Site' );
		$GLOBALS['salt_test_menus']['primary'] = self::FIXTURE_MENU;

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

	/**
	 * Render the site-header part with the current fixtures.
	 *
	 * @return string
	 */
	private function render_header(): string {
		ob_start();
		load_template( locate_template( 'template-parts/header/site-header.php' ), false, array() );
		return (string) ob_get_clean();
	}

	public function test_header_logo_light_only(): void {
		$GLOBALS['salt_test_fields']['option']['logo_light'] = 11;

		$this->assertMatchesHtmlSnapshot( 'site-header-logo-light', $this->render_header() );
	}

	public function test_header_logo_light_and_dark(): void {
		$GLOBALS['salt_test_fields']['option']['logo_light'] = 11;
		$GLOBALS['salt_test_fields']['option']['logo_dark']  = 12;

		$this->assertMatchesHtmlSnapshot( 'site-header-logo-light-dark', $this->render_header() );
	}

	public function test_header_no_logo_text_fallback(): void {
		$this->assertMatchesHtmlSnapshot( 'site-header-text-fallback', $this->render_header() );
	}

	public function test_header_sticky_with_cta(): void {
		$GLOBALS['salt_test_fields']['option']['logo_light']    = 11;
		$GLOBALS['salt_test_fields']['option']['header_sticky'] = true;
		$GLOBALS['salt_test_fields']['option']['header_cta']    = array(
			'title'  => 'Book a call',
			'url'    => 'https://example.test/book/',
			'target' => '',
		);

		$this->assertMatchesHtmlSnapshot( 'site-header-sticky-cta', $this->render_header() );
	}
}
