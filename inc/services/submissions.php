<?php
/**
 * Optional enquiry storage: store / purge / export (spec §9;
 * SEC09/PRIV03/PRIV06).
 *
 * Storage is OFF by default ('submissions_store' option — data minimisation:
 * email/webhook delivery is the primary channel). When on, enquiries are
 * stored as private 'salt_submission' posts (inc/cpt/submission.php,
 * admin-only) and purged after 'submissions_retention_days' days.
 *
 * Purge reliability (PRIV03), three layers:
 *   1. Daily cron event 'salt_submissions_purge' (scheduled on init while
 *      storage is on — activation-independent).
 *   2. Catch-up sweep on admin_init when the last purge is older than 24h
 *      (covers hosts with broken/disabled WP-Cron).
 *   3. A loud admin notice when storage is on and the last purge is older
 *      than the retention window itself — silent retention drift is a
 *      privacy defect, so it fails loud.
 *
 * salt_submission_export() is the DSAR aid (PRIV06): the full stored record
 * as a plain array, downloadable as JSON from the admin list table
 * (admin-only, nonce-checked).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether enquiry storage is enabled ('submissions_store' option).
 *
 * Fail-closed: unset/absent ACF means NO storage.
 *
 * @return bool
 */
function salt_submissions_enabled(): bool {
	if ( ! function_exists( 'get_field' ) ) {
		return false;
	}

	return true === (bool) salt_option( 'submissions_store' );
}

/**
 * The retention window in days ('submissions_retention_days' option).
 *
 * Clamped to the field's 1–730 bounds; defaults to 90.
 *
 * @return int
 */
function salt_submissions_retention_days(): int {
	$days = function_exists( 'get_field' ) ? salt_option( 'submissions_retention_days' ) : null;

	if ( null === $days || '' === $days ) {
		return 90;
	}

	return max( 1, min( 730, (int) $days ) );
}

/**
 * Store an enquiry as a private salt_submission post.
 *
 * Returns null (nothing stored) when storage is off — the caller's delivery
 * path (email/webhook) is unaffected either way.
 *
 * @param array<string,mixed> $submission Keys: name, email, message,
 *                                        enquiry_type, page_url, ip_hash,
 *                                        submitted_at (Unix timestamp).
 * @return int|null Post ID, or null when storage is off or insertion failed.
 */
function salt_submission_store( array $submission ): ?int {
	if ( ! salt_submissions_enabled() ) {
		return null;
	}

	$submitted_at = isset( $submission['submitted_at'] ) ? (int) $submission['submitted_at'] : time();

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'salt_submission',
			'post_status' => 'private',
			'post_title'  => sprintf(
				'%s — %s — %s',
				(string) ( $submission['name'] ?? '' ),
				(string) ( $submission['enquiry_type'] ?? '' ),
				gmdate( 'Y-m-d H:i', $submitted_at )
			),
		),
		true
	);

	if ( ! is_int( $post_id ) || $post_id <= 0 ) {
		salt_log( 'salt_submission_store: wp_insert_post failed — enquiry NOT stored (delivery path unaffected).' );
		return null;
	}

	update_post_meta( $post_id, 'salt_name', (string) ( $submission['name'] ?? '' ) );
	update_post_meta( $post_id, 'salt_email', (string) ( $submission['email'] ?? '' ) );
	update_post_meta( $post_id, 'salt_message', (string) ( $submission['message'] ?? '' ) );
	update_post_meta( $post_id, 'salt_enquiry_type', (string) ( $submission['enquiry_type'] ?? '' ) );
	update_post_meta( $post_id, 'salt_page_url', (string) ( $submission['page_url'] ?? '' ) );
	update_post_meta( $post_id, 'salt_ip_hash', (string) ( $submission['ip_hash'] ?? '' ) );
	update_post_meta( $post_id, 'salt_submitted_at', $submitted_at );

	return $post_id;
}

/**
 * Delete (force) submissions older than the retention window (PRIV03).
 *
 * Runs regardless of the storage toggle so records left behind after
 * storage is switched off still age out via cron. Stamps
 * 'salt_submissions_last_purge' for the catch-up sweep and health notice.
 *
 * @return int Number of submissions deleted.
 */
function salt_submissions_purge(): int {
	$cutoff  = time() - ( salt_submissions_retention_days() * DAY_IN_SECONDS );
	$deleted = 0;

	$posts = get_posts(
		array(
			'post_type'   => 'salt_submission',
			'post_status' => 'any',
			'numberposts' => -1,
		)
	);

	foreach ( $posts as $post ) {
		$submitted_at = (int) get_post_meta( $post->ID, 'salt_submitted_at', true );
		if ( $submitted_at < $cutoff ) {
			wp_delete_post( $post->ID, true );
			++$deleted;
		}
	}

	update_option( 'salt_submissions_last_purge', time() );

	return $deleted;
}
add_action( 'salt_submissions_purge', 'salt_submissions_purge' );

/**
 * Schedule the daily purge (activation-independent, PRIV03 layer 1).
 *
 * @return void
 */
