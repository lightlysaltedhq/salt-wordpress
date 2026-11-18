/**
 * Refuse a save whose title is over the contract's limit (salt-contract
 * rc.7: a collection's title, name or question, maxLength 120; on WordPress
 * the post title of a service, a case study, a post, a testimonial, a team
 * member, an FAQ or a location).
 *
 * Counts as salt_title_length() does (inc/fields/collections.php): Unicode
 * code points, with a CRLF as one. When the form is submitted to be saved
 * (Publish, Update or Save Draft; not Preview), an over-long title stops the
 * save and the message is shown above the form.
 */
const config = window.saltTitleLimit;
const form = document.getElementById('post');
const title = document.getElementById('title');

/**
 * Whether the title may be saved; if not, show the message.
 *
 * @return {boolean} True when it is within the limit.
 */
function check() {
	const length = Array.from(title.value.replace(/\r\n/g, '\n')).length;
	document.getElementById('salt-title-limit')?.remove();
	if (length <= config.max) {
		return true;
	}
	const notice = document.createElement('div');
	notice.id = 'salt-title-limit';
	notice.className = 'notice notice-error';
	notice.setAttribute('role', 'alert');
	const text = document.createElement('p');
	text.textContent = config.message.replace('{length}', String(length));
	notice.append(text);
	(document.querySelector('.wp-header-end') ?? form).after(notice);
	title.focus();
	return false;
}

/**
 * Stop a save the title refuses, before WordPress, ACF or the browser's
 * own required-field check act on it.
 *
 * @param {Event} event The click or submit.
 */
function guard(event) {
	if (!check()) {
		event.preventDefault();
		event.stopImmediatePropagation();
	}
}

if (config && form && title) {
	// Publish, Update and Save Draft: checked on the click, which comes before the browser's
	// required-field check and ACF's validation. Preview is not a save.
	for (const button of form.querySelectorAll('#publish, #save-post')) {
		button.addEventListener('click', guard, true);
	}
	// A save started any other way (Enter in the title).
	form.addEventListener(
		'submit',
		(event) => {
			const by = event.submitter?.id ?? '';
			if (by !== 'post-preview') {
				guard(event);
			}
		},
		true
	);
}
