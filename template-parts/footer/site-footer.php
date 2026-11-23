<?php
/**
 * Site footer part (spec §12a; salt-contract markup/site-footer.json) —
 * carries ALL footer option reads.
 *
 * The footer.php shell stays thin (closes <main>, wp_footer()); child
 * themes override THIS part, never the shell (OVERRIDES.md, v3 chrome
 * seams).
 *
 * Order (the contract's): brand, nav, then the base: text, social, theme
 * toggle, display preferences, copyright, consent.
 *
 * Option reads: footer_text (rich text), footer_show_social + org_same_as
 * (Company & Schema) for the social row, org_name for the © line (falls
 * back to the site title). The Display Preferences widget renders through
 * its single seam, salt_the_display_preferences(). Seams pending the
 * founder's rulings: salt_footer_tone, salt_footer_show_logo and
 * salt_theme_toggle_placement; salt_consent_control (below).
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

$salt_labels    = salt_chrome_labels( 'footer' );
$salt_columns   = salt_footer_columns( Salt_Nav_Walker::salt_menu( 'footer' ) );
$salt_text      = salt_footer_text_html();
$salt_show_soc  = salt_acf_active() && (bool) get_field( 'footer_show_social', 'option' );
$salt_social    = $salt_show_soc ? salt_footer_social_links() : array();
$salt_tone      = salt_footer_tone();
$salt_toggle    = 'footer' === salt_theme_toggle_placement() && '' !== trim( $salt_labels['theme'] );
$salt_copyright = salt_footer_copyright();

/*
 * Consent (founder ruling C): the control and its panel are drawn only when
 * a cookie plugin is wired to the WP Consent API; the `salt_consent_control`
 * filter can turn them off (salt_consent_control_drawn()). The control is drawn aria-disabled until
 * app.js hydrates it; Save writes through the plugin's wp_set_consent(),
 * and the theme stores nothing (PRIV01).
 */
$salt_consent = salt_consent_control_drawn();
$salt_panel   = $salt_consent && true === apply_filters( 'salt_consent_panel_open', false );
?>
<footer class="salt-footer" data-tone="<?php echo esc_attr( $salt_tone ); ?>" data-tone-dark="<?php echo esc_attr( $salt_tone ); ?>">
	<div class="salt-footer__inner">
		<?php if ( salt_footer_show_logo() ) : ?>
			<div class="salt-footer__brand"><?php salt_component( 'logo', salt_logo_args( 'footer' ) ); ?></div>
		<?php endif; ?>
		<?php if ( array() !== $salt_columns ) : ?>
			<nav class="salt-footer__nav" aria-label="<?php echo esc_attr( $salt_labels['navigation'] ); ?>">
				<?php foreach ( $salt_columns as $salt_i => $salt_column ) : ?>
					<?php $salt_title_id = 'site-footer__column-' . ( $salt_i + 1 ); ?>
					<div class="salt-footer__column">
						<?php if ( null !== $salt_column['title'] ) : ?>
							<p class="salt-footer__title" id="<?php echo esc_attr( $salt_title_id ); ?>"><?php echo esc_html( $salt_column['title'] ); ?></p>
							<ul class="salt-footer__links" role="list" aria-labelledby="<?php echo esc_attr( $salt_title_id ); ?>">
						<?php else : ?>
							<ul class="salt-footer__links" role="list" aria-label="<?php echo esc_attr( $salt_labels['navigation'] ); ?>">
						<?php endif; ?>
							<?php foreach ( $salt_column['links'] as $salt_link ) : ?>
								<li><?php echo salt_nav_link_html( $salt_link, 'salt-footer__link', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built. ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<div class="salt-footer__base">
			<?php if ( '' !== $salt_text ) : ?>
				<div class="salt-rich-text salt-footer__text"><?php echo $salt_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- salt_sanitize_html() allow-list. ?></div>
			<?php endif; ?>
			<?php if ( array() !== $salt_social ) : ?>
				<ul class="salt-footer__social" role="list" aria-label="<?php echo esc_attr( $salt_labels['social'] ); ?>">
					<?php foreach ( $salt_social as $salt_profile ) : ?>
						<li>
							<a class="salt-footer__social-link" href="<?php echo esc_url( $salt_profile['url'] ); ?>">
								<?php
								$salt_glyph = '' !== $salt_profile['icon'] ? salt_contract_icon( $salt_profile['icon'], 'md', $salt_profile['label'] ) : '';
								echo '' !== $salt_glyph ? $salt_glyph : esc_html( $salt_profile['label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- salt_contract_icon() escapes its attributes.
								?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php
			if ( $salt_toggle ) {
				echo salt_theme_toggle_html( $salt_labels['theme'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
			}
			if ( function_exists( 'salt_the_display_preferences' ) ) {
				salt_the_display_preferences();
			}
			?>
			<?php if ( '' !== $salt_copyright ) : ?>
				<p class="salt-footer__copyright"><?php echo esc_html( $salt_copyright ); ?></p>
			<?php endif; ?>
			<?php if ( $salt_consent ) : ?>
				<div class="salt-footer__consent">
					<button class="salt-button" type="button" data-style="secondary" data-salt-consent-preferences=""<?php echo $salt_panel ? '' : ' aria-disabled="true"'; ?>><?php echo esc_html( $salt_labels['preferences'] ); ?></button>
					<p class="salt-sr-only" role="status" aria-live="polite"></p>
					<?php
					if ( $salt_panel ) {
						get_template_part( 'template-parts/footer/consent-panel', null, salt_consent_panel() );
					}
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</footer>
