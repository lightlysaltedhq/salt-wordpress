<?php
/**
 * Location open-status service.
 *
 * Turns a stored opening-hours table into a render-ready status
 * ('open' | 'appointment' | 'closed' | 'unknown') plus a translated label.
 * All comparison happens in the venue timezone, so DST transitions are
 * honoured via the tz database rather than fixed offsets (spec §10 #20,
 * Appendix A.6). An 'unknown' status carries an empty label — sections hide
 * the status pill entirely in that case (COMPL02).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse a stored 'HH:MM' 24-hour time into minutes since midnight.
 *
 * @param mixed $time Stored time value (contract: 'HH:MM', 24h).
 * @return int|null Minutes since midnight, or null when malformed.
 */
function salt_location_minutes( $time ): ?int {
	if ( ! is_string( $time ) || 1 !== preg_match( '/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $time, $matches ) ) {
		return null;
	}

	return ( (int) $matches[1] * 60 ) + (int) $matches[2];
}

if ( ! function_exists( 'salt_location_status' ) ) {
	/**
	 * Resolve the current open status for a set of opening hours (ARCH07 pluggable).
	 *
	 * Row contract (frozen, stored 24h): day ('monday'..'sunday', lowercase
	 * English storage keys), open/close ('HH:MM'), appointment_only (bool).
	 * Multiple rows per day express split hours. Semantics:
	 *
	 * - Empty $opening_hours => 'unknown' with an empty label (COMPL02).
	 * - An appointment_only row makes its whole day 'appointment' (times ignored).
	 * - Open when now is within [open, close): at-open inclusive, at-close exclusive.
	 * - close <= open means the span crosses midnight (e.g. friday 20:00–02:00
	 *   covers Fri 20:00 → Sat 02:00), so early-morning times also match the
	 *   previous day's overnight rows.
	 * - A day with no rows is closed.
	 *
	 * Malformed rows are skipped with a salt_log() line (fail loud, never blank).
	 *
	 * @param array<int,array<string,mixed>> $opening_hours Stored opening-hours rows.
	 * @param DateTimeImmutable|null         $now           Injectable "now"; always converted into $timezone before comparing. Defaults to the current instant.
	 * @param DateTimeZone|null              $timezone      Venue timezone. Defaults to wp_timezone().
	 * @return array{status:string,label:string} Status key and translated display label.
	 */
	function salt_location_status( array $opening_hours, ?DateTimeImmutable $now = null, ?DateTimeZone $timezone = null ): array {
		$timezone ??= wp_timezone();
		$now        = null === $now ? new DateTimeImmutable( 'now', $timezone ) : $now->setTimezone( $timezone );

		if ( array() === $opening_hours ) {
			return array(
				'status' => 'unknown',
				'label'  => '',
			);
		}

		$valid_days       = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$rows_by_day      = array_fill_keys( $valid_days, array() );
		$appointment_days = array();

		foreach ( $opening_hours as $index => $row ) {
			$day = is_array( $row ) && isset( $row['day'] ) && is_string( $row['day'] ) ? strtolower( $row['day'] ) : '';

			if ( ! in_array( $day, $valid_days, true ) ) {
				salt_log( sprintf( 'salt_location_status(): row %s has an invalid day; row skipped.', (string) $index ) );
				continue;
			}

			if ( ! empty( $row['appointment_only'] ) ) {
				// Contract: appointment_only claims the whole day; times are ignored.
				$appointment_days[ $day ] = true;
				continue;
			}

			$open  = salt_location_minutes( $row['open'] ?? null );
			$close = salt_location_minutes( $row['close'] ?? null );

			if ( null === $open || null === $close ) {
				salt_log( sprintf( 'salt_location_status(): row %s (%s) has malformed open/close times; row skipped.', (string) $index, $day ) );
				continue;
			}

			$rows_by_day[ $day ][] = array( $open, $close );
		}

		// DateTimeImmutable::format('l') is always English — matches the storage keys.
		$today      = strtolower( $now->format( 'l' ) );
		$yesterday  = strtolower( $now->modify( '-1 day' )->format( 'l' ) );
		$minute_now = ( (int) $now->format( 'G' ) * 60 ) + (int) $now->format( 'i' );

		if ( isset( $appointment_days[ $today ] ) ) {
			return array(
				'status' => 'appointment',
				'label'  => __( 'By appointment', 'salt' ),
			);
		}

		$open_now = false;

		foreach ( $rows_by_day[ $today ] as [ $open, $close ] ) {
			if ( $close > $open ) {
				// Same-day span: [open, close).
				if ( $minute_now >= $open && $minute_now < $close ) {
					$open_now = true;
					break;
				}
			} elseif ( $minute_now >= $open ) {
				// Overnight span, pre-midnight leg: open → midnight today.
				$open_now = true;
				break;
			}
		}

		// Post-midnight leg of yesterday's overnight rows. An appointment-only
		// yesterday has its times ignored, so it contributes no overnight span.
		if ( ! $open_now && ! isset( $appointment_days[ $yesterday ] ) ) {
			foreach ( $rows_by_day[ $yesterday ] as [ $open, $close ] ) {
				if ( $close <= $open && $minute_now < $close ) {
					$open_now = true;
					break;
				}
			}
		}

		if ( $open_now ) {
			return array(
				'status' => 'open',
				'label'  => __( 'Open now', 'salt' ),
			);
		}

		return array(
			'status' => 'closed',
			'label'  => __( 'Closed', 'salt' ),
		);
	}
}
