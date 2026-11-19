<?php
/**
 * Phase: the collection field groups on real ACF Pro. HARNESS ONLY.
 *
 * For every group the theme registers from inc/fields/generated/
 * collections.json (one per collection, on its post type), makes a post of
 * that type and seeds a value for every generated field through
 * update_field(): text, the url and email formats, numbers within their
 * range and step, selects, true/false, images, galleries, relationships,
 * the primary topic, date and time pickers, repeaters, groups, and a post's
 * layout with a row of every section it offers. Each value is read back
 * with get_field() and compared, and every meta path is checked to carry
 * its field key. Then the places WordPress keeps natively (salt.native: the
 * title, the excerpt, the body and the featured image) are set the WordPress
 * way and read back through the theme's own readers.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';

/**
 * A real 1x1 PNG attachment per name, made once.
 *
 * @param string $name Name.
 * @return int Attachment ID.
 */
function salt_harness_collection_image( string $name ): int {
	static $ids = array();
	if ( ! isset( $ids[ $name ] ) ) {
		$uploads = wp_upload_dir();
		$file    = $uploads['path'] . '/collection-' . $name . '.png';
		file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- A fixed 1x1 PNG.
		$ids[ $name ] = (int) wp_insert_attachment(
			array(
				'post_title'     => $name,
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
			),
			$file
		);
	}
	return $ids[ $name ];
}

/**
 * A published post of a type to point a relationship at, made once per type.
 *
 * @param string $post_type Post type.
 * @return int Post ID.
 */
function salt_harness_collection_target( string $post_type ): int {
	static $ids = array();
	if ( ! isset( $ids[ $post_type ] ) ) {
		$ids[ $post_type ] = (int) wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_title'  => 'Harness target ' . $post_type,
			)
		);
	}
	return $ids[ $post_type ];
}

/**
 * A value ACF stores for one field, chosen from its definition.
 *
 * @param array<string,mixed> $field ACF field.
 * @return mixed
 */
function salt_harness_collection_value( array $field ) {
	$name = (string) $field['name'];
	switch ( $field['type'] ) {
		case 'text':
			if ( 'slug' === $name ) {
				return 'harness-person';
			}
			$format = $field['salt']['format'] ?? '';
			if ( 'url' === $format ) {
				return 'https://www.linkedin.com/in/harness-' . strtolower( $name );
			}
			return 'email' === $format ? 'harness@example.test' : 'Harness ' . $name;
		case 'textarea':
			return isset( $field['salt']['maxLines'] ) ? "Harness line one\nHarness line two" : 'Harness ' . $name . ' text.';
		case 'wysiwyg':
			return '<p>Harness <strong>' . $name . '</strong>.</p>';
		case 'number':
			$min  = (float) ( $field['min'] ?? 0 );
			$max  = (float) ( $field['max'] ?? 10 );
			$step = (float) ( $field['step'] ?? 0 );
			// Mid-range on the field's step; one more step where it is a fraction, so a
			// rating stores a half star (3.5); a decimal where there is no step (lat 0.25).
			$v = $step > 0 ? $min + $step * floor( ( $max - $min ) / 2 / $step ) + ( $step < 1 ? $step : 0 ) : ( $min + $max ) / 2 + 0.25;
			return floor( $v ) === $v ? (int) $v : $v;
		case 'select':
		case 'radio':
			if ( isset( $field['salt']['postSources'] ) ) {
				return array( salt_harness_collection_target( 'service' ) );
			}
			$keys = array_keys( (array) ( $field['choices'] ?? array() ) );
			return (string) ( $keys[ count( $keys ) > 1 ? 1 : 0 ] ?? '' );
		case 'true_false':
			return true;
		case 'image':
			return salt_harness_collection_image( $name );
		case 'gallery':
			return array( salt_harness_collection_image( $name . '-1' ), salt_harness_collection_image( $name . '-2' ) );
		case 'post_object':
			return salt_harness_collection_target( (string) ( $field['post_type'][0] ?? 'page' ) );
		case 'relationship':
			return array( salt_harness_collection_target( (string) ( $field['post_type'][0] ?? 'page' ) ) );
		case 'taxonomy':
			$term = term_exists( 'Harness topic', (string) $field['taxonomy'] );
			$term = $term ? $term : wp_insert_term( 'Harness topic', (string) $field['taxonomy'] );
			$id   = (int) $term['term_id'];
			return 'multi_select' === ( $field['field_type'] ?? '' ) || 'checkbox' === ( $field['field_type'] ?? '' ) ? array( $id ) : $id;
		case 'date_time_picker':
			return '2026-11-18 09:30:00';
		case 'time_picker':
			return '09:30';
		case 'group':
			return salt_harness_collection_values( $field['sub_fields'] );
		case 'repeater':
			$rows = max( 1, min( 2, (int) ( $field['max'] ?? 2 ) ) );
			return array_fill( 0, $rows, salt_harness_collection_values( $field['sub_fields'] ) );
		case 'flexible_content':
			return array_map(
				static fn( array $layout ): array => array( 'acf_fc_layout' => $layout['name'] ) + salt_harness_collection_values( $layout['sub_fields'] ),
				array_values( $field['layouts'] )
			);
		default:
			return null; // A type this phase does not seed; reported below.
	}
}

