<?php
/**
 * Site-chrome service (spec §12a): the option-driven helpers behind the
 * header/footer parts and the runtime CSS seam the theme-vars emitter
 * composes.
 *
 * Runtime seams consumed elsewhere: salt_header_runtime_css() is composed
 * by salt_output_theme_vars_css() (inc/setup.php, wp_head 20) into the one
 * nonce-stamped <style id="salt-theme-vars"> — numeric tokens only, never
 * inline style="" attributes (SEC06/CSP).
 *
 * Every function degrades safely without ACF (DEP01) and fails loud via
 * salt_log() on invalid data — never a silent blank (ARCH05/FAIL01).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Logo height + the header runtime CSS seam.
// ---------------------------------------------------------------------------

/**
 * Validate a raw logo_height option value (PURE — unit-tested).
 *
 * Bounds mirror the field definition (inc/fields/options-logo-fonts.php):
 * 16–200px, default 48. Out-of-range values clamp LOUDLY; non-numeric junk
 * degrades to the default LOUDLY; an unset option is silently the default.
 *
 * @param mixed $raw Raw option value (int, numeric string, null, junk).
 * @return int Validated height in px.
 */
function salt_logo_height_value( $raw ): int {
	if ( null === $raw || '' === $raw || false === $raw ) {
		return 48;
	}

	if ( ! is_numeric( $raw ) ) {
		salt_log( sprintf( 'chrome: non-numeric logo_height "%s" — using the 48px default.', is_scalar( $raw ) ? (string) $raw : get_debug_type( $raw ) ) );
		return 48;
	}

	$height = (int) $raw;

	if ( $height < 16 ) {
		salt_log( sprintf( 'chrome: logo_height %d is below the 16px minimum — clamped.', $height ) );
		return 16;
	}

	if ( $height > 200 ) {
		salt_log( sprintf( 'chrome: logo_height %d is above the 200px maximum — clamped.', $height ) );
		return 200;
	}

	return $height;
}

/**
 * The validated logo height option (px).
 *
 * @return int
 */
function salt_logo_height(): int {
	return salt_logo_height_value( salt_acf_active() ? salt_option( 'logo_height' ) : null );
}

/**
 * The logo height the site draws (px), or null when the option holds no
 * value at all. ACF returns the field's default (48) for an option never
 * saved, and an emptied field is the 48px default too, so every site keeps
 * its height; only a site without the field's default (the contract
 * adapter's shims) draws no style, as the contract's site-logo does, and
 * salt.css's 2rem applies.
 *
 * @return int|null
 */
function salt_logo_height_set(): ?int {
	$raw = salt_acf_active() ? salt_option( 'logo_height' ) : null;
	return null === $raw ? null : salt_logo_height_value( $raw );
}

/**
 * Header runtime CSS — the seam salt_output_theme_vars_css() composes.
 *
 * Emits the validated --logo-height on .salt-logo (48px unless the site
 * sets another). The logo also draws it as its contract style attribute,
 * which the CSP refuses (no 'unsafe-inline' for style attributes, SEC06),
 * so this nonce-stamped rule in the head is what applies it; it prints
 * after salt.css, so it wins over the stylesheet's 2rem. Adds the sticky-header anchor
 * offset --salt-scroll-padding ONLY when the header is sticky. Numeric
 * tokens only — safe to print unescaped inside the nonce-stamped <style>.
 *
 * @return string CSS, '' when ACF is absent (compiled defaults apply).
 */
function salt_header_runtime_css(): string {
	if ( ! salt_acf_active() ) {
		return '';
	}

	$height = salt_logo_height_set();
	$css    = null === $height ? '' : sprintf( '.salt-logo{--logo-height:%dpx}', $height );

	if ( (bool) salt_option( 'header_sticky' ) ) {
		// The logo's height (salt.css's 2rem when the option holds none) plus the bar's padding.
		$css .= sprintf( ':root{--salt-scroll-padding:calc(%dpx + 2.5rem)}', $height ?? 32 );
	}

	return $css;
}

// ---------------------------------------------------------------------------
// Header helpers: logo args + CTA.
// ---------------------------------------------------------------------------

