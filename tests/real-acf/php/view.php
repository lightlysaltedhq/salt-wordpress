<?php
/**
 * One contract view or page case, rendered by the harness's running
 * WordPress. HARNESS ONLY: tests/contract-adapter/adapter.php hands a view
 * or page case here when run.sh --conformance has the site up, and relays
 * what this writes and its exit status.
 *
 * Usage: php view.php setup          (once, by run.sh, before the server starts)
 *        php view.php < <case>.json  (once per case)
 *
 * setup sets the routes the cases' paths take (posts under /journal/, topics
 * under /journal/topic/, public profiles under /team/), keeps uploads out
 * of dated folders, removes WordPress's sample content, adds the site's
 * menus (seed.php) and snapshots the database (db.php snapshot).
 *
 * A case, one at a time (a lock: the cases share one site): the database is
 * put back as setup left it (db.php restore) and the uploads emptied, so
 * every case starts from the same site and gets the same IDs in any order.
 * Then the case's data is seeded with the singles and contract phases'
 * helpers (seed.php):
 *
 *  - `media` become attachments with the record's size and alt text, and
 *    `documents` pages whose slug is the href's last segment;
 *  - `site`: name (blogname), timezone (timezone_string), organisation,
 *    logos, logo height, sticky header, header call to action and footer
 *    text into the theme's options, as the adapter's chrome cases do;
 *  - the page: a page with the document's title, at the case's path (the
 *    front page for /), its `document.sections` as rows of `sections`;
 *  - post, service, case-study: the document as singles.php seeds it, at
 *    its path; a post's body as its `layout`'s first row (prose) and its
 *    `document.sections` as the rows after it,
 *    its `adjacent` newer and older posts a day after and before it, and the
 *    case's `collections` as posts of their types;
 *  - archive: the topic as a category with its description, or the author
 *    as a published team member with the href's slug, the title as name, the
 *    strapline as role, the biography as bio and the photo as user metadata;
 *    `route.cards` as posts in
 *    it or crediting them;
 *  - search: each result as a document of its kind; not-found: nothing.
 *
 * It requests the case's `context.path` from the harness server (the
 * search view at WordPress's own search address, /?s=<query>), and writes
 * main#main, or the whole document for the page. Exit 0 rendered; 1 the
 * site answered another status than the view's (200, or 404 for
 * not-found); 4 a view it has no seeding for; 70 a PHP notice, warning, deprecation or error reached
 * debug.log while the case was seeded or rendered, each written to stderr.
 * The theme's salt_log lines are written to stderr and do not fail it.
 *
 * Env (run.sh): SALT_HARNESS_WP, SALT_HARNESS_URL (the site's address),
 * SALT_HARNESS_SERVER (where the server listens), SALT_HARNESS_DB, the
 * SALT_HARNESS_DB_* connection, SALT_HARNESS_LOG (debug.log) and
 * SALT_HARNESS_LOCK.
 *
 * @package Salt\Tests\RealAcf
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

/**
 * Run db.php on the harness database; stop the case when it fails.
 *
 * @param string $action snapshot or restore.
 * @return void
 */
