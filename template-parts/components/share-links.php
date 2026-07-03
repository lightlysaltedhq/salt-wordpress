<?php
/**
 * x-share-links — icon row of social share intents for the current URL.
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   url      string required  Absolute URL to share (escaped here).
 *   title    string ''        Title text passed to services that accept one.
 *   services array  []        Service keys (see salt_share_services());
 *                             empty = the default suite. Unknown keys are
 *                             skipped + salt_log()ged inside salt_share_links().
 *   copy     bool   true      Render the copy-link button. It ships with the
 *                             `hidden` attribute — the share JS module reveals
 *                             and wires it (FAIL02: no dead button without JS).
 *   heading  string ''        Optional label rendered before the row (plain
 *                             text, NOT a heading element — no outline impact).
 *   classes  string ''        Extra utility classes appended to the class list.
 *
 * Share anchors open in a new tab (rel noopener noreferrer); each anchor is
 * icon-only with an aria-label, the icon itself decorative.
 *
 * Empty url renders nothing (logged) — never a dead share row.
 * Render via salt_component( 'share-links', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is WordPress's load_template() template variable.
	$args ?? array(),
	array(
		'url'      => array(
			'type'     => 'string',
			'required' => true,
		),
		'title'    => array(
			'type'    => 'string',
			'default' => '',
		),
		'services' => array(
			'type'    => 'array',
			'default' => array(),
		),
		'copy'     => array(
			'type'    => 'bool',
			'default' => true,
		),
		'heading'  => array(
			'type'    => 'string',
			'default' => '',
		),
		'classes'  => array(
			'type'    => 'string',
			'default' => '',
		),
		'slots'    => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'share-links'
);

$salt_links = salt_share_links( $args['url'], $args['title'], $args['services'] );
if ( array() === $salt_links ) {
	salt_log( 'x-share-links: no resolvable services — rendered nothing.' );
	return;
}

$salt_classes = salt_classnames(
	array(
		'x-share',
		$args['classes'],
	)
);

$salt_button_classes = 'x-share-link inline-flex size-10 items-center justify-center rounded-full border border-border text-ink-muted transition-colors hover:border-brand hover:text-brand focus-visible:text-brand';
?>
<div class="<?php echo esc_attr( $salt_classes ); ?>">
	<?php if ( '' !== trim( $args['heading'] ) ) : ?>
	<p class="x-share-heading mb-3 text-sm font-semibold uppercase tracking-widest text-ink-muted"><?php echo esc_html( $args['heading'] ); ?></p>
	<?php endif; ?>
	<ul class="x-share-list flex flex-wrap gap-2">
		<?php foreach ( $salt_links as $salt_link ) : ?>
		<li>
			<a class="<?php echo esc_attr( $salt_button_classes ); ?>" href="<?php echo esc_url( $salt_link['url'] ); ?>" aria-label="<?php echo esc_attr( $salt_link['label'] ); ?>" target="_blank" rel="noopener noreferrer">
				<?php
				salt_component(
					'icon',
					array(
						'name' => $salt_link['icon'],
						'size' => 'sm',
					)
				);
				?>
			</a>
		</li>
		<?php endforeach; ?>
		<?php if ( $args['copy'] ) : ?>
		<li hidden data-share-copy-item>
			<button type="button" class="<?php echo esc_attr( $salt_button_classes ); ?>" data-share-copy data-share-url="<?php echo esc_url( $args['url'] ); ?>" data-copied-label="<?php echo esc_attr__( 'Link copied', 'salt' ); ?>" aria-label="<?php echo esc_attr__( 'Copy link', 'salt' ); ?>">
				<?php
				salt_component(
					'icon',
					array(
						'name' => 'link',
						'size' => 'sm',
					)
				);
				?>
			</button>
		</li>
		<?php endif; ?>
	</ul>
</div>
