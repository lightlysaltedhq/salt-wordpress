<?php
/**
 * The theme's rich-text lists against the pinned salt-contract.
 *
 * These read the installed contract package, so they live in the conformance
 * suite, the only one that reads node_modules (phpunit.xml.dist). The unit
 * suite keeps the lists' behaviour and their match with the generated JSON.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RichTextContractTest extends SaltTestCase {

	private const CONTRACT = __DIR__ . '/../../node_modules/@lightlysaltedhq/salt-contract';

	public function test_every_contract_kind_maps_into_the_superset_and_covers_it(): void {
		$schema = json_decode( (string) file_get_contents( self::CONTRACT . '/schema/field-definition.schema.json' ), true );
		$kinds  = $schema['$defs']['richTextField']['properties']['allowed']['items']['enum'];

		$this->assertEqualsCanonicalizing( $kinds, array_keys( salt_rich_text_kinds() ), 'One mapping per contract element kind.' );
		$this->assertEqualsCanonicalizing( array_keys( salt_allowed_html() ), array_keys( salt_allowed_html_for( $kinds ) ), 'Every kind together is the superset, no more.' );
	}

	public function test_the_service_intro_list_is_the_pinned_contracts(): void {
		$services = json_decode( (string) file_get_contents( self::CONTRACT . '/contract/collections/services.json' ), true );
		$intro    = array_values( array_filter( $services['fields'], static fn( array $field ): bool => 'intro' === $field['name'] ) )[0];

		$this->assertSame( $intro['allowed'], SALT_SERVICE_INTRO_ALLOWED );
		$this->assertSame( 'body', $intro['platforms']['wordpress']['native'] ?? null, 'The intro is the post body, so no generated field carries its list.' );
	}
}