function salt_submissions_schedule_purge(): void {
	if ( ! salt_submissions_enabled() ) {
		return;
	}

	if ( false === wp_next_scheduled( 'salt_submissions_purge' ) ) {
		wp_schedule_event( time(), 'daily', 'salt_submissions_purge' );
	}
}
add_action( 'init', 'salt_submissions_schedule_purge', 20 );

/**
 * Whether the catch-up sweep is due (pure condition — unit-tested).
 *
 * @param int $last_purge Unix timestamp of the last purge (0 = never).
 * @param int $now        Current Unix timestamp.
 * @return bool
 */
function salt_submissions_purge_due( int $last_purge, int $now ): bool {
	return ( $now - $last_purge ) > DAY_IN_SECONDS;
}

/**
 * Catch-up sweep (PRIV03 layer 2): purge on admin_init when cron has not
 * run for over 24h and storage is on.
 *
 * @return void
 */
function salt_submissions_purge_catch_up(): void {
	if ( ! salt_submissions_enabled() ) {
		return;
	}

	if ( salt_submissions_purge_due( (int) get_option( 'salt_submissions_last_purge', 0 ), time() ) ) {
		salt_submissions_purge();
	}
}
add_action( 'admin_init', 'salt_submissions_purge_catch_up' );

/**
 * Purge health condition (pure — unit-tested, PRIV03 layer 3).
 *
 * OK when storage is off, or when the last purge ran within the retention
 * window. A never-run purge (0) with storage on is NOT ok — retention
 * drift fails loud.
 *
 * @return bool
 */
function salt_submissions_health_ok(): bool {
	if ( ! salt_submissions_enabled() ) {
		return true;
	}

	$last_purge = (int) get_option( 'salt_submissions_last_purge', 0 );

	return ( time() - $last_purge ) <= ( salt_submissions_retention_days() * DAY_IN_SECONDS );
}

/**
 * Loud admin notice when the purge has not run within the retention window
 * (same pattern as inc/performance.php notices — admin_notices only fires
 * inside wp-admin; informational, no action button).
 *
 * @return void
 */
function salt_submissions_health_notice(): void {
	if ( salt_submissions_health_ok() ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'Enquiry storage is on but the retention purge has not run within the retention window — stored enquiries may be older than your retention policy allows. Check that WP-Cron is running.', 'salt' )
	);
}
add_action( 'admin_notices', 'salt_submissions_health_notice' );

/**
 * The full stored record as a plain array — the DSAR aid (PRIV06).
 *
 * @param int $post_id Submission post ID.
 * @return array<string,mixed>|null Record, or null when not a submission.
 */
function salt_submission_export( int $post_id ): ?array {
	$post = get_post( $post_id );

	if ( null === $post || 'salt_submission' !== $post->post_type ) {
		return null;
	}

	return array(
		'id'           => (int) $post->ID,
		'title'        => (string) $post->post_title,
		'created'      => (string) $post->post_date,
		'name'         => (string) get_post_meta( $post_id, 'salt_name', true ),
		'email'        => (string) get_post_meta( $post_id, 'salt_email', true ),
		'message'      => (string) get_post_meta( $post_id, 'salt_message', true ),
		'enquiry_type' => (string) get_post_meta( $post_id, 'salt_enquiry_type', true ),
		'page_url'     => (string) get_post_meta( $post_id, 'salt_page_url', true ),
		'ip_hash'      => (string) get_post_meta( $post_id, 'salt_ip_hash', true ),
		'submitted_at' => (int) get_post_meta( $post_id, 'salt_submitted_at', true ),
	);
}

/**
 * Add the 'salt_export' row action to the submissions list table.
 *
 * @param array<string,string> $actions Row actions.
 * @param WP_Post              $post    Row post.
 * @return array<string,string>
 */
function salt_submission_row_actions( array $actions, $post ): array {
	if ( ! $post instanceof WP_Post || 'salt_submission' !== $post->post_type || ! current_user_can( 'manage_options' ) ) {
		return $actions;
	}

	$url = wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'salt_export_submission',
				'post'   => (int) $post->ID,
			),
			admin_url( 'admin-post.php' )
		),
		'salt_export_submission_' . (int) $post->ID
	);

	$actions['salt_export'] = sprintf(
		'<a href="%s">%s</a>',
		esc_url( $url ),
		esc_html__( 'Export JSON', 'salt' )
	);

	return $actions;
}
add_filter( 'post_row_actions', 'salt_submission_row_actions', 10, 2 );

/**
 * Stream one submission as a JSON download (admin-only, nonce-checked).
 *
 * Fails CLOSED: capability first, then nonce, then record existence — any
 * miss dies without leaking whether the record exists.
 *
 * @return void
 */
function salt_submission_export_download(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to export submissions.', 'salt' ), '', 403 );
	}

	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

	check_admin_referer( 'salt_export_submission_' . $post_id );

	$record = salt_submission_export( $post_id );
	if ( null === $record ) {
		wp_die( esc_html__( 'Submission not found.', 'salt' ), '', 404 );
	}

	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="submission-' . $post_id . '.json"' );
	header( 'X-Content-Type-Options: nosniff' );

	echo wp_json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download (application/json + nosniff), not an HTML context; HTML-escaping would corrupt the export.
	exit;
}
add_action( 'admin_post_salt_export_submission', 'salt_submission_export_download' );
