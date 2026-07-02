<?php
/**
 * 404 (spec §6) — guaranteed H1 (A11Y01), helpful links, search form.
 *
 * @package Salt
 */

get_header();

salt_the_document_h1( __( 'Page not found', 'salt' ) );
?>
<div class="mx-auto w-full max-w-3xl px-4 py-14 sm:px-6 lg:px-8">
	<p class="text-ink-muted"><?php esc_html_e( 'The page you are looking for does not exist or has moved. Try searching, or head back to the homepage.', 'salt' ); ?></p>
	<div class="py-6"><?php get_search_form(); ?></div>
	<a class="text-brand underline-offset-4 hover:underline" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the homepage', 'salt' ); ?></a>
</div>
<?php
get_footer();
