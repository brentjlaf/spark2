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

const cacheTTL = 30000; // 30 seconds
let folderCache = { data: null, time: 0 };
const imageCache = new Map();

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
  try {
    const r = await fetch(basePath + '/CMS/modules/media/list_media.php');
    const data = await r.json();
    folderCache = { data, time: now };
    renderFolders(data);
  } catch (err) {
    console.error('Failed to load folders', err);
  }
}

function renderFolders(data) {
  if (!pickerFolderList) return;
  pickerFolderList.innerHTML = '';
  const cmsBase = basePath + '/CMS';
  const frag = document.createDocumentFragment();
  (data.folders || []).forEach((f) => {
    const name = typeof f === 'string' ? f : f.name;
    const thumb = f.thumbnail ? cmsBase + '/' + f.thumbnail : null;
    const li = document.createElement('li');
    li.dataset.folder = name;
    li.className = 'picker-folder-item';
    if (thumb) {
      const img = document.createElement('img');
      img.src = thumb;
      img.alt = name;
      li.appendChild(img);
    }
    const span = document.createElement('span');
    span.textContent = name;
    li.appendChild(span);
    frag.appendChild(li);
  });
  pickerFolderList.appendChild(frag);
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
    try {
      const r = await fetch(
        basePath + '/CMS/modules/media/list_media.php?folder=' + encodeURIComponent(folder)
      );
      data = await r.json();
      imageCache.set(folder, { data, time: now });
    } catch (err) {
      console.error('Failed to load media', err);
      return;
    }
  }

  if (!pickerImageGrid) return;
  pickerImageGrid.innerHTML = '';
  const cmsBase = basePath + '/CMS';
  const frag = document.createDocumentFragment();
  (data.media || []).forEach((img) => {
    const src = cmsBase + '/' + (img.thumbnail ? img.thumbnail : img.file);
    const full = cmsBase + '/' + img.file;
    const item = document.createElement('div');
    item.className = 'picker-image-item';
    const el = document.createElement('img');
    el.src = src;
    el.dataset.file = full;
    el.dataset.id = img.id;
    item.appendChild(el);
    const overlay = document.createElement('div');
    overlay.className = 'picker-image-overlay';
    const edit = document.createElement('button');
    edit.className = 'edit-btn';
    edit.textContent = '✎';
    edit.addEventListener('click', (e) => {
      e.stopPropagation();
      openEdit(img.id, full);
    });
    overlay.appendChild(edit);
    item.appendChild(overlay);
    frag.appendChild(item);
  });
  pickerImageGrid.appendChild(frag);
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

function saveEditedImage() {
  if (!cropper || !currentEditId) return;
  const canvas = cropper.getCroppedCanvas();
  const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
  const fd = new FormData();
  fd.append('id', currentEditId);
  fd.append('image', dataUrl);
  fd.append('new_version', window.confirm('Create a new version?') ? '1' : '0');
  fd.append('format', 'jpeg');
  fetch(basePath + '/CMS/modules/media/crop_media.php', {
    method: 'POST',
    body: fd,
  }).then(() => {
    closeEdit();
    loadPickerFolders();
    if (currentFolder) selectPickerFolder(currentFolder);
  });
}
