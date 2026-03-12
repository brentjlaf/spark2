# Logs module

## Purpose
The logs module displays historical activity entries such as page edits and system changes.

## Key files
- `CMS/modules/logs/view.php` renders the activity log UI.
- `CMS/modules/logs/logs.js` handles searching and filtering in the UI.
- `CMS/modules/logs/list_logs.php` returns merged log entries as JSON.

## How it works
1. `list_logs.php` loads page history from `CMS/data/page_history.json` and joins it with page titles from `CMS/data/pages.json`.
2. Each entry is normalized with action labels, context, and timestamps, then sorted newest-first.
3. The logs UI fetches the JSON and renders it with filtering and search helpers.

## Data storage
- Activity history: `CMS/data/page_history.json`.
- Page metadata for display: `CMS/data/pages.json`.