function salt_view_db( string $action ): void {
	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/db.php' ) . ' ' . $action . ' ' . escapeshellarg( (string) getenv( 'SALT_HARNESS_DB' ) );
	exec( $command . ' 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- The harness's own database tool.
	if ( 0 !== $status ) {
		fwrite( STDERR, "salt harness: db.php {$action} failed: " . implode( "\n", $output ) . "\n" );
		exit( 1 );
	}
}

// ── setup ─────────────────────────────────────────────────────────────────────────────────────

if ( 'setup' === ( $argv[1] ?? '' ) ) {
	require __DIR__ . '/wp.php';
	require __DIR__ . '/seed.php';

	update_option( 'permalink_structure', '/journal/%postname%/' );
	update_option( 'category_base', 'journal/topic' );
	update_option( 'uploads_use_yearmonth_folders', 0 );
	foreach ( get_posts(
		array(
			'post_type'   => 'any',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	) as $salt_view_id ) {
		wp_delete_post( (int) $salt_view_id, true );
	}
	foreach ( get_comments( array( 'fields' => 'ids' ) ) as $salt_view_id ) {
		wp_delete_comment( (int) $salt_view_id, true );
	}
	salt_harness_seed_menus();
	// This process registered its rules from the old options; the next load builds them afresh.
	delete_option( 'rewrite_rules' );
	salt_view_db( 'snapshot' );
	salt_harness_result(
		'/journal/%postname%/' === get_option( 'permalink_structure' ),
		'the site is ready for the view and page cases',
		sprintf( 'posts at %s, topics at %s, profiles at %s; database snapshot taken', '/journal/<slug>/', '/journal/topic/<slug>/', '/team/<slug>/' )
	);
	salt_harness_exit();
}

// ── One case ──────────────────────────────────────────────────────────────────────────────────

$salt_view_input = json_decode( (string) stream_get_contents( STDIN ), true );
if ( ! is_array( $salt_view_input ) || ( ! isset( $salt_view_input['view'] ) && ! isset( $salt_view_input['page'] ) ) ) {
	fwrite( STDERR, "salt harness: stdin is not a view or page case\n" );
	exit( 2 );
}

$salt_view_lock = fopen( (string) getenv( 'SALT_HARNESS_LOCK' ), 'c' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- A lock file.
flock( $salt_view_lock, LOCK_EX );

salt_view_db( 'restore' );
$salt_view_uploads = (string) getenv( 'SALT_HARNESS_WP' ) . '/wp-content/uploads';
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $salt_view_uploads, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $salt_view_file ) {
	$salt_view_file->isDir() ? rmdir( $salt_view_file->getPathname() ) : unlink( $salt_view_file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.AlternativeFunctions.unlink_unlink -- The throwaway site's uploads.
}
clearstatcache();
$salt_view_log  = (string) getenv( 'SALT_HARNESS_LOG' );
$salt_view_from = (int) filesize( $salt_view_log );

require __DIR__ . '/wp.php';
require __DIR__ . '/seed.php';

/**
 * The case's site data into WordPress's options and the theme's, as the
 * adapter's chrome cases seed them (tests/contract-adapter/adapter.php).
 *
 * @param array<string,mixed> $site The case's `site`.
 * @return void
 */
function salt_view_site( array $site ): void {
	if ( isset( $site['name'] ) ) {
		update_option( 'blogname', (string) $site['name'] );
	}
	if ( isset( $site['timezone'] ) ) {
		update_option( 'timezone_string', (string) $site['timezone'] );
	}
	$options = array(
		'org_name'           => $site['organisation'] ?? null,
		'logo_light'         => isset( $site['logo']['light'] ) ? salt_harness_attachment( (string) $site['logo']['light'] ) : null,
		'logo_dark'          => isset( $site['logo']['dark'] ) ? salt_harness_attachment( (string) $site['logo']['dark'] ) : null,
		'logo_height'        => $site['logoHeight'] ?? null,
		'header_sticky'      => $site['header']['sticky'] ?? null,
		'header_cta'         => isset( $site['header']['cta']['label'], $site['header']['cta']['href'] ) ? array(
			'title' => $site['header']['cta']['label'],
			'url'   => $site['header']['cta']['href'],
		) : null,
		'footer_text'        => $site['footer']['text'] ?? null,
		'footer_show_social' => isset( $site['footer'] ) ? ! empty( $site['footer']['profiles'] ) : null,
	);
	foreach ( $options as $name => $value ) {
		if ( null !== $value ) {
			update_field( $name, $value, 'option' );
		}
	}
}

/**
 * Section rows (`{ section, values }`) as rows of a flexible content field
 * whose layouts take the contract's section ids.
 *
 * @param array<int,array<string,mixed>> $sections The document's sections.
 * @param string                         $key      The flexible content field's key.
 * @return array<int,array<string,mixed>>
 */
function salt_view_rows( array $sections, string $key ): array {
	$field   = acf_get_field( $key );
	$layouts = array_column( array_values( (array) ( $field['layouts'] ?? array() ) ), null, 'name' );
	$rows    = array();
	foreach ( $sections as $section ) {
		$name   = (string) $section['section'];
		$rows[] = array( 'acf_fc_layout' => $name ) + salt_harness_to_acf(
			(array) ( $section['values'] ?? array() ),
			array(
				'type'       => 'group',
				'sub_fields' => $layouts[ $name ]['sub_fields'] ?? array(),
			)
		);
	}
	return $rows;
}

/**
 * The case's collections as posts of their types, in the order given: a
 * field WordPress keeps natively (salt.native in
 * inc/fields/generated/collections.json) in its place, every other key in
 * its generated field, the item id as the slug, and dates that keep the
 * order (as the adapter seeds them for a section case).
 *
 * @param array<string,mixed> $collections The case's `collections`.
 * @return void
 */
function salt_view_collections( array $collections ): void {
	$groups = array_column( array_map( static fn( array $g ): array => $g + array( 'collection' => $g['salt']['collection'] ), salt_generated_collection_groups() ), null, 'collection' );
	foreach ( $collections as $collection => $items ) {
		$group  = $groups[ $collection ];
		$native = (array) ( $group['salt']['native'] ?? array() );
		foreach ( array_values( (array) $items ) as $order => $item ) {
			if ( 'team' === $collection ) {
				salt_harness_team_member(
					array(
						'name'    => $item['name'] ?? $item['title'],
						'href'    => ( $item['showOnWebsite'] ?? true ) ? ( $item['href'] ?? '/team/' . ( $item['slug'] ?? $item['id'] ) . '/' ) : '',
						'role'    => $item['role'] ?? $item['meta'] ?? null,
						'bio'     => $item['bio'] ?? $item['text'] ?? null,
						'email'   => $item['publicEmail'] ?? $item['email'] ?? null,
						'phone'   => $item['phone'] ?? null,
						'socials' => $item['socials'] ?? array(),
						'photo'   => $item['photo'] ?? $item['image'] ?? null,
					),
					$GLOBALS['salt_harness_media']
				);
				continue;
			}
			$post   = array(
				'post_type'   => salt_collection_group_post_type( $group ),
				'post_status' => 'publish',
				'post_name'   => (string) ( $item['id'] ?? '' ),
				'menu_order'  => $order,
				'post_date'   => sprintf( '2026-01-01 00:%02d:00', 59 - min( 59, $order ) ),
			);
			$fields = array();
			foreach ( $item as $name => $value ) {
				$place = $native[ $name ] ?? ( 'id' === $name ? 'id' : null );
				if ( 'title' === $place ) {
					$post['post_title'] = (string) $value;
				} elseif ( 'body' === $place ) {
					$post['post_content'] = (string) $value;
				} elseif ( 'excerpt' === $place ) {
					$post['post_excerpt'] = (string) $value;
				} elseif ( null === $place ) {
					$fields[ $name ] = $value;
				}
			}
			$id = (int) wp_insert_post( $post );
			foreach ( $fields as $name => $value ) {
				update_field( $name, $value, $id );
			}
		}
	}
}

/**
 * A card a route lists (`{ id, title, href, image, date, text }`) as a
 * published post.
 *
 * @param array<string,mixed> $card  The card.
 * @param array<string,mixed> $media The case's media.
 * @param array<string,mixed> $more  More of the post (its category or author).
 * @return int Post ID.
 */
function salt_view_card( array $card, array $media, array $more ): int {
	$id = (int) wp_insert_post(
		$more + array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => (string) $card['title'],
			'post_name'    => salt_harness_slug( (string) ( $card['href'] ?? '' ) ),
			'post_excerpt' => (string) ( $card['text'] ?? '' ),
			'post_date'    => isset( $card['date'] ) ? $card['date'] . ' 09:00:00' : '2026-01-01 09:00:00', // Fixed, so the report does not change day to day.
		)
	);
	if ( isset( $card['image'] ) ) {
		set_post_thumbnail( $id, salt_harness_single_image( (string) $card['image'], $media ) );
	}
	return $id;
}

/**
 * A view case's site as WordPress holds it, and the rest for the theme's
 * seams (tests/real-acf/site/salt-harness-case.php):
 *  - site.home (a path, or `{ href, title }`) as a published static front
 *    page, and none as a static front page that is not chosen, so a site
 *    with no home page has none (salt_contract_home());
 *  - site.collectionIndexes.post as the posts page, with its title;
 *  - the case's site, document, route and context in the `salt_harness_case` option,
 *    which the mu-plugin reads.
 *
 * @param array<string,mixed> $input The case.
 * @return void
 */
function salt_view_case_site( array $input ): void {
	$site = (array) ( $input['site'] ?? array() );
	$page = static function ( string $href, string $title ): int {
		$slug = salt_harness_slug( $href );
		return (int) wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => '' !== $slug ? $slug : sanitize_title( $title ),
			)
		);
	};
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', 0 );
	if ( isset( $site['home'] ) ) {
		$home = is_array( $site['home'] ) ? $site['home'] : array( 'href' => (string) $site['home'] );
		update_option( 'page_on_front', $page( (string) ( $home['href'] ?? '/' ), (string) ( $home['title'] ?? 'Home' ) ) );
	}
	if ( isset( $site['collectionIndexes']['post'] ) ) {
		$index = (array) $site['collectionIndexes']['post'];
		update_option( 'page_for_posts', $page( (string) $index['href'], (string) ( $index['title'] ?? '' ) ) );
	}
	update_option(
		'salt_harness_case',
		array(
			'site'     => $site,
			'document' => (array) ( $input['document'] ?? array() ),
			'route'    => (array) ( $input['route'] ?? array() ),
			'context'  => (array) ( $input['context'] ?? array() ),
		)
	);
}

