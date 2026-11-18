<?php
/**
 * x-author-box — post author attribution card (avatar + bio from the
 * WordPress user; the caller resolves both).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   name    string required  Author display name (escaped here).
 *   bio     string ''        Plain-text bio (the WP user description; escaped here).
 *   url     string ''        Author archive URL — the name links when present.
 *   role    string ''        Role line under the name (escaped here).
 *   media   string ''        PRE-RENDERED HTML slot (an x-image render
 *                            of the avatar). Printed raw —
 *                            the producer escapes at print.
 *   classes string ''        Extra utility classes appended to the class list.
 *
 * Empty name renders nothing (contract violation already logged by
 * salt_require_args) — never a hollow attribution card.
 * Render via salt_component( 'author-box', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is WordPress's load_template() template variable.
	$args ?? array(),
	array(
		'name'    => array(
			'type'     => 'string',
			'required' => true,
		),
		'bio'     => array(
			'type'    => 'string',
			'default' => '',
		),
		'url'     => array(
			'type'    => 'string',
			'default' => '',
		),
		'role'    => array(
			'type'    => 'string',
			'default' => '',
		),
		'media'   => array(
			'type'    => 'string',
			'default' => '',
		),
		'classes' => array(
			'type'    => 'string',
			'default' => '',
		),
		'slots'   => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'author-box'
);

if ( '' === trim( $args['name'] ) ) {
	salt_log( 'x-author-box: empty name — rendered nothing.' );
	return;
}

$salt_classes = salt_classnames(
	array(
		'x-author-box flex flex-col gap-4 rounded-lg border border-border bg-surface-alt p-6 sm:flex-row sm:items-start',
		$args['classes'],
	)
);

$salt_name = '' !== trim( $args['url'] )
	? sprintf(
		'<a class="hover:text-brand" href="%s">%s</a>',
		esc_url( $args['url'] ),
		esc_html( $args['name'] )
	)
	: esc_html( $args['name'] );
?>
<aside class="<?php echo esc_attr( $salt_classes ); ?>">
	<?php if ( '' !== $args['media'] ) : ?>
	<div class="x-author-box-media size-16 shrink-0 overflow-hidden rounded-full">
		<?php echo $args['media']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered slot HTML; the producing component escapes at print. ?>
	</div>
	<?php endif; ?>
	<div class="x-author-box-body flex flex-col gap-1">
		<p class="x-author-box-label text-xs font-semibold uppercase tracking-widest text-ink-muted"><?php esc_html_e( 'Written by', 'salt' ); ?></p>
		<p class="x-author-box-name font-semibold"><?php echo $salt_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from esc_url()/esc_html() parts. ?></p>
		<?php if ( '' !== trim( $args['role'] ) ) : ?>
		<p class="x-author-box-role text-sm text-ink-muted"><?php echo esc_html( $args['role'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== trim( $args['bio'] ) ) : ?>
		<p class="x-author-box-bio text-sm text-ink-muted"><?php echo esc_html( $args['bio'] ); ?></p>
		<?php endif; ?>
	</div>
</aside>
