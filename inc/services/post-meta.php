<?php
/**
 * Post meta services: reading time, share links, topics, a post's layout
 * (blog single build-out — spec §6 extension).
 *
 * Pure string/array builders live here so they unit-test without WP;
 * the WP-context wrappers stay thin.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estimated reading time in whole minutes (PURE).
 *
 * Words are counted on tag-stripped content at 200 wpm (the common
 * editorial baseline); anything non-empty reads as at least 1 minute.
 *
 * @param string $content  Raw post content (may contain HTML).
 * @param int    $wpm      Words per minute (defaults 200; <= 0 resets to 200).
 * @return int Minutes (0 for empty content).
 */
function salt_reading_time_minutes( string $content, int $wpm = 200 ): int {
	if ( $wpm <= 0 ) {
		salt_log( sprintf( 'salt_reading_time_minutes(): non-positive wpm %d — using 200.', $wpm ) );
		$wpm = 200;
	}

	$text = trim( wp_strip_all_tags( $content ) );
	if ( '' === $text ) {
		return 0;
	}

	$split = preg_split( '/\s+/', $text );
	$words = count( is_array( $split ) ? $split : array() );

	return max( 1, (int) ceil( $words / $wpm ) );
}

/**
 * The share-service registry (PURE) — key => label + URL template + icon.
 *
 * Templates use {url} and {title} placeholders (already-encoded values are
 * substituted by salt_share_links()). Icons are bundled asset names
 * (assets/src/icons/{icon}.svg).
 *
 * @return array<string,array{label:string,template:string,icon:string}>
 */
function salt_share_services(): array {
	return array(
		'x'         => array(
			'label'    => __( 'Share on X', 'salt' ),
			'template' => 'https://x.com/intent/post?url={url}&text={title}',
			'icon'     => 'x',
		),
		'facebook'  => array(
			'label'    => __( 'Share on Facebook', 'salt' ),
			'template' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
			'icon'     => 'facebook',
		),
		'linkedin'  => array(
			'label'    => __( 'Share on LinkedIn', 'salt' ),
			'template' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
			'icon'     => 'linkedin',
		),
		'whatsapp'  => array(
			'label'    => __( 'Share on WhatsApp', 'salt' ),
			'template' => 'https://wa.me/?text={title}%20{url}',
			'icon'     => 'whatsapp',
		),
		'bluesky'   => array(
			'label'    => __( 'Share on Bluesky', 'salt' ),
			'template' => 'https://bsky.app/intent/compose?text={title}%20{url}',
			'icon'     => 'bluesky',
		),
		'reddit'    => array(
			'label'    => __( 'Share on Reddit', 'salt' ),
			'template' => 'https://www.reddit.com/submit?url={url}&title={title}',
			'icon'     => 'reddit',
		),
		'pinterest' => array(
			'label'    => __( 'Share on Pinterest', 'salt' ),
			'template' => 'https://pinterest.com/pin/create/button/?url={url}&description={title}',
			'icon'     => 'pinterest',
		),
		'email'     => array(
			'label'    => __( 'Share by email', 'salt' ),
			'template' => 'mailto:?subject={title}&body={url}',
			'icon'     => 'mail',
		),
	);
}

/**
 * The default share suite rendered when a caller passes no explicit list.
 *
 * @return string[] Service keys, in render order.
 */
function salt_share_default_services(): array {
	return array( 'x', 'facebook', 'linkedin', 'whatsapp', 'bluesky', 'reddit', 'email' );
}

/**
 * Resolve share links for a URL + title (PURE).
 *
 * Unknown service keys are skipped + salt_log()ged — never a dead button.
 *
 * @param string   $url      Absolute URL to share.
 * @param string   $title    Title/text accompanying the share.
 * @param string[] $services Service keys; empty = salt_share_default_services().
 * @return array<int,array{key:string,label:string,url:string,icon:string}>
 */