/**
 * Values for a list of fields, by name (a tab stores nothing and is left out).
 *
 * @param array<int,array<string,mixed>> $fields Fields.
 * @return array<string,mixed>
 */
function salt_harness_collection_values( array $fields ): array {
	$out = array();
	foreach ( $fields as $field ) {
		if ( 'tab' !== $field['type'] && '' !== (string) $field['name'] ) {
			$out[ (string) $field['name'] ] = salt_harness_collection_value( $field );
		}
	}
	return $out;
}

/**
 * Compare a saved value with what get_field() returned, and each meta path's
 * key reference, leaf by leaf.
 *
 * @param mixed                $want  Saved value.
 * @param mixed                $got   Read back, formatted.
 * @param array<string,mixed>  $field ACF field.
 * @param string               $path  Meta key of this value.
 * @param array<string,string> $meta  The post's meta.
 * @param array<string,mixed>  $stat  Counters, updated.
 * @param bool                 $row   Whether $path is a flexible row (no key of its own).
 * @return void
 */
function salt_harness_collection_compare( $want, $got, array $field, string $path, array $meta, array &$stat, bool $row = false ): void {
	if ( ! $row ) {
		++$stat['paths'];
		if ( ( $meta[ '_' . $path ] ?? null ) !== $field['key'] ) {
			$stat['misses'][] = sprintf( '_%s is %s, expected %s', $path, wp_json_encode( $meta[ '_' . $path ] ?? null ), $field['key'] );
		}
	}
	$by_name = static fn( array $fields ): array => array_column( $fields, null, 'name' );
	switch ( $field['type'] ) {
		case 'group':
			$subs = $by_name( $field['sub_fields'] );
			foreach ( (array) $want as $name => $v ) {
				salt_harness_collection_compare( $v, is_array( $got ) ? ( $got[ $name ] ?? null ) : null, $subs[ $name ], $path . '_' . $name, $meta, $stat );
			}
			return;
		case 'repeater':
		case 'flexible_content':
			if ( ! is_array( $got ) || count( $got ) !== count( $want ) ) {
				$stat['misses'][] = sprintf( '%s: %d rows saved, read back %s', $path, count( $want ), wp_json_encode( $got ) );
				return;
			}
			$layouts = array_column( $field['layouts'] ?? array(), null, 'name' );
			foreach ( array_values( $want ) as $i => $values ) {
				$subs = $by_name( 'repeater' === $field['type'] ? $field['sub_fields'] : $layouts[ $values['acf_fc_layout'] ]['sub_fields'] );
				foreach ( $values as $name => $v ) {
					if ( 'acf_fc_layout' === $name ) {
						if ( ( $got[ $i ]['acf_fc_layout'] ?? null ) !== $v ) {
							$stat['misses'][] = sprintf( '%s_%d: layout %s read back as %s', $path, $i, $v, wp_json_encode( $got[ $i ]['acf_fc_layout'] ?? null ) );
						}
						continue;
					}
					salt_harness_collection_compare( $v, $got[ $i ][ $name ] ?? null, $subs[ $name ], $path . '_' . $i . '_' . $name, $meta, $stat );
				}
			}
			return;
	}

	++$stat['leaves'];
	if ( null === $want ) {
		$stat['misses'][] = sprintf( '%s: the phase has no value for a %s field', $path, $field['type'] );
		return;
	}
	if ( is_array( $want ) ) {
		$ok = is_array( $got ) && array_map( 'intval', $want ) === array_map( 'intval', array_values( $got ) );
	} elseif ( 'wysiwyg' === $field['type'] ) {
		$ok = (string) apply_filters( 'acf_the_content', $want ) === $got;
	} elseif ( is_bool( $want ) ) {
		$ok = $want === $got;
	} else {
		$ok = is_scalar( $got ) && (string) $want === (string) $got;
	}
	if ( $ok ) {
		++$stat['ok'];
	} else {
		$stat['misses'][] = sprintf( '%s (%s): saved %s, read back %s', $path, $field['type'], wp_json_encode( $want ), wp_json_encode( $got ) );
	}
}

