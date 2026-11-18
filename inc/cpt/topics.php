<?php
/**
 * Topics: the category terms of services, case studies, FAQs and locations.
 *
 * salt-contract rc.7 stores each collection's categories as topics
 * (contract/collections/_topic.json) and owes, on WordPress, "the taxonomy
 * box labelled Topics, with no parent control" (services.json,
 * case-studies.json, faqs.json and locations.json, topics) and "the parent
 * control removed from each taxonomy, so topics stay flat" (_topic.json,
 * title). Each taxonomy keeps its name and its checkbox box; this file
 * labels it Topics, takes away every place an editor could pick a parent
 * (the box's "add new" form and the term add and edit screens; quick edit
 * has none), and stores every term at the top level whatever is sent.
 *
 * Posts' categories are ID-3952's (unit 10) and are not touched here.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The taxonomies that hold topics.
 *
 * @return string[]
 */
function salt_topic_taxonomies(): array {
	return array( 'service_category', 'work_type', 'faq_category', 'location_area' );
}

/**
 * A topics taxonomy's labels: Topics, a Topic.
 *
 * @return array<string,string>
 */
function salt_topic_labels(): array {
	return array(
		'name'                       => __( 'Topics', 'salt' ),
		'singular_name'              => __( 'Topic', 'salt' ),
		'menu_name'                  => __( 'Topics', 'salt' ),
		'all_items'                  => __( 'All topics', 'salt' ),
		'edit_item'                  => __( 'Edit topic', 'salt' ),
		'view_item'                  => __( 'View topic', 'salt' ),
		'update_item'                => __( 'Update topic', 'salt' ),
		'add_new_item'               => __( 'Add new topic', 'salt' ),
		'new_item_name'              => __( 'New topic name', 'salt' ),
		'search_items'               => __( 'Search topics', 'salt' ),
		'popular_items'              => __( 'Popular topics', 'salt' ),
		'not_found'                  => __( 'No topics found', 'salt' ),
		'no_terms'                   => __( 'No topics', 'salt' ),
		'back_to_items'              => __( '&larr; Go to topics', 'salt' ),
		'items_list'                 => __( 'Topics list', 'salt' ),
		'items_list_navigation'      => __( 'Topics list navigation', 'salt' ),
		'separate_items_with_commas' => __( 'Separate topics with commas', 'salt' ),
		'add_or_remove_items'        => __( 'Add or remove topics', 'salt' ),
		'choose_from_most_used'      => __( 'Choose from the most used topics', 'salt' ),
	);
}

/**
 * The Topics box: WordPress's checkbox box with the parent control of its
 * "add new" form removed (its label and its select).
 *
 * @param WP_Post             $post The post.
 * @param array<string,mixed> $box  The meta box.
 * @return void
 */
function salt_topic_meta_box( $post, array $box ): void {
	$taxonomy = (string) ( $box['args']['taxonomy'] ?? '' );
	ob_start();
	post_categories_meta_box( $post, $box );
	$html  = (string) ob_get_clean();
	$field = preg_quote( 'new' . $taxonomy . '_parent', '/' );
	$html  = (string) preg_replace( '/<label[^>]*for="' . $field . '"[^>]*>.*?<\/label>\s*/s', '', $html );
	$html  = (string) preg_replace( '/<select[^>]*name=[\'"]' . $field . '[\'"][^>]*>.*?<\/select>\s*/s', '', $html );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core's own meta box markup, with the parent control taken out.
}

/**
 * On the term add and edit screens of a topics taxonomy, treat it as flat,
 * so WordPress draws no Parent field ('load-edit-tags.php', 'load-term.php').
 *
 * @return void
 */
function salt_topic_screens_without_parent(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read to pick the screen, nothing is saved.
	$taxonomy = isset( $_GET['taxonomy'] ) && is_string( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
	if ( in_array( $taxonomy, salt_topic_taxonomies(), true ) && taxonomy_exists( $taxonomy ) ) {
		get_taxonomy( $taxonomy )->hierarchical = false;
	}
}
add_action( 'load-edit-tags.php', 'salt_topic_screens_without_parent' );
add_action( 'load-term.php', 'salt_topic_screens_without_parent' );

/**
 * Keep a topic at the top level when it is updated ('wp_update_term_parent').
 *
 * @param int    $parent_id The parent asked for.
 * @param int    $term_id   The term.
 * @param string $taxonomy  The taxonomy.
 * @return int
 */
function salt_topic_update_parent( $parent_id, $term_id, $taxonomy ): int {
	return in_array( $taxonomy, salt_topic_taxonomies(), true ) ? 0 : (int) $parent_id;
}
add_filter( 'wp_update_term_parent', 'salt_topic_update_parent', 10, 3 );

/**
 * Move a topic inserted under a parent to the top level ('created_term'):
 * wp_insert_term() has no filter for the parent, so the term is updated
 * straight after, through salt_topic_update_parent().
 *
 * @param int    $term_id  The term.
 * @param int    $tt_id    The term taxonomy ID.
 * @param string $taxonomy The taxonomy.
 * @return void
 */
function salt_topic_insert_flat( $term_id, $tt_id, $taxonomy ): void {
	if ( ! in_array( $taxonomy, salt_topic_taxonomies(), true ) ) {
		return;
	}
	$term = get_term( (int) $term_id, $taxonomy );
	if ( $term instanceof WP_Term && 0 !== (int) $term->parent ) {
		wp_update_term( (int) $term_id, $taxonomy, array( 'parent' => 0 ) );
	}
}
add_action( 'created_term', 'salt_topic_insert_flat', 10, 3 );