function salt_share_links( string $url, string $title, array $services = array() ): array {
	if ( '' === trim( $url ) ) {
		salt_log( 'salt_share_links(): empty url — no links built.' );
		return array();
	}

	$registry = salt_share_services();
	$keys     = array() === $services ? salt_share_default_services() : $services;
	$links    = array();

	foreach ( $keys as $key ) {
		if ( ! is_string( $key ) || ! isset( $registry[ $key ] ) ) {
			salt_log( sprintf( 'salt_share_links(): unknown share service "%s" — skipped.', is_string( $key ) ? $key : gettype( $key ) ) );
			continue;
		}

		$service = $registry[ $key ];
		$links[] = array(
			'key'   => $key,
			'label' => $service['label'],
			'url'   => strtr(
				$service['template'],
				array(
					'{url}'   => rawurlencode( $url ),
					'{title}' => rawurlencode( $title ),
				)
			),
			'icon'  => $service['icon'],
		);
	}

	return $links;
}

/**
 * A post's topics as drawn: its categories in WordPress's order, without
 * the default category ("Uncategorised"), which is never drawn as a topic
 * (salt-contract rc.7 contract/collections/_topic.json).
 *
 * @param int $post_id Post ID (0 = current post).
 * @return WP_Term[]
 */
function salt_post_topics( int $post_id = 0 ): array {
	$post_id = $post_id > 0 ? $post_id : (int) get_the_ID();
	$terms   = get_the_terms( $post_id, 'category' );
	if ( ! is_array( $terms ) ) {
		return array();
	}

	$default_id = (int) get_option( 'default_category' );
	$terms      = array_filter( $terms, static fn( $term ): bool => $term instanceof WP_Term && $term->term_id !== $default_id );
	$primary    = function_exists( 'get_field' ) ? (int) get_field( 'field_salt_collection_posts_primary_topic', $post_id, false ) : 0;
	usort(
		$terms,
		static function ( $a, $b ) use ( $primary ) {
			if ( $a->term_id === $primary || $b->term_id === $primary ) {
				return (int) ( $b->term_id === $primary ) <=> (int) ( $a->term_id === $primary );
			}
			return strcasecmp( $a->name, $b->name );
		}
	);
	return $terms;
}

/**
 * The topic a post shows above its title (salt-contract rc.7
 * contract/collections/posts.json `primaryTopic`).
 *
 * Its primary topic when that is one of its topics; otherwise its first
 * topic; null when it has none. The default category is never one
 * (salt_post_topics()).
 *
 * @param int $post_id Post ID (0 = current post).
 * @return WP_Term|null
 */
function salt_post_primary_category( int $post_id = 0 ): ?WP_Term {
	$topics = salt_post_topics( $post_id );
	if ( array() === $topics ) {
		return null;
	}

	return $topics[0];
}

/**
 * The values a rich-text section in a post's layout has when an editor has
 * left them alone: its eyebrow, heading and alignment, and its section
 * settings, from the generated posts group (each field's default_value; ''
 * where there is none).
 *
 * @return array<string,mixed>
 */
function salt_post_prose_defaults(): array {
	static $defaults = null;
	if ( null !== $defaults ) {
		return $defaults;
	}

	$walk     = static function ( array $fields ) use ( &$walk ): array {
		$out = array();
		foreach ( $fields as $field ) {
			if ( '' === (string) ( $field['name'] ?? '' ) ) {
				continue;
			}
			$out[ (string) $field['name'] ] = 'group' === ( $field['type'] ?? '' ) ? $walk( (array) ( $field['sub_fields'] ?? array() ) ) : ( $field['default_value'] ?? '' );
		}
		return $out;
	};
	$defaults = array();
	foreach ( salt_generated_collection_groups() as $group ) {
		foreach ( 'posts' === ( $group['salt']['collection'] ?? '' ) ? $group['fields'] : array() as $field ) {
			foreach ( 'layout' === $field['name'] ? (array) $field['layouts'] : array() as $layout ) {
				if ( 'rich-text' === $layout['name'] ) {
					$defaults = $walk( (array) $layout['sub_fields'] );
				}
			}
		}
	}
	return $defaults;
}

/**
 * Whether a rich-text row of a post's layout is prose alone, drawn as the
 * post's own prose rather than as a band (salt-contract rc.7
 * section#sections-in-a-post): no eyebrow, no heading, the default
 * alignment, and the default spacing, tone, dark tone, width and divider,
 * no background image and no anchor (PURE).
 *
 * A value that is unset (null, '' or false) counts as the default.
 *
 * @param array<string,mixed> $row      The row's eyebrow, heading, alignment and settings.
 * @param array<string,mixed> $defaults salt_post_prose_defaults().
 * @return bool
 */
