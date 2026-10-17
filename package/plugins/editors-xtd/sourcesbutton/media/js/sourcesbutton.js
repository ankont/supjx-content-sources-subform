import { JoomlaEditor, JoomlaEditorButton } from 'editor-api';
import { scan, serialize } from './inline.js';
import { SourcesBuilder } from './builder.js';
import { startPreviewRequest, appendPreviewStyles } from './preview-request.js';

const config = window.Joomla?.getOptions('plg_editorsxtd_sourcesbutton.inline', {}) || {};
const t = key => config.i18n?.[key] || key;
const modes = ['none', 'placeholder', 'list_manual', 'list_full', 'render'];
const modeIcons = ['code', 'minus-square', 'list', 'check-square', 'eye'];
const bound = new WeakSet();
const documentBindings = new WeakSet();
let opening = false;

function command(doc, key, icon, attribute) {
  const button = doc.createElement('button'); button.type = 'button'; button.title = t(key); button.setAttribute('aria-label', t(key));
  button.className = 'sources-editor-command'; button.setAttribute(attribute, key);
  const glyph = doc.createElement('span'); glyph.className = 'fas fa-' + icon; glyph.setAttribute('aria-hidden', 'true'); button.append(glyph); return button;
}

export function tokenNode(doc, token, mode = config.previewMode || 'placeholder') {
  if (!modes.includes(mode)) mode = 'placeholder';
  const node = doc.createElement('span'); node.className = 'sources-editor-token mceNonEditable sources-editor-token--' + mode;
  node.dataset.sourcesToken = token.raw; node.dataset.sourcesPreviewMode = mode;
  node.contentEditable = 'false'; node.setAttribute('data-mce-contenteditable', 'false'); node.setAttribute('data-mce-resize', 'false');
  node.tabIndex = 0; node.setAttribute('role', 'group'); node.setAttribute('aria-label', config.badgeLabel || 'Sources');
  const heading = doc.createElement('span'); heading.className = 'sources-editor-token__header';
  const label = doc.createElement('span'); label.className = 'sources-editor-token__label';
  label.textContent = mode === 'none' ? token.raw : (config.badgeLabel || 'Sources') + ' · ' + token.config.blocks.length + ' · ' + t(token.config.display);
  const controls = doc.createElement('span'); controls.className = 'sources-editor-token__controls';
  const toolbar = doc.createElement('span'); toolbar.className = 'sources-editor-token__toolbar';
  modes.forEach((id, index) => {
    const control = command(doc, id, modeIcons[index], 'data-sources-mode'); control.setAttribute('aria-pressed', String(id === mode)); toolbar.append(control);
  });
  controls.append(toolbar, command(doc, 'settings', 'cog', 'data-sources-settings'), command(doc, 'edit', 'pencil-alt', 'data-sources-edit'));
  heading.append(label, controls); node.append(heading);
  if (['list_manual', 'list_full', 'render'].includes(mode)) {
    const body = doc.createElement('span'); body.className = 'sources-editor-token__body'; body.textContent = '…'; node.append(body);
  }
  return node;
}

export function restoreTokens(root) {
  root.querySelectorAll('.sources-editor-token[data-sources-token]').forEach(node => {
    node.replaceWith(node.ownerDocument.createTextNode(node.dataset.sourcesToken));
  });
}

export function cleanHtml(html, doc = document) {
  const template = doc.createElement('template'); template.innerHTML = html; restoreTokens(template.content); return template.innerHTML;
}

function decodeSerializedTokens(html) {
  // TinyMCE/JCE may encode Unicode as named HTML entities even after PreProcess.
  // Decode only token text and protect manually entered JSON from Joomla's HTML filters too.
  for (const token of scan(html).reverse()) {
    if (!token.config) continue;
    let decoded = token.raw;
    if (decoded.includes('&')) {
      const textarea = document.createElement('textarea'); textarea.innerHTML = decoded; decoded = textarea.value;
    }
    if (scan(decoded)[0]?.config) {
      decoded = decoded.replace(/[<>&]/g, char => '\\u' + char.charCodeAt(0).toString(16).padStart(4, '0'));
      html = html.slice(0, token.start) + decoded + html.slice(token.end);
    }
  }
  return html;
}

