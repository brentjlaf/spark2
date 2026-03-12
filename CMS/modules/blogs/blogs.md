# Blogs module

## Purpose
The blogs module manages blog posts, categories, and publishing workflows from the CMS dashboard.

## Key files
- `CMS/modules/blogs/view.php` renders the editorial dashboard and post list UI.
- `CMS/modules/blogs/blogs.js` handles filtering, sorting, editing, and status updates.
- `CMS/modules/blogs/list_posts.php` returns all posts as JSON.
- `CMS/modules/blogs/save_post.php` creates/updates posts and validates fields.
- `CMS/modules/blogs/delete_post.php` removes posts by ID.
- `CMS/modules/blogs/publish_post.php` updates post status and publish timestamps.
- `CMS/modules/blogs/list_categories.php` derives categories from existing posts.

## How it works
1. The dashboard loads existing posts from `CMS/data/blog_posts.json` and displays them in a sortable table.
2. Editing and creation flows are managed in `blogs.js`, which submits JSON payloads to `save_post.php`.
3. Status changes (draft/published/scheduled) are handled via `publish_post.php`, while deletions use `delete_post.php`.
4. Categories are derived on demand by scanning existing posts.

## Data storage
- Blog posts are stored in `CMS/data/blog_posts.json`.
