<?php
/**
 * Extension points a child theme declares (ID-3613): the replaced-logic
 * declaration, `salt-overrides.json`, and the map from the salt-contract's
 * section ids to this theme's layout slugs.
 *
 * The declaration's format is the contract's
 * (salt-contract/schema/replaced-logic.schema.json, SC-015): a JSON file at
 * the root of the CHILD theme, one entry per section the site changed above
 * tier 3 of the customisation ladder — `view` (tier 4, the site swapped the
 * section's view) or `logic` (tier 5, the site took the section over) — plus
 * `imageSizes` and `clientSections` (tier 6). The child's OVERRIDES.md points
 * at the file; it never repeats it, and the parent never reads Markdown.
 *
 * What the parent does with it: docs/contracts/OVERRIDES.md "Declaring
 * replaced sections". Rendering for a site with no declaration is unchanged.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The declaration's file name, at the root of the child theme.
 */
const SALT_OVERRIDES_FILE = 'salt-overrides.json';

/**
 * Largest declaration the parent will read, in bytes. A real one is a few
 * kilobytes; anything bigger is treated as malformed rather than parsed.
 */
const SALT_OVERRIDES_MAX_BYTES = 65536;

/**
 * The salt-contract section ids this theme knows, each mapped to the
 * layout slugs (hyphenated, as the section loop passes them) that are that
 * section on WordPress today.
 *
 * Drawn from salt-contract/contract/sections.json (1.0.0-rc.6),
 * the WordPress platform's `formerly` names, with the ACF layout names hyphenated —
 * the same mapping as the slug table in docs/contracts/CONVENTIONS.md.
 * Where the contract folds several layouts into one section
 * (collection-showcase, carousel, rich-text) every one of them is listed,
 * and `testimonials` is under both collection-showcase (its grid rows) and
 * carousel (its slider rows); which one a row is comes from its layout
 * field (salt_contract_section_conditions()). A section WordPress does not ship yet (tabs,
 * listing) maps to no layout. salt-wordpress takes no dependency on the
 * contract package, so this list is kept by hand: a new contract section
 * or a new layout adds a row here.
 *
 * @return array<string,string[]> Contract section id => layout slugs.
 */
function salt_contract_section_layouts(): array {
	return array(
		'hero'                => array( 'hero' ),
		'rich-text'           => array( 'rich-text', 'intro' ),
		'call-to-action'      => array( 'cta' ),
		'media-text'          => array( 'media-text' ),
		'features'            => array( 'features' ),
		'stats'               => array( 'stats' ),
		'logos'               => array( 'logos' ),
		'gallery'             => array( 'gallery' ),
		'process'             => array( 'process' ),
		'faq'                 => array( 'faq' ),
		'tabs'                => array(),
		'collection-showcase' => array( 'services', 'work', 'team', 'testimonials', 'blog-teaser' ),
		'carousel'            => array( 'scroll-carousel', 'testimonials' ),
		'listing'             => array(),
		'contact'             => array( 'contact' ),
		'locations'           => array( 'locations' ),
		'pricing'             => array( 'pricing' ),
	);
}

/**
 * Layouts whose contract section depends on a field of the row (the
 * contract's `when`): testimonials is collection-showcase when its layout
 * field is grid and carousel when it is slider (sections.json,
 * collection-showcase and carousel `formerly`).
 *
 * @return array<string, array{field: string, normalise: callable(string): string, sections: array<string,string>}>
 *         Layout slug => the sub-field, its normaliser (the resolver's own),
 *         and normalised value => contract section id.
 */
function salt_contract_section_conditions(): array {
	return array(
		'testimonials' => array(
			'field'     => 'layout',
			'normalise' => 'salt_testimonials_layout',
			'sections'  => array(
				'grid'   => 'collection-showcase',
				'slider' => 'carousel',
			),
		),
	);
}

/**
 * The testimonials layout field normalised: 'grid' | 'slider', anything
 * else 'grid'. The one rule both the testimonials resolver and the
 * contract-id lookup use.
 *
 * @param string $value Raw `layout` sub-field value.
 * @return string
 */
function salt_testimonials_layout( string $value ): string {
	return in_array( $value, array( 'grid', 'slider' ), true ) ? $value : 'grid';
}

