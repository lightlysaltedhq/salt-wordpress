/**
 * Narrow each ACF rich-text editor to its field's contract list.
 *
 * Every TinyMCE starts from the theme's whole allow-list
 * (salt_tinymce_config()). salt_wysiwyg_field_editor() writes the field's
 * own list (`salt.allowed`) onto its wrapper as data attributes; this
 * applies them, so a field offers only the formats, elements and buttons it
 * may store. The sanitiser enforces the same list on output whatever the
 * editor sends (inc/sanitization.php).
 */
const acf = window.acf;

// acf.addFilter, not the legacy acf.add_filter: the legacy one hands the
// callback the field's jQuery element in place of the field model.
if (acf && typeof acf.addFilter === 'function') {
	acf.addFilter('wysiwyg_tinymce_settings', (init, id, field) => {
		const el = field && field.$el ? field.$el[0] : null;
		const data = el ? el.dataset : null;
		if (!data || typeof data.saltValidElements !== 'string') {
			return init;
		}
		return {
			...init,
			block_formats: data.saltBlockFormats,
			valid_elements: data.saltValidElements,
			toolbar1: data.saltToolbar,
			toolbar2: '',
		};
	});
}
