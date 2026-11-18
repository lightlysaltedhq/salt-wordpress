<?php
/**
 * Phase 2: the contract's field shape on real ACF Pro. HARNESS ONLY.
 *
 * 1. Registration: the group registered under group_salt_sections is the
 *    emitted JSON (SALT_HARNESS_ACF_JSON), every layout and field present,
 *    each field's name, type and `salt` provenance kept.
 * 2. Seeding: one page holds one row per contract fixture case of every
 *    section that is a page-sections layout, in fixture order, saved with
 *    update_field( 'sections', ... ). Named media and documents become real
 *    attachments and pages.
 * 3. Read back with the theme's idioms (have_rows( 'sections' ), the_row(),
 *    get_row_layout(), get_sub_field(), nested have_rows( 'settings' )),
 *    compared leaf by leaf with what was saved, reported per case.
 * 4. Storage: every saved leaf's meta key and its _key reference, read from
 *    the database; a probe page for the paths named in the brief; meta keys
 *    differing only in case, in the database and in the emitted shape.
 *
 * Writes SALT_HARNESS_STATE (JSON) for the browser phase.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';

wp_set_current_user( 1 );

$emitted  = json_decode( (string) file_get_contents( (string) getenv( 'SALT_HARNESS_ACF_JSON' ) ), true );
$fixtures = (string) getenv( 'SALT_HARNESS_FIXTURES' );

// ── 1. Registration ──────────────────────────────────────────────────────────────────────────

$emitted_layouts = $emitted[0]['fields'][0]['layouts'];
$emitted_fields  = array();
foreach ( $emitted_layouts as $layout ) {
	foreach ( salt_harness_walk_fields( $layout['sub_fields'] ) as $entry ) {
		$emitted_fields[ $entry[1]['key'] ] = array( $layout['name'] . '.' . $entry[0], $entry[1] );
	}
}

$group     = acf_get_local_field_group( 'group_salt_sections' );
$container = acf_get_field( 'field_salt_sections_container' );
$layouts   = is_array( $container ) ? array_values( $container['layouts'] ) : array();
$acf_count = 0;
foreach ( $layouts as $layout ) {
	$acf_count += count( salt_harness_walk_fields( $layout['sub_fields'] ?? array() ) );
}

salt_harness_result(
	is_array( $group ) && 'Page sections' === $group['title'] && wp_list_pluck( $layouts, 'name' ) === wp_list_pluck( $emitted_layouts, 'name' ),
	'emitted group registered in place of the hand-written one',
	sprintf( '%d layouts registered of %d emitted: %s', count( $layouts ), count( $emitted_layouts ), implode( ', ', wp_list_pluck( $layouts, 'name' ) ) )
);

$drift      = array();
$salt_kept  = 0;
$salt_total = 0;
foreach ( $emitted_fields as $key => $entry ) {
	list( $where, $want ) = $entry;
	$got                  = acf_get_field( $key );
	if ( ! is_array( $got ) ) {
		$drift[] = $where . ' (' . $key . ') not registered';
		continue;
	}
	if ( $got['name'] !== $want['name'] || $got['type'] !== $want['type'] ) {
		$drift[] = sprintf( '%s registered as %s/%s, emitted %s/%s', $where, $got['name'], $got['type'], $want['name'], $want['type'] );
	}
	if ( isset( $want['salt'] ) ) {
		++$salt_total;
		if ( ( $got['salt'] ?? null ) === $want['salt'] ) {
			++$salt_kept;
		} else {
			$drift[] = $where . ' lost its salt provenance';
		}
	}
}
salt_harness_result(
	count( $emitted_fields ) === $acf_count && array() === $drift,
	'every emitted field registered verbatim',
	sprintf( '%d fields registered of %d emitted; name and type kept on all; salt provenance kept on %d of %d', $acf_count, count( $emitted_fields ), $salt_kept, $salt_total )
);
foreach ( array_slice( $drift, 0, 20 ) as $line ) {
	salt_harness_info( $line );
}

$by_layout = array();
foreach ( $layouts as $layout ) {
	$by_layout[ $layout['name'] ] = $layout;
}

// ── Static shape: meta paths that could differ only in case ──────────────────────────────────

$templates = array();
foreach ( $emitted_layouts as $layout ) {
	foreach ( salt_harness_templates( $layout['sub_fields'], 'sections_N' ) as $template ) {
		$templates[ strtolower( $template ) ][ $template ] = $layout['name'];
	}
}
$static_collisions = array_filter( $templates, static fn( array $t ): bool => count( $t ) > 1 );
salt_harness_result(
	array() === $static_collisions,
	'no case-only collisions in the emitted shape',
	sprintf( '%d distinct storage paths across %d layouts (rows as N), %d collide when lower-cased', count( $templates ), count( $emitted_layouts ), count( $static_collisions ) )
);
foreach ( $static_collisions as $set ) {
	salt_harness_info( 'collision: ' . implode( ' vs ', array_keys( $set ) ) );
}

// ── 2. Seed one row per fixture case ─────────────────────────────────────────────────────────

$cases   = array();
$skipped = array();
foreach ( (array) glob( $fixtures . '/*', GLOB_ONLYDIR ) as $dir ) {
	if ( ! isset( $by_layout[ basename( $dir ) ] ) ) {
		$skipped[] = basename( $dir );
	}
}
foreach ( $emitted_layouts as $layout ) {
	$files = (array) glob( $fixtures . '/' . $layout['name'] . '/*.json' );
	sort( $files );
	foreach ( $files as $file ) {
		$fixture = json_decode( (string) file_get_contents( $file ), true );
		$cases[] = array(
			'layout' => $layout['name'],
			'case'   => basename( $file, '.json' ),
			'values' => $fixture['values'],
			'media'  => $fixture['media'] ?? array(),
		);
	}
}
$with_cases = count( array_unique( array_column( $cases, 'layout' ) ) );
salt_harness_result(
	count( $emitted_layouts ) === $with_cases,
	'every layout has fixture cases',
	sprintf( '%d cases across %d of %d layouts; fixture sections not in page sections (not seeded): %s', count( $cases ), $with_cases, count( $emitted_layouts ), implode( ', ', $skipped ) )
);

$rows     = array();
$expected = array();
foreach ( $cases as $i => $case ) {
	$layout       = $by_layout[ $case['layout'] ];
	$converted    = salt_harness_to_acf(
		$case['values'],
		array(
			'type'       => 'group',
			'sub_fields' => $layout['sub_fields'],
		)
	);
	$expected[]   = $converted;
	$rows[]       = array( 'acf_fc_layout' => $case['layout'] ) + $converted;
	$cases[ $i ] += array( 'row' => $i );
}

$page  = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Harness: every fixture case',
	)
);
$saved = update_field( 'sections', $rows, $page );
salt_harness_result(
	false !== $saved && (int) $page > 0,
	'fixture page saved through update_field',
	sprintf( 'page %d, %d rows (one per case)', $page, count( $rows ) )
);

// ── 3. Read back through the theme's idioms ──────────────────────────────────────────────────

$stats  = array();
$nested = array(
	'checked' => 0,
	'ok'      => 0,
);
$index  = 0;
$order  = array();
if ( have_rows( 'sections', $page ) ) {
	while ( have_rows( 'sections', $page ) ) {
		the_row();
		$order[] = get_row_layout();
		$layout  = $by_layout[ get_row_layout() ];
		$want    = $expected[ $index ] ?? array();
		$stat    = array(
			'leaves'  => 0,
			'exact'   => 0,
			'coerced' => 0,
			'misses'  => array(),
		);
		foreach ( $want as $name => $value ) {
			salt_harness_compare( $value, get_sub_field( $name ), salt_harness_sub( $layout['sub_fields'], $name ), $name, $stat );
		}
		// The nested idiom: have_rows( 'settings' ) over the settings group.
		if ( isset( $want['settings'] ) && have_rows( 'settings' ) ) {
			while ( have_rows( 'settings' ) ) {
				the_row();
				foreach ( $want['settings'] as $name => $value ) {
					if ( is_array( $value ) ) {
						continue;
					}
					++$nested['checked'];
					$sub = salt_harness_sub( salt_harness_sub( $layout['sub_fields'], 'settings' )['sub_fields'], $name );
					$one = array(
						'leaves'  => 0,
						'exact'   => 0,
						'coerced' => 0,
						'misses'  => array(),
					);
					salt_harness_compare( $value, get_sub_field( $name ), $sub, $name, $one );
					if ( array() === $one['misses'] ) {
						++$nested['ok'];
					} else {
						$stat['misses'][] = 'nested have_rows(settings): ' . implode( '; ', $one['misses'] );
					}
				}
			}
		}
		$stats[] = $stat;
		++$index;
	}
}

salt_harness_result(
	array_column( $cases, 'layout' ) === $order,
	'have_rows( sections ) returns every row in order',
	sprintf( '%d rows read back, %d saved', count( $order ), count( $cases ) )
);

$total = array(
	'leaves'  => 0,
	'exact'   => 0,
	'coerced' => 0,
	'misses'  => 0,
);
foreach ( $cases as $i => $case ) {
	$stat = $stats[ $i ] ?? array(
		'leaves'  => 0,
		'exact'   => 0,
		'coerced' => 0,
		'misses'  => array( 'row not read back' ),
	);
	foreach ( array( 'leaves', 'exact', 'coerced' ) as $k ) {
		$total[ $k ] += $stat[ $k ];
	}
	$total['misses'] += count( $stat['misses'] );
	salt_harness_result(
		array() === $stat['misses'],
		sprintf( 'case %s/%s (row %d)', $case['layout'], $case['case'], $i ),
		sprintf( '%d values read back, %d identical, %d equal after type coercion%s', $stat['leaves'], $stat['exact'], $stat['coerced'], array() === $stat['misses'] ? '' : ', ' . count( $stat['misses'] ) . ' wrong' )
	);
	foreach ( $stat['misses'] as $miss ) {
		salt_harness_info( $miss );
	}
}
salt_harness_result(
	0 === $total['misses'] && $total['leaves'] > 0,
	'all fixture values read back',
	sprintf( '%d values saved and read back across %d cases: %d identical, %d equal after type coercion (numbers stored as strings), %d wrong', $total['leaves'], count( $cases ), $total['exact'], $total['coerced'], $total['misses'] )
);
salt_harness_result(
	$nested['checked'] > 0 && $nested['checked'] === $nested['ok'],
	'nested have_rows( settings ) idiom',
	sprintf( '%d settings values read inside have_rows( settings ), %d correct', $nested['checked'], $nested['ok'] )
);

// ── 4. Storage paths, from the database ──────────────────────────────────────────────────────

$meta    = salt_harness_meta( $page );
$storage = array(
	'paths'          => 0,
	'ok'             => 0,
	'false_as_empty' => 0,
	'misses'         => array(),
);
foreach ( $cases as $i => $case ) {
	salt_harness_storage(
		$expected[ $i ],
		array(
			'type'       => 'group',
			'sub_fields' => $by_layout[ $case['layout'] ]['sub_fields'],
		),
		'sections_' . $i,
		$meta,
		$storage,
		true
	);
}
$stored_layouts = maybe_unserialize( $meta['sections'] ?? '' );
salt_harness_result(
	array_column( $cases, 'layout' ) === $stored_layouts && 'field_salt_sections_container' === ( $meta['_sections'] ?? '' ),
	'sections meta holds the layout list',
	'sections = serialised list of ' . ( is_array( $stored_layouts ) ? count( $stored_layouts ) : 0 ) . ' layout names, _sections = ' . ( $meta['_sections'] ?? 'missing' )
);
salt_harness_result(
	array() === $storage['misses'],
	'every saved value at its contract storage path with its key reference',
	sprintf( '%d meta paths checked (values, groups, repeater counts), %d correct', $storage['paths'], $storage['ok'] )
);
salt_harness_info( sprintf( '%d unchecked true_false values were stored as "" by update_field( false ) (the edit form stores "0"; both read back as false)', $storage['false_as_empty'] ) );
foreach ( array_slice( $storage['misses'], 0, 30 ) as $miss ) {
	salt_harness_info( $miss );
}

// The probe: the paths the brief names, set explicitly (no fixture sets them all).
$term  = wp_insert_term( 'Harness category', 'category' );
$post  = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'publish',
		'post_title'  => 'Harness post',
	)
);
$probe = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Harness: storage probe',
	)
);
$image = salt_harness_attachment( 'probe' );
update_field(
	'sections',
	array(
		array(
			'acf_fc_layout' => 'hero',
			'variant'       => 'full-bleed',
			'heading'       => 'Probe',
			'settings'      => array(
				'spacing'         => 'lg',
				'backgroundImage' => array(
					'image'         => $image,
					'scrimStrength' => salt_harness_choice( $by_layout['hero'], 'settings.backgroundImage.scrimStrength' ),
				),
			),
		),
		array(
			'acf_fc_layout' => 'collection-showcase',
			'source'        => 'posts',
			'query'         => array(
				'mode'       => 'by-category',
				'categories' => array( (string) $term['term_id'] ),
				'items'      => array( $post ),
				'order'      => 'newest',
				'count'      => 4,
			),
		),
	),
	$probe
);
$probe_meta = salt_harness_meta( $probe );
$named      = array(
	'sections_0_settings_spacing'                       => 'field_salt_hero_settings_spacing',
	'sections_0_settings_backgroundImage_image'         => 'field_salt_hero_settings_background_image_image',
	'sections_0_settings_backgroundImage_scrimStrength' => 'field_salt_hero_settings_background_image_scrim_strength',
	'sections_1_query_mode'                             => 'field_salt_collection_showcase_query_mode',
	'sections_1_query_categories'                       => 'field_salt_collection_showcase_query_categories',
	'sections_1_query_items'                            => 'field_salt_collection_showcase_query_items',
	'sections_1_query_order'                            => 'field_salt_collection_showcase_query_order',
	'sections_1_query_count'                            => 'field_salt_collection_showcase_query_count',
);
foreach ( $named as $path => $key ) {
	salt_harness_result(
		isset( $probe_meta[ $path ] ) && ( $probe_meta[ '_' . $path ] ?? '' ) === $key,
		'storage path ' . $path,
		sprintf( 'value %s, _%s = %s', isset( $probe_meta[ $path ] ) ? wp_json_encode( $probe_meta[ $path ] ) : 'missing', $path, $probe_meta[ '_' . $path ] ?? 'missing' )
	);
}

// The idioms on the probe: get_sub_field( 'settings' )['spacing'] and nested have_rows.
$idiom = array();
if ( have_rows( 'sections', $probe ) ) {
	while ( have_rows( 'sections', $probe ) ) {
		the_row();
		if ( 'hero' === get_row_layout() ) {
			$idiom['settings'] = get_sub_field( 'settings' )['spacing'] ?? null;
			while ( have_rows( 'settings' ) ) {
				the_row();
				$idiom['nested'] = get_sub_field( 'spacing' );
				while ( have_rows( 'backgroundImage' ) ) {
					the_row();
					$idiom['image'] = get_sub_field( 'image' );
				}
			}
		} else {
			$idiom['query'] = get_sub_field( 'query' );
		}
	}
}
salt_harness_result(
	'lg' === ( $idiom['settings'] ?? null ) && 'lg' === ( $idiom['nested'] ?? null ) && (int) ( $idiom['image'] ?? 0 ) === $image,
	'probe read back through the theme idioms',
	sprintf( "get_sub_field('settings')['spacing'] = %s; have_rows('settings') get_sub_field('spacing') = %s; nested have_rows('backgroundImage') image = %s", wp_json_encode( $idiom['settings'] ?? null ), wp_json_encode( $idiom['nested'] ?? null ), wp_json_encode( $idiom['image'] ?? null ) )
);
$query = $idiom['query'] ?? array();
salt_harness_result(
	'by-category' === ( $query['mode'] ?? null ) && array( (string) $term['term_id'] ) === array_map( 'strval', (array) ( $query['categories'] ?? array() ) ) && array( $post ) === array_map( 'intval', (array) ( $query['items'] ?? array() ) ) && 'newest' === ( $query['order'] ?? null ) && 4 === (int) ( $query['count'] ?? 0 ),
	'probe collection-query read back',
	"get_sub_field('query') = " . wp_json_encode( $query )
);

// Case-only collisions among the stored meta keys of both pages.
$db_collisions = array();
$key_total     = 0;
foreach ( array( $page, $probe ) as $id ) {
	$lower = array();
	foreach ( array_keys( salt_harness_meta( $id ) ) as $key ) {
		++$key_total;
		$lower[ strtolower( $key ) ][] = $key;
	}
	foreach ( $lower as $set ) {
		if ( count( $set ) > 1 ) {
			$db_collisions[] = implode( ' vs ', $set );
		}
	}
}
salt_harness_result(
	array() === $db_collisions,
	'no case-only collisions among stored meta keys',
	sprintf( '%d meta keys on the two pages, %d collide when lower-cased', $key_total, count( $db_collisions ) )
);

file_put_contents(
	(string) getenv( 'SALT_HARNESS_STATE' ),
	wp_json_encode(
		array(
			'fixturesPage' => $page,
			'probePage'    => $probe,
			'rows'         => array_map( static fn( array $c ): string => $c['layout'] . '/' . $c['case'], $cases ),
			'layouts'      => wp_list_pluck( $emitted_layouts, 'name' ),
		)
	)
);

salt_harness_exit();

// ── Helpers ──────────────────────────────────────────────────────────────────────────────────

/**
 * A field's sub field by name.
 *
 * @param array<int,array<string,mixed>> $fields Sub fields.
 * @param string                         $name   Name.
 * @return array<string,mixed>
 */
