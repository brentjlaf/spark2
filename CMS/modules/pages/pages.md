# Pages module

## Purpose
The pages module provides the CMS page editor, publishing controls, and page scheduling.

## Key files
- `CMS/modules/pages/view.php` renders the page editor UI.
- `CMS/modules/pages/pages.js` handles editing, publishing, and template selection.
- `CMS/modules/pages/list_pages.php` returns page lists with homepage ordering.
- `CMS/modules/pages/save_page.php` validates and persists page content, metadata, and scheduling.
- `CMS/modules/pages/delete_page.php` removes pages and records history.
- `CMS/modules/pages/set_home.php` updates the homepage setting.
- `CMS/modules/pages/backfill_schedule.php` normalizes scheduling metadata for existing pages.

## How it works
1. Page records are loaded from `CMS/data/pages.json` and displayed in the editor.
2. Saving a page writes content, SEO metadata, and publish/unpublish schedules to `save_page.php`.
3. Publishing logic relies on `CMS/includes/page_schedule.php` to determine current state and schedule transitions.
4. The homepage is tracked in site settings and applied when listing pages.

## Data storage
- Pages: `CMS/data/pages.json`.
- Settings (homepage slug): `CMS/data/settings.json` (via settings helpers).
