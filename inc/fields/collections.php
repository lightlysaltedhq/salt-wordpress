<?php
/**
 * The collection field groups, generated from salt-contract.
 *
 * One group per collection the theme registers (services, case studies,
 * testimonials, posts, team, FAQs, locations), located on its post type,
 * exactly as inc/fields/generated/collections.json holds it
 * (salt_generated_collection_groups()). A group whose post type belongs to
 * a module that is switched off is not registered (§10b). What WordPress
 * stores in its own place (the title, body, excerpt, featured image,
 * categories and date) has no field here: each group names it under
 * `salt.native`, and the readers take it from WordPress.
 *
 * The options pages and the user profile group stay hand-written.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the generated collection field groups.
 *
 * @return void
 */
function salt_register_collection_fields(): void {
	$modules = array_flip( salt_module_cpts() );

	foreach ( salt_generated_collection_groups() as $group ) {
		$module = $modules[ salt_collection_group_post_type( $group ) ] ?? null;
		if ( null !== $module && ! salt_module_enabled( $module ) ) {
			continue;
		}
		salt_register_field_group( $group );
	}
}
add_action( 'acf/init', 'salt_register_collection_fields' );

/**
 * The longest summary, in characters (salt-contract rc.7: summary,
 * maxLength 300, on services, case studies and posts). WordPress keeps the
 * summary in the excerpt (salt.native), so no ACF field carries the limit
 * and the theme checks it on save.
 */
const SALT_SUMMARY_MAX_LENGTH = 300;

/**
 * The post types whose summary is the excerpt with a 300-character check
 * (the rc.7 owes on services.json, case-studies.json and posts.json).
 *
 * @return string[]
 */
function salt_summary_post_types(): array {
	return array( 'service', 'work', 'post' );
}

/**
 * A summary's length in characters.
 *
 * Counted in Unicode code points (mb_strlen), with a CRLF line break as
 * one character, so "é" is one character, not two bytes (the founder's
 * ruling on PR #26; the same as ACF counts its own maxlength limits).
 *
 * @param string $summary The excerpt, unslashed.
 * @return int
 */
function salt_summary_length( string $summary ): int {
	return mb_strlen( str_replace( "\r\n", "\n", $summary ) );
}

/**
 * The editor's message for a summary of a given length.
 *
 * @param string $length The length, or the script's {length} placeholder.
 * @return string
 */
function salt_summary_message( string $length ): string {
	return sprintf(
		/* translators: 1: the summary's length in characters. 2: the most it may hold. */
		__( 'The summary (the Excerpt box) is %1$s characters long. Shorten it to %2$d characters or fewer.', 'salt' ),
		$length,
		SALT_SUMMARY_MAX_LENGTH
	);
}

/**
 * The editor's message for a summary over the limit, or null.
 *
 * @param string $summary The excerpt, unslashed.
 * @return string|null
 */
function salt_summary_error( string $summary ): ?string {
	$length = salt_summary_length( $summary );
	return $length > SALT_SUMMARY_MAX_LENGTH ? salt_summary_message( (string) $length ) : null;
}

/**
 * Refuse, in the editor, a save whose summary is over the limit
 * ('admin_enqueue_scripts' on a service, case study or post edit screen).
 *
 * admin/summary-limit.js checks the Excerpt box when the form is submitted
 * (Publish, Update or Save Draft), counting as salt_summary_length() does,
 * and if it is over the limit stops the save and shows the message above
 * the form. It is the theme's own check, not ACF's: the excerpt is not an
 * ACF field, so ACF's validation never sees it.
 *
 * @param string $hook_suffix The admin screen.
 * @return void
 */
function salt_summary_limit_enqueue( string $hook_suffix ): void {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! in_array( (string) ( $GLOBALS['typenow'] ?? '' ), salt_summary_post_types(), true ) ) {
		return;
	}
	$src = salt_asset_optional( 'admin/summary-limit.js' );
	if ( null === $src ) {
		salt_log( 'salt_summary_limit: no manifest entry for "admin/summary-limit.js" — the summary is not checked on save. Re-run the asset build.' );
		return;
	}
	wp_enqueue_script(
		'salt-summary-limit',
		$src,
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Content hash in the dist filename is the cache-buster (ARCH03).
		array( 'in_footer' => true )
	);
	wp_localize_script(
		'salt-summary-limit',
		'saltSummaryLimit',
		array(
			'max'     => SALT_SUMMARY_MAX_LENGTH,
			'message' => salt_summary_message( '{length}' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'salt_summary_limit_enqueue' );

/**
 * The longest collection title, in characters (salt-contract rc.7: the
 * title, name or question of every collection, maxLength 120; the owes'
 * "120-character limit"). WordPress keeps it in the post title
 * (salt.native), so no ACF field carries the limit and the theme checks it
 * in the editor.
 */
const SALT_TITLE_MAX_LENGTH = 120;

/**
 * The post types whose title is a collection's title, name or question,
 * with the 120-character check (the founder's ruling on PR #26): services,
 * case studies, posts, testimonials (name), team members (name), FAQs
 * (question) and locations (name).
 *
 * @return string[]
 */
function salt_title_post_types(): array {
	return array( 'service', 'work', 'post', 'testimonial', 'team_member', 'faq', 'location' );
}

/**
 * A title's length in characters, counted as a summary is
 * (salt_summary_length()): Unicode code points, a CRLF as one.
 *
 * @param string $title The title, unslashed.
 * @return int
 */
function salt_title_length( string $title ): int {
	return salt_summary_length( $title );
}

/**
 * The editor's message for a title of a given length.
 *
 * @param string $length The length, or a placeholder for the script.
 * @return string
 */
function salt_title_message( string $length ): string {
	return sprintf(
		/* translators: 1: the title's length in characters. 2: the most it may hold. */
		__( 'The title is %1$s characters long. Shorten it to %2$d characters or fewer.', 'salt' ),
		$length,
		SALT_TITLE_MAX_LENGTH
	);
}

/**
 * The editor's message for a title over the limit, or null.
 *
 * @param string $title The title, unslashed.
 * @return string|null
 */
function salt_title_error( string $title ): ?string {
	$length = salt_title_length( $title );
	return $length > SALT_TITLE_MAX_LENGTH ? salt_title_message( (string) $length ) : null;
}

/**
 * Refuse, in the editor, a save whose title is over the limit
 * ('admin_enqueue_scripts' on the edit screen of a collection's post type).
 *
 * admin/title-limit.js checks the title when the form is submitted
 * (Publish, Update or Save Draft), counting as salt_title_length() does,
 * and if it is over the limit stops the save and shows the message above
 * the form: the same way as the summary (salt_summary_limit_enqueue()).
 *
 * @param string $hook_suffix The admin screen.
 * @return void
 */
function salt_title_limit_enqueue( string $hook_suffix ): void {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! in_array( (string) ( $GLOBALS['typenow'] ?? '' ), salt_title_post_types(), true ) ) {
		return;
	}
	$src = salt_asset_optional( 'admin/title-limit.js' );
	if ( null === $src ) {
		salt_log( 'salt_title_limit: no manifest entry for "admin/title-limit.js" — the title is not checked on save. Re-run the asset build.' );
		return;
	}
	wp_enqueue_script(
		'salt-title-limit',
		$src,
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Content hash in the dist filename is the cache-buster (ARCH03).
		array( 'in_footer' => true )
	);
	wp_localize_script(
		'salt-title-limit',
		'saltTitleLimit',
		array(
			'max'     => SALT_TITLE_MAX_LENGTH,
			'message' => salt_title_message( '{length}' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'salt_title_limit_enqueue' );
