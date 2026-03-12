// File: dragDrop.js
import { ensureBlockState } from './state.js';
import { executeScripts } from "./executeScripts.js";
import { getApiUrl, parseJsonResponse } from './api.js';

const templateCache = new Map();
const TEMPLATE_FAILURE_TTL_MS = 3000;

function loadTemplate(file) {
  const cached = templateCache.get(file);
  if (cached) {
    if (cached.type === 'failed') {
      if (Date.now() - cached.at < TEMPLATE_FAILURE_TTL_MS) {
        return Promise.reject(new Error('Template temporarily unavailable.'));
      }
      templateCache.delete(file);
    }
    return typeof cached === 'string' ? Promise.resolve(cached) : cached;
  }
  const p = fetch(getApiUrl(basePath, 'load-block', { file }))
    .then((response) => parseJsonResponse(response, 'Failed to load template'))
    .then((payload) => {
      const html = payload?.html;
      if (typeof html !== 'string') {
        throw new Error('Template response missing html payload.');
      }
      templateCache.set(file, html);
      return html;
    })
    .catch((err) => {
      const failedAt = Date.now();
      templateCache.set(file, { type: 'failed', at: failedAt });
      setTimeout(() => {
        const entry = templateCache.get(file);
        if (entry && entry.type === 'failed' && entry.at === failedAt) {
          templateCache.delete(file);
        }
      }, TEMPLATE_FAILURE_TTL_MS);
      throw err;
    });
  templateCache.set(file, p);
  return p;
}

let palette;
let canvas;
let basePath = '';
let loggedIn = false;
let openSettings;
let applyStoredSettings;

let dragSource = null;
let fromPalette = false;
let liveRegion;
// caching block control markup avoids rebuilding the DOM for each block
const controlsTemplate = `
  <span class="control edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></span>
  <span class="control drag" title="Drag"><i class="fa-solid fa-arrows-up-down-left-right"></i></span>
  <span class="control duplicate" title="Duplicate"><i class="fa-solid fa-clone"></i></span>
  <span class="control delete" title="Delete"><i class="fa-solid fa-trash"></i></span>
`;
const controlsFragment = document.createElement('div');
controlsFragment.className = 'block-controls';
controlsFragment.innerHTML = controlsTemplate;

function extractTemplateSetting(html) {
  const match = html.match(/<templateSetting[^>]*>[\s\S]*?<\/templateSetting>/i);
  const ts = match ? match[0] : '';
  const cleaned = match ? html.replace(match[0], '') : html;
  return { ts, cleaned };
}

const placeholder = document.createElement('div');
placeholder.className = 'block-placeholder';
placeholder.innerHTML = '<span class="drop-text">Drop block here</span>';
placeholder.style.pointerEvents = 'none';

const insertionIndicator = document.createElement('div');
insertionIndicator.className = 'insertion-indicator';
insertionIndicator.style.pointerEvents = 'none';

function throttleRAF(fn) {
  let running = false;
  let lastEvent;
  return function (e) {
    e.preventDefault();
    lastEvent = e;
    if (running) return;
    running = true;
    requestAnimationFrame(() => {
      running = false;
      fn(lastEvent);
    });
  };
}

function ensureLiveRegion() {
  if (liveRegion) return liveRegion;
  liveRegion = document.createElement('div');
  liveRegion.className = 'sr-only';
  liveRegion.setAttribute('aria-live', 'polite');
  liveRegion.setAttribute('aria-atomic', 'true');
  document.body.appendChild(liveRegion);
  return liveRegion;
}

function announce(message) {
  if (!message) return;
  const region = ensureLiveRegion();
  region.textContent = '';
  requestAnimationFrame(() => {
    region.textContent = message;
  });
}

function clearDragOverState() {
  if (!canvas) return;
  canvas.querySelectorAll('.drag-over').forEach((el) => {
    el.classList.remove('drag-over');
  });
}

function showBuilderMessage(message) {
  const toast = window?.builderUI?.toast || window?.builder?.toast;
  if (typeof toast === 'function') {
    toast(message, { type: 'error' });
  }
}

function resetDragState() {
  placeholder.remove();
  insertionIndicator.remove();
  clearDragOverState();
  if (dragSource) {
    dragSource.classList.remove('dragging');
    dragSource.setAttribute('aria-grabbed', 'false');
  }
  dragSource = null;
  fromPalette = false;
}