/**
 * Each attachment a case's media names (its title is the media name, as
 * seed.php makes them) served from the record's `{width}` template, as
 * the adapter's upload URL filter serves it: the template on the
 * attachment (the mu-plugin draws it), and a `salt-<width>` sub-size for
 * every contract candidate below the upload's width (salt_image_candidates()).
 *
 * @param array<string,mixed> $media The case's media.
 * @return void
 */
function salt_view_media_urls( array $media ): void {
	foreach ( get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => -1,
		)
	) as $attachment ) {
		$record = $media[ $attachment->post_title ] ?? null;
		if ( ! is_array( $record ) || ! isset( $record['url'] ) ) {
			continue;
		}
		$meta   = (array) wp_get_attachment_metadata( $attachment->ID );
		$width  = (int) ( $meta['width'] ?? 0 );
		$height = (int) ( $meta['height'] ?? 0 );
		foreach ( salt_image_candidates() as $candidate ) {
			if ( $candidate < $width ) {
				$meta['sizes'][ 'salt-' . $candidate ] = array(
					'file'      => $attachment->post_title . '-' . $candidate . '.png',
					'width'     => $candidate,
					'height'    => (int) round( $height * $candidate / max( 1, $width ) ),
					'mime-type' => 'image/png',
				);
			}
		}
		wp_update_attachment_metadata( $attachment->ID, $meta );
		update_post_meta( $attachment->ID, '_salt_harness_url', (string) $record['url'] );
	}
}

