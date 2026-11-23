import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { JSDOM } from 'jsdom';
import { scan, serialize, validate, newContainer, newBlock, articleIds, MAX_BYTES, ORDERS } from '../package/plugins/editors-xtd/sourcesbutton/media/js/inline.js';
import { SourcesBuilder } from '../package/plugins/editors-xtd/sourcesbutton/media/js/builder.js';

test('preview dependencies distinguish enabled, access, public policy and installation without changing identity',()=>{
  const result=spawnSync('php',['tests/php-preview-dependency.php'],{encoding:'utf8'});
  assert.equal(result.status,0,result.stdout+result.stderr);
});

test('numeric drafts validate only applicable controls, reveal collapsed fields and preserve zero/settings',async()=>{
  for(const type of ['articles','categories','tags'])for(const mode of ['separate','merged'])for(const editing of [false,true]){
    setup();const config=newContainer();config.blocks=[newBlock(type)];config.display=mode;
    const builder=new SourcesBuilder(config,editing),complete=builder.open(),record=builder.records.get(builder.config.blocks[0]);
    const containerLimit=builder.containerSettings.querySelector('input[type=number]');
    assert.equal(containerLimit.disabled,mode==='separate');
    if(record.limitField)assert.equal(record.limitField.querySelector('input').disabled,mode==='merged');
    const applicable=mode==='merged'?containerLimit:record.limitField?.querySelector('input');
    if(applicable){
      const previous=mode==='merged'?builder.config.presentation.limit:record.block.filters.limit;
      for(const value of ['', '-1','501','1.5']){
        applicable.value=value;applicable.dispatchEvent(new window.Event('input',{bubbles:true}));
        builder.setExpanded(record,false);
        assert.equal(builder.validateInputs(),false);
        assert.equal(applicable.getAttribute('aria-invalid'),'true');
        if(mode==='separate')assert.equal(record.content.hidden,false);
        assert.equal(mode==='merged'?builder.config.presentation.limit:record.block.filters.limit,previous);
      }
      applicable.value='0';applicable.dispatchEvent(new window.Event('input',{bubbles:true}));
      assert.equal(builder.validateInputs(),true);
    }
    const inactive=mode==='separate'?containerLimit:record.limitField?.querySelector('input');
    if(inactive){inactive.value='';assert.equal(builder.validateInputs(),true);}
    builder.confirm.click();const saved=await complete;
    assert.equal(saved.display,mode);assert.ok(saved.blocks.length);
  }
});

test('typed containers round trip nested Unicode JSON and preserve ordered IDs', () => {
  const config = newContainer(); config.blocks[0].title = 'Ελληνικά "} {sources:" \\ <tag>&';
  config.blocks[0].articles = [31,4,31]; config.blocks.push(newBlock('categories'), newBlock('tags'));
  config.blocks[1].filters.categories = [14]; config.blocks[2].filters.tags = [8,12];
  const token = serialize(config), tokens = scan('<p>' + token + '</p>' + token);
  assert.equal(tokens.length, 2); assert.deepEqual(tokens[0].config, validate(config));
  assert.deepEqual(tokens[0].config.blocks[0].articles, [31,4]); assert.ok(!token.includes('<tag>'));
});

