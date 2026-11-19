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

foreach ( array( 'service', 'work', 'testimonial', 'faq', 'location' ) as $salt_cpt_file ) {
	require_once dirname( __DIR__, 2 ) . '/inc/cpt/' . $salt_cpt_file . '.php';
}
require_once dirname( __DIR__, 2 ) . '/inc/fields/collections.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/post.php';

final class GeneratedCollectionsTest extends SaltTestCase {

	/**
	 * The collections in the contract's source order, with the post type each is located on.
	 */
	private const COLLECTIONS = array(
		'services'     => 'service',
		'case-studies' => 'work',
		'testimonials' => 'testimonial',
		'posts'        => 'post',
		'team'         => '', // A user form, not a post type.
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
		$team = str_replace( array( 'faqs', 'post_type', '"faq"' ), array( 'team', 'user_form', '"edit"' ), $ok );
		$this->assertNotNull( salt_generated_collections_decode( '[' . $page . ',' . $team . ']' ) );
		foreach ( array( str_replace( 'team', 'posts', $team ), str_replace( 'edit', 'add', $team ), str_replace( '==', '!=', $team ), str_replace( 'faqs', 'team', $ok ) ) as $wrong ) {
			$this->assertNull( salt_generated_collections_decode( '[' . $page . ',' . $wrong . ']' ) );
		}
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
		foreach ( array( 'service', 'work', 'testimonial', 'faq', 'location' ) as $cpt ) {
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
			if ( 'post' === $post_type || 'team' === $group['salt']['collection'] ) {
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

	public function test_the_primary_topic_is_drawn_in_the_topics_box_alone(): void {
		$posts   = array_values( array_filter( salt_generated_collection_groups( true ), static fn( array $group ): bool => 'posts' === $group['salt']['collection'] ) )[0];
		$primary = array_column( $posts['fields'], null, 'key' )[ SALT_POST_PRIMARY_TOPIC_FIELD ];

		$this->assertSame( array( SALT_POST_PRIMARY_TOPIC_FIELD, 'field_salt_collection_posts_layout' ), array_column( $posts['fields'], 'key' ) );
		$this->assertFalse( salt_post_primary_topic_in_box( $primary ), 'Not drawn in the post\'s field group.' );
		salt_post_drawing_topics_box( true );
		$this->assertSame( $primary, salt_post_primary_topic_in_box( $primary ), 'Drawn in the Topics box.' );
		salt_post_drawing_topics_box( false );
	}

	public function test_the_summary_count_loads_on_the_three_edit_screens_only(): void {
		$loaded = array();
		foreach ( array( 'service', 'work', 'post', 'testimonial', 'page' ) as $type ) {
			$GLOBALS['typenow']                  = $type;
			$GLOBALS['salt_test_inline_scripts'] = array();
			salt_summary_count_enqueue( 'post.php' );
			$loaded[ $type ] = $GLOBALS['salt_test_inline_scripts']['salt-character-count']['before'] ?? array();
		}
		$GLOBALS['typenow']                  = 'service';
		$GLOBALS['salt_test_inline_scripts'] = array();
		salt_summary_count_enqueue( 'edit.php' );
		$on_list = $GLOBALS['salt_test_inline_scripts'];
		unset( $GLOBALS['typenow'] );

		$count = array( 'window.saltCharacterCount = ( window.saltCharacterCount || [] ).concat( [{"field":"excerpt","target":300,"marks":[{"at":300,"note":"Over the suggested 300 characters","strong":true}]}] );' );
		$this->assertSame(
			array(
				'service'     => $count,
				'work'        => $count,
				'post'        => $count,
				'testimonial' => array(),
				'page'        => array(),
			),
			$loaded
		);
		$this->assertSame( array(), $on_list, 'Not on the posts list.' );
	}

	public function test_the_title_count_covers_every_collection_whose_title_is_the_post_title(): void {
		$types = array();
		foreach ( json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/fields/generated/collections.json' ), true ) as $group ) {
			if ( in_array( 'title', $group['salt']['native'] ?? array(), true ) ) {
				$types[] = $group['location'][0][0]['value'];
			}
		}

		$this->assertEqualsCanonicalizing( array( 'service', 'work', 'post', 'testimonial', 'team_member', 'faq', 'location' ), $types );
		$this->assertEqualsCanonicalizing( $types, salt_title_post_types() );
	}

	public function test_the_title_count_loads_on_the_collection_edit_screens_only(): void {
		$loaded = array();
		foreach ( array( 'service', 'work', 'post', 'testimonial', 'team_member', 'faq', 'location', 'page', 'salt_submission' ) as $type ) {
			$GLOBALS['typenow']                  = $type;
			$GLOBALS['salt_test_inline_scripts'] = array();
			salt_title_count_enqueue( 'post-new.php' );
			$loaded[ $type ] = $GLOBALS['salt_test_inline_scripts']['salt-character-count']['before'] ?? array();
		}
		$GLOBALS['typenow']                  = 'faq';
		$GLOBALS['salt_test_inline_scripts'] = array();
		salt_title_count_enqueue( 'edit.php' );
		$on_list = $GLOBALS['salt_test_inline_scripts'];
		unset( $GLOBALS['typenow'] );

		$count  = array( 'window.saltCharacterCount = ( window.saltCharacterCount || [] ).concat( [{"field":"title","target":120,"marks":[{"at":120,"note":"Over the suggested 120 characters","strong":true}]}] );' );
		$search = array( 'window.saltCharacterCount = ( window.saltCharacterCount || [] ).concat( [{"field":"title","target":120,"marks":[{"at":60,"note":"Search results may shorten a title over about 60 characters","strong":false},{"at":120,"note":"Over the suggested 120 characters","strong":true}]}] );' );
		$this->assertSame(
			array(
				'service'         => $search,
				'work'            => $search,
				'post'            => $search,
				'testimonial'     => $count,
				'team_member'     => $count,
				'faq'             => $count,
				'location'        => $count,
				'page'            => array(),
				'salt_submission' => array(),
			),
			$loaded
		);
		$this->assertSame( array(), $on_list, 'Not on the list screen.' );
		$this->assertSame( array( 'service', 'work', 'post' ), salt_title_search_post_types(), 'The 60 note: the collections with a page of their own.' );
	}
}
