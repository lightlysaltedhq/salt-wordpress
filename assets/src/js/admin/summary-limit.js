/**
 * Refuse a save whose summary is over the contract's limit (salt-contract
 * rc.7: summary, maxLength 300; on WordPress the Excerpt box of a service,
 * a case study or a post).
 *
 * Counts as salt_summary_length() does (inc/fields/collections.php):
 * Unicode code points, with a CRLF as one. When the form is submitted to be
 * saved (Publish, Update or Save Draft; not Preview), an over-long summary
 * stops the save and the message is shown above the form.
 */
const config = window.saltSummaryLimit;
const form = document.getElementById('post');
const excerpt = document.getElementById('excerpt');

/**
 * Whether the summary may be saved; if not, show the message.
 *
 * @return {boolean} True when it is within the limit.
 */
function check() {
	const length = Array.from(excerpt.value.replace(/\r\n/g, '\n')).length;
	document.getElementById('salt-summary-limit')?.remove();
	if (length <= config.max) {
		return true;
	}
	const notice = document.createElement('div');
	notice.id = 'salt-summary-limit';
	notice.className = 'notice notice-error';
	notice.setAttribute('role', 'alert');
	const text = document.createElement('p');
	text.textContent = config.message.replace('{length}', String(length));
	notice.append(text);
	(document.querySelector('.wp-header-end') ?? form).after(notice);
	excerpt.focus();
	return false;
}

/**
 * Stop a save the summary refuses, before WordPress, ACF or the browser's
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

if (config && form && excerpt) {
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