test('saving clears obsolete manual limits but retains dynamic and container limits',()=>{
  const config=newContainer();config.blocks[0].filters.limit=16;config.blocks.push(newBlock('categories'),newBlock('tags'));
  config.blocks[1].filters.limit=12;config.blocks[2].filters.limit=7;config.presentation.limit=23;
  const saved=scan(serialize(config))[0].config;
  assert.deepEqual(saved.blocks.map(block=>block.filters.limit),[undefined,12,7]);assert.equal(saved.presentation.limit,23);
  assert.equal(Object.hasOwn(saved.blocks[0].filters,'limit'),false);
  for(const obsolete of ['',null,501]) { config.blocks[0].filters.limit=obsolete; assert.equal(Object.hasOwn(scan(serialize(config))[0].config.blocks[0].filters,'limit'),false); }
  config.blocks[0].filters.limit=16;
  assert.equal(config.blocks[0].filters.limit,16,'Normalization must not mutate the input object');
});
test('legacy, unknown, mixed, unsafe and oversized configurations fail closed', () => {
  assert.equal(scan('{sources:{"version":1,"display":"separate","blocks":[]}}')[0].config, null);
  for (const change of [c=>c.extra=true, c=>c.blocks[0].type='sql', c=>c.blocks[0].articles=[-1],
    c=>c.blocks[0].filters.tags=[8], c=>c.blocks[0].filters.order='SQL', c=>c.ordering.mode='SQL',
    c=>{c.blocks=[newBlock('categories')];c.blocks[0].filters.limit=501}, c=>c.blocks[0].filters.groupByCategory=true]) {
    const config=newContainer();change(config);assert.throws(()=>serialize(config));
  }
  assert.equal(scan('{sources:{"x":"' + 'x'.repeat(MAX_BYTES+5) + '"}}').length,0);
  assert.equal(scan(('ignored {sources:{').repeat(20000)).length,0);
});
test('tokens in attributes, comments and code are ignored', () => {
  const token=serialize(newContainer());
  assert.equal(scan('<a title=\''+token+'\'>x</a><!--'+token+'--><pre>'+token+'</pre><p>'+token+'</p>').length,1);
});
test('Articles references are ordered, homogeneous and omit resolved metadata', () => {
  assert.deepEqual(articleIds([{selection:{adapter:'articles',id:'article:7'},usage:{}},'article:3',7]),[7,3]);
  assert.throws(()=>articleIds(['category:4']));
});
function setup(compact=false) {
  const dom=new JSDOM('<!doctype html><body></body>',{url:'https://test.invalid'});
  global.window=dom.window;global.document=dom.window.document;
  window.Joomla={getOptions:()=>({taxonomyEditor:compact?'compact':'collection',filterFields:{
    tags:'<joomla-field-fancy-select><select multiple><option value="8">Tag 8</option><option value="12">Tag 12</option><option value="5">Tag 5</option></select></joomla-field-fancy-select>',
    categories:'<joomla-field-fancy-select><select multiple><option value="14">Category 14</option><option value="19">Category 19</option></select></joomla-field-fancy-select>'
  }})};
  dom.window.HTMLDialogElement.prototype.showModal=function(){this.open=true};
  dom.window.HTMLDialogElement.prototype.close=function(){this.open=false;this.dispatchEvent(new dom.window.Event('close'))};
  const instances=[],picks=[];
  window.SmartBrowser={mountCollection:(host,config)=>{
    const instance=mountSharedCollection(host,config);
    instances.push(instance);return instance;
  }};
  window.SmartBrowserPicker={open:async config=>{
    picks.push(config);const prefix={articles:'article',categories:'category',tags:'tag'}[config.adapter];
    return {items:[{selection:{adapter:config.adapter,id:prefix+':5'},usage:{}}]};
  }};
  return {instances,picks,dom};
}

