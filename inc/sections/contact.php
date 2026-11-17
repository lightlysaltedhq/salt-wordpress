<?php
/**
 * Resolver: Contact (spec §10 #19) — the logic half of the section; the
 * view is template-parts/sections/contact.php.
 *
 * TWO parts:
 *  1. Contact details — values from the Company options (DATA03 single
 *     source of truth): org_name, company_phone, company_email and
 *     company_address, each (but org_name) gated by the row's show*
 *     toggle (toggles default ON when the row has never been saved).
 *  2. The FORM — only when salt_contact_configured() (a recipient is
 *     configured; the 'salt_contact_form_enabled' filter remains a
 *     kill-switch at 20+). Every service and stateful call the form needs
 *     (request status, enquiry types, privacy page, form action, nonce,
 *     time-trap token, page URL, CAPTCHA provider, widget and no-JS
 *     challenge, privacy permalink) is made HERE, under the same
 *     conditions, the same number of times and in the same order as the
 *     pre-split template made them (CONVENTIONS.md rule 4), and passed to
 *     the view as values.
 *
 * Schema: queues ONE ContactPoint node (telephone/email from options when
 * present, contactType 'customer service') via salt_schema_add().
 *
 * STATES (COMPL02): nothing to show (no heading/intro/formIntro, no
 * option-backed details after toggles, form off) => null (renders
 * NOTHING, no band).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current contact Flexible Content row to its view props.
 *
 * Props (contract view-props/contact.json; WordPress-local names in snake_case):
 *   heading              string|null  Contract `heading`. Null when blank.
 *   intro                string|null  Contract `intro`, from the `intro` field
 *                                     (plain text; the view applies nl2br).
 *                                     Null when blank.
 *   showPhone            bool         Contract `showPhone` (toggle; null/unsaved => on).
 *   showEmail            bool         Contract `showEmail`.
 *   showAddress          bool         Contract `showAddress`.
 *   formIntro            string|null  Contract `formIntro`, from `formIntro`.
 *                                     Null when blank.
 *   details              array{organisation: string|null, phone: string|null,
 *                              phoneHref: string|null, email: string|null,
 *                              address: string[]}
 *                                     Contract `details` (contact-details), from
 *                                     the Company options, trimmed; each null
 *                                     where unset or switched off (address:
 *                                     the non-empty trimmed lines, array()
 *                                     for none). phoneHref is 'tel:' + the
 *                                     dialable digits, null when none survive
 *                                     (the number is then drawn as text).
 *   has_details          bool         WordPress-local: any detail to draw.
 *   form_enabled         bool         WordPress-local: salt_contact_configured()
 *                                     (contract `form` non-null).
 * The form props below are computed only when form_enabled; otherwise they
 * are null (or '' for captcha_widget_html), never computed:
 *   request_status       array{status: string, message: string}|null
 *                                     WordPress-local: salt_contact_request_status().
 *   enquiry_types        string[]|null WordPress-local: salt_contact_enquiry_types()
 *                                     labels (drawn as a select when 2+).
 *   form_action          string|null  WordPress-local (contract `form.action`):
 *                                     admin_url( 'admin-post.php' ).
 *   nonce                string|null  WordPress-local: wp_create_nonce( 'salt_contact' ).
 *   time_token           string|null  WordPress-local: salt_contact_time_token().
 *   page_url             string|null  WordPress-local: home_url( add_query_arg( array() ) ).
 *   captcha_widget_html  string       WordPress-local, service-produced markup:
 *                                     salt_captcha_widget_html() when a CAPTCHA
 *                                     provider is configured, else ''.
 *   challenge            array{question: string, token: string}|null
 *                                     WordPress-local: salt_challenge_create()
 *                                     (writes a transient) when a CAPTCHA
 *                                     provider is configured, else null
 *                                     (contract `form.captcha` = non-null).
 *   privacy_url          string|null  WordPress-local (contract `form.privacyHref`):
 *                                     get_permalink() of the contact_privacy_page
 *                                     option when it is a positive int, else null.
 *
 * Naming gaps against the contract: the form is flat WordPress-local props
 * named as CONVENTIONS.md rule 4 names them, not the contract's nullable
 * `form` object (`form.action` = form_action, `form.privacyHref` =
 * privacy_url, `form.captcha` = null !== challenge); `form.id`,
 * `form.phone` and enquiry-type value/label have no WordPress counterpart
 * (field ids are static salt-contact-*, there is no phone field, and an
 * enquiry type is a label only).
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_contact( array $context ): ?array {
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

	$has_details = '' !== $org_name || '' !== $phone || '' !== $email || '' !== $address;

	$form_enabled = salt_contact_configured();

	if (
		! $has_details
		&& '' === trim( $heading )
		&& '' === trim( $text )
		&& '' === trim( $form_intro )
		&& ! $form_enabled
	) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

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

	// Wrapper inputs: read here, where the pre-split template read them
	// (after the schema, before any form service call).
	$section = array_merge(
		salt_section_wrapper_args( 'contact', $context ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $heading ) )
	);

	// Rule 5: the dialable-phone test decides the markup, so it is made here.
	$phone_href = null;
	if ( '' !== $phone ) {
		$dialable = (string) preg_replace( '/[^0-9+]/', '', $phone );
		if ( '' !== $dialable ) {
			$phone_href = 'tel:' . $dialable;
		}
	}

	$address_lines = array();
	if ( '' !== $address ) {
		foreach ( preg_split( '/\r\n|\r|\n/', $address ) as $line ) {
			if ( '' !== trim( (string) $line ) ) {
				$address_lines[] = trim( (string) $line );
			}
		}
	}

	/*
	 * Form services and stateful calls (rule 4) — ONLY when the form is
	 * drawn, in the pre-split markup's order: request status, enquiry
	 * types, privacy option, form action, nonce, time-trap token, page URL,
	 * CAPTCHA provider; only when a provider is configured, the widget then
	 * the no-JS challenge (salt_challenge_create() writes a transient: it
	 * must not run when the challenge is not drawn); only when the privacy
	 * page is a positive int, its permalink. Order is pinned by
	 * ContactSectionTest.
	 */
	$request_status      = null;
	$enquiry_types       = null;
	$form_action         = null;
	$nonce               = null;
	$time_token          = null;
	$page_url            = null;
	$captcha_widget_html = '';
	$challenge           = null; // The {question, token} pair once made, else null.
	$privacy_url         = null;
	if ( $form_enabled ) {
		$request_status = salt_contact_request_status();
		$enquiry_types  = salt_contact_enquiry_types();
		$privacy        = get_field( 'contact_privacy_page', 'option' );
		$form_action    = admin_url( 'admin-post.php' );
		$nonce          = wp_create_nonce( 'salt_contact' );
		$time_token     = salt_contact_time_token();
		$page_url       = home_url( add_query_arg( array() ) );
		if ( '' !== salt_captcha_provider() ) {
			$captcha_widget_html = salt_captcha_widget_html();
			$challenge           = salt_challenge_create();
		}
		if ( is_int( $privacy ) && $privacy > 0 ) {
			$privacy_url = (string) get_permalink( $privacy );
		}
	}

	return array(
		'section' => $section,
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
				'phoneHref'    => $phone_href,
				'email'        => '' !== $email ? $email : null,
				'address'      => $address_lines,
			),
			'has_details'         => $has_details,
			'form_enabled'        => $form_enabled,
			'request_status'      => $request_status,
			'enquiry_types'       => $enquiry_types,
			'form_action'         => $form_action,
			'nonce'               => $nonce,
			'time_token'          => $time_token,
			'page_url'            => $page_url,
			'captcha_widget_html' => $captcha_widget_html,
			'challenge'           => $challenge,
			'privacy_url'         => $privacy_url,
		),
	);
}