/**
 * The search case's results hold its query in their text, so WordPress's
 * own search finds each one (a result's title need not hold it).
 *
 * @param array<string,mixed> $doc The case's document.
 * @return void
 */
function salt_view_search_text( array $doc ): void {
	foreach ( (array) ( $doc['results'] ?? array() ) as $result ) {
		$found = get_posts(
			array(
				'post_type'   => 'any',
				'title'       => (string) $result['title'],
				'numberposts' => 1,
			)
		);
		if ( array() !== $found ) {
			wp_update_post(
				array(
					'ID'           => $found[0]->ID,
					'post_content' => (string) ( $doc['query'] ?? '' ),
				)
			);
		}
	}
}

/**
 * A case's site.caseStudy (the layout a site sets once for its case
 * studies) on Site Settings → Case Studies, as an editor saves it
 * (inc/fields/options-case-studies.php): each setting the case gives, a dark
 * tone left out as automatic, and the defaults (plain, flow) otherwise.
 *
 * @param array<string,mixed> $site The case's `site`.
 * @return void
 */
function salt_view_case_study_options( array $site ): void {
	$layout = (array) ( $site['caseStudy'] ?? array() );
	update_field( 'field_salt_options_case_study_header', (string) ( $layout['header'] ?? 'plain' ), 'option' );
	update_field( 'field_salt_options_case_study_header_tone', (string) ( $layout['headerTone'] ?? 'surface' ), 'option' );
	update_field( 'field_salt_options_case_study_header_tone_dark', (string) ( $layout['headerToneDark'] ?? 'auto' ), 'option' );
	update_field( 'field_salt_options_case_study_media', (string) ( $layout['media'] ?? 'flow' ), 'option' );
}