function salt_harness_sub( array $fields, string $name ): array {
	foreach ( $fields as $field ) {
		if ( ( $field['name'] ?? '' ) === $name ) {
			return $field;
		}
	}
	return array(
		'type' => 'missing',
		'key'  => '',
		'name' => $name,
	);
}

/**
 * The first choice of a select at a dotted path in a layout.
 *
 * @param array<string,mixed> $layout Layout.
 * @param string              $path   Dotted path of names.
 * @return string
 */
function salt_harness_choice( array $layout, string $path ): string {
	$field = array( 'sub_fields' => $layout['sub_fields'] );
	foreach ( explode( '.', $path ) as $name ) {
		$field = salt_harness_sub( $field['sub_fields'] ?? array(), $name );
	}
	return (string) array_key_last( $field['choices'] ?? array( '' => '' ) );
}

/**
 * Every storage path template under a list of fields; repeater rows as N.
 *
 * @param array<int,array<string,mixed>> $fields Fields.
 * @param string                         $prefix Path so far.
 * @return array<int,string>
 */
function salt_harness_templates( array $fields, string $prefix ): array {
	$out = array();
	foreach ( $fields as $field ) {
		$path  = $prefix . '_' . $field['name'];
		$out[] = $path;
		if ( 'group' === $field['type'] ) {
			array_push( $out, ...salt_harness_templates( $field['sub_fields'], $path ) );
		} elseif ( 'repeater' === $field['type'] ) {
			array_push( $out, ...salt_harness_templates( $field['sub_fields'], $path . '_N' ) );
		}
	}
	return $out;
}

