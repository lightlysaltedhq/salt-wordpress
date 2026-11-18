<?php
/**
 * Seeding helpers the harness's phases share. HARNESS ONLY: required by
 * contract.php, singles.php and view.php after wp.php has loaded WordPress.
 *
 * - From the contract phase: contract-shaped section values to what ACF
 *   stores (salt_harness_to_acf()), with named media and documents made
 *   real attachments and posts. When a view case is being seeded,
 *   $GLOBALS['salt_harness_media'] holds its media records, and an
 *   attachment takes its record's size and alt text.
 * - From the singles phase: the contract's service, case-study and post
 *   documents as WordPress posts (each value in its native place or its
 *   generated field), their images and related services, the site's menus,
 *   and a GET against the harness server.
 *
 * @package Salt\Tests\RealAcf
 */

/**
 * The last segment of a path or URL: a document's slug from its href.
 *
 * @param string $href Path or URL.
 * @return string
 */
function salt_harness_slug( string $href ): string {
	return basename( (string) wp_parse_url( $href, PHP_URL_PATH ) );
}

/**
 * A site has its menus: the chrome logs a theme location with none, as it should.
 *
 * @return void
 */
function salt_harness_seed_menus(): void {
	foreach ( array( 'primary', 'footer' ) as $location ) {
		$menu = wp_create_nav_menu( 'Harness ' . $location );
		wp_update_nav_menu_item(
			(int) $menu,
			0,
			array(
				'menu-item-title'  => 'Home',
				'menu-item-url'    => home_url( '/' ),
				'menu-item-status' => 'publish',
			)
		);
		$locations              = (array) get_theme_mod( 'nav_menu_locations', array() );
		$locations[ $location ] = (int) $menu;
		set_theme_mod( 'nav_menu_locations', $locations );
	}
}

/**
 * A fixture case from the pinned contract.
 *
 * @param string $name e.g. 'service/full'.
 * @return array<string,mixed>
 */
function salt_harness_fixture( string $name ): array {
	return (array) json_decode( (string) file_get_contents( getenv( 'SALT_HARNESS_FIXTURES' ) . '/' . $name . '.json' ), true );
}

/**
 * A real 1x1 PNG attachment per fixture media name, with its alt text.
 *
 * @param string              $name  Media name.
 * @param array<string,mixed> $media The case's media.
 * @return int Attachment ID.
 */
function salt_harness_single_image( string $name, array $media ): int {
	static $ids = array();
	if ( isset( $ids[ $name ] ) ) {
		return $ids[ $name ];
	}
	$uploads = wp_upload_dir();
	$file    = $uploads['path'] . '/single-' . $name . '.png';
	file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- A fixed 1x1 PNG.
	$id = (int) wp_insert_attachment(
		array(
			'post_title'     => $name,
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
			'guid'           => $uploads['url'] . '/single-' . $name . '.png',
		),
		$file
	);
	wp_update_attachment_metadata(
		$id,
		array(
			'width'  => (int) ( $media[ $name ]['width'] ?? 1 ),
			'height' => (int) ( $media[ $name ]['height'] ?? 1 ),
			'file'   => _wp_relative_upload_path( $file ),
			'sizes'  => array(),
		)
	);
	update_post_meta( $id, '_wp_attachment_image_alt', (string) ( $media[ $name ]['alt'] ?? '' ) );
	$ids[ $name ] = $id;
	return $id;
}

/**
 * A published service per fixture id, made once (a related or listed one).
 *
 * @param array<string,mixed> $item  The fixture's card: id, title, text, image.
 * @param array<string,mixed> $media The case's media.
 * @return int Post ID.
 */
function salt_harness_single_service( array $item, array $media ): int {
	static $ids = array();
	$slug       = (string) $item['id'];
	$name       = isset( $item['href'] ) ? salt_harness_slug( (string) $item['href'] ) : '';
	if ( ! isset( $ids[ $slug ] ) ) {
		$ids[ $slug ] = (int) wp_insert_post(
			array(
				'post_type'    => 'service',
				'post_status'  => 'publish',
				'post_name'    => '' !== $name ? $name : 'single-' . $slug,
				'post_title'   => (string) $item['title'],
				'post_excerpt' => (string) ( $item['text'] ?? '' ),
			)
		);
		if ( isset( $item['image'] ) ) {
			set_post_thumbnail( $ids[ $slug ], salt_harness_single_image( (string) $item['image'], $media ) );
		}
	}
	return $ids[ $slug ];
}

/**
 * GET a URL from the harness server: the status and the body. Given a
 * host, the request names it in its Host header (the site's own address,
 * while the server listens elsewhere) and a redirect is not followed.
 *
 * @param string $url  URL.
 * @param string $host Host header, or '' for the URL's own.
 * @return array{int,string}
 */
