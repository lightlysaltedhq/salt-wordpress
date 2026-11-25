<?php
/**
 * Every Flexible Content layout is split into logic and view (ID-3587).
 *
 * The layout list comes from docs/contracts/slug-registry.json (generated
 * from inc/fields/ by bin/extract-slugs.php), mapped "_" → "-" as
 * template-parts/flexible-content.php does. For each layout:
 *  - inc/sections/{slug}.php exists and defines
 *    salt_resolve_section_{slug, "-" as "_"}() (checked through
 *    salt_section_resolver(), the same lookup the render entry uses);
 *  - template-parts/sections/{slug}.php exists, and its PHP calls none of
 *    the logic-only functions below. The scan works on PHP tokens, so
 *    comments, strings and inline HTML never count.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

final class SectionCompletenessTest extends SaltTestCase {

	/**
	 * Functions a view must never call (CONVENTIONS.md, "The view").
	 *
	 * @var string[]
	 */
	private const VIEW_FORBIDDEN_CALLS = array(
		'get_field',
		'get_sub_field',
		'salt_resolve_query',
		'salt_schema_add',
		'salt_log',
		'salt_section',
		'wp_create_nonce',
	);

	/**
	 * Theme root.
	 *
	 * @return string
	 */
	private static function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Every Flexible Content layout, as hyphenated template slugs.
	 *
	 * @return array<string,array{0:string}>
	 */
	public static function layouts(): array {
		$registry = json_decode( (string) file_get_contents( self::root() . '/docs/contracts/slug-registry.json' ), true );
		$layouts  = is_array( $registry ) && is_array( $registry['layouts'] ?? null ) ? $registry['layouts'] : array();

		$cases = array();
		foreach ( $layouts as $layout ) {
			$slug           = str_replace( '_', '-', (string) $layout );
			$cases[ $slug ] = array( $slug );
		}
		return $cases;
	}

	public function test_the_layout_list_is_not_empty(): void {
		$this->assertNotEmpty( self::layouts(), 'docs/contracts/slug-registry.json lists no layouts.' );
	}

	#[DataProvider( 'layouts' )]
	public function test_layout_has_a_parent_resolver( string $slug ): void {
		$this->assertFileExists( self::root() . '/inc/sections/' . $slug . '.php' );
		$this->assertSame(
			salt_section_resolver_function( $slug ),
			salt_section_resolver( $slug )
		);
	}

	#[DataProvider( 'layouts' )]
	public function test_layout_has_a_view_that_calls_no_logic( string $slug ): void {
		$view = self::root() . '/template-parts/sections/' . $slug . '.php';
		$this->assertFileExists( $view );

		$this->assertSame(
			array(),
			self::forbidden_calls( (string) file_get_contents( $view ) ),
			"template-parts/sections/{$slug}.php is a view: it must not call logic-only functions."
		);
	}

	public function test_the_scan_finds_calls_and_ignores_comments_and_strings(): void {
		$source = <<<'PHP'
<?php
// get_field( 'in a line comment' );
/* salt_log( 'in a block comment' ); */
$a = 'get_sub_field( "in a string" )';
$b = "salt_schema_add( in a double-quoted string )";
$c = $salt_log;
$d = salt_section_contract_args( 'x', array() );
echo salt_section ( array(), null );
echo \wp_create_nonce( 'x' );
?>
<p>get_field( 'in inline HTML' )</p>
<?php
$e = $obj->get_field( 'a method, not the function' );
PHP;

		$this->assertSame( array( 'salt_section@8', 'wp_create_nonce@9' ), self::forbidden_calls( $source ) );
	}

	/**
	 * Calls to VIEW_FORBIDDEN_CALLS in PHP source, as "name@line".
	 *
	 * A call is a function-name token followed (after whitespace or
	 * comments) by "(" and not preceded by "->", "?->", "::" or
	 * "function". Comments, strings and inline HTML are other tokens, so
	 * they never match.
	 *
	 * @param string $source PHP source.
	 * @return string[]
	 */
	private static function forbidden_calls( string $source ): array {
		$tokens = token_get_all( $source );
		$skip   = array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT );
		$found  = array();
		$count  = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) || ! in_array( $token[0], array( T_STRING, T_NAME_FULLY_QUALIFIED ), true ) ) {
				continue;
			}
			$name = strtolower( ltrim( $token[1], '\\' ) );
			if ( ! in_array( $name, self::VIEW_FORBIDDEN_CALLS, true ) ) {
				continue;
			}

			$next = $i + 1;
			while ( $next < $count && is_array( $tokens[ $next ] ) && in_array( $tokens[ $next ][0], $skip, true ) ) {
				++$next;
			}
			if ( $next >= $count || '(' !== $tokens[ $next ] ) {
				continue;
			}

			$prev = $i - 1;
			while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], $skip, true ) ) {
				--$prev;
			}
			if ( $prev >= 0 && is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], array( T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
				continue;
			}

			$found[] = $name . '@' . $token[2];
		}

		return $found;
	}
}
