<?php
/**
 * Not found, as the contract draws it (salt-contract rc.8
 * markup/not-found.json): the title as the document's h1, the message, the
 * search form when search is offered (salt_contract_search()) and a link
 * home when the site has a home page (salt_contract_home()), in that order.
 *
 * @package Salt
 */

get_header();

$salt_labels = salt_contract_labels( 'not-found' );
$salt_search = salt_contract_search();
$salt_home   = salt_contract_home();

salt_the_document_h1( (string) $salt_labels['notFound'] );
echo '<p>' . esc_html( (string) $salt_labels['message'] ) . '</p>';
if ( null !== $salt_search ) {
	salt_component(
		'search-form',
		array(
			'search' => $salt_search,
			'query'  => null,
			'labels' => $salt_labels,
		)
	);
}
if ( null !== $salt_home ) {
	echo '<a href="' . esc_url( $salt_home['href'] ) . '">' . esc_html( (string) $salt_labels['home'] ) . '</a>';
}

get_footer();