/**
 * A real 1x1 PNG attachment per media name, made once.
 *
 * @param string $name Fixture media name.
 * @return int Attachment ID.
 */
function salt_harness_attachment( string $name ): int {
	static $ids = array();
	if ( isset( $ids[ $name ] ) ) {
		return $ids[ $name ];
	}
	$uploads = wp_upload_dir();
	$file    = $uploads['path'] . '/' . $name . '.png';
	file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- A fixed 1x1 PNG.
	$id = wp_insert_attachment(
		array(
			'post_title'     => $name,
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
			'guid'           => $uploads['url'] . '/' . $name . '.png',
		),
		$file
	);
	wp_update_attachment_metadata(
		$id,
		array(
			'width'  => 1,
			'height' => 1,
			'file'   => _wp_relative_upload_path( $file ),
			'sizes'  => array(),
		)
	);
	$ids[ $name ] = (int) $id;
	return $ids[ $name ];
}

/**
 * A published post of a type per document name, made once.
 *
 * @param string $name      Fixture document name.
 * @param string $post_type Post type.
 * @return int Post ID.
 */
function salt_harness_document( string $name, string $post_type ): int {
	static $ids = array();
	$key        = $post_type . ':' . $name;
	if ( ! isset( $ids[ $key ] ) ) {
		$ids[ $key ] = (int) wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_title'  => ucfirst( $name ),
				'post_name'   => $name,
			)
		);
	}
	return $ids[ $key ];
}

