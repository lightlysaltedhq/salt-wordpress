<?php
/**
 * Contact slice unit tests: layout shape (DATA02), the
 * salt_contact_form_enabled deferral (SEC01/SEC02/PRIV06 — form absent by
 * default, full accessible markup when the compliance phase flips the
 * filter), option-backed details (DATA03), ContactPoint schema queueing,
 * and the 0-state (COMPL02).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class ContactSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Seed the Company option fixtures (DATA03: the single detail source).
	 *
	 * @param array<string,mixed> $options Option field values.
	 * @return void
	 */
	private function seed_company_options( array $options ): void {
		foreach ( $options as $name => $value ) {
			$GLOBALS['salt_test_fields']['option'][ $name ] = $value;
		}
	}

	/**
	 * Render ONE contact section row.
	 *
	 * @param array<string,mixed> $fields  Row sub-field fixtures.
	 * @param array<string,mixed> $context Loop context.
	 * @return string
	 */
	private function render_contact_section( array $fields = array(), array $context = array(
		'index'      => 0,
		'is_primary' => true,
	) ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'contact',
					'fields' => $fields,
				),
			)
		);
		$this->assertTrue( have_rows( 'sections' ) );
		the_row();

		ob_start();
		salt_render_section( 'contact', $context );
		return (string) ob_get_clean();
	}

	/**
	 * The ContactPoint nodes present in the assembled graph.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function contact_point_nodes(): array {
		$graph = salt_schema_graph(
			array(
				'home_url' => 'https://example.test',
				'page_url' => 'https://example.test/p',
			)
		);

		return array_values(
			array_filter(
				$graph['@graph'],
				static fn( array $node ): bool => 'ContactPoint' === ( $node['@type'] ?? '' )
			)
		);
	}

	// -----------------------------------------------------------------
	// Layout shape (DATA02).
	// -----------------------------------------------------------------

	public function test_layout_shape_content_first_then_wrapper_no_map_fields(): void {
		$layout = require dirname( __DIR__, 2 ) . '/inc/fields/layouts/contact.php';

		$this->assertSame( 'contact', $layout['name'] );
		$this->assertSame( 'layout_salt_contact', $layout['key'] );

		$names = array_column( $layout['sub_fields'], 'name' );
		$this->assertSame(
			array( 'heading', 'text', 'show_phone', 'show_email', 'show_address', 'form_intro' ),
			array_slice( $names, 0, 6 )
		);
		$this->assertContains( 'section_spacing', $names, 'Wrapper Advanced tab embedded last (§11).' );

		// Maps are absent from this build — no map/geo fields of any kind.
		foreach ( $names as $name ) {
			$this->assertStringNotContainsString( 'map', (string) $name );
			$this->assertStringNotContainsString( 'geo', (string) $name );
		}

		$fields = array_column( $layout['sub_fields'], null, 'name' );
		$this->assertSame( 'field_salt_contact_heading', $fields['heading']['key'] );
		foreach ( array( 'show_phone', 'show_email', 'show_address' ) as $toggle ) {
			$this->assertSame( 'true_false', $fields[ $toggle ]['type'] );
			$this->assertSame( 1, $fields[ $toggle ]['default_value'] );
		}
	}

	// -----------------------------------------------------------------
	// Form deferral (SEC01/SEC02/PRIV06).
	// -----------------------------------------------------------------

	public function test_form_is_absent_by_default_and_a_mailto_cta_renders_instead(): void {
		$this->seed_company_options( array( 'company_email' => 'hello@example.test' ) );

		$html = $this->render_contact_section( array( 'heading' => 'Get in touch' ) );

		$this->assertStringNotContainsString( '<form', $html, 'No live endpoint exists: the form never renders by default (SEC01).' );
		$this->assertStringNotContainsString( 'salt_hp', $html );
		$this->assertStringContainsString( 'href="mailto:hello@example.test"', $html );
		$this->assertStringContainsString( 'Email us', $html );
	}

	public function test_filter_on_renders_the_accessible_form_with_honeypot(): void {
		$this->seed_company_options( array( 'company_email' => 'hello@example.test' ) );
		add_filter( 'salt_contact_form_enabled', static fn (): bool => true, 20 );

		$html = $this->render_contact_section( array( 'heading' => 'Get in touch' ) );

		$this->assertStringContainsString( '<form', $html );
		$this->assertStringContainsString( 'action="" method="post"', $html );

		// Honeypot: present, hidden, unreachable by tab/AT.
		$this->assertStringContainsString( 'name="salt_hp"', $html );
		$this->assertStringContainsString( 'visually-hidden', $html );
		$this->assertStringContainsString( 'tabindex="-1"', $html );

		// Label + aria-describedby hint per field (WCAG pattern).
		foreach ( array( 'name', 'email', 'message' ) as $field ) {
			$this->assertStringContainsString( 'for="salt-contact-' . $field . '"', $html );
			$this->assertStringContainsString( 'id="salt-contact-' . $field . '"', $html );
			$this->assertStringContainsString( 'aria-describedby="salt-contact-' . $field . '-hint"', $html );
			$this->assertStringContainsString( 'id="salt-contact-' . $field . '-hint"', $html );
		}

		$this->assertStringContainsString( '<button', $html );
		$this->assertStringContainsString( 'type="submit"', $html );
		$this->assertStringNotContainsString( 'Email us', $html, 'The mailto fallback yields to the form.' );
	}

	// -----------------------------------------------------------------
	// Option-backed details (DATA03).
	// -----------------------------------------------------------------

	public function test_details_render_from_the_company_options(): void {
		$this->seed_company_options(
			array(
				'org_name'      => 'Lightly Salted',
				'company_phone' => '+44 1905 123456',
				'company_email' => 'hello@example.test',
			)
		);

		$html = $this->render_contact_section( array( 'heading' => 'Contact' ) );

		$this->assertStringContainsString( 'Lightly Salted', $html );
		$this->assertStringContainsString( 'href="tel:+441905123456"', $html, 'tel: href strips formatting.' );
		$this->assertStringContainsString( '+44 1905 123456', $html, 'The display number keeps its formatting.' );
		$this->assertStringContainsString( 'href="mailto:hello@example.test"', $html );
	}

	public function test_show_toggles_suppress_individual_details(): void {
		$this->seed_company_options(
			array(
				'company_phone' => '01905 123456',
				'company_email' => 'hello@example.test',
			)
		);

		$html = $this->render_contact_section(
			array(
				'heading'    => 'Contact',
				'show_phone' => 0,
			)
		);

		$this->assertStringNotContainsString( 'tel:', $html );
		$this->assertStringContainsString( 'mailto:hello@example.test', $html );
	}

	// -----------------------------------------------------------------
	// Schema (ContactPoint).
	// -----------------------------------------------------------------

	public function test_contact_point_node_is_queued_from_the_options(): void {
		$this->seed_company_options(
			array(
				'company_phone' => '01905 123456',
				'company_email' => 'hello@example.test',
			)
		);

		$this->render_contact_section( array( 'heading' => 'Contact' ) );

		$nodes = $this->contact_point_nodes();
		$this->assertCount( 1, $nodes );
		$this->assertSame( 'customer service', $nodes[0]['contactType'] );
		$this->assertSame( '01905 123456', $nodes[0]['telephone'] );
		$this->assertSame( 'hello@example.test', $nodes[0]['email'] );
	}

	public function test_no_contact_point_when_neither_phone_nor_email_exists(): void {
		$this->render_contact_section(
			array(
				'heading' => 'Contact',
				'text'    => 'Drop by the studio.',
			)
		);

		$this->assertSame( array(), $this->contact_point_nodes() );
	}

	public function test_hidden_details_stay_out_of_the_schema_node(): void {
		$this->seed_company_options(
			array(
				'company_phone' => '01905 123456',
				'company_email' => 'hello@example.test',
			)
		);

		$this->render_contact_section(
			array(
				'heading'    => 'Contact',
				'show_phone' => 0,
			)
		);

		$nodes = $this->contact_point_nodes();
		$this->assertCount( 1, $nodes );
		$this->assertArrayNotHasKey( 'telephone', $nodes[0], 'Schema mirrors what renders (TEST03).' );
	}

	// -----------------------------------------------------------------
	// States (COMPL02) + escaping.
	// -----------------------------------------------------------------

	public function test_zero_state_no_options_no_text_renders_nothing(): void {
		$this->assertSame( '', $this->render_contact_section( array() ) );
		$this->assertSame( array(), $this->contact_point_nodes(), 'A skipped section queues nothing.' );
	}

	public function test_text_and_intro_are_escaped(): void {
		$this->seed_company_options( array( 'company_email' => 'hello@example.test' ) );

		$html = $this->render_contact_section(
			array(
				'heading'    => 'Contact',
				'text'       => "Line one\n<script>alert(1)</script>",
				'form_intro' => '<em>markup</em> stays text',
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( '&lt;em&gt;markup&lt;/em&gt; stays text', $html );
		$this->assertStringContainsString( '<br', $html, 'Textarea newlines become <br>.' );
	}

	public function test_headingless_section_never_claims_the_h1(): void {
		$this->seed_company_options( array( 'company_email' => 'hello@example.test' ) );

		$html = $this->render_contact_section( array() );

		$this->assertNotSame( '', $html, 'Details alone are enough to render.' );
		$this->assertStringNotContainsString( '<h1', $html );
		$this->assertFalse( salt_h1_claimed(), 'A11Y01: no heading, no claim.' );
	}
}
