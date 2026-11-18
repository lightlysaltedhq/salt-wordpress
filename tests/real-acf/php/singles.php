<?php
/**
 * Phase: the three single templates over HTTP, from the contract's own
 * fixtures. HARNESS ONLY. A smoke render, not conformance: the view cases'
 * markup is a later unit (tests/contract-adapter/).
 *
 * Seeds one document each from the pinned contract's fixtures/service/full.json,
 * fixtures/case-study/full.json and fixtures/post/full.json: each `document`
 * value goes to its native place (the title, the excerpt, the body, the
 * featured image, the categories and the date) or to its generated ACF field
 * through update_field(). Named media become real attachments with the
 * fixture's alt text; a related or listed service becomes a published
 * service. Then it requests single-service.php, single-work.php and
 * single-post.php from the harness's PHP server and checks each answers 200
 * and draws every seeded value the template draws. PHP diagnostics on the
 * server are caught by run.sh's debug.log check, as in every phase.
 *
 * Also over HTTP: a service whose body holds a shortcode and a video address
 * (the intro runs no the_content filter), and a page whose testimonials
 * section holds a 4.5 rating (the half star).
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';

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
	if ( ! isset( $ids[ $slug ] ) ) {
		$ids[ $slug ] = (int) wp_insert_post(
			array(
				'post_type'    => 'service',
				'post_status'  => 'publish',
				'post_name'    => 'single-' . $slug,
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
 * GET a URL from the harness server: the status and the body.
 *
 * @param string $url URL.
 * @return array{int,string}
 */
function salt_harness_get( string $url ): array {
	$context = stream_context_create(
		array(
			'http' => array(
				'ignore_errors' => true,
				'timeout'       => 120,
			),
		)
	);
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
 * Request a page and check it answers 200 and draws every expected fragment.
 *
 * @param string                $what   What is being checked.
 * @param int                   $id     Post ID.
 * @param array<string,string>  $expect Label => fragment that must appear.
 * @param array<string,string>  $absent Label => fragment that must not.
 * @return string The body.
 */
function salt_harness_single_check( string $what, int $id, array $expect, array $absent = array() ): string {
	$url                   = (string) get_permalink( $id );
	list( $status, $body ) = salt_harness_get( $url );
	$missing               = array_keys( array_filter( $expect, static fn( string $fragment ): bool => ! str_contains( $body, $fragment ) ) );
	$present               = array_keys( array_filter( $absent, static fn( string $fragment ): bool => str_contains( $body, $fragment ) ) );
	salt_harness_result(
		200 === $status && array() === $missing && array() === $present,
		$what,
		sprintf(
			'%s answered %d; draws %d of %d seeded values%s%s',
			str_replace( home_url(), '', $url ),
			$status,
			count( $expect ) - count( $missing ),
			count( $expect ),
			array() === $missing ? '' : '; MISSING ' . implode( ', ', $missing ),
			array() === $present ? '' : '; DRAWN BUT MUST NOT BE ' . implode( ', ', $present )
		)
	);
	return $body;
}

// A site has its menus: the chrome logs a theme location with none, as it should.
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

// ── The service (fixtures/service/full.json) ─────────────────────────────────────────────────
$case    = salt_harness_fixture( 'service/full' );
$doc     = $case['document'];
$media   = (array) ( $case['media'] ?? array() );
$enquire = (int) wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => (string) $case['documents'][ $doc['cta']['document'] ]['title'],
	)
);
$related = array_map( static fn( array $item ): int => salt_harness_single_service( $item, $media ), $doc['related'] );
$service = (int) wp_insert_post(
	array(
		'post_type'    => 'service',
		'post_status'  => 'publish',
		'post_title'   => (string) $doc['title'],
		'post_excerpt' => (string) $doc['summary'],
		'post_content' => (string) $doc['intro'],
	)
);
update_field( 'benefits', array_map( static fn( string $text ): array => array( 'text' => $text ), $doc['benefits'] ), $service );
update_field( 'priceFrom', (string) $doc['priceFrom'], $service );
update_field( 'relatedServices', $related, $service );
update_field(
	'cta',
	array(
		'label'    => (string) $doc['cta']['label'],
		'type'     => (string) $doc['cta']['type'],
		'document' => $enquire,
		'url'      => '',
		'newTab'   => (bool) $doc['cta']['newTab'],
	),
	$service
);
$expect = array(
	'title'               => '>' . esc_html( $doc['title'] ) . '</h1>',
	'summary'             => esc_html( $doc['summary'] ),
	'price'               => esc_html( $doc['priceFrom'] ),
	'cta'                 => esc_html( $doc['cta']['label'] ),
	'cta to its document' => 'href="' . esc_url( (string) get_permalink( $enquire ) ) . '"',
	'intro paragraph 1'   => '<p>Every plan starts with a visit, a soil test and a long conversation about how you use the garden.</p>',
	'intro paragraph 2'   => '<p>We draw two options, agree one, then plant it ourselves.</p>',
);
foreach ( $doc['benefits'] as $i => $benefit ) {
	$expect[ "benefit {$i}" ] = esc_html( $benefit );
}
foreach ( $doc['related'] as $i => $item ) {
	$expect[ "related {$i} title" ]   = esc_html( $item['title'] );
	$expect[ "related {$i} summary" ] = esc_html( $item['text'] );
	$expect[ "related {$i} image" ]   = 'alt="' . esc_attr( $media[ $item['image'] ]['alt'] ) . '"';
}
$body = salt_harness_single_check(
	'single-service.php draws the contract\'s service/full',
	$service,
	$expect
);
// KNOWN FIXTURE MISMATCH, pending the EP-70 correction of the contract's example: rc.7's
// fixtures/service/full.json puts an <h3> in the intro, but the intro's own list (services.json
// intro) has no heading, and the founder ruled the intro stays restricted. The theme strips the
// heading to text; this checks the two paragraphs and that no h2 or h3 comes from the intro, and
// deliberately does not check the heading's text.
$intro      = (string) strstr( (string) substr( $body, (int) strpos( $body, '<div class="x-prose">' ) ), '</div>', true );
$paragraphs = array( $expect['intro paragraph 1'], $expect['intro paragraph 2'] );
$drawn      = count( array_filter( $paragraphs, static fn( string $p ): bool => str_contains( $intro, $p ) ) );
$heading    = 1 === preg_match( '/<h[23][\s>]/i', $intro );
salt_harness_result(
	str_contains( $body, '<div class="x-prose">' ) && 2 === $drawn && ! $heading,
	'the service intro draws its two paragraphs and no h2 or h3',
	sprintf( '%d of the 2 paragraphs in the intro; h2 or h3 in the intro: %s', $drawn, $heading ? 'YES' : 'none' )
);
salt_harness_info( 'stored body: ' . wp_json_encode( get_post_field( 'post_content', $service, 'raw' ) ) );

