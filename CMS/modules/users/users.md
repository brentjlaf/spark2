# Users module

## Purpose
The users module manages CMS user accounts, roles, and account status.

## Key files
- `CMS/modules/users/view.php` renders the team management dashboard.
- `CMS/modules/users/users.js` drives filtering, editing, and account creation.
- `CMS/modules/users/list_users.php` returns sanitized user data.
- `CMS/modules/users/save_user.php` creates or updates user accounts.
- `CMS/modules/users/delete_user.php` removes user accounts.

## How it works
1. The users dashboard loads the account list from `list_users.php`.
2. Creating or editing a user posts to `save_user.php`, which hashes passwords and validates role/status data.
3. Deletions are processed by `delete_user.php`.

## Data storage
- User accounts: `CMS/data/users.json`.
