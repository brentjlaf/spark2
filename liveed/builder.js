// File: builder.js
import { initDragDrop, addBlockControls } from './modules/dragDrop.js';
import { initSettings, openSettings, applyStoredSettings, confirmDelete } from './modules/settings.js';
import { ensureBlockState, getSettings, setSetting } from './modules/state.js';
import { initUndoRedo } from './modules/undoRedo.js';
import { initWysiwyg } from './modules/wysiwyg.js';
import { initMediaPicker, openMediaPicker } from './modules/mediaPicker.js';
import { executeScripts } from "./modules/executeScripts.js";
import { appendApiAction, getApiUrl } from './modules/api.js';

let allBlockFiles = [];
let favorites = [];
let builderDraftKey = '';
let lastSavedTimestamp = 0;
let saveRequestId = 0;
let saveAbortController = null;
let draftSaveRequestId = 0;
let draftSaveAbortController = null;
let canvas;
let paletteEl;
// Delay before auto-saving after a change. A longer delay prevents rapid
// successive saves while the user is still actively editing.
const SAVE_DEBOUNCE_DELAY = 1000;
function storeDraft() {
  if (!canvas) return;
  const data = {
    html: canvas.innerHTML,
    timestamp: Date.now(),
  };
  localStorage.setItem(builderDraftKey, JSON.stringify(data));
  const fd = new FormData();
  fd.append('id', window.builderPageId);
  fd.append('content', data.html);
  fd.append('timestamp', data.timestamp);
  appendApiAction(fd, 'save-draft');

  if (draftSaveAbortController) {
    draftSaveAbortController.abort();
  }
  const requestId = ++draftSaveRequestId;
  draftSaveAbortController = new AbortController();

  fetch(getApiUrl(window.builderBase, 'save-draft'), {
    method: 'POST',
    body: fd,
    signal: draftSaveAbortController.signal,
  }).catch((error) => {
    if (error && error.name === 'AbortError') return;
    if (requestId !== draftSaveRequestId) return;
  });
}

function renderGroupItems(details) {
  const items = details.querySelector('.group-items');
  if (!items || details._rendered) return;
  const favs = favorites;
  const list = details._items || [];
  const frag = document.createDocumentFragment();
  list.forEach((it) => {
    const item = document.createElement('div');
    item.className = 'block-item';
    item.setAttribute('draggable', 'true');
    item.dataset.file = it.file;
    const label = it.label
      .replace(/[-_]/g, ' ')
      .replace(/\b\w/g, (c) => c.toUpperCase());
    item.textContent = label;
    const favBtn = document.createElement('span');
    favBtn.className = 'fav-toggle';
    if (favs.includes(it.file)) favBtn.classList.add('active');
    favBtn.textContent = '★';
    favBtn.title = favs.includes(it.file) ? 'Unfavorite' : 'Favorite';
    favBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleFavorite(it.file);
    });
    item.appendChild(favBtn);
    frag.appendChild(item);
  });
  items.appendChild(frag);
  details._rendered = true;
}

function animateAccordion(details) {
  const summary = details.querySelector('summary');
  const items = details.querySelector('.group-items');
  if (!summary || !items) return;
  if (!details.open) {
    items.style.display = 'none';
  } else {
    renderGroupItems(details);
  }
  summary.addEventListener('click', (e) => {
    e.preventDefault();
    const isOpen = details.open;
    if (isOpen) {
      details.open = false;
      items.style.display = 'none';
      items.innerHTML = '';
      details._rendered = false;
    } else {
      document.querySelectorAll('.palette-group[open]').forEach((other) => {
        if (other !== details) {
          other.open = false;
          const otherItems = other.querySelector('.group-items');
          if (otherItems) {
            otherItems.style.display = 'none';
            otherItems.innerHTML = '';
            other._rendered = false;
          }
        }
      });
      details.open = true;
      renderGroupItems(details);
      items.style.display = 'grid';
    }
  });
}