// The intro runs no the_content filter: no shortcode, no oEmbed, no other filter.
$probe = (int) wp_insert_post(
	array(
		'post_type'    => 'service',
		'post_status'  => 'publish',
		'post_title'   => 'Harness intro probe',
		'post_content' => "First paragraph with <strong>bold</strong>.\n\n[gallery ids=\"{$related[0]}\"]\n\nhttps://www.youtube.com/watch?v=abc123\n\n<img src=\"x.jpg\" alt=\"\">",
	)
);
salt_harness_single_check(
	'the service intro: paragraphs added, its list kept, no shortcode or embed',
	$probe,
	array(
		'paragraphs added (wpautop)' => '<p>First paragraph with <strong>bold</strong>.</p>',
		'the shortcode left as text' => '[gallery ids=',
		'the video address as text'  => '<p>https://www.youtube.com/watch?v=abc123</p>',
	),
	array(
		'a gallery' => 'class="gallery',
		'an embed'  => '<iframe',
		'an image'  => 'x.jpg',
	)
);

// ── The case study (fixtures/case-study/full.json) ───────────────────────────────────────────
$case  = salt_harness_fixture( 'case-study/full' );
$doc   = $case['document'];
$media = (array) ( $case['media'] ?? array() );
$work  = (int) wp_insert_post(
	array(
		'post_type'    => 'work',
		'post_status'  => 'publish',
		'post_title'   => (string) $doc['title'],
		'post_excerpt' => (string) $doc['summary'],
	)
);
set_post_thumbnail( $work, salt_harness_single_image( (string) $doc['image'], $media ) );
update_field( 'client', (string) $doc['client'], $work );
update_field( 'challenge', (string) $doc['challenge'], $work );
update_field( 'solution', (string) $doc['solution'], $work );
update_field( 'results', $doc['results'], $work );
update_field( 'details', $doc['details'], $work );
update_field( 'gallery', array_map( static fn( string $name ): int => salt_harness_single_image( $name, $media ), $doc['gallery'] ), $work );
update_field( 'externalUrl', (string) $doc['externalUrl'], $work );
update_field( 'services', array_map( static fn( array $item ): int => salt_harness_single_service( $item, $media ), $doc['services'] ), $work );
$expect = array(
	'title'          => '>' . esc_html( $doc['title'] ) . '</h1>',
	'summary'        => esc_html( $doc['summary'] ),
	'client'         => esc_html( $doc['client'] ),
	'featured image' => 'alt="' . esc_attr( $media[ $doc['image'] ]['alt'] ) . '"',
	'challenge'      => 'forty years of bramble and ash saplings',
	'solution'       => 'We cleared the garden by hand',
	'solution h3'    => '<h3>Planting for the caf',
	'solution bold'  => '<strong>salad and herbs</strong>',
	'externalUrl'    => 'href="' . esc_url( $doc['externalUrl'] ) . '"',
);
foreach ( $doc['results'] as $i => $row ) {
	$expect[ "result {$i} stat" ]  = esc_html( $row['stat'] );
	$expect[ "result {$i} label" ] = esc_html( $row['label'] );
}
foreach ( $doc['gallery'] as $name ) {
	$expect[ "gallery {$name}" ] = 'alt="' . esc_attr( $media[ $name ]['alt'] ) . '"';
}
foreach ( $doc['services'] as $i => $item ) {
	$expect[ "service {$i}" ] = esc_html( $item['title'] );
}
salt_harness_single_check( 'single-work.php draws the contract\'s case-study/full', $work, $expect );
salt_harness_info( 'details is stored but not drawn (M4): get_field returns ' . count( (array) get_field( 'details', $work ) ) . ' rows' );

