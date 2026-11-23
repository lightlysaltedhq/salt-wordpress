<?php
/**
 * A collection's items as the contract's cards (salt-contract
 * view-props/_shared.json `card`, each collection file's approved `display`
 * in contract/collections/<source>.json, rc.7), and the site data the
 * contract's markup reads (`site.labels`, `site.arrow`,
 * `site.collectionIndexes`). Shared by the collection-showcase and carousel
 * resolvers (EP-72 M3 unit 8). Logic only: nothing here draws markup.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The contract's collection sources a section may show, each with its post
 * type, its category taxonomy (null for a source without categories,
 * SC-010, SC-027), its module (null: always on) and whether its items have
 * a page of their own (contract/sections.json; emit/acf.mjs SOURCE_DEFAULTS).
 *
 * @return array<string,array{post_type: string, taxonomy: string|null, module: string|null, linked: bool}>
 */
function salt_collection_sources(): array {
	return array(
		'services'     => array(
			'post_type' => 'service',
			'taxonomy'  => 'service_category',
			'module'    => 'service',
			'linked'    => true,
		),
		'case-studies' => array(
			'post_type' => 'work',
			'taxonomy'  => 'work_type',
			'module'    => 'work',
			'linked'    => true,
		),
		'testimonials' => array(
			'post_type' => 'testimonial',
			'taxonomy'  => null,
			'module'    => 'testimonial',
			'linked'    => false,
		),
		'posts'        => array(
			'post_type' => 'post',
			'taxonomy'  => 'category',
			'module'    => null,
			'linked'    => true,
		),
		'team'         => array(
			'post_type' => null,
			'taxonomy'  => null,
			'module'    => 'team_member',
			'linked'    => true,
		),
	);
}

/**
 * Whether a source is installed: a known source whose module is on.
 *
 * @param string $source Contract source id.
 * @return bool
 */
function salt_collection_source_installed( string $source ): bool {
	$known = salt_collection_sources()[ $source ] ?? null;
	return null !== $known && ( null === $known['module'] || salt_module_enabled( $known['module'] ) );
}

/**
 * The collection's index page (the contract's `site.collectionIndexes`), or
 * null when it has none.
 *
 * The founder's ruling: a collection's own index page, and no link where
 * there is none. That is the post type's archive for services and case
 * studies; for posts the posts page when one is set, the home page only
 * when it lists the posts (show_on_front is posts), otherwise none; and
 * none for team and testimonials, which have no archive. The
 * `salt_collection_index_url` filter is this one seam; the contract
 * adapter feeds it the case's collectionIndexes.
 *
 * @param string $source Contract source id.
 * @return string|null
 */
function salt_collection_index_url( string $source ): ?string {
	$url = null;
	if ( 'posts' === $source ) {
		$page = (int) get_option( 'page_for_posts' );
		if ( $page > 0 ) {
			$url = (string) get_permalink( $page );
		} elseif ( 'posts' === get_option( 'show_on_front' ) ) {
			$url = (string) home_url( '/' );
		}
	} elseif ( in_array( $source, array( 'services', 'case-studies' ), true ) ) {
		$link = get_post_type_archive_link( salt_collection_sources()[ $source ]['post_type'] );
		$url  = is_string( $link ) ? $link : null;
	}

	$url = apply_filters( 'salt_collection_index_url', $url, $source );
	return is_string( $url ) && '' !== trim( $url ) ? $url : null;
}

/**
 * The strings the contract's markup names as `labels.<name>` for the
 * collection sections: viewAll, slide, rating, results, social, previous
 * and next.
 *
 * The founder's ruling: translatable theme strings, in the contract
 * fixtures' English. The `salt_contract_labels` filter is this one seam;
 * the contract adapter feeds it the case's site.labels. A view's own
 * labels (salt_view_label_defaults()) are merged over these for that view,
 * and the filter is told which view asks. A label may be a map of labels
 * (the share links' services).
 *
 * @param string $view A view's id, or '' for the sections and the chrome.
 * @return array<string,string|array<string,string>>
 */
