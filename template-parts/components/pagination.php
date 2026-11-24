<?php
/**
 * Pagination, as the contract draws it (salt-contract markup/
 * pagination.json): previous, the window of pages (salt_pagination_window())
 * with a gap for each longer run, and next. Nothing with one page or none.
 * A view: it draws its args and nothing else.
 *
 * ARGS:
 *   current int      The current page, from 1.
 *   total   int      How many pages.
 *   href    callable function( int $page ): string, each page's address.
 *   labels  array    labels.nav, labels.page ({n}), labels.previous, labels.next.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args    = $args ?? array();
$salt_current = (int) ( $salt_args['current'] ?? 1 );
$salt_total   = (int) ( $salt_args['total'] ?? 1 );
$salt_href    = $salt_args['href'] ?? null;
$salt_labels  = (array) ( $salt_args['labels'] ?? array() );
$salt_window  = salt_pagination_window( $salt_current, $salt_total );
if ( array() === $salt_window || ! is_callable( $salt_href ) ) {
	return;
}

$salt_page_label = static function ( int $n ) use ( $salt_labels ): string {
	// {n} replaced where it stands; a label without it is drawn as written (SC-031).
	return salt_view_label( (string) ( $salt_labels['page'] ?? '' ), array( 'n' => (string) $n ) );
};

echo '<nav class="salt-pagination" aria-label="' . esc_attr( (string) ( $salt_labels['nav'] ?? '' ) ) . '"><ul class="salt-pagination__list" role="list">';
if ( $salt_current > 1 ) {
	printf(
		'<li><a class="salt-pagination__link" data-step="previous" rel="prev" href="%1$s">%2$s</a></li>',
		esc_url( (string) $salt_href( $salt_current - 1 ) ),
		esc_html( (string) ( $salt_labels['previous'] ?? '' ) )
	);
}
foreach ( $salt_window as $salt_page ) {
	if ( null === $salt_page ) {
		echo '<li class="salt-pagination__gap" aria-hidden="true">…</li>';
		continue;
	}
	printf(
		'<li><a class="salt-pagination__link" href="%1$s" aria-label="%2$s"%3$s>%4$d</a></li>',
		esc_url( (string) $salt_href( $salt_page ) ),
		esc_attr( $salt_page_label( $salt_page ) ),
		$salt_page === $salt_current ? ' aria-current="page"' : '',
		(int) $salt_page
	);
}
if ( $salt_current < $salt_total ) {
	printf(
		'<li><a class="salt-pagination__link" data-step="next" rel="next" href="%1$s">%2$s</a></li>',
		esc_url( (string) $salt_href( $salt_current + 1 ) ),
		esc_html( (string) ( $salt_labels['next'] ?? '' ) )
	);
}
echo '</ul></nav>';
