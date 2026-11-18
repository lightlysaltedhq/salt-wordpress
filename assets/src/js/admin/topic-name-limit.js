/**
 * Refuse a topic whose name is over the contract's limit (salt-contract
 * rc.7: contract/collections/_topic.json, the name, maxLength 120; on
 * WordPress the term name of a service, case study, FAQ or location topic).
 *
 * Counts as salt_topic_name_length() does (inc/cpt/topics.php): Unicode
 * code points, with a CRLF as one. Three forms are checked, and an
 * over-long name stops the save and shows the message:
 * - the term add screen (Add new topic), above the screen;
 * - the term edit screen (Update), above the form;
 * - the Topics box's "add new" form on a post edit screen, above that form.
 *   WordPress splits what is typed there at each comma and trims each part
 *   into a topic of its own, so each part is checked as a name.
 */
const config = window.saltTopicNameLimit;

/**
 * A name's length in characters.
 *
 * @param {string} name The name.
 * @return {number} Its length in code points, with a CRLF as one.
 */
const lengthOf = (name) => Array.from(name.replace(/\r\n/g, '\n')).length;

/**
 * Whether the names in a field may be saved; if not, show the message.
 *
 * @param {HTMLInputElement}  field The name field.
 * @param {Function}          names The names the field holds.
 * @param {Function}          place Puts the message on the screen.
 * @return {boolean} True when every name is within the limit.
 */
function check(field, names, place) {
	const over = names(field.value)
		.map(lengthOf)
		.find((length) => length > config.max);
	document.getElementById('salt-topic-name-limit')?.remove();
	if (over === undefined) {
		return true;
	}
	const notice = document.createElement('div');
	notice.id = 'salt-topic-name-limit';
	notice.className = 'notice notice-error';
	notice.setAttribute('role', 'alert');
	const text = document.createElement('p');
	text.textContent = config.message.replace('{length}', String(over));
	notice.append(text);
	place(notice);
	field.focus();
	return false;
}

/**
 * Check a field's names on every way its form is saved, before WordPress's
 * own handlers act on it.
 *
 * @param {Object}           form          The form's parts.
 * @param {HTMLInputElement} form.field    The name field.
 * @param {Element[]}        form.buttons  The buttons that save it.
 * @param {Element|null}     form.element  The form element, when it is submitted as one.
 * @param {boolean}          form.onEnter  Whether Enter in the field saves it by script.
 * @param {Function}         form.names    The names the field holds.
 * @param {Function}         form.place    Puts the message on the screen.
 */
function guard({ field, buttons, element, onEnter, names, place }) {
	const stop = (event) => {
		if (!check(field, names, place)) {
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	};
	for (const button of buttons) {
		button.addEventListener('click', stop, true);
	}
	element?.addEventListener('submit', stop, true);
	if (onEnter) {
		field.addEventListener(
			'keypress',
			(event) => {
				if (event.key === 'Enter') {
					stop(event);
				}
			},
			true
		);
	}
}

const whole = (value) => [value];

if (config) {
	const header = document.querySelector('.wp-header-end');

	// The term add screen.
	const add = document.getElementById('addtag');
	const addName = document.getElementById('tag-name');
	if (add && addName) {
		guard({
			field: addName,
			buttons: add.querySelectorAll('#submit'),
			element: add,
			onEnter: false,
			names: whole,
			place: (notice) => (header ?? add).before(notice),
		});
	}

	// The term edit screen.
	const edit = document.getElementById('edittag');
	const editName = document.getElementById('name');
	if (edit && editName) {
		guard({
			field: editName,
			buttons: edit.querySelectorAll('[type="submit"]'),
			element: edit,
			onEnter: false,
			names: whole,
			place: (notice) => edit.before(notice),
		});
	}

	// The Topics box's "add new" form on a post edit screen.
	for (const taxonomy of config.taxonomies) {
		const field = document.getElementById(`new${taxonomy}`);
		const button = document.getElementById(`${taxonomy}-add-submit`);
		const form = document.getElementById(`${taxonomy}-add`);
		if (field && button && form) {
			guard({
				field,
				buttons: [button],
				element: null,
				onEnter: true,
				names: (value) => value.split(',').map((name) => name.replace(/^[ \t\n\r\0\v]+|[ \t\n\r\0\v]+$/g, '')),
				place: (notice) => form.before(notice),
			});
		}
	}
}
