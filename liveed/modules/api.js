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

export function getApiErrorMessage(payload, fallback = 'Request failed') {
  return payload?.error?.message || fallback;
}

export async function requestApiJson(url, options = {}) {
  const response = await fetch(url, options);
  let payload = null;

  try {
    payload = await response.json();
  } catch (error) {
    throw new Error(`Invalid JSON response (${response.status})`);
  }

  if (!response.ok || !payload || payload.ok !== true) {
    throw new Error(getApiErrorMessage(payload, `Request failed (${response.status})`));
  }

  return payload.data;
}
