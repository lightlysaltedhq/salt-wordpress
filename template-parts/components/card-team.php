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
 *   socials array   []        Rows: array{name: string, url: string}, the contract's
 *                             socials (collections/_person.json). The network is
 *                             worked out from the address (salt_social_link(),
 *                             contract/socials.json) and drawn as its bundled icon
 *                             (shared with x-share-links); an address that names no
 *                             network draws the globe. name names the link. A row
 *                             with no usable address is skipped + salt_log()ged.
 *                             External hrefs allowed here by assignment (socials);
 *                             rel="noopener noreferrer".
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

// Social links: the network from the address (contract/socials.json), the
// label from the row's name; a row with no usable address is skipped + logged.
$salt_social_links = array();
foreach ( (array) $args['socials'] as $salt_social ) {
	$salt_url  = is_array( $salt_social ) ? trim( (string) ( $salt_social['url'] ?? '' ) ) : '';
	$salt_name = is_array( $salt_social ) ? trim( (string) ( $salt_social['name'] ?? '' ) ) : '';
	$salt_link = '' === $salt_url ? null : salt_social_link( $salt_url );

	if ( null === $salt_link ) {
		salt_log( sprintf( 'x-card-team: social row (%s) without a usable url skipped.', $salt_name ) );
		continue;
	}

	$salt_social_links[] = array(
		'label' => sprintf(
			/* translators: 1: member name. 2: what the social link is called, such as LinkedIn. */
			__( '%1$s on %2$s', 'salt' ),
			$args['name'],
			'' !== $salt_name ? $salt_name : $salt_link['label']
		),
		'icon'  => '' !== $salt_link['icon'] ? $salt_link['icon'] : 'globe',
		'url'   => $salt_link['url'],
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
		<ul class="x-card-team-socials mt-1 flex flex-wrap gap-2">
			<?php foreach ( $salt_social_links as $salt_link ) : ?>
			<li>
				<a class="x-card-team-social inline-flex size-9 items-center justify-center rounded-full border border-border text-ink-muted transition-colors hover:border-brand hover:text-brand" href="<?php echo esc_url( $salt_link['url'] ); ?>" aria-label="<?php echo esc_attr( $salt_link['label'] ); ?>" rel="noopener noreferrer">
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
		</ul>
		<?php endif; ?>
	</div>
</article>