ob_start();
$GLOBALS['salt_harness_media'] = (array) ( $salt_view_input['media'] ?? array() );
$salt_view_doc                 = (array) ( $salt_view_input['document'] ?? array() );
$salt_view_media               = $GLOBALS['salt_harness_media'];
$salt_view_kind                = (string) ( $salt_view_input['view'] ?? 'page' );
$salt_view_path                = (string) ( $salt_view_input['context']['path'] ?? '/' );

salt_view_site( (array) ( $salt_view_input['site'] ?? array() ) );
foreach ( array_keys( (array) ( $salt_view_input['documents'] ?? array() ) ) as $salt_view_name ) {
	salt_harness_case_document( $salt_view_input, (string) $salt_view_name );
}

switch ( $salt_view_kind ) {
	case 'page':
		$salt_view_id = (int) wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => (string) ( $salt_view_doc['title'] ?? '' ),
				'post_name'   => salt_harness_slug( $salt_view_path ),
			)
		);
		update_field( 'sections', salt_view_rows( (array) ( $salt_view_doc['sections'] ?? array() ), 'field_salt_sections_container' ), $salt_view_id );
		if ( '/' === $salt_view_path ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $salt_view_id );
		}
		break;
	case 'post':
		$salt_view_post = salt_harness_seed_post( $salt_view_input );
		if ( isset( $salt_view_doc['sections'] ) ) {
			// The prose comes first in the layout (post/band-*), then the case's bands.
			update_field( 'field_salt_collection_posts_layout', array_merge( (array) get_field( 'field_salt_collection_posts_layout', $salt_view_post['id'], false ), salt_view_rows( (array) $salt_view_doc['sections'], 'field_salt_collection_posts_layout' ) ), $salt_view_post['id'] );
		}
		foreach ( array(
			'newer' => '+1 day',
			'older' => '-1 day',
		) as $salt_view_side => $salt_view_shift ) {
			if ( isset( $salt_view_doc['adjacent'][ $salt_view_side ] ) ) {
				salt_view_card(
					(array) $salt_view_doc['adjacent'][ $salt_view_side ],
					$salt_view_media,
					array( 'post_date' => gmdate( 'Y-m-d', (int) strtotime( $salt_view_doc['date'] . ' ' . $salt_view_shift ) ) . ' 09:00:00' )
				);
			}
		}
		salt_view_collections( (array) ( $salt_view_input['collections'] ?? array() ) );
		break;
	case 'service':
		salt_harness_seed_service( $salt_view_input );
		break;
	case 'case-study':
		salt_harness_seed_case_study( $salt_view_input );
		salt_view_case_study_options( (array) ( $salt_view_input['site'] ?? array() ) );
		break;
	case 'archive':
		$salt_view_slug = salt_harness_slug( (string) ( $salt_view_doc['href'] ?? $salt_view_path ) );
		if ( 'author' === ( $salt_view_doc['kind'] ?? '' ) ) {
			$salt_view_member = salt_harness_team_member(
				array(
					'name'    => (string) $salt_view_doc['title'],
					'href'    => (string) ( $salt_view_doc['href'] ?? $salt_view_path ),
					'role'    => $salt_view_doc['strapline'] ?? null,
					'bio'     => $salt_view_doc['biography'] ?? null,
					'photo'   => $salt_view_doc['photo'] ?? null,
					'email'   => $salt_view_doc['email'] ?? null,
					'phone'   => $salt_view_doc['phone'] ?? null,
					'socials' => $salt_view_doc['socials'] ?? array(),
				),
				$salt_view_media
			);
			$salt_view_more   = array();
		} else {
			$salt_view_term = wp_insert_term(
				(string) $salt_view_doc['title'],
				'category',
				array(
					'slug'        => $salt_view_slug,
					'description' => (string) ( $salt_view_doc['description'] ?? '' ),
				)
			);
			$salt_view_more = array( 'post_category' => array( (int) $salt_view_term['term_id'] ) );
		}
		foreach ( (array) ( $salt_view_input['route']['cards'] ?? array() ) as $salt_view_card ) {
			$salt_view_card_id = salt_view_card( (array) $salt_view_card, $salt_view_media, $salt_view_more );
			if ( isset( $salt_view_member ) ) {
				wp_update_post(
					array(
						'ID'          => $salt_view_card_id,
						'post_author' => $salt_view_member,
					)
				);
			}
		}
		break;
	case 'search':
		$salt_view_types = array(
			'Post'       => 'post',
			'Service'    => 'service',
			'Case study' => 'work',
			'Page'       => 'page',
		);
		// The results carry no date: fixed ones, in the order given, keep the report the same every day.
		foreach ( array_values( (array) ( $salt_view_doc['results'] ?? array() ) ) as $salt_view_order => $salt_view_result ) {
			wp_insert_post(
				array(
					'post_type'    => $salt_view_types[ $salt_view_result['kind'] ] ?? 'page',
					'post_status'  => 'publish',
					'post_title'   => (string) $salt_view_result['title'],
					'post_name'    => salt_harness_slug( (string) $salt_view_result['url'] ),
					'post_excerpt' => (string) ( $salt_view_result['note'] ?? '' ),
					'post_date'    => sprintf( '2026-01-01 00:%02d:00', 59 - min( 59, $salt_view_order ) ),
				)
			);
		}
		salt_view_search_text( $salt_view_doc );
		$salt_view_path = '/?s=' . rawurlencode( (string) ( $salt_view_doc['query'] ?? '' ) );
		break;
	case 'not-found':
		break;
	default:
		// As the adapter's exit 4: a kind of case it cannot render yet, not input that is no case.
		fwrite( STDERR, "salt harness: the view {$salt_view_kind} is not rendered by the harness yet: view.php has no seeding for it\n" );
		exit( 4 );
}
if ( 'page' !== $salt_view_kind ) {
	salt_view_case_site( $salt_view_input );
	salt_view_media_urls( $salt_view_media );
}
$salt_view_stray = (string) ob_get_clean();
if ( '' !== $salt_view_stray ) {
	fwrite( STDERR, 'seeding printed: ' . $salt_view_stray . "\n" );
}

