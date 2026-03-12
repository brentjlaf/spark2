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

function getCsrfToken() {
  if (window.builderConfig && typeof window.builderConfig.csrfToken === 'string') {
    return window.builderConfig.csrfToken;
  }
  if (typeof window.builderCsrfToken === 'string') {
    return window.builderCsrfToken;
  }
  return '';
}

export function appendApiAction(formData, action) {
  if (!formData) return;

  if (action) {
    formData.append('action', action);
  }

  const csrfToken = getCsrfToken();
  if (csrfToken) {
    formData.append('csrf_token', csrfToken);
  }
}
