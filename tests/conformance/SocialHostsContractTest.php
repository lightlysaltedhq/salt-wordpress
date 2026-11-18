<?php
/**
 * The theme's social hosts table against the pinned salt-contract's.
 *
 * salt_chrome_social_hosts() (inc/services/chrome.php) is a copy of
 * contract/socials.json: which host names which network. This reads the
 * installed contract package, so it lives in the conformance suite, the only
 * one that reads node_modules (phpunit.xml.dist).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__, 2 ) . '/inc/services/chrome.php';

final class SocialHostsContractTest extends SaltTestCase {

	private const SOCIALS = __DIR__ . '/../../node_modules/@lightlysaltedhq/salt-contract/contract/socials.json';

	/**
	 * The contract's table as host => [network id, network name], in its order.
	 *
	 * @return array<string,array{string,string}>
	 */
	private static function contract_hosts(): array {
		$socials = json_decode( (string) file_get_contents( self::SOCIALS ), true );
		$hosts   = array();
		foreach ( $socials['networks'] as $network ) {
			foreach ( $network['hosts'] as $host ) {
				$hosts[ $host['host'] ] = array( $network['id'], $network['name'] );
			}
		}
		return $hosts;
	}

	public function test_every_host_names_the_contracts_network_in_both_directions(): void {
		$contract = self::contract_hosts();
		$theme    = array_map(
			static fn( array $entry ): array => array( $entry['icon'], $entry['label'] ),
			salt_chrome_social_hosts()
		);

		$this->assertSame( array(), array_values( array_diff( array_keys( $contract ), array_keys( $theme ) ) ), 'Hosts in contract/socials.json the theme lacks.' );
		$this->assertSame( array(), array_values( array_diff( array_keys( $theme ), array_keys( $contract ) ) ), 'Hosts the theme has that contract/socials.json does not.' );
		foreach ( $contract as $host => $network ) {
			$this->assertSame( $network, $theme[ $host ], "{$host}: network id (the icon) and name (the label)." );
		}
		$this->assertSame( array_keys( $contract ), array_keys( $theme ), 'In the contract\'s order: networks are tried in that order (socials.json rule).' );
	}
}
