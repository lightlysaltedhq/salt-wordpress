<?php
/**
 * Site header part (spec §12a) — carries ALL header option reads.
 *
 * The header.php shell stays thin (skip link + <main>); child themes
 * override THIS part, never the shell (OVERRIDES.md, v3 chrome seams).
 * Skip-target-safe: the shell's skip link precedes this landmark and
 * targets #main, so the header never traps the skip jump; the sticky
 * anchor offset is --salt-scroll-padding (salt_header_runtime_css()).
 *
 * Option reads (Header & Footer / Logo & Fonts): header_sticky (adds
 * .is-sticky), header_cta (x-button at the end of the nav), logo_*
 * (via salt_logo_args()).
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

$salt_header_classes = salt_classnames(
	array(
		'x-header',
		'bg-surface border-b border-border',
		$salt_sticky ? 'is-sticky' : '',
	)
);
?>
<header class="<?php echo esc_attr( $salt_header_classes ); ?>">
	<div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-6 px-4 py-3 sm:px-6 lg:px-8">
		<?php salt_component( 'logo', salt_logo_args() ); ?>
		<nav class="x-nav flex items-center gap-6" aria-label="<?php echo esc_attr__( 'Primary', 'salt' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'x-nav-menu flex items-center gap-6',
					'depth'          => 2,
					'walker'         => new Salt_Nav_Walker(),
					'fallback_cb'    => 'salt_nav_menu_fallback',
				)
			);

			if ( null !== $salt_cta ) {
				salt_component(
					'button',
					array(
						'label'   => $salt_cta['label'],
						'url'     => $salt_cta['url'],
						'target'  => $salt_cta['target'],
						'size'    => 'sm',
						'classes' => 'x-header-cta',
					)
				);
			}
			?>
		</nav>
	</div>
</header>