/**
 * The site-logo component args from the Logo & Fonts options, for the
 * header or the footer (salt-contract markup/site-logo.json).
 *
 * @param string $context header|footer: the header's default-scheme logo loads eagerly, the footer's never.
 * @return array{light_id:int,dark_id:int,text:string,url:string,height:int,context:string}
 */
function salt_logo_args( string $context = 'header' ): array {
	$light = 0;
	$dark  = 0;
	$text  = '';

	if ( salt_acf_active() ) {
		$light = absint( salt_option( 'logo_light' ) );
		$dark  = absint( salt_option( 'logo_dark' ) );
		$text  = (string) ( salt_option( 'logo_text_fallback' ) ?? '' );
	}

	return array(
		'light_id' => $light,
		'dark_id'  => $dark,
		'text'     => $text,
		'url'      => home_url( '/' ),
		'height'   => salt_logo_height_set() ?? 0,
		'context'  => 'footer' === $context ? 'footer' : 'header',
	);
}

/**
 * Normalise an ACF link value into x-button args (PURE — unit-tested).
 *
 * Target follows the x-button contract ('' or '_blank' only — anything
 * else is dropped and logged; the component re-validates as
 * defence-in-depth). A link without a URL is "no CTA" (null), a link
 * without a title falls back to the URL as its label — never a blank
 * button (FAIL01).
 *
 * @param mixed $link Raw ACF link array (title, url, target) or anything else.
 * @return array{label:string,url:string,target:string}|null Null when no CTA is configured.
 */
function salt_header_cta( $link ): ?array {
	if ( ! is_array( $link ) ) {
		return null;
	}

	$url = trim( (string) ( $link['url'] ?? '' ) );
	if ( '' === $url ) {
		return null;
	}

	$title  = trim( (string) ( $link['title'] ?? '' ) );
	$target = (string) ( $link['target'] ?? '' );

	if ( ! in_array( $target, array( '', '_blank' ), true ) ) {
		salt_log( sprintf( 'chrome: invalid header_cta target "%s" — dropped.', $target ) );
		$target = '';
	}

	return array(
		'label'  => '' !== $title ? $title : $url,
		'url'    => $url,
		'target' => $target,
	);
}

/**
 * The header CTA from the Header & Footer options.
 *
 * @return array{label:string,url:string,target:string}|null
 */
function salt_header_cta_args(): ?array {
	return salt_acf_active() ? salt_header_cta( salt_option( 'header_cta' ) ) : null;
}

// ---------------------------------------------------------------------------
// Nav menu fallback (both locations).
// ---------------------------------------------------------------------------

/**
 * wp_nav_menu fallback_cb: render NOTHING, log once per location.
 *
 * An unassigned menu location is a configuration gap, not an error page —
 * the header/footer keep rendering (logo, CTA, copyright) while the gap is
 * visible in the log (ARCH05). Static once-per-location flag; $reset is
 * the test seam (see salt_nav_menu_fallback_reset()).
 *
 * @param array<string,mixed> $args  wp_nav_menu args (theme_location read).
 * @param bool                $reset Internal: clear the once-flags.
 * @return string Always ''.
 */
function salt_nav_menu_fallback( array $args = array(), bool $reset = false ): string {
	static $logged = array();

	if ( $reset ) {
		$logged = array();
		return '';
	}

	$location = (string) ( $args['theme_location'] ?? '' );

	if ( ! isset( $logged[ $location ] ) ) {
		$logged[ $location ] = true;
		salt_log( sprintf( 'chrome: no menu assigned to the "%s" location — nav rendered empty.', $location ) );
	}

	return '';
}

/**
 * Reset the fallback's once-per-location log flags (per request / per test).
 *
 * @return void
 */
function salt_nav_menu_fallback_reset(): void {
	salt_nav_menu_fallback( array(), true );
}

// ---------------------------------------------------------------------------
// Footer helpers: copyright + social row.
// ---------------------------------------------------------------------------

/**
 * The auto © line: current year (gmdate) + org_name, falling back to the
 * site title.
 *
 * @return string
 */