function salt_harness_get( string $url, string $host = '' ): array {
	$http = array(
		'ignore_errors' => true,
		'timeout'       => 120,
	);
	if ( '' !== $host ) {
		$http['header']          = 'Host: ' . $host;
		$http['follow_location'] = 0;
	}
	$context = stream_context_create( array( 'http' => $http ) );
	$body    = (string) file_get_contents( $url, false, $context ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- The harness's own local server.
	$headers = function_exists( 'http_get_last_response_headers' ) ? (array) http_get_last_response_headers() : ( $http_response_header ?? array() );
	$status  = 0;
	foreach ( $headers as $header ) {
		if ( 1 === preg_match( '#^HTTP/\S+\s+(\d{3})#', (string) $header, $m ) ) {
			$status = (int) $m[1];
		}
	}
	return array( $status, $body );
}

/**
 * A field's sub field by name.
 *
 * @param array<int,array<string,mixed>> $fields Sub fields.
 * @param string                         $name   Name.
 * @return array<string,mixed>
 */
function salt_harness_sub( array $fields, string $name ): array {
	foreach ( $fields as $field ) {
		if ( ( $field['name'] ?? '' ) === $name ) {
			return $field;
		}
	}
	return array(
		'type' => 'missing',
		'key'  => '',
		'name' => $name,
	);
}

/**
 * A real 1x1 PNG attachment per media name, made once.
 *
 * @param string $name Fixture media name.
 * @return int Attachment ID.
 */
function salt_harness_attachment( string $name ): int {
	static $ids = array();
	if ( isset( $ids[ $name ] ) ) {
		return $ids[ $name ];
	}
	$uploads = wp_upload_dir();
	$file    = $uploads['path'] . '/' . $name . '.png';
	file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- A fixed 1x1 PNG.
	$id = wp_insert_attachment(
		array(
			'post_title'     => $name,
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
			'guid'           => $uploads['url'] . '/' . $name . '.png',
		),
		$file
	);
	wp_update_attachment_metadata(
		$id,
		array(
			'width'  => (int) ( $GLOBALS['salt_harness_media'][ $name ]['width'] ?? 1 ),
			'height' => (int) ( $GLOBALS['salt_harness_media'][ $name ]['height'] ?? 1 ),
			'file'   => _wp_relative_upload_path( $file ),
			'sizes'  => array(),
		)
	);
	if ( isset( $GLOBALS['salt_harness_media'][ $name ]['alt'] ) ) {
		update_post_meta( (int) $id, '_wp_attachment_image_alt', (string) $GLOBALS['salt_harness_media'][ $name ]['alt'] );
	}
	$ids[ $name ] = (int) $id;
	return $ids[ $name ];
}

/**
 * A published post of a type per document name, made once.
 *
 * @param string $name      Fixture document name.
 * @param string $post_type Post type.
 * @return int Post ID.
 */
function salt_harness_document( string $name, string $post_type ): int {
	static $ids = array();
	$key        = $post_type . ':' . $name;
	if ( ! isset( $ids[ $key ] ) ) {
		$ids[ $key ] = (int) wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_title'  => ucfirst( $name ),
				'post_name'   => $name,
			)
		);
	}
	return $ids[ $key ];
}

/**
 * Contract-shaped values to what ACF stores: named media and documents become
 * IDs; everything else is passed as the contract has it.
 *
 * @param mixed               $value Contract value.
 * @param array<string,mixed> $field ACF field.
 * @return mixed
 */
function salt_harness_to_acf( $value, array $field ) {
	switch ( $field['type'] ) {
		case 'group':
			$out = array();
			foreach ( (array) $value as $name => $v ) {
				$out[ $name ] = salt_harness_to_acf( $v, salt_harness_sub( $field['sub_fields'], (string) $name ) );
			}
			return $out;
		case 'repeater':
			return array_map(
				static fn( $row ) => salt_harness_to_acf(
					$row,
					array(
						'type'       => 'group',
						'sub_fields' => $field['sub_fields'],
					)
				),
				(array) $value
			);
		case 'image':
			return salt_harness_attachment( (string) $value );
		case 'gallery':
			return array_map( static fn( $v ) => salt_harness_attachment( (string) $v ), (array) $value );
		case 'post_object':
			return salt_harness_document( (string) $value, (string) ( $field['post_type'][0] ?? 'page' ) );
		case 'relationship':
			return array_map( static fn( $v ) => salt_harness_document( (string) $v, (string) ( $field['post_type'][0] ?? 'page' ) ), (array) $value );
		case 'taxonomy':
			return array_map( static fn( $v ) => (int) ( wp_insert_term( (string) $v, $field['taxonomy'] )['term_id'] ?? 0 ), (array) $value );
		default:
			return $value;
	}
}

