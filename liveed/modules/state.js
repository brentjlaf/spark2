// File: state.js
const stateMap = new Map();
let nextId = 1;
const RESERVED_KEYS = new Set(['blockId', 'template', 'original', 'active', 'ts']);

export function ensureBlockState(block) {
  if (!block) return null;
  if (!block.dataset.blockId) {
    block.dataset.blockId = 'b' + nextId++;
  }
  const id = block.dataset.blockId;
  if (!stateMap.has(id)) {
    const data = {};
    for (const [key, val] of Object.entries(block.dataset)) {
      if (!RESERVED_KEYS.has(key)) {
        data[key] = val;
      }
    }
    stateMap.set(id, data);
  }
  return id;
}

export function getSettings(block) {
  ensureBlockState(block);
  return stateMap.get(block.dataset.blockId);
}

export function setSetting(block, name, value) {
  const s = getSettings(block);
  s[name] = value;
  if (block) {
    block.dataset[name] = value;
  }
}

export function getSetting(block, name, defaultValue = '') {
  const s = getSettings(block);
  return s[name] !== undefined ? s[name] : defaultValue;
}

export { stateMap };