function renderPalette(palette, files = []) {
  const container = palette.querySelector('.palette-items');
  if (!container) return;
  container.innerHTML = '';

  const favs = favorites;
  const groups = {};
  if (favs.length) groups.Favorites = [];
  files.forEach((f) => {
    if (!f.endsWith('.php')) return;
    const base = f.replace(/\.php$/, '');
    const parts = base.split('.');
    const group = parts.shift();
    const label = parts.join(' ') || group;
    if (!groups[group]) groups[group] = [];
    const info = { file: f, label };
    groups[group].push(info);
    if (favs.includes(f)) {
      groups.Favorites.push(info);
    }
  });

  Object.keys(groups)
    .sort((a, b) => (a === 'Favorites' ? -1 : b === 'Favorites' ? 1 : a.localeCompare(b)))
    .forEach((g) => {
      const details = document.createElement('details');
      details.className = 'palette-group';

      const summary = document.createElement('summary');
      summary.textContent = g.charAt(0).toUpperCase() + g.slice(1);
      details.appendChild(summary);

      const wrap = document.createElement('div');
      wrap.className = 'group-items';

      details._items = groups[g]
        .slice()
        .sort((a, b) => a.label.localeCompare(b.label));
      details.appendChild(wrap);
      container.appendChild(details);
      animateAccordion(details);
    });
}

function renderPaletteUnavailableState(palette, retryHandler) {
  const container = palette.querySelector('.palette-items');
  if (!container) return;
  container.innerHTML = '';

  const emptyState = document.createElement('div');
  emptyState.className = 'palette-empty-state';

  const message = document.createElement('p');
  message.className = 'palette-empty-message';
  message.textContent = 'Block palette is temporarily unavailable. You can keep editing and retry loading blocks.';

  const retryBtn = document.createElement('button');
  retryBtn.type = 'button';
  retryBtn.className = 'palette-retry-btn';
  retryBtn.textContent = 'Retry';
  retryBtn.addEventListener('click', () => retryHandler());

  emptyState.appendChild(message);
  emptyState.appendChild(retryBtn);
  container.appendChild(emptyState);
}

function toggleFavorite(file) {
  const idx = favorites.indexOf(file);
  if (idx >= 0) {
    favorites.splice(idx, 1);
  } else {
    favorites.push(file);
  }
  localStorage.setItem('favoriteBlocks', JSON.stringify(favorites));
  if (paletteEl) renderPalette(paletteEl, allBlockFiles);
}

let saveTimer;

const LINK_CHECK_CACHE_TTL_MS = 45000;
const LINK_CHECK_CONCURRENCY = 4;
const linkCheckCache = new Map();
let previousSavedLinkTargets = new Set();
let linkCheckEnabled = true;

function loadLinkCheckEnabled() {
  const stored = localStorage.getItem('builder-link-check-enabled');
  if (stored === '0' || stored === 'false') return false;
  if (stored === '1' || stored === 'true') return true;
  if (typeof window.builderLinkCheckEnabled === 'boolean') {
    return window.builderLinkCheckEnabled;
  }
  return true;
}

function setLinkCheckEnabled(value) {
  linkCheckEnabled = !!value;
  localStorage.setItem('builder-link-check-enabled', linkCheckEnabled ? '1' : '0');
  window.builderLinkCheckEnabled = linkCheckEnabled;
}

function collectLinkTargets(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const targets = new Map();

  const addTarget = (url, type) => {
    if (!url) return;
    try {
      const full = new URL(url, window.location.href).href;
      targets.set(`${type}:${full}`, { originalUrl: url, fullUrl: full, type });
    } catch (e) {
      targets.set(`${type}:invalid:${url}`, { originalUrl: url, fullUrl: null, type, invalid: true });
    }
  };

  doc.querySelectorAll('a[href]').forEach((el) => addTarget(el.getAttribute('href'), 'Link'));
  doc.querySelectorAll('img[src]').forEach((el) => addTarget(el.getAttribute('src'), 'Image'));
  return targets;
}

function runWithConcurrency(items, limit, worker) {
  if (!items.length) return Promise.resolve();
  let cursor = 0;
  const workers = Array.from({ length: Math.min(limit, items.length) }, () =>
    (async () => {
      while (cursor < items.length) {
        const index = cursor++;
        await worker(items[index]);
      }
    })()
  );
  return Promise.all(workers).then(() => undefined);
}

