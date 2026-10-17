import { chromium } from 'playwright';
import { createServer } from 'node:http';
import { readFile, mkdir } from 'node:fs/promises';
import { resolve, dirname, extname } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';
const repo = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const smart = resolve(repo, '../pkg_smartbrowser/package/component/media');
const joomla = resolve(repo, '../../JoomlaTest/site');
const sources = resolve(repo, 'package/plugins/editors-xtd/sourcesbutton/media');
const artifacts = resolve(repo, 'tests/artifacts'); await mkdir(artifacts, { recursive: true });
const resources = ids => ids.map(id => ({ id, title: id === 'article:99' ? 'Unavailable reference' : 'Article ' + id.split(':')[1],
  kind: id.startsWith('article:') ? 'item' : 'node',
  type: id === 'article:99' ? 'unavailable' : id.startsWith('category:') ? 'category' : id.startsWith('tag:') ? 'tag' : 'article',
  unavailable: id === 'article:99', icon: 'fas fa-book', image: null,
  metadata: { cardSummary: 'Resource metadata', id: id.split(':')[1] }, capabilities: {},
  selectionCapabilities: [
    {key:'article.displayedName',type:'string',editor:'text',label:'Displayed name',default:null,validation:{maxLength:255}},
    {key:'visual.thumbnailOverride',type:'resource',editor:'resource',visualRole:'thumbnail',label:'Thumbnail',default:null,picker:{adapter:'media',selectionTarget:'item',allowedResourceTypes:['image']}},
    {key:'visual.iconOverride',type:'string',editor:'text',visualRole:'icon',label:'Custom icon',default:null},
    {key:'visual.presentation',type:'string',editor:'select',label:'Visual presentation',default:null,options:[{value:'thumbnail',label:'Image'},{value:'icon',label:'Icon'},{value:'both',label:'Both'}]},
  ] }));