function decorateHtml(html, doc) {
  for (const token of scan(html).reverse()) {
    if (token.config) html = html.slice(0, token.start) + tokenNode(doc, token).outerHTML + html.slice(token.end);
  }
  return html;
}

function payload(value) {
  if (value && typeof value === 'object' && typeof value.html === 'string') return value;
  if (Array.isArray(value)) { for (const entry of value) { const found = payload(entry); if (found) return found; } }
  if (value && typeof value === 'object') { for (const key of ['data', 'result', 'results']) { const found = payload(value[key]); if (found) return found; } }
  return null;
}

function preview(node) {
  if (!node.querySelector('.sources-editor-token__body') || !config.ajaxUrl) return;
  const token = scan(node.dataset.sourcesToken)[0]; if (!token?.config) return;
  const request = startPreviewRequest(node, JSON.stringify([node.dataset.sourcesToken, node.dataset.sourcesPreviewMode]));
  if (!request) return;
  const data = new URLSearchParams({ configuration: JSON.stringify(token.config), mode: node.dataset.sourcesPreviewMode, [config.csrfToken]: '1' });
  fetch(config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin', signal: request.signal })
    .catch(() => { throw new Error(t('previewNetwork')); })
    .then(async response => {
      if ([401, 403].includes(response.status)) throw new Error(t('previewPermission'));
      if (!response.ok) throw new Error(t('previewFailed') + ' (HTTP ' + response.status + ')');
      try { return await response.json(); } catch { throw new Error(t('previewResponse')); }
    })
    .then(json => {
      if (!request.current()) return;
      const result = payload(json), body = node.querySelector('.sources-editor-token__body');
      if (result?.ok === true && typeof result.html === 'string') {
        appendPreviewStyles(node.ownerDocument, result.styles, document.baseURI);
        body.innerHTML = result.html;
      }
      else {
        const keys = { plugin_disabled: 'previewDisabled', invalid_configuration: 'previewInvalid', render_failed: 'previewFailed' };
        let key = result ? keys[result.code] || 'previewFailed' : 'previewEmpty';
        if (!result && json?.success === false) key = json.message === 'Sources preview not authorised.' ? 'previewPermission' : 'previewFailed';
        body.textContent = t(key);
      }
    }).catch(error => { if (request.current()) node.querySelector('.sources-editor-token__body').textContent = error.message || t('previewFailed'); });
}

export function decorateDocument(doc) {
  if (!doc?.body) return;
  for (const href of [config.contentCss, config.fontAwesomeUrl].filter(Boolean)) {
    const url = new URL(href, document.baseURI);
    const existing = [...doc.querySelectorAll('link[data-sources-css]')].find(link => new URL(link.href, doc.baseURI).pathname === url.pathname);
    if (existing) { if (existing.href !== url.href) existing.href = url.href; }
    else {
      const link = doc.createElement('link'); link.rel = 'stylesheet'; link.href = url.href; link.dataset.sourcesCss = '1'; doc.head.append(link);
    }
  }
  const walker = doc.createTreeWalker(doc.body, 4, { acceptNode: node =>
    node.parentElement?.closest('.sources-editor-token,script,style,textarea,pre,code') || !node.data.includes('{sources:') ? 2 : 1 });
  const nodes = []; while (walker.nextNode()) nodes.push(walker.currentNode);
  for (const text of nodes) {
    const tokens = scan(text.data).filter(token => token.config); if (!tokens.length) continue;
    const fragment = doc.createDocumentFragment(); let offset = 0;
    for (const token of tokens) { fragment.append(doc.createTextNode(text.data.slice(offset, token.start)), tokenNode(doc, token)); offset = token.end; }
    fragment.append(doc.createTextNode(text.data.slice(offset))); text.replaceWith(fragment);
  }
  doc.querySelectorAll('.sources-editor-token').forEach(node => {
    // Editors can sanitize button elements during SetContent. Restore editor-only controls after parsing.
    const controls = node.querySelector('.sources-editor-token__controls');
    if (!controls || controls.querySelectorAll('[data-sources-mode]').length !== modes.length ||
        controls.querySelector('button[disabled]') || !controls.querySelector('[data-sources-edit] .fas')) {
      const token = scan(node.dataset.sourcesToken)[0];
      if (token?.config) {
        const fresh = tokenNode(doc, token, node.dataset.sourcesPreviewMode);
        const header = node.querySelector('.sources-editor-token__header');
        if (header) header.replaceWith(fresh.firstElementChild);
        else node.prepend(fresh.firstElementChild);
      }
    }
    preview(node);
  });
}

function on(editor, name, callback) {
  // JCE's compatibility .on() copies event data; use its native dispatcher for writable content events.
  if (editor['on' + name]?.add) editor['on' + name].add((_editor, event) => callback(event || {}));
  else if (typeof editor.on === 'function') editor.on(name, callback);
}

export function bindEditor(editor) {
  if (!editor?.getDoc || bound.has(editor)) return;
  bound.add(editor);
  const decorate = () => {
    if (editor.isHidden?.()) return;
    const doc = editor.getDoc(); if (!doc?.body) return;
    if (!documentBindings.has(doc)) {
      documentBindings.add(doc);
      doc.body.addEventListener('click', event => {
        const target = event.target.closest?.('.sources-editor-token'); if (!target) return;
        if (event.target.closest('.sources-editor-command')) event.stopPropagation();
        if (event.target.closest('[data-sources-settings]')) { event.preventDefault(); target.classList.toggle('sources-editor-token--toolbar-open'); }
        else if (event.target.closest('[data-sources-edit]')) { event.preventDefault(); openBuilder(null, editor, target); }
        else {
          const control = event.target.closest('[data-sources-mode]');
          if (control) {
            event.preventDefault(); const token = scan(target.dataset.sourcesToken)[0]; if (!token?.config) return;
            const replacement = tokenNode(doc, token, control.dataset.sourcesMode); replacement.classList.add('sources-editor-token--toolbar-open');
            target.replaceWith(replacement); preview(replacement);
          }
        }
      }, true);
      doc.body.addEventListener('dblclick', event => {
        const target = event.target.closest?.('.sources-editor-token');
        if (target && !event.target.closest('button,a')) { event.preventDefault(); openBuilder(null, editor, target); }
      });
      doc.body.addEventListener('keydown', event => {
        const target = event.target.closest?.('.sources-editor-token');
        if (target && event.key === 'Enter' && !event.target.closest('button')) { event.preventDefault(); openBuilder(null, editor, target); }
      });
    }
    decorateDocument(doc);
  };
  for (const name of ['Init', 'SetContent', 'LoadContent', 'Change', 'Input', 'Paste', 'Undo', 'Redo']) on(editor, name, () => setTimeout(decorate, 0));
  on(editor, 'PreProcess', event => { if (event.node) restoreTokens(event.node); });
  on(editor, 'GetContent', event => {
    if (typeof event.content !== 'string') return;
    if (event.content.includes('data-sources-token')) event.content = cleanHtml(event.content);
    event.content = decodeSerializedTokens(event.content);
  });
  on(editor, 'BeforeSetContent', event => {
    if (typeof event.content === 'string' && event.content.includes('{sources:') && !event.content.includes('data-sources-token')) {
      event.content = decorateHtml(event.content, editor.getDoc() || document);
    }
  });
  decorate();
}

function rawEditor(joomlaEditor) {
  const raw = joomlaEditor?.getRawInstance?.();
  if (raw?.getDoc) return raw;
  const id = joomlaEditor?.getId?.();
  for (const manager of [window.tinymce, window.tinyMCE]) {
    const editor = id ? manager?.get?.(id) : null; if (editor) return editor;
  }
  return null;
}

export async function openBuilder(joomlaEditor, visualEditor = rawEditor(joomlaEditor), target = null) {
  if (opening) return;
  opening = true;
  try {
    const visual = visualEditor && !visualEditor.isHidden?.() ? visualEditor : null;
    let original = null, selected = null, bookmark = null, range = null, snapshot;
    let textarea;
    if (visual) {
      bindEditor(visual);
      target ||= visual.selection.getNode()?.closest?.('.sources-editor-token');
      if (target) original = scan(target.dataset.sourcesToken)[0];
      snapshot = visual.getContent(); bookmark = visual.selection.getBookmark(2, true);
    } else {
      if (!joomlaEditor?.getValue || !joomlaEditor?.replaceSelection) throw new Error(t('unavailable'));
      snapshot = joomlaEditor.getValue();
      const id = joomlaEditor.getId?.();
      const sourceArea = document.getElementById(id + '_editor_source_textarea');
      textarea = sourceArea?.getClientRects().length ? sourceArea : document.getElementById(id);
      const raw = joomlaEditor.getRawInstance?.();
      if (raw?.state?.selection?.main) { const main = raw.state.selection.main; range = { from: main.from, to: main.to }; }
      else if (textarea && textarea.selectionStart != null && !textarea.hidden && textarea.getClientRects().length) range = { from: textarea.selectionStart, to: textarea.selectionEnd };
      if (range) original = scan(snapshot).find(token => token.start <= range.from && token.end >= range.to);
      else {
        selected = joomlaEditor.getSelection?.();
        const matches = scan(snapshot).filter(token => token.raw === selected);
        if (matches.length === 1) { original = matches[0]; range = { from: original.start, to: original.end }; }
      }
    }
    if (original && !original.config) throw new Error(t('invalid'));
    const builder = new SourcesBuilder(original?.config, !!original);
    const result = await builder.open(); if (!result) return;
    const token = serialize(result);
    if (visual) {
      if (visual.getContent() !== snapshot || (target && (!target.isConnected || target.dataset.sourcesToken !== original.raw))) throw new Error(t('conflict'));
      const commit = () => {
        visual.focus();
        if (target) {
          const next = tokenNode(visual.getDoc(), scan(token)[0], target.dataset.sourcesPreviewMode);
          target.replaceWith(next); visual.selection.select(next); preview(next);
        } else { visual.selection.moveToBookmark(bookmark); visual.insertContent(tokenNode(visual.getDoc(), scan(token)[0]).outerHTML); }
        visual.nodeChanged?.(); visual.setDirty?.(true); decorateDocument(visual.getDoc());
      };
      if (visual.undoManager?.transact) visual.undoManager.transact(commit); else { visual.undoManager?.add?.(); commit(); visual.undoManager?.add?.(); }
      visual.save?.();
    } else {
      if (joomlaEditor.getValue() !== snapshot) throw new Error(t('conflict'));
      if (original) range = { from: original.start, to: original.end };
      if (range) joomlaEditor.setValue(snapshot.slice(0, range.from) + token + snapshot.slice(range.to));
      else joomlaEditor.replaceSelection(token);
      textarea?.dispatchEvent(new Event('input', { bubbles: true }));
    }
  } catch (error) {
    window.Joomla?.renderMessages?.({ error: [error.message] });
    if (!window.Joomla?.renderMessages) window.alert(error.message);
  } finally { opening = false; }
}

JoomlaEditorButton.registerAction('sourcesbutton:insert', editor => openBuilder(editor));
function discover() {
  const editors = new Set();
  for (const manager of [window.tinymce, window.tinyMCE]) {
    for (const editor of Object.values(manager?.EditorManager?.editors || manager?.editors || {})) if (editor?.getDoc) editors.add(editor);
  }
  const active = JoomlaEditor.getActive?.(); const raw = rawEditor(active); if (raw) editors.add(raw);
  editors.forEach(bindEditor);
}
document.addEventListener('DOMContentLoaded', discover);
document.addEventListener('joomla:updated', discover);
for (const manager of [window.tinymce, window.tinyMCE]) {
  manager?.on?.('AddEditor', event => bindEditor(event.editor));
  manager?.onAddEditor?.add?.((_manager, editor) => bindEditor(editor));
}
// JCE can register after Joomla's module assets without dispatching AddEditor to this window.
setInterval(discover, 1200);
discover();
