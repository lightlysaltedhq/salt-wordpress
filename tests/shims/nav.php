<?php
/**
 * WP shims for the site-chrome slice: nav menus + bloginfo.
 *
 * Fixtures:
 *   $GLOBALS['salt_test_menus'][ $location ] = string  Pre-rendered <ul>
 *     HTML that wp_nav_menu() echoes for that theme_location. An unset
 *     location triggers the fallback_cb, matching core's "no menu
 *     assigned" path.
 *   $GLOBALS['salt_test_menu_items'][ $location ] = object[]  Menu items
 *     (db_id, menu_item_parent, title, url, current, target) that
 *     wp_nav_menu() hands to the args' walker, as core does; used when no
 *     pre-rendered HTML is set for the location.
 *   $GLOBALS['salt_test_bloginfo'][ $show ]  = string  get_bloginfo values
 *     (shim identical in behaviour to the mail slice's superset shim —
 *     whichever loads first, both suites see their fixtures).
 *
 * Walker_Nav_Menu is a minimal base-class stub: just the properties and
 * method signatures Salt_Nav_Walker relies on (has_children is set by
 * core's Walker::display_element; unit tests set it directly).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $GLOBALS['salt_test_menus'] ) ) {
	$GLOBALS['salt_test_menus'] = array();
}

if ( ! function_exists( 'salt_test_menus_reset' ) ) {
	/**
	 * Reset the menu fixtures between tests.
	 *
	 * @return void
	 */
	function salt_test_menus_reset(): void {
		$GLOBALS['salt_test_menus']      = array();
		$GLOBALS['salt_test_menu_items'] = array();
	}
}

if ( ! class_exists( 'Walker_Nav_Menu' ) ) {
	/**
	 * Minimal Walker_Nav_Menu base-class stub (signatures mirror core).
	 */
	class Walker_Nav_Menu {

		/** @var string[] */
		public $tree_type = array( 'post_type', 'taxonomy', 'custom' );

		/** @var array<string,string> */
		public $db_fields = array(
			'parent' => 'menu_item_parent',
			'id'     => 'db_id',
		);

		/** @var bool Set by core's Walker::display_element before start_el. */
		public $has_children = false;

		/**
		 * Open a sub-level (no-op stub).
		 *
		 * @param string $output Passed by reference.
		 * @param int    $depth  Depth.
		 * @param mixed  $args   Args object.
		 * @return void
		 */
		public function start_lvl( &$output, $depth = 0, $args = null ) {
			unset( $output, $depth, $args );
		}

		/**
		 * Close a sub-level (no-op stub).
		 *
		 * @param string $output Passed by reference.
		 * @param int    $depth  Depth.
		 * @param mixed  $args   Args object.
		 * @return void
		 */
		public function end_lvl( &$output, $depth = 0, $args = null ) {
			unset( $output, $depth, $args );
		}

		/**
		 * Open an element (no-op stub).
		 *
		 * @param string $output            Passed by reference.
		 * @param mixed  $data_object       Menu item.
		 * @param int    $depth             Depth.
		 * @param mixed  $args              Args object.
		 * @param int    $current_object_id Current object ID.
		 * @return void
		 */
		public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
			unset( $output, $data_object, $depth, $args, $current_object_id );
		}

		/**
		 * Close an element (no-op stub).
		 *
		 * @param string $output      Passed by reference.
		 * @param mixed  $data_object Menu item.
		 * @param int    $depth       Depth.
		 * @param mixed  $args        Args object.
		 * @return void
		 */
		public function end_el( &$output, $data_object, $depth = 0, $args = null ) {
			unset( $output, $data_object, $depth, $args );
		}
	}
}

if ( ! function_exists( 'wp_nav_menu' ) ) {
	/**
	 * wp_nav_menu shim — echoes the deterministic fixture <ul> for the
	 * requested theme_location; calls fallback_cb when no fixture is set
	 * (core's "no menu assigned to this location" path).
	 *
	 * @param array<string,mixed> $args Menu args (theme_location, fallback_cb, echo honoured).
	 * @return string|void The menu HTML when 'echo' is false.
	 */
	function wp_nav_menu( array $args = array() ) {
		$location = (string) ( $args['theme_location'] ?? '' );
		$menu     = $GLOBALS['salt_test_menus'][ $location ] ?? null;

		// Menu items (objects with db_id, menu_item_parent, title, url,
		// current, target), handed to the walker as core does.
		$items = $GLOBALS['salt_test_menu_items'][ $location ] ?? null;
		if ( null === $menu && is_array( $items ) && array() !== $items && isset( $args['walker'] ) && is_object( $args['walker'] ) ) {
			$menu = (string) $args['walker']->walk( $items, (int) ( $args['depth'] ?? 0 ), (object) $args );
		}

		if ( null === $menu ) {
			$fallback = $args['fallback_cb'] ?? false;
			$menu     = ( $fallback && is_callable( $fallback ) ) ? (string) call_user_func( $fallback, $args ) : '';
		}

		if ( false === ( $args['echo'] ?? true ) ) {
			return $menu;
		}

		echo $menu; // Fixture/fallback HTML — test-only shim.
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * get_bloginfo shim (fixture-backed) — compatible SUPERSET of the
	 * schema slice's shim: $GLOBALS['salt_test_bloginfo'] wins, then the
	 * schema fixture's blogname for 'name', else '' (whichever shim file
	 * loads first, both suites see their fixtures).
	 *
	 * @param string $show Info key.
	 * @return string
	 */
	function get_bloginfo( string $show = '' ): string {
		if ( isset( $GLOBALS['salt_test_bloginfo'][ $show ] ) ) {
			return (string) $GLOBALS['salt_test_bloginfo'][ $show ];
		}
		if ( 'name' === $show ) {
			return (string) ( $GLOBALS['salt_test_schema']['blogname'] ?? '' );
		}
		return '';
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * esc_attr__ shim (defensive — also defined in tests/wp-shims.php).
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function esc_attr__( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return esc_attr( $text );
	}
}