function salt_post_row_is_prose( array $row, array $defaults ): bool {
	$untouched = static function ( $value, $preset_value ): bool {
		if ( null === $value || '' === $value || false === $value ) {
			return true;
		}
		return is_scalar( $value ) && is_scalar( $preset_value ) && (string) $value === (string) $preset_value;
	};
	$settings  = is_array( $row['settings'] ?? null ) ? $row['settings'] : array();
	$preset    = is_array( $defaults['settings'] ?? null ) ? $defaults['settings'] : array();

	if ( '' !== trim( (string) ( $row['eyebrow'] ?? '' ) ) || '' !== trim( (string) ( $row['heading'] ?? '' ) ) ) {
		return false;
	}
	if ( ! $untouched( $row['alignment'] ?? null, $defaults['alignment'] ?? '' ) ) {
		return false;
	}
	foreach ( array( 'spacing', 'tone', 'toneDark', 'width', 'divider' ) as $name ) {
		if ( ! $untouched( $settings[ $name ] ?? null, $preset[ $name ] ?? '' ) ) {
			return false;
		}
	}
	$background = is_array( $settings['backgroundImage'] ?? null ) ? $settings['backgroundImage'] : array();
	return empty( $background['image'] ) && '' === trim( (string) ( $settings['anchorId'] ?? '' ) );
}

/**
 * The text a post's reading time counts: all the text a reader reads in the
 * post's own sections, every row of its layout (the founder's ruling of
 * 19/11/2026: the post's text, not the site's header or footer, which are
 * never read here). Markup is stripped.
 *
 * Read by key from the stored rows, as the rest of this file does. A row's
 * text is each text, textarea and wysiwyg field the posts group generates
 * for its layout (headings, eyebrows, intros, bodies, button labels), in
 * repeaters and groups too, but not its settings, a link's url or a select.
 * An FAQ row adds the question and answer of each FAQ its query draws (the
 * FAQ section's own query, salt_query_controls_from_query()), and a gallery
 * row with captions on adds each image's caption.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function salt_post_reading_text( int $post_id ): string {
	$rows = function_exists( 'get_field' ) ? get_field( 'field_salt_collection_posts_layout', $post_id, false ) : null;
	$rows = is_array( $rows ) ? array_values( $rows ) : array();

	// The layout's fields by key, from the generated posts group; a row's settings are not read.
	$fields = array();
	$index  = static function ( array $group_fields ) use ( &$index, &$fields ): void {
		foreach ( $group_fields as $field ) {
			if ( ! is_array( $field ) || 'settings' === ( $field['name'] ?? '' ) ) {
				continue;
			}
			$fields[ (string) ( $field['key'] ?? '' ) ] = $field;
			$index( (array) ( $field['sub_fields'] ?? array() ) );
			foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
				$index( (array) ( $layout['sub_fields'] ?? array() ) );
			}
		}
	};
	foreach ( salt_generated_collection_groups() as $group ) {
		if ( 'posts' === ( $group['salt']['collection'] ?? '' ) ) {
			$index( (array) ( $group['fields'] ?? array() ) );
		}
	}

	$parts = array();
	$read  = static function ( array $value ) use ( &$read, &$parts, $fields ): void {
		foreach ( $value as $key => $item ) {
			$field = $fields[ (string) $key ] ?? null;
			if ( null === $field ) {
				continue;
			}
			$type = (string) ( $field['type'] ?? '' );
			if ( in_array( $type, array( 'text', 'textarea', 'wysiwyg' ), true ) && ! in_array( $field['name'] ?? '', array( 'url', 'anchorId' ), true ) && is_scalar( $item ) ) {
				$parts[] = wp_strip_all_tags( (string) $item );
			} elseif ( 'group' === $type && is_array( $item ) ) {
				$read( $item );
			} elseif ( 'repeater' === $type && is_array( $item ) ) {
				foreach ( $item as $sub_row ) {
					$read( is_array( $sub_row ) ? $sub_row : array() );
				}
			}
		}
	};

	$i = 0;
	while ( function_exists( 'have_rows' ) && have_rows( 'field_salt_collection_posts_layout', $post_id ) ) {
		the_row();
		$row = is_array( $rows[ $i ] ?? null ) ? $rows[ $i ] : array();
		++$i;
		$read( $row );
		$layout = (string) get_row_layout();
		if ( 'faq' === $layout && salt_module_enabled( 'faq' ) ) {
			foreach ( salt_resolve_query( salt_query_controls_from_query(), 'faq' ) as $faq ) {
				$parts[] = (string) $faq->post_title;
				$parts[] = wp_strip_all_tags( (string) get_field( 'answer', $faq->ID ) );
			}
		}
		if ( 'gallery' === $layout && ! empty( $row['field_salt_collection_posts_layout_gallery_captions'] ) && function_exists( 'wp_get_attachment_caption' ) ) {
			foreach ( (array) ( $row['field_salt_collection_posts_layout_gallery_images'] ?? array() ) as $image ) {
				$parts[] = (string) wp_get_attachment_caption( (int) $image );
			}
		}
	}
	return implode( ' ', array_filter( array_map( 'trim', $parts ), 'strlen' ) );
}

/**
 * Draw a post's layout (salt-contract rc.8 section#sections-in-a-post), in
 * stored order: a rich-text row that is prose alone as the post's own
 * prose, in the reading column; every other row as a band, through the
 * section's resolver and view, as on a page.
 *
 * The bands are planned as a page's are (template-parts/flexible-content.php):
 *  - every minted id is settled before any band draws, holding every
 *    editor's anchor, and an anchored band settles its own as it draws
 *    (salt_section_plan_rows(), section#anchors); a prose row takes no id
 *    but counts in a minted id's number, as a row that draws nothing does;
 *  - a band's index counts the rows drawn before it, prose included, as
 *    the contract's post/band-* cases number them; a row that draws
 *    nothing takes no place;
 *  - the post's title holds the h1 (salt_the_document_h1()), so no band
 *    claims it;
 *  - the featured image, when the post has one that resolves, is drawn
 *    above the layout as the priority image (single-post.php), so no band
 *    takes it (section#priority-media).
 * A band straight after prose keeps its top spacing.
 *
 * @param int $post_id The post.
 * @return void
 */