function salt_contract_labels( string $view = '' ): array {
	$labels = array_merge(
		array(
			'viewAll'  => __( 'View all', 'salt' ),
			'slide'    => __( 'Slide', 'salt' ),
			'rating'   => __( 'Rated', 'salt' ),
			'results'  => __( 'Results', 'salt' ),
			'social'   => __( 'Social profiles', 'salt' ),
			'previous' => __( 'Previous', 'salt' ),
			'next'     => __( 'Next', 'salt' ),
		),
		'' !== $view ? salt_view_label_defaults( $view ) : array()
	);
	$given  = apply_filters( 'salt_contract_labels', $labels, $view );
	$string = static fn( $label ) => is_array( $label ) ? array_map( 'strval', $label ) : (string) $label;
	return is_array( $given ) ? array_map( $string, $given ) : $labels;
}

/**
 * The site's arrow glyph (the contract's `site.arrow`), or null for none.
 *
 * A translatable theme string, as the labels are (the founder's ruling of
 * 19/11/2026), so a site's language can give its own (for example ← in a
 * right-to-left language). The `salt_contract_arrow` filter is the seam
 * beside it; the contract adapter feeds it the case's site.arrow.
 *
 * @return string|null
 */
function salt_contract_arrow(): ?string {
	/* translators: The arrow drawn after a card's or a carousel's link text. A right-to-left language may use ←; leave it empty for none. */
	$arrow = apply_filters( 'salt_contract_arrow', __( '→', 'salt' ) );
	return is_string( $arrow ) && '' !== $arrow ? $arrow : null;
}

/**
 * A slide's or a rating's label: the label, then "{n} of {N}"
 * (markup/carousel-track.json, markup/card.json). A half rating is written
 * "4.5" (the founder's ruling on PR #26). "{n} of {N}" is a translatable
 * format string in the contract's English, as the labels are (a reading of
 * the labels ruling, not a ruling of its own).
 *
 * @param string    $label The label (labels.slide, labels.rating).
 * @param int|float $n     The number.
 * @param int       $total Of how many.
 * @return string
 */
function salt_contract_n_of( string $label, $n, int $total ): string {
	/* translators: 1: the number (a rating may be 4.5), 2: of how many. Follows a label such as "Slide" or "Rated". */
	$of = sprintf( __( '%1$s of %2$d', 'salt' ), (string) $n, $total );
	return trim( $label . ' ' . $of );
}

/**
 * A phone number's tel: address by the contract's rule
 * (section#display-forms), or null when it gives none: only digits,
 * spaces, dots, hyphens, brackets and one leading +; brackets only around
 * one group before the first national group ends; at most 15 digits. After
 * a country code the trunk (0) goes, and after +44 a leading 0 too.
 *
 * @param string $phone As entered.
 * @return string|null
 */
function salt_contract_tel( string $phone ): ?string {
	$phone = trim( $phone );
	if ( 1 !== preg_match( '/^(\+(\d+)[ .\-]*)?(\(\d+\)[ .\-]*)?\d[\d .\-]*$/', $phone, $match ) ) {
		return null;
	}
	$country  = $match[2] ?? '';
	$national = substr( $phone, strlen( $match[1] ?? '' ) );
	if ( '' !== $country ) {
		$national = (string) preg_replace( '/^\(0\)/', '', $national );
	}
	$national = (string) preg_replace( '/\D/', '', $national );
	if ( '44' === $country ) {
		$national = (string) preg_replace( '/^0/', '', $national );
	}
	$digits = $country . $national;
	if ( '' === $national || strlen( $digits ) > 15 ) {
		return null;
	}
	return 'tel:' . ( '' !== $country ? '+' . $country : '' ) . $national;
}

/**
 * A post's topics as the contract's tags ({ id, title }), in the order
 * WordPress gives them, never the default category (Uncategorised).
 *
 * @param WP_Post $post     The item.
 * @param string  $taxonomy The source's taxonomy.
 * @return array<int,array{id: string, title: string}>
 */
