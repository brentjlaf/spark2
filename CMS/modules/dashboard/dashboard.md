# Dashboard module

## Purpose
The dashboard module provides an at-a-glance operational overview of content, media, users, forms, events, and analytics.

## Key files
- `CMS/modules/dashboard/view.php` renders the dashboard layout and widgets.
- `CMS/modules/dashboard/dashboard.js` requests data and updates the dashboard UI.
- `CMS/modules/dashboard/dashboard_data.php` aggregates metrics and returns a JSON payload for the UI.

## How it works
1. `dashboard_data.php` loads data from core JSON stores (pages, media, users, menus, forms, blog posts, events, and orders).
2. It calculates totals (views, counts, activity) and builds summary insights, caching derived data in `CMS/data/dashboard_cache.json` when possible.
3. `dashboard.js` triggers refreshes and hydrates the dashboard cards with the aggregated metrics.

## Data storage
- Aggregates read from multiple JSON files in `CMS/data/` (pages, media, users, menus, forms, blog posts, events, event orders).
- Dashboard-specific cached summaries live in `CMS/data/dashboard_cache.json`.