test('dynamic block introduction toggle and limit share the first advanced row without changing their values', async () => {
  for (const type of ['articles', 'categories', 'tags']) {
    setup(); const config = newContainer(); config.blocks = [newBlock(type)];
    const builder = new SourcesBuilder(config), complete = builder.open();
    const input = builder.dialog.querySelector('.sources-intro-option');
    if (type === 'articles') assert.equal(input, null);
    else {
      assert.equal(input.type, 'button');
      assert.equal(input.getAttribute('aria-pressed'), 'false');
      const record = builder.records.get(builder.config.blocks[0]);
      assert.ok(record.advanced.querySelector('.sources-filter-primary').contains(input));
      assert.ok(input.closest('.sources-filter-primary').contains(record.limitField));
      assert.equal(record.settings.contains(record.limitField), false);
      assert.equal(record.advanced.querySelector('.sources-filter-primary').nextElementSibling, record.filters);
      input.click();
      assert.equal(validate(builder.config).blocks[0].showIntro, true);
      builder.config.display = 'merged'; builder.renderBlocks();
      assert.equal(builder.dialog.querySelector('.sources-intro-option').getAttribute('aria-pressed'), 'true');
      const current = builder.dialog.querySelector('.sources-intro-option');
      current.click();
      assert.equal('showIntro' in validate(builder.config).blocks[0], false);
    }
    builder.finish(null); await complete;
  }
});
test('three add actions create independent typed collections and node pickers', async () => {
  const {instances,picks}=setup();const builder=new SourcesBuilder(newContainer());const complete=builder.open();
  assert.equal(builder.addButtons.length,3);
  builder.addButtons[1].click();builder.addButtons[2].click();
  assert.deepEqual(builder.config.blocks.map(b=>b.type),['articles','categories','tags']);
  assert.deepEqual(instances.map(i=>i.config.adapter),['articles','categories','tags']);
  assert.equal(builder.confirm.className,'btn btn-success');
  const block=builder.config.blocks[1],record=builder.records.get(block);
  await builder.pick(block,'categories',record);assert.equal(picks[0].selectionTarget,'node');assert.deepEqual(block.filters.categories,[5]);
  assert.equal(record.filterFields.categories,undefined);assert.ok(record.filterFields.tags);
  instances[2].config.onChange({items:[{selection:{adapter:'tags',id:'tag:12'}}]});assert.deepEqual(builder.config.blocks[2].filters.tags,[12]);
  builder.finish(null);assert.equal(await complete,null);assert.ok(instances.every(i=>i.destroyed));
});
test('duplicate, delete and reorder preserve block type and independent state', async () => {
  setup();const original=newContainer();original.blocks=[newBlock('categories')];original.blocks[0].filters.categories=[14];
  const builder=new SourcesBuilder(original),complete=builder.open(),block=builder.config.blocks[0],record=builder.records.get(block);
  record.card.querySelector('[data-block-action="duplicate"]').click();
  const copy=builder.config.blocks[1];assert.equal(copy.type,'categories');assert.notEqual(copy.filters,block.filters);
  builder.move(copy,-1);assert.equal(builder.config.blocks[0],copy);
  builder.records.get(copy).remove.click();assert.equal(builder.config.blocks.length,1);
  record.toggle.click();assert.equal(record.toggle.firstElementChild.className,'fas fa-chevron-right');
  builder.finish(null);await complete;assert.deepEqual(original.blocks[0].filters.categories,[14]);
});

test('Expand/Collapse All follows manual toggles and block changes without modifying stored settings', async () => {
  setup();const config=newContainer();config.blocks.push(newBlock('tags'));
  const builder=new SourcesBuilder(config),complete=builder.open(),before=serialize(builder.config);
  assert.equal(builder.expandAll.title,'collapseAll');
  assert.equal(builder.expandAllLabel.textContent,'collapseAll');
  builder.expandAll.click();
  assert.ok([...builder.records.values()].every(record=>record.content.hidden && record.toggle.getAttribute('aria-expanded')==='false'));
  assert.equal(builder.expandAll.title,'expandAll');
  assert.equal(builder.expandAllLabel.textContent,'expandAll');
  builder.records.get(builder.config.blocks[0]).toggle.click();
  assert.equal(builder.expandAll.title,'collapseAll');
  builder.expandAll.click();builder.expandAll.click();
  assert.ok([...builder.records.values()].every(record=>!record.content.hidden));
  assert.equal(serialize(builder.config),before);
  builder.expandAll.click();builder.addButtons[0].click();
  assert.equal(builder.expandAll.title,'collapseAll');
  assert.equal(builder.records.get(builder.config.blocks.at(-1)).content.hidden,false);
  builder.config.blocks=[];builder.renderBlocks();assert.equal(builder.expandAll.disabled,true);
  builder.finish(null);await complete;
});

test('block clipboard copies validated JSON and pastes at the end, including into an empty Builder', async () => {
  setup(); let text = '';
  Object.defineProperty(window.navigator, 'clipboard', { configurable:true, value:{writeText:async value=>{text=value},readText:async()=>text} });
  const builder=new SourcesBuilder(),complete=builder.open();
  const heading=builder.list.querySelector('.sources-block-toolbar');
  assert.deepEqual([...heading.children].map(node=>node.dataset.blockAction || node.title),['up','down','duplicate','copy','delete']);
  assert.equal(builder.list.querySelector('.sources-block-menu'),null);
  assert.equal(builder.list.querySelector('[data-block-action="paste"]'),null);
  assert.ok(builder.add.contains(builder.pasteButton));
  assert.equal(document.getElementById(builder.add.getAttribute('aria-labelledby'))?.textContent,'addBlock');
  assert.equal(heading.querySelector('[data-block-action="duplicate"]').children.length,1);
  assert.ok(heading.querySelector('[data-block-action="duplicate"] .fa-plus'));
  assert.ok(heading.querySelector('[data-block-action="copy"] .fa-copy'));
  for(const type of ['articles','categories','tags']) {
    const block=newBlock(type); block.title='Clipboard '+type;
    const selection={selection:{adapter:type,id:({articles:'article',categories:'category',tags:'tag'})[type]+':14'},usage:{'ext.fixture':{keep:true}}};
    if(type==='articles') block.articles=[selection]; else block.filters[type]=[selection];
    builder.config.blocks=[block]; builder.renderBlocks();
    await builder.clipboardBlock(block);
    const expected=validate({...newContainer(),blocks:[block]}).blocks[0];
    assert.deepEqual(JSON.parse(text),expected);
    builder.config.blocks=[];builder.renderBlocks();
    assert.equal(builder.pasteButton.disabled,false);
    await builder.clipboardBlock(null,true);
    assert.deepEqual(builder.config.blocks,[expected]);
    await builder.clipboardBlock(null,true);
    assert.equal(builder.config.blocks.length,2);
    assert.notEqual(builder.config.blocks[0].filters,builder.config.blocks[1].filters);
  }
  builder.finish(null);await complete;
});