/**
 * Contract-shaped values to what ACF stores: named media and documents become
 * IDs; everything else is passed as the contract has it.
 *
 * @param mixed               $value Contract value.
 * @param array<string,mixed> $field ACF field.
 * @return mixed
 */
function salt_harness_to_acf( $value, array $field ) {
	switch ( $field['type'] ) {
		case 'group':
			$out = array();
			foreach ( (array) $value as $name => $v ) {
				$out[ $name ] = salt_harness_to_acf( $v, salt_harness_sub( $field['sub_fields'], (string) $name ) );
			}
			return $out;
		case 'repeater':
			return array_map(
				static fn( $row ) => salt_harness_to_acf(
					$row,
					array(
						'type'       => 'group',
						'sub_fields' => $field['sub_fields'],
					)
				),
				(array) $value
			);
		case 'image':
			return salt_harness_attachment( (string) $value );
		case 'gallery':
			return array_map( static fn( $v ) => salt_harness_attachment( (string) $v ), (array) $value );
		case 'post_object':
			return salt_harness_document( (string) $value, (string) ( $field['post_type'][0] ?? 'page' ) );
		case 'relationship':
			return array_map( static fn( $v ) => salt_harness_document( (string) $v, (string) ( $field['post_type'][0] ?? 'page' ) ), (array) $value );
		case 'taxonomy':
			return array_map( static fn( $v ) => (int) ( wp_insert_term( (string) $v, $field['taxonomy'] )['term_id'] ?? 0 ), (array) $value );
		default:
			return $value;
	}
}

