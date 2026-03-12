# Speed module

## Purpose
The speed module evaluates page performance characteristics and highlights optimization opportunities.

## Key files
- `CMS/modules/speed/view.php` renders the performance dashboard and analysis summaries.
- `CMS/modules/speed/speed.js` renders charts, score deltas, and issue tables on the client.
- `CMS/includes/score_history.php` provides score delta calculations.

## How it works
1. The view loads pages, settings, and menus, then renders each page through its template to capture HTML.
2. The module estimates performance grades, alerts, and key metrics for each page.
3. Results are displayed in the dashboard UI, with JavaScript enabling sorting and filtering.

## Data storage
- Page content: `CMS/data/pages.json`.
- Menus: `CMS/data/menus.json`.
- No speed-specific storage is used; metrics are derived per request.
