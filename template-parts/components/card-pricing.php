<?php
/**
 * x-card-pricing — pricing plan card (card family, §12).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   name     string  required  Plan name (escaped here).
 *   price    string  ''        Display price, e.g. "£29" — a DISPLAY STRING,
 *                              not structured data (no Offer/PriceSpecification
 *                              schema in v1; deferred).
 *   period   string  ''        Billing period, e.g. "/month". Rendered in the
 *                              same paragraph directly after the price so
 *                              screen readers announce one continuous
 *                              "£29 /month" phrase — no aria-hidden splitting.
 *   features array   array()   Plain-text feature strings. Non-string/empty
 *                              entries are skipped and salt_log()ged.
 *   cta      array   array()   array{ label: string, url: string }. Missing
 *                              label/url => no button (logged when partial).
 *   featured bool    false     Adds the border-brand ring + the translated
 *                              'Most popular' badge; the CTA promotes to the
 *                              primary button style.
 *   level    int     3         Plan-name heading level, clamped 1..6 (out of
 *                              range is clamped + salt_log()ged).
 *   classes  string  ''        Extra utility classes appended to the class list.
 *
 * Empty name renders nothing (logged) — never a hollow card.
 * Render via salt_component( 'card-pricing', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is WordPress's load_template() template variable.
	$args ?? array(),
	array(
		'name'     => array(
			'type'     => 'string',
			'required' => true,
		),
		'price'    => array(
			'type'    => 'string',
			'default' => '',
		),
		'period'   => array(
			'type'    => 'string',
			'default' => '',
		),
		'features' => array(
			'type'    => 'array',
			'default' => array(),
		),
		'cta'      => array(
			'type'    => 'array',
			'default' => array(),
		),
		'featured' => array(
			'type'    => 'bool',
			'default' => false,
		),
		'level'    => array(
			'type'    => 'int',
			'default' => 3,
		),
		'classes'  => array(
			'type'    => 'string',
			'default' => '',
		),
	),
	'card-pricing'
);

if ( '' === trim( $args['name'] ) ) {
	salt_log( 'x-card-pricing: empty name — rendered nothing.' );
	return;
}

$salt_level = (int) $args['level'];
if ( $salt_level < 1 || $salt_level > 6 ) {
	salt_log( sprintf( 'x-card-pricing: level %d out of range — clamped to 1..6.', $salt_level ) );
	$salt_level = max( 1, min( 6, $salt_level ) );
}

// Keep only well-formed, non-empty feature strings (logged — COMPL02).
$salt_features = array();
foreach ( $args['features'] as $salt_i => $salt_feature ) {
	if ( ! is_string( $salt_feature ) || '' === trim( $salt_feature ) ) {
		salt_log( sprintf( 'x-card-pricing: feature %d is not a non-empty string — skipped.', (int) $salt_i ) );
		continue;
	}
	$salt_features[] = trim( $salt_feature );
}

$salt_cta_label = is_string( $args['cta']['label'] ?? null ) ? trim( $args['cta']['label'] ) : '';
$salt_cta_url   = is_string( $args['cta']['url'] ?? null ) ? trim( $args['cta']['url'] ) : '';
$salt_has_cta   = '' !== $salt_cta_label && '' !== $salt_cta_url;
if ( ! $salt_has_cta && ( '' !== $salt_cta_label || '' !== $salt_cta_url ) ) {
	salt_log( 'x-card-pricing: CTA is missing a label or URL — button not rendered.' );
}

$salt_classes = salt_classnames(
	array(
		'x-card-pricing relative flex h-full flex-col gap-4 rounded-lg bg-surface p-6 text-ink',
		$args['featured']
			? 'x-card-pricing-featured border-2 border-brand'
			: 'border border-border',
		$args['classes'],
	)
);
?>
<article class="<?php echo esc_attr( $salt_classes ); ?>">
	<?php if ( $args['featured'] ) : ?>
	<span class="x-card-pricing-badge absolute -top-3 end-6 rounded-full bg-brand px-3 py-1 text-sm font-medium text-on-brand"><?php echo esc_html( __( 'Most popular', 'salt' ) ); ?></span>
	<?php endif; ?>
	<?php
	printf(
		'<h%1$d class="x-card-pricing-name text-lg font-semibold">%2$s</h%1$d>',
		(int) $salt_level,
		esc_html( $args['name'] )
	);
	?>
	<?php if ( '' !== trim( $args['price'] ) ) : ?>
	<p class="x-card-pricing-price">
		<span class="x-card-pricing-amount text-4xl font-bold tracking-tight"><?php echo esc_html( $args['price'] ); ?></span>
		<?php if ( '' !== trim( $args['period'] ) ) : ?>
		<span class="x-card-pricing-period text-sm text-ink-muted"><?php echo esc_html( $args['period'] ); ?></span>
		<?php endif; ?>
	</p>
	<?php endif; ?>
	<?php if ( array() !== $salt_features ) : ?>
	<ul role="list" class="x-card-pricing-features flex flex-col gap-2">
		<?php foreach ( $salt_features as $salt_feature ) : ?>
		<li class="flex items-start gap-2">
			<span class="mt-0.5 shrink-0 text-brand">
				<?php
				salt_component(
					'icon',
					array(
						'name' => 'check',
						'size' => 'sm',
					)
				);
				?>
			</span>
			<span><?php echo esc_html( $salt_feature ); ?></span>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>
	<?php if ( $salt_has_cta ) : ?>
	<div class="x-card-pricing-cta mt-auto pt-2">
		<?php
		salt_component(
			'button',
			array(
				'label'   => $salt_cta_label,
				'url'     => $salt_cta_url,
				'style'   => $args['featured'] ? 'primary' : 'secondary',
				'classes' => 'w-full',
			)
		);
		?>
	</div>
	<?php endif; ?>
</article>