/**
 * Compare a saved value with what get_sub_field() returned, leaf by leaf.
 *
 * @param mixed                $want  Saved (ACF-shaped) value.
 * @param mixed                $got   Read back, formatted.
 * @param array<string,mixed>  $field ACF field.
 * @param string               $path  Dotted path, for the report.
 * @param array<string,mixed>  $stat  Counters, updated.
 * @return void
 */
function salt_harness_compare( $want, $got, array $field, string $path, array &$stat ): void {
	if ( 'group' === $field['type'] ) {
		foreach ( (array) $want as $name => $v ) {
			salt_harness_compare( $v, is_array( $got ) ? ( $got[ $name ] ?? null ) : null, salt_harness_sub( $field['sub_fields'], (string) $name ), $path . '.' . $name, $stat );
		}
		return;
	}
	if ( 'repeater' === $field['type'] && array() !== $want ) {
		if ( ! is_array( $got ) || count( $got ) !== count( $want ) ) {
			++$stat['leaves'];
			$stat['misses'][] = sprintf( '%s: %d rows saved, read back %s', $path, count( $want ), wp_json_encode( $got ) );
			return;
		}
		foreach ( array_values( $want ) as $i => $row ) {
			salt_harness_compare(
				$row,
				$got[ $i ] ?? null,
				array(
					'type'       => 'group',
					'sub_fields' => $field['sub_fields'],
				),
				$path . '.' . $i,
				$stat
			);
		}
		return;
	}

	++$stat['leaves'];
	if ( is_array( $want ) && array() === $want ) {
		$ok    = empty( $got );
		$exact = $ok;
	} elseif ( is_array( $want ) ) {
		$ok    = is_array( $got ) && array_map( 'intval', $want ) === array_map( 'intval', array_values( $got ) );
		$exact = $ok && array_values( $got ) === $want;
	} elseif ( 'wysiwyg' === $field['type'] ) {
		$html  = (string) apply_filters( 'acf_the_content', $want );
		$ok    = $html === $got;
		$exact = $ok;
	} elseif ( in_array( $field['type'], array( 'image', 'post_object' ), true ) ) {
		$ok    = is_numeric( $got ) && (int) $got === $want;
		$exact = $ok && $got === $want;
	} else {
		$exact = $got === $want;
		$ok    = $exact || ( is_scalar( $got ) && is_scalar( $want ) && ! is_bool( $want ) && (string) $got === (string) $want );
	}
	if ( ! $ok ) {
		$stat['misses'][] = sprintf( '%s (%s): saved %s, read back %s', $path, $field['type'], wp_json_encode( $want ), wp_json_encode( $got ) );
	} elseif ( $exact ) {
		++$stat['exact'];
	} else {
		++$stat['coerced'];
	}
}

