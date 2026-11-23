<?php
/**
 * x-opening-hours — semantic <dl>/<time> rendering of the frozen
 * opening_hours storage contract (Appendix A.6; COMPL02 0-state).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class OpeningHoursComponentTest extends SaltTestCase {

	/**
	 * Render the component.
	 *
	 * @param array<int,array<string,mixed>> $hours   Stored rows.
	 * @param bool                           $compact Compact variant.
	 * @return string
	 */
	private function render_hours( array $hours, bool $compact = false ): string {
		return salt_get_component(
			'opening-hours',
			array(
				'hours'   => $hours,
				'compact' => $compact,
			)
		);
	}

	/**
	 * A stored row.
	 *
	 * @param string $day         Day key.
	 * @param string $open        Open time.
	 * @param string $close       Close time.
	 * @param bool   $appointment Appointment-only flag.
	 * @return array<string,mixed>
	 */
	private static function row( string $day, string $open = '', string $close = '', bool $appointment = false ): array {
		return array(
			'day'         => $day,
			'open'        => $open,
			'close'       => $close,
			'appointment' => $appointment,
		);
	}

	public function test_empty_hours_render_nothing(): void {
		$this->assertSame( '', $this->render_hours( array() ) );
	}

	public function test_all_rows_malformed_renders_nothing_and_logs(): void {
		$html = $this->render_hours(
			array(
				self::row( 'someday', '09:00', '17:00' ),
				self::row( 'monday', '9am', '5pm' ),
			)
		);

		$this->assertSame( '', $html );
		$this->assertCount( 2, $this->logs() );
	}

	public function test_malformed_rows_are_skipped_while_valid_rows_render(): void {
		$html = $this->render_hours(
			array(
				self::row( 'monday', '09:00', '17:00' ),
				self::row( 'someday', '09:00', '17:00' ),
			)
		);

		$this->assertStringContainsString( '<dl', $html );
		$this->assertStringContainsString( '<dt>Monday</dt>', $html );
		$this->assertCount( 1, $this->logs() );
	}

	public function test_times_render_as_time_elements_with_datetime(): void {
		$html = $this->render_hours( array( self::row( 'friday', '08:30', '17:00' ) ) );

		$this->assertStringContainsString( '<time datetime="08:30">08:30</time>', $html );
		$this->assertStringContainsString( '<time datetime="17:00">17:00</time>', $html );
	}

	public function test_split_hours_group_into_one_day_row(): void {
		$html = $this->render_hours(
			array(
				self::row( 'wednesday', '09:00', '12:00' ),
				self::row( 'wednesday', '14:00', '17:00' ),
			)
		);

		$this->assertSame( 1, substr_count( $html, '<dt>Wednesday</dt>' ) );
		$this->assertSame( 2, preg_match_all( '/class="salt-opening-hours__span"/', $html ) );
		$this->assertStringContainsString( '<time datetime="14:00">', $html );
	}

	public function test_appointment_day_renders_the_label_not_times(): void {
		$html = $this->render_hours( array( self::row( 'saturday', '', '', true ) ) );

		$this->assertStringContainsString( '<span>By appointment</span>', $html );
		$this->assertStringNotContainsString( '<time', $html );
	}

	public function test_days_render_in_week_order_regardless_of_input_order(): void {
		$html = $this->render_hours(
			array(
				self::row( 'friday', '09:00', '17:00' ),
				self::row( 'monday', '09:00', '17:00' ),
			)
		);

		$this->assertLessThan(
			strpos( $html, 'Friday' ),
			strpos( $html, 'Monday' ),
			'Monday renders before Friday.'
		);
	}

	public function test_compact_variant_abbreviates_day_labels(): void {
		$html = $this->render_hours( array( self::row( 'monday', '09:00', '17:00' ) ), true );

		$this->assertStringContainsString( '<dt>Mon</dt>', $html );
		$this->assertStringNotContainsString( 'Monday', $html );
		$this->assertStringContainsString( '<dl class="salt-opening-hours" data-compact>', $html );
	}
}
