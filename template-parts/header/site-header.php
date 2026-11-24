<?php
/**
 * Site header part (spec §12a; salt-contract markup/site-header.json) —
 * carries ALL header option reads.
 *
 * The header.php shell stays thin (skip link + <main>); child themes
 * override THIS part, never the shell (OVERRIDES.md, v3 chrome seams).
 * Skip-target-safe: the shell's skip link precedes this landmark and
 * targets #main, so the header never traps the skip jump; the sticky
 * anchor offset is --salt-scroll-padding (salt_header_runtime_css()).
 *
 * Order (the contract's): logo, nav, theme toggle, phone, call to action,
 * burger, and the drawer only when open. The burger, drawer, submenu
 * disclosure and theme toggle are script's (assets/src/js/app.js); without
 * script the noscript style in the head (salt_output_header_noscript())
 * shows the full nav in place of the burger, and submenus open on hover
 * and focus-within.
 *
 * Option reads (Header & Footer / Logo & Fonts): header_sticky
 * (data-sticky), header_cta, logo_* (via salt_logo_args()). Seams pending
 * the founder's rulings: salt_theme_toggle_placement, salt_header_phone.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Slice-local dependencies (idempotent — normally loaded from functions.php).
if ( ! function_exists( 'salt_header_runtime_css' ) ) {
	require_once get_template_directory() . '/inc/services/chrome.php';
}
if ( ! class_exists( 'Salt_Nav_Walker' ) ) {
	require_once get_template_directory() . '/inc/class-salt-nav-walker.php';
}

$salt_sticky = salt_acf_active() && (bool) get_field( 'header_sticky', 'option' );
$salt_cta    = salt_header_cta_args();
$salt_phone  = salt_header_phone();
$salt_labels = salt_chrome_labels( 'header' );
$salt_menu   = Salt_Nav_Walker::salt_menu( 'primary' );
$salt_open   = salt_header_drawer_open();
$salt_toggle = 'header' === salt_theme_toggle_placement() && '' !== trim( $salt_labels['theme'] );
?>
<header class="salt-header" data-site-header<?php echo $salt_sticky ? ' data-sticky' : ''; ?>>
	<div class="salt-header__inner">
		<?php salt_component( 'logo', salt_logo_args( 'header' ) ); ?>
		<?php
		if ( array() !== $salt_menu ) {
			echo salt_header_nav_html( $salt_menu, $salt_labels ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
		}
		if ( $salt_toggle ) {
			echo salt_theme_toggle_html( $salt_labels['theme'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
		}
		if ( null !== $salt_phone ) {
			echo salt_header_phone_html( $salt_phone, $salt_labels ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
		}
		if ( null !== $salt_cta ) {
			echo salt_header_cta_html( $salt_cta ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
		}
		?>
		<button class="salt-header__burger" type="button" aria-expanded="<?php echo $salt_open ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $salt_labels['menu'] ); ?>"><?php echo salt_menu_morph_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></button>
		<?php if ( $salt_open ) : ?>
			<dialog class="salt-drawer" open aria-label="<?php echo esc_attr( $salt_labels['menu'] ); ?>" tabindex="-1">
				<button class="salt-drawer__close" type="button" aria-label="<?php echo esc_attr( $salt_labels['closeMenu'] ); ?>"><?php echo salt_menu_morph_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></button>
				<div class="salt-drawer__panel">
					<?php
					if ( array() !== $salt_menu ) {
						echo salt_header_nav_html( $salt_menu, $salt_labels, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
					}
					if ( null !== $salt_phone ) {
						echo salt_header_phone_html( $salt_phone, $salt_labels ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
					}
					if ( null !== $salt_cta ) {
						echo salt_header_cta_html( $salt_cta ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
					}
					?>
				</div>
			</dialog>
		<?php endif; ?>
	</div>
</header>
