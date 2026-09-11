import { JoomlaEditorButton } from 'editor-api';

(function () {
  if (window.__SourcesButtonLoaded) return;
  window.__SourcesButtonLoaded = true;

  const CONFIG = window.SuperSoftSourcesButtonConfig || {};
  const I18N = CONFIG.i18n || {};
  const TOKEN_NAME = String(CONFIG.tokenName || 'sources').trim() || 'sources';
  const BADGE_LABEL = String(CONFIG.badgeLabel || 'Sources').trim() || 'Sources';
  const BADGE_DISPLAY = ['label_token', 'label_only', 'token_only'].includes(CONFIG.badgeDisplay) ? CONFIG.badgeDisplay : 'label_token';
  const PREVIEW_MODE = ['none', 'placeholder', 'list_manual', 'list_full', 'render'].includes(CONFIG.previewMode) ? CONFIG.previewMode : 'placeholder';
  const AJAX_URL = String(CONFIG.ajaxUrl || '').trim();
  const CSRF_TOKEN = String(CONFIG.csrfToken || '').trim();
  const FONT_AWESOME_URL = String(CONFIG.fontAwesomeUrl || '').trim();
  const CONFIG_ARTICLE_ID = parseInt(CONFIG.articleId || '0', 10);
  const previewCache = new Map();
  const TOKEN_RE = new RegExp('\\{\\s*' + escapeRegExp(TOKEN_NAME) + '\\s*(?::\\s*(rest|[1-9]\\d*)\\s*)?\\}', 'gi');
  const TOKEN_EXACT_RE = new RegExp('^\\s*\\{\\s*' + escapeRegExp(TOKEN_NAME) + '\\s*(?::\\s*(rest|[1-9]\\d*)\\s*)?\\}\\s*$', 'i');

  function t(key, fallback) { return I18N[key] || fallback; }
  function escapeRegExp(value) { return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
  function escapeAttr(value) { return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
  function escapeHtml(value) { return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

  function canUseServerPreview(mode) {
    return AJAX_URL && (mode === 'list_manual' || mode === 'list_full' || mode === 'render');
  }

  function getArticleId() {
    const selectors = [
      '#jform_id',
      'input[name=\'jform[a_id]\']',
      'input[name=\'a_id\']',
      'input[name="jform[id]"]',
      'input[name="id"]',
      'input[name="cid[]"]'
    ];

    for (let i = 0; i < selectors.length; i += 1) {
      const field = document.querySelector(selectors[i]);
      const id = field ? parseInt(field.value || '0', 10) : 0;
      if (Number.isFinite(id) && id > 0) return id;
    }

    if (window.Joomla && typeof window.Joomla.getOptions === 'function') {
      const optionKeys = ['com_content.article', 'com_content.edit.article', 'com_content.edit.item', 'joomla.content.article'];
      for (let i = 0; i < optionKeys.length; i += 1) {
        const options = window.Joomla.getOptions(optionKeys[i]) || {};
        const id = parseInt(options.id || options.item_id || options.article_id || '0', 10);
        if (Number.isFinite(id) && id > 0) return id;
      }
    }

    const params = new URLSearchParams(window.location.search || '');
    const urlId = parseInt(params.get('a_id') || params.get('id') || params.get('cid[]') || '0', 10);
    if (Number.isFinite(urlId) && urlId > 0) return urlId;

    const forms = document.querySelectorAll('form[action]');
    for (let i = 0; i < forms.length; i += 1) {
      try {
        const action = new URL(forms[i].getAttribute('action') || '', window.location.href);
        const actionId = parseInt(action.searchParams.get('a_id') || action.searchParams.get('id') || '0', 10);
        if (Number.isFinite(actionId) && actionId > 0) return actionId;
      } catch (error) {
        // Ignore malformed form actions and continue with the remaining fallbacks.
      }
    }

    return Number.isFinite(CONFIG_ARTICLE_ID) && CONFIG_ARTICLE_ID > 0 ? CONFIG_ARTICLE_ID : 0;
  }

  function normalisePreviewPayload(json) {
    return findPreviewPayload(json, 0);
  }

  function findPreviewPayload(value, depth) {
    if (!value || depth > 8) return null;

    if (typeof value === 'object' && !Array.isArray(value) && Object.prototype.hasOwnProperty.call(value, 'html')) {
      return value;
    }

    if (Array.isArray(value)) {
      for (let i = 0; i < value.length; i += 1) {
        const found = findPreviewPayload(value[i], depth + 1);
        if (found) return found;
      }
      return null;
    }

    if (typeof value !== 'object') return null;

    const preferredKeys = ['data', 'result', 'results', 'response', 'payload'];
    for (let i = 0; i < preferredKeys.length; i += 1) {
      if (Object.prototype.hasOwnProperty.call(value, preferredKeys[i])) {
        const found = findPreviewPayload(value[preferredKeys[i]], depth + 1);
        if (found) return found;
      }
    }

    const keys = Object.keys(value);
    for (let i = 0; i < keys.length; i += 1) {
      const found = findPreviewPayload(value[keys[i]], depth + 1);
      if (found) return found;
    }

    return null;
  }

  function tokenFromChoice(choice, blockNumber) {
    if (choice === 'rest') return '{' + TOKEN_NAME + ':rest}';
    if (choice === 'single') return '{' + TOKEN_NAME + ':' + Math.max(1, parseInt(blockNumber || 1, 10) || 1) + '}';
    return '{' + TOKEN_NAME + '}';
  }

  function normalizeToken(value) {
    const text = String(value || '').trim();
    return TOKEN_EXACT_RE.test(text) ? text : '';
  }

  function tokenInfo(token) {
    TOKEN_RE.lastIndex = 0;
    const match = TOKEN_RE.exec(token);
    const arg = match && match[1] ? match[1].toLowerCase() : '';
    if (arg === 'rest') return { mode: 'rest', label: BADGE_LABEL + ': rest' };
    if (arg) return { mode: 'single', label: BADGE_LABEL + ': block ' + arg, block: arg };
    return { mode: 'all', label: BADGE_LABEL };
  }

  function badgeText(token) {
    if (BADGE_DISPLAY === 'label_only') return tokenInfo(token).label;
    if (BADGE_DISPLAY === 'token_only') return token;
    return tokenInfo(token).label + ' ' + token;
  }

  function previewBody(token, mode) {
    const currentMode = mode || PREVIEW_MODE;
    if (currentMode === 'placeholder') return badgeText(token);
    if (currentMode === 'render' || currentMode === 'list_manual' || currentMode === 'list_full') return t('previewLoading', 'Loading Sources preview...');
    return badgeText(token);
  }

  function classForMode(mode) {
    if (mode === 'none') return 'sources-editor-token sources-editor-token--raw';
    if (mode === 'render') return 'sources-editor-token sources-editor-token--notice mceNonEditable';
    if (mode === 'list_manual' || mode === 'list_full') return 'sources-editor-token sources-editor-token--preview mceNonEditable';
    return 'sources-editor-token sources-editor-token--placeholder mceNonEditable';
  }

  function tagForMode(mode) {
    return mode === 'none' ? 'span' : 'div';
  }

  const ICONS = {
    none: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z" fill="currentColor"/></svg>',
    placeholder: '<svg viewBox="0 0 24 24" width="16" height="16"><rect x="3" y="8" width="18" height="8" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
    list_manual: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z" fill="currentColor"/></svg>',
    list_full: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/></svg>',
    render: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" fill="currentColor"/></svg>',
    settings: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M19.43 12.98c.04-.32.07-.65.07-.98s-.02-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.37-.31-.6-.22l-2.49 1a7.3 7.3 0 0 0-1.69-.98L14.5 2.42A.5.5 0 0 0 14 2h-4a.5.5 0 0 0-.49.42l-.38 2.65c-.61.24-1.18.56-1.69.98l-2.49-1a.5.5 0 0 0-.6.22l-2 3.46a.5.5 0 0 0 .12.64l2.11 1.65a7.9 7.9 0 0 0 0 1.96l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46c.12.22.37.31.6.22l2.49-1c.51.4 1.08.73 1.69.98l.38 2.65c.04.24.25.42.49.42h4c.24 0 .45-.18.49-.42l.38-2.65c.61-.24 1.18-.56 1.69-.98l2.49 1c.23.08.48 0 .6-.22l2-3.46a.5.5 0 0 0-.12-.64l-2.11-1.65zM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5z" fill="currentColor"/></svg>'
  };

  const MODE_IDS = ['none', 'placeholder', 'list_manual', 'list_full', 'render'];
  const MODE_LABEL_KEYS = { none: 'modeNone', placeholder: 'modePlaceholder', list_manual: 'modeListManual', list_full: 'modeListFull', render: 'modeRender' };

  function modeLabel(id) { return t(MODE_LABEL_KEYS[id], id); }

  function previewControlsHtml(mode) {
    let toolbar = '<span class="sources-editor-token__toolbar" role="group" aria-label="' + escapeAttr(t('modeSettings', 'Preview mode')) + '">';
    MODE_IDS.forEach(id => {
      const active = id === mode ? ' sources-editor-toolbar-btn--active' : '';
      const title = modeLabel(id);
      toolbar += '<button type="button" class="sources-editor-toolbar-btn' + active + '" data-sources-switch-mode="' + id + '" title="' + escapeAttr(title) + '" aria-label="' + escapeAttr(title) + '">' + ICONS[id] + '</button>';
    });
    toolbar += '</span>';

    const settingsTitle = t('modeSettings', 'Preview mode');
    const toggle = '<button type="button" class="sources-editor-toolbar-btn sources-editor-toolbar-btn--settings" data-sources-toggle-toolbar="1" title="' + escapeAttr(settingsTitle) + '" aria-label="' + escapeAttr(settingsTitle) + '">' + ICONS.settings + '</button>';

    return '<span class="sources-editor-token__controls">' + toolbar + toggle + '</span>';
  }

  function previewHeaderHtml(token, mode) {
    return '<span class="sources-editor-token__header"><span>' + escapeHtml(badgeText(token)) + '</span>' + previewControlsHtml(mode) + '</span>';
  }

  function buildTokenHtml(token, overrideMode) {
    const mode = overrideMode || PREVIEW_MODE;
    const tag = tagForMode(mode);
    const mceAttrs = ' data-mce-contenteditable="false" data-mce-resize="false" data-mce-placeholder="1"';

    if (mode === 'none') {
      return '<span class="sources-editor-token sources-editor-token--raw-wrap mceNonEditable" contenteditable="false" data-sources-token="' + escapeAttr(token) + '" data-sources-preview-mode="none"><span class="sources-editor-raw-text" contenteditable="true" spellcheck="false">' + escapeHtml(token) + '</span>' + previewControlsHtml(mode) + '</span>';
    }

    if (mode === 'list_manual' || mode === 'list_full' || mode === 'render') {
      return '<' + tag + ' class="' + classForMode(mode) + '" contenteditable="false" data-sources-token="' + escapeAttr(token) + '" data-sources-preview-mode="' + mode + '"' + mceAttrs + '>' + previewHeaderHtml(token, mode) + '<span class="sources-editor-token__body">' + escapeHtml(previewBody(token, mode)) + '</span></' + tag + '>';
    }

    return '<' + tag + ' class="' + classForMode(mode) + '" contenteditable="false" data-sources-token="' + escapeAttr(token) + '" data-sources-preview-mode="' + mode + '"' + mceAttrs + '>' + escapeHtml(previewBody(token, mode)) + previewControlsHtml(mode) + '</' + tag + '>';
  }

  function ensureAdminStyles() {
    if (document.getElementById('sourcesbutton-admin-styles')) return;

    const style = document.createElement('style');
    style.id = 'sourcesbutton-admin-styles';
    style.textContent = [
      '.sourcesbutton-modal .modal-dialog{max-width:720px;}',
      '.sourcesbutton-modal .modal-content{border:0;border-radius:.85rem;box-shadow:0 1.25rem 3.25rem rgba(15,23,42,.2);overflow:hidden;}',
      '.sourcesbutton-modal-shell{position:relative;padding:1.55rem 1.75rem 1.15rem;background:#fff;}',
      '.sourcesbutton-close{position:absolute;top:.9rem;right:.9rem;z-index:2;}',
      '.sourcesbutton-hero{display:flex;align-items:center;gap:.85rem;margin-bottom:1.25rem;}',
      '.sourcesbutton-hero-icon{display:inline-flex;align-items:center;justify-content:center;width:2.85rem;height:2.85rem;border-radius:.75rem;background:#eef4ff;color:#1f5fd1;position:relative;}',
      '.sourcesbutton-hero-icon:before{content:"{";font-size:1.45rem;font-weight:800;line-height:1;}.sourcesbutton-hero-icon:after{content:"}";font-size:1.45rem;font-weight:800;line-height:1;}',
      '.sourcesbutton-title{margin:0;color:#142033;font-size:1.25rem;font-weight:750;line-height:1.15;}',
      '.sourcesbutton-subtitle{margin:.25rem 0 0;color:#526179;font-size:.92rem;line-height:1.35;}',
      '.sourcesbutton-choice-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin-bottom:1.05rem;}',
      '.sourcesbutton-choice{position:relative;display:flex;min-height:11.4rem;flex-direction:column;align-items:flex-start;margin:0;padding:1rem 1rem .95rem;border:1px solid #d6dee8;border-radius:.5rem;background:#fff;cursor:pointer;transition:border-color .15s ease,background-color .15s ease,box-shadow .15s ease,transform .15s ease;}',
      '.sourcesbutton-choice:hover{border-color:#8fb3d9;background:#fbfdff;}',
      '.sourcesbutton-choice--checked{border-color:#1f5fd1;background:#f7fbff;box-shadow:0 0 0 1px #1f5fd1;}',
      '.sourcesbutton-choice-input{position:absolute;opacity:0;pointer-events:none;}',
      '.sourcesbutton-choice-mark{position:absolute;top:.95rem;right:.95rem;width:1.05rem;height:1.05rem;border:2px solid #afbfd1;border-radius:999px;background:#fff;box-sizing:border-box;}',
      '.sourcesbutton-choice--checked .sourcesbutton-choice-mark{border:.32rem solid #1f5fd1;}',
      '.sourcesbutton-choice-icon{display:inline-flex;align-items:center;justify-content:center;width:2.85rem;height:2.85rem;margin-bottom:.95rem;border-radius:50%;background:#eef4ff;color:#1f5fd1;position:relative;}',
      '.sourcesbutton-choice--rest .sourcesbutton-choice-icon{background:#eaf8f0;color:#14834f;}',
      '.sourcesbutton-choice-icon:before{content:"";display:block;box-sizing:border-box;}.sourcesbutton-choice-icon--all:before{width:1.25rem;height:.25rem;border-radius:.12rem;background:currentColor;box-shadow:0 -.45rem 0 currentColor,0 .45rem 0 currentColor;}.sourcesbutton-choice-icon--single:before{width:1.35rem;height:1.35rem;border:3px solid currentColor;border-radius:.2rem;}.sourcesbutton-choice-icon--rest:before{width:.58rem;height:.58rem;border-radius:.16rem;background:currentColor;box-shadow:.82rem 0 0 currentColor,0 .82rem 0 currentColor,.82rem .82rem 0 currentColor;transform:translate(-.41rem,-.41rem);}',
      '.sourcesbutton-choice-content{min-width:0;}',
      '.sourcesbutton-choice strong{display:block;color:#142033;font-size:.95rem;font-weight:750;line-height:1.25;}',
      '.sourcesbutton-choice em{display:block;margin-top:.45rem;color:#526179;font-style:normal;font-size:.86rem;line-height:1.4;}',
      '.sourcesbutton-block-row{margin-top:.75rem;width:100%;}',
      '.sourcesbutton-block-row .form-label{display:inline-block;margin:0 0 -.15rem .75rem;padding:0 .35rem;background:#fff;color:#526179;font-size:.78rem;position:relative;z-index:1;}',
      '.sourcesbutton-block-row .form-control{height:2.45rem;border-radius:.45rem;border-color:#d6dee8;box-shadow:none;}',
      '.sourcesbutton-token-section{margin-top:.15rem;}',
      '.sourcesbutton-token-label{margin:0 0 .4rem;color:#23324a;font-size:.82rem;font-weight:700;}',
      '.sourcesbutton-token-preview{display:flex;align-items:center;min-height:2.25rem;padding:.45rem .55rem;border:1px solid #dfe6ee;border-radius:.45rem;background:#f8fafc;}',
      '.sourcesbutton-token-preview .sources-editor-token{margin:0;}',
      '.sourcesbutton-token-preview .sources-editor-token__controls{display:none;}',
      '.sourcesbutton-token-preview .sources-editor-raw-text{padding-right:0;}',
      '.sourcesbutton-note{display:none;}',
      '.sourcesbutton-note-icon{display:none;}',
      '.sourcesbutton-modal .modal-footer{padding:.85rem 1.75rem 1rem;border-top:1px solid #e7edf4;background:#fbfcfe;}',
      '.sourcesbutton-modal .modal-footer .btn{min-width:7rem;border-radius:.45rem;}',
      '@media (max-width: 760px){.sourcesbutton-modal .modal-dialog{max-width:calc(100% - 1rem);}.sourcesbutton-modal-shell{padding:1.4rem 1rem 1rem;}.sourcesbutton-choice-list{grid-template-columns:1fr;gap:.8rem;}.sourcesbutton-choice{min-height:0;}.sourcesbutton-modal .modal-footer{padding:1rem;}}'
    ].join('\n');

    (document.head || document.documentElement).appendChild(style);
  }

  function ensureContentStyles(doc) {
    if (!doc || doc.getElementById('sourcesbutton-editor-styles')) return;
    const style = doc.createElement('style');
    style.id = 'sourcesbutton-editor-styles';
    style.textContent = [
      '.sources-editor-token{display:inline-flex;align-items:center;gap:.35rem;margin:0 .12rem;padding:.16rem .45rem;border:1px solid #8fb3d9;border-radius:.3rem;background:#eef6ff;color:#1f4e79;font:600 .9em/1.3 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;white-space:nowrap;cursor:default;}',
      '.sources-editor-token--raw, .sources-editor-token--raw-wrap{border-style:dashed;background:#f5f7fa;color:#334e68;outline:0;}',
      '.sources-editor-token--raw{cursor:text;}',
      '.sources-editor-token--raw-wrap{cursor:default;}',
      '.sources-editor-token--raw:focus, .sources-editor-token--raw-wrap:focus-within{border-color:#2f70b7;background:#fff;box-shadow:0 0 0 2px rgba(47,112,183,.18);}',
      '.sources-editor-raw-text{cursor:text;outline:none;padding-right:.1rem;}',
      '.sources-editor-token--preview,.sources-editor-token--notice{display:flex;width:100%;box-sizing:border-box;flex-direction:column;align-items:stretch;gap:.5rem;margin:.75rem 0;padding:.75rem;white-space:normal;text-align:left;}',
      '.sources-editor-token--placeholder{display:flex;justify-content:space-between;width:100%;box-sizing:border-box;margin:.75rem 0;padding:.4rem .6rem;white-space:normal;}',
      '.sources-editor-token--notice{border-color:#d4b16a;background:#fff8e6;color:#6c4a00;}',
      '.sources-editor-token__header{display:flex;justify-content:space-between;align-items:center;font-weight:700;color:#1f4e79;}',
      '.sources-editor-token__controls{display:flex;gap:.15rem;align-items:center;margin-left:.5rem;}',
      '.sources-editor-token__toolbar,.sources-editor-token__toolbar-inline{display:none;gap:.15rem;align-items:center;}',
      '.sources-editor-token--toolbar-open .sources-editor-token__toolbar,.sources-editor-token--toolbar-open .sources-editor-token__toolbar-inline{display:flex;}',
      '.sources-editor-toolbar-btn{width:1.65rem;height:1.65rem;background:transparent;border:none;padding:.2rem;cursor:pointer;color:#8fb3d9;display:inline-flex;align-items:center;justify-content:center;border-radius:.2rem;}',
      '.sources-editor-toolbar-btn:hover{background:#eef6ff;color:#2f70b7;}',
      '.sources-editor-toolbar-btn--active{color:#1f4e79;background:#dbe8f5;}',
      '.sources-editor-toolbar-btn--settings{color:#5d7fa8;}',
      '.sources-editor-token__body{font-weight:500;color:#3d4d5c;width:100%;}',
      '.sources-editor-preview-list{display:grid;gap:.65rem;}',
      '.sources-editor-preview-block{padding:.45rem .55rem;border-left:3px solid #8fb3d9;background:#f8fbff;}',
      '.sources-editor-preview-block ul{margin:.25rem 0 .35rem 1.25rem;padding:0;}',
      '.sources-editor-preview-label{margin-top:.35rem;font-size:.85em;font-weight:700;color:#5d6b78;text-transform:uppercase;}',
      '.sources-editor-token code{font-weight:700;color:#1f4e79;background:transparent;padding:0;}'
    ].join('\n');
    (doc.head || doc.documentElement).appendChild(style);
  }

  function textNodeHasToken(node) {
    TOKEN_RE.lastIndex = 0;
    return node && node.nodeType === 3 && TOKEN_RE.test(node.nodeValue || '');
  }

  function makeTokenNode(doc, token, overrideMode) {
    const mode = overrideMode || PREVIEW_MODE;
    const tag = tagForMode(mode);
    const wrap = doc.createElement(tag);
    wrap.innerHTML = buildTokenHtml(token, overrideMode);
    return wrap.firstElementChild;
  }

  function setPreviewBody(node, html) {
    const body = node && node.querySelector ? node.querySelector('.sources-editor-token__body') : null;
    if (body && body.innerHTML !== html) body.innerHTML = html;
  }

  function ensureFontAwesome(doc) {
    if (!FONT_AWESOME_URL || !doc || !doc.head || !doc.querySelector) return;
    if (doc.querySelector('link[data-sourcesbutton-fontawesome],link[href*=joomla-fontawesome]')) return;

    const link = doc.createElement('link');
    link.rel = 'stylesheet';
    link.href = FONT_AWESOME_URL;
    link.setAttribute('data-sourcesbutton-fontawesome', '1');
    doc.head.appendChild(link);
  }

  function getPreviousTokens(node) {
    if (!node || !node.isConnected || !node.ownerDocument || !node.ownerDocument.querySelectorAll) return [];

    const nodes = Array.from(node.ownerDocument.querySelectorAll('.sources-editor-token[data-sources-token]'));
    const position = nodes.indexOf(node);
    if (position <= 0) return [];

    return nodes
      .slice(0, position)
      .map((item) => normalizeToken(item.getAttribute('data-sources-token') || ''))
      .filter(Boolean);
  }

  function refreshPreview(node) {
    if (!node || node.classList.contains('sources-editor-token--raw') || node.classList.contains('sources-editor-token--raw-wrap')) return;

    const mode = node.getAttribute('data-sources-preview-mode') || PREVIEW_MODE;

    if (!canUseServerPreview(mode)) {
      return;
    }

    if (mode === 'render') {
      ensureFontAwesome(node.ownerDocument);
    }

    const token = node.getAttribute('data-sources-token') || '';
    if (!normalizeToken(token)) {
      return;
    }

    const articleId = getArticleId();
    if (!articleId) {
      setPreviewBody(node, escapeHtml(t('saveToPreview', 'Save the article to preview the Sources output.')));
      return;
    }

    const previousTokens = getPreviousTokens(node);
    const contextKey = previousTokens.join('|');
    const cacheKey = articleId + '|' + mode + '|' + token + '|' + contextKey;
    if (node.getAttribute('data-sources-preview-key') === cacheKey && node.getAttribute('data-sources-preview-state') === 'loading') return;

    if (previewCache.has(cacheKey)) {
      setPreviewBody(node, previewCache.get(cacheKey));
      node.setAttribute('data-sources-preview-key', cacheKey);
      node.setAttribute('data-sources-preview-state', 'done');
      return;
    }

    node.setAttribute('data-sources-preview-key', cacheKey);

    node.setAttribute('data-sources-preview-state', 'loading');
    setPreviewBody(node, escapeHtml(t('previewLoading', 'Loading Sources preview...')));

    const params = new URLSearchParams();
    params.set('task', 'preview');
    params.set('article_id', String(articleId));
    params.set('token', token);
    params.set('previous_tokens', JSON.stringify(previousTokens));
    params.set('mode', mode);
    if (CSRF_TOKEN) params.set(CSRF_TOKEN, '1');

    fetch(AJAX_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: params.toString()
    })
      .then((response) => response.ok ? response.json() : Promise.reject(new Error('HTTP ' + response.status)))
      .then((json) => {
        const payload = normalisePreviewPayload(json);
        const html = payload && typeof payload.html === 'string' ? payload.html : escapeHtml(t('saveToPreview', 'Save the article to preview the Sources output.'));
        previewCache.set(cacheKey, html);
        setPreviewBody(node, html);
        node.setAttribute('data-sources-preview-state', 'done');
      })
      .catch((error) => {
        setPreviewBody(node, escapeHtml(t('saveToPreview', 'Save the article to preview the Sources output.')));
        node.setAttribute('data-sources-preview-state', 'error');
      });
  }

  function decorateTextNode(doc, node) {
    const text = node.nodeValue || '';
    TOKEN_RE.lastIndex = 0;
    if (!TOKEN_RE.test(text)) return;
    TOKEN_RE.lastIndex = 0;

    const frag = doc.createDocumentFragment();
    const tokenNodes = [];
    let last = 0;
    let match;
    while ((match = TOKEN_RE.exec(text)) !== null) {
      if (match.index > last) frag.appendChild(doc.createTextNode(text.slice(last, match.index)));
      const tokenNode = makeTokenNode(doc, match[0]);
      tokenNodes.push(tokenNode);
      frag.appendChild(tokenNode);
      last = match.index + match[0].length;
    }
    if (last < text.length) frag.appendChild(doc.createTextNode(text.slice(last)));
    node.parentNode.replaceChild(frag, node);
    tokenNodes.forEach((tokenNode) => refreshPreview(tokenNode));
  }

  function restoreTokens(doc) {
    if (!doc || !doc.querySelectorAll) return;
    doc.querySelectorAll('.sources-editor-token').forEach((node) => {
      let token = '';
      if (node.classList.contains('sources-editor-token--raw') || node.classList.contains('sources-editor-token--raw-wrap')) {
        const textNode = node.querySelector('.sources-editor-raw-text');
        token = normalizeToken((textNode ? textNode.textContent : node.textContent) || '');
      }
      if (!token) token = node.getAttribute('data-sources-token') || '';
      if (token) node.replaceWith(doc.createTextNode(token));
    });
  }

  function decorateDocument(doc) {
    if (!doc || !doc.body) return;
    ensureContentStyles(doc);
    const nodeFilter = doc.defaultView && doc.defaultView.NodeFilter ? doc.defaultView.NodeFilter : window.NodeFilter;
    if (!nodeFilter) return;

    doc.querySelectorAll('.sources-editor-token--raw, .sources-editor-token--raw-wrap').forEach((node) => {
      let token = '';
      const textNode = node.querySelector('.sources-editor-raw-text');
      token = normalizeToken((textNode ? textNode.textContent : node.textContent) || '');
      if (token) node.setAttribute('data-sources-token', token);
    });

    doc.querySelectorAll('.sources-editor-token:not(.sources-editor-token--raw):not(.sources-editor-token--raw-wrap)').forEach((node) => refreshPreview(node));

    const walker = doc.createTreeWalker(doc.body, nodeFilter.SHOW_TEXT, {
      acceptNode(node) {
        if (!textNodeHasToken(node)) return nodeFilter.FILTER_REJECT;
        const parent = node.parentElement;
        if (parent && parent.closest('.sources-editor-token')) return nodeFilter.FILTER_REJECT;
        return nodeFilter.FILTER_ACCEPT;
      }
    });

    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach((node) => decorateTextNode(doc, node));
  }

  function listTinyEditors() {
    if (!window.tinymce) return [];
    if (tinymce.EditorManager && Array.isArray(tinymce.EditorManager.editors)) return tinymce.EditorManager.editors;
    if (Array.isArray(tinymce.editors)) return tinymce.editors;
    if (tinymce.editors && typeof tinymce.editors === 'object') {
      try { return Object.values(tinymce.editors); } catch (e) {}
    }
    return [];
  }

  function bindEditorDoc(doc) {
    if (!doc) return;

    if (!doc._sourcesButtonToolbarBound) {
      doc._sourcesButtonToolbarBound = true;
      doc.body.addEventListener('click', (e) => {
        const toggle = e.target.closest && e.target.closest('[data-sources-toggle-toolbar]');
        if (toggle) {
          e.preventDefault();
          e.stopPropagation();
          const tokenNode = toggle.closest('.sources-editor-token');
          if (tokenNode) tokenNode.classList.toggle('sources-editor-token--toolbar-open');
          return;
        }

        const btn = e.target.closest && e.target.closest('[data-sources-switch-mode]');
        if (btn) {
          e.preventDefault();
          e.stopPropagation();
          const mode = btn.getAttribute('data-sources-switch-mode');
          const tokenNode = btn.closest('.sources-editor-token');
          if (tokenNode) {
            const token = tokenNode.getAttribute('data-sources-token') || tokenNode.textContent;
            const nextNode = makeTokenNode(doc, normalizeToken(token), mode);
            nextNode.classList.add('sources-editor-token--toolbar-open');
            tokenNode.replaceWith(nextNode);
            refreshPreview(nextNode);
          }
        }
      });
    }

    if (!doc._sourcesButtonBound) {
      doc._sourcesButtonBound = true;
      ['input', 'keyup', 'paste', 'mouseup', 'blur', 'drop'].forEach((eventName) => {
        doc.addEventListener(eventName, () => setTimeout(() => decorateDocument(doc), 30), true);
      });
    }
    decorateDocument(doc);
  }

  function bindTinyEditors() {
    listTinyEditors().forEach((ed) => {
      try {
        bindEditorDoc(ed.getDoc && ed.getDoc());
        if (!ed._sourcesButtonBound) {
          ed._sourcesButtonBound = true;
          ed.on('init SetContent LoadContent Change NodeChange Undo Redo', () => setTimeout(() => decorateDocument(ed.getDoc()), 30));
          ed.on('PreProcess', (e) => {
            if (e.node && e.node.querySelectorAll) {
              e.node.querySelectorAll('.sources-editor-token').forEach((node) => {
                const token = node.getAttribute('data-sources-token');
                if (token) node.replaceWith(node.ownerDocument.createTextNode(token));
              });
            }
          });
        }
      } catch (e) {}
    });
  }

  function bindIframes() {
    document.querySelectorAll('iframe').forEach((iframe) => {
      if (!iframe._sourcesButtonBound) {
        iframe._sourcesButtonBound = true;
        iframe.addEventListener('load', () => { try { bindEditorDoc(iframe.contentDocument); } catch (e) {} });
      }
      try { bindEditorDoc(iframe.contentDocument); } catch (e) {}
    });
  }

  function refreshAllPreviews() {
    bindTinyEditors();
    bindIframes();

    document.querySelectorAll('.sources-editor-token:not(.sources-editor-token--raw)').forEach((node) => {
      refreshPreview(node);
    });

    listTinyEditors().forEach((ed) => {
      try {
        ed.getDoc().querySelectorAll('.sources-editor-token:not(.sources-editor-token--raw)').forEach((node) => {
          refreshPreview(node);
        });
      } catch (e) {}
    });

    document.querySelectorAll('iframe').forEach((iframe) => {
      try {
        iframe.contentDocument.querySelectorAll('.sources-editor-token:not(.sources-editor-token--raw)').forEach((node) => {
          refreshPreview(node);
        });
      } catch (e) {}
    });
  }

  function decorateEditors() { bindTinyEditors(); bindIframes(); refreshAllPreviews(); }

  function restoreEditors() {
    listTinyEditors().forEach((ed) => {
      try {
        restoreTokens(ed.getDoc && ed.getDoc());
        if (ed.save) ed.save();
      } catch (e) {}
    });
    document.querySelectorAll('iframe').forEach((iframe) => { try { restoreTokens(iframe.contentDocument); } catch (e) {} });
  }

  function insertToken(token, joomlaEditor) {
    const ed = window.tinymce && tinymce.activeEditor ? tinymce.activeEditor : null;
    if (ed) {
      try {
        ed.insertContent(buildTokenHtml(token));
        decorateDocument(ed.getDoc && ed.getDoc());
        return;
      } catch (e) {}
    }
    if (joomlaEditor && typeof joomlaEditor.insert === 'function') {
      joomlaEditor.insert(buildTokenHtml(token));
      setTimeout(decorateEditors, 100);
    }
  }

  function choiceIconClass(value) {
    if (value === 'single') return 'sourcesbutton-choice-icon--single';
    if (value === 'rest') return 'sourcesbutton-choice-icon--rest';
    return 'sourcesbutton-choice-icon--all';
  }

  function updateModalPreview(modal) {
    const choice = (modal.querySelector('input[name="sources-token-choice"]:checked') || {}).value || 'all';
    const block = modal.querySelector('#sources-token-block');
    const token = tokenFromChoice(choice, block ? block.value : 1);
    const preview = modal.querySelector('[data-sources-token-preview]');
    if (preview) preview.textContent = token;
    modal.querySelectorAll('.sourcesbutton-block-row').forEach((row) => { row.hidden = choice !== 'single'; });
    modal.querySelectorAll('.sourcesbutton-choice').forEach((item) => {
      const input = item.querySelector('input[name="sources-token-choice"]');
      item.classList.toggle('sourcesbutton-choice--checked', !!input && input.checked);
    });
  }

  function ensureModal() {
    ensureAdminStyles();

    let modal = document.getElementById('sourcesbutton-modal');
    if (modal) return modal;

    const wrap = document.createElement('div');
    wrap.innerHTML = '<div class="modal fade sourcesbutton-modal" id="sourcesbutton-modal" tabindex="-1" role="dialog" aria-modal="true">' +
      '<div class="modal-dialog sourcesbutton-dialog"><div class="modal-content sourcesbutton-content">' +
      '<button type="button" class="btn-close sourcesbutton-close" data-sources-close aria-label="Close"></button>' +
      '<div class="sourcesbutton-modal-shell">' +
      '<div class="sourcesbutton-hero"><span class="sourcesbutton-hero-icon" aria-hidden="true"></span><span><h5 class="sourcesbutton-title">Insert Sources</h5><p class="sourcesbutton-subtitle">Choose which sources to insert into your content.</p></span></div>' +
      '<div class="sourcesbutton-choice-list" role="radiogroup" aria-label="' + escapeAttr(t('modalTitle', 'Insert Sources')) + '">' +
      choiceHtml('all', 'sources-token-all', 'All sources', 'Insert all available sources.') +
      choiceHtml('single', 'sources-token-single', 'Specific source block', 'Insert sources from a specific block number.') +
      choiceHtml('rest', 'sources-token-rest', 'Remaining sources', 'Insert sources after the selected block.') +
      '</div>' +
      '<div class="sourcesbutton-token-section"><div class="sourcesbutton-token-label">Token preview</div><div class="sourcesbutton-token-preview"><span class="sources-editor-token sources-editor-token--raw-wrap sourcesbutton-token-badge" contenteditable="false"><span class="sources-editor-raw-text" data-sources-token-preview>{' + escapeHtml(TOKEN_NAME) + '}</span></span></div></div>' +
      '</div>' +
      '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-sources-close>' + escapeHtml(t('cancel', 'Cancel')) + '</button><button type="button" class="btn btn-primary" data-sources-insert>' + escapeHtml(t('insert', 'Insert')) + '</button></div>' +
      '</div></div></div>';

    document.body.appendChild(wrap.firstElementChild);
    modal = document.getElementById('sourcesbutton-modal');
    modal.querySelectorAll('[data-sources-close]').forEach((btn) => btn.addEventListener('click', () => hideModal(modal)));
    modal.querySelectorAll('input[name="sources-token-choice"], #sources-token-block').forEach((input) => input.addEventListener('input', () => updateModalPreview(modal)));
    modal.querySelectorAll('input[name="sources-token-choice"]').forEach((input) => input.addEventListener('change', () => updateModalPreview(modal)));
    updateModalPreview(modal);
    return modal;
  }

  function choiceHtml(value, id, title, description) {
    const blockControl = value === 'single'
      ? '<span class="sourcesbutton-block-row"><label class="form-label" for="sources-token-block">' + escapeHtml(t('blockNumber', 'Block number')) + '</label><input class="form-control" id="sources-token-block" type="number" min="1" value="1"></span>'
      : '';

    return '<label class="sourcesbutton-choice sourcesbutton-choice--' + value + '" for="' + id + '">' +
      '<input class="sourcesbutton-choice-input" type="radio" name="sources-token-choice" id="' + id + '" value="' + value + '"' + (value === 'all' ? ' checked' : '') + '>' +
      '<span class="sourcesbutton-choice-mark" aria-hidden="true"></span>' +
      '<span class="sourcesbutton-choice-icon ' + choiceIconClass(value) + '" aria-hidden="true"></span>' +
      '<span class="sourcesbutton-choice-content"><strong>' + escapeHtml(title) + '</strong><em>' + escapeHtml(description) + '</em></span>' +
      blockControl +
      '</label>';
  }

  function showModal(modal) {
    updateModalPreview(modal);
    const Modal = window.bootstrap && window.bootstrap.Modal;
    if (Modal) { Modal.getOrCreateInstance(modal).show(); return; }
    modal.classList.add('show');
    modal.style.display = 'block';
    document.body.classList.add('modal-open');
  }

  function hideModal(modal) {
    if (document.activeElement && modal.contains(document.activeElement)) document.activeElement.blur();
    const Modal = window.bootstrap && window.bootstrap.Modal;
    if (Modal) { Modal.getOrCreateInstance(modal).hide(); return; }
    modal.classList.remove('show');
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
  }

  function actionInsert(joomlaEditor) {
    const modal = ensureModal();
    const insert = modal.querySelector('[data-sources-insert]');
    insert.onclick = function () {
      const choice = (modal.querySelector('input[name="sources-token-choice"]:checked') || {}).value || 'all';
      const block = modal.querySelector('#sources-token-block');
      insertToken(tokenFromChoice(choice, block ? block.value : 1), joomlaEditor);
      hideModal(modal);
    };
    showModal(modal);
  }


  JoomlaEditorButton.registerAction('sourcesbutton:insert', actionInsert);

  document.addEventListener('submit', restoreEditors, true);
  document.addEventListener('DOMContentLoaded', decorateEditors);
  decorateEditors();
  setTimeout(decorateEditors, 250);
  setTimeout(refreshAllPreviews, 600);
  setTimeout(decorateEditors, 1000);
  setTimeout(refreshAllPreviews, 1800);
  setTimeout(decorateEditors, 2500);
})();
