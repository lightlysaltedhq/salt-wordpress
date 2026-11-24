<?php
/**
 * Contact section (contract markup/contact.json, contact-form.json,
 * SC-025): layout shape (DATA02), option-backed details (DATA03),
 * ContactPoint schema, the closed message in place of the form, the form
 * as first drawn (no credential, no per-visitor value: the same bytes for
 * every visitor), the form answered in its own response (values, errors,
 * status, fresh credentials, every value escaped), the phone setting, the
 * enquiry-type select and the privacy line.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/http.php'; // set_transient() for the challenge.
require_once __DIR__ . '/../shims/blog.php'; // get_permalink() for the privacy line.

final class ContactSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		$GLOBALS['salt_test_transients'] = array();
		$_SERVER['REQUEST_URI']          = '/contact/';
	}

	protected function tearDown(): void {
		unset( $GLOBALS['salt_test_permalinks'], $_SERVER['REQUEST_URI'] );
		$GLOBALS['salt_test_transients'] = array();
		$_GET                            = array();
		parent::tearDown();
	}

	/**
	 * Seed option fixtures (DATA03: the single detail source).
	 *
	 * @param array<string,mixed> $options Option field values.
	 * @return void
	 */
	private function seed_options( array $options ): void {
		foreach ( $options as $name => $value ) {
			$GLOBALS['salt_test_fields']['option'][ $name ] = $value;
		}
	}

	/**
	 * Render ONE contact section row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_contact_section( array $fields = array() ): string {
		salt_render_state_reset(); // Each render is a fresh page.
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
		salt_render_section(
			'contact',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
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
		return array_values( array_filter( $graph['@graph'], static fn( array $node ): bool => 'ContactPoint' === ( $node['@type'] ?? '' ) ) );
	}

	/**
	 * A live form: a recipient, and optionally a phone mode and routes.
	 *
	 * @param array<string,mixed> $options More options.
	 * @return void
	 */
	private function live_form( array $options = array() ): void {
		$this->seed_options( array( 'contact_recipient' => 'hello@example.test' ) + $options );
	}

	/**
	 * A live form with a CAPTCHA provider configured (option, site key and
	 * the wp-config secret, SEC03).
	 *
	 * @return void
	 */
	private function live_form_with_captcha(): void {
		if ( ! defined( 'SALT_TURNSTILE_SECRET' ) ) {
			define( 'SALT_TURNSTILE_SECRET', 'test-secret' );
		}
		$this->live_form(
			array(
				'captcha_provider'           => 'turnstile',
				'captcha_turnstile_site_key' => 'site-key',
			)
		);
		$this->assertSame( 'turnstile', salt_captcha_provider() );
	}

	// -----------------------------------------------------------------
	// Layout shape (DATA02).
	// -----------------------------------------------------------------

	public function test_layout_is_generated_from_the_contract_no_map_fields(): void {
		$layout = salt_test_layout( 'contact' );

		$this->assertSame( 'layout_salt_contact', $layout['key'] );
		$names = array_column( $layout['sub_fields'], 'name' );
		$this->assertSame( array( 'heading', 'intro', 'showPhone', 'showEmail', 'showAddress', 'formIntro', '', 'settings' ), $names );

		$fields = array_column( $layout['sub_fields'], null, 'name' );
		foreach ( array( 'showPhone', 'showEmail', 'showAddress' ) as $toggle ) {
			$this->assertSame( 'true_false', $fields[ $toggle ]['type'] );
			$this->assertSame( 1, $fields[ $toggle ]['default_value'] );
		}
	}

	// -----------------------------------------------------------------
	// Details, schema and the closed message.
	// -----------------------------------------------------------------

	public function test_details_render_from_the_company_options(): void {
		$this->seed_options(
			array(
				'org_name'        => 'Lightly Salted',
				'company_phone'   => '+44 1905 123456',
				'company_email'   => 'hello@example.test',
				'company_address' => "1 High Street\n\nWorcester",
			)
		);

		$html = $this->render_contact_section( array( 'heading' => 'Contact' ) );

		$this->assertStringContainsString( '<p class="salt-contact__organisation">Lightly Salted</p>', $html );
		$this->assertStringContainsString( '<a class="salt-contact__link" href="tel:+441905123456">+44 1905 123456</a>', $html );
		$this->assertStringContainsString( '<a class="salt-contact__link" href="mailto:hello@example.test">', $html );
		$this->assertSame( 2, substr_count( $html, 'class="salt-contact__line"' ), 'Blank address lines are dropped.' );
	}

	public function test_a_phone_without_a_dialable_number_is_text(): void {
		$this->seed_options( array( 'company_phone' => 'Ask at reception' ) );

		$html = $this->render_contact_section();

		$this->assertStringContainsString( '<p>Ask at reception</p>', $html );
		$this->assertStringNotContainsString( 'tel:', $html );
	}

	public function test_show_toggles_suppress_details_and_their_schema(): void {
		$this->seed_options(
			array(
				'company_phone' => '01905 123456',
				'company_email' => 'hello@example.test',
			)
		);

		$html = $this->render_contact_section(
			array(
				'heading'   => 'Contact',
				'showPhone' => 0,
			)
		);

		$this->assertStringNotContainsString( 'tel:', $html );
		$this->assertStringContainsString( 'mailto:hello@example.test', $html );
		$nodes = $this->contact_point_nodes();
		$this->assertCount( 1, $nodes );
		$this->assertSame( 'customer service', $nodes[0]['contactType'] );
		$this->assertArrayNotHasKey( 'telephone', $nodes[0], 'Schema mirrors what renders (TEST03).' );
	}

	public function test_no_contact_point_when_neither_phone_nor_email_exists(): void {
		$this->render_contact_section( array( 'heading' => 'Contact' ) );

		$this->assertSame( array(), $this->contact_point_nodes() );
	}

	public function test_form_off_draws_the_closed_message_never_a_mailto_button(): void {
		$this->seed_options( array( 'company_email' => 'hello@example.test' ) );

		$html = $this->render_contact_section( array( 'heading' => 'Get in touch' ) );

		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringContainsString( '<p class="salt-contact__closed">We are not taking enquiries through the website at the moment.</p>', $html );
		$this->assertStringNotContainsString( 'Email us', $html, 'The mailto button goes (the details carry the email).' );
	}

	public function test_the_section_always_draws(): void {
		$html = $this->render_contact_section();

		$this->assertStringContainsString( '<div class="salt-block" data-block="contact">', $html );
		$this->assertStringContainsString( 'salt-contact__closed', $html );
		$this->assertStringNotContainsString( 'salt-contact__details', $html, 'No details to show: no details block.' );
	}

	public function test_text_is_escaped(): void {
		$this->live_form();

		$html = $this->render_contact_section(
			array(
				'heading'   => 'Contact',
				'intro'     => '<script>alert(1)</script>',
				'formIntro' => '<em>markup</em> stays text',
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( '<p class="salt-intro">&lt;em&gt;markup&lt;/em&gt; stays text</p>', $html );
	}

	public function test_headingless_section_never_claims_the_h1(): void {
		$this->seed_options( array( 'company_email' => 'hello@example.test' ) );

		$html = $this->render_contact_section();

		$this->assertStringNotContainsString( '<h1', $html );
		$this->assertFalse( salt_h1_claimed(), 'A11Y01: no heading, no claim.' );
	}

	// -----------------------------------------------------------------
	// The form as first drawn (SC-025).
	// -----------------------------------------------------------------

	public function test_the_first_drawn_form_carries_no_credential(): void {
		$this->live_form_with_captcha();

		$html = $this->render_contact_section( array( 'heading' => 'Get in touch' ) );

		$this->assertStringContainsString( '<form class="salt-contact__form" id="contact__form" method="post" action="/contact/?salt-contact-send=contact#contact__status" novalidate>', $html );
		foreach ( array( 'formToken', 'challengeToken', 'challengeAnswer', 'salt_contact_nonce', 'salt_time', 'name="action"' ) as $absent ) {
			$this->assertStringNotContainsString( $absent, $html, $absent );
		}
		$this->assertSame( array(), $GLOBALS['salt_test_transients'], 'No challenge minted.' );
		$this->assertStringContainsString( '<input id="contact__website" name="website" type="text" tabindex="-1" autocomplete="off">', $html, 'Honeypot: no value attribute.' );
		$this->assertStringContainsString( '<input type="hidden" name="returnTo" value="/contact/">', $html );
		$this->assertStringContainsString( '<p class="salt-contact__status" id="contact__status" role="status" aria-live="polite" tabindex="-1"></p>', $html );
	}

	public function test_two_visitors_get_the_same_bytes(): void {
		$this->live_form_with_captcha();

		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$first                  = $this->render_contact_section( array( 'heading' => 'Get in touch' ) );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
		$second                 = $this->render_contact_section( array( 'heading' => 'Get in touch' ) );

		$this->assertStringContainsString( '<form', $first );
		$this->assertStringContainsString( 'cf-turnstile', $first, 'The provider widget is drawn (the same for everyone).' );
		$this->assertSame( $first, $second, 'The page as first drawn may be cached and served to every visitor.' );
	}

	public function test_returnto_is_the_page_path_without_the_contact_flags(): void {
		$this->live_form();
		$_SERVER['REQUEST_URI'] = '/?page_id=7&salt-contact=ok&salt-contact-send=x';

		$html = $this->render_contact_section( array( 'heading' => 'Get in touch' ) );

		$this->assertStringContainsString( 'name="returnTo" value="/?page_id=7"', $html );
		$this->assertMatchesRegularExpression( '#action="/\?page_id=7&(\#038;|amp;)?salt-contact-send=contact\#contact__status"#', $html );
	}

	// -----------------------------------------------------------------
	// Phone, enquiry types, privacy.
	// -----------------------------------------------------------------

	public function test_phone_follows_the_setting(): void {
		$this->live_form();
		$this->assertStringNotContainsString( 'name="phone"', $this->render_contact_section( array( 'heading' => 'A' ) ), 'Off by default.' );

		$this->seed_options( array( 'contact_form_phone' => 'optional' ) );
		$html = $this->render_contact_section( array( 'heading' => 'A' ) );
		$this->assertStringContainsString( 'Phone <span class="salt-contact__optional">(optional)</span>', $html );
		$this->assertStringContainsString( '<input class="salt-contact__input" id="contact__phone" name="phone" type="tel" autocomplete="tel">', $html );

		$this->seed_options( array( 'contact_form_phone' => 'required' ) );
		$html = $this->render_contact_section( array( 'heading' => 'A' ) );
		$this->assertStringNotContainsString( 'salt-contact__optional', $html );
		$this->assertStringContainsString( 'name="phone" type="tel" autocomplete="tel" required>', $html );
	}

	public function test_the_enquiry_select_lists_two_or_more_types_by_value(): void {
		$this->live_form(
			array(
				'contact_routes' => array(
					array( 'enquiry_type' => 'Garden design' ),
					array(
						'enquiry_type' => 'Maintenance',
						'value'        => 'upkeep',
					),
				),
			)
		);

		$html = $this->render_contact_section( array( 'heading' => 'A' ) );

		$this->assertStringContainsString( '<select class="salt-contact__input" id="contact__enquiry-type" name="enquiryType">', $html );
		$this->assertStringContainsString( '<option value="garden-design" selected>Garden design</option>', $html );
		$this->assertStringContainsString( '<option value="upkeep">Maintenance</option>', $html );

		$this->seed_options( array( 'contact_routes' => array( array( 'enquiry_type' => 'Only one' ) ) ) );
		$this->assertStringNotContainsString( '<select', $this->render_contact_section( array( 'heading' => 'A' ) ), 'One type: no select.' );
	}

	public function test_the_privacy_line_links_the_privacy_page(): void {
		$this->live_form( array( 'contact_privacy_page' => 12 ) );
		$GLOBALS['salt_test_permalinks'][12] = 'https://example.test/privacy/';

		$html = $this->render_contact_section( array( 'heading' => 'A' ) );

		$this->assertStringContainsString( '<p class="salt-contact__privacy">By sending this form you agree to us handling your details to respond to your enquiry — see our <a class="salt-contact__link" href="/privacy/">privacy policy</a>.</p>', $html );
		$this->assertLessThan( strpos( $html, 'salt-contact__actions' ), strpos( $html, 'salt-contact__privacy' ), 'Before the actions.' );
	}

	// -----------------------------------------------------------------
	// The form answered in its own response.
	// -----------------------------------------------------------------

	public function test_an_answered_form_is_drawn_again_with_its_state_escaped(): void {
		$this->live_form( array( 'contact_form_phone' => 'optional' ) );
		$state = array(
			'state'     => 'invalid',
			'values'    => array(
				'name'    => '"><script>alert(1)</script>',
				'email'   => 'ruth@',
				'message' => '</textarea><script>x</script>',
			),
			'errors'    => array( 'email' => 'Enter an <b>email</b>' ),
			'status'    => array(
				'tone'    => 'problem',
				'message' => 'Please correct <the fields>.',
			),
			'challenge' => array(
				'question' => 'What is 2 plus <3>?',
				'token'    => str_repeat( 'a', 32 ),
			),
			'formToken' => '1.abc."x',
		);
		add_filter( 'salt_contact_form_states', static fn() => array( 'contact' => $state ) );

		$html = $this->render_contact_section( array( 'heading' => 'A' ) );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( 'value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $html );
		$this->assertStringContainsString( '>&lt;/textarea&gt;&lt;script&gt;x&lt;/script&gt;</textarea>', $html );
		$this->assertStringContainsString( '<p class="salt-contact__error" id="contact__email-error">Enter an &lt;b&gt;email&lt;/b&gt;</p>', $html );
		$this->assertStringContainsString( 'aria-invalid="true" aria-describedby="contact__email-error contact__status" value="ruth@"', $html );
		$this->assertStringContainsString( 'data-tone="problem">Please correct &lt;the fields&gt;.</p>', $html );
		$this->assertStringContainsString( 'What is 2 plus &lt;3&gt;?', $html );
		$this->assertStringContainsString( '<input type="hidden" name="formToken" value="1.abc.&quot;x">', $html );
		$this->assertStringNotContainsString( '<noscript>', $html, 'No provider: the challenge is drawn as it is.' );
	}

	public function test_with_a_provider_the_challenge_is_in_noscript(): void {
		$this->live_form_with_captcha();
		$state = array(
			'state'     => 'sent',
			'values'    => array(),
			'errors'    => array(),
			'status'    => array(
				'tone'    => 'sent',
				'message' => 'Thanks.',
			),
			'challenge' => array(
				'question' => 'What is 1 plus 1?',
				'token'    => str_repeat( 'b', 32 ),
			),
			'formToken' => 'tok',
		);
		add_filter( 'salt_contact_form_states', static fn() => array( 'contact' => $state ) );

		$html = $this->render_contact_section( array( 'heading' => 'A' ) );
		$this->assertMatchesRegularExpression( '#<noscript><div class="salt-contact__field">.*name="challengeToken".*</noscript><input type="hidden" name="formToken" value="tok">#s', $html );
	}

	public function test_another_bands_answered_state_is_not_drawn_here(): void {
		$this->live_form();
		add_filter(
			'salt_contact_form_states',
			static fn() => array(
				'elsewhere' => array(
					'state'     => 'sent',
					'status'    => array(
						'tone'    => 'sent',
						'message' => 'Thanks.',
					),
					'challenge' => array(
						'question' => 'Q',
						'token'    => str_repeat( 'c', 32 ),
					),
					'formToken' => 'tok',
				),
			)
		);

		$html = $this->render_contact_section( array( 'heading' => 'A' ) );

		$this->assertStringNotContainsString( 'formToken', $html );
		$this->assertStringNotContainsString( 'Thanks.', $html );
	}

	public function test_the_legacy_status_flag_shows_a_fixed_message(): void {
		$this->live_form();
		$_GET['salt-contact'] = 'ok';

		$html = $this->render_contact_section( array( 'heading' => 'A' ) );

		$this->assertStringContainsString( 'data-tone="sent">Thanks — your message has been sent.</p>', $html );
	}
}