function salt_collection_tags( WP_Post $post, string $taxonomy ): array {
	$terms   = 'category' === $taxonomy ? salt_post_topics( $post->ID ) : get_the_terms( $post, $taxonomy );
	$default = 'category' === $taxonomy ? (int) get_option( 'default_category' ) : 0;
	$tags    = array();
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		if ( ! is_object( $term ) || ( $default > 0 && (int) $term->term_id === $default ) || '' === trim( (string) $term->name ) ) {
			continue;
		}
		$tags[] = array(
			'id'    => (string) $term->term_id,
			'title' => (string) $term->name,
		);
	}
	return $tags;
}

/**
 * One item as the contract's card, by its source's approved display
 * (contract/collections/<source>.json `display`), or null when the card
 * cannot be drawn (no title; a testimonial with no quote): logged.
 *
 * @param WP_Post             $post    The item.
 * @param string              $source  Contract source id.
 * @param array<string,mixed> $options icons, ratings, tags (bool: what the
 *                                     section shows) and sizes (the image
 *                                     slot's sizes, '' for no image).
 * @return array<string,mixed>|null
 */
function salt_collection_card( WP_Post|WP_User $post, string $source, array $options ): ?array {
	$id     = (int) $post->ID;
	$person = $post instanceof WP_User ? salt_team_person( $post ) : null;
	if ( 'team' === $source && empty( $person['url'] ) ) {
		return null;
	}
	$title = trim( (string) ( $person['name'] ?? $post->post_title ) );
	if ( '' === $title ) {
		salt_log( sprintf( 'collection card: %s item %d has no title — skipped.', $source, $id ) );
		return null;
	}

	$text  = static fn( $value ): ?string => is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : null;
	$sizes = (string) ( $options['sizes'] ?? '' );

	$card = array(
		'id'        => (string) $id,
		'title'     => $title,
		'href'      => $person['url'] ?? ( salt_collection_sources()[ $source ]['linked'] ? (string) get_the_permalink( $post ) : null ),
		'image'     => '' !== $sizes ? salt_image_contract( $person['photo'] ?? (int) get_post_thumbnail_id( $post ), $sizes ) : null,
		'icon'      => null,
		'meta'      => null,
		'text'      => $text( $person['bio'] ?? salt_post_summary( $post ) ),
		'date'      => null,
		'rating'    => null,
		'email'     => null,
		'phone'     => null,
		'phoneHref' => null,
		'profiles'  => array(),
		'tags'      => array(),
	);
	if ( '' === (string) $card['href'] ) {
		$card['href'] = null;
	}

	switch ( $source ) {
		case 'services':
			$card['icon'] = ! empty( $options['icons'] ) ? $text( get_field( 'icon', $id ) ) : null;
			break;
		case 'case-studies':
			$card['meta'] = $text( get_field( 'client', $id ) );
			break;
		case 'posts':
			$date         = get_the_date( 'Y-m-d', $post );
			$card['date'] = is_string( $date ) && '' !== $date ? $date : null;
			break;
		case 'team':
			$phone             = $text( $person['phone'] );
			$card['meta']      = $text( $person['role'] );
			$card['email']     = $text( $person['email'] );
			$card['phone']     = $phone;
			$card['phoneHref'] = null === $phone ? null : salt_contract_tel( $phone );
			$card['profiles']  = $person['profiles'];
			break;
		case 'testimonials':
			$quote = $text( get_field( 'quote', $id ) );
			if ( null === $quote ) {
				salt_log( sprintf( 'collection card: testimonial %d has no quote — skipped.', $id ) );
				return null;
			}
			$meta           = array_filter( array( $text( get_field( 'role', $id ) ), $text( get_field( 'company', $id ) ) ) );
			$card['meta']   = array() !== $meta ? implode( ', ', $meta ) : null;
			$card['text']   = $quote;
			$rating         = get_field( 'rating', $id );
			$card['rating'] = ! empty( $options['ratings'] ) && is_numeric( $rating ) ? (float) $rating : null;
			break;
	}

	$taxonomy = salt_collection_sources()[ $source ]['taxonomy'];
	if ( ! empty( $options['tags'] ) && null !== $taxonomy ) {
		$card['tags'] = salt_collection_tags( $post, $taxonomy );
	}

	return $card;
}