test('filter disclosure remembers the last user choice independently of block expansion and validation',async()=>{
  setup(); const key='supjx.sources.builder.filters.expanded',config=newContainer();config.blocks=[newBlock('tags')];
  let builder=new SourcesBuilder(config),done=builder.open(),record=builder.records.values().next().value;
  const before=serialize(builder.config);
  assert.equal(record.advanced.open,true);
  record.advanced.querySelector('summary').click();
  assert.equal(record.advanced.open,false);assert.equal(window.localStorage.getItem(key),'collapsed');
  builder.expandAll.click();assert.equal(window.localStorage.getItem(key),'collapsed');
  builder.finish(null);await done;
  const saved=window.localStorage.getItem(key);setup();window.localStorage.setItem(key,saved);
  builder=new SourcesBuilder(config,true,{onChange(){}});builder.mount(document.body);record=builder.records.values().next().value;
  assert.equal(record.advanced.open,false);assert.equal(record.advanced.querySelector('summary').getAttribute('aria-expanded'),'false');
  record.limitField.querySelector('input').value='';
  assert.equal(builder.validateInputs(),false);assert.equal(record.advanced.open,true);
  assert.equal(window.localStorage.getItem(key),'collapsed');
  record.advanced.querySelector('summary').click();record.advanced.querySelector('summary').click();
  assert.equal(window.localStorage.getItem(key),'expanded');assert.equal(serialize(builder.config),before);builder.destroy();
  builder=new SourcesBuilder(config);done=builder.open();assert.equal(builder.records.values().next().value.advanced.open,true);
  builder.finish(null);await done;
});

test('filter disclosure keeps a page-local preference when storage reads or writes fail',async()=>{
  for(const readFails of [true,false]){
    setup();Object.defineProperty(window,'localStorage',{configurable:true,get(){if(readFails)throw new Error('Blocked');return {getItem(){return 'expanded'},setItem(){throw new Error('Quota')}}}});
    const config=newContainer();config.blocks=[newBlock('categories')];
    let builder=new SourcesBuilder(config),done=builder.open();builder.records.values().next().value.advanced.querySelector('summary').click();builder.finish(null);await done;
    builder=new SourcesBuilder(config);done=builder.open();assert.equal(builder.records.values().next().value.advanced.open,false);builder.finish(null);await done;
  }
});

test('only the last All command persists expansion across Builder instances and reloads',async()=>{
  const {dom}=setup(),key='supjx.sources.builder.expanded';
  const config=newContainer();config.blocks.push(newBlock('tags'));
  let builder=new SourcesBuilder(config),done=builder.open();builder.expandAll.click();
  assert.equal(window.localStorage.getItem(key),'collapsed');
  for(const record of builder.records.values())record.toggle.click();
  assert.equal(window.localStorage.getItem(key),'collapsed');builder.finish(null);await done;
  builder=new SourcesBuilder(config);done=builder.open();assert.ok([...builder.records.values()].every(r=>r.content.hidden));
  builder.expandAll.click();assert.equal(window.localStorage.getItem(key),'expanded');
  for(const record of builder.records.values())record.toggle.click();
  assert.equal(window.localStorage.getItem(key),'expanded');builder.finish(null);await done;
  builder=new SourcesBuilder(config);done=builder.open();assert.ok([...builder.records.values()].every(r=>!r.content.hidden));
  builder.expandAll.click();builder.finish(null);await done;
  const preference=window.localStorage.getItem(key);setup();window.localStorage.setItem(key,preference);
  builder=new SourcesBuilder(config,true,{onChange(){}});builder.mount(document.body);
  assert.ok([...builder.records.values()].every(r=>r.content.hidden));builder.destroy();
  dom.window.close();
});

