<?php
/**
 * Generic result-loop card (spec §6) — used by archive/search loops.
 * CPT-specific cards (card-service.php etc.) land with their sections
 * in Phase 2 via the get_template_part() variant mechanism.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article class="x-card border-b border-border py-6">
	<h2 class="x-heading text-xl font-semibold">
		<a class="hover:text-brand" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	</h2>
	<?php if ( has_excerpt() || '' !== get_the_excerpt() ) : ?>
		<p class="text-ink-muted"><?php echo esc_html( get_the_excerpt() ); ?></p>
	<?php endif; ?>
</article>
