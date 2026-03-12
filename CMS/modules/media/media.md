# Media module

## Purpose
The media module manages uploads, folders, tagging, and image transformations for the CMS media library.

## Key files
- `CMS/modules/media/view.php` renders the media library UI.
- `CMS/modules/media/media.js` handles upload, search, filtering, and drag/drop ordering.
- `CMS/modules/media/list_media.php` lists media and folders with metadata and sorting.
- `CMS/modules/media/upload_media.php` handles file uploads and metadata creation.
- `CMS/modules/media/create_folder.php`, `rename_folder.php`, `delete_folder.php` manage folder structure.
- `CMS/modules/media/rename_media.php`, `delete_media.php`, `move_media.php`, `update_tags.php`, `update_order.php` manage media records.
- `CMS/modules/media/crop_media.php` creates cropped variants.
- `CMS/modules/media/get_usage.php` reports references to media assets.
- `CMS/modules/media/picker.php` provides a picker UI for other modules.

## How it works
1. Media files are uploaded into `uploads/` and tracked in `CMS/data/media.json`.
2. `list_media.php` returns media records along with folder summaries, computed metadata (dimensions, size), and sorting options.
3. JavaScript drives the UI: uploads, tagging, moving, and ordering by calling the various endpoints.
4. Image helpers support cropping and thumbnail generation when needed.

## Data storage
- Media metadata: `CMS/data/media.json`.
- Uploaded files: `uploads/` (organized by folder names).