$generated = salt_generated_collection_groups();
salt_harness_result(
	7 === count( $generated ),
	'the collection groups are the generated ones',
	sprintf( '%d groups from inc/fields/generated/collections.json: %s', count( $generated ), implode( ', ', array_map( static fn( array $g ): string => $g['salt']['collection'] . ' on ' . salt_collection_group_post_type( $g ), $generated ) ) )
);

$totals = array(
	'paths'  => 0,
	'leaves' => 0,
	'ok'     => 0,
	'misses' => 0,
);
foreach ( $generated as $group ) {
	$collection = (string) $group['salt']['collection'];
	$post_type  = salt_collection_group_post_type( $group );
	$local      = acf_get_local_field_group( $group['key'] );
	if ( ! is_array( $local ) ) {
		salt_harness_result( false, "{$collection}: registered on real ACF", "{$group['key']} is not a local field group" );
		continue;
	}

	$is_team = 'team' === $collection;
	$post_id = $is_team ? 'user_' . wp_insert_user(
		array(
			'user_login'   => 'harness-person-fixture',
			'display_name' => 'Harness team',
			'user_pass'    => wp_generate_password(),
		)
	) : (int) wp_insert_post(
		array(
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'post_title'  => 'Harness ' . $collection,
		)
	);
	$fields  = acf_get_fields( $group['key'] );
	$values  = salt_harness_collection_values( $fields );
	foreach ( $fields as $field ) {
		if ( array_key_exists( (string) $field['name'], $values ) ) {
			update_field( $field['key'], $values[ $field['name'] ], $post_id );
		}
	}

	$meta = array_map( static fn( array $v ): string => (string) $v[0], $is_team ? get_user_meta( (int) substr( $post_id, 5 ) ) : get_post_meta( $post_id ) );
	$stat = array(
		'paths'  => 0,
		'leaves' => 0,
		'ok'     => 0,
		'misses' => array(),
	);
	foreach ( $fields as $field ) {
		if ( array_key_exists( (string) $field['name'], $values ) ) {
			salt_harness_collection_compare( $values[ $field['name'] ], get_field( $field['name'], $post_id ), $field, (string) $field['name'], $meta, $stat );
		}
	}
	salt_harness_result(
		array() === $stat['misses'],
		"{$collection}: seeded through update_field and read back",
		sprintf( 'post %d (%s): %d values read back, %d correct; %d meta paths carry their field key%s', $post_id, $post_type, $stat['leaves'], $stat['ok'], $stat['paths'], array() === $stat['misses'] ? '' : '; ' . implode( '; ', array_slice( $stat['misses'], 0, 6 ) ) )
	);
	$totals['paths']  += $stat['paths'];
	$totals['leaves'] += $stat['leaves'];
	$totals['ok']     += $stat['ok'];
	$totals['misses'] += count( $stat['misses'] );
	if ( $is_team ) {
		salt_harness_result( 'Harness team' === get_userdata( (int) substr( $post_id, 5 ) )->display_name, 'team name is native' );
		continue;
	}

	// The places WordPress keeps natively, set the WordPress way and read through the theme.
	$native = (array) ( $group['salt']['native'] ?? array() );
	$photo  = salt_harness_collection_image( 'native-' . $collection );
	$update = array( 'ID' => $post_id );
	if ( in_array( 'excerpt', $native, true ) ) {
		$update['post_excerpt'] = 'Harness summary for ' . $collection . '.';
	}
	if ( in_array( 'body', $native, true ) ) {
		$update['post_content'] = '<p>Harness introduction.</p>';
	}
	wp_update_post( $update );
	if ( in_array( 'featured-image', $native, true ) ) {
		set_post_thumbnail( $post_id, $photo );
	}
	$checks = array( 'title' => get_the_title( $post_id ) === 'Harness ' . $collection );
	if ( isset( $update['post_excerpt'] ) ) {
		$checks['excerpt (salt_post_summary)'] = salt_post_summary( $post_id ) === $update['post_excerpt'];
	}
	if ( isset( $update['post_content'] ) ) {
		$checks['body'] = str_contains( (string) get_post_field( 'post_content', $post_id ), 'Harness introduction.' );
	}
	if ( in_array( 'featured-image', $native, true ) ) {
		$checks['featured image'] = get_post_thumbnail_id( $post_id ) === $photo;
	}
	salt_harness_result(
		! in_array( false, $checks, true ),
		"{$collection}: native places read through the theme",
		sprintf( 'salt.native %s; %s', wp_json_encode( $native ), implode( ', ', array_map( static fn( string $k, bool $ok ): string => $k . ( $ok ? ' ok' : ' WRONG' ), array_keys( $checks ), $checks ) ) )
	);
}

