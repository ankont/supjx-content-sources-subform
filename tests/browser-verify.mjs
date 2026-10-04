import { chromium } from 'playwright';
import { createServer } from 'node:http';
import { readFile, mkdir } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const repo = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const browserMedia = resolve(repo, '../pkg_smartbrowser/package/component/media');
const joomlaSite = resolve(repo, '../../JoomlaTest/site');
const artifacts = resolve(repo, 'tests/artifacts');
await mkdir(artifacts, { recursive: true });
const i18n = {
  classic: 'Εμφάνιση κλασικής φόρμας', builder: 'Επιστροφή στον Sources Builder',
  addBlock: 'Προσθήκη block', addSources: 'Προσθήκη πηγών', duplicate: 'Αντιγραφή block',
  delete: 'Διαγραφή', up: 'Μετακίνηση πάνω', down: 'Μετακίνηση κάτω', actions: 'Ενέργειες block',
  sources: 'Επιλεγμένες πηγές', presentation: 'Εμφάνιση', advanced: 'Φίλτρα και επιπλέον επιλογές',
  untitled: 'Μπλοκ πηγών', empty: 'Δεν υπάρχουν μπλοκ πηγών',
  help: 'Οι αριθμοί BLOCK χρησιμοποιούνται κατά την εισαγωγή συγκεκριμένου block στο άρθρο.',
  sourceCount: '%s πηγές', tagCount: '%s ετικέτες',
  unavailable: 'Ο Builder δεν είναι διαθέσιμος. Χρησιμοποιήστε την κλασική φόρμα.',
  deleteConfirm: 'Διαγραφή block;',
};
const sourceOptions = { fieldName: 'blocks', fieldNames: { field10: 'block-title', field11: 'block-layout', field12: 'block-manual-articles', field13: 'block-tags', field14: 'block-categories', field15: 'block-limit' }, i18n };
const resources = ids => ids.map(id => ({
  id, title: id === 'article:99' ? 'Unavailable (article:99)' : ({ 'article:11': 'Γαλαξίας', 'article:4': 'Καινή Διαθήκη', 'article:2': '2ο Διαδικτυακό Σεμινάριο' }[id] || id),
  kind: 'item', type: id === 'article:99' ? 'unavailable' : 'article', unavailable: id === 'article:99',
  icon: 'fas fa-book', image: null, metadata: { cardSummary: 'Βιβλιοθήκη', id: id.split(':')[1] }, capabilities: {},
}));
const server = createServer(async (req, res) => {
  try {
    const url = new URL(req.url, 'http://localhost');
    let body, mime;
    if (url.pathname === '/') {
      const fixture = await readFile(resolve(repo, 'tests/fixtures/article-form.html'), 'utf8');
      const bootstrap = `<link rel="stylesheet" href="/media/system/css/joomla-fontawesome.min.css"><link rel="stylesheet" href="/smartbrowser/css/smartbrowser.css"><link rel="stylesheet" href="/builder.css">
        <style>body{font:14px/1.5 Arial,sans-serif;margin:0;background:#f5f6f8;color:#253540}.smartbrowser{font:inherit}</style>
        <script>
          window.testCollectionOptions={adapter:'articles',apiBaseUrl:location.origin+'/api?option=com_smartbrowser',csrfToken:'test-token'};
          window.testSourceOptions=${JSON.stringify(sourceOptions)};
          window.Joomla={getOptions:key=>key==='com_smartbrowser.collection'?testCollectionOptions:key==='plg_content_sources.builder'?testSourceOptions:{},Text:{_:key=>({COM_SMARTBROWSER_COLLECTION_TITLE:'Επιλεγμένες πηγές',COM_SMARTBROWSER_COLLECTION_REMOVE:'Αφαίρεση από τη συλλογή',COM_SMARTBROWSER_COLLECTION_EMPTY:'Δεν έχουν επιλεγεί πηγές',COM_SMARTBROWSER_GRID:'Κάρτες',COM_SMARTBROWSER_DETAILS:'Λεπτομέρειες',COM_SMARTBROWSER_NAME:'Τίτλος',JGRID_HEADING_ORDERING:'Σειρά συλλογής',COM_SMARTBROWSER_SORT_BY:'Ταξινόμηση'}[key]||key)},
          request:config=>{const controller=new AbortController();fetch(config.url,{method:config.method,body:config.data,headers:config.headers,signal:controller.signal}).then(r=>r.text()).then(config.onSuccess).catch(()=>config.onError({status:500}));return {abort:()=>controller.abort()}}};
          window.SmartBrowserPicker={open:async()=>[{id:'article:2',title:'2ο Διαδικτυακό Σεμινάριο',type:'article'}]};
        </script>
        <script defer src="/native.js"></script><script type="module" src="/smartbrowser/js/collection.js"></script><script type="module" src="/builder.js"></script>`;
      body = fixture.replace('</head>', bootstrap + '</head>');
      mime = 'text/html; charset=utf-8';
    } else if (url.pathname === '/api') {
      let raw = '';
      for await (const chunk of req) raw += chunk;
      const data = JSON.parse(raw);
      let ids = data.items.map(id => String(id).includes(':') ? String(id) : 'article:' + id);
      if (data.operation === 'reorder') {
        const selected = new Set(data.selection);
        const direction = data.direction === 'up' ? -1 : 1;
        const indices = direction < 0 ? ids.map((_, i) => i) : ids.map((_, i) => i).reverse();
        indices.forEach(i => { const j = i + direction; if (selected.has(ids[i]) && j >= 0 && j < ids.length && !selected.has(ids[j])) [ids[i], ids[j]] = [ids[j], ids[i]]; });
        body = JSON.stringify({ success: true, data: { items: ids } });
      } else body = JSON.stringify({ success: true, data: { identifiers: ids, resources: resources(ids), actions: [], presentation: { gridFields: [{ source: 'metadata.cardSummary' }], sortFields: [{ id: 'title', label: 'COM_SMARTBROWSER_NAME' }], columns: [{ id: 'title', label: 'COM_SMARTBROWSER_NAME', source: 'title' }] } } });
      mime = 'application/json';
    } else {
      const file = url.pathname === '/native.js' ? resolve(repo, 'tests/fixtures/joomla-field-subform.js')
        : url.pathname === '/builder.js' ? resolve(repo, 'package/plugins/content/sources/media/js/builder.js')
        : url.pathname === '/builder.css' ? resolve(repo, 'package/plugins/content/sources/media/css/builder.css')
        : url.pathname.startsWith('/smartbrowser/') ? resolve(browserMedia, url.pathname.slice('/smartbrowser/'.length))
        : url.pathname.startsWith('/media/') ? resolve(joomlaSite, url.pathname.slice(1)) : null;
      if (!file || (!file.startsWith(repo) && !file.startsWith(browserMedia) && !file.startsWith(joomlaSite))) throw new Error('Invalid asset');
      body = await readFile(file);
      mime = file.endsWith('.css') ? 'text/css' : file.endsWith('.woff2') ? 'font/woff2' : 'text/javascript';
    }
    res.writeHead(200, { 'Content-Type': mime });
    res.end(body);
  } catch { res.writeHead(404); res.end(); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const port = server.address().port;
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
  const errors = [];
  for (const viewport of [{ width: 1280, height: 1100 }, { width: 390, height: 844 }]) {
    const page = await browser.newPage({ viewport });
    page.on('pageerror', error => { errors.push(error.message); console.error(error.message); });
    page.on('console', message => { if (message.type() === 'error') console.error(message.text()); });
    page.on('response', response => { if (response.status() >= 400) console.error(response.status(), response.url()); });
    await page.goto('http://127.0.0.1:' + port);
    await page.locator('.sources-builder').waitFor({ state: 'visible' });
    await page.getByText('Γαλαξίας', { exact: true }).waitFor();
    assert.equal(await page.locator('.smartbrowser-collection').count(), 1);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, 'No horizontal overflow');
    await page.screenshot({ path: resolve(artifacts, viewport.width === 390 ? 'builder-mobile.png' : 'builder-desktop.png'), fullPage: true });
    await page.getByRole('button', { name: 'Προσθήκη πηγών', exact: true }).click();
    await page.getByText('2ο Διαδικτυακό Σεμινάριο', { exact: true }).waitFor();
    assert.deepEqual(await page.evaluate(() => new FormData(document.querySelector('form')).getAll('jform[com_fields][blocks][row0][field12][]')), ['11', '4', '99', '2']);
    await page.getByRole('button', { name: 'Εμφάνιση κλασικής φόρμας', exact: true }).click();
    await page.locator('#title0').fill('Classic edit');
    await page.getByRole('button', { name: 'Επιστροφή στον Sources Builder', exact: true }).click();
    await page.getByText('Classic edit', { exact: true }).waitFor();
    await page.close();
  }
  assert.deepEqual(errors, []);
  console.log('Actual SmartBrowser Collection View: desktop/mobile render, native selection, Classic round trip, no overflow or JS errors.');
} finally {
  await browser.close();
  server.close();
}