/**
 * The contract section ids a layout belongs to (usually one; a child-only
 * layout to none). For a layout whose section depends on its row
 * (salt_contract_section_conditions()), pass the row's raw field value as
 * $variant to get the one id that row is; with $variant null every id the
 * layout can be is returned (testimonials: both).
 *
 * @param string      $layout  Layout slug (hyphenated).
 * @param string|null $variant Raw value of the layout's condition field, or null.
 * @return string[]
 */
function salt_contract_sections_for_layout( string $layout, ?string $variant = null ): array {
	$condition = salt_contract_section_conditions()[ $layout ] ?? null;
	if ( null !== $condition && null !== $variant ) {
		$id = $condition['sections'][ ( $condition['normalise'] )( $variant ) ] ?? null;
		return null === $id ? array() : array( $id );
	}

	$ids = array();
	foreach ( salt_contract_section_layouts() as $id => $layouts ) {
		if ( in_array( $layout, $layouts, true ) ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

/**
 * The current Flexible Content row's value of a layout's condition field
 * (raw; salt_contract_sections_for_layout() normalises it), or null when
 * the layout has no condition.
 *
 * @param string $layout Layout slug (hyphenated).
 * @return string|null
 */
function salt_contract_section_variant( string $layout ): ?string {
	$condition = salt_contract_section_conditions()[ $layout ] ?? null;
	if ( null === $condition ) {
		return null;
	}
	return function_exists( 'get_sub_field' ) ? (string) get_sub_field( $condition['field'] ) : '';
}

/**
 * How a layout reads when it is one contract section only for some rows:
 * "testimonials (slider rows)" for carousel, "testimonials" otherwise.
 *
 * @param string $layout Layout slug.
 * @param string $id     Contract section id.
 * @return string
 */
function salt_contract_layout_label( string $layout, string $id ): string {
	$condition = salt_contract_section_conditions()[ $layout ] ?? null;
	$value     = null === $condition ? false : array_search( $id, $condition['sections'], true );
	return false === $value ? $layout : sprintf( '%1$s (%2$s rows)', $layout, $value );
}

/**
 * Validate a decoded declaration against the rules of
 * replaced-logic.schema.json, and return it as plain arrays.
 *
 * Decode with objects (json_decode( $raw, false )) so a JSON object and a
 * JSON array stay distinguishable. Beyond the schema, two rules make the
 * file usable by THIS platform: `platform` must name WordPress, and a
 * section id may have only one entry (and a client section only one row) —
 * two entries for one section would say two different things about it.
 *
 * @param mixed $data Decoded JSON.
 * @return array{declaration: array<string,mixed>|null, errors: string[]}
 */
function salt_overrides_validate( $data ): array {
	$errors  = array();
	$id      = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/D';
	$release = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D';
	$date    = '#^(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/[0-9]{4}$#D';

	$text = static fn( $value ): bool => is_string( $value ) && '' !== $value;

	// One object: every required key present, no key outside the schema.
	$object = static function ( $value, string $at, array $required, array $optional ) use ( &$errors ): ?array {
		if ( ! $value instanceof stdClass ) {
			$errors[] = sprintf( '%s must be an object', $at );
			return null;
		}
		$vars = get_object_vars( $value );
		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $vars ) ) {
				$errors[] = sprintf( '%s is missing "%s"', $at, $key );
			}
		}
		foreach ( array_diff( array_keys( $vars ), $required, $optional ) as $key ) {
			$errors[] = sprintf( '%s has a key the schema does not allow: "%s"', $at, $key );
		}
		return $vars;
	};

	$list = static function ( $value, string $at ) use ( &$errors ): array {
		if ( ! is_array( $value ) ) {
			$errors[] = sprintf( '%s must be an array', $at );
			return array();
		}
		return $value;
	};

	$match = static function ( $value, string $pattern, string $at, string $what ) use ( &$errors ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( $pattern, $value ) ) {
			$errors[] = sprintf( '%s must be %s', $at, $what );
			return '';
		}
		return $value;
	};

	$top = $object( $data, 'the file', array( '$schema', 'site', 'platform', 'contract', 'sections' ), array( 'imageSizes', 'clientSections' ) );
	if ( null === $top ) {
		return array(
			'declaration' => null,
			'errors'      => $errors,
		);
	}

	if ( array_key_exists( '$schema', $top ) && ! is_string( $top['$schema'] ) ) {
		$errors[] = '$schema must be a string';
	}
	if ( array_key_exists( 'site', $top ) && ! $text( $top['site'] ) ) {
		$errors[] = 'site must be a non-empty string';
	}
	if ( array_key_exists( 'platform', $top ) && 'wordpress' !== $top['platform'] ) { // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- The schema's lower-case enum value.
		$errors[] = 'platform must be "wordpress" in a child theme'; // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- The schema's lower-case enum value.
	}
	$contract = array_key_exists( 'contract', $top ) ? $match( $top['contract'], $release, 'contract', 'a release (x.y.z)' ) : '';

	$sections = array();
	$seen     = array();
	foreach ( ( array_key_exists( 'sections', $top ) ? $list( $top['sections'], 'sections' ) : array() ) as $i => $item ) {
		$at   = sprintf( 'sections[%d]', $i );
		$vars = $object( $item, $at, array( 'section', 'tier', 'files', 'reason', 'since', 'verified' ), array() );
		if ( null === $vars ) {
			continue;
		}

		$section = $match( $vars['section'] ?? null, $id, $at . '.section', 'a section id' );
		if ( '' !== $section ) {
			if ( isset( $seen[ $section ] ) ) {
				$errors[] = sprintf( '%s: section "%s" already has an entry', $at, $section );
			}
			$seen[ $section ] = true;
		}

		$tier = $vars['tier'] ?? null;
		if ( 'view' !== $tier && 'logic' !== $tier ) {
			$errors[] = sprintf( '%s.tier must be "view" or "logic"', $at );
			$tier     = '';
		}

		$files = $vars['files'] ?? null;
		if ( ! is_array( $files ) || array() === $files ) {
			$errors[] = sprintf( '%s.files must be a non-empty array', $at );
			$files    = array();
		} elseif ( count( array_filter( $files, $text ) ) !== count( $files ) ) {
			$errors[] = sprintf( '%s.files must hold non-empty strings', $at );
			$files    = array();
		} elseif ( count( array_unique( $files ) ) !== count( $files ) ) {
			$errors[] = sprintf( '%s.files must not repeat a file', $at );
		}

		if ( ! $text( $vars['reason'] ?? null ) ) {
			$errors[] = sprintf( '%s.reason must be a non-empty string', $at );
		}

		$sections[] = array(
			'section'  => $section,
			'tier'     => (string) $tier,
			'files'    => array_values( $files ),
			'reason'   => is_string( $vars['reason'] ?? null ) ? $vars['reason'] : '',
			'since'    => $match( $vars['since'] ?? null, $date, $at . '.since', 'a date (DD/MM/YYYY)' ),
			'verified' => $match( $vars['verified'] ?? null, $release, $at . '.verified', 'a release (x.y.z)' ),
		);
	}

	$image_sizes = array();
	foreach ( ( array_key_exists( 'imageSizes', $top ) ? $list( $top['imageSizes'], 'imageSizes' ) : array() ) as $i => $item ) {
		$at   = sprintf( 'imageSizes[%d]', $i );
		$vars = $object( $item, $at, array( 'section', 'slot', 'sizes', 'reason' ), array( 'band' ) );
		if ( null === $vars ) {
			continue;
		}
		$size = array(
			'section' => $match( $vars['section'] ?? null, $id, $at . '.section', 'a section id' ),
			'slot'    => $match( $vars['slot'] ?? null, $id, $at . '.slot', 'a slot id' ),
			'sizes'   => $text( $vars['sizes'] ?? null ) ? $vars['sizes'] : '',
			'reason'  => $text( $vars['reason'] ?? null ) ? $vars['reason'] : '',
		);
		if ( '' === $size['sizes'] ) {
			$errors[] = sprintf( '%s.sizes must be a non-empty string', $at );
		}
		if ( '' === $size['reason'] ) {
			$errors[] = sprintf( '%s.reason must be a non-empty string', $at );
		}
		if ( array_key_exists( 'band', $vars ) ) {
			if ( ! in_array( $vars['band'], array( 'full', 'wide', 'default', 'narrow' ), true ) ) {
				$errors[] = sprintf( '%s.band must be full, wide, default or narrow', $at );
			} else {
				$size['band'] = $vars['band'];
			}
		}
		$image_sizes[] = $size;
	}

	$client_sections = array();
	$seen            = array();
	foreach ( ( array_key_exists( 'clientSections', $top ) ? $list( $top['clientSections'], 'clientSections' ) : array() ) as $i => $item ) {
		$at   = sprintf( 'clientSections[%d]', $i );
		$vars = $object( $item, $at, array( 'id', 'reason' ), array() );
		if ( null === $vars ) {
			continue;
		}
		$client = $match( $vars['id'] ?? null, $id, $at . '.id', 'a section id' );
		if ( '' !== $client ) {
			if ( isset( $seen[ $client ] ) ) {
				$errors[] = sprintf( '%s: client section "%s" is listed twice', $at, $client );
			}
			$seen[ $client ] = true;
		}
		if ( ! $text( $vars['reason'] ?? null ) ) {
			$errors[] = sprintf( '%s.reason must be a non-empty string', $at );
		}
		$client_sections[] = array(
			'id'     => $client,
			'reason' => is_string( $vars['reason'] ?? null ) ? $vars['reason'] : '',
		);
	}

	if ( array() !== $errors ) {
		return array(
			'declaration' => null,
			'errors'      => $errors,
		);
	}

	return array(
		'declaration' => array(
			'$schema'        => $top['$schema'],
			'site'           => $top['site'],
			'platform'       => $top['platform'],
			'contract'       => $contract,
			'sections'       => $sections,
			'imageSizes'     => $image_sizes,
			'clientSections' => $client_sections,
		),
		'errors'      => array(),
	);
}

