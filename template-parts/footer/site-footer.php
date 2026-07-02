<?php
/**
 * Site footer part (spec §12a) — carries ALL footer option reads.
 *
 * The footer.php shell stays thin (closes <main>, wp_footer()); child
 * themes override THIS part, never the shell (OVERRIDES.md, v3 chrome
 * seams).
 *
 * Option reads: footer_text (line above the ©, esc_html + nl2br),
 * footer_show_social + org_same_as (Company & Schema) for the social row,
 * org_name for the auto © line (falls back to the site title). The
 * Display Preferences widget renders through its single seam,
 * salt_the_display_preferences() (function_exists-guarded — the service
 * owns availability gating).
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

$salt_footer_text = salt_acf_active() ? trim( (string) ( get_field( 'footer_text', 'option' ) ?? '' ) ) : '';
$salt_show_social = salt_acf_active() && (bool) get_field( 'footer_show_social', 'option' );
$salt_social      = $salt_show_social ? salt_footer_social_links() : array();
?>
<footer class="x-footer border-t border-border bg-surface-alt">
	<div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
		<nav class="x-footer-nav" aria-label="<?php echo esc_attr__( 'Footer', 'salt' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'x-footer-menu flex flex-wrap gap-x-6 gap-y-2',
					'depth'          => 1,
					'walker'         => new Salt_Nav_Walker(),
					'fallback_cb'    => 'salt_nav_menu_fallback',
				)
			);
			?>
		</nav>
		<?php if ( array() !== $salt_social ) : ?>
			<ul class="x-footer-social mt-6 flex flex-wrap items-center gap-4">
				<?php foreach ( $salt_social as $salt_link ) : ?>
					<li>
						<a class="x-footer-social-link inline-flex items-center gap-2 text-ink hover:text-brand" href="<?php echo esc_url( $salt_link['url'] ); ?>" rel="noopener">
							<?php if ( '' !== $salt_link['icon'] ) : ?>
								<?php salt_component( 'icon', array( 'name' => $salt_link['icon'] ) ); ?>
								<span class="visually-hidden"><?php echo esc_html( $salt_link['label'] ); ?></span>
							<?php else : ?>
								<?php echo esc_html( $salt_link['label'] ); ?>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( '' !== $salt_footer_text ) : ?>
			<p class="x-footer-text mt-6 text-sm text-ink-muted"><?php echo nl2br( esc_html( $salt_footer_text ), false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() runs FIRST; nl2br() only inserts literal <br> tags into already-escaped text. ?></p>
		<?php endif; ?>
		<p class="x-footer-copyright mt-2 text-sm text-ink-muted"><?php echo esc_html( salt_footer_copyright() ); ?></p>
		<?php
		if ( function_exists( 'salt_the_display_preferences' ) ) {
			salt_the_display_preferences();
		}
		?>
	</div>
</footer>