function salt_footer_copyright(): string {
	$name = salt_acf_active() ? trim( (string) ( salt_option( 'org_name' ) ?? '' ) ) : '';

	if ( '' === $name ) {
		$name = (string) get_bloginfo( 'name' );
	}

	$line  = trim( sprintf( '© %s %s', gmdate( 'Y' ), $name ) );
	$given = apply_filters( 'salt_footer_copyright', $line );
	return is_string( $given ) ? $given : $line;
}

/**
 * The known social hosts map: host => label + candidate icon name.
 *
 * salt-contract's table (contract/socials.json, 1.0.0-rc.7): which host
 * means which network, in the contract's order; a host matches itself or a
 * subdomain of it, never a substring (salt_social_link()). The footer's
 * social row and the team card's socials both read it.
 *
 * Labels are proper nouns (not translated). The icon is only USED when the
 * built-in set ships it (salt_chrome_icon_exists()) — otherwise the label
 * renders as text. The built-in set ships these social glyphs since 4.0.0
 * (shared with x-share-links and the team-card socials).
 *
 * @return array<string,array{label:string,icon:string}>
 */
function salt_chrome_social_hosts(): array {
	return array(
		'bsky.app'        => array(
			'label' => 'Bluesky',
			'icon'  => 'bluesky',
		),
		'bsky.social'     => array(
			'label' => 'Bluesky',
			'icon'  => 'bluesky',
		),
		'facebook.com'    => array(
			'label' => 'Facebook',
			'icon'  => 'facebook',
		),
		'fb.com'          => array(
			'label' => 'Facebook',
			'icon'  => 'facebook',
		),
		'fb.me'           => array(
			'label' => 'Facebook',
			'icon'  => 'facebook',
		),
		'github.com'      => array(
			'label' => 'GitHub',
			'icon'  => 'github',
		),
		'instagram.com'   => array(
			'label' => 'Instagram',
			'icon'  => 'instagram',
		),
		'linkedin.com'    => array(
			'label' => 'LinkedIn',
			'icon'  => 'linkedin',
		),
		'lnkd.in'         => array(
			'label' => 'LinkedIn',
			'icon'  => 'linkedin',
		),
		'pinterest.com'   => array(
			'label' => 'Pinterest',
			'icon'  => 'pinterest',
		),
		'pinterest.co.uk' => array(
			'label' => 'Pinterest',
			'icon'  => 'pinterest',
		),
		'pin.it'          => array(
			'label' => 'Pinterest',
			'icon'  => 'pinterest',
		),
		'reddit.com'      => array(
			'label' => 'Reddit',
			'icon'  => 'reddit',
		),
		'redd.it'         => array(
			'label' => 'Reddit',
			'icon'  => 'reddit',
		),
		'tiktok.com'      => array(
			'label' => 'TikTok',
			'icon'  => 'tiktok',
		),
		'whatsapp.com'    => array(
			'label' => 'WhatsApp',
			'icon'  => 'whatsapp',
		),
		'wa.me'           => array(
			'label' => 'WhatsApp',
			'icon'  => 'whatsapp',
		),
		'x.com'           => array(
			'label' => 'X',
			'icon'  => 'x',
		),
		'twitter.com'     => array(
			'label' => 'X',
			'icon'  => 'x',
		),
		't.co'            => array(
			'label' => 'X',
			'icon'  => 'x',
		),
		'youtube.com'     => array(
			'label' => 'YouTube',
			'icon'  => 'youtube',
		),
		'youtu.be'        => array(
			'label' => 'YouTube',
			'icon'  => 'youtube',
		),
	);
}

/**
 * Whether a built-in icon exists for the given name.
 *
 * @param string $name Icon name (assets/src/icons/{name}.svg).
 * @return bool
 */
function salt_chrome_icon_exists( string $name ): bool {
	return '' !== $name
		&& 1 === preg_match( '/^[a-z0-9-]+$/', $name )
		&& is_readable( get_template_directory() . '/assets/src/icons/' . $name . '.svg' );
}

/**
 * Resolve one sameAs URL into a footer social link (PURE — unit-tested).
 *
 * Known hosts (including their subdomains) get the platform label and —
 * when the built-in set ships one — an x-icon name; unknown hosts label as
 * their bare domain (www. stripped). URLs without a usable host are
 * dropped LOUDLY.
 *
 * @param string $url Profile URL (the org_same_as repeater, a team member's socials).
 * @return array{url:string,icon:string,label:string}|null Null when the URL is unusable.
 */