export function initDragDrop(options = {}) {
  palette = options.palette;
  canvas = options.canvas;
  basePath = options.basePath || '';
  loggedIn = options.loggedIn || false;
  openSettings = options.openSettings;
  applyStoredSettings = options.applyStoredSettings;

  if (palette) palette.addEventListener('dragstart', paletteDragStart);
  if (canvas) {
    ['dragstart', 'dragenter', 'dragleave', 'dragover', 'drop', 'dragend'].forEach(
      (ev) => canvas.addEventListener(ev, delegateDragEvents, true)
    );
  }
  setupDropArea(canvas);
  if (canvas) {
    canvas.querySelectorAll('.drop-area').forEach(setupDropArea);
  }
}

function paletteDragStart(e) {
  const item = e.target.closest('.block-item');
  if (item) {
    dragSource = item;
    fromPalette = true;
    e.dataTransfer.setData('text/plain', item.dataset.file || '');
    e.dataTransfer.effectAllowed = 'copy';
    item.classList.add('dragging');
    item.setAttribute('aria-grabbed', 'true');
    announce(`Dragging ${item.textContent.trim() || 'block'} from palette.`);

    const dragImage = item.cloneNode(true);
    dragImage.classList.add('drag-ghost');
    dragImage.style.position = 'absolute';
    dragImage.style.top = '-1000px';
    document.body.appendChild(dragImage);
    e.dataTransfer.setDragImage(dragImage, dragImage.offsetWidth / 2, dragImage.offsetHeight / 2);
    setTimeout(() => document.body.removeChild(dragImage), 0);
  }
}

function canvasDragStart(e) {
  const handle = e.target.closest('.control.drag');
  if (handle) {
    dragSource = handle.closest('.block-wrapper');
    fromPalette = false;
    dragSource.classList.add('dragging');
    e.dataTransfer.setData('text/plain', 'reorder');
    e.dataTransfer.effectAllowed = 'move';
    dragSource.setAttribute('aria-grabbed', 'true');
    announce('Block picked up. Move to a drop area and release to place.');

    const dragImage = dragSource.cloneNode(true);
    dragImage.classList.add('drag-ghost');
    dragImage.style.position = 'absolute';
    dragImage.style.top = '-1000px';
    document.body.appendChild(dragImage);
    e.dataTransfer.setDragImage(dragImage, dragImage.offsetWidth / 2, dragImage.offsetHeight / 2);
    setTimeout(() => document.body.removeChild(dragImage), 0);
  } else if (e.target.closest('.block-wrapper')) {
    e.preventDefault();
  }
}

export function addBlockControls(block) {
  ensureBlockState(block);
  if (applyStoredSettings) applyStoredSettings(block);
  if (!loggedIn) {
    const existing = block.querySelector('.block-controls');
    if (existing) existing.remove();
    block.removeAttribute('draggable');
    return;
  }
  if (!block.querySelector('.block-controls')) {
    const controls = controlsFragment.cloneNode(true);
    block.style.position = 'relative';
    block.appendChild(controls);
  }
  block.removeAttribute('draggable');
  const dragHandle = block.querySelector('.block-controls .drag');
  if (dragHandle) dragHandle.setAttribute('draggable', 'true');
  if (!block.dataset.original) {
    let html = block.innerHTML;
    const { ts, cleaned } = extractTemplateSetting(html);
    block.dataset.original = cleaned;
    if (ts) {
      block.dataset.ts = btoa(ts);
    }
  } else {
    const { ts, cleaned } = extractTemplateSetting(block.dataset.original);
    block.dataset.original = cleaned;
    if (ts && !block.dataset.ts) {
      block.dataset.ts = btoa(ts);
    }
  }
  const tsEl = block.querySelector('templateSetting');
  if (tsEl) tsEl.remove();
  const areas = block.querySelectorAll('.drop-area');
  areas.forEach(setupDropArea);
  if (areas.length === 0) {
    setupDropArea(block);
  }
}

export function setupDropArea(area) {
  if (!area) return;
  area.dataset.dropArea = 'true';
}

function handleDragEnter(e) {
  const area = e.target.closest('[data-drop-area]');
  if (area) {
    area.classList.add('drag-over');
  }
}

