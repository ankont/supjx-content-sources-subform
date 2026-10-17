import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { JSDOM } from 'jsdom';
import { validate, serialize, scan, newContainer, newBlock } from '../package/plugins/editors-xtd/sourcesbutton/media/js/inline.js';
import { referenceItems, selectionValues, usageProfile } from '../package/plugins/editors-xtd/sourcesbutton/media/js/usage.js';
import { SourcesBuilder } from '../package/plugins/editors-xtd/sourcesbutton/media/js/builder.js';

const entry = (id, usage, adapter = 'articles') => ({ selection: { adapter, id: { articles:'article:',categories:'category:',tags:'tag:' }[adapter] + id }, usage });
const usage = { 'article.displayedName':'A local name', 'visual.thumbnailOverride':{ adapter:'media',id:'images/local.jpg#joomlaImage://local-images/local.jpg?crop=10,10,80,80&ratio=1:1' },
  'visual.iconOverride':'fas fa-book', 'visual.presentation':'both', 'ext.smartcrop.crop': { ratio:'1:1', empty:{}, points:[1,2] }, 'ext.test.cleared':null };
test('usage entries round-trip alongside legacy IDs in the same version-2 arrays', () => {
  const config=newContainer();config.blocks[0].articles=[11,entry(4,usage),11];
  config.blocks.push(newBlock('categories'));config.blocks[1].filters.categories=[entry(14,{'visual.iconOverride':'fas fa-folder'},'categories')];
  const normalized=validate(config),token=serialize(config);
  assert.deepEqual(scan(token)[0].config,normalized);assert.equal(normalized.version,2);
  assert.deepEqual(normalized.blocks[0].articles,[11,entry(4,usage)]);
  const result=spawnSync('php',['tests/php-inline.php'],{input:JSON.stringify({valid:token,future:token.replace('"version":2','"version":99'),expected:normalized}),encoding:'utf8'});
  assert.equal(result.status,0,result.stderr+result.stdout);
});
test('Usage profiles expose name/image primarily and icon/presentation only as secondary', () => {
  assert.deepEqual(usageProfile('articles')['article.displayedName'],{});
  assert.deepEqual(usageProfile('articles')['visual.thumbnailOverride'],{});
  assert.equal(usageProfile('articles')['visual.iconOverride'].presentation,'secondary');
  assert.equal(usageProfile('tags')['visual.presentation'].presentation,'secondary');
  assert.equal('article.displayedName' in usageProfile('tags'),false);
});
test('editor preview stylesheet URLs are versioned by the actual CSS content', context => {
  const result=spawnSync('php',['tests/php-preview-styles.php'],{encoding:'utf8'});
  if(result.status===3){context.skip(result.stderr);return}
  assert.equal(result.status,0,result.stderr+result.stdout);
  assert.match(result.stdout,/Preview stylesheet content hash assertions passed/);
});
test('thumbnail/name/icon overrides use the actual SmartBrowser public PHP API', context => {
  const result=spawnSync('php',['tests/php-usage-public.php'],{encoding:'utf8'});
  if(result.status===3){context.skip(result.stderr);return}
  assert.equal(result.status,0,result.stderr+result.stdout);
  assert.match(result.stdout,/Actual SmartBrowser public Usage API assertions passed/);
});
test('malformed and executable usage values fail closed', () => {
  for(const bad of [{'html':'<b>x</b>'},{'visual.iconOverride':'x" onclick=x'},{'visual.presentation':'script'},
    {'visual.thumbnailOverride':{adapter:'articles',id:'article:4'}},{'article.displayedName':'x'.repeat(256)},
    {'ext.address':'javascript:alert(1)'},{'ext.test':NaN},{'ext.test':undefined}]) {
    assert.throws(()=>selectionValues([entry(4,bad)],'articles'));
  }
  assert.throws(()=>selectionValues([entry(4,{},'tags')],'articles'));
  assert.deepEqual(selectionValues(referenceItems([11,4],'articles'),'articles'),[11,4]);
});
test('Collection, Picker, replacement and compact fields preserve each reference usage', async () => {
  const dom=new JSDOM('<!doctype html><body></body>');global.window=dom.window;global.document=dom.window.document;
  window.Joomla={getOptions:()=>({taxonomyEditor:'compact'})};
  dom.window.HTMLDialogElement.prototype.showModal=function(){this.open=true};
  dom.window.HTMLDialogElement.prototype.close=function(){this.open=false};
  window.SmartBrowser={mountCollection:mountSharedCollection};
  let request,result;
  window.SmartBrowserPicker={open:async config=>{request=config;return result}};
  const config=newContainer();config.blocks[0].articles=[entry(11,usage),4];
  config.blocks.push(newBlock('categories'));config.blocks[1].filters.categories=[entry(14,{'visual.iconOverride':'fas fa-folder'},'categories')];
  config.blocks[1].filters.tags=[entry(8,{'visual.presentation':'icon'},'tags')];
  const builder=new SourcesBuilder(config),completion=builder.open(),block=builder.config.blocks[0],record=builder.records.get(block);
  assert.equal(record.collection.config.selectionUseData,'editable');
  record.collection.config.onChange({items:referenceItems([4,entry(11,usage)],'articles')});
  await record.collection.setItems(referenceItems(block.articles,'articles'));
  assert.deepEqual(block.articles,[4,entry(11,usage)]);
  result={items:[entry(4,{}),entry(11,{...usage,'article.displayedName':'Changed'})]};
  await builder.pick(block,'articles',record,{selection:{adapter:'articles',id:'article:11'}});
  assert.equal(request.initialCollection[1].usage['article.displayedName'],'A local name');
  assert.deepEqual(request.initialFocus,{adapter:'articles',id:'article:11'});
  assert.equal(request.initialInfoOpen,true);
  assert.equal(request.multiple,true);
  assert.deepEqual(block.articles,[4,entry(11,{...usage,'article.displayedName':'Changed'})]);
  assert.equal(request.selectionProfile['visual.presentation'].presentation,'secondary');
  const category=builder.config.blocks[1],native=builder.records.get(category).filterFields.tags.input;
  native.options[0].selected=true;native.dispatchEvent(new dom.window.Event('change'));
  assert.equal(category.filters.tags[0].usage['visual.presentation'],'icon');
  builder.finish(null);await completion;
});
import { mountSharedCollection } from './shared-collection-fixture.mjs';
