# Menus module

## Purpose
The menus module lets administrators build navigation menus that can be embedded in themes.

## Key files
- `CMS/modules/menus/view.php` renders the menu builder UI.
- `CMS/modules/menus/menus.js` handles drag-and-drop ordering and nested items.
- `CMS/modules/menus/list_menus.php` returns menus as JSON.
- `CMS/modules/menus/save_menu.php` saves menu definitions, resolving page links.
- `CMS/modules/menus/delete_menu.php` deletes menu definitions.

## How it works
1. The menu builder loads existing menus from `CMS/data/menus.json`.
2. Editors add page links or custom URLs; `save_menu.php` resolves page IDs to slugs and stores nested menu items.
3. The UI persists changes by calling `save_menu.php` and refreshes from `list_menus.php`.

## Data storage
- Menus: `CMS/data/menus.json`.
- Page metadata for link resolution: `CMS/data/pages.json`.