/**
 * Read and validate the declaration in a child theme directory. Pure apart
 * from the file read: no logging, no caching (salt_overrides_load() does
 * both).
 *
 * @param string $child_dir Child theme directory (get_stylesheet_directory()).
 * @return array{state: string, declaration: array<string,mixed>|null, errors: string[]}
 *         state: 'absent' (no file) | 'invalid' | 'valid'.
 */
function salt_overrides_read( string $child_dir ): array {
	$file = rtrim( $child_dir, '/\\' ) . '/' . SALT_OVERRIDES_FILE;

	if ( ! is_file( $file ) ) {
		return array(
			'state'       => 'absent',
			'declaration' => null,
			'errors'      => array(),
		);
	}

	$invalid = static fn( string $error ): array => array(
		'state'       => 'invalid',
		'declaration' => null,
		'errors'      => array( $error ),
	);

	$size = is_readable( $file ) ? filesize( $file ) : false;
	if ( false === $size ) {
		return $invalid( 'the file cannot be read' );
	}
	if ( $size > SALT_OVERRIDES_MAX_BYTES ) {
		return $invalid( sprintf( 'the file is larger than %d bytes', SALT_OVERRIDES_MAX_BYTES ) );
	}

	$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local child-theme file; WP_Filesystem is not appropriate on the front end.
	if ( false === $raw ) {
		return $invalid( 'the file cannot be read' );
	}
	if ( str_starts_with( $raw, "\xEF\xBB\xBF" ) ) {
		$raw = substr( $raw, 3 );
	}

	try {
		$data = json_decode( $raw, false, 32, JSON_THROW_ON_ERROR );
	} catch ( JsonException $e ) {
		return $invalid( 'the file is not valid JSON (' . $e->getMessage() . ')' );
	}

	$result = salt_overrides_validate( $data );
	return array(
		'state'       => null === $result['declaration'] ? 'invalid' : 'valid',
		'declaration' => $result['declaration'],
		'errors'      => $result['errors'],
	);
}

