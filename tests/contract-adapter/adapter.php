<?php
/**
 * The Salt contract's command adapter for Salt for WordPress (EP-72 M3,
 * ID-3626). DEV ONLY: nothing here ships.
 *
 * Protocol (salt-contract README, "The adapter protocol"): one process per
 * case. It reads one fixture case, the whole <case>.json, as UTF-8 JSON on
 * stdin and writes the theme's server-rendered HTML for it to stdout. It
 * renders without WordPress, through the same shims the unit and snapshot
 * suites use (tests/bootstrap.php):
 *
 *  - a section case: its `values` are seeded into the ACF shims in the
 *    contract's stored shape, the shape the emitted field group (acfSnapshot)
 *    hands back through get_sub_field(): every name as the contract writes
 *    it, a field left out at its default, an image as an attachment ID, a
 *    gallery as a list of them, a link's document and a collection query's
 *    items as post IDs. Nothing is renamed to the theme's hand-written field
 *    names, so what the theme fails to read is a finding for M4, not
 *    something hidden here. The row is then rendered through
 *    salt_render_section(): the theme's resolver and view for that section;
 *  - a chrome case (site-header, site-footer): the case's `site` and
 *    `state` are seeded into the theme's options, menus and chrome seams
 *    (salt_adapter_seed_chrome()), and the template part rendered;
 *  - a view or the page: these need WordPress's loop and document head, so
 *    they are rendered by the real-ACF harness's running WordPress when one
 *    is up (tests/real-acf/run.sh --conformance sets SALT_HARNESS_WP and
 *    SALT_HARNESS_URL): the case goes to tests/real-acf/php/view.php, which
 *    seeds it into the site, requests its path and writes main#main, or the
 *    whole document for the page; its output and exit status are this
 *    adapter's. Without the harness, the case fails with exit 4 and says so.
 *
 * Exit codes: 0 rendered; 1 the harness's site answered the wrong status;
 * 2 the input is not a case; 3 the theme does not ship the section (or has
 * no section for the case's source); 4 the adapter cannot render this kind
 * of case without the harness; 70 the render raised a PHP error, warning,
 * notice or deprecation, or threw. Diagnostics go to stderr, never stdout,
 * so no PHP diagnostic can pass as markup.
 *
 * Usage: php tests/contract-adapter/adapter.php < fixtures/hero/empty.json
 *
 * @package Salt\Tests\ContractAdapter
 */

declare(strict_types=1);

