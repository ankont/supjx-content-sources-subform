import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { startPreviewRequest, appendPreviewStyles } from '../package/plugins/editors-xtd/sourcesbutton/media/js/preview-request.js';

test('resource/Usage and mode updates invalidate old preview responses; server markers survive publication', async () => {
  const dom = new JSDOM('<body><div id="preview"></div></body>');
  const node = dom.window.document.querySelector('#preview');
  node.dataset.sourcesToken = 'resource-one'; node.dataset.sourcesPreviewMode = 'render';
  const begin = () => startPreviewRequest(node, JSON.stringify([node.dataset.sourcesToken, node.dataset.sourcesPreviewMode]));
  const first = begin(); assert.equal(first.current(), true);
  assert.equal(begin(), null, 'same configuration is not fetched twice');
  node.dataset.sourcesToken = 'resource-one-with-new-usage';
  assert.equal(first.current(), false, 'Usage edits invalidate even before the next request');
  const second = begin(); assert.equal(first.signal.aborted, true);
  const publish = (request, html) => { if (request.current()) node.innerHTML = html; };
  publish(second, '<img data-sb-primary-image="1" data-sb-image-presentation="server">');
  publish(first, 'stale');
  assert.equal(node.querySelector('img').dataset.sbImagePresentation, 'server');
  node.dataset.sourcesPreviewMode = 'list_full'; const third = begin();
  assert.equal(second.signal.aborted, true); assert.equal(third.current(), true);
  node.remove(); assert.equal(third.current(), false);
  dom.window.close();
});

test('server-enqueued presentation CSS reaches previews without unsafe URLs or duplicates', () => {
  const dom = new JSDOM('<html><head></head><body></body></html>', { url: 'https://site.test/editor' });
  const doc = dom.window.document;
  appendPreviewStyles(doc, ['/media/site.css', 'javascript:alert(1)', null, '/media/site.css'], doc.baseURI);
  appendPreviewStyles(doc, ['/media/site.css'], doc.baseURI);
  assert.equal(doc.querySelectorAll('link').length, 1);
  assert.equal(doc.querySelector('link').href, 'https://site.test/media/site.css');
  dom.window.close();
});
