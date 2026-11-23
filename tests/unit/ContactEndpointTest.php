<?php
/**
 * Contact endpoint orchestrator (SEC01/SEC02/SEC10, PRIV06, SC-025): the
 * full defence-layer matrix on every submission path — 'rest' (script),
 * 'page' (the self-posted page, no script) and 'legacy' (the pre-6.0
 * admin-post form, kept one release) — with the contract's field names.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/acf.php';

foreach ( glob( __DIR__ . '/../shims/*.php' ) as $salt_shim_file ) {
	require_once $salt_shim_file;
}
unset( $salt_shim_file );

final class ContactEndpointTest extends SaltTestCase {

	private const NOW = 1783000000;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_transients']                            = array();
		$GLOBALS['salt_test_http']                                  = array();
		$GLOBALS['salt_test_mail']                                  = array();
		$GLOBALS['salt_test_nocache']                               = false;
		$GLOBALS['salt_test_status']                                = null;
		$_SERVER['REMOTE_ADDR']                                     = '203.0.113.7';
		$GLOBALS['salt_test_fields']['option']['contact_recipient'] = 'hello@example.test';
		$GLOBALS['salt_test_fields']['option']['submissions_store'] = true;
	}

	protected function tearDown(): void {
		unset( $_SERVER['REQUEST_METHOD'], $_GET['salt-contact-send'], $_SERVER['REQUEST_URI'] );
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * A valid send by the contract's names, with a fresh form token.
	 *
	 * @return array<string,string>
	 */
	private function valid_payload(): array {
		return array(
			'formToken' => salt_contact_form_token( self::NOW - 10 ),
			'website'   => '',
			'name'      => 'Ada Lovelace',
			'email'     => 'ada@example.org',
			'message'   => 'I would like a website.',
			'returnTo'  => '/contact/',
		);
	}

	/**
	 * The valid send with an answered arithmetic challenge (the page path).
	 *
	 * @param bool $right Whether the answer is right.
	 * @return array<string,string>
	 */
	private function page_payload( bool $right = true ): array {
		$challenge = salt_challenge_create();
		preg_match( '/(\d+)\D+(\d+)/', $challenge['question'], $m );
		return array(
			'challengeToken'  => $challenge['token'],
			'challengeAnswer' => (string) ( (int) $m[1] + (int) $m[2] + ( $right ? 0 : 1 ) ),
		) + $this->valid_payload();
	}

	/**
	 * Whether anything was sent or stored.
	 *
	 * @return bool
	 */
	private function nothing_sent_or_stored(): bool {
		$stored = array_filter( $GLOBALS['salt_test_posts'], static fn( WP_Post $p ): bool => 'salt_submission' === $p->post_type );
		return array() === $GLOBALS['salt_test_mail'] && array() === $stored;
	}

	/**
	 * Each path with its valid payload.
	 *
	 * @return array<string,array{0: string}>
	 */
	public static function paths(): array {
		return array(
			'rest' => array( 'rest' ),
			'page' => array( 'page' ),
		);
	}

	private function payload_for( string $path ): array {
		return 'page' === $path ? $this->page_payload() : $this->valid_payload();
	}

	public function test_unconfigured_form_is_a_404(): void {
		unset( $GLOBALS['salt_test_fields']['option']['contact_recipient'] );

		$result = salt_contact_handle( $this->valid_payload(), 'rest', self::NOW );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 404, $result['status'] );
	}

	public function test_kill_switch_filter_wins_over_configuration(): void {
		add_filter( 'salt_contact_form_enabled', static fn(): bool => false, 20 );

		$this->assertFalse( salt_contact_configured() );
	}

	#[DataProvider( 'paths' )]
	public function test_happy_path_delivers_stores_and_reports_ok( string $path ): void {
		$result = salt_contact_handle( $this->payload_for( $path ), $path, self::NOW );

		$this->assertTrue( $result['ok'], 'Expected success: ' . $result['message'] );
		$this->assertNotEmpty( $GLOBALS['salt_test_mail'], 'Delivery reached wp_mail.' );
		$this->assertFalse( $this->nothing_sent_or_stored(), 'Stored with storage on.' );
		$stored = array_values( array_filter( $GLOBALS['salt_test_posts'], static fn( WP_Post $p ): bool => 'salt_submission' === $p->post_type ) );
		$this->assertSame( 'https://example.test/contact/', $GLOBALS['salt_test_meta'][ $stored[0]->ID ]['salt_page_url'], 'returnTo becomes the stored page URL.' );
	}

	#[DataProvider( 'paths' )]
	public function test_a_send_without_credentials_is_refused_and_keeps_the_entries( string $path ): void {
		$payload = $this->payload_for( $path );
		unset( $payload['formToken'] );

		$result = salt_contact_handle( $payload, $path, self::NOW );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 400, $result['status'] );
		$this->assertSame( 'Ada Lovelace', $result['values']['name'], 'The entries come back.' );
		$this->assertSame( 'I would like a website.', $result['values']['message'] );
		$this->assertTrue( $this->nothing_sent_or_stored() );
	}

	public function test_the_page_path_refuses_a_send_without_its_challenge(): void {
		$result = salt_contact_handle( $this->valid_payload(), 'page', self::NOW );

		$this->assertFalse( $result['ok'] );
		$this->assertTrue( $this->nothing_sent_or_stored() );
	}

	#[DataProvider( 'paths' )]
	public function test_stale_and_tampered_form_tokens_send_and_store_nothing( string $path ): void {
		$token = explode( '.', salt_contact_form_token( self::NOW - 10 ) );
		$cases = array(
			'forged nonce'    => $token[0] . '.' . $token[1] . '.forged',
			'tampered time'   => ( self::NOW - 10 ) . '.deadbeef.' . $token[2],
			'stale'           => salt_contact_form_token( self::NOW - 5 * HOUR_IN_SECONDS ),
			'too fast'        => salt_contact_form_token( self::NOW - 1 ),
			'not three parts' => 'garbage',
		);
		foreach ( $cases as $why => $form_token ) {
			$GLOBALS['salt_test_transients'] = array(); // Clear the rate limit between cases.
			$payload                         = $this->payload_for( $path );
			$payload['formToken']            = $form_token;
			$result                          = salt_contact_handle( $payload, $path, self::NOW );
			$this->assertFalse( $result['ok'], $why );
			$this->assertTrue( $this->nothing_sent_or_stored(), $why );
		}
	}

	#[DataProvider( 'paths' )]
	public function test_a_stale_form_token_is_a_403_and_one_too_young_a_400( string $path ): void {
		$payload              = $this->payload_for( $path );
		$payload['formToken'] = salt_contact_form_token( self::NOW - 5 * HOUR_IN_SECONDS );
		$stale                = salt_contact_handle( $payload, $path, self::NOW );
		$this->assertSame( 403, $stale['status'], 'Stale: the script takes a fresh token and sends again.' );
		$this->assertSame( 'Ada Lovelace', $stale['values']['name'], 'The entries come back.' );

		$payload['formToken'] = salt_contact_form_token( self::NOW - 1 );
		$this->assertSame( 400, salt_contact_handle( $payload, $path, self::NOW )['status'], 'Too young: a bot, still the generic refusal.' );
		$this->assertTrue( $this->nothing_sent_or_stored() );
	}

	#[DataProvider( 'paths' )]
	public function test_bad_nonce_is_a_403( string $path ): void {
		$payload              = $this->payload_for( $path );
		$token                = explode( '.', $payload['formToken'] );
		$payload['formToken'] = $token[0] . '.' . $token[1] . '.forged';

		$this->assertSame( 403, salt_contact_handle( $payload, $path, self::NOW )['status'] );
	}

	#[DataProvider( 'paths' )]
	public function test_honeypot_gets_fake_success_and_no_delivery( string $path ): void {
		$payload            = $this->payload_for( $path );
		$payload['website'] = 'http://spam.example';

		$result = salt_contact_handle( $payload, $path, self::NOW );

		$this->assertTrue( $result['ok'], 'Bots must see success (no oracle).' );
		$this->assertTrue( $this->nothing_sent_or_stored() );
	}

	#[DataProvider( 'paths' )]
	public function test_an_array_honeypot_is_treated_as_bot( string $path ): void {
		$payload            = $this->payload_for( $path );
		$payload['website'] = array( 'spam' );

		$this->assertTrue( salt_contact_handle( $payload, $path, self::NOW )['ok'], 'Bots must see success (no oracle).' );
		$this->assertTrue( $this->nothing_sent_or_stored() );
	}

	#[DataProvider( 'paths' )]
	public function test_missing_honeypot_field_is_treated_as_bot( string $path ): void {
		$payload = $this->payload_for( $path );
		unset( $payload['website'] );

		salt_contact_handle( $payload, $path, self::NOW );

		$this->assertTrue( $this->nothing_sent_or_stored() );
	}

	#[DataProvider( 'paths' )]
	public function test_validation_marks_every_failed_field_at_once( string $path ): void {
		$payload            = $this->payload_for( $path );
		$payload['email']   = 'ruth@';
		$payload['message'] = '';
		$payload['name']    = '<script>x</script>';

		$result = salt_contact_handle( $payload, $path, self::NOW );

		$this->assertSame( 400, $result['status'] );
		$this->assertSame( array( 'name', 'email', 'message' ), array_keys( $result['errors'] ) );
		$this->assertSame( 'ruth@', $result['values']['email'], 'The value sent comes back.' );
		$this->assertSame( '', $result['values']['name'], 'Tags are stripped from what comes back.' );
		$this->assertTrue( $this->nothing_sent_or_stored() );
	}

	public function test_rate_limit_blocks_the_sixth_send(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			salt_contact_handle( $this->valid_payload(), 'rest', self::NOW );
		}
		$result = salt_contact_handle( $this->valid_payload(), 'rest', self::NOW );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 429, $result['status'] );
	}

	public function test_rate_limit_counts_page_sends_too(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			salt_contact_handle( $this->page_payload(), 'page', self::NOW );
		}
		$this->assertSame( 429, salt_contact_handle( $this->page_payload(), 'page', self::NOW )['status'] );
	}

	public function test_the_credentials_endpoint_is_rate_limited_and_never_cached(): void {
		$first = salt_contact_rest_credentials();
		$this->assertSame( 200, $first->get_status() );
		$this->assertSame( 3, count( explode( '.', $first->get_data()['formToken'] ) ), 'A form token: time, signature, nonce.' );
		$this->assertSame( wp_get_nocache_headers(), $first->get_headers(), 'WordPress\'s whole no-cache set.' );

		for ( $i = 1; $i < 20; $i++ ) {
			salt_contact_rest_credentials();
		}
		$limited = salt_contact_rest_credentials();
		$this->assertSame( 429, $limited->get_status() );
		$this->assertArrayNotHasKey( 'formToken', $limited->get_data() );
		$this->assertStringContainsString( 'no-store', $limited->get_headers()['Cache-Control'] );
	}

	public function test_a_rest_send_is_never_cached(): void {
		$request = new class( $this->valid_payload() ) {
			/**
			 * Params.
			 *
			 * @var array<string,string>
			 */
			private array $params;

			/**
			 * Constructor.
			 *
			 * @param array<string,string> $params Params.
			 */
			public function __construct( array $params ) {
				$this->params = $params;
			}

			/**
			 * Params.
			 *
			 * @return array<string,string>
			 */
			public function get_params(): array {
				return $this->params;
			}
		};

		$response = salt_contact_rest_send( $request );

		$this->assertStringContainsString( 'no-store', $response->get_headers()['Cache-Control'] );
		$this->assertSame( wp_get_nocache_headers(), $response->get_headers(), 'WordPress\'s whole no-cache set.' );
	}

	public function test_captcha_required_on_rest_path_when_provider_configured(): void {
		$GLOBALS['salt_test_fields']['option']['captcha_provider']           = 'turnstile';
		$GLOBALS['salt_test_fields']['option']['captcha_turnstile_site_key'] = '1x0000';
		if ( ! defined( 'SALT_TURNSTILE_SECRET' ) ) {
			define( 'SALT_TURNSTILE_SECRET', 'test-secret' );
		}

		// No token → fail closed, no delivery.
		$result = salt_contact_handle( $this->valid_payload(), 'rest', self::NOW );
		$this->assertFalse( $result['ok'] );
		$this->assertTrue( $this->nothing_sent_or_stored() );

		// Scripted siteverify success → delivered.
		$GLOBALS['salt_test_http']['responses'][] = array(
			'code' => 200,
			'body' => wp_json_encode(
				array(
					'success'  => true,
					'hostname' => 'example.test',
				)
			),
		);
		$payload                                  = $this->valid_payload();
		$payload['cf-turnstile-response']         = 'token-abc';
		$this->assertTrue( salt_contact_handle( $payload, 'rest', self::NOW )['ok'] );
	}

	public function test_with_a_provider_the_page_path_takes_its_token_without_a_challenge(): void {
		$GLOBALS['salt_test_fields']['option']['captcha_provider']           = 'turnstile';
		$GLOBALS['salt_test_fields']['option']['captcha_turnstile_site_key'] = '1x0000';
		if ( ! defined( 'SALT_TURNSTILE_SECRET' ) ) {
			define( 'SALT_TURNSTILE_SECRET', 'test-secret' );
		}
		$payload                          = $this->valid_payload(); // A scripted visitor's native fallback: no challenge.
		$payload['cf-turnstile-response'] = 'token-abc';

		$GLOBALS['salt_test_http']['responses'][] = array(
			'code' => 200,
			'body' => wp_json_encode( array( 'success' => false ) ),
		);
		$this->assertFalse( salt_contact_handle( $payload, 'page', self::NOW )['ok'], 'A rejected provider token fails closed.' );
		$this->assertTrue( $this->nothing_sent_or_stored() );

		$GLOBALS['salt_test_http']['responses'][] = array(
			'code' => 200,
			'body' => wp_json_encode(
				array(
					'success'  => true,
					'hostname' => 'example.test',
				)
			),
		);
		$this->assertTrue( salt_contact_handle( $payload, 'page', self::NOW )['ok'] );

		unset( $payload['cf-turnstile-response'] );
		$this->assertSame( 400, salt_contact_handle( $payload, 'page', self::NOW )['status'], 'Neither challenge nor provider token: refused.' );
	}

	public function test_the_page_path_checks_the_arithmetic_challenge(): void {
		$wrong = salt_contact_handle( $this->page_payload( false ), 'page', self::NOW );
		$this->assertFalse( $wrong['ok'] );
		$this->assertArrayHasKey( 'challengeAnswer', $wrong['errors'] );
		$this->assertTrue( $this->nothing_sent_or_stored() );

		$this->assertTrue( salt_contact_handle( $this->page_payload(), 'page', self::NOW )['ok'] );
	}

	public function test_a_challenge_is_single_use(): void {
		$payload = $this->page_payload();
		$this->assertTrue( salt_contact_handle( $payload, 'page', self::NOW )['ok'] );
		$this->assertFalse( salt_contact_handle( $payload, 'page', self::NOW )['ok'], 'Replayed: the answer was burned.' );
	}

	public function test_nothing_is_stored_when_storage_is_off(): void {
		$GLOBALS['salt_test_fields']['option']['submissions_store'] = false;

		$this->assertTrue( salt_contact_handle( $this->valid_payload(), 'rest', self::NOW )['ok'] );
		$stored = array_filter( $GLOBALS['salt_test_posts'], static fn( WP_Post $p ): bool => 'salt_submission' === $p->post_type );
		$this->assertSame( array(), $stored );
	}

	// -----------------------------------------------------------------
	// Phone (contact_form_phone: off, optional, required).
	// -----------------------------------------------------------------

	#[DataProvider( 'paths' )]
	public function test_phone_off_ignores_a_sent_phone( string $path ): void {
		$payload          = $this->payload_for( $path );
		$payload['phone'] = '0117 496 0123';

		$this->assertTrue( salt_contact_handle( $payload, $path, self::NOW )['ok'] );
		$this->assertStringNotContainsString( '0117', (string) wp_json_encode( $GLOBALS['salt_test_mail'] ) );
		$this->assertStringNotContainsString( 'salt_phone', (string) wp_json_encode( $GLOBALS['salt_test_meta'] ) );
	}

	#[DataProvider( 'paths' )]
	public function test_phone_optional_is_mailed_and_stored_only_when_given( string $path ): void {
		$GLOBALS['salt_test_fields']['option']['contact_form_phone'] = 'optional';

		$this->assertTrue( salt_contact_handle( $this->payload_for( $path ), $path, self::NOW )['ok'] );
		$this->assertStringNotContainsString( 'Phone:', (string) wp_json_encode( $GLOBALS['salt_test_mail'] ), 'No phone line when none was given.' );

		$GLOBALS['salt_test_mail'] = array();
		$payload                   = $this->payload_for( $path );
		$payload['phone']          = '+44 (0)117 496 0123';
		$this->assertTrue( salt_contact_handle( $payload, $path, self::NOW )['ok'] );
		$this->assertStringContainsString( 'Phone: +44 (0)117 496 0123', (string) wp_json_encode( $GLOBALS['salt_test_mail'] ) );
		$this->assertStringContainsString( '+44 (0)117 496 0123', (string) wp_json_encode( $GLOBALS['salt_test_meta'] ) );

		$bad          = $this->payload_for( $path );
		$bad['phone'] = 'call me <b>maybe</b>';
		$result       = salt_contact_handle( $bad, $path, self::NOW );
		$this->assertArrayHasKey( 'phone', $result['errors'], 'Only a tel-safe number passes.' );
	}

	#[DataProvider( 'paths' )]
	public function test_phone_required_refuses_a_send_without_one( string $path ): void {
		$GLOBALS['salt_test_fields']['option']['contact_form_phone'] = 'required';

		$result = salt_contact_handle( $this->payload_for( $path ), $path, self::NOW );

		$this->assertSame( array( 'phone' ), array_keys( $result['errors'] ) );
		$this->assertTrue( $this->nothing_sent_or_stored() );
	}

	// -----------------------------------------------------------------
	// Enquiry types: a route's value, its label for routing and storage.
	// -----------------------------------------------------------------

	public function test_enquiry_values_default_to_the_label_and_are_unique(): void {
		$GLOBALS['salt_test_fields']['option']['contact_routes'] = array(
			array(
				'enquiry_type' => 'Garden design',
				'recipient'    => 'design@example.test',
			),
			array(
				'enquiry_type' => 'Garden Design!',
				'recipient'    => '',
			),
			array(
				'enquiry_type' => 'Maintenance',
				'value'        => 'upkeep',
				'recipient'    => '',
			),
		);

		$this->assertSame(
			array(
				array(
					'value' => 'garden-design',
					'label' => 'Garden design',
				),
				array(
					'value' => 'garden-design-2',
					'label' => 'Garden Design!',
				),
				array(
					'value' => 'upkeep',
					'label' => 'Maintenance',
				),
			),
			salt_contact_enquiry_types()
		);
	}

	#[DataProvider( 'paths' )]
	public function test_a_route_saved_before_values_routes_by_its_value( string $path ): void {
		// Saved before 6.0: a label and a recipient, no value.
		$GLOBALS['salt_test_fields']['option']['contact_routes']    = array(
			array(
				'enquiry_type' => 'Garden design',
				'recipient'    => 'design@example.test',
			),
			array(
				'enquiry_type' => 'Maintenance',
				'recipient'    => 'upkeep@example.test',
			),
		);
		$GLOBALS['salt_test_fields']['option']['submissions_store'] = true;
		$payload                = $this->payload_for( $path );
		$payload['enquiryType'] = 'maintenance';

		$this->assertTrue( salt_contact_handle( $payload, $path, self::NOW )['ok'] );
		$mail = end( $GLOBALS['salt_test_mail'] );
		$this->assertStringContainsString( 'upkeep@example.test', (string) wp_json_encode( $mail ), 'Mailed to its route.' );
		$this->assertStringContainsString( 'Maintenance', (string) wp_json_encode( $GLOBALS['salt_test_meta'] ), 'Stored by its label, as before.' );
	}

	#[DataProvider( 'paths' )]
	public function test_an_unknown_enquiry_type_is_a_field_error_never_the_first_route( string $path ): void {
		$GLOBALS['salt_test_fields']['option']['contact_routes'] = array(
			array(
				'enquiry_type' => 'Garden design',
				'recipient'    => 'design@example.test',
			),
			array(
				'enquiry_type' => 'Maintenance',
				'recipient'    => '',
			),
		);
		$payload                = $this->payload_for( $path );
		$payload['enquiryType'] = 'not-a-configured-route';

		$result = salt_contact_handle( $payload, $path, self::NOW );

		$this->assertArrayHasKey( 'enquiryType', $result['errors'] );
		$this->assertTrue( $this->nothing_sent_or_stored() );
	}

	public function test_with_no_select_drawn_the_type_is_general(): void {
		$this->assertTrue( salt_contact_handle( $this->valid_payload(), 'rest', self::NOW )['ok'] );
		$this->assertStringContainsString( 'general', strtolower( (string) wp_json_encode( end( $GLOBALS['salt_test_mail'] ) ) ) );
	}

	// -----------------------------------------------------------------
	// returnTo: same-site only.
	// -----------------------------------------------------------------

	public function test_return_to_is_a_same_site_path_or_nothing(): void {
		$this->assertSame( '/contact/', salt_contact_return_to( '/contact/' ) );
		$this->assertSame( '/contact/?page_id=5', salt_contact_return_to( 'https://example.test/contact/?page_id=5' ) );
		$this->assertSame( '/?page_id=5', salt_contact_return_to( '/?utm_source=x&page_id=5&fbclid=abc&salt-contact=ok' ), 'Only WordPress\'s public query vars stay.' );
		foreach ( array( 'https://evil.example/x', '//evil.example/x', '/\\evil.example', 'javascript:alert(1)', "/a\r\nLocation: x", 'contact' ) as $raw ) {
			$this->assertSame( '', salt_contact_return_to( $raw ), $raw );
		}
	}

	public function test_a_foreign_return_to_is_never_stored(): void {
		$payload             = $this->valid_payload();
		$payload['returnTo'] = 'https://evil.example/phish';

		$this->assertTrue( salt_contact_handle( $payload, 'rest', self::NOW )['ok'] );
		$this->assertStringNotContainsString( 'evil.example', (string) wp_json_encode( $GLOBALS['salt_test_meta'] ) );
		$this->assertStringNotContainsString( 'evil.example', (string) wp_json_encode( $GLOBALS['salt_test_mail'] ) );
	}

	// -----------------------------------------------------------------
	// The self-posted page (no script).
	// -----------------------------------------------------------------

	public function test_the_self_posted_page_answers_in_place_uncached_with_fresh_credentials(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET['salt-contact-send'] = 'enquire-self';
		$_POST                     = $this->valid_payload();
		unset( $_POST['formToken'] ); // A no-JS visitor's first send.
		salt_contact_self_post_aside( true );
		apply_filters( 'query_vars', array( 'name' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- As WP::parse_request() applies it.
		salt_contact_self_post_restore();

		salt_contact_self_post();

		$this->assertTrue( $GLOBALS['salt_test_nocache'], 'nocache_headers() sent.' );
		$this->assertSame( 400, $GLOBALS['salt_test_status'] );
		$state = salt_contact_form_states()['enquire-self'];
		$this->assertSame( 'invalid', $state['state'] );
		$this->assertSame( 'Ada Lovelace', $state['values']['name'], 'Nothing lost on the extra round trip.' );
		$this->assertSame( 'problem', $state['status']['tone'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $state['challenge']['token'] );
		$this->assertCount( 3, explode( '.', $state['formToken'] ) );
		$this->assertTrue( $this->nothing_sent_or_stored() );
	}

	/**
	 * WP::parse_request() as far as a self-post meets it: 'do_parse_request',
	 * then its final 'query_vars', then (after reading $_POST) 'parse_request'.
	 *
	 * @param string[]      $vars   The public query vars.
	 * @param callable|null $during What other code does to $_POST before 'parse_request'.
	 * @return bool What 'do_parse_request' answered.
	 */
	private function parse_request( array $vars, ?callable $during = null ): bool {
		$parse = salt_contact_self_post_aside( true );
		apply_filters( 'query_vars', $vars ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- As WP::parse_request() applies it.
		$this->parsed = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The test reads the request it built.
		if ( null !== $during ) {
			$during();
		}
		salt_contact_self_post_restore();
		return $parse;
	}

	/**
	 * $_POST as WordPress read it while parsing.
	 *
	 * @var array<string,mixed>
	 */
	private array $parsed = array();

	public function test_a_self_post_keeps_its_fields_out_of_the_parsed_query(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET['salt-contact-send'] = 'contact';
		$_POST                     = array(
			'name'     => 'Ada Lovelace',
			'page_id'  => '99',
			'message'  => 'Hello',
			'returnTo' => '/contact/',
		);

		$this->assertTrue( $this->parse_request( array( 'name', 'pagename', 'page_id' ) ) );
		$this->assertSame(
			array(
				'message'  => 'Hello',
				'returnTo' => '/contact/',
			),
			$this->parsed,
			'WordPress parses the request without the posted query vars.'
		);
		$this->assertSame( 'Ada Lovelace', $_POST['name'], 'Put back once parsed.' ); // phpcs:ignore WordPress.Security -- The test reads the request it built.
		$this->assertSame( '99', $_POST['page_id'] ); // phpcs:ignore WordPress.Security -- As above.
		$this->assertSame( 'Ada Lovelace', salt_contact_self_post_fields()['name'], 'Set aside for the handler.' );
	}

	public function test_only_the_fields_taken_out_are_put_back(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET['salt-contact-send'] = 'contact';
		$_POST                     = array(
			'name'     => 'Ada Lovelace',
			'message'  => 'Hello',
			'returnTo' => '/contact/',
		);

		$this->parse_request(
			array( 'name' ),
			static function (): void {
				// Other code at work on $_POST while WordPress parses.
				$_POST['message'] = 'Changed';
				$_POST['added']   = 'Kept';
			}
		);

		$this->assertEquals( // Order aside: the request as WordPress and that code left it, with `name` back.
			array(
				'name'     => 'Ada Lovelace',
				'message'  => 'Changed',
				'returnTo' => '/contact/',
				'added'    => 'Kept',
			),
			$_POST // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The test reads the request it built.
		);
	}

	public function test_the_set_aside_neither_applies_query_vars_itself_nor_acts_when_wordpress_will_not_parse(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET['salt-contact-send'] = 'contact';
		$_POST                     = array(
			'name'     => 'Ada Lovelace',
			'returnTo' => '/contact/',
		);
		$applied                   = 0;
		add_filter(
			'query_vars', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
			static function ( $vars ) use ( &$applied ) {
				++$applied;
				return $vars;
			}
		);

		$this->assertFalse( salt_contact_self_post_aside( false ), 'Another handler answers the request.' );
		$this->assertSame( 0, $applied, 'WordPress\'s query_vars filter is not applied by the theme.' );
		apply_filters( 'query_vars', array( 'name' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Were WordPress to parse after all.
		$this->assertSame( 'Ada Lovelace', $_POST['name'], 'Nothing taken out.' ); // phpcs:ignore WordPress.Security -- The test reads the request it built.

		$applied       = 0;
		$GLOBALS['wp'] = (object) array( 'public_query_vars' => array( 'page_id' ) );
		$this->assertSame( '/contact/?page_id=7', salt_contact_return_to( '/contact/?page_id=7&utm=x' ) );
		unset( $GLOBALS['wp'] );
		$this->assertSame( 0, $applied, 'Nor to keep a page\'s own query.' );
	}

	public function test_another_post_is_parsed_as_sent(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array( 'name' => 'x' );

		$this->parse_request( array( 'name' ) );

		$this->assertSame( array( 'name' => 'x' ), $this->parsed );
	}

	public function test_a_self_post_with_the_form_off_handles_and_mints_nothing(): void {
		unset( $GLOBALS['salt_test_fields']['option']['contact_recipient'] );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET['salt-contact-send'] = 'form-off';
		$_POST                     = $this->valid_payload();

		salt_contact_self_post();

		$this->assertArrayNotHasKey( 'form-off', salt_contact_form_states() );
		$this->assertSame( array(), $GLOBALS['salt_test_transients'], 'No challenge, no rate-limit entry.' );
	}

	public function test_a_limited_client_gets_no_fresh_credentials(): void {
		$state = salt_contact_response_state(
			array(
				'ok'      => false,
				'status'  => 429,
				'message' => 'Too many.',
				'errors'  => array(),
				'values'  => array( 'name' => 'Ada' ),
			)
		);

		$this->assertArrayNotHasKey( 'challenge', $state );
		$this->assertArrayNotHasKey( 'formToken', $state );
		$this->assertSame( array(), $GLOBALS['salt_test_transients'], 'Nothing minted.' );
		$this->assertSame( 'Ada', $state['values']['name'], 'The entries still come back.' );
	}

	public function test_credential_less_posts_spend_the_credentials_bucket_not_the_sends(): void {
		$bare = $this->valid_payload();
		unset( $bare['formToken'] );
		for ( $i = 0; $i < 6; $i++ ) {
			$this->assertSame( 400, salt_contact_handle( $bare, 'page', self::NOW )['status'], 'A first post, refused for its credentials.' );
		}
		$this->assertTrue( salt_contact_handle( $this->page_payload(), 'page', self::NOW )['ok'], 'The five sends are untouched.' );

		for ( $i = 6; $i < 20; $i++ ) {
			salt_contact_handle( $bare, 'page', self::NOW );
		}
		$this->assertSame( 429, salt_contact_handle( $bare, 'page', self::NOW )['status'], 'The credentials bucket bounds them (20 / 10 minutes).' );
	}

	public function test_another_form_posted_to_the_answered_page_is_not_a_send(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET['salt-contact-send'] = 'enquire-other';
		$_POST                     = array(
			'name'    => 'Ada Lovelace',
			'comment' => 'Another form on the page.',
		);

		$this->assertTrue( $this->parse_request( array( 'name' ) ) );
		salt_contact_self_post();

		$this->assertSame( $_POST, $this->parsed, 'WordPress parses it as sent.' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The test reads the request it built.
		$this->assertArrayNotHasKey( 'enquire-other', salt_contact_form_states() );
		$this->assertFalse( $GLOBALS['salt_test_nocache'] );
		$this->assertSame( array(), $GLOBALS['salt_test_transients'], 'No credentials bucket spent, nothing minted.' );
	}

	public function test_a_get_or_another_page_post_is_not_a_send(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET['salt-contact-send'] = 'enquire-get';
		salt_contact_self_post();
		$this->assertFalse( $GLOBALS['salt_test_nocache'] );
		$this->assertArrayNotHasKey( 'enquire-get', salt_contact_form_states() );
	}

	// -----------------------------------------------------------------
	// The pre-6.0 admin-post form, kept one release.
	// -----------------------------------------------------------------

	public function test_the_legacy_form_maps_onto_the_handler(): void {
		$GLOBALS['salt_test_fields']['option']['contact_routes'] = array(
			array(
				'enquiry_type' => 'Maintenance',
				'recipient'    => '',
			),
		);
		$fields = salt_contact_legacy_fields(
			array(
				'salt_contact_nonce'   => wp_create_nonce( 'salt_contact' ),
				'salt_hp'              => '',
				'salt_time'            => salt_contact_time_token( self::NOW - 10 ),
				'salt_contact_name'    => 'Ada Lovelace',
				'salt_contact_email'   => 'ada@example.org',
				'salt_contact_message' => 'Hello.',
				'salt_contact_type'    => 'maintenance',
				'salt_page_url'        => 'https://example.test/contact/',
			)
		);

		$this->assertSame( 'maintenance', $fields['enquiryType'], 'An old label maps to its value.' );
		$this->assertTrue( salt_contact_handle( $fields, 'legacy', self::NOW )['ok'] );

		$unmatched                       = $fields;
		$unmatched['enquiryType']        = salt_contact_legacy_fields( array( 'salt_contact_type' => 'Old route' ) )['enquiryType'];
		$GLOBALS['salt_test_mail']       = array();
		$GLOBALS['salt_test_transients'] = array();
		$this->assertTrue( salt_contact_handle( $unmatched, 'legacy', self::NOW )['ok'], 'An unmatched old label still sends.' );
		$this->assertStringContainsString( 'general', strtolower( (string) wp_json_encode( end( $GLOBALS['salt_test_mail'] ) ) ), 'As \'general\', as before.' );

		$no_hp = $fields;
		unset( $no_hp['website'] );
		$GLOBALS['salt_test_mail'] = array();
		salt_contact_handle( $no_hp, 'legacy', self::NOW );
		$this->assertSame( array(), $GLOBALS['salt_test_mail'], 'Still a bot without the honeypot field.' );
	}

	public function test_the_legacy_status_flag_never_echoes_the_address(): void {
		$_GET['salt-contact'] = '<script>alert(1)</script>';
		$status               = salt_contact_request_status();
		unset( $_GET['salt-contact'] );

		$this->assertSame( 'error', $status['status'] );
		$this->assertStringNotContainsString( 'script', $status['message'] );
	}
}
