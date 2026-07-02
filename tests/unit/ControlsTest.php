<?php
/**
 * Query control ACF sub-field factory tests (spec §10a, SCOPE01/DATA02).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class ControlsTest extends SaltTestCase {

	/**
	 * Index a field list by 'name'.
	 *
	 * @param array<int,array<string,mixed>> $fields Field definitions.
	 * @return array<string,array<string,mixed>>
	 */
	private function by_name( array $fields ): array {
		$indexed = array();
		foreach ( $fields as $field ) {
			$indexed[ $field['name'] ] = $field;
		}
		return $indexed;
	}

	public function test_keys_follow_canonical_pattern(): void {
		$fields = $this->by_name( salt_query_control_fields( 'service' ) );

		foreach ( array( 'source', 'manual', 'taxonomy_terms', 'orderby', 'order', 'count' ) as $name ) {
			$this->assertArrayHasKey( $name, $fields );
			$this->assertSame( "field_salt_query_service_{$name}", $fields[ $name ]['key'] );
		}
	}

	public function test_taxonomy_field_omitted_for_testimonial(): void {
		$fields = $this->by_name( salt_query_control_fields( 'testimonial' ) );

		$this->assertArrayNotHasKey( 'taxonomy_terms', $fields );
		// The other five fields are all present.
		$this->assertSame( array( 'source', 'manual', 'orderby', 'order', 'count' ), array_keys( $fields ) );
	}

	public function test_relationship_bound_to_the_cpt(): void {
		$fields = $this->by_name( salt_query_control_fields( 'team_member' ) );

		$this->assertSame( 'relationship', $fields['manual']['type'] );
		$this->assertSame( array( 'team_member' ), $fields['manual']['post_type'] );
		$this->assertSame( array( 'search' ), $fields['manual']['filters'] );
		$this->assertSame( 'id', $fields['manual']['return_format'] );
	}

	public function test_taxonomy_field_bound_to_salt_query_taxonomy(): void {
		$fields = $this->by_name( salt_query_control_fields( 'work' ) );

		$this->assertSame( 'taxonomy', $fields['taxonomy_terms']['type'] );
		$this->assertSame( 'work_type', $fields['taxonomy_terms']['taxonomy'] );
	}

	public function test_conditional_logic_shows_manual_and_taxonomy_fields(): void {
		$fields = $this->by_name( salt_query_control_fields( 'faq' ) );

		$this->assertSame(
			array(
				array(
					array(
						'field'    => 'field_salt_query_faq_source',
						'operator' => '==',
						'value'    => 'manual',
					),
				),
			),
			$fields['manual']['conditional_logic']
		);
		$this->assertSame( 'taxonomy', $fields['taxonomy_terms']['conditional_logic'][0][0]['value'] );
	}

	public function test_source_defaults_to_default_and_count_bounds(): void {
		$fields = $this->by_name( salt_query_control_fields( 'post' ) );

		$this->assertSame( 'button_group', $fields['source']['type'] );
		$this->assertSame( 'default', $fields['source']['default_value'] );
		$this->assertSame( array( 'default', 'taxonomy', 'manual' ), array_keys( $fields['source']['choices'] ) );

		$this->assertSame( 0, $fields['count']['default_value'] );
		$this->assertSame( 0, $fields['count']['min'] );
		$this->assertSame( 24, $fields['count']['max'] );

		$this->assertSame( array( '', 'menu_order', 'date', 'title' ), array_keys( $fields['orderby']['choices'] ) );
		$this->assertSame( array( '', 'ASC', 'DESC' ), array_keys( $fields['order']['choices'] ) );
	}

	public function test_every_label_passes_through_i18n(): void {
		// Runtime spot-check: the shim __() returns the source string, so
		// verify at source level that every 'label' (and 'instructions')
		// literal is wrapped in __() with the 'salt' domain.
		$source = file_get_contents( get_template_directory() . '/inc/query/controls.php' );
		$this->assertIsString( $source );

		$label_count = preg_match_all( "/'label'\s*=>/", $source );
		$i18n_count  = preg_match_all( "/'label'\s*=>\s*__\(\s*'[^']+',\s*'salt'\s*\)/", $source );

		$this->assertGreaterThan( 0, $label_count );
		$this->assertSame( $label_count, $i18n_count );

		// Runtime spot-check of one label value.
		$fields = $this->by_name( salt_query_control_fields( 'service' ) );
		$this->assertSame( 'Content source', $fields['source']['label'] );
	}
}
