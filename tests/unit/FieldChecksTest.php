<?php
/**
 * Checks on save the contract asks of a generated field (inc/fields/checks.php):
 * the email and url formats, a textarea's line limit, and a service never
 * related to itself.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 2 ) . '/inc/fields/checks.php';

final class FieldChecksTest extends SaltTestCase {

	/**
	 * Every generated field, by key, from the committed JSON (sections and
	 * collections).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function fields(): array {
		$found = array();
		$walk  = static function ( array $fields ) use ( &$walk, &$found ): void {
			foreach ( $fields as $field ) {
				$found[ (string) ( $field['key'] ?? '' ) ] = $field;
				$walk( $field['sub_fields'] ?? array() );
				foreach ( $field['layouts'] ?? array() as $layout ) {
					$walk( $layout['sub_fields'] );
				}
			}
		};
		foreach ( array( 'sections', 'collections' ) as $file ) {
			foreach ( json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/fields/generated/' . $file . '.json' ), true ) as $group ) {
				$walk( $group['fields'] );
			}
		}
		return $found;
	}

	/**
	 * A generated field by key, from the committed JSON.
	 *
	 * @param string $key Field key.
	 * @return array<string,mixed>
	 */
	private static function field( string $key ): array {
		return self::fields()[ $key ];
	}

	/**
	 * Value, whether the contract's email format takes it.
	 *
	 * @return array<string,array{string,bool}>
	 */
	public static function email_provider(): array {
		return array(
			'an address'           => array( 'hello@hollinfarm.example', true ),
			'a subdomain'          => array( 'a.b@mail.example.co.uk', true ),
			'no @'                 => array( 'hollinfarm.example', false ),
			'two @'                => array( 'a@b@c.example', false ),
			'no dot in the domain' => array( 'hello@localhost', false ),
			'a space'              => array( 'hello there@x.example', false ),
			'nothing after a dot'  => array( 'hello@x.', false ),
		);
	}

	#[DataProvider( 'email_provider' )]
	public function test_the_email_format( string $value, bool $valid ): void {
		$field  = self::field( 'field_salt_collection_team_email' );
		$result = salt_validate_contract_value( true, $value, $field );

		$this->assertSame( 'email', $field['salt']['format'] );
		$valid ? $this->assertTrue( $result ) : $this->assertSame( 'Enter an email address: one @, a domain with a dot, and no spaces.', $result );
	}

	public function test_a_location_email_is_checked_too_and_an_empty_one_is_left_to_required(): void {
		$field = self::field( 'field_salt_collection_locations_email' );

		$this->assertIsString( salt_validate_contract_value( true, 'nope', $field ) );
		$this->assertTrue( salt_validate_contract_value( true, '', $field ) );
		$this->assertSame( 'ACF\'s own message', salt_validate_contract_value( 'ACF\'s own message', 'nope', $field ), 'ACF\'s message comes first.' );
	}

	/**
	 * Value, whether the contract's url format takes it: an absolute http,
	 * https, mailto or tel address (the founder's ruling on PR #26).
	 *
	 * @return array<string,array{string,bool}>
	 */
	public static function url_provider(): array {
		return array(
			'https'              => array( 'https://hollinfarm.example/visit', true ),
			'http'               => array( 'http://hollinfarm.example', true ),
			'mailto'             => array( 'mailto:hello@hollinfarm.example', true ),
			'tel'                => array( 'tel:+441234567890', true ),
			'a path'             => array( '/contact', false ),
			'an anchor'          => array( '#pricing', false ),
			'scheme-relative'    => array( '//x.example', false ),
			'javascript'         => array( 'javascript:alert(1)', false ),
			'ftp'                => array( 'ftp://files.example', false ),
			'https with no host' => array( 'https://', false ),
			'a bare domain'      => array( 'hollinfarm.example', false ),
		);
	}

	#[DataProvider( 'url_provider' )]
	public function test_the_url_format_on_every_field_that_carries_it( string $value, bool $valid ): void {
		$url_fields = array_filter( self::fields(), static fn( array $field ): bool => 'url' === ( $field['salt']['format'] ?? null ) );

		// socials.url, externalUrl, the features and logos items' url, and a link's url.
		foreach ( array( 'field_salt_collection_team_socials_url', 'field_salt_collection_case_studies_external_url', 'field_salt_features_items_url', 'field_salt_logos_items_url', 'field_salt_hero_buttons_link_url', 'field_salt_collection_services_cta_url' ) as $key ) {
			$this->assertArrayHasKey( $key, $url_fields );
		}
		foreach ( $url_fields as $key => $field ) {
			$result = salt_validate_contract_value( true, $value, $field );
			$valid
				? $this->assertTrue( $result, $key )
				: $this->assertSame( 'Enter a full address that starts with http://, https://, mailto: or tel:. A path such as /contact or an anchor such as #pricing is not accepted; link to a page on this site with an internal link instead.', $result, $key );
		}
	}

	public function test_a_link_url_is_only_shown_and_so_only_submitted_when_the_link_is_external(): void {
		foreach ( self::fields() as $key => $field ) {
			if ( empty( $field['salt']['link'] ) ) {
				continue;
			}
			$url = array_column( $field['sub_fields'], null, 'name' )['url'];
			$this->assertSame( 'url', $url['salt']['format'] ?? null, $key );
			$this->assertSame(
				array(
					array(
						array(
							'field'    => $key . '_type',
							'operator' => '==',
							'value'    => 'external',
						),
					),
				),
				$url['conditional_logic'],
				$key
			);
		}
	}

	public function test_the_street_holds_at_most_two_lines(): void {
		$field = self::field( 'field_salt_collection_locations_address_street' );

		$this->assertSame( 2, $field['salt']['maxLines'] );
		$this->assertTrue( salt_validate_contract_value( true, '1 High Street', $field ) );
		$this->assertTrue( salt_validate_contract_value( true, "Unit 4\r\nHigh Street\r\n", $field ), 'A trailing line break starts no line.' );
		$this->assertSame( 'Use at most 2 lines.', salt_validate_contract_value( true, "Unit 4\nThe Yard\nHigh Street", $field ) );
	}

	public function test_a_service_is_never_related_to_itself(): void {
		$_POST['_acf_post_id'] = '7';
		try {
			$self  = salt_validate_related_services( true, array( '5', '7' ) );
			$other = salt_validate_related_services( true, array( '5', '6' ) );
		} finally {
			unset( $_POST['_acf_post_id'] );
		}

		$this->assertSame( 'A service cannot be related to itself. Remove it from the list.', $self );
		$this->assertTrue( $other );
	}
}
