<?php
/**
 * Tests for inc/modules.php — module feature-flags.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module feature-flag tests.
 */
final class ModulesTest extends SaltTestCase {

	public function test_default_is_enabled_when_option_unset(): void {
		$this->assertTrue( salt_module_enabled( 'service' ) );
	}

	public function test_option_false_disables_module(): void {
		$GLOBALS['salt_test_fields']['option']['module_service'] = false;
		$this->assertFalse( salt_module_enabled( 'service' ) );
	}

	public function test_option_true_enables_module(): void {
		$GLOBALS['salt_test_fields']['option']['module_dark_mode'] = true;
		$this->assertTrue( salt_module_enabled( 'dark_mode' ) );
	}

	public function test_filter_at_priority_twenty_overrides_option(): void {
		$GLOBALS['salt_test_fields']['option']['module_faq'] = true;
		add_filter(
			'salt_module_enabled',
			static fn( bool $enabled, string $slug ): bool => 'faq' === $slug ? false : $enabled,
			20,
			2
		);
		$this->assertFalse( salt_module_enabled( 'faq' ) );
		$this->assertTrue( salt_module_enabled( 'work' ) );
	}

	public function test_unknown_slug_logs_once_and_returns_true(): void {
		$this->assertTrue( salt_module_enabled( 'not_a_module' ) );
		$this->assertTrue( salt_module_enabled( 'not_a_module' ) );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'not_a_module', $this->logs()[0] );
	}

	public function test_client_and_sector_default_to_off_and_the_rest_stay_on(): void {
		$this->assertFalse( salt_module_enabled( 'client' ) );
		$this->assertFalse( salt_module_enabled( 'sector' ) );
		$this->assertTrue( salt_module_enabled( 'work' ) );
		$this->assertTrue( salt_module_enabled( 'dark_mode' ) );

		$GLOBALS['salt_test_fields']['option']['module_client'] = true;
		$this->assertTrue( salt_module_enabled( 'client' ) );
		$this->assertFalse( salt_module_enabled( 'sector' ), 'Each record module switches on alone.' );
	}

	/**
	 * The client slot draws the record's current name only when clients is
	 * on and the record resolves; otherwise the free text, never another
	 * record.
	 */
	public function test_case_study_name_prefers_a_resolving_record_only_when_its_module_is_on(): void {
		$GLOBALS['salt_test_posts']                    = array(
			new WP_Post(
				array(
					'ID'         => 71,
					'post_type'  => 'client',
					'post_title' => 'Ashby Parish Council',
				)
			),
			new WP_Post(
				array(
					'ID'          => 72,
					'post_type'   => 'client',
					'post_status' => 'draft',
					'post_title'  => 'Draft client',
				)
			),
			new WP_Post(
				array(
					'ID'         => 73,
					'post_type'  => 'sector',
					'post_title' => 'Local government',
				)
			),
		);
		$GLOBALS['salt_test_fields']['40']['client']   = ' Ashby PC ';
		$GLOBALS['salt_test_meta'][40]['clientRecord'] = '71';

		$this->assertSame( 'Ashby PC', salt_case_study_name( 40, 'client' ), 'Off: the saved text, though the record resolves.' );

		$GLOBALS['salt_test_fields']['option']['module_client'] = true;
		$this->assertSame( 'Ashby Parish Council', salt_case_study_name( 40, 'client' ), 'On: the record\'s current name.' );

		foreach ( array(
			'72'  => 'a draft',
			'73'  => 'another type',
			'999' => 'a missing post',
			''    => 'no record',
		) as $record => $why ) {
			$GLOBALS['salt_test_meta'][40]['clientRecord'] = $record;
			$this->assertSame( 'Ashby PC', salt_case_study_name( 40, 'client' ), "On, {$why}: the saved text." );
		}
	}

	/**
	 * A record the picker cannot draw (binned, deleted, another type) is
	 * kept through an empty submit; removal and replacement are explicit.
	 */
	public function test_an_unavailable_record_is_kept_until_removed_or_replaced(): void {
		$GLOBALS['salt_test_posts'] = array(
			new WP_Post(
				array(
					'ID'          => 71,
					'post_type'   => 'client',
					'post_status' => 'trash',
					'post_title'  => 'Binned',
				)
			),
			new WP_Post(
				array(
					'ID'          => 72,
					'post_type'   => 'client',
					'post_status' => 'draft',
					'post_title'  => 'Draft',
				)
			),
			new WP_Post(
				array(
					'ID'         => 73,
					'post_type'  => 'sector',
					'post_title' => 'A sector',
				)
			),
		);
		$picker                     = array( 'key' => 'field_salt_collection_case_studies_client_record' );

		foreach ( array(
			'71'  => 'in the bin',
			'999' => 'deleted',
			'73'  => 'another type',
		) as $stored => $why ) {
			$stored                                        = (string) $stored;
			$GLOBALS['salt_test_meta'][40]['clientRecord'] = $stored;
			$this->assertSame( $stored, salt_keep_unavailable_record( '', 40, $picker ), "An ordinary save keeps a record {$why}." );
			$this->assertSame( '72', salt_keep_unavailable_record( '72', 40, $picker ), "Choosing another replaces a record {$why}." );
		}

		$_POST['salt_remove_record']['client'] = '1';
		$this->assertSame( '', salt_keep_unavailable_record( '', 40, $picker ), 'Removal on request clears it.' );
		unset( $_POST['salt_remove_record'] );

		$GLOBALS['salt_test_meta'][40]['clientRecord'] = '72';
		$this->assertSame( '', salt_keep_unavailable_record( '', 40, $picker ), 'Clearing an available record, a draft too, clears it.' );
		$this->assertSame( '', salt_keep_unavailable_record( '', 40, array( 'key' => 'field_salt_collection_case_studies_client' ) ), 'Only the pickers are kept.' );
	}

	public function test_a_disabled_modules_record_field_is_left_out_of_the_group(): void {
		$group = array(
			'fields' => array(
				array( 'name' => 'client' ),
				array( 'name' => 'clientRecord' ),
				array( 'name' => 'sector' ),
				array( 'name' => 'sectorRecord' ),
			),
		);
		$GLOBALS['salt_test_fields']['option']['module_sector'] = true;

		$this->assertSame( array( 'client', 'sector', 'sectorRecord' ), array_column( salt_record_fields_in_group( $group )['fields'], 'name' ) );
	}

	public function test_module_cpts_covers_exactly_the_cpt_modules(): void {
		$this->assertSame(
			array(
				'service'     => 'service',
				'testimonial' => 'testimonial',
				'work'        => 'work',
				'faq'         => 'faq',
				'location'    => 'location',
				'client'      => 'client',
				'sector'      => 'sector',
			),
			salt_module_cpts()
		);
	}

	public function test_modules_returns_all_known_slugs(): void {
		$this->assertSame(
			array( 'service', 'testimonial', 'team_member', 'work', 'faq', 'location', 'client', 'sector', 'dark_mode', 'display_preferences' ),
			array_keys( salt_modules() )
		);
	}
}
