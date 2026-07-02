<?php
/**
 * Enquiry delivery: routed email + optional auto-reply (spec §9; SEC11).
 *
 * The endpoint (inc/services/contact.php) validates and hands over the
 * frozen submission array; this module routes it to the configured
 * recipient, optionally confirms to the submitter, and fans out to signed
 * webhooks (inc/services/webhooks.php).
 *
 * Hardening: every user-supplied value is wp_strip_all_tags()'d before it
 * enters an email; anything that lands on a single-line surface (subject)
 * additionally has CR/LF collapsed. NO user input reaches mail headers
 * except Reply-To, which is only set when the submitter address passes
 * is_email() (which rejects CR/LF — header injection impossible, SEC11).
 * Failures are logged WITHOUT payload PII.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read a contact option (ACF options page), null-safe when ACF is absent.
 *
 * @param string $name Field name.
 * @return mixed Value, or null when unset/ACF inactive (DEP01).
 */
function salt_mailer_option( string $name ) {
	if ( ! salt_acf_active() ) {
		return null;
	}

	return salt_option( $name );
}

/**
 * Strip tags and collapse a value onto a single line (subject/label safe).
 *
 * @param string $value Raw value.
 * @return string
 */
function salt_mailer_single_line( string $value ): string {
	return trim( (string) preg_replace( '/[\r\n\t]+/', ' ', wp_strip_all_tags( $value ) ) );
}

/**
 * The recipient for an enquiry type: a matching contact_routes row wins,
 * else the default contact_recipient; '' when nothing valid is configured
 * (the endpoint treats '' as "form not live").
 *
 * @param string $enquiry_type Submitted enquiry type ('' = default route).
 * @return string Recipient email, or '' when none configured.
 */
function salt_contact_recipient( string $enquiry_type ): string {
	$type   = strtolower( trim( $enquiry_type ) );
	$routes = salt_mailer_option( 'contact_routes' );

	if ( '' !== $type && is_array( $routes ) ) {
		foreach ( $routes as $route ) {
			if ( ! is_array( $route ) ) {
				continue;
			}

			$route_type = strtolower( trim( (string) ( $route['enquiry_type'] ?? '' ) ) );
			$recipient  = trim( (string) ( $route['recipient'] ?? '' ) );

			if ( '' !== $route_type && $route_type === $type && is_email( $recipient ) ) {
				return $recipient;
			}
		}
	}

	$fallback = trim( (string) ( salt_mailer_option( 'contact_recipient' ) ?? '' ) );

	return is_email( $fallback ) ? $fallback : '';
}

/**
 * Deliver a validated enquiry: routed email, optional auto-reply, then the
 * 'contact.submitted' webhook fan-out.
 *
 * Frozen submission contract: array{name: string, email: string,
 * message: string, enquiry_type: string, page_url: string,
 * submitted_at: string, ip_hash: string}.
 *
 * @param array<string,string> $submission Validated submission.
 * @return array{sent: bool, errors: string[]} sent reflects the routed
 *                                             email; the endpoint decides
 *                                             the user-facing response.
 */
function salt_contact_deliver( array $submission ): array {
	$errors = array();

	$name         = salt_mailer_single_line( (string) ( $submission['name'] ?? '' ) );
	$email_raw    = trim( (string) ( $submission['email'] ?? '' ) );
	$email        = is_email( $email_raw ) ? $email_raw : '';
	$enquiry_type = salt_mailer_single_line( (string) ( $submission['enquiry_type'] ?? '' ) );
	$message      = trim( wp_strip_all_tags( (string) ( $submission['message'] ?? '' ) ) );
	$page_url     = salt_mailer_single_line( (string) ( $submission['page_url'] ?? '' ) );
	$submitted_at = salt_mailer_single_line( (string) ( $submission['submitted_at'] ?? '' ) );

	$recipient = salt_contact_recipient( $enquiry_type );
	if ( '' === $recipient ) {
		salt_log( 'mailer: no contact recipient configured — enquiry not delivered.' );

		return array(
			'sent'   => false,
			'errors' => array( __( 'No enquiry recipient is configured.', 'salt' ) ),
		);
	}

	$site = salt_mailer_single_line( (string) get_bloginfo( 'name' ) );

	if ( '' !== $enquiry_type ) {
		/* translators: 1: site name. 2: enquiry type. */
		$subject = sprintf( __( '[%1$s] New enquiry: %2$s', 'salt' ), $site, $enquiry_type );
	} else {
		/* translators: %s: site name. */
		$subject = sprintf( __( '[%s] New enquiry', 'salt' ), $site );
	}
	$subject = salt_mailer_single_line( $subject );

	$body = implode(
		"\n",
		array(
			__( 'Name:', 'salt' ) . ' ' . $name,
			__( 'Email:', 'salt' ) . ' ' . salt_mailer_single_line( $email_raw ),
			__( 'Enquiry type:', 'salt' ) . ' ' . $enquiry_type,
			__( 'Page:', 'salt' ) . ' ' . $page_url,
			__( 'Submitted:', 'salt' ) . ' ' . $submitted_at,
			'',
			__( 'Message:', 'salt' ),
			$message,
		)
	);

	// NEVER user input in headers except the is_email()-validated Reply-To.
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( '' !== $email ) {
		$headers[] = 'Reply-To: ' . $email;
	}

	$sent = (bool) wp_mail( $recipient, $subject, $body, $headers );
	if ( ! $sent ) {
		$errors[] = __( 'The enquiry email could not be sent.', 'salt' );
		salt_log( 'mailer: wp_mail failed delivering an enquiry to the configured recipient (payload not logged).' );
	}

	if ( '' !== $email && (bool) salt_mailer_option( 'contact_autoreply' ) ) {
		$errors = array_merge( $errors, salt_mailer_autoreply( $email, $name, $site ) );
	}

	salt_webhook_dispatch( 'contact.submitted', $submission );

	return array(
		'sent'   => $sent,
		'errors' => $errors,
	);
}

/**
 * Send the templated auto-reply to a VALIDATED submitter address.
 *
 * @param string $email Submitter email (already is_email()-validated).
 * @param string $name  Sanitised submitter name.
 * @param string $site  Sanitised site name.
 * @return string[] Errors (empty on success/skip).
 */
function salt_mailer_autoreply( string $email, string $name, string $site ): array {
	$template = trim( (string) ( salt_mailer_option( 'contact_autoreply_body' ) ?? '' ) );
	if ( '' === $template ) {
		salt_log( 'mailer: auto-reply enabled but contact_autoreply_body is empty — auto-reply skipped.' );
		return array();
	}

	$subject = salt_mailer_single_line( (string) ( salt_mailer_option( 'contact_autoreply_subject' ) ?? '' ) );
	if ( '' === $subject ) {
		/* translators: %s: site name. */
		$subject = salt_mailer_single_line( sprintf( __( 'Thanks for your enquiry — %s', 'salt' ), $site ) );
	}

	$body = wp_strip_all_tags(
		str_replace(
			array( '{name}', '{site}' ),
			array( $name, $site ),
			$template
		)
	);

	$sent = (bool) wp_mail( $email, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	if ( ! $sent ) {
		salt_log( 'mailer: wp_mail failed sending the auto-reply (payload not logged).' );
		return array( __( 'The auto-reply email could not be sent.', 'salt' ) );
	}

	return array();
}
