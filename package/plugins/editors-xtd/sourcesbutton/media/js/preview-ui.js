import { scan } from './inline.js';
import { startPreviewRequest, appendPreviewStyles, previewRequestKey, cancelPreviewRequest } from './preview-request.js';
import { documentPresentation, validatePresentation } from './presentation-context.js';
import { publishEmailSurface, hydrateEmailSurfaces, disposePreviewSurface } from './preview-surface.js';

const previewModes = ['none', 'placeholder', 'list_manual', 'list_full', 'render'];
const previewIcons = ['code', 'minus-square', 'list', 'check-square', 'eye'];
const preferenceKey = 'supjx.sources.editor.previewMode';
const nestedDocuments = new WeakSet();
const jsonDocuments = new WeakSet();
let rememberedMode;
const uiOptions = () => window.Joomla?.getOptions('plg_editorsxtd_sourcesbutton.inline', {}) || {};

export function rememberPreviewMode(mode) {
  if (!previewModes.includes(mode)) return;
  rememberedMode = mode;
  try { window.localStorage.setItem(preferenceKey, mode); } catch { /* Optional browser preference. */ }
}

function preferredMode(settings) {
  try {
    const stored = window.localStorage.getItem(preferenceKey);
    if (previewModes.includes(stored)) return stored;
  } catch { /* Keep the page-local preference if storage is blocked. */ }
  return rememberedMode || settings.previewMode || 'placeholder';
}

export function tokenNode(doc, token, mode, settings = uiOptions()) {
  bindJsonSelection(doc);
  mode ??= preferredMode(settings);
  if (!previewModes.includes(mode)) mode = 'placeholder';
  const translate = key => settings.i18n?.[key] || key;
  const node = doc.createElement('span'); node.className = 'sources-editor-token mceNonEditable sources-editor-token--' + mode;
  node.dataset.sourcesToken = token.raw; node.dataset.sourcesPreviewMode = mode;
  node.dataset.sourcesPresentation = validatePresentation(settings.presentation ?? documentPresentation(doc));
  node.contentEditable = 'false'; node.setAttribute('data-mce-contenteditable', 'false'); node.setAttribute('data-mce-resize', 'false');
  node.tabIndex = 0; node.setAttribute('role', 'group');
  const heading = doc.createElement('span'); heading.className = 'sources-editor-token__header';
  const label = doc.createElement('span'); label.className = 'sources-editor-token__label';
  const container = token.config, blocks = container.blocks, single = blocks.length === 1;
  const title = doc.createElement('strong'), summary = doc.createElement('span'); summary.className = 'sources-editor-token__summary';
  title.textContent = (container.display === 'merged' ? container.presentation.title : single ? blocks[0].title : '')
    || (single ? blocks[0].title : '') || translate('sources');
  node.setAttribute('aria-label', title.textContent);
  if (single) {
    const block = blocks[0], items = block.type === 'articles' ? block.articles : block.filters[block.type];
    summary.textContent = [translate(container.display === 'merged' ? container.presentation.layout : block.presentation.layout),
      translate('type_' + block.type), items.length + ' ' + translate('selected')].join(' · ');
  } else {
    const count = blocks.reduce((total, block) => total + (block.type === 'articles' ? block.articles.length : 0), 0);
    summary.textContent = [translate(container.display), blocks.length + ' ' + translate('previewBlocks'),
      count + ' ' + translate('previewSelectedArticles')].join(' · ');
  }
  label.append(title, summary);
  const controls = doc.createElement('span'); controls.className = 'sources-editor-token__controls';
  const toolbar = doc.createElement('span'); toolbar.className = 'sources-editor-token__toolbar';
  const button = (key, icon, attribute) => {
    const control = doc.createElement('button'); control.type = 'button'; control.title = translate(key);
    control.setAttribute('aria-label', translate(key)); control.className = 'sources-editor-command'; control.setAttribute(attribute, key);
    const glyph = doc.createElement('span'); glyph.className = 'fas fa-' + icon; glyph.setAttribute('aria-hidden', 'true'); control.append(glyph);
    return control;
  };
  previewModes.forEach((id, index) => {
    const control = button(id, previewIcons[index], 'data-sources-mode'); control.setAttribute('aria-pressed', String(id === mode)); toolbar.append(control);
  });
  controls.append(toolbar, button('settings', 'cog', 'data-sources-settings'), button('edit', 'pencil-alt', 'data-sources-edit'));
  heading.append(label, controls); node.append(heading);
  if (mode === 'none') {
    const json = doc.createElement('textarea'); json.className = 'sources-editor-token__json'; json.readOnly = true;
    json.classList.add('mceEditable'); json.contentEditable = 'true'; json.setAttribute('data-mce-contenteditable', 'true');
    json.setAttribute('aria-label', 'Sources JSON'); json.value = token.raw; json.textContent = token.raw;
    node.append(json);
  } else if (['list_manual', 'list_full', 'render'].includes(mode)) {
    const body = doc.createElement('span'); body.className = 'sources-editor-token__body'; body.textContent = '…'; node.append(body);
  }
  return node;
}

