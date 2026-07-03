<?php
/**
 * Generic archive (spec §6): native loop framed by the document H1
 * (A11Y01 — native-loop routes always emit an H1).
 *
 * The H1 renders inside a standard hero band (matching the section-hero
 * rhythm) with the archive description under it when one exists; the loop
 * renders as a responsive card grid — each post type may ship its own
 * content/card-{post_type}.php delegate (card.php is the fallback).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// Prefix is filtered to '' (salt_archive_title_prefix) so this is the bare
// title; strip_all_tags is defence-in-depth against core's raw <span> wrap.
$salt_archive_title       = wp_strip_all_tags( get_the_archive_title() );
$salt_archive_description = trim( (string) get_the_archive_description() );

echo '<header class="x-archive-header bg-surface-alt py-14 md:py-20">';
echo '<div class="mx-auto w-full max-w-5xl px-4 sm:px-6 lg:px-8">';
salt_the_document_h1( $salt_archive_title, 'x-heading text-4xl md:text-5xl font-bold tracking-tight' );
if ( '' !== $salt_archive_description ) {
	echo '<div class="x-archive-description mt-4 max-w-prose text-lg text-ink-muted">' . wp_kses_post( $salt_archive_description ) . '</div>';
}
echo '</div>';
echo '</header>';

if ( have_posts() ) {
	echo '<div class="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8 md:py-20">';
	echo '<div class="x-archive-grid grid gap-6 sm:grid-cols-2 lg:grid-cols-3">';
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/content/card', get_post_type() );
	}
	echo '</div>';
	the_posts_pagination();
	echo '</div>';
} else {
	get_template_part( 'template-parts/content/none' );
}

get_footer();
