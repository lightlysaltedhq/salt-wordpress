<?php
/**
 * Schema: page-level JSON-LD @graph assembly (spec §15; SEO01/SEO02/SEO03/SEO05).
 *
 * Sections NEVER emit their own <script type="application/ld+json"> — they
 * queue nodes via salt_schema_add() and this builder assembles exactly ONE
 * deduplicated @graph per page on wp_footer.
 *
 * Assembly rules (frozen):
 *  1. Base nodes (SEO05 linkage): Organization ({home}#organization, omitted
 *     when the org name is empty), WebSite ({home}#website, publisher
 *     @id-ref to the organization), WebPage/CollectionPage ({page}#webpage,
 *     isPartOf @id-ref to the website, breadcrumb @id-ref when breadcrumbs
 *     exist) and BreadcrumbList ({page}#breadcrumb).
 *  2. Queued nodes get generated @ids where missing (FAQPage => {page}#faq,
 *     others {page}#{type}-{n}); duplicate @ids merge shallowly — the first
 *     node wins per key — and the merge is logged.
 *  3. SEO03: exactly ONE FAQPage per page — every queued FAQPage node's
 *     mainEntity array merges (in order) into the single {page}#faq node.
 *  4. SEO01: office LocalBusiness nodes (type LocalBusiness, or any subtype
 *     flagged 'salt_office' => true) reference the brand via
 *     parentOrganization. The brand node is Organization by default; the
 *     org_type option may promote it to LocalBusiness ONLY when no office
 *     node is queued — the brand is never a LocalBusiness alongside offices.
 *  5. SEO02: aggregateRating on an Organization/LocalBusiness node is
 *     self-serving and stripped; any node carrying the brand @id
 *     ({home}#organization) has aggregateRating/review stripped regardless
 *     of its @type (subtype leak); any aggregateRating with a zero
 *     ratingCount/reviewCount is stripped (omit-when-zero). The strip
 *     re-runs on every same-@id merge result so a queued node cannot
 *     smuggle a rating into the brand node via the shallow merge.
 *  6. Deterministic order: Organization, WebSite, WebPage, BreadcrumbList,
 *     then queued nodes in insertion order.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Per-request node queue.
// ---------------------------------------------------------------------------

/**
 * Access/mutate the per-request schema node queue (single authoritative store).
 *
 * @param string                    $op   'get'|'add'|'reset'.
 * @param array<string,mixed>|null $node Node when adding.
 * @return array<int,array<string,mixed>> The queue after the operation.
 */
function salt_schema_queue( string $op = 'get', ?array $node = null ): array {
	static $queue = array();

	if ( 'reset' === $op ) {
		$queue = array();
	}

	if ( 'add' === $op && null !== $node ) {
		$queue[] = $node;
	}

	return $queue;
}

/**
 * Queue a JSON-LD node for the current page's @graph.
 *
 * Sections call this instead of printing <script> tags themselves
 * (conventions contract, SEO03). A node MUST carry a string '@type';
 * it MAY carry an '@id' (one is generated at assembly time otherwise).
 * Office LocalBusiness subtypes flag themselves with 'salt_office' => true
 * (stripped from output — SEO01).
 *
 * @param array<string,mixed> $node JSON-LD node.
 * @return void
 */
function salt_schema_add( array $node ): void {
	if ( ! isset( $node['@type'] ) || ! is_string( $node['@type'] ) || '' === $node['@type'] ) {
		salt_log( 'schema: node rejected — "@type" (non-empty string) is required.' );
		return;
	}

	salt_schema_queue( 'add', $node );
}

/**
 * Clear the schema node queue (per request / per test).
 *
 * @return void
 */
function salt_schema_reset(): void {
	salt_schema_queue( 'reset' );
}

// ---------------------------------------------------------------------------
// Assembly (pure — fixture-driven, unit-testable).
// ---------------------------------------------------------------------------