function checkLinks(html, options = {}) {
  const { skip = false } = options;
  const targets = collectLinkTargets(html);
  const currentTargetKeys = new Set(targets.keys());

  if (skip || !linkCheckEnabled) {
    return Promise.resolve({ warnings: [], targetKeys: currentTargetKeys });
  }

  const warnings = [];
  const changedTargets = [];

  targets.forEach((target, key) => {
    if (target.invalid) {
      warnings.push(`${target.type} ${target.originalUrl} invalid`);
      return;
    }
    if (!previousSavedLinkTargets.has(key)) {
      changedTargets.push({ key, ...target });
    }
  });

  return runWithConcurrency(changedTargets, LINK_CHECK_CONCURRENCY, (target) => {
    const now = Date.now();
    const cached = linkCheckCache.get(target.key);
    if (cached && cached.expiresAt > now) {
      if (cached.warning) warnings.push(cached.warning);
      return Promise.resolve();
    }

    return fetch(target.fullUrl, { method: 'HEAD' })
      .then((r) => {
        const warning = r.ok ? null : `${target.type} ${target.originalUrl} returned ${r.status}`;
        linkCheckCache.set(target.key, {
          warning,
          expiresAt: now + LINK_CHECK_CACHE_TTL_MS,
        });
        if (warning) warnings.push(warning);
      })
      .catch(() => {
        const warning = `${target.type} ${target.originalUrl} unreachable`;
        linkCheckCache.set(target.key, {
          warning,
          expiresAt: now + LINK_CHECK_CACHE_TTL_MS,
        });
        warnings.push(warning);
      });
  }).then(() => ({ warnings, targetKeys: currentTargetKeys }));
}

function savePage(options = {}) {
  const { skipLinkChecks = false, isAutoSave = false } = options;
  if (!canvas) return;
  const statusEl = document.getElementById('saveStatus');
  const html = canvas.innerHTML;
  const requestId = ++saveRequestId;

  if (statusEl) {
    statusEl.textContent = skipLinkChecks || !linkCheckEnabled ? 'Saving...' : 'Checking links...';
    statusEl.classList.add('saving');
    statusEl.classList.remove('error');
  }

  checkLinks(html, { skip: skipLinkChecks })
    .then(({ warnings, targetKeys }) => {
      if (warnings.length) {
        console.warn('Link issues found:', warnings.join('\n'));
        if (statusEl) {
          statusEl.textContent = 'Link issues found';
          statusEl.classList.add('error');
          statusEl.classList.remove('saving');
          setTimeout(() => {
            if (statusEl.textContent === 'Link issues found') {
              statusEl.textContent = '';
              statusEl.classList.remove('error');
            }
          }, 4000);
        }
      }

      const fd = new FormData();
      fd.append('id', window.builderPageId);
      fd.append('content', html);

      if (statusEl) statusEl.textContent = 'Saving...';

      if (isAutoSave && saveAbortController) {
        saveAbortController.abort();
      }
      saveAbortController = new AbortController();

      appendApiAction(fd, 'save-content');
      return fetch(getApiUrl(window.builderBase, 'save-content'), {
        method: 'POST',
        body: fd,
        signal: saveAbortController.signal,
      }).then((r) => {
        if (!r.ok) throw new Error('Save failed');
        return r.text().then(() => targetKeys);
      });
    })
    .then((targetKeys) => {
      if (requestId !== saveRequestId) return;
      previousSavedLinkTargets = new Set(targetKeys);
      localStorage.removeItem(builderDraftKey);
      lastSavedTimestamp = Date.now();
      if (statusEl) {
        statusEl.textContent = 'Saved';
        statusEl.classList.remove('saving');
      }
      const lastSavedEl = document.getElementById('lastSavedTime');
      if (lastSavedEl) {
        const now = new Date();
        lastSavedEl.textContent = 'Last saved: ' + now.toLocaleString();
      }
      setTimeout(() => {
        if (statusEl && statusEl.textContent === 'Saved') statusEl.textContent = '';
      }, 2000);
    })
    .catch((error) => {
      if (error && error.name === 'AbortError') return;
      if (requestId !== saveRequestId) return;
      if (statusEl) {
        statusEl.textContent = 'Error saving';
        statusEl.classList.add('error');
        statusEl.classList.remove('saving');
      }
    });
}