test('blocked storage preserves page-local All preference without breaking editing',async()=>{
  setup();Object.defineProperty(window,'localStorage',{configurable:true,get(){throw new Error('Blocked')}});
  let builder=new SourcesBuilder(),done=builder.open();builder.expandAll.click();builder.finish(null);await done;
  builder=new SourcesBuilder();done=builder.open();assert.equal(builder.records.values().next().value.content.hidden,true);
  builder.records.values().next().value.toggle.click();builder.finish(null);await done;
  builder=new SourcesBuilder();done=builder.open();assert.equal(builder.records.values().next().value.content.hidden,true);
  builder.finish(null);await done;
});

test('write-only storage failure still honors the last All command on the current page',async()=>{
  setup();Object.defineProperty(window,'localStorage',{configurable:true,value:{getItem(){return 'expanded'},setItem(){throw new Error('Quota')}}});
  let builder=new SourcesBuilder(),done=builder.open();builder.expandAll.click();builder.finish(null);await done;
  builder=new SourcesBuilder();done=builder.open();assert.equal(builder.records.values().next().value.content.hidden,true);
  builder.finish(null);await done;
});

test('invalid or denied clipboard operations never alter blocks and enforce container limits', async () => {
  setup(); let text='{"type":"sql"}';
  Object.defineProperty(window.navigator,'clipboard',{configurable:true,value:{readText:async()=>text,writeText:async()=>{throw new window.DOMException('Denied','NotAllowedError')}}});
  const builder=new SourcesBuilder(),complete=builder.open(),before=structuredClone(builder.config);
  for(const value of [text,'not JSON','x'.repeat(MAX_BYTES+1)]) {
    text=value;await builder.clipboardBlock(null,true);assert.deepEqual(builder.config,before);assert.equal(builder.alert.hidden,false);
  }
  await builder.clipboardBlock(builder.config.blocks[0]);assert.deepEqual(builder.config,before);assert.equal(builder.pending,0);
  text=JSON.stringify(newBlock());builder.config.blocks=Array.from({length:30},()=>newBlock());builder.renderBlocks();
  assert.equal(builder.pasteButton.disabled,true);await builder.clipboardBlock(null,true);assert.equal(builder.config.blocks.length,30);
  builder.finish(null);await complete;
});
test('common merged ordering disables internal ordering without dropping selection', async () => {
  const {instances}=setup();const config=newContainer();config.blocks[0].articles=[11,4];
  config.blocks.push(newBlock('categories'));
  const builder=new SourcesBuilder(config),complete=builder.open();builder.modes[1].click();
  builder.config.ordering.mode='global';builder.refreshDisplay();
  assert.equal(instances[0].destroyed,true);assert.equal(instances.at(-2).config.allowOrdering,false);
  assert.deepEqual(builder.config.blocks[0].articles,[11,4]);
  const category=builder.records.get(builder.config.blocks[1]);
  assert.equal(category.groupInput,undefined);assert.equal(category.orderField.querySelector('select').disabled,true);
  assert.equal(category.limitField.hidden,true);assert.ok(category.filters.classList.contains('sources-filter-single'));
  builder.config.ordering.mode='blocks';builder.refreshDisplay();assert.equal(category.orderField.hidden,false);
  assert.equal(category.limitField.hidden,true);
  builder.modes[0].click();assert.equal(category.limitField.hidden,false);
  builder.finish(null);await complete;
});
test('compact category and tag fields share one draft, including unavailable IDs', async () => {
  const {instances}=setup(true);const config=newContainer();config.blocks=[newBlock('categories'),newBlock('tags')];
  config.blocks[0].filters.categories=[99];config.blocks[1].filters.tags=[8,12];
  const builder=new SourcesBuilder(config),complete=builder.open();assert.equal(instances.length,0);
  const record=builder.records.get(builder.config.blocks[0]);
  assert.deepEqual([...record.filterFields.categories.input.selectedOptions].map(o=>Number(o.value)),[99]);
  await builder.pick(builder.config.blocks[0],'categories',record);assert.deepEqual(builder.config.blocks[0].filters.categories,[5]);
  assert.deepEqual([...record.filterFields.categories.input.selectedOptions].map(o=>Number(o.value)),[5]);
  builder.finish(null);await complete;
});
test('full-selection replacement updates the draft and Builder cancel preserves the original', async () => {
  const {instances}=setup();const config=newContainer();config.blocks[0].articles=[11,4];
  const builder=new SourcesBuilder(config),complete=builder.open();
  assert.equal(instances[0].config.onReplace,undefined);
  await builder.pick(builder.config.blocks[0],'articles',builder.records.get(builder.config.blocks[0]),{id:'article:4'});
  assert.deepEqual(builder.config.blocks[0].articles,[5]);
  builder.finish(null);await complete;assert.deepEqual(config.blocks[0].articles,[11,4]);
});
test('PHP schema agrees with all block types and ordering choices', () => {
  for (const order of ORDERS) {
    const config=newContainer();config.blocks.push(newBlock('categories'),newBlock('tags'));
    config.ordering={mode:'global',order};config.blocks[1].filters.groupByCategory=true;
    config.blocks[1].filters.categories=[14];config.blocks[2].filters.tags=[8];
    const result=spawnSync('php',['tests/php-inline.php'],{input:JSON.stringify({valid:serialize(config),future:serialize(config).replace('"version":2','"version":17'),expected:validate(config)}),encoding:'utf8'});
    assert.equal(result.status,0,result.stderr+result.stdout);
  }
});
test('PHP renderer enforces ACL, typed selection and merged ordering', () => {
  const result=spawnSync('php',['tests/php-renderer.php'],{encoding:'utf8'});assert.equal(result.status,0,result.stderr+result.stdout);
});
test('Sources asset registrations resolve using actual Joomla WebAsset APIs', context => {
  const result=spawnSync('php',['tests/php-assets.php'],{encoding:'utf8'});
  if(result.status===3){context.skip(result.stderr);return}assert.equal(result.status,0,result.stderr+result.stdout);
});
test('preview subscriber returns data through the actual Joomla dispatcher and AjaxEvent', context => {
  const result=spawnSync('php',['tests/php-preview-event.php'],{encoding:'utf8'});
  if(result.status===3){context.skip(result.stderr);return}
  assert.equal(result.status,0,result.stderr+result.stdout);
  assert.match(result.stdout,/Real Joomla AjaxEvent preview assertions passed/);
});
test('new draft starts with articles, last block is deletable and empty insertion stays disabled', async () => {
  setup();const builder=new SourcesBuilder(),complete=builder.open();
  assert.equal(builder.config.blocks.length,1);assert.equal(builder.config.blocks[0].type,'articles');
  const block=builder.config.blocks[0],record=builder.records.get(block);
  assert.equal(builder.confirm.disabled,false);assert.equal(record.remove.disabled,false);
  assert.equal(record.settings.querySelector('input[type=number]'),null);
  record.remove.click();assert.equal(builder.config.blocks.length,0);assert.equal(builder.confirm.disabled,true);
  builder.finish(null);assert.equal(await complete,null);
});
test('one title changes label by display mode and survives switching', async () => {
  const {instances}=setup();const config=newContainer();config.blocks[0].name='Admin name';config.blocks[0].title='Public label';
  const builder=new SourcesBuilder(config),complete=builder.open(),record=builder.records.get(builder.config.blocks[0]);
  assert.equal(record.title.textContent,'Public label');assert.equal(builder.config.blocks[0].name,undefined);
  builder.modes[1].click();assert.equal(record.display.hidden,true);assert.equal(record.captionField.hidden,false);
  assert.equal(record.captionField.firstElementChild.textContent,'name');
  assert.equal(record.titleInput.value,'Public label');
  builder.modes[0].click();assert.equal(record.captionField.firstElementChild.textContent,'caption');
  builder.modes[1].click();
  assert.equal(builder.mergeOrdering.lastElementChild.hidden,true);
  assert.equal(builder.orderingHint.hidden,false);
  builder.config.ordering.mode='global';builder.refreshDisplay();assert.equal(builder.mergeOrdering.lastElementChild.hidden,false);
  assert.equal(builder.orderingHint.hidden,true);
  assert.equal(instances[0].config.informationPanel,true);
  builder.finish(null);await complete;
});
import { mountSharedCollection } from './shared-collection-fixture.mjs';
