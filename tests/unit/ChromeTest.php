<?php
/**
 * Site-chrome service + nav walker (spec §12a): the header runtime CSS
 * seam (sticky on/off, logo-height validation), the CTA normaliser, the
 * © line, the social-row resolver, the nav fallback, and the walker's
 * depth cap / aria-current / disclosure markup (direct unit calls).
 *
 * The modules under test are not in the foundation bootstrap, so this
 * file requires them directly (ABSPATH-guarded and idempotent).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/nav.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/chrome.php';
require_once dirname( __DIR__, 2 ) . '/inc/class-salt-nav-walker.php';

final class ChromeTest extends SaltTestCase {

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

	// -----------------------------------------------------------------------
	// Logo height validation.
	// -----------------------------------------------------------------------

	/**
	 * Height validation cases: raw option value => expected px + log count.
	 *
	 * @return array<string,array{mixed,int,int}>
	 */
	public static function height_cases(): array {
		return array(
			'unset (null) is the silent default' => array( null, 48, 0 ),
			'empty string is the silent default' => array( '', 48, 0 ),
			'false is the silent default'        => array( false, 48, 0 ),
			'in-range int passes through'        => array( 64, 64, 0 ),
			'numeric string passes through'      => array( '120', 120, 0 ),
			'minimum boundary passes'            => array( 16, 16, 0 ),
			'maximum boundary passes'            => array( 200, 200, 0 ),
			'below minimum clamps + logs'        => array( 5, 16, 1 ),
			'above maximum clamps + logs'        => array( 999, 200, 1 ),
			'non-numeric junk defaults + logs'   => array( 'abc', 48, 1 ),
			'array junk defaults + logs'         => array( array( 48 ), 48, 1 ),
		);
	}

	#[DataProvider( 'height_cases' )]
	public function test_logo_height_value_validates( $raw, int $expected, int $logs ): void {
		$this->assertSame( $expected, salt_logo_height_value( $raw ) );
		$this->assertCount( $logs, $this->logs() );
	}

	// -----------------------------------------------------------------------
	// The header runtime CSS seam.
	// -----------------------------------------------------------------------

	public function test_runtime_css_always_emits_the_validated_logo_height(): void {
		$this->assertSame( ':root{--logo-height:48px}', salt_header_runtime_css() );

		$GLOBALS['salt_test_fields']['option']['logo_height'] = 72;
		$this->assertSame( ':root{--logo-height:72px}', salt_header_runtime_css() );
	}

	public function test_runtime_css_adds_scroll_padding_only_when_sticky(): void {
		$this->assertStringNotContainsString( '--salt-scroll-padding', salt_header_runtime_css() );

		$GLOBALS['salt_test_fields']['option']['header_sticky'] = true;
		$this->assertSame(
			':root{--logo-height:48px}:root{--salt-scroll-padding:calc(var(--logo-height,3rem) + 2.5rem)}',
			salt_header_runtime_css()
		);
	}

	public function test_runtime_css_clamps_out_of_range_height_and_logs(): void {
		$GLOBALS['salt_test_fields']['option']['logo_height'] = 9999;

		$this->assertSame( ':root{--logo-height:200px}', salt_header_runtime_css() );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'clamped', $this->logs()[0] );
	}

	// -----------------------------------------------------------------------
	// Header CTA normaliser.
	// -----------------------------------------------------------------------

	/**
	 * CTA cases: raw ACF link value => expected normalised args (or null).
	 *
	 * @return array<string,array{mixed,?array<string,string>,int}>
	 */
	public static function cta_cases(): array {
		return array(
			'non-array is no CTA'          => array( '', null, 0 ),
			'null is no CTA'               => array( null, null, 0 ),
			'empty url is no CTA'          => array(
				array(
					'title' => 'Book',
					'url'   => '',
				),
				null,
				0,
			),
			'full link passes through'     => array(
				array(
					'title'  => 'Book now',
					'url'    => 'https://example.test/book/',
					'target' => '',
				),
				array(
					'label'  => 'Book now',
					'url'    => 'https://example.test/book/',
					'target' => '',
				),
				0,
			),
			'_blank target is kept'        => array(
				array(
					'title'  => 'Portal',
					'url'    => 'https://portal.example.com/',
					'target' => '_blank',
				),
				array(
					'label'  => 'Portal',
					'url'    => 'https://portal.example.com/',
					'target' => '_blank',
				),
				0,
			),
			'invalid target dropped + log' => array(
				array(
					'title'  => 'Book',
					'url'    => 'https://example.test/book/',
					'target' => '_parent',
				),
				array(
					'label'  => 'Book',
					'url'    => 'https://example.test/book/',
					'target' => '',
				),
				1,
			),
			'missing title labels the url' => array(
				array( 'url' => 'https://example.test/book/' ),
				array(
					'label'  => 'https://example.test/book/',
					'url'    => 'https://example.test/book/',
					'target' => '',
				),
				0,
			),
		);
	}

	#[DataProvider( 'cta_cases' )]
	public function test_header_cta_normalises( $link, ?array $expected, int $logs ): void {
		$this->assertSame( $expected, salt_header_cta( $link ) );
		$this->assertCount( $logs, $this->logs() );
	}

	// -----------------------------------------------------------------------
	// © line — the year is asserted DYNAMICALLY (never frozen; the snapshot
	// suite normalises it to 'YYYY' instead).
	// -----------------------------------------------------------------------

	public function test_copyright_uses_org_name_and_the_current_year(): void {
		$GLOBALS['salt_test_fields']['option']['org_name'] = 'Salt Fixture Org';

		$this->assertSame( sprintf( '© %s Salt Fixture Org', gmdate( 'Y' ) ), salt_footer_copyright() );
	}

	public function test_copyright_falls_back_to_the_site_title(): void {
		$this->assertSame( sprintf( '© %s Salt Fixture Site', gmdate( 'Y' ) ), salt_footer_copyright() );
	}

	// -----------------------------------------------------------------------
	// Social row resolver.
	// -----------------------------------------------------------------------

	public function test_social_link_maps_known_hosts_to_platform_labels(): void {
		$link = salt_social_link( 'https://www.facebook.com/saltagency' );

		$this->assertSame( 'https://www.facebook.com/saltagency', $link['url'] );
		$this->assertSame( 'Facebook', $link['label'] );
		// The built-in set ships social glyphs since 3.1.0 — icon name resolves.
		$this->assertSame( 'facebook', $link['icon'] );
	}

	public function test_social_link_matches_known_host_subdomains(): void {
		$this->assertSame( 'LinkedIn', salt_social_link( 'https://uk.linkedin.com/company/salt' )['label'] );
	}

	public function test_social_link_labels_unknown_hosts_with_their_domain(): void {
		$link = salt_social_link( 'https://www.example-community.org/profile/salt' );

		$this->assertSame( 'example-community.org', $link['label'] );
		$this->assertSame( '', $link['icon'] );
	}

	public function test_social_link_drops_hostless_urls_loudly(): void {
		$this->assertNull( salt_social_link( 'not-a-url' ) );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'no host', $this->logs()[0] );
	}

	public function test_footer_social_links_reads_the_same_as_repeater(): void {
		$GLOBALS['salt_test_fields']['option']['org_same_as'] = array(
			array( 'url' => 'https://www.facebook.com/salt' ),
			array( 'url' => '' ), // Empty row — dropped loudly.
			array( 'url' => 'https://mastodon.example/@salt' ),
		);

		$links = salt_footer_social_links();

		$this->assertCount( 2, $links );
		$this->assertSame( 'Facebook', $links[0]['label'] );
		$this->assertSame( 'mastodon.example', $links[1]['label'] );
		$this->assertCount( 1, $this->logs() );
	}

	public function test_footer_social_links_is_empty_when_option_unset(): void {
		$this->assertSame( array(), salt_footer_social_links() );
	}

	// -----------------------------------------------------------------------
	// Nav fallback: renders nothing, logs once per location.
	// -----------------------------------------------------------------------

	public function test_nav_fallback_renders_nothing_and_logs_once_per_location(): void {
		$this->assertSame( '', salt_nav_menu_fallback( array( 'theme_location' => 'primary' ) ) );
		$this->assertSame( '', salt_nav_menu_fallback( array( 'theme_location' => 'primary' ) ) );
		$this->assertSame( '', salt_nav_menu_fallback( array( 'theme_location' => 'footer' ) ) );

		$this->assertCount( 2, $this->logs() );

		salt_nav_menu_fallback_reset();
		salt_nav_menu_fallback( array( 'theme_location' => 'primary' ) );
		$this->assertCount( 3, $this->logs() );
	}

	// -----------------------------------------------------------------------
	// Salt_Nav_Walker — direct unit calls (§12a).
	// -----------------------------------------------------------------------

	/**
	 * Build a menu-item fixture (the decorated-WP_Post shape the walker reads).
	 *
	 * @param array<string,mixed> $props Property overrides.
	 * @return WP_Post
	 */
	private function menu_item( array $props = array() ): WP_Post {
		return new WP_Post(
			array_merge(
				array(
					'ID'      => 10,
					'title'   => 'About',
					'url'     => 'https://example.test/about/',
					'classes' => array( 'menu-item' ),
					'current' => false,
					'target'  => '',
				),
				$props
			)
		);
	}

	public function test_walker_renders_a_plain_top_level_item(): void {
		$walker = new Salt_Nav_Walker();
		$html   = '';
		$walker->start_el( $html, $this->menu_item() );
		$walker->end_el( $html, $this->menu_item() );

		$this->assertStringContainsString( '<li class="x-nav-item menu-item">', $html );
		$this->assertStringContainsString( 'href="https://example.test/about/"', $html );
		$this->assertStringContainsString( '>About</a></li>', $html );
		$this->assertStringNotContainsString( 'x-nav-toggle', $html );
		$this->assertStringNotContainsString( 'aria-current', $html );
	}

	public function test_walker_marks_the_current_item(): void {
		$walker = new Salt_Nav_Walker();
		$html   = '';
		$walker->start_el( $html, $this->menu_item( array( 'current' => true ) ) );

		$this->assertStringContainsString( 'aria-current="page"', $html );
	}

	public function test_walker_hardens_blank_target_links(): void {
		$walker = new Salt_Nav_Walker();
		$html   = '';
		$walker->start_el( $html, $this->menu_item( array( 'target' => '_blank' ) ) );

		$this->assertStringContainsString( 'target="_blank" rel="noopener noreferrer"', $html );
	}

	public function test_walker_renders_disclosure_markup_for_submenu_parents(): void {
		$walker = new Salt_Nav_Walker();
		$parent = $this->menu_item(
			array(
				'ID'      => 42,
				'title'   => 'Services',
				'classes' => array( 'menu-item', 'menu-item-has-children' ),
			)
		);

		$html = '';
		$walker->start_el( $html, $parent );
		$walker->start_lvl( $html, 0 );
		$walker->start_el( $html, $this->menu_item( array( 'ID' => 43 ) ), 1 );
		$walker->end_el( $html, $this->menu_item( array( 'ID' => 43 ) ), 1 );
		$walker->end_lvl( $html, 0 );
		$walker->end_el( $html, $parent );

		// Disclosure button: inert until the future JS, correct pre-enhancement state.
		$this->assertStringContainsString( '<button type="button" class="x-nav-toggle', $html );
		$this->assertStringContainsString( 'aria-expanded="false"', $html );
		$this->assertStringContainsString( 'aria-controls="x-nav-submenu-42"', $html );
		$this->assertStringContainsString( 'data-salt-nav-toggle', $html );

		// Submenu id pairs with the toggle's aria-controls.
		$this->assertStringContainsString( '<ul id="x-nav-submenu-42" class="x-nav-submenu', $html );

		// No-JS reveal: focus-within arbitrary variant on the parent li.
		$this->assertStringContainsString( '[&amp;:focus-within&gt;.x-nav-submenu]:block', $html );

		// Depth-1 items use the sublink treatment.
		$this->assertStringContainsString( 'x-nav-sublink', $html );
	}

	public function test_walker_respects_the_has_children_walker_property(): void {
		$walker               = new Salt_Nav_Walker();
		$walker->has_children = true;

		$html = '';
		$walker->start_el( $html, $this->menu_item( array( 'ID' => 7 ) ) );

		$this->assertStringContainsString( 'aria-controls="x-nav-submenu-7"', $html );
	}

	public function test_walker_caps_submenus_at_one_level(): void {
		$walker = new Salt_Nav_Walker();

		$html = '';
		$walker->start_lvl( $html, 1 );
		$walker->end_lvl( $html, 1 );
		$walker->start_el( $html, $this->menu_item( array( 'ID' => 99 ) ), 2 );
		$walker->end_el( $html, $this->menu_item( array( 'ID' => 99 ) ), 2 );

		$this->assertSame( '', $html );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'deeper than one submenu level', $this->logs()[0] );
	}
}
