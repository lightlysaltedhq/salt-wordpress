<?php
/**
 * Single blog post, as the contract draws it (salt-contract rc.8
 * markup/post.json): the article with its header (the breadcrumb when the
 * posts have an index, the primary topic, the title as the document's h1,
 * then the byline, date and reading time), the featured image as the
 * view's priority image, the post's layout (salt_the_post_layout(): prose
 * bare in the article, every other section as a band), and the footer with
 * the topics as tags (primary first, then A to Z), the share links and the
 * author box; then the newer and older posts outside the article, and the
 * BlogPosting schema.
 *
 * The native author is credited whether or not they publish a profile. An
 * unticked author has plain text credit: no link and no author box.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$salt_labels = salt_contract_labels( 'post' );

while ( have_posts() ) {
	the_post();

	$salt_post_id   = (int) get_the_ID();
	$salt_permalink = (string) get_permalink();
	$salt_title     = salt_view_title( $salt_post_id );
	$salt_category  = salt_post_primary_category( $salt_post_id );

	/**
	 * A post's reading time in minutes, for its meta line. The theme counts
	 * the post's own text (salt_post_reading_text()); the filter is a seam
	 * for the contract harness, whose cases give the minutes they expect.
	 *
	 * @param int $minutes The minutes, 0 for none.
	 * @param int $post_id The post.
	 */
	$salt_minutes = (int) apply_filters( 'salt_post_reading_minutes', salt_reading_time_minutes( salt_post_reading_text( $salt_post_id ) ), $salt_post_id );

	// The credited author: a team member, or no one. An unticked author is
	// credited as text, with no archive and no author box.
	$salt_author = salt_post_credited_author( $salt_post_id );

	echo '<article class="salt-post">';
	echo '<header class="salt-post__header">';

	// The breadcrumb: the home page (unless it is the blog index), the blog
	// index and this post; only when the posts have an index.
	$salt_index = salt_collection_index_url( 'posts' );
	if ( null !== $salt_index ) {
		$salt_index      = salt_contract_url( $salt_index );
		$salt_posts_page = (int) get_option( 'page_for_posts' );
		$salt_home       = salt_contract_home();
		$salt_crumbs     = array();
		if ( null !== $salt_home && $salt_home['href'] !== $salt_index ) {
			$salt_crumbs[] = $salt_home;
		}
		$salt_crumbs[] = array(
			'href'  => $salt_index,
			'title' => $salt_posts_page > 0 ? salt_view_title( $salt_posts_page ) : ( null !== $salt_home ? $salt_home['title'] : '' ),
		);
		$salt_crumbs[] = array(
			'href'  => salt_contract_url( $salt_permalink ),
			'title' => $salt_title,
		);
		echo '<nav class="salt-post__breadcrumb" aria-label="' . esc_attr( (string) $salt_labels['breadcrumb'] ) . '"><ol role="list">';
		foreach ( $salt_crumbs as $salt_i => $salt_crumb ) {
			printf(
				'<li><a href="%1$s"%2$s>%3$s</a></li>',
				esc_url( $salt_crumb['href'] ),
				count( $salt_crumbs ) - 1 === $salt_i ? ' aria-current="page"' : '',
				esc_html( $salt_crumb['title'] )
			);
		}
		echo '</ol></nav>';
	}

	if ( null !== $salt_category ) {
		printf(
			'<p class="salt-post__category"><a href="%1$s">%2$s</a></p>',
			esc_url( salt_contract_url( (string) get_term_link( $salt_category ) ) ),
			esc_html( $salt_category->name )
		);
	}

	salt_the_document_h1( $salt_title );

	// The meta line: byline, date and reading time, one space between them.
	$salt_meta = array();
	if ( null !== $salt_author ) {
		$salt_meta[] = '<span class="salt-post__byline">' . esc_html( (string) $salt_labels['byline'] ) . ' ' . ( '' !== $salt_author['url']
			? '<a href="' . esc_url( salt_contract_url( $salt_author['url'] ) ) . '">' . esc_html( $salt_author['name'] ) . '</a>'
			: esc_html( $salt_author['name'] ) ) . '</span>';
	}
	$salt_meta[] = '<time datetime="' . esc_attr( (string) get_the_date( 'Y-m-d' ) ) . '">' . esc_html( salt_post_date_text( $salt_post_id ) ) . '</time>';
	if ( $salt_minutes > 0 ) {
		$salt_meta[] = '<span>' . esc_html( salt_view_label( (string) $salt_labels['readTime'], array( 'n' => (string) $salt_minutes ) ) ) . '</span>';
	}
	echo '<p class="salt-post__meta">' . implode( ' ', $salt_meta ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each part escaped above.
	echo '</header>';

	// The featured image: the view's priority image (section#priority-media).
	$salt_thumbnail_id = (int) get_post_thumbnail_id( $salt_post_id );
	$salt_featured     = salt_image_contract( $salt_thumbnail_id, salt_image_slot_sizes( 'content' ) );
	if ( null !== $salt_featured ) {
		echo '<div class="salt-post__media">' . salt_image_contract_html( $salt_featured, true ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute by attribute.
	}

	// The body: the post's layout, prose and bands in stored order.
	salt_the_post_layout( $salt_post_id );

	// The footer: tags, share links and the author box.
	ob_start();
	$salt_tags = salt_post_topics( $salt_post_id );
	if ( array() !== $salt_tags ) {
		echo '<ul class="salt-post__tags" role="list" aria-label="' . esc_attr( (string) $salt_labels['tags'] ) . '">';
		foreach ( $salt_tags as $salt_tag ) {
			printf(
				'<li><a href="%1$s">%2$s</a></li>',
				esc_url( salt_contract_url( (string) get_term_link( $salt_tag ) ) ),
				esc_html( $salt_tag->name )
			);
		}
		echo '</ul>';
	}
	salt_component( 'share-links', salt_post_share( $salt_post_id ) + array( 'labels' => $salt_labels ) );
	if ( null !== $salt_author && '' !== $salt_author['url'] ) {
		salt_component(
			'author-box',
			array(
				'name'   => $salt_author['name'],
				'href'   => salt_contract_url( $salt_author['url'] ),
				'role'   => $salt_author['role'],
				'bio'    => $salt_author['bio'],
				'photo'  => salt_image_contract( $salt_author['photo'], salt_image_slot_sizes( 'thumb' ) ),
				'labels' => $salt_labels,
			)
		);
	}
	$salt_footer = (string) ob_get_clean();
	if ( '' !== trim( $salt_footer ) ) {
		echo '<footer class="salt-post__footer">' . $salt_footer . '</footer>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where each part was drawn.
	}
	echo '</article>';

	// The newer and older posts, outside the article.
	$salt_adjacent = array_filter(
		array(
			'newer' => get_next_post(),
			'older' => get_previous_post(),
		),
		static fn( $neighbour ): bool => $neighbour instanceof WP_Post
	);
	if ( array() !== $salt_adjacent ) {
		echo '<nav class="salt-post__adjacent" aria-label="' . esc_attr( (string) $salt_labels['adjacent'] ) . '"><ul role="list">';
		foreach ( $salt_adjacent as $salt_direction => $salt_neighbour ) {
			printf(
				'<li data-direction="%1$s"><a href="%2$s"><span class="salt-post__adjacent-label">%3$s</span> <span>%4$s</span></a></li>',
				esc_attr( $salt_direction ),
				esc_url( salt_contract_url( (string) get_permalink( $salt_neighbour ) ) ),
				esc_html( (string) $salt_labels[ $salt_direction ] ),
				esc_html( salt_view_title( $salt_neighbour ) )
			);
		}
		echo '</ul></nav>';
	}

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
		'publisher'        => array( '@id' => home_url( '/' ) . '#organization' ),
		'mainEntityOfPage' => array( '@id' => $salt_permalink . '#webpage' ),
	);
	if ( null !== $salt_author ) {
		$salt_article['author'] = array_filter(
			array(
				'@type' => 'Person',
				'name'  => $salt_author['name'],
				'url'   => $salt_author['url'],
			)
		);
	}
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
