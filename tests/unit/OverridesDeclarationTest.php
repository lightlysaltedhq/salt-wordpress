<?php
/**
 * The child theme's replaced-logic declaration, salt-overrides.json
 * (ID-3613): read from the child theme root, validated against the rules of
 * salt-contract's replaced-logic.schema.json, memoised, and never fatal; and
 * the map from contract section ids to this theme's layout slugs.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__ ) . '/SaltOverridesTestCase.php';

final class OverridesDeclarationTest extends SaltOverridesTestCase {

	// -----------------------------------------------------------------
	// Reading the declaration.
	// -----------------------------------------------------------------

	public function test_no_child_theme_means_no_declaration_and_no_log(): void {
		$this->assertSame( 'none', salt_overrides_load()['state'] );
		$this->assertNull( salt_overrides() );
		$this->assertNull( salt_override_tier( 'cta' ) );
		$this->assertSame( array(), $this->logs() );
	}

	public function test_child_theme_without_the_file_means_no_declaration(): void {
		salt_test_use_child_theme( $this->temp_child( array( 'style.css' => '/* child */' ) ) );

		$this->assertSame( 'absent', salt_overrides_load()['state'] );
		$this->assertNull( salt_overrides() );
		$this->assertSame( array(), $this->logs() );
	}

	public function test_the_fixture_declaration_is_read_from_the_child_theme_root(): void {
		salt_test_use_child_theme( self::fixture() );

		$declaration = salt_overrides();

		$this->assertIsArray( $declaration );
		$this->assertSame( 'grain-fixture', $declaration['site'] );
		$this->assertSame( 'wordpress', $declaration['platform'] ); // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- Schema enum value.
		$this->assertSame( '1.0.0', $declaration['contract'] );
		$this->assertSame( array( 'call-to-action', 'stats' ), array_column( $declaration['sections'], 'section' ) );
		$this->assertSame( array( 'view', 'logic' ), array_column( $declaration['sections'], 'tier' ) );
		$this->assertSame( array( 'template-parts/sections/cta.php' ), $declaration['sections'][0]['files'] );
		$this->assertSame( array(), $declaration['imageSizes'] );
		$this->assertSame( array( 'showroom-finder' ), array_column( $declaration['clientSections'], 'id' ) );
		$this->assertSame( array(), $this->logs() );
	}

	public function test_the_declaration_is_read_once_per_request(): void {
		$dir = $this->temp_child( array( 'salt-overrides.json' => self::json( self::declaration() ) ) );
		salt_test_use_child_theme( $dir );

		$this->assertNotNull( salt_overrides() );
		unlink( $dir . '/salt-overrides.json' );
		$this->assertNotNull( salt_overrides(), 'Memoised: the file is not read again.' );

		salt_overrides_reset();
		$this->assertNull( salt_overrides() );
	}

	public function test_the_schema_example_is_valid_once_its_platform_is_wordpress(): void {
		// replaced-logic.schema.json's own example (salt-contract 1.0.0-rc.6), platform switched.
		$example = self::declaration(
			array(
				'site'           => 'example-site',
				'sections'       => array(
					self::entry(
						array(
							'files' => array( 'template-parts/sections/hero.php' ),
							'since' => '14/11/2026',
						)
					),
					self::entry(
						array(
							'section' => 'locations',
							'tier'    => 'logic',
							'files'   => array( 'template-parts/sections/locations.php', 'inc/locations.php' ),
						)
					),
				),
				'imageSizes'     => array(
					array(
						'section' => 'hero',
						'slot'    => 'half',
						'band'    => 'default',
						'sizes'   => '(min-width: 75rem) 36rem, calc(100vw - 2rem)',
						'reason'  => 'Three fifths of the band.',
					),
				),
				'clientSections' => array(
					array(
						'id'     => 'showroom-finder',
						'reason' => 'Only this client.',
					),
				),
			)
		);

		$result = salt_overrides_validate( json_decode( self::json( $example ), false ) );

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 'default', $result['declaration']['imageSizes'][0]['band'] );
	}

	/**
	 * Files that break a schema rule (or the two platform rules).
	 *
	 * @return array<string,array{0:string,1:string}> Raw file, expected error fragment.
	 */
	public static function invalid_files(): array {
		$entry = self::entry();
		$cases = array(
			'not JSON'                 => array( '{"site": ', 'not valid JSON' ),
			'a JSON array'             => array( '[]', 'the file must be an object' ),
			'an empty object'          => array( '{}', 'missing "site"' ),
			'unknown top-level key'    => array( self::json( self::declaration( array( 'dials' => array() ) ) ), 'does not allow: "dials"' ),
			'empty site'               => array( self::json( self::declaration( array( 'site' => '' ) ) ), 'site must be' ),
			'Next.js platform'         => array( self::json( self::declaration( array( 'platform' => 'nextjs' ) ) ), 'platform must be "wordpress"' ),
			'pre-release contract'     => array( self::json( self::declaration( array( 'contract' => '1.0.0-rc.6' ) ) ), 'contract must be a release' ),
			'sections not a list'      => array( self::json( self::declaration( array( 'sections' => 'hero' ) ) ), 'sections must be an array' ),
			'bad section id'           => array( self::json( self::declaration( array( 'sections' => array( self::entry( array( 'section' => 'Hero' ) ) ) ) ) ), 'sections[0].section' ),
			'bad tier'                 => array( self::json( self::declaration( array( 'sections' => array( self::entry( array( 'tier' => 'dials' ) ) ) ) ) ), 'tier must be' ),
			'no files'                 => array( self::json( self::declaration( array( 'sections' => array( self::entry( array( 'files' => array() ) ) ) ) ) ), 'files must be a non-empty array' ),
			'repeated file'            => array( self::json( self::declaration( array( 'sections' => array( self::entry( array( 'files' => array( 'a.php', 'a.php' ) ) ) ) ) ) ), 'must not repeat' ),
			'empty file name'          => array( self::json( self::declaration( array( 'sections' => array( self::entry( array( 'files' => array( '' ) ) ) ) ) ) ), 'non-empty strings' ),
			'empty reason'             => array( self::json( self::declaration( array( 'sections' => array( self::entry( array( 'reason' => '' ) ) ) ) ) ), 'reason must be' ),
			'US date'                  => array( self::json( self::declaration( array( 'sections' => array( self::entry( array( 'since' => '11/14/2026' ) ) ) ) ) ), 'since must be a date' ),
			'pre-release verified'     => array( self::json( self::declaration( array( 'sections' => array( self::entry( array( 'verified' => '1.0.0-rc.6' ) ) ) ) ) ), 'verified must be a release' ),
			'extra entry key'          => array( self::json( self::declaration( array( 'sections' => array( $entry + array( 'note' => 'x' ) ) ) ) ), 'does not allow: "note"' ),
			'missing entry key'        => array( self::json( self::declaration( array( 'sections' => array( array_diff_key( $entry, array( 'since' => true ) ) ) ) ) ), 'missing "since"' ),
			'two entries, one section' => array( self::json( self::declaration( array( 'sections' => array( $entry, self::entry( array( 'tier' => 'logic' ) ) ) ) ) ), 'already has an entry' ),
			'bad image band'           => array(
				self::json(
					self::declaration(
						array(
							'imageSizes' => array(
								array(
									'section' => 'hero',
									'slot'    => 'half',
									'band'    => 'huge',
									'sizes'   => '100vw',
									'reason'  => 'x',
								),
							),
						)
					)
				),
				'band must be',
			),
			'client section no reason' => array( self::json( self::declaration( array( 'clientSections' => array( array( 'id' => 'finder' ) ) ) ) ), 'missing "reason"' ),
			'client section twice'     => array(
				self::json(
					self::declaration(
						array(
							'clientSections' => array(
								array(
									'id'     => 'finder',
									'reason' => 'x',
								),
								array(
									'id'     => 'finder',
									'reason' => 'y',
								),
							),
						)
					)
				),
				'listed twice',
			),
			'too large'                => array( str_pad( self::json( self::declaration() ), 70000, ' ' ), 'larger than' ),
		);
		return $cases;
	}

	#[DataProvider( 'invalid_files' )]
	public function test_an_invalid_file_is_logged_once_and_treated_as_no_declaration( string $raw, string $error ): void {
		salt_test_use_child_theme( $this->temp_child( array( 'salt-overrides.json' => $raw ) ) );

		$loaded = salt_overrides_load();

		$this->assertSame( 'invalid', $loaded['state'] );
		$this->assertNull( salt_overrides() );
		$this->assertStringContainsString( $error, implode( ' | ', $loaded['errors'] ) );
		$this->assertCount( 1, $this->logs(), 'Logged once per request, and never thrown (WP_DEBUG is on here).' );
		$this->assertStringContainsString( 'salt-overrides.json in the child theme', $this->logs()[0] );
	}

	public function test_a_byte_order_mark_is_tolerated(): void {
		salt_test_use_child_theme( $this->temp_child( array( 'salt-overrides.json' => "\xEF\xBB\xBF" . self::json( self::declaration() ) ) ) );

		$this->assertNotNull( salt_overrides() );
	}

	// -----------------------------------------------------------------
	// Contract ids → layout slugs.
	// -----------------------------------------------------------------

	public function test_every_parent_layout_is_a_contract_section(): void {
		$map = salt_contract_section_layouts();

		foreach ( glob( dirname( __DIR__, 2 ) . '/inc/sections/*.php' ) as $file ) {
			$slug = basename( $file, '.php' );
			if ( str_starts_with( $slug, 'zz-test-' ) ) {
				continue;
			}
			$this->assertNotSame( array(), salt_contract_sections_for_layout( $slug ), sprintf( 'Layout %s maps to no contract section.', $slug ) );
		}

		foreach ( $map as $id => $layouts ) {
			$this->assertMatchesRegularExpression( '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/D', $id );
			foreach ( $layouts as $layout ) {
				$this->assertNotNull( salt_section_resolver_file( $layout ), sprintf( '%s maps to %s, which is not a parent layout.', $id, $layout ) );
			}
		}

		$this->assertSame( array( 'call-to-action' ), salt_contract_sections_for_layout( 'cta' ), 'The contract name differs from the slug.' );
		$this->assertSame( array( 'collection-showcase', 'carousel' ), salt_contract_sections_for_layout( 'testimonials' ) );
		$this->assertSame( array(), salt_contract_sections_for_layout( 'child-only' ) );
	}

	public function test_tier_is_looked_up_by_the_contract_id_and_logic_wins(): void {
		$declaration = salt_overrides_validate(
			json_decode(
				self::json(
					self::declaration(
						array(
							'sections' => array(
								self::entry(
									array(
										'section' => 'carousel',
										'files'   => array( 'template-parts/sections/scroll-carousel.php' ),
									)
								),
								self::entry(
									array(
										'section' => 'collection-showcase',
										'tier'    => 'logic',
										'files'   => array( 'template-parts/sections/team.php' ),
									)
								),
							),
						)
					)
				),
				false
			)
		)['declaration'];

		$this->assertSame( 'view', salt_override_tier_in( $declaration, 'scroll-carousel' ) );
		$this->assertSame( 'logic', salt_override_tier_in( $declaration, 'team' ) );
		$this->assertSame( 'logic', salt_override_tier_in( $declaration, 'testimonials' ), 'In both sections: logic wins.' );
		$this->assertNull( salt_override_tier_in( $declaration, 'hero' ) );
		$this->assertNull( salt_override_tier_in( null, 'team' ) );
	}
}
