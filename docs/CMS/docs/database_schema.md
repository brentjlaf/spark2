# Database schema overview

The CMS previously stored entities in `CMS/data/*.json`. These are now mapped to MySQL tables with typed columns for common filters (slugs, status, publish dates) plus a serialized payload column (stored as LONGTEXT) that preserves the full original structure. The migration script and data helpers rely on the shared schema map in `CMS/includes/schema.php`.

## Entities and tables
- **Pages (`cms_pages`)** — `id` (PK, INT), `slug` (INDEX), `title`, `published` (INDEX), `last_modified` (INDEX), `payload` (LONGTEXT, serialized). Stores page body, template, SEO metadata.
- **Menus (`cms_menus`)** — `id` (PK, INT), `name` (INDEX), `payload` (LONGTEXT, serialized). `payload.items` keeps nested menu links and children. Relates to pages via `items[].link` slugs.
- **Blog posts (`cms_blog_posts`)** — `id` (PK, INT), `slug` (UNIQUE via index), `status` (INDEX), `publishDate` (INDEX), `createdAt`, `payload` (LONGTEXT, serialized). Contains excerpt, content, category, author, tags.
- **Media (`cms_media`)** — `id` (PK, INT), `name`, `folder` (INDEX), `type` (INDEX), `uploaded_at` (INDEX), `payload` (LONGTEXT, serialized). Tracks file paths, thumbnails, tags, ordering, dimensions.
- **Users (`cms_users`)** — `id` (PK, INT), `username` (UNIQUE/INDEX), `role`, `status` (INDEX), `created_at`, `last_login`, `password`, `payload` (LONGTEXT, serialized). Used by authentication; `last_login` is updated on successful login.
- **Settings (`cms_settings`)** — `setting_key` (PK/INDEX, VARCHAR), `payload` (LONGTEXT, serialized). Flattens the key-value `settings.json` map (site name, tagline, social links, homepage, etc.).
- **Forms (`cms_forms`)** — `id` (PK, INT), `name` (INDEX), `payload` (LONGTEXT, serialized). Includes field definitions and confirmation email settings.
- **Form submissions (`cms_form_submissions`)** — `id` (PK, VARCHAR), `form_id` (INDEX), `submitted_at` (INDEX), `payload` (LONGTEXT, serialized). Holds submitted data, metadata (`ip`, `user_agent`, `referer`), and source URL.
- **Events (`cms_events`)** — `id` (PK, VARCHAR), `status` (INDEX), `start` (INDEX), `end` (INDEX), `published_at`, `payload` (LONGTEXT, serialized). Contains description, tickets, categories, and timestamps.
- **Event orders (`cms_event_orders`)** — `id` (PK, VARCHAR), `event_id` (INDEX), `status` (INDEX), `ordered_at` (INDEX), `payload` (LONGTEXT, serialized). Links to `cms_events.id` and stores ticket selections and totals.
- **Event categories (`cms_event_categories`)** — `id` (PK, VARCHAR), `slug` (INDEX), `payload` (LONGTEXT, serialized). Provides taxonomy for events.
- **Event forms (`cms_event_forms`)** — `id` (PK, VARCHAR), `name` (INDEX), `payload` (LONGTEXT, serialized).
- **Map locations (`cms_map_locations`)** — `id` (PK, INT/VARCHAR), `category` (INDEX), `title`, `payload` (LONGTEXT, serialized). Links to categories via `category` field.
- **Map categories (`cms_map_categories`)** — `id` (PK, VARCHAR), `slug` (INDEX), `name`, `payload` (LONGTEXT, serialized).
- **Page history (`cms_page_history`)** — `id` (PK, INT), `page_id` (INDEX), `saved_at` (INDEX), `payload` (LONGTEXT, serialized). Versioned page snapshots.
- **Speed snapshots (`cms_speed_snapshots`)** — `id` (PK, INT), `captured_at` (INDEX), `payload` (LONGTEXT, serialized). Stores Lighthouse/performance metrics.

## Relationships
- **Menus → Pages**: `cms_menus.payload.items[].link` refers to `cms_pages.slug` values.
- **Events → Orders**: `cms_event_orders.event_id` references `cms_events.id`.
- **Events → Categories**: `cms_events.payload.categories[]` reference `cms_event_categories.id`.
- **Forms → Submissions**: `cms_form_submissions.form_id` references `cms_forms.id`.
- **Pages → History**: `cms_page_history.page_id` references `cms_pages.id`.
- **Maps → Categories**: `cms_map_locations.category` references `cms_map_categories.id`.

Indexes on `slug`, `status`, `publishDate`, `start`, `end`, `ordered_at`, and `submitted_at` support current filtering and sorting behaviors.
