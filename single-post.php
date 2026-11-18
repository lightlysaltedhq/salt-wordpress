<?php
/**
 * Single blog post (spec §6, engagement build-out) — hero (category badge →
 * H1 → author/date/updated/reading-time meta), featured image, content,
 * share row, author box, prev/next navigation + BlogPosting schema.
 *
 * The author box pulls the display name + bio straight from the WordPress
 * user, and the avatar from the ACF profile_photo user field, drawn only
 * when there is one: salt-contract 1.0.0-rc.7 dropped the Gravatar
 * fallback (collections/team.json). The credited author as a team member
 * is ID-3952.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) {
	the_post();

	$salt_post_id   = (int) get_the_ID();
	$salt_permalink = (string) get_permalink();

	$salt_category = salt_post_primary_category( $salt_post_id );

	// Updated line only when the modified DAY differs from the published day
	// — same-day touch-ups aren't an update worth announcing.
	$salt_show_modified = get_the_modified_date( 'Ymd' ) !== get_the_date( 'Ymd' );

	$salt_minutes = salt_reading_time_minutes( (string) get_post_field( 'post_content', $salt_post_id ) );

	$salt_author_id   = (int) get_the_author_meta( 'ID' );
	$salt_author_name = (string) get_the_author();
	$salt_author_bio  = (string) get_the_author_meta( 'description' );
	$salt_author_url  = (string) get_author_posts_url( $salt_author_id );

	// Avatar: the ACF profile_photo user field, or none (no Gravatar).
	$salt_avatar_html = '';
	$salt_photo_id    = function_exists( 'get_field' ) ? (int) get_field( 'profile_photo', 'user_' . $salt_author_id ) : 0;
	if ( $salt_photo_id > 0 ) {
		$salt_avatar_html = salt_get_component(
			'image',
			array(
				'image_id' => $salt_photo_id,
				'alt'      => $salt_author_name,
				'classes'  => 'size-full object-cover',
			)
		);
	}

	// ------------------------------------------------------------------
	// Hero: category badge above the H1, then the byline/meta row.
	// ------------------------------------------------------------------
	echo '<header class="x-post-hero bg-surface-alt py-14 md:py-20">';
	echo '<div class="mx-auto w-full max-w-3xl px-4 sm:px-6 lg:px-8">';

	if ( null !== $salt_category ) {
		printf(
			'<p class="x-post-category mb-4"><a class="inline-flex items-center rounded-full bg-brand-tint px-3 py-1 text-sm font-semibold text-brand hover:text-brand-strong" href="%s">%s</a></p>',
			esc_url( (string) get_term_link( $salt_category ) ),
			esc_html( $salt_category->name )
		);
	}

	salt_the_document_h1( get_the_title(), 'x-heading text-4xl md:text-5xl font-bold tracking-tight' );

	echo '<div class="x-post-meta mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ink-muted">';

	echo '<span class="x-post-meta-author inline-flex items-center gap-2">';
	if ( '' !== $salt_avatar_html ) {
		echo '<span class="size-8 shrink-0 overflow-hidden rounded-full">' . $salt_avatar_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- x-image markup, escaped internally.
	}
	printf(
		'<a class="font-medium text-ink hover:text-brand" href="%s">%s</a>',
		esc_url( $salt_author_url ),
		esc_html( $salt_author_name )
	);
	echo '</span>';

	printf(
		'<time datetime="%s">%s</time>',
		esc_attr( (string) get_the_date( 'c' ) ),
		esc_html( (string) get_the_date() )
	);

	if ( $salt_show_modified ) {
		printf(
			'<span class="x-post-meta-updated">%s <time datetime="%s">%s</time></span>',
			esc_html__( 'Updated', 'salt' ),
			esc_attr( (string) get_the_modified_date( 'c' ) ),
			esc_html( (string) get_the_modified_date() )
		);
	}

	if ( $salt_minutes > 0 ) {
		echo '<span class="x-post-meta-reading-time inline-flex items-center gap-1.5">';
		salt_component(
			'icon',
			array(
				'name' => 'clock',
				'size' => 'sm',
			)
		);
		printf(
			'<span>%s</span>',
			esc_html(
				sprintf(
					/* translators: %d: estimated reading time in minutes. */
					_n( '%d min read', '%d min read', $salt_minutes, 'salt' ),
					$salt_minutes
				)
			)
		);
		echo '</span>';
	}

	echo '</div>'; // .x-post-meta
	echo '</div>';
	echo '</header>';

	// ------------------------------------------------------------------
	// Featured image — standardised crop, THE LCP candidate.
	// ------------------------------------------------------------------
	$salt_thumbnail_id = (int) get_post_thumbnail_id( $salt_post_id );
	if ( $salt_thumbnail_id > 0 ) {
		echo '<div class="x-post-media mx-auto w-full max-w-5xl px-4 pt-10 sm:px-6 lg:px-8 md:pt-14">';
		salt_component(
			'image',
			array(
				'image_id'      => $salt_thumbnail_id,
				'alt'           => '', // Stored attachment alt wins (x-image precedence).
				'priority'      => true,
				'sizes_context' => 'hero',
				'classes'       => 'aspect-[16/9] w-full rounded-lg object-cover',
			)
		);
		echo '</div>';
	}

	// ------------------------------------------------------------------
	// Content.
	// ------------------------------------------------------------------
	echo '<div class="mx-auto w-full max-w-3xl px-4 py-14 sm:px-6 lg:px-8 md:py-20">';
	echo '<div class="x-prose">';
	the_content();
	echo '</div>';

	// Tag list (when tagged).
	$salt_tags = get_the_tags( $salt_post_id );
	if ( is_array( $salt_tags ) && array() !== $salt_tags ) {
		echo '<ul class="x-post-tags mt-10 flex flex-wrap gap-2">';
		foreach ( $salt_tags as $salt_tag ) {
			printf(
				'<li><a class="inline-flex items-center rounded-full border border-border px-3 py-1 text-sm text-ink-muted hover:border-brand hover:text-brand" href="%s">%s</a></li>',
				esc_url( (string) get_term_link( $salt_tag ) ),
				esc_html( $salt_tag->name )
			);
		}
		echo '</ul>';
	}

	// Share row.
	echo '<div class="mt-10 border-t border-border pt-10">';
	salt_component(
		'share-links',
		array(
			'url'     => $salt_permalink,
			'title'   => (string) get_the_title(),
			'heading' => __( 'Share this post', 'salt' ),
		)
	);
	echo '</div>';

	// Author box — name + bio from the WP user.
	salt_component(
		'author-box',
		array(
			'name'    => $salt_author_name,
			'bio'     => $salt_author_bio,
			'url'     => $salt_author_url,
			'media'   => $salt_avatar_html,
			'classes' => 'mt-10',
		)
	);

	// Previous / next post navigation.
	$salt_prev = get_previous_post();
	$salt_next = get_next_post();
	if ( $salt_prev instanceof WP_Post || $salt_next instanceof WP_Post ) {
		echo '<nav class="x-post-nav mt-10 grid gap-4 border-t border-border pt-10 sm:grid-cols-2" aria-label="' . esc_attr__( 'Post navigation', 'salt' ) . '">';
		if ( $salt_prev instanceof WP_Post ) {
			printf(
				'<a class="group flex flex-col gap-1 rounded-lg border border-border p-4 hover:border-brand" href="%s" rel="prev"><span class="text-sm text-ink-muted">%s</span><span class="font-semibold group-hover:text-brand">%s</span></a>',
				esc_url( (string) get_permalink( $salt_prev ) ),
				esc_html__( '← Previous post', 'salt' ),
				esc_html( (string) get_the_title( $salt_prev ) )
			);
		} else {
			echo '<span aria-hidden="true"></span>';
		}
		if ( $salt_next instanceof WP_Post ) {
			printf(
				'<a class="group flex flex-col gap-1 rounded-lg border border-border p-4 text-right hover:border-brand" href="%s" rel="next"><span class="text-sm text-ink-muted">%s</span><span class="font-semibold group-hover:text-brand">%s</span></a>',
				esc_url( (string) get_permalink( $salt_next ) ),
				esc_html__( 'Next post →', 'salt' ),
				esc_html( (string) get_the_title( $salt_next ) )
			);
		}
		echo '</nav>';
	}

	echo '</div>'; // content container

	// ------------------------------------------------------------------
	// BlogPosting schema (queued into the single page @graph — SEO §15).
	// ------------------------------------------------------------------
	$salt_article = array(
		'@type'            => 'BlogPosting',
		'@id'              => $salt_permalink . '#article',
		'headline'         => (string) get_the_title(),
		'url'              => $salt_permalink,
		'datePublished'    => (string) get_the_date( 'c' ),
		'dateModified'     => (string) get_the_modified_date( 'c' ),
		'author'           => array(
			'@type' => 'Person',
			'name'  => $salt_author_name,
			'url'   => $salt_author_url,
		),
		'publisher'        => array( '@id' => home_url( '/' ) . '#organization' ),
		'mainEntityOfPage' => array( '@id' => $salt_permalink . '#webpage' ),
	);
	if ( null !== $salt_category ) {
		$salt_article['articleSection'] = $salt_category->name;
	}
	if ( $salt_thumbnail_id > 0 ) {
		$salt_image_url = wp_get_attachment_image_url( $salt_thumbnail_id, 'large' );
		if ( is_string( $salt_image_url ) && '' !== $salt_image_url ) {
			$salt_article['image'] = $salt_image_url;
		}
	}
	if ( '' !== trim( (string) get_the_excerpt() ) ) {
		$salt_article['description'] = (string) get_the_excerpt();
	}
	salt_schema_add( $salt_article );
}

get_footer();
