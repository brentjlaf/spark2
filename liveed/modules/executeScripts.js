function hashScript(script) {
  const basis = `${script.getAttribute('src') || ''}|${script.getAttribute('type') || ''}|${script.textContent || ''}`;
  let hash = 5381;
  for (let i = 0; i < basis.length; i++) {
    hash = ((hash << 5) + hash) + basis.charCodeAt(i);
    hash |= 0;
  }
  return `s${Math.abs(hash).toString(36)}`;
}

function getBlockType(block, explicitType) {
  if (explicitType) return explicitType;
  const tpl = block && block.dataset ? block.dataset.template : '';
  if (!tpl) return '';
  return tpl.replace(/\.php$/, '');
}

function runBlockInit(block, explicitType) {
  if (!block || !window.LiveEdBlocks || typeof window.LiveEdBlocks.init !== 'function') {
    return false;
  }
  const type = getBlockType(block, explicitType);
  if (!type) return false;

  const attr = `data-liveed-init-${type.replace(/[^a-z0-9_-]/gi, '-')}`;
  if (block.hasAttribute(attr)) return true;

  window.LiveEdBlocks.init(type, block);
  block.setAttribute(attr, 'true');
  return true;
}

function getScriptOwner(script, container) {
  return script.closest('.block-wrapper') || container;
}

/**
 * Initialize block behavior for newly rendered content.
 * Uses `window.LiveEdBlocks.init(type, el)` when available and
 * only re-executes inline scripts once per block instance.
 * @param {Element} container
 * @param {{ blockType?: string }} options
 */
export function executeScripts(container, options = {}) {
  if (!container) return;

  const blocks = container.classList && container.classList.contains('block-wrapper')
    ? [container]
    : Array.from(container.querySelectorAll('.block-wrapper'));

  blocks.forEach((block) => {
    runBlockInit(block, options.blockType);
  });

  const scripts = container.querySelectorAll('script');
  if (!scripts.length) return;

  scripts.forEach((oldScript) => {
    const owner = getScriptOwner(oldScript, container);
    if (!owner) return;
    if (runBlockInit(owner, options.blockType)) return;

    const scriptHash = hashScript(oldScript);
    const executedAttr = `data-executed-script-${scriptHash}`;
    if (owner.hasAttribute(executedAttr)) return;

    const newScript = document.createElement('script');
    for (const attr of oldScript.attributes) {
      newScript.setAttribute(attr.name, attr.value);
    }
    newScript.textContent = oldScript.textContent;
    oldScript.replaceWith(newScript);
    owner.setAttribute(executedAttr, 'true');
  });
}
