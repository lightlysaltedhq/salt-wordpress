<?php
/**
 * Content allow-list + TinyMCE parity tests (spec §16a, TEST01/EDIT01).
 *
 * Table-driven via the wp_kses DOM shim in tests/wp-shims.php: disallowed
 * elements are unwrapped (inner text may survive — that matches kses),
 * disallowed attributes are dropped, and disallowed protocols in href are
 * removed.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

final class SanitizationTest extends SaltTestCase {

	// -----------------------------------------------------------------------
	// 1. Forbidden markup is stripped.
	// -----------------------------------------------------------------------

	/**
	 * Forbidden fixtures: input HTML + the tag/attr fragments that must be gone.
	 *
	 * @return array<string,array{string,string[]}>
	 */
	public static function forbidden_provider(): array {
		return array(
			'script tag'      => array( '<p>ok</p><script>alert(1)</script>', array( '<script' ) ),
			'iframe tag'      => array( '<iframe src="https://evil.test/"></iframe>', array( '<iframe', 'src=' ) ),
			'style attr on p' => array( '<p style="color:red">text</p>', array( 'style=' ) ),
			'class attr'      => array( '<p class="fancy">text</p>', array( 'class=' ) ),
			'id attr'         => array( '<h3 id="anchor">text</h3>', array( 'id=' ) ),
			'span with style' => array( '<p><span style="font-size:40px">big</span></p>', array( '<span', 'style=' ) ),
			'font tag'        => array( '<p><font color="red">loud</font></p>', array( '<font', 'color=' ) ),
			'h1 tag'          => array( '<h1>page title</h1>', array( '<h1' ) ),
			'h6 with handlers' => array( '<h6 onclick="x()" style="a" data-x="1">t</h6>', array( 'onclick', 'style=', 'data-x' ) ),
			'h2 with class'    => array( '<h2 class="c">t</h2>', array( 'class=' ) ),
		);
	}

	/**
	 * @param string   $input  Raw HTML.
	 * @param string[] $absent Fragments that must not survive.
	 */
	#[DataProvider( 'forbidden_provider' )]
	public function test_forbidden_markup_is_stripped( string $input, array $absent ): void {
		$output = salt_sanitize_html( $input );

		foreach ( $absent as $fragment ) {
			$this->assertStringNotContainsString( $fragment, $output );
		}
	}

	// -----------------------------------------------------------------------
	// 2. Permitted markup survives.
	// -----------------------------------------------------------------------

	/**
	 * Permitted fixtures: input HTML that must round-trip intact.
	 *
	 * @return array<string,array{string,string[]}>
	 */
	public static function permitted_provider(): array {
		return array(
			'paragraph'  => array( '<p>hello</p>', array( '<p>hello</p>' ) ),
			'strong'     => array( '<p><strong>bold</strong></p>', array( '<strong>bold</strong>' ) ),
			'em'         => array( '<p><em>italic</em></p>', array( '<em>italic</em>' ) ),
			'ul li'      => array( '<ul><li>one</li><li>two</li></ul>', array( '<ul>', '<li>one</li>', '<li>two</li>' ) ),
			'ol li'      => array( '<ol><li>first</li></ol>', array( '<ol>', '<li>first</li>' ) ),
			'blockquote' => array( '<blockquote><p>quote</p></blockquote>', array( '<blockquote>', '<p>quote</p>' ) ),
			'h2'         => array( '<h2>section title</h2>', array( '<h2>section title</h2>' ) ),
			'h3'         => array( '<h3>heading</h3>', array( '<h3>heading</h3>' ) ),
			'h4'         => array( '<h4>subheading</h4>', array( '<h4>subheading</h4>' ) ),
			'h5'         => array( '<h5>minor</h5>', array( '<h5>minor</h5>' ) ),
			'h6'         => array( '<h6>least</h6>', array( '<h6>least</h6>' ) ),
			'br'         => array( '<p>line<br>break</p>', array( '<br' ) ),
			'code'       => array( '<p><code>salt_log()</code></p>', array( '<code>salt_log()</code>' ) ),
		);
	}

	/**
	 * @param string   $input   Raw HTML.
	 * @param string[] $present Fragments that must survive.
	 */
	#[DataProvider( 'permitted_provider' )]
	public function test_permitted_markup_survives( string $input, array $present ): void {
		$output = salt_sanitize_html( $input );

		foreach ( $present as $fragment ) {
			$this->assertStringContainsString( $fragment, $output );
		}
	}

	public function test_anchor_keeps_all_four_allowed_attributes(): void {
		$input  = '<p><a href="https://example.test/page" title="Example" rel="noopener" target="_blank">link</a></p>';
		$output = salt_sanitize_html( $input );

		$this->assertStringContainsString( '<a', $output );
		$this->assertStringContainsString( 'href="https://example.test/page"', $output );
		$this->assertStringContainsString( 'title="Example"', $output );
		$this->assertStringContainsString( 'rel="noopener"', $output );
		$this->assertStringContainsString( 'target="_blank"', $output );
		$this->assertStringContainsString( '>link</a>', $output );
	}

	// -----------------------------------------------------------------------
	// 3. Protocol filtering.
	// -----------------------------------------------------------------------

	public function test_javascript_href_is_removed(): void {
		$output = salt_sanitize_html( '<p><a href="javascript:alert(1)">click</a></p>' );

		$this->assertStringNotContainsString( 'javascript:', $output );
		$this->assertStringNotContainsString( 'href', $output );
		$this->assertStringContainsString( 'click', $output );
	}

	public function test_safe_protocols_survive(): void {
		$this->assertStringContainsString(
			'href="mailto:ollie@example.test"',
			salt_sanitize_html( '<p><a href="mailto:ollie@example.test">mail</a></p>' )
		);
		$this->assertStringContainsString(
			'href="tel:+441234567890"',
			salt_sanitize_html( '<p><a href="tel:+441234567890">call</a></p>' )
		);
	}

	// -----------------------------------------------------------------------
	// 4. Structural regression lock on the allow-list itself.
	// -----------------------------------------------------------------------

	public function test_allow_list_has_exactly_the_permitted_tags(): void {
		$this->assertEqualsCanonicalizing(
			array( 'p', 'a', 'strong', 'em', 'ul', 'ol', 'li', 'blockquote', 'h2', 'h3', 'h4', 'h5', 'h6', 'br', 'code' ),
			array_keys( salt_allowed_html() )
		);
	}

	public function test_no_tag_allows_class_id_or_style(): void {
		foreach ( salt_allowed_html() as $tag => $attrs ) {
			foreach ( array( 'class', 'id', 'style' ) as $forbidden ) {
				$this->assertArrayNotHasKey( $forbidden, $attrs, "'{$tag}' must not allow '{$forbidden}'" );
			}
		}
	}

	// -----------------------------------------------------------------------
	// 5. TinyMCE parity (EDIT01).
	// -----------------------------------------------------------------------

	public function test_every_allowed_tag_appears_in_mce_valid_elements(): void {
		$valid    = salt_mce_valid_elements();
		$elements = explode( ',', $valid );
		$bare     = array_map(
			static fn( string $element ): string => explode( '/', (string) preg_replace( '/\[.*\]$/', '', $element ) )[0],
			$elements
		);

		foreach ( array_keys( salt_allowed_html() ) as $tag ) {
			$this->assertContains( $tag, $bare, "'{$tag}' missing from valid_elements '{$valid}'" );
		}
	}

	public function test_every_anchor_attr_appears_in_mce_bracket_list(): void {
		$valid = salt_mce_valid_elements();

		$this->assertSame( 1, preg_match( '/(?:^|,)a\[([^\]]*)\]/', $valid, $m ), "no a[...] element in '{$valid}'" );
		$bracket_attrs = explode( '|', $m[1] );

		foreach ( array_keys( salt_allowed_html()['a'] ) as $attr ) {
			$this->assertContains( $attr, $bracket_attrs );
		}
	}

	public function test_invalid_elements_covers_the_dangerous_tags(): void {
		$invalid = explode( ',', salt_tinymce_config( array() )['invalid_elements'] );

		foreach ( array( 'script', 'iframe', 'span', 'font', 'h1' ) as $tag ) {
			$this->assertContains( $tag, $invalid );
		}
	}

	public function test_mce_buttons_exact_and_no_formatting_escape_hatches(): void {
		$buttons = salt_mce_buttons();

		$this->assertSame(
			array( 'formatselect', 'bold', 'italic', 'blockquote', 'bullist', 'numlist', 'link', 'unlink', 'removeformat', 'undo', 'redo' ),
			$buttons
		);
		$this->assertNotContains( 'forecolor', $buttons );
		$this->assertNotContains( 'fontsizeselect', $buttons );

		$this->assertSame( array(), salt_mce_buttons_2() );
	}

	public function test_configure_editor_registers_the_filters(): void {
		salt_configure_editor();

		$init = apply_filters( 'tiny_mce_before_init', array() );
		$this->assertSame( salt_mce_valid_elements(), $init['valid_elements'] );

		$this->assertSame( salt_mce_buttons(), apply_filters( 'mce_buttons', array() ) );
		$this->assertSame( array(), apply_filters( 'mce_buttons_2', array( 'forecolor' ) ) );
	}

	// -----------------------------------------------------------------------
	// 6. Config is additive: unrelated init keys survive.
	// -----------------------------------------------------------------------

	public function test_tinymce_config_preserves_unrelated_init_keys(): void {
		$init = salt_tinymce_config(
			array(
				'body_class' => 'salt-editor',
				'menubar'    => false,
			)
		);

		$this->assertSame( 'salt-editor', $init['body_class'] );
		$this->assertFalse( $init['menubar'] );
		$this->assertSame( 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6', $init['block_formats'] );
		$this->assertSame( 'none', $init['paste_webkit_styles'] );
		$this->assertTrue( $init['paste_remove_styles_if_webkit'] );
	}
	// -----------------------------------------------------------------------
	// 7. Each rich-text field allows only its contract list (rc.7 review).
	// -----------------------------------------------------------------------

	public function test_heading_entries_allow_no_attributes(): void {
		foreach ( array( 'h2', 'h3', 'h4', 'h5', 'h6' ) as $tag ) {
			$this->assertSame( array(), salt_allowed_html()[ $tag ], "{$tag} must allow no attribute." );
		}
	}

	public function test_every_contract_kind_maps_into_the_superset_and_covers_it(): void {
		$schema = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/node_modules/@lightlysaltedhq/salt-contract/schema/field-definition.schema.json' ), true );
		$kinds  = $schema['$defs']['richTextField']['properties']['allowed']['items']['enum'];

		$this->assertEqualsCanonicalizing( $kinds, array_keys( salt_rich_text_kinds() ), 'One mapping per contract element kind.' );
		$this->assertEqualsCanonicalizing( array_keys( salt_allowed_html() ), array_keys( salt_allowed_html_for( $kinds ) ), 'Every kind together is the superset, no more.' );
	}

	public function test_narrowing_cannot_widen_the_superset(): void {
		$this->assertSame( array(), salt_allowed_html_for( array( 'heading-1', 'script', 'table' ) ) );
		$this->assertSame( array( 'h2' => array() ), salt_allowed_html_for( array( 'heading-2', 'heading-1' ) ) );
	}

	/**
	 * Every generated rich-text field, keyed by field key, with the list its
	 * JSON carries.
	 *
	 * @return array<string,string[]>
	 */
	private static function generated_lists(): array {
		$lists = array();
		$walk  = static function ( array $fields ) use ( &$walk, &$lists ): void {
			foreach ( $fields as $field ) {
				if ( 'wysiwyg' === ( $field['type'] ?? '' ) ) {
					$lists[ $field['key'] ] = $field['salt']['allowed'];
				}
				$walk( $field['sub_fields'] ?? array() );
				foreach ( $field['layouts'] ?? array() as $layout ) {
					$walk( $layout['sub_fields'] );
				}
			}
		};
		foreach ( array( 'sections', 'collections' ) as $file ) {
			foreach ( json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . "/inc/fields/generated/{$file}.json" ), true ) as $group ) {
				$walk( $group['fields'] );
			}
		}
		return $lists;
	}

	public function test_field_lists_come_from_the_generated_json(): void {
		$lists = self::generated_lists();

		$this->assertCount( 10, $lists, 'Every generated rich-text field.' );
		foreach ( $lists as $key => $allowed ) {
			$this->assertSame( $allowed, salt_field_allowed( $key, true ), $key );
		}
		// A post's layout draws through the same resolvers as a page's, so
		// its fields' lists must be the page sections' own.
		foreach ( array( 'rich_text_body', 'media_text_rows_content', 'call_to_action_body' ) as $path ) {
			$this->assertSame( $lists[ "field_salt_{$path}" ], $lists[ "field_salt_collection_posts_layout_{$path}" ], $path );
		}
	}

	public function test_an_unknown_field_fails_closed_to_paragraphs(): void {
		$this->assertSame( array( 'paragraph' ), salt_field_allowed( 'field_salt_nope', true ) );
		$this->assertStringContainsString( 'field_salt_nope', implode( "\n", $GLOBALS['salt_test_logs'] ?? array() ) );
	}

	public function test_the_service_intro_list_is_the_pinned_contracts(): void {
		$services = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/node_modules/@lightlysaltedhq/salt-contract/contract/collections/services.json' ), true );
		$intro    = array_values( array_filter( $services['fields'], static fn( array $field ): bool => 'intro' === $field['name'] ) )[0];

		$this->assertSame( $intro['allowed'], SALT_SERVICE_INTRO_ALLOWED );
		$this->assertSame( 'body', $intro['platforms']['wordpress']['native'] ?? null, 'The intro is the post body, so no generated field carries its list.' );
	}

	/**
	 * Field, input, a fragment that must survive, a fragment that must not.
	 *
	 * @return array<string,array{string,string,string,string}>
	 */
	public static function per_field_provider(): array {
		$rich = 'field_salt_rich_text_body';
		$cta  = 'field_salt_call_to_action_body';
		$feat = 'field_salt_features_items_text';
		$faq  = 'field_salt_collection_faqs_answer';
		$case = 'field_salt_collection_case_studies_challenge';
		return array(
			'h2 survives in the rich-text body'   => array( $rich, '<h2>Title</h2>', '<h2>Title</h2>', '<p>' ),
			'h6 survives in the rich-text body'   => array( $rich, '<h6>Small</h6>', '<h6>Small</h6>', '<p>' ),
			'h2 stripped in the CTA body'         => array( $cta, '<h2>Title</h2>', 'Title', '<h2' ),
			'h5 stripped in the CTA body'         => array( $cta, '<h5>Small</h5>', 'Small', '<h5' ),
			'h3 survives in the CTA body'         => array( $cta, '<h3>Sub</h3>', '<h3>Sub</h3>', '<p>' ),
			'h2 stripped in media-text content'   => array( 'field_salt_media_text_rows_content', '<h2>Title</h2>', 'Title', '<h2' ),
			'h3 stripped in a features item'      => array( $feat, '<h3>Sub</h3>', 'Sub', '<h3' ),
			'h2 stripped in a features item'      => array( $feat, '<h2>Card</h2>', 'Card', '<h2' ),
			'blockquote survives in features'     => array( $feat, '<blockquote><p>Q</p></blockquote>', '<blockquote>', '<h' ),
			'h3 stripped in an FAQ answer'        => array( $faq, '<h3>Sub</h3>', 'Sub', '<h3' ),
			'blockquote stripped in an FAQ answer' => array( $faq, '<blockquote><p>Q</p></blockquote>', '<p>Q</p>', '<blockquote' ),
			'code stripped in an FAQ answer'      => array( $faq, '<p><code>x()</code></p>', 'x()', '<code' ),
			'a link survives in an FAQ answer'    => array( $faq, '<p><a href="https://e.test/">e</a></p>', '<a href="https://e.test/">e</a>', '<h' ),
			'h3 survives in a case-study block'   => array( $case, '<h3>Sub</h3>', '<h3>Sub</h3>', '<p>' ),
			'h2 stripped in a case-study block'   => array( $case, '<h2>Title</h2>', 'Title', '<h2' ),
			'code stripped in a case-study block' => array( $case, '<p><code>x()</code></p>', 'x()', '<code' ),
			'blockquote stripped in a case study' => array( 'field_salt_collection_case_studies_solution', '<blockquote><p>Q</p></blockquote>', '<p>Q</p>', '<blockquote' ),
		);
	}

	#[DataProvider( 'per_field_provider' )]
	public function test_each_field_keeps_only_its_list( string $key, string $input, string $kept, string $gone ): void {
		$out = salt_sanitize_html( $input, salt_field_allowed( $key, true ) );

		$this->assertStringContainsString( $kept, $out );
		$this->assertStringNotContainsString( $gone, $out );
	}

	public function test_the_service_intro_keeps_no_heading(): void {
		$out = salt_sanitize_html( '<h2>A</h2><h3>B</h3><p><strong>C</strong></p>', SALT_SERVICE_INTRO_ALLOWED );

		$this->assertSame( 'AB<p><strong>C</strong></p>', $out );
	}

	public function test_editor_settings_follow_each_list(): void {
		$this->assertSame( 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6', salt_mce_block_formats( salt_field_allowed( 'field_salt_rich_text_body', true ) ) );
		$this->assertSame( 'Paragraph=p;Heading 3=h3;Heading 4=h4', salt_mce_block_formats( salt_field_allowed( 'field_salt_call_to_action_body' ) ) );
		$this->assertSame( 'Paragraph=p', salt_mce_block_formats( salt_field_allowed( 'field_salt_collection_faqs_answer' ) ) );

		$this->assertSame( 'p,a[href|title|rel|target],strong/b,em/i,ul,ol,li,br', salt_mce_valid_elements( salt_field_allowed( 'field_salt_collection_faqs_answer' ) ) );
		$this->assertSame(
			array( 'bold', 'italic', 'bullist', 'numlist', 'link', 'unlink', 'removeformat', 'undo', 'redo' ),
			salt_mce_buttons_for( salt_field_allowed( 'field_salt_collection_faqs_answer' ) ),
			'No format menu and no blockquote for an FAQ answer.'
		);
		$this->assertSame( salt_mce_buttons(), salt_mce_buttons_for( salt_field_allowed( 'field_salt_rich_text_body' ) ) );
	}

	public function test_an_acf_rich_text_field_carries_its_editor_settings(): void {
		$field = salt_wysiwyg_field_editor(
			array(
				'key'  => 'field_salt_call_to_action_body',
				'type' => 'wysiwyg',
				'data' => array( 'other' => 'kept' ),
				'salt' => array( 'allowed' => salt_field_allowed( 'field_salt_call_to_action_body', true ) ),
			)
		);

		$this->assertSame( 'kept', $field['data']['other'] );
		$this->assertSame( 'Paragraph=p;Heading 3=h3;Heading 4=h4', $field['data']['salt-block-formats'] );
		$this->assertStringNotContainsString( 'h2', $field['data']['salt-valid-elements'] );
		$this->assertStringContainsString( 'h3', $field['data']['salt-valid-elements'] );
		$this->assertSame( implode( ',', salt_field_allowed( 'field_salt_call_to_action_body' ) ), $field['data']['salt-allowed'] );

		$plain = array(
			'key'  => 'field_x',
			'type' => 'wysiwyg',
		);
		$this->assertSame( $plain, salt_wysiwyg_field_editor( $plain ), 'A field with no list keeps the superset.' );
		$this->assertFalse( salt_wysiwyg_field_editor( false ) );
	}

	public function test_the_service_editor_is_narrowed_to_the_intro(): void {
		$GLOBALS['typenow'] = 'service';
		try {
			$service = salt_tinymce_config( array(), 'content' );
			$other   = salt_tinymce_config( array(), 'acf_content' );
		} finally {
			unset( $GLOBALS['typenow'] );
		}

		$this->assertSame( 'Paragraph=p', $service['block_formats'] );
		$this->assertStringNotContainsString( 'h3', $service['valid_elements'] );
		$this->assertStringNotContainsString( 'formatselect', $service['toolbar1'] );
		$this->assertStringContainsString( 'h2', $other['valid_elements'], 'Other editors start from the superset.' );
		$this->assertArrayNotHasKey( 'toolbar1', $other );
	}
}
