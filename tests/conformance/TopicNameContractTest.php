<?php
/**
 * The topic name's target against the pinned salt-contract's.
 *
 * SALT_TOPIC_NAME_TARGET_LENGTH (inc/cpt/topics.php), the length the topic
 * screens count a name against, is the maxLength of the name in
 * contract/collections/_topic.json. This reads the installed
 * contract package, so it lives in the conformance suite, the only one that
 * reads node_modules (phpunit.xml.dist).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TopicNameContractTest extends SaltTestCase {

	private const TOPIC = __DIR__ . '/../../node_modules/@lightlysaltedhq/salt-contract/contract/collections/_topic.json';

	public function test_the_topic_name_target_is_the_contracts(): void {
		$topic = json_decode( (string) file_get_contents( self::TOPIC ), true );
		$name  = array_values( array_filter( $topic['fields'], static fn( array $field ): bool => 'title' === $field['name'] ) )[0] ?? array();

		$this->assertSame( 'title', $name['platforms']['wordpress']['native'] ?? null, 'The name is the term\'s own (native), so the theme counts it.' );
		$this->assertSame( $name['maxLength'] ?? null, SALT_TOPIC_NAME_TARGET_LENGTH );
	}
}
