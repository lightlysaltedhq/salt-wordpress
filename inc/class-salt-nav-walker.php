<?php
/**
 * Salt_Nav_Walker — reads a core menu for the salt chrome (spec §12a).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nav menu walker that draws nothing: it reads the menu wp_nav_menu() hands
 * it into a tree the chrome draws as the contract's markup
 * (salt-contract markup/site-header.json, site-footer.json), once in the
 * bar and again in the drawer, or as the footer's columns.
 *
 * wp_nav_menu() still does what it owns: finding the location's menu,
 * marking the current item (_wp_menu_item_classes_by_context()) and calling
 * the fallback when no menu is assigned. Call it with 'echo' => false and
 * this walker, then read tree().
 *
 * The tree is one submenu level deep (§12a): items nested deeper are dropped
 * and logged once. Each node is
 * { label, href (null without a usable URL), current, target, children }.
 */
class Salt_Nav_Walker extends Walker_Nav_Menu {

	/**
	 * The menu read by the last walk().
	 *
	 * @var array<int,array{label:string,href:?string,current:bool,target:string,children:array<int,array<string,mixed>>}>
	 */
	private $salt_tree = array();

	/**
	 * Read the menu into the tree; draws nothing.
	 *
	 * @param array<int,object> $elements  Menu items, in menu order.
	 * @param int               $max_depth Unused: the depth is always one submenu level.
	 * @param mixed             ...$args   wp_nav_menu() args.
	 * @return string Always ''.
	 */
	public function walk( $elements, $max_depth, ...$args ) {
		unset( $max_depth, $args );
		$this->salt_tree = array();

		$top = array();
		foreach ( (array) $elements as $item ) {
			$id     = (int) ( $item->db_id ?? $item->ID ?? 0 );
			$parent = (int) ( $item->menu_item_parent ?? 0 );
			if ( 0 === $parent ) {
				$top[ $id ] = array_merge( self::salt_node( $item ), array( 'children' => array() ) );
			} elseif ( isset( $top[ $parent ] ) ) {
				$top[ $parent ]['children'][] = self::salt_node( $item );
			} else {
				salt_log( 'Salt_Nav_Walker: menu nests deeper than one submenu level — deeper levels are not rendered (§12a).' );
			}
		}

		$this->salt_tree = array_values( $top );
		return '';
	}

	/**
	 * The menu read by the last walk().
	 *
	 * @return array<int,array{label:string,href:?string,current:bool,target:string,children:array<int,array<string,mixed>>}>
	 */
	public function tree(): array {
		return $this->salt_tree;
	}

	/**
	 * One menu item as a node. A URL that is empty or only '#' is no URL:
	 * the contract draws such an item's label as a span.
	 *
	 * @param object $item Menu item (decorated WP_Post).
	 * @return array{label:string,href:?string,current:bool,target:string}
	 */
	private static function salt_node( $item ): array {
		$url = trim( (string) ( $item->url ?? '' ) );
		return array(
			'label'   => (string) ( $item->title ?? '' ),
			'href'    => '' === $url || '#' === $url ? null : $url,
			'current' => ! empty( $item->current ),
			'target'  => '_blank' === (string) ( $item->target ?? '' ) ? '_blank' : '',
		);
	}

	/**
	 * Read a location's menu: wp_nav_menu() with this walker, nothing printed.
	 *
	 * @param string $location Theme location (primary, footer).
	 * @return array<int,array<string,mixed>> The tree; empty when no menu is assigned (the fallback logs it).
	 */
	public static function salt_menu( string $location ): array {
		$walker = new self();
		wp_nav_menu(
			array(
				'theme_location' => $location,
				'container'      => false,
				'depth'          => 2,
				'echo'           => false,
				'walker'         => $walker,
				'fallback_cb'    => 'salt_nav_menu_fallback',
			)
		);
		return $walker->tree();
	}
}
