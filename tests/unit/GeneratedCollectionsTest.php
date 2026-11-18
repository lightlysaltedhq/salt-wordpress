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

use PHPUnit\Framework\Attributes\DataProvider;

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

	public function test_the_primary_topic_is_drawn_in_the_topics_box_alone(): void {
		$posts   = array_values( array_filter( salt_generated_collection_groups( true ), static fn( array $group ): bool => 'posts' === $group['salt']['collection'] ) )[0];
		$primary = array_column( $posts['fields'], null, 'key' )[ SALT_POST_PRIMARY_TOPIC_FIELD ];

		$this->assertSame( array( 'field_salt_collection_posts_author', SALT_POST_PRIMARY_TOPIC_FIELD, 'field_salt_collection_posts_layout' ), array_column( $posts['fields'], 'key' ), 'author, primaryTopic and layout, kept in the group verbatim.' );
		$this->assertFalse( salt_post_primary_topic_in_box( $primary ), 'Not drawn in the post\'s field group.' );
		salt_post_drawing_topics_box( true );
		$this->assertSame( $primary, salt_post_primary_topic_in_box( $primary ), 'Drawn in the Topics box.' );
		salt_post_drawing_topics_box( false );
	}

	/**
	 * Excerpt, whether the save is refused.
	 *
	 * @return array<string,array{string,bool}>
	 */
	public static function summary_provider(): array {
		return array(
			'300 characters: saved'                 => array( str_repeat( 'a', 300 ), false ),
			'301 characters: refused'               => array( str_repeat( 'a', 301 ), true ),
			'300 two-byte characters: saved'        => array( str_repeat( 'é', 300 ), false ),
			'301 two-byte characters: refused'      => array( str_repeat( 'é', 301 ), true ),
			'300 with a CRLF counted as one: saved' => array( str_repeat( 'a', 298 ) . "\r\n" . 'a', false ),
		);
	}

	#[DataProvider( 'summary_provider' )]
	public function test_the_summary_limit_counts_characters( string $excerpt, bool $refused ): void {
		$error = salt_summary_error( $excerpt );

		$refused
			? $this->assertSame( 'The summary (the Excerpt box) is 301 characters long. Shorten it to 300 characters or fewer.', $error )
			: $this->assertNull( $error );
	}

	public function test_the_summary_check_loads_on_the_three_edit_screens_only(): void {
		require_once __DIR__ . '/../shims/admin.php';
		$GLOBALS['salt_test_enqueued']  = array();
		$GLOBALS['salt_test_localized'] = array();
		$loaded                         = array();
		foreach ( array( 'service', 'work', 'post', 'testimonial', 'page' ) as $type ) {
			$GLOBALS['typenow'] = $type;
			unset( $GLOBALS['salt_test_enqueued']['scripts']['salt-summary-limit'] );
			salt_summary_limit_enqueue( 'post.php' );
			$loaded[ $type ] = isset( $GLOBALS['salt_test_enqueued']['scripts']['salt-summary-limit'] );
		}
		$GLOBALS['typenow'] = 'service';
		unset( $GLOBALS['salt_test_enqueued']['scripts']['salt-summary-limit'] );
		salt_summary_limit_enqueue( 'edit.php' );
		$on_list = isset( $GLOBALS['salt_test_enqueued']['scripts']['salt-summary-limit'] );
		unset( $GLOBALS['typenow'] );

		$this->assertSame(
			array(
				'service'     => true,
				'work'        => true,
				'post'        => true,
				'testimonial' => false,
				'page'        => false,
			),
			$loaded
		);
		$this->assertFalse( $on_list, 'Not on the posts list.' );
		$this->assertSame(
			array(
				'max'     => 300,
				'message' => 'The summary (the Excerpt box) is {length} characters long. Shorten it to 300 characters or fewer.',
			),
			$GLOBALS['salt_test_localized']['salt-summary-limit']['saltSummaryLimit']
		);
	}

	/**
	 * Title, whether the 120-character limit refuses it.
	 *
	 * @return array<string,array{string,bool}>
	 */
	public static function title_provider(): array {
		return array(
			'120 characters: saved'            => array( str_repeat( 'a', 120 ), false ),
			'121 characters: refused'          => array( str_repeat( 'a', 121 ), true ),
			'120 two-byte characters: saved'   => array( str_repeat( 'é', 120 ), false ),
			'121 two-byte characters: refused' => array( str_repeat( 'é', 121 ), true ),
			'120 four-byte characters: saved'  => array( str_repeat( '🌱', 120 ), false ),
		);
	}

	#[DataProvider( 'title_provider' )]
	public function test_the_title_limit_counts_characters( string $title, bool $refused ): void {
		$error = salt_title_error( $title );

		$refused
			? $this->assertSame( 'The title is 121 characters long. Shorten it to 120 characters or fewer.', $error )
			: $this->assertNull( $error );
	}

	public function test_the_title_limit_covers_every_collection_whose_title_is_the_post_title(): void {
		$types = array();
		foreach ( json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/fields/generated/collections.json' ), true ) as $group ) {
			if ( in_array( 'title', $group['salt']['native'] ?? array(), true ) ) {
				$types[] = $group['location'][0][0]['value'];
			}
		}

		$this->assertEqualsCanonicalizing( array( 'service', 'work', 'post', 'testimonial', 'team_member', 'faq', 'location' ), $types );
		$this->assertEqualsCanonicalizing( $types, salt_title_post_types() );
	}

	public function test_the_title_check_loads_on_the_collection_edit_screens_only(): void {
		require_once __DIR__ . '/../shims/admin.php';
		$GLOBALS['salt_test_enqueued']  = array();
		$GLOBALS['salt_test_localized'] = array();
		$loaded                         = array();
		foreach ( array( 'service', 'work', 'post', 'testimonial', 'team_member', 'faq', 'location', 'page', 'salt_submission' ) as $type ) {
			$GLOBALS['typenow'] = $type;
			unset( $GLOBALS['salt_test_enqueued']['scripts']['salt-title-limit'] );
			salt_title_limit_enqueue( 'post-new.php' );
			$loaded[ $type ] = isset( $GLOBALS['salt_test_enqueued']['scripts']['salt-title-limit'] );
		}
		$GLOBALS['typenow'] = 'faq';
		unset( $GLOBALS['salt_test_enqueued']['scripts']['salt-title-limit'] );
		salt_title_limit_enqueue( 'edit.php' );
		$on_list = isset( $GLOBALS['salt_test_enqueued']['scripts']['salt-title-limit'] );
		unset( $GLOBALS['typenow'] );

		$this->assertSame(
			array(
				'service'         => true,
				'work'            => true,
				'post'            => true,
				'testimonial'     => true,
				'team_member'     => true,
				'faq'             => true,
				'location'        => true,
				'page'            => false,
				'salt_submission' => false,
			),
			$loaded
		);
		$this->assertFalse( $on_list, 'Not on the list screen.' );
		$this->assertSame(
			array(
				'max'     => 120,
				'message' => 'The title is {length} characters long. Shorten it to 120 characters or fewer.',
			),
			$GLOBALS['salt_test_localized']['salt-title-limit']['saltTitleLimit']
		);
	}
}