function salt_social_link( string $url ): ?array {
	$url  = trim( $url );
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

	if ( '' === $host ) {
		salt_log( sprintf( 'chrome: social URL "%s" has no host — skipped.', $url ) );
		return null;
	}

	$host = preg_replace( '/^www\./', '', $host ) ?? $host;

	foreach ( salt_chrome_social_hosts() as $known => $platform ) {
		if ( $host === $known || str_ends_with( $host, '.' . $known ) ) {
			return array(
				'url'   => $url,
				'icon'  => salt_chrome_icon_exists( $platform['icon'] ) ? $platform['icon'] : '',
				'label' => $platform['label'],
			);
		}
	}

	return array(
		'url'   => $url,
		'icon'  => '',
		'label' => $host,
	);
}

/**
 * The footer social row from the org_same_as repeater (Company & Schema).
 *
 * @return array<int,array{url:string,icon:string,label:string}>
 */
function salt_footer_social_links(): array {
	if ( ! salt_acf_active() ) {
		return array();
	}

	$rows  = salt_option( 'org_same_as' );
	$links = array();
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		$link = salt_social_link( is_array( $row ) ? (string) ( $row['url'] ?? '' ) : '' );
		if ( null !== $link ) {
			$links[] = $link;
		}
	}

	// The contract adapter's seam: a case names each profile itself.
	$given = apply_filters( 'salt_footer_social_links', $links );
	return is_array( $given ) ? $given : $links;
}

// ---------------------------------------------------------------------------
// The contract's chrome (salt-contract markup/site-header.json,
// site-footer.json): labels and the seams pending the founder's rulings.
// ---------------------------------------------------------------------------

/**
 * The chrome's labels (the contract's `labels.<name>`): translatable theme
 * strings, as the founder ruled for the collection labels, merged under
 * salt_contract_labels() so the one `salt_contract_labels` seam (and the
 * contract adapter) can give any of them.
 *
 * @param string $context header|footer: their navigation names differ.
 * @return array<string,string>
 */
function salt_chrome_labels( string $context ): array {
	$defaults = array(
		'navigation'  => 'footer' === $context ? __( 'Footer', 'salt' ) : __( 'Main', 'salt' ),
		/* translators: %s: the menu item that opens the submenu. */
		'submenu'     => __( 'Show submenu for %s', 'salt' ),
		'menu'        => __( 'Menu', 'salt' ),
		'closeMenu'   => __( 'Close menu', 'salt' ),
		'theme'       => __( 'Dark mode', 'salt' ),
		'social'      => __( 'Social media', 'salt' ),
		'preferences' => __( 'Cookie preferences', 'salt' ),
	);
	return array_merge( $defaults, salt_contract_labels() );
}

/**
 * Where the theme toggle is placed: 'header', 'footer' or null for none.
 *
 * Site Settings → Header & Footer, theme_toggle_placement (default none,
 * so no site changes); the `salt_theme_toggle_placement` filter is a
 * site's code override on top. A placed toggle takes the place of the
 * display preferences' dark-mode control.
 *
 * @return string|null
 */
function salt_theme_toggle_placement(): ?string {
	$stored    = salt_acf_active() ? salt_option( 'theme_toggle_placement' ) : null;
	$placement = apply_filters( 'salt_theme_toggle_placement', in_array( $stored, array( 'header', 'footer' ), true ) ? $stored : null );
	return in_array( $placement, array( 'header', 'footer' ), true ) ? $placement : null;
}

/**
 * The theme toggle as the contract draws it: the empty span[aria-hidden]
 * until script knows the scheme (SC-019), or the button form with the
 * scheme's glyph when the server knows it. The theme keeps the visitor's
 * choice in localStorage, which the server cannot read, so the server form
 * is the span; the `salt_theme_scheme` filter ('light'|'dark') is the seam
 * for a site that can know it (pending the same ruling).
 *
 * @param string $label The toggle's name (labels.theme).
 * @return string
 */
