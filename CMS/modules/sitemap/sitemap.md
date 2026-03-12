# Sitemap module

## Purpose
The sitemap module generates and previews `sitemap.xml` for published pages.

## Key files
- `CMS/modules/sitemap/view.php` renders the sitemap overview and table.
- `CMS/modules/sitemap/sitemap.js` triggers regeneration and updates the UI.
- `CMS/modules/sitemap/generate.php` builds the XML file and returns summary metadata.

## How it works
1. The view loads published pages from `CMS/data/pages.json` and displays current sitemap entries.
2. Regeneration requests hit `generate.php`, which filters for pages that are live according to scheduling rules.
3. The generator writes `sitemap.xml` at the repo root and returns metadata for the UI.

## Data storage
- Pages: `CMS/data/pages.json`.
- Generated output: `sitemap.xml` at the project root.
