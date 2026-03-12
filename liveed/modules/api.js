// File: api.js
const API_PATH = '/liveed/api.php';

export function getApiUrl(basePath, action, params = {}) {
  const base = basePath || '';
  const url = new URL(base + API_PATH, window.location.origin);
  url.searchParams.set('action', action);
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      url.searchParams.set(key, value);
    }
  });
  return url.toString();
}

export function appendApiAction(formData, action) {
  if (formData && action) {
    formData.append('action', action);
  }
}
