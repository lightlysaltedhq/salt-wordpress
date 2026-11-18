/**
 * Send the chosen source with a collection query's categories search.
 *
 * With several sources the categories picker is one select of term IDs
 * (salt.taxonomies); inc/fields/collection-query.php answers its search
 * with the terms of the source the layout's source select holds, which
 * this adds to each request as `salt_source`. The source select sits on
 * the layout beside the query group: its key is the picker's with
 * `_query_categories` replaced by `_source`.
 */
const acf = window.acf;

if (acf && typeof acf.addFilter === 'function') {
	acf.addFilter('select2_ajax_data', (data, args, $input, field) => {
		const key = field && typeof field.get === 'function' ? field.get('key') : '';
		if (!/_query_categories$/.test(key)) {
			return data;
		}
		const sourceKey = key.replace(/_query_categories$/, '_source');
		const layout = field.$el.closest('.layout');
		const select = layout.find(`.acf-field[data-key="${sourceKey}"] select`).first();
		return { ...data, salt_source: select.val() || '' };
	});
}
