import { SourcesBuilder } from './builder.js';
import { newContainer, validate, serialize, MAX_BYTES } from './inline.js';
import { appendPreviewStyles } from './preview-request.js';

const mounted = new WeakMap();
const options = () => window.Joomla?.getOptions('plg_editorsxtd_sourcesbutton.inline', {}) || {};

export function mountSourcesField(host) {
  if (mounted.has(host)) return mounted.get(host);
  const input = host.querySelector('.sources-field-value');
  if (!input || input.disabled || input.readOnly || input.dataset.locked === '1') return null;
  const labels = JSON.parse(host.dataset.labels || '{}');
  const parent = input.form;
  const alert = document.createElement('div'); alert.className = 'alert alert-warning'; alert.role = 'alert'; alert.hidden = true;
  const surface = document.createElement('div'); surface.className = 'sources-field-builder';
  const fallback = document.createElement('details'); fallback.className = 'sources-field-fallback'; fallback.hidden = true;
  const summary = document.createElement('summary'); summary.textContent = labels.raw || 'View JSON data';
  fallback.append(summary, input); host.append(fallback);
  const toolbar = document.createElement('div'); toolbar.className = 'sources-field-toolbar';
  const preview = document.createElement('button'); preview.type = 'button'; preview.className = 'btn btn-outline-secondary';
  const icon = document.createElement('span'); icon.className = 'icon-eye'; icon.setAttribute('aria-hidden', 'true');
  preview.append(icon, document.createTextNode(' ' + labels.preview)); toolbar.append(preview);
  host.prepend(alert, surface); host.append(toolbar);
  let builder, lastDraft, syncing = false, integrationFailed = false, destroyed = false, request, previewDialog, previewFrame;
  const required = input.required;
  const fail = message => { alert.textContent = message; alert.hidden = false; };
  const parse = raw => {
    if (new TextEncoder().encode(raw).length > MAX_BYTES) throw new Error(labels.invalid);
    return raw.trim() ? validate(JSON.parse(raw)) : newContainer();
  };
  const write = config => {
    const next = config.blocks.length ? (serialize(config), JSON.stringify(validate(config))) : '';
    const draft = JSON.stringify(config);
    if (draft !== lastDraft) {
      lastDraft = draft;
      if (previewDialog?.open) queueMicrotask(() => { if (previewDialog?.open && !destroyed) void showPreview(); });
      if (next !== input.value) {
        input.value = next;
        syncing = true;
        try { input.dispatchEvent(new Event('input', { bubbles: true })); input.dispatchEvent(new Event('change', { bubbles: true })); }
        finally { syncing = false; }
      }
    }
    preview.disabled = !config.blocks.length || !!builder?.pending;
  };
  const mount = () => {
    if (destroyed) return;
    try {
      if (!window.SmartBrowser?.mountCollection || !window.SmartBrowserPicker?.open) throw new Error();
      const config = parse(input.value);
      builder?.destroy(); surface.replaceChildren();
      lastDraft = JSON.stringify(config);
      integrationFailed = false;
      builder = new SourcesBuilder(config, true, { onChange: config => {
        try { write(config); } catch { fail(labels.invalid); }
      }, onError: () => {
        integrationFailed = true; input.hidden = false; fallback.hidden = false; fallback.open = false;
        fail(labels.invalid);
      } });
      builder.mount(surface);
      if (!input.value.trim()) lastDraft = JSON.stringify(builder.config);
      input.hidden = !integrationFailed;
      fallback.hidden = !integrationFailed; fallback.open = false;
      input.required = false; toolbar.hidden = false; alert.hidden = !integrationFailed;
      host.closest('.control-group')?.classList.add('sources-field-control');
      write(builder.config);
      if (previewDialog?.open) queueMicrotask(() => { if (previewDialog?.open && !destroyed) void showPreview(); });
    } catch {
      builder?.destroy(); builder = null; surface.replaceChildren();
      input.hidden = false; input.required = required; toolbar.hidden = true; fail(labels.invalid);
      fallback.hidden = false; fallback.open = false;
    }
  };
  const inputChanged = () => { if (!syncing) mount(); };
  input.addEventListener('change', inputChanged);
  const closePreview = () => {
    request?.abort(); request = null;
    if (previewDialog?.open) previewDialog.close();
    previewDialog?.remove(); previewDialog = null; previewFrame = null;
    preview.disabled = !builder?.config.blocks.length || !!builder?.pending;
  };
  const showPreview = async () => {
    if (!builder || builder.pending || !builder.config.blocks.length) return;
    try {
      serialize(builder.config);
      closePreview();
      previewDialog = document.createElement('dialog'); previewDialog.className = 'sources-field-preview';
      const heading = document.createElement('header'), title = document.createElement('h2'), close = document.createElement('button');
      title.textContent = labels.preview; title.id = input.id + '-preview-title';
      previewDialog.setAttribute('aria-labelledby', title.id);
      close.type = 'button'; close.className = 'btn btn-outline-secondary'; close.setAttribute('aria-label', labels.close);
      const glyph = document.createElement('span'); glyph.className = 'icon-times'; glyph.setAttribute('aria-hidden', 'true'); close.append(glyph);
      close.addEventListener('click', closePreview); heading.append(title, close);
      previewFrame = document.createElement('iframe'); previewFrame.title = labels.preview;
      previewFrame.setAttribute('sandbox', 'allow-same-origin'); previewDialog.append(heading, previewFrame);
      previewDialog.addEventListener('cancel', event => { event.preventDefault(); closePreview(); });
      document.body.append(previewDialog); previewDialog.showModal();
      const currentDialog = previewDialog, frame = previewFrame;
      const snapshot = JSON.stringify(validate(builder.config));
      const settings = options(), body = new URLSearchParams({ configuration: snapshot, mode: 'render' });
      body.set(settings.csrfToken, '1'); request = new AbortController(); preview.disabled = true;
      const response = await fetch(settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body, signal: request.signal });
      if (!response.ok) throw new Error();
      const json = await response.json();
      const result = Array.isArray(json.data) ? json.data[0] : json.data;
      if (!json.success || !result?.ok || typeof result.html !== 'string') throw new Error();
      if (previewDialog !== currentDialog || snapshot !== JSON.stringify(validate(builder.config))) return;
      const doc = document.implementation.createHTMLDocument(labels.preview);
      doc.documentElement.className = 'sources-preview-document';
      doc.body.className = 'sources-preview-surface';
      const base = doc.createElement('base'); base.href = document.baseURI; doc.head.append(base);
      for (const link of document.querySelectorAll('link[rel=stylesheet]')) {
        const style = doc.createElement('link'); style.rel = 'stylesheet'; style.href = link.href; doc.head.append(style);
      }
      const style = doc.createElement('style'); style.textContent = 'body{padding:20px;margin:0;overflow-wrap:anywhere}'; doc.head.append(style);
      appendPreviewStyles(doc, result.styles, document.baseURI);
      doc.body.innerHTML = result.html;
      if (!result.html.trim()) doc.body.textContent = labels.empty;
      frame.srcdoc = '<!doctype html>' + doc.documentElement.outerHTML;
    } catch (error) {
      if (error.name !== 'AbortError') {
        closePreview(); fail(options().i18n?.previewFailed || labels.invalid);
      }
    } finally { preview.disabled = !builder?.config.blocks.length || !!builder?.pending; }
  };
  preview.addEventListener('click', showPreview);
  const beforeSubmit = event => {
    if (!host.isConnected) return;
    const task = parent?.querySelector('[name=task]')?.value || '';
    if (/cancel$/.test(task)) return;
    if (builder?.pending) { event.preventDefault(); fail(labels.pending); return; }
    if (builder) {
      try {
        write(builder.config);
        if (required && !builder.config.blocks.length) throw new Error();
        if (required && !input.value.trim()) {
          input.value = JSON.stringify(validate(builder.config));
        }
      } catch { event.preventDefault(); fail(labels.invalid); }
    }
  };
  parent?.addEventListener('submit', beforeSubmit);
  parent?.addEventListener('joomla:before-submit', beforeSubmit);
  const reset = () => setTimeout(mount, 0);
  parent?.addEventListener('reset', reset);
  mount();
  const instance = { get builder() { return builder; }, showPreview, destroy() {
    destroyed = true;
    closePreview(); builder?.destroy(); input.hidden = false; input.required = required;
    input.removeEventListener('change', inputChanged); parent?.removeEventListener('submit', beforeSubmit);
    parent?.removeEventListener('joomla:before-submit', beforeSubmit); parent?.removeEventListener('reset', reset);
    host.append(input); fallback.remove();
    alert.remove(); surface.remove(); toolbar.remove(); mounted.delete(host);
  } };
  mounted.set(host, instance); return instance;
}

function mountAll(root = document) {
  if (root.matches?.('[data-sources-field]')) mountSourcesField(root);
  for (const host of root.querySelectorAll('[data-sources-field]')) mountSourcesField(host);
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => mountAll(), { once: true });
else mountAll();
document.addEventListener('joomla:updated', event => mountAll(event.target));
