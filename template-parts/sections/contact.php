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
 * LIVE from v2.0.0: the form renders when a recipient is configured in
 * Site Settings → Contact (salt_contact_configured(); the
 * 'salt_contact_form_enabled' filter remains a kill-switch at 20+).
 * Submission: no-JS POST to admin-post.php (action salt_contact) with a
 * redirect-back status read into the aria-live region (A11Y09);
 * sections/contact.js progressively upgrades to the REST endpoint.
 * Defences (SEC01/SEC02): honeypot, signed time-trap, nonce, rate limit,
 * CAPTCHA (provider widget on the JS path; <noscript> arithmetic
 * challenge on the no-JS path). Privacy-policy line at collection
 * (PRIV06 code-half) from the contact_privacy_page option.
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

$salt_form_enabled = salt_contact_configured();

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
			$salt_status  = salt_contact_request_status();
			$salt_types   = salt_contact_enquiry_types();
			$salt_privacy = get_field( 'contact_privacy_page', 'option' );
			?>
			<div id="salt-contact-form" class="x-contact-status w-full" role="status" aria-live="polite" data-salt-contact-status>
				<?php if ( '' !== $salt_status['status'] ) : ?>
					<p class="<?php echo esc_attr( 'ok' === $salt_status['status'] ? 'rounded-md bg-brand-tint p-3 text-ink' : 'rounded-md border border-error p-3 text-error' ); ?>"><?php echo esc_html( $salt_status['message'] ); ?></p>
				<?php endif; ?>
			</div>
			<form class="x-contact-form flex w-full flex-col gap-4" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" novalidate>
				<input type="hidden" name="action" value="salt_contact">
				<input type="hidden" name="salt_contact_nonce" value="<?php echo esc_attr( wp_create_nonce( 'salt_contact' ) ); ?>">
				<input type="hidden" name="salt_time" value="<?php echo esc_attr( salt_contact_time_token() ); ?>">
				<input type="hidden" name="salt_page_url" value="<?php echo esc_url( home_url( add_query_arg( array() ) ) ); ?>">
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
				<?php if ( '' !== salt_captcha_provider() ) : ?>
					<?php echo salt_captcha_widget_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed provider markup, site key escaped inside. ?>
					<noscript>
						<?php $salt_challenge = salt_challenge_create(); ?>
						<p class="flex flex-col gap-1">
							<label class="font-medium" for="salt-contact-challenge"><?php echo esc_html( $salt_challenge['question'] ); ?></label>
							<input type="hidden" name="salt_challenge_token" value="<?php echo esc_attr( $salt_challenge['token'] ); ?>">
							<input class="rounded-md border border-border bg-surface px-3 py-2 text-ink" type="text" id="salt-contact-challenge" name="salt_challenge_answer" inputmode="numeric" required aria-describedby="salt-contact-challenge-hint">
							<span class="text-sm text-ink-muted" id="salt-contact-challenge-hint"><?php esc_html_e( 'Anti-spam check for submissions without JavaScript.', 'salt' ); ?></span>
						</p>
					</noscript>
				<?php endif; ?>
				<?php if ( is_int( $salt_privacy ) && $salt_privacy > 0 ) : ?>
				<p class="text-sm text-ink-muted">
					<?php
					printf(
						/* translators: %s: privacy policy link. */
						esc_html__( 'By sending this form you agree to us handling your details to respond to your enquiry — see our %s.', 'salt' ),
						'<a class="text-brand underline" href="' . esc_url( (string) get_permalink( $salt_privacy ) ) . '">' . esc_html__( 'privacy policy', 'salt' ) . '</a>'
					);
					?>
				</p>
				<?php endif; ?>
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