/**
 * Every meta row of a post, straight from the database.
 *
 * @param int $post_id Post ID.
 * @return array<string,string>
 */
function salt_harness_meta( int $post_id ): array {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d", $post_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The check reads the database itself, not a cache.
	return array_column( $rows, 'meta_value', 'meta_key' );
}

/**
 * Check each saved value is stored at its contract path with its key.
 *
 * @param mixed               $want  Saved (ACF-shaped) value.
 * @param array<string,mixed> $field ACF field.
 * @param string              $path  Meta key.
 * @param array<string,string> $meta  The post's meta.
 * @param array<string,mixed> $st    Counters, updated.
 * @param bool                $row   Whether $path is a flexible row (no meta of its own).
 * @return void
 */
function salt_harness_storage( $want, array $field, string $path, array $meta, array &$st, bool $row = false ): void {
	$fail = static function ( string $why ) use ( &$st, $path ): void {
		$st['misses'][] = $path . ': ' . $why;
	};
	if ( ! $row ) {
		++$st['paths'];
		if ( ( $meta[ '_' . $path ] ?? null ) !== $field['key'] ) {
			$fail( '_' . $path . ' is ' . wp_json_encode( $meta[ '_' . $path ] ?? null ) . ', expected ' . $field['key'] );
			return;
		}
	}
	if ( 'group' === $field['type'] ) {
		if ( ! $row ) {
			++$st['ok'];
		}
		foreach ( (array) $want as $name => $v ) {
			salt_harness_storage( $v, salt_harness_sub( $field['sub_fields'], (string) $name ), $path . '_' . $name, $meta, $st );
		}
		return;
	}
	if ( 'repeater' === $field['type'] ) {
		if ( (string) count( $want ) !== (string) ( $meta[ $path ] ?? '' ) ) {
			$fail( 'row count ' . wp_json_encode( $meta[ $path ] ?? null ) . ', expected ' . count( $want ) );
			return;
		}
		++$st['ok'];
		foreach ( array_values( $want ) as $i => $r ) {
			foreach ( $r as $name => $v ) {
				salt_harness_storage( $v, salt_harness_sub( $field['sub_fields'], (string) $name ), $path . '_' . $i . '_' . $name, $meta, $st );
			}
		}
		return;
	}
	$stored = $meta[ $path ] ?? null;
	if ( is_array( $want ) ) {
		$got = maybe_unserialize( (string) $stored );
		$ok  = array() === $want ? empty( $got ) : is_array( $got ) && array_map( 'intval', $want ) === array_map( 'intval', $got );
	} elseif ( is_bool( $want ) ) {
		// update_field( false ) stores '' (ACF writes the PHP value as given); the edit form
		// stores '0'. Both read back as false; the report counts the first.
		$ok = ( $want ? '1' : '0' ) === $stored || ( false === $want && '' === $stored );
		if ( false === $want && '' === $stored ) {
			++$st['false_as_empty'];
		}
	} else {
		$ok = (string) $want === $stored;
	}
	if ( $ok ) {
		++$st['ok'];
	} else {
		$fail( 'stored ' . wp_json_encode( $stored ) . ', saved ' . wp_json_encode( $want ) );
	}
}