/**
 * A team member's socials rows as the contract's profiles: each row in
 * order, its name naming the link, its network derived from the address
 * (contract/socials.json, through salt_social_link()). A row with no name
 * or no usable address is skipped and logged.
 *
 * @param mixed $rows The socials repeater.
 * @param int   $id   The member, for the log.
 * @return array<int,array{name: string, href: string, network: string|null}>
 */
function salt_collection_profiles( $rows, int $id ): array {
	$profiles = array();
	foreach ( is_array( $rows ) ? $rows : array() as $i => $row ) {
		$name = trim( (string) ( is_array( $row ) ? ( $row['name'] ?? '' ) : '' ) );
		$link = salt_social_link( is_array( $row ) ? (string) ( $row['url'] ?? '' ) : '' );
		if ( '' === $name || null === $link ) {
			salt_log( sprintf( 'collection card: team member %d social row %d has no name or address — skipped.', $id, (int) $i ) );
			continue;
		}
		$profiles[] = array(
			'name'    => $name,
			'href'    => $link['url'],
			'network' => '' !== $link['icon'] ? $link['icon'] : null,
		);
	}
	return $profiles;
}

/**
 * The items a section's collection query picks for a source. A team
 * selection uses the user ID namespace, never post IDs.
 *
 * @param string $source Contract source id.
 * @return WP_Post[]|WP_User[]
 */
function salt_collection_query( string $source ): array {
	$controls = salt_query_controls_from_query();
	if ( 'manual' === $controls['source'] && empty( $controls['manual'] ) ) {
		return array();
	}
	if ( 'team' !== $source ) {
		return salt_resolve_query( $controls, salt_collection_sources()[ $source ]['post_type'] );
	}
	$args = array(
		'meta_key'   => 'showOnWebsite',
		'meta_value' => '1',
		'orderby'    => 'title' === ( $controls['orderby'] ?? '' ) ? 'display_name' : 'ID',
		'order'      => 'ASC',
		'number'     => $controls['count'] ?? 0,
	);
	if ( 'manual' === $controls['source'] ) {
		$args['include'] = $controls['manual'];
		$args['orderby'] = 'include';
	} elseif ( 'date' === ( $controls['orderby'] ?? '' ) ) {
		$args['orderby'] = 'registered';
		$args['order']   = 'DESC';
	}
	return get_users( $args );
}

/**
 * Schema nodes for the cards a section draws, as the former services,
 * work and team sections queued them: a Service per service, a
 * CreativeWork per case study, a Person per team member (never their
 * email or phone).
 *
 * @param string                         $source Contract source id.
 * @param array<int,array<string,mixed>> $cards  Cards.
 * @return void
 */
function salt_collection_schema( string $source, array $cards ): void {
	foreach ( $cards as $card ) {
		if ( 'services' === $source ) {
			$node = array(
				'@type' => 'Service',
				'name'  => $card['title'],
			);
			if ( null !== $card['href'] ) {
				$node['@id'] = $card['href'] . '#service';
				$node['url'] = $card['href'];
			}
			if ( null !== $card['text'] ) {
				$node['description'] = $card['text'];
			}
		} elseif ( 'case-studies' === $source ) {
			$node = array(
				'@type' => 'CreativeWork',
				'name'  => $card['title'],
			);
			if ( null !== $card['href'] ) {
				$node['url'] = $card['href'];
			}
			if ( null !== $card['meta'] ) {
				$node['about'] = array(
					'@type' => 'Organization',
					'name'  => $card['meta'],
				);
			}
		} elseif ( 'team' === $source ) {
			$node = array(
				'@type' => 'Person',
				'name'  => $card['title'],
			);
			if ( null !== $card['meta'] ) {
				$node['jobTitle'] = $card['meta'];
			}
		} else {
			continue;
		}
		salt_schema_add( $node );
	}
}

