<?php
/**
 * Section: Contact (spec §10 #19) — MARKUP + FIELDS ONLY in this build.
 *
 * TWO parts:
 *  1. Contact details column — values from the Company options (DATA03
 *     single source of truth): get_field('company_phone','option') as a
 *     tel: link, company_email as a mailto: link, org_name, and
 *     company_address (multi-line, when the option exists) — each gated
 *     by the row's show_* toggle.
 *  2. The FORM — full accessible markup (label + input + aria-describedby
 *     hint per field, an always-on honeypot, submit button) rendered ONLY
 *     when apply_filters( 'salt_contact_form_enabled', false ) is true.
 *
 * THE WIRING POINT (SEC01/SEC02/PRIV06 deferral): NO live submission
 * endpoint exists in this build, so the filter defaults FALSE — every v1
 * site renders the details + form_intro + a mailto CTA button instead of
 * the form. The compliance phase supplies the POST handler (nonce,
 * capability/rate limiting, sanitisation, routing — §9) and flips the
 * filter on (priority 20+). The form posts to action="" method="post"
 * (same URL) so the handler needs no markup change. The honeypot field
 * ('salt_hp', visually hidden + aria-hidden + tabindex="-1") ships in the
 * markup NOW so the handler can rely on it from day one.
 *
 * NO map: maps are absent from this build (deferred — §9).
 *
 * Schema: queues ONE ContactPoint node (telephone/email from options when
 * present, contactType 'customer service') via salt_schema_add() — never
 * an ad-hoc script tag.
 *
 * STATES (COMPL02): nothing to show (no heading/text/form_intro, no
 * option-backed details after toggles, form off) => renders NOTHING.
 * Field ids are static (salt-contact-*): the section is designed as a
 * one-per-page band.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_sub = static function ( string $field ) {
	return function_exists( 'get_sub_field' ) ? get_sub_field( $field ) : null;
};

$salt_heading    = (string) $salt_sub( 'heading' );
$salt_text       = (string) $salt_sub( 'text' );
$salt_form_intro = (string) $salt_sub( 'form_intro' );

// Toggles default ON when the row has never been saved (null).
$salt_show_phone   = false !== $salt_sub( 'show_phone' ) && 0 !== $salt_sub( 'show_phone' );
$salt_show_email   = false !== $salt_sub( 'show_email' ) && 0 !== $salt_sub( 'show_email' );
$salt_show_address = false !== $salt_sub( 'show_address' ) && 0 !== $salt_sub( 'show_address' );

// Detail VALUES come from the Company options, never from the row (DATA03).
$salt_org_name = trim( (string) get_field( 'org_name', 'option' ) );
$salt_phone    = $salt_show_phone ? trim( (string) get_field( 'company_phone', 'option' ) ) : '';
$salt_email    = $salt_show_email ? trim( (string) get_field( 'company_email', 'option' ) ) : '';
$salt_address  = $salt_show_address ? trim( (string) get_field( 'company_address', 'option' ) ) : '';

$salt_has_details = '' !== $salt_org_name || '' !== $salt_phone || '' !== $salt_email || '' !== $salt_address;

/**
 * Whether the live contact form renders (SEC01/SEC02/PRIV06 wiring point).
 *
 * DEFAULT FALSE: no submission endpoint exists in this build. The
 * compliance phase registers the POST handler and returns true here
 * (priority 20+). See the file docblock.
 *
 * @param bool $enabled Default false.
 */
$salt_form_enabled = (bool) apply_filters( 'salt_contact_form_enabled', false );

if (
	! $salt_has_details
	&& '' === trim( $salt_heading )
	&& '' === trim( $salt_text )
	&& '' === trim( $salt_form_intro )
	&& ! $salt_form_enabled
) {
	return; // 0-state: skip the section entirely (COMPL02).
}

