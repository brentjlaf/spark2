// File: mediaPicker.js
let basePath = '';
let mediaPickerModal;
let pickerFolderList;
let pickerImageGrid;
let pickerCloseBtn;

let pickerEditModal;
let pickerEditImage;
let pickerScale;
let pickerEditSave;
let pickerEditCancel;
let cropper = null;
let currentFolder = null;
let currentEditId = null;
let pickerTargetId = null;
let isSavingEdit = false;

const cacheTTL = 300000; // 5 minutes
let folderCache = { data: null, time: 0 };
const imageCache = new Map();

function getErrorMessage(error, fallback) {
  return (error && error.message) || fallback;
}

async function parseMediaJsonResponse(response, fallbackMessage) {
  let data;
  try {
    data = await response.json();
  } catch (err) {
    throw new Error(`${fallbackMessage}: invalid response.`);
  }

  if (!response.ok) {
    const message = data?.error?.message || data?.message;
    throw new Error(message || `${fallbackMessage} (HTTP ${response.status}).`);
  }

  return data;
}

function invalidateMediaPickerCache(folder) {
  folderCache = { data: null, time: 0 };
  if (folder) {
    imageCache.delete(folder);
    return;
  }
  imageCache.clear();
}

function setListState(container, type, message, onRetry) {
  if (!container) return;
  const existingItems = container.querySelectorAll('[data-folder], [data-media-id]');
  existingItems.forEach((item) => item.remove());

  let state = container.querySelector('[data-picker-state]');
  if (!state) {
    state = document.createElement('div');
    state.dataset.pickerState = 'true';
    state.className = 'picker-state';
    container.appendChild(state);
  }

  state.dataset.stateType = type;
  state.textContent = message || '';

  const existingRetry = state.querySelector('button');
  if (existingRetry) existingRetry.remove();
  if (type === 'error' && typeof onRetry === 'function') {
    const retry = document.createElement('button');
    retry.type = 'button';
    retry.className = 'picker-retry-btn';
    retry.textContent = 'Retry';
    retry.addEventListener('click', onRetry, { once: true });
    state.appendChild(document.createTextNode(' '));
    state.appendChild(retry);
  }
}

function clearListState(container) {
  if (!container) return;
  const state = container.querySelector('[data-picker-state]');
  if (state) state.remove();
}

function upsertFolderItem(existing, folderData, cmsBase) {
  const name = typeof folderData === 'string' ? folderData : folderData.name;
  const thumb = folderData.thumbnail ? cmsBase + '/' + folderData.thumbnail : null;
  const li = existing || document.createElement('li');
  li.dataset.folder = name;
  li.className = 'picker-folder-item';
  li.classList.toggle('active', currentFolder === name);

  let img = li.querySelector('img');
  if (thumb) {
    if (!img) {
      img = document.createElement('img');
      li.appendChild(img);
    }
    img.src = thumb;
    img.alt = name;
  } else if (img) {
    img.remove();
  }

  let span = li.querySelector('span');
  if (!span) {
    span = document.createElement('span');
    li.appendChild(span);
  }
  span.textContent = name;

  return li;
}

function upsertImageItem(existing, img, cmsBase) {
  const src = cmsBase + '/' + (img.thumbnail ? img.thumbnail : img.file);
  const full = cmsBase + '/' + img.file;
  const item = existing || document.createElement('div');
  item.className = 'picker-image-item';
  item.dataset.mediaId = String(img.id);

  let el = item.querySelector('img[data-file]');
  if (!el) {
    el = document.createElement('img');
    item.appendChild(el);
  }
  el.src = src;
  el.dataset.file = full;
  el.dataset.id = img.id;

  let overlay = item.querySelector('.picker-image-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'picker-image-overlay';
    item.appendChild(overlay);
  }

  let edit = overlay.querySelector('.edit-btn');
  if (!edit) {
    edit = document.createElement('button');
    edit.className = 'edit-btn';
    edit.type = 'button';
    overlay.appendChild(edit);
  }
  edit.textContent = '✎';
  edit.onclick = (e) => {
    e.stopPropagation();
    openEdit(img.id, full);
  };

  return item;
}

