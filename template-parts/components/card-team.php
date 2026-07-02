<?php
/**
 * x-card-team — team-member card (spec §12 card family; Team section, §10 #12).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   name    string  required  Member name (escaped here).
 *   role    string  ''        Role / job title (escaped here).
 *   bio     string  ''        Plain-text short bio (escaped here).
 *   email   string  ''        Email address — rendered as a mailto: link when present.
 *   phone   string  ''        Phone — rendered as a tel: link when present
 *                             (href digits/+ only; the visible text keeps formatting).
 *   media   string  ''        PRE-RENDERED HTML slot (an x-image headshot render).
 *                             Printed raw — the producer escapes at print.
 *   socials array   []        Rows: array{platform: 'linkedin'|'x'|'other', url: string}.
 *                             Unknown platform or empty url ⇒ row skipped + salt_log()ged.
 *                             External hrefs allowed here by assignment (socials);
 *                             rel="noopener noreferrer" is applied.
 *   level   int     3         Name heading level, clamped 1..6 (out of range is
 *                             clamped + salt_log()ged).
 *   classes string  ''        Extra utility classes appended to the class list.
 *   slots   array   []        Reserved by salt_get_component() (unused here).
 *
 * Render via salt_component( 'card-team', … ) / salt_get_component().
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
		'role'    => array(
			'type'    => 'string',
			'default' => '',
		),
		'bio'     => array(
			'type'    => 'string',
			'default' => '',
		),
		'email'   => array(
			'type'    => 'string',
			'default' => '',
		),
		'phone'   => array(
			'type'    => 'string',
			'default' => '',
		),
		'media'   => array(
			'type'    => 'string',
			'default' => '',
		),
		'socials' => array(
			'type'    => 'array',
			'default' => array(),
		),
		'level'   => array(
			'type'    => 'int',
			'default' => 3,
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
	'card-team'
);

$salt_level = (int) $args['level'];
if ( $salt_level < 1 || $salt_level > 6 ) {
	salt_log( sprintf( 'x-card-team: level %d out of range — clamped to 1..6.', $salt_level ) );
	$salt_level = max( 1, min( 6, $salt_level ) );
}

$salt_classes = salt_classnames(
	array(
		'x-card x-card-team group relative flex flex-col overflow-hidden rounded-lg border border-border bg-surface text-ink',
		$args['classes'],
	)
);

$salt_email = trim( $args['email'] );
$salt_phone = trim( $args['phone'] );
$salt_tel   = (string) preg_replace( '/[^0-9+]/', '', $salt_phone );
if ( '' !== $salt_phone && '' === $salt_tel ) {
	salt_log( sprintf( 'x-card-team: phone "%s" yields no dialable tel: href — link skipped.', $salt_phone ) );
}

// Validated social links (unknown platform / empty url ⇒ skipped + logged).
$salt_platform_labels = array(
	'linkedin' => __( 'LinkedIn', 'salt' ),
	'x'        => __( 'X', 'salt' ),
	'other'    => __( 'Website', 'salt' ),
);
$salt_social_links    = array();
foreach ( (array) $args['socials'] as $salt_social ) {
	$salt_platform = is_array( $salt_social ) ? (string) ( $salt_social['platform'] ?? '' ) : '';
	$salt_url      = is_array( $salt_social ) ? trim( (string) ( $salt_social['url'] ?? '' ) ) : '';

	if ( ! isset( $salt_platform_labels[ $salt_platform ] ) ) {
		salt_log( sprintf( 'x-card-team: social row with unknown platform "%s" skipped.', $salt_platform ) );
		continue;
	}
	if ( '' === $salt_url ) {
		salt_log( sprintf( 'x-card-team: social row (%s) without a url skipped.', $salt_platform ) );
		continue;
	}

	$salt_social_links[] = array(
		'label' => $salt_platform_labels[ $salt_platform ],
		'url'   => $salt_url,
	);
}
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
			esc_html( $args['name'] )
		);
		?>
		<?php if ( '' !== trim( $args['role'] ) ) : ?>
		<p class="x-card-meta text-sm text-ink-muted"><?php echo esc_html( $args['role'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== trim( $args['bio'] ) ) : ?>
		<p class="x-card-body text-ink-muted"><?php echo esc_html( $args['bio'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $salt_email || ( '' !== $salt_phone && '' !== $salt_tel ) ) : ?>
		<ul class="x-card-team-contact flex flex-col gap-1 text-sm">
			<?php if ( '' !== $salt_email ) : ?>
			<li><a class="text-brand underline" href="<?php echo esc_url( 'mailto:' . $salt_email ); ?>"><?php echo esc_html( $salt_email ); ?></a></li>
			<?php endif; ?>
			<?php if ( '' !== $salt_phone && '' !== $salt_tel ) : ?>
			<li><a class="text-brand underline" href="<?php echo esc_url( 'tel:' . $salt_tel ); ?>"><?php echo esc_html( $salt_phone ); ?></a></li>
			<?php endif; ?>
		</ul>
		<?php endif; ?>
		<?php if ( array() !== $salt_social_links ) : ?>
		<ul class="x-card-team-socials flex flex-wrap gap-4 text-sm">
			<?php foreach ( $salt_social_links as $salt_link ) : ?>
			<li><a class="text-ink-muted underline" href="<?php echo esc_url( $salt_link['url'] ); ?>" rel="noopener noreferrer"><?php echo esc_html( $salt_link['label'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</div>
</article>
