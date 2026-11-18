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
 * showcase holds a 4.5 rating (the half star; labels.rating then
 * "{n} of {N}", markup/card.json).
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';
require __DIR__ . '/seed.php';

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

salt_harness_seed_menus();

// ── The service (fixtures/service/full.json) ─────────────────────────────────────────────────
$case    = salt_harness_fixture( 'service/full' );
$doc     = $case['document'];
$media   = (array) ( $case['media'] ?? array() );
$seeded  = salt_harness_seed_service( $case );
$service = $seeded['id'];
$enquire = $seeded['enquire'];
$expect  = array(
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
		'post_content' => "First paragraph with <strong>bold</strong>.\n\n[gallery ids=\"{$seeded['related'][0]}\"]\n\nhttps://www.youtube.com/watch?v=abc123\n\n<img src=\"x.jpg\" alt=\"\">",
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
$case   = salt_harness_fixture( 'case-study/full' );
$doc    = $case['document'];
$media  = (array) ( $case['media'] ?? array() );
$work   = salt_harness_seed_case_study( $case );
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
$seeded = salt_harness_seed_post( $case );
$post   = $seeded['id'];
$member = $seeded['member'];
salt_harness_single_check(
	'single-post.php draws the contract\'s post/full',
	$post,
	array(
		'title'           => '>' . esc_html( $doc['title'] ) . '</h1>',
		'date'            => 'datetime="' . $doc['date'] . 'T09:00:00',
		// The badge is the primary topic (the fixture's category), not merely the first topic.
		'primary topic'   => esc_html( $doc['category']['title'] ) . '</a></p>',
		'credited author' => '>' . esc_html( $doc['author']['name'] ) . '</a>',
		'author role'     => esc_html( $doc['author']['role'] ),
		'author photo'    => 'alt="' . esc_attr( $media[ $doc['author']['photo'] ]['alt'] ) . '"',
		'featured image'  => 'alt="' . esc_attr( $media[ $doc['featuredImage'] ]['alt'] ) . '"',
		'body'            => 'Most gardeners reach for a bottle of feed',
		'body h3'         => '<h3>Why compost does the heavy lifting</h3>',
		'body bold'       => '<strong>well-rotted</strong>',
	)
);
salt_harness_info( 'author ' . (int) get_field( 'author', $post ) . ' (team member ' . $member . '), primaryTopic ' . (int) get_field( 'primaryTopic', $post ) . '; the WordPress user is never drawn' );

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
			'acf_fc_layout' => 'collection-showcase',
			'heading'       => 'What clients say',
			'source'        => 'testimonials',
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
		'the label (4.5, the founder\'s ruling)' => 'aria-label="Rated 4.5 of 5"',
	)
);

salt_harness_exit();
