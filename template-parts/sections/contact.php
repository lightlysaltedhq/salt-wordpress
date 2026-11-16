<?php
/**
 * View: Contact (spec §10 #19) — contact details + the accessible form.
 *
 * Draws the props from salt_resolve_section_contact() (inc/sections/
 * contact.php), inside the x-section wrapper the shared entry opens. No
 * field or option reads, services, nonces, tokens, CAPTCHA calls, schema
 * or logging here: the resolver made every one of them and passes the
 * results as props (CONVENTIONS.md rule 4). A child theme overriding this
 * file overrides the markup only.
 *
 * Part 1, the details column: organisation, phone (a tel: link when
 * phoneHref is set, else plain text), email (mailto:), address lines.
 * Part 2, the form column: the form when form_enabled — label + input +
 * aria-describedby hint per field, an always-on honeypot, the aria-live
 * status region (A11Y09), the CAPTCHA widget and <noscript> arithmetic
 * challenge when a challenge was made, the privacy line when privacy_url
 * is set; otherwise a mailto CTA when there is an email (never a dead
 * form). sections/contact.js progressively upgrades the form: it keys off
 * form.x-contact-form, [data-salt-contact-status] and the submit button.
 * Field ids are static (salt-contact-*): a one-per-page band. NO map.
 *
 * $args:
 *   props  array{ heading: string|null, intro: string|null, showPhone: bool,
 *                 showEmail: bool, showAddress: bool, formIntro: string|null,
 *                 details: array{organisation: string|null, phone: string|null,
 *                                phoneHref: string|null, email: string|null,
 *                                address: string[]},
 *                 has_details: bool, form_enabled: bool,
 *                 request_status: array{status: string, message: string}|null,
 *                 enquiry_types: string[]|null, form_action: string|null,
 *                 nonce: string|null, time_token: string|null, page_url: string|null,
 *                 captcha_widget_html: string,
 *                 challenge: array{question: string, token: string}|null,
 *                 privacy_url: string|null }
 *          The form props are non-null whenever form_enabled is true
 *          (challenge and privacy_url may still be null).
 *          captcha_widget_html is service markup, printed as it is.
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props   = $args['props'];
$salt_plan    = $args['plan'];
$salt_details = $salt_props['details'];

if ( null !== $salt_props['heading'] ) {
	salt_component(
		'heading',
		array(
			'text'    => $salt_props['heading'],
			'level'   => $salt_plan['headingLevel'],
			'classes' => 'mb-4',
		)
	);
}
if ( null !== $salt_props['intro'] ) {
	printf(
		'<p class="mb-8 max-w-prose text-ink-muted">%s</p>',
		nl2br( esc_html( $salt_props['intro'] ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html applied before nl2br.
	);
}

echo '<div class="x-contact grid gap-10 md:grid-cols-2" data-section-component="contact">';

// --- Part 1: contact details column (option-backed).
if ( $salt_props['has_details'] ) {
	echo '<div class="x-contact-details flex flex-col gap-3 text-ink">';

	if ( null !== $salt_details['organisation'] ) {
		printf( '<p class="font-medium">%s</p>', esc_html( $salt_details['organisation'] ) );
	}
	if ( null !== $salt_details['phone'] ) {
		if ( null !== $salt_details['phoneHref'] ) {
			printf(
				'<p><a class="text-brand underline" href="%1$s">%2$s</a></p>',
				esc_url( $salt_details['phoneHref'] ),
				esc_html( $salt_details['phone'] )
			);
		} else {
			// No dialable digits: plain text, never a dead tel: link.
			printf( '<p>%s</p>', esc_html( $salt_details['phone'] ) );
		}
	}
	if ( null !== $salt_details['email'] ) {
		printf(
			'<p><a class="text-brand underline" href="%1$s">%2$s</a></p>',
			esc_url( 'mailto:' . $salt_details['email'] ),
			esc_html( $salt_details['email'] )
		);
	}
	if ( array() !== $salt_details['address'] ) {
		echo '<address class="not-italic text-ink-muted">';
		foreach ( $salt_details['address'] as $salt_line ) {
			printf( '<span class="block">%s</span>', esc_html( $salt_line ) );
		}
		echo '</address>';
	}

	echo '</div>';
}

// --- Part 2: the form column (or its mailto fallback).
echo '<div class="x-contact-form-col flex flex-col items-start gap-4">';

if ( null !== $salt_props['formIntro'] ) {
	printf(
		'<p class="text-ink-muted">%s</p>',
		nl2br( esc_html( $salt_props['formIntro'] ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html applied before nl2br.
	);
}

if ( $salt_props['form_enabled'] ) {
	$salt_status = $salt_props['request_status'];
	$salt_types  = $salt_props['enquiry_types'];
	// The form markup below is kept byte for byte, indentation included:
	// it is output, and the snapshots compare it (CONVENTIONS.md rule 1).
	?>
			<div id="salt-contact-form" class="x-contact-status w-full" role="status" aria-live="polite" data-salt-contact-status>
				<?php if ( '' !== $salt_status['status'] ) : ?>
					<p class="<?php echo esc_attr( 'ok' === $salt_status['status'] ? 'rounded-md bg-brand-tint p-3 text-ink' : 'rounded-md border border-error p-3 text-error' ); ?>"><?php echo esc_html( $salt_status['message'] ); ?></p>
				<?php endif; ?>
			</div>
			<form class="x-contact-form flex w-full flex-col gap-4" action="<?php echo esc_url( $salt_props['form_action'] ); ?>" method="post" novalidate>
				<input type="hidden" name="action" value="salt_contact">
				<input type="hidden" name="salt_contact_nonce" value="<?php echo esc_attr( $salt_props['nonce'] ); ?>">
				<input type="hidden" name="salt_time" value="<?php echo esc_attr( $salt_props['time_token'] ); ?>">
				<input type="hidden" name="salt_page_url" value="<?php echo esc_url( $salt_props['page_url'] ); ?>">
				<p class="visually-hidden" aria-hidden="true">
					<label for="salt-contact-hp"><?php esc_html_e( 'Leave this field empty', 'salt' ); ?></label>
					<input type="text" id="salt-contact-hp" name="salt_hp" value="" tabindex="-1" autocomplete="off">
				</p>
				<p class="flex flex-col gap-1">
					<label class="font-medium" for="salt-contact-name"><?php esc_html_e( 'Name', 'salt' ); ?></label>
					<input class="rounded-md border border-border bg-surface px-3 py-2 text-ink" type="text" id="salt-contact-name" name="salt_contact_name" autocomplete="name" required aria-describedby="salt-contact-name-hint">
					<span class="text-sm text-ink-muted" id="salt-contact-name-hint"><?php esc_html_e( 'So we know who to reply to.', 'salt' ); ?></span>
				</p>
				<p class="flex flex-col gap-1">
					<label class="font-medium" for="salt-contact-email"><?php esc_html_e( 'Email', 'salt' ); ?></label>
					<input class="rounded-md border border-border bg-surface px-3 py-2 text-ink" type="email" id="salt-contact-email" name="salt_contact_email" autocomplete="email" required aria-describedby="salt-contact-email-hint">
					<span class="text-sm text-ink-muted" id="salt-contact-email-hint"><?php esc_html_e( 'We only use this to respond to your message.', 'salt' ); ?></span>
				</p>
				<p class="flex flex-col gap-1">
					<label class="font-medium" for="salt-contact-message"><?php esc_html_e( 'Message', 'salt' ); ?></label>
					<textarea class="rounded-md border border-border bg-surface px-3 py-2 text-ink" id="salt-contact-message" name="salt_contact_message" rows="5" required aria-describedby="salt-contact-message-hint"></textarea>
					<span class="text-sm text-ink-muted" id="salt-contact-message-hint"><?php esc_html_e( 'How can we help?', 'salt' ); ?></span>
				</p>
				<?php if ( count( $salt_types ) > 1 ) : ?>
				<p class="flex flex-col gap-1">
					<label class="font-medium" for="salt-contact-type"><?php esc_html_e( 'What is this about?', 'salt' ); ?></label>
					<select class="rounded-md border border-border bg-surface px-3 py-2 text-ink" id="salt-contact-type" name="salt_contact_type">
						<?php foreach ( $salt_types as $salt_type_label ) : ?>
							<option value="<?php echo esc_attr( $salt_type_label ); ?>"><?php echo esc_html( $salt_type_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<?php endif; ?>
				<?php if ( null !== $salt_props['challenge'] ) : ?>
					<?php echo $salt_props['captcha_widget_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Service markup made by salt_captcha_widget_html() in the resolver: fixed provider markup, site key escaped inside. ?>
					<noscript>
						<?php $salt_challenge = $salt_props['challenge']; ?>
						<p class="flex flex-col gap-1">
							<label class="font-medium" for="salt-contact-challenge"><?php echo esc_html( $salt_challenge['question'] ); ?></label>
							<input type="hidden" name="salt_challenge_token" value="<?php echo esc_attr( $salt_challenge['token'] ); ?>">
							<input class="rounded-md border border-border bg-surface px-3 py-2 text-ink" type="text" id="salt-contact-challenge" name="salt_challenge_answer" inputmode="numeric" required aria-describedby="salt-contact-challenge-hint">
							<span class="text-sm text-ink-muted" id="salt-contact-challenge-hint"><?php esc_html_e( 'Anti-spam check for submissions without JavaScript.', 'salt' ); ?></span>
						</p>
					</noscript>
				<?php endif; ?>
				<?php if ( null !== $salt_props['privacy_url'] ) : ?>
				<p class="text-sm text-ink-muted">
					<?php
					printf(
						/* translators: %s: privacy policy link. */
						esc_html__( 'By sending this form you agree to us handling your details to respond to your enquiry — see our %s.', 'salt' ),
						'<a class="text-brand underline" href="' . esc_url( $salt_props['privacy_url'] ) . '">' . esc_html__( 'privacy policy', 'salt' ) . '</a>'
					);
					?>
				</p>
				<?php endif; ?>
				<button class="x-button x-button-primary inline-flex items-center justify-center gap-2 rounded-md font-medium bg-brand text-on-brand hover:bg-brand-strong px-5 py-2.5" type="submit"><?php esc_html_e( 'Send message', 'salt' ); ?></button>
			</form>
			<?php
} elseif ( null !== $salt_details['email'] ) {
	// No live form: a mailto CTA instead (never a dead form).
	salt_component(
		'button',
		array(
			'label' => __( 'Email us', 'salt' ),
			'url'   => 'mailto:' . $salt_details['email'],
			'style' => 'primary',
		)
	);
}

echo '</div>';

echo '</div>';
