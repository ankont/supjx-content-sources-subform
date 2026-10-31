const surfaceDocuments = new WeakMap();

/** Email markup lives outside the WYSIWYG parser and site stylesheet cascade. */
export function publishEmailSurface(body, html) {
  disposePreviewSurface(body);
  const doc = body.ownerDocument, frame = doc.createElement('iframe');
  frame.className = 'sources-email-preview'; frame.title = 'Sources email preview';
  frame.setAttribute('sandbox', 'allow-same-origin'); frame.setAttribute('data-mce-resize', 'false');
  const content = doc.implementation.createHTMLDocument('Sources email preview');
  content.documentElement.dataset.sourcesEmailDocument = '1';
  const style = content.createElement('style');
  style.textContent = 'html{margin:0;padding:0}body{display:flow-root;margin:0;padding:0;font:14px/1.5 Arial,sans-serif;color:#24313c;overflow-wrap:break-word}';
  content.head.append(style); content.body.innerHTML = html;
  frame.srcdoc = '<!doctype html>' + content.documentElement.outerHTML;
  body.replaceChildren(frame); hydrateEmailSurfaces(doc);
}

export function hydrateEmailSurfaces(doc) {
  const view = doc.defaultView; if (!view || !doc.body) return;
  let state = surfaceDocuments.get(doc);
  if (!state) {
    const frames = new Map();
    const clean = () => { for (const [frame, dispose] of frames) if (!frame.isConnected) { dispose(); frames.delete(frame); } };
    const observer = new view.MutationObserver(clean); observer.observe(doc, { childList: true, subtree: true });
    view.addEventListener('pagehide', () => { for (const dispose of frames.values()) dispose(); frames.clear(); });
    state = { frames }; surfaceDocuments.set(doc, state);
  }
  for (const frame of doc.querySelectorAll('iframe.sources-email-preview')) {
    if (state.frames.has(frame)) continue;
    let sizeObserver, bootstrap, disposed = false;
    const size = () => {
      if (disposed || !frame.isConnected) return;
      const child = frame.contentDocument;
      if (!child?.documentElement?.dataset.sourcesEmailDocument || !child.body) return;
      const height = Math.max(1, Math.ceil(child.body.getBoundingClientRect().height));
      if (frame.style.height !== height + 'px') frame.style.height = height + 'px';
    };
    const load = () => {
      sizeObserver?.disconnect(); const child = frame.contentDocument;
      if (!child?.body || !child.documentElement?.dataset.sourcesEmailDocument) return;
      if (view.ResizeObserver) { sizeObserver = new view.ResizeObserver(size); sizeObserver.observe(child.body); }
      size();
    };
    frame.addEventListener('load', load);
    state.frames.set(frame, () => { disposed = true; view.cancelAnimationFrame(bootstrap); sizeObserver?.disconnect(); frame.removeEventListener('load', load); });
    // Start observing as soon as srcdoc is parsed, without waiting for remote images.
    const ready = () => {
      if (disposed || !frame.isConnected) return;
      if (frame.contentDocument?.body && frame.contentDocument.documentElement?.dataset.sourcesEmailDocument) load();
      else bootstrap = view.requestAnimationFrame(ready);
    };
    bootstrap = view.requestAnimationFrame(ready);
  }
}

export function disposePreviewSurface(root) {
  const state = surfaceDocuments.get(root.ownerDocument || root); if (!state) return;
  for (const frame of root.querySelectorAll('iframe.sources-email-preview')) {
    state.frames.get(frame)?.(); state.frames.delete(frame);
  }
}