/**
 * A document a case names (its `documents`), as a published page with the
 * record's title and the slug of its href. Made once per name.
 *
 * @param array<string,mixed> $input The case.
 * @param string              $name Document name.
 * @return int Post ID.
 */
function salt_harness_case_document( array $input, string $name ): int {
	$id     = salt_harness_document( $name, 'page' );
	$record = (array) ( $input['documents'][ $name ] ?? array() );
	if ( array() !== $record ) {
		$update = array(
			'ID'         => $id,
			'post_title' => (string) ( $record['title'] ?? '' ),
		);
		if ( '' !== salt_harness_slug( (string) ( $record['href'] ?? '' ) ) ) {
			$update['post_name'] = salt_harness_slug( (string) $record['href'] );
		}
		wp_update_post( $update );
	}
	return $id;
}

/**
 * A post topic (`{ title, href }`) as a category, its slug from its href. Made once.
 *
 * @param array<string,mixed> $topic Topic.
 * @return int Term ID.
 */
function salt_harness_topic( array $topic ): int {
	$slug  = salt_harness_slug( (string) ( $topic['href'] ?? '' ) );
	$found = term_exists( '' !== $slug ? $slug : (string) $topic['title'], 'category' );
	if ( is_array( $found ) ) {
		return (int) $found['term_id'];
	}
	$term = wp_insert_term( (string) $topic['title'], 'category', '' !== $slug ? array( 'slug' => $slug ) : array() );
	return is_array( $term ) ? (int) $term['term_id'] : 0;
}

/**
 * A service document (contract/collections/services.json, as the service
 * view receives it): title, summary (the excerpt), intro (the body),
 * benefits, priceFrom, related services and the call to action. Its slug is
 * the case's path's last segment, when the case gives one.
 *
 * @param array<string,mixed> $input The case.
 * @return array{id:int,enquire:int,related:int[]}
 */
function salt_harness_seed_service( array $input ): array {
	$doc     = (array) $input['document'];
	$media   = (array) ( $input['media'] ?? array() );
	$related = array_map( static fn( array $item ): int => salt_harness_single_service( $item, $media ), (array) ( $doc['related'] ?? array() ) );
	$service = (int) wp_insert_post(
		array(
			'post_type'    => 'service',
			'post_status'  => 'publish',
			'post_title'   => (string) $doc['title'],
			'post_name'    => salt_harness_slug( (string) ( $input['context']['path'] ?? '' ) ),
			'post_excerpt' => (string) ( $doc['summary'] ?? '' ),
			'post_content' => (string) ( $doc['intro'] ?? '' ),
		)
	);
	if ( isset( $doc['benefits'] ) ) {
		update_field( 'benefits', array_map( static fn( string $text ): array => array( 'text' => $text ), $doc['benefits'] ), $service );
	}
	if ( isset( $doc['priceFrom'] ) ) {
		update_field( 'priceFrom', (string) $doc['priceFrom'], $service );
	}
	if ( array() !== $related ) {
		update_field( 'relatedServices', $related, $service );
	}
	$enquire = 0;
	if ( isset( $doc['cta'] ) ) {
		$enquire = isset( $doc['cta']['document'] ) ? salt_harness_case_document( $input, (string) $doc['cta']['document'] ) : 0;
		update_field(
			'cta',
			array(
				'label'    => (string) $doc['cta']['label'],
				'type'     => (string) $doc['cta']['type'],
				'document' => $enquire,
				'url'      => (string) ( $doc['cta']['url'] ?? '' ),
				'newTab'   => (bool) ( $doc['cta']['newTab'] ?? false ),
			),
			$service
		);
	}
	return array(
		'id'      => $service,
		'enquire' => $enquire,
		'related' => $related,
	);
}

/**
 * A case-study document (contract/collections/case-studies.json): title,
 * summary (the excerpt) and image (the featured image) natively; client,
 * challenge, solution, results, details, gallery, externalUrl and services
 * in the generated group. Its slug is the case's path's last segment.
 *
 * @param array<string,mixed> $input The case.
 * @return int Post ID.
 */
