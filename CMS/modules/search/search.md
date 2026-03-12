# Search module

## Purpose
The search module provides unified search across pages, posts, and media within the CMS.

## Key files
- `CMS/modules/search/view.php` renders search results and search history chips.
- `CMS/modules/search/search.js` manages type filters, suggestions, and search history UI.
- `CMS/includes/search_helpers.php` performs the search, scoring, and history tracking.

## How it works
1. The view calls `perform_search` to search pages, posts, and media, then renders the results table.
2. Search history is tracked in the user session via `push_search_history` and displayed for quick access.
3. The JavaScript layer manages suggestion dropdowns and filter persistence.

## Data storage
- Search results are derived from `CMS/data/pages.json`, `CMS/data/blog_posts.json`, and `CMS/data/media.json`.
- Search history is stored in the PHP session for the current user.