function salt_theme_toggle_html( string $label ): string {
	$scheme = apply_filters( 'salt_theme_scheme', null );
	if ( ! in_array( $scheme, array( 'light', 'dark' ), true ) ) {
		return '<span class="salt-theme-toggle" aria-hidden="true"></span>';
	}
	return '<button class="salt-theme-toggle" type="button" aria-pressed="' . ( 'dark' === $scheme ? 'true' : 'false' ) . '" aria-label="' . esc_attr( $label ) . '">'
		. salt_contract_icon( 'dark' === $scheme ? 'moon' : 'sun', 'sm' )
		. '</button>';
}

/**
 * The header's phone number, or null for none: the site's company phone
 * (Company & Schema, company_phone) when its "Show phone in header" switch
 * (header_show_phone, off by default) is on, with the `salt_header_phone`
 * filter as a site's code override (a number, or null or '' for none).
 * Drawn only when it normalises to a tel: link (salt_contract_tel(),
 * section#display-forms).
 *
 * @return array{text:string,tel:string}|null
 */
function salt_header_phone(): ?array {
	$stored = salt_acf_active() && true === (bool) salt_option( 'header_show_phone' ) ? salt_option( 'company_phone' ) : null;
	$phone  = apply_filters( 'salt_header_phone', is_string( $stored ) ? $stored : null );
	if ( ! is_string( $phone ) || '' === trim( $phone ) ) {
		return null;
	}
	$tel = salt_contract_tel( $phone );
	if ( null === $tel ) {
		salt_log( sprintf( 'chrome: header phone "%s" gives no tel: link — not drawn.', $phone ) );
		return null;
	}
	return array(
		'text' => trim( $phone ),
		'tel'  => $tel,
	);
}

/**
 * The header's navigation as the contract's markup, in the bar or the
 * drawer. Ids are fixed (SC-012): the bar's nav is site-navigation, the
 * drawer's site-header__drawer-navigation.
 *
 * @param array<int,array<string,mixed>> $menu   Salt_Nav_Walker tree.
 * @param array<string,string>           $labels salt_chrome_labels( 'header' ).
 * @param bool                           $drawer The drawer's copy.
 * @return string
 */
function salt_header_nav_html( array $menu, array $labels, bool $drawer = false ): string {
	return '<nav class="salt-nav" id="' . ( $drawer ? 'site-header__drawer-navigation' : 'site-navigation' ) . '" aria-label="' . esc_attr( $labels['navigation'] ) . '">'
		. '<ul class="salt-nav__list" role="list">'
		. salt_header_nav_items_html( $menu, $labels, $drawer ? 'site-header__drawer-submenu-' : 'site-header__submenu-' )
		. '</ul></nav>';
}

/**
 * The header navigation's items (the <li>s of salt-nav__list), which
 * Salt_Nav_Walker::walk() also returns. A submenu's id is $prefix<n>, <n>
 * being its item's position in the top-level menu counting from 1 (SC-012).
 *
 * The toggle's name is labels.submenu. The theme's default names the item
 * ("Show submenu for %s"); a label without %s, such as one the contract
 * adapter gives, is used as it is.
 *
 * @param array<int,array<string,mixed>> $menu   Salt_Nav_Walker tree.
 * @param array<string,string>           $labels salt_chrome_labels( 'header' ).
 * @param string                         $prefix The submenu id's prefix.
 * @return string
 */
function salt_header_nav_items_html( array $menu, array $labels, string $prefix ): string {
	$html = '';
	foreach ( array_values( $menu ) as $i => $item ) {
		$children = (array) $item['children'];
		$section  = array() !== array_filter( $children, static fn( $child ) => ! empty( $child['current'] ) );
		$id       = $prefix . ( $i + 1 );

		$html .= '<li class="salt-nav__item"' . ( $section ? ' data-current-section' : '' ) . '>';
		$html .= salt_nav_link_html( $item, 'salt-nav__link', array() !== $children );
		if ( array() !== $children ) {
			$name  = str_replace( '%s', (string) $item['label'], (string) $labels['submenu'] );
			$html .= '<span class="salt-nav__toggle" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr( $name ) . '" aria-hidden="true">'
				. salt_contract_icon( 'chevron-down', 'sm' ) . '</span>';
			$html .= '<ul class="salt-nav__submenu" id="' . esc_attr( $id ) . '" role="list">';
			foreach ( $children as $child ) {
				$html .= '<li class="salt-nav__subitem">' . salt_nav_link_html( $child, 'salt-nav__sublink', false ) . '</li>';
			}
			$html .= '</ul>';
		}
		$html .= '</li>';
	}
	return $html;
}