// ── The post (fixtures/post/full.json) ───────────────────────────────────────────────────────
$case   = salt_harness_fixture( 'post/full' );
$doc    = $case['document'];
$media  = (array) ( $case['media'] ?? array() );
$topics = array();
foreach ( $doc['topics'] as $topic ) {
	$term     = wp_insert_term( (string) $topic['title'], 'category' );
	$topics[] = is_array( $term ) ? (int) $term['term_id'] : 0;
}
$post = (int) wp_insert_post(
	array(
		'post_type'     => 'post',
		'post_status'   => 'publish',
		'post_title'    => (string) $doc['title'],
		'post_content'  => (string) $doc['body'],
		'post_date'     => $doc['date'] . ' 09:00:00',
		'post_category' => $topics,
		'post_author'   => 1,
	)
);
set_post_thumbnail( $post, salt_harness_single_image( (string) $doc['featuredImage'], $media ) );
// The credited author is a team member (ID-3952 draws it); stored, as the contract shapes it.
$member = (int) wp_insert_post(
	array(
		'post_type'   => 'team_member',
		'post_status' => 'publish',
		'post_title'  => (string) $doc['author']['name'],
	)
);
update_field( 'role', (string) $doc['author']['role'], $member );
update_field( 'bio', (string) $doc['author']['bio'], $member );
set_post_thumbnail( $member, salt_harness_single_image( (string) $doc['author']['photo'], $media ) );
update_field( 'author', $member, $post );
update_field( 'primaryTopic', $topics[0], $post );
salt_harness_single_check(
	'single-post.php draws the contract\'s post/full',
	$post,
	array(
		'title'          => '>' . esc_html( $doc['title'] ) . '</h1>',
		'date'           => 'datetime="' . $doc['date'] . 'T09:00:00',
		// Which topic the badge shows is primaryTopic's, which ID-3952 draws; today it is one of them.
		'a topic badge'  => 'class="x-post-category',
		'featured image' => 'alt="' . esc_attr( $media[ $doc['featuredImage'] ]['alt'] ) . '"',
		'body'           => 'Most gardeners reach for a bottle of feed',
		'body h3'        => '<h3>Why compost does the heavy lifting</h3>',
		'body bold'      => '<strong>well-rotted</strong>',
	)
);
salt_harness_info( 'author and primaryTopic are stored, not drawn until ID-3952: author ' . (int) get_field( 'author', $post ) . ' (team member ' . $member . '), primaryTopic ' . (int) get_field( 'primaryTopic', $post ) );

// ── Half stars (testimonials.json rating, step 0.5) ──────────────────────────────────────────
$testimonial = (int) wp_insert_post(
	array(
		'post_type'   => 'testimonial',
		'post_status' => 'publish',
		'post_title'  => 'Harness half star',
	)
);
update_field( 'quote', 'Half a star short of perfect.', $testimonial );
update_field( 'rating', 4.5, $testimonial );
$page = (int) wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Harness testimonials',
	)
);
update_field(
	'sections',
	array(
		array(
			'acf_fc_layout' => 'testimonials',
			'heading'       => 'What clients say',
			'layout'        => 'grid',
		),
	),
	$page
);
salt_harness_single_check(
	'a stored 4.5 draws four and a half stars',
	$page,
	array(
		'the quote'                              => 'Half a star short of perfect.',
		'90% of five stars filled'               => 'style="--salt-rating-fill: 90%"',
		'the label (4.5, the founder\'s ruling)' => 'aria-label="Rated 4.5 out of 5 stars"',
	)
);

salt_harness_exit();
