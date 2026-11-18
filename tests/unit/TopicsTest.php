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

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/service.php';
require_once __DIR__ . '/../shims/faq.php';
require_once __DIR__ . '/../shims/location.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/service.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/work.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/faq.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/location.php';
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
}
