<?php
/**
 * The generated collection field groups (ID-3610 part B): the committed
 * emitter output is registered verbatim, one group per collection on the
 * theme's post type, behind the module gates; a broken file registers
 * nothing (logged, never fatal: DEP01); and every place a group says
 * WordPress keeps natively (salt.native) is one the post type has.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/service.php';
require_once __DIR__ . '/../shims/work.php';
require_once __DIR__ . '/../shims/team.php';
require_once __DIR__ . '/../shims/testimonial.php';
require_once __DIR__ . '/../shims/faq.php';
require_once __DIR__ . '/../shims/location.php';

foreach ( array( 'service', 'work', 'team-member', 'testimonial', 'faq', 'location' ) as $salt_cpt_file ) {
	require_once dirname( __DIR__, 2 ) . '/inc/cpt/' . $salt_cpt_file . '.php';
}
require_once dirname( __DIR__, 2 ) . '/inc/fields/collections.php';

final class GeneratedCollectionsTest extends SaltTestCase {

	/**
	 * The collections in the contract's source order, with the post type each is located on.
	 */
	private const COLLECTIONS = array(
		'services'     => 'service',
		'case-studies' => 'work',
		'testimonials' => 'testimonial',
		'posts'        => 'post',
		'team'         => 'team_member',
		'faqs'         => 'faq',
		'locations'    => 'location',
	);

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_acf_groups'] = array();
		$GLOBALS['salt_test_cpts']       = array();
		$GLOBALS['salt_test_post_types'] = array();
		$GLOBALS['salt_test_taxonomies'] = array();
		salt_generated_collection_groups( true );
	}

	/**
	 * The committed file, decoded as the emitter wrote it.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function committed(): array {
		return json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/fields/generated/collections.json' ), true );
	}

	public function test_one_group_per_collection_is_registered_verbatim(): void {
		salt_register_collection_fields();

		$this->assertSame( array_slice( self::committed(), 1 ), $GLOBALS['salt_test_acf_groups'], 'The groups after the empty page group, exactly as generated.' );
		$this->assertSame(
			self::COLLECTIONS,
			array_combine(
				array_map( static fn( array $g ): string => $g['salt']['collection'], $GLOBALS['salt_test_acf_groups'] ),
				array_map( 'salt_collection_group_post_type', $GLOBALS['salt_test_acf_groups'] )
			)
		);
		$this->assertSame( array(), $this->logs() );
	}

	public function test_a_disabled_module_registers_no_group_for_its_post_type(): void {
		$GLOBALS['salt_test_fields']['option']['module_service']  = false;
		$GLOBALS['salt_test_fields']['option']['module_location'] = false;

		salt_register_collection_fields();

		$this->assertSame(
			array( 'case-studies', 'testimonials', 'posts', 'team', 'faqs' ),
			array_map( static fn( array $g ): string => $g['salt']['collection'], $GLOBALS['salt_test_acf_groups'] ),
			'Posts have no module and always register.'
		);
	}

	public function test_a_missing_or_invalid_file_decodes_to_null(): void {
		$page = '{"key":"group_salt_sections","fields":[{"key":"field_salt_sections_container","layouts":[]}]}';
		$ok   = '{"key":"group_salt_collection_faqs","fields":[],"location":[[{"param":"post_type","operator":"==","value":"faq"}]],"salt":{"collection":"faqs"}}';

		$this->assertNull( salt_generated_collections_decode( null ), 'Missing.' );
		$this->assertNull( salt_generated_collections_decode( '{not json' ), 'Not JSON.' );
		$this->assertNull( salt_generated_collections_decode( '[' . $page . ']' ), 'No collection group.' );
		$this->assertNull( salt_generated_collections_decode( '[' . $ok . ']' ), 'No page group first: not the emitter\'s output.' );
		$this->assertNull( salt_generated_collections_decode( '[' . str_replace( '"layouts":[]', '"layouts":[{}]', $page ) . ',' . $ok . ']' ), 'Page sections belong in sections.json.' );
		$this->assertNull( salt_generated_collections_decode( '[' . $page . ',' . str_replace( 'group_salt_collection_faqs', 'group_salt_faq', $ok ) . ']' ), 'A hand-named group.' );
		$this->assertSame( 'faq', salt_collection_group_post_type( salt_generated_collections_decode( '[' . $page . ',' . $ok . ']' )[0] ) );
	}

	public function test_a_broken_file_registers_nothing_and_logs(): void {
		$path   = dirname( __DIR__, 2 ) . '/inc/fields/generated/collections.json';
		$before = (string) file_get_contents( $path );
		file_put_contents( $path, '[]' );
		try {
			salt_generated_collection_groups( true );
			salt_register_collection_fields();
		} finally {
			file_put_contents( $path, $before );
			salt_generated_collection_groups( true );
		}

		$this->assertSame( array(), $GLOBALS['salt_test_acf_groups'] );
		$this->assertStringContainsString( 'is missing or is not the generated collection groups', implode( ' ', $this->logs() ) );
	}

	public function test_every_native_place_is_one_the_post_type_has(): void {
		foreach ( array( 'service', 'work', 'team_member', 'testimonial', 'faq', 'location' ) as $cpt ) {
			call_user_func( 'salt_register_cpt_' . $cpt );
		}
		$registered = $GLOBALS['salt_test_cpts'] + $GLOBALS['salt_test_post_types'];
		$supports   = array(
			'title'          => 'title',
			'body'           => 'editor',
			'excerpt'        => 'excerpt',
			'featured-image' => 'thumbnail',
		);

		foreach ( array_slice( self::committed(), 1 ) as $group ) {
			$post_type = salt_collection_group_post_type( $group );
			if ( 'post' === $post_type ) {
				continue; // Core's post type: WordPress's own supports and the category taxonomy.
			}
			$args = $registered[ $post_type ] ?? null;
			$this->assertIsArray( $args, "{$post_type} is registered." );

			foreach ( (array) ( $group['salt']['native'] ?? array() ) as $field => $place ) {
				if ( 'categories' === $place ) {
					$taxonomy = salt_query_taxonomy( $post_type );
					$this->assertNotNull( $taxonomy, "{$post_type}: {$field} is stored as terms, so the post type needs a taxonomy." );
					$this->assertContains( $post_type, (array) $GLOBALS['salt_test_taxonomies'][ $taxonomy ]['object_type'], "{$taxonomy} is registered on {$post_type}." );
					continue;
				}
				$this->assertArrayHasKey( $place, $supports, "{$post_type}: {$field} names a place this test does not know ({$place})." );
				$this->assertContains( $supports[ $place ], $args['supports'], "{$post_type}: {$field} is kept in the {$place}, so the post type supports {$supports[ $place ]}." );
			}

			$kept = array_values( array_intersect( array( 'editor', 'thumbnail', 'excerpt' ), $args['supports'] ) );
			$used = array_values( array_intersect( array( 'editor', 'thumbnail', 'excerpt' ), array_map( static fn( string $p ): string => $supports[ $p ] ?? '', (array) ( $group['salt']['native'] ?? array() ) ) ) );
			sort( $kept );
			sort( $used );
			$this->assertSame( $used, $kept, "{$post_type} supports no native place the contract does not use (a dropped field's box is gone too)." );
		}
	}
}
