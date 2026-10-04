import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { JSDOM } from 'jsdom';

const fixture = await readFile(new URL('./fixtures/article-form.html', import.meta.url), 'utf8');
const native = await readFile(new URL('./fixtures/joomla-field-subform.js', import.meta.url), 'utf8');
const builderCode = await readFile(new URL('../package/plugins/content/sources/media/js/builder.js', import.meta.url), 'utf8');
const options = {
  fieldName: 'blocks',
  fieldNames: { field10: 'block-title', field11: 'block-layout', field12: 'block-manual-articles', field13: 'block-tags', field14: 'block-categories', field15: 'block-limit' },
  i18n: { sourceCount: '%s sources', tagCount: '%s tags' },
};
const tick = () => new Promise(resolve => setTimeout(resolve, 20));

async function setup({ unavailable = false, reject = false } = {}) {
  const dom = new JSDOM(fixture, { url: 'https://example.test/edit', runScripts: 'outside-only' });
  const w = dom.window;
  w.CSS = { escape: value => value.replace(/[^a-zA-Z0-9_-]/g, c => '\\' + c) };
  w.Joomla = { getOptions: () => options };
  w.confirm = () => true;
  const instances = [];
  w.SmartBrowser = unavailable ? null : { mountCollection(container, config) {
    const instance = {
      config, container, items: [...config.items], updates: 0, destroyed: false,
      ready: reject ? Promise.reject(new Error('Resolution failure')) : Promise.resolve(),
      setItems: async items => { instance.items = [...items]; instance.updates++; },
      refresh: async () => {},
      destroy: () => { instance.destroyed = true; },
      emit(items) { instance.items = items; config.onChange({ items, resources: [], reason: 'reorder' }); },
    };
    instances.push(instance);
    return instance;
  } };
  w.SmartBrowserPicker = { open: async () => [{ id: 'article:2', title: 'Seminar' }, { id: 'article:11', title: 'Galaxy' }] };
  w.eval(native);
  w.eval(builderCode.replace(/export /g, '') + '\nwindow.SourcesBuilderTest = SourcesBuilder;');
  await tick();
  const root = w.document.querySelector('joomla-field-subform');
  const form = w.document.querySelector('form');
  const selected = row => Array.from(row.querySelector('select[multiple]').selectedOptions, o => o.value);
  return { dom, w, root, form, instances, selected };
}

test('initialization keeps native values and form submission contract intact', async () => {
  const { dom, w, form, instances } = await setup();
  assert.equal(instances.length, 1);
  assert.deepEqual(instances[0].items, ['11', '4', '99']);
  assert.deepEqual(new w.FormData(form).getAll('jform[com_fields][blocks][row0][field12][]'), ['11', '4', '99']);
  assert.equal(w.document.querySelector('.sources-builder').hidden, false);
  assert.equal(w.document.querySelector('.sources-builder-number').textContent, 'BLOCK 1');
  assert.equal([...new w.FormData(form).keys()].some(name => name.startsWith('sources-builder')), false);
  dom.window.close();
});

test('collection order/removal writes native fields and Classic returns without remounting', async () => {
  const { dom, w, form, instances } = await setup();
  instances[0].emit(['article:99', 'article:11']);
  await tick();
  assert.deepEqual(new w.FormData(form).getAll('jform[com_fields][blocks][row0][field12][]'), ['99', '11']);
  const toggle = w.document.querySelector('.sources-builder-footer button');
  toggle.click();
  assert.equal(w.document.querySelector('joomla-field-subform').closest('.control-group').hidden, false);
  toggle.click();
  await tick();
  assert.equal(instances.length, 1);
  dom.window.close();
});

test('Classic edits refresh existing collection and do not overwrite its native state', async () => {
  const { dom, w, root, instances } = await setup();
  const toggle = w.document.querySelector('.sources-builder-footer button');
  toggle.click();
  const select = root.querySelector('select[multiple]');
  Array.from(select.options).forEach(option => { option.selected = option.value === '2'; });
  select.dispatchEvent(new w.Event('change', { bubbles: true }));
  toggle.click();
  await tick();
  assert.deepEqual(instances[0].items, ['2']);
  assert.equal(instances.length, 1);
  dom.window.close();
});

