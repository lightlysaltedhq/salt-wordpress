<?php
/**
 * The section logic/view split entry (ID-3587): resolver lookup, the
 * shared resolver → 0-state → wrapper → view path, the unsplit
 * compatibility path, and what a child-theme view override receives.
 *
 * Child views are simulated with temporary template files passed to
 * salt_render_resolved_section() as the located view — the same thing
 * locate_template() hands it when a child overrides
 * template-parts/sections/{slug}.php.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/acf.php';

final class SectionSplitTest extends SaltTestCase {

	/**
	 * Temporary view files to delete after each test.
	 *
	 * @var string[]
	 */
	private array $temp_views = array();

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	protected function tearDown(): void {
		foreach ( $this->temp_views as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		$this->temp_views = array();
		parent::tearDown();
	}

	/**
	 * Write a temporary view template.
	 *
	 * @param string $php Template source.
	 * @return string Path.
	 */
	private function temp_view( string $php ): string {
		$file = (string) tempnam( sys_get_temp_dir(), 'salt-view-' );
		file_put_contents( $file, $php );
		$this->temp_views[] = $file;
		return $file;
	}

	/**
	 * Seed one FC row and make it current.
	 *
	 * @param string              $layout Layout slug (ACF form).
	 * @param array<string,mixed> $fields Row fields.
	 * @return void
	 */
	private function seed_row( string $layout, array $fields ): void {
		salt_test_seed_fc(
			array(
				array(
					'layout' => $layout,
					'fields' => $fields,
				),
			)
		);
		the_row();
	}

	// -----------------------------------------------------------------
	// Resolver lookup.
	// -----------------------------------------------------------------

	public function test_split_sections_resolve_to_their_parent_resolver(): void {
		$this->assertSame( 'salt_resolve_section_hero', salt_section_resolver( 'hero' ) );
		$this->assertSame( 'salt_resolve_section_faq', salt_section_resolver( 'faq' ) );
		$this->assertSame( 'salt_resolve_section_rich_text', salt_section_resolver( 'rich-text' ) );
	}

	/**
	 * Layouts with no resolver.
	 *
	 * @return array<string,array{0:string}>
	 */
	public static function unsplit_layouts(): array {
		return array(
			'not split yet'      => array( 'cta' ),
			'no such layout'     => array( 'does-not-exist' ),
			'ACF underscore'     => array( 'rich_text' ),
			'path traversal'     => array( '../inc/render' ),
			'uppercase'          => array( 'Hero' ),
			'empty'              => array( '' ),
			'trailing separator' => array( 'hero-' ),
		);
	}

	#[DataProvider( 'unsplit_layouts' )]
	public function test_layouts_without_a_parent_resolver_return_null( string $layout ): void {
		$this->assertNull( salt_section_resolver( $layout ) );
	}

	// -----------------------------------------------------------------
	// Unsplit compatibility path.
	// -----------------------------------------------------------------

	public function test_unsplit_template_loads_whole_with_the_loop_context(): void {
		$view = $this->temp_view( '<?php echo "<p>whole:" . (int) $args[\'index\'] . ":" . ( $args[\'is_primary\'] ? "primary" : "secondary" ) . "</p>";' );

		ob_start();
		salt_render_resolved_section(
			'child-only',
			$view,
			array(
				'index'      => 5,
				'is_primary' => false,
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame( '<p>whole:5:secondary</p>', $html, 'No wrapper, no props: exactly the pre-split behaviour.' );
		$this->assertSame( 0, salt_render_state()['section_index'] );
	}

	// -----------------------------------------------------------------
	// Split path.
	// -----------------------------------------------------------------

	public function test_child_view_receives_props_and_plan_inside_the_wrapper(): void {
		$this->seed_row(
			'rich_text',
			array(
				'heading' => 'Child heading',
				'content' => '<p>Body.</p>',
			)
		);
		$view = $this->temp_view(
			'<?php printf( "<p class=\"child\" data-level=\"%d\" data-index=\"%d\">%s|%s</p>", (int) $args[\'plan\'][\'headingLevel\'], (int) $args[\'plan\'][\'index\'], esc_html( (string) $args[\'props\'][\'heading\'] ), implode( ",", array_keys( $args ) ) );'
		);

		ob_start();
		salt_render_resolved_section(
			'rich-text',
			$view,
			array(
				'index'      => 3,
				'is_primary' => true,
			)
		);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'data-section="rich-text"', $html, 'The entry opens the wrapper.' );
		$this->assertStringContainsString( '<p class="child" data-level="1" data-index="3">Child heading|props,plan</p>', $html );
		$this->assertStringNotContainsString( 'x-prose', $html, 'The parent view did not run.' );
		$this->assertTrue( salt_h1_claimed() );
	}

	public function test_zero_state_never_loads_the_view_or_opens_a_wrapper(): void {
		$this->seed_row( 'rich_text', array() );
		$view = $this->temp_view( '<?php echo "VIEW RAN";' );

		ob_start();
		salt_render_resolved_section(
			'rich-text',
			$view,
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame( '', $html );
		$this->assertSame( 0, salt_render_state()['section_index'], 'No band was opened.' );
		$this->assertFalse( salt_h1_claimed() );
	}

	public function test_logic_runs_even_when_a_child_view_draws_nothing_of_it(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 1,
					'post_type'  => 'faq',
					'post_title' => 'Kept?',
					'menu_order' => 0,
				),
			)
		);
		$GLOBALS['salt_test_fields']['1']['answer'] = '<p>Yes.</p>';
		$this->seed_row( 'faq', array( 'heading' => 'FAQs' ) );
		$view = $this->temp_view( '<?php echo "<p>minimal child view</p>";' );

		ob_start();
		salt_render_resolved_section(
			'faq',
			$view,
			array(
				'index'      => 0,
				'is_primary' => false,
			)
		);
		ob_end_clean();

		$this->assertCount( 1, salt_schema_queue(), 'Schema stays with the parent logic whatever the view draws.' );
	}

	public function test_whole_section_override_inside_a_split_section_is_logged(): void {
		$this->seed_row( 'rich_text', array( 'heading' => 'Legacy' ) );
		$view = $this->temp_view(
			'<?php salt_section( array( "name" => "rich-text" ), static function ( int $level ): void { echo "legacy"; } );'
		);

		ob_start();
		salt_render_resolved_section(
			'rich-text',
			$view,
			array(
				'index'      => 0,
				'is_primary' => false,
			)
		);
		ob_end_clean();

		$this->assertStringContainsString( 'whole-section override', implode( ' ', $this->logs() ) );
	}
}
