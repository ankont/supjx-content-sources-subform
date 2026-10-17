import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { SourcesBuilder } from '../package/plugins/editors-xtd/sourcesbutton/media/js/builder.js';
import { newContainer, newBlock } from '../package/plugins/editors-xtd/sourcesbutton/media/js/inline.js';
import { mountSharedCollection } from './shared-collection-fixture.mjs';

for (const type of ['articles', 'categories', 'tags']) {
  test(type + ' delegates Add/full edit to SmartBrowser, preserving usage and metadata', async () => {
    const dom = new JSDOM('<body></body>');
    global.window = dom.window; global.document = dom.window.document;
    window.Joomla = { getOptions: key => key === 'com_smartbrowser.picker' ? { url: '/picker' } : {} };
    dom.window.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    dom.window.HTMLDialogElement.prototype.close = function () { this.open = false; };
    window.SmartBrowser = { mountCollection: mountSharedCollection };
    const prefix = { articles: 'article', categories: 'category', tags: 'tag' }[type];
    const entry = (id, usage = {}) => ({ selection: { adapter: type, id: prefix + ':' + id }, usage });
    const kept = entry(1, { 'ext.example.data': { nested: [1, 2] }, 'visual.presentation': 'icon' });
    const config = newContainer(); config.blocks = [newBlock(type)];
    if (type === 'articles') config.blocks[0].articles = [kept];
    else config.blocks[0].filters[type] = [kept];
    const requests = []; let result = { items: [entry(1), entry(2)] };
    window.SmartBrowserPicker = { open: async options => { requests.push(options); return result; } };
    const builder = new SourcesBuilder(config), done = builder.open();
    const block = builder.config.blocks[0], record = builder.records.get(block), settings = record.collection.config;
    const values = () => type === 'articles' ? block.articles : block.filters[type];
    assert.equal(settings.onAdd, undefined); assert.equal(settings.onReplace, undefined);
    assert.equal(settings.pickerUrl, '/picker');
    assert.equal(settings.selectedResources, true); assert.equal(settings.referenceItems, true);
    assert.equal(settings.maxSelections, 500);
    assert.deepEqual(settings.allowedAdapters, [type]); assert.deepEqual(settings.allowedResourceTypes, [prefix]);
    assert.equal(settings.viewPreferenceKey, 'supjx.sources.selection.' + type);
    try {
      await builder.pick(block, type, record);
      assert.deepEqual(requests[0].initialCollection, []);
      assert.deepEqual(values(), [kept, 2], 'duplicate Add must not overwrite existing extension/usage data');
      result = null;
      await builder.pick(block, type, record);
      assert.deepEqual(values(), [kept, 2], 'cancelled Add leaves selection intact');
      const focused = { ...entry(2).selection, parentId: 'category:9' };
      result = { items: [entry(3), kept] };
      await builder.pick(block, type, record, focused);
      const request = requests.at(-1);
      assert.equal(request.initialCollection.length, 2);
      assert.deepEqual(request.initialFocus, entry(2).selection);
      assert.equal(request.initialInfoOpen, true); assert.equal(request.multiple, true);
      assert.equal(request.initialNode, 'category:9');
      assert.deepEqual(values(), [3, kept], 'full returned selection replaces rather than appends');
      result = null; await builder.pick(block, type, record, focused);
      assert.deepEqual(values(), [3, kept], 'cancelled full edit leaves selection intact');
      window.SmartBrowserPicker.open = async () => { throw new Error('Picker failed'); };
      const errors = []; builder.error = error => errors.push(error);
      await builder.pick(block, type, record);
      assert.equal(errors.length, 1); assert.equal(builder.pending, 0);
      assert.deepEqual(values(), [3, kept]);
      settings.onError(new Error('Collection failed')); assert.equal(errors.length, 2);
    } finally { builder.finish(null); await done; dom.window.close(); }
  });
}
