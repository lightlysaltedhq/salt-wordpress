<?php
/**
 * Salt_Nav_Walker — the core-menu walker for the salt chrome (spec §12a).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nav menu walker: one submenu level max, disclosure-pattern submenus,
 * aria-current on the current item (§12a).
 *
 * Markup contract (structural change = MAJOR bump, §4/§12):
 * - Top-level items: <li class="x-nav-item …"><a class="x-nav-link" …>.
 * - The current item's link carries aria-current="page".
 * - Submenu parents additionally render a <button type="button"
 *   class="x-nav-toggle" aria-expanded="false" aria-controls="{submenu id}"
 *   data-salt-nav-toggle> after the link, then <ul class="x-nav-submenu"
 *   id="x-nav-submenu-{item id}"> with the depth-1 items.
 * - DEPTH CAP: exactly one submenu level. start_lvl()/end_lvl() at depth 1+
 *   emit nothing (logged once), and items deeper than depth 1 are dropped —
 *   wp_nav_menu callers should also pass 'depth' => 2.
 *
 * NO-JS BEHAVIOUR (deliberate — the drawer/disclosure JS is a later slice):
 * the submenu is hidden with the Tailwind 'hidden' utility and revealed
 * purely via CSS :focus-within — the parent <li> carries the arbitrary
 * variant [&:focus-within>.x-nav-submenu]:block, so keyboard users tabbing
 * onto the parent link, the toggle button, or any submenu link see the
 * submenu with zero JavaScript. The button is inert until the future JS
 * (keying off data-salt-nav-toggle) wires click handling and maintains
 * aria-expanded; the server-rendered aria-expanded="false" is the correct
 * pre-enhancement state.
 */
class Salt_Nav_Walker extends Walker_Nav_Menu {

	/**
	 * The submenu-parent item ID (start_el stashes it for start_lvl's
	 * submenu id — WP calls start_lvl immediately after the parent's
	 * start_el).
	 *
	 * @var int
	 */
	private $salt_parent_id = 0;

	/**
	 * Whether the depth cap has been logged this request.
	 *
	 * @var bool
	 */
	private $salt_depth_logged = false;

	/**
	 * Open a submenu <ul> — first level only (§12a depth cap).
	 *
	 * @param string        $output Passed by reference; appended to.
	 * @param int           $depth  Menu depth (0 = the first submenu).
	 * @param stdClass|null $args   wp_nav_menu() args object.
	 * @return void
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		if ( $depth >= 1 ) {
			if ( ! $this->salt_depth_logged ) {
				salt_log( 'Salt_Nav_Walker: menu nests deeper than one submenu level — deeper levels are not rendered (§12a).' );
				$this->salt_depth_logged = true;
			}
			return;
		}

		$output .= '<ul id="' . esc_attr( 'x-nav-submenu-' . $this->salt_parent_id ) . '" class="x-nav-submenu hidden absolute left-0 top-full z-50 min-w-48 rounded-md border border-border bg-surface p-2 shadow-lg">';
	}

	/**
	 * Close a submenu <ul> — mirrors the start_lvl() depth cap.
	 *
	 * @param string        $output Passed by reference; appended to.
	 * @param int           $depth  Menu depth.
	 * @param stdClass|null $args   wp_nav_menu() args object.
	 * @return void
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		if ( $depth >= 1 ) {
			return;
		}

		$output .= '</ul>';
	}

	/**
	 * Render a menu item's opening markup.
	 *
	 * @param string        $output            Passed by reference; appended to.
	 * @param WP_Post       $data_object       Menu item (decorated WP_Post).
	 * @param int           $depth             Item depth (0 = top level).
	 * @param stdClass|null $args              wp_nav_menu() args object.
	 * @param int           $current_object_id Current object ID (unused).
	 * @return void
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
		if ( $depth > 1 ) {
			return; // Items beyond the one-submenu cap are dropped (start_lvl logged it).
		}

		$item    = $data_object;
		$item_id = (int) ( $item->ID ?? 0 );
		$title   = (string) ( $item->title ?? '' );
		$url     = (string) ( $item->url ?? '' );
		$current = ! empty( $item->current );
		$target  = (string) ( $item->target ?? '' );

		// wp_nav_menu() adds 'menu-item-has-children'; Walker::display_element
		// sets $this->has_children — accept either (direct unit calls use one).
		$item_classes = array_filter( array_map( 'strval', (array) ( $item->classes ?? array() ) ) );
		$has_children = ! empty( $this->has_children ) || in_array( 'menu-item-has-children', $item_classes, true );
		$is_parent    = $has_children && 0 === $depth;

		if ( $is_parent ) {
			$this->salt_parent_id = $item_id;
		}

		$li_classes = salt_classnames(
			array_merge(
				array( 0 === $depth ? 'x-nav-item' : 'x-nav-subitem' ),
				$item_classes,
				// No-JS disclosure: reveal the submenu on focus-within (see class docblock).
				$is_parent ? array( 'relative', '[&:focus-within>.x-nav-submenu]:block' ) : array()
			)
		);

		$link_classes = 0 === $depth
			? 'x-nav-link inline-flex items-center py-2 text-ink hover:text-brand'
			: 'x-nav-sublink block rounded px-3 py-2 text-ink hover:bg-surface-alt';

		$atts  = ' class="' . esc_attr( $link_classes ) . '" href="' . esc_url( $url ) . '"';
		$atts .= $current ? ' aria-current="page"' : '';
		$atts .= '_blank' === $target ? ' target="_blank" rel="noopener noreferrer"' : '';

		$output .= '<li class="' . esc_attr( $li_classes ) . '">';
		$output .= '<a' . $atts . '>' . esc_html( $title ) . '</a>';

		if ( $is_parent ) {
			$output .= '<button type="button" class="x-nav-toggle inline-flex items-center p-2" aria-expanded="false" aria-controls="' . esc_attr( 'x-nav-submenu-' . $item_id ) . '" data-salt-nav-toggle>'
				. '<span class="visually-hidden">' . esc_html(
					sprintf(
						/* translators: %s: parent menu item title. */
						__( 'Show submenu for %s', 'salt' ),
						$title
					)
				) . '</span>'
				. '<span aria-hidden="true">&#9662;</span>'
				. '</button>';
		}
	}

	/**
	 * Render a menu item's closing markup.
	 *
	 * @param string        $output      Passed by reference; appended to.
	 * @param WP_Post       $data_object Menu item (unused).
	 * @param int           $depth       Item depth.
	 * @param stdClass|null $args        wp_nav_menu() args object.
	 * @return void
	 */
	public function end_el( &$output, $data_object, $depth = 0, $args = null ) {
		if ( $depth > 1 ) {
			return;
		}

		$output .= '</li>';
	}
}