/**
 * Police aggregateRating on a node (SEO02).
 *
 * Organization/LocalBusiness self-ratings are stripped outright; any node
 * carrying the brand @id has aggregateRating AND review stripped regardless
 * of its @type (a Dentist-typed node with the org @id is still the brand —
 * subtype leak); any aggregateRating whose ratingCount/reviewCount is zero
 * is stripped (omit-when-zero).
 *
 * @param array<string,mixed> $node      Node (with '@id' already assigned).
 * @param bool                $is_office Whether the node is an office LocalBusiness (SEO01 flag).
 * @param string              $org_id    The brand @id ({home}#organization); '' skips the @id check.
 * @return array<string,mixed>
 */
function salt_schema_police_rating( array $node, bool $is_office, string $org_id = '' ): array {
	$type = (string) ( $node['@type'] ?? '' );
	$id   = (string) ( $node['@id'] ?? '' );

	if ( '' !== $org_id && $id === $org_id && ( array_key_exists( 'aggregateRating', $node ) || array_key_exists( 'review', $node ) ) ) {
		unset( $node['aggregateRating'], $node['review'] );
		salt_log( sprintf( 'schema: stripped self-serving aggregateRating/review from brand-@id node %s "%s" (SEO02).', $type, $id ) );
		return $node;
	}

	if ( ! array_key_exists( 'aggregateRating', $node ) ) {
		return $node;
	}

	if ( $is_office || in_array( $type, array( 'Organization', 'LocalBusiness' ), true ) ) {
		unset( $node['aggregateRating'] );
		salt_log( sprintf( 'schema: stripped self-serving aggregateRating from %s "%s" (SEO02).', $type, $id ) );
		return $node;
	}

	$rating = is_array( $node['aggregateRating'] ) ? $node['aggregateRating'] : array();
	foreach ( array( 'ratingCount', 'reviewCount' ) as $count_key ) {
		if ( array_key_exists( $count_key, $rating ) && 0 === (int) $rating[ $count_key ] ) {
			unset( $node['aggregateRating'] );
			salt_log( sprintf( 'schema: stripped aggregateRating with zero %s from %s "%s" (SEO02 omit-when-zero).', $count_key, $type, $id ) );
			return $node;
		}
	}

	return $node;
}

/**
 * Merge a later node into an earlier node sharing the same @id.
 *
 * Shallow merge, first node wins per key (missing keys added only). The one
 * exception is the SEO03 FAQPage invariant: mainEntity arrays CONCATENATE in
 * queue order so all questions survive in the single FAQPage node.
 *
 * @param array<string,mixed> $existing First-seen node (wins per key).
 * @param array<string,mixed> $incoming Later node with the same @id.
 * @return array<string,mixed>
 */
function salt_schema_merge_node( array $existing, array $incoming ): array {
	if ( 'FAQPage' === ( $existing['@type'] ?? '' ) && 'FAQPage' === ( $incoming['@type'] ?? '' ) ) {
		$existing['mainEntity'] = array_merge(
			array_values( (array) ( $existing['mainEntity'] ?? array() ) ),
			array_values( (array) ( $incoming['mainEntity'] ?? array() ) )
		);
	}

	foreach ( $incoming as $key => $value ) {
		if ( ! array_key_exists( $key, $existing ) ) {
			$existing[ $key ] = $value;
		}
	}

	return $existing;
}

/**
 * Assemble the full JSON-LD document for a page (PURE — no WP calls).
 *
 * @param array{
 *     home_url?: string,
 *     page_url?: string,
 *     page_title?: string,
 *     site_name?: string,
 *     org?: array{name?:string,legal_name?:string,logo?:string,same_as?:string[],phone?:string,email?:string,address?:string,type?:string},
 *     breadcrumbs?: array<int,array{name?:string,url?:string}>,
 *     is_archive?: bool
 * } $context Page context (all optional, fixture-driven).
 * @return array{'@context':string,'@graph':array<int,array<string,mixed>>}
 */
