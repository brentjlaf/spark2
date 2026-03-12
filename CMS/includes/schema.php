<?php
// Schema definitions for CMS entities mapped from JSON storage

function cms_entity_schemas(): array
{
    return [
        'pages.json' => [
            'table' => 'cms_pages',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'slug' => 'slug',
                'title' => 'title',
                'published' => 'published',
                'last_modified' => 'last_modified',
            ],
            'indexes' => ['slug', 'published', 'last_modified'],
            'description' => 'Published pages and drafts with metadata and SEO fields.',
        ],
        'menus.json' => [
            'table' => 'cms_menus',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'name' => 'name',
            ],
            'indexes' => ['name'],
            'description' => 'Navigation menus with nested menu items.',
        ],
        'blog_posts.json' => [
            'table' => 'cms_blog_posts',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'slug' => 'slug',
                'status' => 'status',
                'publishDate' => 'publishDate',
                'createdAt' => 'createdAt',
            ],
            'indexes' => ['slug', 'status', 'publishDate'],
            'description' => 'Blog posts with author, category, status, and publish dates.',
        ],
        'media.json' => [
            'table' => 'cms_media',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'name' => 'name',
                'folder' => 'folder',
                'type' => 'type',
                'uploaded_at' => 'uploaded_at',
            ],
            'indexes' => ['folder', 'type', 'uploaded_at'],
            'description' => 'Uploaded media files, folders, thumbnails, and tags.',
        ],
        'users.json' => [
            'table' => 'cms_users',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'username' => 'username',
                'role' => 'role',
                'status' => 'status',
                'created_at' => 'created_at',
                'last_login' => 'last_login',
                'password' => 'password',
            ],
            'indexes' => ['username', 'status'],
            'exclude_from_payload' => ['password'],
            'description' => 'CMS users and login metadata.',
        ],
        'settings.json' => [
            'table' => 'cms_settings',
            'primary' => 'setting_key',
            'json_column' => 'payload',
            'columns' => [
                'setting_key' => 'setting_key',
            ],
            'indexes' => ['setting_key'],
            'description' => 'Key-value site settings and nested options.',
        ],
        'forms.json' => [
            'table' => 'cms_forms',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'name' => 'name',
            ],
            'indexes' => ['name'],
            'description' => 'Reusable forms and confirmation email settings.',
        ],
        'form_submissions.json' => [
            'table' => 'cms_form_submissions',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'form_id' => 'form_id',
                'submitted_at' => 'submitted_at',
            ],
            'indexes' => ['form_id', 'submitted_at'],
            'description' => 'Captured form submissions with data and metadata.',
        ],
        'events.json' => [
            'table' => 'cms_events',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'status' => 'status',
                'start' => 'start',
                'end' => 'end',
                'published_at' => 'published_at',
            ],
            'indexes' => ['status', 'start', 'end'],
            'description' => 'Event definitions, schedules, tickets, and categories.',
        ],
        'event_orders.json' => [
            'table' => 'cms_event_orders',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'event_id' => 'event_id',
                'status' => 'status',
                'ordered_at' => 'ordered_at',
            ],
            'indexes' => ['event_id', 'status', 'ordered_at'],
            'description' => 'Orders tied to events and ticket selections.',
        ],
        'event_categories.json' => [
            'table' => 'cms_event_categories',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'slug' => 'slug',
            ],
            'indexes' => ['slug'],
            'description' => 'Event taxonomy categories.',
        ],
        'event_forms.json' => [
            'table' => 'cms_event_forms',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'name' => 'name',
            ],
            'indexes' => ['name'],
            'description' => 'Event-specific form templates.',
        ],
        'map_locations.json' => [
            'table' => 'cms_map_locations',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'category' => 'category',
                'title' => 'title',
            ],
            'indexes' => ['category'],
            'description' => 'Map markers and metadata.',
        ],
        'map_categories.json' => [
            'table' => 'cms_map_categories',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'slug' => 'slug',
                'name' => 'name',
            ],
            'indexes' => ['slug'],
            'description' => 'Map category definitions.',
        ],
        'page_history.json' => [
            'table' => 'cms_page_history',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'page_id' => 'page_id',
                'saved_at' => 'saved_at',
            ],
            'indexes' => ['page_id', 'saved_at'],
            'description' => 'Version history for pages.',
        ],
        'speed_snapshot.json' => [
            'table' => 'cms_speed_snapshots',
            'primary' => 'id',
            'json_column' => 'payload',
            'columns' => [
                'captured_at' => 'captured_at',
            ],
            'indexes' => ['captured_at'],
            'description' => 'Performance snapshots.',
        ],
    ];
}

function cms_schema_for_json(string $file): ?array
{
    $basename = basename($file);
    $schemas = cms_entity_schemas();
    return $schemas[$basename] ?? null;
}
?>