/**
 * A menu item's link: an a with its address and the item's own attributes
 * (title, target, rel and any the nav_menu_link_attributes filter adds), or
 * the span form for an item with no address (tabindex 0 when it opens a
 * submenu, so the keyboard can reach the submenu before script).
 *
 * @param array<string,mixed> $item       Tree node.
 * @param string              $class_name The link's class.
 * @param bool                $opens_sub  Whether it opens a submenu.
 * @return string
 */
function salt_nav_link_html( array $item, string $class_name, bool $opens_sub ): string {
	$label = (string) ( $item['label_html'] ?? esc_html( (string) $item['label'] ) );
	if ( null === $item['href'] ) {
		return '<span class="' . $class_name . '"' . ( $opens_sub ? ' tabindex="0"' : '' ) . '>' . $label . '</span>';
	}
	$atts  = ' href="' . esc_url( (string) $item['href'] ) . '"';
	$atts .= empty( $item['current'] ) ? '' : ' aria-current="page"';
	foreach ( (array) ( $item['atts'] ?? array() ) as $name => $value ) {
		$atts .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
	}
	return '<a class="' . $class_name . '"' . $atts . '>' . $label . '</a>';
}

/**
 * The header's call to action as the contract's button (link form).
 *
 * @param array{label:string,url:string,target:string} $cta salt_header_cta_args().
 * @return string
 */
function salt_header_cta_html( array $cta ): string {
	$atts = '_blank' === $cta['target'] ? ' target="_blank" rel="noopener noreferrer"' : '';
	return '<a class="salt-button salt-header__cta" href="' . esc_url( $cta['url'] ) . '" data-style="primary" data-track-control="cta"' . $atts . '>' . esc_html( $cta['label'] ) . '</a>';
}

/**
 * The header's phone link.
 *
 * @param array{text:string,tel:string} $phone  salt_header_phone().
 * @param array<string,string>          $labels salt_chrome_labels( 'header' ).
 * @return string
 */
function salt_header_phone_html( array $phone, array $labels ): string {
	$name = isset( $labels['phone'] ) && '' !== $labels['phone'] ? ' aria-label="' . esc_attr( $labels['phone'] ) . '"' : '';
	return '<a class="salt-header__phone" href="' . esc_url( $phone['tel'], array( 'tel' ) ) . '"' . $name . '>'
		. salt_contract_icon( 'phone', '' )
		. '<span class="salt-header__phone-number">' . esc_html( $phone['text'] ) . '</span></a>';
}

/**
 * The menu glyph the burger and the drawer's close button share
 * (salt-menu-morph): three bars the stylesheet turns into a cross.
 *
 * @return string
 */
function salt_menu_morph_html(): string {
	$bars = '';
	foreach ( array( 'M4 6h16', 'M4 12h16', 'M4 18h16' ) as $d ) {
		$bars .= '<path class="salt-menu-morph__bar" d="' . $d . '" stroke-width="2" stroke-linecap="round"></path>';
	}
	return '<svg class="salt-icon salt-menu-morph" data-icon="menu" aria-hidden="true" fill="none" stroke="currentColor">' . $bars . '</svg>';
}

/**
 * Whether the drawer is drawn open by the server. Only script opens it, so
 * never on a real request; the `salt_header_drawer_open` filter lets the
 * contract adapter draw the case that shows it.
 *
 * @return bool
 */
function salt_header_drawer_open(): bool {
	return true === apply_filters( 'salt_header_drawer_open', false );
}

/**
 * The footer's link columns from its menu (SC-006): a top-level item with
 * children is a titled column of its children; a run of top-level items
 * without children is one untitled column. A single flat menu, as most
 * sites have, is therefore one untitled column.
 *
 * @param array<int,array<string,mixed>> $menu Salt_Nav_Walker tree.
 * @return array<int,array{title:?string,links:array<int,array<string,mixed>>}>
 */
