<?php
/**
 * Single case study, as the contract draws it (salt-contract rc.9
 * markup/case-study.json, SC-034 and SC-036), in three parts:
 *
 * - The header row: the link back to the case studies' index, the title
 *   as the document's h1 and the summary, across the width of what follows.
 * - The story: the featured image (the view's one priority image), the
 *   challenge, the case-study image, the solution, the results, the
 *   gallery, as sections named case-study__<part>-heading (SC-012), and the
 *   live-site link opening in a new tab. Not drawn when it has no part.
 * - The aside: the project details, then the client, the sector and the
 *   delivered services, each row only when it has a value, and the aside
 *   only when a row is. A service links only to its own public page.
 *
 * Then the CreativeWork schema.
 *
 * The case-study image stays in the story after the challenge (after the
 * hero when there is none, before the results when there is no solution);
 * it is never promoted to the hero, and it is drawn once, as the hero,
 * when it is the featured image. A missing image draws nothing.
 *
 * The header is the site's, set once for every case study on Site Settings
 * → Case Studies (salt_case_study_settings(), the contract's
 * site.caseStudy): plain, or a toned band (data-header="band").
 *
 * Reads salt-contract's case study (collections/case-studies.json): the
 * title, summary (the excerpt) and featuredImage (the featured image) from
 * WordPress, and client, sector (each the record's name when its module is
 * on and the record resolves, salt_case_study_name()), results, details,
 * challenge, caseStudyImage, solution, gallery, externalUrl and services
 * from the generated group. A case study has no page body.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$salt_labels = salt_contract_labels( 'case-study' );
$salt_layout = salt_case_study_settings();

while ( have_posts() ) {
	the_post();

	$salt_work_id   = (int) get_the_ID();
	$salt_permalink = (string) get_permalink();
	$salt_text      = static fn( $value ): string => is_string( $value ) ? trim( $value ) : '';

	$salt_client       = salt_case_study_name( $salt_work_id, 'client' );
	$salt_summary      = trim( salt_post_summary( $salt_work_id ) );
	$salt_external_url = $salt_text( get_field( 'externalUrl', $salt_work_id ) );
	$salt_index        = salt_collection_index_url( 'case-studies' );

	$salt_results = array();
	foreach ( (array) get_field( 'results', $salt_work_id ) as $salt_row ) {
		$salt_stat = is_array( $salt_row ) ? $salt_text( $salt_row['stat'] ?? null ) : '';
		if ( '' !== $salt_stat ) {
			$salt_results[] = array( $salt_stat, $salt_text( $salt_row['label'] ?? null ) );
		}
	}
	$salt_details = array();
	foreach ( (array) get_field( 'details', $salt_work_id ) as $salt_row ) {
		$salt_label = is_array( $salt_row ) ? $salt_text( $salt_row['label'] ?? null ) : '';
		$salt_value = is_array( $salt_row ) ? $salt_text( $salt_row['value'] ?? null ) : '';
		if ( '' !== $salt_label && '' !== $salt_value ) {
			$salt_details[] = array( $salt_label, $salt_value );
		}
	}

	$salt_sector = salt_case_study_name( $salt_work_id, 'sector' );

	// The hero: the featured image, the story's first part and the view's one
	// priority image, in either header variant.
	$salt_band     = 'band' === $salt_layout['header'];
	$salt_sizes    = salt_image_slot_sizes( 'story' );
	$salt_thumb_id = (int) get_post_thumbnail_id( $salt_work_id );
	$salt_featured = salt_image_contract( $salt_thumb_id, $salt_sizes );

	// The case-study image, between the challenge and the solution; never
	// promoted to the hero, and drawn once (as the hero) when it is the
	// featured image.
	$salt_flow_id  = (int) get_field( 'caseStudyImage', $salt_work_id, false );
	$salt_flow     = $salt_flow_id > 0 && $salt_flow_id !== $salt_thumb_id ? salt_image_contract( $salt_flow_id, $salt_sizes ) : null;
	$salt_flow_tag = null !== $salt_flow ? '<div class="salt-case-study-view__flow-media">' . salt_image_contract_html( $salt_flow ) . '</div>' : '';

	// The story in order: the hero, challenge, the case-study image, solution,
	// results, gallery and the live link. A missing part draws nothing and
	// leaves the others in their order.
	$salt_parts = array();
	if ( null !== $salt_featured ) {
		$salt_parts[] = '<div class="salt-case-study-view__media">' . salt_image_contract_html( $salt_featured, true ) . '</div>';
	}
	foreach ( array(
		'challenge' => 'field_salt_collection_case_studies_challenge',
		'solution'  => 'field_salt_collection_case_studies_solution',
	) as $salt_name => $salt_key ) {
		// Stored HTML with paragraphs added, without the_content's other filters
		// (no curly quotes, shortcodes or embeds), kept to the field's list.
		$salt_html = salt_sanitize_html( wpautop( $salt_text( get_field( $salt_name, $salt_work_id, false ) ) ), salt_field_allowed( $salt_key ) );
		if ( salt_rich_text_has_words( $salt_html ) ) {
			$salt_parts[] = sprintf(
				'<section class="salt-case-study-view__section" aria-labelledby="case-study__%1$s-heading"><h2 id="case-study__%1$s-heading">%2$s</h2><div class="salt-rich-text">%3$s</div></section>',
				esc_attr( $salt_name ),
				esc_html( (string) $salt_labels[ $salt_name ] ),
				salt_render_rich_text_headings( $salt_html, 2 )
			);
		}
		if ( 'challenge' === $salt_name && '' !== $salt_flow_tag ) {
			$salt_parts[] = $salt_flow_tag;
		}
	}
	if ( array() !== $salt_results ) {
		$salt_count = count( $salt_results );
		$salt_cells = '';
		foreach ( $salt_results as $salt_result ) {
			$salt_cells .= '<li class="salt-stat"><p class="salt-stat__value">' . esc_html( $salt_result[0] ) . '</p>';
			if ( '' !== $salt_result[1] ) {
				$salt_cells .= '<p class="salt-stat__label">' . esc_html( $salt_result[1] ) . '</p>';
			}
			$salt_cells .= '</li>';
		}
		$salt_parts[] = sprintf(
			'<ul class="salt-grid salt-case-study-view__results" role="list" aria-label="%1$s"%2$s>%3$s</ul>',
			esc_attr( (string) $salt_labels['results'] ),
			$salt_count <= 2 ? ' data-columns="2"' : ( $salt_count >= 4 ? ' data-columns="4"' : '' ),
			$salt_cells
		);
	}
	$salt_frames = array();
	foreach ( (array) get_field( 'gallery', $salt_work_id ) as $salt_image_id ) {
		$salt_image = salt_image_contract( (int) ( is_array( $salt_image_id ) ? ( $salt_image_id['ID'] ?? 0 ) : $salt_image_id ), salt_image_slot_sizes( 'story-gallery' ) );
		if ( null !== $salt_image ) {
			$salt_frames[] = '<li><div class="salt-case-study-view__frame">' . salt_image_contract_html( $salt_image ) . '</div></li>';
		}
	}
	if ( array() !== $salt_frames ) {
		$salt_parts[] = '<section class="salt-case-study-view__section" aria-labelledby="case-study__gallery-heading"><h2 id="case-study__gallery-heading">' . esc_html( (string) $salt_labels['gallery'] ) . '</h2><ul class="salt-grid" role="list">' . implode( '', $salt_frames ) . '</ul></section>';
	}
	if ( '' !== $salt_external_url && '' !== esc_url( $salt_external_url, array( 'http', 'https' ) ) ) {
		$salt_parts[] = '<p class="salt-case-study-view__live">' . salt_get_component(
			'contract-button',
			array(
				'button' => array(
					'label'  => (string) $salt_labels['liveSite'],
					'href'   => $salt_external_url,
					'style'  => 'secondary',
					'newTab' => true,
				),
				'arrow'  => null,
			)
		) . '</p>';
	}

	// The aside: the project's facts, each row only when it has a value. A
	// service is left out unless published, and links only to its own public
	// page; client and sector are never links.
	$salt_row  = static fn( string $part, string $label, string $values ): string => '<div class="salt-case-study-view__detail' . ( '' !== $part ? ' salt-case-study-view__' . $part : '' ) . '"><dt class="salt-case-study-view__detail-label">' . esc_html( $label ) . '</dt>' . $values . '</div>';
	$salt_dd   = static fn( string $html ): string => '<dd class="salt-case-study-view__detail-value">' . $html . '</dd>';
	$salt_rows = array();
	foreach ( $salt_details as $salt_detail ) {
		$salt_rows[] = $salt_row( '', $salt_detail[0], $salt_dd( esc_html( $salt_detail[1] ) ) );
	}
	if ( '' !== $salt_client ) {
		$salt_rows[] = $salt_row( 'client', (string) $salt_labels['client'], $salt_dd( esc_html( $salt_client ) ) );
	}
	if ( '' !== $salt_sector ) {
		$salt_rows[] = $salt_row( 'sector', (string) $salt_labels['sector'], $salt_dd( esc_html( $salt_sector ) ) );
	}
	$salt_services = '';
	foreach ( (array) get_field( 'services', $salt_work_id ) as $salt_service_id ) {
		$salt_service = get_post( (int) ( $salt_service_id instanceof WP_Post ? $salt_service_id->ID : $salt_service_id ) );
		$salt_title   = $salt_service instanceof WP_Post ? trim( $salt_service->post_title ) : '';
		if ( '' === $salt_title || 'publish' !== $salt_service->post_status ) {
			continue;
		}
		$salt_href      = is_post_publicly_viewable( $salt_service ) ? (string) get_permalink( $salt_service ) : '';
		$salt_services .= $salt_dd( '' !== $salt_href ? '<a class="salt-case-study-view__service-link" href="' . esc_url( salt_contract_url( $salt_href ) ) . '">' . esc_html( $salt_title ) . '</a>' : esc_html( $salt_title ) );
	}
	if ( '' !== $salt_services ) {
		$salt_rows[] = $salt_row( 'services', (string) $salt_labels['services'], $salt_services );
	}

	echo $salt_band ? '<article class="salt-case-study-view" data-header="band">' : '<article class="salt-case-study-view">';

	// The header row: plain, or a toned band as a section draws one.
	if ( $salt_band ) {
		printf(
			'<header class="salt-section salt-case-study-view__header" data-tone="%1$s" data-tone-dark="%2$s" data-spacing="md" data-width="default"><div class="salt-section__content">',
			esc_attr( $salt_layout['headerTone'] ),
			esc_attr( $salt_layout['headerToneDark'] )
		);
	} else {
		echo '<header class="salt-case-study-view__header">';
	}
	if ( null !== $salt_index && '' !== esc_url( $salt_index ) ) {
		echo '<p class="salt-case-study-view__back"><a class="salt-case-study-view__back-link" href="' . esc_url( salt_contract_url( $salt_index ) ) . '">' . esc_html( (string) $salt_labels['back'] ) . '</a></p>';
	}
	salt_the_document_h1( salt_view_title( $salt_work_id ) );
	if ( '' !== $salt_summary ) {
		echo '<p class="salt-case-study-view__summary">' . esc_html( $salt_summary ) . '</p>';
	}
	echo $salt_band ? '</div></header>' : '</header>';

	if ( array() !== $salt_parts ) {
		echo '<div class="salt-case-study-view__story">' . implode( '', $salt_parts ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each part escaped as it was built; rich text sanitised to its field's contract list.
	}
	if ( array() !== $salt_rows ) {
		echo '<aside class="salt-case-study-view__aside" aria-label="' . esc_attr( (string) $salt_labels['details'] ) . '"><dl class="salt-case-study-view__details">' . implode( '', $salt_rows ) . '</dl></aside>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each row escaped as it was built.
	}
	echo '</article>';

	// ------------------------------------------------------------------
	// CreativeWork schema (queued into the single page @graph — SEO §15).
	// ------------------------------------------------------------------
	$salt_node = array(
		'@type'            => 'CreativeWork',
		'@id'              => $salt_permalink . '#creativework',
		'name'             => (string) get_the_title(),
		'url'              => $salt_permalink,
		'author'           => array( '@id' => home_url( '/' ) . '#organization' ),
		'mainEntityOfPage' => array( '@id' => $salt_permalink . '#webpage' ),
	);
	if ( '' !== $salt_summary ) {
		$salt_node['description'] = $salt_summary;
	}
	if ( $salt_thumb_id > 0 ) {
		$salt_image_url = wp_get_attachment_image_url( $salt_thumb_id, 'large' );
		if ( is_string( $salt_image_url ) && '' !== $salt_image_url ) {
			$salt_node['image'] = $salt_image_url;
		}
	}
	salt_schema_add( $salt_node );
}

get_footer();
