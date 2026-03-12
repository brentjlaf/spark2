# Forms module

## Purpose
The forms module lets administrators build contact and lead forms, manage fields, and export submissions.

## Key files
- `CMS/modules/forms/view.php` renders the form builder and submissions UI.
- `CMS/modules/forms/forms.js` handles the form builder interactions and submission views.
- `CMS/modules/forms/list_forms.php` returns the list of forms.
- `CMS/modules/forms/save_form.php` creates/updates form definitions.
- `CMS/modules/forms/delete_form.php` deletes form definitions.
- `CMS/modules/forms/list_submissions.php` returns stored submissions.
- `CMS/modules/forms/export_submissions.php` exports submissions as CSV.
- `CMS/modules/forms/preview_confirmation_email.php` renders confirmation email previews.

## How it works
1. Form definitions are stored in JSON and loaded into the builder UI.
2. The builder serializes fields and confirmation email settings, posting them to `save_form.php`.
3. Submissions can be reviewed in the dashboard, filtered, and exported via the export endpoint.

## Data storage
- Form definitions: `CMS/data/forms.json`
- Submissions: `CMS/data/form_submissions.json` (read by `list_submissions.php`/`export_submissions.php`).
