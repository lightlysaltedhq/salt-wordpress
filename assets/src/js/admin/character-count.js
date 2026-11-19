/**
 * A character count beside a field, against the length the contract
 * suggests for it (the founder's ruling of 19/11/2026: targets, never
 * limits). The fields come from salt_character_count()
 * (inc/services/character-count.php) as window.saltCharacterCount, a list
 * of { field, target, marks }, each mark { at, note, strong }, shortest
 * first, the target's own (strong) last.
 *
 * Shows "N / target" as the editor types, counted as salt_character_length()
 * counts: Unicode code points, with a CRLF as one. Past a mark it shows that
 * mark's note: a softer note in amber, the target's in red and bold. It
 * never stops a save.
 */

/**
 * A text's length in characters.
 *
 * @param {string} text The text.
 * @return {number} Its length in code points, with a CRLF as one.
 */
const lengthOf = (text) => Array.from(text.replace(/\r\n/g, '\n')).length;

/**
 * The furthest mark a length is past, or null when it is within them all.
 *
 * @param {number}   length The length.
 * @param {Object[]} marks  The field's marks, shortest first.
 * @return {Object|null} The mark.
 */
const passed = (length, marks) => marks.filter((mark) => length > mark.at).pop() ?? null;

/**
 * Draw a field's count and keep it up to date.
 *
 * @param {Element} field Element beside which to show the count.
 * @param {Object} config The field's { field, target, marks }.
 * @param {Function} measure Current text length or list size.
 * @return {Function} Redraws the count.
 */
function mount(field, config, measure) {
	const count = document.createElement('p');
	count.id = `salt-character-count-${config.field}`;
	count.className = 'salt-character-count';
	count.style.margin = '4px 0 0';
	const number = document.createElement('span');
	number.className = 'salt-character-count__number';
	const message = document.createElement('span');
	message.className = 'salt-character-count__message';
	message.setAttribute('role', 'status');
	count.append(number, ' ', message);
	field.after(count);
	field.setAttribute('aria-describedby', `${field.getAttribute('aria-describedby') ?? ''} ${count.id}`.trim());

	const draw = () => {
		const length = measure();
		const mark = passed(length, config.marks);
		number.textContent = `${length} / ${config.target}`;
		message.textContent = mark?.note ?? '';
		count.dataset.mark = mark === null ? '' : String(mark.at);
		count.classList.toggle('is-over', mark?.strong === true);
		// WordPress's description grey; a dark amber (its warning hue, dark enough for text)
		// for a softer note; its error red past the target.
		count.style.color = mark === null ? '#646970' : mark.strong ? '#d63638' : '#8a5a00';
		count.style.fontWeight = mark?.strong ? '600' : '';
	};
	draw();
	return draw;
}

for (const config of window.saltCharacterCount ?? []) {
	const field = document.getElementById(config.field);
	if (!field) continue;
	const draw = mount(field, config, () => lengthOf(field.value));
	field.addEventListener('input', draw);
	field.addEventListener('change', draw);
	if (field.id === 'display_name') {
		const tick = document.querySelector('[data-key="field_salt_collection_team_show_on_website"] input[type="checkbox"]');
		const visible = () => { field.nextElementSibling.hidden = !tick?.checked; draw(); };
		tick?.addEventListener('change', visible);
		new MutationObserver(draw).observe(field, {childList: true, subtree: true});
		visible();
	}
	if (field.id === 'tag-name') window.jQuery?.(document).on('ajaxComplete', draw);
}

// Only mount real fields, not ACF's templates. Duplicating a populated row copies
// its markup but not its listeners, so replace the copied count on that instance.
const mounted = new WeakSet();
let serial = 0;
function mountAcf(wrapper) {
	if (mounted.has(wrapper) || wrapper.closest('.acf-clone, .clones')) return;
	const input = wrapper.querySelector(':scope > .acf-input') ?? wrapper; // Table-layout repeaters use a td.
	const list = {
		repeater: ['.acf-repeater > table > tbody', ':scope > .acf-row:not(.acf-clone)'],
		gallery: ['.acf-gallery-attachments', ':scope > .acf-gallery-attachment'],
		relationship: ['.acf-relationship .values .list', ':scope > li'],
	}[wrapper.dataset.type];
	const field = input.querySelector(list ? list[0] : 'textarea, input[type="text"]');
	if (!field) return;
	mounted.add(wrapper);
	const target = Number(wrapper.dataset.saltTarget);
	const config = {field: `acf-${++serial}`, target, marks: [{at: target, strong: true, note: wrapper.dataset.saltOver}]};
	// Draw outside a table/list, never amongst its rows.
	const anchor = list ? input.firstElementChild : field;
	if (anchor.nextElementSibling?.classList.contains('salt-character-count')) {
		const copied = anchor.nextElementSibling;
		anchor.setAttribute('aria-describedby', (anchor.getAttribute('aria-describedby') ?? '').split(' ').filter((id) => id !== copied.id).join(' '));
		copied.remove();
	}
	const draw = mount(anchor, config, list ? () => field.querySelectorAll(list[1]).length : () => lengthOf(field.value));
	if (list) new MutationObserver(draw).observe(field, {childList: true});
	else field.addEventListener('input', draw);
}
function scan(root) {
	if (root.matches?.('[data-salt-target]')) mountAcf(root);
	root.querySelectorAll('[data-salt-target]').forEach(mountAcf);
}
scan(document);
window.acf?.addAction('append', ($el) => $el.each((_, el) => scan(el)));
// Native constraint validation (for example a number above max) must not intercept Save Draft.
document.getElementById('save-post')?.setAttribute('formnovalidate', '');
