<?php
/**
 * salt_location_status() — timezone/DST-aware open-status resolution.
 *
 * Table-driven fixtures (TEST03) cover boundary semantics (at-open inclusive,
 * at-close exclusive), overnight spans, split hours, appointment-only days,
 * the 'unknown' empty state (COMPL02), real DST handling in the venue
 * timezone, and the wp_timezone() default path.
 *
 * Fixture week: 2026-06-01 (Mon) … 2026-06-07 (Sun); DST fixture date
 * 2026-03-29 (Sun) is the day BST starts in Europe/London (01:00 GMT → 02:00 BST).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

final class LocationStatusTest extends SaltTestCase {

	/**
	 * Build a stored opening-hours row.
	 *
	 * @param string $day         Lowercase English day key.
	 * @param string $open        'HH:MM' open time.
	 * @param string $close       'HH:MM' close time.
	 * @param bool   $appointment Appointment-only flag.
	 * @return array<string,mixed>
	 */
	private static function row( string $day, string $open, string $close, bool $appointment = false ): array {
		return array(
			'day'         => $day,
			'open'        => $open,
			'close'       => $close,
			'appointment' => $appointment,
		);
	}

	/**
	 * Construct a DateTimeImmutable in a named timezone.
	 *
	 * @param string $datetime Datetime string.
	 * @param string $tz       Named timezone identifier.
	 * @return DateTimeImmutable
	 */
	private static function at( string $datetime, string $tz ): DateTimeImmutable {
		return new DateTimeImmutable( $datetime, new DateTimeZone( $tz ) );
	}

	/**
	 * Table of (hours, venue-local now, expected status) fixtures.
	 *
	 * @return array<string,array{0:array<int,array<string,mixed>>,1:string,2:string}>
	 */
	public static function status_provider(): array {
		$standard  = array( self::row( 'monday', '09:00', '17:00' ) );
		$overnight = array( self::row( 'friday', '20:00', '02:00' ) );
		$split     = array(
			self::row( 'wednesday', '09:00', '12:00' ),
			self::row( 'wednesday', '14:00', '17:00' ),
		);
		$appt      = array( self::row( 'tuesday', '09:00', '17:00', true ) );

		return array(
			'before open'                     => array( $standard, '2026-06-01 08:59:00', 'closed' ),
			'at open (inclusive)'             => array( $standard, '2026-06-01 09:00:00', 'open' ),
			'mid-day open'                    => array( $standard, '2026-06-01 12:30:00', 'open' ),
			'at close (exclusive)'            => array( $standard, '2026-06-01 17:00:00', 'closed' ),
			'after close'                     => array( $standard, '2026-06-01 18:00:00', 'closed' ),
			'overnight: friday evening'       => array( $overnight, '2026-06-05 23:00:00', 'open' ),
			'overnight: saturday small hours' => array( $overnight, '2026-06-06 01:59:00', 'open' ),
			'overnight: at close (exclusive)' => array( $overnight, '2026-06-06 02:00:00', 'closed' ),
			'appointment-only day'            => array( $appt, '2026-06-02 12:00:00', 'appointment' ),
			'day absent from table'           => array( $standard, '2026-06-04 12:00:00', 'closed' ),
			'split hours: lunch gap'          => array( $split, '2026-06-03 13:00:00', 'closed' ),
			'split hours: afternoon block'    => array( $split, '2026-06-03 15:00:00', 'open' ),
		);
	}

	/**
	 * Run one fixture row from the status table.
	 *
	 * @param array<int,array<string,mixed>> $hours    Opening-hours rows.
	 * @param string                         $when     Venue-local datetime string.
	 * @param string                         $expected Expected status key.
	 */
	#[DataProvider( 'status_provider' )]
	public function test_status_table( array $hours, string $when, string $expected ): void {
		$result = salt_location_status( $hours, self::at( $when, 'Europe/London' ), new DateTimeZone( 'Europe/London' ) );

		$this->assertSame( $expected, $result['status'] );
	}

	public function test_empty_hours_are_unknown_with_empty_label(): void {
		$result = salt_location_status( array(), self::at( '2026-06-01 12:00:00', 'Europe/London' ), new DateTimeZone( 'Europe/London' ) );

		$this->assertSame( 'unknown', $result['status'] );
		$this->assertSame( '', $result['label'] );
	}

	public function test_labels_are_translated_strings(): void {
		$hours = array( self::row( 'monday', '09:00', '17:00' ) );
		$tz    = new DateTimeZone( 'Europe/London' );

		$this->assertSame( 'Open now', salt_location_status( $hours, self::at( '2026-06-01 12:00:00', 'Europe/London' ), $tz )['label'] );
		$this->assertSame( 'Closed', salt_location_status( $hours, self::at( '2026-06-01 18:00:00', 'Europe/London' ), $tz )['label'] );
		$this->assertSame(
			'By appointment',
			salt_location_status( array( self::row( 'tuesday', '09:00', '17:00', true ) ), self::at( '2026-06-02 12:00:00', 'Europe/London' ), $tz )['label']
		);
	}

	public function test_dst_transition_uses_real_offsets_not_fixed_ones(): void {
		// BST starts 2026-03-29 (Sunday): 01:00 GMT → 02:00 BST in Europe/London.
		$hours = array( self::row( 'sunday', '09:00', '17:00' ) );
		$tz    = new DateTimeZone( 'Europe/London' );

		$this->assertSame( 'closed', salt_location_status( $hours, new DateTimeImmutable( '2026-03-29T08:30:00+01:00' ), $tz )['status'] );
		$this->assertSame( 'open', salt_location_status( $hours, new DateTimeImmutable( '2026-03-29T09:30:00+01:00' ), $tz )['status'] );

		// The same instants expressed in UTC must resolve identically — the
		// helper converts into the venue timezone, applying the +01:00 BST
		// offset that a fixed-offset implementation would miss.
		$this->assertSame( 'closed', salt_location_status( $hours, self::at( '2026-03-29 07:30:00', 'UTC' ), $tz )['status'] );
		$this->assertSame( 'open', salt_location_status( $hours, self::at( '2026-03-29 08:30:00', 'UTC' ), $tz )['status'] );
	}

	public function test_default_timezone_comes_from_wp_timezone_option(): void {
		$GLOBALS['salt_test_options']['timezone_string'] = 'Europe/London';

		$hours = array( self::row( 'monday', '09:00', '17:00' ) );

		// 2026-06-01 08:30 UTC is 09:30 BST in London: open venue-locally
		// even though a naive UTC comparison would say "before open".
		$this->assertSame( 'open', salt_location_status( $hours, self::at( '2026-06-01 08:30:00', 'UTC' ) )['status'] );

		// 16:30 UTC is 17:30 BST: closed venue-locally although 16:30 < 17:00.
		$this->assertSame( 'closed', salt_location_status( $hours, self::at( '2026-06-01 16:30:00', 'UTC' ) )['status'] );
	}

	public function test_malformed_rows_are_skipped_and_logged(): void {
		$hours = array(
			self::row( 'monday', '09:00', '17:00' ),
			self::row( 'someday', '09:00', '17:00' ),
			self::row( 'monday', '9am', '5pm' ),
		);

		$result = salt_location_status( $hours, self::at( '2026-06-01 12:00:00', 'Europe/London' ), new DateTimeZone( 'Europe/London' ) );

		$this->assertSame( 'open', $result['status'] );
		$this->assertCount( 2, $this->logs() );
	}
}
