<?php
/**
 * WP shims for a post's terms.
 *
 * Backed by $GLOBALS['salt_test_post_terms'][ {post ID} ][ {taxonomy} ] =
 * WP_Term[] (in the order get_the_terms() returns them).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Term' ) ) {
	/**
	 * Minimal WP_Term fixture class.
	 */
	#[\AllowDynamicProperties]
	class WP_Term {
 // phpcs:ignore Generic.Files.OneObjectStructurePerFile

		/** @var int */
		public $term_id = 0;
		/** @var string */
		public $name = '';
		/** @var string */
		public $slug = '';
		/** @var string */
		public $taxonomy = '';

		/**
		 * Build from an array of properties.
		 *
		 * @param array<string,mixed> $props Properties.
		 */
		public function __construct( array $props = array() ) {
			foreach ( $props as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

if ( ! function_exists( 'get_the_terms' ) ) {
	/**
	 * get_the_terms shim (fixture-backed).
	 *
	 * @param int|WP_Post $post     Post or ID.
	 * @param string      $taxonomy Taxonomy.
	 * @return WP_Term[]|false
	 */
	function get_the_terms( $post, string $taxonomy ) {
		$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
		$terms   = $GLOBALS['salt_test_post_terms'][ $post_id ][ $taxonomy ] ?? array();
		return array() === $terms ? false : $terms;
	}
}
