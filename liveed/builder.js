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
  fetch(getApiUrl(window.builderBase, 'save-draft'), {
    method: 'POST',
    body: fd,
  }).catch(() => {});
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

function checkLinks(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const warnings = [];
  const checks = [];

  const check = (url, type) => {
    if (!url) return;
    try {
      const full = new URL(url, window.location.href).href;
      checks.push(
        fetch(full, { method: 'HEAD' })
          .then((r) => {
            if (!r.ok) warnings.push(`${type} ${url} returned ${r.status}`);
          })
          .catch(() => warnings.push(`${type} ${url} unreachable`))
      );
    } catch (e) {
      warnings.push(`${type} ${url} invalid`);
    }
  };

  doc.querySelectorAll('a[href]').forEach((el) => check(el.getAttribute('href'), 'Link'));
  doc.querySelectorAll('img[src]').forEach((el) => check(el.getAttribute('src'), 'Image'));

  return Promise.all(checks).then(() => warnings);
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
function savePage() {
  if (!canvas) return;
  const statusEl = document.getElementById('saveStatus');
  const html = canvas.innerHTML;

  if (statusEl) {
    statusEl.textContent = 'Checking links...';
    statusEl.classList.add('saving');
    statusEl.classList.remove('error');
  }

  checkLinks(html).then((warnings) => {
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

    appendApiAction(fd, 'save-content');
    fetch(getApiUrl(window.builderBase, 'save-content'), {
      method: 'POST',
      body: fd,
    })
      .then((r) => {
        if (!r.ok) throw new Error('Save failed');
        return r.text();
      })
      .then(() => {
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
      .catch(() => {
        if (statusEl) {
          statusEl.textContent = 'Error saving';
          statusEl.classList.add('error');
          statusEl.classList.remove('saving');
        }
      });
  });
}

function scheduleSave() {
  clearTimeout(saveTimer);
  storeDraft();
  saveTimer = setTimeout(savePage, SAVE_DEBOUNCE_DELAY);
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

  fetch(getApiUrl(window.builderBase, 'load-draft', { id: window.builderPageId }))
    .then((r) => (r.ok ? r.json() : null))
    .then((serverDraft) => {
      if (serverDraft && serverDraft.timestamp > lastSavedTimestamp) {
        canvas.innerHTML = serverDraft.content;
        lastSavedTimestamp = serverDraft.timestamp;
        localStorage.setItem(builderDraftKey, JSON.stringify(serverDraft));
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

  fetch(getApiUrl(window.builderBase, 'list-blocks'))
    .then((r) => r.json())
    .then((data) => {
      allBlockFiles = data.blocks || [];
      renderPalette(palette, allBlockFiles);
    });

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
    saveBtn.addEventListener('click', () => {
      clearTimeout(saveTimer);
      savePage();
    });
  initWysiwyg(canvas, true);
  initMediaPicker({ basePath: window.builderBase });
  window.openMediaPicker = openMediaPicker;

  canvas.addEventListener('input', scheduleSave);
  canvas.addEventListener('change', scheduleSave);

  canvas.querySelectorAll('.block-wrapper').forEach(addBlockControls);
  executeScripts(canvas);

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
      executeScripts(clone);
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