/**
 * The declaration for this request, read once (memoised).
 *
 * 'none' when no child theme is active (the stylesheet directory is the
 * parent's). An invalid file is logged once per request (salt_log(), never
 * a throw: a typo in the file must not take the front end down) and then
 * treated as no declaration.
 *
 * @param bool $reset Forget the memoised result (tests; salt_overrides_reset()).
 * @return array{state: string, declaration: array<string,mixed>|null, errors: string[], child_dir: string}
 *         state: 'none' | 'absent' | 'invalid' | 'valid'.
 */
function salt_overrides_load( bool $reset = false ): array {
	static $loaded = null;

	if ( $reset ) {
		$loaded = null;
		return array(
			'state'       => 'none',
			'declaration' => null,
			'errors'      => array(),
			'child_dir'   => '',
		);
	}

	if ( null !== $loaded ) {
		return $loaded;
	}

	$child_dir  = get_stylesheet_directory();
	$child_real = realpath( $child_dir );
	if ( false === $child_real || realpath( get_template_directory() ) === $child_real ) {
		$loaded = array(
			'state'       => 'none',
			'declaration' => null,
			'errors'      => array(),
			'child_dir'   => '',
		);
		return $loaded;
	}

	$loaded              = salt_overrides_read( $child_real );
	$loaded['child_dir'] = $child_real;

	if ( 'invalid' === $loaded['state'] ) {
		salt_log(
			sprintf(
				'%1$s in the child theme "%2$s" is not valid (%3$s) — no section is treated as declared until it is fixed (OVERRIDES.md).',
				SALT_OVERRIDES_FILE,
				basename( $child_real ),
				implode( '; ', array_slice( $loaded['errors'], 0, 3 ) ) . ( count( $loaded['errors'] ) > 3 ? '; …' : '' )
			)
		);
	}

	return $loaded;
}

