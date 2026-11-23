<?php
/**
 * Optional enquiry storage: store / purge / export + the locked-down
 * salt_submission CPT posture (spec §9; SEC09/PRIV03/PRIV06).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/service.php';
require_once __DIR__ . '/../shims/submissions.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/submission.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/submissions.php';

final class SubmissionsTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_cpts']        = array();
		$GLOBALS['salt_test_meta']        = array();
		$GLOBALS['salt_test_cron']        = array();
		$GLOBALS['salt_test_insert_fail'] = false;
	}

	/**
	 * Turn the storage option on/off.
	 */
	private function set_storage( bool $on, ?int $retention_days = null ): void {
		$GLOBALS['salt_test_fields']['option']['submissions_store'] = $on ? 1 : 0;
		if ( null !== $retention_days ) {
			$GLOBALS['salt_test_fields']['option']['submissions_retention_days'] = $retention_days;
		}
	}

	/**
	 * A complete enquiry fixture.
	 *
	 * @return array<string,mixed>
	 */
	private function enquiry( int $submitted_at ): array {
		return array(
			'name'         => 'Jane Doe',
			'email'        => 'jane@example.test',
			'message'      => 'Hello — enquiry body.',
			'enquiry_type' => 'booking',
			'page_url'     => 'https://example.test/contact/',
			'ip_hash'      => 'abc123hash',
			'submitted_at' => $submitted_at,
		);
	}

	// ------------------------------------------------------------------
	// salt_submission_store().
	// ------------------------------------------------------------------

	public function test_store_off_returns_null_and_inserts_nothing(): void {
		$this->set_storage( false );

		$this->assertNull( salt_submission_store( $this->enquiry( time() ) ) );
		$this->assertSame( array(), $GLOBALS['salt_test_posts'] );
		$this->assertSame( array(), $GLOBALS['salt_test_meta'] );
	}

	public function test_store_defaults_off_when_option_unset(): void {
		// No fixture at all — data minimisation default (PRIV03).
		$this->assertNull( salt_submission_store( $this->enquiry( time() ) ) );
		$this->assertSame( array(), $GLOBALS['salt_test_posts'] );
	}

	public function test_store_on_creates_a_private_post_with_all_meta(): void {
		$this->set_storage( true );
		$submitted_at = 1767225600; // 2026-01-01 00:00 UTC.

		$post_id = salt_submission_store( $this->enquiry( $submitted_at ) );

		$this->assertIsInt( $post_id );
		$post = get_post( $post_id );
		$this->assertNotNull( $post );
		$this->assertSame( 'salt_submission', $post->post_type );
		$this->assertSame( 'private', $post->post_status );
		$this->assertSame( 'Jane Doe — booking — 2026-01-01 00:00', $post->post_title );

		$this->assertSame( 'Jane Doe', get_post_meta( $post_id, 'salt_name', true ) );
		$this->assertSame( 'jane@example.test', get_post_meta( $post_id, 'salt_email', true ) );
		$this->assertSame( 'Hello — enquiry body.', get_post_meta( $post_id, 'salt_message', true ) );
		$this->assertSame( 'booking', get_post_meta( $post_id, 'salt_enquiry_type', true ) );
		$this->assertSame( 'https://example.test/contact/', get_post_meta( $post_id, 'salt_page_url', true ) );
		$this->assertSame( 'abc123hash', get_post_meta( $post_id, 'salt_ip_hash', true ) );
		$this->assertSame( $submitted_at, get_post_meta( $post_id, 'salt_submitted_at', true ) );
	}

	public function test_store_failure_logs_and_returns_null(): void {
		$this->set_storage( true );
		$GLOBALS['salt_test_insert_fail'] = true;

		$this->assertNull( salt_submission_store( $this->enquiry( time() ) ) );
		$this->assertNotSame( array(), $this->logs() );
	}

	// ------------------------------------------------------------------
	// salt_submissions_purge() (PRIV03).
	// ------------------------------------------------------------------

	public function test_purge_deletes_only_past_retention_records_and_stamps_last_purge(): void {
		$this->set_storage( true, 30 );

		$old_id    = salt_submission_store( $this->enquiry( time() - ( 31 * DAY_IN_SECONDS ) ) );
		$older_id  = salt_submission_store( $this->enquiry( time() - ( 400 * DAY_IN_SECONDS ) ) );
		$recent_id = salt_submission_store( $this->enquiry( time() - ( 5 * DAY_IN_SECONDS ) ) );

		$deleted = salt_submissions_purge();

		$this->assertSame( 2, $deleted );
		$this->assertNull( get_post( $old_id ) );
		$this->assertNull( get_post( $older_id ) );
		$this->assertNotNull( get_post( $recent_id ) );
		$this->assertEqualsWithDelta( time(), (int) get_option( 'salt_submissions_last_purge' ), 5 );
	}

	/**
	 * A row as main stored it before the date fix: salt_submitted_at 2026
	 * (an ISO date cast to int), the post stored the given days ago.
	 *
	 * @param int $days_ago When the post was stored.
	 * @return int Post ID.
	 */
	private function store_misdated_row( int $days_ago ): int {
		$id = wp_insert_post(
			array(
				'post_type'     => 'salt_submission',
				'post_status'   => 'private',
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - ( $days_ago * DAY_IN_SECONDS ) ),
			)
		);
		update_post_meta( $id, 'salt_submitted_at', 2026 );
		return $id;
	}

	public function test_an_iso_date_is_stored_as_its_timestamp(): void {
		$this->set_storage( true );

		$post_id = salt_submission_store( array( 'submitted_at' => '2026-11-23T10:15:00+00:00' ) + $this->enquiry( 0 ) );

		$this->assertSame( 1795428900, $GLOBALS['salt_test_meta'][ $post_id ]['salt_submitted_at'] );
		$this->assertStringEndsWith( '2026-11-23 10:15', get_post( $post_id )->post_title );
	}

	public function test_a_misdated_row_inside_the_window_survives_the_purge(): void {
		$this->set_storage( true, 30 );
		$id = $this->store_misdated_row( 5 );

		$this->assertSame( 0, salt_submissions_purge() );
		$this->assertNotNull( get_post( $id ) );
		$this->assertSame( time() - 5 * DAY_IN_SECONDS, salt_submission_export( $id )['submitted_at'], 'Exported by the trustworthy date.' );
	}

	public function test_a_misdated_row_past_the_window_by_its_post_date_is_purged(): void {
		$this->set_storage( true, 30 );
		$id = $this->store_misdated_row( 100 );

		$this->assertSame( 1, salt_submissions_purge() );
		$this->assertNull( get_post( $id ) );
	}

	public function test_a_row_with_no_trustworthy_date_is_never_purged(): void {
		$this->set_storage( true, 30 );
		$id = wp_insert_post(
			array(
				'post_type'     => 'salt_submission',
				'post_status'   => 'private',
				'post_date_gmt' => '0000-00-00 00:00:00',
			)
		);
		update_post_meta( $id, 'salt_submitted_at', 2026 );

		$this->assertSame( 0, salt_submissions_purge() );
		$this->assertNotNull( get_post( $id ) );
		$this->assertNull( salt_submission_export( $id )['submitted_at'] );
	}

	public function test_purge_ignores_foreign_post_types(): void {
		$this->set_storage( true, 30 );
		$this->seed_posts(
			array(
				array(
					'ID'        => 500,
					'post_type' => 'page',
				),
			)
		);

		$this->assertSame( 0, salt_submissions_purge() );
		$this->assertNotNull( get_post( 500 ) );
	}

	public function test_purge_respects_the_default_90_day_retention(): void {
		$this->set_storage( true ); // No retention fixture → 90.

		$kept_id    = salt_submission_store( $this->enquiry( time() - ( 89 * DAY_IN_SECONDS ) ) );
		$deleted_id = salt_submission_store( $this->enquiry( time() - ( 91 * DAY_IN_SECONDS ) ) );

		$this->assertSame( 1, salt_submissions_purge() );
		$this->assertNotNull( get_post( $kept_id ) );
		$this->assertNull( get_post( $deleted_id ) );
	}

	// ------------------------------------------------------------------
	// Scheduling + catch-up (PRIV03 layers 1 & 2).
	// ------------------------------------------------------------------

	public function test_schedule_registers_daily_cron_when_storage_on(): void {
		$this->set_storage( true );

		salt_submissions_schedule_purge();

		$this->assertArrayHasKey( 'salt_submissions_purge', $GLOBALS['salt_test_cron'] );
		$this->assertSame( 'daily', $GLOBALS['salt_test_cron']['salt_submissions_purge']['recurrence'] );
	}

	public function test_schedule_is_a_noop_when_storage_off_or_already_scheduled(): void {
		$this->set_storage( false );
		salt_submissions_schedule_purge();
		$this->assertSame( array(), $GLOBALS['salt_test_cron'] );

		$this->set_storage( true );
		$GLOBALS['salt_test_cron']['salt_submissions_purge'] = array(
			'timestamp'  => 12345,
			'recurrence' => 'daily',
		);
		salt_submissions_schedule_purge();
		$this->assertSame( 12345, $GLOBALS['salt_test_cron']['salt_submissions_purge']['timestamp'] );
	}

	#[DataProvider( 'purge_due_provider' )]
	public function test_purge_due_condition( int $last_purge, int $now, bool $expected ): void {
		$this->assertSame( $expected, salt_submissions_purge_due( $last_purge, $now ) );
	}

	/**
	 * @return array<string,array{int,int,bool}>
	 */
	public static function purge_due_provider(): array {
		$now = 1767225600;
		return array(
			'never purged'   => array( 0, $now, true ),
			'just purged'    => array( $now - 60, $now, false ),
			'23h ago'        => array( $now - ( 23 * 3600 ), $now, false ),
			'exactly 24h'    => array( $now - DAY_IN_SECONDS, $now, false ),
			'25h ago (due)'  => array( $now - ( 25 * 3600 ), $now, true ),
			'days ago (due)' => array( $now - ( 3 * DAY_IN_SECONDS ), $now, true ),
		);
	}

	public function test_catch_up_purges_when_last_purge_is_stale(): void {
		$this->set_storage( true, 30 );
		$stale_id = salt_submission_store( $this->enquiry( time() - ( 60 * DAY_IN_SECONDS ) ) );
		update_option( 'salt_submissions_last_purge', time() - ( 3 * DAY_IN_SECONDS ) );

		salt_submissions_purge_catch_up();

		$this->assertNull( get_post( $stale_id ) );
		$this->assertEqualsWithDelta( time(), (int) get_option( 'salt_submissions_last_purge' ), 5 );
	}

	public function test_catch_up_is_a_noop_when_fresh_or_storage_off(): void {
		$this->set_storage( true, 30 );
		$stale_id = salt_submission_store( $this->enquiry( time() - ( 60 * DAY_IN_SECONDS ) ) );

		// Fresh last purge → no sweep.
		update_option( 'salt_submissions_last_purge', time() - 3600 );
		salt_submissions_purge_catch_up();
		$this->assertNotNull( get_post( $stale_id ) );

		// Storage off → no sweep even when stale.
		$this->set_storage( false );
		update_option( 'salt_submissions_last_purge', 0 );
		salt_submissions_purge_catch_up();
		$this->assertNotNull( get_post( $stale_id ) );
	}

	// ------------------------------------------------------------------
	// Health condition (PRIV03 layer 3).
	// ------------------------------------------------------------------

	public function test_health_ok_when_storage_off(): void {
		$this->set_storage( false );
		update_option( 'salt_submissions_last_purge', 0 );

		$this->assertTrue( salt_submissions_health_ok() );
	}

	public function test_health_ok_when_purge_ran_within_the_retention_window(): void {
		$this->set_storage( true, 30 );
		update_option( 'salt_submissions_last_purge', time() - ( 2 * DAY_IN_SECONDS ) );

		$this->assertTrue( salt_submissions_health_ok() );
	}

	public function test_health_not_ok_when_purge_never_ran(): void {
		$this->set_storage( true, 30 );

		$this->assertFalse( salt_submissions_health_ok() );
	}

	public function test_health_not_ok_when_purge_older_than_the_retention_window(): void {
		$this->set_storage( true, 30 );
		update_option( 'salt_submissions_last_purge', time() - ( 31 * DAY_IN_SECONDS ) );

		$this->assertFalse( salt_submissions_health_ok() );
	}

	// ------------------------------------------------------------------
	// salt_submission_export() (PRIV06).
	// ------------------------------------------------------------------

	public function test_export_returns_the_exact_stored_shape(): void {
		$this->set_storage( true );
		$submitted_at = 1767225600;
		$post_id      = salt_submission_store( $this->enquiry( $submitted_at ) );
		$post         = get_post( $post_id );

		$this->assertSame(
			array(
				'id'           => $post_id,
				'title'        => 'Jane Doe — booking — 2026-01-01 00:00',
				'created'      => $post->post_date,
				'name'         => 'Jane Doe',
				'email'        => 'jane@example.test',
				'phone'        => '',
				'message'      => 'Hello — enquiry body.',
				'enquiry_type' => 'booking',
				'page_url'     => 'https://example.test/contact/',
				'ip_hash'      => 'abc123hash',
				'submitted_at' => $submitted_at,
			),
			salt_submission_export( $post_id )
		);
	}

	public function test_a_phone_is_stored_exported_and_purged_only_when_given(): void {
		$this->set_storage( true, 30 );

		$without = salt_submission_store( $this->enquiry( time() ) );
		$this->assertArrayNotHasKey( 'salt_phone', $GLOBALS['salt_test_meta'][ $without ], 'No phone given: none stored.' );

		$with = salt_submission_store( array( 'phone' => '+44 117 496 0123' ) + $this->enquiry( time() - ( 31 * DAY_IN_SECONDS ) ) );
		$this->assertSame( '+44 117 496 0123', salt_submission_export( $with )['phone'], 'In the per-record export (PRIV06).' );

		salt_submissions_purge();
		$this->assertArrayNotHasKey( $with, $GLOBALS['salt_test_meta'], 'Purged with its record (PRIV03).' );
	}

	public function test_export_returns_null_for_foreign_posts_and_unknown_ids(): void {
		$this->seed_posts(
			array(
				array(
					'ID'        => 42,
					'post_type' => 'page',
				),
			)
		);

		$this->assertNull( salt_submission_export( 42 ) );
		$this->assertNull( salt_submission_export( 999 ) );
	}

	// ------------------------------------------------------------------
	// CPT posture (SEC09).
	// ------------------------------------------------------------------

	public function test_cpt_registers_with_the_locked_down_posture(): void {
		salt_register_cpt_submission();

		$this->assertArrayHasKey( 'salt_submission', $GLOBALS['salt_test_cpts'] );
		$args = $GLOBALS['salt_test_cpts']['salt_submission'];

		// SEC09: zero public surface.
		$this->assertFalse( $args['public'] );
		$this->assertFalse( $args['publicly_queryable'] );
		$this->assertTrue( $args['exclude_from_search'] );
		$this->assertFalse( $args['show_in_rest'] );
		$this->assertFalse( $args['has_archive'] );
		$this->assertFalse( $args['rewrite'] );

		// Admin list table only, title support only.
		$this->assertTrue( $args['show_ui'] );
		$this->assertTrue( $args['show_in_menu'] );
		$this->assertSame( array( 'title' ), $args['supports'] );

		// Administrators only (documented capability choice).
		$this->assertSame( 'salt_submission', $args['capability_type'] );
		$this->assertTrue( $args['map_meta_cap'] );
		$this->assertSame( array( 'manage_options' ), array_unique( array_values( $args['capabilities'] ) ) );

		// CRITICAL regression guard: the META capabilities must NOT be mapped
		// here. WordPress' _post_type_meta_capabilities() would register the
		// target primitive (manage_options) as a meta-cap alias in the global
		// $post_type_meta_caps, so every bare current_user_can('manage_options')
		// gets rewritten to a post-scoped meta cap and denied for everyone —
		// admins included (hides Settings, plugin options, Site Settings).
		// map_meta_cap => true derives the meta caps from the primitives above.
		foreach ( array( 'edit_post', 'read_post', 'delete_post' ) as $meta_cap ) {
			$this->assertArrayNotHasKey(
				$meta_cap,
				$args['capabilities'],
				sprintf( 'Meta cap "%s" must not be remapped — it poisons the target primitive globally.', $meta_cap )
			);
		}
	}
}
