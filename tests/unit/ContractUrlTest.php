<?php
/**
 * salt_contract_url(): same-site links drawn root-relative, as the
 * contract's fixtures write them; everything else unchanged.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

final class ContractUrlTest extends SaltTestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function urls(): array {
		$home = rtrim( home_url( '/' ), '/' );
		$host = (string) wp_parse_url( $home, PHP_URL_HOST );
		return array(
			'home root'               => array( $home . '/', '/' ),
			'home bare'               => array( $home, '/' ),
			'path, query, fragment'   => array( $home . '/journal/?page=2#top', '/journal/?page=2#top' ),
			'other scheme same host'  => array( 'http://' . $host . '/team/priya-shah/', '/team/priya-shah/' ),
			'protocol-relative'       => array( '//' . $host . '/journal/', '/journal/' ),
			'host case-insensitive'   => array( 'https://' . strtoupper( $host ) . '/a/', '/a/' ),
			'other host'              => array( 'https://example.org/journal/', 'https://example.org/journal/' ),
			'same host other port'    => array( 'https://' . $host . ':8443/a/', 'https://' . $host . ':8443/a/' ),
			'subdomain is other host' => array( 'https://www.' . $host . '/a/', 'https://www.' . $host . '/a/' ),
			'mailto'                  => array( 'mailto:hello@' . $host, 'mailto:hello@' . $host ),
			'tel'                     => array( 'tel:+441234567890', 'tel:+441234567890' ),
			'anchor'                  => array( '#contact', '#contact' ),
			'already relative'        => array( '/journal/', '/journal/' ),
			'empty'                   => array( '', '' ),
		);
	}

	#[DataProvider( 'urls' )]
	public function test_contract_url( string $url, string $expected ): void {
		$this->assertSame( $expected, salt_contract_url( $url ) );
	}
}
