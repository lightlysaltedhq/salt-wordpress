<?php
/**
 * Resolver: Contact (spec §10 #19; contract markup/contact.json and
 * contact-form.json) — the logic half of the section; the view is
 * template-parts/sections/contact.php.
 *
 * Draws, in the contract's order: heading, intro, the contact details
 * (values from the Company options, DATA03: org_name, company_phone,
 * company_email, company_address, each but org_name gated by the row's
 * show* toggle, on when never saved), then the form intro and the form
 * when the form is live (salt_contact_configured()), or the closed message.
 * The section always draws (the contract's zero state).
 *
 * The form as first drawn carries no credential and no per-visitor value
 * (SC-025), so the page can be cached: no nonce, time token or challenge
 * is made here. A form answered in this response (the self-posted page,
 * salt_contact_self_post()) comes through salt_contact_form_states(), with
 * its values, errors, status and fresh credentials.
 *
 * Schema: queues ONE ContactPoint node (telephone/email from options when
 * present, contactType 'customer service') via salt_schema_add().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current contact Flexible Content row to its view props.
 *
 * Props (contract view-props/contact.json):
 *   heading      string|null  Null when blank.
 *   intro        string|null  Null when blank.
 *   showPhone    bool         Toggle; null/unsaved => on. Likewise:
 *   showEmail    bool
 *   showAddress  bool
 *   formIntro    string|null  Null when blank.
 *   details      array{organisation: string|null, phone: string|null,
 *                      phoneHref: string|null, email: string|null,
 *                      address: string[]}
 *                             From the Company options, each null where
 *                             unset or switched off; phoneHref by the
 *                             contract's tel rule (salt_contract_tel()).
 *   form         array{action: string, enquiryTypes: array<int,array{value: string, label: string}>,
 *                      phone: string, privacyHref: string|null, captcha: bool}|null
 *                             Null when the site takes no enquiries. action
 *                             is the page itself; the view adds the band's
 *                             anchor (?salt-contact-send=<anchor>).
 * WordPress-local:
 *   return_to            string  The page's same-site path (returnTo).
 *   captcha_widget_html  string  salt_captcha_widget_html() when a provider
 *                                is configured, else '': service markup,
 *                                the same for every visitor.
 *   states               array<string,array>  salt_contact_form_states():
 *                                the forms answered in this response, by
 *                                anchor (the view picks its own).
 *   legacy_status        array{tone: string, message: string}|null
 *                                The pre-6.0 no-JS path's redirect flag
 *                                (?salt-contact=ok|error), for one release.
 *   labels               array<string,string>  salt_contract_labels().
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}
 */
function salt_resolve_section_contact( array $context ): array {
	$sub = static function ( string $field ) {
		return function_exists( 'get_sub_field' ) ? get_sub_field( $field ) : null;
	};

	$heading    = (string) $sub( 'heading' );
	$text       = (string) $sub( 'intro' );
	$form_intro = (string) $sub( 'formIntro' );

	// Toggles default ON when the row has never been saved (null).
	$show_phone   = false !== $sub( 'showPhone' ) && 0 !== $sub( 'showPhone' );
	$show_email   = false !== $sub( 'showEmail' ) && 0 !== $sub( 'showEmail' );
	$show_address = false !== $sub( 'showAddress' ) && 0 !== $sub( 'showAddress' );

	// Detail VALUES come from the Company options, never from the row (DATA03).
	$org_name = trim( (string) get_field( 'org_name', 'option' ) );
	$phone    = $show_phone ? trim( (string) get_field( 'company_phone', 'option' ) ) : '';
	$email    = $show_email ? trim( (string) get_field( 'company_email', 'option' ) ) : '';
	$address  = $show_address ? trim( (string) get_field( 'company_address', 'option' ) ) : '';

	// One ContactPoint node per rendered contact section (SEO — spec §15).
	$contact_point = array(
		'@type'       => 'ContactPoint',
		'contactType' => 'customer service',
	);
	if ( '' !== $phone ) {
		$contact_point['telephone'] = $phone;
	}
	if ( '' !== $email ) {
		$contact_point['email'] = $email;
	}
	if ( isset( $contact_point['telephone'] ) || isset( $contact_point['email'] ) ) {
		salt_schema_add( $contact_point );
	}

	$address_lines = array();
	foreach ( '' !== $address ? preg_split( '/\r\n|\r|\n/', $address ) : array() as $line ) {
		if ( '' !== trim( (string) $line ) ) {
			$address_lines[] = trim( (string) $line );
		}
	}

	$form      = null;
	$return_to = salt_contact_page_path();
	$widget    = '';
	if ( salt_contact_configured() ) {
		$privacy = get_field( 'contact_privacy_page', 'option' );
		$captcha = '' !== salt_captcha_provider();
		$form    = array(
			'action'       => $return_to,
			'enquiryTypes' => salt_contact_enquiry_types(),
			'phone'        => salt_contact_phone_mode(),
			'privacyHref'  => is_int( $privacy ) && $privacy > 0 ? salt_contract_url( (string) get_permalink( $privacy ) ) : null,
			'captcha'      => $captcha,
		);
		$widget  = $captcha ? salt_captcha_widget_html() : '';
	}
	$legacy = salt_contact_request_status();

	return array(
		'section' => array_merge(
			salt_section_contract_args( 'contact', $context ),
			// A section with no heading is a div and must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'heading'             => salt_nullable_text( $heading ),
			'intro'               => salt_nullable_text( $text ),
			'showPhone'           => $show_phone,
			'showEmail'           => $show_email,
			'showAddress'         => $show_address,
			'formIntro'           => salt_nullable_text( $form_intro ),
			'details'             => array(
				'organisation' => '' !== $org_name ? $org_name : null,
				'phone'        => '' !== $phone ? $phone : null,
				'phoneHref'    => '' !== $phone ? salt_contract_tel( $phone ) : null,
				'email'        => '' !== $email ? $email : null,
				'address'      => $address_lines,
			),
			'form'                => $form,
			'return_to'           => $return_to,
			'captcha_widget_html' => $widget,
			'states'              => null !== $form ? salt_contact_form_states() : array(),
			'legacy_status'       => null !== $form && '' !== $legacy['status'] ? array(
				'tone'    => 'ok' === $legacy['status'] ? 'sent' : 'problem',
				'message' => $legacy['message'],
			) : null,
			'labels'              => salt_contract_labels(),
		),
	);
}
