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

	public function test_the_default_category_is_not_found_and_off_the_sitemap(): void {
		$GLOBALS['salt_test_options']['default_category'] = 1;

		$this->assertTrue( salt_post_topic_is_default( 1 ), '"Uncategorised" answers 404.' );
		$this->assertFalse( salt_post_topic_is_default( 2 ) );
		$this->assertSame( array( 'exclude' => array( 3, 1 ) ), salt_post_default_topic_off_sitemap( array( 'exclude' => array( 3 ) ), 'category' ) );
		$this->assertSame( array(), salt_post_default_topic_off_sitemap( array(), 'service_category' ) );
		$this->assertSame( array( 'exclude' => array( 1 ) ), salt_post_primary_topic_choices( array() ), 'Never offered as a primary topic.' );

		$GLOBALS['salt_test_options']['default_category'] = 0;
		$this->assertFalse( salt_post_topic_is_default( 0 ), 'No default category, nothing is refused.' );
	}

	public function test_the_topic_name_count_loads_on_the_topic_screens_only(): void {
		$loaded  = array();
		$screens = array(
			'edit-tags.php service_category' => array( 'edit-tags.php', 'taxnow', 'service_category' ),
			'term.php work_type'             => array( 'term.php', 'taxnow', 'work_type' ),
			'edit-tags.php faq_category'     => array( 'edit-tags.php', 'taxnow', 'faq_category' ),
			'term.php location_area'         => array( 'term.php', 'taxnow', 'location_area' ),
			'edit-tags.php category'         => array( 'edit-tags.php', 'taxnow', 'category' ),
			'term.php category'              => array( 'term.php', 'taxnow', 'category' ),
			'term.php post_tag'              => array( 'term.php', 'taxnow', 'post_tag' ),
			'post-new.php service'           => array( 'post-new.php', 'typenow', 'service' ),
			'post.php work'                  => array( 'post.php', 'typenow', 'work' ),
			'post-new.php post'              => array( 'post-new.php', 'typenow', 'post' ),
			'edit.php service'               => array( 'edit.php', 'typenow', 'service' ),
		);
		foreach ( $screens as $label => list( $hook, $global, $value ) ) {
			unset( $GLOBALS['taxnow'], $GLOBALS['typenow'] );
			$GLOBALS['salt_test_inline_scripts'] = array();
			$GLOBALS[ $global ]                  = $value;
			salt_topic_name_count_enqueue( $hook );
			$loaded[ $label ] = $GLOBALS['salt_test_inline_scripts']['salt-character-count']['before'] ?? array();
		}
		unset( $GLOBALS['taxnow'], $GLOBALS['typenow'] );

		$add  = array( 'window.saltCharacterCount = ( window.saltCharacterCount || [] ).concat( [{"field":"tag-name","target":120,"marks":[{"at":120,"note":"Over the suggested 120 characters","strong":true}]}] );' );
		$edit = array( 'window.saltCharacterCount = ( window.saltCharacterCount || [] ).concat( [{"field":"name","target":120,"marks":[{"at":120,"note":"Over the suggested 120 characters","strong":true}]}] );' );
		$this->assertSame(
			array(
				'edit-tags.php service_category' => $add,
				'term.php work_type'             => $edit,
				'edit-tags.php faq_category'     => $add,
				'term.php location_area'         => $edit,
				'edit-tags.php category'         => $add,
				'term.php category'              => $edit,
				'term.php post_tag'              => array(),
				'post-new.php service'           => array(),
				'post.php work'                  => array(),
				'post-new.php post'              => array(),
				'edit.php service'               => array(),
			),
			$loaded,
			'The term add and edit screens of the topics taxonomies and posts\' categories; not the Topics box on a post screen.'
		);
	}
}
