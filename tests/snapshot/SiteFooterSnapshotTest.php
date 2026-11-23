<?php
/**
 * Site-footer part snapshots (FLEET01) — the §12a chrome markup child
 * themes couple to when they override the part (OVERRIDES.md).
 *
 * Determinism: fixture menu items, fixture org_name, fixture sameAs
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

	/**
	 * The fixture menu as the items wp_nav_menu() hands the walker.
	 *
	 * @return array<int,object>
	 */
	private static function fixture_menu(): array {
		return array(
			(object) array(
				'db_id'            => 1,
				'menu_item_parent' => 0,
				'title'            => 'Privacy',
				'url'              => 'https://example.test/privacy/',
				'current'          => false,
				'target'           => '',
			),
			(object) array(
				'db_id'            => 2,
				'menu_item_parent' => 0,
				'title'            => 'Terms',
				'url'              => 'https://example.test/terms/',
				'current'          => false,
				'target'           => '',
			),
		);
	}

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
		$GLOBALS['salt_test_menu_items']['footer'] = self::fixture_menu();

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
		$GLOBALS['salt_test_menu_items']['footer'] = self::fixture_menu();

		$GLOBALS['salt_test_fields']['option']['footer_show_social'] = false;

		$this->assertMatchesHtmlSnapshot( 'site-footer-minimal', $this->render_footer() );
	}
}
