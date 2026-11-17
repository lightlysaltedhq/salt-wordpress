<?php
/**
 * Contact slice unit tests: layout shape (DATA02), the
 * salt_contact_form_enabled deferral (SEC01/SEC02/PRIV06 — form absent by
 * default, full accessible markup when the compliance phase flips the
 * filter), option-backed details (DATA03), ContactPoint schema queueing,
 * the 0-state (COMPL02), and the logic/view split (ID-3587): resolver
 * props, a view drawn from props alone, and the pinned order and
 * conditions of the form's service and stateful calls (rule 4).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/http.php'; // set_transient() for the no-JS challenge.
require_once __DIR__ . '/../shims/blog.php'; // get_permalink() for the privacy line.

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
		$this->assertContains( 'spacing', $names, 'Wrapper Advanced tab embedded last (§11).' );

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
		$this->assertStringContainsString( 'action="https://example.test/wp-admin/admin-post.php"', $html );
		$this->assertStringContainsString( 'name="action" value="salt_contact"', $html );
		$this->assertStringContainsString( 'name="salt_contact_nonce"', $html );
		$this->assertStringContainsString( 'name="salt_time"', $html );
		$this->assertStringContainsString( 'data-salt-contact-status', $html, 'aria-live status region present (A11Y09).' );

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

	// -----------------------------------------------------------------
	// Logic/view split (ID-3587): resolver, view, and the order and
	// conditions of the form's service and stateful calls (rule 4).
	// -----------------------------------------------------------------

	protected function tearDown(): void {
		unset( $GLOBALS['salt_test_permalinks'], $GLOBALS['salt_contact_test_trace'] );
		$GLOBALS['salt_test_transients'] = array();
		$_GET                            = array();
		parent::tearDown();
	}

	/**
	 * Seed one contact FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return array<string,mixed>|null
	 */
	private function resolve_contact( array $fields ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'contact',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'contact' );
		$this->assertSame( 'salt_resolve_section_contact', $resolver );

		return $resolver(
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
	}

	/**
	 * An array-like store that records every lookup and write, in order,
	 * into $GLOBALS['salt_contact_test_trace'] as "{tag}:{key}" /
	 * "{tag}=set" — how the stateful calls are observed (option reads go
	 * through the get_field shim; the challenge writes a transient; the
	 * privacy permalink is a per-ID fixture lookup).
	 *
	 * @param string              $tag  Trace label.
	 * @param array<string,mixed> $data Initial contents.
	 * @return ArrayAccess&Countable
	 */
	private static function recorder( string $tag, array $data ): object {
		return new class( $tag, $data ) implements ArrayAccess, Countable {
			/**
			 * @param string              $tag  Trace label.
			 * @param array<string,mixed> $data Contents.
			 */
			public function __construct( private string $tag, private array $data ) {}

			public function offsetExists( mixed $offset ): bool {
				$GLOBALS['salt_contact_test_trace'][] = $this->tag . ':' . $offset;
				return isset( $this->data[ $offset ] );
			}

			public function offsetGet( mixed $offset ): mixed {
				return $this->data[ $offset ] ?? null;
			}

			public function offsetSet( mixed $offset, mixed $value ): void {
				$GLOBALS['salt_contact_test_trace'][] = $this->tag . '=set';
				$this->data[ (string) $offset ]       = $value;
			}

			public function offsetUnset( mixed $offset ): void {
				unset( $this->data[ $offset ] );
			}

			public function count(): int {
				return count( $this->data );
			}

			/**
			 * @return array<string,mixed>
			 */
			public function all(): array {
				return $this->data;
			}
		};
	}

	/**
	 * Seed the live form: enabled, the Company options plus form options,
	 * a frozen time-trap clock, and recorders on options, transients and
	 * permalinks. Optionally a fully configured Turnstile provider.
	 *
	 * @param bool  $captcha Configure a CAPTCHA provider.
	 * @param mixed $privacy The contact_privacy_page option value.
	 * @return void
	 */
	private function seed_live_form( bool $captcha, $privacy ): void {
		salt_captcha_reset();
		$options = array(
			'company_email'        => 'hello@example.test',
			'contact_privacy_page' => $privacy,
			'contact_routes'       => array(
				array( 'enquiry_type' => 'Sales' ),
				array( 'enquiry_type' => 'Support' ),
			),
		);
		if ( $captcha ) {
			if ( ! defined( 'SALT_TURNSTILE_SECRET' ) ) {
				define( 'SALT_TURNSTILE_SECRET', 'test-secret' );
			}
			$options['captcha_provider']           = 'turnstile';
			$options['captcha_turnstile_site_key'] = 'ts-site-key';
		}

		$GLOBALS['salt_contact_test_trace']    = array();
		$GLOBALS['salt_test_fields']['option'] = self::recorder( 'option', $options );
		$GLOBALS['salt_test_transients']       = self::recorder( 'transient', array() );
		$GLOBALS['salt_test_permalinks']       = self::recorder( 'permalink', array( 12 => 'https://example.test/privacy/' ) );

		add_filter( 'salt_contact_form_enabled', static fn (): bool => true, 20 );
		add_filter(
			'salt_contact_clock',
			static function (): int {
				$GLOBALS['salt_contact_test_trace'][] = 'time_token';
				return 1783000000;
			},
			20
		);
	}

	/**
	 * The recorded trace, reduced to the rule-4 events this section pins.
	 *
	 * @return string[]
	 */
	private function form_call_trace(): array {
		$pinned = array( 'option:contact_privacy_page', 'time_token', 'option:captcha_provider', 'transient=set' );
		return array_values(
			array_filter(
				$GLOBALS['salt_contact_test_trace'],
				static fn( string $event ): bool => in_array( $event, $pinned, true ) || str_starts_with( $event, 'permalink:' )
			)
		);
	}

	public function test_resolver_returns_null_for_the_zero_state(): void {
		$this->assertNull( $this->resolve_contact( array( 'heading' => '  ' ) ) );
		$this->assertSame( array(), $this->contact_point_nodes() );
	}

	public function test_resolver_props_use_the_contract_names(): void {
		$this->seed_company_options(
			array(
				'org_name'        => ' Lightly Salted ',
				'company_phone'   => ' +44 (0)1905 123456 ',
				'company_email'   => 'hello@example.test',
				'company_address' => " 1 High Street \n\n  Bath \r\nBA1 1AA",
			)
		);

		$resolved = $this->resolve_contact(
			array(
				'heading'    => ' Get in touch ',
				'text'       => "Line one\nLine two",
				'form_intro' => '',
				'show_email' => 0,
			)
		);

		$this->assertSame(
			array( 'heading', 'intro', 'showPhone', 'showEmail', 'showAddress', 'formIntro', 'details', 'has_details', 'form_enabled', 'request_status', 'enquiry_types', 'form_action', 'nonce', 'time_token', 'page_url', 'captcha_widget_html', 'challenge', 'privacy_url' ),
			array_keys( $resolved['props'] )
		);
		$this->assertSame( ' Get in touch ', $resolved['props']['heading'], 'Optional text is never trimmed.' );
		$this->assertSame( "Line one\nLine two", $resolved['props']['intro'], 'intro comes from the text field.' );
		$this->assertNull( $resolved['props']['formIntro'] );
		$this->assertTrue( $resolved['props']['showPhone'], 'Unsaved toggles default on.' );
		$this->assertFalse( $resolved['props']['showEmail'] );
		$this->assertSame(
			array(
				'organisation' => 'Lightly Salted',
				'phone'        => '+44 (0)1905 123456',
				'phoneHref'    => 'tel:+4401905123456',
				'email'        => null,
				'address'      => array( '1 High Street', 'Bath', 'BA1 1AA' ),
			),
			$resolved['props']['details']
		);
		$this->assertTrue( $resolved['props']['has_details'] );
		$this->assertTrue( $resolved['section']['has_heading'] );
		$this->assertSame( 'contact', $resolved['section']['name'] );
	}

	public function test_resolver_phone_without_dialable_digits_has_no_href(): void {
		$this->seed_company_options( array( 'company_phone' => 'Ask at reception' ) );

		$resolved = $this->resolve_contact( array() );

		$this->assertSame( 'Ask at reception', $resolved['props']['details']['phone'] );
		$this->assertNull( $resolved['props']['details']['phoneHref'], 'Rule 5: the view draws the number as text.' );
	}

	public function test_form_off_computes_no_form_value_and_writes_no_state(): void {
		$this->seed_company_options( array( 'company_email' => 'hello@example.test' ) );
		$GLOBALS['salt_contact_test_trace'] = array();
		$GLOBALS['salt_test_transients']    = self::recorder( 'transient', array() );
		add_filter(
			'salt_contact_clock',
			static function (): int {
				$GLOBALS['salt_contact_test_trace'][] = 'time_token';
				return 1783000000;
			},
			20
		);

		$resolved = $this->resolve_contact( array( 'heading' => 'Contact' ) );

		$this->assertFalse( $resolved['props']['form_enabled'] );
		foreach ( array( 'request_status', 'enquiry_types', 'form_action', 'nonce', 'time_token', 'page_url', 'challenge', 'privacy_url' ) as $prop ) {
			$this->assertNull( $resolved['props'][ $prop ], $prop . ' is never computed with the form off.' );
		}
		$this->assertSame( '', $resolved['props']['captcha_widget_html'] );
		$this->assertSame( array(), $GLOBALS['salt_contact_test_trace'], 'No time token made, nothing written.' );
		$this->assertCount( 0, $GLOBALS['salt_test_transients'] );
	}

	public function test_form_without_a_captcha_provider_makes_no_challenge(): void {
		$this->seed_live_form( false, '12' );

		$resolved = $this->resolve_contact( array( 'heading' => 'Contact' ) );

		$this->assertTrue( $resolved['props']['form_enabled'] );
		$this->assertSame(
			array(
				'status'  => '',
				'message' => '',
			),
			$resolved['props']['request_status']
		);
		$this->assertSame( array( 'Sales', 'Support' ), $resolved['props']['enquiry_types'] );
		$this->assertSame( 'https://example.test/wp-admin/admin-post.php', $resolved['props']['form_action'] );
		$this->assertSame( wp_create_nonce( 'salt_contact' ), $resolved['props']['nonce'] );
		$this->assertSame( salt_contact_time_token( 1783000000 ), $resolved['props']['time_token'] );
		$this->assertSame( 'https://example.test/current-page/', $resolved['props']['page_url'] );
		$this->assertSame( '', $resolved['props']['captcha_widget_html'] );
		$this->assertNull( $resolved['props']['challenge'] );
		$this->assertCount( 0, $GLOBALS['salt_test_transients'], 'No provider: salt_challenge_create() never runs.' );
		$this->assertNull( $resolved['props']['privacy_url'], 'A non-int privacy option draws no privacy line.' );
		$this->assertSame(
			array( 'option:contact_privacy_page', 'time_token', 'option:captcha_provider' ),
			$this->form_call_trace(),
			'Privacy option, then the time token, then ONE provider check; no permalink lookup.'
		);
	}

	public function test_form_with_a_captcha_provider_pins_the_call_order(): void {
		$this->seed_live_form( true, 12 );

		$resolved = $this->resolve_contact( array( 'heading' => 'Contact' ) );

		$this->assertSame(
			array(
				'option:contact_privacy_page', // The privacy page option read.
				'time_token',                  // The time-trap token, after the nonce.
				'option:captcha_provider',     // The provider check: once, in the condition.
				'option:captcha_provider',     // The widget checks the provider again...
				'transient=set',               // ...then the challenge stores its answer.
				'permalink:12',                // The privacy permalink last, only for a positive int.
			),
			$this->form_call_trace()
		);

		$this->assertSame( '<div class="cf-turnstile" data-sitekey="ts-site-key"></div>', $resolved['props']['captcha_widget_html'] );
		$this->assertMatchesRegularExpression( '/^What is [1-9] plus [1-9]\?$/', $resolved['props']['challenge']['question'] );
		$this->assertSame(
			array( 'salt_challenge_' . $resolved['props']['challenge']['token'] ),
			array_keys( $GLOBALS['salt_test_transients']->all() ),
			'Exactly one challenge is stored, under the token the view draws.'
		);
		$this->assertSame( 'https://example.test/privacy/', $resolved['props']['privacy_url'] );
	}

	public function test_resolver_makes_the_form_calls_in_the_pre_split_order(): void {
		$watch = array( 'get_field', 'salt_contact_configured', 'salt_schema_add', 'salt_section_wrapper_args', 'salt_contact_request_status', 'salt_contact_enquiry_types', 'admin_url', 'wp_create_nonce', 'salt_contact_time_token', 'home_url', 'add_query_arg', 'salt_captcha_provider', 'salt_captcha_widget_html', 'salt_challenge_create', 'get_permalink' );

		$this->assertSame(
			array(
				"get_field('org_name'",
				"get_field('company_phone'",
				"get_field('company_email'",
				"get_field('company_address'",
				'salt_contact_configured',
				'salt_schema_add',
				'salt_section_wrapper_args',
				'salt_contact_request_status',
				'salt_contact_enquiry_types',
				"get_field('contact_privacy_page'",
				'admin_url',
				'wp_create_nonce',
				'salt_contact_time_token',
				'home_url',
				'add_query_arg',
				'salt_captcha_provider',
				'salt_captcha_widget_html',
				'salt_challenge_create',
				'get_permalink',
			),
			self::calls_in( dirname( __DIR__, 2 ) . '/inc/sections/contact.php', $watch ),
			'Rule 4: the services run in the order the pre-split markup ran them.'
		);

		$this->assertSame(
			array(),
			self::calls_in( dirname( __DIR__, 2 ) . '/template-parts/sections/contact.php', array_merge( $watch, array( 'get_sub_field', 'salt_option', 'salt_log', 'salt_section' ) ) ),
			'The view makes no service, stateful or data call.'
		);
	}

	/**
	 * The calls to the watched functions in a PHP file, in source order
	 * (get_field with its first argument).
	 *
	 * @param string   $file  PHP file.
	 * @param string[] $watch Function names.
	 * @return string[]
	 */
	private static function calls_in( string $file, array $watch ): array {
		$tokens = array_values(
			array_filter(
				token_get_all( (string) file_get_contents( $file ) ),
				static fn( $token ): bool => ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true )
			)
		);

		$calls = array();
		foreach ( $tokens as $i => $token ) {
			if ( ! is_array( $token ) || T_STRING !== $token[0] || ! in_array( $token[1], $watch, true ) || '(' !== ( $tokens[ $i + 1 ] ?? '' ) ) {
				continue;
			}
			$previous = $tokens[ $i - 1 ] ?? '';
			if ( is_array( $previous ) && T_FUNCTION === $previous[0] ) {
				continue;
			}
			$calls[] = 'get_field' === $token[1] ? "get_field({$tokens[ $i + 2 ][1]}" : $token[1];
		}
		return $calls;
	}

	public function test_live_form_renders_the_captcha_and_privacy_markup_byte_for_byte(): void {
		$this->seed_live_form( true, 12 );

		$html = $this->render_contact_section( array( 'heading' => 'Contact' ) );

		$this->assertSame( 1, preg_match( '/name="salt_challenge_token" value="([0-9a-f]{32})"/', $html, $match ) );
		$this->assertSame(
			array( 'salt_challenge_' . $match[1] ),
			array_keys( $GLOBALS['salt_test_transients']->all() ),
			'The drawn token is the one stored, and only one challenge is made.'
		);

		// Rule 1: the inline tags that now print props keep their tabs.
		$this->assertStringContainsString(
			"\t\t\t\t\t<div class=\"cf-turnstile\" data-sitekey=\"ts-site-key\"></div>\t\t\t\t\t<noscript>\n\t\t\t\t\t\t\t\t\t\t\t\t<p class=\"flex flex-col gap-1\">",
			$html
		);
		$this->assertStringContainsString(
			'see our <a class="text-brand underline" href="https://example.test/privacy/">privacy policy</a>.',
			$html
		);
		$this->assertStringContainsString( '<select class="rounded-md border border-border bg-surface px-3 py-2 text-ink" id="salt-contact-type" name="salt_contact_type">', $html );
	}

	public function test_view_draws_from_props_alone(): void {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/contact.php',
			false,
			array(
				'props' => array(
					'heading'             => 'From props',
					'intro'               => null,
					'showPhone'           => true,
					'showEmail'           => true,
					'showAddress'         => true,
					'formIntro'           => null,
					'details'             => array(
						'organisation' => null,
						'phone'        => 'Reception',
						'phoneHref'    => null,
						'email'        => 'a@example.test',
						'address'      => array(),
					),
					'has_details'         => true,
					'form_enabled'        => true,
					'request_status'      => array(
						'status'  => 'error',
						'message' => 'Try again.',
					),
					'enquiry_types'       => array( 'Only one' ),
					'form_action'         => 'https://example.test/post',
					'nonce'               => 'n0nce',
					'time_token'          => '1.abc',
					'page_url'            => 'https://example.test/here/',
					'captcha_widget_html' => '<div class="cf-turnstile"></div>',
					'challenge'           => array(
						'question' => 'What is 1 plus 1?',
						'token'    => str_repeat( 'a', 32 ),
					),
					'privacy_url'         => null,
				),
				'plan'  => array(
					'index'         => 0,
					'headingLevel'  => 2,
					'priorityMedia' => false,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ) );
		$this->assertStringContainsString( '<p>Reception</p>', $html, 'No phoneHref: the number is text.' );
		$this->assertStringContainsString( 'action="https://example.test/post"', $html );
		$this->assertStringContainsString( 'name="salt_contact_nonce" value="n0nce"', $html );
		$this->assertStringContainsString( 'name="salt_time" value="1.abc"', $html );
		$this->assertStringContainsString( 'name="salt_page_url" value="https://example.test/here/"', $html );
		$this->assertStringContainsString( 'Try again.', $html );
		$this->assertStringContainsString( '<div class="cf-turnstile"></div>', $html );
		$this->assertStringContainsString( 'What is 1 plus 1?', $html );
		$this->assertStringNotContainsString( 'salt-contact-type', $html, 'One enquiry type: no select.' );
		$this->assertStringNotContainsString( 'privacy policy', $html );
		$this->assertStringNotContainsString( 'Email us', $html );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );
		$this->assertCount( 0, $GLOBALS['salt_test_transients'] ?? array(), 'The view writes no state.' );
	}
}