// One ContactPoint node per rendered contact section (SEO — spec §15).
$salt_contact_point = array(
	'@type'       => 'ContactPoint',
	'contactType' => 'customer service',
);
if ( '' !== $salt_phone ) {
	$salt_contact_point['telephone'] = $salt_phone;
}
if ( '' !== $salt_email ) {
	$salt_contact_point['email'] = $salt_email;
}
if ( isset( $salt_contact_point['telephone'] ) || isset( $salt_contact_point['email'] ) ) {
	salt_schema_add( $salt_contact_point );
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'contact', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use (
		$salt_heading,
		$salt_text,
		$salt_form_intro,
		$salt_org_name,
		$salt_phone,
		$salt_email,
		$salt_address,
		$salt_has_details,
		$salt_form_enabled
	): void {
		if ( '' !== trim( $salt_heading ) ) {
			salt_component(
				'heading',
				array(
					'text'    => $salt_heading,
					'level'   => $level,
					'classes' => 'mb-4',
				)
			);
		}
		if ( '' !== trim( $salt_text ) ) {
			printf(
				'<p class="mb-8 max-w-prose text-ink-muted">%s</p>',
				nl2br( esc_html( $salt_text ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html applied before nl2br.
			);
		}

		echo '<div class="x-contact grid gap-10 md:grid-cols-2" data-section-component="contact">';

		// --- Part 1: contact details column (option-backed).
		if ( $salt_has_details ) {
			echo '<div class="x-contact-details flex flex-col gap-3 text-ink">';

			if ( '' !== $salt_org_name ) {
				printf( '<p class="font-medium">%s</p>', esc_html( $salt_org_name ) );
			}
			if ( '' !== $salt_phone ) {
				$salt_dialable = (string) preg_replace( '/[^0-9+]/', '', $salt_phone );
				if ( '' !== $salt_dialable ) {
					printf(
						'<p><a class="text-brand underline" href="%1$s">%2$s</a></p>',
						esc_url( 'tel:' . $salt_dialable ),
						esc_html( $salt_phone )
					);
				} else {
					// No dialable digits: plain text, never a dead tel: link.
					printf( '<p>%s</p>', esc_html( $salt_phone ) );
				}
			}
			if ( '' !== $salt_email ) {
				printf(
					'<p><a class="text-brand underline" href="%1$s">%2$s</a></p>',
					esc_url( 'mailto:' . $salt_email ),
					esc_html( $salt_email )
				);
			}
			if ( '' !== $salt_address ) {
				echo '<address class="not-italic text-ink-muted">';
				foreach ( preg_split( '/\r\n|\r|\n/', $salt_address ) as $salt_line ) {
					if ( '' !== trim( (string) $salt_line ) ) {
						printf( '<span class="block">%s</span>', esc_html( trim( (string) $salt_line ) ) );
					}
				}
				echo '</address>';
			}

			echo '</div>';
		}

		// --- Part 2: the form column (or its v1 fallback).
		echo '<div class="x-contact-form-col flex flex-col items-start gap-4">';

		if ( '' !== trim( $salt_form_intro ) ) {
			printf(
				'<p class="text-ink-muted">%s</p>',
				nl2br( esc_html( $salt_form_intro ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html applied before nl2br.
			);
		}

		if ( $salt_form_enabled ) {
			// Full accessible form MARKUP; the compliance phase supplies
			// the handler behind action="" (see file docblock).
			?>
			<form class="x-contact-form flex w-full flex-col gap-4" action="" method="post" novalidate>
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
				<button class="x-button x-button-primary inline-flex items-center justify-center gap-2 rounded-md font-medium focus-visible:outline-2 focus-visible:outline-offset-2 bg-brand text-on-brand hover:bg-brand-strong px-5 py-2.5" type="submit"><?php esc_html_e( 'Send message', 'salt' ); ?></button>
			</form>
			<?php
		} elseif ( '' !== $salt_email ) {
			// v1 fallback: no live endpoint — a mailto CTA instead (never
			// a dead form).
			salt_component(
				'button',
				array(
					'label' => __( 'Email us', 'salt' ),
					'url'   => 'mailto:' . $salt_email,
					'style' => 'primary',
				)
			);
		}

		echo '</div>';

		echo '</div>';
	}
);