function salt_footer_columns( array $menu ): array {
	$columns = array();
	$flat    = null;
	foreach ( $menu as $item ) {
		if ( array() !== (array) $item['children'] ) {
			if ( null !== $flat ) {
				$columns[] = $flat;
				$flat      = null;
			}
			$columns[] = array(
				'title' => (string) $item['label'],
				'links' => array_values( (array) $item['children'] ),
			);
			continue;
		}
		$flat            = $flat ?? array(
			'title' => null,
			'links' => array(),
		);
		$flat['links'][] = $item;
	}
	if ( null !== $flat ) {
		$columns[] = $flat;
	}
	return $columns;
}

/**
 * The footer's tone (data-tone and data-tone-dark): Site Settings →
 * Header & Footer, footer_tone, with the `salt_footer_tone` filter as a
 * site's code override. The default is surface-alt, the footer's colour
 * before the contract.
 *
 * @return string
 */
function salt_footer_tone(): string {
	$tones  = array( 'surface', 'surface-alt', 'brand-tint', 'surface-inverse' );
	$stored = salt_acf_active() ? salt_option( 'footer_tone' ) : null;
	$tone   = apply_filters( 'salt_footer_tone', in_array( $stored, $tones, true ) ? $stored : 'surface-alt' );
	return in_array( $tone, $tones, true ) ? $tone : 'surface-alt';
}

/**
 * Whether the footer shows the logo: Site Settings → Header & Footer,
 * footer_show_logo (off by default, as before), with the
 * `salt_footer_show_logo` filter as a site's code override.
 *
 * @return bool
 */
function salt_footer_show_logo(): bool {
	$stored = salt_acf_active() && true === (bool) salt_option( 'footer_show_logo' );
	return true === apply_filters( 'salt_footer_show_logo', $stored );
}

/**
 * The footer text (footer_text) as the contract's rich text: the theme's
 * allow-list for a line of prose (paragraphs, bold, italic, links), with
 * paragraphs added to text that has none. '' when it has no words.
 *
 * @return string
 */
function salt_footer_text_html(): string {
	$text = salt_acf_active() ? trim( (string) ( salt_option( 'footer_text' ) ?? '' ) ) : '';
	if ( '' === $text ) {
		return '';
	}
	if ( 1 !== preg_match( '/<p[\s>]/i', $text ) ) {
		$text = wpautop( $text );
	}
	$html = salt_sanitize_html( $text, array( 'paragraph', 'bold', 'italic', 'link' ) );
	return '' === trim( wp_strip_all_tags( $html ) ) ? '' : $html;
}

/**
 * The consent preferences panel's words and categories (site.consent).
 *
 * Drawn only when a cookie plugin is wired to the WP Consent API
 * (salt_consent_plugin_active(), founder ruling C). The categories are the
 * contract's ids, each written through salt_consent_api_category(); the
 * `salt_consent_panel` filter is the seam.
 *
 * @return array{labels:array<string,string>,categories:array<int,array{id:string,label:string,description:string}>}
 */
function salt_consent_panel(): array {
	$panel = array(
		'labels'     => array(
			'title'  => __( 'Cookie preferences', 'salt' ),
			'save'   => __( 'Save preferences', 'salt' ),
			'cancel' => __( 'Cancel', 'salt' ),
			'saved'  => __( 'Your cookie preferences are saved.', 'salt' ),
		),
		'categories' => array(
			array(
				'id'          => 'analytics',
				'label'       => __( 'Analytics', 'salt' ),
				'description' => __( 'Helps us see which pages are read, with no personal details.', 'salt' ),
			),
			array(
				'id'          => 'embeds',
				'label'       => __( 'Embedded content', 'salt' ),
				'description' => __( 'Maps, videos and booking tools from other sites.', 'salt' ),
			),
			array(
				'id'          => 'marketing',
				'label'       => __( 'Marketing', 'salt' ),
				'description' => __( 'Lets us measure how our adverts perform.', 'salt' ),
			),
		),
	);
	$given = apply_filters( 'salt_consent_panel', $panel );
	return is_array( $given ) && isset( $given['labels'], $given['categories'] ) ? $given : $panel;
}

