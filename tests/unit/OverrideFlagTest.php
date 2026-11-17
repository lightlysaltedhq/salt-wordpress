<?php
/**
 * The update flag (ID-3613): which declared sections need re-verifying,
 * and the admin notice that shows them on the Themes and Updates screens.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/SaltOverridesTestCase.php';
require_once __DIR__ . '/../shims/screen.php';

final class OverrideFlagTest extends SaltOverridesTestCase {

	// -----------------------------------------------------------------
	// The update flag.
	// -----------------------------------------------------------------

	/**
	 * Flag rows for the active child theme.
	 *
	 * @return array<int, array{section: string, message: string}>
	 */
	private function flag(): array {
		$loaded = salt_overrides_load();
		return salt_override_flag_items( $loaded, salt_override_child_section_views( $loaded['child_dir'] ) );
	}

	private function flag_for( array $declaration, array $files = array() ): array {
		salt_test_use_child_theme( $this->temp_child( array( 'salt-overrides.json' => self::json( $declaration ) ) + $files ) );
		return $this->flag();
	}

	public function test_flag_is_empty_without_a_child_theme(): void {
		$this->assertSame( array(), $this->flag() );
	}

	public function test_flag_for_the_fixture_raises_only_the_logic_section(): void {
		salt_test_use_child_theme( self::fixture() );

		$items = $this->flag();

		$this->assertCount( 1, $items );
		$this->assertSame( 'stats', $items[0]['section'] );
		$this->assertStringContainsString( 'stats (logic, last verified against 1.0.0)', $items[0]['message'] );
		$this->assertStringContainsString( 'Re-verify it on every update', $items[0]['message'] );
	}

	public function test_flag_raises_a_view_not_verified_against_the_sites_contract(): void {
		$items = $this->flag_for(
			self::declaration( array( 'contract' => '1.1.0' ) ),
			array( 'template-parts/sections/hero.php' => '<?php' )
		);

		$this->assertCount( 1, $items );
		$this->assertStringContainsString( 'hero (view): last verified against 1.0.0, but the file\'s "contract" is 1.1.0. Re-verify the view against that release\'s view props, then set "verified" to 1.1.0.', $items[0]['message'] );
	}

	public function test_flag_raises_mismatches_between_declaration_and_files(): void {
		$items = $this->flag_for(
			self::declaration(
				array(
					'sections' => array(
						self::entry(),
						self::entry(
							array(
								'section' => 'collection-showcase',
								'tier'    => 'logic',
								'files'   => array( 'template-parts/sections/team.php' ),
							)
						),
						self::entry(
							array(
								'section' => 'tabs',
								'tier'    => 'logic',
								'files'   => array( 'template-parts/sections/tabs.php' ),
							)
						),
						self::entry(
							array(
								'section' => 'slider',
								'files'   => array( 'template-parts/sections/slider.php' ),
							)
						),
					),
				)
			),
			array(
				'template-parts/sections/team.php' => '<?php',
				'template-parts/sections/faq.php'  => '<?php',
			)
		);
		$text  = implode( "\n", array_column( $items, 'message' ) );

		$this->assertStringContainsString( 'hero (view): declared, but the child theme has no template-parts/sections/hero.php, so the parent\'s view is drawn.', $text );
		$this->assertStringContainsString( 'hero: the declared file template-parts/sections/hero.php is not in the child theme.', $text );
		$this->assertStringContainsString( 'The child theme has no template-parts/sections/services.php, template-parts/sections/work.php, template-parts/sections/testimonials.php (grid rows), template-parts/sections/blog-teaser.php, so that layout renders nothing.', $text );
		$this->assertStringContainsString( 'Salt for WordPress has no layout for this section yet.', $text );
		$this->assertStringContainsString( '"slider" is not a salt-contract section this theme knows', $text );
		$this->assertStringContainsString( 'template-parts/sections/faq.php: the child theme swaps this view, but salt-overrides.json does not declare section faq', $text );
	}

	public function test_flag_lists_undeclared_views_when_the_child_has_no_declaration(): void {
		salt_test_use_child_theme(
			$this->temp_child(
				array(
					'template-parts/sections/call-to-action.php' => '<?php',
					'template-parts/sections/child-only.php' => '<?php',
				)
			)
		);

		$items = $this->flag();

		$this->assertCount( 1, $items, 'A child-only layout is not a swap.' );
		$this->assertStringContainsString( 'call-to-action.php: the child theme swaps this view, but salt-overrides.json does not declare section call-to-action', $items[0]['message'] );
	}

	public function test_flag_raises_an_invalid_file(): void {
		salt_test_use_child_theme( $this->temp_child( array( 'salt-overrides.json' => '{"site":' ) ) );

		$items = $this->flag();

		$this->assertCount( 1, $items );
		$this->assertStringContainsString( 'salt-overrides.json is not valid', $items[0]['message'] );
	}

	public function test_flag_raises_image_sizes_with_their_section_and_client_section_clashes(): void {
		$sizes = static fn( string $section ): array => array(
			'section' => $section,
			'slot'    => 'half',
			'sizes'   => '100vw',
			'reason'  => 'x',
		);
		$items = $this->flag_for(
			self::declaration(
				array(
					'sections'       => array(
						self::entry(),
						self::entry(
							array(
								'section' => 'stats',
								'tier'    => 'logic',
								'files'   => array( 'template-parts/sections/stats.php' ),
							)
						),
					),
					'imageSizes'     => array( $sizes( 'hero' ), $sizes( 'stats' ), $sizes( 'gallery' ) ),
					'clientSections' => array(
						array(
							'id'     => 'pricing',
							'reason' => 'x',
						),
						array(
							'id'     => 'showroom-finder',
							'reason' => 'x',
						),
					),
				)
			),
			array(
				'template-parts/sections/hero.php'  => '<?php',
				'template-parts/sections/stats.php' => '<?php',
			)
		);
		$text  = implode( "\n", array_column( $items, 'message' ) );

		$this->assertStringNotContainsString( 'hero, image slot', $text, 'Its view entry is verified: not raised.' );
		$this->assertStringContainsString( 'stats, image slot half', $text, 'Raised with its logic entry.' );
		$this->assertStringContainsString( 'gallery, image slot half', $text, 'No entry for its section: raised.' );
		$this->assertStringContainsString( 'pricing (client section): Salt has a section of the same id', $text );
		$this->assertStringNotContainsString( 'showroom-finder', $text );
	}

	public function test_flag_notice_escapes_and_checks_the_capability(): void {
		$items = array(
			array(
				'section' => 'hero',
				'message' => 'hero: the declared file <img src=x onerror=alert(1)>.php is not in the child theme.',
			),
		);

		$this->assertSame( '', salt_override_flag_notice( $items, false ) );
		$this->assertSame( '', salt_override_flag_notice( array(), true ) );

		$html = salt_override_flag_notice( $items, true );
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( '&lt;img src=x onerror=alert(1)&gt;', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_admin_notice_shows_only_on_the_update_screens_to_theme_switchers(): void {
		salt_test_use_child_theme( self::fixture() );
		$print = static function (): string {
			ob_start();
			salt_override_flag_admin_notice();
			return (string) ob_get_clean();
		};

		$GLOBALS['salt_test_user']['caps'] = array( 'switch_themes' );
		$this->assertSame( '', $print(), 'No screen.' );

		$GLOBALS['salt_test_screen'] = (object) array( 'id' => 'dashboard' );
		$this->assertSame( '', $print(), 'Not an update screen.' );

		foreach ( array( 'themes', 'update-core', 'update-core-network' ) as $screen ) {
			$GLOBALS['salt_test_screen'] = (object) array( 'id' => $screen );
			$this->assertStringContainsString( 'stats (logic', $print() );
		}

		$GLOBALS['salt_test_user']['caps'] = array( 'edit_posts' );
		$this->assertSame( '', $print(), 'Capability checked.' );
	}

	public function test_the_notice_is_hooked_on_the_site_and_network_admin(): void {
		// The hooks are added when the module loads (before salt_test_reset()
		// clears the registry), so read the registration from the source.
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/services/override-flag.php' );

		$this->assertStringContainsString( "add_action( 'admin_notices', 'salt_override_flag_admin_notice' );", $source );
		$this->assertStringContainsString( "add_action( 'network_admin_notices', 'salt_override_flag_admin_notice' );", $source );
	}

	public function test_flag_raises_declared_files_outside_the_child_theme(): void {
		$items = $this->flag_for(
			self::declaration(
				array(
					'sections' => array(
						self::entry(
							array(
								'files' => array( 'template-parts/sections/hero.php', '/var/www/hero.php', '../salt-wordpress/hero.php', 'parts/../../x.php' ),
							)
						),
					),
				)
			),
			array( 'template-parts/sections/hero.php' => '<?php' )
		);
		$text  = implode( "\n", array_column( $items, 'message' ) );

		foreach ( array( '/var/www/hero.php', '../salt-wordpress/hero.php', 'parts/../../x.php' ) as $file ) {
			$this->assertStringContainsString( 'hero: the declared file ' . $file . ' is outside the child theme', $text );
		}
		$this->assertCount( 3, $items, 'The file inside the child theme is fine.' );
	}

	public function test_flag_reports_testimonials_rows_left_undeclared(): void {
		$items = $this->flag_for(
			self::declaration(
				array(
					'sections' => array(
						self::entry(
							array(
								'section' => 'carousel',
								'files'   => array( 'template-parts/sections/testimonials.php' ),
							)
						),
					),
				)
			),
			array( 'template-parts/sections/testimonials.php' => '<?php' )
		);
		$text  = implode( "\n", array_column( $items, 'message' ) );

		$this->assertStringContainsString( 'testimonials.php: the child theme swaps this view, but salt-overrides.json does not declare section collection-showcase (grid rows)', $text );
		$this->assertStringNotContainsString( 'carousel (view): declared, but', $text, 'testimonials.php is a carousel view for slider rows.' );
		$this->assertCount( 1, $items );
	}
}