const server = createServer(async (req, res) => {
  try {
    const url = new URL(req.url, 'http://localhost'); let body, mime = 'text/html';
    if (url.pathname === '/') {
      const legacy = url.searchParams.has('jce');
      const administrator = url.searchParams.has('admin');
      body = `<!doctype html><html data-bs-theme="light"><head><meta name="viewport" content="width=device-width,initial-scale=1">
      <link rel="icon" href="data:,"><link rel="stylesheet" href="/joomla/media/templates/${administrator ? 'administrator/atum' : 'site/cassiopeia'}/css/template.css"><link rel="stylesheet" href="/smart/css/smartbrowser.css"><link rel="stylesheet" href="/sources/css/sourcesbutton.css">
      <link rel="stylesheet" href="/joomla/media/system/css/joomla-fontawesome.min.css">
      <link rel="stylesheet" href="/joomla/media/vendor/choicesjs/css/choices.min.css">
      <style>body{font:14px system-ui;margin:16px;background:#f5f7f9}textarea{width:100%;height:400px}</style>
      <script type="importmap">{"imports":{"editor-api":"/joomla/media/system/js/editors/editor-api.js","editor-decorator":"/joomla/media/system/js/editors/editor-decorator.js"}}</script>
      <script>window.Joomla={editors:{instances:{}},getOptions:(key,fallback)=>window.options?.[key]||fallback||{},Text:{_:key=>key},renderMessages:message=>window.messages=message,
        request:configuration=>{const c=new AbortController();fetch(configuration.url,{method:configuration.method,body:configuration.data,headers:configuration.headers,signal:c.signal}).then(r=>r.text()).then(configuration.onSuccess).catch(()=>configuration.onError({status:500}));return {abort:()=>c.abort()}}};
        window.options={'com_smartbrowser.collection':{adapter:'articles',apiBaseUrl:location.origin+'/api?format=json',csrfToken:'test-token',translate:key=>({COM_SMARTBROWSER_COLLECTION_TITLE:'Selected sources',COM_SMARTBROWSER_COLLECTION_REMOVE:'Remove',COM_SMARTBROWSER_COLLECTION_EMPTY:'No selected sources',JGRID_HEADING_ORDERING:'Collection order',COM_SMARTBROWSER_GRID:'Grid',COM_SMARTBROWSER_DETAILS:'Details',COM_SMARTBROWSER_SORT_BY:'Sort by',COM_SMARTBROWSER_NAME:'Title'}[key]||key)},
        'com_smartbrowser.picker':{url:location.origin+'/picker'},
        'plg_editorsxtd_sourcesbutton.inline':{editorSizeUrl:'/smart/js/picker-size.js',contentCss:'/sources/css/sourcesbutton.css?v=current-fixture',fontAwesomeUrl:'/joomla/media/system/css/joomla-fontawesome.min.css',previewMode:'placeholder',badgeLabel:'Sources',csrfToken:'test-token',ajaxUrl:'/preview',
        taxonomyEditor:location.search.includes('compact=1')?'compact':'collection',
        filterFields:${JSON.stringify({ tags: '<joomla-field-fancy-select><select id="tags" name="tags[]" multiple><option value="8">Tag 8</option><option value="12">Tag 12</option><option value="5">Tag 5</option></select></joomla-field-fancy-select>', categories: '<joomla-field-fancy-select><select id="categories" name="categories[]" multiple><option value="14">Category 14</option><option value="19">Category 19</option></select></joomla-field-fancy-select>' })},
        i18n:{builder:'Sources Builder',insert:'Insert',update:'Update',cancel:'Cancel',addBlock:'Add block',addSources:'Add / change sources',title:'Title',sources:'Sources',presentation:'Presentation',filters:'Filters / advanced',tags:'Tags',categories:'Categories',limit:'Limit',layout:'Layout',separate:'Separate',merged:'Merged',duplicate:'Duplicate',delete:'Delete',up:'Move up',down:'Move down',untitled:'Source block',edit:'Edit Sources',advancedTags:'Advanced tag selection',advancedCategories:'Advanced category selection'}}};
      </script>
      <script src="/smart/js/dialog-dismiss.js"></script><script src="/smart/js/media-value.js"></script><script src="/smart/js/picker.js"></script>
      <script src="/joomla/media/vendor/choicesjs/js/choices.min.js"></script><script type="module" src="/joomla/media/system/js/fields/joomla-field-fancy-select.js"></script>
      <script src="/joomla/media/${legacy ? 'plg_editors_jce/tinymce' : 'vendor/tinymce'}/tinymce.js"></script>
      </head><body><form><textarea id="article" name="jform[articletext]"></textarea></form><button id="sources">Sources</button>
      <script type="module">
      import { JoomlaEditor, JoomlaEditorDecorator, JoomlaEditorButton } from 'editor-api';
      import { newContainer, newBlock, serialize, scan } from '/sources/js/inline.js';
      import * as integration from '/sources/js/sourcesbutton.js';
      await import('/smart/js/collection.js');
      const ready=editor=>{
        integration.bindEditor(editor); window.editor=editor;
        editor.getDoc().defaultView.frameElement.style.width='100%';
        class Decorator extends JoomlaEditorDecorator{getValue(){return editor.getContent()}setValue(v){editor.setContent(v)}getSelection(){return editor.selection.getContent({format:'text'})}replaceSelection(v){editor.insertContent(v)}};
        const decorated=new Decorator(editor,'${legacy ? 'jce' : 'tinymce'}','article');JoomlaEditor.register(decorated);JoomlaEditor.setActive(decorated);
        window.decorated=decorated;window.inline={newContainer,newBlock,serialize,scan};window.integration=integration;
        const c=newContainer();c.blocks[0].title='Related articles';c.blocks[0].articles=[11,4,99];
        c.blocks.push(newBlock('categories'),newBlock('tags'));c.blocks[1].filters.categories=[14];c.blocks[1].filters.tags=[8,12];c.blocks[2].filters.tags=[8,12];
        editor.setContent('<p>Before</p><p>'+serialize(c)+'</p><p>Between</p><p>'+serialize(c)+'</p><p>After</p>');
        document.querySelector('#sources').onclick=()=>JoomlaEditorButton.runAction('sourcesbutton:insert');window.ready=true;
      };
      ${legacy ? `tinymce.init({mode:'exact',elements:'article',theme:'core',compress:{css:false},plugins:'noneditable',theme_advanced_buttons1:'bold,italic',theme_advanced_buttons2:'',theme_advanced_buttons3:'',width:'100%',height:500,setup:ed=>{integration.bindEditor(ed);ed.onInit.add(()=>ready(ed))}});`
        : `tinymce.init({selector:'#article',license_key:'gpl',plugins:'code',toolbar:'undo redo code',height:500,setup:ed=>{integration.bindEditor(ed);ed.on('init',()=>ready(ed))}});`}
      </script></body></html>`;
    } else if (url.pathname === '/picker') {
      body = `<!doctype html><button id="choose">Choose Article 8</button><button id="cancel">Cancel picker</button><script>
        const id=new URLSearchParams(location.search).get('pickerInstance');
        window.context=parent.SmartBrowserPicker.context(id,window);
        const adapter=new URLSearchParams(location.search).get('adapter'),prefix=adapter==='tags'?'tag':adapter==='categories'?'category':'article',value=adapter==='tags'?5:adapter==='categories'?19:8;
        document.querySelector('#choose').textContent='Choose '+prefix+' '+value;
        document.querySelector('#choose').onclick=()=>parent.document.dispatchEvent(new parent.CustomEvent('smartbrowser:select',{detail:{pickerInstance:id,adapter,collectionItems:[{selection:{adapter,id:prefix+':'+value},usage:{}}]}}));
        document.querySelector('#cancel').onclick=()=>context.cancel();</script>`;
    } else if (url.pathname === '/api') {
      let raw = ''; for await (const chunk of req) raw += chunk; const data = JSON.parse(raw);
      const entries = data.items.map(item => item?.selection ? item : { selection: { adapter: 'articles', id: String(item).includes(':') ? String(item) : 'article:' + item }, usage: {} });
      let ids = entries.map(item => item.selection.id);
      if (data.operation === 'reorder') {
        const chosen = new Set(data.selection), direction = data.direction === 'up' ? -1 : 1;
        const indices = direction < 0 ? ids.map((_, i) => i) : ids.map((_, i) => i).reverse();
        indices.forEach(i => { const j = i + direction; if (chosen.has(ids[i]) && j >= 0 && j < ids.length && !chosen.has(ids[j])) [ids[i], ids[j]] = [ids[j], ids[i]]; });
        body = JSON.stringify({ success: true, data: { items: ids } });
      } else body = JSON.stringify({ success: true, data: { items: entries, identifiers: ids, resources: resources(ids).map((resource, index) => ({ ...resource, adapter: entries[index].selection.adapter, selection: entries[index].selection, selectionKey: JSON.stringify([entries[index].selection.adapter, resource.id]) })), actions: [], presentation: { gridFields: [{ source: 'metadata.cardSummary' }], sortFields: [{ id: 'title', label: 'Title' }], columns: [{ id: 'title', label: 'Title', source: 'title' }] } } });
      mime = 'application/json';
    } else if (url.pathname === '/template-preview.css') {
      body=await readFile(resolve(repo,'../../Catichisi.gr/templates/site/jl_kidsacademy_free/custom/css-compiled/custom.css'));mime='text/css';
    } else if (url.pathname === '/preview') {
      const failure = url.searchParams.get('failure'); mime = 'application/json';
      if (failure === 'forbidden') { res.writeHead(403, {'Content-Type':mime});res.end('{}');return; }
      if (failure === 'json') body = '<html>Login required</html>';
      else if (failure === 'empty') body = JSON.stringify({success:true,data:[]});
      else if (failure === 'permission') body = JSON.stringify({success:false,message:'Sources preview not authorised.'});
      else if (failure) body = JSON.stringify({success:true,data:[{ok:false,html:'',code:failure}]});
      else body = JSON.stringify({ success: true, data: [{ ok: true, html: '<div>Resolved preview</div>' }] });
    } else {
      const root = url.pathname.startsWith('/sources/') ? sources : url.pathname.startsWith('/smart/') ? smart : url.pathname.startsWith('/joomla/') ? joomla : null;
      if (!root) throw new Error('Unknown path'); const relative = url.pathname.split('/').slice(2).join('/'); const file = resolve(root, relative);
      if (!file.startsWith(root + '/'.replace('/', process.platform === 'win32' ? '\\' : '/'))) throw new Error('Invalid path');
      body = await readFile(file); mime = { '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.html': 'text/html', '.svg': 'image/svg+xml', '.png': 'image/png' }[extname(file)] || 'application/octet-stream';
    }
    res.writeHead(200, { 'Content-Type': mime + (mime.startsWith('text/') ? '; charset=utf-8' : '') }); res.end(body);
  } catch (error) { res.writeHead(404); res.end(); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = 'http://127.0.0.1:' + server.address().port;
const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
  for (const administrator of [false,true]) for (const legacy of [false,true]) for (const width of [1280,390]) {
    const page=await browser.newPage({viewport:{width,height:1000}});page.setDefaultTimeout(15000);const errors=[];
    page.on('pageerror',error=>errors.push(error.message));
    await page.goto(base+'/?'+new URLSearchParams({...legacy?{jce:'1',compact:'1'}:{},...administrator?{admin:'1'}:{}}));
    await page.waitForFunction(()=>window.ready);
    await page.waitForFunction(()=>editor.getDoc().querySelectorAll('.sources-editor-token').length===2);
    assert.equal(await page.evaluate(()=>{
      const doc=editor.getDoc(),link=[...doc.querySelectorAll('link[data-sources-css]')].find(node=>node.href.includes('sourcesbutton.css'));
      link.href='/sources/css/sourcesbutton.css?v=stale-fixture';integration.decorateDocument(doc);
      return new URL(link.href).searchParams.get('v');
    }),'current-fixture','A reused editor document refreshes stale preview CSS');
    await page.evaluate(()=>Joomla.getOptions('plg_editorsxtd_sourcesbutton.inline').i18n.blockOrderingHint='Each block is ordered separately.');
    const before=await page.evaluate(()=>editor.getContent());
    assert.equal(before.includes('data-sources-token'),false);
    const edit=async()=>page.evaluate(()=>editor.getDoc().querySelector('[data-sources-edit]').click());
    await edit();
    const manual=page.locator('.sources-block[data-type=articles]').first();
    const category=page.locator('.sources-block[data-type=categories]').first();
    assert.equal(await page.locator('.sources-add-actions>button').count(),3);
    const picker=page.frameLocator('.smartbrowser-picker iframe');
    await manual.locator('.sb-collection-count-toggle').click();
    await manual.getByText('Article 11',{exact:true}).first().waitFor();
    await manual.locator('.sources-collection').getByRole('button',{name:'add_articles',exact:true}).click();
    await picker.getByRole('button',{name:'Cancel picker'}).click();
    assert.equal(await page.locator('.sources-builder-dialog:modal').count(),1);
    await page.getByRole('button',{name:'maximize',exact:true}).click();
    const box=await page.locator('.sources-builder-dialog').boundingBox();assert.ok(Math.abs(box.width-width)<2);
    await page.getByRole('button',{name:'restore',exact:true}).click();
    if(legacy) await category.getByRole('button',{name:'Advanced category selection',exact:true}).click();
    else {
      await category.locator('.sb-collection-count-toggle').click();
      await category.locator('.sources-collection').getByRole('button',{name:'add_categories',exact:true}).click();
    }
    assert.equal(await picker.locator('body').evaluate(()=>new URLSearchParams(location.search).get('selectionTarget')),'node');
    await picker.getByRole('button',{name:'Choose category 19'}).click();
    await category.getByRole('button',{name:'Advanced tag selection',exact:true}).click();
    await picker.getByRole('button',{name:'Choose tag 5'}).click();
    assert.deepEqual(await category.locator('.sources-advanced joomla-field-fancy-select').evaluate(w=>w.choicesInstance.getValue(true)),['5']);
    assert.equal(await category.locator('.sources-group-switch input').count(),0);
    assert.deepEqual(await category.locator('.sources-advanced joomla-field-fancy-select').evaluate(w=>w.choicesInstance.getValue(true)),['5'],'After grouping');
    await category.locator('.sources-advanced select').last().selectOption('title-asc');
    await page.locator('.sources-add-actions>button').nth(1).click();
    assert.equal(await page.locator('.sources-block[data-type=categories]').count(),2);
    await page.locator('.sources-block[data-type=categories]').last().locator('.sources-block-delete').click();
    await category.locator('.sources-block-settings input[type=text]').fill('Category identity');
    assert.deepEqual(await category.locator('.sources-advanced joomla-field-fancy-select').evaluate(w=>w.choicesInstance.getValue(true)),['5'],'After add/delete');
    await page.getByRole('button',{name:'Merged',exact:true}).click();
    await page.getByText('Each block is ordered separately.',{exact:true}).waitFor();
    await page.locator('.sources-merge-ordering').screenshot({path:resolve(artifacts,'ordering-note-'+(administrator?'admin':'site')+'-'+(legacy?'jce':'tiny')+'-'+width+'.png')});
    assert.equal(await category.locator('.sources-block-settings>label').first().locator('span').first().textContent(),'name');
    await page.locator('.sources-merge-ordering select').first().selectOption('global');
    assert.equal(await page.locator('.sources-ordering-hint').isVisible(),false);
    assert.equal(await category.locator('.sources-block-settings input[type=number]').isVisible(),false);
    assert.equal(await category.locator('.sources-filter-actions').evaluate(row=>{
      const field=row.querySelector('.sources-filter-field');return Math.abs(field.getBoundingClientRect().width-row.getBoundingClientRect().width)<2;
    }),true,'Single merged filter spans the entire row');
    assert.equal(await category.locator('.sources-block-settings input[type=text]').inputValue(),'Category identity');
    await category.locator('.sources-filter-actions').scrollIntoViewIfNeeded();
    await page.screenshot({path:resolve(artifacts,'merged-'+(administrator?'admin':'site')+'-'+(legacy?'jce':'tiny')+'-'+width+'.png'),fullPage:true});
    assert.equal(await category.locator('.sources-advanced select').last().isDisabled(),true);
    await page.locator('.sources-merge-ordering select').first().selectOption('blocks');
    await page.getByRole('button',{name:'Separate',exact:true}).click();
    assert.equal(await category.locator('.sources-block-settings>label').first().locator('span').first().textContent(),'caption');
    assert.deepEqual(await category.locator('.sources-advanced joomla-field-fancy-select').evaluate(w=>w.choicesInstance.getValue(true)),['5'],'After merged switches');
    const row=category.locator('.sources-block-settings');
    assert.equal(await row.evaluate(row=>{
      const ys=[...row.querySelectorAll('input,.sources-layout-menu>summary')].map(n=>n.getBoundingClientRect().top);
      return Math.max(...ys)-Math.min(...ys)<2;
    }),true,'Settings aligned with real template CSS');
    const tagField=category.locator('.sources-advanced .sources-filter-field');
    await tagField.locator('.choices__input--cloned').click();
    await tagField.locator('.choices__input--cloned').fill('Tag 8');
    const choice=tagField.locator('.choices__list--dropdown [data-choice][data-value="8"]');
    await choice.waitFor();
    assert.equal(await choice.evaluate(n=>{
      const r=n.getBoundingClientRect();return n.contains(document.elementFromPoint(r.left+r.width/2,r.top+r.height/2));
    }),true,'No Atum clipping or Selection View overlap');
    await tagField.locator('.choices__input--cloned').press('Escape');
    assert.deepEqual(await category.locator('.sources-advanced joomla-field-fancy-select').evaluate(w=>w.choicesInstance.getValue(true)),['5'],'After dropdown search');
    await page.screenshot({path:resolve(artifacts,'typed-'+(administrator?'admin':'site')+'-'+(legacy?'jce':'tiny')+'-'+width+'.png'),fullPage:true});
    assert.equal(await page.locator('.sources-builder-dialog').evaluate(d=>d.scrollWidth>d.clientWidth+1),false);
    await page.getByRole('button',{name:'Update',exact:true}).click();
    await page.waitForFunction(legacy=>{
      const ids=inline.scan(editor.getContent())[0].config.blocks[1].filters.categories;
      return legacy ? ids.length===1 && ids[0]===19 : ids.length===2 && ids[0]===14 && ids[1]===19;
    },legacy);
    assert.deepEqual(await page.evaluate(()=>inline.scan(editor.getContent())[0].config.blocks[1].filters.tags),[5]);
    assert.equal(await page.evaluate(()=>inline.scan(editor.getContent())[0].config.blocks[1].title),'Category identity');
    assert.equal(await page.evaluate(()=>inline.scan(editor.getContent())[0].config.blocks[1].name),undefined);
    assert.equal(await page.evaluate(()=>inline.scan(editor.getContent())[0].config.blocks[1].filters.groupByCategory),undefined);
    assert.deepEqual(await page.evaluate(()=>inline.scan(editor.getContent())[1].config.blocks[1].filters.categories),[14]);
    await edit();
    assert.deepEqual(await category.locator('.sources-advanced joomla-field-fancy-select').evaluate(w=>w.choicesInstance.getValue(true)),['5']);
    if(legacy) assert.deepEqual(await category.locator('.sources-collection joomla-field-fancy-select').evaluate(w=>w.choicesInstance.getValue(true)),['19']);
    await page.getByRole('button',{name:'Cancel',exact:true}).last().click();
    const saved=await page.evaluate(()=>{editor.save();return document.querySelector('#article').value});
    assert.equal(saved.includes('data-sources-token'),false);
    await page.evaluate(v=>editor.setContent(v),saved);
    await page.waitForFunction(()=>editor.getDoc().querySelectorAll('.sources-editor-token').length===2);
    const frame=page.frameLocator('iframe').first();
    await frame.locator('[data-sources-settings]').first().click();
    await frame.locator('[data-sources-mode=render]').first().click();
    await page.waitForFunction(()=>editor.getDoc().body.textContent.includes('Resolved preview'));
    assert.equal((await page.evaluate(()=>editor.getContent())).includes('Resolved preview'),false);
    const previewSource=await page.evaluate(()=>editor.getContent());
    for(const [failure,message] of [['empty','previewEmpty'],['forbidden','previewPermission'],['permission','previewPermission'],['json','previewResponse'],['invalid_configuration','previewInvalid'],['plugin_disabled','previewDisabled'],['render_failed','previewFailed']]){
      await page.evaluate(failure=>{Joomla.getOptions('plg_editorsxtd_sourcesbutton.inline').ajaxUrl='/preview?failure='+failure},failure);
      await frame.locator('[data-sources-mode=render]').first().click();
      await frame.locator('.sources-editor-token__body').first().getByText(message,{exact:true}).waitFor();
      assert.equal(await page.evaluate(()=>editor.getContent()),previewSource,'Preview errors never change inline configuration');
    }
    await page.evaluate(()=>{Joomla.getOptions('plg_editorsxtd_sourcesbutton.inline').ajaxUrl='/preview'});
    await frame.locator('[data-sources-mode=render]').first().click();
    await page.waitForFunction(()=>editor.getDoc().body.textContent.includes('Resolved preview'));
    await page.evaluate(()=>{
      const paragraph=[...editor.getBody().querySelectorAll('p')].find(p=>p.textContent==='Before');
      editor.selection.select(paragraph);editor.selection.collapse(false);integration.openBuilder(decorated);
    });
    assert.equal(await page.getByRole('button',{name:'Insert',exact:true}).getAttribute('class'),'btn btn-success');
    assert.equal(await page.locator('.sources-block[data-type=articles]').count(),1,'Fresh Builder starts with specific articles');
    assert.equal(await page.getByRole('button',{name:'Insert',exact:true}).isDisabled(),false);
    await page.locator('.sources-block-delete').click();
    assert.equal(await page.locator('.sources-block').count(),0,'Last block can be deleted');
    assert.equal(await page.getByRole('button',{name:'Insert',exact:true}).isDisabled(),true);
    await page.getByRole('button',{name:'Cancel',exact:true}).last().click();
    await page.evaluate(async () => {
      const host=document.createElement('div');host.dataset.sourcesField='';
      host.dataset.labels=JSON.stringify({preview:'Field Preview',close:'Close preview',invalid:'Invalid Sources',empty:'No sources',pending:'Finish selection'});
      const input=document.createElement('textarea');input.className='sources-field-value';input.id='related-sources';
      input.name='jform[com_fields][related_sources]';const c=inline.newContainer();c.blocks[0].articles=[11,4];
      input.value=JSON.stringify(c);host.append(input);document.querySelector('form').append(host);
      const {mountSourcesField}=await import('/sources/js/field.js');window.sourceField=mountSourcesField(host);
    });
    const field=page.locator('[data-sources-field]');
    await page.evaluate(()=>Promise.all([...sourceField.builder.records.values()].map(record=>record.collection?.ready)));
    assert.equal(await field.locator('.sources-builder').evaluate(el=>el.tagName),'DIV','Embedded Builder does not nest its form in the article form');
    assert.equal(await field.locator('.sources-builder-embedded').evaluate(el=>el.tagName),'DIV','Embedded Builder is not a dialog');
    const fieldCaption=field.locator('.sources-block-settings input[type=text]').first();
    await fieldCaption.fill('Field heading before save');
    assert.equal(await field.locator('.sources-field-value').evaluate(el=>JSON.parse(el.value).blocks[0].title),'Field heading before save');
    assert.equal(await field.locator('.sources-field-value').evaluate(el=>el.form.checkValidity()),true,'Embedded SmartBrowser dialogs do not invalidate the article form');
    await field.locator('.sources-collection').getByRole('button',{name:'add_articles',exact:true}).click();
    await picker.getByRole('button',{name:'Cancel picker'}).click();
    assert.deepEqual(await field.locator('.sources-field-value').evaluate(el=>JSON.parse(el.value).blocks[0].articles),[11,4],'Cancelling embedded selection preserves current source order');
    await field.locator('.sources-collection').getByRole('button',{name:'add_articles',exact:true}).click();
    await picker.getByRole('button',{name:'Choose article 8'}).click();
    await page.waitForFunction(()=>JSON.parse(document.querySelector('.sources-field-value').value).blocks[0].articles.join(',')==='11,4,8');
    const usageName=field.getByLabel('Displayed name',{exact:true});
    await usageName.fill('Local article name');
    await page.waitForFunction(()=>JSON.parse(document.querySelector('.sources-field-value').value).blocks[0].articles[0]?.usage?.['article.displayedName']==='Local article name');
    const advancedUsage=field.locator('details.resource-usage-secondary');
    assert.equal(await advancedUsage.getAttribute('open'),null,'Advanced usage starts collapsed');
    await advancedUsage.locator('summary').click();
    await page.evaluate(()=>{
      const record=[...sourceField.builder.records.values()][0];
      window.selectionEdit=record.collection.editSelection().catch(error=>{window.selectionEditError=error.message;});
    });
    assert.equal(await picker.locator('body').evaluate(()=>window.context.getCollectionSnapshot().items[0].usage['article.displayedName']),'Local article name','Picker reopens with stored Usage');
    await picker.getByRole('button',{name:'Cancel picker'}).click();
    await field.getByLabel('Custom icon',{exact:true}).fill('fas fa-star');
    await page.waitForFunction(()=>JSON.parse(document.querySelector('.sources-field-value').value).blocks[0].articles[0]?.usage?.['visual.iconOverride']==='fas fa-star');
    await advancedUsage.locator('summary').click();
    await field.getByRole('button',{name:'Field Preview',exact:true}).click();
    await page.frameLocator('.sources-field-preview iframe').getByText('Resolved preview',{exact:true}).waitFor();
    assert.equal(await page.frameLocator('.sources-field-preview iframe').locator('body').getAttribute('class'),'sources-preview-surface');
    assert.equal(await page.frameLocator('.sources-field-preview iframe').locator('html').evaluate(el=>getComputedStyle(el).scrollbarGutter),'stable','Field Preview reserves a stable scrollbar gutter');
    await page.getByRole('button',{name:'Close preview',exact:true}).click();
    assert.equal(await field.locator('.sources-field-value').evaluate(el=>el.form.checkValidity()),true,'Preview does not invalidate the native article form');
    await field.screenshot({path:resolve(artifacts,(administrator?'admin':'site')+'-field-'+(legacy?'jce':'tiny')+'-'+width+'.png')});
    await page.evaluate(async()=>{
      const host=document.querySelector('[data-sources-field]');sourceField.destroy();
      const {mountSourcesField}=await import('/sources/js/field.js');window.sourceField=mountSourcesField(host);
    });
    assert.equal(await field.getByLabel('Displayed name',{exact:true}).inputValue(),'Local article name','Usage survives field reload');
    assert.equal(await field.locator('details.resource-usage-secondary').getAttribute('open'),null,'Advanced Usage is collapsed again after reload');
    await field.locator('.sources-block-delete').click();
    assert.equal(await field.locator('.sources-field-value').inputValue(),'','Delete last clears the native custom field');
    await field.locator('.sources-add-actions>button').first().click();
    await fieldCaption.fill('Reloaded field');
    await page.evaluate(()=>{const input=document.querySelector('[data-sources-field] .sources-field-value');const value=input.value;
      sourceField.destroy();input.value=value;input.dispatchEvent(new Event('change'));
    });
    await page.evaluate(async()=>{const {mountSourcesField}=await import('/sources/js/field.js');window.sourceField=mountSourcesField(document.querySelector('[data-sources-field]'))});
    assert.equal(await fieldCaption.inputValue(),'Reloaded field','Custom field survives remount from native JSON');
    const savedBeforePreviewSizing=await page.evaluate(()=>editor.getContent());
    await page.evaluate(async()=>{
      const doc=editor.getDoc(),link=doc.createElement('link');link.rel='stylesheet';link.href='/template-preview.css';
      await new Promise((resolve,reject)=>{link.onload=resolve;link.onerror=reject;doc.head.append(link)});
      const canvas=document.createElement('canvas');canvas.width=1200;canvas.height=900;
      const paint=canvas.getContext('2d');paint.fillStyle='#a4ccd7';paint.fillRect(0,0,1200,900);
      paint.fillStyle='#528575';paint.fillRect(0,600,1200,300);
      const image=canvas.toDataURL();
      const card=crop=>'<article class="library-card library-card--details"><div class="library-card__media library-card__media--article"><a class="library-card__thumb'+(crop?' library-card__thumb--smartcrop':'')+'"><img src="'+image+'" width="1200" height="900" alt="Preview fixture"'+(crop?' style="width:160%;max-width:none;left:-30%"':'')+'></a><span class="library-card__type-pin"><span class="fas fa-book"></span></span></div><div class="library-card__body"><h4 class="library-card__title">Details preview title</h4><p>'+('Long_content_for_narrow_preview_'.repeat(12))+'</p></div></article>';
      const surface=doc.querySelector('.sources-editor-token--render .sources-editor-token__body');
      surface.innerHTML='<section class="sources-section"><div class="sources-grid sources-layout--details"><div class="preview-resource">'+card(false)+'</div><div class="preview-resource">'+card(true)+'</div></div></section>';
    });
    for(const previewWidth of [520,900]){
      await page.evaluate(size=>{const token=editor.getDoc().querySelector('.sources-editor-token--render');token.style.width='100%';token.style.maxWidth=size+'px'},previewWidth);
      const metrics=await page.evaluate(()=>{
        const doc=editor.getDoc(),surface=doc.querySelector('.sources-editor-token--render .sources-editor-token__body'),grid=surface.querySelector('.sources-grid');
        const cards=[...grid.children].map(node=>node.querySelector('.library-card')||node),crop=cards[1].querySelector('img');
        return {width:surface.clientWidth,documentWidth:doc.documentElement.clientWidth,bodyWidth:doc.body.clientWidth,frameWidth:editor.getContainer().getBoundingClientRect().width,overflow:surface.scrollWidth>surface.clientWidth+1,gridWidth:grid.getBoundingClientRect().width,
          cards:cards.map(card=>({width:card.getBoundingClientRect().width,display:doc.defaultView.getComputedStyle(card).display})),
          cropMax:doc.defaultView.getComputedStyle(crop).maxWidth,cropWidth:crop.getBoundingClientRect().width,cropFrame:crop.parentElement.getBoundingClientRect().width};
      });
      assert.equal(metrics.overflow,false,'Details preview never overflows its editor container');
      if(width===1280 && previewWidth===900) assert.ok(metrics.width>767,'Desktop editor must exercise wide Details, including JCE: '+JSON.stringify(metrics));
      assert.ok(metrics.cards.every(card=>Math.abs(card.width-metrics.gridWidth)<2),'Details previews are full-width rows');
      assert.ok(metrics.cards.every(card=>card.display===(metrics.width<=767?'block':'grid')),'Details stacks according to preview width, not the page viewport');
      assert.equal(metrics.cropMax,'none','SmartCrop image bounds are not reset');
      assert.ok(metrics.cropWidth>metrics.cropFrame*1.4,'SmartCrop overscan remains intact');
    }
    assert.equal(await page.evaluate(()=>editor.getContent()),savedBeforePreviewSizing,'Preview dimensions never change saved tokens');
    await frame.locator('.sources-editor-token--render').first().screenshot({path:resolve(artifacts,'details-preview-'+(administrator?'admin':'site')+'-'+(legacy?'jce':'tiny')+'-'+width+'.png')});
    const titleSizing=await page.evaluate(()=>{
      const doc=editor.getDoc(),token=doc.querySelector('.sources-editor-token--render'),surface=token.querySelector('.sources-editor-token__body');
      token.style.maxWidth='1116px';
      const measure=heading=>{
        surface.innerHTML='<section class="sources-section">'+(heading?'<h2 class="sources-heading">Block title</h2>':'')+'<div class="sources-grid">'+
          '<article class="library-card library-card--cards" style="height:580px"><h4>Card title</h4></article>'.repeat(4)+'</div></section>';
        const grid=surface.querySelector('.sources-grid');
        const style=doc.defaultView.getComputedStyle(grid),columns=style.gridTemplateColumns.split(' ').length;
        const rows=Math.ceil(4/columns),gap=parseFloat(style.rowGap)||0;
        for(const card of grid.children) card.style.height=((570-gap*(rows-1))/rows)+'px';
        return {width:surface.clientWidth,columns:doc.defaultView.getComputedStyle(grid).gridTemplateColumns.split(' ').length,scroll:surface.scrollHeight>surface.clientHeight,gutter:doc.defaultView.getComputedStyle(surface).scrollbarGutter};
      };
      return {without:measure(false),with:measure(true)};
    });
    assert.equal(titleSizing.with.width,titleSizing.without.width,'A block title must not change the preview width through scrollbars');
    assert.equal(titleSizing.with.columns,titleSizing.without.columns,'Cards must retain the same column count with or without a block title');
    assert.equal(titleSizing.with.gutter,'stable','Editor preview reserves width even before its scrollbar appears');
    assert.equal(titleSizing.without.scroll,false,'Cards without a heading fit below the scroll threshold');
    assert.equal(titleSizing.with.scroll,true,'Adding the heading crosses the scroll threshold without changing columns');
    assert.equal(await page.evaluate(()=>editor.getContent()),savedBeforePreviewSizing,'Preview headings never change saved tokens');
    assert.deepEqual(errors,[]);
    console.log((administrator?'Atum':'Cassiopeia')+' '+(legacy?'JCE/compact':'TinyMCE/collection')+' '+width+': typed blocks, selection, sorting, dropdowns, save/reopen passed');
    await page.close();
  }
} finally {await browser.close();server.closeAllConnections();await new Promise(resolve=>server.close(resolve))}