function checkSeo(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const issues = [];
  if (!doc.querySelector('h1')) {
    issues.push('Missing H1 heading');
  }
  const wordCount = doc.body.textContent.trim().split(/\s+/).length;
  if (wordCount < 300) {
    issues.push('Low word count');
  }
  return issues;
}
function scheduleSave() {
  clearTimeout(saveTimer);
  storeDraft();
  saveTimer = setTimeout(() => savePage({ isAutoSave: true }), SAVE_DEBOUNCE_DELAY);
}

document.addEventListener('DOMContentLoaded', () => {
  canvas = document.getElementById('canvas');
  const palette = (paletteEl = document.querySelector('.block-palette'));
  const settingsPanel = document.getElementById('settingsPanel');
  const builderEl = document.querySelector('.builder');
  const viewToggle = document.getElementById('viewModeToggle');

  const setHelperVisibility = (isOpen) => {
    if (!canvas) return;
    const placeholder = canvas.querySelector('.canvas-placeholder');
    if (!placeholder) return;
    const helper = placeholder.querySelector('.placeholder-helper');
    const toggle = placeholder.querySelector('[data-helper-toggle]');
    if (!helper || !toggle) return;
    helper.classList.toggle('open', isOpen);
    helper.hidden = !isOpen;
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  };

  builderDraftKey = 'builderDraft-' + window.builderPageId;
  setLinkCheckEnabled(loadLinkCheckEnabled());
  window.setLinkCheckEnabled = setLinkCheckEnabled;
  lastSavedTimestamp = window.builderLastModified || 0;
  const draft = localStorage.getItem(builderDraftKey);
  if (draft) {
    try {
      const data = JSON.parse(draft);
      if (data.timestamp > lastSavedTimestamp && data.html) {
        canvas.innerHTML = data.html;
        lastSavedTimestamp = data.timestamp;
      } else {
        localStorage.removeItem(builderDraftKey);
      }
    } catch (e) {
      localStorage.removeItem(builderDraftKey);
    }
  }

  previousSavedLinkTargets = new Set(collectLinkTargets(canvas.innerHTML).keys());

  fetch(getApiUrl(window.builderBase, 'load-draft', { id: window.builderPageId }))
    .then((r) => (r.ok ? r.json() : null))
    .then((serverDraft) => {
      if (serverDraft && serverDraft.timestamp > lastSavedTimestamp) {
        canvas.innerHTML = serverDraft.content;
        lastSavedTimestamp = serverDraft.timestamp;
        localStorage.setItem(builderDraftKey, JSON.stringify(serverDraft));
        previousSavedLinkTargets = new Set(collectLinkTargets(canvas.innerHTML).keys());
      }
    })
    .catch(() => {});

  if (viewToggle) {
    viewToggle.addEventListener('click', () => {
      const viewing = builderEl.classList.toggle('view-mode');
      viewToggle.innerHTML = viewing
        ? '<i class="fa-solid fa-eye-slash"></i>'
        : '<i class="fa-solid fa-eye"></i>';
      if (viewing) {
        if (settingsPanel) settingsPanel.classList.remove('open');
      }
    });
  }

  favorites = JSON.parse(localStorage.getItem('favoriteBlocks') || '[]');


  initSettings({ canvas, settingsPanel, savePage: scheduleSave });

  const searchInput = palette.querySelector('.palette-search');

  function loadPaletteBlocks() {
    return fetch(getApiUrl(window.builderBase, 'list-blocks'))
      .then((r) => {
        if (!r.ok) {
          throw new Error('Failed to load palette blocks');
        }
        return r.json();
      })
      .then((data) => {
        allBlockFiles = data.blocks || [];
        renderPalette(palette, allBlockFiles);
      })
      .catch(() => {
        allBlockFiles = [];
        renderPaletteUnavailableState(palette, loadPaletteBlocks);
      });
  }

  loadPaletteBlocks();

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const term = searchInput.value.toLowerCase();
      const filtered = allBlockFiles.filter((f) => f.toLowerCase().includes(term));
      renderPalette(palette, filtered);
    });
  }

  initDragDrop({
    palette,
    canvas,
    basePath: window.builderBase,
    loggedIn: true,
    openSettings,
    applyStoredSettings,
  });

  const history = initUndoRedo({ canvas, onChange: scheduleSave, maxHistory: 15 });
  const undoBtn = palette.querySelector('.undo-btn');
  const redoBtn = palette.querySelector('.redo-btn');
  const saveBtn = palette.querySelector('.manual-save-btn');
  if (undoBtn) undoBtn.addEventListener('click', () => history.undo());
  if (redoBtn) redoBtn.addEventListener('click', () => history.redo());
  if (saveBtn)
    saveBtn.addEventListener('click', (e) => {
      clearTimeout(saveTimer);
      const fastSave = e && (e.shiftKey || e.altKey);
      savePage({ skipLinkChecks: fastSave });
    });
  initWysiwyg(canvas, true);
  initMediaPicker({ basePath: window.builderBase });
  window.openMediaPicker = openMediaPicker;

  canvas.addEventListener('input', scheduleSave);
  canvas.addEventListener('change', scheduleSave);

  canvas.querySelectorAll('.block-wrapper').forEach(addBlockControls);
  executeScripts(canvas, { blockType: null });

  function updateCanvasPlaceholder() {
    const placeholder = canvas.querySelector('.canvas-placeholder');
    if (!placeholder) return;
    const hasBlocks = canvas.querySelector('.block-wrapper');
    placeholder.hidden = !!hasBlocks;
    if (hasBlocks) setHelperVisibility(false);
  }

  updateCanvasPlaceholder();

  document.addEventListener('canvasUpdated', updateCanvasPlaceholder);
  document.addEventListener('canvasUpdated', scheduleSave);

  canvas.addEventListener('click', (e) => {
    const helperToggle = e.target.closest('[data-helper-toggle]');
    if (helperToggle) {
      e.preventDefault();
      const placeholder = canvas.querySelector('.canvas-placeholder');
      const helperPanel = placeholder
        ? placeholder.querySelector('.placeholder-helper')
        : null;
      const shouldOpen = helperPanel ? !helperPanel.classList.contains('open') : true;
      setHelperVisibility(shouldOpen);
      return;
    }

    if (builderEl.classList.contains('view-mode')) return;
    const block = e.target.closest('.block-wrapper');
    if (!block) return;
    if (e.target.closest('.block-controls .edit')) {
      openSettings(block);
    } else if (e.target.closest('.block-controls .duplicate')) {
      const clone = block.cloneNode(true);
      clone.classList.remove('selected');
      delete clone.dataset.blockId;
      block.after(clone);
      ensureBlockState(clone);
      const settings = getSettings(block);
      for (const key in settings) {
        setSetting(clone, key, settings[key]);
      }
      addBlockControls(clone);
      applyStoredSettings(clone);
      executeScripts(clone, { blockType: clone.dataset.template ? clone.dataset.template.replace(/\.php$/, '') : undefined });
      document.dispatchEvent(new Event('canvasUpdated'));
    } else if (e.target.closest('.block-controls .delete')) {
      confirmDelete('Delete this block?').then((ok) => {
        if (ok) {
          block.remove();
          updateCanvasPlaceholder();
          scheduleSave();
        }
      });
    }
  });


  document.addEventListener('mouseover', (e) => {
    const handle = e.target.closest('.control.drag');
    if (handle) {
      const block = handle.closest('.block-wrapper');
      if (block) block.style.transform = 'scale(1.02)';
    }
  });

  document.addEventListener('mouseout', (e) => {
    const handle = e.target.closest('.control.drag');
    if (handle) {
      const block = handle.closest('.block-wrapper');
      if (block) block.style.transform = '';
    }
  });

  window.addEventListener('beforeunload', () => {
    storeDraft();
  });

});
