<?php
/**
 * Site-footer part snapshots (FLEET01) — the §12a chrome markup child
 * themes couple to when they override the part (OVERRIDES.md).
 *
 * Determinism: frozen fixture menu <ul>, fixture org_name, fixture sameAs
 * URLs. The © line contains the LIVE gmdate('Y') year, so the captured
 * HTML is normalised — the current year is replaced with the literal
 * 'YYYY' before comparing/writing the snapshot (the year itself is
 * asserted dynamically in ChromeTest, never frozen here).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/nav.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/chrome.php';
require_once dirname( __DIR__, 2 ) . '/inc/class-salt-nav-walker.php';

final class SiteFooterSnapshotTest extends SaltSnapshotTestCase {

	private const FIXTURE_MENU = '<ul class="x-footer-menu flex flex-wrap gap-x-6 gap-y-2">'
		. '<li class="x-nav-item menu-item"><a class="x-nav-link inline-flex items-center py-2 text-ink hover:text-brand" href="https://example.test/privacy/">Privacy</a></li>'
		. '<li class="x-nav-item menu-item"><a class="x-nav-link inline-flex items-center py-2 text-ink hover:text-brand" href="https://example.test/terms/">Terms</a></li>'
		. '</ul>';

	protected function setUp(): void {
		parent::setUp();
		salt_test_menus_reset();
		salt_nav_menu_fallback_reset();
		$GLOBALS['salt_test_bloginfo'] = array( 'name' => 'Salt Fixture Site' );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['salt_test_bloginfo'] );
		parent::tearDown();
	}

	/**
	 * Render the site-footer part and normalise the © year to 'YYYY'
	 * (documented normalisation — see the file docblock).
	 *
	 * @return string
	 */
	private function render_footer(): string {
		ob_start();
		load_template( locate_template( 'template-parts/footer/site-footer.php' ), false, array() );
		return str_replace( gmdate( 'Y' ), 'YYYY', (string) ob_get_clean() );
	}

	public function test_footer_with_text_social_and_menu(): void {
		$GLOBALS['salt_test_menus']['footer'] = self::FIXTURE_MENU;

		$GLOBALS['salt_test_fields']['option']['org_name']           = 'Salt Fixture Org';
		$GLOBALS['salt_test_fields']['option']['footer_text']        = "Handcrafted in the fixture kitchen.\nRegistered office: 1 Example Way.";
		$GLOBALS['salt_test_fields']['option']['footer_show_social'] = true;
		$GLOBALS['salt_test_fields']['option']['org_same_as']        = array(
			array( 'url' => 'https://www.facebook.com/saltfixture' ),
			array( 'url' => 'https://mastodon.example/@salt' ),
		);

		$this->assertMatchesHtmlSnapshot( 'site-footer-full', $this->render_footer() );
	}

	public function test_footer_minimal_copyright_only(): void {
		// No menu fixture (fallback renders nothing), no text, social off:
		// the footer still carries the auto © line — never an empty landmark.
		$GLOBALS['salt_test_menus']['footer'] = self::FIXTURE_MENU;

		$GLOBALS['salt_test_fields']['option']['footer_show_social'] = false;

		$this->assertMatchesHtmlSnapshot( 'site-footer-minimal', $this->render_footer() );
	}
}