export function initMediaPicker(options = {}) {
  basePath = options.basePath || '';
  mediaPickerModal = document.getElementById('mediaPickerModal');
  pickerFolderList = document.getElementById('pickerFolderList');
  pickerImageGrid = document.getElementById('pickerImageGrid');
  pickerCloseBtn = document.getElementById('mediaPickerClose');
  pickerEditModal = document.getElementById('pickerEditModal');
  pickerEditImage = document.getElementById('pickerEditImage');
  pickerScale = document.getElementById('pickerScale');
  pickerEditSave = document.getElementById('pickerEditSave');
  pickerEditCancel = document.getElementById('pickerEditCancel');

  if (pickerCloseBtn) pickerCloseBtn.addEventListener('click', closeMediaPicker);
  if (mediaPickerModal) {
    mediaPickerModal.addEventListener('click', (e) => {
      if (e.target === mediaPickerModal) closeMediaPicker();
    });
  }


  if (pickerFolderList) {
    pickerFolderList.addEventListener('click', (e) => {
      const li = e.target.closest('li[data-folder]');
      if (li) selectPickerFolder(li.dataset.folder);
    });
  }

  if (pickerImageGrid) {
    pickerImageGrid.addEventListener('click', (e) => {
      const img = e.target.closest('img[data-file]');
      if (img) {
        const input = document.getElementById(pickerTargetId);
        if (input) {
          input.value = img.dataset.file;
          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        closeMediaPicker();
      }
    });
  }

  if (pickerEditCancel) pickerEditCancel.addEventListener('click', closeEdit);
  if (pickerEditSave) pickerEditSave.addEventListener('click', saveEditedImage);
  if (pickerScale) {
    pickerScale.addEventListener('input', () => {
      if (cropper) cropper.zoomTo(parseFloat(pickerScale.value));
    });
  }

  document.addEventListener('media:uploaded', (e) => {
    const folder = e?.detail?.folder;
    invalidateMediaPickerCache(folder);
  });
  document.addEventListener('media:cropped', (e) => {
    const folder = e?.detail?.folder;
    invalidateMediaPickerCache(folder || currentFolder);
  });
}

export function openMediaPicker(targetId) {
  pickerTargetId = targetId;
  if (mediaPickerModal) {
    mediaPickerModal.classList.add('active');
    loadPickerFolders();
  }
}

export function closeMediaPicker() {
  pickerTargetId = null;
  if (mediaPickerModal) {
    mediaPickerModal.classList.remove('active');
  }
  if (pickerImageGrid) pickerImageGrid.innerHTML = '';
  if (pickerFolderList) pickerFolderList.innerHTML = '';
}

async function loadPickerFolders() {
  const now = Date.now();
  if (folderCache.data && now - folderCache.time < cacheTTL) {
    renderFolders(folderCache.data);
    return;
  }
  setListState(pickerFolderList, 'loading', 'Loading folders…');
  try {
    const r = await fetch(basePath + '/CMS/modules/media/list_media.php');
    const data = await parseMediaJsonResponse(r, 'Unable to load folders');
    folderCache = { data, time: now };
    renderFolders(data);
  } catch (err) {
    console.error('Failed to load folders', err);
    setListState(
      pickerFolderList,
      'error',
      getErrorMessage(err, 'Unable to load folders.'),
      () => { loadPickerFolders(); }
    );
  }
}

function renderFolders(data) {
  if (!pickerFolderList) return;
  clearListState(pickerFolderList);
  const cmsBase = basePath + '/CMS';
  const byKey = new Map(
    Array.from(pickerFolderList.querySelectorAll('li[data-folder]')).map((li) => [li.dataset.folder, li])
  );
  const nextKeys = new Set();

  (data.folders || []).forEach((f) => {
    const name = typeof f === 'string' ? f : f.name;
    nextKeys.add(name);
    const li = upsertFolderItem(byKey.get(name), f, cmsBase);
    pickerFolderList.appendChild(li);
  });

  byKey.forEach((li, key) => {
    if (!nextKeys.has(key)) li.remove();
  });

  if (!nextKeys.size) setListState(pickerFolderList, 'empty', 'No folders available.');
}

async function selectPickerFolder(folder) {
  currentFolder = folder;
  if (pickerFolderList) {
    pickerFolderList.querySelectorAll('li').forEach((li) => {
      li.classList.toggle('active', li.dataset.folder === folder);
    });
  }

  const now = Date.now();
  let data;
  const cache = imageCache.get(folder);
  if (cache && now - cache.time < cacheTTL) {
    data = cache.data;
  } else {
    setListState(pickerImageGrid, 'loading', 'Loading images…');
    try {
      const r = await fetch(
        basePath + '/CMS/modules/media/list_media.php?folder=' + encodeURIComponent(folder)
      );
      data = await parseMediaJsonResponse(r, 'Unable to load media');
      imageCache.set(folder, { data, time: now });
    } catch (err) {
      console.error('Failed to load media', err);
      setListState(
        pickerImageGrid,
        'error',
        getErrorMessage(err, 'Unable to load media.'),
        () => { selectPickerFolder(folder); }
      );
      return;
    }
  }

  renderImages(data);
}

function renderImages(data) {
  if (!pickerImageGrid) return;
  clearListState(pickerImageGrid);
  const cmsBase = basePath + '/CMS';
  const byKey = new Map(
    Array.from(pickerImageGrid.querySelectorAll('[data-media-id]')).map((el) => [el.dataset.mediaId, el])
  );
  const nextKeys = new Set();

  (data.media || []).forEach((img) => {
    const key = String(img.id);
    nextKeys.add(key);
    const item = upsertImageItem(byKey.get(key), img, cmsBase);
    pickerImageGrid.appendChild(item);
  });

  byKey.forEach((item, key) => {
    if (!nextKeys.has(key)) item.remove();
  });

  if (!nextKeys.size) setListState(pickerImageGrid, 'empty', 'No images in this folder.');
}


function openEdit(id, src) {
  currentEditId = id;
  if (!pickerEditModal || !pickerEditImage) return;
  pickerEditImage.src = src;
  pickerEditModal.classList.add('active');
  if (cropper) cropper.destroy();
  cropper = new Cropper(pickerEditImage, { viewMode: 1 });
  if (pickerScale) pickerScale.value = 1;
}

function closeEdit() {
  currentEditId = null;
  if (pickerEditModal) pickerEditModal.classList.remove('active');
  if (cropper) {
    cropper.destroy();
    cropper = null;
  }
  setCropSaveState('idle');
  isSavingEdit = false;
}

function setCropSaveState(state, message) {
  if (!pickerEditSave) return;
  const label = pickerEditSave.querySelector('.btn-label');
  const defaultText = pickerEditSave.dataset.defaultLabel || (label ? label.textContent : pickerEditSave.textContent);
  if (!pickerEditSave.dataset.defaultLabel) pickerEditSave.dataset.defaultLabel = defaultText;

  pickerEditSave.dataset.saveState = state;
  if (state === 'saving') {
    pickerEditSave.disabled = true;
  } else {
    pickerEditSave.disabled = false;
  }

  const text = message || (
    state === 'saving' ? 'Saving…' :
    state === 'success' ? 'Saved' :
    state === 'error' ? 'Save failed' :
    defaultText
  );

  if (label) {
    label.textContent = text;
  } else {
    pickerEditSave.textContent = text;
  }
}

async function saveEditedImage() {
  if (isSavingEdit) return;
  if (!cropper || !currentEditId) return;
  const canvas = cropper.getCroppedCanvas();
  const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
  const fd = new FormData();
  fd.append('id', currentEditId);
  fd.append('image', dataUrl);
  fd.append('new_version', window.confirm('Create a new version?') ? '1' : '0');
  fd.append('format', 'jpeg');

  isSavingEdit = true;
  setCropSaveState('saving');

  try {
    const response = await fetch(basePath + '/CMS/modules/media/crop_media.php', {
      method: 'POST',
      body: fd,
    });

    if (!response.ok) {
      throw new Error('Crop request failed with status ' + response.status);
    }

    const payload = await parseMediaJsonResponse(response, 'Failed to save edited image');
    if (!payload || payload.status !== 'success') {
      throw new Error(payload?.message || 'Crop request returned an error response');
    }

    setCropSaveState('success');
    invalidateMediaPickerCache(currentFolder);
    document.dispatchEvent(new CustomEvent('media:cropped', { detail: { folder: currentFolder } }));
    await loadPickerFolders();
    if (currentFolder) await selectPickerFolder(currentFolder);
    closeEdit();
  } catch (err) {
    console.error('Failed to save edited image', err);
    setCropSaveState('error', getErrorMessage(err, 'Save failed'));
  } finally {
    isSavingEdit = false;
    if (pickerEditModal && !pickerEditModal.classList.contains('active')) {
      setCropSaveState('idle');
    } else if (pickerEditSave && pickerEditSave.dataset.saveState === 'saving') {
      setCropSaveState('idle');
    }
  }
}
