<?php
/**
 * Tests for inc/schema/graph.php — the page-level JSON-LD @graph builder
 * (spec §15; SEO01/SEO02/SEO03/SEO05, TEST02).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/../shims/schema.php';
require_once dirname( __DIR__, 2 ) . '/inc/schema/graph.php';

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversFunction( 'salt_schema_add' )]
#[CoversFunction( 'salt_schema_reset' )]
#[CoversFunction( 'salt_schema_graph' )]
#[CoversFunction( 'salt_schema_render' )]
final class SchemaGraphTest extends SaltTestCase {

	private const HOME = 'https://example.test';
	private const PAGE = 'https://example.test/services/';

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['salt_test_schema'] = array(
			'home_url'       => self::HOME,
			'blogname'       => 'Example Co',
			'document_title' => 'Services — Example Co',
			'is_archive'     => false,
			'is_singular'    => true,
			'permalink'      => self::PAGE,
		);
	}

	// -----------------------------------------------------------------------
	// Fixtures.
	// -----------------------------------------------------------------------

	/**
	 * Full fixture context (TEST02 — fixture-driven, no WP).
	 *
	 * @param array<string,mixed> $overrides Context overrides.
	 * @return array<string,mixed>
	 */
	private function context( array $overrides = array() ): array {
		return array_merge(
			array(
				'home_url'    => self::HOME,
				'page_url'    => self::PAGE,
				'page_title'  => 'Services — Example Co',
				'site_name'   => 'Example Co',
				'org'         => array(
					'name'       => 'Example Co',
					'legal_name' => 'Example Company Ltd',
					'logo'       => 'https://example.test/logo.png',
					'same_as'    => array( 'https://www.linkedin.com/company/example' ),
					'phone'      => '+44 1234 567890',
					'email'      => 'hello@example.test',
				),
				'breadcrumbs' => array(
					array(
						'name' => 'Home',
						'url'  => 'https://example.test/',
					),
					array(
						'name' => 'Services',
						'url'  => '',
					),
				),
				'is_archive'  => false,
			),
			$overrides
		);
	}

	/**
	 * A FAQPage node with $count fixture questions labelled by $prefix.
	 *
	 * @param int    $count  Question count.
	 * @param string $prefix Label prefix.
	 * @return array<string,mixed>
	 */
	private function faq_node( int $count, string $prefix ): array {
		$questions = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => sprintf( '%s question %d?', $prefix, $i ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => sprintf( '%s answer %d.', $prefix, $i ),
				),
			);
		}
		return array(
			'@type'      => 'FAQPage',
			'mainEntity' => $questions,
		);
	}

	// -----------------------------------------------------------------------
	// Helpers.
	// -----------------------------------------------------------------------

	/**
	 * Nodes of a given @type.
	 *
	 * @param array<string,mixed> $document Full JSON-LD document.
	 * @param string              $type     Schema type.
	 * @return array<int,array<string,mixed>>
	 */
	private function nodes_of_type( array $document, string $type ): array {
		return array_values(
			array_filter(
				$document['@graph'],
				static fn( array $node ): bool => ( $node['@type'] ?? '' ) === $type
			)
		);
	}

	/**
	 * The single node carrying an @id, or null.
	 *
	 * @param array<string,mixed> $document Full JSON-LD document.
	 * @param string              $id       Node @id.
	 * @return array<string,mixed>|null
	 */
	private function node_by_id( array $document, string $id ): ?array {
		foreach ( $document['@graph'] as $node ) {
			if ( ( $node['@id'] ?? '' ) === $id ) {
				return $node;
			}
		}
		return null;
	}

	/**
	 * Assert every @id in the graph is unique (SEO05 linkage sanity).
	 *
	 * @param array<string,mixed> $document Full JSON-LD document.
	 * @return void
	 */
	private function assert_unique_ids( array $document ): void {
		$ids = array_map(
			static fn( array $node ): string => (string) ( $node['@id'] ?? '' ),
			$document['@graph']
		);
		$this->assertSame( array_unique( $ids ), $ids, 'Every @id in the @graph must be unique.' );
		$this->assertNotContains( '', $ids, 'Every node must carry an @id.' );
	}

	// -----------------------------------------------------------------------
	// 1. Base graph + SEO05 linkage.
	// -----------------------------------------------------------------------

	public function test_base_graph_nodes_ids_and_linkage(): void {
		$document = salt_schema_graph( $this->context() );

		$org = $this->node_by_id( $document, self::HOME . '#organization' );
		$this->assertNotNull( $org );
		$this->assertSame( 'Organization', $org['@type'] );

		$website = $this->node_by_id( $document, self::HOME . '#website' );
		$this->assertNotNull( $website );
		$this->assertSame( 'WebSite', $website['@type'] );
		$this->assertSame( array( '@id' => self::HOME . '#organization' ), $website['publisher'], 'publisher must be an @id-reference (SEO05).' );

		$webpage = $this->node_by_id( $document, self::PAGE . '#webpage' );
		$this->assertNotNull( $webpage );
		$this->assertSame( 'WebPage', $webpage['@type'] );
		$this->assertSame( array( '@id' => self::HOME . '#website' ), $webpage['isPartOf'], 'isPartOf must be an @id-reference (SEO05).' );
		$this->assertSame( array( '@id' => self::PAGE . '#breadcrumb' ), $webpage['breadcrumb'], 'breadcrumb must be an @id-reference (SEO05).' );

		$breadcrumb = $this->node_by_id( $document, self::PAGE . '#breadcrumb' );
		$this->assertNotNull( $breadcrumb );
		$this->assertSame( 'BreadcrumbList', $breadcrumb['@type'] );

		// Deterministic order: Organization, WebSite, WebPage, BreadcrumbList.
		$this->assertSame(
			array( 'Organization', 'WebSite', 'WebPage', 'BreadcrumbList' ),
			array_column( $document['@graph'], '@type' )
		);
		$this->assert_unique_ids( $document );
	}

	public function test_graph_json_round_trips(): void {
		salt_schema_add( $this->faq_node( 2, 'Round trip' ) );
		$json = wp_json_encode( salt_schema_graph( $this->context() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		$this->assertIsString( $json );
		$decoded = json_decode( $json, true );
		$this->assertIsArray( $decoded, 'Graph must round-trip as valid JSON.' );
		$this->assertSame( 'https://schema.org', $decoded['@context'] );
		$this->assertIsArray( $decoded['@graph'] );
		$this->assertNotEmpty( $decoded['@graph'] );
	}

	public function test_organization_omitted_when_org_name_empty(): void {
		$document = salt_schema_graph( $this->context( array( 'org' => array() ) ) );

		$this->assertSame( array(), $this->nodes_of_type( $document, 'Organization' ) );
		$website = $this->node_by_id( $document, self::HOME . '#website' );
		$this->assertNotNull( $website );
		$this->assertArrayNotHasKey( 'publisher', $website, 'No publisher ref without an organization node.' );
	}

	// -----------------------------------------------------------------------
	// 2. Required properties per type.
	// -----------------------------------------------------------------------

	public function test_required_properties_per_type(): void {
		salt_schema_add( $this->faq_node( 2, 'Props' ) );
		$document = salt_schema_graph( $this->context() );

		$org = $this->nodes_of_type( $document, 'Organization' )[0];
		$this->assertSame( 'Example Co', $org['name'] );

		$website = $this->nodes_of_type( $document, 'WebSite' )[0];
		$this->assertSame( self::HOME, $website['url'] );
		$this->assertSame( 'Example Co', $website['name'] );

		$breadcrumb = $this->nodes_of_type( $document, 'BreadcrumbList' )[0];
		$positions  = array_column( $breadcrumb['itemListElement'], 'position' );
		$this->assertSame( range( 1, count( $positions ) ), $positions, 'ListItem positions must be 1..n contiguous.' );
		foreach ( $breadcrumb['itemListElement'] as $item ) {
			$this->assertSame( 'ListItem', $item['@type'] );
			$this->assertNotSame( '', $item['name'] );
		}

		$faq = $this->nodes_of_type( $document, 'FAQPage' )[0];
		foreach ( $faq['mainEntity'] as $question ) {
			$this->assertSame( 'Question', $question['@type'] );
			$this->assertNotSame( '', $question['name'] );
			$this->assertNotSame( '', $question['acceptedAnswer']['text'] );
		}
	}

	// -----------------------------------------------------------------------
	// 3. SEO03 — exactly ONE FAQPage per page (named invariant).
	// -----------------------------------------------------------------------

	public function test_seo03_two_queued_faqpages_merge_into_exactly_one(): void {
		salt_schema_add( $this->faq_node( 2, 'First' ) );
		salt_schema_add( $this->faq_node( 3, 'Second' ) );

		$document = salt_schema_graph( $this->context() );
		$faqs     = $this->nodes_of_type( $document, 'FAQPage' );

		$this->assertCount( 1, $faqs, 'SEO03 invariant: never two FAQPage nodes in one @graph.' );
		$this->assertSame( self::PAGE . '#faq', $faqs[0]['@id'] );
		$this->assertCount( 5, $faqs[0]['mainEntity'] );
		$this->assertSame(
			array(
				'First question 1?',
				'First question 2?',
				'Second question 1?',
				'Second question 2?',
				'Second question 3?',
			),
			array_column( $faqs[0]['mainEntity'], 'name' ),
			'Merged mainEntity must preserve queue order.'
		);
		$this->assert_unique_ids( $document );
	}

	// -----------------------------------------------------------------------
	// 4. SEO01 — offices hang off the brand; no brand-level LocalBusiness.
	// -----------------------------------------------------------------------

	public function test_seo01_office_nodes_reference_brand_organization(): void {
		salt_schema_add(
			array(
				'@type' => 'LocalBusiness',
				'name'  => 'Example Co London',
			)
		);
		salt_schema_add(
			array(
				'@type'       => 'LocalBusiness',
				'name'        => 'Example Co Leeds',
				'salt_office' => true,
			)
		);

		$document = salt_schema_graph( $this->context() );
		$offices  = $this->nodes_of_type( $document, 'LocalBusiness' );

		$this->assertCount( 2, $offices );
		foreach ( $offices as $office ) {
			$this->assertSame(
				array( '@id' => self::HOME . '#organization' ),
				$office['parentOrganization'],
				'Every office must @id-reference the brand organization (SEO01).'
			);
			$this->assertArrayNotHasKey( 'salt_office', $office, 'Internal salt_office flag must not reach output.' );
		}

		// The brand node stays Organization — never a brand-level LocalBusiness.
		$brand = $this->node_by_id( $document, self::HOME . '#organization' );
		$this->assertSame( 'Organization', $brand['@type'] );
		$this->assertSame(
			array( self::PAGE . '#localbusiness-1', self::PAGE . '#localbusiness-2' ),
			array_column( $offices, '@id' )
		);
		$this->assert_unique_ids( $document );
	}

	public function test_seo01_flagged_subtype_is_treated_as_office(): void {
		salt_schema_add(
			array(
				'@type'       => 'Dentist',
				'name'        => 'Example Dental',
				'salt_office' => true,
			)
		);

		$document = salt_schema_graph( $this->context() );
		$office   = $this->nodes_of_type( $document, 'Dentist' )[0];

		$this->assertSame( array( '@id' => self::HOME . '#organization' ), $office['parentOrganization'] );
		$this->assertSame( self::PAGE . '#dentist-1', $office['@id'] );
		$this->assertArrayNotHasKey( 'salt_office', $office );
	}

	// -----------------------------------------------------------------------
	// 5. SEO02 — aggregateRating policing.
	// -----------------------------------------------------------------------

	public function test_seo02_self_serving_rating_on_organization_is_stripped_and_logged(): void {
		salt_schema_add(
			array(
				'@type'           => 'Organization',
				'name'            => 'Example Co',
				'aggregateRating' => array(
					'@type'       => 'AggregateRating',
					'ratingValue' => 5,
					'ratingCount' => 12,
				),
			)
		);

		$document = salt_schema_graph( $this->context() );
		$queued   = $this->node_by_id( $document, self::PAGE . '#organization-1' );

		$this->assertNotNull( $queued );
		$this->assertArrayNotHasKey( 'aggregateRating', $queued, 'Self-serving rating must be stripped (SEO02).' );
		$this->assertNotEmpty(
			array_filter( $this->logs(), static fn( string $line ): bool => str_contains( $line, 'SEO02' ) ),
			'Stripping a self-serving rating must be logged.'
		);
	}

	public function test_seo02_self_serving_rating_on_localbusiness_is_stripped(): void {
		salt_schema_add(
			array(
				'@type'           => 'LocalBusiness',
				'name'            => 'Example Co London',
				'aggregateRating' => array(
					'@type'       => 'AggregateRating',
					'ratingValue' => 4.8,
					'reviewCount' => 9,
				),
			)
		);

		$office = $this->nodes_of_type( salt_schema_graph( $this->context() ), 'LocalBusiness' )[0];
		$this->assertArrayNotHasKey( 'aggregateRating', $office );
	}

	public function test_seo02_service_rating_with_nonzero_count_is_kept(): void {
		$rating = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => 4.6,
			'ratingCount' => 5,
		);
		salt_schema_add(
			array(
				'@type'           => 'Service',
				'name'            => 'Consulting',
				'aggregateRating' => $rating,
			)
		);

		$service = $this->nodes_of_type( salt_schema_graph( $this->context() ), 'Service' )[0];
		$this->assertSame( $rating, $service['aggregateRating'] );
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function zero_count_keys(): array {
		return array(
			'ratingCount zero' => array( 'ratingCount' ),
			'reviewCount zero' => array( 'reviewCount' ),
		);
	}

	#[DataProvider( 'zero_count_keys' )]
	public function test_seo02_zero_count_rating_is_stripped( string $count_key ): void {
		salt_schema_add(
			array(
				'@type'           => 'Service',
				'name'            => 'Consulting',
				'aggregateRating' => array(
					'@type'       => 'AggregateRating',
					'ratingValue' => 4.6,
					$count_key    => 0,
				),
			)
		);

		$service = $this->nodes_of_type( salt_schema_graph( $this->context() ), 'Service' )[0];
		$this->assertArrayNotHasKey( 'aggregateRating', $service, 'Zero-count ratings must be omitted (SEO02).' );
		$this->assertNotEmpty(
			array_filter( $this->logs(), static fn( string $line ): bool => str_contains( $line, 'omit-when-zero' ) )
		);
	}

	// -----------------------------------------------------------------------
	// 6. Service + Review nesting survives assembly.
	// -----------------------------------------------------------------------

	public function test_service_review_fixture_keeps_item_reviewed_nesting(): void {
		salt_schema_add(
			array(
				'@type' => 'Service',
				'name'  => 'Consulting',
			)
		);
		salt_schema_add(
			array(
				'@type'        => 'Review',
				'reviewBody'   => 'Superb work from start to finish.',
				'author'       => array(
					'@type' => 'Person',
					'name'  => 'Jane Client',
				),
				'itemReviewed' => array(
					'@type' => 'Service',
					'name'  => 'Consulting',
				),
				'reviewRating' => array(
					'@type'       => 'Rating',
					'ratingValue' => 5,
				),
			)
		);

		$document = salt_schema_graph( $this->context() );
		$review   = $this->nodes_of_type( $document, 'Review' )[0];

		$this->assertSame( self::PAGE . '#review-1', $review['@id'] );
		$this->assertSame(
			array(
				'@type' => 'Service',
				'name'  => 'Consulting',
			),
			$review['itemReviewed'],
			'The review\'s itemReviewed nesting must survive assembly.'
		);
		$this->assertSame( 5, $review['reviewRating']['ratingValue'] );
		$this->assert_unique_ids( $document );
	}

	// -----------------------------------------------------------------------
	// 7. Dedupe by @id — first node wins, merge logged.
	// -----------------------------------------------------------------------

	public function test_dedupe_same_explicit_id_merges_first_wins_and_logs(): void {
		salt_schema_add(
			array(
				'@type'       => 'Service',
				'@id'         => self::PAGE . '#svc',
				'name'        => 'First name wins',
				'description' => 'First description.',
			)
		);
		salt_schema_add(
			array(
				'@type'      => 'Service',
				'@id'        => self::PAGE . '#svc',
				'name'       => 'Second name loses',
				'areaServed' => 'UK',
			)
		);

		$document = salt_schema_graph( $this->context() );
		$services = $this->nodes_of_type( $document, 'Service' );

		$this->assertCount( 1, $services, 'Same-@id nodes must collapse into one.' );
		$this->assertSame( 'First name wins', $services[0]['name'], 'First node wins per key.' );
		$this->assertSame( 'First description.', $services[0]['description'] );
		$this->assertSame( 'UK', $services[0]['areaServed'], 'Missing keys are added from later nodes.' );
		$this->assertNotEmpty(
			array_filter( $this->logs(), static fn( string $line ): bool => str_contains( $line, 'duplicate @id' ) ),
			'A dedupe merge must be logged.'
		);
		$this->assert_unique_ids( $document );
	}

	// -----------------------------------------------------------------------
	// 8. Archives.
	// -----------------------------------------------------------------------

	public function test_is_archive_emits_collection_page(): void {
		$document = salt_schema_graph( $this->context( array( 'is_archive' => true ) ) );
		$webpage  = $this->node_by_id( $document, self::PAGE . '#webpage' );

		$this->assertSame( 'CollectionPage', $webpage['@type'] );
		$this->assertSame( array(), $this->nodes_of_type( $document, 'WebPage' ) );
	}

	// -----------------------------------------------------------------------
	// Queue contract: add validation + reset.
	// -----------------------------------------------------------------------

	public function test_add_rejects_nodes_without_a_string_type(): void {
		salt_schema_add( array( 'name' => 'No type at all' ) );
		salt_schema_add(
			array(
				'@type' => array( 'LocalBusiness', 'Dentist' ),
				'name'  => 'Array type',
			)
		);
		salt_schema_add(
			array(
				'@type' => '',
				'name'  => 'Empty type',
			)
		);

		$document = salt_schema_graph( $this->context() );
		$this->assertCount( 4, $document['@graph'], 'Rejected nodes must not reach the graph (base nodes only).' );
		$this->assertCount(
			3,
			array_filter( $this->logs(), static fn( string $line ): bool => str_contains( $line, '@type' ) ),
			'Every rejected node must be logged.'
		);
	}

	public function test_reset_clears_the_queue(): void {
		salt_schema_add(
			array(
				'@type' => 'Service',
				'name'  => 'Doomed',
			)
		);
		salt_schema_reset();

		$document = salt_schema_graph( $this->context() );
		$this->assertSame( array(), $this->nodes_of_type( $document, 'Service' ) );
	}

	// -----------------------------------------------------------------------
	// Render (thin layer): output shape + skip rule + WP context.
	// -----------------------------------------------------------------------

	public function test_render_echoes_one_script_tag_with_valid_json(): void {
		salt_schema_add( $this->faq_node( 1, 'Render' ) );

		ob_start();
		salt_schema_render( $this->context() );
		$html = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $html, '<script type="application/ld+json">' ) );
		$this->assertMatchesRegularExpression( '#^<script type="application/ld\+json">(.*)</script>\n$#s', $html );

		preg_match( '#^<script type="application/ld\+json">(.*)</script>\n$#s', $html, $matches );
		$decoded = json_decode( $matches[1], true );
		$this->assertIsArray( $decoded );
		$this->assertSame( 'https://schema.org', $decoded['@context'] );
	}

	public function test_render_skips_entirely_without_org_and_queue(): void {
		ob_start();
		salt_schema_render( $this->context( array( 'org' => array() ) ) );
		$this->assertSame( '', (string) ob_get_clean(), 'No organization and no queued nodes => no output at all.' );
	}

	public function test_render_defuses_script_breakout_without_changing_values(): void {
		salt_schema_add(
			array(
				'@type' => 'Service',
				'name'  => 'Bad</script><script>alert(1)</script>',
			)
		);

		ob_start();
		salt_schema_render( $this->context() );
		$html = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $html, '</script>' ), 'Only the wrapper close tag may appear literally.' );

		preg_match( '#^<script type="application/ld\+json">(.*)</script>\n$#s', $html, $matches );
		$decoded = json_decode( $matches[1], true );
		$this->assertIsArray( $decoded, 'Defused JSON must stay valid.' );
		$service = array_values( array_filter( $decoded['@graph'], static fn( array $n ): bool => 'Service' === $n['@type'] ) )[0];
		$this->assertSame( 'Bad</script><script>alert(1)</script>', $service['name'], 'Defusing must not change decoded values.' );
	}

	public function test_wp_context_builder_reads_options_and_conditionals(): void {
		$GLOBALS['salt_test_fields']['option'] = array(
			'org_name'       => 'Option Org',
			'org_legal_name' => 'Option Org Ltd',
			'org_logo'       => array( 'url' => 'https://example.test/media/logo.svg' ),
			'org_same_as'    => array( 'https://www.linkedin.com/company/option', '' ),
			'org_phone'      => '+44 20 7946 0000',
			'org_email'      => 'hi@example.test',
		);

		$context = salt_schema_wp_context();

		$this->assertSame( self::HOME . '/', $context['home_url'] );
		$this->assertSame( self::PAGE, $context['page_url'], 'Singular pages use the permalink.' );
		$this->assertSame( 'Services — Example Co', $context['page_title'] );
		$this->assertSame( 'Example Co', $context['site_name'] );
		$this->assertFalse( $context['is_archive'] );
		$this->assertSame(
			array(
				'name'       => 'Option Org',
				'legal_name' => 'Option Org Ltd',
				'logo'       => 'https://example.test/media/logo.svg',
				'same_as'    => array( 'https://www.linkedin.com/company/option' ),
				'phone'      => '+44 20 7946 0000',
				'email'      => 'hi@example.test',
			),
			$context['org']
		);
	}

	public function test_wp_context_org_name_falls_back_to_blogname_and_filter_applies(): void {
		add_filter(
			'salt_schema_context',
			static function ( array $context ): array {
				$context['breadcrumbs'] = array(
					array(
						'name' => 'Home',
						'url'  => 'https://example.test/',
					),
				);
				return $context;
			},
			20
		);

		$context = salt_schema_wp_context();

		$this->assertSame( 'Example Co', $context['org']['name'], 'Org name falls back to the site name.' );
		$this->assertCount( 1, $context['breadcrumbs'], 'salt_schema_context filter must apply.' );
	}

	public function test_wp_context_non_singular_uses_request_uri(): void {
		$GLOBALS['salt_test_schema']['is_singular'] = false;
		$GLOBALS['salt_test_schema']['is_archive']  = true;
		$_SERVER['REQUEST_URI']                     = '/services/';

		$context = salt_schema_wp_context();
		unset( $_SERVER['REQUEST_URI'] );

		$this->assertSame( self::PAGE, $context['page_url'] );
		$this->assertTrue( $context['is_archive'] );
	}
}
