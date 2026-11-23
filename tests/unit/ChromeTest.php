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

	public function test_runtime_css_emits_the_logo_height_only_when_set(): void {
		$this->assertSame( '', salt_header_runtime_css(), 'No height set: salt.css\'s 2rem applies.' );

		$GLOBALS['salt_test_fields']['option']['logo_height'] = 72;
		$this->assertSame( '.salt-logo{--logo-height:72px}', salt_header_runtime_css() );

		// An emptied field keeps the 48px default, so no existing site changes.
		$GLOBALS['salt_test_fields']['option']['logo_height'] = '';
		$this->assertSame( '.salt-logo{--logo-height:48px}', salt_header_runtime_css() );
	}

	public function test_runtime_css_adds_scroll_padding_only_when_sticky(): void {
		$GLOBALS['salt_test_fields']['option']['header_sticky'] = true;
		$this->assertSame( ':root{--salt-scroll-padding:calc(32px + 2.5rem)}', salt_header_runtime_css() );

		$GLOBALS['salt_test_fields']['option']['logo_height'] = 48;
		$this->assertSame(
			'.salt-logo{--logo-height:48px}:root{--salt-scroll-padding:calc(48px + 2.5rem)}',
			salt_header_runtime_css()
		);
	}

	public function test_runtime_css_clamps_out_of_range_height_and_logs(): void {
		$GLOBALS['salt_test_fields']['option']['logo_height'] = 9999;

		$this->assertSame( '.salt-logo{--logo-height:200px}', salt_header_runtime_css() );
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
	// Salt_Nav_Walker reads a menu into a tree (§12a).
	// -----------------------------------------------------------------------

	/**
	 * A menu item as wp_nav_menu() hands it to the walker.
	 *
	 * @param int                 $id     Item ID.
	 * @param int                 $parent Parent item ID.
	 * @param array<string,mixed> $props  Overrides.
	 * @return object
	 */
	private static function item( int $id, int $parent = 0, array $props = array() ): object {
		return (object) array_merge(
			array(
				'db_id'            => $id,
				'menu_item_parent' => $parent,
				'title'            => 'Item ' . $id,
				'url'              => 'https://example.test/' . $id . '/',
				'current'          => false,
				'target'           => '',
			),
			$props
		);
	}

	public function test_walker_reads_one_submenu_level_and_drops_deeper_items(): void {
		$GLOBALS['salt_test_menu_items']['primary'] = array(
			self::item( 1 ),
			self::item( 2, 0, array( 'url' => '#' ) ),
			self::item( 3, 2, array( 'current' => true ) ),
			self::item( 4, 3 ),
			self::item( 5, 0, array( 'target' => '_blank' ) ),
		);

		$tree = Salt_Nav_Walker::salt_menu( 'primary' );

		$this->assertCount( 3, $tree );
		$this->assertNull( $tree[1]['href'], 'A # address is no address: the label draws as a span.' );
		$this->assertSame( array( 'Item 3' ), array_column( $tree[1]['children'], 'label' ) );
		$this->assertTrue( $tree[1]['children'][0]['current'] );
		$this->assertSame(
			array(
				'target' => '_blank',
				'rel'    => 'noopener noreferrer',
			),
			$tree[2]['atts']
		);
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'deeper than one submenu level', $this->logs()[0] );
	}

	public function test_header_nav_marks_the_current_section_and_numbers_submenus_from_one(): void {
		$GLOBALS['salt_test_menu_items']['primary'] = array(
			self::item( 1 ),
			self::item( 2, 0, array( 'url' => '' ) ),
			self::item( 3, 2, array( 'current' => true ) ),
		);

		$html = salt_header_nav_html( Salt_Nav_Walker::salt_menu( 'primary' ), salt_chrome_labels( 'header' ) );

		$this->assertStringContainsString( '<li class="salt-nav__item" data-current-section><span class="salt-nav__link" tabindex="0">Item 2</span>', $html );
		$this->assertStringContainsString( 'aria-controls="site-header__submenu-2"', $html );
		$this->assertStringContainsString( 'id="site-header__submenu-2"', $html );
		$this->assertStringContainsString( 'aria-current="page">Item 3</a>', $html );
	}

	public function test_walker_keeps_an_editors_link_attributes_and_the_core_menu_filters(): void {
		$GLOBALS['salt_test_menu_items']['primary'] = array(
			self::item(
				1,
				0,
				array(
					'xfn'        => 'me',
					'attr_title' => 'Our profile',
					'target'     => '_blank',
					'classes'    => array( 'editor-class' ),
				)
			),
			self::item( 2 ),
			self::item( 3, 2 ),
		);
		add_filter(
			'nav_menu_link_attributes',
			static function ( array $atts, $item, $args, int $depth ): array {
				$atts['data-depth'] = (string) $depth;
				$atts['class']      = 'plugin-class';
				return $atts;
			},
			10,
			4
		);
		add_filter( 'nav_menu_item_title', static fn( string $title, $item ): string => 3 === $item->db_id ? '<em>' . $title . '</em>' : $title, 10, 2 );

		$html = (string) wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'echo'           => false,
				'walker'         => new Salt_Nav_Walker(),
			)
		);

		// walk() draws the bar's items, not nothing.
		$this->assertStringStartsWith( '<li class="salt-nav__item"><a class="salt-nav__link" href="https://example.test/1/" title="Our profile" target="_blank" rel="me" data-depth="0">Item 1</a></li>', $html );
		$this->assertStringContainsString( 'aria-label="Show submenu for Item 2"', $html );
		$this->assertStringContainsString( '<a class="salt-nav__sublink" href="https://example.test/3/" data-depth="1"><em>Item 3</em></a>', $html );
		$this->assertStringNotContainsString( 'class-', $html );
		$this->assertStringNotContainsString( 'plugin-class', $html, 'The contract fixes the class list.' );
	}

	public function test_walker_on_another_location_never_reuses_the_headers_submenu_ids(): void {
		$items                                      = array( self::item( 1 ), self::item( 2, 1 ) );
		$GLOBALS['salt_test_menu_items']['primary'] = $items;
		$GLOBALS['salt_test_menu_items']['footer']  = $items;
		$walk                                       = static fn( string $location ): string => (string) wp_nav_menu(
			array(
				'theme_location' => $location,
				'echo'           => false,
				'walker'         => new Salt_Nav_Walker(),
			)
		);

		$header = $walk( 'primary' );
		$footer = $walk( 'footer' );

		// The header's ids stay the contract's (SC-012).
		$this->assertStringContainsString( 'id="site-header__submenu-1"', $header );
		$this->assertStringContainsString( 'aria-controls="site-header__submenu-1"', $header );
		$this->assertStringContainsString( 'id="site-menu-footer__submenu-1"', $footer );
		$this->assertStringContainsString( 'aria-controls="site-menu-footer__submenu-1"', $footer );
		$this->assertStringNotContainsString( 'site-header__', $footer );
	}

	public function test_footer_columns_group_titled_parents_and_runs_of_flat_links(): void {
		$GLOBALS['salt_test_menu_items']['footer'] = array(
			self::item( 1 ),
			self::item( 2 ),
			self::item( 3, 0, array( 'title' => 'Studio' ) ),
			self::item( 4, 3 ),
			self::item( 5 ),
		);

		$columns = salt_footer_columns( Salt_Nav_Walker::salt_menu( 'footer' ) );

		$this->assertSame( array( null, 'Studio', null ), array_column( $columns, 'title' ) );
		$this->assertSame( array( 'Item 1', 'Item 2' ), array_column( $columns[0]['links'], 'label' ) );
		$this->assertSame( array( 'Item 4' ), array_column( $columns[1]['links'], 'label' ) );
		$this->assertSame( array( 'Item 5' ), array_column( $columns[2]['links'], 'label' ) );
	}

	// -----------------------------------------------------------------------
	// The header phone and the footer's consent control (ruling C).
	// -----------------------------------------------------------------------

	public function test_header_phone_shows_the_company_phone_only_when_switched_on(): void {
		$this->assertNull( salt_header_phone() );

		$GLOBALS['salt_test_fields']['option']['company_phone'] = '+44 1935 123456';
		$this->assertNull( salt_header_phone(), 'Off by default: a phone in Company & Schema alone draws nothing.' );

		$GLOBALS['salt_test_fields']['option']['header_show_phone'] = true;
		$this->assertSame(
			array(
				'text' => '+44 1935 123456',
				'tel'  => 'tel:+441935123456',
			),
			salt_header_phone()
		);

		add_filter( 'salt_header_phone', static fn() => null );
		$this->assertNull( salt_header_phone(), 'A site can turn the header phone off.' );
	}

	public function test_chrome_settings_apply_and_their_filters_override_them(): void {
		$this->assertNull( salt_theme_toggle_placement() );
		$this->assertSame( 'surface-alt', salt_footer_tone() );
		$this->assertFalse( salt_footer_show_logo() );

		$GLOBALS['salt_test_fields']['option']['theme_toggle_placement'] = 'footer';
		$GLOBALS['salt_test_fields']['option']['footer_tone']            = 'brand-tint';
		$GLOBALS['salt_test_fields']['option']['footer_show_logo']       = true;
		$this->assertSame( 'footer', salt_theme_toggle_placement() );
		$this->assertSame( 'brand-tint', salt_footer_tone() );
		$this->assertTrue( salt_footer_show_logo() );

		add_filter( 'salt_theme_toggle_placement', static fn() => 'header' );
		add_filter( 'salt_footer_tone', static fn() => 'surface-inverse' );
		add_filter( 'salt_footer_show_logo', static fn() => false );
		$this->assertSame( 'header', salt_theme_toggle_placement() );
		$this->assertSame( 'surface-inverse', salt_footer_tone() );
		$this->assertFalse( salt_footer_show_logo() );
	}

	public function test_chrome_settings_ignore_values_outside_their_choices(): void {
		$GLOBALS['salt_test_fields']['option']['theme_toggle_placement'] = 'sidebar';
		$GLOBALS['salt_test_fields']['option']['footer_tone']            = 'neon';

		$this->assertNull( salt_theme_toggle_placement() );
		$this->assertSame( 'surface-alt', salt_footer_tone() );
	}

	/**
	 * Render the site-footer part.
	 *
	 * @return string
	 */
	private function render_footer(): string {
		ob_start();
		load_template( locate_template( 'template-parts/footer/site-footer.php' ), false, array() );
		return (string) ob_get_clean();
	}

	public function test_footer_draws_no_consent_control_without_a_consent_plugin(): void {
		require_once __DIR__ . '/../shims/consent-api.php';
		$GLOBALS['salt_test_wp_consent_type'] = '';
		// A site's filter cannot draw it without a plugin: Save would have
		// nothing to write through.
		add_filter( 'salt_consent_control', static fn(): bool => true, 20 );

		$html = $this->render_footer();

		$this->assertStringNotContainsString( 'salt-footer__consent', $html );
		$this->assertStringNotContainsString( 'data-salt-consent-preferences', $html );
		$this->assertArrayNotHasKey( 'consent', salt_chrome_script_data() );
	}

	public function test_footer_draws_the_disabled_consent_control_with_a_consent_plugin(): void {
		require_once __DIR__ . '/../shims/consent-api.php';
		$GLOBALS['salt_test_wp_consent_type'] = 'optin';

		$html = $this->render_footer();
		unset( $GLOBALS['salt_test_wp_consent_type'] );

		$this->assertStringContainsString( 'data-salt-consent-preferences="" aria-disabled="true">Cookie preferences</button>', $html );
		$this->assertStringNotContainsString( '<dialog', $html, 'Only script opens the panel.' );
	}

	public function test_consent_boxes_that_share_an_api_category_say_so(): void {
		require_once __DIR__ . '/../shims/consent-api.php';
		$GLOBALS['salt_test_wp_consent_type'] = 'optin';

		$data = salt_chrome_script_data();
		unset( $GLOBALS['salt_test_wp_consent_type'] );

		// The contract's three boxes and ids, as the server draws them.
		// Embeds and marketing are both the API's marketing, so script keeps
		// them ticked together and each one's note says so.
		$categories = $data['consent']['categories'];
		$this->assertSame( array( 'analytics', 'embeds', 'marketing' ), array_column( $categories, 'id' ) );
		$this->assertSame( array( 'statistics', 'marketing', 'marketing' ), array_column( $categories, 'api' ) );
		$this->assertSame(
			array( '', 'Shares one setting with Marketing: ticking one ticks both.', 'Shares one setting with Embedded content: ticking one ticks both.' ),
			array_column( $categories, 'note' )
		);
	}

	public function test_consent_boxes_carry_no_note_once_a_remap_separates_them(): void {
		add_filter( 'salt_consent_api_category', static fn( string $api, string $salt ): string => 'embeds' === $salt ? 'preferences' : $api, 20, 2 );

		$choices = salt_consent_choices( salt_consent_panel()['categories'] );

		$this->assertSame( array( 'statistics', 'preferences', 'marketing' ), array_column( $choices, 'api' ) );
		$this->assertSame( array( '', '', '' ), array_column( $choices, 'note' ) );
	}
}