list( $salt_view_status, $salt_view_body ) = salt_harness_get( (string) getenv( 'SALT_HARNESS_SERVER' ) . $salt_view_path, salt_harness_host( (string) getenv( 'SALT_HARNESS_URL' ) ) );

// Every diagnostic that reached debug.log while this case was seeded and drawn.
$salt_view_diagnostics = array();
foreach ( preg_split( '/\R/', (string) file_get_contents( $salt_view_log, false, null, $salt_view_from ) ) as $salt_view_line ) {
	if ( ! str_starts_with( $salt_view_line, '[' ) ) {
		continue;
	}
	$salt_view_text = (string) preg_replace( '/^\[[^\]]+\]\s*/', '', $salt_view_line );
	$salt_view_text = str_replace( dirname( __DIR__, 3 ) . '/', '', $salt_view_text );
	if ( str_starts_with( $salt_view_text, 'salt: ' ) ) {
		fwrite( STDERR, 'salt_log: ' . substr( $salt_view_text, 6 ) . "\n" );
	} else {
		$salt_view_diagnostics[] = $salt_view_text;
	}
}
flock( $salt_view_lock, LOCK_UN );

if ( array() !== $salt_view_diagnostics ) {
	fwrite( STDERR, implode( "\n", $salt_view_diagnostics ) . "\n" );
	exit( 70 );
}
$salt_view_expected = 'not-found' === $salt_view_kind ? 404 : 200;
if ( $salt_view_expected !== $salt_view_status ) {
	fwrite( STDERR, sprintf( "salt harness: %s answered %d, expected %d\n", $salt_view_path, $salt_view_status, $salt_view_expected ) );
	exit( 1 );
}

if ( 'page' === $salt_view_kind ) {
	echo $salt_view_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The site's own document.
	exit( 0 );
}
$salt_view_start = preg_match( '/<main\b[^>]*\bid="main"[^>]*>/', $salt_view_body, $salt_view_m, PREG_OFFSET_CAPTURE ) ? (int) $salt_view_m[0][1] : -1;
$salt_view_end   = $salt_view_start >= 0 ? strpos( $salt_view_body, '</main>', $salt_view_start ) : false;
if ( false === $salt_view_end ) {
	fwrite( STDERR, "salt harness: {$salt_view_path} drew no main#main\n" );
	exit( 1 );
}
echo substr( $salt_view_body, $salt_view_start, $salt_view_end + strlen( '</main>' ) - $salt_view_start ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The site's own markup.
exit( 0 );
