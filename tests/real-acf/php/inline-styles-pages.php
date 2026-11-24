<?php
/**
 * The inline-styles browser check's site half (HARNESS ONLY, run by
 * tests/real-acf/inline-styles.mjs through run.sh). The modes:
 *
 *   seed     pretty permalinks and the representative pages: a front page
 *            with a hero, a post in a category (and that category's
 *            archive), a contact page, a case study, and a page of core
 *            blocks drawn through the_content (single.php, the harness's
 *            salt_harness_blocks type in site/salt-harness-blocks.php), and
 *            some Additional CSS.
 *            Prints them as JSON, name => address.
 *   restore  plain permalinks, the front page unset, and everything it made
 *            deleted.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';

$salt_styles_mode = (string) ( $argv[1] ?? '' );
$salt_styles_mark = 'Inline styles check';

if ( 'seed' === $salt_styles_mode ) {
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( '/%postname%/' );
	update_option( 'salt_harness_blocks', 1 );
	// The Customizer's Additional CSS, which core prints inline on every page.
	wp_update_custom_css_post( '.salt-footer{outline-offset:0}' );
	salt_harness_register_blocks();
	// The taxonomies and post types registered before the permalink change,
	// without their rules; let the next request build them (as profiles.php
	// does). The archive's link below is the plain one, which redirects, as
	// the term link would still carry the structure this process started with.
	delete_option( 'rewrite_rules' );

	$salt_styles_insert = static function ( array $post ) use ( $salt_styles_mark ): int {
		return (int) wp_insert_post(
			$post + array(
				'post_status'  => 'publish',
				'post_excerpt' => $salt_styles_mark,
			)
		);
	};

	$salt_styles_home = $salt_styles_insert(
		array(
			'post_type'  => 'page',
			'post_title' => $salt_styles_mark . ' home',
			'post_name'  => 'inline-styles-home',
		)
	);
	update_field(
		'field_salt_sections_container',
		array(
			array(
				'acf_fc_layout' => 'hero',
				'heading'       => 'Gardens that grow with you',
			),
		),
		$salt_styles_home
	);
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $salt_styles_home );

	$salt_styles_topic = wp_insert_term( $salt_styles_mark . ' topic', 'category', array( 'slug' => 'inline-styles-topic' ) );
	$salt_styles_topic = is_array( $salt_styles_topic ) ? (int) $salt_styles_topic['term_id'] : 0;
	$salt_styles_post  = $salt_styles_insert(
		array(
			'post_type'     => 'post',
			'post_title'    => $salt_styles_mark . ' post',
			'post_name'     => 'inline-styles-post',
			'post_category' => array( $salt_styles_topic ),
			'post_author'   => 1,
		)
	);

	$salt_styles_contact = $salt_styles_insert(
		array(
			'post_type'  => 'page',
			'post_title' => $salt_styles_mark . ' contact',
			'post_name'  => 'inline-styles-contact',
		)
	);
	update_field(
		'field_salt_sections_container',
		array(
			array(
				'acf_fc_layout' => 'contact',
				'heading'       => 'Talk to us',
			),
		),
		$salt_styles_contact
	);

	$salt_styles_work = $salt_styles_insert(
		array(
			'post_type'  => 'work',
			'post_title' => $salt_styles_mark . ' case study',
			'post_name'  => 'inline-styles-case-study',
		)
	);

	$salt_styles_blocks = $salt_styles_insert(
		array(
			'post_type'    => 'salt_harness_blocks',
			'post_title'   => $salt_styles_mark . ' blocks',
			'post_name'    => 'inline-styles-blocks',
			'post_content' => implode(
				"\n\n",
				array(
					'<!-- wp:paragraph --><p>Core blocks, styled by core\'s block library.</p><!-- /wp:paragraph -->',
					'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">A core button</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
					'<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>First column</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Second column</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->',
					'<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>A quote block.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->',
					'<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->',
				)
			),
		)
	);

	echo wp_json_encode(
		array(
			'home'       => home_url( '/' ),
			'post'       => get_permalink( $salt_styles_post ),
			'archive'    => add_query_arg( 'cat', $salt_styles_topic, home_url( '/' ) ),
			'contact'    => get_permalink( $salt_styles_contact ),
			'case-study' => get_permalink( $salt_styles_work ),
			'blocks'     => get_permalink( $salt_styles_blocks ),
		),
		JSON_UNESCAPED_SLASHES
	);
	exit( 0 );
}

if ( 'restore' === $salt_styles_mode ) {
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( '' );
	delete_option( 'salt_harness_blocks' );
	wp_update_custom_css_post( '' );
	flush_rewrite_rules();
	update_option( 'show_on_front', 'posts' );
	update_option( 'page_on_front', 0 );
	foreach ( get_posts(
		array(
			'post_type'   => array( 'page', 'post', 'work', 'salt_harness_blocks' ),
			'post_status' => 'any',
			'numberposts' => -1,
			's'           => $salt_styles_mark,
		)
	) as $salt_styles_doc ) {
		wp_delete_post( $salt_styles_doc->ID, true );
	}
	$salt_styles_term = get_term_by( 'slug', 'inline-styles-topic', 'category' );
	if ( $salt_styles_term ) {
		wp_delete_term( (int) $salt_styles_term->term_id, 'category' );
	}
	exit( 0 );
}

fwrite( STDERR, "usage: inline-styles-pages.php seed|restore\n" );
exit( 2 );