// The post fields: author and layout are drawn in the post's field group; primaryTopic only in the
// Topics box (inc/cpt/post.php), so acf_prepare_field() gives false for it outside the box.
$post_fields                  = array();
$post_fields['native author'] = post_type_supports( 'post', 'author' ) && ! acf_get_field( 'field_salt_collection_posts_author' );
foreach ( array( 'field_salt_collection_posts_layout' ) as $key ) {
	$post_fields[ $key ] = is_array( acf_prepare_field( acf_get_field( $key ) ) );
}
$post_fields['primaryTopic not in the group'] = false === acf_prepare_field( acf_get_field( SALT_POST_PRIMARY_TOPIC_FIELD ) );
salt_post_drawing_topics_box( true );
$post_fields['primaryTopic in the Topics box'] = is_array( acf_prepare_field( acf_get_field( SALT_POST_PRIMARY_TOPIC_FIELD ) ) );
salt_post_drawing_topics_box( false );
$post_fields['no classic editor on posts'] = ! post_type_supports( 'post', 'editor' );
$post_fields['no Tags box on posts']       = ! in_array( 'post_tag', get_object_taxonomies( 'post' ), true );
$post_fields['categories labelled Topics'] = 'Topics' === get_taxonomy( 'category' )->labels->name;
salt_harness_result(
	! in_array( false, $post_fields, true ),
	'posts are stored as sections, with the credited author and the primary topic beside Topics',
	implode( ', ', array_map( static fn( string $k, bool $ok ): string => $k . ( $ok ? ' ok' : ' WRONG' ), array_keys( $post_fields ), $post_fields ) )
);

// Topics stay flat (_topic.json): a parent asked for on insert or update is not kept.
$flat = array();
foreach ( array_merge( salt_topic_taxonomies(), array( 'category' ) ) as $taxonomy ) {
	$parent       = wp_insert_term( 'Harness parent ' . $taxonomy, $taxonomy );
	$child        = wp_insert_term( 'Harness child ' . $taxonomy, $taxonomy, array( 'parent' => (int) $parent['term_id'] ) );
	$after_insert = (int) get_term( (int) $child['term_id'], $taxonomy )->parent;
	wp_update_term( (int) $child['term_id'], $taxonomy, array( 'parent' => (int) $parent['term_id'] ) );
	$after_update      = (int) get_term( (int) $child['term_id'], $taxonomy )->parent;
	$flat[ $taxonomy ] = 0 === $after_insert && 0 === $after_update;
}
salt_harness_result(
	! in_array( false, $flat, true ),
	'every topic is stored flat',
	implode( ', ', array_map( static fn( string $t, bool $ok ): string => $t . ( $ok ? ' parent 0 after insert and update' : ' KEPT A PARENT' ), array_keys( $flat ), $flat ) )
);

salt_harness_result(
	0 === $totals['misses'],
	'every collection field round-trips on real ACF',
	sprintf( '%d values across 7 post types read back, %d correct; %d meta paths carry their field key; %d wrong', $totals['leaves'], $totals['ok'], $totals['paths'], $totals['misses'] )
);

salt_harness_exit();
