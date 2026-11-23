<?php
/**
 * Single case study, as the contract draws it (salt-contract rc.9
 * markup/case-study.json, SC-034): the article with its header (the link
 * back to the case studies' index, the title as the document's h1, the
 * client, the summary, the results, the project details and, last, the
 * featured image as the view's one priority image), then the narrative:
 * the challenge, the case-study image, the solution and the gallery as
 * sections named case-study__<part>-heading (SC-012), the live-site link
 * opening in a new tab, and the delivered services as cards; then the
 * CreativeWork schema.
 *
 * The case-study image stays in the flow after the challenge (after the
 * header when there is none, before the gallery when there is no
 * solution); it is never promoted to the header, and it is drawn once, in
 * the header, when it is the featured image. A missing image draws
 * nothing.
 *
 * The header is the site's, set once for every case study on Site Settings
 * → Case Studies (salt_case_study_settings(), the contract's
 * site.caseStudy): plain, or a toned band (data-header="band") with the
 * featured image inside its content.
 *
 * Reads salt-contract's case study (collections/case-studies.json): the
 * title, summary (the excerpt) and featuredImage (the featured image) from
 * WordPress, and client, results, details, challenge, caseStudyImage,
 * solution, gallery, externalUrl and services from the generated group. A
 * case study has no page body.
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

	// The featured image is the header's, and the view's one priority image.
	$salt_band     = 'band' === $salt_layout['header'];
	$salt_thumb_id = (int) get_post_thumbnail_id( $salt_work_id );
	$salt_featured = salt_image_contract( $salt_thumb_id, $salt_band ? salt_image_slot_sizes( 'content', 'default' ) : salt_image_slot_sizes( 'content' ) );

	// The case-study image, in the flow between the challenge and the solution;
	// never promoted to the header, and drawn once (in the header) when it is
	// the featured image.
	$salt_flow_id  = (int) get_field( 'caseStudyImage', $salt_work_id, false );
	$salt_flow     = $salt_flow_id > 0 && $salt_flow_id !== $salt_thumb_id ? salt_image_contract( $salt_flow_id, salt_image_slot_sizes( 'content' ) ) : null;
	$salt_flow_tag = null !== $salt_flow ? '<div class="salt-case-study-view__flow-media">' . salt_image_contract_html( $salt_flow ) . '</div>' : '';

	// The narrative in order: challenge, the case-study image, solution,
	// gallery and the live link. A missing part draws nothing and leaves the
	// others in their order.
	$salt_parts = array();
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
	$salt_frames = array();
	foreach ( (array) get_field( 'gallery', $salt_work_id ) as $salt_image_id ) {
		$salt_image = salt_image_contract( (int) ( is_array( $salt_image_id ) ? ( $salt_image_id['ID'] ?? 0 ) : $salt_image_id ), salt_image_slot_sizes( 'content' ) );
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

	$salt_services = array();
	$salt_sizes    = salt_image_slot_sizes( 'card', 'full', '3' );
	foreach ( (array) get_field( 'services', $salt_work_id ) as $salt_service_id ) {
		$salt_service = get_post( (int) ( $salt_service_id instanceof WP_Post ? $salt_service_id->ID : $salt_service_id ) );
		$salt_card    = $salt_service instanceof WP_Post && 'publish' === $salt_service->post_status ? salt_contract_view_card( $salt_service, 'services', $salt_sizes ) : null;
		if ( null !== $salt_card ) {
			$salt_services[] = $salt_card;
		}
	}

	echo $salt_band ? '<article class="salt-case-study-view" data-header="band">' : '<article class="salt-case-study-view">';

	// The header: plain, or a toned band as a section draws one.
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
	if ( '' !== $salt_client ) {
		echo '<p class="salt-case-study-view__client">' . esc_html( $salt_client ) . '</p>';
	}
	if ( '' !== $salt_summary ) {
		echo '<p class="salt-case-study-view__summary">' . esc_html( $salt_summary ) . '</p>';
	}
	if ( array() !== $salt_results ) {
		$salt_count = count( $salt_results );
		printf(
			'<ul class="salt-grid salt-case-study-view__results" role="list" aria-label="%1$s"%2$s>',
			esc_attr( (string) $salt_labels['results'] ),
			$salt_count <= 2 ? ' data-columns="2"' : ( $salt_count >= 4 ? ' data-columns="4"' : '' )
		);
		foreach ( $salt_results as $salt_result ) {
			echo '<li class="salt-stat"><p class="salt-stat__value">' . esc_html( $salt_result[0] ) . '</p>';
			if ( '' !== $salt_result[1] ) {
				echo '<p class="salt-stat__label">' . esc_html( $salt_result[1] ) . '</p>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
	if ( array() !== $salt_details ) {
		echo '<dl class="salt-case-study-view__details">';
		foreach ( $salt_details as $salt_detail ) {
			echo '<div class="salt-case-study-view__detail"><dt class="salt-case-study-view__detail-label">' . esc_html( $salt_detail[0] ) . '</dt><dd class="salt-case-study-view__detail-value">' . esc_html( $salt_detail[1] ) . '</dd></div>';
		}
		echo '</dl>';
	}
	if ( null !== $salt_featured ) {
		echo '<div class="salt-case-study-view__media">' . salt_image_contract_html( $salt_featured, true ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute by attribute.
	}
	echo $salt_band ? '</div></header>' : '</header>';

	echo implode( '', $salt_parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each part escaped as it was built; rich text sanitised to its field's contract list.

	if ( array() !== $salt_services ) {
		echo '<section class="salt-case-study-view__section salt-case-study-view__services" aria-labelledby="case-study__services-heading">';
		echo '<h2 id="case-study__services-heading">' . esc_html( (string) $salt_labels['services'] ) . '</h2>';
		echo '<ul class="salt-grid salt-showcase" role="list" data-layout="grid">';
		foreach ( $salt_services as $salt_card ) {
			echo '<li>';
			salt_component(
				'contract-card',
				array(
					'card'   => $salt_card,
					'style'  => 'generic',
					'level'  => 3,
					'labels' => $salt_labels,
					'arrow'  => salt_contract_arrow(),
				)
			);
			echo '</li>';
		}
		echo '</ul></section>';
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
