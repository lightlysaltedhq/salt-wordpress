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
 * @param {Object} config The field's { field, target, marks }.
 * @return {Function|null} Redraws the count; null when the field is not on the screen.
 */
function mount(config) {
	const field = document.getElementById(config.field);
	if (!field) {
		return null;
	}
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
		const length = lengthOf(field.value);
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
	field.addEventListener('input', draw);
	draw();
	return draw;
}

const draws = (window.saltCharacterCount ?? []).map(mount).filter(Boolean);

// The term add screen empties its form by script after a topic is added, which fires no input event.
window.jQuery?.(document).on('ajaxComplete', () => draws.forEach((draw) => draw()));