function bindJsonSelection(doc) {
  const view = doc.defaultView;
  if (!view || jsonDocuments.has(doc)) return;
  jsonDocuments.add(doc);
  // Let the native readonly control handle selection/copy, not the editor's atomic-widget shortcuts.
  for (const type of ['pointerdown', 'mousedown', 'click', 'dblclick', 'keydown', 'keyup']) {
    view.addEventListener(type, event => {
      if (event.target.matches?.('textarea.sources-editor-token__json')) event.stopImmediatePropagation();
    }, true);
  }
  if (view.CSS?.supports('field-sizing', 'content')) return;
  // Older browsers need an intrinsic-height fallback; native selection remains unchanged.
  let pending = false;
  const schedule = () => {
    if (pending) return;
    pending = true;
    view.requestAnimationFrame(() => {
      pending = false;
      doc.querySelectorAll('textarea.sources-editor-token__json').forEach(control => {
        if (!control.clientWidth) return;
        const style = view.getComputedStyle(control);
        control.style.height = '0px';
        control.style.height = (control.scrollHeight + parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth)) + 'px';
      });
    });
  };
  const mutations = new view.MutationObserver(schedule);
  const resize = view.ResizeObserver ? new view.ResizeObserver(schedule) : null;
  const observe = () => {
    mutations.observe(doc, { childList: true, subtree: true });
    resize?.observe(doc.documentElement); schedule();
  };
  view.addEventListener('resize', schedule); view.addEventListener('pageshow', observe);
  view.addEventListener('pagehide', () => { mutations.disconnect(); resize?.disconnect(); });
  observe();
}

function previewPayload(value) {
  if (value && typeof value === 'object' && typeof value.html === 'string') return value;
  if (Array.isArray(value)) { for (const entry of value) { const found = previewPayload(entry); if (found) return found; } }
  if (value && typeof value === 'object') { for (const key of ['data', 'result', 'results']) { const found = previewPayload(value[key]); if (found) return found; } }
  return null;
}

export function previewErrorKey(code) {
  const dependency = /^(content|email)_plugin_(missing|disabled|restricted|public_required|unavailable|incomplete)$/.exec(code || '');
  if (dependency) return 'preview' + (dependency[1]==='content' ? 'Content' : 'Email') + ({missing:'Missing',disabled:'Disabled',restricted:'Restricted',public_required:'PublicRequired',unavailable:'Unavailable',incomplete:'Incomplete'})[dependency[2]];
  return ({plugin_disabled:'previewDisabled',invalid_configuration:'previewInvalid',render_failed:'previewFailed',email_budget:'previewBudget'})[code] || 'previewFailed';
}

