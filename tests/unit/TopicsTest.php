<?php
/**
 * Topics (inc/cpt/topics.php): the category taxonomies of services, case
 * studies, FAQs and locations are labelled Topics, offer no parent, and stay
 * flat (salt-contract rc.7 _topic.json and the collections' topics owes).
 *
 * The box itself and the term screens are proved in a real browser by the
 * real ACF harness (tests/real-acf/browser.mjs).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/service.php';
require_once __DIR__ . '/../shims/faq.php';
require_once __DIR__ . '/../shims/location.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/service.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/work.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/faq.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/location.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/collections.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/post.php';

final class TopicsTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_cpts']       = array();
		$GLOBALS['salt_test_taxonomies'] = array();
	}

	public function test_every_topics_taxonomy_is_labelled_topics_with_the_checkbox_box(): void {
		salt_register_cpt_service();
		salt_register_cpt_work();
		salt_register_cpt_faq();
		salt_register_cpt_location();

		$this->assertSame( array( 'service_category', 'work_type', 'faq_category', 'location_area' ), salt_topic_taxonomies() );
		foreach ( salt_topic_taxonomies() as $taxonomy ) {
			$args = $GLOBALS['salt_test_taxonomies'][ $taxonomy ]['args'] ?? null;
			$this->assertIsArray( $args, "{$taxonomy} is registered." );
			$this->assertSame( 'Topics', $args['labels']['name'], $taxonomy );
			$this->assertSame( 'Topic', $args['labels']['singular_name'], $taxonomy );
			$this->assertSame( 'Topics', $args['labels']['menu_name'], $taxonomy );
			$this->assertSame( 'salt_topic_meta_box', $args['meta_box_cb'], "{$taxonomy}: the box without its parent control." );
			$this->assertSame( 'taxonomy_meta_box_sanitize_cb_checkboxes', $args['meta_box_sanitize_cb'], "{$taxonomy}: still saved as the checkbox box saves." );
			$this->assertTrue( $args['hierarchical'], "{$taxonomy}: hierarchical only to keep the checkbox box." );
		}
	}

	public function test_no_label_says_category_area_or_type(): void {
		$labels = implode( ' ', salt_topic_labels() );

		$this->assertDoesNotMatchRegularExpression( '/categor|area|type|parent/i', $labels );
	}

	public function test_a_topic_never_takes_a_parent_on_update(): void {
		foreach ( salt_topic_taxonomies() as $taxonomy ) {
			$this->assertSame( 0, salt_topic_update_parent( 12, 34, $taxonomy ), $taxonomy );
		}
		$this->assertSame( 12, salt_topic_update_parent( 12, 34, 'category' ), 'Posts\' categories are inc/cpt/post.php\'s.' );
	}

	public function test_a_post_topic_never_takes_a_parent_on_update(): void {
		$this->assertSame( 0, salt_post_topic_update_parent( 12, 34, 'category' ) );
		$this->assertSame( 12, salt_post_topic_update_parent( 12, 34, 'service_category' ), 'Another taxonomy is topics.php\'s.' );
	}

	public function test_post_categories_are_labelled_topics_and_drawn_by_the_topics_box(): void {
		$args = salt_post_topics_taxonomy_args( array( 'hierarchical' => true ), 'category' );
		$this->assertSame( salt_topic_labels(), $args['labels'] );
		$this->assertSame( 'salt_post_topics_meta_box', $args['meta_box_cb'] );
		$this->assertTrue( $args['hierarchical'], 'Hierarchical only to keep the checkbox box.' );
		$this->assertSame( array( 'hierarchical' => false ), salt_post_topics_taxonomy_args( array( 'hierarchical' => false ), 'post_tag' ) );
	}

	/**
	 * Topic name, whether the 120-character limit refuses it.
	 *
	 * @return array<string,array{string,bool}>
	 */
	public static function name_provider(): array {
		return array(
			'120 characters: saved'                 => array( str_repeat( 'a', 120 ), false ),
			'121 characters: refused'               => array( str_repeat( 'a', 121 ), true ),
			'120 two-byte characters: saved'        => array( str_repeat( 'é', 120 ), false ),
			'121 two-byte characters: refused'      => array( str_repeat( 'é', 121 ), true ),
			'120 four-byte characters: saved'       => array( str_repeat( '🌱', 120 ), false ),
			'121 four-byte characters: refused'     => array( str_repeat( '🌱', 121 ), true ),
			'120 with a CRLF counted as one: saved' => array( str_repeat( 'a', 59 ) . "\r\n" . str_repeat( 'a', 60 ), false ),
		);
	}

	#[DataProvider( 'name_provider' )]
	public function test_the_topic_name_limit_counts_characters( string $name, bool $refused ): void {
		$error = salt_topic_name_error( $name );

		$refused
			? $this->assertSame( 'The topic name is 121 characters long. Shorten it to 120 characters or fewer.', $error )
			: $this->assertNull( $error );
	}

	public function test_the_topic_box_is_on_every_post_type_with_a_topics_taxonomy(): void {
		salt_register_cpt_service();
		salt_register_cpt_work();
		salt_register_cpt_faq();
		salt_register_cpt_location();

		$types = array();
		foreach ( salt_topic_taxonomies() as $taxonomy ) {
			$types = array_merge( $types, (array) $GLOBALS['salt_test_taxonomies'][ $taxonomy ]['object_type'] );
		}

		$this->assertEqualsCanonicalizing( $types, salt_topic_post_types() );
	}

	public function test_the_topic_name_check_loads_on_the_topic_screens_only(): void {
		require_once __DIR__ . '/../shims/admin.php';
		$GLOBALS['salt_test_localized'] = array();
		$loaded                         = array();
		$screens                        = array(
			'edit-tags.php service_category' => array( 'edit-tags.php', 'taxnow', 'service_category' ),
			'term.php work_type'             => array( 'term.php', 'taxnow', 'work_type' ),
			'edit-tags.php faq_category'     => array( 'edit-tags.php', 'taxnow', 'faq_category' ),
			'term.php location_area'         => array( 'term.php', 'taxnow', 'location_area' ),
			'edit-tags.php category'         => array( 'edit-tags.php', 'taxnow', 'category' ),
			'term.php post_tag'              => array( 'term.php', 'taxnow', 'post_tag' ),
			'post-new.php service'           => array( 'post-new.php', 'typenow', 'service' ),
			'post.php work'                  => array( 'post.php', 'typenow', 'work' ),
			'post-new.php faq'               => array( 'post-new.php', 'typenow', 'faq' ),
			'post.php location'              => array( 'post.php', 'typenow', 'location' ),
			'post-new.php post'              => array( 'post-new.php', 'typenow', 'post' ),
			'post.php page'                  => array( 'post.php', 'typenow', 'page' ),
			'edit.php service'               => array( 'edit.php', 'typenow', 'service' ),
		);
		foreach ( $screens as $label => list( $hook, $global, $value ) ) {
			unset( $GLOBALS['taxnow'], $GLOBALS['typenow'], $GLOBALS['salt_test_enqueued']['scripts']['salt-topic-name-limit'] );
			$GLOBALS[ $global ] = $value;
			salt_topic_name_limit_enqueue( $hook );
			$loaded[ $label ] = isset( $GLOBALS['salt_test_enqueued']['scripts']['salt-topic-name-limit'] );
		}
		unset( $GLOBALS['taxnow'], $GLOBALS['typenow'] );

		$this->assertSame(
			array(
				'edit-tags.php service_category' => true,
				'term.php work_type'             => true,
				'edit-tags.php faq_category'     => true,
				'term.php location_area'         => true,
				'edit-tags.php category'         => false,
				'term.php post_tag'              => false,
				'post-new.php service'           => true,
				'post.php work'                  => true,
				'post-new.php faq'               => true,
				'post.php location'              => true,
				'post-new.php post'              => false,
				'post.php page'                  => false,
				'edit.php service'               => false,
			),
			$loaded
		);
		$this->assertSame(
			array(
				'max'        => 120,
				'message'    => 'The topic name is {length} characters long. Shorten it to 120 characters or fewer.',
				'taxonomies' => array( 'service_category', 'work_type', 'faq_category', 'location_area' ),
			),
			$GLOBALS['salt_test_localized']['salt-topic-name-limit']['saltTopicNameLimit']
		);
	}
}
