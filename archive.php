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
 * An author's archive is a team member's (inc/services/post-author.php):
 * the member's name is the H1 and, on the first page, the profile follows
 * it: the photo, the role as the strapline and the bio as the biography
 * (salt-contract rc.7 markup/archive.json). A topic's archive draws the
 * topic's description under the H1.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// The blog posts index (is_home) is NOT a WP "archive": get_the_archive_title()
// has no case for it and falls back to the generic word "Archives". Use the
// assigned posts-page title (or a "Blog" default) instead. Every other route
// here is a real archive — prefix is filtered to '' (salt_archive_title_prefix)
// so this is the bare title; strip_all_tags defends against core's <span> wrap.
$salt_archive_member = salt_archive_team_member();
if ( null !== $salt_archive_member ) {
	$salt_archive_title = (string) $salt_archive_member->post_title;
} elseif ( is_home() && ! is_front_page() ) {
	$salt_posts_page    = (int) get_option( 'page_for_posts' );
	$salt_archive_title = $salt_posts_page > 0
		? wp_strip_all_tags( (string) get_the_title( $salt_posts_page ) )
		: __( 'Blog', 'salt' );
} else {
	$salt_archive_title = wp_strip_all_tags( get_the_archive_title() );
}
$salt_archive_description = null !== $salt_archive_member ? '' : trim( (string) get_the_archive_description() );

echo '<header class="x-archive-header bg-surface-alt py-14 md:py-20">';
echo '<div class="mx-auto w-full max-w-5xl px-4 sm:px-6 lg:px-8">';
salt_the_document_h1( $salt_archive_title, 'x-heading text-4xl md:text-5xl font-bold tracking-tight' );
if ( '' !== $salt_archive_description ) {
	echo '<div class="x-archive-description mt-4 max-w-prose text-lg text-ink-muted">' . wp_kses_post( $salt_archive_description ) . '</div>';
}
if ( null !== $salt_archive_member && ! is_paged() ) {
	$salt_archive_person = array(
		'role'  => trim( (string) ( function_exists( 'get_field' ) ? get_field( 'field_salt_collection_team_role', $salt_archive_member->ID ) : '' ) ),
		'bio'   => trim( (string) ( function_exists( 'get_field' ) ? get_field( 'field_salt_collection_team_bio', $salt_archive_member->ID ) : '' ) ),
		'photo' => (int) get_post_thumbnail_id( $salt_archive_member ),
	);
	if ( salt_person_has_profile( $salt_archive_person ) ) {
		echo '<div class="x-archive-profile mt-6 flex items-start gap-4">';
		if ( $salt_archive_person['photo'] > 0 ) {
			echo '<div class="x-archive-portrait size-20 shrink-0 overflow-hidden rounded-full">';
			salt_component(
				'image',
				array(
					'image_id' => $salt_archive_person['photo'],
					'alt'      => '', // Stored attachment alt wins (x-image precedence).
					'classes'  => 'size-full object-cover',
				)
			);
			echo '</div>';
		}
		echo '<div class="flex flex-col gap-1">';
		if ( '' !== $salt_archive_person['role'] ) {
			echo '<p class="x-archive-strapline font-semibold">' . esc_html( $salt_archive_person['role'] ) . '</p>';
		}
		if ( '' !== $salt_archive_person['bio'] ) {
			echo '<p class="x-archive-bio max-w-prose text-ink-muted">' . esc_html( $salt_archive_person['bio'] ) . '</p>';
		}
		echo '</div></div>';
	}
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