export function requestTokenPreview(node, settings = uiOptions()) {
  const body = node.querySelector('.sources-editor-token__body');
  if (!body || !settings.ajaxUrl) return;
  const token = scan(node.dataset.sourcesToken)[0]; if (!token?.config) return;
  const presentation = validatePresentation(node.dataset.sourcesPresentation || 'site');
  const request = startPreviewRequest(node, previewRequestKey(node));
  if (!request) return;
  const translate = key => settings.i18n?.[key] || key;
  const data = new URLSearchParams({ configuration: JSON.stringify(token.config), mode: node.dataset.sourcesPreviewMode, presentation, [settings.csrfToken]: '1' });
  return fetch(settings.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin', signal: request.signal })
    .catch(() => { throw new Error(translate('previewNetwork')); })
    .then(async response => {
      if ([401, 403].includes(response.status)) throw new Error(translate('previewPermission'));
      if (!response.ok) throw new Error(translate('previewFailed') + ' (HTTP ' + response.status + ')');
      try { return await response.json(); } catch { throw new Error(translate('previewResponse')); }
    }).then(json => {
      if (!request.current()) return;
      const result = previewPayload(json);
      if (result?.ok === true && typeof result.html === 'string') {
        if ((presentation === 'email' && result.presentation !== 'email') || (result.presentation && result.presentation !== presentation)) throw new Error(translate('previewResponse'));
        disposePreviewSurface(body);
        if (presentation === 'email' && node.dataset.sourcesPreviewMode === 'render') publishEmailSurface(body, result.html);
        else {
          if (presentation === 'site') appendPreviewStyles(node.ownerDocument, result.styles, document.baseURI);
          body.innerHTML = result.html; mountNestedPreviews(body, { ...settings, presentation });
        }
      } else {
        let key = result ? previewErrorKey(result.code) : 'previewEmpty';
        if (!result && json?.success === false) key = json.message === 'Sources preview not authorised.' ? 'previewPermission' : 'previewFailed';
        body.textContent = translate(key);
      }
    }).catch(error => { if (request.current()) body.textContent = error.message || translate('previewFailed'); });
}

export function mountNestedPreviews(root, settings = uiOptions()) {
  const doc = root.ownerDocument || root;
  bindJsonSelection(doc);
  hydrateEmailSurfaces(doc);
  root.querySelectorAll('[data-sources-preview-token]').forEach(marker => {
    const token = scan(marker.dataset.sourcesPreviewToken)[0]; if (!token?.config) return;
    const node = readOnlyPreviewBlock(doc, token, 'list_manual', marker.innerHTML, settings);
    marker.replaceWith(node);
  });
  if (nestedDocuments.has(doc)) return;
  nestedDocuments.add(doc);
  doc.addEventListener('click', event => {
    const node = event.target.closest?.('.sources-editor-token');
    if (!node?.dataset.sourcesNestedPreview || !event.target.closest('.sources-editor-command')) return;
    event.preventDefault(); event.stopImmediatePropagation();
    if (event.target.closest('[data-sources-settings]')) { node.classList.toggle('sources-editor-token--toolbar-open'); return; }
    const control = event.target.closest('[data-sources-mode]'); if (!control) return;
    const token = scan(node.dataset.sourcesToken)[0]; if (!token?.config) return;
    const next = tokenNode(doc, token, control.dataset.sourcesMode, { ...settings, presentation: node.dataset.sourcesPresentation || 'site' }); next.dataset.sourcesNestedPreview = '1';
    next.classList.add('sources-editor-token--toolbar-open'); next.querySelector('[data-sources-edit]').remove();
    disposeTokenPreview(node); node.replaceWith(next); requestTokenPreview(next, settings);
  }, true);
}

export function readOnlyPreviewBlock(doc, token, mode, html, settings = uiOptions()) {
  const node = tokenNode(doc, token, mode, settings); node.dataset.sourcesNestedPreview = '1';
  node.querySelector('[data-sources-edit]').remove();
  const body = node.querySelector('.sources-editor-token__body');
  if (body) {
    if (node.dataset.sourcesPresentation === 'email' && mode === 'render') publishEmailSurface(body, html);
    else body.innerHTML = html;
  }
  return node;
}

export function disposeTokenPreview(node) {
  cancelPreviewRequest(node); disposePreviewSurface(node);
  node.querySelectorAll('.sources-editor-token').forEach(cancelPreviewRequest);
}

export function refreshPresentation(doc, presentation) {
  presentation = validatePresentation(presentation);
  for (const node of doc.querySelectorAll('.sources-editor-token')) {
    if (node.closest('.sources-editor-token__body')) continue;
    disposeTokenPreview(node); node.dataset.sourcesPresentation = presentation;
    const body = node.querySelector('.sources-editor-token__body'); if (body) body.replaceChildren();
    requestTokenPreview(node);
  }
}