// Every diagnostic, at every level, is caught and fails the case (exit 70).
error_reporting( E_ALL ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting -- CLI tooling: every level is caught below.
ini_set( 'display_errors', 'stderr' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed -- CLI tooling: never let PHP print to stdout.

$GLOBALS['salt_adapter_diagnostics'] = array();
set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- The adapter's whole point: catch every diagnostic.
	static function ( int $level, string $message, string $file, int $line ): bool {
		$GLOBALS['salt_adapter_diagnostics'][] = sprintf( 'PHP diagnostic (level %d): %s in %s:%d', $level, $message, salt_adapter_path( $file ), $line );
		return true;
	}
);
register_shutdown_function(
	static function (): void {
		$last = error_get_last();
		if ( null !== $last && in_array( $last['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			fwrite( STDERR, sprintf( "PHP fatal: %s in %s:%d\n", $last['message'], salt_adapter_path( $last['file'] ), $last['line'] ) );
			exit( 70 );
		}
	}
);

define( 'SALT_ADAPTER_THEME', dirname( __DIR__, 2 ) );
define( 'SALT_ADAPTER_CONTRACT', SALT_ADAPTER_THEME . '/node_modules/@lightlysaltedhq/salt-contract' );

/**
 * A file's path from the theme root, so a report names no machine's directories.
 *
 * @param string $file Absolute path.
 * @return string
 */
function salt_adapter_path( string $file ): string {
	return str_starts_with( $file, SALT_ADAPTER_THEME . '/' ) ? substr( $file, strlen( SALT_ADAPTER_THEME ) + 1 ) : $file;
}

/**
 * Stop: write a message to stderr and exit.
 *
 * @param int    $code    Exit code.
 * @param string $message Message.
 * @return never
 */
function salt_adapter_stop( int $code, string $message ) {
	fwrite( STDERR, $message . "\n" );
	exit( $code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- An exit status, not output.
}

/**
 * The contract's sections and the theme's equivalent for each: the theme's
 * section slug (template-parts/sections/<slug>.php), or, for a section the
 * theme draws as one of several former sections, a map from the case's
 * `values.source` to that slug. From contract/sections.json, each
 * section's former WordPress names (`formerly`); the theme's layouts have
 * since taken the contract's ids where one exists (cta is call-to-action;
 * collection-showcase and carousel since unit 8).
 *
 * @return array<string,string|array<string,string>|null>
 */
function salt_adapter_sections(): array {
	return array(
		'hero'                => 'hero',
		'rich-text'           => 'rich-text',
		'call-to-action'      => 'call-to-action',
		'media-text'          => 'media-text',
		'features'            => 'features',
		'stats'               => 'stats',
		'logos'               => 'logos',
		'gallery'             => 'gallery',
		'process'             => 'process',
		'faq'                 => 'faq',
		'tabs'                => 'tabs',
		'collection-showcase' => 'collection-showcase',
		'carousel'            => 'carousel',
		'listing'             => 'listing',
		'contact'             => 'contact',
		'locations'           => 'locations',
		'pricing'             => null,
	);
}

/**
 * Each collection the fixtures name, as the post type the theme stores it in.
 *
 * @return array<string,string>
 */
function salt_adapter_post_types(): array {
	return array(
		'services'     => 'service',
		'case-studies' => 'work',
		'testimonials' => 'testimonial',
		'posts'        => 'post',
		'faqs'         => 'faq',
		'locations'    => 'location',
	);
}

/**
 * Read a JSON file from the pinned contract package.
 *
 * @param string $path Path inside the package.
 * @return array<string,mixed>
 */
function salt_adapter_contract_json( string $path ): array {
	$file = SALT_ADAPTER_CONTRACT . '/' . $path;
	if ( ! is_readable( $file ) ) {
		salt_adapter_stop( 2, sprintf( 'salt adapter: %s is missing; run npm ci at the theme root.', $file ) );
	}
	return (array) json_decode( (string) file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
}

// ── The platform: the theme's own test bootstrap and shims ─────────────────────────────────────

/**
 * Load the theme and the shims the snapshot suites render sections with.
 *
 * @return void
 */
function salt_adapter_boot(): void {
	require SALT_ADAPTER_THEME . '/tests/bootstrap.php';
	// The contract renders same-site links relative to the site root.
	$GLOBALS['salt_test_schema']['home_url'] = '';
	foreach ( array( 'acf', 'media', 'blog', 'nav', 'service', 'work', 'team', 'testimonial', 'faq', 'gallery', 'location', 'http', 'terms' ) as $shim ) {
		require_once SALT_ADAPTER_THEME . '/tests/shims/' . $shim . '.php';
	}
	foreach ( array(
		'/inc/class-salt-nav-walker.php',
		'/inc/services/chrome.php',
		'/inc/services/svg.php',
		'/inc/services/display-preferences.php',
		'/inc/cpt/service.php',
		'/inc/cpt/work.php',
		'/inc/services/post-author.php',
		'/inc/cpt/testimonial.php',
		'/inc/cpt/faq.php',
		'/inc/cpt/location.php',
		'/inc/services/maps.php',
	) as $module ) {
		if ( file_exists( SALT_ADAPTER_THEME . $module ) ) {
			require_once SALT_ADAPTER_THEME . $module;
		}
	}
	salt_test_reset();
	salt_test_attachments_reset();
	foreach ( array( 'salt_render_state_reset', 'salt_schema_reset', 'salt_anchor_registry_reset', 'salt_overrides_reset' ) as $reset ) {
		if ( function_exists( $reset ) ) {
			$reset();
		}
	}
}

// ── Seeding the case ──────────────────────────────────────────────────────────────────────────

/**
 * WordPress core's default sub-size widths that land in a srcset (medium,
 * medium_large, large, 1536x1536, 2048x2048; thumbnail is cropped, so core
 * leaves it out). The theme registers no sizes of its own, so these are what
 * wp_calculate_image_srcset() would list for an upload; the case's media
 * record only says where each width's file lives (its {width} template), as
 * an upload URL filter would on a real site.
 *
 * @return int[]
 */
function salt_adapter_core_widths(): array {
	return array( 300, 768, 1024, 1536, 2048 );
}

/**
 * Seed the case's media into the attachment shim; returns media id => attachment ID.
 *
 * @param array<string,mixed> $media The case's `media`.
 * @return array<string,int>
 */
function salt_adapter_seed_media( array $media ): array {
	$ids  = array();
	$next = 101;
	foreach ( $media as $name => $record ) {
		$width     = (int) ( $record['width'] ?? 0 );
		$template  = (string) ( $record['url'] ?? '' );
		$url       = static fn( int $w ): string => str_replace( '{width}', (string) $w, $template );
		$candidate = array_filter( salt_adapter_core_widths(), static fn( int $w ): bool => $w < $width );
		$srcset    = array_map( static fn( int $w ): string => $url( $w ) . ' ' . $w . 'w', array_merge( $candidate, array( $width ) ) );

		$height = (int) ( $record['height'] ?? 0 );
		// The contract's candidate widths WordPress makes for the upload (the
		// theme's salt-<width> sizes), for the sections drawn to the contract.
		$sizes = array();
		foreach ( salt_image_candidates() as $w ) {
			if ( $w < $width ) {
				$sizes[ 'salt-' . $w ] = array(
					'width'  => $w,
					'height' => (int) round( $height * $w / max( 1, $width ) ),
					'url'    => $url( $w ),
				);
			}
		}

		$GLOBALS['salt_test_attachments'][ $next ] = array(
			'src'     => $url( $width ),
			'width'   => $width,
			'height'  => $height,
			'srcset'  => implode( ', ', $srcset ),
			'sizes'   => $sizes,
			'alt'     => (string) ( $record['alt'] ?? '' ),
			'caption' => (string) ( $record['caption'] ?? '' ),
		);
		$ids[ (string) $name ]                     = $next++;
	}
	return $ids;
}

/**
 * Store each media record's focal point (focalPoint { x, y }) as its
 * attachment's _salt_focal_point meta, which salt_image_focal_point() reads
 * (EP-72 M4 U3).
 *
 * @param array<string,mixed> $media The case's `media`.
 * @param array<string,int>   $ids   Media id => attachment ID.
 * @return void
 */
function salt_adapter_seed_focal_points( array $media, array $ids ): void {
	foreach ( $media as $name => $record ) {
		$focal = $record['focalPoint'] ?? null;
		if ( is_array( $focal ) && isset( $ids[ (string) $name ] ) ) {
			$GLOBALS['salt_test_meta'][ $ids[ (string) $name ] ]['_salt_focal_point'] = array(
				'x' => $focal['x'] ?? null,
				'y' => $focal['y'] ?? null,
			);
		}
	}
}

/**
 * Seed the case's documents as pages whose permalink is the case's href.
 *
 * @param array<string,mixed> $documents The case's `documents`.
 * @return array<string,int> Document key => post ID.
 */
function salt_adapter_seed_documents( array $documents ): array {
	$ids  = array();
	$next = 501;
	foreach ( $documents as $key => $document ) {
		$GLOBALS['salt_test_posts'][]             = new WP_Post(
			array(
				'ID'         => $next,
				'post_type'  => 'page',
				'post_title' => (string) ( $document['title'] ?? '' ),
			)
		);
		$GLOBALS['salt_test_permalinks'][ $next ] = (string) ( $document['href'] ?? '' );
		$ids[ (string) $key ]                     = $next++;
	}
	return $ids;
}

/**
 * Where WordPress keeps each collection field it stores natively, per
 * contract collection: the generated groups' `salt.native`
 * (inc/fields/generated/collections.json), so the adapter stores an item
 * exactly where the theme's readers look for it.
 *
 * @return array<string,array<string,string>> Collection => field => place.
 */
function salt_adapter_native_places(): array {
	$places = array();
	foreach ( salt_generated_collections_decode( (string) file_get_contents( SALT_ADAPTER_THEME . '/inc/fields/generated/collections.json' ) ) ?? array() as $group ) {
		$places[ (string) $group['salt']['collection'] ] = (array) ( $group['salt']['native'] ?? array() );
	}
	return $places;
}

/**
 * Seed the case's collections as posts of the theme's post types, in the
 * order given (menu_order and post_date both keep it). A field WordPress
 * keeps natively goes to its place (the title, the body, the excerpt, the
 * featured image); the fixtures' resolved card keys keep their meaning
 * (`text` is the body and the excerpt, `image` the featured image); every
 * other key is an ACF field on the post under the contract's name.
 *
 * @param array<string,mixed> $collections The case's `collections`.
 * @param array<string,int>   $media       Media id => attachment ID.
 * @return array<string,array<string,int>> Collection => item id => post ID.
 */
function salt_adapter_seed_collections( array $collections, array $media ): array {
	$ids    = array();
	$next   = 1001;
	$types  = salt_adapter_post_types();
	$places = salt_adapter_native_places();
	$terms  = array();
	foreach ( $collections as $collection => $items ) {
		$native = $places[ (string) $collection ] ?? array();
		$parts  = salt_adapter_card_parts( (string) $collection );
		$tax    = function_exists( 'salt_collection_sources' ) ? ( salt_collection_sources()[ (string) $collection ]['taxonomy'] ?? null ) : null;
		foreach ( array_values( (array) $items ) as $order => $item ) {
			if ( 'team' === $collection ) {
				$GLOBALS['salt_test_users'][]                   = new WP_User( $next, (string) ( $item['name'] ?? $item['title'] ) );
				$GLOBALS['salt_test_fields'][ 'user_' . $next ] = array(
					'showOnWebsite' => (int) ( $item['showOnWebsite'] ?? true ),
					'slug'          => $item['slug'] ?? basename( $item['href'] ?? $item['id'] ),
					'role'          => $item['role'] ?? $item['meta'] ?? '',
					'bio'           => $item['bio'] ?? $item['text'] ?? '',
					'publicEmail'   => $item['publicEmail'] ?? $item['email'] ?? '',
					'phone'         => $item['phone'] ?? '',
					'socials'       => $item['socials'] ?? array(),
					'photo'         => $media[ $item['photo'] ?? $item['image'] ?? '' ] ?? 0,
				);
				$ids['team'][ $item['id'] ]                     = $next++;
				continue;
			}
			$post = new WP_Post(
				array(
					'ID'           => $next,
					'post_type'    => $types[ $collection ] ?? (string) $collection,
					'post_title'   => (string) ( $item['title'] ?? '' ),
					'post_name'    => (string) ( $item['id'] ?? '' ),
					'post_content' => (string) ( $item['text'] ?? '' ),
					'post_excerpt' => (string) ( $item['text'] ?? '' ),
					'menu_order'   => $order,
					'post_date'    => isset( $item['date'] ) ? gmdate( 'Y-m-d H:i:s', (int) strtotime( (string) $item['date'] ) ) : sprintf( '2026-01-01 00:%02d:00', 59 - min( 59, $order ) ),
				)
			);
			if ( isset( $item['image'] ) && isset( $media[ (string) $item['image'] ] ) ) {
				$post->salt_thumbnail_id = $media[ (string) $item['image'] ];
			}
			foreach ( $item as $key => $value ) {
				if ( in_array( $key, array( 'id', 'title', 'href', 'image', 'date' ), true ) ) {
					continue;
				}
				// An item's topics, as the fixtures give them ({ title }), are terms in the source's taxonomy.
				if ( in_array( $key, array( 'tags', 'categories' ), true ) ) {
					foreach ( (array) $value as $topic ) {
						$title             = (string) ( $topic['title'] ?? '' );
						$terms[ $title ] ??= 9001 + count( $terms );
						$GLOBALS['salt_test_post_terms'][ $next ][ (string) $tax ][] = new WP_Term(
							array(
								'term_id'  => $terms[ $title ],
								'name'     => $title,
								'taxonomy' => (string) $tax,
							)
						);
					}
					continue;
				}
				// A resolved card key is stored where the collection's approved
				// display reads it from (the first field of a joined part).
				$key = $parts[ $key ] ?? $key;
				if ( 'text' === $key ) {
					continue; // Already the body and the excerpt.
				}
				switch ( $native[ $key ] ?? null ) {
					case 'title':
						$post->post_title = (string) $value;
						break;
					case 'body':
						$post->post_content = (string) $value;
						break;
					case 'excerpt':
						$post->post_excerpt = (string) $value;
						break;
					case 'featured-image':
						$post->salt_thumbnail_id = (int) ( $media[ (string) $value ] ?? 0 );
						break;
					case null:
						$GLOBALS['salt_test_fields'][ (string) $next ][ $key ] = $value;
						break;
					default:
						break; // Topics and the date are not seeded from a collection item.
				}
			}
			$GLOBALS['salt_test_posts'][] = $post;
			if ( isset( $item['href'] ) ) {
				$GLOBALS['salt_test_permalinks'][ $next ] = (string) $item['href'];
			}
			$ids[ (string) $collection ][ (string) ( $item['id'] ?? '' ) ] = $next++;
		}
	}
	return $ids;
}

/**
 * A collection's card display, inverted: each resolved card key to the
 * field it is read from (contract/collections/<collection>.json `display`,
 * `as: card` only; the first field of a joined part).
 *
 * @param string $collection Contract collection id.
 * @return array<string,string>
 */
function salt_adapter_card_parts( string $collection ): array {
	$file = SALT_ADAPTER_CONTRACT . '/contract/collections/' . $collection . '.json';
	if ( ! is_readable( $file ) ) {
		return array();
	}
	$display = (array) ( salt_adapter_contract_json( 'contract/collections/' . $collection . '.json' )['display'] ?? array() );
	if ( 'card' !== ( $display['as'] ?? null ) ) {
		return array();
	}
	$parts = array();
	foreach ( (array) ( $display['parts'] ?? array() ) as $key => $part ) {
		$from = $part['from'] ?? null;
		$from = is_array( $from ) ? ( $from[0] ?? null ) : $from;
		if ( is_string( $from ) && $from !== $key ) {
			$parts[ (string) $key ] = $from;
		}
	}
	return $parts;
}

/**
 * Feed the theme's pending-ruling seams the case's site data: the
 * collection's index page (site.collectionIndexes), the labels
 * (site.labels) and the arrow (site.arrow), so conformance is proved
 * against the contract whatever the founder rules for the live sources.
 *
 * @param array<string,mixed> $site The case's `site`.
 * @return void
 */
function salt_adapter_seed_site( array $site ): void {
	add_filter( 'salt_collection_index_url', static fn( $url, string $source ) => $site['collectionIndexes'][ $source ] ?? null, 10, 2 );
	add_filter( 'salt_contract_labels', static fn() => (array) ( $site['labels'] ?? array() ) );
	add_filter( 'salt_contract_arrow', static fn() => $site['arrow'] ?? null );
}

/**
 * A section's contract fields, its shared settings included as the `settings` group.
 *
 * @param string $section Contract section id.
 * @return array<int,array<string,mixed>>
 */
function salt_adapter_fields( string $section ): array {
	$definition = salt_adapter_contract_json( 'contract/fields/' . $section . '.json' );
	$fields     = (array) ( $definition['fields'] ?? array() );
	if ( isset( $definition['shared'] ) ) {
		$fields[] = array(
			'name'   => 'settings',
			'type'   => 'group',
			'fields' => salt_adapter_settings_fields( (array) ( $definition['shared']['defaults'] ?? array() ) ),
		);
	}
	return $fields;
}

/**
 * The shared section settings' fields with a section's own defaults
 * (its field definition's shared.defaults, such as call-to-action's
 * brand-tint tone), as the theme's emitted field group carries them
 * (EP-72 M4 U3).
 *
 * @param array<string,mixed> $defaults Setting name => the section's default.
 * @return array<int,array<string,mixed>>
 */
function salt_adapter_settings_fields( array $defaults ): array {
	$fields = (array) salt_adapter_contract_json( 'contract/fields/_section-settings.json' )['fields'];
	foreach ( $fields as $i => $field ) {
		if ( array_key_exists( (string) $field['name'], $defaults ) ) {
			$fields[ $i ]['default'] = $defaults[ $field['name'] ];
		}
	}
	return $fields;
}

/**
 * Stored values as ACF hands them back for the emitted field shape: each
 * field the contract defines, at its default when the case leaves it out,
 * with media, documents and collection items as IDs.
 *
 * @param array<string,mixed>            $values Stored values.
 * @param array<int,array<string,mixed>> $fields Contract fields.
 * @param array<string,array<mixed>>     $ids    media, documents and items ID maps.
 * @param string                         $source The case's collection source, for its query items.
 * @return array<string,mixed>
 */
function salt_adapter_to_acf( array $values, array $fields, array $ids, string $source ): array {
	$out = array();
	foreach ( $fields as $field ) {
		$name = (string) $field['name'];
		if ( ! array_key_exists( $name, $values ) ) {
			if ( 'group' === $field['type'] ) {
				$out[ $name ] = salt_adapter_to_acf( array(), (array) $field['fields'], $ids, $source );
			} elseif ( array_key_exists( 'default', $field ) ) {
				$out[ $name ] = $field['default'];
			}
			continue;
		}
		$out[ $name ] = salt_adapter_value( $values[ $name ], $field, $ids, $source );
	}
	// A key the contract does not define is passed through as given.
	foreach ( $values as $name => $value ) {
		if ( ! array_key_exists( $name, $out ) ) {
			$out[ $name ] = $value;
		}
	}
	return $out;
}

/**
 * One stored value as ACF hands it back (see salt_adapter_to_acf()).
 *
 * @param mixed                      $value  Stored value.
 * @param array<string,mixed>        $field  Contract field.
 * @param array<string,array<mixed>> $ids    ID maps.
 * @param string                     $source Collection source.
 * @return mixed
 */
function salt_adapter_value( $value, array $field, array $ids, string $source ) {
	$media = static fn( $v ): int => (int) ( $ids['media'][ (string) $v ] ?? 0 );
	switch ( $field['type'] ) {
		case 'image':
			return ! empty( $field['many'] ) ? array_map( $media, (array) $value ) : $media( $value );
		case 'link':
			if ( is_array( $value ) && isset( $value['document'] ) ) {
				$value['document'] = (int) ( $ids['documents'][ (string) $value['document'] ] ?? 0 );
			}
			return $value;
		case 'collection-query':
			if ( is_array( $value ) && isset( $value['items'] ) ) {
				$value['items'] = array_map( static fn( $v ): int => (int) ( $ids['items'][ $source ][ (string) $v ] ?? 0 ), (array) $value['items'] );
			}
			return $value;
		case 'group':
			return salt_adapter_to_acf( (array) $value, (array) $field['fields'], $ids, $source );
		case 'list':
			return array_map( static fn( $row ) => salt_adapter_to_acf( (array) $row, (array) ( $field['fields'] ?? array() ), $ids, $source ), (array) $value );
		default:
			return $value;
	}
}

/**
 * The locations section's site data (EP-72 M4 U4, test seams): the site's
 * timezone (site.timezone), the maps options (site.maps: enabled, tile)
 * and the case's moment for the open-now status (context.now, through the
 * salt_location_now filter).
 *
 * @param array<string,mixed> $site    The case's `site`.
 * @param array<string,mixed> $context The case's `context`.
 * @return void
 */
function salt_adapter_seed_locations_site( array $site, array $context ): void {
	if ( isset( $site['timezone'] ) ) {
		$GLOBALS['salt_test_options']['timezone_string'] = (string) $site['timezone'];
	}
	if ( isset( $site['maps'] ) ) {
		$GLOBALS['salt_test_fields']['option']['maps_enabled']  = ! empty( $site['maps']['enabled'] );
		$GLOBALS['salt_test_fields']['option']['maps_tile_url'] = $site['maps']['tile'] ?? null;
	}
	if ( isset( $context['now'] ) ) {
		$now = new DateTimeImmutable( (string) $context['now'] );
		add_filter( 'salt_location_now', static fn() => $now );
	}
}

/**
 * The case's locations as the theme stores them (EP-72 M4 U4, test seams):
 * a fixture's address is its resolved lines, stored here as the address
 * group's parts in order (street, locality, region, postalCode, country),
 * which the resolver joins back into the same lines; its directionsUrl
 * stands in for the theme's own OpenStreetMap link (salt_location_directions_url).
 *
 * @param array<string,mixed>      $collections The case's `collections`.
 * @param array<string,array<mixed>> $ids       ID maps.
 * @return void
 */
function salt_adapter_seed_locations( array $collections, array $ids ): void {
	$urls = array();
	foreach ( (array) ( $collections['locations'] ?? array() ) as $item ) {
		$post_id = $ids['items']['locations'][ (string) ( $item['id'] ?? '' ) ] ?? null;
		if ( null === $post_id ) {
			continue;
		}
		if ( isset( $item['address'] ) && array_is_list( (array) $item['address'] ) ) {
			$GLOBALS['salt_test_fields'][ (string) $post_id ]['address'] = array_combine(
				array_slice( array( 'street', 'locality', 'region', 'postalCode', 'country' ), 0, count( (array) $item['address'] ) ),
				array_map( 'strval', array_slice( (array) $item['address'], 0, 5 ) )
			);
		}
		if ( isset( $item['directionsUrl'] ) ) {
			$urls[ $post_id ] = (string) $item['directionsUrl'];
		}
	}
	add_filter( 'salt_location_directions_url', static fn( $url, $post_id ) => $urls[ $post_id ] ?? $url, 10, 2 );
}

/**
 * The case's contact form, as the theme stores and answers it (EP-72 M4
 * U4): the organisation into the Company options, acceptsEnquiries as a
 * recipient set or not, site.contactForm into the form options (phone,
 * the enquiry-type routes, the privacy page, a CAPTCHA provider), the
 * route's path as the request's, and a route.form answered by a send
 * (invalid, sent) as the form state the self-posted page records, through
 * the 'salt_contact_form_states' seam. Its credentials are placeholders:
 * the normaliser masks the challenge question and both tokens.
 *
 * @param array<string,mixed> $input The case.
 * @return void
 */
function salt_adapter_seed_contact( array $input ): void {
	if ( 'contact' !== ( $input['section'] ?? null ) ) {
		return;
	}
	$site   = (array) ( $input['site'] ?? array() );
	$option = static function ( string $name, $value ): void {
		$GLOBALS['salt_test_fields']['option'][ $name ] = $value;
	};

	$organisation = (array) ( $site['organisation'] ?? array() );
	$option( 'org_name', $organisation['name'] ?? null );
	$option( 'company_phone', $organisation['phone'] ?? null );
	$option( 'company_email', $organisation['email'] ?? null );
	$option( 'company_address', isset( $organisation['address'] ) ? implode( "\n", (array) $organisation['address'] ) : null );

	if ( empty( $site['acceptsEnquiries'] ) ) {
		return;
	}
	$option( 'contact_recipient', 'enquiries@example.test' );
	$form = (array) ( $site['contactForm'] ?? array() );
	$option( 'contact_form_phone', $form['phone'] ?? 'off' );
	$option(
		'contact_routes',
		array_map(
			static fn( array $type ): array => array(
				'enquiry_type' => (string) ( $type['label'] ?? '' ),
				'value'        => (string) ( $type['value'] ?? '' ),
				'recipient'    => '',
			),
			(array) ( $form['enquiryTypes'] ?? array() )
		)
	);
	if ( isset( $form['privacyPage'] ) ) {
		$GLOBALS['salt_test_posts'][]         = new WP_Post(
			array(
				'ID'        => 590,
				'post_type' => 'page',
			)
		);
		$GLOBALS['salt_test_permalinks'][590] = (string) $form['privacyPage'];
		$option( 'contact_privacy_page', 590 );
	}
	if ( ! empty( $form['captcha'] ) ) {
		$option( 'captcha_provider', 'turnstile' );
		$option( 'captcha_turnstile_site_key', 'site-key' );
	}

	$route = (array) ( $input['route'] ?? array() );
	if ( isset( $route['path'] ) ) {
		$_SERVER['REQUEST_URI'] = (string) $route['path'];
	}
	$answered = (array) ( $route['form'] ?? array() );
	if ( in_array( $answered['state'] ?? 'open', array( 'invalid', 'sent' ), true ) ) {
		$anchor = sanitize_title( (string) ( $input['values']['settings']['anchorId'] ?? 'contact' ) );
		$state  = array(
			'state'  => $answered['state'],
			'values' => (array) ( $answered['values'] ?? array() ),
			'errors' => (array) ( $answered['errors'] ?? array() ),
			'status' => $answered['status'] ?? null,
		);
		if ( ! empty( $answered['challenge'] ) ) {
			$state['challenge'] = array(
				'question' => 'What is 2 plus 3?',
				'token'    => 'challenge-token',
			);
			$state['formToken'] = 'form-token';
		}
		add_filter( 'salt_contact_form_states', static fn() => array( $anchor => $state ) );
	}
}

// ── Rendering ─────────────────────────────────────────────────────────────────────────────────

/**
 * The rest of the page plan a section case describes, seeded into the
 * render state (EP-72 M4 U1): with priorityMedia false, an earlier band on
 * the page has taken the priority image (section#priority-media), so this
 * one is refused it even when it has an image.
 *
 * @param array<string,mixed> $context The case's context.
 * @return void
 */
function salt_adapter_seed_section_plan( array $context ): void {
	if ( array_key_exists( 'priorityMedia', $context ) && ! $context['priorityMedia'] ) {
		salt_render_state( 'set', 'priority_media', true );
	}
}

/**
 * Render a section case through the theme's resolver and view.
 *
 * @param array<string,mixed> $input The case.
 * @param array<string,array> $ids   ID maps.
 * @return string
 */
function salt_adapter_render_section( array $input, array $ids ): string {
	$section = (string) $input['section'];
	$values  = (array) ( $input['values'] ?? array() );
	$context = (array) ( $input['context'] ?? array() );
	$map     = salt_adapter_sections();
	if ( ! array_key_exists( $section, $map ) ) {
		salt_adapter_stop( 2, sprintf( 'salt adapter: %s is not a section in the pinned contract.', $section ) );
	}
	$source = (string) ( $values['source'] ?? '' );
	$slug   = $map[ $section ];
	if ( is_array( $slug ) ) {
		$slug = $slug[ $source ] ?? $slug[''] ?? null;
	}
	if ( null === $slug ) {
		salt_adapter_stop( 3, sprintf( 'salt adapter: not shipped: Salt for WordPress has no section for %s%s.', $section, '' !== $source ? ' with source ' . $source : '' ) );
	}

	if ( 'listing' === $section ) {
		require_once SALT_ADAPTER_THEME . '/tests/shims/listing.php';
		$route                                    = $input['route'] ?? null;
		$GLOBALS['salt_test_schema']['is_home']   = null !== $route;
		$GLOBALS['salt_test_query_vars']['paged'] = $route['pagination']['current'] ?? 1;
		$GLOBALS['salt_test_page_links']          = $route['pagination']['hrefs'] ?? array();
		$listing_ids                              = salt_adapter_seed_collections( array( 'posts' => $route['cards'] ?? array() ), $ids['media'] );
		$GLOBALS['wp_query']                      = (object) array(
			'posts'         => array_map( 'get_post', array_values( $listing_ids['posts'] ?? array() ) ),
			'max_num_pages' => $route['pagination']['total'] ?? 1,
		);
	}

	salt_test_seed_fc(
		array(
			array(
				'layout' => $slug,
				'fields' => salt_adapter_to_acf( $values, salt_adapter_fields( $section ), $ids, $source ),
			),
		)
	);
	the_row();

	// The page plan's context for this band (fixtures README, "The input"):
	// its position, whether it claims the h1, and the collapse with the band
	// above, which the theme draws from the previous band's tones.
	$index         = (int) ( $context['index'] ?? 0 );
	$heading_level = (int) ( $context['headingLevel'] ?? ( 0 === $index ? 1 : 2 ) );
	salt_render_state( 'set', 'section_index', $index );
	if ( ! empty( $context['headingRendered'] ) ) {
		salt_claim_h1( 'contract-adapter: a heading earlier on the page' );
	}
	// data-track counts the section on the page: the case's track gives the count before it.
	if ( 1 === preg_match( '/^(.+)-(\d+)$/', (string) ( $context['track'] ?? '' ), $track ) ) {
		salt_render_state( 'set', 'tracks', array( $track[1] => (int) $track[2] - 1 ) );
	}
	if ( ! empty( $context['collapseTop'] ) ) {
		$previous = salt_section_contract_args( $slug );
		salt_render_state( 'set', 'last_tone', $previous['tone'] );
		salt_render_state( 'set', 'last_tone_dark', $previous['tone_dark'] );
	}
	salt_adapter_seed_section_plan( $context );

	ob_start();
	salt_render_section(
		$slug,
		array(
			'index'      => $index,
			'is_primary' => 1 === $heading_level,
		)
	);
	return (string) ob_get_clean();
}

/**
 * A chrome case's menu as the menu items wp_nav_menu() hands the theme's
 * walker (the nav shim's salt_test_menu_items): each item's current state
 * is whether its href is the case's path, as WordPress marks the current
 * page's item.
 *
 * @param array<int,array<string,mixed>> $items  The case's items ({ label, href?, children? }).
 * @param string                         $path   The case's context.path.
 * @param int                            $parent_id The parent item's ID (0 for the top level).
 * @param int                            $next   The next item ID, by reference.
 * @return array<int,object>
 */
function salt_adapter_menu_items( array $items, string $path, int $parent_id = 0, int &$next = 1 ): array {
	$out = array();
	foreach ( $items as $item ) {
		$id    = $next++;
		$href  = isset( $item['href'] ) ? (string) $item['href'] : '';
		$out[] = (object) array(
			'ID'               => $id,
			'db_id'            => $id,
			'menu_item_parent' => $parent_id,
			'title'            => (string) ( $item['label'] ?? '' ),
			'url'              => $href,
			'current'          => '' !== $href && $href === $path,
			'target'           => '',
		);
		$out   = array_merge( $out, salt_adapter_menu_items( (array) ( $item['children'] ?? array() ), $path, $id, $next ) );
	}
	return $out;
}

/**
 * Seed a chrome case's site data into the theme's options and the chrome's
 * seams: the header's menu and the footer's columns (as menus), the
 * phone, the theme toggle's placement and known scheme, the footer's tone,
 * logo, profiles, copyright and display preferences, the consent panel,
 * and the case's state (the drawer or the consent panel open). Kept apart
 * from the section and page seeding (EP-72 M4 U5).
 *
 * @param array<string,mixed> $input The case.
 * @param array<string,array> $ids   ID maps.
 * @return void
 */
function salt_adapter_seed_chrome( array $input, array $ids ): void {
	$site   = (array) ( $input['site'] ?? array() );
	$header = (array) ( $site['header'] ?? array() );
	$footer = (array) ( $site['footer'] ?? array() );
	$state  = (array) ( $input['state'] ?? array() );
	$path   = (string) ( $input['context']['path'] ?? '/' );
	$option = static function ( string $name, $value ): void {
		$GLOBALS['salt_test_fields']['option'][ $name ] = $value;
	};

	$GLOBALS['salt_test_bloginfo'] = array( 'name' => (string) ( $site['name'] ?? '' ) );
	$option( 'org_name', $site['organisation'] ?? null );
	$option( 'logo_light', $ids['media'][ (string) ( $site['logo']['light'] ?? '' ) ] ?? null );
	$option( 'logo_dark', $ids['media'][ (string) ( $site['logo']['dark'] ?? '' ) ] ?? null );
	$option( 'logo_height', $site['logoHeight'] ?? null );
	$option( 'header_sticky', $header['sticky'] ?? false );
	if ( isset( $header['cta']['label'], $header['cta']['href'] ) ) {
		$option(
			'header_cta',
			array(
				'title' => $header['cta']['label'],
				'url'   => $header['cta']['href'],
			)
		);
	}
	$option( 'footer_text', $footer['text'] ?? null );
	$option( 'footer_show_social', ! empty( $footer['profiles'] ) );

	// Menus: the header's as given; the footer's columns as a menu the
	// theme reads back as columns (a titled column is a parent without an
	// address, an untitled one its links at the top level).
	$GLOBALS['salt_test_menu_items']['primary'] = salt_adapter_menu_items( (array) ( $header['menu'] ?? array() ), $path );
	$footer_menu                                = array();
	foreach ( (array) ( $footer['columns'] ?? array() ) as $column ) {
		if ( isset( $column['title'] ) && '' !== (string) $column['title'] ) {
			$footer_menu[] = array(
				'label'    => $column['title'],
				'children' => (array) ( $column['links'] ?? array() ),
			);
		} else {
			$footer_menu = array_merge( $footer_menu, (array) ( $column['links'] ?? array() ) );
		}
	}
	$GLOBALS['salt_test_menu_items']['footer'] = salt_adapter_menu_items( $footer_menu, $path );

	// Site Settings the editor sets (Header & Footer; Company & Schema's
	// phone and its header switch), seeded as options, not filters.
	$toggle = $site['themeToggle'] ?? null;
	$option( 'theme_toggle_placement', true === $toggle ? 'header' : ( is_array( $toggle ) ? ( $toggle['placed'] ?? '' ) : '' ) );
	$option( 'company_phone', $header['phone']['text'] ?? null );
	$option( 'header_show_phone', isset( $header['phone']['text'] ) );
	$option( 'footer_tone', $footer['tone'] ?? 'surface' );
	$option( 'footer_show_logo', ! empty( $footer['showLogo'] ) );
	// State only script or the request knows: still through the seams.
	add_filter( 'salt_theme_scheme', static fn() => $state['themeScheme'] ?? null );
	add_filter( 'salt_header_drawer_open', static fn() => ! empty( $state['drawerOpen'] ) );
	if ( isset( $footer['copyright'] ) ) {
		add_filter( 'salt_footer_copyright', static fn() => (string) $footer['copyright'] );
	}
	if ( isset( $footer['profiles'] ) ) {
		add_filter(
			'salt_footer_social_links',
			static fn() => array_map(
				static fn( array $profile ): array => array(
					'url'   => (string) $profile['url'],
					'icon'  => (string) ( $profile['icon'] ?? '' ),
					'label' => (string) $profile['name'],
				),
				(array) $footer['profiles']
			)
		);
	}

	// Consent (ruling C, SC-030): a case's site.consent says a WP Consent
	// API plugin is wired, and its categories' covers are the Salt
	// categories the site's integrations use. The theme groups them into
	// boxes itself (salt_consent_groups()); the case gives only the words.
	// No site.consent: no plugin, so nothing is drawn.
	add_filter( 'salt_consent_panel_open', static fn() => ! empty( $state['consentPanelOpen'] ) );
	if ( isset( $site['consent'] ) ) {
		require_once SALT_ADAPTER_THEME . '/tests/shims/consent-api.php';
		$GLOBALS['salt_test_wp_consent_type'] = 'optin';
		$boxes                                = (array) ( $site['consent']['categories'] ?? array() );
		$in_use                               = array_merge( array(), ...array_map( static fn( $box ): array => (array) ( $box['covers'] ?? array() ), array_values( $boxes ) ) );
		add_filter( 'salt_consent_categories_in_use', static fn() => $in_use );
		add_filter(
			'salt_consent_panel',
			static fn() => array(
				'labels'     => (array) ( $site['consent']['labels'] ?? array() ),
				'categories' => $boxes,
			)
		);
	}

	$prefs = $site['displayPreferences'] ?? null;
	if ( is_array( $prefs ) ) {
		$controls = ! empty( $prefs['scale'] ) ? array( 'font_size' ) : array();
		foreach ( (array) ( $prefs['toggles'] ?? array() ) as $toggle_name ) {
			$controls[] = 'theme' === $toggle_name ? 'dark_mode' : (string) $toggle_name;
		}
		$option( 'display_preferences_enabled', true );
		$option( 'display_controls', $controls );
		$option( 'dark_mode', in_array( 'dark_mode', $controls, true ) );
		$option( 'reduce_motion', 'reduce' === ( $prefs['motionDefault'] ?? null ) );
		add_filter( 'salt_display_preferences_labels', static fn() => (array) ( $prefs['labels'] ?? array() ) );
	}
}

/**
 * Render a chrome case: the case's site data seeded into the theme's
 * options and seams (salt_adapter_seed_chrome()), then the template part.
 *
 * @param array<string,mixed> $input The case.
 * @param array<string,array> $ids   ID maps.
 * @return string
 */
function salt_adapter_render_chrome( array $input, array $ids ): string {
	salt_adapter_seed_chrome( $input, $ids );

	$part = 'site-header' === $input['chrome'] ? 'template-parts/header/site-header.php' : 'template-parts/footer/site-footer.php';
	ob_start();
	load_template( locate_template( $part ), false, array() );
	return (string) ob_get_clean();
}

// ── Main ──────────────────────────────────────────────────────────────────────────────────────

try {
	$salt_adapter_input = json_decode( (string) stream_get_contents( STDIN ), true, 512, JSON_THROW_ON_ERROR );
} catch ( JsonException $e ) {
	salt_adapter_stop( 2, 'salt adapter: stdin is not JSON: ' . $e->getMessage() );
}
if ( ! is_array( $salt_adapter_input ) ) {
	salt_adapter_stop( 2, 'salt adapter: stdin is not a fixture case.' );
}

$salt_adapter_wp = (string) getenv( 'SALT_HARNESS_WP' );
if ( ( isset( $salt_adapter_input['view'] ) || isset( $salt_adapter_input['page'] ) ) && '' !== $salt_adapter_wp && '' !== (string) getenv( 'SALT_HARNESS_URL' ) && is_file( $salt_adapter_wp . '/wp-load.php' ) ) {
	// The harness's site renders it: the case on stdin, its stdout and stderr passed through.
	$salt_adapter_view = proc_open( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- CLI tooling: the harness's own script.
		array( PHP_BINARY, SALT_ADAPTER_THEME . '/tests/real-acf/php/view.php' ),
		array(
			0 => array( 'pipe', 'r' ),
			1 => STDOUT,
			2 => STDERR,
		),
		$salt_adapter_pipes
	);
	fwrite( $salt_adapter_pipes[0], (string) json_encode( $salt_adapter_input ) );
	fclose( $salt_adapter_pipes[0] );
	exit( proc_close( $salt_adapter_view ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- An exit status, not output.
}
if ( isset( $salt_adapter_input['view'] ) || isset( $salt_adapter_input['page'] ) ) {
	salt_adapter_stop(
		4,
		sprintf(
			'salt adapter: the %s %s is not rendered by this adapter yet: it needs WordPress\'s loop and document head, which the shims do not provide (M4).',
			isset( $salt_adapter_input['view'] ) ? 'view' : 'page',
			(string) ( $salt_adapter_input['view'] ?? $salt_adapter_input['page'] )
		)
	);
}
if ( ! isset( $salt_adapter_input['section'] ) && ! isset( $salt_adapter_input['chrome'] ) ) {
	salt_adapter_stop( 2, 'salt adapter: the case names no section, chrome, view or page.' );
}

try {
	salt_adapter_boot();
	salt_adapter_seed_site( (array) ( $salt_adapter_input['site'] ?? array() ) );
	$salt_adapter_ids = array(
		'media'     => salt_adapter_seed_media( (array) ( $salt_adapter_input['media'] ?? array() ) ),
		'documents' => salt_adapter_seed_documents( (array) ( $salt_adapter_input['documents'] ?? array() ) ),
	);
	salt_adapter_seed_focal_points( (array) ( $salt_adapter_input['media'] ?? array() ), $salt_adapter_ids['media'] );
	$salt_adapter_ids['items'] = salt_adapter_seed_collections( (array) ( $salt_adapter_input['collections'] ?? array() ), $salt_adapter_ids['media'] );
	salt_adapter_seed_locations_site( (array) ( $salt_adapter_input['site'] ?? array() ), (array) ( $salt_adapter_input['context'] ?? array() ) );
	salt_adapter_seed_locations( (array) ( $salt_adapter_input['collections'] ?? array() ), $salt_adapter_ids );
	salt_adapter_seed_contact( $salt_adapter_input );
	$salt_adapter_html = isset( $salt_adapter_input['section'] )
		? salt_adapter_render_section( $salt_adapter_input, $salt_adapter_ids )
		: salt_adapter_render_chrome( $salt_adapter_input, $salt_adapter_ids );
} catch ( Throwable $e ) {
	salt_adapter_stop( 70, sprintf( 'salt adapter: the render threw %s: %s in %s:%d', get_class( $e ), $e->getMessage(), salt_adapter_path( $e->getFile() ), $e->getLine() ) );
}

foreach ( $GLOBALS['salt_test_logs'] ?? array() as $salt_adapter_line ) {
	fwrite( STDERR, 'salt_log: ' . $salt_adapter_line . "\n" );
}
if ( array() !== $GLOBALS['salt_adapter_diagnostics'] ) {
	salt_adapter_stop( 70, implode( "\n", $GLOBALS['salt_adapter_diagnostics'] ) );
}

echo $salt_adapter_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The theme's own markup, escaped where it was drawn.
exit( 0 );
