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
require_once dirname( __DIR__, 2 ) . '/inc/fields/cpt-location.php';

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

		salt_register_location_fields();

		$this->assertSame( array(), $GLOBALS['salt_test_acf_groups'] );
	}

	public function test_field_group_follows_the_appendix_a6_table(): void {
		salt_register_location_fields();

		$this->assertCount( 1, $GLOBALS['salt_test_acf_groups'] );
		$group = $GLOBALS['salt_test_acf_groups'][0];

		$this->assertSame( 'group_salt_location', $group['key'] );
		$this->assertSame( 'location', $group['location'][0][0]['value'] );

		$fields = array_column( $group['fields'], null, 'name' );
		$this->assertSame(
			array( 'address', 'geo', 'phone', 'email', 'opening_hours' ),
			array_keys( $fields )
		);

		// Address group parts.
		$this->assertSame( 'group', $fields['address']['type'] );
		$this->assertSame(
			array( 'line1', 'line2', 'city', 'region', 'postcode', 'country' ),
			array_column( $fields['address']['sub_fields'], 'name' )
		);

		$this->assertSame( 'email', $fields['email']['type'] );

		// Opening hours: the frozen storage contract behind the status pill.
		$hours = $fields['opening_hours'];
		$this->assertSame( 'repeater', $hours['type'] );

		$sub = array_column( $hours['sub_fields'], null, 'name' );
		$this->assertSame( array( 'day', 'open', 'close', 'appointment_only' ), array_keys( $sub ) );
		$this->assertSame(
			array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ),
			array_keys( $sub['day']['choices'] )
		);
		$this->assertSame( 'time_picker', $sub['open']['type'] );
		$this->assertSame( 'H:i', $sub['open']['return_format'] );
		$this->assertSame( 'H:i', $sub['close']['return_format'] );
		$this->assertSame( 'true_false', $sub['appointment_only']['type'] );

		// DATA02: no key-convention drift was logged by salt_register_field_group.
		$this->assertSame( array(), $this->logs() );
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
