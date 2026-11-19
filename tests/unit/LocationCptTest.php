<?php
/**
 * Location CPT registration + field group + FC layout contracts
 * (spec §10 #20, Appendix A.6; SEC09 posture, DATA02, FLEET07).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/location.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/location.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/collections.php';

final class LocationCptTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_cpts']       = array();
		$GLOBALS['salt_test_taxonomies'] = array();
		$GLOBALS['salt_test_acf_groups'] = array();
	}

	public function test_cpt_registers_with_the_display_only_posture(): void {
		salt_register_cpt_location();

		$this->assertArrayHasKey( 'location', $GLOBALS['salt_test_cpts'] );
		$args = $GLOBALS['salt_test_cpts']['location'];

		// SEC09 display-only posture: admin UI only, no public surface.
		$this->assertFalse( $args['public'] );
		$this->assertTrue( $args['show_ui'] );
		$this->assertFalse( $args['publicly_queryable'] );
		$this->assertTrue( $args['exclude_from_search'] );
		$this->assertFalse( $args['show_in_rest'] );
		$this->assertFalse( $args['has_archive'] );

		// menu_order drag-sort is the frozen default order (AMBIG07).
		$this->assertContains( 'page-attributes', $args['supports'] );
		$this->assertContains( 'title', $args['supports'] );
		$this->assertNotContains( 'thumbnail', $args['supports'], 'A location has no featured image (the contract dropped it).' );
	}

	public function test_location_area_taxonomy_attaches_to_the_cpt(): void {
		salt_register_cpt_location();

		$this->assertArrayHasKey( 'location_area', $GLOBALS['salt_test_taxonomies'] );
		$taxonomy = $GLOBALS['salt_test_taxonomies']['location_area'];

		$this->assertSame( array( 'location' ), $taxonomy['object_type'] );
		$this->assertFalse( $taxonomy['args']['public'] );
		$this->assertTrue( $taxonomy['args']['show_ui'] );
	}

	public function test_module_flag_gates_cpt_and_taxonomy_registration(): void {
		$GLOBALS['salt_test_fields']['option']['module_location'] = false;

		salt_register_cpt_location();

		$this->assertSame( array(), $GLOBALS['salt_test_cpts'] );
		$this->assertSame( array(), $GLOBALS['salt_test_taxonomies'] );
	}

	public function test_module_flag_gates_the_field_group(): void {
		$GLOBALS['salt_test_fields']['option']['module_location'] = false;

		$this->assertNull( salt_test_collection_group( 'locations' ) );
	}

	public function test_field_group_is_the_contracts_location(): void {
		$group = salt_test_collection_group( 'locations' );

		$this->assertSame( 'group_salt_collection_locations', $group['key'] );
		$this->assertSame( 'location', $group['location'][0][0]['value'] );
		$this->assertSame(
			array(
				'title'  => 'title',
				'topics' => 'categories',
			),
			$group['salt']['native']
		);

		$fields = array_column( $group['fields'], null, 'name' );
		$this->assertSame(
			array( 'address', 'lat', 'lng', 'zoom', 'phone', 'email', 'hours' ),
			array_keys( $fields )
		);

		// The contract's address shape (collections/_address.json).
		$this->assertSame( 'group', $fields['address']['type'] );
		$this->assertSame(
			array( 'street', 'locality', 'region', 'postalCode', 'country' ),
			array_column( $fields['address']['sub_fields'], 'name' )
		);
		$this->assertSame( 14, $fields['zoom']['default_value'] );
		$this->assertSame( 'email', $fields['email']['salt']['format'] );

		// Opening hours: the storage behind the status pill.
		$hours = $fields['hours'];
		$this->assertSame( 'repeater', $hours['type'] );
		$this->assertSame( 14, $hours['salt']['target'] );
		$this->assertArrayNotHasKey( 'max', $hours );

		$sub = array_column( $hours['sub_fields'], null, 'name' );
		$this->assertSame( array( 'day', 'open', 'close', 'appointment' ), array_keys( $sub ) );
		$this->assertSame(
			array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ),
			array_keys( $sub['day']['choices'] )
		);
		$this->assertSame( 'time_picker', $sub['open']['type'] );
		$this->assertSame( 'H:i', $sub['open']['return_format'] );
		$this->assertSame( 'H:i', $sub['close']['return_format'] );
		$this->assertSame( 'true_false', $sub['appointment']['type'] );
	}

	public function test_layout_is_generated_from_the_contract(): void {
		$layout = salt_test_layout( 'locations' );

		$this->assertSame( 'layout_salt_locations', $layout['key'] );
		$this->assertSame( array( 'eyebrow', 'heading', 'query', '', 'settings' ), array_column( $layout['sub_fields'], 'name' ) );

		// The contract's collection query for the location CPT, incl. its taxonomy.
		$query = array_column( $layout['sub_fields'], null, 'name' )['query'];
		$parts = array_column( $query['sub_fields'], null, 'name' );
		$this->assertSame( 'location_area', $parts['categories']['taxonomy'] );
		$this->assertSame( array( 'location' ), $parts['items']['post_type'] );
		$this->assertSame( 'field_salt_locations_query_mode', $parts['mode']['key'] );
	}
}
