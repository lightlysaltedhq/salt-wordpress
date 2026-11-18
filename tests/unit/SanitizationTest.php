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
}
