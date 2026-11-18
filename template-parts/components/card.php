<?php
/**
 * x-card — the base card the Phase 2 card family extends.
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   title   string  required  Card title (escaped here).
 *   url     string  ''        When non-empty the title becomes a stretched
 *                             link (after:absolute after:inset-0 on the <a>;
 *                             the <article> is position:relative).
 *   level   int     3         Title heading level, clamped 1..6 (out of range
 *                             is clamped + salt_log()ged).
 *   media   string  ''        PRE-RENDERED HTML slot (e.g. an x-image render).
 *                             Printed raw — the producer escapes at print.
 *   meta    string  ''        PRE-RENDERED HTML slot (e.g. a date/term line).
 *                             Printed raw — the producer escapes at print.
 *   body    string  ''        Plain-text excerpt/summary (escaped here).
 *   body_html string ''       Rich-text body: HTML the caller has ALREADY
 *                             sanitised (salt_sanitize_html()). Drawn in
 *                             place of body when both are given.
 *   classes string  ''        Extra utility classes appended to the class list.
 *
 * Render via salt_component( 'card', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is WordPress's load_template() template variable.
	$args ?? array(),
	array(
		'title'     => array(
			'type'     => 'string',
			'required' => true,
		),
		'url'       => array(
			'type'    => 'string',
			'default' => '',
		),
		'level'     => array(
			'type'    => 'int',
			'default' => 3,
		),
		'media'     => array(
			'type'    => 'string',
			'default' => '',
		),
		'meta'      => array(
			'type'    => 'string',
			'default' => '',
		),
		'body'      => array(
			'type'    => 'string',
			'default' => '',
		),
		'body_html' => array(
			'type'    => 'string',
			'default' => '',
		),
		'classes'   => array(
			'type'    => 'string',
			'default' => '',
		),
	),
	'card'
);

$salt_level = (int) $args['level'];
if ( $salt_level < 1 || $salt_level > 6 ) {
	salt_log( sprintf( 'x-card: level %d out of range — clamped to 1..6.', $salt_level ) );
	$salt_level = max( 1, min( 6, $salt_level ) );
}

$salt_classes = salt_classnames(
	array(
		'x-card group relative flex flex-col overflow-hidden rounded-lg border border-border bg-surface text-ink',
		$args['classes'],
	)
);

$salt_title = '' !== $args['url']
	? sprintf(
		'<a class="after:absolute after:inset-0" href="%s">%s</a>',
		esc_url( $args['url'] ),
		esc_html( $args['title'] )
	)
	: esc_html( $args['title'] );
?>
<article class="<?php echo esc_attr( $salt_classes ); ?>">
	<?php if ( '' !== $args['media'] ) : ?>
	<div class="x-card-media">
		<?php echo $args['media']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered slot HTML; the producing component escapes at print. ?>
	</div>
	<?php endif; ?>
	<div class="x-card-content flex flex-col gap-2 p-6">
		<?php
		printf(
			'<h%1$d class="x-card-title text-lg font-semibold">%2$s</h%1$d>',
			(int) $salt_level,
			$salt_title // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from esc_url()/esc_html() parts.
		);
		?>
		<?php if ( '' !== $args['meta'] ) : ?>
		<div class="x-card-meta text-sm text-ink-muted">
			<?php echo $args['meta']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered slot HTML; the producing component escapes at print. ?>
		</div>
		<?php endif; ?>
		<?php if ( '' !== $args['body_html'] ) : ?>
		<div class="x-card-body x-prose text-ink-muted"><?php echo $args['body_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised by the caller through salt_sanitize_html() (ARGS CONTRACT). ?></div>
		<?php elseif ( '' !== $args['body'] ) : ?>
		<p class="x-card-body text-ink-muted"><?php echo esc_html( $args['body'] ); ?></p>
		<?php endif; ?>
	</div>
</article>
