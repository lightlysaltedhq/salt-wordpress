<?php
/**
 * Tests for inc/fields/options.php + the inc/fields/options-*.php page files
 * — the Site Settings IA (parent + seven sub-pages) and the v3 retarget
 * contract: every options field group targets a declared sub-page slug and
 * every frozen field NAME survived the consolidation (DATA02/FLEET07).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

require_once dirname( __DIR__, 2 ) . '/inc/fields/options.php';

$salt_options_field_files = glob( dirname( __DIR__, 2 ) . '/inc/fields/options-*.php' );
foreach ( false === $salt_options_field_files ? array() : $salt_options_field_files as $salt_options_field_file ) {
	require_once $salt_options_field_file;
}
unset( $salt_options_field_files, $salt_options_field_file );

// SaltTestCase::setUp() resets the hook registry before every test, wiping
// the acf/init registrations the requires above just made — snapshot them at
// load time so each test can restore-then-fire.
$GLOBALS['salt_test_acf_init_snapshot'] = $GLOBALS['salt_test_hooks']['acf/init'] ?? array();

/**
 * Site Settings pages + retarget/frozen-names regression gate.
 */
final class OptionsPagesTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_acf_groups']        = array();
		$GLOBALS['salt_test_acf_pages']         = array();
		$GLOBALS['salt_test_acf_subpages']      = array();
		$GLOBALS['salt_test_hooks']['acf/init'] = $GLOBALS['salt_test_acf_init_snapshot'];
		salt_test_do_action( 'acf/init' );
	}

	/**
	 * The declared sub-page slugs (registration order).
	 *
	 * @return string[]
	 */
	private function declared_slugs(): array {
		return array_keys( salt_options_subpages() );
	}

	/**
	 * Flatten every field NAME in a captured group (fields + sub_fields;
	 * unnamed fields — tabs — carry no data and are skipped).
	 *
	 * @param array<int,array<string,mixed>> $fields Field definitions.
	 * @return string[]
	 */
	private function flatten_names( array $fields ): array {
		$names = array();
		foreach ( $fields as $field ) {
			if ( isset( $field['name'] ) && '' !== $field['name'] ) {
				$names[] = (string) $field['name'];
			}
			if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				$names = array_merge( $names, $this->flatten_names( $field['sub_fields'] ) );
			}
		}
		return $names;
	}

	public function test_parent_page_registers_as_redirect_shell(): void {
		$pages = $GLOBALS['salt_test_acf_pages'];
		$this->assertCount( 1, $pages );

		$parent = $pages[0];
		$this->assertSame( 'salt-settings', $parent['menu_slug'] );
		$this->assertSame( 'manage_options', $parent['capability'] );
		$this->assertTrue( $parent['redirect'], 'The parent holds no groups — it must redirect to the first sub-page.' );
	}

	public function test_seven_subpages_register_uniquely_in_declared_order(): void {
		$subpages = $GLOBALS['salt_test_acf_subpages'];
		$this->assertCount( 7, $subpages );

		$slugs = array_column( $subpages, 'menu_slug' );
		$this->assertSame( $slugs, array_values( array_unique( $slugs ) ), 'Sub-page slugs must be unique.' );
		$this->assertSame( $this->declared_slugs(), $slugs, 'Registration order IS the menu order.' );

		foreach ( $subpages as $subpage ) {
			$this->assertSame( 'manage_options', $subpage['capability'] );
			$this->assertSame( 'salt-settings', $subpage['parent_slug'] );
		}
	}

	public function test_every_options_group_targets_a_declared_subpage(): void {
		$declared = $this->declared_slugs();
		$this->assertNotEmpty( $GLOBALS['salt_test_acf_groups'] );

		foreach ( $GLOBALS['salt_test_acf_groups'] as $group ) {
			$key   = (string) ( $group['key'] ?? '' );
			$rules = array();
			foreach ( $group['location'] ?? array() as $or_group ) {
				foreach ( $or_group as $rule ) {
					if ( 'options_page' === ( $rule['param'] ?? '' ) ) {
						$rules[] = $rule;
					}
				}
			}

			if ( str_starts_with( $key, 'group_salt_options_' ) ) {
				$this->assertNotEmpty( $rules, "Options group {$key} must target an options page." );
			}

			foreach ( $rules as $rule ) {
				$this->assertSame( '==', $rule['operator'], "Group {$key} options_page rule must use ==." );
				$this->assertContains(
					$rule['value'],
					$declared,
					"Group {$key} targets '{$rule['value']}' — not a declared sub-page (the pre-v3 'salt-settings' parent holds no groups)."
				);
			}
		}
	}

	public function test_frozen_field_names_survive_the_consolidation(): void {
		$names = array();
		foreach ( $GLOBALS['salt_test_acf_groups'] as $group ) {
			$names = array_merge( $names, $this->flatten_names( $group['fields'] ?? array() ) );
		}

		$contract = array_merge(
			array(
				'brand_primary',
				'brand_secondary',
				'brand_accent',
				'dark_mode',
				'org_name',
				'org_legal_name',
				'org_logo',
				'company_phone',
				'company_email',
				'company_address',
				'org_same_as',
				'contact_recipient',
				'contact_routes',
				'contact_autoreply',
				'contact_autoreply_subject',
				'contact_autoreply_body',
				'webhook_targets',
				'contact_privacy_page',
				'captcha_provider',
				'captcha_turnstile_site_key',
				'captcha_recaptcha_site_key',
				'submissions_store',
				'submissions_retention_days',
				'posthog_enabled',
				'posthog_key',
				'posthog_host',
				'posthog_host_custom',
				'posthog_session_recording',
				'ga4_enabled',
				'ga4_id',
				'maps_enabled',
				'maps_tile_url',
				'booking_provider',
				'booking_url',
			),
			array_map(
				static fn( string $slug ): string => 'module_' . $slug,
				array_keys( salt_modules() )
			)
		);

		foreach ( $contract as $name ) {
			$this->assertContains(
				$name,
				$names,
				"Frozen field name '{$name}' is missing — a renamed/dropped name orphans wp_options data (DATA02/FLEET07)."
			);
		}
	}
}