function salt_schema_graph( array $context ): array {
	$home_url    = (string) ( $context['home_url'] ?? '' );
	$page_url    = (string) ( $context['page_url'] ?? '' );
	$page_title  = (string) ( $context['page_title'] ?? '' );
	$site_name   = (string) ( $context['site_name'] ?? '' );
	$org         = is_array( $context['org'] ?? null ) ? $context['org'] : array();
	$breadcrumbs = is_array( $context['breadcrumbs'] ?? null ) ? $context['breadcrumbs'] : array();
	$is_archive  = (bool) ( $context['is_archive'] ?? false );

	$org_id        = $home_url . '#organization';
	$website_id    = $home_url . '#website';
	$webpage_id    = $page_url . '#webpage';
	$breadcrumb_id = $page_url . '#breadcrumb';

	$org_name = is_string( $org['name'] ?? null ) ? trim( $org['name'] ) : '';
	$has_org  = '' !== $org_name;

	$graph    = array();
	$index_of = array();

	$append = static function ( array $node ) use ( &$graph, &$index_of ): void {
		$index_of[ (string) ( $node['@id'] ?? '' ) ] = count( $graph );
		$graph[]                                     = $node;
	};

	// SEO01: any queued office node (LocalBusiness, or a flagged subtype)
	// pins the brand to Organization — never a brand-level LocalBusiness.
	$has_office = false;
	foreach ( salt_schema_queue() as $queued ) {
		if ( 'LocalBusiness' === (string) $queued['@type'] || ! empty( $queued['salt_office'] ) ) {
			$has_office = true;
			break;
		}
	}

	$brand_type = 'Organization';
	if ( 'local_business' === (string) ( $org['type'] ?? '' ) ) {
		if ( $has_office ) {
			salt_log( 'schema: org_type "local_business" ignored — office nodes are queued, brand stays Organization (SEO01).' );
		} else {
			$brand_type = 'LocalBusiness';
		}
	}

	// 1. Organization ({home}#organization) — omitted when the org name is empty.
	if ( $has_org ) {
		$node = array(
			'@type' => $brand_type,
			'@id'   => $org_id,
			'name'  => $org_name,
		);
		if ( '' !== $home_url ) {
			$node['url'] = $home_url;
		}
		if ( is_string( $org['legal_name'] ?? null ) && '' !== $org['legal_name'] ) {
			$node['legalName'] = $org['legal_name'];
		}
		if ( is_string( $org['logo'] ?? null ) && '' !== $org['logo'] ) {
			$node['logo'] = $org['logo'];
		}
		$same_as = array_values(
			array_unique(
				array_filter(
					(array) ( $org['same_as'] ?? array() ),
					static fn( $url ): bool => is_string( $url ) && '' !== $url
				)
			)
		);
		if ( array() !== $same_as ) {
			$node['sameAs'] = $same_as;
		}
		if ( is_string( $org['phone'] ?? null ) && '' !== $org['phone'] ) {
			$node['telephone'] = $org['phone'];
		}
		if ( is_string( $org['email'] ?? null ) && '' !== $org['email'] ) {
			$node['email'] = $org['email'];
		}
		$org_address = is_string( $org['address'] ?? null ) ? trim( $org['address'] ) : '';
		if ( '' !== $org_address ) {
			// schema.org accepts Text for address — the free-text option is
			// passed through verbatim, never decomposed into a PostalAddress.
			$node['address'] = $org_address;
		}
		$append( $node );
	}

	// 2. WebSite ({home}#website) with publisher @id-ref (SEO05).
	if ( '' !== $home_url ) {
		$website = array(
			'@type' => 'WebSite',
			'@id'   => $website_id,
			'url'   => $home_url,
			'name'  => '' !== $site_name ? $site_name : $org_name,
		);
		if ( $has_org ) {
			$website['publisher'] = array( '@id' => $org_id );
		}
		$append( $website );
	}

	// Breadcrumb ListItems first, so the WebPage only references a list that renders.
	$breadcrumb_items = array();
	foreach ( $breadcrumbs as $crumb ) {
		$crumb_name = is_array( $crumb ) ? (string) ( $crumb['name'] ?? '' ) : '';
		if ( '' === $crumb_name ) {
			continue;
		}
		$item = array(
			'@type'    => 'ListItem',
			'position' => count( $breadcrumb_items ) + 1,
			'name'     => $crumb_name,
		);

		$crumb_url = (string) ( $crumb['url'] ?? '' );
		if ( '' !== $crumb_url ) {
			$item['item'] = $crumb_url;
		}
		$breadcrumb_items[] = $item;
	}

	// 3. WebPage/CollectionPage ({page}#webpage) with isPartOf/breadcrumb @id-refs.
	if ( '' !== $page_url ) {
		$webpage = array(
			'@type' => $is_archive ? 'CollectionPage' : 'WebPage',
			'@id'   => $webpage_id,
			'url'   => $page_url,
		);
		if ( '' !== $page_title ) {
			$webpage['name'] = $page_title;
		}
		if ( '' !== $home_url ) {
			$webpage['isPartOf'] = array( '@id' => $website_id );
		}
		if ( array() !== $breadcrumb_items ) {
			$webpage['breadcrumb'] = array( '@id' => $breadcrumb_id );
		}
		$append( $webpage );

		// 4. BreadcrumbList ({page}#breadcrumb).
		if ( array() !== $breadcrumb_items ) {
			$append(
				array(
					'@type'           => 'BreadcrumbList',
					'@id'             => $breadcrumb_id,
					'itemListElement' => $breadcrumb_items,
				)
			);
		}
	}

	// 5. Queued nodes, in insertion order.
	$counters = array();
	foreach ( salt_schema_queue() as $node ) {
		$type      = (string) $node['@type'];
		$is_office = 'LocalBusiness' === $type || ! empty( $node['salt_office'] );
		unset( $node['salt_office'] );

		if ( 'FAQPage' === $type ) {
			// SEO03 invariant: every FAQPage funnels into the ONE {page}#faq node.
			$node['@id'] = $page_url . '#faq';
		} elseif ( ! is_string( $node['@id'] ?? null ) || '' === $node['@id'] ) {
			$type_key              = strtolower( $type );
			$counters[ $type_key ] = ( $counters[ $type_key ] ?? 0 ) + 1;
			$node['@id']           = $page_url . '#' . $type_key . '-' . $counters[ $type_key ];
		}

		// SEO01: offices hang off the brand Organization, never replace it.
		if ( $is_office ) {
			$node['parentOrganization'] = array( '@id' => $org_id );
		}

		$node = salt_schema_police_rating( $node, $is_office, $org_id );

		$id = (string) $node['@id'];
		if ( isset( $index_of[ $id ] ) ) {
			// SEO02: re-police the merge RESULT — the shallow merge adds
			// missing keys, so a queued node sharing the brand @id could
			// otherwise smuggle aggregateRating/review into the org node.
			$graph[ $index_of[ $id ] ] = salt_schema_police_rating(
				salt_schema_merge_node( $graph[ $index_of[ $id ] ], $node ),
				$is_office,
				$org_id
			);
			salt_log( sprintf( 'schema: duplicate @id "%s" merged — first node wins per key.', $id ) );
			continue;
		}

		$append( $node );
	}

	return array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);
}