function handleDragLeave(e) {
  const area = e.target.closest('[data-drop-area]');
  if (area && (!e.relatedTarget || !area.contains(e.relatedTarget))) {
    area.classList.remove('drag-over');
    placeholder.remove();
    insertionIndicator.remove();
  }
}

function handleDragOver(e) {
  const area = e.target.closest('[data-drop-area]');
  if (!area) return;
  e.preventDefault();
  e.dataTransfer.dropEffect = fromPalette ? 'copy' : 'move';
  const after = getDragAfterElement(area, e.clientY);
  if (after == null) {
    area.appendChild(placeholder);
  } else {
    area.insertBefore(placeholder, after);
  }
  insertionIndicator.remove();
  if (placeholder.parentNode) {
    placeholder.parentNode.insertBefore(insertionIndicator, placeholder);
  }
}

function handleDrop(e) {
  const area = e.target.closest('[data-drop-area]');
  if (!area) return;
  e.preventDefault();
  const after = getDragAfterElement(area, e.clientY);
  if (!fromPalette && dragSource && (area === dragSource || dragSource.contains(area))) {
    announce('Cannot drop a block inside itself.');
    placeholder.remove();
    insertionIndicator.remove();
    clearDragOverState();
    dragSource.classList.remove('dragging');
    dragSource.setAttribute('aria-grabbed', 'false');
    dragSource = null;
    return;
  }
  if (fromPalette && dragSource) {
    const file = dragSource.dataset.file;
    if (file) {
      loadTemplate(file).then((html) => {
          const wrapper = document.createElement('div');
          wrapper.className = 'block-wrapper';
          wrapper.dataset.template = file;
          const { ts, cleaned } = extractTemplateSetting(html);
          wrapper.dataset.original = cleaned;
          if (ts) {
            wrapper.dataset.ts = btoa(ts);
          }
          const base = file.replace(/\.php$/, '');
          const parts = base.split('.');
          const group = parts.shift();
          const raw = parts.join(' ') || group;
          const label = raw
            .replace(/[-_]/g, ' ')
            .replace(/\b\w/g, (c) => c.toUpperCase());
          wrapper.setAttribute('data-tpl-tooltip', label);
          wrapper.innerHTML = cleaned;
          executeScripts(wrapper, { blockType: wrapper.dataset.template ? wrapper.dataset.template.replace(/\.php$/, '') : undefined });
          if (applyStoredSettings) applyStoredSettings(wrapper);
          addBlockControls(wrapper);
          if (after == null) area.appendChild(wrapper);
          else area.insertBefore(wrapper, after);
          
          if (openSettings) openSettings(wrapper);
          document.dispatchEvent(new Event('canvasUpdated'));
          announce(`${label} block added.`);
        }).catch((err) => {
          console.error('Failed to add block template:', err);
          announce('Could not add block. Please try again.');
          showBuilderMessage('Could not add block. Please try again.');
          resetDragState();
        });
    }
  } else if (dragSource) {
    dragSource.classList.remove('dragging');
    if (after == null) area.appendChild(dragSource);
    else area.insertBefore(dragSource, after);

    document.dispatchEvent(new Event('canvasUpdated'));
    announce('Block moved.');
  }
  resetDragState();
}

function handleDragEnd() {
  resetDragState();
}

function getDragAfterElement(container, y) {
  const els = container.querySelectorAll('.block-wrapper:not(.dragging)');
  let closest = null;
  let closestOffset = Number.NEGATIVE_INFINITY;
  for (let i = 0; i < els.length; i++) {
    const box = els[i].getBoundingClientRect();
    const offset = y - box.top - box.height / 2;
    if (offset < 0 && offset > closestOffset) {
      closestOffset = offset;
      closest = els[i];
    }
  }
  return closest;
}

const throttledDragOver = throttleRAF(handleDragOver);
const throttledDrop = throttleRAF(handleDrop);

function delegateDragEvents(e) {
  switch (e.type) {
    case 'dragstart':
      canvasDragStart(e);
      break;
    case 'dragenter':
      handleDragEnter(e);
      break;
    case 'dragleave':
      handleDragLeave(e);
      break;
    case 'dragover':
      throttledDragOver(e);
      break;
    case 'drop':
      throttledDrop(e);
      break;
    case 'dragend':
      handleDragEnd(e);
      break;
  }
}
