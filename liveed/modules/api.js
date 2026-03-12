// File: api.js
// Canonical LiveEd endpoint contract:
//   /liveed/api.php?action=<action>[&key=value...]
// - All builder client calls should build URLs through this module.
// - `action` is always encoded as a query parameter, including POST requests.
const LIVEED_API_PATH = '/liveed/api.php';

export function getApiUrl(basePath, action, params = {}) {
  if (!action) throw new Error('LiveEd API action is required');

  const base = basePath || '';
  const url = new URL(base + LIVEED_API_PATH, window.location.origin);
  url.searchParams.set('action', action);

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      url.searchParams.set(key, value);
    }
  });

  return url.toString();
}
