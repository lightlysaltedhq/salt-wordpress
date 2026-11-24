<?php
/**
 * CPTs: client and sector, the name-only records (salt-contract SC-035).
 *
 * A record is a name and nothing else, which a case study may point at
 * (clientRecord, sectorRecord). Each is its own module, off by default
 * (salt_modules_off_by_default()), and has no public surface: no single,
 * archive, rewrite or REST route.
 *
 * Each switch only changes what the case-study editor offers and which
 * name the public client slot draws. It writes nothing: a case study
 * keeps its free text and its stored record ID through every switch and
 * save, and no name is ever matched to a record.
 *
 * - On: the record picker is offered; the free text stays visible until a
 *   record is chosen, and is kept, hidden, after.
 * - Off: the picker is not registered, so ACF leaves its stored ID alone,
 *   and the free text is the editor. When it has never been filled, it
 *   opens with the stored record's name, which a save then keeps.
 * - A saved record in the bin or deleted is unavailable: kept through
 *   ordinary saves, named below the picker and removed only on request.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module slug => the case study's free-text and record field names.
 *
 * @return array<string,array{text:string,record:string}>
 */
function salt_record_fields(): array {
	return array(
		'client' => array(
			'text'   => 'client',
			'record' => 'clientRecord',
		),
		'sector' => array(
			'text'   => 'sector',
			'record' => 'sectorRecord',
		),
	);
}

/**
 * Register the client and sector post types (each module-gated).
 *
 * @return void
 */
function salt_register_cpt_records(): void {
	$labels = array(
		'client' => array(
			'name'          => __( 'Clients', 'salt' ),
			'singular_name' => __( 'Client', 'salt' ),
			'add_new_item'  => __( 'Add new client', 'salt' ),
			'edit_item'     => __( 'Edit client', 'salt' ),
			'new_item'      => __( 'New client', 'salt' ),
			'search_items'  => __( 'Search clients', 'salt' ),
			'not_found'     => __( 'No clients found.', 'salt' ),
			'menu_name'     => __( 'Clients', 'salt' ),
		),
		'sector' => array(
			'name'          => __( 'Sectors', 'salt' ),
			'singular_name' => __( 'Sector', 'salt' ),
			'add_new_item'  => __( 'Add new sector', 'salt' ),
			'edit_item'     => __( 'Edit sector', 'salt' ),
			'new_item'      => __( 'New sector', 'salt' ),
			'search_items'  => __( 'Search sectors', 'salt' ),
			'not_found'     => __( 'No sectors found.', 'salt' ),
			'menu_name'     => __( 'Sectors', 'salt' ),
		),
	);

	foreach ( $labels as $type => $type_labels ) {
		if ( ! salt_module_enabled( $type ) ) {
			continue;
		}
		register_post_type(
			$type,
			array(
				'labels'              => $type_labels,
				// Admin UI only, no public surface (SC-035).
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => salt_module_enabled( 'work' ) ? 'edit.php?post_type=work' : true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
			)
		);
	}
}
add_action( 'init', 'salt_register_cpt_records' );

/**
 * The name of the record a case study points at, when it resolves.
 *
 * Resolves only to a published post of the module's own type: a deleted,
 * trashed, draft or foreign ID resolves to nothing, never to another
 * record.
 *
 * @param int    $work_id Case study ID.
 * @param string $module  'client' or 'sector'.
 * @return string The record's title, or '' when it does not resolve.
 */
function salt_case_study_record_name( int $work_id, string $module ): string {
	$id     = (int) get_post_meta( $work_id, salt_record_fields()[ $module ]['record'], true );
	$record = $id > 0 ? get_post( $id ) : null;

	return $record instanceof WP_Post && $module === $record->post_type && 'publish' === $record->post_status ? trim( $record->post_title ) : '';
}

/**
 * The client or sector name a case study draws, wherever it is drawn.
 *
 * The chosen record's current name when its module is on and the record
 * resolves; otherwise the saved free text.
 *
 * @param int    $work_id Case study ID.
 * @param string $module  'client' or 'sector'.
 * @return string
 */
function salt_case_study_name( int $work_id, string $module ): string {
	$name = salt_module_enabled( $module ) ? salt_case_study_record_name( $work_id, $module ) : '';
	$text = get_field( salt_record_fields()[ $module ]['text'], $work_id );

	return '' !== $name ? $name : ( is_string( $text ) ? trim( $text ) : '' );
}

/**
 * Leave a disabled module's record field out of a collection group.
 *
 * ACF neither draws nor saves a field it was not given, so the stored ID
 * stays as it was (the emitter's salt.leftOut, at run time).
 *
 * @param array $group A generated collection group.
 * @return array
 */
function salt_record_fields_in_group( array $group ): array {
	$off = array();
	foreach ( salt_record_fields() as $module => $names ) {
		if ( ! salt_module_enabled( $module ) ) {
			$off[] = $names['record'];
		}
	}
	$group['fields'] = array_values(
		array_filter(
			$group['fields'],
			static fn( array $field ): bool => ! in_array( $field['name'] ?? '', $off, true )
		)
	);

	return $group;
}

/**
 * Prepare a case study's free-text client or sector for the editor.
 *
 * On, the text shows only while no record is chosen; ACF neither submits
 * nor saves a field its conditions hide, so the text is kept. Off, text
 * that was never filled opens with the stored record's name.
 *
 * @param array|false $field The ACF field.
 * @return array|false
 */