test('picker appends new references preserving order, deduplicating existing references', async () => {
  const { dom, w, form, instances } = await setup();
  w.document.querySelector('.sources-builder-sources button').click();
  await tick();
  assert.deepEqual(new w.FormData(form).getAll('jform[com_fields][blocks][row0][field12][]'), ['11', '4', '99', '2']);
  assert.deepEqual(instances[0].items, ['11', '4', '99', '2']);
  dom.window.close();
});

test('duplicate, add, reorder and delete use native rows with unique names and isolated collections', async () => {
  const { dom, w, root, selected, instances } = await setup();
  w.document.querySelector('.sources-builder-menu-body button').click();
  await tick();
  assert.equal(root.getRows().length, 2);
  assert.deepEqual(selected(root.getRows()[1]), ['11', '4', '99']);
  assert.equal(root.getRows()[1].querySelector('input').value, 'Embedded articles');
  assert.equal(root.getRows()[1].querySelector('input[type=number]').value, '16');
  assert.notEqual(root.getRows()[0].querySelector('input').name, root.getRows()[1].querySelector('input').name);
  assert.equal(instances.length, 2);
  instances[1].emit(['article:2']);
  await tick();
  assert.deepEqual(selected(root.getRows()[0]), ['11', '4', '99']);
  w.document.querySelectorAll('.sources-builder-header')[1].querySelector('button.sources-builder-icon').click();
  await tick();
  assert.deepEqual(selected(root.getRows()[0]), ['2']);
  assert.deepEqual(Array.from(w.document.querySelectorAll('.sources-builder-number'), node => node.textContent), ['BLOCK 1', 'BLOCK 2']);
  w.document.querySelectorAll('.sources-builder-menu-body')[0].querySelectorAll('button')[1].click();
  await tick();
  assert.equal(root.getRows().length, 1);
  assert.equal(instances[1].destroyed, true);
  w.document.querySelector('.sources-builder-content>.sources-builder-action').click();
  await tick();
  assert.equal(root.getRows().length, 2);
  assert.deepEqual(selected(root.getRows()[1]), []);
  dom.window.close();
});

test('Builder title/display/filter edits retain exact native values', async () => {
  const { dom, w, root, form } = await setup();
  const title = w.document.querySelector('.sources-builder-identity input');
  title.value = 'Changed title';
  title.dispatchEvent(new w.Event('input', { bubbles: true }));
  const tags = w.document.querySelector('.sources-builder-advanced select');
  Array.from(tags.options).forEach(option => { option.selected = ['8', '7'].includes(option.value); });
  tags.dispatchEvent(new w.Event('input', { bubbles: true }));
  tags.dispatchEvent(new w.Event('change', { bubbles: true }));
  await tick();
  assert.equal(root.querySelector('input').value, 'Changed title');
  assert.deepEqual(new w.FormData(form).getAll('jform[com_fields][blocks][row0][field13][]'), ['8', '7']);
  dom.window.close();
});

test('empty new article and failed optional integration always retain native editor', async () => {
  for (const parameters of [{ unavailable: true }, { reject: true }]) {
    const { dom, root, w } = await setup(parameters);
    assert.equal(root.closest('.control-group').hidden, false);
    dom.window.close();
  }
  const { dom, root, w, instances } = await setup();
  root.removeRow(root.getRows()[0]);
  await tick();
  assert.equal(w.document.querySelector('.sources-builder-empty').hidden, false);
  assert.equal(instances[0].destroyed, true);
  w.document.querySelector('.sources-builder-content>.sources-builder-action').click();
  await tick();
  assert.equal(root.getRows().length, 1);
  assert.deepEqual(instances[1].items, []);
  dom.window.close();
});
