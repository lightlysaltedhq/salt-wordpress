<?php
/**
 * The search form, as the contract draws it (salt-contract markup/
 * search-form.json): a search landmark, its form to the site's search path
 * with the input named q, which keeps the query (SC-019). A view: it draws
 * its args and nothing else.
 *
 * ARGS:
 *   search array  salt_contract_search(): path, maxLength.
 *   query  string|null The query, or null when none is set.
 *   labels array  labels.landmark, labels.field, labels.submit.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args   = $args ?? array();
$salt_search = (array) ( $salt_args['search'] ?? array() );
$salt_labels = (array) ( $salt_args['labels'] ?? array() );
$salt_query  = $salt_args['query'] ?? null;

printf(
	'<search class="salt-search" aria-label="%1$s"><form class="salt-search__form" action="%2$s" method="get"><label class="salt-search__label" for="search-form__input">%3$s</label><input class="salt-search__input" id="search-form__input" type="search" name="q" maxlength="%4$d" enterkeyhint="search"%5$s><button class="salt-button" type="submit" data-style="primary">%6$s</button></form></search>',
	esc_attr( (string) ( $salt_labels['landmark'] ?? '' ) ),
	esc_url( (string) ( $salt_search['path'] ?? '' ) ),
	esc_html( (string) ( $salt_labels['field'] ?? '' ) ),
	(int) ( $salt_search['maxLength'] ?? 100 ),
	null !== $salt_query ? ' value="' . esc_attr( (string) $salt_query ) . '"' : '',
	esc_html( (string) ( $salt_labels['submit'] ?? '' ) )
);