/**
 * Forget the memoised declaration (per test; after switching themes).
 *
 * @return void
 */
function salt_overrides_reset(): void {
	salt_overrides_load( true );
}

/**
 * The child theme's replaced-logic declaration (salt-overrides.json), or
 * null when no child theme is active, the file is absent, or it is invalid.
 *
 * Shape (validated; plain arrays):
 *   $schema         string
 *   site            string
 *   platform        string   always the WordPress platform's enum value
 *   contract        string   salt-contract release the site's pin conforms to (x.y.z)
 *   sections        list<array{section: string, tier: 'view'|'logic', files: string[],
 *                              reason: string, since: string, verified: string}>
 *   imageSizes      list<array{section: string, slot: string, band?: string, sizes: string, reason: string}>
 *   clientSections  list<array{id: string, reason: string}>
 * Section ids are the CONTRACT's (salt_contract_section_layouts() maps
 * them to layout slugs). Read once per request.
 *
 * @return array<string,mixed>|null
 */
function salt_overrides(): ?array {
	return salt_overrides_load()['declaration'];
}

/**
 * The declared tier of a layout: 'logic' when a contract section the
 * layout is (for this row) is declared `logic`, else 'view' when one is
 * declared `view`, else null (undeclared, or no valid declaration).
 *
 * For testimonials pass the row's layout field as $variant (the render
 * path reads it with salt_contract_section_variant()), so a grid row is
 * looked up as collection-showcase and a slider row as carousel. With
 * $variant null every section the layout can be counts.
 *
 * @param string      $layout  Layout slug (hyphenated).
 * @param string|null $variant Raw value of the layout's condition field, or null.
 * @return string|null
 */
function salt_override_tier( string $layout, ?string $variant = null ): ?string {
	return salt_override_tier_in( salt_overrides(), $layout, $variant );
}

/**
 * salt_override_tier() against a given declaration (pure).
 *
 * @param array<string,mixed>|null $declaration Validated declaration, or null.
 * @param string                   $layout      Layout slug (hyphenated).
 * @param string|null              $variant     Raw value of the layout's condition field, or null.
 * @return string|null 'logic' | 'view' | null.
 */
function salt_override_tier_in( ?array $declaration, string $layout, ?string $variant = null ): ?string {
	if ( null === $declaration ) {
		return null;
	}

	$ids  = salt_contract_sections_for_layout( $layout, $variant );
	$tier = null;
	foreach ( $declaration['sections'] as $entry ) {
		if ( ! in_array( $entry['section'], $ids, true ) ) {
			continue;
		}
		if ( 'logic' === $entry['tier'] ) {
			return 'logic';
		}
		$tier = 'view';
	}
	return $tier;
}

/**
 * Whether a file lives in the active CHILD theme (false when no child theme
 * is active, so a parent file is never mistaken for a child's).
 *
 * @param string $path Absolute file path.
 * @return bool
 */
function salt_override_is_child_file( string $path ): bool {
	$child  = realpath( get_stylesheet_directory() );
	$parent = realpath( get_template_directory() );
	$file   = realpath( $path );

	if ( false === $child || false === $file || $child === $parent ) {
		return false;
	}
	return str_starts_with( $file, $child . DIRECTORY_SEPARATOR );
}
