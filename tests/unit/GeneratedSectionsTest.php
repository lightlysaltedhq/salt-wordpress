<?php
/**
 * The generated page-sections fields (ID-3610 part A): the committed
 * emitter output is loaded as the theme's layouts, a broken file falls
 * back to the hand-written layouts (DEP01 spirit: logged, never fatal), a
 * hand-written layout cannot replace a generated one, the wrapper reads
 * only the `settings` group by its contract names, and the page loop
 * applies firstPageOnly.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class GeneratedSectionsTest extends SaltTestCase {

	/**
	 * The sections the theme renders, in the contract's order.
	 */
	private const GENERATED = array( 'hero', 'rich-text', 'call-to-action', 'media-text', 'features', 'stats', 'logos', 'gallery', 'process', 'faq', 'collection-showcase', 'carousel', 'contact', 'locations' );

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_generated_sections_group( true );
	}

	// -----------------------------------------------------------------
	// Loading.
	// -----------------------------------------------------------------

	public function test_the_generated_layouts_come_first_then_the_hand_written_ones(): void {
		$names = array_column( salt_section_layouts(), 'name' );

		$this->assertSame( self::GENERATED, array_slice( $names, 0, count( self::GENERATED ) ) );
		$this->assertSame( array(), array_slice( $names, count( self::GENERATED ) ), 'No layout is hand-written since unit 8.' );
		foreach ( array( 'intro', 'pricing', 'tabs', 'listing', 'blog_teaser', 'scroll_carousel', 'services', 'team', 'testimonials', 'work' ) as $absent ) {
			$this->assertNotContains( $absent, $names );
		}
	}

	public function test_the_generated_layouts_are_the_committed_json_verbatim(): void {
		$json = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/fields/generated/sections.json' ), true );

		$this->assertSame(
			$json[0]['fields'][0]['layouts'],
			array_slice( salt_section_layouts(), 0, count( self::GENERATED ) )
		);
	}

	public function test_a_hand_written_layout_for_a_generated_section_is_refused(): void {
		$dir  = dirname( __DIR__, 2 ) . '/inc/fields/layouts';
		$made = ! is_dir( $dir ) && mkdir( $dir );
		$file = $dir . '/zz-test-hero.php';
		file_put_contents( $file, "<?php\nreturn array( 'key' => 'layout_salt_hero', 'name' => 'hero', 'label' => 'Hero', 'sub_fields' => array() );\n" );
		try {
			$layouts = salt_section_layouts();
		} finally {
			unlink( $file );
			if ( $made ) {
				rmdir( $dir );
			}
		}

		$this->assertSame( 1, count( array_keys( array_column( $layouts, 'name' ), 'hero', true ) ) );
		$this->assertNotSame( array(), array_column( $layouts, null, 'name' )['hero']['sub_fields'], 'The generated hero is kept.' );
		$this->assertStringContainsString( 'which is generated from the contract — ignored', implode( ' ', $this->logs() ) );
	}

	public function test_a_missing_or_invalid_file_decodes_to_null(): void {
		$this->assertNull( salt_generated_sections_decode( null ), 'Missing.' );
		$this->assertNull( salt_generated_sections_decode( '{not json' ), 'Not JSON.' );
		$this->assertNull( salt_generated_sections_decode( '[{"key":"group_other","fields":[]}]' ), 'Another group.' );
		$this->assertNull( salt_generated_sections_decode( '[{"key":"group_salt_sections","fields":[{"key":"field_salt_sections_container"}]}]' ), 'No layouts.' );
		$this->assertIsArray( salt_generated_sections_decode( '[{"key":"group_salt_sections","fields":[{"key":"field_salt_sections_container","layouts":[]}]}]' ) );
	}

	// -----------------------------------------------------------------
	// The wrapper reads the settings group (contract names and values).
	// -----------------------------------------------------------------

	/**
	 * Wrapper args for one row holding these fields.
	 *
	 * @param array<string,mixed> $fields Row fields.
	 * @return array<string,mixed>
	 */
	private function wrapper_for( array $fields ): array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'rich-text',
					'fields' => $fields,
				),
			)
		);
		the_row();
		return salt_section_wrapper_args( 'rich-text', array( 'is_primary' => true ) );
	}

	public function test_the_wrapper_maps_every_setting_by_its_contract_name(): void {
		$args = $this->wrapper_for(
			array(
				'settings' => array(
					'spacing'         => 'lg',
					'tone'            => 'surface-inverse',
					'toneDark'        => 'surface',
					'width'           => 'narrow',
					'anchorId'        => 'about',
					'divider'         => true,
					'firstPageOnly'   => false,
					'backgroundImage' => array( 'image' => 9 ),
				),
			)
		);

		$this->assertSame(
			array(
				'name'       => 'rich-text',
				'spacing'    => 'lg',
				'background' => 'inverse',
				'container'  => 'narrow',
				'anchor'     => 'about',
				'divider'    => true,
				'is_primary' => true,
				'id'         => '',
			),
			$args,
			'toneDark and backgroundImage are inert until the contract stylesheet (unit 9).'
		);
	}

	public function test_settings_at_the_rows_top_level_are_not_read(): void {
		$args = $this->wrapper_for(
			array(
				'spacing' => 'lg',
				'tone'    => 'surface-alt',
			)
		);

		$this->assertSame( 'md', $args['spacing'] );
		$this->assertSame( 'surface', $args['background'] );
		$this->assertSame( 'default', $args['container'] );
		$this->assertFalse( $args['divider'] );
	}

	// -----------------------------------------------------------------
	// firstPageOnly, in the page loop.
	// -----------------------------------------------------------------

	/**
	 * Render the page loop for two rich-text rows; the second is firstPageOnly.
	 *
	 * @return string
	 */
	private function render_page(): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'rich-text',
					'fields' => array( 'heading' => 'Always here' ),
				),
				array(
					'layout' => 'rich-text',
					'fields' => array(
						'heading'  => 'First page only',
						'settings' => array( 'firstPageOnly' => true ),
					),
				),
			)
		);
		ob_start();
		get_template_part( 'template-parts/flexible-content' );
		return (string) ob_get_clean();
	}

	public function test_first_page_only_sections_draw_on_page_one(): void {
		$html = $this->render_page();

		$this->assertStringContainsString( 'Always here', $html );
		$this->assertStringContainsString( 'First page only', $html );
	}

	public function test_first_page_only_sections_are_hidden_on_later_pages(): void {
		$GLOBALS['salt_test_query_vars']['paged'] = 2;
		$html                                     = $this->render_page();

		$this->assertStringContainsString( 'Always here', $html );
		$this->assertStringNotContainsString( 'First page only', $html );

		$GLOBALS['salt_test_query_vars'] = array( 'page' => 3 );
		$this->assertStringNotContainsString( 'First page only', $this->render_page(), 'A split singular page counts too.' );
	}
}
