# SEO module

## Purpose
The SEO module evaluates pages for metadata quality and on-page search optimization, presenting scores and recommendations.

## Key files
- `CMS/modules/seo/view.php` builds the SEO analysis data and renders the dashboard.
- `CMS/modules/seo/seo.js` renders score rings, deltas, and issue lists on the client.
- `CMS/includes/score_history.php` provides score delta calculations.

## How it works
1. The view loads pages, settings, and menus, then renders each page through its template to obtain the final HTML.
2. The module analyzes titles, meta descriptions, headings, word counts, Open Graph tags, and canonical URLs.
3. Computed scores, issue lists, and summaries are injected into `window.__SEO_MODULE_DATA__` for `seo.js` to render.

## Data storage
- Page content: `CMS/data/pages.json`.
- Menus: `CMS/data/menus.json`.
- No SEO-specific storage is used; scores are derived on demand.
