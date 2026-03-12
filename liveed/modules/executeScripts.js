/**
 * Ensure any script tags within the provided element are executed.
 * Replaces each script with a new one so the browser runs it.
 * @param {Element} container
 */
export function executeScripts(container) {
  if (!container) return;

  const scripts = container.querySelectorAll('script');
  if (!scripts.length) return;

  scripts.forEach((oldScript) => {
    const newScript = document.createElement('script');

    for (const attr of oldScript.attributes) {
      newScript.setAttribute(attr.name, attr.value);
    }

    newScript.textContent = oldScript.textContent;
    oldScript.replaceWith(newScript);
  });
}
