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

  const record = () => {
    if (!recording) return;
    const html = canvas.innerHTML;
    const currentPos = index >= 0 ? (head + index) % maxHistory : -1;
    if (currentPos >= 0 && history[currentPos] === html) return;
    if (index < size - 1) {
      size = index + 1;
    }
    let insertPos = (head + size) % maxHistory;
    history[insertPos] = html;
    if (size < maxHistory) {
      size++;
    } else {
      head = (head + 1) % maxHistory;
    }
    index = size - 1;
    insertPos = (head + index) % maxHistory;
    if (typeof onChange === 'function') onChange(history[insertPos]);
  };

  const scheduleRecord = () => {
    clearTimeout(timer);
    timer = setTimeout(record, 100);
  };

  const observer = new MutationObserver(scheduleRecord);
  observer.observe(canvas, { childList: true, subtree: true, characterData: true, attributes: true });
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
      applyState(history[pos]);
    }
  };

  const redo = () => {
    if (index < size - 1) {
      index++;
      const pos = (head + index) % maxHistory;
      applyState(history[pos]);
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
