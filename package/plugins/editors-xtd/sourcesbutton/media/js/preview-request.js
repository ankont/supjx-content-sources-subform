const requests = new WeakMap();

export function appendPreviewStyles(doc, styles, base) {
  for (const href of Array.isArray(styles) ? styles : []) {
    if (typeof href !== 'string') continue;
    let url;
    try { url = new URL(href, base); } catch { continue; }
    if (!['http:', 'https:'].includes(url.protocol)) continue;
    if ([...doc.querySelectorAll('link[rel=stylesheet]')].some(link => link.href === url.href)) continue;
    const link = doc.createElement('link'); link.rel = 'stylesheet'; link.href = url.href;
    link.dataset.sourcesPreviewStyle = '1'; doc.head.append(link);
  }
}

// A token/Usage or mode change invalidates the previous response, even on the same DOM node.
export function startPreviewRequest(node, key) {
  const previous = requests.get(node);
  if (previous?.key === key) return null;
  previous?.controller.abort();
  const controller = new AbortController();
  const request = { key, controller };
  requests.set(node, request);
  return {
    signal: controller.signal,
    current: () => node.isConnected && !controller.signal.aborted && requests.get(node) === request
      && JSON.stringify([node.dataset.sourcesToken, node.dataset.sourcesPreviewMode]) === key,
  };
}
