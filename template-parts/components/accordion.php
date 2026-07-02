<?php
/**
 * x-accordion — native <details>/<summary> accordion. NO JavaScript: the
 * open/close behaviour is the platform's; the chevron rotates via a pure-CSS
 * group-open utility; exclusive-open grouping uses the native <details name>
 * attribute.
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   items array  required  Each item: array{
 *                            title:   string  required — items missing a title
 *                                     are skipped + salt_log()ged,
 *                            content: string  Pre-sanitised HTML — the caller
 *                                     MUST pass it through salt_sanitize_html();
 *                                     printed without re-escaping, but this
 *                                     partial defensively re-runs
 *                                     salt_sanitize_html() (cheap, idempotent),
 *                            open:    bool    optional — renders the item open.
 *                          }
 *                          Empty items array renders NOTHING (COMPL02 0-state).
 *   name  string 'accordion' Native <details name> group (exclusive open).
 *
 * Render via salt_component( 'accordion', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is WordPress's load_template() template variable.
	$args ?? array(),
	array(
		'items' => array(
			'type'     => 'array',
			'required' => true,
		),
		'name'  => array(
			'type'    => 'string',
			'default' => 'accordion',
		),
	),
	'accordion'
);

$salt_items = array();
foreach ( $args['items'] as $salt_item ) {
	if ( ! is_array( $salt_item ) || ! is_string( $salt_item['title'] ?? null ) || '' === $salt_item['title'] ) {
		salt_log( 'x-accordion: item missing title — skipped.' );
		continue;
	}
	$salt_items[] = $salt_item;
}

if ( array() === $salt_items ) {
	return;
}
?>
<div class="x-accordion" data-section-component="accordion">
	<?php foreach ( $salt_items as $salt_item ) : ?>
	<details class="x-accordion-item group border-b border-border" name="<?php echo esc_attr( $args['name'] ); ?>"<?php echo ! empty( $salt_item['open'] ) ? ' open' : ''; ?>>
		<summary class="x-accordion-summary list-none flex items-center justify-between gap-4 py-4 font-medium cursor-pointer">
			<?php echo esc_html( $salt_item['title'] ); ?>
			<span class="x-accordion-chevron transition-transform group-open:rotate-180" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
			</span>
		</summary>
		<div class="x-accordion-content pb-4 text-ink-muted">
			<?php echo salt_sanitize_html( (string) ( $salt_item['content'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised via salt_sanitize_html() at the point of output. ?>
		</div>
	</details>
	<?php endforeach; ?>
</div>
