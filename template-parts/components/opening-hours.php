<?php
/**
 * The contract's opening hours (salt-contract markup/opening-hours.json,
 * rc.8): a semantic <dl> of structured opening hours.
 *
 * Consumes the stored hours rows (salt-contract collections/locations.json, shared
 * with salt_location_status()): day ('monday'..'sunday' lowercase English
 * keys), open/close ('HH:MM' 24h), appointment (bool). Split hours
 * (multiple rows per day) group into one <dd> per day; every time renders
 * as <time datetime="HH:MM">. Appointment-only days render
 * salt_contract_labels()'s byAppointment. Days with no rows are omitted.
 *
 * ARGS:
 *   hours   array  required  Stored opening-hours rows (shape above).
 *                            Malformed rows (unknown day, bad times) are
 *                            skipped + salt_log()ged — never a fatal.
 *                            Rows that all fail render NOTHING (COMPL02).
 *   compact bool   false     Abbreviated day labels (data-compact).
 *
 * Render via salt_component( 'opening-hours', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is WordPress's load_template() template variable.
	$args ?? array(),
	array(
		'hours'   => array(
			'type'     => 'array',
			'required' => true,
		),
		'compact' => array(
			'type'    => 'bool',
			'default' => false,
		),
	),
	'opening-hours'
);

$salt_day_labels = array(
	'monday'    => array( __( 'Monday', 'salt' ), __( 'Mon', 'salt' ) ),
	'tuesday'   => array( __( 'Tuesday', 'salt' ), __( 'Tue', 'salt' ) ),
	'wednesday' => array( __( 'Wednesday', 'salt' ), __( 'Wed', 'salt' ) ),
	'thursday'  => array( __( 'Thursday', 'salt' ), __( 'Thu', 'salt' ) ),
	'friday'    => array( __( 'Friday', 'salt' ), __( 'Fri', 'salt' ) ),
	'saturday'  => array( __( 'Saturday', 'salt' ), __( 'Sat', 'salt' ) ),
	'sunday'    => array( __( 'Sunday', 'salt' ), __( 'Sun', 'salt' ) ),
);

$salt_spans_by_day       = array_fill_keys( array_keys( $salt_day_labels ), array() );
$salt_appointment_by_day = array();

foreach ( $args['hours'] as $salt_row_index => $salt_row ) {
	$salt_day = is_array( $salt_row ) && is_string( $salt_row['day'] ?? null ) ? strtolower( $salt_row['day'] ) : '';

	if ( ! isset( $salt_day_labels[ $salt_day ] ) ) {
		salt_log( sprintf( 'opening-hours: row %s has an invalid day — skipped.', (string) $salt_row_index ) );
		continue;
	}

	if ( ! empty( $salt_row['appointment'] ) ) {
		// Contract: appointment claims the whole day; times are ignored.
		$salt_appointment_by_day[ $salt_day ] = true;
		continue;
	}

	$salt_open  = $salt_row['open'] ?? null;
	$salt_close = $salt_row['close'] ?? null;

	if ( null === salt_location_minutes( $salt_open ) || null === salt_location_minutes( $salt_close ) ) {
		salt_log( sprintf( 'opening-hours: row %s (%s) has malformed open/close times — skipped.', (string) $salt_row_index, $salt_day ) );
		continue;
	}

	$salt_spans_by_day[ $salt_day ][] = array( (string) $salt_open, (string) $salt_close );
}

$salt_has_rows = array() !== $salt_appointment_by_day;
foreach ( $salt_spans_by_day as $salt_spans ) {
	if ( array() !== $salt_spans ) {
		$salt_has_rows = true;
		break;
	}
}

if ( ! $salt_has_rows ) {
	return; // 0-state: nothing valid to render (COMPL02).
}

$salt_label_index = $args['compact'] ? 1 : 0;
?>
<dl class="salt-opening-hours"<?php echo $args['compact'] ? ' data-compact' : ''; ?>>
	<?php foreach ( $salt_day_labels as $salt_day => $salt_labels ) : ?>
		<?php
		$salt_is_appointment = isset( $salt_appointment_by_day[ $salt_day ] );
		if ( ! $salt_is_appointment && array() === $salt_spans_by_day[ $salt_day ] ) {
			continue;
		}
		?>
	<div class="salt-opening-hours__day">
		<dt><?php echo esc_html( $salt_labels[ $salt_label_index ] ); ?></dt>
		<dd>
		<?php if ( $salt_is_appointment ) : ?>
			<span><?php echo esc_html( salt_contract_labels()['byAppointment'] ?? '' ); ?></span>
		<?php else : ?>
			<?php
			$salt_span_html = array();
			foreach ( $salt_spans_by_day[ $salt_day ] as $salt_span ) {
				$salt_span_html[] = sprintf(
					'<span class="salt-opening-hours__span"><time datetime="%1$s">%2$s</time>&#8211;<time datetime="%3$s">%4$s</time></span>',
					esc_attr( $salt_span[0] ),
					esc_html( $salt_span[0] ),
					esc_attr( $salt_span[1] ),
					esc_html( $salt_span[1] )
				);
			}
			echo implode( '<span aria-hidden="true">, </span>', $salt_span_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each time value escaped at build above; separator is a literal.
			?>
		<?php endif; ?>
		</dd>
	</div>
	<?php endforeach; ?>
</dl>
