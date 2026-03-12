# Persistence migration checklist

## Running migrations
1. Provision MySQL with credentials exported as `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET` (defaults exist in `CMS/includes/db.php`).
2. From the project root, run `php CMS/scripts/migrate_json_to_db.php` to:
   - Back up all `CMS/data/*.json` payload files to a timestamped `backup-*` directory.
   - Create the mapped tables defined in `CMS/includes/schema.php` with indexes on slugs, statuses, and timestamps.
   - Import current payload rows into MySQL, preserving IDs.
3. Verify data by loading the admin dashboard and checking menus, pages, posts, media, events, and settings.

## Manual regression test plan
- **Authentication**: Sign in with the admin user, ensure `last_login` updates in MySQL (`cms_users`). Create an additional user and re-login.
- **Pages/Menus**: Create/edit a page, verify publish status and slug queries work. Ensure menu items render against the new data source.
- **Blog posts**: Publish/unpublish posts and confirm filtering by status/publish date still works.
- **Media**: Upload, tag, rename, move, and delete media entries; confirm folders and ordering persist.
- **Settings**: Change site name/tagline/homepage and confirm values are stored in `cms_settings`.
- **Forms**: Submit a form and confirm `cms_form_submissions` records include metadata. Export submissions.
- **Events**: Create events, orders, and categories; verify relationships (orders list only their event).
- **Logs & history**: Save page versions and check `cms_page_history` entries remain accessible.

## Deployment/rollback
- Deploy environment variables and `CMS/includes/db.php` before switching traffic.
- Run the migration script once per environment; it is idempotent because each import truncates tables before reloading payload data.
- Keep the backups created in `CMS/data/backup-*` for rollback or verification.
- If rollback is needed, restore the backed-up payload files and point `CMS/includes/data.php` back to file reads by temporarily removing the schema entry for the affected file.