/**
 * The scriptless phone layout (salt-contract markup/page.json, scriptless):
 * the custom properties chrome.css reads to show the full nav in place of
 * the burger when script is off.
 *
 * @return string
 */
function salt_header_noscript_css(): string {
	return '.salt-header{--salt-header-phone-menu:inline-flex;--salt-header-phone-burger:none;--salt-header-phone-wrap:wrap}';
}

/**
 * Print the scriptless phone layout in the document head as the contract
 * writes it: noscript > style, after the stylesheet link ('wp_head', 21).
 * A style inside noscript is conforming HTML only in head
 * (site-header.json ruling). It carries no nonce, so its markup is the
 * contract's; the CSP allows it by its hash instead (salt_security_headers()).
 *
 * @return void
 */
function salt_output_header_noscript(): void {
	echo '<noscript><style>' . salt_header_noscript_css() . "</style></noscript>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed string.
}
add_action( 'wp_head', 'salt_output_header_noscript', 21 );

/**
 * What the chrome's script (assets/src/js/app.js) needs that the page does
 * not draw: the labels it names the controls it mounts with, the theme
 * toggle's two glyphs, and the consent panel (only when the footer draws
 * its control).
 *
 * @return array<string,mixed>
 */
function salt_chrome_script_data(): array {
	$labels = salt_chrome_labels( 'header' );
	$data   = array(
		'labels' => array(
			'submenu'   => $labels['submenu'],
			'closeMenu' => $labels['closeMenu'],
			'theme'     => $labels['theme'],
		),
		'glyphs' => array(
			'sun'  => salt_contract_icon( 'sun', 'sm' ),
			'moon' => salt_contract_icon( 'moon', 'sm' ),
		),
	);
	if ( function_exists( 'salt_consent_control_drawn' ) && salt_consent_control_drawn() ) {
		$panel           = salt_consent_panel();
		$data['consent'] = array(
			'labels'     => array_map( 'strval', $panel['labels'] ),
			'categories' => salt_consent_choices( $panel['categories'] ),
		);
	}
	return $data;
}

/**
 * The consent panel's boxes as script draws them: the same boxes and ids
 * as the server form (one per category the contract names), each with the
 * WP Consent API category it writes. Categories that share one (embeds
 * and marketing are both marketing) carry a note naming the others: the
 * script keeps them ticked together, and Save allows the shared category
 * only when all of them are ticked, so the panel and the record always
 * agree.
 *
 * @param array<int,array{id:string,label:string,description:string}> $categories salt_consent_panel() categories.
 * @return array<int,array{id:string,label:string,description:string,api:string,note:string}>
 */
function salt_consent_choices( array $categories ): array {
	$choices = array();
	foreach ( $categories as $category ) {
		$id        = sanitize_key( (string) $category['id'] );
		$choices[] = array(
			'id'          => $id,
			'label'       => (string) $category['label'],
			'description' => (string) $category['description'],
			'api'         => function_exists( 'salt_consent_api_category' ) ? salt_consent_api_category( $id ) : $id,
			'note'        => '',
		);
	}
	foreach ( $choices as $i => $choice ) {
		$others = array();
		foreach ( $choices as $j => $other ) {
			if ( $i !== $j && $other['api'] === $choice['api'] ) {
				$others[] = $other['label'];
			}
		}
		if ( array() !== $others ) {
			/* translators: %s: the other consent categories (for example "Marketing") that share this one's setting. */
			$choices[ $i ]['note'] = sprintf( __( 'Shares one setting with %s: ticking one ticks both.', 'salt' ), implode( ', ', $others ) );
		}
	}
	return $choices;
}

/**
 * Print the chrome's script data as inert JSON ('wp_footer', 5): a
 * script of type application/json is data, not code, so the CSP does not
 * govern it, and the contract's page comparison drops it with the body's
 * other script elements.
 *
 * @return void
 */
function salt_output_chrome_script_data(): void {
	echo '<script type="application/json" id="salt-chrome-data">' . wp_json_encode( salt_chrome_script_data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < > & hex-escaped, so it cannot close the element.
}
add_action( 'wp_footer', 'salt_output_chrome_script_data', 5 );
