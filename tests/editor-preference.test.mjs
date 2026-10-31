import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { JSDOM } from 'jsdom';
import { scan, serialize, newContainer, newBlock } from '../package/plugins/editors-xtd/sourcesbutton/media/js/inline.js';

const key = 'supjx.sources.editor.previewMode';
const source = ['presentation-context.js', 'preview-surface.js', 'preview-request.js', 'preview-ui.js', 'sourcesbutton.js'].map(file => readFileSync(new URL('../package/plugins/editors-xtd/sourcesbutton/media/js/' + file, import.meta.url), 'utf8')
  .replace(/^import .*;\r?\n/gm, '').replace(/^export \{.*\} from .*;\r?\n/gm, '').replace(/^export /gm, '')).join('\n');

function fixture({ stored, blocked = false, defaultMode = 'list_manual', i18n = {} } = {}) {
  const dom = new JSDOM('<!doctype html><body></body>', { url: 'https://test.invalid', pretendToBeVisual: true });
  const { window } = dom;
  window.Joomla = { getOptions: () => ({ previewMode: defaultMode, i18n }) };
  if (stored !== undefined) window.localStorage.setItem(key, stored);
  if (blocked) Object.defineProperty(window, 'localStorage', { get() { throw new Error('Storage blocked'); } });
  const context = vm.createContext({ window, document: window.document, scan, serialize,
    JoomlaEditor: {}, JoomlaEditorButton: { registerAction() {} }, setInterval() {} });
  vm.runInContext(source, context);
  const token = scan(serialize(newContainer()))[0];
  const node = context.tokenNode(window.document, token);
  window.document.body.append(node);
  context.bindEditor({ getDoc: () => window.document, on() {} });
  return { context, window, token, node, choose(mode) {
    window.document.querySelector(`[data-sources-mode="${mode}"]`).click();
    return window.document.querySelector('.sources-editor-token');
  } };
}

test('editor preview remembers every supported mode across reload without changing article JSON', () => {
  for (const mode of ['none', 'placeholder', 'list_manual', 'list_full', 'render']) {
    const f = fixture();
    assert.equal(f.node.dataset.sourcesPreviewMode, 'list_manual');
    const selected = f.choose(mode);
    assert.equal(selected.dataset.sourcesPreviewMode, mode);
    assert.equal(f.window.localStorage.getItem(key), mode);
    assert.equal(selected.dataset.sourcesToken, f.token.raw);
    assert.equal(f.context.cleanHtml(selected.outerHTML), f.token.raw);
    const reload = fixture({ stored: f.window.localStorage.getItem(key) });
    assert.equal(reload.node.dataset.sourcesPreviewMode, mode);
    assert.equal(reload.context.tokenNode(reload.window.document, reload.token, 'placeholder').dataset.sourcesPreviewMode, 'placeholder');
    f.window.close(); reload.window.close();
  }
});

test('invalid preferences use plugin defaults and blocked storage keeps a page-local preference', () => {
  const invalid = fixture({ stored: 'invalid-mode' });
  assert.equal(invalid.node.dataset.sourcesPreviewMode, 'list_manual');
  const fallback = fixture({ defaultMode: 'invalid-mode' });
  assert.equal(fallback.node.dataset.sourcesPreviewMode, 'placeholder');
  const blocked = fixture({ blocked: true });
  assert.equal(blocked.choose('render').dataset.sourcesPreviewMode, 'render');
  assert.equal(blocked.context.tokenNode(blocked.window.document, blocked.token).dataset.sourcesPreviewMode, 'render');
  invalid.window.close(); fallback.window.close(); blocked.window.close();
});

test('JSON is selectable read-only text and nested controls never modify preference or outer source', () => {
  const f = fixture({ stored: 'render' });
  const json = f.context.tokenNode(f.window.document, f.token, 'none').querySelector('textarea');
  assert.equal(json.readOnly, true); assert.equal(json.value, f.token.raw);
  json.select(); assert.equal(json.selectionEnd, f.token.raw.length);
  const marker = f.window.document.createElement('div'); marker.dataset.sourcesPreviewToken = f.token.raw;
  marker.innerHTML = '<ul><li>Nested article</li></ul>';
  f.node.querySelector('.sources-editor-token__body').append(marker);
  f.context.mountNestedPreviews(f.window.document);
  const nested = f.window.document.querySelector('[data-sources-nested-preview]');
  assert.equal(nested.dataset.sourcesPreviewMode, 'list_manual');
  assert.equal(nested.querySelectorAll('[data-sources-mode]').length, 5);
  assert.equal(nested.querySelector('[data-sources-edit]'), null);
  assert.equal(nested.querySelector('li').textContent, 'Nested article');
  nested.querySelector('[data-sources-mode="render"]').click();
  const next = f.window.document.querySelector('[data-sources-nested-preview]');
  assert.equal(next.dataset.sourcesPreviewMode, 'render');
  assert.equal(f.window.localStorage.getItem(key), 'render');
  next.querySelector('[data-sources-mode="placeholder"]').click();
  assert.equal(f.window.localStorage.getItem(key), 'render');
  assert.equal(f.context.cleanHtml(f.node.outerHTML), f.token.raw);
  f.window.close();
});

test('preview headers show titles and translated summaries, not legacy badges or bare numbering', () => {
  const f = fixture({ i18n: { sources: 'Sources', cards: 'Cards', type_articles: 'Specific articles', selected: 'selected',
    separate: 'Separate lists', merged: 'Merged', previewBlocks: 'blocks', previewSelectedArticles: 'manually selected articles' } });
  const config = newContainer(); config.blocks[0].title = 'Recommended'; config.blocks[0].articles = [1, 2];
  const header = () => f.context.tokenNode(f.window.document, scan(serialize(config))[0]).querySelector('.sources-editor-token__label');
  assert.equal(header().querySelector('strong').textContent, 'Recommended');
  assert.equal(header().querySelector('.sources-editor-token__summary').textContent, 'Cards · Specific articles · 2 selected');
  config.blocks.push(newBlock('categories'));
  assert.equal(header().querySelector('strong').textContent, 'Sources');
  assert.equal(header().querySelector('.sources-editor-token__summary').textContent, 'Separate lists · 2 blocks · 2 manually selected articles');
  config.display = 'merged'; config.presentation.title = 'Shared heading';
  assert.equal(header().querySelector('strong').textContent, 'Shared heading');
  f.window.close();
});
