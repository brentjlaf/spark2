# Accessibility module

## Purpose
The accessibility module audits published pages for WCAG compliance and surfaces a prioritized list of issues in the CMS dashboard.

## Key files
- `CMS/modules/accessibility/view.php` renders the dashboard, loads pages, menus, and settings, and generates page HTML snapshots for analysis.
- `CMS/modules/accessibility/accessibility.js` powers filtering, sorting, and score/violation summaries in the UI.
- `CMS/includes/score_history.php` provides deterministic “previous score” comparisons used for trend indicators.

## How it works
1. The view loads page content from `CMS/data/pages.json`, site settings, and menus to determine templates and metadata.
2. Each page is rendered through its template (from `theme/templates/pages`) to capture the final HTML, stripping editor-only markup.
3. The module derives WCAG levels, violation summaries, and actionable recommendations, then renders the dashboard UI.
4. JavaScript handles filtering, sorting, and score delta presentation within the dashboard.

## Data storage
- Page content lives in `CMS/data/pages.json`.
- Menus live in `CMS/data/menus.json`.
- No additional accessibility-specific storage is used; scores are derived on the fly.
