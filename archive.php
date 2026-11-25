<?php
/**
 * An archive, as the contract draws it (salt-contract rc.8
 * markup/archive.json): the title as the document's h1 in a header, with a
 * topic's description; on an author's page, which is a team member's own
 * (inc/services/post-author.php), the profile on its first page; then the
 * listing section (markup/listing.json) with the posts as cards and the
 * pagination. Category, tag, taxonomy, author, date and the posts index
 * draw this view.
 *
 * An author with no posts draws nothing where the listing would be (the
 * founder's ruling of 19/11/2026); any other archive with none draws the
 * listing's empty message.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$salt_labels = salt_contract_labels( 'archive' );

// The posts index (is_home) is not a WordPress archive: get_the_archive_title()
// has no case for it, so it takes the posts page's title (or "Blog").
$salt_archive_member      = salt_archive_team_member();
$salt_archive_description = '';
if ( null !== $salt_archive_member ) {
	$salt_archive_title = $salt_archive_member->display_name;
} elseif ( is_home() && ! is_front_page() ) {
	$salt_posts_page    = (int) get_option( 'page_for_posts' );
	$salt_archive_title = $salt_posts_page > 0 ? salt_view_title( $salt_posts_page ) : __( 'Blog', 'salt' );
} else {
	$salt_archive_title = wp_strip_all_tags( get_the_archive_title() );
	$salt_archive_term  = get_queried_object();
	if ( $salt_archive_term instanceof WP_Term ) {
		$salt_archive_description = trim( wp_strip_all_tags( (string) $salt_archive_term->description ) );
	}
}

echo '<header>';
salt_the_document_h1( $salt_archive_title );
if ( '' !== $salt_archive_description ) {
	echo '<p>' . esc_html( $salt_archive_description ) . '</p>';
}
echo '</header>';

if ( null !== $salt_archive_member && ! is_paged() ) {
	$salt_archive_person = salt_team_person( $salt_archive_member );
	$salt_portrait       = salt_image_contract( $salt_archive_person['photo'], salt_image_slot_sizes( 'thumb' ) );
	$salt_phone_href     = salt_contract_tel( $salt_archive_person['phone'] );
	if ( $salt_portrait || $salt_archive_person['role'] || $salt_archive_person['bio'] || $salt_archive_person['email'] || $salt_phone_href || $salt_archive_person['profiles'] ) {
		echo '<div class="salt-archive__profile">';
		if ( $salt_portrait ) {
			echo '<div class="salt-archive__portrait">';
			echo salt_image_contract_html( $salt_portrait ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image renderer escapes attributes.
			echo '</div>';
		}
		if ( '' !== $salt_archive_person['role'] ) {
			echo '<p class="salt-archive__strapline">' . esc_html( $salt_archive_person['role'] ) . '</p>';
		}
		if ( '' !== $salt_archive_person['bio'] ) {
			echo '<p class="salt-archive__bio">' . esc_html( $salt_archive_person['bio'] ) . '</p>';
		}
		salt_component(
			'person-links',
			$salt_archive_person + array(
				'phoneHref'    => $salt_phone_href,
				'social_label' => (string) $salt_labels['social'],
			)
		);
		echo '</div>';
	}
}

if ( have_posts() || null === $salt_archive_member ) {
	salt_render_section( 'listing', array( 'archive' => true ) );
}

get_footer();
