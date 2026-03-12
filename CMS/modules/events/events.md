# Events module

## Purpose
The events module manages event listings, ticketing data, and registrations, along with operational reporting.

## Key files
- `CMS/modules/events/view.php` renders the events dashboard UI.
- `CMS/modules/events/events.js` drives filtering, editing, and reporting interactions.
- `CMS/modules/events/api.php` serves as the JSON API for listing, saving, deleting, and reporting on events and orders.
- `CMS/modules/events/helpers.php` provides storage helpers, slug generation, and data normalization utilities.

## How it works
1. `helpers.php` ensures event storage JSON files exist and provides helpers for categories, forms, and slug generation.
2. `api.php` routes actions (list events, save event, list orders, export orders, etc.) based on `action` query parameters.
3. The UI calls the API endpoints via `events.js`, then renders dashboards, tables, and detail panels.

## Data storage
- Events: `CMS/data/events.json`
- Orders: `CMS/data/event_orders.json`
- Categories: `CMS/data/event_categories.json`
- Forms: `CMS/data/event_forms.json`