function salt_the_post_layout( int $post_id ): void {
	// By key: a page section's gallery has a `layout` select of the same name.
	$field = 'field_salt_collection_posts_layout';
	if ( ! function_exists( 'have_rows' ) || ! have_rows( $field, $post_id ) ) {
		return;
	}
	$defaults = salt_post_prose_defaults();
	$is_prose = static fn(): bool => 'rich-text' === (string) get_row_layout() && salt_post_row_is_prose(
		array(
			'eyebrow'   => get_sub_field( 'eyebrow' ),
			'heading'   => get_sub_field( 'heading' ),
			'alignment' => get_sub_field( 'alignment' ),
			'settings'  => salt_section_settings(),
		),
		$defaults
	);

	$thumbnail = (int) get_post_thumbnail_id( $post_id );
	if ( $thumbnail > 0 && is_array( wp_get_attachment_image_src( $thumbnail, 'full' ) ) ) {
		salt_render_state( 'set', 'priority_media', true );
	}

	$ids   = salt_section_plan_rows( $field, $post_id, static fn(): bool => ! $is_prose() );
	$first = salt_render_state()['section_index'];
	$prose = 0;
	$row   = -1;
	while ( have_rows( $field, $post_id ) ) {
		the_row();
		++$row;
		if ( salt_section_hidden_on_this_page() ) {
			continue;
		}
		if ( $is_prose() ) {
			$body = (string) get_sub_field( 'body' );
			if ( '' !== trim( $body ) ) {
				printf(
					'<div class="x-post-prose mx-auto w-full max-w-3xl px-4 sm:px-6 lg:px-8"><div class="x-prose">%s</div></div>',
					salt_render_rich_text_headings( salt_sanitize_html( $body, salt_field_allowed( 'field_salt_collection_posts_layout_rich_text_body' ) ), 1 ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised to the field's contract list before adjusting ranks.
				);
				salt_render_state( 'set', 'last_background', '' );
				++$prose;
			}
			continue;
		}
		salt_render_state( 'set', 'plan_id', $ids[ $row ] ?? '' );
		salt_render_section(
			salt_section_slug_for_layout( (string) get_row_layout() ),
			array(
				'index'      => $prose + salt_render_state()['section_index'] - $first,
				'is_primary' => false,
				'id'         => $ids[ $row ] ?? '',
			)
		);
		salt_render_state( 'set', 'plan_id', '' );
	}
}
