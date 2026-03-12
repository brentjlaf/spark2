# Maps module

## Purpose
The maps module manages location listings, categories, and geocoded coordinates for map embeds.

## Key files
- `CMS/modules/maps/view.php` renders the map management dashboard.
- `CMS/modules/maps/maps.js` handles UI interactions, filtering, and form submissions.
- `CMS/modules/maps/api.php` serves JSON actions for locations and categories (list, save, delete, reorder, geocode).
- `CMS/modules/maps/helpers.php` provides storage helpers, slug generation, and defaults.

## How it works
1. `helpers.php` ensures storage JSON files exist and seeds default categories.
2. `api.php` routes `action` requests for locations and categories, returning JSON payloads for the UI.
3. The JavaScript layer fetches the overview, renders location cards, and submits edits back to the API.

## Data storage
- Locations: `CMS/data/map_locations.json`
- Categories: `CMS/data/map_categories.json`