/**
 * The PHP date format the theme draws dates in, or '' when WordPress has
 * none for the site's language (the founder's ruling of 19/11/2026: dates
 * follow the site's language, so a site set to en-GB draws "12 March 2026").
 *
 * 1. The site's Date Format setting (Settings > General), unless it is
 *    WordPress's stock "F j, Y". A language pack sets its own at install
 *    (en_GB's is "j F Y"), and an owner may choose another; either is kept.
 * 2. Otherwise WordPress's own translation of that stock format for the
 *    site's language, from its language pack (en_GB "j F Y", de_DE "j. F Y").
 * 3. Otherwise '': salt_date_text() asks PHP's intl for the language's long
 *    date, or falls back to "F j, Y".
 *
 * @return string
 */
function salt_date_format(): string {
	$setting = (string) get_option( 'date_format' );
	if ( '' !== $setting && 'F j, Y' !== $setting ) {
		return $setting;
	}
	// phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- WordPress core's own string, translated by its language pack.
	$translated = __( 'F j, Y' );
	return 'F j, Y' !== $translated ? $translated : '';
}

/**
 * A date's text in the site's language (salt_date_format()), drawn by
 * wp_date(), which also writes the month in that language. With no format
 * from WordPress, PHP intl's long date for the site's locale
 * (IntlDateFormatter::LONG, the contract's dateStyle long; en_GB
 * "12 March 2026"), and without intl "F j, Y".
 *
 * Every post, card and result date the theme draws comes through here.
 *
 * @param int               $timestamp The moment, a Unix timestamp.
 * @param DateTimeZone|null $timezone  The zone to draw it in; the site's by default.
 * @return string
 */
function salt_date_text( int $timestamp, ?DateTimeZone $timezone = null ): string {
	$timezone = $timezone ?? wp_timezone();
	$format   = salt_date_format();
	if ( '' === $format && class_exists( 'IntlDateFormatter' ) ) {
		$formatter = new IntlDateFormatter( get_locale(), IntlDateFormatter::LONG, IntlDateFormatter::NONE, $timezone );
		$text      = $formatter->format( $timestamp );
		if ( is_string( $text ) && '' !== $text ) {
			return $text;
		}
	}
	return (string) wp_date( '' !== $format ? $format : 'F j, Y', $timestamp, $timezone );
}

/**
 * A post's published (or modified) date's text (salt_date_text()).
 *
 * @param int|WP_Post $post  The post.
 * @param string      $field 'date' or 'modified'.
 * @return string '' when the post has no such date.
 */
function salt_post_date_text( $post, string $field = 'date' ): string {
	$timestamp = get_post_timestamp( $post, $field );
	return false === $timestamp ? '' : salt_date_text( (int) $timestamp );
}

/**
 * A card's date's text (section#display-forms), from YYYY-MM-DD, as a day:
 * drawn in UTC so the day never moves (salt_date_text()).
 *
 * @param string $ymd The date, YYYY-MM-DD.
 * @return string
 */
function salt_contract_date_text( string $ymd ): string {
	$date = date_create_immutable( $ymd . ' 00:00:00', new DateTimeZone( 'UTC' ) );
	return false === $date ? $ymd : salt_date_text( $date->getTimestamp(), new DateTimeZone( 'UTC' ) );
}

/**
 * Hand the carousel script its control labels (labels.previous and
 * labels.next, markup/carousel-track.json) as window.saltCarousel, when the
 * page enqueued it ('wp_enqueue_scripts', after salt_enqueue_section_assets()).
 * The wording is salt_contract_labels()'s, so it is pending the same ruling.
 *
 * @return void
 */
function salt_carousel_script_labels(): void {
	if ( ! wp_script_is( 'salt-section-carousel', 'enqueued' ) ) {
		return;
	}
	$labels = salt_contract_labels();
	wp_add_inline_script(
		'salt-section-carousel',
		'window.saltCarousel = ' . wp_json_encode(
			array(
				'labels' => array(
					'previous' => $labels['previous'] ?? '',
					'next'     => $labels['next'] ?? '',
				),
			)
		) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'salt_carousel_script_labels', 21 );
