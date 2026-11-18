<?php
/**
 * FAQ slice unit tests: CPT posture (SEC09), field group, layout shape,
 * section 0/1/N states (COMPL02), sanitisation, and the SEO03 FAQPage
 * invariant (two section instances merge into ONE node).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/faq.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/faq.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/cpt-faq.php';

final class FaqSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		$GLOBALS['salt_test_post_types'] = array();
		$GLOBALS['salt_test_taxonomies'] = array();
		$GLOBALS['salt_test_acf_groups'] = array();
	}

	/**
	 * Seed published faq posts + their answer fields.
	 *
	 * @param array<int,array{id:int,title:string,answer:mixed}> $faqs FAQ fixtures.
	 * @return void
	 */
	private function seed_faqs( array $faqs ): void {
		$posts = array();
		foreach ( $faqs as $i => $faq ) {
			$posts[] = array(
				'ID'         => $faq['id'],
				'post_type'  => 'faq',
				'post_title' => $faq['title'],
				'menu_order' => $i,
			);

			$GLOBALS['salt_test_fields'][ (string) $faq['id'] ]['answer'] = $faq['answer'];
		}
		$this->seed_posts( $posts );
	}

	/**
	 * Render ONE faq section row.
	 *
	 * @param array<string,mixed> $fields  Row sub-field fixtures.
	 * @param array<string,mixed> $context Loop context.
	 * @return string
	 */
	private function render_faq_section( array $fields = array(), array $context = array(
		'index'      => 0,
		'is_primary' => true,
	) ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'faq',
					'fields' => $fields,
				),
			)
		);
		$this->assertTrue( have_rows( 'sections' ) );
		the_row();

		ob_start();
		salt_render_section( 'faq', $context );
		return (string) ob_get_clean();
	}

	/**
	 * The FAQPage nodes present in the assembled graph.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function faq_nodes(): array {
		$graph = salt_schema_graph(
			array(
				'home_url' => 'https://example.test',
				'page_url' => 'https://example.test/p',
			)
		);

		return array_values(
			array_filter(
				$graph['@graph'],
				static fn( array $node ): bool => 'FAQPage' === ( $node['@type'] ?? '' )
			)
		);
	}

	// -----------------------------------------------------------------
	// CPT registration (SEC09 display-only posture).
	// -----------------------------------------------------------------

	public function test_cpt_registers_with_display_only_posture(): void {
		salt_register_cpt_faq();

		$this->assertArrayHasKey( 'faq', $GLOBALS['salt_test_post_types'] );
		$args = $GLOBALS['salt_test_post_types']['faq'];

		$this->assertFalse( $args['public'] );
		$this->assertTrue( $args['show_ui'] );
		$this->assertFalse( $args['publicly_queryable'] );
		$this->assertTrue( $args['exclude_from_search'] );
		$this->assertFalse( $args['show_in_rest'] );
		$this->assertFalse( $args['has_archive'] );

		$this->assertArrayHasKey( 'faq_category', $GLOBALS['salt_test_taxonomies'] );
		$taxonomy = $GLOBALS['salt_test_taxonomies']['faq_category'];
		// Key spelling varies by which slice shim defined register_taxonomy.
		$this->assertSame( array( 'faq' ), $taxonomy['object_type'] ?? $taxonomy['object_types'] );
		$this->assertFalse( $taxonomy['args']['public'] );
		$this->assertFalse( $taxonomy['args']['show_in_rest'] );
	}

	public function test_cpt_not_registered_when_module_disabled(): void {
		$GLOBALS['salt_test_fields']['option']['module_faq'] = false;

		salt_register_cpt_faq();

		$this->assertArrayNotHasKey( 'faq', $GLOBALS['salt_test_post_types'] );
		$this->assertArrayNotHasKey( 'faq_category', $GLOBALS['salt_test_taxonomies'] );
	}

	// -----------------------------------------------------------------
	// Field group + layout shape (DATA02).
	// -----------------------------------------------------------------

	public function test_faq_field_group_registers_the_answer_wysiwyg(): void {
		salt_register_faq_fields();

		$groups = array_column( $GLOBALS['salt_test_acf_groups'], null, 'key' );
		$this->assertArrayHasKey( 'group_salt_faq', $groups );

		$fields = array_column( $groups['group_salt_faq']['fields'], null, 'name' );
		$this->assertArrayHasKey( 'answer', $fields );
		$this->assertSame( 'field_salt_faq_answer', $fields['answer']['key'] );
		$this->assertSame( 'wysiwyg', $fields['answer']['type'] );
	}

	public function test_layout_is_generated_from_the_contract(): void {
		$layout = salt_test_layout( 'faq' );

		$this->assertSame( 'layout_salt_faq', $layout['key'] );
		$this->assertSame( array( 'eyebrow', 'heading', 'intro', 'query', 'structuredData', '', 'settings' ), array_column( $layout['sub_fields'], 'name' ) );

		$fields = array_column( $layout['sub_fields'], null, 'name' );
		$this->assertSame( array( 'mode', 'categories', 'items', 'order', 'count' ), array_column( $fields['query']['sub_fields'], 'name' ), 'The contract\'s collection query.' );
		$this->assertSame( 'faq_category', array_column( $fields['query']['sub_fields'], null, 'name' )['categories']['taxonomy'] );
		$this->assertSame( 1, $fields['structuredData']['default_value'], 'Schema toggle defaults ON (SEO03).' );
		$this->assertSame( 'field_salt_faq_structured_data', $fields['structuredData']['key'] );
	}

	// -----------------------------------------------------------------
	// Section states (COMPL02).
	// -----------------------------------------------------------------

	public function test_zero_items_renders_nothing_and_queues_no_schema(): void {
		$html = $this->render_faq_section( array( 'heading' => 'FAQs' ) );

		$this->assertSame( '', $html );
		$this->assertSame( array(), $this->faq_nodes() );
	}

	public function test_one_item_renders_a_static_details_accordion(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'What is salt?',
					'answer' => '<p>A parent theme.</p>',
				),
			)
		);

		$html = $this->render_faq_section(
			array(
				'heading' => 'FAQs',
				'eyebrow' => 'Help',
			)
		);

		$this->assertSame( 1, preg_match_all( '/<details[\s>]/', $html ) );
		$this->assertStringContainsString( 'What is salt?', $html );
		$this->assertStringContainsString( 'A parent theme.', $html );
		$this->assertStringContainsString( '<h1', $html, 'Primary section heading claims the H1.' );
		$this->assertStringContainsString( 'Help', $html );
		$this->assertStringNotContainsString( '<script', $html, 'Zero JS — native <details> only.' );
	}

	public function test_n_items_render_in_default_menu_order(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'First question?',
					'answer' => '<p>One.</p>',
				),
				array(
					'id'     => 2,
					'title'  => 'Second question?',
					'answer' => '<p>Two.</p>',
				),
				array(
					'id'     => 3,
					'title'  => 'Third question?',
					'answer' => '<p>Three.</p>',
				),
			)
		);

		$html = $this->render_faq_section( array( 'heading' => 'FAQs' ) );

		$this->assertSame( 3, preg_match_all( '/<details[\s>]/', $html ) );
		$this->assertLessThan( strpos( $html, 'Second question?' ), strpos( $html, 'First question?' ) );
		$this->assertLessThan( strpos( $html, 'Third question?' ), strpos( $html, 'Second question?' ) );
	}

	public function test_malformed_rows_are_skipped_and_logged(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'Good question?',
					'answer' => '<p>Good answer.</p>',
				),
				array(
					'id'     => 2,
					'title'  => '',
					'answer' => '<p>Answer without a question.</p>',
				),
				array(
					'id'     => 3,
					'title'  => 'Question without an answer?',
					'answer' => null,
				),
			)
		);

		$html = $this->render_faq_section( array( 'heading' => 'FAQs' ) );

		$this->assertSame( 1, preg_match_all( '/<details[\s>]/', $html ), 'Only the intact row renders.' );
		$this->assertStringContainsString( 'Good question?', $html );
		$this->assertStringNotContainsString( 'Question without an answer?', $html );

		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'post 2 skipped', $logs );
		$this->assertStringContainsString( 'post 3 skipped', $logs );
	}

	public function test_all_rows_malformed_renders_nothing(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 2,
					'title'  => '',
					'answer' => '<p>Orphan.</p>',
				),
			)
		);

		$this->assertSame( '', $this->render_faq_section( array( 'heading' => 'FAQs' ) ) );
	}

	public function test_module_disabled_renders_nothing(): void {
		$GLOBALS['salt_test_fields']['option']['module_faq'] = false;
		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'Hidden?',
					'answer' => '<p>Yes.</p>',
				),
			)
		);

		$this->assertSame( '', $this->render_faq_section( array( 'heading' => 'FAQs' ) ) );
	}

	// -----------------------------------------------------------------
	// Sanitisation (§16a).
	// -----------------------------------------------------------------

	public function test_answers_are_sanitised_in_markup_and_schema(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'Is it safe?',
					'answer' => '<p>Yes<script>alert(1)</script>, with <strong>bold</strong>.</p>',
				),
			)
		);

		$html = $this->render_faq_section( array( 'heading' => 'FAQs' ) );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '<strong>bold</strong>', $html );

		$nodes = $this->faq_nodes();
		$this->assertCount( 1, $nodes );
		$answer_text = (string) $nodes[0]['mainEntity'][0]['acceptedAnswer']['text'];
		$this->assertStringNotContainsString( '<script', $answer_text, 'Schema text carries the SAME sanitised HTML as the markup.' );
		$this->assertStringContainsString( '<strong>bold</strong>', $answer_text );
	}

	// -----------------------------------------------------------------
	// Schema (SEO03).
	// -----------------------------------------------------------------

	public function test_schema_toggle_on_queues_one_faqpage_node(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'What is salt?',
					'answer' => '<p>A parent theme.</p>',
				),
				array(
					'id'     => 2,
					'title'  => 'Is it fast?',
					'answer' => '<p>Yes.</p>',
				),
			)
		);

		$this->render_faq_section( array( 'heading' => 'FAQs' ) );

		$nodes = $this->faq_nodes();
		$this->assertCount( 1, $nodes );
		$this->assertSame( 'https://example.test/p#faq', $nodes[0]['@id'] );
		$this->assertCount( 2, $nodes[0]['mainEntity'] );
		$this->assertSame( 'Question', $nodes[0]['mainEntity'][0]['@type'] );
		$this->assertSame( 'What is salt?', $nodes[0]['mainEntity'][0]['name'] );
		$this->assertSame( 'Answer', $nodes[0]['mainEntity'][0]['acceptedAnswer']['@type'] );
		$this->assertSame( '<p>A parent theme.</p>', $nodes[0]['mainEntity'][0]['acceptedAnswer']['text'] );
	}

	public function test_schema_toggle_off_queues_no_node_but_still_renders(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'What is salt?',
					'answer' => '<p>A parent theme.</p>',
				),
			)
		);

		$html = $this->render_faq_section(
			array(
				'heading'        => 'FAQs',
				'structuredData' => 0,
			)
		);

		$this->assertStringContainsString( 'What is salt?', $html );
		$this->assertSame( array(), $this->faq_nodes() );
	}

	public function test_two_section_instances_merge_into_exactly_one_faqpage(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'General question?',
					'answer' => '<p>General answer.</p>',
				),
				array(
					'id'     => 2,
					'title'  => 'Billing question?',
					'answer' => '<p>Billing answer.</p>',
				),
			)
		);

		// Two faq rows on one page, each hand-picking a different post.
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'faq',
					'fields' => array(
						'heading' => 'General FAQs',
						'query'   => array(
							'mode'  => 'manual',
							'items' => array( 1 ),
						),
					),
				),
				array(
					'layout' => 'faq',
					'fields' => array(
						'heading' => 'Billing FAQs',
						'query'   => array(
							'mode'  => 'manual',
							'items' => array( 2 ),
						),
					),
				),
			)
		);

		ob_start();
		$index = 0;
		while ( have_rows( 'sections' ) ) {
			the_row();
			salt_render_section(
				'faq',
				array(
					'index'      => $index,
					'is_primary' => 0 === $index,
				)
			);
			++$index;
		}
		$html = (string) ob_get_clean();

		$this->assertSame( 2, preg_match_all( '/<details[\s>]/', $html ) );
		$this->assertStringContainsString( 'name="faq-0"', $html, 'Instances keep separate <details name> groups.' );
		$this->assertStringContainsString( 'name="faq-1"', $html );

		$nodes = $this->faq_nodes();
		$this->assertCount( 1, $nodes, 'SEO03: exactly ONE FAQPage per page.' );
		$this->assertSame( 'https://example.test/p#faq', $nodes[0]['@id'] );
		$this->assertSame(
			array( 'General question?', 'Billing question?' ),
			array_column( $nodes[0]['mainEntity'], 'name' ),
			'mainEntity merges both instances, in render order.'
		);
	}

	// -----------------------------------------------------------------
	// Resolver (logic/view split, ID-3587).
	// -----------------------------------------------------------------

	/**
	 * Seed one faq FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return array<string,mixed>|null
	 */
	private function resolve_faq( array $fields = array() ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'faq',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'faq' );
		$this->assertSame( 'salt_resolve_section_faq', $resolver );

		return $resolver(
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
	}

	public function test_resolver_returns_null_for_zero_items_and_a_disabled_module(): void {
		$this->assertNull( $this->resolve_faq( array( 'heading' => 'FAQs' ) ) );

		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'Hidden?',
					'answer' => '<p>Yes.</p>',
				),
			)
		);
		$GLOBALS['salt_test_fields']['option']['module_faq'] = false;

		$this->assertNull( $this->resolve_faq( array( 'heading' => 'FAQs' ) ) );
	}

	public function test_resolver_items_use_the_contract_faq_item_names(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 7,
					'title'  => '  Is it safe?  ',
					'answer' => '<p>Yes<script>alert(1)</script>.</p>',
				),
				array(
					'id'     => 8,
					'title'  => '',
					'answer' => '<p>Orphan.</p>',
				),
			)
		);

		$resolved = $this->resolve_faq( array( 'heading' => 'FAQs' ) );

		$this->assertSame( array( 'eyebrow', 'heading', 'items', 'structuredData' ), array_keys( $resolved['props'] ) );
		$this->assertNull( $resolved['props']['eyebrow'] );
		$this->assertSame( 'FAQs', $resolved['props']['heading'] );
		$this->assertTrue( $resolved['props']['structuredData'], 'Unsaved toggle reads as on.' );

		$this->assertCount( 1, $resolved['props']['items'], 'The broken row is skipped by the resolver.' );
		$item = $resolved['props']['items'][0];
		$this->assertSame( array( 'id', 'question', 'answer' ), array_keys( $item ) );
		$this->assertSame( '7', $item['id'] );
		$this->assertSame( 'Is it safe?', $item['question'] );
		$this->assertStringNotContainsString( '<script', $item['answer'], 'Answers arrive sanitised.' );
		$this->assertStringContainsString( 'post 8 skipped', implode( ' ', $this->logs() ) );

		$this->assertSame( 'faq', $resolved['section']['name'] );
		$this->assertTrue( $resolved['section']['has_heading'] );
	}

	public function test_resolver_queues_the_schema_node_itself(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'What is salt?',
					'answer' => '<p>A parent theme.</p>',
				),
			)
		);

		$this->resolve_faq( array( 'heading' => 'FAQs' ) );

		$this->assertCount( 1, $this->faq_nodes(), 'Schema is logic: queued without the view ever running.' );
	}

	public function test_resolver_schema_toggle_off_reports_structured_data_false(): void {
		$this->seed_faqs(
			array(
				array(
					'id'     => 1,
					'title'  => 'What is salt?',
					'answer' => '<p>A parent theme.</p>',
				),
			)
		);

		$resolved = $this->resolve_faq( array( 'structuredData' => 0 ) );

		$this->assertFalse( $resolved['props']['structuredData'] );
		$this->assertFalse( $resolved['section']['has_heading'] );
		$this->assertSame( array(), $this->faq_nodes() );
	}
}
