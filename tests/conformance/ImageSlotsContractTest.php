<?php
/**
 * The theme's image candidates and slots against the pinned salt-contract's
 * contract/image-sizes.json.
 *
 * salt_image_candidates() and salt_image_slots() (inc/media.php) are a copy
 * of the slots the collection sections draw. This reads the installed
 * contract package, so it lives in the conformance suite, the only one
 * that reads node_modules (phpunit.xml.dist).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__, 2 ) . '/inc/media.php';

final class ImageSlotsContractTest extends SaltTestCase {

	private const SIZES = __DIR__ . '/../../node_modules/@lightlysaltedhq/salt-contract/contract/image-sizes.json';

	/**
	 * The pinned image-sizes.json.
	 *
	 * @return array<string,mixed>
	 */
	private static function contract(): array {
		return json_decode( (string) file_get_contents( self::SIZES ), true );
	}

	public function test_the_candidates_are_the_contracts(): void {
		$this->assertSame( self::contract()['candidates'], salt_image_candidates() );
	}

	public function test_every_slot_the_theme_draws_is_the_contracts(): void {
		$slots = self::contract()['slots'];
		foreach ( salt_image_slots() as $name => $slot ) {
			$this->assertArrayHasKey( $name, $slots, "{$name} is a contract slot." );
			$this->assertSame( $slots[ $name ]['sizes'], $slot['sizes'], "{$name}: unbanded sizes." );
			$this->assertSame( $slots[ $name ]['bands'] ?? null, $slot['bands'] ?? null, "{$name}: every band." );
		}
	}
}
