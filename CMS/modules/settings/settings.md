# Settings module

## Purpose
The settings module manages global site configuration, branding assets, analytics tags, and payments configuration.

## Key files
- `CMS/modules/settings/view.php` renders the settings form UI.
- `CMS/modules/settings/settings.js` handles form submission and live previews.
- `CMS/modules/settings/list_settings.php` returns settings as JSON.
- `CMS/modules/settings/save_settings.php` validates and persists settings and uploads assets.
- `CMS/includes/settings.php` defines settings defaults and storage helpers.

## How it works
1. The settings UI loads current values via `list_settings.php`.
2. Submissions are posted to `save_settings.php`, which sanitizes text fields, handles uploads (logo, favicon, OG image), and stores configuration.
3. File uploads are validated for type and size before being moved into `uploads/` and recorded in settings.

## Data storage
- Settings JSON: `CMS/data/settings.json` (path resolved via `get_settings_file_path`).
- Uploaded assets: `uploads/`.
