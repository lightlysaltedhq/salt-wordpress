<?php
/**
 * One step of the Process section, as the contract draws it (salt-contract
 * markup/process-step.json, rc.8): li.salt-process__step with its number,
 * an optional icon and image, the title, the text and a secondary button.
 *
 * ARGS:
 *   number   string       required  Position from 1. aria-hidden: the parent
 *                                   <ol> carries the numbering (A11Y01).
 *   title    string       required  Step title. A step without one draws nothing.
 *   text     string|null  null      Step text.
 *   icon     string|null  null      A bundled icon name (salt_contract_icon(), lg).
 *   image    array|null   null      salt_image_contract().
 *   priority bool         false     The image is the page's priority image.
 *   button   array|null   null      The contract's button props (contract-button).
 *   arrow    string|null  null      The site's arrow, for the button.
 *   level    int          3         Title heading level: the body base + 1.
 *
 * Render via salt_component( 'process-step', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is WordPress's load_template() template variable.
	$args ?? array(),
	array(
		'number'   => array(
			'type'     => 'string',
			'required' => true,
		),
		'title'    => array(
			'type'     => 'string',
			'required' => true,
		),
		'text'     => array(
			'type'    => 'mixed',
			'default' => null,
		),
		'icon'     => array(
			'type'    => 'mixed',
			'default' => null,
		),
		'image'    => array(
			'type'    => 'mixed',
			'default' => null,
		),
		'priority' => array(
			'type'    => 'bool',
			'default' => false,
		),
		'button'   => array(
			'type'    => 'mixed',
			'default' => null,
		),
		'arrow'    => array(
			'type'    => 'mixed',
			'default' => null,
		),
		'level'    => array(
			'type'    => 'int',
			'default' => 3,
		),
	),
	'process-step'
);

if ( '' === trim( $args['title'] ) ) {
	return; // markup/process-step.json zeroState: not drawn without a title.
}

$salt_level = max( 1, min( 6, (int) $args['level'] ) );

echo '<li class="salt-process__step">';
echo '<span class="salt-process__number" aria-hidden="true">' . esc_html( $args['number'] ) . '</span>';
if ( is_string( $args['icon'] ) && '' !== trim( $args['icon'] ) ) {
	echo salt_contract_icon( $args['icon'], 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bundled artwork; attributes escaped in salt_contract_icon().
}
if ( is_array( $args['image'] ) ) {
	echo salt_image_contract_html( $args['image'], $args['priority'], array( 'class' => 'salt-process__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute by attribute.
}
printf( '<h%1$d>%2$s</h%1$d>', (int) $salt_level, esc_html( $args['title'] ) );
if ( is_string( $args['text'] ) && '' !== trim( $args['text'] ) ) {
	echo '<p class="salt-process__text">' . esc_html( $args['text'] ) . '</p>';
}
if ( is_array( $args['button'] ) ) {
	salt_component(
		'contract-button',
		array(
			'button' => $args['button'],
			'arrow'  => is_string( $args['arrow'] ) ? $args['arrow'] : null,
		)
	);
}
echo '</li>';
