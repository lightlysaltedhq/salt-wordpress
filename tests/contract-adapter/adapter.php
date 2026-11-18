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
 *  - a chrome case (site-header, site-footer): the case's `site` is seeded
 *    into the theme's options and bloginfo, and the template part rendered;
 *  - a view or the page: not rendered yet (they need WordPress's loop and
 *    document head); the case fails with exit 4 and says so.
 *
 * Exit codes: 0 rendered; 2 the input is not a case; 3 the theme does not
 * ship the section (or has no section for the case's source); 4 the adapter
 * cannot render this kind of case yet; 70 the render raised a PHP error,
 * warning, notice or deprecation, or threw. Diagnostics go to stderr, never
 * stdout, so no PHP diagnostic can pass as markup.
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
 * since taken the contract's ids where one exists (cta is call-to-action).
 * tabs and listing are owed: the theme has no section for them.
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
		'tabs'                => null,
		'collection-showcase' => array(
			'services'     => 'services',
			'case-studies' => 'work',
			'team'         => 'team',
			'testimonials' => 'testimonials',
			'posts'        => 'blog-teaser',
		),
		'carousel'            => array(
			'testimonials' => 'testimonials',
			''             => 'scroll-carousel',
		),
		'listing'             => null,
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
		'team'         => 'team_member',
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
	foreach ( array( 'acf', 'media', 'blog', 'nav', 'service', 'work', 'team', 'testimonial', 'faq', 'gallery', 'location', 'http' ) as $shim ) {
		require_once SALT_ADAPTER_THEME . '/tests/shims/' . $shim . '.php';
	}
	foreach ( array(
		'/inc/class-salt-nav-walker.php',
		'/inc/services/chrome.php',
		'/inc/services/svg.php',
		'/inc/services/display-preferences.php',
		'/inc/cpt/service.php',
		'/inc/cpt/work.php',
		'/inc/cpt/team-member.php',
		'/inc/cpt/testimonial.php',
		'/inc/cpt/faq.php',
		'/inc/cpt/location.php',
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

		$GLOBALS['salt_test_attachments'][ $next ] = array(
			'src'     => $url( $width ),
			'width'   => $width,
			'height'  => (int) ( $record['height'] ?? 0 ),
			'srcset'  => implode( ', ', $srcset ),
			'alt'     => (string) ( $record['alt'] ?? '' ),
			'caption' => (string) ( $record['caption'] ?? '' ),
		);
		$ids[ (string) $name ]                     = $next++;
	}
	return $ids;
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
 * Seed the case's collections as posts of the theme's post types, in the
 * order given (menu_order and post_date both keep it). title and text are
 * the post's title and content; every other key is an ACF field on the post
 * under the contract's name.
 *
 * @param array<string,mixed> $collections The case's `collections`.
 * @param array<string,int>   $media       Media id => attachment ID.
 * @return array<string,array<string,int>> Collection => item id => post ID.
 */
function salt_adapter_seed_collections( array $collections, array $media ): array {
	$ids   = array();
	$next  = 1001;
	$types = salt_adapter_post_types();
	foreach ( $collections as $collection => $items ) {
		foreach ( array_values( (array) $items ) as $order => $item ) {
			$title = (string) ( $item['title'] ?? $item['question'] ?? '' );
			$text  = (string) ( $item['text'] ?? $item['answer'] ?? '' );
			$post  = new WP_Post(
				array(
					'ID'           => $next,
					'post_type'    => $types[ $collection ] ?? (string) $collection,
					'post_title'   => $title,
					'post_name'    => (string) ( $item['id'] ?? '' ),
					'post_content' => $text,
					'post_excerpt' => $text,
					'menu_order'   => $order,
					'post_date'    => isset( $item['date'] ) ? gmdate( 'Y-m-d H:i:s', (int) strtotime( (string) $item['date'] ) ) : sprintf( '2026-01-01 00:%02d:00', 59 - min( 59, $order ) ),
				)
			);
			if ( isset( $item['image'] ) && isset( $media[ (string) $item['image'] ] ) ) {
				$post->salt_thumbnail_id = $media[ (string) $item['image'] ];
			}
			$GLOBALS['salt_test_posts'][] = $post;
			if ( isset( $item['href'] ) ) {
				$GLOBALS['salt_test_permalinks'][ $next ] = (string) $item['href'];
			}
			foreach ( $item as $key => $value ) {
				if ( ! in_array( $key, array( 'id', 'title', 'question', 'text', 'answer', 'href', 'image', 'date' ), true ) ) {
					$GLOBALS['salt_test_fields'][ (string) $next ][ $key ] = $value;
				}
			}
			$ids[ (string) $collection ][ (string) ( $item['id'] ?? '' ) ] = $next++;
		}
	}
	return $ids;
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
			'fields' => salt_adapter_contract_json( 'contract/fields/_section-settings.json' )['fields'],
		);
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

// ── Rendering ─────────────────────────────────────────────────────────────────────────────────

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
	// above, which the theme draws from the previous band's background.
	$index         = (int) ( $context['index'] ?? 0 );
	$heading_level = (int) ( $context['headingLevel'] ?? ( 0 === $index ? 1 : 2 ) );
	salt_render_state( 'set', 'section_index', $index );
	if ( ! empty( $context['headingRendered'] ) ) {
		salt_claim_h1( 'contract-adapter: a heading earlier on the page' );
	}
	if ( ! empty( $context['collapseTop'] ) ) {
		salt_render_state( 'set', 'last_background', salt_section_wrapper_args( $slug )['background'] );
	}

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
 * Render a chrome case: the case's site data seeded into the theme's
 * options, then the template part. The menus are not seeded: the theme
 * draws them through wp_nav_menu() and its walker, which the shims do not
 * run, so a case's menu is drawn by the fallback (nothing).
 *
 * @param array<string,mixed> $input The case.
 * @param array<string,array> $ids   ID maps.
 * @return string
 */
function salt_adapter_render_chrome( array $input, array $ids ): string {
	$site   = (array) ( $input['site'] ?? array() );
	$header = (array) ( $site['header'] ?? array() );
	$footer = (array) ( $site['footer'] ?? array() );
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
	$salt_adapter_ids          = array(
		'media'     => salt_adapter_seed_media( (array) ( $salt_adapter_input['media'] ?? array() ) ),
		'documents' => salt_adapter_seed_documents( (array) ( $salt_adapter_input['documents'] ?? array() ) ),
	);
	$salt_adapter_ids['items'] = salt_adapter_seed_collections( (array) ( $salt_adapter_input['collections'] ?? array() ), $salt_adapter_ids['media'] );
	$salt_adapter_html         = isset( $salt_adapter_input['section'] )
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
