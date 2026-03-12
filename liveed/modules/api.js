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

export async function parseJsonResponse(response, fallbackMessage = 'Request failed') {
  let payload;
  try {
    payload = await response.json();
  } catch (err) {
    throw new Error(`${fallbackMessage}: invalid JSON response.`);
  }

  if (!response.ok) {
    const serverMessage = payload?.error?.message;
    throw new Error(serverMessage || `${fallbackMessage} (HTTP ${response.status}).`);
  }

  if (!payload || typeof payload.ok !== 'boolean') {
    throw new Error(`${fallbackMessage}: malformed payload.`);
  }

  if (!payload.ok) {
    const serverMessage = payload?.error?.message;
    throw new Error(serverMessage || `${fallbackMessage}.`);
  }

  return payload.data;
}
