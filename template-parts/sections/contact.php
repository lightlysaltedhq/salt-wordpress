<?php
/**
 * View: Contact (spec §10 #19; contract markup/contact.json and
 * contact-form.json) — the contact details, then the form or the closed
 * message.
 *
 * Draws the props from salt_resolve_section_contact() (inc/sections/
 * contact.php) as the contract's block, div.salt-block[data-block=contact],
 * inside the band salt_section() opens. No field or option reads,
 * services, nonces, tokens, CAPTCHA calls, schema or logging here
 * (CONVENTIONS.md rule 4). A child theme overriding this file overrides the
 * markup only.
 *
 * Every id is the band's: <anchor>__form, <anchor>__<field> (the field's
 * contract name in kebab case), <anchor>__<field>-error,
 * <anchor>__challenge, <anchor>__status, <anchor>__website (SC-012). The
 * form as first drawn carries no credential (SC-025); a form answered in
 * this response (props.states[<anchor>]) is drawn again with what was
 * sent, each error, its status and fresh credentials: the challenge (in
 * noscript when a CAPTCHA provider's widget is drawn) and the two tokens.
 * sections/contact.js upgrades the form: it keys off form.salt-contact__form,
 * p.salt-contact__status and button.salt-contact__submit.
 *
 * $args:
 *   props  see salt_resolve_section_contact().
 *   plan   array{ index: int, anchor: string, headingLevel: int, headingId: string|null }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props   = $args['props'];
$salt_plan    = $args['plan'];
$salt_details = $salt_props['details'];
$salt_labels  = $salt_props['labels'];
$salt_label   = static fn( string $key ): string => (string) ( $salt_labels[ $key ] ?? '' );
$salt_level   = max( 1, min( 6, (int) $salt_plan['headingLevel'] ) );
$salt_anchor  = (string) ( $salt_plan['anchor'] ?? '' );
$salt_anchor  = '' !== $salt_anchor ? $salt_anchor : 'contact';

echo '<div class="salt-block" data-block="contact">';

if ( null !== $salt_props['heading'] ) {
	printf(
		'<h%1$d%2$s>%3$s</h%1$d>',
		(int) $salt_level,
		is_string( $salt_plan['headingId'] ?? null ) ? ' id="' . esc_attr( $salt_plan['headingId'] ) . '"' : '',
		esc_html( (string) $salt_props['heading'] )
	);
}
if ( null !== $salt_props['intro'] ) {
	echo '<p class="salt-intro">' . esc_html( (string) $salt_props['intro'] ) . '</p>';
}

// The details (option-backed), when there is any to show.
if ( null !== $salt_details['organisation'] || null !== $salt_details['phone'] || null !== $salt_details['email'] || array() !== $salt_details['address'] ) {
	echo '<div class="salt-contact__details">';
	if ( null !== $salt_details['organisation'] ) {
		echo '<p class="salt-contact__organisation">' . esc_html( (string) $salt_details['organisation'] ) . '</p>';
	}
	if ( null !== $salt_details['phone'] ) {
		echo null !== $salt_details['phoneHref']
			? '<p><a class="salt-contact__link" href="' . esc_url( (string) $salt_details['phoneHref'], array( 'tel' ) ) . '">' . esc_html( (string) $salt_details['phone'] ) . '</a></p>'
			: '<p>' . esc_html( (string) $salt_details['phone'] ) . '</p>'; // No dialable number: plain text, never a dead tel: link.
	}
	if ( null !== $salt_details['email'] ) {
		echo '<p><a class="salt-contact__link" href="' . esc_url( 'mailto:' . $salt_details['email'], array( 'mailto' ) ) . '">' . esc_html( (string) $salt_details['email'] ) . '</a></p>';
	}
	if ( array() !== $salt_details['address'] ) {
		echo '<address class="salt-contact__address">';
		foreach ( $salt_details['address'] as $salt_line ) {
			echo '<span class="salt-contact__line">' . esc_html( (string) $salt_line ) . "</span>\n";
		}
		echo '</address>';
	}
	echo '</div>';
}

$salt_form = $salt_props['form'];
if ( null === $salt_form ) {
	echo '<p class="salt-contact__closed">' . esc_html( $salt_label( 'unavailable' ) ) . '</p>';
	echo '</div>';
	return;
}

if ( null !== $salt_props['formIntro'] ) {
	echo '<p class="salt-intro">' . esc_html( (string) $salt_props['formIntro'] ) . '</p>';
}

$salt_state  = $salt_props['states'][ $salt_anchor ] ?? null;
$salt_values = is_array( $salt_state ) ? (array) ( $salt_state['values'] ?? array() ) : array();
$salt_errors = is_array( $salt_state ) ? (array) ( $salt_state['errors'] ?? array() ) : array();
$salt_status = is_array( $salt_state ) ? ( $salt_state['status'] ?? null ) : $salt_props['legacy_status'];
$salt_id     = static fn( string $part ): string => $salt_anchor . '__' . $part;
$salt_types  = $salt_form['enquiryTypes'];

/*
 * One field: its label (with the optional mark), its error, its control.
 * The control's attributes are in the contract's order; aria-* only when
 * it failed, value only when it is drawn again with what was sent.
 */
