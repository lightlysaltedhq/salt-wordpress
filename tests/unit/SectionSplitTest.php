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

	/**
	 * Temporary resolver files under the parent's inc/sections/ to delete
	 * after each test (named zz-test-*; never committed).
	 *
	 * @var string[]
	 */
	private array $temp_resolvers = array();

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	protected function tearDown(): void {
		foreach ( array_merge( $this->temp_views, $this->temp_resolvers ) as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		$this->temp_views     = array();
		$this->temp_resolvers = array();
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
	 * Write a temporary resolver file into the parent's inc/sections/.
	 *
	 * @param string $layout Layout slug (zz-test-…).
	 * @param string $php    File source.
	 * @return void
	 */
	private function temp_resolver( string $layout, string $php ): void {
		$file = dirname( __DIR__, 2 ) . '/inc/sections/' . $layout . '.php';
		file_put_contents( $file, $php );
		$this->temp_resolvers[] = $file;
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

	public function test_resolver_function_writes_each_hyphen_as_an_underscore(): void {
		$this->assertSame( 'salt_resolve_section_hero', salt_section_resolver_function( 'hero' ) );
		$this->assertSame( 'salt_resolve_section_rich_text', salt_section_resolver_function( 'rich-text' ) );
		$this->assertSame( 'salt_resolve_section_call_to_action', salt_section_resolver_function( 'call-to-action' ) );
	}

	public function test_split_sections_resolve_to_their_parent_resolver(): void {
		$this->assertSame( 'salt_resolve_section_hero', salt_section_resolver( 'hero' ) );
		$this->assertSame( 'salt_resolve_section_faq', salt_section_resolver( 'faq' ) );
		$this->assertSame( 'salt_resolve_section_rich_text', salt_section_resolver( 'rich-text' ) );
	}

	/**
	 * Layouts with no resolver. Every parent layout is split, so the
	 * unsplit case is a layout only a child theme adds (no parent
	 * inc/sections/ file): its template still loads whole.
	 *
	 * @return array<string,array{0:string}>
	 */
	public static function unsplit_layouts(): array {
		return array(
			'child-only layout'  => array( 'child-only' ),
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
			'rich-text',
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
		$this->seed_row( 'rich-text', array() );
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

	public function test_view_plan_carries_priority_media_only_when_the_resolver_sets_it(): void {
		$view = $this->temp_view( '<?php echo "<p>" . var_export( $args[\'plan\'][\'priorityMedia\'], true ) . "</p>";' );

		$this->seed_row( 'rich-text', array( 'heading' => 'Text' ) );
		ob_start();
		salt_render_resolved_section( 'rich-text', $view, array( 'index' => 0 ) );
		$this->assertStringContainsString( '<p>false</p>', (string) ob_get_clean(), 'Default: no priority image.' );

		$this->seed_row( 'hero', array( 'heading' => 'Hero' ) );
		ob_start();
		salt_render_resolved_section( 'hero', $view, array( 'index' => 1 ) );
		$this->assertStringContainsString( '<p>true</p>', (string) ob_get_clean(), 'Hero claims the priority image.' );
	}

	// -----------------------------------------------------------------
	// Fail loudly (resolver naming, conflicts, return shape).
	// -----------------------------------------------------------------

	public function test_misnamed_resolver_throws_in_debug_and_never_loads_the_view(): void {
		$this->temp_resolver( 'zz-test-misnamed', "<?php\nfunction salt_resolve_section_zz_test_wrong_name( array \$context ): ?array {\n\treturn null;\n}\n" );
		$view = $this->temp_view( '<?php echo "VIEW RAN";' );

		$this->expectException( LogicException::class );
		try {
			salt_render_resolved_section( 'zz-test-misnamed', $view, array( 'index' => 0 ) );
		} finally {
			$this->assertStringContainsString( 'does not define salt_resolve_section_zz_test_misnamed()', implode( ' ', $this->logs() ) );
		}
	}

	public function test_misnamed_resolver_renders_nothing_in_production(): void {
		add_filter( 'salt_is_debug', static fn(): bool => false, 20 );
		$this->temp_resolver( 'zz-test-misnamed-prod', "<?php\nfunction salt_resolve_section_zz_test_misnamed_prod_typo( array \$context ): ?array {\n\treturn null;\n}\n" );
		$view = $this->temp_view( '<?php echo "VIEW RAN";' );

		ob_start();
		salt_render_resolved_section( 'zz-test-misnamed-prod', $view, array( 'index' => 0 ) );
		$html = (string) ob_get_clean();

		$this->assertSame( '', $html, 'Never the unsplit path: the view is not loaded with the loop context.' );
		$this->assertStringContainsString( 'does not define', implode( ' ', $this->logs() ) );
	}

	public function test_resolver_already_defined_elsewhere_is_logged_not_a_fatal(): void {
		add_filter( 'salt_is_debug', static fn(): bool => false, 20 );
		// A "child theme" defines the resolver first …
		$child = $this->temp_view( "<?php\nfunction salt_resolve_section_zz_test_conflict( array \$context ): ?array {\n\treturn array( 'section' => array( 'name' => 'child' ), 'props' => array() );\n}\n" );
		require $child;
		// … then the parent's file for the same section exists.
		$this->temp_resolver( 'zz-test-conflict', "<?php\nfunction salt_resolve_section_zz_test_conflict( array \$context ): ?array {\n\treturn null;\n}\n" );
		$view = $this->temp_view( '<?php echo "VIEW RAN";' );

		$this->assertNull( salt_section_resolver( 'zz-test-conflict' ) );

		ob_start();
		salt_render_resolved_section( 'zz-test-conflict', $view, array( 'index' => 0 ) );
		$this->assertSame( '', (string) ob_get_clean(), 'The other definition is never used.' );

		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'salt_resolve_section_zz_test_conflict() is already defined in', $logs );
		$this->assertStringContainsString( 'cannot be replaced', $logs );
	}

	public function test_resolver_already_defined_elsewhere_throws_in_debug(): void {
		$child = $this->temp_view( "<?php\nfunction salt_resolve_section_zz_test_conflict_debug( array \$context ): ?array {\n\treturn null;\n}\n" );
		require $child;
		$this->temp_resolver( 'zz-test-conflict-debug', "<?php\nfunction salt_resolve_section_zz_test_conflict_debug( array \$context ): ?array {\n\treturn null;\n}\n" );

		$this->expectException( LogicException::class );
		salt_section_resolver( 'zz-test-conflict-debug' );
	}

	public function test_unknown_plan_keys_are_a_malformed_return(): void {
		$this->temp_resolver( 'zz-test-bad-plan', "<?php\nfunction salt_resolve_section_zz_test_bad_plan( array \$context ): ?array {\n\treturn array( 'section' => array( 'name' => 'zz-test-bad-plan' ), 'props' => array(), 'plan' => array( 'anchor' => 'x' ) );\n}\n" );
		$view = $this->temp_view( '<?php echo "VIEW RAN";' );

		$this->expectException( UnexpectedValueException::class );
		salt_render_resolved_section( 'zz-test-bad-plan', $view, array( 'index' => 0 ) );
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
		$this->seed_row( 'rich-text', array( 'heading' => 'Legacy' ) );
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
