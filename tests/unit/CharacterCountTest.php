<?php
/**
 * The character count (inc/services/character-count.php): how a length is
 * counted, and what one field's count hands admin/character-count.js. The
 * count beside the field, and that it never refuses a save, are proved in
 * a real browser by the real ACF harness (tests/real-acf/browser.mjs).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

final class CharacterCountTest extends SaltTestCase {

	/**
	 * Text, its length as the count shows it.
	 *
	 * @return array<string,array{string,int}>
	 */
	public static function length_provider(): array {
		return array(
			'one-byte characters'          => array( str_repeat( 'a', 121 ), 121 ),
			'two-byte characters'          => array( str_repeat( 'é', 301 ), 301 ),
			'four-byte characters'         => array( str_repeat( '🌱', 120 ), 120 ),
			'a CRLF counted as one'        => array( str_repeat( 'a', 59 ) . "\r\n" . str_repeat( 'a', 60 ), 120 ),
			'a lone LF counted as one too' => array( "a\nb", 3 ),
		);
	}

	#[DataProvider( 'length_provider' )]
	public function test_a_length_is_counted_in_code_points_with_a_crlf_as_one( string $text, int $length ): void {
		$this->assertSame( $length, salt_character_length( $text ) );
	}

	public function test_a_field_is_added_to_the_one_script_with_its_target_and_notes(): void {
		salt_character_count( 'title', 120, array( 60 => 'A softer note' ) );
		salt_character_count( 'excerpt', 300 );

		$this->assertSame( array( 'salt-character-count' ), array_keys( $GLOBALS['salt_test_enqueued']['scripts'] ) );
		$this->assertMatchesRegularExpression( '#^.*/assets/dist/admin/character-count\.[0-9a-f]{10}\.js$#', $GLOBALS['salt_test_enqueued']['scripts']['salt-character-count']['src'] );
		$this->assertSame(
			array(
				'window.saltCharacterCount = ( window.saltCharacterCount || [] ).concat( [{"field":"title","target":120,"marks":[{"at":60,"note":"A softer note","strong":false},{"at":120,"note":"Over the suggested 120 characters","strong":true}]}] );',
				'window.saltCharacterCount = ( window.saltCharacterCount || [] ).concat( [{"field":"excerpt","target":300,"marks":[{"at":300,"note":"Over the suggested 300 characters","strong":true}]}] );',
			),
			$GLOBALS['salt_test_inline_scripts']['salt-character-count']['before']
		);
	}
}
