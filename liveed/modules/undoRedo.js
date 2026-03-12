// File: undoRedo.js
export function initUndoRedo(options = {}) {
  const canvas = options.canvas;
  const restore = options.restore;
  const onChange = options.onChange;
  const maxHistory = options.maxHistory || 15;
  if (!canvas) return;
  let history = new Array(maxHistory);
  let size = 0;
  let head = 0;
  let index = -1;
  let recording = true;
  let timer;
  let observer;
  let nextId = 1;
  let typingBurst = 0;
  let lastTypingAt = 0;
  const compactHistory = options.compactHistory !== false;
  const compactThreshold = options.compactThreshold || Math.max(6, Math.floor(maxHistory * 0.6));
  const transientClassPattern = /(?:^|\s)(?:helper|ghost|placeholder|dragging|resizing|ui-sortable-helper)(?:\s|$)/i;
  const observerConfig = {
    childList: true,
    subtree: true,
    characterData: false,
    attributes: false,
  };

  const findPosById = (id) => {
    if (!id) return -1;
    for (let i = 0; i < size; i++) {
      const pos = (head + i) % maxHistory;
      if (history[pos] && history[pos].id === id) return pos;
    }
    return -1;
  };

  const applyPatch = (base, patch) => {
    if (!patch) return base;
    const end = base.length - patch.suffix;
    return base.slice(0, patch.prefix) + patch.middle + base.slice(end);
  };

  const resolveEntryAt = (pos) => {
    const entry = history[pos];
    if (!entry) return '';
    if (entry.kind === 'snapshot') return entry.data;
    const basePos = findPosById(entry.baseId);
    if (basePos < 0) return entry.fallback || '';
    return applyPatch(resolveEntryAt(basePos), entry.patch);
  };

  const createEntry = (html, prevHtml, prevId) => {
    if (!compactHistory || size < compactThreshold || !prevHtml || !prevId) {
      return { id: nextId++, kind: 'snapshot', data: html };
    }

    let prefix = 0;
    const minLength = Math.min(prevHtml.length, html.length);
    while (prefix < minLength && prevHtml[prefix] === html[prefix]) prefix++;

    let suffix = 0;
    while (
      suffix < minLength - prefix &&
      prevHtml[prevHtml.length - 1 - suffix] === html[html.length - 1 - suffix]
    ) {
      suffix++;
    }

    const middle = html.slice(prefix, html.length - suffix);
    const patchCost = middle.length + 24;
    if (patchCost >= html.length * 0.9) {
      return { id: nextId++, kind: 'snapshot', data: html };
    }

    return {
      id: nextId++,
      kind: 'patch',
      baseId: prevId,
      patch: { prefix, suffix, middle },
      fallback: html,
    };
  };

  const materializeDependents = (evictedEntryId) => {
    if (!evictedEntryId || size <= 1) return;
    for (let i = 1; i < size; i++) {
      const pos = (head + i) % maxHistory;
      const entry = history[pos];
      if (entry && entry.kind === 'patch' && entry.baseId === evictedEntryId) {
        history[pos] = {
          id: entry.id,
          kind: 'snapshot',
          data: resolveEntryAt(pos) || entry.fallback || '',
        };
      }
    }
  };

  const record = () => {
    if (!recording) return;
    const html = canvas.innerHTML;
    const currentPos = index >= 0 ? (head + index) % maxHistory : -1;
    const currentHtml = currentPos >= 0 ? resolveEntryAt(currentPos) : '';
    if (currentPos >= 0 && currentHtml === html) return;
    if (index < size - 1) {
      size = index + 1;
    }
    let insertPos = (head + size) % maxHistory;
    const prevPos = size > 0 ? (head + size - 1) % maxHistory : -1;
    const prevHtml = prevPos >= 0 ? resolveEntryAt(prevPos) : '';
    const prevId = prevPos >= 0 && history[prevPos] ? history[prevPos].id : null;
    history[insertPos] = createEntry(html, prevHtml, prevId);
    if (size < maxHistory) {
      size++;
    } else {
      const evictedId = history[head] && history[head].id;
      materializeDependents(evictedId);
      head = (head + 1) % maxHistory;
    }
    index = size - 1;
    insertPos = (head + index) % maxHistory;
    if (typeof onChange === 'function') onChange(resolveEntryAt(insertPos));
  };

  const nodeIsChrome = (node) => {
    const el = node && node.nodeType === Node.TEXT_NODE ? node.parentElement : node;
    if (!el || el.nodeType !== Node.ELEMENT_NODE) return false;
    if (el.closest('.block-controls')) return true;
    const cls = el.className;
    return typeof cls === 'string' && transientClassPattern.test(cls);
  };

  const isChromeOnlyMutation = (mutation) => {
    const nodes = [mutation.target, ...mutation.addedNodes, ...mutation.removedNodes];
    return nodes.length > 0 && nodes.every(nodeIsChrome);
  };

  const shouldSkipMutations = (mutations) =>
    mutations.length > 0 && mutations.every(isChromeOnlyMutation);

  const typingNow = () => Date.now() - lastTypingAt < 900;

  const getDebounceMs = () => {
    if (!typingNow()) return 140;
    return Math.min(500, 250 + typingBurst * 50);
  };

  const scheduleRecord = (mutations = []) => {
    if (shouldSkipMutations(mutations)) return;
    clearTimeout(timer);
    timer = setTimeout(record, getDebounceMs());
  };

  const reconnectObserver = () => {
    if (observer) observer.disconnect();
    observer = new MutationObserver(scheduleRecord);
    const config = { childList: true, subtree: true };
    if (observerConfig.characterData) config.characterData = true;
    if (observerConfig.attributes) config.attributes = true;
    observer.observe(canvas, config);
  };

  reconnectObserver();

  const markTyping = () => {
    const now = Date.now();
    typingBurst = now - lastTypingAt < 1000 ? Math.min(6, typingBurst + 1) : 1;
    lastTypingAt = now;
    if (!observerConfig.characterData) {
      observerConfig.characterData = true;
      reconnectObserver();
    }
  };

  canvas.addEventListener('input', markTyping);
  canvas.addEventListener('keydown', markTyping);

  record();

  const applyState = (html) => {
    recording = false;
    canvas.innerHTML = html;
    if (restore) restore();
    recording = true;
    if (typeof onChange === 'function') onChange(html);
  };

  const undo = () => {
    if (index > 0) {
      index--;
      const pos = (head + index) % maxHistory;
      applyState(resolveEntryAt(pos));
    }
  };

  const redo = () => {
    if (index < size - 1) {
      index++;
      const pos = (head + index) % maxHistory;
      applyState(resolveEntryAt(pos));
    }
  };

  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') {
      e.preventDefault();
      undo();
    } else if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.shiftKey && e.key === 'Z'))) {
      e.preventDefault();
      redo();
    }
  });

  return { record, undo, redo };
}
