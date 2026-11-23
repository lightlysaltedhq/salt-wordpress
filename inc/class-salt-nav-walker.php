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
 * Nav menu walker for the contract's markup (salt-contract
 * markup/site-header.json, site-footer.json).
 *
 * walk() reads the menu wp_nav_menu() hands it into a tree and returns the
 * bar's items (the <li>s of salt-nav__list), so a child theme that calls
 * wp_nav_menu() with this walker gets the contract's markup. Only the
 * primary location's submenus take the header's ids; another menu's take
 * site-menu-<location>__submenu-<n> (salt_submenu_prefix()). The chrome
 * itself reads tree() through salt_menu() and draws it once in the bar and
 * again in the drawer, or as the footer's columns.
 *
 * wp_nav_menu() still does what it owns: finding the location's menu,
 * marking the current item (_wp_menu_item_classes_by_context()) and calling
 * the fallback when no menu is assigned. Each item keeps what an editor or
 * plugin gives it, as Walker_Nav_Menu::start_el() does: its title attribute
 * (attr_title), target and rel (xfn, so rel="me" holds), the
 * nav_menu_link_attributes filter's attributes and the nav_menu_item_title
 * filter's title. The contract fixes the classes, so an item's CSS classes
 * and the nav_menu_css_class filter do not apply, and its aria-current and
 * href stay the theme's (an href the filter changes is used).
 *
 * The tree is one submenu level deep (§12a): items nested deeper are dropped
 * and logged once. Each node is { label (text), label_html, href (null
 * without a usable URL), current, atts (more <a> attributes), children }.
 */
class Salt_Nav_Walker extends Walker_Nav_Menu {

	/**
	 * The menu read by the last walk().
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $salt_tree = array();

	/**
	 * Read the menu into the tree and draw the bar's items.
	 *
	 * @param array<int,object> $elements  Menu items, in menu order.
	 * @param int               $max_depth Unused: the depth is always one submenu level.
	 * @param mixed             ...$args   wp_nav_menu() args (an object).
	 * @return string The bar's <li> items (salt_header_nav_items_html()).
	 */
	public function walk( $elements, $max_depth, ...$args ) {
		unset( $max_depth );
		$menu_args       = isset( $args[0] ) && is_object( $args[0] ) ? $args[0] : new stdClass();
		$this->salt_tree = array();

		$top = array();
		foreach ( (array) $elements as $item ) {
			$id     = (int) ( $item->db_id ?? $item->ID ?? 0 );
			$parent = (int) ( $item->menu_item_parent ?? 0 );
			if ( 0 === $parent ) {
				$top[ $id ] = array_merge( self::salt_node( $item, $menu_args, 0 ), array( 'children' => array() ) );
			} elseif ( isset( $top[ $parent ] ) ) {
				$top[ $parent ]['children'][] = self::salt_node( $item, $menu_args, 1 );
			} else {
				salt_log( 'Salt_Nav_Walker: menu nests deeper than one submenu level — deeper levels are not rendered (§12a).' );
			}
		}

		$this->salt_tree = array_values( $top );
		return salt_header_nav_items_html( $this->salt_tree, salt_chrome_labels( 'header' ), self::salt_submenu_prefix( $menu_args ) );
	}

	/**
	 * The submenu ids' prefix for the menu being walked. The header's
	 * (primary) are the contract's site-header__submenu-<n> (SC-012); any
	 * other menu takes its own, named by its location (or, without one, its
	 * menu), so two menus on a page never share an id. Each contains __,
	 * which no editor's anchor can.
	 *
	 * @param object $menu_args wp_nav_menu() args.
	 * @return string
	 */
	private static function salt_submenu_prefix( $menu_args ): string {
		$location = (string) ( $menu_args->theme_location ?? '' );
		if ( 'primary' === $location ) {
			return 'site-header__submenu-';
		}
		$menu  = $menu_args->menu ?? '';
		$owner = '' !== $location ? $location : ( is_object( $menu ) ? (string) ( $menu->slug ?? $menu->term_id ?? '' ) : ( is_scalar( $menu ) ? (string) $menu : '' ) );
		$owner = sanitize_key( $owner );
		return 'site-menu-' . ( '' === $owner ? 'menu' : $owner ) . '__submenu-';
	}

	/**
	 * The menu read by the last walk().
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function tree(): array {
		return $this->salt_tree;
	}

	/**
	 * One menu item as a node. A URL that is empty or only '#' is no URL:
	 * the contract draws such an item's label as a span.
	 *
	 * @param object $item      Menu item (decorated WP_Post).
	 * @param object $menu_args wp_nav_menu() args.
	 * @param int    $depth     0 for a top-level item, 1 for a submenu's.
	 * @return array<string,mixed>
	 */
	private static function salt_node( $item, $menu_args, int $depth ): array {
		$title  = (string) ( $item->title ?? '' );
		$target = (string) ( $item->target ?? '' );
		$xfn    = trim( (string) ( $item->xfn ?? '' ) );

		// The attributes Walker_Nav_Menu::start_el() gives a link, through
		// its filter, so plugins and child themes keep working.
		$atts = (array) apply_filters(
			'nav_menu_link_attributes', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's hook, applied as Walker_Nav_Menu does.
			array(
				'title'        => (string) ( $item->attr_title ?? '' ),
				'target'       => $target,
				'rel'          => '' === $xfn && '_blank' === $target ? 'noopener noreferrer' : $xfn,
				'href'         => (string) ( $item->url ?? '' ),
				'aria-current' => empty( $item->current ) ? '' : 'page',
			),
			$item,
			$menu_args,
			$depth
		);

		$url      = trim( is_scalar( $atts['href'] ?? null ) ? (string) $atts['href'] : '' );
		$filtered = apply_filters( 'nav_menu_item_title', $title, $item, $menu_args, $depth ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's hook, applied as Walker_Nav_Menu does.
		$filtered = is_string( $filtered ) ? $filtered : $title;

		// The contract owns the class list, aria-current and tabindex.
		$more = array();
		foreach ( $atts as $name => $value ) {
			if ( is_string( $name ) && ! in_array( strtolower( $name ), array( 'href', 'class', 'aria-current', 'tabindex' ), true ) && is_scalar( $value ) && '' !== (string) $value ) {
				$more[ strtolower( $name ) ] = (string) $value;
			}
		}

		return array(
			'label'      => wp_strip_all_tags( $title ),
			// An editor's title is text; a filter's title may carry markup.
			'label_html' => $filtered === $title ? esc_html( $title ) : wp_kses_post( $filtered ),
			'href'       => '' === $url || '#' === $url ? null : $url,
			'current'    => ! empty( $item->current ),
			'atts'       => $more,
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