// ---------------------------------------------------------------------------
// WP context + output (thin layer over the pure builder).
// ---------------------------------------------------------------------------

/**
 * Read an org option field as a plain string (ACF image arrays resolve to url).
 *
 * @param string $selector Option field selector.
 * @return string
 */
function salt_schema_option_string( string $selector ): string {
	$value = salt_option( $selector );
	if ( is_array( $value ) ) {
		$value = $value['url'] ?? '';
	}
	return is_string( $value ) ? $value : '';
}

/**
 * Build the real page context from WP (options via get_field — spec §13).
 *
 * Filterable via 'salt_schema_context' (site/child overrides register at
 * priority 20+ per the conventions contract) — e.g. to supply breadcrumbs.
 *
 * @return array<string,mixed> Context for salt_schema_graph().
 */
function salt_schema_wp_context(): array {
	$org_name = salt_schema_option_string( 'org_name' );
	if ( '' === $org_name ) {
		$org_name = (string) get_bloginfo( 'name' );
	}

	if ( is_singular() ) {
		$page_url = (string) get_permalink();
	} else {
		$request  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '/';
		$page_url = (string) home_url( $request );
	}

	$same_as = array_values(
		array_filter(
			(array) ( salt_option( 'org_same_as' ) ?? array() ),
			static fn( $url ): bool => is_string( $url ) && '' !== $url
		)
	);

	// The review profile is a public brand profile — it joins sameAs (the
	// graph builder dedupes). Only honoured while a provider is selected;
	// the URL field is conditionally hidden but its value persists.
	$reviews_provider = salt_schema_option_string( 'reviews_provider' );
	$reviews_url      = salt_schema_option_string( 'reviews_profile_url' );
	if ( '' !== $reviews_provider && '' !== $reviews_url ) {
		$same_as[] = $reviews_url;
	}

	$org_type = salt_schema_option_string( 'org_type' );

	$context = array(
		'home_url'    => (string) home_url( '/' ),
		'page_url'    => $page_url,
		'page_title'  => (string) wp_get_document_title(),
		'site_name'   => (string) get_bloginfo( 'name' ),
		'org'         => array(
			'name'       => $org_name,
			'legal_name' => salt_schema_option_string( 'org_legal_name' ),
			'logo'       => salt_schema_option_string( 'org_logo' ),
			'same_as'    => $same_as,
			'phone'      => salt_schema_option_string( 'company_phone' ),
			'email'      => salt_schema_option_string( 'company_email' ),
			'address'    => trim( salt_schema_option_string( 'company_address' ) ),
			'type'       => 'local_business' === $org_type ? 'local_business' : 'organization',
		),
		'breadcrumbs' => array(),
		'is_archive'  => (bool) is_archive(),
	);

	return (array) apply_filters( 'salt_schema_context', $context );
}

