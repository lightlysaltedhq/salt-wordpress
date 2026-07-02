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

	public function test_layout_shape_content_then_query_controls_then_wrapper(): void {
		$layout = require dirname( __DIR__, 2 ) . '/inc/fields/layouts/faq.php';

		$this->assertSame( 'faq', $layout['name'] );
		$this->assertSame( 'layout_salt_faq', $layout['key'] );

		$names = array_column( $layout['sub_fields'], 'name' );
		$this->assertContains( 'eyebrow', $names );
		$this->assertContains( 'heading', $names );
		$this->assertContains( 'schema_toggle', $names );
		$this->assertContains( 'source', $names, 'Query controls embedded (§10a).' );
		$this->assertContains( 'taxonomy_terms', $names, 'faq_category filter present.' );
		$this->assertSame( 'section_divider', end( $names ), 'Wrapper fields come LAST.' );

		$fields = array_column( $layout['sub_fields'], null, 'name' );
		$this->assertSame( 1, $fields['schema_toggle']['default_value'], 'Schema toggle defaults ON (SEO03).' );
		$this->assertSame( 'field_salt_faq_schema_toggle', $fields['schema_toggle']['key'] );
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
				'heading'       => 'FAQs',
				'schema_toggle' => 0,
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
						'source'  => 'manual',
						'manual'  => array( 1 ),
					),
				),
				array(
					'layout' => 'faq',
					'fields' => array(
						'heading' => 'Billing FAQs',
						'source'  => 'manual',
						'manual'  => array( 2 ),
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
}