function salt_harness_seed_case_study( array $input ): int {
	$doc   = (array) $input['document'];
	$media = (array) ( $input['media'] ?? array() );
	$work  = (int) wp_insert_post(
		array(
			'post_type'    => 'work',
			'post_status'  => 'publish',
			'post_title'   => (string) $doc['title'],
			'post_name'    => salt_harness_slug( (string) ( $input['context']['path'] ?? '' ) ),
			'post_excerpt' => (string) ( $doc['summary'] ?? '' ),
		)
	);
	if ( isset( $doc['image'] ) ) {
		set_post_thumbnail( $work, salt_harness_single_image( (string) $doc['image'], $media ) );
	}
	foreach ( array( 'client', 'challenge', 'solution', 'externalUrl' ) as $name ) {
		if ( isset( $doc[ $name ] ) ) {
			update_field( $name, (string) $doc[ $name ], $work );
		}
	}
	foreach ( array( 'results', 'details' ) as $name ) {
		if ( isset( $doc[ $name ] ) ) {
			update_field( $name, $doc[ $name ], $work );
		}
	}
	if ( isset( $doc['gallery'] ) ) {
		update_field( 'gallery', array_map( static fn( string $name ): int => salt_harness_single_image( $name, $media ), $doc['gallery'] ), $work );
	}
	if ( isset( $doc['services'] ) ) {
		update_field( 'services', array_map( static fn( array $item ): int => salt_harness_single_service( $item, $media ), $doc['services'] ), $work );
	}
	return $work;
}

/**
 * A person (`{ name, href?, role, bio, photo }`, contract/collections/
 * _person.json) as a team member: name the title, role and bio its fields,
 * photo its featured image. With an href it is published under the href's
 * last segment, so its archive is that address; without one it is a draft,
 * credited with no archive (the theme's provisional rule, ID-3952).
 *
 * @param array<string,mixed> $person The person.
 * @param array<string,mixed> $media  The case's media.
 * @return int Post ID.
 */
function salt_harness_team_member( array $person, array $media ): int {
	$href   = (string) ( $person['href'] ?? '' );
	$member = (int) wp_insert_post(
		array(
			'post_type'   => 'team_member',
			'post_status' => '' !== $href ? 'publish' : 'draft',
			'post_title'  => (string) $person['name'],
			'post_name'   => '' !== $href ? salt_harness_slug( $href ) : '',
		)
	);
	foreach ( array( 'role', 'bio' ) as $name ) {
		if ( isset( $person[ $name ] ) ) {
			update_field( 'field_salt_collection_team_' . $name, (string) $person[ $name ], $member );
		}
	}
	if ( isset( $person['photo'] ) ) {
		set_post_thumbnail( $member, salt_harness_single_image( (string) $person['photo'], $media ) );
	}
	return $member;
}

/**
 * A post document (contract/collections/posts.json, as the post view
 * receives it): title, date (published at 09:00), topics and category
 * (categories, and the category as primaryTopic), featuredImage, the body
 * as the layout's first row (a rich-text section that is prose alone), and
 * the credited author as a team member in the `author` field
 * (salt_harness_team_member()). The WordPress author is the site's first
 * user, which the theme never draws. Its slug is its href's last segment.
 *
 * @param array<string,mixed> $input The case.
 * @return array{id:int,member:int,topics:int[],primary:int}
 */
function salt_harness_seed_post( array $input ): array {
	$doc     = (array) $input['document'];
	$media   = (array) ( $input['media'] ?? array() );
	$topics  = array_map( 'salt_harness_topic', (array) ( $doc['topics'] ?? array() ) );
	$primary = isset( $doc['category'] ) ? salt_harness_topic( (array) $doc['category'] ) : 0;
	$post    = (int) wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_title'    => (string) $doc['title'],
			'post_name'     => salt_harness_slug( (string) ( $doc['href'] ?? '' ) ),
			'post_date'     => $doc['date'] . ' 09:00:00',
			'post_category' => array_values( array_unique( array_filter( array_merge( $topics, array( $primary ) ) ) ) ),
			'post_author'   => 1,
		)
	);
	if ( isset( $doc['featuredImage'] ) ) {
		set_post_thumbnail( $post, salt_harness_single_image( (string) $doc['featuredImage'], $media ) );
	}
	if ( isset( $doc['body'] ) ) {
		// By key: update_field() looks a name up loosely, and a page section's `layout` select shares it.
		update_field(
			'field_salt_collection_posts_layout',
			array(
				array(
					'acf_fc_layout' => 'rich-text',
					'body'          => (string) $doc['body'],
				),
			),
			$post
		);
	}
	$member = 0;
	if ( isset( $doc['author']['name'] ) ) {
		$member = salt_harness_team_member( (array) $doc['author'], $media );
		update_field( 'field_salt_collection_posts_author', $member, $post );
	}
	if ( $primary > 0 ) {
		update_field( SALT_POST_PRIMARY_TOPIC_FIELD, $primary, $post );
	}
	return array(
		'id'      => $post,
		'member'  => $member,
		'topics'  => $topics,
		'primary' => $primary,
	);
}
