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

const cacheTTL = 300000; // 5 minutes
let folderCache = { data: null, time: 0 };
const imageCache = new Map();

export function invalidateMediaPickerCache() {
  folderCache = { data: null, time: 0 };
  imageCache.clear();
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
      const editBtn = e.target.closest('button.edit-btn[data-id]');
      if (editBtn) {
        e.stopPropagation();
        openEdit(editBtn.dataset.id, editBtn.dataset.src);
        return;
      }

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
  if (pickerImageGrid) pickerImageGrid.replaceChildren();
  if (pickerFolderList) pickerFolderList.replaceChildren();
}

function renderFolderState(message, isError = false) {
  if (!pickerFolderList) return;
  const stateItem = document.createElement('li');
  stateItem.className = 'picker-folder-state';
  if (isError) stateItem.classList.add('is-error');
  stateItem.textContent = message;
  pickerFolderList.replaceChildren(stateItem);
}

function renderImageState(message, isError = false) {
  if (!pickerImageGrid) return;
  const state = document.createElement('div');
  state.className = 'picker-image-state';
  if (isError) state.classList.add('is-error');
  state.textContent = message;
  pickerImageGrid.replaceChildren(state);
}

async function loadPickerFolders() {
  const now = Date.now();
  if (folderCache.data && now - folderCache.time < cacheTTL) {
    renderFolders(folderCache.data);
    return;
  }

  renderFolderState('Loading folders…');
  try {
    const r = await fetch(basePath + '/CMS/modules/media/list_media.php');
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const data = await r.json();
    folderCache = { data, time: Date.now() };
    renderFolders(data);
  } catch (err) {
    console.error('Failed to load folders', err);
    renderFolderState('Could not load folders. Please try again.', true);
  }
}

function renderFolders(data) {
  if (!pickerFolderList) return;
  const cmsBase = basePath + '/CMS';
  const folders = (data.folders || []).map((f) => {
    const name = typeof f === 'string' ? f : f.name;
    const thumb = f.thumbnail ? cmsBase + '/' + f.thumbnail : null;
    return { name, thumb };
  });

  if (!folders.length) {
    renderFolderState('No folders found.');
    return;
  }

  const existing = new Map(
    Array.from(pickerFolderList.querySelectorAll('li[data-folder]')).map((li) => [li.dataset.folder, li])
  );

  const orderedNodes = folders.map(({ name, thumb }) => {
    let li = existing.get(name);
    if (!li) {
      li = document.createElement('li');
      li.dataset.folder = name;
      li.className = 'picker-folder-item';
    }

    const existingImg = li.querySelector('img');
    if (thumb) {
      const img = existingImg || document.createElement('img');
      img.src = thumb;
      img.alt = name;
      if (!existingImg) li.prepend(img);
    } else if (existingImg) {
      existingImg.remove();
    }

    let label = li.querySelector('span');
    if (!label) {
      label = document.createElement('span');
      li.appendChild(label);
    }
    label.textContent = name;
    li.classList.toggle('active', name === currentFolder);

    existing.delete(name);
    return li;
  });

  existing.forEach((node) => node.remove());
  pickerFolderList.replaceChildren(...orderedNodes);
}

async function selectPickerFolder(folder) {
  currentFolder = folder;
  if (pickerFolderList) {
    pickerFolderList.querySelectorAll('li[data-folder]').forEach((li) => {
      li.classList.toggle('active', li.dataset.folder === folder);
    });
  }

  const now = Date.now();
  let data;
  const cache = imageCache.get(folder);
  if (cache && now - cache.time < cacheTTL) {
    data = cache.data;
  } else {
    renderImageState('Loading images…');
    try {
      const r = await fetch(
        basePath + '/CMS/modules/media/list_media.php?folder=' + encodeURIComponent(folder)
      );
      if (!r.ok) throw new Error('HTTP ' + r.status);
      data = await r.json();
      imageCache.set(folder, { data, time: Date.now() });
    } catch (err) {
      console.error('Failed to load media', err);
      renderImageState('Could not load images. Please try again.', true);
      return;
    }
  }

  renderImages(data);
}

function renderImages(data) {
  if (!pickerImageGrid) return;

  const cmsBase = basePath + '/CMS';
  const mediaItems = data.media || [];
  if (!mediaItems.length) {
    renderImageState('No images in this folder.');
    return;
  }

  const existing = new Map(
    Array.from(pickerImageGrid.querySelectorAll('.picker-image-item')).map((item) => {
      const img = item.querySelector('img[data-id]');
      return [img ? img.dataset.id : null, item];
    }).filter(([key]) => key)
  );

  const orderedNodes = mediaItems.map((img) => {
    const key = String(img.id);
    let item = existing.get(key);
    const src = cmsBase + '/' + (img.thumbnail ? img.thumbnail : img.file);
    const full = cmsBase + '/' + img.file;

    if (!item) {
      item = document.createElement('div');
      item.className = 'picker-image-item';

      const imageEl = document.createElement('img');
      item.appendChild(imageEl);

      const overlay = document.createElement('div');
      overlay.className = 'picker-image-overlay';

      const edit = document.createElement('button');
      edit.className = 'edit-btn';
      edit.textContent = '✎';
      overlay.appendChild(edit);

      item.appendChild(overlay);
    }

    const imageEl = item.querySelector('img');
    imageEl.src = src;
    imageEl.dataset.file = full;
    imageEl.dataset.id = key;

    const editBtn = item.querySelector('button.edit-btn');
    editBtn.dataset.id = key;
    editBtn.dataset.src = full;

    existing.delete(key);
    return item;
  });

  existing.forEach((node) => node.remove());
  pickerImageGrid.replaceChildren(...orderedNodes);
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
}

async function saveEditedImage() {
  if (!cropper || !currentEditId) return;
  const canvas = cropper.getCroppedCanvas();
  const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
  const fd = new FormData();
  fd.append('id', currentEditId);
  fd.append('image', dataUrl);
  fd.append('new_version', window.confirm('Create a new version?') ? '1' : '0');
  fd.append('format', 'jpeg');

  try {
    const r = await fetch(basePath + '/CMS/modules/media/crop_media.php', {
      method: 'POST',
      body: fd,
    });
    if (!r.ok) throw new Error('HTTP ' + r.status);

    // Invalidate only on successful media mutation.
    invalidateMediaPickerCache();

    closeEdit();
    await loadPickerFolders();
    if (currentFolder) await selectPickerFolder(currentFolder);
  } catch (err) {
    console.error('Failed to save edited image', err);
  }
}
