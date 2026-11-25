# Hosting requirements for section editing

For Salt 6.0.0, configure the PHP runtime serving WordPress admin (not just CLI):

| Setting | Requirement |
|---|---|
| `max_input_vars` | **At least 20000**. Larger pages or additional plugin fields may need more. PHP's default of 1000 is not sufficient for long section pages. |
| `max_execution_time` | Allow enough time for the largest page's save. Start with **120 seconds or more**, and check the PHP, web-server and proxy timeouts together. The real-ACF fixture page has taken 20–40 seconds locally, exceeding a 30-second limit. |

Ask the host to apply these settings and verify their effective values for web requests.
Salt cannot raise `max_input_vars` at runtime. Test the largest representative page on
staging after changing the limits. The harness uses 20000 inputs and a 600-second time
allowance for its full run; that allowance is not a production performance guarantee.

## What happens if a form is cut short

Salt puts a final hidden completion marker after the classic post editor's fields,
associated with its form through HTML's `form` attribute. Browsers submit successful
controls in document order, including this marker. If PHP discards the end of a form
because it exceeds `max_input_vars`, the marker is missing. Salt refuses the request
with HTTP 400 **before WordPress saves the post, revisions or ACF metadata**. The editor
sees “Salt stopped this save because the editor form was incomplete”, instructions for
the host and a Back link. The protection itself does not need JavaScript or ACF's AJAX
validation and applies to drafts as well as publishing and updating.

Go back to retain the unsaved form, ask the host to raise the limit, then retry. Do not
refresh the error page or close the original editor while relying on unsaved changes.
If the limit is already sufficient, check that the editor finished loading. An editor
opened before the theme update lacks the marker: reload it after preserving unsaved edits.

The guard covers full classic-editor post/page saves (`post.php`, `action=editpost`),
including collection editors. It deliberately refuses custom integrations posting that
action without the completion marker; they must send the complete rendered form or use
WordPress/ACF's PHP APIs instead. It does not cover options forms, custom front-end forms,
direct PHP/REST writes, raw SQL, or make saves transactional against execution timeouts.
Plugins must not move the marker ahead of editable fields or append editable controls
after it. Keep the hosting limits high enough even with the guard installed.

## Existing v5.0.0 sites

The `v5.0.0` source registers the same ACF `sections` flexible-content container on
pages, with no input-completeness guard or `max_input_vars` check. Those sites are
exposed to the same PHP/ACF truncation mechanism when an editor submits more variables
than the host allows. The threshold depends on the layouts and plugins in use; the
6.0.0 fixture count is not a measured v5.0.0 threshold. No live site was inspected.
Until a protected release is installed, have the host raise the limit before re-saving
long pages, and keep a recoverable database backup. Raising the limit does not restore
rows already lost.

The disposable reproduction and negative control are documented in
[the real-ACF harness](../tests/real-acf/README.md).