/**
 * Echo the page's single JSON-LD <script> (hooked on wp_footer).
 *
 * Skips output entirely when the graph would carry no Organization and no
 * queued nodes. Pass an explicit $context in tests; null builds the real
 * WP context.
 *
 * @param array<string,mixed>|null $context Page context; null derives it from WP.
 * @return void
 */
function salt_schema_render( ?array $context = null ): void {
	$context = $context ?? salt_schema_wp_context();

	$org      = is_array( $context['org'] ?? null ) ? $context['org'] : array();
	$has_org  = is_string( $org['name'] ?? null ) && '' !== trim( $org['name'] );
	$has_node = array() !== salt_schema_queue();

	if ( ! $has_org && ! $has_node ) {
		return;
	}

	$json = wp_json_encode( salt_schema_graph( $context ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( ! is_string( $json ) ) {
		salt_log( 'schema: wp_json_encode failed — graph not emitted.' );
		return;
	}

	// Defuse </script> break-out; "<\/" decodes to the identical "</" string.
	$json = str_replace( '</', '<\/', $json );

	echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON built by wp_json_encode with </ defused above; HTML-escaping would corrupt the JSON-LD payload.
}

/**
 * wp_footer hook callback.
 *
 * A dedicated parameterless wrapper because do_action( 'wp_footer' ) passes
 * an empty-string argument to every callback — binding that to
 * salt_schema_render()'s typed ?array $context would TypeError-fatal the
 * whole front end. A no-param wrapper simply ignores the stray arg.
 *
 * @return void
 */
function salt_schema_render_footer(): void {
	salt_schema_render();
}
add_action( 'wp_footer', 'salt_schema_render_footer' );