$salt_field = static function ( string $name, string $kind, array $attributes, bool $optional = false ) use ( $salt_id, $salt_label, $salt_values, $salt_errors, $salt_types ): void {
	$id    = $salt_id( strtolower( (string) preg_replace( '/([a-z])([A-Z])/', '$1-$2', $name ) ) );
	$error = isset( $salt_errors[ $name ] ) ? (string) $salt_errors[ $name ] : null;
	$value = isset( $salt_values[ $name ] ) ? (string) $salt_values[ $name ] : '';

	echo '<div class="salt-contact__field">';
	printf(
		'<label class="salt-contact__label" for="%1$s">%2$s%3$s</label>',
		esc_attr( $id ),
		esc_html( $salt_label( $name ) ),
		$optional ? ' <span class="salt-contact__optional">' . esc_html( $salt_label( 'optional' ) ) . '</span>' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as built.
	);
	if ( null !== $error ) {
		echo '<p class="salt-contact__error" id="' . esc_attr( $id . '-error' ) . '">' . esc_html( $error ) . '</p>';
	}

	$attrs = ' class="salt-contact__input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"';
	foreach ( $attributes as $attribute => $attribute_value ) {
		$attrs .= true === $attribute_value ? ' ' . $attribute : ' ' . $attribute . '="' . esc_attr( (string) $attribute_value ) . '"';
	}
	if ( null !== $error ) {
		$attrs .= ' aria-invalid="true" aria-describedby="' . esc_attr( $id . '-error ' . $salt_id( 'status' ) ) . '"';
	}

	if ( 'select' === $kind ) {
		$selected = in_array( $value, array_column( $salt_types, 'value' ), true ) ? $value : (string) ( $salt_types[0]['value'] ?? '' );
		echo '<select' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as built.
		foreach ( $salt_types as $salt_type ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $salt_type['value'] ),
				$selected === $salt_type['value'] ? ' selected' : '',
				esc_html( (string) $salt_type['label'] )
			);
		}
		echo '</select>';
	} elseif ( 'textarea' === $kind ) {
		echo '<textarea' . $attrs . '>' . esc_html( $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as built.
	} else {
		echo '<input' . $attrs . ( '' !== $value ? ' value="' . esc_attr( $value ) . '"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as built.
	}
	echo '</div>';
};

// The action (platform-native, SC-003) names this form's status as its
// fragment: a scriptless send is answered by a whole page, and the browser
// scrolls that page to the status and focuses it (tabindex -1), so the
// reply is in view and read out, for this form and no other on the page.
printf(
	'<form class="salt-contact__form" id="%1$s" method="post" action="%2$s" novalidate>',
	esc_attr( $salt_id( 'form' ) ),
	esc_url( add_query_arg( 'salt-contact-send', $salt_anchor, (string) $salt_form['action'] ) . '#' . $salt_id( 'status' ) )
);

$salt_field(
	'name',
	'input',
	array(
		'type'         => 'text',
		'autocomplete' => 'name',
		'required'     => true,
	)
);
$salt_field(
	'email',
	'input',
	array(
		'type'         => 'email',
		'autocomplete' => 'email',
		'required'     => true,
	)
);
if ( 'off' !== $salt_form['phone'] ) {
	$salt_field(
		'phone',
		'input',
		array_merge(
			array(
				'type'         => 'tel',
				'autocomplete' => 'tel',
			),
			'required' === $salt_form['phone'] ? array( 'required' => true ) : array()
		),
		'optional' === $salt_form['phone']
	);
}
if ( count( $salt_types ) > 1 ) {
	$salt_field( 'enquiryType', 'select', array() );
}
$salt_field(
	'message',
	'textarea',
	array(
		'rows'     => '6',
		'required' => true,
	)
);

if ( '' !== $salt_props['captcha_widget_html'] ) {
	echo $salt_props['captcha_widget_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Service markup made by salt_captcha_widget_html() in the resolver: fixed provider markup, site key escaped inside.
}

// The credentials, only in the form's own answer to a send (SC-025).
if ( is_array( $salt_state ) && isset( $salt_state['challenge'], $salt_state['formToken'] ) ) {
	$salt_challenge = $salt_state['challenge'];
	$salt_captcha   = (bool) $salt_form['captcha'];
	echo $salt_captcha ? '<noscript>' : '';
	echo '<div class="salt-contact__field">';
	echo '<label class="salt-contact__label" for="' . esc_attr( $salt_id( 'challenge' ) ) . '">' . esc_html( (string) $salt_challenge['question'] ) . '</label>';
	if ( isset( $salt_errors['challengeAnswer'] ) ) {
		echo '<p class="salt-contact__error">' . esc_html( (string) $salt_errors['challengeAnswer'] ) . '</p>';
	}
	echo '<input class="salt-contact__input salt-contact__input--short" id="' . esc_attr( $salt_id( 'challenge' ) ) . '" name="challengeAnswer" type="text" inputmode="numeric" autocomplete="off" required aria-describedby="' . esc_attr( $salt_id( 'status' ) ) . '">';
	echo '</div>';
	echo '<input type="hidden" name="challengeToken" value="' . esc_attr( (string) $salt_challenge['token'] ) . '">';
	echo $salt_captcha ? '</noscript>' : '';
	echo '<input type="hidden" name="formToken" value="' . esc_attr( (string) $salt_state['formToken'] ) . '">';
}

// The honeypot: shaped like every real field, out of the tab order, no value.
echo '<div class="salt-sr-only salt-contact__trap" aria-hidden="true">';
echo '<label for="' . esc_attr( $salt_id( 'website' ) ) . '">' . esc_html( $salt_label( 'honeypot' ) ) . "</label>\n";
echo '<input id="' . esc_attr( $salt_id( 'website' ) ) . '" name="website" type="text" tabindex="-1" autocomplete="off">';
echo '</div>';
echo '<input type="hidden" name="returnTo" value="' . esc_attr( (string) $salt_props['return_to'] ) . '">';

if ( null !== $salt_form['privacyHref'] ) {
	$salt_line = explode( '{link}', $salt_label( 'privacyLine' ), 2 );
	printf(
		'<p class="salt-contact__privacy">%1$s<a class="salt-contact__link" href="%2$s">%3$s</a>%4$s</p>',
		esc_html( $salt_line[0] ),
		esc_url( (string) $salt_form['privacyHref'] ),
		esc_html( $salt_label( 'privacy' ) ),
		esc_html( $salt_line[1] ?? '' )
	);
}

echo '<div class="salt-contact__actions">';
echo '<button class="salt-button salt-contact__submit" type="submit" data-style="primary">' . esc_html( $salt_label( 'submit' ) ) . '</button>';
echo '</div>';

// Always present, so the live region exists before it speaks.
printf(
	'<p class="salt-contact__status" id="%1$s" role="status" aria-live="polite" tabindex="-1"%2$s>%3$s</p>',
	esc_attr( $salt_id( 'status' ) ),
	is_array( $salt_status ) ? ' data-tone="' . esc_attr( (string) $salt_status['tone'] ) . '"' : '',
	is_array( $salt_status ) ? esc_html( (string) $salt_status['message'] ) : ''
);

echo '</form>';
echo '</div>';
