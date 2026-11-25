<?php
/**
 * Fallback for a missing section layout (spec §5; review ARCH05).
 *
 * Reached when a Flexible Content row references a layout with no template
 * (bad release, renamed slug, deleted child override). salt_render_section()
 * has already error_logged the slug. The public sees nothing broken; logged-in
 * editors see a visible marker so lost content is discoverable — never a
 * silent public blank, never a fatal.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_missing = isset( $args['missing_layout'] ) && is_string( $args['missing_layout'] ) ? $args['missing_layout'] : '';
?>
<!-- salt: section unavailable -->
<?php if ( current_user_can( 'edit_posts' ) ) : ?>
	<div class="salt-section" data-tone="surface" data-tone-dark="surface" data-spacing="sm" data-width="default">
		<div class="salt-section__content">
			<p class="salt-section-fallback" role="status">
				<?php
				printf(
					/* translators: %s: the missing section layout slug. */
					esc_html__( 'Section unavailable: the layout “%s” has no template in this theme version. This notice is only visible to editors; visitors see nothing here. Check the error log and the theme changelog.', 'salt' ),
					esc_html( $salt_missing )
				);
				?>
			</p>
		</div>
	</div>
<?php endif; ?>
