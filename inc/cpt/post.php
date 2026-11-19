<?php
/**
 * WordPress's own post type, as salt-contract rc.7 stores a post
 * (contract/collections/posts.json; the founder's rulings of 18/11/2026).
 *
 * - The body is the `layout` field (the section builder), so posts lose the
 *   classic editor.
 * - Tags are drawn from topics: the post_tag taxonomy (the Tags box) is
 *   taken off posts.
 * - WordPress's categories are the posts' topics: the box and the screens
 *   are labelled Topics (salt_topic_labels()), offer no parent, and every
 *   category is stored at the top level whatever is sent, as the other
 *   collections' topics are (inc/cpt/topics.php).
 * - The primary topic, a field of the theme's own (WordPress has no primary
 *   category), is drawn in the Topics box, under its checklist, not in the
 *   post's field group.
 *
 * Only the parent, the labels and the name's count are shared with
 * inc/cpt/topics.php (salt_topic_name_count_taxonomies()): the category
 * taxonomy is not one of salt_topic_taxonomies(), so nothing else that file
 * does to topics reaches posts' categories.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The primary topic field's key (inc/fields/generated/collections.json).
 */
const SALT_POST_PRIMARY_TOPIC_FIELD = 'field_salt_collection_posts_primary_topic';

/**
 * Take the classic editor and the Tags box off posts ('init').
 *
 * @return void
 */
function salt_post_type_supports(): void {
	remove_post_type_support( 'post', 'editor' );
	unregister_taxonomy_for_object_type( 'post_tag', 'post' );
}
add_action( 'init', 'salt_post_type_supports' );

/**
 * Posts' categories as topics: labelled Topics, drawn by the box without
 * its parent control and with the primary topic ('register_taxonomy_args').
 *
 * @param array<string,mixed> $args     The taxonomy's arguments.
 * @param string              $taxonomy The taxonomy.
 * @return array<string,mixed>
 */
function salt_post_topics_taxonomy_args( array $args, string $taxonomy ): array {
	if ( 'category' !== $taxonomy ) {
		return $args;
	}
	$args['labels']      = salt_topic_labels();
	$args['meta_box_cb'] = 'salt_post_topics_meta_box';
	return $args;
}
add_filter( 'register_taxonomy_args', 'salt_post_topics_taxonomy_args', 10, 2 );

/**
 * Whether the Topics box is drawing the primary topic now, or set it.
 *
 * @param bool|null $set True or false to set, null to read.
 * @return bool
 */
function salt_post_drawing_topics_box( ?bool $set = null ): bool {
	static $drawing = false;
	if ( null !== $set ) {
		$drawing = $set;
	}
	return $drawing;
}

/**
 * The posts' Topics box: the topics box (salt_topic_meta_box(): no parent
 * control), then the primary topic field, drawn by ACF so it validates and
 * saves with the post's other fields.
 *
 * @param WP_Post             $post The post.
 * @param array<string,mixed> $box  The meta box.
 * @return void
 */
function salt_post_topics_meta_box( $post, array $box ): void {
	salt_topic_meta_box( $post, $box );
	if ( ! function_exists( 'acf_render_fields' ) ) {
		return;
	}
	$field = acf_get_field( SALT_POST_PRIMARY_TOPIC_FIELD );
	if ( ! is_array( $field ) ) {
		return;
	}
	salt_post_drawing_topics_box( true );
	echo '<div class="salt-primary-topic" style="margin-top:12px">';
	acf_render_fields( array( $field ), (int) $post->ID, 'div', 'label' );
	echo '</div>';
	salt_post_drawing_topics_box( false );
}

/**
 * Draw the primary topic only in the Topics box, not in the post's field
 * group ('acf/prepare_field/key=…'). It stays in the generated group, so
 * it is stored and read as the contract emits it.
 *
 * @param array<string,mixed>|false $field The field.
 * @return array<string,mixed>|false
 */
function salt_post_primary_topic_in_box( $field ) {
	return salt_post_drawing_topics_box() ? $field : false;
}
add_filter( 'acf/prepare_field/key=' . SALT_POST_PRIMARY_TOPIC_FIELD, 'salt_post_primary_topic_in_box' );

