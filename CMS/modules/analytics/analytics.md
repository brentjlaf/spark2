# Analytics module

## Purpose
The analytics module surfaces traffic summaries, top pages, and low-performing content in the CMS dashboard.

## Key files
- `CMS/modules/analytics/view.php` renders the dashboard UI and summarizes the analytics dataset.
- `CMS/modules/analytics/analytics_data.php` returns the analytics dataset as JSON for refreshes.
- `CMS/modules/analytics/export.php` exports the dataset as CSV.
- `CMS/modules/analytics/analytics.js` wires refresh/export actions and updates the UI.
- `CMS/includes/analytics.php` and `CMS/includes/analytics_provider.php` load and normalize the dataset.

## How it works
1. The view loads the analytics dataset (entries, meta, trends) and computes summaries like total views and zero-view pages.
2. The module displays high-level KPIs and precomputed lists (top pages, low-view pages, zero-view pages).
3. The JavaScript layer triggers refreshes via `analytics_data.php` and initiates CSV exports via `export.php`.

## Data storage
- Analytics data is loaded via `CMS/includes/analytics.php` (which may read a local dataset or a provider-backed source). The module itself does not persist new analytics records.
