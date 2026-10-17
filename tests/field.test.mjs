import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { newContainer, newBlock } from '../package/plugins/editors-xtd/sourcesbutton/media/js/inline.js';
import { spawnSync } from 'node:child_process';

async function fixture(raw = '') {
  const dom = new JSDOM('<!doctype html><form><input name="task" value="article.apply"><div data-sources-field><textarea class="sources-field-value" name="jform[com_fields][sources]"></textarea></div></form>', { url: 'https://test.invalid' });
  global.window = dom.window; global.document = dom.window.document; global.Event = dom.window.Event;
  window.Joomla = { getOptions: () => ({}) };
  window.SmartBrowser = { mountCollection: (host, config) => ({ config, ready: Promise.resolve(), destroy() {}, setItems() {} }) };
  window.SmartBrowserPicker = { open: async () => null };
  const host = document.querySelector('[data-sources-field]'), input = host.querySelector('textarea');
  host.dataset.labels = JSON.stringify({ preview: 'Preview', close: 'Close', invalid: 'Invalid Sources', pending: 'Finish selection' });
  input.value = raw;
  const { mountSourcesField } = await import('../package/plugins/editors-xtd/sourcesbutton/media/js/field.js');
  return { host, input, instance: mountSourcesField(host), form: input.form, dom };
}

test('embedded Builder writes the existing field synchronously, without nested forms', async () => {
  const { input, host, instance } = await fixture();
  assert.equal(host.querySelectorAll('form,dialog').length, 0);
  assert.equal(instance.builder.config.blocks.length, 1);
  assert.equal(input.value, '', 'An untouched optional field stays empty');
  const block = instance.builder.config.blocks[0], record = instance.builder.records.get(block);
  record.titleInput.value = 'Unsaved label'; record.titleInput.dispatchEvent(new Event('input', { bubbles: true }));
  assert.equal(JSON.parse(input.value).blocks[0].title, 'Unsaved label');
  record.collection.config.onChange({ items: [{ selection: { adapter: 'articles', id: 'article:8' } }] });
  assert.deepEqual(JSON.parse(input.value).blocks[0].articles, [8]);
  record.remove.click(); assert.equal(input.value, '');
  assert.equal(host.querySelector('.sources-field-toolbar button').disabled, true);
  instance.builder.addButtons[2].click();
  assert.equal(JSON.parse(input.value).blocks[0].type, 'tags');
  instance.destroy(); assert.equal(input.hidden, false);
});

test('external native changes remount from the same JSON, malformed data stays accessible', async () => {
  const config = newContainer(); config.blocks[0].articles = [4, 11]; config.blocks.push(newBlock('categories'));
  const { input, instance, host } = await fixture(JSON.stringify(config));
  assert.deepEqual(instance.builder.config.blocks[0].articles, [4,11]);
  const previous = instance.builder;
  config.blocks[0].title = 'External change'; input.value = JSON.stringify(config); input.dispatchEvent(new Event('change'));
  assert.equal(previous.closed, true); assert.equal(instance.builder.config.blocks[0].title, 'External change');
  input.value = '{"version":999}'; input.dispatchEvent(new Event('change'));
  assert.equal(instance.builder, null); assert.equal(input.hidden, false);
  assert.equal(input.value, '{"version":999}'); assert.equal(host.querySelector('[role=alert]').hidden, false);
  input.value = JSON.stringify(config); input.dispatchEvent(new Event('change'));
  assert.equal(input.hidden, true); assert.equal(instance.builder.config.blocks[0].title, 'External change');
  instance.destroy();
});

test('Save/Apply is blocked during selection or invalid drafts, but cancellation is allowed', async () => {
  const { input, instance, form, dom } = await fixture();
  instance.builder.pending = 1;
  const submit = () => form.dispatchEvent(new dom.window.Event('submit', { cancelable: true }));
  assert.equal(submit(), false);
  form.querySelector('[name=task]').value = 'article.cancel'; assert.equal(submit(), true);
  form.querySelector('[name=task]').value = 'article.apply'; instance.builder.pending = 0;
  instance.builder.config.presentation.limit = -1; assert.equal(submit(), false);
  instance.builder.config.presentation.limit = 0;
  const requiredConfig = newContainer(); input.value = JSON.stringify(requiredConfig); input.dispatchEvent(new Event('change'));
  assert.equal(submit(), true); instance.destroy();
});

test('Sources field uses actual Joomla form type and validation APIs', context => {
  const php = process.env.PHP_BINARY || 'php';
  const result = spawnSync(php, ['tests/php-field.php'], { encoding: 'utf8' });
  if (result.error?.code === 'ENOENT' || result.status === 3) { context.skip(result.error?.message || result.stderr); return; }
  assert.equal(result.status, 0, result.stderr + result.stdout);
  assert.match(result.stdout, /Joomla Sources field assertions passed/);
});

test('SmartBrowser failures keep native JSON inside a closed advanced disclosure', async () => {
  const config = newContainer(); config.blocks[0].articles = [4];
  const raw = JSON.stringify(config);
  const { input, instance, host } = await fixture(raw);
  instance.builder.records.get(instance.builder.config.blocks[0]).collection.config.onError(new Error('Unavailable'));
  assert.equal(input.hidden, false); assert.equal(input.value, raw);
  const fallback = host.querySelector('.sources-field-fallback');
  assert.equal(fallback.hidden, false);
  assert.equal(fallback.open, false);
  assert.equal(fallback.contains(input), true);
  fallback.open = true;
  assert.equal(input.value, raw);
  assert.equal(host.querySelector('[role=alert]').hidden, false);
  instance.destroy();
  assert.equal(host.contains(input), true);
  assert.equal(host.querySelector('.sources-field-fallback'), null);
});
test('field preview refreshes Usage drafts and ignores stale server HTML, retaining presentation markers', async () => {
  const config = newContainer(); config.blocks[0].articles = [4];
  const { instance, host, dom } = await fixture(JSON.stringify(config));
  dom.window.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
  dom.window.HTMLDialogElement.prototype.close = function () { this.open = false; };
  const originalFetch = global.fetch, replies = [];
  global.fetch = () => new Promise(resolve => replies.push(resolve));
  const response = html => ({ ok: true, json: async () => ({ success: true, data: [{ ok: true, html }] }) });
  try {
    const first = instance.showPreview();
    assert.equal(replies.length, 1);
    const record = instance.builder.records.get(instance.builder.config.blocks[0]);
    record.titleInput.value = 'Changed draft'; record.titleInput.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(replies.length, 2);
    replies[1](response('<img data-sb-primary-image="1" data-sb-image-presentation="server"><p>Newest</p>'));
    await new Promise(resolve => setTimeout(resolve, 0));
    replies[0](response('<p>Stale</p>')); await first;
    const html = document.querySelector('.sources-field-preview iframe').srcdoc;
    assert.match(html, /Newest/); assert.doesNotMatch(html, /Stale/);
    assert.match(html, /data-sb-image-presentation="server"/);
    assert.equal(host.querySelector('.sources-field-value').value.includes('Newest'), false);
  } finally { instance.destroy(); global.fetch = originalFetch; dom.window.close(); }
});
