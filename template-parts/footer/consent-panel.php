<?php
/**
 * The consent preferences panel (salt-contract markup/site-footer.json,
 * consent-panel): a dialog drawn only when open, so always with open
 * (SC-019). Its ids are fixed (SC-012): site-footer__consent-title, and
 * site-footer__consent-<category> with its -description.
 *
 * Only script opens it on a real page (assets/src/js/app.js builds the same
 * markup from salt_chrome_script_data()); the server draws it open only for
 * the contract's open-panel case. Save writes through the consent plugin's
 * wp_set_consent(); the theme stores nothing (PRIV01, founder ruling C).
 * The form is bare, as the contract's consent-panel-open fixture draws it;
 * script's copy carries method="dialog", so Enter never submits a request.
 *
 * Args: salt_consent_panel() — labels { title, save, cancel } and
 * categories [ { id, label, description } ].
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_panel_labels = (array) ( $args['labels'] ?? array() );
?>
<dialog class="salt-consent-panel" open aria-labelledby="site-footer__consent-title">
	<form>
		<h2 class="salt-consent-panel__title" id="site-footer__consent-title"><?php echo esc_html( (string) ( $salt_panel_labels['title'] ?? '' ) ); ?></h2>
		<?php foreach ( (array) ( $args['categories'] ?? array() ) as $salt_category ) : ?>
			<?php $salt_id = 'site-footer__consent-' . sanitize_key( (string) $salt_category['id'] ); ?>
			<div class="salt-consent-panel__category">
				<input class="salt-consent-panel__checkbox" type="checkbox" id="<?php echo esc_attr( $salt_id ); ?>" name="<?php echo esc_attr( sanitize_key( (string) $salt_category['id'] ) ); ?>" aria-describedby="<?php echo esc_attr( $salt_id . '-description' ); ?>">
				<label for="<?php echo esc_attr( $salt_id ); ?>"><?php echo esc_html( (string) $salt_category['label'] ); ?></label>
				<div class="salt-consent-panel__description" id="<?php echo esc_attr( $salt_id . '-description' ); ?>"><?php echo esc_html( (string) $salt_category['description'] ); ?></div>
			</div>
		<?php endforeach; ?>
		<div class="salt-consent-panel__actions">
			<button class="salt-button" type="submit" data-style="primary"><?php echo esc_html( (string) ( $salt_panel_labels['save'] ?? '' ) ); ?></button>
			<button class="salt-button" type="button" data-style="secondary"><?php echo esc_html( (string) ( $salt_panel_labels['cancel'] ?? '' ) ); ?></button>
		</div>
	</form>
</dialog>