function salt_prepare_record_text_field( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}
	foreach ( array_keys( salt_record_fields() ) as $module ) {
		// By key: ACF has made the name the input's name by now.
		if ( ( $field['key'] ?? '' ) !== 'field_salt_collection_case_studies_' . $module ) {
			continue;
		}
		if ( salt_module_enabled( $module ) ) {
			$field['conditional_logic'] = array(
				array(
					array(
						'field'    => 'field_salt_collection_case_studies_' . $module . '_record',
						'operator' => '==empty',
					),
				),
			);
		} elseif ( '' === trim( (string) $field['value'] ) ) {
			$post_id = (int) ( $GLOBALS['post']->ID ?? 0 );
			$name    = $post_id > 0 ? salt_case_study_record_name( $post_id, $module ) : '';
			if ( '' !== $name ) {
				$field['value'] = $name;
			}
		}
	}

	return $field;
}
add_filter( 'acf/prepare_field/key=field_salt_collection_case_studies_client', 'salt_prepare_record_text_field' );
add_filter( 'acf/prepare_field/key=field_salt_collection_case_studies_sector', 'salt_prepare_record_text_field' );

/**
 * The stored record ID a case study's picker cannot show.
 *
 * ACF's picker draws a stored post only when it exists, is of its type and
 * is not in the bin (or an auto-draft). Anything else is unavailable: kept,
 * but neither drawn nor matched to another record.
 *
 * @param int    $work_id Case study ID.
 * @param string $module  'client' or 'sector'.
 * @return int The unavailable ID, or 0.
 */
function salt_case_study_unavailable_record( int $work_id, string $module ): int {
	$id = (int) get_post_meta( $work_id, salt_record_fields()[ $module ]['record'], true );
	if ( $id <= 0 ) {
		return 0;
	}
	$record = get_post( $id );

	return $record instanceof WP_Post && $module === $record->post_type && ! in_array( $record->post_status, array( 'trash', 'auto-draft' ), true ) ? 0 : $id;
}

/**
 * The module a case study's record picker belongs to, from its key.
 *
 * @param array $field The ACF field.
 * @return string '' when it is not a record picker.
 */
function salt_record_picker_module( array $field ): string {
	foreach ( array_keys( salt_record_fields() ) as $module ) {
		if ( ( $field['key'] ?? '' ) === 'field_salt_collection_case_studies_' . $module . '_record' ) {
			return $module;
		}
	}
	return '';
}

/**
 * Below the picker, say that the saved record is unavailable, with an
 * explicit way to remove it. Nothing in the picker can select it.
 *
 * @param array $field The ACF field.
 * @return void
 */
function salt_render_unavailable_record( array $field ): void {
	// ACF draws the picker as a select under the same key; say it once, after the picker.
	$module  = 'post_object' === ( $field['type'] ?? '' ) ? salt_record_picker_module( $field ) : '';
	$post_id = (int) ( $GLOBALS['post']->ID ?? 0 );
	$id      = '' !== $module && $post_id > 0 ? salt_case_study_unavailable_record( $post_id, $module ) : 0;
	if ( 0 === $id ) {
		return;
	}
	$record = get_post( $id );
	$status = $record instanceof WP_Post && 'trash' === $record->post_status
		/* translators: %s: the name of the record in the bin. */
		? sprintf( __( 'The saved choice, “%s”, is in the bin. It stays saved, and the site shows the name as text, until you choose another or remove it.', 'salt' ), $record->post_title )
		/* translators: %d: the ID of the record that no longer exists. */
		: sprintf( __( 'The saved choice (record %d) no longer exists. It stays saved, and the site shows the name as text, until you choose another or remove it.', 'salt' ), $id );

	printf(
		'<p class="description salt-record-unavailable" role="status">%1$s</p><label><input type="checkbox" name="salt_remove_record[%2$s]" value="1"> %3$s</label>',
		esc_html( $status ),
		esc_attr( $module ),
		esc_html__( 'Remove the saved choice', 'salt' )
	);
}
add_action( 'acf/render_field/key=field_salt_collection_case_studies_client_record', 'salt_render_unavailable_record' );
add_action( 'acf/render_field/key=field_salt_collection_case_studies_sector_record', 'salt_render_unavailable_record' );

/**
 * Keep an unavailable record through an ordinary save.
 *
 * The picker submits nothing for a record it cannot draw, so an empty value
 * keeps the stored ID unless the editor ticked its removal. Choosing another
 * record replaces it, and clearing an available one clears it, as ever.
 *
 * @param mixed      $value   The submitted value.
 * @param int|string $post_id The post being saved.
 * @param array      $field   The ACF field.
 * @return mixed
 */
function salt_keep_unavailable_record( $value, $post_id, $field ) {
	$module = is_array( $field ) ? salt_record_picker_module( $field ) : '';
	if ( ! empty( $value ) || '' === $module || ! is_numeric( $post_id ) ) {
		return $value;
	}
	$kept = salt_case_study_unavailable_record( (int) $post_id, $module );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read during ACF's save, which has verified its own nonce.
	$remove = isset( $_POST['salt_remove_record'][ $module ] );

	return $kept > 0 && ! $remove ? (string) $kept : $value;
}
add_filter( 'acf/update_value/key=field_salt_collection_case_studies_client_record', 'salt_keep_unavailable_record', 10, 3 );
add_filter( 'acf/update_value/key=field_salt_collection_case_studies_sector_record', 'salt_keep_unavailable_record', 10, 3 );