/**
 * Leave WordPress's default category out of the primary topic's choices
 * ('acf/fields/taxonomy/query/key=…'): it is never drawn as a topic.
 *
 * @param array<string,mixed> $args The get_terms() arguments.
 * @return array<string,mixed>
 */
function salt_post_primary_topic_choices( array $args ): array {
	$args['exclude'] = array( (int) get_option( 'default_category' ) );
	return $args;
}
add_filter( 'acf/fields/taxonomy/query/key=' . SALT_POST_PRIMARY_TOPIC_FIELD, 'salt_post_primary_topic_choices' );

/**
 * Whether a category is WordPress's built-in default ("Uncategorised").
 *
 * @param int $term_id The category.
 * @return bool
 */
function salt_post_topic_is_default( int $term_id ): bool {
	$default = (int) get_option( 'default_category' );
	return $default > 0 && $default === $term_id;
}

/**
 * The default category's archive is not found (the founder's ruling of
 * 19/11/2026): a post's topics never include "Uncategorised", so its
 * archive answers 404 with the theme's not-found page
 * ('template_redirect', before the template is chosen).
 *
 * @return void
 */
function salt_post_default_topic_not_found(): void {
	$term = is_category() ? get_queried_object() : null;
	if ( ! $term instanceof WP_Term || ! salt_post_topic_is_default( (int) $term->term_id ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'salt_post_default_topic_not_found', 0 );

/**
 * Leave the default category out of WordPress's sitemap
 * ('wp_sitemaps_taxonomies_query_args'), since its archive is not found.
 *
 * @param array<string,mixed> $args     The get_terms() arguments.
 * @param string              $taxonomy The taxonomy.
 * @return array<string,mixed>
 */
function salt_post_default_topic_off_sitemap( array $args, string $taxonomy ): array {
	if ( 'category' === $taxonomy ) {
		$args['exclude'] = array_merge( (array) ( $args['exclude'] ?? array() ), array( (int) get_option( 'default_category' ) ) );
	}
	return $args;
}
add_filter( 'wp_sitemaps_taxonomies_query_args', 'salt_post_default_topic_off_sitemap', 10, 2 );

/**
 * On the category add and edit screens, treat categories as flat, so
 * WordPress draws no Parent field ('load-edit-tags.php', 'load-term.php').
 *
 * @return void
 */
function salt_post_topic_screens_without_parent(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read to pick the screen, nothing is saved.
	$taxonomy = isset( $_GET['taxonomy'] ) && is_string( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
	if ( 'category' === $taxonomy ) {
		get_taxonomy( 'category' )->hierarchical = false;
	}
}
add_action( 'load-edit-tags.php', 'salt_post_topic_screens_without_parent' );
add_action( 'load-term.php', 'salt_post_topic_screens_without_parent' );

/**
 * Keep a category at the top level when it is updated ('wp_update_term_parent').
 *
 * @param int    $parent_id The parent asked for.
 * @param int    $term_id   The term.
 * @param string $taxonomy  The taxonomy.
 * @return int
 */
function salt_post_topic_update_parent( $parent_id, $term_id, $taxonomy ): int {
	return 'category' === $taxonomy ? 0 : (int) $parent_id;
}
add_filter( 'wp_update_term_parent', 'salt_post_topic_update_parent', 10, 3 );

/**
 * Move a category inserted under a parent to the top level ('created_term'),
 * as salt_topic_insert_flat() does for the other topics.
 *
 * @param int    $term_id  The term.
 * @param int    $tt_id    The term taxonomy ID.
 * @param string $taxonomy The taxonomy.
 * @return void
 */
function salt_post_topic_insert_flat( $term_id, $tt_id, $taxonomy ): void {
	if ( 'category' !== $taxonomy ) {
		return;
	}
	$term = get_term( (int) $term_id, 'category' );
	if ( $term instanceof WP_Term && 0 !== (int) $term->parent ) {
		wp_update_term( (int) $term_id, 'category', array( 'parent' => 0 ) );
	}
}
add_action( 'created_term', 'salt_post_topic_insert_flat', 10, 3 );
